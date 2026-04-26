<?php
// ============================================================
// /admin/views/sessoes.php — florescer Admin v3.0
// Sessões de estudo dos usuários
// Fuso: America/Recife (PE - Brasil)
// ============================================================
require_once __DIR__ . '/../includes/auth_admin.php';
require_once dirname(dirname(__DIR__)) . '/config/db.php';

requireAdmin();
date_default_timezone_set('America/Recife');

$adminName   = $_SESSION['admin_name'] ?? 'Admin';
$adminLetter = strtoupper(mb_substr($adminName, 0, 1, 'UTF-8'));

// ── Filtros ───────────────────────────────────────────────────
$search   = mb_substr(trim($_GET['q']    ?? ''), 0, 100, 'UTF-8');
$dateFrom = trim($_GET['from'] ?? date('Y-m-d', strtotime('-6 days')));
$dateTo   = trim($_GET['to']   ?? date('Y-m-d'));
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 25;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = date('Y-m-d', strtotime('-6 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))   $dateTo   = date('Y-m-d');
if ($dateFrom > $dateTo) [$dateFrom, $dateTo] = [$dateTo, $dateFrom];

// ── Stats do período ──────────────────────────────────────────
$sp = [$dateFrom, $dateTo];
$totalSessions  = (int)(dbRow('SELECT COUNT(*) AS n FROM daily_summaries ds WHERE ds.study_date BETWEEN ? AND ? AND ds.total_min>0', $sp)['n'] ?? 0);
$totalMinPeriod = (int)(dbRow('SELECT COALESCE(SUM(total_min),0) AS n FROM daily_summaries WHERE study_date BETWEEN ? AND ?', $sp)['n'] ?? 0);
$uniqueUsers    = (int)(dbRow('SELECT COUNT(DISTINCT user_id) AS n FROM daily_summaries WHERE study_date BETWEEN ? AND ? AND total_min>0', $sp)['n'] ?? 0);
$goalsHit       = (int)(dbRow('SELECT COUNT(*) AS n FROM daily_summaries WHERE study_date BETWEEN ? AND ? AND goal_reached=1', $sp)['n'] ?? 0);

// ── Sessões paginadas ─────────────────────────────────────────
$where  = 'WHERE ds.study_date BETWEEN ? AND ? AND ds.total_min > 0';
$params = [$dateFrom, $dateTo];
if ($search !== '') {
    $where   .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
$totalRows  = (int)(dbRow("SELECT COUNT(*) AS n FROM daily_summaries ds JOIN users u ON u.id=ds.user_id $where", $params)['n'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;
$params[]   = $perPage;
$params[]   = $offset;

$sessions = dbQuery(
    "SELECT ds.id, ds.study_date, ds.total_min, ds.goal_reached,
            u.id AS user_id, u.name AS user_name, u.email AS user_email,
            u.level, u.streak
     FROM daily_summaries ds
     JOIN users u ON u.id=ds.user_id
     $where
     ORDER BY ds.study_date DESC, ds.total_min DESC
     LIMIT ? OFFSET ?",
    $params
);

// ── Atividade 14 dias (gráfico) ───────────────────────────────
$daily14    = dbQuery("SELECT study_date, COUNT(DISTINCT user_id) AS active_users, COALESCE(SUM(total_min),0) AS total_min FROM daily_summaries WHERE study_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY study_date ORDER BY study_date ASC");
$daily14Map = array_column($daily14, null, 'study_date');
$maxMin14   = 1;
foreach ($daily14 as $d) { if ((int)$d['total_min'] > $maxMin14) $maxMin14 = (int)$d['total_min']; }

function fmtMinS(int $m): string {
    if ($m < 60) return $m.'min';
    $h = intdiv($m,60); $r = $m%60;
    return $r > 0 ? "{$h}h {$r}min" : "{$h}h";
}
function fmtDateS(string $d): string {
    $diff = (int)floor((strtotime(date('Y-m-d')) - strtotime($d)) / 86400);
    if ($diff === 0) return 'Hoje';
    if ($diff === 1) return 'Ontem';
    return date('d/m', strtotime($d));
}
function fmtDateFullS(string $d): string {
    $MONTHS = ['','jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
    $ts = strtotime($d);
    return date('d',$ts).' '.$MONTHS[(int)date('n',$ts)];
}
function pgUrlS(int $p, string $from, string $to, string $q): string {
    return '?'.http_build_query(array_filter(['page'=>$p>1?$p:null,'from'=>$from,'to'=>$to,'q'=>$q?:null]));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>Sessões — florescer Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{
    --ink:#0b1a12;--ink2:#122019;--ink3:#1a3027;
    --border:rgba(82,183,136,.1);--border2:rgba(82,183,136,.06);
    --muted:rgba(116,198,157,.3);--muted2:rgba(116,198,157,.18);
    --leaf:#52b788;--leaf2:#74c69d;--leaf3:#b7e4c7;
    --gold:#c9a84c;--red:#e05252;
    --text:#c8e6d4;--text2:rgba(200,230,212,.55);--text3:rgba(200,230,212,.3);
    --serif:'Instrument Serif',Georgia,serif;
    --sans:'DM Sans',system-ui,sans-serif;
    --sw:220px;--hh:54px;--r:12px;--r2:8px;--t:.18s cubic-bezier(.4,0,.2,1);
  }
  html,body{height:100%;font-family:var(--sans);background:var(--ink);color:var(--text);-webkit-font-smoothing:antialiased}
  body{display:flex}
  ::-webkit-scrollbar{width:3px}::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px}

  /* ── Sidebar ─────────────────────────────────────────────── */
  .aside{width:var(--sw);min-height:100vh;position:fixed;top:0;left:0;background:var(--ink2);border-right:1px solid var(--border2);display:flex;flex-direction:column;z-index:50}
  .a-logo{padding:1.1rem 1.2rem .9rem;border-bottom:1px solid var(--border2);display:flex;align-items:center;gap:.6rem;flex-shrink:0}
  .a-logo-mark{width:32px;height:32px;border-radius:9px;flex-shrink:0;background:linear-gradient(135deg,var(--leaf),#2d6a4f);display:flex;align-items:center;justify-content:center;font-size:.95rem;box-shadow:0 2px 10px rgba(82,183,136,.25)}
  .a-logo-name{font-family:var(--serif);font-size:1rem;color:var(--leaf3);line-height:1.1}
  .a-logo-tag{font-size:.54rem;color:var(--muted);text-transform:uppercase;letter-spacing:.12em}
  .a-who{margin:.65rem .8rem;background:rgba(82,183,136,.05);border:1px solid var(--border);border-radius:var(--r2);padding:.45rem .65rem;display:flex;align-items:center;gap:.5rem}
  .a-av{width:24px;height:24px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,var(--leaf),#2d6a4f);display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:600;color:#fff}
  .a-name{font-size:.72rem;font-weight:500;color:var(--leaf2);line-height:1}
  .a-role{font-size:.57rem;color:var(--muted);margin-top:.08rem}
  .a-nav{flex:1;overflow-y:auto;padding:.3rem 0 .5rem}
  .a-grp{font-size:.52rem;text-transform:uppercase;letter-spacing:.1em;color:var(--muted2);padding:.7rem 1.2rem .2rem;display:block}
  .a-link{display:flex;align-items:center;gap:.5rem;padding:.38rem 1.2rem;font-size:.74rem;color:var(--text3);text-decoration:none;border-left:2px solid transparent;transition:all var(--t)}
  .a-link:hover{color:var(--leaf2);background:rgba(82,183,136,.04)}
  .a-link.active{color:var(--leaf2);background:rgba(82,183,136,.07);border-left-color:var(--leaf);font-weight:500}
  .a-link-ico{width:.9rem;text-align:center;font-size:.78rem;opacity:.8;flex-shrink:0}
  .a-foot{padding:.7rem .8rem;border-top:1px solid var(--border2);flex-shrink:0}
  .a-logout{width:100%;display:flex;align-items:center;justify-content:center;gap:.38rem;padding:.38rem;border-radius:var(--r2);background:none;border:1px solid rgba(224,82,82,.12);color:rgba(224,82,82,.4);font-family:var(--sans);font-size:.7rem;cursor:pointer;transition:all var(--t)}
  .a-logout:hover{background:rgba(224,82,82,.06);color:var(--red)}

  /* ── Layout ─────────────────────────────────────────────── */
  .main{margin-left:var(--sw);flex:1;min-width:0;display:flex;flex-direction:column}
  .topbar{height:var(--hh);position:sticky;top:0;z-index:40;background:rgba(11,26,18,.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--border2);display:flex;align-items:center;justify-content:space-between;padding:0 1.5rem;flex-shrink:0}
  .tb-title{font-family:var(--serif);font-size:1rem;color:var(--leaf3)}
  .tb-right{font-size:.67rem;color:var(--text3)}

  .page{padding:1.25rem 1.5rem;display:flex;flex-direction:column;gap:1rem;flex:1}

  /* ── Stats ──────────────────────────────────────────────── */
  .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:.65rem}
  .stat{background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);padding:.9rem 1rem;display:flex;align-items:center;gap:.7rem;transition:border-color var(--t),transform var(--t);position:relative;overflow:hidden}
  .stat::before{content:'';position:absolute;bottom:0;left:0;right:0;height:1px;background:var(--stat-accent,var(--leaf));opacity:.4}
  .stat:hover{border-color:var(--border);transform:translateY(-1px)}
  .stat-ico{font-size:1.2rem;flex-shrink:0;opacity:.65}
  .stat-val{font-family:var(--serif);font-size:1.4rem;color:var(--leaf3);line-height:1}
  .stat-lbl{font-size:.6rem;color:var(--text3);margin-top:.1rem;text-transform:uppercase;letter-spacing:.05em}

  /* ── Gráfico 14 dias ─────────────────────────────────────── */
  .chart-card{background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);overflow:hidden}
  .chart-head{padding:.65rem 1rem;border-bottom:1px solid var(--border2);display:flex;align-items:center;justify-content:space-between}
  .chart-title{font-size:.72rem;font-weight:500;color:var(--text2)}
  .chart-sub{font-size:.63rem;color:var(--text3)}
  .chart-bars{display:flex;align-items:flex-end;gap:.3rem;padding:.7rem 1rem .4rem;height:110px}
  .cb-wrap{flex:1;display:flex;flex-direction:column;align-items:center;gap:.2rem;min-width:0}
  .cb{width:100%;border-radius:3px 3px 0 0;min-height:3px;background:rgba(82,183,136,.12);transition:background var(--t);cursor:default}
  .cb:hover,.cb.today{background:var(--leaf)}
  .cb-lbl{font-size:.54rem;color:var(--text3);white-space:nowrap}
  .cb-lbl.today{color:var(--leaf)}
  .cb-n{font-size:.52rem;color:var(--muted);min-height:.75rem}

  /* ── Filtros ─────────────────────────────────────────────── */
  .filter-card{background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);padding:.9rem 1rem}
  .filter-row{display:flex;align-items:flex-end;gap:.55rem;flex-wrap:wrap}
  .fg{display:flex;flex-direction:column;gap:.25rem}
  .fl{font-size:.67rem;font-weight:500;color:var(--muted);letter-spacing:.03em}
  .fc{padding:.46rem .7rem;background:rgba(255,255,255,.03);border:1px solid var(--border2);border-radius:var(--r2);color:var(--text);font-family:var(--sans);font-size:.78rem;outline:none;transition:all var(--t)}
  .fc:focus{border-color:var(--leaf);background:rgba(82,183,136,.05)}
  input[type="date"].fc::-webkit-calendar-picker-indicator{filter:invert(.5) sepia(1) saturate(2) hue-rotate(90deg);cursor:pointer}
  .search-wrap{position:relative;flex:1;min-width:180px}
  .search-wrap svg{position:absolute;left:.6rem;top:50%;transform:translateY(-50%);width:13px;height:13px;color:var(--muted);pointer-events:none}
  .search-wrap .fc{width:100%;padding-left:1.9rem}
  .btn-primary{padding:.46rem .9rem;background:var(--leaf);border:none;border-radius:var(--r2);color:#0b1a12;font-family:var(--sans);font-size:.75rem;font-weight:600;cursor:pointer;transition:all var(--t);white-space:nowrap}
  .btn-primary:hover{background:var(--leaf2);transform:translateY(-1px)}
  .btn-ghost{padding:.46rem .75rem;background:none;border:1px solid var(--border2);border-radius:var(--r2);color:var(--text3);font-family:var(--sans);font-size:.75rem;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;transition:all var(--t)}
  .btn-ghost:hover{border-color:var(--border);color:var(--text2)}
  .presets{display:flex;gap:.35rem;flex-wrap:wrap;margin-top:.6rem}
  .preset{padding:.22rem .6rem;border-radius:50px;font-size:.68rem;font-weight:500;border:1px solid var(--border2);background:none;color:var(--text3);cursor:pointer;text-decoration:none;transition:all var(--t)}
  .preset:hover,.preset.active{border-color:var(--leaf);color:var(--leaf2);background:rgba(82,183,136,.06)}

  /* ── Tabela ──────────────────────────────────────────────── */
  .tbl-card{background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);overflow:hidden}
  .tbl-head{padding:.65rem 1rem;border-bottom:1px solid var(--border2);display:flex;align-items:center;justify-content:space-between}
  .tbl-head-title{font-size:.72rem;font-weight:500;color:var(--text2)}
  .tbl-head-meta{font-size:.63rem;color:var(--text3)}
  .tbl{width:100%;border-collapse:collapse}
  .tbl th{font-size:.57rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted2);font-weight:500;padding:.45rem 1rem;text-align:left;border-bottom:1px solid var(--border2)}
  .tbl td{padding:.5rem 1rem;font-size:.75rem;color:var(--text2);border-bottom:1px solid var(--border2)}
  .tbl tr:last-child td{border-bottom:none}
  .tbl tr:hover td{background:rgba(82,183,136,.025)}
  .td-user{display:flex;align-items:center;gap:.55rem}
  .td-av{width:26px;height:26px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,var(--leaf),#2d6a4f);display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:600;color:#fff}
  .td-name{font-weight:500;color:var(--text)}
  .td-email{font-size:.67rem;color:var(--text3);margin-top:.04rem}
  .date-badge{display:flex;flex-direction:column;align-items:flex-start}
  .date-day{font-family:var(--serif);font-size:.95rem;color:var(--leaf3);line-height:1}
  .date-dow{font-size:.58rem;color:var(--text3);text-transform:uppercase}
  .time-bar-wrap{display:flex;align-items:center;gap:.5rem}
  .time-bar{flex:1;height:4px;background:rgba(255,255,255,.05);border-radius:2px;overflow:hidden;max-width:80px}
  .time-bar-fill{height:100%;border-radius:2px;background:linear-gradient(90deg,var(--leaf),var(--leaf2))}
  .time-val{font-size:.76rem;font-weight:500;color:var(--text);white-space:nowrap}
  .goal-badge{display:inline-flex;align-items:center;gap:.2rem;padding:.1rem .38rem;border-radius:20px;font-size:.62rem;font-weight:600}
  .goal-badge.yes{background:rgba(201,168,76,.1);color:var(--gold);border:1px solid rgba(201,168,76,.2)}
  .goal-badge.no{background:rgba(255,255,255,.03);color:var(--text3);border:1px solid var(--border2)}
  .chip{display:inline-block;padding:.08rem .38rem;border-radius:20px;font-size:.62rem;font-weight:500;background:rgba(82,183,136,.08);color:var(--leaf2);border:1px solid var(--border)}

  /* ── Paginação ──────────────────────────────────────────── */
  .pagination{display:flex;align-items:center;justify-content:center;gap:.3rem;padding:.65rem;border-top:1px solid var(--border2)}
  .pg{padding:.26rem .58rem;border-radius:var(--r2);border:1px solid var(--border2);background:none;color:var(--text3);font-size:.71rem;cursor:pointer;text-decoration:none;transition:all var(--t)}
  .pg:hover{border-color:var(--leaf);color:var(--leaf2)}
  .pg.cur{background:var(--leaf);border-color:var(--leaf);color:#0b1a12;font-weight:600}
  .pg.off{opacity:.2;pointer-events:none}
  .empty-row{padding:2.5rem;text-align:center;color:var(--text3);font-size:.75rem}

  /* Toast */
  #toasts{position:fixed;bottom:1rem;right:1rem;z-index:999;display:flex;flex-direction:column;gap:.3rem;pointer-events:none}
  .toast{background:var(--ink2);color:var(--text);border:1px solid var(--border);border-radius:var(--r2);padding:.48rem .8rem;font-size:.71rem;display:flex;align-items:center;gap:.38rem;animation:slideIn .2s ease both;max-width:270px;pointer-events:all;box-shadow:0 4px 20px rgba(0,0,0,.4)}
  .toast.ok{border-left:2px solid var(--leaf)}.toast.err{border-left:2px solid var(--red)}
  @keyframes slideIn{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:none}}

  @media(max-width:1100px){.stats-row{grid-template-columns:repeat(2,1fr)}}
  @media(max-width:768px){.main{margin-left:0}.page{padding:.9rem}.stats-row{grid-template-columns:1fr 1fr}}
  </style>
</head>
<body>

<aside class="aside">
  <div class="a-logo">
    <div class="a-logo-mark">🌱</div>
    <div><div class="a-logo-name">florescer</div><div class="a-logo-tag">admin</div></div>
  </div>
  <div class="a-who">
    <div class="a-av"><?= $adminLetter ?></div>
    <div><div class="a-name"><?= htmlspecialchars($adminName, ENT_QUOTES) ?></div><div class="a-role">Administrador</div></div>
  </div>
  <nav class="a-nav">
    <span class="a-grp">Visão geral</span>
    <a class="a-link" href="dashboard.php"><span class="a-link-ico">◈</span>Dashboard</a>
    <span class="a-grp">Usuários</span>
    <a class="a-link" href="usuarios.php"><span class="a-link-ico">⊙</span>Usuários</a>
    <a class="a-link active" href="sessoes.php"><span class="a-link-ico">⊘</span>Sessões</a>
    <span class="a-grp">Conteúdo</span>
    <a class="a-link" href="mensagens.php"><span class="a-link-ico">⊡</span>Frases do dia</a>
    <a class="a-link" href="simulados.php"><span class="a-link-ico">⊞</span>Simulados</a>
    <a class="a-link" href="cursos.php"><span class="a-link-ico">⊟</span>Cursos</a>
    <span class="a-grp">Sistema</span>
    <a class="a-link" href="feedbacks.php"><span class="a-link-ico">⊠</span>Feedbacks</a>
    <a class="a-link" href="configuracoes.php"><span class="a-link-ico">⊛</span>Configurações</a>
    <a class="a-link" href="/florescer/public/views/dashboard.php" target="_blank"><span class="a-link-ico">↗</span>Ver plataforma</a>
  </nav>
  <div class="a-foot">
    <button class="a-logout" onclick="doLogout()">↩ Sair</button>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <span class="tb-title">Sessões de Estudo</span>
    <span class="tb-right"><?= date('d/m/Y · H:i') ?> (Recife)</span>
  </header>

  <main class="page">

    <div class="stats-row">
      <div class="stat" style="--stat-accent:var(--leaf)">
        <span class="stat-ico">📅</span>
        <div><div class="stat-val"><?= number_format($totalSessions) ?></div><div class="stat-lbl">Sessões no período</div></div>
      </div>
      <div class="stat" style="--stat-accent:#60a5fa">
        <span class="stat-ico">⏱</span>
        <div><div class="stat-val"><?= fmtMinS($totalMinPeriod) ?></div><div class="stat-lbl">Total estudado</div></div>
      </div>
      <div class="stat" style="--stat-accent:var(--gold)">
        <span class="stat-ico">👥</span>
        <div><div class="stat-val"><?= $uniqueUsers ?></div><div class="stat-lbl">Estudantes ativos</div></div>
      </div>
      <div class="stat" style="--stat-accent:#a78bfa">
        <span class="stat-ico">🎯</span>
        <div><div class="stat-val"><?= $goalsHit ?></div><div class="stat-lbl">Metas atingidas</div></div>
      </div>
    </div>

    <!-- Gráfico 14 dias -->
    <div class="chart-card">
      <div class="chart-head">
        <span class="chart-title">Atividade — últimos 14 dias</span>
        <span class="chart-sub">Horário de Recife (BRT)</span>
      </div>
      <div class="chart-bars">
        <?php for ($i = 13; $i >= 0; $i--):
          $d      = date('Y-m-d', strtotime("-{$i} days"));
          $min    = (int)($daily14Map[$d]['total_min']    ?? 0);
          $users  = (int)($daily14Map[$d]['active_users'] ?? 0);
          $pct    = $maxMin14 > 0 ? max(4, round($min/$maxMin14*88)) : 4;
          $isToday= ($d === date('Y-m-d'));
          $tip    = date('d/m',strtotime($d)).' · '.fmtMinS($min).' · '.$users.' aluno(s)';
        ?>
        <div class="cb-wrap">
          <div class="cb <?= $isToday?'today':'' ?>" style="height:<?= $pct ?>px" title="<?= $tip ?>"></div>
          <div class="cb-lbl <?= $isToday?'today':'' ?>"><?= fmtDateFullS($d) ?></div>
          <div class="cb-n"><?= $users ?: '' ?></div>
        </div>
        <?php endfor; ?>
      </div>
    </div>

    <!-- Filtros -->
    <div class="filter-card">
      <form method="GET" class="filter-row">
        <div class="fg">
          <span class="fl">Data início</span>
          <input class="fc" type="date" name="from" value="<?= $dateFrom ?>" max="<?= date('Y-m-d') ?>"/>
        </div>
        <div class="fg">
          <span class="fl">Data fim</span>
          <input class="fc" type="date" name="to" value="<?= $dateTo ?>" max="<?= date('Y-m-d') ?>"/>
        </div>
        <div class="fg" style="flex:1">
          <span class="fl">Buscar usuário</span>
          <div class="search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input class="fc" type="text" name="q" value="<?= htmlspecialchars($search,ENT_QUOTES) ?>" placeholder="Nome ou e-mail…"/>
          </div>
        </div>
        <button class="btn-primary" type="submit">Filtrar</button>
        <?php if ($search || $dateFrom !== date('Y-m-d',strtotime('-6 days')) || $dateTo !== date('Y-m-d')): ?>
          <a class="btn-ghost" href="sessoes.php">✕ Limpar</a>
        <?php endif; ?>
      </form>
      <div class="presets">
        <?php
        $presets = [
          'Hoje'        => [date('Y-m-d'), date('Y-m-d')],
          'Ontem'       => [date('Y-m-d',strtotime('-1 day')), date('Y-m-d',strtotime('-1 day'))],
          '7 dias'      => [date('Y-m-d',strtotime('-6 days')), date('Y-m-d')],
          '30 dias'     => [date('Y-m-d',strtotime('-29 days')), date('Y-m-d')],
          'Este mês'    => [date('Y-m-01'), date('Y-m-d')],
          'Mês passado' => [date('Y-m-01',strtotime('first day of last month')), date('Y-m-t',strtotime('last day of last month'))],
        ];
        foreach ($presets as $label => [$f,$t]):
          $active = ($dateFrom===$f && $dateTo===$t && !$search) ? 'active' : '';
        ?>
        <a class="preset <?= $active ?>" href="?from=<?= $f ?>&to=<?= $t ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Tabela -->
    <div class="tbl-card">
      <div class="tbl-head">
        <span class="tbl-head-title">Sessões encontradas</span>
        <span class="tbl-head-meta"><?= number_format($totalRows) ?> resultado(s)</span>
      </div>
      <table class="tbl">
        <thead>
          <tr>
            <th>Data</th>
            <th>Estudante</th>
            <th>Tempo estudado</th>
            <th>Meta</th>
            <th>Streak</th>
            <th>Nível</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($sessions)): ?>
            <tr><td colspan="6"><div class="empty-row">Nenhuma sessão encontrada para o período selecionado.</div></td></tr>
          <?php endif; ?>
          <?php
          $maxMinTable = 1;
          foreach ($sessions as $s) { if ((int)$s['total_min'] > $maxMinTable) $maxMinTable = (int)$s['total_min']; }
          $DOWS_PT = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
          foreach ($sessions as $s):
            $ini     = strtoupper(mb_substr($s['user_name'], 0, 1, 'UTF-8'));
            $pct     = min(100, round((int)$s['total_min'] / $maxMinTable * 100));
            $isToday = ($s['study_date'] === date('Y-m-d'));
            $dow     = $DOWS_PT[(int)date('w', strtotime($s['study_date']))];
          ?>
          <tr>
            <td>
              <div class="date-badge">
                <div class="date-day" style="<?= $isToday?'color:var(--leaf)':'' ?>"><?= fmtDateS($s['study_date']) ?></div>
                <div class="date-dow"><?= $dow ?></div>
              </div>
            </td>
            <td>
              <div class="td-user">
                <div class="td-av"><?= $ini ?></div>
                <div>
                  <div class="td-name"><?= htmlspecialchars($s['user_name'],ENT_QUOTES) ?></div>
                  <div class="td-email"><?= htmlspecialchars($s['user_email'],ENT_QUOTES) ?></div>
                </div>
              </div>
            </td>
            <td>
              <div class="time-bar-wrap">
                <div class="time-bar"><div class="time-bar-fill" style="width:<?= $pct ?>%"></div></div>
                <span class="time-val"><?= fmtMinS((int)$s['total_min']) ?></span>
              </div>
            </td>
            <td>
              <?php if ($s['goal_reached']): ?>
                <span class="goal-badge yes">🎯 Atingida</span>
              <?php else: ?>
                <span class="goal-badge no">— Não</span>
              <?php endif; ?>
            </td>
            <td><span class="chip">🌱 <?= $s['streak'] ?>d</span></td>
            <td><span class="chip">Nv.<?= $s['level'] ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <a class="pg <?= $page<=1?'off':'' ?>" href="<?= pgUrlS($page-1,$dateFrom,$dateTo,$search) ?>">‹</a>
        <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
          <a class="pg <?= $p===$page?'cur':'' ?>" href="<?= pgUrlS($p,$dateFrom,$dateTo,$search) ?>"><?= $p ?></a>
        <?php endfor; ?>
        <a class="pg <?= $page>=$totalPages?'off':'' ?>" href="<?= pgUrlS($page+1,$dateFrom,$dateTo,$search) ?>">›</a>
        <span style="font-size:.63rem;color:var(--text3);margin:0 .3rem"><?= $page ?>/<?= $totalPages ?></span>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>

<div id="toasts"></div>
<script>
function doLogout(){
  fetch('../api/auth_admin.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'logout'})})
    .finally(()=>{window.location.href='/florescer/index.php';});
}
</script>
</body>
</html>