<?php
/**
 * models/mou_model.php — คำสั่งคิวรีที่ใช้ร่วมกันระหว่างหน้าสาธารณะและหลังบ้าน
 * (ทุกคำสั่งใช้ prepared statements)
 */
require_once __DIR__ . '/../includes/functions.php';

/** เงื่อนไขของ MOU ที่แสดงสาธารณะได้ */
const PUBLIC_MOU_WHERE = "m.deleted_at IS NULL AND m.is_public = 1 AND m.status IN ('active','expiring','expired')";

/**
 * ค้นหา MOU ตามตัวกรอง
 * @param array $f  q, type, program, status, year (ค.ศ.), province, category, deleted (เฉพาะหลังบ้าน)
 * @param bool  $public true = เฉพาะที่เผยแพร่ (หน้าสาธารณะ), false = ทั้งหมดที่ยังไม่ถูกลบ (หลังบ้าน)
 */
function mou_search(array $f, bool $public = true, int $limit = 1000): array
{
    // หลังบ้าน: $f['deleted'] = true → แสดงเฉพาะรายการที่ถูกลบ (soft delete) เพื่อกู้คืน/ลบถาวร
    $deleted = !$public && !empty($f['deleted']);
    $where  = [$public ? PUBLIC_MOU_WHERE : ($deleted ? 'm.deleted_at IS NOT NULL' : 'm.deleted_at IS NULL')];
    $params = [];

    if (($f['q'] ?? '') !== '') {
        $like = '%' . like_escape($f['q']) . '%';
        $where[] = '(m.title LIKE ? OR m.mou_no LIKE ? OR p.name LIKE ? OR EXISTS (
                        SELECT 1 FROM mou_programs xp JOIN programs xr ON xr.id = xp.program_id
                        WHERE xp.mou_id = m.id AND xr.name LIKE ?))';
        array_push($params, $like, $like, $like, $like);
    }
    if (isset(partner_types()[$f['type'] ?? ''])) {
        $where[] = 'p.type = ?';
        $params[] = $f['type'];
    }
    if ((int) ($f['program'] ?? 0) > 0) {
        $where[] = 'EXISTS (SELECT 1 FROM mou_programs yp WHERE yp.mou_id = m.id AND yp.program_id = ?)';
        $params[] = (int) $f['program'];
    }
    if (isset(mou_statuses()[$f['status'] ?? ''])) {
        $where[] = 'm.status = ?';
        $params[] = $f['status'];
    }
    if ((int) ($f['year'] ?? 0) > 0) {
        // ช่วงวันที่ (แทน YEAR(m.sign_date) = ?) เพื่อให้ใช้ index idx_sign_date ได้
        $where[] = 'm.sign_date BETWEEN ? AND ?';
        array_push($params, sprintf('%04d-01-01', (int) $f['year']), sprintf('%04d-12-31', (int) $f['year']));
    }
    if (($f['province'] ?? '') !== '') {
        $where[] = 'p.province = ?';
        $params[] = $f['province'];
    }
    if (!empty($f['date_from']) && ($d = parse_thai_input($f['date_from']))) {
        $where[] = 'm.sign_date >= ?';
        $params[] = $d;
    }
    if (!empty($f['date_to']) && ($d = parse_thai_input($f['date_to']))) {
        $where[] = 'm.sign_date <= ?';
        $params[] = $d;
    }
    if ((int) ($f['category'] ?? 0) > 0) {
        $where[] = 'EXISTS (SELECT 1 FROM mou_category_map zc WHERE zc.mou_id = m.id AND zc.category_id = ?)';
        $params[] = (int) $f['category'];
    }

    $sql = "SELECT m.id, m.mou_no, m.title, m.sign_date, m.start_date, m.end_date, m.status,
                   m.student_benefit_count, m.is_public, m.responsible, m.deleted_at,
                   p.name AS partner_name, p.type AS partner_type, p.province, p.logo,
                   (SELECT GROUP_CONCAT(CONCAT(pr.level, ' ', pr.name) ORDER BY pr.level, pr.name SEPARATOR ', ')
                      FROM mou_programs mp JOIN programs pr ON pr.id = mp.program_id
                     WHERE mp.mou_id = m.id) AS programs,
                   " . mou_image_columns() . "
              FROM mou m JOIN partners p ON p.id = m.partner_id
             WHERE " . implode(' AND ', $where) . '
             ORDER BY m.sign_date DESC, m.id DESC
             LIMIT ' . (int) $limit;
    return db_all($sql, $params);
}

/** MOU รายการเดียว (พร้อมข้อมูลหน่วยงาน) */
function mou_get(int $id, bool $publicOnly = true): ?array
{
    $sql = 'SELECT m.*, p.name AS partner_name, p.type AS partner_type, p.logo, p.address AS partner_address,
                   p.province, p.website, p.contact_name, p.contact_position, p.phone AS partner_phone, p.email AS partner_email
              FROM mou m JOIN partners p ON p.id = m.partner_id
             WHERE m.id = ? AND ' . ($publicOnly ? PUBLIC_MOU_WHERE : 'm.deleted_at IS NULL');
    return db_one($sql, [$id]);
}

function mou_programs_of(int $id): array
{
    return db_all('SELECT pr.id, pr.name, pr.level FROM mou_programs mp JOIN programs pr ON pr.id = mp.program_id
                    WHERE mp.mou_id = ? ORDER BY pr.level, pr.name', [$id]);
}

function mou_categories_of(int $id): array
{
    return db_all('SELECT c.id, c.name FROM mou_category_map cm JOIN mou_categories c ON c.id = cm.category_id
                    WHERE cm.mou_id = ? ORDER BY c.id', [$id]);
}

function mou_benefits_of(int $id): array
{
    return db_all('SELECT item FROM mou_benefits WHERE mou_id = ? ORDER BY sort_order, id', [$id]);
}

function mou_activities_of(int $id): array
{
    return db_all('SELECT * FROM mou_activities WHERE mou_id = ? ORDER BY activity_date DESC', [$id]);
}

/* ---------- รูปภาพประกอบรายละเอียด (v2) ---------- */

/** มีตาราง mou_images หรือยัง (กันเว็บล่มถ้ายังไม่ได้รัน migration_v2_images.sql) */
function has_mou_images(): bool
{
    static $ok = null;
    if ($ok === null) {
        try { $ok = (bool) db_val("SHOW TABLES LIKE 'mou_images'"); } catch (Throwable $e) { $ok = false; }
    }
    return $ok;
}

/** คอลัมน์ภาพปก + จำนวนรูป สำหรับคิวรีรายการ MOU */
function mou_image_columns(string $alias = 'm'): string
{
    if (!has_mou_images()) {
        return 'NULL AS cover_image, 0 AS image_count';
    }
    return "(SELECT mi.stored_name FROM mou_images mi WHERE mi.mou_id = $alias.id ORDER BY mi.is_cover DESC, mi.sort_order, mi.id LIMIT 1) AS cover_image,
            (SELECT COUNT(*) FROM mou_images mc WHERE mc.mou_id = $alias.id) AS image_count";
}

/** รูปทั้งหมดของ MOU (ภาพปกขึ้นก่อน) */
function mou_images_of(int $id): array
{
    if (!has_mou_images()) {
        return [];
    }
    return db_all('SELECT * FROM mou_images WHERE mou_id = ? ORDER BY is_cover DESC, sort_order, id', [$id]);
}

/** บันทึกรูปที่อัปโหลดให้ MOU — คืนข้อความผิดพลาด (ถ้ามี) */
function mou_images_add(int $mouId, array $files): array
{
    if (!has_mou_images()) {
        return ['ยังไม่ได้สร้างตาราง mou_images — กรุณารัน database/migration_v2_images.sql'];
    }
    $files = normalize_files($files);
    if (!$files) {
        return [];
    }
    $current = (int) db_val('SELECT COUNT(*) FROM mou_images WHERE mou_id = ?', [$mouId]);
    $res = save_images($files, 'mou_images', max(0, MAX_MOU_IMAGES - $current));
    $order = (int) db_val('SELECT COALESCE(MAX(sort_order), 0) FROM mou_images WHERE mou_id = ?', [$mouId]);
    $hasCover = (bool) db_val('SELECT COUNT(*) FROM mou_images WHERE mou_id = ? AND is_cover = 1', [$mouId]);
    foreach ($res['saved'] as $img) {
        db_insert('INSERT INTO mou_images (mou_id, stored_name, caption, width, height, size, is_cover, sort_order, uploaded_by) VALUES (?,?,?,?,?,?,?,?,?)',
            [$mouId, $img['stored'], null, $img['width'] ?: null, $img['height'] ?: null, $img['size'], $hasCover ? 0 : 1, ++$order, $_SESSION['user']['id'] ?? null]);
        $hasCover = true;   // รูปแรกของ MOU ที่ยังไม่มีปก จะเป็นภาพปกอัตโนมัติ
    }
    if ($res['saved']) {
        log_activity('image_upload', 'mou#' . $mouId, count($res['saved']) . ' รูป');
    }
    return $res['errors'];
}

/** ลบรูปทั้งหมดของ MOU ออกจากดิสก์ (ใช้ตอนลบถาวร) */
function mou_images_purge(int $mouId): void
{
    foreach (mou_images_of($mouId) as $img) {
        delete_upload('mou_images', $img['stored_name']);
    }
}

function mou_files_of(int $id, bool $publicOnly = true): array
{
    return db_all('SELECT * FROM mou_files WHERE mou_id = ?' . ($publicOnly ? ' AND is_public = 1' : '') . ' ORDER BY id', [$id]);
}

function mou_renewals_of(int $id): array
{
    return db_all('SELECT r.*, u.full_name FROM mou_renewals r LEFT JOIN users u ON u.id = r.recorded_by
                    WHERE r.mou_id = ? ORDER BY r.recorded_at DESC, r.id DESC', [$id]);
}

function all_programs(bool $onlyActive = true): array
{
    return db_all('SELECT * FROM programs ' . ($onlyActive ? 'WHERE is_active = 1 ' : '') . 'ORDER BY level, name');
}

function all_categories(): array
{
    return db_all('SELECT * FROM mou_categories ORDER BY id');
}

function all_partners(): array
{
    return db_all('SELECT id, name, type FROM partners ORDER BY name');
}

function partner_provinces(): array
{
    return array_column(db_all("SELECT DISTINCT province FROM partners WHERE province IS NOT NULL AND province <> '' ORDER BY province"), 'province');
}

function mou_sign_years(): array
{
    return array_column(db_all('SELECT DISTINCT YEAR(sign_date) y FROM mou WHERE deleted_at IS NULL ORDER BY y DESC'), 'y');
}

/** สถิติสำหรับหน้าแรก (นับเฉพาะ MOU ที่เผยแพร่) */
function public_stats(): array
{
    $r = db_one("SELECT COUNT(*) total,
                        SUM(m.status IN ('active','expiring')) active,
                        COUNT(DISTINCT CASE WHEN p.type = 'enterprise' THEN p.id END) enterprises,
                        COUNT(DISTINCT CASE WHEN p.type = 'education' THEN p.id END) schools,
                        COALESCE(SUM(m.student_benefit_count), 0) students
                   FROM mou m JOIN partners p ON p.id = m.partner_id WHERE " . PUBLIC_MOU_WHERE);
    return array_map('intval', $r ?: ['total' => 0, 'active' => 0, 'enterprises' => 0, 'schools' => 0, 'students' => 0]);
}

/** ตัวเลขสรุปสำหรับ Dashboard หลังบ้าน */
function admin_stats(): array
{
    refresh_mou_status();
    $r = db_one("SELECT COUNT(*) total,
                        COALESCE(SUM(status = 'active'), 0) active,
                        COALESCE(SUM(status = 'expiring'), 0) expiring,
                        COALESCE(SUM(status = 'expired'), 0) expired,
                        COALESCE(SUM(status = 'draft'), 0) draft
                   FROM mou WHERE deleted_at IS NULL");
    return array_map('intval', $r);
}

/** บันทึกความสัมพันธ์ many-to-many (ลบของเดิมแล้วใส่ใหม่) */
function sync_relation(string $table, string $mouCol, int $mouId, string $otherCol, array $ids): void
{
    // $table/$cols มาจากโค้ดภายในเท่านั้น ไม่ใช่ input ผู้ใช้
    db_exec("DELETE FROM `$table` WHERE `$mouCol` = ?", [$mouId]);
    foreach (array_unique(array_map('intval', $ids)) as $oid) {
        if ($oid > 0) {
            db_exec("INSERT INTO `$table` (`$mouCol`, `$otherCol`) VALUES (?, ?)", [$mouId, $oid]);
        }
    }
}
