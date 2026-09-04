# Sprint 8.8 — Website Platform And Component CMS

## Status And Objective

Sprint 8.8 overall is **IN PROGRESS**. M1 is **COMPLETE / STAGING PASS / FORMALLY
CLOSED** on validated and deployed SHA `2a545a056f650122a3d9ccbf077f35cef83f6065`; migration
`023_website_platform_foundation.sql` is applied and reconciled on staging. M2 is
**COMPLETE / STAGING PASS / FORMALLY CLOSED** on validated and deployed SHA
`31d5f64ba6fdf9005fe839c9d3bae4e996ce3bd4`. M3 is **COMPLETE / STAGING PASS** on
validated and deployed SHA `a431f6fc06e24f2252a9a282954d5541551c9000` and is
**FORMALLY CLOSED**. M4 is **IN PROGRESS**: M4A is **COMPLETE / STAGING PASS /
FORMALLY CLOSED**, M4B is **COMPLETE / STAGING PASS / FORMALLY CLOSED** on merged,
deployed, and validated SHA `557cc34fe4cf3ab56cdcb59fd7c623c495fd8eaf`, and M4C is
**NEXT / NOT STARTED**.
Production is **UNAUTHORIZED / NOT DEPLOYED**. The detailed
completion records are `docs/sprint-8.8-m1-closeout.md`,
`docs/sprint-8.8-m2-closeout.md`, `docs/sprint-8.8-m3-closeout.md`,
`docs/sprint-8.8-m4a-closeout.md`, and `docs/sprint-8.8-m4b-closeout.md`.

The sprint implements the approved generic 247SP/EMD website platform in eight focused
milestones. It replaces no historical migration and does not treat the customer
Website Manager as a drag-and-drop builder. The authoritative architecture is
`docs/sprint-8.7-milestone-6-website-platform-audit.md`.

## Entry Gates

- pricing P1/P2 and their staging validation are complete;
- pricing migration `022_247sp_pricing_cohorts.sql` and website foundation migration
  `023_website_platform_foundation.sql` are applied and reconciled on staging;
- current legacy website, Shared Business Profile, domain, LeadHub, and deployment
  baselines are captured;
- Milestone 6 source-of-truth, rights, approval, EMD, conversion, security, and
  transaction decisions remain approved;
- implementation branches begin from a clean, reviewed `main`.

## Migration Strategy

The initial planned website migration is `023_website_platform_foundation.sql`.
Migration 023 is deliberately the **dependency-safe M1 core**, not every operational
table for the whole sprint.

Migration 023 should contain generic site identity, business associations, stable
logical pages, revisions and immutable revision composition records, repository-backed
component/variant metadata required for import, themes, assets/references, approvals,
generation briefs, legacy mappings/import state, and generic site audit events. This
is the coherent aggregate required for M1 backfill and M2/M3 services.

Durable build/deployment structures should arrive in a later additive M6 migration.
Domain/routing/conversion structures that cannot safely reuse or extend current domain
tables should arrive in a later additive M7 migration. Use the next available numbers
in actual implementation order; do not reserve or create empty files now. Historical
migrations are immutable, and each migration must be independently reviewable,
forward-repairable, and staging reconciled.

## Cross-Sprint Rules

- Shared Business Profile and existing business/service/service-area records remain
  authoritative reusable facts.
- Revisions store reproducible presentation snapshots/references, not a competing
  business-facts database.
- Repository code owns executable components, rendering, validation, PHP, JavaScript,
  and CSS. Database records contain only allowlisted keys, schema versions, metadata,
  order, and validated configuration.
- Site purpose, site lifecycle, revision lifecycle, approval, build, deployment,
  domain, routing, subscription, analytics, and conversion state remain separate.
- Customer approval is revision-specific and required for initial public launch and
  material customer-visible revisions.
- Management services enforce authorization/tenant ownership and own transactions;
  routes do not perform lifecycle SQL.
- Every state-changing browser route uses CSRF. Public ingestion uses registered-site
  resolution, permitted domains, rate limits, spam/replay controls, and correlation.
- External provider/filesystem/Apache operations never run inside long database
  transactions.
- Legacy records remain available during the staged cutover; published revisions stay
  intact when regeneration/build/deployment fails.

## M1 — Generic Schema + Legacy Compatibility/Backfill

### Completion status

Migration `023_website_platform_foundation.sql`, the dormant generic schema, the
bounded/idempotent legacy importer, compatibility comparison/reconciliation reporting,
and focused standalone tests are merged, applied, and validated on staging. M1 is
**COMPLETE / STAGING PASS** on SHA
`2a545a056f650122a3d9ccbf077f35cef83f6065`. The actual schema dependencies
discovered before implementation are recorded in
`docs/sprint-8.8-m1-current-schema-audit.md`.

The importer uses a filesystem preflight followed by one short transaction and locked
DB-source comparison per website, deterministic site and logical-page identities,
explicit website/page mappings, source/imported hashes, and explicit quarantine
durability outcomes. It imports the current repository preview as one snapshot-only
legacy page component with the four repository-supported page variants:
`home`, `service`, `about`, and `contact`. It creates no authoritative generic approval,
publication, build, deployment, domain, routing, conversion, or public-ingestion
behavior.

The canonical imported-revision hash covers the facts/reference payload, generation
brief, logical pages, page presentation and section configuration, repository component
and variant keys/versions, theme, and asset usage/checksums. It does not depend on
environment-local component row IDs. The baseline revision snapshot hash and legacy
mapping imported hash store the same evidence and reconciliation verifies both. Asset
source evidence includes normalized path, SHA-256 bytes, size, MIME/type, and usage, so
a changed, renamed, missing, or unreadable file is detected without overwriting the
baseline.

Revision number remains unique per site, while snapshot hash is an ordinary diagnostic
index: two distinct historical revisions may intentionally have identical presentation
content. Composite ownership foreign keys reject cross-site published, ancestry,
restore, brief, import, approval-supersession, revision-page, section, and asset
references at the database boundary. Nullable historical pointers remain nullable and
history deletion remains restrictive.

Reconciliation reports `candidate_legacy_count`, not an overstated exact eligibility
count, plus imported, quarantined, and unmapped-candidate counts. Full eligibility still
requires importer validation of page content and local asset evidence.

Legacy presentation compatibility uses the existing runtime's effective page order:
`sort_order ASC, id ASC`. Import assigns that sequence unique ordinal generic sort
orders `10, 20, 30, ...`, while preserving each raw legacy `sort_order` in
`presentation_json.legacy_sort_order`. This permits deterministic legacy ties without
relaxing the generic unique revision-order constraint.

Migration 023 is applied and reconciled on staging. The final six-site validation
passed for all 6 legacy websites and 37 pages, then cleanup restored the exact zero
generic baseline with 1 seeded component definition and 4 variants. The existing
247SP generated website reader remains authoritative and generic data remains dormant.
Production is unauthorized. At the M1 closeout, M2 had not yet begun; M2 has since
completed its separate staging gate. See `docs/sprint-8.8-m1-closeout.md` for the M1
evidence and `docs/sprint-8.8-m2-closeout.md` for the M2 completion record.

### Deliverables

- migration `023_website_platform_foundation.sql` with the dependency-safe core above;
- seed data only for repository-owned component/variant keys needed by import;
- one generic `sites` identity per eligible legacy `247sp_generated_websites` record;
- separate site/business associations (EMD/internal sites require no fabricated
  business);
- stable logical pages and one baseline imported revision per eligible site;
- deterministic legacy ID mappings, source/content hash, import time, and import status;
- rerunnable eligibility/backfill command/service with quarantine/error reporting;
- compatibility reads and reconciliation report; no destructive legacy mutation.

### Implementation rules

Backfill runs in bounded batches and is idempotent by unique legacy mapping. Before a
write transaction it validates DB source structure, discovers asset references, and
reads/hashes files. The short transaction then reloads and locks the unit, verifies the
DB source still matches preflight, and consumes the immutable preflight asset evidence;
filesystem inspection does not run while the transaction is active. It derives
lifecycle conservatively and never
marks a site active merely because DomainManager says live. Existing generated pages,
branding, overrides, integration references, and authoritative facts are imported as
snapshot/reference inputs without becoming new authoritative facts.

If temporary dual writes are necessary, one service owns them, records reconciliation
state, and does not declare the generic model authoritative before validation. Legacy
write retirement is a later explicit cutover.

### Tests and exit gate

The M1 exit gate passed on staging: PHP lint passed 115 files; all 18 standalone suites
passed; importer unit/database, `SiteGenerator`, migration, M1 scope/static, and pricing
regressions passed. The first six-site pass imported 6/6 sites and matched 37/37 pages;
source, import, and revision hashes reconciled 6/6; the second pass reconciled 6/6
without duplicates. Executable same-site FK cases rejected 8/8 invalid relationships,
and executable `CHECK` cases rejected 7/7 invalid values. Legacy counts/hashes remained
unchanged and cleanup returned generic tables to zero. The authoritative report is
`ubo-sprint-8.8-m1-final-validation-20260901T001402Z/SPRINT-8.8-M1-STAGING-FINAL-VALIDATION.md`,
SHA-256 `db9dcf37aaac700b12604555f32c01d974c28a6a520c6bf1a8a28a97152f6daf`.

## M2 — `SiteManager` + Revision/Lifecycle/Approval Services

Status: **COMPLETE / STAGING PASS** on merged, deployed, and validated SHA
`31d5f64ba6fdf9005fe839c9d3bae4e996ce3bd4`.

M2 adds the reusable authorization policy and focused site,
revision, and approval managers without route/UI integration or schema changes. It
keeps site activation and revision publication future-gated, applies revision-specific
customer approval supersession only to material successors, preserves prior customer
approval for non-material successors, creates restores as new unpublished revisions,
and records success audit inside each mutation transaction. The detailed contract is
`docs/sprint-8.8-m2-service-contract.md`; the authoritative staging completion record
is `docs/sprint-8.8-m2-closeout.md`.

### Deliverables

- `SiteManager` for identity, association, purpose, and site lifecycle;
- `SiteRevisionManager` for new immutable revisions, validation state, materiality,
  publication candidates, supersession, and restore candidates;
- `SiteApprovalManager` for revision-specific customer/internal decisions,
  revocation/supersession, actors, comments/reasons, and correlation/audit;
- explicit state transition maps for approved site and revision lifecycles;
- reusable authorization policy for customer, Internal Admin, and Super Admin roles.

Material customer-visible changes invalidate prior customer approval. Technical,
non-material changes may use internal approval only when the service records and
audits that classification. Regeneration always creates a new revision. Restore
creates/marks an auditable restoration candidate and never erases history.

### Tests and exit gate

The M2 exit gate passed: PHP lint passed; 22/22 standalone suites passed; the M2
service suite passed 103 assertions, database contract 69 assertions, scope 23
assertions, and approval invariants 22 tests / 95 assertions. M1 and pricing regressions
passed. Real MySQL validated site, revision, and approval concurrency; two-tenant
authorization and cross-tenant denial; stale lock rejection; rejection of the initial
non-material approval bypass; material and inherited non-material approval behavior;
restore; customer withdrawal; suspension dominance; terminal archive; transactional
rollback; ownership integrity; audit safety; and exact cleanup. The optional real
lower-customer-role denial was not executable because no safe fixture existed; this
non-blocking limitation does not replace deployed authorization coverage, real Owner
approval, cross-tenant denial, or Internal/Super Admin coverage.

## M3 — Component Registry + Composition

Status: **COMPLETE / STAGING PASS** on merged, deployed, and validated SHA
`a431f6fc06e24f2252a9a282954d5541551c9000`.

PR #105 delivered the repository-backed component catalog, versioned identity,
schemas/rendering, atomic draft composition replacement with stale-hash protection,
stable logical-page reuse, existing same-site asset validation, deterministic
content/revision hashing, authorized editor/render reads, and M2 review-gate
validation. Migration 024 was applied to staging exactly once and produced 16
definitions and 22 variants while preserving the legacy IDs. Migration 023 remained
unchanged. PR #106 corrected a cross-platform test-only hash guard; its code-only retry
did not rerun migration 024. The detailed behavioral contract is
`docs/sprint-8.8-m3-service-contract.md`, and the authoritative completion record is
`docs/sprint-8.8-m3-closeout.md`. No route, UI, upload, publication,
build/deployment, domain/routing, LeadHub ingestion, provider behavior, or legacy
runtime cutover is included.

### Deliverables

- `ComponentRegistry` backed by a repository allowlist and database metadata;
- approved component/variant keys and versioned configuration schemas;
- composition services for revision pages, ordered sections, themes, and asset refs;
- reusable navigation/header/footer, hero, statistics, service, trust, about, contact,
  CTA, LeadHub form, pricing-list, SEO, mobile CTA, escaping, and validation building
  blocks where repository evidence supports them;
- legacy component/content mapping and deterministic validation errors.

The database stores no executable PHP/JavaScript and no arbitrary markup. Composition
validates section ownership, allowed placement/cardinality, variant schema, asset
rights/lifecycle, output escaping requirements, and reproducibility before a revision
can become reviewable.

### Tests and exit gate

The M3 staging gate passed. The code-only retry passed PHP lint for 141 files, HTTP
smoke without 5xx responses, and all 32 standalone suites. Final real-MySQL validation
passed actual composition replacement, stored hashing, editor and validated-render
reads, top-level escaping and inert forms, stable-page reuse, variant rendering/hash
changes, one-winner/one-`stale_write` concurrency, transaction rollback, review tamper
rejection, post-review immutability, inactive historical-version rendering, asset
rights/MIME/business/tenant boundaries, and the import/restore/review/render legacy
path. Cleanup restored every generic/import/audit table to zero while retaining the
16/22 registry and the authoritative legacy runtime's 6 websites / 37 pages. The real
legacy unknown-rights asset subcase was not executable because the selected source had
zero asset references; deployed automated coverage passed and the limitation is
recorded in `docs/sprint-8.8-m3-closeout.md`.

## M4 — Admin Composition / Revision Workflow

Status: **IN PROGRESS**.

- M4A — Admin Workflow Foundation: **COMPLETE / STAGING PASS / FORMALLY CLOSED**;
- M4B — Composition Editor + Generic Admin Preview: **COMPLETE / STAGING PASS / FORMALLY CLOSED**;
- M4C — Review Submission + Internal Approval + Final M4 Validation: **NEXT / NOT STARTED**.

M4A adds the parallel, internal-only **Site Platform** workspace without changing the
legacy **Websites** administration or customer Website Manager. It includes generic
site list/creation/detail, prepared read models, versioned canonical generation briefs,
server-side authoritative fact snapshots, deterministic empty-draft hashing, and normal
authored draft revision creation with one mutable Site Platform draft per site. Every
POST uses the dedicated CSRF scope, rotates after success, and redirects with PRG.
Routes contain no lifecycle SQL. Migrations 023/024 remain unchanged and no migration
025 is added. See `docs/sprint-8.8-m4-service-contract.md` and the authoritative M4A
completion record, `docs/sprint-8.8-m4a-closeout.md`.

M4A's final deployed and real-MySQL-validated SHA is
`8805eeeae704f130ddda357e82c4dd936fde5b4c`. The deployment gate and all 36
standalone suites passed; focused M4A suites passed 31, 35, 51, and 87 assertions.
Actual site, workspace, generation-brief, snapshot, authored-revision, rollback, and
concurrency behavior passed. Real association and suspension races passed. Real
business-status and module-status race variants were not executable because no safe
reversible non-active staging values were available, and authenticated admin/browser
form cases were not executable because no safe established test-session mechanism was
available; none are mislabeled PASS. Cleanup restored the generic/import baseline to
zero, the registry remained 16/22 with zero drift, and the legacy runtime remained
authoritative. Production remains unauthorized.

### Deliverables

Across all three passes, Admin/Super Admin surfaces provide site list/detail,
generation brief, page composition, approved component/variant selection, permitted
asset selection, revision creation, validation feedback, customer-review submission,
and internal approval. Only the list/detail/brief/draft foundation is implemented in
M4A; composition/preview belongs to M4B and review/approval belongs to M4C.

Routes use M2/M3 services, prepared statements inside those boundaries, CSRF,
post/redirect/get, safe errors, correlation IDs, and role checks. No customer
impersonation dependency is introduced. Super Admin remains the authority for
ownership/routing/domain-rights conversion operations; ordinary composition does not
silently perform those operations.

### M4B completion and staging gate

M4B is **COMPLETE / STAGING PASS / FORMALLY CLOSED**. The composer and generic admin
preview are implemented over M3, with structured repository-schema forms, active
catalog choices, exact-version verification, explicit new/based-on initialization,
page/section/theme operations, permitted existing assets, stale-write protection,
and a validated inert preview. All writes converge on one atomic M3 replacement.
See `docs/sprint-8.8-m4-service-contract.md` for the implemented architecture.
PR #110 merged at final deployed and validated SHA
`557cc34fe4cf3ab56cdcb59fd7c623c495fd8eaf`; M4C is **NEXT / NOT STARTED**.
The original implementation is `d3f0cd34397d3451921d669b29a15a2a0c4b46d4`; the
marketing publication documentation correction is
`a2e1917f76a32fa4cfe99b7b7347e69a0ecdff5d`.

Deployment passed with one deploy-wrapper invocation (exit 0), zero migration-wrapper
invocations, 161 PHP files linted, and 39/39 standalone suites PASS. M4B behavior/view/
scope suites passed 170/37/59 assertions. The deployment report is
`evidence/SPRINT-8.8-M4B-STAGING-DEPLOYMENT.md`, SHA-256
`fd0709031cec52c61df2b435dc16a9153ae53c855e89824b8d842f9608a038c8`.

Final validation passed on real MySQL 8.4.8 with native PDO prepares, emulation
disabled. Editor/page/section/theme operations, real concurrent writers (one success,
one `stale_write`), transaction rollback, same-site immutable based-on initialization,
asset validation/repair, inert validated preview, tamper fail-closed behavior,
authorization, audit/integrity, and cleanup passed. Its report is
`ubo-sprint-8.8-m4b-final-validation-20260904T010109Z/SPRINT-8.8-M4B-STAGING-FINAL-VALIDATION.md`,
SHA-256 `dedc82f80c61f5aadb164b2c092093368cd67477ca64421c8b54057e0355ddb8`.
These external evidence references were supplied by the user; full results and
evidence distinctions are in `docs/sprint-8.8-m4b-closeout.md`.

The real inactive/non-authorable historical exact-version based-on case was
**NOT EXECUTABLE** because it required prohibited shared-registry mutation.
Authenticated browser routes were **NOT EXECUTABLE** because no approved safe staging
session mechanism existed. Both are nonblocking with the deployed behavior/route
contracts and real service authorization coverage recorded in the closeout; neither
is claimed PASS. Invalid layout variant rejection is deployed behavior-suite coverage.
M4B required no migration: 023/024 unchanged, 025+ absent, executions zero. Final
cleanup left zero generic validation rows/fixtures, registry 16/22 with zero drift,
and legacy 6 websites/37 pages. Final validation deployed nothing and changed no
Apache configuration, repository, permissions, ownership, sudoers, or persistent Git
configuration; provider calls and production access were zero.

The existing sales/marketing landing page remains the separate `public/marketing`
property. Its staging preview publication is **PASS / ACTIVE** at
[https://staging-app.ultimatebackoffice.com/marketing/](https://staging-app.ultimatebackoffice.com/marketing/).
User-supplied evidence verifies 200 routes/assets, the canonical 302 marketing
redirect, actual noindex/nofollow response headers, and matching CSS/JS hashes.
Browser viewport, console, and interaction QA are **NOT YET RECORDED**; see
`docs/247sp-marketing-staging-preview.md`. Historically, initial marketing publication
required no M4B deployment; its Apache routing was separate staging-only administrator
configuration. Subsequent M4B deployment/final validation confirmed the marketing
pages/assets remained 200, `/marketing` redirected 302 to `/marketing/`, and
`X-Robots-Tag: noindex, nofollow` remained present. Marketing launch placeholders
remain pending and do not block M4B closeout. Production Apache, DNS, and SSL were
unchanged, and `247salespartner.com` was not configured.

### M4A staging gate and remaining exit gate

M4A required no migration. Its deployment evidence is
`evidence/SPRINT-8.8-M4A-STAGING-DEPLOYMENT.md`, recorded SHA-256
`45725a494bf415ad3adec8b113ca2ae5a72155f9f44efbf27385f6ed8af2bffd`. Its final
real-MySQL report is
`ubo-sprint-8.8-m4a-final-validation-20260903T030735Z/SPRINT-8.8-M4A-STAGING-FINAL-VALIDATION.md`,
recorded SHA-256
`9fe38af06fa13c8196d0e106cc207aa80391c8bc7ae1ab53f403c4792f0b2de8`.

The remaining M4C and final M4 workflow gate must cover role matrices, CSRF, cross-business and forged
IDs, invalid composition,
concurrent edits, draft creation without published revision loss, review submission,
internal approval, safe failure activity, and static no-direct-lifecycle-SQL checks.
Browser smoke covers list/detail/composition/review at responsive widths with a clean
console.

## M5 — Customer Preview / Feedback / Approval

### Deliverables

Website Manager becomes the controlled customer surface for preview, revision review,
feedback, change requests, allowed asset/presentation input, and customer approval.
Business Profile remains the editor for reusable facts.

Customers cannot choose arbitrary components, freely rearrange layout, edit executable
markup, publish, restore, archive, convert, reassign domains, or change LeadHub routing.
Approval binds the authenticated customer actor to one material revision and preserves
time, comments, correlation, and supersession history.

### Tests and exit gate

Cover membership/module checks, tenant isolation, CSRF, immutable previews, stale
revision handling, feedback/change request, permitted asset input, customer approval,
approval invalidation after a material successor, prohibited actions, accessibility,
responsive layouts, and clean browser console. A customer cannot approve another
business/site or cause publication.

## M6 — Build / Deployment / Restore

### Deliverables

- additive build/deployment migration using the next available number;
- `SiteBuildService` with durable idempotent jobs, leases/retry, safe errors, input and
  artifact hashes, versioned immutable releases, and validation summaries;
- provider-neutral `SitePublisher` interface;
- initial `ApacheDigitalOceanSitePublisher` for the existing FDV environment;
- staging deployments, explicit production approval gate, health checks, current
  deployment pointer, deployment history/correlation, reconciliation, and restore to
  a previous known-good release.

Build failure leaves the published revision/deployment intact. Deployment status, not
DomainManager state, proves release success. External filesystem/Apache/provider work
is performed by durable workers outside the lifecycle transaction; short transactions
record intent, leases, results, and pointers.

### Tests and exit gate

Cover build success/failure/retry/idempotency, invalid revision rejection, artifact
integrity, stale worker recovery, staging versus production authority, production
approval, failed health checks, partial external failure reconciliation, concurrent
deployment, current-pointer atomicity, restore, previous-release preservation, safe
logs, and no secrets in artifacts/output.

## M7 — Registered-Site LeadHub + Domain/Routing + EMD Compatibility

### Deliverables

- additive domain/routing/conversion migration if current tables cannot satisfy the
  approved associations safely;
- multiple site-domain associations with exactly one primary active domain per site
  and secondary redirect support;
- durable routing assignments separate from domain/site lifecycle;
- shared registered-site public-ingestion contract:

```text
request Host/domain
  -> active permitted site-domain association
  -> active site and successful active deployment
  -> active routing assignment
  -> business or EMD routing target
  -> LeadHub
```

- server-side resolution; no authoritative browser `business_id`;
- request/payload bounds, permitted-domain validation, rate limiting, spam controls,
  replay/duplicate handling, correlation IDs, and safe audit summaries;
- generic EMD identity (`purpose = emd`) without fabricated customer business;
- controlled EMD/internal-demo to purchased 247SP compatibility;
- controlled eligible canceled 247SP to EMD conversion with Super Admin approval,
  domain/content/media rights review, customer data separation, removal of customer
  routing before EMD routing, analytics removal/reassignment, validation, and audit.

Google Analytics remains optional/customer-owned and does not transfer to EMD by
default. DataForSEO remains a planned separate platform provider and is not required
for the website foundation unless separately scheduled.

### Tests and exit gate

Test primary-domain uniqueness, redirects, inactive/suspended/unpublished sites,
forged Host/business/site/page IDs, rate/replay/spam controls, routing isolation,
business and EMD captures, duplicate reconciliation, both conversion directions,
ineligible conversion, rights/analytics gates, rollback, Super Admin authority, and
customer CRM/lead/conversation isolation.

## M8 — Full Staging Validation + Closeout

At the appropriate implementation point, create
`docs/sprint-8.8-website-platform-staging-validation.md`. Its executable phases are:

1. beginning-state repository/database/environment/provider/log baseline;
2. migrations 023 and later additive migration syntax/state/schema reconciliation;
3. PHP lint, standalone suites, compatibility/backfill tests, static no-direct-SQL and
   no-executable-component checks;
4. backfill counts, hashes, mappings, rerun idempotency, quarantine, and legacy
   preview/read compatibility;
5. site creation; purpose/lifecycle; revision; materiality; approval; composition;
   asset rights; Shared Business Profile consumption;
6. customer/admin authorization, CSRF, cross-tenant rejection, transaction rollback,
   activity/audit correlation, and no false success;
7. admin composition/review and customer preview/feedback/approval browser workflows;
8. build success/failure/retry, staging deployment, explicit production-approval test
   using approved staging simulation, health check, failed deployment, reconciliation,
   restore, and known-good preservation;
9. domain/SSL gate, primary/secondary domains, registered-site LeadHub form ingestion,
   rate/spam/replay controls, and routing;
10. suspend/archive, EMD to 247SP, eligible 247SP to EMD, analytics removal, rights and
    customer-data isolation;
11. responsive/accessibility smoke, clean browser console, bounded Apache/PHP/worker
    log delta, no warnings/fatals/PDO errors/secrets/raw customer payloads;
12. synthetic LeadHub/site/revision/asset/job/deployment/domain/routing/conversion
    cleanup, orphan/cross-tenant checks, repository/database/provider reconciliation;
13. final PASS/FAIL report, evidence inventory, checksum, readiness/handoff update.

No production call or deployment is implied by staging validation. Any unresolved
data-loss, tenant, approval, publication, restore, domain/routing, public-ingestion,
conversion-rights, cleanup, or reconciliation failure blocks closeout.

## Sprint Exit Criteria

Sprint 8.8 closes only when M1-M8 are merged in focused PRs, migrations are applied
and reconciled under approval, legacy compatibility is preserved, generic services and
UI/publisher/routing runtime pass the complete staging runbook, first-customer website
blockers are updated honestly, and planned capabilities are relabeled implemented only
where evidence supports that claim.
