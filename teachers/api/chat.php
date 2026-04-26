<?php
// ============================================================
// /florescer/teachers/api/chat.php
// Ações: list_conversations | messages | send
// ============================================================

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startTeacherSession();
ob_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Método não permitido.', 405);

$teacherId = requireTeacherApi();
$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$action    = trim($body['action'] ?? '');

if ($action === 'list_conversations') {
    $rows = dbQuery(
        "SELECT o.id AS order_id, o.type, o.status,
                u.id AS student_id, u.name AS student_name, u.avatar_emoji,
                (SELECT COUNT(*) FROM teacher_messages m
                 WHERE m.order_id=o.id AND m.sender='student' AND m.read_at IS NULL) AS unread,
                (SELECT m2.message FROM teacher_messages m2
                 WHERE m2.order_id=o.id AND m2.blocked=0 ORDER BY m2.created_at DESC LIMIT 1) AS last_msg,
                (SELECT m2.created_at FROM teacher_messages m2
                 WHERE m2.order_id=o.id ORDER BY m2.created_at DESC LIMIT 1) AS last_at
         FROM teacher_orders o
         JOIN users u ON u.id = o.student_id
         WHERE o.teacher_id = ? AND o.status = 'pago'
         ORDER BY last_at DESC",
        [$teacherId]
    );
    ok($rows);
}

if ($action === 'messages') {
    $orderId = (int)($body['order_id'] ?? 0);
    if (!$orderId) fail('ID inválido.');

    // Verifica que o pedido pertence ao professor
    $o = dbRow(
        'SELECT id, student_id FROM teacher_orders WHERE id = ? AND teacher_id = ? AND status = "pago"',
        [$orderId, $teacherId]
    );
    if (!$o) fail('Conversa não encontrada.', 404);

    // Marca como lidas
    dbExec(
        "UPDATE teacher_messages SET read_at = NOW()
         WHERE order_id = ? AND sender = 'student' AND read_at IS NULL",
        [$orderId]
    );

    $msgs = dbQuery(
        'SELECT id, sender, message, blocked, read_at, created_at
         FROM teacher_messages WHERE order_id = ? AND blocked = 0
         ORDER BY created_at ASC',
        [$orderId]
    );
    ok($msgs);
}

if ($action === 'send') {
    $orderId = (int)($body['order_id'] ?? 0);
    $message = mb_substr(trim($body['message'] ?? ''), 0, 1000, 'UTF-8');

    if (!$orderId) fail('ID inválido.');
    if (!$message) fail('Mensagem vazia.');

    $o = dbRow(
        'SELECT id, student_id FROM teacher_orders WHERE id = ? AND teacher_id = ? AND status = "pago"',
        [$orderId, $teacherId]
    );
    if (!$o) fail('Conversa não encontrada.', 404);

    // Anti-contato
    $pattern = detectContact($message);
    if ($pattern) {
        logContactAttempt($teacherId, (int)$o['student_id'], $orderId, 'teacher', $message, $pattern);

        // Conta tentativas
        $attempts = (int)(dbRow(
            'SELECT COUNT(*) AS n FROM teacher_contact_attempts WHERE teacher_id = ?',
            [$teacherId]
        )['n'] ?? 0);

        if ($attempts >= CONTACT_MAX_ATTEMPTS) {
            fail('Sua conta foi suspensa por tentativa de troca de contato externo.');
        }
        fail('Mensagem bloqueada: não é permitido compartilhar dados de contato externo. (' . ($attempts) . '/' . CONTACT_MAX_ATTEMPTS . ' tentativas)');
    }

    dbExec(
        "INSERT INTO teacher_messages (order_id, teacher_id, student_id, sender, message)
         VALUES (?, ?, ?, 'teacher', ?)",
        [$orderId, $teacherId, (int)$o['student_id'], $message]
    );

    ok(null, 'Mensagem enviada.');
}

fail('Ação desconhecida.');