# Sprint 8.8 M2 — Closeout

## Final Status

M2 is **COMPLETE / STAGING PASS**. The merged, deployed, and validated SHA is
`31d5f64ba6fdf9005fe839c9d3bae4e996ce3bd4` (PR #103). Migration
`023_website_platform_foundation.sql` was already applied by M1; M2 added no migration,
and migration 024+ remained absent. M1 is **COMPLETE / STAGING PASS**, M3 is
**NEXT / NOT STARTED**, and Sprint 8.8 remains **IN PROGRESS**. Production is
**UNAUTHORIZED / NOT DEPLOYED**.

## Delivered Services

- `SiteAuthorizationPolicy` enforces active actor, internal-role, customer membership,
  active business/module, tenant, and association authorization.
- `SiteManager` owns site identity, associations, reads, lifecycle transitions, the
  one-active-customer-associated-247SP-site rule, and `lock_version` stale writes.
- `SiteRevisionManager` owns concurrent revision allocation, revision lifecycle,
  write-once materiality, review immutability, composition mutability assertions,
  material-successor invalidation, and restore candidates.
- `SiteApprovalManager` owns idempotent customer/internal approval requests, decisions,
  revocation, supersession, inherited customer baselines, and approval reads.
- `SiteServiceSupport` owns safe input/exception handling, short local transactions,
  correlation identifiers, and transaction-coupled `site_events`.

The boundary includes row locking and concurrency, tenant-safe authorization, site and
revision lifecycle, review readiness, customer/internal approval, restoration, and
safe audit events. It adds no routes, UI, providers, publication, or deployment.

## Final Invariants

- A first non-material revision cannot bypass customer approval. Non-material internal
  review requires one still-current approved customer decision from an earlier
  same-site revision.
- A new material successor supersedes older current approved/requested customer
  workflows and dependent requested internal workflows. Stale decisions are rejected.
- Material-successor invalidation resets pre-publication `approved`,
  `pending_customer`, and `pending_internal_review` site state to `draft` in the same
  transaction. Suspension remains safety-dominant, and later-gated operation is not
  downgraded.
- A customer may withdraw approval until publication. Withdrawal revokes the customer
  approval, supersedes dependent internal approval, returns the revision to
  `ready_for_review`, and normally returns the site to `pending_customer`.
- Archive is operationally terminal for M2 mutations while authorized historical reads
  remain available.
- Activation, publication, production/conversion approval, cancellation, conversion,
  and deployment remain future-gated.
- Restore never changes source history. It creates a new material unpublished revision,
  copies pages/sections/theme, reuses site assets, and remaps revision asset references.
- Future M3 composition writers must call `lockMutableRevisionForComposition()` in the
  same transaction as their writes.

## Final Validation Summary

PHP lint passed. All 22 standalone suites passed. Focused results were:

- M2 service: 103 assertions passed;
- M2 database contract: 69 assertions passed;
- M2 scope: 23 assertions passed;
- M2 approval invariants: 22 tests / 95 assertions passed;
- M1 regressions: passed;
- pricing regressions: passed.

The final real-MySQL gate passed without disabling foreign-key or `CHECK` constraints.
Required `site_event` types were observed, unsafe audit metadata was zero, actor
semantics were preserved, and no raw PDO/deadlock error escaped the service boundary.

## Concurrency And Rollback

- Site creation: two concurrent attempts for one customer business produced one
  success, one conflict, and zero duplicate sites. A third ordinary attempt conflicted.
- Revision creation: two concurrent attempts both succeeded with revision numbers 1
  and 2 and zero duplicates.
- Approval request: two concurrent attempts returned the same one unique requested row
  and approval ID; no duplicate open approval was created.
- Customer approval decision: two concurrent decisions produced one success and one
  conflict; the final approval was approved.
- A stale `lock_version` lifecycle write was rejected. Entering `active`,
  `cancellation_pending`, or `conversion_pending`, requesting production/conversion
  approval, and publishing a revision returned `future_gate_required`.
- Fault injection after an intermediate approval insert caused full rollback when the
  following lifecycle transition failed. Approval and `site_event` counts were
  unchanged, and false success events were zero.

## Approval, Restore, And Lifecycle Behavior

The initial non-material revision's internal request returned `invalid_transition` and
created zero approvals. A material revision then passed customer and internal approval,
leaving the site administratively `approved`; this did not mean active, published,
deployed, domain-ready, or production-ready.

A later non-material revision preserved the still-current earlier customer approval,
passed internal request and decision without a new customer approval, and returned the
site to `approved`. A later material successor superseded the older customer approval,
changed pre-publication site state from `approved` to `draft`, and prevented the older
non-material revision from satisfying the approval gate. Older requested customer and
dependent internal workflows were superseded, and later decisions returned
`invalid_transition`.

Real restore validation left the source revision unchanged, created a new revision with
the correct `restored_from`, copied pages, sections, and theme, reused the `site_asset`,
remapped revision-asset references, and did not publish. As a material successor it
invalidated prior approval and changed the site from `approved` to `draft`.

Customer withdrawal after internal approval but before publication passed: customer
approval became revoked, dependent internal approval became superseded, the revision
returned to `ready_for_review`, the site became `pending_customer`, and no publication
occurred. While suspended, approval request/decision/revocation workflows did not
resume the site or change `lock_version`; an explicit Internal Admin/Super Admin resume
passed and incremented `lock_version` exactly once. After archive, revision creation,
restore, approval request, and approval revoke were rejected, while historical
authorized reads remained available.

## Tenant And Authorization

Validation used existing staging records for an internal Super Admin and Owner actors
from two distinct active 247SP customer businesses. Names, emails, and staging IDs are
not retained here. Own-tenant reads passed for both customers, cross-tenant reads were
unauthorized in both directions, internal Super Admin reads passed, active association
checks passed, and real Owner approval passed.

The optional real lower-customer-role denial test was **NOT EXECUTABLE** because no safe
fixture existed for the selected staging businesses. This is a non-blocking validation
limitation, not a PASS claim. Deployed standalone authorization coverage, real Owner
approval, real cross-tenant denial, and real Internal/Super Admin coverage passed.

## Integrity, Audit, And Source Safety

Service-created data had zero cross-site ownership violations, duplicate revision
numbers, duplicate current approvals, and asset page/section mismatches. Mutation and
success audit shared a transaction; rollback produced zero false-success events.
Required event types were present and unsafe metadata was zero.

Final hashes proved that business records, user records, business memberships, module
records, internal roles, legacy websites, and legacy pages were unchanged. There were
no source, authorization, or legacy mutations.

## Evidence Timeline

- Base `2feeb181594f9eb320c26dd117cffd0714780fe6`.
- Initial implementation `b58595da59d0c64afbf86f296a08e26b84f0d3ce` added
  `SiteAuthorizationPolicy`, `SiteManager`, `SiteRevisionManager`,
  `SiteApprovalManager`, and `SiteServiceSupport`, with no migration 024 and no UI or
  routes.
- Review correction `a845eaf1a5cf715b89cd57bf72c5f9ccfe3df734` (`fix: harden
  M2 approval lifecycle invariants`) added the inherited approved baseline rule,
  initial non-material protection, material-successor workflow invalidation, stale
  decision rejection, customer withdrawal, dependent internal invalidation,
  suspension dominance, terminal archive, and the locked M3 mutability contract.
- Review correction `ca00938008e5f338a6d2df8a9e99a8f17a02ee14` (`fix: invalidate
  stale M2 site approval state`) reset invalidated pre-publication approval states to
  `draft`, preserved suspension, did not reset for non-material classification, did
  not downgrade later-gated operation, and applied the rule to restore.
- PR #103 merged as `31d5f64ba6fdf9005fe839c9d3bae4e996ce3bd4`, the authoritative
  deployed and validated M2 SHA.

The code-only deployment passed with zero migration executions. The deployment report
path is `evidence/SPRINT-8.8-M2-STAGING-DEPLOYMENT.md`, SHA-256
`39aca5d5c2df8e9acd633c1d6f4e6d9709f66c3f813a18133a3d48778943e85d`.

The authoritative external real-MySQL evidence is
`ubo-sprint-8.8-m2-final-validation-20260902T024225Z/SPRINT-8.8-M2-STAGING-FINAL-VALIDATION.md`,
SHA-256 `fa4c3f10796ee0f9c0a9dbf69bbc7d2cbaaa82036cbdbdc0839b5c415314e824`.
The external path and checksum are recorded as evidence; no repository copy is
fabricated.

## Final Clean Baseline

Cleanup restored these generic customer/test tables to zero:

- `sites`, `site_business_associations`, `site_pages`, `site_generation_briefs`;
- `site_revisions`, `site_revision_pages`, `site_page_sections`, `site_themes`;
- `site_assets`, `site_revision_assets`, `site_approvals`, `site_events`;
- `legacy_site_mappings`, `legacy_site_page_mappings`.

Required repository seeds remained: `component_definitions = 1` and
`component_variants = 4`. Legacy data remained: 6 websites and 37 pages.

## Remaining Boundary

M2 does not provide component composition, CMS/admin/customer routes, publication,
activation, deployment, domain/routing management, or legacy runtime replacement. It
does not authorize production. M3 — Component Registry + Composition — is
**NEXT / NOT STARTED**.
