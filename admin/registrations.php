<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/functions.php';
require_admin();

$event = active_event($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $registrationId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (($_POST['action'] ?? '') === 'delete' && $registrationId) {
        $pdo->prepare('DELETE FROM registrations WHERE id = ?')->execute([$registrationId]);
        flash('success', 'ลบข้อมูลลงทะเบียนเรียบร้อย');
    }
    header('Location: registrations.php');
    exit;
}

$rows = [];
$slots = [];
if ($event) {
    $slotStmt = $pdo->prepare('SELECT id, title FROM time_slots WHERE event_id = ? ORDER BY sort_order, id');
    $slotStmt->execute([$event['id']]);
    $slots = $slotStmt->fetchAll();

    $registrationStmt = $pdo->prepare(
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
    $registrationStmt->execute([$event['id']]);
    $rows = $registrationStmt->fetchAll();
}

$adminTitle = 'รายการลงทะเบียน';
$adminPage = 'registrations';
require __DIR__ . '/header.php';
?>

<?php if (!$event): ?>
  <div class="empty">
    <h2>ยังไม่มีงานที่เปิดใช้งาน</h2>
    <p>สร้างและเปิดใช้งานงานก่อน จึงจะแสดงรายการลงทะเบียนได้</p>
  </div>
<?php else: ?>
  <section class="admin-registration-context" aria-labelledby="registration-event-title">
    <div>
      <p class="admin-registration-eyebrow">งานที่เปิดใช้งาน</p>
      <h2 id="registration-event-title"><?= e($event['name']) ?></h2>
      <p>มีรายการลงทะเบียนทั้งหมด <strong><?= count($rows) ?></strong> รายการ</p>
    </div>
    <a class="button button-outline list-export-link" href="<?= e(admin_url('export.php')) ?>"><i class="bx bx-download" aria-hidden="true"></i>ดาวน์โหลด CSV</a>
  </section>

  <section class="admin-registration-workspace" data-participant-list>
  <section class="list-controls admin-registration-controls" aria-label="ตัวกรองรายการลงทะเบียน">
    <div class="list-filter-group">
      <span class="list-filter-label">แสดงผู้ลงทะเบียนตามกิจกรรม:</span>
      <div class="list-filter-buttons" role="group" aria-label="กรองตามกิจกรรม">
        <button class="list-filter is-active" type="button" data-slot-filter="all" aria-pressed="true">ทั้งหมด <b><?= count($rows) ?></b></button>
        <?php foreach ($slots as $slot): ?>
          <?php $slotCount = count(array_filter($rows, fn(array $row): bool => in_array((string) $slot['id'], explode(',', (string) $row['slot_ids'])))); ?>
          <button class="list-filter" type="button" data-slot-filter="<?= (int) $slot['id'] ?>" aria-pressed="false"><i class="bx bx-time-five" aria-hidden="true"></i><?= e($slot['title']) ?> <b><?= $slotCount ?></b></button>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="participant-table-panel admin-registration-list">
    <div class="table-tools">
      <label class="table-length">แสดง
        <select class="select" data-list-length aria-label="จำนวนแถวต่อหน้า"><option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option></select>
        แถว
      </label>
      <label class="table-search">ค้นหา <input class="input" type="search" data-list-search placeholder="ชื่อ, ส่วนงาน, ตำแหน่ง" autocomplete="off"></label>
    </div>

    <div class="table-wrap participant-table-wrap">
      <table class="data-table participant-table">
        <thead><tr><th>#</th><th>ประเภท</th><th>ชื่อผู้ลงทะเบียน / ส่วนงาน</th><th>ชื่อผู้เข้าร่วม / รายละเอียด</th><th>ผู้ติดตาม</th><th>ช่วงเวลาที่เข้าร่วม</th><th>เบอร์โทรผู้ลงทะเบียน</th><th>จัดการ</th></tr></thead>
        <tbody data-list-body>
        <?php foreach ($rows as $index => $row): ?>
          <?php $typeTone = ((int) $row['participant_type_id'] - 1) % 6 + 1; ?>
          <tr data-list-row data-slot-ids="<?= e((string) $row['slot_ids']) ?>">
            <td data-row-number><?= $index + 1 ?></td>
            <td><span class="participant-type participant-type--tone-<?= $typeTone ?>"><?= e($row['type_name']) ?></span></td>
            <td><strong class="participant-org"><?= e($row['org_name'] ?: '— ไม่ระบุส่วนงาน —') ?></strong><span class="participant-name"><?= e($row['full_name']) ?></span></td>
            <td><span class="participant-detail"><?= e($row['representative_name'] ?: '—') ?></span><span class="participant-department"><?= e($row['position'] ?: '—') ?></span><?php if ($row['department']): ?><span class="participant-department"><i class="bx bx-id-card" aria-hidden="true"></i><?= e($row['department']) ?></span><?php endif; ?></td>
            <td><span class="companion-count"><?= (int) $row['companion_count'] ?> คน</span></td>
            <td><?php if ($row['slot_labels']): ?><?php foreach (explode('||', $row['slot_labels']) as $label): ?><span class="slot-chip"><?= e($label) ?></span><?php endforeach; ?><?php else: ?><span class="no-slots">— ไม่ระบุ —</span><?php endif; ?></td>
            <td><?= e($row['phone'] ?: '—') ?></td>
            <td class="registration-action"><div class="inline-form"><a class="button button-outline button-small" href="<?= e(admin_url('registration_edit.php?id=' . (int) $row['id'])) ?>"><i class="bx bx-edit-alt" aria-hidden="true"></i>แก้ไข</a><form method="post" onsubmit="return confirm('ลบรายการลงทะเบียนนี้หรือไม่?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="button button-danger button-small">ลบ</button></form></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div class="table-empty" data-list-empty hidden>ไม่พบรายการที่ตรงกับเงื่อนไข</div>
    </div>

    <footer class="table-footer">
      <p data-list-status aria-live="polite">แสดง 0 ถึง 0 จาก <?= count($rows) ?> แถว</p>
      <nav class="list-pagination" aria-label="แบ่งหน้ารายการลงทะเบียน" data-list-pagination></nav>
    </footer>
  </section>
  </section>
<?php endif; ?>

<?php require __DIR__ . '/footer.php';
