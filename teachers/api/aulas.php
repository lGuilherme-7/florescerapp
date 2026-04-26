<?php
// ============================================================
// /florescer/teachers/api/aulas.php
// Ações: list | get_link | slots_list | slots_save
// ============================================================

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startTeacherSession();
ob_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Método não permitido.', 405);

$teacherId = requireTeacherApi();
$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$action    = trim($body['action'] ?? '');

if ($action === 'list') {
    $status = trim($body['status'] ?? '');
    $where  = 'WHERE o.teacher_id = ? AND o.type = "aula"';
    $params = [$teacherId];
    if ($status) { $where .= ' AND o.status = ?'; $params[] = $status; }

    $rows = dbQuery(
        "SELECT o.id, o.scheduled_at, o.meet_link, o.status, o.gross_amount, o.net_amount,
                u.name AS student_name, u.avatar_emoji
         FROM teacher_orders o
         JOIN users u ON u.id = o.student_id
         $where
         ORDER BY o.scheduled_at ASC",
        $params
    );

    // Libera link se faltam <= 5min
    foreach ($rows as &$row) {
        $row['link_released'] = $row['scheduled_at']
            ? isLinkReleased($row['scheduled_at'])
            : false;
        if (!$row['link_released']) $row['meet_link'] = null;
    }
    ok($rows);
}

if ($action === 'get_link') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) fail('ID inválido.');

    $o = dbRow(
        'SELECT id, scheduled_at, meet_link, status FROM teacher_orders
         WHERE id = ? AND teacher_id = ? AND type = "aula" AND status = "pago"',
        [$id, $teacherId]
    );
    if (!$o) fail('Aula não encontrada.', 404);
    if (!isLinkReleased($o['scheduled_at'])) {
        $mins = ceil((strtotime($o['scheduled_at']) - time()) / 60);
        fail("Link disponível em {$mins} minuto(s).");
    }
    ok(['link' => $o['meet_link']]);
}

if ($action === 'slots_list') {
    $slots = dbQuery(
        'SELECT id, weekday, time_start, time_end, price, is_active
         FROM teacher_slots WHERE teacher_id = ? ORDER BY weekday, time_start',
        [$teacherId]
    );
    ok($slots);
}

if ($action === 'slots_save') {
    $slots = $body['slots'] ?? [];
    if (!is_array($slots)) fail('Formato inválido.');

    // Remove slots antigos e recria
    dbExec('DELETE FROM teacher_slots WHERE teacher_id = ?', [$teacherId]);
    foreach ($slots as $s) {
        $wd    = min(6, max(0, (int)($s['weekday']   ?? 0)));
        $start = substr(trim($s['time_start'] ?? ''), 0, 5);
        $end   = substr(trim($s['time_end']   ?? ''), 0, 5);
        $price = round((float)($s['price'] ?? 0), 2);
        if (!$start || !$end || $price <= 0) continue;
        dbExec(
            'INSERT INTO teacher_slots (teacher_id, weekday, time_start, time_end, price) VALUES (?,?,?,?,?)',
            [$teacherId, $wd, $start, $end, $price]
        );
    }
    ok(null, 'Horários salvos!');
}

fail('Ação desconhecida.');