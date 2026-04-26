<?php
// ============================================================
// /florescer/teachers/api/webhook.php
// Recebe notificações do Mercado Pago e confirma pedidos
// ============================================================

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/db.php';

// Sempre responde 200 primeiro — MP exige resposta rápida
http_response_code(200);
header('Content-Type: application/json');

// ── Access Token ──────────────────────────────────────────────
$accessToken = defined('MP_ACCESS_TOKEN') && MP_ACCESS_TOKEN
    ? MP_ACCESS_TOKEN
    : 'TEST-3183107767265009-042509-06edaf260470c961ebae29648ec8154f-3359901254';

// ── Lê notificação ────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];

// MP envia por query string também
$topic = $_GET['topic'] ?? ($body['type'] ?? '');
$id    = $_GET['id']    ?? ($body['data']['id'] ?? '');

error_log('[Webhook] topic=' . $topic . ' id=' . $id);

// Só processa notificações de pagamento
if (!in_array($topic, ['payment', 'merchant_order'], true) || !$id) {
    echo json_encode(['ok' => true]);
    exit;
}

// ── Consulta pagamento na API do MP ──────────────────────────
if ($topic === 'payment') {
    $url = 'https://api.mercadopago.com/v1/payments/' . $id;
} else {
    $url = 'https://api.mercadopago.com/merchant_orders/' . $id;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
    CURLOPT_TIMEOUT        => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    error_log('[Webhook] Falha ao consultar MP: HTTP ' . $httpCode);
    echo json_encode(['ok' => false]);
    exit;
}

$payment = json_decode($response, true);

// ── Extrai dados do pagamento ─────────────────────────────────
$mpStatus    = $payment['status']             ?? '';
$mpPaymentId = (string)($payment['id']        ?? $id);
$prefId      = $payment['preference_id']      ?? ($payment['external_reference'] ?? '');
$extRef      = $payment['external_reference'] ?? $prefId;
$metadata    = $payment['metadata']           ?? [];

error_log('[Webhook] mp_status=' . $mpStatus . ' ref=' . $extRef);

// ── Busca pedido no banco ─────────────────────────────────────
// Tenta pelo preference_id (salvo como mp_payment_id no INSERT)
$order = dbRow(
    'SELECT * FROM teacher_orders WHERE mp_payment_id = ? LIMIT 1',
    [$prefId ?: $mpPaymentId]
);

// Fallback: busca pelo external_reference
if (!$order && $extRef) {
    $order = dbRow(
        'SELECT * FROM teacher_orders WHERE mp_payment_id = ? LIMIT 1',
        [$extRef]
    );
}

if (!$order) {
    error_log('[Webhook] Pedido não encontrado para ref=' . $extRef);
    echo json_encode(['ok' => true]); // 200 para o MP não retentar
    exit;
}

$orderId = (int)$order['id'];

// Evita reprocessar pedido já pago
if ($order['status'] === 'pago') {
    echo json_encode(['ok' => true]);
    exit;
}

// ── Processa conforme status ──────────────────────────────────
switch ($mpStatus) {

    case 'approved':
        // Atualiza pedido para pago
        dbExec(
            "UPDATE teacher_orders
             SET status = 'pago',
                 mp_payment_id = ?,
                 mp_status     = 'approved',
                 paid_at       = NOW()
             WHERE id = ?",
            [$mpPaymentId, $orderId]
        );

        // Credita saldo pendente ao professor
        $net        = (float)$order['net_amount'];
        $teacherId  = (int)$order['teacher_id'];

        dbExec(
            'UPDATE teachers SET balance_pending = balance_pending + ? WHERE id = ?',
            [$net, $teacherId]
        );

        // Se for aula, gera link do Meet (placeholder — integrar Google Meet API se quiser)
        if ($order['type'] === 'aula') {
            $meetLink = _gerarMeetLink($orderId);
            if ($meetLink) {
                dbExec(
                    'UPDATE teacher_orders SET meet_link = ? WHERE id = ?',
                    [$meetLink, $orderId]
                );
            }
        }

        // Libera créditos de redação
        if ($order['type'] === 'redacao' && $order['package_id']) {
            $pkg = dbRow(
                'SELECT quantity FROM teacher_packages WHERE id = ?',
                [(int)$order['package_id']]
            );
            if ($pkg) {
                dbExec(
                    'UPDATE teacher_orders SET credits_total = ? WHERE id = ?',
                    [(int)$pkg['quantity'], $orderId]
                );
            }
        }

        error_log('[Webhook] Pedido #' . $orderId . ' confirmado. Net: R$' . $net);

        // Log de auditoria
        try {
            dbExec(
                "INSERT INTO admin_audit_log (event, ip, user_agent, context)
                 VALUES ('PAYMENT_APPROVED', ?, '', ?)",
                [
                    $_SERVER['REMOTE_ADDR'] ?? '?',
                    json_encode([
                        'order_id'   => $orderId,
                        'teacher_id' => $teacherId,
                        'gross'      => $order['gross_amount'],
                        'net'        => $net,
                        'mp_id'      => $mpPaymentId,
                    ]),
                ]
            );
        } catch (\Throwable $e) {}
        break;

    case 'rejected':
    case 'cancelled':
        dbExec(
            "UPDATE teacher_orders SET status = 'cancelado', mp_status = ? WHERE id = ?",
            [$mpStatus, $orderId]
        );
        error_log('[Webhook] Pedido #' . $orderId . ' ' . $mpStatus);
        break;

    case 'refunded':
    case 'charged_back':
        dbExec(
            "UPDATE teacher_orders SET status = 'estornado', mp_status = ? WHERE id = ?",
            [$mpStatus, $orderId]
        );
        // Debita saldo do professor se já foi creditado
        dbExec(
            'UPDATE teachers SET balance_pending = GREATEST(0, balance_pending - ?) WHERE id = ?',
            [(float)$order['net_amount'], (int)$order['teacher_id']]
        );
        error_log('[Webhook] Pedido #' . $orderId . ' estornado.');
        break;

    case 'in_process':
    case 'pending':
    case 'authorized':
        dbExec(
            "UPDATE teacher_orders SET mp_status = ? WHERE id = ?",
            [$mpStatus, $orderId]
        );
        break;

    default:
        error_log('[Webhook] Status desconhecido: ' . $mpStatus);
}

echo json_encode(['ok' => true]);

// ── Gera link do Meet (simples — pode integrar Google Calendar API) ──
function _gerarMeetLink(int $orderId): ?string {
    // Por ora gera um link fixo de sala aleatória
    // Para automatizar: integrar Google Calendar API
    $code = substr(md5('meet_' . $orderId . '_' . time()), 0, 12);
    $part1 = substr($code, 0, 4);
    $part2 = substr($code, 4, 4);
    $part3 = substr($code, 8, 4);
    return "https://meet.google.com/{$part1}-{$part2}-{$part3}";
}