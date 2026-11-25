<?php
/**
 * includes/functions.php — ฟังก์ชันกลางของระบบ
 * (escape, วันที่ไทย, CSRF, flash, settings, สถานะ MOU, rate limit, อัปโหลดไฟล์, log)
 */
require_once __DIR__ . '/db.php';

/* =====================================================================
 * 1) พื้นฐาน: escape / URL / redirect
 * ===================================================================== */

/** ป้องกัน XSS — ใช้ครอบทุกค่าที่แสดงบนหน้าเว็บ */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** สร้าง URL เต็มจากพาธภายใน เช่น url('admin/login.php') */
function url(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

/** สร้างลิงก์หน้าสาธารณะ ตามโหมด URL สวย/ลิงก์ตรง */
function route(string $name, $id = null): string
{
    $pretty = ['home' => '', 'mou' => 'mou', 'news' => 'news', 'contact' => 'contact',
               'mou_detail' => 'mou/', 'news_detail' => 'news/', 'download' => 'download/'];
    $plain  = ['home' => 'public/index.php', 'mou' => 'public/mou_list.php', 'news' => 'public/news_list.php',
               'contact' => 'public/contact.php', 'mou_detail' => 'public/mou_detail.php?id=',
               'news_detail' => 'public/news_detail.php?id=', 'download' => 'public/download.php?id='];
    $map = USE_PRETTY_URLS ? $pretty : $plain;
    return url(($map[$name] ?? '') . ($id !== null ? (int) $id : ''));
}

/** escape ตัวอักษรพิเศษของ LIKE */
function like_escape(string $s): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
}

function redirect(string $path): void
{
    // อนุญาตเฉพาะพาธภายในเว็บ ป้องกัน open redirect
    $target = preg_match('#^https?://#i', $path) ? $path : url($path);
    if (preg_match('#^https?://#i', $target) && strpos($target, rtrim(BASE_URL, '/') . '/') !== 0) {
        $target = url();
    }
    header('Location: ' . $target);
    exit;
}

/** IP ผู้ใช้ (ไม่เชื่อ X-Forwarded-For เพราะปลอมได้ — ถ้าอยู่หลัง proxy ให้ปรับเอง) */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** ส่งข้อมูล JSON แล้วจบการทำงาน */
function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* =====================================================================
 * 2) วันที่แบบไทย (พ.ศ.)
 * ===================================================================== */

const TH_MONTHS_FULL  = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
                         'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
const TH_MONTHS_SHORT = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
                         'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

/** แปลงค่าวันที่ใดๆ เป็น DateTimeImmutable (null ถ้าว่าง/ไม่ถูกต้อง) */
function to_date($value): ?DateTimeImmutable
{
    if ($value === null || $value === '' || strpos((string) $value, '0000-00-00') === 0) {
        return null;
    }
    try {
        return new DateTimeImmutable((string) $value);
    } catch (Exception $e) {
        return null;
    }
}

/** ปี พ.ศ. จากวันที่ */
function thai_year($value): string
{
    $d = to_date($value);
    return $d ? (string) ((int) $d->format('Y') + 543) : '';
}

/**
 * วันที่ไทย เช่น 31 ตุลาคม 2569  (short=true → 31 ต.ค. 69 ไม่ใช่ — จะได้ 31 ต.ค. 2569)
 */
function thai_date($value, bool $short = false): string
{
    $d = to_date($value);
    if (!$d) {
        return '-';
    }
    $months = $short ? TH_MONTHS_SHORT : TH_MONTHS_FULL;
    return ((int) $d->format('j')) . ' ' . $months[(int) $d->format('n')] . ' ' . ((int) $d->format('Y') + 543);
}

/** วันที่+เวลา เช่น 31 ตุลาคม 2569 เวลา 14:30 น. */
function thai_datetime($value, bool $short = false): string
{
    $d = to_date($value);
    return $d ? thai_date($value, $short) . ' เวลา ' . $d->format('H:i') . ' น.' : '-';
}

/** แปลง input จากฟอร์มที่เป็น พ.ศ. (เช่น 31/10/2569) → Y-m-d (ค.ศ.) ; ถ้าเป็น ค.ศ. อยู่แล้วคืนตามเดิม */
function parse_thai_input(?string $text): ?string
{
    $text = trim((string) $text);
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $text, $m)) {            // วว/ดด/ปปปป
        [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
    } elseif (preg_match('#^(\d{4})-(\d{2})-(\d{2})#', $text, $m)) {           // ปปปป-ดด-วว (input type=date)
        [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
    } else {
        return null;
    }
    if ($y > 2400) {   // พิมพ์ปีเป็น พ.ศ. → แปลงเป็น ค.ศ. (ทั้งสองรูปแบบ)
        $y -= 543;
    }
    return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
}

/** จำนวนวันคงเหลือจนถึงวันที่ (ติดลบ = เลยกำหนดแล้ว) */
function days_remaining($endDate): ?int
{
    $end = to_date($endDate);
    if (!$end) {
        return null;
    }
    $today = new DateTimeImmutable('today');
    return (int) $today->diff($end->setTime(0, 0))->format('%r%a');
}

/** ข้อความนับถอยหลัง เช่น "เหลืออีก 1 ปี 2 เดือน 5 วัน" */
function remaining_text($endDate): string
{
    $end = to_date($endDate);
    if (!$end) {
        return '-';
    }
    $today = new DateTimeImmutable('today');
    $end   = $end->setTime(0, 0);
    if ($end < $today) {
        return 'หมดอายุแล้ว ' . number_format(abs(days_remaining($endDate))) . ' วัน';
    }
    if ($end == $today) {
        return 'หมดอายุวันนี้';
    }
    $diff  = $today->diff($end);
    $parts = [];
    if ($diff->y) { $parts[] = $diff->y . ' ปี'; }
    if ($diff->m) { $parts[] = $diff->m . ' เดือน'; }
    if ($diff->d || !$parts) { $parts[] = $diff->d . ' วัน'; }
    return 'เหลืออีก ' . implode(' ', $parts);
}

/** รายการปี พ.ศ. สำหรับตัวกรอง [คศ => พศ] */
function year_options(int $back = 10): array
{
    $now = (int) date('Y');
    $out = [];
    for ($y = $now + 1; $y >= $now - $back; $y--) {
        $out[$y] = $y + 543;
    }
    return $out;
}

/* =====================================================================
 * 3) ป้ายชื่อ/สี (label) ของ enum ต่างๆ
 * ===================================================================== */

function partner_types(): array
{
    return [
        'enterprise' => 'สถานประกอบการ',
        'education'  => 'สถาบันการศึกษา',
        'government' => 'หน่วยงานรัฐ',
        'other'      => 'อื่นๆ',
    ];
}

function partner_type_label(?string $t): string
{
    return partner_types()[$t] ?? '-';
}

/** สถานะ MOU: key => [ข้อความ, คลาสสี Bootstrap] */
function mou_statuses(): array
{
    return [
        'draft'     => ['ฉบับร่าง',     'secondary'],
        'active'    => ['มีผลบังคับใช้', 'success'],
        'expiring'  => ['ใกล้หมดอายุ',   'warning'],
        'expired'   => ['หมดอายุ',       'danger'],
        'cancelled' => ['ยกเลิก',        'dark'],
    ];
}

/** HTML ป้ายสถานะสี (เขียว/เหลือง/แดง) */
function status_badge(?string $status): string
{
    $s = mou_statuses()[$status] ?? [$status ?: '-', 'light'];
    $text = $s[1] === 'warning' ? 'text-dark' : '';
    return '<span class="badge status-badge st-' . e((string) $status) . ' bg-' . e($s[1]) . ' ' . $text . '">' . e($s[0]) . '</span>';
}

function proposal_statuses(): array
{
    return [
        'new'       => ['ใหม่',        'primary'],
        'reviewing' => ['กำลังพิจารณา', 'warning'],
        'approved'  => ['อนุมัติ',      'success'],
        'rejected'  => ['ปฏิเสธ',       'danger'],
    ];
}

function role_labels(): array
{
    return ['admin' => 'ผู้ดูแลระบบ', 'staff' => 'เจ้าหน้าที่', 'viewer' => 'ผู้บริหาร (ดูอย่างเดียว)'];
}

/* =====================================================================
 * 4) CSRF + Flash message
 * ===================================================================== */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** ใส่ใน <form> ทุกฟอร์มที่ใช้ POST */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(?string $token = null): bool
{
    $token = $token ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

/** เรียกต้นไฟล์ที่รับ POST — ถ้า token ไม่ผ่านจะหยุดทันที */
function csrf_require(): void
{
    // PHP ล้าง $_POST และ $_FILES ทั้งหมดเมื่อขนาดคำขอเกิน post_max_size → แจ้งสาเหตุจริงแทน "เซสชันหมดอายุ"
    if (!$_POST && !$_FILES && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        http_response_code(413);
        header('Content-Type: text/html; charset=utf-8');
        exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ไฟล์ใหญ่เกินไป</title>'
           . '<div style="font-family:sans-serif;text-align:center;margin-top:80px;padding:0 16px">'
           . '<h1 style="font-size:1.4rem">ไฟล์ที่ส่งมามีขนาดรวมเกินที่เซิร์ฟเวอร์รับได้ (' . e((string) ini_get('post_max_size')) . ')</h1>'
           . '<p>กรุณาย้อนกลับ แล้วลดจำนวนหรือขนาดไฟล์ หรือแบ่งอัปโหลดเป็นหลายครั้ง</p>'
           . '<p>' . back_link_html() . ' · <a href="' . e(url()) . '">หน้าหลัก</a></p></div>');
    }
    if (!csrf_verify()) {
        http_response_code(403);   // เดิมใช้ 419 ซึ่งไม่ใช่รหัสมาตรฐาน — Apache แปลงเป็น 500 Internal Server Error
        header('Content-Type: text/html; charset=utf-8');
        exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>กรุณาลองใหม่</title>'
           . '<div style="font-family:sans-serif;text-align:center;margin-top:80px;padding:0 16px">'
           . '<h1 style="font-size:1.4rem">เซสชันหมดอายุ หรือคำขอไม่ถูกต้อง</h1>'
           . '<p>กรุณากลับไปโหลดหน้าฟอร์มใหม่แล้วส่งอีกครั้ง</p>'
           . '<p>' . back_link_html() . ' · <a href="' . e(url()) . '">หน้าหลัก</a></p></div>');
    }
}

/** ตั้ง/อ่านข้อความแจ้งเตือนครั้งเดียว (แสดงด้วย SweetAlert2) */
function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message]; // success|error|warning|info
}

function flash_pull(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/**
 * เก็บค่าที่ผู้ใช้กรอกไว้ชั่วคราวก่อน redirect กลับฟอร์ม (กรณีตรวจข้อมูลไม่ผ่าน) — ห้ามใส่รหัสผ่าน
 * ใช้คู่กับ form_old() ในหน้าฟอร์ม เพื่อไม่ให้ข้อมูลที่พิมพ์ไว้หาย
 */
function form_keep(array $data): void
{
    $_SESSION['form_old'] = $data;
}

/** อ่านค่าที่เก็บด้วย form_keep() (อ่านได้ครั้งเดียว) */
function form_old(): array
{
    $o = $_SESSION['form_old'] ?? [];
    unset($_SESSION['form_old']);
    return is_array($o) ? $o : [];
}

/** พิมพ์ <script> เรียก SweetAlert2 ถ้ามี flash (เรียกท้ายหน้า หลังโหลด SweetAlert2) */
function flash_script(): string
{
    $f = flash_pull();
    if (!$f) {
        return '';
    }
    // สำเร็จ/แจ้งข้อมูล = toast มุมจอ ปิดเองใน 4 วินาที (ไม่ต้องกด "ตกลง" ทุกครั้งที่บันทึก)
    // ผิดพลาด/คำเตือน = กล่องยืนยันตามเดิม เพราะผู้ใช้ต้องอ่านและตัดสินใจ — app.js: MouFlash
    return '<script nonce="' . e(csp_nonce()) . '">document.addEventListener("DOMContentLoaded",function(){window.MouFlash&&MouFlash('
        . json_encode($f['type'], JSON_FOR_SCRIPT) . ',' . json_encode($f['message'], JSON_FOR_SCRIPT) . ');});</script>';
}

/* =====================================================================
 * 5) ตั้งค่าเว็บ (settings)
 * ===================================================================== */

/** อ่านค่า setting ทั้งหมดครั้งเดียวต่อ request */
function settings_all(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db_all('SELECT skey, svalue FROM settings') as $r) {
                $cache[$r['skey']] = $r['svalue'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings_all();
    return isset($all[$key]) && $all[$key] !== '' ? $all[$key] : $default;
}

function setting_save(string $key, string $value): void
{
    db_exec('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)',
        [$key, $value]);
}

function expiring_days(): int
{
    $d = (int) setting('expiring_days', (string) DEFAULT_EXPIRING_DAYS);
    return $d > 0 ? $d : DEFAULT_EXPIRING_DAYS;
}

/* =====================================================================
 * 6) สถานะ MOU — คำนวณอัตโนมัติจากวันที่สิ้นสุด
 * ===================================================================== */

/**
 * อัปเดตสถานะ MOU ทุกรายการ (ยกเว้น ฉบับร่าง/ยกเลิก) จาก end_date
 *   end_date < วันนี้                  → expired
 *   end_date <= วันนี้ + N วัน (90)     → expiring
 *   นอกนั้น                            → active  (รวมกรณีต่ออายุแล้ว)
 * เรียกซ้ำกี่ครั้งก็ได้: ทำงานจริงวันละครั้ง (หรือทันทีเมื่อส่ง $force = true หลังแก้ไขข้อมูล)
 */
function refresh_mou_status(bool $force = false): void
{
    static $done = false;
    if ($done && !$force) {
        return;
    }
    $done = true;
    // ทำงานจริงวันละครั้ง (จำวันที่ล่าสุดไว้ใน settings) — หน้าที่แก้ไขข้อมูลเรียกแบบ $force = true อยู่แล้ว
    $today = date('Y-m-d');
    if (!$force && setting('status_refreshed_on') === $today) {
        return;
    }
    db_exec(
        "UPDATE mou SET status = CASE
            WHEN end_date < CURDATE() THEN 'expired'
            WHEN end_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY) THEN 'expiring'
            ELSE 'active' END
         WHERE deleted_at IS NULL AND status IN ('active','expiring','expired')",
        [expiring_days()]
    );
    try {
        setting_save('status_refreshed_on', $today);
        if (setting('daily_tasks_on') !== $today) {      // งานประจำวัน: ทำครั้งเดียวต่อวัน ตอนมีคนเปิดเว็บครั้งแรก
            setting_save('daily_tasks_on', $today);
            daily_tasks();
        }
    } catch (Throwable $e) {
        error_log('refresh_mou_status: ' . $e->getMessage());
    }
}

/**
 * งานประจำวัน (ไม่ต้องพึ่ง Task Scheduler): แจ้งเตือน MOU ใกล้หมดอายุ + ล้างข้อมูลเก่า
 * ถูกเรียกอัตโนมัติวันละครั้งจาก refresh_mou_status() — หรือสั่งเองได้ด้วย cron/notify_expiring.php
 */
function daily_tasks(): array
{
    $result = ['new' => 0, 'mail' => null];   // new = จำนวนแจ้งเตือนใหม่, mail = null ไม่ได้ส่ง / true ส่งแล้ว / false ส่งไม่สำเร็จ
    try {
        $new = notify_expiring_mous();
        $result['new'] = count($new);
        if ($new) {
            $result['mail'] = send_expiring_digest($new);
        }
        if (LOG_RETENTION_DAYS > 0) {
            db_exec('DELETE FROM activity_logs WHERE created_at < (NOW() - INTERVAL ? DAY)', [LOG_RETENTION_DAYS]);
        }
        db_exec('DELETE FROM notifications WHERE is_read = 1 AND created_at < (NOW() - INTERVAL 180 DAY)');
    } catch (Throwable $e) {
        error_log('daily_tasks: ' . $e->getMessage());
    }
    return $result;
}

/**
 * สร้างแจ้งเตือนในระบบ (รูประฆังหลังบ้าน) ให้ MOU ที่ "ใกล้หมดอายุ" — MOU ละครั้งต่อ 30 วัน ตราบที่ยังไม่ต่ออายุ
 * @return array รายการ MOU ที่เพิ่งแจ้งเตือนในรอบนี้
 */
function notify_expiring_mous(int $remindEveryDays = 30): array
{
    $rows = db_all(
        "SELECT m.id, m.mou_no, m.title, m.end_date, p.name AS partner_name
           FROM mou m JOIN partners p ON p.id = m.partner_id
          WHERE m.deleted_at IS NULL AND m.status = 'expiring'
          ORDER BY m.end_date, m.id"
    );
    $new = [];
    foreach ($rows as $r) {
        $link = 'admin/mou_view.php?id=' . (int) $r['id'];
        $recent = (int) db_val(
            "SELECT COUNT(*) FROM notifications WHERE type = 'expiring' AND link = ? AND created_at > (NOW() - INTERVAL ? DAY)",
            [$link, $remindEveryDays]
        );
        if ($recent > 0) {
            continue;   // เตือนไปแล้วในรอบนี้
        }
        $days = days_remaining($r['end_date']);
        $msg  = 'MOU ' . $r['mou_no'] . ' (' . $r['partner_name'] . ') จะหมดอายุ ' . thai_date($r['end_date'], true)
              . ($days !== null ? ' — เหลือ ' . max(0, $days) . ' วัน' : '');
        notify_staff('expiring', mb_substr($msg, 0, 500), $link);
        $new[] = $r + ['days' => $days];
    }
    return $new;
}

/** ส่งอีเมลสรุป MOU ใกล้หมดอายุถึง "อีเมลรับแจ้งเตือน" ในหน้าตั้งค่า — คืน null ถ้าไม่ได้ตั้งอีเมลไว้ */
function send_expiring_digest(array $new): ?bool
{
    $to = setting('notify_email');
    if (!$new || $to === '' || !valid_email($to)) {
        return null;
    }
    $body = 'MOU ที่ใกล้หมดอายุ (ภายใน ' . expiring_days() . " วัน)\n" . str_repeat('-', 40) . "\n";
    foreach ($new as $r) {
        $body .= '- ' . $r['mou_no'] . ' | ' . $r['partner_name'] . ' | หมดอายุ ' . thai_date($r['end_date'], true)
               . (($r['days'] ?? null) !== null ? ' (เหลือ ' . max(0, (int) $r['days']) . ' วัน)' : '') . "\n  " . $r['title'] . "\n";
    }
    $body .= "\nดูรายการและบันทึกการต่ออายุ: " . url('admin/mou_list.php?status=expiring') . "\n\n" . setting('college_name') . "\n";
    return send_text_mail($to, 'แจ้งเตือน MOU ใกล้หมดอายุ ' . count($new) . ' รายการ', $body);
}

/**
 * ส่งอีเมลข้อความล้วน (UTF-8) ด้วย mail() ของ PHP — ต้องตั้งค่า SMTP/sendmail ใน php.ini ก่อนจึงจะส่งได้จริง
 * ผู้ส่ง (From) ใช้ "อีเมลติดต่อ" ในหน้าตั้งค่าเว็บ ถ้าไม่ได้ตั้งจะใช้ no-reply@ชื่อเว็บ
 * @return bool false = ส่งไม่ได้ (เช่น ยังไม่ได้ตั้ง SMTP)
 */
function send_text_mail(string $to, string $subject, string $body): bool
{
    if (!valid_email($to)) {
        return false;
    }
    $from = setting('contact_email');
    if (!valid_email($from)) {
        $from = 'no-reply@' . (parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost');
    }
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\nFrom: " . $from;
    $subject = '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], ' ', $subject)) . '?=';
    try {
        return (bool) @mail($to, $subject, str_replace("\r\n", "\n", $body), $headers);
    } catch (Throwable $e) {
        error_log('send_text_mail: ' . $e->getMessage());
        return false;
    }
}

/** จำนวน MOU ที่ใกล้หมดอายุ (ใช้ทำ badge ที่เมนู) */
function expiring_count(): int
{
    refresh_mou_status();
    return (int) db_val("SELECT COUNT(*) FROM mou WHERE deleted_at IS NULL AND status = 'expiring'");
}

/** จำนวนข้อเสนอความร่วมมือที่ยังเป็น "ใหม่" */
function new_proposal_count(): int
{
    return (int) db_val("SELECT COUNT(*) FROM proposals WHERE status = 'new'");
}

/* =====================================================================
 * 7) Validation เบื้องต้นฝั่งเซิร์ฟเวอร์
 * ===================================================================== */

/** อ่านค่า POST เป็นข้อความ (trim) */
function post(string $key, string $default = ''): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : $default;
}

function get(string $key, string $default = ''): string
{
    return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : $default;
}

/** อ่านค่า POST แบบไม่ trim (ใช้กับรหัสผ่าน) — คืนข้อความว่างถ้าไม่ได้ส่งมาหรือส่งมาเป็น array */
function post_raw(string $key): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : '';
}

/**
 * อีเมลที่ระบบยอมรับ: ไม่เกิน 150 ตัวอักษร (เท่าคอลัมน์ในฐานข้อมูล) และไม่มี " < > หรือช่องว่าง
 * (FILTER_VALIDATE_EMAIL ลำพังยอมรับ local part ในเครื่องหมายคำพูด เช่น "<img/src=x/onerror=…>"@x.co)
 */
function valid_email(string $v): bool
{
    return strlen($v) <= 150 && !preg_match('/["<>\s]/', $v) && filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
}

/** ค่าขนาดใน php.ini (เช่น 40M, 1G) เป็นไบต์ — 0 = ไม่จำกัด */
function ini_bytes(string $key): int
{
    $v = trim((string) ini_get($key));
    $n = (int) $v;
    switch (strtolower(substr($v, -1))) {
        case 'g': return $n << 30;
        case 'm': return $n << 20;
        case 'k': return $n << 10;
        default:  return $n;
    }
}

/** จำนวนรูปที่อัปโหลดได้ในครั้งเดียว: ไม่เกินที่เหลือ และไม่เกิน max_file_uploads ของ PHP */
function upload_slots(int $remaining): int
{
    return max(0, min($remaining, (int) ini_get('max_file_uploads') ?: 20));
}

/** ขนาดรวมสูงสุดต่อการส่งฟอร์มหนึ่งครั้ง (MB) ตาม post_max_size เผื่อข้อมูลช่องอื่น 1 MB — 0 = ไม่จำกัด */
function upload_total_mb(): int
{
    $b = ini_bytes('post_max_size');
    return $b > 0 ? max(1, (int) floor($b / 1048576) - 1) : 0;
}

/** เบอร์โทรไทย/ทั่วไป: ตัวเลข ขีด วงเล็บ เว้นวรรค + ต่อ ความยาว 8-20 */
function valid_phone(string $v): bool
{
    return (bool) preg_match('/^[0-9+\-\s()#,ext.]{8,20}$/i', $v);
}

/** ตัดข้อความยาวเพื่อแสดงสรุป */
function excerpt(string $text, int $len = 140): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
    return mb_strlen($text) > $len ? mb_substr($text, 0, $len) . '…' : $text;
}

/** สร้าง slug แบบง่าย (รองรับภาษาไทย) */
function make_slug(string $text): string
{
    $s = mb_strtolower(trim($text));
    $s = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', $s);
    return trim(mb_substr($s, 0, 80), '-') ?: 'item';
}

/* =====================================================================
 * 8) Rate limit (เก็บใน DB — ใช้ได้บน shared hosting)
 * ===================================================================== */

function rate_hash(): string
{
    return hash('sha256', client_ip() . RATE_SALT);
}

/** จำนวนครั้งที่ bucket นี้ถูกใช้โดย IP นี้ ภายใน $minutes นาที */
function rate_count(string $bucket, int $minutes): int
{
    return (int) db_val(
        'SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND ip_hash = ? AND created_at > (NOW() - INTERVAL ? MINUTE)',
        [$bucket, rate_hash(), $minutes]
    );
}

function rate_hit(string $bucket): void
{
    db_exec('INSERT INTO rate_limits (bucket, ip_hash) VALUES (?, ?)', [$bucket, rate_hash()]);
    // เก็บกวาดข้อมูลเก่าเป็นครั้งคราว (ประมาณ 2% ของคำขอ)
    if (random_int(1, 50) === 1) {
        db_exec('DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 1 DAY)');
    }
}

function rate_exceeded(string $bucket, int $max, int $minutes): bool
{
    return rate_count($bucket, $minutes) >= $max;
}

function rate_clear(string $bucket): void
{
    db_exec('DELETE FROM rate_limits WHERE bucket = ? AND ip_hash = ?', [$bucket, rate_hash()]);
}

/* =====================================================================
 * 9) อัปโหลดไฟล์ปลอดภัย
 * ===================================================================== */

/**
 * ตรวจและบันทึกไฟล์อัปโหลด
 * @param array  $file     องค์ประกอบของ $_FILES
 * @param array  $allowed  แผนที่ MIME => นามสกุลที่จะใช้เก็บ
 * @param int    $maxSize  ขนาดสูงสุด (ไบต์)
 * @param string $subDir   โฟลเดอร์ย่อยใน uploads/ เช่น 'mou', 'proposals'
 * @return array ['name'=>ชื่อเดิม,'stored'=>ชื่อสุ่ม,'mime'=>..,'size'=>..] หรือ ['error'=>ข้อความ]
 */
function save_upload(array $file, array $allowed, int $maxSize, string $subDir): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['error' => 'ข้อมูลไฟล์ไม่ถูกต้อง'];
    }
    switch ($file['error']) {
        case UPLOAD_ERR_OK: break;
        case UPLOAD_ERR_NO_FILE: return ['error' => 'ยังไม่ได้เลือกไฟล์'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE: return ['error' => 'ไฟล์มีขนาดใหญ่เกินกำหนด'];
        default: return ['error' => 'อัปโหลดไฟล์ไม่สำเร็จ (รหัส ' . (int) $file['error'] . ')'];
    }
    if ($file['size'] <= 0 || $file['size'] > $maxSize) {
        return ['error' => 'ไฟล์ต้องมีขนาดไม่เกิน ' . round($maxSize / 1048576) . ' MB'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['error' => 'ไฟล์ไม่ถูกต้อง'];
    }
    // ตรวจ MIME จากเนื้อไฟล์จริง (ไม่เชื่อค่าที่เบราว์เซอร์ส่งมา)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {   // ไฟล์ Word บางไฟล์ถูกมองเป็น zip/OLE ทั่วไป → ยืนยันจากโครงสร้างภายในไฟล์
        $mime = office_mime_fallback($file['tmp_name'], (string) $file['name'], (string) $mime);
    }
    if (!isset($allowed[$mime])) {
        return ['error' => 'ชนิดไฟล์ไม่ได้รับอนุญาต (อนุญาต: ' . implode(', ', array_unique($allowed)) . ')'];
    }
    $dir = UPLOAD_PATH . '/' . trim($subDir, '/');
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return ['error' => 'สร้างโฟลเดอร์จัดเก็บไฟล์ไม่ได้'];
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];   // เปลี่ยนชื่อสุ่ม
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
        return ['error' => 'บันทึกไฟล์ไม่สำเร็จ'];
    }
    @chmod($dir . '/' . $stored, 0644);
    return [
        'name'   => mb_substr(basename((string) $file['name']), 0, 200),
        'stored' => $stored,
        'mime'   => $mime,
        'size'   => (int) $file['size'],
    ];
}

/**
 * ไฟล์ .docx ที่สร้างจาก Google Docs/LibreOffice มักถูกตรวจเป็น application/zip และ .doc รุ่นเก่ามักเป็น OLE ทั่วไป
 * ฟังก์ชันนี้ยืนยันจาก "เนื้อไฟล์จริง" (ไม่ใช่แค่นามสกุล) ว่าเป็นเอกสาร Word แล้วคืน MIME ที่ถูกต้อง
 * ถ้ายืนยันไม่ได้จะคืนค่าเดิม (ซึ่งจะถูกปฏิเสธตามปกติ)
 */
function office_mime_fallback(string $path, string $name, string $mime): string
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext === 'docx' && class_exists('ZipArchive')
        && in_array($mime, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'], true)) {
        $zip = new ZipArchive();
        if ($zip->open($path) === true) {
            $isWord = $zip->locateName('word/document.xml') !== false && $zip->locateName('[Content_Types].xml') !== false;
            $zip->close();
            if ($isWord) {
                return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            }
        }
    }
    if ($ext === 'doc'
        && in_array($mime, ['application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office', 'application/octet-stream'], true)
        && @file_get_contents($path, false, null, 0, 8) === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
        return 'application/msword';
    }
    return $mime;
}

/**
 * แปลง $_FILES['images'] แบบหลายไฟล์ (name[]/tmp_name[]...) ให้เป็นรายการไฟล์ทีละตัว
 * ข้ามช่องที่ไม่ได้เลือกไฟล์
 */
function normalize_files(?array $f): array
{
    if (!$f || !isset($f['name'])) {
        return [];
    }
    if (!is_array($f['name'])) {
        return $f['error'] === UPLOAD_ERR_NO_FILE ? [] : [$f];
    }
    $out = [];
    foreach ($f['name'] as $i => $n) {
        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = ['name' => $n, 'type' => $f['type'][$i] ?? '', 'tmp_name' => $f['tmp_name'][$i] ?? '',
                  'error' => $f['error'][$i], 'size' => $f['size'][$i] ?? 0];
    }
    return $out;
}

/**
 * ปรับรูปหลังอัปโหลด: หมุนตาม EXIF, ย่อด้านยาวสุดไม่เกิน $maxDim, บีบอัด และลบข้อมูล EXIF (เช่นพิกัด GPS)
 * ต้องมีส่วนขยาย GD — ถ้าไม่มีจะเก็บไฟล์เดิมไว้ตามเดิม
 * @return array [width, height]
 */
function optimize_image(string $path, string $mime, int $maxDim = IMAGE_MAX_DIMENSION): array
{
    $info = @getimagesize($path);
    $w = (int) ($info[0] ?? 0); $h = (int) ($info[1] ?? 0);
    if (!extension_loaded('gd') || !$w || !$h) {
        return [$w, $h];
    }
    $src = null;
    if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) { $src = @imagecreatefromjpeg($path); }
    elseif ($mime === 'image/png' && function_exists('imagecreatefrompng')) { $src = @imagecreatefrompng($path); }
    elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) { $src = @imagecreatefromwebp($path); }
    if (!$src) {
        return [$w, $h];
    }
    // PNG/WebP: เก็บช่องโปร่งใสไว้ตอนบันทึกซ้ำ (ไม่เช่นนั้นพื้นโปร่งใสของรูปที่ไม่ถูกย่อจะกลายเป็นสีดำ)
    if ($mime !== 'image/jpeg') {
        imagealphablending($src, false);
        imagesavealpha($src, true);
    }
    // หมุนภาพตาม EXIF (รูปจากมือถือ)
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $rot = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($rot && ($r = imagerotate($src, $rot, 0))) { imagedestroy($src); $src = $r; }
    }
    $w = imagesx($src); $h = imagesy($src);
    $scale = min(1, $maxDim / max($w, $h));
    $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
    $dst = $src;
    if ($scale < 1) {
        $dst = imagecreatetruecolor($nw, $nh);
        if ($mime !== 'image/jpeg') { imagealphablending($dst, false); imagesavealpha($dst, true); }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    }
    $ok = false;
    if ($mime === 'image/jpeg') { imageinterlace($dst, true); $ok = imagejpeg($dst, $path . '.tmp', 82); }
    elseif ($mime === 'image/png') { $ok = imagepng($dst, $path . '.tmp', 7); }
    else { $ok = imagewebp($dst, $path . '.tmp', 82); }
    if ($ok && filesize($path . '.tmp') > 0) { @rename($path . '.tmp', $path); } else { @unlink($path . '.tmp'); }
    if ($dst !== $src) { imagedestroy($dst); }
    imagedestroy($src);
    return [$nw, $nh];
}

/**
 * อัปโหลดรูปหลายไฟล์พร้อมกัน (ตรวจ MIME จริง + เปลี่ยนชื่อสุ่ม + ย่อรูป)
 * @return array ['saved' => [[stored,size,width,height,name], ...], 'errors' => [ข้อความ]]
 */
function save_images(array $files, string $subDir, int $limit = MAX_MOU_IMAGES): array
{
    $saved = []; $errors = [];
    foreach ($files as $i => $f) {
        if (count($saved) >= $limit) {
            $errors[] = 'เกินจำนวนรูปสูงสุด (' . $limit . ' รูป) ไฟล์ที่เหลือไม่ถูกบันทึก';
            break;
        }
        $r = save_upload($f, IMAGE_MIMES, MAX_MOU_IMAGE_SIZE, $subDir);
        if (isset($r['error'])) {
            $errors[] = mb_substr((string) ($f['name'] ?? ('ไฟล์ที่ ' . ($i + 1))), 0, 60) . ': ' . $r['error'];
            continue;
        }
        $path = UPLOAD_PATH . '/' . $subDir . '/' . $r['stored'];
        [$w, $h] = optimize_image($path, $r['mime']);
        clearstatcache(true, $path);
        $saved[] = ['stored' => $r['stored'], 'name' => $r['name'], 'size' => (int) @filesize($path), 'width' => $w, 'height' => $h];
    }
    return ['saved' => $saved, 'errors' => $errors];
}

/** ลบไฟล์ใน uploads (กัน path traversal) */
function delete_upload(string $subDir, string $stored): void
{
    $stored = basename($stored);
    $path = UPLOAD_PATH . '/' . trim($subDir, '/') . '/' . $stored;
    if ($stored !== '' && is_file($path)) {
        @unlink($path);
    }
}

/* ---------- อัลบั้มรูปภาพประกอบข่าว (v2.3) ----------
 * ตาราง news_images (database/migration_v3_news_images.sql) — ไฟล์เก็บใน uploads/news/ (โฟลเดอร์รูปสาธารณะ)
 * ถ้ายังไม่ได้สร้างตาราง ทุกฟังก์ชันคืนค่าว่าง เว็บไม่ล่ม */
defined('MAX_NEWS_IMAGES') || define('MAX_NEWS_IMAGES', 30);

function has_news_images(): bool
{
    static $ok = null;
    if ($ok === null) {
        try { $ok = (bool) db_val("SHOW TABLES LIKE 'news_images'"); } catch (Throwable $e) { $ok = false; }
    }
    return $ok;
}

function news_images_of(int $newsId): array
{
    return has_news_images() ? db_all('SELECT * FROM news_images WHERE news_id = ? ORDER BY sort_order, id', [$newsId]) : [];
}

/** บันทึกรูปที่อัปโหลดให้ข่าว (ตรวจชนิดไฟล์จริง + ย่อรูป + ลบ EXIF) — คืน [จำนวนที่บันทึก, ข้อความผิดพลาด[]] */
function news_images_add(int $newsId, ?array $files): array
{
    $files = normalize_files($files);
    if (!$files || !has_news_images()) {
        return [0, $files && !has_news_images() ? ['ยังไม่ได้สร้างตาราง news_images — กรุณารัน database/migration_v3_news_images.sql'] : []];
    }
    $current = (int) db_val('SELECT COUNT(*) FROM news_images WHERE news_id = ?', [$newsId]);
    $res = save_images($files, 'news', max(0, MAX_NEWS_IMAGES - $current));
    $order = (int) db_val('SELECT COALESCE(MAX(sort_order), 0) FROM news_images WHERE news_id = ?', [$newsId]);
    foreach ($res['saved'] as $img) {
        db_insert('INSERT INTO news_images (news_id, stored_name, width, height, size, sort_order, uploaded_by) VALUES (?,?,?,?,?,?,?)',
            [$newsId, $img['stored'], $img['width'] ?: null, $img['height'] ?: null, $img['size'], ++$order, $_SESSION['user']['id'] ?? null]);
    }
    return [count($res['saved']), $res['errors']];
}

/**
 * ส่วน SELECT ของภาพปกข่าว: ใช้ภาพปกที่ตั้งไว้ ถ้าไม่มีใช้รูปแรกในอัลบั้ม + จำนวนรูปในอัลบั้ม
 * ใช้: "SELECT n.id, n.title, " . news_cover_sql() . " FROM news n ..."
 */
function news_cover_sql(string $a = 'n'): string
{
    if (!has_news_images()) {
        return "$a.cover_image, 0 AS image_count";
    }
    return "COALESCE(NULLIF($a.cover_image, ''), (SELECT ni.stored_name FROM news_images ni WHERE ni.news_id = $a.id ORDER BY ni.sort_order, ni.id LIMIT 1)) AS cover_image,
            (SELECT COUNT(*) FROM news_images nc WHERE nc.news_id = $a.id) AS image_count";
}

/* =====================================================================
 * 10) Activity log + แจ้งเตือนภายใน
 * ===================================================================== */

function log_activity(string $action, ?string $target = null, ?string $detail = null): void
{
    try {
        db_exec(
            'INSERT INTO activity_logs (user_id, username, action, target, detail, ip_address) VALUES (?,?,?,?,?,?)',
            [$_SESSION['user']['id'] ?? null, $_SESSION['user']['username'] ?? null, $action, $target, $detail, client_ip()]
        );
    } catch (Throwable $e) {
        error_log('log_activity: ' . $e->getMessage());
    }
}

function notify_staff(string $type, string $message, ?string $link = null): void
{
    db_exec('INSERT INTO notifications (type, message, link) VALUES (?,?,?)', [$type, $message, $link]);
}

/* =====================================================================
 * 11) หน้าแบ่งหน้า (pagination) แบบง่าย
 * ===================================================================== */

function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min(max(1, $page), $pages);
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'per' => $perPage, 'total' => $total];
}

/* =====================================================================
 * 12) ส่วนหัวความปลอดภัย (เรียกก่อนมี output)
 * ===================================================================== */

/** ตัวเลือก json_encode สำหรับค่าที่พิมพ์ลงใน <script> (กัน </script>, <!-- และ & หลุดออกจากสตริง) */
const JSON_FOR_SCRIPT = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

/** nonce ของ CSP (ค่าเดียวต่อ 1 คำขอ) — ใส่ใน <script nonce="…"> ของสคริปต์ inline ทุกบล็อก */
function csp_nonce(): string
{
    static $n = null;
    if ($n === null) {
        $n = base64_encode(random_bytes(16));
    }
    return $n;
}

/** ลิงก์ "ย้อนกลับ" สำหรับหน้าแจ้งข้อผิดพลาด (ไม่ใช้ javascript: URL เพราะ CSP แบบ nonce ไม่อนุญาต) */
function back_link_html(): string
{
    return '<a href="#" id="goBack">ย้อนกลับ</a><script nonce="' . e(csp_nonce()) . '">'
         . 'document.getElementById("goBack").addEventListener("click",function(ev){ev.preventDefault();history.back();});</script>';
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    // CSP: โหลดสคริปต์/สไตล์/ฟอนต์ได้เฉพาะจากเว็บนี้ (ไลบรารีอยู่ใน assets/vendor/)
    // สคริปต์ inline ต้องใส่แอตทริบิวต์ nonce เป็นค่า csp_nonce() จึงจะทำงาน — สคริปต์ที่ถูกแทรกเข้ามา (XSS) จะไม่ถูกรัน
    // ข้อยกเว้น: แผนที่ Google ในหน้าติดต่อ และ CAPTCHA (Turnstile/reCAPTCHA) ถ้าเปิดใช้
    header("Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'nonce-" . csp_nonce() . "' https://challenges.cloudflare.com https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/; "
        . "style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; "
        . "frame-src https://www.google.com https://challenges.cloudflare.com; "
        . "connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'");
}

/**
 * แสดงเนื้อหาข่าวอย่างปลอดภัย: ถ้าเป็นข้อความล้วนจะ escape + ขึ้นบรรทัดใหม่,
 * ถ้ามี HTML จะอนุญาตเฉพาะแท็กพื้นฐาน และตัดแอตทริบิวต์ทั้งหมดทิ้ง (กัน XSS)
 */
function render_content(string $text): string
{
    if ($text === strip_tags($text)) {
        return nl2br(e($text));
    }
    $clean = strip_tags($text, '<p><br><strong><b><em><i><u><ul><ol><li><h3><h4><blockquote>');
    return preg_replace('/<(\w+)\s[^>]*>/', '<$1>', $clean);
}

/* =====================================================================
 * 13) ตัวจัดการข้อผิดพลาดกลาง — ไม่ให้ผู้ใช้เห็นหน้าขาวหรือรายละเอียดภายในเมื่อเกิด error ที่ไม่ได้ดักไว้
 * ===================================================================== */

set_exception_handler(function (Throwable $ex): void {
    error_log('Uncaught ' . get_class($ex) . ': ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
    while (ob_get_level()) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    if (APP_ENV !== 'production') {   // โหมดพัฒนา: แสดงรายละเอียดเพื่อแก้ไข
        echo '<pre style="white-space:pre-wrap;font:14px/1.5 monospace;padding:16px">' . e((string) $ex) . '</pre>';
        return;
    }
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>เกิดข้อผิดพลาด</title>'
       . '<div style="font-family:sans-serif;text-align:center;margin-top:80px;padding:0 16px">'
       . '<h1 style="font-size:1.6rem">ขออภัย ระบบเกิดข้อผิดพลาด</h1>'
       . '<p>ระบบบันทึกปัญหานี้ไว้แล้ว กรุณาลองใหม่อีกครั้ง หรือแจ้งผู้ดูแลระบบหากยังพบปัญหา</p>'
       . '<p>' . back_link_html() . ' · <a href="' . e(url()) . '">หน้าหลัก</a></p></div>';
});
