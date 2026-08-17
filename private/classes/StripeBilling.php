<?php

declare(strict_types=1);

require_once __DIR__ . '/BillingFoundation.php';
require_once __DIR__ . '/Database.php';

final class StripeBilling
{
    private const API_BASE = 'https://api.stripe.com/v1';
    private const WEBHOOK_TOLERANCE_SECONDS = 300;
    private const OPERATION_NAMESPACE = 'ubo-247sp-p2-v1';

    public static function checkoutConfigurationIssues(?array $subscription = null): array
    {
        $issues = [];
        foreach (['STRIPE_MODE', 'STRIPE_SECRET_KEY', 'STRIPE_SUCCESS_URL', 'STRIPE_CANCEL_URL'] as $key) {
            if (trim((string) Database::config($key, '')) === '') {
                $issues[] = $key;
            }
        }

        $mode = strtolower(trim((string) Database::config('STRIPE_MODE', '')));
        $environment = strtolower(trim((string) Database::config('APP_ENV', '')));
        $secretKey = trim((string) Database::config('STRIPE_SECRET_KEY', ''));
        if (!in_array($mode, ['test', 'live'], true)) {
            $issues[] = 'STRIPE_MODE_INVALID';
        } elseif (($environment === 'production' && $mode !== 'live')
            || ($environment !== 'production' && $mode !== 'test')
        ) {
            $issues[] = 'STRIPE_MODE_ENVIRONMENT_MISMATCH';
        }
        if (($mode === 'test' && !str_starts_with($secretKey, 'sk_test_'))
            || ($mode === 'live' && !str_starts_with($secretKey, 'sk_live_'))
        ) {
            $issues[] = 'STRIPE_SECRET_MODE_MISMATCH';
        }

        if ($subscription !== null) {
            if ((string) ($subscription['product_key'] ?? '') !== '247sp'
                || (int) ($subscription['commercial_terms_id'] ?? 0) <= 0
                || (int) ($subscription['allocation_id'] ?? 0) <= 0
            ) {
                $issues[] = 'LOCKED_247SP_COMMERCIAL_TERMS';
            }

            $recurringRef = trim((string) ($subscription['locked_stripe_recurring_price_ref'] ?? ''));
            if (!self::validPriceReference($recurringRef)) {
                $issues[] = 'LOCKED_247SP_RECURRING_PRICE';
            }

            if ((float) ($subscription['setup_fee'] ?? 0) > 0) {
                $setupRef = trim((string) ($subscription['locked_stripe_setup_price_ref'] ?? ''));
                if (!self::validPriceReference($setupRef)) {
                    $issues[] = 'LOCKED_247SP_SETUP_PRICE';
                }
            }
        }

        return array_values(array_unique($issues));
    }

    public static function createCheckoutSession(array $user, array $business, array $subscription): array
    {
        if (count(self::checkoutConfigurationIssues($subscription)) > 0) {
            throw new RuntimeException('Payment setup configuration is not available.');
        }

        $subscriptionId = (int) ($subscription['id'] ?? $subscription['subscription_id'] ?? 0);
        $businessId = (int) ($business['id'] ?? 0);
        if ($subscriptionId <= 0
            || $businessId <= 0
            || $businessId !== (int) ($subscription['business_id'] ?? 0)
        ) {
            throw new InvalidArgumentException('The requested subscription was not found.');
        }

        $stripeCustomerId = trim((string) ($subscription['stripe_customer_id'] ?? ''));
        if ($stripeCustomerId === '') {
            $customer = self::createStripeCustomer($user, $business, $subscription);
            $stripeCustomerId = trim((string) ($customer['id'] ?? ''));
            if ($stripeCustomerId === '') {
                throw new RuntimeException('Payment setup is temporarily unavailable.');
            }
            BillingFoundation::updateSubscriptionBillingState($subscriptionId, [
                'stripe_customer_id' => $stripeCustomerId,
                'payment_method_status' => 'pending',
            ]);
        }

        $existingSessionId = trim((string) ($subscription['stripe_checkout_session_id'] ?? ''));
        $attemptIdentity = '';
        if ($existingSessionId !== '') {
            $existingSession = self::apiRequest('GET', '/checkout/sessions/' . rawurlencode($existingSessionId));
            $existingStatus = (string) ($existingSession['status'] ?? '');
            $existingUrl = trim((string) ($existingSession['url'] ?? ''));

            if ($existingStatus === 'open' && $existingUrl !== '') {
                $existingSession['ubo_reused'] = true;
                return $existingSession;
            }
            if ($existingStatus === 'complete') {
                self::handleCheckoutSessionCompleted($existingSession);
                $existingSession['ubo_already_complete'] = true;
                return $existingSession;
            }
            $attemptIdentity = $existingSessionId;
        }

        $session = self::apiRequest(
            'POST',
            '/checkout/sessions',
            self::buildCheckoutSessionRequest($business, $subscription, $stripeCustomerId),
            self::providerOperationIdentity($subscription, 'checkout_session', $attemptIdentity)
        );
        $checkoutSessionId = trim((string) ($session['id'] ?? ''));
        if ($checkoutSessionId === '' || trim((string) ($session['url'] ?? '')) === '') {
            throw new RuntimeException('Payment setup is temporarily unavailable.');
        }

        BillingFoundation::updateSubscriptionBillingState($subscriptionId, [
            'stripe_customer_id' => $stripeCustomerId,
            'stripe_checkout_session_id' => $checkoutSessionId,
            'payment_method_status' => 'pending',
            'status' => 'pending_payment',
        ]);

        return $session;
    }

    public static function buildCheckoutSessionRequest(
        array $business,
        array $subscription,
        string $stripeCustomerId
    ): array {
        $subscriptionId = (int) ($subscription['id'] ?? $subscription['subscription_id'] ?? 0);
        $businessId = (int) ($business['id'] ?? 0);
        $allocationId = (int) ($subscription['allocation_id'] ?? 0);
        $configurationVersion = (int) ($subscription['configuration_version'] ?? 0);
        $recurringPrice = trim((string) ($subscription['locked_stripe_recurring_price_ref'] ?? ''));

        if ($subscriptionId <= 0
            || $businessId <= 0
            || $businessId !== (int) ($subscription['business_id'] ?? 0)
            || (string) ($subscription['product_key'] ?? '') !== '247sp'
            || $allocationId <= 0
            || $configurationVersion <= 0
            || !self::validPriceReference($recurringPrice)
            || trim($stripeCustomerId) === ''
        ) {
            throw new InvalidArgumentException('The locked 247SP billing contract is incomplete.');
        }

        $lineItems = [['price' => $recurringPrice, 'quantity' => 1]];
        if ((float) ($subscription['setup_fee'] ?? 0) > 0) {
            $setupPrice = trim((string) ($subscription['locked_stripe_setup_price_ref'] ?? ''));
            if (!self::validPriceReference($setupPrice)) {
                throw new InvalidArgumentException('The locked 247SP setup billing contract is incomplete.');
            }
            $lineItems[] = ['price' => $setupPrice, 'quantity' => 1];
        }

        $metadata = [
            'business_id' => (string) $businessId,
            'subscription_id' => (string) $subscriptionId,
            'allocation_id' => (string) $allocationId,
            'product_key' => '247sp',
            'configuration_version' => (string) $configurationVersion,
        ];
        $subscriptionData = ['metadata' => $metadata];

        if ((int) ($subscription['free_introductory_months'] ?? 0) > 0) {
            $trialEnd = self::timestampFromUtc((string) ($subscription['introductory_period_expires_at'] ?? ''));
            $recurringStart = self::timestampFromUtc((string) ($subscription['recurring_billing_starts_at'] ?? ''));
            if ($trialEnd !== $recurringStart || $trialEnd < time() + (48 * 60 * 60)) {
                throw new RuntimeException('The stored introductory billing date is not eligible for Checkout.');
            }
            $subscriptionData['trial_end'] = $trialEnd;
            $subscriptionData['trial_settings'] = [
                'end_behavior' => ['missing_payment_method' => 'cancel'],
            ];
        }

        return [
            'mode' => 'subscription',
            'customer' => trim($stripeCustomerId),
            'client_reference_id' => (string) $businessId,
            'success_url' => self::returnUrl('STRIPE_SUCCESS_URL', $businessId, true),
            'cancel_url' => self::returnUrl('STRIPE_CANCEL_URL', $businessId, false),
            'payment_method_collection' => 'always',
            'line_items' => $lineItems,
            'metadata' => $metadata,
            'subscription_data' => $subscriptionData,
        ];
    }

    public static function providerOperationIdentity(
        array $subscription,
        string $operation,
        string $attemptIdentity = ''
    ): string {
        $subscriptionId = (int) ($subscription['id'] ?? $subscription['subscription_id'] ?? 0);
        $allocationId = (int) ($subscription['allocation_id'] ?? 0);
        $configurationVersion = (int) ($subscription['configuration_version'] ?? 0);
        $operation = strtolower(trim($operation));
        if ($subscriptionId <= 0
            || $allocationId <= 0
            || $configurationVersion <= 0
            || preg_match('/^[a-z0-9_]{3,40}$/', $operation) !== 1
        ) {
            throw new InvalidArgumentException('Provider operation context is invalid.');
        }

        $identity = sprintf(
            '%s:%s:s%d:a%d:v%d',
            self::OPERATION_NAMESPACE,
            $operation,
            $subscriptionId,
            $allocationId,
            $configurationVersion
        );
        if ($attemptIdentity !== '') {
            $identity .= ':r' . substr(hash('sha256', $attemptIdentity), 0, 20);
        }

        return $identity;
    }

    public static function checkoutSessionRedirectUrl(array $session): string
    {
        $url = trim((string) ($session['url'] ?? ''));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (parse_url($url, PHP_URL_SCHEME) !== 'https'
            || ($host !== 'checkout.stripe.com' && !str_ends_with($host, '.checkout.stripe.com'))
        ) {
            throw new RuntimeException('Payment setup returned an invalid redirect.');
        }

        return $url;
    }

    public static function handleWebhook(string $payload, string $signatureHeader): array
    {
        $event = self::verifyWebhookEvent($payload, $signatureHeader);
        $eventId = trim((string) ($event['id'] ?? ''));
        $eventType = trim((string) ($event['type'] ?? ''));
        if ($eventId === '' || $eventType === '') {
            throw new RuntimeException('Stripe webhook event is missing required identifiers.');
        }

        $claim = BillingFoundation::claimStripeWebhookEvent($eventId, $eventType, $payload);
        if ($claim === 'already_processed') {
            return ['status' => $claim, 'event_id' => $eventId, 'event_type' => $eventType];
        }
        if ($claim !== 'claimed') {
            throw new RuntimeException('Stripe webhook event is already being processed.');
        }

        try {
            $object = $event['data']['object'] ?? [];
            if (!is_array($object)) {
                throw new RuntimeException('Stripe webhook object payload is invalid.');
            }
            switch ($eventType) {
                case 'checkout.session.completed':
                    self::handleCheckoutSessionCompleted($object);
                    break;
                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                case 'customer.subscription.deleted':
                    self::handleSubscriptionEvent($object);
                    break;
                case 'invoice.payment_succeeded':
                    self::handleInvoiceEvent($object, true, $eventId);
                    break;
                case 'invoice.payment_failed':
                    self::handleInvoiceEvent($object, false, $eventId);
                    break;
            }

            BillingFoundation::markStripeWebhookEvent($eventId, 'processed');
            return ['status' => 'processed', 'event_id' => $eventId, 'event_type' => $eventType];
        } catch (Throwable $exception) {
            BillingFoundation::markStripeWebhookEvent($eventId, 'failed', get_class($exception));
            error_log('[StripeBilling] webhook processing failed: ' . get_class($exception));
            throw $exception;
        }
    }

    private static function handleCheckoutSessionCompleted(array $session): void
    {
        $subscription = self::subscriptionFromMetadataOrReferences($session);
        if ($subscription === null) {
            throw new RuntimeException('Local subscription was not found for Stripe checkout session.');
        }

        $candidateStatus = self::introductoryPeriodIsCurrent($subscription)
            ? 'trial'
            : ((string) ($session['payment_status'] ?? '') === 'paid' ? 'active' : 'pending_payment');
        $candidateStatus = self::statusWithoutCheckoutRegression((string) ($subscription['status'] ?? ''), $candidateStatus);
        BillingFoundation::updateSubscriptionBillingState((int) $subscription['id'], [
            'status' => $candidateStatus,
            'stripe_customer_id' => self::newOrExistingReference($session['customer'] ?? null, $subscription['stripe_customer_id'] ?? null),
            'stripe_subscription_id' => self::newOrExistingReference($session['subscription'] ?? null, $subscription['stripe_subscription_id'] ?? null),
            'stripe_checkout_session_id' => self::newOrExistingReference($session['id'] ?? null, $subscription['stripe_checkout_session_id'] ?? null),
            'payment_method_status' => (string) ($subscription['status'] ?? '') === 'cancelled'
                ? 'cancelled'
                : 'complete',
        ]);
    }

    private static function handleSubscriptionEvent(array $stripeSubscription): void
    {
        $subscription = self::subscriptionFromMetadataOrReferences($stripeSubscription);
        if ($subscription === null) {
            throw new RuntimeException('Local subscription was not found for Stripe subscription event.');
        }

        $incomingPeriodEnd = self::dateTimeFromTimestamp($stripeSubscription['current_period_end'] ?? null);
        $storedPeriodEnd = self::stringOrNull($subscription['current_period_end'] ?? null);
        $stalePeriod = $incomingPeriodEnd !== null && $storedPeriodEnd !== null && $incomingPeriodEnd < $storedPeriodEnd;
        $localStatus = self::localStatusForStripeSubscription((string) ($stripeSubscription['status'] ?? ''));
        $fields = [
            'stripe_customer_id' => self::newOrExistingReference($stripeSubscription['customer'] ?? null, $subscription['stripe_customer_id'] ?? null),
            'stripe_subscription_id' => self::newOrExistingReference($stripeSubscription['id'] ?? null, $subscription['stripe_subscription_id'] ?? null),
        ];

        if (!$stalePeriod) {
            if ((string) ($subscription['status'] ?? '') === 'active' && $localStatus === 'trial') {
                $localStatus = 'active';
            }
            $fields += [
                'status' => $localStatus,
                'stripe_latest_invoice_id' => self::newOrExistingReference($stripeSubscription['latest_invoice'] ?? null, $subscription['stripe_latest_invoice_id'] ?? null),
                'payment_method_status' => self::paymentMethodStatusForProvider(
                    $localStatus,
                    $stripeSubscription,
                    (string) ($subscription['payment_method_status'] ?? '')
                ),
                'current_period_start' => self::dateTimeFromTimestamp($stripeSubscription['current_period_start'] ?? null),
                'current_period_end' => $incomingPeriodEnd,
                'cancel_at_period_end' => !empty($stripeSubscription['cancel_at_period_end']) ? 1 : 0,
            ];
        }
        BillingFoundation::updateSubscriptionBillingState((int) $subscription['id'], $fields);
    }

    private static function handleInvoiceEvent(array $invoice, bool $paid, string $eventId): void
    {
        $subscription = self::subscriptionFromMetadataOrReferences($invoice);
        if ($subscription === null) {
            throw new RuntimeException('Local subscription was not found for Stripe invoice event.');
        }

        $amountMinor = $paid ? (int) ($invoice['amount_paid'] ?? 0) : (int) ($invoice['amount_due'] ?? 0);
        $stripeInvoiceId = self::stringOrNull($invoice['id'] ?? null);
        if ($stripeInvoiceId === null) {
            throw new RuntimeException('Stripe invoice event is missing its invoice identifier.');
        }
        $effectivePaymentStatus = BillingFoundation::recordStripePayment((int) $subscription['id'], [
            'payment_type' => 'stripe_invoice',
            'amount' => ((float) $amountMinor / 100),
            'status' => $paid ? 'paid' : 'failed',
            'transaction_reference' => $stripeInvoiceId,
            'stripe_invoice_id' => $stripeInvoiceId,
            'stripe_payment_intent_id' => self::stringOrNull($invoice['payment_intent'] ?? null),
            'stripe_event_id' => $eventId,
            'invoice_url' => self::stringOrNull($invoice['hosted_invoice_url'] ?? null),
        ]);

        $isFreeIntroInvoice = $paid && $amountMinor === 0 && self::introductoryPeriodIsCurrent($subscription);
        $effectivelyPaid = $effectivePaymentStatus === 'paid';
        $candidateStatus = $isFreeIntroInvoice ? 'trial' : ($effectivelyPaid ? 'active' : 'past_due');
        if ((string) ($subscription['status'] ?? '') === 'cancelled') {
            $candidateStatus = 'cancelled';
        }
        BillingFoundation::updateSubscriptionBillingState((int) $subscription['id'], [
            'status' => $candidateStatus,
            'stripe_customer_id' => self::newOrExistingReference($invoice['customer'] ?? null, $subscription['stripe_customer_id'] ?? null),
            'stripe_subscription_id' => self::newOrExistingReference(self::stripeSubscriptionIdFromObject($invoice), $subscription['stripe_subscription_id'] ?? null),
            'stripe_latest_invoice_id' => self::newOrExistingReference($stripeInvoiceId, $subscription['stripe_latest_invoice_id'] ?? null),
            'payment_method_status' => (string) ($subscription['status'] ?? '') === 'cancelled'
                ? 'cancelled'
                : ($isFreeIntroInvoice
                    ? self::preserveCompletePaymentMethod((string) ($subscription['payment_method_status'] ?? ''))
                    : ($effectivelyPaid ? 'complete' : 'failed')),
        ]);
    }

    private static function subscriptionFromMetadataOrReferences(array $object): ?array
    {
        $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];
        $subscriptionId = (int) ($metadata['subscription_id'] ?? 0);
        if ($subscriptionId > 0) {
            $subscription = BillingFoundation::adminSubscription($subscriptionId);
            if ($subscription !== null) {
                self::assertWebhookSubscriptionContext($subscription);
                if (isset($metadata['business_id']) && (int) $metadata['business_id'] !== (int) $subscription['business_id']) {
                    throw new RuntimeException('Stripe metadata does not match the local subscription.');
                }
                if (isset($metadata['product_key']) && (string) $metadata['product_key'] !== '247sp') {
                    throw new RuntimeException('Stripe metadata does not match the local product.');
                }
                if (isset($metadata['allocation_id']) && (int) $metadata['allocation_id'] !== (int) $subscription['allocation_id']) {
                    throw new RuntimeException('Stripe metadata does not match the local allocation.');
                }
                if (isset($metadata['configuration_version'])
                    && (int) $metadata['configuration_version'] !== (int) $subscription['configuration_version']
                ) {
                    throw new RuntimeException('Stripe metadata does not match the local pricing version.');
                }
                return $subscription;
            }
        }

        $stripeSubscriptionId = self::stripeSubscriptionIdFromObject($object);
        if ($stripeSubscriptionId !== null) {
            $subscription = BillingFoundation::subscriptionByStripeSubscriptionId($stripeSubscriptionId);
            if ($subscription !== null) {
                self::assertWebhookSubscriptionContext($subscription);
                return $subscription;
            }
        }
        $checkoutSessionId = self::stringOrNull($object['id'] ?? null);
        if (($object['object'] ?? '') === 'checkout.session' && $checkoutSessionId !== null) {
            $subscription = BillingFoundation::subscriptionByStripeCheckoutSessionId($checkoutSessionId);
            if ($subscription !== null) {
                self::assertWebhookSubscriptionContext($subscription);
                return $subscription;
            }
        }
        $stripeCustomerId = self::stringOrNull($object['customer'] ?? null);
        $subscription = $stripeCustomerId === null
            ? null
            : BillingFoundation::subscriptionByStripeCustomerId($stripeCustomerId, '247sp');
        if ($subscription !== null) {
            self::assertWebhookSubscriptionContext($subscription);
        }
        return $subscription;
    }

    private static function assertWebhookSubscriptionContext(array $subscription): void
    {
        if ((string) ($subscription['product_key'] ?? '') !== '247sp'
            || (int) ($subscription['commercial_terms_id'] ?? 0) <= 0
            || (int) ($subscription['allocation_id'] ?? 0) <= 0
        ) {
            throw new RuntimeException('Stripe webhook subscription context is invalid.');
        }
    }

    private static function createStripeCustomer(array $user, array $business, array $subscription): array
    {
        return self::apiRequest(
            'POST',
            '/customers',
            [
                'email' => trim((string) ($user['email'] ?? $business['email'] ?? '')),
                'name' => trim((string) ($business['business_name'] ?? '')),
                'metadata' => [
                    'business_id' => (string) ($business['id'] ?? ''),
                    'subscription_id' => (string) ($subscription['id'] ?? $subscription['subscription_id'] ?? ''),
                    'allocation_id' => (string) ($subscription['allocation_id'] ?? ''),
                    'product_key' => '247sp',
                    'configuration_version' => (string) ($subscription['configuration_version'] ?? ''),
                ],
            ],
            self::providerOperationIdentity($subscription, 'customer_create')
        );
    }

    private static function apiRequest(
        string $method,
        string $path,
        array $params = [],
        ?string $idempotencyKey = null
    ): array {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Payment setup is temporarily unavailable.');
        }
        $secretKey = trim((string) Database::config('STRIPE_SECRET_KEY', ''));
        if ($secretKey === '') {
            throw new RuntimeException('Payment setup is temporarily unavailable.');
        }

        $method = strtoupper($method);
        $curl = curl_init(self::API_BASE . $path);
        if ($curl === false) {
            throw new RuntimeException('Payment setup is temporarily unavailable.');
        }
        $headers = ['Content-Type: application/x-www-form-urlencoded'];
        if ($method === 'POST') {
            if ($idempotencyKey === null || trim($idempotencyKey) === '') {
                curl_close($curl);
                throw new RuntimeException('Provider operation identity is missing.');
            }
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERPWD, $secretKey . ':');
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        if ($method === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query(self::flattenParams($params), '', '&'));
        }

        $response = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($response === false) {
            error_log('[StripeBilling] provider request transport failure.');
            throw new RuntimeException('Payment setup is temporarily unavailable.');
        }
        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded) || $statusCode < 200 || $statusCode >= 300) {
            error_log('[StripeBilling] provider request failed with HTTP status ' . $statusCode . '.');
            throw new RuntimeException('Payment setup is temporarily unavailable.');
        }
        return $decoded;
    }

    private static function verifyWebhookEvent(string $payload, string $signatureHeader): array
    {
        $webhookSecret = trim((string) Database::config('STRIPE_WEBHOOK_SECRET', ''));
        if ($webhookSecret === '') {
            throw new RuntimeException('Stripe webhook configuration is not available.');
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') {
                $timestamp = ctype_digit($value) ? (int) $value : null;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }
        if ($timestamp === null || count($signatures) === 0
            || abs(time() - $timestamp) > self::WEBHOOK_TOLERANCE_SECONDS
        ) {
            throw new RuntimeException('Stripe webhook signature is invalid.');
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $webhookSecret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                $event = json_decode($payload, true);
                if (!is_array($event)) {
                    throw new RuntimeException('Stripe webhook payload is invalid.');
                }
                return $event;
            }
        }
        throw new RuntimeException('Stripe webhook signature is invalid.');
    }

    private static function returnUrl(string $configKey, int $businessId, bool $includeSession): string
    {
        $url = trim((string) Database::config($configKey, ''));
        $validationUrl = str_replace(
            ['{BUSINESS_ID}', '{CHECKOUT_SESSION_ID}'],
            ['1', 'session'],
            $url
        );
        if ($url === '' || filter_var($validationUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Payment return configuration is not available.');
        }
        $url = str_replace('{BUSINESS_ID}', urlencode((string) $businessId), $url);
        if (strpos($url, 'business_id=') === false) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'business_id=' . urlencode((string) $businessId);
        }
        if ($includeSession && strpos($url, '{CHECKOUT_SESSION_ID}') === false) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'checkout_session_id={CHECKOUT_SESSION_ID}';
        }
        return $url;
    }

    private static function localStatusForStripeSubscription(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'active' => 'active',
            'trialing' => 'trial',
            'past_due', 'unpaid', 'paused' => 'past_due',
            'canceled' => 'cancelled',
            default => 'pending_payment',
        };
    }

    private static function paymentMethodStatusForProvider(
        string $localStatus,
        array $stripeSubscription,
        string $existingStatus
    ): string {
        if ($localStatus === 'cancelled') {
            return 'cancelled';
        }
        if ($localStatus === 'past_due') {
            return 'failed';
        }
        if ($localStatus === 'active'
            || self::stringOrNull($stripeSubscription['default_payment_method'] ?? null) !== null
            || $existingStatus === 'complete'
        ) {
            return 'complete';
        }
        return 'pending';
    }

    private static function statusWithoutCheckoutRegression(string $existing, string $candidate): string
    {
        if ($existing === 'cancelled') {
            return 'cancelled';
        }
        if ($existing === 'active' && in_array($candidate, ['trial', 'pending_payment'], true)) {
            return 'active';
        }
        return $candidate;
    }

    private static function introductoryPeriodIsCurrent(array $subscription): bool
    {
        if ((int) ($subscription['free_introductory_months'] ?? 0) <= 0) {
            return false;
        }
        $expiresAt = self::stringOrNull($subscription['introductory_period_expires_at'] ?? null);
        return $expiresAt !== null && self::timestampFromUtc($expiresAt) > time();
    }

    private static function preserveCompletePaymentMethod(string $existing): string
    {
        return $existing === 'complete' ? 'complete' : 'pending';
    }

    private static function stripeSubscriptionIdFromObject(array $object): ?string
    {
        $direct = self::stringOrNull($object['subscription'] ?? null);
        if ($direct !== null) {
            return $direct;
        }
        $parent = is_array($object['parent'] ?? null) ? $object['parent'] : [];
        $details = is_array($parent['subscription_details'] ?? null) ? $parent['subscription_details'] : [];
        $fromParent = self::stringOrNull($details['subscription'] ?? null);
        if ($fromParent !== null) {
            return $fromParent;
        }
        return ($object['object'] ?? '') === 'subscription' ? self::stringOrNull($object['id'] ?? null) : null;
    }

    private static function dateTimeFromTimestamp($timestamp): ?string
    {
        $timestamp = (int) $timestamp;
        return $timestamp > 0 ? gmdate('Y-m-d H:i:s', $timestamp) : null;
    }

    private static function timestampFromUtc(string $timestamp): int
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', trim($timestamp), new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('A stored UTC billing date is invalid.');
        }
        return $parsed->getTimestamp();
    }

    private static function newOrExistingReference($newValue, $existingValue): ?string
    {
        return self::stringOrNull($newValue) ?? self::stringOrNull($existingValue);
    }

    private static function stringOrNull($value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function validPriceReference(string $value): bool
    {
        return preg_match('/^price_[A-Za-z0-9]+$/', $value) === 1;
    }

    private static function flattenParams(array $params, ?string $prefix = null): array
    {
        $flat = [];
        foreach ($params as $key => $value) {
            $name = $prefix === null ? (string) $key : $prefix . '[' . $key . ']';
            if (is_array($value)) {
                $flat += self::flattenParams($value, $name);
            } elseif (is_bool($value)) {
                $flat[$name] = $value ? 'true' : 'false';
            } elseif ($value !== null) {
                $flat[$name] = (string) $value;
            }
        }
        return $flat;
    }
}
