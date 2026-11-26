<?php
/**
 * tools/check_release.php — ตรวจโปรเจกต์ก่อนออก Release / ก่อนส่งงาน (รันจาก Command Prompt เท่านั้น)
 *
 *   C:\xampp\php\php.exe C:\xampp\htdocs\mou-system\tools\check_release.php
 *
 * ตรวจ 4 อย่าง แล้วสรุปผล (exit code 0 = ผ่าน, 1 = มีปัญหา):
 *   1) PHP Syntax ทุกไฟล์ (php -l)
 *   2) ทุก <form action="xxx.php"> และลิงก์ href="xxx.php" ในโฟลเดอร์ admin/ public/ ชี้ไปไฟล์ที่มีอยู่จริง
 *      (ปัญหาแบบ admin/mou_actions.php หายไปใน v2.0.0 จะถูกจับได้ที่ข้อนี้)
 *   3) ไม่มีไฟล์บีบอัด/สำรอง (.zip .sql.gz .bak ฯลฯ) หรือโฟลเดอร์ "Claude outputs" อยู่ในโฟลเดอร์เว็บ
 *   4) ค่าความปลอดภัยใน config.php (รหัสผ่านฐานข้อมูลว่าง / ใช้ root)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root   = dirname(__DIR__);
$php    = PHP_BINARY;
$errors = 0;
$warns  = 0;
$say    = function (string $level, string $msg) use (&$errors, &$warns): void {
    if ($level === 'FAIL') { $errors++; }
    if ($level === 'WARN') { $warns++; }
    echo str_pad("[$level]", 7) . ' ' . $msg . PHP_EOL;
};

$skipDir = fn(string $p) => (bool) preg_match('#[\\\\/](\.git|assets[\\\\/]vendor|uploads|node_modules)([\\\\/]|$)#', $p);
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && !$skipDir($f->getPathname())) {
        $files[] = $f->getPathname();
    }
}
$rel = fn(string $p) => str_replace('\\', '/', substr($p, strlen($root) + 1));

// 1) PHP Syntax
echo "== 1) PHP Syntax" . PHP_EOL;
$phpFiles = array_values(array_filter($files, fn($p) => substr($p, -4) === '.php'));
$bad = 0;
foreach ($phpFiles as $p) {
    $out = [];
    exec(escapeshellarg($php) . ' -l ' . escapeshellarg($p) . ' 2>&1', $out, $code);
    if ($code !== 0) { $bad++; $say('FAIL', $rel($p) . ': ' . trim(implode(' ', $out))); }
}
if (!$bad) { $say('PASS', count($phpFiles) . ' ไฟล์ ไม่มี syntax error'); }

// 2) action= / href= ที่ชี้ไปไฟล์ .php ต้องมีอยู่จริง
echo "== 2) ลิงก์และฟอร์มชี้ไปไฟล์ที่มีอยู่" . PHP_EOL;
$missing = 0;
foreach ($phpFiles as $p) {
    $r = $rel($p);
    if (!preg_match('#^(admin|public|includes)/#', $r)) { continue; }
    $src = (string) file_get_contents($p);
    // เฉพาะค่าที่เป็นข้อความตรง ๆ เช่น action="mou_actions.php" หรือ url('admin/users.php')
    preg_match_all('#(?:action|href)="([A-Za-z0-9_\-/]+\.php)(?:[?\#][^"]*)?"#', $src, $m1);
    preg_match_all('#url\(\'([A-Za-z0-9_\-/]+\.php)(?:[?\#][^\']*)?\'\)#', $src, $m2);
    foreach (array_unique($m1[1]) as $t) {
        $base = strpos($r, 'includes/') === 0 ? $root : dirname($p);   // ลิงก์ในไฟล์ include อ้างจากหน้าที่เรียกใช้ — ตรวจแบบเทียบกับราก
        if (!is_file($base . '/' . $t) && !is_file($root . '/' . $t) && !is_file($root . '/admin/' . $t)) {
            $missing++; $say('FAIL', "$r → \"$t\" ไม่มีไฟล์นี้");
        }
    }
    foreach (array_unique($m2[1]) as $t) {
        if (!is_file($root . '/' . $t)) { $missing++; $say('FAIL', "$r → url('$t') ไม่มีไฟล์นี้"); }
    }
}
if (!$missing) { $say('PASS', 'ทุกฟอร์ม/ลิงก์ .php ชี้ไปไฟล์ที่มีอยู่'); }

// 3) ไฟล์ที่ไม่ควรอยู่ในโฟลเดอร์เว็บ
echo "== 3) ไฟล์สำรอง/บีบอัดในโฟลเดอร์เว็บ" . PHP_EOL;
$leak = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $f) {
    $r = $rel($f->getPathname());
    if (strpos($r, '.git/') === 0) { continue; }
    if (preg_match('#\.(zip|rar|7z|gz|tar|bak)$#i', $r) || stripos($r, 'Claude outputs/') === 0) {
        $leak++; $say('FAIL', "$r — ย้ายออกไปนอก htdocs");
    }
}
if (!$leak) { $say('PASS', 'ไม่พบไฟล์สำรอง/บีบอัดในโฟลเดอร์เว็บ'); }

// 4) ค่าความปลอดภัยใน config
echo "== 4) config.php" . PHP_EOL;
require $root . '/config/config.php';
if (DB_PASS === '') { $say('WARN', 'DB_PASS ว่าง — ตั้งรหัสผ่านฐานข้อมูลก่อนใช้งานจริง (ดู database/setup_db_user.sql)'); }
if (DB_USER === 'root') { $say('WARN', 'DB_USER = root — ควรใช้ผู้ใช้เฉพาะระบบที่มีสิทธิ์แค่ฐานข้อมูลนี้'); }
if (PHP_VERSION_ID < 80100) { $say('WARN', 'PHP ' . PHP_VERSION . ' หมดระยะ security support แล้ว — อัปเกรดเป็น 8.2 ขึ้นไป'); }
if (DB_PASS !== '' && DB_USER !== 'root' && PHP_VERSION_ID >= 80100) { $say('PASS', 'ค่าฐานข้อมูลและเวอร์ชัน PHP เหมาะสม'); }

echo PHP_EOL . "สรุป: FAIL $errors · WARN $warns" . PHP_EOL;
exit($errors > 0 ? 1 : 0);
