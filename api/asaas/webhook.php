<?php
require_once dirname(__DIR__, 2) . '/includes/asaas.php';

try {
    $expected = (string)env_value('ASAAS_WEBHOOK_TOKEN', '');
    $received = $_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? '';
    if ($expected === '' || !hash_equals($expected, $received)) {
        http_response_code(401);
        exit('unauthorized');
    }

    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        throw new Exception('Payload invalido.');
    }
    $event_id = (string)($payload['id'] ?? hash('sha256', $raw));
    $event_type = (string)($payload['event'] ?? 'unknown');
    if (db_fetch('SELECT id FROM webhook_events WHERE event_id = ?', [$event_id])) {
        http_response_code(200);
        exit('ok');
    }
    db_execute(
        'INSERT INTO webhook_events (provider, event_id, event_type, payload, processed_at) VALUES (?, ?, ?, ?, ?)',
        ['asaas', $event_id, $event_type, $raw, date('Y-m-d H:i:s')]
    );

    $payment = $payload['payment'] ?? [];
    $payment_id = $payment['id'] ?? null;
    if ($payment_id && in_array($event_type, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true)) {
        activate_client_subscription($payment_id, $payment);
    } elseif ($payment_id) {
        db_execute(
            'UPDATE payments SET status = ?, raw_payload = ?, updated_at = ? WHERE asaas_payment_id = ?',
            [strtolower($payment['status'] ?? $event_type), json_encode($payment, JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s'), $payment_id]
        );
    }
    http_response_code(200);
    echo 'ok';
} catch (Throwable $e) {
    debug_log('asaas.webhook_failed', ['message' => $e->getMessage()], 'ERROR');
    http_response_code(500);
    echo 'error';
}
