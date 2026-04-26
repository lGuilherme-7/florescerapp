<?php
// ============================================================
// /florescer/teachers/api/financeiro.php
// Ações: summary | extrato | saque_solicitar | saque_list
// ============================================================

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startTeacherSession();
ob_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Método não permitido.', 405);

$teacherId = requireTeacherApi();
$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$action    = trim($body['action'] ?? '');

if ($action === 'summary') {
    $t = dbRow(
        'SELECT balance, balance_pending, commission_pct, commission_aula FROM teachers WHERE id = ?',
        [$teacherId]
    );
    $ganhos_mes = (float)(dbRow(
        'SELECT COALESCE(SUM(net_amount),0) AS total FROM teacher_orders
         WHERE teacher_id = ? AND status = "pago"
         AND MONTH(paid_at) = MONTH(NOW()) AND YEAR(paid_at) = YEAR(NOW())',
        [$teacherId]
    )['total'] ?? 0);
    $saques_mes = (float)(dbRow(
        'SELECT COALESCE(SUM(amount),0) AS total FROM teacher_withdrawals
         WHERE teacher_id = ? AND status IN ("aprovado","pago")
         AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())',
        [$teacherId]
    )['total'] ?? 0);

    ok([
        'balance'         => (float)$t['balance'],
        'balance_pending' => (float)$t['balance_pending'],
        'ganhos_mes'      => $ganhos_mes,
        'saques_mes'      => $saques_mes,
        'commission_pct'  => (float)$t['commission_pct'],
        'commission_aula' => (float)$t['commission_aula'],
    ]);
}

if ($action === 'extrato') {
    $page    = max(1, (int)($body['page'] ?? 1));
    $perPage = 20;
    $offset  = ($page - 1) * $perPage;

    $rows = dbQuery(
        'SELECT o.id, o.type, o.gross_amount, o.commission_amt, o.net_amount,
                o.status, o.paid_at, o.created_at,
                u.name AS student_name
         FROM teacher_orders o
         JOIN users u ON u.id = o.student_id
         WHERE o.teacher_id = ?
         ORDER BY o.created_at DESC
         LIMIT ? OFFSET ?',
        [$teacherId, $perPage, $offset]
    );

    $saques = dbQuery(
        'SELECT id, amount, status, pix_key, created_at, paid_at
         FROM teacher_withdrawals WHERE teacher_id = ? ORDER BY created_at DESC LIMIT 10',
        [$teacherId]
    );

    ok(['pedidos' => $rows, 'saques' => $saques]);
}

if ($action === 'saque_solicitar') {
    $amount  = round((float)($body['amount'] ?? 0), 2);
    $pix_key = mb_substr(trim($body['pix_key'] ?? ''), 0, 150, 'UTF-8');

    if ($amount < MIN_WITHDRAWAL) fail('Valor mínimo para saque: ' . money(MIN_WITHDRAWAL));
    if (!$pix_key) fail('Informe a chave PIX.');

    $t = dbRow('SELECT balance FROM teachers WHERE id = ?', [$teacherId]);
    if ((float)$t['balance'] < $amount) fail('Saldo insuficiente.');

    // Reserva o valor
    dbExec('UPDATE teachers SET balance = balance - ? WHERE id = ?', [$amount, $teacherId]);
    dbExec(
        'INSERT INTO teacher_withdrawals (teacher_id, amount, pix_key) VALUES (?, ?, ?)',
        [$teacherId, $amount, $pix_key]
    );

    ok(null, 'Solicitação enviada! Processaremos em até 1 dia útil.');
}

if ($action === 'saque_list') {
    $rows = dbQuery(
        'SELECT id, amount, pix_key, status, admin_note, created_at, paid_at
         FROM teacher_withdrawals WHERE teacher_id = ? ORDER BY created_at DESC',
        [$teacherId]
    );
    ok($rows);
}

fail('Ação desconhecida.');