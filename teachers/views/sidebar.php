<?php
// ============================================================
// /professor/teachers/views/sidebar.php
// ============================================================

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/db.php';

$currentPage ??= '';
$teacher     ??= [];
$unreadMsgs  ??= 0;
$pendingRed  ??= 0;

$tName   = htmlspecialchars($teacher['name']       ?? 'Professor', ENT_QUOTES);
$tRating = number_format((float)($teacher['rating_avg'] ?? 0), 1);
$tRank   = (int)($teacher['rank_position'] ?? 0);
$tIni    = strtoupper(mb_substr($teacher['name'] ?? 'P', 0, 1, 'UTF-8'));

$nav = [
    'Geral' => [
        'dashboard'  => ['◈', 'Dashboard',  0],
    ],
    'Trabalho' => [
        'redacoes'   => ['📝', 'Redações',   $pendingRed],
        'aulas'      => ['📅', 'Aulas',      0],
        'chat'       => ['💬', 'Mensagens',  $unreadMsgs],
    ],
    'Financeiro' => [
        'financeiro' => ['💰', 'Financeiro', 0],
    ],
    'Conta' => [
        'perfil'     => ['👤', 'Meu Perfil', 0],
    ],
];
?>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{height:100%}
body{
  font-family:'DM Sans',system-ui,sans-serif;
  background:#f4fbf7;color:#111c16;
  display:flex;min-height:100%;
  -webkit-font-smoothing:antialiased;overflow-x:hidden;
}
:root{
  --g900:#0d2618;--g800:#14382a;--g700:#1a4a37;--g600:#225c44;
  --g500:#2d7a58;--g400:#3d9970;--g300:#55b88a;--g200:#8dd4b0;
  --g100:#c2ead6;--g50:#eaf6f0;--g25:#f4fbf7;
  --white:#fff;--n800:#111c16;--n400:#5a7a68;
  --n200:#b8d0c4;--n100:#daeae1;--n50:#f2f8f5;
  --gold:#c9a84c;--red:#d94040;--red-l:#fdeaea;
  --sw:248px;--hh:60px;
  --fd:'Fraunces',Georgia,serif;
  --fb:'DM Sans',system-ui,sans-serif;
  --r:14px;--rs:9px;--d:.2s;--e:cubic-bezier(.4,0,.2,1);
  --sh0:0 1px 3px rgba(0,0,0,.06);
  --sh1:0 4px 12px rgba(0,0,0,.07);
  --sh3:0 20px 48px rgba(0,0,0,.12);
}
::-webkit-scrollbar{width:4px}
::-webkit-scrollbar-thumb{background:var(--g200);border-radius:2px}

/* ── Sidebar ── */
.sidebar{
  width:var(--sw);height:100vh;
  position:fixed;top:0;left:0;
  background:var(--g900);
  display:flex;flex-direction:column;
  z-index:50;
  border-right:1px solid rgba(255,255,255,.05);
}
.sb-logo{
  padding:1.1rem 1.2rem .85rem;
  border-bottom:1px solid rgba(255,255,255,.06);
  display:flex;align-items:center;gap:.6rem;flex-shrink:0;
}
.sb-logo-mark{
  width:34px;height:34px;border-radius:10px;flex-shrink:0;
  background:linear-gradient(135deg,var(--g400),var(--g600));
  display:flex;align-items:center;justify-content:center;
  font-size:1rem;box-shadow:0 2px 10px rgba(45,122,88,.4);
}
.sb-logo-name{font-family:var(--fd);font-size:1.05rem;font-weight:600;color:var(--g100);line-height:1.1}
.sb-logo-tag{font-size:.52rem;color:rgba(141,212,176,.3);text-transform:uppercase;letter-spacing:.12em;margin-top:.06rem}

.sb-profile{
  margin:.7rem .8rem;
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.07);
  border-radius:var(--r);padding:.6rem .8rem;
  display:flex;align-items:center;gap:.55rem;
  text-decoration:none;flex-shrink:0;
  transition:background var(--d) var(--e);
}
.sb-profile:hover{background:rgba(255,255,255,.07)}
.sb-av{
  width:36px;height:36px;border-radius:50%;flex-shrink:0;
  background:linear-gradient(135deg,var(--g400),var(--g600));
  display:flex;align-items:center;justify-content:center;
  font-family:var(--fd);font-size:.95rem;font-weight:600;color:#fff;
  box-shadow:0 0 0 2px rgba(141,212,176,.2);
}
.sb-pname{font-size:.78rem;font-weight:500;color:var(--g100);line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sb-psub{font-size:.63rem;color:rgba(141,212,176,.4);margin-top:.06rem}

.sb-rating{
  margin:0 .8rem .5rem;
  display:flex;align-items:center;gap:.4rem;
  padding:.38rem .7rem;
  background:rgba(201,168,76,.08);
  border:1px solid rgba(201,168,76,.15);
  border-radius:var(--rs);
}
.sb-rating-stars{color:var(--gold);font-size:.75rem}
.sb-rating-val{font-family:var(--fd);font-size:.85rem;font-weight:600;color:var(--gold)}
.sb-rating-rank{font-size:.62rem;color:rgba(201,168,76,.5);margin-left:auto}

.sb-nav{flex:1;overflow-y:auto;padding:.3rem 0 .5rem}
.sb-grp{
  font-size:.52rem;text-transform:uppercase;letter-spacing:.1em;
  color:rgba(141,212,176,.22);padding:.65rem 1.2rem .18rem;display:block;
}
.sb-a{
  display:flex;align-items:center;gap:.5rem;
  padding:.42rem 1.2rem;font-size:.76rem;
  color:rgba(194,234,214,.4);text-decoration:none;
  border-left:2px solid transparent;
  transition:all var(--d) var(--e);
}
.sb-a:hover{color:var(--g100);background:rgba(255,255,255,.03)}
.sb-a.active{color:var(--g100);background:rgba(255,255,255,.06);border-left-color:var(--g300);font-weight:500}
.sb-a-ico{width:.9rem;text-align:center;font-size:.8rem;flex-shrink:0;opacity:.7}
.sb-badge{
  margin-left:auto;
  background:rgba(217,64,64,.2);color:#f87171;
  font-size:.55rem;font-weight:600;
  padding:.1rem .35rem;border-radius:20px;
}

.sb-foot{padding:.7rem .8rem;border-top:1px solid rgba(255,255,255,.05);flex-shrink:0}
.sb-logout{
  width:100%;display:flex;align-items:center;justify-content:center;gap:.4rem;
  padding:.4rem;border-radius:var(--rs);background:none;
  border:1px solid rgba(217,64,64,.15);
  color:rgba(217,64,64,.4);
  font-family:var(--fb);font-size:.71rem;cursor:pointer;
  transition:all var(--d) var(--e);
}
.sb-logout:hover{background:rgba(217,64,64,.07);color:var(--red)}

/* ── Layout main ── */
.main{
  margin-left:var(--sw);flex:1;min-width:0;
  display:flex;flex-direction:column;min-height:100vh;
}
.topbar{
  height:var(--hh);position:sticky;top:0;z-index:40;
  background:rgba(244,251,247,.94);backdrop-filter:blur(16px);
  border-bottom:1px solid var(--n100);
  display:flex;align-items:center;justify-content:space-between;
  padding:0 1.8rem;flex-shrink:0;
}
.tb-title{font-family:var(--fd);font-size:1.05rem;font-weight:600;color:var(--n800)}
.tb-right{display:flex;align-items:center;gap:.7rem}

/* Toast global */
.toast-wrap{position:fixed;bottom:1.4rem;right:1.4rem;z-index:500;display:flex;flex-direction:column;gap:.4rem;pointer-events:none}
.toast{background:var(--n800);color:#eee;padding:.6rem .95rem;border-radius:var(--rs);font-size:.79rem;display:flex;align-items:center;gap:.4rem;animation:toastIn .24s var(--e) both;max-width:270px;box-shadow:var(--sh3);pointer-events:all}
.toast.ok{border-left:3px solid var(--g400)}
.toast.err{border-left:3px solid #f87171}
.toast.info{border-left:3px solid var(--gold)}
@keyframes toastIn{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}

/* Hamburger mobile */
.hamburger{
  display:none;flex-direction:column;justify-content:center;align-items:center;gap:5px;
  cursor:pointer;background:rgba(45,122,88,.08);border:1px solid rgba(45,122,88,.14);
  border-radius:8px;width:38px;height:38px;padding:0;
  position:fixed;top:11px;left:11px;z-index:60;
  transition:background var(--d) var(--e);
}
.hamburger span{display:block;width:18px;height:2px;background:var(--g400);border-radius:1px;transition:all var(--d) var(--e);transform-origin:center}
.hamburger.open span:nth-child(1){transform:translateY(7px) rotate(45deg)}
.hamburger.open span:nth-child(2){opacity:0;transform:scaleX(0)}
.hamburger.open span:nth-child(3){transform:translateY(-7px) rotate(-45deg)}

.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.42);backdrop-filter:blur(4px);z-index:49;opacity:0;transition:opacity var(--d) var(--e)}
.sb-overlay.show{opacity:1}

@media(max-width:768px){
  .sidebar{transform:translateX(-100%);transition:transform var(--d) var(--e)}
  .sidebar.open{transform:translateX(0)}
  .sb-overlay{display:block}
  .main{margin-left:0}
  .hamburger{display:flex}
  .topbar{padding:0 1.1rem 0 3.5rem}
}
</style>

<button class="hamburger" id="sbHamburger" onclick="toggleSidebar()" aria-label="Abrir menu">
  <span></span><span></span><span></span>
</button>

<aside class="sidebar" id="sidebar">

  <div class="sb-logo">
    <div class="sb-logo-mark">🌱</div>
    <div>
      <div class="sb-logo-name">florescer</div>
      <div class="sb-logo-tag">professor</div>
    </div>
  </div>

  <a class="sb-profile" href="<?= TEACHER_VIEWS ?>/perfil.php">
    <div class="sb-av"><?= $tIni ?></div>
    <div style="flex:1;min-width:0">
      <div class="sb-pname"><?= $tName ?></div>
      <div class="sb-psub">Professor(a)</div>
    </div>
  </a>

  <?php if ((float)($teacher['rating_avg'] ?? 0) > 0): ?>
  <div class="sb-rating">
    <span class="sb-rating-stars">★★★★★</span>
    <span class="sb-rating-val"><?= $tRating ?></span>
    <?php if ($tRank > 0): ?>
      <span class="sb-rating-rank">#<?= $tRank ?> ranking</span>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <nav class="sb-nav">
    <?php foreach ($nav as $grupo => $itens): ?>
      <span class="sb-grp"><?= htmlspecialchars($grupo) ?></span>
      <?php foreach ($itens as $page => [$ico, $lbl, $badge]):
        $cls = $currentPage === $page ? ' active' : '';
      ?>
        <a class="sb-a<?= $cls ?>"
           href="<?= TEACHER_VIEWS ?>/<?= $page ?>.php">
          <span class="sb-a-ico"><?= $ico ?></span>
          <?= htmlspecialchars($lbl) ?>
          <?php if ($badge > 0): ?>
            <span class="sb-badge"><?= $badge ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <div class="sb-foot">
    <button class="sb-logout" onclick="doLogout()">↩ Sair da conta</button>
  </div>

</aside>

<div class="sb-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<script>
function toggleSidebar(){
  const sb=document.getElementById('sidebar');
  const ov=document.getElementById('sidebarOverlay');
  const hb=document.getElementById('sbHamburger');
  const open=sb.classList.toggle('open');
  if(ov) ov.classList.toggle('show',open);
  if(hb) hb.classList.toggle('open',open);
  document.body.style.overflow=open?'hidden':'';
}
function closeSidebar(){
  const sb=document.getElementById('sidebar');
  const ov=document.getElementById('sidebarOverlay');
  const hb=document.getElementById('sbHamburger');
  if(sb) sb.classList.remove('open');
  if(ov) ov.classList.remove('show');
  if(hb) hb.classList.remove('open');
  document.body.style.overflow='';
}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeSidebar();});

function toast(msg,type='ok',ms=3200){
  let w=document.getElementById('toastWrap');
  if(!w){w=document.createElement('div');w.id='toastWrap';w.className='toast-wrap';document.body.appendChild(w);}
  const t=document.createElement('div');
  t.className=`toast ${type}`;
  t.innerHTML=`<span>${type==='ok'?'✅':type==='err'?'❌':'💡'}</span><span>${msg}</span>`;
  w.appendChild(t);
  setTimeout(()=>{t.style.cssText+='opacity:0;transform:translateX(10px);transition:.3s';setTimeout(()=>t.remove(),320);},ms);
}

function doLogout(){
  fetch('<?= TEACHER_API ?>/auth.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'logout'})
  }).finally(()=>{window.location.href='<?= TEACHER_VIEWS ?>/index.php';});
}

// Scroll da sidebar — salva posição
(function(){
  const nav=document.querySelector('.sb-nav');
  if(!nav) return;
  nav.addEventListener('scroll',()=>sessionStorage.setItem('sbScroll',nav.scrollTop));
  const saved=sessionStorage.getItem('sbScroll');
  if(saved) nav.scrollTop=parseInt(saved,10);
})();
</script>