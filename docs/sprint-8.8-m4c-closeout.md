# Sprint 8.8 M4C — Closeout

## Final Status

M4C — Review Submission + Internal Approval — is **COMPLETE / STAGING PASS /
FORMALLY CLOSED** on merged, deployed, and validated SHA
`d33589da5eebbf8e2ae0dc203837d6667abd1f71`.

| Milestone | Status at final M4 closeout |
| --- | --- |
| M1 | COMPLETE / STAGING PASS / FORMALLY CLOSED |
| M2 | COMPLETE / STAGING PASS / FORMALLY CLOSED |
| M3 | COMPLETE / STAGING PASS / FORMALLY CLOSED |
| M4A | COMPLETE / STAGING PASS / FORMALLY CLOSED |
| M4B | COMPLETE / STAGING PASS / FORMALLY CLOSED |
| M4C | COMPLETE / STAGING PASS / FORMALLY CLOSED |
| M4 overall | COMPLETE / STAGING PASS / FORMALLY CLOSED |
| Sprint 8.8, through M4 | COMPLETE / STAGING PASS / FORMALLY CLOSED |
| M5 | NEXT / NOT STARTED |
| Production | UNAUTHORIZED / NOT DEPLOYED |

This documentation-only closeout records authoritative user-supplied deployment and
final-validation evidence. External report paths and SHA-256 values are supplied
references; no report copies were fabricated and no staging gates were rerun here.
Sprint 8.8 completion means the Website Platform milestone through M4 is formally
complete at its current boundary. Customer workflow, public runtime, deployment,
domain/routing, LeadHub ingestion, legacy cutover, and production remain later work.
See the [overall M4 closeout](sprint-8.8-m4-closeout.md).

## Delivered Scope

M4C delivered the internal **Site Platform Review Workflow** at
`/app/admin/site-review.php`, backed by `SiteReviewAdminWorkflow`:

- explicit write-once materiality classification and the existing M3 stored-composition
  review gate;
- customer review **REQUEST only**, internal review request, and internal approve/reject;
- authoritative revision/site status reads, approval timeline, ancestry and review-ready
  display, and escaped approval comments/reasons;
- advisory capability flags, with existing M2 lifecycle and approval services retaining
  mutation authority;
- dedicated `admin-site-platform` CSRF, successful-action CSRF rotation, and HTTP 303
  post/redirect/get;
- deliberate separate actions with no automatic chaining, the customer decision hard
  boundary, and the explicit warning **"Approval does not publish this site"**.

## Explicit Non-Scope

M4C did not deliver customer approval decision UI, customer feedback, customer change
request UI, customer-authenticated preview, customer impersonation, public publication,
a deployment feature, domain/routing, LeadHub public ingestion, provider integration,
production activation, or M5 behavior. The staging application deployment recorded
below is separate from a generic-site deployment feature.

## Implementation / Merge History

| Event | Commit |
| --- | --- |
| Initial M4C implementation | `e76aede9677e16cea01da8bd2e962aac67b6e0f8` |
| Review correction | `2fd772ff64db3e681ebb4ad7e1bdcda32c15f04b` |
| PR #112 merge; deployed and validated SHA | `d33589da5eebbf8e2ae0dc203837d6667abd1f71` |

[PR #112 — Sprint 8.8 M4C: add review and internal approval workflow](https://github.com/fvd8383/ultimate-back-office/pull/112)
merged the implementation and review correction. The correction included deterministic
decision TOCTOU coverage; real independent-connection races were subsequently executed
by the final staging validation.

## No-Migration Boundary

M4C required **NO MIGRATION**. Migration 023 remained unchanged, migration 024 remained
unchanged, and migration 025+ remained absent.

| Check | Count |
| --- | --- |
| M4C deployment migration-wrapper invocations | 0 |
| M4C deployment migration 023 executions | 0 |
| M4C deployment migration 024 executions | 0 |
| Final M4 validation migration executions | 0 |

## Staging Deployment Gate

**PASS** on `ubo-stage-app` as `ubo-deploy`. Deployment advanced from
`557cc34fe4cf3ab56cdcb59fd7c623c495fd8eaf` to
`d33589da5eebbf8e2ae0dc203837d6667abd1f71`, matching remote main.

| Deployment check | Recorded result |
| --- | --- |
| Deploy wrapper | 1 invocation; exit 0 |
| Migration wrapper | 0 invocations |
| Deployment-reported PHP lint | 167 files PASS |
| Standalone suites | 42/42 PASS |
| M4C behavior / view / scope | 37 / 21 / 33 assertions PASS |
| M1 / M2 / M3 / M4A / M4B / pricing regression | PASS |

Deployment report: `evidence/SPRINT-8.8-M4C-STAGING-DEPLOYMENT.md`.
Supplied SHA-256:
`0f43460b2706805a8d399ae900ffa87857c0c025dfc328cd7a1118a3f23327ea`.

The deployment count of 167 is not the authoritative tracked-PHP population. The
final validation reconciled and linted all 171 tracked PHP files as described below.

## Final Real-MySQL Validation

**SPRINT 8.8 M4 STAGING FINAL VALIDATION: PASS**.

Validation ran on `ubo-stage-app` as `codex-validation`, using **MySQL 8.4.8** and
**native PDO prepares, emulation disabled**. Deployed SHA and remote main were both
`d33589da5eebbf8e2ae0dc203837d6667abd1f71`; the working tree was clean.

Final report:
`ubo-sprint-8.8-m4-final-validation-20260904T215556Z/SPRINT-8.8-M4-STAGING-FINAL-VALIDATION.md`.
Supplied SHA-256:
`9cec3387d20ab05afec7c9b50d6659c7596a5b1d6e2085b35ed4651f016899bc`.

Local M4C implementation reported **171/171 PHP PASS**. Deployment reported **167 PHP
files PASS**. Final validation independently enumerated Git-tracked PHP on the
deployed commit: **171 tracked / 171 linted / PASS**. The deployment evidence was
unavailable to final validation for determining why its count was 167. No unsupported
root-cause explanation is asserted.

Final regression: **42/42 standalone suites PASS**; M1, M2, M3, M4A, M4B, and pricing
**PASS**; M4C behavior **37**, view **21**, and scope **33 assertions PASS**.

## Actor / Authorization Evidence

| Role-only case | Real service result |
| --- | --- |
| Internal Admin | Authorized |
| Second internal actor | Authorized |
| Non-admin | Rejected |
| Second tenant across tenant boundary | Rejected |
| Real associated customer Owner | Actual customer approval service validation PASS |
| Internal Admin attempting customer impersonation | Rejected |

Actor identities and PII are not retained here. No PII was exposed.

## Integrated M4A Dependency Evidence

Real MySQL passed site creation, generation brief creation, authored revision creation,
the single mutable revision invariant, authorization, and read-only workspace access
with zero mutation. No provider, publication, or legacy cutover occurred. The earlier
M4A implementation and validation chronology remains in the
[M4A closeout](sprint-8.8-m4a-closeout.md).

## Integrated M4B Dependency Evidence

Real MySQL passed composition initialization, page editing, section editing, theme
editing, canonical hashes, and validated preview. Tampered composition **FAILS CLOSED /
PASS**. Preview reads caused zero mutation. The earlier M4B evidence and its specific
limitations remain in the [M4B closeout](sprint-8.8-m4b-closeout.md).

## M4C Materiality Evidence

The review workspace and its read paths **PASS**, with zero read mutation. Explicit
materiality classification and write-once enforcement **PASS** against real MySQL.
The separate materiality concurrency result is recorded below.

## Review Gate Evidence

The existing M3 stored-composition review gate **PASS** through the M4C workflow.
Review readiness remains service-authoritative; the interface does not bypass M2/M3
validation or chain subsequent actions automatically.

## Customer Request Evidence

Customer review request **PASS** and customer decision hard boundary **PASS**. The
internal workflow requests customer review only. Concurrent requests produced one
logical request, as recorded in the real-concurrency section.

## Customer Service Boundary

A safe real associated Owner actor successfully executed a customer approval decision
through the **pre-existing M2 approval service: PASS**. An Internal Admin attempting
customer impersonation was rejected.

This validates the existing service contract. It added no customer UI, route,
authentication, or preview, and it was not M5 implementation. Customer approval
decision UI remains M5 work.

## Internal Approval Evidence

Internal review request, internal approve, and internal reject **PASS**. Existing M2
services own eligibility, locking, transactions, lifecycle transitions, and approval
authority. An administratively approved site is still unpublished.

## Non-Material Evidence

Non-material internal review without an approved customer baseline was rejected:
**PASS**. The inherited still-current customer-approved baseline flow **PASS**.
Non-material classification does not supply an initial customer-approval bypass.

## Real MySQL Concurrency

These were **real races using independent MySQL connections**, separate from the
deterministic local test:

| Race | Actual result |
| --- | --- |
| Materiality classification | PASS: one material classification winner, one safe conflict loser, exactly 1 successful materiality event; no duplicate success event. |
| Customer approval request | PASS: exactly 1 final request row, one creation and one idempotent existing result; no duplicate logical request. |
| Internal approval decision | PASS: one approval winner and one safe conflict loser; final approval `approved`, revision `internally_approved`, site `approved`; false success events 0. |

Temporary concurrency helpers were removed. Losing operations produced no false
success event.

## Deterministic TOCTOU

The existing deployed deterministic decision TOCTOU suite **PASS**. It proves that
M4C advisory/pre-read state cannot override the M2 authoritative mutation check.
Local true two-connection concurrency remained **NOT EXECUTABLE IN THE LOCAL FIXTURE**;
the three real staging races above independently satisfied that final exit gate.

## Display / Escaping

Ancestry, review-ready display, and approval comment escaping **PASS**. Approval reason
escaping **PASS through deployed view/source regression**. Metadata exposure was
absent; correlation, private, and provider data were not exposed. The warning
**"Approval does not publish this site"** was present.

## Authenticated Browser Limitation

**AUTHENTICATED BROWSER ROUTE VALIDATION: NOT EXECUTABLE**. No approved safe staging
session mechanism existed. This case is not labeled PASS.

The limitation is nonblocking because real service-level authorization, real workflow
mutations, route/source contract suites, and deployed unauthenticated route behavior
passed. No session/cookie forgery was performed. The five deployed unauthenticated
routes — `sites.php`, `site.php`, `site-composer.php`, `site-preview.php`, and
`site-review.php` under `/app/admin/` — returned safe authentication redirects;
**5xx responses: 0**. These results do not claim authenticated browser execution.

## Audit / Integrity

False success events **0**; unsafe metadata findings **0**. Race losers and failed
mutations produced no false success events. Constraints remained **enabled**.

Unexpected duplicate revisions, multiple mutable revisions, page duplicates, section
duplicates, cross-site ancestry, cross-site assets, cross-site approvals, orphan
assets, orphan approvals, and ambiguous customer approvals were all **0**.

## Final Cleanup

| Final check | Result |
| --- | --- |
| Generic validation rows | 0 |
| Approval rows | 0 |
| Test event rows | 0 |
| Registry definitions / variants / drift | 16 / 22 / 0 |
| Legacy websites / pages | 6 / 37 |
| Actors created or modified | None |
| `/tmp` helpers remaining | 0 |
| Working tree | Clean |
| Deployed SHA | Unchanged: `d33589da5eebbf8e2ae0dc203837d6667abd1f71` |

## Marketing Regression

The separate existing `public/marketing` staging preview remained healthy. It is a
separate property from generic Site Platform sites.

| Resource | Recorded result |
| --- | --- |
| Marketing index / privacy / terms / contact | 200 each |
| CSS / JS / logo / favicon / social image | 200 each |
| `/marketing` | 302 to `/marketing/` |
| `X-Robots-Tag` | `noindex, nofollow` |

Marketing viewport QA at 1440px, 768px, 390px, and 320px, console, interactions, and
overflow review remain **NOT YET RECORDED**. No new marketing browser QA is claimed.
The initial publication and subsequent deployment chronology is preserved in
[the marketing preview record](247sp-marketing-staging-preview.md).

## M5 Boundary

M4 completes the **internal administrative workflow**: M4A site/brief/authored revision,
M4B composition/preview, and M4C materiality/review/customer-request/internal approval.
M5 is **NEXT / NOT STARTED** and owns customer-authenticated preview, customer feedback,
customer changes request, and customer approval decision UI. M4 does not complete
that customer workflow.

## Publication Boundary

Administrative site status `approved` does not mean published, live, deployed,
domain-active, or production-ready. Later deployment/runtime milestones retain
authority. Production remains **UNAUTHORIZED / NOT DEPLOYED**.

Final M4 validation recorded publication **0**, deployments **0**, provider calls **0**,
production access **0**, Apache changes **0**, and DNS changes **0**. The earlier M4C
application deployment's single wrapper invocation is recorded separately.
This local documentation closeout changes no application code, tests, migrations,
schema, or providers and performs no staging/production access or M5 implementation.

## Evidence Timeline

1. **Local implementation and review correction:** commits
   `e76aede9677e16cea01da8bd2e962aac67b6e0f8` and
   `2fd772ff64db3e681ebb4ad7e1bdcda32c15f04b`; local lint 171/171 and suites 42/42
   PASS. The deterministic TOCTOU test is local/deployed-suite evidence.
2. **PR #112 merge and staging deployment — PASS:**
   `d33589da5eebbf8e2ae0dc203837d6667abd1f71`;
   `evidence/SPRINT-8.8-M4C-STAGING-DEPLOYMENT.md`; SHA-256
   `0f43460b2706805a8d399ae900ffa87857c0c025dfc328cd7a1118a3f23327ea`.
3. **Final integrated M4 real-MySQL validation — PASS:** same deployed SHA;
   `ubo-sprint-8.8-m4-final-validation-20260904T215556Z/SPRINT-8.8-M4-STAGING-FINAL-VALIDATION.md`;
   SHA-256 `9cec3387d20ab05afec7c9b50d6659c7596a5b1d6e2085b35ed4651f016899bc`.
   Git-derived PHP lint 171/171, integrated A/B/C services, and all three real races
   passed; authenticated browser validation remained NOT EXECUTABLE.
4. **Documentation closeout:** records the supplied evidence and formally closes M4C,
   M4, and Sprint 8.8 through M4. It does not rerun staging validation or begin M5.
