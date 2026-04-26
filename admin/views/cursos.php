<?php
// ============================================================
// /admin/views/cursos.php — florescer Admin v3.0
// ============================================================
require_once __DIR__ . '/../includes/auth_admin.php';
require_once dirname(dirname(__DIR__)) . '/config/db.php';

requireAdmin();

$adminName   = $_SESSION['admin_name'] ?? 'Admin';
$adminLetter = strtoupper(mb_substr($adminName, 0, 1, 'UTF-8'));

// ── Verificação da tabela ──────────────────────────────────────
$tableOk = (bool)dbRow("SHOW TABLES LIKE 'store_items'");

if ($tableOk) {
    // Filtros (whitelist de parâmetros)
    $search  = mb_substr(trim($_GET['q']     ?? ''), 0, 100, 'UTF-8');
    $gradeF  = mb_substr(trim($_GET['grade'] ?? ''), 0, 80,  'UTF-8');
    $badgeF  = in_array($_GET['badge'] ?? '', ['', 'novo', 'popular'], true)
               ? ($_GET['badge'] ?? '') : '';
    $statusF = in_array($_GET['status'] ?? '', ['', '1', '0'], true)
               ? ($_GET['status'] ?? '') : '';

    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 12;

    // Query dinâmica com prepared statements
    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[]  = '(title LIKE ? OR description LIKE ? OR category LIKE ?)';
        $term     = '%' . $search . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }
    if ($gradeF !== '')  { $where[] = 'grade_level = ?'; $params[] = $gradeF; }
    if ($badgeF !== '')  { $where[] = 'badge = ?';       $params[] = $badgeF; }
    if ($statusF !== '') { $where[] = 'is_active = ?';   $params[] = (int)$statusF; }

    $wClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $totalRows  = (int)(dbRow("SELECT COUNT(*) AS n FROM store_items $wClause", $params)['n'] ?? 0);
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    $items = dbQuery(
        "SELECT * FROM store_items $wClause ORDER BY sort_order ASC, id DESC LIMIT ? OFFSET ?",
        [...$params, $perPage, $offset]
    );

    // Stats
    $totalActive   = (int)(dbRow('SELECT COUNT(*) AS n FROM store_items WHERE is_active = 1')['n']  ?? 0);
    $totalInactive = (int)(dbRow('SELECT COUNT(*) AS n FROM store_items WHERE is_active = 0')['n']  ?? 0);
    $totalPopular  = (int)(dbRow("SELECT COUNT(*) AS n FROM store_items WHERE badge = 'popular'")['n'] ?? 0);

    // Grades para filtro
    $grades = dbQuery(
        "SELECT DISTINCT grade_level FROM store_items
         WHERE grade_level IS NOT NULL AND grade_level != ''
         ORDER BY grade_level ASC"
    );
}

function pgUrl(int $p, string $q, string $g, string $b, string $s): string {
    $qs = http_build_query(array_filter([
        'page'   => $p > 1 ? $p : null,
        'q'      => $q ?: null,
        'grade'  => $g ?: null,
        'badge'  => $b ?: null,
        'status' => $s ?: null,
    ]));
    return 'cursos.php' . ($qs ? '?' . $qs : '');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>Cursos — florescer Admin</title>
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

  /* ── Sidebar (igual ao dashboard) ────────────────────────── */
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
  .btn-new{padding:.38rem .9rem;background:var(--leaf);border:none;border-radius:var(--r2);color:#0b1a12;font-family:var(--sans);font-size:.75rem;font-weight:600;cursor:pointer;transition:all var(--t);display:flex;align-items:center;gap:.3rem}
  .btn-new:hover{background:var(--leaf2);transform:translateY(-1px)}

  .page{padding:1.25rem 1.5rem;display:flex;flex-direction:column;gap:1rem;flex:1}

  /* ── Stats ──────────────────────────────────────────────── */
  .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:.65rem}
  .stat{background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);padding:.8rem 1rem;display:flex;align-items:center;gap:.7rem;transition:border-color var(--t)}
  .stat:hover{border-color:var(--border)}
  .stat-ico{font-size:1.2rem;flex-shrink:0;opacity:.65}
  .stat-val{font-family:var(--serif);font-size:1.25rem;color:var(--leaf3);line-height:1}
  .stat-lbl{font-size:.6rem;color:var(--text3);margin-top:.1rem}

  /* ── Filtros ─────────────────────────────────────────────── */
  .filters{display:flex;align-items:center;gap:.55rem;flex-wrap:wrap}
  .inp{padding:.46rem .75rem;background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r2);color:var(--text);font-family:var(--sans);font-size:.76rem;outline:none;transition:all var(--t);-webkit-appearance:none;appearance:none}
  .inp:focus{border-color:var(--leaf);background:rgba(82,183,136,.05)}
  .inp::placeholder{color:var(--text3)}
  .inp option{background:var(--ink2)}
  .inp-search{flex:1;min-width:160px;max-width:260px;padding-left:2rem;position:relative}
  .search-wrap{position:relative;flex:1;min-width:160px;max-width:260px}
  .search-wrap svg{position:absolute;left:.65rem;top:50%;transform:translateY(-50%);width:13px;height:13px;color:var(--muted);pointer-events:none}
  .search-wrap .inp{width:100%;padding-left:2rem}
  .btn-filter{padding:.46rem .9rem;background:var(--leaf);border:none;border-radius:var(--r2);color:#0b1a12;font-family:var(--sans);font-size:.75rem;font-weight:600;cursor:pointer;transition:all var(--t)}
  .btn-filter:hover{background:var(--leaf2)}
  .clear-link{font-size:.7rem;color:var(--text3);text-decoration:none;transition:color var(--t)}
  .clear-link:hover{color:var(--leaf)}
  .result-count{font-size:.67rem;color:var(--text3);margin-left:auto}

  /* ── Grid de cards ──────────────────────────────────────── */
  .cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(268px,1fr));gap:.85rem}

  .course-card{background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);overflow:hidden;position:relative;transition:border-color var(--t),transform var(--t)}
  .course-card:hover{border-color:var(--border);transform:translateY(-2px)}
  .course-card.inactive{opacity:.45}
  .course-card.inactive:hover{opacity:.65}

  /* Thumb */
  .thumb{width:100%;height:130px;background:var(--ink3);display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;flex-shrink:0}
  .thumb img{width:100%;height:100%;object-fit:cover;display:block}
  .thumb-placeholder{font-size:2.2rem;opacity:.2}

  /* Badge de destaque */
  .course-badge{position:absolute;top:.5rem;left:.55rem;padding:.16rem .5rem;border-radius:20px;font-size:.6rem;font-weight:600;letter-spacing:.04em;pointer-events:none}
  .course-badge.popular{background:var(--gold);color:#3a2800}
  .course-badge.novo{background:var(--leaf);color:#0b1a12}

  /* Toggle de status */
  .toggle-wrap{position:absolute;top:.5rem;right:.55rem}
  .toggle{width:30px;height:17px;border-radius:9px;border:none;cursor:pointer;position:relative;transition:background var(--t);flex-shrink:0}
  .toggle.on{background:rgba(82,183,136,.5)}
  .toggle.off{background:rgba(255,255,255,.1)}
  .toggle::after{content:'';position:absolute;top:2px;width:13px;height:13px;border-radius:50%;background:#fff;transition:left var(--t)}
  .toggle.on::after{left:15px}.toggle.off::after{left:2px}

  /* Corpo */
  .card-body{padding:.8rem .9rem}
  .card-cat{font-size:.6rem;font-weight:600;color:var(--leaf);text-transform:uppercase;letter-spacing:.07em;margin-bottom:.18rem}
  .card-name{font-family:var(--serif);font-size:.9rem;color:var(--leaf3);line-height:1.3;margin-bottom:.25rem;letter-spacing:-.01em}
  .card-grade{font-size:.65rem;color:var(--text3);margin-bottom:.35rem}
  .card-desc{font-size:.71rem;color:var(--text2);line-height:1.55;margin-bottom:.5rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
  .card-price{font-size:.75rem;font-weight:600;color:var(--leaf2);margin-bottom:.5rem}
  .card-url{font-size:.63rem;color:var(--text3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:.6rem;display:block}

  /* Ações do card */
  .card-actions{display:flex;gap:.35rem;padding-top:.55rem;border-top:1px solid var(--border2)}
  .act-btn{flex:1;padding:.34rem .4rem;border-radius:var(--r2);border:1px solid var(--border2);background:none;font-family:var(--sans);font-size:.7rem;color:var(--text3);cursor:pointer;transition:all var(--t);text-align:center;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:.25rem}
  .act-btn:hover{border-color:var(--leaf);color:var(--leaf2);background:rgba(82,183,136,.05)}
  .act-btn.del:hover{border-color:rgba(224,82,82,.3);color:var(--red);background:rgba(224,82,82,.05)}

  /* ── Empty ──────────────────────────────────────────────── */
  .empty{background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);padding:3rem;text-align:center;color:var(--text3);font-size:.78rem}
  .empty-ico{font-size:2rem;margin-bottom:.6rem;opacity:.4;display:block}

  /* ── Paginação ──────────────────────────────────────────── */
  .pagination{display:flex;align-items:center;justify-content:center;gap:.3rem;padding:.5rem 0}
  .pg{padding:.28rem .6rem;border-radius:var(--r2);border:1px solid var(--border2);background:none;color:var(--text3);font-size:.72rem;cursor:pointer;text-decoration:none;transition:all var(--t)}
  .pg:hover{border-color:var(--leaf);color:var(--leaf2)}
  .pg.cur{background:var(--leaf);border-color:var(--leaf);color:#0b1a12;font-weight:600}
  .pg.off{opacity:.2;pointer-events:none}
  .pg-info{font-size:.65rem;color:var(--text3);margin:0 .4rem}

  /* ── Setup notice ───────────────────────────────────────── */
  .setup{background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);padding:1.5rem;max-width:580px;margin:0 auto}
  .setup h3{font-family:var(--serif);font-size:1rem;color:var(--leaf3);margin-bottom:.5rem}
  .setup p{font-size:.77rem;color:var(--text3);line-height:1.6;margin-bottom:.8rem}
  pre.setup-sql{background:rgba(0,0,0,.25);border:1px solid var(--border2);border-radius:var(--r2);padding:.8rem;font-size:.7rem;color:var(--leaf2);overflow-x:auto;line-height:1.6}

  /* ── Modal ──────────────────────────────────────────────── */
  .overlay{position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.7);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;padding:1rem;opacity:0;pointer-events:none;transition:opacity var(--t)}
  .overlay.open{opacity:1;pointer-events:all}
  .modal{background:var(--ink2);border:1px solid var(--border);border-radius:var(--r);width:100%;max-width:540px;max-height:92vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,.6);transform:translateY(12px) scale(.97);transition:transform var(--t)}
  .overlay.open .modal{transform:none}
  .modal-sm .modal{max-width:380px}
  .modal-head{padding:.75rem 1rem;border-bottom:1px solid var(--border2);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--ink2);z-index:1;border-radius:var(--r) var(--r) 0 0}
  .modal-title{font-family:var(--serif);font-size:.9rem;color:var(--leaf3)}
  .modal-x{width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,.04);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.72rem;color:var(--text3);transition:all var(--t)}
  .modal-x:hover{background:rgba(224,82,82,.15);color:var(--red)}
  .modal-body{padding:1.1rem}
  .modal-foot{padding:.7rem 1rem;border-top:1px solid var(--border2);display:flex;gap:.4rem;justify-content:flex-end;position:sticky;bottom:0;background:var(--ink2)}

  /* Formulário */
  .fg{margin-bottom:.75rem}
  .fl{display:block;font-size:.68rem;font-weight:500;color:var(--muted);letter-spacing:.03em;margin-bottom:.25rem}
  .fc{width:100%;padding:.54rem .75rem;background:rgba(255,255,255,.03);border:1px solid var(--border2);border-radius:var(--r2);color:var(--text);font-family:var(--sans);font-size:.81rem;outline:none;transition:all var(--t);-webkit-appearance:none;appearance:none}
  .fc:focus{border-color:var(--leaf);background:rgba(82,183,136,.05);box-shadow:0 0 0 3px rgba(82,183,136,.1)}
  .fc::placeholder{color:var(--text3)}
  textarea.fc{resize:vertical;min-height:68px;line-height:1.5}
  .fc option{background:var(--ink2)}
  .frow{display:grid;grid-template-columns:1fr 1fr;gap:.7rem}

  /* Badge picker */
  .badge-picker{display:flex;gap:.4rem}
  .bp-opt{padding:.3rem .75rem;border-radius:50px;border:1px solid var(--border2);background:none;font-family:var(--sans);font-size:.71rem;color:var(--text3);cursor:pointer;transition:all var(--t)}
  .bp-opt:hover{border-color:var(--border);color:var(--text2)}
  .bp-opt.s-none{border-color:var(--leaf);background:rgba(82,183,136,.1);color:var(--leaf2)}
  .bp-opt.s-novo{background:var(--leaf);border-color:var(--leaf);color:#0b1a12}
  .bp-opt.s-popular{background:var(--gold);border-color:var(--gold);color:#3a2800}

  /* Preview imagem */
  .img-prev{width:100%;height:80px;background:var(--ink3);border:1px solid var(--border2);border-radius:var(--r2);display:flex;align-items:center;justify-content:center;overflow:hidden;margin-top:.35rem}
  .img-prev img{max-width:100%;max-height:100%;object-fit:contain}
  .img-prev-ph{font-size:1.4rem;opacity:.2}

  /* Botões do modal */
  .btn-primary{padding:.5rem 1rem;background:var(--leaf);border:none;border-radius:var(--r2);color:#0b1a12;font-family:var(--sans);font-size:.77rem;font-weight:600;cursor:pointer;transition:all var(--t)}
  .btn-primary:hover{background:var(--leaf2);transform:translateY(-1px)}
  .btn-primary:disabled{opacity:.5;pointer-events:none;transform:none}
  .btn-ghost{padding:.5rem 1rem;background:none;border:1px solid var(--border2);border-radius:var(--r2);color:var(--text3);font-family:var(--sans);font-size:.77rem;cursor:pointer;transition:all var(--t)}
  .btn-ghost:hover{border-color:var(--border);color:var(--text2)}
  .btn-danger{padding:.5rem 1rem;background:rgba(224,82,82,.1);border:1px solid rgba(224,82,82,.2);border-radius:var(--r2);color:var(--red);font-family:var(--sans);font-size:.77rem;cursor:pointer;transition:all var(--t)}
  .btn-danger:hover{background:rgba(224,82,82,.18)}
  .btn-danger:disabled{opacity:.5;pointer-events:none}

  /* Confirm delete info */
  .del-info{background:rgba(255,255,255,.02);border:1px solid var(--border2);border-radius:var(--r2);padding:.6rem .8rem;margin-bottom:.75rem;font-size:.78rem;color:var(--text2);font-weight:500}
  .del-warn{font-size:.72rem;color:var(--text3);line-height:1.6}

  /* Toast */
  #toasts{position:fixed;bottom:1rem;right:1rem;z-index:999;display:flex;flex-direction:column;gap:.3rem;pointer-events:none}
  .toast{background:var(--ink2);color:var(--text);border:1px solid var(--border);border-radius:var(--r2);padding:.48rem .8rem;font-size:.71rem;display:flex;align-items:center;gap:.38rem;animation:slideIn .2s ease both;max-width:260px;pointer-events:all;box-shadow:0 4px 20px rgba(0,0,0,.4)}
  .toast.ok{border-left:2px solid var(--leaf)}.toast.err{border-left:2px solid var(--red)}.toast.warn{border-left:2px solid var(--gold)}
  @keyframes slideIn{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:none}}

  @media(max-width:1100px){.stats-row{grid-template-columns:repeat(2,1fr)}}
  @media(max-width:768px){.main{margin-left:0}.page{padding:.9rem}.cards-grid{grid-template-columns:1fr}.stats-row{grid-template-columns:1fr 1fr}.frow{grid-template-columns:1fr}}
  </style>
</head>
<body>

<!-- ── Sidebar ─────────────────────────────────────────────── -->
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
    <a class="a-link" href="sessoes.php"><span class="a-link-ico">⊘</span>Sessões</a>
    <span class="a-grp">Conteúdo</span>
    <a class="a-link" href="mensagens.php"><span class="a-link-ico">⊡</span>Frases do dia</a>
    <a class="a-link" href="simulados.php"><span class="a-link-ico">⊞</span>Simulados</a>
    <a class="a-link active" href="cursos.php"><span class="a-link-ico">⊟</span>Cursos</a>
    <span class="a-grp">Sistema</span>
    <a class="a-link" href="feedbacks.php"><span class="a-link-ico">⊠</span>Feedbacks</a>
    <a class="a-link" href="configuracoes.php"><span class="a-link-ico">⊛</span>Configurações</a>
    <a class="a-link" href="/florescer/public/views/dashboard.php" target="_blank"><span class="a-link-ico">↗</span>Ver plataforma</a>
  </nav>
  <div class="a-foot">
    <button class="a-logout" onclick="doLogout()">↩ Sair</button>
  </div>
</aside>

<!-- ── Main ────────────────────────────────────────────────── -->
<div class="main">
  <header class="topbar">
    <span class="tb-title">Cursos & Afiliados</span>
    <?php if ($tableOk): ?>
      <button class="btn-new" onclick="openCreate()">+ Novo curso</button>
    <?php endif; ?>
  </header>

  <main class="page">

    <?php if (!$tableOk): ?>
      <div class="setup">
        <h3>Tabela não encontrada</h3>
        <p>Execute o SQL abaixo no banco <strong>florescer</strong> para criar a tabela <code>store_items</code>:</p>
        <pre class="setup-sql">CREATE TABLE IF NOT EXISTS store_items (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title         VARCHAR(200)  NOT NULL,
  description   TEXT,
  image_url     VARCHAR(500),
  affiliate_url VARCHAR(500)  NOT NULL,
  category      VARCHAR(80),
  grade_level   VARCHAR(80),
  badge         ENUM('','novo','popular') DEFAULT '',
  price_display VARCHAR(50),
  is_active     TINYINT(1) DEFAULT 1,
  sort_order    SMALLINT  DEFAULT 0,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);</pre>
      </div>

    <?php else: ?>

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat"><span class="stat-ico">🎓</span><div><div class="stat-val"><?= $totalActive + $totalInactive ?></div><div class="stat-lbl">Total de cursos</div></div></div>
      <div class="stat"><span class="stat-ico">✅</span><div><div class="stat-val"><?= $totalActive ?></div><div class="stat-lbl">Ativos (visíveis)</div></div></div>
      <div class="stat"><span class="stat-ico">⊘</span><div><div class="stat-val"><?= $totalInactive ?></div><div class="stat-lbl">Inativos (ocultos)</div></div></div>
      <div class="stat"><span class="stat-ico">⭐</span><div><div class="stat-val"><?= $totalPopular ?></div><div class="stat-lbl">Populares</div></div></div>
    </div>

    <!-- Filtros -->
    <form method="GET" class="filters">
      <div class="search-wrap">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input class="inp" type="text" name="q" placeholder="Buscar título…" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>" maxlength="100"/>
      </div>
      <select class="inp" name="grade">
        <option value="">Todas as séries</option>
        <?php foreach ($grades as $g): ?>
          <option value="<?= htmlspecialchars($g['grade_level'], ENT_QUOTES) ?>" <?= $gradeF === $g['grade_level'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($g['grade_level'], ENT_QUOTES) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <select class="inp" name="badge">
        <option value="">Todos os badges</option>
        <option value="novo"    <?= $badgeF === 'novo'    ? 'selected' : '' ?>>✨ Novo</option>
        <option value="popular" <?= $badgeF === 'popular' ? 'selected' : '' ?>>⭐ Popular</option>
      </select>
      <select class="inp" name="status">
        <option value="">Todos os status</option>
        <option value="1" <?= $statusF === '1' ? 'selected' : '' ?>>Ativos</option>
        <option value="0" <?= $statusF === '0' ? 'selected' : '' ?>>Inativos</option>
      </select>
      <button class="btn-filter" type="submit">Filtrar</button>
      <?php if ($search || $gradeF || $badgeF || $statusF): ?>
        <a class="clear-link" href="cursos.php">✕ Limpar</a>
      <?php endif; ?>
      <span class="result-count"><?= $totalRows ?> curso(s)</span>
    </form>

    <!-- Grid -->
    <?php if (empty($items)): ?>
      <div class="empty">
        <span class="empty-ico">🎓</span>
        <?= ($search || $gradeF || $badgeF || $statusF)
          ? 'Nenhum curso encontrado para esses filtros.'
          : 'Nenhum curso cadastrado. Clique em "+ Novo curso" para começar.' ?>
      </div>
    <?php else: ?>
      <div class="cards-grid">
        <?php foreach ($items as $item):
          $bid = (int)$item['id'];
          $bge = $item['badge'] ?? '';
        ?>
        <div class="course-card <?= !$item['is_active'] ? 'inactive' : '' ?>" id="card-<?= $bid ?>">

          <!-- Thumb -->
          <div class="thumb">
            <?php if (!empty($item['image_url'])): ?>
              <img src="<?= htmlspecialchars($item['image_url'], ENT_QUOTES) ?>" alt=""
                   loading="lazy"
                   onerror="this.style.display='none';this.nextSibling.style.display='flex'"/>
              <div class="thumb-placeholder" style="display:none;width:100%;height:100%;align-items:center;justify-content:center">📚</div>
            <?php else: ?>
              <div class="thumb-placeholder">📚</div>
            <?php endif; ?>

            <?php if ($bge === 'popular'): ?>
              <span class="course-badge popular">⭐ Popular</span>
            <?php elseif ($bge === 'novo'): ?>
              <span class="course-badge novo">✨ Novo</span>
            <?php endif; ?>

            <div class="toggle-wrap">
              <button
                class="toggle <?= $item['is_active'] ? 'on' : 'off' ?>"
                id="tog-<?= $bid ?>"
                onclick="toggleItem(<?= $bid ?>, this)"
                title="<?= $item['is_active'] ? 'Visível — clique para ocultar' : 'Oculto — clique para exibir' ?>"
              ></button>
            </div>
          </div>

          <!-- Corpo -->
          <div class="card-body">
            <?php if (!empty($item['category'])): ?>
              <div class="card-cat"><?= htmlspecialchars($item['category'], ENT_QUOTES) ?></div>
            <?php endif; ?>
            <div class="card-name"><?= htmlspecialchars($item['title'], ENT_QUOTES) ?></div>
            <?php if (!empty($item['grade_level'])): ?>
              <div class="card-grade">📚 <?= htmlspecialchars($item['grade_level'], ENT_QUOTES) ?></div>
            <?php endif; ?>
            <?php if (!empty($item['description'])): ?>
              <div class="card-desc"><?= htmlspecialchars($item['description'], ENT_QUOTES) ?></div>
            <?php endif; ?>
            <?php if (!empty($item['price_display'])): ?>
              <div class="card-price"><?= htmlspecialchars($item['price_display'], ENT_QUOTES) ?></div>
            <?php endif; ?>
            <div class="card-url" title="<?= htmlspecialchars($item['affiliate_url'], ENT_QUOTES) ?>">
              🔗 <?= htmlspecialchars($item['affiliate_url'], ENT_QUOTES) ?>
            </div>

            <div class="card-actions">
              <button class="act-btn" onclick='openEdit(<?= json_encode([
                "id"            => $bid,
                "title"         => $item["title"],
                "description"   => $item["description"] ?? "",
                "image_url"     => $item["image_url"]   ?? "",
                "affiliate_url" => $item["affiliate_url"],
                "category"      => $item["category"]    ?? "",
                "grade_level"   => $item["grade_level"] ?? "",
                "badge"         => $item["badge"]        ?? "",
                "price_display" => $item["price_display"] ?? "",
                "sort_order"    => (int)$item["sort_order"],
                "is_active"     => (bool)$item["is_active"],
              ], JSON_UNESCAPED_UNICODE) ?>)'>✏ Editar</button>
              <a class="act-btn" href="<?= htmlspecialchars($item['affiliate_url'], ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer">↗ Testar</a>
              <button class="act-btn del" onclick="confirmDel(<?= $bid ?>, '<?= htmlspecialchars(addslashes($item['title']), ENT_QUOTES) ?>')">🗑</button>
            </div>
          </div>

        </div>
        <?php endforeach; ?>
      </div>

      <!-- Paginação -->
      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <a class="pg <?= $page <= 1 ? 'off' : '' ?>" href="<?= pgUrl($page - 1, $search, $gradeF, $badgeF, $statusF) ?>">‹</a>
        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
          <a class="pg <?= $p === $page ? 'cur' : '' ?>" href="<?= pgUrl($p, $search, $gradeF, $badgeF, $statusF) ?>"><?= $p ?></a>
        <?php endfor; ?>
        <a class="pg <?= $page >= $totalPages ? 'off' : '' ?>" href="<?= pgUrl($page + 1, $search, $gradeF, $badgeF, $statusF) ?>">›</a>
        <span class="pg-info"><?= $page ?>/<?= $totalPages ?></span>
      </div>
      <?php endif; ?>

    <?php endif; // items ?>
    <?php endif; // tableOk ?>

  </main>
</div>

<!-- ── Modal criar/editar ──────────────────────────────────── -->
<div class="overlay" id="overlayForm">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title" id="formTitle">Novo curso</span>
      <button class="modal-x" onclick="closeOverlay('overlayForm')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="fId"/>

      <div class="fg">
        <label class="fl">Título *</label>
        <input class="fc" type="text" id="fTitle" placeholder="Nome do curso" maxlength="200" autocomplete="off"/>
      </div>

      <div class="fg">
        <label class="fl">Link de afiliado *</label>
        <input class="fc" type="url" id="fUrl" placeholder="https://…" maxlength="500"/>
      </div>

      <div class="frow">
        <div class="fg">
          <label class="fl">Categoria</label>
          <input class="fc" type="text" id="fCat" placeholder="Ex: Cursinho, ENEM…" maxlength="80"/>
        </div>
        <div class="fg">
          <label class="fl">Série / Público</label>
          <input class="fc" type="text" id="fGrade" placeholder="Ex: 3º EM, Faculdade…" maxlength="80"/>
        </div>
      </div>

      <div class="frow">
        <div class="fg">
          <label class="fl">Preço (exibição)</label>
          <input class="fc" type="text" id="fPrice" placeholder="Ex: Grátis, R$49/mês…" maxlength="50"/>
        </div>
        <div class="fg">
          <label class="fl">Ordem</label>
          <input class="fc" type="number" id="fOrder" value="0" min="0" max="9999"/>
        </div>
      </div>

      <div class="fg">
        <label class="fl">URL da imagem (thumbnail)</label>
        <input class="fc" type="url" id="fImg" placeholder="https://… (JPG, PNG, WEBP)" maxlength="500" oninput="previewImg()"/>
        <div class="img-prev" id="imgPrev"><div class="img-prev-ph">📷</div></div>
      </div>

      <div class="fg">
        <label class="fl">Badge</label>
        <div class="badge-picker">
          <button type="button" class="bp-opt s-none" data-badge="" onclick="pickBadge('',this)">Nenhum</button>
          <button type="button" class="bp-opt" data-badge="novo" onclick="pickBadge('novo',this)">✨ Novo</button>
          <button type="button" class="bp-opt" data-badge="popular" onclick="pickBadge('popular',this)">⭐ Popular</button>
        </div>
        <input type="hidden" id="fBadge" value=""/>
      </div>

      <div class="fg">
        <label class="fl">Descrição</label>
        <textarea class="fc" id="fDesc" placeholder="Breve descrição do curso…" maxlength="1000"></textarea>
      </div>

      <div class="fg" style="display:flex;align-items:center;gap:.5rem">
        <input type="checkbox" id="fActive" checked style="accent-color:var(--leaf);width:14px;height:14px;cursor:pointer"/>
        <label class="fl" for="fActive" style="margin:0;cursor:pointer">Visível na loja</label>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-ghost" onclick="closeOverlay('overlayForm')">Cancelar</button>
      <button class="btn-primary" id="btnSave" onclick="submitForm()">Salvar</button>
    </div>
  </div>
</div>

<!-- ── Modal excluir ───────────────────────────────────────── -->
<div class="overlay modal-sm" id="overlayDel">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title">Excluir curso</span>
      <button class="modal-x" onclick="closeOverlay('overlayDel')">✕</button>
    </div>
    <div class="modal-body">
      <div class="del-info" id="delInfo"></div>
      <p class="del-warn">Esta ação é irreversível. O curso será removido da loja imediatamente.</p>
    </div>
    <div class="modal-foot">
      <button class="btn-ghost" onclick="closeOverlay('overlayDel')">Cancelar</button>
      <button class="btn-danger" id="btnDel" onclick="submitDel()">Excluir</button>
    </div>
  </div>
</div>

<div id="toasts"></div>

<script>
const API = '/florescer/admin/api/cursos.php';

// ── Toast ──────────────────────────────────────────────────
function toast(msg, type = 'ok', ms = 3500) {
  const w = document.getElementById('toasts');
  const d = document.createElement('div');
  d.className = `toast ${type}`;
  d.innerHTML = `<span>${type === 'ok' ? '✓' : type === 'err' ? '✕' : '!'}</span><span>${msg}</span>`;
  w.appendChild(d);
  setTimeout(() => { d.style.opacity = '0'; d.style.transition = '.25s'; setTimeout(() => d.remove(), 260); }, ms);
}

// ── Overlay ────────────────────────────────────────────────
function openOverlay(id) { document.getElementById(id).classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeOverlay(id){ document.getElementById(id).classList.remove('open'); document.body.style.overflow = ''; }
document.querySelectorAll('.overlay').forEach(o => o.addEventListener('click', e => { if (e.target === o) closeOverlay(o.id); }));
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.overlay.open').forEach(o => closeOverlay(o.id)); });

// ── API call ───────────────────────────────────────────────
async function api(body) {
  const r = await fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
  if (!r.ok) throw new Error('HTTP ' + r.status);
  return r.json();
}

// ── Badge picker ───────────────────────────────────────────
function pickBadge(val, btn) {
  document.getElementById('fBadge').value = val;
  document.querySelectorAll('.bp-opt').forEach(b => {
    b.className = 'bp-opt';
    if (b.dataset.badge === val) b.classList.add(val === '' ? 's-none' : val === 'novo' ? 's-novo' : 's-popular');
  });
}

// ── Preview imagem ─────────────────────────────────────────
function previewImg() {
  const url = document.getElementById('fImg').value.trim();
  const box = document.getElementById('imgPrev');
  if (!url) { box.innerHTML = '<div class="img-prev-ph">📷</div>'; return; }
  box.innerHTML = `<img src="${url}" alt="" onerror="this.parentNode.innerHTML='<div class=img-prev-ph>✕ URL inválida</div>'"/>`;
}

// ── Criar ──────────────────────────────────────────────────
function openCreate() {
  document.getElementById('fId').value = '';
  document.getElementById('formTitle').textContent = 'Novo curso';
  ['fTitle','fUrl','fCat','fGrade','fPrice','fImg','fDesc'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('fOrder').value = '0';
  document.getElementById('fActive').checked = true;
  document.getElementById('imgPrev').innerHTML = '<div class="img-prev-ph">📷</div>';
  pickBadge('', document.querySelector('.bp-opt'));
  openOverlay('overlayForm');
  setTimeout(() => document.getElementById('fTitle').focus(), 150);
}

// ── Editar ─────────────────────────────────────────────────
function openEdit(item) {
  document.getElementById('fId').value = item.id;
  document.getElementById('formTitle').textContent = 'Editar curso';
  document.getElementById('fTitle').value  = item.title          || '';
  document.getElementById('fUrl').value    = item.affiliate_url  || '';
  document.getElementById('fCat').value    = item.category       || '';
  document.getElementById('fGrade').value  = item.grade_level    || '';
  document.getElementById('fPrice').value  = item.price_display  || '';
  document.getElementById('fImg').value    = item.image_url      || '';
  document.getElementById('fDesc').value   = item.description    || '';
  document.getElementById('fOrder').value  = item.sort_order     || 0;
  document.getElementById('fActive').checked = !!item.is_active;
  previewImg();
  pickBadge(item.badge || '', document.querySelector(`.bp-opt[data-badge="${item.badge || ''}"]`) || document.querySelector('.bp-opt'));
  openOverlay('overlayForm');
  setTimeout(() => document.getElementById('fTitle').focus(), 150);
}

// ── Salvar ─────────────────────────────────────────────────
async function submitForm() {
  const id    = document.getElementById('fId').value;
  const title = document.getElementById('fTitle').value.trim();
  const url   = document.getElementById('fUrl').value.trim();

  if (!title) { toast('Informe o título.', 'err'); return; }
  if (!url)   { toast('Informe o link de afiliado.', 'err'); return; }
  if (!url.startsWith('http')) { toast('Link inválido — use https://.', 'err'); return; }

  const btn = document.getElementById('btnSave');
  btn.disabled = true; btn.textContent = 'Salvando…';

  try {
    const d = await api({
      action:        id ? 'update' : 'create',
      id:            id ? +id : null,
      title,
      affiliate_url: url,
      category:      document.getElementById('fCat').value.trim()   || null,
      grade_level:   document.getElementById('fGrade').value.trim() || null,
      price_display: document.getElementById('fPrice').value.trim() || null,
      image_url:     document.getElementById('fImg').value.trim()   || null,
      description:   document.getElementById('fDesc').value.trim()  || null,
      badge:         document.getElementById('fBadge').value,
      sort_order:    parseInt(document.getElementById('fOrder').value) || 0,
      is_active:     document.getElementById('fActive').checked ? 1 : 0,
    });
    if (d.success) {
      toast(id ? 'Curso atualizado.' : 'Curso criado! 🎓');
      closeOverlay('overlayForm');
      setTimeout(() => location.reload(), 600);
    } else toast(d.message || 'Erro ao salvar.', 'err');
  } catch { toast('Erro de conexão.', 'err'); }
  finally  { btn.disabled = false; btn.textContent = 'Salvar'; }
}

// ── Toggle ─────────────────────────────────────────────────
async function toggleItem(id, btn) {
  const isOn = btn.classList.contains('on');
  try {
    const d = await api({ action: 'toggle', id, active: !isOn });
    if (d.success) {
      btn.classList.toggle('on', !isOn);
      btn.classList.toggle('off', isOn);
      document.getElementById('card-' + id)?.classList.toggle('inactive', isOn);
      toast(!isOn ? 'Curso visível na loja.' : 'Curso ocultado.');
    } else toast(d.message || 'Erro.', 'err');
  } catch { toast('Erro de conexão.', 'err'); }
}

// ── Excluir ────────────────────────────────────────────────
let _delId = null;
function confirmDel(id, title) {
  _delId = id;
  document.getElementById('delInfo').textContent = title;
  openOverlay('overlayDel');
}
async function submitDel() {
  if (!_delId) return;
  const btn = document.getElementById('btnDel');
  btn.disabled = true; btn.textContent = 'Excluindo…';
  try {
    const d = await api({ action: 'delete', id: _delId });
    if (d.success) {
      toast('Curso excluído.');
      closeOverlay('overlayDel');
      const card = document.getElementById('card-' + _delId);
      if (card) { card.style.transition = '.3s'; card.style.opacity = '0'; }
      setTimeout(() => location.reload(), 420);
    } else toast(d.message || 'Erro.', 'err');
  } catch { toast('Erro de conexão.', 'err'); }
  finally  { btn.disabled = false; btn.textContent = 'Excluir'; }
}

// ── Logout ─────────────────────────────────────────────────
function doLogout() {
  fetch('../api/auth_admin.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'logout' }) })
    .finally(() => { window.location.href = '/florescer/index.php'; });
}
</script>
</body>
</html>