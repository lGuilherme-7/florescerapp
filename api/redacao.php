<?php
// /api/redacao.php — florescer v2.0
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

startSession();
header('Content-Type: application/json; charset=UTF-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Não autenticado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Método não permitido.']);
    exit;
}

$user   = currentUser();
$userId = (int)($user['id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Sessão inválida.']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($body['action'] ?? '');

function jout(array $d): void {
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

// Garante tabela
// Garante tabela
try {
    dbRow("SELECT 1 FROM sim_redacao_entregas LIMIT 1");
} catch (\Throwable $e) {
    try {
        dbExec("CREATE TABLE sim_redacao_entregas (
            id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id       INT UNSIGNED NOT NULL,
            redacao_id    INT UNSIGNED NOT NULL,
            vestibular_id INT UNSIGNED NOT NULL,
            texto         TEXT NOT NULL,
            created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Throwable $e2) {}
}

if ($action === 'get') {
    $redacaoId = (int)($body['redacao_id'] ?? 0);
    if (!$redacaoId) jout(['success'=>false,'message'=>'ID inválido.']);
    $row = dbRow(
    'SELECT texto_aluno AS texto, created_at AS updated_at FROM sim_redacao_entregas
     WHERE user_id=? AND redacao_id=? ORDER BY created_at DESC LIMIT 1',
    [$userId, $redacaoId]
);
    jout(['success'=>true,'data'=>$row ?: null]);
}

if ($action === 'save') {
    $redacaoId    = (int)($body['redacao_id']    ?? 0);
    $vestibularId = (int)($body['vestibular_id'] ?? 0);
    $texto        = trim($body['texto']          ?? '');

    if (!$redacaoId)    jout(['success'=>false,'message'=>'ID do tema inválido.']);
    if (!$vestibularId) jout(['success'=>false,'message'=>'ID do vestibular inválido.']);
    if (!$texto)        jout(['success'=>false,'message'=>'Redação vazia.']);
    if (mb_strlen($texto,'UTF-8') < 20) jout(['success'=>false,'message'=>'Escreva mais.']);

    $tema = dbRow('SELECT id FROM sim_redacoes WHERE id=? AND is_active=1', [$redacaoId]);
    if (!$tema) jout(['success'=>false,'message'=>'Tema não encontrado.']);

    $existing = dbRow(
        'SELECT id FROM sim_redacao_entregas WHERE user_id=? AND redacao_id=?',
        [$userId, $redacaoId]
    );
    if ($existing) {
        dbExec(
    'UPDATE sim_redacao_entregas SET texto_aluno=?, vestibular_id=? WHERE user_id=? AND redacao_id=?',
    [$texto, $vestibularId, $userId, $redacaoId]
);
    } else {
        dbExec(
    'INSERT INTO sim_redacao_entregas (user_id, redacao_id, vestibular_id, texto_aluno) VALUES (?,?,?,?)',
    [$userId, $redacaoId, $vestibularId, $texto]
);
    }
    jout(['success'=>true,'message'=>'Redação salva!']);
}

if ($action === 'list') {
    $rows = dbQuery(
        'SELECT e.id, e.redacao_id, e.vestibular_id, e.updated_at,
                r.tema, v.name AS vest_name, LENGTH(e.texto) AS chars
         FROM sim_redacao_entregas e
         JOIN sim_redacoes r ON r.id=e.redacao_id
         JOIN sim_vestibulares v ON v.id=e.vestibular_id
         WHERE e.user_id=? ORDER BY e.updated_at DESC',
        [$userId]
    );
    jout(['success'=>true,'data'=>$rows]);
}

http_response_code(400);
jout(['success'=>false,'message'=>'Ação desconhecida: "'.$action.'"']);