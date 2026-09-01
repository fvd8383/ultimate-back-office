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

`changes_requested` and `superseded` are terminal. `published -> superseded` remains
represented as the future M6 rule but is not executable by M2. M2 rejects every
transition to `published` with `future_gate_required`.

Composition/snapshot metadata is mutable only in `draft` and `validation_failed`.
`assertRevisionMutableForComposition()` is the authoritative M3/M4 boundary. M2
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
and supersedes current approved customer approvals on older revisions in the same
transaction. History and original decision timestamps remain; the reason is
`material_successor_revision`, and audit metadata identifies the successor revision.

Classifying a revision `non_material` does not supersede older customer approval.
Migration 023 intentionally enforces current approval uniqueness per revision/type,
not globally per site/type. A non-material technical revision can go directly to
internal review when the public result is unchanged.

## Approval Contract

Only `customer` and `internal` approvals are implemented. `production` and
`conversion` return `future_gate_required`. Requests are Internal Admin/Super
Admin-only, lock site/revision/current request rows, and are idempotent when the same
open request already exists. Requester user ID and semantic actor type are retained in
safe metadata even when the decision actor later replaces the row's actor columns.

A customer request requires a material `ready_for_review` revision and moves the site
to `pending_customer`. An internal request requires `customer_approved` plus a current
customer approval for material revisions, or `ready_for_review` for non-material
revisions; it moves the site to `pending_internal_review`.

Only a `requested` approval can be decided. Customer decisions require the actual
associated Owner/Admin actor; internal administrators cannot impersonate customers.
Customer approval moves the revision to `customer_approved` and the site to
`pending_internal_review`. Internal approval moves an eligible revision to
`internally_approved` and the site to administrative `approved`; this does not mean
active, published, deployed, domain-ready, or production-ready.

Rejection moves the revision to terminal `changes_requested`, the site to `draft`, and
supersedes current approvals on that revision that could otherwise preserve launch
eligibility. Revocation requires a current approved row and a safe reason. Customer
revocation before internal approval returns the revision to `ready_for_review` and the
site to `pending_customer`. Internal revocation returns a material revision with a
current customer approval to `customer_approved`; non-material revision fallback is
`ready_for_review`. Both return the site to `pending_internal_review`. Published
approval history cannot be revoked through M2.

When a new customer approval follows an unambiguous prior superseded/revoked customer
decision, `supersedes_approval_id` preserves that truthful same-site linkage.

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
