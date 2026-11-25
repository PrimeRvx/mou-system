-- =====================================================================
-- ฐานข้อมูลบันทึกข้อตกลงความร่วมมือ (MOU) วิทยาลัยพณิชยการบางนา
-- MySQL 5.7+ / MariaDB 10.3+  |  utf8mb4
-- วิธีใช้: นำเข้าผ่าน phpMyAdmin (แท็บ Import) หรือ  mysql -u root < schema.sql
-- ผู้ใช้เริ่มต้น: admin / Admin@1234  (เปลี่ยนรหัสผ่านทันทีหลังติดตั้ง)
-- =====================================================================
CREATE DATABASE IF NOT EXISTS `mou_bangna`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `mou_bangna`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


-- ---------- ผู้ใช้งานระบบหลังบ้าน ----------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'password_hash()',
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `role` ENUM('admin','staff','viewer') NOT NULL DEFAULT 'staff',
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- หน่วยงานคู่ความร่วมมือ ----------
CREATE TABLE IF NOT EXISTS `partners` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `type` ENUM('enterprise','education','government','other') NOT NULL DEFAULT 'enterprise'
    COMMENT 'สถานประกอบการ/สถาบันการศึกษา/หน่วยงานรัฐ/อื่นๆ',
  `logo` VARCHAR(255) DEFAULT NULL,
  `address` TEXT,
  `province` VARCHAR(100) DEFAULT NULL,
  `contact_name` VARCHAR(150) DEFAULT NULL COMMENT 'ข้อมูลภายใน ไม่แสดงสาธารณะ',
  `contact_position` VARCHAR(150) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `website` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_province` (`province`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- สาขาวิชา ----------
CREATE TABLE IF NOT EXISTS `programs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `level` ENUM('ปวช.','ปวส.') NOT NULL DEFAULT 'ปวช.',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- ข้อตกลงความร่วมมือ (MOU) ----------
CREATE TABLE IF NOT EXISTS `mou` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mou_no` VARCHAR(60) NOT NULL COMMENT 'เลขที่ MOU',
  `title` VARCHAR(300) NOT NULL,
  `partner_id` INT UNSIGNED NOT NULL,
  `sign_date` DATE NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `duration_years` DECIMAL(4,1) DEFAULT NULL,
  `signer_college` VARCHAR(200) DEFAULT NULL,
  `signer_partner` VARCHAR(200) DEFAULT NULL,
  `objective` TEXT,
  `summary` TEXT COMMENT 'สาระสำคัญ',
  `scope` TEXT COMMENT 'ขอบเขตความร่วมมือ',
  `budget` DECIMAL(14,2) DEFAULT NULL COMMENT 'ข้อมูลภายใน ห้ามแสดงสาธารณะ',
  `student_benefit_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('draft','active','expiring','expired','cancelled') NOT NULL DEFAULT 'draft',
  `is_public` TINYINT(1) NOT NULL DEFAULT 1,
  `responsible` VARCHAR(150) DEFAULT NULL COMMENT 'ผู้รับผิดชอบภายในวิทยาลัย',
  `note` TEXT COMMENT 'หมายเหตุภายใน',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL COMMENT 'soft delete',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mou_no` (`mou_no`),
  KEY `idx_partner` (`partner_id`),
  KEY `idx_status` (`status`),
  KEY `idx_end_date` (`end_date`),
  KEY `idx_sign_date` (`sign_date`),
  KEY `idx_deleted` (`deleted_at`),
  FULLTEXT KEY `ft_mou` (`title`,`summary`,`objective`),
  CONSTRAINT `fk_mou_partner` FOREIGN KEY (`partner_id`) REFERENCES `partners`(`id`),
  CONSTRAINT `fk_mou_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mou_programs` (
  `mou_id` INT UNSIGNED NOT NULL,
  `program_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`mou_id`,`program_id`),
  CONSTRAINT `fk_mp_mou` FOREIGN KEY (`mou_id`) REFERENCES `mou`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mp_prog` FOREIGN KEY (`program_id`) REFERENCES `programs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mou_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mou_category_map` (
  `mou_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`mou_id`,`category_id`),
  CONSTRAINT `fk_mc_mou` FOREIGN KEY (`mou_id`) REFERENCES `mou`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mc_cat` FOREIGN KEY (`category_id`) REFERENCES `mou_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mou_benefits` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mou_id` INT UNSIGNED NOT NULL,
  `item` VARCHAR(500) NOT NULL,
  `sort_order` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_mou` (`mou_id`),
  CONSTRAINT `fk_mb_mou` FOREIGN KEY (`mou_id`) REFERENCES `mou`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mou_files` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mou_id` INT UNSIGNED NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name` VARCHAR(100) NOT NULL COMMENT 'ชื่อสุ่มบนดิสก์',
  `mime` VARCHAR(100) NOT NULL,
  `size` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_public` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=เผยแพร่สาธารณะ 0=เฉพาะผู้ดูแล',
  `download_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `uploaded_by` INT UNSIGNED DEFAULT NULL,
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stored` (`stored_name`),
  KEY `idx_mou` (`mou_id`),
  CONSTRAINT `fk_mf_mou` FOREIGN KEY (`mou_id`) REFERENCES `mou`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mou_renewals` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mou_id` INT UNSIGNED NOT NULL,
  `old_end_date` DATE NOT NULL,
  `new_end_date` DATE NOT NULL,
  `doc_no` VARCHAR(100) DEFAULT NULL COMMENT 'เลขที่หนังสือต่ออายุ',
  `reason` TEXT,
  `recorded_by` INT UNSIGNED DEFAULT NULL,
  `recorded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mou` (`mou_id`),
  CONSTRAINT `fk_mr_mou` FOREIGN KEY (`mou_id`) REFERENCES `mou`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mou_activities` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mou_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(300) NOT NULL,
  `activity_date` DATE NOT NULL,
  `result` TEXT,
  `participants` INT UNSIGNED NOT NULL DEFAULT 0,
  `image` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mou` (`mou_id`),
  CONSTRAINT `fk_ma_mou` FOREIGN KEY (`mou_id`) REFERENCES `mou`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- รูปภาพประกอบรายละเอียด MOU (อัลบั้ม) — เพิ่มใน v2 ----------
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

-- ---------- ข่าวประชาสัมพันธ์ ----------
CREATE TABLE IF NOT EXISTS `news` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(300) NOT NULL,
  `slug` VARCHAR(300) DEFAULT NULL,
  `excerpt` VARCHAR(500) DEFAULT NULL,
  `content` MEDIUMTEXT,
  `cover_image` VARCHAR(255) DEFAULT NULL,
  `published_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `author_id` INT UNSIGNED DEFAULT NULL,
  `view_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pub` (`is_published`,`published_at`),
  CONSTRAINT `fk_news_user` FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- อัลบั้มรูปภาพประกอบข่าว — เพิ่มใน v2.3 (ฐานเดิม: รัน migration_v3_news_images.sql) ----------
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

-- ---------- ข้อเสนอความร่วมมือจากภายนอก ----------
CREATE TABLE IF NOT EXISTS `proposals` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ref_no` VARCHAR(30) NOT NULL COMMENT 'เลขอ้างอิง เช่น PRP-2569-00001',
  `org_name` VARCHAR(255) NOT NULL,
  `org_type` ENUM('enterprise','education','government','other') NOT NULL DEFAULT 'enterprise',
  `contact_name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `province` VARCHAR(100) DEFAULT NULL,
  `address` TEXT,
  `programs_interest` VARCHAR(500) DEFAULT NULL COMMENT 'รหัสสาขา คั่น comma',
  `coop_type` VARCHAR(300) DEFAULT NULL COMMENT 'รูปแบบความร่วมมือ',
  `details` TEXT NOT NULL,
  `attachment` VARCHAR(100) DEFAULT NULL,
  `attachment_name` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('new','reviewing','approved','rejected') NOT NULL DEFAULT 'new',
  `staff_note` TEXT,
  `reply_note` TEXT COMMENT 'ข้อความตอบกลับผู้เสนอ',
  `converted_mou_id` INT UNSIGNED DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ref` (`ref_no`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- ตั้งค่าเว็บ ----------
CREATE TABLE IF NOT EXISTS `settings` (
  `skey` VARCHAR(80) NOT NULL,
  `svalue` TEXT,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Activity log ----------
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `username` VARCHAR(50) DEFAULT NULL,
  `action` VARCHAR(60) NOT NULL,
  `target` VARCHAR(100) DEFAULT NULL,
  `detail` TEXT,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- แจ้งเตือนในระบบหลังบ้าน ----------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(30) NOT NULL COMMENT 'proposal / expiring ฯลฯ',
  `message` VARCHAR(500) NOT NULL,
  `link` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Rate limit (ใช้ DB เพื่อให้ทำงานบน shared hosting) ----------
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bucket` VARCHAR(60) NOT NULL,
  `ip_hash` CHAR(64) NOT NULL COMMENT 'sha256(ip+salt) เพื่อลดข้อมูลส่วนบุคคล',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lookup` (`bucket`,`ip_hash`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- ข้อมูลตั้งต้นที่ระบบต้องมี (ผู้ดูแล 1 บัญชี, สาขาวิชา, ประเภทความร่วมมือ, ค่าตั้งค่า)
-- ใช้ INSERT IGNORE: นำเข้าไฟล์นี้ซ้ำได้ ไม่ลบและไม่ทับข้อมูลที่มีอยู่
-- ข้อมูลตัวอย่าง (หน่วยงาน/MOU/ข่าวสมมติ) แยกไปอยู่ที่ database/sample_data.sql
-- =====================================================================

-- บัญชีผู้ดูแลเริ่มต้น: admin / Admin@1234  ** ต้องเปลี่ยนรหัสผ่านทันทีหลังเข้าสู่ระบบครั้งแรก **
INSERT IGNORE INTO `users` (`id`,`username`,`password`,`full_name`,`email`,`role`,`status`) VALUES
(1,'admin','$2y$10$jDAe0xNE5MxIQMdeopEiU.UoyFHtN2x96Yza/6JWw/DcmqIil9s0e','ผู้ดูแลระบบ','admin@example.ac.th','admin',1);

INSERT IGNORE INTO `programs` (`id`,`name`,`level`) VALUES
(1,'การบัญชี','ปวช.'),(2,'การตลาด','ปวช.'),(3,'การเลขานุการ','ปวช.'),
(4,'คอมพิวเตอร์ธุรกิจ','ปวช.'),(5,'การขายหน้าร้าน','ปวช.'),(6,'การท่องเที่ยว','ปวช.'),
(7,'การบัญชี','ปวส.'),(8,'การตลาด','ปวส.'),(9,'คอมพิวเตอร์ธุรกิจ','ปวส.'),
(10,'เทคโนโลยีธุรกิจดิจิทัล','ปวส.'),(11,'การจัดการโลจิสติกส์','ปวส.');

INSERT IGNORE INTO `mou_categories` (`id`,`name`) VALUES
(1,'ทวิภาคี'),(2,'ฝึกงาน/ฝึกประสบการณ์'),(3,'พัฒนาหลักสูตร'),
(4,'แลกเปลี่ยนนักเรียน'),(5,'วิชาการ'),(6,'วิจัย'),(7,'ทุนการศึกษา');

INSERT IGNORE INTO `settings` (`skey`,`svalue`) VALUES
('site_name','ฐานข้อมูลบันทึกข้อตกลงความร่วมมือ (MOU)'),
('college_name','วิทยาลัยพณิชยการบางนา'),
('college_logo',''),
('site_description','ระบบสืบค้นและเผยแพร่ข้อมูลบันทึกข้อตกลงความร่วมมือระหว่างวิทยาลัยพณิชยการบางนากับสถานประกอบการ สถาบันการศึกษา และหน่วยงานภาครัฐ'),
('contact_name','งานความร่วมมือ (ตัวอย่าง: อ.มานะ ประสานงาน)'),
('contact_phone','02-000-0000'),
('contact_email','coop@example.ac.th'),
('contact_address','เลขที่ 1 ถนนบางนา-ตราด แขวงบางนา เขตบางนา กรุงเทพมหานคร 10260'),
('office_hours','จันทร์ – ศุกร์ เวลา 08.30 – 16.30 น.'),
('map_lat','13.6685'),
('map_lng','100.6047'),
('notify_email',''),
('expiring_days','90');
