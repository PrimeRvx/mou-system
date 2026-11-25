<?php
/** admin/mou_list.php — รายการ MOU หลังบ้าน (ทุกสถานะ) */
require_once __DIR__ . '/../includes/layout_admin.php';
require_permission('mou');
refresh_mou_status();

$showDeleted = get('deleted') === '1';   // มุมมอง "ที่ลบแล้ว" (soft delete) — กู้คืน หรือลบถาวร (เฉพาะ admin)
$filters = ['q' => mb_substr(get('q'), 0, 100), 'type' => get('type'), 'program' => get('program'), 'status' => get('status'),
            'year' => get('year'), 'province' => get('province'), 'deleted' => $showDeleted];
$rows = mou_search($filters, false);
$canWrite = can_write();
$isAdmin  = $_SESSION['user']['role'] === 'admin';
$deletedCount = (int) db_val('SELECT COUNT(*) FROM mou WHERE deleted_at IS NOT NULL');
admin_header('จัดการข้อตกลง MOU', 'mou', 'mou');   // โหลดข้อมูลครบแล้วจึงพิมพ์ส่วนหัว
?>
<div class="page-head fade-up"><div><div class="sub"><?= $showDeleted ? 'รายการที่ลบแล้ว' : 'ทั้งหมด' ?> <?= count($rows) ?> รายการ</div><h2 class="title"><?= $showDeleted ? 'MOU ที่ลบแล้ว' : 'ข้อตกลง MOU' ?></h2></div>
  <div class="d-flex flex-wrap gap-2">
  <?php if ($showDeleted): ?><a class="btn btn-outline-secondary" href="mou_list.php"><i class="bi bi-arrow-left" aria-hidden="true"></i> กลับรายการปกติ</a>
  <?php elseif ($canWrite && $deletedCount > 0): ?><a class="btn btn-outline-secondary" href="mou_list.php?deleted=1"><i class="bi bi-trash3" aria-hidden="true"></i> ที่ลบแล้ว (<?= $deletedCount ?>)</a><?php endif; ?>
  <?php if ($canWrite && !$showDeleted): ?><a class="btn btn-gold" href="mou_form.php"><i class="bi bi-plus-lg" aria-hidden="true"></i> เพิ่ม MOU</a><?php endif; ?></div></div>
<?php if ($showDeleted): ?><div class="alert alert-warning fade-up"><i class="bi bi-info-circle me-1" aria-hidden="true"></i> รายการในหน้านี้ถูกซ่อนจากทุกหน้าและเลขที่ MOU ยังถูกจองอยู่ — กด <strong>กู้คืน</strong> เพื่อนำกลับมาใช้ หรือ <strong>ลบถาวร</strong> (เฉพาะผู้ดูแลระบบ) เพื่อลบข้อมูล ไฟล์แนบ และรูปภาพทั้งหมดออกจากระบบ</div><?php endif; ?>
<form class="panel fade-up delay-1" method="get" role="search" aria-label="ตัวกรอง MOU"><?php if ($showDeleted): ?><input type="hidden" name="deleted" value="1"><?php endif; ?><div class="row g-2">
  <div class="col-lg-3"><label class="visually-hidden" for="aq">คำค้น</label><div class="input-group"><span class="input-group-text" aria-hidden="true"><i class="bi bi-search" aria-hidden="true"></i></span><input type="search" id="aq" name="q" class="form-control" placeholder="เลขที่/ชื่อเรื่อง/หน่วยงาน" value="<?= e($filters['q']) ?>"></div></div>
  <div class="col-6 col-lg-2"><label class="visually-hidden" for="atype">ประเภทหน่วยงาน</label><select id="atype" name="type" class="form-select"><option value="">ทุกประเภทหน่วยงาน</option>
    <?php foreach (partner_types() as $k => $l): ?><option value="<?= e($k) ?>" <?= $filters['type'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-lg-2"><label class="visually-hidden" for="astatus">สถานะ</label><select id="astatus" name="status" class="form-select"><option value="">ทุกสถานะ</option>
    <?php foreach (mou_statuses() as $k => $s): ?><option value="<?= e($k) ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= e($s[0]) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-lg-2"><label class="visually-hidden" for="aprog">สาขาวิชา</label><select id="aprog" name="program" class="form-select"><option value="">ทุกสาขา</option>
    <?php foreach (all_programs() as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $filters['program'] === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['level'] . ' ' . $p['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-lg-1"><label class="visually-hidden" for="ayear">ปีที่ลงนาม</label><select id="ayear" name="year" class="form-select"><option value="">ทุกปี</option>
    <?php foreach (mou_sign_years() as $y): ?><option value="<?= (int) $y ?>" <?= $filters['year'] === (string) $y ? 'selected' : '' ?>><?= (int) $y + 543 ?></option><?php endforeach; ?></select></div>
  <div class="col-lg-2 d-flex gap-1"><button class="btn btn-navy flex-fill"><i class="bi bi-funnel" aria-hidden="true"></i> กรอง</button><a class="btn btn-outline-secondary" href="mou_list.php<?= $showDeleted ? '?deleted=1' : '' ?>" title="ล้างตัวกรอง" aria-label="ล้างตัวกรอง"><i class="bi bi-x-lg" aria-hidden="true"></i></a></div>
</div></form>

<div class="panel fade-up delay-2"><div class="table-responsive">
<table class="table table-hover table-sm js-dt align-middle" data-order='[4,"asc"]'>
  <caption class="visually-hidden">รายการ MOU <?= count($rows) ?> รายการ</caption>
  <thead class="table-light"><tr><th>เลขที่</th><th>ชื่อเรื่อง / หน่วยงาน</th><th>ประเภท</th><th>ลงนาม</th><th>สิ้นสุด</th><th>สถานะ</th><th>เผยแพร่</th><th class="no-sort"><span class="visually-hidden">การจัดการ</span></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $m): ?>
    <tr>
      <td class="text-nowrap"><?= e($m['mou_no']) ?></td>
      <td><div class="d-flex gap-2 align-items-center"><?php if (!empty($m['cover_image'])): ?><img src="<?= e(media_url('mou_images', $m['cover_image'])) ?>" alt="" loading="lazy" class="rounded" style="width:46px;height:36px;object-fit:cover;flex-shrink:0"><?php endif; ?>
        <div><?php if ($showDeleted): ?><span class="fw-semibold"><?= e($m['title']) ?></span><?php else: ?><a class="fw-semibold text-decoration-none" href="mou_view.php?id=<?= (int) $m['id'] ?>"><?= e($m['title']) ?></a><?php endif; ?><div class="small text-muted"><?= $showDeleted ? 'ลบเมื่อ ' . e(thai_datetime($m['deleted_at'], true)) . ' · ' : '' ?><?= e($m['partner_name']) ?><?= (int) ($m['image_count'] ?? 0) ? ' · <i class="bi bi-images" aria-hidden="true"></i> ' . (int) $m['image_count'] . ' รูป' : '' ?></div></div></div></td>
      <td class="text-nowrap"><?= e(partner_type_label($m['partner_type'])) ?></td>
      <td class="text-nowrap" data-order="<?= e($m['sign_date']) ?>"><?= e(thai_date($m['sign_date'], true)) ?></td>
      <td class="text-nowrap" data-order="<?= e($m['end_date']) ?>"><?= e(thai_date($m['end_date'], true)) ?></td>
      <td><?= status_badge($m['status']) ?></td>
      <td class="text-nowrap" data-order="<?= (int) $m['is_public'] ?>"><?= $m['is_public'] ? '<i class="bi bi-eye text-success" aria-hidden="true"></i> <span class="small">เผยแพร่</span>' : '<i class="bi bi-eye-slash text-muted" aria-hidden="true"></i> <span class="small text-muted">ไม่เผยแพร่</span>' ?></td>
      <td class="text-nowrap text-end">
        <?php if ($showDeleted): ?>
          <?php if ($canWrite): ?><form method="post" action="mou_actions.php" class="d-inline" data-confirm="กู้คืน MOU เลขที่ <?= e($m['mou_no']) ?> ?">
            <?= csrf_field() ?><input type="hidden" name="action" value="restore_mou"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <button class="btn btn-sm btn-outline-success" title="กู้คืน"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> กู้คืน</button></form><?php endif; ?>
          <?php if ($isAdmin): ?><form method="post" action="mou_actions.php" class="d-inline" data-confirm="ลบ MOU เลขที่ <?= e($m['mou_no']) ?> ถาวร?  ข้อมูล ไฟล์แนบ และรูปภาพทั้งหมดจะหายและกู้คืนไม่ได้">
            <?= csrf_field() ?><input type="hidden" name="action" value="purge_mou"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <button class="btn btn-sm btn-outline-danger" title="ลบถาวร"><i class="bi bi-trash3" aria-hidden="true"></i> ลบถาวร</button></form><?php endif; ?>
        <?php else: ?>
        <a class="btn btn-sm btn-outline-secondary" href="mou_view.php?id=<?= (int) $m['id'] ?>" title="ดู" aria-label="ดู MOU <?= e($m['mou_no']) ?>"><i class="bi bi-eye" aria-hidden="true"></i></a>
        <?php if ($canWrite): ?>
        <a class="btn btn-sm btn-outline-primary" href="mou_form.php?id=<?= (int) $m['id'] ?>" title="แก้ไข" aria-label="แก้ไข MOU <?= e($m['mou_no']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a>
        <a class="btn btn-sm btn-outline-success" href="renewals.php?mou_id=<?= (int) $m['id'] ?>" title="ต่ออายุ" aria-label="ต่ออายุ MOU <?= e($m['mou_no']) ?>"><i class="bi bi-arrow-repeat" aria-hidden="true"></i></a>
        <form method="post" action="mou_actions.php" class="d-inline" data-confirm="ลบ MOU เลขที่ <?= e($m['mou_no']) ?> ?  (ลบแบบซ่อน กู้คืนได้ภายหลัง)">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete_mou"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
          <button type="submit" class="btn btn-sm btn-outline-danger" title="ลบ" aria-label="ลบ MOU <?= e($m['mou_no']) ?>"><i class="bi bi-trash" aria-hidden="true"></i></button></form>
        <?php endif; ?>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
<?php admin_footer();
