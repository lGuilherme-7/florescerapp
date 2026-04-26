<?php
// /api/profile.php — florescer v2.0
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');

function out(array $d): void {
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

// Lê body UMA VEZ antes de qualquer verificação de auth
$isMultipart = !empty($_FILES);
if ($isMultipart) {
    $action = trim($_POST['action'] ?? '');
    $body   = $_POST;
} else {
    $raw    = file_get_contents('php://input');
    $body   = json_decode($raw, true) ?? [];
    $action = trim($body['action'] ?? '');
}

// admin_reset_password usa sessão admin — trata ANTES de verificar sessão de usuário
if ($action === 'admin_reset_password') {
    $adminAuth = __DIR__ . '/../admin/includes/auth_admin.php';
    if (!file_exists($adminAuth)) out(['success'=>false,'message'=>'auth_admin não encontrado.']);
    require_once $adminAuth;
    adminStartSession();
    if (empty($_SESSION['admin_logged_in']) || empty($_SESSION['admin_id']))
        out(['success'=>false,'message'=>'Sem permissão.']);
    $targetId = (int)($body['user_id'] ?? 0);
    $newPass  = trim($body['password'] ?? '');
    if (!$targetId)          out(['success'=>false,'message'=>'user_id inválido.']);
    if (strlen($newPass) < 6) out(['success'=>false,'message'=>'Mínimo 6 caracteres.']);
    if (!dbRow('SELECT id FROM users WHERE id=?', [$targetId]))
        out(['success'=>false,'message'=>'Usuário não encontrado.']);
    dbExec('UPDATE users SET password_hash=? WHERE id=?',
           [password_hash($newPass, PASSWORD_BCRYPT), $targetId]);
    out(['success'=>true]);
}

// Para todas as outras actions, exige sessão de usuário
startSession();
if (!isLoggedIn()) {
    http_response_code(401);
    out(['success'=>false,'message'=>'Não autenticado.']);
}

$user   = currentUser();
$userId = (int)$user['id'];

// Garante colunas extras em users
function ensureCols(): void {
    $cols = array_column(dbQuery("SHOW COLUMNS FROM users"), 'Field');
    $add  = [];
    if (!in_array('nickname',     $cols)) $add[] = "ADD COLUMN nickname     VARCHAR(60)  DEFAULT NULL";
    if (!in_array('school',       $cols)) $add[] = "ADD COLUMN school       VARCHAR(120) DEFAULT NULL";
    if (!in_array('city',         $cols)) $add[] = "ADD COLUMN city         VARCHAR(80)  DEFAULT NULL";
    if (!in_array('bio',          $cols)) $add[] = "ADD COLUMN bio          VARCHAR(280) DEFAULT NULL";
    if (!in_array('class_grade',  $cols)) $add[] = "ADD COLUMN class_grade  VARCHAR(60)  DEFAULT NULL";
    if (!in_array('avatar_emoji', $cols)) $add[] = "ADD COLUMN avatar_emoji VARCHAR(10)  DEFAULT NULL";
    if (!in_array('avatar_url',   $cols)) $add[] = "ADD COLUMN avatar_url   VARCHAR(500) DEFAULT NULL";
    if (!in_array('avatar_type',  $cols)) $add[] = "ADD COLUMN avatar_type  VARCHAR(20)  DEFAULT 'initial'";
    if ($add) dbExec("ALTER TABLE users " . implode(', ', $add));
}
ensureCols();

switch ($action) {

    case 'update_personal':
        $name     = trim($body['name']     ?? '');
        $nickname = mb_substr(trim($body['nickname'] ?? ''), 0, 60,  'UTF-8');
        $school   = mb_substr(trim($body['school']   ?? ''), 0, 120, 'UTF-8');
        $city     = mb_substr(trim($body['city']     ?? ''), 0, 80,  'UTF-8');
        $bio      = mb_substr(trim($body['bio']      ?? ''), 0, 280, 'UTF-8');
        if (!$name) out(['success'=>false,'message'=>'Informe seu nome.']);
        dbExec(
            'UPDATE users SET name=?,nickname=?,school=?,city=?,bio=? WHERE id=?',
            [htmlspecialchars($name,ENT_QUOTES,'UTF-8'), $nickname?:null,
             $school?:null, $city?:null, $bio?:null, $userId]
        );
       $_SESSION['user_name'] = $name;
        out(['success'=>true]);

    case 'update_goal':
        $goalMin    = max(5, min(480, (int)($body['daily_goal_min'] ?? 30)));
        $classGrade = mb_substr(trim($body['class_grade'] ?? ''), 0, 60, 'UTF-8');
        dbExec('UPDATE users SET daily_goal_min=?,class_grade=? WHERE id=?',
               [$goalMin, $classGrade?:null, $userId]);
        out(['success'=>true]);

    case 'change_password':
        $currentPass = $body['current_password'] ?? '';
        $newPass     = $body['new_password']     ?? '';
        if (!$currentPass || !$newPass)
            out(['success'=>false,'message'=>'Preencha todos os campos.']);
        if (strlen($newPass) < 6)
            out(['success'=>false,'message'=>'Mínimo 6 caracteres.']);
        if (!preg_match('/\d/', $newPass))
            out(['success'=>false,'message'=>'A nova senha precisa ter ao menos 1 número.']);
        $row = dbRow('SELECT password_hash FROM users WHERE id=?', [$userId]);
        if (!$row || !password_verify($currentPass, $row['password_hash']))
            out(['success'=>false,'message'=>'Senha atual incorreta.']);
        dbExec('UPDATE users SET password_hash=? WHERE id=?',
               [password_hash($newPass, PASSWORD_BCRYPT), $userId]);
        out(['success'=>true]);

    case 'upload_photo':
        $file = $_FILES['photo'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK)
            out(['success'=>false,'message'=>'Erro no upload: '.($file['error']??'?')]);
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        $mime    = mime_content_type($file['tmp_name']);
        if (!isset($allowed[$mime]))
            out(['success'=>false,'message'=>'Use JPG, PNG ou WEBP.']);
        if ($file['size'] > 2*1024*1024)
            out(['success'=>false,'message'=>'Máx. 2MB.']);
        $uploadDir = __DIR__ . '/../public/uploads/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $old = dbRow('SELECT avatar_url FROM users WHERE id=?', [$userId]);
        if ($old && $old['avatar_url']) {
            $op = __DIR__ . '/../public' . $old['avatar_url'];
            if (file_exists($op)) @unlink($op);
        }
        $ext      = $allowed[$mime];
        $filename = 'avatar_'.$userId.'_'.time().'.'.$ext;
        $dest     = $uploadDir.$filename;
        if (!move_uploaded_file($file['tmp_name'], $dest))
            out(['success'=>false,'message'=>'Falha ao salvar. Verifique permissões.']);
        $relUrl = '/uploads/avatars/'.$filename;
        dbExec("UPDATE users SET avatar_url=?,avatar_emoji=NULL,avatar_type='upload' WHERE id=?",
               [$relUrl, $userId]);
        out(['success'=>true,'url'=>'/florescer/public'.$relUrl]);

    case 'remove_photo':
        $row = dbRow('SELECT avatar_url FROM users WHERE id=?', [$userId]);
        if ($row && $row['avatar_url']) {
            $p = __DIR__ . '/../public' . $row['avatar_url'];
            if (file_exists($p)) @unlink($p);
        }
        dbExec("UPDATE users SET avatar_url=NULL,avatar_emoji=NULL,avatar_type='initial' WHERE id=?",
               [$userId]);
        out(['success'=>true]);

    case 'set_avatar_emoji':
        $emoji = mb_substr(trim($body['emoji'] ?? ''), 0, 10, 'UTF-8');
        if (!$emoji) out(['success'=>false,'message'=>'Emoji inválido.']);
        dbExec("UPDATE users SET avatar_emoji=?,avatar_url=NULL,avatar_type='emoji' WHERE id=?",
               [$emoji, $userId]);
        out(['success'=>true]);

    case 'delete_account':
        $pass = $body['password'] ?? '';
        if (!$pass) out(['success'=>false,'message'=>'Informe sua senha.']);
        $row = dbRow('SELECT password,avatar_url FROM users WHERE id=?', [$userId]);
        if (!$row || !password_verify($pass, $row['password']))
            out(['success'=>false,'message'=>'Senha incorreta.']);
        if ($row['avatar_url']) {
            $p = __DIR__ . '/../public' . $row['avatar_url'];
            if (file_exists($p)) @unlink($p);
        }
        foreach (['sim_penalties','sim_attempts','works','chat_messages','user_profile','grade_headers'] as $tbl) {
            try { dbExec("DELETE FROM {$tbl} WHERE user_id=?", [$userId]); }
            catch (\Throwable $e) {}
        }
        dbExec('DELETE FROM users WHERE id=?', [$userId]);
        session_destroy();
        out(['success'=>true]);

    default:
        http_response_code(400);
        out(['success'=>false,'message'=>'Ação desconhecida: "'.$action.'"']);
}