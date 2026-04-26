<?php
// ============================================================
// /florescer/admin/api/professores.php
// ============================================================

require_once __DIR__ . '/../includes/auth_admin.php';
require_once dirname(dirname(__DIR__)) . '/config/db.php';

requireAdmin();
ob_start();
header('Content-Type: application/json; charset=UTF-8');

$adminId = (int)($_SESSION['admin_id'] ?? 0);

function out(array $d): void {
    if (ob_get_level() > 0) ob_end_clean();
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['success' => false, 'message' => 'Método não permitido.']);
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($body['action'] ?? '');

// ── list: lista professores com filtros ───────────────────────
if ($action === 'list') {
    $status  = trim($body['status'] ?? 'pendente');
    $search  = trim($body['q']      ?? '');
    $page    = max(1, (int)($body['page']     ?? 1));
    $perPage = 20;
    $offset  = ($page - 1) * $perPage;

    $valid = ['pendente', 'ativo', 'suspenso'];
    if (!in_array($status, $valid, true)) $status = 'pendente';

    $where  = 'WHERE t.status = ?';
    $params = [$status];

    if ($search) {
        $like     = '%' . $search . '%';
        $where   .= ' AND (t.name LIKE ? OR t.email LIKE ?)';
        $params[] = $like;
        $params[] = $like;
    }

    $total = (int)(dbRow(
        "SELECT COUNT(*) AS n FROM teachers t $where", $params
    )['n'] ?? 0);

    $rows = dbQuery(
        "SELECT t.id, t.name, t.email, t.bio, t.avatar_url, t.status,
                t.rating_avg, t.rating_count, t.balance, t.created_at, t.last_login,
                (SELECT GROUP_CONCAT(ts.name SEPARATOR ', ')
                 FROM teacher_subjects ts WHERE ts.teacher_id = t.id) AS subjects,
                (SELECT COUNT(*) FROM teacher_redacoes tr WHERE tr.teacher_id = t.id) AS total_redacoes,
                (SELECT COUNT(*) FROM teacher_orders to2
                 WHERE to2.teacher_id = t.id AND to2.status = 'pago') AS total_pedidos
         FROM teachers t
         $where
         ORDER BY t.created_at DESC
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );

    out([
        'success' => true,
        'data'    => $rows,
        'total'   => $total,
        'pages'   => max(1, (int)ceil($total / $perPage)),
        'page'    => $page,
    ]);
}

// ── get: detalhes de um professor ────────────────────────────
if ($action === 'get') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) out(['success' => false, 'message' => 'ID inválido.']);

    $t = dbRow(
        'SELECT t.*, 
                (SELECT GROUP_CONCAT(ts.name SEPARATOR ", ")
                 FROM teacher_subjects ts WHERE ts.teacher_id = t.id) AS subjects,
                (SELECT COUNT(*) FROM teacher_redacoes tr WHERE tr.teacher_id = t.id AND tr.status = "corrigida") AS redacoes_corrigidas,
                (SELECT COUNT(*) FROM teacher_orders to2 WHERE to2.teacher_id = t.id AND to2.status = "pago") AS pedidos_pagos,
                (SELECT COUNT(*) FROM teacher_contact_attempts tca WHERE tca.teacher_id = t.id) AS contact_attempts
         FROM teachers t WHERE t.id = ?',
        [$id]
    );
    if (!$t) out(['success' => false, 'message' => 'Professor não encontrado.']);
    out(['success' => true, 'data' => $t]);
}

// ── aprovar: aprova candidato e define senha temporária ───────
if ($action === 'aprovar') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) out(['success' => false, 'message' => 'ID inválido.']);

    $t = dbRow('SELECT id, name, email, status FROM teachers WHERE id = ?', [$id]);
    if (!$t) out(['success' => false, 'message' => 'Professor não encontrado.']);
    if ($t['status'] !== 'pendente') out(['success' => false, 'message' => 'Professor não está pendente.']);

    // Gera senha temporária legível
    $chars   = 'abcdefghjkmnpqrstuvwxyz23456789';
    $senhaTemp = '';
    for ($i = 0; $i < 8; $i++) $senhaTemp .= $chars[random_int(0, strlen($chars) - 1)];

    $hash = password_hash($senhaTemp, PASSWORD_BCRYPT, ['cost' => 12]);

    dbExec(
        'UPDATE teachers SET status = "ativo", password = ? WHERE id = ?',
        [$hash, $id]
    );

    // TODO: enviar e-mail com senha temporária para $t['email']
    // Por ora, retorna a senha para o admin copiar e enviar manualmente
    out([
        'success'    => true,
        'message'    => 'Professor aprovado!',
        'senha_temp' => $senhaTemp,
        'email'      => $t['email'],
        'name'       => $t['name'],
    ]);
}

// ── recusar: recusa candidatura e remove registro ─────────────
if ($action === 'recusar') {
    $id     = (int)($body['id']    ?? 0);
    $motivo = mb_substr(trim($body['motivo'] ?? ''), 0, 500, 'UTF-8');
    if (!$id) out(['success' => false, 'message' => 'ID inválido.']);

    $t = dbRow('SELECT id, name, email, status FROM teachers WHERE id = ?', [$id]);
    if (!$t) out(['success' => false, 'message' => 'Professor não encontrado.']);
    if ($t['status'] !== 'pendente') out(['success' => false, 'message' => 'Professor não está pendente.']);

    // Remove registro — candidatura recusada não fica no sistema
    dbExec('DELETE FROM teachers WHERE id = ? AND status = "pendente"', [$id]);

    // TODO: enviar e-mail de recusa para $t['email'] com $motivo

    out(['success' => true, 'message' => 'Candidatura recusada.']);
}

// ── suspender: suspende professor ativo ───────────────────────
if ($action === 'suspender') {
    $id     = (int)($body['id']    ?? 0);
    $motivo = mb_substr(trim($body['motivo'] ?? ''), 0, 500, 'UTF-8');
    if (!$id) out(['success' => false, 'message' => 'ID inválido.']);

    $t = dbRow('SELECT id, name, email, status FROM teachers WHERE id = ?', [$id]);
    if (!$t) out(['success' => false, 'message' => 'Professor não encontrado.']);

    dbExec("UPDATE teachers SET status = 'suspenso' WHERE id = ?", [$id]);

    // Audit log
    try {
        dbExec(
            "INSERT INTO admin_audit_log (event, ip, user_agent, context)
             VALUES ('TEACHER_SUSPEND', ?, ?, ?)",
            [
                $_SERVER['REMOTE_ADDR']    ?? '?',
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
                json_encode(['admin_id' => $adminId, 'teacher_id' => $id, 'motivo' => $motivo]),
            ]
        );
    } catch (\Throwable $e) {}

    out(['success' => true, 'message' => 'Professor suspenso.']);
}

// ── reativar: reativa professor suspenso ──────────────────────
if ($action === 'reativar') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) out(['success' => false, 'message' => 'ID inválido.']);

    dbExec("UPDATE teachers SET status = 'ativo' WHERE id = ?", [$id]);
    out(['success' => true, 'message' => 'Professor reativado.']);
}

// ── stats: resumo rápido ──────────────────────────────────────
if ($action === 'stats') {
    $pendentes = (int)(dbRow("SELECT COUNT(*) AS n FROM teachers WHERE status = 'pendente'")['n'] ?? 0);
    $ativos    = (int)(dbRow("SELECT COUNT(*) AS n FROM teachers WHERE status = 'ativo'")['n']    ?? 0);
    $suspensos = (int)(dbRow("SELECT COUNT(*) AS n FROM teachers WHERE status = 'suspenso'")['n'] ?? 0);
    $saques    = (int)(dbRow("SELECT COUNT(*) AS n FROM teacher_withdrawals WHERE status = 'solicitado'")['n'] ?? 0);
    $comissao  = (float)(dbRow(
        "SELECT COALESCE(SUM(commission_amt),0) AS total FROM teacher_orders WHERE status = 'pago'
         AND MONTH(paid_at)=MONTH(NOW()) AND YEAR(paid_at)=YEAR(NOW())"
    )['total'] ?? 0);

    out([
        'success' => true,
        'data' => [
            'pendentes' => $pendentes,
            'ativos'    => $ativos,
            'suspensos' => $suspensos,
            'saques_pendentes' => $saques,
            'comissao_mes'     => $comissao,
        ],
    ]);
}

// ── saques: lista saques pendentes ────────────────────────────
if ($action === 'saques_list') {
    $rows = dbQuery(
        "SELECT w.id, w.amount, w.pix_key, w.status, w.created_at,
                t.name AS teacher_name, t.email AS teacher_email, t.balance
         FROM teacher_withdrawals w
         JOIN teachers t ON t.id = w.teacher_id
         WHERE w.status = 'solicitado'
         ORDER BY w.created_at ASC"
    );
    out(['success' => true, 'data' => $rows]);
}

// ── saque_pagar: marca saque como pago ────────────────────────
if ($action === 'saque_pagar') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) out(['success' => false, 'message' => 'ID inválido.']);

    $w = dbRow('SELECT id, teacher_id, amount, status FROM teacher_withdrawals WHERE id = ?', [$id]);
    if (!$w)                        out(['success' => false, 'message' => 'Saque não encontrado.']);
    if ($w['status'] !== 'solicitado') out(['success' => false, 'message' => 'Saque já processado.']);

    dbExec(
        "UPDATE teacher_withdrawals SET status = 'pago', paid_at = NOW() WHERE id = ?",
        [$id]
    );
    out(['success' => true, 'message' => 'Saque marcado como pago.']);
}

// ── saque_recusar: recusa saque e devolve saldo ───────────────
if ($action === 'saque_recusar') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) out(['success' => false, 'message' => 'ID inválido.']);

    $w = dbRow('SELECT id, teacher_id, amount, status FROM teacher_withdrawals WHERE id = ?', [$id]);
    if (!$w) out(['success' => false, 'message' => 'Saque não encontrado.']);
    if ($w['status'] !== 'solicitado') out(['success' => false, 'message' => 'Saque já processado.']);

    // Devolve saldo ao professor
    dbExec(
        'UPDATE teachers SET balance = balance + ? WHERE id = ?',
        [(float)$w['amount'], (int)$w['teacher_id']]
    );
    dbExec(
        "UPDATE teacher_withdrawals SET status = 'recusado' WHERE id = ?",
        [$id]
    );
    out(['success' => true, 'message' => 'Saque recusado e saldo devolvido.']);
}

out(['success' => false, 'message' => 'Ação desconhecida.']);