# Sprint 8.8 M5 — Customer Preview / Feedback / Approval Contract

## M5B staging closeout — 2026-09-13

**M5B COMPLETE / STAGING PASS / FORMALLY CLOSED** on deployed/validated SHA
`8cd63146713ef8fef26fd2861e960ac64ee1387a`. M5A remains **COMPLETE / STAGING PASS /
FORMALLY CLOSED**. M5 is **IN PROGRESS**; M5C is **NEXT / NOT STARTED**; M6 is
**NOT STARTED**. Migration 025 is absent/reserved for M6. Production is
**UNAUTHORIZED / NOT DEPLOYED**. This is an external documentation export; the deployed
checkout was not edited and no deployment, migration, commit, or PR occurred in the
completion run. See [M5B closeout](sprint-8.8-m5b-closeout.md).

Completed M5B gates: customer feedback/tone/emphasis/image-request browser mutations;
customer approval and request-changes with exact POST303GET receipts and legal DB
states; stale-tab409 with zero writes; populated cross-tenant manager/preview denial;
both forged preference400 cases with zero writes; targeted leakage; and the earlier
real-MySQL/concurrency/replay/HTTP/security validation. All 81 staging table counts
reconciled after final synthetic cleanup. Customer approval does not publish or
automatically create an internal approval request.

M5C follow-up observations and scope: approximately 11px overflow at 360px; PRG focus
observed on BODY; full responsive matrix; actual 200% zoom; keyboard/accessibility
and screen-reader validation; complete console/network QA and integrated browser
testing. These were explicitly deferred by the final M5B closure request and are not
claimed complete. Prior shared global-module-disable and exact independent 128KiB
valid-history limitations remain documented and do not independently block M5B closure.

The immutable four-report evidence chain is:

1. Backend / real MySQL: `/home/codex-validation/ubo-sprint-8.8-m5b-final-validation-20260913T205919Z/SPRINT-8.8-M5B-STAGING-FINAL-VALIDATION.md`
   SHA-256: `8d74bd6b479a84e04b68fd3136df71d032fda12f34785f147954ef2e9bdae909`
2. Staging-host browser dependency report: `/home/codex-validation/ubo-sprint-8.8-m5b-browser-completion-20260913T212752Z/SPRINT-8.8-M5B-BROWSER-COMPLETION.md`
   SHA-256: `354b02c454dd84c345fb9ecd8d9efa47f7e5449840b77dcc0004b18ef2c1d1f3`
3. External browser partial report: `/home/codex-validation/ubo-sprint-8.8-m5b-browser-external-20260913T214743Z/SPRINT-8.8-M5B-EXTERNAL-BROWSER-VALIDATION.md`
   SHA-256: `febc8c065a9e3e6d95daee8f34d07f34baab0dda1af96764a18164d930b59deb`
4. Final browser mutation completion: `/home/codex-validation/ubo-sprint-8.8-m5b-browser-final-20260913T220233Z/SPRINT-8.8-M5B-FINAL-BROWSER-MUTATION-VALIDATION.md`
   SHA-256: `3bdb19ff3d3bcb6aa7e2a5fd472f36864029474c59bb66612acd1a1e15e6620b`

## 1. Status and authoritative baseline

**M5 IN PROGRESS — M5A COMPLETE / STAGING PASS / FORMALLY CLOSED; M5B COMPLETE / STAGING PASS / FORMALLY CLOSED; M5C NEXT / NOT STARTED.**
M1–M4 remain COMPLETE / STAGING PASS / FORMALLY CLOSED. M6–M8 remain NOT STARTED.
Sprint 8.8 remains IN PROGRESS. Production remains UNAUTHORIZED / NOT DEPLOYED.
M5B is deployed and validated on `8cd63146713ef8fef26fd2861e960ac64ee1387a`.
The [M5B closeout](sprint-8.8-m5b-closeout.md) records the four-report evidence chain,
48/48 deployed standalone suites, 258/72/118 focused assertions, 189/189 PHP lint,
real-MySQL concurrency/replay/security gates, and completed authenticated browser
mutation validation. This documentation export does not authorize another deployment,
M5C/M6 implementation, or production.

Authoritative planning baseline: `c2efc5d210b9c6528414f9096acddd815f7e8985`
in `fvd8383/ultimate-back-office`. The session initially found clean local `main` at
`d33589da5eebbf8e2ae0dc203837d6667abd1f71`. Fetching `origin/main` resolved exactly
the requested SHA; local main was fast-forwarded before any edit. This documentation
branch starts at the requested SHA. The initial checkout itself was **not** at that
SHA. Final M4 application deployment/validation remains `d33589d`; `c2efc5d` includes
the subsequent documentation closeout and is not a new deployment claim.
The authoritative M5A implementation baseline after planning PR #114 merged is
`2b110466e6cf0ef0e456a1c3ea874f7622daab95`.

The [sprint plan](sprint-8.8.md), [handoff](codex-handoff.md),
[M2 contract](sprint-8.8-m2-service-contract.md),
[M3 contract](sprint-8.8-m3-service-contract.md),
[M4 contract](sprint-8.8-m4-service-contract.md), and
[M4 closeout](sprint-8.8-m4-closeout.md) agree on this boundary.
The [product specification](247sp-product-spec.md),
[generation architecture](247sp-website-generation-architecture.md),
[first-customer checklist](first-customer-checklist.md), and
[database plan](database-plan.md) preserve business-fact authority, legacy
compatibility, and separate publication authority. Their older conceptual/future
language does not override the implemented M2/M3 lifecycle contracts. The checklist's
completed legacy Website Manager is not evidence of M5 completion.

Throughout the original contract, “existing” describes inspected planning-baseline
code and “must” or “proposed” records the locked design. The M5A service and route names
are now implemented. M5B's previously proposed mutation boundaries are implemented
and staging validated as recorded above. Full integrated browser/accessibility QA
remains M5C work; real-MySQL and focused customer mutation browser gates passed.

## 2. Repository audit and evidence inventory

Paths below are repository-relative. The named implementations and relevant sections
were inspected, not inferred from class names.

| Inspected paths | Finding and M5 consequence |
| --- | --- |
| `public/app/247sp/website-manager.php`; `private/classes/WebsiteManager.php` | Existing customer editing surface; retain the product entry point, isolate generic review from legacy saves. No current CSRF scope in this route. |
| `public/app/247sp/{dashboard,onboarding,review,site-preview,business-profile}.php` | All link to Website Manager. `review.php` completes onboarding; `site-preview.php` reads legacy pages and permits real contact/lead/analytics behavior. Neither is a generic review route. |
| `private/classes/{Auth,Session,Otp,BusinessFoundation,TwentyFourSevenSalesPartner,Csrf}.php`; `public/accounts/{login,verify}.php` | Real user sessions, membership resolution, legacy module gate, scoped CSRF, and existing non-production OTP display. No generic customer bootstrap yet. |
| `private/classes/SiteAuthorizationPolicy.php` | Active customer reads; Owner/Admin approval; internal Admin/Super Admin cannot approve as customer. Does not filter site lifecycle or enforce the business selected in the UI. |
| `private/classes/{SiteManager,SiteRevisionManager,SiteApprovalManager,SiteServiceSupport}.php` | Authoritative locks, lifecycle, decisions, supersession, audit. Read APIs return broader data than M5 may disclose. Customer decision authorization currently occurs before the transaction. |
| `private/classes/{SiteCompositionManager,SiteCompositionValidator,SiteCompositionRenderer,SiteComponentRenderers,SiteAdminPreview,SiteAuthoringCatalog}.php` | Validated stored render pipeline reusable; admin preview/catalog entry points remain internal. Missing asset URL context omits media. CSS-only link disabling is insufficient for keyboard safety. |
| `public/app/admin/{site-review,site-preview}.php`; `private/classes/SiteReviewAdminWorkflow.php`; `private/views/site-review.php` | Explicit internal requests/decisions, scoped CSRF and 303 PRG. Internal timeline renders comments/reasons and must not be reused as a customer DTO/view. |
| `database/migrations/023_website_platform_foundation.sql`; `024_component_registry_versioning.sql` | Revision-bound approval comments and extensible JSON metadata already exist. No independent feedback state/table; 024 changes registry versioning only. |
| `tests/WebsitePlatformM2ApprovalInvariantTest.php`; `WebsitePlatformM4CBehaviorTest.php`; `WebsitePlatformM4CScopeTest.php` | Actual service fixtures and source guards exist; old M4 scope guards intentionally protect Website Manager and will need narrowly revised M5 expectations. |

The relevant M3 render/review suites, M4 view suites, and transaction-aware support
fixtures were located for the implementation test plan. This audit did not execute
them or claim new runtime results. `docs/codex-rules.md` was also read for local,
staging, migration, and document-root boundaries.

## 3. Existing Website Manager and integration decision

The file is `public/app/247sp/website-manager.php`, commonly documented as
`/app/247sp/website-manager.php`. With `public/app` as the application document root,
its application-host URL is `/247sp/website-manager.php`; preserve existing relative
links and configured base URLs rather than adding another `/app` prefix blindly.
The internal review file remains `public/app/admin/site-review.php` (the existing
documented `/app/admin/site-review.php` route).

The customer route calls `Session::requireAuth()`, `Auth::currentUser()`, then
`TwentyFourSevenSalesPartner::businessForUser()`. An explicit POST/GET `business_id`
is passed to `BusinessFoundation::businessForUser()`; absent one, it selects the
first active business by creation time/name. Explicit selection verifies active
membership but does not itself require active/unsuspended business status.
`businessHasAccess()` checks active `business_modules` and module key `247sp`, but
does not check `modules.is_active`. There is no authoritative session business
selection in this route. These legacy gates alone are insufficient for M5.

Reads use the 247SP bundle, `SiteGenerator::websiteForBusiness()/pagesForWebsite()`,
and WebsiteManager branding, service-image, and content-override methods. The view
displays/edits colors; logo, hero, about, service and pricing assets; homepage copy,
CTA labels/behaviors and statistics; about/contact copy; and service copy/images.
The save service also handles supported integration/optional override fields.
`saveWebsiteManager()` writes `247sp_website_branding`,
`247sp_website_content_overrides`, `247sp_website_service_images`, and
`website_integrations`, with an activity record. It does not call generic services.
The separate `saveAndRegenerate()` method can call the legacy generator; the customer
manager route calls only `saveWebsiteManager()`.

There is also an existing legacy approval/change-request surface on
`public/app/247sp/dashboard.php`, using the `247sp-dashboard` CSRF scope.
`TwentyFourSevenSalesPartner::requestWebsiteChanges()` and `approveWebsiteLaunch()`
write business-level activities; `websiteLaunchApproval()` infers the latest outcome
from `247sp_website_launch_approved`/`247sp_website_changes_requested` activity types.
These are not revision-specific approvals and cannot feed M5 eligibility or M6
publication. Preserve their legacy meaning; never copy them into effective generic
approval. Where dashboard navigation introduces generic review, label existing launch
status/actions as legacy and link generic review explicitly to Website Manager.
Do not let a generic approval set the legacy dashboard's launch-readiness flag.

Uploads use MIME/extension/size checks (5 MB), `move_uploaded_file()` and local
`public/app/uploads` directories. SVG logo validation checks for an SVG marker, not
a complete sanitization pipeline. This is not a generic asset rights/storage service
and must not be reused or expanded for M5 uploads.

**Decision:** extend Website Manager with a clearly labeled “Website revision review”
section and subordinate private preview. Keep one navigation entry and a Business
Profile link. Retain legacy settings and preview access, clearly labeled as the
existing website settings/preview; explain that those edits do not change the
revision being reviewed. Generic failures must never dispatch to legacy save.

Do not import, regenerate, dual-write, retire legacy writes, repoint published
readers, or treat a legacy website ID as a generic site ID. Preserve onboarding,
legacy preview, generated pages, existing uploads, domain/analytics settings and
LeadHub behavior until an explicitly authorized later cutover. Add CSRF/303 protection
to the retained manager POST while integrating M5; this is route security, not a
change to legacy content/storage authority. Do not broaden legacy edit permissions
or turn its fields into generic composition inputs.

## 4. Customer workflow and review selection

1. An authenticated customer opens Website Manager for a verified business.
2. The new read service resolves its single active customer-associated `247sp` site.
   Zero sites means “No revision has been sent for review”; multiple eligible sites
   are an integrity conflict, not a choice of whichever row happens to be first.
3. Resolve the most recently issued **customer** request by revision number, then
   request time/ID within that revision. Never use `latestRevision()` to expose an
   unpublished admin draft. Multiple open customer requests are an integrity conflict.
4. If that request is superseded/revoked, or a newer material revision exists, show
   “Review is no longer available; a new review will be provided.” Do not fall back to
   an older open request or expose the successor draft. A later non-material draft
   alone does not invalidate the material customer review.
5. A requested material `ready_for_review` revision presents the validated preview,
   exact revision number, request date, feedback, request-changes, and approval.
6. After approval, show a receipt and “Approved by customer; awaiting internal
   review.” After changes are requested, show a receipt and “Changes requested.”
   Comments/presentation requests never update the displayed revision.
7. Admins read customer submissions in the existing internal review workflow, prepare
   a successor through M4, classify it and explicitly send a new review. There is no
   automatic generation, email, notification-provider call, or approval chaining.

Read-only receipt/preview of the currently selected issued review may remain available
in `customer_approved`, `internally_approved`, or `changes_requested`, provided all
eligibility and validation gates still hold and there is no newer material revision.
Only an open request on `ready_for_review` enables mutations. Superseded/revoked
requests expose a generic status, not historical composition. No revision-history
browser, arbitrary revision URL, unpublished draft preview, or non-material approval
action is introduced. A later non-material internal revision inherits its baseline
through M2; its existence is not represented as a new customer approval.

## 5. Authorization and authoritative resolution

M5 uses `users`, not the future `portal_users` identities for a business's clients.
Every read and mutation requires a current active user and the selected business's
active membership. Resolve business context independently of submitted site IDs.
Explicit unavailable business IDs fail closed; never silently select the first
business after an explicit selection fails. Default business resolution is allowed
only when no business was requested on the initial GET.

Require all of the following server-side:

- `SiteAuthorizationPolicy::actorContext()` identifies an active customer; reject
  internal Admin/Super Admin even if they also have business membership;
- selected business is active and not suspended; membership is active;
- active `business_modules` assignment to `247sp` and `modules.is_active = 1`;
- exactly one active `customer` association, matching the selected business and site;
- site purpose `247sp`; M5 permits only `draft`, `pending_customer`,
  `pending_internal_review`, or `approved` site states, subject to review gates;
  `suspended`, `archived`, `demo`, and future-gated states are unavailable;
- exact request/revision/site agreement and the issued-review selection above;
- eligible immutable revision state, no newer material successor, and consistent
  effective approval using `SiteServiceSupport::effectiveCustomerApproval()` under
  the site lock; missing/ambiguous prerequisite state fails closed. An initial open
  customer review does not require a prior customer approval.

Active associated members may read. **All M5 submissions** (feedback, permitted
presentation input, changes requested, approval) require `requireCustomerApproval()`:
`business_users.is_owner = 1`, or business-scope role `Owner` or `Admin`. Lower-role
members receive a read-only view. This deliberately avoids creating a second role
model for customer contributors. Existing M2 semantics for other internal staff are
unchanged: they have no implicit site authority; actual membership still matters.

Submitted business/site/revision/request IDs are untrusted positive decimal scalars,
not permissively cast arrays or numeric prefixes. The service compares all submitted
expectations with independently resolved ownership; matching IDs alone are not
authorization. Customer DTOs are allowlists, never raw M2 result arrays.

### Concrete mutation gap and required narrow extension

Existing `decideApproval()` resolves customer authority **before** entering
`SiteServiceSupport::transaction()`. It locks site, revision and approval and checks
state/successors, but does not re-resolve actor/membership/module eligibility inside
those locks. `assertSiteOperational()` only rejects archived sites; M2 intentionally
allows decisions while preserving site suspension. These facts require M5 protection;
UI prechecks cannot supply it.

Extend the existing approval service with an optional, strictly validated server-built
expected customer-review context (business/site/revision/request IDs, snapshot hash,
site lock version). Add a connection-aware customer eligibility check within the
decision transaction, before writes. Recheck customer authority inside the transaction
for customer decisions generally; apply M5's narrower site/review expectations when
its context is supplied. Keep existing public call compatibility and M4/internal
transition behavior. Do not wrap `decideApproval()` in another transaction: the
existing transaction helper explicitly rejects nesting.

Preserve site → revision → approval lock order. The connection-aware check must use
current locking reads of the actor/role, association, business, membership and
module rows, not a repeatable-read snapshot or cached actor. Hold eligibility locks
through commit. Include role-assignment absence/range protection so an internal-role
grant cannot slip through the customer classification. Follow existing M4 authored
snapshot locking patterns; test interactions with creation, which acquires business
eligibility before site association locks. Deadlocks/timeouts must roll back and
return a safe retry/reload error with zero success event, not trigger an automatic
retry on another revision. This is a required real-MySQL concurrency gate.

The same exact review guard must protect the new feedback operation. Lifecycle SQL
continues to live solely in the existing managers. No independent approval engine.

## 6. Customer-safe immutable preview

The implemented M5A `SiteCustomerPreview` first uses the customer review resolver, then calls
`SiteCompositionManager::validatedCompositionForActor()` and
`SiteCompositionRenderer::render()` with empty action/asset context. It does not call
the internal-only `SiteAdminPreview` entry point or the legacy preview route.
M3's stored `render_read` validation remains authoritative for hashes, exact component
versions, schemas, assets/rights, theme and ownership. Do not rebuild composition from
current Business Profile values, repair it on GET, or use the mutable editor DTO.

Return only rendered HTML plus approved display labels. Do not serialize composition,
facts/source references, generation brief, internal notes/comments/reasons, provider
data, asset storage keys, correlation IDs, rights metadata, or unrelated business
records into HTML, JSON, data attributes, browser logs, or error messages. Request
comments written by admins are not customer-visible messages. Only recognized
customer-authored submission fields and an actual customer decision comment may be
shown. Unknown metadata stays server-side. Existing legacy compatibility render
markers are not permission to disclose arbitrary imported data.

Use a same-page **`srcdoc` iframe with `sandbox=""`**, a descriptive title, and
repository-owned static styles. Preserve M4's isolation, with these required changes:

- enforce a preview render mode that emits non-navigating text/spans for navigation,
  service-grid links, pricing and CTAs; do not rely on `pointer-events:none`, which
  does not prevent keyboard activation of anchors;
- pass no `lead_form_action`, contact action URLs, analytics, chat or asset URLs;
  lead inputs/buttons remain disabled and are not a submitting form;
- embed a restrictive CSP at the beginning of the srcdoc head: `default-src 'none'`,
  `script-src 'none'`, `connect-src 'none'`, `img-src 'none'`, `font-src 'none'`,
  `object-src 'none'`, `frame-src 'none'`, `form-action 'none'`, `base-uri 'none'`,
  and only the fixed inline styles allowed by `style-src 'unsafe-inline'`;
- grant no scripts, forms, same-origin, downloads, popups or top-navigation sandbox
  allowances. No customer-controlled base URL, CSS, srcdoc or iframe attributes;
- show every validated page in order with page labels; controls outside the iframe
  may navigate the review shell, but cannot navigate to a public website.

The wrapper response and error responses use `Cache-Control: private, no-store`,
`X-Robots-Tag: noindex, nofollow`, `Referrer-Policy: no-referrer` and UTF-8 content type.
Include noindex/nofollow metadata in srcdoc. Use an application response framing
restriction (`frame-ancestors 'self'`/SAMEORIGIN) to prevent third-party framing of
the review shell. Escape the complete srcdoc attribute and all outer display values.

Preview GET performs zero domain/audit/provider/filesystem mutation. Ordinary session
handling and generation of CSRF/session review handles are not domain mutations.
Read resolution/validation should share a consistent read boundary and recheck
eligibility before emitting the response; no GET creates requests, marks “viewed”,
regenerates, approves, or warms durable content. Content delivered before an eligibility
change cannot be recalled; subsequent reads/posts must fail closed.

M4 renders component structure using generic preview styling; it is not a deployed
build or a pixel-identical production release. M5 must say “Private revision preview;
forms and links are inactive; approval does not publish.” Media without resolved URLs
is omitted and this limitation must be visible. No claim of final image fidelity or
production readiness is allowed. Adding a media-delivery pipeline is not necessary
for this bounded structural review and is outside this contract.

## 7. Feedback and change requests

Keep the following actions separate in both UI and service dispatch:

| Customer action | Persistence | Lifecycle result |
| --- | --- | --- |
| Send feedback | Append a customer feedback entry to the exact request metadata | None; request remains open |
| Submit permitted presentation input | Append a typed customer input entry to that metadata | None; explicitly a request for admin consideration |
| Request changes | `SiteApprovalManager::decideApproval(..., 'rejected', comment, ...)` with the exact review guard | Existing M2 rejection: request rejected, revision `changes_requested`, site normally `draft` |
| Approve this revision | `SiteApprovalManager::decideApproval(..., 'approved', comment, ...)` with the exact review guard | Existing M2 approval; see section 8 |

### Bounded durable feedback without a new table

Use a new versioned `customer_review_v1` namespace in the **customer request's**
`site_approvals.metadata_json`. Existing requester metadata must be preserved. Proposed
`SiteApprovalManager::recordCustomerFeedback()` owns one transaction, the review guard,
read/validate/append/write of that namespace, and a content-free success event.
Do not put prose into `site_events`, `activity_logs`, revision snapshots, or generation
briefs. Do not create fake approval rows or overwrite `comments` for general feedback:
`decideApproval()` replaces that column with the final decision comment.

The namespace contains `entries`, an append-only list, maximum **20 entries per
request**, maximum **128 KiB encoded namespace**. Each entry has exactly:

- server-generated `entry_id`, authenticated `actor_user_id`, server UTC `created_at`;
- `kind`: `feedback`, `image_replacement_request`, or `presentation_preference`;
- `text` and the bounded kind-specific target/value defined in section 10;
- server-derived `submission_key_hash` and canonical `payload_hash` for replay checks;
- server-generated `correlation_id` for internal audit linkage only.

No customer edit/delete or admin rewrite of accepted entries. Malformed existing
namespace or unknown version fails closed, never silently resets history. Decode and
merge the namespace under the approval lock; preserve every other existing metadata
key. Customer reads project only kind, text, safe target/value and timestamp; actor
IDs, replay hashes and correlation remain private. Internal M4 review gets an escaped,
allowlisted customer-submission timeline; it must not dump JSON. Customer approval
or supersession leaves this history intact on the original request.

Text is valid UTF-8, trimmed with normalized line endings, at most **2,000 Unicode
characters and 5,000 bytes**. Feedback and change-request text are required; approval
comment is optional. Reject arrays, invalid UTF-8, disallowed controls (except LF/tab),
markup/executable input and unknown fields. Store plain text; escape on every render,
never evaluate Markdown/HTML or auto-link URLs. Count characters with UTF-8 PCRE,
without requiring mbstring. M2's existing `optionalComment()` enforces 5,000 **bytes**
with `strlen`, not a UTF-8 character guarantee; M5 must add the stricter validation.

GET issues a random session-bound review handle with the resolved actor/business/site/
revision/request/hash/site-version tuple and a two-hour expiry. Mutation forms carry
that opaque handle, a server-issued submission nonce and CSRF token. Resolve the
expected tuple from the session; submitted IDs cannot replace it. Bound retained
session handles (e.g. 20; reject expired/evicted forms with a reload instruction).
No authoritative correlation value comes from the browser.

For feedback/input, bind the nonce to the review handle and action. Under locks,
an existing matching submission key and payload returns the original receipt with
no append/audit; the same key with a different payload is a conflict. Recheck current
tenant/actor eligibility even for receipt replay. After a request closes, only such
an already-recorded receipt may be returned; no new append is accepted. Separate
fresh feedback nonces are separate intentional submissions. At the limit, clearly
decline additional comments; change request/approval remain available. This bounded
thread is not an unrestricted messaging/ticket system.

Explicit change requests consume the existing request exactly once. M2 records
`site_revision_changes_requested`, `site_approval_rejected`, and any applicable
lifecycle/supersession events inside its transaction. Composition remains immutable;
`changes_requested` is terminal, so admins create a successor rather than reopening
or editing that revision. No synthetic feedback entry is needed for the decision
comment, and no false success event may survive rollback.

## 8. Exact revision customer approval

The decision service binds the authenticated customer, verified business association,
site, exact immutable revision, exact open `customer` request, decision and database
timestamp. Existing `actor_user_id`/`actor_type` become the decision actor,
`comments` becomes the decision comment, and `decided_at` is recorded by the database.
Requester identity remains in metadata. Original request correlation remains on the
row; the decision event has its server-generated correlation and approval/revision
IDs. Do not replace history merely to make request and decision correlations equal.

Check the session presentation tuple against the locked rows, current site version,
snapshot hash, `requested` state, material `ready_for_review` revision, and absence
of a newer material revision. Revalidate stored composition before approving; a
tampered hash, expired rights or unavailable exact renderer must not produce an
approval for an unrenderable revision. No auto-refresh of the expected tuple after
failure. State gates still apply even when the submitted hash matches.

Successful customer approval changes revision to `customer_approved` and site to
`pending_internal_review`. It **does not create an internal request automatically**.
M4 explicitly requests internal review. A material revision needs its same-revision
current customer approval. A non-material revision requires exactly one effective
earlier customer-approved baseline, and cannot bypass initial approval. Internal
approval changes revision to `internally_approved` and site to administrative
`approved`. Neither action publishes, deploys, activates a domain, or establishes
production readiness.

M2 decision replay is **conflict**, not idempotent success: only `requested` rows can
be decided. Preserve that behavior; two customers racing produce one winner and one
safe stale/conflict response. A later GET may show the winning receipt. Customer
revocation exists in M2 but has no M5 UI in this bounded first pass. Internal Admin
and Super Admin must continue to fail customer approval even with a forged form or
associated owner membership.

## 9. Stale state and supersession

| Change since advisory GET | Required outcome |
| --- | --- |
| New draft, still undetermined/non-material, no review replacement | Existing issued material review remains eligible if all exact tuple/state checks pass. |
| Newer material revision classified | M2 supersedes older requested/current customer approvals and dependent requested internal approvals; M5 rejects old POST and hides old preview. No automatic switch to successor. |
| Request approved/rejected by another actor | Conflict, no overwrite or duplicate decision/event; reload shows the result. |
| Request revoked/superseded or a new request issued for the same revision | Old request/handle rejected; explicitly load the new review, even if revision hash matches. |
| Revision becomes `changes_requested` | No new feedback/approval; read-only selected receipt/validated preview if otherwise eligible. |
| Site suspended/archived | Customer preview and new submissions unavailable; preserve state. M5 never resumes the site. |
| Business suspended/inactive, membership removed, module disabled, association changed, actor deactivated/promoted to internal admin | Deny from current authoritative checks, including after preflight but before mutation locks. |
| Same-site revision/request IDs changed in the browser | Fail exact presentation binding, not just tenant checks. |
| Site lock version changes or composition integrity/hash fails | Stale/conflict; no mutation; user explicitly reloads/reviews. |
| Registry/asset eligibility changes | Render fails closed; approval revalidation fails; no on-GET repair. |

M2 material supersession closes approval rows without necessarily changing the old
revision's lifecycle to `superseded`. Do not rely on revision status alone. Its
prepublication site reset normally returns `approved`, `pending_customer`, or
`pending_internal_review` to `draft`, while suspension remains dominant. M5's stricter
customer suspension gate does not alter those established M2 transitions.

## 10. Smallest permitted customer asset/presentation input

Implement **requests only**, through the same durable feedback mechanism:

- `image_replacement_request`: choose a server-listed image usage from the exact
  validated revision and supply required bounded text describing the requested
  replacement. Re-resolve usage/page/section ownership on POST. Do not accept an
  upload, storage key, URL, arbitrary asset ID, or change `site_revision_assets`.
- `presentation_preference`: choose one of `tone = professional|friendly|concise`
  or `emphasis = services|trust|contact`, with optional bounded explanatory text.
  These are advisory preferences, not theme/component/schema values applied to the
  revision. Reject unknown keys/values and reusable business-fact fields.

One entry contains one kind and one preference/target. A revision with no eligible
image usage has no image-target option; presentation preference remains available.
The view explicitly says “Sent for consideration; this does not change your preview.”
If the customer requires revision changes before approval, they must use the separate
Request changes action. No later process may treat an undecided preference as consent
to modify an already-approved revision.

Selecting existing authorized assets was evaluated: M4 has an **internal-only**
catalog over same-site ready assets with allowed unexpired rights and matching
business ownership/licensing. There is no customer delivery/thumbnail/curation service.
Do not expose all site assets just because M3 can reference them. A future selection
feature needs an explicit customer candidate allowlist and safe media delivery.
Uploads, provider/storage finalization and asset-rights changes belong to later
separately scoped work; M6 does not implicitly authorize them either.

Business name, hours, services, FAQs, travel area and other reusable facts continue
through Business Profile/existing fact routes. Saving facts does not mutate an
immutable review snapshot; staff must prepare an appropriate successor.

## 11. Schema and migration decision

**No M5 migration is required for this bounded contract. Preserve 023/024 unchanged;
do not create 025. M6 keeps the next available number (currently 025).**

The durable concept missing from current services is repeated customer commentary,
not approval state. Approval metadata already has a durable revision/request parent,
survives decisions/supersession, and can hold a small versioned append-only list.
Decision prose uses existing `comments`; feedback prose uses the explicit namespace.
This is a new application metadata contract, not an assertion that feedback exists
today. Existing `site_events` remains a content-free audit trail with entry ID/kind,
approval ID and counts only. Do not copy feedback into event reasons.

Alternatives rejected: replacing `comments` loses prior feedback and the distinction
from final decisions; fake approval types conflict with 023's CHECK and lifecycle;
revision JSON violates immutability/hash semantics; audit prose violates M2's audit
contract. A separate table would be justified for unbounded discussions, independently
addressable/moderated messages, attachments, search or per-message retention. None
is required here. If those requirements are later added, revisit an additive table
with same-site revision/request foreign keys and durable submission uniqueness, and
renumber M6 only after that concrete scope change is accepted.

## 12. Proposed route and service boundaries

| Boundary | Responsibility |
| --- | --- |
| Existing `public/app/247sp/website-manager.php` | Customer bootstrap, business context, generic review section, explicit action dispatch, CSRF, safe errors, PRG; retain separate legacy settings save. No lifecycle SQL. |
| Existing `public/app/247sp/dashboard.php` | Navigation/label clarification only for generic review versus legacy activity-derived launch approval; preserve legacy action and readiness authority. |
| New `public/app/247sp/website-review-preview.php` | GET-only subordinate private preview wrapper; same business/request/presentation resolution. No POST or mutation. |
| New `private/classes/SiteCustomerReviewWorkflow.php` | Customer-only business/review resolver, projected workspace DTO, exact presentation expectations and action allowlist; delegate writes to approval manager. |
| New `private/classes/SiteCustomerPreview.php` | Customer eligibility plus immutable M3 validation, inert render mode and safe iframe document. |
| Existing `SiteAuthorizationPolicy` | Connection-aware current customer eligibility support; retain Owner/Admin and no-impersonation semantics. |
| Existing `SiteApprovalManager` | Exact-context decision extension and bounded feedback append, locking/atomicity; share existing lifecycle implementation. |
| Existing `SiteCompositionRenderer`/`SiteComponentRenderers` | Explicit preview mode for inert navigation; unchanged default rendering semantics, no customer renderer selection. |
| Existing `SiteReviewAdminWorkflow`/`private/views/site-review.php` | Escaped customer feedback/input visibility for internal staff, no customer action delegation or impersonation. |

Proposed customer views may be extracted under `private/views`; reuse the current
application shell. Generic routes/services must not call WebsiteManager,
SiteGenerator, DomainManager, LeadHub or provider integrations. The retained legacy
branch is the only manager route branch allowed to call its existing save service.
Unknown or malformed actions fail; missing action must not accidentally invoke a
legacy save for a generic form.

## 13. Explicit prohibited capabilities and M6+ exclusions

Customers cannot choose arbitrary components/variants, rearrange pages or sections,
edit HTML/PHP/JavaScript/CSS/JSON composition or executable markup, create revisions,
classify materiality, submit admin review requests, impersonate another actor,
enumerate revisions, access another tenant, or approve anything except the exact
presented open review. No generic customer upload or direct asset/theme mutation.

No publish/deploy/build/restore/archive/convert/domain reassignment/routing changes,
LeadHub routing or public lead submission, DNS/SSL/Apache work, provider integration,
production approval, active/public pointer changes, worker/job records, public runtime
cutover or automated communications belong to M5. M6 retains build/deployment/restore;
M7 retains domain/routing/registered-site ingestion/conversion; M8 retains integrated
sprint validation. Approval is not publication or readiness for the first customer.

## 14. Security and HTTP contract

Use dedicated **`customer-website-manager`** CSRF scope for the integrated manager's
customer POST actions, including its retained legacy settings form. There is no
existing Website Manager scope to reuse. Do not reuse `admin-site-platform` or
`shared-business-profile`. Validate before dispatch, rotate only after successful
mutation, and issue HTTP **303** to a server-built same-application URL. Missing,
expired, array or cross-scope tokens fail without mutation. Feedback receipt replay
does not authorize bypassing CSRF.

GET/POST are the manager's only supported methods; preview accepts only GET. Return
405 with Allow for others. Successful POST uses PRG; invalid input may return an
escaped form with 400, CSRF failure 403, stale state 409. Unknown/cross-tenant IDs
share a generic unavailable response (404) with no existence detail. Do not render
raw service/SQL exceptions. Authentication uses the configured accounts login, with
no externally supplied redirect target. Use session flash receipts rather than
trusting `?saved=1` as proof of generic success.

Limit request body to 16 KiB for generic actions; reject unexpected files, JSON
graphs, actor/correlation/provider fields and oversized scalar fields. Do not apply
that limit to the separate retained legacy upload handler's established file limits.
Avoid logging customer prose or tokens. Escape validation messages and retained form
values. On eligibility loss, omit old form values and preview entirely. On stale
state, show a reload link and never silently attach new revision expectations to
the failed action. Customer DTOs contain no internal reasons or actor identifiers.

## 15. Test strategy and evidence distinctions

The M5A subsets below have deployed executable coverage recorded in the M5A closeout.
M5B mutation requirements have deployed fixture/DOM/source, real-MySQL
mutation/concurrency, and focused authenticated browser coverage. Full integrated
responsive/accessibility/browser QA remains M5C work.

| Layer | Required executable coverage |
| --- | --- |
| Actual service tests | Owner/Admin/is_owner and lower-role matrix; inactive user/business/module/membership; internal impersonation; wrong business/site/revision/request; duplicate associations/requests; exact presentation binding; no request/draft enumeration; feedback/input bounds and replay; terminal states and supersession. |
| Transaction/integrity tests | Decision and feedback rollback including audit; UTF-8/JSON namespace corruption; preserve requester/history; no composition/hash changes; replay mismatched payload; feedback cap does not block approval; approval after hash/asset tamper fails. |
| Render/view tests | Actual rendered forms submitted through real workflow; safe projection with seeded secret/internal sentinel values; escaped customer text; empty/error states; sandbox/CSP; keyboard-inert navigation; no form/action/media/analytics network behavior; no mutation on reads. |
| Route/source tests | Authentication, dedicated CSRF, rotation and 303; allowed methods/actions, safe errors, headers; no lifecycle SQL/provider calls; no admin customer delegation; narrowly update historical M4 scope allowlists without dropping legacy/runtime/migration protections. |
| Unauthenticated HTTP | New preview and manager redirect to normal login, no 5xx/data leakage; method/header behavior recorded as actually observed after auth ordering. Does not prove authenticated authorization. |
| Real authenticated browser | Normal login, business navigation, preview, feedback/input/change request/approval, stale tabs, role/tenant denial, keyboard/screen-reader smoke, responsive behavior, browser console/network. Cannot be replaced by source tests. |
| Real MySQL | Independent connections for two customer decisions, feedback append/replay, material successor versus decision, eligibility/suspension changes versus mutation, admin/internal state changes; native prepares, transactional audit, cleanup and no legacy drift. |

Run the repository's standalone suite set (42 suites at baseline) and PHP lint for
changed/new files, plus focused M2/M3/M4 regressions and new M5 suites. Local fixtures
prove service behavior, not real MySQL locking. Every race verifies one legal serial
outcome, zero partial writes and zero false success. Distinct valid feedback appends
may both succeed serially; same nonce appends once; decisions have one winner.
Do not require every concurrent call to succeed or silently retry decisions.

Check canonical composition hashes and row counts before/after GET and feedback;
verify approval alone never changes published pointers/build/domain/LeadHub rows.
Required cases that cannot execute must be NOT EXECUTABLE/BLOCKED with a reason.
The final customer-facing browser gate cannot inherit M4's nonblocking browser waiver.

## 16. Staging authentication and browser validation

M4 closeout records authenticated browser validation as NOT EXECUTABLE because no
approved safe session mechanism existed. It does not prove that normal login cannot
be used. Audit found an existing route-based path:

`public/accounts/login.php` → `Auth::requestLoginCode()` → `Otp::createForUser()` →
`public/accounts/verify.php` → `Auth::verifyLoginCode()` → `Session::login()`.

`shouldDisplayOtp()` returns true only for development/local/staging. The current
login route includes that prepared code in its redirect to verify; verification
checks the stored hash/expiry and marks it used, then regenerates the session ID.
Email sending is currently unconfigured in this Auth flow. These were planning source findings; subsequent M5B evidence confirms the staging
environment and successful normal OTP browser login. The code-in-URL
behavior also means validation evidence must not retain login URLs, OTPs or cookies.

For future authorized staging validation:

1. Operator confirms deployed SHA, staging host/environment, existing authentication
   setup and designated synthetic active customer actors/businesses. Use separate
   Owner/Admin/lower-role and cross-tenant fixtures, plus internal actors for denial.
   Do not choose a real customer's email or change existing actors to manufacture tests.
2. Sign in through the actual login/verify UI using the designated staging account
   and the existing staging OTP mechanism, or have the operator complete login.
   Do not call `Session::login()` from helpers, insert session rows/files, forge or
   transplant cookies, or add test-user query parameters/authentication bypasses.
3. Use actual M4 services to prepare synthetic review fixtures only in the separately
   authorized staging validation scope. Do not run onboarding/payment/provider work
   merely to obtain a review fixture. No migrations needed.
4. Exercise 360/768/1280 CSS-pixel widths, 200% zoom, keyboard-only operation, visible
   focus, labels/error associations, status announcements, iframe title and scrolling.
   Check no horizontal overflow, no keyboard trap, and clear exact-revision approval
   confirmation. Inspect console and network for errors, warnings, blocked unintended
   requests, lead/contact/analytics effects and private metadata.
5. Record the tested SHA, browser/version, role/fixture references, case results and
   sanitized evidence. Log out and reconcile only approved synthetic domain, audit
   and authentication artifacts; record cleanup boundaries and counts.

If designated actors or the existing staging login path are unavailable, operator
authentication/provisioning is a staging prerequisite, not a reason to add a production
bypass. No dedicated session-minting endpoint is proposed. A test-only browser runner
may automate these same login forms with ephemeral secrets outside the repository;
it must not modify auth code or environment configuration. Browser and real-MySQL
gates remain blocked until safely executable. This does not block local M5 coding
once implementation is separately authorized.

## 17. Recommended implementation passes and exit gates

Keep one milestone with three internal passes. Move permitted input into M5B because
it uses the same bounded feedback persistence and security contract; do not defer its
data model until the final QA pass. Begin browser validation in M5A, not at closeout.

| Pass | Scope | Exit gate |
| --- | --- | --- |
| **M5A — Customer review/preview foundation** | Website Manager integration, business/review resolver and safe DTO, issued-review selection, immutable inert preview, navigation and status. No new customer domain mutation. Establish designated staging login strategy. | Service/read/render/route tests; zero domain writes on GET; tenant/role/private-data gates; early authenticated responsive/keyboard preview smoke after approved deployment, or explicitly blocked evidence. |
| **M5B — Feedback, permitted input and decisions** | Versioned bounded feedback namespace, input requests, exact presentation binding, narrow transactional authorization extension, existing approve/reject delegation, CSRF/303 on integrated manager, admin visibility and stale handling. | Actual service/view tests, M2/M3/M4 regressions, replay/rollback/security coverage; no lifecycle duplication or legacy cutover; focused authorized MySQL/browser mutation validation. |
| **M5C — Integrated customer QA and closeout** | Responsive/accessibility/console corrections, complete authenticated browser matrix, final real-MySQL concurrency/eligibility/integrity run, evidence and cleanup. | All mandatory customer-facing gates executable and passed, no unresolved tenant/approval/data-loss/security issues, no schema/runtime cutover, evidence-backed review/merge/staging closeout. |

M5A is **COMPLETE / STAGING PASS / FORMALLY CLOSED** on merged/deployed SHA
`ee8c670a6dc8bc19ecb0786dff62abfea645aff3`. Its deployed gate passes 45/45
standalone suites, focused behavior/view/scope results of 103/40/59 assertions, and
180/180 PHP lint. Real MySQL 8.4.8 authorization, issued-review, M3 integrity,
zero-domain-mutation, concurrency, normal OTP-authenticated HTTP/DOM, legacy CSRF and
303 PRG, private-data, and cleanup gates passed. Browser-only responsive/keyboard,
console, and network smoke is explicitly NOT EXECUTABLE because no browser runtime or
interactive operator was available; M5A permits blocked evidence, while the complete
mandatory customer browser matrix remains an M5C exit gate. See
[M5A closeout](sprint-8.8-m5a-closeout.md). M5B is **COMPLETE / STAGING PASS / FORMALLY CLOSED** and M5C is
**NEXT / NOT STARTED**; M5 and Sprint 8.8 remain in progress.

## 18. Reconciliation, implementation prerequisites and planning verification

Concrete architectural issues are resolved in this proposal: legacy module checks
are weaker than M2; broad M2 reads need customer projection/issued-request selection;
customer decision authorization needs an in-transaction current-state recheck;
M2 suspension preservation is distinct from M5's deny-while-suspended UX; request
comments are internal and overwritten on decision; independent feedback needs bounded
metadata persistence; M4 pointer-only link disabling needs keyboard-safe rendering;
legacy uploads are not a reusable generic media pipeline.

No schema or approval-state-machine gap blocks implementation of the selected scope.
The metadata limits and request-only input are deliberate first-customer constraints.
Full media fidelity, uploads or an unbounded discussion system would require revisiting
this scope; they are not silently promised. Safe designated staging actors/login and
executable browser/race fixtures remain later validation prerequisites. The separate
M5A implementation instruction authorized only the read-only customer review and
preview foundation.

Planning verification: all ten required documents exist at the authoritative SHA;
relevant implementation paths are inventoried above. At the start of the planning
audit, after synchronization, HEAD, main and origin/main matched that SHA.
The contract has all 18 required sections, its relative Markdown
links resolve, and it has no unbalanced fences or trailing whitespace.
`git diff --check` and a separate new-file `git diff --no-index --check` passed.
The changed/untracked path allowlist contains only this contract and the handoff
pointer; no application/schema/runtime behavior changed. No application tests, browser tests, MySQL validation,
migrations, deployment, provider calls or production access are represented as run
by this planning audit.
