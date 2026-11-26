<?php
/**
 * public/robots.php — robots.txt (เรียกผ่าน /robots.txt ด้วย RewriteRule ใน .htaccess)
 * ให้ค้นหาได้เฉพาะหน้าสาธารณะ ปิดหลังบ้าน และบอกที่อยู่ sitemap
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=86400');
$base = (string) parse_url(BASE_URL, PHP_URL_PATH);
$base = rtrim($base, '/');
echo "User-agent: *\n"
   . "Disallow: {$base}/admin/\n"
   . "Disallow: {$base}/public/download.php\n"
   . "Disallow: {$base}/download/\n"
   . "Allow: {$base}/\n\n"
   . 'Sitemap: ' . url(USE_PRETTY_URLS ? 'sitemap.xml' : 'public/sitemap.php') . "\n";
