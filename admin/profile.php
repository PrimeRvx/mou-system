<?php
/** admin/profile.php — เปลี่ยนรหัสผ่านของตนเอง */
require_once __DIR__ . '/../includes/layout_admin.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $u = db_one('SELECT * FROM users WHERE id = ?', [$_SESSION['user']['id']]);
    $new = post_raw('new_password');
    if (!password_verify(post_raw('old_password'), $u['password'])) {
        flash('error', 'รหัสผ่านเดิมไม่ถูกต้อง');
    } elseif ($new !== post_raw('confirm_password')) {
        flash('error', 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน');
    } elseif ($p = password_problem($new)) {
        flash('error', $p);
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        db_exec('UPDATE users SET password = ? WHERE id = ?', [$hash, $u['id']]);
        session_regenerate_id(true);
        $_SESSION['user']['pw'] = password_signature($hash);   // เซสชันนี้ใช้ต่อได้ เซสชันอื่นของบัญชีนี้จะถูกบังคับออก
        unset($_SESSION['must_change_password']);
        log_activity('change_password', 'users#' . $u['id'], 'เปลี่ยนรหัสผ่านตนเอง');
        flash('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
    }
    redirect('admin/profile.php');
}
admin_header('เปลี่ยนรหัสผ่าน', '', 'dashboard');
?>
<?php if (!empty($_SESSION['must_change_password'])): ?><div class="alert alert-warning" style="max-width:480px"><i class="bi bi-shield-exclamation me-1" aria-hidden="true"></i> บัญชีนี้ยังใช้<strong>รหัสผ่านเริ่มต้นของระบบ</strong>ซึ่งเป็นที่รู้กันทั่วไป — ตั้งรหัสผ่านใหม่ก่อนจึงจะใช้เมนูอื่นได้</div><?php endif; ?>
<div class="panel scale-in" style="max-width:480px"><h2><i class="bi bi-key" aria-hidden="true"></i> เปลี่ยนรหัสผ่าน</h2><form method="post">
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label" for="f-old_password">รหัสผ่านเดิม</label><input id="f-old_password" type="password" name="old_password" class="form-control" required autocomplete="current-password"></div>
  <div class="mb-3"><label class="form-label" for="f-new_password">รหัสผ่านใหม่ (อย่างน้อย 8 ตัว มีตัวอักษรและตัวเลข)</label><input id="f-new_password" type="password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password"></div>
  <div class="mb-3"><label class="form-label" for="f-confirm_password">ยืนยันรหัสผ่านใหม่</label><input id="f-confirm_password" type="password" name="confirm_password" class="form-control" required autocomplete="new-password"></div>
  <button class="btn btn-navy"><i class="bi bi-save" aria-hidden="true"></i> บันทึก</button>
</form></div>
<?php admin_footer();
