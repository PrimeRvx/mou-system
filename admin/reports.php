<?php
/** admin/reports.php — รายงานสรุป + Export Excel (XLSX/CSV) + พิมพ์/PDF */
require_once __DIR__ . '/../includes/layout_admin.php';
require_once __DIR__ . '/../includes/xlsx.php';
require_permission('report');
refresh_mou_status();

$filters = ['date_from' => get('date_from'), 'date_to' => get('date_to'), 'type' => get('type'),
            'program' => get('program'), 'status' => get('status')];
$rows = mou_search($filters, false, 5000);

$export = get('export');
if (in_array($export, ['csv', 'xlsx'], true)) {
    $data = [['ลำดับ', 'เลขที่ MOU', 'ชื่อเรื่อง', 'หน่วยงาน', 'ประเภทหน่วยงาน', 'จังหวัด', 'สาขาวิชา', 'วันที่ลงนาม', 'วันที่เริ่ม', 'วันที่สิ้นสุด', 'สถานะ', 'นักศึกษาที่ได้รับประโยชน์']];
    foreach ($rows as $i => $m) {
        $data[] = [$i + 1, $m['mou_no'], $m['title'], $m['partner_name'], partner_type_label($m['partner_type']), (string) $m['province'],
                   (string) $m['programs'], thai_date($m['sign_date']), thai_date($m['start_date']), thai_date($m['end_date']),
                   mou_statuses()[$m['status']][0], (int) $m['student_benefit_count']];
    }
    log_activity('report_export', null, $export . ' ' . count($rows) . ' แถว');
    $fn = 'mou-report-' . date('Ymd-His') . '.' . $export;
    $export === 'csv' ? csv_download($fn, $data) : xlsx_download($fn, 'รายงาน MOU', $data);
}

// สรุปตัวเลข
$byStatus = []; $byType = []; $students = 0;
foreach ($rows as $m) {
    $byStatus[$m['status']] = ($byStatus[$m['status']] ?? 0) + 1;
    $byType[$m['partner_type']] = ($byType[$m['partner_type']] ?? 0) + 1;
    $students += (int) $m['student_benefit_count'];
}
$qs = http_build_query(array_filter($filters, fn($v) => $v !== ''));
admin_header('รายงานสรุป MOU', 'report', 'report');
?>
<form class="panel no-print fade-up" method="get"><div class="row g-2 align-items-end">
  <div class="col-6 col-lg-2"><label class="form-label small mb-0" for="f-date_from">ลงนามตั้งแต่</label><input id="f-date_from" type="date" name="date_from" class="form-control" value="<?= e($filters['date_from']) ?>"></div>
  <div class="col-6 col-lg-2"><label class="form-label small mb-0" for="f-date_to">ถึงวันที่</label><input id="f-date_to" type="date" name="date_to" class="form-control" value="<?= e($filters['date_to']) ?>"></div>
  <div class="col-6 col-lg-2"><label class="form-label small mb-0" for="f-type">ประเภทหน่วยงาน</label><select id="f-type" name="type" class="form-select"><option value="">ทั้งหมด</option>
    <?php foreach (partner_types() as $k => $l): ?><option value="<?= e($k) ?>" <?= $filters['type'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-lg-2"><label class="form-label small mb-0" for="f-program">สาขาวิชา</label><select id="f-program" name="program" class="form-select"><option value="">ทั้งหมด</option>
    <?php foreach (all_programs() as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $filters['program'] === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['level'] . ' ' . $p['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-lg-2"><label class="form-label small mb-0" for="f-status">สถานะ</label><select id="f-status" name="status" class="form-select"><option value="">ทั้งหมด</option>
    <?php foreach (mou_statuses() as $k => $s): ?><option value="<?= e($k) ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= e($s[0]) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-lg-2 d-flex gap-1"><button class="btn btn-navy flex-fill">ออกรายงาน</button><a class="btn btn-outline-secondary" href="reports.php">ล้าง</a></div>
</div></form>

<div class="d-flex gap-2 mb-3 no-print flex-wrap fade-up delay-1">
  <a class="btn btn-success" href="?<?= e($qs . ($qs ? '&' : '')) ?>export=xlsx"><i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Excel (XLSX)</a>
  <a class="btn btn-outline-success" href="?<?= e($qs . ($qs ? '&' : '')) ?>export=csv"><i class="bi bi-filetype-csv" aria-hidden="true"></i> CSV</a>
  <button class="btn btn-danger js-print"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> PDF / พิมพ์</button>
  <span class="text-muted small align-self-center">PDF: กด “พิมพ์” แล้วเลือกเครื่องพิมพ์เป็น “บันทึกเป็น PDF”</span>
</div>

<div class="panel fade-up delay-2" id="reportArea">
  <div class="text-center mb-3">
    <h3 class="mb-0"><?= e(setting('college_name')) ?></h3>
    <h4 class="mb-1">รายงานสรุปบันทึกข้อตกลงความร่วมมือ (MOU)</h4>
    <div class="small text-muted">ข้อมูล ณ วันที่ <?= e(thai_date('today')) ?>
      <?php if ($filters['date_from'] || $filters['date_to']): ?> · ลงนามระหว่าง <?= e($filters['date_from'] ? thai_date($filters['date_from']) : 'เริ่มต้น') ?> ถึง <?= e($filters['date_to'] ? thai_date($filters['date_to']) : 'ปัจจุบัน') ?><?php endif; ?>
      <?= isset(partner_types()[$filters['type']]) ? ' · ประเภท: ' . e(partner_type_label($filters['type'])) : '' ?>
      <?= isset(mou_statuses()[$filters['status']]) ? ' · สถานะ: ' . e(mou_statuses()[$filters['status']][0]) : '' ?></div>
  </div>
  <div class="row g-2 mb-3 text-center stagger scale">
    <div class="col-6 col-md-3"><div class="border rounded-4 p-3 h-100 hover-lift"><div class="small text-muted">รวม</div><strong class="fs-4"><?= count($rows) ?></strong> ฉบับ</div></div>
    <div class="col-6 col-md-3"><div class="border rounded-4 p-3 h-100 hover-lift"><div class="small text-muted">มีผล / ใกล้หมด / หมดอายุ</div><strong><?= (int) ($byStatus['active'] ?? 0) ?> / <?= (int) ($byStatus['expiring'] ?? 0) ?> / <?= (int) ($byStatus['expired'] ?? 0) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="border rounded-4 p-3 h-100 hover-lift"><div class="small text-muted">ตามประเภท</div><small><?php foreach (partner_types() as $k => $l): ?><?= e($l) ?> <?= (int) ($byType[$k] ?? 0) ?> · <?php endforeach; ?></small></div></div>
    <div class="col-6 col-md-3"><div class="border rounded-4 p-3 h-100 hover-lift"><div class="small text-muted">นักศึกษาที่ได้รับประโยชน์</div><strong class="fs-4"><?= number_format($students) ?></strong> คน</div></div>
  </div>
  <div class="table-responsive"><table class="table table-bordered table-sm align-middle">
    <thead class="table-light"><tr><th>#</th><th>เลขที่ MOU</th><th>ชื่อเรื่อง / หน่วยงาน</th><th>ประเภท</th><th>สาขา</th><th>ลงนาม</th><th>สิ้นสุด</th><th>สถานะ</th><th class="text-end">นศ.</th></tr></thead><tbody>
    <?php foreach ($rows as $i => $m): ?><tr><td><?= $i + 1 ?></td><td class="text-nowrap"><?= e($m['mou_no']) ?></td>
      <td><?= e($m['title']) ?><div class="small text-muted"><?= e($m['partner_name']) ?></div></td><td><?= e(partner_type_label($m['partner_type'])) ?></td>
      <td class="small"><?= e($m['programs']) ?></td><td class="text-nowrap"><?= e(thai_date($m['sign_date'], true)) ?></td><td class="text-nowrap"><?= e(thai_date($m['end_date'], true)) ?></td>
      <td><?= e(mou_statuses()[$m['status']][0]) ?></td><td class="text-end"><?= number_format((int) $m['student_benefit_count']) ?></td></tr><?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9" class="text-center text-muted">ไม่พบข้อมูลตามเงื่อนไข</td></tr><?php endif; ?></tbody></table></div>
  <div class="d-none d-print-block mt-5 text-end small">ลงชื่อ ........................................ ผู้จัดทำรายงาน<br>(<?= e($_SESSION['user']['full_name']) ?>)</div>
</div>
<?php admin_footer();
