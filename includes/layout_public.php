<?php
/**
 * includes/layout_public.php — โครงหน้าสาธารณะ (Navbar + Footer + meta SEO/Open Graph)
 * ใช้: public_header('ชื่อหน้า', 'คำอธิบาย', ['active'=>'mou']);  ...  public_footer();
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/../models/mou_model.php';

function public_header(string $title, string $desc = '', array $opts = []): void
{
    refresh_mou_status();
    $GLOBALS['_public_opts'] = $opts;   // public_footer() ใช้ตัวเลือกเดียวกัน (เช่น 'datatables' => true)
    $college = setting('college_name', 'วิทยาลัยพณิชยการบางนา');
    $full    = $title !== '' ? $title . ' | ' . $college : setting('site_name', 'ฐานข้อมูล MOU') . ' | ' . $college;
    $desc    = $desc !== '' ? $desc : setting('site_description');
    $active  = $opts['active'] ?? '';
    $logo    = setting('college_logo');
    // ใช้โฮสต์จาก BASE_URL (ผ่านการตรวจแล้ว) ไม่ใช้ header Host ดิบ
    $canon   = preg_replace('#^(https?://[^/]+).*$#i', '$1', BASE_URL) . ($_SERVER['REQUEST_URI'] ?? '/');
    // URL หลักของหน้า (canonical): ลิงก์แบบเดียวกันเสมอ ไม่ว่าจะเปิดจาก /mou/5 หรือ public/mou_detail.php?id=5
    // และไม่รวมพารามิเตอร์ค้นหา/ติดตามผล (ยกเว้นเลขหน้าของรายการข่าว)
    $script  = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $page    = (int) get('page', '1');
    $canonUrl = $opts['canonical'] ?? [
        'index.php'       => route('home'),
        'mou_list.php'    => route('mou'),
        'mou_detail.php'  => route('mou_detail', (int) get('id')),
        'news_list.php'   => route('news') . ($page > 1 ? '?page=' . $page : ''),
        'news_detail.php' => route('news_detail', (int) get('id')),
        'contact.php'     => route('contact'),
    ][$script] ?? strtok($canon, '?');
    $canon = $canonUrl;
    $ogImage = $opts['og_image'] ?? ($logo ? url('uploads/logos/' . $logo) : '');
    $nav = ['home' => ['หน้าหลัก', route('home'), 'bi-house'], 'mou' => ['สืบค้น MOU', route('mou'), 'bi-search'],
            'news' => ['ข่าวประชาสัมพันธ์', route('news'), 'bi-newspaper'], 'contact' => ['ติดต่อ/เสนอความร่วมมือ', route('contact'), 'bi-envelope']];
    ?>
<!doctype html>
<html lang="th" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= theme_boot_script() ?>
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#0b2e6b">
<title><?= e($full) ?></title>
<meta name="description" content="<?= e(excerpt($desc, 160)) ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="th_TH">
<meta property="og:title" content="<?= e($full) ?>">
<meta property="og:description" content="<?= e(excerpt($desc, 160)) ?>">
<link rel="canonical" href="<?= e($canon) ?>"><meta property="og:url" content="<?= e($canon) ?>">
<?php if ($ogImage): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<?= ui_head_assets(false, !empty($opts['datatables'])) ?>
</head>
<body class="public">
<?= page_loader() ?>
<a class="visually-hidden-focusable position-absolute top-0 start-0 m-2 btn btn-gold" style="z-index:3100" href="#main">ข้ามไปยังเนื้อหา</a>
<nav class="navbar navbar-expand-lg navbar-dark site-nav sticky-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(route('home')) ?>">
      <?php if ($logo): ?><img class="logo-img bounce-icon" src="<?= e(url('uploads/logos/' . $logo)) ?>" alt=""><?php else: ?>
      <span class="logo-slot bounce-icon" aria-hidden="true"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span><?php endif; ?>
      <span class="lh-sm"><small class="d-block text-gold">ฐานข้อมูลบันทึกข้อตกลงความร่วมมือ (MOU)</small><?= e($college) ?></span>
    </a>
    <div class="d-flex align-items-center gap-2 order-lg-last ms-auto ms-lg-2">
      <?= theme_toggle_button() ?>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="เปิดเมนู"><span class="navbar-toggler-icon"></span></button>
    </div>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
        <?php foreach ($nav as $k => $n): ?>
        <li class="nav-item"><a class="nav-link <?= $active === $k ? 'active' : '' ?>" <?= $active === $k ? 'aria-current="page"' : '' ?> href="<?= e($n[1]) ?>"><i class="bi <?= e($n[2]) ?>" aria-hidden="true"></i> <?= e($n[0]) ?></a></li>
        <?php endforeach; ?>
        <li class="nav-item ms-lg-2 mt-2 mt-lg-0"><a class="btn btn-sm btn-gold px-3" href="<?= e(url('admin/')) ?>"><i class="bi bi-person-lock" aria-hidden="true"></i> เจ้าหน้าที่</a></li>
      </ul>
    </div>
  </div>
</nav>
<main id="main">
<?php
}

function public_footer(): void
{
    $college = setting('college_name', 'วิทยาลัยพณิชยการบางนา');
    $opts    = $GLOBALS['_public_opts'] ?? [];
    ?>
</main>
<footer class="site-footer mt-5">
  <div class="container py-5">
    <div class="row g-4">
      <div class="col-md-5">
        <div class="d-flex align-items-center gap-2 mb-3"><span class="logo-slot" aria-hidden="true"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
          <h2 class="h5 text-gold mb-0"><?= e($college) ?></h2></div>
        <p class="small mb-2"><span class="f-ic" aria-hidden="true"><i class="bi bi-geo-alt" aria-hidden="true"></i></span><span class="visually-hidden">ที่อยู่: </span><?= e(setting('contact_address')) ?></p>
        <p class="small mb-2"><span class="f-ic" aria-hidden="true"><i class="bi bi-telephone" aria-hidden="true"></i></span><span class="visually-hidden">โทรศัพท์: </span><?= e(setting('contact_phone')) ?></p>
        <p class="small mb-0"><span class="f-ic" aria-hidden="true"><i class="bi bi-envelope" aria-hidden="true"></i></span><span class="visually-hidden">อีเมล: </span><?= e(setting('contact_email')) ?></p>
      </div>
      <div class="col-6 col-md-3">
        <h2 class="h6 text-gold">เมนู</h2>
        <ul class="list-unstyled small mb-0 d-grid gap-2">
          <li><a href="<?= e(route('mou')) ?>"><i class="bi bi-chevron-right small" aria-hidden="true"></i> สืบค้น MOU</a></li>
          <li><a href="<?= e(route('news')) ?>"><i class="bi bi-chevron-right small" aria-hidden="true"></i> ข่าวประชาสัมพันธ์</a></li>
          <li><a href="<?= e(route('contact')) ?>"><i class="bi bi-chevron-right small" aria-hidden="true"></i> ติดต่อ / เสนอความร่วมมือ</a></li>
        </ul>
      </div>
      <div class="col-6 col-md-4">
        <h2 class="h6 text-gold">เวลาทำการ</h2>
        <p class="small"><?= e(setting('office_hours')) ?></p>
        <p class="small mb-0"><i class="bi bi-shield-check" aria-hidden="true"></i> ข้อมูลที่เผยแพร่เป็นข้อมูลสาธารณะเท่านั้น ไม่เปิดเผยข้อมูลส่วนบุคคลตาม พ.ร.บ.คุ้มครองข้อมูลส่วนบุคคล (PDPA)</p>
      </div>
    </div>
    <hr class="border-light opacity-25 my-4">
    <div class="small text-center">© <?= (int) date('Y') + 543 ?> <?= e($college) ?> — สงวนลิขสิทธิ์</div>
  </div>
</footer>
<?= ui_scripts(false, !empty($opts['datatables'])) ?>
<?= flash_script() ?>
</body>
</html>
<?php
}

/** การ์ด MOU ใช้ซ้ำในหน้าแรก/รายการ (มีภาพปกถ้าอัปโหลดรูปไว้) */
function mou_card(array $m): string
{
    $cover = $m['cover_image'] ?? null;
    $imgs  = (int) ($m['image_count'] ?? 0);
    ob_start(); ?>
<article class="card h-100 mou-card">
  <div class="card-cover">
    <?php if ($cover): ?><img src="<?= e(media_url('mou_images', $cover)) ?>" alt="" loading="lazy"><?php else: ?><i class="bi bi-file-earmark-text" aria-hidden="true"></i><?php endif; ?>
    <div class="cover-badges"><span class="chip chip-glass"><?= e(partner_type_label($m['partner_type'])) ?></span><?= status_badge($m['status']) ?></div>
    <?php if ($imgs > 1): ?><span class="img-count"><i class="bi bi-images" aria-hidden="true"></i> <?= $imgs ?> รูป</span><?php endif; ?>
  </div>
  <div class="card-body">
    <h3 class="card-title h6 mb-2"><a class="stretched-link text-decoration-none" href="<?= e(route('mou_detail', $m['id'])) ?>"><?= e($m['title']) ?></a></h3>
    <div class="meta mb-1"><i class="bi bi-building" aria-hidden="true"></i> <span class="text-truncate"><?= e($m['partner_name']) ?></span></div>
    <div class="meta mb-1"><i class="bi bi-hash" aria-hidden="true"></i> <span class="visually-hidden">เลขที่ </span><?= e($m['mou_no']) ?></div>
    <div class="meta"><i class="bi bi-calendar-check" aria-hidden="true"></i> ลงนาม <?= e(thai_date($m['sign_date'], true)) ?></div>
    <div class="card-foot d-flex justify-content-between align-items-center">
      <span class="text-muted"><i class="bi bi-hourglass-split" aria-hidden="true"></i> <?= $m['status'] === 'expired' ? 'หมดอายุ ' . e(thai_date($m['end_date'], true)) : e(remaining_text($m['end_date'])) ?></span>
      <i class="bi bi-arrow-right-circle text-gold" aria-hidden="true"></i>
    </div>
  </div>
</article>
<?php
    return ob_get_clean();
}
