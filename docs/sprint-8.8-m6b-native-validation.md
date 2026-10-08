# M6B isolated native validation evidence

Recorded **2026-10-08 UTC** after read-only retrieval of the operator's completed run.
**M6B — IMPLEMENTED / ISOLATED REAL-MYSQL GATE PASS**.
**PR #126 — EVIDENCE RECORDED / MERGE REVIEW PENDING**.
**Working staging deployment/migration — NOT PERFORMED**.
M6 **IN PROGRESS**; M6C–M6G **NOT STARTED**; Production **UNAUTHORIZED / NOT DEPLOYED**.
M6B is not formally closed. M5 acceptance and Narrator deferral are unchanged.

## Tested revision and verification provenance

| Item | Evidence |
| --- | --- |
| Exact natively tested SHA / prior PR head | `bef67310adafa041a45ee204ed0fc99ca13b754f` |
| Original implementation baseline | `5baae28c9af68cca7694a912d7f35c87c50c9dc5` |
| Reported unchanged deployed staging SHA | `70a3051f73874e7268b9c1bba45bf19d41f9432a` |
| PR / branch | [PR #126](https://github.com/fvd8383/ultimate-back-office/pull/126), `codex/sprint-8.8-m6b-persistence-jobs` |
| Tested-head review | [Clean completed review](https://github.com/fvd8383/ultimate-back-office/pull/126#issuecomment-6049440679), 2026-10-08T00:13:04Z, names `bef67310ad` |
| Operator run identity | `ubo-stage-app / codex-validation / UID 1000` |
| Full invocation | One, 2026-10-08T00:21:06Z–00:22:00Z; actual launcher/test exits **0/0** |
| Separate summary | 2026-10-08T00:25:13Z |

The operator executed the native run under its earlier authorization. This Windows
documentation task did **not** execute it. Established `ubo-validation` SSH access
confirmed the exact host/user/UID and read only fixed existing evidence paths. Existing
host verification and credentials were retained; no sudo or identity switch occurred.
Regular-file, resolved-path containment and size checks preceded byte-preserving SCP.
Manifest paths were validated before following selected entries: plain basenames only,
no traversal, absolute paths or duplicates. No evidence file was executed.

Original bytes are retained separately from this repository summary in the durable
private local directory
`C:/Users/fvd83/.codex/visualizations/2026/09/19/01a0bb3d-94d5-7e03-a64e-cc80bd7239a7/m6b-pass-evidence`.
Its protected ACL permits only the current Windows user, SYSTEM and Administrators.
The local sibling `m6b-pass-evidence-inventory.json` records every retrieved filename,
byte size and SHA-256. Content was reviewed for secrets before selecting these excerpts;
no credentials, environment files or unrelated application data were copied or posted.

Exactly **35 files / 69,183 bytes** were retrieved. All **252 operator-manifest paths**
and **3 launcher-manifest paths** were validated. Hash verification covers the **29
selected operator entries** and **all 3 launcher entries**, plus the original summary
checksum and operator-manifest checksum. The supplied summary digest matches. The
remaining operator entries and all 1749 prior archive files were not retrieved/rehashed
by this task; the operator's preservation statement remains attributed to its report.
No remote report or manifest was edited or replaced.

## Source locations and verified hashes

Operator directory:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6b-replay-validation-20261008T001902Z`.

Original launcher directory:
`/mnt/ubo_stage_testdata/codex-validation/evidence/run-20261008T002107Z-P9dqtQHb`.

| Directory / original file | Verified SHA-256 |
| --- | --- |
| Operator / `M6B-HISTORICAL-REPLAY-AND-MYSQL-VALIDATION.md` | `8cf8794fbb2fec7ee9c0d21fa1e308d085f883f9808ed39fb825e591bc94e08b` |
| Operator / `summary.sha256` | `8ca20e9e3983a307c844e34faca63c8c22e3f39368a806261a84ba16a296fc04` |
| Operator / `SHA256SUMS` | `e7c2ff02330e2586536b71df0709444299814164295a9e2e372c00cf2e062417` |
| Operator / `manifest.sha256` | `e39f7063503033517ea15bcbff10693dea7633d4215059712db03a4dc81dcbf4` |
| Launcher / `report.txt` | `a8315f55a35b21b8e734f32767b8262425c4fe5e85a892bd186ec87bd3094b0f` |
| Launcher / `test-output.txt` | `db51980e803fd1562f8fb5e50f460b06da27f62ff27057e90f5676b29d1d4872` |
| Launcher / `mysql-tail.txt` | `c83c2b32f2288a913f647c699e1661d1d61bf551a812e270d165aa894755a0d0` |
| Launcher / `SHA256SUMS` | `c00eb3c28dcabce7680c42cf2ddfbff5a7b23df34e5549c7872400ac453857d2` |

The additional 27 operator files, each matched to the original operator `SHA256SUMS`,
are `full-launcher.json/.stdout/.stderr`, `full-invocation-started.json`,
`migration-execution-reconciliation.json`, `cleanup-verification.json`,
`docker-final-state.json/.stdout`, `after-deployed-head.json/.stdout`,
`after-deployed-status.json/.stdout`, `after-deployed-hashes.json`,
`after-apache.json/.stdout`, and all three `.json/.stdout/.stderr` files for each of
`guard-smoke`, `migration-runner`, `dns-bootstrap` and `replay-comparison`.
Their individual digests remain in the retrieved immutable manifest and private local
inventory. The full invocation start/completion metadata agrees on command, tested SHA
and invocation count; its actual exit is 0 and stderr is empty.

## Runtime and completed results

Actual recorded runtime: **PHP 8.3.6**, Docker client/server **29.8.1**, SQL-queried
**MySQL 8.4.11**, **REPEATABLE-READ**, native PDO prepares; systemd 255.
The isolated exact-SHA checkout was outside web roots, under
`/mnt/ubo_stage_testdata/codex-validation/checkouts/ultimate-back-office`.
The launcher used explicit shared-staging/volume mode, the verified rootless socket,
cached pinned Linux/amd64 image and disposable databases/test credentials.

The original report records effective MySQL CPU=1, memory=1536 MiB, additional swap=0,
PIDs=128 and database tmpfs=1 GiB within that memory cap; PHP group CPU=1, memory=1 GiB,
swap=0, tasks=32 and KillMode=control-group, at most three PHP processes at 256 MiB each.
The 1200-second deadline, bounded cleanup/publication, admission and operating reserve
checks passed. Storage remained a monitored 6 GiB budget, **not a quota**. No OOM or
PID-limit events were recorded. These are observations from the saved run, not new
runtime measurements or authorization to repeat it.

| Separate result | Recorded outcome |
| --- | --- |
| No-INI guard smoke | PASS; two mount forms / three PHP profiles |
| Migration runner simulation | 848 assertions PASS |
| DNS static/bootstrap regression | 151 assertions PASS |
| Replay comparison/diagnostic simulation | 141 assertions PASS |
| Four synthetic commands | Each actual exit 0; each stderr empty |
| Full native harness | **1824 assertions PASS**; canonical fresh/upgrade and independent process/barrier concurrency |
| Launcher / test / supervisor | Exit 0 / exit 0 / success; failure class none, interruption NONE |
| Cleanup / publication / finalized launcher manifest | PASS / PASS / all three entries verified |

The synthetic counts are **not added** to 1824. No tests, lint or launcher checks were
rerun by this documentation task. Earlier Windows suite counts in the implementation
record remain historical local results.

| Migration-file executions in this native run | Attempted | Completed |
| --- | ---: | ---: |
| Separate canonical 015 rename fixture | 1 | 1 |
| Fresh canonical 001–025 | 25 | 25 |
| Upgrade canonical 001–024, preexisting-data snapshot, then 025 | 25 | 25 |
| Separate 019 table-absent and 020 deficient-table repair fixtures | 2 | 2 |
| **Disposable total** | **53** | **53** |

There was no partial migration. The 53 ordered completion lines exactly match the
captured reconciliation inventory. Canonical 025 applied twice. The two repair
fixtures remain distinct from canonical fresh/upgrade evidence. Working-staging and
production migration executions and deployment/migration-wrapper calls were **zero**.

## Corrected historical replay

All twelve named conditions are true: `worker_shape`, `worker_fields`, `response_shape`,
`response_fields`, `reply_content`, `existing_true`, `replayed_true`, `job_record`,
`release_record`, `prepared_zero`, `verified_zero`, `snapshot_unchanged`.
The complete original value-free diagnostic in the hash-verified test output is:

```text
M6B_REPLAY_DIAGNOSTIC {"case":"historical_success_obsolete_policy","conditions":{"worker_shape":true,"worker_fields":true,"response_shape":true,"response_fields":true,"reply_content":true,"existing_true":true,"replayed_true":true,"job_record":true,"release_record":true,"prepared_zero":true,"verified_zero":true,"snapshot_unchanged":true},"failed_conditions":[],"differences":[{"path":"worker","category":"order-only"},{"path":"worker.job","category":"order-only"},{"path":"worker.job.release","category":"order-only"}],"differences_truncated":false}
```

There is no failed condition or truncated diagnostic. Differences occur only in
associative field order at the three listed paths; strict values/types, complete field
sets, ordered lists, flags, zero preparation/verification counters and the unchanged
database snapshot all passed independently. The before-snapshot follows intentional
obsolete-policy/revocation setup, and the after-snapshot is captured once. The case
still uses an immutable successful release, revoked approval, obsolete policy, current
authorized reader and an independent observed worker. Historical replay grants no
current deployability or real-artifact health guarantee.

## Native coverage and retained limitations

The final PASS and reviewed sequential harness control flow support completed schema,
actor-deletion/FK/CHECK/unique rejection, concurrent request/claim, builder retirement,
historical replay and policy-retirement contention cases. DNS metadata, full-length
identity/normalization and separate repair checks completed. After the corrected replay,
the harness completed policy audit rollback/retry, exhausted safe-queue retirement,
all **ten** revocation/claim combinations (five modes × both lock orders), both
result/audit rollback faults and independent recovery accounting at execution budget
three. **No later case in this native harness was unreached.** Individual assertions
were not all printed; this coverage is reconciled with source flow and the final PASS.
The three bounded `PDOException` notices match the three intentional constraint-fault
rollback cases, followed by successful assertions and final completion.

This persistence gate does **not** establish all **77 planned M6 acceptance cases**.
The [existing acceptance mapping](sprint-8.8-m6b-local-implementation.md#executed-local-checks-and-77-case-mapping)
and [reviewed plan](sprint-8.8-m6-implementation-plan.md) remain intact. Synthetic
artifact receipts are not M6C artifact-byte/determinism/privacy/rendering/SVG/path/form/
browser-security or real-filesystem publication/kill-point evidence. Real heartbeat/
completion expiry races and query-plan/EXPLAIN evidence are not supplied here.
D01–D26, R01–R06, later target/restore/helper/HTTP/Apache/control-plane work, U03 browser/
accessibility, and U01–U02 deployment/HTTP/CSRF portions remain deferred. A normal native
PASS does not prove injection of every launcher signal, unit or publication failure.

## Cleanup and working-staging boundaries

The report and captured cleanup record agree: both owned disposable databases were
dropped; the owned container, live test processes and M6B cgroups were absent; scratch
and private client files were removed. Three preexisting failed-unit metadata records
remained without live processes/cgroups; no reset-failed was performed. Docker was
restored to inactive/dead/disabled, MainPID 0, empty ControlGroup. The current task
only read these captures and issued no Docker or service command.

The operator recorded deployed `/var/www/ubo-repo` clean at the unchanged staging SHA,
no deployed 025, Apache active and unchanged volume UUID
`7d255792-0283-4773-b7a1-20596ed0d8fa`. Retrieved head/status/Apache captures agree.
The current task did not inspect either server checkout or Git metadata directly.
APP_ENV remains independently unconfirmed; no application environment file was sourced.
No working-staging or production database was accessed, migrated or reconciled, and
no deployment/migration wrapper was called. Production access remains unauthorized.

## Tested-code and migration provenance

The documentation successor containing this record is a **different PR head** from
the natively tested SHA. Its full commit ID and exact-head review request are recorded
on PR #126 after the commit exists; the native run is attributed only to `bef67310adafa041a45ee204ed0fc99ca13b754f`.
All **339 tracked non-documentation paths**, including all application code, tests,
25 migrations, both launchers, guards/helpers/fixtures, configuration, dependencies
and infrastructure, retain the same Git blob identities and modes. Their captured
Windows raw-file hashes are also unchanged, separately accounting for checkout line
endings. The delta is limited to this new document and six current-status documents.
No MySQL rerun is needed or authorized for this documentation-only successor.

| Preserved migration | Git blob | Canonical SHA-256 |
| --- | --- | --- |
| 017 | `a8345bed9669e2e43b29a3c40de593cbce1f44de` | `eab87000d3796c8b6d3fb6c8c4a85c1d6a56253620f6d465d7343b78aaa6392f` |
| 019 | `de2d1ce8c3481ae034383be4b138a446650d5d51` | `b420624b28c4f5c6eba129f1ac47bba3e871286156d54fd52793d98aacc9108b` |
| 025 | Unchanged from tested SHA | `dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0` |

The exact 017/019 historical-source exception already reviewed for disposable bootstrap
remains unchanged. The other 22 historical migrations match the original implementation
baseline; 020/023/024 are unchanged. This is not permission to rewrite deployed applied
history, synchronize deployed 017/019 or reapply historical migrations. Any later
working-staging deployment requires separate authority and applied-history planning.

## Individual review reconciliation

All seven original findings, their correction replies, current tested source and
regression evidence were inspected. At initial inspection GitHub returned exactly seven
conversations, two comments each, with no later rebuttal or new substantive finding after the tested-head
clean review. The implementation/evidence basis for each disposition follows; neither
an outdated flag nor the aggregate assertion count supplies that basis.

| Original conversation | Current correction at the tested SHA | Evidence and disposition |
| --- | --- | --- |
| [4054753149](https://github.com/fvd8383/ultimate-back-office/pull/126#discussion_r4054753149) — builder retirement | [claimBuild](https://github.com/fvd8383/ultimate-back-office/blob/bef67310adafa041a45ee204ed0fc99ca13b754f/private/classes/SiteBuildService.php#L167), `inputFailure` at line 816: locked terminal incompatibility, cleared scheduling, bounded scan continues | Prior fake behavior regressions plus completed native 25-job/two-claimer contention, observed InnoDB wait, one compatible lease, exactly-once retirement and preserved counters. **Resolved**; [final disposition](https://github.com/fvd8383/ultimate-back-office/pull/126#discussion_r4213502402). |
| [4054753155](https://github.com/fvd8383/ultimate-back-office/pull/126#discussion_r4054753155) — successful history | [requestBuild](https://github.com/fvd8383/ultimate-back-office/blob/bef67310adafa041a45ee204ed0fc99ca13b754f/private/classes/SiteBuildService.php#L18) checks authorized exact history before new eligibility; `successfulRequest` at line 98 verifies stored identity/release | Prior behavior/replay comparisons plus actual concurrent revoked/new-revision/deleted-requester history and all twelve corrected native no-effects conditions. **Resolved**; [final disposition](https://github.com/fvd8383/ultimate-back-office/pull/126#discussion_r4213502695). |
| [4054877005](https://github.com/fvd8383/ultimate-back-office/pull/126#discussion_r4054877005) — persisted policy | [policyFailure](https://github.com/fvd8383/ultimate-back-office/blob/bef67310adafa041a45ee204ed0fc99ca13b754f/private/classes/SiteBuildContract.php#L83) and locked claim retire unsupported policy before allocation | Prior contract/behavior regressions plus completed native bounded policy contention, no new attempts/effects, audit rollback/retry and exhausted-queue accounting. **Resolved**; [final disposition](https://github.com/fvd8383/ultimate-back-office/pull/126#discussion_r4213503012). |
| [4055419657](https://github.com/fvd8383/ultimate-back-office/pull/126#discussion_r4055419657) — unloaded units | [m6_stop_unit](https://github.com/fvd8383/ultimate-back-office/blob/bef67310adafa041a45ee204ed0fc99ca13b754f/tests/RunM6BMySql.sh#L297) captures ownership, accepts explicit absence after stop, checks residual cgroup tree | Prior simulated unload/removed/foreign/replaced/inaccessible/populated/stop-failure cases; actual run establishes normal cleanup and absent live processes/cgroups, not every injected stop failure. **Resolved**; [final disposition](https://github.com/fvd8383/ultimate-back-office/pull/126#discussion_r4213503305). |
| [4055419662](https://github.com/fvd8383/ultimate-back-office/pull/126#discussion_r4055419662) — Docker config | [m6_docker](https://github.com/fvd8383/ultimate-back-office/blob/bef67310adafa041a45ee204ed0fc99ca13b754f/tests/RunM6BMySql.sh#L10), private config lifecycle at line 52, guard environment check and PHP reinspection | Prior proxy-sentinel/config/environment rejection fixtures; actual container passed pinned environment/identity checks and private-client cleanup. Native run did not inject credential-bearing proxies. **Resolved**; [final disposition](https://github.com/fvd8383/ultimate-back-office/pull/126#discussion_r4213503636). |
| [4058088240](https://github.com/fvd8383/ultimate-back-office/pull/126#discussion_r4058088240) — deterministic interruption | [record-only traps/collection](https://github.com/fvd8383/ultimate-back-office/blob/bef67310adafa041a45ee204ed0fc99ca13b754f/tests/RunM6BMySql.sh#L18), checkpoints, bounded reaping and single cleanup preserve first signal | Controlled historical TERM/INT supervisor/wait/cleanup regression matrix supplies interruption evidence. This native run records interruption NONE and does not replace signal tests. **Resolved**; [final disposition](https://github.com/fvd8383/ultimate-back-office/pull/126#discussion_r4213503910). |
| [4058219312](https://github.com/fvd8383/ultimate-back-office/pull/126#discussion_r4058219312) — final evidence boundary | [finalization boundary](https://github.com/fvd8383/ultimate-back-office/blob/bef67310adafa041a45ee204ed0fc99ca13b754f/tests/RunM6BMySql.sh#L401) freezes signal outcome; bounded publication commits manifest last | Historical pre/post-boundary and write/hash/hung-publisher regressions; actual exit/report/manifest consistency PASS, with no native signal/failure injection claimed. **Resolved**; [final disposition](https://github.com/fvd8383/ultimate-back-office/pull/126#discussion_r4213504171). |

The retained launcher regression matrix is in
[WebsitePlatformM6BLinuxLauncherTest.php](../tests/WebsitePlatformM6BLinuxLauncherTest.php)
and its [fake adapters](../tests/support/WebsitePlatformM6BLinuxLauncherFixture.sh).
Its previously recorded 2131 assertions / 161 fake Bash scenarios were not rerun here.
Native coverage is in [WebsitePlatformM6BMySql.php](../tests/WebsitePlatformM6BMySql.php).
All seven final replies were posted once and each resolution mutation was confirmed. A fresh complete thread snapshot reports **7 resolved / 0 unresolved**, with no additional conversation. No review submission was dismissed and no rule was changed.

## Historical evidence and remaining gates

The f729ecf report remains historically **FAILED**, SHA-256
`806382a34eeb61b54bf42262188417f6506f9131f347e46eda71b608e61447f8`.
Its compound replay operands were not individually retained. This later successful
run proves its own order-only differences and no-effects conditions; it does not
retrospectively establish that ordering was the old run's only mismatch. Earlier
FAILED/BLOCKED/INCOMPLETE reports and original hashes remain in the
[implementation history](sprint-8.8-m6b-local-implementation.md) and
[operating guide](sprint-8.8-m6b-linux-validation.md). The successful run supersedes
their blocking effect for this isolated persistence gate only.

Merge preparation still requires the exact documentation-head evidence review and
assessment of the actual applicable GitHub review/check/rule state, followed by an
authorized merge decision. The tested-head clean automated comment is not a formal
approval, and `MERGEABLE`/`CLEAN` is not proof that branch rules are satisfied. This
task leaves PR #126 open, non-draft, unmerged and auto-merge disabled.

Later deployment/migration planning, working-staging validation and handoff/formal M6B
closure require separate authorization; they are not falsely reported as completed
or as reasons to repeat this unchanged isolated gate. M6C–M6G and production remain
outside this task. Documentation links/anchors/fences, current status, the exact
seven-document allowlist, raw-file/blob preservation and working/staged/committed
diffs are the validations for this task. There are **zero new tests, containers,
SQL/migrations, service operations, deployed-checkout changes or production actions**.

Documentation verification passed for all seven documents: **124 relative links,
10 referenced anchors and 12 balanced fence pairs**, current-status consistency,
retained historical report hashes, exact changed-file inventory and Git diff checks.
All 339 non-documentation blob/mode identities and raw-file hashes matched; all 25
migrations matched the tested SHA, including the canonical 017/019/025 hashes above.
Staged/committed preservation and clean-tree results are recorded with the final commit
and PR review request, independently of the earlier native execution.
