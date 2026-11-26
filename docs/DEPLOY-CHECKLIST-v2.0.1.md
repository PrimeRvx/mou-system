# ขั้นตอนที่ต้องทำเองบนเครื่องเซิร์ฟเวอร์ (v2.0.1)

โค้ดแก้ไขแล้ว ส่วนนี้คือการตั้งค่าเครื่องที่ต้องทำด้วยมือ ทำตามลำดับ

## 1. ฐานข้อมูล (ทำก่อน)

1. หลังบ้าน → ตั้งค่าเว็บ → กด **สำรองข้อมูลระบบ** เก็บไฟล์ไว้นอกเครื่อง
2. phpMyAdmin → เลือก `mou_bangna` → Import → `database/migration_v4_integrity.sql` → Go
3. เปิด `database/setup_db_user.sql` แก้ `CHANGE_ME_STRONG_PASSWORD` เป็นรหัสผ่านใหม่ แล้วรันใน phpMyAdmin
4. แก้ `config/config.php`: `DB_USER` = `mou_app`, `DB_PASS` = รหัสผ่านข้อ 3
5. แก้ `tools/backup.bat`: `DB_USER` และ `DB_PASS` ให้ตรงกัน
6. ตั้งรหัสผ่าน root ใน phpMyAdmin (User accounts → root → Change password) แล้วแก้ `C:\xampp\phpMyAdmin\config.inc.php`
7. ลบค่ารหัสผ่านออกจาก `setup_db_user.sql` (หรือลบไฟล์ที่แก้แล้ว)

## 2. ไฟล์ที่ไม่ควรอยู่ในเว็บ

- ย้ายโฟลเดอร์ `Claude outputs` ออกจาก `C:\xampp\htdocs\mou-system` ไปไว้ที่อื่น เช่น `C:\mou-backups`
  (ตอนนี้ `.htaccess` ปิดไว้แล้ว แต่ไม่ควรเก็บไว้ในเว็บ)
- ลบไฟล์ `admin/mou_action.php` (ไฟล์เก่าที่ปิดใช้งานแล้ว)
- ลบไฟล์โลโก้ที่ซ้ำ `uploads/logos/2db8220ed1311df8e7346fcd7d158a3a.png` ถ้าไม่มีหน่วยงานใดใช้

## 3. บัญชีผู้ใช้

- เปลี่ยนรหัสผ่าน admin และ staff01 เป็นวลียาว 12 ตัวขึ้นไป (เมนูผู้ใช้งาน หรือ เปลี่ยนรหัสผ่าน)
- ตั้งรหัสผ่านให้ viewer01

## 4. ก่อนเปิดใช้บนอินเทอร์เน็ต

- อัปเกรด XAMPP เป็นรุ่นที่มี PHP 8.2 ขึ้นไป (ย้ายโฟลเดอร์ `mou-system` และ export/import ฐานข้อมูล)
- `C:\xampp\php\php.ini`: `expose_php = Off`
- `C:\xampp\apache\conf\httpd.conf`: เปิด `LoadModule deflate_module` และ `LoadModule expires_module`,
  เพิ่ม `ServerTokens Prod` และ `ServerSignature Off`
- เปิด HTTPS (ใบรับรองจริง เช่น Let's Encrypt) + redirect HTTP→HTTPS + header HSTS
- `config/config.php`: ตั้ง `BASE_URL_CONFIG` เป็นโดเมนจริง และ `APP_ENV_MODE` = `production` ถ้าอยู่หลัง reverse proxy
- `robots.txt` และ `sitemap.xml` มีผลกับเครื่องมือค้นหาเมื่อเว็บอยู่ที่รากโดเมนเท่านั้น
  (ถ้าติดตั้งใน `/mou-system` ให้ส่ง sitemap ผ่าน Google Search Console แทน)

## 5. ตรวจก่อนส่งงาน / ออก Release ทุกครั้ง

```
C:\xampp\php\php.exe C:\xampp\htdocs\mou-system\tools\check_release.php
```

ต้องไม่มีบรรทัด `[FAIL]` (บรรทัด `[WARN]` จะหายไปเมื่อทำข้อ 1 และ 4 ครบ)
