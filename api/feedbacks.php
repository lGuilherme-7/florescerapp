<?php
// /api/feedbacks.php — florescer v2.0
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

ob_start();
startSession();

if (!isLoggedIn()) {
    ob_end_clean();
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success'=>false,'message'=>'Não autenticado.']);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');
$user   = currentUser();
$userId = (int)$user['id'];

function out(array $d): void {
    if (ob_get_level() > 0) ob_end_clean();
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

// Garante que a tabela existe
dbExec("CREATE TABLE IF NOT EXISTS feedbacks (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    type        VARCHAR(20)  NOT NULL DEFAULT 'sugestao',
    title       VARCHAR(150) NOT NULL,
    message     TEXT         NOT NULL,
    status      VARCHAR(20)  NOT NULL DEFAULT 'aberto',
    admin_reply TEXT         DEFAULT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$raw    = file_get_contents('php://input');
$body   = json_decode($raw, true) ?? [];
$action = trim($body['action'] ?? '');

switch ($action) {

    // Lista feedbacks do usuário logado
    case 'list':
        $rows = dbQuery(
            "SELECT id, type, title, message, status, admin_reply, created_at, updated_at
             FROM feedbacks WHERE user_id=? ORDER BY created_at DESC",
            [$userId]
        );
        out(['success'=>true,'data'=>$rows]);

    // Envia novo feedback
    case 'create':
        $type    = in_array($body['type']??'',['sugestao','bug','elogio','duvida'])
                   ? $body['type'] : 'sugestao';
        $title   = mb_substr(trim($body['title']  ?? ''), 0, 150, 'UTF-8');
        $message = mb_substr(trim($body['message'] ?? ''), 0, 2000, 'UTF-8');

        if (!$title)   out(['success'=>false,'message'=>'Informe um título.']);
        if (!$message) out(['success'=>false,'message'=>'Escreva sua mensagem.']);

        dbExec(
            "INSERT INTO feedbacks (user_id, type, title, message) VALUES (?,?,?,?)",
            [$userId, $type, $title, $message]
        );
        $newId = (int)(dbRow('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
        $new   = dbRow('SELECT * FROM feedbacks WHERE id=?', [$newId]);
        out(['success'=>true,'data'=>$new]);

    // Deleta feedback do usuário (só se ainda aberto)
    case 'delete':
        $id = (int)($body['id'] ?? 0);
        if (!$id) out(['success'=>false,'message'=>'ID inválido.']);
        $row = dbRow('SELECT id, status FROM feedbacks WHERE id=? AND user_id=?', [$id,$userId]);
        if (!$row) out(['success'=>false,'message'=>'Não encontrado.']);
        if ($row['status'] !== 'aberto') out(['success'=>false,'message'=>'Só é possível excluir feedbacks abertos.']);
        dbExec('DELETE FROM feedbacks WHERE id=?', [$id]);
        out(['success'=>true]);

    default:
        http_response_code(400);
        out(['success'=>false,'message'=>'Ação desconhecida.']);
}