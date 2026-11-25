<?php
/** admin/programs.php — CRUD สาขาวิชา */
require_once __DIR__ . '/../includes/layout_admin.php';
require_permission('program');
$canWrite = can_write();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('program');
    $act = post('action') ?: 'save'; $id = (int) post('id');
    if (!in_array($act, ['save', 'delete', 'toggle'], true)) { http_response_code(400); exit('ไม่รู้จักคำสั่งนี้'); }
    if ($act === 'delete') {
        if (db_val('SELECT COUNT(*) FROM mou_programs WHERE program_id = ?', [$id]) > 0) {
            db_exec('UPDATE programs SET is_active = 0 WHERE id = ?', [$id]);   // มี MOU อ้างอิง → ซ่อนแทนลบ
            flash('warning', 'สาขานี้มี MOU อ้างอิงอยู่ จึงปิดการใช้งานแทนการลบ');
        } else { db_exec('DELETE FROM programs WHERE id = ?', [$id]); flash('success', 'ลบสาขาเรียบร้อยแล้ว'); }
    } elseif ($act === 'toggle') {
        db_exec('UPDATE programs SET is_active = 1 - is_active WHERE id = ?', [$id]);
        flash('success', 'เปลี่ยนสถานะเรียบร้อยแล้ว');
    } else {
        $name = mb_substr(post('name'), 0, 150); $level = post('level');
        if ($name === '' || !in_array($level, ['ปวช.', 'ปวส.'], true)) { flash('error', 'กรุณากรอกชื่อสาขาและเลือกระดับ'); }
        elseif ($id) { db_exec('UPDATE programs SET name = ?, level = ? WHERE id = ?', [$name, $level, $id]); flash('success', 'บันทึกเรียบร้อยแล้ว'); }
        else { db_insert('INSERT INTO programs (name, level) VALUES (?,?)', [$name, $level]); flash('success', 'เพิ่มสาขาเรียบร้อยแล้ว'); }
    }
    log_activity('program_' . $act, 'programs#' . $id, mb_substr(post('name'), 0, 150));
    redirect('admin/programs.php');
}
admin_header('สาขาวิชา', 'program', 'program');
$edit = get('edit') ? db_one('SELECT * FROM programs WHERE id = ?', [(int) get('edit')]) : null;
$rows = db_all('SELECT p.*, (SELECT COUNT(*) FROM mou_programs mp WHERE mp.program_id = p.id) c FROM programs p ORDER BY p.level, p.name');
?>
<div class="row g-3 stagger">
  <?php if ($canWrite): ?><div class="col-lg-4"><div class="panel"><h2><i class="bi bi-mortarboard" aria-hidden="true"></i> <?= $edit ? 'แก้ไขสาขา' : 'เพิ่มสาขา' ?></h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <div class="mb-3"><label class="form-label" for="f-name">ชื่อสาขา *</label><input id="f-name" name="name" class="form-control" required maxlength="150" value="<?= e($edit['name'] ?? '') ?>"></div>
      <div class="mb-3"><label class="form-label" for="f-level">ระดับ *</label><select id="f-level" name="level" class="form-select">
        <?php foreach (['ปวช.', 'ปวส.'] as $l): ?><option <?= ($edit['level'] ?? 'ปวช.') === $l ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <button class="btn btn-navy"><i class="bi bi-save" aria-hidden="true"></i> บันทึก</button> <?php if ($edit): ?><a class="btn btn-outline-secondary" href="programs.php">ยกเลิก</a><?php endif; ?>
    </form></div></div><?php endif; ?>
  <div class="<?= $canWrite ? 'col-lg-8' : 'col-12' ?>"><div class="panel"><table class="table table-sm align-middle js-dt">
    <thead><tr><th>ระดับ</th><th>ชื่อสาขา</th><th>MOU</th><th>สถานะ</th><th class="no-sort"><span class="visually-hidden">การจัดการ</span></th></tr></thead><tbody>
    <?php foreach ($rows as $p): ?><tr><td><?= e($p['level']) ?></td><td><?= e($p['name']) ?></td><td><?= (int) $p['c'] ?></td>
      <td><?= $p['is_active'] ? '<span class="badge bg-success">ใช้งาน</span>' : '<span class="badge bg-secondary">ปิด</span>' ?></td>
      <td class="text-end text-nowrap"><?php if ($canWrite): ?><a class="btn btn-sm btn-outline-primary" href="programs.php?edit=<?= (int) $p['id'] ?>" title="แก้ไข" aria-label="แก้ไขสาขา <?= e($p['name']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a>
        <form class="d-inline" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-secondary" title="เปิด/ปิด" aria-label="เปิดหรือปิดสาขา <?= e($p['name']) ?>"><i class="bi bi-toggle-on" aria-hidden="true"></i></button></form>
        <form class="d-inline" method="post" data-confirm="ลบสาขานี้?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="name" value="<?= e($p['name']) ?>"><button type="submit" class="btn btn-sm btn-outline-danger" title="ลบ" aria-label="ลบสาขา <?= e($p['name']) ?>"><i class="bi bi-trash" aria-hidden="true"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
</div>
<?php admin_footer();
