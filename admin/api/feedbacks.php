<?php
// ============================================================
// /admin/api/feedbacks.php — florescer Admin v3.0
// ============================================================
require_once __DIR__ . '/../includes/auth_admin.php';
require_once dirname(dirname(__DIR__)) . '/config/db.php';

requireAdmin();
ob_start();
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

$adminId = (int)($_SESSION['admin_id'] ?? 0);

function out(array $d): void {
    if (ob_get_level() > 0) ob_end_clean();
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['success' => false, 'message' => 'Método não permitido.']);
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($body['action'] ?? '');

// ── Constantes de validação ───────────────────────────────────
const VALID_STATUSES = ['aberto', 'em_analise', 'resolvido', 'fechado'];
const VALID_TYPES    = ['sugestao', 'bug', 'elogio', 'duvida'];
const PER_PAGE_MAX   = 50;
const REPLY_MAX_LEN  = 2000;

switch ($action) {

    // ── list: lista feedbacks com filtros e paginação ─────────
    case 'list':
        $status  = trim($body['status'] ?? '');
        $type    = trim($body['type']   ?? '');
        $search  = trim($body['q']      ?? '');
        $page    = max(1, (int)($body['page']     ?? 1));
        $perPage = max(1, min(PER_PAGE_MAX, (int)($body['per_page'] ?? 20)));
        $offset  = ($page - 1) * $perPage;

        $where  = 'WHERE 1=1';
        $params = [];

        if ($status && in_array($status, VALID_STATUSES, true)) {
            $where .= ' AND f.status = ?';
            $params[] = $status;
        }
        if ($type && in_array($type, VALID_TYPES, true)) {
            $where .= ' AND f.type = ?';
            $params[] = $type;
        }
        if ($search !== '') {
            $like     = '%' . $search . '%';
            $where   .= ' AND (f.title LIKE ? OR f.message LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $total = (int)(dbRow(
            "SELECT COUNT(*) AS n FROM feedbacks f
             LEFT JOIN users u ON u.id = f.user_id $where",
            $params
        )['n'] ?? 0);

        $rows = dbQuery(
            "SELECT f.*, u.name AS user_name, u.email AS user_email,
                    u.avatar_emoji, u.level AS user_level
             FROM feedbacks f
             LEFT JOIN users u ON u.id = f.user_id
             $where
             ORDER BY
               CASE f.status
                 WHEN 'aberto'     THEN 0
                 WHEN 'em_analise' THEN 1
                 WHEN 'resolvido'  THEN 2
                 ELSE 3
               END ASC,
               f.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        out([
            'success' => true,
            'data'    => $rows,
            'total'   => $total,
            'page'    => $page,
            'pages'   => max(1, (int)ceil($total / $perPage)),
        ]);

    // ── get: retorna um feedback específico ───────────────────
    case 'get':
        $id = (int)($body['id'] ?? 0);
        if (!$id) out(['success' => false, 'message' => 'ID inválido.']);

        $row = dbRow(
            "SELECT f.*, u.name AS user_name, u.email AS user_email, u.avatar_emoji
             FROM feedbacks f
             LEFT JOIN users u ON u.id = f.user_id
             WHERE f.id = ?",
            [$id]
        );
        if (!$row) out(['success' => false, 'message' => 'Feedback não encontrado.']);
        out(['success' => true, 'data' => $row]);

    // ── update_status: muda o status do feedback ──────────────
    case 'update_status':
        $id     = (int)($body['id']     ?? 0);
        $status = trim($body['status']  ?? '');

        if (!$id) out(['success' => false, 'message' => 'ID inválido.']);
        if (!in_array($status, VALID_STATUSES, true))
            out(['success' => false, 'message' => 'Status inválido.']);

        $exists = dbRow('SELECT id FROM feedbacks WHERE id = ?', [$id]);
        if (!$exists) out(['success' => false, 'message' => 'Feedback não encontrado.']);

        dbExec('UPDATE feedbacks SET status = ? WHERE id = ?', [$status, $id]);

        // Grava no audit log
        try {
            dbExec(
                "INSERT INTO admin_audit_log (event, ip, user_agent, context)
                 VALUES ('FEEDBACK_STATUS', ?, ?, ?)",
                [
                    $_SERVER['REMOTE_ADDR']    ?? '?',
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
                    json_encode(['admin_id' => $adminId, 'feedback_id' => $id, 'new_status' => $status]),
                ]
            );
        } catch (\Throwable $e) { /* audit não bloqueia */ }

        out(['success' => true]);

    // ── reply: salva resposta do admin ────────────────────────
    case 'reply':
        $id    = (int)($body['id']    ?? 0);
        $reply = mb_substr(trim($body['reply'] ?? ''), 0, REPLY_MAX_LEN, 'UTF-8');

        if (!$id)    out(['success' => false, 'message' => 'ID inválido.']);
        if (!$reply) out(['success' => false, 'message' => 'A resposta não pode ser vazia.']);

        $exists = dbRow('SELECT id FROM feedbacks WHERE id = ?', [$id]);
        if (!$exists) out(['success' => false, 'message' => 'Feedback não encontrado.']);

        // Ao responder, muda para "resolvido" automaticamente
        dbExec(
            "UPDATE feedbacks SET admin_reply = ?, status = 'resolvido', updated_at = NOW() WHERE id = ?",
            [$reply, $id]
        );

        try {
            dbExec(
                "INSERT INTO admin_audit_log (event, ip, user_agent, context)
                 VALUES ('FEEDBACK_REPLY', ?, ?, ?)",
                [
                    $_SERVER['REMOTE_ADDR']    ?? '?',
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
                    json_encode(['admin_id' => $adminId, 'feedback_id' => $id]),
                ]
            );
        } catch (\Throwable $e) { /* audit não bloqueia */ }

        out(['success' => true]);

    // ── clear_reply: apaga resposta do admin ──────────────────
    case 'clear_reply':
        $id = (int)($body['id'] ?? 0);
        if (!$id) out(['success' => false, 'message' => 'ID inválido.']);

        $exists = dbRow('SELECT id FROM feedbacks WHERE id = ?', [$id]);
        if (!$exists) out(['success' => false, 'message' => 'Feedback não encontrado.']);

        dbExec(
            "UPDATE feedbacks SET admin_reply = NULL, status = 'aberto', updated_at = NOW() WHERE id = ?",
            [$id]
        );
        out(['success' => true]);

    // ── delete: exclui feedback ───────────────────────────────
    case 'delete':
        $id = (int)($body['id'] ?? 0);
        if (!$id) out(['success' => false, 'message' => 'ID inválido.']);

        // Verifica se existe antes de apagar
        $row = dbRow('SELECT id, user_id FROM feedbacks WHERE id = ?', [$id]);
        if (!$row) out(['success' => false, 'message' => 'Feedback não encontrado.']);

        dbExec('DELETE FROM feedbacks WHERE id = ?', [$id]);

        try {
            dbExec(
                "INSERT INTO admin_audit_log (event, ip, user_agent, context)
                 VALUES ('FEEDBACK_DELETE', ?, ?, ?)",
                [
                    $_SERVER['REMOTE_ADDR']    ?? '?',
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
                    json_encode(['admin_id' => $adminId, 'feedback_id' => $id, 'user_id' => $row['user_id']]),
                ]
            );
        } catch (\Throwable $e) { /* audit não bloqueia */ }

        out(['success' => true]);

    // ── bulk_status: atualiza vários de uma vez ───────────────
    case 'bulk_status':
        $ids    = array_filter(array_map('intval', $body['ids'] ?? []), fn($v) => $v > 0);
        $status = trim($body['status'] ?? '');

        if (empty($ids))   out(['success' => false, 'message' => 'Nenhum ID fornecido.']);
        if (!in_array($status, VALID_STATUSES, true))
            out(['success' => false, 'message' => 'Status inválido.']);
        if (count($ids) > 100) out(['success' => false, 'message' => 'Máx. 100 registros por vez.']);

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        dbExec(
            "UPDATE feedbacks SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)",
            array_merge([$status], $ids)
        );
        out(['success' => true, 'updated' => count($ids)]);

    // ── stats: estatísticas rápidas ───────────────────────────
    case 'stats':
        $total   = (int)(dbRow('SELECT COUNT(*) AS n FROM feedbacks')['n']                                ?? 0);
        $abertos = (int)(dbRow("SELECT COUNT(*) AS n FROM feedbacks WHERE status='aberto'")['n']          ?? 0);
        $analise = (int)(dbRow("SELECT COUNT(*) AS n FROM feedbacks WHERE status='em_analise'")['n']      ?? 0);
        $resolv  = (int)(dbRow("SELECT COUNT(*) AS n FROM feedbacks WHERE status='resolvido'")['n']       ?? 0);
        $byType  = dbQuery('SELECT type, COUNT(*) AS n FROM feedbacks GROUP BY type');

        out([
            'success' => true,
            'data'    => [
                'total'    => $total,
                'abertos'  => $abertos,
                'analise'  => $analise,
                'resolv'   => $resolv,
                'by_type'  => array_column($byType, 'n', 'type'),
            ],
        ]);

    default:
        http_response_code(400);
        out(['success' => false, 'message' => 'Ação desconhecida.']);
}