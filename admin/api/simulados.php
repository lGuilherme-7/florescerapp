<?php
// ============================================================
// /admin/api/simulados.php — florescer Admin v3.0
// CRUD de vestibulares, questões e redações
// ============================================================
require_once __DIR__ . '/../includes/auth_admin.php';
require_once dirname(dirname(__DIR__)) . '/config/db.php';

requireAdmin();

if (ob_get_level() > 0) ob_end_clean();
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

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

function jsonOut(array $d): never {
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

function vs(string $s, int $max = 500): string {
    return mb_substr(trim($s), 0, $max, 'UTF-8');
}

function auditSimulados(string $event, array $ctx = []): void {
    try {
        _adminAuditLog($event, array_merge($ctx, ['admin_id' => $_SESSION['admin_id'] ?? null]));
    } catch (\Throwable $e) {}
}

$VALID_OPTIONS = ['a', 'b', 'c', 'd', 'e'];
$VALID_CATS    = ['vestibular', 'materia', 'escolar', 'redacao'];
$VALID_DIFFS   = ['facil', 'medio', 'dificil'];
$VALID_TIPOS   = ['dissertativo', 'narrativo', 'expositivo'];

switch ($action) {

    // ══════════════════════════════════════════════════════
    // VESTIBULARES
    // ══════════════════════════════════════════════════════

    case 'create_vest': {
        $name = vs($body['name'] ?? '', 150);
        if ($name === '') jsonOut(['success' => false, 'message' => 'Nome obrigatório.']);

        $cat   = in_array($body['category'] ?? '', $VALID_CATS) ? $body['category'] : 'vestibular';
        $grade = vs($body['grade_level'] ?? '', 80) ?: null;
        $badge = in_array($body['badge'] ?? '', ['', 'novo', 'popular']) ? ($body['badge'] ?? '') : '';

        dbExec(
            'INSERT INTO sim_vestibulares (name, description, category, grade_level, badge, time_min, time_max, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $name,
                vs($body['description'] ?? '', 500) ?: null,
                $cat, $grade, $badge,
                max(0, (int)($body['time_min'] ?? 0)),
                max(0, (int)($body['time_max'] ?? 60)),
                (int)($body['sort_order'] ?? 0),
            ]
        );
        $newId = (int)(dbRow('SELECT LAST_INSERT_ID() AS id')['id'] ?? 0);
        auditSimulados('VEST_CREATED', ['id' => $newId, 'name' => $name]);
        jsonOut(['success' => true, 'id' => $newId]);
    }

    case 'update_vest': {
        $id   = (int)($body['id'] ?? 0);
        $name = vs($body['name'] ?? '', 150);
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'ID inválido.']);
        if ($name === '') jsonOut(['success' => false, 'message' => 'Nome obrigatório.']);
        if (!dbRow('SELECT id FROM sim_vestibulares WHERE id = ?', [$id]))
            jsonOut(['success' => false, 'message' => 'Vestibular não encontrado.']);

        $cat   = in_array($body['category'] ?? '', $VALID_CATS) ? $body['category'] : 'vestibular';
        $grade = vs($body['grade_level'] ?? '', 80) ?: null;
        $badge = in_array($body['badge'] ?? '', ['', 'novo', 'popular']) ? ($body['badge'] ?? '') : '';

        dbExec(
            'UPDATE sim_vestibulares SET name=?, description=?, category=?, grade_level=?, badge=?, time_min=?, time_max=?, sort_order=? WHERE id=?',
            [
                $name,
                vs($body['description'] ?? '', 500) ?: null,
                $cat, $grade, $badge,
                max(0, (int)($body['time_min'] ?? 0)),
                max(0, (int)($body['time_max'] ?? 60)),
                (int)($body['sort_order'] ?? 0),
                $id,
            ]
        );
        auditSimulados('VEST_UPDATED', ['id' => $id]);
        jsonOut(['success' => true]);
    }

    case 'toggle_vest': {
        $id     = (int)($body['id'] ?? 0);
        $active = $body['active'] ? 1 : 0;
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'ID inválido.']);
        dbExec('UPDATE sim_vestibulares SET is_active = ? WHERE id = ?', [$active, $id]);
        jsonOut(['success' => true]);
    }

    case 'delete_vest': {
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'ID inválido.']);
        $row = dbRow('SELECT name FROM sim_vestibulares WHERE id = ?', [$id]);
        if (!$row) jsonOut(['success' => false, 'message' => 'Vestibular não encontrado.']);
        // CASCADE apaga questões e redações vinculadas
        dbExec('DELETE FROM sim_vestibulares WHERE id = ?', [$id]);
        auditSimulados('VEST_DELETED', ['id' => $id, 'name' => $row['name']]);
        jsonOut(['success' => true]);
    }

    // ══════════════════════════════════════════════════════
    // QUESTÕES
    // ══════════════════════════════════════════════════════

    case 'create_q':
    case 'update_q': {
        $id     = (int)($body['id'] ?? 0);
        $vestId = (int)($body['vestibular_id'] ?? 0);
        $stmt   = vs($body['statement'] ?? '', 65000);
        $optA   = vs($body['option_a'] ?? '', 1000);
        $optB   = vs($body['option_b'] ?? '', 1000);
        $optC   = vs($body['option_c'] ?? '', 1000);
        $optD   = vs($body['option_d'] ?? '', 1000);
        $optE   = vs($body['option_e'] ?? '', 1000) ?: null;
        $correct = in_array($body['correct_option'] ?? '', $VALID_OPTIONS)
            ? $body['correct_option'] : 'a';
        $diff   = in_array($body['difficulty'] ?? '', $VALID_DIFFS)
            ? $body['difficulty'] : 'medio';
        $active = isset($body['is_active']) ? (int)$body['is_active'] : 1;
        $subj   = vs($body['subject_tag'] ?? '', 100) ?: null;
        $origin = vs($body['origin'] ?? '', 80) ?: null;
        $year   = ($body['year'] ?? '') !== '' ? (int)$body['year'] : null;
        $expl   = vs($body['explanation'] ?? '', 65000) ?: null;
        $area   = vs($body['area'] ?? '', 80) ?: null;

        if ($vestId <= 0) jsonOut(['success' => false, 'message' => 'Vestibular obrigatório.']);
        if ($stmt === '')  jsonOut(['success' => false, 'message' => 'Enunciado obrigatório.']);
        if (!$optA || !$optB || !$optC || !$optD)
            jsonOut(['success' => false, 'message' => 'Alternativas A, B, C e D são obrigatórias.']);
        if (!dbRow('SELECT id FROM sim_vestibulares WHERE id = ?', [$vestId]))
            jsonOut(['success' => false, 'message' => 'Vestibular não encontrado.']);

        if ($action === 'create_q') {
            dbExec(
                'INSERT INTO sim_questions
                 (vestibular_id, area, subject_tag, year, statement,
                  option_a, option_b, option_c, option_d, option_e,
                  correct_option, explanation, difficulty, origin, is_active)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$vestId, $area, $subj, $year, $stmt,
                 $optA, $optB, $optC, $optD, $optE,
                 $correct, $expl, $diff, $origin, $active]
            );
            auditSimulados('Q_CREATED', ['vestibular_id' => $vestId]);
        } else {
            if ($id <= 0) jsonOut(['success' => false, 'message' => 'ID inválido.']);
            if (!dbRow('SELECT id FROM sim_questions WHERE id = ?', [$id]))
                jsonOut(['success' => false, 'message' => 'Questão não encontrada.']);
            dbExec(
                'UPDATE sim_questions SET
                 vestibular_id=?, area=?, subject_tag=?, year=?, statement=?,
                 option_a=?, option_b=?, option_c=?, option_d=?, option_e=?,
                 correct_option=?, explanation=?, difficulty=?, origin=?, is_active=?
                 WHERE id=?',
                [$vestId, $area, $subj, $year, $stmt,
                 $optA, $optB, $optC, $optD, $optE,
                 $correct, $expl, $diff, $origin, $active, $id]
            );
            auditSimulados('Q_UPDATED', ['id' => $id]);
        }
        jsonOut(['success' => true]);
    }

    case 'delete_q': {
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'ID inválido.']);
        if (!dbRow('SELECT id FROM sim_questions WHERE id = ?', [$id]))
            jsonOut(['success' => false, 'message' => 'Questão não encontrada.']);
        dbExec('DELETE FROM sim_questions WHERE id = ?', [$id]);
        auditSimulados('Q_DELETED', ['id' => $id]);
        jsonOut(['success' => true]);
    }

    // ══════════════════════════════════════════════════════
    // REDAÇÃO
    // ══════════════════════════════════════════════════════

    case 'create_redacao':
    case 'update_redacao': {
        // Verifica se a tabela existe
        if (!dbRow("SHOW TABLES LIKE 'sim_redacoes'"))
            jsonOut(['success' => false, 'message' => 'Módulo de redação não instalado.']);

        $id     = (int)($body['id'] ?? 0);
        $vestId = (int)($body['vestibular_id'] ?? 0);
        $tema   = vs($body['tema'] ?? '', 300);
        $txt1   = vs($body['texto1'] ?? '', 65000);
        $txt2   = vs($body['texto2'] ?? '', 65000);
        $txt3   = vs($body['texto3'] ?? '', 65000) ?: null;
        $prop   = vs($body['proposta'] ?? '', 65000);
        $tipo   = in_array($body['tipo'] ?? '', $VALID_TIPOS) ? $body['tipo'] : 'dissertativo';
        $active = isset($body['is_active']) ? (int)$body['is_active'] : 1;
        $order  = (int)($body['sort_order'] ?? 0);

        if ($vestId <= 0) jsonOut(['success' => false, 'message' => 'Vestibular obrigatório.']);
        if ($tema === '')  jsonOut(['success' => false, 'message' => 'O tema é obrigatório.']);
        if ($txt1 === '')  jsonOut(['success' => false, 'message' => 'O Texto 1 é obrigatório.']);
        if ($txt2 === '')  jsonOut(['success' => false, 'message' => 'O Texto 2 é obrigatório.']);
        if ($prop === '')  jsonOut(['success' => false, 'message' => 'A proposta é obrigatória.']);

        if (!dbRow('SELECT id FROM sim_vestibulares WHERE id = ?', [$vestId]))
            jsonOut(['success' => false, 'message' => 'Vestibular não encontrado.']);

        if ($action === 'create_redacao') {
            dbExec(
                'INSERT INTO sim_redacoes (vestibular_id, tema, texto1, texto2, texto3, proposta, tipo, is_active, sort_order)
                 VALUES (?,?,?,?,?,?,?,?,?)',
                [$vestId, $tema, $txt1, $txt2, $txt3, $prop, $tipo, $active, $order]
            );
            auditSimulados('RED_CREATED', ['vestibular_id' => $vestId, 'tema' => mb_substr($tema, 0, 60, 'UTF-8')]);
        } else {
            if ($id <= 0) jsonOut(['success' => false, 'message' => 'ID inválido.']);
            if (!dbRow('SELECT id FROM sim_redacoes WHERE id = ?', [$id]))
                jsonOut(['success' => false, 'message' => 'Tema não encontrado.']);
            dbExec(
                'UPDATE sim_redacoes SET vestibular_id=?, tema=?, texto1=?, texto2=?, texto3=?, proposta=?, tipo=?, is_active=?, sort_order=? WHERE id=?',
                [$vestId, $tema, $txt1, $txt2, $txt3, $prop, $tipo, $active, $order, $id]
            );
            auditSimulados('RED_UPDATED', ['id' => $id]);
        }
        jsonOut(['success' => true]);
    }

    case 'delete_redacao': {
        if (!dbRow("SHOW TABLES LIKE 'sim_redacoes'"))
            jsonOut(['success' => false, 'message' => 'Módulo de redação não instalado.']);

        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'ID inválido.']);
        $row = dbRow('SELECT tema FROM sim_redacoes WHERE id = ?', [$id]);
        if (!$row) jsonOut(['success' => false, 'message' => 'Tema não encontrado.']);
        dbExec('DELETE FROM sim_redacoes WHERE id = ?', [$id]);
        auditSimulados('RED_DELETED', ['id' => $id, 'tema' => mb_substr($row['tema'], 0, 60, 'UTF-8')]);
        jsonOut(['success' => true]);
    }

    default:
        http_response_code(400);
        jsonOut(['success' => false, 'message' => 'Ação desconhecida.']);
}