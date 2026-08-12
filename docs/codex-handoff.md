# Codex Handoff

You are building Ultimate Back Office.

## Current Handoff

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
Sprint 8.7 is complete at the Pricing P1 baseline
`0aa076140eb04c66141121fda48198efb5081fb8`.

Pricing P1 is implemented for review in migration
`022_247sp_pricing_cohorts.sql`, `private/classes/PricingCohortManager.php`, and the
focused standalone tests. It reuses the stable `plans.id` for `product_key = '247sp'`,
adds durable cohort/counter/allocation/snapshot records, atomically assigns never-reused
positions, stores Alpha dates, enforces user/system authorization and tenant isolation,
and records success activity inside the transaction. No migration has been applied to
staging or production.

Pricing P2 remains the next part of the first-customer gate: completed-signup route
integration, cohort-aware Checkout, Alpha payment-method/free-period behavior, setup
charges, webhooks/reconciliation, and customer/admin pricing presentation. A dedicated
staging validation remains required after P1 and P2 review/merge. Migration 022
precedes planned Sprint 8.8 migration `023_website_platform_foundation.sql`.

The executable future plans are `docs/sprint-8.8.md` for the Website Platform And
Component CMS and `docs/sprint-8.9.md` for the Communications Core Foundation.
Vendasta professional email is first-customer critical in Sprint 8.9 M1; Twilio's
shared provider/webhook foundation is Sprint 8.9; Retell voice runtime remains Sprint
8.10.

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
