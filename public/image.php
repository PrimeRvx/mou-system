<?php
/**
 * public/image.php — ส่งรูปภาพจาก uploads/ ผ่าน PHP (ไม่เปิดโฟลเดอร์ให้เข้าตรง)
 * ใช้: image.php?d=<โฟลเดอร์>&f=<32hex>.jpg
 *   - logos, news            : รูปสาธารณะ
 *   - mou_images, activities : รูปประกอบ MOU — แสดงสาธารณะเฉพาะ MOU ที่เผยแพร่แล้ว
 *                              (ฉบับร่าง/ไม่เผยแพร่ เห็นได้เฉพาะเจ้าหน้าที่ที่ login)
 */
require_once __DIR__ . '/../includes/functions.php';

$dir  = $_GET['d'] ?? '';
$file = $_GET['f'] ?? '';
$ok   = in_array($dir, ['news', 'logos', 'activities', 'mou_images'], true)
     && is_string($file) && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $file);
$path = $ok ? UPLOAD_PATH . '/' . $dir . '/' . $file : '';

if (!$ok || !is_file($path)) {
    http_response_code(404);
    exit;
}

$public = true;
if ($dir === 'mou_images' || $dir === 'activities') {
    $sql = $dir === 'mou_images'
        ? 'SELECT m.is_public, m.status, m.deleted_at FROM mou_images i JOIN mou m ON m.id = i.mou_id WHERE i.stored_name = ?'
        : 'SELECT m.is_public, m.status, m.deleted_at FROM mou_activities a JOIN mou m ON m.id = a.mou_id WHERE a.image = ?';
    try { $m = db_one($sql, [$file]); } catch (Throwable $e) { $m = null; }
    $public = $m && (int) $m['is_public'] === 1 && $m['deleted_at'] === null && in_array($m['status'], ['active', 'expiring', 'expired'], true);
    if (!$public) {
        require_once __DIR__ . '/../includes/auth.php';   // เปิด session เฉพาะกรณีต้องตรวจสิทธิ์เจ้าหน้าที่
        if (!$m || !session_is_valid() || !can('dashboard')) {
            http_response_code(404);
            exit;
        }
    }
}

$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
$ext   = pathinfo($file, PATHINFO_EXTENSION);
$etag  = '"' . md5($file . filemtime($path)) . '"';
header('Content-Type: ' . $types[$ext]);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: ' . ($public ? 'public, max-age=2592000, immutable' : 'private, max-age=600'));
header('ETag: ' . $etag);
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
header('Content-Length: ' . filesize($path));
readfile($path);
