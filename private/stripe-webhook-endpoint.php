<?php

require_once __DIR__ . '/classes/StripeBilling.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'method_not_allowed']);
    exit;
}

$payload = file_get_contents('php://input');
$signature = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

try {
    $result = StripeBilling::handleWebhook((string) $payload, $signature);
    http_response_code(200);
    echo json_encode($result);
} catch (Throwable $exception) {
    error_log('[StripeWebhook] request failed: ' . get_class($exception));
    http_response_code(400);
    echo json_encode(['status' => 'error']);
}
