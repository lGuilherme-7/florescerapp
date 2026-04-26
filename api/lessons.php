<?php
// ============================================================
// /api/lessons.php
// Ações: list | create | update | delete | complete | reorder
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
    'list'     => actionList($userId, $input),
    'create'   => actionCreate($userId, $input),
    'update'   => actionUpdate($userId, $input),
    'delete'   => actionDelete($userId, $input),
    'complete' => actionComplete($userId, $input),
    'reorder'  => actionReorder($userId, $input),
    default    => fail('Ação inválida.', 400),
};

// ════════════════════════════════════════════════════════════
// LISTAR AULAS
// ════════════════════════════════════════════════════════════

function actionList(int $userId, array $input): void {
    $topicId = inpInt($input, 'topic_id', true);

    // Valida posse do assunto via subjects
    $topic = dbRow(
        'SELECT t.id, t.name
         FROM topics t
         JOIN subjects s ON s.id = t.subject_id
         WHERE t.id = ? AND s.user_id = ? AND s.is_active = 1',
        [$topicId, $userId]
    );
    if (!$topic) fail('Assunto não encontrado.', 404);

    $lessons = dbQuery(
        'SELECT
            l.id,
            l.title,
            l.youtube_id,
            l.order_num,
            l.is_completed,
            l.completed_at,
            l.created_at,
            -- Indica se há anotação salva para essa aula
            EXISTS (
                SELECT 1 FROM lesson_notes ln
                WHERE ln.lesson_id = l.id
                  AND ln.user_id   = ?
                  AND ln.content  != ""
            ) AS has_notes
         FROM lessons l
         WHERE l.topic_id = ?
         ORDER BY l.order_num ASC, l.created_at ASC',
        [$userId, $topicId]
    );

    // Converte flags para bool
    foreach ($lessons as &$lesson) {
        $lesson['is_completed'] = (bool) $lesson['is_completed'];
        $lesson['has_notes']    = (bool) $lesson['has_notes'];
    }
    unset($lesson);

    ok($lessons);
}

// ════════════════════════════════════════════════════════════
// CRIAR AULA
// ════════════════════════════════════════════════════════════

function actionCreate(int $userId, array $input): void {
    $topicId   = inpInt($input, 'topic_id', true);
    $title     = inp($input,    'title',    true);
    $youtubeId = inp($input,    'youtube_id');
    $youtubeUrl = inp($input,   'youtube_url'); // Aceita URL completa também

    // Valida posse do assunto
    $topic = dbRow(
        'SELECT t.id
         FROM topics t
         JOIN subjects s ON s.id = t.subject_id
         WHERE t.id = ? AND s.user_id = ? AND s.is_active = 1',
        [$topicId, $userId]
    );
    if (!$topic) fail('Assunto não encontrado.', 404);

    // Validações do título
    if (mb_strlen($title, 'UTF-8') < 1) {
        fail('O título da aula é obrigatório.');
    }

    if (mb_strlen($title, 'UTF-8') > 200) {
        fail('O título deve ter no máximo 200 caracteres.');
    }

    // Resolve youtube_id — aceita ID direto ou extrai da URL
    $resolvedId = _resolveYoutubeId($youtubeId, $youtubeUrl);

    // Próximo order_num
    $maxOrder = dbRow(
        'SELECT COALESCE(MAX(order_num), 0) AS max_order FROM lessons WHERE topic_id = ?',
        [$topicId]
    );
    $orderNum = (int) $maxOrder['max_order'] + 1;

    dbExec(
        'INSERT INTO lessons (topic_id, title, youtube_id, order_num)
         VALUES (?, ?, ?, ?)',
        [$topicId, $title, $resolvedId, $orderNum]
    );

    $lessonId = (int) dbLastId();
    $lesson   = dbRow(
        'SELECT id, title, youtube_id, order_num, is_completed FROM lessons WHERE id = ?',
        [$lessonId]
    );

    ok($lesson, 'Aula adicionada!');
}

// ════════════════════════════════════════════════════════════
// ATUALIZAR AULA
// ════════════════════════════════════════════════════════════

function actionUpdate(int $userId, array $input): void {
    $lessonId   = inpInt($input, 'lesson_id',   true);
    $title      = inp($input,    'title',       true);
    $youtubeId  = inp($input,    'youtube_id');
    $youtubeUrl = inp($input,    'youtube_url');

    ownershipCheck('lessons', $lessonId, $userId);

    if (mb_strlen($title, 'UTF-8') < 1) {
        fail('O título da aula é obrigatório.');
    }

    if (mb_strlen($title, 'UTF-8') > 200) {
        fail('O título deve ter no máximo 200 caracteres.');
    }

    $resolvedId = _resolveYoutubeId($youtubeId, $youtubeUrl);

    dbExec(
        'UPDATE lessons SET title = ?, youtube_id = ? WHERE id = ?',
        [$title, $resolvedId, $lessonId]
    );

    ok(null, 'Aula atualizada.');
}

// ════════════════════════════════════════════════════════════
// DELETAR AULA
// ════════════════════════════════════════════════════════════

function actionDelete(int $userId, array $input): void {
    $lessonId = inpInt($input, 'lesson_id', true);

    ownershipCheck('lessons', $lessonId, $userId);

    // CASCADE remove lesson_notes vinculadas
    dbExec('DELETE FROM lessons WHERE id = ?', [$lessonId]);

    ok(null, 'Aula removida.');
}

// ════════════════════════════════════════════════════════════
// MARCAR / DESMARCAR COMO CONCLUÍDA
// ════════════════════════════════════════════════════════════

function actionComplete(int $userId, array $input): void {
    $lessonId  = inpInt($input, 'lesson_id', true);
    $completed = isset($input['completed']) ? (bool) $input['completed'] : true;

    ownershipCheck('lessons', $lessonId, $userId);

    $lesson = dbRow('SELECT id, is_completed FROM lessons WHERE id = ?', [$lessonId]);
    if (!$lesson) fail('Aula não encontrada.', 404);

    // Sem mudança — evita XP duplicado
    if ((bool) $lesson['is_completed'] === $completed) {
        ok(null, 'Nenhuma alteração.');
        return;
    }

    $completedAt = $completed ? date('Y-m-d H:i:s') : null;

    dbExec(
        'UPDATE lessons SET is_completed = ?, completed_at = ? WHERE id = ?',
        [(int) $completed, $completedAt, $lessonId]
    );

    if ($completed) {
        // XP por concluir aula
        $xpAmount = 20;
        addXP($userId, $xpAmount);

        // Atualiza resumo diário
        updateDailySummary($userId, 0, $xpAmount, 1);

        // Verifica conquistas por aulas concluídas
        $totalDone = dbRow(
            'SELECT COUNT(*) AS total
             FROM lessons l
             JOIN topics t   ON t.id = l.topic_id
             JOIN subjects s ON s.id = t.subject_id
             WHERE s.user_id = ? AND l.is_completed = 1',
            [$userId]
        );
        checkAchievements($userId, 'lessons_done', (int) ($totalDone['total'] ?? 0));

        ok(['xp_earned' => $xpAmount], 'Aula concluída! +' . $xpAmount . ' XP 🎉');
    } else {
        ok(null, 'Aula desmarcada.');
    }
}

// ════════════════════════════════════════════════════════════
// REORDENAR AULAS
// ════════════════════════════════════════════════════════════

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

        foreach ($order as $position => $lessonId) {
            $lessonId = (int) $lessonId;

            // Verifica posse via JOIN — topics → subjects → user_id
            dbExec(
                'UPDATE lessons l
                 JOIN topics t   ON t.id = l.topic_id
                 JOIN subjects s ON s.id = t.subject_id
                 SET l.order_num = ?
                 WHERE l.id = ? AND s.user_id = ?',
                [$position + 1, $lessonId, $userId]
            );
        }

        dbCommit();

    } catch (Throwable $e) {
        dbRollback();
        error_log('[Florescer] Erro ao reordenar aulas: ' . $e->getMessage());
        fail('Erro interno ao reordenar.', 500);
    }

    ok(null, 'Ordem atualizada.');
}

// ════════════════════════════════════════════════════════════
// HELPER INTERNO — RESOLVE YOUTUBE ID
// ════════════════════════════════════════════════════════════

/**
 * Resolve o ID do YouTube a partir de um ID direto ou URL completa.
 * Retorna null se nenhum dos dois for informado ou válido.
 * Encerra com erro se uma URL for informada mas for inválida.
 */
function _resolveYoutubeId(?string $youtubeId, ?string $youtubeUrl): ?string {
    // ID direto tem prioridade
    if ($youtubeId !== null && $youtubeId !== '') {
        // Valida formato do ID (11 caracteres alfanuméricos + _ -)
        if (!preg_match('/^[a-zA-Z0-9_-]{11}$/', $youtubeId)) {
            fail('ID do YouTube inválido.');
        }
        return $youtubeId;
    }

    // Tenta extrair da URL
    if ($youtubeUrl !== null && $youtubeUrl !== '') {
        $extracted = extractYoutubeId($youtubeUrl);
        if ($extracted === null) {
            fail('Link do YouTube inválido. Cole um link válido do youtube.com ou youtu.be.');
        }
        return $extracted;
    }

    // Nenhum informado — aula sem vídeo
    return null;
}