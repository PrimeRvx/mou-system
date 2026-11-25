<?php
/**
 * admin/ping.php — ต่ออายุเซสชันหลังบ้าน
 * app.js เรียกทุก 5 นาที "เฉพาะเมื่อผู้ใช้ยังพิมพ์/คลิกอยู่" เพื่อไม่ให้หมดเวลาระหว่างกรอกฟอร์มยาว
 * (ถ้าไม่มีการใช้งานเลย เซสชันยังหมดเวลาตาม SESSION_TIMEOUT เหมือนเดิม)
 */
require_once __DIR__ . '/../includes/auth.php';
header('Cache-Control: no-store');
if (!is_logged_in() || time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
    http_response_code(401);
    exit;
}
$_SESSION['last_activity'] = time();
http_response_code(204);
