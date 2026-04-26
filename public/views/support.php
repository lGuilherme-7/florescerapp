<?php
// ============================================================
// /public/views/support.php — florescer v2.1
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
$currentPage = 'support';

// ── Stats de impacto da plataforma ───────────────────────────
$totalStudents = (int)(dbRow('SELECT COUNT(*) AS n FROM users')['n']           ?? 0);
$totalHoursAll = (int)(dbRow('SELECT COALESCE(SUM(total_min),0) AS n FROM daily_summaries')['n'] ?? 0);
$totalLessons  = (int)(dbRow(
    'SELECT COUNT(*) AS n FROM lessons WHERE is_completed=1'
)['n'] ?? 0);
$totalHoursDisplay = $totalHoursAll >= 60
    ? number_format(intdiv($totalHoursAll, 60)) . 'h'
    : $totalHoursAll . 'min';

// ── Sidebar vars ──────────────────────────────────────────────
$ud = dbRow('SELECT xp, level, streak FROM users WHERE id=?', [$userId]);
$xp    = (int)($ud['xp']     ?? 0);
$level = (int)($ud['level']  ?? 1);
$streak= (int)($ud['streak'] ?? 0);
$lvN   = ['','Semente','Broto','Estudante','Aplicado','Dedicado','Avançado','Expert','Mestre','Lendário'];
$lvName= $lvN[min($level,count($lvN)-1)] ?? 'Lendário';
$stages= [[0,6,'🌱','Semente'],[7,14,'🌿','Broto Inicial'],[15,29,'☘️','Planta Jovem'],
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
$allObjs     = dbQuery('SELECT id,name FROM objectives WHERE user_id=? ORDER BY is_active DESC,created_at DESC', [$userId]);

// ── Chave Pix (edite aqui) ────────────────────────────────────
$PIX_KEY = 'fdbe6929-e993-4a88-a55a-c83ee8b56cd9';

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
  <title>florescer — Apoiar</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700;9..144,900&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
  <style>
  /* ── Layout ──────────────────────────────────────────────── */
  .support-wrap{
    flex:1;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    padding:2rem 1.5rem;
    gap:1.2rem;
  }

  /* ── Mini stats de impacto ───────────────────────────────── */
  .impact-row{
    display:flex;
    align-items:center;
    background:var(--white);
    border:1px solid rgba(0,0,0,.06);
    border-radius:var(--r);
    box-shadow:var(--sh0);
    overflow:hidden;
    width:100%;
    max-width:520px;
  }
  .impact-item{
    flex:1;
    text-align:center;
    padding:.85rem .5rem;
  }
  .impact-val{
    font-family:var(--fd);
    font-size:1.35rem;
    font-weight:700;
    color:var(--g500);
    line-height:1;
    letter-spacing:-.03em;
  }
  .impact-lbl{
    font-size:.68rem;
    color:#bbb;
    margin-top:.22rem;
    font-weight:500;
  }
  .impact-divider{
    width:1px;
    height:36px;
    background:rgba(0,0,0,.07);
    flex-shrink:0;
  }

  /* ── Card principal ──────────────────────────────────────── */
  .support-card{
    background:linear-gradient(160deg, var(--g800) 0%, var(--g950) 60%, #0a1a10 100%);
    border:1px solid rgba(116,198,157,.1);
    border-radius:20px;
    box-shadow:var(--sh3);
    padding:2.2rem 2rem 1.8rem;
    text-align:center;
    width:100%;
    max-width:520px;
    position:relative;
    overflow:hidden;
  }
  .support-card::before{
    content:'';
    position:absolute;top:-70px;right:-70px;
    width:220px;height:220px;border-radius:50%;
    background:radial-gradient(circle,rgba(116,198,157,.07) 0%,transparent 70%);
    pointer-events:none;
  }
  .support-card::after{
    content:'';
    position:absolute;bottom:-50px;left:-50px;
    width:180px;height:180px;border-radius:50%;
    background:radial-gradient(circle,rgba(201,168,76,.05) 0%,transparent 70%);
    pointer-events:none;
  }

  /* Coração */
  .heart-wrap{ margin-bottom:.9rem; position:relative;z-index:1; }
  .heart{
    font-size:2.8rem;
    display:inline-block;
    animation:heartbeat 1.4s ease-in-out infinite;
  }
  @keyframes heartbeat{
    0%,100%{transform:scale(1)}
    14%    {transform:scale(1.18)}
    28%    {transform:scale(1)}
    42%    {transform:scale(1.13)}
    70%    {transform:scale(1)}
  }

  /* Título e descrição */
  .support-title{
    font-family:var(--fd);
    font-size:1.45rem;
    font-weight:900;
    color:var(--g100);
    letter-spacing:-.03em;
    margin-bottom:.6rem;
    position:relative;z-index:1;
  }
  .support-desc{
    font-size:.82rem;
    color:rgba(183,228,199,.55);
    line-height:1.7;
    margin-bottom:1.4rem;
    position:relative;z-index:1;
    max-width:400px;
    margin-left:auto;margin-right:auto;
  }

  /* ── Toggle tabs ─────────────────────────────────────────── */
  .pix-tabs{
    display:inline-flex;
    background:rgba(0,0,0,.25);
    border:1px solid rgba(116,198,157,.12);
    border-radius:50px;
    padding:3px;
    margin-bottom:1.1rem;
    position:relative;
    z-index:1;
  }
  .pix-tab{
    padding:.38rem 1.1rem;
    border-radius:50px;
    border:none;
    background:transparent;
    color:rgba(116,198,157,.4);
    font-family:var(--fb);
    font-size:.78rem;
    font-weight:600;
    cursor:pointer;
    transition:all .22s cubic-bezier(.4,0,.2,1);
    display:flex;align-items:center;gap:.35rem;
    white-space:nowrap;
  }
  .pix-tab.active{
    background:var(--g500);
    color:var(--white);
    box-shadow:0 2px 10px rgba(64,145,108,.35);
  }
  .pix-tab:not(.active):hover{
    color:rgba(116,198,157,.75);
    background:rgba(116,198,157,.06);
  }

  /* ── Painel Pix ──────────────────────────────────────────── */
  .pix-panel{
    position:relative;z-index:1;
    animation:fadePanel .25s ease both;
  }
  .pix-panel.hidden{ display:none; }
  @keyframes fadePanel{
    from{opacity:0;transform:translateY(6px)}
    to{opacity:1;transform:translateY(0)}
  }

  .pix-box{
    background:rgba(255,255,255,.05);
    border:1px solid rgba(116,198,157,.15);
    border-radius:var(--r);
    padding:.85rem 1rem;
    margin-bottom:1rem;
    text-align:left;
  }
  .pix-label{
    display:flex;align-items:center;gap:.4rem;
    font-size:.68rem;font-weight:600;
    color:rgba(116,198,157,.5);
    text-transform:uppercase;letter-spacing:.08em;
    margin-bottom:.55rem;
  }
  .pix-key-row{
    display:flex;align-items:center;gap:.6rem;
  }
  .pix-key{
    flex:1;min-width:0;
    font-family:'Courier New',monospace;
    font-size:.86rem;
    color:var(--g200);
    word-break:break-all;
    background:none;border:none;
    padding:0;
    user-select:all;
  }
  .btn-copy{
    display:inline-flex;align-items:center;gap:.35rem;
    padding:.42rem .9rem;
    background:var(--g500);
    color:var(--white);
    border:none;border-radius:50px;
    font-family:var(--fb);font-size:.75rem;font-weight:600;
    cursor:pointer;
    transition:all .22s ease;
    white-space:nowrap;flex-shrink:0;
    box-shadow:0 2px 10px rgba(64,145,108,.3);
  }
  .btn-copy:hover{background:var(--g400);transform:translateY(-1px)}
  .btn-copy.copied{ background:#16a34a; pointer-events:none; }

  /* ── Painel QR ───────────────────────────────────────────── */
  .qr-panel{
    position:relative;z-index:1;
    animation:fadePanel .25s ease both;
  }
  .qr-panel.hidden{ display:none; }

  .qr-frame{
    display:inline-block;
    padding:12px;
    background:var(--white);
    border-radius:16px;
    box-shadow:0 0 0 1px rgba(116,198,157,.18), 0 8px 32px rgba(0,0,0,.3);
    margin-bottom:.9rem;
    position:relative;
  }
  .qr-frame img{
    display:block;
    width:190px;
    height:190px;
    border-radius:8px;
    image-rendering:pixelated;
  }
  /* Cantos decorativos verdes */
  .qr-frame::before,.qr-frame::after{
    content:'';
    position:absolute;
    width:18px;height:18px;
    border-color:var(--g400);
    border-style:solid;
  }
  .qr-frame::before{
    top:-1px;left:-1px;
    border-width:3px 0 0 3px;
    border-radius:4px 0 0 0;
  }
  .qr-frame::after{
    bottom:-1px;right:-1px;
    border-width:0 3px 3px 0;
    border-radius:0 0 4px 0;
  }
  /* Cantos adicionais via spans */
  .qr-corner-tr,.qr-corner-bl{
    position:absolute;
    width:18px;height:18px;
    border-color:var(--g400);
    border-style:solid;
  }
  .qr-corner-tr{top:-1px;right:-1px;border-width:3px 3px 0 0;border-radius:0 4px 0 0;}
  .qr-corner-bl{bottom:-1px;left:-1px;border-width:0 0 3px 3px;border-radius:0 0 0 4px;}

  .qr-hint{
    font-size:.76rem;
    color:rgba(116,198,157,.4);
    margin-bottom:0;
    display:flex;align-items:center;justify-content:center;gap:.35rem;
  }
  .qr-hint-icon{ font-size:.95rem; animation:scanline 2s ease-in-out infinite; }
  @keyframes scanline{
    0%,100%{transform:translateY(0);opacity:.5}
    50%{transform:translateY(-3px);opacity:1}
  }

  /* ── Formas alternativas ─────────────────────────────────── */
  .alt-ways{
    margin-bottom:1.4rem;
    position:relative;z-index:1;
  }
  .alt-title{
    font-size:.72rem;font-weight:600;
    color:rgba(116,198,157,.35);
    text-transform:uppercase;letter-spacing:.07em;
    margin-bottom:.65rem;
  }
  .alt-grid{
    display:flex;justify-content:center;gap:.55rem;flex-wrap:wrap;
  }
  .alt-item{
    display:flex;align-items:center;gap:.35rem;
    background:rgba(255,255,255,.04);
    border:1px solid rgba(116,198,157,.1);
    border-radius:50px;
    padding:.38rem .8rem;
    font-size:.76rem;color:rgba(183,228,199,.5);
    transition:all .2s ease;
  }
  .alt-item:hover{
    background:rgba(116,198,157,.07);
    border-color:rgba(116,198,157,.2);
    color:rgba(183,228,199,.75);
  }
  .alt-ico{font-size:.9rem}

  /* Rodapé */
  .support-footer{
    font-size:.75rem;
    color:rgba(116,198,157,.28);
    line-height:1.6;
    position:relative;z-index:1;
    border-top:1px solid rgba(116,198,157,.07);
    padding-top:1rem;
    margin-top:1.2rem;
  }
  .support-footer strong{color:rgba(116,198,157,.45)}

  /* ── Divisor ─────────────────────────────────────────────── */
  .pix-divider{
    height:1px;
    background:rgba(116,198,157,.08);
    margin:1.2rem 0;
    position:relative;z-index:1;
  }

  /* ── Responsivo ──────────────────────────────────────────── */
  @media(max-width:520px){
    .support-card{padding:1.6rem 1.1rem 1.4rem}
    .support-title{font-size:1.2rem}
    .impact-val{font-size:1.1rem}
    .qr-frame img{width:165px;height:165px}
  }
  </style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
  <header class="topbar">
    <div class="tb-left">
      <button class="hamburger" id="hamburger" onclick="toggleSidebar()">
        <span></span><span></span><span></span>
      </button>
      <span class="tb-title">💚 Apoiar</span>
    </div>
    <div class="xp-pill">⭐ <?= number_format($xp) ?> XP</div>
  </header>

  <div class="support-wrap">

    <!-- Mini stats -->
    <div class="impact-row">
      <div class="impact-item">
        <div class="impact-val"><?= number_format($totalStudents) ?></div>
        <div class="impact-lbl">🌱 Estudantes</div>
      </div>
      <div class="impact-divider"></div>
      <div class="impact-item">
        <div class="impact-val"><?= $totalHoursDisplay ?></div>
        <div class="impact-lbl">⏱ Horas estudadas</div>
      </div>
      <div class="impact-divider"></div>
      <div class="impact-item">
        <div class="impact-val"><?= number_format($totalLessons) ?></div>
        <div class="impact-lbl">📖 Aulas concluídas</div>
      </div>
    </div>

    <!-- Card principal -->
    <div class="support-card">

      <div class="heart-wrap">
        <span class="heart">💚</span>
      </div>

      <h1 class="support-title">Ajude o florescer a crescer</h1>
      <p class="support-desc">
        O florescer é gratuito e feito com dedicação para ajudar estudantes como você.
        Se a plataforma fez diferença nos seus estudos, considere apoiar com qualquer valor —
        isso mantém o projeto vivo e em constante melhoria.
      </p>

      <!-- Toggle Pix / QR Code -->
      <div class="pix-tabs">
        <button class="pix-tab active" id="tabPix" onclick="showTab('pix')">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
          Copiar Pix
        </button>
        <button class="pix-tab" id="tabQr" onclick="showTab('qr')">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM17 17h3v3h-3zM14 20h3"/></svg>
          QR Code
        </button>
      </div>

      <!-- PAINEL: Chave Pix -->
      <div class="pix-panel" id="panelPix">
        <div class="pix-box">
          <div class="pix-label">
            <span class="pix-icon">💚</span>
            <span>Chave Pix</span>
          </div>
          <div class="pix-key-row">
            <code class="pix-key" id="pixKey"><?= htmlspecialchars($PIX_KEY, ENT_QUOTES) ?></code>
            <button class="btn-copy" id="btnCopy" onclick="copyPix()">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
              </svg>
              Copiar
            </button>
          </div>
        </div>
        <p style="font-size:.73rem;color:rgba(116,198,157,.35);margin-bottom:0;position:relative;z-index:1">
          📱 Abra o app do seu banco, vá em Pix e cole a chave acima
        </p>
      </div>

      <!-- PAINEL: QR Code -->
      <div class="qr-panel hidden" id="panelQr">
        <div class="qr-frame">
          <span class="qr-corner-tr"></span>
          <span class="qr-corner-bl"></span>
          <img src="../img/qr.jpeg" alt="QR Code para apoiar o florescer"/>
        </div>
        <p class="qr-hint">
          <span class="qr-hint-icon">📷</span>
          Aponte a câmera do seu banco para o QR Code
        </p>
      </div>

      <div class="pix-divider"></div>

      <!-- Formas alternativas -->
      <div class="alt-ways">
        <p class="alt-title">Outras formas de apoiar:</p>
        <div class="alt-grid">
          <div class="alt-item"><span class="alt-ico">🗣️</span><span>Indique para amigos</span></div>
          <div class="alt-item"><span class="alt-ico">⭐</span><span>Deixe um feedback</span></div>
          <div class="alt-item"><span class="alt-ico">📲</span><span>Compartilhe nas redes</span></div>
        </div>
      </div>

      <p class="support-footer">
        Feito com 💚 para estudantes brasileiros. Obrigado por fazer parte disso, <strong><?= $userName ?></strong>!
      </p>

    </div><!-- /support-card -->
  </div><!-- /support-wrap -->
</div><!-- /main -->

<script>
// ── Toggle de abas ────────────────────────────────────────────
function showTab(tab){
  const isPix = tab === 'pix';
  document.getElementById('tabPix').classList.toggle('active', isPix);
  document.getElementById('tabQr').classList.toggle('active', !isPix);
  document.getElementById('panelPix').classList.toggle('hidden', !isPix);
  document.getElementById('panelQr').classList.toggle('hidden', isPix);
  // Re-trigger animation
  const panel = isPix ? document.getElementById('panelPix') : document.getElementById('panelQr');
  panel.style.animation = 'none';
  panel.offsetHeight; // reflow
  panel.style.animation = '';
}

// ── Copiar Pix ────────────────────────────────────────────────
function copyPix(){
  const key = document.getElementById('pixKey').textContent.trim();
  const btn = document.getElementById('btnCopy');

  if(navigator.clipboard && window.isSecureContext){
    navigator.clipboard.writeText(key).then(onCopied).catch(fallbackCopy);
  } else {
    fallbackCopy();
  }

  function fallbackCopy(){
    const ta = document.createElement('textarea');
    ta.value = key;
    ta.style.cssText = 'position:fixed;left:-9999px;top:-9999px';
    document.body.appendChild(ta);
    ta.focus(); ta.select();
    try{ document.execCommand('copy'); onCopied(); }
    catch{ alert('Copie manualmente: ' + key); }
    document.body.removeChild(ta);
  }

  function onCopied(){
    btn.innerHTML = '✅ Copiada!';
    btn.classList.add('copied');
    setTimeout(()=>{
      btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Copiar';
      btn.classList.remove('copied');
    }, 2500);
  }
}
</script>

</body>
</html>