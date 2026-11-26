<?php
/** admin/news.php — CRUD ข่าวประชาสัมพันธ์ (soft delete) + ภาพปก + อัลบั้มรูปภาพประกอบ (news_images) */
require_once __DIR__ . '/../includes/layout_admin.php';
require_permission('news');
$canWrite = can_write();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('news');
    $act = post('action') ?: 'save'; $id = (int) post('id');
    if (!in_array($act, ['save', 'delete', 'toggle'], true)) { http_response_code(400); exit('ไม่รู้จักคำสั่งนี้'); }
    // แก้ไข/ลบ/สลับสถานะ ต้องเป็นข่าวที่มีอยู่และยังไม่ถูกลบ (กันการเพิ่มรูปให้ข่าวที่ลบแล้ว หรือ id ที่ไม่มีอยู่)
    if ($id && !db_val('SELECT id FROM news WHERE id = ? AND deleted_at IS NULL', [$id])) {
        flash('error', 'ไม่พบข่าวนี้ หรือข่าวถูกลบไปแล้ว');
        redirect('admin/news.php');
    }
    if (!$id && $act !== 'save') { flash('error', 'ไม่พบข่าวที่ต้องการ'); redirect('admin/news.php'); }
    if ($act === 'delete') {
        db_exec('UPDATE news SET deleted_at = NOW() WHERE id = ?', [$id]);
        log_activity('news_delete', 'news#' . $id);
        flash('success', 'ลบข่าวเรียบร้อยแล้ว');
        redirect('admin/news.php');
    }
    if ($act === 'toggle') {
        db_exec('UPDATE news SET is_published = 1 - is_published WHERE id = ?', [$id]);
        log_activity('news_toggle', 'news#' . $id);
        flash('success', 'เปลี่ยนสถานะเผยแพร่แล้ว');
        redirect('admin/news.php');
    }
    $title = mb_substr(post('title'), 0, 300);
    $pubTs = post('published_at') !== '' ? strtotime(str_replace('T', ' ', post('published_at'))) : false;
    $pub = date('Y-m-d H:i:s', $pubTs ?: time());
    // เก็บสิ่งที่พิมพ์ไว้ เผื่อตรวจไม่ผ่านแล้วต้องกลับไปที่ฟอร์ม (ข้อมูลจะไม่หาย)
    $keep = ['title' => $title, 'excerpt' => post('excerpt'), 'content' => post('content'),
             'published_at' => post('published_at'), 'is_published' => isset($_POST['is_published']) ? 1 : 0];
    if ($title === '' || post('content') === '') {
        form_keep($keep);
        flash('error', 'กรุณากรอกหัวข้อและเนื้อหาข่าว');
        redirect('admin/news.php?action=' . ($id ? 'edit&id=' . $id : 'new'));
    }
    $cover = null;
    if (!empty($_FILES['cover']) && $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) {
        // ใช้ตัวอัปโหลดรูปเดียวกับอัลบั้ม: ตรวจชนิดไฟล์จริง ย่อรูป และลบข้อมูล EXIF (เช่นพิกัด GPS)
        $r = save_images([$_FILES['cover']], 'news', 1);
        if (!$r['saved']) { form_keep($keep); flash('error', 'ภาพปก: ' . ($r['errors'][0] ?? 'อัปโหลดไม่สำเร็จ')); redirect('admin/news.php?action=' . ($id ? 'edit&id=' . $id : 'new')); }
        $cover = $r['saved'][0]['stored'];
    }
    $excerpt = mb_substr(post('excerpt') ?: excerpt(post('content'), 200), 0, 500);
    $isPub = isset($_POST['is_published']) ? 1 : 0;
    if ($id) {
        db_exec('UPDATE news SET title=?, slug=?, excerpt=?, content=?, published_at=?, is_published=? WHERE id=? AND deleted_at IS NULL',
            [$title, make_slug($title), $excerpt, post('content'), $pub, $isPub, $id]);
        if ($cover) {
            $old = db_val('SELECT cover_image FROM news WHERE id = ?', [$id]); if ($old) { delete_upload('news', $old); }
            db_exec('UPDATE news SET cover_image = ? WHERE id = ?', [$cover, $id]);
        }
    } else {
        $id = db_insert('INSERT INTO news (title, slug, excerpt, content, cover_image, published_at, is_published, author_id) VALUES (?,?,?,?,?,?,?,?)',
            [$title, make_slug($title), $excerpt, post('content'), $cover, $pub, $isPub, $_SESSION['user']['id']]);
    }
    // ---- อัลบั้มรูป: ลบรูปที่ติ๊ก → แก้คำบรรยาย/ลำดับ → เพิ่มรูปใหม่ ----
    $imgMsg = '';
    if (has_news_images()) {
        if (isset($_POST['remove_cover']) && !$cover) {
            $old = db_val('SELECT cover_image FROM news WHERE id = ?', [$id]);
            if ($old) { delete_upload('news', $old); db_exec('UPDATE news SET cover_image = NULL WHERE id = ?', [$id]); }
        }
        foreach ((array) ($_POST['img_delete'] ?? []) as $imgId) {
            $fn = db_val('SELECT stored_name FROM news_images WHERE id = ? AND news_id = ?', [(int) $imgId, $id]);
            if ($fn) { db_exec('DELETE FROM news_images WHERE id = ?', [(int) $imgId]); delete_upload('news', $fn); }
        }
        foreach ((array) ($_POST['img_caption'] ?? []) as $imgId => $cap) {
            db_exec('UPDATE news_images SET caption = ?, sort_order = ? WHERE id = ? AND news_id = ?',
                [mb_substr(trim((string) $cap), 0, 255) ?: null, (int) ($_POST['img_sort'][$imgId] ?? 0), (int) $imgId, $id]);
        }
        [$added, $imgErrs] = news_images_add($id, $_FILES['images'] ?? null);
        if ($added) { $imgMsg = ' และเพิ่มรูป ' . $added . ' รูป'; }
        if ($imgErrs) {
            log_activity('news_save', 'news#' . $id, $title);
            flash('warning', 'บันทึกข่าวแล้ว' . $imgMsg . ' แต่มีรูปที่อัปโหลดไม่สำเร็จ: ' . implode(' / ', $imgErrs));
            redirect('admin/news.php?action=edit&id=' . $id);
        }
    }
    log_activity('news_save', 'news#' . $id, $title);
    flash('success', 'บันทึกข่าวเรียบร้อยแล้ว' . $imgMsg);
    redirect('admin/news.php');
}

// โหลดข้อมูลและตรวจว่ามีอยู่จริง "ก่อน" พิมพ์ส่วนหัวของหน้า (redirect หลังมี output แล้วจะไม่ทำงาน)
$action = get('action');
$n = null;
if ($canWrite && $action === 'edit') {
    $n = db_one('SELECT * FROM news WHERE id = ? AND deleted_at IS NULL', [(int) get('id')]);
    if (!$n) { flash('error', 'ไม่พบข่าวที่ต้องการแก้ไข'); redirect('admin/news.php'); }
}
$old = form_old();   // ค่าที่กรอกค้างไว้จากการบันทึกที่ไม่ผ่าน
admin_header('ข่าวประชาสัมพันธ์', 'news', 'news');
if ($canWrite && in_array($action, ['new', 'edit'], true)):
    if ($old) { $n = array_merge($n ?? [], $old); }
?>
<?php $imgs = !empty($n['id']) ? news_images_of((int) $n['id']) : []; ?>
<div class="panel scale-in"><h2><i class="bi bi-newspaper" aria-hidden="true"></i> <?= $action === 'edit' ? 'แก้ไขข่าว' : 'เพิ่มข่าว' ?></h2>
<form method="post" enctype="multipart/form-data" data-dirty-guard><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($n['id'] ?? 0) ?>">
  <div class="row g-3">
    <div class="col-12"><label class="form-label" for="f-title">หัวข้อ *</label><input id="f-title" name="title" class="form-control" required maxlength="300" value="<?= e($n['title'] ?? '') ?>"></div>
    <div class="col-12"><label class="form-label" for="f-excerpt">คำโปรย (ไม่เกิน 500 ตัวอักษร ถ้าเว้นว่างระบบสร้างให้)</label><input id="f-excerpt" name="excerpt" class="form-control" maxlength="500" value="<?= e($n['excerpt'] ?? '') ?>"></div>
    <div class="col-12"><label class="form-label" for="f-content">เนื้อหา * <span class="text-muted small">(พิมพ์ข้อความธรรมดา หรือใช้แท็ก &lt;p&gt; &lt;strong&gt; &lt;ul&gt;&lt;li&gt; ได้ — แท็กอื่นและแอตทริบิวต์จะถูกตัดออกเพื่อความปลอดภัย)</span></label>
      <textarea id="f-content" name="content" class="form-control" rows="10" required><?= e($n['content'] ?? '') ?></textarea></div>
    <div class="col-md-4"><label class="form-label" for="f-cover">ภาพปก (JPG/PNG/WebP ≤ <?= (int) (MAX_MOU_IMAGE_SIZE / 1048576) ?> MB)</label><input id="f-cover" type="file" name="cover" class="form-control" accept="image/jpeg,image/png,image/webp" aria-describedby="f-cover-help">
      <div class="form-text" id="f-cover-help">ไม่บังคับ — ถ้าไม่ใส่ ระบบใช้รูปแรกในอัลบั้มเป็นภาพปก</div>
      <?php if (!empty($n['cover_image'])): ?><div class="d-flex align-items-center gap-2 mt-2"><img class="img-thumbnail" style="max-height:90px" src="<?= e(url('uploads/news/' . $n['cover_image'])) ?>" alt="ภาพปกปัจจุบัน">
        <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="remove_cover" value="1" id="rmCover"><label class="form-check-label small" for="rmCover">นำภาพปกออก</label></div></div><?php endif; ?></div>
    <div class="col-md-4"><label class="form-label" for="f-published_at">วันเวลาเผยแพร่</label><input id="f-published_at" type="datetime-local" name="published_at" class="form-control" value="<?= e(date('Y-m-d\TH:i', strtotime((string) ($n['published_at'] ?? '')) ?: time())) ?>"></div>
    <div class="col-md-4 pt-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_published" id="isp" value="1" <?= !isset($n['is_published']) || $n['is_published'] ? 'checked' : '' ?>><label class="form-check-label" for="isp">เผยแพร่</label></div></div>

    <!-- อัลบั้มรูปภาพประกอบข่าว: แก้คำบรรยาย/ลำดับ/ลบ และเพิ่มรูปใหม่ แล้วกด "บันทึก" ครั้งเดียว -->
    <fieldset class="col-12 form-group-set"><legend class="form-label"><i class="bi bi-images" aria-hidden="true"></i> รูปภาพประกอบข่าว <span class="text-muted small">(<?= count($imgs) ?>/<?= MAX_NEWS_IMAGES ?> รูป)</span></legend>
    <?php if (!has_news_images()): ?>
      <div class="alert alert-warning mb-0"><i class="bi bi-database-exclamation" aria-hidden="true"></i> ยังไม่ได้สร้างตารางรูปภาพข่าว — กรุณานำเข้า <code>database/migration_v3_news_images.sql</code></div>
    <?php else: ?>
      <?php if ($imgs): ?>
      <p class="small text-muted mb-2">แก้คำบรรยาย ลำดับ (เลขน้อยแสดงก่อน) หรือติ๊ก "ลบ" แล้วกดบันทึกด้านล่าง</p>
      <div class="img-manage mb-3">
        <?php foreach ($imgs as $k => $img): $iid = (int) $img['id']; ?>
        <div class="img-card">
          <a class="ph" href="<?= e(url('uploads/news/' . $img['stored_name'])) ?>" data-lightbox="admin-news" data-caption="<?= e($img['caption'] ?? '') ?>">
            <img src="<?= e(url('uploads/news/' . $img['stored_name'])) ?>" alt="<?= e($img['caption'] ?: 'รูปที่ ' . ($k + 1)) ?>" loading="lazy"></a>
          <div class="body">
            <label class="visually-hidden" for="ncap<?= $iid ?>">คำบรรยายรูปที่ <?= $k + 1 ?></label>
            <input id="ncap<?= $iid ?>" name="img_caption[<?= $iid ?>]" class="form-control form-control-sm mb-1" maxlength="255" placeholder="คำบรรยายภาพ" value="<?= e($img['caption'] ?? '') ?>">
            <div class="d-flex gap-2 align-items-center">
              <input type="number" name="img_sort[<?= $iid ?>]" class="form-control form-control-sm" style="width:64px" value="<?= (int) $img['sort_order'] ?>" aria-label="ลำดับของรูปที่ <?= $k + 1 ?> (เลขน้อยแสดงก่อน)" title="ลำดับ">
              <div class="form-check ms-auto mb-0"><input class="form-check-input" type="checkbox" name="img_delete[]" value="<?= $iid ?>" id="ndel<?= $iid ?>">
                <label class="form-check-label small text-danger" for="ndel<?= $iid ?>">ลบ<span class="visually-hidden">รูปที่ <?= $k + 1 ?></span></label></div>
            </div>
            <div class="text-muted mt-1 small"><?= $img['width'] ? (int) $img['width'] . '×' . (int) $img['height'] . ' · ' : '' ?><?= number_format($img['size'] / 1024) ?> KB</div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if (count($imgs) < MAX_NEWS_IMAGES): ?>
      <label class="dropzone d-block" data-dropzone data-max="<?= upload_slots(MAX_NEWS_IMAGES - count($imgs)) ?>" data-max-total-mb="<?= upload_total_mb() ?>" data-max-mb="<?= (int) (MAX_MOU_IMAGE_SIZE / 1048576) ?>" data-previews="#newsPreviews">
        <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple aria-label="เลือกรูปภาพประกอบข่าว (เลือกได้หลายรูป)">
        <span class="dz-ic"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i></span>
        <div class="fw-semibold mt-1">ลากรูปมาวางที่นี่ หรือคลิกเพื่อเลือกหลายรูป</div>
        <div class="small text-muted">JPG/PNG/WebP รูปละไม่เกิน <?= (int) (MAX_MOU_IMAGE_SIZE / 1048576) ?> MB · เพิ่มได้ครั้งละไม่เกิน <?= (int) ini_get('max_file_uploads') ?: 20 ?> รูป · ระบบย่อรูปและลบข้อมูลตำแหน่ง GPS ให้อัตโนมัติ</div>
        <div class="small fw-semibold text-gold-strong mt-1" data-dz-count aria-live="polite"></div>
      </label>
      <div class="dz-previews" id="newsPreviews"></div>
      <?php else: ?><div class="small text-muted">ครบจำนวนรูปสูงสุดแล้ว — ลบรูปเดิมก่อนเพิ่มใหม่</div><?php endif; ?>
    <?php endif; ?>
    </fieldset>
  </div>
  <div class="mt-3 d-flex gap-2"><button type="submit" class="btn btn-navy"><i class="bi bi-save" aria-hidden="true"></i> บันทึก</button><a class="btn btn-outline-secondary" href="news.php">ยกเลิก</a></div>
</form></div>
<?php else: $rows = db_all('SELECT n.id, n.title, n.published_at, n.view_count, n.is_published, ' . news_cover_sql() . ', u.full_name FROM news n LEFT JOIN users u ON u.id = n.author_id WHERE n.deleted_at IS NULL ORDER BY n.published_at DESC'); ?>
<div class="page-head fade-up"><div><div class="sub">ทั้งหมด <?= count($rows) ?> ข่าว</div><h2 class="title">ข่าวประชาสัมพันธ์</h2></div>
  <?php if ($canWrite): ?><a class="btn btn-gold" href="news.php?action=new"><i class="bi bi-plus-lg" aria-hidden="true"></i> เพิ่มข่าว</a><?php endif; ?></div>
<div class="panel fade-up delay-1"><div class="table-responsive"><table class="table table-sm table-hover js-dt align-middle" data-order='[1,"desc"]'>
  <thead><tr><th>หัวข้อ</th><th>เผยแพร่เมื่อ</th><th>ผู้เขียน</th><th>อ่าน</th><th>สถานะ</th><th class="no-sort"><span class="visually-hidden">การจัดการ</span></th></tr></thead><tbody>
  <?php foreach ($rows as $n): ?><tr><td><div class="d-flex gap-2 align-items-center"><?php if ($n['cover_image']): ?><img src="<?= e(url('uploads/news/' . $n['cover_image'])) ?>" alt="" class="rounded" style="width:52px;height:38px;object-fit:cover;flex-shrink:0" loading="lazy"><?php endif; ?><div><a target="_blank" rel="noopener" href="<?= e(route('news_detail', $n['id'])) ?>"><?= e($n['title']) ?><span class="visually-hidden"> (เปิดแท็บใหม่)</span></a><?php if ((int) $n['image_count']): ?><div class="small text-muted"><i class="bi bi-images" aria-hidden="true"></i> <?= (int) $n['image_count'] ?> รูป</div><?php endif; ?></div></div></td>
    <td class="text-nowrap" data-order="<?= e($n['published_at']) ?>"><?= e(thai_date($n['published_at'], true)) ?></td><td><?= e($n['full_name']) ?></td><td><?= number_format((int) $n['view_count']) ?></td>
    <td><?= $n['is_published'] ? '<span class="badge bg-success">เผยแพร่</span>' : '<span class="badge bg-secondary">ซ่อน</span>' ?></td>
    <td class="text-end text-nowrap"><?php if ($canWrite): ?><a class="btn btn-sm btn-outline-primary" href="news.php?action=edit&id=<?= (int) $n['id'] ?>" title="แก้ไข" aria-label="แก้ไขข่าว <?= e($n['title']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a>
      <form class="d-inline" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-secondary" title="เปิด/ซ่อน" aria-label="เปิดหรือซ่อนข่าว <?= e($n['title']) ?>"><i class="bi bi-eye" aria-hidden="true"></i></button></form>
      <form class="d-inline" method="post" data-confirm="ลบข่าวนี้?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger" title="ลบ" aria-label="ลบข่าว <?= e($n['title']) ?>"><i class="bi bi-trash" aria-hidden="true"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div></div>
<?php endif; admin_footer();
