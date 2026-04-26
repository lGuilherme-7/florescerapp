<?php
// ============================================================
// /professor/teachers/views/financeiro.php
// ============================================================

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireTeacher();
$teacher     = currentTeacher();
$currentPage = 'financeiro';

$pendingRed = (int)(dbRow(
    'SELECT COUNT(*) AS n FROM teacher_redacoes WHERE teacher_id = ? AND status = "pendente"',
    [(int)$teacher['id']]
)['n'] ?? 0);
$unreadMsgs = (int)(dbRow(
    "SELECT COUNT(*) AS n FROM teacher_messages
     WHERE teacher_id = ? AND sender='student' AND read_at IS NULL",
    [(int)$teacher['id']]
)['n'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Financeiro — Professor</title>
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
  --sw:248px;--hh:60px;
  --fd:'Fraunces',Georgia,serif;--fb:'DM Sans',system-ui,sans-serif;
  --r:14px;--rs:9px;--d:.2s;--e:cubic-bezier(.4,0,.2,1);
  --sh0:0 1px 3px rgba(0,0,0,.06);--sh1:0 4px 12px rgba(0,0,0,.07);--sh3:0 20px 48px rgba(0,0,0,.12);
}
html{height:100%}
body{font-family:var(--fb);background:var(--g25);color:var(--n800);display:flex;min-height:100%;-webkit-font-smoothing:antialiased;overflow-x:hidden}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:var(--g200);border-radius:2px}
.main{margin-left:var(--sw);flex:1;min-width:0;display:flex;flex-direction:column}
.topbar{height:var(--hh);position:sticky;top:0;z-index:40;background:rgba(244,251,247,.94);backdrop-filter:blur(16px);border-bottom:1px solid var(--n100);display:flex;align-items:center;justify-content:space-between;padding:0 1.8rem;flex-shrink:0}
.tb-title{font-family:var(--fd);font-size:1.05rem;font-weight:600;color:var(--n800)}
.page{padding:1.5rem 1.8rem;flex:1;display:flex;flex-direction:column;gap:1.1rem}

/* Hero saldo */
.saldo-hero{
  background:linear-gradient(135deg,var(--g800),var(--g900));
  border-radius:var(--r);padding:1.8rem 2rem;
  display:grid;grid-template-columns:1fr auto;gap:1.5rem;align-items:center;
  box-shadow:0 8px 24px rgba(13,38,24,.18);
}
.sh-lbl{font-size:.65rem;text-transform:uppercase;letter-spacing:.1em;color:rgba(194,234,214,.4);margin-bottom:.3rem}
.sh-val{font-family:var(--fd);font-size:2.8rem;font-weight:900;color:#fff;line-height:1;letter-spacing:-.03em}
.sh-val span{font-size:1.2rem;opacity:.55;margin-right:.15rem}
.sh-pending{margin-top:.7rem;font-size:.76rem;color:rgba(194,234,214,.45)}
.sh-pending strong{color:rgba(194,234,214,.75)}
.sh-commission{margin-top:.35rem;font-size:.7rem;color:rgba(194,234,214,.28)}
.sh-actions{display:flex;flex-direction:column;gap:.55rem;align-items:flex-end}
.btn-saque{padding:.65rem 1.4rem;border-radius:50px;border:none;background:linear-gradient(135deg,var(--g300),var(--g400));color:var(--g900);font-family:var(--fb);font-size:.82rem;font-weight:700;cursor:pointer;transition:all var(--d) var(--e);box-shadow:0 4px 16px rgba(45,122,88,.35);white-space:nowrap}
.btn-saque:hover{filter:brightness(1.08);transform:translateY(-1px)}
.btn-refresh{padding:.5rem 1.1rem;border-radius:50px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:rgba(194,234,214,.65);font-family:var(--fb);font-size:.76rem;cursor:pointer;transition:all var(--d) var(--e);white-space:nowrap}
.btn-refresh:hover{background:rgba(255,255,255,.13)}

/* Stats */
.stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:.8rem}
.stat{background:var(--white);border:1px solid var(--n100);border-radius:var(--r);padding:.9rem 1.1rem;box-shadow:var(--sh0);position:relative;overflow:hidden;transition:transform var(--d) var(--e)}
.stat:hover{transform:translateY(-2px)}
.stat::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;background:var(--stat-color,var(--g400));opacity:.5}
.stat-lbl{font-size:.63rem;color:var(--n400);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.3rem}
.stat-val{font-family:var(--fd);font-size:1.4rem;font-weight:900;color:var(--n800);line-height:1}
.stat-val.green{color:var(--g600)}
.stat-sub{font-size:.7rem;color:var(--n400);margin-top:.25rem}

/* Grid */
.fin-grid{display:grid;grid-template-columns:1.3fr 1fr;gap:1rem;align-items:start}

/* Widget */
.widget{background:var(--white);border:1px solid var(--n100);border-radius:var(--r);box-shadow:var(--sh0);overflow:hidden}
.wh{padding:.8rem 1.1rem;border-bottom:1px solid var(--n100);display:flex;align-items:center;justify-content:space-between}
.wh-title{font-family:var(--fd);font-size:.9rem;font-weight:600;color:var(--n800)}
.wh-sub{font-size:.68rem;color:var(--n400)}

/* Extrato */
.extrato-list{display:flex;flex-direction:column}
.extrato-item{display:flex;align-items:center;gap:.8rem;padding:.7rem 1.1rem;border-bottom:1px solid var(--n50);transition:background var(--d) var(--e)}
.extrato-item:last-child{border-bottom:none}
.extrato-item:hover{background:var(--g25)}
.ei-ico{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0}
.ei-ico.redacao{background:var(--g50);border:1px solid var(--g100)}
.ei-ico.aula{background:var(--gold-l);border:1px solid rgba(201,168,76,.2)}
.ei-info{flex:1;min-width:0}
.ei-aluno{font-size:.8rem;font-weight:500;color:var(--n800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ei-type{font-size:.66rem;color:var(--n400);margin-top:.04rem}
.ei-right{text-align:right;flex-shrink:0}
.ei-val{font-family:var(--fd);font-size:.88rem;font-weight:700;color:var(--g600)}
.ei-comm{font-size:.62rem;color:var(--n400)}
.ei-date{font-size:.63rem;color:var(--n400);margin-top:.06rem}

/* Saques */
.saque-list{display:flex;flex-direction:column}
.saque-item{display:flex;align-items:center;gap:.7rem;padding:.7rem 1.1rem;border-bottom:1px solid var(--n50)}
.saque-item:last-child{border-bottom:none}
.si-status{font-size:.62rem;font-weight:600;padding:.1rem .45rem;border-radius:20px;flex-shrink:0;white-space:nowrap}
.si-info{flex:1;min-width:0}
.si-pix{font-size:.72rem;color:var(--n400);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.si-date{font-size:.62rem;color:var(--n400);margin-top:.04rem}
.si-val{font-family:var(--fd);font-size:.9rem;font-weight:700;color:var(--n800);flex-shrink:0}

.empty{text-align:center;padding:2rem 1rem;color:var(--n400);font-size:.8rem}

/* Modal */
.overlay{position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.45);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;padding:1.2rem;opacity:0;pointer-events:none;transition:opacity var(--d) var(--e)}
.overlay.open{opacity:1;pointer-events:all}
.modal{background:var(--white);border:1px solid var(--n100);border-radius:18px;width:100%;max-width:420px;box-shadow:var(--sh3);transform:translateY(14px) scale(.97);transition:transform var(--d) var(--e)}
.overlay.open .modal{transform:none}
.modal-head{padding:.9rem 1.3rem;border-bottom:1px solid var(--n100);display:flex;align-items:center;justify-content:space-between}
.modal-title{font-family:var(--fd);font-size:.95rem;font-weight:700;color:var(--n800)}
.modal-x{width:28px;height:28px;border-radius:50%;background:var(--n50);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.78rem;color:var(--n400);transition:all var(--d) var(--e)}
.modal-x:hover{background:var(--red-l);color:var(--red)}
.modal-body{padding:1.3rem;display:flex;flex-direction:column;gap:.85rem}
.modal-foot{padding:.85rem 1.3rem;border-top:1px solid var(--n100);display:flex;gap:.5rem;justify-content:flex-end}
.fg{display:flex;flex-direction:column;gap:.28rem}
.fl{font-size:.75rem;font-weight:500;color:var(--n400)}
.fc{padding:.62rem .85rem;background:var(--g25);border:1.5px solid var(--n100);border-radius:var(--rs);color:var(--n800);font-family:var(--fb);font-size:.88rem;outline:none;transition:all var(--d) var(--e)}
.fc:focus{border-color:var(--g400);background:var(--white);box-shadow:0 0 0 3px rgba(45,122,88,.1)}
.fc::placeholder{color:#bbb}
.saldo-box{background:var(--g50);border:1px solid var(--g100);border-radius:var(--rs);padding:.8rem 1rem;text-align:center}
.saldo-box-lbl{font-size:.65rem;color:var(--n400);text-transform:uppercase;letter-spacing:.06em}
.saldo-box-val{font-family:var(--fd);font-size:1.6rem;font-weight:900;color:var(--g600);margin-top:.15rem}
.info-box{padding:.6rem .85rem;background:var(--g25);border:1px solid var(--n100);border-radius:var(--rs);font-size:.76rem;color:var(--n400);line-height:1.6}
.alert-modal{padding:.6rem .85rem;border-radius:var(--rs);font-size:.78rem;display:none}
.alert-modal.show{display:block}
.alert-modal.err{background:var(--red-l);border:1px solid rgba(217,64,64,.2);color:var(--red)}
.alert-modal.ok{background:var(--g50);border:1px solid var(--g100);color:var(--g600)}
.btn-ghost{padding:.5rem 1rem;background:none;border:1px solid var(--n100);border-radius:var(--rs);color:var(--n400);font-family:var(--fb);font-size:.8rem;cursor:pointer;transition:all var(--d) var(--e)}
.btn-ghost:hover{border-color:var(--n200);color:var(--n800)}
.btn-primary{padding:.55rem 1.2rem;background:linear-gradient(135deg,var(--g400),var(--g700));color:#fff;border:none;border-radius:var(--rs);font-family:var(--fb);font-size:.82rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);box-shadow:0 3px 12px rgba(45,122,88,.22)}
.btn-primary:hover{transform:translateY(-1px)}
.btn-primary:disabled{opacity:.5;cursor:not-allowed;transform:none}

#toasts{position:fixed;bottom:1.2rem;right:1.2rem;z-index:999;display:flex;flex-direction:column;gap:.35rem;pointer-events:none}
.toast{background:var(--n800);color:#eee;padding:.55rem .9rem;border-radius:var(--rs);font-size:.78rem;display:flex;align-items:center;gap:.4rem;animation:tin .22s var(--e) both;max-width:280px;box-shadow:var(--sh3);pointer-events:all}
.toast.ok{border-left:3px solid var(--g400)}.toast.err{border-left:3px solid #f87171}
@keyframes tin{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}

@media(max-width:1000px){.fin-grid{grid-template-columns:1fr}.stats-row{grid-template-columns:1fr 1fr}}
@media(max-width:768px){.main{margin-left:0}.page{padding:1rem}.saldo-hero{grid-template-columns:1fr}.sh-actions{align-items:flex-start}.stats-row{grid-template-columns:1fr}}
</style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
  <header class="topbar">
    <span class="tb-title">💰 Financeiro</span>
    <div style="font-size:.72rem;color:var(--n400)"><?= date('d/m/Y · H:i') ?></div>
  </header>

  <main class="page">

    <!-- Hero saldo -->
    <div class="saldo-hero">
      <div>
        <div class="sh-lbl">Saldo disponível</div>
        <div class="sh-val" id="heroSaldo"><span>R$</span>—</div>
        <div class="sh-pending" id="heroPending">Pendente: <strong>—</strong></div>
        <div class="sh-commission" id="heroComm">Comissão: —% redação · —% aula</div>
      </div>
      <div class="sh-actions">
        <button class="btn-saque" onclick="openSaque()">💸 Solicitar saque</button>
        <button class="btn-refresh" onclick="init()">↻ Atualizar</button>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat" style="--stat-color:var(--g400)">
        <div class="stat-lbl">Ganhos este mês</div>
        <div class="stat-val green" id="statGanhos">—</div>
        <div class="stat-sub">Líquido após comissão</div>
      </div>
      <div class="stat" style="--stat-color:var(--gold)">
        <div class="stat-lbl">Saques este mês</div>
        <div class="stat-val" id="statSaques">—</div>
        <div class="stat-sub">Total solicitado</div>
      </div>
      <div class="stat" style="--stat-color:var(--g300)">
        <div class="stat-lbl">Saldo pendente</div>
        <div class="stat-val" id="statPendente">—</div>
        <div class="stat-sub">Aguardando liberação</div>
      </div>
    </div>

    <!-- Grid extrato + saques -->
    <div class="fin-grid">

      <div class="widget">
        <div class="wh">
          <span class="wh-title">📋 Extrato de pedidos</span>
          <span class="wh-sub" id="extratoSub">carregando…</span>
        </div>
        <div class="extrato-list" id="extratoList">
          <div class="empty">Carregando…</div>
        </div>
      </div>

      <div class="widget">
        <div class="wh">
          <span class="wh-title">💸 Histórico de saques</span>
          <span class="wh-sub">Últimos 10</span>
        </div>
        <div class="saque-list" id="saqueList">
          <div class="empty">Carregando…</div>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- Modal saque -->
<div class="overlay" id="overlaySaque">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title">Solicitar saque</span>
      <button class="modal-x" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body">
      <div class="saldo-box">
        <div class="saldo-box-lbl">Saldo disponível</div>
        <div class="saldo-box-val" id="modalSaldo">—</div>
      </div>
      <div class="alert-modal" id="saqueAlert"></div>
      <div class="fg">
        <label class="fl">Valor a sacar (R$)</label>
        <input class="fc" type="number" id="saqueValor" min="50" step="10" placeholder="Mínimo R$ 50,00"/>
      </div>
      <div class="fg">
        <label class="fl">Chave PIX</label>
        <input class="fc" type="text" id="saquePix" placeholder="CPF, e-mail, telefone ou chave aleatória"/>
      </div>
      <div class="info-box">
        ⏱ Processamento em até <strong>1 dia útil</strong> após aprovação.
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-ghost" onclick="closeModal()">Cancelar</button>
      <button class="btn-primary" id="btnSaque" onclick="submitSaque()">Solicitar saque</button>
    </div>
  </div>
</div>

<div id="toasts"></div>

<script>
const API_FIN = '<?= TEACHER_API ?>/financeiro.php';
let currentBalance = 0;

function toast(msg,type='ok',ms=3200){
  const w=document.getElementById('toasts'),d=document.createElement('div');
  d.className=`toast ${type}`;
  d.innerHTML=`<span>${type==='ok'?'✅':'❌'}</span><span>${msg}</span>`;
  w.appendChild(d);
  setTimeout(()=>{d.style.opacity='0';d.style.transition='.3s';setTimeout(()=>d.remove(),300);},ms);
}

async function api(body){
  const r=await fetch(API_FIN,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  return r.json();
}

function money(v){
  const n=parseFloat(v||0);
  return 'R$ '+n.toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.');
}
function esc(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}

const STATUS_SAQUE={
  solicitado:{lbl:'Solicitado', bg:'#eff4ff',color:'#2563eb'},
  aprovado:  {lbl:'Aprovado',   bg:'#eaf6f0',color:'#2d7a58'},
  pago:      {lbl:'Pago',       bg:'#eaf6f0',color:'#2d7a58'},
  recusado:  {lbl:'Recusado',   bg:'#fdeaea',color:'#d94040'},
};

async function loadSummary(){
  const d=await api({action:'summary'});
  if(!d.success) return;
  const s=d.data;
  currentBalance=parseFloat(s.balance||0);

  document.getElementById('heroSaldo').innerHTML=
    `<span>R$</span>${currentBalance.toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.')}`;
  document.getElementById('heroPending').innerHTML=
    `Pendente: <strong>${money(s.balance_pending)}</strong>`;
  document.getElementById('heroComm').textContent=
    `Comissão: ${s.commission_pct}% redação · ${s.commission_aula}% aula`;
  document.getElementById('statGanhos').textContent=money(s.ganhos_mes);
  document.getElementById('statSaques').textContent=money(s.saques_mes);
  document.getElementById('statPendente').textContent=money(s.balance_pending);
  document.getElementById('modalSaldo').textContent=money(s.balance);
}

async function loadExtrato(){
  const d=await api({action:'extrato'});
  if(!d.success) return;

  const pedidos=d.data.pedidos||[];
  const saques=d.data.saques||[];

  document.getElementById('extratoSub').textContent=pedidos.length+' registro'+(pedidos.length!==1?'s':'');

  const elE=document.getElementById('extratoList');
  if(!pedidos.length){
    elE.innerHTML='<div class="empty">Nenhum pedido ainda.</div>';
  } else {
    elE.innerHTML=pedidos.map(p=>{
      const isAula=p.type==='aula';
      const dt=new Date(p.created_at).toLocaleDateString('pt-BR',{day:'2-digit',month:'short'});
      return `<div class="extrato-item">
        <div class="ei-ico ${p.type}">${isAula?'📅':'📝'}</div>
        <div class="ei-info">
          <div class="ei-aluno">${esc(p.student_name||'—')}</div>
          <div class="ei-type">${isAula?'Aula':'Correção de redação'}</div>
        </div>
        <div class="ei-right">
          <div class="ei-val">${money(p.net_amount)}</div>
          <div class="ei-comm">-${money(p.commission_amt)} comissão</div>
          <div class="ei-date">${dt}</div>
        </div>
      </div>`;
    }).join('');
  }

  const elS=document.getElementById('saqueList');
  if(!saques.length){
    elS.innerHTML='<div class="empty">Nenhum saque solicitado.</div>';
  } else {
    elS.innerHTML=saques.map(s=>{
      const sm=STATUS_SAQUE[s.status]||STATUS_SAQUE.solicitado;
      const dt=new Date(s.created_at).toLocaleDateString('pt-BR',{day:'2-digit',month:'short'});
      return `<div class="saque-item">
        <span class="si-status" style="background:${sm.bg};color:${sm.color}">${sm.lbl}</span>
        <div class="si-info">
          <div class="si-pix">${esc(s.pix_key)}</div>
          <div class="si-date">${dt}</div>
        </div>
        <span class="si-val">${money(s.amount)}</span>
      </div>`;
    }).join('');
  }
}

// ── Saque ─────────────────────────────────────────────────────
function openSaque(){
  document.getElementById('overlaySaque').classList.add('open');
  document.body.style.overflow='hidden';
  document.getElementById('saqueValor').value='';
  document.getElementById('saquePix').value='';
  document.getElementById('saqueAlert').className='alert-modal';
}

function closeModal(){
  document.getElementById('overlaySaque').classList.remove('open');
  document.body.style.overflow='';
}

function showAlert(msg,type='err'){
  const el=document.getElementById('saqueAlert');
  el.textContent=msg;el.className=`alert-modal ${type} show`;
}

async function submitSaque(){
  const amount=parseFloat(document.getElementById('saqueValor').value||0);
  const pix_key=document.getElementById('saquePix').value.trim();

  if(amount<50){showAlert('Valor mínimo: R$ 50,00.');return;}
  if(amount>currentBalance){showAlert('Saldo insuficiente.');return;}
  if(!pix_key){showAlert('Informe a chave PIX.');return;}

  const btn=document.getElementById('btnSaque');
  btn.disabled=true;btn.textContent='Enviando…';

  const d=await api({action:'saque_solicitar',amount,pix_key});
  if(d.success){
    toast('Solicitação enviada! ✅');
    closeModal();
    init();
  } else {
    showAlert(d.message||'Erro ao solicitar.');
  }
  btn.disabled=false;btn.textContent='Solicitar saque';
}

document.getElementById('overlaySaque').addEventListener('click',e=>{
  if(e.target===document.getElementById('overlaySaque')) closeModal();
});
document.addEventListener('keydown',e=>{if(e.key==='Escape') closeModal();});

async function init(){
  await loadSummary();
  await loadExtrato();
}

init();
</script>
</body>
</html>