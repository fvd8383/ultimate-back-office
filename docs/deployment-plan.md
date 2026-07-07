# Deployment Plan

## Purpose

This document defines how Ultimate Back Office code is deployed to staging and production.

It exists to keep deployment predictable, prevent accidental production changes, and document the server/web root structure.

---

# Environments

## Local Development

Used for:

* Editing files
* Committing changes
* Creating sprint specs
* Reviewing code

Local development does not currently run the full PHP/MySQL stack.

Runtime validation happens on staging.

---

## Staging

Used for:

* Running PHP lint checks
* Running database migrations
* Testing authentication
* Testing dashboards
* Testing onboarding flows
* Testing generated website previews
* Verifying user permissions
* Verifying UI behavior

Staging is the primary runtime validation environment.

---

## Production

Production should only receive code after staging validation is complete.

Production is not used for experimental testing.

---

# Git Branching

## main

`main` must represent code that has passed staging validation or is ready for staging deployment.

Do not commit directly to `main` unless adding documentation or approved sprint specs.

---

## Sprint Branches

Each sprint should use its own branch.

Examples:

* sprint-3-247sp-onboarding
* sprint-4-site-generator
* sprint-5-admin-portal

All sprint branches should open a pull request into `main`.

---

## Follow-Up Fix Branches

Small fixes after sprint deployment should use descriptive branches.

Examples:

* fix-sprint-4-preview-layout
* fix-sprint-5-admin-routing

---

# Pull Request Flow

1. Codex creates sprint branch.
2. Codex opens draft PR into `main`.
3. PR is reviewed.
4. PR is merged into `main`.
5. Staging pulls latest `main`.
6. PHP lint is run on staging.
7. Database migration is run on staging if needed.
8. Feature is manually tested on staging.
9. Follow-up PRs are created if needed.
10. Sprint is tagged complete/verified after validation.

---

# Staging Deployment Commands

Run on staging server:

```bash
cd /var/www/ubo-repo
bash scripts/deploy-staging.sh
```

The script checks out `main`, pulls with `--ff-only`, runs PHP lint, and reloads `apache2` only after lint passes.

Manual equivalent:

```bash
cd /var/www/ubo-repo
git checkout main
git pull --ff-only origin main
find private public shared -name "*.php" -print0 | xargs -0 -n1 php -l
systemctl reload apache2
```

If a migration exists for the sprint, run it manually.

Example:

```bash
mysql \
-h ubo-stage-mysql-do-user-18803129-0.g.db.ondigitalocean.com \
-P 25060 \
-u ubo_stage_user \
-p \
--ssl-mode=REQUIRED \
ubo_staging < database/migrations/005_admin_portal.sql
```

For Sprint 8.6 Milestone 3, run:

```bash
mysql \
-h ubo-stage-mysql-do-user-18803129-0.g.db.ondigitalocean.com \
-P 25060 \
-u ubo_stage_user \
-p \
--ssl-mode=REQUIRED \
ubo_staging < database/migrations/016_stripe_billing_integration.sql
```

Migration 016 is order-independent from migration 015. Migration 015 only repairs a legacy website integrations table name; migration 016 only extends billing tables and creates Stripe webhook event storage. If migration 016 was accidentally run before migration 015 on staging, do not rerun migration 016. Run migration 015 next:

```bash
mysql \
-h ubo-stage-mysql-do-user-18803129-0.g.db.ondigitalocean.com \
-P 25060 \
-u ubo_stage_user \
-p \
--ssl-mode=REQUIRED \
ubo_staging < database/migrations/015_rename_legacy_website_integrations.sql
```

Then verify the final schema state:

```sql
SELECT table_name, table_rows
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN (
    'website_integrations',
    '247sp_website_integrations',
    'subscriptions',
    'payments',
    'stripe_webhook_events'
  )
ORDER BY table_name;

SELECT table_name, column_name
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND (
    (table_name = 'website_integrations' AND column_name IN (
      'ga_measurement_id',
      'google_search_console_property',
      'google_tag_manager_id',
      'microsoft_clarity_id',
      'meta_pixel_id',
      'google_business_profile_url'
    ))
    OR (table_name = 'subscriptions' AND column_name IN (
      'stripe_customer_id',
      'stripe_subscription_id',
      'stripe_checkout_session_id',
      'stripe_latest_invoice_id',
      'payment_method_status',
      'current_period_start',
      'current_period_end',
      'cancel_at_period_end',
      'updated_at'
    ))
    OR (table_name = 'payments' AND column_name IN (
      'stripe_invoice_id',
      'stripe_payment_intent_id',
      'stripe_checkout_session_id',
      'stripe_event_id',
      'invoice_url',
      'updated_at'
    ))
  )
ORDER BY table_name, column_name;

SELECT table_name, index_name, column_name
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND (
    (table_name = 'website_integrations' AND index_name = 'uq_website_integrations_business')
    OR (table_name = 'subscriptions' AND index_name IN (
      'uq_subscriptions_stripe_subscription',
      'idx_subscriptions_stripe_customer',
      'idx_subscriptions_stripe_checkout_session',
      'idx_subscriptions_payment_method_status'
    ))
    OR (table_name = 'payments' AND index_name IN (
      'uq_payments_stripe_invoice',
      'idx_payments_stripe_payment_intent',
      'idx_payments_stripe_event'
    ))
    OR (table_name = 'stripe_webhook_events' AND index_name = 'uq_stripe_webhook_events_event')
  )
ORDER BY table_name, index_name, seq_in_index;
```

Expected state: `website_integrations`, `subscriptions`, `payments`, and `stripe_webhook_events` exist with the listed columns and indexes. `247sp_website_integrations` should not exist after a clean repair. If both `website_integrations` and `247sp_website_integrations` exist, pause and review table row counts before deleting or merging anything.

Then configure the Stripe webhook endpoint in Stripe:

```text
https://staging-accounts.ultimatebackoffice.com/stripe-webhook.php
```

The repository also contains `public/webhooks/stripe.php` for a future standalone webhook mapping, but the current staging accounts document root can use `/stripe-webhook.php`.

For Sprint 8.6 Milestone 4, migration 017 was originally the Domain Services migration:

```bash
mysql \
-h ubo-stage-mysql-do-user-18803129-0.g.db.ondigitalocean.com \
-P 25060 \
-u ubo_stage_user \
-p \
--ssl-mode=REQUIRED \
ubo_staging < database/migrations/017_domain_services_automation.sql
```

If staging already contains `domain_requests.request_type`, migration 017 fails at line 1 with:

```text
ERROR 1060 (42S21): Duplicate column name 'request_type'
```

The duplicate is on `domain_requests`. `website_domains` does not use `request_type`. In the repository migration history, migration 009 creates the baseline domain tables and migration 017 adds the Domain Services columns/indexes, so this error indicates staging already had at least one 017-era column before 017 was run.

In that case, do not rerun 017. Run the repair/completion migration 019:

```bash
mysql \
-h ubo-stage-mysql-do-user-18803129-0.g.db.ondigitalocean.com \
-P 25060 \
-u ubo_stage_user \
-p \
--ssl-mode=REQUIRED \
ubo_staging < database/migrations/019_repair_domain_services_schema.sql
```

Then run migration 018 if it has not already been applied:

```bash
mysql \
-h ubo-stage-mysql-do-user-18803129-0.g.db.ondigitalocean.com \
-P 25060 \
-u ubo_stage_user \
-p \
--ssl-mode=REQUIRED \
ubo_staging < database/migrations/018_247sp_service_area_radius.sql
```

Then configure domain environment values in `private/config/env.php`:

```text
NAMECHEAP_API_USER
NAMECHEAP_API_KEY
NAMECHEAP_USERNAME
NAMECHEAP_CLIENT_IP
NAMECHEAP_SANDBOX
DOMAIN_DEFAULT_REGISTRAR
DOMAIN_TARGET_IPV4
DOMAIN_TARGET_IPV6
DOMAIN_WWW_CNAME
DOMAIN_TXT_VERIFICATION_NAME
DOMAIN_TXT_VERIFICATION_VALUE
DOMAIN_MAIL_MX_HOST
```

Use Namecheap sandbox credentials for staging. `NAMECHEAP_CLIENT_IP` must be the whitelisted IPv4 address configured in Namecheap API access. Keep `DOMAIN_DEFAULT_REGISTRAR=namecheap` until another `RegistrarInterface` implementation is added.

Verify the domain schema after migration:

```sql
SELECT table_name
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN ('domain_requests', 'domain_assignments', 'domain_dns_records', 'domain_events')
ORDER BY table_name;

SELECT table_name, column_name
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND (
    (table_name = 'domain_requests' AND column_name IN (
      'request_type',
      'registrar_domain_id',
      'registrar_order_id',
      'registrar_transaction_id',
      'registrar_response_json',
      'dns_status',
      'dns_verified_at',
      'ssl_status',
      'ssl_updated_at',
      'next_action',
      'last_error',
      'last_checked_at'
    ))
    OR (table_name = 'domain_assignments' AND column_name IN (
      'registrar',
      'registrar_domain_id',
      'ownership_type',
      'auto_renew',
      'expiration_date',
      'ssl_status'
    ))
    OR table_name IN ('domain_dns_records', 'domain_events')
  )
ORDER BY table_name, column_name;

SELECT table_name, index_name, column_name
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND (
    (table_name = 'domain_requests' AND index_name IN (
      'idx_domain_requests_request_type',
      'idx_domain_requests_dns_status',
      'idx_domain_requests_ssl_status'
    ))
    OR (table_name = 'domain_assignments' AND index_name IN (
      'idx_domain_assignments_registrar',
      'idx_domain_assignments_ownership',
      'idx_domain_assignments_ssl_status'
    ))
    OR (table_name = 'domain_dns_records' AND index_name IN (
      'uq_domain_dns_record',
      'idx_domain_dns_records_business',
      'idx_domain_dns_records_request',
      'idx_domain_dns_records_assignment',
      'idx_domain_dns_records_status'
    ))
    OR (table_name = 'domain_events' AND index_name IN (
      'idx_domain_events_business',
      'idx_domain_events_request',
      'idx_domain_events_assignment',
      'idx_domain_events_type',
      'idx_domain_events_status'
    ))
  )
ORDER BY table_name, index_name, seq_in_index;
```

Expected state after 019: `domain_requests.request_type` exists on `domain_requests`, not `website_domains`; all Domain Services columns listed above exist; `domain_dns_records` and `domain_events` exist; and the listed indexes exist. Migration 019 is additive and skips columns/indexes/tables that already exist.

---

# Web Root Structure

## Accounts Subdomain

URL:

```text
staging-accounts.ultimatebackoffice.com
```

Document root:

```text
public/accounts
```

Accounts pages belong here.

Examples:

```text
public/accounts/login.php
public/accounts/dashboard.php
public/accounts/business.php
public/accounts/business-create.php
```

---

## App Subdomain

URL:

```text
staging-app.ultimatebackoffice.com
```

Document root:

```text
public/app
```

App pages belong here.

Examples:

```text
public/app/dashboard.php
public/app/247sp/dashboard.php
public/app/247sp/onboarding.php
public/app/247sp/site-preview.php
public/app/admin/dashboard.php
```

---

# Important Routing Rules

Because Apache document roots point directly to subfolders:

Do not place browser-accessible app routes in:

```text
public/
```

unless there is a configured web root for that folder.

Do not place admin routes in:

```text
public/admin
```

Admin routes must live under:

```text
public/app/admin
```

Do not place shared CSS only in:

```text
shared/ui
```

Browser-accessible CSS must exist under the active document root.

Current public CSS locations:

```text
public/accounts/assets/css/design-system.css
public/app/assets/css/design-system.css
```

Current public branding asset locations:

```text
public/accounts/assets/img/ubo-logo.svg
public/accounts/assets/img/favicon.svg
public/accounts/assets/img/247sp-logo.svg
public/accounts/assets/img/emd-logo.svg
public/accounts/assets/img/ssp-logo.svg
public/accounts/assets/img/tuhwd-logo.svg
public/app/assets/img/ubo-logo.svg
public/app/assets/img/favicon.svg
public/app/assets/img/247sp-logo.svg
public/app/assets/img/emd-logo.svg
public/app/assets/img/ssp-logo.svg
public/app/assets/img/tuhwd-logo.svg
```

The shared layout loads:

```text
/assets/img/ubo-logo.svg
/assets/img/favicon.svg
```

Those paths resolve inside whichever document root is serving the current subdomain. Do not move these platform branding assets to DigitalOcean Spaces unless a supported public asset pipeline is added.

---

# Configuration Files

Environment-specific config lives in:

```text
private/config/env.php
```

This file is not committed to Git.

Template/example config lives in:

```text
private/config/env.example.php
```

Do not commit real credentials.

Stripe billing configuration for 24/7 Sales Partner customer payments also lives in `private/config/env.php`:

```text
STRIPE_SECRET_KEY
STRIPE_PUBLISHABLE_KEY
STRIPE_WEBHOOK_SECRET
STRIPE_247SP_PRICE_ID
STRIPE_247SP_SETUP_FEE_PRICE_ID
STRIPE_SUCCESS_URL
STRIPE_CANCEL_URL
```

These values are for customers paying Ultimate Back Office for 24/7 Sales Partner. Do not configure Stripe Connect here; Stripe Connect is reserved for future customer payment-processing products such as Super Simple Payments.

Domain services configuration also lives in `private/config/env.php`:

```text
NAMECHEAP_API_USER
NAMECHEAP_API_KEY
NAMECHEAP_USERNAME
NAMECHEAP_CLIENT_IP
NAMECHEAP_SANDBOX
DOMAIN_DEFAULT_REGISTRAR
DOMAIN_TARGET_IPV4
DOMAIN_TARGET_IPV6
DOMAIN_WWW_CNAME
DOMAIN_TXT_VERIFICATION_NAME
DOMAIN_TXT_VERIFICATION_VALUE
DOMAIN_MAIL_MX_HOST
```

Do not commit Namecheap credentials. `DOMAIN_TARGET_IPV4`, optional `DOMAIN_TARGET_IPV6`, and optional `DOMAIN_WWW_CNAME` define the website launch DNS records the Domain Manager prepares. TXT verification and MX host values are optional placeholders for verification and future email provisioning.

---

# Database Migrations

Migrations live in:

```text
database/migrations
```

Migrations are run manually on staging.

Do not assume Codex can run migrations.

Do not edit previously-run migrations unless specifically approved.

Create a new migration for each sprint that changes database structure.

---

# Staging Validation Checklist

After every deploy:

* Run PHP lint.
* Run new migration if applicable.
* Reload Apache.
* Test changed URLs.
* Test authentication.
* Test access control.
* Test role permissions.
* Test database writes.
* Check Apache logs if errors occur.

For Stripe billing changes, also validate on staging:

* `private/config/env.php` contains Stripe test-mode keys and Price IDs, with no real secrets committed.
* `public/accounts/checkout.php?business_id={BUSINESS_ID}` redirects an authenticated linked 24/7 Sales Partner business to Stripe Checkout.
* Missing Stripe config shows a safe account-page error instead of a white screen.
* Completing Checkout updates the local subscription after Stripe webhook delivery.
* `invoice.payment_succeeded` creates or updates one local payment record for the Stripe invoice.
* `invoice.payment_failed` marks the local subscription `past_due` and shows warnings in Billing, Subscriptions, and 24/7 Sales Partner Launch Readiness.
* Admin Billing shows Stripe customer ID, Stripe subscription ID, payment method status, and latest payment/invoice status.
* Module access remains unchanged by failed payment handling.

For Domain Services changes, also validate on staging:

* `private/config/env.php` contains Namecheap sandbox values and domain target DNS values, with no real secrets committed.
* `public/accounts/domains.php` allows an authenticated customer to request a new domain and connect an existing domain for a linked business.
* Customer Domains shows status, progress, next action, estimated timing, DNS records, and SSL status.
* `public/app/admin/domains.php` allows admins to check availability, purchase a domain in sandbox, refresh registrar status, sync DNS, verify DNS, update SSL status, and mark a ready domain live.
* Failed Namecheap calls show a safe admin error and store domain status/error details for support review.
* 24/7 Sales Partner Launch Readiness marks Domain complete only after domain, DNS, and SSL readiness are satisfied.
* SSL status tracking does not claim certificate automation unless staging infrastructure has completed that step.

Logs:

```bash
tail -n 100 /var/www/ubo-staging/logs/staging-app-error.log
tail -n 100 /var/www/ubo-staging/logs/staging-accounts-error.log
```

---

# Production Deployment Rules

Production deployment should not begin until:

* Staging validation passes.
* Database backup is complete.
* Sprint is tagged verified.
* No known blocking bugs exist.

Production should use separate:

* Droplet
* Database
* Environment config
* Backups

Production should never share the staging database.

---

# Tags

Each completed sprint should receive:

```text
sprint-X-complete
sprint-X-verified
```

Example:

```bash
git tag sprint-5-complete
git push origin sprint-5-complete

git tag sprint-5-verified
git push origin sprint-5-verified
```

---

# Backups

Before closing each sprint, create a staging database backup.

Example:

```bash
mysqldump \
-h ubo-stage-mysql-do-user-18803129-0.g.db.ondigitalocean.com \
-P 25060 \
-u ubo_stage_user \
-p \
--ssl-mode=REQUIRED \
ubo_staging > /root/sprint5_verified.sql
```

Verify:

```bash
ls -lh /root/sprint5_verified.sql
```

---

# Current Deployment Philosophy

Staging is for proving features.

Production is for customers.

No feature should go to production until it has passed staging validation.
