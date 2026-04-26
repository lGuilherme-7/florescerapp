<?php
// ============================================================
// /professor/teachers/views/aulas.php
// ============================================================

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireTeacher();
$teacher     = currentTeacher();
$currentPage = 'aulas';

$pendingRed = (int)(dbRow(
    'SELECT COUNT(*) AS n FROM teacher_redacoes WHERE teacher_id = ? AND status = "pendente"',
    [(int)$teacher['id']]
)['n'] ?? 0);
$unreadMsgs = (int)(dbRow(
    "SELECT COUNT(*) AS n FROM teacher_messages
     WHERE teacher_id = ? AND sender='student' AND read_at IS NULL",
    [(int)$teacher['id']]
)['n'] ?? 0);

$weekdays = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Aulas — Professor</title>
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
  --gold:#c9a84c;--red:#d94040;--red-l:#fdeaea;
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
.page{padding:1.5rem 1.8rem;flex:1;display:flex;flex-direction:column;gap:1rem}

.tabs-row{display:flex;gap:.4rem;background:var(--white);border:1px solid var(--n100);border-radius:50px;padding:4px;width:fit-content}
.tab-btn{padding:.4rem 1.1rem;border-radius:50px;border:none;background:transparent;font-family:var(--fb);font-size:.78rem;font-weight:500;color:var(--n400);cursor:pointer;transition:all var(--d) var(--e)}
.tab-btn.active{background:var(--g600);color:#fff;box-shadow:0 2px 8px rgba(45,122,88,.22)}

.btn-primary{display:inline-flex;align-items:center;gap:.38rem;padding:.48rem 1rem;background:linear-gradient(135deg,var(--g500),var(--g700));color:#fff;border:none;border-radius:50px;font-family:var(--fb);font-size:.78rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);box-shadow:0 3px 10px rgba(45,122,88,.22)}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(45,122,88,.32)}

/* Aulas agendadas */
.aulas-grid{display:flex;flex-direction:column;gap:.65rem}
.aula-card{background:var(--white);border:1px solid var(--n100);border-radius:var(--r);box-shadow:var(--sh0);display:flex;overflow:hidden;transition:transform var(--d) var(--e),box-shadow var(--d) var(--e)}
.aula-card:hover{transform:translateX(3px);box-shadow:var(--sh1)}
.ac-stripe{width:4px;flex-shrink:0}
.ac-body{flex:1;padding:.85rem 1.1rem;display:flex;align-items:center;gap:.9rem;flex-wrap:wrap}
.ac-av{width:38px;height:38px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,var(--g100),var(--g200));display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-size:.95rem;font-weight:700;color:var(--g700)}
.ac-info{flex:1;min-width:0}
.ac-aluno{font-size:.85rem;font-weight:600;color:var(--n800)}
.ac-sub{font-size:.71rem;color:var(--n400);margin-top:.06rem}
.ac-right{display:flex;flex-direction:column;align-items:flex-end;gap:.4rem;flex-shrink:0}
.ac-valor{font-family:var(--fd);font-size:.9rem;font-weight:700;color:var(--g600)}
.btn-link{padding:.35rem .8rem;border-radius:50px;border:none;font-family:var(--fb);font-size:.73rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);display:inline-flex;align-items:center;gap:.3rem;white-space:nowrap}
.btn-link.locked{background:var(--n50);border:1px solid var(--n100);color:var(--n400);cursor:not-allowed}
.btn-link.ready{background:linear-gradient(135deg,var(--g400),var(--g600));color:#fff;box-shadow:0 3px 10px rgba(45,122,88,.22)}
.btn-link.ready:hover{transform:translateY(-1px)}
.countdown-tag{font-size:.65rem;color:var(--n400);text-align:right}
.countdown-tag.soon{color:var(--g500);font-weight:600;animation:pulse 1.5s ease-in-out infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}

/* Slots */
.slots-section{display:flex;flex-direction:column;gap:1rem}
.slots-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.6rem}
.slots-title{font-family:var(--fd);font-size:1rem;font-weight:700;color:var(--n800)}
.slots-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:.7rem}
.slot-card{background:var(--white);border:1px solid var(--n100);border-radius:var(--r);padding:.9rem 1.1rem;box-shadow:var(--sh0);display:flex;align-items:center;gap:.8rem}
.slot-day{font-size:.72rem;font-weight:600;color:var(--n400);text-transform:uppercase;letter-spacing:.06em;min-width:58px}
.slot-time{font-family:var(--fd);font-size:.95rem;font-weight:700;color:var(--n800);flex:1}
.slot-price{font-size:.82rem;font-weight:600;color:var(--g600);white-space:nowrap}
.slot-del{width:26px;height:26px;border-radius:50%;background:none;border:1px solid rgba(217,64,64,.15);color:rgba(217,64,64,.5);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.72rem;transition:all var(--d) var(--e);flex-shrink:0}
.slot-del:hover{background:var(--red-l);color:var(--red);border-color:rgba(217,64,64,.3)}
.slot-add-card{background:var(--white);border:2px dashed var(--n100);border-radius:var(--r);padding:.9rem 1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.5rem;font-size:.8rem;color:var(--n400);transition:all var(--d) var(--e)}
.slot-add-card:hover{border-color:var(--g300);color:var(--g600);background:var(--g25)}

/* Empty */
.empty{text-align:center;padding:3rem 1rem;color:var(--n400)}
.empty-ico{font-size:2.2rem;opacity:.35;display:block;margin-bottom:.6rem}
.empty p{font-size:.82rem;line-height:1.7}

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
.fc{padding:.6rem .85rem;background:var(--g25);border:1.5px solid var(--n100);border-radius:var(--rs);color:var(--n800);font-family:var(--fb);font-size:.88rem;outline:none;transition:all var(--d) var(--e);appearance:none}
.fc:focus{border-color:var(--g400);background:var(--white);box-shadow:0 0 0 3px rgba(45,122,88,.1)}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:.65rem}
.btn-ghost{padding:.5rem 1rem;background:none;border:1px solid var(--n100);border-radius:var(--rs);color:var(--n400);font-family:var(--fb);font-size:.8rem;cursor:pointer;transition:all var(--d) var(--e)}
.btn-ghost:hover{border-color:var(--n200);color:var(--n800)}

#toasts{position:fixed;bottom:1.2rem;right:1.2rem;z-index:999;display:flex;flex-direction:column;gap:.35rem;pointer-events:none}
.toast{background:var(--n800);color:#eee;padding:.55rem .9rem;border-radius:var(--rs);font-size:.78rem;display:flex;align-items:center;gap:.4rem;animation:tin .22s var(--e) both;max-width:270px;box-shadow:var(--sh3);pointer-events:all}
.toast.ok{border-left:3px solid var(--g400)}.toast.err{border-left:3px solid #f87171}
@keyframes tin{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}

@media(max-width:768px){.main{margin-left:0}.page{padding:1rem}.slots-grid{grid-template-columns:1fr}}
</style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
  <header class="topbar">
    <span class="tb-title">📅 Aulas</span>
    <div style="font-size:.72rem;color:var(--n400)"><?= date('d/m/Y · H:i') ?></div>
  </header>

  <main class="page">

    <div class="tabs-row">
      <button class="tab-btn active" onclick="switchTab('agenda',this)">📅 Agenda</button>
      <button class="tab-btn" onclick="switchTab('horarios',this)">⏰ Meus horários</button>
    </div>

    <!-- Aba agenda -->
    <div id="tabAgenda">
      <div class="aulas-grid" id="aulasList">
        <div class="empty"><span class="empty-ico">⏳</span>Carregando…</div>
      </div>
    </div>

    <!-- Aba horários -->
    <div id="tabHorarios" style="display:none">
      <div class="slots-section">
        <div class="slots-header">
          <span class="slots-title">Horários disponíveis</span>
          <button class="btn-primary" onclick="openAddSlot()">+ Adicionar horário</button>
        </div>
        <div class="slots-grid" id="slotsList">
          <div class="empty"><span class="empty-ico">⏳</span>Carregando…</div>
        </div>
      </div>
    </div>

  </main>
</div>

<!-- Modal: adicionar horário -->
<div class="overlay" id="overlaySlot">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title">Novo horário</span>
      <button class="modal-x" onclick="closeModal('overlaySlot')">✕</button>
    </div>
    <div class="modal-body">
      <div class="fg">
        <label class="fl">Dia da semana</label>
        <select class="fc" id="slotDay">
          <?php foreach($weekdays as $i=>$d): ?>
            <option value="<?= $i ?>"><?= $d ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="frow">
        <div class="fg">
          <label class="fl">Início</label>
          <input class="fc" type="time" id="slotStart" value="08:00"/>
        </div>
        <div class="fg">
          <label class="fl">Fim</label>
          <input class="fc" type="time" id="slotEnd" value="09:00"/>
        </div>
      </div>
      <div class="fg">
        <label class="fl">Valor da aula (R$)</label>
        <input class="fc" type="number" id="slotPrice" min="10" step="5" placeholder="Ex: 80"/>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-ghost" onclick="closeModal('overlaySlot')">Cancelar</button>
      <button class="btn-primary" onclick="saveSlot()">Salvar horário</button>
    </div>
  </div>
</div>

<div id="toasts"></div>

<script>
const API_AULAS = '<?= TEACHER_API ?>/aulas.php';
const WEEKDAYS  = <?= json_encode($weekdays, JSON_UNESCAPED_UNICODE) ?>;
let slots = [];
let aulasList = [];

function toast(msg,type='ok',ms=3200){
  const w=document.getElementById('toasts'),d=document.createElement('div');
  d.className=`toast ${type}`;
  d.innerHTML=`<span>${type==='ok'?'✅':'❌'}</span><span>${msg}</span>`;
  w.appendChild(d);
  setTimeout(()=>{d.style.opacity='0';d.style.transition='.3s';setTimeout(()=>d.remove(),300);},ms);
}

async function api(body){
  const r=await fetch(API_AULAS,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  return r.json();
}

function esc(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}
function money(v){return 'R$ '+parseFloat(v||0).toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.')}

function switchTab(tab,btn){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('tabAgenda').style.display   = tab==='agenda'   ? 'block':'none';
  document.getElementById('tabHorarios').style.display = tab==='horarios' ? 'block':'none';
  if(tab==='horarios') loadSlots();
}

// ── Agenda ────────────────────────────────────────────────────
async function loadAgenda(){
  const d=await api({action:'list',status:'pago'});
  aulasList=d.data||[];
  const el=document.getElementById('aulasList');

  if(!aulasList.length){
    el.innerHTML='<div class="empty"><span class="empty-ico">📅</span><p>Nenhuma aula agendada ainda.</p></div>';
    return;
  }

  el.innerHTML=aulasList.map(a=>{
    const dt      = new Date(a.scheduled_at);
    const dateStr = dt.toLocaleDateString('pt-BR',{weekday:'long',day:'2-digit',month:'short'});
    const timeStr = dt.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});
    const ini     = (a.student_name||'?')[0].toUpperCase();
    const diff    = Math.floor((dt.getTime()-Date.now())/1000);
    const released= diff<=300;
    const stripeC = released ? 'var(--g400)' : diff<3600 ? 'var(--gold)' : 'var(--n200)';

    const countdownHtml = diff>0
      ? `<div class="countdown-tag ${released?'soon':''}" id="cd-${a.id}">
           ${released?'🟢 Disponível agora':'⏳ '+fmtDiff(diff)}
         </div>`
      : `<div class="countdown-tag" style="color:var(--n400)">Encerrada</div>`;

    return `<div class="aula-card">
      <div class="ac-stripe" style="background:${stripeC}"></div>
      <div class="ac-body">
        <div class="ac-av">${a.student_emoji||ini}</div>
        <div class="ac-info">
          <div class="ac-aluno">${esc(a.student_name||'—')}</div>
          <div class="ac-sub">${dateStr} · ${timeStr}</div>
        </div>
        <div class="ac-right">
          <span class="ac-valor">${money(a.net_amount)}</span>
          ${countdownHtml}
          <button class="btn-link ${released?'ready':'locked'}"
                  ${released?`onclick="getLink(${a.id},this)"`:'disabled'}>
            ${released?'🎥 Entrar na aula':'🔒 Link bloqueado'}
          </button>
        </div>
      </div>
    </div>`;
  }).join('');

  startCountdowns();
}

function fmtDiff(diff){
  const m=Math.floor(diff/60);
  return m<60 ? m+'min' : Math.floor(m/60)+'h '+m%60+'min';
}

function startCountdowns(){
  setInterval(()=>{
    aulasList.forEach(a=>{
      const el=document.getElementById('cd-'+a.id);
      if(!el) return;
      const diff=Math.floor((new Date(a.scheduled_at).getTime()-Date.now())/1000);
      if(diff<=0){el.textContent='🔴 Encerrada';return;}
      if(diff<=300){el.textContent='🟢 Disponível agora';el.classList.add('soon');}
      else el.textContent='⏳ '+fmtDiff(diff);
    });
  },30000);
}

async function getLink(id,btn){
  const d=await api({action:'get_link',id});
  if(d.success) window.open(d.data.link,'_blank');
  else toast(d.message,'err');
}

// ── Slots ─────────────────────────────────────────────────────
async function loadSlots(){
  const d=await api({action:'slots_list'});
  slots=d.data||[];
  renderSlots();
}

function renderSlots(){
  const el=document.getElementById('slotsList');
  let html=slots.map((s,i)=>`
    <div class="slot-card">
      <div class="slot-day">${WEEKDAYS[s.weekday]||''}</div>
      <div class="slot-time">${s.time_start} – ${s.time_end}</div>
      <div class="slot-price">${money(s.price)}</div>
      <button class="slot-del" onclick="removeSlot(${i})" title="Remover">✕</button>
    </div>`).join('');
  html+=`<div class="slot-add-card" onclick="openAddSlot()">+ Adicionar horário</div>`;
  el.innerHTML=html;
}

function openAddSlot(){
  document.getElementById('overlaySlot').classList.add('open');
  document.body.style.overflow='hidden';
}

function removeSlot(idx){
  slots.splice(idx,1);
  saveAllSlots();
}

async function saveSlot(){
  const day  =parseInt(document.getElementById('slotDay').value);
  const start=document.getElementById('slotStart').value;
  const end  =document.getElementById('slotEnd').value;
  const price=parseFloat(document.getElementById('slotPrice').value);
  if(!start||!end){toast('Informe os horários.','err');return;}
  if(isNaN(price)||price<10){toast('Valor mínimo: R$10.','err');return;}
  if(start>=end){toast('Início deve ser antes do fim.','err');return;}
  slots.push({weekday:day,time_start:start,time_end:end,price});
  closeModal('overlaySlot');
  await saveAllSlots();
}

async function saveAllSlots(){
  const d=await api({action:'slots_save',slots});
  if(d.success){toast('Horários salvos!');renderSlots();}
  else toast(d.message||'Erro.','err');
}

function closeModal(id){
  document.getElementById(id).classList.remove('open');
  document.body.style.overflow='';
}

document.querySelectorAll('.overlay').forEach(o=>
  o.addEventListener('click',e=>{if(e.target===o)closeModal(o.id);}));
document.addEventListener('keydown',e=>{
  if(e.key==='Escape')
    document.querySelectorAll('.overlay.open').forEach(o=>closeModal(o.id));
});

loadAgenda();
</script>
</body>
</html>