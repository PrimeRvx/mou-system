<?php
/**
 * config/config.php — ค่าตั้งค่าหลักของระบบ MOU วิทยาลัยพณิชยการบางนา
 * แก้ไขค่าด้านล่างให้ตรงกับเซิร์ฟเวอร์ของท่านก่อนใช้งาน
 */

// ---------- โหมดระบบ ----------
// 'auto'        = (ค่าแนะนำ) เปิดเว็บจากเครื่องเซิร์ฟเวอร์เอง (localhost) จะเห็นรายละเอียด error เพื่อใช้แก้ไข
//                 ผู้ใช้จากเครื่องอื่นจะเห็นเพียงหน้าแจ้งข้อผิดพลาดทั่วไป ไม่เห็นรายละเอียดภายใน
// 'production'  = ซ่อน error ทุกกรณี (ใช้ค่านี้ถ้าเว็บอยู่หลัง reverse proxy / โฮสติ้งจริง)
// 'development' = แสดง error ทุกกรณี (ห้ามใช้บนเครื่องที่คนอื่นเข้าถึงได้)
// ทุกโหมดบันทึก error ลงไฟล์ uploads/.php-error.log
define('APP_ENV_MODE', 'auto');
define('APP_ENV', APP_ENV_MODE !== 'auto'
    ? APP_ENV_MODE
    : ((PHP_SAPI === 'cli' || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) ? 'development' : 'production'));

// ---------- ฐานข้อมูล ----------
define('DB_HOST', 'localhost');
define('DB_NAME', 'mou_bangna');
define('DB_USER', 'root');      // XAMPP ค่าเริ่มต้น
define('DB_PASS', '');          // XAMPP ค่าเริ่มต้นคือว่าง (production ต้องตั้งรหัสผ่าน)
define('DB_CHARSET', 'utf8mb4');

// ---------- ที่อยู่เว็บ ----------
// ที่อยู่หลักของเว็บ (ไม่ต้องมี / ท้าย) เช่น http://localhost/mou-system หรือ https://mou.example.ac.th
// ระบบปรับให้อัตโนมัติเมื่อเปิดจากเครื่องอื่นในเครือข่ายภายใน (เช่น http://192.168.1.10/mou-system หรือ http://ชื่อเครื่อง/mou-system)
// ถ้าใช้ชื่อโดเมนจริงบนอินเทอร์เน็ต ต้องใส่ชื่อโดเมนนั้นที่บรรทัดนี้ (โดเมนอื่นที่ไม่ตรงจะถูกบังคับกลับมาที่ค่านี้)
define('BASE_URL_CONFIG', 'http://localhost/mou-system');

/** เลือก BASE_URL ตามที่อยู่ที่ผู้ใช้เปิดจริง — ยอมรับเฉพาะ host ที่ตั้งไว้, localhost, IP ภายใน และชื่อเครื่องในวง LAN */
function mou_base_url(string $configured): string
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if (PHP_SAPI === 'cli' || $host === '' || !preg_match('/^[a-z0-9.\-]+(:\d{1,5})?$/', $host)) {
        return $configured;
    }
    $name    = preg_replace('/:\d+$/', '', $host);
    $cfgHost = strtolower((string) parse_url($configured, PHP_URL_HOST));
    $isLan   = $name === 'localhost'
            || preg_match('/^(127|10)\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $name)
            || preg_match('/^192\.168\.\d{1,3}\.\d{1,3}$/', $name)
            || preg_match('/^172\.(1[6-9]|2\d|3[01])\.\d{1,3}\.\d{1,3}$/', $name)
            || preg_match('/^[a-z0-9\-]+$/', $name);          // ชื่อเครื่องในวง LAN (ไม่มีจุด)
    if ($name !== $cfgHost && !$isLan) {
        return $configured;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https' : 'http') . '://' . $host . rtrim((string) parse_url($configured, PHP_URL_PATH), '/');
}
define('BASE_URL', mou_base_url(BASE_URL_CONFIG));
// true = ใช้ URL สวย (/mou/5) ต้องเปิด mod_rewrite และตั้ง RewriteBase ใน .htaccess ให้ถูก
// false = ใช้ลิงก์ตรง (public/mou_detail.php?id=5) ปลอดภัยสุดถ้า rewrite มีปัญหา
define('USE_PRETTY_URLS', true);

// ---------- พาธบนดิสก์ ----------
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

// ---------- ความปลอดภัย ----------
define('SESSION_NAME', 'MOUBANGNA');
define('SESSION_TIMEOUT', 1800);          // วินาที: ไม่มีการใช้งาน 30 นาที → ออกจากระบบ
define('LOGIN_MAX_ATTEMPTS', 5);          // ใส่รหัสผิดได้กี่ครั้ง
define('LOGIN_LOCK_MINUTES', 15);         // ล็อกกี่นาทีเมื่อผิดเกินกำหนด
// รหัสผ่านเริ่มต้นที่มากับไฟล์ติดตั้ง: admin ที่ยังใช้รหัสเหล่านี้จะถูกบังคับให้เปลี่ยนทันทีหลัง login
// ส่วนบัญชีระดับอื่นที่ยังใช้รหัสเหล่านี้จะเข้าใช้ไม่ได้จนกว่าผู้ดูแลจะตั้งรหัสใหม่ให้
define('DEFAULT_PASSWORDS', ['Admin@1234', 'Staff@1234', 'Viewer@1234']);
// viewer (ผู้บริหาร/ดูอย่างเดียว) เห็นข้อมูลภายในของ MOU (งบประมาณ, หมายเหตุภายใน) หรือไม่
define('VIEWER_SEES_INTERNAL', false);
// เก็บ Activity Log ย้อนหลังกี่วัน (ลบที่เก่ากว่านี้อัตโนมัติวันละครั้ง) | 0 = เก็บตลอดไป
define('LOG_RETENTION_DAYS', 365);
define('RATE_SALT', 'CHANGE_ME_random_string'); // ค่าสุ่มเฉพาะการติดตั้งนี้ ใช้ hash IP (ห้ามเผยแพร่)

// ---------- การอัปโหลด ----------
define('MAX_MOU_FILE_SIZE', 10 * 1024 * 1024);   // 10MB ไฟล์แนบ MOU
define('MAX_PROPOSAL_FILE_SIZE', 5 * 1024 * 1024); // 5MB ไฟล์ข้อเสนอ (PDF)
define('MOU_FILE_MIMES', [
    'application/pdf'    => 'pdf',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'image/jpeg'         => 'jpg',
]);

// ---------- รูปภาพประกอบรายละเอียด MOU / กิจกรรม (v2) ----------
define('IMAGE_MIMES', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']);
define('MAX_MOU_IMAGE_SIZE', 5 * 1024 * 1024);   // 5MB ต่อรูป (ก่อนย่อ)
define('MAX_MOU_IMAGES', 30);                     // จำนวนรูปสูงสุดต่อ MOU
define('IMAGE_MAX_DIMENSION', 1920);              // ย่อด้านยาวสุดเหลือกี่พิกเซล (ต้องมีส่วนขยาย GD; ไม่มีจะเก็บไฟล์เดิม)

// ---------- Rate limit แบบฟอร์มเสนอความร่วมมือ ----------
define('PROPOSAL_LIMIT_COUNT', 3);        // ส่งได้กี่ครั้ง
define('PROPOSAL_LIMIT_MINUTES', 60);     // ภายในกี่นาที

// ---------- reCAPTCHA / Cloudflare Turnstile (ทางเลือก) ----------
// ปล่อยว่าง = ไม่ใช้งาน | provider: 'turnstile' หรือ 'recaptcha'
define('CAPTCHA_PROVIDER', '');
define('CAPTCHA_SITE_KEY', '');
define('CAPTCHA_SECRET_KEY', '');

// ---------- ค่าอื่นๆ ----------
define('DEFAULT_EXPIRING_DAYS', 90);      // ใกล้หมดอายุกี่วัน (ถ้าไม่ได้ตั้งใน settings)
date_default_timezone_set('Asia/Bangkok');
mb_internal_encoding('UTF-8');

// ---------- การแสดง error ----------
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', ROOT_PATH . '/uploads/.php-error.log');
ini_set('display_errors', APP_ENV === 'production' ? '0' : '1');
