<?php
// ============================================================
// /admin/views/feedbacks.php — florescer Admin v3.0
// ============================================================
require_once __DIR__ . '/../includes/auth_admin.php';
require_once dirname(dirname(__DIR__)) . '/config/db.php';

requireAdmin();
date_default_timezone_set('America/Recife');

$adminName   = $_SESSION['admin_name'] ?? 'Admin';
$adminLetter = strtoupper(mb_substr($adminName, 0, 1, 'UTF-8'));

// ── Garante existência da tabela ──────────────────────────────
dbExec("CREATE TABLE IF NOT EXISTS feedbacks (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    type        VARCHAR(20)  NOT NULL DEFAULT 'sugestao',
    title       VARCHAR(150) NOT NULL,
    message     TEXT         NOT NULL,
    status      VARCHAR(20)  NOT NULL DEFAULT 'aberto',
    admin_reply TEXT         DEFAULT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user   (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ── Helpers ───────────────────────────────────────────────────
function timeAgo(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'agora';
    if ($diff < 3600)   return intdiv($diff,  60) . 'min atrás';
    if ($diff < 86400)  return intdiv($diff, 3600) . 'h atrás';
    if ($diff < 604800) return intdiv($diff, 86400) . 'd atrás';
    return date('d/m/Y', strtotime($dt));
}

// ── Stats ─────────────────────────────────────────────────────
$total   = (int)(dbRow('SELECT COUNT(*) AS n FROM feedbacks')['n']                                          ?? 0);
$abertos = (int)(dbRow("SELECT COUNT(*) AS n FROM feedbacks WHERE status='aberto'")['n']                    ?? 0);
$analise = (int)(dbRow("SELECT COUNT(*) AS n FROM feedbacks WHERE status='em_analise'")['n']                ?? 0);
$resolv  = (int)(dbRow("SELECT COUNT(*) AS n FROM feedbacks WHERE status='resolvido'")['n']                 ?? 0);
$fechado = (int)(dbRow("SELECT COUNT(*) AS n FROM feedbacks WHERE status='fechado'")['n']                   ?? 0);

// Por tipo
$byType = dbQuery("SELECT type, COUNT(*) AS n FROM feedbacks GROUP BY type");
$typeMap = array_column($byType, 'n', 'type');

// ── Filtros ───────────────────────────────────────────────────
$fStatus = trim($_GET['status'] ?? '');
$fType   = trim($_GET['type']   ?? '');
$search  = trim($_GET['q']      ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

$where  = 'WHERE 1=1';
$params = [];
if ($fStatus) { $where .= ' AND f.status = ?';  $params[] = $fStatus; }
if ($fType)   { $where .= ' AND f.type = ?';    $params[] = $fType; }
if ($search)  {
    $where .= ' AND (f.title LIKE ? OR f.message LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}

$totalRows  = (int)(dbRow(
    "SELECT COUNT(*) AS n FROM feedbacks f
     LEFT JOIN users u ON u.id = f.user_id $where", $params
)['n'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$queryParams = array_merge($params, [$perPage, $offset]);
$feedbacks   = dbQuery(
    "SELECT f.*,
            u.name  AS user_name,
            u.email AS user_email,
            u.avatar_emoji,
            u.level AS user_level
     FROM feedbacks f
     LEFT JOIN users u ON u.id = f.user_id
     $where
     ORDER BY
       CASE f.status
         WHEN 'aberto'     THEN 0
         WHEN 'em_analise' THEN 1
         WHEN 'resolvido'  THEN 2
         ELSE 3
       END ASC,
       f.created_at DESC
     LIMIT ? OFFSET ?",
    $queryParams
);

// ── Meta de labels ────────────────────────────────────────────
$TYPE_META = [
    'sugestao' => ['💡', 'Sugestão', '#7c3aed', 'rgba(124,58,237,.1)', 'rgba(124,58,237,.18)'],
    'bug'      => ['🐛', 'Bug',      '#dc2626', 'rgba(220,38,38,.1)',  'rgba(220,38,38,.18)'],
    'elogio'   => ['⭐', 'Elogio',   '#d97706', 'rgba(217,119,6,.1)',  'rgba(217,119,6,.18)'],
    'duvida'   => ['❓', 'Dúvida',   '#2563eb', 'rgba(37,99,235,.1)',  'rgba(37,99,235,.18)'],
];
$STATUS_META = [
    'aberto'     => ['📬 Aberto',     '#d97706', 'rgba(217,119,6,.12)'],
    'em_analise' => ['🔍 Em análise', '#2563eb', 'rgba(37,99,235,.12)'],
    'resolvido'  => ['✅ Resolvido',  '#16a34a', 'rgba(22,163,74,.12)'],
    'fechado'    => ['🔒 Fechado',    '#64748b', 'rgba(100,116,139,.12)'],
];

function pgUrl(int $p, string $s, string $t, string $q): string {
    $u = '?page=' . $p;
    if ($s) $u .= '&status=' . urlencode($s);
    if ($t) $u .= '&type='   . urlencode($t);
    if ($q) $u .= '&q='      . urlencode($q);
    return $u;
}

// ── audit log: logins falhos 24h ─────────────────────────────
$failedLogins = (int)(dbRow(
    "SELECT COUNT(*) AS n FROM admin_audit_log
     WHERE event='LOGIN_FAIL' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
)['n'] ?? 0);

$openFeedbacks = $abertos;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>Feedbacks — florescer Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
  /* ══ RESET & TOKENS ══════════════════════════════════════════ */
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{
    --ink:   #0b1a12;--ink2:#122019;--ink3:#1a3027;
    --border:rgba(82,183,136,.1);--border2:rgba(82,183,136,.06);
    --muted: rgba(116,198,157,.3);--muted2:rgba(116,198,157,.18);
    --leaf:  #52b788;--leaf2:#74c69d;--leaf3:#b7e4c7;
    --gold:  #c9a84c;--red:#e05252;
    --text:  #c8e6d4;--text2:rgba(200,230,212,.55);--text3:rgba(200,230,212,.3);
    --serif:'Instrument Serif',Georgia,serif;
    --sans: 'DM Sans',system-ui,sans-serif;
    --sw:220px;--hh:54px;--r:12px;--r2:8px;--gap:1rem;
    --sh1:0 1px 4px rgba(0,0,0,.2);--sh2:0 4px 20px rgba(0,0,0,.3);
    --t:.18s cubic-bezier(.4,0,.2,1);
  }
  html,body{height:100%;font-family:var(--sans);background:var(--ink);color:var(--text);-webkit-font-smoothing:antialiased}
  body{display:flex}
  ::-webkit-scrollbar{width:3px;height:3px}
  ::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px}

  /* ══ SIDEBAR ════════════════════════════════════════════════ */
  .aside{width:var(--sw);min-height:100vh;position:fixed;top:0;left:0;
    background:var(--ink2);border-right:1px solid var(--border2);
    display:flex;flex-direction:column;z-index:50}

  .a-logo{padding:1.1rem 1.2rem .9rem;border-bottom:1px solid var(--border2);
    display:flex;align-items:center;gap:.6rem;flex-shrink:0}
  .a-logo-mark{width:32px;height:32px;border-radius:9px;flex-shrink:0;
    background:linear-gradient(135deg,var(--leaf) 0%,#2d6a4f 100%);
    display:flex;align-items:center;justify-content:center;font-size:.95rem;
    box-shadow:0 2px 10px rgba(82,183,136,.25)}
  .a-logo-name{font-family:var(--serif);font-size:1rem;color:var(--leaf3);line-height:1.1;letter-spacing:-.01em}
  .a-logo-tag{font-size:.54rem;color:var(--muted);text-transform:uppercase;letter-spacing:.12em}

  .a-who{margin:.65rem .8rem;background:rgba(82,183,136,.05);border:1px solid var(--border);
    border-radius:var(--r2);padding:.45rem .65rem;display:flex;align-items:center;gap:.5rem}
  .a-av{width:24px;height:24px;border-radius:50%;flex-shrink:0;
    background:linear-gradient(135deg,var(--leaf),#2d6a4f);
    display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:600;color:#fff}
  .a-name{font-size:.72rem;font-weight:500;color:var(--leaf2);line-height:1}
  .a-role{font-size:.57rem;color:var(--muted);margin-top:.08rem}

  .a-nav{flex:1;overflow-y:auto;padding:.3rem 0 .5rem}
  .a-grp{font-size:.52rem;text-transform:uppercase;letter-spacing:.1em;
    color:var(--muted2);padding:.7rem 1.2rem .2rem;display:block}
  .a-link{display:flex;align-items:center;gap:.5rem;padding:.38rem 1.2rem;
    font-size:.74rem;color:var(--text3);text-decoration:none;
    border-left:2px solid transparent;transition:all var(--t)}
  .a-link:hover{color:var(--leaf2);background:rgba(82,183,136,.04)}
  .a-link.active{color:var(--leaf2);background:rgba(82,183,136,.07);
    border-left-color:var(--leaf);font-weight:500}
  .a-link-ico{width:.9rem;text-align:center;font-size:.78rem;opacity:.8;flex-shrink:0}
  .nav-badge{margin-left:auto;background:rgba(224,82,82,.15);color:#e05252;
    font-size:.55rem;font-weight:600;padding:.1rem .35rem;border-radius:20px;
    border:1px solid rgba(224,82,82,.2)}

  .a-foot{padding:.7rem .8rem;border-top:1px solid var(--border2);flex-shrink:0}
  .a-logout{width:100%;display:flex;align-items:center;justify-content:center;gap:.38rem;
    padding:.38rem;border-radius:var(--r2);background:none;border:1px solid rgba(224,82,82,.12);
    color:rgba(224,82,82,.4);font-family:var(--sans);font-size:.7rem;cursor:pointer;transition:all var(--t)}
  .a-logout:hover{background:rgba(224,82,82,.06);color:var(--red)}

  /* ══ MAIN ════════════════════════════════════════════════════ */
  .main{margin-left:var(--sw);flex:1;min-width:0;display:flex;flex-direction:column}

  .topbar{height:var(--hh);position:sticky;top:0;z-index:40;
    background:rgba(11,26,18,.92);backdrop-filter:blur(16px);
    border-bottom:1px solid var(--border2);
    display:flex;align-items:center;justify-content:space-between;padding:0 1.5rem;flex-shrink:0}
  .tb-left{display:flex;align-items:baseline;gap:.5rem}
  .tb-title{font-family:var(--serif);font-size:1rem;color:var(--leaf3);letter-spacing:-.01em}
  .tb-sub{font-size:.67rem;color:var(--muted)}
  .tb-right{display:flex;align-items:center;gap:.75rem}
  .pill{display:flex;align-items:center;gap:.28rem;background:rgba(82,183,136,.07);
    border:1px solid var(--border);border-radius:50px;padding:.2rem .6rem;
    font-size:.63rem;font-weight:500;color:var(--leaf2)}
  .pill-dot{width:5px;height:5px;border-radius:50%;background:var(--leaf);animation:pulse 2.5s infinite}
  @keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.8)}}

  /* ══ PAGE ════════════════════════════════════════════════════ */
  .page{padding:1.25rem 1.5rem;display:flex;flex-direction:column;gap:var(--gap);flex:1}

  /* ══ STATS ROW ═══════════════════════════════════════════════ */
  .stats-row{display:grid;grid-template-columns:repeat(5,1fr);gap:.65rem}
  .stat{background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);
    padding:.75rem 1rem;position:relative;overflow:hidden;cursor:default;
    transition:border-color var(--t),transform var(--t)}
  .stat:hover{border-color:var(--border);transform:translateY(-1px)}
  .stat.clickable{cursor:pointer}
  .stat::before{content:'';position:absolute;bottom:0;left:0;right:0;height:1px;
    background:var(--stat-color,var(--leaf));opacity:.4}
  .stat-icon{font-size:.75rem;margin-bottom:.4rem;opacity:.65;display:block}
  .stat-val{font-family:var(--serif);font-size:1.5rem;color:var(--leaf3);line-height:1;letter-spacing:-.03em}
  .stat-label{font-size:.6rem;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-top:.15rem}

  /* ══ FILTER BAR ══════════════════════════════════════════════ */
  .filter-bar{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;
    background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);padding:.6rem .8rem}
  .fc{padding:.38rem .65rem;background:rgba(82,183,136,.04);border:1px solid var(--border2);
    border-radius:var(--r2);color:var(--text);font-family:var(--sans);font-size:.76rem;
    outline:none;appearance:none;transition:all var(--t)}
  .fc:focus{border-color:var(--leaf);box-shadow:0 0 0 3px rgba(82,183,136,.08)}
  .fc option{background:var(--ink2)}
  .search-wrap{position:relative;flex:1;min-width:180px;max-width:280px}
  .search-wrap svg{position:absolute;left:.55rem;top:50%;transform:translateY(-50%);
    width:12px;height:12px;color:var(--muted);pointer-events:none}
  .search-wrap .fc{width:100%;padding-left:1.8rem}
  .btn-filter{padding:.38rem .85rem;background:var(--leaf);border:none;border-radius:var(--r2);
    color:#fff;font-family:var(--sans);font-size:.76rem;font-weight:600;cursor:pointer;transition:all var(--t)}
  .btn-filter:hover{background:var(--leaf2)}
  .filter-clear{font-size:.7rem;color:var(--text3);text-decoration:none;transition:color var(--t)}
  .filter-clear:hover{color:var(--leaf)}
  .filter-count{margin-left:auto;font-size:.68rem;color:var(--muted);white-space:nowrap}

  /* ══ FEEDBACK CARDS ══════════════════════════════════════════ */
  .fb-list{display:flex;flex-direction:column;gap:.6rem}

  .fb-card{background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);
    overflow:hidden;transition:border-color var(--t)}
  .fb-card:hover{border-color:var(--border)}
  .fb-card.aberto    {border-left:3px solid #d97706}
  .fb-card.em_analise{border-left:3px solid #2563eb}
  .fb-card.resolvido {border-left:3px solid #16a34a}
  .fb-card.fechado   {border-left:3px solid #555}

  .fb-head{padding:.6rem 1rem;border-bottom:1px solid var(--border2);
    display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
  .fb-badge{font-size:.6rem;font-weight:600;padding:.13rem .45rem;border-radius:20px;
    white-space:nowrap;flex-shrink:0;border:1px solid transparent}
  .fb-title-text{font-size:.82rem;font-weight:500;color:var(--text);flex:1;min-width:0;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .fb-user-chip{font-size:.67rem;color:var(--text3);white-space:nowrap;flex-shrink:0}
  .fb-time{font-size:.63rem;color:var(--muted);white-space:nowrap;flex-shrink:0}

  .fb-body{padding:.7rem 1rem}
  .fb-message{font-size:.78rem;color:var(--text2);line-height:1.65;margin-bottom:.65rem;
    padding:.55rem .7rem;background:rgba(82,183,136,.03);border-radius:var(--r2);
    border:1px solid var(--border2)}

  .fb-reply-box{margin-bottom:.65rem;padding:.55rem .75rem;
    background:rgba(82,183,136,.06);border:1px solid rgba(82,183,136,.14);
    border-radius:var(--r2);border-left:3px solid var(--leaf)}
  .fb-reply-lbl{font-size:.6rem;font-weight:600;color:var(--leaf);
    text-transform:uppercase;letter-spacing:.05em;margin-bottom:.18rem}
  .fb-reply-txt{font-size:.76rem;color:var(--text2);line-height:1.6}

  .fb-actions{display:flex;align-items:center;gap:.4rem;flex-wrap:wrap}
  .status-sel{padding:.3rem .55rem;background:rgba(82,183,136,.05);
    border:1px solid var(--border);border-radius:var(--r2);color:var(--text2);
    font-family:var(--sans);font-size:.72rem;outline:none;cursor:pointer;transition:all var(--t)}
  .status-sel:focus{border-color:var(--leaf)}
  .status-sel option{background:var(--ink2)}

  .btn-sm{padding:.3rem .65rem;border-radius:var(--r2);border:1px solid var(--border);
    background:none;color:var(--text3);font-family:var(--sans);font-size:.72rem;
    cursor:pointer;transition:all var(--t);display:inline-flex;align-items:center;gap:.28rem}
  .btn-sm:hover{border-color:var(--leaf);color:var(--leaf2);background:rgba(82,183,136,.05)}
  .btn-del{border-color:rgba(224,82,82,.15);color:rgba(224,130,130,.5)}
  .btn-del:hover{border-color:var(--red);color:var(--red);background:rgba(224,82,82,.07)}
  .btn-sm.active-reply{border-color:var(--leaf);color:var(--leaf);background:rgba(82,183,136,.06)}

  .reply-area{display:none;margin-top:.65rem;padding-top:.65rem;border-top:1px solid var(--border2)}
  .reply-area.open{display:block}
  .reply-inp{width:100%;padding:.55rem .75rem;background:rgba(82,183,136,.03);
    border:1px solid var(--border);border-radius:var(--r2);color:var(--text);
    font-family:var(--sans);font-size:.78rem;resize:vertical;min-height:80px;
    outline:none;transition:border-color var(--t);line-height:1.6}
  .reply-inp:focus{border-color:var(--leaf);box-shadow:0 0 0 3px rgba(82,183,136,.08)}
  .reply-inp::placeholder{color:var(--text3)}
  .reply-footer{display:flex;align-items:center;justify-content:space-between;margin-top:.4rem}
  .reply-chars{font-size:.62rem;color:var(--muted)}
  .btn-send{padding:.38rem .9rem;background:linear-gradient(135deg,var(--leaf),#2d6a4f);
    border:none;border-radius:var(--r2);color:#fff;font-family:var(--sans);
    font-size:.76rem;font-weight:600;cursor:pointer;transition:all var(--t)}
  .btn-send:hover:not(:disabled){filter:brightness(1.1)}
  .btn-send:disabled{opacity:.45;cursor:not-allowed}

  /* ══ EMPTY ═══════════════════════════════════════════════════ */
  .empty-state{text-align:center;padding:3rem;background:var(--ink2);
    border:1px solid var(--border2);border-radius:var(--r);color:var(--text3);font-size:.8rem}
  .empty-ico{font-size:2rem;display:block;margin-bottom:.6rem;opacity:.4}

  /* ══ PAGINATION ══════════════════════════════════════════════ */
  .pagination{display:flex;align-items:center;justify-content:center;gap:.3rem;
    padding:.8rem;background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r)}
  .pg-btn{padding:.28rem .6rem;border-radius:var(--r2);border:1px solid var(--border2);
    background:none;color:var(--text3);font-size:.72rem;cursor:pointer;text-decoration:none;
    transition:all var(--t)}
  .pg-btn:hover{border-color:var(--leaf);color:var(--leaf2);background:rgba(82,183,136,.05)}
  .pg-btn.active{background:var(--leaf);border-color:var(--leaf);color:#fff}
  .pg-btn.disabled{opacity:.22;pointer-events:none}
  .pg-info{font-size:.65rem;color:var(--muted);margin-left:.3rem}

  /* ══ TOAST ═══════════════════════════════════════════════════ */
  #toasts{position:fixed;bottom:1rem;right:1rem;z-index:999;
    display:flex;flex-direction:column;gap:.35rem;pointer-events:none}
  .toast{background:var(--ink2);color:var(--text);border:1px solid var(--border);
    border-radius:var(--r2);padding:.5rem .85rem;font-size:.72rem;
    display:flex;align-items:center;gap:.4rem;animation:slideIn .2s var(--t) both;
    max-width:280px;pointer-events:all;box-shadow:var(--sh2)}
  .toast.ok {border-left:2px solid var(--leaf)}
  .toast.err{border-left:2px solid var(--red)}
  .toast.warn{border-left:2px solid var(--gold)}
  @keyframes slideIn{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:none}}

  /* ══ RESPONSIVE ══════════════════════════════════════════════ */
  @media(max-width:1200px){.stats-row{grid-template-columns:repeat(3,1fr)}}
  @media(max-width:768px){.main{margin-left:0}.page{padding:.9rem}.stats-row{grid-template-columns:repeat(2,1fr)}}
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="aside">
  <div class="a-logo">
    <div class="a-logo-mark">🌱</div>
    <div>
      <div class="a-logo-name">florescer</div>
      <div class="a-logo-tag">admin</div>
    </div>
  </div>
  <div class="a-who">
    <div class="a-av"><?= $adminLetter ?></div>
    <div>
      <div class="a-name"><?= htmlspecialchars($adminName, ENT_QUOTES) ?></div>
      <div class="a-role">Administrador</div>
    </div>
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
    <a class="a-link" href="cursos.php"><span class="a-link-ico">⊟</span>Cursos</a>
    <span class="a-grp">Sistema</span>
    <a class="a-link active" href="feedbacks.php">
      <span class="a-link-ico">⊠</span>Feedbacks
      <?php if ($openFeedbacks > 0): ?>
        <span class="nav-badge"><?= $openFeedbacks ?></span>
      <?php endif; ?>
    </a>
    <a class="a-link" href="configuracoes.php"><span class="a-link-ico">⊛</span>Configurações</a>
    <a class="a-link" href="/florescer/public/views/dashboard.php" target="_blank"><span class="a-link-ico">↗</span>Ver plataforma</a>
  </nav>
  <div class="a-foot">
    <button class="a-logout" onclick="doLogout()"><span>↩</span> Sair</button>
  </div>
</aside>

<!-- MAIN -->
<div class="main">

  <header class="topbar">
    <div class="tb-left">
      <span class="tb-title">Feedbacks</span>
      <span class="tb-sub"><?= date('d/m/Y') ?></span>
    </div>
    <div class="tb-right">
      <?php if ($failedLogins > 0): ?>
        <span class="pill" style="background:rgba(224,82,82,.07);border-color:rgba(224,82,82,.15);color:#e08080">
          <span style="width:5px;height:5px;border-radius:50%;background:#e05252;flex-shrink:0"></span>
          <?= $failedLogins ?> tentativas falhas
        </span>
      <?php endif; ?>
      <?php if ($abertos > 0): ?>
        <span class="pill" style="background:rgba(217,119,6,.07);border-color:rgba(217,119,6,.18);color:#f59e0b">
          <span style="width:5px;height:5px;border-radius:50%;background:#d97706;flex-shrink:0"></span>
          <?= $abertos ?> aberto<?= $abertos > 1 ? 's' : '' ?>
        </span>
      <?php endif; ?>
      <span class="pill"><span class="pill-dot"></span>online</span>
    </div>
  </header>

  <main class="page">

    <!-- ── Stats ─────────────────────────────────────────────── -->
    <div class="stats-row">
      <div class="stat" style="--stat-color:var(--leaf)">
        <span class="stat-icon">💌</span>
        <div class="stat-val"><?= number_format($total) ?></div>
        <div class="stat-label">Total</div>
      </div>
      <div class="stat clickable" style="--stat-color:#d97706"
           onclick="location.href='?status=aberto'">
        <span class="stat-icon">📬</span>
        <div class="stat-val"><?= $abertos ?></div>
        <div class="stat-label">Abertos</div>
      </div>
      <div class="stat clickable" style="--stat-color:#2563eb"
           onclick="location.href='?status=em_analise'">
        <span class="stat-icon">🔍</span>
        <div class="stat-val"><?= $analise ?></div>
        <div class="stat-label">Em análise</div>
      </div>
      <div class="stat clickable" style="--stat-color:#16a34a"
           onclick="location.href='?status=resolvido'">
        <span class="stat-icon">✅</span>
        <div class="stat-val"><?= $resolv ?></div>
        <div class="stat-label">Resolvidos</div>
      </div>
      <div class="stat clickable" style="--stat-color:#64748b"
           onclick="location.href='?status=fechado'">
        <span class="stat-icon">🔒</span>
        <div class="stat-val"><?= $fechado ?></div>
        <div class="stat-label">Fechados</div>
      </div>
    </div>

    <!-- ── Filtros ────────────────────────────────────────────── -->
    <form method="GET" class="filter-bar">
      <select class="fc" name="status">
        <option value="">Todos os status</option>
        <?php foreach (['aberto'=>'📬 Aberto','em_analise'=>'🔍 Em análise','resolvido'=>'✅ Resolvido','fechado'=>'🔒 Fechado'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= $fStatus===$v?'selected':'' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
      <select class="fc" name="type">
        <option value="">Todos os tipos</option>
        <?php foreach (['sugestao'=>'💡 Sugestão','bug'=>'🐛 Bug','elogio'=>'⭐ Elogio','duvida'=>'❓ Dúvida'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= $fType===$v?'selected':'' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
      <div class="search-wrap">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input class="fc" type="text" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>"
               placeholder="Buscar…"/>
      </div>
      <button class="btn-filter" type="submit">Filtrar</button>
      <?php if ($fStatus || $fType || $search): ?>
        <a class="filter-clear" href="feedbacks.php">✕ Limpar</a>
      <?php endif; ?>
      <span class="filter-count"><?= $totalRows ?> feedback<?= $totalRows!==1?'s':'' ?></span>
    </form>

    <!-- ── Lista de feedbacks ─────────────────────────────────── -->
    <?php if (empty($feedbacks)): ?>
      <div class="empty-state">
        <span class="empty-ico">💌</span>
        Nenhum feedback encontrado com os filtros aplicados.
      </div>
    <?php else: ?>

    <div class="fb-list">
      <?php foreach ($feedbacks as $fb):
        $type   = $fb['type']   ?? 'sugestao';
        $status = $fb['status'] ?? 'aberto';
        [$tIco, $tLbl, $tColor, $tBg, $tBorder] = $TYPE_META[$type]   ?? ['💬', '?', '#888', 'rgba(136,136,136,.1)', 'rgba(136,136,136,.18)'];
        [$sLbl, $sColor, $sBg]                   = $STATUS_META[$status] ?? ['?', '#888', 'rgba(136,136,136,.1)'];
        $hasEmoji = !empty($fb['avatar_emoji']);
        $userInit = strtoupper(mb_substr($fb['user_name'] ?? '?', 0, 1, 'UTF-8'));
      ?>
      <div class="fb-card <?= htmlspecialchars($status, ENT_QUOTES) ?>" id="fb-<?= $fb['id'] ?>">

        <div class="fb-head">
          <!-- Tipo -->
          <span class="fb-badge"
                style="background:<?= $tBg ?>;color:<?= $tColor ?>;border-color:<?= $tBorder ?>">
            <?= $tIco ?> <?= $tLbl ?>
          </span>
          <!-- Status -->
          <span class="fb-badge"
                style="background:<?= $sBg ?>;color:<?= $sColor ?>">
            <?= $sLbl ?>
          </span>
          <!-- Título -->
          <span class="fb-title-text" title="<?= htmlspecialchars($fb['title'], ENT_QUOTES) ?>">
            <?= htmlspecialchars($fb['title'], ENT_QUOTES) ?>
          </span>
          <!-- Usuário -->
          <span class="fb-user-chip">
            <?= $hasEmoji
              ? htmlspecialchars($fb['avatar_emoji'], ENT_QUOTES)
              : '' ?>
            <?= htmlspecialchars($fb['user_name'] ?? 'Anônimo', ENT_QUOTES) ?>
          </span>
          <!-- Tempo -->
          <span class="fb-time"><?= timeAgo($fb['created_at']) ?></span>
        </div>

        <div class="fb-body">
          <!-- Mensagem -->
          <div class="fb-message"><?= nl2br(htmlspecialchars($fb['message'], ENT_QUOTES)) ?></div>

          <!-- Resposta do admin (se houver) -->
          <?php if (!empty($fb['admin_reply'])): ?>
          <div class="fb-reply-box">
            <div class="fb-reply-lbl">💬 Resposta do admin</div>
            <div class="fb-reply-txt"><?= nl2br(htmlspecialchars($fb['admin_reply'], ENT_QUOTES)) ?></div>
          </div>
          <?php endif; ?>

          <!-- Ações -->
          <div class="fb-actions">
            <select class="status-sel" onchange="updateStatus(<?= $fb['id'] ?>, this.value, this)">
              <?php foreach (['aberto'=>'📬 Aberto','em_analise'=>'🔍 Em análise','resolvido'=>'✅ Resolvido','fechado'=>'🔒 Fechado'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $status===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>

            <button class="btn-sm" id="reply-btn-<?= $fb['id'] ?>"
                    onclick="toggleReply(<?= $fb['id'] ?>)">
              ✏️ <?= empty($fb['admin_reply']) ? 'Responder' : 'Editar resposta' ?>
            </button>

            <button class="btn-sm btn-del" onclick="deleteFb(<?= $fb['id'] ?>)">🗑 Excluir</button>

            <?php if (!empty($fb['user_email'])): ?>
              <a class="btn-sm" href="mailto:<?= htmlspecialchars($fb['user_email'], ENT_QUOTES) ?>"
                 title="E-mail: <?= htmlspecialchars($fb['user_email'], ENT_QUOTES) ?>">
                ✉️ E-mail
              </a>
            <?php endif; ?>
          </div>

          <!-- Área de resposta -->
          <div class="reply-area" id="reply-<?= $fb['id'] ?>">
            <textarea class="reply-inp"
                      id="reply-txt-<?= $fb['id'] ?>"
                      placeholder="Escreva sua resposta para <?= htmlspecialchars($fb['user_name'] ?? 'o usuário', ENT_QUOTES) ?>…"
                      maxlength="2000"
                      oninput="updateChars(<?= $fb['id'] ?>, this.value)"><?= htmlspecialchars($fb['admin_reply'] ?? '', ENT_QUOTES) ?></textarea>
            <div class="reply-footer">
              <span class="reply-chars" id="chars-<?= $fb['id'] ?>">0/2000</span>
              <button class="btn-send" id="send-btn-<?= $fb['id'] ?>"
                      onclick="sendReply(<?= $fb['id'] ?>)">Enviar resposta</button>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ── Paginação ──────────────────────────────────────────── -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <a class="pg-btn <?= $page <= 1 ? 'disabled' : '' ?>"
         href="<?= pgUrl($page - 1, $fStatus, $fType, $search) ?>">‹</a>
      <?php
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);
        if ($start > 1): ?>
          <a class="pg-btn" href="<?= pgUrl(1, $fStatus, $fType, $search) ?>">1</a>
          <?php if ($start > 2): ?><span style="color:var(--text3);font-size:.7rem">…</span><?php endif; ?>
        <?php endif;
        for ($p = $start; $p <= $end; $p++): ?>
          <a class="pg-btn <?= $p === $page ? 'active' : '' ?>"
             href="<?= pgUrl($p, $fStatus, $fType, $search) ?>"><?= $p ?></a>
        <?php endfor;
        if ($end < $totalPages): ?>
          <?php if ($end < $totalPages - 1): ?><span style="color:var(--text3);font-size:.7rem">…</span><?php endif; ?>
          <a class="pg-btn" href="<?= pgUrl($totalPages, $fStatus, $fType, $search) ?>"><?= $totalPages ?></a>
      <?php endif; ?>
      <a class="pg-btn <?= $page >= $totalPages ? 'disabled' : '' ?>"
         href="<?= pgUrl($page + 1, $fStatus, $fType, $search) ?>">›</a>
      <span class="pg-info">Pág. <?= $page ?>/<?= $totalPages ?></span>
    </div>
    <?php endif; ?>

    <?php endif; // fim if empty($feedbacks) ?>

  </main>
</div>

<div id="toasts"></div>

<script>
const API = '/florescer/admin/api/feedbacks.php';

// ── Toast ────────────────────────────────────────────────────
function toast(msg, type = 'ok', ms = 3500) {
  const wrap = document.getElementById('toasts');
  const el   = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `<span>${type === 'ok' ? '✓' : type === 'err' ? '✕' : '!'}</span><span>${msg}</span>`;
  wrap.appendChild(el);
  setTimeout(() => {
    el.style.opacity = '0'; el.style.transition = '.25s';
    setTimeout(() => el.remove(), 260);
  }, ms);
}

// ── API helper ───────────────────────────────────────────────
async function apiFetch(body) {
  const r = await fetch(API, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify(body)
  });
  if (!r.ok) throw new Error('HTTP ' + r.status);
  return r.json();
}

// ── Atualiza status ──────────────────────────────────────────
async function updateStatus(id, status, sel) {
  try {
    const d = await apiFetch({ action: 'update_status', id, status });
    if (d.success) {
      toast('Status atualizado!');
      // Atualiza borda do card
      const card = document.getElementById('fb-' + id);
      if (card) {
        card.className = card.className.replace(/aberto|em_analise|resolvido|fechado/g, '') + ' ' + status;
      }
    } else {
      toast(d.message || 'Erro ao atualizar.', 'err');
      location.reload(); // reverte select
    }
  } catch (e) {
    toast('Erro de conexão.', 'err');
    location.reload();
  }
}

// ── Toggle área de resposta ──────────────────────────────────
function toggleReply(id) {
  const area = document.getElementById('reply-' + id);
  const btn  = document.getElementById('reply-btn-' + id);
  const open = area.classList.toggle('open');
  btn.classList.toggle('active-reply', open);
  if (open) {
    document.getElementById('reply-txt-' + id).focus();
    // Inicializa contador
    const txt = document.getElementById('reply-txt-' + id);
    updateChars(id, txt.value);
  }
}

// ── Contador de caracteres ───────────────────────────────────
function updateChars(id, val) {
  const el = document.getElementById('chars-' + id);
  if (el) el.textContent = val.length + '/2000';
}

// ── Envia resposta ───────────────────────────────────────────
async function sendReply(id) {
  const reply = document.getElementById('reply-txt-' + id).value.trim();
  if (!reply) { toast('Escreva uma resposta.', 'err'); return; }
  const btn = document.getElementById('send-btn-' + id);
  btn.disabled = true; btn.textContent = 'Enviando…';
  try {
    const d = await apiFetch({ action: 'reply', id, reply });
    if (d.success) {
      toast('Resposta enviada! ✅');
      setTimeout(() => location.reload(), 500);
    } else {
      toast(d.message || 'Erro.', 'err');
      btn.disabled = false; btn.textContent = 'Enviar resposta';
    }
  } catch (e) {
    toast('Erro de conexão.', 'err');
    btn.disabled = false; btn.textContent = 'Enviar resposta';
  }
}

// ── Exclui feedback ──────────────────────────────────────────
async function deleteFb(id) {
  if (!confirm('Excluir este feedback permanentemente? Esta ação não pode ser desfeita.')) return;
  try {
    const d = await apiFetch({ action: 'delete', id });
    if (d.success) {
      toast('Feedback excluído.');
      const el = document.getElementById('fb-' + id);
      if (el) {
        el.style.opacity = '0'; el.style.transform = 'translateX(-8px)';
        el.style.transition = 'all .3s';
        setTimeout(() => { el.remove(); }, 300);
      }
    } else {
      toast(d.message || 'Erro ao excluir.', 'err');
    }
  } catch (e) {
    toast('Erro de conexão.', 'err');
  }
}

// ── Logout ───────────────────────────────────────────────────
function doLogout() {
  fetch('../api/auth_admin.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ action: 'logout' })
  }).finally(() => { window.location.href = '/florescer/index.php'; });
}
</script>
</body>
</html>