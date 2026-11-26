<?php
/** admin/mou_form.php — เพิ่ม/แก้ไข MOU */
require_once __DIR__ . '/../includes/layout_admin.php';
require_permission('mou');

$id   = (int) get('id');
$mou  = $id ? mou_get($id, false) : null;
if ($id && !$mou) { flash('error', 'ไม่พบข้อมูล MOU'); redirect('admin/mou_list.php'); }

$fromProposal = (int) get('proposal_id');
$errors = [];
$data = $mou ?: [
    'mou_no' => '', 'title' => '', 'partner_id' => (int) get('partner_id'), 'sign_date' => '', 'start_date' => '', 'end_date' => '',
    'duration_years' => '', 'signer_college' => 'ผู้อำนวยการวิทยาลัยพณิชยการบางนา', 'signer_partner' => '', 'objective' => '', 'summary' => '',
    'scope' => '', 'budget' => '', 'student_benefit_count' => 0, 'status' => 'draft', 'is_public' => 0, 'responsible' => '', 'note' => '',
];
$selProgs = $id ? array_column(mou_programs_of($id), 'id') : [];
$selCats  = $id ? array_column(mou_categories_of($id), 'id') : [];
$benefitsText = $id ? implode("\n", array_column(mou_benefits_of($id), 'item')) : '';

// เติมค่าเริ่มต้นจากข้อเสนอความร่วมมือที่กด "แปลงเป็น MOU"
if (!$id && $fromProposal && ($pr = db_one('SELECT * FROM proposals WHERE id = ?', [$fromProposal]))) {
    $data['title'] = 'ความร่วมมือกับ ' . $pr['org_name'];
    $data['objective'] = $pr['details'];
    $selProgs = array_filter(array_map('intval', explode(',', (string) $pr['programs_interest'])));
    $note = 'แปลงจากข้อเสนอ ' . $pr['ref_no'];
    $data['note'] = $note;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard('mou');
    $d = [
        'mou_no' => mb_substr(post('mou_no'), 0, 60), 'title' => mb_substr(post('title'), 0, 300),
        'partner_id' => (int) post('partner_id'),
        'sign_date' => parse_thai_input(post('sign_date')), 'start_date' => parse_thai_input(post('start_date')),
        'end_date' => parse_thai_input(post('end_date')),
        'duration_years' => post('duration_years') !== '' ? (float) post('duration_years') : null,
        'signer_college' => mb_substr(post('signer_college'), 0, 200), 'signer_partner' => mb_substr(post('signer_partner'), 0, 200),
        'objective' => post('objective'), 'summary' => post('summary'), 'scope' => post('scope'),
        'budget' => post('budget') !== '' ? (float) str_replace(',', '', post('budget')) : null,
        'student_benefit_count' => max(0, (int) post('student_benefit_count')),
        'status' => post('status'), 'is_public' => isset($_POST['is_public']) ? 1 : 0,
        'responsible' => mb_substr(post('responsible'), 0, 150), 'note' => post('note'),
    ];
    // key = ชื่อช่อง → ข้อความขึ้นใต้ช่องนั้น และกล่องสรุปลิงก์ไปที่ช่อง
    if ($d['mou_no'] === '') { $errors['mou_no'] = 'กรุณากรอกเลขที่ MOU'; }
    if ($d['title'] === '') { $errors['title'] = 'กรุณากรอกชื่อเรื่อง'; }
    if (!db_val('SELECT id FROM partners WHERE id = ?', [$d['partner_id']])) { $errors['partner_id'] = 'กรุณาเลือกหน่วยงานคู่ความร่วมมือ'; }
    foreach (['sign_date' => 'วันที่ลงนาม', 'start_date' => 'วันที่เริ่ม', 'end_date' => 'วันที่สิ้นสุด'] as $k => $l) {
        if (!$d[$k]) { $errors[$k] = "กรุณาระบุ{$l}อย่างถูกต้อง"; }
    }
    if ($d['start_date'] && $d['end_date'] && $d['end_date'] < $d['start_date']) { $errors['end_date'] = 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่ม'; }
    if (!in_array($d['status'], ['draft', 'active', 'cancelled'], true)) { $d['status'] = 'draft'; }
    if ($dup = db_one('SELECT id, deleted_at FROM mou WHERE mou_no = ? AND id <> ?', [$d['mou_no'], $id])) {
        $errors['mou_no'] = $dup['deleted_at'] !== null
            ? 'เลขที่ MOU นี้เป็นของรายการที่ถูกลบไว้ — กู้คืนหรือลบถาวรรายการนั้นก่อน (หน้ารายการ MOU → ปุ่ม "ที่ลบแล้ว")'
            : 'เลขที่ MOU นี้มีอยู่แล้ว';
    }
    if ($d['duration_years'] === null && $d['start_date'] && $d['end_date']) {
        $d['duration_years'] = round((strtotime($d['end_date']) - strtotime($d['start_date'])) / 86400 / 365.25, 1);
    }
    $selProgs = array_map('intval', (array) ($_POST['programs'] ?? []));
    $selCats  = array_map('intval', (array) ($_POST['categories'] ?? []));
    $benefitsText = (string) ($_POST['benefits'] ?? '');
    $data = array_merge($data, $d);

    if (!$errors) {
        $cols = ['mou_no', 'title', 'partner_id', 'sign_date', 'start_date', 'end_date', 'duration_years', 'signer_college', 'signer_partner',
                 'objective', 'summary', 'scope', 'budget', 'student_benefit_count', 'status', 'is_public', 'responsible', 'note'];
        $vals = array_map(fn($c) => $d[$c] === '' ? null : $d[$c], $cols);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($id) {
                db_exec('UPDATE mou SET ' . implode(', ', array_map(fn($c) => "`$c` = ?", $cols)) . ' WHERE id = ?', array_merge($vals, [$id]));
            } else {
                $id = db_insert('INSERT INTO mou (' . implode(',', array_map(fn($c) => "`$c`", $cols)) . ', created_by) VALUES ('
                    . implode(',', array_fill(0, count($cols), '?')) . ', ?)', array_merge($vals, [$_SESSION['user']['id']]));
            }
            sync_relation('mou_programs', 'mou_id', $id, 'program_id', $selProgs);
            sync_relation('mou_category_map', 'mou_id', $id, 'category_id', $selCats);
            db_exec('DELETE FROM mou_benefits WHERE mou_id = ?', [$id]);
            $i = 0;
            foreach (preg_split('/\R/u', $benefitsText) as $line) {
                $line = trim($line);
                if ($line !== '') { db_exec('INSERT INTO mou_benefits (mou_id, item, sort_order) VALUES (?,?,?)', [$id, mb_substr($line, 0, 500), ++$i]); }
            }
            // แปลงข้อเสนอเป็น MOU: ผูกกลับและปิดเป็น "อนุมัติ"
            // ใช้เลขข้อเสนอที่ตรวจแล้วว่ามีอยู่จริง ($pr) — ไม่เชื่อค่า proposal_id ที่ส่งมากับฟอร์มโดยตรง
            if (!empty($pr) && (int) post('proposal_id') === (int) $pr['id']) {
                db_exec("UPDATE proposals SET converted_mou_id = ?, status = 'approved' WHERE id = ?", [$id, (int) $pr['id']]);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            error_log($ex->getMessage());
            $errors[] = 'บันทึกไม่สำเร็จ: ' . (APP_ENV === 'production' ? 'เกิดข้อผิดพลาด' : $ex->getMessage());
        }
        if (!$errors) {
            refresh_mou_status(true);
            log_activity($mou ? 'mou_update' : 'mou_create', 'mou#' . $id, $d['mou_no'] . ' ' . $d['title']);
            // รูปภาพประกอบรายละเอียด (อัปโหลดหลังบันทึกข้อมูลหลักสำเร็จ)
            $imgErr = mou_images_add($id, $_FILES['images'] ?? []);
            if ($imgErr) {
                flash('warning', 'บันทึกข้อมูล MOU แล้ว แต่มีรูปที่อัปโหลดไม่สำเร็จ: ' . implode(' | ', $imgErr));
                redirect('admin/mou_view.php?id=' . $id . '#gallery');
            }
            flash('success', 'บันทึกข้อมูล MOU เรียบร้อยแล้ว');
            redirect('admin/mou_view.php?id=' . $id);
        }
    }
}

// แปลงค่าวันที่ให้ input type=date
$dv = fn($k) => e(substr((string) ($data[$k] ?? ''), 0, 10));
admin_header($mou ? 'แก้ไข MOU' : 'เพิ่ม MOU ใหม่', 'mou', 'mou');
$partners = all_partners();
$images = $id ? mou_images_of($id) : [];
// attribute ของช่อง: id + class (+ is-invalid) + aria-invalid/aria-describedby เมื่อมีข้อผิดพลาด
$fa = fn(string $k, string $cls = 'form-control') => ' id="' . fid($k) . '" class="' . $cls . field_err_class($errors, $k) . '"' . field_err_attrs($errors, $k);
$req = '<span class="text-danger" aria-hidden="true">*</span>';
$hadImages = !empty($_FILES['images']['name'][0]);
?>
<div class="page-head fade-up">
  <div><div class="sub"><?= $mou ? 'แก้ไขข้อตกลงเลขที่ ' . e($mou['mou_no']) : 'กรอกข้อมูลให้ครบ แล้วแนบรูปภาพประกอบได้ในขั้นตอนเดียว' ?></div>
    <h2 class="title"><?= $mou ? 'แก้ไข MOU' : 'เพิ่ม MOU ใหม่' ?></h2></div>
  <a class="btn btn-outline-secondary" href="<?= e(url($mou ? 'admin/mou_view.php?id=' . $id : 'admin/mou_list.php')) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> กลับ</a>
</div>
<?= error_summary($errors, $hadImages ? 'รูปภาพที่เลือกไว้ยังไม่ถูกอัปโหลด — กรุณาเลือกรูปใหม่อีกครั้งก่อนบันทึก' : '') ?>
<p class="small text-muted"><?= $req ?> หมายถึงช่องที่ต้องกรอก</p>
<form method="post" id="mouForm" enctype="multipart/form-data" novalidate class="stagger" data-dirty-guard>
  <?= csrf_field() ?>
  <?php if ($fromProposal): ?><input type="hidden" name="proposal_id" value="<?= (int) $fromProposal ?>"><?php endif; ?>
  <div class="panel"><h2><i class="bi bi-info-circle" aria-hidden="true"></i> ข้อมูลหลัก</h2><div class="row g-3">
    <div class="col-md-4"><label class="form-label" for="<?= fid('mou_no') ?>">เลขที่ MOU <?= $req ?></label><input name="mou_no"<?= $fa('mou_no') ?> required maxlength="60" value="<?= e($data['mou_no']) ?>"><?= field_err_msg($errors, 'mou_no') ?></div>
    <div class="col-md-8"><label class="form-label" for="<?= fid('title') ?>">ชื่อเรื่อง <?= $req ?></label><input name="title"<?= $fa('title') ?> required maxlength="300" value="<?= e($data['title']) ?>"><?= field_err_msg($errors, 'title') ?></div>
    <div class="col-md-8"><label class="form-label" for="<?= fid('partner_id') ?>">หน่วยงานคู่ความร่วมมือ <?= $req ?></label>
      <div class="input-group">
      <select name="partner_id"<?= $fa('partner_id', 'form-select') ?> required><option value="">เลือกหน่วยงาน...</option>
        <?php foreach ($partners as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $data['partner_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?> (<?= e(partner_type_label($p['type'])) ?>)</option><?php endforeach; ?></select>
      <button type="button" class="btn btn-outline-secondary" id="reloadPartners" title="โหลดรายชื่อหน่วยงานใหม่" aria-label="โหลดรายชื่อหน่วยงานใหม่"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button></div>
      <div class="form-text"><a href="<?= e(url('admin/partners.php?action=new')) ?>" target="_blank" rel="noopener" data-no-loader>+ เพิ่มหน่วยงานใหม่ <span class="small">(เปิดแท็บใหม่)</span></a> — เพิ่มเสร็จแล้วกลับมาที่แท็บนี้ รายชื่อจะอัปเดตเอง ข้อมูลที่กรอกไว้ไม่หาย</div>
      <?= field_err_msg($errors, 'partner_id') ?></div>
    <div class="col-md-4"><label class="form-label" for="<?= fid('responsible') ?>">ผู้รับผิดชอบ (ภายใน)</label><input name="responsible"<?= $fa('responsible') ?> maxlength="150" value="<?= e($data['responsible']) ?>"></div>
    <?php foreach (['sign_date' => 'วันที่ลงนาม', 'start_date' => 'วันที่เริ่ม', 'end_date' => 'วันที่สิ้นสุด'] as $dk => $dl): ?>
    <div class="col-md-3"><label class="form-label" for="<?= fid($dk) ?>"><?= e($dl) ?> <?= $req ?></label><input type="date" name="<?= $dk ?>"<?= $fa($dk, 'form-control js-date') ?> required value="<?= $dv($dk) ?>"<?= isset($errors[$dk]) ? '' : ' aria-describedby="' . fid($dk) . '-be"' ?>><div class="form-text js-be" id="<?= fid($dk) ?>-be"></div><?= field_err_msg($errors, $dk) ?></div>
    <?php endforeach; ?>
    <div class="col-md-3"><label class="form-label" for="<?= fid('duration_years') ?>">ระยะเวลา (ปี)</label><input name="duration_years" type="number" step="0.1" min="0" inputmode="decimal"<?= $fa('duration_years') ?> value="<?= e($data['duration_years']) ?>" aria-describedby="<?= fid('duration_years') ?>-help"><div class="form-text" id="<?= fid('duration_years') ?>-help">เว้นว่างได้ ระบบคำนวณจากวันที่ให้</div></div>
    <div class="col-md-6"><label class="form-label" for="<?= fid('signer_college') ?>">ผู้ลงนามฝ่ายวิทยาลัย</label><input name="signer_college"<?= $fa('signer_college') ?> maxlength="200" value="<?= e($data['signer_college']) ?>"></div>
    <div class="col-md-6"><label class="form-label" for="<?= fid('signer_partner') ?>">ผู้ลงนามฝ่ายคู่ความร่วมมือ</label><input name="signer_partner"<?= $fa('signer_partner') ?> maxlength="200" value="<?= e($data['signer_partner']) ?>"></div>
  </div></div>

  <div class="panel"><h2><i class="bi bi-journal-text" aria-hidden="true"></i> เนื้อหาข้อตกลง</h2><div class="row g-3">
    <div class="col-12"><label class="form-label" for="<?= fid('objective') ?>">วัตถุประสงค์</label><textarea name="objective"<?= $fa('objective') ?> rows="3"><?= e($data['objective']) ?></textarea></div>
    <div class="col-12"><label class="form-label" for="<?= fid('summary') ?>">สาระสำคัญ</label><textarea name="summary"<?= $fa('summary') ?> rows="3"><?= e($data['summary']) ?></textarea></div>
    <div class="col-12"><label class="form-label" for="<?= fid('scope') ?>">ขอบเขตความร่วมมือ</label><textarea name="scope"<?= $fa('scope') ?> rows="3"><?= e($data['scope']) ?></textarea></div>
    <div class="col-12"><label class="form-label" for="<?= fid('benefits') ?>">สิทธิประโยชน์ของนักศึกษา (หนึ่งรายการต่อหนึ่งบรรทัด)</label><textarea name="benefits"<?= $fa('benefits') ?> rows="4"><?= e($benefitsText) ?></textarea></div>
  </div></div>

  <div class="panel"><h2><i class="bi bi-diagram-3" aria-hidden="true"></i> สาขาวิชาและประเภทความร่วมมือ</h2><div class="row g-3">
    <fieldset class="col-md-6 form-group-set"><legend class="form-label">สาขาวิชาที่เกี่ยวข้อง</legend>
      <div class="check-box">
      <?php foreach (all_programs() as $p): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="programs[]" id="p<?= (int) $p['id'] ?>" value="<?= (int) $p['id'] ?>" <?= in_array((int) $p['id'], array_map('intval', $selProgs), true) ? 'checked' : '' ?>>
        <label class="form-check-label" for="p<?= (int) $p['id'] ?>"><?= e($p['level'] . ' ' . $p['name']) ?></label></div><?php endforeach; ?></div></fieldset>
    <fieldset class="col-md-6 form-group-set"><legend class="form-label">ประเภทความร่วมมือ</legend>
      <div class="check-box">
      <?php foreach (all_categories() as $c): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="categories[]" id="c<?= (int) $c['id'] ?>" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], array_map('intval', $selCats), true) ? 'checked' : '' ?>>
        <label class="form-check-label" for="c<?= (int) $c['id'] ?>"><?= e($c['name']) ?></label></div><?php endforeach; ?></div></fieldset>
  </div></div>

  <div class="panel"><h2><i class="bi bi-shield-lock" aria-hidden="true"></i> ข้อมูลภายในและการเผยแพร่</h2><div class="row g-3">
    <div class="col-md-4"><label class="form-label" for="<?= fid('budget') ?>">งบประมาณ (บาท) <span class="badge bg-secondary">ภายใน ไม่แสดงสาธารณะ</span></label><input name="budget" inputmode="decimal"<?= $fa('budget') ?> value="<?= e($data['budget']) ?>"></div>
    <div class="col-md-4"><label class="form-label" for="<?= fid('student_benefit_count') ?>">จำนวนนักศึกษาที่ได้รับประโยชน์</label><input type="number" min="0" inputmode="numeric" name="student_benefit_count"<?= $fa('student_benefit_count') ?> value="<?= (int) $data['student_benefit_count'] ?>"></div>
    <div class="col-md-4"><label class="form-label" for="<?= fid('status') ?>">สถานะ</label>
      <select name="status"<?= $fa('status', 'form-select') ?>>
        <option value="draft" <?= $data['status'] === 'draft' ? 'selected' : '' ?>>ฉบับร่าง</option>
        <option value="active" <?= in_array($data['status'], ['active', 'expiring', 'expired'], true) ? 'selected' : '' ?>>มีผลบังคับใช้ (ระบบคำนวณใกล้หมดอายุ/หมดอายุให้อัตโนมัติ)</option>
        <option value="cancelled" <?= $data['status'] === 'cancelled' ? 'selected' : '' ?>>ยกเลิก</option></select></div>
    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="is_public" id="is_public" value="1" <?= !empty($data['is_public']) ? 'checked' : '' ?>>
      <label class="form-check-label" for="is_public">เผยแพร่บนเว็บไซต์สาธารณะ (ฉบับร่างและยกเลิกจะไม่แสดงแม้เปิดตัวเลือกนี้)</label></div></div>
    <div class="col-12"><label class="form-label" for="<?= fid('note') ?>">หมายเหตุภายใน</label><textarea name="note"<?= $fa('note') ?> rows="2"><?= e($data['note']) ?></textarea></div>
  </div></div>

  <!-- รูปภาพประกอบรายละเอียด (v2) -->
  <div class="panel" id="images"><h2><i class="bi bi-images" aria-hidden="true"></i> รูปภาพประกอบรายละเอียด <span class="panel-sub">JPG / PNG / WebP ไม่เกิน <?= (int) (MAX_MOU_IMAGE_SIZE / 1048576) ?> MB ต่อรูป · สูงสุด <?= (int) MAX_MOU_IMAGES ?> รูป</span></h2>
    <?php if ($images): ?>
      <div class="small text-muted mb-2">รูปที่มีอยู่แล้ว <?= count($images) ?> รูป — แก้คำบรรยาย ตั้งภาพปก หรือลบได้ที่ <a href="<?= e(url('admin/mou_view.php?id=' . $id . '#gallery')) ?>">หน้ารายละเอียด MOU</a></div>
      <div class="gallery-grid mb-3">
        <?php foreach ($images as $img): ?><a class="g-item" href="<?= e(media_url('mou_images', $img['stored_name'])) ?>" data-lightbox="form" data-caption="<?= e($img['caption'] ?? '') ?>">
          <img src="<?= e(media_url('mou_images', $img['stored_name'])) ?>" alt="<?= e($img['caption'] ?: 'รูปที่มีอยู่แล้ว') ?>" loading="lazy"><?php if ($img['is_cover']): ?><span class="g-cap"><i class="bi bi-star-fill text-warning" aria-hidden="true"></i> ภาพปก</span><?php endif; ?></a><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if (has_mou_images()): ?>
    <label class="dropzone d-block" data-dropzone data-max="<?= upload_slots(MAX_MOU_IMAGES - count($images)) ?>" data-max-total-mb="<?= upload_total_mb() ?>" data-max-mb="<?= (int) (MAX_MOU_IMAGE_SIZE / 1048576) ?>" data-previews="#formPreviews">
      <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple aria-label="เลือกรูปภาพประกอบ (เลือกได้หลายรูป)">
      <span class="dz-ic"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i></span>
      <div class="fw-semibold mt-1">ลากรูปมาวางที่นี่ หรือคลิกเพื่อเลือกหลายรูป</div>
      <div class="small text-muted">รูปแรกจะเป็นภาพปกอัตโนมัติ (เปลี่ยนภายหลังได้) · ระบบย่อรูปให้อัตโนมัติ</div>
      <div class="small fw-semibold text-gold-strong mt-1" data-dz-count aria-live="polite"></div>
    </label>
    <div class="dz-previews" id="formPreviews"></div>
    <?php else: ?>
    <div class="alert alert-warning mb-0"><i class="bi bi-database-exclamation" aria-hidden="true"></i> ยังไม่ได้สร้างตารางรูปภาพ — กรุณานำเข้าไฟล์ <code>database/migration_v2_images.sql</code> ผ่าน phpMyAdmin ก่อน</div>
    <?php endif; ?>
  </div>

  <div class="form-actions"><button type="submit" class="btn btn-navy"><i class="bi bi-save" aria-hidden="true"></i> บันทึก</button>
    <a class="btn btn-outline-secondary" href="<?= e(url($mou ? 'admin/mou_view.php?id=' . $id : 'admin/mou_list.php')) ?>">ยกเลิก</a>
    <span class="small text-muted ms-auto"><?= $req ?> = จำเป็นต้องกรอก</span></div>
</form>
<script nonce="<?= e(csp_nonce()) ?>">
document.addEventListener('DOMContentLoaded', function () {
  var f = document.getElementById('mouForm');
  function be(inp) { var h = document.getElementById(inp.id + '-be'); if (!h) return;
    if (!inp.value) { h.textContent = ''; return; }
    var p = inp.value.split('-'); var m = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    h.textContent = 'พ.ศ.: ' + parseInt(p[2], 10) + ' ' + m[parseInt(p[1], 10)] + ' ' + (parseInt(p[0], 10) + 543); }
  document.querySelectorAll('.js-date').forEach(function (i) { be(i); i.addEventListener('change', function () { be(i); calc(); }); });
  function calc() { var s = f.start_date.value, e = f.end_date.value;
    if (s && e && !f.duration_years.dataset.manual) f.duration_years.value = Math.max(0, Math.round((new Date(e) - new Date(s)) / 86400000 / 365.25 * 10) / 10); }
  f.duration_years.addEventListener('input', function () { this.dataset.manual = '1'; });
  // ตรวจฝั่งไคลเอนต์: ข้อความใต้ช่อง + กล่องสรุปที่รับ focus (app.js: MouForm)
  f.addEventListener('submit', function (ev) { var errs = [];
    if (!f.mou_no.value.trim()) errs.push(['mou_no', 'กรุณากรอกเลขที่ MOU']);
    if (!f.title.value.trim()) errs.push(['title', 'กรุณากรอกชื่อเรื่อง']);
    if (!f.partner_id.value) errs.push(['partner_id', 'กรุณาเลือกหน่วยงานคู่ความร่วมมือ']);
    [['sign_date', 'วันที่ลงนาม'], ['start_date', 'วันที่เริ่ม'], ['end_date', 'วันที่สิ้นสุด']].forEach(function (d) {
      if (!f[d[0]].value) errs.push([d[0], 'กรุณาระบุ' + d[1]]); });
    if (f.start_date.value && f.end_date.value && f.end_date.value < f.start_date.value) errs.push(['end_date', 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่ม']);
    if (errs.length) { ev.preventDefault(); ev.stopImmediatePropagation(); MouForm.showErrors(f, errs); } });

  // โหลดรายชื่อหน่วยงานใหม่ (หลังเพิ่มหน่วยงานในแท็บอื่น) โดยไม่ต้องรีเฟรชหน้า — ค่าที่เลือกไว้ยังอยู่
  var sel = f.partner_id, btn = document.getElementById('reloadPartners');
  function reloadPartners(announce) {
    return fetch('partners_json.php', { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (list) {
      var cur = sel.value, before = sel.options.length - 1;
      sel.length = 1;
      list.forEach(function (p) { var o = new Option(p.label, p.id); if (String(p.id) === cur) o.selected = true; sel.add(o); });
      var added = list.length - before;
      if (announce || added > 0) MouFlash('info', added > 0 ? 'เพิ่มหน่วยงานใหม่ในรายการแล้ว ' + added + ' รายการ' : 'รายชื่อหน่วยงานเป็นปัจจุบันแล้ว');
    }).catch(function () { if (announce) MouFlash('warning', 'โหลดรายชื่อหน่วยงานไม่สำเร็จ กรุณาลองใหม่'); });
  }
  btn.addEventListener('click', function () { reloadPartners(true); });
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') reloadPartners(false); });
});
</script>
<?php admin_footer();
