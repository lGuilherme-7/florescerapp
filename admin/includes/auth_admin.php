<?php
// ============================================================
// /admin/includes/auth_admin.php — florescer Admin v3.0
// Autenticação segura seguindo OWASP Top 10
// ============================================================

define('ADMIN_ROOT',       dirname(__DIR__));
define('PROJECT_ROOT',     dirname(dirname(__DIR__)));
define('DB_CONFIG',        PROJECT_ROOT . '/config/db.php');
define('LOG_PATH',         PROJECT_ROOT . '/logs/admin_auth.log');
define('ADMIN_LOGIN',      '/florescer/admin/index.php');
define('SESSION_LIFETIME', 3600);   // 1 hora

// ── Rate Limiting ────────────────────────────────────────────
define('MAX_ATTEMPTS',     5);      // tentativas antes de bloquear
define('LOCKOUT_BASE',     30);     // segundos de bloqueio base (dobra a cada falha extra)
define('MAX_LOCKOUT',      900);    // bloqueio máximo: 15 minutos

// ── 2FA ─────────────────────────────────────────────────────
define('TOTP_ISSUER',      'florescer Admin');
define('TOTP_DIGITS',      6);
define('TOTP_PERIOD',      30);     // segundos
define('TOTP_WINDOW',      1);      // janela de ±1 período (tolerância de clock)

// ============================================================
// SESSÃO SEGURA
// ============================================================
function adminStartSession(): void {
    if (session_status() !== PHP_SESSION_NONE) return;

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,          // bloqueio de JS
        'samesite' => 'Strict',      // proteção CSRF
    ]);
    session_name('florescer_adm');
    session_start();

    // Regenera ID periodicamente (a cada 10 min) para mitigar session fixation
    $now = time();
    if (!isset($_SESSION['_init_time'])) {
        $_SESSION['_init_time'] = $now;
    }
    if ($now - ($_SESSION['_last_regen'] ?? 0) > 600) {
        session_regenerate_id(true);
        $_SESSION['_last_regen'] = $now;
    }
}

// ============================================================
// VERIFICAÇÃO DE LOGIN
// ============================================================
function isAdminLoggedIn(): bool {
    adminStartSession();

    // Campos obrigatórios
    if (
        empty($_SESSION['admin_id'])         ||
        empty($_SESSION['admin_logged_in'])  ||
        empty($_SESSION['admin_2fa_ok'])       // 2FA deve estar validado
    ) {
        return false;
    }

    // Expiração de sessão
    if (time() - ($_SESSION['admin_last_activity'] ?? 0) > SESSION_LIFETIME) {
        _adminDestroySession();
        return false;
    }

    // Fingerprint do navegador + IP (session hijacking)
    $fp = _buildFingerprint();
    if (!empty($_SESSION['admin_fp']) && !hash_equals($_SESSION['admin_fp'], $fp)) {
        _adminAuditLog('SESSION_HIJACK', ['stored_fp' => $_SESSION['admin_fp'], 'req_fp' => $fp]);
        _adminDestroySession();
        return false;
    }

    $_SESSION['admin_last_activity'] = time();
    return true;
}

// ============================================================
// EXIGE LOGIN (usar no topo de cada view protegida)
// ============================================================
function requireAdmin(): void {
    if (!isAdminLoggedIn()) {
        header('Location: ' . ADMIN_LOGIN);
        exit;
    }
}

// ============================================================
// LOGIN — ETAPA 1: e-mail + senha
// Retorna: ['success'=>bool, 'message'=>string, 'need_2fa'=>bool]
// ============================================================
function adminLogin(string $email, string $password): array {
    require_once DB_CONFIG;

    $ip    = _getClientIp();
    $email = mb_strtolower(trim($email));

    // ── Validação de entrada ──────────────────────────────────
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return _loginFail('Credenciais inválidas.', $email, $ip, 'INVALID_EMAIL');
    }
    if (mb_strlen($password) < 8 || mb_strlen($password) > 1024) {
        return _loginFail('Credenciais inválidas.', $email, $ip, 'INVALID_PASSWORD_LEN');
    }

    // ── Rate limiting por IP ──────────────────────────────────
    $ipBlock = _checkRateLimit("ip:$ip");
    if ($ipBlock['blocked']) {
        _adminAuditLog('RATE_LIMIT_IP', ['ip' => $ip, 'wait' => $ipBlock['wait_seconds']]);
        return ['success' => false, 'message' => "Muitas tentativas. Aguarde {$ipBlock['wait_seconds']}s.", 'need_2fa' => false];
    }

    // ── Rate limiting por e-mail ──────────────────────────────
    $emailBlock = _checkRateLimit("email:$email");
    if ($emailBlock['blocked']) {
        _adminAuditLog('RATE_LIMIT_EMAIL', ['email' => $email, 'wait' => $emailBlock['wait_seconds']]);
        // Mesma mensagem para não revelar se o e-mail existe
        return ['success' => false, 'message' => "Muitas tentativas. Aguarde {$emailBlock['wait_seconds']}s.", 'need_2fa' => false];
    }

    // ── Busca no banco (prepared statement) ──────────────────
    $admin = dbRow(
        'SELECT id, name, email, password_hash, is_active, totp_secret, totp_enabled
         FROM admin_users
         WHERE email = ?
         LIMIT 1',
        [$email]
    );

    // Tempo constante mesmo quando usuário não existe (anti-timing)
    $dummyHash = '$2y$12$invalidhashthatisnevervalidXXXXXXXXXXXXXXXXXXXXXXXX';
    $hashToVerify = $admin ? $admin['password_hash'] : $dummyHash;

    $passwordOk = password_verify($password, $hashToVerify);

    if (!$admin || !$passwordOk) {
        _incrementRateLimit("ip:$ip");
        _incrementRateLimit("email:$email");
        return _loginFail('Credenciais inválidas.', $email, $ip, 'WRONG_CREDENTIALS');
    }

    if (!(bool)$admin['is_active']) {
        _adminAuditLog('LOGIN_DISABLED_ACCOUNT', ['email' => $email, 'ip' => $ip]);
        return ['success' => false, 'message' => 'Conta desativada.', 'need_2fa' => false];
    }

    // Verifica se hash precisa de rehash (atualização automática de custo)
    if (password_needs_rehash($admin['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
        $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        dbExec('UPDATE admin_users SET password_hash = ? WHERE id = ?', [$newHash, $admin['id']]);
    }

    // Zera contadores de falha após sucesso de senha
    _resetRateLimit("ip:$ip");
    _resetRateLimit("email:$email");

    // ── 2FA ──────────────────────────────────────────────────
    if ($admin['totp_enabled'] && $admin['totp_secret']) {
        // Armazena estado parcial na sessão (aguardando TOTP)
        adminStartSession();
        session_regenerate_id(true);
        $_SESSION['admin_pending_id']    = (int)$admin['id'];
        $_SESSION['admin_pending_email'] = $admin['email'];
        $_SESSION['admin_pending_name']  = $admin['name'];
        $_SESSION['admin_pending_time']  = time();

        _adminAuditLog('LOGIN_NEEDS_2FA', ['email' => $email, 'ip' => $ip]);
        return ['success' => true, 'need_2fa' => true, 'message' => ''];
    }

    // Sem 2FA configurado: login completo
    _finalizeLogin($admin, $ip);
    return ['success' => true, 'need_2fa' => false, 'message' => ''];
}

// ============================================================
// LOGIN — ETAPA 2: validação TOTP
// ============================================================
function adminVerify2FA(string $code): array {
    adminStartSession();

    $ip = _getClientIp();

    // Verifica que etapa 1 foi completada (max 5 min)
    if (
        empty($_SESSION['admin_pending_id'])   ||
        empty($_SESSION['admin_pending_time']) ||
        time() - $_SESSION['admin_pending_time'] > 300
    ) {
        _adminDestroySession();
        return ['success' => false, 'message' => 'Sessão expirada. Faça login novamente.'];
    }

    // Rate limiting para TOTP (evita brute force de 6 dígitos)
    $key = "totp:{$_SESSION['admin_pending_id']}";
    $block = _checkRateLimit($key, max_attempts: 5, base_seconds: 60);
    if ($block['blocked']) {
        return ['success' => false, 'message' => "Muitas tentativas. Aguarde {$block['wait_seconds']}s."];
    }

    // Sanitiza: apenas dígitos, 6 caracteres
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== TOTP_DIGITS) {
        _incrementRateLimit($key);
        return ['success' => false, 'message' => 'Código inválido.'];
    }

    // Busca segredo TOTP
    require_once DB_CONFIG;
    $admin = dbRow(
        'SELECT id, name, email, totp_secret, is_active FROM admin_users WHERE id = ? LIMIT 1',
        [$_SESSION['admin_pending_id']]
    );

    if (!$admin || !$admin['is_active'] || !$admin['totp_secret']) {
        _adminDestroySession();
        return ['success' => false, 'message' => 'Erro de autenticação.'];
    }

    if (!_totpVerify($admin['totp_secret'], $code)) {
        _incrementRateLimit($key);
        _adminAuditLog('2FA_FAIL', ['admin_id' => $admin['id'], 'ip' => $ip]);
        return ['success' => false, 'message' => 'Código inválido ou expirado.'];
    }

    _resetRateLimit($key);
    _finalizeLogin($admin, $ip);
    return ['success' => true, 'message' => ''];
}

// ============================================================
// LOGOUT
// ============================================================
function adminLogout(): void {
    adminStartSession();
    _adminAuditLog('LOGOUT', ['admin_id' => $_SESSION['admin_id'] ?? null]);
    _adminDestroySession();
    header('Location: ' . ADMIN_LOGIN);
    exit;
}

// ============================================================
// GERAÇÃO DE SECRET TOTP (para setup do 2FA)
// ============================================================
function adminGenerate2FASecret(): string {
    $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32
    $secret = '';
    $bytes  = random_bytes(20);
    for ($i = 0; $i < 20; $i++) {
        $secret .= $chars[ord($bytes[$i]) & 31];
    }
    return $secret;
}

function adminGet2FAQrUrl(string $email, string $secret): string {
    $issuer = rawurlencode(TOTP_ISSUER);
    $label  = rawurlencode("$email");
    return "otpauth://totp/{$issuer}:{$label}?secret={$secret}&issuer={$issuer}&digits=" . TOTP_DIGITS . "&period=" . TOTP_PERIOD;
}

// ============================================================
// HELPERS INTERNOS
// ============================================================

/** Finaliza login definindo a sessão autenticada */
function _finalizeLogin(array $admin, string $ip): void {
    adminStartSession();
    session_regenerate_id(true);

    $_SESSION['admin_id']            = (int)$admin['id'];
    $_SESSION['admin_name']          = $admin['name'];
    $_SESSION['admin_email']         = $admin['email'];
    $_SESSION['admin_logged_in']     = true;
    $_SESSION['admin_2fa_ok']        = true;
    $_SESSION['admin_last_activity'] = time();
    $_SESSION['admin_fp']            = _buildFingerprint();

    // Limpa dados pendentes
    unset(
        $_SESSION['admin_pending_id'],
        $_SESSION['admin_pending_email'],
        $_SESSION['admin_pending_name'],
        $_SESSION['admin_pending_time']
    );

    _adminAuditLog('LOGIN_SUCCESS', ['admin_id' => $admin['id'], 'email' => $admin['email'], 'ip' => $ip]);
}

/** Resposta genérica de falha + log */
function _loginFail(string $msg, string $email, string $ip, string $reason): array {
    _adminAuditLog('LOGIN_FAIL', ['email' => $email, 'ip' => $ip, 'reason' => $reason]);
    // Delay constante para mitigar timing attacks
    usleep(random_int(200_000, 400_000));
    return ['success' => false, 'message' => $msg, 'need_2fa' => false];
}

/** Destrói sessão completamente */
function _adminDestroySession(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 86400,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/** Fingerprint resistente a mudança de IP em mobile (usa apenas UA + subnet /24) */
function _buildFingerprint(): string {
    $ip     = _getClientIp();
    $subnet = implode('.', array_slice(explode('.', $ip), 0, 3)); // /24
    $ua     = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return hash_hmac('sha256', "$subnet|$ua", _getFingerprintKey());
}

function _getFingerprintKey(): string {
    // Chave derivada do segredo da aplicação (defina em config)
    if (defined('APP_SECRET')) return APP_SECRET;
    // Fallback: segredo persistido em arquivo (não versionado)
    $file = PROJECT_ROOT . '/.fp_key';
    if (!file_exists($file)) {
        $key = bin2hex(random_bytes(32));
        file_put_contents($file, $key);
        chmod($file, 0600);
    }
    return trim(file_get_contents($file));
}

/** IP real do cliente (cuidado com proxies não confiáveis) */
function _getClientIp(): string {
    // Só aceita X-Forwarded-For se o servidor estiver atrás de proxy confiável
    // Ajuste conforme sua infraestrutura
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip  = trim($ips[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// ============================================================
// RATE LIMITING (baseado em sessão de arquivo / APCu / Redis)
// Usa banco de dados para persistência entre processos
// ============================================================

/**
 * Verifica se a chave está bloqueada.
 * Retorna ['blocked'=>bool, 'wait_seconds'=>int]
 */
function _checkRateLimit(string $key, int $max_attempts = MAX_ATTEMPTS, int $base_seconds = LOCKOUT_BASE): array {
    require_once DB_CONFIG;

    $row = dbRow(
        'SELECT attempts, blocked_until FROM admin_rate_limits WHERE `key` = ? LIMIT 1',
        [$key]
    );

    if (!$row) return ['blocked' => false, 'wait_seconds' => 0];

    $now = time();
    if ($row['blocked_until'] && $row['blocked_until'] > $now) {
        return ['blocked' => true, 'wait_seconds' => $row['blocked_until'] - $now];
    }

    return ['blocked' => false, 'wait_seconds' => 0];
}

/** Incrementa contador e aplica bloqueio progressivo se necessário */
function _incrementRateLimit(string $key, int $max_attempts = MAX_ATTEMPTS, int $base_seconds = LOCKOUT_BASE): void {
    require_once DB_CONFIG;

    $now = time();

    dbExec(
        'INSERT INTO admin_rate_limits (`key`, attempts, first_attempt, last_attempt, blocked_until)
         VALUES (?, 1, ?, ?, NULL)
         ON DUPLICATE KEY UPDATE
           attempts     = attempts + 1,
           last_attempt = ?',
        [$key, $now, $now, $now]
    );

    $row = dbRow('SELECT attempts FROM admin_rate_limits WHERE `key` = ?', [$key]);
    $attempts = (int)($row['attempts'] ?? 1);

    if ($attempts >= $max_attempts) {
        // Bloqueio exponencial: 30s, 60s, 120s… até MAX_LOCKOUT
        $extra    = max(0, $attempts - $max_attempts);
        $lockout  = min($base_seconds * (2 ** $extra), MAX_LOCKOUT);
        $until    = $now + $lockout;
        dbExec(
            'UPDATE admin_rate_limits SET blocked_until = ? WHERE `key` = ?',
            [$until, $key]
        );
    }
}

/** Zera contador após login bem-sucedido */
function _resetRateLimit(string $key): void {
    require_once DB_CONFIG;
    dbExec('DELETE FROM admin_rate_limits WHERE `key` = ?', [$key]);
}

// ============================================================
// TOTP (RFC 6238) — sem dependência externa
// ============================================================

function _totpVerify(string $secret, string $code): bool {
    $timestamp = time();
    // Verifica janela de ±TOTP_WINDOW períodos
    for ($i = -TOTP_WINDOW; $i <= TOTP_WINDOW; $i++) {
        $counter = (int)(($timestamp / TOTP_PERIOD) + $i);
        if (hash_equals(_totpGenerate($secret, $counter), $code)) {
            return true;
        }
    }
    return false;
}

function _totpGenerate(string $secret, int $counter): string {
    // Decodifica Base32
    $base32  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret  = strtoupper($secret);
    $bits    = '';
    foreach (str_split($secret) as $char) {
        $pos  = strpos($base32, $char);
        if ($pos === false) continue;
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $key = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) $key .= chr(bindec($byte));
    }

    // HMAC-SHA1 do contador (big-endian 8 bytes)
    $msg  = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $msg, $key, true);

    // Dynamic truncation
    $offset = ord($hash[19]) & 0x0F;
    $code   = (
        ((ord($hash[$offset])   & 0x7F) << 24) |
        ((ord($hash[$offset+1]) & 0xFF) << 16) |
        ((ord($hash[$offset+2]) & 0xFF) <<  8) |
        ((ord($hash[$offset+3]) & 0xFF))
    ) % (10 ** TOTP_DIGITS);

    return str_pad((string)$code, TOTP_DIGITS, '0', STR_PAD_LEFT);
}

// ============================================================
// AUDIT LOG
// ============================================================

function _adminAuditLog(string $event, array $context = []): void {
    try {
        $entry = [
            'time'    => date('Y-m-d H:i:s'),
            'event'   => $event,
            'ip'      => _getClientIp(),
            'ua'      => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
            'context' => $context,
        ];
        $dir = dirname(LOG_PATH);
        if (!is_dir($dir)) mkdir($dir, 0750, true);
        file_put_contents(LOG_PATH, json_encode($entry) . PHP_EOL, FILE_APPEND | LOCK_EX);

        // Também persiste no banco para consulta no painel
        if (defined('DB_CONFIG') && file_exists(DB_CONFIG)) {
            require_once DB_CONFIG;
            dbExec(
                'INSERT INTO admin_audit_log (event, ip, user_agent, context, created_at)
                 VALUES (?, ?, ?, ?, NOW())',
                [
                    $event,
                    _getClientIp(),
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
                    json_encode($context),
                ]
            );
        }
    } catch (Throwable) {
        // Log silencioso — nunca expõe erros de log ao usuário
    }
}