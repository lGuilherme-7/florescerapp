<?php
// ============================================================
// /public/views/chat.php — florescer v2.0
// Comunidade Q&A — sem global.js
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
$currentPage = 'chat';

// ── Sidebar vars ──────────────────────────────────────────────
$ud     = dbRow('SELECT xp, level, streak FROM users WHERE id=?', [$userId]);
$xp     = (int)($ud['xp']     ?? 0);
$level  = (int)($ud['level']  ?? 1);
$streak = (int)($ud['streak'] ?? 0);
$lvN    = ['','Semente','Broto','Estudante','Aplicado','Dedicado','Avançado','Expert','Mestre','Lendário'];
$lvName = $lvN[min($level,count($lvN)-1)] ?? 'Lendário';
$stages = [[0,6,'🌱','Semente'],[7,14,'🌿','Broto Inicial'],[15,29,'☘️','Planta Jovem'],
           [30,59,'🌲','Planta Forte'],[60,99,'🌳','Árvore Crescendo'],
           [100,149,'🌴','Árvore Robusta'],[150,199,'🎋','Árvore Antiga'],
           [200,299,'✨','Árvore Gigante'],[300,PHP_INT_MAX,'🏆','Árvore Lendária']];
$plant = ['emoji'=>'🌱','name'=>'Semente','pct'=>0];
foreach ($stages as [$mn,$mx,$em,$nm]) {
    if ($streak>=$mn && $streak<=$mx) {
        $r2=$mx<PHP_INT_MAX?$mx-$mn+1:1;
        $plant=['emoji'=>$em,'name'=>$nm,'pct'=>$mx<PHP_INT_MAX?min(100,round(($streak-$mn)/$r2*100)):100];
        break;
    }
}
if (!isset($_SESSION['active_objective'])) {
    $ao=dbRow('SELECT id FROM objectives WHERE user_id=? AND is_active=1 ORDER BY created_at DESC LIMIT 1',[$userId]);
    if (!$ao) $ao=dbRow('SELECT id FROM objectives WHERE user_id=? ORDER BY created_at DESC LIMIT 1',[$userId]);
    $_SESSION['active_objective']=$ao['id']??null;
}
$activeObjId=$_SESSION['active_objective'];
$allObjs=dbQuery('SELECT id,name FROM objectives WHERE user_id=? ORDER BY is_active DESC,created_at DESC',[$userId]);

$CATS = ['Todas','Geral','Matemática','Português','História','Geografia',
         'Biologia','Física','Química','Inglês','Redação','ENEM','Vestibular','Outros'];
$CAT_ICONS = [
    'Todas'=>'🌐','Geral'=>'💬','Matemática'=>'📐','Português'=>'📝',
    'História'=>'🏛️','Geografia'=>'🌍','Biologia'=>'🧬','Física'=>'⚡',
    'Química'=>'🧪','Inglês'=>'🌐','Redação'=>'✍️','ENEM'=>'📚',
    'Vestibular'=>'🎓','Outros'=>'❓',
];

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
  <title>florescer — Comunidade</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700;9..144,900&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
</head>

<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
  <header class="topbar">
    <div class="tb-left">
      <button class="hamburger" id="hamburger" onclick="toggleSidebar()">
        <span></span><span></span><span></span>
      </button>
      <span class="tb-title">💬 Comunidade</span>
    </div>
    <div class="xp-pill">⭐ <?= number_format($xp) ?> XP</div>
  </header>

  <main class="chat-content">

    <!-- Layout: sidebar de cats + feed -->
    <div class="chat-layout">

      <!-- Coluna esquerda: filtros + botão perguntar -->
      <aside class="cat-sidebar">
        <button class="btn-ask" onclick="openAsk()">
          ✏️ Fazer pergunta
        </button>

        <div class="cat-list">
          <?php foreach ($CATS as $cat): ?>
          <button class="cat-btn <?= $cat==='Todas'?'active':'' ?>"
                  data-cat="<?= htmlspecialchars($cat,ENT_QUOTES) ?>"
                  onclick="filterCat(this,'<?= htmlspecialchars($cat,ENT_QUOTES) ?>')">
            <span><?= $CAT_ICONS[$cat] ?? '💬' ?></span>
            <?= htmlspecialchars($cat,ENT_QUOTES) ?>
          </button>
          <?php endforeach; ?>
        </div>
      </aside>

      <!-- Coluna direita: feed de perguntas -->
      <section class="feed-col">
        <div class="feed-head">
          <span class="feed-title" id="feedTitle">Todas as perguntas</span>
          <span class="feed-count" id="feedCount"></span>
        </div>

        <!-- Feed carregado via JS -->
        <div id="feedList">
          <div class="loading-state">
            <div class="spinner"></div>
            Carregando perguntas...
          </div>
        </div>

        <div id="feedPagination"></div>
      </section>

      <!-- Painel direito: pergunta aberta + respostas -->
      <section class="post-panel" id="postPanel">
        <div class="post-placeholder">
          <span>💬</span>
          <p>Selecione uma pergunta para ler e responder</p>
        </div>
      </section>

    </div>
  </main>
</div>

<!-- ══ MODAL: Fazer pergunta ══ -->
<div class="modal-overlay" id="modalAsk" onclick="if(event.target===this)closeAsk()">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title">✏️ Fazer pergunta</span>
      <button class="modal-x" onclick="closeAsk()">✕</button>
    </div>
    <div class="modal-body">
      <div class="f-alert" id="askAlert"></div>

      <div class="fg">
        <label class="fl">Categoria</label>
        <select class="fc" id="askCat">
          <?php foreach (array_slice($CATS,1) as $cat): ?>
            <option value="<?= htmlspecialchars($cat,ENT_QUOTES) ?>"><?= $CAT_ICONS[$cat]??'' ?> <?= htmlspecialchars($cat,ENT_QUOTES) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="fg">
        <label class="fl">Título da pergunta *</label>
        <input class="fc" id="askTitle" type="text"
               placeholder="Ex: Como resolver equações do 2º grau?" maxlength="200"/>
      </div>

      <div class="fg">
        <label class="fl">Descreva sua dúvida *</label>
        <textarea class="fc" id="askMsg" rows="5"
                  placeholder="Explique com detalhes o que você não entendeu. Quanto mais detalhes, melhor!" maxlength="2000"></textarea>
        <div class="char-count" id="askCount">0/2000</div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-ghost" onclick="closeAsk()">Cancelar</button>
      <button class="btn-primary" id="btnAsk" onclick="submitAsk()">Publicar pergunta</button>
    </div>
  </div>
</div>

<style>
/* ══════════════════════════════════════════════════════════════
   CHAT / COMUNIDADE — estilos desta view
   Variáveis globais, sidebar e topbar vêm do sidebar.php
══════════════════════════════════════════════════════════════ */

/* ── Layout geral ───────────────────────────────────────────── */
.chat-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  height: calc(100vh - var(--hh));
}

.chat-layout {
  display: grid;
  grid-template-columns: 210px 1fr 390px;
  flex: 1;
  overflow: hidden;
  gap: 0;
}

/* ══ SIDEBAR DE CATEGORIAS ══════════════════════════════════ */
.cat-sidebar {
  border-right: 1px solid rgba(0,0,0,.06);
  padding: 1rem .8rem;
  display: flex;
  flex-direction: column;
  gap: .5rem;
  overflow-y: auto;
  background: var(--white);
}

.btn-ask {
  width: 100%;
  padding: .7rem .8rem;
  background: linear-gradient(135deg, var(--g500), var(--g600));
  color: #fff;
  border: none;
  border-radius: 10px;
  font-family: var(--fb);
  font-size: .82rem;
  font-weight: 600;
  cursor: pointer;
  text-align: center;
  margin-bottom: .5rem;
  transition: all var(--d) var(--e);
  box-shadow: 0 3px 10px rgba(64,145,108,.25);
  letter-spacing: .01em;
}
.btn-ask:hover {
  transform: translateY(-1px);
  box-shadow: 0 5px 16px rgba(64,145,108,.35);
}

.cat-list { display: flex; flex-direction: column; gap: .12rem; }

.cat-btn {
  width: 100%;
  padding: .48rem .65rem;
  border-radius: 8px;
  border: none;
  background: transparent;
  font-family: var(--fb);
  font-size: .78rem;
  color: #888;
  cursor: pointer;
  text-align: left;
  display: flex;
  align-items: center;
  gap: .45rem;
  transition: all var(--d) var(--e);
  line-height: 1.3;
}
.cat-btn:hover  { background: var(--n100); color: var(--n800); }
.cat-btn.active { background: rgba(64,145,108,.1); color: var(--g600); font-weight: 600; }

/* ══ FEED (coluna central) ══════════════════════════════════ */
.feed-col {
  border-right: 1px solid rgba(0,0,0,.06);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--n50);
}

.feed-head {
  padding: .9rem 1.15rem;
  border-bottom: 1px solid rgba(0,0,0,.05);
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
  background: var(--white);
}
.feed-title { font-family: var(--fd); font-size: .92rem; font-weight: 700; color: var(--n800); }
.feed-count { font-size: .71rem; color: #c0bab4; font-weight: 500; }

#feedList { flex: 1; overflow-y: auto; padding: .75rem .7rem; }

/* Card de post no feed */
.post-item {
  padding: .9rem 1rem;
  border-radius: 10px;
  border: 1px solid rgba(0,0,0,.06);
  margin-bottom: .45rem;
  cursor: pointer;
  background: var(--white);
  transition: all var(--d) var(--e);
}
.post-item:hover {
  border-color: rgba(64,145,108,.22);
  box-shadow: var(--sh0);
  transform: translateX(2px);
}
.post-item.active {
  border-color: var(--g400);
  background: rgba(64,145,108,.04);
  box-shadow: 0 0 0 1px rgba(64,145,108,.12);
}

.post-cat {
  display: inline-block;
  font-size: .6rem;
  font-weight: 700;
  padding: .12rem .45rem;
  border-radius: 20px;
  background: rgba(64,145,108,.1);
  color: var(--g600);
  margin-bottom: .32rem;
  letter-spacing: .04em;
  text-transform: uppercase;
}
.post-title {
  font-size: .84rem;
  font-weight: 600;
  color: var(--n800);
  margin-bottom: .22rem;
  line-height: 1.4;
}
.post-preview {
  font-size: .73rem;
  color: #b0aaa4;
  line-height: 1.45;
  margin-bottom: .42rem;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.post-meta {
  display: flex;
  align-items: center;
  gap: .5rem;
  font-size: .68rem;
  color: #c0bab4;
}
.post-avatar {
  width: 18px; height: 18px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--g500), var(--g700));
  display: flex; align-items: center; justify-content: center;
  font-size: .54rem; font-weight: 700; color: #fff;
  flex-shrink: 0;
}
.post-replies {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: .25rem;
  font-size: .68rem;
  color: var(--g500);
  font-weight: 600;
}

/* Paginação */
#feedPagination {
  padding: .65rem;
  display: flex;
  justify-content: center;
  gap: .3rem;
  flex-shrink: 0;
  border-top: 1px solid rgba(0,0,0,.05);
  background: var(--white);
}
.pg-btn {
  padding: .3rem .65rem;
  border-radius: 8px;
  border: 1px solid rgba(0,0,0,.1);
  background: none;
  font-family: var(--fb);
  font-size: .73rem;
  color: #888;
  cursor: pointer;
  transition: all var(--d) var(--e);
}
.pg-btn:hover        { border-color: var(--g400); color: var(--g500); }
.pg-btn.active       { background: var(--g500); border-color: var(--g500); color: #fff; }
.pg-btn:disabled     { opacity: .3; cursor: not-allowed; }

/* ══ PAINEL DO POST ABERTO ══════════════════════════════════ */
.post-panel {
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  background: var(--white);
}

.post-placeholder {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: .7rem;
  color: #ccc;
}
.post-placeholder span { font-size: 2.5rem; opacity: .35; }
.post-placeholder p    { font-size: .82rem; color: #c8c2bb; }

.open-post {
  padding: 1.3rem 1.35rem;
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
  flex: 1;
}

/* Cabeçalho do post aberto */
.open-post-cat {
  font-size: .62rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .07em;
  color: var(--g500);
  margin-bottom: .25rem;
}
.open-post-title {
  font-family: var(--fd);
  font-size: 1.05rem;
  font-weight: 900;
  color: var(--n800);
  letter-spacing: -.02em;
  line-height: 1.35;
}
.open-post-body {
  font-size: .84rem;
  color: #555;
  line-height: 1.7;
  white-space: pre-wrap;
  padding: .9rem 1rem;
  background: var(--n50);
  border-radius: 10px;
  border: 1px solid rgba(0,0,0,.05);
}
.open-post-author {
  display: flex;
  align-items: center;
  gap: .5rem;
  font-size: .72rem;
  color: #b0aaa4;
}
.open-post-author .av {
  width: 22px; height: 22px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--g500), var(--g700));
  display: flex; align-items: center; justify-content: center;
  font-size: .6rem; font-weight: 700; color: #fff;
}

/* Divisor de respostas */
.replies-divider {
  display: flex;
  align-items: center;
  gap: .6rem;
  margin: .15rem 0;
}
.replies-divider span {
  font-size: .72rem;
  font-weight: 600;
  color: #c0bab4;
  white-space: nowrap;
}
.replies-divider::before,
.replies-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: rgba(0,0,0,.07);
}

/* Card de resposta */
.reply-item {
  background: var(--n50);
  border: 1px solid rgba(0,0,0,.05);
  border-radius: 10px;
  padding: .9rem 1rem;
  margin-bottom: .45rem;
  transition: all var(--d) var(--e);
}
.reply-item:hover { border-color: rgba(64,145,108,.15); box-shadow: var(--sh0); }

.reply-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: .5rem;
}
.reply-author {
  display: flex;
  align-items: center;
  gap: .4rem;
  font-size: .72rem;
  color: #999;
}
.reply-author .av {
  width: 20px; height: 20px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--g500), var(--g700));
  display: flex; align-items: center; justify-content: center;
  font-size: .56rem; font-weight: 700; color: #fff;
}
.reply-upvote {
  display: flex;
  align-items: center;
  gap: .25rem;
  font-size: .7rem;
  color: #b0aaa4;
  background: none;
  border: 1px solid rgba(0,0,0,.09);
  border-radius: 50px;
  padding: .18rem .55rem;
  cursor: pointer;
  transition: all var(--d) var(--e);
}
.reply-upvote:hover { border-color: var(--g400); color: var(--g500); background: var(--g50); }

.reply-body {
  font-size: .83rem;
  color: #444;
  line-height: 1.65;
  white-space: pre-wrap;
}

/* Box de responder */
.reply-box {
  background: var(--n50);
  border: 1px solid rgba(0,0,0,.07);
  border-radius: 12px;
  padding: 1rem 1.1rem;
  margin-top: .3rem;
  flex-shrink: 0;
}
.reply-box-title {
  font-size: .8rem;
  font-weight: 600;
  color: var(--n800);
  margin-bottom: .65rem;
}
.reply-textarea {
  width: 100%;
  min-height: 90px;
  padding: .65rem .85rem;
  background: var(--white);
  border: 1px solid rgba(0,0,0,.1);
  border-radius: 8px;
  font-family: var(--fb);
  font-size: .83rem;
  color: var(--n800);
  resize: vertical;
  outline: none;
  transition: all var(--d) var(--e);
  line-height: 1.55;
}
.reply-textarea:focus {
  border-color: var(--g400);
  box-shadow: 0 0 0 3px rgba(64,145,108,.1);
}
.reply-textarea::placeholder { color: #ccc; }

.reply-foot {
  display: flex;
  justify-content: flex-end;
  margin-top: .55rem;
}

/* ══ ESTADOS (loading, vazio) ═══════════════════════════════ */
.loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .6rem;
  padding: 2.5rem 1rem;
  color: #c0bab4;
  font-size: .82rem;
}
.spinner {
  width: 18px; height: 18px;
  border: 2px solid rgba(64,145,108,.2);
  border-top-color: var(--g500);
  border-radius: 50%;
  animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.empty-feed {
  text-align: center;
  padding: 2.5rem 1rem;
  color: #c8c2bb;
}
.empty-feed p { font-size: .82rem; margin-top: .5rem; line-height: 1.55; }

/* ══ MODAL ══════════════════════════════════════════════════ */
.modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 200;
  background: rgba(0,0,0,.45);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.2rem;
  opacity: 0;
  pointer-events: none;
  transition: opacity var(--d) var(--e);
}
.modal-overlay.open { opacity: 1; pointer-events: all; }

.modal {
  background: var(--white);
  border: 1px solid rgba(0,0,0,.08);
  border-radius: 14px;
  width: 100%;
  max-width: 540px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: var(--sh3);
  transform: translateY(14px) scale(.97);
  transition: transform var(--d) var(--e);
}
.modal-overlay.open .modal { transform: translateY(0) scale(1); }

.modal-head {
  padding: 1rem 1.3rem;
  border-bottom: 1px solid rgba(0,0,0,.06);
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  background: var(--white);
  z-index: 1;
}
.modal-title { font-family: var(--fd); font-size: .96rem; font-weight: 700; color: var(--n800); }
.modal-x {
  width: 28px; height: 28px;
  border-radius: 50%;
  background: var(--n100);
  border: none;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  font-size: .8rem; color: #999;
  transition: all var(--d) var(--e);
}
.modal-x:hover { background: var(--red-l); color: var(--red); }

.modal-body { padding: 1.3rem; }
.modal-foot {
  padding: .9rem 1.3rem;
  border-top: 1px solid rgba(0,0,0,.06);
  display: flex;
  gap: .5rem;
  justify-content: flex-end;
  background: var(--white);
}

/* Form elements */
.fg { margin-bottom: .88rem; }
.fl { display: block; font-size: .74rem; font-weight: 500; color: #777; margin-bottom: .3rem; }
.fc {
  width: 100%;
  padding: .58rem .85rem;
  background: var(--n50);
  border: 1px solid rgba(0,0,0,.1);
  border-radius: 8px;
  color: var(--n800);
  font-family: var(--fb);
  font-size: .84rem;
  outline: none;
  transition: all var(--d) var(--e);
  appearance: none;
}
.fc:focus {
  border-color: var(--g400);
  background: var(--white);
  box-shadow: 0 0 0 3px rgba(64,145,108,.1);
}
.fc::placeholder { color: #ccc; }
textarea.fc    { resize: vertical; min-height: 110px; line-height: 1.55; }

.char-count { font-size: .68rem; color: #c8c2bb; text-align: right; margin-top: .25rem; }

.f-alert {
  padding: .58rem .82rem;
  border-radius: 8px;
  font-size: .79rem;
  margin-bottom: .85rem;
  display: none;
  line-height: 1.4;
}
.f-alert.show { display: block; }
.f-alert.err  { background: var(--red-l); border: 1px solid rgba(220,38,38,.2); color: var(--red); }

/* ══ BOTÕES ═════════════════════════════════════════════════ */
.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: .38rem;
  padding: .55rem 1.2rem;
  background: linear-gradient(135deg, var(--g500), var(--g600));
  color: #fff;
  border: none;
  border-radius: 50px;
  font-family: var(--fb);
  font-size: .83rem;
  font-weight: 600;
  cursor: pointer;
  transition: all var(--d) var(--e);
  box-shadow: 0 3px 12px rgba(64,145,108,.28);
}
.btn-primary:hover    { transform: translateY(-1px); box-shadow: 0 5px 18px rgba(64,145,108,.38); }
.btn-primary:disabled { opacity: .5; cursor: not-allowed; transform: none; }

.btn-sm {
  padding: .4rem .9rem;
  font-size: .78rem;
  border-radius: 50px;
  border: none;
  background: linear-gradient(135deg, var(--g500), var(--g600));
  color: #fff;
  font-family: var(--fb);
  font-weight: 600;
  cursor: pointer;
  transition: all var(--d) var(--e);
  box-shadow: 0 2px 8px rgba(64,145,108,.22);
}
.btn-sm:hover    { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(64,145,108,.32); }
.btn-sm:disabled { opacity: .5; cursor: not-allowed; transform: none; }

.btn-ghost {
  padding: .5rem 1rem;
  border-radius: 50px;
  background: transparent;
  border: 1px solid rgba(0,0,0,.1);
  font-family: var(--fb);
  font-size: .79rem;
  font-weight: 500;
  color: #888;
  cursor: pointer;
  transition: all var(--d) var(--e);
}
.btn-ghost:hover { background: var(--n100); color: var(--n800); }

/* ══ TOAST ══════════════════════════════════════════════════ */
.toast-wrap {
  position: fixed;
  bottom: 1.4rem; right: 1.4rem;
  z-index: 500;
  display: flex;
  flex-direction: column;
  gap: .4rem;
  pointer-events: none;
}
.toast {
  background: var(--n800);
  color: #eee;
  padding: .62rem .95rem;
  border-radius: 8px;
  font-size: .78rem;
  display: flex;
  align-items: center;
  gap: .4rem;
  animation: tin .22s var(--e) both;
  max-width: 280px;
  box-shadow: var(--sh3);
  pointer-events: all;
}
.toast.ok  { border-left: 3px solid var(--g400); }
.toast.err { border-left: 3px solid #f87171; }
@keyframes tin {
  from { opacity: 0; transform: translateX(10px); }
  to   { opacity: 1; transform: translateX(0); }
}

/* ══ RESPONSIVO ═════════════════════════════════════════════ */
@media (max-width: 1100px) {
  .chat-layout { grid-template-columns: 180px 1fr; }
  .post-panel  { display: none; }
}
@media (max-width: 768px) {
  .chat-layout { grid-template-columns: 1fr; }
  .cat-sidebar {
    flex-direction: row;
    flex-wrap: wrap;
    overflow-x: auto;
    border-right: none;
    border-bottom: 1px solid rgba(0,0,0,.06);
    padding: .6rem;
  }
  .cat-list   { flex-direction: row; flex-wrap: wrap; gap: .3rem; }
  .cat-btn    { white-space: nowrap; }
  .main       { margin-left: 0; }
}
</style>

<div class="toast-wrap" id="toastWrap"></div>

<script>
const API     = '/florescer/api/chat.php';
const ME_ID   = <?= $userId ?>;
const ME_NAME = <?= json_encode($userName) ?>;
const ME_INIT = <?= json_encode($userInitial) ?>;

let currentCat  = 'Todas';
let currentPage = 1;
let totalPosts  = 0;
let openPostId  = null;

/* ── Utils ───────────────────────────────────────────────── */
function toast(msg, type='ok', ms=3400) {
  const w = document.getElementById('toastWrap');
  const d = document.createElement('div');
  d.className = `toast ${type}`;
  d.innerHTML = `<span>${type==='ok'?'✅':'❌'}</span><span>${msg}</span>`;
  w.appendChild(d);
  setTimeout(()=>{d.style.opacity='0';d.style.transition='.3s';setTimeout(()=>d.remove(),320);},ms);
}

async function apiCall(body) {
  try {
    const r = await fetch(API, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify(body)
    });
    if (!r.ok) throw new Error('HTTP '+r.status);
    return await r.json();
  } catch(e) {
    return {success:false, message:'Erro: '+e.message};
  }
}

function esc(s) {
  return String(s??'')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function timeAgo(dateStr) {
  const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
  if (diff < 60)    return 'agora';
  if (diff < 3600)  return Math.floor(diff/60)+'min atrás';
  if (diff < 86400) return Math.floor(diff/3600)+'h atrás';
  return Math.floor(diff/86400)+'d atrás';
}

function initials(name) {
  return (name||'?').split(' ').map(w=>w[0]).join('').substring(0,2).toUpperCase();
}

/* ── Filtro de categoria ─────────────────────────────────── */
function filterCat(btn, cat) {
  document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  currentCat  = cat;
  currentPage = 1;
  document.getElementById('feedTitle').textContent =
    cat === 'Todas' ? 'Todas as perguntas' : cat;
  loadFeed();
  // Fecha painel aberto
  document.getElementById('postPanel').innerHTML =
    '<div class="post-placeholder"><span>💬</span><p>Selecione uma pergunta</p></div>';
  openPostId = null;
}

/* ── Carrega feed ────────────────────────────────────────── */
async function loadFeed() {
  const list = document.getElementById('feedList');
  list.innerHTML = '<div class="loading-state"><div class="spinner"></div>Carregando...</div>';

  const r = await apiCall({
    action: 'list',
    category: currentCat === 'Todas' ? '' : currentCat,
    page: currentPage
  });

  if (!r.success) {
    list.innerHTML = `<div class="empty-feed"><p>Erro ao carregar. Tente novamente.</p></div>`;
    return;
  }

  totalPosts = r.total || 0;
  document.getElementById('feedCount').textContent =
    totalPosts + (totalPosts===1?' pergunta':' perguntas');

  const posts = r.data || [];
  if (!posts.length) {
    list.innerHTML = `<div class="empty-feed">
      <span style="font-size:2rem;opacity:.3">❓</span>
      <p>Nenhuma pergunta ainda.<br>Seja o primeiro a perguntar!</p>
    </div>`;
    renderPagination();
    return;
  }

  list.innerHTML = posts.map(p => `
    <div class="post-item ${p.id==openPostId?'active':''}"
         id="pi-${p.id}" onclick="openPost(${p.id})">
      <div class="post-cat">${esc(p.category||'Geral')}</div>
      <div class="post-title">${esc(p.title||p.message||'Sem título')}</div>
      <div class="post-preview">${esc(p.message||'')}</div>
      <div class="post-meta">
        <div class="post-avatar">${initials(p.author_name)}</div>
        <span>${esc(p.author_name||'?')}</span>
        <span>·</span>
        <span>${timeAgo(p.created_at)}</span>
        <div class="post-replies">
          💬 ${p.reply_count||0}
        </div>
      </div>
    </div>
  `).join('');

  renderPagination();
}

function renderPagination() {
  const pg = document.getElementById('feedPagination');
  const totalPages = Math.max(1, Math.ceil(totalPosts / 20));
  if (totalPages <= 1) { pg.innerHTML=''; return; }

  let html = `<button class="pg-btn" onclick="goPage(${currentPage-1})" ${currentPage<=1?'disabled':''}>‹</button>`;
  for (let i=Math.max(1,currentPage-2); i<=Math.min(totalPages,currentPage+2); i++) {
    html += `<button class="pg-btn ${i===currentPage?'active':''}" onclick="goPage(${i})">${i}</button>`;
  }
  html += `<button class="pg-btn" onclick="goPage(${currentPage+1})" ${currentPage>=totalPages?'disabled':''}>›</button>`;
  pg.innerHTML = html;
}

function goPage(p) {
  currentPage = p;
  loadFeed();
  document.getElementById('feedList').scrollTop = 0;
}

/* ── Abrir post ──────────────────────────────────────────── */
async function openPost(id) {
  openPostId = id;

  // Destaca no feed
  document.querySelectorAll('.post-item').forEach(el => el.classList.remove('active'));
  document.getElementById('pi-'+id)?.classList.add('active');

  const panel = document.getElementById('postPanel');
  panel.innerHTML = '<div class="open-post"><div class="loading-state"><div class="spinner"></div>Carregando...</div></div>';

  // Busca o post na lista atual
  const listEl = document.getElementById('pi-'+id);
  const titleEl = listEl?.querySelector('.post-title');
  const title = titleEl?.textContent || '';

  // Carrega respostas
  const r = await apiCall({action:'replies', post_id:id});
  const replies = r.data || [];

  // Busca texto completo da mensagem
  const listR = await apiCall({action:'list', category:'', page:currentPage});
  const post  = (listR.data||[]).find(p=>p.id==id) || {};

  panel.innerHTML = `
    <div class="open-post">
      <div>
        <div class="open-post-cat">${esc(post.category||'Geral')}</div>
        <div class="open-post-title">${esc(post.title||post.message||title)}</div>
        <div class="open-post-author" style="margin:.6rem 0">
          <div class="av">${initials(post.author_name||'?')}</div>
          <span>${esc(post.author_name||'?')}</span>
          <span>·</span>
          <span>${timeAgo(post.created_at||new Date())}</span>
        </div>
        <div class="open-post-body">${esc(post.message||'')}</div>
      </div>

      <div>
        <div class="replies-divider">
          <span>${replies.length} resposta${replies.length!==1?'s':''}</span>
        </div>
        ${replies.length ? replies.map(rep => `
          <div class="reply-item" id="rep-${rep.id}">
            <div class="reply-header">
              <div class="reply-author">
                <div class="av">${initials(rep.author_name)}</div>
                <strong>${esc(rep.author_name)}</strong>
                <span style="color:#ccc">·</span>
                <span>${timeAgo(rep.created_at)}</span>
              </div>
              <button class="reply-upvote" onclick="upvote(${rep.id},this)">
                👍 <span class="uv-count">${rep.upvotes||0}</span>
              </button>
            </div>
            <div class="reply-body">${esc(rep.message)}</div>
          </div>
        `).join('') : '<div class="empty-feed" style="padding:1rem"><p>Nenhuma resposta ainda. Seja o primeiro a ajudar!</p></div>'}
      </div>

      <div class="reply-box">
        <div class="reply-box-title">✍️ Sua resposta</div>
        <textarea class="reply-textarea" id="replyTxt"
                  placeholder="Escreva sua resposta aqui..." maxlength="2000"></textarea>
        <div class="reply-foot">
          <button class="btn-sm" id="btnReply" onclick="submitReply(${id})">
            Enviar resposta
          </button>
        </div>
      </div>
    </div>
  `;
}

/* ── Enviar resposta ─────────────────────────────────────── */
async function submitReply(postId) {
  const txt = document.getElementById('replyTxt')?.value.trim();
  if (!txt) { toast('Escreva sua resposta.','err'); return; }

  const btn = document.getElementById('btnReply');
  btn.disabled=true; btn.textContent='Enviando…';

  const r = await apiCall({action:'reply', post_id:postId, message:txt});
  btn.disabled=false; btn.textContent='Enviar resposta';

  if (r.success) {
    toast('Resposta enviada! 💬');
    openPost(postId); // Recarrega o painel
  } else {
    toast(r.message||'Erro ao enviar.','err');
  }
}

/* ── Upvote ──────────────────────────────────────────────── */
async function upvote(id, btn) {
  btn.disabled = true;
  const r = await apiCall({action:'upvote', id});
  if (r.success) {
    btn.querySelector('.uv-count').textContent = r.upvotes;
    btn.style.color = 'var(--g500)';
    btn.style.borderColor = 'var(--g400)';
  }
}

/* ── Modal perguntar ─────────────────────────────────────── */
function openAsk() {
  document.getElementById('askAlert').className = 'f-alert';
  document.getElementById('askTitle').value = '';
  document.getElementById('askMsg').value   = '';
  document.getElementById('askCount').textContent = '0/2000';
  document.getElementById('modalAsk').classList.add('open');
  document.body.style.overflow = 'hidden';
  setTimeout(()=>document.getElementById('askTitle').focus(),150);
}
function closeAsk() {
  document.getElementById('modalAsk').classList.remove('open');
  document.body.style.overflow = '';
}
document.getElementById('askMsg')?.addEventListener('input', function(){
  document.getElementById('askCount').textContent = this.value.length + '/2000';
});

async function submitAsk() {
  const title = document.getElementById('askTitle').value.trim();
  const msg   = document.getElementById('askMsg').value.trim();
  const cat   = document.getElementById('askCat').value;
  const alert = document.getElementById('askAlert');

  if (!title) { alert.textContent='Informe o título.'; alert.className='f-alert err show'; return; }
  if (!msg)   { alert.textContent='Descreva sua dúvida.'; alert.className='f-alert err show'; return; }

  const btn = document.getElementById('btnAsk');
  btn.disabled=true; btn.textContent='Publicando…';

  const r = await apiCall({action:'ask', title, message:msg, category:cat});
  btn.disabled=false; btn.textContent='Publicar pergunta';

  if (r.success) {
    toast('Pergunta publicada! 🎉');
    closeAsk();
    currentPage=1;
    loadFeed();
  } else {
    alert.textContent = r.message||'Erro.'; alert.className='f-alert err show';
  }
}

/* ESC fecha modal */
document.addEventListener('keydown', e=>{
  if(e.key==='Escape') closeAsk();
});

/* ── Init ────────────────────────────────────────────────── */
loadFeed();
</script>

</body>
</html>