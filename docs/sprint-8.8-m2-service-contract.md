# Sprint 8.8 M2 — Site Lifecycle Service Contract

## Status

M2 is **IMPLEMENTED LOCALLY / REVIEW REQUIRED / STAGING VALIDATION PENDING** on branch
`codex/sprint-8.8-m2-site-lifecycle-services`. M1 remains **COMPLETE / STAGING PASS**.
Sprint 8.8 remains **IN PROGRESS**. This implementation is service/domain logic only;
it has not been deployed or validated against staging and is not production-authorized.

## Service Boundary

- `SiteAuthorizationPolicy` derives active customer, Internal Admin, and Super Admin
  authority from database roles and tenant relationships.
- `SiteManager` owns site creation, site reads, association reads, lifecycle transitions,
  and `sites.lock_version` stale-write protection.
- `SiteRevisionManager` owns revision allocation, snapshot changes, validation state,
  write-once materiality, review readiness, immutable composition assertions, and
  restore candidates.
- `SiteApprovalManager` owns revision-specific customer/internal requests, decisions,
  revocation, supersession linkage, and approval reads.
- `SiteServiceSupport` supplies the safe exception classification, actor-independent
  input normalization, short transaction wrapper, UUID/correlation generation, and
  generic `site_events` writer.

No route, UI, provider, filesystem, deployment, domain, routing, LeadHub, Stripe, or
legacy-reader authority is part of M2.

## Authorization Matrix

| Operation | Customer | Internal Admin | Super Admin |
| --- | --- | --- | --- |
| Read owned active 247SP site/revision/approvals | Yes, with active membership, business, and module | Yes | Yes |
| Read another tenant | No | Yes | Yes |
| Create site/revision/restore candidate | No | Yes | Yes |
| Classify materiality / submit for review | No | Yes | Yes |
| Request customer/internal approval | No | Yes | Yes |
| Decide/revoke customer approval | Owner, `Owner`, or `Admin` business role only | No impersonation | No impersonation |
| Decide/revoke internal approval | No | Yes | Yes |
| Archive | No | Yes, subject to lifecycle gates | Yes, subject to lifecycle gates |
| Publish/activate/convert | No | No, future-gated | No, future-gated |

An actor must be an active user. Internal administration is limited to active users
with an internal `Super Admin` or `Admin` role. Other internal staff roles receive no
implicit M2 authority. Customer reads require a `247sp` site with an active customer
association, active `business_users` membership, active/non-suspended business, and an
active repository `247sp` module. Customer-supplied identifiers never grant authority.

## Site Identity And Lifecycle

Site purposes are exactly `247sp`, `emd`, and `internal_demo`, and purpose is immutable
in M2. Site keys use cryptographically secure RFC 4122 version-4 UUID randomness.
Creation is Internal Admin/Super Admin-only and begins in `draft`.

A 247SP site requires an eligible business. The service locks the business, verifies
active/non-suspended business and active module state, locks/checks customer site
associations, and creates the site plus active customer association in one transaction.
This serializes the one-active-customer-associated-247SP-site service invariant. EMD
and internal-demo sites are created without fabricated businesses or associations.

The explicit M2 transition map covers `draft`, `demo`, `pending_customer`,
`pending_internal_review`, `approved`, `suspended`, and terminal `archived`. `demo` is
restricted to `internal_demo`. `approved` requires a current internally approved
revision; material revisions also require a current customer approval. A site with a
published revision pointer cannot be archived because deployment retirement is later
work. Every lifecycle mutation locks the site, compares the expected `lock_version`,
increments it atomically, and reports `stale_write` for stale callers.

`suspended` is safety-dominant: approval requests, decisions, rejection, and revocation
may update revision/approval state but never implicitly resume the site or increment
its lock version. Only an explicit Internal Admin/Super Admin lifecycle transition can
resume it after the ordinary target-state gates pass. `archived` is operationally
terminal for every M2 mutation, including revision creation/preparation, restore, and
approval mutation; authorized historical reads remain available.

`active`, `cancellation_pending`, and `conversion_pending` are explicit
`future_gate_required` states and cannot be entered through M2.

## Revision Lifecycle And Immutability

Revision creation locks the site before allocating `MAX(revision_number) + 1`. It
validates same-site ancestry and generation briefs, positive snapshot schema version,
top-level array/object snapshot data, and a 64-hex SHA-256 snapshot hash. New revisions
start `draft` with `undetermined` materiality.

The implemented transition model is:

- `draft -> validation_failed | ready_for_review`
- `validation_failed -> draft | ready_for_review`
- `restored -> ready_for_review`
- `ready_for_review -> changes_requested | customer_approved | internally_approved`
- `customer_approved -> changes_requested | internally_approved`
- `internally_approved -> changes_requested`

Approval invalidation has a separate narrow fallback: revoking customer approval may
return `customer_approved` or unpublished `internally_approved` to `ready_for_review`,
and internal revocation may return `internally_approved` to `customer_approved` or
`ready_for_review`. These are not general editing transitions; the revision remains
immutable and may be reapproved without composition changes.

`changes_requested` and `superseded` are terminal. `published -> superseded` remains
represented as the future M6 rule but is not executable by M2. M2 rejects every
transition to `published` with `future_gate_required`.

Composition/snapshot metadata is mutable only in `draft` and `validation_failed`.
`assertRevisionMutableForComposition()` remains a useful authorized read assertion.
Future M3 composition writes must use `lockMutableRevisionForComposition()` inside the
same caller-owned transaction as their writes so site ownership and mutability are
validated under the site/revision row locks. This contract does not begin M3. M2
snapshot updates cannot change site, revision number, creator, ancestry, or restoration
identity. Review-ready and later revisions reject snapshot mutation as
`immutable_revision`.

Review readiness requires classified materiality, a valid snapshot hash, at least one
same-site revision page, at least one same-revision section per page, exactly one theme,
same-site/same-revision asset ownership, and agreement between an asset's supplied page
and section references. M2 rejects inconsistent composition and never repairs it.

## Materiality And Customer Approval Supersession

Materiality is an explicit Internal Admin/Super Admin decision with a required reason.
It is write-once: only `undetermined -> material|non_material` is accepted. Incorrect
classification requires a new revision.

Classifying a later revision `material` locks the site/revision/affected approval rows
and supersedes older current approved customer approvals, requested customer approvals,
and requested internal approvals dependent on the earlier public baseline in the same
transaction. History and original approved decision timestamps remain; requested rows
receive a closing decision timestamp. The reason is `material_successor_revision`, and
audit metadata identifies approval type and successor revision. Approval decisions also
reject an older request whenever a newer material same-site revision exists, even if an
inconsistent requested row somehow survived invalidation.

The same material-successor transaction also invalidates the site's pre-publication
approval workflow state: `approved`, `pending_customer`, and
`pending_internal_review` return to `draft` through `SiteManager`, with the ordinary
transactional lifecycle audit. `suspended` remains suspended without a lock-version
change or false lifecycle event. `draft`, `demo`, and later-gated states remain
unchanged; in particular M2 never downgrades active published operation. Material
restore candidates apply this same invalidation and remain unpublished.

Classifying a revision `non_material` does not supersede older customer approval or
reset the site lifecycle state.
Migration 023 intentionally enforces current approval uniqueness per revision/type,
not globally per site/type. Non-material never means customer approval is unnecessary:
the revision may proceed to internal review only when it inherits exactly one still-
current approved customer decision from an earlier same-site revision. Revision 1
cannot bypass initial customer approval by being labeled non-material. Ambiguous
inherited approval history is a conflict.

## Approval Contract

Only `customer` and `internal` approvals are implemented. `production` and
`conversion` return `future_gate_required`. Requests are Internal Admin/Super
Admin-only, lock site/revision/current request rows, and are idempotent when the same
open request already exists. Requester user ID and semantic actor type are retained in
safe metadata even when the decision actor later replaces the row's actor columns.

A customer request requires a material `ready_for_review` revision and normally moves
the site to `pending_customer`. An internal request requires `customer_approved` plus a
current same-revision customer approval for material revisions. A non-material request
requires `ready_for_review` plus a still-current approved customer decision on an
earlier revision of the same site. Internal approval rechecks this effective customer
approval at decision time, so a revoked or superseded inherited prerequisite cannot
authorize a stale request. The ordinary internal-request site target is
`pending_internal_review`, subject to suspension preservation.

Only a `requested` approval can be decided. Customer decisions require the actual
associated Owner/Admin actor; internal administrators cannot impersonate customers.
Customer approval moves the revision to `customer_approved` and the site to
`pending_internal_review`. Internal approval moves an eligible revision to
`internally_approved` and the site to administrative `approved`; this does not mean
active, published, deployed, domain-ready, or production-ready.

Rejection moves the revision to terminal `changes_requested`, the site to `draft`, and
supersedes current approvals on that revision that could otherwise preserve launch
eligibility. Revocation requires a current approved row and a safe reason. Customer
approval may be revoked in `customer_approved` or unpublished `internally_approved`.
After internal approval, revocation supersedes the dependent current internal approval
as a system/service consequence, preserves both decision histories, returns the
revision to immutable `ready_for_review`, and normally targets `pending_customer`.
Internal revocation returns a material revision with a current customer approval to
`customer_approved`; non-material revision fallback is `ready_for_review`. Its ordinary
site target is `pending_internal_review`. All workflow site targets preserve
`suspended`. Published approval history cannot be revoked through M2.

When a new customer approval follows an unambiguous prior superseded/revoked customer
decision, `supersedes_approval_id` preserves that truthful same-site linkage only when
the prior decision belongs to an earlier revision; backwards linkage is forbidden.

## Restore Contract

A restore never mutates source history. Internal Admin/Super Admin selects an eligible
`published`, `superseded`, or `internally_approved` source on the same site. Under a
site lock the service allocates a new revision number, copies snapshot metadata, sets
`restored_from_revision_id`, bases it on the latest revision, and creates a `restored`,
`material`, unpublished candidate.

The transaction copies revision pages, sections, and theme; reuses stable logical page
and `site_assets` identities; and remaps new revision-page and section IDs in revision
asset references. No asset bytes or site-asset row is duplicated. The candidate must
then pass review, customer approval, internal approval, and later M6 build/deployment.

## Transactions, Locking, And Audit

Each mutation owns one short local transaction. There are no provider, filesystem,
HTTP, email, payment, domain, or Apache operations. Row locks serialize business site
creation, site revision numbering, materiality/supersession, duplicate approval
requests, and approval decisions. Site lock versions protect lifecycle stale writes.
Mutation lock order remains site, then target revision, then approval rows. Material
successor invalidation follows site, successor revision, then older approval rows.

Successful `site_events` are inserted inside the mutation transaction, so rollback
also rolls back success audit. Events contain identifiers, semantic actor type, bounded
reason, safe correlation ID, and non-content metadata only—never snapshots, public
copy, credentials, tokens, SQL, raw provider data, or filesystem paths. Unexpected
database errors are logged only by exception class and exposed as `database_failure`
with a safe message. M2 does not add `activity_logs` summaries.

## M2 Exclusions And Future Gates

M2 includes no component registry/authoring, schemas, composition UI, customer Website
Manager UI, admin CMS UI, routes, builds, publishers, deployment, production approval,
domains, routing, public ingestion, conversion, provider integration, legacy cutover,
or M3+ classes. Migration 023 is unchanged and no migration 024 is added. The existing
`SiteGenerator` and `AdminPortal` legacy website readers remain authoritative.
