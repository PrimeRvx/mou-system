<?php
/** admin/proposal_file.php — ดาวน์โหลดไฟล์แนบของข้อเสนอ (เฉพาะเจ้าหน้าที่ที่ login) */
require_once __DIR__ . '/../includes/auth.php';
require_permission('proposal');
$p = db_one('SELECT attachment, attachment_name FROM proposals WHERE id = ?', [(int) get('id')]);
$path = $p && $p['attachment'] ? UPLOAD_PATH . '/proposals/' . basename($p['attachment']) : '';
if (!$path || !is_file($path)) { http_response_code(404); exit('ไม่พบไฟล์'); }
$name = preg_replace('/[\r\n"]+/', '', (string) $p['attachment_name']);
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header("Content-Disposition: attachment; filename=\"" . rawurlencode($name) . "\"; filename*=UTF-8''" . rawurlencode($name));
header('X-Content-Type-Options: nosniff');
while (ob_get_level()) { ob_end_clean(); }
readfile($path);
exit;
