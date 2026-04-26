<?php
// ============================================================
// /admin/views/simulados.php — florescer Admin v3.0
// ============================================================
require_once __DIR__ . '/../includes/auth_admin.php';
require_once dirname(dirname(__DIR__)) . '/config/db.php';

requireAdmin();
date_default_timezone_set('America/Recife');

$adminName   = $_SESSION['admin_name'] ?? 'Admin';
$adminLetter = strtoupper(mb_substr($adminName, 0, 1, 'UTF-8'));

$tablesOk  = (bool)dbRow("SHOW TABLES LIKE 'sim_vestibulares'");
$questOk   = $tablesOk && (bool)dbRow("SHOW TABLES LIKE 'sim_questions'");
$redacaoOk = $tablesOk && (bool)dbRow("SHOW TABLES LIKE 'sim_redacoes'");

if ($tablesOk && $questOk) {
    $tab        = $_GET['tab']  ?? 'vestibulares';
    $vestFilter = (int)($_GET['vest'] ?? 0);
    $diffFilter = trim($_GET['diff'] ?? '');
    $search     = mb_substr(trim($_GET['q'] ?? ''), 0, 100, 'UTF-8');
    $page       = max(1, (int)($_GET['page'] ?? 1));
    $perPage    = 20;
    $offset     = ($page - 1) * $perPage;

    $qCols     = array_column(dbQuery("SHOW COLUMNS FROM sim_questions"), 'Field');
    $hasDiff   = in_array('difficulty', $qCols);
    $hasOrigin = in_array('origin',     $qCols);

    $vestibulares = dbQuery(
        "SELECT v.id, v.name, v.description, v.is_active,
                COALESCE(v.sort_order,0) AS sort_order,
                COALESCE(v.category,'vestibular') AS category,
                COALESCE(v.badge,'') AS badge,
                COALESCE(v.grade_level,'') AS grade_level,
                COALESCE(v.time_min,0) AS time_min,
                COALESCE(v.time_max,0) AS time_max,
                COUNT(q.id) AS total_q,
                SUM(CASE WHEN q.is_active=1 THEN 1 ELSE 0 END) AS active_q
         FROM sim_vestibulares v
         LEFT JOIN sim_questions q ON q.vestibular_id=v.id
         GROUP BY v.id, v.name, v.description, v.is_active, v.sort_order, v.category, v.badge, v.grade_level, v.time_min, v.time_max
         ORDER BY COALESCE(v.sort_order,0) ASC, v.id ASC"
    );

    $totalVest     = count($vestibulares);
    $totalQ        = (int)(dbRow('SELECT COUNT(*) AS n FROM sim_questions WHERE is_active=1')['n'] ?? 0);
    $totalRedacoes = $redacaoOk ? (int)(dbRow('SELECT COUNT(*) AS n FROM sim_redacoes WHERE is_active=1')['n'] ?? 0) : 0;
    $totalAttempts = 0; $avgPct = 0;
    try {
        $totalAttempts = (int)(dbRow('SELECT COUNT(*) AS n FROM sim_attempts WHERE finished_at IS NOT NULL')['n'] ?? 0);
        $r = dbRow('SELECT ROUND(AVG(score/total_questions*100),1) AS n FROM sim_attempts WHERE finished_at IS NOT NULL AND total_questions>0');
        $avgPct = $r['n'] ?? 0;
    } catch (\Throwable $e) {}

    $catGroups = [
        'vestibular' => ['label'=>'Vestibulares & ENEM','icon'=>'🎓','color'=>'var(--leaf)'],
        'materia'    => ['label'=>'Por Matéria',        'icon'=>'📚','color'=>'#60a5fa'],
        'escolar'    => ['label'=>'Escolar',             'icon'=>'🏫','color'=>'#a78bfa'],
        'redacao'    => ['label'=>'Redação',             'icon'=>'✍️','color'=>'var(--gold)'],
    ];

    if ($tab === 'questoes') {
        $where = 'WHERE 1=1'; $params = [];
        if ($vestFilter) { $where .= ' AND q.vestibular_id=?'; $params[] = $vestFilter; }
        if ($diffFilter && $hasDiff) { $where .= ' AND q.difficulty=?'; $params[] = $diffFilter; }
        if ($search !== '') { $where .= ' AND q.statement LIKE ?'; $params[] = "%{$search}%"; }
        $totalRows  = (int)(dbRow("SELECT COUNT(*) AS n FROM sim_questions q $where", $params)['n'] ?? 0);
        $totalPages = max(1, (int)ceil($totalRows / $perPage));
        $page = min($page, $totalPages);
        $params[] = $perPage; $params[] = $offset;
        $selectExtra = '';
        if ($hasDiff)   $selectExtra .= ', q.difficulty';
        if ($hasOrigin) $selectExtra .= ', q.origin';
        $questions = dbQuery(
            "SELECT q.id, q.vestibular_id, q.statement, q.correct_option, q.is_active,
                    q.subject_tag, q.year, v.name AS vest_name,
                    q.option_a, q.option_b, q.option_c, q.option_d, q.option_e, q.explanation $selectExtra
             FROM sim_questions q JOIN sim_vestibulares v ON v.id=q.vestibular_id
             $where ORDER BY q.vestibular_id ASC, q.id ASC LIMIT ? OFFSET ?",
            $params
        );
    }

    if ($tab === 'redacao' && $redacaoOk) {
        $redacoes = $vestFilter
            ? dbQuery('SELECT r.*, v.name AS vest_name FROM sim_redacoes r JOIN sim_vestibulares v ON v.id=r.vestibular_id WHERE r.vestibular_id=? ORDER BY r.sort_order ASC, r.id ASC', [$vestFilter])
            : dbQuery('SELECT r.*, v.name AS vest_name FROM sim_redacoes r JOIN sim_vestibulares v ON v.id=r.vestibular_id ORDER BY r.sort_order ASC, r.id ASC');
    }
}

$DIFFS = [
    'facil'   => ['Fácil',   '#52b788', 'rgba(82,183,136,.1)'],
    'medio'   => ['Médio',   '#c9a84c', 'rgba(201,168,76,.1)'],
    'dificil' => ['Difícil', '#e05252', 'rgba(224,82,82,.1)'],
];
$COMPS = [
    ['num'=>1,'label'=>'Norma culta',          'icon'=>'📖'],
    ['num'=>2,'label'=>'Compreensão do tema',   'icon'=>'🎯'],
    ['num'=>3,'label'=>'Argumentação',          'icon'=>'🧠'],
    ['num'=>4,'label'=>'Coesão textual',        'icon'=>'🔗'],
    ['num'=>5,'label'=>'Proposta de intervenção','icon'=>'💡'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>Simulados — florescer Admin</title>
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
  .tb-right{display:flex;align-items:center;gap:.5rem}
  .page{padding:1.25rem 1.5rem;display:flex;flex-direction:column;gap:1rem;flex:1}

  /* ── Botões topbar ───────────────────────────────────────── */
  .btn-top{padding:.38rem .85rem;border-radius:var(--r2);font-family:var(--sans);font-size:.73rem;font-weight:600;cursor:pointer;transition:all var(--t);border:none;white-space:nowrap;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem}
  .btn-top.green{background:var(--leaf);color:#0b1a12}
  .btn-top.green:hover{background:var(--leaf2);transform:translateY(-1px)}
  .btn-top.gold{background:rgba(201,168,76,.1);color:var(--gold);border:1px solid rgba(201,168,76,.2)}
  .btn-top.gold:hover{background:rgba(201,168,76,.18)}
  .btn-top.ghost{background:rgba(82,183,136,.06);color:var(--leaf2);border:1px solid var(--border)}
  .btn-top.ghost:hover{background:rgba(82,183,136,.1)}

  /* ── Stats ──────────────────────────────────────────────── */
  .stats-row{display:grid;grid-template-columns:repeat(5,1fr);gap:.65rem}
  .stat{background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);padding:.9rem 1rem;display:flex;align-items:center;gap:.7rem;transition:border-color var(--t),transform var(--t);position:relative;overflow:hidden;cursor:default}
  .stat::before{content:'';position:absolute;bottom:0;left:0;right:0;height:1px;background:var(--stat-accent,var(--leaf));opacity:.4}
  .stat:hover{border-color:var(--border);transform:translateY(-1px)}
  .stat-ico{font-size:1.1rem;flex-shrink:0;opacity:.65}
  .stat-val{font-family:var(--serif);font-size:1.3rem;color:var(--leaf3);line-height:1}
  .stat-lbl{font-size:.6rem;color:var(--text3);margin-top:.1rem;text-transform:uppercase;letter-spacing:.05em}

  /* ── Tabs ───────────────────────────────────────────────── */
  .tabs-wrap{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem}
  .tabs{display:flex;background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);padding:.25rem;gap:0}
  .tab-btn{padding:.34rem .9rem;border-radius:var(--r2);font-family:var(--sans);font-size:.74rem;font-weight:500;cursor:pointer;border:none;background:transparent;color:var(--text3);text-decoration:none;transition:all var(--t);display:inline-flex;align-items:center;gap:.3rem;white-space:nowrap}
  .tab-btn:hover{color:var(--leaf2);background:rgba(82,183,136,.04)}
  .tab-btn.active{background:var(--leaf);color:#0b1a12;font-weight:600}
  .tab-btn.active.gold-tab{background:linear-gradient(135deg,#a8892a,var(--gold));color:#1a0e00}
  .tab-badge{background:rgba(255,255,255,.15);border-radius:10px;padding:.04rem .35rem;font-size:.6rem;font-weight:700}

  /* ── Vestibulares ────────────────────────────────────────── */
  .cat-section{margin-bottom:.8rem}
  .cat-header{display:flex;align-items:center;gap:.45rem;margin-bottom:.6rem;padding-bottom:.4rem;border-bottom:1px solid var(--border2)}
  .cat-icon{font-size:.9rem}
  .cat-label{font-family:var(--serif);font-size:.88rem;color:var(--text)}
  .cat-count{font-size:.63rem;color:var(--text3);margin-left:auto;background:rgba(82,183,136,.06);padding:.08rem .38rem;border-radius:10px;border:1px solid var(--border2)}
  .vest-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(255px,1fr));gap:.7rem}
  .vest-card{background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);padding:.9rem 1rem;position:relative;overflow:hidden;transition:all var(--t)}
  .vest-card:hover{border-color:var(--border);transform:translateY(-1px)}
  .vest-accent{position:absolute;top:0;left:0;right:0;height:2px}
  .vest-name{font-family:var(--serif);font-size:.93rem;color:var(--text);margin-bottom:.08rem;line-height:1.3}
  .vest-meta{font-size:.63rem;color:var(--text3);margin-bottom:.05rem}
  .vest-desc{font-size:.7rem;color:var(--muted);margin-bottom:.6rem;line-height:1.5;min-height:1em}
  .vest-stats{display:flex;gap:.3rem;margin-bottom:.65rem;flex-wrap:wrap}
  .vest-stat{font-size:.64rem;color:var(--muted);background:rgba(82,183,136,.04);border:1px solid var(--border2);padding:.1rem .4rem;border-radius:20px}
  .vest-stat strong{color:var(--leaf2)}
  .vest-badge-pill{display:inline-block;font-size:.58rem;font-weight:700;padding:.08rem .4rem;border-radius:10px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.28rem}
  .vest-actions{display:flex;gap:.28rem;flex-wrap:wrap}
  .vbtn{padding:.28rem .6rem;border-radius:var(--r2);border:1px solid var(--border2);background:none;font-family:var(--sans);font-size:.68rem;color:var(--text3);cursor:pointer;transition:all var(--t);text-decoration:none;display:inline-flex;align-items:center;gap:.2rem;white-space:nowrap}
  .vbtn:hover{border-color:var(--leaf);color:var(--leaf2);background:rgba(82,183,136,.04)}
  .vbtn.primary{background:var(--leaf);color:#0b1a12;border-color:var(--leaf)}
  .vbtn.primary:hover{background:var(--leaf2)}
  .vbtn.gold{background:rgba(201,168,76,.08);color:var(--gold);border-color:rgba(201,168,76,.2)}
  .vbtn.gold:hover{background:rgba(201,168,76,.15)}
  .vbtn.danger:hover{border-color:rgba(224,82,82,.25);color:var(--red);background:rgba(224,82,82,.05)}
  .vest-toggle{position:absolute;top:.8rem;right:.9rem}
  .toggle{width:30px;height:17px;border-radius:9px;border:none;cursor:pointer;position:relative;transition:background .3s;flex-shrink:0}
  .toggle.on{background:var(--leaf)}.toggle.off{background:rgba(255,255,255,.08)}
  .toggle::after{content:'';position:absolute;top:2px;width:13px;height:13px;border-radius:50%;background:#fff;transition:left .3s}
  .toggle.on::after{left:15px}.toggle.off::after{left:2px}

  /* ── Questões ────────────────────────────────────────────── */
  .filter-row{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
  .fc-sm{padding:.44rem .7rem;background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r2);color:var(--text);font-family:var(--sans);font-size:.76rem;outline:none;transition:all var(--t);appearance:none}
  .fc-sm:focus{border-color:var(--leaf)}
  .fc-sm option{background:var(--ink2)}
  .search-wrap{position:relative;flex:1;min-width:180px;max-width:280px}
  .search-wrap svg{position:absolute;left:.6rem;top:50%;transform:translateY(-50%);width:13px;height:13px;color:var(--muted);pointer-events:none}
  .search-wrap .fc-sm{width:100%;padding-left:1.9rem}
  .btn-filter{padding:.44rem .85rem;background:var(--leaf);border:none;border-radius:var(--r2);color:#0b1a12;font-family:var(--sans);font-size:.74rem;font-weight:600;cursor:pointer;transition:all var(--t)}
  .btn-filter:hover{background:var(--leaf2)}
  .result-lbl{font-size:.67rem;color:var(--text3);margin-left:auto}
  .tbl-card{background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);overflow:hidden}
  .tbl-head{padding:.65rem 1rem;border-bottom:1px solid var(--border2);display:flex;align-items:center;justify-content:space-between}
  .tbl-head-title{font-size:.72rem;font-weight:500;color:var(--text2)}
  .tbl{width:100%;border-collapse:collapse}
  .tbl th{font-size:.57rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted2);font-weight:500;padding:.45rem 1rem;text-align:left;border-bottom:1px solid var(--border2)}
  .tbl td{padding:.48rem 1rem;font-size:.75rem;color:var(--text2);border-bottom:1px solid var(--border2)}
  .tbl tr:last-child td{border-bottom:none}
  .tbl tr:hover td{background:rgba(82,183,136,.025)}
  .tbl tr.inactive-row td{opacity:.35}
  .q-stmt{font-size:.74rem;color:var(--text);max-width:320px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .diff-badge{display:inline-block;padding:.08rem .38rem;border-radius:20px;font-size:.6rem;font-weight:600}
  .correct-opt{font-weight:700;padding:.07rem .36rem;border-radius:var(--r2);font-size:.7rem;background:rgba(82,183,136,.1);color:var(--leaf)}
  .tbl-actions{display:flex;gap:.25rem}
  .act{padding:.24rem .48rem;border-radius:var(--r2);border:1px solid var(--border2);background:none;color:var(--text3);font-size:.7rem;cursor:pointer;transition:all var(--t)}
  .act:hover{border-color:var(--leaf);color:var(--leaf2);background:rgba(82,183,136,.04)}
  .act.del:hover{border-color:rgba(224,82,82,.25);color:var(--red);background:rgba(224,82,82,.05)}

  /* ── Redação ─────────────────────────────────────────────── */
  .redacao-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:.7rem}
  .red-card{background:var(--ink2);border:1px solid rgba(201,168,76,.12);border-radius:var(--r);overflow:hidden;transition:all var(--t)}
  .red-card:hover{border-color:rgba(201,168,76,.25);transform:translateY(-1px)}
  .red-top{background:linear-gradient(135deg,rgba(201,168,76,.06),rgba(201,168,76,.02));padding:.8rem .95rem;border-bottom:1px solid rgba(201,168,76,.09)}
  .red-vest{font-size:.6rem;font-weight:600;color:rgba(201,168,76,.45);text-transform:uppercase;letter-spacing:.07em;margin-bottom:.18rem}
  .red-tema{font-family:var(--serif);font-size:.88rem;color:#e8c97a;line-height:1.35}
  .red-tipo{display:inline-block;margin-top:.28rem;font-size:.58rem;padding:.08rem .38rem;border-radius:10px;background:rgba(201,168,76,.1);color:rgba(201,168,76,.65);border:1px solid rgba(201,168,76,.16);text-transform:capitalize}
  .red-body{padding:.8rem .95rem}
  .red-lbl{font-size:.58rem;font-weight:600;color:var(--muted2);text-transform:uppercase;letter-spacing:.07em;margin-bottom:.3rem}
  .red-proposta{font-size:.71rem;color:var(--text2);line-height:1.55;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:.7rem}
  .red-comps{margin-bottom:.7rem}
  .red-comp-row{display:flex;align-items:center;gap:.42rem;padding:.22rem 0;border-bottom:1px solid var(--border2)}
  .red-comp-row:last-child{border-bottom:none}
  .red-comp-ico{font-size:.72rem}
  .red-comp-name{font-size:.7rem;color:var(--text2);flex:1}
  .red-comp-range{font-size:.6rem;color:rgba(201,168,76,.45);font-weight:600}
  .red-ai{background:rgba(201,168,76,.04);border:1px solid rgba(201,168,76,.1);border-radius:var(--r2);padding:.52rem .72rem;margin-bottom:.7rem;font-size:.7rem;color:rgba(201,168,76,.55);line-height:1.5}
  .red-ai strong{color:rgba(201,168,76,.85);display:block;margin-bottom:.05rem}
  .red-actions{display:flex;gap:.3rem}

  /* ── Notice / empty ─────────────────────────────────────── */
  .notice{background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);padding:2rem;text-align:center;color:var(--text3);font-size:.8rem;line-height:1.7}
  .notice h3{font-family:var(--serif);font-size:.98rem;color:var(--leaf3);margin-bottom:.45rem}

  /* ── Paginação ──────────────────────────────────────────── */
  .pagination{display:flex;align-items:center;justify-content:center;gap:.3rem;padding:.65rem;border-top:1px solid var(--border2)}
  .pg{padding:.26rem .58rem;border-radius:var(--r2);border:1px solid var(--border2);background:none;color:var(--text3);font-size:.71rem;cursor:pointer;text-decoration:none;transition:all var(--t)}
  .pg:hover{border-color:var(--leaf);color:var(--leaf2)}
  .pg.cur{background:var(--leaf);border-color:var(--leaf);color:#0b1a12;font-weight:600}
  .pg.off{opacity:.2;pointer-events:none}

  /* ── Modais ──────────────────────────────────────────────── */
  .overlay{position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.7);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;padding:1rem;opacity:0;pointer-events:none;transition:opacity var(--t)}
  .overlay.open{opacity:1;pointer-events:all}
  .modal{background:var(--ink2);border:1px solid var(--border);border-radius:var(--r);width:100%;max-width:540px;max-height:92vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,.6);transform:translateY(12px) scale(.97);transition:transform var(--t)}
  .overlay.open .modal{transform:none}
  .modal-sm .modal{max-width:400px}
  .modal-lg .modal{max-width:700px}
  .modal-head{padding:.75rem 1rem;border-bottom:1px solid var(--border2);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--ink2);z-index:1;border-radius:var(--r) var(--r) 0 0}
  .modal-title{font-family:var(--serif);font-size:.9rem;color:var(--leaf3);display:flex;align-items:center;gap:.35rem}
  .modal-x{width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,.04);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.72rem;color:var(--text3);transition:all var(--t)}
  .modal-x:hover{background:rgba(224,82,82,.15);color:var(--red)}
  .modal-body{padding:1.1rem}
  .modal-foot{padding:.7rem 1rem;border-top:1px solid var(--border2);display:flex;gap:.4rem;justify-content:flex-end;position:sticky;bottom:0;background:var(--ink2)}
  .fg{margin-bottom:.72rem}
  .fl{display:block;font-size:.68rem;font-weight:500;color:var(--muted);letter-spacing:.03em;margin-bottom:.25rem}
  .fc{width:100%;padding:.54rem .75rem;background:rgba(255,255,255,.03);border:1px solid var(--border2);border-radius:var(--r2);color:var(--text);font-family:var(--sans);font-size:.81rem;outline:none;transition:all var(--t);appearance:none}
  .fc:focus{border-color:var(--leaf);background:rgba(82,183,136,.05);box-shadow:0 0 0 3px rgba(82,183,136,.1)}
  .fc::placeholder{color:var(--text3)}
  .fc option{background:var(--ink2)}
  textarea.fc{resize:vertical;min-height:78px;line-height:1.55}
  .frow{display:grid;grid-template-columns:1fr 1fr;gap:.65rem}
  .frow3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.65rem}
  .fsep{height:1px;background:var(--border2);margin:.55rem 0}
  .fsec{font-size:.65rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:.45rem;padding-bottom:.3rem;border-bottom:1px solid var(--border2)}
  /* Alternativas */
  .opt-row{display:grid;grid-template-columns:28px 1fr;gap:.45rem;align-items:center;margin-bottom:.4rem}
  .opt-letter{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;color:var(--leaf2);background:rgba(82,183,136,.06);border:1px solid rgba(82,183,136,.15);flex-shrink:0;cursor:pointer;transition:all var(--t)}
  .opt-letter.selected{background:var(--leaf);color:#0b1a12;border-color:var(--leaf)}
  .opt-letter:hover:not(.selected){background:rgba(82,183,136,.14)}
  /* Competências */
  .comp-item{background:rgba(201,168,76,.04);border:1px solid rgba(201,168,76,.1);border-radius:var(--r2);padding:.5rem .7rem;display:flex;align-items:center;gap:.5rem;margin-bottom:.38rem}
  .comp-ico{font-size:.88rem;flex-shrink:0}
  .comp-info{flex:1}
  .comp-num{font-size:.57rem;font-weight:700;color:rgba(201,168,76,.42);text-transform:uppercase;letter-spacing:.07em}
  .comp-name{font-size:.76rem;font-weight:600;color:rgba(201,168,76,.75)}
  .comp-desc{font-size:.64rem;color:var(--muted);margin-top:.04rem;line-height:1.4}
  .comp-range{font-size:.66rem;font-weight:700;color:rgba(201,168,76,.5);flex-shrink:0}
  /* Botões modal */
  .btn-primary{padding:.5rem 1rem;background:var(--leaf);border:none;border-radius:var(--r2);color:#0b1a12;font-family:var(--sans);font-size:.77rem;font-weight:600;cursor:pointer;transition:all var(--t)}
  .btn-primary:hover{background:var(--leaf2);transform:translateY(-1px)}
  .btn-primary:disabled{opacity:.5;pointer-events:none;transform:none}
  .btn-primary.gold-btn{background:linear-gradient(135deg,#a8892a,var(--gold));color:#1a0e00}
  .btn-ghost{padding:.5rem 1rem;background:none;border:1px solid var(--border2);border-radius:var(--r2);color:var(--text3);font-family:var(--sans);font-size:.77rem;cursor:pointer;transition:all var(--t)}
  .btn-ghost:hover{border-color:var(--border);color:var(--text2)}
  .btn-danger{padding:.5rem 1rem;background:rgba(224,82,82,.1);border:1px solid rgba(224,82,82,.2);border-radius:var(--r2);color:var(--red);font-family:var(--sans);font-size:.77rem;cursor:pointer;transition:all var(--t)}
  .btn-danger:hover{background:rgba(224,82,82,.18)}
  .btn-danger:disabled{opacity:.5;pointer-events:none}
  .del-preview{background:rgba(255,255,255,.02);border:1px solid var(--border2);border-radius:var(--r2);padding:.6rem .8rem;margin-bottom:.7rem;font-size:.78rem;font-style:italic;color:var(--text2);line-height:1.5}
  .del-warn{font-size:.72rem;color:var(--text3);line-height:1.6}

  /* Toast */
  #toasts{position:fixed;bottom:1rem;right:1rem;z-index:999;display:flex;flex-direction:column;gap:.3rem;pointer-events:none}
  .toast{background:var(--ink2);color:var(--text);border:1px solid var(--border);border-radius:var(--r2);padding:.48rem .8rem;font-size:.71rem;display:flex;align-items:center;gap:.38rem;animation:slideIn .2s ease both;max-width:270px;pointer-events:all;box-shadow:0 4px 20px rgba(0,0,0,.4)}
  .toast.ok{border-left:2px solid var(--leaf)}.toast.err{border-left:2px solid var(--red)}.toast.warn{border-left:2px solid var(--gold)}
  @keyframes slideIn{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:none}}

  @media(max-width:1200px){.stats-row{grid-template-columns:repeat(3,1fr)}}
  @media(max-width:900px){.stats-row{grid-template-columns:repeat(2,1fr)}.vest-grid{grid-template-columns:1fr}}
  @media(max-width:768px){.main{margin-left:0}.page{padding:.9rem}.frow,.frow3{grid-template-columns:1fr}}
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
    <a class="a-link" href="mensagens.php"><span class="a-link-ico">⊡</span>Frases do dia</a>
    <a class="a-link active" href="simulados.php"><span class="a-link-ico">⊞</span>Simulados</a>
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
    <span class="tb-title">Simulados &amp; Redação</span>
    <div class="tb-right">
      <?php if ($tablesOk && $questOk): ?>
        <?php $tab2 = $tab ?? 'vestibulares'; ?>
        <?php if ($tab2 === 'questoes'): ?>
          <button class="btn-top ghost" onclick="openCreateVest()">+ Vestibular</button>
          <button class="btn-top green" onclick="openCreateQ()">+ Questão</button>
        <?php elseif ($tab2 === 'redacao'): ?>
          <button class="btn-top ghost" onclick="openCreateVest('redacao')">+ Vestibular Redação</button>
          <button class="btn-top gold" onclick="openCreateRedacao()">✍️ + Tema</button>
        <?php else: ?>
          <button class="btn-top ghost" onclick="openCreateVest()">+ Vestibular</button>
          <a class="btn-top ghost" href="?tab=questoes">Questões</a>
          <button class="btn-top gold" onclick="openCreateRedacao()">✍️ + Redação</button>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </header>

  <main class="page">
    <?php if (!$tablesOk || !$questOk): ?>
      <div class="notice">
        <h3>🗄️ Tabelas não encontradas</h3>
        Execute o arquivo <strong>simulados_migration.sql</strong> no phpMyAdmin para ativar os simulados.
      </div>
    <?php else: ?>

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat" style="--stat-accent:var(--leaf)"><span class="stat-ico">🏛️</span><div><div class="stat-val"><?= $totalVest ?></div><div class="stat-lbl">Vestibulares</div></div></div>
      <div class="stat" style="--stat-accent:#60a5fa"><span class="stat-ico">❓</span><div><div class="stat-val"><?= number_format($totalQ) ?></div><div class="stat-lbl">Questões ativas</div></div></div>
      <div class="stat" style="--stat-accent:var(--gold)"><span class="stat-ico">✍️</span><div><div class="stat-val"><?= $totalRedacoes ?></div><div class="stat-lbl">Temas de redação</div></div></div>
      <div class="stat" style="--stat-accent:#a78bfa"><span class="stat-ico">📝</span><div><div class="stat-val"><?= number_format($totalAttempts) ?></div><div class="stat-lbl">Simulados feitos</div></div></div>
      <div class="stat" style="--stat-accent:#34d399"><span class="stat-ico">📊</span><div><div class="stat-val"><?= $avgPct ?>%</div><div class="stat-lbl">Média de acerto</div></div></div>
    </div>

    <!-- Tabs -->
    <div class="tabs-wrap">
      <div class="tabs">
        <a class="tab-btn <?= ($tab??'vestibulares')==='vestibulares'?'active':'' ?>" href="?tab=vestibulares">🏛️ Vestibulares</a>
        <a class="tab-btn <?= ($tab??'')==='questoes'?'active':'' ?>" href="?tab=questoes">
          ❓ Questões <span class="tab-badge"><?= number_format($totalQ) ?></span>
        </a>
        <a class="tab-btn gold-tab <?= ($tab??'')==='redacao'?'active':'' ?>" href="?tab=redacao">
          ✍️ Redação <span class="tab-badge"><?= $totalRedacoes ?></span>
        </a>
      </div>
    </div>

    <?php if (($tab??'vestibulares') === 'vestibulares'): ?>
    <!-- ══ ABA: VESTIBULARES ══ -->
    <?php
    $grouped = [];
    foreach ($vestibulares as $v) {
        $cat = $v['category'] ?: 'vestibular';
        $grouped[$cat][] = $v;
    }
    if (empty($vestibulares)): ?>
      <div class="notice">Nenhum vestibular cadastrado. Clique em "+ Vestibular" para começar.</div>
    <?php else:
      foreach (['vestibular','materia','escolar','redacao'] as $catKey):
        if (empty($grouped[$catKey])) continue;
        $cinfo = $catGroups[$catKey];
    ?>
      <div class="cat-section">
        <div class="cat-header">
          <span class="cat-icon"><?= $cinfo['icon'] ?></span>
          <span class="cat-label"><?= $cinfo['label'] ?></span>
          <span class="cat-count"><?= count($grouped[$catKey]) ?></span>
        </div>
        <div class="vest-grid">
          <?php foreach ($grouped[$catKey] as $v):
            $isRedacao  = ($v['category'] === 'redacao');
            $accentColor = $cinfo['color'];
            $badgeMap   = ['popular'=>['⭐ Popular','var(--gold)','rgba(201,168,76,.1)'],'novo'=>['✨ Novo','var(--leaf)','rgba(82,183,136,.08)']];
            $badgeInfo  = $badgeMap[$v['badge']] ?? null;
          ?>
          <div class="vest-card" id="vest-<?= $v['id'] ?>">
            <div class="vest-accent" style="background:linear-gradient(90deg,<?= $accentColor ?>,transparent)"></div>
            <button class="toggle vest-toggle <?= $v['is_active']?'on':'off' ?>"
                    id="tog-<?= $v['id'] ?>" onclick="toggleVest(<?= $v['id'] ?>,this)"
                    title="<?= $v['is_active']?'Ativo':'Inativo' ?>"></button>
            <?php if ($badgeInfo): ?>
              <div class="vest-badge-pill" style="color:<?= $badgeInfo[1] ?>;background:<?= $badgeInfo[2] ?>;border:1px solid <?= $badgeInfo[1] ?>33"><?= $badgeInfo[0] ?></div>
            <?php endif; ?>
            <div class="vest-name"><?= htmlspecialchars($v['name'],ENT_QUOTES) ?></div>
            <div class="vest-meta">
              <?php
              $catLabels=['vestibular'=>'🎓 Vestibular','materia'=>'📚 Por Matéria','escolar'=>'🏫 Escolar','redacao'=>'✍️ Redação'];
              echo $catLabels[$v['category']] ?? '📋 Simulado';
              if ($v['grade_level']) echo ' · '.htmlspecialchars($v['grade_level'],ENT_QUOTES);
              ?>
            </div>
            <div class="vest-desc"><?= htmlspecialchars($v['description']??'',ENT_QUOTES) ?></div>
            <div class="vest-stats">
              <?php if (!$isRedacao): ?>
                <span class="vest-stat"><strong><?= $v['total_q'] ?></strong> questões</span>
                <span class="vest-stat"><strong><?= $v['active_q'] ?></strong> ativas</span>
              <?php else: ?>
                <?php $redCount = $redacaoOk ? (int)(dbRow('SELECT COUNT(*) AS n FROM sim_redacoes WHERE vestibular_id=? AND is_active=1',[$v['id']])['n']??0) : 0; ?>
                <span class="vest-stat"><strong><?= $redCount ?></strong> temas</span>
              <?php endif; ?>
              <?php if ($v['time_max']): ?><span class="vest-stat">⏱ <?= $v['time_max'] ?>min</span><?php endif; ?>
            </div>
            <div class="vest-actions">
              <?php if (!$isRedacao): ?>
                <a class="vbtn primary" href="?tab=questoes&vest=<?= $v['id'] ?>">❓ Questões</a>
              <?php else: ?>
                <a class="vbtn gold" href="?tab=redacao&vest=<?= $v['id'] ?>">✍️ Temas</a>
              <?php endif; ?>
              <button class="vbtn" onclick='openEditVest(<?= json_encode(["id"=>(int)$v["id"],"name"=>$v["name"],"description"=>$v["description"]??"","sort_order"=>(int)($v["sort_order"]??0),"category"=>$v["category"]??"vestibular","badge"=>$v["badge"]??"","grade_level"=>$v["grade_level"]??"","time_min"=>(int)($v["time_min"]??0),"time_max"=>(int)($v["time_max"]??60)],JSON_UNESCAPED_UNICODE) ?>)'>✏️ Editar</button>
              <button class="act del" title="Excluir"
  onclick="confirmDelQ(<?= $q['id'] ?>)">🗑</button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; endif; ?>

    <?php elseif (($tab??'') === 'questoes'): ?>
    <!-- ══ ABA: QUESTÕES ══ -->
    <form method="GET" class="filter-row">
      <input type="hidden" name="tab" value="questoes"/>
      <select class="fc-sm" name="vest">
        <option value="">Todos vestibulares</option>
        <?php foreach ($vestibulares as $v):
          if ($v['category']==='redacao') continue; ?>
          <option value="<?= $v['id'] ?>" <?= $vestFilter==(int)$v['id']?'selected':'' ?>><?= htmlspecialchars($v['name'],ENT_QUOTES) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($hasDiff): ?>
      <select class="fc-sm" name="diff">
        <option value="">Todos os níveis</option>
        <?php foreach ($DIFFS as $k=>[$lbl,,]): ?>
          <option value="<?= $k ?>" <?= $diffFilter===$k?'selected':'' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
      <div class="search-wrap">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input class="fc-sm" type="text" name="q" value="<?= htmlspecialchars($search,ENT_QUOTES) ?>" placeholder="Buscar enunciado…"/>
      </div>
      <button class="btn-filter" type="submit">Filtrar</button>
      <?php if ($vestFilter||$diffFilter||$search): ?>
        <a href="?tab=questoes" style="font-size:.7rem;color:var(--text3);text-decoration:none">✕ Limpar</a>
      <?php endif; ?>
      <span class="result-lbl"><?= $totalRows ?? 0 ?> questão(ões)</span>
    </form>

    <div class="tbl-card">
      <div class="tbl-head">
        <span class="tbl-head-title">Questões cadastradas</span>
        <span style="font-size:.63rem;color:var(--text3)">Página <?= $page ?>/<?= $totalPages ?? 1 ?></span>
      </div>
      <table class="tbl">
        <thead>
          <tr>
            <th>#</th><th>Vestibular</th>
            <?php if ($hasDiff): ?><th>Nível</th><?php endif; ?>
            <th>Matéria</th><th>Enunciado</th>
            <?php if ($hasOrigin): ?><th>Origem</th><?php endif; ?>
            <th>Gab.</th><th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($questions)): ?>
            <tr><td colspan="9" style="text-align:center;padding:2rem;color:var(--text3);font-size:.74rem">Nenhuma questão encontrada.</td></tr>
          <?php endif; ?>
          <?php foreach ($questions ?? [] as $q):
            $diff = $q['difficulty'] ?? 'medio';
            [$dLbl,$dColor,$dBg] = $DIFFS[$diff] ?? ['?','#888','#eee'];
          ?>
          <tr class="<?= !$q['is_active']?'inactive-row':'' ?>" id="qrow-<?= $q['id'] ?>">
            <td style="font-size:.68rem;color:var(--text3)"><?= $q['id'] ?></td>
            <td>
              <div style="font-size:.73rem;font-weight:500;color:var(--leaf2)"><?= htmlspecialchars($q['vest_name'],ENT_QUOTES) ?></div>
              <?php if ($q['year']): ?><div style="font-size:.62rem;color:var(--text3)"><?= $q['year'] ?></div><?php endif; ?>
            </td>
            <?php if ($hasDiff): ?>
            <td><span class="diff-badge" style="background:<?= $dBg ?>;color:<?= $dColor ?>;border:1px solid <?= $dColor ?>44"><?= $dLbl ?></span></td>
            <?php endif; ?>
            <td style="font-size:.71rem;color:var(--muted)"><?= htmlspecialchars($q['subject_tag']??'',ENT_QUOTES) ?></td>
            <td><div class="q-stmt" title="<?= htmlspecialchars($q['statement'],ENT_QUOTES) ?>"><?= htmlspecialchars(mb_substr($q['statement'],0,70,'UTF-8'),ENT_QUOTES) ?>…</div></td>
            <?php if ($hasOrigin): ?><td style="font-size:.68rem;color:var(--text3)"><?= htmlspecialchars($q['origin']??'',ENT_QUOTES) ?></td><?php endif; ?>
            <td><span class="correct-opt"><?= strtoupper($q['correct_option']) ?></span></td>
            <td>
              <div class="tbl-actions">
                <button class="act" title="Editar"
                  onclick='openEditQ(<?= json_encode(["id"=>(int)$q["id"],"vest"=>(int)$q["vestibular_id"],"subject"=>$q["subject_tag"]??"","year"=>$q["year"]??"","difficulty"=>$q["difficulty"]??"medio","origin"=>$q["origin"]??"","stmt"=>$q["statement"],"a"=>$q["option_a"]??"","b"=>$q["option_b"]??"","c"=>$q["option_c"]??"","d"=>$q["option_d"]??"","e"=>$q["option_e"]??"","correct"=>$q["correct_option"],"expl"=>$q["explanation"]??"","active"=>(bool)$q["is_active"]],JSON_UNESCAPED_UNICODE) ?>)'>✏️</button>
                <button class="vbtn danger"
                data-id="<?= $red['id'] ?>"
                data-tema="<?= htmlspecialchars($red['tema'], ENT_QUOTES) ?>"
                onclick="confirmDelRedacao(this.dataset.id, this.dataset.tema)">🗑 Excluir
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php
      $tp = $totalPages ?? 1;
      if ($tp > 1):
        function pUrlQ(int $p, int $v, string $d, string $q): string {
            return '?'.http_build_query(array_filter(['tab'=>'questoes','page'=>$p>1?$p:null,'vest'=>$v?:null,'diff'=>$d?:null,'q'=>$q?:null]));
        }
      ?>
      <div class="pagination">
        <a class="pg <?= $page<=1?'off':'' ?>" href="<?= pUrlQ($page-1,$vestFilter,$diffFilter,$search) ?>">‹</a>
        <?php for ($p=max(1,$page-2);$p<=min($tp,$page+2);$p++): ?>
          <a class="pg <?= $p===$page?'cur':'' ?>" href="<?= pUrlQ($p,$vestFilter,$diffFilter,$search) ?>"><?= $p ?></a>
        <?php endfor; ?>
        <a class="pg <?= $page>=$tp?'off':'' ?>" href="<?= pUrlQ($page+1,$vestFilter,$diffFilter,$search) ?>">›</a>
      </div>
      <?php endif; ?>
    </div>

    <?php elseif (($tab??'') === 'redacao'): ?>
    <!-- ══ ABA: REDAÇÃO ══ -->
    <?php if (!$redacaoOk): ?>
      <div class="notice">
        <h3>✍️ Módulo de Redação não instalado</h3>
        Execute o arquivo <strong>simulados_redacao_migration.sql</strong> no phpMyAdmin para habilitar.
      </div>
    <?php elseif (empty($redacoes)): ?>
      <div class="notice">
        <h3>✍️ Nenhum tema cadastrado</h3>
        Clique em "+ Tema de Redação" para criar o primeiro tema.
      </div>
    <?php else: ?>
      <?php if ($vestFilter): ?><div style="margin-bottom:.45rem"><a href="?tab=redacao" style="font-size:.7rem;color:var(--text3);text-decoration:none">← Todos os temas</a></div><?php endif; ?>
      <div class="redacao-grid">
        <?php foreach ($redacoes as $red): ?>
        <div class="red-card" id="red-<?= $red['id'] ?>">
          <div class="red-top">
            <div class="red-vest"><?= htmlspecialchars($red['vest_name'],ENT_QUOTES) ?></div>
            <div class="red-tema"><?= htmlspecialchars($red['tema'],ENT_QUOTES) ?></div>
            <div class="red-tipo"><?= ucfirst($red['tipo']??'dissertativo') ?></div>
          </div>
          <div class="red-body">
            <div class="red-lbl">Proposta de redação</div>
            <div class="red-proposta"><?= htmlspecialchars($red['proposta'],ENT_QUOTES) ?></div>
            <div class="red-comps">
              <div class="red-lbl">Critérios de correção (ENEM)</div>
              <?php foreach ($COMPS as $c): ?>
              <div class="red-comp-row">
                <span class="red-comp-ico"><?= $c['icon'] ?></span>
                <div class="red-comp-name">C<?= $c['num'] ?>: <?= $c['label'] ?></div>
                <span class="red-comp-range">0–200</span>
              </div>
              <?php endforeach; ?>
            </div>
            <div class="red-ai"><strong>🤖 Correção automática em breve</strong>Integração com IA para correção pelas 5 competências está em desenvolvimento.</div>
            <div class="red-actions">
              <button class="vbtn" onclick='openEditRedacao(<?= json_encode(["id"=>(int)$red["id"],"vestibular_id"=>(int)$red["vestibular_id"],"tema"=>$red["tema"],"texto1"=>$red["texto1"]??"","texto2"=>$red["texto2"]??"","texto3"=>$red["texto3"]??"","proposta"=>$red["proposta"],"tipo"=>$red["tipo"]??"dissertativo","is_active"=>(bool)($red["is_active"]??1),"sort_order"=>(int)($red["sort_order"]??0)],JSON_UNESCAPED_UNICODE) ?>)'>✏️ Editar</button>
              <button class="vbtn danger" onclick="confirmDelRedacao(<?= $red['id'] ?>, <?= json_encode($red['tema'],JSON_UNESCAPED_UNICODE) ?>)">🗑 Excluir</button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php endif; ?>
  </main>
</div>

<!-- Modal: Vestibular -->
<div class="overlay modal-sm" id="overlayVest">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title" id="vestModalTitle">Novo vestibular</span>
      <button class="modal-x" onclick="closeOverlay('overlayVest')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="vestId"/>
      <div class="frow">
        <div class="fg"><label class="fl">Nome *</label><input class="fc" type="text" id="vestName" placeholder="Ex: ENEM, FUVEST…" maxlength="150"/></div>
        <div class="fg"><label class="fl">Categoria</label>
          <select class="fc" id="vestCategory">
            <option value="vestibular">🎓 Vestibular/ENEM</option>
            <option value="materia">📚 Por Matéria</option>
            <option value="escolar">🏫 Escolar</option>
            <option value="redacao">✍️ Redação</option>
          </select>
        </div>
      </div>
      <div class="fg"><label class="fl">Descrição</label><input class="fc" type="text" id="vestDesc" placeholder="Breve descrição…" maxlength="500"/></div>
      <div class="frow">
        <div class="fg"><label class="fl">Nível / Série</label><input class="fc" type="text" id="vestGrade" placeholder="Ex: 3º EM…" maxlength="80"/></div>
        <div class="fg"><label class="fl">Badge</label>
          <select class="fc" id="vestBadge"><option value="">Nenhum</option><option value="novo">✨ Novo</option><option value="popular">⭐ Popular</option></select>
        </div>
      </div>
      <div class="frow">
        <div class="fg"><label class="fl">Tempo mínimo (min)</label><input class="fc" type="number" id="vestTimeMin" value="0" min="0"/></div>
        <div class="fg"><label class="fl">Tempo máximo (min)</label><input class="fc" type="number" id="vestTimeMax" value="60" min="0"/></div>
      </div>
      <div class="fg"><label class="fl">Ordem de exibição</label><input class="fc" type="number" id="vestOrder" value="0" min="0"/></div>
    </div>
    <div class="modal-foot">
      <button class="btn-ghost" onclick="closeOverlay('overlayVest')">Cancelar</button>
      <button class="btn-primary" id="btnSaveVest" onclick="submitVest()">Salvar</button>
    </div>
  </div>
</div>

<!-- Modal: Questão -->
<div class="overlay" id="overlayQ">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title" id="qModalTitle">Nova questão</span>
      <button class="modal-x" onclick="closeOverlay('overlayQ')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="qId"/>
      <div class="fsec">Identificação</div>
      <div class="frow">
        <div class="fg"><label class="fl">Vestibular *</label>
          <select class="fc" id="qVest">
            <option value="">Selecione…</option>
            <?php foreach ($vestibulares??[] as $v):
              if ($v['category']==='redacao') continue; ?>
              <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name'],ENT_QUOTES) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg"><label class="fl">Nível</label>
          <select class="fc" id="qDiff">
            <option value="facil">🟢 Fácil</option>
            <option value="medio" selected>🟡 Médio</option>
            <option value="dificil">🔴 Difícil</option>
          </select>
        </div>
      </div>
      <div class="frow3">
        <div class="fg"><label class="fl">Matéria / Tema</label><input class="fc" type="text" id="qSubject" placeholder="Ex: Funções…" maxlength="100"/></div>
        <div class="fg"><label class="fl">Origem</label><input class="fc" type="text" id="qOrigin" placeholder="Ex: ENEM…" maxlength="80"/></div>
        <div class="fg"><label class="fl">Ano</label><input class="fc" type="number" id="qYear" placeholder="2024" min="1990" max="2099"/></div>
      </div>
      <div class="fsep"></div>
      <div class="fsec">Questão</div>
      <div class="fg"><label class="fl">Enunciado *</label><textarea class="fc" id="qStmt" rows="4" placeholder="Escreva o enunciado completo…"></textarea></div>
      <div class="fg">
        <label class="fl">Alternativas — clique na letra para marcar o gabarito *</label>
        <?php foreach (['a','b','c','d','e'] as $l): ?>
        <div class="opt-row">
          <div class="opt-letter" id="opt-<?= $l ?>" onclick="selectOpt('<?= $l ?>')"><?= strtoupper($l) ?></div>
          <input class="fc" type="text" id="opt-inp-<?= $l ?>" placeholder="Alternativa <?= strtoupper($l) ?><?= $l==='e'?' (opcional)':'' ?>"/>
        </div>
        <?php endforeach; ?>
        <input type="hidden" id="qCorrect" value="a"/>
      </div>
      <div class="fg"><label class="fl">Explicação (opcional)</label><textarea class="fc" id="qExpl" rows="3" placeholder="Comentário exibido no gabarito…"></textarea></div>
      <div class="fg" style="display:flex;align-items:center;gap:.45rem">
        <input type="checkbox" id="qActive" checked style="accent-color:var(--leaf);width:14px;height:14px"/>
        <label class="fl" for="qActive" style="margin:0;cursor:pointer">Questão ativa</label>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-ghost" onclick="closeOverlay('overlayQ')">Cancelar</button>
      <button class="btn-primary" id="btnSaveQ" onclick="submitQ()">Salvar questão</button>
    </div>
  </div>
</div>

<!-- Modal: Redação -->
<div class="overlay modal-lg" id="overlayRedacao">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title">✍️ <span id="redModalTitle">Novo tema de redação</span></span>
      <button class="modal-x" onclick="closeOverlay('overlayRedacao')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="redId"/>
      <div class="frow">
        <div class="fg"><label class="fl">Vestibular / Simulado *</label>
          <select class="fc" id="redVest">
            <option value="">Selecione…</option>
            <?php foreach ($vestibulares??[] as $v): ?>
              <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name'],ENT_QUOTES) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg"><label class="fl">Tipo de texto</label>
          <select class="fc" id="redTipo">
            <option value="dissertativo">Dissertativo-argumentativo</option>
            <option value="narrativo">Narrativo</option>
            <option value="expositivo">Expositivo</option>
          </select>
        </div>
      </div>
      <div class="fg"><label class="fl">Tema *</label><input class="fc" type="text" id="redTema" placeholder="Ex: Os desafios da saúde mental entre jovens na era digital" maxlength="300"/></div>
      <div class="fsep"></div>
      <div class="fsec">Textos motivadores (mínimo 2)</div>
      <div class="fg"><label class="fl">Texto 1 *</label><textarea class="fc" id="redTexto1" rows="4" placeholder="Texto motivador 1 — inclua a fonte ao final…"></textarea></div>
      <div class="fg"><label class="fl">Texto 2 *</label><textarea class="fc" id="redTexto2" rows="4" placeholder="Texto motivador 2…"></textarea></div>
      <div class="fg"><label class="fl">Texto 3 (opcional)</label><textarea class="fc" id="redTexto3" rows="3" placeholder="Texto motivador 3…"></textarea></div>
      <div class="fsep"></div>
      <div class="fsec">Proposta de redação</div>
      <div class="fg"><label class="fl">Proposta *</label><textarea class="fc" id="redProposta" rows="5" placeholder="A partir da leitura dos textos motivadores…"></textarea></div>
      <div class="fsep"></div>
      <div class="fsec">Critérios de correção — modelo ENEM</div>
      <?php foreach ($COMPS as $c): ?>
      <div class="comp-item">
        <span class="comp-ico"><?= $c['icon'] ?></span>
        <div class="comp-info">
          <div class="comp-num">Competência <?= $c['num'] ?></div>
          <div class="comp-name"><?= $c['label'] ?></div>
        </div>
        <span class="comp-range">0–200</span>
      </div>
      <?php endforeach; ?>
      <div class="frow" style="margin-top:.75rem">
        <div class="fg"><label class="fl">Ordem de exibição</label><input class="fc" type="number" id="redOrder" value="0" min="0"/></div>
        <div class="fg" style="display:flex;align-items:flex-end;gap:.45rem;padding-bottom:.72rem">
          <input type="checkbox" id="redActive" checked style="accent-color:var(--leaf);width:14px;height:14px;flex-shrink:0"/>
          <label class="fl" for="redActive" style="margin:0;cursor:pointer">Tema ativo</label>
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-ghost" onclick="closeOverlay('overlayRedacao')">Cancelar</button>
      <button class="btn-primary gold-btn" id="btnSaveRed" onclick="submitRedacao()">✍️ Salvar tema</button>
    </div>
  </div>
</div>

<!-- Modal: Delete -->
<div class="overlay modal-sm" id="overlayDel">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title" id="delTitle">Confirmar exclusão</span>
      <button class="modal-x" onclick="closeOverlay('overlayDel')">✕</button>
    </div>
    <div class="modal-body">
      <div class="del-preview" id="delPreview"></div>
      <p class="del-warn" id="delMsg"></p>
    </div>
    <div class="modal-foot">
      <button class="btn-ghost" onclick="closeOverlay('overlayDel')">Cancelar</button>
      <button class="btn-danger" id="btnConfirmDel" onclick="submitDel()">Excluir</button>
    </div>
  </div>
</div>

<div id="toasts"></div>

<script>
const API = '/florescer/admin/api/simulados.php';

function toast(msg,type='ok',ms=3500){
  const w=document.getElementById('toasts'),d=document.createElement('div');
  d.className=`toast ${type}`;
  d.innerHTML=`<span>${type==='ok'?'✓':type==='err'?'✕':'!'}</span><span>${msg}</span>`;
  w.appendChild(d);
  setTimeout(()=>{d.style.opacity='0';d.style.transition='.25s';setTimeout(()=>d.remove(),260);},ms);
}
function openOverlay(id){document.getElementById(id).classList.add('open');document.body.style.overflow='hidden';}
function closeOverlay(id){document.getElementById(id).classList.remove('open');document.body.style.overflow='';}
document.querySelectorAll('.overlay').forEach(o=>o.addEventListener('click',e=>{if(e.target===o)closeOverlay(o.id);}));
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.overlay.open').forEach(o=>closeOverlay(o.id));});

async function apiCall(body){
  try{
    const r=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    return r.json();
  }catch{return{success:false,message:'Erro de conexão.'};}
}

/* ── Vestibulares ── */
function openCreateVest(defaultCat='vestibular'){
  document.getElementById('vestId').value='';
  document.getElementById('vestModalTitle').textContent='Novo vestibular';
  ['vestName','vestDesc','vestGrade'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('vestCategory').value=defaultCat;
  document.getElementById('vestBadge').value='';
  document.getElementById('vestOrder').value='0';
  document.getElementById('vestTimeMin').value='0';
  document.getElementById('vestTimeMax').value='60';
  openOverlay('overlayVest');
  setTimeout(()=>document.getElementById('vestName').focus(),150);
}
function openEditVest(v){
  document.getElementById('vestId').value=v.id;
  document.getElementById('vestModalTitle').textContent='Editar vestibular';
  document.getElementById('vestName').value=v.name;
  document.getElementById('vestDesc').value=v.description||'';
  document.getElementById('vestCategory').value=v.category||'vestibular';
  document.getElementById('vestBadge').value=v.badge||'';
  document.getElementById('vestGrade').value=v.grade_level||'';
  document.getElementById('vestOrder').value=v.sort_order||0;
  document.getElementById('vestTimeMin').value=v.time_min||0;
  document.getElementById('vestTimeMax').value=v.time_max||60;
  openOverlay('overlayVest');
  setTimeout(()=>document.getElementById('vestName').focus(),150);
}
async function submitVest(){
  const id=document.getElementById('vestId').value;
  const name=document.getElementById('vestName').value.trim();
  if(!name){toast('Informe o nome.','err');return;}
  const btn=document.getElementById('btnSaveVest');
  btn.disabled=true;btn.textContent='Salvando…';
  try{
    const d=await apiCall({action:id?'update_vest':'create_vest',id:id?+id:null,name,
      description:document.getElementById('vestDesc').value.trim(),
      category:document.getElementById('vestCategory').value,
      badge:document.getElementById('vestBadge').value,
      grade_level:document.getElementById('vestGrade').value.trim()||null,
      sort_order:parseInt(document.getElementById('vestOrder').value)||0,
      time_min:parseInt(document.getElementById('vestTimeMin').value)||0,
      time_max:parseInt(document.getElementById('vestTimeMax').value)||60,
    });
    if(d.success){toast(id?'Atualizado!':'Vestibular criado!');closeOverlay('overlayVest');setTimeout(()=>location.reload(),500);}
    else toast(d.message||'Erro.','err');
  }finally{btn.disabled=false;btn.textContent='Salvar';}
}
async function toggleVest(id,btn){
  const on=btn.classList.contains('on');
  const d=await apiCall({action:'toggle_vest',id,active:!on});
  if(d.success){btn.classList.toggle('on',!on);btn.classList.toggle('off',on);toast(!on?'Ativado':'Desativado.');}
  else toast('Erro.','err');
}

/* ── Questões ── */
let selOpt='a';
function selectOpt(l){
  selOpt=l;document.getElementById('qCorrect').value=l;
  ['a','b','c','d','e'].forEach(x=>document.getElementById('opt-'+x).classList.toggle('selected',x===l));
}
function openCreateQ(){
  document.getElementById('qId').value='';
  document.getElementById('qModalTitle').textContent='Nova questão';
  ['qVest','qSubject','qOrigin','qYear','qStmt','qExpl'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('qDiff').value='medio';
  ['a','b','c','d','e'].forEach(l=>document.getElementById('opt-inp-'+l).value='');
  document.getElementById('qActive').checked=true;
  selectOpt('a');
  openOverlay('overlayQ');
  setTimeout(()=>document.getElementById('qStmt').focus(),150);
}
function openEditQ(q){
  document.getElementById('qId').value=q.id;
  document.getElementById('qModalTitle').textContent='Editar questão #'+q.id;
  document.getElementById('qVest').value=q.vest;
  document.getElementById('qDiff').value=q.difficulty||'medio';
  document.getElementById('qSubject').value=q.subject||'';
  document.getElementById('qOrigin').value=q.origin||'';
  document.getElementById('qYear').value=q.year||'';
  document.getElementById('qStmt').value=q.stmt;
  ['a','b','c','d','e'].forEach(l=>document.getElementById('opt-inp-'+l).value=q[l]||'');
  document.getElementById('qExpl').value=q.expl||'';
  document.getElementById('qActive').checked=!!q.active;
  selectOpt(q.correct||'a');
  openOverlay('overlayQ');
  setTimeout(()=>document.getElementById('qStmt').focus(),150);
}
async function submitQ(){
  const id=document.getElementById('qId').value;
  const vest=document.getElementById('qVest').value;
  const stmt=document.getElementById('qStmt').value.trim();
  const opts={};
  ['a','b','c','d','e'].forEach(l=>{opts[l]=document.getElementById('opt-inp-'+l).value.trim();});
  if(!vest){toast('Selecione o vestibular.','err');return;}
  if(!stmt){toast('O enunciado é obrigatório.','err');return;}
  if(!opts.a||!opts.b||!opts.c||!opts.d){toast('Preencha A, B, C e D.','err');return;}
  const btn=document.getElementById('btnSaveQ');
  btn.disabled=true;btn.textContent='Salvando…';
  try{
    const d=await apiCall({action:id?'update_q':'create_q',id:id?+id:null,
      vestibular_id:+vest,
      difficulty:document.getElementById('qDiff').value,
      subject_tag:document.getElementById('qSubject').value.trim()||null,
      origin:document.getElementById('qOrigin').value.trim()||null,
      year:document.getElementById('qYear').value?+document.getElementById('qYear').value:null,
      statement:stmt,
      option_a:opts.a,option_b:opts.b,option_c:opts.c,option_d:opts.d,option_e:opts.e||null,
      correct_option:document.getElementById('qCorrect').value,
      explanation:document.getElementById('qExpl').value.trim()||null,
      is_active:document.getElementById('qActive').checked?1:0,
    });
    if(d.success){toast(id?'Questão atualizada!':'Questão criada!');closeOverlay('overlayQ');setTimeout(()=>location.reload(),500);}
    else toast(d.message||'Erro.','err');
  }finally{btn.disabled=false;btn.textContent='Salvar questão';}
}

/* ── Redação ── */
function openCreateRedacao(){
  document.getElementById('redId').value='';
  document.getElementById('redModalTitle').textContent='Novo tema de redação';
  ['redVest','redTema','redTexto1','redTexto2','redTexto3','redProposta'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('redTipo').value='dissertativo';
  document.getElementById('redOrder').value='0';
  document.getElementById('redActive').checked=true;
  openOverlay('overlayRedacao');
  setTimeout(()=>document.getElementById('redTema').focus(),150);
}
function openEditRedacao(r){
  document.getElementById('redId').value=r.id;
  document.getElementById('redModalTitle').textContent='Editar tema de redação';
  document.getElementById('redVest').value=r.vestibular_id;
  document.getElementById('redTema').value=r.tema||'';
  document.getElementById('redTexto1').value=r.texto1||'';
  document.getElementById('redTexto2').value=r.texto2||'';
  document.getElementById('redTexto3').value=r.texto3||'';
  document.getElementById('redProposta').value=r.proposta||'';
  document.getElementById('redTipo').value=r.tipo||'dissertativo';
  document.getElementById('redOrder').value=r.sort_order||0;
  document.getElementById('redActive').checked=!!r.is_active;
  openOverlay('overlayRedacao');
  setTimeout(()=>document.getElementById('redTema').focus(),150);
}
async function submitRedacao(){
  const id=document.getElementById('redId').value;
  const vest=document.getElementById('redVest').value;
  const tema=document.getElementById('redTema').value.trim();
  const txt1=document.getElementById('redTexto1').value.trim();
  const txt2=document.getElementById('redTexto2').value.trim();
  const prop=document.getElementById('redProposta').value.trim();
  if(!vest){toast('Selecione o vestibular.','err');return;}
  if(!tema){toast('O tema é obrigatório.','err');return;}
  if(!txt1){toast('O Texto 1 é obrigatório.','err');return;}
  if(!txt2){toast('O Texto 2 é obrigatório.','err');return;}
  if(!prop){toast('A proposta é obrigatória.','err');return;}
  const btn=document.getElementById('btnSaveRed');
  btn.disabled=true;btn.textContent='Salvando…';
  try{
    const d=await apiCall({action:id?'update_redacao':'create_redacao',id:id?+id:null,
      vestibular_id:+vest,tema,
      texto1:txt1,texto2:txt2,
      texto3:document.getElementById('redTexto3').value.trim()||null,
      proposta:prop,
      tipo:document.getElementById('redTipo').value,
      sort_order:parseInt(document.getElementById('redOrder').value)||0,
      is_active:document.getElementById('redActive').checked?1:0,
    });
    if(d.success){toast(id?'Tema atualizado!':'Tema criado!');closeOverlay('overlayRedacao');setTimeout(()=>location.reload(),500);}
    else toast(d.message||'Erro.','err');
  }finally{btn.disabled=false;btn.textContent='✍️ Salvar tema';}
}

/* ── Delete ── */
let delAction=null,delId=null;
function confirmDelVest(id,name){
  delAction='delete_vest';delId=id;
  document.getElementById('delTitle').textContent='Excluir vestibular';
  document.getElementById('delPreview').textContent=name;
  document.getElementById('delMsg').textContent='Todas as questões vinculadas também serão excluídas. Esta ação é irreversível.';
  openOverlay('overlayDel');
}
function confirmDelQ(id){
  delAction='delete_q';delId=id;
  document.getElementById('delTitle').textContent='Excluir questão #'+id;
  document.getElementById('delPreview').textContent='Questão ID '+id;
  document.getElementById('delMsg').textContent='Esta ação é irreversível.';
  openOverlay('overlayDel');
}
function confirmDelRedacao(id,tema){
  delAction='delete_redacao';delId=id;
  document.getElementById('delTitle').textContent='Excluir tema';
  document.getElementById('delPreview').textContent=tema;
  document.getElementById('delMsg').textContent='O tema e seus textos motivadores serão excluídos permanentemente.';
  openOverlay('overlayDel');
}
async function submitDel(){
  if(!delAction||!delId) return;
  const btn=document.getElementById('btnConfirmDel');
  btn.disabled=true;btn.textContent='Excluindo…';
  try{
    const d=await apiCall({action:delAction,id:delId});
    if(d.success){toast('Excluído!');closeOverlay('overlayDel');setTimeout(()=>location.reload(),500);}
    else toast(d.message||'Erro.','err');
  }finally{btn.disabled=false;btn.textContent='Excluir';}
}

function doLogout(){
  fetch('../api/auth_admin.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'logout'})})
    .finally(()=>{window.location.href='/florescer/index.php';});
}
</script>
</body>
</html>