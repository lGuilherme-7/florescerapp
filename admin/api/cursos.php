<?php
// ============================================================
// /admin/api/cursos.php — florescer Admin v3.0
// CRUD da loja de cursos/afiliados (store_items)
// ============================================================
require_once __DIR__ . '/../includes/auth_admin.php';
require_once dirname(dirname(__DIR__)) . '/config/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

requireAdmin();

// ── Apenas POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonOut(['success' => false, 'message' => 'Método não permitido.']);
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    http_response_code(400);
    jsonOut(['success' => false, 'message' => 'Payload inválido.']);
}

$action = trim($body['action'] ?? '');

// ── Helpers ───────────────────────────────────────────────────
function jsonOut(array $d): never {
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Sanitiza string: trim + trunca + null se vazio.
 */
function clean(mixed $val, int $max = 255): ?string {
    $s = trim((string)($val ?? ''));
    return $s !== '' ? mb_substr($s, 0, $max, 'UTF-8') : null;
}

/**
 * Valida URL HTTP/HTTPS.
 */
function validUrl(?string $url): bool {
    if (!$url) return false;
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $scheme = parse_url($url, PHP_URL_SCHEME);
    return in_array($scheme, ['http', 'https'], true);
}

// ── Whitelist de badges ───────────────────────────────────────
const VALID_BADGES = ['', 'novo', 'popular'];

// ── Roteamento ────────────────────────────────────────────────
switch ($action) {

    // ── CREATE ────────────────────────────────────────────────
    case 'create': {
        $title = clean($body['title']        ?? '', 200);
        $url   = clean($body['affiliate_url'] ?? '', 500);
        $badge = in_array($body['badge'] ?? '', VALID_BADGES, true) ? ($body['badge'] ?? '') : '';

        if (!$title)       jsonOut(['success' => false, 'message' => 'Título obrigatório.']);
        if (!$url)         jsonOut(['success' => false, 'message' => 'Link de afiliado obrigatório.']);
        if (!validUrl($url)) jsonOut(['success' => false, 'message' => 'URL do afiliado inválida (use https://).']);

        $imgUrl = clean($body['image_url'] ?? '', 500);
        if ($imgUrl && !validUrl($imgUrl)) {
            jsonOut(['success' => false, 'message' => 'URL da imagem inválida.']);
        }

        $sortOrder = max(0, min(9999, (int)($body['sort_order'] ?? 0)));
        $isActive  = isset($body['is_active']) ? ($body['is_active'] ? 1 : 0) : 1;

        dbExec(
            'INSERT INTO store_items
             (title, description, image_url, affiliate_url, category,
              grade_level, badge, price_display, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $title,
                clean($body['description']  ?? '', 1000),
                $imgUrl,
                $url,
                clean($body['category']     ?? '', 80),
                clean($body['grade_level']  ?? '', 80),
                $badge,
                clean($body['price_display'] ?? '', 50),
                $isActive,
                $sortOrder,
            ]
        );

        _adminAuditLog('CURSO_CREATED', [
            'title'    => $title,
            'admin_id' => $_SESSION['admin_id'] ?? null,
        ]);

        jsonOut(['success' => true, 'message' => 'Curso criado com sucesso.']);
    }

    // ── UPDATE ────────────────────────────────────────────────
    case 'update': {
        $id    = (int)($body['id'] ?? 0);
        $title = clean($body['title']        ?? '', 200);
        $url   = clean($body['affiliate_url'] ?? '', 500);
        $badge = in_array($body['badge'] ?? '', VALID_BADGES, true) ? ($body['badge'] ?? '') : '';

        if ($id <= 0)      jsonOut(['success' => false, 'message' => 'ID inválido.']);
        if (!$title)       jsonOut(['success' => false, 'message' => 'Título obrigatório.']);
        if (!$url)         jsonOut(['success' => false, 'message' => 'Link de afiliado obrigatório.']);
        if (!validUrl($url)) jsonOut(['success' => false, 'message' => 'URL do afiliado inválida.']);

        // Confirma existência antes de atualizar
        if (!dbRow('SELECT id FROM store_items WHERE id = ? LIMIT 1', [$id])) {
            jsonOut(['success' => false, 'message' => 'Curso não encontrado.']);
        }

        $imgUrl = clean($body['image_url'] ?? '', 500);
        if ($imgUrl && !validUrl($imgUrl)) {
            jsonOut(['success' => false, 'message' => 'URL da imagem inválida.']);
        }

        $sortOrder = max(0, min(9999, (int)($body['sort_order'] ?? 0)));
        $isActive  = isset($body['is_active']) ? ($body['is_active'] ? 1 : 0) : 1;

        dbExec(
            'UPDATE store_items SET
               title         = ?,
               description   = ?,
               image_url     = ?,
               affiliate_url = ?,
               category      = ?,
               grade_level   = ?,
               badge         = ?,
               price_display = ?,
               is_active     = ?,
               sort_order    = ?
             WHERE id = ?',
            [
                $title,
                clean($body['description']   ?? '', 1000),
                $imgUrl,
                $url,
                clean($body['category']      ?? '', 80),
                clean($body['grade_level']   ?? '', 80),
                $badge,
                clean($body['price_display'] ?? '', 50),
                $isActive,
                $sortOrder,
                $id,
            ]
        );

        _adminAuditLog('CURSO_UPDATED', [
            'id'       => $id,
            'title'    => $title,
            'admin_id' => $_SESSION['admin_id'] ?? null,
        ]);

        jsonOut(['success' => true, 'message' => 'Curso atualizado.']);
    }

    // ── TOGGLE ativo/inativo ──────────────────────────────────
    case 'toggle': {
        $id     = (int)($body['id'] ?? 0);
        $active = $body['active'] ? 1 : 0;

        if ($id <= 0) jsonOut(['success' => false, 'message' => 'ID inválido.']);

        $affected = dbExec(
            'UPDATE store_items SET is_active = ? WHERE id = ?',
            [$active, $id]
        );

        if ($affected === 0) jsonOut(['success' => false, 'message' => 'Curso não encontrado.']);

        _adminAuditLog('CURSO_TOGGLED', [
            'id'       => $id,
            'active'   => $active,
            'admin_id' => $_SESSION['admin_id'] ?? null,
        ]);

        jsonOut(['success' => true]);
    }

    // ── DELETE ────────────────────────────────────────────────
    case 'delete': {
        $id = (int)($body['id'] ?? 0);

        if ($id <= 0) jsonOut(['success' => false, 'message' => 'ID inválido.']);

        // Busca título para o log antes de deletar
        $row = dbRow('SELECT title FROM store_items WHERE id = ? LIMIT 1', [$id]);
        if (!$row) jsonOut(['success' => false, 'message' => 'Curso não encontrado.']);

        dbExec('DELETE FROM store_items WHERE id = ?', [$id]);

        _adminAuditLog('CURSO_DELETED', [
            'id'       => $id,
            'title'    => $row['title'],
            'admin_id' => $_SESSION['admin_id'] ?? null,
        ]);

        jsonOut(['success' => true, 'message' => 'Curso excluído.']);
    }

    // ── REORDER (drag-and-drop opcional) ─────────────────────
    case 'reorder': {
        $items = $body['items'] ?? [];
        if (!is_array($items) || empty($items)) {
            jsonOut(['success' => false, 'message' => 'Lista inválida.']);
        }

        dbBegin();
        try {
            foreach ($items as $item) {
                $itemId    = (int)($item['id']    ?? 0);
                $sortOrder = (int)($item['order'] ?? 0);
                if ($itemId <= 0) continue;
                dbExec(
                    'UPDATE store_items SET sort_order = ? WHERE id = ?',
                    [$sortOrder, $itemId]
                );
            }
            dbCommit();
        } catch (Throwable $e) {
            dbRollback();
            error_log('[Admin/Cursos] reorder: ' . $e->getMessage());
            jsonOut(['success' => false, 'message' => 'Erro ao reordenar.']);
        }

        jsonOut(['success' => true]);
    }

    default:
        http_response_code(400);
        jsonOut(['success' => false, 'message' => 'Ação desconhecida.']);
}