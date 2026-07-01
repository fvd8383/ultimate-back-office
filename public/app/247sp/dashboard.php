<?php

require_once __DIR__ . '/../../../private/classes/Auth.php';
require_once __DIR__ . '/../../../private/classes/BillingFoundation.php';
require_once __DIR__ . '/../../../private/classes/DomainAutomation.php';
require_once __DIR__ . '/../../../private/classes/TwentyFourSevenSalesPartner.php';
require_once __DIR__ . '/../../../private/classes/SiteGenerator.php';

try {
    $accountsBaseUrl = rtrim((string) Database::config('ACCOUNTS_BASE_URL'), '/');
} catch (Throwable $exception) {
    $accountsBaseUrl = '../../accounts';
}

Session::requireAuth($accountsBaseUrl . '/login.php');

$user = null;
$business = null;
$summary = [];
$website = null;
$billing = null;
$websiteApproval = ['approved' => false, 'status' => 'not_reviewed', 'created_at' => null];
$loadError = '';
$actionError = '';
$accessDenied = false;
$completed = isset($_GET['completed']);
$changesRequested = isset($_GET['changes_requested']);
$approvedForLaunch = isset($_GET['approved']);

try {
    $user = Auth::currentUser();

    if ($user === null) {
        Session::logout();
        header('Location: ' . $accountsBaseUrl . '/login.php');
        exit;
    }

    $requestedBusinessId = (int) ($_POST['business_id'] ?? $_GET['business_id'] ?? 0);
    $requestedBusinessId = $requestedBusinessId > 0 ? $requestedBusinessId : null;
    $business = TwentyFourSevenSalesPartner::businessForUser($requestedBusinessId, (int) $user['id']);

    if ($business !== null) {
        $businessId = (int) $business['id'];
        $accessDenied = !TwentyFourSevenSalesPartner::businessHasAccess($businessId);

        if (!$accessDenied && $_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['request_changes'])) {
                TwentyFourSevenSalesPartner::requestWebsiteChanges($businessId, (int) $user['id'], (string) ($_POST['change_request'] ?? ''));
                header('Location: dashboard.php?business_id=' . $businessId . '&changes_requested=1');
                exit;
            }

            if (isset($_POST['approve_launch'])) {
                $approvalWebsite = SiteGenerator::websiteForBusiness($businessId);
                if ($approvalWebsite === null || !in_array((string) ($approvalWebsite['status'] ?? ''), ['generated', 'published'], true)) {
                    throw new InvalidArgumentException('Review your website preview before approving launch.');
                }

                TwentyFourSevenSalesPartner::approveWebsiteLaunch($businessId, (int) $user['id']);
                header('Location: dashboard.php?business_id=' . $businessId . '&approved=1');
                exit;
            }
        }

        if (!$accessDenied) {
            $summary = TwentyFourSevenSalesPartner::dashboardSummary($businessId);
            $website = SiteGenerator::websiteForBusiness($businessId);
            $billing = BillingFoundation::subscriptionForBusiness($businessId);
            $websiteApproval = TwentyFourSevenSalesPartner::websiteLaunchApproval($businessId);

            if ($website !== null) {
                $summary['website_status'] = (string) $website['status'];
            }
        }
    }
} catch (InvalidArgumentException $exception) {
    $actionError = $exception->getMessage();

    if ($business !== null && !$accessDenied) {
        $summary = TwentyFourSevenSalesPartner::dashboardSummary((int) $business['id']);
        $website = SiteGenerator::websiteForBusiness((int) $business['id']);
        $billing = BillingFoundation::subscriptionForBusiness((int) $business['id']);
        $websiteApproval = TwentyFourSevenSalesPartner::websiteLaunchApproval((int) $business['id']);

        if ($website !== null) {
            $summary['website_status'] = (string) $website['status'];
        }
    }
} catch (Throwable $exception) {
    $loadError = '24/7 Sales Partner could not be loaded. Check the environment and database setup.';
}

function sp247_status_label(string $status): string
{
    $labels = [
        'not_started' => 'Not Started',
        'in_progress' => 'In Progress',
        'ready_for_build' => 'Ready For Preview',
        'generated' => 'Preview Ready',
        'published' => 'Published',
        'not_selected' => 'Not Selected',
        'pending' => 'Pending',
        'registered' => 'Registered',
        'requested' => 'Requested',
        'pending_purchase' => 'Pending Purchase',
        'transferred' => 'Transferred',
        'expired' => 'Expired',
        'cancelled' => 'Cancelled',
        'active' => 'Active',
        'complete' => 'Complete',
    ];

    return $labels[$status] ?? ucwords(str_replace('_', ' ', $status));
}

function sp247_business_profile_complete(?array $business): bool
{
    if ($business === null) {
        return false;
    }

    foreach (['business_name', 'email', 'phone', 'address_line_1', 'city', 'state', 'postal_code'] as $field) {
        if (trim((string) ($business[$field] ?? '')) === '') {
            return false;
        }
    }

    return true;
}

function sp247_subscription_paid(?array $billing): bool
{
    return $billing !== null && (string) ($billing['status'] ?? '') === 'active';
}

function sp247_build_launch_readiness(
    ?array $business,
    array $summary,
    ?array $website,
    ?array $billing,
    array $websiteApproval,
    string $accountsBaseUrl,
    int $businessIdForLinks
): array {
    $businessId = urlencode((string) $businessIdForLinks);
    $setupComplete = (string) ($summary['setup_status'] ?? '') === 'complete';
    $previewReady = $website !== null && in_array((string) ($website['status'] ?? ''), ['generated', 'published'], true);
    $domainReady = !in_array((string) ($summary['domain_status'] ?? 'not_selected'), ['', 'not_selected'], true);
    $emailReady = !in_array((string) ($summary['email_status'] ?? 'not_selected'), ['', 'not_selected'], true);
    $paymentComplete = sp247_subscription_paid($billing);
    $approvalDate = (string) ($websiteApproval['created_at'] ?? '');
    $generatedDate = (string) ($website['generated_at'] ?? '');
    $websiteApproved = !empty($websiteApproval['approved'])
        && ($generatedDate === '' || $approvalDate === '' || $approvalDate >= $generatedDate);
    $readyToLaunch = $setupComplete && $previewReady && $domainReady && $emailReady && $websiteApproved && $paymentComplete;

    $items = [
        [
            'label' => 'Business profile complete',
            'completed' => sp247_business_profile_complete($business),
            'detail' => sp247_business_profile_complete($business) ? 'Your business contact and service area details are saved.' : 'Add the core business details used for your website.',
            'action' => ['label' => 'Update profile', 'href' => $accountsBaseUrl . '/business.php?business_id=' . $businessId],
        ],
        [
            'label' => 'Website onboarding complete',
            'completed' => $setupComplete,
            'detail' => $setupComplete ? 'Your website setup answers have been submitted.' : 'Finish the website setup steps so your preview can be prepared.',
            'action' => ['label' => 'Continue onboarding', 'href' => 'onboarding.php?business_id=' . $businessId . '&step=' . urlencode((string) ($summary['current_step'] ?? 'business_information'))],
        ],
        [
            'label' => 'Website preview ready',
            'completed' => $previewReady,
            'detail' => $previewReady ? 'Your private website preview is ready to review.' : 'Your preview appears here after onboarding and website preparation.',
            'action' => ['label' => $setupComplete ? 'Review onboarding' : 'Finish onboarding', 'href' => $setupComplete ? 'review.php?business_id=' . $businessId : 'onboarding.php?business_id=' . $businessId],
        ],
        [
            'label' => 'Domain selected/requested',
            'completed' => $domainReady,
            'detail' => $domainReady ? 'Your domain request is saved and visible in your account.' : 'Choose the domain you want connected to this website.',
            'action' => ['label' => 'Choose domain', 'href' => 'onboarding.php?business_id=' . $businessId . '&step=domain_selection'],
        ],
        [
            'label' => 'Email selected/requested',
            'completed' => $emailReady,
            'detail' => $emailReady ? 'Your first mailbox request is saved.' : 'Choose the mailbox name you want for your business email.',
            'action' => ['label' => 'Choose email', 'href' => 'onboarding.php?business_id=' . $businessId . '&step=email_selection'],
        ],
        [
            'label' => 'Website approved',
            'completed' => $websiteApproved,
            'detail' => $websiteApproved ? 'Your approval has been saved.' : ($previewReady ? 'Approve the preview when it looks ready, or request changes below.' : 'Review and approve your preview once it is ready.'),
            'action' => ['label' => 'Preview website', 'href' => 'site-preview.php?business_id=' . $businessId],
        ],
        [
            'label' => 'Payment method added',
            'completed' => $paymentComplete,
            'detail' => $paymentComplete ? 'Your billing status is active.' : 'Complete payment setup after reviewing your website preview.',
            'action' => ['label' => 'View billing', 'href' => $accountsBaseUrl . '/billing.php'],
        ],
        [
            'label' => 'Ready to launch',
            'completed' => $readyToLaunch,
            'detail' => $readyToLaunch ? 'Your launch requirements are complete.' : 'Complete the remaining items above to prepare this website for launch.',
        ],
    ];

    $primaryAction = null;
    $supportingText = '';

    if ($previewReady && !$paymentComplete) {
        $primaryAction = ['label' => 'Complete Payment & Launch', 'href' => $accountsBaseUrl . '/billing.php'];
        $supportingText = 'Payment is requested only after your website preview is ready for approval.';
    } elseif ($previewReady && $paymentComplete && !$websiteApproved) {
        $primaryAction = [
            'label' => 'Approve & Launch Website',
            'href' => '',
            'attributes' => ['name' => 'approve_launch', 'value' => '1', 'form' => 'launch-readiness-action-form'],
        ];
        $supportingText = 'Approve the preview when it looks ready, or send a change request below.';
    } elseif ($readyToLaunch) {
        $supportingText = 'Your approval is saved and your launch requirements are complete.';
    }

    return [
        'items' => $items,
        'primary_action' => $primaryAction,
        'supporting_text' => $supportingText,
    ];
}

$businessIdForLinks = $business ? (int) $business['id'] : 0;
$sp247NavItems = [
    ['label' => 'Dashboard', 'href' => 'dashboard.php' . ($businessIdForLinks > 0 ? '?business_id=' . urlencode((string) $businessIdForLinks) : ''), 'current' => true],
    ['label' => 'Onboarding', 'href' => $businessIdForLinks > 0 ? 'onboarding.php?business_id=' . urlencode((string) $businessIdForLinks) : 'onboarding.php'],
    ['label' => 'Review', 'href' => $businessIdForLinks > 0 ? 'review.php?business_id=' . urlencode((string) $businessIdForLinks) : 'review.php'],
    ['label' => 'Preview', 'href' => $businessIdForLinks > 0 ? 'site-preview.php?business_id=' . urlencode((string) $businessIdForLinks) : 'site-preview.php'],
];
if ($businessIdForLinks > 0 && !$accessDenied) {
    array_splice($sp247NavItems, 4, 0, [[
        'label' => 'Website Manager',
        'href' => 'website-manager.php?business_id=' . urlencode((string) $businessIdForLinks),
    ]]);
}
$pageTitle = '24/7 Sales Partner - Ultimate Back Office';
$bodyClass = 'app-dashboard theme-247sp';
$layoutHomeHref = '../dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = $accountsBaseUrl . '/logout.php';
require __DIR__ . '/../../../private/views/header.php';
require __DIR__ . '/../../../private/views/account-navigation.php';
?>
<?php application_shell_begin('247sp', ['area' => 'app_247sp', 'user' => $user, 'business' => $business, 'secondary_nav' => $sp247NavItems]); ?>
        <section class="hero-panel product-hero product-hero--247sp">
            <img class="product-hero__logo" src="../assets/img/247sp-logo.svg" alt="24/7 Sales Partner">
            <p class="eyebrow">24/7 Sales Partner</p>
            <h1><?= $business ? e($business['business_name']) : '247SP onboarding' ?></h1>
            <p class="muted">Track onboarding, preview status, change requests, domain status, and email status.</p>
        </section>

        <?php if ($completed): ?>
            <?= ui_alert('247SP onboarding is complete. The team can prepare your website preview.', 'success') ?>
        <?php endif; ?>
        <?php if ($changesRequested): ?>
            <?= ui_alert('Change request sent. The 247SP team will review it.', 'success') ?>
        <?php endif; ?>
        <?php if ($approvedForLaunch): ?>
            <?= ui_alert('Website approval saved. Your launch checklist has been updated.', 'success') ?>
        <?php endif; ?>
        <?php if ($actionError !== ''): ?>
            <?= ui_alert($actionError, 'error') ?>
        <?php endif; ?>
        <?php if ($billing !== null && in_array((string) $billing['status'], ['pending_payment', 'past_due'], true)): ?>
            <?= ui_alert('Billing status is ' . sp247_status_label((string) $billing['status']) . '. Visit Billing to review your current plan and payment status.', 'warning') ?>
        <?php endif; ?>

        <?php if ($loadError !== ''): ?>
            <?= ui_alert($loadError, 'error') ?>
        <?php elseif ($business === null): ?>
            <section class="empty-state">
                <h2>Business setup required</h2>
                <p>Create or select a business before starting 24/7 Sales Partner onboarding.</p>
            </section>
        <?php elseif ($accessDenied): ?>
            <section class="empty-state">
                <h2>Access denied</h2>
                <p>24/7 Sales Partner is not active for this business.</p>
                <div class="button-row">
                    <?= ui_button('Activate modules', $accountsBaseUrl . '/business-create.php?step=modules&business_id=' . urlencode((string) $businessIdForLinks), 'secondary') ?>
                </div>
            </section>
        <?php else: ?>
            <section class="metrics-grid" aria-label="247SP status summary">
                <a class="metric-card" href="<?= e($website !== null ? 'site-preview.php?business_id=' . urlencode((string) $businessIdForLinks) : 'onboarding.php?business_id=' . urlencode((string) $businessIdForLinks) . '&step=' . urlencode((string) ($summary['current_step'] ?? 'business_information'))) ?>">
                    <span>Website Status</span>
                    <strong><?= e(sp247_status_label((string) $summary['website_status'])) ?></strong>
                </a>
                <a class="metric-card" href="<?= e($accountsBaseUrl . '/domains.php') ?>">
                    <span>Domain Status</span>
                    <strong><?= e(sp247_status_label((string) $summary['domain_status'])) ?></strong>
                </a>
                <a class="metric-card" href="<?= e($accountsBaseUrl . '/email.php') ?>">
                    <span>Email Status</span>
                    <strong><?= e(sp247_status_label((string) $summary['email_status'])) ?></strong>
                </a>
                <a class="metric-card" href="<?= e($accountsBaseUrl . '/billing.php') ?>">
                    <span>Billing Status</span>
                    <strong><?= e($billing ? sp247_status_label((string) $billing['status']) : 'No Subscription') ?></strong>
                </a>
            </section>

            <?php
                $launchReadiness = sp247_build_launch_readiness($business, $summary, $website, $billing, $websiteApproval, $accountsBaseUrl, $businessIdForLinks);
            ?>
            <form id="launch-readiness-action-form" method="post" action="dashboard.php">
                <input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>">
            </form>
            <?= ui_launch_readiness(
                'Launch Readiness',
                'Use this checklist to see what is complete and what still needs attention before your 24/7 Sales Partner website can launch.',
                $launchReadiness['items'],
                [
                    'module_label' => '24/7 Sales Partner',
                    'primary_action' => $launchReadiness['primary_action'],
                    'supporting_text' => $launchReadiness['supporting_text'],
                ]
            ) ?>

            <section class="business-switcher">
                <h2>Onboarding Progress</h2>
                <div class="summary-list">
                    <div><dt>Setup Status</dt><dd><?= e(sp247_status_label((string) $summary['setup_status'])) ?></dd></div>
                    <div><dt>Current Step</dt><dd><?= e(sp247_status_label((string) $summary['current_step'])) ?></dd></div>
                    <div><dt>Completed At</dt><dd><?= e($summary['completed_at'] ?: 'Not complete') ?></dd></div>
                    <div><dt>Template</dt><dd><?= e($website['template_name'] ?? 'Not assigned') ?></dd></div>
                    <div><dt>Preview Updated</dt><dd><?= e($website['generated_at'] ?? 'Preview pending') ?></dd></div>
                    <div><dt>Billing Status</dt><dd><?= e($billing ? sp247_status_label((string) $billing['status']) : 'No Subscription') ?></dd></div>
                </div>
                <div class="button-row">
                    <?= ui_button($summary['setup_status'] === 'complete' ? 'Review onboarding' : 'Continue onboarding', $summary['setup_status'] === 'complete'
                        ? 'review.php?business_id=' . urlencode((string) $businessIdForLinks)
                        : 'onboarding.php?business_id=' . urlencode((string) $businessIdForLinks) . '&step=' . urlencode((string) $summary['current_step'])) ?>
                </div>
            </section>

            <section class="business-switcher">
                <h2>Website Preview</h2>
                <?php if ($website === null): ?>
                    <p class="muted">Complete onboarding so the 247SP team can prepare your private website preview.</p>
                    <div class="button-row">
                        <?= ui_button($summary['setup_status'] === 'complete' ? 'Review onboarding' : 'Complete onboarding', $summary['setup_status'] === 'complete'
                            ? 'review.php?business_id=' . urlencode((string) $businessIdForLinks)
                            : 'onboarding.php?business_id=' . urlencode((string) $businessIdForLinks) . '&step=' . urlencode((string) $summary['current_step'])) ?>
                    </div>
                <?php else: ?>
                    <p class="muted">Review your private website preview, adjust customer-editable content, or request changes from the 247SP team.</p>
                    <div class="button-row">
                        <?= ui_button('Preview Website', 'site-preview.php?business_id=' . urlencode((string) $businessIdForLinks), 'primary') ?>
                        <?= ui_button('Website Manager', 'website-manager.php?business_id=' . urlencode((string) $businessIdForLinks), 'secondary') ?>
                    </div>
                    <form method="post" action="dashboard.php" class="form-stack">
                        <input type="hidden" name="business_id" value="<?= e($businessIdForLinks) ?>">
                        <label>Request Changes
                            <textarea name="change_request" rows="4" maxlength="2000" placeholder="Tell us what you would like changed on the website preview."></textarea>
                        </label>
                        <?= ui_button('Send change request', '', 'secondary', ['name' => 'request_changes', 'value' => '1']) ?>
                    </form>
                    <p class="muted">If the preview looks ready, contact your 247SP team member to approve the website.</p>
                <?php endif; ?>
            </section>

            <section class="business-switcher">
                <h2>Domain Workflow</h2>
                <p class="muted">Domain requests are reviewed by the 247SP team. This dashboard shows status while availability and setup details are confirmed.</p>
                <?= ui_button('View Account Domains', $accountsBaseUrl . '/domains.php', 'secondary') ?>
            </section>

            <section class="business-switcher">
                <h2>Email Workflow</h2>
                <p class="muted">Mailbox requests are reviewed by the 247SP team. This dashboard shows status while setup details are confirmed.</p>
                <?= ui_button('View Account Email', $accountsBaseUrl . '/email.php', 'secondary') ?>
            </section>
        <?php endif; ?>
<?php application_shell_end(); ?>
<?php require __DIR__ . '/../../../private/views/footer.php'; ?>
