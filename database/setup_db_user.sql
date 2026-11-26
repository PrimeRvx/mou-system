-- =====================================================================
-- setup_db_user.sql — สร้างผู้ใช้ฐานข้อมูลเฉพาะระบบ MOU (แทนการใช้ root ที่ไม่มีรหัสผ่าน)
--
-- วิธีใช้ (ทำครั้งเดียว ตามลำดับ):
--   1) แก้คำว่า  CHANGE_ME_STRONG_PASSWORD  ด้านล่างเป็นรหัสผ่านใหม่ (ยาว 16 ตัวขึ้นไป ห้ามมี ')
--   2) phpMyAdmin → แท็บ SQL → วางทั้งไฟล์ → Go
--   3) แก้ config/config.php:  DB_USER = 'mou_app'  และ  DB_PASS = รหัสผ่านเดียวกับข้อ 1
--   4) แก้ tools/backup.bat ให้ตรงกัน: set "DB_USER=mou_app" และ set "DB_PASS=รหัสผ่านเดียวกัน"
--   5) เปิดเว็บและเข้าสู่ระบบหลังบ้านเพื่อตรวจว่าใช้งานได้
--   6) ตั้งรหัสผ่านให้ root ด้วย (phpMyAdmin → User accounts → root → Edit privileges → Change password)
--      แล้วแก้ C:\xampp\phpMyAdmin\config.inc.php ให้ใช้รหัสผ่านใหม่ (หรือเปลี่ยน auth_type เป็น 'cookie')
--
-- อย่าเก็บไฟล์นี้ที่ใส่รหัสผ่านจริงแล้วไว้ในโฟลเดอร์เว็บ หรือ commit ขึ้น GitHub
-- การรัน migration (ALTER TABLE) ในอนาคตให้ใช้ root เพราะ mou_app ไม่มีสิทธิ์แก้โครงสร้างตาราง
-- =====================================================================
CREATE USER IF NOT EXISTS 'mou_app'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';
ALTER USER 'mou_app'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';
-- สิทธิ์เท่าที่เว็บใช้: อ่าน/เพิ่ม/แก้/ลบข้อมูล + LOCK TABLES และ SHOW VIEW สำหรับปุ่มสำรองข้อมูลและ tools/backup.bat
GRANT SELECT, INSERT, UPDATE, DELETE, LOCK TABLES, SHOW VIEW ON `mou_bangna`.* TO 'mou_app'@'localhost';
FLUSH PRIVILEGES;
