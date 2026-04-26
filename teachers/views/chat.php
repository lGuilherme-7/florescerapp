<?php
// ============================================================
// /professor/teachers/views/chat.php
// ============================================================

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireTeacher();
$teacher     = currentTeacher();
$currentPage = 'chat';

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
<title>Mensagens — Professor</title>
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
html,body{height:100%}
body{font-family:var(--fb);background:var(--g25);color:var(--n800);display:flex;-webkit-font-smoothing:antialiased;overflow:hidden}
::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:var(--g200);border-radius:2px}

.main{margin-left:var(--sw);flex:1;min-width:0;display:flex;flex-direction:column;height:100vh;overflow:hidden}
.topbar{height:var(--hh);position:sticky;top:0;z-index:40;background:rgba(244,251,247,.94);backdrop-filter:blur(16px);border-bottom:1px solid var(--n100);display:flex;align-items:center;justify-content:space-between;padding:0 1.8rem;flex-shrink:0}
.tb-title{font-family:var(--fd);font-size:1.05rem;font-weight:600;color:var(--n800)}

/* Layout chat */
.chat-layout{flex:1;display:grid;grid-template-columns:300px 1fr;overflow:hidden}

/* Lista de conversas */
.conv-list{border-right:1px solid var(--n100);display:flex;flex-direction:column;overflow:hidden;background:var(--white)}
.conv-search{padding:.75rem;border-bottom:1px solid var(--n100);flex-shrink:0}
.conv-search input{width:100%;padding:.5rem .75rem;background:var(--g25);border:1.5px solid var(--n100);border-radius:50px;color:var(--n800);font-family:var(--fb);font-size:.8rem;outline:none;transition:all var(--d) var(--e)}
.conv-search input:focus{border-color:var(--g400);background:var(--white)}
.conv-search input::placeholder{color:#bbb}
.conv-items{flex:1;overflow-y:auto}
.conv-item{display:flex;align-items:center;gap:.7rem;padding:.75rem 1rem;cursor:pointer;border-bottom:1px solid var(--n50);transition:background var(--d) var(--e);position:relative}
.conv-item:hover{background:var(--g25)}
.conv-item.active{background:var(--g50);border-left:3px solid var(--g400)}
.conv-av{width:38px;height:38px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,var(--g100),var(--g200));display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-size:.95rem;font-weight:700;color:var(--g700)}
.conv-info{flex:1;min-width:0}
.conv-name{font-size:.82rem;font-weight:600;color:var(--n800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.conv-last{font-size:.7rem;color:var(--n400);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:.08rem}
.conv-meta{display:flex;flex-direction:column;align-items:flex-end;gap:.25rem;flex-shrink:0}
.conv-time{font-size:.62rem;color:var(--n400)}
.conv-badge{background:var(--g500);color:#fff;font-size:.58rem;font-weight:700;padding:.1rem .38rem;border-radius:20px;min-width:16px;text-align:center}
.conv-type{font-size:.6rem;color:var(--n400);padding:.08rem .35rem;background:var(--n50);border-radius:20px;border:1px solid var(--n100)}

/* Área de mensagens */
.chat-area{display:flex;flex-direction:column;overflow:hidden;background:var(--g25)}
.chat-header{padding:.85rem 1.3rem;border-bottom:1px solid var(--n100);background:var(--white);display:flex;align-items:center;gap:.8rem;flex-shrink:0}
.chat-header-av{width:36px;height:36px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,var(--g100),var(--g200));display:flex;align-items:center;justify-content:center;font-family:var(--fd);font-size:.9rem;font-weight:700;color:var(--g700)}
.chat-header-info{flex:1}
.chat-header-name{font-size:.88rem;font-weight:600;color:var(--n800)}
.chat-header-sub{font-size:.68rem;color:var(--n400);margin-top:.04rem}
.chat-anti{font-size:.65rem;color:var(--n400);background:var(--n50);border:1px solid var(--n100);padding:.22rem .6rem;border-radius:20px;flex-shrink:0}

.msgs-wrap{flex:1;overflow-y:auto;padding:1.2rem;display:flex;flex-direction:column;gap:.6rem}
.msg{display:flex;flex-direction:column;max-width:72%}
.msg.teacher{align-self:flex-end;align-items:flex-end}
.msg.student{align-self:flex-start;align-items:flex-start}
.msg-bubble{padding:.65rem .9rem;border-radius:14px;font-size:.84rem;line-height:1.6;word-break:break-word}
.msg.teacher .msg-bubble{background:var(--g600);color:#fff;border-radius:14px 14px 4px 14px}
.msg.student .msg-bubble{background:var(--white);color:var(--n800);border:1px solid var(--n100);border-radius:14px 14px 14px 4px;box-shadow:var(--sh0)}
.msg-time{font-size:.6rem;color:var(--n400);margin-top:.18rem;padding:0 .2rem}

.chat-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--n400);text-align:center;gap:.5rem}
.chat-empty-ico{font-size:2.5rem;opacity:.3}
.chat-empty p{font-size:.82rem;line-height:1.7}

/* Input */
.chat-input-wrap{padding:.85rem 1.1rem;border-top:1px solid var(--n100);background:var(--white);display:flex;align-items:flex-end;gap:.6rem;flex-shrink:0}
.chat-input{flex:1;padding:.65rem .9rem;background:var(--g25);border:1.5px solid var(--n100);border-radius:22px;color:var(--n800);font-family:var(--fb);font-size:.85rem;outline:none;resize:none;max-height:120px;overflow-y:auto;line-height:1.5;transition:all var(--d) var(--e)}
.chat-input:focus{border-color:var(--g400);background:var(--white);box-shadow:0 0 0 3px rgba(45,122,88,.08)}
.chat-input::placeholder{color:#bbb}
.btn-send{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--g400),var(--g600));border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;transition:all var(--d) var(--e);box-shadow:0 3px 10px rgba(45,122,88,.25)}
.btn-send:hover{transform:scale(1.08);box-shadow:0 5px 16px rgba(45,122,88,.35)}
.btn-send:disabled{opacity:.4;cursor:not-allowed;transform:none}

/* Aviso sem conversa selecionada */
.no-chat{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--n400);text-align:center;gap:.6rem}
.no-chat-ico{font-size:3rem;opacity:.25}
.no-chat p{font-size:.84rem}

/* Toast */
#toasts{position:fixed;bottom:1.2rem;right:1.2rem;z-index:999;display:flex;flex-direction:column;gap:.35rem;pointer-events:none}
.toast{background:var(--n800);color:#eee;padding:.55rem .9rem;border-radius:var(--rs);font-size:.78rem;display:flex;align-items:center;gap:.4rem;animation:tin .22s var(--e) both;max-width:270px;box-shadow:var(--sh3);pointer-events:all}
.toast.ok{border-left:3px solid var(--g400)}.toast.err{border-left:3px solid #f87171}.toast.warn{border-left:3px solid var(--gold)}
@keyframes tin{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}

@media(max-width:900px){.chat-layout{grid-template-columns:240px 1fr}}
@media(max-width:768px){.main{margin-left:0}.chat-layout{grid-template-columns:1fr}.chat-area{display:none}.chat-area.open{display:flex}}
</style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
  <header class="topbar">
    <span class="tb-title">💬 Mensagens</span>
    <div style="font-size:.72rem;color:var(--n400)"><?= date('d/m/Y · H:i') ?></div>
  </header>

  <div class="chat-layout">

    <!-- Lista de conversas -->
    <div class="conv-list">
      <div class="conv-search">
        <input type="text" id="searchInput" placeholder="Buscar aluno…" oninput="filterConvs(this.value)"/>
      </div>
      <div class="conv-items" id="convItems">
        <div style="text-align:center;padding:2rem;color:#bbb;font-size:.8rem">Carregando…</div>
      </div>
    </div>

    <!-- Área de chat -->
    <div class="chat-area" id="chatArea">
      <div class="no-chat" id="noChat">
        <span class="no-chat-ico">💬</span>
        <p>Selecione uma conversa<br>para começar</p>
      </div>

      <!-- Header da conversa ativa -->
      <div id="chatHeader" style="display:none">
        <div class="chat-header">
          <div class="chat-header-av" id="chatAv"></div>
          <div class="chat-header-info">
            <div class="chat-header-name" id="chatName">—</div>
            <div class="chat-header-sub" id="chatSub">—</div>
          </div>
          <span class="chat-anti">🔒 Chat monitorado</span>
        </div>
      </div>

      <!-- Mensagens -->
      <div class="msgs-wrap" id="msgsWrap">
        <div class="chat-empty" id="chatEmpty" style="display:none">
          <span class="chat-empty-ico">💬</span>
          <p>Nenhuma mensagem ainda.<br>Inicie a conversa!</p>
        </div>
      </div>

      <!-- Input -->
      <div class="chat-input-wrap" id="chatInputWrap" style="display:none">
        <textarea class="chat-input" id="chatInput"
                  placeholder="Digite sua mensagem…" rows="1"
                  oninput="autoResize(this)"
                  onkeydown="handleKey(event)"></textarea>
        <button class="btn-send" id="btnSend" onclick="sendMsg()">➤</button>
      </div>
    </div>

  </div>
</div>

<div id="toasts"></div>

<script>
const API_CHAT = '<?= TEACHER_API ?>/chat.php';
let convs     = [];
let activeOrder = null;
let pollInterval = null;

function toast(msg,type='ok',ms=3200){
  const w=document.getElementById('toasts'),d=document.createElement('div');
  d.className=`toast ${type}`;
  d.innerHTML=`<span>${type==='ok'?'✅':type==='err'?'❌':'⚠️'}</span><span>${msg}</span>`;
  w.appendChild(d);
  setTimeout(()=>{d.style.opacity='0';d.style.transition='.3s';setTimeout(()=>d.remove(),300);},ms);
}

async function api(body){
  const r=await fetch(API_CHAT,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  return r.json();
}

function esc(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}

function timeAgo(dt){
  if(!dt) return '';
  const diff=Math.floor((Date.now()-new Date(dt).getTime())/1000);
  if(diff<60) return 'agora';
  if(diff<3600) return Math.floor(diff/60)+'min';
  if(diff<86400) return Math.floor(diff/3600)+'h';
  return new Date(dt).toLocaleDateString('pt-BR',{day:'2-digit',month:'short'});
}

// ── Conversas ─────────────────────────────────────────────────
async function loadConvs(){
  const d=await api({action:'list_conversations'});
  convs=d.data||[];
  renderConvs(convs);
}

function renderConvs(list){
  const el=document.getElementById('convItems');
  if(!list.length){
    el.innerHTML='<div style="text-align:center;padding:2rem;color:#bbb;font-size:.8rem">Nenhuma conversa ainda.</div>';
    return;
  }
  el.innerHTML=list.map(c=>{
    const ini=(c.student_name||'?')[0].toUpperCase();
    const isActive=activeOrder===c.order_id;
    return `<div class="conv-item ${isActive?'active':''}"
                 id="conv-${c.order_id}"
                 onclick="openConv(${c.order_id},'${esc(c.student_name||'?')}','${c.student_emoji||ini}','${c.type||''}')">
      <div class="conv-av">${c.student_emoji||ini}</div>
      <div class="conv-info">
        <div class="conv-name">${esc(c.student_name||'—')}</div>
        <div class="conv-last">${esc(c.last_msg||'Sem mensagens')}</div>
      </div>
      <div class="conv-meta">
        <span class="conv-time">${timeAgo(c.last_at)}</span>
        ${c.unread>0?`<span class="conv-badge">${c.unread}</span>`:''}
        <span class="conv-type">${c.type==='aula'?'📅 Aula':'📝 Redação'}</span>
      </div>
    </div>`;
  }).join('');
}

function filterConvs(q){
  const filtered=q
    ? convs.filter(c=>(c.student_name||'').toLowerCase().includes(q.toLowerCase()))
    : convs;
  renderConvs(filtered);
}

// ── Conversa ativa ────────────────────────────────────────────
async function openConv(orderId, name, emoji, type){
  activeOrder=orderId;

  // Atualiza visual da lista
  document.querySelectorAll('.conv-item').forEach(el=>el.classList.remove('active'));
  const convEl=document.getElementById('conv-'+orderId);
  if(convEl) convEl.classList.add('active');

  // Header
  document.getElementById('noChat').style.display='none';
  document.getElementById('chatHeader').style.display='block';
  document.getElementById('chatInputWrap').style.display='flex';
  document.getElementById('chatAv').textContent=emoji;
  document.getElementById('chatName').textContent=name;
  document.getElementById('chatSub').textContent=type==='aula'?'📅 Aula agendada':'📝 Correção de redação';

  // Carrega mensagens
  await loadMsgs(orderId);

  // Poll a cada 5s
  if(pollInterval) clearInterval(pollInterval);
  pollInterval=setInterval(()=>{
    if(activeOrder===orderId) loadMsgs(orderId, true);
  },5000);

  // Foca input
  setTimeout(()=>document.getElementById('chatInput')?.focus(),100);
}

async function loadMsgs(orderId, silent=false){
  const d=await api({action:'messages',order_id:orderId});
  if(!d.success) return;

  const msgs=d.data||[];
  const wrap=document.getElementById('msgsWrap');
  const empty=document.getElementById('chatEmpty');

  if(!msgs.length){
    empty.style.display='flex';
    wrap.innerHTML='';
    wrap.appendChild(empty);
    return;
  }

  empty.style.display='none';
  const wasBottom=wrap.scrollHeight-wrap.scrollTop<=wrap.clientHeight+40;

  wrap.innerHTML=msgs.map(m=>`
    <div class="msg ${m.sender}">
      <div class="msg-bubble">${esc(m.message).replace(/\n/g,'<br>')}</div>
      <div class="msg-time">${timeAgo(m.created_at)}</div>
    </div>`).join('');

  if(!silent || wasBottom){
    wrap.scrollTop=wrap.scrollHeight;
  }

  // Limpa badge da conversa
  const convEl=document.getElementById('conv-'+orderId);
  if(convEl){
    const badge=convEl.querySelector('.conv-badge');
    if(badge) badge.remove();
  }
}

async function sendMsg(){
  const inp=document.getElementById('chatInput');
  const msg=inp.value.trim();
  if(!msg||!activeOrder) return;

  const btn=document.getElementById('btnSend');
  btn.disabled=true;

  const d=await api({action:'send',order_id:activeOrder,message:msg});

  if(d.success){
    inp.value='';
    inp.style.height='auto';
    await loadMsgs(activeOrder);
  } else {
    // Anti-contato detectado
    if(d.message?.includes('bloqueada')||d.message?.includes('suspenso')){
      toast(d.message,'warn',5000);
    } else {
      toast(d.message||'Erro ao enviar.','err');
    }
  }
  btn.disabled=false;
  inp.focus();
}

function handleKey(e){
  if(e.key==='Enter'&&!e.shiftKey){
    e.preventDefault();
    sendMsg();
  }
}

function autoResize(el){
  el.style.height='auto';
  el.style.height=Math.min(el.scrollHeight,120)+'px';
}

// Init
loadConvs();
</script>
</body>
</html>