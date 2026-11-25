<?php
/** public/index.php — หน้าหลัก (2.1) */
require_once __DIR__ . '/../includes/layout_public.php';

$stats   = public_stats();
$news    = db_all("SELECT n.id, n.title, n.excerpt, " . news_cover_sql() . ", n.published_at FROM news n
                    WHERE n.is_published = 1 AND n.deleted_at IS NULL AND n.published_at <= NOW()
                    ORDER BY n.published_at DESC LIMIT 6");
$latest  = mou_search([], true, 6);
$partners = db_all("SELECT DISTINCT p.id, p.name, p.logo FROM partners p JOIN mou m ON m.partner_id = p.id
                     WHERE " . PUBLIC_MOU_WHERE . " ORDER BY p.name");

public_header('', 'ค้นหาข้อมูลบันทึกข้อตกลงความร่วมมือ (MOU) ของวิทยาลัยพณิชยการบางนากับสถานประกอบการ สถาบันการศึกษา และหน่วยงานรัฐ', ['active' => 'home']);
?>
<section class="hero">
  <div class="shapes" aria-hidden="true"><span class="s1"></span><span class="s2"></span><span class="s3"></span><span class="s4"></span><span class="s5"></span><span class="s6"></span></div>
  <div class="container text-center position-relative">
    <span class="hero-badge fade-down"><i class="bi bi-patch-check-fill text-gold" aria-hidden="true"></i> ข้อมูลความร่วมมืออย่างเป็นทางการ</span>
    <h1 class="display-5 mt-3 fade-up">ฐานข้อมูลบันทึกข้อตกลง<br class="d-none d-md-block"><span class="hl">ความร่วมมือ (MOU)</span></h1>
    <p class="lead mb-4 fade-up delay-1"><?= e(setting('college_name')) ?> — เชื่อมโยงการเรียนรู้กับโลกการทำงานจริง</p>
    <form class="hero-search mx-auto d-flex gap-2 align-items-center fade-up delay-2" action="<?= e(route('mou')) ?>" method="get" role="search" data-no-loader>
      <i class="bi bi-search ms-2 d-none d-sm-inline" aria-hidden="true"></i>
      <label class="visually-hidden" for="heroQ">ค้นหา MOU</label>
      <input type="search" id="heroQ" name="q" class="form-control" maxlength="100" placeholder="ค้นหาชื่อหน่วยงาน / เลขที่ MOU / สาขาวิชา" autocomplete="off">
      <button class="btn btn-gold px-4 text-nowrap" type="submit"><i class="bi bi-search" aria-hidden="true"></i> ค้นหา</button>
    </form>
    <div class="d-flex flex-wrap justify-content-center gap-2 mt-3 fade-up delay-3" role="group" aria-label="ค้นหาตามประเภทหน่วยงาน">
      <?php foreach (partner_types() as $k => $l): ?><a class="chip chip-glass text-decoration-none" href="<?= e(route('mou')) ?>?type=<?= e($k) ?>"><?= e($l) ?></a><?php endforeach; ?>
    </div>
  </div>
  <svg class="hero-wave" viewBox="0 0 1440 70" preserveAspectRatio="none" aria-hidden="true"><path fill="currentColor" d="M0,40 C240,80 480,0 720,24 C960,48 1200,70 1440,30 L1440,70 L0,70 Z"/></svg>
</section>

<div class="container stat-wrap">
  <div class="row g-3 stagger scale">
    <?php foreach ([
      ['bi-file-earmark-text', $stats['total'], 'MOU ทั้งหมด'],
      ['bi-patch-check', $stats['active'], 'มีผลบังคับใช้'],
      ['bi-buildings', $stats['enterprises'], 'สถานประกอบการ'],
      ['bi-bank', $stats['schools'], 'สถาบันการศึกษา'],
      ['bi-people', $stats['students'], 'นักศึกษาที่ได้รับประโยชน์'],
    ] as $s): ?>
    <div class="col-6 col-lg"><div class="stat-card"><div class="stat-ic" aria-hidden="true"><i class="bi <?= e($s[0]) ?>" aria-hidden="true"></i></div>
      <div class="num" data-count="<?= (int) $s[1] ?>"><?= number_format((int) $s[1]) ?></div><div class="text-muted small"><?= e($s[2]) ?></div></div></div>
    <?php endforeach; ?>
  </div>
</div>

<section class="container mt-5 pt-2 reveal">
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><div class="section-kicker">ข้อตกลงล่าสุด</div><h2 class="section-title h3">MOU ที่ลงนามล่าสุด</h2></div>
    <a href="<?= e(route('mou')) ?>" class="btn btn-sm btn-outline-primary mb-3">ดู MOU ทั้งหมด <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
  </div>
  <div class="row g-4 stagger">
    <?php foreach ($latest as $m): ?><div class="col-md-6 col-lg-4"><?= mou_card($m) ?></div><?php endforeach; ?>
    <?php if (!$latest): ?><div class="col-12"><?= empty_state('ยังไม่มีข้อมูล MOU ที่เผยแพร่', 'bi-file-earmark') ?></div><?php endif; ?>
  </div>
</section>

<section class="container mt-5 pt-2 reveal">
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><div class="section-kicker">ข่าวและกิจกรรม</div><h2 class="section-title h3">ข่าวประชาสัมพันธ์และกิจกรรมความร่วมมือ</h2></div>
    <a href="<?= e(route('news')) ?>" class="btn btn-sm btn-outline-primary mb-3">ดูข่าวทั้งหมด <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
  </div>
  <div class="row g-4 stagger">
    <?php foreach ($news as $n): ?>
    <div class="col-md-6 col-lg-4">
      <article class="card news-card h-100">
        <div class="cover"><?php if ($n['cover_image']): ?><img src="<?= e(url('uploads/news/' . $n['cover_image'])) ?>" alt="" loading="lazy"><?php else: ?><i class="bi bi-newspaper" aria-hidden="true"></i><?php endif; ?>
          <span class="chip chip-glass date-pill"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= e(thai_date($n['published_at'], true)) ?></span>
          <?php if ((int) $n['image_count'] > 0): ?><span class="img-count"><i class="bi bi-images" aria-hidden="true"></i> <?= (int) $n['image_count'] ?> รูป</span><?php endif; ?></div>
        <div class="card-body">
          <h3 class="h6"><a class="stretched-link" href="<?= e(route('news_detail', $n['id'])) ?>"><?= e($n['title']) ?></a></h3>
          <p class="small text-muted mb-0"><?= e(excerpt((string) $n['excerpt'], 90)) ?></p>
        </div>
      </article>
    </div>
    <?php endforeach; ?>
    <?php if (!$news): ?><div class="col-12"><?= empty_state('ยังไม่มีข่าว', 'bi-newspaper') ?></div><?php endif; ?>
  </div>
</section>

<?php if ($partners): ?>
<section class="container mt-5 pt-2 reveal">
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><div class="section-kicker">เครือข่ายของเรา</div><h2 class="section-title h3">หน่วยงานคู่ความร่วมมือ</h2></div>
    <button type="button" class="btn btn-sm btn-outline-secondary mb-3 marquee-toggle" data-marquee-toggle aria-pressed="false"><i class="bi bi-pause-fill" aria-hidden="true"></i> <span>หยุดเลื่อน</span></button>
  </div>
  <div class="marquee" id="partnerMarquee"><div class="marquee-track">
    <?php for ($i = 0; $i < 2; $i++): foreach ($partners as $p): ?>
      <div class="partner-chip" <?= $i ? 'aria-hidden="true"' : '' ?>>
        <?php if ($p['logo']): ?><img src="<?= e(url('uploads/logos/' . $p['logo'])) ?>" alt="" loading="lazy">
        <?php else: ?><span class="ini"><?= e(initial_of($p['name'])) ?></span><?php endif; ?>
        <span class="small"><?= e($p['name']) ?></span>
      </div>
    <?php endforeach; endfor; ?>
  </div></div>
</section>
<?php endif; ?>

<section class="container mt-5 reveal">
  <div class="surface p-4 p-md-5 d-flex flex-wrap align-items-center justify-content-between gap-3" style="background:var(--grad-brand-soft)">
    <div><h2 class="h4 text-heading mb-1">สนใจร่วมเป็นเครือข่ายความร่วมมือกับวิทยาลัย?</h2>
      <p class="text-muted mb-0">ส่งข้อเสนอออนไลน์ได้ทันที รับเลขอ้างอิงเพื่อติดตามผล</p></div>
    <a class="btn btn-gold btn-lg" href="<?= e(route('contact')) ?>#proposal"><i class="bi bi-send" aria-hidden="true"></i> เสนอความร่วมมือ</a>
  </div>
</section>
<?php public_footer();
