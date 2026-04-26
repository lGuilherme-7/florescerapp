<?php

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../services/StreakService.php';

startSession();
if (!isLoggedIn()) { header('Location: /florescer/public/index.php'); exit; }

// Fuso horário definido antes de qualquer chamada a date()
date_default_timezone_set('America/Recife');

$user        = currentUser();
$userId      = (int)$user['id'];
$userName    = htmlspecialchars($user['name'] ?? 'Estudante', ENT_QUOTES, 'UTF-8');
$userInitial = strtoupper(mb_substr($user['name'] ?? 'E', 0, 1, 'UTF-8'));
$currentPage = 'dashboard';
$today       = date('Y-m-d');
$yesterday   = date('Y-m-d', strtotime('-1 day'));

// ══════════════════════════════════════════════════════════════
// BLOCO 1 — Dados base do usuário
// ══════════════════════════════════════════════════════════════
$u       = dbRow('SELECT xp, level, streak, daily_goal_min, water_chances FROM users WHERE id=?', [$userId]);
$xp      = (int)($u['xp']             ?? 0);
$level   = (int)($u['level']          ?? 1);
$goalMin = (int)($u['daily_goal_min'] ?? 30);

// ══════════════════════════════════════════════════════════════
// BLOCO 2 — Progresso de hoje (leitura pura, sem escrita)
// ══════════════════════════════════════════════════════════════
$todaySummary = dbRow(
    'SELECT total_min, goal_reached FROM daily_summaries WHERE user_id=? AND study_date=?',
    [$userId, $today]
);
$studiedToday = (int)($todaySummary['total_min']    ?? 0);
$goalReached  = (bool)($todaySummary['goal_reached'] ?? false);
$goalPct      = $goalMin > 0 ? min(100, round($studiedToday / $goalMin * 100)) : 0;

// ══════════════════════════════════════════════════════════════
// BLOCO 3 — StreakService: sincroniza estado e retorna tudo pronto
//
// CORREÇÃO #1: Passa $pdo explicitamente em vez de null, para que as
// transações com FOR UPDATE usem a mesma conexão de banco garantindo
// que o row-lock funcione corretamente.
//
// Caso seu wrapper global não exponha $pdo, mantenha null — o Service
// usará dbRow/dbQuery globais (funciona, mas sem garantia de lock).
// ══════════════════════════════════════════════════════════════
global $pdo; // exposto pelo config/db.php (se disponível)
$ss = StreakService::syncAndRead(
    $userId,
    $today,
    $yesterday,
    $goalReached,
    $pdo ?? null
);

$streak         = $ss['streak'];
$waterLeft      = $ss['waterLeft'];
$dropsDisplay   = $ss['dropsDisplay'];
$streakJustDied = $ss['streakJustDied'];
$penaltyToday   = $ss['penaltyToday'];   // CORREÇÃO #5: agora usado no HTML
$seedState      = $ss['seedState'];      // 'healthy' | 'warning' | 'dead'

$seedDead    = ($seedState === 'dead');
$seedWarning = ($seedState === 'warning');

// ══════════════════════════════════════════════════════════════
// BLOCO 4 — Estágios visuais da planta
// ══════════════════════════════════════════════════════════════
$stagesDef = [
    [0,   6,   'Semente',           0],
    [7,   14,  'Broto Inicial',     1],
    [15,  29,  'Planta Jovem',      2],
    [30,  59,  'Planta Forte',      3],
    [60,  99,  'Árvore Crescendo',  4],
    [100, 149, 'Árvore Robusta',    5],
    [150, 199, 'Árvore Antiga',     6],
    [200, 299, 'Árvore Gigante',    7],
    [300, PHP_INT_MAX, 'Árvore Lendária', 8],
];
$emojis = ['🌱','🌿','☘️','🌲','🌳','🌴','🎋','✨','🏆'];

$plant = ['emoji'=>'🌱','name'=>'Semente','stage'=>0,'pct'=>0,'next'=>'Broto Inicial','daysToNext'=>7];
foreach ($stagesDef as [$mn, $mx, $nm, $si]) {
    if ($streak >= $mn && $streak <= $mx) {
        $range    = $mx < PHP_INT_MAX ? ($mx - $mn + 1) : 1;
        $pct      = $mx < PHP_INT_MAX ? min(100, round(($streak - $mn) / $range * 100)) : 100;
        $nextName = isset($stagesDef[$si + 1]) ? $stagesDef[$si + 1][2] : $nm;
        $daysNext = $mx < PHP_INT_MAX ? ($mx - $streak + 1) : 0;
        $plant    = [
            'emoji'      => $emojis[$si] ?? '🌱',
            'name'       => $nm,
            'stage'      => $si,
            'pct'        => $pct,
            'next'       => $nextName,
            'daysToNext' => $daysNext,
        ];
        break;
    }
}
// Sobrescreve emoji pelo estado da semente
if ($seedDead)        $plant['emoji'] = '🥀';
elseif ($seedWarning) $plant['emoji'] = '🌵';

// ══════════════════════════════════════════════════════════════
// BLOCO 5 — Dados auxiliares (frase, objetivo, semana, avatar)
// ══════════════════════════════════════════════════════════════

// Frase do dia
$totalMotiv = (int)(dbRow('SELECT COUNT(*) AS n FROM motivational_messages')['n'] ?? 0);
$todayMotiv = null;
$mTxtCol    = 'message';
$mAuthCol   = null;
if ($totalMotiv > 0) {
    $motivIdx   = ((int)date('z')) % $totalMotiv;
    $mCols      = array_column(dbQuery('SHOW COLUMNS FROM motivational_messages'), 'Field');
    $mTxtCol    = in_array('message', $mCols) ? 'message' : (in_array('text', $mCols) ? 'text' : (in_array('frase', $mCols) ? 'frase' : 'content'));
    $mAuthCol   = in_array('author', $mCols)  ? 'author'  : (in_array('autor', $mCols) ? 'autor' : null);
    $todayMotiv = dbRow('SELECT * FROM motivational_messages ORDER BY id ASC LIMIT 1 OFFSET ?', [$motivIdx]);
}

// Objetivo ativo + matérias
$activeObj = dbRow(
    'SELECT id, name FROM objectives WHERE user_id=? AND is_active=1 ORDER BY created_at DESC LIMIT 1',
    [$userId]
) ?? dbRow('SELECT id, name FROM objectives WHERE user_id=? ORDER BY created_at DESC LIMIT 1', [$userId]);

$subjects = [];
if ($activeObj) {
    $subjects = dbQuery(
        'SELECT s.id, s.name, s.color,
            (SELECT COUNT(*) FROM lessons l JOIN topics t ON t.id=l.topic_id WHERE t.subject_id=s.id AND l.is_completed=1) AS done,
            (SELECT COUNT(*) FROM lessons l JOIN topics t ON t.id=l.topic_id WHERE t.subject_id=s.id) AS total
         FROM subjects s WHERE s.objective_id=? AND s.is_active=1
         ORDER BY s.name ASC LIMIT 6',
        [$activeObj['id']]
    );
}

// Semana — usa $today do PHP (não CURDATE()) para consistência de fuso
$sixDaysAgo = date('Y-m-d', strtotime('-6 days'));
$weekData   = dbQuery(
    'SELECT study_date, total_min, goal_reached FROM daily_summaries
      WHERE user_id=? AND study_date >= ?
      ORDER BY study_date ASC',
    [$userId, $sixDaysAgo]
);
$weekMap    = array_column($weekData, null, 'study_date');
$weekMaxMin = 1;
foreach ($weekData as $w) {
    if ((int)$w['total_min'] > $weekMaxMin) $weekMaxMin = (int)$w['total_min'];
}

// Saudação
$hour = (int)date('G');
if ($hour >= 5 && $hour < 12)      $greeting = 'Bom dia';
elseif ($hour >= 12 && $hour < 18) $greeting = 'Boa tarde';
else                                $greeting = 'Boa noite';

// Sidebar
$lvNames       = ['','Semente','Broto','Estudante','Aplicado','Dedicado','Avançado','Expert','Mestre','Lendário'];
$lvNameSidebar = $lvNames[min($level, count($lvNames) - 1)] ?? 'Lendário';
$lvName        = $lvNameSidebar;

// Avatar
$avatarRow       = dbRow('SELECT avatar_type, avatar_emoji, avatar_url FROM users WHERE id=?', [$userId]);
$avatarType      = $avatarRow['avatar_type']  ?? 'initial';
$avatarEmoji     = $avatarRow['avatar_emoji'] ?? '';
$avatarUrl       = $avatarRow['avatar_url']   ?? '';
$avatarPublicUrl = $avatarUrl ? '/florescer/public' . $avatarUrl : '';

// Objetivos para session
$allObjs = dbQuery('SELECT id,name FROM objectives WHERE user_id=? ORDER BY is_active DESC,created_at DESC', [$userId]);
if (!isset($_SESSION['active_objective'])) {
    $ao = dbRow('SELECT id FROM objectives WHERE user_id=? AND is_active=1 ORDER BY created_at DESC LIMIT 1', [$userId]);
    if (!$ao) $ao = dbRow('SELECT id FROM objectives WHERE user_id=? ORDER BY created_at DESC LIMIT 1', [$userId]);
    $_SESSION['active_objective'] = $ao['id'] ?? null;
}
$activeObjId = $_SESSION['active_objective'] ?? null;
if (!$activeObjId && !empty($allObjs)) $activeObjId = $allObjs[0]['id'] ?? null;

// ══════════════════════════════════════════════════════════════
// BLOCO 6 — SVG da planta
// ══════════════════════════════════════════════════════════════
function getPlantSVG(int $s, bool $dead = false, bool $warn = false): string {
    if ($dead) return '<svg width="120" height="120" viewBox="0 0 120 120">
        <ellipse cx="60" cy="96" rx="30" ry="8" fill="rgba(0,0,0,.06)"/>
        <ellipse cx="60" cy="90" rx="18" ry="9" fill="#c5b8a8"/>
        <path d="M60 90 C60 75 57 58 60 44" stroke="#a08060" stroke-width="3.5" stroke-linecap="round" fill="none"/>
        <path d="M52 68 Q42 60 38 52" stroke="#a08060" stroke-width="2" stroke-linecap="round" fill="none"/>
        <path d="M68 58 Q78 50 82 42" stroke="#a08060" stroke-width="2" stroke-linecap="round" fill="none"/>
        <ellipse cx="42" cy="50" rx="9" ry="5" transform="rotate(-30 42 50)" fill="#c5b0a0" opacity=".7"/>
        <ellipse cx="82" cy="40" rx="9" ry="5" transform="rotate(30 82 40)" fill="#c5b0a0" opacity=".6"/>
        <ellipse cx="60" cy="40" rx="10" ry="7" fill="#c5b0a0" opacity=".8"/>
        <text x="60" y="115" text-anchor="middle" font-size="18" opacity=".5">🥀</text>
    </svg>';
    if ($s === 0) return '<svg width="100" height="110" viewBox="0 0 100 110">
        <ellipse cx="50" cy="98" rx="28" ry="7" fill="rgba(26,143,85,.12)"/>
        <ellipse cx="50" cy="92" rx="18" ry="9" fill="#c8e0c0"/>
        <path d="M50 92 C50 80 48 66 50 54" stroke="url(#st0)" stroke-width="3.5" stroke-linecap="round" fill="none"/>
        <defs><linearGradient id="st0" x1="0" y1="1" x2="0" y2="0"><stop offset="0%" stop-color="#0a4428"/><stop offset="100%" stop-color="#2dd48a"/></linearGradient></defs>
        <ellipse cx="42" cy="68" rx="10" ry="5.5" transform="rotate(-30 42 68)" fill="#1aaa6a" opacity=".9"/>
        <ellipse cx="58" cy="62" rx="10" ry="5.5" transform="rotate(30 58 62)" fill="#2dd48a" opacity=".85"/>
        <circle cx="50" cy="50" r="8" fill="#2dd48a" opacity=".92"/>
        <circle cx="50" cy="45" r="5" fill="#5af0b8" opacity=".8"/>
    </svg>';
    if ($s === 1) return '<svg width="130" height="160" viewBox="0 0 130 160">
        <defs><linearGradient id="st1" x1="0" y1="1" x2="0" y2="0"><stop offset="0%" stop-color="#0a4428"/><stop offset="100%" stop-color="#2dd48a"/></linearGradient></defs>
        <ellipse cx="65" cy="148" rx="36" ry="9" fill="rgba(26,143,85,.12)"/>
        <ellipse cx="65" cy="142" rx="22" ry="10" fill="#c8e0c0"/>
        <path d="M65 144 C65 128 62 106 64 84 C66 64 64 44 65 28" stroke="url(#st1)" stroke-width="5" stroke-linecap="round" fill="none"/>
        <ellipse cx="46" cy="112" rx="19" ry="9.5" transform="rotate(-38 46 112)" fill="#1aaa6a" opacity=".9"/>
        <ellipse cx="84" cy="100" rx="19" ry="9.5" transform="rotate(38 84 100)" fill="#2dd48a" opacity=".85"/>
        <ellipse cx="44" cy="82" rx="17" ry="8.5" transform="rotate(-34 44 82)" fill="#0d8a52" opacity=".88"/>
        <ellipse cx="86" cy="70" rx="17" ry="8.5" transform="rotate(34 86 70)" fill="#3deda0" opacity=".82"/>
        <ellipse cx="65" cy="42" rx="18" ry="13" fill="#2dd48a" opacity=".93"/>
        <ellipse cx="65" cy="32" rx="11" ry="7" fill="#5af0b8" opacity=".86"/>
    </svg>';
    if ($s === 2) return '<svg width="160" height="210" viewBox="0 0 160 210">
        <defs><linearGradient id="st2" x1="0" y1="1" x2="0" y2="0"><stop offset="0%" stop-color="#0a4428"/><stop offset="60%" stop-color="#1a8855"/><stop offset="100%" stop-color="#2dd48a"/></linearGradient></defs>
        <ellipse cx="80" cy="198" rx="44" ry="10" fill="rgba(26,143,85,.12)"/>
        <ellipse cx="80" cy="192" rx="28" ry="12" fill="#c8e0c0"/>
        <path d="M80 194 C80 175 76 148 78 118 C80 92 78 66 80 44 C82 26 80 12 80 6" stroke="url(#st2)" stroke-width="7" stroke-linecap="round" fill="none"/>
        <path d="M80 158 C66 148 52 142 40 134" stroke="#1a7848" stroke-width="3.5" stroke-linecap="round" fill="none"/>
        <path d="M80 142 C94 132 108 126 120 118" stroke="#1a7848" stroke-width="3.5" stroke-linecap="round" fill="none"/>
        <ellipse cx="34" cy="128" rx="20" ry="10" fill="#1aaa6a" opacity=".88"/>
        <ellipse cx="126" cy="112" rx="20" ry="10" fill="#2dd48a" opacity=".84"/>
        <ellipse cx="40" cy="100" rx="22" ry="11" transform="rotate(-35 40 100)" fill="#0d8a52" opacity=".86"/>
        <ellipse cx="120" cy="86" rx="22" ry="11" transform="rotate(35 120 86)" fill="#3deda0" opacity=".82"/>
        <ellipse cx="44" cy="68" rx="20" ry="10" transform="rotate(-30 44 68)" fill="#1aaa6a" opacity=".86"/>
        <ellipse cx="116" cy="54" rx="20" ry="10" transform="rotate(30 116 54)" fill="#2dd48a" opacity=".82"/>
        <ellipse cx="80" cy="36" rx="26" ry="16" fill="#2dd48a" opacity=".93"/>
        <ellipse cx="80" cy="22" rx="17" ry="10" fill="#5af0b8" opacity=".87"/>
    </svg>';
    if ($s === 3) return '<svg width="185" height="250" viewBox="0 0 185 250">
        <defs><linearGradient id="st3" x1="0" y1="1" x2="0" y2="0"><stop offset="0%" stop-color="#071a0e"/><stop offset="40%" stop-color="#0a4428"/><stop offset="100%" stop-color="#2dd48a"/></linearGradient></defs>
        <ellipse cx="92" cy="238" rx="52" ry="12" fill="rgba(26,143,85,.12)"/>
        <ellipse cx="92" cy="230" rx="34" ry="14" fill="#c8e0c0"/>
        <path d="M92 234 C92 210 85 178 87 144 C89 112 87 80 89 52 C91 30 89 14 92 4" stroke="url(#st3)" stroke-width="10" stroke-linecap="round" fill="none"/>
        <path d="M92 182 C74 168 56 158 40 146" stroke="#0a4428" stroke-width="5" stroke-linecap="round" fill="none"/>
        <path d="M92 164 C110 150 128 140 144 128" stroke="#0a4428" stroke-width="5" stroke-linecap="round" fill="none"/>
        <path d="M92 130 C76 115 62 106 48 96" stroke="#1a7848" stroke-width="4" stroke-linecap="round" fill="none"/>
        <path d="M92 114 C108 99 122 90 136 80" stroke="#1a7848" stroke-width="4" stroke-linecap="round" fill="none"/>
        <ellipse cx="32" cy="138" rx="26" ry="13" fill="#1aaa6a" opacity=".88"/>
        <ellipse cx="152" cy="120" rx="26" ry="13" fill="#2dd48a" opacity=".84"/>
        <ellipse cx="40" cy="88" rx="26" ry="13" fill="#0d8a52" opacity=".88"/>
        <ellipse cx="144" cy="72" rx="26" ry="13" fill="#3deda0" opacity=".84"/>
        <ellipse cx="48" cy="48" rx="28" ry="15" transform="rotate(-12 48 48)" fill="#1aaa6a" opacity=".88"/>
        <ellipse cx="136" cy="36" rx="28" ry="15" transform="rotate(12 136 36)" fill="#2dd48a" opacity=".84"/>
        <ellipse cx="92" cy="28" rx="34" ry="20" fill="#2dd48a" opacity=".93"/>
        <ellipse cx="92" cy="14" rx="22" ry="12" fill="#5af0b8" opacity=".87"/>
    </svg>';
    if ($s === 4) return '<svg width="210" height="290" viewBox="0 0 210 290">
        <defs><linearGradient id="st4" x1="0" y1="1" x2="0" y2="0"><stop offset="0%" stop-color="#040d07"/><stop offset="35%" stop-color="#082d18"/><stop offset="100%" stop-color="#1a8855"/></linearGradient></defs>
        <ellipse cx="105" cy="278" rx="60" ry="14" fill="rgba(26,143,85,.12)"/>
        <ellipse cx="105" cy="268" rx="40" ry="16" fill="#c8e0c0"/>
        <path d="M105 272 C105 244 97 204 99 162 C101 124 99 88 101 58 C103 34 101 16 105 4" stroke="url(#st4)" stroke-width="13" stroke-linecap="round" fill="none"/>
        <path d="M105 212 C84 196 62 184 44 170" stroke="#082d18" stroke-width="6.5" stroke-linecap="round" fill="none"/>
        <path d="M105 192 C126 176 148 164 166 150" stroke="#082d18" stroke-width="6.5" stroke-linecap="round" fill="none"/>
        <path d="M105 154 C86 136 68 124 52 112" stroke="#0a4a28" stroke-width="5.5" stroke-linecap="round" fill="none"/>
        <path d="M105 136 C124 118 142 106 158 94" stroke="#0a4a28" stroke-width="5.5" stroke-linecap="round" fill="none"/>
        <path d="M105 104 C88 86 74 74 60 62" stroke="#1a7848" stroke-width="4.5" stroke-linecap="round" fill="none"/>
        <path d="M105 88 C122 70 136 58 150 46" stroke="#1a7848" stroke-width="4.5" stroke-linecap="round" fill="none"/>
        <ellipse cx="36" cy="162" rx="30" ry="16" fill="#1aaa6a" opacity=".88"/>
        <ellipse cx="174" cy="142" rx="30" ry="16" fill="#2dd48a" opacity=".84"/>
        <ellipse cx="44" cy="104" rx="30" ry="16" fill="#0d8a52" opacity=".88"/>
        <ellipse cx="166" cy="86" rx="30" ry="16" fill="#3deda0" opacity=".84"/>
        <ellipse cx="52" cy="54" rx="34" ry="20" fill="#1aaa6a" opacity=".88"/>
        <ellipse cx="158" cy="38" rx="34" ry="20" fill="#2dd48a" opacity=".84"/>
        <ellipse cx="105" cy="22" rx="40" ry="24" fill="#2dd48a" opacity=".94"/>
        <ellipse cx="105" cy="6" rx="26" ry="14" fill="#5af0b8" opacity=".88"/>
    </svg>';
    if ($s === 5) return '<svg width="220" height="310" viewBox="0 0 220 310">
        <defs><linearGradient id="st5" x1="0" y1="1" x2="0" y2="0"><stop offset="0%" stop-color="#020906"/><stop offset="30%" stop-color="#061f10"/><stop offset="100%" stop-color="#14623a"/></linearGradient></defs>
        <path d="M96 300 C78 308 58 314 38 310" stroke="#030a06" stroke-width="5" fill="none" stroke-linecap="round" opacity=".7"/>
        <path d="M124 300 C142 308 162 314 182 310" stroke="#030a06" stroke-width="5" fill="none" stroke-linecap="round" opacity=".7"/>
        <ellipse cx="110" cy="296" rx="66" ry="15" fill="rgba(26,143,85,.12)"/>
        <ellipse cx="110" cy="285" rx="44" ry="18" fill="#c8e0c0"/>
        <path d="M110 290 C110 258 101 210 103 162 C105 118 103 76 105 44 C107 20 105 6 110 2" stroke="url(#st5)" stroke-width="17" stroke-linecap="round" fill="none"/>
        <path d="M110 228 C86 210 60 196 38 180" stroke="#061f10" stroke-width="8" stroke-linecap="round" fill="none"/>
        <path d="M110 206 C134 188 160 174 182 158" stroke="#061f10" stroke-width="8" stroke-linecap="round" fill="none"/>
        <path d="M110 166 C88 146 66 132 46 118" stroke="#0a3820" stroke-width="7" stroke-linecap="round" fill="none"/>
        <path d="M110 148 C132 128 154 114 174 100" stroke="#0a3820" stroke-width="7" stroke-linecap="round" fill="none"/>
        <path d="M110 112 C90 92 74 78 58 64" stroke="#1a6840" stroke-width="5.5" stroke-linecap="round" fill="none"/>
        <path d="M110 96 C130 76 146 62 162 48" stroke="#1a6840" stroke-width="5.5" stroke-linecap="round" fill="none"/>
        <ellipse cx="28" cy="170" rx="34" ry="18" fill="#1aaa6a" opacity=".88"/>
        <ellipse cx="192" cy="148" rx="34" ry="18" fill="#2dd48a" opacity=".84"/>
        <ellipse cx="36" cy="108" rx="34" ry="18" fill="#0d8a52" opacity=".88"/>
        <ellipse cx="184" cy="90" rx="34" ry="18" fill="#3deda0" opacity=".84"/>
        <ellipse cx="48" cy="56" rx="38" ry="22" fill="#1aaa6a" opacity=".9"/>
        <ellipse cx="172" cy="40" rx="38" ry="22" fill="#2dd48a" opacity=".86"/>
        <ellipse cx="110" cy="20" rx="50" ry="30" fill="#2dd48a" opacity=".94"/>
        <ellipse cx="110" cy="4" rx="32" ry="18" fill="#5af0b8" opacity=".88"/>
    </svg>';
    if ($s === 6) return '<svg width="230" height="320" viewBox="0 0 230 320">
        <defs><linearGradient id="st6" x1="0" y1="1" x2="0" y2="0"><stop offset="0%" stop-color="#010604"/><stop offset="28%" stop-color="#04140a"/><stop offset="100%" stop-color="#0d5232"/></linearGradient></defs>
        <path d="M96 312 C74 320 50 326 28 322" stroke="#030a06" stroke-width="6" fill="none" stroke-linecap="round" opacity=".72"/>
        <path d="M134 312 C156 320 180 326 202 322" stroke="#030a06" stroke-width="6" fill="none" stroke-linecap="round" opacity=".72"/>
        <ellipse cx="115" cy="308" rx="74" ry="16" fill="rgba(26,143,85,.12)"/>
        <ellipse cx="115" cy="296" rx="50" ry="20" fill="#c8e0c0"/>
        <path d="M115 300 C115 264 105 208 107 154 C109 106 107 62 109 28 C111 8 109 -2 115 -4" stroke="url(#st6)" stroke-width="21" stroke-linecap="round" fill="none"/>
        <path d="M115 244 C88 224 60 208 36 190" stroke="#05180c" stroke-width="9" stroke-linecap="round" fill="none"/>
        <path d="M115 220 C142 200 170 184 194 166" stroke="#05180c" stroke-width="9" stroke-linecap="round" fill="none"/>
        <path d="M115 176 C90 154 66 138 44 122" stroke="#082d18" stroke-width="8" stroke-linecap="round" fill="none"/>
        <path d="M115 158 C140 136 164 120 186 104" stroke="#082d18" stroke-width="8" stroke-linecap="round" fill="none"/>
        <path d="M115 120 C92 98 72 82 54 66" stroke="#1a5c34" stroke-width="6" stroke-linecap="round" fill="none"/>
        <path d="M115 104 C138 82 158 66 176 50" stroke="#1a5c34" stroke-width="6" stroke-linecap="round" fill="none"/>
        <ellipse cx="22" cy="180" rx="38" ry="20" fill="#1a9a5a" opacity=".88"/>
        <ellipse cx="208" cy="156" rx="38" ry="20" fill="#0d7a42" opacity=".86"/>
        <ellipse cx="32" cy="112" rx="38" ry="20" fill="#1aaa6a" opacity=".88"/>
        <ellipse cx="198" cy="94" rx="38" ry="20" fill="#2dd48a" opacity=".84"/>
        <ellipse cx="44" cy="58" rx="44" ry="26" fill="#0d8a52" opacity=".9"/>
        <ellipse cx="186" cy="42" rx="44" ry="26" fill="#3deda0" opacity=".86"/>
        <ellipse cx="115" cy="16" rx="58" ry="36" fill="#2dd48a" opacity=".95"/>
        <ellipse cx="115" cy="-2" rx="36" ry="20" fill="#5af0b8" opacity=".88"/>
    </svg>';
    if ($s === 7) return '<svg width="240" height="340" viewBox="0 0 240 340">
        <defs><linearGradient id="st7" x1="0" y1="1" x2="0" y2="0"><stop offset="0%" stop-color="#010503"/><stop offset="25%" stop-color="#030e07"/><stop offset="100%" stop-color="#0f6535"/></linearGradient></defs>
        <path d="M100 330 C76 340 50 346 24 342" stroke="#020805" stroke-width="7" fill="none" stroke-linecap="round" opacity=".75"/>
        <path d="M140 330 C164 340 190 346 216 342" stroke="#020805" stroke-width="7" fill="none" stroke-linecap="round" opacity=".75"/>
        <ellipse cx="120" cy="326" rx="84" ry="18" fill="rgba(26,143,85,.12)"/>
        <ellipse cx="120" cy="312" rx="56" ry="22" fill="#c8e0c0"/>
        <path d="M120 318 C120 278 109 214 111 152 C113 96 111 50 113 18 C115 -4 113 -14 120 -16" stroke="url(#st7)" stroke-width="26" stroke-linecap="round" fill="none"/>
        <path d="M120 266 C90 244 58 226 30 206" stroke="#030e07" stroke-width="11" stroke-linecap="round" fill="none"/>
        <path d="M120 240 C150 218 182 200 210 180" stroke="#030e07" stroke-width="11" stroke-linecap="round" fill="none"/>
        <path d="M120 196 C92 172 66 154 42 136" stroke="#061a0e" stroke-width="10" stroke-linecap="round" fill="none"/>
        <path d="M120 174 C148 150 174 132 198 114" stroke="#061a0e" stroke-width="10" stroke-linecap="round" fill="none"/>
        <path d="M120 132 C94 108 72 90 52 72" stroke="#1a5c34" stroke-width="8" stroke-linecap="round" fill="none"/>
        <path d="M120 112 C146 88 168 70 188 52" stroke="#1a5c34" stroke-width="8" stroke-linecap="round" fill="none"/>
        <ellipse cx="16" cy="192" rx="42" ry="22" fill="#1aaa6a" opacity=".88"/>
        <ellipse cx="224" cy="166" rx="42" ry="22" fill="#2dd48a" opacity=".84"/>
        <ellipse cx="28" cy="124" rx="42" ry="22" fill="#0d8a52" opacity=".88"/>
        <ellipse cx="212" cy="104" rx="42" ry="22" fill="#3deda0" opacity=".84"/>
        <ellipse cx="40" cy="64" rx="50" ry="30" fill="#1aaa6a" opacity=".9"/>
        <ellipse cx="200" cy="44" rx="50" ry="30" fill="#2dd48a" opacity=".86"/>
        <ellipse cx="120" cy="12" rx="68" ry="42" fill="#2dd48a" opacity=".95"/>
        <ellipse cx="120" cy="-10" rx="44" ry="26" fill="#5af0b8" opacity=".9"/>
    </svg>';
    // Estágio 8 — Árvore Lendária (com animação)
    return '<svg width="240" height="340" viewBox="0 0 240 340">
        <defs>
          <linearGradient id="st8" x1="0" y1="1" x2="0" y2="0"><stop offset="0%" stop-color="#010402"/><stop offset="22%" stop-color="#020c05"/><stop offset="100%" stop-color="#0a4a28"/></linearGradient>
          <radialGradient id="aura8" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="#f0c060" stop-opacity=".1"/><stop offset="100%" stop-color="transparent"/></radialGradient>
        </defs>
        <ellipse cx="120" cy="170" rx="110" ry="150" fill="url(#aura8)"/>
        <ellipse cx="120" cy="328" rx="84" ry="18" fill="rgba(26,143,85,.12)"/>
        <ellipse cx="120" cy="314" rx="56" ry="22" fill="#c8e0c0"/>
        <path d="M120 320 C120 280 109 216 111 152 C113 94 111 46 113 14 C115 -8 113 -18 120 -20" stroke="url(#st8)" stroke-width="28" stroke-linecap="round" fill="none"/>
        <path d="M120 268 C90 246 58 228 30 208" stroke="#020c05" stroke-width="12" stroke-linecap="round" fill="none"/>
        <path d="M120 242 C150 220 182 202 210 182" stroke="#020c05" stroke-width="12" stroke-linecap="round" fill="none"/>
        <path d="M120 198 C92 174 66 156 42 138" stroke="#04160a" stroke-width="11" stroke-linecap="round" fill="none"/>
        <path d="M120 176 C148 152 174 134 198 116" stroke="#04160a" stroke-width="11" stroke-linecap="round" fill="none"/>
        <path d="M120 134 C94 110 72 92 52 74" stroke="#0f4a28" stroke-width="9" stroke-linecap="round" fill="none"/>
        <path d="M120 114 C146 90 168 72 188 54" stroke="#0f4a28" stroke-width="9" stroke-linecap="round" fill="none"/>
        <ellipse cx="16" cy="196" rx="44" ry="24" fill="#1a9a5a" opacity=".88"/>
        <ellipse cx="224" cy="170" rx="44" ry="24" fill="#0d7a42" opacity=".86"/>
        <ellipse cx="28" cy="126" rx="44" ry="24" fill="#1aaa6a" opacity=".88"/>
        <ellipse cx="212" cy="106" rx="44" ry="24" fill="#2dd48a" opacity=".84"/>
        <ellipse cx="40" cy="66" rx="52" ry="32" fill="#0d8a52" opacity=".9"/>
        <ellipse cx="200" cy="46" rx="52" ry="32" fill="#3deda0" opacity=".86"/>
        <ellipse cx="120" cy="16" rx="72" ry="46" fill="#2dd48a" opacity=".95"/>
        <ellipse cx="120" cy="-8" rx="48" ry="28" fill="#4af0b0" opacity=".9"/>
        <ellipse cx="120" cy="-20" rx="28" ry="16" fill="#90ffd8" opacity=".8"/>
        <ellipse cx="120" cy="16" rx="76" ry="50" fill="none" stroke="#f0c060" stroke-width="1.5" opacity=".35">
          <animate attributeName="opacity" values="0.35;0.65;0.35" dur="3s" repeatCount="indefinite"/>
        </ellipse>
        <path d="M120 -36 L122 -30 L129 -30 L123.5 -26 L126 -19 L120 -23 L114 -19 L116.5 -26 L111 -30 L118 -30 Z" fill="#f0c060" opacity=".9">
          <animateTransform attributeName="transform" type="rotate" from="0 120 -28" to="360 120 -28" dur="9s" repeatCount="indefinite"/>
        </path>
    </svg>';
}

$plantSVG = getPlantSVG($plant['stage'], $seedDead, $seedWarning);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Florescer — Painel</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700;9..144,900&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>

  <!-- Favicon básico -->
  <link rel="icon" href="/florescer/public/img/fav/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="/florescer/public/img/fav/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/florescer/public/img/fav/favicon-16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/florescer/public/img/fav/favicon-180.png">
  <link rel="manifest" href="/florescer/public/img/fav/site.webmanifest">
  <meta name="msapplication-TileImage" content="/florescer/public/img/fav/mstile-150x150.png">
  <meta name="msapplication-TileColor" content="#ffffff">
  <meta name="theme-color" content="#ffffff">
</head>
<body>
<style>
/* ═══════════════════════════════════════════
   DASHBOARD — Mobile First
═══════════════════════════════════════════ */

/* BASE MOBILE */
.dash-content {
  flex: 1;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
  min-width: 0;
  width: 100%;
  box-sizing: border-box;
}

.topbar {
  transition: background .3s, box-shadow .3s;
}
.topbar.scrolled {
  background: rgba(250,248,245,.97);
  box-shadow: 0 1px 8px rgba(0,0,0,.07);
}

/* BANNERS */
.seed-banner {
  display: flex;
  align-items: flex-start;
  gap: .75rem;
  padding: .85rem 1rem;
  border-radius: var(--r);
  animation: slideDown .3s var(--e) both;
  flex-wrap: nowrap;
}
@keyframes slideDown {
  from { opacity: 0; transform: translateY(-8px); }
  to   { opacity: 1; transform: none; }
}
.seed-banner.dead    { background: rgba(220,38,38,.06);  border: 1px solid rgba(220,38,38,.15); }
.seed-banner.warn    { background: rgba(201,168,76,.06); border: 1px solid rgba(201,168,76,.18); }
.seed-banner.penalty-new { background: rgba(239,68,68,.07); border: 1px solid rgba(239,68,68,.2); }

.sb-ico  { font-size: 1.5rem; flex-shrink: 0; }
.sb-text { flex: 1; min-width: 0; }
.sb-text strong {
  font-size: .83rem;
  font-weight: 700;
  color: var(--n800);
  display: block;
  margin-bottom: .12rem;
}
.sb-text p { font-size: .74rem; color: #999; line-height: 1.5; }
.sb-btn {
  padding: .42rem .85rem;
  border-radius: 50px;
  border: none;
  background: var(--g500);
  color: #fff;
  font-family: var(--fb);
  font-size: .74rem;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  white-space: nowrap;
  flex-shrink: 0;
  align-self: center;
  min-height: 36px;
  display: inline-flex;
  align-items: center;
  transition: all var(--d) var(--e);
  -webkit-tap-highlight-color: transparent;
}
.sb-btn:hover { background: var(--g400); transform: translateY(-1px); }
.seed-banner.dead .sb-btn       { background: #dc2626; }
.seed-banner.penalty-new .sb-btn { background: #ef4444; }

/* SAUDAÇÃO */
.greet-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: .5rem;
}
.greet-title {
  font-family: var(--fd);
  font-size: 1.25rem;
  font-weight: 900;
  color: var(--n800);
  letter-spacing: -.03em;
}
.greet-sub {
  font-size: .73rem;
  color: #bbb;
  margin-top: .1rem;
  text-transform: capitalize;
}
.goal-badge {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .32rem .85rem;
  border-radius: 50px;
  background: rgba(64,145,108,.1);
  border: 1px solid rgba(64,145,108,.2);
  font-size: .73rem;
  font-weight: 600;
  color: var(--g500);
  white-space: nowrap;
  flex-shrink: 0;
}

/* FRASE MOTIVACIONAL */
.motiv-card {
  background: linear-gradient(135deg, var(--g800), var(--g950));
  border: 1px solid rgba(116,198,157,.1);
  border-radius: var(--r);
  padding: .9rem 1.2rem;
  position: relative;
  overflow: hidden;
}
.motiv-card::before {
  content: '';
  position: absolute;
  top: -40px;
  right: -40px;
  width: 130px;
  height: 130px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(116,198,157,.07) 0%, transparent 70%);
  pointer-events: none;
}
.motiv-quote {
  font-family: var(--fd);
  font-size: .9rem;
  font-weight: 700;
  color: var(--g200);
  line-height: 1.55;
  font-style: italic;
}
.motiv-author {
  font-size: .69rem;
  color: rgba(116,198,157,.38);
  margin-top: .35rem;
}

/* GRID — mobile: coluna única */
.dash-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem;
  align-items: start;
}

/* WIDGET BASE */
.widget {
  background: var(--white);
  border: 1px solid rgba(0,0,0,.06);
  border-radius: var(--r);
  padding: 1rem;
  box-shadow: var(--sh0);
  transition: transform var(--d) var(--e), box-shadow var(--d) var(--e);
  min-width: 0;
}
.widget:hover { transform: translateY(-2px); box-shadow: var(--sh1); }
.wh {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: .85rem;
}
.wh-title { font-family: var(--fd); font-size: .87rem; font-weight: 700; color: var(--n800); }
.wh-sub   { font-size: .69rem; color: #bbb; }

/* ── WIDGET PLANTA ── */
.widget-plant { overflow: hidden; position: relative; padding-bottom: 1rem; }
.widget-plant.dead { border-color: rgba(220,38,38,.2);  background: rgba(220,38,38,.015); }
.widget-plant.warn { border-color: rgba(201,168,76,.25); background: rgba(201,168,76,.015); }

.plant-svg-wrap {
  display: flex;
  justify-content: center;
  align-items: flex-end;
  overflow: hidden;
  min-height: 160px;
  max-height: 280px;
  height: auto;
  position: relative;
  margin: -.2rem -.2rem .4rem;
  padding-top: .8rem;
}
.plant-svg-wrap svg {
  max-width: 100%;
  transform-origin: bottom center;
  animation: plantFloat 5s ease-in-out infinite;
  filter: drop-shadow(0 8px 24px rgba(26,143,85,.2));
}
.widget-plant.dead .plant-svg-wrap svg {
  animation: none;
  filter: grayscale(.65) drop-shadow(0 4px 10px rgba(0,0,0,.1));
}
.widget-plant.warn .plant-svg-wrap svg {
  filter: saturate(.5) drop-shadow(0 6px 14px rgba(201,168,76,.15));
}
@keyframes plantFloat {
  0%,100% { transform: translateY(0) scaleX(1); }
  50%      { transform: translateY(-6px) scaleX(1.012); }
}
.plant-glow {
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 160px;
  height: 160px;
  border-radius: 50%;
  pointer-events: none;
  background: radial-gradient(circle, rgba(26,143,85,.1) 0%, transparent 70%);
  animation: glowPulse 4s ease-in-out infinite;
}
.widget-plant.dead .plant-glow { background: radial-gradient(circle, rgba(220,38,38,.07) 0%, transparent 70%); }
.widget-plant.warn .plant-glow { background: radial-gradient(circle, rgba(201,168,76,.1) 0%, transparent 70%); }
@keyframes glowPulse {
  0%,100% { opacity: .55; transform: translateX(-50%) scale(1); }
  50%      { opacity: 1;   transform: translateX(-50%) scale(1.18); }
}
.plant-info { padding: 0 .2rem; text-align: center; }
.plant-name-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .4rem;
  margin-bottom: .4rem;
  flex-wrap: wrap;
}
.plant-name { font-family: var(--fd); font-size: .92rem; font-weight: 700; color: var(--n800); }
.plant-status-pill {
  font-size: .62rem;
  padding: .14rem .46rem;
  border-radius: 20px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: .2rem;
}
.plant-status-pill.ok   { background: rgba(64,145,108,.1);  color: var(--g500); }
.plant-status-pill.warn { background: rgba(201,168,76,.12); color: #92720c; }
.plant-status-pill.dead { background: rgba(220,38,38,.1);   color: #dc2626; }

.drops-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .45rem;
  margin-bottom: .55rem;
}
.drops-label { font-size: .64rem; color: #bbb; font-weight: 500; }
.drop-wrap   { display: flex; gap: .25rem; }
.drop-item   { position: relative; }
.drop-svg    { width: 18px; height: 22px; display: block; }
.drop-item.full .drop-svg path { filter: drop-shadow(0 1px 3px rgba(56,189,248,.4)); }
.drop-item.empty { opacity: .35; }

.plant-stage-bar {
  height: 5px;
  background: rgba(0,0,0,.06);
  border-radius: 3px;
  overflow: hidden;
  margin-bottom: .26rem;
}
.plant-stage-fill {
  height: 100%;
  border-radius: 3px;
  background: linear-gradient(90deg, var(--g500), var(--g300));
  transition: width .8s var(--e);
}
.plant-stage-labels {
  display: flex;
  justify-content: space-between;
  font-size: .61rem;
  color: #ccc;
  margin-bottom: .42rem;
}
.plant-next-tag {
  display: inline-block;
  font-size: .67rem;
  color: #aaa;
  padding: .26rem .62rem;
  background: rgba(0,0,0,.03);
  border-radius: 20px;
}
.plant-next-tag span { color: var(--g500); font-weight: 600; }

/* ALERTAS DE TENTATIVAS */
.chances-alert {
  display: flex;
  align-items: flex-start;
  gap: .55rem;
  padding: .5rem .7rem;
  border-radius: 8px;
  margin-top: .6rem;
  font-size: .72rem;
  font-weight: 500;
  line-height: 1.4;
}
.chances-alert.c2 { background: rgba(251,191,36,.08); border: 1px solid rgba(251,191,36,.22); color: #92720c; }
.chances-alert.c1 { background: rgba(239,68,68,.07);  border: 1px solid rgba(239,68,68,.2);  color: #b91c1c; }
.ca-icon { font-size: .95rem; flex-shrink: 0; margin-top: .05rem; }
.ca-text strong { display: block; font-weight: 700; margin-bottom: .05rem; }

/* ── WIDGET META ── */
.widget-meta { display: flex; flex-direction: column; gap: 0; }
.meta-circle-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: .5rem 0 .7rem;
  position: relative;
}
.meta-ring { width: 110px; height: 90px; transform: rotate(-90deg); flex-shrink: 0; }
.meta-ring-track { fill: none; stroke: rgba(64,145,108,.1); stroke-width: 8; }
.meta-ring-fill {
  fill: none;
  stroke: var(--g500);
  stroke-width: 8;
  stroke-linecap: round;
  stroke-dasharray: 289;
  transition: stroke-dashoffset 1s var(--e);
}
.meta-ring-fill.done { stroke: var(--g400); }
.meta-ring-inner {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  pointer-events: none;
}
.meta-pct-num {
  font-family: var(--fd);
  font-size: 1.55rem;
  font-weight: 900;
  color: var(--n800);
  line-height: 1;
  display: block;
}
.meta-pct-num.done { color: var(--g500); }
.meta-pct-unit    { font-size: .6rem; color: #bbb; display: block; margin-top: .1rem; }

.meta-time-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .28rem;
  margin-top: .45rem;
  font-size: .77rem;
  color: #aaa;
}
.meta-time-done { font-weight: 700; color: var(--n800); }
.meta-time-sep  { color: #ddd; }
.meta-time-goal { color: #bbb; }

.meta-hint {
  text-align: center;
  font-size: .74rem;
  color: #bbb;
  line-height: 1.5;
  margin-bottom: .75rem;
  padding: 0 .3rem;
}
.meta-hint.done { color: var(--g500); font-weight: 500; }

.meta-cta {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: .6rem 1rem;
  border-radius: 50px;
  text-decoration: none;
  background: linear-gradient(135deg, var(--g500), var(--g600));
  color: #fff;
  font-size: .78rem;
  font-weight: 600;
  min-height: 44px;
  transition: all var(--d) var(--e);
  box-shadow: 0 3px 10px rgba(64,145,108,.22);
  -webkit-tap-highlight-color: transparent;
}
.meta-cta:hover { transform: translateY(-1px); box-shadow: 0 5px 16px rgba(64,145,108,.32); }

.streak-row {
  display: flex;
  align-items: center;
  gap: .5rem;
  margin-top: .7rem;
  padding-top: .7rem;
  border-top: 1px solid rgba(0,0,0,.05);
}
.streak-fire { font-size: 1.1rem; animation: flick 1.8s ease-in-out infinite; flex-shrink: 0; }
@keyframes flick {
  0%,100% { transform: scale(1) rotate(-2deg); }
  50%      { transform: scale(1.12) rotate(2deg); }
}
.streak-info   { flex: 1; }
.streak-num    { font-family: var(--fd); font-size: 1.05rem; font-weight: 900; color: #e08020; }
.streak-txt    { font-size: .67rem; color: #bbb; display: block; margin-top: .02rem; }
.streak-drops  { display: flex; gap: .13rem; align-items: center; }
.streak-drop   { font-size: .8rem; }

/* ── WIDGET SEMANA ── */
.week-bars {
  display: flex;
  align-items: flex-end;
  height: 90px;
  gap: 3px;
  padding: .1rem 0 .2rem;
}
.week-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-end;
  gap: 3px;
  height: 100%;
}
.week-bar {
  width: 100%;
  border-radius: 4px 4px 2px 2px;
  background: rgba(64,145,108,.14);
  min-height: 4px;
  transition: background .3s, height .5s var(--e);
  position: relative;
}
.week-bar.today  { background: var(--g500); }
.week-bar.goal   { background: linear-gradient(180deg, var(--g300), var(--g500)); }
.week-bar.future { background: rgba(0,0,0,.04); }
.week-bar:hover::after {
  content: attr(title);
  position: absolute;
  bottom: calc(100% + 4px);
  left: 50%;
  transform: translateX(-50%);
  background: var(--n800);
  color: #fff;
  font-size: .6rem;
  padding: .2rem .4rem;
  border-radius: 4px;
  white-space: nowrap;
  pointer-events: none;
  z-index: 10;
}
.week-lbl { font-size: .58rem; color: rgba(0,0,0,.28); font-weight: 500; }
.week-lbl.today { color: var(--g500); font-weight: 700; }

.week-legend {
  display: flex;
  gap: .65rem;
  margin-top: .6rem;
  flex-wrap: wrap;
}
.wl-item { display: flex; align-items: center; gap: .25rem; font-size: .61rem; color: #bbb; }
.wl-dot  { width: 8px; height: 8px; border-radius: 2px; flex-shrink: 0; }

.week-totals {
  margin-top: .65rem;
  padding-top: .65rem;
  border-top: 1px solid rgba(0,0,0,.05);
  display: flex;
  gap: 1rem;
}
.wt-item { flex: 1; text-align: center; }
.wt-val  { font-family: var(--fd); font-size: 1rem; font-weight: 700; color: var(--n800); display: block; line-height: 1; }
.wt-lbl  { font-size: .6rem; color: #bbb; margin-top: .15rem; display: block; }

/* ── MATÉRIAS ── */
.subjects-section { margin-top: .1rem; }
.sec-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: .75rem;
  gap: .5rem;
}
.sec-title { font-family: var(--fd); font-size: .92rem; font-weight: 700; color: var(--n800); }
.sec-link  { font-size: .73rem; color: var(--g500); text-decoration: none; white-space: nowrap; transition: color var(--d) var(--e); }
.sec-link:hover { color: var(--g600); }

.subjects-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: .6rem;
}
.subj-card {
  text-decoration: none;
  background: var(--white);
  border: 1px solid rgba(0,0,0,.06);
  border-radius: var(--rs);
  overflow: hidden;
  box-shadow: var(--sh0);
  transition: transform var(--d) var(--e), box-shadow var(--d) var(--e);
  -webkit-tap-highlight-color: transparent;
}
.subj-card:hover { transform: translateY(-2px); box-shadow: var(--sh1); }
.subj-top {
  padding: .6rem .8rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .3rem;
}
.subj-name {
  font-size: .77rem;
  font-weight: 600;
  color: var(--n800);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.subj-pct  { font-size: .7rem; font-weight: 700; flex-shrink: 0; }
.subj-bar  { height: 3px; background: rgba(0,0,0,.06); }
.subj-bar-fill { height: 100%; transition: width .6s var(--e); }
.subj-meta { font-size: .65rem; color: #bbb; padding: .3rem .8rem; }

.empty-obj {
  text-align: center;
  padding: 2rem 1.5rem;
  background: var(--white);
  border: 1px solid rgba(0,0,0,.06);
  border-radius: var(--r);
  box-shadow: var(--sh0);
}
.empty-obj-ico { font-size: 2rem; margin-bottom: .4rem; opacity: .32; }
.empty-obj p   { font-size: .8rem; color: #aaa; }
.empty-obj a   { color: var(--g500); text-decoration: none; }

/* BANNER GOTA RECUPERADA */
.drop-recovered-banner {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: .75rem 1rem;
  border-radius: var(--r);
  background: rgba(56,189,248,.06);
  border: 1px solid rgba(56,189,248,.2);
  animation: slideDown .3s var(--e) both;
}
.drop-recovered-banner .sb-ico  { font-size: 1.4rem; }
.drop-recovered-banner .sb-text strong { color: #0369a1; }

/* ─────────────────────────────────────
   TABLET ≥ 640px
───────────────────────────────────── */
@media (min-width: 640px) {
  .dash-content {
    padding: 1.2rem 1.5rem;
    gap: 1.1rem;
  }

  .greet-title { font-size: 1.4rem; }

  .dash-grid {
    grid-template-columns: 1fr 1fr;
  }

  /* planta ocupa a largura total nas 2 colunas */
  .widget-plant {
    grid-column: 1 / -1;
  }

  .plant-svg-wrap {
    min-height: 200px;
    max-height: 320px;
  }

  .subjects-grid {
    grid-template-columns: repeat(3, 1fr);
  }

  .seed-banner {
    align-items: center;
  }
}

/* ─────────────────────────────────────
   TABLET LANDSCAPE ≥ 900px
───────────────────────────────────── */
@media (min-width: 900px) {
  .dash-content {
    padding: 1.4rem 1.8rem;
    gap: 1.15rem;
  }

  .greet-title { font-size: 1.5rem; }

  .dash-grid {
    grid-template-columns: 1.1fr 1fr 1fr;
    align-items: start;
  }

  /* planta volta a 1 coluna */
  .widget-plant {
    grid-column: auto;
    grid-row: 1 / 3;
  }

  .plant-svg-wrap {
    min-height: 220px;
    max-height: 340px;
  }

  .subjects-grid {
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  }
}

/* ─────────────────────────────────────
   DESKTOP ≥ 1200px
───────────────────────────────────── */
@media (min-width: 1200px) {
  .dash-content {
    padding: 1.6rem 2rem;
    gap: 1.2rem;
  }

  .greet-title { font-size: 1.5rem; }

  .plant-svg-wrap {
    min-height: 260px;
    max-height: 380px;
  }
}
</style>

<?php
$lvName = $lvNameSidebar;
include __DIR__ . '/sidebar.php';
?>

<div class="main">
  <header class="topbar" id="topbar">
    <div class="tb-left">
      <button class="hamburger" id="hamburger" onclick="toggleSidebar()">
        <span></span><span></span><span></span>
      </button>
      <span class="tb-title">🌱 Painel</span>
    </div>
    <div class="xp-pill">⭐ <?= number_format($xp) ?> XP</div>
  </header>

  <main class="dash-content">

    <!-- ══ BANNERS DE ESTADO ══════════════════════════════════════════ -->

    <?php if ($seedDead): ?>
    <!-- CORREÇÃO #3: banner de morte persiste via last_death_date, não
         apenas na visita em que o streak zerou -->
    <div class="seed-banner dead" id="seedBanner">
      <div class="sb-ico">🥀</div>
      <div class="sb-text">
        <strong>Sua semente morreu...</strong>
        <p>Suas 3 gotas acabaram sem atingir a meta de <?= $goalMin ?>min. Uma nova semente começa hoje — cada grande árvore começou pequena 🌱</p>
      </div>
      <button class="sb-btn" onclick="closeBanner()">Entendido</button>
    </div>

    <?php elseif ($penaltyToday && $seedWarning): ?>
    <!-- CORREÇÃO #5: $penaltyToday agora distingue "penalidade aplicada
         AGORA" de "estado de warning já existente de dias anteriores" -->
    <div class="seed-banner penalty-new" id="seedBanner">
      <div class="sb-ico">🚨</div>
      <div class="sb-text">
        <strong>Você perdeu uma gota agora!</strong>
        <p>Não cumpriu a meta ontem. Restam <strong><?= $waterLeft ?> de 3 gota<?= $waterLeft !== 1 ? 's' : '' ?></strong> — estude hoje para salvar sua planta!</p>
      </div>
      <a class="sb-btn" href="/florescer/public/views/materials.php">Regar agora 💧</a>
    </div>

    <?php elseif ($seedWarning): ?>
    <!-- Estado de warning já existente (penalidade de dias anteriores) -->
    <div class="seed-banner warn" id="seedBanner">
      <div class="sb-ico">🌵</div>
      <div class="sb-text">
        <strong>Sua planta está murchando!</strong>
        <p>Restam <strong><?= $waterLeft ?> de 3 gota<?= $waterLeft !== 1 ? 's' : '' ?></strong> — estude hoje para salvar sua planta!</p>
      </div>
      <a class="sb-btn" href="/florescer/public/views/materials.php">Regar agora 💧</a>
    </div>

    <?php elseif ($goalReached && $waterLeft === 3 && !$seedDead): ?>
    <!-- CORREÇÃO #5: feedback positivo ao recuperar gotas -->
    <?php
      // Mostra banner de recuperação apenas se havia warning antes de hoje
      $hadWarning = dbRow(
          'SELECT last_penalty_date FROM users WHERE id=?', [$userId]
      );
      $wasInWarning = $hadWarning && $hadWarning['last_penalty_date'] === $today;
    ?>
    <?php if ($wasInWarning): ?>
    <div class="drop-recovered-banner" id="seedBanner">
      <div class="sb-ico">💧</div>
      <div class="sb-text">
        <strong>Gotas restauradas! 3/3 💙</strong>
        <p>Você cumpriu a meta hoje e sua planta está saudável novamente!</p>
      </div>
      <button class="sb-btn" style="background:#0ea5e9" onclick="closeBanner()">Ótimo!</button>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- SAUDAÇÃO -->
    <div class="greet-row">
      <div>
        <div class="greet-title"><?= $greeting ?>, <?= $userName ?> 👋</div>
        <div class="greet-sub"><?= date('l, d \d\e F', strtotime($today)) ?></div>
      </div>
      <?php if ($goalReached): ?>
        <div class="goal-badge">🎯 Meta atingida hoje!</div>
      <?php endif; ?>
    </div>

    <!-- FRASE MOTIVACIONAL -->
    <?php if ($todayMotiv): ?>
    <div class="motiv-card">
      <div class="motiv-quote">"<?= htmlspecialchars($todayMotiv[$mTxtCol] ?? '', ENT_QUOTES) ?>"</div>
      <?php if ($mAuthCol && !empty($todayMotiv[$mAuthCol])): ?>
        <div class="motiv-author">— <?= htmlspecialchars($todayMotiv[$mAuthCol], ENT_QUOTES) ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- GRID PRINCIPAL -->
    <div class="dash-grid">

      <!-- ══ WIDGET PLANTA ══ -->
      <div class="widget widget-plant <?= $seedDead ? 'dead' : ($seedWarning ? 'warn' : '') ?>">
        <div class="wh">
          <span class="wh-title">🌱 Minha planta</span>
          <span class="wh-sub"><?= htmlspecialchars($plant['name'], ENT_QUOTES) ?></span>
        </div>

        <div class="plant-svg-wrap">
          <div class="plant-glow"></div>
          <?= $plantSVG ?>
        </div>

        <div class="plant-info">
          <div class="plant-name-row">
            <span class="plant-name"><?= htmlspecialchars($plant['name'], ENT_QUOTES) ?></span>
            <?php if ($seedDead): ?>
              <span class="plant-status-pill dead">💀 Morta</span>
            <?php elseif ($seedWarning): ?>
              <span class="plant-status-pill warn">⚠️ Murchando</span>
            <?php else: ?>
              <span class="plant-status-pill ok">🌱 <?= $streak ?> dia<?= $streak !== 1 ? 's' : '' ?></span>
            <?php endif; ?>
          </div>

          <!-- Gotas -->
          <div class="drops-row">
            <span class="drops-label">
              <?= $goalReached ? '💧 Regada hoje!' : ($dropsDisplay < 3 ? 'Gotas restantes:' : 'Gotas:') ?>
            </span>
            <div class="drop-wrap">
              <?php for ($d = 0; $d < 3; $d++): $isFull = ($d < $dropsDisplay); ?>
              <div class="drop-item <?= $isFull ? 'full' : 'empty' ?>">
                <svg class="drop-svg" viewBox="0 0 18 22" fill="none">
                  <path d="M9 2C9 2 2 10 2 14.5a7 7 0 0014 0C16 10 9 2 9 2z"
                    fill="<?= $isFull ? '#38bdf8' : 'rgba(0,0,0,.08)' ?>"
                    stroke="<?= $isFull ? '#22a8e0' : 'rgba(0,0,0,.12)' ?>"
                    stroke-width="1.2"/>
                  <?php if ($isFull): ?>
                  <path d="M6.5 15 Q9 12 11.5 15" stroke="rgba(255,255,255,.4)" stroke-width=".8" fill="none" stroke-linecap="round"/>
                  <?php endif; ?>
                </svg>
              </div>
              <?php endfor; ?>
            </div>
          </div>

          <!-- Alertas contextuais de tentativas -->
          <?php if (!$goalReached && $dropsDisplay === 2): ?>
          <div class="chances-alert c2">
            <span class="ca-icon">⚠️</span>
            <div class="ca-text">
              <strong>1 gota perdida — 2/3 restantes</strong>
              Estude <?= max(0, $goalMin - $studiedToday) ?>min hoje para evitar mais perdas!
            </div>
          </div>
          <?php elseif (!$goalReached && $dropsDisplay === 1): ?>
          <div class="chances-alert c1">
            <span class="ca-icon">🚨</span>
            <div class="ca-text">
              <strong>Última chance! 1/3 gota restante</strong>
              Se não estudar hoje, sua planta morre e o streak zera.
            </div>
          </div>
          <?php endif; ?>

          <!-- Barra de estágio -->
          <div class="plant-stage-bar">
            <div class="plant-stage-fill" style="width:<?= $plant['pct'] ?>%"></div>
          </div>
          <div class="plant-stage-labels">
            <span><?= htmlspecialchars($plant['name'], ENT_QUOTES) ?></span>
            <span><?= $plant['pct'] ?>%</span>
          </div>

          <?php if ($plant['stage'] < 8): ?>
            <span class="plant-next-tag">
              Próximo: <span><?= htmlspecialchars($plant['next'], ENT_QUOTES) ?></span>
              em <?= $plant['daysToNext'] ?> dia<?= $plant['daysToNext'] !== 1 ? 's' : '' ?>
            </span>
          <?php else: ?>
            <span class="plant-next-tag" style="color:var(--gold,#c8882a);background:rgba(200,136,42,.08)">
              ✨ Nível máximo!
            </span>
          <?php endif; ?>
        </div>
      </div><!-- /widget-plant -->

      <!-- ══ WIDGET META DE HOJE ══ -->
      <div class="widget widget-meta">
        <div class="wh">
          <span class="wh-title">🎯 Meta de hoje</span>
          <span class="wh-sub"><?= $goalMin ?>min</span>
        </div>

        <div class="meta-circle-wrap">
          <svg class="meta-ring" viewBox="0 0 110 110">
            <circle class="meta-ring-track" cx="55" cy="55" r="46"/>
            <circle class="meta-ring-fill <?= $goalReached ? 'done' : '' ?>"
                    cx="55" cy="55" r="46"
                    style="stroke-dashoffset:<?= round(289 * (1 - $goalPct / 100)) ?>"/>
          </svg>
          <div class="meta-ring-inner">
            <span class="meta-pct-num <?= $goalReached ? 'done' : '' ?>"><?= $goalPct ?>%</span>
            <span class="meta-pct-unit">da meta</span>
          </div>
        </div>

        <div class="meta-time-row">
          <span class="meta-time-done"><?= $studiedToday ?>min</span>
          <span class="meta-time-sep">/</span>
          <span class="meta-time-goal"><?= $goalMin ?>min</span>
        </div>

        <div class="meta-hint <?= $goalReached ? 'done' : '' ?>" style="margin-top:.55rem">
          <?php if ($goalReached): ?>
            ✅ Meta concluída! Sua planta foi regada hoje!
          <?php else: ?>
            Faltam <strong><?= max(0, $goalMin - $studiedToday) ?>min</strong> para regar sua planta 💪
          <?php endif; ?>
        </div>

        <a class="meta-cta" href="/florescer/public/views/materials.php">
          <?= $goalReached ? '📚 Continuar estudando' : '▶ Estudar agora' ?>
        </a>

        <div class="streak-row">
          <span class="streak-fire">🌱</span>
          <div class="streak-info">
            <span class="streak-num"><?= $streak ?></span>
            <span class="streak-txt">dia<?= $streak !== 1 ? 's' : '' ?> de streak</span>
          </div>
          <div class="streak-drops">
            <?php for ($d = 0; $d < 3; $d++): ?>
              <span class="streak-drop"><?= ($d < $dropsDisplay) ? '💧' : '🩶' ?></span>
            <?php endfor; ?>
          </div>
        </div>
      </div><!-- /widget-meta -->

      <!-- ══ WIDGET ESTA SEMANA ══ -->
      <div class="widget widget-week">
        <div class="wh">
          <span class="wh-title">📈 Esta semana</span>
          <span class="wh-sub">últimos 7 dias</span>
        </div>

        <div class="week-bars">
          <?php
          $DOWS   = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
          $wTotal = 0;
          $wGoals = 0;
          for ($i = 6; $i >= 0; $i--):
            $d        = date('Y-m-d', strtotime("-{$i} days"));
            $min      = (int)($weekMap[$d]['total_min']    ?? 0);
            $goal     = (bool)($weekMap[$d]['goal_reached'] ?? false);
            $isToday  = ($d === $today);
            $isFuture = ($d > $today);
            $pxH      = ($weekMaxMin > 0 && !$isFuture)
                        ? max(4, round($min / $weekMaxMin * 88)) : 4;
            $dow      = $DOWS[(int)date('w', strtotime($d))];
            if (!$isFuture) { $wTotal += $min; if ($goal) $wGoals++; }
          ?>
          <div class="week-col">
            <div class="week-bar
                  <?= $isToday  ? 'today'  : '' ?>
                  <?= $isFuture ? 'future' : '' ?>
                  <?= (!$isFuture && $goal && !$isToday) ? 'goal' : '' ?>"
                 style="height:<?= $pxH ?>px"
                 title="<?= date('d/m', strtotime($d)) ?> · <?= $min ?>min<?= $goal ? ' ✓' : '' ?>">
            </div>
            <div class="week-lbl <?= $isToday ? 'today' : '' ?>"><?= $dow ?></div>
          </div>
          <?php endfor; ?>
        </div>

        <div class="week-legend">
          <div class="wl-item"><div class="wl-dot" style="background:var(--g500)"></div>Hoje</div>
          <div class="wl-item"><div class="wl-dot" style="background:linear-gradient(var(--g300),var(--g500))"></div>Meta batida</div>
          <div class="wl-item"><div class="wl-dot" style="background:rgba(64,145,108,.14)"></div>Estudou</div>
        </div>

        <div class="week-totals">
          <div class="wt-item">
            <span class="wt-val"><?= $wTotal >= 60
              ? floor($wTotal/60).'h'.($wTotal%60 ? str_pad($wTotal%60,2,'0',STR_PAD_LEFT).'m' : '')
              : $wTotal.'min' ?></span>
            <span class="wt-lbl">Total estudado</span>
          </div>
          <div class="wt-item">
            <span class="wt-val"><?= $wGoals ?>/7</span>
            <span class="wt-lbl">Metas batidas</span>
          </div>
        </div>
      </div><!-- /widget-week -->

    </div><!-- /dash-grid -->

    <!-- MATÉRIAS -->
    <?php if (!empty($subjects)): ?>
    <div class="subjects-section">
      <div class="sec-head">
        <span class="sec-title">📚 Matérias<?= $activeObj ? ' — '.htmlspecialchars($activeObj['name'], ENT_QUOTES) : '' ?></span>
        <a class="sec-link" href="/florescer/public/views/materials.php">Ver todas →</a>
      </div>
      <div class="subjects-grid">
        <?php foreach ($subjects as $s):
          $pct = (int)$s['total'] > 0 ? round((int)$s['done'] / (int)$s['total'] * 100) : 0;
          $col = $s['color'] ?: '#40916c';
        ?>
        <a class="subj-card" href="/florescer/public/views/materials.php">
          <div class="subj-top" style="background:<?= $col ?>14;border-bottom:2px solid <?= $col ?>">
            <span class="subj-name"><?= htmlspecialchars($s['name'], ENT_QUOTES) ?></span>
            <span class="subj-pct" style="color:<?= $col ?>"><?= $pct ?>%</span>
          </div>
          <div class="subj-bar">
            <div class="subj-bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div>
          </div>
          <div class="subj-meta"><?= (int)$s['done'] ?>/<?= (int)$s['total'] ?> aulas</div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php elseif (!$activeObj): ?>
    <div class="empty-obj">
      <div class="empty-obj-ico">🎯</div>
      <p>Nenhum objetivo ativo. <a href="/florescer/public/views/objectives.php">Criar objetivo →</a></p>
    </div>
    <?php endif; ?>

  </main>
</div><!-- /main -->

<script>
(function(){
  /* topbar scroll */
  const tb = document.getElementById('topbar');
  if (tb) {
    window.addEventListener('scroll', () => {
      tb.classList.toggle('scrolled', window.scrollY > 10);
    }, { passive: true });
  }

  /* fit planta ao container */
  function fitPlant() {
    const wrap = document.querySelector('.plant-svg-wrap');
    const svg  = wrap && wrap.querySelector('svg');
    if (!svg) return;
    const vb = svg.viewBox.baseVal;
    if (!vb || !vb.height) return;
    const maxScale = window.innerWidth < 640 ? 1.1 : 1.4;
    const scale = Math.min(
      (wrap.clientHeight || 200) / vb.height,
      (wrap.clientWidth  || 280) / vb.width,
      maxScale
    );
    svg.style.transform       = `scale(${scale.toFixed(3)})`;
    svg.style.transformOrigin = 'bottom center';
  }

  function sizeWrap() {
    const wrap = document.querySelector('.plant-svg-wrap');
    const svg  = wrap && wrap.querySelector('svg');
    if (!svg || !wrap) return;
    const vb = svg.viewBox.baseVal;
    if (!vb || !vb.height) return;
    const isMobile = window.innerWidth < 640;
    const minH = isMobile ? 160 : 220;
    const maxH = isMobile ? 260 : 360;
    wrap.style.minHeight = Math.min(Math.max(vb.height * .85, minH), maxH) + 'px';
    fitPlant();
  }

  sizeWrap();
  window.addEventListener('resize', sizeWrap, { passive: true });
})();

function closeBanner() {
  const b = document.getElementById('seedBanner');
  if (b) {
    b.style.opacity    = '0';
    b.style.transition = 'opacity .3s';
    setTimeout(() => b.remove(), 320);
  }
}
</script>
</body>
</html>