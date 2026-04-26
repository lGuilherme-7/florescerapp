<?php
// ============================================================
// /professor/teachers/public/ranking.php
// Página pública — não exige login
// ============================================================

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/db.php';

// Filtros
$fSubject = trim($_GET['materia'] ?? '');
$fOrder   = in_array($_GET['ordem'] ?? '', ['rating','redacoes','preco']) ? $_GET['ordem'] : 'rating';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 12;
$offset   = ($page - 1) * $perPage;

// Busca matérias disponíveis para filtro
$allSubjects = dbQuery(
    'SELECT DISTINCT ts.name FROM teacher_subjects ts
     JOIN teachers t ON t.id = ts.teacher_id
     WHERE t.status = "ativo" ORDER BY ts.name ASC'
);

// Query principal
$where  = 'WHERE t.status = "ativo"';
$params = [];

if ($fSubject) {
    $where .= ' AND EXISTS (SELECT 1 FROM teacher_subjects ts WHERE ts.teacher_id = t.id AND ts.name = ?)';
    $params[] = $fSubject;
}

$orderSql = match($fOrder) {
    'redacoes' => 't.rating_count DESC, t.rating_avg DESC',
    'preco'    => 'min_price ASC, t.rating_avg DESC',
    default    => 't.rating_avg DESC, t.rating_count DESC',
};

$total      = (int)(dbRow("SELECT COUNT(*) AS n FROM teachers t $where", $params)['n'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$params2 = array_merge($params, [$perPage, $offset]);
$teachers = dbQuery(
    "SELECT t.id, t.name, t.bio, t.avatar_url, t.rating_avg, t.rating_count,
            t.rank_position, t.is_premium,
            (SELECT MIN(tp.price) FROM teacher_packages tp WHERE tp.teacher_id = t.id AND tp.is_active = 1) AS min_price,
            (SELECT COUNT(*) FROM teacher_redacoes tr WHERE tr.teacher_id = t.id AND tr.status = 'corrigida') AS total_redacoes,
            (SELECT COUNT(*) FROM teacher_orders to2 WHERE to2.teacher_id = t.id AND to2.type = 'aula' AND to2.status = 'pago') AS total_aulas,
            (SELECT GROUP_CONCAT(ts.name ORDER BY ts.name SEPARATOR '|') FROM teacher_subjects ts WHERE ts.teacher_id = t.id LIMIT 5) AS subjects
     FROM teachers t
     $where
     ORDER BY t.is_premium DESC, $orderSql
     LIMIT ? OFFSET ?",
    $params2
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Professores — florescer</title>
<meta name="description" content="Encontre professores verificados para correção de redações e aulas particulares. Avaliados por alunos reais."/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,900;1,9..144,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --g900:#0d2618;--g800:#14382a;--g700:#1a4a37;--g600:#225c44;
  --g500:#2d7a58;--g400:#3d9970;--g300:#55b88a;--g200:#8dd4b0;
  --g100:#c2ead6;--g50:#eaf6f0;--g25:#f4fbf7;
  --white:#fff;--n800:#111c16;--n400:#5a7a68;
  --n200:#b8d0c4;--n100:#daeae1;--n50:#f2f8f5;
  --gold:#c9a84c;--gold-l:#fdf3d8;--red:#d94040;
  --fd:'Fraunces',Georgia,serif;--fb:'DM Sans',system-ui,sans-serif;
  --r:14px;--rs:9px;--d:.2s;--e:cubic-bezier(.4,0,.2,1);
  --sh0:0 1px 3px rgba(0,0,0,.06);--sh1:0 4px 12px rgba(0,0,0,.07);
  --sh2:0 8px 24px rgba(0,0,0,.09);--sh3:0 20px 48px rgba(0,0,0,.12);
}
html{scroll-behavior:smooth}
body{font-family:var(--fb);background:var(--g25);color:var(--n800);-webkit-font-smoothing:antialiased;min-height:100vh}

/* ── Nav ── */
nav{background:var(--g900);padding:0 2rem;display:flex;align-items:center;justify-content:space-between;height:58px;position:sticky;top:0;z-index:50}
.nav-logo{display:flex;align-items:center;gap:.5rem;text-decoration:none}
.nav-logo-mark{width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,var(--g400),var(--g600));display:flex;align-items:center;justify-content:center;font-size:.85rem}
.nav-logo-name{font-family:var(--fd);font-size:1rem;font-weight:600;color:var(--g100)}
.nav-right{display:flex;align-items:center;gap:.6rem}
.nav-link{font-size:.8rem;color:rgba(194,234,214,.5);text-decoration:none;transition:color var(--d) var(--e)}
.nav-link:hover{color:var(--g200)}
.nav-btn{padding:.4rem .9rem;border-radius:50px;background:linear-gradient(135deg,var(--g400),var(--g600));color:#fff;border:none;font-family:var(--fb);font-size:.78rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all var(--d) var(--e)}
.nav-btn:hover{filter:brightness(1.08)}

/* ── Hero ── */
.hero{background:linear-gradient(135deg,var(--g800),var(--g900));padding:3.5rem 2rem 2.5rem;text-align:center}
.hero-tag{display:inline-flex;align-items:center;gap:.4rem;background:rgba(141,212,176,.1);border:1px solid rgba(141,212,176,.2);border-radius:50px;padding:.3rem .9rem;font-size:.72rem;font-weight:600;color:var(--g300);letter-spacing:.04em;margin-bottom:1.1rem}
.hero h1{font-family:var(--fd);font-size:clamp(1.8rem,4vw,2.8rem);font-weight:900;color:#fff;line-height:1.1;margin-bottom:.75rem;letter-spacing:-.02em}
.hero h1 em{color:var(--g300);font-style:italic;font-weight:400}
.hero-sub{font-size:.95rem;color:rgba(194,234,214,.55);max-width:480px;margin:0 auto 1.8rem;line-height:1.7;font-weight:300}
.hero-stats{display:flex;align-items:center;justify-content:center;gap:2rem;flex-wrap:wrap}
.hs-item{text-align:center}
.hs-val{font-family:var(--fd);font-size:1.6rem;font-weight:900;color:var(--g300);line-height:1}
.hs-lbl{font-size:.65rem;color:rgba(194,234,214,.35);text-transform:uppercase;letter-spacing:.07em;margin-top:.15rem}

/* ── Filtros ── */
.filters-bar{background:var(--white);border-bottom:1px solid var(--n100);padding:.85rem 2rem;display:flex;align-items:center;gap:.7rem;flex-wrap:wrap;position:sticky;top:58px;z-index:40}
.filter-label{font-size:.72rem;font-weight:600;color:var(--n400);white-space:nowrap}
.filter-select{padding:.42rem .8rem;background:var(--g25);border:1.5px solid var(--n100);border-radius:var(--rs);color:var(--n800);font-family:var(--fb);font-size:.8rem;outline:none;cursor:pointer;appearance:none;transition:all var(--d) var(--e)}
.filter-select:focus{border-color:var(--g400)}
.filter-sep{width:1px;height:18px;background:var(--n100);flex-shrink:0}
.filter-tag{padding:.35rem .8rem;border-radius:50px;border:1px solid var(--n100);background:var(--white);font-family:var(--fb);font-size:.75rem;color:var(--n400);cursor:pointer;transition:all var(--d) var(--e);text-decoration:none}
.filter-tag:hover,.filter-tag.active{background:var(--g600);border-color:var(--g600);color:#fff}
.filter-count{margin-left:auto;font-size:.72rem;color:var(--n400);white-space:nowrap}

/* ── Grid ── */
.page-wrap{max-width:1200px;margin:0 auto;padding:1.8rem 1.5rem}
.teachers-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem}

/* ── Card professor ── */
.teacher-card{
  background:var(--white);border:1px solid var(--n100);border-radius:var(--r);
  box-shadow:var(--sh0);overflow:hidden;
  transition:transform var(--d) var(--e),box-shadow var(--d) var(--e);
  display:flex;flex-direction:column;
}
.teacher-card:hover{transform:translateY(-4px);box-shadow:var(--sh2)}
.teacher-card.premium{border-color:rgba(201,168,76,.35);box-shadow:0 2px 16px rgba(201,168,76,.12)}

.tc-top{padding:1.2rem 1.2rem .9rem;display:flex;gap:.9rem;align-items:flex-start}
.tc-av{width:56px;height:56px;border-radius:50%;flex-shrink:0;object-fit:cover;border:2px solid var(--g100)}
.tc-av-ini{width:56px;height:56px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,var(--g400),var(--g600));display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-size:1.3rem;font-weight:700;color:#fff;border:2px solid var(--g100)}
.tc-info{flex:1;min-width:0}
.tc-name-row{display:flex;align-items:center;gap:.4rem;flex-wrap:wrap}
.tc-name{font-family:var(--fd);font-size:.98rem;font-weight:700;color:var(--n800)}
.tc-premium-badge{font-size:.58rem;font-weight:700;padding:.1rem .4rem;background:var(--gold-l);color:#92720c;border:1px solid rgba(201,168,76,.3);border-radius:20px;white-space:nowrap}
.tc-verified{font-size:.68rem;color:var(--g600);font-weight:600}
.tc-rating{display:flex;align-items:center;gap:.3rem;margin-top:.25rem}
.tc-stars{color:var(--gold);font-size:.78rem;letter-spacing:.05em}
.tc-rating-val{font-family:var(--fd);font-size:.85rem;font-weight:700;color:var(--n800)}
.tc-rating-count{font-size:.68rem;color:var(--n400)}
.tc-rank{font-size:.65rem;color:var(--n400);margin-top:.12rem}

.tc-bio{padding:0 1.2rem .8rem;font-size:.78rem;color:var(--n400);line-height:1.65;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}

.tc-subjects{padding:0 1.2rem .8rem;display:flex;flex-wrap:wrap;gap:.3rem}
.tc-subj{font-size:.65rem;padding:.14rem .48rem;background:var(--g50);border:1px solid var(--g100);border-radius:20px;color:var(--g700);font-weight:500}

.tc-stats{padding:.75rem 1.2rem;border-top:1px solid var(--n50);display:flex;gap:1.2rem}
.tc-stat{text-align:center;flex:1}
.tc-stat-val{font-family:var(--fd);font-size:.98rem;font-weight:700;color:var(--n800);line-height:1}
.tc-stat-lbl{font-size:.6rem;color:var(--n400);text-transform:uppercase;letter-spacing:.05em;margin-top:.1rem}

.tc-foot{padding:.75rem 1.2rem;border-top:1px solid var(--n50);display:flex;align-items:center;justify-content:space-between;gap:.6rem;background:var(--g25)}
.tc-price{font-size:.72rem;color:var(--n400)}
.tc-price strong{font-family:var(--fd);font-size:.95rem;color:var(--g600);font-weight:700}
.tc-price span{font-size:.65rem}
.btn-contratar{padding:.45rem 1rem;border-radius:50px;border:none;background:linear-gradient(135deg,var(--g500),var(--g700));color:#fff;font-family:var(--fb);font-size:.76rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);box-shadow:0 3px 10px rgba(45,122,88,.22);white-space:nowrap}
.btn-contratar:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(45,122,88,.32)}

/* Empty */
.empty-state{text-align:center;padding:4rem 1rem;color:var(--n400)}
.empty-ico{font-size:3rem;opacity:.3;display:block;margin-bottom:.8rem}
.empty-state p{font-size:.88rem;line-height:1.7}

/* Paginação */
.pagination{display:flex;align-items:center;justify-content:center;gap:.35rem;margin-top:2rem;padding-top:1.5rem;border-top:1px solid var(--n100)}
.pg{padding:.32rem .7rem;border-radius:var(--rs);border:1px solid var(--n100);background:var(--white);color:var(--n400);font-size:.78rem;cursor:pointer;text-decoration:none;transition:all var(--d) var(--e)}
.pg:hover{border-color:var(--g300);color:var(--g600)}
.pg.cur{background:var(--g600);border-color:var(--g600);color:#fff;font-weight:600}
.pg.off{opacity:.25;pointer-events:none}

/* Footer */
footer{background:var(--g900);padding:2rem;text-align:center;margin-top:3rem}
footer p{font-size:.78rem;color:rgba(194,234,214,.3);line-height:1.7}
footer a{color:var(--g300);text-decoration:none}

@media(max-width:768px){
  .hero{padding:2.5rem 1.2rem 2rem}
  .filters-bar{padding:.75rem 1.2rem;gap:.5rem}
  .page-wrap{padding:1.2rem 1rem}
  .teachers-grid{grid-template-columns:1fr}
  .hero-stats{gap:1.2rem}
  nav{padding:0 1.2rem}
}
</style>
</head>
<body>

<!-- Nav -->
<nav>
  <a class="nav-logo" href="<?= TEACHER_BASE_URL ?>/public/ranking.php">
    <div class="nav-logo-mark">🌱</div>
    <span class="nav-logo-name">florescer</span>
  </a>
  <div class="nav-right">
    <a class="nav-link" href="/florescer/public/index.php">Plataforma</a>
    <a class="nav-btn" href="<?= TEACHER_VIEWS ?>/index.php">Sou professor</a>
  </div>
</nav>

<!-- Hero -->
<div class="hero">
  <div class="hero-tag">🎓 Professores verificados</div>
  <h1>Encontre seu professor <em>ideal</em></h1>
  <p class="hero-sub">Correção de redações e aulas particulares com professores avaliados por alunos reais.</p>
  <div class="hero-stats">
    <div class="hs-item">
      <div class="hs-val"><?= $total ?></div>
      <div class="hs-lbl">Professores ativos</div>
    </div>
    <div class="hs-item">
      <div class="hs-val">
        <?php
        $totalRed=(int)(dbRow('SELECT COUNT(*) AS n FROM teacher_redacoes WHERE status="corrigida"')['n']??0);
        echo $totalRed;
        ?>
      </div>
      <div class="hs-lbl">Redações corrigidas</div>
    </div>
    <div class="hs-item">
      <div class="hs-val">
        <?php
        $avgRating=dbRow('SELECT ROUND(AVG(rating_avg),1) AS avg FROM teachers WHERE status="ativo" AND rating_count>0')['avg']??'—';
        echo $avgRating;
        ?>
      </div>
      <div class="hs-lbl">Avaliação média</div>
    </div>
  </div>
</div>

<!-- Filtros -->
<div class="filters-bar">
  <span class="filter-label">Filtrar:</span>

  <form method="GET" style="display:contents">
    <select class="filter-select" name="materia" onchange="this.form.submit()">
      <option value="">Todas as matérias</option>
      <?php foreach($allSubjects as $s): ?>
        <option value="<?= htmlspecialchars($s['name'],ENT_QUOTES) ?>"
                <?= $fSubject===$s['name']?'selected':'' ?>>
          <?= htmlspecialchars($s['name'],ENT_QUOTES) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <div class="filter-sep"></div>

    <?php
    $ordens=['rating'=>'⭐ Melhor avaliados','redacoes'=>'📝 Mais correções','preco'=>'💰 Menor preço'];
    foreach($ordens as $k=>$lbl):
      $url='?'.http_build_query(array_filter(['materia'=>$fSubject,'ordem'=>$k]));
    ?>
      <a class="filter-tag <?= $fOrder===$k?'active':'' ?>" href="<?= $url ?>">
        <?= $lbl ?>
      </a>
    <?php endforeach; ?>

    <span class="filter-count"><?= $total ?> professor<?= $total!==1?'es':'' ?></span>
  </form>
</div>

<!-- Grid de professores -->
<div class="page-wrap">
  <?php if(empty($teachers)): ?>
    <div class="empty-state">
      <span class="empty-ico">🔍</span>
      <p>Nenhum professor encontrado para este filtro.<br>
         <a href="ranking.php" style="color:var(--g500)">Ver todos os professores →</a>
      </p>
    </div>
  <?php else: ?>

  <div class="teachers-grid">
    <?php foreach($teachers as $t):
      $ini   = strtoupper(mb_substr($t['name']??'P',0,1,'UTF-8'));
      $rating= (float)($t['rating_avg']??0);
      $stars = $rating>0 ? round($rating) : 0;
      $subs  = $t['subjects'] ? explode('|',$t['subjects']) : [];
      $minP  = $t['min_price'] ? 'R$ '.number_format((float)$t['min_price'],2,',','.') : null;
      $isPremium = (bool)$t['is_premium'];
    ?>
    <div class="teacher-card <?= $isPremium?'premium':'' ?>">

      <div class="tc-top">
        <?php if(!empty($t['avatar_url'])): ?>
          <img class="tc-av"
               src="<?= htmlspecialchars($t['avatar_url'],ENT_QUOTES) ?>"
               alt="<?= htmlspecialchars($t['name'],ENT_QUOTES) ?>"/>
        <?php else: ?>
          <div class="tc-av-ini"><?= $ini ?></div>
        <?php endif; ?>

        <div class="tc-info">
          <div class="tc-name-row">
            <span class="tc-name"><?= htmlspecialchars($t['name'],ENT_QUOTES) ?></span>
            <?php if($isPremium): ?>
              <span class="tc-premium-badge">👑 Destaque</span>
            <?php endif; ?>
          </div>
          <?php if($rating>0): ?>
          <div class="tc-rating">
            <span class="tc-stars"><?= str_repeat('★',$stars).str_repeat('☆',5-$stars) ?></span>
            <span class="tc-rating-val"><?= number_format($rating,1) ?></span>
            <span class="tc-rating-count">(<?= (int)$t['rating_count'] ?>)</span>
          </div>
          <?php else: ?>
          <div style="font-size:.7rem;color:var(--n400);margin-top:.2rem">Sem avaliações ainda</div>
          <?php endif; ?>
          <?php if((int)$t['rank_position']>0): ?>
            <div class="tc-rank">#<?= $t['rank_position'] ?> no ranking</div>
          <?php endif; ?>
          <div style="margin-top:.25rem">
            <span class="tc-verified">✓ Verificado</span>
          </div>
        </div>
      </div>

      <?php if(!empty($t['bio'])): ?>
      <div class="tc-bio"><?= htmlspecialchars($t['bio'],ENT_QUOTES) ?></div>
      <?php endif; ?>

      <?php if(!empty($subs)): ?>
      <div class="tc-subjects">
        <?php foreach(array_slice($subs,0,4) as $s): ?>
          <span class="tc-subj"><?= htmlspecialchars($s,ENT_QUOTES) ?></span>
        <?php endforeach; ?>
        <?php if(count($subs)>4): ?>
          <span class="tc-subj" style="background:var(--n50);color:var(--n400);border-color:var(--n100)">
            +<?= count($subs)-4 ?>
          </span>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="tc-stats">
        <div class="tc-stat">
          <div class="tc-stat-val"><?= (int)$t['total_redacoes'] ?></div>
          <div class="tc-stat-lbl">Redações</div>
        </div>
        <div class="tc-stat">
          <div class="tc-stat-val"><?= (int)$t['total_aulas'] ?></div>
          <div class="tc-stat-lbl">Aulas</div>
        </div>
        <div class="tc-stat">
          <div class="tc-stat-val"><?= (int)$t['rating_count'] ?></div>
          <div class="tc-stat-lbl">Avaliações</div>
        </div>
      </div>

      <div class="tc-foot">
        <?php if($minP): ?>
          <div class="tc-price">
            A partir de <strong><?= $minP ?></strong><span>/correção</span>
          </div>
        <?php else: ?>
          <div class="tc-price" style="color:var(--n400)">Consulte preços</div>
        <?php endif; ?>
        <button class="btn-contratar"
                onclick="contratar(<?= (int)$t['id'] ?>)">
          Contratar →
        </button>
      </div>

    </div>
    <?php endforeach; ?>
  </div>

  <!-- Paginação -->
  <?php if($totalPages>1): ?>
  <div class="pagination">
    <?php
    function pgUrl(int $p, string $mat, string $ord): string {
        return '?'.http_build_query(array_filter(['page'=>$p>1?$p:null,'materia'=>$mat,'ordem'=>$ord!=='rating'?$ord:null]));
    }
    ?>
    <a class="pg <?= $page<=1?'off':'' ?>" href="<?= pgUrl($page-1,$fSubject,$fOrder) ?>">‹</a>
    <?php for($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?>
      <a class="pg <?= $p===$page?'cur':'' ?>" href="<?= pgUrl($p,$fSubject,$fOrder) ?>"><?= $p ?></a>
    <?php endfor; ?>
    <a class="pg <?= $page>=$totalPages?'off':'' ?>" href="<?= pgUrl($page+1,$fSubject,$fOrder) ?>">›</a>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>

<!-- Footer -->
<footer>
  <p>
    🌱 <strong style="color:var(--g300)">florescer</strong> · Plataforma de estudos<br>
    <a href="/florescer/public/index.php">Voltar para a plataforma</a> ·
    <a href="<?= TEACHER_VIEWS ?>/index.php">Área do professor</a>
  </p>
</footer>

<script>
function contratar(teacherId){
  // Redireciona para login do aluno com intenção de contratar
  const redirect=encodeURIComponent(window.location.href+'?contratar='+teacherId);
  window.location.href='/florescer/public/index.php?action=contratar&teacher='+teacherId;
}
</script>
</body>
</html>