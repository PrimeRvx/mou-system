<?php
/** public/mou_list.php — ระบบสืบค้นและคลังข้อมูล MOU (2.2) */
require_once __DIR__ . '/../includes/layout_public.php';

$filters = [
    'q' => mb_substr(get('q'), 0, 100), 'type' => get('type'), 'program' => get('program'),
    'status' => get('status'), 'year' => get('year'), 'province' => get('province'),
];
// หน้าสาธารณะแสดงเฉพาะสถานะ มีผล/ใกล้หมดอายุ/หมดอายุ
if (!in_array($filters['status'], ['active', 'expiring', 'expired'], true)) { $filters['status'] = ''; }
$rows     = mou_search($filters, true);
$programs = all_programs();
$hasFilter = array_filter($filters, fn($v) => $v !== '') !== [];

public_header('สืบค้น MOU', 'ค้นหาและกรองข้อมูลบันทึกข้อตกลงความร่วมมือตามประเภทหน่วยงาน สาขาวิชา สถานะ ปีที่ลงนาม และจังหวัด', ['active' => 'mou', 'datatables' => true]);
?>
<div class="container mt-4">
  <nav aria-label="breadcrumb" class="fade-down"><ol class="breadcrumb mb-2">
    <li class="breadcrumb-item"><a href="<?= e(route('home')) ?>"><i class="bi bi-house" aria-hidden="true"></i> หน้าหลัก</a></li>
    <li class="breadcrumb-item active" aria-current="page">สืบค้น MOU</li></ol></nav>
  <div class="section-kicker">คลังข้อมูล MOU</div>
  <h1 class="section-title h3 fade-up">สืบค้นบันทึกข้อตกลงความร่วมมือ (MOU)</h1>

  <!-- แท็บแยกตามประเภทหน่วยงาน -->
  <nav aria-label="ประเภทหน่วยงาน"><ul class="nav nav-pills mb-3 flex-nowrap overflow-auto pb-1 scroll-thin fade-up delay-1">
    <?php $tabQ = $filters; unset($tabQ['type']); ?>
    <li class="nav-item"><a class="nav-link <?= $filters['type'] === '' ? 'active' : '' ?>" <?= $filters['type'] === '' ? 'aria-current="page"' : '' ?> href="?<?= e(http_build_query($tabQ)) ?>">ทั้งหมด</a></li>
    <?php foreach (partner_types() as $k => $lbl): ?>
    <li class="nav-item"><a class="nav-link <?= $filters['type'] === $k ? 'active' : '' ?>" <?= $filters['type'] === $k ? 'aria-current="page"' : '' ?> href="?<?= e(http_build_query($tabQ + ['type' => $k])) ?>"><?= e($lbl) ?></a></li>
    <?php endforeach; ?>
  </ul></nav>

  <form class="filter-box mb-4 fade-up delay-2" method="get" id="filterForm" role="search" aria-label="ตัวกรอง MOU">
    <input type="hidden" name="type" value="<?= e($filters['type']) ?>">
    <div class="row g-2">
      <div class="col-lg-4"><label class="visually-hidden" for="fq">คำค้น</label><div class="input-group"><span class="input-group-text" aria-hidden="true"><i class="bi bi-search" aria-hidden="true"></i></span><input type="search" id="fq" name="q" class="form-control" maxlength="100" value="<?= e($filters['q']) ?>" placeholder="คำค้น: ชื่อเรื่อง/หน่วยงาน/เลขที่ MOU/สาขา"></div></div>
      <div class="col-6 col-lg-2"><label class="visually-hidden" for="fprog">สาขาวิชา</label><select id="fprog" name="program" class="form-select"><option value="">ทุกสาขาวิชา</option>
        <?php foreach ($programs as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (string) $p['id'] === $filters['program'] ? 'selected' : '' ?>><?= e($p['level'] . ' ' . $p['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-6 col-lg-2"><label class="visually-hidden" for="fstatus">สถานะ</label><select id="fstatus" name="status" class="form-select"><option value="">ทุกสถานะ</option>
        <?php foreach (['active', 'expiring', 'expired'] as $s): ?><option value="<?= e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(mou_statuses()[$s][0]) ?></option><?php endforeach; ?></select></div>
      <div class="col-6 col-lg-2"><label class="visually-hidden" for="fyear">ปีที่ลงนาม</label><select id="fyear" name="year" class="form-select"><option value="">ทุกปีที่ลงนาม</option>
        <?php foreach (mou_sign_years() as $y): ?><option value="<?= (int) $y ?>" <?= (string) $y === $filters['year'] ? 'selected' : '' ?>>พ.ศ. <?= (int) $y + 543 ?></option><?php endforeach; ?></select></div>
      <div class="col-6 col-lg-2"><label class="visually-hidden" for="fprov">จังหวัด</label><select id="fprov" name="province" class="form-select"><option value="">ทุกจังหวัด</option>
        <?php foreach (partner_provinces() as $pv): ?><option <?= $filters['province'] === $pv ? 'selected' : '' ?>><?= e($pv) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="mt-2 d-flex gap-2 flex-wrap align-items-center">
      <button class="btn btn-navy"><i class="bi bi-funnel" aria-hidden="true"></i> กรองข้อมูล</button>
      <a href="<?= e(route('mou')) ?>" class="btn btn-outline-secondary"><i class="bi bi-x-circle" aria-hidden="true"></i> ล้างตัวกรอง</a>
      <span class="ms-auto chip chip-primary" role="status">พบ <strong class="mx-1"><?= count($rows) ?></strong> รายการ</span>
      <div class="btn-group" role="group" aria-label="รูปแบบการแสดงผล">
        <button type="button" class="btn btn-outline-primary js-view-toggle active" data-view="table" aria-pressed="true"><i class="bi bi-table" aria-hidden="true"></i> ตาราง</button>
        <button type="button" class="btn btn-outline-primary js-view-toggle" data-view="cards" aria-pressed="false"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i> การ์ด</button>
      </div>
    </div>
  </form>

  <div id="view-table" class="table-wrap fade-up delay-3">
    <div class="table-responsive">
    <table class="table table-hover align-middle js-dt" data-order='[4,"desc"]'>
      <caption class="visually-hidden">รายการ MOU ที่ตรงกับตัวกรอง <?= count($rows) ?> รายการ</caption>
      <thead class="table-light"><tr><th>เลขที่ MOU</th><th>ชื่อเรื่อง / หน่วยงาน</th><th>ประเภท</th><th>จังหวัด</th><th>วันที่ลงนาม</th><th>สิ้นสุด</th><th>สถานะ</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $m): ?>
        <tr>
          <td class="text-nowrap"><?= e($m['mou_no']) ?></td>
          <td><div class="d-flex gap-2 align-items-center">
                <?php if (!empty($m['cover_image'])): ?><img src="<?= e(media_url('mou_images', $m['cover_image'])) ?>" alt="" loading="lazy" class="rounded" style="width:52px;height:40px;object-fit:cover;flex-shrink:0"><?php endif; ?>
                <div><a class="fw-semibold text-decoration-none" href="<?= e(route('mou_detail', $m['id'])) ?>"><?= e($m['title']) ?></a>
                <div class="small text-muted"><?= e($m['partner_name']) ?></div></div></div></td>
          <td class="text-nowrap"><?= e(partner_type_label($m['partner_type'])) ?></td>
          <td><?= e($m['province']) ?></td>
          <td data-order="<?= e($m['sign_date']) ?>" class="text-nowrap"><?= e(thai_date($m['sign_date'], true)) ?></td>
          <td data-order="<?= e($m['end_date']) ?>" class="text-nowrap"><?= e(thai_date($m['end_date'], true)) ?></td>
          <td><?= status_badge($m['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>

  <div id="view-cards" class="d-none">
    <div class="row g-4 stagger">
      <?php foreach ($rows as $m): ?><div class="col-md-6 col-lg-4 card-item"><?= mou_card($m) ?></div><?php endforeach; ?>
    </div>
    <?php if (!$rows): ?><div class="surface"><?= empty_state('ไม่พบข้อมูลที่ตรงกับเงื่อนไข ลองล้างตัวกรองหรือเปลี่ยนคำค้น', 'bi-search') ?></div><?php endif; ?>
    <div class="text-center mt-3"><button type="button" class="btn btn-outline-primary d-none" id="btn-more">แสดงเพิ่มเติม</button></div>
  </div>
</div>
<?php public_footer();
