# Sprint 8.8 M6D — Source-specific native v3 acceptance

2026-11-01: **NATIVE V3 PASS AT 7cdbbeef / SCOPE RECONCILED LOCALLY / FINAL REVIEW AND MERGE PENDING**.
Formal M6D closure is pending;
M6C remains formally closed, M6 remains in progress, and M6E–M6G remain not started.

## Tested source and original evidence

The complete run tested `7cdbbeef639d670208d7bdd9318bc7097649a043`, profile
`m6d-db-authority-v3`, after its [clean automated review](https://github.com/fvd8383/ultimate-back-office/pull/130#issuecomment-6562181436).
That receipt is not a formal GitHub APPROVED review. The subsequent scope-policy and
documentation commit is **not itself natively executed**. Its runtime equivalence is
checked separately; no native rerun is attributed to this local task.

Original `report.json` SHA-256, independently supplied and verified before use:

```text
b95d3bb44616b445ba365ab7c037772febb9f443dd8d7b8a546e0dfb11297e63
```

Private original evidence on `ubo-stage-app`:

```text
/mnt/ubo_stage_testdata/codex-validation/evidence/m6d-v3-native-20261101T185154Z-89736d55aa5f
```

Existing local originals used in this task:

```text
C:/Users/fvd83/.codex/visualizations/2026/11/01/01a19870-c748-7243-b4ac-19f571433e6d/m6d-transaction-native/fresh/
```

This task read the actual `SHA256SUMS` and verified these **25 selected original
payloads**, without repeating the 1,098-payload audit:

```text
report.json
M6D-V3-ACCEPTANCE-ASSESSMENT.txt
M6D-V3-NATIVE-RESULT-INVENTORY.json
M6D-V3-SOURCE-AND-IDENTITIES.txt
M6D-V3-RESOURCE-AND-CLEANUP.txt
native-contract-assessment.json
operator-native-result.json
native-coordinator.stdout
native-coordinator.stderr
admission-receipt.json
admission-pass.json
schema-preparation-pass.json
native-database-connection-accounting.json
actual-operation-counters.json
owned-cleanup.json
home-cleanup.json
resource-floor-observations.json
transaction-suite-1-assessment.json
transaction-suite-1.stdout
transaction-suite-1.stderr
transaction-suite-2-assessment.json
transaction-suite-2.stdout
transaction-suite-2.stderr
M6D-V3-TRANSACTION-REGRESSION-AND-LINT.txt
SUMMARY.txt
```

The observed manifest hash is
`6c221ea55a5a67ac3215e8cce95a9e5e5798774c11ed3c1252d6bde32cd21f4b`;
it is **not an independently supplied trust anchor**. Original report bytes were
accepted again offline by the native-tested, definitions-only
`M6DNativeContract::acceptPublished`, using the retained nonsecret admission identity.
No application configuration, database connection or native coordinator was invoked.
Exact selected hashes, offline output, local commands/exits/durations and the complete
handoff are outside Git in the sibling `m6d-scope-acceptance/` directory.

## Complete native outcome and identities

Coordinator **exit 0, no signal, 238.112168099 seconds**. All **29 cases / 146 subcases /
22 ordering-progress observations / five transport legs / five fresh authoritative
observations PASS**, with no primary failure, failed or unreached subcase.
All **18 deployment-schema probes PASS within the 146 subcases**, not additional cases.
The case totals sum to **790**, equal to the sum of all subcase assertions; no separate
setup assertion split is recorded or invented. This run completed within the unchanged
600-second limit; its duration is not a general performance guarantee.

| Recorded identity | Value |
| --- | --- |
| Host / operator / worker | `ubo-stage-app`; `codex-validation` UID1000; isolated PHP UID/GID2000 |
| Runtime image | `sha256:88208948cc5dbeff44ddd9f0094d922f9e15350cf38c1004884f6f7e99f5c85e` |
| MySQL image | `sha256:7c07d11b694dcc7e2ef3de845075eca1a8b39868f85f519f938b54c432edc1cb` |
| Run token | `89736d55aa5f6cd03fa5523c020f49c0` |
| Runtime / DB hostname | `m6d-run-89736d55aa5f6cd0` / `m6d-db-89736d55aa5f6cd0` |
| Disposable database / account | `ubo_m6d_89736d55aa5f6cd03fa5523c020f49c0` / `m6d_89736d55aa5f6cd0` |
| MySQL version / server UUID | `8.4.11` / `5a2bbd93-d625-11f1-a1ae-c627e6cb3c7e` |
| Test-volume UUID | `7d255792-0283-4773-b7a1-20596ed0d8fa` |

The retained operator records establish a root-owned read-only source export with
real Git metadata, zero PHP capabilities/NoNewPrivs, one CPU, 512 MiB/no extra swap,
32 PIDs and private 128 MiB state tmpfs. MySQL used one CPU, 1536 MiB/no extra swap,
128 PIDs and one new data volume on the shared owned internal bridge, without published
ports or operational mounts. The cached runtime's historical M6C source label stayed
unchanged; application code came from the verified export.

Preparation applied the unchanged 25 migrations once to the disposable schema, verified
empty fixture tables, documented seeds and exact restricted grants. Genuine admission
passed once, opening zero database connections. Recorded connections are **31 preparation,
54 native, one subsequent cleanup observation**; the operator counter of 32 includes
that last observation. No exact SQL-statement total is claimed.

Strict report acceptance, capture/publication, child cleanup, schema cleanup and namespace
cleanup are all **PASS**. Saved operator records report removal of task containers,
network, volume, credentials, marker and scratch, and restoration of Docker to
inactive/disabled. They report Apache active, clean source/deployed checkouts, preserved
images/client configuration/migration pins/volume identity and sufficient resource floors.
These are saved observations, **not new live host checks by this task**. Deployed source
remained `f466fc1cdbfb53325a1db11445736bdc5ea7c6f4`.

## Actual connection-loss and recovery observations

All four faulted legs retain `SiteServiceException / database_failure`, return no guessed
success, and record real `HY000 / 2006` transport loss. The unarmed control succeeds.
The five structured relay/worker legs retain binding, barrier sequence, fault, OUTCOME,
worker/relay exit and effect-count records, with five distinct fresh authoritative reads.

- `commit-not-delivered/result`: COMMIT was not forwarded. Connection 85 independently
  observed the result uncommitted: `health_check_pending`, no success event, active-health
  row or committed pointer/lifecycle; prior activation/grant remained. Recovery changed
  attempt 220 to recovery attempt 221, with totals 4/3/1 and one automatic recovery.
  Stale authority was denied; no new candidate or compensation effects occurred, and
  exactly one success event resulted.
- `ack-withheld/result`: COMMIT was forwarded and server OK received but suppressed.
  Connection 87 observed committed success, one success event and two active-health rows.
  Later deployment 256 remained the winner; old success/history remained accessible only
  under current authority, with no duplicate effects or reversal of the winner.
- `authoritative-unavailable`: the result leg lost its acknowledgement; the reread query
  was intercepted without forwarding. Connections 89 and 91 observed the same committed
  state hash. The failed service read returned no success, guessed no outcome, and left
  database and publisher-model state unchanged before explicit subsequent resolution.

The unchanged strict assertions establish no automatic uncertain-COMMIT replay, guessed
compensation or duplicate success/publisher effects in these cases. The precise internal
transaction-cleanup call/phase is **unobserved**; the worker exports no new transaction
metadata. Five expected coordinator PDO negative-diagnostic lines remain in retained stderr.

## Existing requirement allocation and evidence

The [original 77 definitions](sprint-8.8-m6-implementation-plan.md#24-implementation-test-matrix)
remain unchanged. This maps the existing M6D allocations and case names; it does not
replace the plan or claim all 77 requirements complete.

| Existing requirement allocation | Actual evidence and remaining boundary |
| --- | --- |
| D19–D26, U01/U02 service/history access | `publish-request-history`, `restore-request-history`, both payload-fields and request-outcomes families, `scoped-current-access`, `concurrent-request-identity`; current authorization, immutable identity, cancellations and bounded replay. HTTP/CSRF/browser obligations remain future gates. |
| D02–D06, U01/U02 authority | `production-grant-boundaries`, `production-role-lock`, `authority-boundary-orders`, `grant-field-bindings`, `grant-phase-denials`, `concurrent-target-claims`, `independent-targets`; 22 required real ordering/progress observations. External helper concurrency is not established. |
| D11/D12, C09 | `result-audit-rollback`, `committed-ack-history`, `unavailable-result-no-compensation`, `result-commit-transport`; actual transactions and wire faults. Injected post-commit API loss is separately identified from wire loss. |
| D13/D15, C04/C06/C08/C11/C12 | `stale-lease-fence`, `staging-production-lifecycle`, `third-execution-recovery`, `recovery-budget-operator`, plus transport recovery/history; real DB ownership/counters and simulated external effects. |
| R01–R06, D09/D10/D17, C01/C02/C05/C10/C13 | `known-good-restore`, `restore-eligibility`, `health-compensation` and recovery cases; real service/DB consequences, modeled artifact/health/publisher/external pointer. Actual Apache/helper/HTTP/filesystem proof remains M6E/M6G. |
| S02/S03 and U01/U02 actor-history consequences | `deployment-schema` (18 named probes), `deleted-recovery-actor`, `deployment-history-access`, plus claim/recovery cases; exact constraints, retained history/current authorization, bounded indexed access. S01 clean/upgrade evidence stays attributed to M6B. |
| D14/D16 | Historical local transaction-boundary and lifecycle regressions described in the [implementation record](sprint-8.8-m6d-local-implementation.md); no new native case or local rerun is invented for them. |

Publisher, artifact, health and external-pointer behavior remain modeled even where
database consequences are native. D01's actual activation, D07/D08/D18 external behavior,
and all allocated served-policy/browser portions remain unexecuted. The approved
[B14/B16 allocation](sprint-8.8-m6c-closeout.md#approved-b14b16-allocation) remains mandatory:
M6E served-policy/header checks, M6G integrated browser navigation/CSP/inert controls.
No site activation, Apache/browser acceptance, power-loss durability, production readiness
or deployment authorization follows from this database-only gate.

## Shared correction, historical failures and scope reconciliation

The reviewed correction changed exactly `SiteServiceSupport.php` and `SiteBuildStore.php`
in production: protected primary-error conversion/cleanup and explicit retry termination
safety. It forbids uncertain-COMMIT replay and retry/winner reread after unsafe cleanup.
Both files and all native behavior remain unchanged by this scope/documentation task.

Evidence layers remain separate: **790 native assertions**; Linux TransactionTest
**773** (exit0, 0.153944379 s), M6DTransactionTest **97** (exit0, 0.394837884 s), five
Linux PHP lints; historical desktop **34 passing suites / 5,717 assertions** plus the
separate historical scope failure. The two transaction suites are controlled PDO/service
fixtures, not independent native connection-loss evidence, and were not rerun here.

Historical results are not overwritten or combined into this PASS:

- `5f32b1b5ed1380da4f33e5938f03e6056df26c2c`: earlier native FAILED; the test observer's
  unsupported table access required the separately reviewed fixture correction. The
  historical record retains 18 cases, 31 subcases and three ordering observations PASS,
  58.241917827 seconds, and report hash
  `1fcc967957bc60e0e6202c0a7771a59c09eb6f1eff8a7596e3e63cba1ba18cf6`.
- `e222dbb9dd500c8c738b439fe7ca844c4691017c`: native FAILED, 26/1/2 cases and 121/1/24
  subcases (PASS/FAIL/UNREACHED), 610 assertions, `exact_constraint_fk_sd_target`.
  The historical driver tuple is unavailable; modeled `1451/fk_sdt_active` remains a
  hypothesis, never recovered native evidence.
- `48f6d2b3fd3d30d2669046f63e94aae3616e26d6`: the retained 17:40 UTC controller attempt
  was BLOCKED before native invocation; completed schema/lint evidence did not make it
  native PASS. Its later 18:04 UTC native attempt FAILED with 28/1 cases, 143/1/2 subcases,
  719 assertions and raw PDOException classification at `commit-not-delivered`.
  Its report hash remains `ad715659d862be644cf1700873f731f82d83a444a82af02ad914c9d594f33fb4`.
- `7cdbbeef`: the historical scope guard separately exited 255 because it froze
  `SiteBuildStore.php` at the original baseline. That failure was reproduced once and
  retained locally before this deliberate reconciliation; it is not relabeled PASS.

The current [scope policy](../tests/support/WebsitePlatformM6DScopePolicy.php) retains
baseline `1134c65c5f6cdfea804d634fad73e2d8a354d065`. It requires both shared production
files and the two generic paths `tests/WebsitePlatformTransactionTest.php` and
`tests/support/WebsitePlatformTransactionPDO.php` to match immutable approved reference
`7cdbbeef639d670208d7bdd9318bc7097649a043` in content and tracked modes. The M6D completion
test remains unchanged under its existing prefix. No entire protected directory is waived.
Only this acceptance-document path is added to the existing precise documentation list.

HEAD, index and working state are checked separately, including all 77 requirement rows.
Working hashes use Git's declared EOL conversion without rewriting files; Windows tracked
modes are checked in HEAD/index, and representable working execute modes are also checked
on POSIX. The [focused regression](../tests/WebsitePlatformM6DScopePolicyTest.php) uses this
same implementation in an owned temporary local Git clone, with bounded subprocesses and
exact cleanup. It rejects altered pinned files, masked staged/committed edits or modes,
missing/replaced files, migration changes, unrelated production/generic test/support/docs
paths, missing approved-reference data and changed/missing requirement rows.

## Current review and merge handoff

The corrected scope guard passes. Its focused policy regression passed **27 assertions /
21 controls**, including exact temporary-fixture cleanup. Three changed/new test PHP
files pass lint. Initial Windows sandbox clone-launch failures and a read-only fixture-object
cleanup failure are preserved outside Git; explicit local cloning and owned-file cleanup
resolved the tooling issues before the passing run. No application or native suite was rerun.
Scope-policy validation and runtime-equivalence receipts are retained outside Git.
The only changes after the native-tested source are the scope test/policy/regression and
the six explicit documentation paths: nine changed paths in total, with all **536 other
pre-existing tracked files** preserved. Application, native harness, transaction regressions,
migrations/pins, requirements, runtime configuration and limits remain identical in Git
content and modes. This is a test/documentation change, not documentation-only.

Final exact-new-head review, merge and formal closeout remain pending. Any substantive
new review finding must be addressed before merge. No automated comment is represented
as formal APPROVED, and absent CI checks are not CI PASS. The final review request and
actual observed PR state are retained in the outside-Git handoff. No deployment,
infrastructure action, native rerun, merge, auto-merge or M6E implementation occurred here.
