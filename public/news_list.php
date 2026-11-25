<?php
/** public/news_list.php — รายการข่าวทั้งหมด */
require_once __DIR__ . '/../includes/layout_public.php';

$per   = 9;
$total = (int) db_val("SELECT COUNT(*) FROM news WHERE is_published = 1 AND deleted_at IS NULL AND published_at <= NOW()");
$pg    = paginate($total, $per, (int) get('page', '1'));
$rows  = db_all("SELECT n.id, n.title, n.excerpt, " . news_cover_sql() . ", n.published_at FROM news n
                  WHERE n.is_published = 1 AND n.deleted_at IS NULL AND n.published_at <= NOW()
                  ORDER BY n.published_at DESC LIMIT $per OFFSET " . (int) $pg['offset']);

public_header('ข่าวประชาสัมพันธ์', 'ข่าวประชาสัมพันธ์และกิจกรรมความร่วมมือของวิทยาลัยพณิชยการบางนา', ['active' => 'news']);
?>
<div class="container mt-4">
  <nav aria-label="breadcrumb" class="fade-down"><ol class="breadcrumb mb-2">
    <li class="breadcrumb-item"><a href="<?= e(route('home')) ?>"><i class="bi bi-house" aria-hidden="true"></i> หน้าหลัก</a></li>
    <li class="breadcrumb-item active" aria-current="page">ข่าวประชาสัมพันธ์</li></ol></nav>
  <div class="section-kicker">ข่าวและกิจกรรม</div>
  <h1 class="section-title h3 fade-up">ข่าวประชาสัมพันธ์และกิจกรรมความร่วมมือ</h1>
  <div class="row g-4 stagger">
    <?php foreach ($rows as $n): ?>
    <div class="col-md-6 col-lg-4"><article class="card news-card h-100">
      <div class="cover"><?php if ($n['cover_image']): ?><img src="<?= e(url('uploads/news/' . $n['cover_image'])) ?>" alt="" loading="lazy"><?php else: ?><i class="bi bi-newspaper" aria-hidden="true"></i><?php endif; ?>
        <span class="chip chip-glass date-pill"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= e(thai_date($n['published_at'], true)) ?></span>
        <?php if ((int) $n['image_count'] > 0): ?><span class="img-count"><i class="bi bi-images" aria-hidden="true"></i> <?= (int) $n['image_count'] ?> รูป</span><?php endif; ?></div>
      <div class="card-body">
        <h2 class="h6"><a class="stretched-link" href="<?= e(route('news_detail', $n['id'])) ?>"><?= e($n['title']) ?></a></h2>
        <p class="small text-muted mb-0"><?= e(excerpt((string) $n['excerpt'], 100)) ?></p>
      </div></article></div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="col-12"><div class="surface"><?= empty_state('ยังไม่มีข่าว', 'bi-newspaper') ?></div></div><?php endif; ?>
  </div>
  <?php if ($pg['pages'] > 1): ?>
  <nav class="mt-5" aria-label="เลือกหน้าข่าว"><ul class="pagination justify-content-center">
    <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
      <li class="page-item <?= $i === $pg['page'] ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>" <?= $i === $pg['page'] ? 'aria-current="page"' : '' ?>><span class="visually-hidden">หน้า </span><?= $i ?></a></li>
    <?php endfor; ?></ul></nav>
  <?php endif; ?>
</div>
<?php public_footer();
