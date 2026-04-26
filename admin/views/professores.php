<?php
// ============================================================
// /florescer/admin/views/professores.php
// ============================================================

require_once __DIR__ . '/../includes/auth_admin.php';
requireAdmin();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Professores — Admin florescer</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --g900:#0d2618;--g800:#14382a;--g700:#1a4a37;--g600:#225c44;
  --g500:#2d7a58;--g400:#3d9970;--g300:#55b88a;--g200:#8dd4b0;
  --g100:#c2ead6;--g50:#eaf6f0;--g25:#f4fbf7;
  --white:#fff;--n800:#111c16;--n400:#5a7a68;
  --n200:#b8d0c4;--n100:#daeae1;--n50:#f2f8f5;
  --gold:#c9a84c;--gold-l:#fdf3d8;--red:#d94040;--red-l:#fdeaea;
  --fd:'Georgia',serif;--fb:'DM Sans',system-ui,sans-serif;
  --r:12px;--rs:8px;--d:.2s;--e:cubic-bezier(.4,0,.2,1);
  --sh0:0 1px 3px rgba(0,0,0,.06);--sh1:0 4px 12px rgba(0,0,0,.07);--sh3:0 20px 48px rgba(0,0,0,.12);
}
html{height:100%}
body{font-family:var(--fb);background:var(--n50);color:var(--n800);min-height:100%;-webkit-font-smoothing:antialiased}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:var(--n200);border-radius:2px}

.page{max-width:1200px;margin:0 auto;padding:1.8rem 1.5rem}

/* Header */
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.8rem}
.ph-title{font-family:var(--fd);font-size:1.4rem;font-weight:700;color:var(--n800)}
.ph-back{font-size:.78rem;color:var(--n400);text-decoration:none;display:flex;align-items:center;gap:.3rem;transition:color var(--d) var(--e)}
.ph-back:hover{color:var(--g600)}

/* Stats */
.stats{display:grid;grid-template-columns:repeat(5,1fr);gap:.7rem;margin-bottom:1.4rem}
.stat{background:var(--white);border:1px solid var(--n100);border-radius:var(--r);padding:.8rem 1rem;box-shadow:var(--sh0);position:relative;overflow:hidden}
.stat::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;background:var(--stat-color,var(--g400))}
.stat-val{font-family:var(--fd);font-size:1.5rem;font-weight:700;color:var(--n800);line-height:1}
.stat-lbl{font-size:.63rem;color:var(--n400);text-transform:uppercase;letter-spacing:.06em;margin-top:.2rem}

/* Tabs */
.tabs{display:flex;gap:.35rem;margin-bottom:1rem;flex-wrap:wrap}
.tab{padding:.42rem 1rem;border-radius:50px;border:1px solid var(--n100);background:var(--white);font-family:var(--fb);font-size:.78rem;font-weight:500;color:var(--n400);cursor:pointer;transition:all var(--d) var(--e)}
.tab:hover{border-color:var(--g300);color:var(--g600)}
.tab.active{background:var(--g600);border-color:var(--g600);color:#fff}
.tab-badge{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;font-size:.6rem;font-weight:700;margin-left:.3rem}
.tab.active .tab-badge{background:rgba(255,255,255,.25);color:#fff}
.tab:not(.active) .tab-badge{background:var(--red-l);color:var(--red)}

/* Toolbar */
.toolbar{display:flex;align-items:center;gap:.6rem;margin-bottom:.9rem;flex-wrap:wrap}
.search-input{padding:.5rem .85rem;background:var(--white);border:1.5px solid var(--n100);border-radius:var(--rs);color:var(--n800);font-family:var(--fb);font-size:.82rem;outline:none;transition:all var(--d) var(--e);min-width:220px}
.search-input:focus{border-color:var(--g400);box-shadow:0 0 0 3px rgba(45,122,88,.08)}
.search-input::placeholder{color:#bbb}
.count-lbl{font-size:.74rem;color:var(--n400);margin-left:auto}

/* Tabela */
.table-wrap{background:var(--white);border:1px solid var(--n100);border-radius:var(--r);box-shadow:var(--sh0);overflow:hidden}
table{width:100%;border-collapse:collapse}
th{padding:.65rem 1rem;font-size:.68rem;font-weight:600;color:var(--n400);text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--n100);text-align:left;background:var(--n50)}
td{padding:.7rem 1rem;border-bottom:1px solid var(--n50);font-size:.82rem;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--g25)}
.tc-av{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--g100),var(--g200));display:inline-flex;align-items:center;justify-content:center;font-family:var(--fd);font-size:.9rem;font-weight:700;color:var(--g700);flex-shrink:0}
.tc-name{font-weight:600;color:var(--n800)}
.tc-email{font-size:.73rem;color:var(--n400)}
.tc-subjects{font-size:.7rem;color:var(--n400);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.badge{font-size:.62rem;font-weight:600;padding:.12rem .45rem;border-radius:20px;display:inline-block}
.badge-pend{background:var(--gold-l);color:#92720c;border:1px solid rgba(201,168,76,.3)}
.badge-ativo{background:var(--g50);color:var(--g600);border:1px solid var(--g100)}
.badge-susp{background:var(--red-l);color:var(--red);border:1px solid rgba(217,64,64,.2)}
.stars{color:var(--gold);font-size:.75rem}
.actions{display:flex;gap:.35rem;align-items:center}
.btn-action{padding:.3rem .7rem;border-radius:var(--rs);border:none;font-family:var(--fb);font-size:.74rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);white-space:nowrap}
.btn-ver{background:var(--n50);color:var(--n400);border:1px solid var(--n100)}
.btn-ver:hover{background:var(--n100);color:var(--n800)}
.btn-ok{background:var(--g50);color:var(--g600);border:1px solid var(--g100)}
.btn-ok:hover{background:var(--g100)}
.btn-nok{background:var(--red-l);color:var(--red);border:1px solid rgba(217,64,64,.15)}
.btn-nok:hover{background:rgba(217,64,64,.15)}
.btn-gold{background:var(--gold-l);color:#92720c;border:1px solid rgba(201,168,76,.3)}
.btn-gold:hover{background:rgba(201,168,76,.2)}

.empty-row td{text-align:center;padding:3rem;color:var(--n400);font-size:.82rem}

/* Paginação */
.pag{display:flex;align-items:center;justify-content:center;gap:.3rem;padding:.9rem;border-top:1px solid var(--n100)}
.pag-btn{padding:.3rem .65rem;border-radius:var(--rs);border:1px solid var(--n100);background:var(--white);color:var(--n400);font-size:.76rem;cursor:pointer;transition:all var(--d) var(--e)}
.pag-btn:hover{border-color:var(--g300);color:var(--g600)}
.pag-btn.cur{background:var(--g600);border-color:var(--g600);color:#fff;font-weight:600}
.pag-btn:disabled{opacity:.3;cursor:not-allowed}

/* Seção saques */
.saques-section{margin-top:1.4rem}
.saques-title{font-family:var(--fd);font-size:1rem;font-weight:700;color:var(--n800);margin-bottom:.7rem;display:flex;align-items:center;gap:.5rem}

/* Modal */
.overlay{position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.45);backdrop-filter:blur(6px);display:flex;align-items:flex-start;justify-content:center;padding:1.5rem;opacity:0;pointer-events:none;transition:opacity var(--d) var(--e);overflow-y:auto}
.overlay.open{opacity:1;pointer-events:all}
.modal{background:var(--white);border:1px solid var(--n100);border-radius:16px;width:100%;max-width:560px;box-shadow:var(--sh3);transform:translateY(12px) scale(.97);transition:transform var(--d) var(--e);margin:auto}
.overlay.open .modal{transform:none}
.modal-head{padding:.85rem 1.2rem;border-bottom:1px solid var(--n100);display:flex;align-items:center;justify-content:space-between}
.modal-title{font-family:var(--fd);font-size:.95rem;font-weight:700;color:var(--n800)}
.modal-x{width:26px;height:26px;border-radius:50%;background:var(--n50);border:none;cursor:pointer;font-size:.76rem;color:var(--n400);transition:all var(--d) var(--e)}
.modal-x:hover{background:var(--red-l);color:var(--red)}
.modal-body{padding:1.2rem;display:flex;flex-direction:column;gap:.8rem}
.modal-foot{padding:.8rem 1.2rem;border-top:1px solid var(--n100);display:flex;gap:.4rem;justify-content:flex-end}

.info-row{display:flex;gap:.5rem;flex-wrap:wrap}
.info-item{flex:1;min-width:120px;padding:.6rem .8rem;background:var(--n50);border:1px solid var(--n100);border-radius:var(--rs)}
.info-item-lbl{font-size:.62rem;color:var(--n400);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.18rem}
.info-item-val{font-size:.84rem;font-weight:600;color:var(--n800)}

.bio-box{background:var(--n50);border:1px solid var(--n100);border-radius:var(--rs);padding:.75rem .9rem;font-size:.8rem;color:var(--n400);line-height:1.7;max-height:160px;overflow-y:auto;white-space:pre-wrap}
.diploma-link{display:inline-flex;align-items:center;gap:.4rem;font-size:.78rem;color:var(--g600);text-decoration:none;padding:.4rem .7rem;background:var(--g50);border:1px solid var(--g100);border-radius:var(--rs)}
.diploma-link:hover{background:var(--g100)}

.senha-box{background:var(--g50);border:1px solid var(--g100);border-radius:var(--rs);padding:.9rem;text-align:center}
.senha-lbl{font-size:.7rem;color:var(--n400);margin-bottom:.3rem}
.senha-val{font-family:monospace;font-size:1.4rem;font-weight:700;color:var(--g700);letter-spacing:.15em}
.senha-hint{font-size:.72rem;color:var(--n400);margin-top:.3rem;line-height:1.5}

.fg{display:flex;flex-direction:column;gap:.25rem}
.fl{font-size:.74rem;font-weight:500;color:var(--n400)}
.fc{padding:.55rem .8rem;background:var(--g25);border:1.5px solid var(--n100);border-radius:var(--rs);color:var(--n800);font-family:var(--fb);font-size:.84rem;outline:none;transition:all var(--d) var(--e)}
.fc:focus{border-color:var(--g400)}
textarea.fc{resize:vertical;min-height:70px;line-height:1.6}

/* Toast */
#toasts{position:fixed;bottom:1.2rem;right:1.2rem;z-index:999;display:flex;flex-direction:column;gap:.35rem;pointer-events:none}
.toast{background:var(--n800);color:#eee;padding:.55rem .9rem;border-radius:var(--rs);font-size:.78rem;display:flex;align-items:center;gap:.4rem;animation:tin .22s var(--e) both;max-width:280px;box-shadow:var(--sh3)}
.toast.ok{border-left:3px solid var(--g400)}.toast.err{border-left:3px solid #f87171}.toast.warn{border-left:3px solid var(--gold)}
@keyframes tin{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}

@media(max-width:900px){.stats{grid-template-columns:repeat(3,1fr)}}
@media(max-width:600px){.stats{grid-template-columns:1fr 1fr}.page{padding:1rem}}
</style>
</head>
<body>

<div class="page">

  <!-- Header -->
  <div class="ph">
    <div>
      <a class="ph-back" href="dashboard.php">← Admin</a>
      <div class="ph-title">👨‍🏫 Professores</div>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats" id="statsRow">
    <div class="stat" style="--stat-color:var(--gold)">
      <div class="stat-val" id="sPend">—</div>
      <div class="stat-lbl">Candidatos pendentes</div>
    </div>
    <div class="stat" style="--stat-color:var(--g400)">
      <div class="stat-val" id="sAtiv">—</div>
      <div class="stat-lbl">Professores ativos</div>
    </div>
    <div class="stat" style="--stat-color:var(--red)">
      <div class="stat-val" id="sSusp">—</div>
      <div class="stat-lbl">Suspensos</div>
    </div>
    <div class="stat" style="--stat-color:var(--g300)">
      <div class="stat-val" id="sSaques">—</div>
      <div class="stat-lbl">Saques pendentes</div>
    </div>
    <div class="stat" style="--stat-color:var(--g500)">
      <div class="stat-val" id="sComissao">—</div>
      <div class="stat-lbl">Comissão do mês</div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab active" onclick="switchTab('pendente',this)">
      ⏳ Candidatos <span class="tab-badge" id="badgePend">0</span>
    </button>
    <button class="tab" onclick="switchTab('ativo',this)">
      ✅ Ativos
    </button>
    <button class="tab" onclick="switchTab('suspenso',this)">
      🚫 Suspensos
    </button>
    <button class="tab" onclick="switchTab('saques',this)">
      💸 Saques pendentes <span class="tab-badge" id="badgeSaques">0</span>
    </button>
  </div>

  <!-- Toolbar -->
  <div class="toolbar" id="toolbar">
    <input class="search-input" type="text" id="searchInput"
           placeholder="Buscar por nome ou e-mail…"
           oninput="debounceSearch(this.value)"/>
    <span class="count-lbl" id="countLbl"></span>
  </div>

  <!-- Tabela professores -->
  <div id="tabelaSection">
    <div class="table-wrap">
      <table>
        <thead id="thead"></thead>
        <tbody id="tbody">
          <tr><td colspan="6" style="text-align:center;padding:2rem;color:#bbb">Carregando…</td></tr>
        </tbody>
      </table>
      <div class="pag" id="pag"></div>
    </div>
  </div>

  <!-- Seção saques -->
  <div id="saquesSection" style="display:none">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Professor</th>
            <th>Chave PIX</th>
            <th>Valor</th>
            <th>Solicitado em</th>
            <th>Saldo atual</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody id="tbodySaques">
          <tr><td colspan="6" style="text-align:center;padding:2rem;color:#bbb">Carregando…</td></tr>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Modal: detalhes + aprovar/recusar/suspender -->
<div class="overlay" id="overlay">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title" id="modalTitle">Professor</span>
      <button class="modal-x" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body" id="modalBody"></div>
    <div class="modal-foot" id="modalFoot"></div>
  </div>
</div>

<!-- Modal: senha aprovação -->
<div class="overlay" id="overlaySenha">
  <div class="modal" style="max-width:380px">
    <div class="modal-head">
      <span class="modal-title">✅ Professor aprovado!</span>
      <button class="modal-x" onclick="document.getElementById('overlaySenha').classList.remove('open')">✕</button>
    </div>
    <div class="modal-body">
      <p style="font-size:.82rem;color:var(--n400);line-height:1.6">
        Envie as credenciais abaixo para o professor via e-mail:
      </p>
      <div class="fg">
        <label class="fl">E-mail do professor</label>
        <div class="fc" id="aprovEmail" style="background:var(--n50)"></div>
      </div>
      <div class="senha-box">
        <div class="senha-lbl">Senha temporária</div>
        <div class="senha-val" id="aprovSenha"></div>
        <div class="senha-hint">
          Peça ao professor trocar a senha no primeiro acesso<br>
          em <strong>Perfil → Alterar senha</strong>
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-action btn-ok" onclick="copiarCredenciais()">📋 Copiar credenciais</button>
      <button class="btn-action btn-ver" onclick="document.getElementById('overlaySenha').classList.remove('open')">Fechar</button>
    </div>
  </div>
</div>

<div id="toasts"></div>

<script>
const API = '/florescer/admin/api/professores.php';
let currentTab   = 'pendente';
let currentPage  = 1;
let searchQ      = '';
let searchTimer  = null;
let totalPages   = 1;

function toast(msg,type='ok',ms=3500){
  const w=document.getElementById('toasts'),d=document.createElement('div');
  d.className=`toast ${type}`;
  d.innerHTML=`<span>${type==='ok'?'✅':type==='err'?'❌':'⚠️'}</span><span>${msg}</span>`;
  w.appendChild(d);
  setTimeout(()=>{d.style.opacity='0';d.style.transition='.3s';setTimeout(()=>d.remove(),320);},ms);
}

async function api(body){
  const r=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  return r.json();
}

function esc(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}
function money(v){return 'R$ '+parseFloat(v||0).toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.')}
function fmtDate(dt){return dt?new Date(dt).toLocaleDateString('pt-BR',{day:'2-digit',month:'short',year:'2-digit',hour:'2-digit',minute:'2-digit'}):'—'}

// ── Stats ─────────────────────────────────────────────────────
async function loadStats(){
  const d=await api({action:'stats'});
  if(!d.success) return;
  const s=d.data;
  document.getElementById('sPend').textContent    = s.pendentes;
  document.getElementById('sAtiv').textContent    = s.ativos;
  document.getElementById('sSusp').textContent    = s.suspensos;
  document.getElementById('sSaques').textContent  = s.saques_pendentes;
  document.getElementById('sComissao').textContent= money(s.comissao_mes);
  document.getElementById('badgePend').textContent  = s.pendentes;
  document.getElementById('badgeSaques').textContent= s.saques_pendentes;
}

// ── Tabs ──────────────────────────────────────────────────────
function switchTab(tab, btn){
  document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
  btn.classList.add('active');
  currentTab  = tab;
  currentPage = 1;
  searchQ     = '';
  document.getElementById('searchInput').value='';

  const isSaque = tab === 'saques';
  document.getElementById('tabelaSection').style.display  = isSaque ? 'none'  : 'block';
  document.getElementById('saquesSection').style.display  = isSaque ? 'block' : 'none';
  document.getElementById('toolbar').style.display        = isSaque ? 'none'  : 'flex';

  if(isSaque) loadSaques();
  else loadList();
}

// ── Lista professores ─────────────────────────────────────────
async function loadList(){
  document.getElementById('tbody').innerHTML=
    '<tr><td colspan="6" style="text-align:center;padding:2rem;color:#bbb">Carregando…</td></tr>';

  const d=await api({action:'list',status:currentTab,q:searchQ,page:currentPage});
  if(!d.success){toast(d.message,'err');return;}

  totalPages=d.pages;
  document.getElementById('countLbl').textContent=d.total+' resultado'+(d.total!==1?'s':'');

  // Thead
  const cols = currentTab==='pendente'
    ? ['','Nome/E-mail','Matérias','Formação','Enviou em','Ações']
    : ['','Nome/E-mail','Matérias','Avaliação','Pedidos','Ações'];
  document.getElementById('thead').innerHTML=
    '<tr>'+cols.map(c=>`<th>${c}</th>`).join('')+'</tr>';

  // Tbody
  const rows = d.data||[];
  if(!rows.length){
    document.getElementById('tbody').innerHTML=
      '<tr class="empty-row"><td colspan="6">Nenhum registro encontrado.</td></tr>';
    document.getElementById('pag').innerHTML='';
    return;
  }

  document.getElementById('tbody').innerHTML=rows.map(t=>{
    const ini=((t.name||'?')[0]).toUpperCase();
    const stars=t.rating_avg>0?'★'.repeat(Math.round(parseFloat(t.rating_avg))):'—';
    const statusBadge={
      pendente:`<span class="badge badge-pend">⏳ Pendente</span>`,
      ativo:   `<span class="badge badge-ativo">✅ Ativo</span>`,
      suspenso:`<span class="badge badge-susp">🚫 Suspenso</span>`,
    }[t.status]||'';

    const actions = currentTab==='pendente' ? `
      <button class="btn-action btn-ok"  onclick="verDetalhes(${t.id})">👁 Ver</button>
      <button class="btn-action btn-ok"  onclick="aprovar(${t.id},'${esc(t.name)}')">✓ Aprovar</button>
      <button class="btn-action btn-nok" onclick="recusar(${t.id},'${esc(t.name)}')">✕ Recusar</button>
    ` : currentTab==='ativo' ? `
      <button class="btn-action btn-ver" onclick="verDetalhes(${t.id})">👁 Ver</button>
      <button class="btn-action btn-nok" onclick="suspender(${t.id},'${esc(t.name)}')">🚫 Suspender</button>
    ` : `
      <button class="btn-action btn-ver"  onclick="verDetalhes(${t.id})">👁 Ver</button>
      <button class="btn-action btn-gold" onclick="reativar(${t.id},'${esc(t.name)}')">↩ Reativar</button>
    `;

    const col3 = currentTab==='pendente'
      ? `<td>${esc(extrairInfo(t.bio,'Formação') || '—')}</td>
         <td style="font-size:.72rem;color:var(--n400)">${fmtDate(t.created_at)}</td>`
      : `<td><span class="stars">${stars}</span> ${t.rating_avg>0?parseFloat(t.rating_avg).toFixed(1):'—'}</td>
         <td>${t.total_pedidos||0}</td>`;

    return `<tr>
      <td><div class="tc-av">${ini}</div></td>
      <td>
        <div class="tc-name">${esc(t.name)} ${statusBadge}</div>
        <div class="tc-email">${esc(t.email)}</div>
      </td>
      <td><div class="tc-subjects" title="${esc(t.subjects||'')}">${esc(t.subjects||'—')}</div></td>
      ${col3}
      <td><div class="actions">${actions}</div></td>
    </tr>`;
  }).join('');

  renderPag();
}

function extrairInfo(bio, campo){
  if(!bio) return null;
  const m=bio.match(new RegExp('\\['+campo+': ([^\\]]+)\\]'));
  return m?m[1]:null;
}

// ── Paginação ─────────────────────────────────────────────────
function renderPag(){
  if(totalPages<=1){document.getElementById('pag').innerHTML='';return;}
  let html='';
  html+=`<button class="pag-btn" ${currentPage<=1?'disabled':''} onclick="goPage(${currentPage-1})">‹</button>`;
  for(let p=Math.max(1,currentPage-2);p<=Math.min(totalPages,currentPage+2);p++){
    html+=`<button class="pag-btn ${p===currentPage?'cur':''}" onclick="goPage(${p})">${p}</button>`;
  }
  html+=`<button class="pag-btn" ${currentPage>=totalPages?'disabled':''} onclick="goPage(${currentPage+1})">›</button>`;
  document.getElementById('pag').innerHTML=html;
}

function goPage(p){currentPage=p;loadList();}

// ── Search ────────────────────────────────────────────────────
function debounceSearch(v){
  clearTimeout(searchTimer);
  searchTimer=setTimeout(()=>{searchQ=v;currentPage=1;loadList();},380);
}

// ── Ver detalhes ──────────────────────────────────────────────
async function verDetalhes(id){
  document.getElementById('overlay').classList.add('open');
  document.getElementById('modalBody').innerHTML='<div style="text-align:center;padding:2rem;color:#bbb">Carregando…</div>';
  document.getElementById('modalFoot').innerHTML='';
  document.body.style.overflow='hidden';

  const d=await api({action:'get',id});
  if(!d.success){toast(d.message,'err');closeModal();return;}
  const t=d.data;

  document.getElementById('modalTitle').textContent=t.name;

  const formacao    = extrairInfo(t.bio,'Formação')||'—';
  const exp         = extrairInfo(t.bio,'Experiência')||'—';
  const materias    = extrairInfo(t.bio,'Matérias')||t.subjects||'—';
  const linkProf    = extrairInfo(t.bio,'Link');
  const bioLimpa    = (t.bio||'').replace(/\[.*?\]/g,'').trim();
  const statusLabel = {pendente:'⏳ Pendente',ativo:'✅ Ativo',suspenso:'🚫 Suspenso'}[t.status]||t.status;

  document.getElementById('modalBody').innerHTML=`
    <div class="info-row">
      <div class="info-item"><div class="info-item-lbl">Status</div><div class="info-item-val">${statusLabel}</div></div>
      <div class="info-item"><div class="info-item-lbl">Experiência</div><div class="info-item-val">${esc(exp)}</div></div>
      <div class="info-item"><div class="info-item-lbl">Redações</div><div class="info-item-val">${t.redacoes_corrigidas||0}</div></div>
      <div class="info-item"><div class="info-item-lbl">Pedidos pagos</div><div class="info-item-val">${t.pedidos_pagos||0}</div></div>
    </div>
    <div class="fg">
      <div class="fl">E-mail</div>
      <div class="fc" style="background:var(--n50)">${esc(t.email)}</div>
    </div>
    <div class="fg">
      <div class="fl">Formação</div>
      <div class="fc" style="background:var(--n50)">${esc(formacao)}</div>
    </div>
    <div class="fg">
      <div class="fl">Matérias</div>
      <div class="fc" style="background:var(--n50)">${esc(materias)}</div>
    </div>
    ${bioLimpa?`
    <div class="fg">
      <div class="fl">Biografia</div>
      <div class="bio-box">${esc(bioLimpa)}</div>
    </div>`:''}
    ${t.avatar_url?`
    <div class="fg">
      <div class="fl">Diploma / Certificado</div>
      <a class="diploma-link" href="${esc(t.avatar_url)}" target="_blank">
        📄 Ver documento enviado
      </a>
    </div>`:''}
    ${linkProf?`
    <div class="fg">
      <div class="fl">Currículo / LinkedIn</div>
      <a href="${esc(linkProf)}" target="_blank" style="font-size:.8rem;color:var(--g500)">${esc(linkProf)}</a>
    </div>`:''}
    ${t.contact_attempts>0?`
    <div style="padding:.6rem .8rem;background:var(--red-l);border:1px solid rgba(217,64,64,.2);border-radius:var(--rs);font-size:.76rem;color:var(--red)">
      ⚠️ ${t.contact_attempts} tentativa(s) de contato externo detectadas.
    </div>`:''}
  `;

  // Botões no footer conforme status
  let footHtml='';
  if(t.status==='pendente'){
    footHtml=`
      <button class="btn-action btn-ok"  onclick="aprovar(${t.id},'${esc(t.name)}')">✓ Aprovar</button>
      <button class="btn-action btn-nok" onclick="recusar(${t.id},'${esc(t.name)}')">✕ Recusar</button>`;
  } else if(t.status==='ativo'){
    footHtml=`<button class="btn-action btn-nok" onclick="suspender(${t.id},'${esc(t.name)}')">🚫 Suspender</button>`;
  } else {
    footHtml=`<button class="btn-action btn-gold" onclick="reativar(${t.id},'${esc(t.name)}')">↩ Reativar</button>`;
  }
  footHtml+=`<button class="btn-action btn-ver" onclick="closeModal()">Fechar</button>`;
  document.getElementById('modalFoot').innerHTML=footHtml;
}

// ── Aprovar ───────────────────────────────────────────────────
async function aprovar(id, nome){
  if(!confirm(`Aprovar ${nome}?`)) return;
  const d=await api({action:'aprovar',id});
  if(!d.success){toast(d.message,'err');return;}
  closeModal();
  document.getElementById('aprovEmail').textContent = d.email;
  document.getElementById('aprovSenha').textContent = d.senha_temp;
  document.getElementById('overlaySenha').classList.add('open');
  toast('Professor aprovado!');
  loadStats();
  loadList();
}

// ── Recusar ───────────────────────────────────────────────────
function recusar(id, nome){
  const motivo=prompt(`Motivo da recusa de ${nome} (opcional):`);
  if(motivo===null) return; // cancelou
  api({action:'recusar',id,motivo}).then(d=>{
    if(d.success){toast('Candidatura recusada.');closeModal();loadStats();loadList();}
    else toast(d.message,'err');
  });
}

// ── Suspender ─────────────────────────────────────────────────
function suspender(id, nome){
  const motivo=prompt(`Motivo da suspensão de ${nome}:`);
  if(!motivo) return;
  api({action:'suspender',id,motivo}).then(d=>{
    if(d.success){toast('Professor suspenso.','warn');closeModal();loadStats();loadList();}
    else toast(d.message,'err');
  });
}

// ── Reativar ──────────────────────────────────────────────────
async function reativar(id, nome){
  if(!confirm(`Reativar ${nome}?`)) return;
  const d=await api({action:'reativar',id});
  if(d.success){toast('Professor reativado!');closeModal();loadStats();loadList();}
  else toast(d.message,'err');
}

// ── Saques ────────────────────────────────────────────────────
async function loadSaques(){
  document.getElementById('tbodySaques').innerHTML=
    '<tr><td colspan="6" style="text-align:center;padding:2rem;color:#bbb">Carregando…</td></tr>';
  const d=await api({action:'saques_list'});
  if(!d.success){toast(d.message,'err');return;}
  const rows=d.data||[];
  if(!rows.length){
    document.getElementById('tbodySaques').innerHTML=
      '<tr class="empty-row"><td colspan="6">Nenhum saque pendente.</td></tr>';
    return;
  }
  document.getElementById('tbodySaques').innerHTML=rows.map(w=>`
    <tr>
      <td>
        <div style="font-weight:600">${esc(w.teacher_name)}</div>
        <div style="font-size:.72rem;color:var(--n400)">${esc(w.teacher_email)}</div>
      </td>
      <td style="font-family:monospace;font-size:.82rem">${esc(w.pix_key)}</td>
      <td style="font-family:Georgia,serif;font-weight:700;color:var(--g600)">${money(w.amount)}</td>
      <td style="font-size:.74rem;color:var(--n400)">${fmtDate(w.created_at)}</td>
      <td style="font-size:.78rem">${money(w.balance)}</td>
      <td>
        <div class="actions">
          <button class="btn-action btn-ok"  onclick="pagarSaque(${w.id})">✓ Pago</button>
          <button class="btn-action btn-nok" onclick="recusarSaque(${w.id})">✕ Recusar</button>
        </div>
      </td>
    </tr>`).join('');
}

async function pagarSaque(id){
  if(!confirm('Confirmar que o PIX foi enviado?')) return;
  const d=await api({action:'saque_pagar',id});
  if(d.success){toast('Saque marcado como pago!');loadSaques();loadStats();}
  else toast(d.message,'err');
}

async function recusarSaque(id){
  if(!confirm('Recusar este saque e devolver o saldo ao professor?')) return;
  const d=await api({action:'saque_recusar',id});
  if(d.success){toast('Saque recusado e saldo devolvido.','warn');loadSaques();loadStats();}
  else toast(d.message,'err');
}

// ── Modal ─────────────────────────────────────────────────────
function closeModal(){
  document.getElementById('overlay').classList.remove('open');
  document.body.style.overflow='';
}
document.getElementById('overlay').addEventListener('click',e=>{
  if(e.target===document.getElementById('overlay')) closeModal();
});
document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeModal(); });

// ── Copiar credenciais ────────────────────────────────────────
function copiarCredenciais(){
  const email=document.getElementById('aprovEmail').textContent;
  const senha=document.getElementById('aprovSenha').textContent;
  const txt=`Olá! Sua candidatura na plataforma Florescer foi aprovada!\n\nAcesse: https://florescerapp.com.br/florescer/teachers/views/index.php\n\nE-mail: ${email}\nSenha temporária: ${senha}\n\nPor segurança, troque a senha no primeiro acesso em Perfil → Alterar senha.\n\nBem-vindo(a) ao Florescer! 🌱`;
  navigator.clipboard.writeText(txt).then(()=>toast('Credenciais copiadas!'));
}

// Init
loadStats();
loadList();
</script>
</body>
</html>