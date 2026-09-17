# Sprint 8.8 M5C — Local integrated customer QA corrections

## Current second correction — 2026-09-17

**M5C SECOND ACCESSIBILITY CORRECTION IMPLEMENTED LOCALLY / REVIEW REQUIRED**.
M5 remains **IN PROGRESS**; M6 remains **NOT STARTED**. Final recorded Narrator
revalidation remains mandatory after review, merge and separately authorized deployment.
No staging access, deployment, provider/domain/public-runtime or production work occurs
in this local correction. Historical evidence and dated implementation sections below
are preserved; their superseded receipt architectures are not the current design.

Baseline: `3c20b113a42fe2d94b27a1dd0fbc687a21b171c4` (merged PR #120).
Branch: `codex/sprint-8.8-m5c-narrator-deduplication`.

### Confirmed defect and correction

The first correction made the success message audible without subsequent Tab or
navigation. One submission, recorded to one Windows WASAPI WAV, then produced:

> Sent for consideration. This does not change your preview. Sent for consideration.
> This does not change your preview.

The transcript contains at least two consecutive recognizable receipt occurrences.
The original recording reached its 90-second ceiling while Narrator continued; it
proves duplication but does not establish the total utterance count beyond that window.
The post-recording browser inspection timed out, so that run did not retain its HTTP
trace. These limitations remain in the immutable failure report and are not relabeled
as a passing run. The two-accessible-copy design (visible receipt plus hidden live
announcer containing the same text) is the architectural cause targeted by this fix;
no undocumented Narrator internals are asserted.

The success view now renders one visible-style, initially empty paragraph with
`role=status`, `aria-live=polite`, `aria-atomic=true` and the static receipt marker.
An adjacent inert `template` contains the server-escaped message. A server-escaped
`noscript` paragraph supplies readable fallback when JavaScript is disabled. In a
JS-enabled document neither source creates a second accessible receipt. The empty
paragraph needs no reserved height or hidden CSS; it becomes visibly sized when filled.

The deferred same-origin asset requires exactly one marked receipt and exactly one
source template in its review section. After window load, one rendered frame and
one later zero-delay task, it assigns `source.content.textContent` to the same
receipt's `textContent` only if that receipt is still empty. There is no second timing
cycle, focus call, assertive/alert workaround, role removal/re-addition, cloning, HTML
insertion, network operation or storage. Asset re-execution neither clears nor
repopulates the status. Normal and legacy GETs remain inert.

The separate `.site-customer-announcer` markup and its entire visually hidden CSS
rule are removed. All other stylesheet rules remain equivalent to baseline.
The exact receipt sentence was also present as static preference-form guidance;
it now reads “A preference request is advisory and does not change your preview.”
The advisory/preview boundary and existing M5B consequence assertion remain intact.
For approved/changes states, the persistent status label is identical to the receipt.
The view omits that duplicate label only when the current non-legacy receipt supplies
exactly the same text. Subsequent GETs without a receipt retain the original label;
no backend DTO, state or lifecycle behavior changes.

### Security and scope

Application changes are limited to:

- `private/views/site-customer-review.php`;
- `public/app/assets/js/customer-review-status.js`;
- `public/app/assets/css/design-system.css` (obsolete rule removal only).

Server escaping prevents template/noscript termination, HTML/attribute/script injection
and selector changes. Tests round-trip hostile closing tags, executable-looking text,
quotes, ampersands and Unicode through source -> textContent exactly. Customer text
never enters executable JavaScript or a selector. The fixture server's optional script
probe exists only in browser tests, to verify enabled/disabled document execution.

The scope baseline is the exact merged SHA above. Protected service, authorization,
replay, CSRF, lifecycle, database, infrastructure, accounts, webhooks, shared, application
routing and error-view trees remain unchanged. The complete Website Manager route,
400/403/404/409 mapping, error alert/focus/recovery and legacy dispatch are preserved.
No migration: 023/024 are unchanged; 025 remains absent. No validation audio helper,
NAudio package, transcription code, API key or auth state is added to this repository.

### Local validation

| Gate | Actual result |
| --- | --- |
| Standalone PHP suites | **50/50 PASS**, including focused reruns after guidance wording correction |
| M5C view | **267 assertions PASS** |
| M5C scope | **34 assertions PASS**, bound to 3c20b113a42fe2d94b27a1dd0fbc687a21b171c4 |
| Synthetic Edge 153.0.4234.32 | **176 assertions PASS**, zero console errors |
| Actual JavaScript-disabled Edge contexts | Feedback, approved, changes and hostile receipts: visible exact fallback, empty status, no document script execution, one accessible copy |
| M2 | **4 suites PASS**, 95/69/23/103 assertions |
| M3 | **10 suites PASS**, 29/42/59/13/46/26/34/84/30/27 assertions |
| M4A / M4B / M4C | **4/3/3 suites PASS**, 209/267/92 assertions total |
| M5A behavior/view/scope | **103/40/58 assertions PASS** |
| M5B behavior/input-session/view-route | **258/72/118 assertions PASS**; no M5B tests changed |
| Tracked PHP lint | **193/193 PASS**, with changed-file rechecks |
| Markdown and Git | Local links/fences and `git diff --check` PASS |

The synthetic Edge test holds the script response before execution, inspects the empty
status and inert source, and checks zero matching accessible StaticText nodes. After
execution it verifies the same status node, visible exact text, one accessibility-tree
copy and one population mutation. Re-execution produces no second mutation, node or
accessible copy. Terminal later-GET labels, normal focus/Tab, hostile text, disabled-JS
fallback, reflow, error recovery and inert preview regressions are covered. Loopback
fixtures simulate POST -> 303 -> GET; no authenticated staging flow is claimed.

**DOM/live-region mutation evidence only — actual Narrator audio validation required after deployment.**

Local suite/lint/browser logs are outside the repository at
`%TEMP%/ubo-m5c-deduplication-local-qa`. Browser contexts and fixture server close
automatically. No staging fixtures were created and no staging cleanup is claimed.

### Immutable evidence and final audio gate

| Evidence | SHA-256 |
| --- | --- |
| Server-side PASS | `f362926d5ac0a10018794a1c8107750d52e435bccb774355c183365761189727` |
| Broad browser matrix | `8ddc215e371da0ec9679d59ef009d09b1ec2134e62305bb9d295ebd52f04fcfe` |
| Initial Narrator failure | `013996df0a39dcf2392fe4dc3a6e6f81ee4d1918d7f814dfa6289ab236e6da5e` |
| Deployed first-correction regression | `5f9459afbbbf5ad35212e9f57e34798a3b51432e3abd0b3a443cf82ddc0128eb` |
| Recorded duplicate failure report | `d85dbb2e4cc390c2e040a7994a9f48a79eff2d9af816d7cf04a4f8e8b6bc2968` |
| Single-submission success WAV showing duplication | `b58a7b2113e6cb871ce8d6e6504c554c31419726cb8102c7a76447b3cbade30d` |

Audio report:
`/home/codex-validation/ubo-sprint-8.8-m5c-narrator-audio-final-20260917T011223Z/SPRINT-8.8-M5C-FINAL-NARRATOR-AUDIO-VALIDATION.md`.
The local report and WAV hashes were rechecked without accessing staging. Older hashes
are preserved from authoritative evidence; no historical report was rewritten.

Final revalidation must retain the working methodology: **NAudio 3.1.0**, Windows
**WASAPI loopback**, **Speakers (Realtek(R) Audio)**, **48 kHz stereo PCM16** and
**gpt-transcribe**. Keep recorder/transcription tooling disposable and outside UBO;
read the API key from process environment only. Record **ONE browser action per WAV**,
with capture/action timestamps, output device, original WAV and transcript/raw JSON
hashes. Establish quiet output and keep Narrator off during authentication; no microphone
capture or spoken secrets. Preserve a complete speech window and browser trace rather
than treating a truncated recording as a complete passing result.

**PASS:** one successful submission produces exactly **one** recognizable receipt
occurrence in its dedicated WAV, automatically without subsequent Tab/navigation.
**FAIL:** that one submission produces **zero** or **two-or-more** recognizable
occurrences. Never combine submissions into one WAV. The recording and transcription,
not operator memory or a DOM mutation count, are authoritative. Record error/recovery,
approved, changes and private-preview cases separately, then verify cleanup. All
required recorded Narrator cases must pass before M5C/M5 can close; M6 stays NOT STARTED.

## First correction — 2026-09-14 (historical)

**M5C ACCESSIBILITY CORRECTION IMPLEMENTED LOCALLY / REVIEW REQUIRED**.
M5C and M5 remain **IN PROGRESS**; M6 remains **NOT STARTED**. No staging PASS,
deployment, formal closure or production authorization is claimed by this change.

Baseline: clean current `main` at `026fbeb07e5700dd87436b30de00ee605abd82be`.
Branch: `codex/sprint-8.8-m5c-narrator-announcement`.

Actual Windows Narrator validation on that deployed baseline failed the automatic
success announcement: the browser focused the server-rendered `role=status`
receipt, but the operator heard its text only after pressing Tab. Focus and a
prepopulated status node did not establish a reliable live announcement. This is
the observed design failure; no undocumented Narrator-internal cause is asserted.
The repeated history label was separate from the missing success announcement.
The error-flow `GET /favicon.ico` 404 was attributed to optional browser fallback
and is non-blocking; no favicon change is included.

The correction keeps escaped visible receipt text with a static
`data-customer-review-receipt` marker, removing its role, tabindex and autofocus.
A separate initially empty `.site-customer-announcer` has `role=status`,
`aria-live=polite` and `aria-atomic=true`. It is visually hidden with a narrowly
scoped rule that remains exposed to accessibility APIs. The focus outline now
targets only the existing alert receipt; the complete error document is unchanged.

One dedicated same-origin deferred asset,
`public/app/assets/js/customer-review-status.js`, waits for window load (or handles
an already loaded document), then a rendered frame and a zero-delay queued task.
It copies the visible receipt's `textContent` to the established region's
`textContent` once. It never moves focus, interprets HTML, constructs script,
uses customer-controlled selectors or URLs, or initiates a network/database write.
Re-executing the asset cannot repopulate the region. Without JavaScript the visible
receipt remains available. Normal GETs and legacy saves emit neither the generic
marker/announcer nor its script.

Inspection found no shared application JS loader or existing visually hidden
utility. The shared header/footer offer no reusable script mechanism; a few other
pages use inline page scripts, which this correction does not extend. The manager
uses `frame-ancestors 'self'` without a restrictive `default-src`/`script-src`;
the same-origin external path is permitted. The private preview's separate
`script-src 'none'` CSP remains unchanged. No CSP was weakened, dependency added,
or backend/auth/CSRF/replay/lifecycle/dispatch behavior modified.

### Immutable validation evidence

- Server-side final PASS SHA-256:
  `f362926d5ac0a10018794a1c8107750d52e435bccb774355c183365761189727`.
- Broader external-browser evidence SHA-256:
  `8ddc215e371da0ec9679d59ef009d09b1ec2134e62305bb9d295ebd52f04fcfe`.
- Narrator/network supplemental failure SHA-256:
  `013996df0a39dcf2392fe4dc3a6e6f81ee4d1918d7f814dfa6289ab236e6da5e`.
- Supplemental report:
  `/home/codex-validation/ubo-sprint-8.8-m5c-accessibility-final-20260914T002109Z/SPRINT-8.8-M5C-NARRATOR-NETWORK-CLOSURE.md`.

These reports remain immutable. This local correction does not replace or convert
the failure evidence to PASS. No staging access occurred in this correction task.

### Correction validation

All **50/50 standalone PHP suites PASS**; full tracked PHP lint **193/193 PASS**.
M2/M3/M4 regressions pass. M5A behavior/view/scope remains **103/40/58**;
M5B behavior/input-session/view-route remains **258/72/118**. Failure-injection
exceptions printed by those suites are expected test output, not failed suites.
Markdown local-reference/fence checks and `git diff --check` pass. Migrations
023/024 are byte-equivalent to the correction baseline; migration 025 is absent.

M5C view: **200 assertions**. M5C scope: **34 assertions**. The scope suite preserves
the existing global CSS and all protected backend/schema/security/route/error
trees, and allows only the exact new JS asset in the public application changes.
M2/M3 allowlists add that single path; their protected-tree assertions remain intact.

Local Edge 153.0.4234.32 synthetic browser: **118 assertions PASS**, no console errors.
The test holds the script response to prove the live region exists empty before
execution; after load it verifies exact plain-text population once, even if the
asset executes again. It covers simulated POST/303/GET, normal Tab order, hostile
text, legacy/normal GET separation and no-JavaScript fallback, while retaining the
existing reflow, error recovery and inert-preview regressions.

**DOM/live-region mutation evidence only — Narrator validation still required after
deployment.** The test neither authenticates against staging nor proves audible
speech. Separately authorized post-merge/deployment Narrator revalidation remains
mandatory, including success without Tab, error/recovery, terminal states, detailed
guidance and private iframe navigation. Do not close M5C/M5 on local tests alone.

The original implementation history below is retained as a dated snapshot; its
success-autofocus design and earlier pending-gate wording are superseded by this
correction and the immutable evidence above.

## Original local implementation — 2026-09-13 (historical)

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
