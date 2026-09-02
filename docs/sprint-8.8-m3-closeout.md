# Sprint 8.8 M3 — Closeout

## Final Status

Sprint 8.8 M3 — Component Registry + Composition — is **COMPLETE / STAGING
PASS** on final deployed and validated SHA
`a431f6fc06e24f2252a9a282954d5541551c9000`.

- M1: **COMPLETE / STAGING PASS**
- M2: **COMPLETE / STAGING PASS**
- M3: **COMPLETE / STAGING PASS**
- M4: **NEXT / NOT STARTED**
- Sprint 8.8: **IN PROGRESS**
- Production: **UNAUTHORIZED / NOT DEPLOYED**

M3 completion does not complete Sprint 8.8, activate the generic website runtime, or
establish 247SP production or first-customer readiness.

## Delivered Scope

M3 delivered the versioned repository-backed component registry, repository-owned
configuration schemas and rendering, placement/cardinality enforcement, theme/layout
composition, existing asset references and rights checks, atomic full-revision
composition replacement, stable logical-page reuse, deterministic
section/page/theme/revision hashing, stale-writer protection, editor and validated
render reads, M2 review-gate composition validation, historical exact-version
rendering, restored M1 legacy compatibility, and migration 024 registry versioning.

It did not deliver admin or customer composition UI, browser composition routes,
asset upload/finalization, public preview cutover, build jobs, deployment/publishing,
production activation, domains/routing, public LeadHub ingestion, conversion,
provider integrations, or legacy runtime retirement.

## Implementation And Review History

- Initial implementation commit:
  `49ee1081a01e5eb2d1bd687a3c144bb2995a96b7`
- First review correction:
  `95ac2a9d79600c6ac4a234fa44239b330d49d546`
- Historical rendering correction:
  `52aa0f113fc32d578d5e66ba7cc61ffbeabac591`
- PR #105 merge:
  `e17f9fc353ee7a086b674db1966f26823f3e4da7`
- Cross-platform migration-023 integrity-test hotfix:
  `4a0bed50ee19fd58ad7bc3f20dcc40b93b92848e`
- PR #106 merge and final deployed/validated SHA:
  `a431f6fc06e24f2252a9a282954d5541551c9000`

The implementation branch was `codex/sprint-8.8-m3-component-composition`. PR #105
delivered M3; PR #106 corrected only the cross-platform test guard.

## Migration 024 And Registry State

`024_component_registry_versioning.sql` was applied to staging successfully exactly
once during the initial deployment at SHA
`e17f9fc353ee7a086b674db1966f26823f3e4da7`. It replaced component-key-only
uniqueness with immutable `component_key + implementation_version`, preserved the
existing legacy IDs, and seeded 15 authored definitions and 18 authored variants.

Final registry state:

- `component_definitions = 16`
- `component_variants = 22`
- verifier drift issues: 0
- legacy definition `legacy_247sp_page@legacy-preview-v1`: ID 1
- legacy variants: home 1, service 2, about 3, contact 4

Migration 023 remained unchanged in canonical repository content. Migration 024 was
not rerun during the code-only retry, and migration 025+ remains absent.

## Initial Deployment Attempt And Hash-Guard Correction

The first M3 staging deployment and migration succeeded. Migration 024 ran once, the
registry reached 16 definitions / 22 variants, legacy IDs were preserved, and the
registry verifier reported zero drift. The overall automated gate was reported FAIL
only because `WebsitePlatformFoundationMigrationTest` stopped on a platform-dependent
migration-023 integrity assertion.

PR #105's tests had hashed raw checkout bytes. Git stored migration 023 with LF, while
the Windows checkout used CRLF through `core.autocrlf=true`. The SQL and Git blob were
unchanged; only checkout line-ending representation differed. PR #106 changed both
integrity tests to normalize CRLF/CR to canonical LF and compare against fixed SHA-256
`f0912bafc947eab8cc5b2dd5d534466d6b3675f991cac2f6849b1f84819db302`.

Historical migration-023 Git blob
`813716544457ca0b36a85489c3176f8cb212dbff` is identical at:

- `31d5f64ba6fdf9005fe839c9d3bae4e996ce3bd4`
- `4235f5a622b106f3e7983f670e46882002168e92`
- `e17f9fc353ee7a086b674db1966f26823f3e4da7`

This was a test defect, not a migration or schema defect. No database repair or
migration rerun was required.

## Deployment Retry

The code-only retry deployed SHA
`a431f6fc06e24f2252a9a282954d5541551c9000` with zero migration executions.

- registry: 16 definitions / 22 variants / zero drift
- PHP lint: 141 files PASS
- Accounts smoke: HTTP 200 after one redirect
- App smoke: HTTP 200 after two redirects
- 5xx responses: 0
- standalone suites: 32/32 PASS
- M3 focused assertions: registry 26, configuration 42, composition validator 29,
  service behavior 27, review gate 84, renderer 34, database 59, migration 46, scope
  30, legacy hash compatibility 13
- M1, M2, and pricing regressions: PASS

## Final Real-MySQL Validation

Final validation ran against deployed SHA
`a431f6fc06e24f2252a9a282954d5541551c9000` on `ubo-stage-app` using validation
identity `codex-validation`, database `ubo_staging`, MySQL 8.4.8, and native PDO
prepares (`ATTR_EMULATE_PREPARES=false`).

Exact final result: **SPRINT 8.8 M3 STAGING FINAL VALIDATION: PASS**.

## Composition And Hashing Evidence

Actual deployed services created a temporary internal-demo site and revision.
`SiteCompositionManager::replaceDraftComposition()` passed, and the stored revision
hash matched `SiteRevisionSnapshotHasher::hashStoredRevision()`. Editor read,
validated render read, top-level rendering, escaping, the inert lead-form preview, and
the safe relative future POST action passed. No public endpoint was created.

Stable logical-page ID reuse, omitted-page retention in `site_pages`, and
cross-revision stable-page reuse passed. Revision composition continued to contain
only submitted revision pages.

Changing a repository variant changed rendered output, section hash, page hash, and
revision hash.

## Concurrency And Rollback Evidence

Two actual concurrent writers used the same expected snapshot hash. Exactly one
succeeded and one returned `stale_write`; no raw PDO error or deadlock escaped. The
winning composition stored cleanly. The losing writer left no partial rows and no
false success event.

The real transaction rollback companion restored revision rows, retained the original
snapshot, and left no false event. The deployed service-level failure-after-delete
rollback suite also passed.

## Review-Gate Evidence

Actual `markReadyForReview()` rejected configuration/schema, section-hash,
page-hash, theme-hash, revision-hash, and placement/cardinality tampering. After exact
restoration, the valid transition reached `ready_for_review`. Post-review composition
mutation was rejected as immutable, while validated read and rendering continued to
pass.

## Historical Versioning Evidence

A reviewed stored revision using a temporarily inactive exact component version
continued to pass validated render read and rendering. An ordinary draft using the
inactive version was rejected by `markReadyForReview()`. No silent version upgrade
occurred. Component and variant statuses were restored, after which the registry
verifier again passed with zero drift.

## Asset And Tenant Evidence

A real same-site valid customer asset passed. Wrong-business, cross-site,
unknown-rights authored, prohibited, and expired licensed assets were rejected.
Composition replacement did not modify or delete `site_assets`. The pricing-list PDF
fixture accepted a valid PDF and rejected the wrong MIME type.

BUSINESS A's customer Owner/Admin could read its composition; BUSINESS B's customer
was denied cross-tenant access; Internal Super Admin access passed. No PII is retained
in this closeout.

## Legacy Compatibility Evidence

One legacy importer execution completed as `imported`.
`compareWebsite()` reported matching source, import, and revision hash. The original
dormant imported baseline correctly remained ineligible for M3 validated rendering
because it had no restore provenance.

A documented test-fixture promotion changed only the imported generic source
revision's lifecycle so the existing M2 restore service could exercise its actual
clone path. `SiteRevisionManager::createRestoreCandidate()` passed with correct
`restored_from_revision_id`; pages, sections, and theme were copied; `site_assets`
were reused; revision asset references were remapped; and the source composition was
unchanged.

The restored legacy revision passed `markReadyForReview()`,
`validatedCompositionForActor()`, and `SiteCompositionRenderer`. Its validated model
reported `historical=true` and `legacy_compatibility=true`, and meaningful escaped
legacy content rendered.

## Known Validation Limitation

The selected real legacy source contained zero asset references. Therefore the **real
legacy unknown-rights asset subcase was NOT EXECUTABLE**. This is not recorded as a
real-data PASS. It is non-blocking because the actual import/restore/review/render path
passed, normal authored asset-rights boundaries passed in real MySQL, and the deployed
automated legacy unknown-rights compatibility tests passed.

## Audit / Integrity

- false success events: 0
- unsafe metadata findings: 0
- duplicate revisions/pages/sections: 0
- cross-site mismatches: 0
- asset target mismatches: 0
- constraints disabled: no
- final-validation deployments: 0
- final-validation migrations: 0
- provider calls: 0
- production access: 0
- repository writes: 0
- temporary helpers remaining: 0
- permission or ownership changes: 0
- persistent Git configuration changes: 0
- M4 begun: no

## Final Clean Baseline

Cleanup restored these tables to zero rows:

- `sites`
- `site_business_associations`
- `site_pages`
- `site_generation_briefs`
- `site_revisions`
- `site_revision_pages`
- `site_page_sections`
- `site_themes`
- `site_assets`
- `site_revision_assets`
- `site_approvals`
- `site_events`
- `legacy_site_mappings`
- `legacy_site_page_mappings`

The final registry remained 16 definitions / 22 variants. The authoritative legacy
runtime remained 6 generated websites / 37 generated pages. The final registry
verifier passed with zero drift. Internal, BUSINESS A, BUSINESS B, and legacy source
hashes were unchanged, and the repository was clean.

## Evidence Timeline

1. Initial deployment and migration attempt:
   `evidence/SPRINT-8.8-M3-STAGING-DEPLOYMENT-MIGRATION.md`
   — SHA-256
   `0172bd2329a43e3c9665362ad38e58e082d1ca58b82e69cb15b62d092f2d8d28`
   — overall gate FAIL because the automated test stopped on the platform-dependent
   hash guard; deployment, migration 024, and registry reconciliation PASS.
2. Code-only retry:
   `evidence/SPRINT-8.8-M3-STAGING-DEPLOYMENT-RETRY.md`
   — SHA-256
   `7e65858b13fffb2528a93918a917f9868fc89fecebb2b52910f47b397c5b705a`
   — PASS.
3. Final real-MySQL behavioral validation:
   `ubo-sprint-8.8-m3-final-validation-20260902T221631Z/SPRINT-8.8-M3-STAGING-FINAL-VALIDATION.md`
   — SHA-256
   `5fdfd9ca6b2118ad82b23b97e81a651990b47f9a7140d62a5cef0e857038df70`
   — PASS.

These are evidence references and hashes; external evidence files are not recreated
in this repository.

## Remaining Boundary

M4 is **NEXT / NOT STARTED**. Sprint 8.8 remains **IN PROGRESS**. The legacy website
runtime remains authoritative. Production is **UNAUTHORIZED / NOT DEPLOYED**. Later
milestones own browser UI, customer workflow, uploads, build/deployment, public
runtime cutover, routing, ingestion, conversion, providers, and production activation.
