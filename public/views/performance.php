<?php
// ============================================================
// public/views/performance.php — florescer v2.4
// NOVO: avaliações personalizadas, faixas dinâmicas, sem padrão
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
$currentPage = 'performance';

// ── Sidebar vars ──────────────────────────────────────────────
$ud     = dbRow('SELECT xp, level, streak FROM users WHERE id=?', [$userId]);
$xp     = (int)($ud['xp']     ?? 0);
$level  = (int)($ud['level']  ?? 1);
$streak = (int)($ud['streak'] ?? 0);
$lvN    = ['','Semente','Broto','Estudante','Aplicado','Dedicado','Avançado','Expert','Mestre','Lendário'];
$lvName = $lvN[min($level,count($lvN)-1)] ?? 'Lendário';
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
    $ao=dbRow('SELECT id FROM objectives WHERE user_id=? AND is_active=1 ORDER BY created_at DESC LIMIT 1',[$userId]);
    if (!$ao) $ao=dbRow('SELECT id FROM objectives WHERE user_id=? ORDER BY created_at DESC LIMIT 1',[$userId]);
    $_SESSION['active_objective']=$ao['id']??null;
}
$activeObjId=$_SESSION['active_objective'];
$allObjs=dbQuery('SELECT id,name FROM objectives WHERE user_id=? ORDER BY is_active DESC,created_at DESC',[$userId]);

// ── Objetivo ativo ────────────────────────────────────────────
$activeObj = dbRow(
    'SELECT o.id, o.name, o.default_avg, o.unit_count,
            tt.name AS teach_name, tt.periods AS tt_periods
     FROM objectives o
     LEFT JOIN teaching_types tt ON tt.id = o.teaching_type_id
     WHERE o.user_id=? AND o.is_active=1
     ORDER BY o.created_at DESC LIMIT 1',
    [$userId]
) ?? dbRow(
    'SELECT o.id, o.name, o.default_avg, o.unit_count,
            tt.name AS teach_name, tt.periods AS tt_periods
     FROM objectives o
     LEFT JOIN teaching_types tt ON tt.id = o.teaching_type_id
     WHERE o.user_id=?
     ORDER BY o.created_at DESC LIMIT 1',
    [$userId]
);

// Média mínima definida no objetivo (padrão 7.0)
$avgMin = (float)($activeObj['default_avg'] ?? 7.0);

// ── Faixas de cores dinâmicas baseadas em $avgMin ────────────
// Acima:    > avgMin + 1 (pelo menos 1 ponto acima da média)  → roxo
// Na média: >= avgMin                                          → verde
// Próximo:  >= avgMin - 2                                      → amarelo
// Em risco: < avgMin - 2                                       → vermelho
$threshAbove = $avgMin + 1;       // > isso → roxo
$threshOk    = $avgMin;           // >= isso → verde
$threshWarn  = max(0, $avgMin - 2); // >= isso → amarelo, < isso → vermelho

// ── Unidades ──────────────────────────────────────────────────
$unitCount = getUnitCount($activeObj);
$units     = buildUnitSlugs($unitCount);
$ttPeriods = (int)($activeObj['tt_periods'] ?? 0);
$unitLabel = match($ttPeriods) {
    4 => 'Bimestral (4 unidades)',
    3 => 'Trimestral (3 unidades)',
    2 => 'Semestral (2 unidades)',
    default => $unitCount . ($unitCount === 1 ? ' unidade' : ' unidades'),
};

// ── Avaliações personalizadas do objetivo ────────────────────
// Busca da tabela objective_assessments. Se não existir ainda,
// cria a tabela na hora (garante funcionamento mesmo sem migration).
$assessments = [];
if ($activeObj) {
    $tableExists = dbRow("SHOW TABLES LIKE 'objective_assessments'");
    if (!$tableExists) {
        dbQuery("CREATE TABLE IF NOT EXISTS `objective_assessments` (
            `id`           int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `objective_id` int(10) UNSIGNED NOT NULL,
            `name`         varchar(12) NOT NULL,
            `slug`         varchar(12) NOT NULL,
            `sort_order`   tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
            `created_at`   datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_obj_slug` (`objective_id`, `slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", []);
    }
    $assessments = dbQuery(
        'SELECT id, name, slug FROM objective_assessments
         WHERE objective_id = ? ORDER BY sort_order ASC, id ASC',
        [$activeObj['id']]
    );
}

// Slugs das avaliações (para indexar grade_sub_scores.score_type)
$assessSlugs = array_column($assessments, 'slug');

// ── Matérias ──────────────────────────────────────────────────
$subjects    = [];
$subScoreMap = []; // [subject_id][unit][slug] = float

if ($activeObj) {
    $subjects = dbQuery(
        'SELECT id, name, color FROM subjects
         WHERE objective_id = ? AND is_active = 1 ORDER BY name ASC',
        [$activeObj['id']]
    );

    if (!empty($assessSlugs) && dbRow("SHOW TABLES LIKE 'grade_sub_scores'")) {
        $ssRows = dbQuery(
            'SELECT g.subject_id, g.unit, g.score_type, g.score
             FROM grade_sub_scores g
             JOIN subjects s ON s.id = g.subject_id
             WHERE s.objective_id = ? AND s.is_active = 1',
            [$activeObj['id']]
        );
        foreach ($ssRows as $r) {
            $subScoreMap[(int)$r['subject_id']][$r['unit']][$r['score_type']] = (float)$r['score'];
        }
    }
}

// ── Cabeçalho da grade ────────────────────────────────────────
$gradeHeader = [];
if (dbRow("SHOW TABLES LIKE 'grade_headers'")) {
    $gradeHeader = dbRow('SELECT escola, classe, ano_letivo FROM grade_headers WHERE user_id=?', [$userId]) ?? [];
}

// ── Helper: média de uma unidade ──────────────────────────────
function calcUnitMedia(array $slugs, array $scoreMap): ?float {
    $vals = [];
    foreach ($slugs as $slug) {
        if (isset($scoreMap[$slug])) $vals[] = $scoreMap[$slug];
    }
    return count($vals) ? array_sum($vals)/count($vals) : null;
}

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
  <title>florescer — Desempenho</title>
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
    --sh0:0 1px 3px rgba(0,0,0,.06);--sh1:0 2px 8px rgba(0,0,0,.07);--sh3:0 12px 32px rgba(0,0,0,.12);
  }
  html,body{height:100%}
  body{font-family:var(--fb);background:var(--n50);color:var(--n800);display:flex;-webkit-font-smoothing:antialiased;overflow-x:hidden}
  ::-webkit-scrollbar{width:4px}::-webkit-scrollbar-thumb{background:rgba(64,145,108,.2);border-radius:2px}
  .main{margin-left:var(--sw);flex:1;display:flex;flex-direction:column;min-width:0}
  .topbar{height:var(--hh);background:rgba(250,248,245,.92);backdrop-filter:blur(14px);border-bottom:1px solid rgba(0,0,0,.055);display:flex;align-items:center;gap:.8rem;padding:0 1.8rem;position:sticky;top:0;z-index:40;flex-shrink:0}
  .hamburger{display:none;flex-direction:column;gap:4px;cursor:pointer;background:none;border:none;padding:5px;border-radius:6px}
  .hamburger span{display:block;width:19px;height:2px;background:var(--g600);border-radius:1px;transition:all var(--d) var(--e)}
  .hamburger.open span:nth-child(1){transform:translateY(6px) rotate(45deg)}
  .hamburger.open span:nth-child(2){opacity:0}
  .hamburger.open span:nth-child(3){transform:translateY(-6px) rotate(-45deg)}
  .tb-title{font-family:var(--fd);font-size:1rem;font-weight:600;color:var(--n800)}
  .content{flex:1;padding:1.8rem 2rem}
  .sec-head{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.4rem;flex-wrap:wrap;gap:.8rem}
  .sec-title{font-family:var(--fd);font-size:1.4rem;font-weight:900;color:var(--n800);letter-spacing:-.03em}
  .sec-sub{font-size:.8rem;color:#aaa;margin-top:.2rem}
  .legend{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;font-size:.73rem;margin-bottom:1.1rem}
  .leg-item{display:flex;align-items:center;gap:.28rem;padding:.2rem .55rem;border-radius:20px;font-weight:500}
  /* ── Cabeçalho da grade ── */
  .grade-header-card{background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r);padding:1rem 1.2rem;margin-bottom:1rem;box-shadow:var(--sh0);display:grid;grid-template-columns:2fr 1fr 1fr;gap:.75rem;align-items:end}
  .gh-group label{display:block;font-size:.68rem;font-weight:600;color:#aaa;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.28rem}
  .gh-input{width:100%;padding:.48rem .7rem;background:var(--n50);border:1px solid rgba(0,0,0,.09);border-radius:var(--rs);font-family:var(--fb);font-size:.83rem;color:var(--n800);outline:none;transition:all var(--d) var(--e)}
  .gh-input:focus{border-color:var(--g400);background:var(--white);box-shadow:0 0 0 3px rgba(64,145,108,.1)}
  /* ── Avaliações personalizadas ── */
  .assess-card{background:var(--white);border:1px solid rgba(0,0,0,.06);border-radius:var(--r);padding:1rem 1.2rem;margin-bottom:1rem;box-shadow:var(--sh0)}
  .assess-title{font-size:.78rem;font-weight:700;color:var(--n800);margin-bottom:.7rem;display:flex;align-items:center;gap:.5rem}
  .assess-list{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:.6rem}
  .assess-tag{display:inline-flex;align-items:center;gap:.35rem;padding:.28rem .65rem;border-radius:20px;background:var(--g50);border:1px solid rgba(64,145,108,.2);font-size:.75rem;font-weight:600;color:var(--g600)}
  .assess-tag .remove-btn{cursor:pointer;opacity:.5;font-size:.68rem;transition:opacity .15s;background:none;border:none;color:inherit;padding:0;line-height:1}
  .assess-tag .remove-btn:hover{opacity:1}
  .assess-add-row{display:flex;gap:.4rem;align-items:center;flex-wrap:wrap}
  .assess-input{padding:.38rem .7rem;background:var(--n50);border:1px solid rgba(0,0,0,.09);border-radius:var(--rs);font-family:var(--fb);font-size:.8rem;color:var(--n800);outline:none;transition:all var(--d) var(--e);width:140px}
  .assess-input:focus{border-color:var(--g400);background:var(--white);box-shadow:0 0 0 2px rgba(64,145,108,.1)}
  .assess-btn{display:inline-flex;align-items:center;gap:.28rem;padding:.36rem .8rem;background:linear-gradient(135deg,var(--g500),var(--g600));color:#fff;border:none;border-radius:50px;font-family:var(--fb);font-size:.75rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);box-shadow:0 2px 8px rgba(64,145,108,.2)}
  .assess-btn:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(64,145,108,.3)}
  .assess-hint{font-size:.7rem;color:#bbb;margin-top:.35rem}
  /* ── Tabela ── */
  .grade-table-wrap{background:var(--white);border:1px solid rgba(0,0,0,.07);border-radius:var(--r);overflow:hidden;box-shadow:var(--sh0);overflow-x:auto}
  .grade-table{width:100%;border-collapse:collapse;font-size:.8rem;min-width:500px}
  .grade-table thead th{padding:.55rem .5rem;text-align:center;border-bottom:1px solid var(--n100);background:var(--n50)}
  .grade-table thead th:first-child{text-align:left;padding-left:1.1rem}
  .grade-table thead .th-unit-name{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#aaa}
  .grade-table thead .th-tipos{display:flex;justify-content:center;gap:4px;margin-top:3px;flex-wrap:wrap}
  .tipo-badge{font-size:.59rem;font-weight:700;padding:.1rem .32rem;border-radius:3px;letter-spacing:.04em;background:var(--g50);color:var(--g600);border:1px solid rgba(64,145,108,.15)}
  .grade-table td{padding:.45rem .35rem;border-bottom:1px solid rgba(0,0,0,.04);vertical-align:middle;text-align:center}
  .grade-table td:first-child{text-align:left;padding-left:1.1rem;font-weight:600;white-space:nowrap;font-size:.82rem;color:var(--n800)}
  .grade-table tr:last-child td{border-bottom:none}
  .grade-table tr:hover td{background:var(--n50)}
  .unit-cell{display:flex;flex-direction:column;gap:3px;align-items:center;padding:4px 0}
  .cell-input{width:52px;padding:.28rem .25rem;text-align:center;background:var(--n50);border:1px solid rgba(0,0,0,.08);border-radius:5px;font-family:var(--fb);font-size:.76rem;color:var(--n800);outline:none;transition:all var(--d) var(--e)}
  .cell-input:focus{border-color:var(--g400);background:var(--white);box-shadow:0 0 0 2px rgba(64,145,108,.12)}
  .cell-input::placeholder{color:#ddd}
  .cell-input.purple{background:#ede9fe!important;border-color:#c4b5fd!important;color:#7c3aed!important;font-weight:700}
  .unit-avg{font-size:.64rem;font-weight:600;color:#aaa;min-height:.9rem;margin-top:1px}
  .unit-avg.green  {color:#16a34a}
  .unit-avg.amber  {color:#d97706}
  .unit-avg.red    {color:#dc2626}
  .unit-avg.purple {color:#7c3aed}
  .media-val{font-family:var(--fd);font-size:.92rem;font-weight:700}
  .media-roxo {color:#7c3aed}
  .media-green{color:#16a34a}
  .media-amber{color:#d97706}
  .media-red  {color:#dc2626}
  .media-gray {color:#aaa}
  .res-badge{display:inline-block;padding:.22rem .6rem;border-radius:20px;font-size:.67rem;font-weight:600;white-space:nowrap}
  .res-ad   {background:#dcfce7;color:#16a34a}
  .res-roxo {background:#ede9fe;color:#7c3aed}
  .res-rec  {background:#fef3c7;color:#d97706}
  .res-rep  {background:#fee2e2;color:#dc2626}
  .res-nd   {background:#f3f4f6;color:#9ca3af}
  .btn-save-header{display:inline-flex;align-items:center;gap:.3rem;padding:.42rem .9rem;background:linear-gradient(135deg,var(--g500),var(--g600));color:var(--white);border:none;border-radius:50px;font-family:var(--fb);font-size:.76rem;font-weight:600;cursor:pointer;transition:all var(--d) var(--e);box-shadow:0 2px 8px rgba(64,145,108,.2);white-space:nowrap;align-self:flex-end}
  .btn-save-header:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(64,145,108,.3)}
  .btn-save-header:disabled{opacity:.6;cursor:not-allowed;transform:none}
  .info-row{display:flex;align-items:center;gap:.5rem;margin-bottom:.8rem;flex-wrap:wrap}
  .info-chip{display:flex;align-items:center;gap:.28rem;font-size:.73rem;color:#888;padding:.18rem .55rem;border-radius:20px;background:var(--n100);border:1px solid rgba(0,0,0,.06)}
  .empty{text-align:center;padding:3rem 1.5rem;color:#bbb}
  .empty-ico{font-size:2.5rem;opacity:.3;display:block;margin-bottom:.7rem}
  .empty p{font-size:.83rem;line-height:1.7}
  .empty a{color:var(--g500)}
  .toast-wrap{position:fixed;bottom:1.4rem;right:1.4rem;z-index:500;display:flex;flex-direction:column;gap:.4rem;pointer-events:none}
  .toast{background:var(--n800);color:#eee;padding:.62rem .95rem;border-radius:var(--rs);font-size:.79rem;display:flex;align-items:center;gap:.4rem;animation:tin .24s var(--e) both;max-width:270px;box-shadow:var(--sh3);pointer-events:all}
  .toast.ok  {border-left:3px solid var(--g400)}
  .toast.err {border-left:3px solid #f87171}
  .toast.info{border-left:3px solid var(--gold)}
  @keyframes tin{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}
  @media(max-width:900px){.grade-header-card{grid-template-columns:1fr 1fr}}
  @media(max-width:768px).main{margin-left:0}.hamburger{display:flex}.topbar{padding:0 1.1rem}.content{padding:1.2rem 1rem}.grade-header-card{grid-template-columns:1fr}}
  </style>
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

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
  <header class="topbar">
    <button class="hamburger" id="hamburger" onclick="toggleSidebar()">
      <span></span><span></span><span></span>
    </button>
    <span class="tb-title">📊 Desempenho</span>
  </header>

  <main class="content">

    <?php if (!$activeObj): ?>
    <div class="empty">
      <span class="empty-ico">📊</span>
      <p>Nenhum objetivo ativo.<br><a href="/florescer/public/views/objectives.php">Criar objetivo →</a></p>
    </div>
    <?php else: ?>

    <div class="sec-head">
      <div>
        <div class="sec-title">Desempenho</div>
        <div class="sec-sub">
          <?= htmlspecialchars($activeObj['name'],ENT_QUOTES) ?>
          · média mínima <?= number_format($avgMin,1,',','.') ?>
          · <strong><?= htmlspecialchars($unitLabel, ENT_QUOTES) ?></strong>
        </div>
      </div>
    </div>

    <!-- Legenda de cores dinâmica com base na média do objetivo -->
    <div class="legend">
      <span class="leg-item" style="background:#ede9fe;color:#7c3aed">
        🟣 Acima de <?= number_format($threshAbove,1,',','.') ?>
      </span>
      <span class="leg-item" style="background:#dcfce7;color:#16a34a">
        🟢 Na média (≥<?= number_format($threshOk,1,',','.') ?>)
      </span>
      <span class="leg-item" style="background:#fef3c7;color:#d97706">
        🟡 Próximo (≥<?= number_format($threshWarn,1,',','.') ?>)
      </span>
      <span class="leg-item" style="background:#fee2e2;color:#dc2626">
        🔴 Em risco (&lt;<?= number_format($threshWarn,1,',','.') ?>)
      </span>
      <span class="leg-item" style="background:#f3f4f6;color:#9ca3af">⚪ Sem nota</span>
    </div>

    <!-- Avaliações personalizadas -->
    <div class="assess-card">
      <div class="assess-title">
        📝 Avaliações desta grade
        <span style="font-size:.7rem;font-weight:400;color:#aaa">(máx. 5 por unidade)</span>
      </div>
      <div class="assess-list" id="assessList">
        <?php if (empty($assessments)): ?>
          <span style="font-size:.75rem;color:#ccc;font-style:italic">Nenhuma avaliação adicionada ainda.</span>
        <?php else: ?>
          <?php foreach ($assessments as $a): ?>
            <span class="assess-tag" id="at-<?= $a['id'] ?>">
              <span><?= htmlspecialchars($a['name'], ENT_QUOTES) ?></span>
              <button class="remove-btn" onclick="removeAssessment(<?= $a['id'] ?>, '<?= htmlspecialchars($a['slug'], ENT_QUOTES) ?>')"
                      title="Remover">✕</button>
            </span>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="assess-add-row">
        <input class="assess-input" id="assessName" type="text" maxlength="12"
               placeholder="Ex: Prova, Trabalho..." oninput="limitAssessName(this)"/>
        <button class="assess-btn" onclick="addAssessment()">+ Adicionar</button>
      </div>
      <div class="assess-hint">
        A 1ª letra de cada avaliação é usada como símbolo na fórmula da média.
        Letras repetidas são diferenciadas automaticamente.
      </div>
    </div>

    <?php if (empty($subjects)): ?>
    <div class="empty">
      <span class="empty-ico">📚</span>
      <p>Nenhuma matéria neste objetivo.<br><a href="/florescer/public/views/materials.php">Adicionar matérias →</a></p>
    </div>
    <?php else: ?>

    <!-- Cabeçalho da grade -->
    <div class="grade-header-card">
      <div class="gh-group">
        <label>Colégio / Universidade</label>
        <input class="gh-input" id="gh-escola"
               value="<?= htmlspecialchars($gradeHeader['escola']??'',ENT_QUOTES) ?>"
               placeholder="Nome da instituição" oninput="headerChanged()"/>
      </div>
      <div class="gh-group">
        <label>Classe / Turma</label>
        <input class="gh-input" id="gh-classe"
               value="<?= htmlspecialchars($gradeHeader['classe']??'',ENT_QUOTES) ?>"
               placeholder="Ex: 1º EM – A" oninput="headerChanged()"/>
      </div>
      <div class="gh-group" style="display:flex;flex-direction:column">
        <label>Ano Letivo</label>
        <div style="display:flex;gap:.5rem;align-items:center">
          <input class="gh-input" id="gh-ano" style="flex:1"
                 value="<?= htmlspecialchars($gradeHeader['ano_letivo']??date('Y'),ENT_QUOTES) ?>"
                 placeholder="<?= date('Y') ?>" oninput="headerChanged()"/>
          <button class="btn-save-header" id="btnSaveHeader" onclick="saveHeaderNow()">💾 Salvar</button>
        </div>
      </div>
    </div>

    <?php if (empty($assessments)): ?>
    <div class="empty" style="padding:2rem">
      <span class="empty-ico">📝</span>
      <p>Adicione pelo menos uma avaliação acima para registrar notas.</p>
    </div>
    <?php else: ?>

    <!-- Tabela de notas -->
    <div class="grade-table-wrap">
      <table class="grade-table">
        <thead>
          <tr>
            <th class="th-unit-name" style="text-align:left;padding-left:1.1rem;min-width:130px">Matéria</th>

            <?php foreach ($units as $u): ?>
            <th>
              <div class="th-unit-name"><?= htmlspecialchars($u, ENT_QUOTES) ?></div>
              <div class="th-tipos">
                <?php foreach ($assessments as $a):
                  // Símbolo = primeira letra do nome
                  $sym = strtoupper(mb_substr($a['name'], 0, 1, 'UTF-8'));
                ?>
                <span class="tipo-badge" title="<?= htmlspecialchars($a['name'],ENT_QUOTES) ?>"><?= $sym ?></span>
                <?php endforeach; ?>
              </div>
            </th>
            <?php endforeach; ?>

            <th class="th-unit-name">Média</th>
            <th class="th-unit-name">Resultado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($subjects as $s):
            $sid     = (int)$s['id'];
            $subjMap = $subScoreMap[$sid] ?? [];

            // Médias das unidades usando os slugs das avaliações
            $unitMediaArr = [];
            foreach ($units as $u) {
                $m = calcUnitMedia($assessSlugs, $subjMap[$u] ?? []);
                if ($m !== null) $unitMediaArr[] = $m;
            }
            $mediaGeral = count($unitMediaArr)
                ? array_sum($unitMediaArr) / count($unitMediaArr)
                : null;

            // Classes de cor dinâmicas
            $mediaCls = 'media-gray';
            if ($mediaGeral !== null) {
                if      ($mediaGeral > $threshAbove) $mediaCls = 'media-roxo';
                elseif  ($mediaGeral >= $threshOk)   $mediaCls = 'media-green';
                elseif  ($mediaGeral >= $threshWarn) $mediaCls = 'media-amber';
                else                                 $mediaCls = 'media-red';
            }

            $resLabel = '—'; $resCls = 'res-nd';
            if ($mediaGeral !== null) {
                if      ($mediaGeral > $threshAbove) { $resLabel = 'Aprovado AD'; $resCls = 'res-roxo'; }
                elseif  ($mediaGeral >= $threshOk)   { $resLabel = 'Aprovado';    $resCls = 'res-ad';   }
                elseif  ($mediaGeral >= $threshWarn) { $resLabel = 'Recuperação'; $resCls = 'res-rec';  }
                else                                 { $resLabel = 'Reprovado';   $resCls = 'res-rep';  }
            }
          ?>
          <tr id="gr-<?= $sid ?>">
            <td>
              <?php if ($s['color']): ?>
              <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= htmlspecialchars($s['color'],ENT_QUOTES) ?>;margin-right:6px;flex-shrink:0"></span>
              <?php endif; ?>
              <?= htmlspecialchars($s['name'],ENT_QUOTES) ?>
            </td>

            <?php foreach ($units as $u):
              $tiposData = $subjMap[$u] ?? [];
              $uVals     = [];
              foreach ($assessSlugs as $slug) {
                  if (isset($tiposData[$slug])) $uVals[] = $tiposData[$slug];
              }
              $uMedia  = count($uVals) ? array_sum($uVals)/count($uVals) : null;
              $uAvgCls = '';
              if ($uMedia !== null) {
                  if      ($uMedia > $threshAbove) $uAvgCls = 'purple';
                  elseif  ($uMedia >= $threshOk)   $uAvgCls = 'green';
                  elseif  ($uMedia >= $threshWarn) $uAvgCls = 'amber';
                  else                             $uAvgCls = 'red';
              }
            ?>
            <td>
              <div class="unit-cell" id="uc-<?= $sid ?>-<?= $u ?>">
                <?php foreach ($assessments as $a):
                  $tv = $tiposData[$a['slug']] ?? null;
                  $isPurple = $tv !== null && $tv > $threshAbove;
                  $sym = strtoupper(mb_substr($a['name'], 0, 1, 'UTF-8'));
                ?>
                <input class="cell-input <?= $isPurple ? 'purple' : '' ?>"
                       id="gi-<?= $sid ?>-<?= $u ?>-<?= htmlspecialchars($a['slug'],ENT_QUOTES) ?>"
                       type="text"
                       inputmode="decimal"
                       maxlength="4"
                       value="<?= $tv !== null ? number_format($tv,1,'.','') : '' ?>"
                       placeholder="<?= $sym ?>"
                       title="<?= htmlspecialchars($a['name'],ENT_QUOTES) ?>"
                       data-sid="<?= $sid ?>"
                       data-unit="<?= $u ?>"
                       data-slug="<?= htmlspecialchars($a['slug'],ENT_QUOTES) ?>"
                       oninput="onGradeInput(this)"/>
                <?php endforeach; ?>
                <div class="unit-avg <?= $uAvgCls ?>" id="ua-<?= $sid ?>-<?= $u ?>">
                  <?= $uMedia !== null ? number_format($uMedia,1,',','') : '' ?>
                </div>
              </div>
            </td>
            <?php endforeach; ?>

            <td id="gm-<?= $sid ?>">
              <span class="media-val <?= $mediaCls ?>">
                <?= $mediaGeral !== null ? number_format($mediaGeral,2,',','') : '—' ?>
              </span>
            </td>
            <td id="gres-<?= $sid ?>">
              <span class="res-badge <?= $resCls ?>"><?= $resLabel ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php endif; // assessments ?>
    <?php endif; // subjects ?>
    <?php endif; // activeObj ?>

  </main>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
const API_GRADES    = '/florescer/api/grades.php';
const API_ASSESS    = '/florescer/api/assessments.php';
const OBJ_ID        = <?= (int)($activeObj['id'] ?? 0) ?>;
const AVG_MIN       = <?= (float)$avgMin ?>;
const THRESH_ABOVE  = <?= (float)$threshAbove ?>;
const THRESH_OK     = <?= (float)$threshOk ?>;
const THRESH_WARN   = <?= (float)$threshWarn ?>;
const UNITS         = <?= json_encode($units) ?>;
// Avaliações carregadas do banco (slug → label)
let ASSESSMENTS = <?= json_encode(array_values($assessments), JSON_UNESCAPED_UNICODE) ?>;

/* ── Utils ────────────────────────────────────────────────── */
function toast(msg, type='ok', ms=3200) {
  const w=document.getElementById('toastWrap'), d=document.createElement('div');
  d.className=`toast ${type}`;
  d.innerHTML=`<span>${type==='ok'?'✅':type==='err'?'❌':'💡'}</span><span>${msg}</span>`;
  w.appendChild(d);
  setTimeout(()=>{d.style.opacity='0';d.style.transition='.3s';setTimeout(()=>d.remove(),320);},ms);
}

async function apiCall(url, body) {
  try {
    const r = await fetch(url,{
      method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)
    });
    if(!r.ok) throw new Error('HTTP '+r.status);
    return await r.json();
  } catch(e) {
    return {success:false, message:'Erro de conexão: '+e.message};
  }
}

function toggleSidebar(){
  const sb=document.getElementById('sidebar'),ov=document.getElementById('sbOverlay'),hb=document.getElementById('hamburger');
  if(!sb) return;
  const open=sb.classList.toggle('open');
  if(ov) ov.classList.toggle('show',open);
  if(hb) hb.classList.toggle('open',open);
  document.body.style.overflow=open?'hidden':'';
}

function parseNota(v) {
  const s=String(v||'').trim().replace(',','.');
  if(s===''||s==='—') return null;
  const n=parseFloat(s);
  return isNaN(n)?null:n;
}

function avgClass(v) {
  if(v===null) return 'media-gray';
  if(v>THRESH_ABOVE) return 'media-roxo';
  if(v>=THRESH_OK)   return 'media-green';
  if(v>=THRESH_WARN) return 'media-amber';
  return 'media-red';
}
function avgClassUnit(v) {
  if(v===null) return '';
  if(v>THRESH_ABOVE) return 'purple';
  if(v>=THRESH_OK)   return 'green';
  if(v>=THRESH_WARN) return 'amber';
  return 'red';
}

/* ── Limite de caracteres no input de avaliação ─────────── */
function limitAssessName(inp){
  if(inp.value.length>12) inp.value=inp.value.slice(0,12);
}

/* ── Avaliações ─────────────────────────────────────────── */
async function addAssessment() {
  const inp = document.getElementById('assessName');
  const name = inp.value.trim();
  if(!name){ toast('Informe o nome da avaliação.','err'); return; }
  if(name.length>12){ toast('Nome deve ter no máximo 12 caracteres.','err'); return; }
  if(ASSESSMENTS.length>=5){ toast('Máximo de 5 avaliações por grade.','info'); return; }

  const r = await apiCall(API_ASSESS, {action:'create', objective_id:OBJ_ID, name});
  if(!r.success){ toast(r.message||'Erro ao adicionar.','err'); return; }

  // Adiciona à lista local
  ASSESSMENTS.push({id:r.data.id, name:r.data.name, slug:r.data.slug});
  inp.value='';

  // Renderiza a tag
  const list = document.getElementById('assessList');
  // Remove placeholder
  const ph = list.querySelector('span[style]');
  if(ph) ph.remove();

  const tag = document.createElement('span');
  tag.className='assess-tag'; tag.id=`at-${r.data.id}`;
  tag.innerHTML=`<span>${escHtml(r.data.name)}</span>
    <button class="remove-btn" onclick="removeAssessment(${r.data.id},'${r.data.slug}')" title="Remover">✕</button>`;
  list.appendChild(tag);

  toast(`"${r.data.name}" adicionada! Recarregue a página para ver as colunas.`,'info',4000);
}

async function removeAssessment(id, slug) {
  if(!confirm(`Remover esta avaliação? As notas salvas para ela serão mantidas.`)) return;
  const r = await apiCall(API_ASSESS, {action:'delete', id, objective_id:OBJ_ID});
  if(!r.success){ toast(r.message||'Erro.','err'); return; }
  ASSESSMENTS = ASSESSMENTS.filter(a=>a.id!==id);
  const tag = document.getElementById(`at-${id}`);
  if(tag) tag.remove();
  const list=document.getElementById('assessList');
  if(!list.children.length){
    list.innerHTML='<span style="font-size:.75rem;color:#ccc;font-style:italic">Nenhuma avaliação adicionada ainda.</span>';
  }
  toast('Avaliação removida.');
}

/* ── Input de nota ──────────────────────────────────────── */
const _debGrade = {};

function onGradeInput(inp) {
  const sid  = parseInt(inp.dataset.sid);
  const unit = inp.dataset.unit;
  const slug = inp.dataset.slug;
  const n    = parseNota(inp.value);

  inp.classList.toggle('purple', n!==null && n>THRESH_ABOVE);
  calcUnitAvg(sid, unit);
  calcRowAvg(sid);

  const key=`${sid}-${unit}-${slug}`;
  clearTimeout(_debGrade[key]);
  _debGrade[key]=setTimeout(async()=>{
    if(n!==null && (n<0||n>10)){
      toast('Nota deve ser entre 0 e 10','err'); return;
    }
    const r=await apiCall(API_GRADES,{action:'save_sub',subject_id:sid,unit,score_type:slug,score:n});
    if(!r.success) toast(r.message||'Erro ao salvar','err');
  }, 700);
}

function calcUnitAvg(sid, unit) {
  const vals = ASSESSMENTS.map(a=>{
    const el=document.getElementById(`gi-${sid}-${unit}-${a.slug}`);
    return el?parseNota(el.value):null;
  }).filter(v=>v!==null&&!isNaN(v));

  const avgEl=document.getElementById(`ua-${sid}-${unit}`);
  if(!avgEl) return;
  if(!vals.length){ avgEl.textContent=''; avgEl.className='unit-avg'; return; }
  const avg=vals.reduce((a,b)=>a+b,0)/vals.length;
  avgEl.textContent=avg.toFixed(1).replace('.',',');
  avgEl.className='unit-avg '+avgClassUnit(avg);
}

function calcRowAvg(sid) {
  const unitMedias=UNITS.map(u=>{
    const vals=ASSESSMENTS.map(a=>{
      const el=document.getElementById(`gi-${sid}-${u}-${a.slug}`);
      return el?parseNota(el.value):null;
    }).filter(v=>v!==null&&!isNaN(v));
    return vals.length?vals.reduce((a,b)=>a+b,0)/vals.length:null;
  }).filter(v=>v!==null);

  const mediaEl=document.getElementById('gm-'+sid);
  const resEl  =document.getElementById('gres-'+sid);
  if(!mediaEl) return;

  if(!unitMedias.length){
    mediaEl.innerHTML='<span class="media-val media-gray">—</span>';
    if(resEl) resEl.innerHTML='<span class="res-badge res-nd">—</span>';
    return;
  }
  const avg=unitMedias.reduce((a,b)=>a+b,0)/unitMedias.length;
  const cls=avgClass(avg);
  mediaEl.innerHTML=`<span class="media-val ${cls}">${avg.toFixed(2).replace('.',',')}</span>`;

  if(resEl){
    let label, badge;
    if     (avg>THRESH_ABOVE){label='Aprovado AD';badge='res-roxo';}
    else if(avg>=THRESH_OK)  {label='Aprovado';   badge='res-ad'; }
    else if(avg>=THRESH_WARN){label='Recuperação';badge='res-rec';}
    else                     {label='Reprovado';  badge='res-rep';}
    resEl.innerHTML=`<span class="res-badge ${badge}">${label}</span>`;
  }
}

/* ── Cabeçalho ──────────────────────────────────────────── */
let _headerDeb=null, _headerDirty=false;
function headerChanged(){
  _headerDirty=true;
  const btn=document.getElementById('btnSaveHeader');
  if(btn) btn.style.boxShadow='0 0 0 2px rgba(64,145,108,.35)';
  clearTimeout(_headerDeb);
  _headerDeb=setTimeout(saveHeaderNow,2000);
}
async function saveHeaderNow(){
  clearTimeout(_headerDeb);
  if(!_headerDirty) return;
  const btn=document.getElementById('btnSaveHeader');
  if(btn){btn.disabled=true;btn.textContent='Salvando…';}
  const r=await apiCall(API_GRADES,{
    action:'save_header',
    escola:document.getElementById('gh-escola')?.value||'',
    classe:document.getElementById('gh-classe')?.value||'',
    ano:   document.getElementById('gh-ano')?.value||'',
  });
  _headerDirty=false;
  if(btn){
    btn.disabled=false;
    btn.innerHTML=r.success?'✓ Salvo':'❌ Erro';
    btn.style.boxShadow='';
    setTimeout(()=>{btn.innerHTML='💾 Salvar';},2000);
  }
  if(!r.success) toast(r.message||'Erro ao salvar cabeçalho','err');
}

/* ── Escape HTML ─────────────────────────────────────────── */
function escHtml(s){
  return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
</script>

</body>
</html>