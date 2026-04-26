<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

startSession();
if (!isLoggedIn()) { header('Location: /florescer/public/index.php'); exit; }

$user        = currentUser();
$userId      = (int)$user['id'];
$userName    = htmlspecialchars($user['name'] ?? 'Estudante', ENT_QUOTES, 'UTF-8');
$userInitial = strtoupper(mb_substr($user['name'] ?? 'E', 0, 1, 'UTF-8'));
$currentPage = 'simulated';

// Sidebar vars
$ud     = dbRow('SELECT xp, level, streak FROM users WHERE id=?', [$userId]);
$xp     = (int)($ud['xp'] ?? 0);
$level  = (int)($ud['level'] ?? 1);
$streak = (int)($ud['streak'] ?? 0);
$lvN    = ['','Semente','Broto','Estudante','Aplicado','Dedicado','Avançado','Expert','Mestre','Lendário'];
$lvName = $lvN[min($level, count($lvN)-1)] ?? 'Lendário';
$stages = [[0,6,'🌱','Semente'],[7,14,'🌿','Broto Inicial'],[15,29,'☘️','Planta Jovem'],
           [30,59,'🌲','Planta Forte'],[60,99,'🌳','Árvore Crescendo'],[100,149,'🌴','Árvore Robusta'],
           [150,199,'🎋','Árvore Antiga'],[200,299,'✨','Árvore Gigante'],[300,PHP_INT_MAX,'🏆','Árvore Lendária']];
$plant = ['emoji'=>'🌱','name'=>'Semente','pct'=>0];
foreach ($stages as [$mn,$mx,$em,$nm]) {
    if ($streak >= $mn && $streak <= $mx) {
        $r2 = $mx < PHP_INT_MAX ? $mx - $mn + 1 : 1;
        $plant = ['emoji'=>$em,'name'=>$nm,'pct'=>$mx < PHP_INT_MAX ? min(100,round(($streak-$mn)/$r2*100)) : 100];
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

// Redações por vestibular
$redacaoTableOk  = (bool)dbRow("SHOW TABLES LIKE 'sim_redacoes'");
$redacoesPorVest = [];
if ($redacaoTableOk) {
    foreach (dbQuery('SELECT id,vestibular_id,tema,texto1,texto2,texto3,proposta FROM sim_redacoes WHERE is_active=1 ORDER BY sort_order ASC,id ASC') as $r)
        $redacoesPorVest[(int)$r['vestibular_id']][] = $r;
}

// Penalização
$penalty = dbRow('SELECT until FROM sim_penalties WHERE user_id=? AND until >= CURDATE()', [$userId]);

// Vestibulares
$tableOk = (bool)dbRow("SHOW TABLES LIKE 'sim_vestibulares'");
$vests   = [];
if ($tableOk) {
    $vests = dbQuery("SELECT v.id, v.name, v.description, v.is_active,
        COALESCE(v.sort_order,0) AS sort_order,
        COALESCE(v.category,'vestibular') AS category,
        COALESCE(v.badge,'') AS badge,
        COALESCE(v.grade_level,'') AS grade_level,
        COALESCE(v.time_min,0) AS time_min,
        COALESCE(v.time_max,0) AS time_max,
        COUNT(q.id) AS total_questions
     FROM sim_vestibulares v
     LEFT JOIN sim_questions q ON q.vestibular_id=v.id AND q.is_active=1
     WHERE v.is_active=1
     GROUP BY v.id,v.name,v.description,v.is_active,v.sort_order,v.category,v.badge,v.grade_level,v.time_min,v.time_max
     ORDER BY COALESCE(v.sort_order,0) ASC, v.id ASC");
}

$byCategory = ['escolar'=>[],'vestibular'=>[],'materia'=>[],'outras'=>[]];
foreach ($vests as $v) {
    $cat = in_array($v['category'],['escolar','vestibular','materia']) ? $v['category'] : 'outras';
    $byCategory[$cat][] = $v;
}

$CAT_LABELS = [
    'escolar'    => ['🏫','Simulados Escolares',   'Ensino Médio por matéria'],
    'vestibular' => ['🎓','Vestibular Brasileiro', 'ENEM, FUVEST, SSA e outros'],
    'materia'    => ['📚','Simulados por Matéria', 'Treine uma matéria específica'],
    'outras'     => ['⚖️','Outras Áreas',          'Direito, Concursos e mais'],
];
$BADGE_LABELS = [
    'novo'    => ['✨ Novo',   '#40916c','#d8f3dc'],
    'popular' => ['⭐ Popular','#c9a84c','#fef3c7'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>florescer — Simulados</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700;9..144,900&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --g950:#0d1f16;--g800:#1a3a2a;--g700:#1e4d35;--g600:#2d6a4f;
  --g500:#40916c;--g400:#52b788;--g300:#74c69d;--g200:#b7e4c7;--g50:#f0faf4;
  --n800:#1c1c1a;--n100:#f5f2ee;--n50:#faf8f5;--white:#fff;
  --gold:#c9a84c;--red:#dc2626;--red-l:#fee2e2;
  --sw:240px;--hh:58px;
  --fd:'Fraunces',Georgia,serif;--fb:'Inter',system-ui,sans-serif;
  --r:12px;--rs:7px;--d:.22s;--e:cubic-bezier(.4,0,.2,1);
  --sh0:0 1px 3px rgba(0,0,0,.06);--sh1:0 2px 8px rgba(0,0,0,.07);
  --sh2:0 4px 16px rgba(0,0,0,.09);--sh3:0 12px 32px rgba(0,0,0,.12);
}
html,body{height:100%}
body{font-family:var(--fb);background:var(--n50);color:var(--n800);display:flex;-webkit-font-smoothing:antialiased}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:rgba(64,145,108,.2);border-radius:2px}

/* Sidebar */
.sidebar{width:var(--sw);height:100vh;position:fixed;top:0;left:0;background:var(--g800);display:flex;flex-direction:column;z-index:50;overflow:hidden;transition:transform var(--d) var(--e);border-right:1px solid rgba(116,198,157,.08)}
.sb-logo{padding:.95rem 1.1rem;border-bottom:1px solid rgba(116,198,157,.08);display:flex;align-items:center;gap:.5rem;flex-shrink:0}
.sb-logo-icon{width:28px;height:28px;background:linear-gradient(135deg,var(--g500),var(--g700));border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.88rem}
.sb-logo-name{font-family:var(--fd);font-size:1.05rem;font-weight:700;color:var(--g200)}
.sb-logo-sub{font-size:.56rem;color:rgba(116,198,157,.3);text-transform:uppercase;letter-spacing:.1em;margin-top:.08rem}
.sb-profile{padding:.72rem 1.1rem;border-bottom:1px solid rgba(116,198,157,.08);display:flex;align-items:center;gap:.6rem;text-decoration:none;flex-shrink:0}
.sb-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--g500),var(--g600));display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-size:.86rem;font-weight:700;color:var(--white);flex-shrink:0;overflow:hidden}
.sb-pname{font-size:.82rem;font-weight:500;color:var(--g200);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sb-plevel{font-size:.68rem;color:var(--g300);margin-top:.06rem;opacity:.7}
.sb-plant{padding:.6rem 1.1rem;border-bottom:1px solid rgba(116,198,157,.08);flex-shrink:0}
.sb-plant-row{display:flex;align-items:center;gap:.5rem}
.sb-pemoji{font-size:1.25rem;animation:breathe 4s ease-in-out infinite}
@keyframes breathe{0%,100%{transform:scale(1)}50%{transform:scale(1.07) translateY(-1px)}}
.sb-pname2{font-size:.7rem;font-weight:600;color:var(--g300)}
.sb-pstreak{font-size:.64rem;color:rgba(116,198,157,.4);margin-top:.06rem}
.sb-pbar{height:2px;background:rgba(116,198,157,.1);border-radius:1px;margin-top:.28rem;overflow:hidden}
.sb-pbar-fill{height:100%;background:linear-gradient(90deg,var(--g400),var(--g200))}
.sb-obj{padding:.5rem 1.1rem;border-bottom:1px solid rgba(116,198,157,.08);flex-shrink:0}
.sb-obj-lbl{font-size:.58rem;text-transform:uppercase;letter-spacing:.1em;color:rgba(116,198,157,.28);display:block;margin-bottom:.22rem}
.sb-obj-sel{width:100%;background:none;border:none;color:var(--g300);font-family:var(--fb);font-size:.78rem;font-weight:500;cursor:pointer;padding:0;outline:none;appearance:none}
.sb-obj-sel option{background:var(--g800)}
.sb-nav{flex:1;overflow-y:auto;padding:.45rem 0}
.sb-nav-grp{font-size:.57rem;text-transform:uppercase;letter-spacing:.1em;color:rgba(116,198,157,.25);padding:.7rem 1.1rem .25rem;display:block}
.sb-nav-a{display:flex;align-items:center;gap:.55rem;padding:.48rem 1.1rem;color:rgba(183,228,199,.48);font-size:.81rem;text-decoration:none;transition:all var(--d) var(--e);border-left:2px solid transparent}
.sb-nav-a:hover{color:var(--g300);background:rgba(116,198,157,.04)}
.sb-nav-a.active{color:var(--g300);background:rgba(116,198,157,.07);border-left-color:var(--g400);font-weight:500}
.sb-nav-ico{font-size:.85rem;min-width:.95rem;text-align:center}
.sb-footer{padding:.75rem 1rem;border-top:1px solid rgba(116,198,157,.08);flex-shrink:0}
.sb-logout{display:flex;align-items:center;gap:.4rem;width:100%;padding:.44rem .7rem;background:none;border:1px solid rgba(220,100,100,.13);border-radius:var(--rs);color:rgba(220,100,100,.52);font-family:var(--fb);font-size:.77rem;cursor:pointer}
.sb-logout:hover{background:rgba(220,38,38,.07);color:#e07070}
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);z-index:49;opacity:0;transition:opacity var(--d) var(--e)}
.sb-overlay.show{opacity:1}

/* Main */
.main{margin-left:var(--sw);flex:1;display:flex;flex-direction:column;min-width:0}
.topbar{height:var(--hh);background:rgba(250,248,245,.92);backdrop-filter:blur(14px);border-bottom:1px solid rgba(0,0,0,.055);display:flex;align-items:center;gap:.8rem;padding:0 1.8rem;position:sticky;top:0;z-index:40;flex-shrink:0}
.hamburger{display:none;flex-direction:column;gap:4px;cursor:pointer;background:none;border:none;padding:5px;border-radius:6px}
.hamburger span{display:block;width:19px;height:2px;background:var(--g600);border-radius:1px;transition:all var(--d) var(--e)}
.hamburger.open span:nth-child(1){transform:translateY(6px) rotate(45deg)}
.hamburger.open span:nth-child(2){opacity:0}
.hamburger.open span:nth-child(3){transform:translateY(-6px) rotate(-45deg)}
.tb-title{font-family:var(--fd);font-size:1rem;font-weight:600;color:var(--n800)}

/* Tela lista */
#screen-list{flex:1;padding:1.8rem 2rem;display:flex;flex-direction:column;gap:2rem;overflow-y:auto}
.page-title{font-family:var(--fd);font-size:1.45rem;font-weight:900;color:var(--n800);letter-spacing:-.03em}
.page-sub{font-size:.8rem;color:#aaa;margin-top:.2rem}
.penalty-banner{background:var(--red-l);border:1px solid rgba(220,38,38,.25);border-radius:var(--r);padding:1rem 1.3rem;display:flex;align-items:center;gap:.85rem}
.penalty-ico{font-size:1.5rem;flex-shrink:0}
.penalty-text strong{font-size:.88rem;color:var(--red);display:block;margin-bottom:.2rem}
.penalty-text p{font-size:.78rem;color:#888;line-height:1.5}
.cat-head{display:flex;align-items:center;gap:.65rem;margin-bottom:1rem}
.cat-ico{font-size:1.3rem}
.cat-title{font-family:var(--fd);font-size:1.05rem;font-weight:700;color:var(--n800)}
.cat-sub{font-size:.74rem;color:#aaa}
.sim-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:.9rem}
.sim-card{background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r);overflow:hidden;box-shadow:var(--sh0);cursor:pointer;transition:transform var(--d) var(--e),box-shadow var(--d) var(--e)}
.sim-card:hover{transform:translateY(-3px);box-shadow:var(--sh2)}
.sim-card.no-questions{opacity:.55;cursor:not-allowed}
.sim-card.no-questions:hover{transform:none;box-shadow:var(--sh0)}
.sim-card-top{background:linear-gradient(135deg,var(--g800),var(--g950));padding:1.1rem 1.2rem;position:relative;overflow:hidden}
.sim-card-top::after{content:'';position:absolute;top:-20px;right:-20px;width:80px;height:80px;border-radius:50%;background:rgba(116,198,157,.06)}
.sim-badge{position:absolute;top:.7rem;right:.8rem;font-size:.65rem;font-weight:700;padding:.14rem .5rem;border-radius:20px;z-index:1}
.sim-name{font-family:var(--fd);font-size:.95rem;font-weight:700;color:rgba(240,250,244,.92);letter-spacing:-.02em;margin-bottom:.25rem;position:relative;z-index:1}
.sim-desc{font-size:.72rem;color:rgba(116,198,157,.5);line-height:1.5;position:relative;z-index:1}
.sim-card-body{padding:.85rem 1.1rem}
.sim-meta{display:flex;gap:.75rem;font-size:.72rem;color:#bbb;margin-bottom:.75rem;flex-wrap:wrap}
.sim-grade{display:inline-block;font-size:.68rem;font-weight:600;padding:.12rem .45rem;border-radius:20px;background:rgba(64,145,108,.1);color:var(--g500);margin-bottom:.6rem}
.btn-start{width:100%;padding:.52rem;background:linear-gradient(135deg,var(--g500),var(--g600));color:#fff;border:none;border-radius:var(--rs);font-family:var(--fb);font-size:.82rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e)}
.btn-start:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(64,145,108,.35)}
.btn-start:disabled{opacity:.4;cursor:not-allowed;transform:none;box-shadow:none}
.empty-sim{text-align:center;padding:2rem;color:#bbb;background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r)}

/* Tela início (simulado e redação compartilham) */
#screen-start,#screen-redacao-intro{display:none;flex:1;align-items:center;justify-content:center;padding:2rem;background:rgba(10,24,22,.03)}
.start-card{background:var(--white);border:1px solid rgba(0,0,0,.07);border-radius:16px;width:100%;max-width:620px;box-shadow:var(--sh3);overflow:hidden}
.start-head{background:linear-gradient(135deg,var(--g800),var(--g950));padding:1.8rem 2rem;position:relative;overflow:hidden}
.start-head::after{content:'';position:absolute;top:-40px;right:-40px;width:180px;height:180px;border-radius:50%;background:rgba(116,198,157,.06)}
.start-cat{font-size:.68rem;font-weight:600;text-transform:uppercase;letter-spacing:.1em;color:rgba(116,198,157,.5);margin-bottom:.4rem}
.start-title{font-family:var(--fd);font-size:1.4rem;font-weight:900;color:rgba(240,250,244,.95);letter-spacing:-.03em;position:relative;z-index:1}
.start-meta{font-size:.74rem;color:rgba(116,198,157,.45);margin-top:.4rem;position:relative;z-index:1}
.start-body{padding:1.8rem 2rem;display:flex;flex-direction:column;gap:1.4rem}
.warn-box{background:#fff7ed;border:1px solid rgba(234,88,12,.2);border-radius:var(--rs);padding:1rem 1.2rem;display:flex;gap:.7rem;align-items:flex-start}
.warn-ico{font-size:1.1rem;flex-shrink:0;margin-top:.05rem}
.warn-text{font-size:.8rem;color:#9a3412;line-height:1.6}
.warn-text strong{display:block;margin-bottom:.2rem;font-size:.84rem}
.topics-section h4{font-size:.78rem;font-weight:600;color:#888;margin-bottom:.6rem;text-transform:uppercase;letter-spacing:.05em}
.topics-list{display:flex;flex-wrap:wrap;gap:.35rem}
.topic-chip{font-size:.73rem;padding:.22rem .65rem;border-radius:20px;background:rgba(64,145,108,.08);border:1px solid rgba(64,145,108,.15);color:var(--g600)}
.no-topics{font-size:.78rem;color:#bbb;font-style:italic}
.timer-info{display:flex;align-items:center;gap:.6rem;padding:.75rem 1rem;background:var(--n50);border-radius:var(--rs);border:1px solid rgba(0,0,0,.07);font-size:.8rem;color:#666}
.start-actions{display:flex;gap:.65rem}
.btn-back{flex:1;padding:.68rem;background:transparent;border:1.5px solid rgba(0,0,0,.12);border-radius:var(--rs);font-family:var(--fb);font-size:.84rem;font-weight:500;color:#888;cursor:pointer}
.btn-back:hover{background:var(--n100);color:var(--n800)}
.btn-go{flex:2;padding:.68rem;background:linear-gradient(135deg,var(--g500),var(--g600));border:none;border-radius:var(--rs);font-family:var(--fd);font-size:.95rem;font-weight:700;color:#fff;cursor:pointer;box-shadow:0 4px 14px rgba(64,145,108,.3)}
.btn-go:hover{transform:translateY(-1px)}

/* Tela prova */
#screen-exam{display:none;flex:1;flex-direction:column;background:#fafafa}
.exam-topbar{background:var(--white);border-bottom:1px solid rgba(0,0,0,.08);padding:.7rem 1.8rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:var(--hh);z-index:30;flex-shrink:0}
.exam-title{font-family:var(--fd);font-size:.9rem;font-weight:700;color:var(--n800)}
.exam-prog{font-size:.76rem;color:#aaa}
.exam-timer{font-family:var(--fd);font-size:1rem;font-weight:700;color:var(--n800);background:var(--n50);border:1px solid rgba(0,0,0,.09);border-radius:50px;padding:.28rem .85rem}
.exam-timer.warning{color:var(--red);border-color:rgba(220,38,38,.3);background:var(--red-l);animation:pulse .8s ease-in-out infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.6}}
.exam-q-tabs{background:var(--white);border-bottom:1px solid rgba(0,0,0,.06);padding:.5rem 1.8rem;display:flex;gap:.35rem;flex-wrap:wrap;flex-shrink:0}
.q-tab{width:32px;height:32px;border-radius:50%;border:1.5px solid rgba(0,0,0,.12);background:var(--white);font-family:var(--fb);font-size:.76rem;font-weight:500;color:#aaa;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all var(--d) var(--e)}
.q-tab:hover{border-color:var(--g400);color:var(--g500)}
.q-tab.answered{background:var(--g500);border-color:var(--g500);color:#fff}
.q-tab.current{border-color:var(--n800);color:var(--n800);font-weight:700}
.exam-body{flex:1;overflow-y:auto;display:flex;justify-content:center;padding:2rem 1.5rem}
.question-wrap{width:100%;max-width:720px;display:flex;flex-direction:column;gap:1.5rem}
.q-header{display:flex;align-items:center;gap:.75rem;margin-bottom:.2rem}
.q-num{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#bbb}
.q-area-badge{font-size:.68rem;font-weight:600;padding:.14rem .5rem;border-radius:20px;background:rgba(64,145,108,.1);color:var(--g600)}
.q-statement{font-size:1rem;line-height:1.75;color:var(--n800);background:var(--white);border:1px solid rgba(0,0,0,.07);border-radius:var(--r);padding:1.4rem 1.6rem;box-shadow:var(--sh0)}
.options-list{display:flex;flex-direction:column;gap:.6rem}
.option-btn{display:flex;align-items:flex-start;gap:.9rem;padding:.9rem 1.1rem;background:var(--white);border:1.5px solid rgba(0,0,0,.09);border-radius:var(--r);cursor:pointer;transition:all var(--d) var(--e);text-align:left;width:100%;font-family:var(--fb);font-size:.89rem;color:var(--n800)}
.option-btn:hover{border-color:var(--g400);background:rgba(64,145,108,.04)}
.option-btn.selected{border-color:var(--g500);background:rgba(64,145,108,.08);font-weight:500}
.opt-letter{width:28px;height:28px;border-radius:50%;background:rgba(0,0,0,.06);display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;color:#888;flex-shrink:0;transition:all var(--d) var(--e)}
.option-btn.selected .opt-letter{background:var(--g500);color:#fff}
.opt-text{flex:1;line-height:1.55;padding-top:.04rem}
.exam-nav{background:var(--white);border-top:1px solid rgba(0,0,0,.07);padding:.85rem 1.8rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-shrink:0}
.btn-nav{padding:.52rem 1.2rem;border-radius:var(--rs);font-family:var(--fb);font-size:.82rem;font-weight:500;cursor:pointer;transition:all var(--d) var(--e)}
.btn-nav.prev{background:transparent;border:1.5px solid rgba(0,0,0,.12);color:#888}
.btn-nav.prev:hover{background:var(--n100)}
.btn-nav.next{background:var(--g500);border:none;color:#fff}
.btn-nav.next:hover{background:var(--g400)}
.btn-nav.finish{background:linear-gradient(135deg,#1e40af,#3b82f6);border:none;color:#fff}

/* Tela resultado */
#screen-result{display:none;flex:1;overflow-y:auto;padding:2rem;background:var(--n50);justify-content:center}
.result-wrap{width:100%;max-width:680px}
.result-card{background:var(--white);border:1px solid rgba(0,0,0,.07);border-radius:16px;overflow:hidden;box-shadow:var(--sh2);margin-bottom:1.2rem}
.result-head{padding:2rem;text-align:center;background:linear-gradient(135deg,var(--g800),var(--g950))}
.result-emoji{font-size:3rem;margin-bottom:.6rem;display:block}
.result-title{font-family:var(--fd);font-size:1.4rem;font-weight:900;color:rgba(240,250,244,.95);margin-bottom:.3rem}
.result-sub{font-size:.8rem;color:rgba(116,198,157,.5)}
.result-score{font-family:var(--fd);font-size:3.5rem;font-weight:900;color:#fff;margin:.8rem 0 .3rem;line-height:1}
.result-pct{font-size:.8rem;color:rgba(116,198,157,.5)}
.result-body{padding:1.5rem}
.result-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;margin-bottom:1.2rem}
.res-stat{text-align:center;padding:.8rem;background:var(--n50);border-radius:var(--rs);border:1px solid rgba(0,0,0,.06)}
.res-stat-val{font-family:var(--fd);font-size:1.4rem;font-weight:700;color:var(--n800)}
.res-stat-lbl{font-size:.68rem;color:#aaa;margin-top:.15rem}
.gabarito-title{font-family:var(--fd);font-size:.9rem;font-weight:700;color:var(--n800);margin-bottom:.9rem;margin-top:1.2rem}
.gab-item{background:var(--n50);border:1px solid rgba(0,0,0,.06);border-radius:var(--r);padding:1rem 1.1rem;margin-bottom:.65rem}
.gab-item.correct{border-left:3px solid var(--g500)}
.gab-item.wrong{border-left:3px solid var(--red)}
.gab-q-num{font-size:.68rem;font-weight:700;text-transform:uppercase;color:#aaa;margin-bottom:.4rem}
.gab-stmt{font-size:.81rem;color:var(--n800);line-height:1.55;margin-bottom:.6rem}
.gab-answer{font-size:.78rem;display:flex;gap:1rem;flex-wrap:wrap}
.gab-correct{color:var(--g500);font-weight:600}
.gab-given{color:var(--red);font-weight:600}
.gab-given.ok{color:var(--g500)}
.gab-expl{font-size:.76rem;color:#666;line-height:1.55;margin-top:.5rem;padding:.6rem .8rem;background:rgba(64,145,108,.05);border-radius:var(--rs);border-left:2px solid var(--g400)}
.result-actions{display:flex;gap:.65rem;margin-top:1rem}
.btn-retry{flex:1;padding:.62rem;background:var(--g500);border:none;border-radius:var(--rs);color:#fff;font-family:var(--fb);font-size:.84rem;font-weight:600;cursor:pointer}
.btn-retry:hover{background:var(--g400)}
.btn-home{flex:1;padding:.62rem;background:transparent;border:1.5px solid rgba(0,0,0,.12);border-radius:var(--rs);color:#888;font-family:var(--fb);font-size:.84rem;font-weight:500;cursor:pointer}
.btn-home:hover{background:var(--n100)}

/* Tela redação */
#screen-redacao{display:none;flex:1;flex-direction:column;background:#fafafa}
.red-topbar{background:var(--white);border-bottom:1px solid rgba(0,0,0,.08);padding:.7rem 1.8rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:var(--hh);z-index:30;flex-shrink:0}
.red-title-bar{font-family:var(--fd);font-size:.9rem;font-weight:700;color:var(--n800)}
.red-words{font-size:.76rem;color:#aaa;background:var(--n50);border:1px solid rgba(0,0,0,.09);border-radius:50px;padding:.28rem .85rem}

/* Tabs redação */
.red-tabs{background:var(--white);border-bottom:1px solid rgba(0,0,0,.06);padding:.5rem 1.8rem;display:flex;gap:.35rem;flex-shrink:0}
.red-tab{padding:.32rem .85rem;border-radius:50px;font-family:var(--fb);font-size:.76rem;font-weight:500;cursor:pointer;border:1.5px solid rgba(0,0,0,.1);background:var(--white);color:#aaa;transition:all var(--d) var(--e)}
.red-tab:hover{border-color:var(--g400);color:var(--g500)}
.red-tab.active{background:var(--g500);border-color:var(--g500);color:#fff;font-weight:600}

/* Painel apoio */
.red-apoio{flex:1;overflow-y:auto;padding:1.8rem;display:none;flex-direction:column;gap:1rem}
.red-apoio.show{display:flex}
.apoio-card{background:var(--white);border:1px solid rgba(0,0,0,.07);border-radius:var(--r);padding:1.2rem 1.4rem;box-shadow:var(--sh0)}
.apoio-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--g500);margin-bottom:.6rem}
.apoio-text{font-size:.88rem;line-height:1.75;color:var(--n800)}

/* Folha de redação */
.red-folha{flex:1;overflow-y:auto;padding:1.8rem;display:none;flex-direction:column;gap:1rem}
.red-folha.show{display:flex}
.folha-wrap{background:var(--white);border:1.5px solid #ccc;border-radius:var(--r);overflow:hidden;box-shadow:var(--sh1);max-width:760px;width:100%;margin:0 auto}
.folha-head{background:#f5f5f5;border-bottom:2px solid #333;padding:.9rem 1.4rem;text-align:center}
.folha-head-title{font-family:var(--fd);font-size:1.1rem;font-weight:900;letter-spacing:.08em;color:#111}
.folha-info{display:grid;grid-template-columns:1fr auto;border-bottom:1.5px solid #333}
.folha-info-nome{padding:.5rem 1rem;border-right:1.5px solid #333;font-size:.75rem;color:#333}
.folha-info-data{padding:.5rem 1rem;font-size:.75rem;color:#333;display:flex;align-items:center;gap:1rem}
.folha-linhas{position:relative}
.folha-linhas-bg{display:flex;flex-direction:column}
.folha-linha{height:2rem;display:flex;align-items:center;border-bottom:1px solid}
.folha-linha-num{width:2.8rem;flex-shrink:0;text-align:center;font-size:.68rem;font-weight:400;color:#bbb;border-right:1.5px solid #ddd;height:100%;display:flex;align-items:center;justify-content:center}
.folha-linha-num.m5{font-weight:700;color:#666;border-right-color:#aaa}
.folha-textarea{position:absolute;top:0;left:2.8rem;right:0;bottom:0;width:calc(100% - 2.8rem);background:transparent;border:none;outline:none;resize:none;font-family:'Courier New',monospace;font-size:.88rem;line-height:2rem;color:#111;padding:.2rem .9rem 0;z-index:2;overflow:hidden}
.folha-footer{padding:.5rem 1rem;background:#f5f5f5;border-top:1.5px solid #ddd;font-size:.67rem;color:#aaa;text-align:center}

/* Rodapé redação */
.red-nav{background:var(--white);border-top:1px solid rgba(0,0,0,.07);padding:.85rem 1.8rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-shrink:0}

/* Anticola / toast */
.anticola-banner{position:fixed;top:0;left:0;right:0;z-index:999;background:rgba(220,38,38,.95);color:#fff;padding:.65rem 1.5rem;display:flex;align-items:center;justify-content:space-between;font-size:.82rem;font-weight:600;animation:slideDown .3s var(--e) both}
@keyframes slideDown{from{transform:translateY(-100%)}to{transform:translateY(0)}}
.toast-wrap{position:fixed;bottom:1.4rem;right:1.4rem;z-index:500;display:flex;flex-direction:column;gap:.4rem;pointer-events:none}
.toast{background:var(--n800);color:#eee;padding:.62rem .95rem;border-radius:var(--rs);font-size:.79rem;display:flex;align-items:center;gap:.4rem;animation:tin .24s var(--e) both;max-width:270px;box-shadow:var(--sh3);pointer-events:all}
.toast.ok{border-left:3px solid var(--g400)}.toast.err{border-left:3px solid #f87171}.toast.warn{border-left:3px solid var(--gold)}
@keyframes tin{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}

@media(max-width:768px){
  .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
  .sb-overlay{display:block}.main{margin-left:0}.hamburger{display:flex}
  #screen-list{padding:1.2rem 1rem}.sim-grid{grid-template-columns:1fr}
  .exam-body,.red-apoio,.red-folha{padding:1rem}
  .result-stats{grid-template-columns:1fr 1fr}
}
</style>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
  <header class="topbar">
    <button class="hamburger" id="hamburger" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
    <span class="tb-title">🧠 Simulados</span>
  </header>

  <!-- TELA 1: LISTA -->
  <div id="screen-list">
    <div>
      <div class="page-title">Simulados</div>
      <div class="page-sub">Escolha um simulado para começar a treinar</div>
    </div>

    <?php if ($penalty): ?>
    <div class="penalty-banner">
      <div class="penalty-ico">🚫</div>
      <div class="penalty-text">
        <strong>Você está penalizado por troca de aba</strong>
        <p>Disponível novamente em <strong><?= date('d/m/Y', strtotime($penalty['until'])) ?></strong></p>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!$tableOk): ?>
      <div class="empty-sim"><p>⚙️ Execute o SQL de migração no phpMyAdmin.</p></div>
    <?php else: ?>
      <?php foreach ($CAT_LABELS as $catKey => [$catIco,$catTitle,$catSub]): ?>
        <?php if (empty($byCategory[$catKey])): continue; endif; ?>
        <div>
          <div class="cat-head">
            <span class="cat-ico"><?= $catIco ?></span>
            <div><div class="cat-title"><?= $catTitle ?></div><div class="cat-sub"><?= $catSub ?></div></div>
          </div>
          <div class="sim-grid">
            <?php foreach ($byCategory[$catKey] as $v):
              $isRedacao = ($v['category'] === 'redacao');
              $temTema   = !empty($redacoesPorVest[(int)$v['id']]);
              $hasQs     = (int)$v['total_questions'] > 0;
              $podeAbrir = !(bool)$penalty && ($isRedacao ? $temTema : $hasQs);
              $onFn      = $isRedacao ? "showRedacaoIntro({$v['id']})" : "showStart({$v['id']})";
              $badge     = $v['badge'] ?? '';
              $timeInfo  = $v['time_max'] ? '⏱ '.($v['time_min']?$v['time_min'].'–':'').$v['time_max'].'min' : '';
            ?>
            <div class="sim-card <?= !$podeAbrir?'no-questions':'' ?>" onclick="<?= $podeAbrir?$onFn:'' ?>">
              <div class="sim-card-top">
                <?php if ($badge && isset($BADGE_LABELS[$badge])): ?>
                  <span class="sim-badge" style="background:<?= $BADGE_LABELS[$badge][2] ?>;color:<?= $BADGE_LABELS[$badge][1] ?>"><?= $BADGE_LABELS[$badge][0] ?></span>
                <?php endif; ?>
                <div class="sim-name"><?= htmlspecialchars($v['name'],ENT_QUOTES) ?></div>
                <?php if ($v['description']): ?><div class="sim-desc"><?= htmlspecialchars($v['description'],ENT_QUOTES) ?></div><?php endif; ?>
              </div>
              <div class="sim-card-body">
                <?php if ($v['grade_level']): ?><div class="sim-grade"><?= htmlspecialchars($v['grade_level'],ENT_QUOTES) ?></div><?php endif; ?>
                <div class="sim-meta">
                  <?php if ($isRedacao): ?>
                    <span>✍️ <?= count($redacoesPorVest[(int)$v['id']] ?? []) ?> tema(s)</span>
                  <?php else: ?>
                    <span>📝 <?= $v['total_questions'] ?> questão(ões)</span>
                    <?php if ($timeInfo): ?><span><?= $timeInfo ?></span><?php endif; ?>
                  <?php endif; ?>
                </div>
                <button class="btn-start" <?= !$podeAbrir?'disabled':'' ?>
                        onclick="event.stopPropagation();<?= $podeAbrir?$onFn:'' ?>">
                  <?php
                  if ($penalty) echo '🚫 Penalizado';
                  elseif ($isRedacao && !$temTema) echo 'Sem temas';
                  elseif ($isRedacao) echo '✍️ Praticar redação';
                  elseif (!$hasQs) echo 'Sem questões';
                  else echo '▶ Iniciar simulado';
                  ?>
                </button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($vests)): ?><div class="empty-sim"><p>Nenhum simulado disponível.</p></div><?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- TELA 2: INÍCIO SIMULADO -->
  <div id="screen-start">
    <div class="start-card">
      <div class="start-head">
        <div class="start-cat" id="start-cat"></div>
        <div class="start-title" id="start-title"></div>
        <div class="start-meta" id="start-meta"></div>
      </div>
      <div class="start-body">
        <div class="warn-box">
          <span class="warn-ico">⚠️</span>
          <div class="warn-text">
            <strong>Atenção: regras do simulado</strong>
            Mais de <strong>2 trocas de aba</strong> geram penalização de <strong>5 dias</strong>.
          </div>
        </div>
        <div class="topics-section">
          <h4>📚 Conteúdos abordados</h4>
          <div class="topics-list" id="start-topics"></div>
        </div>
        <div class="timer-info" id="start-timer-info" style="display:none">
          <span>⏱</span><span id="start-timer-text"></span>
        </div>
        <div class="start-actions">
          <button class="btn-back" onclick="backToList()">← Voltar</button>
          <button class="btn-go" id="btnGo" onclick="startExam()">🚀 Iniciar simulado</button>
        </div>
      </div>
    </div>
  </div>

  <!-- TELA 2B: INÍCIO REDAÇÃO -->
  <div id="screen-redacao-intro">
    <div class="start-card">
      <div class="start-head">
        <div class="start-cat">✍️ Redação — Modelo ENEM</div>
        <div class="start-title" id="red-intro-title"></div>
        <div class="start-meta" id="red-intro-meta"></div>
      </div>
      <div class="start-body">
        <div class="warn-box">
          <span class="warn-ico">📋</span>
          <div class="warn-text">
            <strong>Como funciona</strong>
            Leia a proposta e os textos motivadores, depois escreva sua redação.
            Você pode alternar entre os textos de apoio e a folha de redação. Ao sair, receberá um aviso se houver texto não salvo.
          </div>
        </div>
        <div>
          <h4 style="font-size:.78rem;font-weight:600;color:#888;margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.05em">📌 Tema</h4>
          <div id="red-intro-tema" style="font-size:.88rem;color:var(--n800);line-height:1.6;padding:.75rem 1rem;background:var(--n50);border-radius:var(--rs);border:1px solid rgba(0,0,0,.07)"></div>
        </div>
        <div style="display:flex;flex-direction:column;gap:.4rem">
          <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#888">📊 Critérios ENEM (cada um vale 0–200)</div>
          <?php foreach ([['📖','C1','Norma culta'],['🎯','C2','Compreensão da proposta'],['🧠','C3','Argumentação'],['🔗','C4','Coesão textual'],['💡','C5','Proposta de intervenção']] as [$i,$c,$l]): ?>
          <div style="display:flex;align-items:center;gap:.5rem;font-size:.78rem;padding:.3rem .5rem;background:var(--n50);border-radius:6px">
            <span><?= $i ?></span><span style="font-weight:700;color:var(--g500);min-width:2rem"><?= $c ?></span><span style="flex:1"><?= $l ?></span><span style="color:#ccc;font-size:.7rem">0–200</span>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="start-actions">
          <button class="btn-back" onclick="backToList()">← Voltar</button>
          <button class="btn-go" onclick="entrarRedacao()">✍️ Iniciar redação</button>
        </div>
      </div>
    </div>
  </div>

  <!-- TELA 3: PROVA -->
  <div id="screen-exam">
    <div class="exam-topbar">
      <div style="display:flex;align-items:center;gap:1rem">
        <span class="exam-title" id="exam-title"></span>
        <span class="exam-prog" id="exam-prog"></span>
      </div>
      <div class="exam-timer" id="exam-timer">--:--</div>
    </div>
    <div class="exam-q-tabs" id="exam-q-tabs"></div>
    <div class="exam-body"><div class="question-wrap" id="question-wrap"></div></div>
    <div class="exam-nav">
      <button class="btn-nav prev" id="btnPrev" onclick="navigate(-1)">← Anterior</button>
      <button class="btn-nav next" id="btnNext" onclick="navigate(1)">Próxima →</button>
    </div>
  </div>

  <!-- TELA 4: RESULTADO -->
  <div id="screen-result">
    <div class="result-wrap">
      <div class="result-card">
        <div class="result-head" id="result-head"></div>
        <div class="result-body">
          <div class="result-stats" id="result-stats"></div>
          <div id="result-gabarito"></div>
        </div>
      </div>
      <div class="result-actions">
        <button class="btn-retry" onclick="retryExam()">🔄 Tentar novamente</button>
        <button class="btn-home" onclick="backToList()">🏠 Voltar</button>
      </div>
    </div>
  </div>

  <!-- TELA 5: REDAÇÃO -->
  <div id="screen-redacao">
    <div class="red-topbar">
      <div>
        <div class="red-title-bar" id="red-title-bar">Redação</div>
      </div>
      <div class="red-words" id="red-words">0 palavras</div>
    </div>
    <div class="red-tabs">
      <button class="red-tab" id="tab-apoio" onclick="showRedTab('apoio')">📄 Textos de apoio</button>
      <button class="red-tab active" id="tab-folha" onclick="showRedTab('folha')">✏️ Minha redação</button>
    </div>

    <!-- Painel apoio -->
    <div class="red-apoio" id="panel-apoio">
      <div class="apoio-card">
        <div class="apoio-label">📋 Proposta</div>
        <div class="apoio-text" id="red-proposta"></div>
      </div>
      <div id="red-textos"></div>
    </div>

    <!-- Folha de redação -->
    <div class="red-folha show" id="panel-folha">
      <div class="folha-wrap">
        <div class="folha-head">
          <div class="folha-head-title">FOLHA DE REDAÇÃO</div>
        </div>
        <div class="folha-info">
          <div class="folha-info-nome">NOME: <strong><?= $userName ?></strong></div>
          <div class="folha-info-data">
            <span>DATA: <strong><?= date('d/m/Y') ?></strong></span>
          </div>
        </div>
        <div class="folha-linhas">
          <div class="folha-linhas-bg" id="folha-linhas-bg">
            <?php for($ln=1;$ln<=30;$ln++): ?>
            <div class="folha-linha" style="border-bottom-color:<?= ($ln%5===0)?'#bbb':'#ebebeb' ?>">
              <div class="folha-linha-num <?= ($ln%5===0)?'m5':'' ?>"><?= $ln ?></div>
            </div>
            <?php endfor; ?>
          </div>
          <textarea class="folha-textarea" id="red-texto" rows="30"
            oninput="atualizarRedacao()"
            onscroll="sincronizarScroll(this)"
            spellcheck="true"
            placeholder="Escreva aqui..."></textarea>
        </div>
        <div class="folha-footer">florescer · Redação ENEM · 30 linhas · máx. 30 linhas (~500 palavras)</div>
      </div>
    </div>

    <div class="red-nav">
      <button class="btn-nav prev" onclick="sairRedacao()">← Voltar</button>
      <button class="btn-nav next" id="btnSaveRed" onclick="salvarRedacao()">💾 Salvar rascunho</button>
    </div>
  </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
const API      = '/florescer/api/simulated.php';
const API_RED  = '/florescer/api/redacao.php';
const PENALIZED = <?= $penalty ? 'true' : 'false' ?>;
const REDACOES  = <?= json_encode(array_map(fn($v)=>$v, $redacoesPorVest), JSON_UNESCAPED_UNICODE) ?>;

let State = {
  vestId:null, vestName:null, vestData:null, attemptId:null,
  questions:[], answers:{}, currentQ:0,
  timerInterval:null, secondsLeft:0, tabSwitches:0,
};
let RedState = { vestId:null, redacaoId:null, saved:true };

/* ── Utils ── */
function toast(msg, type='ok', ms=3500) {
  const w=document.getElementById('toastWrap'), d=document.createElement('div');
  d.className=`toast ${type}`;
  d.innerHTML=`<span>${type==='ok'?'✅':type==='err'?'❌':'⚠️'}</span><span>${msg}</span>`;
  w.appendChild(d);
  setTimeout(()=>{d.style.opacity='0';d.style.transition='.3s';setTimeout(()=>d.remove(),320);},ms);
}
async function apiFetch(url, body) {
  try {
    const r = await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    return await r.json();
  } catch { return {success:false,message:'Erro de conexão.'}; }
}
function esc(s){ return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function showScreen(id) {
  ['screen-list','screen-start','screen-redacao-intro','screen-exam','screen-result','screen-redacao'].forEach(s=>{
    const el=document.getElementById(s);
    if(el) el.style.display = s===id ? 'flex' : 'none';
  });
}

function backToList() {
  clearInterval(State.timerInterval);
  State = {vestId:null,vestName:null,vestData:null,attemptId:null,questions:[],answers:{},currentQ:0,timerInterval:null,secondsLeft:0,tabSwitches:0};
  showScreen('screen-list');
}

/* ── SIMULADO ── */
async function showStart(vestId) {
  if (PENALIZED) return;
  const r = await apiFetch(API, {action:'get_questions', vestibular_id:vestId});
  if (!r.success) { toast(r.message||'Erro.','err'); return; }
  if (!r.questions?.length) { toast('Sem questões ativas.','warn'); return; }
  State.vestId=vestId; State.vestName=r.vest.name; State.vestData=r.vest; State.questions=r.questions;
  const catMap={escolar:'🏫 Escolar',vestibular:'🎓 Vestibular',materia:'📚 Por Matéria'};
  document.getElementById('start-cat').textContent   = catMap[r.vest.category]||'Simulado';
  document.getElementById('start-title').textContent = r.vest.name;
  document.getElementById('start-meta').textContent  = `${r.questions.length} questões · ${r.vest.description||''}`;
  const tList = document.getElementById('start-topics');
  tList.innerHTML = r.topics?.length
    ? r.topics.map(t=>`<span class="topic-chip">${esc(t)}</span>`).join('')
    : '<span class="no-topics">Múltiplas disciplinas</span>';
  const tInfo=document.getElementById('start-timer-info');
  tInfo.style.display = r.vest.time_max ? 'flex' : 'none';
  if (r.vest.time_max) document.getElementById('start-timer-text').textContent =
    r.vest.time_min ? `Tempo: ${r.vest.time_min}–${r.vest.time_max} min` : `Máx. ${r.vest.time_max} min`;
  showScreen('screen-start');
}

async function startExam() {
  const btn=document.getElementById('btnGo');
  btn.disabled=true; btn.textContent='Iniciando…';
  const r = await apiFetch(API, {action:'start', vestibular_id:State.vestId});
  btn.disabled=false; btn.textContent='🚀 Iniciar simulado';
  if (!r.success) { toast(r.message||'Erro.','err'); return; }
  State.attemptId=r.attempt_id; State.answers={}; State.currentQ=0; State.tabSwitches=0;
  const maxMin = parseInt(State.vestData?.time_max)||0;
  if (maxMin > 0) { State.secondsLeft=maxMin*60; startTimer(); }
  renderExam(); setupAnticola(); showScreen('screen-exam');
}

function renderExam() {
  const q=State.questions[State.currentQ], tot=State.questions.length, cur=State.currentQ;
  document.getElementById('exam-title').textContent = State.vestName;
  document.getElementById('exam-prog').textContent  = `Questão ${cur+1} de ${tot}`;
  document.getElementById('exam-q-tabs').innerHTML  = State.questions.map((_,i)=>
    `<button class="q-tab ${State.answers[State.questions[i].id]?'answered':''} ${i===cur?'current':''}" onclick="goToQ(${i})">${i+1}</button>`
  ).join('');
  const selected=State.answers[q.id]||null;
  document.getElementById('question-wrap').innerHTML = `
    <div class="q-header">
      <span class="q-num">Questão ${cur+1}</span>
      ${q.subject_tag?`<span class="q-area-badge">${esc(q.subject_tag)}</span>`:''}
      ${q.year?`<span class="q-num">${q.year}</span>`:''}
    </div>
    <div class="q-statement">${esc(q.statement).replace(/\n/g,'<br>')}</div>
    <div class="options-list">
      ${['a','b','c','d','e'].filter(l=>q['option_'+l]).map(l=>`
        <button class="option-btn ${selected===l?'selected':''}" onclick="selectAnswer('${q.id}','${l}')">
          <span class="opt-letter">${l.toUpperCase()}</span>
          <span class="opt-text">${esc(q['option_'+l])}</span>
        </button>`).join('')}
    </div>`;
  document.getElementById('btnPrev').style.visibility = cur===0?'hidden':'visible';
  const isLast=cur===tot-1;
  const nb=document.getElementById('btnNext');
  nb.className=`btn-nav ${isLast?'finish next':'next'}`;
  nb.textContent=isLast?'✅ Finalizar':'Próxima →';
  nb.onclick=isLast?finishExam:()=>navigate(1);
}

function goToQ(i){ State.currentQ=i; renderExam(); }
function navigate(d){ const n=State.currentQ+d; if(n>=0&&n<State.questions.length)goToQ(n); }
function selectAnswer(qId,l){ State.answers[qId]=l; renderExam(); }

function startTimer(){
  updateTimer();
  State.timerInterval=setInterval(()=>{
    State.secondsLeft--;updateTimer();
    if(State.secondsLeft<=0){clearInterval(State.timerInterval);toast('⏱ Tempo esgotado!','warn');setTimeout(finishExam,1500);}
  },1000);
}
function updateTimer(){
  const el=document.getElementById('exam-timer');
  const m=Math.floor(State.secondsLeft/60),s=State.secondsLeft%60;
  el.textContent=`${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
  el.className=`exam-timer${State.secondsLeft<=120?' warning':''}`;
}

function setupAnticola(){ document.addEventListener('visibilitychange',onVis); window.addEventListener('blur',onBlur); }
function teardownAnticola(){ document.removeEventListener('visibilitychange',onVis); window.removeEventListener('blur',onBlur); }
async function onVis(){ if(document.hidden&&State.attemptId)await regSwitch(); }
async function onBlur(){ if(State.attemptId)await regSwitch(); }
let _ls=0;
async function regSwitch(){
  const now=Date.now(); if(now-_ls<500)return; _ls=now;
  const r=await apiFetch(API,{action:'tab_switch',attempt_id:State.attemptId});
  if(!r.success)return;
  State.tabSwitches=r.switches;
  const rem=Math.max(0,r.limit-r.switches);
  const ex=document.querySelector('.anticola-banner');if(ex)ex.remove();
  const b=document.createElement('div'); b.className='anticola-banner';
  b.innerHTML=`<span>⚠️ Troca de aba! (${r.switches}/${r.limit}) — ${rem} chance(s) restante(s)</span><button onclick="this.parentNode.remove()" style="background:none;border:none;color:#fff;cursor:pointer">✕</button>`;
  document.body.appendChild(b); setTimeout(()=>{if(b.parentNode)b.remove();},5000);
  if(r.penalized){ teardownAnticola(); clearInterval(State.timerInterval); toast('🚫 Penalizado por 5 dias!','err',8000); setTimeout(()=>location.reload(),3000); }
}

async function finishExam(){
  clearInterval(State.timerInterval); teardownAnticola();
  const btn=document.getElementById('btnNext');
  if(btn){btn.disabled=true;btn.textContent='Calculando…';}
  const r=await apiFetch(API,{action:'finish',attempt_id:State.attemptId,answers:State.answers});
  if(!r.success){toast(r.message||'Erro.','err');if(btn){btn.disabled=false;btn.textContent='✅ Finalizar';}return;}
  showResult(r); showScreen('screen-result');
}

function showResult(r){
  const passed=r.pct>=r.pass_score;
  const emoji=r.pct>=80?'🏆':r.pct>=60?'🎉':r.pct>=40?'📚':'💪';
  document.getElementById('result-head').innerHTML=`
    <div class="result-emoji">${emoji}</div>
    <div class="result-title">${passed?'Aprovado!':'Continue treinando!'}</div>
    <div class="result-score">${r.score}/${r.total}</div>
    <div class="result-pct">${r.pct}% de acerto</div>
    <div class="result-sub">+${r.xp_earned} XP</div>`;
  const wrong=r.total-r.score;
  document.getElementById('result-stats').innerHTML=`
    <div class="res-stat"><div class="res-stat-val" style="color:var(--g500)">${r.score}</div><div class="res-stat-lbl">Acertos</div></div>
    <div class="res-stat"><div class="res-stat-val" style="color:var(--red)">${wrong}</div><div class="res-stat-lbl">Erros</div></div>
    <div class="res-stat"><div class="res-stat-val">${r.pct}%</div><div class="res-stat-lbl">Aproveit.</div></div>`;
  const gab=document.getElementById('result-gabarito');
  if(!r.show_answers||!Object.keys(r.results||{}).length){gab.innerHTML='';return;}
  gab.innerHTML='<div class="gabarito-title">📋 Gabarito</div>'+
    Object.entries(r.results).map(([,res],i)=>`
      <div class="gab-item ${res.is_correct?'correct':'wrong'}">
        <div class="gab-q-num">Questão ${i+1} ${res.is_correct?'✅':'❌'}</div>
        <div class="gab-stmt">${esc(res.statement).substring(0,200)}…</div>
        <div class="gab-answer">
          <span class="gab-correct">Correta: ${res.correct.toUpperCase()}</span>
          <span class="gab-given ${res.is_correct?'ok':''}">Sua: ${res.given?res.given.toUpperCase():'—'}</span>
        </div>
        ${res.explanation?`<div class="gab-expl">${esc(res.explanation)}</div>`:''}
      </div>`).join('');
}

function retryExam(){ if(State.vestId)showStart(State.vestId); else backToList(); }

/* ── REDAÇÃO ── */
function showRedacaoIntro(vestId) {
  const temas = REDACOES[vestId];
  if (!temas?.length) { toast('Nenhum tema disponível.','warn'); return; }
  const red = temas[0];
  RedState.vestId = vestId; RedState.redacaoId = red.id;
  document.getElementById('red-intro-title').textContent = red.tema;
  document.getElementById('red-intro-meta').textContent  = 'Dissertativo-argumentativo · Modelo ENEM';
  document.getElementById('red-intro-tema').textContent  = red.tema;
  showScreen('screen-redacao-intro');
}

async function entrarRedacao() {
  const red = REDACOES[RedState.vestId]?.[0];
  if (!red) return;
  // Proposta
  document.getElementById('red-proposta').textContent = red.proposta||'';
  // Textos motivadores
  const textos=[red.texto1,red.texto2,red.texto3].filter(Boolean);
  document.getElementById('red-textos').innerHTML = textos.map((t,i)=>`
    <div class="apoio-card">
      <div class="apoio-label">📄 Texto ${i+1}</div>
      <div class="apoio-text">${t.replace(/\n/g,'<br>')}</div>
    </div>`).join('');
  // Título
  document.getElementById('red-title-bar').textContent = red.tema;
  // Carrega rascunho salvo
  const r = await apiFetch(API_RED, {action:'get', redacao_id:red.id});
  const ta = document.getElementById('red-texto');
  ta.value = r.success && r.data?.texto ? r.data.texto : '';
  RedState.saved = true;
  atualizarRedacao();
  showRedTab('folha');
  showScreen('screen-redacao');
}

function showRedTab(tab) {
  document.getElementById('panel-apoio').classList.toggle('show', tab==='apoio');
  document.getElementById('panel-folha').classList.toggle('show', tab==='folha');
  document.getElementById('tab-apoio').classList.toggle('active', tab==='apoio');
  document.getElementById('tab-folha').classList.toggle('active', tab==='folha');
}

function atualizarRedacao() {
  const txt=document.getElementById('red-texto').value;
  const palavras=txt.trim()?txt.trim().split(/\s+/).length:0;
  document.getElementById('red-words').textContent=palavras+' palavra'+(palavras!==1?'s':'');
  RedState.saved=false;
  // Expande linhas
  const linhas=document.getElementById('folha-linhas-bg');
  const totalLinhas=txt.split('\n').length+2;
  while(linhas.children.length < Math.max(30,totalLinhas)){
    const n=linhas.children.length+1;
    const div=document.createElement('div');
    div.className='folha-linha';
    div.style.borderBottomColor=n%5===0?'#bbb':'#ebebeb';
    div.innerHTML=`<div class="folha-linha-num ${n%5===0?'m5':''}">${n}</div>`;
    linhas.appendChild(div);
  }
}

function sincronizarScroll(ta) {
  document.getElementById('folha-linhas-bg').style.marginTop=`-${ta.scrollTop}px`;
}

async function salvarRedacao() {
  const texto = document.getElementById('red-texto').value.trim();
  if (!texto) { toast('Escreva algo antes de salvar.', 'warn'); return; }
  const btn = document.getElementById('btnSaveRed');
  btn.disabled = true; btn.textContent = 'Salvando…';
  try {
    const resp = await fetch(API_RED, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({action:'save', redacao_id: RedState.redacaoId, vestibular_id: RedState.vestId, texto})
    });
    console.log('Status:', resp.status);
    const text = await resp.text();
    console.log('Response text:', text);
    const r = JSON.parse(text);
    if (r.success) { toast('Redação salva! 💚'); RedState.saved = true; }
    else toast(r.message || 'Erro.', 'err');
  } catch(e) {
    console.error('Erro:', e);
    toast('Erro: ' + e.message, 'err');
  }
  btn.disabled = false; btn.textContent = '💾 Salvar rascunho';
}

function sairRedacao() {
  if(!RedState.saved && document.getElementById('red-texto').value.trim()) {
    if(!confirm('Você tem texto não salvo. Deseja sair mesmo assim?')) return;
  }
  backToList();
}

/* ── Init ── */
showScreen('screen-list');
</script>
</body>
</html>