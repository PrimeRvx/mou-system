-- =====================================================================
-- migration_v4_integrity.sql — ปรับความถูกต้องของฐานข้อมูล (v2.0.1)
-- รันครั้งเดียวใน phpMyAdmin: เลือกฐานข้อมูล mou_bangna → แท็บ SQL/Import → เลือกไฟล์นี้ → Go
-- รันซ้ำได้โดยไม่เสียหาย (ทุกคำสั่งตรวจก่อนทำ) · ควรกด "สำรองข้อมูลระบบ" ในหน้าตั้งค่าก่อนรัน
--
-- ทำอะไรบ้าง
--   1) ทุกตาราง/คอลัมน์ใช้ collation เดียวกัน (utf8mb4_unicode_ci) กัน error "Illegal mix of collations"
--   2) proposals.converted_mou_id มี Foreign Key ไป mou(id) — ลบ MOU แล้วข้อเสนอไม่ชี้ไป MOU ที่ไม่มีอยู่
--   3) mou.is_public ค่าเริ่มต้น = 0 (ไม่เผยแพร่) ให้ตรงกับฟอร์มเพิ่ม MOU
--   4) ลบ FULLTEXT ft_mou ที่ระบบไม่ได้ใช้ (ค้นหาใช้ LIKE และ FULLTEXT ปกติตัดคำภาษาไทยไม่ได้)
-- ใช้กับ MariaDB 10.3+ (ที่มากับ XAMPP) — คำสั่ง IF EXISTS / IF NOT EXISTS เป็นไวยากรณ์ของ MariaDB
-- =====================================================================
SET NAMES utf8mb4;

-- 1) collation เดียวกันทั้งฐานข้อมูล
ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `users`            CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `partners`         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `programs`         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `mou`              CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `mou_programs`     CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `mou_categories`   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `mou_category_map` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `mou_benefits`     CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `mou_files`        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `mou_renewals`     CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `mou_activities`   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `mou_images`       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `news`             CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `news_images`      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `proposals`        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `settings`         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `activity_logs`    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `notifications`    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `rate_limits`      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2) Foreign Key ของข้อเสนอที่แปลงเป็น MOU แล้ว (ล้างค่าที่ชี้ไป MOU ที่ไม่มีอยู่ก่อน)
UPDATE `proposals` p LEFT JOIN `mou` m ON m.id = p.converted_mou_id
   SET p.converted_mou_id = NULL
 WHERE p.converted_mou_id IS NOT NULL AND m.id IS NULL;
ALTER TABLE `proposals`
  ADD CONSTRAINT `fk_prop_mou` FOREIGN KEY IF NOT EXISTS (`converted_mou_id`) REFERENCES `mou` (`id`) ON DELETE SET NULL;

-- 3) ค่าเริ่มต้นการเผยแพร่ MOU = ไม่เผยแพร่ (ตรงกับฟอร์ม)
ALTER TABLE `mou` ALTER COLUMN `is_public` SET DEFAULT 0;

-- 4) ลบ FULLTEXT ที่ไม่ได้ใช้
ALTER TABLE `mou` DROP INDEX IF EXISTS `ft_mou`;
