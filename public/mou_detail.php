<?php
/** public/mou_detail.php — หน้ารายละเอียดข้อตกลง (2.3) */
require_once __DIR__ . '/../includes/layout_public.php';

$id = (int) get('id');
refresh_mou_status();
$m = mou_get($id, true);
$preview = false;
// เจ้าหน้าที่ที่ login อยู่ดูตัวอย่างฉบับร่าง/ไม่เผยแพร่ได้
if (!$m && session_is_valid() && can('mou')) {
    $m = mou_get($id, false);
    $preview = (bool) $m;
}
if (!$m) {
    http_response_code(404);
    public_header('ไม่พบข้อมูล', '', ['active' => 'mou']);
    echo '<div class="container my-5"><div class="surface p-5 text-center scale-in"><i class="bi bi-file-earmark-x display-4 text-muted" aria-hidden="true"></i><h2 class="mt-3">ไม่พบข้อตกลงที่ต้องการ</h2><p class="text-muted">ข้อมูลอาจถูกลบหรือไม่ได้เผยแพร่</p><a class="btn btn-navy" href="' . e(route('mou')) . '"><i class="bi bi-arrow-left" aria-hidden="true"></i> กลับไปสืบค้น</a></div></div>';
    public_footer();
    exit;
}
$programs   = mou_programs_of($id);
$cats       = mou_categories_of($id);
$benefits   = mou_benefits_of($id);
$activities = mou_activities_of($id);
// ไฟล์: MOU ต้องเผยแพร่ + ไฟล์ตั้งเป็นสาธารณะ (พรีวิวโดยเจ้าหน้าที่เห็นทั้งหมด)
$files      = mou_files_of($id, !$preview);
$days       = days_remaining($m['end_date']);
$images     = mou_images_of($id);                 // รูปภาพประกอบรายละเอียด (v2)
$cover      = $images[0] ?? null;                 // ภาพปกเรียงมาก่อนเสมอ
// ความคืบหน้าของระยะเวลาข้อตกลง (%)
$totalDays  = max(1, (int) ((strtotime($m['end_date']) - strtotime($m['start_date'])) / 86400));
$passed     = min(100, max(0, (int) round((time() - strtotime($m['start_date'])) / 86400 / $totalDays * 100)));

public_header($m['title'], excerpt((string) ($m['summary'] ?: $m['objective']), 160), ['active' => 'mou',
    'og_image' => $cover ? media_url('mou_images', $cover['stored_name']) : null]);
?>
<div class="container mt-4">
  <nav aria-label="breadcrumb" class="no-print fade-down"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= e(route('home')) ?>"><i class="bi bi-house" aria-hidden="true"></i> หน้าหลัก</a></li>
    <li class="breadcrumb-item"><a href="<?= e(route('mou')) ?>">สืบค้น MOU</a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= e($m['mou_no']) ?></li></ol></nav>
  <?php if ($preview): ?><div class="alert alert-warning no-print"><i class="bi bi-eye" aria-hidden="true"></i> โหมดตัวอย่างสำหรับเจ้าหน้าที่ — ข้อตกลงนี้ยังไม่เผยแพร่สู่สาธารณะ</div><?php endif; ?>

  <header class="d-flex flex-wrap justify-content-between gap-3 mb-4 fade-up">
    <div class="min-w-0" style="max-width:820px">
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <?= status_badge($m['status']) ?> <span class="chip"><?= e(partner_type_label($m['partner_type'])) ?></span>
        <?php foreach ($cats as $c): ?><span class="chip chip-gold"><?= e($c['name']) ?></span><?php endforeach; ?>
      </div>
      <h1 class="h2 mt-3 mb-2 text-heading"><?= e($m['title']) ?></h1>
      <div class="text-muted"><span class="visually-hidden">เลขที่ </span><i class="bi bi-hash" aria-hidden="true"></i><?= e($m['mou_no']) ?> <span class="mx-2" aria-hidden="true">·</span> <span class="visually-hidden">หน่วยงาน </span><i class="bi bi-building" aria-hidden="true"></i> <?= e($m['partner_name']) ?></div>
    </div>
    <div class="no-print d-flex gap-2 align-items-start">
      <button type="button" class="btn btn-outline-secondary js-print"><i class="bi bi-printer" aria-hidden="true"></i> พิมพ์</button>
      <button type="button" class="btn btn-outline-primary js-share"><i class="bi bi-share" aria-hidden="true"></i> แชร์ลิงก์</button>
    </div>
  </header>

  <div class="row g-4">
    <div class="col-lg-8">
      <?php if ($cover): ?>
      <!-- ภาพปก (คลิกเพื่อดูภาพขยาย) -->
      <a class="mou-cover d-block scale-in" href="<?= e(media_url('mou_images', $cover['stored_name'])) ?>" <?= count($images) > 1 ? 'data-lightbox-open="mou"' : 'data-lightbox="mou"' ?> data-caption="<?= e($cover['caption'] ?? '') ?>">
        <img src="<?= e(media_url('mou_images', $cover['stored_name'])) ?>" alt="<?= e($cover['caption'] ?: $m['title']) ?>">
        <?php if ($cover['caption'] || count($images) > 1): ?><div class="cover-cap d-flex justify-content-between gap-2"><span><?= e($cover['caption'] ?? '') ?></span>
          <?php if (count($images) > 1): ?><span class="text-nowrap"><i class="bi bi-images" aria-hidden="true"></i> ทั้งหมด <?= count($images) ?> รูป</span><?php endif; ?></div><?php endif; ?>
      </a>
      <?php endif; ?>

      <?php if ($m['summary']): ?><section class="detail-box reveal"><h2 class="box-title"><i class="bi bi-card-text" aria-hidden="true"></i> สาระสำคัญ</h2><div><?= nl2br(e($m['summary'])) ?></div></section><?php endif; ?>
      <?php if ($m['objective']): ?><section class="detail-box reveal"><h2 class="box-title"><i class="bi bi-bullseye" aria-hidden="true"></i> วัตถุประสงค์</h2><div><?= nl2br(e($m['objective'])) ?></div></section><?php endif; ?>
      <?php if ($m['scope']): ?><section class="detail-box reveal"><h2 class="box-title"><i class="bi bi-diagram-3" aria-hidden="true"></i> ขอบเขตความร่วมมือ</h2><div><?= nl2br(e($m['scope'])) ?></div></section><?php endif; ?>

      <?php if (count($images) > 1): ?>
      <!-- อัลบั้มภาพประกอบ -->
      <section class="detail-box reveal" id="gallery"><h2 class="box-title"><i class="bi bi-images" aria-hidden="true"></i> ภาพประกอบ <span class="chip ms-auto"><?= count($images) ?> รูป</span></h2>
        <div class="gallery-grid stagger scale">
          <?php foreach ($images as $k => $img): ?>
          <a class="g-item <?= $k === 0 && count($images) >= 5 ? 'g-feature' : '' ?>" href="<?= e(media_url('mou_images', $img['stored_name'])) ?>" data-lightbox="mou" data-caption="<?= e($img['caption'] ?? '') ?>">
            <img src="<?= e(media_url('mou_images', $img['stored_name'])) ?>" alt="<?= e($img['caption'] ?: 'ภาพประกอบ ' . ($k + 1)) ?>" loading="lazy">
            <?php if ($img['caption']): ?><span class="g-cap"><?= e($img['caption']) ?></span><?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <?php if ($benefits): ?><section class="detail-box reveal"><h2 class="box-title"><i class="bi bi-award" aria-hidden="true"></i> สิทธิประโยชน์ของนักศึกษา</h2>
        <ul class="benefit-list"><?php foreach ($benefits as $b): ?><li><?= e($b['item']) ?></li><?php endforeach; ?></ul></section><?php endif; ?>
      <?php if ($programs): ?><section class="detail-box reveal"><h2 class="box-title"><i class="bi bi-mortarboard" aria-hidden="true"></i> สาขาวิชาที่เกี่ยวข้อง</h2>
        <div class="d-flex flex-wrap gap-2"><?php foreach ($programs as $p): ?><span class="chip chip-primary"><i class="bi bi-bookmark" aria-hidden="true"></i> <?= e($p['level'] . ' ' . $p['name']) ?></span><?php endforeach; ?></div></section><?php endif; ?>
      <?php if ($activities): ?><section class="detail-box reveal" id="activities"><h2 class="box-title"><i class="bi bi-calendar-event" aria-hidden="true"></i> กิจกรรมที่ดำเนินการภายใต้ MOU</h2>
        <?php foreach ($activities as $a): $ad = to_date($a['activity_date']); ?>
          <div class="activity-item">
            <?php if (!empty($a['image'])): ?>
              <a href="<?= e(media_url('activities', $a['image'])) ?>" data-lightbox="act" data-caption="<?= e($a['title']) ?>"><img class="act-thumb" src="<?= e(media_url('activities', $a['image'])) ?>" alt="<?= e($a['title']) ?>" loading="lazy"></a>
            <?php else: ?>
              <div class="act-date"><b><?= $ad ? (int) $ad->format('j') : '-' ?></b><small><?= $ad ? e(TH_MONTHS_SHORT[(int) $ad->format('n')] . ' ' . thai_year($a['activity_date'])) : '' ?></small></div>
            <?php endif; ?>
            <div class="min-w-0"><strong class="d-block"><?= e($a['title']) ?></strong>
              <div class="small text-muted"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= e(thai_date($a['activity_date'])) ?> · <i class="bi bi-people" aria-hidden="true"></i> ผู้เข้าร่วม <?= number_format((int) $a['participants']) ?> คน</div>
              <?php if ($a['result']): ?><div class="small mt-1"><?= e($a['result']) ?></div><?php endif; ?></div>
          </div>
        <?php endforeach; ?></section><?php endif; ?>
    </div>

    <aside class="col-lg-4">
      <div class="position-sticky" style="top:90px">
      <div class="countdown mb-4 scale-in delay-1">
        <div class="shapes" aria-hidden="true"><span class="s2" style="top:-30px;right:-30px"></span><span class="s5" style="top:65%;left:8%"></span></div>
        <div class="small note">ระยะเวลาความร่วมมือคงเหลือ</div>
        <div class="big" data-count="<?= $days !== null && $days >= 0 ? (int) $days : 0 ?>"><?= $days !== null && $days >= 0 ? number_format($days) : '0' ?></div>
        <div>วัน</div>
        <div class="progress mt-3" role="progressbar" aria-label="ระยะเวลาที่ผ่านไป" aria-valuenow="<?= $passed ?>" aria-valuemin="0" aria-valuemax="100" aria-valuetext="ผ่านไปแล้ว <?= $passed ?>%"><div class="progress-bar" style="width:<?= $passed ?>%"></div></div>
        <div class="d-flex justify-content-between small mt-1 note"><span><?= e(thai_date($m['start_date'], true)) ?></span><span><?= e(thai_date($m['end_date'], true)) ?></span></div>
        <div class="small mt-2 note"><?= e(remaining_text($m['end_date'])) ?></div>
      </div>
      <div class="detail-box fade-up delay-2">
        <h2 class="box-title"><i class="bi bi-info-circle" aria-hidden="true"></i> ข้อมูลทั่วไป</h2>
        <dl class="row small mb-0 kv">
          <dt class="col-5">วันที่ลงนาม</dt><dd class="col-7"><?= e(thai_date($m['sign_date'])) ?></dd>
          <dt class="col-5">วันที่เริ่ม</dt><dd class="col-7"><?= e(thai_date($m['start_date'])) ?></dd>
          <dt class="col-5">วันที่สิ้นสุด</dt><dd class="col-7"><?= e(thai_date($m['end_date'])) ?></dd>
          <?php if ($m['duration_years']): ?><dt class="col-5">ระยะเวลา</dt><dd class="col-7"><?= e(rtrim(rtrim((string) $m['duration_years'], '0'), '.')) ?> ปี</dd><?php endif; ?>
          <dt class="col-5">นักศึกษาที่ได้รับประโยชน์</dt><dd class="col-7"><?= number_format((int) $m['student_benefit_count']) ?> คน</dd>
          <?php if ($m['signer_college']): ?><dt class="col-5">ผู้ลงนามฝ่ายวิทยาลัย</dt><dd class="col-7"><?= e($m['signer_college']) ?></dd><?php endif; ?>
          <?php if ($m['signer_partner']): ?><dt class="col-5">ผู้ลงนามฝ่ายคู่ความร่วมมือ</dt><dd class="col-7"><?= e($m['signer_partner']) ?></dd><?php endif; ?>
        </dl>
      </div>
      <div class="detail-box fade-up delay-3">
        <h2 class="box-title"><i class="bi bi-building" aria-hidden="true"></i> หน่วยงานคู่ความร่วมมือ</h2>
        <div class="d-flex gap-3 align-items-center">
          <?php if ($m['logo']): ?><img src="<?= e(url('uploads/logos/' . $m['logo'])) ?>" alt="" style="width:52px;height:52px;object-fit:contain" class="rounded-circle bg-white border"><?php else: ?>
          <span class="partner-chip p-0 border-0 bg-transparent"><span class="ini" style="width:52px;height:52px;font-size:1.2rem"><?= e(initial_of($m['partner_name'])) ?></span></span><?php endif; ?>
          <div><div class="fw-semibold"><?= e($m['partner_name']) ?></div>
          <div class="small text-muted"><?= e(partner_type_label($m['partner_type'])) ?><?= $m['province'] ? ' · ' . e($m['province']) : '' ?></div></div>
        </div>
        <?php if ($m['partner_address']): ?><div class="small mt-3"><i class="bi bi-geo-alt text-gold" aria-hidden="true"></i><span class="visually-hidden">ที่อยู่: </span> <?= e($m['partner_address']) ?></div><?php endif; ?>
        <?php if ($m['website'] && preg_match('#^https?://#i', $m['website'])): ?><div class="small mt-1"><i class="bi bi-globe text-gold" aria-hidden="true"></i> <a href="<?= e($m['website']) ?>" target="_blank" rel="noopener noreferrer">เว็บไซต์ <?= e($m['partner_name']) ?><span class="visually-hidden"> (เปิดแท็บใหม่)</span></a></div><?php endif; ?>
        <?php // ไม่แสดงผู้ติดต่อ/เบอร์/อีเมล/งบประมาณ — ข้อมูลภายใน (PDPA) ?>
      </div>
      <div class="detail-box no-print fade-up delay-4">
        <h2 class="box-title"><i class="bi bi-paperclip" aria-hidden="true"></i> เอกสารดาวน์โหลด</h2>
        <?php if (!$files): ?><div class="small text-muted">ไม่มีเอกสารเผยแพร่สาธารณะ</div><?php endif; ?>
        <?php foreach ($files as $f): ?>
          <a class="list-file d-flex justify-content-between align-items-center border rounded-3 p-2 mb-2 text-decoration-none hover-lift" href="<?= e(route('download', $f['id'])) ?>">
            <span class="d-flex gap-2 align-items-center min-w-0"><i class="bi bi-file-earmark-arrow-down fs-4 text-danger" aria-hidden="true"></i>
              <span class="min-w-0"><span class="d-block text-truncate"><?= e($f['original_name']) ?></span>
              <?php if (!$f['is_public']): ?><span class="badge bg-secondary">เฉพาะผู้ดูแล</span><?php endif; ?>
              <small class="text-muted"><?= number_format($f['size'] / 1024, 0) ?> KB</small></span></span>
            <span class="chip"><i class="bi bi-download" aria-hidden="true"></i> <?= number_format((int) $f['download_count']) ?><span class="visually-hidden"> ครั้งที่ดาวน์โหลด</span></span>
          </a>
        <?php endforeach; ?>
      </div>
      </div>
    </aside>
  </div>
</div>
<?php public_footer();
