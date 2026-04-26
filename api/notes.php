<?php
// ============================================================
// /api/notes.php
// Ações: get | save
// Autosave via debounce no front-end
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
    'get'  => actionGet($userId, $input),
    'save' => actionSave($userId, $input),
    default => fail('Ação inválida.', 400),
};

// ════════════════════════════════════════════════════════════
// BUSCAR ANOTAÇÃO
// ════════════════════════════════════════════════════════════

function actionGet(int $userId, array $input): void {
    $lessonId = inpInt($input, 'lesson_id', true);

    // Valida posse da aula via topics → subjects → user_id
    _assertLessonOwnership($lessonId, $userId);

    $note = dbRow(
        'SELECT content, updated_at
         FROM lesson_notes
         WHERE lesson_id = ? AND user_id = ?',
        [$lessonId, $userId]
    );

    // Retorna conteúdo vazio se ainda não há anotação — nunca 404
    ok([
        'content'    => $note['content']    ?? '',
        'updated_at' => $note['updated_at'] ?? null,
    ]);
}

// ════════════════════════════════════════════════════════════
// SALVAR ANOTAÇÃO (INSERT ou UPDATE)
// ════════════════════════════════════════════════════════════

function actionSave(int $userId, array $input): void {
    $lessonId = inpInt($input, 'lesson_id', true);
    $content  = isset($input['content']) ? (string) $input['content'] : '';

    // Valida posse da aula
    _assertLessonOwnership($lessonId, $userId);

    // Limite de tamanho — MEDIUMTEXT suporta até ~16MB, limitamos a 100KB
    if (strlen($content) > 102400) {
        fail('O conteúdo da anotação é muito longo. Máximo: 100KB.');
    }

    // UPSERT — cria ou atualiza com uma única query
    dbExec(
        'INSERT INTO lesson_notes (lesson_id, user_id, content)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE content = VALUES(content)',
        [$lessonId, $userId, $content]
    );

    ok(['updated_at' => date('Y-m-d H:i:s')], 'Anotação salva.');
}

// ════════════════════════════════════════════════════════════
// HELPER INTERNO — VALIDA POSSE DA AULA
// ════════════════════════════════════════════════════════════

/**
 * Encerra com 403 se a aula não pertencer ao usuário.
 * Verifica via lessons → topics → subjects → user_id.
 */
function _assertLessonOwnership(int $lessonId, int $userId): void {
    $exists = dbRow(
        'SELECT l.id
         FROM lessons l
         JOIN topics t   ON t.id = l.topic_id
         JOIN subjects s ON s.id = t.subject_id
         WHERE l.id = ? AND s.user_id = ? AND s.is_active = 1',
        [$lessonId, $userId]
    );

    if (!$exists) {
        fail('Aula não encontrada ou acesso negado.', 403);
    }
}