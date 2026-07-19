<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/functions.php';

session_start();
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($_POST['password'] ?? '', $admin['password_hash'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        header('Location: dashboard.php');
        exit;
    }

    flash('error', 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
    header('Location: login.php');
    exit;
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>เข้าสู่ระบบ | KU Join</title>
  <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/icons/favicon/favicon.svg')) ?>">
  <link rel="alternate icon" type="image/png" href="<?= e(url('assets/icons/favicon/favicon.png')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(url('assets/vendor/sweetalert2.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
  <script src="<?= e(url('assets/vendor/sweetalert2.all.min.js')) ?>" defer></script>
  <script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</head>
<body class="login-page">
  <form class="login-card" method="post">
    <h1>KU Join</h1>
    <p>เข้าสู่ระบบสำหรับผู้ดูแล</p>
    <?php if ($m = flash('error')): ?>
      <div class="toast toast-error" role="alert"><?= e($m) ?></div>
    <?php endif; ?>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="field">
      <label class="field-label" for="username">ชื่อผู้ใช้</label>
      <input class="input" id="username" name="username" required autofocus>
    </div>
    <div class="field">
      <label class="field-label" for="password">รหัสผ่าน</label>
      <input class="input" id="password" name="password" type="password" required>
    </div>
    <button class="button button-primary" style="width:100%">เข้าสู่ระบบ</button>
    <a href="<?= e(url('index.php')) ?>" style="display:block;margin-top:15px;text-align:center;color:var(--green)">กลับหน้าเว็บไซต์</a>
  </form>
</body>
</html>
