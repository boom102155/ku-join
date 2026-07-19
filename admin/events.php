<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/functions.php';

require_admin();

$defaultParticipantTypes = [
    ['ผู้บริหารมหาวิทยาลัยเกษตรศาสตร์', 1, 0, 1],
    ['คณบดี / ผู้อำนวยการสำนัก-สถาบัน', 1, 0, 2],
    ['ผู้บริหารและบุคลากร', 1, 1, 3],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $name = trim($_POST['name'] ?? '');
        $slug = preg_replace('/[^a-z0-9-]/', '-', strtolower(trim($_POST['slug'] ?? '')));
        $date = $_POST['event_date'] ?? '';
        $location = trim($_POST['location'] ?? '');
        $active = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '' || $slug === '' || !$date) {
            flash('error', 'กรุณากรอกชื่องาน รหัสงาน และวันจัดงาน');
        } else {
            try {
                $pdo->beginTransaction();

                if ($active) {
                    $pdo->exec('UPDATE events SET is_active = 0');
                }

                if ($id) {
                    $stmt = $pdo->prepare('UPDATE events SET name = ?, slug = ?, event_date = ?, location = ?, is_active = ? WHERE id = ?');
                    $stmt->execute([$name, $slug, $date, $location ?: null, $active, $id]);
                    $successMessage = 'บันทึกงานเรียบร้อย';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO events(name, slug, event_date, location, is_active) VALUES(?,?,?,?,?)');
                    $stmt->execute([$name, $slug, $date, $location ?: null, $active]);

                    $eventId = (int) $pdo->lastInsertId();
                    $typeStmt = $pdo->prepare('INSERT INTO participant_types(event_id, name, requires_org_name, requires_status_field, sort_order) VALUES(?,?,?,?,?)');
                    foreach ($defaultParticipantTypes as [$typeName, $requiresOrgName, $requiresStatusField, $sortOrder]) {
                        $typeStmt->execute([$eventId, $typeName, $requiresOrgName, $requiresStatusField, $sortOrder]);
                    }

                    $successMessage = 'สร้างงานและเพิ่มประเภทผู้เข้าร่วมเริ่มต้นเรียบร้อย';
                }

                $pdo->commit();
                flash('success', $successMessage);
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                flash('error', 'ไม่สามารถบันทึกงานได้ กรุณาตรวจสอบรหัสงานว่าไม่ซ้ำกัน');
            }
        }
    } elseif ($action === 'toggle_active' && ($id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT))) {
        try {
            $pdo->beginTransaction();
            $eventStmt = $pdo->prepare('SELECT id, is_active FROM events WHERE id = ? FOR UPDATE');
            $eventStmt->execute([$id]);
            $event = $eventStmt->fetch();

            if (!$event) {
                throw new RuntimeException('ไม่พบงาน');
            }

            $isActive = !(bool) $event['is_active'];
            if ($isActive) {
                $pdo->exec('UPDATE events SET is_active = 0');
            }
            $pdo->prepare('UPDATE events SET is_active = ? WHERE id = ?')->execute([(int) $isActive, $id]);
            $pdo->commit();

            flash('success', $isActive ? 'เปิดรับลงทะเบียนสำหรับงานนี้แล้ว' : 'ปิดรับลงทะเบียนสำหรับงานนี้แล้ว');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', 'ไม่สามารถเปลี่ยนสถานะงานได้');
        }
    } elseif ($action === 'delete' && ($id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT))) {
        $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
        flash('success', 'ลบงานเรียบร้อย');
    }

    header('Location: events.php');
    exit;
}

$edit = null;
if ($id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT)) {
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$id]);
    $edit = $stmt->fetch();
}

$events = $pdo->query('SELECT * FROM events ORDER BY event_date DESC')->fetchAll();
$adminTitle = 'งาน / กิจกรรม / โครงการ';
$adminPage = 'events';
require __DIR__ . '/header.php';
?>

<div class="admin-panel">
  <h2><?= $edit ? 'แก้ไขงาน' : 'สร้างงานใหม่' ?></h2>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <div class="field-grid">
      <div class="field span-full"><label class="field-label">ชื่องาน</label><input class="input" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
      <div class="field"><label class="field-label">รหัสงาน (slug)</label><input class="input" name="slug" required pattern="[a-z0-9-]+" value="<?= e($edit['slug'] ?? '') ?>" placeholder="ku-join-2026"></div>
      <div class="field"><label class="field-label">วันที่จัด</label><input class="input" type="date" name="event_date" required value="<?= e($edit['event_date'] ?? '') ?>"></div>
      <div class="field span-full"><label class="field-label">สถานที่</label><input class="input" name="location" value="<?= e($edit['location'] ?? '') ?>"></div>
    </div>
    <label><input type="checkbox" name="is_active" <?= !isset($edit) || $edit['is_active'] ? 'checked' : '' ?>> เปิดรับลงทะเบียน (มีได้หนึ่งงาน)</label>
    <div class="actions"><button class="button button-primary">บันทึก</button><?php if ($edit): ?><a class="button button-outline" href="events.php">ยกเลิก</a><?php endif; ?></div>
  </form>
</div>

<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>งาน</th><th>วันจัด</th><th>สถานะ</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($events as $row): ?>
        <tr>
          <td><?= e($row['name']) ?><br><small><?= e($row['slug']) ?></small></td>
          <td><?= e(thai_date($row['event_date'])) ?></td>
          <td>
            <form class="event-status-form" method="post">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="toggle_active">
              <input type="hidden" name="id" value="<?= $row['id'] ?>">
              <button class="event-status-toggle<?= $row['is_active'] ? ' is-active' : '' ?>" type="submit" role="switch" aria-checked="<?= $row['is_active'] ? 'true' : 'false' ?>" aria-label="สถานะการเปิดรับลงทะเบียน สำหรับ <?= e($row['name']) ?>" title="<?= $row['is_active'] ? 'เปิดรับลงทะเบียน' : 'ปิดรับลงทะเบียน' ?>">
              </button>
            </form>
          </td>
          <td>
            <div class="inline-form">
              <a class="button button-outline button-small" href="?edit=<?= $row['id'] ?>">แก้ไข</a>
              <form method="post" onsubmit="return confirm('ลบงานนี้หรือไม่?')">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                <button class="button button-danger button-small">ลบ</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/footer.php';
