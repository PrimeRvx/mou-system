<?php
/** public/news_detail.php — หน้าอ่านข่าว */
require_once __DIR__ . '/../includes/layout_public.php';

$id = (int) get('id');
$n  = db_one("SELECT n.*, u.full_name FROM news n LEFT JOIN users u ON u.id = n.author_id
               WHERE n.id = ? AND n.deleted_at IS NULL AND n.is_published = 1 AND n.published_at <= NOW()", [$id]);
if (!$n) {
    http_response_code(404);
    public_header('ไม่พบข่าว', '', ['active' => 'news']);
    echo '<div class="container my-5"><div class="surface p-5 text-center scale-in"><i class="bi bi-newspaper display-4 text-muted" aria-hidden="true"></i><h2 class="mt-3">ไม่พบข่าวที่ต้องการ</h2><a class="btn btn-navy mt-3" href="' . e(route('news')) . '"><i class="bi bi-arrow-left" aria-hidden="true"></i> กลับหน้าข่าว</a></div></div>';
    public_footer();
    exit;
}
// นับผู้อ่าน (นับครั้งเดียวต่อเซสชัน)
if (empty($_SESSION['news_read'][$id])) {
    db_exec('UPDATE news SET view_count = view_count + 1 WHERE id = ?', [$id]);
    $_SESSION['news_read'][$id] = true;
    $n['view_count']++;
}
// อัลบั้มรูป + ภาพปก (ถ้าไม่ได้ตั้งภาพปก ใช้รูปแรกในอัลบั้มเป็นภาพหัวข่าว)
$images = news_images_of($id);
$hero   = $n['cover_image'] ?: ($images[0]['stored_name'] ?? null);
$others = db_all("SELECT id, title, published_at FROM news WHERE is_published = 1 AND deleted_at IS NULL
                   AND published_at <= NOW() AND id <> ? ORDER BY published_at DESC LIMIT 5", [$id]);

public_header($n['title'], (string) ($n['excerpt'] ?: excerpt((string) $n['content'], 160)), [
    'active' => 'news', 'og_image' => $hero ? url('uploads/news/' . $hero) : '']);
?>
<div class="container mt-4">
  <nav aria-label="breadcrumb" class="fade-down"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= e(route('home')) ?>"><i class="bi bi-house" aria-hidden="true"></i> หน้าหลัก</a></li>
    <li class="breadcrumb-item"><a href="<?= e(route('news')) ?>">ข่าวประชาสัมพันธ์</a></li>
    <li class="breadcrumb-item active text-truncate" style="max-width:40ch" aria-current="page"><?= e($n['title']) ?></li></ol></nav>
  <div class="row g-4">
    <div class="col-lg-8">
      <article class="detail-box p-md-5 fade-up">
        <h1 class="h2 text-heading mb-3"><?= e($n['title']) ?></h1>
        <div class="d-flex flex-wrap gap-2 mb-4">
          <span class="chip"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= e(thai_date($n['published_at'])) ?></span>
          <span class="chip"><i class="bi bi-eye" aria-hidden="true"></i> อ่าน <?= number_format((int) $n['view_count']) ?> ครั้ง</span>
          <?php if ($n['full_name']): ?><span class="chip"><i class="bi bi-person" aria-hidden="true"></i> <?= e($n['full_name']) ?></span><?php endif; ?>
        </div>
        <?php if ($n['cover_image']): ?>
          <a href="<?= e(url('uploads/news/' . $n['cover_image'])) ?>" data-lightbox="news" data-caption="<?= e($n['title']) ?>" class="d-block mb-4 overflow-hidden rounded-4 shadow-soft"><img class="img-fluid w-100 scale-in" src="<?= e(url('uploads/news/' . $n['cover_image'])) ?>" alt="ภาพปกข่าว: <?= e($n['title']) ?>"></a>
        <?php elseif ($hero): ?>
          <!-- ไม่มีภาพปก: ใช้รูปแรกในอัลบั้ม คลิกแล้วเปิดอัลบั้มจากรูปแรก -->
          <a href="<?= e(url('uploads/news/' . $hero)) ?>" data-lightbox-open="news" class="d-block mb-4 overflow-hidden rounded-4 shadow-soft"><img class="img-fluid w-100 scale-in" src="<?= e(url('uploads/news/' . $hero)) ?>" alt="<?= e($images[0]['caption'] ?: 'ภาพประกอบข่าว: ' . $n['title']) ?>"><span class="visually-hidden"> (เปิดอัลบั้มภาพ)</span></a>
        <?php endif; ?>
        <div class="news-body"><?= render_content((string) $n['content']) ?></div>
        <?php if ($images): ?>
        <!-- อัลบั้มรูปภาพประกอบข่าว -->
        <section class="mt-4" id="gallery" aria-labelledby="galleryTitle">
          <h2 class="box-title h5" id="galleryTitle"><i class="bi bi-images" aria-hidden="true"></i> ภาพประกอบ <span class="chip ms-auto"><?= count($images) ?> รูป</span></h2>
          <div class="gallery-grid lg stagger scale">
            <?php foreach ($images as $k => $img): ?>
            <a class="g-item" href="<?= e(url('uploads/news/' . $img['stored_name'])) ?>" data-lightbox="news" data-caption="<?= e($img['caption'] ?? '') ?>">
              <img src="<?= e(url('uploads/news/' . $img['stored_name'])) ?>" alt="<?= e($img['caption'] ?: 'ภาพประกอบที่ ' . ($k + 1)) ?>" loading="lazy"<?= $img['width'] ? ' width="' . (int) $img['width'] . '" height="' . (int) $img['height'] . '"' : '' ?>>
              <?php if ($img['caption']): ?><span class="g-cap"><?= e($img['caption']) ?></span><?php endif; ?>
            </a>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>
        <hr class="my-4">
        <div class="no-print d-flex gap-2"><button type="button" class="btn btn-outline-primary btn-sm js-share"><i class="bi bi-share" aria-hidden="true"></i> แชร์</button>
          <a class="btn btn-outline-secondary btn-sm" href="<?= e(route('news')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> กลับหน้าข่าว</a></div>
      </article>
    </div>
    <aside class="col-lg-4">
      <div class="detail-box fade-up delay-2"><h2 class="box-title"><i class="bi bi-collection" aria-hidden="true"></i> ข่าวอื่นๆ</h2>
        <div class="stagger">
        <?php foreach ($others as $o): ?>
          <a class="d-block p-2 rounded-3 text-decoration-none hover-lift mb-1" href="<?= e(route('news_detail', $o['id'])) ?>"><span class="d-block fw-medium"><?= e($o['title']) ?></span>
            <small class="text-muted"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= e(thai_date($o['published_at'], true)) ?></small></a>
        <?php endforeach; ?></div>
        <?php if (!$others): ?><div class="small text-muted">ยังไม่มีข่าวอื่น</div><?php endif; ?></div>
    </aside>
  </div>
</div>
<?php public_footer();
