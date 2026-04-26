<?php
// ============================================================
// /admin/index.php — florescer Admin v3.0
// Página de login com segurança de produção (OWASP Top 10)
// ============================================================
require_once __DIR__ . '/includes/auth_admin.php';

adminStartSession();

// Se já está logado, vai para o dashboard
if (isAdminLoggedIn()) {
    header('Location: views/dashboard.php');
    exit;
}

// ── Security Headers ─────────────────────────────────────────
// Deve ser definido ANTES de qualquer output

// Nonce CSP único por requisição (base64url seguro)
$cspNonce = base64_encode(random_bytes(18));

header("Content-Security-Policy: "
    . "default-src 'none'; "
    . "script-src 'nonce-{$cspNonce}'; "
    . "style-src 'nonce-{$cspNonce}' https://fonts.googleapis.com; "
    . "font-src https://fonts.gstatic.com; "
    . "connect-src 'none'; "
    . "img-src 'self' data:; "
    . "form-action 'self'; "
    . "base-uri 'none'; "
    . "frame-ancestors 'none';"
);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 0');           // Desabilitado — CSP é superior
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');

// Remove informações do servidor
header_remove('X-Powered-By');
header_remove('Server');

// ── Estado da página ─────────────────────────────────────────
// Etapas: 'login' → 'two_factor'
$step  = 'login';
$error = '';

// Verifica se está aguardando 2FA
if (!empty($_SESSION['admin_pending_id'])) {
    $step = 'two_factor';
}

// ── Processa POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Validação CSRF ────────────────────────────────────────
    $csrfOk = !empty($_POST['csrf_token'])
           && !empty($_SESSION['admin_csrf'])
           && hash_equals($_SESSION['admin_csrf'], $_POST['csrf_token']);

    if (!$csrfOk) {
        // Invalida token após uso
        unset($_SESSION['admin_csrf']);
        $error = 'Requisição inválida. Recarregue a página.';
    } else {
        // Token de uso único
        unset($_SESSION['admin_csrf']);

        $action = $_POST['action'] ?? 'login';

        // ── Etapa 1: e-mail + senha ───────────────────────────
        if ($action === 'login') {
            // Sanitização de entrada
            $email    = filter_input(INPUT_POST, 'email',    FILTER_SANITIZE_EMAIL)  ?? '';
            $password = $_POST['password'] ?? '';  // Não sanitizar — verificar hash

            $result = adminLogin($email, $password);

            if ($result['success']) {
                if ($result['need_2fa']) {
                    $step = 'two_factor';
                } else {
                    header('Location: views/dashboard.php');
                    exit;
                }
            } else {
                $error = $result['message'];
            }
        }

        // ── Etapa 2: TOTP ─────────────────────────────────────
        elseif ($action === 'two_factor') {
            $code = preg_replace('/\D/', '', $_POST['totp_code'] ?? '');

            $result = adminVerify2FA($code);

            if ($result['success']) {
                header('Location: views/dashboard.php');
                exit;
            } else {
                $error = $result['message'];
                $step  = 'two_factor';
            }
        }
    }
}

// ── Gera novo token CSRF (uso único) ─────────────────────────
$_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['admin_csrf'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <!--
    Nunca expõe informações internas em meta tags.
    Sem generator, sem framework info.
  -->
  <title>florescer — Admin</title>
  <meta name="robots" content="noindex, nofollow"/>

  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link
    href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700;9..144,900&family=Inter:wght@400;500;600&display=swap"
    rel="stylesheet"
    nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES) ?>"
  />

  <style nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES) ?>">
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{
    --g950:#0d1f16;--g900:#132a1e;--g800:#1a3a2a;
    --g500:#40916c;--g400:#52b788;--g300:#74c69d;--g200:#b7e4c7;
    --white:#fff;
    --red:#dc2626;
    --fd:'Fraunces',Georgia,serif;
    --fb:'Inter',system-ui,sans-serif;
    --d:.22s;--e:cubic-bezier(.4,0,.2,1);
  }
  html,body{
    height:100%;font-family:var(--fb);
    background:var(--g950);
    display:flex;align-items:center;justify-content:center;
    -webkit-font-smoothing:antialiased;
  }
  body::before{
    content:'';position:fixed;inset:0;
    background:
      radial-gradient(ellipse 80% 50% at 20% 20%,rgba(64,145,108,.07) 0%,transparent 60%),
      radial-gradient(ellipse 60% 40% at 80% 80%,rgba(64,145,108,.04) 0%,transparent 60%);
    pointer-events:none;
  }

  /* ── Card ──────────────────────────────────────────────── */
  .card{
    width:100%;max-width:380px;
    background:var(--g900);
    border:1px solid rgba(116,198,157,.1);
    border-radius:16px;
    padding:2.2rem 2rem;
    box-shadow:0 24px 64px rgba(0,0,0,.4),0 0 0 1px rgba(116,198,157,.05);
    position:relative;overflow:hidden;
    animation:cardIn .35s var(--e) both;
  }
  @keyframes cardIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
  .card::before{
    content:'';position:absolute;top:0;left:0;right:0;height:2px;
    background:linear-gradient(90deg,var(--g500),var(--g300),transparent);
    opacity:.6;
  }

  /* ── Logo ──────────────────────────────────────────────── */
  .logo{display:flex;align-items:center;gap:.6rem;margin-bottom:1.8rem}
  .logo-ico{
    width:36px;height:36px;border-radius:9px;
    background:linear-gradient(135deg,var(--g500),#2d6a4f);
    display:flex;align-items:center;justify-content:center;
    font-size:1.1rem;
    box-shadow:0 4px 12px rgba(64,145,108,.3);
  }
  .logo-name{font-family:var(--fd);font-size:1.1rem;font-weight:700;color:var(--g200);letter-spacing:-.02em;line-height:1}
  .logo-sub{font-size:.58rem;color:rgba(116,198,157,.3);text-transform:uppercase;letter-spacing:.12em;margin-top:.1rem}

  /* ── Títulos ───────────────────────────────────────────── */
  .card-title{font-family:var(--fd);font-size:1.3rem;font-weight:900;color:#d8f3dc;letter-spacing:-.03em;margin-bottom:.3rem}
  .card-sub{font-size:.78rem;color:rgba(116,198,157,.35);margin-bottom:1.6rem}

  /* ── Inputs ────────────────────────────────────────────── */
  .fg{margin-bottom:1rem}
  label{display:block;font-size:.72rem;font-weight:500;color:rgba(116,198,157,.45);letter-spacing:.03em;margin-bottom:.3rem}
  input[type=email],input[type=password],input[type=text]{
    width:100%;padding:.65rem .85rem;
    background:rgba(255,255,255,.04);
    border:1px solid rgba(116,198,157,.12);
    border-radius:8px;
    color:var(--g200);font-family:var(--fb);font-size:.88rem;
    outline:none;transition:all var(--d) var(--e);
  }
  input[type=email]::placeholder,
  input[type=password]::placeholder,
  input[type=text]::placeholder{color:rgba(116,198,157,.18)}
  input:focus{
    border-color:var(--g400);
    background:rgba(64,145,108,.07);
    box-shadow:0 0 0 3px rgba(64,145,108,.12);
  }
  input:-webkit-autofill{
    -webkit-box-shadow:0 0 0 1000px #132a1e inset;
    -webkit-text-fill-color:var(--g200);
    caret-color:var(--g200);
  }
  .inp-wrap{position:relative}
  .inp-wrap input{padding-right:2.8rem}
  .eye{
    position:absolute;right:.75rem;top:50%;transform:translateY(-50%);
    background:none;border:none;cursor:pointer;
    color:rgba(116,198,157,.25);padding:.2rem;
    transition:color var(--d) var(--e);
    display:flex;align-items:center;
  }
  .eye:hover{color:var(--g400)}
  .eye svg{width:15px;height:15px}

  /* ── TOTP ──────────────────────────────────────────────── */
  .totp-grid{
    display:flex;gap:.5rem;justify-content:center;
    margin-bottom:1.4rem;
  }
  .totp-grid input{
    width:44px;height:52px;text-align:center;
    font-size:1.4rem;font-weight:600;letter-spacing:0;
    padding:.3rem;
    background:rgba(255,255,255,.04);
    border:1px solid rgba(116,198,157,.15);
    border-radius:8px;
    color:var(--g200);
    caret-color:var(--g300);
    transition:all var(--d) var(--e);
    -moz-appearance:textfield;
  }
  .totp-grid input::-webkit-outer-spin-button,
  .totp-grid input::-webkit-inner-spin-button{-webkit-appearance:none}
  .totp-grid input:focus{
    border-color:var(--g400);
    background:rgba(64,145,108,.07);
    box-shadow:0 0 0 3px rgba(64,145,108,.12);
  }
  .totp-hint{font-size:.73rem;color:rgba(116,198,157,.35);text-align:center;margin-bottom:1rem}
  .back-link{
    display:block;text-align:center;margin-top:1rem;
    font-size:.73rem;color:rgba(116,198,157,.3);
    text-decoration:none;transition:color var(--d) var(--e);
  }
  .back-link:hover{color:var(--g300)}

  /* ── Erro ──────────────────────────────────────────────── */
  .error-box{
    display:flex;align-items:center;gap:.5rem;
    background:rgba(220,38,38,.08);
    border:1px solid rgba(220,38,38,.18);
    border-radius:8px;padding:.6rem .85rem;
    margin-bottom:1.1rem;
    font-size:.78rem;color:#f87171;
    animation:shake .35s var(--e);
  }
  @keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-5px)}75%{transform:translateX(5px)}}

  /* ── Botão ─────────────────────────────────────────────── */
  .btn{
    width:100%;padding:.72rem;margin-top:.3rem;
    background:linear-gradient(135deg,var(--g500),#2d6a4f);
    border:none;border-radius:8px;
    color:#fff;font-family:var(--fb);font-size:.88rem;font-weight:600;
    cursor:pointer;transition:all var(--d) var(--e);
    box-shadow:0 4px 14px rgba(64,145,108,.3);
    display:flex;align-items:center;justify-content:center;gap:.4rem;
    position:relative;
  }
  .btn:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(64,145,108,.4)}
  .btn:active{transform:translateY(0)}
  .btn:disabled{pointer-events:none;opacity:.6}
  .btn.loading::after{
    content:'';width:14px;height:14px;
    border:2px solid rgba(255,255,255,.3);border-top-color:#fff;
    border-radius:50%;
    animation:spin .6s linear infinite;
    position:absolute;right:.9rem;
  }
  @keyframes spin{to{transform:rotate(360deg)}}

  /* ── Footer ────────────────────────────────────────────── */
  .card-foot{
    margin-top:1.6rem;padding-top:1rem;
    border-top:1px solid rgba(116,198,157,.06);
    text-align:center;font-size:.68rem;
    color:rgba(116,198,157,.2);
  }
  </style>
</head>
<body>

<div class="card">
  <!-- Logo -->
  <div class="logo">
    <div class="logo-ico">🌱</div>
    <div class="logo-text">
      <div class="logo-name">florescer</div>
      <div class="logo-sub">painel admin</div>
    </div>
  </div>

  <!-- Erro -->
  <?php if ($error): ?>
    <div class="error-box" role="alert">
      <span aria-hidden="true">⚠️</span>
      <!-- htmlspecialchars evita XSS refletido -->
      <span><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
    </div>
  <?php endif; ?>

  <!-- ══════════ ETAPA 1: LOGIN ══════════════════════════════ -->
  <?php if ($step === 'login'): ?>
    <div class="card-title">Bem-vindo de volta</div>
    <div class="card-sub">Acesso restrito a administradores</div>

    <form method="POST" autocomplete="off" id="loginForm">
      <!-- Token CSRF de uso único -->
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"/>
      <input type="hidden" name="action" value="login"/>

      <div class="fg">
        <label for="email">E-mail</label>
        <input
          type="email"
          id="email"
          name="email"
          placeholder="E-mail"
          autocomplete="username"
          maxlength="254"
          required
          autofocus
        />
      </div>

      <div class="fg">
        <label for="password">Senha</label>
        <div class="inp-wrap">
          <input
            type="password"
            id="password"
            name="password"
            placeholder="••••••••"
            autocomplete="current-password"
            maxlength="1024"
            required
          />
          <button type="button" class="eye" id="eyeBtn" aria-label="Mostrar senha">
            <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn" id="submitBtn">
        <span id="btnText">Entrar no painel</span>
      </button>
    </form>

  <!-- ══════════ ETAPA 2: 2FA ════════════════════════════════ -->
  <?php elseif ($step === 'two_factor'): ?>
    <div class="card-title">Verificação em dois fatores</div>
    <div class="card-sub">Abra seu aplicativo autenticador</div>

    <p class="totp-hint">Digite o código de 6 dígitos gerado pelo seu app</p>

    <form method="POST" id="totpForm" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"/>
      <input type="hidden" name="action" value="two_factor"/>
      <!-- Campo oculto que recebe os 6 dígitos montados pelo JS -->
      <input type="hidden" name="totp_code" id="totpHidden"/>

      <!-- 6 campos visuais individuais -->
      <div class="totp-grid" role="group" aria-label="Código de verificação">
        <?php for ($i = 0; $i < 6; $i++): ?>
          <input
            type="number"
            class="totp-digit"
            inputmode="numeric"
            min="0" max="9"
            maxlength="1"
            aria-label="Dígito <?= $i + 1 ?>"
            <?= $i === 0 ? 'autofocus' : '' ?>
          />
        <?php endfor; ?>
      </div>

      <button type="submit" class="btn" id="totpSubmit" disabled>
        <span>Verificar código</span>
      </button>
    </form>

    <a href="<?= htmlspecialchars(ADMIN_LOGIN, ENT_QUOTES, 'UTF-8') ?>" class="back-link">
      ← Voltar ao login
    </a>
  <?php endif; ?>

  <div class="card-foot">
    Acesso monitorado · <?= (int)date('Y') ?> florescer
  </div>
</div>

<script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES) ?>">
// ── Mostrar/ocultar senha ───────────────────────────────────
const eyeOpen  = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
const eyeClose = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';

const eyeBtn = document.getElementById('eyeBtn');
if (eyeBtn) {
  eyeBtn.addEventListener('click', () => {
    const inp = document.getElementById('password');
    const ico = document.getElementById('eyeIcon');
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    ico.innerHTML = show ? eyeClose : eyeOpen;
    eyeBtn.setAttribute('aria-label', show ? 'Ocultar senha' : 'Mostrar senha');
  });
}

// ── Loading no submit ───────────────────────────────────────
const loginForm = document.getElementById('loginForm');
if (loginForm) {
  loginForm.addEventListener('submit', () => {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.classList.add('loading');
    document.getElementById('btnText').textContent = 'Entrando…';
  });
}

// ── TOTP: navegação entre dígitos ───────────────────────────
const digits      = document.querySelectorAll('.totp-digit');
const totpHidden  = document.getElementById('totpHidden');
const totpSubmit  = document.getElementById('totpSubmit');
const totpForm    = document.getElementById('totpForm');

function updateTotpHidden() {
  const code = [...digits].map(d => d.value).join('');
  totpHidden.value = code;
  totpSubmit.disabled = code.length !== 6;
}

digits.forEach((el, idx) => {
  // Força apenas 1 dígito numérico
  el.addEventListener('input', e => {
    const val = e.target.value.replace(/\D/g, '').slice(-1);
    e.target.value = val;
    if (val && idx < 5) digits[idx + 1].focus();
    updateTotpHidden();
  });

  el.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !el.value && idx > 0) {
      digits[idx - 1].focus();
      digits[idx - 1].value = '';
      updateTotpHidden();
    }
    // Avança com seta direita
    if (e.key === 'ArrowRight' && idx < 5) digits[idx + 1].focus();
    if (e.key === 'ArrowLeft'  && idx > 0) digits[idx - 1].focus();
  });

  // Suporte a colar (ex: cópia do autenticador)
  el.addEventListener('paste', e => {
    e.preventDefault();
    const pasted = (e.clipboardData || window.clipboardData)
      .getData('text').replace(/\D/g, '').slice(0, 6);
    [...pasted].forEach((ch, i) => {
      if (digits[i]) digits[i].value = ch;
    });
    const nextEmpty = [...digits].findIndex(d => !d.value);
    (nextEmpty !== -1 ? digits[nextEmpty] : digits[5]).focus();
    updateTotpHidden();
  });
});

// Loading no submit do TOTP
if (totpForm) {
  totpForm.addEventListener('submit', (e) => {
    if (totpHidden.value.length !== 6) {
      e.preventDefault(); return;
    }
    totpSubmit.disabled = true;
    totpSubmit.classList.add('loading');
  });
}
</script>
</body>
</html>