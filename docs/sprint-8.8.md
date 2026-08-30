# Sprint 8.8 — Website Platform And Component CMS

## Status And Objective

**Planned; next authorized roadmap work.** Sprint 8.7 is closed and the dedicated 247SP
first-customer pricing implementation and staging gate are COMPLETE / PASS. Sprint 8.8
has not begun as part of the pricing closeout.

The sprint implements the approved generic 247SP/EMD website platform in eight focused
milestones. It replaces no historical migration and does not treat the customer
Website Manager as a drag-and-drop builder. The authoritative architecture is
`docs/sprint-8.7-milestone-6-website-platform-audit.md`.

## Entry Gates

- pricing P1/P2 and their staging validation are complete;
- planned `022_247sp_pricing_cohorts.sql` is the latest applied migration and schema is
  reconciled;
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

Backfill runs in bounded batches, locks only the rows needed for each unit, and is
idempotent by unique legacy mapping. It derives lifecycle conservatively and never
marks a site active merely because DomainManager says live. Existing generated pages,
branding, overrides, integration references, and authoritative facts are imported as
snapshot/reference inputs without becoming new authoritative facts.

If temporary dual writes are necessary, one service owns them, records reconciliation
state, and does not declare the generic model authoritative before validation. Legacy
write retirement is a later explicit cutover.

### Tests and exit gate

Validate MySQL syntax, columns/types/nullability, FK/delete behavior, indexes and
uniqueness, seed allowlists, empty-database behavior, eligible/ineligible fixtures,
rerun idempotency, collisions, quarantine/repair, counts/hashes/slugs/ownership,
preview-equivalent imported content, no legacy mutation, and full schema
reconciliation. M1 exits with generic data dormant/read-compatible and a documented
rollback-to-legacy-reader path.

## M2 — `SiteManager` + Revision/Lifecycle/Approval Services

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

Implement standalone authorization, tenant isolation, lifecycle, invalid-transition,
revision immutability, materiality, approval supersession, restoration, transaction
rollback, concurrent update, and success/failure audit tests. No route integration
begins until services reject cross-business site/revision/page/asset IDs and write
success activity only after commit.

## M3 — Component Registry + Composition

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

Test every component schema/variant, invalid keys/config, cross-site asset references,
section ordering/cardinality, theme validation, legacy mapping, escaping, deterministic
serialization/hash, and no-executable-code constraints. Static checks confirm routes
and database content cannot select arbitrary filesystem code.

## M4 — Admin Composition / Revision Workflow

### Deliverables

Admin/Super Admin surfaces provide site list/detail, generation brief, page composition,
approved component/variant selection, permitted asset selection, revision creation,
validation feedback, customer-review submission, and internal approval.

Routes use M2/M3 services, prepared statements inside those boundaries, CSRF,
post/redirect/get, safe errors, correlation IDs, and role checks. No customer
impersonation dependency is introduced. Super Admin remains the authority for
ownership/routing/domain-rights conversion operations; ordinary composition does not
silently perform those operations.

### Tests and exit gate

Cover role matrices, CSRF, cross-business and forged IDs, invalid composition,
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
