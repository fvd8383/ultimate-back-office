<?php

require_once __DIR__ . '/BillingFoundation.php';
require_once __DIR__ . '/Database.php';

final class StripeBilling
{
    private const API_BASE = 'https://api.stripe.com/v1';
    private const WEBHOOK_TOLERANCE_SECONDS = 300;

    public static function checkoutConfigurationIssues(?array $subscription = null): array
    {
        $required = [
            'STRIPE_SECRET_KEY',
            'STRIPE_247SP_PRICE_ID',
            'STRIPE_SUCCESS_URL',
            'STRIPE_CANCEL_URL',
        ];

        $issues = [];
        foreach ($required as $key) {
            if (trim((string) Database::config($key, '')) === '') {
                $issues[] = $key;
            }
        }

        if ($subscription !== null
            && (float) ($subscription['setup_fee'] ?? 0) > 0
            && trim((string) Database::config('STRIPE_247SP_SETUP_FEE_PRICE_ID', '')) === ''
        ) {
            $issues[] = 'STRIPE_247SP_SETUP_FEE_PRICE_ID';
        }

        return $issues;
    }

    public static function createCheckoutSession(array $user, array $business, array $subscription): array
    {
        $issues = self::checkoutConfigurationIssues($subscription);
        if (count($issues) > 0) {
            throw new RuntimeException('Stripe checkout is missing required configuration: ' . implode(', ', $issues));
        }

        $subscriptionId = (int) ($subscription['id'] ?? $subscription['subscription_id'] ?? 0);
        if ($subscriptionId <= 0) {
            throw new InvalidArgumentException('Subscription not found.');
        }

        $stripeCustomerId = trim((string) ($subscription['stripe_customer_id'] ?? ''));
        if ($stripeCustomerId === '') {
            $customer = self::createStripeCustomer($user, $business);
            $stripeCustomerId = (string) ($customer['id'] ?? '');
            if ($stripeCustomerId === '') {
                throw new RuntimeException('Stripe customer could not be created.');
            }
            BillingFoundation::updateSubscriptionBillingState($subscriptionId, [
                'stripe_customer_id' => $stripeCustomerId,
                'payment_method_status' => 'pending',
            ]);
        }

        $lineItems = [
            [
                'price' => trim((string) Database::config('STRIPE_247SP_PRICE_ID', '')),
                'quantity' => 1,
            ],
        ];

        $setupPriceId = trim((string) Database::config('STRIPE_247SP_SETUP_FEE_PRICE_ID', ''));
        if ($setupPriceId !== '') {
            $lineItems[] = [
                'price' => $setupPriceId,
                'quantity' => 1,
            ];
        }

        $businessId = (int) $business['id'];
        $metadata = [
            'business_id' => (string) $businessId,
            'subscription_id' => (string) $subscriptionId,
            'product_key' => '247sp',
        ];

        $session = self::apiRequest('POST', '/checkout/sessions', [
            'mode' => 'subscription',
            'customer' => $stripeCustomerId,
            'client_reference_id' => (string) $businessId,
            'success_url' => self::returnUrl('STRIPE_SUCCESS_URL', $businessId, true),
            'cancel_url' => self::returnUrl('STRIPE_CANCEL_URL', $businessId, false),
            'line_items' => $lineItems,
            'metadata' => $metadata,
            'subscription_data' => [
                'metadata' => $metadata,
            ],
        ]);

        $checkoutSessionId = (string) ($session['id'] ?? '');
        if ($checkoutSessionId === '' || trim((string) ($session['url'] ?? '')) === '') {
            throw new RuntimeException('Stripe checkout session could not be created.');
        }

        BillingFoundation::updateSubscriptionBillingState($subscriptionId, [
            'stripe_customer_id' => $stripeCustomerId,
            'stripe_checkout_session_id' => $checkoutSessionId,
            'payment_method_status' => 'pending',
            'status' => 'pending_payment',
        ]);

        return $session;
    }

    public static function handleWebhook(string $payload, string $signatureHeader): array
    {
        $event = self::verifyWebhookEvent($payload, $signatureHeader);
        $eventId = (string) ($event['id'] ?? '');
        $eventType = (string) ($event['type'] ?? '');

        if ($eventId === '' || $eventType === '') {
            throw new RuntimeException('Stripe webhook event is missing required identifiers.');
        }

        if (BillingFoundation::stripeWebhookEventExists($eventId)) {
            return ['status' => 'already_processed', 'event_id' => $eventId, 'event_type' => $eventType];
        }

        BillingFoundation::recordStripeWebhookEvent($eventId, $eventType, $payload);

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
                default:
                    break;
            }

            BillingFoundation::markStripeWebhookEvent($eventId, 'processed');
            return ['status' => 'processed', 'event_id' => $eventId, 'event_type' => $eventType];
        } catch (Throwable $exception) {
            BillingFoundation::markStripeWebhookEvent($eventId, 'failed', $exception->getMessage());
            throw $exception;
        }
    }

    private static function handleCheckoutSessionCompleted(array $session): void
    {
        $subscription = self::subscriptionFromMetadataOrReferences($session);
        if ($subscription === null) {
            throw new RuntimeException('Local subscription was not found for Stripe checkout session.');
        }

        $paymentStatus = (string) ($session['payment_status'] ?? '');
        $stripeSubscriptionId = self::stringOrNull($session['subscription'] ?? null);
        $stripeCustomerId = self::stringOrNull($session['customer'] ?? null);

        BillingFoundation::updateSubscriptionBillingState((int) $subscription['id'], [
            'status' => $paymentStatus === 'paid' ? 'active' : 'pending_payment',
            'stripe_customer_id' => $stripeCustomerId,
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_checkout_session_id' => self::stringOrNull($session['id'] ?? null),
            'payment_method_status' => $paymentStatus === 'paid' ? 'complete' : 'pending',
        ]);
    }

    private static function handleSubscriptionEvent(array $stripeSubscription): void
    {
        $subscription = self::subscriptionFromMetadataOrReferences($stripeSubscription);
        if ($subscription === null) {
            throw new RuntimeException('Local subscription was not found for Stripe subscription event.');
        }

        $localStatus = self::localStatusForStripeSubscription((string) ($stripeSubscription['status'] ?? ''));

        BillingFoundation::updateSubscriptionBillingState((int) $subscription['id'], [
            'status' => $localStatus,
            'stripe_customer_id' => self::stringOrNull($stripeSubscription['customer'] ?? null),
            'stripe_subscription_id' => self::stringOrNull($stripeSubscription['id'] ?? null),
            'stripe_latest_invoice_id' => self::stringOrNull($stripeSubscription['latest_invoice'] ?? null),
            'payment_method_status' => self::paymentMethodStatusForLocalStatus($localStatus),
            'current_period_start' => self::dateTimeFromTimestamp($stripeSubscription['current_period_start'] ?? null),
            'current_period_end' => self::dateTimeFromTimestamp($stripeSubscription['current_period_end'] ?? null),
            'cancel_at_period_end' => !empty($stripeSubscription['cancel_at_period_end']) ? 1 : 0,
        ]);
    }

    private static function handleInvoiceEvent(array $invoice, bool $paid, string $eventId): void
    {
        $subscription = self::subscriptionFromMetadataOrReferences($invoice);
        if ($subscription === null) {
            throw new RuntimeException('Local subscription was not found for Stripe invoice event.');
        }

        $amount = $paid
            ? ((float) ($invoice['amount_paid'] ?? 0) / 100)
            : ((float) ($invoice['amount_due'] ?? 0) / 100);
        $stripeInvoiceId = self::stringOrNull($invoice['id'] ?? null);
        $stripePaymentIntentId = self::stringOrNull($invoice['payment_intent'] ?? null);
        $invoiceUrl = self::stringOrNull($invoice['hosted_invoice_url'] ?? null);

        BillingFoundation::recordStripePayment((int) $subscription['id'], [
            'payment_type' => 'stripe_invoice',
            'amount' => $amount,
            'status' => $paid ? 'paid' : 'failed',
            'transaction_reference' => $stripeInvoiceId,
            'stripe_invoice_id' => $stripeInvoiceId,
            'stripe_payment_intent_id' => $stripePaymentIntentId,
            'stripe_event_id' => $eventId,
            'invoice_url' => $invoiceUrl,
        ]);

        BillingFoundation::updateSubscriptionBillingState((int) $subscription['id'], [
            'status' => $paid ? 'active' : 'past_due',
            'stripe_customer_id' => self::stringOrNull($invoice['customer'] ?? null),
            'stripe_subscription_id' => self::stripeSubscriptionIdFromObject($invoice),
            'stripe_latest_invoice_id' => $stripeInvoiceId,
            'payment_method_status' => $paid ? 'complete' : 'failed',
        ]);
    }

    private static function subscriptionFromMetadataOrReferences(array $object): ?array
    {
        $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];
        $subscriptionId = (int) ($metadata['subscription_id'] ?? 0);
        if ($subscriptionId > 0) {
            $subscription = BillingFoundation::adminSubscription($subscriptionId);
            if ($subscription !== null) {
                return $subscription;
            }
        }

        $stripeSubscriptionId = self::stripeSubscriptionIdFromObject($object);
        if ($stripeSubscriptionId !== null) {
            $subscription = BillingFoundation::subscriptionByStripeSubscriptionId($stripeSubscriptionId);
            if ($subscription !== null) {
                return $subscription;
            }
        }

        $checkoutSessionId = self::stringOrNull($object['id'] ?? null);
        if (($object['object'] ?? '') === 'checkout.session' && $checkoutSessionId !== null) {
            $subscription = BillingFoundation::subscriptionByStripeCheckoutSessionId($checkoutSessionId);
            if ($subscription !== null) {
                return $subscription;
            }
        }

        $stripeCustomerId = self::stringOrNull($object['customer'] ?? null);
        if ($stripeCustomerId !== null) {
            return BillingFoundation::subscriptionByStripeCustomerId($stripeCustomerId, '247sp');
        }

        return null;
    }

    private static function createStripeCustomer(array $user, array $business): array
    {
        $email = trim((string) ($user['email'] ?? $business['email'] ?? ''));
        $name = trim((string) ($business['business_name'] ?? ''));

        return self::apiRequest('POST', '/customers', [
            'email' => $email,
            'name' => $name,
            'metadata' => [
                'business_id' => (string) ($business['id'] ?? ''),
                'ubo_user_id' => (string) ($user['id'] ?? ''),
            ],
        ]);
    }

    private static function apiRequest(string $method, string $path, array $params = []): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Stripe checkout requires the PHP cURL extension.');
        }

        $secretKey = trim((string) Database::config('STRIPE_SECRET_KEY', ''));
        if ($secretKey === '') {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        $curl = curl_init(self::API_BASE . $path);
        if ($curl === false) {
            throw new RuntimeException('Stripe request could not be initialized.');
        }

        $headers = ['Content-Type: application/x-www-form-urlencoded'];
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERPWD, $secretKey . ':');
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        if (strtoupper($method) === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query(self::flattenParams($params), '', '&'));
        }

        $response = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            throw new RuntimeException('Stripe request failed: ' . $error);
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Stripe returned an invalid response.');
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = (string) ($decoded['error']['message'] ?? 'Stripe request failed.');
            throw new RuntimeException($message);
        }

        return $decoded;
    }

    private static function verifyWebhookEvent(string $payload, string $signatureHeader): array
    {
        $webhookSecret = trim((string) Database::config('STRIPE_WEBHOOK_SECRET', ''));
        if ($webhookSecret === '') {
            throw new RuntimeException('Stripe webhook secret is not configured.');
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || count($signatures) === 0) {
            throw new RuntimeException('Stripe webhook signature header is invalid.');
        }

        if (abs(time() - $timestamp) > self::WEBHOOK_TOLERANCE_SECONDS) {
            throw new RuntimeException('Stripe webhook signature timestamp is outside the allowed tolerance.');
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $webhookSecret);
        $valid = false;
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            throw new RuntimeException('Stripe webhook signature verification failed.');
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) {
            throw new RuntimeException('Stripe webhook payload is invalid JSON.');
        }

        return $event;
    }

    private static function returnUrl(string $configKey, int $businessId, bool $includeSession): string
    {
        $url = trim((string) Database::config($configKey, ''));
        if ($url === '') {
            throw new RuntimeException($configKey . ' is not configured.');
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
        switch ($stripeStatus) {
            case 'active':
                return 'active';
            case 'trialing':
                return 'trial';
            case 'past_due':
            case 'unpaid':
                return 'past_due';
            case 'canceled':
                return 'cancelled';
            case 'incomplete':
            case 'incomplete_expired':
            default:
                return 'pending_payment';
        }
    }

    private static function paymentMethodStatusForLocalStatus(string $localStatus): string
    {
        switch ($localStatus) {
            case 'active':
                return 'complete';
            case 'past_due':
                return 'failed';
            case 'cancelled':
                return 'cancelled';
            case 'pending_payment':
                return 'pending';
            default:
                return 'not_on_file';
        }
    }

    private static function stripeSubscriptionIdFromObject(array $object): ?string
    {
        $direct = self::stringOrNull($object['subscription'] ?? null);
        if ($direct !== null) {
            return $direct;
        }

        $parent = is_array($object['parent'] ?? null) ? $object['parent'] : [];
        $subscriptionDetails = is_array($parent['subscription_details'] ?? null) ? $parent['subscription_details'] : [];
        $fromParent = self::stringOrNull($subscriptionDetails['subscription'] ?? null);
        if ($fromParent !== null) {
            return $fromParent;
        }

        if (($object['object'] ?? '') === 'subscription') {
            return self::stringOrNull($object['id'] ?? null);
        }

        return null;
    }

    private static function dateTimeFromTimestamp($timestamp): ?string
    {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private static function stringOrNull($value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function flattenParams(array $params, ?string $prefix = null): array
    {
        $flat = [];
        foreach ($params as $key => $value) {
            $name = $prefix === null ? (string) $key : $prefix . '[' . $key . ']';

            if (is_array($value)) {
                $flat += self::flattenParams($value, $name);
                continue;
            }

            if (is_bool($value)) {
                $flat[$name] = $value ? 'true' : 'false';
                continue;
            }

            if ($value !== null) {
                $flat[$name] = (string) $value;
            }
        }

        return $flat;
    }
}
