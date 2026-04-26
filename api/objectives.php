<?php
// ============================================================
// api/objectives.php — florescer v2.4
// CORREÇÕES: anti-duplicata via token, erro de conexão, avg
// ============================================================
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/unit_helper.php';

header('Content-Type: application/json; charset=utf-8');
ob_clean();

startSession();
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

$userId = (int)currentUser()['id'];
$raw    = file_get_contents('php://input');
$body   = $raw ? (json_decode($raw, true) ?? []) : $_POST;
$action = trim($body['action'] ?? '');

function jsonOk(array $data = []): void {
    ob_clean();
    echo json_encode(array_merge(['success' => true], $data)); exit;
}
function jsonErr(string $msg, int $code = 400): void {
    ob_clean();
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]); exit;
}

function resolveUnitCount(int $teachingTypeId, int $userDefinedCount): int {
    $tt = dbRow('SELECT periods FROM teaching_types WHERE id = ?', [$teachingTypeId]);
    if (!$tt) return max(1, min(12, $userDefinedCount ?: 4));
    $periods = (int)$tt['periods'];
    if ($periods > 0) return $periods;
    return max(1, min(12, $userDefinedCount ?: 4));
}

// ════════════════════════════════════════════════════════════
// ACTION: list
// ════════════════════════════════════════════════════════════
if ($action === 'list') {
    $rows = dbQuery(
        'SELECT o.id, o.name, o.grade_level, o.default_avg, o.is_active, o.created_at,
                o.unit_count,
                ot.name AS type_name,
                tt.name AS teach_name,
                tt.periods AS tt_periods,
                (SELECT COUNT(*) FROM subjects s WHERE s.objective_id=o.id AND s.is_active=1) AS subj_count,
                (SELECT COUNT(*) FROM lessons l
                 JOIN topics t ON t.id=l.topic_id
                 JOIN subjects s ON s.id=t.subject_id
                 WHERE s.objective_id=o.id AND l.is_completed=1) AS lessons_done,
                (SELECT COUNT(*) FROM lessons l
                 JOIN topics t ON t.id=l.topic_id
                 JOIN subjects s ON s.id=t.subject_id
                 WHERE s.objective_id=o.id) AS lessons_total
         FROM objectives o
         LEFT JOIN objective_types ot ON ot.id = o.objective_type_id
         LEFT JOIN teaching_types  tt ON tt.id = o.teaching_type_id
         WHERE o.user_id = ?
         ORDER BY o.is_active DESC, o.created_at DESC',
        [$userId]
    );
    jsonOk(['data' => $rows]);
}

// ════════════════════════════════════════════════════════════
// ACTION: create — com proteção anti-duplicata por token
// ════════════════════════════════════════════════════════════
if ($action === 'create') {
    $typeId     = (int)($body['objective_type_id'] ?? 0);
    $teachId    = (int)($body['teaching_type_id']  ?? 0);
    $name       = trim($body['name'] ?? '');
    $gradeLevel = trim($body['grade_level'] ?? '') ?: null;
    $defaultAvg = isset($body['default_avg']) && $body['default_avg'] !== ''
                    ? (float)$body['default_avg'] : null;
    $userUC     = (int)($body['unit_count'] ?? 4);
    // Token de idempotência gerado pelo frontend para evitar duplicatas
    $token      = trim($body['idempotency_token'] ?? '');

    if (!$typeId) jsonErr('Tipo de objetivo obrigatório.');
    if (!$name)  jsonErr('Nome obrigatório.');
    if ($defaultAvg !== null && ($defaultAvg < 0 || $defaultAvg > 10))
        jsonErr('Média deve ser entre 0 e 10.');

    // ── Verificação anti-duplicata por token de sessão ────────
    if ($token) {
        $tokenKey = 'obj_create_' . $userId . '_' . $token;
        if (!empty($_SESSION[$tokenKey])) {
            // Já processado — devolve o ID criado anteriormente
            jsonOk([
                'id'         => $_SESSION[$tokenKey],
                'unit_count' => $userUC,
                'message'    => 'Objetivo já criado.',
                'duplicate'  => true,
            ]);
        }
    }

    // ── Calcula unit_count final ─────────────────────────────
    $finalUC = $teachId
        ? resolveUnitCount($teachId, $userUC)
        : max(1, min(12, $userUC ?: 4));

    // Desativa os outros objetivos do usuário
    dbQuery('UPDATE objectives SET is_active=0 WHERE user_id=?', [$userId]);

    dbQuery(
        'INSERT INTO objectives
            (user_id, objective_type_id, teaching_type_id, name, grade_level, default_avg, is_active, unit_count)
         VALUES (?, ?, ?, ?, ?, ?, 1, ?)',
        [$userId, $typeId, $teachId ?: null, $name, $gradeLevel, $defaultAvg, $finalUC]
    );
    $newId = dbLastId();
    $_SESSION['active_objective'] = $newId;

    // Guarda o token para evitar duplicatas futuras nesta sessão
    if ($token) {
        $_SESSION['obj_create_' . $userId . '_' . $token] = $newId;
    }

    jsonOk([
        'id'         => $newId,
        'unit_count' => $finalUC,
        'message'    => 'Objetivo criado com sucesso.',
    ]);
}

// ════════════════════════════════════════════════════════════
// ACTION: update
// ════════════════════════════════════════════════════════════
if ($action === 'update') {
    $id         = (int)($body['id'] ?? 0);
    $name       = trim($body['name'] ?? '');
    $defaultAvg = isset($body['default_avg']) && $body['default_avg'] !== ''
                    ? (float)$body['default_avg'] : null;
    $userUC     = (int)($body['unit_count'] ?? 0);

    if (!$id)   jsonErr('ID do objetivo obrigatório.');
    if (!$name) jsonErr('Nome obrigatório.');
    if ($defaultAvg !== null && ($defaultAvg < 0 || $defaultAvg > 10))
        jsonErr('Média deve ser entre 0 e 10.');
    if ($userUC < 1 || $userUC > 12) jsonErr('Quantidade de unidades deve ser entre 1 e 12.');

    $obj = dbRow(
        'SELECT o.id, o.teaching_type_id, tt.periods AS tt_periods
         FROM objectives o
         LEFT JOIN teaching_types tt ON tt.id = o.teaching_type_id
         WHERE o.id = ? AND o.user_id = ?',
        [$id, $userId]
    );
    if (!$obj) jsonErr('Objetivo não encontrado.', 404);

    $ttPeriods = (int)($obj['tt_periods'] ?? 0);
    $finalUC   = $ttPeriods > 0
        ? $ttPeriods
        : max(1, min(12, $userUC ?: 4));

    dbQuery(
        'UPDATE objectives SET name=?, default_avg=?, unit_count=? WHERE id=? AND user_id=?',
        [$name, $defaultAvg, $finalUC, $id, $userId]
    );

    jsonOk(['unit_count' => $finalUC, 'message' => 'Objetivo atualizado.']);
}

// ════════════════════════════════════════════════════════════
// ACTION: activate
// ════════════════════════════════════════════════════════════
if ($action === 'activate') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) jsonErr('ID obrigatório.');

    $obj = dbRow('SELECT id FROM objectives WHERE id=? AND user_id=?', [$id, $userId]);
    if (!$obj) jsonErr('Objetivo não encontrado.', 404);

    dbQuery('UPDATE objectives SET is_active=0 WHERE user_id=?', [$userId]);
    dbQuery('UPDATE objectives SET is_active=1 WHERE id=? AND user_id=?', [$id, $userId]);
    $_SESSION['active_objective'] = $id;

    jsonOk(['message' => 'Objetivo ativado.']);
}

// ════════════════════════════════════════════════════════════
// ACTION: delete
// ════════════════════════════════════════════════════════════
if ($action === 'delete') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) jsonErr('ID obrigatório.');

    $obj = dbRow('SELECT id FROM objectives WHERE id=? AND user_id=?', [$id, $userId]);
    if (!$obj) jsonErr('Objetivo não encontrado.', 404);

    dbQuery('DELETE FROM objectives WHERE id=? AND user_id=?', [$id, $userId]);

    if (($_SESSION['active_objective'] ?? 0) == $id) {
        $next = dbRow(
            'SELECT id FROM objectives WHERE user_id=? ORDER BY is_active DESC, created_at DESC LIMIT 1',
            [$userId]
        );
        $_SESSION['active_objective'] = $next['id'] ?? null;
    }

    jsonOk(['message' => 'Objetivo excluído.']);
}

jsonErr('Ação inválida.', 400);