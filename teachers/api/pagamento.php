<?php
// ============================================================
// /florescer/teachers/api/pagamento.php
// Cria preferência de pagamento no Mercado Pago
// POST: { action, type, teacher_id, package_id|slot_id, student_id }
// ============================================================

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

ob_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Método não permitido.', 405);

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$action    = trim($body['action'] ?? '');
$type      = trim($body['type']   ?? ''); // 'redacao' ou 'aula'
$teacherId = (int)($body['teacher_id'] ?? 0);
$studentId = (int)($body['student_id'] ?? 0);
$packageId = (int)($body['package_id'] ?? 0);
$slotId    = (int)($body['slot_id']    ?? 0);
$scheduled = trim($body['scheduled_at'] ?? ''); // para aulas

if ($action !== 'criar_preferencia') fail('Ação inválida.');
if (!$teacherId || !$studentId)      fail('Dados incompletos.');
if (!in_array($type, ['redacao','aula'], true)) fail('Tipo inválido.');

// ── Busca professor ───────────────────────────────────────────
$teacher = dbRow(
    'SELECT id, name, email, commission_pct, commission_aula, mp_access_token
     FROM teachers WHERE id = ? AND status = "ativo"',
    [$teacherId]
);
if (!$teacher) fail('Professor não encontrado.');

// ── Access Token: usa o do professor ou o da plataforma ───────
// Por ora usa o token da plataforma (você pode configurar split depois)
$accessToken = defined('MP_ACCESS_TOKEN') && MP_ACCESS_TOKEN
    ? MP_ACCESS_TOKEN
    : 'TEST-3183107767265009-042509-06edaf260470c961ebae29648ec8154f-3359901254';

// ── Busca aluno ───────────────────────────────────────────────
$student = dbRow(
    'SELECT id, name, email FROM users WHERE id = ?',
    [$studentId]
);
if (!$student) fail('Aluno não encontrado.');

// ── Monta item e calcula comissão ─────────────────────────────
if ($type === 'redacao') {
    if (!$packageId) fail('Pacote não informado.');
    $package = dbRow(
        'SELECT id, name, quantity, price FROM teacher_packages
         WHERE id = ? AND teacher_id = ? AND is_active = 1',
        [$packageId, $teacherId]
    );
    if (!$package) fail('Pacote não encontrado.');

    $gross       = (float)$package['price'];
    $pct         = (float)$teacher['commission_pct'];
    $itemTitle   = "Correção de redação — {$package['name']} ({$package['quantity']}x) com {$teacher['name']}";
    $itemDesc    = "Pacote de {$package['quantity']} correção(ões) de redação";
    $refId       = "red_{$teacherId}_{$studentId}_{$packageId}_".time();

} else { // aula
    if (!$slotId || !$scheduled) fail('Horário não informado.');
    $slot = dbRow(
        'SELECT id, price, weekday, time_start, time_end FROM teacher_slots
         WHERE id = ? AND teacher_id = ? AND is_active = 1',
        [$slotId, $teacherId]
    );
    if (!$slot) fail('Horário não encontrado.');

    $gross       = (float)$slot['price'];
    $pct         = (float)$teacher['commission_aula'];
    $itemTitle   = "Aula particular com {$teacher['name']}";
    $itemDesc    = "Aula em ".date('d/m/Y H:i', strtotime($scheduled));
    $refId       = "aula_{$teacherId}_{$studentId}_{$slotId}_".time();
}

$commission  = round($gross * ($pct / 100), 2);
$net         = round($gross - $commission, 2);

// ── Monta preferência MP ──────────────────────────────────────
$baseUrl  = 'https://florescerapp.com.br/florescer';
$preference = [
    'items' => [[
        'id'          => $refId,
        'title'       => $itemTitle,
        'description' => $itemDesc,
        'quantity'    => 1,
        'currency_id' => 'BRL',
        'unit_price'  => $gross,
    ]],
    'payer' => [
        'name'  => $student['name'],
        'email' => $student['email'],
    ],
    'back_urls' => [
        'success' => $baseUrl . '/teachers/api/pagamento.php?status=success&ref=' . $refId,
        'failure' => $baseUrl . '/public/views/professores.php?erro=pagamento_falhou',
        'pending' => $baseUrl . '/public/views/professores.php?info=pagamento_pendente',
    ],
    'auto_return'           => 'approved',
    'external_reference'    => $refId,
    'notification_url'      => $baseUrl . '/teachers/api/webhook.php',
    'statement_descriptor'  => 'FLORESCER',
    'expires'               => false,
    'metadata' => [
        'type'        => $type,
        'teacher_id'  => $teacherId,
        'student_id'  => $studentId,
        'package_id'  => $packageId ?: null,
        'slot_id'     => $slotId    ?: null,
        'scheduled_at'=> $scheduled ?: null,
        'gross'       => $gross,
        'commission'  => $commission,
        'net'         => $net,
    ],
];

// ── Chama API do MP ───────────────────────────────────────────
$ch = curl_init('https://api.mercadopago.com/checkout/preferences');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken,
    ],
    CURLOPT_POSTFIELDS     => json_encode($preference),
    CURLOPT_TIMEOUT        => 15,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    error_log('[Pagamento] cURL error: ' . $curlErr);
    fail('Erro ao conectar com o Mercado Pago.');
}

$data = json_decode($response, true);

if ($httpCode !== 201 || empty($data['id'])) {
    error_log('[Pagamento] MP error: ' . $response);
    fail('Erro ao criar preferência de pagamento.');
}

// ── Salva pedido pendente no banco ────────────────────────────
dbExec(
    'INSERT INTO teacher_orders
     (teacher_id, student_id, type, package_id, slot_id,
      gross_amount, commission_amt, net_amount,
      status, mp_payment_id, mp_status, scheduled_at)
     VALUES (?,?,?,?,?,?,?,?,"aguardando",?,?,?)',
    [
        $teacherId,
        $studentId,
        $type,
        $packageId ?: null,
        $slotId    ?: null,
        $gross,
        $commission,
        $net,
        $data['id'],         // preference_id do MP
        'pending',
        $scheduled ?: null,
    ]
);

$orderId = (int)dbLastId();

// ── Retorna link de checkout ──────────────────────────────────
ok([
    'order_id'     => $orderId,
    'preference_id'=> $data['id'],
    // init_point = produção / sandbox_init_point = teste
    'checkout_url' => $data['sandbox_init_point'] ?? $data['init_point'],
]);