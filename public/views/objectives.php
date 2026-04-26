<?php
// ============================================================
// public/views/objectives.php — florescer v2.4
// CORREÇÕES: anti-duplicata, erro de conexão, avg para performance
// ============================================================
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/unit_helper.php';

startSession();
authGuard();
$user        = currentUser();
$userId      = (int)$user['id'];
$userName    = htmlspecialchars($user['name'] ?? 'Estudante', ENT_QUOTES, 'UTF-8');
$userInitial = strtoupper(mb_substr($user['name'] ?? 'E', 0, 1, 'UTF-8'));
$currentPage = 'objectives';

// ── Sidebar vars ──────────────────────────────────────────────
$ud     = dbRow('SELECT xp, level, streak FROM users WHERE id=?', [$userId]);
$xp     = (int)($ud['xp']     ?? 0);
$level  = (int)($ud['level']  ?? 1);
$streak = (int)($ud['streak'] ?? 0);
$lvN    = ['','Semente','Broto','Estudante','Aplicado','Dedicado','Avançado','Expert','Mestre','Lendário'];
$lvName = $lvN[min($level, count($lvN)-1)] ?? 'Lendário';
$stages = [[0,6,'🌱','Semente'],[7,14,'🌿','Broto Inicial'],[15,29,'☘️','Planta Jovem'],
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

// ── Dados da view ─────────────────────────────────────────────
$objTypes   = dbQuery('SELECT id, name FROM objective_types  ORDER BY id');
$teachTypes = dbQuery('SELECT id, name, periods FROM teaching_types ORDER BY id');

$objectives = dbQuery(
    'SELECT o.id, o.name, o.grade_level, o.default_avg, o.is_active, o.created_at,
            o.unit_count,
            ot.name AS type_name,
            tt.name AS teach_name,
            tt.periods AS tt_periods,
            (SELECT COUNT(*) FROM subjects s
             WHERE s.objective_id=o.id AND s.is_active=1) AS subj_count,
            (SELECT COUNT(*) FROM lessons l
             JOIN topics t ON t.id=l.topic_id
             JOIN subjects s ON s.id=t.subject_id
             WHERE s.objective_id=o.id AND l.is_completed=1) AS lessons_done,
            (SELECT COUNT(*) FROM lessons l
             JOIN topics t ON t.id=l.topic_id
             JOIN subjects s ON s.id=t.subject_id
             WHERE s.objective_id=o.id) AS lessons_total
     FROM objectives o
     LEFT JOIN objective_types ot ON ot.id=o.objective_type_id
     LEFT JOIN teaching_types  tt ON tt.id=o.teaching_type_id
     WHERE o.user_id=?
     ORDER BY o.is_active DESC, o.created_at DESC',
    [$userId]
);

$lvNameSidebar = $lvName;

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
  <title>florescer — Objetivos</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700;9..144,900&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
</head>
  <!-- Favicon básico -->
  <link rel="icon" href="/florescer/public/img/fav/favicon.ico">

  <!-- PNG moderno -->
  <link rel="icon" type="image/png" sizes="32x32" href="/florescer/public/img/fav/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/florescer/public/img/fav/favicon-16.png">

  <!-- Apple (iOS) -->
  <link rel="apple-touch-icon" sizes="180x180" href="/florescer/public/img/fav/favicon-180.png">

  <!-- Android / PWA -->
  <link rel="manifest" href="/florescer/public/img/fav/site.webmanifest">

  <!-- Windows (tiles) -->
  <meta name="msapplication-TileImage" content="/florescer/public/img/fav/mstile-150x150.png">
  <meta name="msapplication-TileColor" content="#ffffff">

  <!-- Cor da barra do navegador (mobile) -->
  <meta name="theme-color" content="#ffffff">


<body>

<?php
$lvName = $lvNameSidebar;
include __DIR__ . '/sidebar.php';
?>

<div class="main">
  <header class="topbar">
    <div class="tb-left">
      <button class="hamburger" id="hamburger" onclick="toggleSidebar()">
        <span></span><span></span><span></span>
      </button>
      <span class="tb-title">🎯 Objetivos</span>
    </div>
    <div class="xp-pill">⭐ <?= number_format($xp) ?> XP</div>
  </header>

  <main class="obj-content">

    <div class="page-head">
      <div>
        <div class="page-title">Meus Objetivos</div>
        <div class="page-sub">Organize seus estudos por objetivo</div>
      </div>
      <button class="btn-primary" onclick="openModal()">+ Novo objetivo</button>
    </div>

    <div class="obj-grid" id="objGrid">
      <?php if (empty($objectives)): ?>
        <div class="empty">
          <span class="empty-ico">🎯</span>
          <p>Você ainda não tem nenhum objetivo.<br>Crie o primeiro para começar a organizar seus estudos!</p>
          <button class="btn-primary" onclick="openModal()">+ Criar primeiro objetivo</button>
        </div>
      <?php else: ?>
        <?php foreach ($objectives as $o):
          $pct = $o['lessons_total']>0 ? round($o['lessons_done']/$o['lessons_total']*100) : 0;
          $realUC = getUnitCount([
              'unit_count' => $o['unit_count'],
              'tt_periods' => $o['tt_periods'],
          ]);
          $jsName  = htmlspecialchars(addslashes($o['name']), ENT_QUOTES);
          $jsAvg   = htmlspecialchars(addslashes($o['default_avg'] ?? ''), ENT_QUOTES);
          $eName   = htmlspecialchars($o['name'], ENT_QUOTES);
          $ttPer   = (int)$o['tt_periods'];
          $unitLabel = match($ttPer) {
              4 => '4 bimestres',
              3 => '3 trimestres',
              2 => '2 semestres',
              default => $realUC . ($realUC === 1 ? ' unidade' : ' unidades'),
          };
          $avgDisplay = $o['default_avg'] !== null
            ? number_format((float)$o['default_avg'], 1, ',', '.')
            : '—';
        ?>
        <div class="obj-card" id="card-<?= $o['id'] ?>">
          <div class="obj-top" onclick="goToMaterials(<?= $o['id'] ?>)" title="Entrar em <?= $eName ?>">
            <div class="obj-type-tag"><?= htmlspecialchars($o['type_name']??'Objetivo',ENT_QUOTES) ?></div>
            <div class="obj-name">
              <?= $eName ?>
              <?php if ($o['is_active']): ?>
                <span class="badge-ativo">ativo</span>
              <?php endif; ?>
            </div>
            <?php if ($o['teach_name'] || $o['grade_level']): ?>
              <div class="obj-sub">
                <?= htmlspecialchars($o['teach_name']??'',ENT_QUOTES) ?>
                <?= $o['grade_level'] ? ' · '.htmlspecialchars($o['grade_level'],ENT_QUOTES) : '' ?>
                · <?= htmlspecialchars($unitLabel, ENT_QUOTES) ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="obj-body">
            <div class="obj-meta">
              <span>📚 <?= (int)$o['subj_count'] ?> matéria<?= $o['subj_count']!=1?'s':'' ?></span>
              <span>📋 <?= (int)$o['lessons_done'] ?>/<?= (int)$o['lessons_total'] ?> aulas</span>
              <span>✔ média mín. <?= $avgDisplay ?></span>
            </div>
            <div class="prog-bar">
              <div class="prog-fill" style="width:<?= $pct ?>%"></div>
            </div>
            <div class="obj-pct"><?= $pct ?>% concluído</div>
            <div class="obj-actions">
              <?php if (!$o['is_active']): ?>
                <button class="btn-activate" onclick="activateObj(<?= $o['id'] ?>,'<?= $eName ?>')">
                  ⚡ Ativar
                </button>
              <?php endif; ?>
              <button class="btn-ghost"
                onclick="openModal(<?= $o['id'] ?>,'<?= $jsName ?>','<?= $jsAvg ?>',<?= $realUC ?>)">
                ✏️ Editar
              </button>
              <button class="btn-danger"
                onclick="deleteObj(<?= $o['id'] ?>,'<?= $jsName ?>')">
                🗑 Excluir
              </button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </main>
</div>

<!-- Modal criar/editar -->
<div class="modal-overlay" id="modalOverlay" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title" id="modalTitle">Novo objetivo</span>
      <button class="modal-x" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body">
      <div class="f-alert" id="mAlert"></div>
      <input type="hidden" id="mId"/>

      <div class="fg" id="grpType">
        <label class="fl">Tipo de objetivo</label>
        <select class="fc" id="mType" onchange="onTypeChange()">
          <option value="">Selecione...</option>
          <?php foreach ($objTypes as $t): ?>
            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name'],ENT_QUOTES) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="fg" id="grpGrade" style="display:none">
        <label class="fl">Série</label>
        <select class="fc" id="mGrade">
          <option value="">Selecione...</option>
          <?php foreach (['5º Ano — Fundamental','6º Ano — Fundamental','7º Ano — Fundamental',
                          '8º Ano — Fundamental','9º Ano — Fundamental',
                          '1º Ano — Ensino Médio','2º Ano — Ensino Médio','3º Ano — Ensino Médio'] as $g): ?>
            <option><?= htmlspecialchars($g,ENT_QUOTES) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="fg">
        <label class="fl">Nome / Identificação *</label>
        <input class="fc" id="mName" type="text"
               placeholder="Ex: 2º Ensino Médio, ENEM 2026..." maxlength="120"/>
      </div>

      <div class="frow">
        <div class="fg" id="grpTeach">
          <label class="fl">Tipo de ensino</label>
          <select class="fc" id="mTeach" onchange="onTeachChange()">
            <?php foreach ($teachTypes as $t): ?>
              <option value="<?= (int)$t['id'] ?>" data-periods="<?= (int)$t['periods'] ?>">
                <?= htmlspecialchars($t['name'],ENT_QUOTES) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg">
          <label class="fl">Média de aprovação</label>
          <input class="fc" id="mAvg" type="number" min="0" max="10" step="0.1" placeholder="Ex: 7.0"/>
        </div>
      </div>

      <div class="fg" id="grpUnitCount" style="display:none">
        <label class="fl">
          Quantas unidades/módulos?
          <small style="color:#aaa">(1 a 12)</small>
        </label>
        <input class="fc" id="mUnitCount" type="number" min="1" max="12" value="4"
               placeholder="Ex: 4"/>
        <small style="font-size:.7rem;color:#bbb;margin-top:.2rem;display:block">
          Define quantas unidades aparecerão em Matérias e Desempenho.
        </small>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-ghost" onclick="closeModal()">Cancelar</button>
      <!-- data-submitting evita duplo clique -->
      <button class="btn-primary" id="btnSave" onclick="submitObj()">Salvar objetivo</button>
    </div>
  </div>
</div>

<!-- Modal confirmar exclusão -->
<div class="modal-overlay" id="delOverlay" onclick="if(event.target===this)closeDelModal()">
  <div class="modal modal-sm">
    <div class="modal-head">
      <span class="modal-title">🗑 Excluir objetivo</span>
      <button class="modal-x" onclick="closeDelModal()">✕</button>
    </div>
    <div class="modal-body">
      <div class="del-preview" id="delPreview"></div>
      <p class="del-warn">
        Isso vai apagar permanentemente <strong>todas as matérias, assuntos, aulas e anotações</strong>
        deste objetivo. Esta ação não pode ser desfeita.
      </p>
    </div>
    <div class="modal-foot">
      <button class="btn-ghost" onclick="closeDelModal()">Cancelar</button>
      <button class="btn-danger-solid" id="btnConfirmDel" onclick="confirmDelete()">
        Excluir permanentemente
      </button>
    </div>
  </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<style>
/* ══════════════════════════════════════════════════════════════
   OBJECTIVES — Mobile First
══════════════════════════════════════════════════════════════ */

:root{
  --g950:#0d1f16;--g800:#1a3a2a;--g700:#1e4d35;--g600:#2d6a4f;
  --g500:#40916c;--g400:#52b788;--g300:#74c69d;--g200:#b7e4c7;--g50:#f0faf4;
  --n800:#1c1c1a;--n100:#f5f2ee;--n50:#faf8f5;--white:#fff;
  --gold:#c9a84c;--red:#dc2626;--red-l:#fee2e2;
  --sw:242px;--hh:58px;
  --fd:'Fraunces',Georgia,serif;--fb:'Inter',system-ui,sans-serif;
  --r:14px;--rs:8px;--d:.2s;--e:cubic-bezier(.4,0,.2,1);
  --sh0:0 1px 3px rgba(0,0,0,.05);--sh1:0 2px 8px rgba(0,0,0,.06);
  --sh2:0 4px 16px rgba(0,0,0,.08);--sh3:0 12px 32px rgba(0,0,0,.11);
}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{
  font-family:var(--fb);
  background:var(--n50);
  color:var(--n800);
  display:flex;
  overflow-x:hidden;
  -webkit-font-smoothing:antialiased;
}

/* ── MAIN — mobile first ── */
.main{
  margin-left:0;
  flex:1;
  min-height:100vh;
  display:flex;
  flex-direction:column;
  min-width:0;
  width:100%;
}

/* ── TOPBAR ── */
.topbar{
  height:var(--hh);
  background:rgba(250,248,245,.95);
  backdrop-filter:blur(14px);
  -webkit-backdrop-filter:blur(14px);
  border-bottom:1px solid rgba(0,0,0,.055);
  display:flex;
  align-items:center;
  justify-content:space-between;
  /* padding-left: 60px para não sobrepor o hamburger fixo */
  padding:0 1rem 0 60px;
  position:sticky;top:0;z-index:40;
  flex-shrink:0;
  gap:.6rem;
  transition:background .3s,box-shadow .3s;
}
.topbar.scrolled{
  background:rgba(250,248,245,.98);
  box-shadow:0 1px 8px rgba(0,0,0,.07);
}
.tb-left{
  display:flex;align-items:center;gap:.65rem;
  min-width:0;flex:1;
}
/* hamburger local (legacy — mantido para não quebrar JS das views) */
.hamburger{
  display:none; /* o hamburger fixo do sidebar.php já cuida do mobile */
}
.tb-title{
  font-family:var(--fd);font-size:.93rem;font-weight:600;
  color:var(--n800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.xp-pill{
  display:inline-flex;align-items:center;gap:.28rem;
  padding:.24rem .7rem;
  background:linear-gradient(135deg,rgba(201,168,76,.12),rgba(201,168,76,.06));
  border:1px solid rgba(201,168,76,.2);
  border-radius:20px;
  font-size:.72rem;font-weight:600;color:var(--gold);
  white-space:nowrap;flex-shrink:0;
}

/* ── CONTENT ── */
.obj-content{
  flex:1;
  padding:1rem;
  display:flex;
  flex-direction:column;
  gap:1.1rem;
}

/* ── PAGE HEAD ── */
.page-head{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  flex-wrap:wrap;
  gap:.75rem;
}
.page-title{
  font-family:var(--fd);font-size:1.3rem;font-weight:900;
  color:var(--n800);letter-spacing:-.03em;
}
.page-sub{font-size:.77rem;color:#aaa;margin-top:.18rem}

/* ── BUTTONS ── */
.btn-primary{
  display:inline-flex;align-items:center;gap:.35rem;
  padding:.55rem 1.1rem;
  background:linear-gradient(135deg,var(--g500),var(--g600));
  color:#fff;border:none;border-radius:50px;
  font-family:var(--fb);font-size:.82rem;font-weight:600;
  cursor:pointer;transition:all var(--d) var(--e);
  box-shadow:0 3px 12px rgba(64,145,108,.28);
  min-height:40px;white-space:nowrap;
  -webkit-tap-highlight-color:transparent;
}
.btn-primary:hover:not(:disabled){transform:translateY(-1px);box-shadow:0 5px 18px rgba(64,145,108,.38)}
.btn-primary:disabled{opacity:.6;cursor:not-allowed}

.btn-ghost{
  display:inline-flex;align-items:center;gap:.26rem;
  padding:.34rem .72rem;border-radius:50px;
  background:transparent;border:1px solid rgba(0,0,0,.1);
  font-family:var(--fb);font-size:.72rem;font-weight:500;color:#888;
  cursor:pointer;transition:all var(--d) var(--e);
  min-height:36px;-webkit-tap-highlight-color:transparent;
}
.btn-ghost:hover{background:var(--n100);color:var(--n800)}

.btn-danger{
  display:inline-flex;align-items:center;gap:.26rem;
  padding:.34rem .72rem;border-radius:50px;
  background:transparent;border:1px solid rgba(220,38,38,.15);
  font-family:var(--fb);font-size:.72rem;font-weight:500;color:var(--red);
  cursor:pointer;transition:all var(--d) var(--e);
  min-height:36px;-webkit-tap-highlight-color:transparent;
}
.btn-danger:hover{background:var(--red-l)}

.btn-activate{
  display:inline-flex;align-items:center;gap:.26rem;
  padding:.34rem .72rem;border-radius:50px;
  background:rgba(64,145,108,.08);border:1px solid rgba(64,145,108,.2);
  font-family:var(--fb);font-size:.72rem;font-weight:500;color:var(--g500);
  cursor:pointer;transition:all var(--d) var(--e);
  min-height:36px;-webkit-tap-highlight-color:transparent;
}
.btn-activate:hover{background:rgba(64,145,108,.15)}

.btn-danger-solid{
  display:inline-flex;align-items:center;gap:.35rem;
  padding:.52rem 1.1rem;
  background:var(--red);border:none;border-radius:var(--rs);
  color:#fff;font-family:var(--fb);font-size:.79rem;font-weight:600;
  cursor:pointer;transition:all var(--d) var(--e);
  min-height:40px;-webkit-tap-highlight-color:transparent;
}
.btn-danger-solid:hover{background:#b91c1c}
.btn-danger-solid:disabled{opacity:.5;cursor:not-allowed}

/* ── GRID — mobile: 1 coluna ── */
.obj-grid{
  display:grid;
  grid-template-columns:1fr;
  gap:1rem;
}

/* ── CARDS ── */
.obj-card{
  background:var(--white);
  border:1px solid rgba(0,0,0,.06);
  border-radius:var(--r);
  overflow:hidden;
  box-shadow:var(--sh0);
  transition:transform var(--d) var(--e),box-shadow var(--d) var(--e);
}
.obj-card:hover{transform:translateY(-3px);box-shadow:var(--sh2)}

.obj-top{
  background:linear-gradient(135deg,var(--g800),var(--g950));
  padding:1rem 1.1rem;
  position:relative;overflow:hidden;
  cursor:pointer;transition:filter var(--d) var(--e);
}
.obj-top:hover{filter:brightness(1.08)}
.obj-top::after{
  content:'';position:absolute;top:-20px;right:-20px;
  width:80px;height:80px;border-radius:50%;
  background:rgba(116,198,157,.06);
}
.obj-type-tag{
  display:inline-block;
  padding:.13rem .46rem;
  background:rgba(116,198,157,.12);border:1px solid rgba(116,198,157,.16);
  border-radius:20px;font-size:.63rem;color:var(--g300);
  margin-bottom:.38rem;
}
.obj-name{
  font-family:var(--fd);font-size:.97rem;font-weight:700;
  color:rgba(240,250,244,.92);letter-spacing:-.02em;
  position:relative;z-index:1;word-break:break-word;
}
.obj-sub{
  font-size:.66rem;color:rgba(116,198,157,.42);
  margin-top:.13rem;position:relative;z-index:1;
}
.badge-ativo{
  display:inline-block;padding:.1rem .38rem;
  background:rgba(64,145,108,.2);border:1px solid rgba(64,145,108,.3);
  border-radius:20px;font-size:.6rem;font-weight:600;color:var(--g400);
  margin-left:.4rem;vertical-align:middle;
}

.obj-body{padding:.85rem 1.1rem}
.obj-meta{
  display:flex;gap:.7rem;font-size:.71rem;color:#bbb;
  margin-bottom:.6rem;flex-wrap:wrap;
}
.prog-bar{height:4px;background:var(--n100);border-radius:2px;overflow:hidden;margin-bottom:.28rem}
.prog-fill{
  height:100%;border-radius:2px;
  background:linear-gradient(90deg,var(--g500),var(--g300));
  transition:width .6s var(--e);
}
.obj-pct{font-size:.68rem;color:var(--g500);font-weight:600;margin-bottom:.7rem}
.obj-actions{
  display:flex;gap:.38rem;flex-wrap:wrap;
  justify-content:flex-end;
}

/* ── EMPTY ── */
.empty{
  grid-column:1/-1;text-align:center;
  padding:3rem 1.5rem;
  background:var(--white);border:1px solid rgba(0,0,0,.06);
  border-radius:var(--r);box-shadow:var(--sh0);
}
.empty-ico{font-size:2.5rem;margin-bottom:.75rem;opacity:.35;display:block}
.empty p{font-size:.82rem;color:#bbb;line-height:1.7;margin-bottom:1.2rem}

/* ── MODALS ── */
.modal-overlay{
  position:fixed;inset:0;z-index:200;
  background:rgba(0,0,0,.5);
  backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
  display:flex;
  align-items:flex-end; /* mobile: bottom sheet */
  justify-content:center;
  padding:0;
  opacity:0;pointer-events:none;
  transition:opacity var(--d) var(--e);
}
.modal-overlay.open{opacity:1;pointer-events:all}

.modal{
  background:var(--white);
  border:1px solid rgba(0,0,0,.08);
  border-radius:var(--r) var(--r) 0 0;
  width:100%;max-width:100%;
  max-height:92vh;overflow-y:auto;
  box-shadow:0 -8px 40px rgba(0,0,0,.18);
  transform:translateY(100%);
  transition:transform .3s var(--e);
  /* drag handle visual */
  padding-top:.5rem;
}
.modal::before{
  content:'';display:block;
  width:36px;height:4px;border-radius:2px;
  background:rgba(0,0,0,.12);
  margin:0 auto .75rem;
}
.modal-overlay.open .modal{transform:translateY(0)}

.modal-sm{max-width:100%}

.modal-head{
  padding:.9rem 1.2rem;
  border-bottom:1px solid rgba(0,0,0,.06);
  display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;background:var(--white);z-index:1;
}
.modal-title{
  font-family:var(--fd);font-size:.93rem;font-weight:700;color:var(--n800);
}
.modal-x{
  width:30px;height:30px;border-radius:50%;
  background:var(--n100);border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  font-size:.8rem;color:#999;transition:all var(--d) var(--e);
  min-width:30px;min-height:30px;-webkit-tap-highlight-color:transparent;
}
.modal-x:hover{background:var(--red-l);color:var(--red)}

.modal-body{padding:1.1rem 1.2rem}
.modal-foot{
  padding:.85rem 1.2rem;
  border-top:1px solid rgba(0,0,0,.06);
  display:flex;gap:.5rem;justify-content:flex-end;
  background:var(--white);
  position:sticky;bottom:0;
}

/* FORM */
.fg{margin-bottom:.82rem}
.fl{display:block;font-size:.75rem;font-weight:500;color:#666;margin-bottom:.28rem}
.fc{
  width:100%;padding:.62rem .82rem;
  background:var(--n50);border:1px solid rgba(0,0,0,.1);
  border-radius:var(--rs);color:var(--n800);
  font-family:var(--fb);font-size:.9rem; /* maior em mobile para evitar zoom */
  outline:none;transition:all var(--d) var(--e);
  appearance:none;min-height:44px;
}
.fc:focus{border-color:var(--g400);background:var(--white);box-shadow:0 0 0 3px rgba(64,145,108,.1)}
.fc::placeholder{color:#ccc}
.frow{display:grid;grid-template-columns:1fr;gap:.7rem}

.f-alert{
  padding:.55rem .82rem;border-radius:var(--rs);
  font-size:.78rem;margin-bottom:.82rem;display:none;line-height:1.4;
}
.f-alert.show{display:block}
.f-alert.err{background:var(--red-l);border:1px solid rgba(220,38,38,.2);color:var(--red)}
.f-alert.ok{background:var(--g50);border:1px solid rgba(64,145,108,.2);color:var(--g500)}

.del-preview{
  font-family:var(--fd);font-size:.93rem;font-weight:700;color:var(--n800);
  padding:.62rem .88rem;background:var(--n50);border-radius:var(--rs);
  margin-bottom:.82rem;border:1px solid rgba(0,0,0,.07);
  word-break:break-word;
}
.del-warn{font-size:.79rem;color:#888;line-height:1.6}

/* ── TOAST ── */
.toast-wrap{
  position:fixed;bottom:1.1rem;right:1rem;
  z-index:500;display:flex;flex-direction:column;gap:.4rem;
  pointer-events:none;max-width:calc(100vw - 2rem);
}
.toast{
  background:var(--n800);color:#eee;
  padding:.6rem .9rem;border-radius:var(--rs);
  font-size:.77rem;display:flex;align-items:center;gap:.4rem;
  animation:tin .24s var(--e) both;
  max-width:280px;box-shadow:var(--sh3);pointer-events:all;
}
.toast.ok  {border-left:3px solid var(--g400)}
.toast.err {border-left:3px solid #f87171}
.toast.info{border-left:3px solid var(--gold)}
@keyframes tin{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:none}}

/* ─────────────────────────────────────
   TABLET ≥ 640px
───────────────────────────────────── */
@media(min-width:640px){
  .obj-content{padding:1.3rem 1.5rem;gap:1.2rem}
  .page-title{font-size:1.4rem}
  .obj-grid{grid-template-columns:repeat(auto-fill,minmax(280px,1fr))}
  .frow{grid-template-columns:1fr 1fr}

  /* modal vira centered em vez de bottom sheet */
  .modal-overlay{align-items:center;padding:1.2rem}
  .modal{
    border-radius:var(--r);
    max-width:480px;
    transform:translateY(14px) scale(.97);
    padding-top:0;
  }
  .modal::before{display:none}
  .modal-overlay.open .modal{transform:none}
  .modal-sm{max-width:400px}
}

/* ─────────────────────────────────────
   DESKTOP ≥ 769px
───────────────────────────────────── */
@media(min-width:769px){
  .main{margin-left:var(--sw);width:calc(100% - var(--sw))}
  .topbar{padding:0 1.8rem} /* sem offset do hamburger — ele some no desktop */
  .obj-content{padding:1.8rem 2rem;gap:1.3rem}
  .page-title{font-size:1.45rem}
  .obj-grid{grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.1rem}
}

/* ─────────────────────────────────────
   DESKTOP LARGO ≥ 1200px
───────────────────────────────────── */
@media(min-width:1200px){
  .obj-grid{grid-template-columns:repeat(auto-fill,minmax(320px,1fr))}
}
</style>

<script>
const API_BASE = '/florescer/api/';

const TEACH_PERIODS = {
  <?php foreach ($teachTypes as $t): ?>
  <?= (int)$t['id'] ?>: <?= (int)$t['periods'] ?>,
  <?php endforeach; ?>
};

// ── Guard anti-duplicata ──────────────────────────────────────
let _submitting = false;

async function api(endpoint, body) {
  try {
    const r = await fetch(API_BASE + endpoint, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(body)
    });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return await r.json();
  } catch(e) {
    return {success: false, message: 'Erro de conexão: ' + e.message};
  }
}

function toast(msg, type='ok', ms=3400) {
  let w = document.getElementById('toastWrap');
  const d = document.createElement('div');
  d.className = `toast ${type}`;
  d.innerHTML = `<span>${type==='ok'?'✅':type==='err'?'❌':'ℹ️'}</span><span>${msg}</span>`;
  w.appendChild(d);
  setTimeout(()=>{ d.style.opacity='0'; d.style.transition='.3s'; setTimeout(()=>d.remove(),320); }, ms);
}

function toggleSidebar(){
  const sb=document.getElementById('sidebar'),ov=document.getElementById('sbOverlay'),hb=document.getElementById('hamburger');
  if(!sb) return;
  const open=sb.classList.toggle('open');
  if(ov) ov.classList.toggle('show',open);
  if(hb) hb.classList.toggle('open',open);
  document.body.style.overflow=open?'hidden':'';
}

function onTeachChange() {
  const sel    = document.getElementById('mTeach');
  const tid    = parseInt(sel?.value) || 0;
  const per    = TEACH_PERIODS[tid] ?? 4;
  const grp    = document.getElementById('grpUnitCount');
  const inp    = document.getElementById('mUnitCount');
  const isEdit = !!document.getElementById('mId')?.value;

  if (isEdit) {
    grp.style.display = '';
    if (per > 0) {
      inp.value = per; inp.readOnly = true; inp.style.opacity = '.55';
      inp.title = 'Definido automaticamente pelo tipo de ensino';
    } else {
      inp.readOnly = false; inp.style.opacity = '1'; inp.title = '';
      if (!inp.value || inp.value === '0') inp.value = '4';
    }
    return;
  }
  if (per === 0) {
    grp.style.display = ''; inp.readOnly = false; inp.style.opacity = '1';
    if (!inp.value || inp.value === '0') inp.value = '4';
  } else {
    grp.style.display = 'none'; inp.value = per;
  }
}

function onTypeChange() {
  const isSchool = document.getElementById('mType').value === '1';
  document.getElementById('grpGrade').style.display = isSchool ? '' : 'none';
  onTeachChange();
}

function openModal(id=null, name='', avg='', unitCount=4) {
  if (_submitting) return;
  const isEdit = id !== null;
  document.getElementById('modalTitle').textContent = isEdit ? 'Editar objetivo' : 'Novo objetivo';
  document.getElementById('mId').value        = id ?? '';
  document.getElementById('mName').value      = name;
  document.getElementById('mAvg').value       = avg;
  document.getElementById('mUnitCount').value = unitCount || 4;
  document.getElementById('mAlert').className  = 'f-alert';
  document.getElementById('grpType').style.display  = isEdit ? 'none' : '';
  document.getElementById('grpTeach').style.display = isEdit ? 'none' : '';
  document.getElementById('grpGrade').style.display = 'none';

  const btn = document.getElementById('btnSave');
  btn.disabled = false; btn.textContent = 'Salvar objetivo';

  if (isEdit) {
    document.getElementById('grpUnitCount').style.display = '';
    const inp = document.getElementById('mUnitCount');
    inp.readOnly = false; inp.style.opacity = '1';
  } else {
    document.getElementById('mType').value  = '';
    if (document.getElementById('mGrade')) document.getElementById('mGrade').value = '';
    onTeachChange();
  }
  document.getElementById('modalOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
  setTimeout(() => document.getElementById('mName').focus(), 150);
}

function closeModal() {
  if (_submitting) return;
  document.getElementById('modalOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

function setAlert(msg) {
  const el = document.getElementById('mAlert');
  el.textContent = msg; el.className = 'f-alert err show';
}

async function submitObj() {
  // Duplo clique / dupla submissão: bloqueia
  if (_submitting) return;

  const id        = document.getElementById('mId').value;
  const name      = document.getElementById('mName').value.trim();
  const avg       = document.getElementById('mAvg').value;
  const unitCount = parseInt(document.getElementById('mUnitCount').value) || 4;
  const isEdit    = !!id;

  if (!name) { setAlert('Informe o nome do objetivo.'); return; }
  if (unitCount < 1 || unitCount > 12) { setAlert('A quantidade de unidades deve ser entre 1 e 12.'); return; }

  _submitting = true;
  const btn = document.getElementById('btnSave');
  btn.disabled = true; btn.textContent = 'Salvando…';

  let payload;
  if (isEdit) {
    payload = {
      action:      'update',
      id:          +id,
      name,
      default_avg: avg !== '' ? +avg : null,
      unit_count:  unitCount,
    };
  } else {
    const typeId  = document.getElementById('mType').value;
    const teachId = document.getElementById('mTeach').value;
    const grade   = document.getElementById('mGrade')?.value ?? '';
    if (!typeId) {
      setAlert('Selecione o tipo de objetivo.');
      btn.disabled = false; btn.textContent = 'Salvar objetivo';
      _submitting = false; return;
    }
    const per     = TEACH_PERIODS[parseInt(teachId)] ?? 0;
    const finalUC = per > 0 ? per : unitCount;
    // Token único por submissão para evitar duplicatas no servidor
    const token = Date.now().toString(36) + Math.random().toString(36).slice(2);
    payload = {
      action:              'create',
      objective_type_id:   +typeId,
      teaching_type_id:    +teachId || null,
      name,
      grade_level:         grade || null,
      default_avg:         avg !== '' ? +avg : null,
      unit_count:          finalUC,
      idempotency_token:   token,
    };
  }

  const r = await api('objectives.php', payload);
  _submitting = false;
  btn.disabled = false; btn.textContent = 'Salvar objetivo';

  if (r.success) {
    toast(isEdit ? 'Objetivo atualizado! ✅' : 'Objetivo criado! 🎯');
    closeModal();
    setTimeout(() => location.reload(), 600);
  } else {
    setAlert(r.message || 'Erro ao salvar objetivo.');
  }
}

async function goToMaterials(id) {
  await api('set_objetive.php', {objective_id: id});
  window.location.href = '/florescer/public/views/materials.php';
}

async function activateObj(id, name) {
  const r = await api('objectives.php', {action:'activate', id});
  if (r.success) { toast(`"${name}" ativado!`); setTimeout(() => location.reload(), 500); }
  else toast(r.message || 'Erro ao ativar.', 'err');
}

let pendingDeleteId = null;

function deleteObj(id, name) {
  pendingDeleteId = id;
  document.getElementById('delPreview').textContent = name;
  document.getElementById('delOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeDelModal() {
  pendingDeleteId = null;
  document.getElementById('delOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

async function confirmDelete() {
  if (!pendingDeleteId) return;
  const btn = document.getElementById('btnConfirmDel');
  btn.disabled = true; btn.textContent = 'Excluindo…';
  const r = await api('objectives.php', {action:'delete', id: pendingDeleteId});
  if (r.success) {
    toast('Objetivo excluído.');
    closeDelModal();
    const card = document.getElementById(`card-${pendingDeleteId}`);
    if (card) {
      card.style.opacity='0'; card.style.transform='scale(.96)';
      card.style.transition='opacity .28s, transform .28s';
      setTimeout(() => {
        card.remove();
        const grid = document.getElementById('objGrid');
        if (grid && !grid.querySelector('.obj-card')) {
          grid.innerHTML=`<div class="empty"><span class="empty-ico">🎯</span><p>Nenhum objetivo ainda.</p><button class="btn-primary" onclick="openModal()">+ Criar objetivo</button></div>`;
        }
      }, 300);
    }
  } else {
    toast(r.message || 'Erro ao excluir objetivo.', 'err');
    btn.disabled = false; btn.textContent = 'Excluir permanentemente';
  }
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closeModal(); closeDelModal(); }
});
</script>
</body>
</html>