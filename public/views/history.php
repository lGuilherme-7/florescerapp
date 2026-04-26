<?php
// ============================================================
// /public/views/history.php — Semente v2.0
// Histórico de estudos — heatmap mensal + stats + sessões
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
$currentPage = 'history';

// ── Mês exibido ───────────────────────────────────────────────
$today    = date('Y-m-d');
$todayY   = (int)date('Y');
$todayM   = (int)date('n');
$viewYear = max(2020, min($todayY, (int)($_GET['y'] ?? $todayY)));
$viewMon  = max(1,    min(12,      (int)($_GET['m'] ?? $todayM)));

// Bloqueia meses futuros
if ($viewYear > $todayY || ($viewYear === $todayY && $viewMon > $todayM)) {
    $viewYear = $todayY;
    $viewMon  = $todayM;
}

$isCurrentMonth = ($viewYear === $todayY && $viewMon === $todayM);
$daysInMon      = (int)date('t', mktime(0,0,0,$viewMon,1,$viewYear));
$firstWday      = (int)date('w', mktime(0,0,0,$viewMon,1,$viewYear)); // 0=dom

// Mês anterior / próximo
$prevM = $viewMon - 1; $prevY = $viewYear;
if ($prevM < 1)  { $prevM = 12; $prevY--; }
$nextM = $viewMon + 1; $nextY = $viewYear;
if ($nextM > 12) { $nextM = 1;  $nextY++; }
$nextDisabled = ($nextY > $todayY || ($nextY === $todayY && $nextM > $todayM));

$MONTHS_PT = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho',
              'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$monthLabel = $MONTHS_PT[$viewMon] . ' ' . $viewYear;

// ── Dados do mês (daily_summaries) ───────────────────────────
$startDate    = sprintf('%04d-%02d-01', $viewYear, $viewMon);
$endDate      = sprintf('%04d-%02d-%02d', $viewYear, $viewMon, $daysInMon);
$effectiveEnd = min($endDate, $today); // nunca mostra dados futuros

$rows = dbQuery(
    'SELECT study_date, total_min, goal_reached
     FROM daily_summaries
     WHERE user_id=? AND study_date BETWEEN ? AND ?
     ORDER BY study_date ASC',
    [$userId, $startDate, $effectiveEnd]
);

// Indexa por número do dia
$dayData = [];
foreach ($rows as $r) {
    $d = (int)date('j', strtotime($r['study_date']));
    $dayData[$d] = [
        'min'  => (int)$r['total_min'],
        'goal' => (bool)$r['goal_reached'],
    ];
}

// ── Stats do mês ──────────────────────────────────────────────
$daysStudied = count(array_filter($dayData, fn($d) => $d['min'] > 0));
$totalMinMon = array_sum(array_column($dayData, 'min'));
$goalsHitMon = count(array_filter($dayData, fn($d) => $d['goal']));
$bestDayMin  = $dayData ? max(array_column($dayData, 'min')) : 0;

function fmtMin(int $m): string {
    if ($m === 0) return '0min';
    if ($m < 60)  return $m . 'min';
    $h = intdiv($m, 60); $r = $m % 60;
    return $r > 0 ? "{$h}h {$r}min" : "{$h}h";
}

// Retorna 0–3 baseado nos minutos estudados
function heatLevel(int $min): int {
    if ($min === 0) return 0;
    if ($min < 30)  return 1;
    if ($min < 60)  return 2;
    return 3;
}

// Aulas por dia (tabela sem created_at — mantido vazio conforme original)
$lessonsPerDay = [];

// ── Sidebar vars ──────────────────────────────────────────────
$ud     = dbRow('SELECT xp, level, streak FROM users WHERE id=?', [$userId]);
$xp     = (int)($ud['xp']     ?? 0);
$level  = (int)($ud['level']  ?? 1);
$streak = (int)($ud['streak'] ?? 0);
$lvN    = ['','Semente','Broto','Estudante','Aplicado','Dedicado','Avançado','Expert','Mestre','Lendário'];
$lvName = $lvN[min($level, count($lvN)-1)] ?? 'Lendário';
$stages = [[0,6,'🌱','Semente'],[7,14,'🌿','Broto Inicial'],[15,29,'☘️','Planta Jovem'],
           [30,59,'🌲','Planta Forte'],[60,99,'🌳','Árvore Crescendo'],[100,149,'🌴','Árvore Robusta'],
           [150,199,'🎋','Árvore Antiga'],[200,299,'✨','Árvore Gigante'],[300,PHP_INT_MAX,'🏆','Árvore Lendária']];
$plant  = ['emoji'=>'🌱','name'=>'Semente','pct'=>0];
foreach ($stages as [$mn,$mx,$em,$nm]) {
    if ($streak >= $mn && $streak <= $mx) {
        $r2 = $mx < PHP_INT_MAX ? $mx - $mn + 1 : 1;
        $plant = ['emoji'=>$em,'name'=>$nm,
                  'pct'=>$mx<PHP_INT_MAX ? min(100,round(($streak-$mn)/$r2*100)) : 100];
        break;
    }
}
if (!isset($_SESSION['active_objective'])) {
    $ao = dbRow('SELECT id FROM objectives WHERE user_id=? AND is_active=1 ORDER BY created_at DESC LIMIT 1', [$userId]);
    if (!$ao) $ao = dbRow('SELECT id FROM objectives WHERE user_id=? ORDER BY created_at DESC LIMIT 1', [$userId]);
    $_SESSION['active_objective'] = $ao['id'] ?? null;
}
$activeObjId = $_SESSION['active_objective'];
$allObjs     = dbQuery('SELECT id,name FROM objectives WHERE user_id=? ORDER BY is_active DESC,created_at DESC', [$userId]);

$avatarRow       = dbRow('SELECT avatar_type, avatar_emoji, avatar_url FROM users WHERE id=?', [$userId]);
$avatarType      = $avatarRow['avatar_type']  ?? 'initial';
$avatarEmoji     = $avatarRow['avatar_emoji'] ?? '';
$avatarUrl       = $avatarRow['avatar_url']   ?? '';
$avatarPublicUrl = $avatarUrl ? '/florescer/public' . $avatarUrl : '';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Semente — Histórico</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700;9..144,900&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
  <header class="topbar">
    <div class="tb-left">
      <button class="hamburger" id="hamburger" onclick="toggleSidebar()">
        <span></span><span></span><span></span>
      </button>
      <span class="tb-title">🕓 Histórico</span>
    </div>
    <div class="xp-pill">⭐ <?= number_format($xp) ?> XP</div>
  </header>

  <main class="hist-content">

    <!-- ══ STATS — 4 cards ocupando a largura toda ══ -->
    <div class="stats-grid">

      <div class="stat-card" style="--sc:var(--g500);--sc2:var(--g300)">
        <span class="stat-ico">📅</span>
        <div class="stat-val"><?= $daysStudied ?></div>
        <div class="stat-lbl">Dias estudados</div>
      </div>

      <div class="stat-card" style="--sc:#2563eb;--sc2:#60a5fa">
        <span class="stat-ico">⏱</span>
        <div class="stat-val"><?= fmtMin($totalMinMon) ?></div>
        <div class="stat-lbl">Horas no mês</div>
      </div>

      <div class="stat-card" style="--sc:#c9a84c;--sc2:#e8c97a">
        <span class="stat-ico">🏆</span>
        <div class="stat-val"><?= fmtMin($bestDayMin) ?></div>
        <div class="stat-lbl">Melhor dia</div>
      </div>

      <div class="stat-card" style="--sc:#7c3aed;--sc2:#a78bfa">
        <span class="stat-ico">🎯</span>
        <div class="stat-val"><?= $goalsHitMon ?></div>
        <div class="stat-lbl">Metas atingidas</div>
      </div>

    </div><!-- /stats-grid -->

    <!-- ══ CALENDÁRIO HEATMAP ══ -->
    <div class="cal-card">

      <!-- Cabeçalho: título + navegação -->
      <div class="cal-header">
        <a class="cal-nav-btn" href="?y=<?= $prevY ?>&m=<?= $prevM ?>" title="Mês anterior">‹</a>

        <div class="cal-month-label">
          <span class="cal-month-name"><?= $monthLabel ?></span>
          <?php if (!$isCurrentMonth): ?>
            <a class="cal-today-link" href="?">Hoje</a>
          <?php endif; ?>
        </div>

        <a class="cal-nav-btn <?= $nextDisabled ? 'disabled' : '' ?>"
           <?= !$nextDisabled ? "href=\"?y={$nextY}&m={$nextM}\"" : '' ?>
           title="Próximo mês">›</a>
      </div>

      <!-- Legenda de intensidade -->
      <div class="cal-legend">
        <span class="leg-lbl">Menos</span>
        <span class="leg-cell lv0"></span>
        <span class="leg-cell lv1"></span>
        <span class="leg-cell lv2"></span>
        <span class="leg-cell lv3"></span>
        <span class="leg-lbl">Mais</span>
      </div>

      <!-- Cabeçalho dos dias da semana -->
      <div class="cal-dow-row">
        <?php foreach (['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'] as $i => $dw): ?>
          <div class="cal-dow <?= in_array($i,[0,6]) ? 'wkend' : '' ?>"><?= $dw ?></div>
        <?php endforeach; ?>
      </div>

      <!-- Grid do calendário — mostra TODOS os dias do mês -->
      <div class="cal-grid">
        <?php
        // Células vazias antes do dia 1
        for ($i = 0; $i < $firstWday; $i++) {
            echo '<div class="cal-cell empty"></div>';
        }

        for ($d = 1; $d <= $daysInMon; $d++) {
            $dateStr  = sprintf('%04d-%02d-%02d', $viewYear, $viewMon, $d);
            $isFuture = ($dateStr > $today);
            $isToday  = ($dateStr === $today);
            $wday     = (int)date('w', strtotime($dateStr));
            $isWkend  = ($wday === 0 || $wday === 6);

            $min  = $dayData[$d]['min']  ?? 0;
            $goal = $dayData[$d]['goal'] ?? false;
            $lv   = $isFuture ? 0 : heatLevel($min);
            $les  = $lessonsPerDay[$d]   ?? 0;

            // Classes da célula
            $cls = 'cal-cell lv' . $lv;
            if ($isToday)  $cls .= ' today';
            if ($isWkend)  $cls .= ' wkend';
            if ($goal)     $cls .= ' goal';
            if ($isFuture) $cls .= ' future';

            // Tooltip: minutos + aulas + meta
            if ($isFuture) {
                $tip = '';
            } elseif ($min > 0) {
                $tip  = fmtMin($min);
                $tip .= $les  > 0 ? " · {$les} aula" . ($les > 1 ? 's' : '') : '';
                $tip .= $goal ? ' · 🎯' : '';
            } else {
                $tip = 'Sem estudo';
            }

            $tipAttr = $tip ? " data-tip=\"{$tip}\"" : '';

            echo "<div class=\"{$cls}\"{$tipAttr}>";
            echo   "<span class=\"cal-day-num\">{$d}</span>";
            if ($goal) echo '<span class="cal-goal-dot"></span>';
            echo '</div>';
        }

        // Preenche última linha para fechar o grid
        $total = $firstWday + $daysInMon;
        $rem   = (7 - ($total % 7)) % 7;
        for ($i = 0; $i < $rem; $i++) {
            echo '<div class="cal-cell empty"></div>';
        }
        ?>
      </div><!-- /cal-grid -->

      <!-- Tooltip global (posicionado via JS) -->
      <div class="cal-tooltip" id="calTip"></div>

    </div><!-- /cal-card -->

    <!-- ══ LISTA DE SESSÕES ══ -->
    <div class="sessions-card">
      <div class="sessions-head">
        <span class="sessions-title">📋 Dias de <?= $MONTHS_PT[$viewMon] ?></span>
        <?php $lastDay = $isCurrentMonth ? (int)date('j') : $daysInMon; ?>
        <span class="sessions-sub"><?= $daysStudied ?> de <?= $lastDay ?> dias com estudo</span>
      </div>

      <div class="sessions-list">
        <?php
        $WDAYS_PT = ['domingo','segunda','terça','quarta','quinta','sexta','sábado'];

        for ($d = $lastDay; $d >= 1; $d--) {
            $dateStr = sprintf('%04d-%02d-%02d', $viewYear, $viewMon, $d);
            $min     = $dayData[$d]['min']  ?? 0;
            $goal    = $dayData[$d]['goal'] ?? false;
            $les     = $lessonsPerDay[$d]   ?? 0;
            $wday    = (int)date('w', strtotime($dateStr));
            $isToday = ($dateStr === $today);
            $studied = ($min > 0);
        ?>
        <div class="sess-row <?= $studied ? '' : 'empty' ?>">
          <div class="sess-date-col">
            <div class="sess-day-num <?= $isToday ? 'today' : '' ?>"><?= $d ?></div>
            <div class="sess-day-name"><?= $WDAYS_PT[$wday] ?></div>
          </div>
          <div class="sess-bar-col">
            <?php if ($studied): ?>
              <div class="sess-bar-wrap">
                <div class="sess-bar-fill lv<?= heatLevel($min) ?>"
                     style="width:<?= min(100, round($min/120*100)) ?>%"></div>
              </div>
              <div class="sess-meta">
                <span class="sess-time"><?= fmtMin($min) ?></span>
                <?php if ($les > 0): ?>
                  <span class="sess-lessons"><?= $les ?> aula<?= $les > 1 ? 's' : '' ?></span>
                <?php endif; ?>
                <?php if ($goal): ?>
                  <span class="sess-goal-badge">🎯 Meta</span>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="sess-empty-lbl">Sem estudo neste dia</div>
            <?php endif; ?>
          </div>
        </div>
        <?php } ?>
      </div>
    </div><!-- /sessions-card -->

  </main>
</div><!-- /main -->

<style>
/* ══════════════════════════════════════════════════════════════
   HISTORY — estilos desta view
   Estilos globais (sidebar, topbar, variáveis CSS) vêm do sidebar.php
══════════════════════════════════════════════════════════════ */

/* ── Conteúdo principal ─────────────────────────────────────── */
.hist-content {
  flex: 1;
  padding: 1.6rem 1.8rem;
  display: flex;
  flex-direction: column;
  gap: 1.4rem;
  width: 100%;
}

/* ══ STATS ══════════════════════════════════════════════════ */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr); /* 4 colunas iguais, largura total */
  gap: 1rem;
}

.stat-card {
  background: var(--white);
  border: 1px solid rgba(0,0,0,.06);
  border-radius: 14px;
  padding: 1.15rem 1.2rem 1rem;
  box-shadow: var(--sh0);
  position: relative;
  overflow: hidden;
  transition: transform var(--d) var(--e), box-shadow var(--d) var(--e);
}
.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--sh1);
}

/* Barra de cor no topo */
.stat-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--sc), var(--sc2, var(--sc)));
  border-radius: 14px 14px 0 0;
}

/* Círculo decorativo de fundo */
.stat-card::after {
  content: '';
  position: absolute;
  top: -10px; right: -14px;
  width: 72px; height: 72px;
  border-radius: 50%;
  background: var(--sc);
  opacity: .06;
  pointer-events: none;
}

.stat-ico  { font-size: 1.1rem; display: block; margin-bottom: .45rem; line-height: 1; }
.stat-val  { font-family: var(--fd); font-size: 1.55rem; font-weight: 700;
             color: var(--n800); line-height: 1; letter-spacing: -.03em; }
.stat-lbl  { font-size: .63rem; font-weight: 600; color: #bbb;
             text-transform: uppercase; letter-spacing: .07em; margin-top: .2rem; }

/* ══ CALENDÁRIO ════════════════════════════════════════════ */
.cal-card {
  background: var(--white);
  border: 1px solid rgba(0,0,0,.06);
  border-radius: 14px;
  box-shadow: var(--sh1);
  padding: 1.3rem 1.5rem 1.5rem;
  width: 100%;       /* ocupa toda a largura disponível */
}

/* Cabeçalho: nav + título + link hoje */
.cal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
}
.cal-month-label {
  display: flex;
  align-items: center;
  gap: .65rem;
}
.cal-month-name {
  font-family: var(--fd);
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--n800);
  letter-spacing: -.02em;
}
.cal-nav-btn {
  width: 32px; height: 32px;
  border-radius: 50%;
  border: 1px solid rgba(0,0,0,.1);
  background: var(--white);
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  font-size: .92rem;
  color: #999;
  text-decoration: none;
  transition: all var(--d) var(--e);
  user-select: none;
  flex-shrink: 0;
}
.cal-nav-btn:hover:not(.disabled) { background: var(--n100); color: var(--n800); }
.cal-nav-btn.disabled { opacity: .25; cursor: not-allowed; pointer-events: none; }

.cal-today-link {
  font-size: .72rem; font-weight: 500;
  color: var(--g500); text-decoration: none;
  background: var(--g50);
  padding: .2rem .6rem;
  border-radius: 20px;
  border: 1px solid rgba(64,145,108,.18);
  transition: all var(--d) var(--e);
}
.cal-today-link:hover { background: var(--g200); border-color: var(--g400); color: var(--g700); }

/* Legenda de intensidade */
.cal-legend {
  display: flex; align-items: center;
  gap: .3rem;
  justify-content: flex-end;
  margin-bottom: .75rem;
}
.leg-lbl  { font-size: .62rem; color: #c8c2bb; }
.leg-cell { width: 14px; height: 14px; border-radius: 3px; display: inline-block; }
.leg-cell.lv0 { background: rgba(0,0,0,.07); }
.leg-cell.lv1 { background: rgba(82,183,136,.32); }
.leg-cell.lv2 { background: rgba(64,145,108,.62); }
.leg-cell.lv3 { background: #2d6a4f; }

/* Cabeçalho: Dom Seg Ter ... */
.cal-dow-row {
  display: grid;
  grid-template-columns: repeat(7, 1fr); /* mesma grade do calendário */
  border-bottom: 1px solid rgba(0,0,0,.05);
  margin-bottom: .5rem;
  padding-bottom: .35rem;
}
.cal-dow {
  text-align: center;
  font-size: .65rem; font-weight: 600;
  color: #c0bab4;
  text-transform: uppercase; letter-spacing: .06em;
  padding: .2rem 0;
}
.cal-dow.wkend { color: #d4cfc9; }

/* ── Grade das células ─────────────────────────────────────── */
.cal-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr); /* 7 colunas, cresce com a largura */
  gap: 5px;
}

/* Célula — cresce horizontalmente, altura mínima fixa */
.cal-cell {
  min-height: 62px;           /* altura mínima confortável */
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  position: relative;
  cursor: default;
  transition: transform var(--d) var(--e), box-shadow var(--d) var(--e);
}
.cal-cell:not(.empty):not(.future)[data-tip]:hover {
  transform: scale(1.06);
  z-index: 2;
  box-shadow: 0 4px 14px rgba(0,0,0,.13);
}

/* Intensidades de cor */
.cal-cell.lv0 { background: rgba(0,0,0,.04); }
.cal-cell.lv1 { background: rgba(82,183,136,.22); }
.cal-cell.lv2 { background: rgba(64,145,108,.55); }
.cal-cell.lv3 { background: #2d6a4f; }

/* Número do dia — centralizado */
.cal-day-num {
  font-size: .75rem; font-weight: 600;
  color: #bbb;
  line-height: 1;
  pointer-events: none;
  display: flex; align-items: center; justify-content: center;
}
.cal-cell.lv0 .cal-day-num { color: #c0bab4; }
.cal-cell.lv1 .cal-day-num { color: rgba(30,77,53,.75); }
.cal-cell.lv2 .cal-day-num { color: rgba(255,255,255,.88); }
.cal-cell.lv3 .cal-day-num { color: rgba(255,255,255,.92); }

/* Hoje: anel verde */
.cal-cell.today { box-shadow: 0 0 0 2px var(--g500), 0 0 0 4px rgba(64,145,108,.12); }
.cal-cell.today.lv0 .cal-day-num { color: var(--g500); font-weight: 700; }
.cal-cell.today.lv2 .cal-day-num,
.cal-cell.today.lv3 .cal-day-num { color: #fff; font-weight: 700; }

/* Fim de semana */
.cal-cell.wkend.lv0 { background: rgba(0,0,0,.026); }

/* Célula vazia (padding de grid) */
.cal-cell.empty { background: transparent; pointer-events: none; }

/* Dias futuros — desbotados, sem interação */
.cal-cell.future {
  background: rgba(0,0,0,.02);
  opacity: .3;
  cursor: not-allowed;
  pointer-events: none;
}
.cal-cell.future .cal-day-num { color: #ccc; }

/* Ponto de meta (🎯) no canto inferior direito */
.cal-goal-dot {
  width: 5px; height: 5px;
  border-radius: 50%;
  background: var(--gold);
  position: absolute;
  bottom: 5px; right: 5px;
  flex-shrink: 0;
}
.cal-cell.lv3 .cal-goal-dot { background: #e8c97a; }

/* ── Tooltip ────────────────────────────────────────────────── */
.cal-tooltip {
  position: fixed;
  background: var(--n800); color: #eee;
  padding: .42rem .82rem;
  border-radius: 8px;
  font-size: .73rem; font-weight: 500;
  pointer-events: none;
  z-index: 200;
  opacity: 0; transform: translateY(5px);
  transition: opacity .13s ease, transform .13s ease;
  white-space: nowrap;
  box-shadow: var(--sh2);
  border: 1px solid rgba(255,255,255,.05);
}
.cal-tooltip.show { opacity: 1; transform: translateY(0); }

/* ══ LISTA DE SESSÕES ═══════════════════════════════════════ */
.sessions-card {
  background: var(--white);
  border: 1px solid rgba(0,0,0,.06);
  border-radius: 14px;
  box-shadow: var(--sh0);
  overflow: hidden;
}
.sessions-head {
  padding: .95rem 1.3rem;
  border-bottom: 1px solid rgba(0,0,0,.05);
  display: flex; align-items: center; justify-content: space-between;
  background: rgba(0,0,0,.013);
}
.sessions-title { font-family: var(--fd); font-size: .92rem; font-weight: 700; color: var(--n800); }
.sessions-sub   { font-size: .71rem; color: #c0bab4; font-weight: 500; }

.sessions-list { display: flex; flex-direction: column; }

/* Linha de sessão */
.sess-row {
  display: grid;
  grid-template-columns: 72px 1fr;
  align-items: center;
  gap: 1rem;
  padding: .7rem 1.3rem;
  border-bottom: 1px solid rgba(0,0,0,.04);
  transition: background var(--d) var(--e);
}
.sess-row:last-child { border-bottom: none; }
.sess-row:hover      { background: var(--n50); }
.sess-row.empty      { opacity: .35; }
.sess-row.empty:hover{ background: transparent; }

/* Coluna da data */
.sess-date-col { text-align: center; }
.sess-day-num {
  font-family: var(--fd); font-size: 1.3rem; font-weight: 700;
  color: var(--n800); line-height: 1; letter-spacing: -.03em;
}
.sess-day-num.today { color: var(--g500); }
.sess-day-name { font-size: .61rem; color: #c0bab4; text-transform: capitalize;
                 margin-top: .1rem; font-weight: 500; }

/* Coluna da barra */
.sess-bar-col { display: flex; flex-direction: column; gap: .32rem; flex: 1; min-width: 0; }
.sess-bar-wrap {
  height: 5px; background: rgba(0,0,0,.06);
  border-radius: 3px; overflow: hidden;
}
.sess-bar-fill { height: 100%; border-radius: 3px; transition: width .5s var(--e); }
.sess-bar-fill.lv1 { background: rgba(82,183,136,.5); }
.sess-bar-fill.lv2 { background: rgba(64,145,108,.72); }
.sess-bar-fill.lv3 { background: var(--g500); }

.sess-meta    { display: flex; align-items: center; gap: .45rem; flex-wrap: wrap; }
.sess-time    { font-size: .79rem; font-weight: 600; color: var(--n800); }
.sess-lessons { font-size: .68rem; color: #b8b2ab; }
.sess-goal-badge {
  font-size: .64rem; font-weight: 600;
  background: rgba(201,168,76,.1); color: #8a6a0c;
  padding: .1rem .4rem; border-radius: 20px;
  border: 1px solid rgba(201,168,76,.22);
}
.sess-empty-lbl { font-size: .75rem; color: #d0cac3; font-style: italic; }

/* ══ RESPONSIVO ════════════════════════════════════════════ */
@media (max-width: 860px) {
  .hist-content  { padding: 1.2rem 1.3rem; gap: 1.1rem; }
  .stats-grid    { grid-template-columns: repeat(2, 1fr); gap: .75rem; }
  .cal-card      { padding: 1rem 1.1rem 1.2rem; }
}
@media (max-width: 600px) {
  .hist-content  { padding: .9rem 1rem; gap: .9rem; }
  .stats-grid    { gap: .6rem; }
  .cal-cell      { min-height: 44px; }
  .cal-day-num   { font-size: .62rem; }
  .sess-row      { grid-template-columns: 52px 1fr; gap: .6rem; padding: .6rem .9rem; }
  .sess-day-num  { font-size: 1.1rem; }
}
@media (max-width: 380px) {
  .stats-grid    { grid-template-columns: repeat(2, 1fr); }
  .stat-val      { font-size: 1.25rem; }
}
</style>

<script>
/* ── Tooltip do heatmap ──────────────────────────────────────
   Mostra minutos + meta ao passar o mouse pela célula.
   Posiciona automaticamente para não sair da viewport.
─────────────────────────────────────────────────────────── */
(function () {
  const tip   = document.getElementById('calTip');
  const cells = document.querySelectorAll('.cal-cell[data-tip]');

  cells.forEach(cell => {
    cell.addEventListener('mouseenter', e => {
      tip.textContent = cell.dataset.tip;
      tip.classList.add('show');
      positionTip(e);
    });
    cell.addEventListener('mousemove',  positionTip);
    cell.addEventListener('mouseleave', () => tip.classList.remove('show'));
  });

  function positionTip(e) {
    const m  = 12;
    let x = e.clientX + m;
    let y = e.clientY - 38;
    const tw = tip.offsetWidth  || 130;
    const th = tip.offsetHeight || 32;
    if (x + tw > window.innerWidth)  x = e.clientX - tw - m;
    if (y < 0)                        y = e.clientY + m;
    if (y + th > window.innerHeight)  y = window.innerHeight - th - 8;
    tip.style.left = x + 'px';
    tip.style.top  = y + 'px';
  }
})();
</script>

</body>
</html>