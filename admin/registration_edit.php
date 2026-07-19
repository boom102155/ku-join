<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/functions.php';

require_admin();

$registrationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$registrationId) {
    flash('error', 'ไม่พบรายการลงทะเบียนที่ต้องการแก้ไข');
    header('Location: registrations.php');
    exit;
}

$registrationStmt = $pdo->prepare('SELECT r.*, e.name AS event_name FROM registrations r INNER JOIN events e ON e.id = r.event_id WHERE r.id = ? LIMIT 1');
$registrationStmt->execute([$registrationId]);
$registration = $registrationStmt->fetch();

if (!$registration) {
    flash('error', 'ไม่พบรายการลงทะเบียนที่ต้องการแก้ไข');
    header('Location: registrations.php');
    exit;
}

$eventId = (int) $registration['event_id'];
$typesStmt = $pdo->prepare('SELECT * FROM participant_types WHERE event_id = ? ORDER BY sort_order, id');
$typesStmt->execute([$eventId]);
$types = $typesStmt->fetchAll();

$slotsStmt = $pdo->prepare('SELECT * FROM time_slots WHERE event_id = ? ORDER BY sort_order, id');
$slotsStmt->execute([$eventId]);
$slots = $slotsStmt->fetchAll();

$selectedSlotStmt = $pdo->prepare('SELECT time_slot_id FROM registration_time_slots WHERE registration_id = ?');
$selectedSlotStmt->execute([$registrationId]);
$selectedSlots = array_map('intval', $selectedSlotStmt->fetchAll(PDO::FETCH_COLUMN));
$formData = $registration;
$formError = '';
$allowedStatuses = ['ผู้บริหาร', 'อาจารย์อาวุโส_ผู้เกษียณ', 'อาจารย์', 'บุคลากร'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $typeId = filter_input(INPUT_POST, 'participant_type_id', FILTER_VALIDATE_INT);
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $representativeName = trim((string) ($_POST['representative_name'] ?? ''));
    $position = trim((string) ($_POST['position'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $orgName = trim((string) ($_POST['org_name'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? ''));
    $department = trim((string) ($_POST['department'] ?? ''));
    $companionCount = filter_input(INPUT_POST, 'companion_count', FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0, 'max_range' => 100]]);
    $rawSlots = $_POST['time_slots'] ?? [];
    $selectedSlots = is_array($rawSlots)
        ? array_values(array_unique(array_map('intval', array_filter($rawSlots, static fn ($slotId): bool => filter_var($slotId, FILTER_VALIDATE_INT) !== false && (int) $slotId > 0))))
        : [];

    $formData = array_merge($registration, [
        'participant_type_id' => $typeId ?: 0,
        'full_name' => $fullName,
        'representative_name' => $representativeName,
        'position' => $position,
        'phone' => $phone,
        'org_name' => $orgName,
        'status' => $status,
        'department' => $department,
        'companion_count' => $companionCount === false ? 0 : $companionCount,
    ]);

    $typeStmt = $pdo->prepare('SELECT * FROM participant_types WHERE id = ? AND event_id = ? LIMIT 1');
    $typeStmt->execute([$typeId, $eventId]);
    $type = $typeStmt->fetch();
    $length = static fn (string $value): int => function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);

    if (!$type) {
        $formError = 'กรุณาเลือกประเภทผู้เข้าร่วมที่ถูกต้อง';
    } elseif ($fullName === '' || $representativeName === '' || $position === '') {
        $formError = 'กรุณากรอกชื่อผู้ลงทะเบียน ชื่อผู้เข้าร่วม และตำแหน่งให้ครบถ้วน';
    } elseif ($type['requires_org_name'] && $orgName === '') {
        $formError = 'กรุณากรอกชื่อส่วนงานสำหรับประเภทผู้เข้าร่วมที่เลือก';
    } elseif ($type['requires_status_field'] && $status === '') {
        $formError = 'กรุณาเลือกสถานภาพสำหรับประเภทผู้เข้าร่วมที่เลือก';
    } elseif ($status !== '' && !in_array($status, $allowedStatuses, true)) {
        $formError = 'สถานภาพที่เลือกไม่ถูกต้อง';
    } elseif ($companionCount === false) {
        $formError = 'จำนวนผู้ติดตามต้องอยู่ระหว่าง 0–100 คน';
    } elseif ($phone !== '' && !preg_match('/^[0-9+()\-\s]{7,20}$/', $phone)) {
        $formError = 'กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง';
    } elseif (count($selectedSlots) > 50) {
        $formError = 'เลือกช่วงเวลาได้ไม่เกิน 50 รายการ';
    } else {
        foreach ([$fullName, $representativeName, $position, $orgName, $department] as $value) {
            if ($length($value) > 255) {
                $formError = 'ข้อมูลข้อความยาวเกินกำหนด';
                break;
            }
        }
    }

    if ($formError === '' && $selectedSlots) {
        $placeholders = implode(',', array_fill(0, count($selectedSlots), '?'));
        $validSlotsStmt = $pdo->prepare("SELECT id FROM time_slots WHERE event_id = ? AND id IN ($placeholders)");
        $validSlotsStmt->execute([$eventId, ...$selectedSlots]);
        if (count($validSlotsStmt->fetchAll(PDO::FETCH_COLUMN)) !== count($selectedSlots)) {
            $formError = 'มีช่วงเวลาที่เลือกไม่อยู่ในงานนี้';
        }
    }

    if ($formError === '') {
        try {
            $pdo->beginTransaction();
            $updateStmt = $pdo->prepare('UPDATE registrations SET participant_type_id = ?, org_name = ?, representative_name = ?, position = ?, companion_count = ?, phone = ?, full_name = ?, status = ?, department = ? WHERE id = ? AND event_id = ?');
            $updateStmt->execute([$typeId, $orgName ?: null, $representativeName, $position, $companionCount, $phone ?: null, $fullName, $status ?: null, $department ?: null, $registrationId, $eventId]);
            $pdo->prepare('DELETE FROM registration_time_slots WHERE registration_id = ?')->execute([$registrationId]);

            if ($selectedSlots) {
                $linkSlotStmt = $pdo->prepare('INSERT INTO registration_time_slots (registration_id, time_slot_id) VALUES (?, ?)');
                foreach ($selectedSlots as $slotId) {
                    $linkSlotStmt->execute([$registrationId, $slotId]);
                }
            }

            $pdo->commit();
            flash('success', 'แก้ไขรายการลงทะเบียนเรียบร้อย');
            header('Location: registrations.php');
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $formError = 'ไม่สามารถบันทึกการแก้ไขได้ กรุณาลองใหม่อีกครั้ง';
        }
    }
}

$adminTitle = 'แก้ไขรายการลงทะเบียน';
$adminPage = 'registrations';
require __DIR__ . '/header.php';
?>

<section class="admin-panel admin-edit-registration-panel" aria-labelledby="edit-registration-title">
  <h2 id="edit-registration-title">แก้ไขรายการลงทะเบียน</h2>
  <p class="admin-password-note">งาน: <strong><?= e($registration['event_name']) ?></strong></p>

  <?php if ($formError): ?><div class="form-error-summary" role="alert"><strong>ไม่สามารถบันทึกข้อมูลได้</strong><span><?= e($formError) ?></span></div><?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= $registrationId ?>">
    <div class="field">
      <label class="field-label" for="participant_type_id">ประเภทผู้เข้าร่วม <span class="required">*</span></label>
      <select class="select" id="participant_type_id" name="participant_type_id" required>
        <?php foreach ($types as $type): ?><option value="<?= (int) $type['id'] ?>"<?= (int) $formData['participant_type_id'] === (int) $type['id'] ? ' selected' : '' ?>><?= e($type['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field-grid">
      <div class="field"><label class="field-label" for="full_name">ชื่อผู้ลงทะเบียน <span class="required">*</span></label><input class="input" id="full_name" name="full_name" maxlength="255" value="<?= e($formData['full_name']) ?>" required></div>
      <div class="field"><label class="field-label" for="org_name">ส่วนงาน</label><input class="input" id="org_name" name="org_name" maxlength="255" value="<?= e($formData['org_name'] ?? '') ?>"></div>
      <div class="field"><label class="field-label" for="representative_name">ชื่อผู้เข้าร่วม <span class="required">*</span></label><input class="input" id="representative_name" name="representative_name" maxlength="255" value="<?= e($formData['representative_name'] ?? '') ?>" required></div>
      <div class="field"><label class="field-label" for="position">ตำแหน่ง <span class="required">*</span></label><input class="input" id="position" name="position" maxlength="255" value="<?= e($formData['position'] ?? '') ?>" required></div>
      <div class="field"><label class="field-label" for="phone">เบอร์โทรผู้ลงทะเบียน</label><input class="input" id="phone" name="phone" maxlength="20" value="<?= e($formData['phone'] ?? '') ?>" inputmode="tel" pattern="[0-9+()\-\s]{7,20}"></div>
      <div class="field"><label class="field-label" for="companion_count">จำนวนผู้ติดตาม</label><input class="input" id="companion_count" name="companion_count" type="number" min="0" max="100" value="<?= (int) $formData['companion_count'] ?>"></div>
      <div class="field"><label class="field-label" for="status">สถานภาพ</label><select class="select" id="status" name="status"><option value="">ไม่ระบุ</option><?php foreach ($allowedStatuses as $statusOption): ?><option value="<?= e($statusOption) ?>"<?= ($formData['status'] ?? '') === $statusOption ? ' selected' : '' ?>><?= e($statusOption) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label class="field-label" for="department">สังกัด</label><input class="input" id="department" name="department" maxlength="255" value="<?= e($formData['department'] ?? '') ?>"></div>
    </div>

    <hr class="section-rule">
    <h3 class="slots-title">ช่วงเวลาที่เข้าร่วม</h3>
    <?php $slotIds = array_map(static fn (array $slot): int => (int) $slot['id'], $slots); ?>
    <?php $allSlotsSelected = $slotIds !== [] && array_diff($slotIds, $selectedSlots) === []; ?>
    <label class="slot-select-all">
      <input type="checkbox" data-select-all-slots<?= $allSlotsSelected ? ' checked' : '' ?>>
      <span>เข้าร่วมทั้งหมด</span>
      <small>เลือกทุกช่วงเวลา</small>
    </label>
    <div class="slot-grid admin-edit-slot-grid">
      <?php foreach ($slots as $slot): ?>
        <label class="slot"><input type="checkbox" name="time_slots[]" value="<?= (int) $slot['id'] ?>" data-slot-choice<?= in_array((int) $slot['id'], $selectedSlots, true) ? ' checked' : '' ?>><time><?= e($slot['slot_time']) ?></time><span><strong><?= e($slot['title']) ?></strong><?php if ($slot['location']): ?><small><i class="bx bx-map-pin" aria-hidden="true"></i><?= e($slot['location']) ?></small><?php endif; ?></span></label>
      <?php endforeach; ?>
    </div>
    <div class="actions"><button class="button button-primary" type="submit">บันทึกการแก้ไข</button><a class="button button-outline" href="<?= e(admin_url('registrations.php')) ?>">ยกเลิก</a></div>
  </form>
</section>

<?php require __DIR__ . '/footer.php';
