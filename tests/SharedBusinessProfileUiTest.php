<?php

declare(strict_types=1);

require_once __DIR__ . '/../private/classes/SharedBusinessProfileUi.php';

function assertProfileUi(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertProfileUiThrows(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return;
    }

    throw new RuntimeException($message);
}

function assertPostFormsHaveCsrf(string $source, string $routeName): void
{
    preg_match_all('/<form\b[^>]*method="post"[^>]*>(.*?)<\/form>/is', $source, $matches);
    assertProfileUi(count($matches[1]) > 0, $routeName . ' must contain at least one POST form.');
    foreach ($matches[1] as $index => $formBody) {
        assertProfileUi(
            str_contains($formBody, 'Csrf::input'),
            $routeName . ' POST form ' . ($index + 1) . ' must render a CSRF token.'
        );
    }
}

assertProfileUi(
    SharedBusinessProfileUi::action(['profile_action' => 'save_faqs']) === 'save_faqs',
    'Customer section actions must be allowlisted.'
);
assertProfileUiThrows(
    static fn (): string => SharedBusinessProfileUi::action(['profile_action' => 'transition_lifecycle']),
    'Customers must not receive the internal lifecycle action.'
);
assertProfileUi(
    SharedBusinessProfileUi::action(['profile_action' => 'transition_lifecycle'], true) === 'transition_lifecycle',
    'Internal administrators must receive the approved lifecycle action.'
);
assertProfileUi(
    SharedBusinessProfileUi::allowedTransitions('ready') === ['in_review', 'incomplete', 'active'],
    'The UI must source permitted lifecycle transitions from SharedBusinessProfile.'
);
assertProfileUiThrows(
    static fn (): string => SharedBusinessProfileUi::action(['profile_action' => 'delete_everything']),
    'Unknown actions must be rejected.'
);

assertProfileUiThrows(
    static fn (): array => SharedBusinessProfileUi::payload('save_faqs', []),
    'An absent collection must never be interpreted as replacement with an empty collection.'
);
assertProfileUiThrows(
    static fn (): array => SharedBusinessProfileUi::payload('save_faqs', ['rows' => []]),
    'A malformed empty collection must leave existing rows unchanged.'
);
assertProfileUiThrows(
    static fn (): array => SharedBusinessProfileUi::payload('save_faqs', [
        'rows' => [['question' => '', 'answer' => '', '_include' => '0']],
    ]),
    'A blank, unselected new row must not trigger destructive replacement.'
);

$explicitRemoval = SharedBusinessProfileUi::payload('save_faqs', [
    'rows' => [['id' => '17', '_remove' => '1']],
]);
assertProfileUi($explicitRemoval === [], 'Removing the final existing row must require an explicit row removal.');

$retainedExistingRow = SharedBusinessProfileUi::payload('save_faqs', [
    'rows' => [['id' => '17', 'question' => 'Existing question', 'answer' => 'Existing answer']],
]);
assertProfileUi(
    ($retainedExistingRow[0]['id'] ?? null) === '17',
    'An existing row must remain in the replacement payload unless it is explicitly removed.'
);

$faqPayload = SharedBusinessProfileUi::payload('save_faqs', [
    'rows' => [[
        '_include' => '1',
        'question' => 'Do you provide emergency service?',
        'answer' => 'Call us so we can confirm availability.',
        'channel_scope' => 'all',
        'is_active' => '1',
        'sort_order' => '0',
    ]],
]);
assertProfileUi(count($faqPayload) === 1, 'An explicitly included new FAQ must be dispatched.');
assertProfileUi(!isset($faqPayload[0]['_include']), 'UI-only collection controls must not reach the service.');
assertProfileUi($faqPayload[0]['is_active'] === '1', 'Collection booleans must be normalized for the service.');

$rulePayload = SharedBusinessProfileUi::payload('save_transfer_rules', [
    'rows' => [[
        '_include' => '1',
        'name' => 'Owner',
        'transfer_number' => '5551234567',
        'service_reference' => 'sub:42',
        'applies_during_business_hours' => '1',
        'applies_after_hours' => '1',
        'is_active' => '1',
    ]],
]);
assertProfileUi($rulePayload[0]['sub_service_id'] === '42', 'Selected service references must map to the existing sub-service ID.');
assertProfileUi($rulePayload[0]['business_custom_service_id'] === '', 'A sub-service reference must not also submit a custom service ID.');

$dispatch = null;
$result = SharedBusinessProfileUi::dispatch(
    'save_faqs',
    8,
    12,
    $faqPayload,
    static function (string $method, array $arguments) use (&$dispatch): array {
        $dispatch = ['method' => $method, 'arguments' => $arguments];
        return ['ok' => true];
    }
);
assertProfileUi($result === ['ok' => true], 'Request dispatch must return the service result.');
assertProfileUi($dispatch['method'] === 'saveFaqs', 'FAQ requests must dispatch to SharedBusinessProfile::saveFaqs.');
assertProfileUi($dispatch['arguments'] === [8, 12, $faqPayload], 'Dispatch must preserve business, actor, and validated payload boundaries.');

$expectedDispatches = [
    'save_shared_facts' => 'updateProfile',
    'save_hours' => 'replaceHours',
    'save_exceptions' => 'replaceHourExceptions',
    'save_faqs' => 'saveFaqs',
    'save_pricing_guidance' => 'savePricingGuidance',
    'save_appointment_settings' => 'updateProfile',
    'save_appointment_rules' => 'saveAppointmentRules',
    'save_transfer_rules' => 'saveTransferRules',
    'save_escalation_rules' => 'saveEscalationRules',
    'save_notification_preferences' => 'saveNotificationPreferences',
    'submit_for_review' => 'transitionLifecycleStatus',
    'transition_lifecycle' => 'transitionLifecycleStatus',
];
foreach ($expectedDispatches as $action => $expectedMethod) {
    $observedMethod = '';
    $payload = str_contains($action, 'lifecycle') || $action === 'submit_for_review'
        ? ['target_status' => 'in_review']
        : ['example' => 'value'];
    SharedBusinessProfileUi::dispatch(
        $action,
        8,
        12,
        $payload,
        static function (string $method) use (&$observedMethod): array {
            $observedMethod = $method;
            return [];
        }
    );
    assertProfileUi($observedMethod === $expectedMethod, $action . ' must dispatch through ' . $expectedMethod . '.');
}

$customerLifecycle = SharedBusinessProfileUi::payload('submit_for_review', []);
assertProfileUi($customerLifecycle === ['target_status' => 'in_review'], 'Customer lifecycle submission must be fixed to in_review.');

$mapped = SharedBusinessProfileUi::fieldErrorsForSection('faqs', [
    'faqs.0.question' => 'Question required.',
    'transfer_rules.0.name' => 'Name required.',
]);
assertProfileUi($mapped === ['faqs.0.question' => 'Question required.'], 'Field errors must remain scoped to the submitted section.');

assertProfileUi(
    SharedBusinessProfileUi::escape('<script>alert("x")</script>') === '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;',
    'Customer-provided output must be escaped.'
);

$dashboard = file_get_contents(__DIR__ . '/../public/app/247sp/dashboard.php');
assertProfileUi(is_string($dashboard), 'The dashboard source must be readable.');
assertProfileUi(str_contains($dashboard, 'SharedBusinessProfile::calculateReadiness'), 'Dashboard completion must use live SharedBusinessProfile readiness.');
assertProfileUi(!str_contains($dashboard, 'function sp247_business_profile_complete'), 'The legacy competing completion calculation must be removed.');
assertProfileUi(str_contains($dashboard, "'href' => 'business-profile.php?business_id='"), 'Dashboard profile actions must open the Shared Business Profile route.');
assertPostFormsHaveCsrf($dashboard, '247SP dashboard');

$route = file_get_contents(__DIR__ . '/../public/app/247sp/business-profile.php');
assertProfileUi(is_string($route), 'The customer route source must be readable.');
assertProfileUi(
    !preg_match('/Database::connection\(\)|->(?:prepare|query|exec)\s*\(/', $route),
    'The customer route must not contain direct profile SQL.'
);
assertProfileUi(str_contains($route, 'SharedBusinessProfileUi::dispatch'), 'Customer mutations must dispatch through the approved service boundary.');
assertProfileUi(str_contains($route, 'Csrf::requireValid'), 'Customer mutations must require the reusable CSRF helper.');
assertProfileUi(str_contains($route, 'Session::requireAuth'), 'The customer route must require an authenticated session.');
assertProfileUi(str_contains($route, 'TwentyFourSevenSalesPartner::businessForUser'), 'The customer route must resolve a business through user membership.');
assertProfileUi(str_contains($route, 'TwentyFourSevenSalesPartner::businessHasAccess'), 'The customer route must enforce 247SP module access.');
assertProfileUi(str_contains($route, 'SharedBusinessProfile::getProfileForBusiness'), 'The service must re-enforce business authorization when loading the profile.');
assertPostFormsHaveCsrf($route, 'Shared Business Profile customer route');

$adminRoute = file_get_contents(__DIR__ . '/../public/app/admin/business.php');
assertProfileUi(is_string($adminRoute), 'The admin business route source must be readable.');
assertProfileUi(str_contains($adminRoute, "if (!\$context['is_admin'])"), 'The admin route must reject non-admin sessions.');
assertProfileUi(str_contains($adminRoute, 'SharedBusinessProfileUi::action($_POST, true)'), 'Admin lifecycle mutations must use the admin-only action allowlist.');
assertProfileUi(!str_contains(strtolower($adminRoute), 'impersonat'), 'The admin profile view must not add an impersonation path.');
assertPostFormsHaveCsrf($adminRoute, 'Admin business route');

echo "Shared Business Profile UI test passed.\n";
