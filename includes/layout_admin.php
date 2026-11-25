<?php
/**
 * includes/layout_admin.php — โครงหน้าหลังบ้าน (Sidebar ซ้าย + แถบบน)
 * ใช้: admin_header('ชื่อหน้า','mou','permission', ['charts'=>true]); ... admin_footer();
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/../models/mou_model.php';

function admin_header(string $title, string $active, string $permission = 'dashboard', array $opts = []): void
{
    require_permission($permission);
    refresh_mou_status();
    $user = current_user();
    $badgeExp = expiring_count();
    $badgeProp = can('proposal') ? new_proposal_count() : 0;
    // [key, ข้อความ, ไอคอน, ลิงก์, สิทธิ์, badge, กลุ่ม]
    $menu = [
        ['dashboard', 'แดชบอร์ด', 'bi-speedometer2', 'admin/index.php', 'dashboard', 0, 'ภาพรวม'],
        ['report', 'รายงาน', 'bi-bar-chart-line', 'admin/reports.php', 'report', 0, 'ภาพรวม'],
        ['mou', 'ข้อตกลง MOU', 'bi-file-earmark-text', 'admin/mou_list.php', 'mou', $badgeExp, 'งานข้อตกลง'],
        ['renewal', 'ต่ออายุสัญญา', 'bi-arrow-repeat', 'admin/renewals.php', 'renewal', 0, 'งานข้อตกลง'],
        ['proposal', 'ข้อเสนอความร่วมมือ', 'bi-inbox', 'admin/proposals.php', 'proposal', $badgeProp, 'งานข้อตกลง'],
        ['partner', 'หน่วยงานคู่ความร่วมมือ', 'bi-buildings', 'admin/partners.php', 'partner', 0, 'ข้อมูลหลัก'],
        ['program', 'สาขาวิชา', 'bi-mortarboard', 'admin/programs.php', 'program', 0, 'ข้อมูลหลัก'],
        ['news', 'ข่าวประชาสัมพันธ์', 'bi-newspaper', 'admin/news.php', 'news', 0, 'ข้อมูลหลัก'],
        ['user', 'ผู้ใช้งาน', 'bi-people', 'admin/users.php', 'user', 0, 'ระบบ'],
        ['log', 'Activity Log', 'bi-clock-history', 'admin/logs.php', 'log', 0, 'ระบบ'],
        ['setting', 'ตั้งค่าเว็บ', 'bi-gear', 'admin/settings.php', 'setting', 0, 'ระบบ'],
    ];
    $college = setting('college_name', 'วิทยาลัยพณิชยการบางนา');
    // การแจ้งเตือนในระบบ (ข้อเสนอใหม่ / MOU ใกล้หมดอายุ) — แสดงที่รูประฆังบนแถบบน
    $notes = []; $noteCount = 0;
    if (can('proposal')) {
        try {
            $noteCount = (int) db_val('SELECT COUNT(*) FROM notifications WHERE is_read = 0');
            $notes = $noteCount ? db_all('SELECT id, type, message, created_at FROM notifications WHERE is_read = 0 ORDER BY id DESC LIMIT 8') : [];
        } catch (Throwable $e) {
            error_log('notifications: ' . $e->getMessage());
        }
    }
    ?>
<!doctype html>
<html lang="th" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= theme_boot_script() ?>
<meta name="robots" content="noindex,nofollow">
<meta name="color-scheme" content="light dark">
<title><?= e($title) ?> | หลังบ้าน MOU</title>
<?= ui_head_assets(true) ?>
</head>
<body class="admin-body">
<?= page_loader() ?>
<div class="admin-wrap">
  <aside class="admin-side offcanvas-lg offcanvas-start" id="sideMenu" tabindex="-1" aria-label="เมนูหลังบ้าน">
    <div class="d-flex align-items-center">
      <a class="side-brand flex-grow-1" href="<?= e(url('admin/index.php')) ?>"><span class="logo-slot bounce-icon" aria-hidden="true"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
        <div class="lh-sm"><small class="text-gold d-block">ระบบหลังบ้าน MOU</small><?= e($college) ?></div></a>
      <button type="button" class="btn-close btn-close-white d-lg-none me-3" data-bs-dismiss="offcanvas" data-bs-target="#sideMenu" aria-label="ปิดเมนู"></button>
    </div>
    <nav class="nav flex-column mt-1">
      <?php $group = null; foreach ($menu as $m): if (!can($m[4])) { continue; }
        if ($group !== $m[6]): $group = $m[6]; ?><div class="side-label"><?= e($group) ?></div><?php endif; ?>
        <a class="nav-link <?= $active === $m[0] ? 'active' : '' ?>" <?= $active === $m[0] ? 'aria-current="page"' : '' ?> href="<?= e(url($m[3])) ?>">
          <i class="bi <?= e($m[2]) ?>" aria-hidden="true"></i> <span><?= e($m[1]) ?></span>
          <?php if ($m[5] > 0): ?><span class="badge rounded-pill bg-<?= $m[0] === 'mou' ? 'warning text-dark' : 'danger' ?> ms-auto <?= $m[0] === 'proposal' ? 'pulse' : '' ?>"><?= (int) $m[5] ?><span class="visually-hidden"> <?= $m[0] === 'mou' ? 'รายการใกล้หมดอายุ' : 'รายการใหม่' ?></span></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <div class="side-label">เว็บไซต์</div>
      <a class="nav-link" href="<?= e(route('home')) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> <span>ดูเว็บไซต์สาธารณะ<span class="visually-hidden"> (เปิดแท็บใหม่)</span></span></a>
    </nav>
    <div class="side-foot"><i class="bi bi-shield-lock" aria-hidden="true"></i> เข้าสู่ระบบในชื่อ <?= e($user['username']) ?></div>
  </aside>
  <div class="admin-main">
    <header class="admin-top">
      <button class="btn btn-soft btn-icon d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sideMenu" aria-controls="sideMenu" aria-label="เปิดเมนู"><i class="bi bi-list" aria-hidden="true"></i></button>
      <div class="flex-grow-1 min-w-0">
        <div class="crumb d-none d-sm-block"><i class="bi bi-house-door" aria-hidden="true"></i> หลังบ้าน</div>
        <h1 class="mb-0 text-truncate"><?= e($title) ?></h1>
      </div>
      <?php if ($badgeExp > 0 && can('mou')): ?>
        <a class="btn btn-sm btn-warning d-none d-md-inline-flex align-items-center gap-1" href="<?= e(url('admin/mou_list.php?status=expiring')) ?>"><i class="bi bi-bell-fill" aria-hidden="true"></i> ใกล้หมดอายุ <?= (int) $badgeExp ?> รายการ</a>
      <?php endif; ?>
      <?php if (can('proposal')): ?>
      <div class="dropdown">
        <button type="button" class="btn btn-soft btn-icon position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="การแจ้งเตือน<?= $noteCount > 0 ? ' ' . (int) $noteCount . ' รายการใหม่' : '' ?>" title="การแจ้งเตือน"><i class="bi bi-bell" aria-hidden="true"></i>
          <?php if ($noteCount > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.72rem" aria-hidden="true"><?= $noteCount > 99 ? '99+' : (int) $noteCount ?></span><?php endif; ?></button>
        <div class="dropdown-menu dropdown-menu-end p-0 overflow-hidden" style="width:min(22rem,92vw)">
          <div class="px-3 py-2 fw-semibold border-bottom"><i class="bi bi-bell me-1" aria-hidden="true"></i> การแจ้งเตือน<?= $noteCount > 0 ? ' (' . (int) $noteCount . ')' : '' ?></div>
          <?php if (!$notes): ?><div class="px-3 py-3 small text-muted">ไม่มีการแจ้งเตือนใหม่</div><?php endif; ?>
          <?php foreach ($notes as $nt): ?>
          <form method="post" action="<?= e(url('admin/notif_read.php')) ?>" class="border-bottom"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $nt['id'] ?>">
            <button type="submit" class="dropdown-item text-wrap small py-2"><i class="bi <?= $nt['type'] === 'expiring' ? 'bi-hourglass-split text-warning' : 'bi-inbox text-primary' ?> me-1" aria-hidden="true"></i><?= e($nt['message']) ?>
              <span class="d-block text-muted meta-sm"><?= e(thai_datetime($nt['created_at'], true)) ?></span></button></form>
          <?php endforeach; ?>
          <?php if ($notes): ?><form method="post" action="<?= e(url('admin/notif_read.php')) ?>" class="text-center py-1"><?= csrf_field() ?><input type="hidden" name="all" value="1">
            <button type="submit" class="btn btn-sm btn-link text-decoration-none">ทำเครื่องหมายว่าอ่านแล้วทั้งหมด</button></form><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
      <?= theme_toggle_button(true) ?>
      <div class="dropdown">
        <button type="button" class="user-chip dropdown-toggle border-0" data-bs-toggle="dropdown" aria-expanded="false" aria-label="บัญชีผู้ใช้: <?= e($user['full_name']) ?>">
          <span class="avatar" aria-hidden="true"><?= e(initial_of($user['full_name'])) ?></span>
          <span class="d-none d-md-inline text-start lh-sm"><span class="d-block small fw-semibold"><?= e($user['full_name']) ?></span>
            <span class="d-block text-muted meta-sm"><?= e(role_labels()[$user['role']] ?? $user['role']) ?></span></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li class="d-md-none"><span class="dropdown-item-text small"><strong><?= e($user['full_name']) ?></strong><br><span class="text-muted"><?= e(role_labels()[$user['role']] ?? $user['role']) ?></span></span></li>
          <li><a class="dropdown-item" href="<?= e(url('admin/profile.php')) ?>"><i class="bi bi-key me-2" aria-hidden="true"></i>เปลี่ยนรหัสผ่าน</a></li>
          <li><button type="button" class="dropdown-item" data-theme-toggle data-theme-label><i class="bi bi-circle-half me-2" aria-hidden="true"></i>สลับโหมดมืด/สว่าง</button></li>
          <li><hr class="dropdown-divider"></li>
          <li><form method="post" action="<?= e(url('admin/logout.php')) ?>"><?= csrf_field() ?><button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>ออกจากระบบ</button></form></li>
        </ul>
      </div>
    </header>
    <div class="admin-content fade-in">
<?php
    $GLOBALS['_admin_opts'] = $opts;
}

function admin_footer(): void
{
    $opts = $GLOBALS['_admin_opts'] ?? [];
    ?>
    </div>
  </div>
</div>
<?= ui_scripts(!empty($opts['charts'])) ?>
<?= flash_script() ?>
</body>
</html>
<?php
}

/** ตรวจ POST + CSRF + สิทธิ์เขียน ใช้ต้นไฟล์ที่รับฟอร์ม */
function admin_post_guard(string $permission): void
{
    require_permission($permission);
    require_writer();
    csrf_require();
}
