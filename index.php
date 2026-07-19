<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/functions.php';

$event = active_event($pdo);
$pageTitle = $event['name'] ?? 'ลงทะเบียน';
$activePage = 'register';

if ($event) {
    $typesStmt = $pdo->prepare('SELECT * FROM participant_types WHERE event_id = ? ORDER BY sort_order, id');
    $typesStmt->execute([$event['id']]);
    $types = $typesStmt->fetchAll();
    $slotsStmt = $pdo->prepare('SELECT * FROM time_slots WHERE event_id = ? ORDER BY sort_order, id');
    $slotsStmt->execute([$event['id']]);
    $slots = $slotsStmt->fetchAll();
} else {
    $types = [];
    $slots = [];
}

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$old = $_SESSION['registration_old'] ?? [];
$formErrors = $_SESSION['registration_errors'] ?? [];
unset($_SESSION['registration_old'], $_SESSION['registration_errors']);
$selectedTypeId = (int) ($old['participant_type_id'] ?? 0);
$selectedSlots = array_map('intval', $old['time_slots'] ?? []);

require __DIR__ . '/includes/header.php';
if (!$event): ?>
  <section class="page"><div class="empty"><h1>ยังไม่มี งาน/กิจกรรม/โครงการ ที่เปิดรับลงทะเบียน</h1></div></section>
<?php require __DIR__ . '/includes/footer.php'; exit; endif; ?>

<section class="page">
  <section class="hero">
    <span class="date-badge"><i class="bx bx-calendar" aria-hidden="true"></i><?= e(thai_date($event['event_date'])) ?></span>
    <h1><?= e($event['name']) ?></h1>
    <?php if ($event['location']): ?><p><?= e($event['location']) ?></p><?php endif; ?>
    <p class="hero-caption">ระบบลงทะเบียนเข้าร่วมงาน KU Join</p>
  </section>

  <form class="form-shell" action="<?= e(url('register_submit.php')) ?>" method="post" data-registration-form>
    <div class="form-heading"><i class="bx bx-clipboard" aria-hidden="true"></i>แบบฟอร์มลงทะเบียน</div>
    <div class="form-content">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">

      <?php if ($formErrors): ?><div class="form-error-summary" role="alert" tabindex="-1"><strong>กรุณาตรวจสอบข้อมูลอีกครั้ง</strong><span>ข้อมูลที่จำเป็นหรือรูปแบบข้อมูลบางรายการยังไม่ถูกต้อง</span></div><?php endif; ?>

      <label class="field-label"><span class="required">* </span>เลือกประเภทผู้เข้าร่วม</label>
      <p class="registration-type-hint">เลือกประเภทผู้เข้าร่วมเพื่อแสดงแบบฟอร์มลงทะเบียน</p>
      <div class="type-grid">
        <?php foreach ($types as $index => $type): ?>
          <label class="type-card <?= $selectedTypeId === (int) $type['id'] ? 'is-selected' : '' ?>" data-type-card data-org="<?= (int) $type['requires_org_name'] ?>" data-status="<?= (int) $type['requires_status_field'] ?>">
            <input type="radio" name="participant_type_id" value="<?= (int) $type['id'] ?>" <?= $selectedTypeId === (int) $type['id'] ? 'checked' : '' ?>>
            <span class="type-icon"><i class="bx <?= $index === 0 ? 'bx-buildings' : ($index === 1 ? 'bx-graduation' : 'bx-id-card') ?>" aria-hidden="true"></i></span>
            <span><?= e($type['name']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="registration-details<?= $selectedTypeId ? '' : ' is-hidden' ?>" data-registration-details>
      <div class="field span-full" data-org-field>
        <label class="field-label" for="org_name">ชื่อส่วนงาน <span class="required">*</span></label>
        <input class="input<?= isset($formErrors['org_name']) ? ' has-error' : '' ?>" id="org_name" name="org_name" maxlength="255" value="<?= e($old['org_name'] ?? '') ?>" placeholder="เช่น คณะ... / สำนัก... / หน่วยงาน..."<?= isset($formErrors['org_name']) ? ' aria-invalid="true"' : '' ?>>
      </div>

      <div class="field-grid">
        <div class="field"><label class="field-label" for="representative_name">ชื่อผู้บริหารหรือผู้แทน <span class="required">*</span></label><input class="input<?= isset($formErrors['representative_name']) ? ' has-error' : '' ?>" id="representative_name" name="representative_name" maxlength="255" value="<?= e($old['representative_name'] ?? '') ?>" required placeholder="ชื่อ - นามสกุล"<?= isset($formErrors['representative_name']) ? ' aria-invalid="true"' : '' ?>></div>
        <div class="field"><label class="field-label" for="position">ตำแหน่ง <span class="required">*</span></label><input class="input<?= isset($formErrors['position']) ? ' has-error' : '' ?>" id="position" name="position" maxlength="255" value="<?= e($old['position'] ?? '') ?>" required placeholder="เช่น ผู้อำนวยการ / คณบดี"<?= isset($formErrors['position']) ? ' aria-invalid="true"' : '' ?>></div>
        <div class="field"><label class="field-label" for="full_name">ชื่อ-นามสกุลผู้ลงทะเบียน <span class="required">*</span></label><input class="input<?= isset($formErrors['full_name']) ? ' has-error' : '' ?>" id="full_name" name="full_name" maxlength="255" value="<?= e($old['full_name'] ?? '') ?>" required placeholder="ชื่อ - นามสกุล"<?= isset($formErrors['full_name']) ? ' aria-invalid="true"' : '' ?>></div>
        <div class="field"><label class="field-label" for="phone">เบอร์โทรศัพท์ติดต่อ</label><input class="input<?= isset($formErrors['phone']) ? ' has-error' : '' ?>" id="phone" name="phone" maxlength="20" value="<?= e($old['phone'] ?? '') ?>" inputmode="tel" autocomplete="tel" pattern="[0-9+()\-\s]{7,20}" placeholder="08x-xxx-xxxx"<?= isset($formErrors['phone']) ? ' aria-invalid="true"' : '' ?>></div>
        <div class="field"><label class="field-label" for="companion_count">จำนวนผู้ติดตาม (โดยประมาณ)</label><input class="input<?= isset($formErrors['companion_count']) ? ' has-error' : '' ?>" id="companion_count" name="companion_count" type="number" min="0" max="100" value="<?= e($old['companion_count'] ?? '0') ?>"<?= isset($formErrors['companion_count']) ? ' aria-invalid="true"' : '' ?>></div>
        <div class="field is-hidden" data-status-field><label class="field-label" for="status">สถานภาพ <span class="required">*</span></label><select class="select<?= isset($formErrors['status']) ? ' has-error' : '' ?>" id="status" name="status" data-old-value="<?= e($old['status'] ?? '') ?>"<?= isset($formErrors['status']) ? ' aria-invalid="true"' : '' ?>><option value="">เลือกสถานภาพ</option><option>ผู้บริหาร</option><option>อาจารย์อาวุโส_ผู้เกษียณ</option><option>อาจารย์</option><option>บุคลากร</option></select></div>
        <div class="field is-hidden" data-status-field><label class="field-label" for="department">สังกัด</label><input class="input<?= isset($formErrors['department']) ? ' has-error' : '' ?>" id="department" name="department" maxlength="255" value="<?= e($old['department'] ?? '') ?>" placeholder="ภาควิชา / หน่วยงาน"<?= isset($formErrors['department']) ? ' aria-invalid="true"' : '' ?>></div>
      </div>

      <hr class="section-rule">
      <h2 class="slots-title"><i class="bx bx-time-five" aria-hidden="true"></i>เลือกช่วงเวลาที่เข้าร่วม <small>(เลือกได้มากกว่า 1 ช่วง)</small></h2>
      <?php $slotIds = array_map(static fn(array $slot): int => (int) $slot['id'], $slots); ?>
      <?php $allSlotsSelected = $slotIds !== [] && array_diff($slotIds, $selectedSlots) === []; ?>
      <label class="slot-select-all">
        <input type="checkbox" data-select-all-slots<?= $allSlotsSelected ? ' checked' : '' ?>>
        <span>เข้าร่วมทั้งหมด</span>
        <small>เลือกทุกช่วงเวลา</small>
      </label>
      <div class="slot-grid">
        <?php foreach ($slots as $slot): ?><label class="slot"><input type="checkbox" name="time_slots[]" value="<?= (int) $slot['id'] ?>" data-slot-choice<?= in_array((int) $slot['id'], $selectedSlots, true) ? ' checked' : '' ?>><time><?= e($slot['slot_time']) ?></time><span><strong><?= e($slot['title']) ?></strong><?php if ($slot['location']): ?><small><i class="bx bx-map-pin" aria-hidden="true"></i><?= e($slot['location']) ?></small><?php endif; ?></span></label><?php endforeach; ?>
      </div>
      </div>
      <div class="actions"><button class="button button-primary" type="submit" data-registration-submit><i class="bx bx-check-circle" aria-hidden="true"></i><span data-registration-label>ลงทะเบียน</span></button><a class="button button-outline" href="<?= e(url('list.php?event=' . $event['id'])) ?>"><i class="bx bx-list-ul" aria-hidden="true"></i>ดูรายชื่อ</a></div>
    </div>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php';
