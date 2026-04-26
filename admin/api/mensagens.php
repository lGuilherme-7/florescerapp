<?php
// /admin/api/mensagens.php — florescer Admin v3.0
require_once __DIR__ . '/../includes/auth_admin.php';
require_once dirname(dirname(__DIR__)) . '/config/db.php';

// 1. Função definida ANTES de qualquer uso
function jsonOut(array $d): void {
    if (ob_get_level() > 0) ob_end_clean();
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. requireAdmin ANTES do header
requireAdmin();

// 3. Header depois
ob_start();
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonOut(['success' => false, 'message' => 'Método não permitido.']);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    jsonOut(['success' => false, 'message' => 'Payload inválido.']);
}

$action = trim($body['action'] ?? '');

switch ($action) {

    case 'create':
        $text   = trim($body['text']   ?? '');
        $author = mb_substr(trim($body['author'] ?? ''), 0, 100, 'UTF-8');
        if ($text === '') jsonOut(['success' => false, 'message' => 'A frase não pode estar vazia.']);
        if (mb_strlen($text, 'UTF-8') > 500) jsonOut(['success' => false, 'message' => 'Frase muito longa (máx. 500 caracteres).']);
        $exists = dbRow('SELECT id FROM motivational_messages WHERE message = ? LIMIT 1', [$text]);
        if ($exists) jsonOut(['success' => false, 'message' => 'Essa frase já está cadastrada.']);
        dbExec('INSERT INTO motivational_messages (message, author) VALUES (?, ?)', [$text, $author]);
        jsonOut(['success' => true, 'message' => 'Frase criada.']);

    case 'update':
        $id     = (int)($body['id']    ?? 0);
        $text   = trim($body['text']   ?? '');
        $author = mb_substr(trim($body['author'] ?? ''), 0, 100, 'UTF-8');
        if ($id <= 0)     jsonOut(['success' => false, 'message' => 'ID inválido.']);
        if ($text === '') jsonOut(['success' => false, 'message' => 'A frase não pode estar vazia.']);
        if (mb_strlen($text, 'UTF-8') > 500) jsonOut(['success' => false, 'message' => 'Frase muito longa.']);
        if (!dbRow('SELECT id FROM motivational_messages WHERE id = ? LIMIT 1', [$id]))
            jsonOut(['success' => false, 'message' => 'Frase não encontrada.']);
        $dup = dbRow('SELECT id FROM motivational_messages WHERE message = ? AND id != ? LIMIT 1', [$text, $id]);
        if ($dup) jsonOut(['success' => false, 'message' => 'Já existe outra frase com esse texto.']);
        dbExec('UPDATE motivational_messages SET message = ?, author = ? WHERE id = ?', [$text, $author, $id]);
        jsonOut(['success' => true, 'message' => 'Frase atualizada.']);

    case 'delete':
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'ID inválido.']);
        $row = dbRow('SELECT message FROM motivational_messages WHERE id = ? LIMIT 1', [$id]);
        if (!$row) jsonOut(['success' => false, 'message' => 'Frase não encontrada.']);
        $total = (int)(dbRow('SELECT COUNT(*) AS n FROM motivational_messages')['n'] ?? 0);
        if ($total <= 1) jsonOut(['success' => false, 'message' => 'Deve haver ao menos uma frase cadastrada.']);
        dbExec('DELETE FROM motivational_messages WHERE id = ?', [$id]);
        jsonOut(['success' => true, 'message' => 'Frase excluída.']);

    default:
        http_response_code(400);
        jsonOut(['success' => false, 'message' => 'Ação desconhecida.']);
}