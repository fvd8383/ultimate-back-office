<?php

require_once __DIR__ . '/../../private/classes/Auth.php';
require_once __DIR__ . '/../../private/classes/EmailProvisioningFoundation.php';

Session::requireAuth('login.php');

$loadError = '';
$notice = '';
$errors = [];
$user = null;
$businesses = [];
$requests = [];
$assignments = [];

try {
    $user = Auth::currentUser();

    if ($user === null) {
        Session::logout();
        header('Location: login.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        EmailProvisioningFoundation::createCustomerRequest(
            (int) $user['id'],
            (int) ($_POST['business_id'] ?? 0),
            (string) ($_POST['mailbox_name'] ?? ''),
            (string) ($_POST['display_name'] ?? '')
        );
        $notice = 'Mailbox request saved.';
    }

    $businesses = EmailProvisioningFoundation::customerBusinessesForUser((int) $user['id']);
    $requests = EmailProvisioningFoundation::customerMailboxRequestsForUser((int) $user['id']);
    $assignments = EmailProvisioningFoundation::customerMailboxAssignmentsForUser((int) $user['id']);
} catch (InvalidArgumentException $exception) {
    $errors[] = $exception->getMessage();
    if ($user !== null) {
        $businesses = EmailProvisioningFoundation::customerBusinessesForUser((int) $user['id']);
        $requests = EmailProvisioningFoundation::customerMailboxRequestsForUser((int) $user['id']);
        $assignments = EmailProvisioningFoundation::customerMailboxAssignmentsForUser((int) $user['id']);
    }
} catch (Throwable $exception) {
    $loadError = 'Email information could not be loaded. Run the Sprint 8 migration and check the database setup.';
}

function accounts_email_count(array $business, string $key): int
{
    return (int) ($business[$key] ?? 0);
}

$pageTitle = 'Email - Ultimate Back Office';
$bodyClass = 'accounts-dashboard';
$layoutHomeHref = 'dashboard.php';
$layoutUserName = $user ? trim((string) $user['first_name'] . ' ' . (string) $user['last_name']) : '';
$layoutLogoutHref = 'logout.php';
require __DIR__ . '/../../private/views/header.php';
?>
<section class="dashboard-grid">
    <div class="dashboard-card dashboard-card--wide">
        <p class="eyebrow">Accounts</p>
        <h1>Email</h1>
        <p class="muted">Request business mailboxes and track manual setup status. No real mailbox, DNS, password, SMTP, IMAP, or provider provisioning occurs here.</p>
    </div>

    <div class="dashboard-card">
        <h2>Navigation</h2>
        <div class="button-row">
            <?= ui_button('Dashboard', 'dashboard.php', 'secondary') ?>
            <?= ui_button('Businesses', 'business.php', 'secondary') ?>
            <?= ui_button('Billing', 'billing.php', 'secondary') ?>
            <?= ui_button('Domains', 'domains.php', 'secondary') ?>
            <?= ui_button('Email', 'email.php', 'secondary') ?>
            <?= ui_button('Logout', 'logout.php') ?>
        </div>
    </div>
</section>

<?php if ($notice !== ''): ?>
    <?= ui_alert($notice, 'success') ?>
<?php endif; ?>
<?php foreach ($errors as $error): ?>
    <?= ui_alert($error, 'error') ?>
<?php endforeach; ?>

<?php if ($loadError !== ''): ?>
    <?= ui_alert($loadError, 'error') ?>
<?php else: ?>
    <section class="dashboard-card">
        <h2>Request Mailbox</h2>
        <?php if (count($businesses) === 0): ?>
            <p class="muted">Activate 24/7 Sales Partner and choose a domain before requesting business email.</p>
        <?php else: ?>
            <form method="post" action="email.php" class="form-stack">
                <div class="form-grid">
                    <label>Business
                        <select name="business_id" required>
                            <?php foreach ($businesses as $business): ?>
                                <option value="<?= e($business['id']) ?>">
                                    <?= e($business['business_name']) ?><?= ($business['domain_name'] ?? '') !== '' ? ' - ' . e($business['domain_name']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="form-help">The selected business domain is used for the mailbox request.</span>
                    </label>
                    <label>Mailbox Name
                        <input name="mailbox_name" required value="<?= e((string) ($_POST['mailbox_name'] ?? 'info')) ?>" placeholder="info">
                        <span class="form-help">Use only the part before the @ symbol.</span>
                    </label>
                    <label>Display Name
                        <input name="display_name" value="<?= e((string) ($_POST['display_name'] ?? '')) ?>" placeholder="Office">
                    </label>
                </div>
                <?= ui_button('Request mailbox') ?>
            </form>
        <?php endif; ?>
    </section>

    <section class="dashboard-card">
        <h2>Mailbox Summary</h2>
        <?php if (count($businesses) === 0): ?>
            <p class="muted">No 24/7 Sales Partner businesses found.</p>
        <?php else: ?>
            <div class="business-list">
                <?php foreach ($businesses as $business): ?>
                    <article class="business-list__item">
                        <div>
                            <h3><?= e($business['business_name']) ?></h3>
                            <p class="muted"><?= e($business['domain_name'] ?: 'No domain selected') ?></p>
                        </div>
                        <div class="summary-list billing-summary-list">
                            <div><dt>Included Allowance</dt><dd><?= e($business['included_mailbox_count']) ?></dd></div>
                            <div><dt>Additional Purchased</dt><dd><?= e($business['additional_mailbox_count']) ?></dd></div>
                            <div><dt>Requested</dt><dd><?= e(accounts_email_count($business, 'requested_mailbox_count')) ?></dd></div>
                            <div><dt>Pending Setup</dt><dd><?= e(accounts_email_count($business, 'pending_setup_mailbox_count')) ?></dd></div>
                            <div><dt>Active Mailboxes</dt><dd><?= e(accounts_email_count($business, 'active_mailbox_count')) ?></dd></div>
                            <div><dt>Suspended</dt><dd><?= e(accounts_email_count($business, 'suspended_mailbox_count')) ?></dd></div>
                            <div><dt>Cancelled</dt><dd><?= e(accounts_email_count($business, 'cancelled_mailbox_count')) ?></dd></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="dashboard-card">
        <h2>Requested Mailboxes</h2>
        <?php if (count($requests) === 0): ?>
            <p class="muted">No mailbox requests have been created yet.</p>
        <?php else: ?>
            <div class="business-list">
                <?php foreach ($requests as $request): ?>
                    <article class="business-list__item">
                        <div>
                            <h3><?= e($request['requested_email']) ?></h3>
                            <p class="muted"><?= e($request['business_name']) ?></p>
                        </div>
                        <div class="summary-list billing-summary-list">
                            <div><dt>Display Name</dt><dd><?= e($request['display_name'] ?: 'Not set') ?></dd></div>
                            <div><dt>Status</dt><dd><?= ui_badge(EmailProvisioningFoundation::statusLabel($request['status']), in_array((string) $request['status'], ['suspended', 'cancelled'], true) ? 'role' : 'status') ?></dd></div>
                            <div><dt>Request Date</dt><dd><?= e($request['created_at']) ?></dd></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="dashboard-card">
        <h2>Active Mailboxes</h2>
        <?php if (count($assignments) === 0): ?>
            <p class="muted">No active mailbox assignments have been created yet.</p>
        <?php else: ?>
            <div class="business-list">
                <?php foreach ($assignments as $assignment): ?>
                    <article class="business-list__item">
                        <div>
                            <h3><?= e($assignment['email_address']) ?></h3>
                            <p class="muted"><?= e($assignment['business_name']) ?></p>
                        </div>
                        <div class="summary-list billing-summary-list">
                            <div><dt>Display Name</dt><dd><?= e($assignment['display_name'] ?: 'Not set') ?></dd></div>
                            <div><dt>Status</dt><dd><?= ui_badge(EmailProvisioningFoundation::statusLabel($assignment['status']), in_array((string) $assignment['status'], ['suspended', 'cancelled'], true) ? 'role' : 'status') ?></dd></div>
                            <div><dt>Mailbox Type</dt><dd><?= e(EmailProvisioningFoundation::statusLabel($assignment['mailbox_type'])) ?></dd></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/../../private/views/footer.php'; ?>
