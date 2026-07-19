<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$adminPage = $adminPage ?? '';
$adminTitle = $adminTitle ?? 'ผู้ดูแลระบบ';
$styleVersion = (string) filemtime(__DIR__ . '/../assets/css/style.css');
$registrationMenuCount = 0;
if (isset($pdo)) {
    $registrationMenuCount = (int) $pdo->query(
        'SELECT COUNT(*) FROM registrations r INNER JOIN events e ON e.id = r.event_id WHERE e.is_active = 1'
    )->fetchColumn();
}
$menuItems = [
    ['page' => 'dashboard', 'path' => 'dashboard.php', 'icon' => 'bx-grid-alt', 'label' => 'ภาพรวม'],
    ['page' => 'events', 'path' => 'events.php', 'icon' => 'bx-calendar-event', 'label' => 'งาน / กิจกรรม'],
    ['page' => 'types', 'path' => 'participant_types.php', 'icon' => 'bx-category', 'label' => 'ประเภทผู้เข้าร่วม'],
    ['page' => 'slots', 'path' => 'time_slots.php', 'icon' => 'bx-time-five', 'label' => 'ตารางเวลา'],
    ['page' => 'registrations', 'path' => 'registrations.php', 'icon' => 'bx-receipt', 'label' => 'รายการลงทะเบียน', 'count' => $registrationMenuCount],
];
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <title><?= e($adminTitle) ?> | KU Join</title>
  <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/icons/favicon/favicon.svg')) ?>">
  <link rel="alternate icon" type="image/png" href="<?= e(url('assets/icons/favicon/favicon.png')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(url('assets/vendor/sweetalert2.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>?v=<?= e($styleVersion) ?>">
  <script src="<?= e(url('assets/vendor/sweetalert2.all.min.js')) ?>" defer></script>
</head>
<body class="admin-body">
  <div class="admin-layout">
    <aside class="admin-side" data-admin-shell>
      <div class="admin-mobile-bar">
        <a class="brand" href="<?= e(admin_url('dashboard.php')) ?>"><span>KU Join</span></a>
        <button class="admin-menu-toggle" type="button" data-admin-menu-toggle aria-expanded="false" aria-controls="admin-navigation">
          <i class="bx bx-list-ul" aria-hidden="true"></i><span>เมนู</span>
        </button>
      </div>
      <nav class="admin-menu" id="admin-navigation" data-admin-menu aria-label="เมนูผู้ดูแล">
        <?php foreach ($menuItems as $item): ?>
          <a class="<?= $adminPage === $item['page'] ? 'is-active' : '' ?>" href="<?= e(admin_url($item['path'])) ?>"<?= $adminPage === $item['page'] ? ' aria-current="page"' : '' ?>><i class="bx <?= e($item['icon']) ?>" aria-hidden="true"></i><?= e($item['label']) ?><?php if (isset($item['count'])): ?> <span class="admin-menu-count"><?= (int) $item['count'] ?></span><?php endif; ?></a>
        <?php endforeach; ?>
        <a href="<?= e(url('summary.php')) ?>"><i class="bx bx-bar-chart-alt-2" aria-hidden="true"></i>สรุปผล</a>
        <a class="<?= $adminPage === 'password' ? 'is-active' : '' ?>" href="<?= e(admin_url('password.php')) ?>"<?= $adminPage === 'password' ? ' aria-current="page"' : '' ?>><i class="bx bx-lock-alt" aria-hidden="true"></i>เปลี่ยนรหัสผ่าน</a>
        <a href="<?= e(url('index.php')) ?>"><i class="bx bx-window-open" aria-hidden="true"></i>ดูหน้าลงทะเบียน</a>
        <a class="admin-menu-logout" href="<?= e(admin_url('logout.php')) ?>"><i class="bx bx-log-out" aria-hidden="true"></i>ออกจากระบบ</a>
      </nav>
    </aside>
    <section class="admin-content">
      <header class="admin-top"><div><h1><?= e($adminTitle) ?></h1><p>สวัสดี, <?= e($_SESSION['admin_username'] ?? 'ผู้ดูแล') ?></p></div></header>
      <?php if ($m = flash('success')): ?><div class="toast toast-success" role="status"><?= e($m) ?></div><?php endif; ?>
      <?php if ($m = flash('error')): ?><div class="toast toast-error" role="alert"><?= e($m) ?></div><?php endif; ?>
