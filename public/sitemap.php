<?php
/**
 * public/sitemap.php — แผนผังเว็บสำหรับเครื่องมือค้นหา (เรียกผ่าน /sitemap.xml ด้วย RewriteRule ใน .htaccess)
 * รวมเฉพาะหน้าที่เผยแพร่แล้ว: หน้าหลัก, รายการ MOU, MOU ที่เผยแพร่, ข่าวที่เผยแพร่, หน้าติดต่อ
 */
require_once __DIR__ . '/../models/mou_model.php';

$urls = [
    [route('home'), null, '1.0'],
    [route('mou'), null, '0.9'],
    [route('news'), null, '0.7'],
    [route('contact'), null, '0.5'],
];
foreach (db_all('SELECT m.id, COALESCE(m.updated_at, m.created_at) AS lastmod FROM mou m WHERE ' . PUBLIC_MOU_WHERE . ' ORDER BY m.id') as $r) {
    $urls[] = [route('mou_detail', (int) $r['id']), $r['lastmod'], '0.8'];
}
foreach (db_all('SELECT id, published_at AS lastmod FROM news WHERE is_published = 1 AND deleted_at IS NULL AND published_at <= NOW() ORDER BY id') as $r) {
    $urls[] = [route('news_detail', (int) $r['id']), $r['lastmod'], '0.6'];
}

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$x = fn($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $mod, $prio]) {
    echo '  <url><loc>' . $x($loc) . '</loc>';
    if ($mod && ($d = to_date($mod))) {
        echo '<lastmod>' . $d->format('Y-m-d') . '</lastmod>';
    }
    echo '<priority>' . $prio . "</priority></url>\n";
}
echo "</urlset>\n";
