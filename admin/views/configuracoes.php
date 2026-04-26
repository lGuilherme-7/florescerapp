<?php
// ============================================================
// /admin/views/configuracoes.php — florescer Admin v3.0
// ============================================================
require_once __DIR__ . '/../includes/auth_admin.php';
require_once dirname(dirname(__DIR__)) . '/config/db.php';

requireAdmin();
date_default_timezone_set('America/Recife');

$adminId   = (int)($_SESSION['admin_id']   ?? 0);
$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminLetter = strtoupper(mb_substr($adminName, 0, 1, 'UTF-8'));

// ── Lê system_config ─────────────────────────────────────────
$tableOk = (bool)dbRow("SHOW TABLES LIKE 'system_config'");
$cfg = [];
if ($tableOk) {
    foreach (dbQuery("SELECT key_name, value FROM system_config") as $r)
        $cfg[$r['key_name']] = $r['value'];
}

function cv(array $cfg, string $k, string $d = ''): string {
    return htmlspecialchars($cfg[$k] ?? $d, ENT_QUOTES, 'UTF-8');
}
function ck(array $cfg, string $k): bool {
    return ($cfg[$k] ?? '0') === '1';
}

// ── Dados do admin logado ─────────────────────────────────────
$adminRow   = dbRow('SELECT email, totp_enabled FROM admin_users WHERE id = ?', [$adminId]);
$adminEmail = $adminRow['email']        ?? '';
$totpOn     = (bool)($adminRow['totp_enabled'] ?? false);

// ── audit log ────────────────────────────────────────────────
$failedLogins = (int)(dbRow(
    "SELECT COUNT(*) AS n FROM admin_audit_log
     WHERE event='LOGIN_FAIL' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
)['n'] ?? 0);

// ── feedbacks abertos (badge no nav) ─────────────────────────
$openFeedbacks = (bool)dbRow("SHOW TABLES LIKE 'feedbacks'")
    ? (int)(dbRow("SELECT COUNT(*) AS n FROM feedbacks WHERE status='aberto'")['n'] ?? 0)
    : 0;

// ── Versão do banco de dados ──────────────────────────────────
$dbVersion = dbRow("SELECT VERSION() AS v")['v'] ?? '?';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <meta name="robots" content="noindex,nofollow"/>
  <title>Configurações — florescer Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
  /* ══ RESET & TOKENS ══════════════════════════════════════════ */
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{
    --ink:  #0b1a12;--ink2:#122019;--ink3:#1a3027;
    --border:rgba(82,183,136,.1);--border2:rgba(82,183,136,.06);
    --muted: rgba(116,198,157,.3);--muted2:rgba(116,198,157,.18);
    --leaf:  #52b788;--leaf2:#74c69d;--leaf3:#b7e4c7;
    --gold:  #c9a84c;--red:#e05252;
    --text:  #c8e6d4;--text2:rgba(200,230,212,.55);--text3:rgba(200,230,212,.3);
    --serif:'Instrument Serif',Georgia,serif;
    --sans: 'DM Sans',system-ui,sans-serif;
    --sw:220px;--hh:54px;--r:12px;--r2:8px;--gap:1rem;
    --sh1:0 1px 4px rgba(0,0,0,.2);--sh2:0 4px 20px rgba(0,0,0,.3);
    --t:.18s cubic-bezier(.4,0,.2,1);
  }
  html,body{height:100%;font-family:var(--sans);background:var(--ink);color:var(--text);-webkit-font-smoothing:antialiased}
  body{display:flex;overflow-x:hidden}
  ::-webkit-scrollbar{width:3px;height:3px}
  ::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px}

  /* ══ SIDEBAR ════════════════════════════════════════════════ */
  .aside{width:var(--sw);min-height:100vh;position:fixed;top:0;left:0;
    background:var(--ink2);border-right:1px solid var(--border2);
    display:flex;flex-direction:column;z-index:50}
  .a-logo{padding:1.1rem 1.2rem .9rem;border-bottom:1px solid var(--border2);
    display:flex;align-items:center;gap:.6rem;flex-shrink:0}
  .a-logo-mark{width:32px;height:32px;border-radius:9px;flex-shrink:0;
    background:linear-gradient(135deg,var(--leaf) 0%,#2d6a4f 100%);
    display:flex;align-items:center;justify-content:center;font-size:.95rem;
    box-shadow:0 2px 10px rgba(82,183,136,.25)}
  .a-logo-name{font-family:var(--serif);font-size:1rem;color:var(--leaf3);line-height:1.1;letter-spacing:-.01em}
  .a-logo-tag{font-size:.54rem;color:var(--muted);text-transform:uppercase;letter-spacing:.12em}
  .a-who{margin:.65rem .8rem;background:rgba(82,183,136,.05);border:1px solid var(--border);
    border-radius:var(--r2);padding:.45rem .65rem;display:flex;align-items:center;gap:.5rem}
  .a-av{width:24px;height:24px;border-radius:50%;flex-shrink:0;
    background:linear-gradient(135deg,var(--leaf),#2d6a4f);
    display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:600;color:#fff}
  .a-name{font-size:.72rem;font-weight:500;color:var(--leaf2);line-height:1}
  .a-role{font-size:.57rem;color:var(--muted);margin-top:.08rem}
  .a-nav{flex:1;overflow-y:auto;padding:.3rem 0 .5rem}
  .a-grp{font-size:.52rem;text-transform:uppercase;letter-spacing:.1em;
    color:var(--muted2);padding:.7rem 1.2rem .2rem;display:block}
  .a-link{display:flex;align-items:center;gap:.5rem;padding:.38rem 1.2rem;
    font-size:.74rem;color:var(--text3);text-decoration:none;
    border-left:2px solid transparent;transition:all var(--t)}
  .a-link:hover{color:var(--leaf2);background:rgba(82,183,136,.04)}
  .a-link.active{color:var(--leaf2);background:rgba(82,183,136,.07);
    border-left-color:var(--leaf);font-weight:500}
  .a-link-ico{width:.9rem;text-align:center;font-size:.78rem;opacity:.8;flex-shrink:0}
  .nav-badge{margin-left:auto;background:rgba(224,82,82,.15);color:#e05252;
    font-size:.55rem;font-weight:600;padding:.1rem .35rem;border-radius:20px;
    border:1px solid rgba(224,82,82,.2)}
  .a-foot{padding:.7rem .8rem;border-top:1px solid var(--border2);flex-shrink:0}
  .a-logout{width:100%;display:flex;align-items:center;justify-content:center;gap:.38rem;
    padding:.38rem;border-radius:var(--r2);background:none;border:1px solid rgba(224,82,82,.12);
    color:rgba(224,82,82,.4);font-family:var(--sans);font-size:.7rem;cursor:pointer;transition:all var(--t)}
  .a-logout:hover{background:rgba(224,82,82,.06);color:var(--red)}

  /* ══ MAIN ════════════════════════════════════════════════════ */
  .main{margin-left:var(--sw);flex:1;min-width:0;display:flex;flex-direction:column;min-height:100vh}
  .topbar{height:var(--hh);position:sticky;top:0;z-index:40;
    background:rgba(11,26,18,.92);backdrop-filter:blur(16px);
    border-bottom:1px solid var(--border2);
    display:flex;align-items:center;justify-content:space-between;padding:0 1.5rem;flex-shrink:0}
  .tb-left{display:flex;align-items:baseline;gap:.5rem}
  .tb-title{font-family:var(--serif);font-size:1rem;color:var(--leaf3);letter-spacing:-.01em}
  .tb-sub{font-size:.67rem;color:var(--muted)}
  .tb-right{display:flex;align-items:center;gap:.75rem}
  .pill{display:flex;align-items:center;gap:.28rem;background:rgba(82,183,136,.07);
    border:1px solid var(--border);border-radius:50px;padding:.2rem .6rem;
    font-size:.63rem;font-weight:500;color:var(--leaf2)}
  .pill-dot{width:5px;height:5px;border-radius:50%;background:var(--leaf);animation:pulse 2.5s infinite}
  @keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.8)}}

  /* ══ PAGE ════════════════════════════════════════════════════ */
  .page-wrap{flex:1;overflow-y:auto;padding:1.5rem 1.8rem 3rem}
  .page-inner{width:100%;max-width:820px;margin:0 auto;display:flex;flex-direction:column;gap:1.2rem}

  /* ══ SECTION ════════════════════════════════════════════════ */
  .sec{background:var(--ink2);border:1px solid var(--border2);border-radius:var(--r);overflow:hidden}
  .sec-head{padding:.85rem 1.2rem;border-bottom:1px solid var(--border2);
    display:flex;align-items:center;gap:.8rem}
  .sec-ico{width:34px;height:34px;border-radius:9px;flex-shrink:0;
    background:rgba(82,183,136,.08);border:1px solid var(--border);
    display:flex;align-items:center;justify-content:center;font-size:.95rem}
  .sec-title{font-family:var(--serif);font-size:.92rem;color:var(--text);letter-spacing:-.01em}
  .sec-sub{font-size:.68rem;color:var(--text3);margin-top:.08rem}
  .sec-body{padding:1.2rem}

  /* ══ FORM GRID ══════════════════════════════════════════════ */
  .fgrid{display:grid;grid-template-columns:1fr 1fr;gap:.85rem}
  .fgrid.g3{grid-template-columns:1fr 1fr 1fr}
  .fgrid.g1{grid-template-columns:1fr}
  .fg{display:flex;flex-direction:column;gap:.3rem}
  .fg.span2{grid-column:1/-1}
  .fg.span3{grid-column:1/-1}
  .fl{font-size:.68rem;font-weight:500;color:var(--text3);letter-spacing:.03em}
  .fc{width:100%;padding:.55rem .8rem;background:rgba(82,183,136,.04);
    border:1px solid var(--border2);border-radius:var(--r2);
    color:var(--text);font-family:var(--sans);font-size:.82rem;
    outline:none;transition:all var(--t);appearance:none}
  .fc:focus{border-color:var(--leaf);background:rgba(82,183,136,.06);
    box-shadow:0 0 0 3px rgba(82,183,136,.08)}
  .fc::placeholder{color:var(--text3)}
  .fc option{background:var(--ink2)}
  .fhint{font-size:.65rem;color:var(--muted)}

  /* ══ TOGGLES ════════════════════════════════════════════════ */
  .toggles{border:1px solid var(--border2);border-radius:var(--r2);overflow:hidden;margin-top:1rem}
  .toggle-row{display:flex;align-items:center;justify-content:space-between;
    padding:.8rem 1rem;gap:1rem;border-bottom:1px solid var(--border2);
    transition:background var(--t)}
  .toggle-row:last-child{border-bottom:none}
  .toggle-row:hover{background:rgba(82,183,136,.03)}
  .tg-lbl{font-size:.82rem;font-weight:500;color:var(--text);line-height:1.2}
  .tg-hint{font-size:.67rem;color:var(--text3);margin-top:.08rem}

  /* Switch */
  .sw{position:relative;width:38px;height:21px;flex-shrink:0;cursor:pointer;display:inline-block}
  .sw input{position:absolute;opacity:0;width:0;height:0}
  .sw-track{position:absolute;inset:0;border-radius:10.5px;background:rgba(255,255,255,.07);
    transition:background .28s;border:1px solid var(--border)}
  .sw input:checked ~ .sw-track{background:var(--leaf);border-color:var(--leaf)}
  .sw-thumb{position:absolute;top:3px;left:3px;width:15px;height:15px;border-radius:50%;
    background:#fff;transition:transform .28s;box-shadow:0 1px 3px rgba(0,0,0,.35);pointer-events:none}
  .sw input:checked ~ .sw-thumb{transform:translateX(17px)}

  /* ══ SAVE ROW ═══════════════════════════════════════════════ */
  .save-row{margin-top:1.1rem;padding-top:1rem;border-top:1px solid var(--border2);
    display:flex;align-items:center;gap:.8rem}
  .btn-save{padding:.5rem 1.3rem;background:linear-gradient(135deg,var(--leaf),#2d6a4f);
    border:none;border-radius:var(--r2);color:#fff;font-family:var(--sans);
    font-size:.78rem;font-weight:600;cursor:pointer;transition:all var(--t);
    display:inline-flex;align-items:center;gap:.35rem}
  .btn-save:hover:not(:disabled){transform:translateY(-1px);filter:brightness(1.1)}
  .btn-save:disabled{opacity:.4;cursor:not-allowed;transform:none}
  .btn-save.danger{background:linear-gradient(135deg,#dc2626,#b91c1c)}
  .save-ok{font-size:.72rem;color:var(--leaf2);font-weight:500;opacity:0;transition:opacity .3s}
  .save-ok.show{opacity:1}

  /* ══ PASSWORD FIELD ══════════════════════════════════════════ */
  .inp-wrap{position:relative}
  .inp-wrap .fc{padding-right:2.4rem}
  .eye-btn{position:absolute;right:.65rem;top:50%;transform:translateY(-50%);
    background:none;border:none;cursor:pointer;color:var(--text3);padding:.2rem;
    transition:color var(--t);line-height:0}
  .eye-btn:hover{color:var(--leaf2)}
  .eye-btn svg{width:14px;height:14px}
  .str-bar{height:3px;background:rgba(255,255,255,.05);border-radius:2px;overflow:hidden;margin-top:.3rem}
  .str-fill{height:100%;border-radius:2px;width:0;transition:width .3s,background .3s}
  .str-lbl{font-size:.65rem;color:var(--muted);margin-top:.15rem;min-height:.85rem}

  /* ══ INFO / WARN BOXES ═══════════════════════════════════════ */
  .info-box{display:flex;align-items:flex-start;gap:.5rem;
    background:rgba(82,183,136,.05);border:1px solid var(--border);
    border-radius:var(--r2);padding:.65rem .9rem;margin-bottom:1rem;
    font-size:.76rem;color:var(--text3);line-height:1.65}
  .warn-box{display:flex;align-items:flex-start;gap:.5rem;
    background:rgba(224,82,82,.04);border:1px solid rgba(224,82,82,.14);
    border-radius:var(--r2);padding:.65rem .9rem;margin-bottom:1rem;
    font-size:.76rem;color:rgba(224,130,130,.7);line-height:1.65}
  .box-ico{font-size:.9rem;flex-shrink:0;margin-top:.05rem}

  /* ══ SYSTEM INFO PILLS ═══════════════════════════════════════ */
  .sys-info{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem}
  .sys-pill{padding:.28rem .65rem;background:rgba(82,183,136,.05);
    border:1px solid var(--border2);border-radius:20px;
    font-size:.65rem;color:var(--text3);display:flex;align-items:center;gap:.3rem}
  .sys-pill strong{color:var(--leaf2)}

  /* ══ TOAST ═══════════════════════════════════════════════════ */
  #toasts{position:fixed;bottom:1rem;right:1rem;z-index:999;
    display:flex;flex-direction:column;gap:.35rem;pointer-events:none}
  .toast{background:var(--ink2);color:var(--text);border:1px solid var(--border);
    border-radius:var(--r2);padding:.5rem .85rem;font-size:.72rem;
    display:flex;align-items:center;gap:.4rem;animation:slideIn .2s var(--t) both;
    max-width:280px;pointer-events:all;box-shadow:var(--sh2)}
  .toast.ok  {border-left:2px solid var(--leaf)}
  .toast.err {border-left:2px solid var(--red)}
  .toast.warn{border-left:2px solid var(--gold)}
  @keyframes slideIn{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:none}}

  /* ══ RESPONSIVE ══════════════════════════════════════════════ */
  @media(max-width:960px){.fgrid.g3{grid-template-columns:1fr 1fr}}
  @media(max-width:700px){.fgrid,.fgrid.g3{grid-template-columns:1fr}}
  @media(max-width:768px){.main{margin-left:0}.page-wrap{padding:1rem 1rem 2.5rem}}
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="aside">
  <div class="a-logo">
    <div class="a-logo-mark">🌱</div>
    <div>
      <div class="a-logo-name">florescer</div>
      <div class="a-logo-tag">admin</div>
    </div>
  </div>
  <div class="a-who">
    <div class="a-av"><?= $adminLetter ?></div>
    <div>
      <div class="a-name"><?= htmlspecialchars($adminName, ENT_QUOTES) ?></div>
      <div class="a-role">Administrador</div>
    </div>
  </div>
  <nav class="a-nav">
    <span class="a-grp">Visão geral</span>
    <a class="a-link" href="dashboard.php"><span class="a-link-ico">◈</span>Dashboard</a>
    <span class="a-grp">Usuários</span>
    <a class="a-link" href="usuarios.php"><span class="a-link-ico">⊙</span>Usuários</a>
    <a class="a-link" href="sessoes.php"><span class="a-link-ico">⊘</span>Sessões</a>
    <span class="a-grp">Conteúdo</span>
    <a class="a-link" href="mensagens.php"><span class="a-link-ico">⊡</span>Frases do dia</a>
    <a class="a-link" href="simulados.php"><span class="a-link-ico">⊞</span>Simulados</a>
    <a class="a-link" href="cursos.php"><span class="a-link-ico">⊟</span>Cursos</a>
    <span class="a-grp">Sistema</span>
    <a class="a-link" href="feedbacks.php">
      <span class="a-link-ico">⊠</span>Feedbacks
      <?php if ($openFeedbacks > 0): ?>
        <span class="nav-badge"><?= $openFeedbacks ?></span>
      <?php endif; ?>
    </a>
    <a class="a-link active" href="configuracoes.php"><span class="a-link-ico">⊛</span>Configurações</a>
    <a class="a-link" href="/florescer/public/views/dashboard.php" target="_blank"><span class="a-link-ico">↗</span>Ver plataforma</a>
  </nav>
  <div class="a-foot">
    <button class="a-logout" onclick="doLogout()"><span>↩</span> Sair</button>
  </div>
</aside>

<!-- MAIN -->
<div class="main">

  <header class="topbar">
    <div class="tb-left">
      <span class="tb-title">Configurações</span>
      <span class="tb-sub"><?= date('d/m/Y · H:i') ?></span>
    </div>
    <div class="tb-right">
      <?php if ($failedLogins > 0): ?>
        <span class="pill" style="background:rgba(224,82,82,.07);border-color:rgba(224,82,82,.15);color:#e08080">
          <span style="width:5px;height:5px;border-radius:50%;background:#e05252;flex-shrink:0"></span>
          <?= $failedLogins ?> tentativas falhas
        </span>
      <?php endif; ?>
      <span class="pill"><span class="pill-dot"></span>online</span>
    </div>
  </header>

  <div class="page-wrap">
    <div class="page-inner">

      <?php if (!$tableOk): ?>
      <div class="warn-box">
        <span class="box-ico">⚠️</span>
        A tabela <code>system_config</code> não foi encontrada. Execute o SQL de instalação e recarregue.
      </div>
      <?php endif; ?>

      <!-- ══ INFO DO SISTEMA ══ -->
      <div class="sys-info">
        <span class="sys-pill">🗄️ MariaDB <strong><?= htmlspecialchars($dbVersion, ENT_QUOTES) ?></strong></span>
        <span class="sys-pill">🐘 PHP <strong><?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?></strong></span>
        <span class="sys-pill">⚙️ Versão <strong><?= cv($cfg, 'app_version', '1.0.0') ?></strong></span>
        <span class="sys-pill">🕒 Servidor <strong><?= date('d/m H:i') ?></strong></span>
      </div>

      <!-- ══ 1. PLATAFORMA ══ -->
      <div class="sec">
        <div class="sec-head">
          <div class="sec-ico">🌱</div>
          <div>
            <div class="sec-title">Plataforma</div>
            <div class="sec-sub">Nome, slogan e informações gerais de contato</div>
          </div>
        </div>
        <div class="sec-body">
          <div class="fgrid">
            <div class="fg">
              <label class="fl">Nome da plataforma</label>
              <input class="fc" type="text" id="cfg_app_name"
                     value="<?= cv($cfg, 'app_name', 'florescer') ?>" maxlength="80"/>
            </div>
            <div class="fg">
              <label class="fl">Versão</label>
              <input class="fc" type="text" id="cfg_app_version"
                     value="<?= cv($cfg, 'app_version', '1.0.0') ?>" maxlength="20"
                     placeholder="1.0.0"/>
            </div>
            <div class="fg span2">
              <label class="fl">Slogan</label>
              <input class="fc" type="text" id="cfg_app_tagline"
                     value="<?= cv($cfg, 'app_tagline', 'Estude com consistência') ?>" maxlength="150"/>
            </div>
            <div class="fg">
              <label class="fl">E-mail de contato</label>
              <input class="fc" type="email" id="cfg_contact_email"
                     value="<?= cv($cfg, 'contact_email') ?>" maxlength="150"
                     placeholder="contato@florescer.app"/>
            </div>
            <div class="fg">
              <label class="fl">URL da plataforma</label>
              <input class="fc" type="url" id="cfg_app_url"
                     value="<?= cv($cfg, 'app_url') ?>" maxlength="200"
                     placeholder="https://florescer.app"/>
            </div>
          </div>
          <div class="save-row">
            <button class="btn-save"
                    onclick="save(this,'plataforma',['app_name','app_version','app_tagline','contact_email','app_url'])">
              💾 Salvar
            </button>
            <span class="save-ok" id="st_plataforma">✓ Salvo</span>
          </div>
        </div>
      </div>

      <!-- ══ 2. PIX ══ -->
      <div class="sec">
        <div class="sec-head">
          <div class="sec-ico">💚</div>
          <div>
            <div class="sec-title">Chave Pix</div>
            <div class="sec-sub">Exibida na página de apoio da plataforma</div>
          </div>
        </div>
        <div class="sec-body">
          <div class="fgrid">
            <div class="fg">
              <label class="fl">Tipo de chave</label>
              <select class="fc" id="cfg_pix_type">
                <?php foreach ([
                  'email'    => 'E-mail',
                  'cpf'      => 'CPF',
                  'cnpj'     => 'CNPJ',
                  'telefone' => 'Telefone',
                  'aleatoria'=> 'Chave aleatória',
                ] as $v => $l): ?>
                  <option value="<?= $v ?>" <?= cv($cfg,'pix_type','email')===$v?'selected':'' ?>>
                    <?= $l ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="fg">
              <label class="fl">Chave Pix</label>
              <input class="fc" type="text" id="cfg_pix_key"
                     value="<?= cv($cfg, 'pix_key') ?>" maxlength="200"
                     placeholder="sua@chave.com ou UUID"/>
            </div>
            <div class="fg span2">
              <label class="fl">Nome do favorecido</label>
              <input class="fc" type="text" id="cfg_pix_name"
                     value="<?= cv($cfg, 'pix_name') ?>" maxlength="100"
                     placeholder="Nome completo"/>
            </div>
          </div>
          <div class="save-row">
            <button class="btn-save"
                    onclick="save(this,'pix',['pix_type','pix_key','pix_name'])">
              💾 Salvar
            </button>
            <span class="save-ok" id="st_pix">✓ Salvo</span>
          </div>
        </div>
      </div>

      <!-- ══ 3. METAS E GAMIFICAÇÃO ══ -->
      <div class="sec">
        <div class="sec-head">
          <div class="sec-ico">🎯</div>
          <div>
            <div class="sec-title">Metas e Gamificação</div>
            <div class="sec-sub">XP, streaks e parâmetros padrão para novos alunos</div>
          </div>
        </div>
        <div class="sec-body">
          <div class="fgrid g3">
            <div class="fg">
              <label class="fl">Meta diária padrão (min)</label>
              <input class="fc" type="number" id="cfg_default_goal_min"
                     value="<?= cv($cfg, 'default_goal_min', '30') ?>" min="5" max="480"/>
              <span class="fhint">Para novos usuários</span>
            </div>
            <div class="fg">
              <label class="fl">Chances de regar (streak)</label>
              <input class="fc" type="number" id="cfg_water_chances"
                     value="<?= cv($cfg, 'water_chances', '3') ?>" min="1" max="10"/>
              <span class="fhint">Antes de zerar o streak</span>
            </div>
            <div class="fg">
              <label class="fl">Unidades por matéria</label>
              <input class="fc" type="number" id="cfg_default_units"
                     value="<?= cv($cfg, 'default_units', '4') ?>" min="1" max="20"/>
              <span class="fhint">UND1, UND2… padrão</span>
            </div>
            <div class="fg">
              <label class="fl">XP por aula concluída</label>
              <input class="fc" type="number" id="cfg_xp_per_lesson"
                     value="<?= cv($cfg, 'xp_per_lesson', '10') ?>" min="1" max="1000"/>
            </div>
            <div class="fg">
              <label class="fl">XP por meta diária</label>
              <input class="fc" type="number" id="cfg_xp_per_goal"
                     value="<?= cv($cfg, 'xp_per_goal', '50') ?>" min="1" max="1000"/>
            </div>
            <div class="fg">
              <label class="fl">XP por dia de streak</label>
              <input class="fc" type="number" id="cfg_xp_per_streak_day"
                     value="<?= cv($cfg, 'xp_per_streak_day', '5') ?>" min="0" max="500"/>
            </div>
          </div>
          <div class="save-row">
            <button class="btn-save"
                    onclick="save(this,'gamificacao',['default_goal_min','water_chances','default_units','xp_per_lesson','xp_per_goal','xp_per_streak_day'])">
              💾 Salvar
            </button>
            <span class="save-ok" id="st_gamificacao">✓ Salvo</span>
          </div>
        </div>
      </div>

      <!-- ══ 4. CHAT ══ -->
      <div class="sec">
        <div class="sec-head">
          <div class="sec-ico">💬</div>
          <div>
            <div class="sec-title">Chat da Comunidade</div>
            <div class="sec-sub">Controle de mensagens e moderação</div>
          </div>
        </div>
        <div class="sec-body">
          <div class="fgrid" style="margin-bottom:.85rem">
            <div class="fg">
              <label class="fl">Intervalo de polling (seg)</label>
              <input class="fc" type="number" id="cfg_chat_poll_interval"
                     value="<?= cv($cfg, 'chat_poll_interval', '3') ?>" min="1" max="60"/>
              <span class="fhint">Frequência de atualização automática</span>
            </div>
            <div class="fg">
              <label class="fl">Máx. mensagens exibidas</label>
              <input class="fc" type="number" id="cfg_chat_max_messages"
                     value="<?= cv($cfg, 'chat_max_messages', '100') ?>" min="10" max="500"/>
            </div>
            <div class="fg">
              <label class="fl">Máx. caracteres por mensagem</label>
              <input class="fc" type="number" id="cfg_chat_max_chars"
                     value="<?= cv($cfg, 'chat_max_chars', '300') ?>" min="50" max="2000"/>
            </div>
            <div class="fg">
              <label class="fl">Cooldown entre mensagens (seg)</label>
              <input class="fc" type="number" id="cfg_chat_cooldown"
                     value="<?= cv($cfg, 'chat_cooldown', '5') ?>" min="0" max="300"/>
              <span class="fhint">0 = sem limite</span>
            </div>
          </div>
          <div class="toggles">
            <div class="toggle-row">
              <div>
                <div class="tg-lbl">Chat habilitado</div>
                <div class="tg-hint">Exibe ou oculta o chat para todos os alunos</div>
              </div>
              <label class="sw">
                <input type="checkbox" id="cfg_chat_enabled" <?= ck($cfg,'chat_enabled')?'checked':'' ?>>
                <span class="sw-track"></span><span class="sw-thumb"></span>
              </label>
            </div>
            <div class="toggle-row">
              <div>
                <div class="tg-lbl">Moderação manual</div>
                <div class="tg-hint">Mensagens ficam pendentes até aprovação do admin</div>
              </div>
              <label class="sw">
                <input type="checkbox" id="cfg_chat_moderation" <?= ck($cfg,'chat_moderation')?'checked':'' ?>>
                <span class="sw-track"></span><span class="sw-thumb"></span>
              </label>
            </div>
          </div>
          <div class="save-row">
            <button class="btn-save"
                    onclick="save(this,'chat',['chat_poll_interval','chat_max_messages','chat_max_chars','chat_cooldown','chat_enabled','chat_moderation'])">
              💾 Salvar
            </button>
            <span class="save-ok" id="st_chat">✓ Salvo</span>
          </div>
        </div>
      </div>

      <!-- ══ 5. SIMULADOS ══ -->
      <div class="sec">
        <div class="sec-head">
          <div class="sec-ico">🧠</div>
          <div>
            <div class="sec-title">Simulados</div>
            <div class="sec-sub">Regras, tempo, pontuação e comportamento dos testes</div>
          </div>
        </div>
        <div class="sec-body">
          <div class="fgrid" style="margin-bottom:.85rem">
            <div class="fg">
              <label class="fl">Questões por simulado</label>
              <input class="fc" type="number" id="cfg_sim_questions_count"
                     value="<?= cv($cfg, 'sim_questions_count', '10') ?>" min="5" max="200"/>
            </div>
            <div class="fg">
              <label class="fl">Tempo máximo (min)</label>
              <input class="fc" type="number" id="cfg_sim_time_limit"
                     value="<?= cv($cfg, 'sim_time_limit', '30') ?>" min="0" max="360"/>
              <span class="fhint">0 = sem limite de tempo</span>
            </div>
            <div class="fg">
              <label class="fl">XP por simulado completo</label>
              <input class="fc" type="number" id="cfg_sim_xp_reward"
                     value="<?= cv($cfg, 'sim_xp_reward', '100') ?>" min="0" max="5000"/>
            </div>
            <div class="fg">
              <label class="fl">Nota de aprovação (%)</label>
              <input class="fc" type="number" id="cfg_sim_pass_score"
                     value="<?= cv($cfg, 'sim_pass_score', '60') ?>" min="1" max="100"/>
            </div>
          </div>
          <div class="toggles">
            <div class="toggle-row">
              <div>
                <div class="tg-lbl">Anti-cola ativo</div>
                <div class="tg-hint">Alerta quando o aluno troca de aba durante o simulado</div>
              </div>
              <label class="sw">
                <input type="checkbox" id="cfg_sim_anticheating" <?= ck($cfg,'sim_anticheating')?'checked':'' ?>>
                <span class="sw-track"></span><span class="sw-thumb"></span>
              </label>
            </div>
            <div class="toggle-row">
              <div>
                <div class="tg-lbl">Mostrar gabarito ao finalizar</div>
                <div class="tg-hint">Exibe questões erradas e explicações imediatamente</div>
              </div>
              <label class="sw">
                <input type="checkbox" id="cfg_sim_show_answers" <?= ck($cfg,'sim_show_answers')?'checked':'' ?>>
                <span class="sw-track"></span><span class="sw-thumb"></span>
              </label>
            </div>
          </div>
          <div class="save-row">
            <button class="btn-save"
                    onclick="save(this,'simulados',['sim_questions_count','sim_time_limit','sim_xp_reward','sim_pass_score','sim_anticheating','sim_show_answers'])">
              💾 Salvar
            </button>
            <span class="save-ok" id="st_simulados">✓ Salvo</span>
          </div>
        </div>
      </div>

      <!-- ══ 6. CONTA DO ADMIN ══ -->
      <div class="sec">
        <div class="sec-head">
          <div class="sec-ico">🔐</div>
          <div>
            <div class="sec-title">Conta do Admin</div>
            <div class="sec-sub"><?= htmlspecialchars($adminEmail, ENT_QUOTES) ?></div>
          </div>
        </div>
        <div class="sec-body">
          <div class="info-box">
            <span class="box-ico">ℹ️</span>
            A senha atual é necessária para confirmar qualquer alteração de credenciais.
          </div>
          <div class="fgrid">
            <div class="fg">
              <label class="fl">Senha atual</label>
              <div class="inp-wrap">
                <input class="fc" type="password" id="currPass" placeholder="••••••••" autocomplete="current-password"/>
                <button type="button" class="eye-btn" onclick="eye('currPass',this)" tabindex="-1">
                  <svg id="eye-currPass" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                  </svg>
                </button>
              </div>
            </div>
            <div class="fg">
              <label class="fl">Nova senha</label>
              <div class="inp-wrap">
                <input class="fc" type="password" id="newPass" placeholder="Mín. 6 caracteres"
                       autocomplete="new-password" oninput="strength(this.value)"/>
                <button type="button" class="eye-btn" onclick="eye('newPass',this)" tabindex="-1">
                  <svg id="eye-newPass" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                  </svg>
                </button>
              </div>
              <div class="str-bar"><div class="str-fill" id="strFill"></div></div>
              <div class="str-lbl" id="strLbl"></div>
            </div>
            <div class="fg">
              <label class="fl">Confirmar nova senha</label>
              <div class="inp-wrap">
                <input class="fc" type="password" id="confPass" placeholder="Repita a nova senha"
                       autocomplete="new-password"/>
                <button type="button" class="eye-btn" onclick="eye('confPass',this)" tabindex="-1">
                  <svg id="eye-confPass" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                  </svg>
                </button>
              </div>
            </div>
            <div class="fg" style="align-self:flex-end">
              <button class="btn-save" style="width:100%;justify-content:center"
                      onclick="changePass(this)">🔑 Alterar senha</button>
            </div>
          </div>
        </div>

        <!-- Cole logo após o </div> que fecha .fgrid da senha -->
<div style="margin-top:1.1rem;padding-top:1rem;border-top:1px solid var(--border2)">
  <div class="fgrid">
    <div class="fg">
      <label class="fl">Novo e-mail</label>
      <input class="fc" type="email" id="newEmail"
             placeholder="novo@email.com" maxlength="150" autocomplete="email"/>
    </div>
    <div class="fg">
      <label class="fl">Confirmar com senha atual</label>
      <div class="inp-wrap">
        <input class="fc" type="password" id="passForEmail"
               placeholder="Senha atual para confirmar" autocomplete="current-password"/>
        <button type="button" class="eye-btn" onclick="eye('passForEmail',this)" tabindex="-1">
          <svg id="eye-passForEmail" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
          </svg>
        </button>
      </div>
    </div>
    <div class="fg" style="align-self:flex-end">
      <button class="btn-save" style="width:100%;justify-content:center"
              onclick="changeEmail(this)">✉️ Alterar e-mail</button>
    </div>
  </div>
</div>
      </div>

      <!-- ══ 7. ZONA DE PERIGO ══ -->
      <div class="sec" style="border-color:rgba(224,82,82,.14)">
        <div class="sec-head" style="border-color:rgba(224,82,82,.1);background:rgba(224,82,82,.03)">
          <div class="sec-ico" style="background:rgba(224,82,82,.08);border-color:rgba(224,82,82,.18)">⚠️</div>
          <div>
            <div class="sec-title" style="color:rgba(240,130,130,.85)">Zona de Perigo</div>
            <div class="sec-sub" style="color:rgba(224,82,82,.4)">Ações que afetam todos os usuários da plataforma</div>
          </div>
        </div>
        <div class="sec-body">
          <div class="warn-box">
            <span class="box-ico">⚠️</span>
            O modo manutenção exibe uma tela de aviso para os alunos e bloqueia novos acessos.
            O painel admin continua funcionando normalmente.
          </div>
          <div class="toggles">
            <div class="toggle-row">
              <div>
                <div class="tg-lbl" style="color:rgba(240,130,130,.8)">Modo manutenção</div>
                <div class="tg-hint">Bloqueia o acesso dos alunos à plataforma temporariamente</div>
              </div>
              <label class="sw">
                <input type="checkbox" id="cfg_maintenance_mode"
                       <?= (ck($cfg,'maintenance_mode') || ck($cfg,'maintenance'))?'checked':'' ?>>
                <span class="sw-track" style="--active-bg:var(--red)"></span>
                <span class="sw-thumb"></span>
              </label>
            </div>
          </div>
          <div class="save-row">
            <button class="btn-save danger"
                    onclick="save(this,'manutencao',['maintenance_mode','maintenance'])">
              💾 Salvar modo manutenção
            </button>
            <span class="save-ok" id="st_manutencao">✓ Salvo</span>
          </div>
        </div>
      </div>

    </div><!-- /page-inner -->
  </div><!-- /page-wrap -->
</div><!-- /main -->

<div id="toasts"></div>

<script>
const API = '/florescer/admin/api/configuracoes.php';

// ── Toast ────────────────────────────────────────────────────
function toast(msg, type = 'ok', ms = 3800) {
  const wrap = document.getElementById('toasts');
  const el   = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `<span>${type==='ok'?'✓':type==='err'?'✕':'!'}</span><span>${msg}</span>`;
  wrap.appendChild(el);
  setTimeout(() => {
    el.style.opacity='0'; el.style.transition='.25s';
    setTimeout(() => el.remove(), 260);
  }, ms);
}

// ── API call genérica ────────────────────────────────────────
async function apiCall(body) {
  const r = await fetch(API, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify(body),
  });
  if (!r.ok) throw new Error('HTTP ' + r.status);
  return r.json();
}

// ── Salva seção ──────────────────────────────────────────────
async function save(btn, section, keys) {
  btn.disabled = true;
  const data = { action: 'save_config', section };

  keys.forEach(k => {
    const el = document.getElementById('cfg_' + k);
    if (!el) return;
    // Para toggles de manutenção duplicados (maintenance + maintenance_mode)
    data[k] = el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value.trim();
  });

  try {
    const d = await apiCall(data);
    if (d.success) {
      toast('Configurações salvas!');
      const st = document.getElementById('st_' + section);
      if (st) { st.classList.add('show'); setTimeout(() => st.classList.remove('show'), 2800); }
    } else {
      toast(d.message || 'Erro ao salvar.', 'err');
    }
  } catch (e) {
    toast('Erro de conexão: ' + e.message, 'err');
  } finally {
    btn.disabled = false;
  }
}

// ── Altera senha ─────────────────────────────────────────────
async function changePass(btn) {
  const curr = document.getElementById('currPass').value;
  const nw   = document.getElementById('newPass').value;
  const conf = document.getElementById('confPass').value;

  if (!curr)          { toast('Informe a senha atual.', 'err'); return; }
  if (nw.length < 6)  { toast('Nova senha: mínimo 6 caracteres.', 'err'); return; }
  if (nw !== conf)    { toast('As senhas não coincidem.', 'err'); return; }

  btn.disabled = true;
  try {
    const d = await apiCall({ action: 'change_admin_pass', current: curr, newpass: nw });
    if (d.success) {
      toast('Senha alterada com sucesso! 🔐');
      ['currPass','newPass','confPass'].forEach(id => document.getElementById(id).value = '');
      document.getElementById('strFill').style.width = '0';
      document.getElementById('strLbl').textContent  = '';
    } else {
      toast(d.message || 'Senha atual incorreta.', 'err');
    }
  } catch (e) {
    toast('Erro de conexão: ' + e.message, 'err');
  } finally {
    btn.disabled = false;
  }
}

// ── Show/hide password ───────────────────────────────────────
const eyeOpen   = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
const eyeClosed = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`;
function eye(id, btn) {
  const inp = document.getElementById(id);
  const svg = document.getElementById('eye-' + id);
  const show = inp.type === 'password';
  inp.type      = show ? 'text' : 'password';
  svg.innerHTML = show ? eyeClosed : eyeOpen;
  btn.style.color = show ? 'var(--leaf2)' : 'var(--text3)';
}

// ── Força da senha ───────────────────────────────────────────
function strength(v) {
  let s = 0;
  if (v.length >= 6)             s++;
  if (v.length >= 10)            s++;
  if (/\d/.test(v))              s++;
  if (/[^a-zA-Z0-9]/.test(v))   s++;
  if (/[A-Z]/.test(v))          s++;
  const map = [
    [0, '0%',   'transparent', ''],
    [1, '20%',  '#f87171',     'Muito fraca'],
    [2, '40%',  '#f59e0b',     'Fraca'],
    [3, '65%',  'var(--leaf)', 'Boa'],
    [4, '85%',  'var(--leaf)', 'Forte'],
    [5, '100%', 'var(--leaf2)','Muito forte'],
  ];
  const [, w, c, l] = map[Math.min(s, 5)];
  const f  = document.getElementById('strFill');
  const lb = document.getElementById('strLbl');
  f.style.width  = w;
  f.style.background = c;
  lb.textContent = l;
  lb.style.color = c;
}

// email 
async function changeEmail(btn) {
  const newEmail = document.getElementById('newEmail').value.trim();
  const pass     = document.getElementById('passForEmail').value;

  if (!newEmail)  { toast('Informe o novo e-mail.', 'err'); return; }
  if (!pass)      { toast('Informe a senha atual para confirmar.', 'err'); return; }
  // Validação simples no front
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail)) {
    toast('E-mail inválido.', 'err'); return;
  }

  btn.disabled = true;
  try {
    const d = await apiCall({ action: 'change_admin_email', newemail: newEmail, current: pass });
    if (d.success) {
      toast('E-mail alterado! Faça login novamente.', 'ok', 5000);
      document.getElementById('newEmail').value     = '';
      document.getElementById('passForEmail').value = '';
      // Atualiza o subtítulo visível
      document.querySelector('.sec-sub') && (document.querySelector('[data-admin-email]').textContent = newEmail);
      setTimeout(() => { window.location.href = '/florescer/index.php'; }, 3000);
    } else {
      toast(d.message || 'Erro ao alterar e-mail.', 'err');
    }
  } catch (e) {
    toast('Erro de conexão.', 'err');
  } finally {
    btn.disabled = false;
  }
}

// ── Logout ───────────────────────────────────────────────────
function doLogout() {
  fetch('../api/auth_admin.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ action: 'logout' }),
  }).finally(() => { window.location.href = '/florescer/index.php'; });
}

// ── Relogio topbar ───────────────────────────────────────────
(function () {
  const el = document.querySelector('.tb-sub');
  if (!el) return;
  setInterval(() => {
    const n = new Date();
    const d = n.toLocaleDateString('pt-BR');
    const t = n.getHours().toString().padStart(2,'0') + ':' + n.getMinutes().toString().padStart(2,'0');
    el.textContent = d + ' · ' + t;
  }, 30000);
})();
</script>
</body>
</html>