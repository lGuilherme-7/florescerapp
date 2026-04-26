<?php
// ============================================================
// /florescer/teachers/api/profile.php
// Ações: get | update | subjects_save | packages_save
// ============================================================

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startTeacherSession();
ob_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Método não permitido.', 405);

$teacherId = requireTeacherApi();
$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$action    = trim($body['action'] ?? '');

if ($action === 'get') {
    $t = dbRow(
        'SELECT id, name, email, bio, avatar_url, pix_key, status,
                rating_avg, rating_count, rank_position, is_premium
         FROM teachers WHERE id = ?',
        [$teacherId]
    );
    $subjects = dbQuery(
        'SELECT id, name FROM teacher_subjects WHERE teacher_id = ? ORDER BY name',
        [$teacherId]
    );
    $packages = dbQuery(
        'SELECT id, name, quantity, price, is_active FROM teacher_packages WHERE teacher_id = ? ORDER BY price',
        [$teacherId]
    );
    $t['subjects'] = $subjects;
    $t['packages'] = $packages;
    ok($t);
}

if ($action === 'update') {
    $name    = mb_substr(trim($body['name']    ?? ''), 0, 100, 'UTF-8');
    $bio     = mb_substr(trim($body['bio']     ?? ''), 0, 1000, 'UTF-8');
    $pix_key = mb_substr(trim($body['pix_key'] ?? ''), 0, 150, 'UTF-8');

    if (!$name) fail('Informe seu nome.');

    dbExec(
        'UPDATE teachers SET name = ?, bio = ?, pix_key = ? WHERE id = ?',
        [$name, $bio ?: null, $pix_key ?: null, $teacherId]
    );
    ok(null, 'Perfil atualizado!');
}

if ($action === 'subjects_save') {
    $subjects = array_filter(array_map('trim', $body['subjects'] ?? []));
    if (empty($subjects)) fail('Informe ao menos uma matéria.');

    dbExec('DELETE FROM teacher_subjects WHERE teacher_id = ?', [$teacherId]);
    foreach (array_slice($subjects, 0, 10) as $s) {
        $name = mb_substr($s, 0, 80, 'UTF-8');
        if ($name) {
            dbExec(
                'INSERT INTO teacher_subjects (teacher_id, name) VALUES (?, ?)',
                [$teacherId, $name]
            );
        }
    }
    ok(null, 'Matérias salvas!');
}

if ($action === 'packages_save') {
    $packages = $body['packages'] ?? [];
    if (!is_array($packages)) fail('Formato inválido.');

    dbExec('DELETE FROM teacher_packages WHERE teacher_id = ?', [$teacherId]);
    foreach (array_slice($packages, 0, 5) as $p) {
        $name  = mb_substr(trim($p['name'] ?? ''), 0, 100, 'UTF-8');
        $qty   = min(20, max(1, (int)($p['quantity'] ?? 1)));
        $price = round(max(0, (float)($p['price'] ?? 0)), 2);
        if ($name && $price > 0) {
            dbExec(
                'INSERT INTO teacher_packages (teacher_id, name, quantity, price) VALUES (?,?,?,?)',
                [$teacherId, $name, $qty, $price]
            );
        }
    }
    ok(null, 'Pacotes salvos!');
}

fail('Ação desconhecida.');