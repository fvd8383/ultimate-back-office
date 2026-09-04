# Codex Handoff

You are building Ultimate Back Office.

## Current Handoff

Sprint 8.8 M1 is **COMPLETE / STAGING PASS / FORMALLY CLOSED** on validated and deployed SHA
`2a545a056f650122a3d9ccbf077f35cef83f6065`. Migration
`023_website_platform_foundation.sql` is applied and reconciled on staging. The final
six-site validation imported and reconciled all 6 legacy websites and 37 pages,
including normalized legacy duplicate ordering, hash/idempotence, and executable
real-MySQL contracts. Cleanup restored the zero generic baseline with the expected
1 component definition and 4 variants. The generic model remains dormant and the
legacy website runtime remains authoritative. Sprint 8.8 overall is **IN PROGRESS**.
Sprint 8.8 M2 is **COMPLETE / STAGING PASS / FORMALLY CLOSED** on merged, deployed,
and validated SHA `31d5f64ba6fdf9005fe839c9d3bae4e996ce3bd4`. M3 is **COMPLETE /
STAGING PASS / FORMALLY CLOSED** on final deployed and validated SHA
`a431f6fc06e24f2252a9a282954d5541551c9000`.
Migration 024 was applied exactly once; the final registry contains 16 definitions and
22 variants with zero verifier drift. M4 is **IN PROGRESS**. M4A is **COMPLETE /
STAGING PASS / FORMALLY CLOSED** on final deployed and validated SHA
`8805eeeae704f130ddda357e82c4dd936fde5b4c`; M4A required no migration. M4B is
**COMPLETE / STAGING PASS / FORMALLY CLOSED** on merged, deployed, and validated SHA
`557cc34fe4cf3ab56cdcb59fd7c623c495fd8eaf`. M4C is **IMPLEMENTED LOCALLY /
REVIEW REQUIRED** on branch `codex/sprint-8.8-m4c-review-approval-workflow`.
Production remains **UNAUTHORIZED / NOT DEPLOYED**.

M4B was merged through PR #110, “Sprint 8.8 M4B: add composition editor and admin
preview,” at `557cc34fe4cf3ab56cdcb59fd7c623c495fd8eaf`. It adds the structured
composition editor, repository-schema forms/catalog, and validated inert internal
preview over the M3 replacement boundary. No migration or generic runtime cutover
occurs. The staging deployment and final real-MySQL validation passed. The deployment
gate recorded 161 PHP files linted and 39/39 standalone suites PASS, including M4B
170/37/59 assertions. Final validation used MySQL 8.4.8 with native PDO prepares and
emulation disabled. Real editor, concurrency, rollback, assets, validated preview,
authorization, integrity, and cleanup evidence is recorded in
`docs/sprint-8.8-m4b-closeout.md`.

The M4B deployment report is `evidence/SPRINT-8.8-M4B-STAGING-DEPLOYMENT.md`, SHA-256
`fd0709031cec52c61df2b435dc16a9153ae53c855e89824b8d842f9608a038c8`.

M4C local implementation adds `/app/admin/site-review.php` and
`SiteReviewAdminWorkflow` for Internal Admin/Super Admin review work. It presents an
authorized revision/site read model, approval timeline, deliberate write-once
materiality, the existing M3 review gate, customer review request only, internal
review request, and internal approve/reject. The route uses the dedicated CSRF scope,
success rotation, and 303 PRG; services re-resolve state and own all locks,
transactions, transitions, idempotency, supersession, and audit. Customer decisions
remain M5. Approval does not publish the generic site. M4C adds no migration, customer
route, provider, public runtime, staging, production, or deployment work. M4 remains
in progress pending review, merge, deployment, and final real-MySQL validation.
The local gate is 42/42 standalone suites PASS; focused M4C behavior/view/scope
coverage passes 32/16/33 assertions; repository-wide PHP lint passes 171/171; and
`git diff --check` passes. No runtime or real-MySQL validation is claimed locally.
The final real-MySQL report is
`ubo-sprint-8.8-m4b-final-validation-20260904T010109Z/SPRINT-8.8-M4B-STAGING-FINAL-VALIDATION.md`,
SHA-256 `dedc82f80c61f5aadb164b2c092093368cd67477ca64421c8b54057e0355ddb8`.
These are user-supplied external evidence references. Real inactive/non-authorable
exact-version based-on validation and authenticated browser route validation were
**NOT EXECUTABLE**; the closeout retains their reasons and nonblocking coverage.
Migrations 023/024 remained unchanged, 025+ remained absent, and migration executions
were zero. Cleanup left zero generic validation rows and temporary fixtures, registry
16 definitions / 22 variants with zero drift, and legacy 6 websites / 37 pages.

The separate `public/marketing` staging preview is **PASS / ACTIVE** at
[https://staging-app.ultimatebackoffice.com/marketing/](https://staging-app.ultimatebackoffice.com/marketing/).
User-supplied evidence on 2026-09-03 verifies 200 responses for the marketing pages
and assets, the 302 `/marketing` redirect, actual `noindex, nofollow` response headers,
and matching CSS/JS hashes. Staging-only administrator routing serves the existing
marketing property. Historically, that initial publication left the deployed application
at `8805eeeae704f130ddda357e82c4dd936fde5b4c` and required no M4B deployment.
PR #110 was subsequently merged, deployed, and validated at
`557cc34fe4cf3ab56cdcb59fd7c623c495fd8eaf`. M4B deployment and final validation
confirmed the marketing HTTP/asset, redirect, and noindex regression checks passed;
the separate Apache setup did not occur during M4B final validation.
Viewport, console, and interactive browser QA are **NOT YET RECORDED**. See
`docs/247sp-marketing-staging-preview.md`. Production Apache, DNS, and SSL were
unchanged; `247salespartner.com` was not configured. This is not a production launch.

The authoritative M4A final report is
`ubo-sprint-8.8-m4a-final-validation-20260903T030735Z/SPRINT-8.8-M4A-STAGING-FINAL-VALIDATION.md`,
SHA-256 `9fe38af06fa13c8196d0e106cc207aa80391c8bc7ae1ab53f403c4792f0b2de8`.
See `docs/sprint-8.8-m4a-closeout.md` for the implementation, review correction,
deployment, real-MySQL validation, limitations, and clean-baseline record.

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

Sprint 8.8 M2 — `SiteManager` and revision/lifecycle/approval services — is
**COMPLETE / STAGING PASS**. Its final real-MySQL staging report is
`ubo-sprint-8.8-m2-final-validation-20260902T024225Z/SPRINT-8.8-M2-STAGING-FINAL-VALIDATION.md`,
SHA-256 `fa4c3f10796ee0f9c0a9dbf69bbc7d2cbaaa82036cbdbdc0839b5c415314e824`.
The authoritative completion record is `docs/sprint-8.8-m2-closeout.md`, and the
service contract is `docs/sprint-8.8-m2-service-contract.md`. M3 — Component Registry +
Composition — is **COMPLETE / STAGING PASS**. Its contract is
`docs/sprint-8.8-m3-service-contract.md`, and its authoritative completion record is
`docs/sprint-8.8-m3-closeout.md`. Migration 024 versions component registry identity
and was applied once; migration 023 remains unchanged. The final real-MySQL report is
`ubo-sprint-8.8-m3-final-validation-20260902T221631Z/SPRINT-8.8-M3-STAGING-FINAL-VALIDATION.md`,
SHA-256 `5fdfd9ca6b2118ad82b23b97e81a651990b47f9a7140d62a5cef0e857038df70`.
The repository owns executable schemas and fixed rendering, while M3 adds atomic
full-revision composition, same-site ready/rights asset checks, deterministic
stored-row hashes, and M2 review-gate validation. It adds no routes/UI, upload,
build/deployment, LeadHub routing, provider work, or public runtime cutover. M4A added
the parallel internal Site Platform workspace, generic site creation/detail reads,
versioned creative briefs, authoritative server-side snapshots, and deterministic
empty authored drafts. It preserves all legacy website/customer runtime boundaries
and adds no migration, provider action, review/approval UI, generic preview, or public
cutover. M4A is **COMPLETE / STAGING PASS / FORMALLY CLOSED**; M4 is **IN PROGRESS**,
M4B is **COMPLETE / STAGING PASS / FORMALLY CLOSED**, and M4C is **IMPLEMENTED LOCALLY / REVIEW REQUIRED**. The contract is
`docs/sprint-8.8-m4-service-contract.md`. The
later executable plan is
`docs/sprint-8.9.md` for the Communications Core Foundation.
Vendasta professional email is first-customer critical in Sprint 8.9 M1; Twilio's
shared provider/webhook foundation is Sprint 8.9; Retell voice runtime remains Sprint
8.10.

Production pricing migration/deployment is NOT authorized and has not been performed.
The pricing staging PASS does not authorize production or establish first-customer
readiness for the entire 247SP product.

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
