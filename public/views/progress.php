<?php
// ============================================================
// /public/views/progress.php — florescer v3.0
// XP níveis 1–50 · conquistas em 2 fases · sistema XP justo
// ============================================================
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

startSession();
authGuard();

$user        = currentUser();
$userId      = (int)$user['id'];
$userName    = htmlspecialchars($user['name'] ?? 'Estudante', ENT_QUOTES, 'UTF-8');
$userInitial = strtoupper(mb_substr($user['name'] ?? 'E', 0, 1, 'UTF-8'));
$currentPage = 'progress';

// ── Dados do usuário ─────────────────────────────────────────
$u      = dbRow('SELECT xp, level, streak, streak_max, daily_goal_min FROM users WHERE id=?', [$userId]);
$xp     = (int)($u['xp']             ?? 0);
$level  = max(1, (int)($u['level']   ?? 1));
$streak = (int)($u['streak']         ?? 0);
// streak_max: usa o maior entre streak_max e streak atual
$maxStrk = max((int)($u['streak_max'] ?? 0), $streak);

// ── Stats ─────────────────────────────────────────────────────
$totalLessons = (int)(dbRow(
    'SELECT COUNT(*) AS n FROM lessons l
     JOIN topics t ON t.id=l.topic_id
     JOIN subjects s ON s.id=t.subject_id
     WHERE s.user_id=? AND l.is_completed=1',
    [$userId]
)['n'] ?? 0);

$totalMin = (int)(dbRow(
    'SELECT COALESCE(SUM(total_min),0) AS n FROM daily_summaries WHERE user_id=?',
    [$userId]
)['n'] ?? 0);

$goalsHit = (int)(dbRow(
    'SELECT COUNT(*) AS n FROM daily_summaries WHERE user_id=? AND goal_reached=1',
    [$userId]
)['n'] ?? 0);

$totalDays = (int)(dbRow(
    'SELECT COUNT(*) AS n FROM daily_summaries WHERE user_id=? AND total_min > 0',
    [$userId]
)['n'] ?? 0);

$totalObjectives = (int)(dbRow(
    'SELECT COUNT(*) AS n FROM objectives WHERE user_id=?',
    [$userId]
)['n'] ?? 0);

// Horas com 1 casa decimal para exibição, inteiro para lógica de conquistas
$totalHoursFloat = $totalMin > 0 ? round($totalMin / 60, 1) : 0;
$totalHoursInt   = (int)floor($totalMin / 60); // usado nas comparações das conquistas

// Dados da semana atual
$weekMin = (int)(dbRow(
    'SELECT COALESCE(SUM(total_min),0) AS n FROM daily_summaries
     WHERE user_id=? AND study_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)',
    [$userId]
)['n'] ?? 0);

$weekDays = (int)(dbRow(
    'SELECT COUNT(*) AS n FROM daily_summaries
     WHERE user_id=? AND total_min > 0 AND study_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)',
    [$userId]
)['n'] ?? 0);

$weekGoalsHit = (int)(dbRow(
    'SELECT COUNT(*) AS n FROM daily_summaries
     WHERE user_id=? AND goal_reached=1 AND study_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)',
    [$userId]
)['n'] ?? 0);

// ── Tabela XP por nível (justo, não trivial) ──────────────────
// Filosofia: nível 1→5 acessível (primeiros dias), 6→15 requer semanas,
// 16→30 requer meses, 31→50 requer dedicação de longo prazo.
//
// Fórmula para nível N (XP acumulado necessário para ESTAR no nível N):
//   Níveis 1-10: crescimento quadrático moderado
//   Níveis 11-30: crescimento cúbico
//   Níveis 31-50: crescimento exponencial pesado
//
// XP por ação (definido na API):
//   +5 XP por minuto de estudo (máx 200 XP/dia via tempo)
//   +20 XP por aula concluída
//   +50 XP ao bater a meta diária
//   +3 XP bônus por dia de streak (acumula)
//   Bônus de streak: 5× a cada 7 dias consecutivos (+35 XP extra)

function xpForLevel(int $lv): int {
    if ($lv <= 1) return 0;

    // XP total acumulado para estar no nível N
    // Baseado em: ~1 semana de estudo diário = nível 3-4
    //             ~1 mês = nível 8-10
    //             ~6 meses = nível 20-25
    //             ~2 anos = nível 40+
    $thresholds = [
        1  => 0,
        2  => 500,       // ~2-3 dias de estudo consistente
        3  => 1_500,     // ~1 semana
        4  => 3_500,     // ~2 semanas
        5  => 7_000,     // ~3-4 semanas
        6  => 13_000,    // ~6 semanas
        7  => 22_000,    // ~2 meses
        8  => 35_000,    // ~3 meses
        9  => 52_000,    // ~4 meses
        10 => 75_000,    // ~5 meses
        11 => 105_000,
        12 => 142_000,
        13 => 187_000,
        14 => 242_000,
        15 => 308_000,   // ~1 ano de estudo diário sério
        16 => 386_000,
        17 => 478_000,
        18 => 585_000,
        19 => 710_000,
        20 => 855_000,
        21 => 1_022_000,
        22 => 1_215_000,
        23 => 1_436_000,
        24 => 1_689_000,
        25 => 1_978_000, // ~2 anos
        26 => 2_308_000,
        27 => 2_682_000,
        28 => 3_107_000,
        29 => 3_588_000,
        30 => 4_131_000,
        31 => 4_744_000,
        32 => 5_434_000,
        33 => 6_209_000,
        34 => 7_076_000,
        35 => 8_044_000,
        36 => 9_123_000,
        37 => 10_323_000,
        38 => 11_656_000,
        39 => 13_133_000,
        40 => 14_768_000, // ~5 anos de uso diário
        41 => 16_573_000,
        42 => 18_563_000,
        43 => 20_751_000,
        44 => 23_152_000,
        45 => 25_782_000,
        46 => 28_658_000,
        47 => 31_797_000,
        48 => 35_219_000,
        49 => 38_943_000,
        50 => 42_989_000, // máximo absoluto
    ];

    return $thresholds[min($lv, 50)] ?? $thresholds[50];
}

$xpCurLevel  = xpForLevel($level);
$xpNextLevel = xpForLevel($level + 1);
$xpInLevel   = max(0, $xp - $xpCurLevel);
$xpNeeded    = max(1, $xpNextLevel - $xpCurLevel);
$xpPct       = ($level >= 50) ? 100 : min(100, (int)round($xpInLevel / $xpNeeded * 100));

// ── Nomes de nível ────────────────────────────────────────────
$LEVEL_NAMES = [
    1=>'Semente',       2=>'Broto',          3=>'Estudante',      4=>'Aplicado',
    5=>'Dedicado',      6=>'Focado',         7=>'Persistente',    8=>'Constante',
    9=>'Disciplinado',  10=>'Avançado',      11=>'Expert',        12=>'Mestre',
    13=>'Especialista', 14=>'Erudito',       15=>'Sábio',         16=>'Iluminado',
    17=>'Visionário',   18=>'Raiz Profunda', 19=>'Tronco Firme',  20=>'Copa Larga',
    21=>'Árvore Antiga',22=>'Guardião',      23=>'Ancestral',     24=>'Eterno Crescimento',
    25=>'Floresta',     26=>'Biodiverso',    27=>'Ecossistema',   28=>'Bioma',
    29=>'Natureza Viva',30=>'Ciclo Eterno',  31=>'Renascimento',  32=>'Hibernação',
    33=>'Primavera',    34=>'Florescência',  35=>'Lendário',      36=>'Mítico',
    37=>'Transcendente',38=>'Cósmico',       39=>'Estelar',       40=>'Galáctico',
    41=>'Universal',    42=>'Infinito',      43=>'Primordial',    44=>'Eterno',
    45=>'Absoluto',     46=>'Supremo',       47=>'Divino',        48=>'Imortal',
    49=>'Perpétuo',     50=>'Supremo Eterno',
];
$lvName = $LEVEL_NAMES[min($level, 50)] ?? 'Lendário';
$phase  = $level <= 17 ? 1 : ($level <= 34 ? 2 : 3);
$PHASE_INFO = [
    1 => ['name'=>'Fase 1: Broto',    'color'=>'#52b788','range'=>'Níveis 1–17', 'emoji'=>'🌱'],
    2 => ['name'=>'Fase 2: Árvore',   'color'=>'#40916c','range'=>'Níveis 18–34','emoji'=>'🌳'],
    3 => ['name'=>'Fase 3: Lendário', 'color'=>'#c9a84c','range'=>'Níveis 35–50','emoji'=>'🏆'],
];

// ── Estágio da planta (baseado em streak) ─────────────────────
$stages = [
    [0,   6,  '🌱','Semente'],
    [7,   14, '🌿','Broto Inicial'],
    [15,  29, '☘️', 'Planta Jovem'],
    [30,  59, '🌲','Planta Forte'],
    [60,  99, '🌳','Árvore Crescendo'],
    [100, 149,'🌴','Árvore Robusta'],
    [150, 199,'🎋','Árvore Antiga'],
    [200, 299,'✨','Árvore Gigante'],
    [300, PHP_INT_MAX,'🏆','Árvore Lendária'],
];
$plant = ['emoji'=>'🌱','name'=>'Semente','pct'=>0];
foreach ($stages as [$mn, $mx, $em, $nm]) {
    if ($streak >= $mn && $streak <= $mx) {
        $range = ($mx < PHP_INT_MAX) ? ($mx - $mn + 1) : 1;
        $plant = [
            'emoji' => $em,
            'name'  => $nm,
            'pct'   => ($mx < PHP_INT_MAX) ? min(100, (int)round(($streak - $mn) / $range * 100)) : 100,
        ];
        break;
    }
}

// ── Sidebar ───────────────────────────────────────────────────
$allObjs = dbQuery(
    'SELECT id, name FROM objectives WHERE user_id=? ORDER BY is_active DESC, created_at DESC',
    [$userId]
);
if (!isset($_SESSION['active_objective'])) {
    $ao = dbRow('SELECT id FROM objectives WHERE user_id=? AND is_active=1 ORDER BY created_at DESC LIMIT 1', [$userId]);
    if (!$ao) $ao = dbRow('SELECT id FROM objectives WHERE user_id=? ORDER BY created_at DESC LIMIT 1', [$userId]);
    $_SESSION['active_objective'] = $ao['id'] ?? null;
}
$activeObjId = $_SESSION['active_objective'];
$lvNSimple   = ['','Semente','Broto','Estudante','Aplicado','Dedicado','Avançado','Expert','Mestre','Lendário'];
$lvNameSidebar = $lvNSimple[min($level, count($lvNSimple) - 1)] ?? 'Lendário';

// ══════════════════════════════════════════════════════════════
// CONQUISTAS — FASE 1 (24 missões)
// ══════════════════════════════════════════════════════════════
$missions_p1 = [
    ['id'=>1,  'cat'=>'Primeiros Passos','ico'=>'🌱','name'=>'Primeira Semente',  'desc'=>'Complete sua primeira aula',               'check'=> $totalLessons >= 1],
    ['id'=>2,  'cat'=>'Primeiros Passos','ico'=>'🌿','name'=>'Broto Nascendo',    'desc'=>'Complete 5 aulas',                         'check'=> $totalLessons >= 5],
    ['id'=>3,  'cat'=>'Primeiros Passos','ico'=>'☘️', 'name'=>'Raízes Firmes',    'desc'=>'Complete 10 aulas',                        'check'=> $totalLessons >= 10],
    ['id'=>4,  'cat'=>'Primeiros Passos','ico'=>'🍀', 'name'=>'Folhas ao Vento',  'desc'=>'Crie seu primeiro objetivo',               'check'=> $totalObjectives >= 1],
    ['id'=>5,  'cat'=>'Consistência',    'ico'=>'🌞','name'=>'Primeira Semana',   'desc'=>'7 dias seguidos de estudo',                'check'=> $maxStrk >= 7],
    ['id'=>6,  'cat'=>'Consistência',    'ico'=>'🌙','name'=>'Lua Crescente',     'desc'=>'15 dias seguidos de estudo',               'check'=> $maxStrk >= 15],
    ['id'=>7,  'cat'=>'Consistência',    'ico'=>'🌺','name'=>'Florescendo',       'desc'=>'30 dias seguidos de estudo',               'check'=> $maxStrk >= 30],
    ['id'=>8,  'cat'=>'Consistência',    'ico'=>'🌻','name'=>'Sol Girassol',      'desc'=>'60 dias seguidos de estudo',               'check'=> $maxStrk >= 60],
    ['id'=>9,  'cat'=>'Conhecimento',    'ico'=>'🍃','name'=>'Folhagem Densa',    'desc'=>'Complete 25 aulas',                        'check'=> $totalLessons >= 25],
    ['id'=>10, 'cat'=>'Conhecimento',    'ico'=>'🌲','name'=>'Árvore Crescendo',  'desc'=>'Complete 50 aulas',                        'check'=> $totalLessons >= 50],
    ['id'=>11, 'cat'=>'Conhecimento',    'ico'=>'🌳','name'=>'Árvore Robusta',    'desc'=>'Complete 100 aulas',                       'check'=> $totalLessons >= 100],
    ['id'=>12, 'cat'=>'Conhecimento',    'ico'=>'🌴','name'=>'Palmeira Altiva',   'desc'=>'Complete 200 aulas',                       'check'=> $totalLessons >= 200],
    ['id'=>13, 'cat'=>'Dedicação',       'ico'=>'⏳','name'=>'Primeira Hora',     'desc'=>'Estude 1 hora no total',                   'check'=> $totalHoursInt >= 1],
    ['id'=>14, 'cat'=>'Dedicação',       'ico'=>'🕰️', 'name'=>'Dez Horas',       'desc'=>'Estude 10 horas no total',                 'check'=> $totalHoursInt >= 10],
    ['id'=>15, 'cat'=>'Dedicação',       'ico'=>'⌛','name'=>'Cinquenta Horas',   'desc'=>'Estude 50 horas no total',                 'check'=> $totalHoursInt >= 50],
    ['id'=>16, 'cat'=>'Dedicação',       'ico'=>'🌊','name'=>'Cem Horas',         'desc'=>'Estude 100 horas no total',                'check'=> $totalHoursInt >= 100],
    ['id'=>17, 'cat'=>'Metas',           'ico'=>'🎯','name'=>'Meta Certeira',     'desc'=>'Atinja a meta diária pela 1ª vez',         'check'=> $goalsHit >= 1],
    ['id'=>18, 'cat'=>'Metas',           'ico'=>'🪴','name'=>'Vaso Cheio',       'desc'=>'Atinja a meta diária 10 vezes',            'check'=> $goalsHit >= 10],
    ['id'=>19, 'cat'=>'Metas',           'ico'=>'🌾','name'=>'Colheita Farta',   'desc'=>'Atinja a meta diária 30 vezes',            'check'=> $goalsHit >= 30],
    ['id'=>20, 'cat'=>'Metas',           'ico'=>'🍎','name'=>'Frutos Maduros',   'desc'=>'Atinja a meta diária 100 vezes',           'check'=> $goalsHit >= 100],
    ['id'=>21, 'cat'=>'Evolução',        'ico'=>'💎','name'=>'Cristal de XP',     'desc'=>'Alcance o nível 5',                        'check'=> $level >= 5],
    ['id'=>22, 'cat'=>'Evolução',        'ico'=>'🪨','name'=>'Pedra Fundamental','desc'=>'Alcance o nível 10',                       'check'=> $level >= 10],
    ['id'=>23, 'cat'=>'Evolução',        'ico'=>'🗻','name'=>'Pico da Montanha', 'desc'=>'Alcance o nível 20',                       'check'=> $level >= 20],
    ['id'=>24, 'cat'=>'Evolução',        'ico'=>'🌌','name'=>'Além das Nuvens',  'desc'=>'Alcance o nível 35',                       'check'=> $level >= 35],
];

$unlocked_p1 = count(array_filter($missions_p1, fn($a) => $a['check']));
$total_p1    = count($missions_p1);
$p1_complete = ($unlocked_p1 === $total_p1);

// ══════════════════════════════════════════════════════════════
// CONQUISTAS — FASE 2 (24 missões avançadas)
// ══════════════════════════════════════════════════════════════
$missions_p2 = [
    ['id'=>25,'cat'=>'Lenda do Estudo','ico'=>'🏔️','name'=>'Maratona de Aulas', 'desc'=>'Complete 500 aulas no total',          'check'=> $totalLessons >= 500],
    ['id'=>26,'cat'=>'Lenda do Estudo','ico'=>'🌋','name'=>'Erupção de Saber',  'desc'=>'Complete 1000 aulas no total',         'check'=> $totalLessons >= 1000],
    ['id'=>27,'cat'=>'Lenda do Estudo','ico'=>'🪸','name'=>'Coral do Oceano',   'desc'=>'Estude 300 horas no total',            'check'=> $totalHoursInt >= 300],
    ['id'=>28,'cat'=>'Lenda do Estudo','ico'=>'🌐','name'=>'Conhecimento Global','desc'=>'Estude 500 horas no total',           'check'=> $totalHoursInt >= 500],
    ['id'=>29,'cat'=>'Implacável',     'ico'=>'🦋','name'=>'Metamorfose',       'desc'=>'90 dias seguidos de estudo',           'check'=> $maxStrk >= 90],
    ['id'=>30,'cat'=>'Implacável',     'ico'=>'🌵','name'=>'Deserto Florescido','desc'=>'180 dias seguidos de estudo',          'check'=> $maxStrk >= 180],
    ['id'=>31,'cat'=>'Implacável',     'ico'=>'🧊','name'=>'Inverno Superado',  'desc'=>'270 dias seguidos de estudo',          'check'=> $maxStrk >= 270],
    ['id'=>32,'cat'=>'Implacável',     'ico'=>'🪐','name'=>'Órbita Perfeita',   'desc'=>'365 dias seguidos de estudo',          'check'=> $maxStrk >= 365],
    ['id'=>33,'cat'=>'Mestre das Metas','ico'=>'🌈','name'=>'Arco-íris de Metas','desc'=>'Atinja a meta diária 200 vezes',     'check'=> $goalsHit >= 200],
    ['id'=>34,'cat'=>'Mestre das Metas','ico'=>'🍇','name'=>'Vinhedo de Ouro',  'desc'=>'Atinja a meta diária 365 vezes',      'check'=> $goalsHit >= 365],
    ['id'=>35,'cat'=>'Mestre das Metas','ico'=>'🌿','name'=>'Jardim Eterno',    'desc'=>'Crie 5 objetivos diferentes',         'check'=> $totalObjectives >= 5],
    ['id'=>36,'cat'=>'Mestre das Metas','ico'=>'🍁','name'=>'Folhas de Outono', 'desc'=>'Estude em 200 dias diferentes',       'check'=> $totalDays >= 200],
    ['id'=>37,'cat'=>'Tempo e Espaço', 'ico'=>'🌠','name'=>'Chuva de Estrelas', 'desc'=>'Acumule 50.000 XP',                   'check'=> $xp >= 50000],
    ['id'=>38,'cat'=>'Tempo e Espaço', 'ico'=>'🌙','name'=>'Eclipse Total',     'desc'=>'Alcance o nível 30',                  'check'=> $level >= 30],
    ['id'=>39,'cat'=>'Tempo e Espaço', 'ico'=>'🪐','name'=>'Gravidade Própria', 'desc'=>'Alcance o nível 40',                  'check'=> $level >= 40],
    ['id'=>40,'cat'=>'Tempo e Espaço', 'ico'=>'🌌','name'=>'Buraco de Minhoca', 'desc'=>'Alcance o nível 50 (Máximo)',         'check'=> $level >= 50],
    ['id'=>41,'cat'=>'Transcendente',  'ico'=>'🍄','name'=>'Fungo Milenar',     'desc'=>'Estude em 500 dias diferentes',       'check'=> $totalDays >= 500],
    ['id'=>42,'cat'=>'Transcendente',  'ico'=>'🌺','name'=>'Flor Rara',         'desc'=>'Acumule 100.000 XP',                  'check'=> $xp >= 100000],
    ['id'=>43,'cat'=>'Transcendente',  'ico'=>'🪨','name'=>'Rocha Ancestral',   'desc'=>'Estude 1000 horas no total',          'check'=> $totalHoursInt >= 1000],
    ['id'=>44,'cat'=>'Transcendente',  'ico'=>'🌊','name'=>'Oceano de Saber',   'desc'=>'Complete 2000 aulas no total',        'check'=> $totalLessons >= 2000],
    ['id'=>45,'cat'=>'Lendário Total', 'ico'=>'🏆','name'=>'Campeão das Metas', 'desc'=>'Atinja a meta diária 500 vezes',      'check'=> $goalsHit >= 500],
    ['id'=>46,'cat'=>'Lendário Total', 'ico'=>'🌲','name'=>'Floresta Sagrada',  'desc'=>'500 dias seguidos de estudo',         'check'=> $maxStrk >= 500],
    ['id'=>47,'cat'=>'Lendário Total', 'ico'=>'✨','name'=>'Estrela Perpétua',  'desc'=>'Acumule 500.000 XP',                  'check'=> $xp >= 500000],
    ['id'=>48,'cat'=>'Lendário Total', 'ico'=>'🌌','name'=>'Mestre Supremo',    'desc'=>'Complete todas as 23 missões da Fase 2','check'=> false],
];
// Última conquista: todas as outras 23 da fase 2 completas
$unlocked_p2_base = count(array_filter(array_slice($missions_p2, 0, 23), fn($a) => $a['check']));
$missions_p2[23]['check'] = ($unlocked_p2_base === 23);
$unlocked_p2 = count(array_filter($missions_p2, fn($a) => $a['check']));
$total_p2    = count($missions_p2);

// ── Desafios semanais ─────────────────────────────────────────
$challenges = [
    ['ico'=>'📚','name'=>'Maratonista',   'desc'=>'Estude 5 dias nesta semana',        'cur'=>$weekDays,             'goal'=>5,   'done'=>$weekDays >= 5,    'xp'=>100],
    ['ico'=>'⏱', 'name'=>'Horas de Ouro','desc'=>'Acumule 3h de estudo nesta semana', 'cur'=>min(180,$weekMin),     'goal'=>180, 'done'=>$weekMin >= 180,   'xp'=>150],
    ['ico'=>'🎯','name'=>'Meta em Dia',   'desc'=>'Atinja a meta 3x nesta semana',     'cur'=>$weekGoalsHit,         'goal'=>3,   'done'=>$weekGoalsHit >= 3,'xp'=>120],
    ['ico'=>'🌱','name'=>'Sequência Viva','desc'=>'Mantenha 7 dias seguidos de estudo','cur'=>min($streak, 7),       'goal'=>7,   'done'=>$streak >= 7,      'xp'=>200],
];

// Avatar
$avatarRow       = dbRow('SELECT avatar_type, avatar_emoji, avatar_url FROM users WHERE id=?', [$userId]);
$avatarType      = $avatarRow['avatar_type']  ?? 'initial';
$avatarEmoji     = $avatarRow['avatar_emoji'] ?? '';
$avatarUrl       = $avatarRow['avatar_url']   ?? '';
$avatarPublicUrl = $avatarUrl ? '/florescer/public' . $avatarUrl : '';

// XP para próximo nível legível
function fmtXp(int $n): string {
    if ($n >= 1_000_000) return number_format($n / 1_000_000, 1) . 'M';
    if ($n >= 1_000)     return number_format($n / 1_000, 1) . 'k';
    return (string)$n;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>florescer — Progresso</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700;9..144,900&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
  <style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --g950:#0d1f16;--g900:#132a1e;--g800:#1a3a2a;--g700:#1e4d35;
  --g600:#2d6a4f;--g500:#40916c;--g400:#52b788;--g300:#74c69d;
  --g200:#b7e4c7;--g50:#f0faf4;
  --n800:#1c1c1a;--n100:#f5f2ee;--n50:#faf8f5;--white:#fff;
  --gold:#c9a84c;--gold-l:#fef9e7;--gold-d:#7a5800;
  --red:#dc2626;--red-l:#fee2e2;
  --sw:240px;--hh:58px;
  --fd:'Fraunces',Georgia,serif;--fb:'Inter',system-ui,sans-serif;
  --r:12px;--rs:8px;
  --d:.22s;--e:cubic-bezier(.4,0,.2,1);
  --sh0:0 1px 3px rgba(0,0,0,.06);--sh1:0 2px 8px rgba(0,0,0,.07);
  --sh2:0 4px 16px rgba(0,0,0,.09);--sh3:0 12px 32px rgba(0,0,0,.12);
}
html,body{height:100%}
body{font-family:var(--fb);background:var(--n50);color:var(--n800);display:flex;overflow-x:hidden;-webkit-font-smoothing:antialiased}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:rgba(64,145,108,.2);border-radius:2px}

/* ── Main ───────────────────────────────────────────────── */
.main{margin-left:var(--sw);flex:1;min-height:100vh;display:flex;flex-direction:column;min-width:0}
.topbar{height:var(--hh);background:rgba(250,248,245,.92);backdrop-filter:blur(14px);border-bottom:1px solid rgba(0,0,0,.055);display:flex;align-items:center;justify-content:space-between;padding:0 1.8rem;position:sticky;top:0;z-index:40;flex-shrink:0}
.tb-left{display:flex;align-items:center;gap:.8rem}
.hamburger{display:none;flex-direction:column;gap:4px;cursor:pointer;background:none;border:none;padding:5px;border-radius:6px}
.hamburger span{display:block;width:19px;height:2px;background:var(--g600);border-radius:1px;transition:all var(--d) var(--e)}
.hamburger.open span:nth-child(1){transform:translateY(6px) rotate(45deg)}
.hamburger.open span:nth-child(2){opacity:0}
.hamburger.open span:nth-child(3){transform:translateY(-6px) rotate(-45deg)}
.tb-title{font-family:var(--fd);font-size:1rem;font-weight:600;color:var(--n800)}
.xp-pill{display:flex;align-items:center;gap:.28rem;background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:50px;padding:.26rem .75rem;font-size:.75rem;font-weight:600;color:var(--g500);box-shadow:var(--sh0)}

/* ── Conteúdo ───────────────────────────────────────────── */
.content{flex:1;padding:1.8rem 2rem}

/* ── Hero ───────────────────────────────────────────────── */
.evo-hero{background:linear-gradient(135deg,var(--g800),var(--g950));border-radius:var(--r);padding:1.6rem 1.8rem;margin-bottom:1.4rem;border:1px solid rgba(116,198,157,.1);box-shadow:var(--sh2);position:relative;overflow:hidden;display:grid;grid-template-columns:1fr auto;gap:1.5rem;align-items:center}
.evo-hero::before{content:'';position:absolute;top:-60px;right:-60px;width:220px;height:220px;border-radius:50%;background:radial-gradient(circle,rgba(116,198,157,.07) 0%,transparent 70%);pointer-events:none}
.evo-phase-badge{display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .65rem;border-radius:20px;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin-bottom:.55rem}
.evo-phase-badge.p1{background:rgba(82,183,136,.15);color:var(--g300);border:1px solid rgba(82,183,136,.2)}
.evo-phase-badge.p2{background:rgba(64,145,108,.15);color:#74c69d;border:1px solid rgba(64,145,108,.2)}
.evo-phase-badge.p3{background:rgba(201,168,76,.15);color:var(--gold);border:1px solid rgba(201,168,76,.2)}
.evo-level{font-family:var(--fd);font-size:2.5rem;font-weight:900;color:var(--white);letter-spacing:-.04em;line-height:1}
.evo-level span{font-size:1rem;font-weight:400;letter-spacing:0;color:rgba(116,198,157,.45);margin-left:.3rem}
.evo-name{font-size:.88rem;color:var(--g300);margin-top:.18rem;font-weight:500}
.xp-block{margin-top:1rem}
.xp-bar-wrap{height:10px;background:rgba(255,255,255,.07);border-radius:5px;overflow:hidden;margin-bottom:.3rem;position:relative}
.xp-bar-fill{height:100%;border-radius:5px;background:linear-gradient(90deg,var(--g500),var(--g300));width:0%;transition:width 1s var(--e);position:relative;overflow:hidden}
.xp-bar-fill::after{content:'';position:absolute;top:0;left:-60%;width:50%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.25),transparent);animation:shimmer 2.2s ease-in-out infinite}
@keyframes shimmer{0%{left:-60%}100%{left:120%}}
.xp-labels{display:flex;justify-content:space-between;font-size:.68rem;color:rgba(116,198,157,.45)}
.xp-labels strong{color:var(--g300)}
.evo-plant{text-align:center;position:relative;z-index:1}
.evo-plant-emoji{font-size:4.5rem;display:block;animation:plantFloat 4s ease-in-out infinite;filter:drop-shadow(0 4px 12px rgba(116,198,157,.25))}
@keyframes plantFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.evo-plant-name{font-size:.72rem;color:rgba(116,198,157,.45);margin-top:.4rem;font-weight:600;text-transform:uppercase;letter-spacing:.07em}
.evo-plant-bar{width:80px;height:3px;background:rgba(255,255,255,.07);border-radius:2px;margin:.35rem auto 0;overflow:hidden}
.evo-plant-fill{height:100%;background:linear-gradient(90deg,var(--g400),var(--g200));transition:width .8s var(--e)}

/* ── XP Info box ─────────────────────────────────────────── */
.xp-info-box{background:rgba(255,255,255,.04);border:1px solid rgba(116,198,157,.1);border-radius:var(--rs);padding:.6rem .85rem;margin-top:.65rem;font-size:.67rem;color:rgba(116,198,157,.45);line-height:1.6}
.xp-info-box strong{color:rgba(116,198,157,.75)}

/* ── Fases de nível ──────────────────────────────────────── */
.phases-row{display:grid;grid-template-columns:repeat(3,1fr);gap:.85rem;margin-bottom:1.4rem}
.phase-card{background:var(--white);border:1.5px solid rgba(0,0,0,.07);border-radius:var(--r);padding:.9rem 1rem;box-shadow:var(--sh0);position:relative;overflow:hidden}
.phase-card::after{content:'';position:absolute;bottom:0;left:0;right:0;height:3px;background:var(--pc,var(--g400));opacity:.7}
.phase-card.current{border-color:var(--pc,var(--g400));background:var(--g50);box-shadow:0 0 0 3px rgba(64,145,108,.08)}
.phase-card.locked{opacity:.5}
.ph-emoji{font-size:1.4rem;margin-bottom:.3rem}
.ph-name{font-size:.8rem;font-weight:700;color:var(--n800)}
.ph-range{font-size:.68rem;color:#bbb;margin-top:.08rem}
.ph-badge{display:inline-block;margin-top:.4rem;padding:.12rem .45rem;border-radius:20px;font-size:.63rem;font-weight:600}
.ph-badge.done{background:var(--g50);color:var(--g500)}
.ph-badge.active{background:rgba(64,145,108,.12);color:var(--g500)}
.ph-badge.upcoming{background:var(--n100);color:#bbb}

/* ── Stats grid ─────────────────────────────────────────── */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.9rem;margin-bottom:1.4rem}
.stat-card{background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r);padding:.95rem 1rem;box-shadow:var(--sh0);position:relative;overflow:hidden;transition:transform var(--d) var(--e)}
.stat-card:hover{transform:translateY(-2px);box-shadow:var(--sh1)}
.stat-card::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2.5px;background:var(--sc,linear-gradient(90deg,var(--g400),var(--g300)));opacity:.7}
.stat-ico{font-size:1.1rem;margin-bottom:.4rem;display:block}
.stat-val{font-family:var(--fd);font-size:1.65rem;font-weight:700;color:var(--n800);line-height:1;letter-spacing:-.03em}
.stat-val span{font-size:.85rem;font-weight:400;letter-spacing:0}
.stat-lbl{font-size:.64rem;font-weight:600;color:#bbb;text-transform:uppercase;letter-spacing:.06em;margin-top:.18rem}

/* ── Section ────────────────────────────────────────────── */
.section{background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r);box-shadow:var(--sh0);overflow:hidden;margin-bottom:1.2rem}
.section-head{padding:.9rem 1.2rem;border-bottom:1px solid rgba(0,0,0,.05);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem}
.section-title{font-family:var(--fd);font-size:.9rem;font-weight:700;color:var(--n800)}
.section-sub{font-size:.72rem;color:#bbb}
.section-body{padding:1.1rem 1.2rem}

/* ── Desafios ───────────────────────────────────────────── */
.challenges-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:.85rem}
.challenge-card{background:var(--n50);border:1.5px solid rgba(0,0,0,.07);border-radius:var(--rs);padding:.9rem 1rem;position:relative;overflow:hidden}
.challenge-card.done{border-color:rgba(64,145,108,.3);background:var(--g50)}
.challenge-card.done::before{content:'✓';position:absolute;top:.6rem;right:.75rem;font-size:.75rem;font-weight:700;color:var(--g500)}
.ch-head{display:flex;align-items:center;gap:.55rem;margin-bottom:.55rem}
.ch-ico{font-size:1.3rem;flex-shrink:0}
.ch-name{font-size:.84rem;font-weight:700;color:var(--n800)}
.ch-xp{font-size:.65rem;font-weight:700;color:var(--gold);background:var(--gold-l);padding:.1rem .38rem;border-radius:20px;margin-left:auto;flex-shrink:0}
.ch-desc{font-size:.74rem;color:#aaa;margin-bottom:.6rem;line-height:1.45}
.ch-bar-wrap{height:5px;background:rgba(0,0,0,.07);border-radius:3px;overflow:hidden;margin-bottom:.28rem}
.ch-bar-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,var(--g500),var(--g300));transition:width .6s var(--e)}
.challenge-card.done .ch-bar-fill{background:var(--g400)}
.ch-prog{font-size:.67rem;color:#bbb;display:flex;justify-content:space-between}
.ch-prog .done-lbl{color:var(--g500);font-weight:600}

/* ── Conquistas ──────────────────────────────────────────── */
.mission-phase-selector{display:flex;align-items:center;gap:.3rem;background:var(--n50);border-radius:50px;padding:.22rem;border:1px solid rgba(0,0,0,.08)}
.mps-btn{padding:.25rem .82rem;border-radius:50px;font-family:var(--fb);font-size:.72rem;font-weight:600;cursor:pointer;border:none;background:transparent;color:#aaa;transition:all var(--d) var(--e);line-height:1.4}
.mps-btn.active-p1{background:var(--g500);color:var(--white);box-shadow:0 2px 6px rgba(64,145,108,.22)}
.mps-btn.active-p2{background:linear-gradient(135deg,var(--gold),#e8c97a);color:var(--gold-d);box-shadow:0 2px 6px rgba(201,168,76,.25)}
.mps-btn:disabled{opacity:.38;cursor:not-allowed}
.congrats-banner{background:linear-gradient(135deg,var(--g800),var(--g950));border:1px solid rgba(201,168,76,.22);border-radius:var(--rs);padding:.8rem 1rem;margin-bottom:.9rem;display:flex;align-items:center;gap:.75rem}
.congrats-banner .cb-ico{font-size:1.6rem;flex-shrink:0}
.congrats-banner .cb-text strong{display:block;font-size:.82rem;font-weight:700;color:var(--gold);margin-bottom:.12rem}
.congrats-banner .cb-text p{font-size:.72rem;color:rgba(116,198,157,.55);line-height:1.45}
.ach-progress-wrap{background:var(--n50);border:1px solid rgba(0,0,0,.06);border-radius:var(--rs);padding:.7rem .95rem;margin-bottom:.9rem;display:flex;align-items:center;gap:1rem}
.ach-prog-left{flex:1}
.ach-prog-title{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#bbb;margin-bottom:.28rem}
.ach-prog-barwrap{height:5px;background:rgba(0,0,0,.07);border-radius:3px;overflow:hidden}
.ach-prog-fill{height:100%;border-radius:3px;transition:width .8s var(--e)}
.ach-prog-fill.p1{background:linear-gradient(90deg,var(--g500),var(--g200))}
.ach-prog-fill.p2{background:linear-gradient(90deg,var(--gold),#e8c97a)}
.ach-prog-count{font-family:var(--fd);font-size:1.3rem;font-weight:900;line-height:1}
.ach-prog-count.p1{color:var(--g500)}.ach-prog-count.p2{color:var(--gold)}
.ach-prog-of{font-size:.61rem;color:#ccc;margin-top:.04rem;text-align:right}
.ach-cats{}
.ach-cat{margin-bottom:1.1rem}
.ach-cat-title{font-size:.62rem;text-transform:uppercase;letter-spacing:.09em;font-weight:700;color:#ccc;margin-bottom:.55rem;display:flex;align-items:center;gap:.45rem}
.ach-cat-title::after{content:'';flex:1;height:1px;background:rgba(0,0,0,.06)}
.ach-cat-title.p2-cat{color:rgba(201,168,76,.55)}
.ach-cat-title.p2-cat::after{background:rgba(201,168,76,.1)}
.ach-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.6rem}
.ach-card{background:var(--n50);border:1px solid rgba(0,0,0,.07);border-radius:10px;padding:.8rem .55rem .7rem;text-align:center;position:relative;overflow:hidden;transition:transform var(--d) var(--e),box-shadow var(--d) var(--e);cursor:default;display:flex;flex-direction:column;align-items:center;gap:.2rem}
.ach-card:hover{transform:translateY(-2px);box-shadow:0 4px 14px rgba(0,0,0,.08)}
.ach-card.unlocked-p1{background:var(--white);border-color:rgba(64,145,108,.22)}
.ach-card.unlocked-p1::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--g400),var(--g200));border-radius:10px 10px 0 0}
.ach-card.unlocked-p2{background:var(--white);border-color:rgba(201,168,76,.3)}
.ach-card.unlocked-p2::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--gold),#e8c97a);border-radius:10px 10px 0 0}
.ach-card.locked{opacity:.35;filter:grayscale(.5)}
.ach-check{position:absolute;top:.32rem;right:.4rem;font-size:.58rem}
.ach-card.unlocked-p1 .ach-check::before{content:'★';color:var(--g400);animation:starPulse 2s ease-in-out infinite}
.ach-card.unlocked-p2 .ach-check::before{content:'★';color:var(--gold);animation:starPulse 2s ease-in-out infinite}
@keyframes starPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.25)}}
.ach-ico{font-size:1.7rem;display:block;line-height:1;margin-bottom:.1rem}
.ach-card.locked .ach-ico{filter:grayscale(1);opacity:.35}
.ach-name{font-size:.68rem;font-weight:700;color:var(--n800);line-height:1.3}
.ach-desc{font-size:.6rem;color:#bbb;line-height:1.4}
.ach-card.unlocked-p1 .ach-name{color:var(--g600)}
.ach-card.unlocked-p2 .ach-name{color:var(--gold-d)}
.ach-locked-ico{font-size:.6rem;color:#d0d0d0;margin-top:.05rem;display:block}
#missionPanel1,#missionPanel2{animation:fadeUp .2s var(--e) both}
@keyframes fadeUp{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:translateY(0)}}

/* Toast */
.toast-wrap{position:fixed;bottom:1.4rem;right:1.4rem;z-index:500;display:flex;flex-direction:column;gap:.4rem;pointer-events:none}
.toast{background:var(--n800);color:#eee;padding:.62rem .95rem;border-radius:var(--rs);font-size:.79rem;display:flex;align-items:center;gap:.4rem;animation:tin .24s var(--e) both;max-width:270px;box-shadow:var(--sh3);pointer-events:all}
.toast.ok{border-left:3px solid var(--g400)}.toast.err{border-left:3px solid #f87171}.toast.info{border-left:3px solid var(--gold)}
@keyframes tin{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}

/* Responsivo */
@media(max-width:900px){.stats-grid{grid-template-columns:repeat(2,1fr)}.ach-grid{grid-template-columns:repeat(3,1fr)}.phases-row{grid-template-columns:1fr}}
@media(max-width:768px){.main{margin-left:0}.hamburger{display:flex}.topbar{padding:0 1.1rem}.content{padding:1.2rem 1rem}.evo-hero{grid-template-columns:1fr}.evo-plant{display:none}.challenges-grid{grid-template-columns:1fr}.ach-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:480px){.stats-grid{grid-template-columns:repeat(2,1fr)}.ach-grid{grid-template-columns:repeat(2,1fr)}}
  </style>
</head>
  <!-- Favicon básico -->
  <link rel="icon" href="/florescer/public/img/fav/favicon.ico">

  <!-- PNG moderno -->
  <link rel="icon" type="image/png" sizes="32x32" href="/florescer/public/img/fav/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/florescer/public/img/fav/favicon-16.png">

  <!-- Apple (iOS) -->
  <link rel="apple-touch-icon" sizes="180x180" href="/florescer/public/img/fav/favicon-180.png">

  <!-- Android / PWA -->
  <link rel="manifest" href="/florescer/public/img/fav/site.webmanifest">

  <!-- Windows (tiles) -->
  <meta name="msapplication-TileImage" content="/florescer/public/img/fav/mstile-150x150.png">
  <meta name="msapplication-TileColor" content="#ffffff">

  <!-- Cor da barra do navegador (mobile) -->
  <meta name="theme-color" content="#ffffff">
<body>

<?php
$lvName = $lvNameSidebar;
include __DIR__ . '/sidebar.php';
?>

<div class="main">
  <header class="topbar">
    <div class="tb-left">
      <button class="hamburger" id="hamburger" onclick="toggleSidebar()">
        <span></span><span></span><span></span>
      </button>
      <span class="tb-title">🌱 Meu Progresso</span>
    </div>
    <div class="xp-pill">⭐ <?= number_format($xp) ?> XP</div>
  </header>

  <main class="content">

    <!-- ── Hero ───────────────────────────────────────────── -->
    <div class="evo-hero">
      <div>
        <div class="evo-phase-badge p<?= $phase ?>">
          <?= $PHASE_INFO[$phase]['emoji'] ?> <?= $PHASE_INFO[$phase]['name'] ?> · <?= $PHASE_INFO[$phase]['range'] ?>
        </div>
        <div class="evo-level"><?= $level ?><span>/ 50</span></div>
        <div class="evo-name"><?= htmlspecialchars($lvName, ENT_QUOTES) ?></div>
        <div class="xp-block">
          <div class="xp-bar-wrap">
            <div class="xp-bar-fill" id="xpBarFill"></div>
          </div>
          <div class="xp-labels">
            <span><strong><?= number_format($xp) ?></strong> XP</span>
            <?php if ($level < 50): ?>
              <span><?= $xpPct ?>% → Nv.<?= $level + 1 ?> (faltam <?= fmtXp(max(0, $xpNextLevel - $xp)) ?> XP)</span>
            <?php else: ?>
              <span>🏆 Nível máximo!</span>
            <?php endif; ?>
          </div>
        </div>
        <!-- Dica de como ganhar XP 
        <div class="xp-info-box">
          <strong>Como ganhar XP:</strong>
          +5 XP/min estudado · +20 XP/aula · +50 XP ao bater a meta · +3 XP/dia de streak · Bônus semanal a cada 7 dias
        </div> -->
      </div>
      <div class="evo-plant">
        <span class="evo-plant-emoji"><?= $plant['emoji'] ?></span>
        <div class="evo-plant-name"><?= htmlspecialchars($plant['name'], ENT_QUOTES) ?></div>
        <div class="evo-plant-bar"><div class="evo-plant-fill" id="plantFill"></div></div>
      </div>
    </div>

    <!-- ── Fases ───────────────────────────────────────────── -->
    <div class="phases-row">
      <?php foreach ($PHASE_INFO as $pid => $pi):
        $isCurrent  = ($pid === $phase);
        $isDone     = ($pid < $phase);
        $isUpcoming = ($pid > $phase);
        $pc = ['1'=>'#52b788','2'=>'#40916c','3'=>'#c9a84c'][$pid] ?? '#40916c';
        $cls = $isCurrent ? 'current' : ($isUpcoming ? 'locked' : '');
      ?>
      <div class="phase-card <?= $cls ?>" style="--pc:<?= $pc ?>">
        <div class="ph-emoji"><?= $pi['emoji'] ?></div>
        <div class="ph-name"><?= $pi['name'] ?></div>
        <div class="ph-range"><?= $pi['range'] ?></div>
        <?php if ($isDone): ?>
          <span class="ph-badge done">✓ Concluída</span>
        <?php elseif ($isCurrent): ?>
          <span class="ph-badge active">⬤ Fase atual</span>
        <?php else: ?>
          <span class="ph-badge upcoming">🔒 Bloqueada</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ── Stats ──────────────────────────────────────────── -->
    <div class="stats-grid">
      <div class="stat-card" style="--sc:linear-gradient(90deg,#40916c,#74c69d)">
        <span class="stat-ico">📖</span>
        <div class="stat-val"><?= number_format($totalLessons) ?></div>
        <div class="stat-lbl">Aulas concluídas</div>
      </div>
      <div class="stat-card" style="--sc:linear-gradient(90deg,#2563eb,#60a5fa)">
        <span class="stat-ico">⏱</span>
        <div class="stat-val">
          <?php if ($totalHoursFloat >= 1): ?>
            <?= number_format($totalHoursFloat, 1) ?><span>h</span>
          <?php else: ?>
            <?= $totalMin ?><span>min</span>
          <?php endif; ?>
        </div>
        <div class="stat-lbl">Horas estudadas</div>
      </div>
      <div class="stat-card" style="--sc:linear-gradient(90deg,#c9a84c,#e8c97a)">
        <span class="stat-ico"><?= $plant['emoji'] ?></span>
        <div class="stat-val"><?= number_format($maxStrk) ?></div>
        <div class="stat-lbl">Maior streak</div>
      </div>
      <div class="stat-card" style="--sc:linear-gradient(90deg,#7c3aed,#a78bfa)">
        <span class="stat-ico">🎯</span>
        <div class="stat-val"><?= number_format($goalsHit) ?></div>
        <div class="stat-lbl">Metas atingidas</div>
      </div>
    </div>

    <!-- ── Desafios da semana ─────────────────────────────── -->
    <div class="section">
      <div class="section-head">
        <span class="section-title">⚡ Desafios da Semana</span>
        <span class="section-sub">Reiniciam toda semana</span>
      </div>
      <div class="section-body">
        <div class="challenges-grid">
          <?php foreach ($challenges as $ch):
            $pct = $ch['goal'] > 0 ? min(100, (int)round($ch['cur'] / $ch['goal'] * 100)) : 0;
          ?>
          <div class="challenge-card <?= $ch['done'] ? 'done' : '' ?>">
            <div class="ch-head">
              <span class="ch-ico"><?= $ch['ico'] ?></span>
              <span class="ch-name"><?= htmlspecialchars($ch['name'], ENT_QUOTES) ?></span>
              <span class="ch-xp">+<?= $ch['xp'] ?> XP</span>
            </div>
            <div class="ch-desc"><?= htmlspecialchars($ch['desc'], ENT_QUOTES) ?></div>
            <div class="ch-bar-wrap">
              <div class="ch-bar-fill" style="width:<?= $pct ?>%"></div>
            </div>
            <div class="ch-prog">
              <span><?= $ch['cur'] ?> / <?= $ch['goal'] ?></span>
              <?php if ($ch['done']): ?>
                <span class="done-lbl">✓ Concluído!</span>
              <?php else: ?>
                <span><?= $pct ?>%</span>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ── Conquistas ─────────────────────────────────────── -->
    <div class="section">
      <div class="section-head">
        <span class="section-title">🏅 Conquistas</span>
        <div class="mission-phase-selector">
          <button class="mps-btn active-p1" id="btnPhase1" onclick="showMissionPhase(1)">
            🌱 Fase 1
            <span style="font-size:.65rem;margin-left:.2rem;opacity:.7"><?= $unlocked_p1 ?>/<?= $total_p1 ?></span>
          </button>
          <button class="mps-btn" id="btnPhase2" onclick="showMissionPhase(2)"
            <?= !$p1_complete ? 'disabled' : '' ?>>
            ✨ Fase 2
            <?php if (!$p1_complete): ?>
              <span style="font-size:.65rem;margin-left:.2rem">🔒</span>
            <?php else: ?>
              <span style="font-size:.65rem;margin-left:.2rem;opacity:.7"><?= $unlocked_p2 ?>/<?= $total_p2 ?></span>
            <?php endif; ?>
          </button>
        </div>
      </div>
      <div class="section-body">

        <!-- PAINEL FASE 1 -->
        <div id="missionPanel1">
          <div class="ach-progress-wrap">
            <div class="ach-prog-left">
              <div class="ach-prog-title">Progresso Fase 1</div>
              <div class="ach-prog-barwrap">
                <div class="ach-prog-fill p1" id="achBar1" style="width:0%"></div>
              </div>
            </div>
            <div style="text-align:right;margin-left:1.2rem">
              <div class="ach-prog-count p1"><?= $unlocked_p1 ?></div>
              <div class="ach-prog-of">de <?= $total_p1 ?></div>
            </div>
          </div>

          <?php if ($p1_complete): ?>
          <div class="congrats-banner">
            <span class="cb-ico">🎉</span>
            <div class="cb-text">
              <strong>Fase 1 completa! Parabéns, <?= $userName ?>!</strong>
              <p>Você desbloqueou a Fase 2 com missões ainda mais desafiadoras.</p>
            </div>
          </div>
          <?php endif; ?>

          <div class="ach-cats">
            <?php
            $cats1 = [];
            foreach ($missions_p1 as $m) $cats1[$m['cat']][] = $m;
            foreach ($cats1 as $catName => $list):
            ?>
            <div class="ach-cat">
              <div class="ach-cat-title"><?= htmlspecialchars($catName, ENT_QUOTES) ?></div>
              <div class="ach-grid">
                <?php foreach ($list as $m):
                  $cls = $m['check'] ? 'unlocked-p1' : 'locked';
                ?>
                <div class="ach-card <?= $cls ?>" title="<?= htmlspecialchars($m['desc'], ENT_QUOTES) ?>">
                  <?php if ($m['check']): ?><span class="ach-check"></span><?php endif; ?>
                  <span class="ach-ico"><?= $m['ico'] ?></span>
                  <div class="ach-name"><?= htmlspecialchars($m['name'], ENT_QUOTES) ?></div>
                  <div class="ach-desc"><?= htmlspecialchars($m['desc'], ENT_QUOTES) ?></div>
                  <?php if (!$m['check']): ?><span class="ach-locked-ico">🔒</span><?php endif; ?>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- PAINEL FASE 2 -->
        <div id="missionPanel2" style="display:none">
          <?php if (!$p1_complete): ?>
          <div style="text-align:center;padding:3rem 1.5rem;color:#bbb">
            <div style="font-size:3rem;margin-bottom:.8rem;opacity:.3">🔒</div>
            <div style="font-size:.88rem;font-weight:600;margin-bottom:.4rem">Fase 2 bloqueada</div>
            <div style="font-size:.78rem;line-height:1.65">
              Complete as <?= $total_p1 ?> conquistas da Fase 1 para desbloquear.<br>
              Você completou <strong><?= $unlocked_p1 ?></strong> de <?= $total_p1 ?> (faltam <?= $total_p1 - $unlocked_p1 ?>).
            </div>
          </div>
          <?php else: ?>
          <div class="ach-progress-wrap">
            <div class="ach-prog-left">
              <div class="ach-prog-title">Progresso Fase 2</div>
              <div class="ach-prog-barwrap">
                <div class="ach-prog-fill p2" id="achBar2" style="width:0%"></div>
              </div>
            </div>
            <div style="text-align:right;margin-left:1.2rem">
              <div class="ach-prog-count p2"><?= $unlocked_p2 ?></div>
              <div class="ach-prog-of">de <?= $total_p2 ?></div>
            </div>
          </div>
          <div class="ach-cats">
            <?php
            $cats2 = [];
            foreach ($missions_p2 as $m) $cats2[$m['cat']][] = $m;
            foreach ($cats2 as $catName => $list):
            ?>
            <div class="ach-cat">
              <div class="ach-cat-title p2-cat"><?= htmlspecialchars($catName, ENT_QUOTES) ?></div>
              <div class="ach-grid">
                <?php foreach ($list as $m):
                  $cls = $m['check'] ? 'unlocked-p2' : 'locked';
                ?>
                <div class="ach-card <?= $cls ?>" title="<?= htmlspecialchars($m['desc'], ENT_QUOTES) ?>">
                  <?php if ($m['check']): ?><span class="ach-check"></span><?php endif; ?>
                  <span class="ach-ico"><?= $m['ico'] ?></span>
                  <div class="ach-name"><?= htmlspecialchars($m['name'], ENT_QUOTES) ?></div>
                  <div class="ach-desc"><?= htmlspecialchars($m['desc'], ENT_QUOTES) ?></div>
                  <?php if (!$m['check']): ?><span class="ach-locked-ico">🔒</span><?php endif; ?>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

      </div>
    </div>

  </main>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
const XP_PCT    = <?= $xpPct ?>;
const PLANT_PCT = <?= $plant['pct'] ?>;
const ACH1_PCT  = <?= $total_p1 > 0 ? round($unlocked_p1 / $total_p1 * 100) : 0 ?>;
const ACH2_PCT  = <?= $total_p2 > 0 ? round($unlocked_p2 / $total_p2 * 100) : 0 ?>;

function toggleSidebar() {
    if (typeof window._sidebarToggle === 'function') { window._sidebarToggle(); return; }
    const sb = document.getElementById('sidebar'),
          ov = document.getElementById('sbOverlay'),
          hb = document.getElementById('hamburger');
    if (!sb) return;
    const open = sb.classList.toggle('open');
    if (ov) ov.classList.toggle('show', open);
    if (hb) hb.classList.toggle('open', open);
    document.body.style.overflow = open ? 'hidden' : '';
}

function showMissionPhase(phase) {
    const p1 = document.getElementById('missionPanel1');
    const p2 = document.getElementById('missionPanel2');
    const b1 = document.getElementById('btnPhase1');
    const b2 = document.getElementById('btnPhase2');
    if (phase === 1) {
        p1.style.display = 'block'; p2.style.display = 'none';
        b1.className = 'mps-btn active-p1'; b2.className = 'mps-btn';
        setTimeout(() => { const b = document.getElementById('achBar1'); if (b) b.style.width = ACH1_PCT + '%'; }, 80);
    } else {
        p1.style.display = 'none'; p2.style.display = 'block';
        b1.className = 'mps-btn'; b2.className = 'mps-btn active-p2';
        setTimeout(() => { const b = document.getElementById('achBar2'); if (b) b.style.width = ACH2_PCT + '%'; }, 80);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        const xpBar = document.getElementById('xpBarFill');
        if (xpBar) xpBar.style.width = XP_PCT + '%';
        const plantBar = document.getElementById('plantFill');
        if (plantBar) plantBar.style.width = PLANT_PCT + '%';
        const bar1 = document.getElementById('achBar1');
        if (bar1) bar1.style.width = ACH1_PCT + '%';
    }, 150);
});
</script>
</body>
</html>