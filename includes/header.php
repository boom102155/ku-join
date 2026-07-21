<?php
require_once __DIR__ . '/functions.php';
$pageTitle = $pageTitle ?? 'KU Join';
$activePage = $activePage ?? 'register';
$styleVersion = (string) filemtime(__DIR__ . '/../assets/css/style.css');
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#006b52">
  <title><?= e($pageTitle) ?> | KU Join</title>
  <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/icons/favicon/favicon.svg')) ?>">
  <link rel="alternate icon" type="image/png" href="<?= e(url('assets/icons/favicon/favicon.png')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(url('assets/vendor/sweetalert2.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>?v=<?= e($styleVersion) ?>">
  <script src="<?= e(url('assets/vendor/sweetalert2.all.min.js')) ?>" defer></script>
</head>
<body>
<header class="site-header">
  <div class="nav-wrap">
    <a class="brand" href="<?= e(url('index.php')) ?>"><span>KU Join</span></a>
    <nav class="main-nav" aria-label="เมนูหลัก">
      <a class="<?= $activePage === 'register' ? 'is-active' : '' ?>" href="<?= e(url('index.php')) ?>"><i class="bx bx-edit-alt" aria-hidden="true"></i>ลงทะเบียน</a>
      <a class="<?= $activePage === 'list' ? 'is-active' : '' ?>" href="<?= e(url('list.php')) ?>"><i class="bx bx-group" aria-hidden="true"></i>รายชื่อผู้เข้าร่วม</a>
      <a class="<?= $activePage === 'summary' ? 'is-active' : '' ?>" href="<?= e(url('summary.php')) ?>"><i class="bx bx-bar-chart-alt-2" aria-hidden="true"></i>สรุปผล</a>
    </nav>
  </div>
</header>
<main>
<?php if ($message = flash('success')): ?><div class="toast toast-success" role="status"><?= e($message) ?></div><?php endif; ?>
<?php if ($message = flash('error')): ?><div class="toast toast-error" role="alert"><?= e($message) ?></div><?php endif; ?>
