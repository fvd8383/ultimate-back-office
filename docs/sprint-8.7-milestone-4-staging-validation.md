# Sprint 8.7 Milestone 4 Staging Runtime Validation

This document began as the operator-run plan for the merged `SharedBusinessProfile`
service in `private/classes/SharedBusinessProfile.php`. Run its procedures only
against staging, using designated synthetic test records. Section 23 now records the
completed PASS result and its external evidence reference. This document does not
authorize production access.

The service has no HTTP route and no session-derived business context. Every public
method requires an explicit business ID and acting-user ID, then performs its own
authorization. The normal class-loading path is a direct `require_once` of
`private/classes/SharedBusinessProfile.php`; that file loads `Database`,
`BusinessFoundation`, and `AdminPortal`, and their existing dependencies.

## 1. Preconditions

Before any mutation, the operator must:

- Confirm staging—not production—is selected and record the deployed commit. The
  deployed commit must be `a0d4723` or a later commit containing PR #83.
- Confirm the deployed checkout is clean and the configured `APP_ENV` is `staging`.
- Confirm all nine migration 021 tables exist: `business_profiles`,
  `business_profile_hours`, `business_profile_hour_exceptions`,
  `business_profile_faqs`, `business_profile_pricing_guidance`,
  `business_appointment_rules`, `business_transfer_rules`,
  `business_escalation_rules`, and `business_notification_preferences`.
- Confirm PHP can load the service and its dependencies by completing section 2.
- Select an active synthetic staging user with an active `business_users` membership
  for designated Business A.
- Select a separate inactive membership and, if available, an inactive synthetic user.
- Select an active staging internal administrator whose active role is `Super Admin`
  or `Admin` with `roles.scope = 'internal'`. A business-scoped Admin role is not an
  internal administrator under the implementation.
- Select two separate synthetic staging businesses, Business A and Business B. Keep
  customer mutations on Business A unless a test explicitly says otherwise.
- Identify one active selected `sub_services` row for Business A, one
  `business_custom_services` row owned by Business A, and one custom service owned by
  Business B.
- Confirm which designated business may be temporarily suspended for the suspension
  test and obtain approval before changing its state through existing admin tooling.
- Record the original normalized profile, lifecycle, readiness, and exact child rows
  for both businesses in an access-restricted staging work file. Do not copy private
  customer data into this repository or the validation result.
- Record baseline counts for every migration 021 table and for matching
  `activity_logs` rows. Use the queries in sections 18 and 19.
- Use designated test records only. Do not use real customer profiles, credentials,
  provider identifiers, private LeadHub notes, or production-derived data.

Set the deployed repository path once for the commands below. The operator must
replace the example with the actual staging release path:

```bash
export UBO_STAGE_ROOT=/absolute/path/to/staging/ultimate-back-office
cd "$UBO_STAGE_ROOT"
test "$(git rev-parse --is-inside-work-tree)" = "true"
git status --short
git log -1 --oneline
```

Expected: the status command has no output and the commit contains PR #83.

Confirm the schema without displaying database credentials:

```sql
SELECT table_name
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN (
    'business_profiles',
    'business_profile_hours',
    'business_profile_hour_exceptions',
    'business_profile_faqs',
    'business_profile_pricing_guidance',
    'business_appointment_rules',
    'business_transfer_rules',
    'business_escalation_rules',
    'business_notification_preferences'
  )
ORDER BY table_name;
```

Expected: exactly nine rows.

## 2. PHP syntax and class-loading validation

Run from `$UBO_STAGE_ROOT`:

```bash
php -l private/classes/SharedBusinessProfile.php
git diff-tree --no-commit-id --name-only -r a0d4723^1 a0d4723 -- '*.php' \
  | xargs -r -n1 php -l
```

PR #83 changed one PHP file, `private/classes/SharedBusinessProfile.php`. The second
command derives that list from the merge rather than relying on memory.

Use the repository's normal direct include chain for the minimal load/reflection test:

```bash
php -d display_errors=1 -r '
require_once getcwd() . "/private/classes/SharedBusinessProfile.php";
$classes = [
    "SharedBusinessProfileException",
    "SharedBusinessProfile",
    "Database",
    "BusinessFoundation",
    "AdminPortal",
];
foreach ($classes as $class) {
    if (!class_exists($class, false)) {
        fwrite(STDERR, "Missing class: {$class}\n");
        exit(1);
    }
}
$expected = [
    "getProfileForBusiness" => ["int", "int"],
    "updateProfile" => ["int", "int", "array"],
    "getHours" => ["int", "int"],
    "replaceHours" => ["int", "int", "array"],
    "getHourExceptions" => ["int", "int"],
    "replaceHourExceptions" => ["int", "int", "array"],
    "getFaqs" => ["int", "int"],
    "saveFaqs" => ["int", "int", "array"],
    "getPricingGuidance" => ["int", "int"],
    "savePricingGuidance" => ["int", "int", "array"],
    "getAppointmentRules" => ["int", "int"],
    "saveAppointmentRules" => ["int", "int", "array"],
    "getTransferRules" => ["int", "int"],
    "saveTransferRules" => ["int", "int", "array"],
    "getEscalationRules" => ["int", "int"],
    "saveEscalationRules" => ["int", "int", "array"],
    "getNotificationPreferences" => ["int", "int"],
    "saveNotificationPreferences" => ["int", "int", "array"],
    "transitionLifecycleStatus" => ["int", "int", "string"],
    "calculateReadiness" => ["int", "int"],
];
foreach ($expected as $method => $parameterTypes) {
    $reflection = new ReflectionMethod("SharedBusinessProfile", $method);
    $actualParameterTypes = array_map(
        fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
        $reflection->getParameters()
    );
    if (!$reflection->isPublic() || !$reflection->isStatic()
        || $reflection->getNumberOfRequiredParameters() !== count($parameterTypes)
        || $actualParameterTypes !== $parameterTypes
        || (string) $reflection->getReturnType() !== "array") {
        fwrite(STDERR, "Signature mismatch: {$method}\n");
        exit(1);
    }
}
echo "SharedBusinessProfile class load and public signatures: PASS\n";
'
```

Pass only if both lint commands and the load/reflection command exit `0`, all
dependencies resolve, and no warning, fatal error, method mismatch, or type mismatch
appears. Record the PHP version with `php -v`.

## 3. Test harness approach

Repository inspection found no existing CLI or diagnostic harness for this service.
The safest approach is therefore a temporary staging-only PHP CLI script at
`/tmp/ubo-sbp-validation.php`, outside both `public/accounts` and `public/app`. It
loads the deployed service directly, uses the normal staging database configuration,
requires explicit IDs, allowlists every action, accepts JSON only from an explicit
file, and masks customer-facing text, email, phone, URL, and address output.

Create the temporary script on staging with mode `0700`. Do not add it to the
repository and do not put credentials in it:

```php
<?php

declare(strict_types=1);

$options = getopt('', [
    'repo-root:',
    'business-id:',
    'acting-user-id:',
    'action:',
    'input-file::',
]);

$repoRoot = realpath((string) ($options['repo-root'] ?? ''));
$businessId = filter_var($options['business-id'] ?? null, FILTER_VALIDATE_INT);
$actingUserId = filter_var($options['acting-user-id'] ?? null, FILTER_VALIDATE_INT);
$action = trim((string) ($options['action'] ?? ''));

if ($repoRoot === false || $businessId === false || $businessId < 1
    || $actingUserId === false || $actingUserId < 1 || $action === '') {
    fwrite(STDERR, "Required: --repo-root --business-id --acting-user-id --action\n");
    exit(64);
}

$serviceFile = $repoRoot . '/private/classes/SharedBusinessProfile.php';
if (!is_file($serviceFile)) {
    fwrite(STDERR, "SharedBusinessProfile.php was not found under --repo-root.\n");
    exit(66);
}
require_once $serviceFile;

$input = [];
if (isset($options['input-file'])) {
    $inputPath = realpath((string) $options['input-file']);
    if ($inputPath === false || !is_file($inputPath)) {
        fwrite(STDERR, "The input file was not found.\n");
        exit(66);
    }
    $decoded = json_decode((string) file_get_contents($inputPath), true);
    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
        fwrite(STDERR, "The input file must contain a JSON object or array.\n");
        exit(65);
    }
    $input = $decoded;
}

$actions = [
    'get-profile' => fn () => SharedBusinessProfile::getProfileForBusiness($businessId, $actingUserId),
    'update-profile' => fn () => SharedBusinessProfile::updateProfile($businessId, $actingUserId, $input),
    'get-hours' => fn () => SharedBusinessProfile::getHours($businessId, $actingUserId),
    'replace-hours' => fn () => SharedBusinessProfile::replaceHours($businessId, $actingUserId, $input),
    'get-hour-exceptions' => fn () => SharedBusinessProfile::getHourExceptions($businessId, $actingUserId),
    'replace-hour-exceptions' => fn () => SharedBusinessProfile::replaceHourExceptions($businessId, $actingUserId, $input),
    'get-faqs' => fn () => SharedBusinessProfile::getFaqs($businessId, $actingUserId),
    'save-faqs' => fn () => SharedBusinessProfile::saveFaqs($businessId, $actingUserId, $input),
    'get-pricing-guidance' => fn () => SharedBusinessProfile::getPricingGuidance($businessId, $actingUserId),
    'save-pricing-guidance' => fn () => SharedBusinessProfile::savePricingGuidance($businessId, $actingUserId, $input),
    'get-appointment-rules' => fn () => SharedBusinessProfile::getAppointmentRules($businessId, $actingUserId),
    'save-appointment-rules' => fn () => SharedBusinessProfile::saveAppointmentRules($businessId, $actingUserId, $input),
    'get-transfer-rules' => fn () => SharedBusinessProfile::getTransferRules($businessId, $actingUserId),
    'save-transfer-rules' => fn () => SharedBusinessProfile::saveTransferRules($businessId, $actingUserId, $input),
    'get-escalation-rules' => fn () => SharedBusinessProfile::getEscalationRules($businessId, $actingUserId),
    'save-escalation-rules' => fn () => SharedBusinessProfile::saveEscalationRules($businessId, $actingUserId, $input),
    'get-notification-preferences' => fn () => SharedBusinessProfile::getNotificationPreferences($businessId, $actingUserId),
    'save-notification-preferences' => fn () => SharedBusinessProfile::saveNotificationPreferences($businessId, $actingUserId, $input),
    'transition-lifecycle' => function () use ($businessId, $actingUserId, $input) {
        return SharedBusinessProfile::transitionLifecycleStatus(
            $businessId,
            $actingUserId,
            (string) ($input['target_status'] ?? '')
        );
    },
    'calculate-readiness' => fn () => SharedBusinessProfile::calculateReadiness($businessId, $actingUserId),
];

if (!isset($actions[$action])) {
    fwrite(STDERR, "Unsupported action.\n");
    exit(64);
}

function sanitized($value, string $key = '', bool $sensitiveContext = false)
{
    if (is_array($value)) {
        $result = [];
        $childSensitiveContext = $sensitiveContext
            || preg_match('/address$/i', $key) === 1;
        foreach ($value as $childKey => $childValue) {
            $result[$childKey] = sanitized(
                $childValue,
                (string) $childKey,
                $childSensitiveContext
            );
        }
        return $result;
    }
    if (is_string($value) && ($sensitiveContext || preg_match(
        '/(?:email|phone|number|url|address|name|title|question|answer|description|greeting|proposition|personality|claims|tone|text|label)$/i',
        $key
    ) === 1)) {
        return $value === '' ? '' : '[present ' . strlen($value) . ' chars]';
    }
    return $value;
}

try {
    $result = $actions[$action]();
    echo json_encode([
        'status' => 'ok',
        'action' => $action,
        'result' => sanitized($result),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
} catch (SharedBusinessProfileException $exception) {
    echo json_encode([
        'status' => 'error',
        'action' => $action,
        'exception_class' => get_class($exception),
        'error_type' => $exception->errorType(),
        'message' => $exception->getMessage(),
        'field_errors' => $exception->fieldErrors(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(2);
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode([
        'status' => 'error',
        'action' => $action,
        'exception_class' => get_class($exception),
        'message' => 'Unexpected failure; inspect the staging PHP error log.',
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(3);
}
```

Then run:

```bash
chmod 0700 /tmp/ubo-sbp-validation.php
php -l /tmp/ubo-sbp-validation.php
php /tmp/ubo-sbp-validation.php \
  --repo-root="$UBO_STAGE_ROOT" \
  --business-id=BUSINESS_A_ID \
  --acting-user-id=ACTIVE_USER_A_ID \
  --action=get-profile
```

For mutation input, use access-restricted files outside the repository:

```bash
umask 077
mkdir -p /tmp/ubo-sbp-validation-fixtures
php /tmp/ubo-sbp-validation.php \
  --repo-root="$UBO_STAGE_ROOT" \
  --business-id=BUSINESS_A_ID \
  --acting-user-id=ACTIVE_USER_A_ID \
  --action=update-profile \
  --input-file=/tmp/ubo-sbp-validation-fixtures/profile.json
```

The harness must never be placed under `public/`, invoked through HTTP, committed,
or configured with embedded credentials. After validation and restoration:

```bash
rm -f /tmp/ubo-sbp-validation.php
rm -rf /tmp/ubo-sbp-validation-fixtures
```

### Exact public contract

All methods are `public static`, return `array`, require an active acting user, and
authorize the supplied business before returning data or mutating it.

| Method signature | Input after IDs | Successful return |
| --- | --- | --- |
| `getProfileForBusiness(int $businessId, int $actingUserId): array` | None | Complete normalized profile |
| `updateProfile(int $businessId, int $actingUserId, array $input): array` | Partial allowlisted profile map | Complete normalized profile |
| `getHours(int $businessId, int $actingUserId): array` | None | Hours rows |
| `replaceHours(int $businessId, int $actingUserId, array $hours): array` | Complete replacement rows | Complete normalized profile |
| `getHourExceptions(int $businessId, int $actingUserId): array` | None | Exception rows |
| `replaceHourExceptions(int $businessId, int $actingUserId, array $exceptions): array` | Complete replacement rows | Complete normalized profile |
| `getFaqs(int $businessId, int $actingUserId): array` | None | FAQ rows |
| `saveFaqs(int $businessId, int $actingUserId, array $faqs): array` | Complete replacement rows | Complete normalized profile |
| `getPricingGuidance(int $businessId, int $actingUserId): array` | None | Pricing rows |
| `savePricingGuidance(int $businessId, int $actingUserId, array $guidance): array` | Complete replacement rows | Complete normalized profile |
| `getAppointmentRules(int $businessId, int $actingUserId): array` | None | Appointment-rule rows |
| `saveAppointmentRules(int $businessId, int $actingUserId, array $rules): array` | Complete replacement rows | Complete normalized profile |
| `getTransferRules(int $businessId, int $actingUserId): array` | None | Transfer-rule rows |
| `saveTransferRules(int $businessId, int $actingUserId, array $rules): array` | Complete replacement rows | Complete normalized profile |
| `getEscalationRules(int $businessId, int $actingUserId): array` | None | Escalation-rule rows |
| `saveEscalationRules(int $businessId, int $actingUserId, array $rules): array` | Complete replacement rows | Complete normalized profile |
| `getNotificationPreferences(int $businessId, int $actingUserId): array` | None | Notification rows |
| `saveNotificationPreferences(int $businessId, int $actingUserId, array $preferences): array` | Complete replacement rows | Complete normalized profile |
| `transitionLifecycleStatus(int $businessId, int $actingUserId, string $targetStatus): array` | One lifecycle value | Complete normalized profile |
| `calculateReadiness(int $businessId, int $actingUserId): array` | None | Live readiness result |

Representative JSON fixture shapes follow. Replace example service IDs with the
designated staging IDs. For existing rows, add that row's integer `id`; for new rows,
omit `id`. Treat every collection example as the entire desired replacement.

`update-profile` accepts a partial object:

```json
{
  "public_display_name": "Milestone Four Test Business",
  "timezone": "America/New_York",
  "default_language": "en-US",
  "primary_greeting": "Thank you for calling the designated staging business.",
  "appointment_requests_enabled": true,
  "automatic_booking_enabled": false,
  "minimum_notice_minutes": 120,
  "default_appointment_duration_minutes": 60,
  "emergency_service_enabled": false
}
```

`replace-hours` accepts an array and supports split/overnight rows:

```json
[
  {"day_of_week":0,"time_range_order":1,"is_closed":true,"is_24_hours":false,"opens_at":null,"closes_at":null},
  {"day_of_week":1,"time_range_order":1,"is_closed":false,"is_24_hours":false,"opens_at":"09:00","closes_at":"17:00"},
  {"day_of_week":2,"time_range_order":1,"is_closed":false,"is_24_hours":true,"opens_at":null,"closes_at":null},
  {"day_of_week":3,"time_range_order":1,"is_closed":false,"is_24_hours":false,"opens_at":"08:00","closes_at":"12:00"},
  {"day_of_week":3,"time_range_order":2,"is_closed":false,"is_24_hours":false,"opens_at":"13:00","closes_at":"17:00"},
  {"day_of_week":4,"time_range_order":1,"is_closed":false,"is_24_hours":false,"opens_at":"20:00","closes_at":"02:00"},
  {"day_of_week":5,"time_range_order":1,"is_closed":false,"is_24_hours":false,"opens_at":"09:00","closes_at":"17:00"},
  {"day_of_week":6,"time_range_order":1,"is_closed":true,"is_24_hours":false,"opens_at":null,"closes_at":null}
]
```

`replace-hour-exceptions` accepts an array:

```json
[
  {"exception_date":"2030-01-15","time_range_order":1,"label":"Closed for training","is_closed":true,"is_24_hours":false,"opens_at":null,"closes_at":null},
  {"exception_date":"2030-01-16","time_range_order":1,"label":"Special hours","is_closed":false,"is_24_hours":false,"opens_at":"10:00","closes_at":"14:00"}
]
```

`save-faqs` accepts an array:

```json
[
  {
    "question": "What areas do you serve?",
    "answer": "We serve the approved staging service area.",
    "channel_scope": "all",
    "is_active": true,
    "sort_order": 0
  }
]
```

`save-pricing-guidance` accepts an array:

```json
[
  {
    "guidance_type": "starting_price",
    "title": "Approved starting guidance",
    "guidance_text": "Final pricing depends on the approved scope.",
    "amount_min": "100.00",
    "amount_max": "250.00",
    "currency_code": "USD",
    "is_active": true,
    "sort_order": 0
  },
  {
    "guidance_type": "estimate_policy",
    "title": null,
    "guidance_text": "A written estimate is provided after review.",
    "amount_min": null,
    "amount_max": null,
    "currency_code": null,
    "is_active": true,
    "sort_order": 1
  }
]
```

`save-appointment-rules` accepts an array; replace `9001` with a selected standard
service ID and `9002` with Business A's custom-service ID:

```json
[
  {"rule_type":"general","sub_service_id":null,"business_custom_service_id":null,"rule_text":"Requests are reviewed before confirmation.","is_active":true,"sort_order":0},
  {"rule_type":"preparation","sub_service_id":9001,"business_custom_service_id":null,"rule_text":"Use the approved service preparation instructions.","is_active":true,"sort_order":1},
  {"rule_type":"service_eligibility","sub_service_id":null,"business_custom_service_id":9002,"rule_text":"Confirm the approved custom-service scope.","is_active":true,"sort_order":2}
]
```

`save-transfer-rules` accepts an array and orders by `priority`, not `sort_order`:

```json
[
  {
    "name": "Primary staging transfer",
    "transfer_number": "+12125550123",
    "backup_transfer_number": "+12125550124",
    "applies_during_business_hours": true,
    "applies_after_hours": false,
    "priority": 10,
    "maximum_attempts": 2,
    "fallback_behavior": "collect_message",
    "sub_service_id": null,
    "business_custom_service_id": null,
    "condition_text": "Transfer only under the approved test condition.",
    "is_active": true
  }
]
```

`save-escalation-rules` accepts an array:

```json
[
  {
    "rule_type": "owner_alert",
    "name": "Owner alert test",
    "condition_text": "The designated test condition is met.",
    "instruction_text": "Collect a callback number and flag the request.",
    "sub_service_id": null,
    "business_custom_service_id": null,
    "urgency_level": "high",
    "requires_immediate_transfer": false,
    "requires_owner_alert": true,
    "disclaimer_text": null,
    "priority": 10,
    "is_active": true
  }
]
```

`save-notification-preferences` accepts an array:

```json
[
  {
    "notification_type": "new_lead",
    "email_enabled": true,
    "sms_enabled": false,
    "in_app_enabled": true,
    "destination_email": "sbp-validation@example.test",
    "destination_phone": null,
    "daily_summary_enabled": false,
    "is_active": true
  }
]
```

The harness adapts this object to the string argument for `transition-lifecycle`:

```json
{"target_status":"in_review"}
```

Every collection save is replace-all: omitted rows are deleted. Existing `id` values
may preserve identity and `created_at`, but are checked against both business and
profile before the transaction. A new row omits `id`.

Safe service failures are `SharedBusinessProfileException`. Record `errorType()`, the
safe exception message, and `fieldErrors()`; expected types are `unauthorized`,
`business_not_found`, `profile_not_found`, `validation_failed`,
`invalid_lifecycle_transition`, `child_record_not_found`,
`cross_business_reference`, and `database_failure`. Unexpected exceptions are logged
server-side and wrapped as `database_failure`; never paste raw SQL or driver details
into the result record.

## 4. Authorization validation

Use the harness command pattern from section 3. For mutation tests, first save the
entire original collection because every save method replaces all rows.

| Test | Call | Expected result |
| --- | --- | --- |
| Own read | `getProfileForBusiness(BUSINESS_A_ID, ACTIVE_USER_A_ID)` | Success |
| Own update | `updateProfile(BUSINESS_A_ID, ACTIVE_USER_A_ID, allowlisted fixture)` | Success and persistence |
| Cross-business read | `getProfileForBusiness(BUSINESS_B_ID, ACTIVE_USER_A_ID)` | `unauthorized` |
| Cross-business update | `updateProfile(BUSINESS_B_ID, ACTIVE_USER_A_ID, allowlisted fixture)` | `unauthorized`, no mutation/audit success |
| Inactive membership | Own read and update using a user whose Business A membership is inactive | `unauthorized` |
| Inactive user | Own read using an inactive user record | `unauthorized`, even if that user has an internal admin role |
| Suspended/inactive business, customer | Read and update using an otherwise active member | `unauthorized` |
| Suspended/inactive business, internal admin | Read using active internal Admin/Super Admin | Success; the admin check occurs before customer business-state checks |
| Business-scoped Admin role | Read a business without active membership | `unauthorized`; only internal scope counts |
| Internal admin | Read Business A without membership | Success |
| Customer activation | From a complete `ready` profile, call `transitionLifecycleStatus(..., 'active')` as the customer | `unauthorized` |
| Cross-business child ID | Include a Business A FAQ ID in a complete Business B FAQ replacement | `cross_business_reference`, no changes |
| Cross-business custom service | Reference Business B's custom service in a Business A appointment rule | `cross_business_reference`, no changes |

The service itself does not verify an owner flag or a particular business role. Any
active membership passes the customer membership check. Record that behavior; do not
reinterpret it as owner-only authorization during validation.

## 5. Profile read validation

Call `getProfileForBusiness(BUSINESS_A_ID, ACTIVE_USER_A_ID)` and record the top-level
keys. The exact normalized sections must be:

```text
shared_business_facts
services
service_area
hours
exceptions
faqs
pricing_guidance
appointment_rules
transfer_rules
escalation_rules
notification_preferences
readiness
lifecycle
```

Confirm:

- `services` contains `selected_sub_services` and `custom_services`.
- `service_area` reports the authoritative mode, visit/travel booleans, base address,
  radius, custom-radius flag, and update time from existing business/247SP records.
- Child rows use normalized booleans/integers; hours and exceptions report
  `is_overnight`; rule rows include safe service names.
- `readiness` is recalculated live and `lifecycle.status` is the stored status.
- Website component/presentation data, billing/subscription data, provider
  credentials, stored readiness snapshots, admin notes, and private LeadHub notes are
  absent.

## 6. Core update validation

`updateProfile()` accepts only these keys:

```text
public_display_name
website_url
timezone
default_language
short_description
long_description
primary_greeting
value_proposition
tone
personality
prohibited_claims
appointment_requests_enabled
automatic_booking_enabled
minimum_notice_minutes
default_appointment_duration_minutes
emergency_service_enabled
```

Use a synthetic fixture that changes several fields, including a valid IANA timezone
such as `America/New_York`, then reload with `getProfileForBusiness()` and verify
persistence. Test each failure separately and compare the profile and activity count
to the pre-call state:

| Input | Expected result |
| --- | --- |
| `{"unknown_field":"x"}` | `validation_failed`; field key `profile.unknown_field` |
| `{"default_language":"english_US"}` | `validation_failed`; `default_language` |
| `{"timezone":"Eastern Standard Time"}` | `validation_failed`; `timezone` |
| `{"lifecycle_status":"active"}` | `validation_failed`; immutable/unknown field |
| `{"id":999,"business_id":BUSINESS_B_ID}` | `validation_failed`; immutable/unknown fields |
| `{"automatic_booking_enabled":true,"appointment_requests_enabled":false}` | `validation_failed`; automatic booking dependency |
| `{}` | `validation_failed`; at least one supported field is required |
| HTML-like text such as `{"primary_greeting":"<script>alert(1)</script>"}` | `validation_failed`; markup is rejected, not sanitized |

Also verify HTTP/HTTPS URL validation, language-code normalization to lowercase,
minimum notice `0..525600`, appointment duration `1..1440`, and boolean forms accepted
by the service. Restore original values through `updateProfile()` after the section.

## 7. Hours validation

Each row accepts only `id`, `day_of_week`, `time_range_order`, `is_closed`,
`is_24_hours`, `opens_at`, and `closes_at`. Days are `0` (Sunday) through `6`
(Saturday); order is `1..65535`; time is exact `HH:MM` or `HH:MM:SS` and is stored as
`HH:MM:SS`.

Build one seven-day replacement containing:

- A normal same-day range.
- One closed day represented by exactly one row with no times.
- One 24-hour day represented by exactly one row with no times.
- Two non-overlapping split-shift rows on one day with distinct order values.
- One overnight range whose close is earlier than open.
- Explicit valid rows for every remaining day.

Save with `replaceHours()`, then verify `getHours()` ordering, normalized booleans, and
`is_overnight`. The timezone is explanatory only during saving; a missing timezone
does not block the save but makes the `timezone` readiness section incomplete.

Starting from a complete known-good replacement, test one invalid full replacement at
a time:

- Overlapping ranges on the same starting day.
- Closed plus opening/closing times.
- 24 hours plus opening/closing times.
- Closed and 24 hours together.
- Equal opening and closing times.
- A closed or 24-hour row plus a second row for the same day.
- Duplicate `time_range_order` within a day.
- Missing opening or closing time on a normal row.
- Invalid day, invalid order, invalid time, non-array row, and unknown field.

Each must return `validation_failed`, preserve the entire prior hours collection,
preserve lifecycle and readiness snapshot, and create no success activity. The
database-trigger cases in section 17 separately verify rollback after the replacement
transaction has actually begun.

The implementation compares overlaps only among ranges with the same starting
`day_of_week`; an overnight range is not compared with an early range on the following
day. Add one observation case for that boundary and record the current acceptance
behavior as an implementation concern, without changing it during validation. The
same same-starting-date rule applies to hour exceptions.

## 8. Hour-exception validation

Each exception accepts only `id`, `exception_date`, `time_range_order`, `label`,
`is_closed`, `is_24_hours`, `opens_at`, and `closes_at`. Use ISO `YYYY-MM-DD` dates.

With complete replacement fixtures, test:

- Normal special hours.
- A labeled closed date such as `Closed for training`.
- A 24-hour date.
- Split ranges on one date with unique orders.
- An overnight exception.
- A `null`/empty optional label.
- Duplicate equivalent date/order rows; expect `validation_failed` on range order.
- Closed/24-hour/timed contradictions; expect `validation_failed`.
- An impossible or malformed date; expect `validation_failed` on `exception_date`.
- A failed full replacement; verify the original collection is unchanged.

Do not generate or assume holidays. Restore the original exception collection.

## 9. FAQ validation

Each FAQ accepts `id`, `question`, `answer`, `channel_scope`, `is_active`, and
`sort_order`. Valid channel scopes are `all`, `website`, `voice`, `sms`, and `chat`.
Question and answer are required plain text; maximum lengths are 500 and 5,000
characters.

Test add, update with its existing ID, reorder, deactivate, reactivate, and remove by
omitting a row from the full replacement. Then test missing question, missing answer,
unsupported channel scope, invalid order, duplicate child ID, and a Business B FAQ ID
in Business A. HTML-like tags must produce `validation_failed`; the service does not
silently strip them. Confirm the failed replacement leaves all prior FAQ rows intact.

The merged implementation requires at least one active FAQ for readiness version 1.
Zero active FAQs must put `faqs` in `incomplete_sections` with `faqs.active` missing.
Do not mark FAQs optional in the result.

## 10. Pricing-guidance validation

Each row accepts `id`, `guidance_type`, `title`, `guidance_text`, `amount_min`,
`amount_max`, `currency_code`, `is_active`, and `sort_order`. Actual supported types:

```text
starting_price
service_call_fee
estimate_policy
deposit_policy
financing
disclaimer
prohibited_statement
general_guidance
```

Test:

- `starting_price` and `service_call_fee` with minimum only, maximum only, and both.
- Non-monetary policy/disclaimer/general records without amounts.
- Two-place non-negative decimal normalization and three-letter uppercase currency.
- Reordering and deactivation.
- Invalid/negative/over-precision decimal, value above `99999999.99`, minimum greater
  than maximum, missing both amounts for a monetary type, amount without currency,
  unsupported type, missing guidance text, markup, and unknown fields.

Expected failures are `validation_failed` with field errors, and a failed replacement
must preserve the prior collection. Confirm no estimate, invoice, quote, contact, or
LeadHub record is created. Pricing guidance is optional for readiness; absence creates
only the exact warning `Approved pricing guidance has not been added; it is optional
for current profile completion.`

## 11. Appointment-rule validation

Each row accepts `id`, `rule_type`, `sub_service_id`,
`business_custom_service_id`, `rule_text`, `is_active`, and `sort_order`. Actual rule
types are `general`, `request_only`, `automatic_booking`, `minimum_notice`,
`preparation`, and `service_eligibility`.

Test full replacements containing:

- A profile-wide rule with both service IDs null/omitted.
- An active `sub_service_id` selected for Business A.
- A `business_custom_service_id` owned by Business A.
- Reordered and inactive rules.

Then test Business B's custom service (`cross_business_reference`), both service IDs
(`validation_failed`), a nonexistent standard/custom service
(`child_record_not_found`), an active standard service not selected for Business A
(`validation_failed`), an unsupported rule type, missing rule text, markup, and a
cross-business child rule ID. Verify every failure preserves the prior collection.
No calendar, availability, appointment, or booking record may be created.

## 12. Transfer-rule validation

Each row accepts `id`, `name`, `transfer_number`, `backup_transfer_number`,
`applies_during_business_hours`, `applies_after_hours`, `priority`,
`maximum_attempts`, `fallback_behavior`, `sub_service_id`,
`business_custom_service_id`, `condition_text`, and `is_active`. There is no
`sort_order`; ordering is ascending `priority`, then ID. Maximum attempts is `1..10`.
Fallback values are:

```text
create_leadhub_task
collect_message
owner_notification
voicemail
end_conversation
```

Test:

- A valid E.164 primary number and optional backup.
- For a US/Canadian Business A, a common 10-digit formatted number and verify `+1`
  normalization. For other countries, require an explicit country code.
- Business-hours only, after-hours only, and both.
- Profile-wide, selected standard-service, and same-business custom-service rules.
- Priority changes and inactive rows.
- Invalid/short/overlong phone, both hours flags false, invalid maximum attempts,
  unsupported fallback, missing name, both service references, unselected/missing
  standard service, cross-business custom service, and cross-business child ID.

Failures must be safe and atomic. Confirm no Twilio/Retell/provider request, call,
message, voicemail, notification, or LeadHub task is created; fallback values are
configuration only.

## 13. Escalation-rule validation

Each row accepts `id`, `rule_type`, `name`, `condition_text`, `instruction_text`,
`sub_service_id`, `business_custom_service_id`, `urgency_level`,
`requires_immediate_transfer`, `requires_owner_alert`, `disclaimer_text`, `priority`,
and `is_active`. Actual rule types are `immediate_transfer`, `owner_alert`,
`prohibited_ai_handling`, and `disclaimer_language`; urgency values are `low`,
`normal`, `high`, `urgent`, and `emergency`.

Test profile-wide and both valid service-specific forms, immediate-transfer and
owner-alert flags, `prohibited_ai_handling`, disclaimer-only content, instruction-only
content, priority ordering, and inactive rows. At least instruction or disclaimer text
is required. Test unsupported urgency/type, missing condition/name, missing both
instruction and disclaimer, markup, both service references, unselected/missing
standard service, cross-business custom service, and cross-business child ID.

An `emergency` urgency must fail until `emergency_service_enabled` is explicitly true;
after enabling it, the same rule must save. Confirm no transfer, AI action, provider
action, or notification delivery occurs.

## 14. Notification-preference validation

Each row accepts `id`, `notification_type`, `email_enabled`, `sms_enabled`,
`in_app_enabled`, `destination_email`, `destination_phone`,
`daily_summary_enabled`, and `is_active`. Actual types are:

```text
new_lead
missed_call
transfer_failed
urgent_lead
new_message
appointment_request
unresolved_lead_summary
```

Test valid email, valid E.164 SMS, US/Canadian 10-digit normalization, in-app only,
daily summary, inactive rows, and one row for each supported type. The implementation
accepts `daily_summary_enabled` on every supported type; record that exact behavior.

An active row with email enabled but no destination and an active row with SMS
enabled but no destination must save successfully as draft configuration, then make
`notification_destinations` readiness incomplete with the specific missing field.
Also test invalid email, invalid phone, duplicate type, unsupported type, unknown
field, and cross-business child ID. Those cases must fail atomically. Confirm no email,
SMS, in-app notification, queue job, or delivery record is created.

## 15. Lifecycle validation

Allowed deliberate transitions are exactly:

```text
draft      -> incomplete | in_review
incomplete -> draft | in_review
in_review  -> draft | incomplete | ready
ready      -> in_review | incomplete | active
active     -> in_review | incomplete | ready
```

Run and record:

1. Start incomplete and transition to `draft` through an allowed path if needed.
2. Save incomplete data and confirm a draft does not auto-advance.
3. Submit `in_review` from `draft` or `incomplete`; readiness is not required for this
   transition.
4. Attempt `ready` while readiness fails; expect `invalid_lifecycle_transition`.
5. Attempt `active` while readiness fails from a status where the transition is
   otherwise allowed; expect readiness/transition rejection before the admin check.
6. Complete all required readiness sections, move to `in_review`, then transition to
   `ready`. A customer is allowed to make the `in_review -> ready` transition.
7. From `ready`, attempt `active` as the customer; expect `unauthorized`.
8. From `ready`, transition to `active` as the internal administrator; expect success,
   `profile_completed_at` set if previously null, and `activated_at` set if previously
   null. This activates only the stored profile lifecycle.
9. Remove one required value through a SharedBusinessProfile mutation. Confirm stored
   `ready` or `active` is automatically changed to `incomplete` in the same transaction.
10. Restore the value and confirm status remains `incomplete`; writes never
    auto-advance lifecycle.
11. Request the current status again. Confirm the service returns normally and creates
    no mutation/audit event.
12. Test unsupported status and disallowed direct transitions.

Readiness and stored lifecycle are distinct. `calculateReadiness()` always uses live
data. A SharedBusinessProfile mutation recalculates readiness and automatically
demotes stored `ready`/`active`. By contrast, a change made through existing business,
service, or service-area code is outside `runMutation()`: calculated readiness can
become incomplete while the stored lifecycle remains unchanged until a later profile
mutation. Record this exact behavior; do not change it during validation.

## 16. Readiness validation

For each call, record `is_complete`, `lifecycle_status`, `completed_sections`,
`incomplete_sections`, `missing_fields`, `warnings`, `calculated_at`, and
`readiness_version` (currently `1`). Results must be deterministic except for
`calculated_at`.

Required sections and exact conditions:

| Section | Completion condition |
| --- | --- |
| `business_identity` | `businesses.business_name`, `phone`, and `email` are non-empty |
| `timezone` | Profile timezone is a valid IANA identifier |
| `services` | At least one selected standard or custom service is returned |
| `service_area` | Mode is not `unconfigured`; city/state/postal code exist; travel mode has radius greater than zero |
| `hours` | All seven days have valid, noncontradictory, unique-order, nonoverlapping rows |
| `greeting` | `primary_greeting` is non-empty |
| `faqs` | At least one active FAQ |
| `transfer_behavior` | At least one active rule and every active primary number is E.164 |
| `escalation_behavior` | At least one active rule; emergency-enabled profiles also need active emergency/immediate-transfer behavior |
| `notification_destinations` | At least one active preference; every enabled email/SMS channel has its destination |
| `appointment_rules` | At least one active rule only when appointment requests are enabled |

Warning/optional behavior:

- Pricing guidance is optional and warning-only when no active row exists.
- Appointment rules are optional and warning-only while appointment requests are
  disabled.
- Hour exceptions, descriptions, value proposition, tone, personality, website URL,
  minimum notice, duration, and backup transfer numbers are not readiness sections.
- Automatic booking adds a warning because scheduling/calendar availability is not
  implemented.

Record readiness before changes, after all required data is complete, and after
removing one required value. Specifically exercise timezone, services, service area,
hours, greeting, transfer rules, escalation rules, notification destinations, FAQs,
pricing guidance, and both appointment-request states. Confirm missing paths match the
implementation rather than a manually reconstructed checklist.

Static inspection also found that normalized selected services/readiness do not filter
`sub_services.is_active`, although service-specific rule validation does require an
active sub-service. Without changing data, check whether designated Business A already
has an inactive selected standard service:

```sql
SELECT bss.business_id, bss.sub_service_id, ss.is_active
FROM business_sub_services bss
INNER JOIN sub_services ss ON ss.id = bss.sub_service_id
WHERE bss.business_id = BUSINESS_A_ID
  AND ss.is_active <> 1;
```

If a row exists, record whether it appears in normalized services and can satisfy the
`services` readiness section while being rejected by a new service-specific rule. Do
not activate/deactivate a catalog service merely to manufacture this case. Record the
untested condition when no safe fixture exists, and open a focused defect if runtime
behavior conflicts with the approved active-service requirement.

## 17. Transaction rollback validation

Ordinary validation and ownership failures occur before `beginTransaction()`. They
prove atomic behavior but do not exercise rollback after deletion. To test true
mid-replacement rollback without changing application code, an authorized staging DBA
may install one short-lived sentinel trigger at a time. Do not do this on production.
Confirm the trigger names do not already exist, obtain staging change approval, and
drop each trigger immediately after its test.

Scenario A forces an FAQ insert failure after the service deletes the old collection:

```sql
SELECT TRIGGER_NAME
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND TRIGGER_NAME = 'sbp_m4_fail_faq_insert';

DELIMITER $$
CREATE TRIGGER sbp_m4_fail_faq_insert
BEFORE INSERT ON business_profile_faqs
FOR EACH ROW
BEGIN
  IF NEW.question = '__SBP_M4_FORCE_ROLLBACK__' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Milestone 4 rollback test';
  END IF;
END$$
DELIMITER ;
```

Submit a complete otherwise-valid FAQ replacement containing the sentinel question.
Expect safe `database_failure`; never record the raw SQL exception. Verify the exact
old FAQ rows, lifecycle, readiness snapshot, and successful activity-log count are
unchanged. Then:

```sql
DROP TRIGGER sbp_m4_fail_faq_insert;
```

Scenario B repeats the test for transfer rules:

```sql
SELECT TRIGGER_NAME
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND TRIGGER_NAME = 'sbp_m4_fail_transfer_insert';

DELIMITER $$
CREATE TRIGGER sbp_m4_fail_transfer_insert
BEFORE INSERT ON business_transfer_rules
FOR EACH ROW
BEGIN
  IF NEW.name = '__SBP_M4_FORCE_ROLLBACK__' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Milestone 4 rollback test';
  END IF;
END$$
DELIMITER ;
```

Submit a complete otherwise-valid transfer replacement containing the sentinel name.
Verify the same five invariants, then remove it:

```sql
DROP TRIGGER sbp_m4_fail_transfer_insert;
```

If the staging database role cannot create triggers, do not substitute production,
change grants, or weaken the criterion. Record transaction rollback validation as
blocked and keep Milestone 4 runtime validation—and therefore Milestone 5—blocked.

## 18. Activity-log validation

Successful mutations write one `activity_logs` row in the same transaction with:

- `business_id` equal to the authorized target business.
- `user_id` equal to the supplied acting user, including the internal administrator.
- `module_key = 'shared_business_profile'`.
- `activity_type = 'shared_business_profile_'` plus the action name.
- Subject `Shared Business Profile updated` and a safe target description.
- `metadata_json` containing `action`, `target_type`, the profile ID as `target_id`,
  and a limited `change_summary`.
- Database-generated `created_at`.

Expected action suffixes are `profile_updated`, `hours_replaced`,
`hour_exceptions_replaced`, `faqs_saved`, `pricing_guidance_saved`,
`appointment_rules_saved`, `transfer_rules_saved`, `escalation_rules_saved`,
`notification_preferences_saved`, and `lifecycle_transitioned`.

After each successful mutation, run:

```sql
SELECT
  al.id,
  al.business_id,
  al.user_id,
  al.module_key,
  al.activity_type,
  al.subject,
  al.description,
  al.metadata_json,
  al.created_at
FROM activity_logs al
WHERE al.business_id = BUSINESS_A_ID
  AND al.module_key = 'shared_business_profile'
ORDER BY al.id DESC
LIMIT 20;
```

Confirm metadata contains only changed profile field names, collection row counts, the
notification type names where applicable, or lifecycle from/to values. It must not
contain submitted wording, destination email, phone numbers, credentials, provider
data, full request payloads, or raw SQL errors. The service does not mask the numeric
business/user/profile IDs; those are expected internal audit identifiers.

Validation/authorization failures are not logged as activity events. Unexpected
database failures are sent to the PHP error log and exposed to the caller only as safe
`database_failure`. A failure after the transaction starts must not leave a success
activity row because the activity insert participates in the same transaction. Record
this behavior and compare it with existing staging conventions.

## 19. Database integrity checks

Run the following through the authorized staging SQL console. Save results in the
restricted validation work area; do not add private row values to Git.

### Baseline and reconciliation counts

Run before tests, after tests, and after restoration:

```sql
SELECT 'business_profiles' AS table_name, COUNT(*) AS row_count FROM business_profiles
UNION ALL SELECT 'business_profile_hours', COUNT(*) FROM business_profile_hours
UNION ALL SELECT 'business_profile_hour_exceptions', COUNT(*) FROM business_profile_hour_exceptions
UNION ALL SELECT 'business_profile_faqs', COUNT(*) FROM business_profile_faqs
UNION ALL SELECT 'business_profile_pricing_guidance', COUNT(*) FROM business_profile_pricing_guidance
UNION ALL SELECT 'business_appointment_rules', COUNT(*) FROM business_appointment_rules
UNION ALL SELECT 'business_transfer_rules', COUNT(*) FROM business_transfer_rules
UNION ALL SELECT 'business_escalation_rules', COUNT(*) FROM business_escalation_rules
UNION ALL SELECT 'business_notification_preferences', COUNT(*) FROM business_notification_preferences
UNION ALL SELECT 'shared_business_profile_activity', COUNT(*)
FROM activity_logs
WHERE module_key = 'shared_business_profile';
```

Child counts after restoration must match baseline. Activity count is expected to
increase by exactly the number of successful mutations and must be reconciled rather
than deleted. Do not delete legitimate audit history to make counts match.

### One profile per business

```sql
SELECT
  (SELECT COUNT(*) FROM businesses) AS business_count,
  (SELECT COUNT(*) FROM business_profiles) AS profile_count,
  (SELECT COUNT(DISTINCT business_id) FROM business_profiles) AS distinct_profile_business_count;

SELECT business_id, COUNT(*) AS profile_count
FROM business_profiles
GROUP BY business_id
HAVING COUNT(*) <> 1;
```

Expected: all three counts match and the second query returns no rows.

### Orphan and business/profile mismatch checks

```sql
SELECT 'business_profile_hours' AS table_name, COUNT(*) AS invalid_count
FROM business_profile_hours c
LEFT JOIN business_profiles bp
  ON bp.id = c.business_profile_id AND bp.business_id = c.business_id
WHERE bp.id IS NULL
UNION ALL
SELECT 'business_profile_hour_exceptions', COUNT(*)
FROM business_profile_hour_exceptions c
LEFT JOIN business_profiles bp
  ON bp.id = c.business_profile_id AND bp.business_id = c.business_id
WHERE bp.id IS NULL
UNION ALL
SELECT 'business_profile_faqs', COUNT(*)
FROM business_profile_faqs c
LEFT JOIN business_profiles bp
  ON bp.id = c.business_profile_id AND bp.business_id = c.business_id
WHERE bp.id IS NULL
UNION ALL
SELECT 'business_profile_pricing_guidance', COUNT(*)
FROM business_profile_pricing_guidance c
LEFT JOIN business_profiles bp
  ON bp.id = c.business_profile_id AND bp.business_id = c.business_id
WHERE bp.id IS NULL
UNION ALL
SELECT 'business_appointment_rules', COUNT(*)
FROM business_appointment_rules c
LEFT JOIN business_profiles bp
  ON bp.id = c.business_profile_id AND bp.business_id = c.business_id
WHERE bp.id IS NULL
UNION ALL
SELECT 'business_transfer_rules', COUNT(*)
FROM business_transfer_rules c
LEFT JOIN business_profiles bp
  ON bp.id = c.business_profile_id AND bp.business_id = c.business_id
WHERE bp.id IS NULL
UNION ALL
SELECT 'business_escalation_rules', COUNT(*)
FROM business_escalation_rules c
LEFT JOIN business_profiles bp
  ON bp.id = c.business_profile_id AND bp.business_id = c.business_id
WHERE bp.id IS NULL
UNION ALL
SELECT 'business_notification_preferences', COUNT(*)
FROM business_notification_preferences c
LEFT JOIN business_profiles bp
  ON bp.id = c.business_profile_id AND bp.business_id = c.business_id
WHERE bp.id IS NULL;
```

Expected: every `invalid_count` is zero. The composite joins detect both orphans and
`business_id`/`business_profile_id` disagreement.

### Duplicate notification types

```sql
SELECT business_profile_id, notification_type, COUNT(*) AS row_count
FROM business_notification_preferences
GROUP BY business_profile_id, notification_type
HAVING COUNT(*) > 1;
```

Expected: no rows.

### Dual and invalid service references

```sql
SELECT 'business_appointment_rules' AS table_name, id, business_id,
       sub_service_id, business_custom_service_id
FROM business_appointment_rules
WHERE sub_service_id IS NOT NULL AND business_custom_service_id IS NOT NULL
UNION ALL
SELECT 'business_transfer_rules', id, business_id,
       sub_service_id, business_custom_service_id
FROM business_transfer_rules
WHERE sub_service_id IS NOT NULL AND business_custom_service_id IS NOT NULL
UNION ALL
SELECT 'business_escalation_rules', id, business_id,
       sub_service_id, business_custom_service_id
FROM business_escalation_rules
WHERE sub_service_id IS NOT NULL AND business_custom_service_id IS NOT NULL;
```

```sql
SELECT 'business_appointment_rules' AS table_name, r.id, r.business_id,
       r.business_custom_service_id, bcs.business_id AS service_business_id
FROM business_appointment_rules r
INNER JOIN business_custom_services bcs ON bcs.id = r.business_custom_service_id
WHERE r.business_id <> bcs.business_id
UNION ALL
SELECT 'business_transfer_rules', r.id, r.business_id,
       r.business_custom_service_id, bcs.business_id
FROM business_transfer_rules r
INNER JOIN business_custom_services bcs ON bcs.id = r.business_custom_service_id
WHERE r.business_id <> bcs.business_id
UNION ALL
SELECT 'business_escalation_rules', r.id, r.business_id,
       r.business_custom_service_id, bcs.business_id
FROM business_escalation_rules r
INNER JOIN business_custom_services bcs ON bcs.id = r.business_custom_service_id
WHERE r.business_id <> bcs.business_id;
```

```sql
SELECT 'business_appointment_rules' AS table_name, r.id, r.business_id, r.sub_service_id
FROM business_appointment_rules r
LEFT JOIN business_sub_services bss
  ON bss.business_id = r.business_id AND bss.sub_service_id = r.sub_service_id
WHERE r.sub_service_id IS NOT NULL AND bss.sub_service_id IS NULL
UNION ALL
SELECT 'business_transfer_rules', r.id, r.business_id, r.sub_service_id
FROM business_transfer_rules r
LEFT JOIN business_sub_services bss
  ON bss.business_id = r.business_id AND bss.sub_service_id = r.sub_service_id
WHERE r.sub_service_id IS NOT NULL AND bss.sub_service_id IS NULL
UNION ALL
SELECT 'business_escalation_rules', r.id, r.business_id, r.sub_service_id
FROM business_escalation_rules r
LEFT JOIN business_sub_services bss
  ON bss.business_id = r.business_id AND bss.sub_service_id = r.sub_service_id
WHERE r.sub_service_id IS NOT NULL AND bss.sub_service_id IS NULL;
```

Expected: all three queries return no rows.

### Contradictory hours and exceptions

```sql
SELECT 'business_profile_hours' AS table_name, id, business_id,
       business_profile_id, day_of_week AS day_or_date
FROM business_profile_hours
WHERE (is_closed = 1 AND (is_24_hours = 1 OR opens_at IS NOT NULL OR closes_at IS NOT NULL))
   OR (is_24_hours = 1 AND (opens_at IS NOT NULL OR closes_at IS NOT NULL))
   OR (is_closed = 0 AND is_24_hours = 0 AND (opens_at IS NULL OR closes_at IS NULL))
   OR (opens_at IS NOT NULL AND closes_at IS NOT NULL AND opens_at = closes_at)
UNION ALL
SELECT 'business_profile_hour_exceptions', id, business_id,
       business_profile_id, CAST(exception_date AS CHAR)
FROM business_profile_hour_exceptions
WHERE (is_closed = 1 AND (is_24_hours = 1 OR opens_at IS NOT NULL OR closes_at IS NOT NULL))
   OR (is_24_hours = 1 AND (opens_at IS NOT NULL OR closes_at IS NOT NULL))
   OR (is_closed = 0 AND is_24_hours = 0 AND (opens_at IS NULL OR closes_at IS NULL))
   OR (opens_at IS NOT NULL AND closes_at IS NOT NULL AND opens_at = closes_at);
```

```sql
SELECT business_profile_id, day_of_week, COUNT(*) AS row_count
FROM business_profile_hours
GROUP BY business_profile_id, day_of_week
HAVING SUM(CASE WHEN is_closed = 1 OR is_24_hours = 1 THEN 1 ELSE 0 END) > 0
   AND COUNT(*) > 1;

SELECT business_profile_id, exception_date, COUNT(*) AS row_count
FROM business_profile_hour_exceptions
GROUP BY business_profile_id, exception_date
HAVING SUM(CASE WHEN is_closed = 1 OR is_24_hours = 1 THEN 1 ELSE 0 END) > 0
   AND COUNT(*) > 1;
```

Check overlaps using the service's same-starting-day/date rule, including overnight
end times shifted into the next day:

```sql
SELECT a.business_profile_id, a.day_of_week, a.id AS first_id, b.id AS second_id
FROM business_profile_hours a
INNER JOIN business_profile_hours b
  ON b.business_profile_id = a.business_profile_id
 AND b.business_id = a.business_id
 AND b.day_of_week = a.day_of_week
 AND b.id > a.id
WHERE a.opens_at IS NOT NULL AND a.closes_at IS NOT NULL
  AND b.opens_at IS NOT NULL AND b.closes_at IS NOT NULL
  AND GREATEST(
        TIME_TO_SEC(a.opens_at),
        TIME_TO_SEC(b.opens_at)
      ) < LEAST(
        TIME_TO_SEC(a.closes_at) + IF(a.closes_at <= a.opens_at, 86400, 0),
        TIME_TO_SEC(b.closes_at) + IF(b.closes_at <= b.opens_at, 86400, 0)
      );

SELECT a.business_profile_id, a.exception_date, a.id AS first_id, b.id AS second_id
FROM business_profile_hour_exceptions a
INNER JOIN business_profile_hour_exceptions b
  ON b.business_profile_id = a.business_profile_id
 AND b.business_id = a.business_id
 AND b.exception_date = a.exception_date
 AND b.id > a.id
WHERE a.opens_at IS NOT NULL AND a.closes_at IS NOT NULL
  AND b.opens_at IS NOT NULL AND b.closes_at IS NOT NULL
  AND GREATEST(
        TIME_TO_SEC(a.opens_at),
        TIME_TO_SEC(b.opens_at)
      ) < LEAST(
        TIME_TO_SEC(a.closes_at) + IF(a.closes_at <= a.opens_at, 86400, 0),
        TIME_TO_SEC(b.closes_at) + IF(b.closes_at <= b.opens_at, 86400, 0)
      );
```

Expected: every contradictory/state/overlap query returns no rows.

### Lifecycle distribution and test-record restoration

```sql
SELECT lifecycle_status, COUNT(*) AS row_count
FROM business_profiles
GROUP BY lifecycle_status
ORDER BY lifecycle_status;

SELECT id, business_id, lifecycle_status
FROM business_profiles
WHERE lifecycle_status NOT IN ('draft', 'incomplete', 'in_review', 'ready', 'active');
```

Expected: the invalid-value query returns no rows. Compare the designated Business A
and Business B profile/child rows to the secure pre-test snapshot. Restore profile
fields and every child collection through the service, restore approved business
suspension/state changes through existing admin tooling, and rerun all integrity and
count queries. Do not modify migration 021 or clean up by deleting audit history.

## 20. Regression smoke tests

After service tests and data restoration, use staging browser sessions for an active
customer and authorized administrator. Record pass/fail and a safe note for:

- Login and logout.
- Account dashboard, business list, business detail, and business selection.
- 247SP onboarding, review, private preview, Website Manager, and existing website
  content.
- Submit one designated staging website form and verify the expected LeadHub contact,
  note/task/activity path without exposing its contents in this document.
- LeadHub dashboard, contacts, contact detail, notes, tasks, and activity timeline.
- Domain pages.
- Email pages.
- Billing and subscription pages.
- Admin dashboard, users, businesses, business detail, websites, domains, email, and
  billing pages.

This plan creates no route, so there is no Shared Business Profile browser URL to test.
Do not claim a browser smoke test passed unless the operator actually ran it.

## 21. Pass/fail criteria

Milestone 4 passes staging runtime validation only when all of the following are true:

- PHP syntax and class loading pass on the deployed staging commit.
- All 20 public methods execute with their documented signatures and return shapes.
- Active customer, inactive membership/user, suspended business, internal admin,
  business-scoped admin, and cross-tenant authorization behavior matches the service.
- Valid profile and child data persists; invalid data returns safe exceptions and no
  raw SQL details.
- Cross-business child IDs and custom-service references fail without changes.
- Both controlled mid-transaction replacement failures roll back completely.
- Live readiness is deterministic and reports exact completed/incomplete sections,
  missing fields, warnings, lifecycle status, and version.
- Lifecycle transitions, readiness gates, admin-only activation, and automatic
  mutation-time demotion match the implementation.
- Successful activity records are present and sanitized; failed mutations do not
  falsely record success.
- Every integrity query has the expected result, designated records are restored, and
  counts reconcile.
- Regression smoke tests pass.
- No database corruption, unexpected customer change, provider action, delivery,
  route, migration, or production access occurs.

Any required failure, unperformed required test, trigger-permission block, unexplained
warning, or unreconciled change is a final `FAIL`/`BLOCKED`. Milestone 5 remains blocked
until defects are resolved, the affected tests are rerun, and a truthful passing result
is committed.

## 22. Result-recording template

Copy this template into a dated section below or a focused follow-up result document.
Do not replace placeholders with credentials, private customer data, raw payloads, or
raw SQL errors.

```markdown
# Milestone 4 staging runtime validation result

- Validation date/time (timezone):
- Deployed commit:
- Operator:
- Environment confirmed as staging: YES / NO
- Business A ID (masked where appropriate):
- Business B ID (masked where appropriate):
- Active user A ID (masked where appropriate):
- Internal admin ID (masked where appropriate):
- Same-business custom-service ID (masked):
- Other-business custom-service ID (masked):
- Original data snapshot secured: YES / NO
- Baseline counts recorded: YES / NO

## Syntax and loading

- PHP version:
- `SharedBusinessProfile.php` lint: PASS / FAIL
- PR #83 changed-PHP lint: PASS / FAIL
- Class/dependency load: PASS / FAIL
- Reflection/signature check: PASS / FAIL
- Evidence reference (no secrets):

## Authorization and tenant isolation

| Test | Result | Exception/error type | Safe note |
| --- | --- | --- | --- |
| Active user own read | | | |
| Active user own update | | | |
| Cross-business read | | | |
| Cross-business update | | | |
| Inactive membership | | | |
| Inactive user | | | |
| Suspended business customer | | | |
| Suspended business internal admin | | | |
| Business-scoped Admin role | | | |
| Internal admin access | | | |
| Customer activation attempt | | | |
| Cross-business child ID | | | |
| Cross-business custom service | | | |

## Section results

| Section | Valid cases | Invalid cases | Persistence/rollback | Result | Safe note |
| --- | --- | --- | --- | --- | --- |
| Profile read/shape | | | | | |
| Core profile update | | | | | |
| Hours | | | | | |
| Hour exceptions | | | | | |
| FAQs | | | | | |
| Pricing guidance | | | | | |
| Appointment rules | | | | | |
| Transfer rules | | | | | |
| Escalation rules | | | | | |
| Notification preferences | | | | | |

## Readiness

- Before-test readiness:
- Complete-data readiness:
- One-required-value-removed readiness:
- Required sections matched implementation: PASS / FAIL
- Optional/warning behavior matched: PASS / FAIL
- Missing fields matched: PASS / FAIL
- Deterministic except `calculated_at`: PASS / FAIL
- Safe note:

## Lifecycle

- Transition matrix: PASS / FAIL
- Ready gate: PASS / FAIL
- Active gate: PASS / FAIL
- Customer activation blocked: PASS / FAIL
- Internal admin activation: PASS / FAIL
- Mutation-time automatic demotion: PASS / FAIL
- External-authoritative-change behavior recorded: PASS / FAIL
- Stored status after restoration:
- Safe note:

## Transactions and activity

- FAQ forced rollback: PASS / FAIL / BLOCKED
- Transfer forced rollback: PASS / FAIL / BLOCKED
- No partial child rows: PASS / FAIL
- Lifecycle/snapshot unchanged on failure: PASS / FAIL
- No false success activity: PASS / FAIL
- Successful activity summaries safe: PASS / FAIL
- Failure logging matches convention: PASS / FAIL
- Temporary triggers removed: YES / NO / NOT CREATED
- Safe note:

## Integrity and regression

- One profile per business: PASS / FAIL
- Orphans/business-profile mismatches: PASS / FAIL
- Duplicate notifications: PASS / FAIL
- Dual/cross-business service references: PASS / FAIL
- Contradictory/overlapping hours: PASS / FAIL
- Lifecycle distribution: PASS / FAIL
- Baseline counts reconciled: PASS / FAIL
- Designated records restored: PASS / FAIL
- Regression smoke suite: PASS / FAIL
- Safe note:

## Closeout

- Warnings:
- Defects discovered:
- Defect issue/PR references:
- Cleanup completed:
- Unexpected staging changes: NONE / describe safely
- Final result: PASS / FAIL / BLOCKED
- Milestone 5 remains blocked: YES / NO
- Operator sign-off:
```

The template and conditional blocker language above are retained as the original test
plan. The completed result follows and does not rewrite the earlier stop conditions.

## 23. Final staging runtime validation result

Final result: **PASS**

- Deployed commit: `d11bd0e7d14b9d9dd432f3ce244a9b2bbebfafb7`
- Environment: `APP_ENV=staging`
- Database: `ubo_staging`
- Staging worktree: clean and matching `origin/main`
- Migration difference: none
- Repository-wide PHP lint: PASS for 84 PHP files
- Focused DomainManager regression test: PASS
- Live DNS ordering fixture: PASS
- Authenticated Domains page: PASS
- Apache/PHP error-log delta: zero
- Customer/application page checks: 20 of 20 PASS
- Admin page checks: 10 of 10 PASS
- Final report: `/home/codex-validation/ubo-sbp-validation/MILESTONE-4-REGRESSION-RERUN-3.md`
- Final report SHA-256: `8ea329ecc1f1515eaafe28cf5284d6e6f6a97bc61ec010b106e4a67620f849b4`
- Milestone 5 remains blocked: NO
- Milestone 5 status: unblocked and ready to begin; implementation not started

### Completed service coverage

Validation completed environment/deployment checks, class loading and signatures,
schema presence, customer and internal-admin authorization, tenant isolation,
inactive user/membership rejection, suspended-business behavior, profile updates,
weekly hours, hour exceptions, FAQs, pricing guidance, appointment rules, transfer
rules, escalation rules, notification preferences, readiness, lifecycle transitions,
lifecycle timestamps, automatic demotion, sanitized activity logging, transaction
atomicity, trigger-based rollback, exact staging cleanup/reconciliation, and the full
regression smoke suite.

The approved website-form smoke created exactly one contact, one note, one task, and
one activity. No unrelated database change was found.

### Observed service contract

- Partial weekly-hour collections are accepted by the write API; readiness identifies
  missing days.
- Duplicate FAQ `sort_order` values are accepted.
- An FAQ collection containing only inactive rows is accepted; readiness requires at
  least one active FAQ.
- Pricing guidance is optional for readiness and produces a warning when absent.
- Appointment rules become required when appointment requests are enabled.
- Breaking a required readiness condition while active automatically demotes the
  profile.
- `profile_completed_at` is initialized on the first transition to `ready` and is not
  later cleared by the API.
- `activated_at` is initialized on the first transition to `active` and is not later
  cleared by the API.
- A direct active-target/readiness-incomplete case cannot be constructed through the
  API because breaking readiness first automatically demotes a `ready` or `active`
  profile.
- The `in_review`-to-`ready` readiness gate was directly verified.
- Failed transactional mutations did not create false success activity records.

### Domains regression history

PR #85, fix commit `cffc220`, corrected the original authenticated Domains
PDOException. MySQL `ANSI_QUOTES` treated double-quoted values inside `FIELD(...)` as
identifiers; the query now uses ANSI-safe single-quoted string literals. No migration
or schema change was required.

PR #86, fix commit `e2b3b9e769ee8e0037754c22e00d3a9e3b58f3fb` and
merge/deployed commit `d11bd0e7d14b9d9dd432f3ce244a9b2bbebfafb7`, corrected
the remaining ordering defect. Ascending `FIELD(...)` assigns unlisted statuses rank
zero, so the query now uses explicit `CASE` ranking for `pending`, `planned`,
`synced`, and `verified`, with all remaining statuses afterward. Ties are ordered by
`status`, `record_type`, `host`, and `id`. No migration or schema change was required.

### Cleanup and restoration

Businesses 6, 7, and 8 matched their private cleanup baselines. All eight Shared
Business Profile child tables were empty after cleanup. The two synthetic custom
services, temporary validation triggers, and Shared Business Profile sentinels were
removed. Repository and database reconciliation passed.

Validation used three staging-only restoration exceptions:

- A transactional restoration of Business A `lifecycle_status`,
  `profile_completed_at`, `activated_at`, and `updated_at`.
- A later transactional restoration of Business A `updated_at` only.
- Direct deletion of custom service 1 for Business 6 and custom service 2 for
  Business 7.

These narrowly scoped staging fixture restorations do not establish a production
procedure.

### Retained blocked-run evidence

The original blocked report and two supplemental blocked reports remain unchanged at
their recorded checksums. They are retained audit evidence for the original Domains
PDOException, the ANSI_QUOTES correction, the `FIELD(...)` ranking correction, the
temporary log-permission blocker, and the reason each run stopped before continuing:

- `7fb7e5f218606969c9d75114ff5086fda68bf0bca00faae0aa4477c3f0892e76`
- `d67aee783451097e5e34b55fb15d3d0d82e11b6c09b4ba23f56148fa3f2064a2`
- `064ffaaec75c67d0792682810a65932af41f75646354006fe655c0cbff7deaea`
