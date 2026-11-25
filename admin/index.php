<?php
/** admin/index.php — Dashboard */
require_once __DIR__ . '/../includes/layout_admin.php';
require_permission('dashboard');

// โหลดข้อมูลให้ครบก่อนพิมพ์ส่วนหัวของหน้า (ถ้าคิวรีผิดพลาดจะได้หน้าแจ้งข้อผิดพลาดเต็มหน้า ไม่ใช่หน้าค้างครึ่งเดียว)
$st = admin_stats();
$byType = db_all("SELECT p.type k, COUNT(*) c FROM mou m JOIN partners p ON p.id = m.partner_id
                   WHERE m.deleted_at IS NULL AND m.status <> 'cancelled' GROUP BY p.type");
$byYear = db_all("SELECT YEAR(sign_date) y, COUNT(*) c FROM mou WHERE deleted_at IS NULL AND status <> 'cancelled'
                   GROUP BY y ORDER BY y");
$byProg = db_all("SELECT CONCAT(pr.level, ' ', pr.name) n, COUNT(*) c FROM mou_programs mp
                   JOIN mou m ON m.id = mp.mou_id AND m.deleted_at IS NULL AND m.status <> 'cancelled'
                   JOIN programs pr ON pr.id = mp.program_id GROUP BY pr.id ORDER BY c DESC, n LIMIT 10");
$expiring = db_all("SELECT m.id, m.mou_no, m.title, m.end_date, m.status, p.name partner FROM mou m JOIN partners p ON p.id = m.partner_id
                     WHERE m.deleted_at IS NULL AND m.status IN ('expiring','expired') ORDER BY m.end_date LIMIT 10");
$newProps = db_all("SELECT id, ref_no, org_name, created_at FROM proposals WHERE status = 'new' ORDER BY id DESC LIMIT 5");

$typeLabels = []; $typeData = [];
foreach ($byType as $r) { $typeLabels[] = partner_type_label($r['k']); $typeData[] = (int) $r['c']; }
$yearLabels = array_map(fn($r) => (string) ((int) $r['y'] + 543), $byYear);
$yearData   = array_map(fn($r) => (int) $r['c'], $byYear);
$progLabels = array_column($byProg, 'n');
$progData   = array_map(fn($r) => (int) $r['c'], $byProg);

/** ข้อความสรุปกราฟสำหรับโปรแกรมอ่านหน้าจอ (WCAG 1.1.1) */
function chart_summary(string $title, array $labels, array $data): string
{
    if (!$data) { return $title . ': ยังไม่มีข้อมูล'; }
    $parts = [];
    foreach ($labels as $i => $l) { $parts[] = $l . ' ' . number_format($data[$i] ?? 0) . ' รายการ'; }
    return $title . ': ' . implode(', ', $parts);
}

/** ตารางข้อมูลของกราฟ (พับไว้ เปิดดูได้) */
function chart_table(string $caption, string $col, array $labels, array $data): string
{
    if (!$data) { return ''; }
    $h = '<details class="mt-2 small"><summary class="text-muted">ดูข้อมูลเป็นตาราง</summary><table class="table table-sm mt-2 mb-0"><caption class="visually-hidden">'
       . e($caption) . '</caption><thead><tr><th scope="col">' . e($col) . '</th><th scope="col" class="text-end">จำนวน MOU</th></tr></thead><tbody>';
    foreach ($labels as $i => $l) {
        $h .= '<tr><th scope="row" class="fw-normal">' . e($l) . '</th><td class="text-end">' . number_format($data[$i] ?? 0) . '</td></tr>';
    }
    return $h . '</tbody></table></details>';
}
$hour = (int) date('G');
$greet = $hour < 12 ? 'สวัสดีตอนเช้า' : ($hour < 17 ? 'สวัสดีตอนบ่าย' : 'สวัสดีตอนเย็น');
admin_header('แดชบอร์ด', 'dashboard', 'dashboard', ['charts' => true]);
?>
<div class="page-head fade-up">
  <div><div class="sub"><?= e(thai_date('today')) ?></div><h2 class="title"><?= e($greet) ?>, <?= e(current_user()['full_name']) ?></h2></div>
  <?php if (can('mou') && can_write()): ?><a class="btn btn-gold" href="<?= e(url('admin/mou_form.php')) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> เพิ่ม MOU ใหม่</a><?php endif; ?>
</div>
<?php if (can('mou') && $st['expiring'] > 0): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 fade-up delay-1"><i class="bi bi-bell-fill fs-5" aria-hidden="true"></i>
  <span>มี MOU ใกล้หมดอายุภายใน <?= (int) expiring_days() ?> วัน จำนวน <strong><?= (int) $st['expiring'] ?></strong> รายการ</span>
  <a class="ms-auto btn btn-sm btn-warning" href="<?= e(url('admin/mou_list.php?status=expiring')) ?>">ดูรายการ <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
<?php endif; ?>
<?php // เตือนผู้ดูแลระบบเมื่อไม่ได้สำรองข้อมูลเกิน 7 วัน (สำรองได้ที่หน้าตั้งค่าเว็บ)
$lastBackup = setting('last_backup_at');
if (can('setting') && ($lastBackup === '' || time() - strtotime($lastBackup) > 7 * 86400)): ?>
<div class="alert alert-info d-flex align-items-center gap-2 fade-up delay-1"><i class="bi bi-database-exclamation fs-5" aria-hidden="true"></i>
  <span><?= $lastBackup === '' ? 'ยังไม่เคยสำรองข้อมูลระบบ' : 'ไม่ได้สำรองข้อมูลระบบมาแล้ว ' . (int) floor((time() - strtotime($lastBackup)) / 86400) . ' วัน' ?> — ควรดาวน์โหลดไฟล์สำรองเก็บไว้นอกเครื่องเซิร์ฟเวอร์</span>
  <a class="ms-auto btn btn-sm btn-outline-primary text-nowrap" href="<?= e(url('admin/settings.php')) ?>">ไปหน้าสำรองข้อมูล <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
<?php endif; ?>

<div class="row g-3 mb-4 stagger scale">
  <?php foreach ([['MOU ทั้งหมด', $st['total'], 'navy', 'bi-file-earmark-text', ''],
                  ['มีผลบังคับใช้', $st['active'], 'success', 'bi-patch-check', 'active'],
                  ['ใกล้หมดอายุ (' . expiring_days() . ' วัน)', $st['expiring'], 'warning', 'bi-hourglass-split', 'expiring'],
                  ['หมดอายุ', $st['expired'], 'danger', 'bi-x-octagon', 'expired']] as $c): ?>
  <div class="col-6 col-xl-3"><a class="card card-stat t-<?= e($c[2]) ?> text-decoration-none" href="<?= e(url('admin/mou_list.php' . ($c[4] ? '?status=' . $c[4] : ''))) ?>"><div class="card-body">
    <span class="ic ic-<?= e($c[2]) ?>"><i class="bi <?= e($c[3]) ?>" aria-hidden="true"></i></span>
    <div><div class="num" data-count="<?= (int) $c[1] ?>"><?= number_format((int) $c[1]) ?></div><div class="lbl"><?= e($c[0]) ?></div></div></div></a></div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-4 reveal"><div class="panel h-100"><?= panel_title('bi-pie-chart', 'MOU ตามประเภทหน่วยงาน') ?><div style="height:260px"><canvas id="chType" role="img" aria-label="<?= e(chart_summary('MOU ตามประเภทหน่วยงาน', $typeLabels, $typeData)) ?>"></canvas></div>
    <?= chart_table('ข้อมูลกราฟ: MOU ตามประเภทหน่วยงาน', 'ประเภทหน่วยงาน', $typeLabels, $typeData) ?></div></div>
  <div class="col-lg-8 reveal"><div class="panel h-100"><?= panel_title('bi-bar-chart', 'จำนวน MOU ที่ลงนามในแต่ละปี (พ.ศ.)') ?><div style="height:260px"><canvas id="chYear" role="img" aria-label="<?= e(chart_summary('จำนวน MOU ที่ลงนามในแต่ละปี', $yearLabels, $yearData)) ?>"></canvas></div>
    <?= chart_table('ข้อมูลกราฟ: จำนวน MOU ที่ลงนามในแต่ละปี', 'ปี พ.ศ.', $yearLabels, $yearData) ?></div></div>
  <div class="col-lg-6 reveal"><div class="panel"><?= panel_title('bi-mortarboard', 'MOU ตามสาขาวิชา (10 อันดับ)') ?><div style="height:300px"><canvas id="chProg" role="img" aria-label="<?= e(chart_summary('MOU ตามสาขาวิชา 10 อันดับแรก', $progLabels, $progData)) ?>"></canvas></div>
    <?= chart_table('ข้อมูลกราฟ: MOU ตามสาขาวิชา', 'สาขาวิชา', $progLabels, $progData) ?></div></div>
  <div class="col-lg-6 reveal">
    <div class="panel"><?= panel_title('bi-hourglass-split', 'ใกล้หมดอายุ / หมดอายุ') ?>
      <?php foreach ($expiring as $m): ?>
        <div class="list-row">
          <div class="min-w-0"><a class="fw-semibold text-decoration-none" href="<?= e(url('admin/mou_view.php?id=' . $m['id'])) ?>"><?= e($m['mou_no']) ?></a>
            <div class="small text-muted text-truncate"><?= e($m['partner']) ?></div></div>
          <div class="text-end small text-nowrap"><?= status_badge($m['status']) ?><div class="text-muted"><?= e(thai_date($m['end_date'], true)) ?></div></div>
          <?php if (can('renewal') && can_write()): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/renewals.php?mou_id=' . $m['id'])) ?>"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> ต่ออายุ</a><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$expiring): ?><?= empty_state('ไม่มีรายการใกล้หมดอายุ', 'bi-emoji-smile') ?><?php endif; ?></div>
    <?php if (can('proposal')): ?>
    <div class="panel"><?= panel_title('bi-inbox', 'ข้อเสนอความร่วมมือใหม่', '<a href="' . e(url('admin/proposals.php')) . '">ดูทั้งหมด</a>') ?>
      <?php foreach ($newProps as $p): ?>
        <div class="list-row"><a class="text-decoration-none min-w-0 text-truncate" href="<?= e(url('admin/proposal_view.php?id=' . $p['id'])) ?>"><span class="chip chip-primary me-1"><?= e($p['ref_no']) ?></span> <?= e($p['org_name']) ?></a>
          <small class="text-muted text-nowrap"><?= e(thai_date($p['created_at'], true)) ?></small></div>
      <?php endforeach; ?>
      <?php if (!$newProps): ?><?= empty_state('ไม่มีข้อเสนอใหม่', 'bi-inbox') ?><?php endif; ?></div>
    <?php endif; ?>
  </div>
</div>

<script nonce="<?= e(csp_nonce()) ?>">
document.addEventListener('DOMContentLoaded', function () {
  Chart.defaults.font.family = "'Sarabun', sans-serif";
  // ผู้ใช้ที่ตั้งค่าลดการเคลื่อนไหว: กราฟแสดงทันทีไม่มี animation
  if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) Chart.defaults.animation = false;
  var charts = [];
  function build() {
    charts.forEach(function (c) { c.destroy(); }); charts = [];
    var t = MouTheme.chart();
    Chart.defaults.color = t.text; Chart.defaults.borderColor = t.grid;
    var grid = { color: t.grid }, bar = { borderRadius: 8, borderSkipped: false, maxBarThickness: 46 };
    charts.push(new Chart(document.getElementById('chType'), { type: 'doughnut',
      data: { labels: <?= json_encode($typeLabels, JSON_FOR_SCRIPT) ?>, datasets: [{ data: <?= json_encode($typeData) ?>, backgroundColor: t.palette, borderColor: t.surface, borderWidth: 3, hoverOffset: 10 }] },
      options: { maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 14 } } } } }));
    charts.push(new Chart(document.getElementById('chYear'), { type: 'bar',
      data: { labels: <?= json_encode($yearLabels) ?>,
              datasets: [Object.assign({ label: 'จำนวน MOU', data: <?= json_encode($yearData) ?>, backgroundColor: t.primary }, bar)] },
      options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 }, grid: grid } } } }));
    charts.push(new Chart(document.getElementById('chProg'), { type: 'bar',
      data: { labels: <?= json_encode($progLabels, JSON_FOR_SCRIPT) ?>,
              datasets: [Object.assign({ label: 'จำนวน MOU', data: <?= json_encode($progData) ?>, backgroundColor: t.gold }, bar)] },
      options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 }, grid: grid }, y: { grid: { display: false } } } } }));
  }
  build();
  document.addEventListener('mou:themechange', build);   // เปลี่ยนสีกราฟตามธีม
});
</script>
<?php admin_footer();
