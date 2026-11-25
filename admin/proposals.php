<?php
/** admin/proposals.php — รายการข้อเสนอความร่วมมือจากภายนอก */
require_once __DIR__ . '/../includes/layout_admin.php';
require_permission('proposal');
$status = get('status');
$where = isset(proposal_statuses()[$status]) ? 'WHERE status = ?' : '';
$rows = db_all("SELECT * FROM proposals $where ORDER BY (status = 'new') DESC, id DESC", $where ? [$status] : []);
admin_header('ข้อเสนอความร่วมมือ', 'proposal', 'proposal');   // โหลดข้อมูลครบแล้วจึงพิมพ์ส่วนหัว
?>
<div class="mb-3 d-flex gap-1 flex-wrap fade-up">
  <a class="btn btn-sm <?= $status === '' ? 'btn-navy' : 'btn-outline-secondary' ?>" href="proposals.php">ทั้งหมด</a>
  <?php foreach (proposal_statuses() as $k => $s): ?><a class="btn btn-sm <?= $status === $k ? 'btn-navy' : 'btn-outline-secondary' ?>" href="proposals.php?status=<?= e($k) ?>"><?= e($s[0]) ?></a><?php endforeach; ?>
</div>
<div class="panel fade-up delay-1"><div class="table-responsive"><table class="table table-sm table-hover js-dt align-middle" data-order='[0,"desc"]'>
  <thead><tr><th>เลขอ้างอิง</th><th>หน่วยงาน</th><th>ผู้ติดต่อ</th><th>วันที่ส่ง</th><th>สถานะ</th><th class="no-sort"><span class="visually-hidden">การจัดการ</span></th></tr></thead><tbody>
  <?php foreach ($rows as $p): $s = proposal_statuses()[$p['status']]; ?>
  <tr><td><?= e($p['ref_no']) ?></td><td><?= e($p['org_name']) ?><div class="small text-muted"><?= e(partner_type_label($p['org_type'])) ?></div></td>
    <td><?= e($p['contact_name']) ?><div class="small text-muted"><?= e($p['phone']) ?></div></td>
    <td class="text-nowrap" data-order="<?= e($p['created_at']) ?>"><?= e(thai_date($p['created_at'], true)) ?></td>
    <td><span class="badge bg-<?= e($s[1]) ?> <?= $s[1] === 'warning' ? 'text-dark' : '' ?>"><?= e($s[0]) ?></span><?= $p['converted_mou_id'] ? ' <span class="badge bg-info text-dark">แปลงเป็น MOU</span>' : '' ?></td>
    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="proposal_view.php?id=<?= (int) $p['id'] ?>">เปิด</a></td></tr>
  <?php endforeach; ?></tbody></table></div></div>
<?php admin_footer();
