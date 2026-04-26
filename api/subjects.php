<?php
// ============================================================
// /api/subjects.php
// Ações: list | create | update | delete | presets | reorder
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
    'presets' => actionPresets($input),
    'reorder' => actionReorder($userId, $input),
    default   => fail('Ação inválida.', 400),
};

// ════════════════════════════════════════════════════════════
// LISTAR MATÉRIAS
// ════════════════════════════════════════════════════════════

function actionList(int $userId, array $input): void {
    $objectiveId = inpInt($input, 'objective_id', true);

    // Garante que o objetivo pertence ao usuário
    $objective = dbRow(
        'SELECT id, name, default_avg FROM objectives WHERE id = ? AND user_id = ?',
        [$objectiveId, $userId]
    );
    if (!$objective) fail('Objetivo não encontrado.', 404);

    $subjects = dbQuery(
        'SELECT
            s.id,
            s.name,
            s.color,
            s.individual_avg,
            s.order_num,
            s.is_active,
            -- Usa média individual se definida, senão usa a do objetivo
            COALESCE(s.individual_avg, ?) AS effective_avg,
            -- Progresso via view
            vsp.total_lessons,
            vsp.completed_lessons,
            vsp.progress_pct
         FROM subjects s
         LEFT JOIN vw_subject_progress vsp ON vsp.subject_id = s.id
         WHERE s.objective_id = ?
           AND s.user_id      = ?
           AND s.is_active    = 1
         ORDER BY s.order_num ASC, s.created_at ASC',
        [$objective['default_avg'], $objectiveId, $userId]
    );

    ok($subjects);
}

// ════════════════════════════════════════════════════════════
// CRIAR MATÉRIA
// ════════════════════════════════════════════════════════════

function actionCreate(int $userId, array $input): void {
    $objectiveId   = inpInt($input,   'objective_id',  true);
    $name          = inp($input,      'name',          true);
    $color         = inp($input,      'color');
    $individualAvg = inpFloat($input, 'individual_avg');

    // Valida posse do objetivo
    $objective = dbRow(
        'SELECT id FROM objectives WHERE id = ? AND user_id = ?',
        [$objectiveId, $userId]
    );
    if (!$objective) fail('Objetivo não encontrado.', 404);

    // Validações
    if (mb_strlen($name, 'UTF-8') < 1) {
        fail('O nome da matéria é obrigatório.');
    }

    if (mb_strlen($name, 'UTF-8') > 100) {
        fail('O nome deve ter no máximo 100 caracteres.');
    }

    // Valida e normaliza a cor hex
    $color = _sanitizeColor($color ?? '#40916c');

    if ($individualAvg !== null && ($individualAvg < 0 || $individualAvg > 10)) {
        fail('A média deve estar entre 0 e 10.');
    }

    // Evita duplicata de nome na mesma matéria do mesmo objetivo
    $duplicate = dbRow(
        'SELECT id FROM subjects
         WHERE objective_id = ? AND user_id = ? AND name = ? AND is_active = 1',
        [$objectiveId, $userId, $name]
    );
    if ($duplicate) fail('Já existe uma matéria com esse nome neste objetivo.');

    // Define order_num como o próximo disponível
    $maxOrder = dbRow(
        'SELECT COALESCE(MAX(order_num), 0) AS max_order
         FROM subjects WHERE objective_id = ? AND user_id = ?',
        [$objectiveId, $userId]
    );
    $orderNum = (int) $maxOrder['max_order'] + 1;

    dbExec(
        'INSERT INTO subjects (user_id, objective_id, name, color, individual_avg, order_num)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$userId, $objectiveId, $name, $color, $individualAvg, $orderNum]
    );

    $subjectId = (int) dbLastId();
    $subject   = dbRow('SELECT id, name, color FROM subjects WHERE id = ?', [$subjectId]);

    ok($subject, 'Matéria adicionada!');
}

// ════════════════════════════════════════════════════════════
// ATUALIZAR MATÉRIA
// ════════════════════════════════════════════════════════════

function actionUpdate(int $userId, array $input): void {
    $subjectId     = inpInt($input,   'subject_id',    true);
    $name          = inp($input,      'name',          true);
    $color         = inp($input,      'color');
    $individualAvg = inpFloat($input, 'individual_avg');

    ownershipCheck('subjects', $subjectId, $userId);

    if (mb_strlen($name, 'UTF-8') < 1) {
        fail('O nome da matéria é obrigatório.');
    }

    if (mb_strlen($name, 'UTF-8') > 100) {
        fail('O nome deve ter no máximo 100 caracteres.');
    }

    $color = _sanitizeColor($color ?? '#40916c');

    if ($individualAvg !== null && ($individualAvg < 0 || $individualAvg > 10)) {
        fail('A média deve estar entre 0 e 10.');
    }

    dbExec(
        'UPDATE subjects
         SET name = ?, color = ?, individual_avg = ?
         WHERE id = ?',
        [$name, $color, $individualAvg, $subjectId]
    );

    ok(null, 'Matéria atualizada.');
}

// ════════════════════════════════════════════════════════════
// DELETAR MATÉRIA (soft delete)
// ════════════════════════════════════════════════════════════

function actionDelete(int $userId, array $input): void {
    $subjectId = inpInt($input, 'subject_id', true);

    ownershipCheck('subjects', $subjectId, $userId);

    // Soft delete — preserva histórico de sessões e notas
    dbExec(
        'UPDATE subjects SET is_active = 0 WHERE id = ?',
        [$subjectId]
    );

    ok(null, 'Matéria removida.');
}

// ════════════════════════════════════════════════════════════
// MATÉRIAS PRÉ-DEFINIDAS (sugestões por tipo de objetivo)
// ════════════════════════════════════════════════════════════

function actionPresets(array $input): void {
    $objectiveTypeId = inpInt($input, 'objective_type_id', true);
    $gradeLevel      = inp($input,    'grade_level');

    $params = [$objectiveTypeId];
    $sql    = 'SELECT id, name
               FROM preset_subjects
               WHERE objective_type_id = ?';

    if ($gradeLevel !== null) {
        $sql     .= ' AND grade_level = ?';
        $params[] = $gradeLevel;
    }

    $sql .= ' ORDER BY name ASC';

    $presets = dbQuery($sql, $params);

    ok($presets);
}

// ════════════════════════════════════════════════════════════
// REORDENAR MATÉRIAS
// ════════════════════════════════════════════════════════════

function actionReorder(int $userId, array $input): void {
    // Espera array de IDs na nova ordem: [3, 1, 5, 2]
    if (!isset($input['order']) || !is_array($input['order'])) {
        fail('Lista de ordenação inválida.');
    }

    $order = $input['order'];

    // Valida que todos os valores são inteiros positivos
    foreach ($order as $id) {
        if (!is_int($id) && !ctype_digit((string) $id)) {
            fail('IDs de ordenação inválidos.');
        }
    }

    try {
        dbBegin();

        foreach ($order as $position => $subjectId) {
            $subjectId = (int) $subjectId;

            // Atualiza apenas se a matéria pertence ao usuário
            dbExec(
                'UPDATE subjects
                 SET order_num = ?
                 WHERE id = ? AND user_id = ?',
                [$position + 1, $subjectId, $userId]
            );
        }

        dbCommit();

    } catch (Throwable $e) {
        dbRollback();
        error_log('[Florescer] Erro ao reordenar matérias: ' . $e->getMessage());
        fail('Erro interno ao reordenar.', 500);
    }

    ok(null, 'Ordem atualizada.');
}

// ════════════════════════════════════════════════════════════
// HELPER INTERNO — SANITIZA COR HEX
// ════════════════════════════════════════════════════════════

/**
 * Valida e retorna uma cor hex de 7 caracteres (#rrggbb).
 * Se inválida, retorna o verde padrão do Florescer.
 */
function _sanitizeColor(string $color): string {
    $color = trim($color);

    // Aceita #rgb (3 dígitos) e converte para #rrggbb
    if (preg_match('/^#([0-9a-fA-F]{3})$/', $color, $m)) {
        $r     = str_repeat($m[1][0], 2);
        $g     = str_repeat($m[1][1], 2);
        $b     = str_repeat($m[1][2], 2);
        $color = "#{$r}{$g}{$b}";
    }

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        return '#40916c'; // Verde padrão
    }

    return strtolower($color);
}