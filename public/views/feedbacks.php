<?php
// /public/views/feedbacks.php — florescer v2.0
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

startSession();
authGuard();

$user        = currentUser();
$userId      = (int)$user['id'];
$currentPage = 'feedbacks';

// Sidebar vars
$userData = dbRow('SELECT xp,level,streak FROM users WHERE id=?',[$userId]);
$xp       = (int)($userData['xp']    ?? 0);
$level    = (int)($userData['level'] ?? 1);
$streak   = (int)($userData['streak']?? 0);
$lvN      = ['','Semente','Broto','Estudante','Aplicado','Dedicado','Avançado','Expert','Mestre','Lendário'];
$lvName   = $lvN[min($level,count($lvN)-1)] ?? 'Lendário';
$stages   = [[0,6,'🌱','Semente'],[7,14,'🌿','Broto Inicial'],[15,29,'☘️','Planta Jovem'],
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
$allObjs = dbQuery('SELECT id,name FROM objectives WHERE user_id=? ORDER BY is_active DESC,created_at DESC',[$userId]);
if (!isset($_SESSION['active_objective'])) {
    $ao=dbRow('SELECT id FROM objectives WHERE user_id=? AND is_active=1 ORDER BY created_at DESC LIMIT 1',[$userId]);
    if(!$ao)$ao=dbRow('SELECT id FROM objectives WHERE user_id=? ORDER BY created_at DESC LIMIT 1',[$userId]);
    $_SESSION['active_objective']=$ao['id']??null;
}
$activeObjId = $_SESSION['active_objective'];
$userName    = htmlspecialchars($user['name']??'Estudante',ENT_QUOTES,'UTF-8');
$userInitial = strtoupper(mb_substr($user['name']??'E',0,1,'UTF-8'));

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
  <title>florescer — Feedbacks</title>
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
  body{font-family:var(--fb);background:var(--n50);color:var(--n800);display:flex;overflow-x:hidden;-webkit-font-smoothing:antialiased}
  ::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:rgba(64,145,108,.2);border-radius:2px}

  /* Main */
  .main{margin-left:var(--sw);flex:1;min-height:100vh;display:flex;flex-direction:column;min-width:0}
  .topbar{height:var(--hh);background:rgba(250,248,245,.94);backdrop-filter:blur(14px);border-bottom:1px solid rgba(0,0,0,.055);display:flex;align-items:center;gap:.8rem;padding:0 1.8rem;position:sticky;top:0;z-index:40;flex-shrink:0}
  .hamburger{display:none;flex-direction:column;gap:4px;cursor:pointer;background:none;border:none;padding:5px;border-radius:6px;flex-shrink:0}
  .hamburger span{display:block;width:19px;height:2px;background:var(--g600);border-radius:1px;transition:all var(--d) var(--e)}
  .hamburger.open span:nth-child(1){transform:translateY(6px) rotate(45deg)}
  .hamburger.open span:nth-child(2){opacity:0}
  .hamburger.open span:nth-child(3){transform:translateY(-6px) rotate(-45deg)}
  .tb-title{font-family:var(--fd);font-size:.98rem;font-weight:600;color:var(--n800);flex:1}

  /* Layout */
  .content{flex:1;padding:1.8rem 2rem;display:grid;grid-template-columns:1fr 360px;gap:1.5rem;align-items:start}
  .page-header{margin-bottom:1.4rem}
  .page-header h1{font-family:var(--fd);font-size:1.5rem;font-weight:900;color:var(--n800);letter-spacing:-.03em}
  .page-header p{font-size:.82rem;color:#aaa;margin-top:.2rem}

  /* Coluna esquerda: lista */
  .col-left{display:flex;flex-direction:column;gap:1rem}

  /* Card de feedback enviado */
  .fb-card{background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r);box-shadow:var(--sh0);overflow:hidden;transition:box-shadow var(--d) var(--e)}
  .fb-card:hover{box-shadow:var(--sh1)}
  .fb-card-head{padding:.85rem 1.1rem;border-bottom:1px solid rgba(0,0,0,.05);display:flex;align-items:center;gap:.6rem;background:var(--n50)}
  .fb-type-badge{font-size:.65rem;font-weight:600;padding:.18rem .55rem;border-radius:20px;white-space:nowrap}
  .fb-card-title{font-size:.88rem;font-weight:600;color:var(--n800);flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .fb-status{font-size:.65rem;font-weight:600;padding:.18rem .55rem;border-radius:20px;white-space:nowrap;flex-shrink:0}
  .fb-card-body{padding:.9rem 1.1rem}
  .fb-message{font-size:.82rem;color:#555;line-height:1.65;margin-bottom:.6rem}
  .fb-date{font-size:.68rem;color:#bbb}
  .fb-reply{margin-top:.75rem;padding:.75rem .9rem;background:var(--g50);border:1px solid rgba(64,145,108,.15);border-radius:var(--rs);border-left:3px solid var(--g400)}
  .fb-reply-label{font-size:.65rem;font-weight:600;color:var(--g500);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.3rem}
  .fb-reply-text{font-size:.81rem;color:var(--n800);line-height:1.6}
  .fb-actions{margin-top:.65rem;display:flex;justify-content:flex-end}
  .btn-del-fb{font-size:.71rem;color:var(--red);background:none;border:none;cursor:pointer;padding:.22rem .5rem;border-radius:var(--rs);transition:background var(--d) var(--e)}
  .btn-del-fb:hover{background:var(--red-l)}

  /* Estado vazio */
  .empty-fb{text-align:center;padding:3rem 1rem;color:#bbb;background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r)}
  .empty-fb-ico{font-size:2.5rem;display:block;margin-bottom:.6rem;opacity:.3}
  .empty-fb p{font-size:.82rem;line-height:1.7}

  /* Coluna direita: formulário */
  .col-right{position:sticky;top:calc(var(--hh) + 1.2rem);display:flex;flex-direction:column;gap:1rem}
  .form-card{background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r);box-shadow:var(--sh0);overflow:hidden}
  .form-card-head{padding:.9rem 1.1rem;border-bottom:1px solid rgba(0,0,0,.05);background:var(--n50)}
  .form-card-title{font-family:var(--fd);font-size:.95rem;font-weight:700;color:var(--n800)}
  .form-card-sub{font-size:.73rem;color:#aaa;margin-top:.15rem}
  .form-card-body{padding:1.1rem}

  /* Tipos de feedback */
  .type-grid{display:grid;grid-template-columns:1fr 1fr;gap:.45rem;margin-bottom:.9rem}
  .type-btn{padding:.5rem .4rem;border:1.5px solid rgba(0,0,0,.08);border-radius:var(--rs);background:var(--n50);font-family:var(--fb);font-size:.73rem;font-weight:500;color:#888;cursor:pointer;transition:all var(--d) var(--e);text-align:center;display:flex;flex-direction:column;align-items:center;gap:.18rem}
  .type-btn:hover{border-color:var(--g400);background:var(--g50);color:var(--g600)}
  .type-btn.sel{border-color:var(--g500);background:var(--g50);color:var(--g600);box-shadow:0 0 0 3px rgba(64,145,108,.12)}
  .type-btn-ico{font-size:1.1rem}

  .fg{margin-bottom:.8rem}
  .lbl{display:block;font-size:.74rem;font-weight:500;color:#999;margin-bottom:.28rem}
  .inp{width:100%;padding:.62rem .85rem;background:var(--n50);border:1px solid rgba(0,0,0,.08);border-radius:var(--rs);color:var(--n800);font-family:var(--fb);font-size:.85rem;outline:none;transition:all var(--d) var(--e)}
  .inp:focus{border-color:var(--g400);background:var(--g50);box-shadow:0 0 0 3px rgba(64,145,108,.1)}
  .inp::placeholder{color:#ccc}
  textarea.inp{resize:vertical;min-height:110px;line-height:1.65}
  .char-count{font-size:.68rem;color:#bbb;text-align:right;margin-top:.18rem}
  .btn-send{width:100%;padding:.7rem;background:linear-gradient(135deg,var(--g500),var(--g600));color:#fff;border:none;border-radius:50px;font-family:var(--fb);font-size:.85rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);box-shadow:0 3px 12px rgba(64,145,108,.25);margin-top:.2rem}
  .btn-send:hover{transform:translateY(-1px);box-shadow:0 5px 18px rgba(64,145,108,.35)}
  .btn-send:disabled{opacity:.55;cursor:not-allowed;transform:none}

  /* Info card */
  .info-card{background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r);padding:1rem 1.1rem;box-shadow:var(--sh0)}
  .info-item{display:flex;align-items:flex-start;gap:.6rem;margin-bottom:.7rem}
  .info-item:last-child{margin-bottom:0}
  .info-ico{font-size:1rem;flex-shrink:0;margin-top:.05rem}
  .info-text{font-size:.78rem;color:#777;line-height:1.55}
  .info-text strong{color:var(--n800);display:block;font-size:.8rem;margin-bottom:.08rem}

  /* Status colors */
  .s-aberto{background:#fef3c7;color:#d97706}
  .s-em_analise{background:#dbeafe;color:#2563eb}
  .s-resolvido{background:#dcfce7;color:#16a34a}
  .s-fechado{background:var(--n100);color:#888}

  /* Type colors */
  .t-sugestao{background:#ede9fe;color:#7c3aed}
  .t-bug{background:var(--red-l);color:var(--red)}
  .t-elogio{background:#fef3c7;color:#d97706}
  .t-duvida{background:#dbeafe;color:#2563eb}

  /* Modal */
  .modal-overlay{position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.4);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;padding:1.2rem;opacity:0;pointer-events:none;transition:opacity var(--d) var(--e)}
  .modal-overlay.open{opacity:1;pointer-events:all}
  .modal{background:var(--white);border:1px solid rgba(0,0,0,.08);border-radius:var(--r);width:100%;max-width:380px;box-shadow:var(--sh3);transform:translateY(12px) scale(.97);transition:transform var(--d) var(--e)}
  .modal-overlay.open .modal{transform:translateY(0) scale(1)}
  .modal-head{padding:.9rem 1.1rem;border-bottom:1px solid rgba(0,0,0,.06);display:flex;align-items:center;justify-content:space-between}
  .modal-title{font-family:var(--fd);font-size:.88rem;font-weight:700;color:var(--n800)}
  .modal-x{width:24px;height:24px;border-radius:50%;background:var(--n100);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.75rem;color:#aaa;transition:all var(--d) var(--e)}
  .modal-x:hover{background:var(--red-l);color:var(--red)}
  .modal-body{padding:1.1rem;font-size:.82rem;color:#666;line-height:1.65}
  .modal-foot{padding:.8rem 1.1rem;border-top:1px solid rgba(0,0,0,.06);display:flex;gap:.4rem;justify-content:flex-end}
  .btn-ghost{padding:.5rem 1rem;background:transparent;border:1px solid rgba(0,0,0,.1);border-radius:50px;font-family:var(--fb);font-size:.79rem;color:#888;cursor:pointer;transition:all var(--d) var(--e)}
  .btn-ghost:hover{background:var(--n100)}
  .btn-confirm-del{padding:.5rem 1rem;background:var(--red);border:none;border-radius:50px;color:#fff;font-family:var(--fb);font-size:.79rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e)}
  .btn-confirm-del:hover{background:#b91c1c}

  /* Toast */
  .toast-wrap{position:fixed;bottom:1.4rem;right:1.4rem;z-index:500;display:flex;flex-direction:column;gap:.4rem;pointer-events:none}
  .toast{background:var(--n800);color:#eee;padding:.6rem .95rem;border-radius:var(--rs);font-size:.79rem;display:flex;align-items:center;gap:.4rem;animation:tin .22s var(--e) both;max-width:270px;box-shadow:var(--sh3)}
  .toast.ok{border-left:3px solid var(--g400)}.toast.err{border-left:3px solid #f87171}
  @keyframes tin{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}

  @media(max-width:900px){.content{grid-template-columns:1fr}.col-right{position:static}}
  @media(max-width:768px){.main{margin-left:0}.hamburger{display:flex}.topbar{padding:0 1.1rem}.content{padding:1.2rem 1rem}}
  </style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
  <header class="topbar">
    <button class="hamburger" id="hamburger" onclick="toggleSidebar()">
      <span></span><span></span><span></span>
    </button>
    <span class="tb-title">💬 Feedbacks</span>
  </header>

  <main class="content">

    <!-- Coluna esquerda: histórico -->
    <div class="col-left">
      <div class="page-header">
        <h1>Meus Feedbacks</h1>
        <p>Acompanhe suas sugestões, dúvidas e elogios enviados</p>
      </div>
      <div id="fbList"><div class="empty-fb"><span class="empty-fb-ico">💬</span><p>Carregando…</p></div></div>
    </div>

    <!-- Coluna direita: formulário -->
    <div class="col-right">
      <div class="form-card">
        <div class="form-card-head">
          <div class="form-card-title">Enviar feedback</div>
          <div class="form-card-sub">Sua opinião nos ajuda a melhorar</div>
        </div>
        <div class="form-card-body">
          <!-- Tipo -->
          <div class="type-grid" id="typeGrid">
            <button class="type-btn sel" data-type="sugestao" onclick="selectType(this)">
              <span class="type-btn-ico">💡</span>Sugestão
            </button>
            <button class="type-btn" data-type="bug" onclick="selectType(this)">
              <span class="type-btn-ico">🐛</span>Bug / Erro
            </button>
            <button class="type-btn" data-type="elogio" onclick="selectType(this)">
              <span class="type-btn-ico">⭐</span>Elogio
            </button>
            <button class="type-btn" data-type="duvida" onclick="selectType(this)">
              <span class="type-btn-ico">❓</span>Dúvida
            </button>
          </div>

          <div class="fg">
            <label class="lbl">Título *</label>
            <input class="inp" type="text" id="fbTitle" placeholder="Resumo em uma linha…" maxlength="150"/>
          </div>
          <div class="fg">
            <label class="lbl">Mensagem *</label>
            <textarea class="inp" id="fbMessage" placeholder="Descreva com detalhes…" maxlength="2000"
                      oninput="document.getElementById('msgCount').textContent=this.value.length+'/2000'"></textarea>
            <div class="char-count" id="msgCount">0/2000</div>
          </div>
          <button class="btn-send" id="btnSend" onclick="sendFeedback()">
            Enviar feedback
          </button>
        </div>
      </div>

      <div class="info-card">
        <div class="info-item">
          <span class="info-ico">⏱</span>
          <div class="info-text"><strong>Tempo de resposta</strong>Respondemos em até 3 dias úteis.</div>
        </div>
        <div class="info-item">
          <span class="info-ico">🔒</span>
          <div class="info-text"><strong>Privacidade</strong>Apenas a equipe florescer visualiza seus feedbacks.</div>
        </div>
        <div class="info-item">
          <span class="info-ico">🌱</span>
          <div class="info-text"><strong>Sua voz importa</strong>Cada sugestão é lida com atenção pela equipe.</div>
        </div>
      </div>
    </div>

  </main>
</div>

<!-- Modal confirmar exclusão -->
<div class="modal-overlay" id="modalDel" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title">Excluir feedback?</span>
      <button class="modal-x" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body">Esta ação é irreversível. O feedback será removido permanentemente.</div>
    <div class="modal-foot">
      <button class="btn-ghost" onclick="closeModal()">Cancelar</button>
      <button class="btn-confirm-del" id="btnConfirmDel" onclick="confirmDel()">Excluir</button>
    </div>
  </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
const API = '/florescer/api/feedbacks.php';

const TYPE_CONFIG = {
  sugestao: {label:'Sugestão',   cls:'t-sugestao'},
  bug:      {label:'Bug / Erro', cls:'t-bug'},
  elogio:   {label:'Elogio',     cls:'t-elogio'},
  duvida:   {label:'Dúvida',     cls:'t-duvida'},
};
const STATUS_CONFIG = {
  aberto:      {label:'Aberto',      cls:'s-aberto'},
  em_analise:  {label:'Em análise',  cls:'s-em_analise'},
  resolvido:   {label:'Resolvido',   cls:'s-resolvido'},
  fechado:     {label:'Fechado',     cls:'s-fechado'},
};

function toast(msg,type='ok'){
  const w=document.getElementById('toastWrap'),d=document.createElement('div');
  d.className=`toast ${type}`;
  d.innerHTML=`<span>${type==='ok'?'✅':'❌'}</span><span>${msg}</span>`;
  w.appendChild(d);
  setTimeout(()=>{d.style.opacity='0';d.style.transition='.3s';setTimeout(()=>d.remove(),300);},3500);
}
function esc(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function fmtDate(s){return new Date(s).toLocaleDateString('pt-BR',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});}

async function apiFetch(body){
  const r=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  return r.json();
}

/* ── Sidebar ── */
function toggleSidebar(){
  const sb=document.querySelector('.sidebar'),hb=document.getElementById('hamburger'),ov=document.getElementById('sbOverlay');
  if(!sb)return;const open=sb.classList.toggle('open');
  if(hb)hb.classList.toggle('open',open);if(ov)ov.classList.toggle('show',open);
  document.body.style.overflow=open?'hidden':'';
}

/* ── Tipo selecionado ── */
let selectedType = 'sugestao';
function selectType(btn){
  document.querySelectorAll('.type-btn').forEach(b=>b.classList.remove('sel'));
  btn.classList.add('sel');
  selectedType = btn.dataset.type;
}

/* ── Lista feedbacks ── */
async function loadFeedbacks(){
  const list = document.getElementById('fbList');
  const r = await apiFetch({action:'list'});
  if(!r.success){list.innerHTML='<div class="empty-fb"><span class="empty-fb-ico">⚠️</span><p>Erro ao carregar.</p></div>';return;}
  if(!r.data?.length){
    list.innerHTML='<div class="empty-fb"><span class="empty-fb-ico">💬</span><p>Você ainda não enviou nenhum feedback.<br>Use o formulário ao lado para começar!</p></div>';
    return;
  }
  list.innerHTML = r.data.map(fb=>{
    const tc = TYPE_CONFIG[fb.type]   || {label:fb.type,  cls:'t-sugestao'};
    const sc = STATUS_CONFIG[fb.status]|| {label:fb.status,cls:'s-aberto'};
    return `<div class="fb-card" id="fb-${fb.id}">
      <div class="fb-card-head">
        <span class="fb-type-badge ${tc.cls}">${tc.label}</span>
        <span class="fb-card-title">${esc(fb.title)}</span>
        <span class="fb-status ${sc.cls}">${sc.label}</span>
      </div>
      <div class="fb-card-body">
        <div class="fb-message">${esc(fb.message)}</div>
        <div class="fb-date">Enviado em ${fmtDate(fb.created_at)}</div>
        ${fb.admin_reply ? `
        <div class="fb-reply">
          <div class="fb-reply-label">💬 Resposta da equipe</div>
          <div class="fb-reply-text">${esc(fb.admin_reply)}</div>
        </div>` : ''}
        ${fb.status==='aberto'?`
        <div class="fb-actions">
          <button class="btn-del-fb" onclick="openDel(${fb.id})">🗑 Excluir</button>
        </div>`:''}
      </div>
    </div>`;
  }).join('');
}

/* ── Enviar feedback ── */
async function sendFeedback(){
  const title   = document.getElementById('fbTitle').value.trim();
  const message = document.getElementById('fbMessage').value.trim();
  if(!title)  {toast('Informe um título.','err');document.getElementById('fbTitle').focus();return;}
  if(!message){toast('Escreva sua mensagem.','err');document.getElementById('fbMessage').focus();return;}
  const btn = document.getElementById('btnSend');
  btn.disabled=true;btn.textContent='Enviando…';
  const r = await apiFetch({action:'create',type:selectedType,title,message});
  btn.disabled=false;btn.textContent='Enviar feedback';
  if(r.success){
    toast('Feedback enviado! Obrigado 💚');
    document.getElementById('fbTitle').value='';
    document.getElementById('fbMessage').value='';
    document.getElementById('msgCount').textContent='0/2000';
    document.querySelectorAll('.type-btn').forEach(b=>b.classList.remove('sel'));
    document.querySelector('.type-btn[data-type="sugestao"]').classList.add('sel');
    selectedType='sugestao';
    loadFeedbacks();
  } else toast(r.message||'Erro ao enviar.','err');
}

/* ── Excluir ── */
let delId = null;
function openDel(id){
  delId=id;
  document.getElementById('modalDel').classList.add('open');
  document.body.style.overflow='hidden';
}
function closeModal(){
  document.getElementById('modalDel').classList.remove('open');
  document.body.style.overflow='';
}
async function confirmDel(){
  const btn=document.getElementById('btnConfirmDel');
  btn.disabled=true;btn.textContent='Excluindo…';
  const r=await apiFetch({action:'delete',id:delId});
  btn.disabled=false;btn.textContent='Excluir';
  if(r.success){
    toast('Feedback excluído.');
    closeModal();
    const el=document.getElementById('fb-'+delId);
    if(el){el.style.opacity='0';el.style.transition='.3s';setTimeout(()=>loadFeedbacks(),350);}
  }else toast(r.message||'Erro.','err');
}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});

/* Init */
document.addEventListener('DOMContentLoaded',loadFeedbacks);
</script>
</body>
</html>