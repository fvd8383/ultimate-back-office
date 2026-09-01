# Codex Handoff

You are building Ultimate Back Office.

## Current Handoff

Sprint 8.8 M1 is **COMPLETE / STAGING PASS** on validated and deployed SHA
`2a545a056f650122a3d9ccbf077f35cef83f6065`. Migration
`023_website_platform_foundation.sql` is applied and reconciled on staging. The final
six-site validation imported and reconciled all 6 legacy websites and 37 pages,
including normalized legacy duplicate ordering, hash/idempotence, and executable
real-MySQL contracts. Cleanup restored the zero generic baseline with the expected
1 component definition and 4 variants. The generic model remains dormant and the
legacy website runtime remains authoritative. Sprint 8.8 overall is **IN PROGRESS**.
M2 service/domain implementation is locally complete on branch
`codex/sprint-8.8-m2-site-lifecycle-services`; it is **IMPLEMENTED LOCALLY / REVIEW
REQUIRED / STAGING VALIDATION PENDING** in draft PR
`https://github.com/fvd8383/ultimate-back-office/pull/103`. Production remains
**UNAUTHORIZED / NOT DEPLOYED**.

The authoritative final report is
`ubo-sprint-8.8-m1-final-validation-20260901T001402Z/SPRINT-8.8-M1-STAGING-FINAL-VALIDATION.md`,
SHA-256 `db9dcf37aaac700b12604555f32c01d974c28a6a520c6bf1a8a28a97152f6daf`.
See `docs/sprint-8.8-m1-closeout.md` for the implementation, correction, deployment,
validation, and clean-baseline record.

Sprint 8.7 Milestone 4 is complete and staging validated as PASS on deployed commit
`d11bd0e7d14b9d9dd432f3ce244a9b2bbebfafb7`. Cleanup and repository/database
reconciliation passed. The final report is
`/home/codex-validation/ubo-sbp-validation/MILESTONE-4-REGRESSION-RERUN-3.md`,
SHA-256 `8ea329ecc1f1515eaafe28cf5284d6e6f6a97bc61ec010b106e4a67620f849b4`.

Sprint 8.7 Milestone 5 is COMPLETE / PASS. The validated/deployed commit is
`ea81194e7d853782f927fdf58ed65eecd6473a7f`, the final deployed `main` state after
the Milestone 5 implementation and required fixes. The final successful validation
artifact SHA-256 is
`687a1444664f9d7167dfb316510f09094e922c2b83166874849db44fb10382a6`.

Sprint 8.7 Milestone 6 is COMPLETE and its documentation-only website-platform
architecture audit was merged at `fa9228eefbbba94523781599e74ca04e0dbadb22`.
Sprint 8.7 is COMPLETE. Pricing P1 is COMPLETE / STAGING PASS at
`e71f7bed62e54cc5851e2bb365c136e6b5f6321d`; validation evidence SHA-256 is
`6d20e5fc601a18a494dbf2eac15d4f903ceac24e5860d99429737518f335d67c`.

Pricing P1 is implemented in migration `022_247sp_pricing_cohorts.sql`,
`private/classes/PricingCohortManager.php`, and the focused standalone tests. It reuses
the stable `plans.id` for `product_key = '247sp'`, adds durable
cohort/counter/allocation/snapshot records, atomically assigns never-reused positions,
stores Alpha dates, enforces user/system authorization and tenant isolation, and
records success activity inside the transaction. Migration 022 has been applied and
validated on staging. No pricing migration has been applied to production.

Pricing P2 is COMPLETE / STAGING PASS at merged, deployed, and validated SHA
`f4f767d7cf907a085d77f705e734288a3af04f16` (PR #94): completed-signup allocation and business
completion share one local transaction; Checkout is POST/CSRF and consumes locked
cohort Price references; Alpha collects a payment method and uses its exact stored trial
end; setup lines, provider idempotency/recovery, webhook replay/order guards, locked
customer/admin presentation, and MRR changes are included. The CLI-only
`scripts/configure-247sp-stripe-prices.php` populates the current environment catalog
from separate TEST/LIVE configuration and refuses unsafe replacement. P2 required no
new migration. Migration 023 was absent at the pricing closeout and reserved for
Sprint 8.8; it was subsequently implemented, applied, reconciled, and staging
validated as part of the completed M1 gate. The pricing first-customer technical gate
is CLEARED; its evidence is retained in
`docs/247sp-pricing-p1-p2-closeout.md`.

The current roadmap milestone is Sprint 8.8 M2 — `SiteManager` and
revision/lifecycle/approval services — in `docs/sprint-8.8.md`; its local implementation
is complete, review is required, and staging validation is pending. M3 has not started.
Review corrections now require an inherited current customer-approved baseline for
non-material internal approval, close stale older workflows on material successors,
reset material-successor pre-publication approval state to draft, allow pre-publication
customer withdrawal, preserve suspension, and make archive operationally terminal. The
detailed local contract is
`docs/sprint-8.8-m2-service-contract.md`. The later executable plan is
`docs/sprint-8.9.md` for the Communications Core Foundation.
Vendasta professional email is first-customer critical in Sprint 8.9 M1; Twilio's
shared provider/webhook foundation is Sprint 8.9; Retell voice runtime remains Sprint
8.10.

Production pricing migration/deployment is NOT authorized and has not been performed.
The pricing staging PASS does not make the entire 247SP product first-customer or
production ready.

Use the execution model in `docs/codex-rules.md` and `docs/deployment-plan.md`:
local branch/test/PR work first, then review and merge, approved staging deployment,
and evidence-backed Remote SSH validation.

Before writing code, read these files:

1. docs/database-plan.md
2. docs/future-modules.md
3. docs/codex-rules.md

The following shared platform foundation list is retained as historical build-order
context and must not be restarted:

- environment loading
- database connection
- authentication structure
- OTP login foundation
- users
- businesses
- employees
- business_users
- roles
- permissions
- modules
- business_modules
- activity logs
- payment_providers
- business_payment_accounts
- workspace shell
- contacts
- contact statuses
- notes
- tasks

The original restriction on starting feature modules is retained as historical
context. 247SP work is now authorized only through the current approved sprint
milestone. EMD, SSP, TUHWD, KYN, scheduling, field operations, Twilio, and other
future-module work remain out of scope until separately instructed.

Use PHP, MySQL, HTML, CSS, and vanilla JavaScript.

Do not introduce Laravel, Symfony, React, Vue, Angular, Node backend, or another framework.

Development happens locally through Git branches and pull requests. Runtime
validation happens on staging after approved merge and deployment.

Do not modify production unless explicitly instructed.
