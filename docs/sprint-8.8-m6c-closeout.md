# Sprint 8.8 M6C — Owner acceptance and formal closeout proposal

Prepared October 31, 2026 (America/New_York).

**Current effective status while this closeout PR is open:**
**M6C — MERGED / SCOPED EVIDENCE ACCEPTED / CLOSEOUT REVIEW PENDING.**
M6 overall is **IN PROGRESS**; M6B is **COMPLETE / STAGING PASS / FORMALLY CLOSED**;
M6D–M6G are **NOT STARTED**. Formal M6C closure did not occur at the implementation
merge and is not declared by preparing this record.

## Already-effective owner decisions and implementation merge

Frank explicitly accepted the consolidated core20/journal3/supplement17 evidence,
its documented limitations and **G1–G4 for scoped M6C**. He approved the B14/B16
future allocation below and manually merged [PR #128](https://github.com/fvd8383/ultimate-back-office/pull/128).
The [owner-acceptance/manual-merge receipt](https://github.com/fvd8383/ultimate-back-office/pull/128#issuecomment-6549296854)
records those effective decisions; they are not awaiting renewed approval.
This is an owner decision record, not a formal GitHub APPROVED review.

| Implementation boundary | Verified value |
| --- | --- |
| Reviewed and merged PR head | `fc3eeeb76751a35a6d5d975e23fa78fac2e14163` |
| Actual manual merge | [`0bd0ce3c8932fd6a72bcb2afdee6954b09a0d718`](https://github.com/fvd8383/ultimate-back-office/commit/0bd0ce3c8932fd6a72bcb2afdee6954b09a0d718) |
| Merge time | `2026-10-31T21:09:50Z`; October 31, 2026, 5:09:50 p.m. America/New_York |
| First parent / reviewed base | `7ed48fbaa4858b1b5299dc7cbea98f96e98f4e45` |
| Second parent | `fc3eeeb76751a35a6d5d975e23fa78fac2e14163` |
| Reviewed-head and merge tree | `beb12edefab5b8d94af2985921cf10ed9e665a43` |
| Main when this documentation branch was created | `0bd0ce3c8932fd6a72bcb2afdee6954b09a0d718` |
| Retained implementation branch | `codex/sprint-8.8-m6c-artifact-builder`, unchanged at the reviewed head |

Whole-tree equality establishes Git path/blob/mode preservation across the manual
merge. It is not a runtime test or private-evidence rehash. The prior Codex task
performed no merge: it observed Frank's completed merge and stopped under its old
open/unmerged precondition. That stop did not revoke the owner's decisions.

## Accepted scoped evidence and limitations

The [consolidated acceptance record](sprint-8.8-m6c-native-acceptance.md) and
[original evidence/review handoff](https://github.com/fvd8383/ultimate-back-office/pull/128#issuecomment-6549010979)
retain the detailed source, profile, image, assertion, G1–G4 and operational attribution.
The [exact-head automated receipt](https://github.com/fvd8383/ultimate-back-office/pull/128#issuecomment-6549026675)
reported no major issues for `fc3eeeb767`; automated review is distinct from owner
acceptance and formal GitHub approval. This closeout carries forward the completed
assessment without downloading or rehashing its 116 original evidence files.

| Separate native result | Tested source | Completed assertions |
| --- | --- | --- |
| Core20 — 20/20 PASS | `2ab14c7d8212d1170aafe6d8dcdf3b178dd7d5bf` | 1,315 = 805 case + 510 setup/identity |
| Journal3 — 3/3 PASS | `ccbc708b62891c70071c6ea6e42dcfaf5a3976d7` | 593 = 54 case + 539 outside-case |
| Supplement17 — 17/17 PASS | `910c1345221dcf78cf0e50f998449e2b9a50ec62` | 1,316 = 776 case + 540 outside-case |

The supplemental 540 include setup/identity **and the final shared assertion**.
All three launchers exited 0 in their recorded runs. They are three source-specific
runs/images, not one combined run or an all-77-case result. Core protected-factory
and instrumented cases retain their distinct attribution; supplement17 is instrumented,
and journal3 uses separate helper-loaded fault/control and extension-free recovery workers.

Accepted limitations remain effective: journal PHP API-return injection is not
failed-kernel-syscall or power-loss proof; process interruption is not power-loss
durability; approved-PDF fixture integration is not a customer/browser workflow.
Historical failures remain failed and their missing packet/transaction outcomes
remain unestablished. Cleanup, permissions, service and image observations come
from saved operator records; hash verification did not re-observe live services.
The [recorded limitations](sprint-8.8-m6c-native-acceptance.md#proportionate-limitations-and-recorded-operational-observations),
including unchecked individual PNG chmod returns, the uncaptured exact cgroup path,
teardown exit attribution and missing aggregate SQL totals, remain disclosed.
Neither the merged documentation head nor implementation merge was natively executed.

## Approved B14/B16 allocation

| Mandatory future portion | Allocated milestone | Execution state |
| --- | --- | --- |
| Served-policy/header checks | M6E | Unexecuted; separate authorization required |
| Integrated browser navigation, CSP and inert-control validation | M6G | Unexecuted; separate authorization required |

This allocation is **APPROVED**, not a waiver or PASS. The unchanged
[77-case requirement matrix](sprint-8.8-m6-implementation-plan.md#24-implementation-test-matrix)
continues to define B14/B16; M6F internal-controls testing is not a substitute.
No browser execution, implementation or public activation is authorized here.

## Formal closeout proposal and effective status

**Proposed final status: M6C — COMPLETE / ISOLATED NATIVE GATES PASS / FORMALLY CLOSED.**
The single remaining closeout decision is explicit owner acceptance of this formal
closeout proposal, followed by merge of the PR containing it. Only when both occur
does the proposed label become effective and M6D become **NEXT / NOT STARTED**.
Until then, the current effective status is **MERGED / SCOPED EVIDENCE ACCEPTED /
CLOSEOUT REVIEW PENDING**. Closure is not backdated to PR #128's manual merge.

This conditional rule is the authoritative status transition for the linked current
status sections: acceptance and merge make it effective without a second status-only
commit or a guessed future merge SHA/time. M6 stays **IN PROGRESS**, M6B stays formally
closed, and M6E–M6G remain **NOT STARTED**. Identifying M6D as next does not authorize
its implementation; a separate explicit handoff is required.

## Documentation integrity and operational boundary

This closeout changes only documentation on `codex/sprint-8.8-m6c-closeout`, based on
the verified merged main above. The 420 tracked paths outside `docs/` retain their
baseline paths, Git blobs/modes and working-file raw bytes. All 25 migration sources
and integrity pins, the 77 requirement definitions and existing executable operating
commands remain unchanged. These are document/Git checks, not new native PASS evidence.

Migration 025 remains **APPLIED TO WORKING STAGING** and must not be reapplied.
Its canonical source SHA-256 is
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
The last operator-reported deployed application remains
`f466fc1cdbfb53325a1db11445736bdc5ea7c6f4`; neither this record nor PR #128 establishes
M6C deployment to working staging. No live check is performed. Both backup copies
and receipts remain retained; restore is **NOT EXECUTED**.

No application/test/helper/launcher/recipe/configuration changes, runtime tests,
server access, SQL, migration, containers, images, services, deployment, providers,
Narrator work or cleanup occur in this task. Production and public activation remain
unauthorized. At this task's handoff, the closeout PR remains open for review; this task neither merges it
nor declares formal closure or starts M6D.
