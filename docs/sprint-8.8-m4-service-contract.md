# Sprint 8.8 M4 Service Contract

## Status

Sprint 8.8 M4 is delivered in three internal passes:

- **M4A — Admin Workflow Foundation:** **COMPLETE / STAGING PASS / FORMALLY CLOSED**;
- **M4B — Composition Editor + Generic Admin Preview:** **IMPLEMENTED LOCALLY / REVIEW REQUIRED**;
- **M4C — Review Submission + Internal Approval + Final M4 Validation:** **NOT STARTED**;
- **M4 overall:** **IN PROGRESS**.

This contract describes the complete M4 boundary, completed M4A, and locally implemented M4B. M4
remains in progress. Sprint 8.8 remains in progress, and production remains
unauthorized and not deployed. The authoritative M4A completion record is
`docs/sprint-8.8-m4a-closeout.md`.

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
Limits count valid UTF-8 characters through PCRE and do not require the optional
`mbstring` extension.

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
active business status, suspension state, active business-module assignment, and
active 247SP module under lock, allocates the next revision number, and writes its
success event in the same transaction. Revision insertion occurs only after the final
eligibility recheck.

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

## Final M4A Staging Evidence

M4A was deployed and validated on SHA
`8805eeeae704f130ddda357e82c4dd936fde5b4c`. The deployment gate report is
`evidence/SPRINT-8.8-M4A-STAGING-DEPLOYMENT.md`, recorded SHA-256
`45725a494bf415ad3adec8b113ca2ae5a72155f9f44efbf27385f6ed8af2bffd`. The final
real-MySQL report is
`ubo-sprint-8.8-m4a-final-validation-20260903T030735Z/SPRINT-8.8-M4A-STAGING-FINAL-VALIDATION.md`,
recorded SHA-256
`9fe38af06fa13c8196d0e106cc207aa80391c8bc7ae1ab53f403c4792f0b2de8`.

M4A required no migration: migrations 023/024 remained unchanged, migration 025+
remained absent, and deployment/final-validation migration executions were zero. The
final generic/import baseline was zero; the registry remained 16 definitions / 22
variants with zero drift.

Real association and suspension eligibility races passed. Real business-status and
module-status race variants were not executable because no safe reversible non-active
staging values were available; they are not labeled PASS. Authenticated admin GET and
browser-form cases were likewise not executable because no safe established staging
test-session mechanism avoided credential/session forgery. Deployed unauthenticated
HTTP, actual service authorization, and CSRF/PRG contracts passed. See the closeout for
the exact evidence and limitations.

## M4B - Implemented Locally / Review Required

M4B adds `/app/admin/site-composer.php` and `/app/admin/site-preview.php`.
M4A site detail links mutable revisions to the composer and composed revisions to
preview. Both routes use authenticated sessions and Internal Admin/Super Admin
policy. Composer POSTs validate `admin-site-platform` CSRF, rotate only after success,
and return HTTP 303. Preview is GET-only, private/no-store, and noindex/nofollow.

`SiteAuthoringCatalog` intersects repository-authorable definitions/variants with
active DB metadata through `ComponentRegistry::resolve()`. It excludes legacy,
inactive, missing, and drifted identities; page type and existing cardinality filter
new-section choices. Safe metadata includes the repository configuration schema and
asset requirements. Theme choices derive from `ThemeRegistry`.

`SiteSchemaForm` renders and parses structured controls for current authored section
and layout schemas: strings, nullable/optional values, enums, booleans, bounded lists,
unique enum lists, nested objects, CTA objects, and asset usage fields. Optional
fields/list rows have explicit inclusion controls. No configuration JSON editor or
unsupported-schema fallback exists. `ComponentSchemaValidator` remains the final
content authority. Existing page/theme schemas were extracted into public repository
accessors without changing their validation rules or duplicating their schemas.

`SiteCompositionEditor` accepts one structured operation and the exact loaded
`expected_snapshot_hash`. Operations are `initialize_new`,
`initialize_from_based_on`, `add_page`, `update_page`, `remove_page`, `move_page`,
`add_section`, `update_section`, `remove_section`, `move_section`, and `update_theme`.
The service loads authorized composition, applies the operation in memory, rebuilds
the complete DTO, and calls `SiteCompositionManager::replaceDraftComposition()`
exactly once on success. M3 owns locking, stale-write rejection, full validation,
atomic replacement, canonical hashes, rollback, and the single success event.
There is no direct composition SQL write, silent retry, or browser-supplied
composition graph. A failed POST shows an explicit reload/review link and does not
silently attach a new hash to the failed operation.

Page and section keys remain stable on update; logical identity changes require
remove/add. New pages begin with a draft text section so each atomic operation can
produce valid composition. Moves normalize all page/section orders to 10, 20, 30,
etc. Exact component/version/schema identities come from server catalog resolution;
unchanged stored versions are verified, never silently upgraded. Changing a stored
component requires explicit removal/addition; variants are selectable independently.

GET causes no composition mutation. An empty draft offers explicit initialization
with `local_service@1`, authorable layouts, and a home page containing `Content
pending review`. This satisfies all existing purpose entry-page rules without
inventing business facts or calling AI/providers. Based-on initialization resolves
only the target's stored ancestry, requires immutable same-site composed source,
validates it, retains exact authorable identities and eligible same-site asset IDs,
and rebuilds target rows through M3. Source row IDs/hash are not copied. Target facts
and references remain its own. Source composition is unchanged.

Asset selection uses existing `site_assets` only. UI candidates require same-site,
ready lifecycle, permitted unexpired rights, and matching active customer business
for customer-owned/licensed 247SP assets. Forms expose IDs/type/MIME/size, not storage
keys or private rights/provider data. M3 revalidates every reference, MIME requirement,
usage target, and collision inside replacement. Retained asset provenance is kept
server-side. An expired asset can be explicitly removed/replaced from a draft;
invalid resulting composition still fails. No upload or asset URL invention exists.

`SiteAdminPreview` checks the empty state, then calls
`validatedCompositionForActor()` and passes only that result to
`SiteCompositionRenderer::render()`. Failed validation shows no composition. The
preview is isolated in a sandboxed iframe, with no lead-form action or invented
contact URLs. Lead forms remain disabled. It shows the repository component output
for all pages; unresolved media URLs are omitted. This is an internal component
preview, not a deployed website or a customer preview.

Local verification includes actual service fixtures for authoring, reconstruction,
initialization, based-on copies, pages/sections/themes/assets, stale writers,
rollback/event atomicity, and validated inert rendering. A DOM-based view suite
submits successful controls from the actual rendered editor forms through the real
editor/M3 services and checks PHP input limits. Static scope tests supplement these
behavioral suites. Local fixtures do not claim real-MySQL or authenticated staging
browser validation.

Local gate on 2026-09-03: **39/39 standalone suites PASS**, including M1, M2, M3,
M4A, and pricing. M4B service behavior: **170 assertions PASS**; rendered forms:
**37 assertions PASS**; scope/contract: **59 assertions PASS**. All **18** changed/new
PHP files pass lint; `git diff --check` and the staged diff check pass. Historical
M2/M3 scope allowlists were extended only for the two authorized M4B routes. M4A's
obsolete composer-placeholder/no-preview assertions now check the M4B delegation;
legacy and migration guards remain intact.

M4B has not been deployed. M4C remains **NOT STARTED**. There is no materiality,
review-submission, approval, publication, customer workflow, or generic runtime
cutover. M4 and Sprint 8.8 remain **IN PROGRESS**.

The separate existing marketing property in `public/marketing` is not a Site Platform
site. Its staging preview publication is **PASS / ACTIVE** at
[https://staging-app.ultimatebackoffice.com/marketing/](https://staging-app.ultimatebackoffice.com/marketing/).
User-supplied evidence verifies HTTP routes/assets, the canonical marketing redirect,
actual noindex/nofollow response headers, and matching CSS/JS hashes. Browser viewport,
console, and interaction QA are **NOT YET RECORDED**. See
`docs/247sp-marketing-staging-preview.md`. M4B was not deployed; production Apache,
DNS, and SSL were unchanged. `247salespartner.com` is not configured and production
remains unauthorized.

## M4C — Not Started

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
provider integrations. Generic sites remain dormant and not publicly deployed. M4A
application services perform no Stripe, Twilio, Retell, Vendasta, Namecheap,
DigitalOcean provider, staging, or production operation.
