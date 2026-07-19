<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$event = active_event($pdo);
$eventId = (int) ($event['id'] ?? 0);
$total = 0;
$companions = 0;
$types = [];
$slotSummaries = [];

if ($eventId) {
    $totalStmt = $pdo->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(companion_count), 0) AS companions FROM registrations WHERE event_id = ?');
    $totalStmt->execute([$eventId]);
    $totals = $totalStmt->fetch();
    $total = (int) $totals['total'];
    $companions = (int) $totals['companions'];

    $typeStmt = $pdo->prepare(
        'SELECT pt.id, pt.name, COUNT(r.id) AS registration_count
         FROM participant_types pt
         LEFT JOIN registrations r ON r.participant_type_id = pt.id AND r.event_id = pt.event_id
         WHERE pt.event_id = ?
         GROUP BY pt.id
         ORDER BY pt.sort_order, pt.id'
    );
    $typeStmt->execute([$eventId]);
    $types = $typeStmt->fetchAll();

    $slotStmt = $pdo->prepare('SELECT id, slot_time, title FROM time_slots WHERE event_id = ? ORDER BY sort_order, id');
    $slotStmt->execute([$eventId]);
    foreach ($slotStmt->fetchAll() as $slot) {
        $slot['type_counts'] = array_fill_keys(array_map('intval', array_column($types, 'id')), 0);
        $slot['total_count'] = 0;
        $slot['companion_count'] = 0;
        $slotSummaries[(int) $slot['id']] = $slot;
    }

    $slotCountStmt = $pdo->prepare(
        'SELECT rts.time_slot_id, r.participant_type_id, COUNT(DISTINCT r.id) AS registration_count,
                COALESCE(SUM(r.companion_count), 0) AS companion_count
         FROM registration_time_slots rts
         INNER JOIN registrations r ON r.id = rts.registration_id
         WHERE r.event_id = ?
         GROUP BY rts.time_slot_id, r.participant_type_id'
    );
    $slotCountStmt->execute([$eventId]);
    foreach ($slotCountStmt->fetchAll() as $count) {
        $slotId = (int) $count['time_slot_id'];
        $typeId = (int) $count['participant_type_id'];
        if (!isset($slotSummaries[$slotId])) continue;
        $registrationCount = (int) $count['registration_count'];
        $slotSummaries[$slotId]['type_counts'][$typeId] = $registrationCount;
        $slotSummaries[$slotId]['total_count'] += $registrationCount;
        $slotSummaries[$slotId]['companion_count'] += (int) $count['companion_count'];
    }
}

$chartColors = ['#1f7af5', '#7549c8', '#08775e', '#bd7b12', '#bf4b75', '#5267ba'];
$chartData = [
    'labels' => array_values(array_map(fn(array $slot): string => $slot['slot_time'], $slotSummaries)),
    'datasets' => array_map(
        fn(array $type, int $index): array => [
            'label' => $type['name'],
            'color' => $chartColors[$index % count($chartColors)],
            'values' => array_values(array_map(fn(array $slot): int => (int) ($slot['type_counts'][(int) $type['id']] ?? 0), $slotSummaries)),
        ],
        $types,
        array_keys($types)
    ),
];

$pageTitle = 'สรุปผล';
$activePage = 'summary';
require __DIR__ . '/includes/header.php';
?>

<section class="page summary-page">
  <?php if (!$event): ?>
    <div class="empty"><h1>ยังไม่มีงานที่เปิดใช้งาน</h1><p>ผู้ดูแลระบบสามารถสร้างและเปิดใช้งานงานได้จากหน้าแอดมิน</p></div>
  <?php else: ?>
    <header class="summary-page-header">
      <h1><i class="bx bx-bar-chart-alt-2" aria-hidden="true"></i>สรุปผลการลงทะเบียน</h1>
      <p><?= e($event['name']) ?></p>
    </header>

    <section class="summary-metrics" aria-label="ภาพรวมการลงทะเบียน">
      <?php foreach ($types as $index => $type): ?>
        <article class="summary-metric summary-metric--tone-<?= ($index % 6) + 1 ?>">
          <i class="bx <?= ['bx-buildings', 'bx-graduation', 'bx-id-card'][$index] ?? 'bx-category' ?>" aria-hidden="true"></i>
          <b><?= (int) $type['registration_count'] ?></b>
          <span><?= e($type['name']) ?></span>
        </article>
      <?php endforeach; ?>
      <article class="summary-metric summary-metric--total">
        <i class="bx bx-group" aria-hidden="true"></i>
        <b><?= $total ?></b>
        <span>ผู้ลงทะเบียนทั้งหมด</span>
      </article>
    </section>

    <p class="summary-estimate"><i class="bx bx-group" aria-hidden="true"></i>ประมาณการผู้เข้าร่วมงานรวมผู้ติดตาม <strong><?= $total + $companions ?> คน</strong> <span>(ผู้ลงทะเบียน <?= $total ?> คน + ผู้ติดตาม <?= $companions ?> คน)</span></p>

    <section class="summary-panel" aria-labelledby="summary-chart-title">
      <header class="summary-panel-header"><h2 id="summary-chart-title"><i class="bx bx-bar-chart-alt-2" aria-hidden="true"></i>จำนวนผู้เข้าร่วมในแต่ละช่วงเวลา <small>(แยกตามประเภท)</small></h2></header>
      <?php if ($slotSummaries && $types): ?>
        <div class="summary-chart-wrap"><canvas class="summary-chart" data-registration-chart aria-label="กราฟแท่งซ้อนแสดงจำนวนผู้เข้าร่วมแต่ละช่วงเวลา"></canvas></div>
        <div class="summary-chart-legend" aria-label="คำอธิบายสีกราฟ">
          <?php foreach ($chartData['datasets'] as $dataset): ?><span><i style="--legend-color: <?= e($dataset['color']) ?>"></i><?= e($dataset['label']) ?></span><?php endforeach; ?>
        </div>
        <script id="summary-chart-data" type="application/json"><?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
      <?php else: ?>
        <div class="summary-chart-empty">ยังไม่มีข้อมูลช่วงเวลาหรือประเภทผู้เข้าร่วมสำหรับสร้างกราฟ</div>
      <?php endif; ?>
    </section>

    <section class="summary-panel" aria-labelledby="summary-table-title">
      <header class="summary-panel-header summary-panel-header--actions">
        <h2 id="summary-table-title"><i class="bx bx-list-ul" aria-hidden="true"></i>ตารางสรุปจำนวนผู้เข้าร่วมแต่ละช่วงเวลา</h2>
        <a class="button button-outline button-small summary-export-link" href="<?= e(url('summary_export.php')) ?>">
          <i class="bx bx-download" aria-hidden="true"></i>ดาวน์โหลด CSV
        </a>
      </header>
      <div class="table-wrap summary-table-wrap">
        <table class="data-table summary-table">
          <thead><tr><th>ช่วงเวลา / กิจกรรม</th><?php foreach ($types as $type): ?><th><?= e($type['name']) ?></th><?php endforeach; ?><th>รวม (คน)</th><th>รวม+ผู้ติดตาม</th></tr></thead>
          <tbody>
          <?php foreach ($slotSummaries as $slot): ?>
            <tr><td><strong><?= e($slot['slot_time']) ?></strong><span><?= e($slot['title']) ?></span></td><?php foreach ($types as $type): ?><td><?= (int) ($slot['type_counts'][(int) $type['id']] ?? 0) ?></td><?php endforeach; ?><td><strong><?= (int) $slot['total_count'] ?></strong></td><td><strong><?= (int) $slot['total_count'] + (int) $slot['companion_count'] ?></strong></td></tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot><tr><th>รวมผู้ลงทะเบียนทั้งงาน</th><?php foreach ($types as $type): ?><th><?= (int) $type['registration_count'] ?></th><?php endforeach; ?><th><?= $total ?></th><th><?= $total + $companions ?></th></tr></tfoot>
        </table>
      </div>
      <p class="summary-note">* ตัวเลขในตารางคือจำนวนผู้ลงทะเบียนที่เลือกเข้าร่วมในแต่ละช่วงเวลา โดยผู้ลงทะเบียนหนึ่งคนเลือกได้มากกว่าหนึ่งช่วง</p>
    </section>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php';
