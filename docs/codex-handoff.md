# Codex Handoff

## Current M5C post-submit orientation enhancement

**M5C POST-SUBMIT ORIENTATION ENHANCEMENT IMPLEMENTED LOCALLY / REVIEW REQUIRED**.
M5C, M5 and Sprint 8.8 remain **IN PROGRESS**; M6 remains **NOT STARTED**.
M1–M4, M5A and M5B remain COMPLETE / STAGING PASS / FORMALLY CLOSED.
Production remains UNAUTHORIZED / NOT DEPLOYED.

Current interpretation of the 15.013-second recording:
**NARRATOR SUCCESS TEST INCONCLUSIVE — POLITE SPEECH QUEUE STILL ACTIVE AT RECORDING CUTOFF**.
Narrator was reading document title/application chrome at cutoff. The original failed
acceptance report remains unchanged as historical evidence; it is not proof of a
polite live-region implementation defect.

On baseline `8d198af9c96c52f879252932456eef29cb0b97cb`, Website Manager already
supplies its escaped page title through the existing shared header. The narrow local
enhancement adds only fixed “Feedback sent”, “Website approved” or “Changes requested”
prefixes for the corresponding one-time receipt-bearing GET. Normal/later terminal
GETs, legacy saves and unknown receipt values retain the exact original title.
The single initially empty polite status, template, noscript fallback, population
timing and focus behavior are unchanged. No shared layout, backend, lifecycle,
authorization, CSRF, replay, redirect or error behavior changes.

See the [M5C implementation and evidence record](sprint-8.8-m5c-local-implementation.md)
for the title path, local validation totals and revised recorded acceptance protocol.
Latest historical report SHA-256:
`a3a8094aba91509f5d468e200a9d411d10f5c1068c5c6a2e13d752a31dfd3f11`.

The next recorded test has two observations: mandatory immediate title orientation
before the full sidebar, and the distinct detailed polite receipt at a graceful idle
opportunity. Record up to 60 seconds; continuous page reading at that limit is
**INCONCLUSIVE — NARRATOR NEVER REACHED IDLE**, not application FAIL. Missing detail
after actual idle plus a reasonable observation interval, or repeated detail, fails.
Local DOM/title tests are not Narrator PASS. Review, separately authorized deployment
and recorded Narrator revalidation remain mandatory; M5C/M5 cannot close yet.
No staging access, deployment or M6 work occurs in this task. Earlier server/browser
evidence remains authoritative; the optional favicon fallback remains non-blocking.

The M5B closeout snapshot below is historical; this current status supersedes its
M5C NEXT / NOT STARTED wording without changing the M5B evidence.

## M5B staging closeout — 2026-09-13 (historical snapshot)

**M5B COMPLETE / STAGING PASS / FORMALLY CLOSED** on deployed/validated SHA
`8cd63146713ef8fef26fd2861e960ac64ee1387a`. M5A remains **COMPLETE / STAGING PASS /
FORMALLY CLOSED**. M5 is **IN PROGRESS**; M5C is **NEXT / NOT STARTED**; M6 is
**NOT STARTED**. Migration 025 is absent/reserved for M6. Production is
**UNAUTHORIZED / NOT DEPLOYED**. This is an external documentation export; the deployed
checkout was not edited and no deployment, migration, commit, or PR occurred in the
completion run. See [M5B closeout](sprint-8.8-m5b-closeout.md).

Completed M5B gates: customer feedback/tone/emphasis/image-request browser mutations;
customer approval and request-changes with exact POST303GET receipts and legal DB
states; stale-tab409 with zero writes; populated cross-tenant manager/preview denial;
both forged preference400 cases with zero writes; targeted leakage; and the earlier
real-MySQL/concurrency/replay/HTTP/security validation. All 81 staging table counts
reconciled after final synthetic cleanup. Customer approval does not publish or
automatically create an internal approval request.

M5C — Integrated customer QA and closeout — owns correction of the approximately 11px
overflow at 360px; review and correction if needed of PRG focus returning to BODY;
the complete authenticated browser and responsive matrices; actual 200% zoom;
keyboard/accessibility and required screen-reader validation; complete console/network
QA; integrated customer-workflow regression; the final real-MySQL concurrency,
eligibility, and integrity run; private-data and lifecycle integrity confirmation;
evidence, cleanup, and formal M5 closeout if every M5C gate passes. These requirements
were explicitly deferred by the final M5B closure request and remain unstarted. Prior
shared global-module-disable and exact independent 128KiB valid-history limitations
remain documented and do not independently block M5B closure.

The immutable four-report evidence chain is:

1. Backend / real MySQL: `/home/codex-validation/ubo-sprint-8.8-m5b-final-validation-20260913T205919Z/SPRINT-8.8-M5B-STAGING-FINAL-VALIDATION.md`
   SHA-256: `8d74bd6b479a84e04b68fd3136df71d032fda12f34785f147954ef2e9bdae909`
2. Staging-host browser dependency report: `/home/codex-validation/ubo-sprint-8.8-m5b-browser-completion-20260913T212752Z/SPRINT-8.8-M5B-BROWSER-COMPLETION.md`
   SHA-256: `354b02c454dd84c345fb9ecd8d9efa47f7e5449840b77dcc0004b18ef2c1d1f3`
3. External browser partial report: `/home/codex-validation/ubo-sprint-8.8-m5b-browser-external-20260913T214743Z/SPRINT-8.8-M5B-EXTERNAL-BROWSER-VALIDATION.md`
   SHA-256: `febc8c065a9e3e6d95daee8f34d07f34baab0dda1af96764a18164d930b59deb`
4. Final browser mutation completion: `/home/codex-validation/ubo-sprint-8.8-m5b-browser-final-20260913T220233Z/SPRINT-8.8-M5B-FINAL-BROWSER-MUTATION-VALIDATION.md`
   SHA-256: `3bdb19ff3d3bcb6aa7e2a5fd472f36864029474c59bb66612acd1a1e15e6620b`

You are building Ultimate Back Office.

## Current Handoff

Sprint 8.8 M1 is **COMPLETE / STAGING PASS / FORMALLY CLOSED** on validated and deployed SHA
`2a545a056f650122a3d9ccbf077f35cef83f6065`. Migration
`023_website_platform_foundation.sql` is applied and reconciled on staging. The final
six-site validation imported and reconciled all 6 legacy websites and 37 pages,
including normalized legacy duplicate ordering, hash/idempotence, and executable
real-MySQL contracts. Cleanup restored the zero generic baseline with the expected
1 component definition and 4 variants. The generic model remains dormant and the
legacy website runtime remains authoritative. Sprint 8.8 remains **IN PROGRESS**.
Sprint 8.8 M2 is **COMPLETE / STAGING PASS / FORMALLY CLOSED** on merged, deployed,
and validated SHA `31d5f64ba6fdf9005fe839c9d3bae4e996ce3bd4`. M3 is **COMPLETE /
STAGING PASS / FORMALLY CLOSED** on final deployed and validated SHA
`a431f6fc06e24f2252a9a282954d5541551c9000`.
Migration 024 was applied exactly once; the final registry contains 16 definitions and
22 variants with zero verifier drift. M4 is **COMPLETE / STAGING PASS / FORMALLY
CLOSED**. M4A is **COMPLETE /
STAGING PASS / FORMALLY CLOSED** on final deployed and validated SHA
`8805eeeae704f130ddda357e82c4dd936fde5b4c`; M4A required no migration. M4B is
**COMPLETE / STAGING PASS / FORMALLY CLOSED** on merged, deployed, and validated SHA
`557cc34fe4cf3ab56cdcb59fd7c623c495fd8eaf`. M4C is **COMPLETE / STAGING PASS /
FORMALLY CLOSED** on PR #112 merge and final deployed/validated SHA
`d33589da5eebbf8e2ae0dc203837d6667abd1f71`. M5 is **IN PROGRESS**; M5A is
**COMPLETE / STAGING PASS / FORMALLY CLOSED** on deployed/validated SHA
`ee8c670a6dc8bc19ecb0786dff62abfea645aff3`, M5B is **COMPLETE / STAGING PASS / FORMALLY CLOSED**, and M5C
is **IMPLEMENTED LOCALLY / REVIEW REQUIRED**.
Production remains **UNAUTHORIZED / NOT DEPLOYED**. M4's exit gate is complete;
M5–M8 customer/public/runtime work and the full M1–M8 sprint exit gate remain required
before Sprint 8.8 closes.
See [M4C closeout](sprint-8.8-m4c-closeout.md) and
[overall M4 closeout](sprint-8.8-m4-closeout.md).

M5 planning is recorded in the [M5 service/workflow contract](sprint-8.8-m5-service-contract.md),
audited from `c2efc5d210b9c6528414f9096acddd815f7e8985`. Planning PR #114 merged before
implementation, establishing M5A baseline `2b110466e6cf0ef0e456a1c3ea874f7622daab95`.
M5A adds the read-only customer Website Manager review section, exact issued-review
resolver and allowlisted DTO, dedicated GET-only private preview, and keyboard-inert
M3 rendering under an empty iframe sandbox and restrictive srcdoc CSP. The local gate
passes 45/45 standalone suites, with M5A behavior/view/scope results of 103/40/59
assertions. Repository-wide PHP lint passes 180/180. Final staging validation passed
MySQL 8.4.8 native-prepare authorization, issued-review, M3 integrity,
zero-domain-mutation, concurrency, normal OTP-authenticated HTTP/DOM, retained legacy
CSRF/303 PRG, private-data, and cleanup gates. Browser-only responsive/accessibility
smoke is explicitly NOT EXECUTABLE because no browser runtime/operator was available;
the M5A contract permits blocked evidence, while M5C retains the mandatory complete
browser gate. The authoritative report is
`ubo-sprint-8.8-m5a-final-validation-20260911T234520Z/SPRINT-8.8-M5A-STAGING-FINAL-VALIDATION.md`,
SHA-256 `284133b11b7285c34abfa9972b2215532b0c842b5e7f8901b32b07990bb201a7`.
No migration/provider/production action occurred during M5A closeout.

M5B is **COMPLETE / STAGING PASS / FORMALLY CLOSED** on deployed/validated SHA
`8cd63146713ef8fef26fd2861e960ac64ee1387a`, implemented from baseline
`2c742190809006008b42f7e2c7075701047ac74b`. It adds bounded feedback and advisory
presentation/image requests, exact session-bound customer decisions, transaction-time
reauthorization, replay protection, CSRF/303 receipts, and internal submission visibility.
The local gate passes 48/48 standalone suites, including M5B behavior/input-session/
view-route totals of 258/72/118 assertions. PHP lint passes 189/189. Migration 023/024
remain unchanged and 025 remains absent. See the [M5B local implementation record](sprint-8.8-m5b-local-implementation.md)
for security findings and file inventory. The [M5B closeout](sprint-8.8-m5b-closeout.md)
records passed real-MySQL/concurrency/replay/HTTP and authenticated browser mutation
gates, including approval, changes, stale-tab, populated tenant denial, and forged input.
All 81 table counts reconciled after cleanup. M5C is IMPLEMENTED LOCALLY / REVIEW REQUIRED; M6 is NOT STARTED.

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

The M4B final real-MySQL report is
`ubo-sprint-8.8-m4b-final-validation-20260904T010109Z/SPRINT-8.8-M4B-STAGING-FINAL-VALIDATION.md`,
SHA-256 `dedc82f80c61f5aadb164b2c092093368cd67477ca64421c8b54057e0355ddb8`.
These are user-supplied external evidence references. Real inactive/non-authorable
exact-version based-on validation and authenticated browser route validation were
**NOT EXECUTABLE**; the closeout retains their reasons and nonblocking coverage.
Migrations 023/024 remained unchanged, 025+ remained absent, and migration executions
were zero. Cleanup left zero generic validation rows and temporary fixtures, registry
16 definitions / 22 variants with zero drift, and legacy 6 websites / 37 pages.

M4C delivered `/app/admin/site-review.php` and `SiteReviewAdminWorkflow` for internal
review: authoritative revision/site reads, approval timeline, explicit write-once
materiality, the M3 stored-composition review gate, customer request only, internal
request/approve/reject, ancestry/review-ready display, and escaped comments/reasons.
Dedicated CSRF, success rotation, 303 PRG, and separate explicit actions preserve M2/M3
mutation authority. Customer decision UI remains M5. Approval does not publish.

Initial implementation `e76aede9677e16cea01da8bd2e962aac67b6e0f8` and review correction
`2fd772ff64db3e681ebb4ad7e1bdcda32c15f04b` merged through
[PR #112 — Sprint 8.8 M4C: add review and internal approval workflow](https://github.com/fvd8383/ultimate-back-office/pull/112)
at `d33589da5eebbf8e2ae0dc203837d6667abd1f71`. Staging deployment passed with one
deploy-wrapper invocation (exit 0), zero migration-wrapper invocations, 42/42 suites,
and M4C behavior/view/scope 37/21/33 assertions PASS. Deployment report:
`evidence/SPRINT-8.8-M4C-STAGING-DEPLOYMENT.md`; SHA-256
`0f43460b2706805a8d399ae900ffa87857c0c025dfc328cd7a1118a3f23327ea`.

**SPRINT 8.8 M4 STAGING FINAL VALIDATION: PASS** on the same deployed SHA, MySQL
8.4.8, native PDO prepares with emulation disabled. Final report:
`ubo-sprint-8.8-m4-final-validation-20260904T215556Z/SPRINT-8.8-M4-STAGING-FINAL-VALIDATION.md`;
SHA-256 `9cec3387d20ab05afec7c9b50d6659c7596a5b1d6e2085b35ed4651f016899bc`.
These are authoritative user-supplied external references, not new local staging runs.
Final regression passed 42/42 suites and M4C 37/21/33. Final Git enumeration and lint
passed **171 tracked / 171 linted** PHP files, matching the local 171/171 result.
Deployment reported 167; its evidence was unavailable to final validation to explain
that count, so no unsupported cause is asserted.

Integrated M4A/B/C service workflows, authorization, zero-mutation reads, review and
approval, audit/integrity, and cleanup passed. A real associated Owner passed the
existing M2 customer approval service; Internal Admin customer impersonation was
rejected. This added no customer UI, route, authentication, or preview and did not
begin M5. Three real independent-connection MySQL races passed: materiality had one
material winner, one conflict, and exactly one successful event; customer request had
one row with one creation and one idempotent result; internal decision had one approval
winner and one conflict, ending approval `approved`, revision `internally_approved`,
and site `approved`. False success events were zero. Deployed deterministic TOCTOU
coverage separately passed; the local fixture itself cannot execute real concurrency.

Authenticated browser route validation remained **NOT EXECUTABLE** because no approved
safe staging session mechanism existed. It is nonblocking given real service
authorization/mutations, route/source suites, and five safe unauthenticated route
redirects with zero 5xx; no session/cookie forgery or browser PASS is claimed.
M4C required no migration: 023/024 unchanged, 025+ absent, migration executions zero.
Final cleanup left generic rows/approvals/test events at zero, registry 16/22/0 drift,
legacy 6 websites/37 pages, no actors created or modified, no `/tmp` helpers, and a
clean tree at the same deployed SHA. Publication, final-validation deployments,
provider calls, production access, Apache changes, and DNS changes were zero.

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
cutover. M4A, M4B, M4C, and M4 overall are
**COMPLETE / STAGING PASS / FORMALLY CLOSED**. Sprint 8.8 remains **IN PROGRESS**.
M5 is **IN PROGRESS**. M5A's customer-authenticated read-only preview foundation is
**COMPLETE / STAGING PASS / FORMALLY CLOSED**. M5B feedback/change requests and
customer approval UI are also **COMPLETE / STAGING PASS / FORMALLY CLOSED**. M5C still
requires integrated customer-workflow and browser validation, the final real-MySQL
concurrency/eligibility/integrity run, private-data/lifecycle confirmation, evidence,
cleanup, and formal M5 closeout.
Build/deployment, domain/routing, LeadHub ingestion, and legacy runtime cutover remain
later milestones. Administrative `approved` does not mean published, live, deployed,
domain-active, or production-ready. The contract is
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
