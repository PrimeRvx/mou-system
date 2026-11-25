<?php
/**
 * admin/backup.php — ดาวน์โหลดไฟล์สำรองข้อมูลทั้งระบบ (เฉพาะผู้ดูแลระบบ, POST + CSRF)
 * ได้ไฟล์ .zip 1 ไฟล์: database.sql (โครงสร้าง + ข้อมูลทุกตาราง) และโฟลเดอร์ uploads/ (ไฟล์แนบ รูปภาพ โลโก้)
 * กู้คืน: นำเข้า database.sql ด้วย phpMyAdmin ลงฐานข้อมูลว่าง แล้วคัดลอก uploads/ กลับที่เดิม
 * ไฟล์สำรองมีข้อมูลส่วนบุคคลและ hash รหัสผ่าน — เก็บในที่ปลอดภัย ห้ามวางในโฟลเดอร์เว็บ
 */
require_once __DIR__ . '/../includes/auth.php';
require_permission('setting');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/settings.php');
}
csrf_require();
if (!class_exists('ZipArchive')) {
    flash('error', 'สำรองข้อมูลไม่ได้: ต้องเปิดส่วนขยาย zip ของ PHP ใน php.ini ก่อน');
    redirect('admin/settings.php');
}
@set_time_limit(600);

$sqlFile = tempnam(sys_get_temp_dir(), 'mousql');
$zipFile = tempnam(sys_get_temp_dir(), 'mouzip');
try {
    // ---------- 1) dump ฐานข้อมูลลงไฟล์ชั่วคราว ----------
    $pdo = db();
    $fh  = fopen($sqlFile, 'wb');
    fwrite($fh, "-- สำรองข้อมูลระบบ MOU | ฐานข้อมูล " . DB_NAME . ' | ' . date('Y-m-d H:i:s') . "\n"
              . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
    foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $t) {
        $table  = '`' . str_replace('`', '``', $t[0]) . '`';
        $create = $pdo->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM);
        fwrite($fh, "DROP TABLE IF EXISTS $table;\n" . $create[1] . ";\n\n");
        $stmt  = $pdo->query("SELECT * FROM $table");
        $batch = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $batch[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)) . ')';
            if (count($batch) >= 200) {
                fwrite($fh, "INSERT INTO $table VALUES\n" . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        }
        if ($batch) {
            fwrite($fh, "INSERT INTO $table VALUES\n" . implode(",\n", $batch) . ";\n");
        }
        fwrite($fh, "\n");
    }
    fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n");
    fclose($fh);

    // ---------- 2) รวมเป็น zip พร้อมโฟลเดอร์ uploads ----------
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('สร้างไฟล์ zip ไม่ได้');
    }
    $zip->addFile($sqlFile, 'database.sql');
    $count = 0;
    $base  = realpath(UPLOAD_PATH);
    if ($base !== false) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile() || $f->getFilename() === '.php-error.log') {
                continue;
            }
            $zip->addFile($f->getPathname(), 'uploads/' . str_replace('\\', '/', substr($f->getPathname(), strlen($base) + 1)));
            $count++;
        }
    }
    $zip->close();
} catch (Throwable $ex) {
    @unlink($sqlFile);
    @unlink($zipFile);
    error_log('backup: ' . $ex->getMessage());
    flash('error', 'สำรองข้อมูลไม่สำเร็จ กรุณาลองใหม่ (รายละเอียดอยู่ในไฟล์บันทึกข้อผิดพลาด)');
    redirect('admin/settings.php');
}
@unlink($sqlFile);

setting_save('last_backup_at', date('Y-m-d H:i:s'));
log_activity('backup_download', 'system', 'ดาวน์โหลดไฟล์สำรองข้อมูล (ไฟล์อัปโหลด ' . $count . ' ไฟล์)');

while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="mou-backup-' . date('Ymd-Hi') . '.zip"');
header('Content-Length: ' . filesize($zipFile));
header('Cache-Control: no-store');
readfile($zipFile);
@unlink($zipFile);
exit;
