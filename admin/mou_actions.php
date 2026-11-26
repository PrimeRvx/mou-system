<?php
/**
 * admin/mou_actions.php — การกระทำย่อยของ MOU (POST + CSRF เท่านั้น)
 * delete_mou, restore_mou, purge_mou (ลบถาวร เฉพาะ admin), upload_file, delete_file, toggle_file, add_activity, delete_activity
 * (v2) upload_images, delete_image, set_cover, save_images (คำบรรยาย + ลำดับ)
 *
 * v2.0.1: กู้คืนไฟล์ที่หายไปจาก Release v2.0.0 (ฟอร์มใน mou_list.php / mou_view.php ส่งมาที่ไฟล์นี้)
 *         + ตรวจว่า MOU ยังมีอยู่และไม่ถูกลบก่อนทำคำสั่งย่อยทุกคำสั่ง
 */
require_once __DIR__ . '/../includes/layout_admin.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('admin/mou_list.php'); }
admin_post_guard('mou');

$action = post('action');
$id     = (int) post('id');
$mouId  = (int) post('mou_id');
$back   = 'admin/mou_view.php?id=' . $mouId;

// คำสั่งที่ทำกับ MOU ทั้งฉบับ (ใช้ id) — นอกนั้นเป็นคำสั่งย่อยของ MOU ที่เปิดอยู่ (ใช้ mou_id) ซึ่งต้องยังมีอยู่และไม่ถูกลบ
$wholeMou = ['delete_mou', 'restore_mou', 'purge_mou'];
$known    = array_merge($wholeMou, ['upload_file', 'delete_file', 'toggle_file', 'add_activity', 'delete_activity',
                                    'upload_images', 'delete_image', 'set_cover', 'save_images']);
if (!in_array($action, $known, true)) {
    http_response_code(400);
    exit('ไม่รู้จักคำสั่งนี้');
}
if (!in_array($action, $wholeMou, true) && !mou_get($mouId, false)) {
    flash('error', 'ไม่พบ MOU หรือ MOU นี้ถูกลบไปแล้ว');
    redirect('admin/mou_list.php');
}

switch ($action) {
    case 'delete_mou':   // soft delete
        $m = db_one('SELECT mou_no FROM mou WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($m) {
            db_exec('UPDATE mou SET deleted_at = NOW() WHERE id = ?', [$id]);
            log_activity('mou_delete', 'mou#' . $id, $m['mou_no']);
            flash('success', 'ลบ MOU เรียบร้อยแล้ว (กู้คืนได้ที่ปุ่ม "ที่ลบแล้ว" ในหน้ารายการ)');
        } else {
            flash('error', 'ไม่พบรายการที่ต้องการลบ');
        }
        redirect('admin/mou_list.php');

    case 'restore_mou':   // กู้คืนรายการที่ลบแบบซ่อน
        $m = db_one('SELECT mou_no FROM mou WHERE id = ? AND deleted_at IS NOT NULL', [$id]);
        if ($m) {
            if (db_val('SELECT id FROM mou WHERE mou_no = ? AND id <> ? AND deleted_at IS NULL', [$m['mou_no'], $id])) {
                flash('error', 'กู้คืนไม่ได้ เพราะมี MOU เลขที่ ' . $m['mou_no'] . ' ที่ใช้งานอยู่แล้ว');
                redirect('admin/mou_list.php?deleted=1');
            }
            db_exec('UPDATE mou SET deleted_at = NULL WHERE id = ?', [$id]);
            refresh_mou_status(true);   // คำนวณสถานะตามวันสิ้นสุดใหม่ทันที
            log_activity('mou_restore', 'mou#' . $id, $m['mou_no']);
            flash('success', 'กู้คืน MOU ' . $m['mou_no'] . ' เรียบร้อยแล้ว');
            redirect('admin/mou_view.php?id=' . $id);
        }
        flash('error', 'ไม่พบรายการที่ต้องการกู้คืน');
        redirect('admin/mou_list.php?deleted=1');

    case 'purge_mou':   // ลบถาวร: เฉพาะผู้ดูแลระบบ และเฉพาะรายการที่ถูกลบแบบซ่อนไว้แล้วเท่านั้น
        if (($_SESSION['user']['role'] ?? '') !== 'admin') {
            flash('error', 'เฉพาะผู้ดูแลระบบเท่านั้นที่ลบถาวรได้');
            redirect('admin/mou_list.php?deleted=1');
        }
        $m = db_one('SELECT mou_no FROM mou WHERE id = ? AND deleted_at IS NOT NULL', [$id]);
        if (!$m) {
            flash('error', 'ไม่พบรายการที่ต้องการลบถาวร');
            redirect('admin/mou_list.php?deleted=1');
        }
        // จดชื่อไฟล์บนดิสก์ไว้ก่อน แล้วลบข้อมูลในฐานข้อมูล (ตารางลูกถูกลบตาม FK) จากนั้นจึงลบไฟล์
        $docs = array_column(db_all('SELECT stored_name FROM mou_files WHERE mou_id = ?', [$id]), 'stored_name');
        $acts = array_filter(array_column(db_all('SELECT image FROM mou_activities WHERE mou_id = ?', [$id]), 'image'));
        $imgs = array_column(mou_images_of($id), 'stored_name');
        $pdo = db();
        $pdo->beginTransaction();
        try {
            db_exec('UPDATE proposals SET converted_mou_id = NULL WHERE converted_mou_id = ?', [$id]);
            db_exec('DELETE FROM mou WHERE id = ?', [$id]);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            error_log('purge_mou: ' . $ex->getMessage());
            flash('error', 'ลบถาวรไม่สำเร็จ กรุณาลองใหม่');
            redirect('admin/mou_list.php?deleted=1');
        }
        foreach ($docs as $fn) { delete_upload('mou', $fn); }
        foreach ($acts as $fn) { delete_upload('activities', $fn); }
        foreach ($imgs as $fn) { delete_upload('mou_images', $fn); }
        log_activity('mou_purge', 'mou#' . $id, $m['mou_no'] . ' (ลบถาวร พร้อมไฟล์ ' . (count($docs) + count($acts) + count($imgs)) . ' ไฟล์)');
        flash('success', 'ลบ MOU ' . $m['mou_no'] . ' ถาวรเรียบร้อยแล้ว');
        redirect('admin/mou_list.php?deleted=1');

    /* ---------- ไฟล์แนบ MOU ---------- */
    case 'upload_file':
        $r = save_upload($_FILES['file'] ?? [], MOU_FILE_MIMES, MAX_MOU_FILE_SIZE, 'mou');
        if (isset($r['error'])) { flash('error', $r['error']); redirect($back); }
        db_insert('INSERT INTO mou_files (mou_id, original_name, stored_name, mime, size, is_public, uploaded_by) VALUES (?,?,?,?,?,?,?)',
            [$mouId, $r['name'], $r['stored'], $r['mime'], $r['size'], isset($_POST['is_public']) ? 1 : 0, $_SESSION['user']['id']]);
        log_activity('file_upload', 'mou#' . $mouId, $r['name']);
        flash('success', 'อัปโหลดไฟล์เรียบร้อยแล้ว');
        redirect($back);

    case 'delete_file':
        $f = db_one('SELECT * FROM mou_files WHERE id = ? AND mou_id = ?', [$id, $mouId]);
        if ($f) {
            db_exec('DELETE FROM mou_files WHERE id = ?', [$id]);
            delete_upload('mou', $f['stored_name']);
            log_activity('file_delete', 'mou#' . $mouId, $f['original_name']);
            flash('success', 'ลบไฟล์เรียบร้อยแล้ว');
        } else {
            flash('error', 'ไม่พบไฟล์ที่ต้องการลบ');
        }
        redirect($back);

    case 'toggle_file':
        if (db_exec('UPDATE mou_files SET is_public = 1 - is_public WHERE id = ? AND mou_id = ?', [$id, $mouId]) > 0) {
            log_activity('file_toggle', 'mou#' . $mouId, 'file#' . $id);
            flash('success', 'เปลี่ยนการเผยแพร่ไฟล์แล้ว');
        } else {
            flash('error', 'ไม่พบไฟล์');
        }
        redirect($back);

    /* ---------- กิจกรรมภายใต้ MOU ---------- */
    case 'add_activity':
        $title = mb_substr(post('title'), 0, 300);
        $date  = parse_thai_input(post('activity_date'));
        if ($title === '' || !$date) { flash('error', 'กรุณากรอกชื่อกิจกรรมและวันที่'); redirect($back . '#activities'); }
        $img = null;   // รูปกิจกรรม (ไม่บังคับ)
        if (($af = normalize_files($_FILES['image'] ?? null))) {
            $r = save_images([$af[0]], 'activities', 1);
            if ($r['errors']) { flash('error', 'รูปกิจกรรม: ' . implode(' ', $r['errors'])); redirect($back . '#activities'); }
            $img = $r['saved'][0]['stored'] ?? null;
        }
        db_insert('INSERT INTO mou_activities (mou_id, title, activity_date, result, participants, image) VALUES (?,?,?,?,?,?)',
            [$mouId, $title, $date, post('result') ?: null, max(0, (int) post('participants')), $img]);
        log_activity('activity_add', 'mou#' . $mouId, $title);
        flash('success', 'เพิ่มกิจกรรมเรียบร้อยแล้ว');
        redirect($back . '#activities');

    case 'delete_activity':
        $a = db_one('SELECT title, image FROM mou_activities WHERE id = ? AND mou_id = ?', [$id, $mouId]);
        if ($a) {
            db_exec('DELETE FROM mou_activities WHERE id = ? AND mou_id = ?', [$id, $mouId]);
            if ($a['image']) { delete_upload('activities', $a['image']); }
            log_activity('activity_delete', 'mou#' . $mouId, $a['title']);
            flash('success', 'ลบกิจกรรมเรียบร้อยแล้ว');
        } else {
            flash('error', 'ไม่พบกิจกรรมที่ต้องการลบ');
        }
        redirect($back . '#activities');

    /* ---------- รูปภาพประกอบรายละเอียด (v2) ---------- */
    case 'upload_images':
        $before = count(mou_images_of($mouId));
        $errs = mou_images_add($mouId, $_FILES['images'] ?? []);
        $added = count(mou_images_of($mouId)) - $before;
        if ($added > 0 && !$errs) { flash('success', 'อัปโหลดรูปภาพ ' . $added . ' รูปเรียบร้อยแล้ว'); }
        elseif ($added > 0) { flash('warning', 'อัปโหลดได้ ' . $added . ' รูป แต่มีบางไฟล์ไม่สำเร็จ: ' . implode(' | ', $errs)); }
        else { flash('error', $errs ? implode(' | ', $errs) : 'กรุณาเลือกรูปภาพ'); }
        redirect($back . '#gallery');

    case 'delete_image':
        $img = has_mou_images() ? db_one('SELECT * FROM mou_images WHERE id = ? AND mou_id = ?', [$id, $mouId]) : null;
        if ($img) {
            db_exec('DELETE FROM mou_images WHERE id = ?', [$id]);
            delete_upload('mou_images', $img['stored_name']);
            if ($img['is_cover']) {   // ลบภาพปก → ใช้รูปถัดไปเป็นปกแทน
                db_exec('UPDATE mou_images SET is_cover = 1 WHERE mou_id = ? ORDER BY sort_order, id LIMIT 1', [$mouId]);
            }
            log_activity('image_delete', 'mou#' . $mouId, 'image#' . $id);
            flash('success', 'ลบรูปภาพเรียบร้อยแล้ว');
        } else {
            flash('error', 'ไม่พบรูปภาพที่ต้องการลบ');
        }
        redirect($back . '#gallery');

    case 'set_cover':
        if (has_mou_images() && db_val('SELECT id FROM mou_images WHERE id = ? AND mou_id = ?', [$id, $mouId])) {
            db_exec('UPDATE mou_images SET is_cover = (id = ?) WHERE mou_id = ?', [$id, $mouId]);
            log_activity('image_cover', 'mou#' . $mouId, 'image#' . $id);
            flash('success', 'ตั้งเป็นภาพปกเรียบร้อยแล้ว');
        }
        redirect($back . '#gallery');

    case 'save_images':   // บันทึกคำบรรยาย + ลำดับของทุกรูปในครั้งเดียว
        if (has_mou_images()) {
            $captions = is_array($_POST['caption'] ?? null) ? $_POST['caption'] : [];
            $sorts    = is_array($_POST['sort'] ?? null) ? $_POST['sort'] : [];
            foreach ($captions as $imgId => $cap) {
                $ord = max(-32768, min(32767, (int) ($sorts[$imgId] ?? 0)));   // ช่วงของคอลัมน์ SMALLINT
                db_exec('UPDATE mou_images SET caption = ?, sort_order = ? WHERE id = ? AND mou_id = ?',
                    [mb_substr(trim(is_string($cap) ? $cap : ''), 0, 255) ?: null, $ord, (int) $imgId, $mouId]);
            }
            log_activity('image_update', 'mou#' . $mouId, 'แก้คำบรรยาย/ลำดับรูป');
            flash('success', 'บันทึกคำบรรยายและลำดับรูปแล้ว');
        }
        redirect($back . '#gallery');
}
redirect('admin/mou_list.php');
