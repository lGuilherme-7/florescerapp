<?php
// ============================================================
// /api/simulated.php — florescer v2.0
// ============================================================
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

ob_start(); // captura qualquer warning/erro acidental

startSession();
if (!isLoggedIn()) {
    ob_end_clean();
    http_response_code(401);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success'=>false,'message'=>'Não autenticado.']);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

$user   = currentUser();
$userId = (int)$user['id'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($body['action'] ?? '');

function out(array $d): void {
    ob_end_clean();
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

function safeDbExec(string $sql, array $params = []): void {
    try {
        dbExec($sql, $params);
    } catch (\Throwable $e) {
        out(['success'=>false,'message'=>'Erro BD: '.$e->getMessage()]);
    }
}

function safeDbRow(string $sql, array $params = []): ?array {
    try {
        return dbRow($sql, $params) ?: null;
    } catch (\Throwable $e) {
        return null;
    }
}

function safeDbQuery(string $sql, array $params = []): array {
    try {
        return dbQuery($sql, $params) ?? [];
    } catch (\Throwable $e) {
        return [];
    }
}

// Garante tabelas necessárias (com TEXT em vez de JSON — compatível MySQL 5.x)
function ensureTables(): void {
    // Colunas extras em sim_vestibulares
    $cols = array_column(dbQuery("SHOW COLUMNS FROM sim_vestibulares"), 'Field');
    $alts = [];
    if (!in_array('category',    $cols)) $alts[] = "ADD COLUMN category    VARCHAR(30)  DEFAULT 'vestibular'";
    if (!in_array('badge',       $cols)) $alts[] = "ADD COLUMN badge       VARCHAR(20)  DEFAULT ''";
    if (!in_array('time_min',    $cols)) $alts[] = "ADD COLUMN time_min    SMALLINT     DEFAULT 0";
    if (!in_array('time_max',    $cols)) $alts[] = "ADD COLUMN time_max    SMALLINT     DEFAULT 60";
    if (!in_array('grade_level', $cols)) $alts[] = "ADD COLUMN grade_level VARCHAR(80)  DEFAULT NULL";
    if (!in_array('sort_order',  $cols)) $alts[] = "ADD COLUMN sort_order  SMALLINT     DEFAULT 0";
    if ($alts) dbExec("ALTER TABLE sim_vestibulares " . implode(',', $alts));

    // sim_attempts com TEXT (não JSON — compatível com MySQL 5.x/MariaDB antigo)
    dbExec("CREATE TABLE IF NOT EXISTS sim_attempts (
        id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id          INT UNSIGNED NOT NULL,
        vestibular_id    INT UNSIGNED NOT NULL,
        started_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        finished_at      TIMESTAMP NULL DEFAULT NULL,
        score            SMALLINT  DEFAULT 0,
        total_questions  SMALLINT  DEFAULT 0,
        answers          TEXT,
        tab_switches     TINYINT   DEFAULT 0,
        INDEX idx_ua (user_id),
        INDEX idx_va (vestibular_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // sim_penalties
    dbExec("CREATE TABLE IF NOT EXISTS sim_penalties (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id       INT UNSIGNED NOT NULL UNIQUE,
        penalized_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        until         DATE NOT NULL,
        reason        VARCHAR(200) DEFAULT 'Troca de aba excessiva',
        INDEX idx_up (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

try {
    ensureTables();
} catch (\Throwable $e) {
    // Se falhar (tabelas já existem com estrutura diferente), continua
}

function getCfg(string $key, string $default = ''): string {
    try {
        $r = dbRow("SELECT value FROM system_config WHERE key_name=? LIMIT 1", [$key]);
        return $r['value'] ?? $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

switch ($action) {

    // ── Lista vestibulares ────────────────────────────────────
    case 'list':
        $vests = safeDbQuery(
            "SELECT v.id, v.name,
                    COALESCE(v.category,'vestibular') AS category,
                    COALESCE(v.badge,'') AS badge,
                    v.description,
                    COALESCE(v.time_min,0) AS time_min,
                    COALESCE(v.time_max,60) AS time_max,
                    COALESCE(v.grade_level,'') AS grade_level,
                    COALESCE(v.sort_order,0) AS sort_order,
                    COUNT(q.id) AS total_questions
             FROM sim_vestibulares v
             LEFT JOIN sim_questions q ON q.vestibular_id=v.id AND q.is_active=1
             WHERE v.is_active=1
             GROUP BY v.id
             ORDER BY COALESCE(v.sort_order,0) ASC, v.id ASC"
        );
        out(['success'=>true,'data'=>$vests]);

    // ── Verifica penalização ──────────────────────────────────
    case 'check_penalty':
        $pen = safeDbRow(
            'SELECT until FROM sim_penalties WHERE user_id=? AND until >= CURDATE()',
            [$userId]
        );
        out(['success'=>true,'penalized'=>(bool)$pen,'until'=>$pen['until']??null]);

    // ── Busca questões (preview) ──────────────────────────────
    case 'get_questions':
        $vestId = (int)($body['vestibular_id'] ?? 0);
        if (!$vestId) out(['success'=>false,'message'=>'vestibular_id inválido.']);

        $vest = safeDbRow('SELECT * FROM sim_vestibulares WHERE id=? AND is_active=1', [$vestId]);
        if (!$vest) out(['success'=>false,'message'=>'Simulado não encontrado ou inativo.']);

        $qtd = max(1, (int)getCfg('sim_questions_count', '10'));

        $questions = safeDbQuery(
            'SELECT id, area, subject_tag, difficulty, origin, year, statement,
                    option_a, option_b, option_c, option_d, option_e
             FROM sim_questions
             WHERE vestibular_id=? AND is_active=1
             ORDER BY RAND()
             LIMIT ?',
            [$vestId, $qtd]
        );

        if (empty($questions))
            out(['success'=>false,'message'=>'Este simulado não tem questões cadastradas.']);

        $topics = array_values(array_unique(array_filter(
            array_column($questions, 'subject_tag')
        )));

        out(['success'=>true,'vest'=>$vest,'questions'=>$questions,'topics'=>$topics]);

    // ── Inicia tentativa ──────────────────────────────────────
    case 'start':
        $vestId = (int)($body['vestibular_id'] ?? 0);
        if (!$vestId) out(['success'=>false,'message'=>'vestibular_id inválido.']);

        // Penalização
        $pen = safeDbRow(
            'SELECT until FROM sim_penalties WHERE user_id=? AND until >= CURDATE()',
            [$userId]
        );
        if ($pen) out([
            'success'=>false,
            'message'=>'Você está penalizado até '.($pen['until']??'?').'. Acesso bloqueado.',
            'penalized'=>true
        ]);

        $qtd = max(1, (int)getCfg('sim_questions_count', '10'));

        safeDbExec(
            'INSERT INTO sim_attempts (user_id, vestibular_id, total_questions, answers)
             VALUES (?, ?, ?, ?)',
            [$userId, $vestId, $qtd, '{}']
        );

        $row = safeDbRow('SELECT LAST_INSERT_ID() AS id');
        $attemptId = (int)($row['id'] ?? 0);
        if (!$attemptId) out(['success'=>false,'message'=>'Falha ao criar tentativa.']);

        out(['success'=>true,'attempt_id'=>$attemptId]);

    // ── Troca de aba (anti-cola) ──────────────────────────────
    case 'tab_switch':
        $attemptId = (int)($body['attempt_id'] ?? 0);
        if (!$attemptId) out(['success'=>false,'message'=>'attempt_id inválido.']);

        $attempt = safeDbRow(
            'SELECT id, tab_switches, finished_at FROM sim_attempts WHERE id=? AND user_id=?',
            [$attemptId, $userId]
        );
        if (!$attempt || $attempt['finished_at']) out(['success'=>false]);

        $switches = (int)$attempt['tab_switches'] + 1;
        safeDbExec('UPDATE sim_attempts SET tab_switches=? WHERE id=?', [$switches, $attemptId]);

        $antiCheat = getCfg('sim_anticheating', '1');
        $limit     = ($antiCheat === '1') ? 2 : 999;

        if ($switches >= $limit) {
            $until = date('Y-m-d', strtotime('+5 days'));
            try {
                dbExec(
                    'INSERT INTO sim_penalties (user_id, until) VALUES (?,?)
                     ON DUPLICATE KEY UPDATE until=VALUES(until), penalized_at=NOW()',
                    [$userId, $until]
                );
            } catch (\Throwable $e) {
                safeDbExec(
                    'INSERT IGNORE INTO sim_penalties (user_id, until) VALUES (?,?)',
                    [$userId, $until]
                );
            }
            safeDbExec(
                'UPDATE sim_attempts SET finished_at=NOW(), score=0 WHERE id=?',
                [$attemptId]
            );
            out(['success'=>true,'penalized'=>true,'until'=>$until,'switches'=>$switches]);
        }

        out(['success'=>true,'penalized'=>false,'switches'=>$switches,'limit'=>$limit]);

    // ── Finaliza e calcula resultado ──────────────────────────
    case 'finish':
        $attemptId = (int)($body['attempt_id'] ?? 0);
        $answers   = $body['answers'] ?? [];
        if (!$attemptId) out(['success'=>false,'message'=>'attempt_id inválido.']);

        $attempt = safeDbRow(
            'SELECT a.*, v.id AS vid FROM sim_attempts a
             JOIN sim_vestibulares v ON v.id=a.vestibular_id
             WHERE a.id=? AND a.user_id=? AND a.finished_at IS NULL',
            [$attemptId, $userId]
        );
        if (!$attempt) out(['success'=>false,'message'=>'Tentativa não encontrada ou já finalizada.']);

        if (empty($answers)) out(['success'=>false,'message'=>'Nenhuma resposta enviada.']);

        $qIds = array_map('intval', array_keys($answers));
        $placeholders = implode(',', array_fill(0, count($qIds), '?'));

        $correctRows = safeDbQuery(
            "SELECT id, correct_option, explanation, statement,
                    option_a, option_b, option_c, option_d, option_e
             FROM sim_questions WHERE id IN ($placeholders)",
            $qIds
        );

        $score = 0; $results = [];
        foreach ($correctRows as $q) {
            $qId     = $q['id'];
            $given   = strtolower(trim($answers[$qId] ?? ''));
            $correct = strtolower(trim($q['correct_option'] ?? ''));
            $isRight = ($given !== '' && $given === $correct);
            if ($isRight) $score++;
            $results[$qId] = [
                'correct'     => $correct,
                'given'       => $given,
                'is_correct'  => $isRight,
                'explanation' => $q['explanation'] ?? '',
                'statement'   => $q['statement'],
                'options'     => [
                    'a'=>$q['option_a'],'b'=>$q['option_b'],
                    'c'=>$q['option_c'],'d'=>$q['option_d'],
                    'e'=>$q['option_e'],
                ],
            ];
        }

        $total  = count($correctRows);
        $pct    = $total > 0 ? round($score / $total * 100) : 0;
        $xpGain = max(0, (int)getCfg('sim_xp_reward', '100'));

        safeDbExec(
            'UPDATE sim_attempts SET finished_at=NOW(), score=?, total_questions=?, answers=? WHERE id=?',
            [$score, $total, json_encode($answers), $attemptId]
        );

        if ($xpGain > 0) {
            safeDbExec('UPDATE users SET xp=xp+? WHERE id=?', [$xpGain, $userId]);
        }

        $showAnswers = getCfg('sim_show_answers', '1') === '1';

        out([
            'success'      => true,
            'score'        => $score,
            'total'        => $total,
            'pct'          => $pct,
            'xp_earned'    => $xpGain,
            'pass_score'   => (int)getCfg('sim_pass_score', '60'),
            'show_answers' => $showAnswers,
            'results'      => $showAnswers ? $results : [],
        ]);

    default:
        http_response_code(400);
        out(['success'=>false,'message'=>'Ação desconhecida: "'.$action.'"']);
}