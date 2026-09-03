# Sprint 8.8 M4A — Closeout

## Final Status

Sprint 8.8 M4A — Admin Workflow Foundation — is **COMPLETE / STAGING PASS /
FORMALLY CLOSED** on final deployed and validated SHA
`8805eeeae704f130ddda357e82c4dd936fde5b4c`.

- M1: **COMPLETE / STAGING PASS / FORMALLY CLOSED**
- M2: **COMPLETE / STAGING PASS / FORMALLY CLOSED**
- M3: **COMPLETE / STAGING PASS / FORMALLY CLOSED**
- M4A: **COMPLETE / STAGING PASS / FORMALLY CLOSED**
- M4B: **NEXT / NOT STARTED**
- M4C: **NOT STARTED**
- M4 overall: **IN PROGRESS**
- Sprint 8.8: **IN PROGRESS**
- Production: **UNAUTHORIZED / NOT DEPLOYED**

This closeout does not declare M4 or Sprint 8.8 complete. It does not establish
generic website runtime cutover, production readiness or deployment, or complete
first-customer readiness.

## Delivered Scope

M4A delivered a parallel internal **Site Platform** workspace while preserving the
legacy **Websites** administration and customer Website Manager. The workspace adds:

- Site Platform navigation and the internal routes `/app/admin/sites.php` and
  `/app/admin/site.php`;
- generic site list, creation, detail, active 247SP business selection, revision and
  generation-brief history, and safe approval summaries;
- `247sp`, `emd`, and `internal_demo` creation through `SiteManager`, without invoking
  the legacy generator or fabricating customer businesses;
- append-only, canonical, presentation-only generation briefs with UTF-8 character
  limits, SHA-256 content hashes, `admin_manual` provenance, and server correlation
  IDs;
- authoritative server-side snapshots sourced from Shared Business Profile, selected
  and custom services, service area, public hours/exceptions, website/all FAQs, and
  active public pricing guidance, with private operational data excluded;
- normal empty-composition authored drafts with undetermined materiality, null restore
  provenance, deterministic seed hashing, same-site ancestry, one mutable admin draft,
  and a locked final 247SP eligibility recheck before insertion; and
- Internal Admin/Super Admin authorization, dedicated `admin-site-platform` CSRF,
  success rotation, 303 PRG, safe errors, and no direct route lifecycle SQL.

M4A did not deliver composition/component/variant/asset-selection UI, generic admin
preview, materiality or review-submission UI, approval-decision UI, customer preview
or approval, public runtime cutover, build/deployment, domain/routing, LeadHub public
ingestion, provider integrations, or production activation.

## Implementation And Review History

- Initial M4A implementation commit:
  `aff1ac272845d3885fc625946a00ecc6e7d6242e`
- Review correction commit:
  `3c5e829bf8e939b9adf5878f92432a9c0c6d9e00`
- PR #108 merge and final deployed/validated SHA:
  `8805eeeae704f130ddda357e82c4dd936fde5b4c`

The review correction closed insufficient actual M4A service-behavior coverage, the
247SP eligibility TOCTOU window, and byte-based UTF-8 length semantics. It added an
actual service fixture and behavior suite, locked final association/business/module
eligibility checks, rollback coverage, and dependency-free UTF-8 character counting.

## Schema / Migration Boundary

M4A required no schema change. Migrations 023 and 024 remained unchanged, migration
025+ remained absent, and migration executions during both deployment and final
validation were zero. The final registry remained:

- `component_definitions = 16`
- `component_variants = 22`
- registry verifier: **PASS / zero drift**

## Staging Deployment Gate

The staging deployment gate ran on SHA
`8805eeeae704f130ddda357e82c4dd936fde5b4c` using deployment identity `ubo-deploy`
on `ubo-stage-app`.

- deployment wrapper: **PASS**
- migration wrapper: **NOT INVOKED**
- PHP lint: **151 files PASS**
- deployed HTTP smoke: **PASS**
- new unauthenticated routes: normal 302 authentication redirects, final
  authentication page 200, and no 5xx responses
- standalone suites: **36/36 PASS**
- `WebsitePlatformM4AServiceTest`: **31 assertions PASS**
- `WebsitePlatformM4ADatabaseContractTest`: **35 assertions PASS**
- `WebsitePlatformM4AScopeTest`: **51 assertions PASS**
- `WebsitePlatformM4AServiceBehaviorTest`: **87 assertions PASS**
- M1, M2, M3, and pricing regressions: **PASS**

Deployment evidence is externally retained as
`evidence/SPRINT-8.8-M4A-STAGING-DEPLOYMENT.md`, recorded SHA-256
`45725a494bf415ad3adec8b113ca2ae5a72155f9f44efbf27385f6ed8af2bffd`.

## Final Real-MySQL Validation

Final validation executed the deployed services on `ubo-stage-app` as
`codex-validation`, using database `ubo_staging`, MySQL 8.4.8, and native PDO
prepares (`ATTR_EMULATE_PREPARES = false`). The deployed and validated SHA was
`8805eeeae704f130ddda357e82c4dd936fde5b4c`.

Exact result: **SPRINT 8.8 M4A STAGING FINAL VALIDATION: PASS**.

The externally retained final report is
`ubo-sprint-8.8-m4a-final-validation-20260903T030735Z/SPRINT-8.8-M4A-STAGING-FINAL-VALIDATION.md`,
recorded SHA-256
`9fe38af06fa13c8196d0e106cc207aa80391c8bc7ae1ab53f403c4792f0b2de8`.

## Site Creation Evidence

Actual `SiteManager::createSite()` calls created temporary `internal_demo`, `emd`, and
`247sp` sites successfully. A duplicate active generic 247SP customer site was
rejected. No legacy generation was invoked.

Actual `SiteAdminWorkspace::listSites()` and `siteDetail()` reads passed. They showed
the correct customer association, no fabricated EMD/internal-demo business, safe
approval summaries, and zero unsafe metadata findings. A real non-admin actor was
rejected as unauthorized.

## Generation Brief Evidence

Actual `SiteGenerationBriefManager::createBrief()` calls created append-only versions
1 and 2. Duplicate identical content was rejected.

Two different-content concurrent writers both succeeded with final versions 1 and 2.
For identical-content concurrent writers, one succeeded and one received a conflict.
There were no duplicate brief versions or hashes. The real-MySQL rollback companion
passed without a false success event.

A real multibyte brief and its canonical stored hash passed. Malformed UTF-8 was
rejected, and no `mbstring` dependency was added.

## Snapshot Evidence

The actual 247SP snapshot was deterministic and used accurate source references.
Selected services, service area, FAQ filtering, and pricing filtering passed. The
selected real fixture correctly produced empty custom-service and public-hours
collections; this closeout does not imply those source rows existed. Sensitive
findings were zero.

Actual EMD and internal-demo snapshots passed with null businesses and no fabricated
customer facts.

## Revision / Concurrency Evidence

Actual `SiteRevisionManager::createAuthoredDraftRevision()` execution passed and
verified draft lifecycle, undetermined materiality, `restored_from_revision_id =
NULL`, server-generated facts and references, deterministic server hashing, and empty
composition.

The one-mutable-admin-draft rule passed. Concurrent authored-revision creation produced
one success and one conflict, with zero duplicate revision numbers. Authored revision
creation for an eligible 247SP site passed.

## Eligibility Race Evidence

Two real eligibility races executed successfully:

- active association changed after the initial eligible snapshot and before the
  transactional locking recheck: revision creation rejected with `conflict`;
- business suspension changed after the initial eligible snapshot and before the
  transactional locking recheck: revision creation rejected with `conflict`.

Both produced zero rejected revisions and zero false success events, and the exact
source values were restored.

The business-status race was **NOT EXECUTABLE** because no existing safe reversible
non-active staging value was available; none was invented. The module-status race was
also **NOT EXECUTABLE** for the same reason. Neither unexecuted race is labeled PASS.
These limitations are nonblocking because the two safe real races passed and the
deployed service plus behavior/static coverage recheck and exercise business status
and active 247SP module eligibility under lock.

## Route / Authorization Evidence

Deployed unauthenticated HTTP validation passed with the expected authentication
redirect chain and no 5xx responses. Authorization service tests and CSRF/PRG route
contract tests passed.

Authenticated admin GET and authenticated browser form validation were **NOT
EXECUTABLE**. No established safe staging test-session mechanism avoided
password/OTP/cookie forgery, and validation did not weaken authentication to create
one. These browser cases are not labeled PASS.

## Known Validation Limitations

The explicitly retained nonblocking limitations are:

- real business-status eligibility race: **NOT EXECUTABLE**;
- real module-status eligibility race: **NOT EXECUTABLE**;
- authenticated admin GET: **NOT EXECUTABLE**; and
- authenticated browser form submission: **NOT EXECUTABLE**.

Automated behavior/static coverage passed for the corresponding service contracts,
but it is not represented as execution of these unavailable staging subcases.

## Audit / Integrity

Final integrity findings were:

- false success events: 0
- unsafe metadata findings: 0
- duplicate brief versions: 0
- duplicate brief hashes: 0
- duplicate revision numbers: 0
- multiple mutable revisions: 0
- cross-site ancestry: 0
- constraints disabled: no

Final behavioral validation performed zero deployments, migrations, provider calls,
production access, repository writes, legacy imports, legacy generation, permission
or ownership changes, or M4B/M4C implementation. No temporary helper or persistent Git
configuration change remained.

## Final Clean Baseline

Cleanup restored every generic/import aggregate to zero:

```text
sites = 0
site_business_associations = 0
site_pages = 0
site_generation_briefs = 0
site_revisions = 0
site_revision_pages = 0
site_page_sections = 0
site_themes = 0
site_assets = 0
site_revision_assets = 0
site_approvals = 0
site_events = 0
legacy_site_mappings = 0
legacy_site_page_mappings = 0
```

The registry remained 16 definitions / 22 variants with zero verifier drift. The
authoritative legacy runtime remained unchanged at 6 generated websites / 37 pages.

## Source Safety

Final hashes proved the internal actor, business, business-module, module, and Shared
Business Profile/source rows unchanged. Temporary eligibility changes were restored
exactly, and no persistent source mutation remained.

## Remaining M4 Boundary

M4B is **NEXT / NOT STARTED** and owns the Composition Editor + Generic Admin Preview.
M4C is **NOT STARTED** and owns Review Submission + Internal Approval + final M4
validation. M5 retains customer preview, feedback, and customer approval.

M4 and Sprint 8.8 remain **IN PROGRESS**. The generic public runtime remains uncut,
and production remains **UNAUTHORIZED / NOT DEPLOYED**.

## Evidence Timeline

The evidence paths below are externally retained identifiers. This repository records
their verified workflow hashes without fabricating local copies.

1. **Deployment gate — PASS.** Deployed SHA
   `8805eeeae704f130ddda357e82c4dd936fde5b4c`; report
   `evidence/SPRINT-8.8-M4A-STAGING-DEPLOYMENT.md`; SHA-256
   `45725a494bf415ad3adec8b113ca2ae5a72155f9f44efbf27385f6ed8af2bffd`.
2. **Final real-MySQL validation — PASS.** Validated the same SHA; report
   `ubo-sprint-8.8-m4a-final-validation-20260903T030735Z/SPRINT-8.8-M4A-STAGING-FINAL-VALIDATION.md`;
   SHA-256
   `9fe38af06fa13c8196d0e106cc207aa80391c8bc7ae1ab53f403c4792f0b2de8`.
