<?php
/** admin/settings.php — ตั้งค่าเว็บไซต์ (เฉพาะ Admin) */
require_once __DIR__ . '/../includes/layout_admin.php';
require_permission('setting');
$keys = ['site_name', 'college_name', 'site_description', 'contact_name', 'contact_phone', 'contact_email', 'contact_address',
         'office_hours', 'map_lat', 'map_lng', 'notify_email', 'expiring_days'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('setting');
    $err = null;
    if (post('notify_email') !== '' && !valid_email(post('notify_email'))) { $err = 'อีเมลรับแจ้งเตือนไม่ถูกต้อง'; }
    elseif (!is_numeric(post('map_lat')) || !is_numeric(post('map_lng'))) { $err = 'พิกัดแผนที่ต้องเป็นตัวเลข'; }
    elseif ((int) post('expiring_days') < 1 || (int) post('expiring_days') > 365) { $err = 'จำนวนวันแจ้งเตือนต้องอยู่ระหว่าง 1–365'; }
    if ($err) { flash('error', $err); redirect('admin/settings.php'); }
    foreach ($keys as $k) { setting_save($k, mb_substr(post($k), 0, 2000)); }
    if (!empty($_FILES['college_logo']) && $_FILES['college_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $r = save_upload($_FILES['college_logo'], ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'], 2 * 1024 * 1024, 'logos');
        if (isset($r['error'])) { flash('error', 'โลโก้: ' . $r['error']); redirect('admin/settings.php'); }
        $old = setting('college_logo'); if ($old) { delete_upload('logos', $old); }
        setting_save('college_logo', $r['stored']);
    }
    refresh_mou_status(true);
    log_activity('settings_save', 'settings');
    flash('success', 'บันทึกการตั้งค่าเรียบร้อยแล้ว');
    redirect('admin/settings.php');
}
admin_header('ตั้งค่าเว็บไซต์', 'setting', 'setting');
$labels = ['site_name' => 'ชื่อเว็บไซต์', 'college_name' => 'ชื่อวิทยาลัย', 'site_description' => 'คำอธิบายเว็บ (SEO)', 'contact_name' => 'ชื่อผู้ประสานงาน',
           'contact_phone' => 'โทรศัพท์', 'contact_email' => 'อีเมลติดต่อ', 'contact_address' => 'ที่อยู่', 'office_hours' => 'เวลาทำการ',
           'map_lat' => 'ละติจูดแผนที่', 'map_lng' => 'ลองจิจูดแผนที่', 'notify_email' => 'อีเมลรับแจ้งเตือนข้อเสนอใหม่ (เว้นว่าง = ไม่ส่ง)', 'expiring_days' => 'แจ้งเตือนใกล้หมดอายุล่วงหน้า (วัน)'];
?>
<div class="panel scale-in" style="max-width:860px"><h2><i class="bi bi-gear" aria-hidden="true"></i> ตั้งค่าเว็บไซต์</h2><form method="post" enctype="multipart/form-data"><?= csrf_field() ?><div class="row g-3">
  <?php foreach ($keys as $k): $wide = in_array($k, ['site_description', 'contact_address'], true); ?>
  <div class="<?= $wide ? 'col-12' : 'col-md-6' ?>"><label class="form-label" for="<?= fid($k) ?>"><?= e($labels[$k]) ?></label>
    <?php if ($wide): ?><textarea id="<?= fid($k) ?>" name="<?= e($k) ?>" class="form-control" rows="2"><?= e(setting($k)) ?></textarea>
    <?php else: ?><input id="<?= fid($k) ?>" name="<?= e($k) ?>" class="form-control" value="<?= e(setting($k)) ?>"<?= in_array($k, ['contact_email', 'notify_email'], true) ? ' type="email"' : ($k === 'contact_phone' ? ' type="tel"' : (in_array($k, ['expiring_days', 'map_lat', 'map_lng'], true) ? ' inputmode="decimal"' : '')) ?>><?php endif; ?></div>
  <?php endforeach; ?>
  <div class="col-12"><label class="form-label" for="f-college_logo">โลโก้วิทยาลัย (PNG/JPG/WebP ≤ 2MB)</label><input id="f-college_logo" type="file" name="college_logo" class="form-control" accept="image/png,image/jpeg,image/webp">
    <?php if (setting('college_logo')): ?><img class="img-thumbnail mt-2" style="height:70px" src="<?= e(url('uploads/logos/' . setting('college_logo'))) ?>" alt=""><?php endif; ?></div>
</div><button class="btn btn-navy mt-3"><i class="bi bi-save" aria-hidden="true"></i> บันทึก</button></form></div>

<div class="panel fade-up" style="max-width:860px"><h2><i class="bi bi-database-down" aria-hidden="true"></i> สำรองข้อมูล</h2>
  <p class="mb-2">ดาวน์โหลดไฟล์ .zip ที่มี<strong>ฐานข้อมูลทั้งหมด</strong>และ<strong>ไฟล์อัปโหลดทั้งหมด</strong> (ไฟล์แนบ MOU, รูปภาพ, โลโก้) แล้วเก็บไว้นอกเครื่องเซิร์ฟเวอร์ เช่น ไดรฟ์ภายนอกหรือคลาวด์ของวิทยาลัย</p>
  <?php $lastBackup = setting('last_backup_at'); $backupAge = $lastBackup !== '' ? (int) floor((time() - strtotime($lastBackup)) / 86400) : null; ?>
  <p class="small <?= $backupAge === null || $backupAge > 7 ? 'text-danger' : 'text-muted' ?>"><i class="bi bi-clock-history me-1" aria-hidden="true"></i>
    <?= $lastBackup !== '' ? 'สำรองล่าสุดเมื่อ ' . e(thai_datetime($lastBackup, true)) . ($backupAge > 7 ? ' — ผ่านมา ' . $backupAge . ' วันแล้ว ควรสำรองใหม่' : '') : 'ยังไม่เคยสำรองข้อมูลจากหน้านี้' ?></p>
  <form method="post" action="backup.php" data-no-loader><?= csrf_field() ?>
    <button class="btn btn-gold"><i class="bi bi-download" aria-hidden="true"></i> ดาวน์โหลดไฟล์สำรองข้อมูล</button></form>
  <div class="form-text mt-2">ไฟล์สำรองมีข้อมูลส่วนบุคคลและรหัสผ่านที่เข้ารหัสไว้ — เก็บในที่ปลอดภัย กู้คืนโดยนำเข้า <code>database.sql</code> ผ่าน phpMyAdmin แล้วคัดลอกโฟลเดอร์ <code>uploads</code> กลับที่เดิม</div>
</div>
<?php admin_footer();
