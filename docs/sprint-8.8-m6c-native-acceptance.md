# M6C scoped isolated native acceptance evidence

Current owner-acceptance/closeout status: October 31, 2026 (America/New_York).
**M6C — MERGED / SCOPED EVIDENCE ACCEPTED / CLOSEOUT REVIEW PENDING.**
Frank accepted the scoped evidence and documented limitations and manually merged
PR #128; see the [owner receipt](https://github.com/fvd8383/ultimate-back-office/pull/128#issuecomment-6549296854).
Formal closure awaits explicit owner acceptance and merge of the
[closeout proposal](sprint-8.8-m6c-closeout.md); its [conditional status rule](sprint-8.8-m6c-closeout.md#formal-closeout-proposal-and-effective-status)
then governs. The evidence assessment below is carried forward, not reopened.

| Distinct native profile | Tested source SHA | Native result | Completed assertion accounting | Launcher |
| --- | --- | --- | --- | --- |
| Core20 / m6c-native-v1 | `2ab14c7d8212d1170aafe6d8dcdf3b178dd7d5bf` | 20/20 PASS | 1,315 = 805 case + 510 setup/identity | exit 0; 198.413633410 s |
| Journal3 / m6c-native-journal-v1 | `ccbc708b62891c70071c6ea6e42dcfaf5a3976d7` | 3/3 PASS | 593 = 54 case + 539 outside-case | exit 0; 32.233306161 s |
| Supplement17 / m6c-native-supplement-v1 | `910c1345221dcf78cf0e50f998449e2b9a50ec62` | 17/17 PASS | 1,316 = 776 case + 540 outside-case | exit 0; 51.273321532 s |

Attempted and completed totals agree for each profile. The supplemental 540 include
setup/identity **and the final shared assertion**, not setup alone. These are three
source-specific runs and three runtime images, never one run or an all-77-case PASS.
The consolidation started at `910c134`. Its documentation-only child `fc3eeeb` and
implementation merge `0bd0ce3` were **not natively executed**. No native execution,
evidence download or private-original rehash occurs in this closeout task.
The retrieval/checking observations below belong to the completed consolidation.

**Directly checked originals:** the 37 existing core files (128,693 bytes) were reused
and rehashed without download: 17/70 operator payloads, 17/17 launcher payloads,
two manifests and one publication receipt. The four authorized new directories
supplied **79 files / 546,523 bytes**: journal 17/106 operator payloads and 17/17 launcher
payloads; supplement 21/83 operator payloads and 17/17 launcher payloads; four
manifests, two publication receipts and the separately anchored supplemental
`CHECKSUM-VERIFICATION.txt`. All 13 supplied new anchors match; each selected payload
matches its manifest entry. The checksum-verification record and publication receipts
are intentionally outside their payload manifests. The combined local check covers
116 original files / 675,216 bytes, not all 106/83 operator payloads.

Path validation preceded retrieval; exact canonical paths, regular-file/no-symlink,
same-device and bounded-size checks restricted every read to the four specified
directories. There were 79 successful remote file reads, no core redownload and no
other server inspection. Original bytes and the source/name/size/hash/result inventory
remain private outside Git. Independent data-only checks covered source/run/profile,
case names/order/lanes, assertion arithmetic, children, report size/hash,
acknowledgement, capture and manifest/publication agreement. Strict-validator exit 0 /
`accepted` is **recorded evidence**, not a validator rerun by this task.

**Attribution limit:** hashes verify the saved records. Their cleanup, service,
resource, preparation, image-provenance and deployed-source observations remain
operator observations at the recorded time. Earlier preparation directories were not
retrieved, images were not inspected live, and no new operational verification is
claimed. Existing M6B/local evidence keeps the scope credited in the
[prior four-gap assessment](https://github.com/fvd8383/ultimate-back-office/pull/128#issuecomment-6458480460).

## Four-gap dispositions and remaining decisions

The finite assessment is updated against the original missing observations,
using [plan section 24](sprint-8.8-m6-implementation-plan.md#24-implementation-test-matrix)
and the inspected unchanged assertions. **All four previously blocking evidence tasks
are SATISFIED FOR SCOPED M6C REVIEW.** No missing technical observation within those
four scoped tasks remains. Frank has now accepted these G1–G4 dispositions and
their documented limitations for scoped M6C; formal closeout is a separate decision.

| Gap / original requirement and missing observation | New original evidence and disposition |
| --- | --- |
| **G1 — B23/B24/C07; build C11/C12.** B23 requires no new release/validation/event after requester loss, retaining output and counts. B24 requires independent recovery at (4,3,1) after execution three despite revocation/deletion, no fourth execution/reset, and retained stale-token/auto/operator rules. C07 requires execution three to seal then die, followed by exact-byte adoption with original receipt/current eligibility/requester checks and zero rerenders. C11 requires restarted recovery with a greater number, fresh ownership/deadline and rejection of the old owner; C12 requires two exhausted automatic claims, blocked state and one authorized, idempotent operator recovery. Previously missing: these observations with real artifacts/MySQL, including both post-seal acceptance lock orders. | Supplement cases **1–6 at 910c134**, native JSON observations/lock waits and case/worker assertions: both lock orders, actual service-created executions 1–3, third-producer death/adoption, revoked restart, deleted-requester inspection and exhausted auto/operator budget. **SATISFIED FOR SCOPED M6C REVIEW.** Instrumented real PDO/files/processes; controlled expiry/backoff is not elapsed-time proof. Helper fences, target slots and deployment compensation remain later scope. |
| **G2 — B25 and build C09.** B25 requires immutable committed success after revocation/deletion including lost acknowledgement, returned before a new-success authority gate with no duplicate effects. C09 requires resolving execution-three commit outcome before compensation/claim; uncertainty blocks blind rollback. Previously missing: actual completion COMMIT loss with independent committed-state proof and an inaccessible-authority branch. | Supplement cases **7–9 at 910c134**, relay FAULT plus distinct authoritative connections and before-replay state, followed by effect-free deleted-requester replay. **SATISFIED FOR SCOPED M6C REVIEW.** Real MySQL transport faults; the PDO exception alone proves none of the three boundaries. C09 later-current-deployment/pointer behavior remains M6D/M6E. |
| **G3 — B08/B12/B15 and sections 11/24, as scoped in the prior assessment.** B08 requires safe reconcile/adopt/quarantine after crash with no inferred success; B12 requires invalid bytes/inventory to fail sealing; B15 requires bounded/path-safe artifact handling without outside-root mutation. Previously missing: actual candidate/proof process stops and relevant write permission/space, flush/fsync/journal publication, rename/foreign-target, nonregular/cross-device/low-or-unknown-space boundaries. | Supplement cases **10–16 at 910c134** plus **all three journal cases at ccbc708** supply the missing process/filesystem and journal-return observations. **SATISFIED FOR SCOPED M6C REVIEW.** Actual permission/ENOSPC and SIGKILL observations are distinct from injected PHP API false returns. Neither establishes failed kernel fsync/rename, spontaneous storage faults or power-loss durability. Prior Linux link and local bounds evidence remains credited. |
| **G4 — B15 / section 9 allowed image/PDF selection / section 11 asset gates, plus the authored non-material/PDF native commitment.** Previously missing: a valid approved public-use PDF through request eligibility, copy, inventory, MIME/size/digest, seal and later verification; negative PDF tests did not supply it. | Supplement case **17, nonmaterial-approved-pdf at 910c134**, with an earlier effective customer approval, exact internal approval, supported document component, sealed package and exact PDF bytes/MIME/size/hash/inventory/document link. **SATISFIED FOR SCOPED M6C REVIEW.** Synthetic fixture integration, not customer approval UI, browser behavior or a new PDF engine. |

The earlier C/build timing question no longer needs a waiver to defer missing build
evidence: G1/G2 now provide it. Existing deployment/helper portions stay with their
planned milestones. Previously credited M6B/local requirements are not reopened, and
no fresh same-SHA core/journal run is required merely for uniformity.

**OWNER SCOPE DECISION APPROVED — B14/B16 browser allocation.** Frank approved
M6E served-policy/header checks and M6G integrated browser navigation/CSP/inert-control
validation, as recorded in the [closeout allocation](sprint-8.8-m6c-closeout.md#approved-b14b16-allocation).
These remain mandatory, unexecuted future gates; allocation authorizes no browser
execution. M6F internal-controls testing is not a substitute.

PR #128 is merged and scoped evidence acceptance is effective. The only remaining
closeout decision is explicit owner acceptance and merge of the separate closeout PR.
M6 overall stays **IN PROGRESS**; M6B stays **COMPLETE / STAGING PASS /
FORMALLY CLOSED**; M6D–M6G stay **NOT STARTED**; production stays **UNAUTHORIZED /
NOT DEPLOYED**. Last operator-reported deployed application remains
`f466fc1cdbfb53325a1db11445736bdc5ea7c6f4`, not the M6C tested/documentation source.
Migration 025 is **APPLIED TO WORKING STAGING** and must not be reapplied.
Both backup copies and receipts are retained; restore is **NOT EXECUTED**.

## Journal and supplemental original locations and hashes

journal operator directory:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-journal-native-operator-20261029T021104Z-b065ebf927fc`

journal launcher directory:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-native-20261029T021236Z-6ec23f87b130e32e723c80a570dff0bb`

supplement operator directory:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-supplement-wire-retry-20261029T231937Z-75e4f66c92d9`

supplement launcher directory:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-native-20261029T232111Z-213ee7991a373be59cd9b348aabdae73`

| Original file / role | SHA-256 |
| --- | --- |
| journal operator `M6C-JOURNAL-NATIVE-ACCEPTANCE.txt` | `e8e12833a5b128b45ff2f3c6ef261db681444ca2fe6cd9e6f6fa53d0049b045a` |
| journal operator `SUMMARY.txt` | `618c5fb898ed8cf0bc470082035363649bcb04870628dd8e6c6fa1d8100c74f1` |
| journal operator `SHA256SUMS` | `822bbe088f37a16b3dba03df927bf4c4d8716dcab5a8d7a6b15f0e1652301b17` |
| journal launcher `native.json` | `ee90010060c620ec9366018f0e1aa2eff004241930ce68c6f4b1c44b11d3ac47` |
| journal launcher `SHA256SUMS` | `bc3e9b13bb99299619568c953d28c9a347492a9745ed080fe3a9abadee3e6d75` |
| journal launcher `PUBLICATION` | `128ff1c3e4517667f16ad8562f2f4a107f560b4ec6d50f06a7061f6e5828b922` |
| supplement operator `M6C-SUPPLEMENT-WIRE-RETRY.txt` | `dc0acb8a1fc314a78576c810b7cb49acb42169a43775ac76a4a613dbbf8fce3d` |
| supplement operator `SUMMARY.txt` | `a574dc0e1e368e64b8ce67818f21b8066bd34a38602d11f81c43b33eac6ddc14` |
| supplement operator `SHA256SUMS` | `b88cdd3abbda9a74d5fc9de7385fc676449cc328ebd66b0d3488161be5c275c0` |
| supplement operator `CHECKSUM-VERIFICATION.txt` | `4e0e5d7a62fc45dcefd463480d4322163443c3e2f51a1de9e107184f114b9d64` |
| supplement launcher `native.json` | `a8b2b9de6780a3daa52660c46a3b0d8512cc5f8f1e607dfd2eb3e86a537373a5` |
| supplement launcher `SHA256SUMS` | `b90b77980cb9dc3815ce2fb3369b1801eb53901a179dad67f65074f5fcc67188` |
| supplement launcher `PUBLICATION` | `39b7dc9dc8fb64c4fd771f9ed82043d957f3b809ee114bf1fc5b0a2959340362` |

Journal runtime **IMAGE ID**:
`sha256:22607ed4b4d6626e30090b9598e38e25d9d87e51d3854a04e974dbba6af41748`.
Supplement runtime **IMAGE ID**:
`sha256:88208948cc5dbeff44ddd9f0094d922f9e15350cf38c1004884f6f7e99f5c85e`.
These agree with the retained invocation/runtime/image records. They are not repository
digests or file hashes. The core image remains separately recorded below. No image
was rebuilt, retagged or relabeled for this documentation.

## Journal3 observations at ccbc708

Run `6ec23f87b130e32e723c80a570dff0bb`; native report 19,452 bytes.
Ordered cases `producer-journal-flush-failure`, `producer-journal-fsync-failure`,
`producer-journal-publication-failure` each PASS with **18 assertions**.
Nine ordered children are control/fault/recovery for each case: three successful
same-helper controls, three fault workers, three extension-free recoveries. All exit
0; six workers load the helper and three injections occur. Fault matched-call counts
are **4/5/7**, each injected exactly once with 20 off-target calls passing through;
controls match/accept all seven calls, inject zero and pass through 149 off-target calls.

Flush failure prevents fsync; fsync failure follows successful flush; publication
failure follows accepted write/flush/fsync/close/chmod. Each retains the exact pending
producer evidence, no final journal/candidate/release, unknown inspection and blocked
recovery with no render. Original producers **2/5/8** remain distinct from recoveries
**3/6/9**. Each fault job has **(attempts, executions, recoveries) = (2,1,1)**,
zero validation/success events and unchanged pending bytes. Expected control releases
are different jobs and are not counted as fault-job successes.
[Journal case assertions](../tests/support/WebsitePlatformM6CJournalCases.php) and
[worker](../tests/support/WebsitePlatformM6CJournalWorker.php) explain the recorded
producer binding and extension absence. This proves injected **PHP API failure-return
handling**, not failed kernel syscalls, spontaneous disk faults or power-loss durability.

## Supplement17 observations at 910c134

Run `213ee7991a373be59cd9b348aabdae73`; native report 134,184 bytes.
All cases are instrumented real service/PDO/files, **not protected-factory cases**.

| Ordered case | PASS assertions |
| --- | ---: |
| 1. acceptance-revocation-first | 41 |
| 2. acceptance-success-first | 79 |
| 3. third-execution-adoption | 102 |
| 4. third-revoked-recovery-restart | 66 |
| 5. third-deleted-inspection | 60 |
| 6. automatic-budget-operator | 71 |
| 7. completion-not-committed | 99 |
| 8. completion-ack-dropped | 91 |
| 9. authoritative-outcome-unavailable | 36 |
| 10. candidate-process-kill | 27 |
| 11. proof-process-kill | 59 |
| 12. artifact-write-permission | 9 |
| 13. artifact-space-exhaustion | 10 |
| 14. artifact-rename-permission | 6 |
| 15. foreign-target-collision | 6 |
| 16. filesystem-admission | 6 |
| 17. nonmaterial-approved-pdf | 8 |
| **Case total** | **776** |

The 1,316 attempted/completed total includes **540 outside-case assertions**:
setup/identity plus the final shared no-publication assertion. All 63 child records
represent distinct stopped processes (60 workers, three relays); nine deliberate
SIGKILL outcomes and no unexpected child failure or cleanup peer termination.
These are recorded run counts, not processes launched by this documentation task.

G1 observations: independent MySQL waits are **10 blocked by 11** (revocation wins)
and **13 blocked by 12** (acceptance wins). Revocation-first retains sealed bytes
without release/validation/success; success-first permits effect-free historical
replay after revocation. Third adoption uses actual execution attempts **3/4/5** and
recovery **6**, counters **(3,3,0) → (4,3,1)**, unchanged bytes, no rerender and an expired
original producer. Revoked restart retains producer **9**, recovery attempts **10/11**,
counts **(5,3,2)**, fresh token/later deadline and rejected stale renewal/result.
Deleted-requester inspection retains producer **14**, recovery **15**, actual NULL FK,
counts **(4,3,1)** and denied adoption. Budget case retains producer **18**, automatic
recoveries **19/20**, then authorized operator recovery **21**, counts **(6,3,3)**;
unauthorized operator fails, duplicate operator key has no effect and unknown output
stays blocked. Attempt IDs are run-local; counters were service-created, with only
due/expiry metadata controlled. No elapsed-time or helper-fence proof is inferred.

G2 has three different observations:

- **Not committed:** completion connection **46**, job **7**, producer **24**;
  relay FAULT before-commit has forwarded=false / ok_received=false. Fresh connection
  **47** observes running / zero releases before recovery. Recovery **25** then adopts
  retained bytes at **(4,3,1)** without rendering.
- **Acknowledgement dropped:** completion connection **52**, job **8**, producer **28**;
  relay forwards COMMIT, receives and suppresses validated server OK. Fresh connection
  **53** establishes succeeded / one release **before replay**. Requester deletion then
  leaves effect-free committed-history replay, builds=0 and counts **(3,3,0)**.
- **Authority unavailable:** a separate drop-query relay leaves the observer unknown,
  builds=0, unchanged DB/file state, no claim/compensation/guessed success. A later
  authoritative replay recognizes committed history.

The two PDO HY000/2006 diagnostics alone prove none of these outcomes. The relay,
connection identity and fresh state observations together support the claims.
The earlier `ccbc708` supplemental run remains **FAILED**; its actual packet and
transaction outcome remain **UNESTABLISHED**. This successful run does not recover them.

G3: candidate PID **209** stops after one asset file; proof PID **218** stops after
seal proof/eight files but before rename. Distinct recovery retains producer identity,
uses **(2,1,1)**, does not resume/render, and respectively yields failed or
reconciliation_required with no partial success. Permission/write and rename cases
require actual permission errors; bounded 256-MiB run-owned tmpfs pressure requires
an actual subsequent store ENOSPC, plus low/unknown free-space refusal. Foreign empty
target remains untouched; distinct-device roots and FIFO inventory are refused.
Restriction/restoration and pressure cleanup are fixture assertions/records, not host
operations here. Separate journal3 supplies the remaining PHP-return failures.

G4: a non-material revision retains an effective earlier customer approval and exact
internal approval; its public-use PDF passes service eligibility and a successful
sealed build. The checked package contains exactly one expected PDF entry with
application/pdf MIME, byte size, SHA-256 and identical bytes, and an HTML document
link rather than an image. One release and success event are asserted. This is
fixture approval/integration, not a customer or browser workflow.

See the unchanged [case assertions](../tests/support/WebsitePlatformM6CSupplementCases.php),
[worker boundaries](../tests/support/WebsitePlatformM6CSupplementWorker.php) and
[PDF verification](../tests/support/WebsitePlatformM6CSupplementPdf.php). The saved
supplement report's profile-local text saying journal failure injection is missing
is preserved byte-for-byte. It describes supplement17 itself; separate journal3
supplies that evidence and is never added into supplement17's counts.

## Proportionate limitations and recorded operational observations

Sixteen fixture PNG chmod calls have no individually checked return values. Their
successful artifact use does not prove every chmod succeeded. This does not invalidate
the distinct permission-fault cases: those require the actual permission error and
checked restriction/restoration; the PDF fixture also checks its chmod. No acceptance
claim depends on treating all 16 PNG returns as proven successes.

The exact pre-removal host cgroup path was not separately captured. Supplemental
cleanup instead records all 63 children stopped, exact container/scope absence and a
scan of **194 process cgroup files**, zero unreadable/vanished and zero matches to the
exact container IDs. This supports recorded process cleanup without asserting an
independent check of an uncaptured path. Runtime sleep/PID1 teardown **exit 137 /
OOMKilled=false** is distinct from nine intentional worker kills (and journal's nine
normal child exits). Launcher/native acceptance is exit 0; retained OOM/PID event
counters are zero. No OOM failure is inferred from exit 137 alone.

Exact SQL-statement/connection totals were not recorded. The known one schema / 25
migration executions per native profile and specific connection identities do not
supply those totals, but the missing aggregate is not a contradiction of the lock or
COMMIT observations. These limitations do not create new test requirements or defeat
G1–G4 at the stated layers. No material integrity, identity or outcome contradiction
was found in the checked records. Physical durability, deployment/restore, served
policy and browser validation remain unproved by these build-only profiles.

The operator records report cleanup and service restoration, preservation of images,
sources/pins and the working application, with no working-staging/production DB
access. Those are hash-verified historical statements only. Both backups/receipts
remain untouched and restore remains NOT EXECUTED; earlier failures are retained.

## Current supplemental evidence status, October 29, 2026

**Historical snapshot — superseded for current status by the October 31 assessment above.**

Core20 remains historical PASS at `2ab14c7d8212d1170aafe6d8dcdf3b178dd7d5bf`.
The operator reports **journal3 PASS at `ccbc708`**, 593 assertions including
54 case assertions, and **supplement17 FAILED at `ccbc708`**: six PASS / one FAIL /
ten UNREACHED, launcher exit 1, 984 attempted/completed assertions. The six passing
cases account for 419; the remaining 565 combine setup/identity and partial
failed-case assertions without a published split. These six passes do not belong
to the new correction head. See the [native failure record](sprint-8.8-m6c-native-validation.md#supplemental-native-failure-and-exact-commit-correction-october-29-2026)
for exact cases, process observations and separately assigned evidence hashes.

`completion-not-committed` failed after relay `ARMED` with `wire_expected_commit`;
no successful fault or fresh authoritative outcome was retained. The historical
packet and transaction outcome remain **UNESTABLISHED**. The source-confirmed
mysqlnd default trailing-space mismatch is now corrected by an exact two-form,
byte-preserving test-only recognizer, with [local before/after packet results](sprint-8.8-m6c-local-implementation.md#supplemental-exact-commit-correction-october-29-2026).
No new native run, production recovery fix or completed broader acceptance package
is claimed. This task did not retrieve server evidence or independently verify
operator hashes. Earlier preparation failures remain at their own SHAs.

The current image belongs to `ccbc708`; later authorized preparation must establish
reviewed-new-source provenance before supplemental native retry. Old receipts/image
identities remain untouched. Unaffected core20/journal3, backup and transport smoke
are not automatically repeated. Formal acceptance stays **PENDING**, browser
allocation stays an owner decision, backups/receipts remain untouched and restore
is **NOT EXECUTED**. No approval, merge, deployment or formal closure is authorized.

## Producer-journal authoring status, October 28, 2026

**Historical snapshot — superseded for current status by the October 31 assessment above.**

**CORE NATIVE GATE — HISTORICAL PASS at `2ab14c7`.
STORAGE FAULT COVERAGE — AUTHORED / PARTIAL.
HELPER COMPILATION/RECIPE STARTUP-LOAD — OPERATOR-REPORTED PASS at `4d49e5f`.
RECEIPT ADMISSION — FAILED at `4d49e5f`; BOOLEAN CORRECTION LOCALLY TESTED.
SUPPLEMENTAL NATIVE EXECUTION — NOT PERFORMED.
FORMAL M6C ACCEPTANCE — PENDING.**

The correction starting at `5ff2b601a3ae07a47094bd4815d6f4cfc90db1ea` authors three
additional fixed instrumented outcomes under the separate `m6c-native-journal-v1`
profile. The [native guide](sprint-8.8-m6c-native-validation.md#producer-journal-fault-profile-october-28-2026)
defines checked producer-journal flush, fsync and pending-to-final publication
boundaries, the child-local PHP API-return interceptor and required controls.
The [local results](sprint-8.8-m6c-local-implementation.md#producer-journal-local-authoring-october-28-2026)
distinguish synthetic operation observations and real private-file/fake-PDO recovery
from the still-unexecuted Linux API interception and real-MySQL cases.

The original desktop authoring did not build/load the helper. The later
[operator preparation and receipt failure](sprint-8.8-m6c-native-validation.md#journal-helper-preparation-and-receipt-correction-october-28-2026)
reports compilation/linking and recipe startup-load PASS, then receipt admission
exit 255 at `4d49e5f`; eight retained warnings were not assessed by this desktop task.
The supplied image/binary/report hashes were not retrieved or verified here.
The [boolean correction](sprint-8.8-m6c-local-implementation.md#journal-receipt-boolean-correction-october-28-2026)
has fresh local receipt/type/serialization and policy tests, including intended ZTS
rejection on Windows PHP 8.4.24. It changes no native acceptance rule or evidence
attribution. Separate runtime startup-load verification, six runtime suites, both
profile admissions, arming, status calls, actual interception and native recovery
remain unexecuted. New-source preparation requires later exact-source image/receipt
provenance; the old failed receipt/image must not be edited or relabeled.
Exact core20 and existing supplement17 profiles remain unchanged and do not satisfy
the separate three-case journal gate. The killed-child normalization correction is
preserved; the operator reports the host Linux protocol suite passed once at
`4d49e5f`, while runtime suites remain unexecuted. None of the four
broader packages is complete through this authoring. API-return injection would
not establish kernel EIO, spontaneous disk failure or power-loss durability.
Production, migrations/pins, historical evidence, browser-owner decision and backup/
restore status remain unchanged; no native run, approval, merge or closure occurs.

## Supplemental acceptance status, October 28, 2026

**Historical snapshot — superseded for current status by the October 31 assessment above.**

**M6C CORE 20-CASE NATIVE GATE — HISTORICAL PASS at `2ab14c7`.**
**SUPPLEMENTAL COVERAGE — PARTIALLY IMPLEMENTED LOCALLY / REVIEW REQUIRED.**
**SUPPLEMENTAL NATIVE EXECUTION — NOT PERFORMED. FORMAL M6C ACCEPTANCE — STILL PENDING.**

The [completed assessment](https://github.com/fvd8383/ultimate-back-office/pull/128#issuecomment-6458480460)
reported 13/13 conversations resolved and identified four finite evidence tasks.
The new test authoring on the same branch starts at `fa617fd7eeda92d8febdce66c46f973a5ae23a7e`:
A post-seal authority/execution-three recovery; B real completion transport loss;
C candidate/seal interruption and build-store filesystem faults; D approved PDF
through the supported document component with non-material eligibility.
The [operating guide](sprint-8.8-m6c-native-validation.md#supplemental-authoring-october-28-2026)
defines all 17 fixed instrumented cases, mechanisms, profile selection and later
preparation requirements. The [local implementation record](sprint-8.8-m6c-local-implementation.md#supplemental-local-validation-october-28-2026)
separates actual private-file/process regressions from modeled DB/kernel behavior.

None of G1–G4 is resolved by authoring. At the 17-case source, flush/fsync failure and
producer-journal publication failure remained missing C mechanisms. The later
separate journal profile authors bounded API-return injection. Compilation and recipe
startup-load are now operator-reported PASS at `4d49e5f`; receipt admission failed,
and runtime interception/native outcomes remain unverified. All new native cases require exact-source review and later authorized
native execution. No new image identity is asserted. The original 20-case inventory,
acceptance rules, 1,315 assertions (805 case + 510 setup/identity), launcher exit 0,
verified transport, cleanup/publication PASS and earlier FAILED/BLOCKED histories
retain their original source/run attribution and are not rerun or relabeled here.

The 77-case plan is unchanged. Browser timing at M6E/M6G is recommended, not approved
or executed. Backup/download remains operator-reported COMPLETE; restore remains
NOT EXECUTED. No new download, backup, host preservation check or cleanup occurs.
M6B closure and M6D–M6G status are unchanged; no implementation merge or formal
acceptance is authorized by this supplement.

## Source, scope and provenance

The historical core native test executed **only**
`2ab14c7d8212d1170aafe6d8dcdf3b178dd7d5bf`, also the clean starting desktop/PR
head for the October 27 core documentation task on `codex/sprint-8.8-m6c-artifact-builder`, [PR #128](https://github.com/fvd8383/ultimate-back-office/pull/128).
Run token: `fa4a34d87d165e463e15ad0445217b0f`; contract: `m6c-native-v1`.
The [existing exact-source review](https://github.com/fvd8383/ultimate-back-office/pull/128#issuecomment-6242505861)
was completed without findings. That is an automated review, not formal approval.
The later documentation commit is a different, **not natively executed** SHA;
its executable-content equivalence is established separately below.

The [M6 plan, section 25](sprint-8.8-m6-implementation-plan.md#25-recommended-submilestones-and-pr-sequence)
defines M6C's staging exit gate as an approved isolated build-only fixture validating
assets, permissions and bytes without activation. This run supplies evidence for
that scoped gate. Neither a working-staging build, public activation nor a new
migration is added as a requirement. The plan's 77 cases span broader M6 work;
the 20-case native contract is not a one-to-one substitute for that matrix.

Evidence layers used here:

- **Direct evidence:** 37 original files, 128,693 bytes, retrieved read-only through
  the existing `codex-validation` SSH route. Local SHA-256 checks verified
  **17/70 operator manifest payloads**, **17/17 launcher manifest payloads**,
  both manifest-file hashes and the separately hashed `PUBLICATION` receipt.
  All six user-supplied file hashes matched. No raw evidence is committed.
- **Recorded operational observations:** cleanup, preservation, operation counts,
  image preparation provenance and service restoration are statements in those
  hash-verified operator records. They were not independently re-observed on the
  live host. Hash verification establishes record integrity, not a new live-state check.
- **Source-derived interpretation:** the unchanged coordinator/case assertions and
  contract explain what each PASS proves. In particular, owner/loser PID labels are
  inferred from record order and committed control flow, not independently labeled
  process events. Historical local regressions retain their original source/run scope.

The private retrieval inventory records each filename, original source, byte size,
checksum and verification result. The selection includes the operator report and
summary, invocation/result, strict-validator record, child classification, image
verification, counters, cleanup/preservation records and launcher stdout/stderr;
all 17 launcher payloads include native JSON, child stdout/stderr, capture and
acknowledgement, runtime/DB identities, exit states, limits, counters and status.
Manifest paths were validated before retrieval; absolute/escaping paths and unsafe
substitutions were rejected, and resolved remote paths were confined to the exact
two directories. Downloads preserve original bytes. Original evidence was not edited.

The operator's complete **70/70** verification claim is not this task's count.
The operator's 184 preparation and ten selected older provenance verifications
remain carried-forward claims; those earlier directories were not accessed.
The strict PHP acceptance validator's recorded exit 0 / `accepted` was inspected;
the validator was not re-executed. Desktop checks independently compared JSON shape,
source/run binding, all 20 names/lanes/counts, arithmetic and receipt/capture agreement.

## Original locations and distinct checksum roles

Operator directory:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-git-native-operator-20261028T010938Z-df1b0ceabf8c`

Launcher directory:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-native-20261028T010951Z-fa4a34d87d165e463e15ad0445217b0f`

| Original file and role | Locally verified SHA-256 |
| --- | --- |
| Operator `M6C-GIT-ENVIRONMENT-NATIVE-ACCEPTANCE.txt` — report | `be4dbca123210e8fd4c818061b65701b6e3b8f83b35f34031db93e43ce877b86` |
| Operator `SUMMARY.txt` — summary | `c8bbd8c5a78fcb0f811618e0b774c14a25abfaf0eb74e70b61b3c990df42a269` |
| Operator `SHA256SUMS` — manifest file | `c7c9e699ae7caf9c0348385a926fae095fb062d8ff0c7c113d7ec9240435f86d` |
| Launcher `native.json` — native result, 42,812 bytes | `ddad91fd14d26c8701d05fdc66ea29f69bdc950fb2c92c4823a79ae1079a6c7f` |
| Launcher `SHA256SUMS` — manifest file | `7f29951aed4fd2f0b2ae29b3d37693c85853fdcc519c7e482319c6e23f12a5c4` |
| Launcher `PUBLICATION` — publication receipt, outside payload manifest | `6d6e085412bcdb0f0fcd4b76b431320070033f972cd2a6e3f4b4af207bcaa147` |

| Runtime identity role | Value in retrieved evidence |
| --- | --- |
| Runtime IMAGE ID | `sha256:cd4705ca898598b84c735ebafdffc6d0591e3e71539cc38264c840d6f5889a8c` |
| MySQL IMAGE ID | `sha256:7c07d11b694dcc7e2ef3de845075eca1a8b39868f85f519f938b54c432edc1cb` |
| PHP-base IMAGE ID | `sha256:daa9b0c758912ad686f0528cf6c4085b2ee34b51a6733055e522102a10ba37a2` |
| MySQL repository digest used by invocation | `sha256:85b9bf2e29cf836ecb8c2a15a935d4ba0c606631dff1dd79531a11983c638f2a` |
| PHP-base repository digest used by invocation | `sha256:a2d10c137d6ec9a41331e8e03e9d5468c85bfa8b1971436ffb2ad4f19d890e27` |
| Runtime / platform | PHP **8.4.27**, MySQL **8.4.11**, Linux / amd64 |

The runtime and DB identity payloads agree with the operator image record and each
other's network binding. The operator record binds the runtime source label to
`2ab14c7…` and reports matching preparation layers/recipe. The PHP-base image ID
is an operator-recorded identity, not an image independently inspected by this task.
Image IDs, repository digests, file hashes, manifest hashes and receipt hashes are
distinct. Host launcher PHP `/usr/bin/php8.3` is not the runtime PHP version.

## Completed native result

One full `--run` invocation; actual launcher exit **0**; **198.413633410 seconds**
(198.414 rounded). All **20 PASS / 0 FAIL / 0 UNREACHED**. Assertions attempted
and completed: **1,315 / 1,315 = 805 case assertions + 510 setup/identity assertions**.
The committed 1,200-second execution and 90-second cleanup/evidence bounds were
unchanged. Source verification recorded 461 tracked source files; that is the
native whole-tree count, distinct from the October 27 documentation task's historical 392 paths outside `docs/`.

| Case | Native lane | PASS assertions |
| --- | --- | ---: |
| protected-startup-denials | protected | 53 |
| protected-multipage-build | protected | 51 |
| protected-replay | protected | 60 |
| concurrent-claims | instrumented | 54 |
| requester-denied-after-claim | instrumented | 13 |
| source-denied-after-claim | instrumented | 13 |
| asset-loss-after-claim | instrumented | 12 |
| asset-corruption-after-claim | instrumented | 11 |
| execution-verification-renewal | instrumented | 49 |
| recovery-verification-renewal | instrumented | 53 |
| stale-completion | instrumented | 44 |
| killed-before-journal | instrumented | 15 |
| killed-after-seal | instrumented | 25 |
| fresh-process-adoption | protected | 80 |
| missing-evidence | instrumented | 56 |
| tampered-evidence | instrumented | 60 |
| ambiguous-evidence | instrumented | 63 |
| audit-rollback | instrumented | 44 |
| validation-rollback | instrumented | 44 |
| no-publication | instrumented | 5 |
| **Total case assertions** | **4 protected cases / 16 instrumented cases** | **805** |

The protected lane executes the unchanged CLI/factory at the constrained worker
identity. `protected-replay` also includes instrumented service request replay;
`fresh-process-adoption` recovers a producer killed in the instrumented lane.
Instrumented cases use real MySQL and real private artifacts with test dependency
wiring and controlled faults. They do not prove protected factory admission for
every fault. The [native guide](sprint-8.8-m6c-native-validation.md#authored-native-acceptance-cases)
and [committed case assertions](../tests/support/WebsitePlatformM6CNativeCases.php)
retain these distinctions.

Both concurrent children reached READY before release. The case requires one
owner through CLAIM → SEALED → RESULT, one attempt, one render, exact completion
replay, and a loser with no claim/render. Both recorded exits are 0. Namespace
owner PID 78 / loser PID 77 is a source-order inference. The JSON does not retain
independently labeled owner/loser protocol events.

Fresh protected recovery waited **120.070275863 seconds** for actual lease expiry
and **30.122954766 seconds** for automatic backoff. Six other expiry cases mutate
owned lease metadata; verification-renewal cases shorten leases under controlled
faults. Those are not elapsed-cadence or full heartbeat-race proof.

All 29 child records were checked: **8 protected / 21 instrumented**, four expected
startup denials and six deliberate SIGKILL outcomes; no unexpected child failure,
`source_error`, `worker_error` or cleanup-terminated peer was recorded. Absence of
a diagnostic is not universal dependency proof. Audit rollback deliberately caused
SQLSTATE 23000 / driver 1452; validation rollback caused HY000 / 3819. These cases
assert no partial release/validation/success audit and unchanged sealed bytes.

## Requirement-to-evidence mapping

Identifiers and expected meanings below come from [plan section 24](sprint-8.8-m6-implementation-plan.md#24-implementation-test-matrix).
**S** means supplement17 at `910c134`; **J** means journal3 at `ccbc708`.
Rows retain core-specific limits and add the new evidence without reopening credited
M6B/local layers. **L** means previously recorded local/synthetic results with inspected assertions,
not tests rerun here. **P/I** mean protected/instrumented cases in the historical core20 run.
**O** means operator operational observation. **U** means evidence not established
by core20 alone. A row names actual asserted behavior, not blanket completion of its ID.

Local sources: [M6B behavior](../tests/WebsitePlatformM6BBehaviorTest.php),
[authorization](../tests/WebsitePlatformM6BAuthorizationTest.php),
[contract](../tests/WebsitePlatformM6BContractTest.php),
[recovery](../tests/WebsitePlatformM6BRecoveryTest.php);
M6C [artifact](../tests/WebsitePlatformM6CArtifactTest.php),
[security](../tests/WebsitePlatformM6CSecurityTest.php),
[integration](../tests/WebsitePlatformM6CIntegrationTest.php),
[recovery/progress](../tests/WebsitePlatformM6CRecoveryTest.php),
[execution](../tests/WebsitePlatformM6CExecutionTest.php),
[admission](../tests/WebsitePlatformM6CAdmissionTest.php),
[bounds](../tests/WebsitePlatformM6CBoundsTest.php),
[stored input](../tests/WebsitePlatformM6CStoredInputTest.php) and
[Git source](../tests/WebsitePlatformM6CNativeGitSourceTest.php).
Their [dated validation record](sprint-8.8-m6c-local-implementation.md) includes
the latest 11 sequential suites / 8,931 assertions at the tested source, plus
earlier source-specific results. Counts from different runs are not combined.
The separately closed [M6B native gate](sprint-8.8-m6b-native-validation.md#native-coverage-and-retained-limitations)
provides persistence/FK/request/claim/revocation/recovery evidence using synthetic
artifact receipts; it is not new M6C artifact-byte evidence.

| Plan ID and requirement | Actual supporting evidence and remaining boundary |
| --- | --- |
| B01 material eligible revision, exact approvals | L artifact/integration; P `protected-multipage-build` verifies persisted release, canonical input/manifest/artifact/payload/validation hashes, actual asset bytes, two validations and one audit. I `no-publication` checks published pointer absent. Synthetic approval seeding is not approval UI coverage. |
| B02 non-material effective earlier approval | L M6B behavior checks allowed and missing/ambiguous approval baselines. Core20 used a material fixture. S case 17 now supplies non-material/effective-earlier-approval artifact integration (G4, satisfied). |
| B03 ineligible revision variants and zero writes | L M6B behavior denies lifecycle/site/approval variants with effect checks. U full native variant matrix in this run. |
| B04 site/business/module eligibility at request and execution | L M6B behavior and M6C execution; I `source-denied-after-claim` changes revision lifecycle and asserts failed attempt/count retained, zero builder/files/success and exact audit. U all site/business/module variants with real artifacts. |
| B05 successor eligibility | L M6B behavior and M6C integration reject newer material successor while preserving sealed evidence. U native successor variant matrix. |
| B06 duplicate/simultaneous requests | L M6B replay/behavior; P `protected-replay` includes sequential exact service request replay with one job/release/attempt/event and unchanged bytes/mtime. U simultaneous requests here: `concurrent-claims` races claims, not requests. Earlier M6B concurrency remains separate. |
| B07 transient retry and three-execution budget | L M6B behavior/recovery and M6C recovery exercise quarantine, transient faults, retry identity and budgets. U real storage failure through exhausted retry in this run. |
| B08 crash/evidence/adoption | L integration/recovery exercise partial output, producer binding and uncertain evidence; I `killed-before-journal`, `killed-after-seal`, asset faults and missing/tampered/ambiguous evidence; P `fresh-process-adoption` adopts unchanged bytes with one execution/one recovery after real waits. Core20 alone did not cover all remaining process/store boundaries. S cases 10/11 add actual candidate/proof process stops; S cases 7–9 supply completion transport faults; J supplies journal-return failures (G2/G3, satisfied). Physical durability remains unproved. |
| B09 lease/heartbeat race and stale completion | L recovery/progress and M6B clocks; I `concurrent-claims` proves one owner/render, renewal cases extend only the active lease without extending deadline, `stale-completion` rejects expired completion and preserves bytes. U full competing expiry/heartbeat/late-owner race and elapsed renewal cadence. |
| B10 canonical identity/determinism | L M6B contract golden ordering/NULL/integer/Unicode and identity changes; M6C artifact same-job/different-producer byte determinism and stored-input representation tests. P multipage verification checks canonical input and release binding, not every golden-identity perturbation. |
| B11 exact replay/conflicting output | L artifact/integration/recovery preserve immutable seal and reject replacement; P replay and I concurrent completion replay prove no duplicate effects. U native conflicting second output for the same release key. |
| B12 tamper/missing/extra/MIME/digest | L security/admission/stored-input cover inventory, byte and metadata negatives; I asset loss/corruption and evidence tampering block success/repair. U full native malformed inventory/MIME matrix; stage/restore portions belong to later milestones. S cases 10–16 and J supply the specifically missing build-store boundaries (G3, satisfied); stage/restore remains later scope. |
| B13 private canaries and allowed public fields | L artifact/security projection/canary tests; P multipage scans all eight artifact files for canaries/private upload locators and preserves approved public facts. Native worker diagnostics record bounded fields; local diagnostic suites cover privacy schema. U universal leakage proof. |
| B14 multipage/nav/HTML/CSP/broken references | L artifact/security validate distinct documents, navigation, bad refs/scripts/PHP/.htaccess/CSP; P multipage verifies three distinct pages, full nav and CSP. U browser execution and native injection of every malformed HTML/reference. Browser portions have [approved M6E/M6G allocation](sprint-8.8-m6c-closeout.md#approved-b14b16-allocation); mandatory and unexecuted. |
| B15 rights/ownership/path/SVG/bounds | L security/admission/bounds deny rights/business/MIME/path/SVG/budget faults; P startup denies UID/config ownership/mode/source violations; P build verifies approved image bytes; I asset faults preserve exact journal and quarantine. Core20 alone did not supply the remaining Linux store negatives or positive PDF. S cases 12–16 establish actual permission/space/rename, preserved foreign target, cross-device/FIFO and low/unknown-space refusals; case 17 supplies positive approved PDF. J adds injected journal API returns (G3/G4, satisfied). Earlier Linux link evidence stays credited. |
| B16 inert form/server-owned profile | L artifact/security and M6B contract reject active forms/caller-selected production profile; P build asserts disabled required email and no form. U browser behavior/production profile operation; production stays denied. Browser portions have [approved M6E/M6G allocation](sprint-8.8-m6c-closeout.md#approved-b14b16-allocation); mandatory and unexecuted. |
| B17 source mismatch/dirty runtime and revoked requester before claim | L Git-source/CLI and M6B authorization reject source contamination, dirty/wrong SHA and revoked requester with no allocation. P startup verifies wrong SHA and ownership/config/UID denials, no attempt/artifact. U protected dirty-runtime and full preclaim revocation variants in this run. |
| B18 permitted role changes and trusted worker | L authorization checks Admin↔Super Admin, untrusted worker denial and source policy; P build establishes one valid protected identity/claim. U native role-transition/environment mismatch combinations here. |
| B19 inactive/deleted/NULL requester | L authorization checks each denial and zero allocation; inherited M6B native FK/revocation coverage remains separate. I requester-after-claim is only inactivity after allocation. U full variants with M6C artifacts. |
| B20 business-only role denial | L authorization checks absence of internal authority and zero execution. U native business Owner/Admin/is_owner variants in this run; no inference from protected valid admin. |
| B21 revoked original requester across retry | L authorization preserves original requester, prior attempt and retry denial/cancellation; M6C recovery preserves requester on new attempt. U native artifact retry/revocation combination. |
| B22 claim/revocation lock orders and reauthorization | L authorization models lock order/deadlock/FK reread; earlier M6B native covers five revocation modes × both lock orders. I requester-after-claim shows committed-claim-first pre-output denial only. Claim-vs-claim remains distinct from final acceptance/revocation. S cases 1/2 now establish the distinct final-acceptance lock orders; inherited M6B claim/revocation evidence remains separate. |
| B23 requester loss before success/adoption | L integration/authorization retain sealed evidence and deny new success after revocation; I requester-after-claim proves no render and atomic failure with execution count retained. S cases 1/2/4/5 now establish post-seal denial, revoked/deleted recovery inspection and preserved output with real MySQL/files (G1, satisfied). |
| B24 exhausted execution-three independent recovery | L integration/recovery use real files with fake counters (4 total / 3 execution / 1 recovery), revoked requester and no fourth render; earlier M6B native proves persistence accounting with synthetic receipts. P adoption here is execution one. S cases 3–6 now establish actual third-execution adoption, revoked restart/deleted inspection, fresh/stale ownership and auto/operator limits (G1, satisfied). |
| B25 committed success after revocation/deletion/lost acknowledgement | L M6B authorization/replay and M6C integration lost-ack fault preserve one success and sealed evidence. P replay has no revocation/lost-ack fault. Earlier M6B success-history proof remains separate. S cases 7–9 now supply actual completion COMMIT boundaries and fresh authoritative outcomes, deleted-requester historical replay and unavailable-authority blocking (G2, satisfied). |
| B26 requester substitution/recovery execution denial | L authorization/contract reject overrides with unchanged state; M6C integration denies executing a recovery lease. P recovery adopts without rerender. U all forged identity/recovery-kind native combinations here. |

Other applicable build obligations: local dependency guards and inspected native
wiring enforce external I/O outside transactions; native audit/validation rollback
cases verify atomic result persistence, but do not trace every I/O call under locks.
C03–C05/C07/C09/C11–C12 build portions and U01/U02 build authority inherit the
specific ownership/recovery/replay evidence above, with the formerly missing C07 exhausted-budget and C09
lost-ack artifact/native combinations now supplied by S cases 3–9. S01–S03 schema evidence
belongs to the unchanged, formally closed M6B gate. I `no-publication` asserts no
published revision pointer, deployment/target/health rows or production/conversion
approval. O records zero provider/activation activity. D01–D26, R01–R06, deployment
portions of C/U, internal controls and integrated closeout remain later M6 scope.

## Acceptance limits requiring an explicit disposition

The core20 inventory remains a bounded historical PASS. The current four-gap
assessment above credits the additional journal/supplement originals and inherited
M6B/local evidence at their stated layers; Frank accepted G1–G4 and these limitations.
B14/B16 browser allocation is approved for M6E/M6G; those checks remain mandatory
and unexecuted. Deployment, served
policy, physical durability and the full 77-case M6 matrix are not established by
these three build-only runs. No criteria are waived or new tests authorized.

Historical BLOCKED/FAILED runs remain failures at their own SHAs. In particular,
`abac019` remains 3 PASS / 1 FAIL / 16 UNREACHED, with exact cause **UNDETERMINED**.
The later PASS follows Git isolation, but does not recover historical HOME/stderr
or conclusively identify the earlier origin. SIGKILL/recovery and file fsync are
not power-loss durability proof. No public/customer activation, production readiness,
restore completion or formal M6C closure follows from this PASS. Backup/download
remains operator-reported **COMPLETE**; both copies/receipt retained; restore
**NOT EXECUTED**.

## Capture, cleanup and preservation

Local checks confirm `native.json` is 42,812 bytes, source/run-bound and identical
to the acknowledgement digest/size. Normal producer/consumer/verification exits
are **0/0/0**, acknowledgement **MATCH**, no fallback. The coordinator stderr is
the supported publication acknowledgement, not an unexplained failure. `status.txt`
and `PUBLICATION` agree on primary/interruption/final **0/0/0**, cleanup **PASS** and
publication **PASS**; the receipt binds the locally verified launcher manifest.

Recorded disposable activity: two containers, one network, one MySQL start, one
coordinator, one new schema, 25 canonical migration executions and 29 workers.
Exact SQL-statement/connection totals were not recorded. A label-filtered cleanup
event summary reports zero network creates because it omitted network events;
the operator's separately recorded exact-network-ID observations and corrected
counters report one create/destroy. This limitation is retained, not treated as
zero network activity or silently removed.

The retrieved cleanup record reports exact containers/network/scopes/process group,
scratch and lock absent, preserving baseline workloads. Runtime PID1 cleanup exit
**137**, `OOMKilled=false`, and zero recorded OOM/PID-limit counters are separate
from coordinator/launcher exit **0**. This explains this recorded cleanup lifecycle;
it is not a rule that every exit 137 is benign. MySQL cleanup exit is 0.

Operator records report Docker started/stopped once and restored
inactive/dead/disabled; images/client retained; three exact temporary operator files
archived and removed. They report the deployed checkout clean at `f466fc1…`, Apache
active, independent source/volume/migration pins unchanged. No live application
configuration, database, service or current preservation state was inspected here.
The native run's zero working-staging/production DB access, deployments, image
build/pull/retag, installations/configuration changes, providers and other prohibited
actions applies to **that run**, not earlier image-preparation/regression tasks.

## Cross-source applicability

All **267 tracked paths outside docs/ and tests/** are identical in path/blob/mode
across `2ab14c7`, `ccbc708` and `910c134`. This includes all production/application
content under apps/, private/, public/, shared/, scripts/, database/ and
infrastructure/, the 25 migrations and production configuration examples. The
protected worker entry/factory, artifact builder/store/validator, build service and
eligibility implementation therefore retain the same executable content. No live
configuration was read.

Between core and journal sources, changes are confined to documentation and tests:
additive supplement/journal profiles, cases, workers, contracts and supporting
regressions; explicit profile routing in the coordinator/report/transport/launcher;
an optional fixture callback (absent for core); and the image recipe's journal helper
build. Supplemental killed-child normalization and journal boolean-receipt admission
corrections concern those newly added paths. The core contract, case body,
dependencies, protected worker and shared native process implementation are unchanged.
The shared routing additions retain the core default and its original acceptance
branch; they do not turn historical core execution into a later-source rerun.

Between `ccbc708` and `910c134`, the only non-documentation changes are the exact
two-form, byte-preserving COMMIT recognizer in
[SupplementWire](../tests/support/WebsitePlatformM6CSupplementWire.php) and its added
packet regression. Journal code, helper/recipe and dependencies are identical.
Thus core and journal evidence remains applicable to the unchanged production paths
and declared profiles. **Not all non-documentation files are identical across the
three native SHAs**; the added/corrected harness infrastructure is explicitly retained.

## Documentation-only preservation and review handoff

**Historical pre-merge consolidation snapshot — current owner decisions and manual
merge are recorded above and in the [closeout proposal](sprint-8.8-m6c-closeout.md).
The original task instructions/results below are retained, not current pending actions.**

This October 31 task starts at `910c1345221dcf78cf0e50f998449e2b9a50ec62` on
`codex/sprint-8.8-m6c-artifact-builder`, with a clean worktree. The actual baseline
contains **490 tracked paths, including 420 outside docs/**. Its private inventory
records each non-doc path, Git blob ID, mode, byte size and raw-byte SHA-256. Working
files match Git blobs under normal content filtering. Working/staged/committed
comparisons must preserve that exact path set, blobs, modes and raw bytes, with no
outside-doc addition/deletion/rename. All 25 migrations/pins remain unchanged.
Migration 025's canonical/raw SHA-256 remains
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`;
it is APPLIED TO WORKING STAGING and must not be reapplied.

The single documentation commit reconciles these seven existing records:
this acceptance record, native validation, local implementation, M6 implementation
plan, sprint 8.8, Codex handoff and first-customer checklist. Private SUMMARY.txt,
retrieval inventory, detailed assessment and document/Git verification results are
saved under `C:\Users\fvd83\.ssh\m6c-consolidation-20261031`, outside Git.
They record the resulting exact documentation SHA; the commit's parent is the
supplement-tested source. Checks are limited to Markdown links/anchors/fences/status,
the unchanged 77-row plan matrix and operating Bash blocks, JSON/checksums and
working/staged/committed Git content. No application/harness/validator execution,
bootstrap, native rerun, browser test or operational action is part of this handoff.

Baseline live PR state was read through the existing Windows Git identity:
**open, non-draft, unmerged, auto-merge disabled; 14/14 threads resolved, zero
unresolved; 27 COMMENTED review submissions, zero APPROVED; zero checks/status
contexts**. No later substantive finding/rebuttal was present. The
[automated review summary](https://github.com/fvd8383/ultimate-back-office/pull/128#issuecomment-6242505861)
names exact head `910c134`, completed without findings on October 29 at
22:30:29 UTC; its [receipt](https://github.com/fvd8383/ultimate-back-office/pull/128#issuecomment-6509895830)
also names that source. This is neither CI PASS nor formal approval of the later
documentation head. No thread or review disposition is changed here.

One combined evidence summary and exact-new-head review request belongs on
[PR #128](https://github.com/fvd8383/ultimate-back-office/pull/128), omitting a duplicate
trigger if that head's review has already started automatically. The private final
handoff records the observed request/review state; no completed new-head review is
presumed. Evidence sufficiency, automated review, formal approval and owner
authorization remain distinct states.

Recommendation: accept **G1–G4 as satisfied for scoped M6C review**. Remaining
decisions are documentation-head review, owner evidence acceptance, B14/B16 browser
allocation, and separate merge/formal-closeout authorization. Leave PR #128 open,
non-draft and unmerged with auto-merge disabled. M6C formal closure is PENDING;
M6 overall IN PROGRESS; M6B formally closed; M6D–M6G NOT STARTED. No merge,
deployment, migration, image operation, new runtime test, M6D implementation or
formal closure follows from this documentation handoff.
