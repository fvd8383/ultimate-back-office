# Sprint 8.8 M4 — Overall Closeout

## Final Status And Sprint Boundary

M4 is **COMPLETE / STAGING PASS / FORMALLY CLOSED** on final
merged, deployed, and validated SHA `d33589da5eebbf8e2ae0dc203837d6667abd1f71`.
Sprint 8.8 remains **IN PROGRESS**. The M4 exit gate completes the internal
administrative workflow; M5–M8 and the full M1–M8 exit requirements remain necessary
before Sprint 8.8 itself closes.

| Milestone | Final status | Completion record |
| --- | --- | --- |
| M1 | COMPLETE / STAGING PASS / FORMALLY CLOSED | [M1 closeout](sprint-8.8-m1-closeout.md) |
| M2 | COMPLETE / STAGING PASS / FORMALLY CLOSED | [M2 closeout](sprint-8.8-m2-closeout.md) |
| M3 | COMPLETE / STAGING PASS / FORMALLY CLOSED | [M3 closeout](sprint-8.8-m3-closeout.md) |
| M4A | COMPLETE / STAGING PASS / FORMALLY CLOSED | [M4A closeout](sprint-8.8-m4a-closeout.md) |
| M4B | COMPLETE / STAGING PASS / FORMALLY CLOSED | [M4B closeout](sprint-8.8-m4b-closeout.md) |
| M4C | COMPLETE / STAGING PASS / FORMALLY CLOSED | [M4C closeout](sprint-8.8-m4c-closeout.md) |
| M4 overall | COMPLETE / STAGING PASS / FORMALLY CLOSED | This record |
| Sprint 8.8 | IN PROGRESS | [Sprint record](sprint-8.8.md) |
| M5 | NEXT / NOT STARTED | Customer workflow remains outstanding |
| Production | UNAUTHORIZED / NOT DEPLOYED | Separate authorization required |

Earlier milestone closeouts retain their status and next-work chronology at the time
they closed. This record and the current handoff describe the subsequent final state.
This is documentation of authoritative user-supplied external evidence, not a new
staging run or a fabricated local report copy.

## Completed Internal Workflow

- **M4A — Admin Workflow Foundation:** site creation/detail, generation briefs,
  authoritative snapshots, and authored revisions; closed on
  `8805eeeae704f130ddda357e82c4dd936fde5b4c`.
- **M4B — Composition Editor + Generic Admin Preview:** structured page/section/theme
  authoring and validated inert internal preview through M3; closed on
  `557cc34fe4cf3ab56cdcb59fd7c623c495fd8eaf`.
- **M4C — Review Submission + Internal Approval:** explicit materiality, stored
  composition review gate, customer request only, internal request/approve/reject,
  and authoritative status/approval display; PR #112 merged on
  `d33589da5eebbf8e2ae0dc203837d6667abd1f71`.

M2/M3 retain authorization, transaction, lifecycle, composition, and approval authority.
Browser actions are explicit, use dedicated CSRF and successful-action rotation, and
return HTTP 303 PRG. The [M4 service contract](sprint-8.8-m4-service-contract.md)
records the implemented boundary.

## Final Integrated Real-MySQL Exit Gate

**SPRINT 8.8 M4 STAGING FINAL VALIDATION: PASS** on `ubo-stage-app` as
`codex-validation`, MySQL **8.4.8**, native PDO prepares with emulation disabled.
The deployed and remote-main SHA matched the final SHA above; the tree was clean.

External report:
`ubo-sprint-8.8-m4-final-validation-20260904T215556Z/SPRINT-8.8-M4-STAGING-FINAL-VALIDATION.md`.
Supplied SHA-256:
`9cec3387d20ab05afec7c9b50d6659c7596a5b1d6e2085b35ed4651f016899bc`.

Integrated real services passed A's site/brief/authored-revision/single-mutable rule;
B's initialization, page/section/theme editing, hashes, validated preview and tamper
rejection; and C's workspace, write-once materiality, review gate, customer request,
internal request/approve/reject, and non-material baseline rules. Workspace and preview
reads caused zero mutation. Internal actors were authorized; non-admin and cross-tenant
access were rejected.

A real associated Owner passed the pre-existing **M2 customer approval service**;
Internal Admin customer impersonation was rejected. This added no customer UI, route,
authentication, or preview and did not implement M5.

All **42/42 standalone suites PASS**, including M1/M2/M3/M4A/M4B/pricing and M4C
behavior/view/scope **37/21/33 assertions PASS**. Final Git enumeration established
**171 tracked PHP files / 171 linted / PASS**. The earlier deployment reported 167
files PASS; its evidence was unavailable to final validation to explain that count,
so no root cause is asserted. Local implementation also reported 171/171 PASS.

## Concurrency, Audit, And Integrity

Three real independent-connection MySQL races passed:

1. Materiality: one material winner, one safe conflict loser, exactly one successful
   materiality event.
2. Customer approval request: exactly one row; one creation and one idempotent existing
   result.
3. Internal decision: one approval winner and one conflict loser; final approval
   `approved`, revision `internally_approved`, site `approved`; false success events 0.

The deployed deterministic TOCTOU suite separately passed, demonstrating that M4C
advisory reads cannot override M2 mutation checks. It is not substituted for the races.
Audit false success events and unsafe metadata were zero. Failed mutations and race
losers generated no false success. Duplicate revision/page/section, multiple mutable
revision, cross-site ancestry/asset/approval, orphan asset/approval, and ambiguous
customer approval findings were all zero; constraints stayed enabled.

## Limitations And Separate Marketing Evidence

**Authenticated browser route validation: NOT EXECUTABLE**, because no approved safe
staging session mechanism existed. No authenticated browser PASS is claimed. This is
nonblocking given real service authorization and mutations, deployed route/source
contract suites, and all five unauthenticated Site Platform routes returning safe
authentication redirects with zero 5xx. No session/cookie forgery occurred.

Ancestry, review-ready display, and comment escaping passed; reason escaping passed
through deployed view/source regression. Private/provider/correlation metadata was not
exposed, and the approval-does-not-publish warning was present.

The separate marketing property retained 200 responses for index/privacy/terms/contact
and CSS/JS/logo/favicon/social, a 302 `/marketing` to `/marketing/` redirect, and
`X-Robots-Tag: noindex, nofollow`. Marketing viewport/browser QA remains **NOT YET
RECORDED**; the initial marketing publication chronology is unchanged.

## Migration And Cleanup

M4A/M4B/M4C required no new migration. Migrations 023/024 remained unchanged and 025+
remained absent. M4C deployment migration-wrapper invocations and 023/024 executions
were zero; final M4 validation migration executions were zero.

Cleanup left generic validation rows, approvals, and test events at **0**; registry
**16 definitions / 22 variants / 0 drift**; legacy **6 websites / 37 pages**. No actors
were created or modified, `/tmp` helpers remaining were **0**, and the clean deployed
tree remained at `d33589da5eebbf8e2ae0dc203837d6667abd1f71`.

## Remaining Customer, Runtime, And Production Boundary

M5 is **NEXT / NOT STARTED**: customer-authenticated preview, feedback, changes request,
and customer approval decision UI remain its responsibility. Later milestones retain
build/deployment, domain/routing, LeadHub public ingestion, and public/legacy runtime
cutover. The separately planned Sprint 8.9 communications workstream remains distinct.

Administrative `approved` does not mean published, live, deployed, domain-active, or
production-ready. Final validation performed **0** publications, deployments, provider
calls, production accesses, Apache changes, and DNS changes. Production is
**UNAUTHORIZED / NOT DEPLOYED**; first-customer readiness is not established by M4.

The earlier M4C application deployment separately passed with one deploy-wrapper
invocation, exit 0, and zero migrations. Its supplied report is
`evidence/SPRINT-8.8-M4C-STAGING-DEPLOYMENT.md`, SHA-256
`0f43460b2706805a8d399ae900ffa87857c0c025dfc328cd7a1118a3f23327ea`.
Full deployment, implementation, and final-validation details are in the
[M4C closeout](sprint-8.8-m4c-closeout.md).
