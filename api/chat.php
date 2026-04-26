<?php
// ============================================================
// /api/chat.php — florescer v2.0
// Sistema Q&A: perguntas + respostas por categoria
// Tabela: chat_messages (id, user_id, title, message, category,
//         parent_id, is_deleted, upvotes, created_at)
// ============================================================
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

startSession();
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Não autenticado.']);
    exit;
}

$user   = currentUser();
$userId = (int)$user['id'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($body['action'] ?? '');

function out(array $d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function v(string $s, int $max=500): string { return mb_substr(trim($s),0,$max,'UTF-8'); }

// Verifica se a coluna parent_id já existe (pós-migração)
$cols = array_column(dbQuery("SHOW COLUMNS FROM chat_messages"), 'Field');
$hasQA = in_array('parent_id', $cols);

// Categorias permitidas
$CATS = ['Geral','Matemática','Português','História','Geografia','Biologia',
         'Física','Química','Inglês','Redação','ENEM','Vestibular','Outros'];

switch ($action) {

    // ── Listar perguntas (posts raiz) ─────────────────────────
    case 'list':
        $cat    = trim($body['category'] ?? '');
        $page   = max(1,(int)($body['page'] ?? 1));
        $limit  = 20;
        $offset = ($page-1)*$limit;

        if ($hasQA) {
            $where  = 'WHERE m.is_deleted=0 AND m.parent_id IS NULL';
            $params = [];
            if ($cat && $cat !== 'Todas') {
                $where  .= ' AND m.category=?';
                $params[] = $cat;
            }

            $total = (int)(dbRow(
                "SELECT COUNT(*) AS n FROM chat_messages m $where", $params
            )['n'] ?? 0);

            $params[] = $limit; $params[] = $offset;

            $posts = dbQuery(
                "SELECT m.id, m.user_id, m.title, m.message, m.category,
                        m.upvotes, m.created_at,
                        u.name AS author_name,
                        (SELECT COUNT(*) FROM chat_messages r
                         WHERE r.parent_id=m.id AND r.is_deleted=0) AS reply_count
                 FROM chat_messages m
                 JOIN users u ON u.id=m.user_id
                 $where
                 ORDER BY m.created_at DESC
                 LIMIT ? OFFSET ?",
                $params
            );
        } else {
            // Fallback pré-migração: retorna mensagens simples
            $total = (int)(dbRow('SELECT COUNT(*) AS n FROM chat_messages')['n'] ?? 0);
            $posts = dbQuery(
                'SELECT m.id, m.user_id, m.message, m.created_at,
                        u.name AS author_name
                 FROM chat_messages m
                 JOIN users u ON u.id=m.user_id
                 ORDER BY m.created_at DESC LIMIT ? OFFSET ?',
                [$limit, $offset]
            );
        }

        out(['success'=>true,'data'=>$posts,'total'=>$total,'page'=>$page]);

    // ── Carregar respostas de uma pergunta ────────────────────
    case 'replies':
        if (!$hasQA) out(['success'=>true,'data'=>[]]);
        $postId = (int)($body['post_id'] ?? 0);
        if (!$postId) out(['success'=>false,'message'=>'ID inválido.']);

        $replies = dbQuery(
            "SELECT m.id, m.user_id, m.message, m.upvotes, m.created_at,
                    u.name AS author_name
             FROM chat_messages m
             JOIN users u ON u.id=m.user_id
             WHERE m.parent_id=? AND m.is_deleted=0
             ORDER BY m.upvotes DESC, m.created_at ASC",
            [$postId]
        );

        // Marca qual resposta o usuário atual já upvotou
        foreach ($replies as &$r) {
            $r['my_upvote'] = false; // simplificado sem tabela de upvotes
        }
        unset($r);

        out(['success'=>true,'data'=>$replies]);

    // ── Criar pergunta ────────────────────────────────────────
    case 'ask':
        $title   = v($body['title']   ?? '',200);
        $message = v($body['message'] ?? '',2000);
        $cat     = in_array($body['category']??'',$CATS) ? $body['category'] : 'Geral';

        if (!$title)   out(['success'=>false,'message'=>'Informe o título da pergunta.']);
        if (!$message) out(['success'=>false,'message'=>'Descreva sua dúvida.']);

        if ($hasQA) {
            dbExec(
                'INSERT INTO chat_messages (user_id,title,message,category,parent_id)
                 VALUES (?,?,?,?,NULL)',
                [$userId,$title,$message,$cat]
            );
        } else {
            dbExec(
                'INSERT INTO chat_messages (user_id,message) VALUES (?,?)',
                [$userId,"[$cat] $title\n\n$message"]
            );
        }

        $newId = (int)dbRow('SELECT LAST_INSERT_ID() AS id')['id'];
        out(['success'=>true,'id'=>$newId]);

    // ── Responder pergunta ────────────────────────────────────
    case 'reply':
        if (!$hasQA) out(['success'=>false,'message'=>'Execute a migração SQL primeiro.']);

        $postId  = (int)($body['post_id'] ?? 0);
        $message = v($body['message'] ?? '',2000);

        if (!$postId)  out(['success'=>false,'message'=>'Post inválido.']);
        if (!$message) out(['success'=>false,'message'=>'Escreva sua resposta.']);

        // Garante que o post pai existe e não é uma resposta
        $parent = dbRow(
            'SELECT id FROM chat_messages WHERE id=? AND parent_id IS NULL AND is_deleted=0',
            [$postId]
        );
        if (!$parent) out(['success'=>false,'message'=>'Pergunta não encontrada.']);

        dbExec(
            'INSERT INTO chat_messages (user_id,message,parent_id) VALUES (?,?,?)',
            [$userId,$message,$postId]
        );

        $newId = (int)dbRow('SELECT LAST_INSERT_ID() AS id')['id'];
        $reply = dbRow(
            'SELECT m.*, u.name AS author_name
             FROM chat_messages m JOIN users u ON u.id=m.user_id WHERE m.id=?',
            [$newId]
        );

        out(['success'=>true,'id'=>$newId,'data'=>$reply]);

    // ── Upvote ────────────────────────────────────────────────
    case 'upvote':
        if (!$hasQA) out(['success'=>false,'message'=>'Execute a migração SQL primeiro.']);

        $id = (int)($body['id'] ?? 0);
        if (!$id) out(['success'=>false,'message'=>'ID inválido.']);

        dbExec('UPDATE chat_messages SET upvotes=upvotes+1 WHERE id=?', [$id]);
        $upvotes = (int)(dbRow('SELECT upvotes FROM chat_messages WHERE id=?',[$id])['upvotes']??0);

        out(['success'=>true,'upvotes'=>$upvotes]);

    // ── Excluir (só o autor ou soft-delete) ───────────────────
    case 'delete':
        $id = (int)($body['id'] ?? 0);
        if (!$id) out(['success'=>false,'message'=>'ID inválido.']);

        $msg = dbRow('SELECT user_id FROM chat_messages WHERE id=?',[$id]);
        if (!$msg) out(['success'=>false,'message'=>'Mensagem não encontrada.']);
        if ((int)$msg['user_id'] !== $userId)
            out(['success'=>false,'message'=>'Sem permissão.']);

        if ($hasQA) {
            dbExec('UPDATE chat_messages SET is_deleted=1 WHERE id=?',[$id]);
        } else {
            dbExec('DELETE FROM chat_messages WHERE id=? AND user_id=?',[$id,$userId]);
        }

        out(['success'=>true]);

    // ── Categorias disponíveis ────────────────────────────────
    case 'categories':
        out(['success'=>true,'data'=>$CATS]);

    default:
        http_response_code(400);
        out(['success'=>false,'message'=>'Ação desconhecida.']);
}