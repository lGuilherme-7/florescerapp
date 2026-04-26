<?php
// ============================================================
// /api/timer.php
// Ações: record | settings | settings_save
// Registra sessões de estudo e gerencia config do Pomodoro
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
    'record'        => actionRecord($userId, $input),
    'settings'      => actionSettings($userId),
    'settings_save' => actionSettingsSave($userId, $input),
    default         => fail('Ação inválida.', 400),
};

// ════════════════════════════════════════════════════════════
// REGISTRAR SESSÃO DE ESTUDO
// ════════════════════════════════════════════════════════════

function actionRecord(int $userId, array $input): void {
    $durationMin = inpInt($input, 'duration_min', true);
    $pomodoros   = inpInt($input, 'pomodoros')   ?? 0;
    $lessonId    = inpInt($input, 'lesson_id');
    $subjectId   = inpInt($input, 'subject_id');

    // Validações
    if ($durationMin <= 0) {
        fail('A duração deve ser maior que zero.');
    }

    if ($durationMin > 480) {
        fail('Duração máxima por sessão: 8 horas (480 minutos).');
    }

    if ($pomodoros < 0) {
        fail('Número de pomodoros inválido.');
    }

    // Valida posse da aula se informada
    if ($lessonId !== null) {
        $lessonExists = dbRow(
            'SELECT l.id
             FROM lessons l
             JOIN topics t   ON t.id = l.topic_id
             JOIN subjects s ON s.id = t.subject_id
             WHERE l.id = ? AND s.user_id = ?',
            [$lessonId, $userId]
        );
        if (!$lessonExists) {
            $lessonId = null; // Ignora silenciosamente se inválido
        }
    }

    // Valida posse da matéria se informada
    if ($subjectId !== null) {
        $subjectExists = dbRow(
            'SELECT id FROM subjects WHERE id = ? AND user_id = ? AND is_active = 1',
            [$subjectId, $userId]
        );
        if (!$subjectExists) {
            $subjectId = null;
        }
    }

    // Calcula XP da sessão:
    // - 5 XP por minuto estudado
    // - 15 XP bônus por pomodoro completo
    $xpEarned = ($durationMin * 5) + ($pomodoros * 15);

    $today     = date('Y-m-d');
    $now       = date('Y-m-d H:i:s');
    $startedAt = date('Y-m-d H:i:s', strtotime("-{$durationMin} minutes"));

    try {
        dbBegin();

        // Registra a sessão
        dbExec(
            'INSERT INTO study_sessions
                (user_id, lesson_id, subject_id, started_at, ended_at, duration_min, pomodoros, xp_earned, session_date)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $lessonId, $subjectId, $startedAt, $now, $durationMin, $pomodoros, $xpEarned, $today]
        );

        // Atualiza XP e nível
        addXP($userId, $xpEarned);

        // Atualiza resumo diário e streak
        updateDailySummary($userId, $durationMin, $xpEarned, 0);

        dbCommit();

    } catch (Throwable $e) {
        dbRollback();
        error_log('[Florescer] Erro ao registrar sessão: ' . $e->getMessage());
        fail('Erro interno ao registrar sessão.', 500);
    }

    // Busca dados atualizados para retornar ao front
    $user = dbRow(
        'SELECT xp, level, streak FROM users WHERE id = ?',
        [$userId]
    );

    $todaySummary = dbRow(
        'SELECT total_min, goal_reached FROM daily_summaries WHERE user_id = ? AND study_date = ?',
        [$userId, $today]
    );

    ok([
        'xp_earned'    => $xpEarned,
        'xp_total'     => (int) ($user['xp']     ?? 0),
        'level'        => (int) ($user['level']   ?? 1),
        'streak'       => (int) ($user['streak']  ?? 0),
        'today_min'    => (int) ($todaySummary['total_min']   ?? 0),
        'goal_reached' => (bool) ($todaySummary['goal_reached'] ?? false),
    ], "Sessão registrada! +{$xpEarned} XP");
}

// ════════════════════════════════════════════════════════════
// BUSCAR CONFIGURAÇÕES DO POMODORO
// ════════════════════════════════════════════════════════════

function actionSettings(int $userId): void {
    $settings = dbRow(
        'SELECT pomodoro_min, short_break_min, long_break_min, long_break_after, notifications
         FROM user_settings
         WHERE user_id = ?',
        [$userId]
    );

    // Retorna padrões se ainda não há configurações (não deveria acontecer após cadastro)
    if (!$settings) {
        ok([
            'pomodoro_min'    => 25,
            'short_break_min' => 5,
            'long_break_min'  => 15,
            'long_break_after' => 4,
            'notifications'   => true,
        ]);
        return;
    }

    $settings['notifications'] = (bool) $settings['notifications'];

    ok($settings);
}

// ════════════════════════════════════════════════════════════
// SALVAR CONFIGURAÇÕES DO POMODORO
// ════════════════════════════════════════════════════════════

function actionSettingsSave(int $userId, array $input): void {
    $pomodoroMin    = inpInt($input, 'pomodoro_min');
    $shortBreakMin  = inpInt($input, 'short_break_min');
    $longBreakMin   = inpInt($input, 'long_break_min');
    $longBreakAfter = inpInt($input, 'long_break_after');
    $notifications  = isset($input['notifications']) ? (int) (bool) $input['notifications'] : null;

    // Validações com limites razoáveis
    if ($pomodoroMin !== null && ($pomodoroMin < 5 || $pomodoroMin > 90)) {
        fail('Duração do Pomodoro deve estar entre 5 e 90 minutos.');
    }

    if ($shortBreakMin !== null && ($shortBreakMin < 1 || $shortBreakMin > 30)) {
        fail('Pausa curta deve estar entre 1 e 30 minutos.');
    }

    if ($longBreakMin !== null && ($longBreakMin < 5 || $longBreakMin > 60)) {
        fail('Pausa longa deve estar entre 5 e 60 minutos.');
    }

    if ($longBreakAfter !== null && ($longBreakAfter < 2 || $longBreakAfter > 8)) {
        fail('Pausa longa deve ocorrer a cada 2 a 8 pomodoros.');
    }

    // Monta SET dinâmico — só atualiza campos enviados
    $fields = [];
    $params = [];

    if ($pomodoroMin    !== null) { $fields[] = 'pomodoro_min = ?';     $params[] = $pomodoroMin; }
    if ($shortBreakMin  !== null) { $fields[] = 'short_break_min = ?';  $params[] = $shortBreakMin; }
    if ($longBreakMin   !== null) { $fields[] = 'long_break_min = ?';   $params[] = $longBreakMin; }
    if ($longBreakAfter !== null) { $fields[] = 'long_break_after = ?'; $params[] = $longBreakAfter; }
    if ($notifications  !== null) { $fields[] = 'notifications = ?';    $params[] = $notifications; }

    if (empty($fields)) {
        fail('Nenhum campo para atualizar.');
    }

    $params[] = $userId;

    dbExec(
        'UPDATE user_settings SET ' . implode(', ', $fields) . ' WHERE user_id = ?',
        $params
    );

    ok(null, 'Configurações salvas.');
}