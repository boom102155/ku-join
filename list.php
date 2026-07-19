<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$event = active_event($pdo);
$eventId = filter_input(INPUT_GET, 'event', FILTER_VALIDATE_INT) ?: ($event['id'] ?? 0);
$rows = [];
$slots = [];

if ($eventId) {
    $slotStmt = $pdo->prepare('SELECT id, slot_time, title FROM time_slots WHERE event_id = ? ORDER BY sort_order, id');
    $slotStmt->execute([$eventId]);
    $slots = $slotStmt->fetchAll();

    $listStmt = $pdo->prepare(
        "SELECT r.*, pt.name AS type_name,
                GROUP_CONCAT(DISTINCT ts.id ORDER BY ts.sort_order SEPARATOR ',') AS slot_ids,
                GROUP_CONCAT(DISTINCT CONCAT(ts.slot_time, ' ', ts.title) ORDER BY ts.sort_order SEPARATOR '||') AS slot_labels
         FROM registrations r
         JOIN participant_types pt ON pt.id = r.participant_type_id
         LEFT JOIN registration_time_slots rts ON rts.registration_id = r.id
         LEFT JOIN time_slots ts ON ts.id = rts.time_slot_id
         WHERE r.event_id = ?
         GROUP BY r.id
         ORDER BY r.created_at DESC"
    );
    $listStmt->execute([$eventId]);
    $rows = $listStmt->fetchAll();
}

$pageTitle = 'รายชื่อผู้เข้าร่วม';
$activePage = 'list';
require __DIR__ . '/includes/header.php';
?>
<section class="page list-page" data-participant-list>
  <header class="list-page-header">
    <div>
      <h1><i class="bx bx-group" aria-hidden="true"></i>รายชื่อผู้เข้าร่วมงาน</h1>
      <p>ค้นหา กรอง และตรวจสอบข้อมูลการลงทะเบียน</p>
    </div>
    <a class="button button-primary list-register-link" href="<?= e(url('index.php')) ?>"><i class="bx bx-edit-alt" aria-hidden="true"></i>ลงทะเบียนเพิ่ม</a>
  </header>

  <?php if (!$event): ?>
    <div class="empty"><h2>ยังไม่มี งาน/กิจกรรม/โครงการ ที่เปิดรับลงทะเบียน</h2></div>
  <?php else: ?>
    <section class="list-controls" aria-label="ตัวกรองรายชื่อผู้เข้าร่วม">
      <div class="list-filter-group">
        <span class="list-filter-label">แสดงรายชื่อผู้เข้าร่วม:</span>
        <div class="list-filter-buttons" role="group" aria-label="กรองตามกิจกรรม">
          <button class="list-filter is-active" type="button" data-slot-filter="all" aria-pressed="true">ทั้งหมด <b><?= count($rows) ?></b></button>
          <?php foreach ($slots as $slot): ?>
            <?php $slotCount = count(array_filter($rows, fn(array $row): bool => in_array((string) $slot['id'], explode(',', (string) $row['slot_ids'])))); ?>
            <button class="list-filter" type="button" data-slot-filter="<?= (int) $slot['id'] ?>" aria-pressed="false"><i class="bx bx-time-five" aria-hidden="true"></i><?= e($slot['title']) ?> <b><?= $slotCount ?></b></button>
          <?php endforeach; ?>
        </div>
      </div>
      <a class="button button-outline list-export-link" href="<?= e(url('list_export.php?event=' . $eventId)) ?>"><i class="bx bx-download" aria-hidden="true"></i>ดาวน์โหลดรายชื่อ (CSV)</a>
    </section>

    <section class="participant-table-panel">
      <div class="table-tools">
        <label class="table-length">แสดง
          <select class="select" data-list-length aria-label="จำนวนแถวต่อหน้า"><option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option></select>
          แถว
        </label>
        <label class="table-search">ค้นหา <input class="input" type="search" data-list-search placeholder="ชื่อ, ส่วนงาน, ตำแหน่ง" autocomplete="off"></label>
      </div>

      <div class="table-wrap participant-table-wrap">
        <table class="data-table participant-table">
          <thead><tr><th>#</th><th>ประเภท</th><th>ชื่อผู้ลงทะเบียน / ส่วนงาน</th><th>ชื่อผู้เข้าร่วม / รายละเอียด</th><th>ผู้ติดตาม</th><th>ช่วงเวลาที่เข้าร่วม</th></tr></thead>
          <tbody data-list-body>
          <?php foreach ($rows as $index => $row): ?>
            <tr data-list-row data-slot-ids="<?= e((string) $row['slot_ids']) ?>">
              <td data-row-number><?= $index + 1 ?></td>
              <?php $typeTone = ((int) $row['participant_type_id'] - 1) % 6 + 1; ?>
              <td><span class="participant-type participant-type--tone-<?= $typeTone ?>"><?= e($row['type_name']) ?></span></td>
              <td><strong class="participant-org"><?= e($row['org_name'] ?: '— ไม่ระบุส่วนงาน —') ?></strong><span class="participant-name"><?= e($row['full_name']) ?></span></td>
              <td><span class="participant-detail"><?= e($row['representative_name'] ?: '—') ?></span><span class="participant-department"><?= e($row['position'] ?: '—') ?></span><?php if ($row['department']): ?><span class="participant-department"><i class="bx bx-id-card" aria-hidden="true"></i><?= e($row['department']) ?></span><?php endif; ?></td>
              <td><span class="companion-count"><?= (int) $row['companion_count'] ?> คน</span></td>
              <td><?php if ($row['slot_labels']): ?><?php foreach (explode('||', $row['slot_labels']) as $label): ?><span class="slot-chip"><?= e($label) ?></span><?php endforeach; ?><?php else: ?><span class="no-slots">— ไม่ระบุ —</span><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="table-empty" data-list-empty hidden>ไม่พบรายชื่อที่ตรงกับเงื่อนไข</div>
      </div>

      <footer class="table-footer">
        <p data-list-status aria-live="polite">แสดง 0 ถึง 0 จาก <?= count($rows) ?> แถว</p>
        <nav class="list-pagination" aria-label="แบ่งหน้ารายชื่อ" data-list-pagination></nav>
      </footer>
    </section>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php';
