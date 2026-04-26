<?php
// ============================================================
// /public/views/works_calendar.php — Semente v2.0
// Calendário de eventos (provas, trabalhos, atividades...)
// Visual mensal clicável + lista lateral do dia selecionado
// Lógica: dias futuros normais, dias passados com event. marcado
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
$currentPage = 'works_calendar';

// Sidebar
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

// Mês exibido (GET param ou atual)
$today    = date('Y-m-d');
$todayY   = (int)date('Y');
$todayM   = (int)date('n');
$viewYear = (int)($_GET['y'] ?? $todayY);
$viewMon  = (int)($_GET['m'] ?? $todayM);
$viewMon  = max(1, min(12, $viewMon));

// Navega: mês anterior / próximo
$prevM = $viewMon - 1; $prevY = $viewYear;
if ($prevM < 1) { $prevM = 12; $prevY--; }
$nextM = $viewMon + 1; $nextY = $viewYear;
if ($nextM > 12) { $nextM = 1; $nextY++; }

// Primeiro e último dia do mês
$firstDay  = mktime(0,0,0,$viewMon,1,$viewYear);
$lastDay   = mktime(0,0,0,$viewMon+1,0,$viewYear);
$daysInMon = (int)date('j',$lastDay);
$startWday = (int)date('N',$firstDay); // 1=seg...7=dom → ISO
// Ajusta para semana começar no domingo (0=dom)
$startWday = $startWday % 7; // dom=0, seg=1...sáb=6

// Eventos do mês via PHP (direto no banco)
$tableOk = (bool)dbRow("SHOW TABLES LIKE 'calendar_events'");
$eventsByDate = [];
$monthLabel   = '';
$MONTHS_PT    = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                 'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$monthLabel   = $MONTHS_PT[$viewMon] . ' ' . $viewYear;

if ($tableOk) {
    $start = sprintf('%04d-%02d-01', $viewYear, $viewMon);
    $end   = sprintf('%04d-%02d-%02d', $viewYear, $viewMon, $daysInMon);
    $evs   = dbQuery(
        'SELECT e.id, e.title, e.event_date, e.event_type, e.is_done,
                e.description, e.subject_id,
                s.name AS subject_name, s.color AS subject_color
         FROM calendar_events e
         LEFT JOIN subjects s ON s.id = e.subject_id
         WHERE e.user_id = ? AND e.event_date BETWEEN ? AND ?
         ORDER BY e.event_date ASC, e.id ASC',
        [$userId, $start, $end]
    );
    foreach ($evs as $ev) {
        $eventsByDate[$ev['event_date']][] = $ev;
    }
}

// Matérias do objetivo ativo (para o modal de criar evento)
$subjects = [];
if ($activeObjId) {
    $subjects = dbQuery(
        'SELECT id, name, color FROM subjects WHERE objective_id=? AND is_active=1 ORDER BY name ASC',
        [$activeObjId]
    );
}

// Cores por tipo de evento
$TYPE_COLOR = [
    'prova'     => '#dc2626',
    'trabalho'  => '#2563eb',
    'atividade' => '#d97706',
    'teste'     => '#7c3aed',
    'outro'     => '#6b7280',
];
$TYPE_LABEL = [
    'prova'     => 'Prova',
    'trabalho'  => 'Trabalho',
    'atividade' => 'Atividade',
    'teste'     => 'Teste',
    'outro'     => 'Outro',
];
$TYPE_ICO = [
    'prova'=>'📝','trabalho'=>'📋','atividade'=>'✏️','teste'=>'🧪','outro'=>'📌'
];

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
  <title>Semente — Calendário</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700;9..144,900&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
  <style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{
    --g950:#0d1f16;--g800:#1a3a2a;--g700:#1e4d35;--g600:#2d6a4f;
    --g500:#40916c;--g400:#52b788;--g300:#74c69d;--g200:#b7e4c7;--g50:#f0faf4;
    --n800:#1c1c1a;--n100:#f5f2ee;--n50:#faf8f5;--white:#fff;
    --gold:#c9a84c;--red:#dc2626;--red-l:#fee2e2;--blue:#2563eb;--blue-l:#dbeafe;
    --violet:#7c3aed;--violet-l:#ede9fe;--amber:#d97706;--amber-l:#fef3c7;
    --sw:240px;--hh:58px;
    --fd:'Fraunces',Georgia,serif;--fb:'Inter',system-ui,sans-serif;
    --r:14px;--rs:8px;
    --d:.22s;--e:cubic-bezier(.4,0,.2,1);
    --sh0:0 1px 3px rgba(0,0,0,.06);--sh1:0 2px 8px rgba(0,0,0,.07);
    --sh2:0 4px 16px rgba(0,0,0,.09);--sh3:0 12px 32px rgba(0,0,0,.12);
  }
  html,body{height:100%}
  body{font-family:var(--fb);background:var(--n50);color:var(--n800);display:flex;overflow-x:hidden;-webkit-font-smoothing:antialiased}
  ::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:rgba(64,145,108,.2);border-radius:2px}
 
  /* ── Sidebar ─────────────────────────────────────────────── */
  .sidebar{width:var(--sw);height:100vh;position:fixed;top:0;left:0;background:var(--g800);display:flex;flex-direction:column;z-index:50;overflow:hidden;transition:transform var(--d) var(--e);border-right:1px solid rgba(116,198,157,.08)}
  .sidebar::after{content:'';position:absolute;top:-60px;right:-60px;width:200px;height:200px;border-radius:50%;background:radial-gradient(circle,rgba(116,198,157,.05) 0%,transparent 70%);pointer-events:none}
  .sb-logo{padding:.95rem 1.1rem;border-bottom:1px solid rgba(116,198,157,.08);display:flex;align-items:center;gap:.5rem;flex-shrink:0}
  .sb-logo-icon{width:28px;height:28px;background:linear-gradient(135deg,var(--g500),var(--g700));border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.88rem;box-shadow:0 2px 8px rgba(64,145,108,.22)}
  .sb-logo-name{font-family:var(--fd);font-size:1.05rem;font-weight:700;color:var(--g200);letter-spacing:-.02em;line-height:1}
  .sb-logo-sub{font-size:.56rem;color:rgba(116,198,157,.3);text-transform:uppercase;letter-spacing:.1em;margin-top:.08rem}
  .sb-profile{padding:.72rem 1.1rem;border-bottom:1px solid rgba(116,198,157,.08);display:flex;align-items:center;gap:.6rem;text-decoration:none;flex-shrink:0;transition:background var(--d) var(--e)}
  .sb-profile:hover{background:rgba(116,198,157,.04)}
  .sb-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--g500),var(--g600));display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-size:.86rem;font-weight:700;color:var(--white);flex-shrink:0;overflow:hidden;box-shadow:0 0 0 2px rgba(116,198,157,.18)}
  .sb-pname{font-size:.82rem;font-weight:500;color:var(--g100);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .sb-plevel{font-size:.68rem;color:var(--g300);margin-top:.06rem;opacity:.7}
  .sb-plant{padding:.6rem 1.1rem;border-bottom:1px solid rgba(116,198,157,.08);flex-shrink:0}
  .sb-plant-row{display:flex;align-items:center;gap:.5rem}
  .sb-pemoji{font-size:1.25rem;animation:breathe 4s ease-in-out infinite;flex-shrink:0}
  @keyframes breathe{0%,100%{transform:scale(1)}50%{transform:scale(1.07) translateY(-1px)}}
  .sb-pname2{font-size:.7rem;font-weight:600;color:var(--g300)}
  .sb-pstreak{font-size:.64rem;color:rgba(116,198,157,.4);margin-top:.06rem}
  .sb-pbar{height:2px;background:rgba(116,198,157,.1);border-radius:1px;margin-top:.28rem;overflow:hidden}
  .sb-pbar-fill{height:100%;background:linear-gradient(90deg,var(--g400),var(--g200));transition:width .6s var(--e)}
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
  .sb-logout{display:flex;align-items:center;gap:.4rem;width:100%;padding:.44rem .7rem;background:none;border:1px solid rgba(220,100,100,.13);border-radius:var(--rs);color:rgba(220,100,100,.52);font-family:var(--fb);font-size:.77rem;cursor:pointer;transition:all var(--d) var(--e)}
  .sb-logout:hover{background:rgba(220,38,38,.07);color:#e07070}
  .sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);z-index:49;opacity:0;transition:opacity var(--d) var(--e)}
  .sb-overlay.show{opacity:1}
 
  /* ── Main ────────────────────────────────────────────────── */
  .main{margin-left:var(--sw);flex:1;min-height:100vh;display:flex;flex-direction:column;min-width:0}
  .topbar{height:var(--hh);background:rgba(250,248,245,.92);backdrop-filter:blur(14px);border-bottom:1px solid rgba(0,0,0,.055);display:flex;align-items:center;justify-content:space-between;padding:0 1.8rem;position:sticky;top:0;z-index:40;flex-shrink:0}
  .tb-left{display:flex;align-items:center;gap:.8rem}
  .hamburger{display:none;flex-direction:column;gap:4px;cursor:pointer;background:none;border:none;padding:5px;border-radius:6px}
  .hamburger span{display:block;width:19px;height:2px;background:var(--g600);border-radius:1px;transition:all var(--d) var(--e)}
  .hamburger.open span:nth-child(1){transform:translateY(6px) rotate(45deg)}
  .hamburger.open span:nth-child(2){opacity:0}
  .hamburger.open span:nth-child(3){transform:translateY(-6px) rotate(-45deg)}
  .tb-title{font-family:var(--fd);font-size:1rem;font-weight:600;color:var(--n800)}
  .xp-pill{display:flex;align-items:center;gap:.28rem;background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:50px;padding:.26rem .75rem;font-size:.75rem;font-weight:600;color:var(--g500);box-shadow:var(--sh0)}
 
  /* ── Layout calendário + painel lateral ──────────────────── */
  .content{flex:1;padding:1.5rem 1.8rem;display:grid;grid-template-columns:1fr 310px;gap:1.4rem;align-items:start}
 
  /* ── Calendário ─────────────────────────────────────────── */
  .cal-card{background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r);box-shadow:var(--sh1);overflow:hidden}
 
  .cal-header{padding:1rem 1.3rem;border-bottom:1px solid rgba(0,0,0,.05);display:flex;align-items:center;justify-content:space-between}
  .cal-month{font-family:var(--fd);font-size:1.18rem;font-weight:700;color:var(--n800);letter-spacing:-.02em}
  .cal-nav{display:flex;align-items:center;gap:.4rem}
  .cal-nav-btn{width:32px;height:32px;border-radius:50%;border:1px solid rgba(0,0,0,.1);background:var(--white);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.85rem;color:#888;transition:all var(--d) var(--e);text-decoration:none}
  .cal-nav-btn:hover{background:var(--n100);color:var(--n800)}
  .cal-today-btn{padding:.32rem .88rem;border-radius:50px;border:1px solid rgba(0,0,0,.1);background:var(--white);cursor:pointer;font-family:var(--fb);font-size:.73rem;font-weight:500;color:#888;transition:all var(--d) var(--e);text-decoration:none}
  .cal-today-btn:hover{background:var(--n100);color:var(--n800)}
 
  /* Grid dos dias */
  .cal-grid{display:grid;grid-template-columns:repeat(7,1fr)}
 
  /* Cabeçalho dos dias da semana */
  .cal-dow{
    text-align:center;
    padding:.6rem 0;
    font-size:.65rem;
    font-weight:600;
    color:#c0bbb5;
    text-transform:uppercase;
    letter-spacing:.07em;
    border-bottom:1px solid rgba(0,0,0,.05);
    background:rgba(0,0,0,.012);
  }
  .cal-dow.weekend{color:#d5d0ca}
 
  /* Células de dia */
  .cal-day{
    min-height:88px;
    padding:.55rem .5rem .45rem;
    border-right:1px solid rgba(0,0,0,.04);
    border-bottom:1px solid rgba(0,0,0,.04);
    cursor:pointer;
    transition:background var(--d) var(--e);
    position:relative;
    display:flex;
    flex-direction:column;
    align-items:center; /* número centralizado horizontalmente */
  }
  .cal-day:nth-child(7n){border-right:none}
  .cal-day:hover{background:var(--g50)}
  .cal-day.selected{background:var(--g50)!important;box-shadow:inset 0 0 0 1.5px var(--g400)}
  .cal-day.other-month{background:rgba(0,0,0,.012);pointer-events:none}
  .cal-day.other-month .cal-day-num{color:#ddd}
 
  /* Número do dia — centralizado */
  .cal-day-num{
    width:26px;
    height:26px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:.8rem;
    font-weight:500;
    color:var(--n800);
    border-radius:50%;
    margin-bottom:.3rem;
    flex-shrink:0;
    transition:background var(--d) var(--e), color var(--d) var(--e);
  }
 
  /* Hoje: círculo verde */
  .cal-day.today .cal-day-num{
    background:var(--g500);
    color:var(--white);
    font-weight:700;
  }
 
  /* Passado: levemente apagado */
  .cal-day.past{opacity:.62}
 
  /* Final de semana: número mais claro */
  .cal-day.weekend .cal-day-num{color:#b0a8a0}
 
  /* Pills de evento dentro da célula — largura completa */
  .cal-dots{display:flex;flex-wrap:wrap;gap:2px;margin-top:.2rem;width:100%}
  .cal-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0}
 
  .cal-event-pill{
    display:flex;
    align-items:center;
    gap:.22rem;
    padding:.13rem .36rem;
    border-radius:5px;
    font-size:.59rem;
    font-weight:600;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    max-width:100%;
    width:100%;
    margin-bottom:2px;
    opacity:.92;
    transition:opacity var(--d) var(--e);
  }
  .cal-event-pill.done{opacity:.38;text-decoration:line-through}
  .cal-more{font-size:.6rem;color:#bbb;margin-top:2px;width:100%;text-align:center}
 
  /* ── Painel lateral do dia ───────────────────────────────── */
  .day-panel{
    background:var(--white);
    border:1px solid rgba(0,0,0,.06);
    border-radius:var(--r);
    box-shadow:var(--sh1);
    overflow:hidden;
    position:sticky;
    top:calc(var(--hh) + 1.5rem);
  }
 
  .day-panel-head{
    padding:.95rem 1.15rem;
    border-bottom:1px solid rgba(0,0,0,.05);
    display:flex;
    align-items:center;
    justify-content:space-between;
    background:var(--white);
  }
  .day-panel-date{font-family:var(--fd);font-size:.97rem;font-weight:700;color:var(--n800)}
  .day-panel-sub{font-size:.7rem;color:#bbb;margin-top:.1rem}
  .btn-add-event{
    display:inline-flex;
    align-items:center;
    gap:.28rem;
    padding:.4rem .88rem;
    background:linear-gradient(135deg,var(--g500),var(--g600));
    color:var(--white);
    border:none;
    border-radius:50px;
    font-family:var(--fb);
    font-size:.74rem;
    font-weight:600;
    cursor:pointer;
    transition:all var(--d) var(--e);
    box-shadow:0 2px 8px rgba(64,145,108,.22);
    white-space:nowrap;
  }
  .btn-add-event:hover{transform:translateY(-1px);box-shadow:0 3px 12px rgba(64,145,108,.32)}
 
  .day-panel-body{padding:.8rem 1rem;max-height:calc(100vh - var(--hh) - 200px);overflow-y:auto}
  .day-panel-body::-webkit-scrollbar{width:3px}
 
  .day-empty{text-align:center;padding:2.2rem .5rem;color:#ccc}
  .day-empty-ico{font-size:1.9rem;opacity:.28;display:block;margin-bottom:.6rem}
  .day-empty p{font-size:.78rem;line-height:1.65;color:#c5c0b8}
 
  /* Evento no painel lateral */
  .ev-item{
    background:var(--n50);
    border-radius:var(--rs);
    padding:.7rem .85rem;
    margin-bottom:.5rem;
    border-left:3px solid;
    position:relative;
    transition:all var(--d) var(--e);
  }
  .ev-item:hover{box-shadow:var(--sh1);transform:translateY(-1px)}
  .ev-item.done{opacity:.48}
  .ev-item-head{display:flex;align-items:flex-start;justify-content:space-between;gap:.4rem}
  .ev-type-badge{
    font-size:.59rem;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.07em;
    padding:.1rem .42rem;
    border-radius:20px;
    background:rgba(0,0,0,.06);
    color:#999;
    flex-shrink:0;
  }
  .ev-title{font-size:.84rem;font-weight:600;color:var(--n800);line-height:1.4;flex:1}
  .ev-item.done .ev-title{text-decoration:line-through;color:#bbb}
  .ev-subject{font-size:.69rem;color:#b0a8a0;margin-top:.2rem;display:flex;align-items:center;gap:.25rem}
  .ev-subject-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0}
  .ev-desc{font-size:.74rem;color:#aaa;margin-top:.3rem;line-height:1.5;border-top:1px solid rgba(0,0,0,.05);padding-top:.3rem}
  .ev-actions{display:flex;gap:.3rem;margin-top:.5rem;justify-content:flex-end}
  .ev-btn{
    display:inline-flex;
    align-items:center;
    gap:.2rem;
    padding:.24rem .58rem;
    border-radius:20px;
    border:1px solid rgba(0,0,0,.1);
    background:var(--white);
    font-family:var(--fb);
    font-size:.68rem;
    font-weight:500;
    color:#999;
    cursor:pointer;
    transition:all var(--d) var(--e);
  }
  .ev-btn:hover{background:var(--n100);color:var(--n800)}
  .ev-btn.done-btn{border-color:rgba(64,145,108,.22);color:var(--g500)}
  .ev-btn.done-btn:hover{background:var(--g50)}
  .ev-btn.done-btn:disabled{opacity:.4;cursor:default;pointer-events:none}
  .ev-btn.del-btn{border-color:rgba(220,38,38,.15);color:var(--red)}
  .ev-btn.del-btn:hover{background:var(--red-l)}
 
  /* ── Modal criar/editar ──────────────────────────────────── */
  .modal-overlay{position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.45);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;padding:1.2rem;opacity:0;pointer-events:none;transition:opacity var(--d) var(--e)}
  .modal-overlay.open{opacity:1;pointer-events:all}
  .modal{background:var(--white);border:1px solid rgba(0,0,0,.08);border-radius:var(--r);width:100%;max-width:440px;max-height:90vh;overflow-y:auto;box-shadow:var(--sh3);transform:translateY(14px) scale(.97);transition:transform var(--d) var(--e)}
  .modal-overlay.open .modal{transform:translateY(0) scale(1)}
  .modal-head{padding:1rem 1.2rem;border-bottom:1px solid rgba(0,0,0,.06);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--white);z-index:1}
  .modal-title{font-family:var(--fd);font-size:.95rem;font-weight:700;color:var(--n800)}
  .modal-x{width:26px;height:26px;border-radius:50%;background:var(--n100);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.78rem;color:#aaa;transition:all var(--d) var(--e)}
  .modal-x:hover{background:var(--red-l);color:var(--red)}
  .modal-body{padding:1.2rem}
  .modal-foot{padding:.9rem 1.2rem;border-top:1px solid rgba(0,0,0,.06);display:flex;gap:.4rem;justify-content:flex-end;background:var(--white)}
  .fg{margin-bottom:.88rem}
  .fl{display:block;font-size:.74rem;font-weight:500;color:#777;margin-bottom:.3rem}
  .fc{width:100%;padding:.58rem .85rem;background:var(--n50);border:1px solid rgba(0,0,0,.09);border-radius:var(--rs);color:var(--n800);font-family:var(--fb);font-size:.83rem;outline:none;transition:all var(--d) var(--e);appearance:none}
  .fc:focus{border-color:var(--g400);background:var(--white);box-shadow:0 0 0 3px rgba(64,145,108,.1)}
  .fc::placeholder{color:#ccc}
  .frow{display:grid;grid-template-columns:1fr 1fr;gap:.7rem}
  .f-alert{padding:.52rem .78rem;border-radius:var(--rs);font-size:.78rem;margin-bottom:.75rem;display:none}
  .f-alert.show{display:block}
  .f-alert.err{background:var(--red-l);border:1px solid rgba(220,38,38,.2);color:var(--red)}
 
  /* Tipo de evento — seletor visual */
  .type-picker{display:flex;flex-wrap:wrap;gap:.35rem}
  .type-opt{padding:.32rem .72rem;border-radius:50px;border:1.5px solid rgba(0,0,0,.1);background:var(--white);font-family:var(--fb);font-size:.74rem;font-weight:500;color:#888;cursor:pointer;transition:all var(--d) var(--e)}
  .type-opt:hover{border-color:rgba(64,145,108,.3);color:var(--g500)}
  .type-opt.selected{color:var(--white);border-color:transparent;font-weight:600}
 
  /* Botões */
  .btn-primary{display:inline-flex;align-items:center;gap:.35rem;padding:.54rem 1.15rem;background:linear-gradient(135deg,var(--g500),var(--g600));color:var(--white);border:none;border-radius:50px;font-family:var(--fb);font-size:.8rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);box-shadow:0 3px 10px rgba(64,145,108,.25)}
  .btn-primary:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(64,145,108,.35)}
  .btn-ghost{display:inline-flex;align-items:center;gap:.35rem;padding:.54rem 1.15rem;background:var(--white);border:1px solid rgba(0,0,0,.1);border-radius:50px;font-family:var(--fb);font-size:.8rem;font-weight:500;color:#888;cursor:pointer;transition:all var(--d) var(--e)}
  .btn-ghost:hover{background:var(--n100);color:var(--n800)}
 
  /* ── Toast ───────────────────────────────────────────────── */
  .toast-wrap{position:fixed;bottom:1.4rem;right:1.4rem;z-index:500;display:flex;flex-direction:column;gap:.4rem;pointer-events:none}
  .toast{background:var(--n800);color:#eee;padding:.64rem .98rem;border-radius:var(--rs);font-size:.79rem;display:flex;align-items:center;gap:.4rem;animation:tin .24s var(--e) both;max-width:270px;box-shadow:var(--sh3);pointer-events:all}
  .toast.ok{border-left:3px solid var(--g400)}.toast.err{border-left:3px solid #f87171}.toast.info{border-left:3px solid var(--gold)}
  @keyframes tin{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}
 
  /* ── Responsivo ─────────────────────────────────────────── */
  @media(max-width:900px){.content{grid-template-columns:1fr}.day-panel{position:static}}
  @media(max-width:768px){
    .sidebar{transform:translateX(-100%)}.sidebar.open{transform:translateX(0)}
    .sb-overlay{display:block}.main{margin-left:0}.hamburger{display:flex}
    .topbar{padding:0 1.1rem}.content{padding:1rem}
    .cal-day{min-height:64px}.frow{grid-template-columns:1fr}
  }
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

<!-- Main -->
<div class="main">
  <header class="topbar">
    <div class="tb-left">
      <button class="hamburger" id="hamburger" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
      <span class="tb-title">📅 Calendário</span>
    </div>
    <div class="xp-pill">⭐ <?= number_format($xp) ?> XP</div>
  </header>

  <div class="content">

    <!-- ── Calendário ─────────────────────────────────────── -->
    <div class="cal-card">
      <div class="cal-header">
        <div>
          <div class="cal-month"><?= $monthLabel ?></div>
        </div>
        <div class="cal-nav">
          <a class="cal-nav-btn" href="?y=<?= $prevY ?>&m=<?= $prevM ?>">‹</a>
          <a class="cal-today-btn" href="?y=<?= $todayY ?>&m=<?= $todayM ?>">Hoje</a>
          <a class="cal-nav-btn" href="?y=<?= $nextY ?>&m=<?= $nextM ?>">›</a>
        </div>
      </div>

      <!-- Cabeçalho dos dias da semana -->
      <div class="cal-grid">
        <?php
        $DOWS = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
        foreach ($DOWS as $i => $d) {
            $cls = ($i===0||$i===6) ? 'cal-dow weekend' : 'cal-dow';
            echo "<div class=\"$cls\">$d</div>";
        }
        ?>

        <?php
        // Células vazias antes do dia 1
        for ($i = 0; $i < $startWday; $i++) {
            echo '<div class="cal-day other-month"></div>';
        }

        // Dias do mês
        for ($d = 1; $d <= $daysInMon; $d++) {
            $dateStr  = sprintf('%04d-%02d-%02d', $viewYear, $viewMon, $d);
            $isToday  = ($dateStr === $today);
            $isPast   = ($dateStr < $today);
            $isFuture = ($dateStr > $today);
            $wday     = (int)date('w', mktime(0,0,0,$viewMon,$d,$viewYear)); // 0=dom
            $isWeekend= ($wday===0 || $wday===6);

            $classes = ['cal-day'];
            if ($isToday)    $classes[] = 'today';
            if ($isPast)     $classes[] = 'past';
            if ($isWeekend)  $classes[] = 'weekend';

            $evList = $eventsByDate[$dateStr] ?? [];
            $show   = array_slice($evList, 0, 3);
            $extra  = count($evList) - count($show);

            $evHTML = '';
            foreach ($show as $ev) {
                $tc  = $TYPE_COLOR[$ev['event_type']] ?? '#6b7280';
                $lbl = htmlspecialchars(mb_substr($ev['title'],0,18,'UTF-8'),ENT_QUOTES);
                $done = $ev['is_done'] ? ' done' : '';
                $evHTML .= "<div class=\"cal-event-pill{$done}\" style=\"background:{$tc}22;color:{$tc};border:1px solid {$tc}33\">"
                         . htmlspecialchars($TYPE_ICO[$ev['event_type']]??'📌',ENT_QUOTES)
                         . " $lbl</div>";
            }
            if ($extra > 0) $evHTML .= "<div class=\"cal-more\">+$extra mais</div>";

            $cls = implode(' ', $classes);
            echo "<div class=\"$cls\" data-date=\"$dateStr\" onclick=\"selectDay('$dateStr')\">"
               . "<div class=\"cal-day-num\">$d</div>"
               . $evHTML
               . '</div>';
        }

        // Células restantes
        $total = $startWday + $daysInMon;
        $rem   = (7 - ($total % 7)) % 7;
        for ($i = 0; $i < $rem; $i++) {
            echo '<div class="cal-day other-month"></div>';
        }
        ?>
      </div><!-- /cal-grid -->
    </div><!-- /cal-card -->

    <!-- ── Painel lateral do dia ──────────────────────────── -->
    <div class="day-panel" id="dayPanel">
      <div class="day-panel-head">
        <div>
          <div class="day-panel-date" id="panelDate">Selecione um dia</div>
          <div class="day-panel-sub" id="panelSub">clique em qualquer data</div>
        </div>
        <button class="btn-add-event" id="btnAddEvent" onclick="openCreateModal()" style="display:none">
          + Evento
        </button>
      </div>
      <div class="day-panel-body" id="panelBody">
        <div class="day-empty">
          <span class="day-empty-ico">🗓️</span>
          <p>Selecione um dia no calendário para ver ou adicionar eventos.</p>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<!-- Modal criar/editar evento -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title" id="modalTitle">Novo evento</span>
      <button class="modal-x" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body">
      <div class="f-alert" id="mAlert"></div>
      <input type="hidden" id="mId"/>
      <input type="hidden" id="mDate"/>

      <div class="fg">
        <label class="fl">Tipo de evento</label>
        <div class="type-picker" id="typePicker">
          <?php foreach ($TYPE_LABEL as $k => $lbl): ?>
            <div class="type-opt" data-type="<?= $k ?>"
                 style="--tc:<?= $TYPE_COLOR[$k] ?>"
                 onclick="selectType('<?= $k ?>',this)">
              <?= $TYPE_ICO[$k] ?> <?= $lbl ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="fg">
        <label class="fl">Título *</label>
        <input class="fc" id="mTitle" type="text" maxlength="200" placeholder="Ex: Prova de Matemática"/>
      </div>

      <div class="frow">
        <div class="fg">
          <label class="fl">Data *</label>
          <input class="fc" id="mDateInput" type="date"/>
        </div>
        <div class="fg">
          <label class="fl">Matéria (opcional)</label>
          <select class="fc" id="mSubject">
            <option value="">Nenhuma</option>
            <?php foreach ($subjects as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['name'],ENT_QUOTES) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="fg">
        <label class="fl">Descrição (opcional)</label>
        <textarea class="fc" id="mDesc" rows="3" placeholder="Capítulos cobrados, instruções..."></textarea>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-ghost" onclick="closeModal()">Cancelar</button>
      <button class="btn-primary" onclick="submitEvent()">Salvar evento</button>
    </div>
  </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
// ════════════════════════════════════════════════════════════
// works_calendar.js inline — Semente v2.0
// ════════════════════════════════════════════════════════════
const API      = '/florescer/api/works_calendar.php';
const TODAY    = <?= json_encode($today) ?>;
const VIEW_Y   = <?= $viewYear ?>;
const VIEW_M   = <?= $viewMon ?>;

// Dados do mês já carregados pelo PHP
const EVENTS_BY_DATE = <?= json_encode($eventsByDate, JSON_UNESCAPED_UNICODE) ?>;
const SUBJS          = <?= json_encode(array_values($subjects), JSON_UNESCAPED_UNICODE) ?>;
const TYPE_COLOR     = <?= json_encode($TYPE_COLOR, JSON_UNESCAPED_UNICODE) ?>;
const TYPE_LABEL     = <?= json_encode($TYPE_LABEL, JSON_UNESCAPED_UNICODE) ?>;
const TYPE_ICO       = <?= json_encode($TYPE_ICO, JSON_UNESCAPED_UNICODE) ?>;

const DAYS_PT  = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
const MONTHS_PT= ['','janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];

// ── Sidebar ──────────────────────────────────────────────────
function toggleSidebar(){
  const sb=document.getElementById('sidebar'),ov=document.getElementById('sbOverlay'),hb=document.getElementById('hamburger');
  const open=sb.classList.toggle('open');
  ov.classList.toggle('show',open);hb.classList.toggle('open',open);
  document.body.style.overflow=open?'hidden':'';
}
function closeSidebar(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sbOverlay').classList.remove('show');
  document.getElementById('hamburger').classList.remove('open');
  document.body.style.overflow='';
}

// ── Toast ─────────────────────────────────────────────────────
function toast(msg,type='ok',ms=3000){
  const w=document.getElementById('toastWrap'),d=document.createElement('div');
  d.className=`toast ${type}`;
  d.innerHTML=`<span>${type==='ok'?'✅':type==='err'?'❌':'💡'}</span><span>${msg}</span>`;
  w.appendChild(d);setTimeout(()=>{d.style.opacity='0';setTimeout(()=>d.remove(),300);},ms);
}

// ── API ───────────────────────────────────────────────────────
async function api(body){
  const r=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  return r.json();
}

// ── Escape ────────────────────────────────────────────────────
function esc(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}

// ════════════════════════════════════════════════════════════
// DIA SELECIONADO
// ════════════════════════════════════════════════════════════
let selectedDate = null;

function selectDay(dateStr){
  if (!dateStr) return;

  selectedDate = dateStr;

  document.querySelectorAll('.cal-day').forEach(d => {
    d.classList.toggle('selected', d.dataset.date === dateStr);
  });

  const dt    = new Date(dateStr + 'T12:00:00');
  const wday  = dt.getDay();
  const dom   = dt.getDate();
  const month = MONTHS_PT[dt.getMonth()+1];
  const year  = dt.getFullYear();
  const isPast= dateStr < TODAY;
  const isToday = dateStr === TODAY;

  document.getElementById('panelDate').textContent =
    `${dom} de ${month}${year !== VIEW_Y ? ' de '+year : ''}`;
  document.getElementById('panelSub').textContent =
    isToday ? 'Hoje' : (isPast ? DAYS_PT[wday] + ' (passado)' : DAYS_PT[wday]);

  document.getElementById('btnAddEvent').style.display = '';

  renderDayPanel(dateStr);
}

function renderDayPanel(dateStr){
  const body  = document.getElementById('panelBody');
  const evs   = EVENTS_BY_DATE[dateStr] || [];
  const isPast = dateStr < TODAY;

  if (!evs.length) {
    body.innerHTML = `
      <div class="day-empty">
        <span class="day-empty-ico">📭</span>
        <p>${isPast ? 'Nenhum evento registrado neste dia.' : 'Nenhum evento agendado.<br>Clique em "+ Evento" para adicionar.'}</p>
      </div>`;
    return;
  }

  body.innerHTML = evs.map(ev => buildEvItem(ev, isPast)).join('');
}

function buildEvItem(ev, isPast){
  const tc   = TYPE_COLOR[ev.event_type] || '#6b7280';
  const ico  = TYPE_ICO[ev.event_type]  || '📌';
  const lbl  = TYPE_LABEL[ev.event_type]|| ev.event_type;
  const done = ev.is_done ? ' done' : '';

  const subjectHTML = (ev.subject_name)
    ? `<div class="ev-subject"><span class="ev-subject-dot" style="background:${esc(ev.subject_color||tc)}"></span>${esc(ev.subject_name)}</div>`
    : '';

  const descHTML = ev.description
    ? `<div class="ev-desc">${esc(ev.description)}</div>`
    : '';

  const doneBtn = ev.is_done
    ? `<button class="ev-btn done-btn" disabled style="opacity:.4;cursor:default">✓ Concluído</button>`
    : `<button class="ev-btn done-btn" onclick="toggleDone(${ev.id})">✓ Concluído</button>`;

  return `
    <div class="ev-item${done}" id="evitem-${ev.id}" style="border-left-color:${tc}">
      <div class="ev-item-head">
        <div style="flex:1;min-width:0">
          <span class="ev-type-badge" style="color:${tc}">${ico} ${lbl}</span>
          <div class="ev-title">${esc(ev.title)}</div>
          ${subjectHTML}
        </div>
      </div>
      ${descHTML}
      <div class="ev-actions">
        ${doneBtn}
        <button class="ev-btn" onclick="openEditModal(${ev.id})">✏️ Editar</button>
        <button class="ev-btn del-btn" onclick="deleteEvent(${ev.id},'${esc(ev.title)}')">🗑</button>
      </div>
    </div>`;
}

// ════════════════════════════════════════════════════════════
// MODAL
// ════════════════════════════════════════════════════════════
let selectedType = '';

function selectType(type, el){
  // Se clicar no mesmo tipo já selecionado → desmarca
  if (selectedType === type) {
    selectedType = '';

    el.classList.remove('selected');
    el.style.background = '';
    el.style.borderColor = '';
    el.style.color = '';

    return;
  }

  // Caso contrário → seleciona normalmente
  selectedType = type;

  document.querySelectorAll('.type-opt').forEach(o => {
    o.classList.remove('selected');
    o.style.background = '';
    o.style.borderColor = '';
    o.style.color = '';
  });

  el.classList.add('selected');

  const tc = TYPE_COLOR[type] || '#6b7280';
  el.style.background = tc;
  el.style.borderColor = tc;
  el.style.color = '#fff';
}

function resetTypePicker(type=''){
  selectedType = type;
  document.querySelectorAll('.type-opt').forEach(o => {
    const t = o.dataset.type;
    const isSelected = (t === type);
    o.classList.toggle('selected', isSelected);
    const tc = TYPE_COLOR[t] || '#6b7280';
    if (isSelected) {
      o.style.background = tc;
      o.style.borderColor = tc;
      o.style.color = '#fff';
    } else {
      o.style.background = '';
      o.style.borderColor = '';
      o.style.color = '';
    }
  });
}

function openCreateModal(){
  document.getElementById('modalTitle').textContent = 'Novo evento';
  document.getElementById('mId').value = '';
  document.getElementById('mTitle').value = '';
  document.getElementById('mDateInput').value = selectedDate || TODAY;
  document.getElementById('mSubject').value = '';
  document.getElementById('mDesc').value = '';
  document.getElementById('mAlert').className = 'f-alert';
  resetTypePicker();
  document.getElementById('modalOverlay').classList.add('open');
  document.body.style.overflow='hidden';
  setTimeout(()=>document.getElementById('mTitle').focus(),150);
}

function openEditModal(id){
  let ev = null;
  for (const evs of Object.values(EVENTS_BY_DATE)) {
    ev = evs.find(e => +e.id === +id);
    if (ev) break;
  }
  if (!ev) { toast('Evento não encontrado.','err'); return; }

  document.getElementById('modalTitle').textContent = 'Editar evento';
  document.getElementById('mId').value = ev.id;
  document.getElementById('mTitle').value = ev.title || '';
  document.getElementById('mDateInput').value = ev.event_date || '';
  document.getElementById('mSubject').value = ev.subject_id || '';
  document.getElementById('mDesc').value = ev.description || '';
  document.getElementById('mAlert').className = 'f-alert';
  resetTypePicker(ev.event_type || '');
  document.getElementById('modalOverlay').classList.add('open');
  document.body.style.overflow='hidden';
  setTimeout(()=>document.getElementById('mTitle').focus(),150);
}

function closeModal(){
  document.getElementById('modalOverlay').classList.remove('open');
  document.body.style.overflow='';
}

function setAlert(msg){
  const el=document.getElementById('mAlert');
  el.textContent=msg; el.className='f-alert err show';
}

// ── Salvar evento ─────────────────────────────────────────────
async function submitEvent(){
  const id    = document.getElementById('mId').value;
  const title = document.getElementById('mTitle').value.trim();
  const date  = document.getElementById('mDateInput').value;
  const subj  = document.getElementById('mSubject').value || null;
  const desc  = document.getElementById('mDesc').value.trim();

  if (!title) { setAlert('Informe o título do evento.'); return; }
  if (!date)  { setAlert('Informe a data.'); return; }
  if (!selectedType) { setAlert('Selecione o tipo do evento.'); return; }

  const isEdit = !!id;
  const payload = {
    action:      isEdit ? 'update' : 'create',
    title, event_date: date, event_type: selectedType,
    description: desc || null, subject_id: subj,
    ...(isEdit ? {id:+id} : {}),
  };

  const r = await api(payload);
  if (!r.success) { setAlert(r.message || 'Erro ao salvar.'); return; }

  toast(isEdit ? 'Evento atualizado!' : 'Evento criado! 📅');
  closeModal();

  if (!isEdit) {
    const ev = r.data;
    if (!EVENTS_BY_DATE[ev.event_date]) EVENTS_BY_DATE[ev.event_date] = [];
    EVENTS_BY_DATE[ev.event_date].push(ev);
    updateCalCell(ev.event_date);
  } else {
    for (const d in EVENTS_BY_DATE) {
      EVENTS_BY_DATE[d] = EVENTS_BY_DATE[d].filter(e => +e.id !== +id);
      if (!EVENTS_BY_DATE[d].length) delete EVENTS_BY_DATE[d];
    }
    if (!EVENTS_BY_DATE[date]) EVENTS_BY_DATE[date] = [];
    EVENTS_BY_DATE[date].push({
      id:+id, title, event_date:date, event_type:selectedType,
      description:desc, subject_id:subj?+subj:null,
      is_done:0,
      subject_name: SUBJS.find(s=>s.id==subj)?.name||null,
      subject_color: SUBJS.find(s=>s.id==subj)?.color||null,
    });
    updateCalCell(date);
  }
  if (selectedDate) renderDayPanel(selectedDate);
}

// ── Toggle concluído ──────────────────────────────────────────
async function toggleDone(id){
  const r = await api({action:'toggle_done', id});
  if (!r.success) { toast('Erro.','err'); return; }

  for (const evs of Object.values(EVENTS_BY_DATE)) {
    const ev = evs.find(e=>+e.id===+id);
    if (ev) { ev.is_done = r.is_done; break; }
  }

  toast('✅ Marcado como concluído!');
  if (selectedDate) renderDayPanel(selectedDate);
  for (const d in EVENTS_BY_DATE) {
    if (EVENTS_BY_DATE[d].some(e=>+e.id===+id)) { updateCalCell(d); break; }
  }
}

// ── Excluir evento ────────────────────────────────────────────
async function deleteEvent(id, title){
  if (!confirm(`Excluir "${title}"?\n\nEsta ação não pode ser desfeita.`)) return;
  const r = await api({action:'delete', id});
  if (!r.success) { toast(r.message||'Erro.','err'); return; }

  let removedDate = null;
  for (const d in EVENTS_BY_DATE) {
    const idx = EVENTS_BY_DATE[d].findIndex(e=>+e.id===+id);
    if (idx>-1) {
      EVENTS_BY_DATE[d].splice(idx,1);
      if (!EVENTS_BY_DATE[d].length) delete EVENTS_BY_DATE[d];
      removedDate = d;
      break;
    }
  }

  toast('Evento excluído.');
  if (removedDate) updateCalCell(removedDate);
  if (selectedDate) renderDayPanel(selectedDate);
}

// ── Atualiza célula do calendário ─────────────────────────────
function updateCalCell(dateStr){
  const cell = document.querySelector(`.cal-day[data-date="${dateStr}"]`);
  if (!cell) return;

  cell.querySelectorAll('.cal-event-pill,.cal-more').forEach(el=>el.remove());

  const evs  = EVENTS_BY_DATE[dateStr] || [];
  const show = evs.slice(0,3);
  const extra = evs.length - show.length;

  show.forEach(ev=>{
    const tc = TYPE_COLOR[ev.event_type]||'#6b7280';
    const pill=document.createElement('div');
    pill.className='cal-event-pill'+(ev.is_done?' done':'');
    pill.style.cssText=`background:${tc}22;color:${tc};border:1px solid ${tc}33`;
    pill.textContent=(TYPE_ICO[ev.event_type]||'📌')+' '+ev.title.slice(0,18);
    cell.appendChild(pill);
  });

  if (extra>0){
    const more=document.createElement('div');
    more.className='cal-more';
    more.textContent=`+${extra} mais`;
    cell.appendChild(more);
  }
}

// ── Seleciona hoje ao carregar ────────────────────────────────
document.addEventListener('DOMContentLoaded',()=>{
  if (VIEW_Y===new Date().getFullYear() && VIEW_M===new Date().getMonth()+1) {
    selectDay(TODAY);
  }
});
</script>
</body>
</html>