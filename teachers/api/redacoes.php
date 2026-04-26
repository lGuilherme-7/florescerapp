<?php
// ============================================================
// /florescer/teachers/api/redacoes.php
// Ações: list | get | corrigir
// ============================================================

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startTeacherSession();
ob_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Método não permitido.', 405);

$teacherId = requireTeacherApi();
$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$action    = trim($body['action'] ?? '');

if ($action === 'list') {
    $status = trim($body['status'] ?? '');
    $valid  = ['pendente','em_correcao','corrigida'];
    $where  = 'WHERE r.teacher_id = ?';
    $params = [$teacherId];
    if ($status && in_array($status, $valid, true)) {
        $where .= ' AND r.status = ?';
        $params[] = $status;
    }
    $rows = dbQuery(
        "SELECT r.id, r.tema, r.status, r.nota_total, r.created_at, r.corrigida_em,
                u.name AS student_name, u.avatar_emoji
         FROM teacher_redacoes r
         JOIN users u ON u.id = r.student_id
         $where
         ORDER BY FIELD(r.status,'pendente','em_correcao','corrigida'), r.created_at ASC",
        $params
    );
    ok($rows);
}

if ($action === 'get') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) fail('ID inválido.');

    $r = dbRow(
        'SELECT r.*, u.name AS student_name, u.email AS student_email, u.avatar_emoji
         FROM teacher_redacoes r
         JOIN users u ON u.id = r.student_id
         WHERE r.id = ? AND r.teacher_id = ?',
        [$id, $teacherId]
    );
    if (!$r) fail('Redação não encontrada.', 404);

    // Marca como em correção
    if ($r['status'] === 'pendente') {
        dbExec("UPDATE teacher_redacoes SET status='em_correcao' WHERE id=?", [$id]);
        $r['status'] = 'em_correcao';
    }
    ok($r);
}

if ($action === 'corrigir') {
    $id       = (int)($body['id'] ?? 0);
    $feedback = mb_substr(trim($body['feedback'] ?? ''), 0, 5000, 'UTF-8');
    $comp1    = min(200, max(0, (int)($body['comp1'] ?? 0)));
    $comp2    = min(200, max(0, (int)($body['comp2'] ?? 0)));
    $comp3    = min(200, max(0, (int)($body['comp3'] ?? 0)));
    $comp4    = min(200, max(0, (int)($body['comp4'] ?? 0)));
    $comp5    = min(200, max(0, (int)($body['comp5'] ?? 0)));

    if (!$id)       fail('ID inválido.');
    if (!$feedback) fail('Escreva o feedback.');

    $r = dbRow(
        'SELECT id, order_id FROM teacher_redacoes WHERE id = ? AND teacher_id = ? AND status != "corrigida"',
        [$id, $teacherId]
    );
    if (!$r) fail('Redação não encontrada ou já corrigida.');

    $nota = $comp1 + $comp2 + $comp3 + $comp4 + $comp5;

    dbExec(
        'UPDATE teacher_redacoes
         SET status="corrigida", comp1=?, comp2=?, comp3=?, comp4=?, comp5=?,
             nota_total=?, feedback=?, corrigida_em=NOW()
         WHERE id=?',
        [$comp1, $comp2, $comp3, $comp4, $comp5, $nota, $feedback, $id]
    );

    // Desconta crédito do pedido
    dbExec(
        'UPDATE teacher_orders SET credits_used = credits_used + 1 WHERE id = ?',
        [(int)$r['order_id']]
    );

    ok(['nota_total' => $nota], 'Redação corrigida com sucesso!');
}

fail('Ação desconhecida.');