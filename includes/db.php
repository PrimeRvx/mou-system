<?php
/**
 * includes/db.php — เชื่อมต่อฐานข้อมูลด้วย PDO (Prepared Statements เท่านั้น)
 */
require_once __DIR__ . '/../config/config.php';

/**
 * คืนค่า PDO แบบ singleton
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,   // ใช้ prepared จริงของ MySQL
            ]);
            $pdo->exec("SET time_zone = '+07:00'");
        } catch (PDOException $e) {
            error_log('DB connect error: ' . $e->getMessage());
            http_response_code(500);
            exit(APP_ENV === 'production'
                ? 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาลองใหม่ภายหลัง'
                : 'DB error: ' . htmlspecialchars($e->getMessage()));
        }
    }
    return $pdo;
}

/** ดึงหลายแถว */
function db_all(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** ดึงแถวเดียว (null ถ้าไม่พบ) */
function db_one(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

/** ดึงค่าเดียว */
function db_val(string $sql, array $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

/** INSERT/UPDATE/DELETE คืนจำนวนแถวที่ได้รับผล */
function db_exec(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

/** INSERT แล้วคืน id ล่าสุด */
function db_insert(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return (int) db()->lastInsertId();
}
