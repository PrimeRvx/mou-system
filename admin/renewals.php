<?php
/** admin/renewals.php — บันทึกการต่ออายุสัญญา + ประวัติทั้งหมด */
require_once __DIR__ . '/../includes/layout_admin.php';
require_permission('renewal');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('renewal');
    $mouId = (int) post('mou_id');
    $m = db_one("SELECT id, mou_no, start_date, end_date, status FROM mou WHERE id = ? AND deleted_at IS NULL", [$mouId]);
    $new = parse_thai_input(post('new_end_date'));
    $err = null;
    if (!$m) { $err = 'กรุณาเลือก MOU'; }
    elseif ($m['status'] === 'cancelled') { $err = 'MOU ที่ยกเลิกแล้วต่ออายุไม่ได้'; }
    elseif (!$new) { $err = 'กรุณาระบุวันสิ้นสุดใหม่'; }
    elseif ($new <= $m['end_date']) { $err = 'วันสิ้นสุดใหม่ต้องมากกว่าวันสิ้นสุดเดิม (' . thai_date($m['end_date']) . ')'; }
    elseif (post('doc_no') === '') { $err = 'กรุณากรอกเลขที่หนังสือ'; }
    if ($err) {
        flash('error', $err);
        redirect('admin/renewals.php?mou_id=' . $mouId);
    }
    $pdo = db(); $pdo->beginTransaction();
    try {
        db_insert('INSERT INTO mou_renewals (mou_id, old_end_date, new_end_date, doc_no, reason, recorded_by) VALUES (?,?,?,?,?,?)',
            [$mouId, $m['end_date'], $new, mb_substr(post('doc_no'), 0, 100), post('reason') ?: null, $_SESSION['user']['id']]);
        $years = round((strtotime($new) - strtotime($m['start_date'])) / 86400 / 365.25, 1);
        // draft คงเป็นร่าง; อื่นๆ ตั้งเป็น active แล้วให้ระบบคำนวณสถานะต่อ
        db_exec("UPDATE mou SET end_date = ?, duration_years = ?, status = IF(status = 'draft', 'draft', 'active') WHERE id = ?", [$new, $years, $mouId]);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack(); error_log($ex->getMessage());
        flash('error', 'บันทึกไม่สำเร็จ'); redirect('admin/renewals.php?mou_id=' . $mouId);
    }
    refresh_mou_status(true);
    log_activity('mou_renew', 'mou#' . $mouId, $m['mou_no'] . ' ' . $m['end_date'] . ' → ' . $new);
    flash('success', 'บันทึกการต่ออายุเรียบร้อยแล้ว สถานะถูกอัปเดตอัตโนมัติ');
    redirect('admin/mou_view.php?id=' . $mouId);
}

admin_header('ต่ออายุสัญญา', 'renewal', 'renewal');
$sel = (int) get('mou_id');
$canWrite = can_write();
$options = db_all("SELECT m.id, m.mou_no, m.title, m.end_date, m.status, p.name partner FROM mou m JOIN partners p ON p.id = m.partner_id
                    WHERE m.deleted_at IS NULL AND m.status <> 'cancelled' ORDER BY (m.status IN ('expiring','expired')) DESC, m.end_date");
$history = db_all("SELECT r.*, m.mou_no, m.title, u.full_name FROM mou_renewals r JOIN mou m ON m.id = r.mou_id
                    LEFT JOIN users u ON u.id = r.recorded_by ORDER BY r.recorded_at DESC, r.id DESC");
?>
<div class="row g-3 stagger">
  <?php if ($canWrite): ?>
  <div class="col-lg-5"><div class="panel"><h2><i class="bi bi-arrow-repeat" aria-hidden="true"></i> บันทึกการต่ออายุ</h2>
    <form method="post" id="renewForm"><?= csrf_field() ?>
      <div class="mb-3"><label class="form-label" for="mouSel">เลือก MOU *</label>
        <select name="mou_id" id="mouSel" class="form-select" required><option value="">เลือก...</option>
          <?php foreach ($options as $o): ?><option value="<?= (int) $o['id'] ?>" data-end="<?= e($o['end_date']) ?>" <?= $sel === (int) $o['id'] ? 'selected' : '' ?>>
            <?= e($o['mou_no'] . ' — ' . $o['partner']) ?> [<?= e(mou_statuses()[$o['status']][0] ?? '') ?>]</option><?php endforeach; ?></select>
        <div class="form-text" id="curEnd"></div></div>
      <div class="mb-3"><label class="form-label" for="f-new_end_date">วันสิ้นสุดใหม่ *</label><input id="f-new_end_date" type="date" name="new_end_date" class="form-control" required><div class="form-text" id="newBe"></div></div>
      <div class="mb-3"><label class="form-label" for="f-doc_no">เลขที่หนังสือต่ออายุ *</label><input id="f-doc_no" name="doc_no" class="form-control" maxlength="100" required></div>
      <div class="mb-3"><label class="form-label" for="f-reason">เหตุผล</label><textarea id="f-reason" name="reason" class="form-control" rows="3"></textarea></div>
      <button class="btn btn-navy"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> บันทึกการต่ออายุ</button>
    </form></div></div>
  <?php endif; ?>
  <div class="<?= $canWrite ? 'col-lg-7' : 'col-12' ?>"><div class="panel"><h2><i class="bi bi-clock-history" aria-hidden="true"></i> ประวัติการต่ออายุทั้งหมด</h2>
    <div class="table-responsive"><table class="table table-sm js-dt" data-order='[0,"desc"]'>
      <thead><tr><th>วันที่บันทึก</th><th>MOU</th><th>วันสิ้นสุดเดิม → ใหม่</th><th>เลขที่หนังสือ</th><th>ผู้บันทึก</th></tr></thead><tbody>
      <?php foreach ($history as $h): ?><tr><td data-order="<?= e($h['recorded_at']) ?>"><?= e(thai_date($h['recorded_at'], true)) ?></td>
        <td><a href="mou_view.php?id=<?= (int) $h['mou_id'] ?>"><?= e($h['mou_no']) ?></a></td>
        <td class="text-nowrap"><?= e(thai_date($h['old_end_date'], true)) ?> → <strong><?= e(thai_date($h['new_end_date'], true)) ?></strong></td>
        <td><?= e($h['doc_no']) ?><div class="small text-muted"><?= e($h['reason']) ?></div></td><td><?= e($h['full_name']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div></div></div>
</div>
<script nonce="<?= e(csp_nonce()) ?>">
document.addEventListener('DOMContentLoaded', function () {
  var sel = document.getElementById('mouSel'); if (!sel) return;
  var mths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
  function be(v) { if (!v) return ''; var p = v.split('-'); return parseInt(p[2], 10) + ' ' + mths[parseInt(p[1], 10)] + ' ' + (parseInt(p[0], 10) + 543); }
  function upd() { var o = sel.options[sel.selectedIndex]; var d = o && o.dataset.end;
    document.getElementById('curEnd').textContent = d ? 'วันสิ้นสุดปัจจุบัน: ' + be(d) : '';
    var ne = document.querySelector('[name=new_end_date]'); if (d) ne.min = d; }
  sel.addEventListener('change', upd); upd();
  document.querySelector('[name=new_end_date]').addEventListener('change', function () { document.getElementById('newBe').textContent = this.value ? 'พ.ศ.: ' + be(this.value) : ''; });
});
</script>
<?php admin_footer();
