# Sprint 8.8 M4B — Closeout

## Final Status

Sprint 8.8 M4B — Composition Editor + Generic Admin Preview — is **COMPLETE /
STAGING PASS / FORMALLY CLOSED** on merged, deployed, and validated SHA
`557cc34fe4cf3ab56cdcb59fd7c623c495fd8eaf`.

| Milestone | Status at M4B closeout |
| --- | --- |
| M1 | COMPLETE / STAGING PASS / FORMALLY CLOSED |
| M2 | COMPLETE / STAGING PASS / FORMALLY CLOSED |
| M3 | COMPLETE / STAGING PASS / FORMALLY CLOSED |
| M4A | COMPLETE / STAGING PASS / FORMALLY CLOSED |
| M4B | COMPLETE / STAGING PASS / FORMALLY CLOSED |
| M4C | NEXT / NOT STARTED |
| M4 overall | IN PROGRESS |
| Sprint 8.8 | IN PROGRESS |
| Production | UNAUTHORIZED / NOT DEPLOYED |

This documentation-only closeout records authoritative user-supplied deployment and
final-validation evidence. It does not rerun those gates or establish production
readiness, generic runtime cutover, or completion of M4 or Sprint 8.8. External report
paths and checksums below are supplied references; no report copies were fabricated.

## Delivered Scope

M4B delivered the structured internal Site Platform composition editor and generic
admin preview, with Site Platform detail links into:

- `/app/admin/site-composer.php`;
- `/app/admin/site-preview.php`.

Both require Internal Admin/Super Admin authorization. Composer mutations use dedicated
`admin-site-platform` CSRF, rotate the token after successful mutation, and return
HTTP 303 post/redirect/get. Internal preview is GET-only.

The editor provides a repository-driven authoring catalog and repository-schema-driven
structured forms, with no raw JSON editor. It supports page and section add, update,
remove, and reorder; component variant selection; structured theme/layout editing;
existing eligible site-asset selection; explicit **Initialize New Composition** and
**Start From Based-On Revision** actions.

Each operation carries the exact `expected_snapshot_hash` and converges on one atomic
`SiteCompositionManager::replaceDraftComposition()` boundary. `stale_write` is
preserved without silent retry. Preview consumes `validatedCompositionForActor()`
and fixed repository rendering through `SiteCompositionRenderer`, keeps lead forms
inert, and fails closed on invalid composition.

M3 remains authoritative for component identity, variants, schemas,
placement/cardinality, asset validation, canonical hashes, transactions, locking,
stale writes, and rendering validation. The detailed architecture remains in
`docs/sprint-8.8-m4-service-contract.md`.

## Explicit Non-Scope

M4B did not deliver customer preview, customer feedback/change requests, customer
approval, materiality workflow UI, mark-ready-for-review UI, internal approval
request/decision UI, publishing, public generic website runtime, a build/deployment
engine, domain/routing, LeadHub public ingestion, asset upload, provider integration,
legacy runtime replacement, or production activation.

M4C owns Review Submission + Internal Approval + final M4 workflow validation.
M5 owns customer preview, feedback/change requests, and customer approval.

## Implementation / Merge History

| Event | Commit |
| --- | --- |
| Original M4B implementation | `d3f0cd34397d3451921d669b29a15a2a0c4b46d4` |
| Documentation correction recording active 247SP marketing preview | `a2e1917f76a32fa4cfe99b7b7347e69a0ecdff5d` |
| PR #110 merge; final deployed/validated M4B SHA | `557cc34fe4cf3ab56cdcb59fd7c623c495fd8eaf` |

[PR #110 — Sprint 8.8 M4B: add composition editor and admin preview](https://github.com/fvd8383/ultimate-back-office/pull/110)
is merged. The initial marketing preview Apache configuration was separate,
staging-only administrator work. That initial publication required no M4B deployment;
the subsequent M4B application deployment and final validation are distinct events.

## Schema / Migration Boundary

M4B required **NO MIGRATION**. Migration 023 and migration 024 remained unchanged;
migration 025+ remained absent.

| Check | Recorded result |
| --- | --- |
| Deployment migration-wrapper invocations | 0 |
| Deployment migration 023 executions | 0 |
| Deployment migration 024 executions | 0 |
| Final-validation deployments | 0 |
| Final-validation migration executions | 0 |
| Final registry definitions / variants | 16 / 22 |
| Registry drift | 0 |
| Legacy websites / pages | 6 / 37 |

## Staging Deployment Gate

**PASS** on `ubo-stage-app` as `ubo-deploy`. The deployed application advanced from
`8805eeeae704f130ddda357e82c4dd936fde5b4c` to
`557cc34fe4cf3ab56cdcb59fd7c623c495fd8eaf`; remote main matched the final SHA.

| Deployment check | Result |
| --- | --- |
| Deploy wrapper | 1 invocation; exit 0 |
| Migration wrapper | 0 invocations |
| PHP lint | 161 files PASS |
| Standalone suites | 39/39 PASS |
| WebsitePlatformM4BBehaviorTest | 170 assertions PASS |
| WebsitePlatformM4BViewTest | 37 assertions PASS |
| WebsitePlatformM4BScopeTest | 59 assertions PASS |
| M1 / M2 / M3 / M4A / pricing regression | PASS |
| Unauthenticated new routes | Normal authentication redirects; no 5xx |

Deployment validation did not claim authenticated staging browser behavior.

Deployment evidence: `evidence/SPRINT-8.8-M4B-STAGING-DEPLOYMENT.md`.
Recorded SHA-256:
`fd0709031cec52c61df2b435dc16a9153ae53c855e89824b8d842f9608a038c8`.

## Final Real-MySQL Validation

**SPRINT 8.8 M4B STAGING FINAL VALIDATION: PASS**.

Validation ran on `ubo-stage-app` as `codex-validation`, using MySQL **8.4.8** and
PDO **native prepares, emulation disabled**. Deployed SHA and remote main both were
`557cc34fe4cf3ab56cdcb59fd7c623c495fd8eaf`; the working tree was clean.

Final report:
`ubo-sprint-8.8-m4b-final-validation-20260904T010109Z/SPRINT-8.8-M4B-STAGING-FINAL-VALIDATION.md`.
Recorded SHA-256:
`dedc82f80c61f5aadb164b2c092093368cd67477ca64421c8b54057e0355ddb8`.

The sections below distinguish actual real-MySQL/service results from deployed
behavior-suite coverage and cases that were not executable.

## Editor / Initialization Evidence

Real-MySQL validation passed for the Internal Admin workspace and a second Internal
Admin workspace. A real non-admin was rejected, and the second-tenant ownership
boundary was enforced. Editor GET/read service paths caused zero mutation; this is
not an authenticated browser-route claim. Explicit `initialize_new` passed, and a
second initialization was rejected.

## Page Evidence

Real-MySQL page add, update, move, and remove passed. Duplicate page keys, duplicate
slugs, and invalid page types were rejected. The purpose/entry invariant was enforced.

## Section Evidence

Real-MySQL section add, update, variant selection, move, and remove passed. Placement
and cardinality enforcement passed. Duplicate section keys and legacy authoring were
rejected.

## Theme Evidence

Real-MySQL `local_service@1`, color validation, typography, and layout editing passed;
legacy authoring was rejected. Invalid layout variant rejection is covered by the
deployed behavior suite, not claimed as a separately executed real-MySQL case.

## Concurrency Evidence

Two real MySQL writers targeted the same mutable revision with the initial shared hash
`635b9e2f24b4eb307ab7d8447cc7e3339976a149ff8ad93e7aa94dcb9a378097`.
The result was **one success and one `stale_write`**. The loser produced **0 partial
writes** and **0 false success events**. This was actual real-MySQL concurrency,
not simulated concurrency.

## Rollback Evidence

Real MySQL transaction rollback **PASS**: rows and the revision hash were restored,
false success events were **0**, and no partial composition remained.

## Based-On Evidence

Real same-site immutable-source based-on initialization **PASS**:

- source composition remained unchanged;
- the target was independently canonicalized;
- exact authorable versions were preserved;
- source row IDs were not copied as target identity;
- the target snapshot was independently computed.

Cross-site, mutable, and empty source rejection are covered by the deployed behavior
suite. The real inactive/non-authorable historical exact component-version case was
**NOT EXECUTABLE**, because it would have required prohibited mutation of shared
component registry state. It is a nonblocking limitation: deployed behavior contracts
cover exact-version rejection without unsafe shared-registry mutation. This real case
is not labeled PASS.

## Asset Evidence

Real-MySQL catalog filtering, valid same-site image selection, and valid same-site PDF
selection **PASS**. Wrong-site, wrong-business, prohibited-rights, unknown
normal-authoring-rights, expired, wrong-MIME, missing-required-asset, and duplicate-usage
references were rejected. The invalid/expired existing-asset repair path **PASS**.
Failed asset writes left no partial rows and no false success event.

## Preview Evidence

Real service validation passed for empty and composed preview,
`validatedCompositionForActor()`, `SiteCompositionRenderer`, escaping, inert lead
forms, omission of unresolved media, and zero mutation on preview reads. Tampered
stored composition **FAILS CLOSED / PASS**: raw invalid composition was not rendered.
These are real service results, not authenticated browser GET/form results.

## Authorization / Route Evidence

Real service authorization passed for Internal Admin and a second Internal Admin.
Non-admin and cross-tenant access were rejected. Deployed source/route contract suites
passed; deployed unauthenticated routes returned normal authentication redirects with
no 5xx.

Authenticated browser route validation was **NOT EXECUTABLE** because no approved
safe staging session mechanism existed. No authenticated browser GET/form PASS is
claimed. This is nonblocking given real service authorization, route source/contract
suites, and deployed unauthenticated HTTP coverage. No unsafe cookie/session forging
was performed.

## Known Validation Limitations

| Case | Actual status | Reason and nonblocking basis |
| --- | --- | --- |
| Real inactive/non-authorable historical exact-version based-on case | NOT EXECUTABLE | Prohibited shared-registry mutation would be required; deployed behavior contracts cover exact-version rejection. |
| Authenticated browser route validation | NOT EXECUTABLE | No approved safe staging session mechanism; real service authorization, source/route contracts, and deployed unauthenticated HTTP passed without forging sessions. |
| Separate marketing viewport, console, and interaction QA | NOT YET RECORDED | HTTP publication/regression evidence is retained; browser QA remains pending and does not block M4B closeout. |

Neither NOT EXECUTABLE case is a PASS claim. Marketing browser QA is a separate gap
from M4B authenticated-route validation. Invalid layout variant and cross-site/mutable/
empty based-on rejection are attributed to deployed behavior-suite coverage.

## Audit / Integrity

| Final audit check | Result |
| --- | --- |
| False success events | 0 |
| Unsafe metadata | 0 |
| Duplicate revision numbers | 0 |
| Multiple mutable revisions | 0 |
| Duplicate page keys/orders | 0 |
| Duplicate section keys/orders | 0 |
| Cross-site ancestry | 0 |
| Cross-site ownership/asset target violations | 0 |
| Orphan revision assets | 0 |
| Constraints enabled | Yes |

## Final Clean Baseline

Final generic validation rows and temporary test fixtures were **0**. The component
registry remained **16 definitions / 22 variants**, with **0 drift**. Legacy records
remained **6 websites / 37 pages**. Source restoration was complete, the working tree
was clean, and deployed SHA remained
`557cc34fe4cf3ab56cdcb59fd7c623c495fd8eaf`.

## Marketing Preview Regression

The separate existing `public/marketing` property remains **PASS / ACTIVE** at
[the marketing staging preview](https://staging-app.ultimatebackoffice.com/marketing/).
It is separate from Site Platform and its dormant generic customer sites.

M4B deployment and final validation verified these HTTP regression results:

| Resource | Result |
| --- | --- |
| `/marketing/` | 200 |
| `/marketing/privacy.php` | 200 |
| `/marketing/terms.php` | 200 |
| `/marketing/contact.php` | 200 |
| `/assets/css/marketing.css` | 200 |
| `/assets/js/marketing.js` | 200 |
| `/assets/brand/247sp-logo.svg` | 200 |
| `/assets/brand/favicon.svg` | 200 |
| `/assets/img/og-247sp.png` | 200 |
| `/marketing` | 302 to `/marketing/` |
| `X-Robots-Tag` | `noindex, nofollow` |

Browser QA at **1440px, 768px, 390px, and 320px**, plus console and interactions,
remains **NOT YET RECORDED**. Marketing launch placeholders remain pending. Neither
gap blocks M4B closeout. The initial Apache preview setup was separate staging-only
administrator configuration and required no M4B deployment at that time; it was not
part of the subsequent M4B application deployment or final validation. See
`docs/247sp-marketing-staging-preview.md` for that historical publication record.

## Safety Boundary

Final M4B validation recorded:

| Action | Count |
| --- | --- |
| Deployments | 0 |
| Migrations | 0 |
| Provider calls | 0 |
| Production access | 0 |
| Apache changes | 0 |
| Repository writes | 0 |
| Permission changes | 0 |
| Ownership changes | 0 |
| Sudoers changes | 0 |
| Persistent Git configuration changes | 0 |
| M4C implementation | 0 |

The earlier deployment's single deploy-wrapper invocation is recorded separately.
This local closeout changes documentation only, with no application, test, migration,
schema, or provider changes and no staging/production access or deployment.

## Remaining M4 Boundary

M4C is **NEXT / NOT STARTED** and owns Review Submission + Internal Approval + final
M4 workflow validation, including materiality and mark-ready/review/approval UI. M5
owns customer preview/feedback/customer approval. Publishing, public generic runtime,
build/deployment, domain/routing, public LeadHub ingestion, uploads, provider work,
legacy replacement, and production activation remain outside M4B.

M4 and Sprint 8.8 remain **IN PROGRESS**. Production remains **UNAUTHORIZED / NOT
DEPLOYED**. This closeout does not begin M4C.

## Evidence Timeline

1. M4B implementation was recorded at `d3f0cd34397d3451921d669b29a15a2a0c4b46d4`.
2. Separate staging-only administrator configuration published the marketing preview
   while the application remained at M4A SHA
   `8805eeeae704f130ddda357e82c4dd936fde5b4c`; no M4B deployment was required for that
   publication. Documentation correction
   `a2e1917f76a32fa4cfe99b7b7347e69a0ecdff5d` recorded its active state.
3. PR #110 merged at `557cc34fe4cf3ab56cdcb59fd7c623c495fd8eaf`.
4. The M4B staging deployment gate passed on that merge SHA. The deployment report
   path and checksum are recorded in Staging Deployment Gate above.
5. Final real-MySQL validation passed on the same SHA, with no deployment or migration
   during validation. Its report identifier is timestamped `20260904T010109Z`; the
   supplied path and checksum are recorded in Final Real-MySQL Validation above.
6. This documentation-only closeout records those gates and their limitations,
   formally closes M4B, and leaves M4C next/not started. It adds no new staging test
   results and does not copy or regenerate the external reports.
