<?php
// ============================================================
// /api/history.php
// Ações: list | calendar
// Histórico de sessões e dados para o calendário GitHub-style
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
    'calendar' => actionCalendar($userId, $input),
    default    => fail('Ação inválida.', 400),
};

// ════════════════════════════════════════════════════════════
// LISTAR HISTÓRICO DE SESSÕES
// ════════════════════════════════════════════════════════════

function actionList(int $userId, array $input): void {
    $limit  = inpInt($input, 'limit')  ?? 20;
    $offset = inpInt($input, 'offset') ?? 0;

    // Limites de segurança
    if ($limit  < 1 || $limit  > 100) $limit  = 20;
    if ($offset < 0)                   $offset = 0;

    // Resumo diário agrupado — cada linha é um dia de estudo
    $rows = dbQuery(
        'SELECT
            ds.study_date,
            ds.total_min,
            ds.xp_earned,
            ds.lessons_done,
            ds.goal_reached,
            -- Matérias estudadas no dia (via sessões)
            GROUP_CONCAT(
                DISTINCT s.name
                ORDER BY s.name ASC
                SEPARATOR ", "
            ) AS subjects
         FROM daily_summaries ds
         LEFT JOIN study_sessions ss
            ON  ss.user_id      = ds.user_id
            AND ss.session_date = ds.study_date
         LEFT JOIN subjects s
            ON  s.id      = ss.subject_id
            AND s.user_id = ds.user_id
         WHERE ds.user_id = ?
         GROUP BY ds.study_date, ds.total_min, ds.xp_earned, ds.lessons_done, ds.goal_reached
         ORDER BY ds.study_date DESC
         LIMIT ? OFFSET ?',
        [$userId, $limit, $offset]
    );

    // Formata dados para o front
    foreach ($rows as &$row) {
        $row['goal_reached']  = (bool) $row['goal_reached'];
        $row['total_min']     = (int)  $row['total_min'];
        $row['xp_earned']     = (int)  $row['xp_earned'];
        $row['lessons_done']  = (int)  $row['lessons_done'];
        $row['time_formatted'] = formatMinutes((int) $row['total_min']);
        // Remove matérias null/vazias
        $row['subjects'] = $row['subjects'] ?? '';
    }
    unset($row);

    // Total de dias estudados para paginação
    $total = dbRow(
        'SELECT COUNT(*) AS total FROM daily_summaries WHERE user_id = ?',
        [$userId]
    );

    ok([
        'data'   => $rows,
        'total'  => (int) ($total['total'] ?? 0),
        'limit'  => $limit,
        'offset' => $offset,
    ]);
}

// ════════════════════════════════════════════════════════════
// CALENDÁRIO ESTILO GITHUB
// ════════════════════════════════════════════════════════════

function actionCalendar(int $userId, array $input): void {
    $months = inpInt($input, 'months') ?? 6;

    // Limite de segurança
    if ($months < 1 || $months > 12) $months = 6;

    $from = date('Y-m-d', strtotime("-{$months} months"));
    $to   = date('Y-m-d');

    // Busca minutos estudados por dia no período
    $rows = dbQuery(
        'SELECT study_date, total_min, goal_reached
         FROM daily_summaries
         WHERE user_id    = ?
           AND study_date >= ?
           AND study_date <= ?
         ORDER BY study_date ASC',
        [$userId, $from, $to]
    );

    // Transforma em mapa date → dados para acesso O(1) no front
    $calendar = [];
    foreach ($rows as $row) {
        $calendar[$row['study_date']] = [
            'min'          => (int)  $row['total_min'],
            'goal_reached' => (bool) $row['goal_reached'],
        ];
    }

    // Estatísticas do período
    $stats = _calcCalendarStats($rows);

    ok([
        'data'  => $calendar,   // { "2025-01-15": { min: 45, goal_reached: true }, ... }
        'from'  => $from,
        'to'    => $to,
        'stats' => $stats,
    ]);
}

// ════════════════════════════════════════════════════════════
// HELPER INTERNO — ESTATÍSTICAS DO CALENDÁRIO
// ════════════════════════════════════════════════════════════

/**
 * Calcula estatísticas do período para exibição no histórico.
 */
function _calcCalendarStats(array $rows): array {
    if (empty($rows)) {
        return [
            'total_days'   => 0,
            'total_min'    => 0,
            'avg_min_day'  => 0,
            'best_day_min' => 0,
            'goals_hit'    => 0,
        ];
    }

    $totalMin    = 0;
    $bestDayMin  = 0;
    $goalsHit    = 0;

    foreach ($rows as $row) {
        $min       = (int) $row['total_min'];
        $totalMin += $min;

        if ($min > $bestDayMin) {
            $bestDayMin = $min;
        }

        if ($row['goal_reached']) {
            $goalsHit++;
        }
    }

    $totalDays  = count($rows);
    $avgMinDay  = $totalDays > 0 ? (int) round($totalMin / $totalDays) : 0;

    return [
        'total_days'   => $totalDays,
        'total_min'    => $totalMin,
        'avg_min_day'  => $avgMinDay,
        'best_day_min' => $bestDayMin,
        'goals_hit'    => $goalsHit,
    ];
}