-- =====================================================================
-- migration_v2_images.sql — เพิ่มระบบ "รูปภาพประกอบรายละเอียด MOU" (อัลบั้มภาพ)
-- ใช้กับฐานข้อมูลที่ติดตั้งไปแล้ว (ไม่ลบข้อมูลเดิม)
-- วิธีใช้: phpMyAdmin → เลือกฐาน mou_bangna → แท็บ SQL/Import → เลือกไฟล์นี้ → Go
-- (ถ้าติดตั้งใหม่ด้วย schema.sql เวอร์ชันนี้แล้ว ไม่ต้องรันไฟล์นี้)
-- =====================================================================
USE `mou_bangna`;

CREATE TABLE IF NOT EXISTS `mou_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mou_id` INT UNSIGNED NOT NULL,
  `stored_name` VARCHAR(100) NOT NULL COMMENT 'ชื่อไฟล์สุ่มใน uploads/mou_images/',
  `caption` VARCHAR(255) DEFAULT NULL COMMENT 'คำบรรยายภาพ',
  `width` SMALLINT UNSIGNED DEFAULT NULL,
  `height` SMALLINT UNSIGNED DEFAULT NULL,
  `size` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_cover` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = ภาพปก (แสดงบนการ์ดและหัวหน้ารายละเอียด)',
  `sort_order` SMALLINT NOT NULL DEFAULT 0,
  `uploaded_by` INT UNSIGNED DEFAULT NULL,
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_img_stored` (`stored_name`),
  KEY `idx_img_mou` (`mou_id`, `sort_order`),
  CONSTRAINT `fk_img_mou` FOREIGN KEY (`mou_id`) REFERENCES `mou` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='รูปภาพประกอบรายละเอียด MOU';
