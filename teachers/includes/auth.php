<?php
// ============================================================
// /professor/teachers/includes/auth.php
// ============================================================

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/db.php';

if (!defined('SESSION_NAME_TEACHER')) {
    define('SESSION_NAME_TEACHER', 'florescer_teacher');
}

function startTeacherSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name(SESSION_NAME_TEACHER);
        session_start();
    }
}

function isTeacherLoggedIn(): bool {
    return isset($_SESSION['teacher_id']) && (int)$_SESSION['teacher_id'] > 0;
}

function currentTeacher(): ?array {
    if (!isTeacherLoggedIn()) return null;
    return dbRow(
        'SELECT id, name, email, bio, avatar_url, pix_key, status,
                rating_avg, rating_count, rank_position, is_premium,
                balance, balance_pending, commission_pct, commission_aula
         FROM teachers WHERE id = ?',
        [(int)$_SESSION['teacher_id']]
    ) ?: null;
}

function loginTeacherSession(array $teacher): void {
    session_regenerate_id(true);
    $_SESSION['teacher_id']    = (int)$teacher['id'];
    $_SESSION['teacher_name']  = $teacher['name'];
    $_SESSION['teacher_email'] = $teacher['email'];
}

function destroyTeacherSession(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function requireTeacher(): void {
    startTeacherSession();
    if (!isTeacherLoggedIn()) {
        header('Location: ' . TEACHER_VIEWS . '/index.php');
        exit;
    }
    $t = dbRow('SELECT id FROM teachers WHERE id = ?', [(int)$_SESSION['teacher_id']]);
    if (!$t) {
        destroyTeacherSession();
        header('Location: ' . TEACHER_VIEWS . '/index.php?erro=conta_suspensa');
        exit;
    }
}

function requireTeacherApi(): int {
    startTeacherSession();
    if (!isTeacherLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
        exit;
    }
    return (int)$_SESSION['teacher_id'];
}