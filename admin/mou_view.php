<?php
/** admin/mou_view.php — รายละเอียด MOU (หลังบ้าน เห็นข้อมูลภายในทั้งหมด) */
require_once __DIR__ . '/../includes/layout_admin.php';
require_permission('dashboard');   // viewer ดูได้ (อ่านอย่างเดียว) — ตรวจเพิ่มด้านล่าง
$id = (int) get('id');
refresh_mou_status();
$m = mou_get($id, false);
if (!$m) { flash('error', 'ไม่พบข้อมูล MOU'); redirect('admin/mou_list.php'); }
$canWrite = can_write() && can('mou');
// ข้อมูลภายใน (งบประมาณ, หมายเหตุภายใน): viewer เห็นหรือไม่ ขึ้นกับ VIEWER_SEES_INTERNAL ใน config
$seeInternal = can_write() || VIEWER_SEES_INTERNAL;
$files = mou_files_of($id, false); $acts = mou_activities_of($id); $ren = mou_renewals_of($id);
$images = mou_images_of($id);
admin_header('MOU ' . $m['mou_no'], 'mou', 'dashboard');
$hid = fn(string $action, int $rid = 0) => csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="id" value="' . $rid . '"><input type="hidden" name="mou_id" value="' . $id . '">';
?>
<div class="page-head fade-up">
  <div class="min-w-0">
    <div class="d-flex flex-wrap gap-2 mb-1"><?= status_badge($m['status']) ?> <?= $m['is_public'] ? '<span class="chip chip-primary"><i class="bi bi-globe" aria-hidden="true"></i> เผยแพร่</span>' : '<span class="chip"><i class="bi bi-eye-slash" aria-hidden="true"></i> ไม่เผยแพร่</span>' ?>
      <span class="chip"><i class="bi bi-hash" aria-hidden="true"></i><?= e($m['mou_no']) ?></span></div>
    <h2 class="title"><?= e($m['title']) ?></h2>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-outline-secondary" href="mou_list.php"><i class="bi bi-arrow-left" aria-hidden="true"></i> กลับ</a>
    <a class="btn btn-outline-primary" target="_blank" rel="noopener" href="<?= e(route('mou_detail', $id)) ?>"><i class="bi bi-globe" aria-hidden="true"></i> หน้าสาธารณะ<span class="visually-hidden"> (เปิดแท็บใหม่)</span></a>
    <?php if ($canWrite): ?><a class="btn btn-navy" href="mou_form.php?id=<?= $id ?>"><i class="bi bi-pencil" aria-hidden="true"></i> แก้ไข</a>
    <a class="btn btn-gold" href="renewals.php?mou_id=<?= $id ?>"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> ต่ออายุ</a><?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8 stagger">
    <div class="panel"><?= panel_title('bi-info-circle', 'ข้อมูลข้อตกลง') ?>
      <dl class="row mb-0 kv">
        <dt class="col-sm-3">หน่วยงาน</dt><dd class="col-sm-9"><?= e($m['partner_name']) ?> <span class="chip ms-1"><?= e(partner_type_label($m['partner_type'])) ?></span></dd>
        <dt class="col-sm-3">ผู้ติดต่อ</dt><dd class="col-sm-9"><?= e($m['contact_name']) ?> <?= e($m['contact_position']) ?> · <?= e($m['partner_phone']) ?> · <?= e($m['partner_email']) ?></dd>
        <dt class="col-sm-3">ลงนาม</dt><dd class="col-sm-9"><?= e(thai_date($m['sign_date'])) ?></dd>
        <dt class="col-sm-3">ระยะเวลา</dt><dd class="col-sm-9"><?= e(thai_date($m['start_date'])) ?> – <?= e(thai_date($m['end_date'])) ?> <span class="text-muted">(<?= e(remaining_text($m['end_date'])) ?>)</span></dd>
        <dt class="col-sm-3">ผู้ลงนาม</dt><dd class="col-sm-9"><?= e($m['signer_college']) ?> / <?= e($m['signer_partner']) ?></dd>
        <?php if ($seeInternal): ?><dt class="col-sm-3">งบประมาณ (ภายใน)</dt><dd class="col-sm-9"><?= $m['budget'] !== null ? number_format((float) $m['budget'], 2) . ' บาท' : '-' ?></dd><?php endif; ?>
        <dt class="col-sm-3">นักศึกษาที่ได้รับประโยชน์</dt><dd class="col-sm-9"><?= number_format((int) $m['student_benefit_count']) ?> คน</dd>
        <dt class="col-sm-3">ผู้รับผิดชอบ</dt><dd class="col-sm-9"><?= e($m['responsible'] ?: '-') ?></dd>
        <?php if ($seeInternal): ?><dt class="col-sm-3">หมายเหตุภายใน</dt><dd class="col-sm-9"><?= $m['note'] ? nl2br(e($m['note'])) : '-' ?></dd><?php endif; ?>
        <dt class="col-sm-3">สาขา/ประเภท</dt><dd class="col-sm-9 mb-0"><?php foreach (mou_programs_of($id) as $p): ?><span class="chip chip-primary me-1 mb-1"><?= e($p['level'] . ' ' . $p['name']) ?></span><?php endforeach; ?>
          <div class="mt-1"><?php foreach (mou_categories_of($id) as $c): ?><span class="chip chip-gold me-1"><?= e($c['name']) ?></span><?php endforeach; ?></div></dd>
      </dl></div>

    <!-- รูปภาพประกอบรายละเอียด (v2) -->
    <div class="panel" id="gallery"><?= panel_title('bi-images', 'รูปภาพประกอบรายละเอียด', count($images) . ' / ' . MAX_MOU_IMAGES . ' รูป') ?>
      <?php if (!has_mou_images()): ?>
        <div class="alert alert-warning mb-0"><i class="bi bi-database-exclamation" aria-hidden="true"></i> ยังไม่ได้สร้างตารางรูปภาพ — กรุณานำเข้า <code>database/migration_v2_images.sql</code></div>
      <?php else: ?>
        <?php if ($images): ?>
        <form method="post" action="mou_actions.php" id="imgForm" data-dirty-guard><?= $hid('save_images') ?></form>
        <p class="small text-muted mb-2" id="imgHint">แก้คำบรรยาย/ลำดับแล้วกด "บันทึกคำบรรยาย/ลำดับ" ก่อนตั้งภาพปกหรือลบรูป — ถ้าลืม ระบบจะถามก่อนออกจากหน้า</p>
        <div class="img-manage mb-3">
          <?php foreach ($images as $k => $img): ?>
          <div class="img-card">
            <a class="ph" href="<?= e(media_url('mou_images', $img['stored_name'])) ?>" data-lightbox="admin" data-caption="<?= e($img['caption'] ?? '') ?>">
              <img src="<?= e(media_url('mou_images', $img['stored_name'])) ?>" alt="<?= e($img['caption'] ?: 'รูปที่ ' . ($k + 1)) ?>" loading="lazy">
              <?php if ($img['is_cover']): ?><span class="cover-flag badge bg-warning text-dark"><i class="bi bi-star-fill" aria-hidden="true"></i> ภาพปก</span><?php endif; ?></a>
            <div class="body">
              <?php if ($canWrite): ?>
              <label class="visually-hidden" for="cap<?= (int) $img['id'] ?>">คำบรรยายรูปที่ <?= $k + 1 ?></label>
              <input form="imgForm" id="cap<?= (int) $img['id'] ?>" name="caption[<?= (int) $img['id'] ?>]" class="form-control form-control-sm mb-1" maxlength="255" placeholder="คำบรรยายภาพ" value="<?= e($img['caption'] ?? '') ?>">
              <div class="d-flex gap-1 align-items-center">
                <input form="imgForm" type="number" name="sort[<?= (int) $img['id'] ?>]" class="form-control form-control-sm" style="width:64px" value="<?= (int) $img['sort_order'] ?>" title="ลำดับ (น้อยขึ้นก่อน)" aria-label="ลำดับของรูปที่ <?= $k + 1 ?> (เลขน้อยขึ้นก่อน)">
                <?php if (!$img['is_cover']): ?><form method="post" action="mou_actions.php" class="d-inline"><?= $hid('set_cover', (int) $img['id']) ?><button type="submit" class="btn btn-sm btn-outline-secondary btn-icon" title="ตั้งเป็นภาพปก" aria-label="ตั้งรูปที่ <?= $k + 1 ?> เป็นภาพปก" data-bs-toggle="tooltip"><i class="bi bi-star" aria-hidden="true"></i></button></form><?php endif; ?>
                <form method="post" action="mou_actions.php" class="d-inline ms-auto" data-confirm="ลบรูปนี้?"><?= $hid('delete_image', (int) $img['id']) ?><button type="submit" class="btn btn-sm btn-outline-danger btn-icon" title="ลบรูป" aria-label="ลบรูปที่ <?= $k + 1 ?>"><i class="bi bi-trash" aria-hidden="true"></i></button></form>
              </div>
              <?php else: ?><div class="small text-muted text-truncate"><?= e($img['caption'] ?: '—') ?></div><?php endif; ?>
              <div class="text-muted mt-1 small"><?= $img['width'] ? (int) $img['width'] . '×' . (int) $img['height'] . ' · ' : '' ?><?= number_format($img['size'] / 1024) ?> KB</div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if ($canWrite): ?><button form="imgForm" class="btn btn-sm btn-navy mb-3"><i class="bi bi-save" aria-hidden="true"></i> บันทึกคำบรรยาย/ลำดับ</button><?php endif; ?>
        <?php else: ?><?= empty_state('ยังไม่มีรูปภาพประกอบ', 'bi-image') ?><?php endif; ?>

        <?php if ($canWrite && count($images) < MAX_MOU_IMAGES): ?>
        <form method="post" action="mou_actions.php" enctype="multipart/form-data"><?= $hid('upload_images') ?>
          <label class="dropzone d-block" data-dropzone data-max="<?= upload_slots(MAX_MOU_IMAGES - count($images)) ?>" data-max-total-mb="<?= upload_total_mb() ?>" data-max-mb="<?= (int) (MAX_MOU_IMAGE_SIZE / 1048576) ?>" data-previews="#viewPreviews">
            <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required aria-label="เลือกรูปภาพที่จะอัปโหลด (เลือกได้หลายรูป)">
            <span class="dz-ic"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i></span>
            <div class="fw-semibold mt-1">ลากรูปมาวาง หรือคลิกเพื่อเลือกหลายรูป</div>
            <div class="small text-muted">JPG / PNG / WebP ไม่เกิน <?= (int) (MAX_MOU_IMAGE_SIZE / 1048576) ?> MB ต่อรูป</div>
            <div class="small fw-semibold text-gold-strong mt-1" data-dz-count aria-live="polite"></div>
          </label>
          <div class="dz-previews" id="viewPreviews"></div>
          <button class="btn btn-gold mt-3"><i class="bi bi-upload" aria-hidden="true"></i> อัปโหลดรูป</button>
        </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="panel"><?= panel_title('bi-journal-text', 'วัตถุประสงค์ / สาระสำคัญ / ขอบเขต') ?>
      <p><strong class="text-heading">วัตถุประสงค์:</strong> <?= nl2br(e($m['objective'])) ?></p><p><strong class="text-heading">สาระสำคัญ:</strong> <?= nl2br(e($m['summary'])) ?></p><p class="mb-0"><strong class="text-heading">ขอบเขต:</strong> <?= nl2br(e($m['scope'])) ?></p></div>

    <div class="panel" id="activities"><?= panel_title('bi-calendar-event', 'กิจกรรมภายใต้ MOU', count($acts) . ' กิจกรรม') ?>
      <?php foreach ($acts as $a): ?><div class="activity-item">
        <?php if (!empty($a['image'])): ?><a href="<?= e(media_url('activities', $a['image'])) ?>" data-lightbox="acts" data-caption="<?= e($a['title']) ?>"><img class="act-thumb" src="<?= e(media_url('activities', $a['image'])) ?>" alt="รูปกิจกรรม <?= e($a['title']) ?>"></a><?php endif; ?>
        <div class="flex-grow-1 min-w-0"><strong><?= e($a['title']) ?></strong>
          <div class="small text-muted"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= e(thai_date($a['activity_date'])) ?> · <i class="bi bi-people" aria-hidden="true"></i> <?= (int) $a['participants'] ?> คน<?= $a['result'] ? ' · ' . e($a['result']) : '' ?></div></div>
        <?php if ($canWrite): ?><form method="post" action="mou_actions.php" data-confirm="ลบกิจกรรมนี้?"><?= $hid('delete_activity', (int) $a['id']) ?><button type="submit" class="btn btn-sm btn-outline-danger btn-icon" title="ลบกิจกรรม" aria-label="ลบกิจกรรม <?= e($a['title']) ?>"><i class="bi bi-trash" aria-hidden="true"></i></button></form><?php endif; ?></div>
      <?php endforeach; if (!$acts): ?><?= empty_state('ยังไม่มีกิจกรรม', 'bi-calendar-x') ?><?php endif; ?>
      <?php if ($canWrite): ?><form method="post" action="mou_actions.php" enctype="multipart/form-data" class="row g-2 mt-2 p-3 rounded-4" style="background:var(--c-surface-2)"><?= $hid('add_activity') ?>
        <h3 class="col-12 h6 mb-0 text-heading"><i class="bi bi-plus-circle" aria-hidden="true"></i> เพิ่มกิจกรรม</h3>
        <div class="col-md-5"><label class="form-label small mb-1" for="actTitle">ชื่อกิจกรรม <span class="text-danger" aria-hidden="true">*</span></label><input id="actTitle" name="title" class="form-control form-control-sm" required></div>
        <div class="col-md-3"><label class="form-label small mb-1" for="actDate">วันที่จัด <span class="text-danger" aria-hidden="true">*</span></label><input id="actDate" type="date" name="activity_date" class="form-control form-control-sm" required></div>
        <div class="col-md-4"><label class="form-label small mb-1" for="actPeople">จำนวนผู้เข้าร่วม (คน)</label><input id="actPeople" type="number" min="0" inputmode="numeric" name="participants" class="form-control form-control-sm"></div>
        <div class="col-md-7"><label class="form-label small mb-1" for="actResult">ผลลัพธ์</label><input id="actResult" name="result" class="form-control form-control-sm"></div>
        <div class="col-md-5"><label class="form-label small mb-1" for="actImage">รูปกิจกรรม (ไม่บังคับ, 1 รูป)</label><input id="actImage" type="file" name="image" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp"></div>
        <div class="col-12"><button type="submit" class="btn btn-sm btn-navy"><i class="bi bi-plus-lg" aria-hidden="true"></i> เพิ่มกิจกรรม</button></div></form><?php endif; ?></div>
  </div>

  <div class="col-lg-4 stagger" style="--stagger-base:150ms">
    <div class="panel"><?= panel_title('bi-paperclip', 'ไฟล์เอกสาร') ?>
      <?php foreach ($files as $f): ?><div class="border rounded-3 p-2 mb-2"><a class="text-decoration-none" href="<?= e(route('download', $f['id'])) ?>"><i class="bi bi-file-earmark-text text-danger" aria-hidden="true"></i> <?= e($f['original_name']) ?></a>
        <div class="small text-muted"><?= number_format($f['size'] / 1024) ?> KB · ดาวน์โหลด <?= (int) $f['download_count'] ?> ครั้ง ·
          <?= $f['is_public'] ? '<span class="text-success">สาธารณะ</span>' : '<span class="text-secondary">เฉพาะผู้ดูแล</span>' ?></div>
        <?php if ($canWrite): ?><div class="mt-2 d-flex gap-1">
          <form method="post" action="mou_actions.php"><?= $hid('toggle_file', (int) $f['id']) ?><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left-right" aria-hidden="true"></i> สลับสาธารณะ</button></form>
          <form method="post" action="mou_actions.php" data-confirm="ลบไฟล์นี้?"><?= $hid('delete_file', (int) $f['id']) ?><button type="submit" class="btn btn-sm btn-outline-danger btn-icon" title="ลบไฟล์" aria-label="ลบไฟล์ <?= e($f['original_name']) ?>"><i class="bi bi-trash" aria-hidden="true"></i></button></form></div><?php endif; ?></div>
      <?php endforeach; if (!$files): ?><div class="text-muted small mb-2">ยังไม่มีไฟล์</div><?php endif; ?>
      <?php if ($canWrite): ?><form method="post" action="mou_actions.php" enctype="multipart/form-data" class="mt-2"><?= $hid('upload_file') ?>
        <label class="form-label small mb-1" for="mouFile">เพิ่มไฟล์เอกสาร</label>
        <input type="file" id="mouFile" name="file" class="form-control form-control-sm mb-2" accept=".pdf,.doc,.docx,.jpg,.jpeg" required aria-describedby="mouFileHelp">
        <div class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" name="is_public" value="1" id="fpub"><label class="form-check-label small" for="fpub">เผยแพร่ไฟล์สาธารณะ</label></div>
        <div class="form-text mb-2" id="mouFileHelp">PDF/DOC/DOCX/JPG ไม่เกิน 10 MB</div><button class="btn btn-sm btn-navy"><i class="bi bi-upload" aria-hidden="true"></i> อัปโหลด</button></form><?php endif; ?></div>
    <div class="panel"><?= panel_title('bi-clock-history', 'ประวัติการต่ออายุ') ?>
      <?php if ($ren): ?><div class="timeline"><?php foreach ($ren as $k => $r): ?><div class="step" data-n="<?= count($ren) - $k ?>"><strong class="small"><?= e(thai_date($r['old_end_date'], true)) ?> → <?= e(thai_date($r['new_end_date'], true)) ?></strong>
        <div class="small"><?= e($r['doc_no']) ?> · <?= e($r['reason']) ?></div><div class="small text-muted">โดย <?= e($r['full_name']) ?> · <?= e(thai_datetime($r['recorded_at'], true)) ?></div></div>
      <?php endforeach; ?></div><?php else: ?><div class="text-muted small">ยังไม่เคยต่ออายุ</div><?php endif; ?></div>
  </div>
</div>
<?php admin_footer();
