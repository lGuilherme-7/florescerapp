<?php
// ============================================================
// /professor/teachers/views/redacoes.php
// ============================================================

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireTeacher();
$teacher     = currentTeacher();
$currentPage = 'redacoes';

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
<title>Redações — Professor</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,900;1,9..144,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --g900:#0d2618;--g800:#14382a;--g700:#1a4a37;--g600:#225c44;
  --g500:#2d7a58;--g400:#3d9970;--g300:#55b88a;--g200:#8dd4b0;
  --g100:#c2ead6;--g50:#eaf6f0;--g25:#f4fbf7;
  --white:#fff;--n800:#111c16;--n600:#2a3d32;--n400:#5a7a68;
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

.filter-row{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}
.filter-btn{padding:.38rem .85rem;border-radius:50px;border:1px solid var(--n100);background:var(--white);font-family:var(--fb);font-size:.76rem;font-weight:500;color:var(--n400);cursor:pointer;transition:all var(--d) var(--e)}
.filter-btn:hover{border-color:var(--g300);color:var(--g600)}
.filter-btn.active{background:var(--g600);border-color:var(--g600);color:#fff}
.filter-count{font-size:.72rem;color:var(--n400);margin-left:auto}

.redacao-list{display:flex;flex-direction:column;gap:.6rem}
.redacao-card{background:var(--white);border:1px solid var(--n100);border-radius:var(--r);box-shadow:var(--sh0);cursor:pointer;transition:transform var(--d) var(--e),box-shadow var(--d) var(--e);display:flex;overflow:hidden}
.redacao-card:hover{transform:translateX(3px);box-shadow:var(--sh1)}
.rc-urgency{width:4px;flex-shrink:0}
.rc-body{flex:1;padding:.85rem 1.1rem;display:flex;align-items:center;gap:.9rem}
.rc-av{width:36px;height:36px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,var(--g100),var(--g200));display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-size:.9rem;font-weight:700;color:var(--g700)}
.rc-info{flex:1;min-width:0}
.rc-aluno{font-size:.84rem;font-weight:600;color:var(--n800)}
.rc-tema{font-size:.72rem;color:var(--n400);margin-top:.1rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rc-meta{display:flex;align-items:center;gap:.6rem;margin-top:.3rem}
.rc-status{font-size:.65rem;font-weight:600;padding:.1rem .45rem;border-radius:20px}
.rc-time{font-size:.65rem;color:var(--n400)}
.rc-nota{font-family:var(--fd);font-size:1rem;font-weight:700;flex-shrink:0}
.rc-arrow{font-size:.8rem;color:var(--n200);flex-shrink:0}

.overlay{position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.5);backdrop-filter:blur(8px);display:flex;align-items:flex-start;justify-content:center;padding:1.2rem;opacity:0;pointer-events:none;transition:opacity var(--d) var(--e);overflow-y:auto}
.overlay.open{opacity:1;pointer-events:all}
.modal{background:var(--white);border:1px solid var(--n100);border-radius:18px;width:100%;max-width:760px;box-shadow:var(--sh3);transform:translateY(14px) scale(.97);transition:transform var(--d) var(--e);margin:auto}
.overlay.open .modal{transform:none}
.modal-head{padding:.9rem 1.3rem;border-bottom:1px solid var(--n100);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--white);z-index:1;border-radius:18px 18px 0 0}
.modal-title{font-family:var(--fd);font-size:.95rem;font-weight:700;color:var(--n800)}
.modal-x{width:28px;height:28px;border-radius:50%;background:var(--n50);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.78rem;color:var(--n400);transition:all var(--d) var(--e)}
.modal-x:hover{background:var(--red-l);color:var(--red)}
.modal-body{padding:1.3rem;display:flex;flex-direction:column;gap:1.1rem}
.redacao-text-box{background:var(--g25);border:1px solid var(--n100);border-radius:var(--rs);padding:1rem;font-size:.85rem;line-height:1.9;color:var(--n800);max-height:280px;overflow-y:auto}
.comp-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:.6rem}
.comp-item label{display:block;font-size:.63rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--n400);margin-bottom:.28rem;text-align:center}
.comp-item input{width:100%;padding:.5rem .4rem;text-align:center;background:var(--g25);border:1.5px solid var(--n100);border-radius:var(--rs);color:var(--n800);font-family:var(--fd);font-size:1rem;font-weight:700;outline:none;transition:all var(--d) var(--e)}
.comp-item input:focus{border-color:var(--g400);background:var(--white);box-shadow:0 0 0 3px rgba(45,122,88,.1)}
.nota-total-wrap{text-align:center;padding:.8rem;background:var(--g50);border:1px solid var(--g100);border-radius:var(--rs)}
.nota-total-lbl{font-size:.65rem;text-transform:uppercase;letter-spacing:.08em;color:var(--n400);margin-bottom:.2rem}
.nota-total-val{font-family:var(--fd);font-size:2rem;font-weight:900;color:var(--g600)}
.fl{display:block;font-size:.75rem;font-weight:500;color:var(--n400);margin-bottom:.28rem}
.feedback-area{width:100%;padding:.75rem .9rem;background:var(--g25);border:1.5px solid var(--n100);border-radius:var(--rs);color:var(--n800);font-family:var(--fb);font-size:.85rem;line-height:1.7;resize:vertical;min-height:140px;outline:none;transition:all var(--d) var(--e)}
.feedback-area:focus{border-color:var(--g400);background:var(--white);box-shadow:0 0 0 3px rgba(45,122,88,.1)}
.modal-foot{padding:.85rem 1.3rem;border-top:1px solid var(--n100);display:flex;gap:.5rem;justify-content:flex-end;background:var(--white);border-radius:0 0 18px 18px}
.btn-ghost{padding:.5rem 1rem;background:none;border:1px solid var(--n100);border-radius:var(--rs);color:var(--n400);font-family:var(--fb);font-size:.8rem;cursor:pointer;transition:all var(--d) var(--e)}
.btn-ghost:hover{border-color:var(--n200);color:var(--n800)}
.btn-primary{padding:.52rem 1.2rem;background:linear-gradient(135deg,var(--g400),var(--g700));color:#fff;border:none;border-radius:var(--rs);font-family:var(--fb);font-size:.82rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);box-shadow:0 3px 12px rgba(45,122,88,.25)}
.btn-primary:hover{transform:translateY(-1px)}
.btn-primary:disabled{opacity:.5;cursor:not-allowed;transform:none}

.empty{text-align:center;padding:3.5rem 1rem;color:var(--n400)}
.empty-ico{font-size:2.5rem;opacity:.35;display:block;margin-bottom:.7rem}

#toasts{position:fixed;bottom:1.2rem;right:1.2rem;z-index:999;display:flex;flex-direction:column;gap:.35rem;pointer-events:none}
.toast{background:var(--n800);color:#eee;padding:.55rem .9rem;border-radius:var(--rs);font-size:.78rem;display:flex;align-items:center;gap:.4rem;animation:tin .22s var(--e) both;max-width:270px;box-shadow:var(--sh3);pointer-events:all}
.toast.ok{border-left:3px solid var(--g400)}.toast.err{border-left:3px solid #f87171}
@keyframes tin{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}
@media(max-width:768px){.main{margin-left:0}.page{padding:1rem}}
</style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
  <header class="topbar">
    <span class="tb-title">📝 Redações</span>
    <div style="font-size:.72rem;color:var(--n400)"><?= date('d/m/Y · H:i') ?></div>
  </header>

  <main class="page">
    <div class="filter-row">
      <button class="filter-btn active" onclick="filterBy('',this)">Todas</button>
      <button class="filter-btn" onclick="filterBy('pendente',this)">⏳ Pendentes</button>
      <button class="filter-btn" onclick="filterBy('em_correcao',this)">✏️ Em correção</button>
      <button class="filter-btn" onclick="filterBy('corrigida',this)">✅ Corrigidas</button>
      <span class="filter-count" id="filterCount"></span>
    </div>
    <div class="redacao-list" id="redacaoList">
      <div class="empty"><span class="empty-ico">⏳</span>Carregando…</div>
    </div>
  </main>
</div>

<!-- Modal correção -->
<div class="overlay" id="overlay">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title" id="modalTitle">Corrigir redação</span>
      <button class="modal-x" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body" id="modalBody"></div>
    <div class="modal-foot" id="modalFoot" style="display:none">
      <button class="btn-ghost" onclick="closeModal()">Cancelar</button>
      <button class="btn-primary" id="btnCorrigir" onclick="submitCorrecao()">✓ Salvar correção</button>
    </div>
  </div>
</div>

<div id="toasts"></div>

<script>
const API_RED = '<?= TEACHER_API ?>/redacoes.php';
let currentId = null;
let currentStatus = '';

function toast(msg,type='ok',ms=3200){
  const w=document.getElementById('toasts'),d=document.createElement('div');
  d.className=`toast ${type}`;
  d.innerHTML=`<span>${type==='ok'?'✅':'❌'}</span><span>${msg}</span>`;
  w.appendChild(d);
  setTimeout(()=>{d.style.opacity='0';d.style.transition='.3s';setTimeout(()=>d.remove(),300);},ms);
}

async function api(body){
  const r=await fetch(API_RED,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  return r.json();
}

function esc(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}

const STATUS_META={
  pendente:    {lbl:'Pendente',    bg:'#fdeaea',color:'#d94040'},
  em_correcao: {lbl:'Em correção', bg:'#fdf3d8',color:'#c9a84c'},
  corrigida:   {lbl:'Corrigida',   bg:'#eaf6f0',color:'#2d7a58'},
};

function renderList(list){
  const el=document.getElementById('redacaoList');
  const n=list.length;
  document.getElementById('filterCount').textContent=n+' redaç'+(n===1?'ão':'ões');
  if(!n){el.innerHTML='<div class="empty"><span class="empty-ico">📝</span>Nenhuma redação encontrada.</div>';return;}
  const urgC={pendente:'#d94040',em_correcao:'#c9a84c',corrigida:'#2d7a58'};
  el.innerHTML=list.map(r=>{
    const sm=STATUS_META[r.status]||STATUS_META.pendente;
    const ini=(r.student_name||'?')[0].toUpperCase();
    const nota=r.nota_total?`<span class="rc-nota" style="color:var(--g600)">${r.nota_total}/1000</span>`:'';
    return `<div class="redacao-card" onclick="openRedacao(${r.id})">
      <div class="rc-urgency" style="background:${urgC[r.status]||'#ccc'}"></div>
      <div class="rc-body">
        <div class="rc-av">${r.student_emoji||ini}</div>
        <div class="rc-info">
          <div class="rc-aluno">${esc(r.student_name||'—')}</div>
          <div class="rc-tema">${esc(r.tema||'—')}</div>
          <div class="rc-meta">
            <span class="rc-status" style="background:${sm.bg};color:${sm.color}">${sm.lbl}</span>
            <span class="rc-time">${timeAgo(r.created_at)}</span>
          </div>
        </div>
        ${nota}
        <span class="rc-arrow">›</span>
      </div>
    </div>`;
  }).join('');
}

function filterBy(status,btn){
  document.querySelectorAll('.filter-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  currentStatus=status;
  loadList();
}

async function loadList(){
  document.getElementById('redacaoList').innerHTML='<div class="empty"><span class="empty-ico">⏳</span>Carregando…</div>';
  const d=await api({action:'list',status:currentStatus});
  renderList(d.data||[]);
}

async function openRedacao(id){
  currentId=id;
  document.getElementById('overlay').classList.add('open');
  document.getElementById('modalBody').innerHTML='<div style="text-align:center;padding:2rem;color:#aaa">Carregando…</div>';
  document.getElementById('modalFoot').style.display='none';
  document.body.style.overflow='hidden';

  const d=await api({action:'get',id});
  if(!d.success){toast(d.message,'err');closeModal();return;}
  const r=d.data;

  document.getElementById('modalTitle').textContent='Redação de '+(r.student_name||'—');
  const isPending=r.status!=='corrigida';

  document.getElementById('modalBody').innerHTML=`
    <div>
      <div style="font-size:.72rem;color:var(--n400);margin-bottom:.35rem">📌 Tema</div>
      <div style="font-size:.88rem;font-weight:600;color:var(--n800)">${esc(r.tema||'—')}</div>
    </div>
    <div>
      <div style="font-size:.72rem;color:var(--n400);margin-bottom:.35rem">📄 Texto do aluno</div>
      <div class="redacao-text-box">${esc(r.texto||'').replace(/\n/g,'<br>')}</div>
    </div>
    ${isPending?`
    <div>
      <div style="font-size:.75rem;font-weight:600;color:var(--n800);margin-bottom:.6rem">🎯 Competências ENEM (0–200 cada)</div>
      <div class="comp-grid">
        ${[1,2,3,4,5].map(c=>`<div class="comp-item"><label>C${c}</label><input type="number" id="comp${c}" min="0" max="200" step="40" value="120" oninput="updateNota()"/></div>`).join('')}
      </div>
    </div>
    <div class="nota-total-wrap">
      <div class="nota-total-lbl">Nota total</div>
      <div><span class="nota-total-val" id="notaTotal">600</span><span style="font-size:.75rem;color:var(--n400)"> / 1000</span></div>
    </div>
    <div>
      <label class="fl">✍️ Feedback detalhado</label>
      <textarea class="feedback-area" id="feedbackTxt" placeholder="Escreva um feedback construtivo…"></textarea>
    </div>`:`
    <div style="background:var(--g50);border:1px solid var(--g100);border-radius:var(--rs);padding:1rem">
      <div style="font-size:.72rem;color:var(--n400);margin-bottom:.5rem">Competências</div>
      <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:.5rem;margin-bottom:.8rem">
        ${[1,2,3,4,5].map(c=>`<div style="text-align:center"><div style="font-size:.6rem;color:var(--n400)">C${c}</div><div style="font-family:var(--fd);font-size:1.1rem;font-weight:700;color:var(--g600)">${r['comp'+c]||0}</div></div>`).join('')}
      </div>
      <div style="text-align:center"><span style="font-family:var(--fd);font-size:1.8rem;font-weight:900;color:var(--g600)">${r.nota_total||0}</span><span style="font-size:.8rem;color:var(--n400)"> / 1000</span></div>
      ${r.feedback?`<div style="margin-top:.8rem;padding-top:.8rem;border-top:1px solid var(--g100)"><div style="font-size:.72rem;color:var(--n400);margin-bottom:.3rem">Feedback enviado</div><div style="font-size:.82rem;color:var(--n800);line-height:1.7">${esc(r.feedback)}</div></div>`:''}
    </div>`}`;

  if(isPending){document.getElementById('modalFoot').style.display='flex';updateNota();}
}

function updateNota(){
  const vals=[1,2,3,4,5].map(c=>Math.min(200,Math.max(0,parseInt(document.getElementById('comp'+c)?.value)||0)));
  const el=document.getElementById('notaTotal');
  if(el) el.textContent=vals.reduce((a,b)=>a+b,0);
}

async function submitCorrecao(){
  const feedback=document.getElementById('feedbackTxt')?.value?.trim();
  if(!feedback){toast('Escreva o feedback.','err');return;}
  const comps={};
  [1,2,3,4,5].forEach(c=>{comps['comp'+c]=Math.min(200,Math.max(0,parseInt(document.getElementById('comp'+c)?.value)||0));});
  const btn=document.getElementById('btnCorrigir');
  btn.disabled=true;btn.textContent='Salvando…';
  const d=await api({action:'corrigir',id:currentId,feedback,...comps});
  if(d.success){toast('Redação corrigida! ✅');closeModal();loadList();}
  else toast(d.message||'Erro.','err');
  btn.disabled=false;btn.textContent='✓ Salvar correção';
}

function closeModal(){
  document.getElementById('overlay').classList.remove('open');
  document.body.style.overflow='';
  currentId=null;
}

document.getElementById('overlay').addEventListener('click',e=>{if(e.target===document.getElementById('overlay'))closeModal();});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});

function timeAgo(dt){
  const diff=Math.floor((Date.now()-new Date(dt).getTime())/1000);
  if(diff<60) return 'agora';
  if(diff<3600) return Math.floor(diff/60)+'min atrás';
  if(diff<86400) return Math.floor(diff/3600)+'h atrás';
  if(diff<604800) return Math.floor(diff/86400)+'d atrás';
  return new Date(dt).toLocaleDateString('pt-BR');
}

loadList();
</script>
</body>
</html>