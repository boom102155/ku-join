<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../includes/functions.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!is_string($currentPassword) || !is_string($newPassword) || !is_string($confirmPassword)) {
        flash('error', 'ข้อมูลรหัสผ่านไม่ถูกต้อง');
    } elseif (mb_strlen($newPassword) < 8) {
        flash('error', 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร');
    } elseif ($newPassword !== $confirmPassword) {
        flash('error', 'ยืนยันรหัสผ่านใหม่ไม่ตรงกัน');
    } else {
        $adminStmt = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = ? LIMIT 1');
        $adminStmt->execute([(int) $_SESSION['admin_id']]);
        $admin = $adminStmt->fetch();

        if (!$admin || !password_verify($currentPassword, $admin['password_hash'])) {
            flash('error', 'รหัสผ่านปัจจุบันไม่ถูกต้อง');
        } else {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
            $updateStmt->execute([$passwordHash, (int) $_SESSION['admin_id']]);
            session_regenerate_id(true);
            flash('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
        }
    }

    header('Location: password.php');
    exit;
}

$adminTitle = 'เปลี่ยนรหัสผ่าน';
$adminPage = 'password';
require __DIR__ . '/header.php';
?>

<section class="admin-panel admin-password-panel" aria-labelledby="password-title">
  <h2 id="password-title">เปลี่ยนรหัสผ่าน</h2>
  <p class="admin-password-note">เพื่อความปลอดภัย โปรดระบุรหัสผ่านปัจจุบันก่อนตั้งรหัสผ่านใหม่</p>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="field">
      <label class="field-label" for="current_password">รหัสผ่านปัจจุบัน</label>
      <input class="input" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
    </div>
    <div class="field">
      <label class="field-label" for="new_password">รหัสผ่านใหม่</label>
      <input class="input" id="new_password" name="new_password" type="password" minlength="8" autocomplete="new-password" required>
      <small class="admin-password-hint">อย่างน้อย 8 ตัวอักษร</small>
    </div>
    <div class="field">
      <label class="field-label" for="confirm_password">ยืนยันรหัสผ่านใหม่</label>
      <input class="input" id="confirm_password" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required>
    </div>
    <div class="actions"><button class="button button-primary" type="submit">บันทึกรหัสผ่านใหม่</button></div>
  </form>
</section>

<?php require __DIR__ . '/footer.php';
