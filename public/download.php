<?php
/**
 * public/download.php — ดาวน์โหลดไฟล์ MOU ผ่าน PHP (ตรวจสิทธิ์ก่อนส่งไฟล์ ไม่ลิงก์ตรงไป uploads/)
 *  - ผู้เข้าชมทั่วไป: ต้องเป็นไฟล์ is_public=1 และ MOU ต้องเผยแพร่และไม่ถูกลบ
 *  - เจ้าหน้าที่ที่ login (สิทธิ์ mou): ดาวน์โหลดได้ทุกไฟล์
 */
require_once __DIR__ . '/../includes/auth.php';

$id = (int) get('id');
$f  = db_one('SELECT f.*, m.is_public AS mou_public, m.status AS mou_status, m.deleted_at AS mou_deleted
                FROM mou_files f JOIN mou m ON m.id = f.mou_id WHERE f.id = ?', [$id]);

$staff = session_is_valid() && can('mou');
$allowed = $f && ($staff || (
    (int) $f['is_public'] === 1 && (int) $f['mou_public'] === 1 && $f['mou_deleted'] === null
    && in_array($f['mou_status'], ['active', 'expiring', 'expired'], true)));

$path = $f ? UPLOAD_PATH . '/mou/' . basename($f['stored_name']) : '';
if (!$allowed || !is_file($path)) {
    http_response_code(404);
    exit('ไม่พบไฟล์ หรือไม่มีสิทธิ์ดาวน์โหลด');
}

// นับจำนวนดาวน์โหลด (ไม่นับเจ้าหน้าที่ที่เปิดดูเอง)
if (!$staff) {
    db_exec('UPDATE mou_files SET download_count = download_count + 1 WHERE id = ?', [$id]);
}

$name = preg_replace('/[\r\n"]+/', '', $f['original_name']);
header('Content-Type: ' . $f['mime']);
header('Content-Length: ' . filesize($path));
header("Content-Disposition: attachment; filename=\"" . rawurlencode($name) . "\"; filename*=UTF-8''" . rawurlencode($name));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0');
while (ob_get_level()) { ob_end_clean(); }
readfile($path);
exit;
