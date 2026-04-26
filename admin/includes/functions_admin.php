<?php
// ============================================================
// /admin/includes/functions_admin.php — florescer Admin v1.0
// Funções auxiliares do painel administrativo
// Requer: auth_admin.php (já carregado) e config/db.php
// ============================================================

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(dirname(__DIR__)));
}
if (!defined('DB_CONFIG')) {
    define('DB_CONFIG', PROJECT_ROOT . '/config/db.php');
}

require_once DB_CONFIG;

// ============================================================
// UTILITÁRIOS GERAIS
// ============================================================

/**
 * Escapa string para saída HTML segura (evita XSS).
 * Use em TODA variável exibida no HTML.
 */
function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Retorna o nome do admin logado ou 'Admin'.
 */
function adminName(): string {
    return e($_SESSION['admin_name'] ?? 'Admin');
}

/**
 * Formata timestamp/datetime para exibição (pt-BR).
 * Ex: "21 de abril de 2026 às 14:32"
 */
function adminFormatDate(string|int|null $value, bool $short = false): string {
    if (!$value) return '—';
    $ts = is_numeric($value) ? (int)$value : strtotime($value);
    if (!$ts) return '—';
    return $short
        ? date('d/m/Y H:i', $ts)
        : date('d/m/Y \à\s H:i', $ts);
}

/**
 * Formata bytes em unidade legível (KB, MB, GB).
 */
function adminFormatBytes(int $bytes): string {
    if ($bytes >= 1_073_741_824) return round($bytes / 1_073_741_824, 2) . ' GB';
    if ($bytes >= 1_048_576)     return round($bytes / 1_048_576, 2)     . ' MB';
    if ($bytes >= 1_024)         return round($bytes / 1_024, 2)          . ' KB';
    return "$bytes B";
}

/**
 * Paginação simples.
 * Retorna ['offset'=>int, 'limit'=>int, 'page'=>int]
 */
function adminPaginate(int $perPage = 25): array {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;
    return ['page' => $page, 'limit' => $perPage, 'offset' => $offset];
}

/**
 * Gera HTML de paginação.
 */
function adminPaginationHtml(int $total, int $perPage, int $currentPage, string $baseUrl = ''): string {
    $pages = (int)ceil($total / $perPage);
    if ($pages <= 1) return '';

    $base = $baseUrl ?: strtok($_SERVER['REQUEST_URI'], '?');
    $html = '<nav class="adm-pagination" aria-label="Paginação">';

    for ($i = 1; $i <= $pages; $i++) {
        $active = $i === $currentPage ? ' adm-pagination__item--active' : '';
        $url    = e($base . '?page=' . $i);
        $html  .= "<a href=\"$url\" class=\"adm-pagination__item$active\">$i</a>";
    }

    return $html . '</nav>';
}

/**
 * Sanitiza e valida entrada de texto simples.
 * Retorna string limpa ou null se inválida.
 */
function adminSanitizeText(mixed $value, int $maxLen = 255): ?string {
    if (!is_string($value)) return null;
    $clean = trim(strip_tags($value));
    if ($clean === '' || mb_strlen($clean) > $maxLen) return null;
    return $clean;
}

/**
 * Redireciona com mensagem flash armazenada em sessão.
 */
function adminRedirect(string $url, string $type = 'success', string $message = ''): never {
    if ($message) {
        $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Exibe e limpa mensagem flash (chame no topo da view).
 * Tipos: success | error | warning | info
 */
function adminFlash(): string {
    if (empty($_SESSION['admin_flash'])) return '';
    $flash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
    $type = in_array($flash['type'], ['success','error','warning','info'], true)
        ? $flash['type'] : 'info';
    return '<div class="adm-flash adm-flash--' . $type . '">' . e($flash['message']) . '</div>';
}

// ============================================================
// DASHBOARD — ESTATÍSTICAS
// ============================================================

/**
 * Retorna contadores gerais para o dashboard.
 * Adapte os nomes de tabela conforme seu banco.
 */
function adminDashboardStats(): array {
    try {
        $stats = [];

        // Total de usuários cadastrados
        $stats['total_users'] = (int)(dbRow(
            'SELECT COUNT(*) AS c FROM users'
        )['c'] ?? 0);

        // Novos usuários nos últimos 7 dias
        $stats['new_users_week'] = (int)(dbRow(
            'SELECT COUNT(*) AS c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
        )['c'] ?? 0);

        // Usuários ativos (logaram nos últimos 30 dias)
        $stats['active_users_month'] = (int)(dbRow(
            'SELECT COUNT(*) AS c FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
        )['c'] ?? 0);

        // Total de mensagens (chat/suporte)
        $stats['total_messages'] = (int)(dbRow(
            'SELECT COUNT(*) AS c FROM messages'
        )['c'] ?? 0);

        // Mensagens não lidas
        $stats['unread_messages'] = (int)(dbRow(
            'SELECT COUNT(*) AS c FROM messages WHERE is_read = 0'
        )['c'] ?? 0);

        // Sessões ativas agora
        $stats['active_sessions'] = (int)(dbRow(
            'SELECT COUNT(*) AS c FROM user_sessions
             WHERE expires_at > NOW() AND is_active = 1'
        )['c'] ?? 0);

        // Logins com falha nas últimas 24h (do audit log)
        $stats['failed_logins_24h'] = (int)(dbRow(
            "SELECT COUNT(*) AS c FROM admin_audit_log
             WHERE event = 'LOGIN_FAIL'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        )['c'] ?? 0);

        // Crescimento de usuários por dia (últimos 14 dias)
        $stats['user_growth'] = dbQuery(
            "SELECT DATE(created_at) AS day, COUNT(*) AS total
             FROM users
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC"
        );

        return $stats;

    } catch (Throwable $e) {
        error_log('[Admin] adminDashboardStats: ' . $e->getMessage());
        return [];
    }
}

// ============================================================
// USUÁRIOS
// ============================================================

/**
 * Lista usuários com busca, filtro e paginação.
 *
 * @param string $search  Busca por nome ou e-mail
 * @param string $filter  'all' | 'active' | 'banned'
 * @param int    $limit
 * @param int    $offset
 * @return array ['users' => array, 'total' => int]
 */
function adminGetUsers(string $search = '', string $filter = 'all', int $limit = 25, int $offset = 0): array {
    try {
        $where  = [];
        $params = [];

        // Busca por nome ou e-mail (prepared — sem SQL injection)
        if ($search !== '') {
            $where[]  = '(name LIKE ? OR email LIKE ?)';
            $term     = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
        }

        // Filtro de status
        if ($filter === 'active') {
            $where[]  = 'is_banned = 0 AND is_active = 1';
        } elseif ($filter === 'banned') {
            $where[]  = 'is_banned = 1';
        }

        $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Total para paginação
        $total = (int)(dbRow(
            "SELECT COUNT(*) AS c FROM users $whereClause",
            $params
        )['c'] ?? 0);

        // Listagem
        $users = dbQuery(
            "SELECT id, name, email, avatar, is_active, is_banned,
                    created_at, last_login, city, school
             FROM users
             $whereClause
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?",
            [...$params, $limit, $offset]
        );

        return ['users' => $users, 'total' => $total];

    } catch (Throwable $e) {
        error_log('[Admin] adminGetUsers: ' . $e->getMessage());
        return ['users' => [], 'total' => 0];
    }
}

/**
 * Busca detalhes completos de um usuário.
 */
function adminGetUser(int $id): ?array {
    try {
        return dbRow(
            'SELECT id, name, email, avatar, bio, city, school,
                    is_active, is_banned, ban_reason, created_at, last_login
             FROM users WHERE id = ? LIMIT 1',
            [$id]
        );
    } catch (Throwable $e) {
        error_log('[Admin] adminGetUser: ' . $e->getMessage());
        return null;
    }
}

/**
 * Bane um usuário.
 * Retorna true em sucesso.
 */
function adminBanUser(int $userId, string $reason = ''): bool {
    try {
        $reason = mb_substr(trim($reason), 0, 500);

        $affected = dbExec(
            'UPDATE users SET is_banned = 1, ban_reason = ?, updated_at = NOW()
             WHERE id = ? AND id != 0',  // impossível banir id=0
            [$reason, $userId]
        );

        if ($affected > 0) {
            _adminAuditLog('USER_BANNED', [
                'target_user_id' => $userId,
                'reason'         => $reason,
                'admin_id'       => $_SESSION['admin_id'] ?? null,
            ]);
            // Revoga todas as sessões do usuário banido
            adminRevokeUserSessions($userId);
        }

        return $affected > 0;

    } catch (Throwable $e) {
        error_log('[Admin] adminBanUser: ' . $e->getMessage());
        return false;
    }
}

/**
 * Remove o banimento de um usuário.
 */
function adminUnbanUser(int $userId): bool {
    try {
        $affected = dbExec(
            'UPDATE users SET is_banned = 0, ban_reason = NULL, updated_at = NOW()
             WHERE id = ?',
            [$userId]
        );

        if ($affected > 0) {
            _adminAuditLog('USER_UNBANNED', [
                'target_user_id' => $userId,
                'admin_id'       => $_SESSION['admin_id'] ?? null,
            ]);
        }

        return $affected > 0;

    } catch (Throwable $e) {
        error_log('[Admin] adminUnbanUser: ' . $e->getMessage());
        return false;
    }
}

/**
 * Deleta conta de usuário (hard delete — use com cuidado).
 * Considere soft delete (is_active = 0) em produção.
 */
function adminDeleteUser(int $userId): bool {
    try {
        adminRevokeUserSessions($userId);

        $affected = dbExec('DELETE FROM users WHERE id = ?', [$userId]);

        if ($affected > 0) {
            _adminAuditLog('USER_DELETED', [
                'target_user_id' => $userId,
                'admin_id'       => $_SESSION['admin_id'] ?? null,
            ]);
        }

        return $affected > 0;

    } catch (Throwable $e) {
        error_log('[Admin] adminDeleteUser: ' . $e->getMessage());
        return false;
    }
}

// ============================================================
// MENSAGENS / CHAT
// ============================================================

/**
 * Lista mensagens com filtro de leitura e paginação.
 *
 * @param string $filter 'all' | 'unread' | 'read'
 */
function adminGetMessages(string $filter = 'all', int $limit = 30, int $offset = 0): array {
    try {
        $where  = [];
        $params = [];

        if ($filter === 'unread') {
            $where[] = 'm.is_read = 0';
        } elseif ($filter === 'read') {
            $where[] = 'm.is_read = 1';
        }

        $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $total = (int)(dbRow(
            "SELECT COUNT(*) AS c FROM messages m $whereClause",
            $params
        )['c'] ?? 0);

        $messages = dbQuery(
            "SELECT m.id, m.content, m.is_read, m.created_at,
                    u.id AS user_id, u.name AS user_name, u.email AS user_email, u.avatar
             FROM messages m
             LEFT JOIN users u ON u.id = m.user_id
             $whereClause
             ORDER BY m.created_at DESC
             LIMIT ? OFFSET ?",
            [...$params, $limit, $offset]
        );

        return ['messages' => $messages, 'total' => $total];

    } catch (Throwable $e) {
        error_log('[Admin] adminGetMessages: ' . $e->getMessage());
        return ['messages' => [], 'total' => 0];
    }
}

/**
 * Marca uma ou todas as mensagens como lidas.
 * Passe $id = null para marcar todas.
 */
function adminMarkMessageRead(?int $id = null): bool {
    try {
        if ($id !== null) {
            return dbExec('UPDATE messages SET is_read = 1 WHERE id = ?', [$id]) > 0;
        }
        dbExec('UPDATE messages SET is_read = 1 WHERE is_read = 0');
        return true;
    } catch (Throwable $e) {
        error_log('[Admin] adminMarkMessageRead: ' . $e->getMessage());
        return false;
    }
}

/**
 * Deleta uma mensagem.
 */
function adminDeleteMessage(int $id): bool {
    try {
        return dbExec('DELETE FROM messages WHERE id = ?', [$id]) > 0;
    } catch (Throwable $e) {
        error_log('[Admin] adminDeleteMessage: ' . $e->getMessage());
        return false;
    }
}

// ============================================================
// SESSÕES DE USUÁRIOS
// ============================================================

/**
 * Lista sessões ativas de usuários.
 */
function adminGetActiveSessions(int $limit = 50, int $offset = 0): array {
    try {
        $total = (int)(dbRow(
            'SELECT COUNT(*) AS c FROM user_sessions
             WHERE expires_at > NOW() AND is_active = 1'
        )['c'] ?? 0);

        $sessions = dbQuery(
            'SELECT s.id, s.ip_address, s.user_agent, s.created_at, s.expires_at,
                    u.id AS user_id, u.name AS user_name, u.email AS user_email
             FROM user_sessions s
             LEFT JOIN users u ON u.id = s.user_id
             WHERE s.expires_at > NOW() AND s.is_active = 1
             ORDER BY s.created_at DESC
             LIMIT ? OFFSET ?',
            [$limit, $offset]
        );

        return ['sessions' => $sessions, 'total' => $total];

    } catch (Throwable $e) {
        error_log('[Admin] adminGetActiveSessions: ' . $e->getMessage());
        return ['sessions' => [], 'total' => 0];
    }
}

/**
 * Revoga uma sessão específica de usuário.
 */
function adminRevokeSession(int $sessionId): bool {
    try {
        $affected = dbExec(
            'UPDATE user_sessions SET is_active = 0, expires_at = NOW()
             WHERE id = ?',
            [$sessionId]
        );

        if ($affected > 0) {
            _adminAuditLog('SESSION_REVOKED', [
                'session_id' => $sessionId,
                'admin_id'   => $_SESSION['admin_id'] ?? null,
            ]);
        }

        return $affected > 0;

    } catch (Throwable $e) {
        error_log('[Admin] adminRevokeSession: ' . $e->getMessage());
        return false;
    }
}

/**
 * Revoga todas as sessões de um usuário específico.
 * Usado ao banir ou deletar conta.
 */
function adminRevokeUserSessions(int $userId): bool {
    try {
        dbExec(
            'UPDATE user_sessions SET is_active = 0, expires_at = NOW()
             WHERE user_id = ? AND is_active = 1',
            [$userId]
        );

        _adminAuditLog('USER_SESSIONS_REVOKED', [
            'target_user_id' => $userId,
            'admin_id'       => $_SESSION['admin_id'] ?? null,
        ]);

        return true;

    } catch (Throwable $e) {
        error_log('[Admin] adminRevokeUserSessions: ' . $e->getMessage());
        return false;
    }
}

/**
 * Revoga TODAS as sessões de todos os usuários (emergência).
 */
function adminRevokeAllSessions(): bool {
    try {
        dbExec('UPDATE user_sessions SET is_active = 0, expires_at = NOW() WHERE is_active = 1');

        _adminAuditLog('ALL_SESSIONS_REVOKED', [
            'admin_id' => $_SESSION['admin_id'] ?? null,
        ]);

        return true;

    } catch (Throwable $e) {
        error_log('[Admin] adminRevokeAllSessions: ' . $e->getMessage());
        return false;
    }
}

// ============================================================
// CONFIGURAÇÕES DO SISTEMA
// ============================================================

/**
 * Lê todas as configurações da tabela admin_settings.
 * Estrutura esperada: id | key | value | updated_at
 *
 * Retorna array associativo: ['chave' => 'valor', ...]
 */
function adminGetSettings(): array {
    try {
        $rows = dbQuery('SELECT `key`, `value` FROM admin_settings ORDER BY `key` ASC');
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['key']] = $row['value'];
        }
        return $settings;
    } catch (Throwable $e) {
        error_log('[Admin] adminGetSettings: ' . $e->getMessage());
        return [];
    }
}

/**
 * Lê uma configuração específica.
 * $default é retornado se a chave não existir.
 */
function adminGetSetting(string $key, mixed $default = null): mixed {
    try {
        $row = dbRow(
            'SELECT `value` FROM admin_settings WHERE `key` = ? LIMIT 1',
            [$key]
        );
        return $row ? $row['value'] : $default;
    } catch (Throwable $e) {
        error_log('[Admin] adminGetSetting: ' . $e->getMessage());
        return $default;
    }
}

/**
 * Salva (INSERT ou UPDATE) um conjunto de configurações.
 * $data = ['chave' => 'valor', ...]
 *
 * Apenas chaves da whitelist são aceitas (evita mass assignment).
 */
function adminSaveSettings(array $data): bool {
    // ── Whitelist de chaves permitidas ──────────────────────
    // Adicione aqui todas as configurações válidas do seu sistema
    $allowed = [
        'site_name',
        'site_description',
        'maintenance_mode',
        'maintenance_message',
        'registration_open',
        'max_login_attempts',
        'session_lifetime_minutes',
        'email_support',
        'allow_chat',
        'allow_courses',
        'allow_simulados',
        'daily_goal_default',
    ];

    try {
        dbBegin();

        foreach ($data as $key => $value) {
            // Ignora silenciosamente chaves não permitidas
            if (!in_array($key, $allowed, true)) continue;

            $value = is_string($value) ? mb_substr(trim($value), 0, 1000) : (string)$value;

            dbExec(
                'INSERT INTO admin_settings (`key`, `value`, updated_at)
                 VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()',
                [$key, $value]
            );
        }

        dbCommit();

        _adminAuditLog('SETTINGS_UPDATED', [
            'keys'     => array_keys(array_intersect_key($data, array_flip($allowed))),
            'admin_id' => $_SESSION['admin_id'] ?? null,
        ]);

        return true;

    } catch (Throwable $e) {
        dbRollback();
        error_log('[Admin] adminSaveSettings: ' . $e->getMessage());
        return false;
    }
}

// ============================================================
// AUDIT LOG — CONSULTA
// ============================================================

/**
 * Lista eventos do audit log com filtro e paginação.
 *
 * @param string $event  Filtra por tipo de evento (ex: 'LOGIN_FAIL')
 * @param string $ip     Filtra por IP
 */
function adminGetAuditLog(string $event = '', string $ip = '', int $limit = 50, int $offset = 0): array {
    try {
        $where  = [];
        $params = [];

        if ($event !== '') {
            $where[]  = 'event = ?';
            $params[] = $event;
        }
        if ($ip !== '') {
            $where[]  = 'ip = ?';
            $params[] = $ip;
        }

        $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $total = (int)(dbRow(
            "SELECT COUNT(*) AS c FROM admin_audit_log $whereClause",
            $params
        )['c'] ?? 0);

        $logs = dbQuery(
            "SELECT id, event, ip, user_agent, context, created_at
             FROM admin_audit_log
             $whereClause
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?",
            [...$params, $limit, $offset]
        );

        // Decodifica JSON do context para array
        foreach ($logs as &$log) {
            $log['context'] = json_decode($log['context'] ?? '{}', true) ?? [];
        }
        unset($log);

        return ['logs' => $logs, 'total' => $total];

    } catch (Throwable $e) {
        error_log('[Admin] adminGetAuditLog: ' . $e->getMessage());
        return ['logs' => [], 'total' => 0];
    }
}

/**
 * Lista os tipos de evento distintos para o filtro da view.
 */
function adminGetAuditEventTypes(): array {
    try {
        $rows = dbQuery('SELECT DISTINCT event FROM admin_audit_log ORDER BY event ASC');
        return array_column($rows, 'event');
    } catch (Throwable $e) {
        return [];
    }
}

// ============================================================
// INFORMAÇÕES DO SERVIDOR (para view de configurações)
// ============================================================

/**
 * Retorna métricas do servidor para exibição no painel.
 */
function adminServerInfo(): array {
    return [
        'php_version'   => PHP_VERSION,
        'server_os'     => PHP_OS_FAMILY,
        'memory_limit'  => ini_get('memory_limit'),
        'memory_usage'  => adminFormatBytes(memory_get_usage(true)),
        'memory_peak'   => adminFormatBytes(memory_get_peak_usage(true)),
        'upload_max'    => ini_get('upload_max_filesize'),
        'post_max'      => ini_get('post_max_size'),
        'max_exec_time' => ini_get('max_execution_time') . 's',
        'timezone'      => date_default_timezone_get(),
        'server_time'   => date('d/m/Y H:i:s'),
        'extensions'    => [
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'mbstring'  => extension_loaded('mbstring'),
            'openssl'   => extension_loaded('openssl'),
            'json'      => extension_loaded('json'),
            'gd'        => extension_loaded('gd'),
        ],
    ];
}

// ============================================================
// VALIDAÇÃO DE CSRF PARA ACTIONS DAS VIEWS
// ============================================================

/**
 * Verifica token CSRF de um formulário de action (POST).
 * Se inválido, redireciona com erro.
 *
 * Uso nas views:
 *   adminVerifyCsrf();  // aborta se inválido
 */
function adminVerifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['admin_csrf'] ?? '';

    // Token de uso único
    unset($_SESSION['admin_csrf']);

    if (!$token || !$stored || !hash_equals($stored, $token)) {
        _adminAuditLog('CSRF_FAIL', ['uri' => $_SERVER['REQUEST_URI'] ?? '']);
        adminRedirect(
            $_SERVER['HTTP_REFERER'] ?? ADMIN_LOGIN,
            'error',
            'Requisição inválida. Tente novamente.'
        );
    }
}

/**
 * Gera e armazena novo token CSRF na sessão.
 * Retorna o token para uso no hidden input.
 *
 * Uso nas views:
 *   <input type="hidden" name="csrf_token" value="<?= adminCsrfToken() ?>">
 */
function adminCsrfToken(): string {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['admin_csrf'];
}

/**
 * Retorna o token CSRF atual como atributo HTML pronto para uso.
 * Uso: <?= adminCsrfField() ?>
 */
function adminCsrfField(): string {
    return '<input type="hidden" name="csrf_token" value="'
        . e(adminCsrfToken())
        . '">';
}