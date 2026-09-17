# Sprint 8.8 M5 — Closeout with deferred Narrator follow-up

Decision date: 2026-09-17. Documentation baseline and authoritative deployed
application SHA: `70a3051f73874e7268b9c1bba45bf19d41f9432a`.

**M5 COMPLETE FOR SPRINT PROGRESSION** by product-owner decision.
**M5C ACCEPTED / NARRATOR FOLLOW-UP DEFERRED**.
This closes M5 for progression without claiming full accessibility validation.
Sprint 8.8 remains **IN PROGRESS**.

## Outcomes and scope

| Milestone | Current status | Outcome |
| --- | --- | --- |
| M5A | COMPLETE / STAGING PASS / FORMALLY CLOSED | Customer-authenticated issued-review resolution, allowlisted read-only review, and immutable inert private preview. |
| M5B | COMPLETE / STAGING PASS / FORMALLY CLOSED | Bounded feedback and advisory presentation/image requests, exact-revision customer decisions, transaction-time authorization, replay protection, and CSRF/303 receipts. |
| M5C | ACCEPTED / NARRATOR FOLLOW-UP DEFERRED | Integrated QA/corrections, server/browser regression evidence, completed partial Narrator testing, and explicit acceptance of deferred validation. |
| M5 | COMPLETE FOR SPRINT PROGRESSION | Customer preview/feedback/approval milestone accepted for progression to M6. |
| M6 | NEXT / NOT STARTED | Build/deployment/restore is the next milestone; implementation has not begun. |
| Production | UNAUTHORIZED / NOT DEPLOYED | No production authorization or deployment is granted. |

Customer approval remains distinct from internal approval, publication, deployment,
and legacy launch authority. Business Profile remains the source of reusable facts.
Customers cannot publish, restore, reassign domains, edit executable markup, or
mutate the reviewed revision through advisory feedback. The legacy public runtime
remains authoritative; this closeout introduces no runtime cutover.

See [M5A closeout](sprint-8.8-m5a-closeout.md),
[M5B closeout](sprint-8.8-m5b-closeout.md),
[M5 service contract](sprint-8.8-m5-service-contract.md), and
[M5C implementation history](sprint-8.8-m5c-local-implementation.md).

## Completed validation and corrections

Existing evidence covers service/view/scope regressions, real-MySQL authorization,
concurrency/replay/rollback and lifecycle integrity, normal authenticated HTTP,
tenant/private-data boundaries, the broad external-browser matrix,
responsive/200% zoom, keyboard and structural/accessibility-tree checks, and
browser console/network behavior. These gates are historical results; they were
not rerun for this documentation change.

M5C corrected narrow-screen overflow and post-submit orientation, replaced earlier
receipt announcement approaches with one initially empty polite status populated
once from an inert template, suppressed the identical persistent label when the
receipt already supplies it, and added allowlisted transient result titles. The
approval title explicitly says customer approval was recorded and internal review
is pending. Normal/later terminal GETs retain the generic Website Manager title.
Detailed receipts and document-title speech are separate semantic sources.
Earlier failure reports retain their original verdicts and are not rewritten.

| Recorded Narrator observation | Preserved result | Report |
| --- | --- | --- |
| Feedback automatic result title | PASS | Primary partial report |
| Feedback detailed receipt | PASS-ACCESSIBLE | Primary partial report |
| Customer approval automatic title | PASS | Primary partial report |
| Customer approval detailed receipt | PASS-ACCESSIBLE | Primary partial report |
| Duplicate detailed receipts in completed cases | None found | Primary partial and operator reports |
| Changes-requested automatic title | PASS | Final operator continuation |
| Changes-requested detailed receipt | PASS-ACCESSIBLE after a separately preserved incomplete navigation attempt | Final operator continuation |
| Later changes-requested state readability/navigation | PASS | Final operator continuation |

The primary report confirms customer approval legally remained pending internal
review, with no internal approval, publication, public pointer mutation, or legacy
launch. The operator continuation confirms legal changes-requested state and
unchanged immutable content. Both runs restored **81/81** baseline table counts,
reported zero synthetic markers, and completed local profile/helper/auth cleanup.
No current staging inspection was performed for this closeout.

## Product-owner acceptance decision

Further Windows Narrator validation is intentionally deferred. The implemented
accessibility behavior is accepted as sufficient for the current product stage
based on completed structural/accessibility-tree, keyboard, responsive/zoom,
regression, and actual recorded/transcribed Narrator observations. Automatic
post-submit title orientation and accessible detailed receipts passed in completed
cases, with no duplicate detailed receipts found. No known application
accessibility failure remains from those completed cases.

Acceptance is a scope decision for sprint progression; unexecuted checks are not
passed. Historical application failures, tooling blocks, original recordings,
raw transcripts, and report hashes remain immutable. The operator-continuation
stopping-point report retains its partial-run status; this new decision supersedes
its requirement for full validation closure before sprint progression.

## Deferred Accessibility Validation

**M5C Narrator Follow-Up** is non-blocking for M6 development. Remaining checks may
resume later without blocking M6 unless a future release requirement makes them
mandatory. These are follow-up validation items, not established application defects.
No future sprint number is assigned.

The product-owner checklist retains the following follow-up scope. Three items
already have completed PASS evidence in the latest operator continuation; they are
retained here as optional future revalidation, without erasing their results.

| Follow-up scope | Current evidence / disposition |
| --- | --- |
| Changes-requested automatic title audio verification | Operator PASS preserved; additional revalidation deferred. |
| Changes-requested detailed receipt Narrator verification | Operator PASS-ACCESSIBLE preserved; additional revalidation deferred. |
| Later approval terminal-state Narrator readability | Remaining actual Narrator check intentionally deferred. |
| Later changes-requested terminal-state Narrator readability | Operator PASS preserved; additional revalidation deferred. |
| Error/recovery Narrator verification | Remaining actual Narrator check intentionally deferred. |
| Private-preview iframe Narrator navigation | Remaining actual Narrator entry/read/exit check intentionally deferred. |
| Final combined keyboard/screen-reader trap assessment | Incomplete overall assessment intentionally deferred; completed changes-surface navigation remains documented. |

Resume from preserved evidence rather than repeating unchanged passed cases solely
for newer timestamps. Any future validation should preserve original artifacts and
record new evidence separately. No full accessibility validation PASS is claimed.

## Immutable evidence chain

| Evidence | SHA-256 |
| --- | --- |
| Server-side final PASS | `f362926d5ac0a10018794a1c8107750d52e435bccb774355c183365761189727` |
| Broad external-browser matrix | `8ddc215e371da0ec9679d59ef009d09b1ec2134e62305bb9d295ebd52f04fcfe` |
| First Narrator failure | `013996df0a39dcf2392fe4dc3a6e6f81ee4d1918d7f814dfa6289ab236e6da5e` |
| First correction deployed regression | `5f9459afbbbf5ad35212e9f57e34798a3b51432e3abd0b3a443cf82ddc0128eb` |
| Duplicate-announcement audio failure | `d85dbb2e4cc390c2e040a7994a9f48a79eff2d9af816d7cf04a4f8e8b6bc2968` |
| Second correction deployed regression | `4075aad176eddc7c9290671a4eaa70f5cf4bc26e6339365a3e7bd348de5e34f1` |
| Historical 15-second Narrator report | `a3a8094aba91509f5d468e200a9d411d10f5c1068c5c6a2e13d752a31dfd3f11` |
| Title-enhancement deployed regression PASS | `de1e215f7716e550a6ab993872cb50eaaf7d4a2d5a2dc03c4159fb9a569368c7` |
| Primary partial Narrator report | `061ccc25b579581179130ece33a3355f56b94b4baeeaed1cfb86660029781fd4` |
| Computer Use tooling-block continuation | `0efda3e97bb8669cdead8fea3ccc97763a75626dbdd251bd1ffd4acbef6c5439` |
| Final operator continuation / stopping-point record | `d9a2bd3899bf92fcbc3c3ce25c0604c31b7390b896fcf4c29174d2812a0dd717` |

Primary partial remote report:
`/home/codex-validation/ubo-sprint-8.8-m5c-final-narrator-20260917T032010Z/SPRINT-8.8-M5C-FINAL-RECORDED-NARRATOR-VALIDATION.md`.

Tooling-block remote report:
`/home/codex-validation/ubo-sprint-8.8-m5c-final-narrator-continuation-20260917T220649Z/SPRINT-8.8-M5C-FINAL-NARRATOR-CONTINUATION.md`.

Final operator remote report:
`/home/codex-validation/ubo-sprint-8.8-m5c-final-operator-continuation-20260917T222134Z/SPRINT-8.8-M5C-FINAL-OPERATOR-CONTINUATION.md`.

Durable local directories under
`C:/Users/fvd83/Documents/UBO-Validation-Evidence/` are respectively
`M5C-Final-20260917T032010Z/`, `M5C-Final-Continuation-20260917T220649Z/`, and
`M5C-Final-Operator-Continuation-20260917T222134Z/`.
The operator directory retains four original WAVs, raw transcription JSON,
transcripts, action/capture metadata, manifest, per-file hashes, and user-stop record.
This closeout changes none of those artifacts. Remote paths are references only;
staging access is prohibited for this documentation task.

## Migration, production, and M6 handoff

Migrations 023/024 remain unchanged against the baseline; migration 025 is absent.
Canonical Git-blob SHA-256 values, excluding Windows checkout line-ending conversion:

- 023: `f0912bafc947eab8cc5b2dd5d534466d6b3675f991cac2f6849b1f84819db302`
- 024: `95093d67a3319c561f28588b563d7f23a126b44a501b9cb80f9d791b988e3950`

No PHP/JS/CSS, application, test, deployment/configuration, provider/domain,
public-runtime, staging, or production changes are included. Deployment-wrapper
calls and migration-wrapper calls are both **0**.

**M6 NEXT / NOT STARTED**: build/deployment/restore is the explicit next handoff
in the [sprint plan](sprint-8.8.md#m6--build--deployment--restore). Begin it under
its own implementation scope; this documentation PR does not implement M6 or
authorize production. **Production UNAUTHORIZED / NOT DEPLOYED**.

Documentation validation for this change comprises local Markdown links, fenced
code blocks, current-status consistency, migration/scope integrity, and
`git diff --check`. Application/MySQL/browser/Narrator suites are not rerun for
documentation-only changes.
