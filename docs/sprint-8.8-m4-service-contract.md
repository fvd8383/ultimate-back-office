# Sprint 8.8 M4 Service Contract

## Status

Sprint 8.8 M4 is delivered in three internal passes:

- **M4A — Admin Workflow Foundation:** implemented locally / review required;
- **M4B — Composition Editor + Generic Admin Preview:** planned / not started;
- **M4C — Review Submission + Internal Approval + Final M4 Validation:** planned / not started.

This contract describes the complete M4 boundary and the implemented M4A subset. M4
is not complete. Sprint 8.8 remains in progress, and production remains unauthorized
and not deployed.

## Parallel Admin Workspace And Legacy Boundary

M4A adds a parallel internal admin workspace named **Site Platform**:

- `/app/admin/sites.php` lists and creates generic sites;
- `/app/admin/site.php` shows one generic site, its active customer association,
  generation brief history, revision history, approval summary, and M4A creation forms;
- `SiteAdminWorkspace` owns prepared, read-only generic site queries and requires an
  Internal Admin or Super Admin actor for every public entry point.

The existing **Websites** navigation and the legacy routes `websites.php`,
`website.php`, and `website-editor.php` remain unchanged and authoritative for legacy
247SP administration. The customer Website Manager also remains unchanged. M4A does
not replace their reads, invoke `SiteGenerator`, change `WebsiteManager`, or alter
legacy preview/public runtime behavior.

## Authorization And Route Contract

Both Site Platform routes require an authenticated session plus Internal Admin or
Super Admin authority. The route check and every service entry point use existing
Admin/Site authorization contracts. Submitted identifiers are untrusted; services
re-resolve site, business association, brief, revision, and ancestry ownership.

Every POST uses the dedicated `admin-site-platform` CSRF scope. A successful mutation
rotates the token and returns a 303 post/redirect/get response. Known service, CSRF,
and input failures expose only designed safe messages; unexpected failures collapse to
a generic message. Routes contain no site/revision/approval lifecycle SQL.

Every M4A mutation uses a server-generated `SiteServiceSupport` correlation ID. The
browser neither supplies nor receives an authoritative correlation ID.

## Generic Site Creation

`sites.php` delegates to `SiteManager::createSite()` for the exact approved purposes:

- `247sp` requires a server-eligible active, unsuspended business with an active 247SP
  module and no conflicting active generic customer site;
- `emd` creates no business association;
- `internal_demo` creates no business association.

No purpose fabricates a business. Site creation does not create domains, routing,
provider records, generated legacy pages, or deployments.

## Generation Brief Contract

`SiteGenerationBriefManager` owns M4 authored generation briefs. These records are
versioned presentation and creative direction, not reusable business facts.

The authored schema contains exactly six bounded plain-string fields:

```text
summary             2,000 characters, required
target_audience     1,000 characters
tone_notes          1,000 characters
design_notes        2,000 characters
conversion_notes    2,000 characters
page_notes          4,000 characters
```

HTML/markup, PHP, JavaScript URLs/handlers, template expressions, stylesheet imports,
file traversal, executable template/script/style paths, control characters, and
unknown fields are rejected. The UI is structured and provides no raw JSON editor.
Business name/address/hours/services are intentionally absent from this schema.

Authored rows use `source_type = admin_manual`. The manager canonicalizes the exact
schema with `CanonicalJson`, stores its deterministic SHA-256 `content_hash`, locks the
site, and allocates monotonically increasing `brief_version` in one transaction. Every
manual row uses the immutable `authored` state. The current authored brief is derived
as the highest `admin_manual` version; prior state, JSON, hash, and `superseded_at` are
never rewritten. This append-only lifecycle is required because generation-brief state
participates in M3 revision hashing. Imported rows remain historical `imported` records
and are not rewritten.

## Authoritative Revision Snapshot Contract

`SiteRevisionSnapshotBuilder` constructs all M4A authored snapshot input server-side
with `snapshot_schema_version = 1`. Browser forms cannot submit facts JSON, source
reference JSON, or a snapshot hash.

For `247sp`, the builder re-resolves the active customer association and consumes:

- core/public identity from `businesses`;
- presentation-safe Shared Business Profile facts from `business_profiles`;
- selected services from `business_sub_services`/`sub_services`/`categories`;
- custom services from `business_custom_services`/`categories`;
- the current service-area contract in `247sp_website_configurations`;
- public hours, hour exceptions, website/all-channel FAQs, and active public pricing
  guidance from their Shared Business Profile child tables.

The facts snapshot contains only presentation-needed values. Source references name
the authoritative tables and stable row identifiers/timestamps used. Transfer numbers,
notification destinations, passwords, OTP/authentication material, billing/Stripe
references, provider credentials, internal notes, and other private operational data
are excluded. Legacy generated page `content_json`, legacy content overrides, and
generation brief prose are not authoritative business-fact inputs.

For `emd` and `internal_demo`, the builder emits an explicit minimal purpose snapshot,
a null business association, and `customer_business_fabricated = false`.

The initial empty-draft `snapshot_hash` is `CanonicalJson::hash()` over the schema
version, stable site identity/purpose, authoritative facts, source references,
generation brief version/hash/source type, optional same-site ancestry revision
number/hash, and the explicit empty composition seed:

```json
{"state":"empty","pages":[],"theme":null,"assets":[]}
```

No random or placeholder hash is used. When M4B saves composition, the existing M3
stored-revision hasher remains authoritative for the complete revision hash.

## Authored Draft Revision Contract

`SiteRevisionManager::createAuthoredDraftRevision()` accepts only actor, site, brief,
and optional based-on revision identifiers. It requires Internal Admin authority,
builds authoritative facts/references server-side, generates a server correlation ID,
locks and re-resolves the site, applies the M2 operational gate, enforces same-site
brief and immutable ancestry ownership, re-checks the 247SP customer association,
allocates the next revision number under lock, and writes its success event in the
same transaction.

The row is a normal `draft`, with `materiality = undetermined`,
`restored_from_revision_id = NULL`, and an empty composition. M4A never creates
`ready_for_review`, `customer_approved`, `internally_approved`, `published`, or
`restored` revisions and does not copy prior composition.

## One Mutable Admin Draft

The Site Platform allows at most one existing revision in `draft` or
`validation_failed` per site before creating another authored draft. The check runs
under the site lock. A conflict identifies the existing revision ID and performs no
mutation. This is an M4 admin-workflow rule; it does not change the M2 historical or
concurrency model.

`changes_requested`, `ready_for_review`, `customer_approved`, `internally_approved`,
`published`, `superseded`, and `restored` do not block a successor. An optional
based-on revision must be same-site and immutable. M4A records ancestry but deliberately
does not copy composition; that behavior belongs to M4B.

## M4B — Planned / Not Started

M4B will own the composition editor and generic internal admin preview, including
approved component/variant selection, page/section/theme composition, permitted asset
assignment, and starting from prior composition. M4A contains only a non-actionable
placeholder. It adds no generic preview route or rendering cutover.

## M4C — Planned / Not Started

M4C will own materiality classification UI, validation feedback, mark-ready-for-review,
customer-review request, internal approval request/decision, and final M4 validation.
M4A provides no such mutation or UI.

## Customer Review And M5 Boundary

M4 does not permit internal staff to approve as a customer or forge customer feedback.
`SiteAuthorizationPolicy::requireCustomerApproval()` remains unchanged and rejects
Internal Admin actors. M5 owns the controlled customer preview, feedback/change
request, approved input, and revision-specific customer approval UI. The legacy
customer Website Manager is not converted in M4A.

## Schema, Runtime, And Provider Boundaries

M4A uses migrations 023 and 024 unchanged. It adds no migration 025 or later. It
makes no changes to `SiteGenerator`, legacy generated websites/pages, legacy preview,
public lead submission, DomainManager, LeadHub, Apache, build/deployment records, or
provider integrations. Generic sites remain dormant and not publicly deployed. No
Stripe, Twilio, Retell, Vendasta, Namecheap, DigitalOcean provider, staging, or
production operation is part of M4A.
