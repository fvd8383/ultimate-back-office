# Sprint 8.8 M5C — Local integrated customer QA corrections

Status: **M5C IMPLEMENTED LOCALLY / REVIEW REQUIRED**. M5 and Sprint 8.8 remain
**IN PROGRESS**. M1–M4, M5A and M5B remain COMPLETE / STAGING PASS / FORMALLY CLOSED.
M6 is NOT STARTED. Production remains UNAUTHORIZED / NOT DEPLOYED.

This record covers local implementation and synthetic validation on 2026-09-13.
It does not close M5C or M5. The complete authenticated browser matrix AND final
real-MySQL concurrency/eligibility/integrity gate remain mandatory after separately
authorized deployment. M5B's MySQL PASS does not replace the final M5C gate.

## Baseline and authority

- Repository: `fvd8383/ultimate-back-office`.
- Requested and actual baseline: `3280140789a796d6dbf3d77faa9a68e7d1db09e1`, PR #118.
- Local `main` began clean at `8cd63146713ef8fef26fd2861e960ac64ee1387a`.
  Fetch resolved origin/main exactly to the requested baseline; main fast-forwarded.
  There were no commits beyond the requested baseline to inspect.
- M5B closeout was present; M5C was NEXT / NOT STARTED before this work.
- Branch: `codex/sprint-8.8-m5c-integrated-qa`.
- Local Git identity: Frank Dalba, `frank@frankdalba.com`.
- Migrations 023/024 are unchanged against baseline; 025 is absent. No migration.
- The [M5 contract](sprint-8.8-m5-service-contract.md) remains authoritative.

## Reproduction and responsive correction

Inspected actual Website Manager, review, shared submissions and preview markup,
the served application stylesheet, and the preview renderer. Tested default
fieldset minimums, form controls, image-option labels, grid sizing, card padding,
long text/URLs, and iframe sizing before editing.

The empty synthetic review and full manager fit at 360px. The exact historical
approximately 11px excess was **not independently reproduced with its original
staging fixture**; no staging access occurred. A concrete defect was reproduced
with permitted feedback containing a long URL: unbroken plain text in the ordered
submission list increased the review card's min-content width, expanding the shared
grid column and pushing sibling sections/forms past the viewport. Baseline CSS with
the synthetic URL rendered document scroll width **1350px at a 360px viewport**.
Resetting fieldset minimums alone did not fix that reproduction.

The correction applies `overflow-wrap: anywhere` and `min-width: 0` only to the
customer review, shared customer submissions, preview heading/status shell and
manager business heading. The shared submissions rule also protects the internal
customer-submission projection. Review fieldsets can shrink, and textareas retain
vertical resizing while their horizontal size remains bounded. No existing global
CSS rule changed. There is no new overflow hiding, clipping, ellipsis, fixed height,
content truncation or cross-application design-system refactor.

Corrected synthetic views fit at 360/768/1280px, including long feedback, receipt,
approval, changes, lower-role, unavailable, error and private-preview states.
A 2,000-character unbroken string and a reserved scrollbar gutter also fit at 360px.
The exact historical staging fixture must still be retested at the final gate.

A separate local extraction of the actual manager presentation tail, rendered with
synthetic values and the real header/navigation/footer, covered the retained legacy
controls as well. It executed no route authentication, real DB or POST writes.
The render contained 70 controls including hidden inputs; this is layout evidence,
not a claim of authenticated legacy upload/save validation.

| Viewport | Document width | Content client / scroll width |
| --- | --- | --- |
| 360 | 360 | 328 / 328 |
| 768 | 768 | 736 / 736 |
| 1280 | 1280 | 976 / 976 |
| 640 | 640 | 608 / 608 |

640px is a reflow approximation for a 1280px window at 200%. It is **not actual
browser zoom**. No zoom PASS is claimed.

## Focus and accessibility decision

A `role=status` present when a new document loads is not sufficient evidence that a
screen reader announced the result. The one-time session-flash receipt now has
native `autofocus` and `tabindex=-1`, preserving its status role. The browser focuses
the escaped result after the user-initiated submission; Tab continues to the private
preview link. No JavaScript, dynamic selector, customer-supplied ID, fragment target
or new dependency is involved. Fresh GETs, ordinary read-only views and legacy-save
receipts have no generic autofocus. There is no positive tab index or focus trap.

The generic failed-submission branch previously returned only a paragraph/link.
It now renders a complete English document with title, viewport, main landmark,
heading, escaped alert, visible focus and recovery link. Existing HTTP status mapping
and safe failure messages are unchanged. Since these failures include stale review,
CSRF and eligibility conditions and do not preserve a form, the error directs the
user to reload rather than inventing an incorrect field-specific association.
No raw exception or submitted value enters the error document.

Each textarea references the existing plain-text/byte-limit guidance through one
static `aria-describedby` ID. Fieldset/legend structure, visible implicit labels,
required/optional text, required constraints and terminal receipts remain intact.
Scoped solid outlines improve visibility for review keyboard controls and the
receipt. Source/DOM inspection found no further heading or label defect on this
surface. Local Edge keyboard tests verify sequential reachability and leaving the
review region, plus logical continuation after success and error.

Screen reader: **NOT RUN — FINAL M5C STAGING/EXTERNAL BROWSER GATE**.
Windows Narrator is present at `C:/Windows/System32/Narrator.exe`; the intended
external method is an operator using Narrator with Microsoft Edge on Windows.
Record Windows, Edge and Narrator versions/settings, start Narrator, then use the
normal authenticated application with keyboard and scan-mode reading. Verify page
title/heading navigation, legends, labels, required fields, text-limit descriptions,
success/error speech, terminal receipts and entry/exit/reading of the titled inert
iframe. Capture what was actually spoken, including duplicate or missing receipt
announcements. Native focus/DOM assertions do not constitute a screen-reader PASS.

## Console, network, private data and security review

The local browser runner uses a disposable loopback fixture server, actual PHP view
renders and synthetic 303 redirects. It never serves application authentication or
uses real database sessions. Edge 153.0.4234.32 with Playwright 1.63.0 recorded no
console/page errors. Request hosts were loopback, `fonts.googleapis.com` and
`fonts.gstatic.com`; Google Fonts is the existing shared stylesheet dependency.
No new external dependency was added. The synthetic shell uses placeholder SVGs;
complete deployed asset/network/CSP inspection remains an external gate.

The private srcdoc preview still has `sandbox=""`, its descriptive title and its
restrictive CSP. The renderer, header policy and inactive links/forms are unchanged.
Local browser checks found no active links/forms/scripts or enabled controls inside
the preview; its document reflowed. No provider, analytics, chat or lead behavior was
added to generic review.

Customer and internal projections still use the established allowlists. Existing
M5A/M5B tests and new rendered-state leakage checks exclude private metadata,
actor identifiers, replay/payload hashes, correlation identifiers, storage keys,
source/facts sentinels and internal reasons/comments. Customer text remains escaped;
hostile text cannot inject DOM nodes, attributes or a focus target. Wrapping preserves
security messages and full customer prose. Internal customer-submission text remains
escaped; no new admin projection field was introduced.

The M5C scope suite verifies unchanged service/security/lifecycle implementations,
database, infrastructure, accounts and shared assets, plus an exact
two-change comparison of the manager route against baseline. Customer authorization,
internal-role denial, binding/nonces/replay, feedback namespace, stale/cross-tenant
handling, M2 transitions, M4 authority, immutable preview, legacy separation and
CSRF/303 behavior are unchanged. No M5B security regression requiring reopening was
found. This local self-review does not replace deployed integrity validation.

## Local tests and repeatability

| Gate | Result |
| --- | --- |
| Standalone `tests/*Test.php` | **50/50 PASS** |
| M5C semantic/view | **144 assertions PASS** |
| M5C scope | **19 assertions PASS** |
| M5C synthetic browser | **86 assertions PASS** |
| M2 regression | **4 suites PASS**, 95/69/23/103 assertions |
| M3 regression | **10 suites PASS**, 29/42/59/13/46/26/34/84/30/27 assertions |
| M4A / M4B / M4C regression | **4/3/3 suites PASS**, 209/267/92 assertions total |
| M5A behavior/view/scope | **103/40/58 assertions PASS** |
| M5B behavior/input-session/view-route | **258/72/118 assertions PASS** |
| PHP 8.4.24 lint | **193/193 PASS** |

Two older scope guards initially rejected the new stylesheet path. Their only
exception is the served app stylesheet; the new M5C scope suite verifies that all
existing rules remain unchanged and that additions are confined to customer review.
The full suite was rerun successfully after that correction. No service assertion
was removed or weakened. Existing M5B integration coverage remains authoritative for
Owner/Admin feedback, separate tone/emphasis, images, decisions, stale/tenant/internal
denials and legacy separation. New tests cover the presentation corrections and their
receipt/error integration seams without duplicating the M5B concurrency model.

Run all standalone PHP tests and lint every tracked/new PHP file. For the browser
runner, use an installed Playwright module and Edge (or an explicitly chosen installed
Playwright channel). No repository npm dependency/install is required:

```powershell
$env:M5C_PLAYWRIGHT_MODULE = 'absolute/path/to/node_modules/playwright'
node tests/WebsitePlatformM5CBrowser.cjs
```

The runner closes its browser and loopback server. The CLI-only view fixture creates
and destroys its synthetic PHP session. Local evidence and disposable presentation
helpers are under `%TEMP%/ubo-m5c-local-qa`, with no real authentication secrets.
The 360px receipt screenshot was visually inspected. No staging fixtures were created
and there is no staging cleanup to claim. Markdown links/fences/whitespace and Git
working, cached and committed diffs are checked in the commit/PR handoff.

## Final gate after review, merge and authorized deployment

Every row below remains **NOT RUN — FINAL M5C STAGING/EXTERNAL BROWSER GATE** or
**NOT RUN — FINAL M5C STAGING MYSQL GATE**, as appropriate. Do not infer PASS from
this local record or from M5B's prior focused results.

| Gate | Required evidence and acceptance |
| --- | --- |
| Environment/authentication | Confirm exact deployed SHA and staging environment; designated synthetic Owner/Admin/lower-role/foreign-tenant/internal actors; normal OTP login, no session/cookie forgery or bypass. |
| Responsive | Actual authenticated manager and private preview at 360/768/1280 CSS px; repeat original overflow fixture, long text/URLs and image labels, legacy controls and terminal/error states; measure page/container widths and retain screenshots. |
| Actual 200% zoom | Set Edge's real browser zoom to 200%; record setting and resulting viewport, reach every required control, inspect clipping/overlap and scroll the private iframe. Reduced viewport and CSS transforms are not substitutes. |
| Keyboard/screen reader | Execute the Narrator/Edge method above, visible focus, logical Tab continuation and no trap. Verify one-time receipt focus and actual spoken result after successful POST303GET, rejected POST and terminal decisions. |
| Console/network/CSP | Inspect complete authenticated flow and preview requests/console, response CSP, unexpected navigation, forms, analytics/chat/lead/provider calls and private browser text/attributes. Distinguish approved shared assets. |
| Integrated workflows | Issue review through M4; read-only lower role; Owner/Admin feedback, tone, emphasis and image request; immutable preview; customer approval then explicit internal visibility/review; independent request-changes fixture; stale retained tab, foreign tenant/internal-role denial and legacy separation. |
| Final concurrency | Fresh MySQL connections/native prepares: competing decisions, same/distinct nonce feedback, feedback versus decision, material successor versus decision, lock timeout/deadlock rollback. Check one legal winner/receipt/event set, no lost updates, no partial writes or retargeting. |
| Current eligibility | Race membership/role/internal-role/actor/business/module/association changes against feedback and decisions. Recheck under locks through commit. Shared global-module disabling requires isolated authorized tooling; preserve the previous shared-impact limitation if unavailable, never relabel it PASS. |
| Approval/stale integrity | Validate exact request/revision/snapshot, effective customer approval, terminal replay and obsolete replay rejection, supersession/revocation, component/asset rights eligibility and malformed metadata. Independently exercise valid-history size boundaries or explicitly record any unexecuted boundary. |
| Metadata/lifecycle/private data | Compare before/after immutable hashes, unrelated metadata, approvals, M2 states and audit counts; verify no public pointer, publication, automatic internal request, legacy launch flag or private projection leakage. |
| Cleanup/evidence/closeout | Store sanitized SHA/version/case evidence; log actors out; reconcile approved synthetic domain/audit/authentication artifacts and all affected table counts. Report PASS/FAIL/NOT RUN honestly. Formal M5 closeout is a later evidence-backed action only after all required gates pass. |

M6, domains/routing, DNS/SSL, public generic runtime, LeadHub ingestion, providers and
production are untouched. No deployment or merge is authorized by this record.

## File inventory

Added:

- `private/views/site-customer-review-error.php`
- `tests/WebsitePlatformM5CViewTest.php`
- `tests/WebsitePlatformM5CScopeTest.php`
- `tests/WebsitePlatformM5CBrowser.cjs`
- `tests/support/WebsitePlatformM5CViewFixture.php`
- `docs/sprint-8.8-m5c-local-implementation.md`

Modified:

- `private/views/site-customer-review.php`
- `private/views/site-customer-submissions.php`
- `private/views/site-customer-preview.php`
- `public/app/247sp/website-manager.php`
- `public/app/assets/css/design-system.css`
- `tests/WebsitePlatformM2ScopeTest.php`
- `tests/WebsitePlatformM3ScopeTest.php`
- `docs/sprint-8.8-m5-service-contract.md`
- `docs/sprint-8.8.md`
- `docs/codex-handoff.md`
- `docs/247sp-website-generation-architecture.md`

Database changes: none. Routing changes: none. New application URLs: none.
