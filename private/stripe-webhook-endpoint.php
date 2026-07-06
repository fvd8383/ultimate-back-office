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
    $isConfigurationError = stripos($exception->getMessage(), 'configured') !== false;
    http_response_code($isConfigurationError ? 500 : 400);

    $response = ['status' => 'error'];
    try {
        if ((bool) Database::config('APP_DEBUG', false)) {
            $response['message'] = $exception->getMessage();
        }
    } catch (Throwable $configException) {
        $response['message'] = 'Configuration could not be loaded.';
    }

    echo json_encode($response);
}
