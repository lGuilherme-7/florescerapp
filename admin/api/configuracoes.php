<?php
// ============================================================
// /admin/api/configuracoes.php — florescer Admin v3.0
// Salva configs em system_config + altera senha do admin
// ============================================================
require_once __DIR__ . '/../includes/auth_admin.php';
require_once dirname(dirname(__DIR__)) . '/config/db.php';

requireAdmin();
ob_start();
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

$adminId = (int)($_SESSION['admin_id'] ?? 0);

function jsonOut(array $d): void {
    if (ob_get_level() > 0) ob_end_clean();
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonOut(['success' => false, 'message' => 'Método não permitido.']);
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($body['action'] ?? '');

// ── Chaves permitidas por seção ────────────────────────────────
// Whitelist estrita: evita salvar qualquer chave arbitrária
const ALLOWED_KEYS = [
    'plataforma'  => ['app_name', 'app_version', 'app_tagline', 'contact_email', 'app_url'],
    'pix'         => ['pix_type', 'pix_key', 'pix_name'],
    'gamificacao' => [
        'default_goal_min', 'water_chances', 'default_units',
        'xp_per_lesson', 'xp_per_goal', 'xp_per_streak_day',
    ],
    'chat'        => [
        'chat_poll_interval', 'chat_max_messages', 'chat_max_chars',
        'chat_cooldown', 'chat_enabled', 'chat_moderation',
    ],
    'simulados'   => [
        'sim_questions_count', 'sim_time_limit', 'sim_xp_reward',
        'sim_pass_score', 'sim_anticheating', 'sim_show_answers',
    ],
    'manutencao'  => ['maintenance_mode', 'maintenance'],
];

// ── Validadores por campo ──────────────────────────────────────
function validateField(string $key, string $val): ?string {
    switch ($key) {
        case 'contact_email':
            if ($val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL))
                return 'E-mail de contato inválido.';
            break;
        case 'app_url':
            if ($val !== '' && !filter_var($val, FILTER_VALIDATE_URL))
                return 'URL da plataforma inválida.';
            break;
        case 'pix_type':
            $validTypes = ['email','cpf','cnpj','telefone','aleatoria'];
            if ($val !== '' && !in_array($val, $validTypes, true))
                return 'Tipo de chave Pix inválido.';
            break;
        case 'default_goal_min':
            if ($val !== '' && ((int)$val < 5 || (int)$val > 480))
                return 'Meta diária deve ser entre 5 e 480 minutos.';
            break;
        case 'water_chances':
            if ($val !== '' && ((int)$val < 1 || (int)$val > 10))
                return 'Chances de regar deve ser entre 1 e 10.';
            break;
        case 'sim_pass_score':
            if ($val !== '' && ((int)$val < 1 || (int)$val > 100))
                return 'Nota de aprovação deve ser entre 1 e 100%.';
            break;
        case 'sim_time_limit':
            if ($val !== '' && ((int)$val < 0 || (int)$val > 360))
                return 'Tempo máximo deve ser entre 0 e 360 minutos.';
            break;
        case 'chat_poll_interval':
            if ($val !== '' && ((int)$val < 1 || (int)$val > 60))
                return 'Intervalo de polling deve ser entre 1 e 60 segundos.';
            break;
    }
    return null; // OK
}

// ── Helper: garante tabela system_config ──────────────────────
function ensureConfigTable(): void {
    dbExec("CREATE TABLE IF NOT EXISTS system_config (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        key_name   VARCHAR(100) NOT NULL UNIQUE,
        value      TEXT,
        label      VARCHAR(200) DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

switch ($action) {

    // ── save_config: salva uma seção de configurações ─────────
    case 'save_config':
        $section = trim($body['section'] ?? '');
        if (!isset(ALLOWED_KEYS[$section])) {
            jsonOut(['success' => false, 'message' => 'Seção inválida.']);
        }

        ensureConfigTable();
        $keys  = ALLOWED_KEYS[$section];
        $saved = 0;
        $errors = [];

        foreach ($keys as $key) {
            // Aceita tanto key quanto cfg_key no body (flexibilidade)
            $val = $body[$key] ?? $body['cfg_' . $key] ?? null;
            if ($val === null) continue;

            // Sanitiza
            $val = mb_substr(trim((string)$val), 0, 2000, 'UTF-8');

            // Valida
            $err = validateField($key, $val);
            if ($err) { $errors[] = $err; continue; }

            // UPSERT
            dbExec(
                "INSERT INTO system_config (key_name, value)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE value = VALUES(value)",
                [$key, $val]
            );
            $saved++;
        }

        if (!empty($errors)) {
            jsonOut(['success' => false, 'message' => implode(' ', $errors)]);
        }

        // Audit log
        try {
            dbExec(
                "INSERT INTO admin_audit_log (event, ip, user_agent, context)
                 VALUES ('CONFIG_SAVE', ?, ?, ?)",
                [
                    $_SERVER['REMOTE_ADDR'] ?? '?',
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
                    json_encode(['admin_id' => $adminId, 'section' => $section, 'saved' => $saved]),
                ]
            );
        } catch (\Throwable $e) { /* não bloqueia */ }

        jsonOut(['success' => true, 'saved' => $saved]);

    // ── get_config: retorna todas as configs ──────────────────
    case 'get_config':
        if (!(bool)dbRow("SHOW TABLES LIKE 'system_config'")) {
            jsonOut(['success' => true, 'data' => []]);
        }
        $rows = dbQuery("SELECT key_name, value FROM system_config");
        $cfg  = array_column($rows, 'value', 'key_name');
        jsonOut(['success' => true, 'data' => $cfg]);

    // ── change_admin_pass: altera senha do admin ──────────────
    case 'change_admin_pass':
        $current = trim($body['current'] ?? '');
        $newpass = trim($body['newpass'] ?? '');

        if (!$current || !$newpass) {
            jsonOut(['success' => false, 'message' => 'Preencha todos os campos.']);
        }
        if (mb_strlen($newpass, 'UTF-8') < 6) {
            jsonOut(['success' => false, 'message' => 'A nova senha deve ter pelo menos 6 caracteres.']);
        }
        if (mb_strlen($newpass, 'UTF-8') > 200) {
            jsonOut(['success' => false, 'message' => 'Senha muito longa.']);
        }

        // Busca o admin
        $admin = dbRow('SELECT * FROM admin_users WHERE id = ? AND is_active = 1', [$adminId]);
        if (!$admin) {
            jsonOut(['success' => false, 'message' => 'Admin não encontrado.']);
        }

        // Detecta campo de senha (flexível para diferentes schemas)
        $candidates = ['password_hash', 'password', 'senha', 'passwd', 'hash'];
        $passField  = null;
        foreach ($candidates as $c) {
            if (array_key_exists($c, $admin)) { $passField = $c; break; }
        }
        if (!$passField) {
            jsonOut(['success' => false, 'message' => 'Campo de senha não identificado. Contate o suporte.']);
        }

        // Verifica senha atual — timing-safe
        $validCurrent = password_verify($current, $admin[$passField]);
        if (!$validCurrent) {
            // Dummy hash para equalizar timing e dificultar timing attacks
            password_verify('dummy_timing_equalizer_florescer', '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ0123');
            jsonOut(['success' => false, 'message' => 'Senha atual incorreta.']);
        }

        // Não permite reutilizar a mesma senha
        if (password_verify($newpass, $admin[$passField])) {
            jsonOut(['success' => false, 'message' => 'A nova senha não pode ser igual à atual.']);
        }

        // Salva nova senha com bcrypt cost 12
        $hash = password_hash($newpass, PASSWORD_BCRYPT, ['cost' => 12]);
        dbExec(
            "UPDATE admin_users SET {$passField} = ?, updated_at = NOW() WHERE id = ?",
            [$hash, $adminId]
        );

        // Audit log
        try {
            dbExec(
                "INSERT INTO admin_audit_log (event, ip, user_agent, context)
                 VALUES ('ADMIN_PASS_CHANGE', ?, ?, ?)",
                [
                    $_SERVER['REMOTE_ADDR'] ?? '?',
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
                    json_encode(['admin_id' => $adminId, 'email' => $admin['email'] ?? '']),
                ]
            );
        } catch (\Throwable $e) { /* não bloqueia */ }

        jsonOut(['success' => true]);

    // ── reset_config: reseta uma chave para o valor padrão ────
    case 'reset_config':
        $key = trim($body['key'] ?? '');
        if (!$key) jsonOut(['success' => false, 'message' => 'Chave não informada.']);

        // Verifica se a chave está na whitelist de alguma seção
        $allowed = false;
        foreach (ALLOWED_KEYS as $keys) {
            if (in_array($key, $keys, true)) { $allowed = true; break; }
        }
        if (!$allowed) jsonOut(['success' => false, 'message' => 'Chave não permitida.']);

        dbExec("DELETE FROM system_config WHERE key_name = ?", [$key]);
        jsonOut(['success' => true]);
    
    // ── change_admin_email ────────────────────────────────────
case 'change_admin_email':
    $newEmail = trim($body['newemail'] ?? '');
    $current  = trim($body['current']  ?? '');

    if (!$newEmail || !$current) {
        jsonOut(['success' => false, 'message' => 'Preencha todos os campos.']);
    }
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        jsonOut(['success' => false, 'message' => 'E-mail inválido.']);
    }
    if (mb_strlen($newEmail, 'UTF-8') > 150) {
        jsonOut(['success' => false, 'message' => 'E-mail muito longo.']);
    }

    $admin = dbRow('SELECT * FROM admin_users WHERE id = ? AND is_active = 1', [$adminId]);
    if (!$admin) jsonOut(['success' => false, 'message' => 'Admin não encontrado.']);

    // Verifica senha atual
    if (!password_verify($current, $admin['password_hash'])) {
        password_verify('dummy', '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ0123');
        jsonOut(['success' => false, 'message' => 'Senha atual incorreta.']);
    }

    // Verifica se o e-mail já está em uso
    $exists = dbRow('SELECT id FROM admin_users WHERE email = ? AND id != ?', [$newEmail, $adminId]);
    if ($exists) jsonOut(['success' => false, 'message' => 'Este e-mail já está em uso.']);

    dbExec('UPDATE admin_users SET email = ?, updated_at = NOW() WHERE id = ?', [$newEmail, $adminId]);

    try {
        dbExec(
            "INSERT INTO admin_audit_log (event, ip, user_agent, context) VALUES ('ADMIN_EMAIL_CHANGE', ?, ?, ?)",
            [
                $_SERVER['REMOTE_ADDR'] ?? '?',
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
                json_encode(['admin_id' => $adminId, 'new_email' => $newEmail]),
            ]
        );
    } catch (\Throwable $e) {}

    // Invalida sessão para forçar novo login com o e-mail novo
    session_destroy();
    jsonOut(['success' => true]);
    
    default:
        http_response_code(400);
        jsonOut(['success' => false, 'message' => 'Ação desconhecida: ' . htmlspecialchars($action)]);
}