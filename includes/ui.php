<?php
/**
 * includes/ui.php — ชิ้นส่วน UI ที่ใช้ร่วมกันทุกหน้า (ธีมมืด/สว่าง, Loading overlay, ไฟล์ CSS/JS)
 * ถูก require จาก layout_public.php, layout_admin.php และ admin/login.php
 */
require_once __DIR__ . '/functions.php';

/** เลขเวอร์ชันสำรองของไฟล์ CSS/JS (ใช้เมื่ออ่านเวลาแก้ไขไฟล์ไม่ได้) */
const ASSET_VER = '2.2.2';

/** URL ของไฟล์ static + ?v=เวลาแก้ไขไฟล์ — แก้ไฟล์เมื่อไร เบราว์เซอร์โหลดใหม่เอง ไม่ต้องแก้เลขเวอร์ชัน */
function asset(string $path): string
{
    $file = ROOT_PATH . '/' . ltrim($path, '/');
    return url($path) . '?v=' . (is_file($file) ? filemtime($file) : ASSET_VER);
}

/**
 * สคริปต์ตั้งธีมก่อนหน้าเว็บแสดงผล (วางใน <head> ก่อน CSS) — ป้องกันจอกระพริบขาวเมื่อใช้โหมดมืด
 * ลำดับ: ค่าที่ผู้ใช้เลือกไว้ (localStorage) → ค่าของระบบปฏิบัติการ (prefers-color-scheme)
 */
function theme_boot_script(): string
{
    return '<script nonce="' . e(csp_nonce()) . '">' . "(function(){var t;try{t=localStorage.getItem('mou-theme')}catch(e){}"
         . "if(t!=='light'&&t!=='dark'){t=window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'}"
         . "document.documentElement.setAttribute('data-bs-theme',t)})();</script>";
}

/** ฟอนต์ + Bootstrap + Icons (ใช้ร่วมกันทุกหน้า) */
function ui_head_assets(bool $admin = false, bool $datatables = true): string
{
    // ไลบรารีและฟอนต์ทั้งหมดเก็บไว้ใน assets/vendor/ (ไม่พึ่ง CDN — ใช้ได้แม้เครือข่ายไม่มีอินเทอร์เน็ต)
    $h = '<link href="' . e(asset('assets/vendor/fonts/fonts.css')) . '" rel="stylesheet">'
       . '<link href="' . e(asset('assets/vendor/bootstrap/bootstrap.min.css')) . '" rel="stylesheet">'
       . '<link href="' . e(asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) . '" rel="stylesheet">';
    if ($datatables) {
        $h .= '<link href="' . e(asset('assets/vendor/datatables/dataTables.bootstrap5.min.css')) . '" rel="stylesheet">';
    }
    $h .= '<link href="' . e(asset('assets/css/style.css')) . '" rel="stylesheet">';
    if ($admin) {
        $h .= '<link href="' . e(asset('assets/css/admin.css')) . '" rel="stylesheet">';
    }
    return $h;
}

/** ปุ่มสลับโหมดมืด/สว่าง  ($onLight = true เมื่อวางบนพื้นสว่าง เช่น topbar หลังบ้าน) */
function theme_toggle_button(bool $onLight = false): string
{
    // ชื่อปุ่มคงที่ "โหมดมืด" + aria-pressed บอกสถานะ (กดอยู่ = มืด) — app.js อัปเดต aria-pressed
    return '<button type="button" class="theme-toggle' . ($onLight ? ' on-light' : '') . '" data-theme-toggle'
         . ' aria-label="โหมดมืด" aria-pressed="false" title="สลับโหมดมืด/สว่าง">'
         . '<i class="bi bi-moon-stars-fill ic-moon" aria-hidden="true"></i><i class="bi bi-sun-fill ic-sun" aria-hidden="true"></i></button>';
}

/**
 * แถบความคืบหน้าบางๆ ด้านบนจอ (แทน overlay เต็มจอเดิมที่บังเนื้อหาจนรูปโหลดครบ)
 * app.js แสดงเมื่อเปลี่ยนหน้า/ส่งฟอร์มแล้วรอเกิน 300 ms — ไม่บังเนื้อหา ไม่ถูกอ่านออกเสียงทุกหน้า
 */
function page_loader(): string
{
    return '<div class="page-progress" id="pageLoader" aria-hidden="true"></div>';
}

/* ---------- ฟอร์ม: label ผูกช่อง + ข้อผิดพลาดใต้ช่อง (WCAG 1.3.1, 3.3.1) ----------
 * ใช้คู่กับ $errors แบบ ['ชื่อช่อง' => 'ข้อความ'] เช่น
 *   <label class="form-label" for="<?= fid('email') ?>">อีเมล</label>
 *   <input id="<?= fid('email') ?>" name="email" class="form-control<?= field_err_class($errors,'email') ?>"<?= field_err_attrs($errors,'email') ?>>
 *   <?= field_err_msg($errors,'email') ?>
 */
function fid(string $name): string
{
    return 'f-' . trim(preg_replace('/[^a-z0-9_-]+/i', '-', $name), '-');
}

function field_err_class(array $errs, string $name): string
{
    return isset($errs[$name]) ? ' is-invalid' : '';
}

function field_err_attrs(array $errs, string $name): string
{
    return isset($errs[$name]) ? ' aria-invalid="true" aria-describedby="' . e(fid($name)) . '-err"' : '';
}

function field_err_msg(array $errs, string $name): string
{
    return isset($errs[$name])
        ? '<div class="invalid-feedback d-block" id="' . e(fid($name)) . '-err">' . e($errs[$name]) . '</div>' : '';
}

/** กล่องสรุปข้อผิดพลาด: app.js โฟกัสให้ทันที (data-autofocus) และแต่ละรายการลิงก์ไปที่ช่องนั้น */
function error_summary(array $errs, string $extra = ''): string
{
    if (!$errs) {
        return '';
    }
    $h = '<div class="alert alert-danger form-error-summary" role="alert" tabindex="-1" id="errorSummary" data-autofocus>'
       . '<h2 class="h6 mb-2"><i class="bi bi-exclamation-octagon me-1" aria-hidden="true"></i>กรุณาแก้ไข ' . count($errs) . ' รายการ</h2><ul class="mb-0">';
    foreach ($errs as $k => $msg) {
        $h .= '<li>' . (is_string($k) ? '<a href="#' . e(fid($k)) . '">' . e($msg) . '</a>' : e($msg)) . '</li>';
    }
    return $h . '</ul>' . ($extra !== '' ? '<div class="small mt-2">' . $extra . '</div>' : '') . '</div>';
}

/** สคริปต์ท้ายหน้า (jQuery, Bootstrap, DataTables, SweetAlert2, Chart.js(ถ้าต้องการ), app.js) */
function ui_scripts(bool $charts = false, bool $datatables = true): string
{
    $s = '<script src="' . e(asset('assets/vendor/jquery/jquery.min.js')) . '"></script>'
       . '<script src="' . e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) . '"></script>';
    if ($datatables) {
        $s .= '<script src="' . e(asset('assets/vendor/datatables/jquery.dataTables.min.js')) . '"></script>'
            . '<script src="' . e(asset('assets/vendor/datatables/dataTables.bootstrap5.min.js')) . '"></script>';
    }
    $s .= '<script src="' . e(asset('assets/vendor/sweetalert2/sweetalert2.all.min.js')) . '"></script>';
    if ($charts) {
        $s .= '<script src="' . e(asset('assets/vendor/chartjs/chart.umd.min.js')) . '"></script>';
    }
    return $s . '<script src="' . e(asset('assets/js/app.js')) . '"></script>';
}

/** URL รูปที่ส่งผ่าน public/image.php (mou_images, activities, logos, news) */
function media_url(string $dir, ?string $file): string
{
    return $file ? url('public/image.php?d=' . rawurlencode($dir) . '&f=' . rawurlencode($file)) : '';
}

/** อักษรย่อสำหรับ avatar / โลโก้หน่วยงานที่ไม่มีรูป */
function initial_of(string $name): string
{
    $n = trim(preg_replace('/^(บริษัท|ห้างหุ้นส่วน(จำกัด)?|มหาวิทยาลัย|ธนาคาร|สำนักงาน|สมาคม|โรงแรม|นาย|นาง(สาว)?)\s*/u', '', $name));
    $n = preg_replace('/^[เแโใไ]/u', '', $n !== '' ? $n : $name);   // ข้ามสระหน้า ให้ได้พยัญชนะตัวแรก
    return mb_substr($n !== '' ? $n : $name, 0, 1);
}

/** ส่วนหัวของแผง (panel) พร้อมไอคอน */
function panel_title(string $icon, string $text, string $right = ''): string
{
    return '<h2 class="panel-title"><i class="bi ' . e($icon) . '" aria-hidden="true"></i> ' . e($text)
         . ($right !== '' ? '<span class="panel-sub">' . $right . '</span>' : '') . '</h2>';
}

/** Empty state สวยๆ */
function empty_state(string $text, string $icon = 'bi-inbox'): string
{
    return '<div class="empty-state"><i class="bi ' . e($icon) . '" aria-hidden="true"></i>' . e($text) . '</div>';
}
