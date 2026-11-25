<?php
/**
 * includes/auth.php — เซสชัน, เข้าสู่ระบบ, ตรวจสิทธิ์ตามบทบาท (admin / staff / viewer)
 * ทุกหน้า (ทั้งสาธารณะและหลังบ้าน) ให้ require ไฟล์นี้ ซึ่งจะ start session ให้อัตโนมัติ
 */
require_once __DIR__ . '/functions.php';

/* ---------- เริ่มเซสชันแบบปลอดภัย ---------- */
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    // อายุไฟล์เซสชันฝั่งเซิร์ฟเวอร์ต้องยาวกว่า SESSION_TIMEOUT (ค่าเริ่มต้นของ PHP คือ 24 นาที ซึ่งสั้นกว่า 30 นาที)
    ini_set('session.gc_maxlifetime', (string) (SESSION_TIMEOUT + 900));
    session_start();
}
send_security_headers();

/**
 * สิทธิ์ของแต่ละบทบาท (permission matrix)
 *  - admin  : ทุกอย่าง
 *  - staff  : MOU, ต่ออายุ, ข่าว, ข้อเสนอ, รายงาน
 *  - viewer : ดู Dashboard และรายงานเท่านั้น
 */
function role_permissions(): array
{
    return [
        'admin'  => ['dashboard', 'report', 'mou', 'renewal', 'partner', 'program', 'news', 'proposal', 'user', 'log', 'setting'],
        'staff'  => ['dashboard', 'report', 'mou', 'renewal', 'partner', 'program', 'news', 'proposal'],
        'viewer' => ['dashboard', 'report'],
    ];
}

/** ผู้ใช้ปัจจุบัน (null ถ้ายังไม่ login) */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user']['id']);
}

/**
 * สำหรับหน้าที่ redirect ไป login ไม่ได้ (ดาวน์โหลดไฟล์, รูปภาพ, JSON, พรีวิวหน้าสาธารณะ):
 * เซสชันต้องยังไม่หมดเวลา บัญชียังเปิดใช้งาน และรหัสผ่านไม่ถูกเปลี่ยน — เงื่อนไขเดียวกับ require_login()
 */
function session_is_valid(): bool
{
    if (!is_logged_in() || !empty($_SESSION['must_change_password'])
        || time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        return false;
    }
    static $ok = null;
    if ($ok === null) {
        $u  = db_one('SELECT role, status, password FROM users WHERE id = ?', [$_SESSION['user']['id']]);
        $ok = $u && (int) $u['status'] === 1
            && (!isset($_SESSION['user']['pw']) || hash_equals((string) $_SESSION['user']['pw'], password_signature($u['password'])));
        if ($ok) {
            $_SESSION['user']['role'] = $u['role'];   // สิทธิ์ตามฐานข้อมูลเสมอ
        }
    }
    return $ok;
}

/** ผู้ใช้ปัจจุบันแก้ไขข้อมูลได้หรือไม่ (viewer ดูได้อย่างเดียว) */
function can_write(): bool
{
    return is_logged_in() && ($_SESSION['user']['role'] ?? '') !== 'viewer';
}

/** ตรวจว่าผู้ใช้ปัจจุบันมีสิทธิ์ทำสิ่งนั้นหรือไม่ เช่น can('mou') */
function can(string $permission): bool
{
    $role = $_SESSION['user']['role'] ?? null;
    return $role !== null && in_array($permission, role_permissions()[$role] ?? [], true);
}

/**
 * บังคับ login — ใช้ต้นไฟล์ทุกหน้าใน admin/
 * - ตรวจ timeout เมื่อไม่มีการใช้งาน
 * - ตรวจว่าบัญชียังเปิดใช้งานอยู่ในฐานข้อมูล
 */
function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('admin/login.php');
    }
    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        logout_user(false);
        session_start();
        flash('warning', 'หมดเวลาการใช้งาน กรุณาเข้าสู่ระบบอีกครั้ง');
        redirect('admin/login.php');
    }
    $_SESSION['last_activity'] = time();

    // ตรวจซ้ำว่าบัญชียัง active (กรณี admin ปิดบัญชีระหว่างที่ผู้ใช้ออนไลน์)
    static $checked = false;
    if (!$checked) {
        $checked = true;
        $u = db_one('SELECT id, role, status, password FROM users WHERE id = ?', [$_SESSION['user']['id']]);
        if (!$u || (int) $u['status'] !== 1) {
            logout_user(false);
            session_start();
            flash('error', 'บัญชีนี้ถูกระงับการใช้งาน');
            redirect('admin/login.php');
        }
        // รหัสผ่านถูกเปลี่ยนจากที่อื่น (เช่น ผู้ดูแลรีเซ็ตให้ หรือเปลี่ยนจากอีกเครื่อง) → เซสชันเก่าใช้ต่อไม่ได้
        if (isset($_SESSION['user']['pw']) && !hash_equals((string) $_SESSION['user']['pw'], password_signature($u['password']))) {
            logout_user(false);
            session_start();
            flash('warning', 'รหัสผ่านของบัญชีนี้ถูกเปลี่ยน กรุณาเข้าสู่ระบบอีกครั้ง');
            redirect('admin/login.php');
        }
        $_SESSION['user']['pw']   = password_signature($u['password']);
        $_SESSION['user']['role'] = $u['role']; // สิทธิ์อัปเดตตามฐานข้อมูลเสมอ
    }

    // ยังใช้รหัสผ่านเริ่มต้นจากไฟล์ติดตั้ง → ใช้ได้เฉพาะหน้าเปลี่ยนรหัสผ่าน (และออกจากระบบ) จนกว่าจะเปลี่ยน
    if (!empty($_SESSION['must_change_password'])
        && !in_array(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')), ['profile.php', 'logout.php'], true)) {
        flash('warning', 'บัญชีนี้ยังใช้รหัสผ่านเริ่มต้นของระบบ กรุณาตั้งรหัสผ่านใหม่ก่อนใช้งานต่อ');
        redirect('admin/profile.php');
    }
}

/** บังคับสิทธิ์ เช่น require_permission('mou') — ไม่มีสิทธิ์ตอบ 403 */
function require_permission(string $permission): void
{
    require_login();
    if (!can($permission)) {
        http_response_code(403);
        exit('<!doctype html><meta charset="utf-8"><title>403</title>'
           . '<div style="font-family:sans-serif;text-align:center;margin-top:80px">'
           . '<h1>403</h1><p>ท่านไม่มีสิทธิ์เข้าถึงหน้านี้</p>'
           . '<a href="' . e(url('admin/')) . '">กลับหน้าหลังบ้าน</a></div>');
    }
}

/** สำหรับหน้าที่แก้ไขข้อมูล: viewer ห้ามแก้ไข */
function require_writer(): void
{
    require_login();
    if (!can_write()) {
        http_response_code(403);
        exit('บทบาทของท่านดูข้อมูลได้อย่างเดียว');
    }
}

/** ลายเซ็นของ hash รหัสผ่าน (เก็บในเซสชัน เพื่อรู้ว่ารหัสผ่านถูกเปลี่ยนหลัง login หรือไม่) */
function password_signature(string $hash): string
{
    return substr(hash('sha256', $hash), 0, 32);
}

/** ชื่อ bucket ของ rate limit สำหรับ login ของชื่อผู้ใช้นี้ (นับแยกตาม "ชื่อผู้ใช้ + IP") */
function login_bucket(string $username): string
{
    return 'login:' . sha1(mb_strtolower(trim($username)));
}

/**
 * พยายามเข้าสู่ระบบ
 * @return string|null  null = สำเร็จ, ข้อความ = สาเหตุที่ล้มเหลว
 */
function attempt_login(string $username, string $password): ?string
{
    // ล็อก 2 ชั้น: (1) ชื่อผู้ใช้นี้จาก IP นี้ ผิดเกินกำหนด — คนอื่นที่ใช้ IP เดียวกันไม่โดนล็อกไปด้วย
    //            (2) เพดานรวมต่อ IP (6 เท่า) กันการไล่เดาหลายบัญชีจากเครื่องเดียว
    $bucket = login_bucket($username);
    if (rate_exceeded($bucket, LOGIN_MAX_ATTEMPTS, LOGIN_LOCK_MINUTES)
        || rate_exceeded('login_ip', LOGIN_MAX_ATTEMPTS * 6, LOGIN_LOCK_MINUTES)) {
        return 'ใส่รหัสผ่านผิดหลายครั้ง กรุณารอ ' . LOGIN_LOCK_MINUTES . ' นาทีแล้วลองใหม่';
    }
    $user = db_one('SELECT * FROM users WHERE username = ? LIMIT 1', [$username]);

    // ตรวจรหัสผ่านเสมอแม้ไม่พบผู้ใช้ เพื่อลดโอกาสเดาชื่อผู้ใช้จากเวลาตอบสนอง
    $hash = $user['password'] ?? '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234';
    $ok   = password_verify($password, $hash);

    if (!$user || !$ok) {
        rate_hit($bucket);
        rate_hit('login_ip');
        log_login_fail($username);
        return 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
    if ((int) $user['status'] !== 1) {
        return 'บัญชีนี้ถูกระงับการใช้งาน';
    }
    // รหัสผ่านเริ่มต้นจากไฟล์ติดตั้ง (ใคร ๆ ก็รู้): บัญชีที่ไม่ใช่ admin เข้าใช้ไม่ได้ / admin เข้าได้แต่ต้องเปลี่ยนทันที
    $isDefaultPw = in_array($password, DEFAULT_PASSWORDS, true);
    if ($isDefaultPw && $user['role'] !== 'admin') {
        log_login_fail($username);
        return 'บัญชีนี้ยังใช้รหัสผ่านเริ่มต้นของระบบ จึงปิดการเข้าใช้ไว้เพื่อความปลอดภัย — ให้ผู้ดูแลระบบตั้งรหัสผ่านใหม่ที่เมนู "ผู้ใช้งาน"';
    }

    session_regenerate_id(true);               // ป้องกัน session fixation
    $hash = $user['password'];
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        db_exec('UPDATE users SET password = ? WHERE id = ?', [$hash, $user['id']]);
    }
    $_SESSION['user'] = [
        'id'        => (int) $user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
        'pw'        => password_signature($hash),
    ];
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token']    = bin2hex(random_bytes(32));
    unset($_SESSION['must_change_password']);
    if ($isDefaultPw) {
        $_SESSION['must_change_password'] = true;
    }

    db_exec('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
    rate_clear($bucket);   // ล้างเฉพาะตัวนับของบัญชีนี้ — ไม่ล้างตัวนับรวมของ IP (กันการ login บัญชีตัวเองเพื่อรีเซ็ตแล้วเดาบัญชีอื่นต่อ)
    log_activity('login', 'users#' . $user['id'], 'เข้าสู่ระบบ');
    return null;
}

function log_login_fail(string $username): void
{
    db_exec(
        'INSERT INTO activity_logs (username, action, detail, ip_address) VALUES (?,?,?,?)',
        [mb_substr($username, 0, 50), 'login_failed', 'เข้าสู่ระบบไม่สำเร็จ', client_ip()]
    );
}

/** ออกจากระบบ */
function logout_user(bool $log = true): void
{
    if ($log && is_logged_in()) {
        log_activity('logout', 'users#' . $_SESSION['user']['id'], 'ออกจากระบบ');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** ตรวจความแข็งแรงรหัสผ่านเบื้องต้น (ใช้ตอนสร้าง/เปลี่ยนรหัส) */
function password_problem(string $pw): ?string
{
    if (mb_strlen($pw) < 8) {
        return 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร';
    }
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
        return 'รหัสผ่านต้องมีทั้งตัวอักษรและตัวเลข';
    }
    if (in_array($pw, DEFAULT_PASSWORDS, true)) {
        return 'ห้ามใช้รหัสผ่านเริ่มต้นของระบบ กรุณาตั้งรหัสผ่านใหม่ที่ไม่ซ้ำ';
    }
    return null;
}
