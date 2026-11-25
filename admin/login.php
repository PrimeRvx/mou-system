<?php
/** admin/login.php — เข้าสู่ระบบหลังบ้าน */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ui.php';

if (is_logged_in()) { redirect('admin/'); }
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $error = attempt_login(post('username'), post_raw('password'));
    if ($error === null) {
        // กลับไปหน้าที่ตั้งใจ เฉพาะที่อยู่ใต้ /admin/ ของเว็บนี้เท่านั้น (กัน open redirect)
        $back = $_SESSION['redirect_after_login'] ?? '';
        unset($_SESSION['redirect_after_login']);
        $adminPath = rtrim((string) parse_url(BASE_URL, PHP_URL_PATH), '/') . '/admin/';
        if (is_string($back) && strpos($back, $adminPath) === 0 && strpos($back, '//') === false) {
            header('Location: ' . $back);
            exit;
        }
        redirect('admin/');
    }
}
$flash = flash_pull();
?>
<!doctype html>
<html lang="th" data-bs-theme="light">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<?= theme_boot_script() ?>
<meta name="color-scheme" content="light dark">
<title>เข้าสู่ระบบ | ระบบจัดการ MOU</title>
<?= ui_head_assets(true, false) ?>
</head>
<body class="login-bg">
<?= page_loader() ?>
<!-- Floating shapes (ตกแต่งพื้นหลัง) -->
<div class="shapes" aria-hidden="true"><span class="s1"></span><span class="s2"></span><span class="s3"></span><span class="s4"></span><span class="s5"></span><span class="s6"></span></div>
<div class="login-top"><?= theme_toggle_button() ?></div>

<div class="container px-3">
  <div class="card login-card mx-auto"><div class="card-body p-4 p-sm-5">
    <div class="text-center mb-4 fade-up">
      <span class="logo-slot bounce-icon" aria-hidden="true"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
      <h1 class="h4 mt-3 mb-1 text-heading">ระบบจัดการ MOU</h1>
      <div class="text-muted small"><?= e(setting('college_name', 'วิทยาลัยพณิชยการบางนา')) ?></div>
    </div>
    <?php if ($error): ?><div class="alert alert-danger py-2 scale-in" role="alert" id="loginError"><i class="bi bi-exclamation-octagon me-1" aria-hidden="true"></i><?= e($error) ?></div><?php endif; ?>
    <?php if ($flash): ?><div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : ($flash['type'] === 'success' ? 'success' : 'warning') ?> py-2 scale-in" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
    <form method="post" autocomplete="on" class="stagger" id="loginForm">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label" for="username">ชื่อผู้ใช้</label>
        <div class="input-group input-group-lg"><span class="input-group-text" aria-hidden="true"><i class="bi bi-person" aria-hidden="true"></i></span>
        <input id="username" name="username" class="form-control" required maxlength="50" <?= $error ? '' : 'autofocus' ?> autocomplete="username" autocapitalize="none" spellcheck="false" value="<?= e(post('username')) ?>"<?= $error ? ' aria-describedby="loginError"' : '' ?>></div></div>
      <div class="mb-4"><label class="form-label" for="password">รหัสผ่าน</label>
        <div class="input-group input-group-lg"><span class="input-group-text" aria-hidden="true"><i class="bi bi-lock" aria-hidden="true"></i></span>
        <input id="password" name="password" type="password" class="form-control" required autocomplete="current-password" <?= $error ? 'autofocus aria-describedby="loginError"' : '' ?>></div></div>
      <button type="submit" class="btn btn-navy btn-lg w-100" id="loginBtn"><span class="btn-txt"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> เข้าสู่ระบบ</span></button>
    </form>
    <div class="text-center mt-4"><a class="small text-decoration-none" href="<?= e(route('home')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> กลับหน้าเว็บไซต์</a></div>
  </div></div>
  <p class="text-center small mt-4 mb-0" style="color:rgba(255,255,255,.78)"><i class="bi bi-shield-lock" aria-hidden="true"></i> ระบบนี้สำหรับเจ้าหน้าที่เท่านั้น · การเข้าใช้งานถูกบันทึกไว้</p>
</div>
<?= ui_scripts(false, false) ?>
<script nonce="<?= e(csp_nonce()) ?>">
// แสดงสถานะกำลังเข้าสู่ระบบบนปุ่ม (app.js จัดการปุ่มส่งฟอร์มทุกฟอร์มให้อยู่แล้ว: spinner + aria-busy)
</script>
</body></html>
