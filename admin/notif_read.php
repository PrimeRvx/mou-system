<?php
/**
 * admin/notif_read.php — ทำเครื่องหมายแจ้งเตือนว่าอ่านแล้ว (POST + CSRF)
 *   id=<เลขแจ้งเตือน> → อ่านแล้ว แล้วพาไปยังหน้าที่แจ้งเตือนนั้นชี้ไป
 *   all=1            → อ่านแล้วทั้งหมด
 */
require_once __DIR__ . '/../includes/auth.php';
require_permission('proposal');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/');
}
csrf_require();

if (post('all') === '1') {
    db_exec('UPDATE notifications SET is_read = 1 WHERE is_read = 0');
    redirect('admin/');
}
$n = db_one('SELECT id, link FROM notifications WHERE id = ?', [(int) post('id')]);
if ($n) {
    db_exec('UPDATE notifications SET is_read = 1 WHERE id = ?', [$n['id']]);
}
// ไปได้เฉพาะหน้าในหลังบ้านของระบบนี้เท่านั้น
$link = (string) ($n['link'] ?? '');
redirect(preg_match('#^admin/[A-Za-z0-9_.\\-]+\\.php(\\?[A-Za-z0-9_=&\\-]*)?$#', $link) ? $link : 'admin/');
