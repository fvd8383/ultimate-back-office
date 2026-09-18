# Sprint 8.8 M5C — Local integrated customer QA corrections

## Current M5 closeout — 2026-09-17

**M5 COMPLETE FOR SPRINT PROGRESSION** under the product-owner acceptance decision.
**M5C ACCEPTED / NARRATOR FOLLOW-UP DEFERRED**.

| Milestone | Current status |
| --- | --- |
| M5A | COMPLETE / STAGING PASS / FORMALLY CLOSED |
| M5B | COMPLETE / STAGING PASS / FORMALLY CLOSED |
| M5C | ACCEPTED / NARRATOR FOLLOW-UP DEFERRED |
| M5 | COMPLETE FOR SPRINT PROGRESSION |
| M6 | NEXT / NOT STARTED |
| Production | UNAUTHORIZED / NOT DEPLOYED |

Authoritative deployed application SHA: `70a3051f73874e7268b9c1bba45bf19d41f9432a`.
Latest deployed title-regression PASS SHA-256:
`de1e215f7716e550a6ab993872cb50eaaf7d4a2d5a2dc03c4159fb9a569368c7`.
These are existing evidence references; this documentation closeout does not access staging.

Further Windows Narrator validation is intentionally deferred. The product owner
accepts the implemented behavior as sufficient for the current product stage based
on the completed structural/accessibility-tree, keyboard, responsive/zoom, regression,
and recorded Narrator evidence. Feedback and customer-approval title/detail tests
passed. The later operator continuation also recorded changes-requested title,
detailed receipt, and later changes-state PASS results before testing was stopped.
Those completed observations are preserved; the remaining terminal-approval,
error/recovery, iframe, and combined trap checks were not executed in that continuation.

See [M5 closeout](sprint-8.8-m5-closeout.md) for the immutable evidence chain,
completed observations, and **M5C Narrator Follow-Up** backlog. The original requested
follow-up scope includes the changes flow and both terminal states; completed changes
checks are historical PASS evidence, and any future revalidation remains optional.
Remaining checks may resume later without blocking M6 development unless a future
release requirement makes them mandatory. This acceptance does not establish full
accessibility validation. No known application accessibility failure remains from
completed cases; deferred checks are validation items, not established defects.

Migrations 023/024 are unchanged; 025 is absent. No application, deployment,
configuration, provider, domain, public-runtime, or production change occurs here.
M6 implementation has not started. Sprint 8.8 remains **IN PROGRESS**.
This current decision supersedes earlier closure prerequisites; dated snapshots and
historical failure/tooling-block reports below retain their original verdicts.

## Customer-approval title clarification — 2026-09-17 (historical pre-deployment snapshot)

**M5C APPROVAL TITLE CLARIFICATION IMPLEMENTED LOCALLY / REVIEW REQUIRED**.
M5C and M5 remain **IN PROGRESS**; M6 remains **NOT STARTED**. Production remains
**UNAUTHORIZED / NOT DEPLOYED**. Recorded Narrator validation remains pending.

Correction baseline: `a2288b041ce7ff116fed839c7702fd64b9767770` (merged PR #122).
Branch: `codex/sprint-8.8-m5c-approval-title-clarification`.
Origin/main matched that baseline exactly; local main was fast-forwarded and the
working tree was clean before the new branch. No intervening commits were present.
The last reported staging SHA remains `8d198af9c96c52f879252932456eef29cb0b97cb`;
this task does not access or change staging.

The completed automated review on [PR #122](https://github.com/fvd8383/ultimate-back-office/pull/122)
found: “Preserve the pending-review state in the approval title.” The previous
“Website approved” prefix did not distinguish customer approval from internal
approval, legacy launch authority, publication or deployment. This follow-up changes
only the output string of the existing exact receipt match arm:

```php
'Approved by customer; awaiting internal review.' => 'Customer approval recorded; internal review pending',
```

The matched receipt string, workflow response, consumed session flash and lifecycle
are unchanged. The existing Website Manager `$pageTitle` → `private/views/header.php`
→ `shared/ui/layout/header.php` path still escapes the title through `e($pageTitle)`.
No shared header change is necessary.

| Situation | Current complete title |
| --- | --- |
| Immediate customer approval receipt GET | Customer approval recorded; internal review pending - 247SP Website Manager - Ultimate Back Office |
| Immediate feedback/advisory receipt GET | Feedback sent - 247SP Website Manager - Ultimate Back Office |
| Immediate changes receipt GET | Changes requested - 247SP Website Manager - Ultimate Back Office |
| Normal GET, later terminal GET, legacy save, unknown/hostile receipt | 247SP Website Manager - Ultimate Back Office |

GET-only/non-legacy conditions, default behavior and allowlisting remain unchanged.
No customer text, POST field, URL parameter or metadata supplies title text.
The detailed approval receipt remains exactly
“Approved by customer; awaiting internal review.”

The scope test now uses the correction baseline and restores exactly one corrected
match arm to prove that the entire application file differs only by this output-string
replacement. All protected-tree assertions are retained. Private classes/views,
database, infrastructure, accounts, webhooks, shared headers, receipt JavaScript and
CSS remain unchanged, as do authorization, CSRF, replay, tenant isolation, approval
services, lifecycle, POST dispatch, redirects, errors and legacy authority.
Migrations 023/024 remain unchanged; 025 remains absent.

View and synthetic Edge expectations use the exact corrected complete approval title
and explicitly verify the unchanged detailed approval receipt. Feedback, changes,
normal/later/legacy, hostile-input, single-status, template, noscript, one-time population
and focus assertions remain intact. The existing local synthetic runner is reused;
no staging fixtures, audio recording or transcription API call occur.

### Local clarification validation

| Gate | Actual result |
| --- | --- |
| Standalone PHP suites | **50/50 PASS**, including all M2–M5B regressions |
| M5C view / scope | **314 / 38 assertions PASS** |
| Synthetic Edge 153.0.4234.32 | **202 assertions PASS**, zero console errors |
| Tracked PHP lint | **193/193 PASS** |
| Updated Markdown | **5/5 PASS**, local links and fenced code blocks |
| Git | `git diff --check` PASS; protected application trees unchanged |
| Migrations | 023/024 unchanged; 025 absent |

The scope assertion proves exactly one output-string replacement against the correction
baseline. Edge verifies the exact server-rendered title before receipt JavaScript,
after synthetic POST -> 303 -> GET and with JavaScript disabled. No new network
dependency appears. These local synthetic DOM/title results are not authenticated
staging, actual zoom, MySQL or Narrator speech evidence. Local logs remain outside Git
at `%TEMP%/ubo-m5c-approval-title-local-qa-20260917`.

### Recorded validation still required

Document-title narration provides immediate orientation and must communicate both
customer approval and pending internal review. The distinct detailed polite status
is evaluated separately; title narration must not be miscounted as a duplicate
detailed live-region announcement. The previously documented two-observation,
idle-aware, up-to-60-second acceptance protocol remains unchanged.

The latest recording's current interpretation remains
**NARRATOR SUCCESS TEST INCONCLUSIVE — POLITE SPEECH QUEUE STILL ACTIVE AT RECORDING CUTOFF**.
Historical reports, original verdicts, recordings and evidence hashes are unchanged.
The merged PR #122 implementation description below is explicitly historical; this
section supplies the authoritative current approval title and correction scope.

No staging access, deployment, provider/domain/public-runtime operation or production
action occurs. This is a new follow-up branch/PR, not an amendment to merged history.
Review and separately authorized deployment/recorded Narrator validation remain
required. No Narrator PASS or M5 closure is claimed; M5C/M5 remain open and M6 is
NOT STARTED.

## Post-submit orientation enhancement — 2026-09-17 (historical merged PR #122)

**M5C POST-SUBMIT ORIENTATION ENHANCEMENT IMPLEMENTED LOCALLY / REVIEW REQUIRED**.
M5C and M5 remain **IN PROGRESS**; M6 remains **NOT STARTED**. Production remains
**UNAUTHORIZED / NOT DEPLOYED**. No staging access or deployment occurs in this task.

Baseline: `8d198af9c96c52f879252932456eef29cb0b97cb` (merged PR #121).
Branch: `codex/sprint-8.8-m5c-post-submit-page-title`.

### Current interpretation of the last recording

**NARRATOR SUCCESS TEST INCONCLUSIVE — POLITE SPEECH QUEUE STILL ACTIVE AT RECORDING CUTOFF**

The fixed 15-second acceptance window was too short to distinguish a missing polite announcement from an announcement queued behind Narrator's automatic page-reading speech. The transcript showed Narrator remained busy reading page chrome at cutoff. This run is therefore retained as historical failed-test evidence but is not treated as proof of a live-region implementation defect.

The 15.013-second raw transcript remains:

> Send feedback button. Loading complete. 247SP Website Manager Ultimate Back Office.
> 247SP Website Manager Ultimate Back Office has finished loading. 247SP Website Manager.

The original report and its original failed-test verdict are unchanged. Their local
SHA-256 values were rechecked read-only; no historical evidence file was rewritten:

- Recorded report: `a3a8094aba91509f5d468e200a9d411d10f5c1068c5c6a2e13d752a31dfd3f11`.
- WAV: `0e220c02b8e3731d4d17b8e0b65227dff1349ac094baa36f63a1dd05d5f6f669`.
- Prior deployed second-correction regression: `4075aad176eddc7c9290671a4eaa70f5cf4bc26e6339365a3e7bd348de5e34f1`.

The report remains at
`/home/codex-validation/ubo-sprint-8.8-m5c-narrator-final-20260917T021615Z/SPRINT-8.8-M5C-FINAL-RECORDED-NARRATOR-VALIDATION.md`.
Durable local originals remain under
`%USERPROFILE%/Documents/UBO-Validation-Evidence/M5C-Narrator-20260917T021615Z/`.
Older server, browser and Narrator evidence hashes in the dated records below remain
unchanged. The current interpretation supersedes the fixed-window acceptance method;
it does not retrospectively alter any original observation, hash or report verdict.

[WAI-ARIA aria-live guidance](https://www.w3.org/TR/wai-aria/#aria-live) gives polite
updates low priority and generally avoids interrupting the current task. Presentation
is expected at a graceful opportunity; user/assistive-technology behavior can vary.
The short recording cannot distinguish a queued update from one that never arrives.
This enhancement addresses immediate server-navigation orientation independently of
polite live-region scheduling; it does not establish a Narrator PASS.

### Discovered title architecture and narrow change

`public/app/247sp/website-manager.php` already sets the complete page-specific
`$pageTitle` immediately before including `private/views/header.php`. That view
retains the supplied value and includes `shared/ui/layout/header.php`, whose title
is rendered through the existing `e($pageTitle)` HTML-escaping helper. There is no
need for a new shared API, a shared-header edit, client-side title mutation, or a
second application file. Existing punctuation is a spaced hyphen.

The only application change is eight lines beside the existing Website Manager title
assignment. For GET with the existing legacy-saved flag false, an exact strict match
of the consumed, application-controlled review flash selects one of three fixed
prefixes. Unknown values select no prefix. The title is never built from receipt
prose, customer feedback, metadata, POST fields or URL parameters. The existing session
flash read/unset, action dispatch and redirect code are unchanged, so the prefix lasts
only for the immediate receipt-bearing GET.

| Situation | Complete title |
| --- | --- |
| Advisory receipt (feedback, tone, emphasis, image request) | Feedback sent - 247SP Website Manager - Ultimate Back Office |
| Approval receipt | Website approved - 247SP Website Manager - Ultimate Back Office |
| Changes receipt | Changes requested - 247SP Website Manager - Ultimate Back Office |
| Normal GET, later terminal GET, legacy save, unknown/hostile receipt | 247SP Website Manager - Ultimate Back Office |

No current terminal lifecycle state is consulted for the transient title. Later
approved/changes GETs retain their existing body labels and the normal page title.
`Website settings saved.` is not an M5C title source. Even a recognized review
receipt cannot add the prefix on POST or when the legacy-saved flag is true.

### Preserved receipt and security behavior

The deployed single-status architecture remains byte-equivalent: one visible,
initially empty status with `role=status`, `aria-live=polite`, `aria-atomic=true`,
one escaped inert template and an escaped noscript fallback. The same status receives
plain text once on the existing frame/later-task schedule. There is no assertive or
alert success role, autofocus, focused status, hidden announcer, second live region,
clone, timing change, network call or storage operation.

The concise complete document title differs from the detailed receipt and serves
page-load orientation. The detailed receipt remains unchanged, including the advisory
preview boundary and approval/internal-review wording. For changes, the short prefix
is the requested “Changes requested” wording within the complete document title;
title orientation must not be miscounted as another detailed status occurrence.

Protected private/classes, database, infrastructure, accounts, webhooks, shared and
private/views trees, and all existing CSS/JS assets, remain byte-equivalent. Scope
coverage permits only the Website Manager path and reconstructs its baseline by
removing the exact title presentation block, proving all remaining route bytes are
unchanged. Authorization, tenant isolation, CSRF, replay, lifecycle, approval, POST
dispatch, 303 redirects, error mapping/focus/recovery and legacy behavior are preserved.
Migrations 023/024 remain unchanged; 025 remains absent.

The synthetic fixture executes only the actual route's trusted title presentation
block in a CLI test function, restoring its controlled request method afterward. It
never executes route authentication, real DB work or dispatch. Repository PHP source
is evaluated, not customer text. Actual shared header rendering verifies escaped
output. The existing backend still rejects HTML-like feedback; a separate hostile
view projection tests defense-in-depth title/markup safety without relaxing input rules.
Tests cover unknown receipts, non-string values, almost-matching strings, malicious
GET/POST data, later terminal states, legacy exclusion and hostile template/history text.

### Local validation

| Gate | Actual result |
| --- | --- |
| Standalone PHP suites | **50/50 PASS** |
| M5C view / scope | **313 / 37 assertions PASS** |
| Synthetic Edge 153.0.4234.32 | **201 assertions PASS**, zero console errors |
| M2 | **4 suites PASS**, 95/69/23/103 assertions |
| M3 | **10 suites PASS**, 29/42/59/13/46/26/34/84/30/27 assertions |
| M4A / M4B / M4C | **4/3/3 suites PASS**, 209/267/92 assertions total |
| M5A behavior/view/scope | **103/40/58 assertions PASS** |
| M5B behavior/input-session/view-route | **258/72/118 assertions PASS** |
| Tracked PHP lint | **193/193 PASS** |
| Markdown / Git | Local links/fences and `git diff --check` PASS |
| Migrations | 023/024 unchanged; 025 absent |

Existing receipt assertions are retained: empty region before script execution; the
same status node receives one population; exactly one detailed accessible copy;
no hidden announcer or focus movement; re-execution is inert; noscript fallback and
hostile text remain safe. Added Edge assertions check the server-rendered title before
receipt JavaScript is released, after synthetic POST -> 303 -> GET, on later terminal
GETs and in actual JavaScript-disabled contexts. Title feedback adds no asset or network
dependency. Synthetic redirects and DOM/title evidence are not authenticated HTTP or
actual Narrator evidence. Local logs are outside Git at `%TEMP%/ubo-m5c-title-local-qa`.

### Revised recorded Narrator acceptance after deployment

Review, merge and separately authorized deployment must precede the next real test.
Use the recorded Windows output methodology: NAudio 3.1.0 WASAPI render-loopback,
actual selected output device/format, no microphone, gpt-transcribe and original WAV/
raw-transcript hashes. Preserve a durable evidence directory and HTTP/network trace.
Authenticate with Narrator off and keep secrets outside recordings. One submission
per dedicated WAV; no Tab, navigation, focus change or manual full-page read after
submitting. Evaluate **two separate observations** from that single-action recording:

1. **Immediate orientation — mandatory.** Initial page-load speech must communicate
   the concise result through the title before traversing the full sidebar, for example
   “Feedback sent - 247SP Website Manager - Ultimate Back Office”. Check near the start
   of the WAV. A local document.title assertion is not an audio PASS.
2. **Detailed polite receipt.** Allow a graceful idle opportunity and record up to
   **60 seconds**. Exactly one recognizable detailed status occurrence passes. Two or
   more detailed occurrences fail. If Narrator remains continuously busy reading other
   content at 60 seconds, classify **INCONCLUSIVE — NARRATOR NEVER REACHED IDLE**,
   not application FAIL. If Narrator reaches genuine idle, allow a reasonable additional
   observation interval (for example 5–10 seconds); a still-missing detailed receipt
   then fails. A cutoff without enough observed idle time is inconclusive and must not
   be silently converted into failure or PASS. Do not restore a fixed 15-second failure
   threshold or compensate by changing application JS delays.

Preserve title speech and detailed receipt observations separately in the transcript
analysis; the concise title is not an announcement clone or a second detailed receipt.
Document any missing/inconclusive observation honestly. Complete the required error,
terminal and iframe/keyboard cases and cleanup before any M5C/M5 formal closure.
M5 remains IN PROGRESS, M6 NOT STARTED, production UNAUTHORIZED / NOT DEPLOYED.
No provider/domain/public-runtime/production work is included in this enhancement.

## Second correction — 2026-09-17 (historical)

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
