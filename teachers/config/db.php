<?php
// ============================================================
// /florescer/teachers/config/db.php — PRODUÇÃO Hostinger
// ============================================================

define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'u681383341_florescer');  
define('DB_USER',    'u681383341_u681383341_app');     
define('DB_PASS',    'g&@bSj@yP7uN6');    
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('[Teachers] DB error: '.$e->getMessage());
        die(json_encode(['success'=>false,'message'=>'Erro de conexão.']));
    }
    return $pdo;
}

function dbRow(string $sql, array $params = []): ?array {
    $st = getDB()->prepare($sql);
    $st->execute($params);
    $r = $st->fetch();
    return $r ?: null;
}

function dbQuery(string $sql, array $params = []): array {
    $st = getDB()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function dbExec(string $sql, array $params = []): int {
    $st = getDB()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

function dbLastId(): string {
    return getDB()->lastInsertId();
}

function dbBegin(): void    { getDB()->beginTransaction(); }
function dbCommit(): void   { getDB()->commit(); }
function dbRollback(): void { getDB()->rollBack(); }