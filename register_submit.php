<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('index.php'));
    exit;
}

verify_csrf();

$eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
$typeId = filter_input(INPUT_POST, 'participant_type_id', FILTER_VALIDATE_INT);
$fullName = trim((string) ($_POST['full_name'] ?? ''));
$representative = trim((string) ($_POST['representative_name'] ?? ''));
$position = trim((string) ($_POST['position'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$org = trim((string) ($_POST['org_name'] ?? ''));
$status = trim((string) ($_POST['status'] ?? ''));
$department = trim((string) ($_POST['department'] ?? ''));
$companion = filter_input(INPUT_POST, 'companion_count', FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0, 'max_range' => 100]]);
$rawSlots = $_POST['time_slots'] ?? [];
$slots = is_array($rawSlots)
    ? array_values(array_unique(array_map('intval', array_filter($rawSlots, static fn($id): bool => filter_var($id, FILTER_VALIDATE_INT) !== false && (int) $id > 0))))
    : [];

$old = [
    'participant_type_id' => (string) ($typeId ?: ''),
    'full_name' => $fullName,
    'representative_name' => $representative,
    'position' => $position,
    'phone' => $phone,
    'org_name' => $org,
    'status' => $status,
    'department' => $department,
    'companion_count' => (string) ($companion === false ? 0 : $companion),
    'time_slots' => $slots,
];

$fail = static function (string $message, array $fields = []) use ($old): void {
    $_SESSION['registration_old'] = $old;
    $_SESSION['registration_errors'] = $fields;
    flash('error', $message);
    header('Location: ' . url('index.php'));
    exit;
};

$length = static fn(string $value): int => function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
$errors = [];

if (!$eventId || !$typeId) $errors['participant_type_id'] = 'กรุณาเลือกประเภทผู้เข้าร่วม';
if ($fullName === '') $errors['full_name'] = 'กรุณากรอกชื่อ-นามสกุลผู้ลงทะเบียน';
if ($representative === '') $errors['representative_name'] = 'กรุณากรอกชื่อผู้แทน';
if ($position === '') $errors['position'] = 'กรุณากรอกตำแหน่ง';
foreach (['full_name' => $fullName, 'representative_name' => $representative, 'position' => $position, 'org_name' => $org, 'department' => $department] as $field => $value) {
    if ($length($value) > 255) $errors[$field] = 'ข้อมูลยาวเกินกำหนด';
}
if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{7,20}$/', $phone)) $errors['phone'] = 'กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง';
if ($companion === false) $errors['companion_count'] = 'จำนวนผู้ติดตามต้องอยู่ระหว่าง 0–100 คน';
if (count($slots) > 50) $errors['time_slots'] = 'เลือกช่วงเวลาได้ไม่เกิน 50 รายการ';
if ($errors) $fail('กรุณาตรวจสอบข้อมูลที่ระบุไว้', $errors);

$eventStmt = $pdo->prepare('SELECT id FROM events WHERE id = ? AND is_active = 1');
$eventStmt->execute([$eventId]);
if (!$eventStmt->fetch()) $fail('งานนี้ยังไม่เปิดรับลงทะเบียน');

$typeStmt = $pdo->prepare('SELECT * FROM participant_types WHERE id = ? AND event_id = ?');
$typeStmt->execute([$typeId, $eventId]);
$type = $typeStmt->fetch();
if (!$type) $fail('ไม่พบประเภทผู้เข้าร่วมที่เลือก');

if ($type['requires_org_name'] && $org === '') $errors['org_name'] = 'กรุณากรอกชื่อส่วนงาน';
if ($type['requires_status_field'] && $status === '') $errors['status'] = 'กรุณาเลือกสถานภาพ';
$allowedStatuses = ['ผู้บริหาร', 'อาจารย์อาวุโส_ผู้เกษียณ', 'อาจารย์', 'บุคลากร'];
if ($status !== '' && !in_array($status, $allowedStatuses, true)) $errors['status'] = 'สถานภาพที่เลือกไม่ถูกต้อง';
if ($errors) $fail('กรุณากรอกข้อมูลให้ครบถ้วน', $errors);

if ($slots) {
    $placeholders = implode(',', array_fill(0, count($slots), '?'));
    $slotStmt = $pdo->prepare("SELECT id FROM time_slots WHERE event_id = ? AND id IN ($placeholders)");
    $slotStmt->execute([$eventId, ...$slots]);
    if (count($slotStmt->fetchAll(PDO::FETCH_COLUMN)) !== count($slots)) {
        $fail('มีช่วงเวลาที่เลือกไม่อยู่ในงานนี้', ['time_slots' => 'ช่วงเวลาที่เลือกไม่ถูกต้อง']);
    }
}

$fingerprint = hash('sha256', json_encode([$eventId, $typeId, $fullName, $representative, $position, $phone, $org, $status, $department, $companion, $slots], JSON_UNESCAPED_UNICODE));
if (($_SESSION['last_registration_fingerprint'] ?? '') === $fingerprint && (time() - (int) ($_SESSION['last_registration_at'] ?? 0)) < 20) {
    $fail('รายการนี้ถูกบันทึกไปแล้ว กรุณารอสักครู่ก่อนส่งซ้ำ');
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO registrations (event_id, participant_type_id, org_name, representative_name, position, companion_count, phone, full_name, status, department, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$eventId, $typeId, $org ?: null, $representative, $position, $companion, $phone ?: null, $fullName, $status ?: null, $department ?: null, $_SERVER['REMOTE_ADDR'] ?? null]);
    $registrationId = (int) $pdo->lastInsertId();

    if ($slots) {
        $link = $pdo->prepare('INSERT INTO registration_time_slots (registration_id, time_slot_id) VALUES (?, ?)');
        foreach ($slots as $slot) $link->execute([$registrationId, $slot]);
    }

    $pdo->commit();
    $_SESSION['last_registration_fingerprint'] = $fingerprint;
    $_SESSION['last_registration_at'] = time();
    unset($_SESSION['registration_old'], $_SESSION['registration_errors']);
    flash('success', 'ลงทะเบียนเรียบร้อยแล้ว ขอบคุณที่เข้าร่วมงาน');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $fail('ไม่สามารถบันทึกการลงทะเบียนได้ กรุณาลองใหม่อีกครั้ง');
}

header('Location: ' . url('index.php'));
exit;
