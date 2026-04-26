<?php
// ============================================================
// /api/topics.php
// Ações: list | create | update | delete | reorder
// ============================================================

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Método não permitido.', 405);
}

$userId = requireAuth();
$input  = getInput();
$action = inp($input, 'action', true);

match ($action) {
    'list'    => actionList($userId, $input),
    'create'  => actionCreate($userId, $input),
    'update'  => actionUpdate($userId, $input),
    'delete'  => actionDelete($userId, $input),
    'reorder' => actionReorder($userId, $input),
    default   => fail('Ação inválida.', 400),
};

function actionList(int $userId, array $input): void {
    $subjectId = inpInt($input, 'subject_id', true);

    $subject = dbRow(
        'SELECT id, name FROM subjects WHERE id = ? AND user_id = ? AND is_active = 1',
        [$subjectId, $userId]
    );
    if (!$subject) fail('Matéria não encontrada.', 404);

    $topics = dbQuery(
        'SELECT
            t.id,
            t.name,
            t.unit_index,
            t.order_num,
            t.created_at,
            COUNT(l.id)                                              AS total_lessons,
            SUM(CASE WHEN l.is_completed = 1 THEN 1 ELSE 0 END)     AS completed_lessons,
            ROUND(
                SUM(CASE WHEN l.is_completed = 1 THEN 1 ELSE 0 END)
                / NULLIF(COUNT(l.id), 0) * 100, 1
            )                                                        AS progress_pct
         FROM topics t
         LEFT JOIN lessons l ON l.topic_id = t.id
         WHERE t.subject_id = ?
         GROUP BY t.id, t.name, t.unit_index, t.order_num, t.created_at
         ORDER BY t.unit_index ASC, t.order_num ASC, t.created_at ASC',
        [$subjectId]
    );

    ok($topics);
}

function actionCreate(int $userId, array $input): void {
    $subjectId = inpInt($input, 'subject_id', true);
    $name      = inp($input,    'name',       true);
    $unitIndex = isset($input['unit_index']) ? (int)$input['unit_index'] : 0;

    $subject = dbRow(
        'SELECT id FROM subjects WHERE id = ? AND user_id = ? AND is_active = 1',
        [$subjectId, $userId]
    );
    if (!$subject) fail('Matéria não encontrada.', 404);

    if (mb_strlen($name, 'UTF-8') < 1) fail('O nome do assunto é obrigatório.');
    if (mb_strlen($name, 'UTF-8') > 150) fail('O nome deve ter no máximo 150 caracteres.');

    $duplicate = dbRow(
        'SELECT id FROM topics WHERE subject_id = ? AND name = ?',
        [$subjectId, $name]
    );
    if ($duplicate) fail('Já existe um assunto com esse nome nesta matéria.');

    $maxOrder = dbRow(
        'SELECT COALESCE(MAX(order_num), 0) AS max_order FROM topics WHERE subject_id = ?',
        [$subjectId]
    );
    $orderNum = (int)$maxOrder['max_order'] + 1;

    dbExec(
        'INSERT INTO topics (subject_id, name, order_num, unit_index) VALUES (?, ?, ?, ?)',
        [$subjectId, $name, $orderNum, $unitIndex]
    );

    $topicId = (int)dbLastId();
    $topic   = dbRow(
        'SELECT id, name, order_num, unit_index FROM topics WHERE id = ?',
        [$topicId]
    );

    ok($topic, 'Assunto criado!');
}

function actionUpdate(int $userId, array $input): void {
    $topicId = inpInt($input, 'topic_id', true);
    $name    = inp($input,    'name',     true);

    ownershipCheck('topics', $topicId, $userId);

    if (mb_strlen($name, 'UTF-8') < 1) fail('O nome do assunto é obrigatório.');
    if (mb_strlen($name, 'UTF-8') > 150) fail('O nome deve ter no máximo 150 caracteres.');

    dbExec('UPDATE topics SET name = ? WHERE id = ?', [$name, $topicId]);

    ok(null, 'Assunto atualizado.');
}

function actionDelete(int $userId, array $input): void {
    $topicId = inpInt($input, 'topic_id', true);

    ownershipCheck('topics', $topicId, $userId);

    dbExec('DELETE FROM topics WHERE id = ?', [$topicId]);

    ok(null, 'Assunto removido.');
}

function actionReorder(int $userId, array $input): void {
    if (!isset($input['order']) || !is_array($input['order'])) {
        fail('Lista de ordenação inválida.');
    }

    $order = $input['order'];

    foreach ($order as $id) {
        if (!is_int($id) && !ctype_digit((string) $id)) {
            fail('IDs de ordenação inválidos.');
        }
    }

    try {
        dbBegin();

        foreach ($order as $position => $topicId) {
            $topicId = (int)$topicId;
            dbExec(
                'UPDATE topics t
                 JOIN subjects s ON s.id = t.subject_id
                 SET t.order_num = ?
                 WHERE t.id = ? AND s.user_id = ?',
                [$position + 1, $topicId, $userId]
            );
        }

        dbCommit();

    } catch (Throwable $e) {
        dbRollback();
        error_log('[Florescer] Erro ao reordenar assuntos: ' . $e->getMessage());
        fail('Erro interno ao reordenar.', 500);
    }

    ok(null, 'Ordem atualizada.');
}