<?php
/** admin/logout.php — ออกจากระบบ (รับเฉพาะ POST + CSRF กันเว็บอื่นแอบสั่งให้ออกจากระบบ) */
require_once __DIR__ . '/../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    redirect('admin/');
}
logout_user(true);
session_start();
flash('success', 'ออกจากระบบเรียบร้อยแล้ว');
redirect('admin/login.php');
