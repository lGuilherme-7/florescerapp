<?php
// ============================================================
// /professor/teachers/views/dashboard.php
// ============================================================

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireTeacher();
$teacher     = currentTeacher();
$currentPage = 'dashboard';
$teacherId   = (int)$teacher['id'];

// ── Badges sidebar ────────────────────────────────────────────
$pendingRed = (int)(dbRow(
    'SELECT COUNT(*) AS n FROM teacher_redacoes
     WHERE teacher_id = ? AND status = "pendente"',
    [$teacherId]
)['n'] ?? 0);

$unreadMsgs = (int)(dbRow(
    "SELECT COUNT(*) AS n FROM teacher_messages
     WHERE teacher_id = ? AND sender = 'student' AND read_at IS NULL",
    [$teacherId]
)['n'] ?? 0);

// ── Saldo ─────────────────────────────────────────────────────
$saldo          = (float)($teacher['balance']         ?? 0);
$saldoPendente  = (float)($teacher['balance_pending'] ?? 0);

// ── Redações ──────────────────────────────────────────────────
$redacoesPend   = $pendingRed;
$redacoesMes    = (int)(dbRow(
    'SELECT COUNT(*) AS n FROM teacher_redacoes
     WHERE teacher_id = ? AND status = "corrigida"
     AND MONTH(corrigida_em) = MONTH(NOW()) AND YEAR(corrigida_em) = YEAR(NOW())',
    [$teacherId]
)['n'] ?? 0);

// ── Alunos ────────────────────────────────────────────────────
$totalAlunos = (int)(dbRow(
    'SELECT COUNT(DISTINCT student_id) AS n FROM teacher_orders
     WHERE teacher_id = ? AND status = "pago"',
    [$teacherId]
)['n'] ?? 0);

// ── Ganhos do mês ─────────────────────────────────────────────
$ganhosMes = (float)(dbRow(
    'SELECT COALESCE(SUM(net_amount), 0) AS total FROM teacher_orders
     WHERE teacher_id = ? AND status = "pago"
     AND MONTH(paid_at) = MONTH(NOW()) AND YEAR(paid_at) = YEAR(NOW())',
    [$teacherId]
)['total'] ?? 0);

// ── Ganhos por dia (últimos 7 dias) ───────────────────────────
$ganhosSemana = [];
for ($i = 6; $i >= 0; $i--) {
    $d   = date('Y-m-d', strtotime("-{$i} days"));
    $val = (float)(dbRow(
        'SELECT COALESCE(SUM(net_amount), 0) AS total FROM teacher_orders
         WHERE teacher_id = ? AND status = "pago" AND DATE(paid_at) = ?',
        [$teacherId, $d]
    )['total'] ?? 0);
    $ganhosSemana[] = ['date' => $d, 'val' => $val, 'day' => date('D', strtotime($d))];
}
$maxGanho = max(array_column($ganhosSemana, 'val') ?: [1]);

// ── Próxima aula ──────────────────────────────────────────────
$proximaAula = dbRow(
    "SELECT o.id, o.scheduled_at, o.net_amount, o.meet_link, o.student_id
     FROM teacher_orders o
     WHERE o.teacher_id = ? AND o.type = 'aula' AND o.status = 'pago'
     AND o.scheduled_at > NOW()
     ORDER BY o.scheduled_at ASC LIMIT 1",
    [$teacherId]
);

// ── Redações pendentes (últimas 4) ────────────────────────────
$redacoesPendentes = dbQuery(
    "SELECT r.id, r.tema, r.status, r.created_at, r.student_id
     FROM teacher_redacoes r
     WHERE r.teacher_id = ?
     AND r.status IN ('pendente','em_correcao')
     ORDER BY r.created_at ASC LIMIT 4",
    [$teacherId]
);

// ── Notificações recentes ─────────────────────────────────────
$notifs = [];

// Redações novas
$novasRed = dbQuery(
    "SELECT id, tema, created_at FROM teacher_redacoes
     WHERE teacher_id = ? AND status = 'pendente'
     ORDER BY created_at DESC LIMIT 3",
    [$teacherId]
);
foreach ($novasRed as $r) {
    $notifs[] = ['ico'=>'📝','msg'=>'Nova redação para corrigir: '.mb_substr($r['tema'],0,40,'UTF-8'),'time'=>$r['created_at'],'nova'=>true];
}

// Mensagens não lidas
if ($unreadMsgs > 0) {
    $notifs[] = ['ico'=>'💬','msg'=>"{$unreadMsgs} mensagem".($unreadMsgs>1?'ns':'').' não lida'.($unreadMsgs>1?'s':''),'time'=>date('Y-m-d H:i:s'),'nova'=>true];
}

// Último saque
$ultimoSaque = dbRow(
    "SELECT amount, status, created_at FROM teacher_withdrawals
     WHERE teacher_id = ? ORDER BY created_at DESC LIMIT 1",
    [$teacherId]
);
if ($ultimoSaque) {
    $notifs[] = ['ico'=>'💰','msg'=>'Saque de '.money((float)$ultimoSaque['amount']).' — '.$ultimoSaque['status'],'time'=>$ultimoSaque['created_at'],'nova'=>false];
}

usort($notifs, fn($a,$b)=>strtotime($b['time'])-strtotime($a['time']));
$notifs = array_slice($notifs, 0, 5);

// ── Ranking ───────────────────────────────────────────────────
$rankTotal = (int)(dbRow("SELECT COUNT(*) AS n FROM teachers WHERE status = 'ativo'")['n'] ?? 0);
$rankPos   = (int)($teacher['rank_position'] ?? 0);
$rankPct   = $rankPos > 0 && $rankTotal > 0 ? round((1 - ($rankPos - 1) / $rankTotal) * 100) : 0;

$hora = (int)date('G');
$saudacao = $hora >= 5 && $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
$primeiroNome = explode(' ', trim($teacher['name'] ?? 'Professor'))[0];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Dashboard — Professor</title>
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
  --gold:#c9a84c;--gold-l:#fdf3d8;--red:#d94040;--red-l:#fdeaea;--blue:#2563eb;
  --sw:248px;--hh:60px;
  --fd:'Fraunces',Georgia,serif;--fb:'DM Sans',system-ui,sans-serif;
  --r:14px;--rs:9px;--d:.2s;--e:cubic-bezier(.4,0,.2,1);
  --sh0:0 1px 3px rgba(0,0,0,.06);--sh1:0 4px 12px rgba(0,0,0,.07);
  --sh2:0 8px 24px rgba(0,0,0,.09);--sh3:0 20px 48px rgba(0,0,0,.12);
}
html{height:100%}
body{font-family:var(--fb);background:var(--g25);color:var(--n800);display:flex;min-height:100%;-webkit-font-smoothing:antialiased;overflow-x:hidden}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:var(--g200);border-radius:2px}
.main{margin-left:var(--sw);flex:1;min-width:0;display:flex;flex-direction:column}
.topbar{height:var(--hh);position:sticky;top:0;z-index:40;background:rgba(244,251,247,.94);backdrop-filter:blur(16px);border-bottom:1px solid var(--n100);display:flex;align-items:center;justify-content:space-between;padding:0 1.8rem;flex-shrink:0}
.tb-title{font-family:var(--fd);font-size:1.05rem;font-weight:600;color:var(--n800)}
.tb-right{display:flex;align-items:center;gap:.7rem}
.tb-date{font-size:.68rem;color:var(--n400)}

.page{padding:1.5rem 1.8rem;display:flex;flex-direction:column;gap:1.1rem;flex:1}

/* Saudação */
.greet-row{display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:.8rem}
.greet-txt{}
.greet-hi{font-size:.76rem;color:var(--n400);margin-bottom:.18rem}
.greet-name{font-family:var(--fd);font-size:1.5rem;font-weight:900;color:var(--n800);letter-spacing:-.03em;line-height:1.1}
.greet-name em{color:var(--g500);font-style:italic;font-weight:400}
.btn-saque{padding:.48rem 1.1rem;background:linear-gradient(135deg,var(--g500),var(--g700));color:#fff;border:none;border-radius:50px;font-family:var(--fb);font-size:.78rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);box-shadow:0 3px 10px rgba(45,122,88,.22)}
.btn-saque:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(45,122,88,.32)}

/* Stats */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:.75rem}
.stat{background:var(--white);border:1px solid var(--n100);border-radius:var(--r);padding:.9rem 1rem;box-shadow:var(--sh0);position:relative;overflow:hidden;transition:transform var(--d) var(--e),box-shadow var(--d) var(--e)}
.stat:hover{transform:translateY(-2px);box-shadow:var(--sh1)}
.stat::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;background:var(--stat-color,var(--g400));opacity:.5}
.stat-ico{font-size:1rem;margin-bottom:.45rem;opacity:.65;display:block}
.stat-val{font-family:var(--fd);font-size:1.45rem;font-weight:900;color:var(--n800);line-height:1;letter-spacing:-.02em}
.stat-val.green{color:var(--g600)}
.stat-lbl{font-size:.62rem;color:var(--n400);text-transform:uppercase;letter-spacing:.06em;margin-top:.2rem}
.stat-sub{font-size:.7rem;color:var(--n400);margin-top:.28rem}
.stat-sub span{color:var(--g500);font-weight:600}

/* Grid principal */
.dash-grid{display:grid;grid-template-columns:1.4fr 1fr;gap:1rem}
.dash-col{display:flex;flex-direction:column;gap:1rem}

/* Widget */
.widget{background:var(--white);border:1px solid var(--n100);border-radius:var(--r);box-shadow:var(--sh0);overflow:hidden}
.wh{padding:.8rem 1.1rem;border-bottom:1px solid var(--n100);display:flex;align-items:center;justify-content:space-between}
.wh-title{font-family:var(--fd);font-size:.9rem;font-weight:600;color:var(--n800)}
.wh-sub{font-size:.68rem;color:var(--n400)}
.wh-badge{font-size:.62rem;font-weight:600;padding:.12rem .45rem;border-radius:20px;background:var(--red-l);color:var(--red)}
.wh-link{font-size:.7rem;color:var(--g500);text-decoration:none;transition:color var(--d) var(--e)}
.wh-link:hover{color:var(--g700)}

/* Saldo widget */
.saldo-widget{background:linear-gradient(135deg,var(--g800),var(--g900));border:none;color:#fff}
.saldo-body{padding:1.2rem 1.4rem}
.saldo-lbl{font-size:.63rem;text-transform:uppercase;letter-spacing:.1em;color:rgba(194,234,214,.4);margin-bottom:.28rem}
.saldo-val{font-family:var(--fd);font-size:2.2rem;font-weight:900;color:#fff;line-height:1;letter-spacing:-.03em}
.saldo-val span{font-size:1rem;opacity:.55;margin-right:.1rem}
.saldo-pend{margin-top:.6rem;padding:.45rem .7rem;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);border-radius:var(--rs);display:flex;align-items:center;justify-content:space-between}
.sp-lbl{font-size:.67rem;color:rgba(194,234,214,.5)}
.sp-val{font-size:.8rem;font-weight:600;color:rgba(194,234,214,.8)}
.saldo-actions{padding:.85rem 1.4rem;border-top:1px solid rgba(255,255,255,.07);display:flex;gap:.45rem}
.btn-saque-sm{flex:1;padding:.46rem;border-radius:var(--rs);border:none;background:linear-gradient(135deg,var(--g300),var(--g400));color:var(--g900);font-family:var(--fb);font-size:.75rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e)}
.btn-saque-sm:hover{filter:brightness(1.08);transform:translateY(-1px)}
.btn-extrato{flex:1;padding:.46rem;border-radius:var(--rs);background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);color:rgba(194,234,214,.7);font-family:var(--fb);font-size:.75rem;cursor:pointer;transition:all var(--d) var(--e)}
.btn-extrato:hover{background:rgba(255,255,255,.12)}

/* Próxima aula */
.aula-body{padding:1rem 1.1rem}
.aula-aluno{display:flex;align-items:center;gap:.65rem;margin-bottom:.8rem}
.aula-av{width:36px;height:36px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,var(--g100),var(--g200));display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-size:.9rem;font-weight:700;color:var(--g700)}
.aula-name{font-size:.82rem;font-weight:600;color:var(--n800)}
.aula-sub{font-size:.67rem;color:var(--n400);margin-top:.04rem}
.countdown-box{background:var(--g50);border:1px solid var(--g100);border-radius:var(--rs);padding:.7rem;text-align:center;margin-bottom:.7rem}
.cd-lbl{font-size:.62rem;text-transform:uppercase;letter-spacing:.07em;color:var(--n400);margin-bottom:.15rem}
.cd-time{font-family:var(--fd);font-size:1.55rem;font-weight:900;color:var(--g600);letter-spacing:.04em}
.cd-time.soon{color:var(--g400);animation:pulse 1.5s ease-in-out infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.55}}
.btn-link{width:100%;padding:.5rem;border-radius:var(--rs);border:none;font-family:var(--fb);font-size:.76rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);display:flex;align-items:center;justify-content:center;gap:.35rem}
.btn-link.locked{background:var(--n50);border:1px solid var(--n100);color:var(--n400);cursor:not-allowed}
.btn-link.ready{background:linear-gradient(135deg,var(--g400),var(--g600));color:#fff;box-shadow:0 3px 10px rgba(45,122,88,.22)}
.btn-link.ready:hover{transform:translateY(-1px)}
.no-aula{padding:1.8rem;text-align:center;color:var(--n400);font-size:.78rem}

/* Redações pendentes */
.redacao-list{display:flex;flex-direction:column}
.redacao-item{display:flex;align-items:center;gap:.7rem;padding:.65rem 1.1rem;border-bottom:1px solid var(--n50);cursor:pointer;transition:background var(--d) var(--e)}
.redacao-item:last-child{border-bottom:none}
.redacao-item:hover{background:var(--g25)}
.ri-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.ri-info{flex:1;min-width:0}
.ri-tema{font-size:.78rem;font-weight:500;color:var(--n800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ri-time{font-size:.64rem;color:var(--n400);margin-top:.04rem}
.ri-status{font-size:.62rem;font-weight:600;padding:.1rem .4rem;border-radius:20px;flex-shrink:0}
.ri-arr{font-size:.75rem;color:var(--n200);flex-shrink:0}

/* Gráfico ganhos */
.chart-body{padding:.85rem 1.1rem}
.chart-total-lbl{font-size:.63rem;color:var(--n400);text-transform:uppercase;letter-spacing:.06em}
.chart-total-val{font-family:var(--fd);font-size:1.3rem;font-weight:900;color:var(--g600);line-height:1.1;margin-bottom:.8rem}
.bar-chart{display:flex;align-items:flex-end;gap:6px;height:88px}
.bar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;height:100%}
.bar{width:100%;border-radius:4px 4px 2px 2px;min-height:4px;transition:height .5s var(--e)}
.bar.today{background:linear-gradient(180deg,var(--g300),var(--g500))}
.bar.other{background:rgba(61,153,112,.2)}
.bar-lbl{font-size:.58rem;color:var(--n400);font-weight:500}
.bar-lbl.today{color:var(--g500);font-weight:600}

/* Ranking */
.rank-body{padding:.9rem 1.1rem}
.rank-pos-box{display:flex;align-items:center;gap:.75rem;margin-bottom:.85rem;padding:.7rem;background:var(--g50);border:1px solid var(--g100);border-radius:var(--rs)}
.rank-num{font-family:var(--fd);font-size:2rem;font-weight:900;color:var(--g500);line-height:1;min-width:2.2rem;text-align:center}
.rank-info-title{font-size:.8rem;font-weight:600;color:var(--n800)}
.rank-info-sub{font-size:.67rem;color:var(--n400);margin-top:.05rem}
.rank-prog-lbl{display:flex;justify-content:space-between;font-size:.66rem;color:var(--n400);margin-bottom:.28rem}
.rank-prog-bar{height:5px;background:var(--n100);border-radius:3px;overflow:hidden}
.rank-prog-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,var(--g400),var(--g300));transition:width .8s var(--e)}
.rank-premium{margin-top:.85rem;padding:.6rem .75rem;background:var(--gold-l);border:1px solid rgba(201,168,76,.2);border-radius:var(--rs);display:flex;align-items:center;gap:.5rem}
.rp-text{font-size:.72rem;color:var(--n800);line-height:1.5}
.rp-text strong{color:var(--gold)}

/* Notificações */
.notif-list{display:flex;flex-direction:column}
.notif-item{display:flex;align-items:flex-start;gap:.65rem;padding:.65rem 1.1rem;border-bottom:1px solid var(--n50);transition:background var(--d) var(--e)}
.notif-item:last-child{border-bottom:none}
.notif-item.nova{background:var(--g25)}
.notif-item:hover{background:var(--g50)}
.notif-dot{width:6px;height:6px;border-radius:50%;background:var(--g400);flex-shrink:0;margin-top:.4rem}
.notif-ico{font-size:.95rem;flex-shrink:0}
.notif-msg{font-size:.75rem;color:var(--n800);line-height:1.5;flex:1}
.notif-time{font-size:.62rem;color:var(--n400);flex-shrink:0;white-space:nowrap}

.empty-widget{padding:1.8rem;text-align:center;color:var(--n400);font-size:.78rem}

#toasts{position:fixed;bottom:1.2rem;right:1.2rem;z-index:999;display:flex;flex-direction:column;gap:.35rem;pointer-events:none}
.toast{background:var(--n800);color:#eee;padding:.55rem .9rem;border-radius:var(--rs);font-size:.78rem;display:flex;align-items:center;gap:.4rem;animation:tin .22s var(--e) both;max-width:280px;box-shadow:var(--sh3);pointer-events:all}
.toast.ok{border-left:3px solid var(--g400)}.toast.err{border-left:3px solid #f87171}
@keyframes tin{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}

@media(max-width:1200px){.stats-row{grid-template-columns:repeat(2,1fr)}}
@media(max-width:900px){.dash-grid{grid-template-columns:1fr}}
@media(max-width:768px){.main{margin-left:0}.page{padding:1rem}.stats-row{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
  <header class="topbar">
    <span class="tb-title">◈ Dashboard</span>
    <div class="tb-right">
      <span class="tb-date"><?= date('d/m/Y · H:i') ?></span>
    </div>
  </header>

  <main class="page">

    <!-- Saudação -->
    <div class="greet-row">
      <div class="greet-txt">
        <div class="greet-hi"><?= $saudacao ?>, professor(a) 👋</div>
        <div class="greet-name">
          <?= htmlspecialchars($primeiroNome, ENT_QUOTES) ?>,
          <em>bem-vindo de volta.</em>
        </div>
      </div>
      <button class="btn-saque" onclick="location.href='<?= TEACHER_VIEWS ?>/financeiro.php'">
        💸 Solicitar saque
      </button>
    </div>

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat" style="--stat-color:var(--g400)">
        <span class="stat-ico">💰</span>
        <div class="stat-val green">
          R$ <?= number_format($ganhosMes,2,',','.') ?>
        </div>
        <div class="stat-lbl">Ganhos este mês</div>
        <div class="stat-sub">Saldo: <span>R$ <?= number_format($saldo,2,',','.') ?></span></div>
      </div>
      <div class="stat" style="--stat-color:var(--red)">
        <span class="stat-ico">📝</span>
        <div class="stat-val"><?= $redacoesPend ?></div>
        <div class="stat-lbl">Redações pendentes</div>
        <div class="stat-sub">Corrigidas no mês: <span><?= $redacoesMes ?></span></div>
      </div>
      <div class="stat" style="--stat-color:var(--gold)">
        <span class="stat-ico">⭐</span>
        <div class="stat-val">
          <?= (float)$teacher['rating_avg'] > 0
            ? number_format((float)$teacher['rating_avg'],1)
            : '—' ?>
        </div>
        <div class="stat-lbl">Avaliação média</div>
        <div class="stat-sub">Ranking: <span>
          <?= $rankPos > 0 ? '#'.$rankPos.' de '.$rankTotal : '—' ?>
        </span></div>
      </div>
      <div class="stat" style="--stat-color:var(--blue)">
        <span class="stat-ico">👥</span>
        <div class="stat-val"><?= $totalAlunos ?></div>
        <div class="stat-lbl">Alunos atendidos</div>
        <div class="stat-sub">Msgs não lidas: <span><?= $unreadMsgs ?></span></div>
      </div>
    </div>

    <!-- Grid principal -->
    <div class="dash-grid">

      <!-- Coluna esquerda -->
      <div class="dash-col">

        <!-- Saldo -->
        <div class="widget saldo-widget">
          <div class="saldo-body">
            <div class="saldo-lbl">Saldo disponível</div>
            <div class="saldo-val">
              <span>R$</span><?= number_format($saldo,2,',','.') ?>
            </div>
            <div class="saldo-pend">
              <span class="sp-lbl">⏳ Pendente</span>
              <span class="sp-val">R$ <?= number_format($saldoPendente,2,',','.') ?></span>
            </div>
          </div>
          <div class="saldo-actions">
            <button class="btn-saque-sm"
                    onclick="location.href='<?= TEACHER_VIEWS ?>/financeiro.php'">
              💸 Solicitar saque
            </button>
            <button class="btn-extrato"
                    onclick="location.href='<?= TEACHER_VIEWS ?>/financeiro.php'">
              📊 Ver financeiro
            </button>
          </div>
        </div>

        <!-- Redações pendentes -->
        <div class="widget">
          <div class="wh">
            <span class="wh-title">📝 Redações para corrigir</span>
            <?php if($redacoesPend>0): ?>
              <span class="wh-badge"><?= $redacoesPend ?> pendente<?= $redacoesPend>1?'s':'' ?></span>
            <?php else: ?>
              <a class="wh-link" href="<?= TEACHER_VIEWS ?>/redacoes.php">Ver todas →</a>
            <?php endif; ?>
          </div>
          <?php if(empty($redacoesPendentes)): ?>
            <div class="empty-widget">✅ Nenhuma redação pendente!</div>
          <?php else: ?>
          <div class="redacao-list">
            <?php
            $urgColors = ['pendente'=>'#d94040','em_correcao'=>'#c9a84c'];
            $stMeta = ['pendente'=>['Pendente','#fdeaea','#d94040'],'em_correcao'=>['Em correção','#fdf3d8','#c9a84c']];
            foreach($redacoesPendentes as $r):
              $diff = time()-strtotime($r['created_at']);
              $ago  = $diff<3600?intdiv($diff,60).'min':($diff<86400?intdiv($diff,3600).'h':intdiv($diff,86400).'d');
              [$slbl,$sbg,$scol] = $stMeta[$r['status']]??['?','#eee','#999'];
            ?>
            <div class="redacao-item"
                 onclick="location.href='<?= TEACHER_VIEWS ?>/redacoes.php'">
              <div class="ri-dot" style="background:<?= $urgColors[$r['status']]??'#ccc' ?>"></div>
              <div class="ri-info">
                <div class="ri-tema"><?= htmlspecialchars($r['tema'],ENT_QUOTES) ?></div>
                <div class="ri-time">há <?= $ago ?></div>
              </div>
              <span class="ri-status" style="background:<?= $sbg ?>;color:<?= $scol ?>">
                <?= $slbl ?>
              </span>
              <span class="ri-arr">›</span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Ganhos da semana -->
        <div class="widget">
          <div class="wh">
            <span class="wh-title">📈 Ganhos esta semana</span>
            <a class="wh-link" href="<?= TEACHER_VIEWS ?>/financeiro.php">Ver extrato →</a>
          </div>
          <div class="chart-body">
            <div class="chart-total-lbl">Total acumulado</div>
            <div class="chart-total-val">
              R$ <?= number_format(array_sum(array_column($ganhosSemana,'val')),2,',','.') ?>
            </div>
            <div class="bar-chart">
              <?php
              $hoje = date('Y-m-d');
              $dias = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
              foreach($ganhosSemana as $g):
                $pct     = $maxGanho > 0 ? round($g['val']/$maxGanho*84) : 4;
                $isToday = $g['date'] === $hoje;
                $dow     = $dias[(int)date('w',strtotime($g['date']))];
              ?>
              <div class="bar-col">
                <div class="bar <?= $isToday?'today':'other' ?>"
                     style="height:<?= max(4,$pct) ?>px"
                     title="R$ <?= number_format($g['val'],2,',','.') ?>"></div>
                <span class="bar-lbl <?= $isToday?'today':'' ?>"><?= $dow ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

      </div>

      <!-- Coluna direita -->
      <div class="dash-col">

        <!-- Próxima aula -->
        <div class="widget">
          <div class="wh">
            <span class="wh-title">📅 Próxima aula</span>
            <?php if($proximaAula): ?>
              <span class="wh-sub">
                <?= date('H:i',strtotime($proximaAula['scheduled_at'])) ?>
              </span>
            <?php endif; ?>
          </div>
          <?php if(!$proximaAula): ?>
            <div class="no-aula">
              📅 Nenhuma aula agendada.<br>
              <a href="<?= TEACHER_VIEWS ?>/aulas.php"
                 style="color:var(--g500);font-size:.75rem">
                Configurar horários →
              </a>
            </div>
          <?php else:
            $ini = 'A'; // sem tabela users local
          ?>
          <div class="aula-body">
            <div class="aula-aluno">
              <div class="aula-av"><?= $ini ?></div>
              <div>
                <div class="aula-name">Aluno</div>
                <div class="aula-sub">
                  <?= date('d/m/Y · H:i',strtotime($proximaAula['scheduled_at'])) ?>
                </div>
              </div>
            </div>
            <div class="countdown-box">
              <div class="cd-lbl">Começa em</div>
              <div class="cd-time" id="countdown">--:--</div>
            </div>
            <button class="btn-link locked" id="btnLink">
              🔒 Link disponível em breve
            </button>
          </div>
          <?php endif; ?>
        </div>

        <!-- Ranking -->
        <div class="widget">
          <div class="wh">
            <span class="wh-title">🏆 Seu ranking</span>
            <a class="wh-link"
               href="<?= TEACHER_BASE_URL ?>/public/ranking.php">
              Ver ranking →
            </a>
          </div>
          <div class="rank-body">
            <?php if($rankPos > 0): ?>
            <div class="rank-pos-box">
              <div class="rank-num">#<?= $rankPos ?></div>
              <div>
                <div class="rank-info-title">
                  <?= htmlspecialchars($teacher['name'],ENT_QUOTES) ?>
                </div>
                <div class="rank-info-sub">
                  de <?= $rankTotal ?> professores ·
                  ⭐ <?= number_format((float)$teacher['rating_avg'],1) ?>
                </div>
              </div>
            </div>
            <div>
              <div class="rank-prog-lbl">
                <span>Progresso</span>
                <span><?= $rankPct ?>% acima da média</span>
              </div>
              <div class="rank-prog-bar">
                <div class="rank-prog-fill" style="width:<?= $rankPct ?>%"></div>
              </div>
            </div>
            <?php else: ?>
            <div class="empty-widget">
              Complete seu perfil para aparecer no ranking.
            </div>
            <?php endif; ?>
            <div class="rank-premium">
              <span>👑</span>
              <div class="rp-text">
                Destaque no ranking por <strong>R$29/mês</strong>.
                Apareça primeiro para os alunos.
              </div>
            </div>
          </div>
        </div>

        <!-- Notificações -->
        <div class="widget">
          <div class="wh">
            <span class="wh-title">🔔 Notificações</span>
            <span class="wh-sub">
              <?= count(array_filter($notifs,fn($n)=>$n['nova'])) ?> nova<?= count(array_filter($notifs,fn($n)=>$n['nova']))!==1?'s':'' ?>
            </span>
          </div>
          <?php if(empty($notifs)): ?>
            <div class="empty-widget">Nenhuma notificação.</div>
          <?php else: ?>
          <div class="notif-list">
            <?php foreach($notifs as $n):
              $diff = time()-strtotime($n['time']);
              $ago  = $diff<60?'agora':($diff<3600?intdiv($diff,60).'min':($diff<86400?intdiv($diff,3600).'h':intdiv($diff,86400).'d atrás'));
            ?>
            <div class="notif-item <?= $n['nova']?'nova':'' ?>">
              <?php if($n['nova']): ?>
                <div class="notif-dot"></div>
              <?php endif; ?>
              <span class="notif-ico"><?= $n['ico'] ?></span>
              <span class="notif-msg">
                <?= htmlspecialchars($n['msg'],ENT_QUOTES) ?>
              </span>
              <span class="notif-time"><?= $ago ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

      </div>
    </div>

  </main>
</div>

<div id="toasts"></div>

<?php if($proximaAula): ?>
<script>
(function(){
  const aulaTs  = <?= strtotime($proximaAula['scheduled_at']) ?> * 1000;
  const btnLink = document.getElementById('btnLink');
  const cdEl    = document.getElementById('countdown');
  const CINCO   = 5 * 60 * 1000;
  const linkUrl = <?= $proximaAula['meet_link'] ? json_encode($proximaAula['meet_link']) : 'null' ?>;

  function tick(){
    const now  = Date.now();
    const diff = aulaTs - now;
    if(diff <= 0){
      cdEl.textContent='Agora!';cdEl.classList.add('soon');
      if(linkUrl){ btnLink.className='btn-link ready';btnLink.innerHTML='🎥 Entrar na aula';btnLink.onclick=()=>window.open(linkUrl,'_blank'); }
      return;
    }
    const m=Math.floor(diff/60000), s=Math.floor((diff%60000)/1000);
    cdEl.textContent=String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
    if(diff<=CINCO && linkUrl){
      cdEl.classList.add('soon');
      btnLink.className='btn-link ready';
      btnLink.innerHTML='🎥 Entrar na aula';
      btnLink.onclick=()=>window.open(linkUrl,'_blank');
    }
  }
  tick();
  setInterval(tick,1000);
})();
</script>
<?php endif; ?>

</body>
</html>