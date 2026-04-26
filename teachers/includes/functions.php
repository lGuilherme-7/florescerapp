<?php
// ============================================================
// /professor/teachers/includes/functions.php
// ============================================================

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/db.php';

function jout(array $d): void {
    if (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

function ok($data = null, string $msg = ''): void {
    $r = ['success' => true];
    if ($msg)         $r['message'] = $msg;
    if ($data !== null) $r['data']  = $data;
    jout($r);
}

function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    jout(['success' => false, 'message' => $msg]);
}

function calcCommission(float $gross, float $pct): array {
    $commission = round($gross * ($pct / 100), 2);
    $net        = round($gross - $commission, 2);
    return ['gross' => $gross, 'commission' => $commission, 'net' => $net];
}

function recalcRating(int $teacherId): void {
    $r = dbRow(
        'SELECT AVG(stars) AS avg, COUNT(*) AS total FROM teacher_ratings WHERE teacher_id = ?',
        [$teacherId]
    );
    dbExec(
        'UPDATE teachers SET rating_avg = ?, rating_count = ? WHERE id = ?',
        [round((float)($r['avg'] ?? 0), 2), (int)($r['total'] ?? 0), $teacherId]
    );
    updateRanking();
}

function updateRanking(): void {
    $teachers = dbQuery(
        'SELECT id FROM teachers WHERE status = "ativo" ORDER BY rating_avg DESC, rating_count DESC'
    );
    foreach ($teachers as $pos => $t) {
        dbExec('UPDATE teachers SET rank_position = ? WHERE id = ?', [$pos + 1, $t['id']]);
    }
}

function detectContact(string $message): ?string {
    foreach (CONTACT_PATTERNS as $pattern) {
        if (preg_match($pattern, $message)) return $pattern;
    }
    return null;
}

function logContactAttempt(int $teacherId, int $studentId, int $orderId, string $sender, string $message, string $pattern): void {
    dbExec(
        'INSERT INTO teacher_contact_attempts (teacher_id, student_id, order_id, sender, message, pattern)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$teacherId, $studentId, $orderId, $sender, $message, $pattern]
    );
    $col      = $sender === 'teacher' ? 'teacher_id' : 'student_id';
    $id       = $sender === 'teacher' ? $teacherId   : $studentId;
    $attempts = (int)(dbRow(
        "SELECT COUNT(*) AS n FROM teacher_contact_attempts WHERE {$col} = ?", [$id]
    )['n'] ?? 0);
    if ($attempts >= CONTACT_MAX_ATTEMPTS && $sender === 'teacher') {
        dbExec("UPDATE teachers SET status = 'suspenso' WHERE id = ?", [$teacherId]);
    }
}

function isLinkReleased(string $scheduledAt): bool {
    return (strtotime($scheduledAt) - time()) <= LINK_RELEASE_BEFORE;
}

function money(float $v): string {
    return 'R$ ' . number_format($v, 2, ',', '.');
}

function timeAgoTeacher(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'agora';
    if ($diff < 3600)   return intdiv($diff, 60) . 'min atrás';
    if ($diff < 86400)  return intdiv($diff, 3600) . 'h atrás';
    if ($diff < 604800) return intdiv($diff, 86400) . 'd atrás';
    return date('d/m/Y', strtotime($dt));
}