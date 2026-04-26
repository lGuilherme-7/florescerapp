<?php
// ============================================================
// api/assessments.php — florescer v2.4
// CRUD de avaliações personalizadas por objetivo
// ============================================================
ini_set('display_errors', 0);
ob_start();

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
ob_clean();

startSession();
if (!isLoggedIn()) {
    echo json_encode(['success'=>false,'message'=>'Não autenticado.']);
    exit;
}

$userId = (int)currentUser()['id'];
$raw    = file_get_contents('php://input');
$body   = $raw ? (json_decode($raw, true) ?? []) : $_POST;
$action = trim($body['action'] ?? '');

function jsonOk(array $data=[]): void {
    ob_clean();
    echo json_encode(array_merge(['success'=>true],$data)); exit;
}
function jsonErr(string $msg, int $code=400): void {
    ob_clean();
    http_response_code($code);
    echo json_encode(['success'=>false,'message'=>$msg]); exit;
}

// ── Cria a tabela se não existir (segurança extra) ────────────
dbQuery("CREATE TABLE IF NOT EXISTS `objective_assessments` (
    `id`           int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `objective_id` int(10) UNSIGNED NOT NULL,
    `name`         varchar(12) NOT NULL,
    `slug`         varchar(12) NOT NULL,
    `sort_order`   tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
    `created_at`   datetime NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_obj_slug` (`objective_id`, `slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", []);

// ════════════════════════════════════════════════════════════
// ACTION: list
// ════════════════════════════════════════════════════════════
if ($action === 'list') {
    $objId = (int)($body['objective_id'] ?? 0);
    if (!$objId) jsonErr('objective_id obrigatório.');

    // Verifica posse do objetivo
    $obj = dbRow('SELECT id FROM objectives WHERE id=? AND user_id=?', [$objId, $userId]);
    if (!$obj) jsonErr('Objetivo não encontrado.', 404);

    $rows = dbQuery(
        'SELECT id, name, slug FROM objective_assessments WHERE objective_id=? ORDER BY sort_order, id',
        [$objId]
    );
    jsonOk(['data' => $rows]);
}

// ════════════════════════════════════════════════════════════
// ACTION: create
// ════════════════════════════════════════════════════════════
if ($action === 'create') {
    $objId = (int)($body['objective_id'] ?? 0);
    $name  = trim($body['name'] ?? '');

    if (!$objId) jsonErr('objective_id obrigatório.');
    if (!$name)  jsonErr('Nome da avaliação obrigatório.');
    if (mb_strlen($name, 'UTF-8') > 12) jsonErr('Nome deve ter no máximo 12 caracteres.');

    // Verifica posse
    $obj = dbRow('SELECT id FROM objectives WHERE id=? AND user_id=?', [$objId, $userId]);
    if (!$obj) jsonErr('Objetivo não encontrado.', 404);

    // Limite de 5 avaliações por objetivo
    $count = dbRow('SELECT COUNT(*) AS cnt FROM objective_assessments WHERE objective_id=?', [$objId]);
    if ((int)$count['cnt'] >= 5) jsonErr('Limite de 5 avaliações atingido.');

    // Gera slug único: lowercase, sem espaços, máx 12 chars
    $baseSlug = preg_replace('/[^a-z0-9]/i', '', strtolower($name));
    $baseSlug = mb_substr($baseSlug ?: 'aval', 0, 10, 'UTF-8');
    $slug = $baseSlug;
    $i = 2;
    while (dbRow('SELECT id FROM objective_assessments WHERE objective_id=? AND slug=?', [$objId, $slug])) {
        $slug = $baseSlug . $i++;
    }

    $order = (int)(dbRow('SELECT MAX(sort_order) AS mx FROM objective_assessments WHERE objective_id=?', [$objId])['mx'] ?? 0) + 1;

    dbQuery(
        'INSERT INTO objective_assessments (objective_id, name, slug, sort_order) VALUES (?,?,?,?)',
        [$objId, $name, $slug, $order]
    );
    $newId = dbLastId();

    jsonOk(['data' => ['id' => $newId, 'name' => $name, 'slug' => $slug]]);
}

// ════════════════════════════════════════════════════════════
// ACTION: delete
// ════════════════════════════════════════════════════════════
if ($action === 'delete') {
    $id    = (int)($body['id']           ?? 0);
    $objId = (int)($body['objective_id'] ?? 0);

    if (!$id)    jsonErr('ID obrigatório.');
    if (!$objId) jsonErr('objective_id obrigatório.');

    // Verifica posse via join
    $row = dbRow(
        'SELECT oa.id FROM objective_assessments oa
         JOIN objectives o ON o.id = oa.objective_id
         WHERE oa.id=? AND oa.objective_id=? AND o.user_id=?',
        [$id, $objId, $userId]
    );
    if (!$row) jsonErr('Avaliação não encontrada.', 404);

    dbQuery('DELETE FROM objective_assessments WHERE id=?', [$id]);
    jsonOk(['message' => 'Avaliação removida.']);
}

jsonErr('Ação inválida.', 400);