# Sprint 8.8 M1 — Closeout

## Final Status

- M1: **COMPLETE / STAGING PASS**
- Validated SHA: `2a545a056f650122a3d9ccbf077f35cef83f6065`
- Migration: `023_website_platform_foundation.sql`
- Staging database: `ubo_staging`
- Sprint 8.8 overall: **IN PROGRESS**
- M2: **NEXT / NOT STARTED**
- Production: **UNAUTHORIZED / NOT DEPLOYED**

The authoritative completion evidence is the externally retained staging report
`ubo-sprint-8.8-m1-final-validation-20260901T001402Z/SPRINT-8.8-M1-STAGING-FINAL-VALIDATION.md`,
SHA-256 `db9dcf37aaac700b12604555f32c01d974c28a6a520c6bf1a8a28a97152f6daf`.

## Delivered Foundation

M1 delivered the dependency-safe 16-table generic website foundation in migration
023: generic sites and business associations, stable logical pages, generation
briefs, immutable revisions and composition, repository-owned component/variant seed
metadata, themes, assets and revision-asset usage, approval records, legacy
website/page mappings, and generic site events.

The foundation includes a bounded, idempotent legacy importer with deterministic
identity, source/import/revision hashing, quarantine and retry behavior,
compatibility comparison, and reconciliation reporting. Imported generic records
remain dormant snapshots. The legacy `SiteGenerator` runtime and its existing readers
remain authoritative.

## Compatibility Decisions Proven

- Native PDO prepares use distinct named placeholders.
- A pending legacy mapping is bound to its site and revision before page mappings are
  inserted; finalization verifies that same ownership tuple.
- Transaction rollback and durable quarantine behavior remain safe after early mapping
  bind and injected failures.
- Destination lengths are validated before writes so strict-MySQL width failures are
  quarantined safely rather than leaving partial aggregates.
- Legacy pages are read in their effective runtime order,
  `sort_order ASC, id ASC`, then imported with unique ordinal generic sort orders
  `10, 20, 30, ...`.
- Each imported page retains its raw legacy order in
  `presentation_json.legacy_sort_order`, along with its legacy page ID.
- Future `SiteGenerator` output assigns About and Contact after all service pages so
  new generated page orders remain unique.
- Asset evidence covers normalized path, byte hash, size, MIME/type, and usage.
- Same-site composite foreign keys reject cross-site revision, composition, approval,
  asset, restore, and mapping relationships.
- Identical content is allowed across different revision numbers, while revision
  numbers remain unique per site.
- Executable MySQL `CHECK` constraints reject invalid values.

## Final Validation Summary

### Automated validation

- PHP lint: **PASS**, 115 files
- Standalone suites: **18/18 PASS**
- Importer unit: **PASS**, 22 tests / 59 assertions
- Importer database: **PASS**, 48 assertions
- `SiteGenerator`: **PASS**, 5 tests / 25 assertions
- Migration contract: **PASS**, 122 assertions
- M1 scope/static: **PASS**, 40 assertions
- Pricing regressions: **PASS**

### Six-site import and compatibility

- Legacy websites/pages: 6 / 37
- Website IDs: 1, 2, 3, 4, 5, 6
- Business IDs: 3, 4, 1, 5, 6, 9
- Per-site page counts: 6, 6, 6, 6, 7, 6
- First pass: processed 6; imported 6; reconciled 0; quarantined 0;
  failed 0; retryable 0
- Page comparison: 37/37 matched; 0 content, order-sequence, or legacy-order
  provenance mismatches
- Generic duplicate page sort orders: 0
- Website 5 raw legacy order: `10,20,30,40,50,50,60`
- Website 5 generic order: `10,20,30,40,50,60,70`
- Website 5 raw-order provenance preserved: yes
- Themes: 6 compared; 0 mismatches
- Assets: 6 usages; 2 distinct files; 0 mismatches
- Hash reconciliation: source 6/6; import 6/6; revision 6/6
- Second pass: processed 6; imported 0; reconciled 6; quarantined 0;
  failed 0; retryable 0; duplicates created 0

### MySQL and legacy contracts

- Duplicate-content revisions allowed: yes
- Duplicate revision number rejected: yes
- Same-site foreign-key invalid relationships: 8/8 rejected
- Executable `CHECK` constraint cases: 7/7 rejected
- `information_schema`-only limitations: none
- Legacy website/page counts remained 6/37; hashes were unchanged; legacy mutations: 0
- Generic active sites, published revisions, and approvals: 0 / 0 / 0
- Production access/deployment: 0
- M2 implementation: 0

## Evidence Timeline

The staging validation reports below are external evidence reported by the completed
validation workflow. Some paths are not repository-local; this closeout records their
identifiers and verified SHA-256 values without fabricating repository copies.

1. **Implementation and migration — PASS.** PR #97 merged at
   `6dad891943f90d50f34776064a4a11d7b1376b47`. Migration 023 and the M1 schema,
   importer, seed metadata, reporting, and tests were deployed and applied to
   `ubo_staging`. Report:
   `evidence/SPRINT-8.8-M1-STAGING-DEPLOYMENT-MIGRATION.md`; SHA-256
   `ae13fd987cb17ff5541387133b39a3f1ba8460e18fde9ff5800a2bdafaa45a0b`.

2. **First behavioral validation — BLOCKED.** On the PR #97 baseline, the importer
   unit test attempted a test filesystem write under deployed `public/app`. No importer
   or database mutation occurred. Report:
   `ubo-sprint-8.8-m1-validation-20260831T003328Z/SPRINT-8.8-M1-STAGING-VALIDATION.md`;
   SHA-256 `27cd23009f372aa6b166284aa0b44cfd039b4cb044cfe17cd0609b101178932b`.
   PR #98 fixed the harness and merged at
   `e39921b778d704c7d306246a02c0afbf55ba2090`; code-only deployment **PASS** report
   `evidence/SPRINT-8.8-M1-VALIDATION-TEST-FIX-DEPLOYMENT.md`; SHA-256
   `bb03404bb36648738d9bff1bf0976ac652fd8db1c1a74f9ebd54f26bd5d71806`.

3. **Second behavioral validation — FAIL.** The scope test hit Git
   dubious-ownership protection because its direct diff lacked command-scoped
   `safe.directory`. No importer or database mutation occurred. Report:
   `ubo-sprint-8.8-m1-validation-20260831T005744Z/SPRINT-8.8-M1-STAGING-VALIDATION.md`;
   SHA-256 `b9b1145907cd76057c9d773397a88ed74d59ff1289994c2bca45b54df5b80775`.
   PR #99 fixed the harness and merged at
   `be8f3701bea488cc7a08f7bda888ae1e32a1b25f`; code-only deployment **PASS** report
   `evidence/SPRINT-8.8-M1-SCOPE-TEST-FIX-DEPLOYMENT.md`; SHA-256
   `bb2b194c9a89c46b7d4769c8f93a28c99ce7664c7a17109b14ecce8697dbdb9a`.

4. **First real-MySQL importer validation — FAIL.** On the PR #99 baseline, the first
   batch processed 2 and quarantined both with `database_failure`. Native PDO rejected
   a reused named placeholder, and page mappings preceded binding the parent mapping's
   site tuple. Report:
   `ubo-sprint-8.8-m1-validation-20260831T011622Z/SPRINT-8.8-M1-STAGING-VALIDATION.md`;
   SHA-256 `5b2e8778b25f2f4dfd51e820ebb05a572b624777d20d1fbe5668aa4588071627`.
   PR #100 fixed these contracts and merged at
   `6131585bece5b4c5a6ceac2a04ada119829d62fb`; deployment **PASS** report
   `evidence/SPRINT-8.8-M1-REAL-MYSQL-IMPORT-FIX-DEPLOYMENT.md`; SHA-256
   `bec5b7e69e1a35fe02944f107502559b15b7ee3c297db1dc1df40d0287a68a04`.
   The one-site MySQL smoke **PASS** report is
   `ubo-sprint-8.8-m1-one-site-smoke-20260831T014839Z/SPRINT-8.8-M1-ONE-SITE-MYSQL-SMOKE.md`;
   SHA-256 `93a4fb59fb577648fabdf00dbf0f5643a22143d5e7ab867ecc5b57bf5b2ea0bf`.

5. **Next full six-site validation — FAIL.** Five sites imported; website 5 was
   quarantined with `page_order_collision`. Four service pages produced raw order
   `10,20,30,40,50,50,60`, but the legacy runtime was deterministic by
   `(sort_order, id)`. Report:
   `ubo-sprint-8.8-m1-final-validation-20260831T020113Z/SPRINT-8.8-M1-STAGING-FINAL-VALIDATION.md`;
   SHA-256 `a1fe4c234de0c8d433db33c83d79bba79848e8afe610e57b9c2ac7244f303297`.
   PR #101 normalized imported order, retained raw-order provenance, and corrected
   future generation; it merged at `2a545a056f650122a3d9ccbf077f35cef83f6065`.

6. **First PR #101 deployment attempt — BLOCKED BEFORE DEPLOYMENT.** The restricted
   `ubo-deploy` identity attempted direct `git fetch origin --prune` and could not write
   `.git/FETCH_HEAD`; no environment change occurred. This was a deployment preflight
   mistake, not an application defect. Report:
   `evidence/SPRINT-8.8-M1-LEGACY-PAGE-ORDER-FIX-DEPLOYMENT.md`; SHA-256
   `1c053e3f069b114ae50b53c51420200d25fb0b501d1464d7ad1373c0fb94b98c`.
   The restricted preflight must use `git ls-remote`; repository-mutating Git belongs
   to `/usr/local/sbin/ubo-stage-deploy`. The retry deployment **PASS** report is
   `evidence/SPRINT-8.8-M1-LEGACY-PAGE-ORDER-FIX-DEPLOYMENT-RETRY.md`; SHA-256
   `000231dbbc976213f6667e2b4f51843f7b5347d3d02bd5c06f5774f7d99a8a0d`.

7. **Final full six-site validation — PASS.** SHA
   `2a545a056f650122a3d9ccbf077f35cef83f6065` passed all automated, import,
   compatibility, hash, idempotence, MySQL, legacy-immutability, publication-state,
   and cleanup gates. Report:
   `ubo-sprint-8.8-m1-final-validation-20260901T001402Z/SPRINT-8.8-M1-STAGING-FINAL-VALIDATION.md`;
   SHA-256 `db9dcf37aaac700b12604555f32c01d974c28a6a520c6bf1a8a28a97152f6daf`.

## Final Clean Baseline

Cleanup restored the generic staging baseline to:

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
legacy_site_mappings = 0
legacy_site_page_mappings = 0
site_events = 0

component_definitions = 1
component_variants = 4

legacy websites = 6
legacy pages = 37
```

## Remaining Boundary

- M1 does not make generic sites live.
- M1 does not publish generic revisions.
- M1 does not replace `SiteGenerator` legacy reads.
- M1 does not implement M2 lifecycle, revision, or approval services.
- M1 does not authorize production access or deployment.
- Sprint 8.8 remains **IN PROGRESS**; M2 is **NEXT / NOT STARTED**.
