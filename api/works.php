<?php
// ============================================================
// /api/works.php — florescer v2.0
// CRUD de trabalhos escolares
// Tabela: works (id, user_id, subject_id, title, description,
//                unit, due_date, status, created_at)
// ============================================================
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

startSession();
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

$userId = (int)currentUser()['id'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($body['action'] ?? '');

function out(array $d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function str(string $s, int $max = 500): string { return mb_substr(trim($s), 0, $max, 'UTF-8'); }

$VALID_STATUS = ['pendente', 'em_andamento', 'entregue', 'atrasado'];

switch ($action) {

    // ── Listar trabalhos ──────────────────────────────────────
    case 'list':
        $subjectId = isset($body['subject_id']) ? (int)$body['subject_id'] : null;
        $status    = trim($body['status'] ?? '');
        $unit      = trim($body['unit']   ?? '');

        $where  = 'WHERE w.user_id=?';
        $params = [$userId];

        if ($subjectId) { $where .= ' AND w.subject_id=?'; $params[] = $subjectId; }
        if ($status)    { $where .= ' AND w.status=?';     $params[] = $status; }
        if ($unit)      { $where .= ' AND w.unit=?';       $params[] = $unit; }

        $rows = dbQuery(
            "SELECT w.id, w.subject_id, w.title, w.description,
                    w.unit, w.due_date, w.status, w.created_at,
                    s.name AS subject_name, s.color AS subject_color
             FROM works w
             LEFT JOIN subjects s ON s.id = w.subject_id
             $where
             ORDER BY
               CASE w.status
                 WHEN 'atrasado'     THEN 1
                 WHEN 'pendente'     THEN 2
                 WHEN 'em_andamento' THEN 3
                 WHEN 'entregue'     THEN 4
                 ELSE 5
               END,
               w.due_date ASC, w.created_at DESC",
            $params
        );

        // Marca automaticamente atrasados
        $today = date('Y-m-d');
        foreach ($rows as &$r) {
            if ($r['due_date'] && $r['due_date'] < $today && $r['status'] !== 'entregue') {
                if ($r['status'] !== 'atrasado') {
                    dbExec('UPDATE works SET status=? WHERE id=? AND user_id=?',
                           ['atrasado', $r['id'], $userId]);
                    $r['status'] = 'atrasado';
                }
            }
        }
        unset($r);

        out(['success' => true, 'data' => $rows]);

    // ── Criar trabalho ────────────────────────────────────────
    case 'create':
        $title   = str($body['title']        ?? '');
        $subjId  = (int)($body['subject_id'] ?? 0);
        $desc    = str($body['description']  ?? '', 2000);
        $unit    = str($body['unit']         ?? '', 20);
        $dueDate = trim($body['due_date']    ?? '');
        $status  = in_array($body['status'] ?? '', $VALID_STATUS)
                   ? $body['status'] : 'pendente';

        if (!$title)  out(['success'=>false,'message'=>'Informe o título do trabalho.']);
        if (!$subjId) out(['success'=>false,'message'=>'Selecione a matéria.']);

        // Valida que a matéria pertence ao usuário
        $subj = dbRow(
            'SELECT s.id FROM subjects s
             JOIN objectives o ON o.id=s.objective_id
             WHERE s.id=? AND o.user_id=?',
            [$subjId, $userId]
        );
        if (!$subj) out(['success'=>false,'message'=>'Matéria não encontrada.']);

        if ($dueDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) $dueDate = null;

        dbExec(
            'INSERT INTO works (user_id, subject_id, title, description, unit, due_date, status)
             VALUES (?,?,?,?,?,?,?)',
            [$userId, $subjId, $title, $desc ?: null, $unit ?: null,
             $dueDate ?: null, $status]
        );

        $newId = (int)dbRow('SELECT LAST_INSERT_ID() AS id')['id'];
        $work  = dbRow(
            'SELECT w.*, s.name AS subject_name, s.color AS subject_color
             FROM works w LEFT JOIN subjects s ON s.id=w.subject_id
             WHERE w.id=?',
            [$newId]
        );

        out(['success'=>true, 'id'=>$newId, 'data'=>$work]);

    // ── Editar trabalho ───────────────────────────────────────
    case 'update':
        $id      = (int)($body['id']         ?? 0);
        $title   = str($body['title']        ?? '');
        $subjId  = (int)($body['subject_id'] ?? 0);
        $desc    = str($body['description']  ?? '', 2000);
        $unit    = str($body['unit']         ?? '', 20);
        $dueDate = trim($body['due_date']    ?? '');
        $status  = in_array($body['status'] ?? '', $VALID_STATUS)
                   ? $body['status'] : 'pendente';

        if (!$id)    out(['success'=>false,'message'=>'ID inválido.']);
        if (!$title) out(['success'=>false,'message'=>'Informe o título.']);

        $existing = dbRow('SELECT id FROM works WHERE id=? AND user_id=?', [$id, $userId]);
        if (!$existing) out(['success'=>false,'message'=>'Trabalho não encontrado.']);

        if ($dueDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) $dueDate = null;

        dbExec(
            'UPDATE works
             SET subject_id=?, title=?, description=?, unit=?, due_date=?, status=?
             WHERE id=? AND user_id=?',
            [$subjId ?: null, $title, $desc ?: null, $unit ?: null,
             $dueDate ?: null, $status, $id, $userId]
        );

        out(['success'=>true]);

    // ── Atualizar só status ───────────────────────────────────
    case 'update_status':
        $id     = (int)($body['id']    ?? 0);
        $status = trim($body['status'] ?? '');

        if (!$id) out(['success'=>false,'message'=>'ID inválido.']);
        if (!in_array($status, $VALID_STATUS))
            out(['success'=>false,'message'=>'Status inválido.']);

        $existing = dbRow('SELECT id FROM works WHERE id=? AND user_id=?', [$id, $userId]);
        if (!$existing) out(['success'=>false,'message'=>'Trabalho não encontrado.']);

        dbExec('UPDATE works SET status=? WHERE id=? AND user_id=?', [$status, $id, $userId]);
        out(['success'=>true]);

    // ── Excluir ───────────────────────────────────────────────
    case 'delete':
        $id = (int)($body['id'] ?? 0);
        if (!$id) out(['success'=>false,'message'=>'ID inválido.']);

        $existing = dbRow('SELECT id FROM works WHERE id=? AND user_id=?', [$id, $userId]);
        if (!$existing) out(['success'=>false,'message'=>'Trabalho não encontrado.']);

        dbExec('DELETE FROM works WHERE id=? AND user_id=?', [$id, $userId]);
        out(['success'=>true]);

    // ── Matérias disponíveis para o select ───────────────────
    case 'get_subjects':
        $objId = isset($body['objective_id']) ? (int)$body['objective_id'] : null;

        if (!$objId) {
            $ao    = dbRow('SELECT id FROM objectives WHERE user_id=? AND is_active=1 ORDER BY created_at DESC LIMIT 1', [$userId]);
            $objId = $ao['id'] ?? null;
        }

        if ($objId) {
            $subs = dbQuery(
                'SELECT s.id, s.name, s.color FROM subjects s
                 WHERE s.objective_id=? AND s.is_active=1 ORDER BY s.name ASC',
                [$objId]
            );
        } else {
            $subs = dbQuery(
                'SELECT s.id, s.name, s.color FROM subjects s
                 JOIN objectives o ON o.id=s.objective_id
                 WHERE o.user_id=? AND s.is_active=1 ORDER BY s.name ASC',
                [$userId]
            );
        }

        out(['success'=>true, 'data'=>$subs]);

    // ── Stats rápido ──────────────────────────────────────────
    case 'stats':
        $s = dbRow(
            "SELECT COUNT(*) AS total,
               SUM(status='pendente')     AS pendente,
               SUM(status='em_andamento') AS em_andamento,
               SUM(status='entregue')     AS entregue,
               SUM(status='atrasado')     AS atrasado
             FROM works WHERE user_id=?",
            [$userId]
        );
        out(['success'=>true, 'data'=>$s]);

    default:
        http_response_code(400);
        out(['success'=>false,'message'=>'Ação desconhecida: '.$action]);
}