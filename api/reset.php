<?php
// ============================================================
// /api/reset.php
// Ações: request | reset
// Fluxo: usuário informa e-mail → recebe código → redefine senha
// ============================================================

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/mail.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Método não permitido.', 405);
}

$input  = getInput();
$action = inp($input, 'action', true);

match ($action) {
    'request' => actionRequest($input),
    'reset'   => actionReset($input),
    default   => fail('Ação inválida.', 400),
};

// ════════════════════════════════════════════════════════════
// PASSO 1 — SOLICITAR CÓDIGO
// ════════════════════════════════════════════════════════════

function actionRequest(array $input): void {
    $email = mb_strtolower(trim(inp($input, 'email', true)), 'UTF-8');

    if (!isValidEmail($email)) {
        fail('Formato de e-mail inválido.');
    }

    // ── Rate limit: máximo 3 solicitações por e-mail em 15 minutos ──
    $recentCount = dbRow(
        'SELECT COUNT(*) AS total
         FROM password_resets
         WHERE email = ?
           AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)',
        [$email]
    );

    if ((int) ($recentCount['total'] ?? 0) >= 3) {
        // Resposta neutra — não revela se o e-mail existe
        ok(null, 'Se esse e-mail estiver cadastrado, você receberá um código em breve.');
        return;
    }

    // ── Busca usuário — resposta sempre igual independente do resultado ──
    $user = dbRow(
        'SELECT id, name FROM users WHERE email = ? LIMIT 1',
        [$email]
    );

    // Gera e persiste o código mesmo assim — decisão de enviar é separada
    $code      = generateResetCode();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    if ($user) {
        // Invalida códigos anteriores não usados para este e-mail
        dbExec(
            'UPDATE password_resets SET used = 1
             WHERE email = ? AND used = 0',
            [$email]
        );

        // Insere novo código
        dbExec(
            'INSERT INTO password_resets (email, code, expires_at)
             VALUES (?, ?, ?)',
            [$email, $code, $expiresAt]
        );

        // Envia e-mail — falha silenciosa para não revelar existência do e-mail
        sendPasswordReset($email, $user['name'], $code);
    }

    // Resposta sempre idêntica — não revela se e-mail existe ou não
    ok(null, 'Se esse e-mail estiver cadastrado, você receberá um código em breve.');
}

// ════════════════════════════════════════════════════════════
// PASSO 2 — VALIDAR CÓDIGO E REDEFINIR SENHA
// ════════════════════════════════════════════════════════════

function actionReset(array $input): void {
    $email    = mb_strtolower(trim(inp($input, 'email',    true)), 'UTF-8');
    $code     = trim(inp($input, 'code',     true));
    $password = inp($input, 'password', true);

    // ── Validações básicas ───────────────────────────────────

    if (!isValidEmail($email)) {
        fail('E-mail inválido.');
    }

    if (!preg_match('/^\d{6}$/', $code)) {
        fail('Código inválido.');
    }

    if (!isValidPassword($password)) {
        fail('A senha deve ter no mínimo 6 caracteres, incluindo um número e um caractere especial.');
    }

    // ── Busca o código mais recente válido ───────────────────
    $reset = dbRow(
        'SELECT id, code, attempts
         FROM password_resets
         WHERE email    = ?
           AND used     = 0
           AND expires_at > NOW()
         ORDER BY created_at DESC
         LIMIT 1',
        [$email]
    );

    // ── Limite de tentativas por código: máximo 5 ───────────
    if ($reset && (int) $reset['attempts'] >= 5) {
        // Invalida o código por excesso de tentativas
        dbExec(
            'UPDATE password_resets SET used = 1 WHERE id = ?',
            [$reset['id']]
        );
        fail('Código bloqueado por excesso de tentativas. Solicite um novo código.');
    }

    // ── Verifica o código com comparação segura ──────────────
    if (!$reset || !hash_equals($reset['code'], $code)) {
        // Incrementa tentativas se o registro existe
        if ($reset) {
            dbExec(
                'UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?',
                [$reset['id']]
            );
        }
        // Mensagem genérica — não revela se o e-mail existe
        fail('Código inválido ou expirado.');
    }

    // ── Busca o usuário para atualizar a senha ───────────────
    $user = dbRow(
        'SELECT id FROM users WHERE email = ? LIMIT 1',
        [$email]
    );

    if (!$user) {
        fail('Código inválido ou expirado.');
    }

    // ── Atualiza senha e invalida o código ───────────────────
    try {
        dbBegin();

        dbExec(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            [hashPassword($password), (int) $user['id']]
        );

        dbExec(
            'UPDATE password_resets SET used = 1 WHERE id = ?',
            [$reset['id']]
        );

        // Invalida todos os outros códigos pendentes do mesmo e-mail
        dbExec(
            'UPDATE password_resets SET used = 1
             WHERE email = ? AND used = 0',
            [$email]
        );

        dbCommit();

    } catch (Throwable $e) {
        dbRollback();
        error_log('[Florescer] Erro ao redefinir senha: ' . $e->getMessage());
        fail('Erro interno. Tente novamente.', 500);
    }

    // Encerra qualquer sessão ativa do usuário por segurança
    destroySession();

    ok(null, 'Senha redefinida com sucesso. Faça login com a nova senha.');
}