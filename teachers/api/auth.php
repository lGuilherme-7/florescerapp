<?php
// ============================================================
// /florescer/teachers/api/auth.php
// Ações: login | logout | candidatura | forgot | reset
// ============================================================

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startTeacherSession();
ob_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Método não permitido.', 405);

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($body['action'] ?? '');

// ── Login ──────────────────────────────────────────────────────
if ($action === 'login') {
    $email = trim($body['email']    ?? '');
    $pass  = trim($body['password'] ?? '');
    if (!$email || !$pass) fail('Preencha todos os campos.');

    $t = dbRow('SELECT * FROM teachers WHERE email = ?', [$email]);
    if (!$t || !password_verify($pass, $t['password'])) {
        fail('E-mail ou senha incorretos.');
    }
    if ($t['status'] === 'pendente') {
        fail('Sua candidatura ainda está em análise. Aguarde o e-mail de aprovação.');
    }
    if ($t['status'] === 'suspenso') {
        fail('Conta suspensa por violação dos termos de uso.');
    }

    loginTeacherSession($t);
    dbExec('UPDATE teachers SET last_login = NOW() WHERE id = ?', [(int)$t['id']]);
    ok(['name' => $t['name']], 'Bem-vindo de volta!');
}

// ── Candidatura ────────────────────────────────────────────────
if ($action === 'candidatura') {
    $name      = mb_substr(trim($body['name']      ?? ''), 0, 100, 'UTF-8');
    $email     = trim($body['email']     ?? '');
    $subjects  = mb_substr(trim($body['subjects']  ?? ''), 0, 200, 'UTF-8');
    $formacao  = mb_substr(trim($body['formacao']  ?? ''), 0, 150, 'UTF-8');
    $exp       = trim($body['exp']       ?? '');
    $bio       = mb_substr(trim($body['bio']       ?? ''), 0, 1000, 'UTF-8');
    $link      = mb_substr(trim($body['link']      ?? ''), 0, 255, 'UTF-8');
    $diploma   = $body['diploma']      ?? null; // base64
    $dipName   = mb_substr(trim($body['diploma_name'] ?? ''), 0, 100, 'UTF-8');
    $dipType   = trim($body['diploma_type'] ?? '');

    // Validações
    if (!$name)    fail('Nome obrigatório.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('E-mail inválido.');
    if (!$subjects) fail('Informe as matérias.');
    if (!$formacao) fail('Informe a formação acadêmica.');
    if (!$exp)      fail('Selecione o tempo de experiência.');
    if (mb_strlen($bio, 'UTF-8') < 100) fail('Biografia muito curta (mínimo 100 caracteres).');

    // Verifica duplicata
    $exists = dbRow('SELECT id, status FROM teachers WHERE email = ?', [$email]);
    if ($exists) {
        if ($exists['status'] === 'suspenso') {
            fail('Este e-mail está banido da plataforma.');
        }
        if ($exists['status'] === 'pendente') {
            fail('Já existe uma candidatura pendente com este e-mail.');
        }
        fail('Este e-mail já está cadastrado. Use a aba "Entrar".');
    }

    // Salva diploma em disco (opcional — se não tiver pasta, ignora)
    $diplomaPath = null;
    if ($diploma && $dipName) {
        $uploadDir = dirname(__DIR__) . '/uploads/diplomas/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
        $ext  = pathinfo($dipName, PATHINFO_EXTENSION);
        $safe = preg_replace('/[^a-zA-Z0-9]/', '', $name) . '_' . time() . '.' . $ext;
        $full = $uploadDir . $safe;
        if (@file_put_contents($full, base64_decode($diploma)) !== false) {
            $diplomaPath = '/florescer/teachers/uploads/diplomas/' . $safe;
        }
    }

    // Monta bio completa com dados extras
    $bioCompleta = $bio
        . "\n\n[Formação: {$formacao}]"
        . "\n[Experiência: {$exp}]"
        . "\n[Matérias: {$subjects}]"
        . ($link ? "\n[Link: {$link}]" : '');

    // Insere como pendente — sem senha ainda
    dbExec(
        'INSERT INTO teachers (name, email, password, bio, avatar_url, status)
         VALUES (?, ?, ?, ?, ?, "pendente")',
        [
            $name,
            $email,
            password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT), // senha aleatória temporária
            $bioCompleta,
            $diplomaPath,
        ]
    );

    // Salva matérias
    $teacherId = (int)dbLastId();
    foreach (array_slice(array_map('trim', explode(',', $subjects)), 0, 10) as $s) {
        if ($s) dbExec(
            'INSERT INTO teacher_subjects (teacher_id, name) VALUES (?, ?)',
            [$teacherId, mb_substr($s, 0, 80, 'UTF-8')]
        );
    }

    // TODO: enviar e-mail de confirmação para o professor
    // TODO: enviar notificação para admin

    ok(null, 'Candidatura enviada! Responderemos em até 48h.');
}

// ── Logout ─────────────────────────────────────────────────────
if ($action === 'logout') {
    destroyTeacherSession();
    ok(null, 'Sessão encerrada.');
}

// ── Recuperar senha ────────────────────────────────────────────
if ($action === 'forgot') {
    $email = trim($body['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('E-mail inválido.');

    $t = dbRow('SELECT id, status FROM teachers WHERE email = ?', [$email]);
    if ($t) {
        if ($t['status'] === 'suspenso') {
            fail('Esta conta está suspensa. Entre em contato com o suporte.');
        }
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        dbExec(
            'UPDATE teachers SET reset_token = ?, reset_expires = ? WHERE id = ?',
            [$token, $expires, (int)$t['id']]
        );
        // TODO: enviar e-mail com link de reset
    }
    ok(null, 'Se o e-mail estiver cadastrado e aprovado, você receberá as instruções em breve.');
}

// ── Reset senha ────────────────────────────────────────────────
if ($action === 'reset') {
    $token = trim($body['token']    ?? '');
    $pass  = trim($body['password'] ?? '');

    if (!$token) fail('Token inválido.');
    if (strlen($pass) < 6) fail('Senha deve ter ao menos 6 caracteres.');

    $t = dbRow(
        'SELECT id FROM teachers WHERE reset_token = ? AND reset_expires > NOW()',
        [$token]
    );
    if (!$t) fail('Token inválido ou expirado. Solicite um novo.');

    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
    dbExec(
        'UPDATE teachers SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?',
        [$hash, (int)$t['id']]
    );
    ok(null, 'Senha redefinida! Faça login.');
}

// ── Trocar senha (logado) ──────────────────────────────────────
if ($action === 'change_password') {
    $teacherId = requireTeacherApi();
    $pass      = trim($body['password'] ?? '');
    if (strlen($pass) < 6) fail('Senha deve ter ao menos 6 caracteres.');
    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
    dbExec('UPDATE teachers SET password = ? WHERE id = ?', [$hash, $teacherId]);
    ok(null, 'Senha alterada com sucesso!');
}

fail('Ação desconhecida.');