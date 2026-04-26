<?php
// ============================================================
// /api/progress.php — florescer v3.1
// Sistema XP justo e não trivial
//
// REGRAS DE XP:
//   +5  XP por minuto estudado (cap: 40 min/sessão → 200 XP/dia via tempo)
//   +20 XP por aula concluída (sem cap)
//   +50 XP ao bater a meta diária
//   +3  XP bônus por dia de streak ativo
//   +35 XP bônus a cada 7 dias consecutivos (1 semana completa)
//   +10 XP ao criar um objetivo
//
// LÓGICA DE ÁGUA (streak):
//   - 3 águas totais por usuário (water_chances na tabela users)
//   - Cada dia sem bater a meta → -1 água (idempotente por last_penalty_date)
//   - 0 águas → streak zera, água restaura para 3
//   - Bater a meta → streak cresce; água NÃO é consumida
//
// BARRA DE XP:
//   - Retorna xp_pct, xp_in_level, xp_needed calculados no servidor
//   - Nunca retorna valores negativos ou > 100%
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
    'dashboard'    => actionDashboard($userId),
    'full'         => actionFull($userId),
    'achievements' => actionAchievements($userId),
    'settings'     => actionSettings($userId),
    'add_xp'       => actionAddXp($userId, $input),
    'check_level'  => actionCheckLevel($userId),
    'sync_streak'  => actionSyncStreak($userId),
    default        => fail('Ação inválida.', 400),
};

// ════════════════════════════════════════════════════════════
// TABELA DE XP POR NÍVEL
// ════════════════════════════════════════════════════════════

function xpForLevel(int $lv): int {
    static $t = [
        1  => 0,         2  => 500,       3  => 1_500,     4  => 3_500,
        5  => 7_000,     6  => 13_000,    7  => 22_000,    8  => 35_000,
        9  => 52_000,    10 => 75_000,    11 => 105_000,   12 => 142_000,
        13 => 187_000,   14 => 242_000,   15 => 308_000,   16 => 386_000,
        17 => 478_000,   18 => 585_000,   19 => 710_000,   20 => 855_000,
        21 => 1_022_000, 22 => 1_215_000, 23 => 1_436_000, 24 => 1_689_000,
        25 => 1_978_000, 26 => 2_308_000, 27 => 2_682_000, 28 => 3_107_000,
        29 => 3_588_000, 30 => 4_131_000, 31 => 4_744_000, 32 => 5_434_000,
        33 => 6_209_000, 34 => 7_076_000, 35 => 8_044_000, 36 => 9_123_000,
        37 => 10_323_000,38 => 11_656_000,39 => 13_133_000,40 => 14_768_000,
        41 => 16_573_000,42 => 18_563_000,43 => 20_751_000,44 => 23_152_000,
        45 => 25_782_000,46 => 28_658_000,47 => 31_797_000,48 => 35_219_000,
        49 => 38_943_000,50 => 42_989_000,
    ];
    return $t[min(max(1, $lv), 50)] ?? $t[50];
}

/**
 * Calcula o nível correto para um total de XP — sempre recalcula,
 * nunca confia no valor salvo no banco.
 */
function calcLevel(int $totalXp): int {
    for ($lv = 50; $lv >= 1; $lv--) {
        if ($totalXp >= xpForLevel($lv)) return $lv;
    }
    return 1;
}

/**
 * Retorna os dados da barra de XP para o frontend.
 * xp_pct nunca é negativo nem maior que 100.
 */
function xpBarData(int $totalXp): array {
    $level    = calcLevel($totalXp);
    $curThres = xpForLevel($level);
    $nxtThres = xpForLevel($level + 1);   // Para nível 50, retorna o mesmo threshold

    $xpInLevel  = max(0, $totalXp - $curThres);
    $xpNeeded   = max(1, $nxtThres - $curThres);
    $pct        = ($level >= 50) ? 100 : min(100, (int)round($xpInLevel / $xpNeeded * 100));

    return [
        'level'          => $level,
        'xp'             => $totalXp,
        'xp_in_level'    => $xpInLevel,
        'xp_needed'      => $xpNeeded,
        'xp_pct'         => $pct,           // 0–100, nunca NaN
        'xp_for_current' => $curThres,
        'xp_for_next'    => $nxtThres,
        'xp_remaining'   => max(0, $nxtThres - $totalXp),
    ];
}

/**
 * Concede XP ao usuário, faz level-up se necessário e corrige o banco.
 * Retorna array com novos valores + flag de level-up.
 */
function grantXp(int $userId, int $amount): array {
    if ($amount <= 0) {
        $row = dbRow('SELECT xp, level FROM users WHERE id=?', [$userId]);
        return array_merge(xpBarData((int)($row['xp'] ?? 0)), [
            'leveled_up'    => false,
            'levels_gained' => 0,
            'xp_gained'     => 0,
        ]);
    }

    $row = dbRow('SELECT xp, level FROM users WHERE id=?', [$userId]);
    if (!$row) fail('Usuário não encontrado.', 404);

    $oldXp    = (int)$row['xp'];
    $oldLevel = (int)$row['level'];
    $newXp    = $oldXp + $amount;
    $newLevel = max($oldLevel, calcLevel($newXp));  // nunca retroage
    $newLevel = min($newLevel, 50);

    dbExec('UPDATE users SET xp=?, level=? WHERE id=?', [$newXp, $newLevel, $userId]);

    return array_merge(xpBarData($newXp), [
        'leveled_up'    => $newLevel > $oldLevel,
        'levels_gained' => $newLevel - $oldLevel,
        'xp_gained'     => $amount,
    ]);
}

// ════════════════════════════════════════════════════════════
// LÓGICA DE ÁGUA / STREAK (idempotente, segura p/ reload)
// ════════════════════════════════════════════════════════════

/**
 * Sincroniza o estado de streak e água.
 * Chame uma vez por carregamento de página.
 * É completamente idempotente: pode ser chamada N vezes no mesmo dia.
 *
 * Retorna: ['streak','waterLeft','dropsDisplay','seedState','penaltyToday','streakJustDied']
 */
function syncStreak(int $userId): array {
    date_default_timezone_set('America/Recife');
    $today     = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    $u = dbRow(
        'SELECT streak, water_chances, last_streak_date, last_penalty_date FROM users WHERE id=?',
        [$userId]
    );

    $streak      = max(0, (int)($u['streak']           ?? 0));
    $water       = max(0, min(3, (int)($u['water_chances'] ?? 3)));
    $lastStreak  = $u['last_streak_date']  ?? null;
    $lastPenalty = $u['last_penalty_date'] ?? null;

    // Verificar se cumpriu a meta hoje
    $todayGoal = (bool)(dbRow(
        'SELECT goal_reached FROM daily_summaries WHERE user_id=? AND study_date=?',
        [$userId, $today]
    )['goal_reached'] ?? false);

    $streakJustDied = false;
    $penaltyToday   = false;

    // ── Lógica de penalidade (idempotente por last_penalty_date) ──
    // Aplica se: ontem o usuário NÃO bateu a meta E ainda não foi penalizado hoje
    if ($lastPenalty !== $today) {
        // Verifica se ontem houve falha (sem daily_summary OU goal_reached=0)
        $yesterdaySummary = dbRow(
            'SELECT goal_reached FROM daily_summaries WHERE user_id=? AND study_date=?',
            [$userId, $yesterday]
        );
        $yesterdayGoal = (bool)($yesterdaySummary['goal_reached'] ?? false);

        // Aplica penalidade se: ontem existiu um dia de uso (lastStreak em dia anterior)
        // e não cumpriu a meta de ontem
        $hadActivity = ($lastStreak !== null && $lastStreak < $today);

        if ($hadActivity && !$yesterdayGoal) {
            $water--;
            $penaltyToday = true;

            if ($water <= 0) {
                // Streak morreu — zera e restaura água
                $water          = 3;
                $streak         = 0;
                $streakJustDied = true;
                dbExec(
                    'UPDATE users SET streak=0, water_chances=3, last_penalty_date=?, streak_max=streak_max WHERE id=?',
                    [$today, $userId]
                );
            } else {
                dbExec(
                    'UPDATE users SET water_chances=?, last_penalty_date=? WHERE id=?',
                    [$water, $today, $userId]
                );
            }
        }
    } else {
        // Já foi penalizado hoje — apenas relê o estado
        $penaltyToday = ($u['last_penalty_date'] === $today);
    }

    // ── Incrementa streak se meta de hoje foi cumprida (idempotente por last_streak_date) ──
    if ($todayGoal && $lastStreak !== $today) {
        $streak++;
        // Restaura água ao bater a meta?
        // Design: SIM — bater a meta "rega" (restaura 1 água, máx 3)
        $water = min(3, $water + 1);
        $newMax = max($streak, (int)(dbRow('SELECT streak_max FROM users WHERE id=?', [$userId])['streak_max'] ?? 0));
        dbExec(
            'UPDATE users SET streak=?, streak_max=?, water_chances=?, last_streak_date=? WHERE id=?',
            [$streak, $newMax, $water, $today, $userId]
        );
    }

    // ── Estado visual da semente ──
    $seedState = 'healthy';
    if ($streakJustDied || ($streak === 0 && $water === 3 && $lastStreak !== null && $lastStreak < $today)) {
        $seedState = 'dead';
    } elseif ($water < 3 || ($penaltyToday && !$todayGoal)) {
        $seedState = 'warning';
    }

    return [
        'streak'         => $streak,
        'waterLeft'      => $water,
        'dropsDisplay'   => $water,
        'seedState'      => $seedState,     // 'healthy'|'warning'|'dead'
        'penaltyToday'   => $penaltyToday,
        'streakJustDied' => $streakJustDied,
        'goalToday'      => $todayGoal,
    ];
}

// ════════════════════════════════════════════════════════════
// ACTION: dashboard
// ════════════════════════════════════════════════════════════

function actionDashboard(int $userId): void {
    date_default_timezone_set('America/Recife');
    $today = date('Y-m-d');

    $user = dbRow(
        'SELECT name, xp, level, streak, streak_max, daily_goal_min, last_study_date, water_chances
         FROM users WHERE id=?',
        [$userId]
    );
    if (!$user) fail('Usuário não encontrado.', 404);

    // Sincroniza streak/água (idempotente)
    $ss = syncStreak($userId);

    $daily = dbRow(
        'SELECT total_min, xp_earned, lessons_done, goal_reached
         FROM daily_summaries WHERE user_id=? AND study_date=?',
        [$userId, $today]
    );

    $lessonsTotal = (int)(dbRow(
        'SELECT COUNT(*) AS total FROM lessons l
         JOIN topics t ON t.id=l.topic_id
         JOIN subjects s ON s.id=t.subject_id
         WHERE s.user_id=? AND l.is_completed=1',
        [$userId]
    )['total'] ?? 0);

    // XP bar data — sempre calculado no servidor
    $xpData = xpBarData((int)$user['xp']);

    // Corrige nível no banco silenciosamente se divergiu
    if ($xpData['level'] !== (int)$user['level']) {
        dbExec('UPDATE users SET level=? WHERE id=?', [$xpData['level'], $userId]);
    }

    $studiedToday = (int)($daily['total_min'] ?? 0);
    $goalMin      = (int)$user['daily_goal_min'];
    $goalPct      = $goalMin > 0 ? min(100, (int)round($studiedToday / $goalMin * 100)) : 0;

    ok([
        'name'           => $user['name'],
        'xp'             => $xpData['xp'],
        'level'          => $xpData['level'],
        'xp_pct'         => $xpData['xp_pct'],
        'xp_in_level'    => $xpData['xp_in_level'],
        'xp_needed'      => $xpData['xp_needed'],
        'xp_for_next'    => $xpData['xp_for_next'],
        'xp_remaining'   => $xpData['xp_remaining'],
        'streak'         => $ss['streak'],
        'streak_max'     => (int)$user['streak_max'],
        'water_left'     => $ss['waterLeft'],
        'drops_display'  => $ss['dropsDisplay'],
        'seed_state'     => $ss['seedState'],
        'penalty_today'  => $ss['penaltyToday'],
        'streak_died'    => $ss['streakJustDied'],
        'goal_today'     => $ss['goalToday'],
        'daily_goal_min' => $goalMin,
        'today_min'      => $studiedToday,
        'goal_pct'       => $goalPct,
        'today_xp'       => (int)($daily['xp_earned']   ?? 0),
        'today_lessons'  => (int)($daily['lessons_done'] ?? 0),
        'goal_reached'   => (bool)($daily['goal_reached'] ?? false),
        'lessons_done'   => $lessonsTotal,
    ]);
}

// ════════════════════════════════════════════════════════════
// ACTION: full — dados para página de progresso
// ════════════════════════════════════════════════════════════

function actionFull(int $userId): void {
    date_default_timezone_set('America/Recife');
    $today = date('Y-m-d');

    $user = dbRow(
        'SELECT xp, level, streak, streak_max, daily_goal_min, water_chances FROM users WHERE id=?',
        [$userId]
    );
    if (!$user) fail('Usuário não encontrado.', 404);

    // Sincroniza streak/água
    $ss = syncStreak($userId);

    // XP bar — recalculado sempre
    $xpData = xpBarData((int)$user['xp']);
    if ($xpData['level'] !== (int)$user['level']) {
        dbExec('UPDATE users SET level=? WHERE id=?', [$xpData['level'], $userId]);
    }

    $totalMin = (int)(dbRow(
        'SELECT COALESCE(SUM(total_min),0) AS n FROM daily_summaries WHERE user_id=?',
        [$userId]
    )['n'] ?? 0);

    $lessonsDone = (int)(dbRow(
        'SELECT COUNT(*) AS n FROM lessons l
         JOIN topics t ON t.id=l.topic_id
         JOIN subjects s ON s.id=t.subject_id
         WHERE s.user_id=? AND l.is_completed=1',
        [$userId]
    )['n'] ?? 0);

    $goalsReached = (int)(dbRow(
        'SELECT COUNT(*) AS n FROM daily_summaries WHERE user_id=? AND goal_reached=1',
        [$userId]
    )['n'] ?? 0);

    $totalDays = (int)(dbRow(
        'SELECT COUNT(*) AS n FROM daily_summaries WHERE user_id=? AND total_min>0',
        [$userId]
    )['n'] ?? 0);

    $totalObjectives = (int)(dbRow(
        'SELECT COUNT(*) AS n FROM objectives WHERE user_id=?',
        [$userId]
    )['n'] ?? 0);

    $weekMin = (int)(dbRow(
        'SELECT COALESCE(SUM(total_min),0) AS n FROM daily_summaries
         WHERE user_id=? AND study_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)',
        [$userId]
    )['n'] ?? 0);

    $weekDays = (int)(dbRow(
        'SELECT COUNT(*) AS n FROM daily_summaries
         WHERE user_id=? AND total_min>0 AND study_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)',
        [$userId]
    )['n'] ?? 0);

    $weekGoalsHit = (int)(dbRow(
        'SELECT COUNT(*) AS n FROM daily_summaries
         WHERE user_id=? AND goal_reached=1 AND study_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)',
        [$userId]
    )['n'] ?? 0);

    $achievements = dbQuery(
        'SELECT achievement_id, unlocked_at FROM user_achievements
         WHERE user_id=? ORDER BY unlocked_at DESC',
        [$userId]
    );

    ok([
        'xp'              => $xpData['xp'],
        'level'           => $xpData['level'],
        'xp_pct'          => $xpData['xp_pct'],
        'xp_in_level'     => $xpData['xp_in_level'],
        'xp_needed'       => $xpData['xp_needed'],
        'xp_for_current'  => $xpData['xp_for_current'],
        'xp_for_next'     => $xpData['xp_for_next'],
        'xp_remaining'    => $xpData['xp_remaining'],
        'streak'          => $ss['streak'],
        'streak_max'      => max((int)$user['streak_max'], $ss['streak']),
        'water_left'      => $ss['waterLeft'],
        'drops_display'   => $ss['dropsDisplay'],
        'seed_state'      => $ss['seedState'],
        'penalty_today'   => $ss['penaltyToday'],
        'streak_died'     => $ss['streakJustDied'],
        'goal_today'      => $ss['goalToday'],
        'total_min'       => $totalMin,
        'lessons_done'    => $lessonsDone,
        'goals_reached'   => $goalsReached,
        'total_days'      => $totalDays,
        'total_objectives'=> $totalObjectives,
        'week_min'        => $weekMin,
        'week_days'       => $weekDays,
        'week_goals_hit'  => $weekGoalsHit,
        'achievements'    => $achievements,
    ]);
}

// ════════════════════════════════════════════════════════════
// ACTION: sync_streak — sincroniza e retorna estado (AJAX)
// ════════════════════════════════════════════════════════════

function actionSyncStreak(int $userId): void {
    $ss = syncStreak($userId);
    ok($ss);
}

// ════════════════════════════════════════════════════════════
// ACTION: add_xp — concede XP com base em ação
// ════════════════════════════════════════════════════════════

function actionAddXp(int $userId, array $input): void {
    date_default_timezone_set('America/Recife');
    $reason  = inp($input, 'reason', true);
    $minutes = max(0, (int)($input['minutes'] ?? 0));
    $today   = date('Y-m-d');

    switch ($reason) {

        // ── Tempo de estudo ───────────────────────────────
        case 'study_time': {
            if ($minutes < 1) fail('Sessão muito curta para ganhar XP.', 400);

            $xpTodayTime = (int)(dbRow(
                'SELECT COALESCE(SUM(xp_earned),0) AS n
                 FROM study_sessions WHERE user_id=? AND session_date=?',
                [$userId, $today]
            )['n'] ?? 0);

            $cappedMin    = min($minutes, 40);
            $rawXp        = $cappedMin * 5;
            $remainingCap = max(0, 200 - $xpTodayTime);
            $xpToGrant    = min($rawXp, $remainingCap);

            if ($xpToGrant <= 0) {
                $row = dbRow('SELECT xp FROM users WHERE id=?', [$userId]);
                ok(array_merge(xpBarData((int)($row['xp'] ?? 0)), [
                    'xp_gained' => 0,
                    'reason'    => 'daily_cap_reached',
                    'leveled_up'    => false,
                    'levels_gained' => 0,
                ]));
                return;
            }

            $result = grantXp($userId, $xpToGrant);
            ok(array_merge($result, ['reason' => $reason]));
            break;
        }

        // ── Aula concluída ────────────────────────────────
        case 'lesson': {
            $lessonId = (int)($input['lesson_id'] ?? 0);
            if ($lessonId <= 0) fail('lesson_id inválido.', 400);
            if (!dbRow('SELECT id FROM lessons WHERE id=?', [$lessonId]))
                fail('Aula não encontrada.', 404);

            $result = grantXp($userId, 20);
            ok(array_merge($result, ['reason' => $reason]));
            break;
        }

        // ── Meta diária atingida ──────────────────────────
        case 'goal': {
            $result = grantXp($userId, 50);
            // Sincroniza streak após bater meta
            $ss = syncStreak($userId);
            ok(array_merge($result, [
                'reason'        => $reason,
                'streak'        => $ss['streak'],
                'water_left'    => $ss['waterLeft'],
                'drops_display' => $ss['dropsDisplay'],
                'seed_state'    => $ss['seedState'],
            ]));
            break;
        }

        // ── Bônus de streak ───────────────────────────────
        case 'streak': {
            $streak  = max(0, (int)($input['streak'] ?? 0));
            $xpStreak = 3;
            if ($streak > 0 && $streak % 7 === 0) $xpStreak += 35;
            $result = grantXp($userId, $xpStreak);
            ok(array_merge($result, ['reason' => $reason, 'streak' => $streak]));
            break;
        }

        // ── Criar objetivo ────────────────────────────────
        case 'objective': {
            $result = grantXp($userId, 10);
            ok(array_merge($result, ['reason' => $reason]));
            break;
        }

        default:
            fail('Razão de XP inválida.', 400);
    }
}

// ════════════════════════════════════════════════════════════
// ACTION: check_level — corrige nível e retorna barra de XP
// ════════════════════════════════════════════════════════════

function actionCheckLevel(int $userId): void {
    $row = dbRow('SELECT xp, level FROM users WHERE id=?', [$userId]);
    if (!$row) fail('Usuário não encontrado.', 404);

    $xp           = (int)$row['xp'];
    $currentLevel = (int)$row['level'];
    $data         = xpBarData($xp);

    if ($data['level'] !== $currentLevel) {
        dbExec('UPDATE users SET level=? WHERE id=?', [$data['level'], $userId]);
    }

    ok(array_merge($data, ['level_fixed' => $data['level'] !== $currentLevel]));
}

// ════════════════════════════════════════════════════════════
// ACTION: achievements
// ════════════════════════════════════════════════════════════

function actionAchievements(int $userId): void {
    $achievements = dbQuery(
        'SELECT a.id, a.slug, a.name, a.description, a.icon, a.xp_reward,
                a.condition_type, a.condition_value,
                (ua.unlocked_at IS NOT NULL) AS is_unlocked,
                ua.unlocked_at
         FROM achievements a
         LEFT JOIN user_achievements ua ON ua.achievement_id=a.id AND ua.user_id=?
         ORDER BY (ua.unlocked_at IS NOT NULL) DESC, a.condition_value ASC',
        [$userId]
    );

    foreach ($achievements as &$a) {
        $a['is_unlocked'] = (bool)$a['is_unlocked'];
        $a['xp_reward']   = (int)$a['xp_reward'];
    }
    unset($a);

    ok($achievements);
}

// ════════════════════════════════════════════════════════════
// ACTION: settings
// ════════════════════════════════════════════════════════════

function actionSettings(int $userId): void {
    $settings = dbRow(
        'SELECT us.pomodoro_min, us.short_break_min, us.long_break_min,
                us.long_break_after, us.notifications, us.pix_key,
                u.daily_goal_min, u.name, u.email, u.water_chances
         FROM user_settings us
         JOIN users u ON u.id=us.user_id
         WHERE us.user_id=?',
        [$userId]
    );
    if (!$settings) fail('Configurações não encontradas.', 404);
    $settings['notifications'] = (bool)$settings['notifications'];
    ok($settings);
}