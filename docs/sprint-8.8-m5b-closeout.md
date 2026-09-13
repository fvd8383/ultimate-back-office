# Sprint 8.8 M5B — Customer Feedback and Decisions Closeout

## M5B staging closeout — 2026-09-13

**M5B COMPLETE / STAGING PASS / FORMALLY CLOSED** on deployed/validated SHA
`8cd63146713ef8fef26fd2861e960ac64ee1387a`. M5A remains **COMPLETE / STAGING PASS /
FORMALLY CLOSED**. M5 is **IN PROGRESS**; M5C is **NEXT / NOT STARTED**; M6 is
**NOT STARTED**. Migration 025 is absent/reserved for M6. Production is
**UNAUTHORIZED / NOT DEPLOYED**. This is an external documentation export; the deployed
checkout was not edited and no deployment, migration, commit, or PR occurred in the
completion run. This document records the M5B closure boundary.

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

## Backend validation summary and retained limitations

The backend report in the evidence chain records actual deployed execution, not
new tests run during this documentation import:

| Gate | Recorded result |
| --- | --- |
| Standalone suites | 48/48 PASS |
| Focused M5B assertions | 258 behavior + 72 input/session + 118 view/route = 448 PASS |
| Repository PHP lint | 189/189 PASS |
| Database | MySQL 8.4.8; native PDO prepares; emulate prepares false |
| Authorization matrix | PASS for Owner/Admin authority, read-only and tenant/internal-role denials, and current eligibility checks |
| Seven transaction-time authorization races | PASS: membership removal versus feedback and approval, business suspension, customer-association deactivation, internal-role grant, actor deactivation, and business-module deactivation |
| Concurrent decisions | One winner and one conflict; one legal decision/lifecycle event pair |
| Same-nonce feedback | Exactly one durable append and one audit event; other call replayed the receipt |
| Distinct concurrent feedback | Both entries preserved, with no lost update |
| Feedback versus decision | Legal serialization; later fresh feedback rejected without writes |
| Material successor versus decision | Stale approval rejected without retargeting |
| Lock timeout and deadlock | Feedback/approval timeouts rolled back without partial writes; MySQL1213 selected the controller as deadlock victim, which rolled back while the application survived with one legal approval |
| Metadata/replay/integrity | PASS; unrelated metadata and immutable composition preserved; malformed input failed closed |
| Terminal replay | Exact receipts replayed without writes after customer approval, explicit internal approval, and request changes |
| Obsolete replay | Superseded, revoked, replaced, and newer-material-review replay rejected |
| Feedback cap | Entries 1–20 accepted; entry21 rejected without writes; decisions remained available |
| Image requests and rights | Listed usage accepted; arbitrary targets rejected; prohibited/expired/non-ready/wrong-business assets blocked approval |
| Approval and changes lifecycle | PASS; exact existing M2 transitions, no publication or automatic internal review |
| Legacy separation and authenticated HTTP | PASS; normal OTP, CSRF/303, safe errors, immutable revision boundary, and no legacy launch approval from generic decisions |
| Cleanup | 81/81 table counts reconciled |

The external-browser reports additionally establish advisory mutations, both customer
decisions, stale-tab rejection, populated cross-tenant denial, and forged preference
rejection through authenticated rendered controls. The deadlock result does not claim
that the application was the victim; application rollback on real lock failure was
independently established by the timeout cases.

Shared global-module disabling remains **NOT EXECUTABLE — SHARED STAGING IMPACT**.
Its synthetic business-module counterpart passed. The independent exact valid-history
128 KiB boundary was not separately exercised: oversized malformed history was
rejected, which is not an independent valid-history size-boundary PASS. Neither
limitation blocks M5B closure under the recorded scope.

Migrations 023 and 024 remain unchanged; migration 025 is absent and reserved for M6.
The [M5A closeout](sprint-8.8-m5a-closeout.md) and
[M5B local implementation record](sprint-8.8-m5b-local-implementation.md) preserve
historical status at their respective validation stages. Their then-current M5B
NEXT/local-review wording is superseded for current status by this closeout, the
updated sprint plan, and the current handoff; it is not a claim that M5B remains open.

## Documentation import provenance

All five files were downloaded from the validation export and initially matched its
SHA-256 values byte-for-byte. The other four imported files remain exact copies.
This file retains the exported content and adds this backend summary, explicit
limitations, and historical-status clarification to satisfy the closeout import
requirements. The remote export and all four evidence reports were not edited.

## Final browser execution

Direct local Playwright 1.63.0 drove installed Microsoft Edge 153.0.4234.32 on Windows
NT 10.0.26200.0, against the explicit staging application base URL. Computer Use was
not invoked. Remote fixture setup, reads, cleanup, and evidence storage ran only as
`codex-validation` on `ubo-stage-app`; APP_ENV was staging. Normal OTP login was used
without cookie/session forgery, and authentication secrets were excluded from evidence.

| Completed case | Browser result | Authoritative state/write result |
| --- | --- | --- |
| Approval | Rendered button POST303GET; exact awaiting-internal-review receipt; controls disappear | approved / customer_approved / pending_internal_review; no internal request, public pointer, or legacy launch approval |
| Request changes | Required-text form POST303GET; Changes requested receipt; controls disappear | rejected / changes_requested / draft; M2 comments match; no duplicated feedback |
| Stale tab | Original form retained through legitimate material successor; POST409; reload has no actions | Full domain hash unchanged after denied submission; zero new feedback/events/decisions |
| Populated foreign tenant | B owns an actionable business/site; foreign manager generically unavailable; preview404 | No tenant A content/authority; visible denial identical for existing foreign and nonexistent business; no writes |
| Forged preferences | Genuine Owner forms/CSRF with only value altered: tone/services and emphasis/professional each POST400 | Metadata entries and event counts unchanged; complete domain hash unchanged |
| Targeted leakage | Twenty response bodies plus targeted DOM text/attributes inspected | No private sentinel, actor identifier, metadata/correlation/hash-field, source/brief/storage/internal-reason leakage found |

The prior reports establish six-class normal OTP login, Owner/Admin mutations,
lower-role read-only access, internal/Super Admin customer denial, private sandboxed
preview, advisory PRG, and the real-MySQL locking/race/replay/lifecycle contract. The
final run adds the five remaining cases rather than substituting source tests for
browser execution. Earlier partial/block reports remain historical evidence and are
completed by the final supplemental report under the explicit scoped closure rule.

## Cleanup and exclusions

The completion run created only disposable synthetic fixtures: three actors and five
independent business/site/review contexts, plus a legitimate material successor for
stale-tab testing. Cleanup restored every one of 81 table counts and found zero
remaining synthetic users/businesses/sites/revisions/approvals/events/assets/briefs/
associations. Final deployed SHA was unchanged, the tree was clean, and migration025
was absent. Deployment-wrapper calls=0; migration-wrapper calls=0. Staging OS, deployed
application, providers, public runtime, and production were untouched.

Both browser actors logged out and Edge closed; the current profile was removed.
Local fixture helpers and private scan-value copies were deleted. Two old temporary
directories from the prior interrupted run remain available for manual removal, as
recorded in the fourth report. The user explicitly made local profile cleanup
non-blocking for M5B staging PASS. No authentication secrets are in the evidence.

## Export and next milestone

The external export root is `/home/codex-validation/ultimate-back-office-m5b-closeout/`.
It contains updated full copies of this document, the M5 service contract, Sprint 8.8
plan, Codex handoff, and website-generation architecture under `docs/`. The four
existing documents were copied from the exact deployed checkout and updated only in
the external export. No deployment, application edit, staging commit, or PR is implied.

M5C is the next unstarted pass and owns the deferred responsive/accessibility/browser
corrections and complete integrated QA. M5 is not complete; M6 and production remain
outside this closure.
