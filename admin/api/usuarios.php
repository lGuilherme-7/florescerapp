<?php
// /admin/api/usuarios.php — florescer Admin v3.0
require_once __DIR__ . '/../includes/auth_admin.php';
require_once dirname(dirname(__DIR__)) . '/config/db.php';

function jout(array $d): void {
    if (ob_get_level() > 0) ob_end_clean();
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

requireAdmin();
ob_start();
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jout(['success'=>false,'message'=>'Método não permitido.']);
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($body['action'] ?? '');

if ($action === 'reset_password') {
    $userId   = (int)($body['user_id']  ?? 0);
    $password = trim($body['password']  ?? '');

    if ($userId <= 0)       jout(['success'=>false,'message'=>'ID inválido.']);
    if (strlen($password) < 6) jout(['success'=>false,'message'=>'Senha deve ter ao menos 6 caracteres.']);

    $user = dbRow('SELECT id FROM users WHERE id=?', [$userId]);
    if (!$user) jout(['success'=>false,'message'=>'Usuário não encontrado.']);

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);
    dbExec('UPDATE users SET password=? WHERE id=?', [$hash, $userId]);

    jout(['success'=>true,'message'=>'Senha alterada com sucesso.']);
}

if ($action === 'delete_user') {
    $userId = (int)($body['user_id'] ?? 0);
    if ($userId <= 0) jout(['success'=>false,'message'=>'ID inválido.']);

    $user = dbRow('SELECT id, name FROM users WHERE id=?', [$userId]);
    if (!$user) jout(['success'=>false,'message'=>'Usuário não encontrado.']);

    // Protege contra exclusão do próprio admin
    $adminEmail = $_SESSION['admin_email'] ?? '';
    $userEmail  = dbRow('SELECT email FROM users WHERE id=?', [$userId])['email'] ?? '';
    if ($adminEmail && $adminEmail === $userEmail)
        jout(['success'=>false,'message'=>'Não é possível excluir sua própria conta.']);

    // Exclui dados relacionados
    $tables = [
        'daily_summaries'       => 'user_id',
        'study_sessions'        => 'user_id',
        'sim_attempts'          => 'user_id',
        'sim_redacao_entregas'  => 'user_id',
        'sim_penalties'         => 'user_id',
        'objectives'            => 'user_id',
        'calendar_events'       => 'user_id',
        'grade_headers'         => 'user_id',
    ];
    foreach ($tables as $table => $col) {
        try {
            dbExec("DELETE FROM {$table} WHERE {$col}=?", [$userId]);
        } catch (\Throwable $e) {
            // tabela pode não existir — ignora
        }
    }

    // Exclui objetivos e seus filhos
    $objIds = array_column(
        dbQuery('SELECT id FROM objectives WHERE user_id=?', [$userId]),
        'id'
    );
    foreach ($objIds as $oid) {
        $subjIds = array_column(
            dbQuery('SELECT id FROM subjects WHERE objective_id=?', [$oid]),
            'id'
        );
        foreach ($subjIds as $sid) {
            try { dbExec('DELETE FROM topics WHERE subject_id=?', [$sid]); } catch(\Throwable $e){}
            try { dbExec('DELETE FROM grades WHERE subject_id=?', [$sid]); } catch(\Throwable $e){}
            try { dbExec('DELETE FROM grade_sub_scores WHERE subject_id=?', [$sid]); } catch(\Throwable $e){}
        }
        try { dbExec('DELETE FROM subjects WHERE objective_id=?', [$oid]); } catch(\Throwable $e){}
    }
    try { dbExec('DELETE FROM objectives WHERE user_id=?', [$userId]); } catch(\Throwable $e){}

    // Exclui o usuário
    dbExec('DELETE FROM users WHERE id=?', [$userId]);

    jout(['success'=>true,'message'=>'Usuário excluído com sucesso.']);
}

http_response_code(400);
jout(['success'=>false,'message'=>'Ação desconhecida: "'.$action.'"']);