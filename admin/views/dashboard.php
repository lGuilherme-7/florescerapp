<?php
// ============================================================
// /admin/views/dashboard.php — florescer Admin v3.0
// ============================================================
require_once __DIR__ . '/../includes/auth_admin.php';
require_once dirname(dirname(__DIR__)) . '/config/db.php';

requireAdmin();

$adminName   = $_SESSION['admin_name'] ?? 'Admin';
$adminLetter = strtoupper(mb_substr($adminName, 0, 1, 'UTF-8'));

// ── Helpers ───────────────────────────────────────────────────
function fmtHours(int $min): string {
    if ($min < 60)  return $min . 'min';
    $h = intdiv($min, 60); $r = $min % 60;
    return $r > 0 ? "{$h}h {$r}min" : "{$h}h";
}
function timeAgo(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)    return 'agora';
    if ($diff < 3600)  return intdiv($diff, 60) . 'min atrás';
    if ($diff < 86400) return intdiv($diff, 3600) . 'h atrás';
    if ($diff < 604800) return intdiv($diff, 86400) . 'd atrás';
    return date('d/m/Y', strtotime($dt));
}
function safeCount(string $table, string $where = ''): int {
    $w = $where ? "WHERE $where" : '';
    return (int)(dbRow("SELECT COUNT(*) AS n FROM `$table` $w")['n'] ?? 0);
}
function tableExists(string $table): bool {
    return (bool)dbRow("SHOW TABLES LIKE '$table'");
}

// ── Stats principais ──────────────────────────────────────────
$totalUsers   = safeCount('users');
$newUsersWeek = safeCount('users', 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
$activeToday  = safeCount('daily_summaries', 'study_date = CURDATE()');
$activeWeek   = (int)(dbRow(
    'SELECT COUNT(DISTINCT user_id) AS n FROM daily_summaries
     WHERE study_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND total_min > 0'
)['n'] ?? 0);

$totalMinAll  = (int)(dbRow('SELECT COALESCE(SUM(total_min),0) AS n FROM daily_summaries')['n'] ?? 0);
$totalGoals   = safeCount('daily_summaries', 'goal_reached = 1');
$totalLessons = safeCount('lessons', 'is_completed = 1');
$totalMotiv   = safeCount('motivational_messages');

// Tabelas opcionais
$totalMsgs    = tableExists('chat_messages')   ? safeCount('chat_messages')  : 0;
$totalSimQ    = tableExists('sim_questions')   ? safeCount('sim_questions',  'is_active = 1') : 0;
$totalVest    = tableExists('sim_vestibulares')? safeCount('sim_vestibulares','is_active = 1') : 0;
$totalStore   = tableExists('store_items')     ? safeCount('store_items',    'is_active = 1') : 0;
$totalFeedbacks = tableExists('feedbacks')     ? safeCount('feedbacks') : 0;
$openFeedbacks  = tableExists('feedbacks')     ? safeCount('feedbacks', "status = 'aberto'") : 0;
$totalSessions  = tableExists('study_sessions')? safeCount('study_sessions') : 0;
$totalSimAttempts = tableExists('sim_attempts')? safeCount('sim_attempts') : 0;
$totalAchievements = tableExists('user_achievements') ? safeCount('user_achievements') : 0;

// ── Atividade semanal ─────────────────────────────────────────
$weekActivity = dbQuery(
    'SELECT study_date,
            COUNT(DISTINCT user_id) AS active_users,
            COALESCE(SUM(total_min), 0) AS total_min
     FROM daily_summaries
     WHERE study_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY study_date ORDER BY study_date ASC'
);
$weekMap = array_column($weekActivity, null, 'study_date');
$maxMin  = 1;
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $m = (int)($weekMap[$d]['total_min'] ?? 0);
    if ($m > $maxMin) $maxMin = $m;
}

// ── Crescimento mensal (últimos 6 meses) ──────────────────────
$monthlyGrowth = dbQuery(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
            COUNT(*) AS total
     FROM users
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY month ORDER BY month ASC"
);

// ── Top usuários por streak ───────────────────────────────────
$topUsers = dbQuery(
    'SELECT name, nickname, level, xp, streak, streak_max, avatar_emoji, avatar_initial
     FROM users
     ORDER BY streak DESC, xp DESC LIMIT 8'
);

// ── Últimos cadastros ─────────────────────────────────────────
$recentUsers = dbQuery(
    'SELECT id, name, email, level, xp, streak, created_at, avatar_emoji, avatar_initial
     FROM users ORDER BY created_at DESC LIMIT 6'
);

// ── Feedbacks recentes não respondidos ────────────────────────
$recentFeedbacks = tableExists('feedbacks') ? dbQuery(
    "SELECT f.id, f.type, f.title, f.status, f.created_at,
            u.name AS user_name
     FROM feedbacks f
     LEFT JOIN users u ON u.id = f.user_id
     WHERE f.status = 'aberto'
     ORDER BY f.created_at DESC LIMIT 5"
) : [];

// ── Login failures últimas 24h (audit log) ───────────────────
$failedLogins = (int)(dbRow(
    "SELECT COUNT(*) AS n FROM admin_audit_log
     WHERE event = 'LOGIN_FAIL' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
)['n'] ?? 0);

// ── Média de minutos por usuário ativo ────────────────────────
$avgMinPerUser = $activeWeek > 0
    ? round(array_sum(array_column($weekActivity, 'total_min')) / $activeWeek)
    : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>Dashboard — florescer Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
  /* ══════════════════════════════════════════════════════════
     RESET & TOKENS
  ══════════════════════════════════════════════════════════ */
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

  :root{
    /* Paleta verde escura */
    --ink:    #0b1a12;
    --ink2:   #122019;
    --ink3:   #1a3027;
    --border: rgba(82,183,136,.1);
    --border2:rgba(82,183,136,.06);
    --muted:  rgba(116,198,157,.3);
    --muted2: rgba(116,198,157,.18);
    --leaf:   #52b788;
    --leaf2:  #74c69d;
    --leaf3:  #b7e4c7;
    --gold:   #c9a84c;
    --red:    #e05252;
    --text:   #c8e6d4;
    --text2:  rgba(200,230,212,.55);
    --text3:  rgba(200,230,212,.3);

    /* Tipografia */
    --serif: 'Instrument Serif', Georgia, serif;
    --sans:  'DM Sans', system-ui, sans-serif;

    /* Layout */
    --sw:    220px;
    --hh:    54px;
    --r:     12px;
    --r2:    8px;
    --gap:   1rem;

    /* Sombras */
    --sh1: 0 1px 4px rgba(0,0,0,.2);
    --sh2: 0 4px 20px rgba(0,0,0,.3);

    /* Transição */
    --t: .18s cubic-bezier(.4,0,.2,1);
  }

  html,body{
    height:100%;
    font-family:var(--sans);
    background:var(--ink);
    color:var(--text);
    -webkit-font-smoothing:antialiased;
  }
  body{display:flex}

  ::-webkit-scrollbar{width:3px;height:3px}
  ::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px}

  /* ══════════════════════════════════════════════════════════
     SIDEBAR
  ══════════════════════════════════════════════════════════ */
  .aside{
    width:var(--sw);min-height:100vh;position:fixed;top:0;left:0;
    background:var(--ink2);
    border-right:1px solid var(--border2);
    display:flex;flex-direction:column;z-index:50;
  }

  /* Logo */
  .a-logo{
    padding:1.1rem 1.2rem .9rem;
    border-bottom:1px solid var(--border2);
    display:flex;align-items:center;gap:.6rem;
    flex-shrink:0;
  }
  .a-logo-mark{
    width:32px;height:32px;border-radius:9px;flex-shrink:0;
    background:linear-gradient(135deg, var(--leaf) 0%, #2d6a4f 100%);
    display:flex;align-items:center;justify-content:center;
    font-size:.95rem;box-shadow:0 2px 10px rgba(82,183,136,.25);
  }
  .a-logo-name{
    font-family:var(--serif);font-size:1rem;
    color:var(--leaf3);line-height:1.1;letter-spacing:-.01em;
  }
  .a-logo-tag{
    font-size:.54rem;color:var(--muted);
    text-transform:uppercase;letter-spacing:.12em;
  }

  /* Admin pill */
  .a-who{
    margin:.65rem .8rem;
    background:rgba(82,183,136,.05);
    border:1px solid var(--border);
    border-radius:var(--r2);
    padding:.45rem .65rem;
    display:flex;align-items:center;gap:.5rem;
  }
  .a-av{
    width:24px;height:24px;border-radius:50%;flex-shrink:0;
    background:linear-gradient(135deg,var(--leaf),#2d6a4f);
    display:flex;align-items:center;justify-content:center;
    font-size:.65rem;font-weight:600;color:#fff;
  }
  .a-name{font-size:.72rem;font-weight:500;color:var(--leaf2);line-height:1}
  .a-role{font-size:.57rem;color:var(--muted);margin-top:.08rem}

  /* Nav */
  .a-nav{flex:1;overflow-y:auto;padding:.3rem 0 .5rem}

  .a-grp{
    font-size:.52rem;text-transform:uppercase;letter-spacing:.1em;
    color:var(--muted2);padding:.7rem 1.2rem .2rem;display:block;
  }

  .a-link{
    display:flex;align-items:center;gap:.5rem;
    padding:.38rem 1.2rem;
    font-size:.74rem;color:var(--text3);
    text-decoration:none;
    border-left:2px solid transparent;
    transition:all var(--t);
  }
  .a-link:hover{color:var(--leaf2);background:rgba(82,183,136,.04)}
  .a-link.active{
    color:var(--leaf2);
    background:rgba(82,183,136,.07);
    border-left-color:var(--leaf);
    font-weight:500;
  }
  .a-link-ico{width:.9rem;text-align:center;font-size:.78rem;opacity:.8;flex-shrink:0}

  /* Badge no nav */
  .nav-badge{
    margin-left:auto;
    background:rgba(224,82,82,.15);
    color:#e05252;
    font-size:.55rem;font-weight:600;
    padding:.1rem .35rem;border-radius:20px;
    border:1px solid rgba(224,82,82,.2);
  }

  /* Footer */
  .a-foot{
    padding:.7rem .8rem;
    border-top:1px solid var(--border2);
    flex-shrink:0;
  }
  .a-logout{
    width:100%;display:flex;align-items:center;justify-content:center;gap:.38rem;
    padding:.38rem;border-radius:var(--r2);
    background:none;border:1px solid rgba(224,82,82,.12);
    color:rgba(224,82,82,.4);font-family:var(--sans);font-size:.7rem;
    cursor:pointer;transition:all var(--t);
  }
  .a-logout:hover{background:rgba(224,82,82,.06);color:var(--red)}

  /* ══════════════════════════════════════════════════════════
     TOPBAR
  ══════════════════════════════════════════════════════════ */
  .main{margin-left:var(--sw);flex:1;min-width:0;display:flex;flex-direction:column}

  .topbar{
    height:var(--hh);
    position:sticky;top:0;z-index:40;
    background:rgba(11,26,18,.92);
    backdrop-filter:blur(16px);
    border-bottom:1px solid var(--border2);
    display:flex;align-items:center;justify-content:space-between;
    padding:0 1.5rem;
    flex-shrink:0;
  }
  .tb-left{display:flex;align-items:baseline;gap:.5rem}
  .tb-title{font-family:var(--serif);font-size:1rem;color:var(--leaf3);letter-spacing:-.01em}
  .tb-sub{font-size:.67rem;color:var(--muted)}

  .tb-right{display:flex;align-items:center;gap:.75rem}
  .tb-time{font-size:.67rem;color:var(--text3)}

  .pill{
    display:flex;align-items:center;gap:.28rem;
    background:rgba(82,183,136,.07);
    border:1px solid var(--border);
    border-radius:50px;padding:.2rem .6rem;
    font-size:.63rem;font-weight:500;color:var(--leaf2);
  }
  .pill-dot{
    width:5px;height:5px;border-radius:50%;
    background:var(--leaf);
    animation:pulse 2.5s infinite;
  }
  @keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.8)}}

  /* ══════════════════════════════════════════════════════════
     LAYOUT CONTEÚDO
  ══════════════════════════════════════════════════════════ */
  .page{
    padding:1.25rem 1.5rem;
    display:flex;flex-direction:column;
    gap:var(--gap);
    flex:1;
  }

  /* Linha divisória de seção */
  .section-header{
    display:flex;align-items:center;justify-content:space-between;
    margin-bottom:.5rem;
  }
  .section-label{
    font-size:.6rem;text-transform:uppercase;letter-spacing:.1em;
    color:var(--muted);font-weight:500;
  }
  .section-action{
    font-size:.67rem;color:var(--leaf);text-decoration:none;
    transition:color var(--t);
  }
  .section-action:hover{color:var(--leaf2)}

  /* ══════════════════════════════════════════════════════════
     CARDS DE STAT (linha principal)
  ══════════════════════════════════════════════════════════ */
  .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:.65rem}

  .stat{
    background:var(--ink2);
    border:1px solid var(--border2);
    border-radius:var(--r);
    padding:.9rem 1rem;
    position:relative;overflow:hidden;
    transition:border-color var(--t),transform var(--t);
    cursor:default;
  }
  .stat:hover{border-color:var(--border);transform:translateY(-1px)}

  /* Linha de cor no fundo */
  .stat::before{
    content:'';
    position:absolute;bottom:0;left:0;right:0;height:1px;
    background:var(--stat-color, var(--leaf));
    opacity:.4;
  }

  /* Ícone */
  .stat-icon{
    font-size:.8rem;margin-bottom:.55rem;
    opacity:.6;display:block;
  }

  /* Número principal */
  .stat-val{
    font-family:var(--serif);
    font-size:1.75rem;
    color:var(--leaf3);
    line-height:1;
    letter-spacing:-.03em;
  }
  .stat-val sup{font-size:.7rem;opacity:.6;vertical-align:super}

  .stat-label{
    font-size:.63rem;color:var(--text3);
    text-transform:uppercase;letter-spacing:.06em;
    margin-top:.2rem;
  }

  .stat-sub{
    font-size:.65rem;color:var(--muted);
    margin-top:.35rem;
    padding-top:.35rem;
    border-top:1px solid var(--border2);
  }
  .stat-sub strong{color:var(--leaf2)}

  /* ══════════════════════════════════════════════════════════
     GRID 2 COLUNAS
  ══════════════════════════════════════════════════════════ */
  .row-2{display:grid;grid-template-columns:1fr 1fr;gap:.65rem}
  .row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.65rem}

  /* ══════════════════════════════════════════════════════════
     CARD BASE
  ══════════════════════════════════════════════════════════ */
  .card{
    background:var(--ink2);
    border:1px solid var(--border2);
    border-radius:var(--r);
    overflow:hidden;
  }
  .card-head{
    padding:.65rem 1rem;
    border-bottom:1px solid var(--border2);
    display:flex;align-items:center;justify-content:space-between;
  }
  .card-title{
    font-size:.75rem;font-weight:500;
    color:var(--text2);
    display:flex;align-items:center;gap:.4rem;
  }
  .card-title-ico{opacity:.6;font-size:.8rem}
  .card-badge{
    font-size:.6rem;font-weight:500;
    background:rgba(82,183,136,.08);
    color:var(--leaf2);
    padding:.1rem .4rem;border-radius:20px;
    border:1px solid var(--border);
  }
  .card-link{font-size:.67rem;color:var(--leaf);text-decoration:none;transition:color var(--t)}
  .card-link:hover{color:var(--leaf2)}

  /* ══════════════════════════════════════════════════════════
     BARRAS SEMANAIS
  ══════════════════════════════════════════════════════════ */
  .bars{
    display:flex;align-items:flex-end;gap:.45rem;
    padding:.75rem 1rem .6rem;
    height:96px;
  }
  .bar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:.2rem}
  .bar{
    width:100%;border-radius:3px 3px 0 0;
    background:var(--ink3);
    min-height:3px;
    transition:background var(--t);
    cursor:default;
    position:relative;
  }
  .bar:hover{background:var(--leaf)}
  .bar.today{background:rgba(82,183,136,.4);border:1px solid rgba(82,183,136,.3)}
  .bar.today:hover{background:var(--leaf)}
  .bar-day{font-size:.54rem;color:var(--text3)}
  .bar-day.today{color:var(--leaf);font-weight:600}
  .bar-n{font-size:.54rem;color:var(--muted);min-height:.75rem;line-height:1}

  /* ══════════════════════════════════════════════════════════
     CRESCIMENTO MENSAL (mini sparkline)
  ══════════════════════════════════════════════════════════ */
  .sparkline-wrap{padding:.6rem 1rem .7rem;display:flex;align-items:flex-end;gap:.3rem;height:60px}
  .spark-bar{
    flex:1;border-radius:2px;
    background:rgba(82,183,136,.15);
    min-height:2px;
    position:relative;
    transition:background var(--t);
  }
  .spark-bar:hover{background:rgba(82,183,136,.35)}
  .spark-labels{
    display:flex;justify-content:space-between;
    padding:.1rem 1rem .6rem;
  }
  .spark-lbl{font-size:.53rem;color:var(--text3)}

  /* ══════════════════════════════════════════════════════════
     TOP USUÁRIOS
  ══════════════════════════════════════════════════════════ */
  .top-item{
    display:flex;align-items:center;gap:.6rem;
    padding:.42rem 1rem;
    transition:background var(--t);
  }
  .top-item:hover{background:rgba(82,183,136,.03)}

  .top-rank{
    font-family:var(--serif);
    font-size:.8rem;
    color:var(--text3);
    width:1rem;text-align:center;flex-shrink:0;
  }
  .top-rank.gold{color:var(--gold)}
  .top-rank.silver{color:rgba(200,200,220,.55)}
  .top-rank.bronze{color:rgba(180,130,80,.55)}

  .top-av{
    width:26px;height:26px;border-radius:50%;flex-shrink:0;
    background:var(--ink3);
    border:1px solid var(--border);
    display:flex;align-items:center;justify-content:center;
    font-size:.9rem;line-height:1;
    overflow:hidden;
  }
  .top-av.letter{font-size:.7rem;font-weight:600;color:var(--leaf2)}

  .top-name{
    flex:1;min-width:0;
    font-size:.73rem;font-weight:500;color:var(--text);
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  }
  .top-nick{
    font-size:.62rem;color:var(--text3);
    white-space:nowrap;
  }

  .top-stats{
    display:flex;align-items:center;gap:.4rem;flex-shrink:0;
  }
  .chip{
    font-size:.6rem;font-weight:500;padding:.1rem .38rem;
    border-radius:20px;background:var(--ink3);
    border:1px solid var(--border2);color:var(--text3);
  }
  .chip.streak{color:var(--leaf2);border-color:rgba(82,183,136,.15);background:rgba(82,183,136,.07)}
  .chip.lv{color:var(--muted)}

  /* ══════════════════════════════════════════════════════════
     TABELA ÚLTIMOS USUÁRIOS
  ══════════════════════════════════════════════════════════ */
  .tbl{width:100%;border-collapse:collapse}
  .tbl th{
    font-size:.57rem;text-transform:uppercase;letter-spacing:.07em;
    color:var(--muted2);font-weight:500;
    padding:.4rem 1rem;text-align:left;
    border-bottom:1px solid var(--border2);
  }
  .tbl td{
    padding:.45rem 1rem;font-size:.72rem;
    color:var(--text2);
    border-bottom:1px solid var(--border2);
    transition:background var(--t);
  }
  .tbl tr:last-child td{border-bottom:none}
  .tbl tr:hover td{background:rgba(82,183,136,.025)}
  .td-main{font-weight:500;color:var(--text) !important;line-height:1.2}
  .td-sub{font-size:.63rem;color:var(--text3) !important;margin-top:.1rem}

  /* ══════════════════════════════════════════════════════════
     FEEDBACKS
  ══════════════════════════════════════════════════════════ */
  .fb-item{
    padding:.55rem 1rem;
    border-bottom:1px solid var(--border2);
    display:flex;align-items:flex-start;gap:.6rem;
    transition:background var(--t);
  }
  .fb-item:last-child{border-bottom:none}
  .fb-item:hover{background:rgba(82,183,136,.025)}
  .fb-dot{
    width:6px;height:6px;border-radius:50%;flex-shrink:0;margin-top:.35rem;
  }
  .fb-dot.sugestao{background:rgba(82,183,136,.6)}
  .fb-dot.erro{background:rgba(224,82,82,.6)}
  .fb-dot.elogio{background:rgba(201,168,76,.6)}
  .fb-dot.duvida{background:rgba(100,160,240,.6)}
  .fb-title{font-size:.72rem;font-weight:500;color:var(--text);line-height:1.3}
  .fb-meta{font-size:.62rem;color:var(--text3);margin-top:.1rem}
  .fb-type{
    margin-left:auto;flex-shrink:0;
    font-size:.58rem;padding:.1rem .35rem;border-radius:20px;
    background:var(--ink3);color:var(--text3);border:1px solid var(--border2);
  }

  /* ══════════════════════════════════════════════════════════
     MINI STAT CARDS (linha inferior)
  ══════════════════════════════════════════════════════════ */
  .mini-stat{
    background:var(--ink2);
    border:1px solid var(--border2);
    border-radius:var(--r);
    padding:.75rem 1rem;
    display:flex;align-items:center;gap:.7rem;
    transition:border-color var(--t);
  }
  .mini-stat:hover{border-color:var(--border)}
  .mini-ico{font-size:1.2rem;flex-shrink:0;opacity:.65}
  .mini-val{
    font-family:var(--serif);font-size:1.2rem;
    color:var(--leaf3);line-height:1;
  }
  .mini-lbl{font-size:.6rem;color:var(--text3);margin-top:.1rem}

  /* ══════════════════════════════════════════════════════════
     AVISO DE SEGURANÇA
  ══════════════════════════════════════════════════════════ */
  .sec-alert{
    display:flex;align-items:center;gap:.6rem;
    background:rgba(224,82,82,.05);
    border:1px solid rgba(224,82,82,.12);
    border-radius:var(--r2);
    padding:.55rem .85rem;
    font-size:.7rem;color:rgba(224,130,130,.7);
    margin:.4rem 1rem;
  }

  /* ══════════════════════════════════════════════════════════
     EMPTY STATE
  ══════════════════════════════════════════════════════════ */
  .empty{
    padding:1.2rem;text-align:center;
    font-size:.72rem;color:var(--text3);
  }

  /* ══════════════════════════════════════════════════════════
     TOAST
  ══════════════════════════════════════════════════════════ */
  #toasts{
    position:fixed;bottom:1rem;right:1rem;z-index:999;
    display:flex;flex-direction:column;gap:.35rem;pointer-events:none;
  }
  .toast{
    background:var(--ink2);color:var(--text);
    border:1px solid var(--border);
    border-radius:var(--r2);
    padding:.5rem .85rem;font-size:.72rem;
    display:flex;align-items:center;gap:.4rem;
    animation:slideIn .2s var(--t) both;
    max-width:260px;pointer-events:all;
    box-shadow:var(--sh2);
  }
  .toast.ok{border-left:2px solid var(--leaf)}
  .toast.err{border-left:2px solid var(--red)}
  @keyframes slideIn{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:none}}

  /* ══════════════════════════════════════════════════════════
     RESPONSIVO
  ══════════════════════════════════════════════════════════ */
  @media(max-width:1200px){.stats-row{grid-template-columns:repeat(2,1fr)}}
  @media(max-width:900px){.row-2,.row-3{grid-template-columns:1fr}}
  @media(max-width:720px){
    .main{margin-left:0}
    .stats-row{grid-template-columns:1fr 1fr}
    .page{padding:.9rem}
  }
  </style>
</head>
<body>

<!-- ══════════════════════════════════════════════════════════
     SIDEBAR
══════════════════════════════════════════════════════════ -->
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
    <a class="a-link <?= ($currentPage??'')==='dashboard'?'active':'' ?>" href="dashboard.php">
      <span class="a-link-ico">◈</span>Dashboard
    </a>

    <span class="a-grp">Usuários</span>
    <a class="a-link <?= ($currentPage??'')==='usuarios'?'active':'' ?>" href="usuarios.php">
      <span class="a-link-ico">⊙</span>Usuários
    </a>
    <a class="a-link <?= ($currentPage??'')==='sessoes'?'active':'' ?>" href="sessoes.php">
      <span class="a-link-ico">⊘</span>Sessões
    </a>

    <span class="a-grp">Professores</span>
    <a class="a-link <?= ($currentPage??'')==='professores'?'active':'' ?>" href="professores.php">
      <span class="a-link-ico">👨‍🏫</span>Professores
      <?php
        $profPendentes = 0;
        try {
            $profPendentes = (int)(dbRow(
                "SELECT COUNT(*) AS n FROM teachers WHERE status = 'pendente'"
            )['n'] ?? 0);
        } catch (\Throwable $e) {}
        if ($profPendentes > 0):
      ?>
        <span class="nav-badge"><?= $profPendentes ?></span>
      <?php endif; ?>
    </a>
    <a class="a-link <?= ($currentPage??'')==='saques'?'active':'' ?>" href="professores.php?tab=saques">
      <span class="a-link-ico">💸</span>Saques
      <?php
        $saquesPend = 0;
        try {
            $saquesPend = (int)(dbRow(
                "SELECT COUNT(*) AS n FROM teacher_withdrawals WHERE status = 'solicitado'"
            )['n'] ?? 0);
        } catch (\Throwable $e) {}
        if ($saquesPend > 0):
      ?>
        <span class="nav-badge"><?= $saquesPend ?></span>
      <?php endif; ?>
    </a>

    <span class="a-grp">Conteúdo</span>
    <a class="a-link <?= ($currentPage??'')==='mensagens'?'active':'' ?>" href="mensagens.php">
      <span class="a-link-ico">⊡</span>Frases do dia
    </a>
    <a class="a-link <?= ($currentPage??'')==='simulados'?'active':'' ?>" href="simulados.php">
      <span class="a-link-ico">⊞</span>Simulados
    </a>
    <a class="a-link <?= ($currentPage??'')==='cursos'?'active':'' ?>" href="cursos.php">
      <span class="a-link-ico">⊟</span>Cursos
    </a>

    <span class="a-grp">Sistema</span>
    <a class="a-link <?= ($currentPage??'')==='feedbacks'?'active':'' ?>" href="feedbacks.php">
      <span class="a-link-ico">⊠</span>Feedbacks
      <?php if (($openFeedbacks??0) > 0): ?>
        <span class="nav-badge"><?= $openFeedbacks ?></span>
      <?php endif; ?>
    </a>
    <a class="a-link <?= ($currentPage??'')==='configuracoes'?'active':'' ?>" href="configuracoes.php">
      <span class="a-link-ico">⊛</span>Configurações
    </a>
    <a class="a-link" href="/florescer/public/views/dashboard.php" target="_blank">
      <span class="a-link-ico">↗</span>Ver plataforma
    </a>
    <a class="a-link" href="/florescer/teachers/views/index.php" target="_blank">
      <span class="a-link-ico">↗</span>Área do professor
    </a>
  </nav>
  <div class="a-foot">
    <button class="a-logout" onclick="doLogout()">
      <span>↩</span> Sair
    </button>
  </div>
</aside>

<!-- ══════════════════════════════════════════════════════════
     MAIN
══════════════════════════════════════════════════════════ -->
<div class="main">

  <!-- Topbar -->
  <header class="topbar">
    <div class="tb-left">
      <span class="tb-title">Dashboard</span>
      <span class="tb-sub"><?= date('d/m/Y') ?></span>
    </div>
    <div class="tb-right">
      <?php if ($failedLogins > 0): ?>
        <span class="pill" style="background:rgba(224,82,82,.07);border-color:rgba(224,82,82,.15);color:#e08080">
          <span style="width:5px;height:5px;border-radius:50%;background:#e05252;flex-shrink:0"></span>
          <?= $failedLogins ?> tentativas falhas
        </span>
      <?php endif; ?>
      <span class="tb-time"><?= date('H:i') ?></span>
      <span class="pill">
        <span class="pill-dot"></span>online
      </span>
    </div>
  </header>

  <!-- Conteúdo -->
  <main class="page">

    <!-- ── STATS PRINCIPAIS ─────────────────────────────────── -->
    <div class="stats-row">

      <div class="stat" style="--stat-color:var(--leaf)">
        <span class="stat-icon">🌱</span>
        <div class="stat-val"><?= number_format($totalUsers) ?></div>
        <div class="stat-label">Estudantes</div>
        <div class="stat-sub">
          <strong><?= $newUsersWeek ?></strong> novos esta semana
        </div>
      </div>

      <div class="stat" style="--stat-color:#60a5fa">
        <span class="stat-icon">⏱</span>
        <div class="stat-val"><?= fmtHours($totalMinAll) ?></div>
        <div class="stat-label">Total estudado</div>
        <div class="stat-sub">
          <strong><?= number_format($totalGoals) ?></strong> metas atingidas
        </div>
      </div>

      <div class="stat" style="--stat-color:var(--gold)">
        <span class="stat-icon">📖</span>
        <div class="stat-val"><?= number_format($totalLessons) ?></div>
        <div class="stat-label">Aulas concluídas</div>
        <div class="stat-sub">
          <strong><?= number_format($totalSessions) ?></strong> sessões registradas
        </div>
      </div>

      <div class="stat" style="--stat-color:#a78bfa">
        <span class="stat-icon">🏆</span>
        <div class="stat-val"><?= number_format($totalAchievements) ?></div>
        <div class="stat-label">Conquistas desbloqueadas</div>
        <div class="stat-sub">
          <strong><?= $activeWeek ?></strong> ativos esta semana
        </div>
      </div>

    </div>

    <!-- ── ATIVIDADE SEMANAL + TOP USUÁRIOS ──────────────────── -->
    <div class="row-2">

      <!-- Atividade 7 dias -->
      <div class="card">
        <div class="card-head">
          <span class="card-title">
            <span class="card-title-ico">📈</span>Atividade — últimos 7 dias
          </span>
          <span class="card-badge"><?= $activeToday ?> hoje</span>
        </div>
        <div class="bars">
          <?php for ($i = 6; $i >= 0; $i--):
            $d     = date('Y-m-d', strtotime("-{$i} days"));
            $min   = (int)($weekMap[$d]['total_min']    ?? 0);
            $users = (int)($weekMap[$d]['active_users'] ?? 0);
            $pct   = $maxMin > 0 ? max(4, round($min / $maxMin * 72)) : 4;
            $days  = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
            $dow   = $days[(int)date('w', strtotime($d))];
            $today = ($d === date('Y-m-d'));
          ?>
          <div class="bar-col">
            <div
              class="bar <?= $today ? 'today' : '' ?>"
              style="height:<?= $pct ?>px"
              title="<?= date('d/m', strtotime($d)) ?> · <?= fmtHours($min) ?> · <?= $users ?> usuário(s)"
            ></div>
            <span class="bar-day <?= $today ? 'today' : '' ?>"><?= $dow ?></span>
            <span class="bar-n"><?= $users ?: '' ?></span>
          </div>
          <?php endfor; ?>
        </div>
        <div style="padding:0 1rem .65rem;font-size:.63rem;color:var(--text3)">
          Média: <strong style="color:var(--leaf2)"><?= fmtHours($avgMinPerUser) ?>/usuário</strong>
          na semana
        </div>
      </div>

      <!-- Top streak -->
      <div class="card">
        <div class="card-head">
          <span class="card-title">
            <span class="card-title-ico">🔥</span>Top por sequência
          </span>
          <a class="card-link" href="usuarios.php">ver todos →</a>
        </div>
        <?php if (empty($topUsers)): ?>
          <p class="empty">Nenhum dado ainda.</p>
        <?php else: ?>
          <?php foreach ($topUsers as $i => $u):
            $rankClass = $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : ''));
            $hasEmoji  = !empty($u['avatar_emoji']);
          ?>
          <div class="top-item">
            <span class="top-rank <?= $rankClass ?>"><?= $i + 1 ?></span>
            <div class="top-av <?= $hasEmoji ? '' : 'letter' ?>">
              <?= $hasEmoji
                  ? htmlspecialchars($u['avatar_emoji'], ENT_QUOTES)
                  : strtoupper(mb_substr($u['name'], 0, 1, 'UTF-8')) ?>
            </div>
            <div style="flex:1;min-width:0">
              <div class="top-name"><?= htmlspecialchars($u['name'], ENT_QUOTES) ?></div>
              <?php if (!empty($u['nickname'])): ?>
                <div class="top-nick">@<?= htmlspecialchars($u['nickname'], ENT_QUOTES) ?></div>
              <?php endif; ?>
            </div>
            <div class="top-stats">
              <span class="chip streak">🌱 <?= $u['streak'] ?>d</span>
              <span class="chip lv">Nv.<?= $u['level'] ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>

    <!-- ── CRESCIMENTO + MINI STATS ──────────────────────────── -->
    <div class="row-3">

      <!-- Crescimento mensal -->
      <div class="card">
        <div class="card-head">
          <span class="card-title">
            <span class="card-title-ico">📊</span>Crescimento mensal
          </span>
        </div>
        <?php if (!empty($monthlyGrowth)):
          $maxM = max(array_column($monthlyGrowth, 'total')) ?: 1;
        ?>
        <div class="sparkline-wrap">
          <?php foreach ($monthlyGrowth as $m):
            $h = max(4, round($m['total'] / $maxM * 46));
          ?>
          <div class="spark-bar" style="height:<?= $h ?>px"
               title="<?= htmlspecialchars($m['month'], ENT_QUOTES) ?>: <?= $m['total'] ?> usuários">
          </div>
          <?php endforeach; ?>
        </div>
        <div class="spark-labels">
          <?php foreach ($monthlyGrowth as $m): ?>
            <span class="spark-lbl"><?= date('M', strtotime($m['month'] . '-01')) ?></span>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
          <p class="empty">Sem dados de crescimento.</p>
        <?php endif; ?>
      </div>

      <!-- Simulados -->
      <div class="mini-stat">
        <span class="mini-ico">🧠</span>
        <div>
          <div class="mini-val"><?= number_format($totalSimQ) ?></div>
          <div class="mini-lbl">Questões ativas</div>
          <div style="font-size:.6rem;color:var(--muted);margin-top:.15rem">
            <?= $totalVest ?> vestibular(es) · <?= number_format($totalSimAttempts) ?> tentativas
          </div>
        </div>
      </div>

      <!-- Chat + Loja -->
      <div style="display:flex;flex-direction:column;gap:.65rem">
        <div class="mini-stat">
          <span class="mini-ico">💬</span>
          <div>
            <div class="mini-val"><?= number_format($totalMsgs) ?></div>
            <div class="mini-lbl">Msgs no chat</div>
          </div>
        </div>
        <div class="mini-stat">
          <span class="mini-ico">🎓</span>
          <div>
            <div class="mini-val"><?= $totalStore ?></div>
            <div class="mini-lbl">Cursos na loja · <?= $totalMotiv ?> frases</div>
          </div>
        </div>
      </div>

    </div>

    <!-- ── ÚLTIMOS CADASTROS + FEEDBACKS ABERTOS ─────────────── -->
    <div class="row-2">

      <!-- Últimos usuários -->
      <div class="card">
        <div class="card-head">
          <span class="card-title">
            <span class="card-title-ico">🆕</span>Últimos cadastros
          </span>
          <a class="card-link" href="usuarios.php">ver todos →</a>
        </div>
        <?php if (empty($recentUsers)): ?>
          <p class="empty">Nenhum cadastro ainda.</p>
        <?php else: ?>
        <table class="tbl">
          <thead>
            <tr>
              <th>Usuário</th>
              <th>Nível</th>
              <th>Streak</th>
              <th>Cadastro</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentUsers as $u):
              $hasEmoji = !empty($u['avatar_emoji']);
            ?>
            <tr>
              <td>
                <div class="td-main"><?= htmlspecialchars($u['name'], ENT_QUOTES) ?></div>
                <div class="td-sub"><?= htmlspecialchars($u['email'], ENT_QUOTES) ?></div>
              </td>
              <td><span class="chip lv">Nv.<?= $u['level'] ?></span></td>
              <td><span class="chip streak">🌱 <?= $u['streak'] ?>d</span></td>
              <td style="color:var(--text3);font-size:.67rem"><?= timeAgo($u['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

      <!-- Feedbacks abertos -->
      <div class="card">
        <div class="card-head">
          <span class="card-title">
            <span class="card-title-ico">💡</span>Feedbacks pendentes
          </span>
          <?php if ($openFeedbacks > 0): ?>
            <span class="card-badge" style="background:rgba(224,82,82,.08);color:#e08080;border-color:rgba(224,82,82,.15)">
              <?= $openFeedbacks ?> aberto<?= $openFeedbacks > 1 ? 's' : '' ?>
            </span>
          <?php endif; ?>
        </div>

        <?php if ($failedLogins >= 3): ?>
          <div class="sec-alert">
            ⚠️ <?= $failedLogins ?> tentativas de login com falha nas últimas 24h
          </div>
        <?php endif; ?>

        <?php if (empty($recentFeedbacks)): ?>
          <p class="empty">Nenhum feedback pendente. ✓</p>
        <?php else: ?>
          <?php foreach ($recentFeedbacks as $fb): ?>
          <div class="fb-item">
            <span class="fb-dot <?= htmlspecialchars($fb['type'], ENT_QUOTES) ?>"></span>
            <div style="flex:1;min-width:0">
              <div class="fb-title"><?= htmlspecialchars($fb['title'], ENT_QUOTES) ?></div>
              <div class="fb-meta">
                <?= htmlspecialchars($fb['user_name'] ?? 'Anônimo', ENT_QUOTES) ?>
                · <?= timeAgo($fb['created_at']) ?>
              </div>
            </div>
            <span class="fb-type"><?= htmlspecialchars($fb['type'], ENT_QUOTES) ?></span>
          </div>
          <?php endforeach; ?>
          <div style="padding:.5rem 1rem">
            <a href="feedbacks.php" class="card-link" style="font-size:.68rem">
              Ver todos os feedbacks →
            </a>
          </div>
        <?php endif; ?>
      </div>

    </div>

  </main>
</div>

<div id="toasts"></div>

<script>
// ── Toast ────────────────────────────────────────────────────
function toast(msg, type = 'ok', ms = 3500) {
  const wrap = document.getElementById('toasts');
  const el   = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `<span>${type === 'ok' ? '✓' : '✕'}</span><span>${msg}</span>`;
  wrap.appendChild(el);
  setTimeout(() => {
    el.style.opacity = '0';
    el.style.transition = '.25s';
    setTimeout(() => el.remove(), 260);
  }, ms);
}

// ── Logout ───────────────────────────────────────────────────
function doLogout() {
  fetch('../api/auth_admin.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'logout' })
  }).finally(() => { window.location.href = '/florescer/index.php'; });
}

// ── Tooltip nas barras (title nativo é suficiente) ───────────
// Atualiza relógio da topbar a cada minuto
(function() {
  const el = document.querySelector('.tb-time');
  if (!el) return;
  setInterval(() => {
    const n = new Date();
    el.textContent = n.getHours().toString().padStart(2,'0') + ':' + n.getMinutes().toString().padStart(2,'0');
  }, 30000);
})();
</script>
</body>
</html>