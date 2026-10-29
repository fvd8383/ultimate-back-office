# M6C scoped isolated native acceptance evidence

Recorded October 27, 2026 (America/New_York); original run evidence is dated
October 28 in UTC. **M6C — IMPLEMENTED / SCOPED ISOLATED NATIVE GATE PASS /
ACCEPTANCE REVIEW PENDING. M6C formal closure — PENDING.**
The PASS is supported by directly retrieved, locally hash-verified original
evidence. That documentation task executed no runtime test. The October 28
supplement below adds local test authoring, without a new native execution.

M6 overall — **IN PROGRESS**. M6B — **COMPLETE / STAGING PASS / FORMALLY CLOSED**.
M6D–M6G — **NOT STARTED**. Production — **UNAUTHORIZED / NOT DEPLOYED**.
The operator-reported deployed application remains
`f466fc1cdbfb53325a1db11445736bdc5ea7c6f4`; M6C is not deployed there.
Migration 025 is **APPLIED TO WORKING STAGING** and must not be reapplied.

## Producer-journal authoring status, October 28, 2026

**CORE NATIVE GATE — HISTORICAL PASS at `2ab14c7`.
STORAGE FAULT COVERAGE — AUTHORED / PARTIAL.
NATIVE HELPER BUILD/LOAD — NOT VERIFIED.
SUPPLEMENTAL NATIVE EXECUTION — NOT PERFORMED.
FORMAL M6C ACCEPTANCE — PENDING.**

The correction starting at `5ff2b601a3ae07a47094bd4815d6f4cfc90db1ea` authors three
additional fixed instrumented outcomes under the separate `m6c-native-journal-v1`
profile. The [native guide](sprint-8.8-m6c-native-validation.md#producer-journal-fault-profile-october-28-2026)
defines checked producer-journal flush, fsync and pending-to-final publication
boundaries, the child-local PHP API-return interceptor and required controls.
The [local results](sprint-8.8-m6c-local-implementation.md#producer-journal-local-authoring-october-28-2026)
distinguish synthetic operation observations and real private-file/fake-PDO recovery
from the still-unexecuted Linux helper and real-MySQL cases.

The test runtime recipe now authors a fixed extension build and source/binary
receipt. It was not built or loaded here; no binary hash or new image is claimed.
Exact core20 and existing supplement17 profiles remain unchanged and do not satisfy
the separate three-case journal gate. The killed-child normalization correction is
preserved; its actual Linux execution also remains outstanding. None of the four
broader packages is complete through this authoring. API-return injection would
not establish kernel EIO, spontaneous disk failure or power-loss durability.
Production, migrations/pins, historical evidence, browser-owner decision and backup/
restore status remain unchanged; no native run, approval, merge or closure occurs.

## Supplemental acceptance status, October 28, 2026

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
separate journal profile authors bounded API-return injection, with actual Linux
build/load/interception and native outcomes still unverified. All new native cases require exact-source review and later authorized
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

The native test executed **only**
`2ab14c7d8212d1170aafe6d8dcdf3b178dd7d5bf`, also the clean starting desktop/PR
head on `codex/sprint-8.8-m6c-artifact-builder`, [PR #128](https://github.com/fvd8383/ultimate-back-office/pull/128).
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
native whole-tree count, distinct from this task's 392 paths outside `docs/`.

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
**L** means previously recorded local/synthetic results with inspected assertions,
not tests rerun here. **P/I** mean protected/instrumented cases in this native run.
**O** means operator operational observation. **U** means evidence not established
by this M6C run. A row names actual asserted behavior, not blanket completion of its ID.

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
| B02 non-material effective earlier approval | L M6B behavior checks allowed and missing/ambiguous approval baselines. U non-material artifact integration in this 20-case run; material fixture PASS cannot establish it. |
| B03 ineligible revision variants and zero writes | L M6B behavior denies lifecycle/site/approval variants with effect checks. U full native variant matrix in this run. |
| B04 site/business/module eligibility at request and execution | L M6B behavior and M6C execution; I `source-denied-after-claim` changes revision lifecycle and asserts failed attempt/count retained, zero builder/files/success and exact audit. U all site/business/module variants with real artifacts. |
| B05 successor eligibility | L M6B behavior and M6C integration reject newer material successor while preserving sealed evidence. U native successor variant matrix. |
| B06 duplicate/simultaneous requests | L M6B replay/behavior; P `protected-replay` includes sequential exact service request replay with one job/release/attempt/event and unchanged bytes/mtime. U simultaneous requests here: `concurrent-claims` races claims, not requests. Earlier M6B concurrency remains separate. |
| B07 transient retry and three-execution budget | L M6B behavior/recovery and M6C recovery exercise quarantine, transient faults, retry identity and budgets. U real storage failure through exhausted retry in this run. |
| B08 crash/evidence/adoption | L integration/recovery exercise partial output, producer binding and uncertain evidence; I `killed-before-journal`, `killed-after-seal`, asset faults and missing/tampered/ambiguous evidence; P `fresh-process-adoption` adopts unchanged bytes with one execution/one recovery after real waits. U every write/rename/commit kill point and power-loss durability. |
| B09 lease/heartbeat race and stale completion | L recovery/progress and M6B clocks; I `concurrent-claims` proves one owner/render, renewal cases extend only the active lease without extending deadline, `stale-completion` rejects expired completion and preserves bytes. U full competing expiry/heartbeat/late-owner race and elapsed renewal cadence. |
| B10 canonical identity/determinism | L M6B contract golden ordering/NULL/integer/Unicode and identity changes; M6C artifact same-job/different-producer byte determinism and stored-input representation tests. P multipage verification checks canonical input and release binding, not every golden-identity perturbation. |
| B11 exact replay/conflicting output | L artifact/integration/recovery preserve immutable seal and reject replacement; P replay and I concurrent completion replay prove no duplicate effects. U native conflicting second output for the same release key. |
| B12 tamper/missing/extra/MIME/digest | L security/admission/stored-input cover inventory, byte and metadata negatives; I asset loss/corruption and evidence tampering block success/repair. U full native malformed inventory/MIME matrix; stage/restore portions belong to later milestones. |
| B13 private canaries and allowed public fields | L artifact/security projection/canary tests; P multipage scans all eight artifact files for canaries/private upload locators and preserves approved public facts. Native worker diagnostics record bounded fields; local diagnostic suites cover privacy schema. U universal leakage proof. |
| B14 multipage/nav/HTML/CSP/broken references | L artifact/security validate distinct documents, navigation, bad refs/scripts/PHP/.htaccess/CSP; P multipage verifies three distinct pages, full nav and CSP. U browser execution and native injection of every malformed HTML/reference. |
| B15 rights/ownership/path/SVG/bounds | L security/admission/bounds deny rights/business/MIME/path/SVG/budget faults; P startup denies UID/config ownership/mode/source violations; P build verifies approved image bytes; I asset faults preserve exact journal and quarantine. U Linux link/device/permission/quota/disk-full negative matrix and approved PDF integration. |
| B16 inert form/server-owned profile | L artifact/security and M6B contract reject active forms/caller-selected production profile; P build asserts disabled required email and no form. U browser behavior/production profile operation; production stays denied. |
| B17 source mismatch/dirty runtime and revoked requester before claim | L Git-source/CLI and M6B authorization reject source contamination, dirty/wrong SHA and revoked requester with no allocation. P startup verifies wrong SHA and ownership/config/UID denials, no attempt/artifact. U protected dirty-runtime and full preclaim revocation variants in this run. |
| B18 permitted role changes and trusted worker | L authorization checks Admin↔Super Admin, untrusted worker denial and source policy; P build establishes one valid protected identity/claim. U native role-transition/environment mismatch combinations here. |
| B19 inactive/deleted/NULL requester | L authorization checks each denial and zero allocation; inherited M6B native FK/revocation coverage remains separate. I requester-after-claim is only inactivity after allocation. U full variants with M6C artifacts. |
| B20 business-only role denial | L authorization checks absence of internal authority and zero execution. U native business Owner/Admin/is_owner variants in this run; no inference from protected valid admin. |
| B21 revoked original requester across retry | L authorization preserves original requester, prior attempt and retry denial/cancellation; M6C recovery preserves requester on new attempt. U native artifact retry/revocation combination. |
| B22 claim/revocation lock orders and reauthorization | L authorization models lock order/deadlock/FK reread; earlier M6B native covers five revocation modes × both lock orders. I requester-after-claim shows committed-claim-first pre-output denial only. U combined native artifact acceptance race; claim-vs-claim is not claim-vs-revocation. |
| B23 requester loss before success/adoption | L integration/authorization retain sealed evidence and deny new success after revocation; I requester-after-claim proves no render and atomic failure with execution count retained. U native revocation after sealing/during recovery adoption. |
| B24 exhausted execution-three independent recovery | L integration/recovery use real files with fake counters (4 total / 3 execution / 1 recovery), revoked requester and no fourth render; earlier M6B native proves persistence accounting with synthetic receipts. P adoption here is execution one. U combined real-artifact/MySQL exhausted-budget recovery/restart. |
| B25 committed success after revocation/deletion/lost acknowledgement | L M6B authorization/replay and M6C integration lost-ack fault preserve one success and sealed evidence. P replay has no revocation/lost-ack fault. U native M6C commit-ack loss plus authority change; earlier M6B success-history proof is separate. |
| B26 requester substitution/recovery execution denial | L authorization/contract reject overrides with unchanged state; M6C integration denies executing a recovery lease. P recovery adopts without rerender. U all forged identity/recovery-kind native combinations here. |

Other applicable build obligations: local dependency guards and inspected native
wiring enforce external I/O outside transactions; native audit/validation rollback
cases verify atomic result persistence, but do not trace every I/O call under locks.
C03–C05/C07/C09/C11–C12 build portions and U01/U02 build authority inherit the
specific ownership/recovery/replay evidence above, with C07 exhausted-budget and C09
lost-ack artifact/native combinations still unestablished. S01–S03 schema evidence
belongs to the unchanged, formally closed M6B gate. I `no-publication` asserts no
published revision pointer, deployment/target/health rows or production/conversion
approval. O records zero provider/activation activity. D01–D26, R01–R06, deployment
portions of C/U, internal controls and integrated closeout remain later M6 scope.

## Acceptance limits requiring an explicit disposition

There is no failed or unreached case within the 20-case contract. This does not
complete every broader scenario originally authored in the
[local implementation record](sprint-8.8-m6c-local-implementation.md#original-local-validation-and-later-native-gate).
Acceptance review must reconcile that broader list with this scoped evidence:
non-material/PDF artifact fixtures; full eligibility/requester variants and
artifact-integrated acceptance/commit-loss races; exhausted execution-three artifact
recovery; additional mid-write/rename/commit kill points; Linux malicious-link/device,
permission, quota/disk-full faults; and browser/security behavior are not all
independently established by this run. Existing local and M6B evidence is identified
above, not silently promoted to full M6C native coverage. No criteria are waived or
new tests authorized by this record; review must decide sufficiency or identify a
separately authorized, bounded follow-up before formal acceptance.

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

## Documentation-only preservation and review handoff

The pre-edit batch inventory contains **392 tracked paths outside `docs/`**, with
path, Git blob ID, mode and raw-byte SHA-256. Working files matched their tested
Git blobs after Git's normal content filtering. The post-edit batch comparison
verified the exact path set, blob IDs, modes and raw bytes unchanged, including production,
tests, launchers, recipes, protected workers, all assertions and all 25 migrations/pins.
Canonical migration 025 SHA-256 remains
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
No addition, deletion or rename outside `docs/` is permitted.

The documentation change contains this new acceptance record and status/cross-link
updates to `sprint-8.8-m6c-native-validation.md`, `sprint-8.8-m6c-local-implementation.md`,
`sprint-8.8-m6-implementation-plan.md`, `sprint-8.8.md`, `codex-handoff.md` and
`first-customer-checklist.md`, all under `docs/`. Private `SUMMARY.txt`, retrieval
inventory and Git/document check results record the final SHA and exact inventory
outside Git. Document checks passed: **194 links, 34 local anchors, 46 external URL
syntax checks and 14 balanced fence markers**, with all **77 plan matrix rows** and
**three operating Bash blocks** unchanged. Working Git diff checks passed; final
staged/committed comparisons are retained in the private check results. Validation
is limited to document/link/anchor/fence/status, checksum/JSON and Git diff/content
checks. No PHP suite, bootstrap, container or native rerun.
Executable-content equivalence does not mean the new documentation SHA executed
the native test. Future runtime tasks must satisfy their own source/image guards;
no image rebuild/relabel or rerun is required merely to record this documentation.

At baseline, PR #128 is open, non-draft, unmerged, auto-merge disabled, with zero
check runs/status contexts and no APPROVED review (21 COMMENTED submissions).
Zero checks is not CI PASS. Of 13 inline threads, nine are resolved and four remain
unresolved/outdated, each with an existing correction reply at `7787bec`:
[producer journal](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4304835568),
[verification renewal](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4304835573),
[bounds alias](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4304835576),
[CLI platform assertion](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4304835581).
Their dispositions are not changed by this task. A clean latest automated review
does not resolve those historical conversations or grant formal approval.

One evidence summary and one review request for the exact new documentation head
belong in the existing PR. Remaining decisions are evidence sufficiency/acceptance,
historical thread disposition, exact-new-head review and a separate authorized merge
decision. M6C formal closure remains pending. No merge, deployment, migration,
service/image operation, further native run, M6D implementation or formal closure
is authorized by this record.
