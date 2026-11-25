<?php
/**
 * cron/notify_expiring.php — สั่งแจ้งเตือน MOU ใกล้หมดอายุด้วยตนเอง / ตามเวลา (ไม่บังคับ)
 *
 * ปกติระบบทำงานนี้เองวันละครั้งเมื่อมีคนเปิดเว็บครั้งแรกของวัน (ดู daily_tasks() ใน includes/functions.php)
 * ไฟล์นี้มีไว้สำหรับกรณีต้องการให้แจ้งเตือนตรงเวลาแม้ไม่มีใครเปิดเว็บ — ตั้ง Task Scheduler ให้รัน:
 *
 *   C:\xampp\php\php.exe C:\xampp\htdocs\mou-system\cron\notify_expiring.php
 *
 * ทำอะไรบ้าง: คำนวณสถานะ MOU ใหม่ → สร้างแจ้งเตือนที่รูประฆังหลังบ้าน (MOU ละครั้งต่อ 30 วัน)
 *             → ส่งอีเมลสรุปถ้าตั้ง "อีเมลรับแจ้งเตือน" ในหน้าตั้งค่าเว็บ และ PHP ส่งอีเมลได้ (ตั้ง SMTP แล้ว)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/functions.php';

setting_save('daily_tasks_on', date('Y-m-d'));   // กันไม่ให้หน้าเว็บทำงานเดียวกันซ้ำในวันนี้
refresh_mou_status(true);
$r = daily_tasks();
echo date('Y-m-d H:i') . ' | แจ้งเตือนใหม่ ' . $r['new'] . " รายการ\n";

$sent = $r['mail'];
if ($sent === true) {
    echo 'ส่งอีเมลสรุปถึง ' . setting('notify_email') . " แล้ว\n";
} elseif ($sent === false) {
    echo "ส่งอีเมลไม่สำเร็จ (ตรวจการตั้งค่า SMTP/sendmail ของ PHP) — แจ้งเตือนในระบบยังถูกสร้างตามปกติ\n";
}
