-- =====================================================================
-- migration_v3_news_images.sql — อัลบั้มรูปภาพประกอบข่าวประชาสัมพันธ์
-- ใช้กับฐานข้อมูลที่ติดตั้งไปแล้ว (ไม่ลบข้อมูลเดิม)
-- วิธีใช้: phpMyAdmin → เลือกฐาน mou_bangna → แท็บ SQL/Import → เลือกไฟล์นี้ → Go
-- (schema.sql เวอร์ชันนี้มีตารางนี้แล้ว — ติดตั้งใหม่ไม่ต้องรันไฟล์นี้)
-- =====================================================================
USE `mou_bangna`;

CREATE TABLE IF NOT EXISTS `news_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `news_id` INT UNSIGNED NOT NULL,
  `stored_name` VARCHAR(100) NOT NULL COMMENT 'ชื่อไฟล์สุ่มใน uploads/news/',
  `caption` VARCHAR(255) DEFAULT NULL COMMENT 'คำบรรยายภาพ',
  `width` SMALLINT UNSIGNED DEFAULT NULL,
  `height` SMALLINT UNSIGNED DEFAULT NULL,
  `size` INT UNSIGNED NOT NULL DEFAULT 0,
  `sort_order` SMALLINT NOT NULL DEFAULT 0,
  `uploaded_by` INT UNSIGNED DEFAULT NULL,
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_news_img_stored` (`stored_name`),
  KEY `idx_news_img` (`news_id`, `sort_order`),
  CONSTRAINT `fk_news_img` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='รูปภาพประกอบข่าว (อัลบั้ม)';
