<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$event = active_event($pdo);
$eventId = filter_input(INPUT_GET, 'event', FILTER_VALIDATE_INT) ?: ($event['id'] ?? 0);
if (!$eventId) {
    http_response_code(404);
    exit('ไม่พบงาน');
}

$stmt = $pdo->prepare(
    "SELECT r.full_name, pt.name AS participant_type, r.org_name, r.position, r.department,
            r.companion_count, r.phone,
            GROUP_CONCAT(CONCAT(ts.slot_time, ' ', ts.title) ORDER BY ts.sort_order SEPARATOR ' | ') AS slots
     FROM registrations r
     JOIN participant_types pt ON pt.id = r.participant_type_id
     LEFT JOIN registration_time_slots rts ON rts.registration_id = r.id
     LEFT JOIN time_slots ts ON ts.id = rts.time_slot_id
     WHERE r.event_id = ?
     GROUP BY r.id
     ORDER BY r.created_at DESC"
);
$stmt->execute([$eventId]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=ku-join-participants-' . date('Ymd') . '.csv');
echo "\xEF\xBB\xBF";
$output = fopen('php://output', 'w');
fputcsv($output, ['ชื่อ-นามสกุล', 'ประเภท', 'ส่วนงาน', 'ตำแหน่ง', 'สังกัด', 'ผู้ติดตาม', 'โทรศัพท์', 'ช่วงเวลาที่เข้าร่วม']);
foreach ($stmt as $row) {
    fputcsv($output, [$row['full_name'], $row['participant_type'], $row['org_name'], $row['position'], $row['department'], $row['companion_count'], $row['phone'], $row['slots']]);
}
fclose($output);
