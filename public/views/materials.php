<?php
// ============================================================
// public/views/materials.php — florescer v2.4
// CORREÇÕES: navegação entre aulas, timer automático
// ============================================================
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/unit_helper.php';

startSession();
authGuard();

$user        = currentUser();
$userId      = (int)$user['id'];
$userName    = htmlspecialchars($user['name'] ?? 'Estudante', ENT_QUOTES, 'UTF-8');
$userInitial = strtoupper(mb_substr($user['name'] ?? 'E', 0, 1, 'UTF-8'));
$currentPage = 'materials';

// ── Objetivo ativo ─────────────────────────────────────────────
if (!isset($_SESSION['active_objective'])) {
    $ao = dbRow('SELECT id FROM objectives WHERE user_id=? AND is_active=1 ORDER BY created_at DESC LIMIT 1', [$userId]);
    if (!$ao) $ao = dbRow('SELECT id FROM objectives WHERE user_id=? ORDER BY created_at DESC LIMIT 1', [$userId]);
    $_SESSION['active_objective'] = $ao['id'] ?? null;
}
$activeObjId = $_SESSION['active_objective'];

$activeObjData = null;
$objUnitCount  = 4;

if ($activeObjId) {
    $activeObjData = dbRow(
        'SELECT o.unit_count, tt.periods AS tt_periods
         FROM objectives o
         LEFT JOIN teaching_types tt ON tt.id = o.teaching_type_id
         WHERE o.id = ? AND o.user_id = ?',
        [$activeObjId, $userId]
    );
    $objUnitCount = getUnitCount($activeObjData);
}

$unitNames = buildUnitNames($objUnitCount);

// ── Sidebar vars ───────────────────────────────────────────────
$userData = dbRow('SELECT xp, level, streak FROM users WHERE id=?', [$userId]);
$xp     = (int)($userData['xp']     ?? 0);
$level  = (int)($userData['level']  ?? 1);
$streak = (int)($userData['streak'] ?? 0);
$lvN    = ['','Semente','Broto','Estudante','Aplicado','Dedicado','Avançado','Expert','Mestre','Lendário'];
$lvName = $lvN[min($level, count($lvN)-1)] ?? 'Lendário';
$stages = [
    [0,6,'🌱','Semente'],[7,14,'🌿','Broto Inicial'],[15,29,'☘️','Planta Jovem'],
    [30,59,'🌲','Planta Forte'],[60,99,'🌳','Árvore Crescendo'],[100,149,'🌴','Árvore Robusta'],
    [150,199,'🎋','Árvore Antiga'],[200,299,'✨','Árvore Gigante'],[300,PHP_INT_MAX,'🏆','Árvore Lendária']
];
$plant = ['emoji'=>'🌱','name'=>'Semente','pct'=>0];
foreach ($stages as [$mn,$mx,$em,$nm]) {
    if ($streak >= $mn && $streak <= $mx) {
        $r2 = $mx < PHP_INT_MAX ? $mx - $mn + 1 : 1;
        $plant = ['emoji'=>$em,'name'=>$nm,'pct'=>$mx < PHP_INT_MAX ? min(100,round(($streak-$mn)/$r2*100)) : 100];
        break;
    }
}
$allObjs = dbQuery('SELECT id, name FROM objectives WHERE user_id=? ORDER BY is_active DESC, created_at DESC', [$userId]);

// ── Matérias do objetivo ativo ─────────────────────────────────
$subjects = [];
if ($activeObjId) {
    $rawSubjects = dbQuery(
        'SELECT s.id, s.name, s.color,
            (SELECT COUNT(*) FROM topics t WHERE t.subject_id=s.id) AS topic_count,
            (SELECT COUNT(*) FROM lessons l JOIN topics t ON t.id=l.topic_id WHERE t.subject_id=s.id AND l.is_completed=1) AS done,
            (SELECT COUNT(*) FROM lessons l JOIN topics t ON t.id=l.topic_id WHERE t.subject_id=s.id) AS total
         FROM subjects s
         WHERE s.objective_id=? AND s.is_active=1
         ORDER BY s.name ASC',
        [$activeObjId]
    );
    foreach ($rawSubjects as $s) {
        $subjects[] = [
            'id'          => (int)$s['id'],
            'name'        => $s['name'] ?? '',
            'color'       => $s['color'] ?: '#40916c',
            'topic_count' => (int)($s['topic_count'] ?? 0),
            'done'        => (int)($s['done']  ?? 0),
            'total'       => (int)($s['total'] ?? 0),
        ];
    }
}

function subjIcon(string $n): string {
    $n = mb_strtolower($n, 'UTF-8');
    foreach (['/mat|álgeb|geom/u'=>'📐','/port|liter|gramát|redaç/u'=>'📝','/hist/u'=>'🏛️',
              '/geo/u'=>'🌍','/bio/u'=>'🧬','/fís/u'=>'⚡','/quím/u'=>'🧪',
              '/ingl|espan/u'=>'🌐','/art/u'=>'🎨','/educ.fís/u'=>'⚽','/fil/u'=>'🤔',
              '/soc/u'=>'👥','/ciên/u'=>'🔬'] as $re=>$ico) {
        if (preg_match($re, $n)) return $ico;
    }
    return '📚';
}

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
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>florescer — Matérias</title>
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
    --r:14px;--rs:8px;--d:.2s;--e:cubic-bezier(.4,0,.2,1);
    --sh0:0 1px 3px rgba(0,0,0,.05);--sh1:0 2px 8px rgba(0,0,0,.06);
    --sh2:0 4px 16px rgba(0,0,0,.08);--sh3:0 12px 32px rgba(0,0,0,.11);
  }
  html,body{height:100%}
  body{font-family:var(--fb);background:var(--n50);color:var(--n800);display:flex;overflow-x:hidden;-webkit-font-smoothing:antialiased}
  ::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:rgba(64,145,108,.2);border-radius:2px}
  .main{margin-left:var(--sw);flex:1;min-height:100vh;display:flex;flex-direction:column;min-width:0}
  .topbar{height:var(--hh);background:rgba(250,248,245,.94);backdrop-filter:blur(14px);border-bottom:1px solid rgba(0,0,0,.055);display:flex;align-items:center;justify-content:space-between;padding:0 1.8rem;position:sticky;top:0;z-index:40;flex-shrink:0;gap:.8rem}
  .tb-left{display:flex;align-items:center;gap:.8rem;min-width:0;flex:1}
  .hamburger{display:none;flex-direction:column;gap:4px;cursor:pointer;background:none;border:none;padding:5px;border-radius:6px;flex-shrink:0}
  .hamburger span{display:block;width:19px;height:2px;background:var(--g600);border-radius:1px;transition:all var(--d) var(--e)}
  .hamburger.open span:nth-child(1){transform:translateY(6px) rotate(45deg)}
  .hamburger.open span:nth-child(2){opacity:0}
  .hamburger.open span:nth-child(3){transform:translateY(-6px) rotate(-45deg)}
  .tb-title{font-family:var(--fd);font-size:.98rem;font-weight:600;color:var(--n800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .timer-wrap{display:none;align-items:center;gap:.6rem;flex-shrink:0}
  .timer-wrap.show{display:flex}
  .clock-ring{position:relative;width:36px;height:36px;flex-shrink:0}
  .clock-svg{width:36px;height:36px}
  .clock-track{fill:none;stroke:rgba(64,145,108,.12);stroke-width:3}
  .clock-arc{fill:none;stroke:var(--g400);stroke-width:3;stroke-linecap:round;stroke-dasharray:94.25;stroke-dashoffset:94.25;transform-origin:18px 18px;transform:rotate(-90deg);transition:stroke-dashoffset .5s var(--e)}
  .clock-arc.danger{stroke:#f87171}
  .clock-time-inner{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-size:.55rem;font-weight:700;color:var(--g500);line-height:1}
  .timer-info{display:flex;flex-direction:column;gap:1px}
  .timer-elapsed{font-family:var(--fd);font-size:.9rem;font-weight:700;color:var(--n800);line-height:1}
  .timer-elapsed.running{color:var(--g500)}
  .timer-goal-label{font-size:.62rem;color:#aaa;line-height:1}
  .timer-toggle{position:relative}
  .timer-toggle-btn{background:var(--white);border:1px solid rgba(0,0,0,.08);border-radius:50px;padding:.22rem .72rem;font-family:var(--fb);font-size:.76rem;font-weight:500;color:#888;cursor:pointer;box-shadow:var(--sh0);transition:all var(--d) var(--e);display:flex;align-items:center;gap:.3rem}
  .timer-toggle-btn:hover,.timer-toggle-btn.running{border-color:rgba(64,145,108,.25);color:var(--g500)}
  .timer-menu{position:absolute;top:calc(100% + 6px);right:0;background:var(--white);border:1px solid rgba(0,0,0,.09);border-radius:var(--r);box-shadow:var(--sh3);padding:.5rem;min-width:160px;display:none;z-index:100}
  .timer-menu.open{display:block}
  .tm-btn{width:100%;padding:.48rem .7rem;margin-bottom:.22rem;border:none;border-radius:var(--rs);font-family:var(--fb);font-size:.8rem;font-weight:500;cursor:pointer;transition:all var(--d) var(--e);text-align:left;display:flex;align-items:center;gap:.4rem}
  .tm-btn:last-child{margin-bottom:0}
  .tm-start{background:linear-gradient(135deg,var(--g500),var(--g600));color:var(--white)}
  .tm-start:hover{filter:brightness(1.08)}
  .tm-reset{background:var(--red-l);color:var(--red)}
  .tm-reset:hover{background:#fecaca}
  .tm-sep{height:1px;background:rgba(0,0,0,.06);margin:.25rem 0}
  .tm-goal-row{display:flex;align-items:center;gap:.4rem;padding:.3rem .7rem}
  .tm-goal-lbl{font-size:.72rem;color:#999;flex:1}
  .tm-goal-sel{background:none;border:none;font-family:var(--fb);font-size:.76rem;color:var(--g500);cursor:pointer;outline:none}
  .content{flex:1;padding:1.6rem 2rem}
  .breadcrumb{display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;font-size:.76rem;color:#bbb;margin-bottom:1.1rem}
  .bc-btn{color:var(--g500);cursor:pointer;background:none;border:none;font-family:var(--fb);font-size:.76rem;padding:0;transition:color var(--d) var(--e)}
  .bc-btn:hover{color:var(--g600)}
  .bc-sep{color:#ddd}
  .bc-cur{color:var(--n800);font-weight:500}
  .sec-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.4rem;flex-wrap:wrap;gap:.8rem}
  .sec-title{font-family:var(--fd);font-size:1.28rem;font-weight:900;color:var(--n800);letter-spacing:-.03em}
  .btn-primary{display:inline-flex;align-items:center;gap:.38rem;padding:.48rem 1rem;background:linear-gradient(135deg,var(--g500),var(--g600));color:var(--white);border:none;border-radius:50px;font-family:var(--fb);font-size:.78rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);box-shadow:0 3px 10px rgba(64,145,108,.22);white-space:nowrap}
  .btn-primary:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(64,145,108,.32)}
  .btn-ghost{display:inline-flex;align-items:center;gap:.28rem;padding:.34rem .68rem;border-radius:50px;background:transparent;border:1px solid rgba(0,0,0,.1);font-family:var(--fb);font-size:.71rem;font-weight:500;color:#888;cursor:pointer;transition:all var(--d) var(--e)}
  .btn-ghost:hover{background:var(--n100);color:var(--n800)}
  .btn-danger{display:inline-flex;align-items:center;gap:.28rem;padding:.34rem .68rem;border-radius:50px;background:transparent;border:1px solid rgba(220,38,38,.15);font-family:var(--fb);font-size:.71rem;font-weight:500;color:var(--red);cursor:pointer;transition:all var(--d) var(--e)}
  .btn-danger:hover{background:var(--red-l)}
  .subj-list{display:flex;flex-direction:column;gap:.75rem;max-width:1000px}
  .subj-card{background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r);overflow:hidden;box-shadow:var(--sh0);transition:all var(--d) var(--e);display:flex;cursor:pointer}
  .subj-card:hover{transform:translateX(4px);box-shadow:var(--sh2);border-color:rgba(64,145,108,.12)}
  .subj-card-stripe{width:5px;flex-shrink:0}
  .subj-card-body{flex:1;padding:1rem 1.2rem;display:flex;align-items:center;gap:1rem;min-width:0}
  .subj-ico-wrap{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0}
  .subj-card-info{flex:1;min-width:0}
  .subj-card-name{font-size:.92rem;font-weight:600;color:var(--n800);margin-bottom:.25rem}
  .subj-card-meta{font-size:.71rem;color:#bbb;display:flex;gap:.6rem;margin-bottom:.5rem}
  .subj-card-prog{display:flex;align-items:center;gap:.6rem}
  .subj-prog-bar{flex:1;height:3px;background:var(--n100);border-radius:2px;overflow:hidden;max-width:160px}
  .subj-prog-fill{height:100%;border-radius:2px;transition:width .6s var(--e)}
  .subj-prog-pct{font-size:.72rem;font-weight:600}
  .subj-card-actions{display:flex;gap:.3rem;align-items:center;flex-shrink:0}
  .units-view{max-width:1000px}
  .subject-header-card{background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r);overflow:hidden;box-shadow:var(--sh0);margin-bottom:1.2rem;display:flex}
  .shc-stripe{width:5px;flex-shrink:0}
  .shc-body{flex:1;padding:1rem 1.2rem;display:flex;align-items:center;gap:1rem}
  .shc-ico{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0}
  .shc-info{flex:1}
  .shc-name{font-size:.98rem;font-weight:700;color:var(--n800)}
  .shc-meta{font-size:.72rem;color:#bbb;margin-top:.15rem}
  .units-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.7rem}
  .unit-card{background:var(--white);border:1px solid rgba(0,0,0,.07);border-radius:var(--r);padding:1rem 1.1rem;cursor:pointer;transition:all var(--d) var(--e);box-shadow:var(--sh0);position:relative;overflow:hidden}
  .unit-card:hover{transform:translateY(-3px);box-shadow:var(--sh2);border-color:rgba(64,145,108,.15)}
  .unit-number{font-size:.62rem;font-weight:600;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.35rem}
  .unit-name{font-size:.86rem;font-weight:600;color:var(--n800);margin-bottom:.5rem}
  .unit-meta{font-size:.7rem;color:#bbb;margin-bottom:.6rem}
  .unit-pbar{height:3px;background:var(--n100);border-radius:2px;overflow:hidden}
  .unit-pfill{height:100%;border-radius:2px;transition:width .6s var(--e)}
  .topics-view{max-width:1000px}
  .unit-header-card{background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r);overflow:hidden;box-shadow:var(--sh0);margin-bottom:1rem;display:flex}
  .uhc-stripe{width:5px;flex-shrink:0}
  .uhc-body{flex:1;padding:.85rem 1.2rem;display:flex;align-items:center;justify-content:space-between;gap:1rem}
  .uhc-info{flex:1}
  .uhc-label{font-size:.6rem;font-weight:600;text-transform:uppercase;letter-spacing:.1em;color:#bbb;margin-bottom:.1rem}
  .uhc-name{font-size:.96rem;font-weight:700;color:var(--n800)}
  .uhc-meta{font-size:.71rem;color:#bbb;margin-top:.1rem}
  .topics-list{display:flex;flex-direction:column;gap:.5rem}
  .topic-row{background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r);padding:.85rem 1.1rem;display:flex;align-items:center;gap:.8rem;cursor:pointer;transition:all var(--d) var(--e);box-shadow:var(--sh0)}
  .topic-row:hover{transform:translateX(3px);box-shadow:var(--sh1);border-color:rgba(64,145,108,.14)}
  .topic-row-icon{font-size:1rem;flex-shrink:0}
  .topic-row-info{flex:1;min-width:0}
  .topic-row-name{font-size:.86rem;font-weight:600;color:var(--n800);margin-bottom:.2rem}
  .topic-row-meta{font-size:.7rem;color:#bbb}
  .topic-row-prog{display:flex;align-items:center;gap:.5rem;flex-shrink:0}
  .topic-prog-bar{width:80px;height:3px;background:var(--n100);border-radius:2px;overflow:hidden}
  .topic-prog-fill{height:100%;border-radius:2px;transition:width .6s var(--e)}
  .topic-prog-pct{font-size:.7rem;font-weight:600;min-width:28px;text-align:right}
  .topic-row-actions{display:flex;gap:.25rem;flex-shrink:0}
  .lesson-page-layout{display:grid;grid-template-columns:1fr 380px;gap:1.3rem;align-items:start;max-width:1400px}
  .lesson-left{display:flex;flex-direction:column;gap:1rem;min-width:0}
  .video-card{background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r);overflow:hidden;box-shadow:var(--sh0)}
  .video-wrap{aspect-ratio:16/9;background:#0a1a0f;overflow:hidden}
  .video-wrap iframe{width:110%;height:110%;border:none;display:block}
  .video-placeholder{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--g950),var(--g800));color:rgba(116,198,157,.3);gap:.5rem;font-size:.8rem}
  .video-placeholder span:first-child{font-size:1.8rem;opacity:.35}
  .lesson-info-bar{padding:.9rem 1.1rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.6rem;border-top:1px solid rgba(0,0,0,.05)}
  .lesson-info-title{font-family:var(--fd);font-size:1rem;font-weight:700;color:var(--n800);flex:1;min-width:0}
  .btn-complete{display:inline-flex;align-items:center;gap:.35rem;padding:.45rem .95rem;border-radius:50px;border:none;font-family:var(--fb);font-size:.78rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);white-space:nowrap;flex-shrink:0}
  .btn-complete.pending{background:linear-gradient(135deg,var(--g500),var(--g600));color:var(--white);box-shadow:0 3px 10px rgba(64,145,108,.22)}
  .btn-complete.pending:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(64,145,108,.32)}
  .btn-complete.done{background:var(--g50);color:var(--g500);border:1px solid rgba(64,145,108,.2)}
  .lesson-nav-bar{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
  .lesson-nav-btn{display:inline-flex;align-items:center;gap:.3rem;padding:.38rem .75rem;border-radius:50px;border:1px solid rgba(0,0,0,.1);background:var(--white);font-family:var(--fb);font-size:.74rem;font-weight:500;color:#666;cursor:pointer;transition:all var(--d) var(--e);box-shadow:var(--sh0)}
  .lesson-nav-btn:hover:not(:disabled){border-color:rgba(64,145,108,.25);color:var(--g500);background:var(--g50)}
  .lesson-nav-btn:disabled{opacity:.35;cursor:not-allowed;pointer-events:none}
  .lesson-nav-info{font-size:.72rem;color:#bbb;margin:0 .2rem}
  .notes-card{background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r);overflow:hidden;box-shadow:var(--sh0);display:flex;flex-direction:column;position:sticky;top:calc(var(--hh) + 1rem)}
  .notes-head{padding:.85rem 1.1rem;border-bottom:1px solid rgba(0,0,0,.05);display:flex;align-items:center;justify-content:space-between;background:rgba(250,248,245,.5)}
  .notes-head-title{font-size:.86rem;font-weight:600;color:var(--n800);display:flex;align-items:center;gap:.4rem}
  .notes-ind{font-size:.68rem;color:#ccc;transition:color .3s}
  .notes-ind.saving{color:var(--gold)}
  .notes-ind.saved{color:var(--g400)}
  .notes-area{width:100%;padding:1rem 1.1rem;border:none;outline:none;font-family:var(--fb);font-size:.85rem;line-height:1.8;color:var(--n800);resize:none;background:transparent;min-height:calc(100vh - var(--hh) - 280px);max-height:calc(100vh - var(--hh) - 180px);overflow-y:auto}
  .notes-area::placeholder{color:#ccc}
  .notes-foot{padding:.65rem 1.1rem;border-top:1px solid rgba(0,0,0,.05);display:flex;align-items:center;gap:.4rem;background:rgba(250,248,245,.5)}
  .notes-chars{font-size:.68rem;color:#ccc;margin-left:auto}
  .swatches{display:flex;flex-wrap:wrap;gap:.38rem;margin-bottom:.6rem}
  .sw{width:22px;height:22px;border-radius:50%;cursor:pointer;border:2px solid transparent;transition:all var(--d) var(--e);flex-shrink:0}
  .sw:hover,.sw.sel{transform:scale(1.25);border-color:var(--n800)}
  .rgb-wrap{border-top:1px solid rgba(0,0,0,.06);padding-top:.6rem;margin-top:.2rem}
  .rgb-lbl{font-size:.7rem;color:#aaa;margin-bottom:.4rem}
  .rgb-row{display:grid;grid-template-columns:10px 1fr 30px;align-items:center;gap:.4rem;margin-bottom:.28rem}
  .rgb-dot{width:9px;height:9px;border-radius:50%}
  input[type=range]{-webkit-appearance:none;height:3px;border-radius:2px;outline:none;cursor:pointer;width:100%}
  input[type=range]::-webkit-slider-thumb{-webkit-appearance:none;width:13px;height:13px;border-radius:50%;background:var(--g500);cursor:pointer;border:2px solid white;box-shadow:0 1px 4px rgba(0,0,0,.18)}
  .color-preview{height:20px;border-radius:4px;margin-top:.5rem;border:1px solid rgba(0,0,0,.08)}
  .modal-overlay{position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.45);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;padding:1.2rem;opacity:0;pointer-events:none;transition:opacity var(--d) var(--e)}
  .modal-overlay.open{opacity:1;pointer-events:all}
  .modal{background:var(--white);border:1px solid rgba(0,0,0,.08);border-radius:var(--r);width:100%;max-width:430px;max-height:88vh;overflow-y:auto;box-shadow:var(--sh3);transform:translateY(14px) scale(.97);transition:transform var(--d) var(--e)}
  .modal-overlay.open .modal{transform:translateY(0) scale(1)}
  .modal-head{padding:1rem 1.2rem;border-bottom:1px solid rgba(0,0,0,.06);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--white);z-index:1}
  .modal-title{font-family:var(--fd);font-size:.92rem;font-weight:700;color:var(--n800)}
  .modal-x{width:26px;height:26px;border-radius:50%;background:var(--n100);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.78rem;color:#aaa;transition:all var(--d) var(--e)}
  .modal-x:hover{background:var(--red-l);color:var(--red)}
  .modal-body{padding:1.2rem}
  .modal-foot{padding:.8rem 1.2rem;border-top:1px solid rgba(0,0,0,.06);display:flex;gap:.4rem;justify-content:flex-end;background:var(--white)}
  .fg{margin-bottom:.8rem}
  .fl{display:block;font-size:.74rem;font-weight:500;color:#666;margin-bottom:.28rem}
  .fc{width:100%;padding:.55rem .8rem;background:var(--n50);border:1px solid rgba(0,0,0,.09);border-radius:var(--rs);color:var(--n800);font-family:var(--fb);font-size:.83rem;outline:none;transition:all var(--d) var(--e);appearance:none}
  .fc:focus{border-color:var(--g400);background:var(--white);box-shadow:0 0 0 3px rgba(64,145,108,.1)}
  .fc::placeholder{color:#ccc}
  .f-alert{padding:.55rem .8rem;border-radius:var(--rs);font-size:.78rem;margin-bottom:.8rem;display:none}
  .f-alert.show{display:block}
  .f-alert.err{background:var(--red-l);border:1px solid rgba(220,38,38,.2);color:var(--red)}
  .empty{text-align:center;padding:3rem 1rem;color:#bbb}
  .empty-ico{font-size:2.2rem;opacity:.3;display:block;margin-bottom:.6rem}
  .empty p{font-size:.82rem;line-height:1.7}
  .toast-wrap{position:fixed;bottom:1.4rem;right:1.4rem;z-index:500;display:flex;flex-direction:column;gap:.4rem;pointer-events:none}
  .toast{background:var(--n800);color:#eee;padding:.6rem .9rem;border-radius:var(--rs);font-size:.78rem;display:flex;align-items:center;gap:.4rem;animation:tin .22s var(--e) both;max-width:260px;box-shadow:var(--sh3);pointer-events:all}
  .toast.ok{border-left:3px solid var(--g400)}.toast.err{border-left:3px solid #f87171}.toast.info{border-left:3px solid var(--gold)}
  @keyframes tin{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}
  @keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
  @media(max-width:1100px){.lesson-page-layout{grid-template-columns:1fr 320px}}
  @media(max-width:900px){.lesson-page-layout{grid-template-columns:1fr}.notes-card{position:static}}
  @media(max-width:768px){
    .main{margin-left:0}.hamburger{display:flex}.topbar{padding:0 1.1rem}.content{padding:1.1rem .9rem}
  }
  @media(max-width:520px){.units-grid{grid-template-columns:1fr 1fr}}
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
      <span class="tb-title" id="topTitle">Matérias</span>
    </div>

    <!-- Timer — só aparece na tela de aula -->
    <div class="timer-wrap" id="timerWrap">
      <div class="clock-ring" title="Progresso da sessão">
        <svg class="clock-svg" viewBox="0 0 36 36">
          <circle class="clock-track" cx="18" cy="18" r="15"/>
          <circle class="clock-arc" id="clockArc" cx="18" cy="18" r="15"/>
        </svg>
        <div class="clock-time-inner" id="clockInner">0m</div>
      </div>
      <div class="timer-info">
        <div class="timer-elapsed" id="timerDisplay">00:00</div>
        <div class="timer-goal-label" id="timerGoalLabel">Meta: 25 min</div>
      </div>
      <div class="timer-toggle">
        <button class="timer-toggle-btn" id="timerBtn" onclick="toggleTimerMenu()">⏱ Timer ▾</button>
        <div class="timer-menu" id="timerMenu">
          <button class="tm-btn tm-start" id="tmStartBtn" onclick="timerStartPause()">▶ Iniciar</button>
          <button class="tm-btn tm-reset" onclick="timerReset()">↺ Resetar</button>
          <div class="tm-sep"></div>
          <div class="tm-goal-row">
            <span class="tm-goal-lbl">Meta</span>
            <select class="tm-goal-sel" id="goalSel" onchange="setGoal(this.value)">
              <option value="15">15 min</option>
              <option value="25" selected>25 min</option>
              <option value="30">30 min</option>
              <option value="45">45 min</option>
              <option value="60">1 hora</option>
              <option value="90">1h 30</option>
            </select>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="content">
    <div class="breadcrumb" id="breadcrumb"></div>
    <div class="sec-head">
      <div class="sec-title" id="secTitle">Matérias</div>
      <button class="btn-primary" id="addBtn" onclick="onAdd()" style="display:none">+ Adicionar</button>
    </div>
    <div id="mainContent"></div>
  </main>
</div>

<!-- Modal genérico -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title" id="modalTitle">Modal</span>
      <button class="modal-x" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body" id="modalBody"></div>
    <div class="modal-foot" id="modalFoot"></div>
  </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
const SUBJECTS   = <?= json_encode($subjects,   JSON_UNESCAPED_UNICODE) ?>;
const OBJ_ID     = <?= json_encode($activeObjId) ?>;
const UNIT_NAMES = <?= json_encode($unitNames,   JSON_UNESCAPED_UNICODE) ?>;
const UNIT_COUNT = <?= (int)$objUnitCount ?>;
</script>

<script>
'use strict';

// ── API ──────────────────────────────────────────────────────
async function api(ep, data) {
  try {
    const r = await fetch('/florescer/api/' + ep, {
      method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)
    });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.json();
  } catch(e) {
    console.error('API error:', ep, e);
    return {success: false, message: 'Erro de conexão: ' + e.message};
  }
}

function toast(msg, type='ok', ms=3200) {
  const w = document.getElementById('toastWrap');
  const d = document.createElement('div');
  d.className = `toast ${type}`;
  d.innerHTML = `<span>${type==='ok'?'✅':type==='err'?'❌':'💡'}</span><span>${esc(msg)}</span>`;
  w.appendChild(d);
  setTimeout(()=>{ d.style.opacity='0'; d.style.transition='.3s'; setTimeout(()=>d.remove(),300); }, ms);
}

function toggleSidebar(){
  const sb=document.getElementById('sidebar'),ov=document.getElementById('sbOverlay'),hb=document.getElementById('hamburger');
  if(!sb) return;
  const open=sb.classList.toggle('open');
  if(ov) ov.classList.toggle('show',open);
  if(hb) hb.classList.toggle('open',open);
  document.body.style.overflow=open?'hidden':'';
}

function esc(s){ return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }

// ── Estado ────────────────────────────────────────────────────
const Nav = {
  level: 'subjects',
  subjectId: null, subjectName: '', subjectColor: '#40916c',
  unitIdx: null, unitName: '',
  topicId: null, topicName: '',
  lessonId: null, lessonIdx: -1, lessonList: [],
};

const COLORS = [
  '#40916c','#2563eb','#dc2626','#d97706','#7c3aed',
  '#db2777','#0891b2','#059669','#ea580c','#4f46e5',
  '#be185d','#0284c7','#16a34a','#b45309','#6d28d9',
  '#c2410c','#0e7490','#15803d','#92400e','#5b21b6',
];
let pickerColor = '#40916c';

// ── Timer ─────────────────────────────────────────────────────
const T = { running:false, seconds:0, interval:null, lessonId:null, goalMin:25 };
const CIRC = 94.25;

function setGoal(v){ T.goalMin=parseInt(v); updateTimerUI(); }
function toggleTimerMenu(){ document.getElementById('timerMenu').classList.toggle('open'); }

function timerStartPause(){
  if(T.running){
    clearInterval(T.interval); T.interval=null; T.running=false;
    document.getElementById('tmStartBtn').innerHTML='▶ Retomar';
    document.getElementById('timerBtn').classList.remove('running');
    document.getElementById('timerDisplay').classList.remove('running');
  } else {
    T.running=true;
    T.interval=setInterval(()=>{ T.seconds++; updateTimerUI(); if(T.seconds>0&&T.seconds%300===0) recordTimer(5); },1000);
    document.getElementById('tmStartBtn').innerHTML='⏸ Pausar';
    document.getElementById('timerBtn').classList.add('running');
    document.getElementById('timerDisplay').classList.add('running');
  }
  document.getElementById('timerMenu').classList.remove('open');
}

function timerReset(){
  const min=Math.floor((T.seconds%300)/60); if(min>0) recordTimer(min);
  clearInterval(T.interval); T.interval=null; T.running=false; T.seconds=0;
  document.getElementById('tmStartBtn').innerHTML='▶ Iniciar';
  document.getElementById('timerBtn').classList.remove('running');
  document.getElementById('timerDisplay').classList.remove('running');
  document.getElementById('timerMenu').classList.remove('open');
  updateTimerUI();
}

// Para o timer e esconde — chamado ao sair de aula
function timerStop(){
  if(T.running){
    const min=Math.floor(T.seconds/60);
    if(min>0&&T.lessonId) recordTimer(min);
  }
  clearInterval(T.interval); T.interval=null; T.running=false; T.seconds=0; T.lessonId=null;
  const tw = document.getElementById('timerWrap');
  if(tw) tw.classList.remove('show');
  updateTimerUI();
}

// Inicia automaticamente ao entrar na aula
function timerActivate(lessonId){
  // Se já está rodando para outra aula, salva o tempo e reseta
  if(T.lessonId && T.lessonId !== lessonId && T.running){
    const min=Math.floor(T.seconds/60);
    if(min>0) recordTimer(min);
    clearInterval(T.interval); T.interval=null; T.running=false; T.seconds=0;
  }
  T.lessonId = lessonId;
  document.getElementById('timerWrap').classList.add('show');
  // Inicia automaticamente se não estava rodando
  if(!T.running) timerStartPause();
}

function updateTimerUI(){
  const m=Math.floor(T.seconds/60), s=T.seconds%60;
  const disp=document.getElementById('timerDisplay');
  if(disp) disp.textContent=String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
  const goalSec=T.goalMin*60, pct=Math.min(T.seconds/goalSec,1);
  const arc=document.getElementById('clockArc');
  if(arc){ arc.style.strokeDashoffset=CIRC-(pct*CIRC); arc.classList.toggle('danger',pct>=1); }
  const inner=document.getElementById('clockInner');
  if(inner) inner.textContent=m<60?m+'m':Math.floor(m/60)+'h';
  const remaining=Math.max(0,goalSec-T.seconds), rm=Math.floor(remaining/60), rs2=remaining%60;
  const lbl=document.getElementById('timerGoalLabel');
  if(lbl){
    if(T.seconds===0) lbl.textContent=`Meta: ${T.goalMin} min`;
    else if(remaining>0) lbl.textContent=`Restam ${String(rm).padStart(2,'0')}:${String(rs2).padStart(2,'0')}`;
    else lbl.textContent='✓ Meta atingida!';
  }
}

async function recordTimer(minutes){
  if(minutes<=0||!T.lessonId) return;
  await api('timer.php',{action:'record',duration_min:minutes,lesson_id:T.lessonId,subject_id:Nav.subjectId});
}

// ── Init ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded',()=>{
  renderSubjects(SUBJECTS);
  document.addEventListener('click',e=>{
    if(!e.target.closest('#timerBtn')&&!e.target.closest('#timerMenu'))
      document.getElementById('timerMenu').classList.remove('open');
  });
  document.getElementById('modalOverlay').addEventListener('click',e=>{
    if(e.target===document.getElementById('modalOverlay')) closeModal();
  });
});

window.addEventListener('keydown',e=>{ if(e.key==='Escape') closeModal(); });
window.addEventListener('beforeunload',()=>{
  if(T.running && T.lessonId){
    const min=Math.floor(T.seconds/60);
    if(min>0) navigator.sendBeacon('/florescer/api/timer.php',
      new Blob([JSON.stringify({action:'record',duration_min:min,lesson_id:T.lessonId,subject_id:Nav.subjectId})],
      {type:'application/json'}));
  }
});

// ══════════════════════════════════════════════════
// NÍVEL 1: MATÉRIAS
// ══════════════════════════════════════════════════
function renderSubjects(list){
  Nav.level='subjects';
  timerStop(); // Para o timer ao voltar para matérias
  setUI('Matérias','Matérias');
  document.getElementById('breadcrumb').innerHTML='';
  document.getElementById('addBtn').style.display = OBJ_ID ? 'flex' : 'none';
  document.getElementById('addBtn').textContent='+ Matéria';
  if(!OBJ_ID){
    document.getElementById('mainContent').innerHTML=`<div class="empty"><span class="empty-ico">🎯</span><p>Selecione um objetivo na sidebar.<br><a href="/florescer/public/views/objectives.php" style="color:var(--g500)">Gerenciar objetivos</a></p></div>`;
    return;
  }
  if(!list.length){
    document.getElementById('mainContent').innerHTML=`<div class="empty"><span class="empty-ico">📚</span><p>Nenhuma matéria ainda.<br>Crie a primeira usando o botão acima.</p></div>`;
    return;
  }
  document.getElementById('mainContent').innerHTML=`<div class="subj-list">
    ${list.map(s=>{
      const tc=parseInt(s.topic_count)||0,done=parseInt(s.done)||0,tot=parseInt(s.total)||0;
      const pct=tot>0?Math.round(done/tot*100):0,c=s.color||'#40916c',cbg=c+'15';
      return `<div class="subj-card" onclick="openSubject(${s.id},'${esc(s.name)}','${esc(c)}')">
        <div class="subj-card-stripe" style="background:${c}"></div>
        <div class="subj-card-body">
          <div class="subj-ico-wrap" style="background:${cbg}">${subjIcon(s.name)}</div>
          <div class="subj-card-info">
            <div class="subj-card-name">${esc(s.name)}</div>
            <div class="subj-card-meta"><span>📑 ${tc} assunto${tc!==1?'s':''}</span><span>📋 ${done}/${tot} aulas</span></div>
            <div class="subj-card-prog">
              <div class="subj-prog-bar"><div class="subj-prog-fill" style="width:${pct}%;background:${c}"></div></div>
              <span class="subj-prog-pct" style="color:${c}">${pct}%</span>
            </div>
          </div>
          <div class="subj-card-actions" onclick="event.stopPropagation()">
            <button class="btn-ghost" onclick="editSubject(${s.id},'${esc(s.name)}','${esc(c)}')">✏️</button>
            <button class="btn-danger" onclick="confirmDeleteSubject(${s.id},'${esc(s.name)}')">🗑</button>
          </div>
        </div>
      </div>`;
    }).join('')}
  </div>`;
}

// ══════════════════════════════════════════════════
// NÍVEL 2: UNIDADES
// ══════════════════════════════════════════════════
async function openSubject(id, name, color){
  Nav.level='units'; Nav.subjectId=id; Nav.subjectName=name; Nav.subjectColor=color;
  timerStop();
  setUI(name, name);
  setBc([{label:'Matérias', fn:'reloadSubjects()'}, {label:name}]);
  document.getElementById('addBtn').style.display='none';
  document.getElementById('mainContent').innerHTML=spinner();

  const r = await api('topics.php', {action:'list', subject_id:id});
  const rawTopics = r.data || [];
  const topics = rawTopics.map(t => ({
    id:parseInt(t.id)||0, name:t.name||'',
    unit_index:parseInt(t.unit_index??0),
    total_lessons:parseInt(t.total_lessons)||0,
    completed_lessons:parseInt(t.completed_lessons)||0,
  }));

  const units = UNIT_NAMES.map((uname, i) => {
    const ut = topics.filter(t => t.unit_index === i);
    return { name:uname, idx:i, topics:ut,
      total:ut.reduce((a,t)=>a+t.total_lessons,0),
      done:ut.reduce((a,t)=>a+t.completed_lessons,0) };
  });

  const c=color, cbg=c+'15', icon=subjIcon(name);
  const totalTopics=topics.length, totalLessons=units.reduce((a,u)=>a+u.total,0);

  document.getElementById('mainContent').innerHTML=`
    <div class="units-view">
      <div class="subject-header-card">
        <div class="shc-stripe" style="background:${c}"></div>
        <div class="shc-body">
          <div class="shc-ico" style="background:${cbg}">${icon}</div>
          <div class="shc-info">
            <div class="shc-name">${esc(name)}</div>
            <div class="shc-meta">${totalTopics} assunto${totalTopics!==1?'s':''} · ${totalLessons} aula${totalLessons!==1?'s':''} · ${UNIT_COUNT} unidade${UNIT_COUNT!==1?'s':''}</div>
          </div>
        </div>
      </div>
      <div class="units-grid">
        ${units.map(u=>{
          const pct=u.total>0?Math.round(u.done/u.total*100):0;
          return `<div class="unit-card" onclick="openUnit(${u.idx},'${esc(u.name)}')">
            <div style="position:absolute;top:0;left:0;right:0;height:3px;background:${c};opacity:.65;border-radius:var(--r) var(--r) 0 0"></div>
            <div class="unit-number" style="color:${c}">${esc(u.name)}</div>
            <div class="unit-meta">${u.topics.length} assunto${u.topics.length!==1?'s':''} · ${u.total} aula${u.total!==1?'s':''}</div>
            <div class="unit-pbar"><div class="unit-pfill" style="width:${pct}%;background:${c}"></div></div>
            <div style="margin-top:.5rem;font-size:.7rem;color:${c};font-weight:600">${pct}% concluído</div>
          </div>`;
        }).join('')}
      </div>
    </div>`;
}

// ══════════════════════════════════════════════════
// NÍVEL 3: ASSUNTOS
// ══════════════════════════════════════════════════
async function openUnit(unitIdx, unitName){
  Nav.level='topics'; Nav.unitIdx=unitIdx; Nav.unitName=unitName;
  timerStop();
  setUI(unitName, unitName);
  setBc([
    {label:'Matérias', fn:'reloadSubjects()'},
    {label:Nav.subjectName, fn:`openSubject(${Nav.subjectId},'${esc(Nav.subjectName)}','${esc(Nav.subjectColor)}')`},
    {label:unitName}
  ]);
  document.getElementById('addBtn').style.display='flex';
  document.getElementById('addBtn').textContent='+ Assunto';
  document.getElementById('mainContent').innerHTML=spinner();

  const r = await api('topics.php', {action:'list', subject_id:Nav.subjectId});
  const allTopics=(r.data||[]).map(t=>({
    id:parseInt(t.id)||0, name:t.name||'',
    unit_index:parseInt(t.unit_index??0),
    total_lessons:parseInt(t.total_lessons)||0,
    completed_lessons:parseInt(t.completed_lessons)||0,
  }));
  const topics = allTopics.filter(t => t.unit_index === unitIdx);
  const c = Nav.subjectColor;

  document.getElementById('mainContent').innerHTML=`
    <div class="topics-view">
      <div class="unit-header-card">
        <div class="uhc-stripe" style="background:${c}"></div>
        <div class="uhc-body">
          <div class="uhc-info">
            <div class="uhc-label">${esc(Nav.subjectName)}</div>
            <div class="uhc-name">${esc(unitName)}</div>
            <div class="uhc-meta">${topics.length} assunto${topics.length!==1?'s':''}</div>
          </div>
        </div>
      </div>
      ${topics.length===0
        ? `<div class="empty"><span class="empty-ico">📑</span><p>Nenhum assunto nesta unidade ainda.<br>Use o botão acima para adicionar.</p></div>`
        : `<div class="topics-list">${topics.map(t=>{
            const pct=t.total_lessons>0?Math.round(t.completed_lessons/t.total_lessons*100):0;
            return `<div class="topic-row" onclick="openTopic(${t.id},'${esc(t.name)}')">
              <div class="topic-row-icon">📑</div>
              <div class="topic-row-info">
                <div class="topic-row-name">${esc(t.name)}</div>
                <div class="topic-row-meta">${t.total_lessons} aula${t.total_lessons!==1?'s':''} · ${t.completed_lessons} concluída${t.completed_lessons!==1?'s':''}</div>
              </div>
              <div class="topic-row-prog">
                <div class="topic-prog-bar"><div class="topic-prog-fill" style="width:${pct}%;background:${c}"></div></div>
                <span class="topic-prog-pct" style="color:${c}">${pct}%</span>
              </div>
              <div class="topic-row-actions" onclick="event.stopPropagation()">
                <button class="btn-danger" onclick="confirmDeleteTopic(${t.id},'${esc(t.name)}')">🗑</button>
              </div>
            </div>`;}).join('')}</div>`
      }
    </div>`;
}

// ══════════════════════════════════════════════════
// NÍVEL 4: AULAS
// ══════════════════════════════════════════════════
async function openTopic(id, name){
  Nav.level='lessons'; Nav.topicId=id; Nav.topicName=name;
  timerStop();
  setUI(name, name);
  setBc([
    {label:'Matérias', fn:'reloadSubjects()'},
    {label:Nav.subjectName, fn:`openSubject(${Nav.subjectId},'${esc(Nav.subjectName)}','${esc(Nav.subjectColor)}')`},
    {label:Nav.unitName, fn:`openUnit(${Nav.unitIdx},'${esc(Nav.unitName)}')`},
    {label:name}
  ]);
  document.getElementById('addBtn').style.display='flex';
  document.getElementById('addBtn').textContent='+ Aula';
  document.getElementById('mainContent').innerHTML=spinner();

  const r = await api('lessons.php', {action:'list', topic_id:id});
  Nav.lessonList = (r.data||[]).map(l=>({
    id:parseInt(l.id)||0, title:l.title||'',
    youtube_id:l.youtube_id||null, is_completed:!!l.is_completed,
  }));

  renderLessonsLayout();
}

function renderLessonsLayout(){
  const c = Nav.subjectColor;
  if(!Nav.lessonList.length){
    document.getElementById('mainContent').innerHTML=`<div class="empty"><span class="empty-ico">🎥</span><p>Nenhuma aula ainda.<br>Clique em "+ Aula" para adicionar.</p></div>`;
    return;
  }
  // Abre a primeira aula não concluída, ou a primeira da lista
  const firstIdx = Nav.lessonList.findIndex(l=>!l.is_completed);
  const startIdx = firstIdx>=0 ? firstIdx : 0;

  document.getElementById('mainContent').innerHTML=`
    <div class="lesson-page-layout" id="lessonPageLayout">
      <div class="lesson-left" id="lessonLeft">
        <div style="padding:3.5rem 1.5rem;text-align:center;color:#ccc;background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r)">
          <span style="font-size:2.2rem;display:block;margin-bottom:.6rem;opacity:.3">⏳</span>
          <p style="font-size:.8rem;line-height:1.7">Carregando aula…</p>
        </div>
      </div>
      <div class="notes-card" id="notesCard">
        <div class="notes-head">
          <span class="notes-head-title">✍️ Anotações</span>
          <span class="notes-ind" id="notesInd">—</span>
        </div>
        <textarea class="notes-area" id="notesArea"
                  placeholder="Selecione uma aula para começar as anotações…" disabled></textarea>
        <div class="notes-foot">
          <button class="btn-ghost" onclick="copyNotes()" style="font-size:.7rem">📋 Copiar</button>
          <button class="btn-ghost" onclick="clearNotes()" style="font-size:.7rem">🗑 Limpar</button>
          <span class="notes-chars" id="notesChars">0 caracteres</span>
        </div>
      </div>
    </div>

    <div style="max-width:1400px;margin-top:1rem">
      <div style="background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r);overflow:hidden;box-shadow:var(--sh0)">
        <div style="padding:.75rem 1.1rem;border-bottom:1px solid rgba(0,0,0,.05);display:flex;align-items:center;justify-content:space-between">
          <span style="font-size:.84rem;font-weight:600;color:var(--n800)">📋 Aulas — ${esc(Nav.topicName)}</span>
          <span style="font-size:.72rem;color:#bbb" id="lessonsProgressLabel">
            ${Nav.lessonList.filter(l=>l.is_completed).length}/${Nav.lessonList.length} concluídas
          </span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.5rem;padding:.75rem">
          ${Nav.lessonList.map((l,i)=>`
            <div class="lesson-grid-item" id="lgi-${l.id}"
                 onclick="openLesson(${l.id},${i})"
                 style="display:flex;align-items:center;gap:.6rem;padding:.55rem .7rem;border-radius:8px;border:1px solid rgba(0,0,0,.07);cursor:pointer;transition:all var(--d,0.2s) var(--e,ease);background:${l.is_completed?'var(--g50)':'var(--white)'}">
              <div style="width:20px;height:20px;border-radius:50%;border:2px solid ${l.is_completed?c:'rgba(0,0,0,.12)'};display:flex;align-items:center;justify-content:center;font-size:.62rem;color:${l.is_completed?'#fff':'#ccc'};background:${l.is_completed?c:'transparent'};flex-shrink:0">
                ${l.is_completed?'✓':''}
              </div>
              <div style="flex:1;min-width:0">
                <div style="font-size:.78rem;font-weight:500;color:var(--n800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(l.title)}</div>
                <div style="font-size:.64rem;color:#bbb">${l.youtube_id?'📺':'📝'} #${i+1}</div>
              </div>
            </div>`).join('')}
        </div>
      </div>
    </div>`;

  // Abre automaticamente a primeira aula e inicia o timer
  openLesson(Nav.lessonList[startIdx].id, startIdx);
}

async function openLesson(lessonId, idx){
  Nav.lessonId = lessonId; Nav.lessonIdx = idx;
  const l = Nav.lessonList[idx];
  if(!l) return;

  // Destaca item ativo na grade
  document.querySelectorAll('.lesson-grid-item').forEach(el=>{
    const lid = parseInt(el.id.replace('lgi-',''));
    const lObj = Nav.lessonList.find(x=>x.id===lid);
    el.style.borderColor='rgba(0,0,0,.07)';
    el.style.background=lObj?.is_completed?'var(--g50)':'var(--white)';
  });
  const activeItem=document.getElementById(`lgi-${lessonId}`);
  if(activeItem){ activeItem.style.borderColor=Nav.subjectColor; activeItem.style.background=Nav.subjectColor+'12'; }

  // ── Timer: inicia automaticamente ao entrar na aula ──
  timerActivate(lessonId);

  const total=Nav.lessonList.length;

  const left=document.getElementById('lessonLeft');
  left.innerHTML=`
    <div class="video-card">
      <div class="video-wrap">
        ${l.youtube_id
          ? `<iframe src="https://www.youtube.com/embed/${esc(l.youtube_id)}?rel=0&modestbranding=1"
               allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture"
               allowfullscreen loading="lazy"></iframe>`
          : `<div class="video-placeholder"><span>📝</span><span>Aula sem vídeo</span></div>`}
      </div>
      <div class="lesson-info-bar">
        <div class="lesson-info-title">${esc(l.title)}</div>
        <button class="btn-complete ${l.is_completed?'done':'pending'}" id="completeBtn"
                onclick="toggleComplete(${lessonId},${idx})">
          ${l.is_completed?'✓ Concluída':'✓ Marcar concluída'}
        </button>
      </div>
    </div>
    <div class="lesson-nav-bar">
      <button class="lesson-nav-btn" id="btnPrev"
              onclick="navLesson(${idx-1})" ${idx>0?'':'disabled'}>← Anterior</button>
      <span class="lesson-nav-info">Aula ${idx+1} de ${total}</span>
      <button class="lesson-nav-btn" id="btnNext"
              onclick="navLesson(${idx+1})" ${idx<total-1?'':'disabled'}>Próxima →</button>
    </div>`;

  const ta=document.getElementById('notesArea');
  if(ta) ta.disabled=false;
  loadNotes(lessonId);
}

// ── Navegação entre aulas ─────────────────────────────────────
function navLesson(idx){
  if(idx<0||idx>=Nav.lessonList.length) return;
  openLesson(Nav.lessonList[idx].id, idx);
}

// ── Anotações ─────────────────────────────────────────────────
let _notesDeb=null;
async function loadNotes(lessonId){
  const ta=document.getElementById('notesArea'), ind=document.getElementById('notesInd');
  if(!ta) return;
  if(ind){ind.textContent='● carregando…';ind.className='notes-ind saving';}
  const r=await api('notes.php',{action:'get',lesson_id:lessonId});
  ta.value=r.success&&r.data?.content?r.data.content:'';
  updateCharsCount(ta.value);
  if(ind){ind.textContent=r.success?'● salvo':'● novo';ind.className='notes-ind saved';}
  ta.oninput=()=>{
    updateCharsCount(ta.value);
    if(ind){ind.textContent='● salvando…';ind.className='notes-ind saving';}
    clearTimeout(_notesDeb);
    _notesDeb=setTimeout(async()=>{
      const r2=await api('notes.php',{action:'save',lesson_id:lessonId,content:ta.value});
      if(ind){ind.textContent=r2.success?'● salvo':'● erro';ind.className=r2.success?'notes-ind saved':'notes-ind';}
    },1400);
  };
}
function updateCharsCount(v){
  const el=document.getElementById('notesChars');
  if(el) el.textContent=`${v.length} caractere${v.length!==1?'s':''}`;
}
function copyNotes(){
  const ta=document.getElementById('notesArea');
  if(!ta?.value.trim()){toast('Nenhuma anotação para copiar.','info');return;}
  navigator.clipboard.writeText(ta.value).then(()=>toast('Anotações copiadas! 📋'));
}
function clearNotes(){
  const ta=document.getElementById('notesArea');
  if(!ta||!ta.value.trim()) return;
  if(!confirm('Limpar todas as anotações desta aula?')) return;
  ta.value=''; ta.dispatchEvent(new Event('input'));
}

async function toggleComplete(lessonId, idx){
  const l=Nav.lessonList[idx]; if(!l) return;
  const r=await api('lessons.php',{action:'complete',lesson_id:lessonId,completed:!l.is_completed});
  if(!r.success){toast(r.message||'Erro.','err');return;}
  l.is_completed=!l.is_completed;

  const btn=document.getElementById('completeBtn');
  if(btn){btn.className=`btn-complete ${l.is_completed?'done':'pending'}`;btn.textContent=l.is_completed?'✓ Concluída':'✓ Marcar concluída';}

  const item=document.getElementById(`lgi-${lessonId}`);
  if(item){
    const c=Nav.subjectColor, circle=item.querySelector('div');
    if(circle){circle.style.background=l.is_completed?c:'transparent';circle.style.borderColor=l.is_completed?c:'rgba(0,0,0,.12)';circle.style.color=l.is_completed?'#fff':'#ccc';circle.textContent=l.is_completed?'✓':'';}
    item.style.background=l.is_completed?(c+'12'):'var(--white)';
  }
  const prog=document.getElementById('lessonsProgressLabel');
  if(prog){const done=Nav.lessonList.filter(ll=>ll.is_completed).length;prog.textContent=`${done}/${Nav.lessonList.length} concluídas`;}
  toast(l.is_completed?`✅ Concluída! +${r.data?.xp_earned||20} XP`:'Desmarcada.');
}

// ── Adicionar ─────────────────────────────────────────────────
function onAdd(){
  if(Nav.level==='subjects')      modalAddSubject();
  else if(Nav.level==='topics')   modalAddTopicInUnit(Nav.unitIdx,Nav.unitName);
  else if(Nav.level==='lessons')  modalAddLesson();
}

function modalAddSubject(){
  pickerColor='#40916c';
  openModal('Nova Matéria',
    `<div class="f-alert" id="ma"></div>
    <div class="fg"><label class="fl">Nome</label>
      <input class="fc" id="mName" placeholder="Ex: Matemática" maxlength="80"/></div>
    <div class="fg"><label class="fl">Cor</label>
      <div class="swatches">${COLORS.map(c=>`<div class="sw${c===pickerColor?' sel':''}" style="background:${c}" onclick="pickColor('${c}',this)"></div>`).join('')}</div>
      <div class="rgb-wrap">
        <div class="rgb-lbl">Personalizada (RGB)</div>
        <div class="rgb-row"><div class="rgb-dot" style="background:#dc2626"></div>
          <input type="range" id="slR" min="0" max="255" value="64" oninput="updateRGB()"/>
          <span class="rgb-val" id="vR">64</span></div>
        <div class="rgb-row"><div class="rgb-dot" style="background:#16a34a"></div>
          <input type="range" id="slG" min="0" max="255" value="145" oninput="updateRGB()"/>
          <span class="rgb-val" id="vG">145</span></div>
        <div class="rgb-row"><div class="rgb-dot" style="background:#2563eb"></div>
          <input type="range" id="slB" min="0" max="255" value="108" oninput="updateRGB()"/>
          <span class="rgb-val" id="vB">108</span></div>
        <div class="color-preview" id="colorPreview" style="background:${pickerColor}"></div>
      </div>
    </div>
    <div class="fg"><label class="fl">Média individual (opcional)</label>
      <input class="fc" id="mAvg" type="number" min="0" max="10" step="0.1" placeholder="Usa padrão do objetivo"/></div>`,
    `<button class="btn-ghost" onclick="closeModal()">Cancelar</button>
     <button class="btn-primary" onclick="submitSubject()">Adicionar</button>`,
    ()=>document.getElementById('mName').focus()
  );
}

function pickColor(c,el){
  pickerColor=c;
  document.querySelectorAll('.sw').forEach(s=>s.classList.remove('sel'));
  el.classList.add('sel');
  const rgb=hexToRgb(c);
  if(rgb){
    document.getElementById('slR').value=rgb.r;document.getElementById('vR').textContent=rgb.r;
    document.getElementById('slG').value=rgb.g;document.getElementById('vG').textContent=rgb.g;
    document.getElementById('slB').value=rgb.b;document.getElementById('vB').textContent=rgb.b;
    document.getElementById('colorPreview').style.background=c;
  }
}
function updateRGB(){
  const r=+document.getElementById('slR').value,g=+document.getElementById('slG').value,b=+document.getElementById('slB').value;
  document.getElementById('vR').textContent=r;document.getElementById('vG').textContent=g;document.getElementById('vB').textContent=b;
  pickerColor=rgbToHex(r,g,b);
  document.getElementById('colorPreview').style.background=pickerColor;
  document.querySelectorAll('.sw').forEach(s=>s.classList.remove('sel'));
}
function hexToRgb(h){const m=/^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(h);return m?{r:parseInt(m[1],16),g:parseInt(m[2],16),b:parseInt(m[3],16)}:null;}
function rgbToHex(r,g,b){return'#'+[r,g,b].map(v=>Math.min(255,Math.max(0,v)).toString(16).padStart(2,'0')).join('');}

async function submitSubject(){
  const name=document.getElementById('mName').value.trim();
  const avg=document.getElementById('mAvg').value;
  if(!name){setAlert('Informe o nome.');return;}
  const r=await api('subjects.php',{action:'create',objective_id:OBJ_ID,name,color:pickerColor,individual_avg:avg?+avg:null});
  if(r.success){toast('Matéria adicionada! 📚');closeModal();reloadSubjects();}
  else setAlert(r.message||'Erro.');
}

function editSubject(id,name,color){
  pickerColor=color||'#40916c';
  openModal('Editar Matéria',
    `<div class="f-alert" id="ma"></div>
    <div class="fg"><label class="fl">Nome</label><input class="fc" id="mName" value="${esc(name)}" maxlength="80"/></div>
    <div class="fg"><label class="fl">Cor</label>
      <div class="swatches">${COLORS.map(c=>`<div class="sw${c===color?' sel':''}" style="background:${c}" onclick="pickColor('${c}',this)"></div>`).join('')}</div>
      <div class="color-preview" id="colorPreview" style="background:${pickerColor};margin-top:.5rem;border-radius:4px;height:20px;border:1px solid rgba(0,0,0,.08)"></div>
    </div>`,
    `<button class="btn-danger" onclick="confirmDeleteSubject(${id},'${esc(name)}')">🗑 Excluir</button>
     <button class="btn-ghost" onclick="closeModal()">Cancelar</button>
     <button class="btn-primary" onclick="submitEditSubject(${id})">Salvar</button>`,
    ()=>document.getElementById('mName').focus()
  );
}
async function submitEditSubject(id){
  const name=document.getElementById('mName').value.trim();
  if(!name){setAlert('Informe o nome.');return;}
  const r=await api('subjects.php',{action:'update',subject_id:id,id,name,color:pickerColor});
  if(r.success){toast('Atualizado!');closeModal();reloadSubjects();}
  else setAlert(r.message||'Erro.');
}
function confirmDeleteSubject(id,name){
  if(!confirm(`⚠️ Excluir "${name}" e todos os assuntos e aulas?\n\nEsta ação é irreversível.`)) return;
  api('subjects.php',{action:'delete',subject_id:id,id}).then(r=>{
    if(r.success){toast('Matéria excluída.');closeModal();reloadSubjects();}
    else toast(r.message||'Erro ao excluir.','err');
  });
}

function modalAddTopicInUnit(unitIdx,unitName){
  Nav.unitIdx=unitIdx; Nav.unitName=unitName;
  openModal(`Novo Assunto — ${unitName}`,
    `<div class="f-alert" id="ma"></div>
    <div class="fg"><label class="fl">Nome do assunto</label>
      <input class="fc" id="mName" placeholder="Ex: Funções Quadráticas" maxlength="120"/></div>`,
    `<button class="btn-ghost" onclick="closeModal()">Cancelar</button>
     <button class="btn-primary" onclick="submitTopicInUnit(${unitIdx})">Criar</button>`,
    ()=>document.getElementById('mName').focus()
  );
}
async function submitTopicInUnit(unitIdx){
  const name=document.getElementById('mName').value.trim();
  if(!name){setAlert('Informe o nome.');return;}
  const r=await api('topics.php',{action:'create',subject_id:Nav.subjectId,name,unit_index:unitIdx});
  if(r.success){toast('Assunto criado em '+UNIT_NAMES[unitIdx]+'! ✅');closeModal();openUnit(unitIdx,UNIT_NAMES[unitIdx]);}
  else setAlert(r.message||'Erro.');
}
function confirmDeleteTopic(id,name){
  if(!confirm(`⚠️ Excluir "${name}" e todas as aulas?\n\nEsta ação é irreversível.`)) return;
  api('topics.php',{action:'delete',topic_id:id,id}).then(r=>{
    if(r.success){toast('Assunto excluído.');openUnit(Nav.unitIdx,Nav.unitName);}
    else toast(r.message||'Erro ao excluir.','err');
  });
}

function modalAddLesson(){
  openModal('Nova Aula',
    `<div class="f-alert" id="ma"></div>
    <div class="fg"><label class="fl">Título da aula</label>
      <input class="fc" id="mTitle" placeholder="Ex: Introdução às Funções" maxlength="200"/></div>
    <div class="fg"><label class="fl">Link YouTube (opcional)</label>
      <input class="fc" id="mUrl" placeholder="https://youtube.com/watch?v=..."/>
      <small style="font-size:.7rem;color:#ccc;margin-top:.2rem;display:block">youtube.com ou youtu.be</small></div>`,
    `<button class="btn-ghost" onclick="closeModal()">Cancelar</button>
     <button class="btn-primary" onclick="submitLesson()">Adicionar</button>`,
    ()=>document.getElementById('mTitle').focus()
  );
}
async function submitLesson(){
  const title=document.getElementById('mTitle').value.trim();
  const url=document.getElementById('mUrl').value.trim();
  if(!title){setAlert('Informe o título.');return;}
  if(url&&!/^https?:\/\/(www\.)?(youtube\.com|youtu\.be)\/.+/.test(url)){setAlert('URL do YouTube inválida.');return;}
  const r=await api('lessons.php',{action:'create',topic_id:Nav.topicId,title,youtube_url:url||null});
  if(r.success){toast('Aula adicionada! 🎥');closeModal();openTopic(Nav.topicId,Nav.topicName);}
  else setAlert(r.message||'Erro.');
}

// ── Helpers ───────────────────────────────────────────────────
function setUI(sec,top){
  document.getElementById('secTitle').textContent=sec;
  document.getElementById('topTitle').textContent=top;
}
function setBc(items){
  document.getElementById('breadcrumb').innerHTML=items.map((it,i)=>{
    if(i===items.length-1) return`<span class="bc-cur">${esc(it.label)}</span>`;
    return`<button class="bc-btn" onclick="${it.fn}">${esc(it.label)}</button><span class="bc-sep">›</span>`;
  }).join('');
}
async function reloadSubjects(){
  timerStop();
  if(!OBJ_ID){renderSubjects([]);return;}
  const r=await api('subjects.php',{action:'list',objective_id:OBJ_ID});
  const raw=r.data||r.subjects||r||[];
  const list=(Array.isArray(raw)?raw:[]).map(s=>({
    id:parseInt(s.id)||0, name:s.name||'', color:s.color||'#40916c',
    topic_count:parseInt(s.topic_count)||0, done:parseInt(s.done)||0, total:parseInt(s.total)||0,
  }));
  renderSubjects(list);
}
function subjIcon(name){
  const n=name.toLowerCase();
  if(/mat|álgeb|geom/.test(n))return'📐';if(/port|liter|gramát|redaç/.test(n))return'📝';
  if(/hist/.test(n))return'🏛️';if(/geo/.test(n))return'🌍';if(/bio/.test(n))return'🧬';
  if(/fís/.test(n))return'⚡';if(/quím/.test(n))return'🧪';if(/ingl|espan/.test(n))return'🌐';
  if(/art/.test(n))return'🎨';return'📚';
}
function spinner(){return'<div style="text-align:center;padding:2.5rem 1rem;color:#ccc;font-size:.8rem;display:flex;align-items:center;justify-content:center;gap:.5rem"><span style="animation:spin 1s linear infinite;display:inline-block">⏳</span> Carregando…</div>';}
function openModal(title,body,foot,onOpen){
  document.getElementById('modalTitle').textContent=title;
  document.getElementById('modalBody').innerHTML=body;
  document.getElementById('modalFoot').innerHTML=foot;
  document.getElementById('modalOverlay').classList.add('open');
  document.body.style.overflow='hidden';
  if(onOpen) setTimeout(onOpen,150);
}
function closeModal(){document.getElementById('modalOverlay').classList.remove('open');document.body.style.overflow='';}
function setAlert(msg){const el=document.getElementById('ma');if(el){el.textContent=msg;el.className='f-alert err show';}}
</script>
</body>
</html>