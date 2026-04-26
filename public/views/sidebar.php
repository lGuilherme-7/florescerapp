<?php
// ============================================================
// /public/views/sidebar.php — florescer v2.0
// ============================================================

define('BASE_VIEWS', '/florescer/public/views/');
define('BASE_API',   '/florescer/api/');

$currentPage ??= '';
$userName    ??= 'Estudante';
$userInitial ??= 'E';
$plant       ??= ['emoji' => '🌱', 'name' => 'Semente', 'pct' => 0];
$streak      ??= 0;
$level       ??= 1;
$lvName      ??= 'Semente';
$xp          ??= 0;
$allObjs     ??= [];
$activeObjId ??= null;
$avatarEmoji     ??= '';
$avatarPublicUrl ??= '';
$avatarType      ??= '';

$nav = [
    'Painel' => [
        'dashboard'      => ['🏠', 'Painel'],
    ],
    'Estudos' => [
        'objectives'     => ['🎯', 'Objetivos'],
        'materials'      => ['📚', 'Matérias'],
        'works'          => ['📋', 'Trabalhos'],
        'works_calendar' => ['🗓️', 'Calendário'],
        'simulated'      => ['🧠', 'Simulados'],
    ],
    'Desempenho' => [
        'performance'    => ['📊', 'Desempenho'],
        'history'        => ['🕓', 'Histórico'],
        'progress'       => ['🌿', 'Progresso'],
    ],
    'Comunidade' => [
        'chat'           => ['💬', 'Comunidade'],
        'store'          => ['🎓', 'Cursos'],
        'support'        => ['❤️', 'Apoiar'],
        'feedbacks'      => ['💌', 'Feedbacks'],
    ],
    'Conta' => [
        'profile'        => ['👤', 'Perfil'],
    ],
];
?>
<style>
/* ══════════════════════════════════════════════════════════════
   SIDEBAR — florescer v2.0 — MOBILE FIRST CORRIGIDO
══════════════════════════════════════════════════════════════ */

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{height:100%}
body{
  font-family:'Inter',system-ui,sans-serif;
  background:#faf8f5;color:#1c1c1a;
  display:flex;overflow-x:hidden;
  -webkit-font-smoothing:antialiased;
  min-height:100%;
}
:root{
  --g950:#0d1f16;--g900:#132a1e;--g800:#1a3a2a;--g700:#1e4d35;
  --g600:#2d6a4f;--g500:#40916c;--g400:#52b788;--g300:#74c69d;
  --g200:#b7e4c7;--g100:#d8f3dc;--g50:#f0faf4;
  --n800:#1c1c1a;--n200:#e8e4de;--n100:#f5f2ee;--n50:#faf8f5;--white:#fff;
  --gold:#c9a84c;--red:#dc2626;--red-l:#fee2e2;
  --sw:242px;--hh:58px;
  --fd:'Fraunces',Georgia,serif;
  --fb:'Inter',system-ui,sans-serif;
  --r:12px;--rs:7px;
  --d:.22s;--e:cubic-bezier(.4,0,.2,1);
  --sh0:0 1px 3px rgba(0,0,0,.06);
  --sh1:0 2px 8px rgba(0,0,0,.07);
  --sh2:0 4px 16px rgba(0,0,0,.09);
  --sh3:0 12px 32px rgba(0,0,0,.14);
}

::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-thumb{background:rgba(64,145,108,.2);border-radius:2px}

/* ══════════════════════════════════════════════════════════
   HAMBURGER — MOBILE FIRST
══════════════════════════════════════════════════════════ */
.hamburger{
  display:flex; /* visível por padrão (mobile first) */
  flex-direction:column;
  justify-content:center;
  align-items:center;
  gap:5px;
  cursor:pointer;
  background:rgba(255,255,255,.92);
  border:1px solid rgba(64,145,108,.18);
  border-radius:10px;
  width:40px;height:40px;
  padding:0;
  box-shadow:0 2px 8px rgba(0,0,0,.08);
  transition:background var(--d) var(--e), box-shadow var(--d) var(--e);
  /* FIXO no viewport — não dentro da sidebar */
  position:fixed;
  top:10px;
  left:10px;
  z-index:200; /* acima de tudo */
  -webkit-tap-highlight-color:transparent;
}
.hamburger:hover{background:rgba(64,145,108,.08);box-shadow:0 3px 12px rgba(0,0,0,.12)}
.hamburger span{
  display:block;width:18px;height:2px;
  background:var(--g500);border-radius:1px;
  transition:all var(--d) var(--e);
  transform-origin:center;
}
.hamburger.open span:nth-child(1){transform:translateY(7px) rotate(45deg)}
.hamburger.open span:nth-child(2){opacity:0;transform:scaleX(0)}
.hamburger.open span:nth-child(3){transform:translateY(-7px) rotate(-45deg)}

/* ══════════════════════════════════════════════════════════
   SIDEBAR — MOBILE FIRST (fora do viewport por padrão)
══════════════════════════════════════════════════════════ */
.sidebar{
  width:var(--sw);
  height:100vh;
  position:fixed;top:0;left:0;
  background:var(--g800);
  display:flex;flex-direction:column;
  z-index:150; /* abaixo do hamburger (200) mas acima do overlay (100) */
  overflow:hidden;
  /* MOBILE: escondida fora da tela por padrão */
  transform:translateX(-100%);
  transition:transform .28s var(--e);
  border-right:1px solid rgba(116,198,157,.08);
  flex-shrink:0;
  box-shadow:none;
}
.sidebar.open{
  transform:translateX(0);
  box-shadow:4px 0 32px rgba(0,0,0,.22);
}
.sidebar::after{
  content:'';position:absolute;top:-70px;right:-70px;
  width:220px;height:220px;border-radius:50%;
  background:radial-gradient(circle,rgba(116,198,157,.05) 0%,transparent 70%);
  pointer-events:none;
}

/* ── Logo ── */
.sb-logo{
  padding:.9rem 1.1rem;
  border-bottom:1px solid rgba(116,198,157,.08);
  display:flex;align-items:center;gap:.55rem;
  flex-shrink:0;
}
.sb-logo-icon{
  width:30px;height:30px;
  background:linear-gradient(135deg,var(--g500),var(--g700));
  border-radius:8px;
  display:flex;align-items:center;justify-content:center;
  font-size:.9rem;flex-shrink:0;
  overflow:hidden;padding:0;background:transparent;
  box-shadow:0 2px 8px rgba(64,145,108,.25);
}
.sb-logo-icon img{width:100%;height:100%;object-fit:cover;display:block;border-radius:8px}
.sb-logo-name{
  font-family:var(--fd);font-size:1.05rem;font-weight:700;
  color:var(--g200);letter-spacing:-.02em;line-height:1;
}
.sb-logo-sub{
  font-size:.55rem;color:rgba(116,198,157,.28);
  text-transform:uppercase;letter-spacing:.11em;margin-top:.1rem;
}

/* ── Perfil ── */
.sb-profile{
  padding:.68rem 1.1rem;
  border-bottom:1px solid rgba(116,198,157,.08);
  display:flex;align-items:center;gap:.6rem;
  text-decoration:none;flex-shrink:0;
  transition:background var(--d) var(--e);
}
.sb-profile:hover{background:rgba(116,198,157,.05)}
.sb-avatar{
  width:34px;height:34px;border-radius:50%;
  background:linear-gradient(135deg,var(--g500),var(--g600));
  display:flex;align-items:center;justify-content:center;
  font-family:var(--fd);font-size:.88rem;font-weight:700;
  color:var(--white);flex-shrink:0;overflow:hidden;
  box-shadow:0 0 0 2px rgba(116,198,157,.2);
}
.sb-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.sb-pname{
  font-size:.81rem;font-weight:500;color:var(--g100);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.sb-plevel{font-size:.67rem;color:var(--g300);margin-top:.06rem;opacity:.65}

/* ── Planta ── */
.sb-plant{
  padding:.58rem 1.1rem;
  border-bottom:1px solid rgba(116,198,157,.08);
  flex-shrink:0;
}
.sb-plant-row{display:flex;align-items:center;gap:.52rem}
.sb-pemoji{
  font-size:1.25rem;
  animation:sbBreathe 4s ease-in-out infinite;
  flex-shrink:0;
}
@keyframes sbBreathe{
  0%,100%{transform:scale(1)}
  50%{transform:scale(1.07) translateY(-1px)}
}
.sb-pname2{font-size:.7rem;font-weight:600;color:var(--g300)}
.sb-pstreak{font-size:.63rem;color:rgba(116,198,157,.38);margin-top:.05rem}
.sb-pbar{
  height:2px;background:rgba(116,198,157,.1);
  border-radius:1px;margin-top:.26rem;overflow:hidden;
}
.sb-pbar-fill{
  height:100%;border-radius:2px;
  background:linear-gradient(90deg,var(--g400),var(--g200));
  transition:width .6s var(--e);
}

/* ── Objetivo ── */
.sb-obj{
  padding:.48rem 1.1rem;
  border-bottom:1px solid rgba(116,198,157,.08);
  flex-shrink:0;
}
.sb-obj-lbl{
  font-size:.57rem;text-transform:uppercase;letter-spacing:.1em;
  color:rgba(116,198,157,.26);display:block;margin-bottom:.2rem;
}
.sb-obj-sel{
  width:100%;background:none;border:none;
  color:var(--g300);font-family:var(--fb);font-size:.77rem;font-weight:500;
  cursor:pointer;padding:0;outline:none;
  appearance:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.sb-obj-sel option{background:var(--g800);color:var(--g300)}

/* ── Nav ── */
.sb-nav{flex:1;overflow-y:auto;padding:.4rem 0;display:flex;flex-direction:column;gap:.22rem}
.sb-nav-grp{
  font-size:.56rem;text-transform:uppercase;letter-spacing:.1em;
  color:rgba(116,198,157,.22);
  padding:.65rem 1.1rem .22rem;display:block;
}
.sb-nav-a{
  display:flex;align-items:center;gap:.52rem;
  padding:.52rem 1.1rem;
  color:rgba(183,228,199,.45);
  font-size:.8rem;font-weight:400;
  text-decoration:none;
  transition:all var(--d) var(--e);
  border-left:2px solid transparent;
  min-height:44px; /* touch target */
  -webkit-tap-highlight-color:transparent;
}
.sb-nav-a:hover{color:var(--g300);background:rgba(116,198,157,.04)}
.sb-nav-a.active{
  color:var(--g300);
  background:rgba(116,198,157,.08);
  border-left-color:var(--g400);
  font-weight:500;
}
.sb-nav-ico{font-size:.84rem;min-width:.95rem;text-align:center}

/* ── Footer ── */
.sb-footer{
  padding:.7rem 1rem;
  border-top:1px solid rgba(116,198,157,.08);
  flex-shrink:0;
}
.sb-logout{
  display:flex;align-items:center;gap:.4rem;
  width:100%;padding:.48rem .7rem;
  background:none;border:1px solid rgba(220,100,100,.12);
  border-radius:var(--rs);
  color:rgba(220,100,100,.5);
  font-family:var(--fb);font-size:.76rem;
  cursor:pointer;transition:all var(--d) var(--e);
  min-height:44px;-webkit-tap-highlight-color:transparent;
}
.sb-logout:hover{background:rgba(220,38,38,.07);color:#e07070}

/* ══════════════════════════════════════════════════════════
   OVERLAY MOBILE
══════════════════════════════════════════════════════════ */
.sb-overlay{
  position:fixed;inset:0;
  background:rgba(0,0,0,.45);
  backdrop-filter:blur(3px);
  -webkit-backdrop-filter:blur(3px);
  z-index:100; /* entre main (0) e sidebar (150) */
  opacity:0;
  pointer-events:none;
  transition:opacity .28s var(--e);
}
.sb-overlay.show{
  opacity:1;
  pointer-events:all;
}

/* ══════════════════════════════════════════════════════════
   MAIN LAYOUT — MOBILE FIRST
══════════════════════════════════════════════════════════ */
.main{
  margin-left:0; /* mobile: sem margem, sidebar é flutuante */
  flex:1;
  min-height:100vh;
  display:flex;
  flex-direction:column;
  min-width:0;
  width:100%;
}

/* ── Topbar ── */
.topbar{
  height:var(--hh);
  background:rgba(250,248,245,.95);
  backdrop-filter:blur(14px);
  -webkit-backdrop-filter:blur(14px);
  border-bottom:1px solid rgba(0,0,0,.055);
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding:0 1rem 0 60px; /* 60px para não sobrepor o hamburger fixo */
  position:sticky;top:0;z-index:40;
  flex-shrink:0;
  transition:background .3s,box-shadow .3s;
}
.topbar.scrolled{
  background:rgba(250,248,245,.98);
  box-shadow:0 1px 8px rgba(0,0,0,.07);
}
.tb-left{display:flex;align-items:center;gap:.7rem}
.tb-title{
  font-family:var(--fd);font-size:.95rem;font-weight:600;
  color:var(--n800);letter-spacing:-.01em;
}
.xp-pill{
  display:flex;align-items:center;gap:.28rem;
  background:var(--white);border:1px solid rgba(0,0,0,.06);
  border-radius:50px;padding:.26rem .75rem;
  font-size:.73rem;font-weight:600;color:var(--g500);
  box-shadow:var(--sh0);white-space:nowrap;
}

/* ── Toast ── */
.toast-wrap{
  position:fixed;bottom:1.2rem;right:1rem;
  z-index:500;display:flex;flex-direction:column;gap:.4rem;
  pointer-events:none;max-width:calc(100vw - 2rem);
}
.toast{
  background:var(--n800);color:#eee;
  padding:.6rem .95rem;border-radius:var(--rs);
  font-size:.78rem;display:flex;align-items:center;gap:.4rem;
  animation:toastIn .24s var(--e) both;
  max-width:280px;box-shadow:var(--sh3);pointer-events:all;
}
.toast.ok  {border-left:3px solid var(--g400)}
.toast.err {border-left:3px solid #f87171}
.toast.info{border-left:3px solid var(--gold)}
@keyframes toastIn{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:none}}

/* ══════════════════════════════════════════════════════════
   DESKTOP ≥ 769px — sidebar fixa, hamburger some
══════════════════════════════════════════════════════════ */
@media(min-width:769px){
  .hamburger{display:none} /* some no desktop */

  .sidebar{
    transform:translateX(0); /* sempre visível */
    box-shadow:none;
    position:fixed; /* continua fixed mas sempre aberta */
  }

  .sb-overlay{display:none !important} /* nunca aparece em desktop */

  .main{
    margin-left:var(--sw); /* empurra o conteúdo para a direita */
    width:calc(100% - var(--sw));
  }

  .topbar{
    padding:0 1.8rem; /* sem offset do hamburger */
  }
}
</style>

<?php
/*
  HAMBURGER RENDERIZADO AQUI — fora da <aside>, fixo no viewport.
  Isso garante que ele SEMPRE exista no DOM quando sidebar.php
  for incluído, sem depender de cada view adicionar o próprio botão.
*/
?>
<button class="hamburger" id="sbHamburger" onclick="toggleSidebar()" aria-label="Abrir menu">
  <span></span>
  <span></span>
  <span></span>
</button>

<aside class="sidebar" id="sidebar">

  <!-- Logo -->
  <div class="sb-logo">
    <div class="sb-logo-icon" style="width:30px;height:30px;border-radius:8px;overflow:hidden;padding:0;background:transparent;box-shadow:0 2px 8px rgba(64,145,108,.25)">
      <img src="/florescer/public/img/logo.png" alt="florescer" style="width:100%;height:100%;object-fit:cover;display:block;border-radius:8px"/>
    </div>
    <div>
      <div class="sb-logo-name">florescer</div>
      <div class="sb-logo-sub">by Florescer</div>
    </div>
  </div>

  <!-- Perfil -->
  <a class="sb-profile" href="<?= BASE_VIEWS ?>profile.php">
    <div class="sb-avatar" id="sidebarAvatar">
      <?php if ($avatarType === 'upload' && !empty($avatarPublicUrl)): ?>
        <img src="<?= htmlspecialchars($avatarPublicUrl, ENT_QUOTES) ?>" alt="Avatar">
      <?php elseif ($avatarType === 'emoji' && !empty($avatarEmoji)): ?>
        <?= htmlspecialchars($avatarEmoji, ENT_QUOTES) ?>
      <?php else: ?>
        <?= htmlspecialchars($userInitial, ENT_QUOTES) ?>
      <?php endif; ?>
    </div>
    <div style="flex:1;min-width:0">
      <div class="sb-pname" id="sidebarName">
        <?= htmlspecialchars($userName, ENT_QUOTES) ?>
      </div>
      <div class="sb-plevel" id="sidebarLevel">
        Nível <?= (int)$level ?> — <?= htmlspecialchars($lvName, ENT_QUOTES) ?>
      </div>
    </div>
  </a>

  <!-- Planta / streak -->
  <div class="sb-plant">
    <div class="sb-plant-row">
      <span class="sb-pemoji" id="sidebarPlantEmoji">
        <?= htmlspecialchars($plant['emoji'], ENT_QUOTES) ?>
      </span>
      <div style="flex:1;min-width:0">
        <div class="sb-pname2" id="sidebarPlantName">
          <?= htmlspecialchars($plant['name'], ENT_QUOTES) ?>
        </div>
        <div class="sb-pstreak" id="sidebarPlantStreak">
          <?= (int)$streak ?> dias consecutivos
        </div>
        <div class="sb-pbar">
          <div class="sb-pbar-fill" id="sidebarPlantBar"
               style="width:<?= (int)($plant['pct'] ?? 0) ?>%"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Objetivo ativo -->
  <div class="sb-obj">
    <span class="sb-obj-lbl">Objetivo ativo</span>
    <?php if (!empty($allObjs)): ?>
      <form method="POST" action="<?= BASE_API ?>set_objective.php" style="margin:0">
        <select class="sb-obj-sel" name="objective_id" onchange="this.form.submit()">
          <option value="">Selecionar objetivo...</option>
          <?php foreach ($allObjs as $obj): ?>
            <option value="<?= (int)$obj['id'] ?>"
              <?= (int)$obj['id'] === (int)$activeObjId ? 'selected' : '' ?>>
              <?= htmlspecialchars($obj['name'], ENT_QUOTES) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php else: ?>
      <select class="sb-obj-sel" disabled>
        <option>Nenhum objetivo</option>
      </select>
    <?php endif; ?>
  </div>

  <!-- Navegação -->
  <nav class="sb-nav">
    <?php foreach ($nav as $grupo => $itens): ?>
      <span class="sb-nav-grp"><?= htmlspecialchars($grupo) ?></span>
      <?php foreach ($itens as $page => [$ico, $lbl]):
        $cls = $currentPage === $page ? ' active' : '';
      ?>
        <a class="sb-nav-a<?= $cls ?>"
           href="<?= BASE_VIEWS . htmlspecialchars($page) ?>.php">
          <span class="sb-nav-ico"><?= $ico ?></span>
          <?= htmlspecialchars($lbl) ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <!-- Footer -->
  <div class="sb-footer">
    <button class="sb-logout" onclick="doLogout()">↩ Sair da conta</button>
  </div>

</aside>

<div class="sb-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<script>
/* ── Sidebar JS ── */

function toggleSidebar(){
  const sb = document.getElementById('sidebar');
  const ov = document.getElementById('sidebarOverlay');
  const hb = document.getElementById('sbHamburger');

  const open = sb.classList.toggle('open');
  if(ov) ov.classList.toggle('show', open);
  if(hb) hb.classList.toggle('open', open);
  document.body.style.overflow = open ? 'hidden' : '';
}

function closeSidebar(){
  const sb = document.getElementById('sidebar');
  const ov = document.getElementById('sidebarOverlay');
  const hb = document.getElementById('sbHamburger');

  if(sb) sb.classList.remove('open');
  if(ov) ov.classList.remove('show');
  if(hb) hb.classList.remove('open');
  document.body.style.overflow = '';
}

/* Fecha com Escape */
document.addEventListener('keydown', e => {
  if(e.key === 'Escape') closeSidebar();
});

/* Fecha ao clicar em link da nav (mobile UX) */
document.querySelectorAll('.sb-nav-a').forEach(a => {
  a.addEventListener('click', () => {
    if(window.innerWidth < 769) closeSidebar();
  });
});

/* Toast global */
function toast(msg, type='ok', ms=3200){
  let w = document.getElementById('toastWrap');
  if(!w){
    w = document.createElement('div');
    w.id = 'toastWrap';
    w.className = 'toast-wrap';
    document.body.appendChild(w);
  }
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  const ico = type==='ok' ? '✅' : type==='err' ? '❌' : '💡';
  t.innerHTML = `<span>${ico}</span><span>${msg}</span>`;
  w.appendChild(t);
  setTimeout(() => {
    t.style.cssText += 'opacity:0;transform:translateX(10px);transition:.3s';
    setTimeout(() => t.remove(), 320);
  }, ms);
}

/* Logout */
function doLogout(){
  fetch('/florescer/api/auth.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'logout'})
  })
  .then(r => r.json())
  .then(d => { if(d.success) window.location.href = '/florescer/public/index.php'; })
  .catch(() => { window.location.href = '/florescer/public/index.php'; });
}

/* Restaura posição de scroll da nav */
(function(){
  const nav = document.querySelector('.sb-nav');
  if(!nav) return;
  nav.addEventListener('scroll', () => {
    sessionStorage.setItem('sidebarScroll', nav.scrollTop);
  });
  const saved  = sessionStorage.getItem('sidebarScroll');
  const active = nav.querySelector('.sb-nav-a.active');
  if(saved !== null){
    nav.scrollTop = parseInt(saved, 10);
  } else if(active){
    const offset = active.getBoundingClientRect().top
                 - nav.getBoundingClientRect().top
                 - nav.clientHeight / 2
                 + active.clientHeight / 2;
    nav.scrollTop = offset;
  }
})();
</script>