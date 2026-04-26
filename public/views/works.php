<?php
// ============================================================
// /public/views/works.php — florescer v2.0
// Meus Trabalhos — CRUD completo sem global.js
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
$currentPage = 'works';

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
        $r2  = $mx<PHP_INT_MAX ? $mx-$mn+1 : 1;
        $plant = ['emoji'=>$em,'name'=>$nm,'pct'=>$mx<PHP_INT_MAX?min(100,round(($streak-$mn)/$r2*100)):100];
        break;
    }
}
if (!isset($_SESSION['active_objective'])) {
    $ao = dbRow('SELECT id FROM objectives WHERE user_id=? AND is_active=1 ORDER BY created_at DESC LIMIT 1', [$userId]);
    if (!$ao) $ao = dbRow('SELECT id FROM objectives WHERE user_id=? ORDER BY created_at DESC LIMIT 1', [$userId]);
    $_SESSION['active_objective'] = $ao['id'] ?? null;
}
$activeObjId = $_SESSION['active_objective'];
$allObjs = dbQuery('SELECT id,name FROM objectives WHERE user_id=? ORDER BY is_active DESC,created_at DESC', [$userId]);

// ── Matérias do objetivo ativo ────────────────────────────────
$subjects = [];
if ($activeObjId) {
    $subjects = dbQuery(
        'SELECT id, name, color FROM subjects WHERE objective_id=? AND is_active=1 ORDER BY name ASC',
        [$activeObjId]
    );
}

// ── Trabalhos (com auto-marcar atrasados) ─────────────────────
$today = date('Y-m-d');

// Auto-marca atrasados
dbExec(
    "UPDATE works SET status='atrasado'
     WHERE user_id=? AND status NOT IN ('entregue','atrasado')
     AND due_date IS NOT NULL AND due_date < ?",
    [$userId, $today]
);

$works = dbQuery(
    "SELECT w.id, w.subject_id, w.title, w.description,
            w.unit, w.due_date, w.status, w.created_at,
            s.name AS subject_name, s.color AS subject_color
     FROM works w
     LEFT JOIN subjects s ON s.id=w.subject_id
     WHERE w.user_id=?
     ORDER BY
       CASE w.status
         WHEN 'atrasado'     THEN 1
         WHEN 'pendente'     THEN 2
         WHEN 'em_andamento' THEN 3
         WHEN 'entregue'     THEN 4
         ELSE 5
       END,
       w.due_date ASC, w.created_at DESC",
    [$userId]
);

// Stats
$stats = [
    'total'        => count($works),
    'pendente'     => 0,
    'em_andamento' => 0,
    'entregue'     => 0,
    'atrasado'     => 0,
];
foreach ($works as $w) {
    if (isset($stats[$w['status']])) $stats[$w['status']]++;
}

$STATUS_LABELS = [
    'pendente'     => ['📋', 'Pendente',     '#6b7280','rgba(107,114,128,.1)'],
    'em_andamento' => ['⚡', 'Em andamento', '#2563eb','rgba(37,99,235,.1)'],
    'entregue'     => ['✅', 'Entregue',     '#16a34a','rgba(22,163,74,.1)'],
    'atrasado'     => ['🔴', 'Atrasado',     '#dc2626','rgba(220,38,38,.1)'],
];

// Unidades disponíveis (configurável)
$UNITS = ['UND1','UND2','UND3','UND4'];

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
  <title>florescer — Trabalhos</title>
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
      <span class="tb-title">💼 Meus Trabalhos</span>
    </div>
    <div class="xp-pill">⭐ <?= number_format($xp) ?> XP</div>
  </header>

  <main class="works-content">

    <!-- Cabeçalho -->
    <div class="page-head">
      <div>
        <div class="page-title">Meus Trabalhos</div>
        <div class="page-sub">Organize suas tarefas e trabalhos escolares</div>
      </div>
      <button class="btn-primary" onclick="openCreate()">+ Novo trabalho</button>
    </div>

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat-chip atrasado">
        🔴 <strong><?= $stats['atrasado'] ?></strong> atrasado<?= $stats['atrasado']!=1?'s':'' ?>
      </div>
      <div class="stat-chip pendente">
        📋 <strong><?= $stats['pendente'] ?></strong> pendente<?= $stats['pendente']!=1?'s':'' ?>
      </div>
      <div class="stat-chip em_andamento">
        ⚡ <strong><?= $stats['em_andamento'] ?></strong> em andamento
      </div>
      <div class="stat-chip entregue">
        ✅ <strong><?= $stats['entregue'] ?></strong> entregue<?= $stats['entregue']!=1?'s':'' ?>
      </div>
    </div>

    <!-- Sem matérias aviso -->
    <?php if (empty($subjects)): ?>
    <div class="info-box">
      ℹ️ Você não tem matérias no objetivo ativo.
      <a href="materials.php">Adicione matérias primeiro →</a>
    </div>
    <?php endif; ?>

    <!-- Lista de trabalhos -->
    <?php if (empty($works)): ?>
    <div class="empty-state">
      <div class="empty-ico">💼</div>
      <p>Nenhum trabalho ainda.<br>Clique em "+ Novo trabalho" para começar.</p>
    </div>
    <?php else: ?>
    <div class="works-list" id="worksList">
      <?php foreach ($works as $w):
        [$icon,$label,$color,$bg] = $STATUS_LABELS[$w['status']] ?? ['📋','Pendente','#6b7280','rgba(107,114,128,.1)'];
        $subColor = $w['subject_color'] ?: '#40916c';
        $isOverdue = $w['due_date'] && $w['due_date'] < $today && $w['status'] !== 'entregue';
        $dueFormatted = $w['due_date'] ? date('d/m/Y', strtotime($w['due_date'])) : null;
        $jsData = json_encode([
          'id'          => (int)$w['id'],
          'subject_id'  => (int)$w['subject_id'],
          'title'       => $w['title'],
          'description' => $w['description'] ?? '',
          'unit'        => $w['unit'] ?? '',
          'due_date'    => $w['due_date'] ?? '',
          'status'      => $w['status'],
        ], JSON_UNESCAPED_UNICODE);
      ?>
      <div class="work-card <?= $w['status'] === 'entregue' ? 'done' : '' ?>"
           id="wcard-<?= $w['id'] ?>">
        <!-- Faixa lateral da matéria -->
        <div class="work-accent" style="background:<?= htmlspecialchars($subColor,ENT_QUOTES) ?>"></div>

        <div class="work-body">
          <!-- Cabeçalho do card -->
          <div class="work-top">
            <div class="work-info">
              <?php if ($w['subject_name']): ?>
                <span class="work-subject"
                      style="color:<?= htmlspecialchars($subColor,ENT_QUOTES) ?>;
                             background:<?= htmlspecialchars($subColor,ENT_QUOTES) ?>18">
                  <?= htmlspecialchars($w['subject_name'],ENT_QUOTES) ?>
                  <?php if ($w['unit']): ?>
                    · <?= htmlspecialchars($w['unit'],ENT_QUOTES) ?>
                  <?php endif; ?>
                </span>
              <?php endif; ?>
              <h3 class="work-title <?= $w['status']==='entregue'?'striked':'' ?>">
                <?= htmlspecialchars($w['title'],ENT_QUOTES) ?>
              </h3>
              <?php if ($w['description']): ?>
                <p class="work-desc"><?= htmlspecialchars(mb_substr($w['description'],0,120,'UTF-8'),ENT_QUOTES) ?><?= mb_strlen($w['description'],'UTF-8')>120?'…':'' ?></p>
              <?php endif; ?>
            </div>
            <div class="work-meta-col">
              <span class="status-badge" style="color:<?= $color ?>;background:<?= $bg ?>">
                <?= $icon ?> <?= $label ?>
              </span>
              <?php if ($dueFormatted): ?>
                <span class="due-date <?= $isOverdue?'overdue':'' ?>">
                  📅 <?= $dueFormatted ?>
                </span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Ações -->
          <div class="work-actions">
            <!-- Botões de status rápido -->
            <?php if ($w['status'] !== 'entregue'): ?>
              <button class="btn-status" onclick="quickStatus(<?= $w['id'] ?>,'entregue')">
                ✅ Marcar entregue
              </button>
            <?php else: ?>
              <button class="btn-status reopen" onclick="quickStatus(<?= $w['id'] ?>,'pendente')">
                ↩ Reabrir
              </button>
            <?php endif; ?>
            <?php if ($w['status'] === 'pendente'): ?>
              <button class="btn-status start" onclick="quickStatus(<?= $w['id'] ?>,'em_andamento')">
                ⚡ Iniciar
              </button>
            <?php endif; ?>
            <div class="work-actions-right">
              <button class="btn-icon-edit" onclick='openEdit(<?= htmlspecialchars($jsData,ENT_QUOTES) ?>)' title="Editar">✏️</button>
              <button class="btn-icon-del"  onclick="deleteWork(<?= $w['id'] ?>,'<?= htmlspecialchars(addslashes($w['title']),ENT_QUOTES) ?>')" title="Excluir">🗑</button>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </main>
</div>

<!-- ══ MODAL CRIAR / EDITAR ══ -->
<div class="modal-overlay" id="modalOverlay" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="modal-head">
      <span class="modal-title" id="modalTitle">Novo trabalho</span>
      <button class="modal-x" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body">
      <div class="f-alert" id="fAlert"></div>
      <input type="hidden" id="wId"/>

      <div class="fg">
        <label class="fl">Título *</label>
        <input class="fc" id="wTitle" type="text" placeholder="Ex: Trabalho de História — Revolução Francesa" maxlength="200"/>
      </div>

      <div class="frow">
        <div class="fg">
          <label class="fl">Matéria *</label>
          <select class="fc" id="wSubject">
            <option value="">Selecione...</option>
            <?php foreach ($subjects as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name'],ENT_QUOTES) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg">
          <label class="fl">Unidade</label>
          <select class="fc" id="wUnit">
            <option value="">Nenhuma</option>
            <?php foreach ($UNITS as $u): ?>
              <option value="<?= $u ?>"><?= $u ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="frow">
        <div class="fg">
          <label class="fl">Data de entrega</label>
          <input class="fc" id="wDue" type="date"/>
        </div>
        <div class="fg">
          <label class="fl">Status</label>
          <select class="fc" id="wStatus">
            <option value="pendente">📋 Pendente</option>
            <option value="em_andamento">⚡ Em andamento</option>
            <option value="entregue">✅ Entregue</option>
          </select>
        </div>
      </div>

      <div class="fg">
        <label class="fl">Descrição / Observações</label>
        <textarea class="fc" id="wDesc" rows="3"
                  placeholder="Detalhes do trabalho, critérios de avaliação..." maxlength="2000"></textarea>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-ghost" onclick="closeModal()">Cancelar</button>
      <button class="btn-primary" id="btnSave" onclick="submitWork()">Salvar</button>
    </div>
  </div>
</div>

<!-- ══ MODAL EXCLUIR ══ -->
<div class="modal-overlay modal-sm-overlay" id="delOverlay" onclick="if(event.target===this)closeDelModal()">
  <div class="modal modal-sm">
    <div class="modal-head">
      <span class="modal-title">🗑 Excluir trabalho</span>
      <button class="modal-x" onclick="closeDelModal()">✕</button>
    </div>
    <div class="modal-body">
      <div class="del-name" id="delName"></div>
      <p class="del-warn">Esta ação é irreversível.</p>
    </div>
    <div class="modal-foot">
      <button class="btn-ghost" onclick="closeDelModal()">Cancelar</button>
      <button class="btn-danger-solid" id="btnDel" onclick="confirmDel()">Excluir</button>
    </div>
  </div>
</div>

<style>
/* ── Conteúdo ─────────────────────────────────────────────── */
.works-content{flex:1;padding:1.8rem 2rem;display:flex;flex-direction:column;gap:1.2rem}
.page-head{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:.8rem}
.page-title{font-family:var(--fd);font-size:1.45rem;font-weight:900;color:var(--n800);letter-spacing:-.03em}
.page-sub{font-size:.8rem;color:#aaa;margin-top:.2rem}

/* Stats */
.stats-row{display:flex;gap:.6rem;flex-wrap:wrap}
.stat-chip{font-size:.78rem;padding:.35rem .85rem;border-radius:50px;font-weight:500;
            display:flex;align-items:center;gap:.35rem}
.stat-chip.atrasado    {background:rgba(220,38,38,.08); color:#dc2626;border:1px solid rgba(220,38,38,.18)}
.stat-chip.pendente    {background:rgba(107,114,128,.08);color:#4b5563;border:1px solid rgba(107,114,128,.18)}
.stat-chip.em_andamento{background:rgba(37,99,235,.08); color:#2563eb;border:1px solid rgba(37,99,235,.18)}
.stat-chip.entregue    {background:rgba(22,163,74,.08);  color:#16a34a;border:1px solid rgba(22,163,74,.18)}

/* Info box */
.info-box{background:rgba(37,99,235,.06);border:1px solid rgba(37,99,235,.15);
           border-radius:var(--rs);padding:.72rem 1rem;font-size:.8rem;color:#2563eb}
.info-box a{color:inherit;font-weight:600}

/* Empty */
.empty-state{text-align:center;padding:3.5rem;background:var(--white);
              border:1px solid rgba(0,0,0,.06);border-radius:var(--r);box-shadow:var(--sh0)}
.empty-ico{font-size:2.5rem;margin-bottom:.7rem;opacity:.35}
.empty-state p{font-size:.84rem;color:#bbb;line-height:1.7}

/* Lista */
.works-list{display:flex;flex-direction:column;gap:.7rem}

/* Card */
.work-card{background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r);
            display:flex;overflow:hidden;box-shadow:var(--sh0);
            transition:transform var(--d) var(--e),box-shadow var(--d) var(--e)}
.work-card:hover{transform:translateY(-1px);box-shadow:var(--sh1)}
.work-card.done{opacity:.6}
.work-card.done:hover{opacity:.85}
.work-accent{width:4px;flex-shrink:0}
.work-body{flex:1;padding:.9rem 1.1rem;display:flex;flex-direction:column;gap:.6rem;min-width:0}

.work-top{display:flex;gap:1rem;align-items:flex-start;justify-content:space-between}
.work-info{flex:1;min-width:0}
.work-subject{display:inline-block;font-size:.67rem;font-weight:600;padding:.12rem .5rem;
               border-radius:20px;margin-bottom:.3rem;letter-spacing:.03em}
.work-title{font-family:var(--fd);font-size:.95rem;font-weight:700;color:var(--n800);
             letter-spacing:-.015em;margin-bottom:.18rem}
.work-title.striked{text-decoration:line-through;opacity:.6}
.work-desc{font-size:.76rem;color:#aaa;line-height:1.5}

.work-meta-col{display:flex;flex-direction:column;align-items:flex-end;gap:.3rem;flex-shrink:0}
.status-badge{font-size:.72rem;font-weight:600;padding:.18rem .58rem;
               border-radius:20px;white-space:nowrap}
.due-date{font-size:.7rem;color:#aaa;white-space:nowrap}
.due-date.overdue{color:var(--red);font-weight:600}

/* Ações do card */
.work-actions{display:flex;align-items:center;gap:.4rem;flex-wrap:wrap}
.work-actions-right{margin-left:auto;display:flex;gap:.3rem}
.btn-status{padding:.3rem .7rem;border-radius:50px;border:1px solid rgba(22,163,74,.2);
             background:rgba(22,163,74,.06);color:#16a34a;font-family:var(--fb);
             font-size:.72rem;font-weight:500;cursor:pointer;
             transition:all var(--d) var(--e)}
.btn-status:hover{background:rgba(22,163,74,.14)}
.btn-status.start{border-color:rgba(37,99,235,.2);background:rgba(37,99,235,.06);color:#2563eb}
.btn-status.start:hover{background:rgba(37,99,235,.14)}
.btn-status.reopen{border-color:rgba(107,114,128,.2);background:rgba(107,114,128,.06);color:#6b7280}
.btn-status.reopen:hover{background:rgba(107,114,128,.14)}
.btn-icon-edit,.btn-icon-del{width:28px;height:28px;border-radius:50%;border:1px solid rgba(0,0,0,.08);
  background:none;cursor:pointer;font-size:.78rem;display:flex;align-items:center;
  justify-content:center;transition:all var(--d) var(--e)}
.btn-icon-edit:hover{background:var(--n100);border-color:rgba(0,0,0,.14)}
.btn-icon-del:hover{background:var(--red-l);border-color:rgba(220,38,38,.2)}

/* Botões gerais */
.btn-primary{display:inline-flex;align-items:center;gap:.38rem;padding:.55rem 1.15rem;
              background:linear-gradient(135deg,var(--g500),var(--g600));color:#fff;
              border:none;border-radius:50px;font-family:var(--fb);font-size:.83rem;
              font-weight:600;cursor:pointer;transition:all var(--d) var(--e);
              box-shadow:0 3px 12px rgba(64,145,108,.28)}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 5px 18px rgba(64,145,108,.38)}
.btn-primary:disabled{opacity:.5;cursor:not-allowed;transform:none}
.btn-ghost{padding:.5rem 1.05rem;border-radius:var(--rs);background:transparent;
            border:1px solid rgba(0,0,0,.1);font-family:var(--fb);font-size:.79rem;
            font-weight:500;color:#888;cursor:pointer;transition:all var(--d) var(--e)}
.btn-ghost:hover{background:var(--n100);color:var(--n800)}
.btn-danger-solid{padding:.5rem 1.05rem;background:var(--red);border:none;
                   border-radius:var(--rs);color:#fff;font-family:var(--fb);
                   font-size:.79rem;font-weight:600;cursor:pointer;
                   transition:all var(--d) var(--e)}
.btn-danger-solid:hover{background:#b91c1c}
.btn-danger-solid:disabled{opacity:.5;cursor:not-allowed}

/* Modal */
.modal-overlay,.modal-sm-overlay{position:fixed;inset:0;z-index:200;
  background:rgba(0,0,0,.45);backdrop-filter:blur(8px);
  display:flex;align-items:center;justify-content:center;
  padding:1.2rem;opacity:0;pointer-events:none;transition:opacity var(--d) var(--e)}
.modal-overlay.open,.modal-sm-overlay.open{opacity:1;pointer-events:all}
.modal{background:var(--white);border:1px solid rgba(0,0,0,.08);border-radius:var(--r);
        width:100%;max-width:520px;max-height:90vh;overflow-y:auto;box-shadow:var(--sh3);
        transform:translateY(14px) scale(.97);transition:transform var(--d) var(--e)}
.modal-overlay.open .modal,.modal-sm-overlay.open .modal{transform:translateY(0) scale(1)}
.modal-sm{max-width:400px}
.modal-head{padding:1rem 1.3rem;border-bottom:1px solid rgba(0,0,0,.06);
             display:flex;align-items:center;justify-content:space-between;
             position:sticky;top:0;background:var(--white);z-index:1}
.modal-title{font-family:var(--fd);font-size:.95rem;font-weight:700;color:var(--n800)}
.modal-x{width:28px;height:28px;border-radius:50%;background:var(--n100);border:none;
           cursor:pointer;display:flex;align-items:center;justify-content:center;
           font-size:.8rem;color:#999;transition:all var(--d) var(--e)}
.modal-x:hover{background:var(--red-l);color:var(--red)}
.modal-body{padding:1.3rem}
.modal-foot{padding:.9rem 1.3rem;border-top:1px solid rgba(0,0,0,.06);
             display:flex;gap:.5rem;justify-content:flex-end;background:var(--white)}

/* Form */
.fg{margin-bottom:.85rem}
.fl{display:block;font-size:.76rem;font-weight:500;color:#666;margin-bottom:.3rem}
.fc{width:100%;padding:.58rem .82rem;background:var(--n50);
     border:1px solid rgba(0,0,0,.1);border-radius:var(--rs);
     color:var(--n800);font-family:var(--fb);font-size:.84rem;
     outline:none;transition:all var(--d) var(--e);appearance:none}
.fc:focus{border-color:var(--g400);background:var(--white);
           box-shadow:0 0 0 3px rgba(64,145,108,.1)}
.fc::placeholder{color:#ccc}
textarea.fc{resize:vertical;min-height:75px;line-height:1.55}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
.f-alert{padding:.58rem .82rem;border-radius:var(--rs);font-size:.79rem;
          margin-bottom:.85rem;display:none;line-height:1.4}
.f-alert.show{display:block}
.f-alert.err{background:var(--red-l);border:1px solid rgba(220,38,38,.2);color:var(--red)}
.del-name{font-family:var(--fd);font-size:.93rem;font-weight:700;color:var(--n800);
           padding:.6rem .85rem;background:var(--n50);border-radius:var(--rs);
           margin-bottom:.75rem;border:1px solid rgba(0,0,0,.07)}
.del-warn{font-size:.8rem;color:#888;line-height:1.5}

/* Toast */
.toast-wrap{position:fixed;bottom:1.4rem;right:1.4rem;z-index:500;
             display:flex;flex-direction:column;gap:.4rem;pointer-events:none}
.toast{background:var(--n800);color:#eee;padding:.6rem .95rem;border-radius:var(--rs);
        font-size:.78rem;display:flex;align-items:center;gap:.4rem;
        animation:tin .22s var(--e) both;max-width:280px;
        box-shadow:var(--sh3);pointer-events:all}
.toast.ok {border-left:3px solid var(--g400)}
.toast.err{border-left:3px solid #f87171}
@keyframes tin{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}

@media(max-width:640px){.works-content{padding:1.2rem 1rem}.frow{grid-template-columns:1fr}
  .work-top{flex-direction:column}.work-meta-col{align-items:flex-start}}
</style>

<div class="toast-wrap" id="toastWrap"></div>

<script>
const API = '/florescer/api/works.php';

/* ── Utils ───────────────────────────────────────────────── */
function toast(msg, type='ok', ms=3400) {
  const w = document.getElementById('toastWrap');
  const d = document.createElement('div');
  d.className = `toast ${type}`;
  d.innerHTML = `<span>${type==='ok'?'✅':'❌'}</span><span>${msg}</span>`;
  w.appendChild(d);
  setTimeout(() => { d.style.opacity='0'; d.style.transition='.3s'; setTimeout(()=>d.remove(),320); }, ms);
}

async function apiCall(body) {
  try {
    const r = await fetch(API, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(body)
    });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return await r.json();
  } catch(e) {
    return { success: false, message: 'Erro de conexão: ' + e.message };
  }
}

function setAlert(msg) {
  const el = document.getElementById('fAlert');
  el.textContent = msg; el.className = 'f-alert err show';
}
function clearAlert() {
  document.getElementById('fAlert').className = 'f-alert';
}

/* ── Modal criar/editar ──────────────────────────────────── */
function openCreate() {
  document.getElementById('wId').value      = '';
  document.getElementById('wTitle').value   = '';
  document.getElementById('wSubject').value = '';
  document.getElementById('wUnit').value    = '';
  document.getElementById('wDue').value     = '';
  document.getElementById('wStatus').value  = 'pendente';
  document.getElementById('wDesc').value    = '';
  document.getElementById('modalTitle').textContent = 'Novo trabalho';
  document.getElementById('btnSave').textContent    = 'Salvar';
  clearAlert();
  openModal();
}

function openEdit(data) {
  document.getElementById('wId').value      = data.id;
  document.getElementById('wTitle').value   = data.title       || '';
  document.getElementById('wSubject').value = data.subject_id  || '';
  document.getElementById('wUnit').value    = data.unit        || '';
  document.getElementById('wDue').value     = data.due_date    || '';
  document.getElementById('wStatus').value  = data.status      || 'pendente';
  document.getElementById('wDesc').value    = data.description || '';
  document.getElementById('modalTitle').textContent = 'Editar trabalho';
  document.getElementById('btnSave').textContent    = 'Salvar';
  clearAlert();
  openModal();
}

function openModal() {
  document.getElementById('modalOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
  setTimeout(() => document.getElementById('wTitle').focus(), 150);
}
function closeModal() {
  document.getElementById('modalOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

/* ── Submit ──────────────────────────────────────────────── */
async function submitWork() {
  const id      = document.getElementById('wId').value;
  const title   = document.getElementById('wTitle').value.trim();
  const subject = document.getElementById('wSubject').value;
  const unit    = document.getElementById('wUnit').value;
  const due     = document.getElementById('wDue').value;
  const status  = document.getElementById('wStatus').value;
  const desc    = document.getElementById('wDesc').value.trim();

  if (!title)   { setAlert('Informe o título do trabalho.'); return; }
  if (!subject) { setAlert('Selecione a matéria.'); return; }

  const btn = document.getElementById('btnSave');
  btn.disabled = true; btn.textContent = 'Salvando…';

  const body = {
    action:      id ? 'update' : 'create',
    title,
    subject_id:  +subject,
    unit:        unit   || null,
    due_date:    due    || null,
    status,
    description: desc   || null,
  };
  if (id) body.id = +id;

  const r = await apiCall(body);
  btn.disabled = false; btn.textContent = 'Salvar';

  if (r.success) {
    toast(id ? 'Trabalho atualizado! ✅' : 'Trabalho criado! 💼');
    closeModal();
    setTimeout(() => location.reload(), 500);
  } else {
    setAlert(r.message || 'Erro ao salvar.');
  }
}

/* ── Status rápido ───────────────────────────────────────── */
async function quickStatus(id, status) {
  const r = await apiCall({ action: 'update_status', id, status });
  if (r.success) {
    toast(status === 'entregue' ? '✅ Marcado como entregue!' :
          status === 'em_andamento' ? '⚡ Em andamento!' : '↩ Reaberto!');
    setTimeout(() => location.reload(), 400);
  } else {
    toast(r.message || 'Erro.', 'err');
  }
}

/* ── Excluir ─────────────────────────────────────────────── */
let pendingDelId = null;

function deleteWork(id, title) {
  pendingDelId = id;
  document.getElementById('delName').textContent = title;
  document.getElementById('delOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeDelModal() {
  pendingDelId = null;
  document.getElementById('delOverlay').classList.remove('open');
  document.body.style.overflow = '';
}
async function confirmDel() {
  if (!pendingDelId) return;
  const btn = document.getElementById('btnDel');
  btn.disabled = true; btn.textContent = 'Excluindo…';

  const r = await apiCall({ action: 'delete', id: pendingDelId });
  if (r.success) {
    toast('Trabalho excluído.');
    closeDelModal();
    const card = document.getElementById('wcard-' + pendingDelId);
    if (card) {
      card.style.opacity = '0'; card.style.transition = '.28s';
      setTimeout(() => { card.remove(); }, 300);
    }
  } else {
    toast(r.message || 'Erro ao excluir.', 'err');
    btn.disabled = false; btn.textContent = 'Excluir';
  }
}

/* ESC fecha modais */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closeModal(); closeDelModal(); }
});
</script>
</body>
</html>