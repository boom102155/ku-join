<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$event = active_event($pdo);
if (!$event) {
    http_response_code(404);
    exit('ไม่พบงานที่เปิดใช้งาน');
}

$eventId = (int) $event['id'];

$totalsStmt = $pdo->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(companion_count), 0) AS companions FROM registrations WHERE event_id = ?');
$totalsStmt->execute([$eventId]);
$totals = $totalsStmt->fetch() ?: ['total' => 0, 'companions' => 0];

$typesStmt = $pdo->prepare('SELECT pt.id, pt.name, COUNT(r.id) AS registration_count
    FROM participant_types pt
    LEFT JOIN registrations r ON r.participant_type_id = pt.id AND r.event_id = pt.event_id
    WHERE pt.event_id = ?
    GROUP BY pt.id, pt.name, pt.sort_order
    ORDER BY pt.sort_order, pt.id');
$typesStmt->execute([$eventId]);
$types = $typesStmt->fetchAll();

$slotsStmt = $pdo->prepare('SELECT id, slot_time, title FROM time_slots WHERE event_id = ? ORDER BY sort_order, id');
$slotsStmt->execute([$eventId]);
$slots = [];

foreach ($slotsStmt->fetchAll() as $slot) {
    $slot['type_counts'] = array_fill_keys(array_map(static fn ($type) => (int) $type['id'], $types), 0);
    $slot['total_count'] = 0;
    $slot['companion_count'] = 0;
    $slots[(int) $slot['id']] = $slot;
}

$slotCountsStmt = $pdo->prepare('SELECT rts.time_slot_id, r.participant_type_id, COUNT(DISTINCT r.id) AS registration_count,
        COALESCE(SUM(r.companion_count), 0) AS companion_count
    FROM registration_time_slots rts
    INNER JOIN registrations r ON r.id = rts.registration_id
    WHERE r.event_id = ?
    GROUP BY rts.time_slot_id, r.participant_type_id');
$slotCountsStmt->execute([$eventId]);

foreach ($slotCountsStmt->fetchAll() as $count) {
    $slotId = (int) $count['time_slot_id'];
    $typeId = (int) $count['participant_type_id'];

    if (!isset($slots[$slotId])) {
        continue;
    }

    $registrationCount = (int) $count['registration_count'];
    $slots[$slotId]['type_counts'][$typeId] = $registrationCount;
    $slots[$slotId]['total_count'] += $registrationCount;
    $slots[$slotId]['companion_count'] += (int) $count['companion_count'];
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="ku-join-summary-' . date('Ymd') . '.csv"');

echo "\xEF\xBB\xBF";
$output = fopen('php://output', 'w');

$headings = ['ช่วงเวลา / กิจกรรม'];
foreach ($types as $type) {
    $headings[] = $type['name'];
}
$headings[] = 'รวม (คน)';
$headings[] = 'รวม+ผู้ติดตาม';
fputcsv($output, $headings);

foreach ($slots as $slot) {
    $row = [trim($slot['slot_time'] . ' ' . $slot['title'])];
    foreach ($types as $type) {
        $row[] = $slot['type_counts'][(int) $type['id']] ?? 0;
    }
    $row[] = $slot['total_count'];
    $row[] = $slot['total_count'] + $slot['companion_count'];
    fputcsv($output, $row);
}

$totalRow = ['รวมผู้ลงทะเบียนทั้งงาน'];
foreach ($types as $type) {
    $totalRow[] = (int) $type['registration_count'];
}
$totalRow[] = (int) $totals['total'];
$totalRow[] = (int) $totals['total'] + (int) $totals['companions'];
fputcsv($output, $totalRow);

fclose($output);
