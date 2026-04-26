<?php
// ============================================================
// /admin/views/usuarios.php — florescer Admin v3.0
// Gerenciamento de usuários
// ============================================================
require_once __DIR__ . '/../includes/auth_admin.php';
require_once dirname(dirname(__DIR__)) . '/config/db.php';

requireAdmin();

$adminName   = $_SESSION['admin_name'] ?? 'Admin';
$adminLetter = strtoupper(mb_substr($adminName, 0, 1, 'UTF-8'));

// ── Filtros ───────────────────────────────────────────────────
$search  = mb_substr(trim($_GET['q']     ?? ''), 0, 100, 'UTF-8');
$orderBy = $_GET['order'] ?? 'created_at';
$dir     = $_GET['dir']   ?? 'desc';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$validOrders = ['name','email','level','xp','streak','created_at'];
if (!in_array($orderBy, $validOrders)) $orderBy = 'created_at';
$dir = ($dir === 'asc') ? 'asc' : 'desc';

$where  = '';
$params = [];
if ($search !== '') {
    $where    = 'WHERE u.name LIKE ? OR u.email LIKE ?';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$totalRows  = (int)(dbRow("SELECT COUNT(*) AS n FROM users u $where", $params)['n'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$params[] = $perPage;
$params[] = $offset;

$users = dbQuery(
    "SELECT u.id, u.name, u.email, u.level, u.xp, u.streak,
            u.daily_goal_min, u.created_at, u.avatar_emoji, u.avatar_initial,
            (SELECT COUNT(*) FROM daily_summaries ds WHERE ds.user_id=u.id AND ds.total_min>0) AS days_studied,
            (SELECT COUNT(*) FROM objectives o WHERE o.user_id=u.id) AS total_objectives
     FROM users u $where
     ORDER BY u.{$orderBy} {$dir}
     LIMIT ? OFFSET ?",
    $params
);

$totalUsers  = (int)(dbRow('SELECT COUNT(*) AS n FROM users')['n'] ?? 0);
$activeToday = (int)(dbRow('SELECT COUNT(DISTINCT user_id) AS n FROM daily_summaries WHERE study_date=CURDATE()')['n'] ?? 0);
$newThisWeek = (int)(dbRow('SELECT COUNT(*) AS n FROM users WHERE created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)')['n'] ?? 0);

function timeAgoU(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'agora';
    if ($diff < 3600)   return intdiv($diff, 60) . 'min atrás';
    if ($diff < 86400)  return intdiv($diff, 3600) . 'h atrás';
    if ($diff < 604800) return intdiv($diff, 86400) . 'd atrás';
    return date('d/m/Y', strtotime($dt));
}
function sortUrlU(string $col, string $cur, string $curDir, string $q): string {
    $d = ($col === $cur && $curDir === 'asc') ? 'desc' : 'asc';
    return '?' . http_build_query(array_filter(['order'=>$col,'dir'=>$d,'q'=>$q?:null]));
}
function sortIcoU(string $col, string $cur, string $dir): string {
    if ($col !== $cur) return '<span style="opacity:.2">↕</span>';
    return $dir === 'asc' ? '↑' : '↓';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>Usuários — florescer Admin</title>
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
  .tb-right{display:flex;align-items:center;gap:.6rem}
  .tb-date{font-size:.67rem;color:var(--text3)}

  .page{padding:1.25rem 1.5rem;display:flex;flex-direction:column;gap:1rem;flex:1}

  /* ── Stats ──────────────────────────────────────────────── */
  .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:.65rem}
  .stat{background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);padding:.9rem 1rem;display:flex;align-items:center;gap:.7rem;transition:border-color var(--t),transform var(--t);position:relative;overflow:hidden}
  .stat::before{content:'';position:absolute;bottom:0;left:0;right:0;height:1px;background:var(--stat-accent,var(--leaf));opacity:.4}
  .stat:hover{border-color:var(--border);transform:translateY(-1px)}
  .stat-ico{font-size:1.2rem;flex-shrink:0;opacity:.65}
  .stat-val{font-family:var(--serif);font-size:1.4rem;color:var(--leaf3);line-height:1}
  .stat-lbl{font-size:.6rem;color:var(--text3);margin-top:.1rem;text-transform:uppercase;letter-spacing:.05em}

  /* ── Toolbar ─────────────────────────────────────────────── */
  .toolbar{display:flex;align-items:center;gap:.55rem;flex-wrap:wrap}
  .search-wrap{position:relative;flex:1;max-width:340px}
  .search-wrap svg{position:absolute;left:.65rem;top:50%;transform:translateY(-50%);width:13px;height:13px;color:var(--muted);pointer-events:none}
  .search-wrap .inp{width:100%;padding-left:2rem}
  .inp{padding:.46rem .75rem;background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r2);color:var(--text);font-family:var(--sans);font-size:.76rem;outline:none;transition:all var(--t)}
  .inp:focus{border-color:var(--leaf);background:rgba(82,183,136,.05)}
  .inp::placeholder{color:var(--text3)}
  .btn-primary{padding:.46rem .9rem;background:var(--leaf);border:none;border-radius:var(--r2);color:#0b1a12;font-family:var(--sans);font-size:.75rem;font-weight:600;cursor:pointer;transition:all var(--t)}
  .btn-primary:hover{background:var(--leaf2);transform:translateY(-1px)}
  .clear-link{font-size:.7rem;color:var(--text3);text-decoration:none;transition:color var(--t)}
  .clear-link:hover{color:var(--leaf)}
  .result-count{font-size:.67rem;color:var(--text3);margin-left:auto}

  /* ── Tabela ──────────────────────────────────────────────── */
  .tbl-card{background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);overflow:hidden}
  .tbl-head{padding:.65rem 1rem;border-bottom:1px solid var(--border2);display:flex;align-items:center;justify-content:space-between}
  .tbl-head-title{font-size:.72rem;font-weight:500;color:var(--text2)}
  .tbl-head-meta{font-size:.63rem;color:var(--text3)}
  .tbl{width:100%;border-collapse:collapse}
  .tbl th{font-size:.57rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted2);font-weight:500;padding:.45rem 1rem;text-align:left;border-bottom:1px solid var(--border2)}
  .tbl th a{color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:.25rem;transition:color var(--t)}
  .tbl th a:hover{color:var(--leaf2)}
  .tbl td{padding:.5rem 1rem;font-size:.75rem;color:var(--text2);border-bottom:1px solid var(--border2)}
  .tbl tr:last-child td{border-bottom:none}
  .tbl tr:hover td{background:rgba(82,183,136,.025)}
  .td-user{display:flex;align-items:center;gap:.6rem}
  .td-av{width:28px;height:28px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,var(--leaf),#2d6a4f);display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:600;color:#fff}
  .td-name{font-weight:500;color:var(--text)}
  .td-email{font-size:.67rem;color:var(--text3);margin-top:.05rem}
  .chip{display:inline-block;padding:.08rem .38rem;border-radius:20px;font-size:.62rem;font-weight:500;background:rgba(82,183,136,.08);color:var(--leaf2);border:1px solid var(--border)}
  .chip.streak{background:rgba(82,183,136,.07);color:var(--leaf2);border-color:rgba(82,183,136,.15)}
  .tbl-actions{display:flex;gap:.28rem}
  .act{padding:.26rem .5rem;border-radius:var(--r2);border:1px solid var(--border2);background:none;color:var(--text3);font-size:.72rem;cursor:pointer;transition:all var(--t)}
  .act:hover{border-color:var(--leaf);color:var(--leaf2);background:rgba(82,183,136,.05)}
  .act.del:hover{border-color:rgba(224,82,82,.25);color:var(--red);background:rgba(224,82,82,.05)}

  /* ── Paginação ──────────────────────────────────────────── */
  .pagination{display:flex;align-items:center;justify-content:center;gap:.3rem;padding:.65rem;border-top:1px solid var(--border2)}
  .pg{padding:.26rem .58rem;border-radius:var(--r2);border:1px solid var(--border2);background:none;color:var(--text3);font-size:.71rem;cursor:pointer;text-decoration:none;transition:all var(--t)}
  .pg:hover{border-color:var(--leaf);color:var(--leaf2)}
  .pg.cur{background:var(--leaf);border-color:var(--leaf);color:#0b1a12;font-weight:600}
  .pg.off{opacity:.2;pointer-events:none}
  .pg-info{font-size:.63rem;color:var(--text3);margin:0 .3rem}
  .empty-row{padding:2.5rem;text-align:center;color:var(--text3);font-size:.75rem}

  /* ── Modais ──────────────────────────────────────────────── */
  .overlay{position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.7);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;padding:1rem;opacity:0;pointer-events:none;transition:opacity var(--t)}
  .overlay.open{opacity:1;pointer-events:all}
  .modal{background:var(--ink2);border:1px solid var(--border);border-radius:var(--r);width:100%;max-width:460px;max-height:92vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,.6);transform:translateY(12px) scale(.97);transition:transform var(--t)}
  .overlay.open .modal{transform:none}
  .modal-sm .modal{max-width:380px}
  .modal-head{padding:.75rem 1rem;border-bottom:1px solid var(--border2);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--ink2);z-index:1;border-radius:var(--r) var(--r) 0 0}
  .modal-title{font-family:var(--serif);font-size:.9rem;color:var(--leaf3)}
  .modal-x{width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,.04);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.72rem;color:var(--text3);transition:all var(--t)}
  .modal-x:hover{background:rgba(224,82,82,.15);color:var(--red)}
  .modal-body{padding:1.1rem}
  .modal-foot{padding:.7rem 1rem;border-top:1px solid var(--border2);display:flex;gap:.4rem;justify-content:flex-end}
  .fg{margin-bottom:.75rem}
  .fl{display:block;font-size:.68rem;font-weight:500;color:var(--muted);letter-spacing:.03em;margin-bottom:.25rem}
  .fc{width:100%;padding:.54rem .75rem;background:rgba(255,255,255,.03);border:1px solid var(--border2);border-radius:var(--r2);color:var(--text);font-family:var(--sans);font-size:.81rem;outline:none;transition:all var(--t)}
  .fc:focus{border-color:var(--leaf);background:rgba(82,183,136,.05);box-shadow:0 0 0 3px rgba(82,183,136,.1)}
  .fc::placeholder{color:var(--text3)}
  .frow{display:grid;grid-template-columns:1fr 1fr;gap:.65rem}
  .info-box{background:rgba(82,183,136,.04);border:1px solid var(--border2);border-radius:var(--r2);padding:.65rem .85rem;margin-bottom:.85rem}
  .info-box strong{display:block;font-size:.78rem;color:var(--leaf2);margin-bottom:.18rem}
  .info-box p{font-size:.73rem;color:var(--text3);line-height:1.5}
  .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:.75rem}
  .info-card{background:rgba(255,255,255,.02);border:1px solid var(--border2);border-radius:var(--r2);padding:.55rem .7rem}
  .info-card-lbl{font-size:.6rem;color:var(--muted);margin-bottom:.12rem}
  .info-card-val{font-family:var(--serif);font-size:1rem;color:var(--leaf3)}
  .btn-ghost{padding:.5rem 1rem;background:none;border:1px solid var(--border2);border-radius:var(--r2);color:var(--text3);font-family:var(--sans);font-size:.77rem;cursor:pointer;transition:all var(--t)}
  .btn-ghost:hover{border-color:var(--border);color:var(--text2)}
  .btn-danger{padding:.5rem 1rem;background:rgba(224,82,82,.1);border:1px solid rgba(224,82,82,.2);border-radius:var(--r2);color:var(--red);font-family:var(--sans);font-size:.77rem;cursor:pointer;transition:all var(--t)}
  .btn-danger:hover{background:rgba(224,82,82,.18)}
  .btn-danger:disabled{opacity:.5;pointer-events:none}
  .del-warn{font-size:.75rem;color:var(--text3);line-height:1.6}
  .del-warn strong{color:var(--red)}

  /* Toast */
  #toasts{position:fixed;bottom:1rem;right:1rem;z-index:999;display:flex;flex-direction:column;gap:.3rem;pointer-events:none}
  .toast{background:var(--ink2);color:var(--text);border:1px solid var(--border);border-radius:var(--r2);padding:.48rem .8rem;font-size:.71rem;display:flex;align-items:center;gap:.38rem;animation:slideIn .2s ease both;max-width:270px;pointer-events:all;box-shadow:0 4px 20px rgba(0,0,0,.4)}
  .toast.ok{border-left:2px solid var(--leaf)}.toast.err{border-left:2px solid var(--red)}
  @keyframes slideIn{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:none}}

  @media(max-width:900px){.stats-row{grid-template-columns:1fr 1fr}.frow{grid-template-columns:1fr}}
  @media(max-width:768px){.main{margin-left:0}.page{padding:.9rem}.stats-row{grid-template-columns:1fr}}
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
    <a class="a-link active" href="usuarios.php"><span class="a-link-ico">⊙</span>Usuários</a>
    <a class="a-link" href="sessoes.php"><span class="a-link-ico">⊘</span>Sessões</a>
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
    <span class="tb-title">Usuários</span>
    <div class="tb-right">
      <span class="tb-date"><?= date('d/m/Y · H:i') ?></span>
    </div>
  </header>

  <main class="page">

    <div class="stats-row">
      <div class="stat" style="--stat-accent:var(--leaf)">
        <span class="stat-ico">🌱</span>
        <div><div class="stat-val"><?= number_format($totalUsers) ?></div><div class="stat-lbl">Total de estudantes</div></div>
      </div>
      <div class="stat" style="--stat-accent:#60a5fa">
        <span class="stat-ico">⚡</span>
        <div><div class="stat-val"><?= $activeToday ?></div><div class="stat-lbl">Ativos hoje</div></div>
      </div>
      <div class="stat" style="--stat-accent:var(--gold)">
        <span class="stat-ico">🆕</span>
        <div><div class="stat-val"><?= $newThisWeek ?></div><div class="stat-lbl">Novos esta semana</div></div>
      </div>
    </div>

    <form method="GET" class="toolbar">
      <div class="search-wrap">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input class="inp" type="text" name="q" placeholder="Buscar por nome ou e-mail…" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>" maxlength="100"/>
      </div>
      <input type="hidden" name="order" value="<?= $orderBy ?>"/>
      <input type="hidden" name="dir" value="<?= $dir ?>"/>
      <button class="btn-primary" type="submit">Buscar</button>
      <?php if ($search): ?>
        <a class="clear-link" href="usuarios.php">✕ Limpar</a>
      <?php endif; ?>
      <span class="result-count"><?= number_format($totalRows) ?> usuário(s)</span>
    </form>

    <div class="tbl-card">
      <div class="tbl-head">
        <span class="tbl-head-title">Todos os usuários</span>
        <span class="tbl-head-meta">Pág. <?= $page ?>/<?= $totalPages ?></span>
      </div>
      <table class="tbl">
        <thead>
          <tr>
            <th style="width:35%"><a href="<?= sortUrlU('name',$orderBy,$dir,$search) ?>">Nome <?= sortIcoU('name',$orderBy,$dir) ?></a></th>
            <th><a href="<?= sortUrlU('level',$orderBy,$dir,$search) ?>">Nível <?= sortIcoU('level',$orderBy,$dir) ?></a></th>
            <th><a href="<?= sortUrlU('xp',$orderBy,$dir,$search) ?>">XP <?= sortIcoU('xp',$orderBy,$dir) ?></a></th>
            <th><a href="<?= sortUrlU('streak',$orderBy,$dir,$search) ?>">Streak <?= sortIcoU('streak',$orderBy,$dir) ?></a></th>
            <th>Dias</th>
            <th><a href="<?= sortUrlU('created_at',$orderBy,$dir,$search) ?>">Cadastro <?= sortIcoU('created_at',$orderBy,$dir) ?></a></th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr><td colspan="7"><div class="empty-row"><?= $search ? 'Nenhum resultado para "'.htmlspecialchars($search,ENT_QUOTES).'".' : 'Nenhum usuário cadastrado.' ?></div></td></tr>
          <?php endif; ?>
          <?php foreach ($users as $u):
            $ini = strtoupper(mb_substr($u['name'], 0, 1, 'UTF-8'));
            // Serializa os dados de cada usuário em JSON para uso seguro nos onclick
            $dataDetail = json_encode([
              'id'       => (int)$u['id'],
              'name'     => $u['name'],
              'email'    => $u['email'],
              'level'    => (int)$u['level'],
              'xp'       => (int)$u['xp'],
              'streak'   => (int)$u['streak'],
              'days'     => (int)$u['days_studied'],
              'goals'    => (int)$u['total_objectives'],
              'goal_min' => (int)$u['daily_goal_min'],
              'since'    => $u['created_at'],
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS);

            $dataPass = json_encode([
              'id'   => (int)$u['id'],
              'name' => $u['name'],
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS);

            $dataDel = json_encode([
              'id'   => (int)$u['id'],
              'name' => $u['name'],
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS);
          ?>
          <tr id="row-<?= $u['id'] ?>">
            <td>
              <div class="td-user">
                <div class="td-av"><?= !empty($u['avatar_emoji']) ? htmlspecialchars($u['avatar_emoji'],ENT_QUOTES) : $ini ?></div>
                <div>
                  <div class="td-name"><?= htmlspecialchars($u['name'], ENT_QUOTES) ?></div>
                  <div class="td-email"><?= htmlspecialchars($u['email'], ENT_QUOTES) ?></div>
                </div>
              </div>
            </td>
            <td><span class="chip">Nv.<?= $u['level'] ?></span></td>
            <td><?= number_format($u['xp']) ?></td>
            <td><span class="chip streak">🌱 <?= $u['streak'] ?>d</span></td>
            <td><?= $u['days_studied'] ?></td>
            <td style="font-size:.67rem"><?= timeAgoU($u['created_at']) ?></td>
            <td>
              <div class="tbl-actions">
                <button class="act" title="Ver detalhes"
                  onclick='openDetail(<?= $dataDetail ?>)'>👁</button>
                <button class="act" title="Resetar senha"
                  onclick='openResetPass(<?= $dataPass ?>)'>🔑</button>
                <button class="act del" title="Excluir"
                  onclick='confirmDelete(<?= $dataDel ?>)'>🗑</button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php
        function pgUrlU(int $p, string $order, string $dir, string $q): string {
            return '?' . http_build_query(array_filter(['page'=>$p>1?$p:null,'order'=>$order,'dir'=>$dir,'q'=>$q?:null]));
        }
        ?>
        <a class="pg <?= $page<=1?'off':'' ?>" href="<?= pgUrlU($page-1,$orderBy,$dir,$search) ?>">‹</a>
        <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
          <a class="pg <?= $p===$page?'cur':'' ?>" href="<?= pgUrlU($p,$orderBy,$dir,$search) ?>"><?= $p ?></a>
        <?php endfor; ?>
        <a class="pg <?= $page>=$totalPages?'off':'' ?>" href="<?= pgUrlU($page+1,$orderBy,$dir,$search) ?>">›</a>
        <span class="pg-info"><?= $page ?>/<?= $totalPages ?></span>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>

<!-- Modal: Detalhes -->
<div class="overlay" id="overlayDetail">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title">Detalhes do usuário</span>
      <button class="modal-x" onclick="closeOverlay('overlayDetail')">✕</button>
    </div>
    <div class="modal-body" id="detailBody"></div>
    <div class="modal-foot">
      <button class="btn-ghost" onclick="closeOverlay('overlayDetail')">Fechar</button>
    </div>
  </div>
</div>

<!-- Modal: Resetar senha -->
<div class="overlay" id="overlayPass">
  <div class="modal modal-sm">
    <div class="modal-head">
      <span class="modal-title">Resetar senha</span>
      <button class="modal-x" onclick="closeOverlay('overlayPass')">✕</button>
    </div>
    <div class="modal-body">
      <div class="info-box" id="passInfo"></div>
      <div class="fg">
        <label class="fl" for="newPass">Nova senha</label>
        <input class="fc" type="password" id="newPass" placeholder="Mínimo 6 caracteres"/>
      </div>
      <div class="fg">
        <label class="fl" for="newPassConf">Confirmar senha</label>
        <input class="fc" type="password" id="newPassConf" placeholder="Repita a senha"/>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-ghost" onclick="closeOverlay('overlayPass')">Cancelar</button>
      <button class="btn-primary" onclick="submitResetPass()">Salvar senha</button>
    </div>
  </div>
</div>

<!-- Modal: Excluir -->
<div class="overlay modal-sm" id="overlayDel">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title">Excluir usuário</span>
      <button class="modal-x" onclick="closeOverlay('overlayDel')">✕</button>
    </div>
    <div class="modal-body">
      <div class="info-box" id="delInfo"></div>
      <p class="del-warn">Esta ação é <strong>irreversível</strong>. Todos os dados do usuário (objetivos, sessões, histórico, conquistas) serão permanentemente excluídos.</p>
    </div>
    <div class="modal-foot">
      <button class="btn-ghost" onclick="closeOverlay('overlayDel')">Cancelar</button>
      <button class="btn-danger" id="btnDel" onclick="submitDelete()">Excluir permanentemente</button>
    </div>
  </div>
</div>

<div id="toasts"></div>

<script>
const API = '/florescer/admin/api/usuarios.php';

function toast(msg, type='ok', ms=3500){
  const w=document.getElementById('toasts'), d=document.createElement('div');
  d.className=`toast ${type}`;
  d.innerHTML=`<span>${type==='ok'?'✓':'✕'}</span><span>${msg}</span>`;
  w.appendChild(d);
  setTimeout(()=>{d.style.opacity='0';d.style.transition='.25s';setTimeout(()=>d.remove(),260);},ms);
}
function openOverlay(id){document.getElementById(id).classList.add('open');document.body.style.overflow='hidden';}
function closeOverlay(id){document.getElementById(id).classList.remove('open');document.body.style.overflow='';}
document.querySelectorAll('.overlay').forEach(o=>o.addEventListener('click',e=>{if(e.target===o)closeOverlay(o.id);}));
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.overlay.open').forEach(o=>closeOverlay(o.id));});

function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

/* ── Detalhes ─────────────────────────────────────────────── */
function openDetail(u){
  const since = new Date(u.since).toLocaleDateString('pt-BR',{day:'2-digit',month:'long',year:'numeric'});
  document.getElementById('detailBody').innerHTML = `
    <div class="info-box"><strong>${esc(u.name)}</strong><p>${esc(u.email)}</p></div>
    <div class="info-grid">
      ${ic('⭐','Nível','Nv. '+u.level)}
      ${ic('🔥','XP Total',u.xp.toLocaleString('pt-BR'))}
      ${ic('🌱','Streak',u.streak+' dias')}
      ${ic('📅','Dias estudados',u.days+' dias')}
      ${ic('🎯','Objetivos',u.goals)}
      ${ic('⏱','Meta diária',u.goal_min+'min')}
    </div>
    <div style="font-size:.68rem;color:var(--text3)">Cadastrado em: ${since}</div>`;
  openOverlay('overlayDetail');
}
function ic(ico,lbl,val){
  return `<div class="info-card"><div class="info-card-lbl">${ico} ${lbl}</div><div class="info-card-val">${val}</div></div>`;
}

/* ── Reset senha ──────────────────────────────────────────── */
let _resetId = null;
// Recebe objeto {id, name} — seguro contra qualquer caractere no nome
function openResetPass(u){
  _resetId = u.id;
  document.getElementById('passInfo').innerHTML = `<strong>${esc(u.name)}</strong><p>Defina uma nova senha para este usuário.</p>`;
  document.getElementById('newPass').value='';
  document.getElementById('newPassConf').value='';
  openOverlay('overlayPass');
  setTimeout(()=>document.getElementById('newPass').focus(),150);
}
async function submitResetPass(){
  const p1=document.getElementById('newPass').value;
  const p2=document.getElementById('newPassConf').value;
  if(!p1||p1.length<6){toast('Senha deve ter pelo menos 6 caracteres.','err');return;}
  if(p1!==p2){toast('As senhas não coincidem.','err');return;}
  try{
    const r=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'reset_password',user_id:_resetId,password:p1})});
    const d=await r.json();
    if(d.success){toast('Senha alterada com sucesso!');closeOverlay('overlayPass');}
    else toast(d.message||'Erro ao alterar senha.','err');
  }catch{toast('Erro de conexão.','err');}
}

/* ── Excluir ──────────────────────────────────────────────── */
let _deleteId = null;
// Recebe objeto {id, name} — seguro contra qualquer caractere no nome
function confirmDelete(u){
  _deleteId = u.id;
  document.getElementById('delInfo').innerHTML = `<strong>${esc(u.name)}</strong><p>Tem certeza que deseja excluir este usuário?</p>`;
  openOverlay('overlayDel');
}
async function submitDelete(){
  if(!_deleteId) return;
  const btn=document.getElementById('btnDel');
  btn.disabled=true;btn.textContent='Excluindo…';
  try{
    const r=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete_user',user_id:_deleteId})});
    const d=await r.json();
    if(d.success){
      toast('Usuário excluído.');closeOverlay('overlayDel');
      const row=document.getElementById('row-'+_deleteId);
      if(row){row.style.opacity='0';row.style.transition='.3s';setTimeout(()=>row.remove(),320);}
    }else toast(d.message||'Erro ao excluir.','err');
  }catch{toast('Erro de conexão.','err');}
  finally{btn.disabled=false;btn.textContent='Excluir permanentemente';}
}

function doLogout(){
  fetch('../api/auth_admin.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'logout'})})
    .finally(()=>{window.location.href='/florescer/index.php';});
}
</script>
</body>
</html>