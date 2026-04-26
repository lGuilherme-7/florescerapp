<?php
// ============================================================
// /api/grades.php — florescer v2.4
// ============================================================
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');
ob_clean();

startSession();
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Não autenticado.']);
    exit;
}

$userId = (int)currentUser()['id'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($body['action'] ?? '');

function out(array $d): void { ob_clean(); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

function assertSubjectOwner(int $subjId, int $userId): void {
    $subj = dbRow(
        'SELECT s.id FROM subjects s
         JOIN objectives o ON o.id = s.objective_id
         WHERE s.id = ? AND o.user_id = ?',
        [$subjId, $userId]
    );
    if (!$subj) out(['success'=>false,'message'=>'Matéria não encontrada ou sem permissão.']);
}

switch ($action) {

    case 'save_sub':
        $subjId = (int)($body['subject_id'] ?? 0);
        $unit   = trim($body['unit']        ?? '');
        $tipo   = trim($body['score_type']  ?? '');
        $score  = array_key_exists('score',$body) ? $body['score'] : '__missing__';

        if (!$subjId) out(['success'=>false,'message'=>'subject_id inválido.']);
        if (!$unit)   out(['success'=>false,'message'=>'Unidade inválida.']);
        if (!$tipo)   out(['success'=>false,'message'=>'Tipo de avaliação inválido.']);

        assertSubjectOwner($subjId, $userId);

        if ($score === '__missing__' || $score === null || $score === '') {
            dbQuery(
                'DELETE FROM grade_sub_scores WHERE subject_id=? AND unit=? AND score_type=?',
                [$subjId, $unit, $tipo]
            );
            out(['success'=>true, 'deleted'=>true]);
        }

        $n = (float)$score;
        if ($n < 0 || $n > 10) out(['success'=>false,'message'=>'Nota deve ser entre 0 e 10.']);

        dbQuery(
            'INSERT INTO grade_sub_scores (subject_id, unit, score_type, score)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE score=VALUES(score), updated_at=NOW()',
            [$subjId, $unit, $tipo, $n]
        );
        out(['success'=>true]);

    case 'save':
        $subjId   = (int)($body['subject_id'] ?? 0);
        $unit     = trim($body['unit']        ?? '');
        $gradeVal = $body['grade'] ?? null;

        if (!$subjId) out(['success'=>false,'message'=>'subject_id inválido.']);
        assertSubjectOwner($subjId, $userId);

        if ($gradeVal === null || $gradeVal === '') {
            if ($unit) {
                dbQuery('DELETE FROM grades WHERE subject_id=? AND unit=?', [$subjId, $unit]);
            } else {
                dbQuery('DELETE FROM grades WHERE subject_id=? AND (unit IS NULL OR unit="")', [$subjId]);
            }
            out(['success'=>true, 'deleted'=>true]);
        }

        $n = (float)$gradeVal;
        if ($n < 0 || $n > 10) out(['success'=>false,'message'=>'Nota deve ser entre 0 e 10.']);

        $cols    = array_column(dbQuery("SHOW COLUMNS FROM grades"), 'Field');
        $hasUnit = in_array('unit', $cols);

        if ($hasUnit && $unit) {
            dbQuery(
                'INSERT INTO grades (subject_id, unit, grade, period_id)
                 VALUES (?,?,?,NULL)
                 ON DUPLICATE KEY UPDATE grade=VALUES(grade), updated_at=NOW()',
                [$subjId, $unit, $n]
            );
        } else {
            $existing = dbRow(
                'SELECT id FROM grades WHERE subject_id=? AND period_id IS NULL AND (unit IS NULL OR unit="")',
                [$subjId]
            );
            if ($existing) {
                dbQuery('UPDATE grades SET grade=?, updated_at=NOW() WHERE id=?', [$n, $existing['id']]);
            } else {
                dbQuery('INSERT INTO grades (subject_id, grade, period_id) VALUES (?,?,NULL)', [$subjId, $n]);
            }
        }
        out(['success'=>true]);

    case 'get':
        $objId = (int)($body['objective_id'] ?? 0);
        if (!$objId) {
            $ao    = dbRow('SELECT id FROM objectives WHERE user_id=? AND is_active=1 ORDER BY created_at DESC LIMIT 1', [$userId]);
            $objId = $ao['id'] ?? 0;
        }
        if (!$objId) out(['success'=>false,'message'=>'Nenhum objetivo encontrado.']);

        $cols    = array_column(dbQuery("SHOW COLUMNS FROM grades"), 'Field');
        $hasUnit = in_array('unit', $cols);

        if ($hasUnit) {
            $rows = dbQuery(
                'SELECT g.subject_id, g.unit, g.grade FROM grades g
                 JOIN subjects s ON s.id=g.subject_id
                 WHERE s.objective_id=? AND s.is_active=1', [$objId]
            );
        } else {
            $rows = dbQuery(
                'SELECT g.subject_id, NULL AS unit, g.grade FROM grades g
                 JOIN subjects s ON s.id=g.subject_id
                 WHERE s.objective_id=? AND s.is_active=1 AND g.period_id IS NULL', [$objId]
            );
        }

        $map = [];
        foreach ($rows as $r) {
            $sid = (int)$r['subject_id'];
            $u   = $r['unit'] ?: 'geral';
            if (!isset($map[$sid])) $map[$sid] = [];
            $map[$sid][$u] = (float)$r['grade'];
        }
        out(['success'=>true, 'data'=>$map]);

    case 'get_sub':
        $objId = (int)($body['objective_id'] ?? 0);
        if (!$objId) {
            $ao    = dbRow('SELECT id FROM objectives WHERE user_id=? AND is_active=1 ORDER BY created_at DESC LIMIT 1', [$userId]);
            $objId = $ao['id'] ?? 0;
        }
        if (!$objId) out(['success'=>false,'message'=>'Nenhum objetivo encontrado.']);

        if (!(bool)dbRow("SHOW TABLES LIKE 'grade_sub_scores'")) {
            out(['success'=>true,'data'=>[]]);
        }

        $rows = dbQuery(
            'SELECT g.subject_id, g.unit, g.score_type, g.score
             FROM grade_sub_scores g
             JOIN subjects s ON s.id = g.subject_id
             WHERE s.objective_id = ? AND s.is_active = 1', [$objId]
        );

        $map = [];
        foreach ($rows as $r) {
            $sid = (int)$r['subject_id'];
            if (!isset($map[$sid])) $map[$sid]=[];
            if (!isset($map[$sid][$r['unit']])) $map[$sid][$r['unit']]=[];
            $map[$sid][$r['unit']][$r['score_type']] = (float)$r['score'];
        }
        out(['success'=>true,'data'=>$map]);

    case 'save_header':
        $escola = mb_substr(trim($body['escola'] ?? ''), 0, 150, 'UTF-8');
        $classe = mb_substr(trim($body['classe'] ?? ''), 0, 80,  'UTF-8');
        $ano    = mb_substr(trim($body['ano']    ?? date('Y')), 0, 20, 'UTF-8');

        if (!(bool)dbRow("SHOW TABLES LIKE 'grade_headers'")) {
            dbQuery("CREATE TABLE IF NOT EXISTS `grade_headers` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `user_id` INT UNSIGNED NOT NULL,
              `escola` VARCHAR(150) DEFAULT NULL,
              `classe` VARCHAR(80) DEFAULT NULL,
              `ano_letivo` VARCHAR(20) DEFAULT NULL,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", []);
        } else {
            $indexes = dbQuery("SHOW INDEX FROM grade_headers WHERE Key_name='uq_user'");
            if (empty($indexes)) {
                try { dbQuery("ALTER TABLE grade_headers ADD UNIQUE KEY `uq_user` (`user_id`)", []); }
                catch (\Throwable $e) {}
            }
        }

        dbQuery(
            'INSERT INTO grade_headers (user_id, escola, classe, ano_letivo) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE escola=VALUES(escola), classe=VALUES(classe), ano_letivo=VALUES(ano_letivo)',
            [$userId, $escola, $classe, $ano]
        );
        out(['success'=>true]);

    case 'get_header':
        if (!(bool)dbRow("SHOW TABLES LIKE 'grade_headers'")) {
            out(['success'=>true,'data'=>[]]);
        }
        $h = dbRow('SELECT escola, classe, ano_letivo FROM grade_headers WHERE user_id=?', [$userId]);
        out(['success'=>true,'data'=>$h??[]]);

    default:
        http_response_code(400);
        out(['success'=>false,'message'=>'Ação desconhecida: '.$action]);
}