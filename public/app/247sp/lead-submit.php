<?php

require_once __DIR__ . '/../../../private/classes/LeadHub.php';

function sp247_lead_submit_redirect(int $businessId, string $sourceSlug, string $status, string $error = ''): void
{
    $query = [
        'business_id' => $businessId,
        'lead_status' => $status,
    ];

    if ($sourceSlug !== '') {
        $query['page'] = $sourceSlug;
    }

    if ($error !== '') {
        $query['lead_error'] = $error;
    }

    header('Location: site-preview.php?' . http_build_query($query));
    exit;
}

function sp247_lead_submit_error_code(string $message): string
{
    $message = strtolower($message);

    if (strpos($message, 'name') !== false) {
        return 'name';
    }

    if (strpos($message, 'valid email') !== false) {
        return 'email';
    }

    if (strpos($message, 'phone') !== false || strpos($message, 'email address') !== false) {
        return 'contact';
    }

    if (strpos($message, 'website') !== false || strpos($message, 'page') !== false) {
        return 'website';
    }

    return 'spam';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sp247_lead_submit_redirect(0, '', 'error', 'website');
}

$businessId = (int) ($_POST['business_id'] ?? 0);
$sourceSlug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_POST['source_slug'] ?? ''));

try {
    LeadHub::capture247spWebsiteSubmission($_POST, $_SERVER);
    sp247_lead_submit_redirect($businessId, $sourceSlug, 'success');
} catch (InvalidArgumentException $exception) {
    sp247_lead_submit_redirect($businessId, $sourceSlug, 'error', sp247_lead_submit_error_code($exception->getMessage()));
} catch (Throwable $exception) {
    sp247_lead_submit_redirect($businessId, $sourceSlug, 'error', 'spam');
}
