<?php
/** admin/partners.php — CRUD หน่วยงานคู่ความร่วมมือ (รายการ + ฟอร์มในหน้าเดียว) */
require_once __DIR__ . '/../includes/layout_admin.php';
require_permission('partner');
$canWrite = can_write();
$fields = ['name', 'type', 'address', 'province', 'contact_name', 'contact_position', 'phone', 'email', 'website'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('partner');
    $act = post('action') ?: 'save'; $id = (int) post('id');
    if (!in_array($act, ['save', 'delete'], true)) { http_response_code(400); exit('ไม่รู้จักคำสั่งนี้'); }
    if ($act === 'delete') {
        if (db_val('SELECT COUNT(*) FROM mou WHERE partner_id = ?', [$id]) > 0) {
            flash('error', 'ลบไม่ได้ เพราะมี MOU ผูกกับหน่วยงานนี้อยู่ (รวม MOU ที่ถูกลบไว้ — ต้องลบถาวรก่อน)');
        } else {
            $old = db_one('SELECT logo, name FROM partners WHERE id = ?', [$id]);
            db_exec('DELETE FROM partners WHERE id = ?', [$id]);
            if ($old && $old['logo']) { delete_upload('logos', $old['logo']); }
            log_activity('partner_delete', 'partners#' . $id, $old['name'] ?? '');
            flash('success', 'ลบหน่วยงานเรียบร้อยแล้ว');
        }
        redirect('admin/partners.php');
    }
    // ความยาวสูงสุดตรงกับคอลัมน์ในตาราง partners (database/schema.sql)
    $max = ['name' => 255, 'type' => 20, 'address' => 1000, 'province' => 100, 'contact_name' => 150,
            'contact_position' => 150, 'phone' => 50, 'email' => 150, 'website' => 255];
    $d = [];
    foreach ($fields as $f) { $d[$f] = mb_substr(post($f), 0, $max[$f]); }
    $err = null;
    if ($d['name'] === '') { $err = 'กรุณากรอกชื่อหน่วยงาน'; }
    elseif (!isset(partner_types()[$d['type']])) { $err = 'กรุณาเลือกประเภท'; }
    elseif ($d['email'] !== '' && !valid_email($d['email'])) { $err = 'อีเมลไม่ถูกต้อง'; }
    elseif ($d['website'] !== '' && !preg_match('#^https?://#i', $d['website'])) { $err = 'เว็บไซต์ต้องขึ้นต้นด้วย http:// หรือ https://'; }
    if ($err) { form_keep($d); flash('error', $err); redirect('admin/partners.php?action=' . ($id ? 'edit&id=' . $id : 'new')); }

    $logo = null;
    if (!empty($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $r = save_upload($_FILES['logo'], ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'], 2 * 1024 * 1024, 'logos');
        if (isset($r['error'])) { form_keep($d); flash('error', 'โลโก้: ' . $r['error']); redirect('admin/partners.php?action=' . ($id ? 'edit&id=' . $id : 'new')); }
        $logo = $r['stored'];
    }
    $vals = array_map(fn($f) => $d[$f] === '' ? null : $d[$f], $fields);
    if ($id) {
        if ($logo) {
            $old = db_val('SELECT logo FROM partners WHERE id = ?', [$id]); if ($old) { delete_upload('logos', $old); }
            db_exec('UPDATE partners SET logo = ? WHERE id = ?', [$logo, $id]);
        }
        db_exec('UPDATE partners SET ' . implode(', ', array_map(fn($f) => "`$f` = ?", $fields)) . ' WHERE id = ?', array_merge($vals, [$id]));
    } else {
        $id = db_insert('INSERT INTO partners (' . implode(',', array_map(fn($f) => "`$f`", $fields)) . ', logo) VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ', ?)', array_merge($vals, [$logo]));
    }
    log_activity('partner_save', 'partners#' . $id, $d['name']);
    flash('success', 'บันทึกหน่วยงานเรียบร้อยแล้ว');
    redirect('admin/partners.php');
}

// โหลดข้อมูลและตรวจว่ามีอยู่จริง "ก่อน" พิมพ์ส่วนหัวของหน้า (redirect หลังมี output แล้วจะไม่ทำงาน)
$action = get('action');
$p = null;
if ($canWrite && $action === 'edit') {
    $p = db_one('SELECT * FROM partners WHERE id = ?', [(int) get('id')]);
    if (!$p) { flash('error', 'ไม่พบหน่วยงานที่ต้องการแก้ไข'); redirect('admin/partners.php'); }
}
$old = form_old();   // ค่าที่กรอกค้างไว้จากการบันทึกที่ไม่ผ่าน
admin_header('หน่วยงานคู่ความร่วมมือ', 'partner', 'partner');
if ($canWrite && in_array($action, ['new', 'edit'], true)):
    if ($old) { $p = array_merge($p ?? [], $old); }
    $v = fn($k) => e($p[$k] ?? '');
?>
<div class="panel scale-in"><h2><i class="bi bi-buildings" aria-hidden="true"></i> <?= $action === 'edit' ? 'แก้ไขหน่วยงาน' : 'เพิ่มหน่วยงาน' ?></h2>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>">
  <div class="row g-3">
    <div class="col-md-8"><label class="form-label" for="f-name">ชื่อหน่วยงาน *</label><input id="f-name" name="name" class="form-control" required maxlength="255" value="<?= $v('name') ?>"></div>
    <div class="col-md-4"><label class="form-label" for="f-type">ประเภท *</label><select id="f-type" name="type" class="form-select" required>
      <?php foreach (partner_types() as $k => $l): ?><option value="<?= e($k) ?>" <?= ($p['type'] ?? 'enterprise') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="col-12"><label class="form-label" for="f-address">ที่อยู่</label><textarea id="f-address" name="address" class="form-control" rows="2"><?= $v('address') ?></textarea></div>
    <div class="col-md-4"><label class="form-label" for="f-province">จังหวัด</label><input id="f-province" name="province" class="form-control" maxlength="100" value="<?= $v('province') ?>"></div>
    <div class="col-md-4"><label class="form-label" for="f-website">เว็บไซต์</label><input id="f-website" name="website" class="form-control" placeholder="https://" value="<?= $v('website') ?>"></div>
    <div class="col-md-4"><label class="form-label" for="f-logo">โลโก้ (PNG/JPG/WebP ≤ 2MB)</label><input id="f-logo" type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp">
      <?php if (!empty($p['logo'])): ?><img class="img-thumbnail mt-2 rounded-circle" style="width:64px;height:64px;object-fit:contain" src="<?= e(url('uploads/logos/' . $p['logo'])) ?>" alt=""><?php endif; ?></div>
    <div class="col-12"><div class="alert alert-secondary py-2 mb-0 small">ข้อมูลผู้ประสานงานด้านล่างเป็นข้อมูลภายใน <strong>ไม่แสดงบนเว็บไซต์สาธารณะ</strong></div></div>
    <div class="col-md-3"><label class="form-label" for="f-contact_name">ผู้ประสานงาน</label><input id="f-contact_name" name="contact_name" class="form-control" value="<?= $v('contact_name') ?>"></div>
    <div class="col-md-3"><label class="form-label" for="f-contact_position">ตำแหน่ง</label><input id="f-contact_position" name="contact_position" class="form-control" value="<?= $v('contact_position') ?>"></div>
    <div class="col-md-3"><label class="form-label" for="f-phone">เบอร์โทร</label><input id="f-phone" name="phone" class="form-control" value="<?= $v('phone') ?>"></div>
    <div class="col-md-3"><label class="form-label" for="f-email">อีเมล</label><input id="f-email" name="email" type="email" class="form-control" value="<?= $v('email') ?>"></div>
  </div>
  <div class="mt-3 d-flex gap-2"><button class="btn btn-navy"><i class="bi bi-save" aria-hidden="true"></i> บันทึก</button><a class="btn btn-outline-secondary" href="partners.php">ยกเลิก</a></div>
</form></div>
<?php else:
    $rows = db_all('SELECT p.*, (SELECT COUNT(*) FROM mou m WHERE m.partner_id = p.id AND m.deleted_at IS NULL) mou_count FROM partners p ORDER BY p.name'); ?>
<div class="page-head fade-up"><div><div class="sub">ทั้งหมด <?= count($rows) ?> หน่วยงาน</div><h2 class="title">หน่วยงานคู่ความร่วมมือ</h2></div>
  <?php if ($canWrite): ?><a class="btn btn-gold" href="partners.php?action=new"><i class="bi bi-plus-lg" aria-hidden="true"></i> เพิ่มหน่วยงาน</a><?php endif; ?></div>
<div class="panel fade-up delay-1"><div class="table-responsive"><table class="table table-sm table-hover js-dt align-middle">
  <thead><tr><th>ชื่อหน่วยงาน</th><th>ประเภท</th><th>จังหวัด</th><th>ผู้ประสานงาน</th><th>MOU</th><th class="no-sort"><span class="visually-hidden">การจัดการ</span></th></tr></thead><tbody>
  <?php foreach ($rows as $p): ?><tr><td><div class="d-flex gap-2 align-items-center"><?php if ($p['logo']): ?><img src="<?= e(url('uploads/logos/' . $p['logo'])) ?>" alt="" style="width:32px;height:32px;object-fit:contain" class="rounded-circle bg-white border"><?php else: ?><span class="partner-chip p-0 border-0 bg-transparent"><span class="ini" style="width:32px;height:32px"><?= e(initial_of($p['name'])) ?></span></span><?php endif; ?><span><?= e($p['name']) ?></span></div></td><td><?= e(partner_type_label($p['type'])) ?></td><td><?= e($p['province']) ?></td>
    <td><?= e($p['contact_name']) ?><div class="small text-muted"><?= e($p['phone']) ?></div></td><td><?= (int) $p['mou_count'] ?></td>
    <td class="text-end text-nowrap"><?php if ($canWrite): ?><a class="btn btn-sm btn-outline-primary" href="partners.php?action=edit&id=<?= (int) $p['id'] ?>" title="แก้ไข" aria-label="แก้ไขหน่วยงาน <?= e($p['name']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a>
      <form class="d-inline" method="post" data-confirm="ลบหน่วยงาน <?= e($p['name']) ?> ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger" title="ลบ" aria-label="ลบหน่วยงาน <?= e($p['name']) ?>"><i class="bi bi-trash" aria-hidden="true"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div>
<?php endif; admin_footer();
