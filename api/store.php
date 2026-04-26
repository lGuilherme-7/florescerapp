<?php
// ============================================================
// /api/store.php — Semente v2.0
// API da loja de cursos (apenas leitura pública para usuários)
// Gerenciamento é feito pelo admin diretamente no banco.
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

// Apenas GET para listagem
$action = $_GET['action'] ?? 'list';

function jsonOut(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Verifica se a tabela existe
$tableExists = dbRow("SHOW TABLES LIKE 'store_items'");
if (!$tableExists) {
    jsonOut(['success'=>true,'data'=>[],'grades'=>[]]);
}

switch ($action) {

    // ── Listar itens ───────────────────────────────────────────
    case 'list':
        $grade = trim($_GET['grade'] ?? '');
        $items = $grade
            ? dbQuery('SELECT * FROM store_items WHERE is_active=1 AND grade_level=? ORDER BY sort_order ASC, id ASC', [$grade])
            : dbQuery('SELECT * FROM store_items WHERE is_active=1 ORDER BY sort_order ASC, id ASC');

        $grades = dbQuery(
            "SELECT DISTINCT grade_level FROM store_items
             WHERE is_active=1 AND grade_level IS NOT NULL AND grade_level != ''
             ORDER BY grade_level ASC"
        );

        jsonOut(['success'=>true,'data'=>$items,'grades'=>array_column($grades,'grade_level')]);
        break;

    // ── Buscar item único ──────────────────────────────────────
    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonOut(['success'=>false,'message'=>'ID inválido.']);

        $item = dbRow('SELECT * FROM store_items WHERE id=? AND is_active=1', [$id]);
        if (!$item) jsonOut(['success'=>false,'message'=>'Curso não encontrado.']);

        jsonOut(['success'=>true,'data'=>$item]);
        break;

    default:
        http_response_code(400);
        jsonOut(['success'=>false,'message'=>'Ação desconhecida.']);
}