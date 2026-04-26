<?php
// ============================================================
// /api/works_calendar.php — Semente v2.0
// CRUD de eventos do calendário do aluno
// Actions: month | create | update | delete | toggle_done
// ============================================================
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

startSession();
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Não autenticado.']);
    exit;
}

$user   = currentUser();
$userId = (int)$user['id'];

function jsonOut(array $d): void {
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

// Verifica tabela
if (!dbRow("SHOW TABLES LIKE 'calendar_events'")) {
    jsonOut(['success'=>false,'message'=>'Tabela calendar_events não existe. Execute o SQL de setup.','setup'=>true]);
}

$method = $_SERVER['REQUEST_METHOD'];
$body   = [];
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
}
$action = trim($method === 'GET' ? ($_GET['action'] ?? 'month') : ($body['action'] ?? ''));

$VALID_TYPES = ['prova','trabalho','atividade','teste','outro'];

// =============================================================
switch ($action) {

// ── Eventos de um mês ─────────────────────────────────────────
case 'month':
    $year  = (int)($_GET['year']  ?? date('Y'));
    $month = (int)($_GET['month'] ?? date('n'));
    $month = max(1, min(12, $month));

    $start = sprintf('%04d-%02d-01', $year, $month);
    $end   = date('Y-m-t', strtotime($start));

    $events = dbQuery(
        'SELECT e.id, e.title, e.description, e.event_date, e.event_type,
                e.is_done, e.subject_id,
                s.name AS subject_name, s.color AS subject_color
         FROM calendar_events e
         LEFT JOIN subjects s ON s.id = e.subject_id
         WHERE e.user_id = ? AND e.event_date BETWEEN ? AND ?
         ORDER BY e.event_date ASC, e.id ASC',
        [$userId, $start, $end]
    );

    // Agrupa por data
    $byDate = [];
    foreach ($events as $ev) {
        $byDate[$ev['event_date']][] = $ev;
    }

    jsonOut(['success'=>true,'data'=>$byDate,'year'=>$year,'month'=>$month]);

// ── Criar evento ──────────────────────────────────────────────
case 'create':
    $title       = trim($body['title'] ?? '');
    $date        = trim($body['event_date'] ?? '');
    $type        = trim($body['event_type'] ?? 'outro');
    $desc        = trim($body['description'] ?? '');
    $subjectId   = $body['subject_id'] ? (int)$body['subject_id'] : null;

    if (!$title)                         jsonOut(['success'=>false,'message'=>'Título obrigatório.']);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) jsonOut(['success'=>false,'message'=>'Data inválida.']);
    if (!in_array($type, $VALID_TYPES))  $type = 'outro';

    // Valida subject pertence ao usuário
    if ($subjectId) {
        $subj = dbRow('SELECT id FROM subjects WHERE id=? AND (SELECT user_id FROM objectives WHERE id=subjects.objective_id)=?', [$subjectId, $userId]);
        if (!$subj) $subjectId = null;
    }

    dbExec(
        'INSERT INTO calendar_events (user_id, subject_id, title, description, event_date, event_type)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$userId, $subjectId, $title, $desc ?: null, $date, $type]
    );
    $newId = (int)dbRow('SELECT LAST_INSERT_ID() AS id')['id'];
    $ev = dbRow(
        'SELECT e.*, s.name AS subject_name, s.color AS subject_color
         FROM calendar_events e LEFT JOIN subjects s ON s.id=e.subject_id
         WHERE e.id=?',
        [$newId]
    );
    jsonOut(['success'=>true,'data'=>$ev]);

// ── Atualizar evento ──────────────────────────────────────────
case 'update':
    $id        = (int)($body['id'] ?? 0);
    $title     = trim($body['title'] ?? '');
    $date      = trim($body['event_date'] ?? '');
    $type      = trim($body['event_type'] ?? 'outro');
    $desc      = trim($body['description'] ?? '');
    $subjectId = isset($body['subject_id']) && $body['subject_id'] ? (int)$body['subject_id'] : null;

    if (!$id)    jsonOut(['success'=>false,'message'=>'ID inválido.']);
    if (!$title) jsonOut(['success'=>false,'message'=>'Título obrigatório.']);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) jsonOut(['success'=>false,'message'=>'Data inválida.']);
    if (!in_array($type, $VALID_TYPES)) $type = 'outro';

    $ev = dbRow('SELECT id FROM calendar_events WHERE id=? AND user_id=?', [$id, $userId]);
    if (!$ev) jsonOut(['success'=>false,'message'=>'Evento não encontrado.']);

    if ($subjectId) {
        $subj = dbRow('SELECT id FROM subjects WHERE id=? AND (SELECT user_id FROM objectives WHERE id=subjects.objective_id)=?', [$subjectId, $userId]);
        if (!$subj) $subjectId = null;
    }

    dbExec(
        'UPDATE calendar_events SET title=?, description=?, event_date=?, event_type=?, subject_id=? WHERE id=? AND user_id=?',
        [$title, $desc ?: null, $date, $type, $subjectId, $id, $userId]
    );
    jsonOut(['success'=>true]);

// ── Excluir evento ────────────────────────────────────────────
case 'delete':
    $id = (int)($body['id'] ?? 0);
    if (!$id) jsonOut(['success'=>false,'message'=>'ID inválido.']);
    $ev = dbRow('SELECT id FROM calendar_events WHERE id=? AND user_id=?', [$id, $userId]);
    if (!$ev) jsonOut(['success'=>false,'message'=>'Evento não encontrado.']);
    dbExec('DELETE FROM calendar_events WHERE id=? AND user_id=?', [$id, $userId]);
    jsonOut(['success'=>true]);

// ── Marcar como feito/não feito ───────────────────────────────
case 'toggle_done':
    $id = (int)($body['id'] ?? 0);
    if (!$id) jsonOut(['success'=>false,'message'=>'ID inválido.']);
    $ev = dbRow('SELECT id, is_done FROM calendar_events WHERE id=? AND user_id=?', [$id, $userId]);
    if (!$ev) jsonOut(['success'=>false,'message'=>'Evento não encontrado.']);
    $newDone = $ev['is_done'] ? 0 : 1;
    dbExec('UPDATE calendar_events SET is_done=? WHERE id=? AND user_id=?', [$newDone, $id, $userId]);
    jsonOut(['success'=>true,'is_done'=>$newDone]);

default:
    http_response_code(400);
    jsonOut(['success'=>false,'message'=>'Ação desconhecida.']);
}