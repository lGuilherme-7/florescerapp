<?php
// /api/usuarios.php — florescer Admin v2.0
// Chamado pela view admin/views/usuarios.php via ../../api/usuarios.php
require_once __DIR__ . '/../admin/includes/auth_admin.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');
requireAdmin();

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($body['action'] ?? '');

function out(array $d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

switch ($action) {

    case 'list':
        $search  = trim($body['search'] ?? '');
        $page    = max(1, (int)($body['page']     ?? 1));
        $perPage = max(1, min(100, (int)($body['per_page'] ?? 20)));
        $offset  = ($page - 1) * $perPage;
        $orderBy = in_array($body['order_by'] ?? '', ['name','email','level','xp','streak','created_at'])
                   ? $body['order_by'] : 'created_at';
        $dir     = strtoupper($body['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $where = ''; $params = [];
        if ($search) {
            $where    = 'WHERE u.name LIKE ? OR u.email LIKE ?';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $total = (int)(dbRow("SELECT COUNT(*) AS n FROM users u $where", $params)['n'] ?? 0);
        $users = dbQuery(
            "SELECT u.id, u.name, u.email, u.level, u.xp, u.streak, u.daily_goal_min, u.created_at,
                    (SELECT COUNT(DISTINCT study_date) FROM daily_summaries WHERE user_id=u.id) AS study_days,
                    (SELECT COUNT(*) FROM objectives WHERE user_id=u.id) AS objectives_count
             FROM users u $where
             ORDER BY u.{$orderBy} {$dir}
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );
        out(['success'=>true,'data'=>$users,'total'=>$total,'page'=>$page,
             'pages'=>max(1,(int)ceil($total/$perPage))]);

    case 'get':
        $uid = (int)($body['user_id'] ?? 0);
        if (!$uid) out(['success'=>false,'message'=>'user_id inválido.']);
        $row = dbRow("SELECT id,name,email,level,xp,streak,daily_goal_min,created_at
                      FROM users WHERE id=?", [$uid]);
        if (!$row) out(['success'=>false,'message'=>'Usuário não encontrado.']);
        out(['success'=>true,'data'=>$row]);

    case 'delete_user':
        $uid = (int)($body['user_id'] ?? 0);
        if (!$uid) out(['success'=>false,'message'=>'user_id inválido.']);
        if (!dbRow('SELECT id FROM users WHERE id=?', [$uid]))
            out(['success'=>false,'message'=>'Usuário não encontrado.']);

        // Remove foto se existir
        try {
            $u = dbRow('SELECT avatar_url FROM users WHERE id=?', [$uid]);
            if ($u && $u['avatar_url']) {
                $p = __DIR__ . '/../public' . $u['avatar_url'];
                if (file_exists($p)) @unlink($p);
            }
        } catch (\Throwable $e) {}

        // Cascade por tabelas auxiliares
        foreach (['sim_penalties','sim_attempts','works','chat_messages',
                  'user_profile','grade_headers','daily_summaries','grades'] as $tbl) {
            try { dbExec("DELETE FROM {$tbl} WHERE user_id=?", [$uid]); }
            catch (\Throwable $e) {}
        }

        // Cascade por objetivos → matérias → assuntos → aulas → notas
        $objs = dbQuery('SELECT id FROM objectives WHERE user_id=?', [$uid]);
        foreach ($objs as $o) {
            $subjs = dbQuery('SELECT id FROM subjects WHERE objective_id=?', [$o['id']]);
            foreach ($subjs as $s) {
                $topics = dbQuery('SELECT id FROM topics WHERE subject_id=?', [$s['id']]);
                foreach ($topics as $t) {
                    $lessons = dbQuery('SELECT id FROM lessons WHERE topic_id=?', [$t['id']]);
                    foreach ($lessons as $l) {
                        foreach (['lesson_notes','notes'] as $nt) {
                            try { dbExec("DELETE FROM {$nt} WHERE lesson_id=?", [$l['id']]); }
                            catch (\Throwable $e) {}
                        }
                    }
                    dbExec('DELETE FROM lessons WHERE topic_id=?', [$t['id']]);
                }
                dbExec('DELETE FROM topics WHERE subject_id=?', [$s['id']]);
            }
            dbExec('DELETE FROM subjects WHERE objective_id=?', [$o['id']]);
        }
        dbExec('DELETE FROM objectives WHERE user_id=?', [$uid]);
        dbExec('DELETE FROM users WHERE id=?', [$uid]);
        out(['success'=>true]);

    case 'reset_password':
        // Chamado pela view via ../../api/profile.php com action:'admin_reset_password'
        // mas também aceita diretamente aqui
        $uid  = (int)($body['user_id'] ?? 0);
        $pass = trim($body['password'] ?? '');
        if (!$uid)          out(['success'=>false,'message'=>'user_id inválido.']);
        if (strlen($pass)<6) out(['success'=>false,'message'=>'Senha deve ter mínimo 6 caracteres.']);
        if (!dbRow('SELECT id FROM users WHERE id=?', [$uid]))
            out(['success'=>false,'message'=>'Usuário não encontrado.']);
        dbExec('UPDATE users SET password_hash=? WHERE id=?',
               [password_hash($pass, PASSWORD_BCRYPT), $uid]);
        out(['success'=>true]);

    case 'stats':
        $total    = (int)(dbRow('SELECT COUNT(*) AS n FROM users')['n'] ?? 0);
        $today    = 0; $week = 0;
        try {
            $today = (int)(dbRow(
                'SELECT COUNT(DISTINCT user_id) AS n FROM daily_summaries WHERE study_date=CURDATE()'
            )['n'] ?? 0);
        } catch (\Throwable $e) {}
        try {
            $week = (int)(dbRow(
                "SELECT COUNT(*) AS n FROM users WHERE created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)"
            )['n'] ?? 0);
        } catch (\Throwable $e) {}
        out(['success'=>true,'data'=>[
            'total'=>$total,'active_today'=>$today,'new_this_week'=>$week
        ]]);

    default:
        http_response_code(400);
        out(['success'=>false,'message'=>'Ação desconhecida: "'.$action.'"']);
}