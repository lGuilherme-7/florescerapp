<?php
// ============================================================
// /florescer/public/views/professores.php
// Sessão do aluno para contratar professores
// ============================================================

require_once dirname(dirname(dirname(__DIR__))) . '/florescer/includes/session.php';
require_once dirname(dirname(dirname(__DIR__))) . '/florescer/includes/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/florescer/config/db.php';

startSession();
if (!isLoggedIn()) {
    header('Location: /florescer/public/index.php');
    exit;
}

$user   = currentUser();
$userId = (int)$user['id'];

// Filtros
$fSubject = trim($_GET['materia'] ?? '');
$fOrder   = in_array($_GET['ordem'] ?? '', ['rating','redacoes','preco']) ? $_GET['ordem'] : 'rating';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 9;
$offset   = ($page - 1) * $perPage;

// Matérias disponíveis
$allSubjects = dbQuery(
    'SELECT DISTINCT ts.name FROM teacher_subjects ts
     JOIN teachers t ON t.id = ts.teacher_id
     WHERE t.status = "ativo" ORDER BY ts.name'
);

// Query professores
$where  = 'WHERE t.status = "ativo"';
$params = [];
if ($fSubject) {
    $where   .= ' AND EXISTS (SELECT 1 FROM teacher_subjects ts WHERE ts.teacher_id = t.id AND ts.name = ?)';
    $params[] = $fSubject;
}

$orderSql = match($fOrder) {
    'redacoes' => 't.rating_count DESC, t.rating_avg DESC',
    'preco'    => 'min_price ASC, t.rating_avg DESC',
    default    => 't.rating_avg DESC, t.rating_count DESC',
};

$total      = (int)(dbRow("SELECT COUNT(*) AS n FROM teachers t $where", $params)['n'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));

$teachers = dbQuery(
    "SELECT t.id, t.name, t.bio, t.avatar_url, t.rating_avg, t.rating_count,
            t.rank_position, t.is_premium,
            (SELECT MIN(tp.price) FROM teacher_packages tp
             WHERE tp.teacher_id = t.id AND tp.is_active = 1) AS min_price,
            (SELECT COUNT(*) FROM teacher_redacoes tr
             WHERE tr.teacher_id = t.id AND tr.status = 'corrigida') AS total_redacoes,
            (SELECT COUNT(*) FROM teacher_orders to2
             WHERE to2.teacher_id = t.id AND to2.type = 'aula' AND to2.status = 'pago') AS total_aulas,
            (SELECT GROUP_CONCAT(ts.name ORDER BY ts.name SEPARATOR '|')
             FROM teacher_subjects ts WHERE ts.teacher_id = t.id LIMIT 5) AS subjects,
            (SELECT COUNT(*) FROM teacher_orders toc
             WHERE toc.teacher_id = t.id AND toc.student_id = ? AND toc.status = 'pago') AS ja_contratou
     FROM teachers t
     $where
     ORDER BY t.is_premium DESC, $orderSql
     LIMIT ? OFFSET ?",
    array_merge([$userId], $params, [$perPage, $offset])
);

// Pedidos ativos do aluno
$meusped = dbQuery(
    "SELECT o.id, o.type, o.status, o.credits_total, o.credits_used,
            o.scheduled_at, o.meet_link, o.created_at,
            t.name AS teacher_name, t.id AS teacher_id
     FROM teacher_orders o
     JOIN teachers t ON t.id = o.teacher_id
     WHERE o.student_id = ? AND o.status = 'pago'
     ORDER BY o.created_at DESC LIMIT 10",
    [$userId]
);

$hora     = (int)date('G');
$saudacao = $hora>=5&&$hora<12?'Bom dia':($hora<18?'Boa tarde':'Boa noite');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Professores — florescer</title>
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
  --gold:#c9a84c;--gold-l:#fdf3d8;--red:#d94040;--red-l:#fdeaea;
  --fd:'Fraunces',Georgia,serif;--fb:'DM Sans',system-ui,sans-serif;
  --r:14px;--rs:9px;--d:.2s;--e:cubic-bezier(.4,0,.2,1);
  --sh0:0 1px 3px rgba(0,0,0,.06);--sh1:0 4px 12px rgba(0,0,0,.07);
  --sh2:0 8px 24px rgba(0,0,0,.09);--sh3:0 20px 48px rgba(0,0,0,.12);
}
html{height:100%}
body{font-family:var(--fb);background:var(--g25);color:var(--n800);-webkit-font-smoothing:antialiased}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:var(--g200);border-radius:2px}

/* ── Hero ── */
.hero{background:linear-gradient(135deg,var(--g800),var(--g900));padding:2.5rem 2rem 2rem;position:relative;overflow:hidden}
.hero::after{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 70% 80% at 80% 50%,rgba(85,184,138,.08),transparent);pointer-events:none}
.hero-inner{max-width:1100px;margin:0 auto;position:relative;z-index:1}
.hero-greet{font-size:.75rem;color:rgba(194,234,214,.45);margin-bottom:.3rem}
.hero-title{font-family:var(--fd);font-size:1.7rem;font-weight:900;color:#fff;letter-spacing:-.02em;line-height:1.1;margin-bottom:.5rem}
.hero-title em{color:var(--g300);font-style:italic;font-weight:400}
.hero-sub{font-size:.85rem;color:rgba(194,234,214,.5);max-width:420px;line-height:1.7}
.hero-stats{display:flex;gap:1.5rem;margin-top:1.4rem;flex-wrap:wrap}
.hs{text-align:left}
.hs-val{font-family:var(--fd);font-size:1.3rem;font-weight:900;color:var(--g300);line-height:1}
.hs-lbl{font-size:.62rem;color:rgba(194,234,214,.35);text-transform:uppercase;letter-spacing:.07em;margin-top:.1rem}

/* ── Meus pedidos ── */
.meus-peds{background:var(--white);border-bottom:1px solid var(--n100)}
.meus-peds-inner{max-width:1100px;margin:0 auto;padding:.9rem 1.5rem;display:flex;gap:.6rem;align-items:center;overflow-x:auto}
.meus-peds-lbl{font-size:.72rem;font-weight:600;color:var(--n400);white-space:nowrap;margin-right:.3rem}
.ped-chip{display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .8rem;background:var(--g50);border:1px solid var(--g100);border-radius:50px;font-size:.74rem;color:var(--g700);cursor:pointer;transition:all var(--d) var(--e);white-space:nowrap;text-decoration:none;flex-shrink:0}
.ped-chip:hover{background:var(--g100)}
.ped-chip.aula{background:var(--gold-l);border-color:rgba(201,168,76,.25);color:#92720c}

/* ── Filtros ── */
.filters{background:var(--white);border-bottom:1px solid var(--n100);position:sticky;top:0;z-index:40}
.filters-inner{max-width:1100px;margin:0 auto;padding:.75rem 1.5rem;display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}
.fl-label{font-size:.72rem;font-weight:600;color:var(--n400)}
.fl-select{padding:.4rem .8rem;background:var(--g25);border:1.5px solid var(--n100);border-radius:var(--rs);font-family:var(--fb);font-size:.78rem;color:var(--n800);outline:none;cursor:pointer;appearance:none;transition:all var(--d) var(--e)}
.fl-select:focus{border-color:var(--g400)}
.fl-tag{padding:.32rem .8rem;border-radius:50px;border:1px solid var(--n100);background:var(--white);font-family:var(--fb);font-size:.74rem;color:var(--n400);cursor:pointer;transition:all var(--d) var(--e);text-decoration:none}
.fl-tag:hover,.fl-tag.on{background:var(--g600);border-color:var(--g600);color:#fff}
.fl-count{margin-left:auto;font-size:.72rem;color:var(--n400)}

/* ── Grid ── */
.main{max-width:1100px;margin:0 auto;padding:1.5rem}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:1rem}

/* ── Card professor ── */
.pcard{background:var(--white);border:1px solid var(--n100);border-radius:var(--r);box-shadow:var(--sh0);overflow:hidden;display:flex;flex-direction:column;transition:transform var(--d) var(--e),box-shadow var(--d) var(--e)}
.pcard:hover{transform:translateY(-4px);box-shadow:var(--sh2)}
.pcard.premium{border-color:rgba(201,168,76,.3);box-shadow:0 2px 16px rgba(201,168,76,.1)}
.pcard.contratado{border-color:rgba(45,122,88,.25)}

.pc-top{padding:1.1rem 1.1rem .8rem;display:flex;gap:.8rem}
.pc-av{width:54px;height:54px;border-radius:50%;flex-shrink:0;object-fit:cover;border:2px solid var(--g100)}
.pc-av-ini{width:54px;height:54px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,var(--g400),var(--g600));display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-size:1.2rem;font-weight:700;color:#fff;border:2px solid var(--g100)}
.pc-info{flex:1;min-width:0}
.pc-name-row{display:flex;align-items:center;gap:.35rem;flex-wrap:wrap;margin-bottom:.18rem}
.pc-name{font-family:var(--fd);font-size:.95rem;font-weight:700;color:var(--n800)}
.pc-premium{font-size:.58rem;font-weight:700;padding:.08rem .38rem;background:var(--gold-l);color:#92720c;border:1px solid rgba(201,168,76,.3);border-radius:20px}
.pc-verified{font-size:.65rem;color:var(--g600);font-weight:600}
.pc-contratado{font-size:.65rem;color:var(--g600);font-weight:600;background:var(--g50);padding:.06rem .38rem;border-radius:20px;border:1px solid var(--g100)}
.pc-rating{display:flex;align-items:center;gap:.28rem}
.pc-stars{color:var(--gold);font-size:.75rem}
.pc-rval{font-family:var(--fd);font-size:.82rem;font-weight:700;color:var(--n800)}
.pc-rcount{font-size:.66rem;color:var(--n400)}

.pc-bio{padding:0 1.1rem .7rem;font-size:.77rem;color:var(--n400);line-height:1.65;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}

.pc-subjects{padding:0 1.1rem .7rem;display:flex;flex-wrap:wrap;gap:.28rem}
.pc-subj{font-size:.63rem;padding:.12rem .42rem;background:var(--g50);border:1px solid var(--g100);border-radius:20px;color:var(--g700);font-weight:500}

.pc-stats{padding:.65rem 1.1rem;border-top:1px solid var(--n50);display:flex;gap:1rem}
.pc-stat{flex:1;text-align:center}
.pc-stat-val{font-family:var(--fd);font-size:.92rem;font-weight:700;color:var(--n800);line-height:1}
.pc-stat-lbl{font-size:.58rem;color:var(--n400);text-transform:uppercase;letter-spacing:.04em;margin-top:.1rem}

.pc-foot{padding:.7rem 1.1rem;border-top:1px solid var(--n50);display:flex;align-items:center;justify-content:space-between;gap:.5rem;background:var(--g25);margin-top:auto}
.pc-price{font-size:.7rem;color:var(--n400)}
.pc-price strong{font-family:var(--fd);font-size:.9rem;color:var(--g600);font-weight:700}
.btn-contratar{padding:.42rem .95rem;border-radius:50px;border:none;background:linear-gradient(135deg,var(--g500),var(--g700));color:#fff;font-family:var(--fb);font-size:.75rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);box-shadow:0 3px 10px rgba(45,122,88,.2)}
.btn-contratar:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(45,122,88,.3)}
.btn-ver{padding:.42rem .95rem;border-radius:50px;border:1px solid var(--g200);background:transparent;color:var(--g600);font-family:var(--fb);font-size:.75rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e)}
.btn-ver:hover{background:var(--g50)}

/* ── Paginação ── */
.pag{display:flex;align-items:center;justify-content:center;gap:.35rem;margin-top:1.8rem;padding-top:1.5rem;border-top:1px solid var(--n100)}
.pg{padding:.32rem .65rem;border-radius:var(--rs);border:1px solid var(--n100);background:var(--white);color:var(--n400);font-size:.76rem;cursor:pointer;text-decoration:none;transition:all var(--d) var(--e)}
.pg:hover{border-color:var(--g300);color:var(--g600)}
.pg.cur{background:var(--g600);border-color:var(--g600);color:#fff;font-weight:600}
.pg.off{opacity:.25;pointer-events:none}

.empty{text-align:center;padding:4rem 1rem;color:var(--n400)}
.empty-ico{font-size:2.5rem;opacity:.3;display:block;margin-bottom:.7rem}

/* ── Modal professor ── */
.overlay{position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.5);backdrop-filter:blur(8px);display:flex;align-items:flex-start;justify-content:center;padding:1.5rem;opacity:0;pointer-events:none;transition:opacity var(--d) var(--e);overflow-y:auto}
.overlay.open{opacity:1;pointer-events:all}
.modal{background:var(--white);border:1px solid var(--n100);border-radius:18px;width:100%;max-width:600px;box-shadow:var(--sh3);transform:translateY(12px) scale(.97);transition:transform var(--d) var(--e);margin:auto}
.overlay.open .modal{transform:none}
.modal-head{padding:.9rem 1.3rem;border-bottom:1px solid var(--n100);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--white);z-index:1;border-radius:18px 18px 0 0}
.modal-title{font-family:var(--fd);font-size:1rem;font-weight:700;color:var(--n800)}
.modal-x{width:28px;height:28px;border-radius:50%;background:var(--n50);border:none;cursor:pointer;font-size:.78rem;color:var(--n400);transition:all var(--d) var(--e)}
.modal-x:hover{background:var(--red-l);color:var(--red)}
.modal-body{padding:1.3rem;display:flex;flex-direction:column;gap:1rem}
.modal-foot{padding:.85rem 1.3rem;border-top:1px solid var(--n100);display:flex;gap:.5rem;justify-content:flex-end;border-radius:0 0 18px 18px}

/* Perfil modal */
.prof-header{display:flex;gap:1rem;align-items:flex-start}
.prof-av-lg{width:70px;height:70px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,var(--g400),var(--g600));display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-size:1.6rem;font-weight:700;color:#fff;border:3px solid var(--g100)}
.prof-av-img{width:70px;height:70px;border-radius:50%;object-fit:cover;border:3px solid var(--g100)}
.prof-info-name{font-family:var(--fd);font-size:1.1rem;font-weight:900;color:var(--n800)}
.prof-badges{display:flex;gap:.4rem;flex-wrap:wrap;margin:.3rem 0}
.prof-bio{font-size:.82rem;color:var(--n400);line-height:1.75}
.prof-stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem}
.ps{text-align:center;padding:.65rem;background:var(--g25);border:1px solid var(--n100);border-radius:var(--rs)}
.ps-val{font-family:var(--fd);font-size:1.2rem;font-weight:900;color:var(--g600);line-height:1}
.ps-lbl{font-size:.6rem;color:var(--n400);text-transform:uppercase;letter-spacing:.05em;margin-top:.1rem}
.avs-list{display:flex;flex-direction:column;gap:.5rem;max-height:180px;overflow-y:auto}
.av-item{padding:.6rem .8rem;background:var(--n50);border:1px solid var(--n100);border-radius:var(--rs)}
.av-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:.2rem}
.av-name{font-size:.78rem;font-weight:600;color:var(--n800)}
.av-stars{color:var(--gold);font-size:.75rem}
.av-comment{font-size:.76rem;color:var(--n400);font-style:italic;line-height:1.5}

/* Pacotes */
.pkg-grid{display:flex;flex-direction:column;gap:.5rem}
.pkg-item{display:flex;align-items:center;gap:.8rem;padding:.75rem .9rem;background:var(--g25);border:1.5px solid var(--n100);border-radius:var(--rs);cursor:pointer;transition:all var(--d) var(--e)}
.pkg-item:hover,.pkg-item.selected{border-color:var(--g400);background:var(--g50)}
.pkg-item.selected{border-color:var(--g500)}
.pkg-ico{font-size:1.1rem;flex-shrink:0}
.pkg-info{flex:1}
.pkg-name{font-size:.82rem;font-weight:600;color:var(--n800)}
.pkg-qty{font-size:.7rem;color:var(--n400);margin-top:.04rem}
.pkg-price{font-family:var(--fd);font-size:1rem;font-weight:700;color:var(--g600);flex-shrink:0}
.pkg-radio{width:16px;height:16px;accent-color:var(--g500);flex-shrink:0}

/* Slots */
.slots-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:.5rem;max-height:220px;overflow-y:auto}
.slot-item{padding:.6rem .8rem;background:var(--g25);border:1.5px solid var(--n100);border-radius:var(--rs);cursor:pointer;transition:all var(--d) var(--e);text-align:center}
.slot-item:hover,.slot-item.selected{border-color:var(--g400);background:var(--g50)}
.slot-day{font-size:.72rem;font-weight:600;color:var(--n400);text-transform:uppercase;letter-spacing:.05em}
.slot-time{font-family:var(--fd);font-size:.95rem;font-weight:700;color:var(--n800);margin:.1rem 0}
.slot-price{font-size:.72rem;color:var(--g600);font-weight:600}

.section-title{font-family:var(--fd);font-size:.9rem;font-weight:700;color:var(--n800);margin-bottom:.5rem}
.type-tabs{display:flex;gap:.4rem;margin-bottom:.8rem}
.type-tab{flex:1;padding:.5rem;border-radius:var(--rs);border:1.5px solid var(--n100);background:var(--white);font-family:var(--fb);font-size:.8rem;font-weight:500;color:var(--n400);cursor:pointer;transition:all var(--d) var(--e);text-align:center}
.type-tab.on{background:var(--g600);border-color:var(--g600);color:#fff}

.btn-pagar{flex:1;padding:.6rem;border-radius:50px;border:none;background:linear-gradient(135deg,var(--g500),var(--g700));color:#fff;font-family:var(--fb);font-size:.85rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);box-shadow:0 4px 14px rgba(45,122,88,.25)}
.btn-pagar:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(45,122,88,.35)}
.btn-pagar:disabled{opacity:.5;cursor:not-allowed;transform:none}
.btn-ghost{padding:.6rem 1.1rem;background:none;border:1px solid var(--n100);border-radius:50px;color:var(--n400);font-family:var(--fb);font-size:.82rem;cursor:pointer;transition:all var(--d) var(--e)}
.btn-ghost:hover{border-color:var(--n200);color:var(--n800)}

.info-box{padding:.65rem .8rem;background:var(--g25);border:1px solid var(--n100);border-radius:var(--rs);font-size:.76rem;color:var(--n400);line-height:1.6}
.info-box strong{color:var(--g600)}

#toasts{position:fixed;bottom:1.2rem;right:1.2rem;z-index:999;display:flex;flex-direction:column;gap:.35rem;pointer-events:none}
.toast{background:var(--n800);color:#eee;padding:.55rem .9rem;border-radius:var(--rs);font-size:.78rem;display:flex;align-items:center;gap:.4rem;animation:tin .22s var(--e) both;max-width:280px;box-shadow:var(--sh3);pointer-events:all}
.toast.ok{border-left:3px solid var(--g400)}.toast.err{border-left:3px solid #f87171}.toast.info{border-left:3px solid var(--gold)}
@keyframes tin{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}

@media(max-width:768px){.grid{grid-template-columns:1fr}.hero{padding:1.8rem 1.2rem 1.5rem}.main{padding:1rem}.slots-grid{grid-template-columns:1fr}}
</style>
</head>
<body>

<!-- Hero -->
<div class="hero">
  <div class="hero-inner">
    <div class="hero-greet"><?= $saudacao ?>, <?= htmlspecialchars(explode(' ',$user['name'])[0],ENT_QUOTES) ?> 👋</div>
    <div class="hero-title">Encontre seu professor <em>ideal</em></div>
    <div class="hero-sub">Redações corrigidas com feedback detalhado e aulas particulares com professores verificados.</div>
    <div class="hero-stats">
      <div class="hs">
        <div class="hs-val"><?= $total ?></div>
        <div class="hs-lbl">Professores ativos</div>
      </div>
      <?php
      $totalRed=(int)(dbRow('SELECT COUNT(*) AS n FROM teacher_redacoes WHERE status="corrigida"')['n']??0);
      ?>
      <div class="hs">
        <div class="hs-val"><?= $totalRed ?></div>
        <div class="hs-lbl">Redações corrigidas</div>
      </div>
      <div class="hs">
        <div class="hs-val"><?= count($meusped) ?></div>
        <div class="hs-lbl">Meus pedidos ativos</div>
      </div>
    </div>
  </div>
</div>

<!-- Meus pedidos ativos -->
<?php if(!empty($meusped)): ?>
<div class="meus-peds">
  <div class="meus-peds-inner">
    <span class="meus-peds-lbl">Meus pedidos:</span>
    <?php foreach($meusped as $p): ?>
      <a class="ped-chip <?= $p['type']==='aula'?'aula':'' ?>"
         href="/florescer/public/views/meu_professor.php?order=<?= $p['id'] ?>">
        <?= $p['type']==='aula'?'📅':'📝' ?>
        <?= htmlspecialchars($p['teacher_name'],ENT_QUOTES) ?>
        <?php if($p['type']==='redacao'): ?>
          (<?= (int)$p['credits_used'] ?>/<?= (int)$p['credits_total'] ?>)
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Filtros -->
<div class="filters">
  <form class="filters-inner" method="GET">
    <span class="fl-label">Filtrar:</span>
    <select class="fl-select" name="materia" onchange="this.form.submit()">
      <option value="">Todas as matérias</option>
      <?php foreach($allSubjects as $s): ?>
        <option value="<?= htmlspecialchars($s['name'],ENT_QUOTES) ?>"
                <?= $fSubject===$s['name']?'selected':'' ?>>
          <?= htmlspecialchars($s['name'],ENT_QUOTES) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?php
    $ordens=['rating'=>'⭐ Melhor avaliados','redacoes'=>'📝 Mais correções','preco'=>'💰 Menor preço'];
    foreach($ordens as $k=>$lbl):
      $url='?'.http_build_query(array_filter(['materia'=>$fSubject,'ordem'=>$k]));
    ?>
      <a class="fl-tag <?= $fOrder===$k?'on':'' ?>" href="<?= $url ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
    <span class="fl-count"><?= $total ?> professor<?= $total!==1?'es':'' ?></span>
  </form>
</div>

<!-- Grid -->
<div class="main">
  <?php if(empty($teachers)): ?>
    <div class="empty">
      <span class="empty-ico">🔍</span>
      Nenhum professor encontrado.<br>
      <a href="professores.php" style="color:var(--g500);font-size:.82rem">Ver todos →</a>
    </div>
  <?php else: ?>
  <div class="grid">
    <?php foreach($teachers as $t):
      $ini   = strtoupper(mb_substr($t['name']??'P',0,1,'UTF-8'));
      $rating= (float)($t['rating_avg']??0);
      $stars = $rating>0?round($rating):0;
      $subs  = $t['subjects']?explode('|',$t['subjects']):[];
      $minP  = $t['min_price']?'R$ '.number_format((float)$t['min_price'],2,',','.'):null;
    ?>
    <div class="pcard <?= $t['is_premium']?'premium':'' ?> <?= $t['ja_contratou']>0?'contratado':'' ?>">
      <div class="pc-top">
        <?php if(!empty($t['avatar_url'])): ?>
          <img class="pc-av" src="<?= htmlspecialchars($t['avatar_url'],ENT_QUOTES) ?>" alt=""/>
        <?php else: ?>
          <div class="pc-av-ini"><?= $ini ?></div>
        <?php endif; ?>
        <div class="pc-info">
          <div class="pc-name-row">
            <span class="pc-name"><?= htmlspecialchars($t['name'],ENT_QUOTES) ?></span>
            <?php if($t['is_premium']): ?><span class="pc-premium">👑</span><?php endif; ?>
          </div>
          <?php if($rating>0): ?>
          <div class="pc-rating">
            <span class="pc-stars"><?= str_repeat('★',$stars).str_repeat('☆',5-$stars) ?></span>
            <span class="pc-rval"><?= number_format($rating,1) ?></span>
            <span class="pc-rcount">(<?= (int)$t['rating_count'] ?>)</span>
          </div>
          <?php endif; ?>
          <div style="display:flex;gap:.3rem;margin-top:.2rem;flex-wrap:wrap">
            <span class="pc-verified">✓ Verificado</span>
            <?php if($t['ja_contratou']>0): ?>
              <span class="pc-contratado">✓ Já contratei</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php if(!empty($t['bio'])): ?>
        <div class="pc-bio"><?= htmlspecialchars($t['bio'],ENT_QUOTES) ?></div>
      <?php endif; ?>
      <?php if(!empty($subs)): ?>
      <div class="pc-subjects">
        <?php foreach(array_slice($subs,0,4) as $s): ?>
          <span class="pc-subj"><?= htmlspecialchars($s,ENT_QUOTES) ?></span>
        <?php endforeach; ?>
        <?php if(count($subs)>4): ?><span class="pc-subj" style="background:var(--n50);color:var(--n400)">+<?= count($subs)-4 ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="pc-stats">
        <div class="pc-stat"><div class="pc-stat-val"><?= (int)$t['total_redacoes'] ?></div><div class="pc-stat-lbl">Redações</div></div>
        <div class="pc-stat"><div class="pc-stat-val"><?= (int)$t['total_aulas'] ?></div><div class="pc-stat-lbl">Aulas</div></div>
        <div class="pc-stat"><div class="pc-stat-val"><?= (int)$t['rating_count'] ?></div><div class="pc-stat-lbl">Avaliações</div></div>
      </div>
      <div class="pc-foot">
        <?php if($minP): ?>
          <div class="pc-price">A partir de <strong><?= $minP ?></strong></div>
        <?php else: ?>
          <div class="pc-price" style="color:var(--n400)">Consulte</div>
        <?php endif; ?>
        <div style="display:flex;gap:.4rem">
          <button class="btn-ver" onclick="verPerfil(<?= (int)$t['id'] ?>)">Ver perfil</button>
          <button class="btn-contratar" onclick="contratar(<?= (int)$t['id'] ?>,'<?= htmlspecialchars($t['name'],ENT_QUOTES) ?>')">Contratar</button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Paginação -->
  <?php if($totalPages>1): ?>
  <div class="pag">
    <?php
    function pgUrl2(int $p,string $m,string $o):string{return '?'.http_build_query(array_filter(['page'=>$p>1?$p:null,'materia'=>$m,'ordem'=>$o!=='rating'?$o:null]));}
    ?>
    <a class="pg <?= $page<=1?'off':'' ?>" href="<?= pgUrl2($page-1,$fSubject,$fOrder) ?>">‹</a>
    <?php for($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?>
      <a class="pg <?= $p===$page?'cur':'' ?>" href="<?= pgUrl2($p,$fSubject,$fOrder) ?>"><?= $p ?></a>
    <?php endfor; ?>
    <a class="pg <?= $page>=$totalPages?'off':'' ?>" href="<?= pgUrl2($page+1,$fSubject,$fOrder) ?>">›</a>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>

<!-- Modal: perfil + contratação -->
<div class="overlay" id="overlay">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title" id="modalTitle">Professor</span>
      <button class="modal-x" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body" id="modalBody">
      <div style="text-align:center;padding:2rem;color:#bbb">Carregando…</div>
    </div>
    <div class="modal-foot" id="modalFoot"></div>
  </div>
</div>

<div id="toasts"></div>

<script>
const STUDENT_ID = <?= $userId ?>;
const API_PROF   = '/florescer/teachers/api/profile.php';
const API_PAG    = '/florescer/teachers/api/pagamento.php';
const WEEKDAYS   = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];

let currentTeacherId = null;
let selectedPkg  = null;
let selectedSlot = null;
let currentType  = 'redacao';
let teacherData  = null;

function toast(msg,type='ok',ms=3200){
  const w=document.getElementById('toasts'),d=document.createElement('div');
  d.className=`toast ${type}`;
  d.innerHTML=`<span>${type==='ok'?'✅':type==='err'?'❌':'💡'}</span><span>${msg}</span>`;
  w.appendChild(d);
  setTimeout(()=>{d.style.opacity='0';d.style.transition='.3s';setTimeout(()=>d.remove(),300);},ms);
}

function esc(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}
function money(v){return 'R$ '+parseFloat(v||0).toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.')}

async function apiPost(url,body){
  const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  return r.json();
}

// ── Ver perfil ────────────────────────────────────────────────
async function verPerfil(id){
  currentTeacherId=id;
  selectedPkg=null;selectedSlot=null;
  document.getElementById('overlay').classList.add('open');
  document.body.style.overflow='hidden';
  document.getElementById('modalBody').innerHTML='<div style="text-align:center;padding:2rem;color:#bbb">Carregando…</div>';
  document.getElementById('modalFoot').innerHTML='';

  const d=await apiPost(API_PROF,{action:'get'});
  // Busca dados públicos do professor
  const pr=await fetch('/florescer/teachers/api/public.php?id='+id);
  const pd=await pr.json();
  if(!pd.success){toast('Erro ao carregar perfil.','err');return;}
  teacherData=pd.data;
  renderPerfil(pd.data);
}

function renderPerfil(t){
  document.getElementById('modalTitle').textContent=t.name;
  const ini=((t.name||'?')[0]).toUpperCase();
  const rating=(float=parseFloat(t.rating_avg||0));
  const stars=rating>0?'★'.repeat(Math.round(rating))+'☆'.repeat(5-Math.round(rating)):'—';
  const subs=(t.subjects||[]).map(s=>`<span class="pc-subj">${esc(s.name)}</span>`).join('');
  const avs=(t.ratings||[]).map(r=>`
    <div class="av-item">
      <div class="av-top"><span class="av-name">${esc(r.student_name||'Aluno')}</span>
      <span class="av-stars">${'★'.repeat(r.stars)+'☆'.repeat(5-r.stars)}</span></div>
      ${r.comment?`<div class="av-comment">"${esc(r.comment)}"</div>`:''}
    </div>`).join('');

  document.getElementById('modalBody').innerHTML=`
    <div class="prof-header">
      ${t.avatar_url
        ?`<img class="prof-av-img" src="${esc(t.avatar_url)}" alt=""/>`
        :`<div class="prof-av-lg">${ini}</div>`}
      <div style="flex:1">
        <div class="prof-info-name">${esc(t.name)}</div>
        <div class="prof-badges">
          <span class="pc-verified">✓ Verificado</span>
          ${t.is_premium?'<span class="pc-premium">👑 Destaque</span>':''}
        </div>
        <div class="pc-rating">
          <span class="pc-stars">${stars}</span>
          <span class="pc-rval">${rating>0?rating.toFixed(1):'Sem avaliações'}</span>
          ${rating>0?`<span class="pc-rcount">(${t.rating_count})</span>`:''}
        </div>
      </div>
    </div>
    ${t.bio?`<div class="prof-bio">${esc(t.bio.replace(/\[.*?\]/g,'').trim())}</div>`:''}
    <div class="prof-stats-row">
      <div class="ps"><div class="ps-val">${t.total_redacoes||0}</div><div class="ps-lbl">Redações</div></div>
      <div class="ps"><div class="ps-val">${t.total_aulas||0}</div><div class="ps-lbl">Aulas</div></div>
      <div class="ps"><div class="ps-val">${t.rating_count||0}</div><div class="ps-lbl">Avaliações</div></div>
    </div>
    ${subs?`<div><div class="section-title">📚 Matérias</div><div class="pc-subjects" style="flex-wrap:wrap;gap:.3rem;display:flex">${subs}</div></div>`:''}
    ${avs?`<div><div class="section-title">⭐ Avaliações recentes</div><div class="avs-list">${avs}</div></div>`:''}
  `;

  document.getElementById('modalFoot').innerHTML=`
    <button class="btn-ghost" onclick="closeModal()">Fechar</button>
    <button class="btn-pagar" onclick="contratar(${t.id},'${esc(t.name)}')">Contratar</button>
  `;
}

// ── Contratar ─────────────────────────────────────────────────
async function contratar(id,nome){
  currentTeacherId=id;
  selectedPkg=null;selectedSlot=null;
  document.getElementById('overlay').classList.add('open');
  document.body.style.overflow='hidden';
  document.getElementById('modalTitle').textContent='Contratar '+nome;
  document.getElementById('modalBody').innerHTML='<div style="text-align:center;padding:2rem;color:#bbb">Carregando…</div>';
  document.getElementById('modalFoot').innerHTML='';

  // Busca dados públicos
  const pr=await fetch('/florescer/teachers/api/public.php?id='+id);
  const pd=await pr.json();
  if(!pd.success){toast('Erro ao carregar dados.','err');return;}
  teacherData=pd.data;
  renderContratacao(pd.data);
}

function renderContratacao(t){
  const pkgs=(t.packages||[]);
  const slots=(t.slots||[]);
  const hasAulas=slots.length>0;

  document.getElementById('modalBody').innerHTML=`
    <div class="info-box">
      Você está contratando <strong>${esc(t.name)}</strong>.
      Após o pagamento, você terá acesso ao chat e poderá enviar redações ou agendar aulas.
    </div>

    <div>
      <div class="section-title">O que deseja?</div>
      <div class="type-tabs">
        <button class="type-tab on" id="tabRed" onclick="switchType('redacao')">📝 Correção de redação</button>
        ${hasAulas?`<button class="type-tab" id="tabAula" onclick="switchType('aula')">📅 Aula particular</button>`:''}
      </div>
    </div>

    <!-- Pacotes redação -->
    <div id="sectionRed">
      <div class="section-title">Escolha um pacote</div>
      ${pkgs.length?`<div class="pkg-grid">
        ${pkgs.map((p,i)=>`
          <div class="pkg-item" id="pkg-${p.id}" onclick="selectPkg(${p.id},${p.price})">
            <span class="pkg-ico">📦</span>
            <div class="pkg-info">
              <div class="pkg-name">${esc(p.name)}</div>
              <div class="pkg-qty">${p.quantity} correção${p.quantity!=1?'ões':''}</div>
            </div>
            <div class="pkg-price">${money(p.price)}</div>
            <input class="pkg-radio" type="radio" name="pkg" ${i===0?'checked':''}/>
          </div>`).join('')}
      </div>`:'<div style="text-align:center;padding:1rem;color:var(--n400);font-size:.8rem">Nenhum pacote disponível.</div>'}
    </div>

    <!-- Horários aula -->
    <div id="sectionAula" style="display:none">
      <div class="section-title">Escolha um horário</div>
      ${hasAulas?`<div class="slots-grid">
        ${slots.map(s=>`
          <div class="slot-item" id="slot-${s.id}" onclick="selectSlot(${s.id},${s.price},'${nextDate(s.weekday,s.time_start)}')">
            <div class="slot-day">${WEEKDAYS[s.weekday]}</div>
            <div class="slot-time">${s.time_start} – ${s.time_end}</div>
            <div class="slot-price">${money(s.price)}</div>
          </div>`).join('')}
      </div>`:'<div style="text-align:center;padding:1rem;color:var(--n400);font-size:.8rem">Nenhum horário disponível.</div>'}
    </div>

    <div class="info-box" style="font-size:.74rem">
      🔒 Pagamento seguro via <strong>Mercado Pago</strong> ·
      💬 Chat liberado após confirmação ·
      ⭐ Avalie após a experiência
    </div>
  `;

  // Auto-seleciona primeiro pacote
  if(pkgs.length) selectPkg(pkgs[0].id, pkgs[0].price);

  document.getElementById('modalFoot').innerHTML=`
    <button class="btn-ghost" onclick="closeModal()">Cancelar</button>
    <button class="btn-pagar" id="btnPagar" onclick="iniciarPagamento()">💳 Pagar e contratar</button>
  `;
}

function switchType(type){
  currentType=type;
  document.getElementById('tabRed').classList.toggle('on',type==='redacao');
  const tabAula=document.getElementById('tabAula');
  if(tabAula) tabAula.classList.toggle('on',type==='aula');
  document.getElementById('sectionRed').style.display=type==='redacao'?'block':'none';
  document.getElementById('sectionAula').style.display=type==='aula'?'block':'none';
  selectedPkg=null;selectedSlot=null;
}

function selectPkg(id,price){
  selectedPkg={id,price};
  document.querySelectorAll('.pkg-item').forEach(el=>el.classList.remove('selected'));
  document.getElementById('pkg-'+id)?.classList.add('selected');
  document.querySelectorAll('.pkg-radio').forEach(r=>r.checked=false);
  document.querySelector(`#pkg-${id} .pkg-radio`).checked=true;
}

function selectSlot(id,price,scheduled){
  selectedSlot={id,price,scheduled};
  document.querySelectorAll('.slot-item').forEach(el=>el.classList.remove('selected'));
  document.getElementById('slot-'+id)?.classList.add('selected');
}

function nextDate(weekday, time){
  const now=new Date();
  const day=now.getDay();
  let diff=(weekday-day+7)%7||7;
  const next=new Date(now);
  next.setDate(now.getDate()+diff);
  const [h,m]=time.split(':');
  next.setHours(parseInt(h),parseInt(m),0,0);
  return next.toISOString().slice(0,16).replace('T',' ');
}

async function iniciarPagamento(){
  if(currentType==='redacao' && !selectedPkg){toast('Selecione um pacote.','err');return;}
  if(currentType==='aula'    && !selectedSlot){toast('Selecione um horário.','err');return;}

  const btn=document.getElementById('btnPagar');
  btn.disabled=true;btn.textContent='Aguarde…';

  const body={
    action:'criar_preferencia',
    type:currentType,
    teacher_id:currentTeacherId,
    student_id:STUDENT_ID,
  };
  if(currentType==='redacao'){body.package_id=selectedPkg.id;}
  else{body.slot_id=selectedSlot.id;body.scheduled_at=selectedSlot.scheduled;}

  const d=await apiPost(API_PAG,body);
  if(d.success){
    toast('Redirecionando para pagamento… 💳','info');
    setTimeout(()=>window.location.href=d.data.checkout_url,800);
  } else {
    toast(d.message||'Erro ao iniciar pagamento.','err');
    btn.disabled=false;btn.textContent='💳 Pagar e contratar';
  }
}

function closeModal(){
  document.getElementById('overlay').classList.remove('open');
  document.body.style.overflow='';
  currentTeacherId=null;
}

document.getElementById('overlay').addEventListener('click',e=>{
  if(e.target===document.getElementById('overlay')) closeModal();
});
document.addEventListener('keydown',e=>{if(e.key==='Escape') closeModal();});

// Abre contratação direto se vier da URL
const urlParams=new URLSearchParams(window.location.search);
if(urlParams.get('contratar')) contratar(parseInt(urlParams.get('contratar')),'Professor');
if(urlParams.get('erro')==='pagamento_falhou') toast('Pagamento não concluído. Tente novamente.','err');
if(urlParams.get('info')==='pagamento_pendente') toast('Pagamento pendente de confirmação.','info');
</script>
</body>
</html>