<?php
// ============================================================
// /public/views/store.php — florescer v2.1
// Cursos por categoria — imagens funcionando + divisão visual
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
$currentPage = 'store';

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
$plant = ['emoji'=>'🌱','name'=>'Semente','pct'=>0];
foreach ($stages as [$mn,$mx,$em,$nm]) {
    if ($streak>=$mn && $streak<=$mx) {
        $r2=$mx<PHP_INT_MAX?$mx-$mn+1:1;
        $plant=['emoji'=>$em,'name'=>$nm,'pct'=>$mx<PHP_INT_MAX?min(100,round(($streak-$mn)/$r2*100)):100];
        break;
    }
}
if (!isset($_SESSION['active_objective'])) {
    $ao = dbRow('SELECT id FROM objectives WHERE user_id=? AND is_active=1 ORDER BY created_at DESC LIMIT 1', [$userId]);
    if (!$ao) $ao = dbRow('SELECT id FROM objectives WHERE user_id=? ORDER BY created_at DESC LIMIT 1', [$userId]);
    $_SESSION['active_objective'] = $ao['id'] ?? null;
}
$activeObjId = $_SESSION['active_objective'];
$allObjs = dbQuery('SELECT id, name FROM objectives WHERE user_id=? ORDER BY is_active DESC, created_at DESC', [$userId]);

// ── Filtros ───────────────────────────────────────────────────
$gradeFilter    = trim($_GET['grade']    ?? '');
$categoryFilter = trim($_GET['category'] ?? '');

// ── Busca itens ───────────────────────────────────────────────
$tableExists = (bool)dbRow("SHOW TABLES LIKE 'store_items'");
$items       = [];
$grades      = [];
$categories  = [];

if ($tableExists) {
    // Séries disponíveis
    $grades = dbQuery(
        "SELECT DISTINCT grade_level FROM store_items
         WHERE is_active=1 AND grade_level IS NOT NULL AND grade_level != ''
         ORDER BY grade_level ASC"
    );

    // Categorias disponíveis
    $categories = dbQuery(
        "SELECT DISTINCT category FROM store_items
         WHERE is_active=1 AND category IS NOT NULL AND category != ''
         ORDER BY category ASC"
    );

    // Monta WHERE dinâmico
    $where  = ['is_active=1'];
    $params = [];
    if ($gradeFilter)    { $where[] = 'grade_level=?';  $params[] = $gradeFilter; }
    if ($categoryFilter) { $where[] = 'category=?';     $params[] = $categoryFilter; }

    $items = dbQuery(
        'SELECT * FROM store_items WHERE ' . implode(' AND ', $where) .
        ' ORDER BY COALESCE(NULLIF(category,""), "zz"), sort_order ASC, id ASC',
        $params
    );
}

// Agrupa por categoria para renderização
$grouped = [];
foreach ($items as $item) {
    $cat = $item['category'] ?: 'Outros';
    $grouped[$cat][] = $item;
}

// Avatar
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
  <title>florescer — Cursos</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700;9..144,900&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
  <style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{
    --g950:#0d1f16;--g900:#132a1e;--g800:#1a3a2a;--g700:#1e4d35;
    --g600:#2d6a4f;--g500:#40916c;--g400:#52b788;--g300:#74c69d;
    --g200:#b7e4c7;--g50:#f0faf4;
    --n800:#1c1c1a;--n100:#f5f2ee;--n50:#faf8f5;--white:#fff;
    --gold:#c9a84c;--gold-l:#fef3c7;--red:#dc2626;--red-l:#fee2e2;
    --sw:240px;--hh:58px;
    --fd:'Fraunces',Georgia,serif;--fb:'Inter',system-ui,sans-serif;
    --r:12px;--rs:7px;
    --d:.22s;--e:cubic-bezier(.4,0,.2,1);
    --sh0:0 1px 3px rgba(0,0,0,.06);--sh1:0 2px 8px rgba(0,0,0,.07);
    --sh2:0 4px 18px rgba(0,0,0,.1);--sh3:0 12px 32px rgba(0,0,0,.12);
  }
  html,body{height:100%}
  body{font-family:var(--fb);background:var(--n50);color:var(--n800);display:flex;overflow-x:hidden;-webkit-font-smoothing:antialiased}
  ::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:rgba(64,145,108,.2);border-radius:2px}

  /* ── Main ─────────────────────────────────────────────────── */
  .main{margin-left:var(--sw);flex:1;min-height:100vh;display:flex;flex-direction:column;min-width:0}

  /* ── Topbar ───────────────────────────────────────────────── */
  .topbar{height:var(--hh);background:rgba(250,248,245,.92);backdrop-filter:blur(14px);border-bottom:1px solid rgba(0,0,0,.055);display:flex;align-items:center;justify-content:space-between;padding:0 1.8rem;position:sticky;top:0;z-index:40;flex-shrink:0}
  .tb-left{display:flex;align-items:center;gap:.8rem}
  .hamburger{display:none;flex-direction:column;gap:4px;cursor:pointer;background:none;border:none;padding:5px;border-radius:6px}
  .hamburger span{display:block;width:19px;height:2px;background:var(--g600);border-radius:1px;transition:all var(--d) var(--e)}
  .hamburger.open span:nth-child(1){transform:translateY(6px) rotate(45deg)}
  .hamburger.open span:nth-child(2){opacity:0}
  .hamburger.open span:nth-child(3){transform:translateY(-6px) rotate(-45deg)}
  .tb-title{font-family:var(--fd);font-size:1rem;font-weight:600;color:var(--n800)}
  .xp-pill{display:flex;align-items:center;gap:.28rem;background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:50px;padding:.26rem .75rem;font-size:.75rem;font-weight:600;color:var(--g500);box-shadow:var(--sh0)}

  /* ── Conteúdo ─────────────────────────────────────────────── */
  .content{flex:1;padding:1.8rem 2rem}
  .page-head{margin-bottom:1.3rem}
  .page-title{font-family:var(--fd);font-size:1.4rem;font-weight:900;color:var(--n800);letter-spacing:-.03em}
  .page-sub{font-size:.82rem;color:#aaa;margin-top:.22rem}

  /* ── Banner ───────────────────────────────────────────────── */
  .info-banner{background:linear-gradient(135deg,var(--g800),var(--g950));border-radius:var(--r);padding:1rem 1.3rem;margin-bottom:1.3rem;display:flex;align-items:center;gap:.9rem;border:1px solid rgba(116,198,157,.1)}
  .info-banner-ico{font-size:1.5rem;flex-shrink:0}
  .info-banner-text{font-size:.79rem;color:rgba(240,250,244,.7);line-height:1.55}
  .info-banner-text strong{color:var(--g300);display:block;font-size:.83rem;margin-bottom:.12rem}

  /* ── Filtros ──────────────────────────────────────────────── */
  .filter-section{margin-bottom:1.3rem;display:flex;flex-direction:column;gap:.6rem}
  .filter-row{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
  .filter-label{font-size:.68rem;font-weight:700;color:#bbb;text-transform:uppercase;letter-spacing:.08em;white-space:nowrap;min-width:56px}
  .filter-chip{display:inline-flex;align-items:center;padding:.28rem .78rem;border-radius:50px;border:1px solid rgba(0,0,0,.1);background:var(--white);font-family:var(--fb);font-size:.74rem;font-weight:500;color:#888;cursor:pointer;text-decoration:none;transition:all var(--d) var(--e);box-shadow:var(--sh0);white-space:nowrap}
  .filter-chip:hover{border-color:rgba(64,145,108,.25);color:var(--g500);background:var(--g50)}
  .filter-chip.active{background:var(--g500);color:var(--white);border-color:var(--g500);box-shadow:0 2px 8px rgba(64,145,108,.22)}
  .filter-chip.active-cat{background:var(--g700);color:var(--g200);border-color:var(--g600);box-shadow:0 2px 8px rgba(30,77,53,.3)}

  /* ── Seção de categoria ───────────────────────────────────── */
  .cat-section{margin-bottom:2.2rem;animation:fadeUp .22s var(--e) both}
  @keyframes fadeUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
  .cat-header{display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding-bottom:.65rem;border-bottom:2px solid rgba(0,0,0,.06)}
  .cat-icon{width:36px;height:36px;border-radius:9px;background:var(--g50);border:1px solid rgba(64,145,108,.15);display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex-shrink:0}
  .cat-name{font-family:var(--fd);font-size:1.05rem;font-weight:700;color:var(--n800);letter-spacing:-.02em}
  .cat-count{font-size:.72rem;color:#bbb;margin-left:.3rem;font-weight:400;font-family:var(--fb)}

  /* ── Grid de cards ────────────────────────────────────────── */
  .cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(262px,1fr));gap:1rem}

  /* ── Card ─────────────────────────────────────────────────── */
  .course-card{background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r);overflow:hidden;box-shadow:var(--sh0);display:flex;flex-direction:column;transition:transform var(--d) var(--e),box-shadow var(--d) var(--e);position:relative}
  .course-card:hover{transform:translateY(-4px);box-shadow:var(--sh2)}

  /* Thumbnail — aspect-ratio fixo, imagem real ou fallback */
  .card-thumb{position:relative;width:100%;aspect-ratio:16/9;overflow:hidden;background:linear-gradient(135deg,var(--g800),var(--g950));flex-shrink:0}
  .card-thumb img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;transition:transform .4s var(--e)}
  .course-card:hover .card-thumb img{transform:scale(1.04)}
  .card-thumb-placeholder{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.4rem}
  .card-thumb-placeholder span:first-child{font-size:2.2rem;opacity:.4}
  .card-thumb-placeholder span:last-child{font-size:.7rem;color:rgba(116,198,157,.3);letter-spacing:.06em;text-transform:uppercase}

  /* Badges sobre a imagem */
  .card-badge{position:absolute;top:.55rem;left:.55rem;padding:.18rem .52rem;border-radius:20px;font-size:.63rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;z-index:2}
  .badge-popular{background:var(--gold);color:#3a2800}
  .badge-novo{background:var(--g400);color:var(--g950)}
  .card-grade-tag{position:absolute;top:.55rem;right:.55rem;padding:.16rem .48rem;border-radius:20px;font-size:.62rem;font-weight:600;background:rgba(0,0,0,.6);color:#fff;backdrop-filter:blur(6px);z-index:2}

  /* Corpo */
  .card-body{padding:.95rem 1rem;flex:1;display:flex;flex-direction:column}
  .card-category-label{font-size:.63rem;font-weight:700;color:var(--g500);text-transform:uppercase;letter-spacing:.07em;margin-bottom:.28rem}
  .card-title{font-family:var(--fd);font-size:.93rem;font-weight:700;color:var(--n800);line-height:1.35;margin-bottom:.45rem;letter-spacing:-.015em}
  .card-desc{font-size:.77rem;color:#888;line-height:1.6;flex:1;margin-bottom:.8rem;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}

  /* Rodapé */
  .card-foot{display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding-top:.6rem;border-top:1px solid rgba(0,0,0,.05)}
  .card-price{font-family:var(--fd);font-size:.85rem;font-weight:700;color:var(--n800)}
  .card-price.free{color:var(--g500)}
  .card-price.empty{display:none}
  .btn-access{display:inline-flex;align-items:center;gap:.3rem;padding:.4rem .9rem;background:linear-gradient(135deg,var(--g500),var(--g600));color:var(--white);border:none;border-radius:50px;font-family:var(--fb);font-size:.76rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all var(--d) var(--e);box-shadow:0 2px 8px rgba(64,145,108,.2);white-space:nowrap}
  .btn-access:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(64,145,108,.32)}

  /* ── Empty ────────────────────────────────────────────────── */
  .empty{text-align:center;padding:3.5rem 1.5rem;color:#bbb;background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r)}
  .empty-ico{font-size:2.5rem;opacity:.3;display:block;margin-bottom:.7rem}
  .empty p{font-size:.83rem;line-height:1.7}
  .empty a{color:var(--g500)}

  /* ── Toast ────────────────────────────────────────────────── */
  .toast-wrap{position:fixed;bottom:1.4rem;right:1.4rem;z-index:500;display:flex;flex-direction:column;gap:.4rem;pointer-events:none}
  .toast{background:var(--n800);color:#eee;padding:.62rem .95rem;border-radius:var(--rs);font-size:.79rem;display:flex;align-items:center;gap:.4rem;animation:tin .24s var(--e) both;max-width:270px;box-shadow:var(--sh3);pointer-events:all}
  .toast.ok{border-left:3px solid var(--g400)}.toast.err{border-left:3px solid #f87171}.toast.info{border-left:3px solid var(--gold)}
  @keyframes tin{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}

  /* ── Responsivo ───────────────────────────────────────────── */
  @media(max-width:768px){
    .main{margin-left:0}.hamburger{display:flex}
    .topbar{padding:0 1.1rem}.content{padding:1.2rem 1rem}
  }
  @media(max-width:520px){.cards-grid{grid-template-columns:1fr}}
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

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
  <header class="topbar">
    <div class="tb-left">
      <button class="hamburger" id="hamburger" onclick="toggleSidebar()">
        <span></span><span></span><span></span>
      </button>
      <span class="tb-title">🎓 Cursos</span>
    </div>
    <div class="xp-pill">⭐ <?= number_format($xp) ?> XP</div>
  </header>

  <main class="content">

    <div class="page-head">
      <div class="page-title">Cursos & Materiais</div>
      <div class="page-sub">Recursos selecionados para acelerar seus estudos</div>
    </div>

    <!-- Banner -->
    <div class="info-banner">
      <span class="info-banner-ico">🎓</span>
      <div class="info-banner-text">
        <strong>Cursos recomendados</strong>
        Ao clicar em "Acessar curso" você será redirecionado para a plataforma parceira.
      </div>
    </div>

    <?php if (!$tableExists || (empty($grades) && empty($categories) && empty($items))): ?>
    <!-- Nenhum curso cadastrado -->
    <div class="empty">
      <span class="empty-ico">🎓</span>
      <p>Nenhum curso disponível no momento.<br>Novos cursos serão adicionados em breve.</p>
    </div>
    <?php else: ?>

    <!-- Filtros -->
    <div class="filter-section">

      <?php if (!empty($grades)): ?>
      <div class="filter-row">
        <span class="filter-label">Série</span>
        <a class="filter-chip <?= $gradeFilter===''?'active':'' ?>"
           href="store.php<?= $categoryFilter?'?category='.urlencode($categoryFilter):'' ?>">Todas</a>
        <?php foreach ($grades as $g):
          $gv = htmlspecialchars($g['grade_level'], ENT_QUOTES);
          $href = 'store.php?grade=' . urlencode($g['grade_level']);
          if ($categoryFilter) $href .= '&category=' . urlencode($categoryFilter);
        ?>
          <a class="filter-chip <?= $gradeFilter===$g['grade_level']?'active':'' ?>"
             href="<?= $href ?>"><?= $gv ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($categories)): ?>
      <div class="filter-row">
        <span class="filter-label">Área</span>
        <a class="filter-chip <?= $categoryFilter===''?'active-cat':'' ?>"
           href="store.php<?= $gradeFilter?'?grade='.urlencode($gradeFilter):'' ?>">Todas</a>
        <?php foreach ($categories as $cat):
          $cv = htmlspecialchars($cat['category'], ENT_QUOTES);
          $href = 'store.php?category=' . urlencode($cat['category']);
          if ($gradeFilter) $href .= '&grade=' . urlencode($gradeFilter);
        ?>
          <a class="filter-chip <?= $categoryFilter===$cat['category']?'active-cat':'' ?>"
             href="<?= $href ?>"><?= $cv ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </div>

    <!-- Cursos agrupados por categoria -->
    <?php if (empty($grouped)): ?>
    <div class="empty">
      <span class="empty-ico">🔍</span>
      <p>Nenhum curso encontrado para esse filtro.<br>
        <a href="store.php">Ver todos os cursos →</a></p>
    </div>
    <?php else: ?>

    <?php
    // Ícones automáticos por categoria
    $catIcons = [
        'matemática'      => '📐','matematica'       => '📐',
        'português'       => '📝','portugues'        => '📝',
        'história'        => '🏛️','historia'         => '🏛️',
        'geografia'       => '🌍',
        'biologia'        => '🧬',
        'física'          => '⚡','fisica'           => '⚡',
        'química'         => '🧪','quimica'          => '🧪',
        'inglês'          => '🌐','ingles'           => '🌐',
        'artes'           => '🎨',
        'educação física' => '⚽','educacao fisica'  => '⚽',
        'filosofia'       => '🤔',
        'sociologia'      => '👥',
        'ciências'        => '🔬','ciencias'         => '🔬',
        'vestibular'      => '🎯',
        'enem'            => '🎯',
        'redação'         => '✍️','redacao'          => '✍️',
        'outros'          => '📚',
    ];
    function getCatIcon(string $cat): string {
        global $catIcons;
        $key = mb_strtolower(trim($cat), 'UTF-8');
        foreach ($catIcons as $k=>$ico) {
            if (strpos($key, $k) !== false) return $ico;
        }
        return '📚';
    }
    ?>

    <?php foreach ($grouped as $catName => $catItems):
      $catCount = count($catItems);
      $catIco = getCatIcon($catName);
    ?>
    <div class="cat-section">
      <div class="cat-header">
        <div class="cat-icon"><?= $catIco ?></div>
        <div>
          <span class="cat-name"><?= htmlspecialchars($catName, ENT_QUOTES) ?></span>
          <span class="cat-count"><?= $catCount ?> curso<?= $catCount!==1?'s':'' ?></span>
        </div>
      </div>

      <div class="cards-grid">
        <?php foreach ($catItems as $item):
          $title    = htmlspecialchars($item['title'],        ENT_QUOTES);
          $desc     = htmlspecialchars($item['description']   ?? '', ENT_QUOTES);
          $url      = htmlspecialchars($item['affiliate_url'], ENT_QUOTES);
          $grade    = htmlspecialchars($item['grade_level']   ?? '', ENT_QUOTES);
          $price    = htmlspecialchars($item['price_display'] ?? '', ENT_QUOTES);
          $badge    = $item['badge'] ?? '';
          $imgUrl   = trim($item['image_url'] ?? '');
          $isFree   = in_array(mb_strtolower($price,'UTF-8'), ['grátis','gratuito','free','']);
        ?>
        <div class="course-card">

          <!-- Thumbnail -->
          <div class="card-thumb">
            <?php if ($imgUrl): ?>
              <img src="<?= htmlspecialchars($imgUrl, ENT_QUOTES) ?>"
                   alt="<?= $title ?>"
                   loading="lazy"
                   onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"/>
              <div class="card-thumb-placeholder" style="display:none">
                <span><?= $catIco ?></span>
                <span>Sem imagem</span>
              </div>
            <?php else: ?>
              <div class="card-thumb-placeholder">
                <span><?= $catIco ?></span>
                <span>Sem imagem</span>
              </div>
            <?php endif; ?>

            <!-- Badge -->
            <?php if ($badge === 'popular'): ?>
              <span class="card-badge badge-popular">⭐ Popular</span>
            <?php elseif ($badge === 'novo'): ?>
              <span class="card-badge badge-novo">✨ Novo</span>
            <?php endif; ?>

            <!-- Série -->
            <?php if ($grade): ?>
              <span class="card-grade-tag"><?= $grade ?></span>
            <?php endif; ?>
          </div>

          <!-- Corpo -->
          <div class="card-body">
            <div class="card-title"><?= $title ?></div>
            <?php if ($desc): ?>
              <div class="card-desc"><?= $desc ?></div>
            <?php endif; ?>

            <div class="card-foot">
              <?php if ($price): ?>
                <span class="card-price <?= $isFree?'free':'' ?>">
                  <?= $isFree ? '✓ Grátis' : $price ?>
                </span>
              <?php else: ?>
                <span></span>
              <?php endif; ?>
              <a class="btn-access"
                 href="<?= $url ?>"
                 target="_blank"
                 rel="noopener noreferrer"
                 onclick="trackClick(<?= (int)$item['id'] ?>)">
                Acessar ↗
              </a>
            </div>
          </div>

        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <?php endif; // empty grouped ?>
    <?php endif; // tableExists ?>

  </main>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
function toggleSidebar(){
  const sb=document.getElementById('sidebar'),
        ov=document.getElementById('sbOverlay'),
        hb=document.getElementById('hamburger');
  if(!sb) return;
  const open=sb.classList.toggle('open');
  if(ov) ov.classList.toggle('show',open);
  if(hb) hb.classList.toggle('open',open);
  document.body.style.overflow=open?'hidden':'';
}

function toast(msg,type='ok',ms=3000){
  const w=document.getElementById('toastWrap'),d=document.createElement('div');
  d.className=`toast ${type}`;
  d.innerHTML=`<span>${type==='ok'?'✅':type==='err'?'❌':'💡'}</span><span>${msg}</span>`;
  w.appendChild(d);
  setTimeout(()=>{d.style.opacity='0';setTimeout(()=>d.remove(),300);},ms);
}

function trackClick(id){
  // Registra clique — pode integrar com analytics futuramente
  console.log('[Store] curso acessado, id:', id);
}
</script>
</body>
</html>