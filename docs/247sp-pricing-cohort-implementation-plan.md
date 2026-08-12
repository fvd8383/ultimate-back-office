# 247SP Pricing Cohort Implementation Plan

## Status And Gate

Pricing P1 is implemented in migration `022_247sp_pricing_cohorts.sql`,
`PricingCohortManager`, and the focused standalone tests. The migration has not been
applied to staging or production. Pricing P2 and the dedicated staging gate remain
planned and blocking, so this document does not claim that cohort-aware billing is
available to customers.

The implementation is the first gate after Sprint 8.7 closes and before Sprint 8.8 M1:

```text
Sprint 8.7 closeout merged
  -> Pricing P1: schema and PricingCohortManager
  -> Pricing P2: billing, Stripe, customer, and admin integration
  -> dedicated staging validation PASS
  -> Sprint 8.8 M1
```

No production 247SP business signup may be accepted until this gate passes. The
P1 additive migration is `022_247sp_pricing_cohorts.sql`. Historical migrations,
including their legacy prices, remain immutable.

## Fixed Product And Assignment Contract

There is one 247SP product. Alpha, Beta, Founding, and Standard are pricing cohorts,
not feature tiers.

| Cohort | Permanent sequence positions | Setup fee | Introductory period | Recurring price |
| --- | ---: | ---: | --- | ---: |
| Alpha | 1-5 | $0 | First 6 months free | $79/month afterward |
| Beta | 6-10 | $0 | None | $97/month |
| Founding | 11-25 | $100 one-time | None | $147/month |
| Standard | 26+ | $250 one-time | None | $197/month |

One completed 247SP business signup consumes one permanent customer sequence
position. Cohort assignment occurs atomically as part of successful completion of
that business signup. Anonymous account creation, website launch, first payment,
Stripe webhook receipt, later subscription state, and active-customer count do not
determine cohort.

The implementation-level completed-signup boundary is the successful transaction
that creates or confirms the local 247SP business subscription and stores its
sequence, cohort, and locked commercial terms. Rollback consumes no position. A retry
for the same business signup returns the existing assignment. Cancellations never
reopen positions. Each independently completed signup for a multi-business owner
consumes one position. Separate environment databases keep test/staging allocations
out of production.

## Pricing P1 — Schema And `PricingCohortManager`

### Scope

P1 adds migration `022_247sp_pricing_cohorts.sql`, seed/configuration records for the
four approved cohorts, the authorized `PricingCohortManager` service, and standalone
schema/service tests. It reuses the one `plans` row identified by `product_key =
'247sp'` as stable product identity. It does not call Stripe, alter Checkout, integrate
the completed-signup route, or present the new terms in customer/admin routes.

Implemented P1 tables are `pricing_cohorts`,
`product_customer_sequence_counters`,
`product_customer_sequence_allocations`, and `subscription_commercial_terms`.
Allocation and activity writes occur inside the same transaction as the guarded
counter advance. Alpha expiration uses a UTC calendar-month calculation that clamps
invalid target-month days; non-Alpha snapshots leave introductory dates null and set
recurring billing start to the completed-signup timestamp.

### Implemented additive schema

Use existing product/plan identity where it can represent one stable 247SP product
without confusing product entitlement with price. If a durable product record is
missing, add the smallest product identity needed by the following tables.

#### `pricing_cohorts`

Responsibility: versioned commercial configuration for a sequence range.

Implemented fields:

- `id BIGINT UNSIGNED` primary key;
- `product_id` or stable `product_key`, required and foreign-keyed where applicable;
- `cohort_key VARCHAR(32)`, required (`alpha`, `beta`, `founding`, `standard`);
- `display_name VARCHAR(100)`, required;
- `position_start BIGINT UNSIGNED`, required and inclusive;
- `position_end BIGINT UNSIGNED NULL`, inclusive; `NULL` means unbounded;
- `setup_fee DECIMAL(10,2)` and `monthly_fee DECIMAL(10,2)`, required;
- `currency CHAR(3)`, required;
- `free_introductory_months SMALLINT UNSIGNED`, required, default zero;
- `effective_from DATETIME`, required, and `effective_until DATETIME NULL`;
- `version INT UNSIGNED`, required;
- `is_active TINYINT(1)`, required;
- `stripe_recurring_price_ref VARCHAR(255) NULL` and reference version/key;
- `stripe_setup_price_ref VARCHAR(255) NULL` and reference version/key;
- timestamps.

Constraints/indexes:

- unique product/cohort/version;
- unique product/position-start/effective-version as appropriate;
- indexes for active effective range selection;
- checks for nonnegative prices/months and `position_end >= position_start`;
- service validation under a product lock rejects overlapping effective ranges;
- referenced cohort rows are retained and never edited to reprice snapshots.

Stripe references may initially be nullable for P1 local testing, but P2 and its
validation gate require the applicable test-mode references before signup is enabled.
No secret is stored in these rows.

#### `product_customer_sequence_counters`

Responsibility: lockable, product-scoped source of the next never-reused position.

Implemented fields are product identity as the primary key,
`next_sequence_number BIGINT UNSIGNED NOT NULL`, optional `lock_version`, and
timestamps. Allocation uses a transaction and `SELECT ... FOR UPDATE` (or the
repository-supported equivalent). The counter only advances in the transaction that
stores the allocation and snapshot.

#### `product_customer_sequence_allocations`

Responsibility: immutable evidence that one completed business signup consumed one
product sequence.

Implemented fields include `id`, product, business, subscription, assigned cohort,
`customer_sequence_number`, a stable completed-signup idempotency key,
`assigned_at`, actor/system and correlation identifiers, and timestamps.

Constraints include unique product/sequence, unique subscription, and unique
product/idempotency key. Business/subscription/cohort deletion is restricted so a
consumed position cannot disappear. Corrections use explicit forward audit/repair,
not row reuse or renumbering.

#### `subscription_commercial_terms`

Responsibility: immutable one-to-one commercial snapshot used for billing and display.

Implemented fields include:

- `subscription_id` unique and foreign-keyed;
- allocation/cohort and `customer_sequence_number`;
- locked setup and monthly amounts plus currency;
- `pricing_assigned_at` and business-signup date/time;
- `introductory_period_starts_at`, `introductory_period_expires_at`, and
  `recurring_billing_starts_at` (nullable only when the selected cohort has no such
  phase and the billing contract does not require the date);
- locked recurring/setup Stripe price references and their configuration version;
- timestamps and safe correlation/audit references.

The snapshot is never recomputed from current cohort configuration. Customer/admin
views and Stripe orchestration consume it. One-time setup fees remain separate from
recurring revenue.

### Implemented service transaction

`PricingCohortManager` owns authorization, tenant checks, range selection, allocation,
date calculation, and the transaction:

```text
begin transaction
  -> authorize the business/subscription context
  -> lock or find the local 247SP business subscription
  -> return its existing assignment if already assigned
  -> lock the 247SP product sequence counter
  -> allocate the next never-reused sequence
  -> select the active effective cohort containing that sequence
  -> establish/link the local subscription
  -> snapshot locked fees, intro terms, and Stripe references/version
  -> calculate and store Alpha dates once
  -> store immutable allocation and commercial snapshot
  -> advance the counter
  -> store bounded success activity/audit
  -> commit
```

Failure rolls back the subscription mutation, allocation, snapshot, and counter
advance together. No success activity is written after rollback. Duplicate requests
return the existing assignment without advancing the counter. Routes do not perform
direct cohort/allocation SQL.

### Alpha date rule

For sequence positions 1-5, store the actual introductory start, expiration, and
recurring billing start at assignment. The approved business flow is:

```text
completed business signup
  -> Alpha position and locked terms
  -> $0 setup
  -> six-month free introductory period
  -> automatic $79/month billing after the stored expiration
```

The implementation must define a single UTC/calendar-month rule and test end-of-month,
leap-year, and time-zone boundaries. Reads must not repeatedly calculate six months
from signup.

### P1 tests and exit criteria

Standalone tests must cover:

- sequences 1/5 Alpha, 6/10 Beta, 11/25 Founding, 26/high Standard;
- concurrent allocation protection and unique constraints;
- idempotent repeated completed signup;
- rollback without number consumption or success activity;
- cancellation without reuse;
- one allocation per independently completed multi-business signup;
- locked prices unchanged after cohort configuration changes;
- exact Alpha introductory dates and non-Alpha date behavior;
- missing/overlapping cohort configuration fails safely;
- inactive cohort/effective-version selection;
- customer/tenant/admin authorization and cross-business rejection;
- no direct pricing SQL in route controllers.

P1 local implementation exits after migration checks, standalone tests, repository
lint, and rollback review pass. Genuine parallel-session allocation behavior and the
applied migration remain mandatory dedicated staging validations after review and
merge; P1 does not authorize staging access by itself.

## Pricing P2 — Billing, Stripe, Customer, And Admin Integration

### Scope and services

P2 integrates the P1 contract through `BillingFoundation`, `StripeBilling`, and the
service that owns successful completion of the 247SP business signup. It updates
Checkout/subscription orchestration, webhook/reconciliation behavior, customer billing
views, and admin billing views without hard-coded prices in routes or templates.

The responsible signup route/service and Stripe workflow are implementation details;
they must call the P1 transaction exactly once at the completed-business-signup
boundary. Stripe latency or webhook delivery must not become the cohort-selection
event.

### Planned Stripe behavior

- Select recurring/setup test Price references from the locked snapshot, not one
  global `STRIPE_247SP_PRICE_ID`.
- Maintain separate test/live configuration and reject environment/reference mismatch.
- Collect or retain a payment method safely for Alpha's later automatic charge.
- Use the stored Alpha expiration/recurring-start dates for Stripe trial/schedule
  behavior and reconciliation.
- Charge Beta `$97/month` with no setup fee from completed signup.
- Charge Founding `$100` one time plus `$147/month`.
- Charge Standard `$250` one time plus `$197/month`.
- Keep setup charges separate from MRR and idempotent across retry/webhook replay.
- Verify signed webhooks, deduplicate provider events, map them to the already assigned
  subscription, and never reselect a cohort from provider data.
- Reconcile safe provider failures without losing the local assignment.

The P2 design must specify how successful signup can commit without an unrecoverable
split-brain provider operation. External Stripe calls do not run inside a long database
transaction. Use durable local intent/state and idempotent provider commands where a
provider step follows local assignment.

### Planned presentation

Admin billing displays customer sequence, cohort, locked setup fee, locked monthly
amount, intro state/expiration, recurring billing start, local/Stripe subscription
state, and safe provider references. Customer billing displays only that customer's
locked terms and status. Customers cannot edit cohorts, sequence, or snapshots.

### P2 tests and exit criteria

Tests cover completed-signup orchestration, all four commercial contracts, Stripe
reference selection, Alpha payment-method retention and expiration transition,
one-time setup idempotency, webhook replay/order/failure, reconciliation, customer and
admin authorization, safe missing configuration, and the absence of hard-coded UI
prices. P2 does not exit until test-mode behavior is ready for the dedicated gate.

## Dedicated Pricing Staging Validation Gate

This gate runs only after P1 and P2 are merged through the approved workflow. It uses
Stripe test mode and no production calls.

1. Record repository, database, environment, and log baselines.
2. Validate migration `022`, rollback assumptions, indexes/FKs/uniqueness, seed ranges,
   and schema reconciliation.
3. Run PHP lint and all standalone pricing/billing/authorization/static-route tests.
4. Exercise boundary allocations 1, 5, 6, 10, 11, 25, 26, and a high sequence in an
   isolated approved fixture strategy.
5. Prove concurrent signup uniqueness, idempotent retry, transaction rollback without
   consumption, cancellation without reopening, and multi-business behavior.
6. Verify locked snapshots survive cohort configuration version changes.
7. Verify exact Alpha start/expiration/recurring dates and automatic `$79/month`
   test-mode transition behavior.
8. Complete Stripe test-mode Checkout/payment-method collection for each cohort;
   verify setup charges, recurring prices, subscriptions, invoices, and local state.
9. Replay and reorder signed test webhook events; verify deduplication and
   reconciliation after safe simulated failures.
10. Verify customer and admin UI fields, tenant isolation, CSRF, safe errors, and a
    clean browser console.
11. Review bounded Apache/PHP and worker/webhook logs for warnings, fatals, PDO errors,
    secrets, and unnecessary customer/provider payloads.
12. Remove synthetic customers, subscriptions, Stripe test objects where appropriate,
    payments, allocations, and activities; reconcile counters, tables, provider state,
    and repository baseline.
13. Record an evidence-backed PASS/FAIL report and checksum.

Any failed atomicity, duplicate allocation, wrong price, Alpha date/payment-method,
tenant-isolation, webhook, or reconciliation check blocks Sprint 8.8 and first-customer
acceptance until repaired and rerun.

## Deferred Commercial Policy

Refunds, fraud, ownership changes, reactivation after cancellation, setup-fee treatment
on reactivation, taxes, overage-policy differences, and unusual manual exceptions need
explicit later policy. They do not reopen the completed-business-signup assignment
event or consumed Alpha/Beta/Founding positions.

## Completion Evidence

The pricing gate is complete only when both implementation PRs are merged, migration
022 is applied and reconciled on staging under explicit approval, all required tests
and browser/provider checks pass, cleanup/reconciliation passes, documentation reflects
implemented behavior, and the validation report/checksum are retained. Until then the
overall pricing gate remains **incomplete / first-customer critical** despite the
implemented P1 foundation.
