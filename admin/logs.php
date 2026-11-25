<?php
/** admin/logs.php — ดู Activity Log (เฉพาะ Admin) — ค้นหาและแบ่งหน้าที่ฝั่งเซิร์ฟเวอร์ ดูย้อนหลังได้ทั้งหมด */
require_once __DIR__ . '/../includes/layout_admin.php';
require_permission('log');

$q      = mb_substr(get('q'), 0, 100);
$where  = '';
$params = [];
if ($q !== '') {
    $like   = '%' . like_escape($q) . '%';
    $where  = 'WHERE username LIKE ? OR action LIKE ? OR target LIKE ? OR detail LIKE ? OR ip_address LIKE ?';
    $params = [$like, $like, $like, $like, $like];
}
$total = (int) db_val("SELECT COUNT(*) FROM activity_logs $where", $params);
$pg    = paginate($total, 50, (int) get('page', '1'));
$rows  = db_all("SELECT * FROM activity_logs $where ORDER BY id DESC LIMIT " . (int) $pg['per'] . ' OFFSET ' . (int) $pg['offset'], $params);
$link  = fn(int $p) => '?' . http_build_query(array_filter(['q' => $q, 'page' => $p > 1 ? $p : null], fn($v) => $v !== null && $v !== ''));
admin_header('Activity Log', 'log', 'log');   // โหลดข้อมูลครบแล้วจึงพิมพ์ส่วนหัว
?>
<div class="panel fade-up"><h2><i class="bi bi-clock-history" aria-hidden="true"></i> ประวัติการใช้งานระบบ
  <span class="panel-sub">ทั้งหมด <?= number_format($total) ?> รายการ<?= LOG_RETENTION_DAYS > 0 ? ' · เก็บย้อนหลัง ' . (int) LOG_RETENTION_DAYS . ' วัน' : '' ?></span></h2>
<form method="get" class="row g-2 mb-3"><div class="col-sm-8 col-md-5"><div class="input-group"><span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
  <input type="search" name="q" class="form-control" maxlength="100" aria-label="ค้นหาบันทึกการใช้งาน" placeholder="ค้นหา ผู้ใช้ / การกระทำ / เป้าหมาย / รายละเอียด / IP" value="<?= e($q) ?>"></div></div>
  <div class="col-auto"><button class="btn btn-navy"><i class="bi bi-funnel" aria-hidden="true"></i> ค้นหา</button>
  <?php if ($q !== ''): ?><a class="btn btn-outline-secondary" href="logs.php" title="ล้างคำค้น" aria-label="ล้างคำค้น"><i class="bi bi-x-lg" aria-hidden="true"></i></a><?php endif; ?></div></form>
<div class="table-responsive"><table class="table table-sm align-middle">
  <thead><tr><th>เวลา</th><th>ผู้ใช้</th><th>การกระทำ</th><th>เป้าหมาย</th><th>รายละเอียด</th><th>IP</th></tr></thead><tbody>
  <?php foreach ($rows as $l): ?><tr><td class="text-nowrap"><?= e(thai_datetime($l['created_at'], true)) ?></td><td><?= e($l['username']) ?></td>
    <td><span class="badge bg-<?= $l['action'] === 'login_failed' ? 'danger' : 'secondary' ?>"><?= e($l['action']) ?></span></td><td><?= e($l['target']) ?></td><td><?= e($l['detail']) ?></td><td><?= e($l['ip_address']) ?></td></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">ไม่พบรายการ</td></tr><?php endif; ?>
  </tbody></table></div>
<?php if ($pg['pages'] > 1): $from = max(1, $pg['page'] - 2); $to = min($pg['pages'], $pg['page'] + 2); ?>
<nav class="mt-3 d-flex flex-wrap justify-content-between align-items-center gap-2" aria-label="หน้า">
  <div class="small text-muted">หน้า <?= (int) $pg['page'] ?> จาก <?= number_format($pg['pages']) ?></div>
  <ul class="pagination pagination-sm mb-0">
    <li class="page-item <?= $pg['page'] <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($link(1)) ?>">« แรกสุด</a></li>
    <li class="page-item <?= $pg['page'] <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($link($pg['page'] - 1)) ?>">‹</a></li>
    <?php for ($i = $from; $i <= $to; $i++): ?><li class="page-item <?= $i === $pg['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= e($link($i)) ?>"><?= $i ?></a></li><?php endfor; ?>
    <li class="page-item <?= $pg['page'] >= $pg['pages'] ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($link($pg['page'] + 1)) ?>">›</a></li>
    <li class="page-item <?= $pg['page'] >= $pg['pages'] ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($link($pg['pages'])) ?>">ท้ายสุด »</a></li>
  </ul></nav>
<?php endif; ?>
</div>
<?php admin_footer();
