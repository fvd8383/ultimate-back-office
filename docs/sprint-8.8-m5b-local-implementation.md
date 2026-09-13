# Sprint 8.8 M5B — Local implementation and review handoff

Status: **M5B IMPLEMENTED LOCALLY / REVIEW REQUIRED**. M5 remains IN PROGRESS.
M5A remains COMPLETE / STAGING PASS / FORMALLY CLOSED. M5C and M6–M8 remain
NOT STARTED. Production remains UNAUTHORIZED / NOT DEPLOYED.

## Baseline and boundaries

Authoritative baseline: `2c742190809006008b42f7e2c7075701047ac74b`, PR #116's
M5A closeout merge. Local `main` initially pointed to `ee8c670`; fetching origin
resolved exactly the requested baseline and local main fast-forwarded cleanly.
The intervening changes were M5A closeout documentation only. There was no
unmerged or dirty local work. The implementation branch is
`codex/sprint-8.8-m5b-customer-feedback-decisions`.

The [M5 contract](sprint-8.8-m5-service-contract.md) remains binding. No schema
migration was added: 023 and 024 are unchanged; 025 is absent and reserved for M6.
No new approval type, feedback table, revision hash semantics, publishing authority,
or alternate customer approval engine was introduced.

## Implementation

`SiteApprovalManager::decideApproval()` gains an optional, strictly checked
server-built expected-review context. Existing customer callers also receive current
transaction-time customer reauthorization; internal callers retain their lifecycle
semantics. The identity probe occurs before the transaction, avoiding an early
repeatable-read snapshot. Inside the owning transaction, locks follow site → revision
→ approval. The exact actor/business/site/revision/request/hash/site-version tuple,
current issued request, materiality, state, absence of a newer material revision,
effective approval, validated immutable composition, and safe rendering are checked
before writes. There is no nested transaction or automatic retry.

`SiteAuthorizationPolicy` supports an explicit current connection. It locks the
actor parent and role-assignment range, then locks assigned role definitions without
filtering away a possible scope change. Current locking reads recheck active user,
internal Admin/Super Admin exclusion, business status/suspension, membership,
Owner/Admin/is_owner authority, business module, global module, and customer association.
Eligibility reads use shared locks where sufficient. The M5 guard also protects the
business parent and all its associations against ambiguous selection, holds asset
rows and shared registry locks through commit, and checks the current request selection.
The small component registry is read-locked during validation; registry edits may wait
for the short customer transaction. Site creation takes business locks before site
association locks, so opposing operations can deadlock. The existing transaction helper
rolls back and the route returns a safe reload response; no decision is retargeted.

`recordCustomerFeedback()` owns one transaction for authorization, exact-review checks,
metadata validation, replay detection, append, write, and a content-free audit event.
The `customer_review_v1` namespace contains only `entries`, limited to 20 entries and
128 KiB encoded. Entries contain the contract's generated UUID, authenticated actor,
UTC timestamp, kind/text/target/value, derived submission hash, canonical payload hash,
and generated correlation UUID. Unrelated metadata survives unchanged; malformed or
unknown versions fail closed. Validation tolerates MySQL JSON object-key reordering.
No accepted-entry edit/delete or admin rewrite operation exists.

Each eligible manager GET issues a random 32-byte opaque session handle, with a
two-hour lifetime and a maximum of 20 retained handles. The browser receives the
handle and separate random action nonces; domain expectations remain server-side.
The replay key derives from handle/action/nonce. Matching feedback replay returns the
original receipt without another append/event, including after a consistent terminal
decision only when the same review remains selected and current eligibility holds.
Superseded, revoked, replaced, and newer-material-invalidated reviews reject replay.
Changed payloads conflict. Fresh nonces represent
intentional new submissions. Decisions retain M2's conflict-on-second-decision behavior.

Text validation normalizes line endings, trims, validates UTF-8 with PCRE, and enforces
2,000 Unicode characters and 5,000 bytes without mbstring. It rejects arrays, controls
other than LF/tab, markup/executable forms, unknown keys, and invalid selections.
Feedback and rejection instructions are required; approval comments are optional.
All output is escaped without Markdown interpretation or URL autolinking.

Presentation requests allow one `tone = professional|friendly|concise` or
`emphasis = services|trust|contact`. Separate server-rendered tone and emphasis forms
fix each target in a hidden control and list only that target's values, retaining the
same presentation action/nonce semantics and server validation. Image requests accept only an opaque token derived
from a validated image usage in the exact revision, re-resolved on POST, with required
instructions. Safe page/image labels are projected for both customer and admin views.
No uploads, asset catalog, arbitrary asset identifiers/URLs, or asset writes are added.
Advisory submissions never alter the preview or immutable hash.

Approval uses M2's `approved` decision: revision → `customer_approved`, site →
`pending_internal_review`. Rejection uses `rejected`: revision → `changes_requested`,
site → normal M2 `draft`. Decision comments remain in the existing comments field;
they are not duplicated as feedback. The feedback cap does not block decisions.
Neither decision automatically creates internal review, builds, publishes, deploys,
changes public pointers, or establishes production readiness.

Website Manager explicitly dispatches generic actions and retained `legacy_save`.
Missing/unknown actions cannot fall through. Generic bodies are capped at 16 KiB and
unexpected files/fields are rejected independently of legacy upload handling. Both
branches use `customer-website-manager` CSRF, rotation after actual successful mutation,
HTTP 303 to server-built application URLs, and session flash receipts. Matching replay
does not rotate CSRF because it performs no new mutation. Manager methods remain
GET/POST; private preview remains GET-only. Safe errors use 400/403/404/409 and never
render raw SQL or traces. Lower-role customers see no mutation forms. Terminal receipts
and advisory/lifecycle consequences are explicit. M4 shows an escaped allowlisted
customer timeline. Dashboard changes are exactly three labels and one explanatory
paragraph; its legacy activity-derived authority is unchanged.

## File inventory

Added:

- `private/classes/SiteCustomerFeedback.php`
- `private/classes/SiteCustomerReviewGuard.php`
- `private/classes/SiteCustomerReviewInput.php`
- `private/classes/SiteCustomerReviewSession.php`
- `private/views/site-customer-submissions.php`
- `tests/WebsitePlatformM5BBehaviorTest.php`
- `tests/WebsitePlatformM5BInputSessionTest.php`
- `tests/WebsitePlatformM5BViewRouteTest.php`
- `tests/support/WebsitePlatformM5BDatabase.php`
- This local implementation record.

Modified:

- `private/classes/SiteApprovalManager.php`
- `private/classes/SiteAuthorizationPolicy.php`
- `private/classes/SiteCustomerReviewWorkflow.php`
- `private/classes/SiteReviewAdminWorkflow.php`
- `private/views/site-customer-review.php`
- `private/views/site-review.php`
- `public/app/247sp/website-manager.php`
- `public/app/247sp/dashboard.php`
- `tests/WebsitePlatformM2ScopeTest.php`, `WebsitePlatformM3ScopeTest.php`,
  `WebsitePlatformM4AScopeTest.php`, `WebsitePlatformM4CScopeTest.php`,
  `WebsitePlatformM5AScopeTest.php` (narrow later-milestone allowances).
- `docs/codex-handoff.md`, `docs/sprint-8.8.md`, and the M5 service contract's status.

## Executed local validation

| Gate | Actual result |
| --- | --- |
| All repository standalone `tests/*Test.php` suites | **48/48 PASS** |
| M5B behavior | **258 assertions PASS** |
| M5B input/session | **72 assertions PASS** |
| M5B view/route | **118 assertions PASS** |
| Combined focused M5B | **448 assertions PASS** |
| M5A behavior/view/scope | **103/40/58 assertions PASS** |
| Pricing, M1, M2, M3, M4A, M4B, M4C regressions | **All included suites PASS** |
| Repository PHP lint | **189/189 PASS**, including subsequent lint of all 22 changed/new PHP files |
| Markdown relative references and fences | PASS |
| Working, cached, and committed patch whitespace checks | PASS |
| Migration diff and 025 absence | PASS |

M5A's scope count changed from 59 to 58 because dashboard immutability moved to a
more precise M5B assertion: the entire dashboard must equal the authoritative
baseline plus exactly its four permitted text edits. Broader migration/runtime/provider
protections remain. M2/M3 scopes likewise allow that one authorized dashboard path.
The service fixture tests execute real PHP managers/validators; view tests parse actual
rendered form controls and submit through the actual workflow. Route assertions are
source checks, supplemented by executable CSRF tests, not authenticated HTTP evidence.
Injected audit, timeout, and deadlock failures intentionally exercise safe rollback;
their class-only log lines are expected test output, not live database incidents.

## Dedicated security self-review

Reviewed TOCTOU and internal-role promotion, role-definition changes, tenant/business/
module/site association changes, decision races, nonce/action confusion, replay after
closure, handle tampering/expiry/eviction, stale revision switching, metadata corruption,
stored XSS, raw-metadata leakage, CSRF scopes, legacy fallthrough, rollback/event atomicity,
nested transactions, and lock ordering. Corrections made during review:

- Removed the in-transaction plain identity probe before current locks.
- Locked actor parent/assignment range and role definitions, including scope changes.
- Added exact locked tuple/type checks, whole-business association ambiguity checks,
  and authoritative newest-issued-request selection in addition to material-successor checks.
- Protected current asset and registry eligibility during render validation.
- Made namespace validation independent of MySQL object-key ordering and kept corrupt
  history fail-closed; preserved unrelated metadata and original replay receipts.
- Prevented replay-only CSRF rotation and made the feedback-cap error customer-safe.
- Verified all customer/internal prose rendering escapes and dashboard behavior remains
  byte-for-byte equivalent apart from the permitted text changes.

The initial local review missed the two pre-merge findings corrected below. Actual lock scheduling,
FK/range protection, native PDO execution, and authenticated browser behavior remain
mandatory unproven gates; local fixtures do not establish them.

## PR #117 pre-merge correction pass

This local correction starts from `a13d876f5628095f9439980bbf0b78eb7267d4c6`
on the existing branch and PR; the authoritative baseline remains unchanged.
The GitHub stale-replay finding and the presentation-pair UI finding are addressed:

- Receipt checks no longer return immediately after actor/tenant authorization.
  Both receipt and open-mutation paths verify exact tuple and active association,
  newest selected issued request, non-revocation/non-supersession, materiality,
  no newer material revision, and current validation before receipt lookup can succeed.
- Terminal receipts require a decision timestamp and one consistent tuple:
  approved/customer_approved/pending_internal_review;
  approved/internally_approved/approved; or rejected/changes_requested/draft.
  Effective customer approval must match the selected request for approved states,
  and be absent for changes requested. These terminal receipts do not require the
  pre-decision site lock version. Open replay and every fresh append retain all
  original open-review/version requirements. Matching payload/key is still mandatory.
- Executable regressions return the exact original receipt with `replayed = true`
  for all three terminal states and verify zero metadata/event/lifecycle writes.
  Superseded, revoked, revoked-timestamp, replacement-on-same-revision, newer-material,
  inconsistent-state, and missing-decision-timestamp cases return conflict with no
  writes. Actor, membership, business, module, internal-role, association, and site
  eligibility-loss cases still deny matching replay without writes.
- Presentation preferences use two forms with fixed hidden targets. The DOM parser
  preserves both forms explicitly: six forms still represent five distinct actions.
  Every form retains CSRF and secret-field assertions. Tests verify exact option sets,
  successful actual rendered tone/emphasis submissions, and server rejection of both
  forged cross-category pairs with zero writes. No JavaScript is required.

Correction changes are limited to `SiteCustomerReviewGuard.php`,
`site-customer-review.php`, the M5B behavior and view/route tests, and this record.
Current focused totals are **258 behavior / 72 input-session / 118 view-route = 448**.
The full **48/48** standalone set and **189/189** repository lint pass, including
M2 approval and M5A regressions. Markdown and working/cached/committed diff checks pass.
Both findings are resolved in code and executable local regression coverage; this is
not a staging or browser PASS. M5B remains IMPLEMENTED LOCALLY / REVIEW REQUIRED.

## Environment distinctions and next gate

This was local Codex Desktop implementation with the configured local Git identity.
**Staging deployment: NOT RUN. Real staging MySQL/native-prepare/concurrency validation:
NOT RUN / NOT EXECUTABLE within this authorized local-only pass. Authenticated browser
mutation, responsive/accessibility/console/network validation: NOT RUN. Production
validation: NOT RUN / UNAUTHORIZED.** No live authentication session was minted.

Review the PR without automatically merging. A separately authorized staging gate must
exercise real independent connections for customer decisions and feedback replay,
role/business/module/association changes, registry/assets, and creation-versus-decision
lock ordering. Use designated synthetic actors through normal authentication for browser
mutations and stale-tab/role/tenant cases. M5C remains NOT STARTED and M5 is not closed.

No staging deployment, M5C/M6 implementation, provider operations, customer uploads,
notifications, public runtime/build/domain changes, or production action occurred.
