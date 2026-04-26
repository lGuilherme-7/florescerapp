<?php
require_once __DIR__ . '/../includes/auth_admin.php';
require_once dirname(dirname(__DIR__)) . '/config/db.php';

requireAdmin();

$adminName   = $_SESSION['admin_name'] ?? 'Admin';
$adminLetter = strtoupper(mb_substr($adminName, 0, 1, 'UTF-8'));

$total = (int)(dbRow('SELECT COUNT(*) AS n FROM motivational_messages')['n'] ?? 0);

$dayOfYear = (int)date('z');
$todayIdx  = $total > 0 ? ($dayOfYear % $total) : 0;
$todayMsg  = $total > 0 ? dbRow('SELECT * FROM motivational_messages ORDER BY id ASC LIMIT 1 OFFSET ?', [$todayIdx]) : null;
$todayId   = $todayMsg ? (int)$todayMsg['id'] : 0;

$totalAuthors = (int)(dbRow("SELECT COUNT(DISTINCT author) AS n FROM motivational_messages WHERE author != '' AND author IS NOT NULL")['n'] ?? 0);

$search  = mb_substr(trim($_GET['q'] ?? ''), 0, 100, 'UTF-8');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 18;

$where  = '';
$params = [];
if ($search !== '') {
    $where    = 'WHERE (message LIKE ? OR author LIKE ?)';
    $term     = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
}

$totalRows  = (int)(dbRow("SELECT COUNT(*) AS n FROM motivational_messages $where", $params)['n'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$messages = dbQuery(
    "SELECT * FROM motivational_messages $where ORDER BY id ASC LIMIT ? OFFSET ?",
    [...$params, $perPage, $offset]
);

$allIds  = array_column(dbQuery('SELECT id FROM motivational_messages ORDER BY id ASC'), 'id');
$idToPos = array_flip(array_map('intval', $allIds));

$nextIdx = $total > 0 ? (($todayIdx + 1) % $total) : 0;
$nextMsg = $total > 0 ? dbRow('SELECT message, author FROM motivational_messages ORDER BY id ASC LIMIT 1 OFFSET ?', [$nextIdx]) : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Frases — florescer Admin</title>
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
  .aside{width:var(--sw);min-height:100vh;position:fixed;top:0;left:0;background:var(--ink2);border-right:1px solid var(--border2);display:flex;flex-direction:column;z-index:50}
  .a-logo{padding:1.1rem 1.2rem .9rem;border-bottom:1px solid var(--border2);display:flex;align-items:center;gap:.6rem;flex-shrink:0}
  .a-logo-mark{width:32px;height:32px;border-radius:9px;flex-shrink:0;background:linear-gradient(135deg,var(--leaf),#2d6a4f);display:flex;align-items:center;justify-content:center;font-size:.95rem}
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
  .main{margin-left:var(--sw);flex:1;min-width:0;display:flex;flex-direction:column}
  .topbar{height:var(--hh);position:sticky;top:0;z-index:40;background:rgba(11,26,18,.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--border2);display:flex;align-items:center;justify-content:space-between;padding:0 1.5rem;flex-shrink:0}
  .tb-title{font-family:var(--serif);font-size:1rem;color:var(--leaf3)}
  .btn-new{padding:.38rem .9rem;background:var(--leaf);border:none;border-radius:var(--r2);color:#0b1a12;font-family:var(--sans);font-size:.75rem;font-weight:600;cursor:pointer;transition:all var(--t)}
  .btn-new:hover{background:var(--leaf2)}
  .page{padding:1.25rem 1.5rem;display:flex;flex-direction:column;gap:1rem;flex:1}
  .today-card{background:var(--ink2);border:1px solid var(--border);border-radius:var(--r);padding:1.1rem 1.25rem;position:relative;overflow:hidden}
  .today-card::after{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,var(--leaf),transparent);opacity:.5}
  .today-tag{display:flex;align-items:center;gap:.35rem;font-size:.6rem;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);margin-bottom:.6rem}
  .today-dot{width:5px;height:5px;border-radius:50%;background:var(--leaf);animation:pulse 2.5s infinite}
  @keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
  .today-quote{font-family:var(--serif);font-size:1.05rem;font-style:italic;color:var(--leaf3);line-height:1.5;margin-bottom:.4rem}
  .today-author{font-size:.72rem;color:var(--text3)}
  .today-footer{display:flex;align-items:center;gap:1rem;margin-top:.65rem;padding-top:.55rem;border-top:1px solid var(--border2)}
  .today-meta{font-size:.62rem;color:var(--text3)}
  .today-next{font-size:.62rem;color:var(--muted)}
  .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:.65rem}
  .stat{background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);padding:.8rem 1rem;display:flex;align-items:center;gap:.7rem}
  .stat-ico{font-size:1.2rem;flex-shrink:0;opacity:.65}
  .stat-val{font-family:var(--serif);font-size:1.25rem;color:var(--leaf3);line-height:1}
  .stat-lbl{font-size:.6rem;color:var(--text3);margin-top:.1rem}
  .toolbar{display:flex;align-items:center;gap:.55rem}
  .search-wrap{position:relative;flex:1;max-width:340px}
  .search-wrap svg{position:absolute;left:.65rem;top:50%;transform:translateY(-50%);width:13px;height:13px;color:var(--muted);pointer-events:none}
  .search-wrap .inp{width:100%;padding-left:2rem}
  .inp{padding:.46rem .75rem;background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r2);color:var(--text);font-family:var(--sans);font-size:.76rem;outline:none;transition:all var(--t)}
  .inp:focus{border-color:var(--leaf);background:rgba(82,183,136,.05)}
  .inp::placeholder{color:var(--text3)}
  .btn-search{padding:.46rem .9rem;background:var(--leaf);border:none;border-radius:var(--r2);color:#0b1a12;font-family:var(--sans);font-size:.75rem;font-weight:600;cursor:pointer}
  .clear-link{font-size:.7rem;color:var(--text3);text-decoration:none}
  .clear-link:hover{color:var(--leaf)}
  .result-count{font-size:.67rem;color:var(--text3);margin-left:auto}
  .list-card{background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);overflow:hidden}
  .list-head{padding:.6rem 1rem;border-bottom:1px solid var(--border2);display:flex;align-items:center;justify-content:space-between}
  .list-head-title{font-size:.72rem;font-weight:500;color:var(--text2)}
  .list-head-meta{font-size:.63rem;color:var(--text3)}
  .msg-row{padding:.65rem 1rem;border-bottom:1px solid var(--border2);display:grid;grid-template-columns:2.2rem 1fr auto;gap:.75rem;align-items:start;transition:background var(--t)}
  .msg-row:last-child{border-bottom:none}
  .msg-row:hover{background:rgba(82,183,136,.025)}
  .msg-row.is-today{background:rgba(82,183,136,.05);border-left:2px solid var(--leaf);padding-left:calc(1rem - 2px)}
  .msg-pos{font-family:var(--serif);font-size:.78rem;color:var(--muted2);text-align:center;padding-top:.1rem}
  .msg-pos.today{color:var(--leaf)}
  .msg-text{font-size:.8rem;color:var(--text);line-height:1.5;font-style:italic}
  .msg-author{font-size:.67rem;color:var(--text3);margin-top:.12rem}
  .today-pill{display:inline-flex;align-items:center;gap:.2rem;padding:.08rem .38rem;border-radius:20px;background:rgba(82,183,136,.12);color:var(--leaf);font-size:.58rem;font-weight:600;border:1px solid rgba(82,183,136,.18);margin-left:.4rem;font-style:normal;vertical-align:middle}
  .msg-actions{display:flex;gap:.28rem;align-items:center;flex-shrink:0}
  .act{padding:.26rem .5rem;border-radius:var(--r2);border:1px solid var(--border2);background:none;color:var(--text3);font-size:.72rem;cursor:pointer;transition:all var(--t)}
  .act:hover{border-color:var(--leaf);color:var(--leaf2);background:rgba(82,183,136,.05)}
  .act.del:hover{border-color:rgba(224,82,82,.25);color:var(--red);background:rgba(224,82,82,.05)}
  .empty{padding:2rem;text-align:center;color:var(--text3);font-size:.75rem}
  .pagination{display:flex;align-items:center;justify-content:center;gap:.3rem;padding:.6rem;border-top:1px solid var(--border2)}
  .pg{padding:.26rem .58rem;border-radius:var(--r2);border:1px solid var(--border2);background:none;color:var(--text3);font-size:.71rem;cursor:pointer;text-decoration:none;transition:all var(--t)}
  .pg:hover{border-color:var(--leaf);color:var(--leaf2)}
  .pg.cur{background:var(--leaf);border-color:var(--leaf);color:#0b1a12;font-weight:600}
  .pg.off{opacity:.2;pointer-events:none}
  .pg-info{font-size:.63rem;color:var(--text3);margin:0 .3rem}
  .overlay{position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.7);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;padding:1rem;opacity:0;pointer-events:none;transition:opacity var(--t)}
  .overlay.open{opacity:1;pointer-events:all}
  .modal{background:var(--ink2);border:1px solid var(--border);border-radius:var(--r);width:100%;max-width:480px;box-shadow:0 24px 60px rgba(0,0,0,.6);transform:translateY(12px) scale(.97);transition:transform var(--t)}
  .overlay.open .modal{transform:none}
  .modal-sm .modal{max-width:360px}
  .modal-head{padding:.75rem 1rem;border-bottom:1px solid var(--border2);display:flex;align-items:center;justify-content:space-between;border-radius:var(--r) var(--r) 0 0}
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
  textarea.fc{resize:vertical;min-height:90px;line-height:1.55}
  .char-row{display:flex;justify-content:space-between;align-items:center;margin-top:.2rem}
  .char-count{font-size:.63rem;color:var(--text3)}
  .char-count.warn{color:var(--gold)}.char-count.over{color:var(--red)}
  .preview-label{font-size:.6rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:.25rem}
  .preview-box{background:rgba(255,255,255,.02);border:1px solid var(--border2);border-radius:var(--r2);padding:.6rem .8rem;font-size:.78rem;font-style:italic;color:var(--text2);line-height:1.5;min-height:44px}
  .btn-primary{padding:.5rem 1rem;background:var(--leaf);border:none;border-radius:var(--r2);color:#0b1a12;font-family:var(--sans);font-size:.77rem;font-weight:600;cursor:pointer;transition:all var(--t)}
  .btn-primary:hover{background:var(--leaf2)}
  .btn-primary:disabled{opacity:.5;pointer-events:none}
  .btn-ghost{padding:.5rem 1rem;background:none;border:1px solid var(--border2);border-radius:var(--r2);color:var(--text3);font-family:var(--sans);font-size:.77rem;cursor:pointer;transition:all var(--t)}
  .btn-ghost:hover{border-color:var(--border);color:var(--text2)}
  .btn-danger{padding:.5rem 1rem;background:rgba(224,82,82,.1);border:1px solid rgba(224,82,82,.2);border-radius:var(--r2);color:var(--red);font-family:var(--sans);font-size:.77rem;cursor:pointer;transition:all var(--t)}
  .btn-danger:hover{background:rgba(224,82,82,.18)}
  .btn-danger:disabled{opacity:.5;pointer-events:none}
  .del-preview{background:rgba(255,255,255,.02);border:1px solid var(--border2);border-radius:var(--r2);padding:.6rem .8rem;margin-bottom:.7rem;font-size:.78rem;font-style:italic;color:var(--text2);line-height:1.5}
  .del-warn{font-size:.7rem;color:var(--text3);line-height:1.6}
  #toasts{position:fixed;bottom:1rem;right:1rem;z-index:999;display:flex;flex-direction:column;gap:.3rem;pointer-events:none}
  .toast{background:var(--ink2);color:var(--text);border:1px solid var(--border);border-radius:var(--r2);padding:.48rem .8rem;font-size:.71rem;display:flex;align-items:center;gap:.38rem;animation:slideIn .2s ease both;max-width:260px;pointer-events:all;box-shadow:0 4px 20px rgba(0,0,0,.4)}
  .toast.ok{border-left:2px solid var(--leaf)}.toast.err{border-left:2px solid var(--red)}
  @keyframes slideIn{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:none}}
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
    <a class="a-link" href="sessoes.php"><span class="a-link-ico">⊘</span>Sessões</a>
    <span class="a-grp">Conteúdo</span>
    <a class="a-link active" href="mensagens.php"><span class="a-link-ico">⊡</span>Frases do dia</a>
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
    <span class="tb-title">Frases Motivacionais</span>
    <button class="btn-new" onclick="openCreate()">+ Nova frase</button>
  </header>

  <main class="page">

    <?php if ($todayMsg): ?>
    <div class="today-card">
      <div class="today-tag"><span class="today-dot"></span>Exibida hoje · <?= date('d/m/Y') ?></div>
      <div class="today-quote">"<?= htmlspecialchars($todayMsg['message'], ENT_QUOTES) ?>"</div>
      <?php if (!empty($todayMsg['author'])): ?>
        <div class="today-author">— <?= htmlspecialchars($todayMsg['author'], ENT_QUOTES) ?></div>
      <?php endif; ?>
      <div class="today-footer">
        <span class="today-meta">Posição <?= $todayIdx + 1 ?> de <?= $total ?> · ID #<?= $todayId ?></span>
        <?php if ($nextMsg): ?>
          <span class="today-next">Amanhã: "<?= htmlspecialchars(mb_substr($nextMsg['message'], 0, 55, 'UTF-8'), ENT_QUOTES) ?>…"</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="stats-row">
      <div class="stat"><span class="stat-ico">💬</span><div><div class="stat-val"><?= $total ?></div><div class="stat-lbl">Total de frases</div></div></div>
      <div class="stat"><span class="stat-ico">✍️</span><div><div class="stat-val"><?= $totalAuthors ?></div><div class="stat-lbl">Autores distintos</div></div></div>
      <div class="stat"><span class="stat-ico">🔄</span><div><div class="stat-val"><?= $total > 0 ? round($total / 365, 1) : 0 ?>x</div><div class="stat-lbl">Rotações por ano</div></div></div>
    </div>

    <form method="GET" class="toolbar">
      <div class="search-wrap">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input class="inp" type="text" name="q" placeholder="Buscar frase ou autor…" value="<?= htmlspecialchars($search, ENT_QUOTES) ?>" maxlength="100"/>
      </div>
      <button class="btn-search" type="submit">Buscar</button>
      <?php if ($search): ?><a class="clear-link" href="mensagens.php">✕ Limpar</a><?php endif; ?>
      <span class="result-count"><?= $totalRows ?> frase(s)</span>
    </form>

    <div class="list-card">
      <div class="list-head">
        <span class="list-head-title">Todas as frases</span>
        <span class="list-head-meta">Pág. <?= $page ?>/<?= $totalPages ?></span>
      </div>

      <?php if (empty($messages)): ?>
        <div class="empty">
          <?= $search ? 'Nenhuma frase encontrada para "' . htmlspecialchars($search, ENT_QUOTES) . '".' : 'Nenhuma frase cadastrada.' ?>
        </div>
      <?php else: ?>

        <?php foreach ($messages as $m):
          $mid     = (int)$m['id'];
          $posGlob = isset($idToPos[$mid]) ? (int)$idToPos[$mid] : -1;
          $isToday = ($mid === $todayId);
        ?>
        <div class="msg-row <?= $isToday ? 'is-today' : '' ?>" id="row-<?= $mid ?>">

          <div class="msg-pos <?= $isToday ? 'today' : '' ?>">
            <?= $posGlob >= 0 ? $posGlob + 1 : '—' ?>
          </div>

          <div class="msg-content">
            <div class="msg-text">
              "<?= htmlspecialchars($m['message'], ENT_QUOTES) ?>"
              <?php if ($isToday): ?><span class="today-pill">📅 Hoje</span><?php endif; ?>
            </div>
            <?php if (!empty($m['author'])): ?>
              <div class="msg-author">— <?= htmlspecialchars($m['author'], ENT_QUOTES) ?></div>
            <?php endif; ?>
          </div>

          <div class="msg-actions">
            <button class="act" title="Editar"
              data-msg="<?= htmlspecialchars($m['message'], ENT_QUOTES) ?>"
              data-author="<?= htmlspecialchars($m['author'] ?? '', ENT_QUOTES) ?>"
              onclick="openEdit(<?= $mid ?>, this)">✏</button>
            <button class="act del" title="Excluir"
              data-preview="<?= htmlspecialchars(mb_substr($m['message'], 0, 50, 'UTF-8'), ENT_QUOTES) ?>"
              onclick="confirmDel(<?= $mid ?>, this.dataset.preview)">🗑</button>
          </div>

        </div>
        <?php endforeach; ?>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php
          function pgUrl(int $p, string $q): string {
              $qs = http_build_query(array_filter(['page' => $p > 1 ? $p : null, 'q' => $q ?: null]));
              return 'mensagens.php' . ($qs ? '?' . $qs : '');
          }
          ?>
          <a class="pg <?= $page <= 1 ? 'off' : '' ?>" href="<?= pgUrl($page - 1, $search) ?>">‹</a>
          <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <a class="pg <?= $p === $page ? 'cur' : '' ?>" href="<?= pgUrl($p, $search) ?>"><?= $p ?></a>
          <?php endfor; ?>
          <a class="pg <?= $page >= $totalPages ? 'off' : '' ?>" href="<?= pgUrl($page + 1, $search) ?>">›</a>
          <span class="pg-info"><?= $page ?>/<?= $totalPages ?></span>
        </div>
        <?php endif; ?>

      <?php endif; ?>
    </div>

  </main>
</div>

<!-- Modal criar/editar -->
<div class="overlay" id="overlayForm">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title" id="formTitle">Nova frase</span>
      <button class="modal-x" onclick="closeOverlay('overlayForm')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="editId"/>
      <div class="fg">
        <label class="fl" for="fText">Frase motivacional *</label>
        <textarea class="fc" id="fText" rows="4" placeholder="Digite a frase aqui…" maxlength="500"
                  oninput="updatePreview(); countChars(this, 500)"></textarea>
        <div class="char-row">
          <span></span>
          <span class="char-count" id="charCount">0 / 500</span>
        </div>
      </div>
      <div class="fg">
        <label class="fl" for="fAuthor">Autor / Fonte <span style="opacity:.5">(opcional)</span></label>
        <input class="fc" type="text" id="fAuthor" placeholder="Ex: Confúcio, Einstein, Anônimo…" maxlength="100"/>
      </div>
      <div class="fg">
        <div class="preview-label">Preview</div>
        <div class="preview-box" id="previewBox">A frase aparecerá aqui…</div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-ghost" onclick="closeOverlay('overlayForm')">Cancelar</button>
      <button class="btn-primary" id="btnSave" onclick="submitForm()">Salvar frase</button>
    </div>
  </div>
</div>

<!-- Modal excluir -->
<div class="overlay modal-sm" id="overlayDel">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title">Excluir frase</span>
      <button class="modal-x" onclick="closeOverlay('overlayDel')">✕</button>
    </div>
    <div class="modal-body">
      <div class="del-preview" id="delPreview"></div>
      <p class="del-warn">Esta ação é irreversível.</p>
    </div>
    <div class="modal-foot">
      <button class="btn-ghost" onclick="closeOverlay('overlayDel')">Cancelar</button>
      <button class="btn-danger" id="btnDel" onclick="submitDel()">Excluir</button>
    </div>
  </div>
</div>

<div id="toasts"></div>

<script>
const API = '/florescer/admin/api/mensagens.php';

function toast(msg, type='ok', ms=3500) {
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

function countChars(el,max){
  const n=el.value.length, c=document.getElementById('charCount');
  c.textContent=`${n} / ${max}`;
  c.className='char-count'+(n>max*.9?' warn':'')+(n>=max?' over':'');
}

function updatePreview(){
  const t=document.getElementById('fText').value.trim();
  document.getElementById('previewBox').textContent=t||'A frase aparecerá aqui…';
}

function openCreate(){
  document.getElementById('editId').value='';
  document.getElementById('formTitle').textContent='Nova frase';
  document.getElementById('fText').value='';
  document.getElementById('fAuthor').value='';
  document.getElementById('previewBox').textContent='A frase aparecerá aqui…';
  document.getElementById('charCount').textContent='0 / 500';
  document.getElementById('charCount').className='char-count';
  openOverlay('overlayForm');
  setTimeout(()=>document.getElementById('fText').focus(),150);
}

function openEdit(id, btn){
  document.getElementById('editId').value=id;
  document.getElementById('formTitle').textContent='Editar frase';
  document.getElementById('fText').value=btn.dataset.msg||'';
  document.getElementById('fAuthor').value=btn.dataset.author||'';
  updatePreview();
  countChars(document.getElementById('fText'),500);
  openOverlay('overlayForm');
  setTimeout(()=>document.getElementById('fText').focus(),150);
}

async function submitForm(){
  const id=document.getElementById('editId').value;
  const text=document.getElementById('fText').value.trim();
  const author=document.getElementById('fAuthor').value.trim();
  if(!text){toast('A frase não pode estar vazia.','err');return;}
  if(text.length>500){toast('Frase muito longa.','err');return;}
  const btn=document.getElementById('btnSave');
  btn.disabled=true;btn.textContent='Salvando…';
  try{
    const r=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action:id?'update':'create',id:id?+id:null,text,author})});
    const d=await r.json();
    if(d.success){toast(id?'Frase atualizada.':'Frase criada! 💬');closeOverlay('overlayForm');setTimeout(()=>location.reload(),600);}
    else toast(d.message||'Erro ao salvar.','err');
  }catch{toast('Erro de conexão.','err');}
  finally{btn.disabled=false;btn.textContent='Salvar frase';}
}

let _delId=null;
function confirmDel(id,preview){
  _delId=id;
  document.getElementById('delPreview').textContent='"'+(preview||'')+(preview&&preview.length>=50?'…':'')+'"';
  openOverlay('overlayDel');
}

async function submitDel(){
  if(!_delId)return;
  const btn=document.getElementById('btnDel');
  btn.disabled=true;btn.textContent='Excluindo…';
  try{
    const r=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action:'delete',id:_delId})});
    const d=await r.json();
    if(d.success){
      toast('Frase excluída.');
      closeOverlay('overlayDel');
      const row=document.getElementById('row-'+_delId);
      if(row){row.style.transition='.3s';row.style.opacity='0';}
      setTimeout(()=>location.reload(),420);
    }else toast(d.message||'Erro.','err');
  }catch{toast('Erro de conexão.','err');}
  finally{btn.disabled=false;btn.textContent='Excluir';}
}

function doLogout(){
  fetch('../api/auth_admin.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'logout'})})
    .finally(()=>{window.location.href='/florescer/index.php';});
}
</script>
</body>
</html>