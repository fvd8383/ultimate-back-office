# Sprint 8.8 M6E — Isolated Linux/Apache validation

**PARTIALLY IMPLEMENTED / NATIVE NOT EXECUTED.** The Windows implementation task does not authorize
installation, server preparation, Linux/Apache execution, SQL, service operations,
working-staging activation or deployment. This document is the bounded later path,
not a claim that a host contract exists. M6D’s closed native gate is not reopened.

## Failed-observer evidence controls following a996c96

**LOCAL CORRECTION / NATIVE MYSQL UNEXECUTED / NO ADMISSION.** The starting branch,
clean HEAD and OPEN DRAFT PR #131 matched the task; the exact-head P2 comment
`4474721638` described both reproduced losses. Original reproduction records remain
separate from corrected results in the desktop `observer-fix` evidence directory.
The PHP reproduction executed the original extracted drift predicate with completed
modeled observations. The Python reproduction ran the actual modeled bootstrap and
teardown: `notes` expected one row, observed zero; its private after-observation was
deleted and neither table digest remained in retained eligibility diagnostics.
The already protected original snapshot remained available; it did not explain the
actual mismatching observation.

The [corrected contract](sprint-8.8-m6e-local-implementation.md#failed-observer-evidence-correction-following-a996c96)
retains bounded measurements before outward rejection and teardown. PHP tests execute
the production observer orchestration through bounded reader/writer seams, including
real file capture and failed verification of deliberately corrupted real M6C bytes.
Python tests execute the actual preflight, immutable ledger publication and modeled
teardown. They compare publication bytes before and after teardown, not mocked
completed receipts. Both phases cover schema/data/artifact drift, artifact failure,
missing/partial output, malformed/oversized diagnostics, missing measurements,
publication failure, combined capture/cleanup failure, same-byte substitution,
changed request/phase binding and unchanged success.

Principal drift controls require the original mismatch, useful digest/count pairs,
identical retained bytes after removal, no raw fixture/secret contents, no eligibility
verification receipt and BLOCKED admission. Missing observations remain null; later
artifact failures preserve completed database measurements. Capture failures cannot
replace the original mismatch, and teardown does not depend on declaring capture
complete. Uncertain substituted resources remain preserved by existing ownership rules.

Final corrected-source runs on 2026-11-04 (PHP 8.4.24 / Python 3.14.7):

| Complete suite | Result | Suite duration / subprocess wall time |
| --- | --- | --- |
| `WebsitePlatformM6EEligibilityTest.php` | **99 assertions PASS** | 1.275 s wall |
| `WebsitePlatformM6EEligibilityTest.py` | **25 tests PASS**, including 28 before/after failure scenarios | 420.320 s / 422.188 s |
| `WebsitePlatformM6EBootstrapControlTest.py` | **35/35 PASS**, full invocation on corrected source | 398.605 s / 400.427 s |
| `WebsitePlatformM6ELedgerRecoveryTest.py` | **14 tests PASS** | 52.393 s / 54.640 s |

M6E source scope passed **350 assertions**. Two PHP lints, three Python AST parses,
both documents' local links/fences, exact seven-file scope, whitespace and Git
integrity checks passed. Tested source hashes are compared with staged and committed
bytes after Git's line-ending normalization. Full suite output, command/duration/digest
records and the original reproduction records are retained outside Git in
`observer-fix`; the earlier three-test focused pass (70.300 s) is not counted as a
complete suite. No unrelated native or application campaign was rerun.

Native MySQL, native Linux file/process ownership and installed service execution
remain unperformed. These results establish local producer/coordinator ordering and
failure semantics only. All six commissioning MISSING classifications remain unchanged.
No deployment, publisher activation, live settlement, staging/production access or
PR merge is part of this correction.

## Eligibility preflight controls following 2e1f19a

**AUTHORED LOCALLY / MODELED ELIGIBILITY EXECUTED / NATIVE SQL UNEXECUTED.**
Operational lifecycle admission is **NOT AUTHORIZED**. Deployment request/claim
and publisher activation are **NOT EXECUTED**. The
[implementation and privilege decision](sprint-8.8-m6e-local-implementation.md#independent-eligibility-preflight-following-2e1f19a)
identify the exact unchanged service call, destination-only non-DML SQL privileges,
READ WRITE transaction with ROLLBACK, protected evidence and independent observers.
Starting Git/PR/main/review prerequisites were verified before edits.

The new PHP controls call the real unchanged source-eligibility owner through an
explicit behavioral PDO adapter. A separate copy of the faithful pre-B service
model supplies original data; the original M6C builder/store supplies real sealed
artifact bytes. They verify A eligibility, original source/approval/business/build/
producer/release identities, retained locking SQL, table SELECT scope, READ WRITE
access and confirmed ROLLBACK without mutations. Negatives cover a SELECT-only or
READ ONLY session, broad/wrong grants, wrong schema/server/account, an already-open
unowned transaction, changed source hash, missing/revoked approvals, foreign business,
missing producer/release, newer material B, deployment/target rows, expired execution,
lost rollback acknowledgement, SQL/rollback failures and corrupt original files.
The original completed A+B fixture still rejects A. No descriptor replaces the
actual gate invocation. PDO, MySQL grants/locks and transaction outcomes are modeled.

The Python controls independently compare complete observer data and validate
transaction, process, account-seal, original-input and publication bindings. They
reject open/unknown/committed outcomes, reused/stale invocation evidence, wrong
owner/session identities, cleanup ambiguity, added deployment/fence/B rows, source
drift, changed artifact hashes, false native labels and authority promotion. Real
temporary files exercise protected publication, same-byte replacement, retained
history and cleanup; Linux UID/GID, SQL and services are modeled. The single-use
orchestration controls reject unknown child completion even when its private output
claims successful rollback, and reject independent after-state drift despite gate
success. They retain first failure and secondary teardown errors.

Executed locally on 2026-11-04 with PHP 8.4.24 and Python 3.14.7:

| Control | Result |
| --- | --- |
| New `WebsitePlatformM6EEligibilityTest.php` | **41 assertions PASS**; actual unchanged gate, modeled SQL, real sealed bytes |
| New `WebsitePlatformM6EEligibilityTest.py` | **18 tests PASS**; 11 integrity and 7 ownership/orchestration controls |
| `WebsitePlatformM6EBootstrapControlTest.py`, complete invocation without a test selector | **35/35 PASS**, 355.981 seconds; includes the previous writer-fault correction |
| Restoration PHP / Python | **42 assertions / 16 tests PASS** |
| Snapshot PHP / Python | **43 assertions / 23 tests PASS** |
| Checkpoint PHP / Python | **46 assertions / 13 tests PASS** |
| Bootstrap fixture PHP | **37 assertions PASS** |
| M6D authority / transaction / execution / request / recovery | **96 / 97 / 63 / 70 / 60 assertions PASS** |
| M6E authority | **18 assertions PASS** |
| Closed M6D scope-policy controls | **27 assertions / 21 controls PASS** |
| Current M6E source scope, working diff | **340 assertions PASS** |
| Selected existing commission integration controls | **5 tests PASS**; controlled evidence, blocked native promotion/override, postinspection and full controlled attempt |
| Selected existing component integration controls | **2 tests PASS**; admitted dispatch/supervisor and distinct commissioning admission |
| Syntax and repository integrity | **PASS**: 3 PHP lints, 6 Python AST parses, document links/fences, exact 11-file scope, `git diff --check` and `git fsck --no-reflogs` |

The 35-test result above is the **complete bootstrap rerun**, not the earlier
single-control rerun and not the selected commission/component checks. The last
PHP eligibility run includes the native process-inventory fail-closed guard;
native process enumeration itself is not executed by the local models.

Batched regression output and command/digest records are retained outside Git under
`m6e-eligibility-validation` in the desktop visualization workspace. PHP uses
`php -d memory_limit=256M tests/<File>`; Python uses the existing Python 3.14.7
with `-I -B tests/<File>`. Windows temporary-file hard-link controls require the
same local sandbox exception used by previous ownership runs. This grants no
native fixture, service, staging, production or network-database execution.

Native MySQL 8.4 eligibility remains **UNEXECUTED**. Later isolated validation must
prove the full prior capture/restoration contract, actual 24-table SELECT and exact-
schema LOCK TABLES privileges, DML/global/foreign-schema denials, READ WRITE locking
semantics, unchanged gate behavior, real ROLLBACK/error outcomes, writer/account
quiescence, independent sessions, sealed artifacts, before/after data equality,
process/file identity, durability and teardown under interruption. There is no native
admission override: the authored evidence stays `native_admission=BLOCKED` and
`admission=UNIMPLEMENTED`, even if an individual preflight later succeeds.

SQL-account sealing and confirmed rollback/child reaping are recorded before the
later whole-fixture teardown. Uncertain SQL or cleanup outcomes remain INCOMPLETE;
historical evidence cannot be replayed as live readiness after teardown or expiry.
No real A failure, operational lease/fence, target reservation, journal settlement,
host receipt, publisher activation, deployment request/claim or PR merge occurred.
All six commissioning MISSING classifications and historical M6C/M6D acceptance
evidence remain unchanged.

## Isolated restoration controls following ccaa23b

**LOCAL MODELS ONLY / NATIVE MYSQL RESTORATION UNEXECUTED / NO LIFECYCLE ADMISSION.**
The architecture and ownership contract are described in the
[local implementation record](sprint-8.8-m6e-local-implementation.md#isolated-pre-b-restoration-following-ccaa23b).
The expected branch/clean HEAD, OPEN DRAFT PR #131, resolved review threads and
unchanged main were checked against local Git and live GitHub before edits.

The local SQL adapter exercises the actual restoration algorithm against explicit
PDO/catalog/transaction models. It covers seed deletion before original PK import,
90-table inventory, schema mismatch, exact binary/text/decimal/JSON/temporal values,
generated-column exclusion/recomputation, original AUTO_INCREMENT, composite FK
cycles, duplicate keys, wrong schema/server/account/grants, corrupt bindings,
interrupted DDL/DML, rollback failure and uncertain COMMIT. The actual M6C builder/
store supplies original sealed bytes; corrupting a real sealed file is rejected.
Canonical JSON round-trip inputs exercise the persisted PHP/Python boundary.
Neither the synthetic column metadata nor fake transaction behavior proves MySQL.

Python independently checks full observer data against the protected snapshot and
checkpoint. Negatives cover missing/extra/altered tables and rows, changed values/
PKs, approvals/producers/releases, B or deployment rows, corrupt inputs/artifact
hashes, account/binding/expiry changes, worker-success substitution and attempted
authority promotion. Real temporary files exercise ownership, no-replace publication,
same-byte inode replacement, preserved failure evidence and cleanup. Linux UID/GID,
services, SQL catalog/privileges and all database observations remain modeled.
Orchestration negatives include successful writer output followed by partial
observer data, original-database mutation, unknown child completion and cleanup
failure with the first failure/watchdog retained.

Executed local validation results follow. PHP commands use
`php -d memory_limit=256M tests/<File>`. Python uses the existing GIMP Python 3.14.7
with `-I -B tests/<File>`. Batched test stdout/stderr, command arrays, elapsed times and
digests are retained outside Git in the desktop visualization workspace under
`m6e-restoration-validation`; they contain no native snapshot rows or credentials.

| Selected local validation | Result |
| --- | --- |
| Restoration PHP SQL/identity/transaction model; real sealed artifact bytes | 42 assertions PASS |
| Restoration Python integrity/ownership/orchestration | 16 tests PASS, including final live-state guards |
| PHP snapshot / checkpoint / original bootstrap artifact fixture | 43 / 46 / 37 assertions PASS |
| Python snapshot / checkpoint regressions | 23 / 13 tests PASS |
| Bootstrap controls | 34 of 35 passed initially; corrected account-removal control passed its focused rerun |
| Ledger recovery / selected commissioning authority controls | 14 / 5 tests PASS |
| M6D authority / transaction / execution | 96 / 97 / 63 assertions PASS |
| M6D request / recovery; M6E authority | 70 / 60 / 18 assertions PASS |
| Unchanged M6D scope policy in a private local Git clone | 27 assertions / 21 controls PASS |
| Current M6E scope | PASS; exact baseline, working/index/HEAD, 25 migrations and 77 definitions preserved |
| Changed PHP lint / Python AST syntax | 4 / 8 files PASS |
| Documentation links/fences, Git diff and object integrity | PASS; no Git corruption or whitespace errors |

No local SQL model result is native acceptance. M6D's expected redacted PDO failure
messages are negative-control diagnostics. No native restoration, deployment,
host receipt or operational commissioning result was produced.

Development observations: the first sandboxed bootstrap control could not create
its temporary hard link (Windows sandbox access denial), so local controls were
rerun outside that sandbox. An initial PHP model lacked the PDOStatement
`fetchColumn` method; the model was corrected. The first 16-test Python run had
15 successes and one post-teardown missing-file error; explicit live-state denial
now rejects before reading removed paths. Review also caught PHP associative-array
key-order assumptions at the canonical input boundary; canonical comparisons and
the persisted-input control correct them. The broader bootstrap run passed 34 of
35 controls; its account-removal fault assumed the writer was the last-created
account. The fault now targets the writer explicitly and its focused rerun passes.
These failures are not native results.

Native validation is still **UNEXECUTED**, and is not authorized by this code
increment. It must prove genuine source snapshot consistency, all canonical
migrations and seeds, destination reconstruction, exact full schema/counters/row
values, generated/default/JSON/temporal behavior, actual FK and unique enforcement,
TLS/account/grant separation, writer locking and process closure, source preservation,
Linux file/directory durability and cleanup under interruption. Use only the
separately authorized isolated MySQL 8.4 fixture, never working staging or
production. No new droplet, staging SSH, operational publisher or host receipt is
authorized. Restoration's resource/phase limits can reject a run; no model result
or worker success bypasses those limits or independent verification.

Failure cleanup is whole-owned-fixture teardown, not a generic database DROP or
resume facility. It removes the original disposable fixture as well as the
destination only after recorded processes and inode ownership are checked;
uncertainty/cleanup evidence is retained outside those roots. Unknown outcomes
remain INCOMPLETE even when cleanup succeeds. Failure to stop or identify resources
leaves cleanup incomplete. Native power-loss guarantees remain unproven.

Independent lifecycle admission is still UNIMPLEMENTED. The restored schema has
zero staging targets and no operational leases, deployments, attempts or health
records. The application DSN and host configurations still identify the original
fixture. All six commissioning MISSING classifications and historical M6C/M6D
acceptance evidence remain unchanged.

## Pre-B snapshot controls following 891f26a

**LOCAL CONTROLS ONLY / NATIVE SQL CAPTURE UNEXECUTED / RESTORATION AND LIFECYCLE
ADMISSION UNIMPLEMENTED.** Starting branch/HEAD, clean worktree, OPEN DRAFT PR
#131, completed no-findings exact-head review, resolved review threads and
unchanged GitHub main were verified. The
[snapshot design](sprint-8.8-m6e-local-implementation.md#pre-b-database-snapshot-following-891f26a)
defines the exact 90-table scope, separate reader, global DDL exclusion,
consistent transaction and protected evidence. The original source checkpoint
and its `database_snapshot=UNIMPLEMENTED`/lifecycle fields are preserved.

New PHP controls execute the actual capture orchestration against an explicitly
modeled PDO reader, while the existing local two-release fixture builds and
verifies real original M6C artifact bytes. They check synchronous pre-B ordering,
one read boundary, nonlocking reader SQL, no data mutations, original row and
approval/producer identities, empty/populated tables, missing/duplicate rows,
wrong schema/server/account/version/TLS/grants/session/isolation, schema drift,
row/cell bounds, changed source, uncommitted A, B before completion, expired
capture, deployment/lease denial and commit/rollback/unlock error preservation.
The Python verifier independently consumes that private child output, validates
PK/FK closure and schema/migration binding, and checks binary/Unicode/decimal/
null/empty values, corruption, truncation, malformed inventory, stale/incomplete
records and false native/authority claims. The metadata/SQL model is deliberately
not the live MySQL catalog or a native transaction oracle.

The ownership controls exercise real private files and the existing ledger with
modeled Unix identities/services. They cover identical-byte inode replacement,
missing/corrupt/unverified leaves, interrupted writes, failed outcomes, lost
publication acknowledgement, secondary cleanup failure, redacted diagnostics,
unchanged A+B output and teardown removal of all private row data. Only bounded
verification metadata remains in protected historical evidence. The final
teardown diagnostic records resource cleanup against the same snapshot receipt.

| Selected local validation | Result |
| --- | --- |
| New PHP snapshot controls | 43 assertions PASS |
| New Python snapshot integrity/ownership | 23 tests PASS; 3 affected controls rechecked after final outcome binding |
| Existing PHP checkpoint controls | 46 assertions PASS |
| Existing Python checkpoint controls | 13 tests PASS |
| Existing bootstrap controls | 35 tests PASS; 5 affected controls rechecked after final command diagnostics |
| PHP original bootstrap/artifact fixture | 37 assertions PASS |
| M6D authority / transaction / execution | 96 / 97 / 63 assertions PASS |
| Unchanged M6D scope policy, private Git clone | 27 assertions / 21 controls PASS |
| M6E independent authority | 18 assertions PASS |
| Current M6E scope | 308 working assertions PASS; final staged/committed count recorded in review handoff |
| PHP lint / Python syntax / documentation links | 6 / 6 / 35 checks PASS |
| Git diff / object integrity | PASS; existing dangling objects retained, no corruption or pruning |

Commands: `php -d display_errors=0 -d log_errors=0 tests/WebsitePlatformM6ESnapshotTest.php`,
`python -I -B tests/WebsitePlatformM6ESnapshotTest.py`, matching checkpoint and
bootstrap programs, `WebsitePlatformM6DAuthorityTest.php`,
`WebsitePlatformM6DTransactionTest.php`, `WebsitePlatformM6DExecutionTest.php`,
`WebsitePlatformM6DScopePolicyTest.php`, `WebsitePlatformM6EAuthorityTest.php` and
`WebsitePlatformM6EScopeTest.php`. Python is the installed 3.14.7 GIMP runtime;
PHP is 8.4.24. No broad unrelated M6E campaign was repeated.

Development failures were retained as failures: Windows sandbox denial of the
existing hard-link publication primitive required rerunning those local tests
outside the sandbox; no filesystem model was weakened. The first new model
duplicated assets selected for several composition usages; it was corrected to
represent unique table rows, and the integrity tests then passed. The existing
missing-checkpoint control also exposed a model exception-type mismatch; the
model now explicitly rejects that missing input before attempting snapshot
construction. None of these runs is native SQL evidence.

A separately authorized disposable Linux/MySQL 8.4 environment must still prove:

1. Exact instance/schema/account/TLS/grant admission and source/migration identity.
2. Real `REPEATABLE READ` consistent-snapshot behavior, including independent
   concurrent-writer controls, and that the retained global read lock excludes
   DDL and survives transaction start without an implicit unlock.
3. Complete actual catalog/row capture for all 90 InnoDB tables, original PK/FK,
   generated and approval/source/producer values, and exact logical-value encoding.
4. Independent captured-data integrity and schema-definition verification;
   real syscall no-follow/inode/durability behavior; bounded interruption and
   cleanup with the first failure and all secondary errors retained.
5. Unchanged original A+B fixture behavior and no effect on the working staging
   application. The global read lock is permitted only on this new isolated
   fixture instance, never the working staging database.

No such environment was accessed or provisioned by this task. No native SQL,
Linux bootstrap, schema migration, SSH/droplet operation, operational publisher,
deployment, lifecycle schema, restoration or host acceptance receipt was run.
The next prerequisite is a separate reviewed restoration/admission contract that
checks destination schema and all restored identities/data/artifact bytes, then
reruns unchanged source eligibility before any genuine M6D request/claim.
All six MISSING classifications remain: `retained-release-static-responses`,
`two-release-transfer-retention-conflict`, `supervised-helper-lock-fence`,
`candidate-active-fault-restoration`, `retained-state-interruption-authority-recovery`
and `owned-uncertain-reload-reconciliation`. Historical M6C/M6D acceptance is unchanged.

## Pre-B checkpoint local controls following 8b38ace

**LOCAL MODELS AND REAL ARTIFACT BYTES ONLY. NATIVE CAPTURE UNEXECUTED;
CONSISTENT DATABASE SNAPSHOT, RESTORE AND LIFECYCLE ADMISSION UNIMPLEMENTED.**
Starting HEAD was `8b38aceedff338a108aedfed544f81b78c7c2e50`; the worktree was
clean, PR #131 OPEN DRAFT, and remote main matched
`303b073658d7eb08e6086237a70dd4d285dc121c`. This is new evidence, not a revision
of any historical M6C/M6D/M6E closeout.

The [implementation record](sprint-8.8-m6e-local-implementation.md#pre-b-source-checkpoint-following-8b38ace)
defines the fixed boundary after A's completed build and before native B revision
creation. Native setup now authors a separate-session committed read, exact
schema/server/account/TLS checks, two unchanged source-eligibility observations
around real immutable artifact verification, exclusive private descriptor capture,
bootstrap ownership/publication binding and final-fixture A denial. These native
paths have **not** run here. No SQL instance, Linux UID/service boundary, restored
database, Apache listener or operational publisher is implied by desktop PASS.

Local coverage establishes the following:

- Original A passes unchanged source eligibility before B. The original local
  completed A+B fixture rejects a new A request because its source hash changed;
  a distinct newer material B revision model also rejects through the same gate
  and actual `SiteDeploymentService` request owner. Both denials create no rows.
- The captured A revision/source hash, effective approval lineage, association,
  completed job, execution producer and sealed manifest/files retain their
  original identity. Both original releases and shared/changed/PDF sets remain
  verifiable after capture and B completion.
- Schema/server/account/site/source/approval/association substitutions, duplicate
  approvals/associations, newer revisions, unfinished transactions, nonzero
  deployment state, actual artifact corruption, changed observations, expiry and
  manifest/producer substitution reject. Rewriting every SQL revision reference
  together still fails against the original sealed manifest.
- Checkpoint capture issues SELECTs only and leaves the modeled source/database
  state unchanged. It cannot parse as a deployment request or lease. Native
  include controls establish dependencies without opening SQL/configuration or
  constructing a publisher runtime.
- A descriptor cannot assert snapshot completion, restored admission or publisher
  authority. Protected-file identity, bytes, source provenance, immutable archive,
  bootstrap publication and live ownership are independently checked. Absent,
  stale, malformed, duplicate-key, replaced and incomplete checkpoints fail closed.
- Interrupted worker capture, uncertain archive acknowledgement and failed cleanup
  cannot report completed ready evidence. Successful cleanup retains historical
  archive bytes while invalidating live readiness.

Focused local validation:

| Control | Executed result |
| --- | --- |
| New PHP checkpoint/temporal controls | 46 assertions PASS |
| New Python checkpoint integrity/ownership controls | 13 tests PASS, including a cold native-helper include |
| Original bootstrap controls | 35 tests PASS |
| Original two-release serving controls | 12 tests PASS |
| Original PHP bootstrap/artifact fixture | 35 assertions PASS |
| M6D transaction / execution controls | 97 / 63 assertions PASS |
| M6E read-only authority controls | 18 assertions PASS |
| Selected component authority/native-admission controls | 2 tests PASS |
| Original serving dependency controls | 90 assertions PASS |
| M6E scope, before staging this change | 294 assertions PASS; 25 migrations and 77 definitions preserved |
| Changed PHP / Python sources | 5 lint checks / 4 syntax parses PASS |
| Git | Diff check PASS; full fsck found no corruption (existing dangling objects retained) |

The combined first run passed all 35 bootstrap tests and exposed one new test
assertion that confused the existing publisher socket startup with a publication
operation. The assertion now checks the unchanged pristine journal; all 13 new
checkpoint tests pass on the corrected run. PHP tests use sequential fake PDO;
Python bootstrap tests model SQL, Linux ownership and
services. The cross-language integrity tests use the real PHP M6C builder,
sealed files and verifier/exporter, with only Unix modes modeled on Windows.
Filesystem evidence publication uses real private bytes, inodes and hard links;
the Windows sandbox's hard-link denial required an approved local test retry.
No weakened publication primitive is used.

Native acceptance still requires, in an explicitly isolated disposable fixture:

1. Observe actual committed pre-B source eligibility through the separate fixed
   MySQL session, check its exact schema/server/account and original approval and
   build/producer identities, and verify original sealed artifacts.
2. Implement a consistent snapshot/restoration contract at this boundary. Preserve
   the complete relational closure and its original IDs; copying release rows or
   replaying the descriptor does not establish eligibility. Independently read
   back the distinct lifecycle schema and artifact namespace, with exclusive
   ownership and no cross-schema authority fallback.
3. Admit only the restored lifecycle fixture after unchanged eligibility succeeds.
   Preserve migration 025's one staging target per site/schema. No target is
   created during checkpoint capture.
4. Only a later authorized increment may request/claim A, exercise its real failure
   and prove terminal M6D authority plus same-operation helper settlement before B
   admission. The prior A-only synthetic stage authority does not qualify.

The unchanged gate order is apache, filesystem, ownership, assets, runtime,
health, recovery, provisioning. All six commissioning MISSING classifications
remain unchanged. No native receipt, activation, settlement, B deployment,
working-staging/production access, migration or merge occurred.

## Apache commissioning local controls following 90d891c

**IMPLEMENTED LOCALLY / NATIVE UNEXECUTED.** Starting source was
`90d891c8bb99a51d24c26ebf99d8e6a67cbc0a0f`, on the clean existing PR #131 branch;
the remote PR was verified OPEN DRAFT at the same SHA. The
[implementation and exact 15-file inventory](sprint-8.8-m6e-local-implementation.md#apache-commissioning-integration-following-90d891c)
describe the separate Apache ownership boundary and resolved ordering dependency.
The eight-gate order and all six MISSING classifications remain unchanged.

The new controls run the actual `NativeProbes.probe_apache`, collector, original
M6C PHP fixture/exporter, transfer receiver, publisher, independent observer and
identity-bound lifecycle against private files and distinct real PHP/Python children.
The original two-release fixture is built once before the probe; A is re-observed
and compared before helper effects. Only A is staged. Unix permissions, process
credentials, services, database observations, Apache, HTTP and TLS are explicitly
modeled on Windows. Real bytes, processes, hashes, inodes, hard links and immutable
evidence files do not turn those models into native evidence.

The positive observation checks seven original public files by GET and HEAD,
including HTML, marker, CSS, PNG and PDF: 55 requests, 40 denials. Both component
and gate captures precede reader shutdown; the result record follows verified
quiescence and removal. The gate remains INCOMPLETE. Negative controls cover
original source/producer mismatch before helper startup; marker/MIME/digest/security
and authentication/handler bypass; absent candidate, identical-byte inode replacement,
missing or replaced serving resources; reader death/uncertain shutdown; component,
archive and gate capture failures; failed configtest; failed cleanup; original
environment mutation; and archive/capture/result tampering. Existing component,
entrypoint, serving and commissioning regressions cover the related boundary cases.

The external `m6e-apache-commission` evidence directory retains command logs,
instrumented counts/durations, source hashes, development failures and final
working/index/committed integrity. Assertion counts use the prior runner's
assertion-method-call convention, including unittest's internal helper calls.
The WindowsApps python alias was unavailable; the already installed GIMP Python
3.14.7 executable was used with `-I -B`, alongside PHP 8.4.24. The first sandboxed
positive test failed in the helper; the same test passed with normal Windows file
access required by the existing temporary hard-link publication. This is not a
native Apache failure or PASS.

Executed focused validation (all PASS; zero skipped tests):

| Suite | Tests | Assertion-method calls | Subtests | Seconds |
| --- | ---: | ---: | ---: | ---: |
| `WebsitePlatformM6ECommissionApacheTest` | 8 | 403 | 23 | 402.011 |
| `WebsitePlatformM6EComponentTest` | 22 | 1,040 | 58 | 615.230 |
| `WebsitePlatformM6ECommissionControlTest` + `WebsitePlatformM6EServingControlTest` | 59 | 923 | 80 | 124.954 |
| `WebsitePlatformM6EComponentEntryTest` | 17 | 246 | 32 | 259.486 |
| Total | 106 | 2,612 | 193 | 1,401.681 |

The total is summed suite duration; the four suites ran concurrently. Counts and
full test names are retained in the external runner JSON and logs. The previously
passed single positive control took 14.766 seconds; its sandboxed failed attempt
took 6.124 seconds. Neither is added to the instrumented suite totals above.

PHP bootstrap-fixture controls passed 35 assertions in 1.311 seconds; serving
dependencies passed 90 in 1.565 seconds; host controls passed 33 in 0.466 seconds.
The working-tree M6E scope guard passed 286 assertions in 3.466 seconds, preserving
all 25 migrations and 77 matrix definitions. Scope is rechecked against the final
index and commit; those counts and durations are retained in the external integrity
record. Four changed PHP files passed lint (0.354, 0.345, 0.395 and 0.377 seconds
for ScopeTest, CommissionChild, Component and ComponentLocal respectively).
All 39 M6E Python files passed syntax compilation with warnings treated as errors.
Both documents passed relative-link, anchor and paired-fence checks (30 links,
9 anchors), and `git diff --check` passed (0.514 seconds). Documentation checks
are repeated after this result table is added. Exact raw tested code hashes and
Git-normalized working/index/committed bytes and modes are checked before handoff.

The full earlier 147-test campaign was not repeated: the shared component lifecycle
required its full component and entrypoint regressions, while unchanged bootstrap,
ledger and unrelated application campaigns remain outside this focused increment.
Native Unix/Apache/TLS/permission, host preparation, SQL, destructive faults and
operational acceptance obligations remain unexecuted by authorization and environment
constraints; no test skip conceals them.

Native requirements remain a separately authorized disposable Linux host with the
exact reviewed source, original protected bootstrap/handoff and source inventories,
pinned tools/modules, real systemd/cgroup identities, isolated certificates and
client credentials, and the fixture's original MySQL 8.4 build/producer rows.
Fixture-local MySQL/SQL authorization is still required. Run the existing fixed
commissioning route only after that preparation is authorized; no new public CLI
or privilege interface was introduced. Observe actual configtest, private TLS,
Host/SNI/trust/client-authentication, static responses, real canary denial, native
UID/GID/modes, inode/resource stability, reader termination and owned cleanup.
Capture original evidence before restoration and verify the operational target,
database, source artifacts, pointers and journal remain unchanged.

The shared staging droplet does not meet those isolated prerequisites and was not
accessed. Two-release retention/conflict, supervised helper/lock/fence, real health
fault/restoration, retained-state recovery and uncertain-reload reconciliation remain
separate incomplete producers. Native readiness, installed workflow, full acceptance,
M6G browser behavior and production approval remain outstanding. No native
commissioning PASS or operational host receipt is claimed or permitted.

## Standalone A and configtest local controls

This increment starts at `71b1839ee3390ea7e4c4c2bfdba258c6e38ea6d0` on PR #131's
existing OPEN DRAFT branch, with clean checkout and completed exact-head review.
The ending commit SHA, command logs, timing/count summaries, original failed
development runs and exact working/index/commit hashes are retained outside Git
in `m6e-entry-configtest/SUMMARY.txt` and its sibling evidence files. The one
automated review handoff identifies that ending SHA. See the
[exact file inventory and implementation contract](sprint-8.8-m6e-local-implementation.md#supervised-standalone-a-and-required-configtest-following-71b1839).

For later separately authorized native work only, the fixed public request is:

```sh
/usr/bin/python3 -I -B /opt/ubo/tests/support/WebsitePlatformM6EPreparation.py --component-a
```

The fixed bootstrap-owned `ubo-m6e-component-a.service` alone invokes
`WebsitePlatformM6EComponentEntry.py --component-a-owned`. There is no command,
path, release or config selector. Direct internal/protected invocation without
the original admitted dispatch, matching MainPID/cgroup/fragment/invocation and
lifecycle lock rejects before component effects. Public and owned paths recheck
original READY, handoff, source/configuration/host/expiry and pristine target.
The 90/420/1,200/120-second component/service/watchdog/cleanup budgets remain.

Ordering is original A transfer/stage, independent artifact observation, owned
config generation/identity verification, bounded pinned `apache2 -t -f` configtest,
successful command/process and unchanged-config recheck, private Apache reader,
independent HTTPS/artifact/resource observations, immutable evidence, reader stop,
process quiescence, original-inventory-bound removal, final original-state check.
No configtest failure or uncertainty can start the reader. No configtest reloads
or changes operational Apache. Windows success is explicitly modeled and is
never native Apache evidence.

Positive controls cover fixed request dispatch, live supervisor binding, the
original lifecycle, configtest-before-start ordering, hashes-only diagnostics,
original observer inventory and immutable cleanup/result evidence. Negative
controls cover direct invocation, cgroup/MainPID/fragment/invocation changes,
missing service admission/command, stale source, missing/altered handoff,
expiry/watchdog reserve, repeated/conflicting ownership, missing/malformed/replaced
config, failed/timed-out/unknown/overflow configtest, post-validation config/command
mutation, uncertain reader termination, capture failure, cleanup replacement and
original operational-state mutation. Existing component fault controls continue
to verify first-failure retention and LIMITED handling. Real private files,
inodes, PHP/Python processes and original artifact bytes are exercised; Linux
UID/systemd/Apache/TLS and SQL observations are controlled models.

Executed on Windows with existing Python 3.14.7, PHP 8.4.24 and Git
2.56.0.windows.2. Each Python row uses `python -I -B` with the external
`run_tests.py` instrumentation and the named test modules; full commands and
per-case output are retained in the evidence directory. Assertion-method calls
include calls made internally by unittest assertion helpers; they are not the
earlier uninstrumented assertion-count convention. Durations are wall seconds.

| Local campaign | Tests | Subtests | Assertion-method calls | Seconds | Failures / errors |
| --- | ---: | ---: | ---: | ---: | --- |
| Final entrypoint/configtest controls | 17 | 32 | 246 | 260.350 | 0 / 0 |
| Full component regression | 22 | 58 | 1040 | 762.796 | 0 / 0 |
| Final affected reader/capture rerun | 2 | 5 | 94 | 139.370 | 0 / 0 |
| Commissioning/bootstrap/serving | 94 | 185 | 1995 | 392.898 | 0 / 0 |
| Ledger recovery | 14 | 28 | 706 | 42.879 | 0 / 0 |

The full component regression was followed by final-source entrypoint/configtest
controls and the two affected reader/capture reruns after the cleanup deadline
and command/process quiescence refinements. These are 147 distinct Python tests
plus two focused repeats, with no skipped final controls. All negative controls
must reach their asserted fault boundary; no blocked native case is credited as
a passing native observation.

PHP bootstrap-fixture controls pass 35 assertions (3.194s), serving-dependency
controls 90 assertions (4.410s), and the working M6E scope guard 280 assertions
(8.859s). All exit zero with empty stderr. PHP lint passes for the one changed PHP
file (the scope inventory). All 37 M6E Python support/control sources parse and
compile with warnings treated as errors; the final nine changed Python sources
are rechecked with the exact integrity inventory. Both documents, 27 relative
links, fenced blocks and `git diff --check` pass. The staged and committed scope
counts and exact content hashes are recorded in SUMMARY.txt after commit.

Earlier development failures remain separate: the sandbox denied existing
hard-link publication (the initial accidentally expanded discovery ran 47 tests,
51 errors in 52.394s; assertion/subtest counts were not instrumented). The explicit
12-test development run had seven stale-test-ledger errors (104.797s); the initial
22-test component run had eight failures and one error (560.683s) from the serving
metadata model ordering. Two lifecycle rechecks exposed that same adapter issue
and a lost test event recorder when rebinding the ledger. Their logs and counts
are retained; the corrected final rows above supersede no historical native
evidence. Local hard-link tests ran with normal Windows filesystem access after
the sandbox restriction was established. No native service or database operation
was used to resolve those failures.

Native prerequisites remain an explicitly admitted disposable Linux VM with the
exact reviewed source and pinned tools/modules, existing bootstrap/handoff,
original release-A build/manifest/producer rows and immutable storage, actual
systemd/cgroup identity, private Apache config/TLS/UID observations and independent
cleanup verification. **Fixture-local MySQL and real SQL require separate explicit
authorization.** This task executes no SQL, native Linux/Apache/systemd, SSH,
service installation, host preparation, deployment or activation. Synthetic
responses are not substitutes for native database readiness.

The six commissioning blockers are unchanged: retained-release Apache static/access
checks; two-release asset transfer/serving/conflict negatives; supervised runtime
helper/lock/fence; real checker candidate/active fault/restoration; retained-state
interruption/unknown-authority recovery; owned uncertain-reload reconciliation.
Standalone evidence does not promote any of them, issue an operational receipt,
accept an installed publishing workflow or complete M6E native acceptance.
M6C/M6D remain FORMALLY CLOSED; M6E PARTIALLY IMPLEMENTED / REVIEW REQUIRED;
M6F/M6G NOT STARTED; production UNAUTHORIZED.

## A-only candidate-serving local evidence and native boundary

Starting source HEAD: `18b4900ebad62c511587c4bb4498000460cf5129`, clean reviewed
OPEN DRAFT PR #131. The exact introducing commit SHA and post-commit inventory
are recorded in external `m6e-candidate-https/SUMMARY.txt`, together with the
exact-head review request. [Implementation responsibilities and binding](sprint-8.8-m6e-local-implementation.md#a-only-private-candidate-serving-following-18b4900)
are confined to seven M6E Python support/test files and these two documents.
No production code, PHP, templates, host contract or closed M6C/M6D policy changes.

The controlled-local positive path reads original A's actual staged candidate
and asset pool through a separate Python reader. The original PHP build/export
and private transfer, production Python stage and fresh independent observers
remain in the path. The reader holds a real retained PNG descriptor through
immutable evidence capture. Evidence precedes shutdown; all children must be
reaped before identity-bound removal. Original target/database/journal sentinels
remain unchanged. Identical-byte inode substitution is detected even when all
HTTP byte checks pass; no later observer becomes a replacement baseline.

Executed response negatives include wrong marker, MIME, digest and security
headers, handler and authorization bypass. Requests explicitly exercise wrong
Host/SNI/trust/client certificate, missing client authorization, methods, private
paths, traversal and actual canary files. Artifact/resource faults include absent
or corrupt candidate, missing marker, identical-byte replacement, changed/missing
public files, extra/missing serving objects, malformed/missing inventories before
and after serving, and replacement between capture and removal. Process/evidence
faults cover actual reader death, timeout, modeled unknown termination, capture
failure, incomplete restoration observation and first/secondary failure retention.
Controlled failures remain INCOMPLETE; uncertain ownership/observation/reader
state remains LIMITED. The outer local test harness disposes of its synthetic
temporary fixtures only after asserting those component outcomes and retaining
bounded diagnostics; this is not native uncertain-resource cleanup evidence.

The full affected component suite passed 22 tests / 58 subtests / 464 top-level
unittest assertion calls in 500.623 seconds. After the final fixture config and
executable CGI changes, all five candidate controls passed again: 24 subtests,
181 assertion calls, 304.711 seconds. Existing serving controls passed 12 tests
in 15.348 seconds. These counts overlap; they are not unique-case totals. The
positive path verifies seven original files (9,144 bytes), seven GET and seven
HEAD file responses, the index route and 40 denials: 55 requests in total. It
independently binds 20 serving resources and retains the original inventory hash.

Current scope passes 278 assertions, including preserved migrations/definitions
and closed source. All seven changed Python files pass AST/compile with warnings
as errors; no PHP changed, so changed-PHP lint is not applicable. The two documents
pass paired-fence and 24 relative-file-link checks (existing fragment anchors are
not all verified). Git whitespace and exact working/index/committed object checks
are retained in the external SUMMARY after commit. Original failed development
runs remain separately recorded: a restricted loopback launch, a template input
type error, and three Windows replacement controls whose open index handle blocked
the intended rename. Those cases were corrected and rerun; no failed run is
credited as PASS. The final reader holds a retained PNG instead.

The native-only process identity/capability checks and Apache configuration are
authored and syntax-inspected, with no native execution claim. Local model results
do not test Apache's configuration merging, pthread mutex implementation or TLS.

Native execution is **AUTHORED / UNEXECUTED** and needs separate authorization.
Prerequisites are the existing admitted disposable Linux fixture, exact source
and module/tool pins, protected original A, existing role accounts and CA/server/
client/wrong-certificate files, an unused loopback `127.0.0.4:8443`, and no conflicting
resource paths. The fixed Apache `-X -f <owned-config>` process is owned by the
existing admitted operator; it is not installed as a service or exposed as a new
privileged publishing interface. The config reuses the candidate template and
existing certificate paths, combines Location authorization with preceding file
denials using [AuthMerging And](https://httpd.apache.org/docs/2.4/mod/mod_authz_core.html#authmerging),
supplies only the separate listener, suppresses log
bodies and uses `SSLSessionCache none` and `Mutex pthread default`. Native acceptance
must establish support for that [Apache mutex mechanism](https://httpd.apache.org/docs/2.4/mod/core.html#mutex),
config validity, exact module/UID confinement, real TLS chain/authentication and
Host/SNI behavior, actual denied canaries, bounded shutdown, absence of descendants
and exact resource cleanup. Unrecognized runtime objects fail the closed inventory.
The PID file must identify the tracked process and retain its original identity
or disappear only after verified normal shutdown.

No native result is inferred from Windows models or older native runs. All six
MISSING producers remain unsatisfied. M6C/M6D: FORMALLY CLOSED. M6E: PARTIALLY
IMPLEMENTED / REVIEW REQUIRED. M6F/M6G: NOT STARTED. Production: UNAUTHORIZED.
No server/droplet, SSH, SQL, installation, service restart/reload, receipt, activation,
deployment, migration or merge operation was performed.

## Component ancestor and observer-inventory correction

Starting head: 8916b25a67f1f15e890cb7b2fc4b3a9eac7a3206. Its automated review completed
WITH findings 4447829150 and 4447829161, not a clean review. Before-fix evidence is
retained separately outside Git in m6e-component-review-fix.

The first reproduction invokes the unchanged production Tree constructor through
a deterministic helper UID 2102/mode adapter. O_RDONLY directory opening fails
with EACCES on the original root-owned 0711 data ancestor even though search is
permitted. The corrected 0755 ancestor opens successfully. Worker access to served
and journal children and helper access to sealed worker files still reject.
Control remains 0711. No native Linux/account/permission operation was executed.

The second reproduction runs the actual worker/transfer/helper/observer lifecycle,
then keeps the original index.html inode alive outside the component namespace and
creates a new inode with identical bytes and identical expected metadata. At the
starting head, cleanup deletes the replacement and reports success. Corrected
controls require the original observer hash at quiescence and retain that same
substitution as LIMITED before deletion. Source digests and observer evidence are
preserved. Different bytes, wrong UID/GID/mode, changed link target, extra control
or data objects, missing objects and missing/malformed observer hashes reject for
the intended reason. A never-completed observer cannot authorize staged output.

Races injected before ledger tree capture and after its deletion inventory reject
using the original expected identities; a late hardlink also fails at unlink.
Tests assert the fault was reached, the precise rejection, retained uncertain
file/journal and unchanged original bootstrap sentinels. The successful path still
verifies seven original public files, captures before removal, reaps children and
destroys the complete separate journal at fence 1. Startup/helper-death controls
assert the actual failed child/phase, preserving the original failure.

The affected suite continues to reject controlled evidence as native readiness.
Only focused component controls, current M6E scope, changed Python syntax,
documentation and exact committed-inventory checks are run for this correction.
Bootstrap/commissioning dependencies and transfer suites are unchanged and are not
rerun. Six targeted controls passed (163.058s); the full affected component suite
passed 17 tests (291.322s), including fault subcases. Four changed Python sources
pass AST checks with -W error; current scope passes 278 assertions. Two documents
pass paired-fence and 15 relative-link checks (anchors not verified). Retained
success inventory hashes exactly to the original observer value; substitution and
race captures retain fence 1 and the uncertain file. Exact committed inventory and
review dispositions are recorded in external SUMMARY.txt.
Native Linux remains AUTHORED / UNEXECUTED; six commissioning blockers
remain, with no server/SQL/infrastructure/receipt/deployment/merge operations.

## Release-A component controls following cd4af965

The native component lifecycle is implemented but **AUTHORED / UNEXECUTED**.
[Its ownership and authority boundaries](sprint-8.8-m6e-local-implementation.md#isolated-release-a-component-lifecycle-following-cd4af965)
remain separate from operational publishing and every commissioning gate.

[Component controls](../tests/WebsitePlatformM6EComponentTest.py) exercise the actual
runner with three separate processes: original PHP M6C builder/exporter/sender,
Python receiver/publisher and a fresh Python observer. The success case verifies
seven original public files (9,144 bytes), three asset objects including the
approved PDF, fence 1/phase 1, separate process IDs and capture-before-removal.
The full namespace is removed with its journal; the original target/database
sentinels remain byte-identical and no receipt exists. Unix identity/permission,
filesystem syscall and peer observations on Windows are explicitly modeled.
The local transport is private loopback TCP, not a claim of native Unix IPC.

Controls cover original producer binding, source change/extra files, worker write
and helper source denial, corrupted manifest/body, truncation/EOF, child startup
and helper death before/during transfer, independent readback corruption, extra,
foreign and hardlinked paths, replacement after capture, no-retry replay denial,
cleanup/capture/result-write faults and first-error preservation. Unknown
interrupted scratch is retained as LIMITED. Existing transfer/publication suites
add actual retained replay/no-overwrite, partial I/O and lost-ack cases. The native
entry rejects desktop execution; unchanged native readiness and six MISSING
entries prevent these component observations from authorizing commissioning.

The cold include-graph control also checks the exact worker support dependency
pins and rejects unknown support paths or changed bytes. This fixes the narrow
commissioning inventory boundary without changing production receipt admission.

Development runs first exposed PHP's handling of Windows extended-path prefixes;
only the local adapter removes that prefix for PHP paths. A sandbox run could not
connect its local TCP stream or create evidence hardlinks. Authorized local tests
then ran outside that sandbox. These failed development attempts are not native
acceptance. Detailed commands, outputs, counts, source hashes, preservation and
review state are retained in the external m6e-release-a/SUMMARY.txt.

Final focused local results (Python 3.14.7 -I -B; PHP 8.4.24):

| Check | Observed result |
| --- | --- |
| Component lifecycle | 11 tests PASS / 69.927s, plus bounded fault subcases |
| Commissioning collector/inventory | 47 tests PASS / 67.286s |
| Actual PHP/Python transfer | 20 tests PASS / 24.914s |
| Immutable publication | 8 tests PASS / 1.238s |
| Original-source/serving controls | 12 tests PASS / 11.556s |
| Bootstrap fixture | 35 assertions PASS |
| Cold runtime dependency | 90 assertions PASS |
| Operational default denial | 33 assertions PASS |
| Changed sources | 6 PHP lint and 8 Python AST syntax checks PASS |
| Current M6E scope | PASS; 25 migrations / 77 definitions preserved |
| Documentation | Paired fences and 15 relative links PASS; anchors not checked |

Distinct successful and interrupted component captures are retained outside Git.
The immutable before-cleanup capture retains the original component fence; the
quiescent ownership capture precedes removal. Interrupted unknown scratch remains
LIMITED in its captured ledger. These extra retained runs are local OS models.

No server/SSH, SQL/migration, native Linux, Apache/systemd, installation, receipt,
activation, deployment or merge operation was performed. Release B/Apache/asset
retention and the other four incomplete commissioning producers remain outstanding.

## Cold-process runtime dependency correction

Before editing 50c4356, direct includes of CommissionServing.php and NativeSetup.php
in separate PHP CLI processes each left SiteBuildRuntime unloaded and raised the
reviewed missing-class TypeError at the real Closure::fromCallable call. Both had
zero autoloaders and no M6C fixture. Identical reproductions succeed after the one
explicit runtime require in NativeSetup; original before/after results are retained
separately in m6e-source-dependency outside Git.

[WebsitePlatformM6EServingDependencyTest.php](../tests/WebsitePlatformM6EServingDependencyTest.php)
passes **90 assertions**. Two new PHP processes prove the class initially absent,
load each actual entry graph, and call the same m6eArtifactStore function used by
both native callers. Reflection identifies the stored closure as SiteBuildRuntime::guard;
an initialized transaction-active PDO model proves the constructor executes that
guard and rejects before effects. Database connection/config remain uninitialized,
no fixture/autoloader is introduced, and private directories are unchanged and removed.
Two Windows entry checks retain unsupported-host/missing-argument denial. Linux
UID/bootstrap/missing-or-invalid-admission checks remain unchanged and unexecuted;
these local results do not establish native admission or PHP/MySQL/Apache acceptance.

The original bootstrap fixture passes **35 assertions** and the related real
PHP/Python serving/transfer controls pass **12 tests**. Four changed PHP files lint;
no Python source changed. Current scope, document/diff checks and exact committed
blobs are recorded in external SUMMARY.txt. Production code, receipt/schema,
transfer framing, permissions, ledger/watchdog/socket policy and all six MISSING
entries are unchanged. The reviewed head had an unresolved P2 finding, not a clean
review; correction-thread disposition and new-head review status belong to the
external handoff record. No server, live SQL, native infrastructure or deployment ran.

## Shared Apache/assets source and serving controls — native incomplete

This source slice starts at ce2d15466bff1b0243e5f595762b721561980b9c. No native
entry was executed. The two requested producers remain incomplete: new worker-source
inspection is wired into both gates, but the shared serving checks still require
the native fixture/process/socket/alias ownership and restoration adapter described
in the [implementation inventory](sprint-8.8-m6e-local-implementation.md#shared-apacheassets-observations-following-ce2d154--incomplete-producers).
All six prior MISSING entries remain and block host receipt issuance.

[Serving controls](../tests/WebsitePlatformM6EServingControlTest.py) use two actual
M6C builds in one local fixture and the real PHP exporter/UBT1 sender, Python receiver,
original package validator, Publisher.stage and immutable pool. A replay transfers
no files and preserves inodes. The exact original manifests bind distinct producer
identities, shared image/PDF and changed CSS. GET/HEAD observations read actual stored
bytes through deterministic HTTP adapters; original and changed assets remain readable
after a modeled switch to B, with A retained on candidate. No native Apache claim is made.

Negative controls cover original producer/source/binding changes, foreign identity,
wrong active/candidate content, MIME/length/digest, redirects, cache/TLS/security/CSP,
methods/private paths, immutable collisions, corrupt retained bytes, unexpected files,
hardlinks, traversal and incomplete/interrupted frames. Failed HTTP summaries precede
predicates/restoration; failed worker identity hashes survive native-probe validation.
The original collector tests cover first-error preservation, checkpoint-before-cleanup,
cleanup failure, modeled-evidence denial and absent operational receipt. Separate
transfer regressions retain actual exporter mutation, old-producer, authority, deadline,
storage-failure and lost-ack controls. No fake SQL deployment success is needed by the
new two-release component path. Native canary handlers and uncertain-effect recovery
are not credited by modeled denial of missing paths.

Initial development observations are retained in the external summary: the first
serving setup failed a strict PHP array comparison because original metadata key
order differed. Comparison now canonicalizes mappings without weakening scalar
types or reconstructing original manifest bytes. A sandbox commissioning run hit
Windows hardlink permission denials; the authorized private-file controls were then
run outside that sandbox. These failed attempts are not described as acceptance.

Final focused local checks use installed Python 3.14.7 with -I -B and PHP 8.4.24:

| Program | Actual result |
| --- | --- |
| WebsitePlatformM6EServingControlTest.py | 12 tests PASS / 9.998s |
| WebsitePlatformM6ECommissionControlTest.py | 46 tests PASS / 63.979s |
| WebsitePlatformM6ETransferTest.py | 20 tests PASS / 20.925s |
| WebsitePlatformM6EIntegrationControlTest.py | 37 tests PASS / 27.904s |
| WebsitePlatformM6EBootstrapFixtureTest.php | 35 assertions PASS |
| WebsitePlatformM6EHostTest.php | 33 assertions PASS |

Six changed PHP files lint; five changed Python files pass AST parsing with warnings
as errors, without native execution or bytecode. The working M6E scope guard passes
246 assertions. Both changed documents have paired fences and ten valid relative
file links; this does not claim an automated anchor check. Exact staged/committed
scope counts, tested blob inventory, diff/template integrity and new-head review
handoff are in m6e-apache-assets/SUMMARY.txt outside Git.
No server, SSH, real SQL, Apache/systemd/MySQL installation/start/reload, native
bootstrap/commissioning, operational receipt, staging publication or deployment was
performed. M6C/M6D remain formally closed. Full installed workflow, Linux acceptance
and M6G browser behaviors remain pending.

## Failed-measurement and deterministic-admission correction

Review of bcf64ec reported [lost failed measurements (4446480421)](https://github.com/fvd8383/ultimate-back-office/pull/131#discussion_r4446480421) and
a [host-dependent admission assertion (4446480432)](https://github.com/fvd8383/ultimate-back-office/pull/131#discussion_r4446480432). Both findings were reproduced locally through actual
collector/helper functions and controlled dependencies; this is not Linux execution.
Before-fix filesystem replacement_rejected=0 reached the strict rejecting predicate,
then an empty checkpoint and cleanup. A native runtime producer's post-child pin
failure likewise lost its obtained hash and real child PID. Controlled Linux
platform/argv/feature prerequisites plus a missing-config reader produced the actual
FileNotFoundError, with zero UID calls, instead of the test's intended Denied.

The corrected failed checkpoints retain the observed zero/hash and participant
context, first safe failure classification and finite stage progress before cleanup.
They remain invalid gate evidence. Independent failure leaves its unavailable
observations absent; earlier publications stay unchanged; later gates do not execute.
The fixed mutation case proves published bytes survive backend cleanup changes.
Capture and cleanup failures stay secondary to the original probe failure, owned
resources stay tracked, and no host receipt is issued.

Regression coverage includes returned invalid numbers/status, exceptions before any
measurement, child execution/parse/internal-pin failures, first-role/first-HTTP
progress, inventory/journal/configtest failure, malformed/private fields, complete
filesystem/ownership positive and malformed-negative paths, offline rejection and
existing controlled commissioning. No expected measurement is supplied to replace
missing data. Native outcomes remain modeled through explicit infrastructure adapters.

Platform, wrong-UID and missing-config tests are separate. They patch only the
helper module's system/OS references within context managers and assert exact
protected-reader/UID call boundaries. The real positive configuration/receipt parser
still runs with private mapped files. Operational paths are never read or written.

Windows local verification with installed Python 3.14.7 (MINGW Clang UCRT) passes:
**46 commissioning controls / 106.051s**, followed sequentially by **37 integration
controls / 47.205s**, then **8 immutable-publication controls / 1.971s**. The earlier 44-control development run also passed (85.916s);
then two explicit complete-producer controls were added and the finite malformed
access boundary was checked. Before/after reproductions pass their distinct expected
loss/preservation assertions. Two changed Python AST checks with warnings as errors
and the working 242-assertion M6E scope guard pass. Staged/committed integrity, exact tested blobs and review dispositions are retained in
m6e-measurement-correction/SUMMARY.txt outside Git. No PHP source changed,
so no PHP lint expansion or production edits were needed. Historical bootstrap inode
repetitions, closed M6C/M6D campaigns and native infrastructure were not rerun.
The six incomplete gate producers and native issuance block are unchanged;
commissioning, installed workflow and full M6E acceptance remain unfinished.

## Current commissioning command and local controls — partial

**COMMISSIONING / RECEIPT ISSUER — PARTIALLY IMPLEMENTED LOCALLY.** Filesystem
and role-access component producers are authored; six required gate producers are
incomplete. Native commissioning has not executed. Installed workflow and full
acceptance remain pending. The native issuer rejects this incomplete inventory.
A successful formatter/private-publication control is not a host prerequisite PASS.

For a later separately authorized disposable VM prepared from the exact same
reviewed source, as its root operator (never ubo-stage-app or the developer machine):

~~~sh
/usr/bin/python3 -I -B /opt/ubo/tests/support/WebsitePlatformM6EPreparation.py --commission
/usr/bin/python3 -I -B /opt/ubo/tests/support/WebsitePlatformM6EPreparation.py --inspect-commissioning
/usr/bin/python3 -I -B /opt/ubo/tests/support/WebsitePlatformM6EPreparation.py --teardown
~~~

The fixed commissioning service calls Commission.py --commission-owned internally.
That mode checks its own systemd cgroup, MainPID and installed fragment; it is not
a general privileged invocation. Public commission validates the original ready
bootstrap before starting the service. The current expected terminal is
COMMISSIONING INCOMPLETE / HOST RECEIPT ABSENT, with the six missing executable
steps. These commands are authored entrypoints, not a recipe for obtaining a usable
receipt from the current partial implementation.

Inputs are the existing version-2 bootstrap admission, retained plan and ownership
ledger, immutable bootstrap.json and bootstrap-publication.json under
/var/lib/ubo-m6e-evidence/<run_id>/, protected bootstrap-handoff.json and current
fixed configuration. The handoff must match its original bytes, complete schema,
source inventory, run/VM/boot, roles, tools/modules, fixture/database and configuration.
There is no supplied gate result, configurable root, environment bypass or new
runtime configuration. Changing source/configuration or dependencies invalidates
measurements; inspection cannot regenerate a matching receipt.

The sequence is historical bootstrap validation, current ownership/configuration/
identity/watchdog recheck, receipt absence, ordered eight-producer collection,
immutable original observations before restoration, owned cleanup, final independent
evidence and file validation, then receipt publication and consumer acknowledgement
only if every gate is complete. The current implementation stops at evidence policy,
which identifies six missing producers. It never writes a temporary all-PASS receipt.

See the [gate-to-executable mapping](sprint-8.8-m6e-local-implementation.md#commissioning-collector-and-protected-receipt-publication--partial).
The missing steps are: retained-release Apache static/access checks; two-release
asset transfer/serving/conflict negatives; pre-receipt supervised helper/lock/fence;
real checker candidate/active fault/restoration; retained-state interruption and
unknown/unavailable-authority recovery; owned uncertain-reload reconciliation. They
require additional executable state/ownership integration, not changed boolean
attestations or removal of the missing-producer policy. Later native execution must
also validate the authored filesystem/ownership and runtime/configtest portions.

Commissioning appends commission-start, per-gate before-restore/final measurements
and commission-result records; bootstrap READY bytes remain historical. All scratch,
canaries, unexpected-success access-attempt leaves and pending config enter the
existing ownership ledger before effects. The watchdog stops the commissioning
service before locking and cleaning. Scratch cleanup checks original directory and
child identities, rejects unknown names or replaced inodes, and does not follow
links. If bounded cleanup cannot prove ownership, it retains state as LIMITED.
The original worker-private two-release artifacts are retained by bootstrap for the
later installed tests. No deployment row/current DB pointer or backup is invented.

The 420-second service cap is subordinate to the existing 1,200-second timer and
120-second cleanup reserve. Stage admission uses the original timer command's
monotonic start, not a restarted watchdog. The full acceptance coordinator remains
600 seconds and still rejects its missing scenarios/audit/predicate schemas.
Commissioning does not add callable stubs to native_readiness().

Publication code uses /etc/ubo-site-publisher/commission-receipt.pending and the
fixed host-receipt.json. Both intentions precede effects. The candidate is fully
written, synchronized and checked before the protected immutable issuance-intent
record and no-replace rename. Final mode is root/root 0644, regular and one link,
with trusted ancestors. The unchanged receipt has exactly version, profile,
worker_config_sha256, verified_at, expires_at, gates and installed_files. Rich
provenance stays in protected hash-bound evidence. Expiry is capped by the fixture
and original watchdog, as well as the existing 24-hour maximum.

Any existing receipt conflicts. Publication acknowledgement uncertainty preserves
the exact final object and its intent; no delete/overwrite/retry is attempted.
Inspection checks exact bytes, inode, original identity and expiry without changing
the ledger or refreshing validity. A present conflicting/expired object fails.
Native inspection additionally requires complete evidence, so a copied synthetic
private receipt cannot authorize operational inspection. No operational receipt
or actual consumer startup was executed in this desktop task.

Local controls use installed PHP and Python, private files and bounded children.
They exercise actual dispatch/result parsing, collector, missing/partial/failed and
modeled-evidence rejection, binding/expiry/budget checks, source/configuration and
include-inventory changes, modes/hardlinks/ancestors, original capture before cleanup,
first-failure preservation, no-replace publication faults and exact read-only
uncertainty reconciliation. PHP compatibility calls the real pure validate method;
Python compatibility calls actual load_config with explicitly mapped protected-read
and OS boundaries, preserving its checks. The unmodified desktop operational load
still rejects. Synthetic format/publication objects exist only in private temporary
directories, with no usable native evidence.

Executed local verification on Windows 11, installed Python 3.14.7 (MINGW Clang UCRT) and PHP 8.4.24: **28 commissioning controls PASS (44.457s)**; **11 selected bootstrap lifecycle controls PASS (149.020s)**; **37 existing process/provisioning/evidence controls PASS (60.675s)**; **8 immutable-publication controls PASS (2.082s)**. Two watchdog controls were rechecked after the activating-service identity change and pass (21.301s). PHP host/fixture/authority/health programs pass **33/35/18/43 assertions**. Nine changed Python AST checks (warnings treated as errors), two PHP lints, six relative document links and working scope/diff checks pass. Staged/committed integrity and review handoff details are retained in the external summary.

Validation commands and final integrity totals are recorded in the external
SUMMARY.txt. Initial sandbox hard-link denials and corrected development failures
are retained separately. The existing allocator-sensitive fixture controls and
historical 68-program/M6C campaigns are not repeated. Windows bytes, child PIDs,
inodes, replacements and publication are real; Unix ownership, service/VM/SQL and
native probe outcomes in controls are modeled. Native directory durability,
renameat2, Linux permission enforcement, actual Apache/MySQL/systemd and the new
native probe bodies remain unexecuted. These limitations apply even when all local
controls pass. Earlier sections are preserved as historical records.

## Recursive directory lifecycle controls following a5ff864

The previous 14/31 Windows results below remain historical PASS evidence. The
reviewer reported Linux full-suite and isolated index-1 partial-construction failure
on that head: a newly created `/opt/ubo-m6e/infrastructure` inherited cached regular
kind, and inventory raised `base_tree_hardlink` for its real directory link count.
This is reviewer-reported Linux execution, not a local Linux run.

Before-fix local reproduction injected stale regular or socket metadata immediately
after real mkdir at that path, using the directory's actual device/inode. Actual
identity/inventory saw the wrong modeled type and cleanup remained LIMITED with
resources/accounts retained. Windows reported directory link count 1, so the regular
case passed that link check and failed later in cleanup. The command failure stayed
primary. After correction the same injection is cleared by fresh metadata before
inventory: real directory type, root/root and 0755. Both partial-clone cases reach
their intended command failure, release all owned entries/accounts, preserve
before-teardown evidence and finish in phase `removed`.

The audit covers file/mkdir/socket helpers, recursive constructor and `put()` parents,
modeled command outputs and direct test filesystem calls. Intentional foreign,
replacement and unsafe-type fixtures remain unmanaged; independent text-template
scratch files have no modeled metadata cache. New directory controls preserve
restrictive existing parents and reject file/socket/mismatched-type collisions.
File callback/prefix/fsync controls inject stale directory and socket metadata;
transient teardown and distinct same-content foreign replacement remain exercised.
Three fixed repetitions cover sensitive directory/partial-construction cases in
fresh private directories: all 12 executions pass, with every result retained
without retry-until-green.

Sequential full standalone runs pass: **14 recovery tests in 19.381 seconds** and
**35 bootstrap tests in 178.228 seconds**, using installed Python 3.14.7 (MINGW
Clang UCRT) on Windows 11 build 26300. The five initial focused controls also pass
in 6.624 seconds. Both changed Python AST checks and 221 current scope assertions
pass; scope allowances are unchanged. Exact commands, durations, platform, document
checks, finite audit and before/after bytes are retained outside Git in
`m6e-fixture-directory-lifetimes/SUMMARY.txt`. No local Linux runner is available;
none was installed or created. Actual bytes/inodes/renames/hardlinks are distinguished
from modeled Unix metadata/services. No native bootstrap, crash or power-loss result
is claimed. Ledger/production code and scope allowances stay unchanged; M6E remains
**PARTIALLY IMPLEMENTED / REVIEW REQUIRED**, with commissioning/native work pending
and M6C/M6D formally closed.

## Fixture inode-assumption correction following 1834f93

The prior 13-test Windows PASS below is preserved. Review of that exact head
reported failures in both transient teardown subcases from stale modeled socket
metadata, plus a foreign-inode failure in the full suite and three isolated runs.
The review comments do not identify their platform; those are reviewer-reported
results, not independently observed local Linux execution.

Local deterministic reproduction seeded socket kind/group/mode for an actual newly
opened regular descriptor's device/inode. The real identification callback observed
that stale metadata and the unchanged ledger rejected it. This explicitly injects
metadata reuse; no allocation loop or fake stat/inode is used. One observation of
the old foreign-file setup on Windows did not reuse the original inode, which does
not contradict the reviewer or establish portability. Original evidence is retained
separately in external `m6e-fixture-inode-controls`.

The corrected fixture initializes fresh metadata before identification. Controls
verify normal/callback/injected failure ordering, intended prefix/file-sync fault
boundaries, preservation of live unsafe metadata and collisions, renamed objects
and remaining hardlink names. The corrected foreign-inode case preallocates a real
file while the original exists, retains the installed object and checks all three
identities before same-content substitution. Content and hardlink subcases assert
their own conditions before triggering the original failure. Existing transient
teardown, persistent failure and repeated replacement-denial controls remain.

Validation uses installed Python 3.14.7 (MINGW Clang UCRT) on Windows 11 build 26300.
Sequential full runs pass: `WebsitePlatformM6ELedgerRecoveryTest.py` **14 tests**
in 20.369 seconds, then `WebsitePlatformM6EBootstrapControlTest.py` **31 tests** in
173.974 seconds. Four sensitive controls pass in each of three fixed repetitions
(12 executions, zero failures/errors) in fresh private directories. Two changed
Python AST checks, the 221-assertion scope guard and document/diff checks pass.
Every result is retained rather than retrying until green. Exact commands, timings,
syntax/scope/document/diff checks and tested source hashes are recorded in external
`m6e-fixture-inode-controls/SUMMARY.txt`. No scope expansion is needed.
WSL reports it is not installed; no available local Linux runner was identified,
installed or created. No Linux result is claimed. Real bytes/inodes/renames/hardlinks
are exercised; Unix metadata and services remain modeled. Ledger/production code,
watchdog/admission policy, closed M6C/M6D, 25 migrations and 77 definitions remain
unchanged. M6E stays **PARTIALLY IMPLEMENTED / REVIEW REQUIRED**, with commissioning,
receipts, installed workflows and native acceptance still pending.

## Pending-ledger correction controls following 82aef44

Before-fix reproduction retained real 0-byte and 13-byte pending files, later
`FileExistsError`, and zero modeled stop calls through actual bootstrap teardown.
A post-rename directory-sync error separately reproduced a stale cached inode.
After correction, each transient fault leaves no owned pending file; deliberate
save and binding-checked reload succeed. Empty/prefix cases both reach five real
controlled executor stop calls (four workloads, then the watchdog timer), release
all recorded resources/accounts and retain failure/evidence records. Persistent
save failure reaches no stop executor, reports LIMITED and leaves the guard ARMED.

`python -I -B tests/WebsitePlatformM6ELedgerRecoveryTest.py`: **13 tests PASS**
(22.376 seconds). Cases cover successful replace/reload, empty versus actual prefix,
zero progress, file sync, close and cleanup failures, preexisting/foreign pending
objects, descriptor/name disagreement, unsafe type/mode/hardlinks, pre/post-rename
faults, exact-object/full-content reconciliation, actual teardown/repeat cleanup,
persistent failure and diagnostic canaries. Twelve selected existing bootstrap
controls pass in 60.368 seconds, including capture-before-cleanup, original failure,
replacement safety, evidence roots, account cleanup and watchdog/public teardown.
Three changed Python files pass AST syntax checks; the scope PHP file passes lint.
Only the exact new test path is added to the current M6E scope policy. Working,
staged and committed scope/diff results and exact commands are in external
`m6e-ledger-recovery/SUMMARY.txt`, alongside original failures and corrected bytes.

These use real private bytes/inodes. Unix metadata, directory synchronization and
services are modeled on Windows; a real alternate descriptor models disagreement
because Windows forbids renaming the open writer. Earlier timestamp-comparison and
platform-specific exception failures are retained separately. In-process ownership
does not prove persisted crash recovery. Post-replace failure records unconfirmed
durability even when exact installed content permits cached-identity reconciliation.
No Linux service cleanup, SIGKILL or power-loss recovery is claimed. Integration
capture dependencies are unchanged; no unrelated campaign/native entry point ran.
Overall M6E remains **PARTIALLY IMPLEMENTED / REVIEW REQUIRED**. Commissioning,
receipt issuance, installed successful workflows, remaining scenarios/COMMIT relay,
full audit/predicates and native acceptance remain outstanding; M6C/M6D stay closed.

## Current fixture bootstrap command and handoff

**FIXTURE BOOTSTRAP/SUPERVISION — IMPLEMENTED LOCALLY, NATIVE EXECUTION PENDING.**
The commands below are authored for a separately created, explicitly admitted
disposable VM only, under UID 0. They are not commands for `ubo-stage-app`, another
droplet or this Windows checkout. The VM image and pinned preinstalled tools are
external prerequisites; no missing bootstrap step is assigned to the operator.
The older sections remain historical/full-acceptance context, not bootstrap gaps.

The VM must meet the fixed identity/network/account/storage contract below, have
no operational mounts/configuration, no conflicting fixture accounts/UIDs/GIDs,
roots/units/drop-ins or listeners, at least 2 GiB and 20,000 available inodes on
both data roots, at least 1 GiB available memory, and cgroup v2/systemd. Supply
the exact clean root-protected checkout at read-only `/opt/ubo`, preserving Git
file bytes and executable modes. No Docker/container fallback or package install
is accepted. The version-2 root-protected `/run/ubo-m6e-bootstrap.json` has exactly:

- `version: 2`, 32-hex `run_id`, exact 40-hex `source_sha`, 32-hex `machine_id`,
  canonical UUID `boot_id` and integer `expires_at` (at most one hour ahead, with
  at least 1,320 seconds remaining at preparation).
- `tool_sha256`: exact keys `php`, `python`, `apache`, `mysql`, `openssl`, `systemd`,
  `git`, pinned to the fixed `BINARIES` paths in the preparation module.
- `auxiliary_sha256`: exact keys `getent`, `groupadd`, `useradd`, `groupdel`,
  `userdel`, `detect-virt`, pinned to the fixed paths in `BootstrapPlan.py`.
- `apache_modules`: every key in `BootstrapPlan.MODULES`, each with `path` and
  `sha256`. Paths are limited to that module's fixed Apache library locations;
  `builtin` instead requires the Apache binary digest and an actual compiled-module
  observation. Missing modules or unresolved placeholders reject preparation.

PHP requires PDO MySQL, posix, curl, sockets, DOM, fileinfo, mbstring and OpenSSL.
All fixtures and configuration are generated by these commands; never supply real
application configuration, a host receipt or a pre-published fixture. Run, as the
admitted VM's root operator:

```sh
/usr/bin/python3 -I -B /opt/ubo/tests/support/WebsitePlatformM6EPreparation.py --plan
/usr/bin/python3 -I -B /opt/ubo/tests/support/WebsitePlatformM6EPreparation.py --prepare-base
/usr/bin/python3 -I -B /opt/ubo/tests/support/WebsitePlatformM6EPreparation.py --inspect-base
/usr/bin/python3 -I -B /opt/ubo/tests/support/WebsitePlatformM6EPreparation.py --teardown
```

`--plan` is definitions-only and creates no host adapter. Preparation performs
preflight before mutation, installs/arms the fixed watchdog before starting managed
workloads, then dispatches `ubo-m6e-bootstrap.service`. This service, rather than
the initiating terminal, owns the body. Public teardown dispatches the fixed
`ubo-m6e-watchdog.service`; client disconnect does not abandon that cleanup.
Preparation has `TimeoutStartSec=600`, cleanup `TimeoutStartSec=120` and the timer
`OnActiveSec=1200`. These are separate bootstrap budgets; the existing full native
600-second acceptance deadline is unchanged. Systemd's explicit oneshot startup
timeout is used because `RuntimeMaxSec` does not bound oneshot execution
([systemd.service](https://www.freedesktop.org/software/systemd/man/261/systemd.service.html)).

The sole successful terminal is **FIXTURE BOOTSTRAP READY / COMMISSIONING NOT
EXECUTED / HOST RECEIPT ABSENT / NATIVE ACCEPTANCE NOT EXECUTED**. The distinct
profile is `m6e-fixture-bootstrap-v1`; it cannot pass the full native validator.
MySQL/Apache and the two fixed sockets are inspected; protected worker activation
and publisher calls are absent. Normal application admission still refuses the
missing commissioning receipt. Configtest/startup is not a measured TLS, health,
private-content, recovery, provisioning or publishing gate.

The root-only retained export `/var/lib/ubo-m6e-evidence/<run_id>/` contains the
plan, mutable durable ownership ledger, immutable `bootstrap.json`, checked
`bootstrap-publication.json` and bounded failure/before-teardown/cleanup reports.
The protected `/run/ubo-m6e-fixture/bootstrap-handoff.json` binds actual config,
source inventory, tool/module pins, roles/endpoints, observed schema/server/account
identity and two verified release/job/producer/manifest/asset inventories. It is
the exact bootstrap input for the future commissioning implementation, together
with the protected configurations it hashes. It contains no gate PASS values,
secrets or future outcomes. The target remains unpublished with zero deployment
operations, empty served output and pristine helper journal.

`--inspect-base` requires the existing lock, ready ledger, unexpired admission and
armed timer; it validates the immutable record/publication/handoff and remeasures
state without changing the ledger or reports. Repeated prepare rejects. Teardown
captures before restoration, stops only identified services, verifies no owned
process remains and removes only recorded objects/accounts. Replacements, unknown
children or failed cleanup are retained as LIMITED; the watchdog/control files
remain available until other resources are removed. Repeated teardown verifies
already removed resources and rejects replacement names. The export, admission
file and external `/opt/ubo` source are never deleted. Archived READY bytes are
historical evidence after teardown; they are not a still-valid prepared state.

Local verification: **26 bootstrap-control tests, 35 PHP fixture assertions and
37 existing integration-control tests PASS**. Python AST syntax checks cover all
six changed Python files; PHP lint covers all three changed PHP files. The current
scope guard verifies working/index/HEAD against the exact M6D baseline, preserving
25 migrations and 77 definitions. Commands are `python -I -B
tests/WebsitePlatformM6EBootstrapControlTest.py`, `php -d display_errors=0 -d
log_errors=0 tests/WebsitePlatformM6EBootstrapFixtureTest.php`, `python -I -B
tests/WebsitePlatformM6EIntegrationControlTest.py` and `php
tests/WebsitePlatformM6EScopeTest.php`. The external task summary records timings,
scope counts, original failures and corrected rechecks. Windows required normal
local filesystem access for hard-link report publication. Private bytes/inodes
are real; Linux UID/mode/socket/process/service observations and SQL responses are
explicit controlled models. PHP builds/validates real M6C bytes in isolated fake
PDO fixtures. No native SQL, migration, TLS, Apache, systemd or UID enforcement is
claimed, and the native same-site build sequence remains unexecuted.

Socket compatibility was originally recorded at `166765f`: both shared examples
combined `Accept=yes` and explicit `Service=`, and the fixture renderer removed
that line. The canonical correction now removes `Service=` from both examples
and removes that fixture-only repair. Per-connection activation uses the existing
matching `ubo-site-authority@.service` / `ubo-site-publisher@.service` names. Service
stdio, identity, capability/filesystem restrictions and resource limits are unchanged.
Authority group policy is explicit in the source as `SocketGroup=@PUBLISHER_GROUP@`
inside `[Socket]`, exactly once; the renderer no longer appends a fixture group.
With only `SocketUser` specified, systemd derives its default group; the earlier
omission did not necessarily select root's group. The explicit binding preserves
helper-only authority 0600 and root/worker-group publisher 0660 access.

The compatibility target is **systemd 257**, whose documented `Service=` setting
is for `Accept=no`. Do not assume identical behavior on every systemd release;
verify the actual pinned installed version during later authorized Linux execution.
The small policy check accepts only these fixed unit names and socket sections,
one exact listener, `Accept=yes`, exact user/group/modes, directory mode0755 and
MaxConnections4. It rejects duplicate/conflicting/extra directives, incompatible
`Service=`, extra sections and continuation syntax in canonical and rendered input.
It is deliberately not a general unit parser, `systemd-analyze` check or installed
activation test. Rendered socket bytes must equal ordinary placeholder substitution
of the canonical source, with no added fixture-only policy.

Focused controls read real repository templates and real private copies for
negative cases: old activation combination, duplicate access/activation, widened
permissions, wrong listeners/groups/names, extra sections/listeners and missing
matching service. They inspect actual renderer output and preserved service stdio/
hardening. Existing controlled orchestration still reaches only bootstrap READY,
with worker inactive, host receipt absent and no publisher/authority connections.
Inspection checks `Accept=yes`, the exact listener, no triggered instances and
zero accepted/active/refused connections, plus the pinned matching service bytes.
An idle accepting socket cannot be required to report a triggered template
([systemd socket manual](https://www.freedesktop.org/software/systemd/man/257/systemd.socket.html),
[systemd socket implementation](https://github.com/systemd/systemd/blob/v257/src/core/socket.c)).
This is source/controlled evidence, not a native configuration reproduction.
Original failing controls and passing rechecks, exact commands/durations, source
hashes, syntax/current-scope/document checks and review snapshots are retained
outside Git in this task's `m6e-socket-template-alignment` evidence directory.
Eight directly affected controls pass: four template/rendering tests in 1.295
seconds and four existing bootstrap workflow tests in 10.802 seconds. Two changed
Python files pass AST syntax checks; the unchanged current scope guard passes 218
assertions. The original two pre-fix controls recorded 30 failed assertions. No
unrelated suite, native harness, SQL or service execution was run for this correction.
The separate pending-ledger finding was unresolved at that socket correction;
the ledger recovery controls above now address it separately.

Remaining: measured commissioning and host-receipt issuer, installed protected
successful workflow, remaining fault scenarios and COMMIT relay, full Apache
restore/retention/command auditing/native predicates and Linux syscall/race/identity
acceptance. Full `--run` and legacy `--prepare` remain blocked. Overall M6E remains
**PARTIALLY IMPLEMENTED / REVIEW REQUIRED**; M6C/M6D remain formally closed.

## Observation capture before restoration

Review finding 4437786320 identified a shared ordering defect in all three final
drivers: cleanup preceded capture, and an exception skipped the old aggregate
`Recorder.add()`. The local before-fix reproduction saved UNREACHED rows after losing
obtained baseline/fault measurements. The corrected control path records progress
incrementally and publishes a checkpoint **before** `http_cleanup`, `config_cleanup`
or `identity_cleanup`. Real private marker bytes and saved snapshot hashes prove
the ordering in the controlled tests; a callback count alone is not the proof.

Every started observation has `lifecycle.version=1`, attempted and last-completed
stage, immutable safe events, first failure, secondary outcomes, separate capture
and cleanup results and actually evaluated assertions. FAILED partial observations
are diagnostic records, never acceptance. UNREACHED means the observation did not
start. A measured fault is recorded only after obtaining its measurement; calling
or partially executing the injection method does not manufacture a reached fault.
Adapters can retain an outcome before an independent measurement throws.

The fixed checkpoint boundaries are `observation` and, where applicable,
`provision-pending`. Their names bind the run and a source/config/case/observation
digest. Publication is exclusive, checked and at most 64 KiB per checkpoint; no
filesystem image, response body, SQL, token, credential or exception argument is
captured. Context is limited to 256 child/resource entries and 16 safe notes.
Original snapshot bytes show capture PENDING; the final lifecycle carries their
checked file/length/hash receipt. `validate_checkpoint()` verifies those bytes and
bindings. Pending configuration is hashed before nested cleanup can erase it.

Failure of required capture blocks PASS and never skips owned cleanup. If a case
already failed, it remains primary through capture and cleanup failures. Cleanup
responsibility is registered before partial construction. Successfully released
resources are skipped by outer cleanup; unresolved resources remain tracked for
bounded final handling. Pre-cleanup child context and final exit/reap metadata
distinguish requested termination from an original child failure. No SIGKILL/finally,
supervisor recovery, kernel durability or power-loss claim follows from these tests.

The coordinator's `evidence-before-cleanup.json` remains a **global** checkpoint
after per-observation checkpoints; it cannot recover lost per-case evidence.
Final report/publication bytes are not rewritten on a later error; a separate safe
post-publication diagnostic may describe that failure. Complete successful rows
retain all previous requirements. Legacy v2 integration rows without the versioned
lifecycle extension explicitly reject as `legacy_observation_lifecycle_missing`.
Missing stages, fabricated completion, failed cleanup/capture, wrong lanes, missing
native predicates and missing publication receipts still reject.

The 37 focused desktop controls pass, including the requested A–K failure/success
paths across actual driver functions and real private report files. The 600-second
coordinator and existing component limits are unchanged. Production code and the
native-readiness barrier are unchanged. Commissioning, receipts, supervised fixed
entrypoints/watchdog, native scenario orchestration, real COMMIT relay, remaining
Apache restore/retention/command auditing, native schemas and syscall/race work
remain incomplete; this correction authorizes or proves none of those gates.

## Full v2 execution barrier and fixture contract

The three final parent names dispatch through actual driver functions in
`tests/support/WebsitePlatformM6EDrivers.py`. Their controlled adapters are tested;
the installed path is incomplete. `WebsitePlatformM6EIsolated.py --run` and
`WebsitePlatformM6EPreparation.py --prepare` reject at the executable-readiness
barrier before creating infrastructure. There is no full native acceptance launch
command yet. The separate bootstrap-only commands above preserve that barrier.

The exact 13 parents and every mandatory named subobservation are defined by
`PARENTS` and `OBSERVATIONS` in `WebsitePlatformM6EAcceptance.py`. The first ten
component functions are retained; conversion to their complete v2 measured report
schemas remains missing. In particular, directory/file/named-inode replacement map
to `link_hardlink_special_path_rejection`; actual ENOSPC and syscall-fsync faults map
to `stage_storage_fault`; shutdown/timeout cleanup maps to `journal_fsync_fault`;
interrupted recovery maps to `activation_restore_compensation`. These obligations
remain required, not skipped or reclassified as injected API evidence.

The authored preparation contract requires a disposable dedicated VM (not a
container), PID 1 systemd, only loopback networking and no routes, matching machine
and boot IDs, hostname `ubo-m6e-<first 16 run-id characters>` and kernel argument
`ubo.m6e=<run-id>`. `/opt/ubo` must be a clean read-only exact-source checkout;
`/opt/ubo-m6e` is its separately owned fixture copy. A root-owned
`/run/ubo-m6e-bootstrap.json` binds version, source SHA, 128-bit run ID, machine/boot
IDs, exact tool digests and expiry. No real-host path override is accepted.

| Role | Fixed UID | Boundary |
| --- | ---: | --- |
| Operator | 0 | Preparation/configuration/teardown inside the owned VM only |
| Coordinator | 2100 | Private reports and component fixture roots |
| Worker | 2000 | Original artifact roots 0700/0600; worker-only credentials |
| Helper | 2102 | Served and journal descendants; no source-artifact access |
| Authority | 2103 | Independent protected service and SELECT-only SQL identity |
| Apache | 2104 | Public-file read/traverse only |
| MySQL | 2105 | Private disposable database files |
| Fixture writer | 2106 | Scoped test mutations and independent transaction inspection |

The fixed endpoint set is active.m6e.invalid/127.0.0.2:443,
candidate.m6e.invalid/127.0.0.3:443 and MySQL 127.0.0.1:3306. Private OpenSSL CA,
server/client and deliberately wrong test certificates are generated only inside
the future admitted VM. No DNS, ACME or provider operation is involved. MySQL 8.4
uses the unchanged 25 canonical migrations; `m6e_observer` has only SELECT on
`ubo_m6e_<run-id>`, while the separate writer can mutate that disposable schema and
inspect worker transactions. A foreign schema supplies the access-denial canary.

Required preinstalled tools include pinned PHP with PDO MySQL/curl/posix support,
Python, Apache with the reviewed modules, MySQL 8.4, OpenSSL, Git, systemd and account
utilities. Actual local ext4/xfs/btrfs read/write storage, Unix sockets/peer identity,
UID switching and supervision are necessary. Overlay/rootless Docker is not a
substitute. Actual ENOSPC/fsync-error cases additionally need an owned fault-volume
implementation and capabilities that have **not yet been authored**. No package
installation is performed by the scripts.

The required commissioning order remains: (1) verify namespace/source/tools;
(2) create tracked private fixture resources; (3) execute separate actual component
prerequisites for all eight Apache/filesystem/ownership/assets/runtime/health/
recovery/provisioning gates; (4) issue a protected source/config-bound receipt from
those measured bytes; (5) run normal protected installed entry points; (6) validate
the final report; (7) capture original bytes before separate namespace teardown.
Steps 3–5 are not connected end to end. No receipt issuer exists, and no integrated
PASS is assumed to commission the same integrated test. Component PHP agents are
not credited as normal protected worker/recovery CLI execution.

### Report and cleanup contract

Profile `m6e-isolated-v2` binds source SHA, run ID, configuration digest, tool hashes/
versions, namespace, roles, endpoints and database identity. All 13 exact parents
and their named observations must be present once. Unknown/renamed/count-only/
missing/UNREACHED/INCOMPLETE rows reject. Every observation requires separately
recorded baseline, intended boundary, outcome, independent PID/effect and cleanup;
hashes bind their original safe scalar evidence. Lanes distinguish controlled-local,
Linux component, real Apache/TLS, identity/supervisor, MySQL/authority, injected API
and process/syscall observations. None is power-loss or M6G browser proof.

`WebsitePlatformM6ECoordinator.py` captures a first evidence file before cleanup,
keeps the first failure, bounds child output while reading, reaps tracked children
and records unresolved resources. It writes original report bytes without replacing
an existing name, checks write/fsync/close/readback and then publishes a separate
hash/length-bound receipt. Original report bytes retain publication PENDING.
`validate()` checks content; only `validate_published()` can accept finalized bytes.
The offline CLI reads only the three named files and imports no application runtime:

```text
python3 -I -B tests/support/WebsitePlatformM6EAcceptance.py --validate report.json identity.json publication.json
```

The offline CLI requires native evidence. Native schemas for the first ten cases,
identity scenarios, retained manifest inventory and command auditing remain
unimplemented and reject; synthetic assertions cannot make them pass. A complete
controlled-local report is usable only through the explicit controlled test API,
never this native CLI. Coordinator cleanup and namespace teardown are separate;
the fixed coordinator budget remains 600 seconds with no production-limit increase.

### Concrete remaining executable work

Finish `native_readiness()` dependencies: measured commissioning, protected receipt
issuer and full acceptance run/health/fixture-plan integration, first-ten-case
evidence conversion, identity scenarios with real barriers and commit-fault relay,
kernel command audit and final owned-resource integration. The separate bootstrap
now authors source-pinned service/socket installation, watchdog and two same-site
builds; all remain native-unexecuted. Apache still needs exact restore/retention
cleanup; config fault/reload recovery needs actual owned supervisor verification.
The current instrumented session-hold barrier is **not** a
300-second timeout test. Actual syscall faults and race tests are still absent.

## Retained component definitions (historical v1 admission)

The component harness is [WebsitePlatformM6EIsolated.py](../tests/WebsitePlatformM6EIsolated.py).
It retains definitions from the earlier v1 fixture described below. The old launch
coordinator has been replaced by the v2 readiness barrier above; the following v1
admission is historical component context, not a current launch recipe.

Admission keys are exactly: `profile=m6e-isolated-v1`, `source_sha`, `fixture_uid`,
`fixture_root`, `source_root`, `served_root`, `journal_root`, `report_root`, `expires_at`.
Use a unique root-owned `/srv/ubo-m6e-fixture/<run-token>` with four precreated empty
child roots owned by the explicitly recorded non-root fixture UID. All ancestors are
root-owned/nonwritable to that UID. Do not use any installed publisher root, app
checkout, uploads, customer paths or marketing paths. Root-source SHA and clean Git
status must match the reviewed head. Pin PHP, Python, kernel, filesystem/mount,
Apache and module versions separately in the evidence ledger. No default UID/GID or
hostname is supplied.

The PHP artifact child reuses the actual M6C builder/validator/store with private
files and its established fake SQL fixture. The Linux component harness uses the
real `Tree` and `Publisher`; its authority is explicitly a controlled test channel,
not a database PASS. Its real filesystem/locks and process kill points do not prove
power-loss durability or installed service identity separation.

The integration command (currently BLOCKED before effects) is:

```text
/usr/bin/python3 -I -B tests/WebsitePlatformM6EIsolated.py --run
```

Do not execute it in this desktop task. It has a 600-second coordinator budget,
bounded child capture, exact SHA binding, a fixed case inventory, first-failure
context before teardown, owned-child reaping and exact private-root inventory
cleanup. Cleanup failure cannot turn the first failure into success. Reports use
exclusive creation, flush/fsync and a SHA-256 receipt outside the source checkout.
No assertion total is asserted before execution. In v2 all unexecuted cases and
observations are explicitly UNREACHED; they cannot become skipped PASS.

## Exact authored component inventory

| Case | Actual authored assertion path |
| --- | --- |
| stage_replay_retention | New immutable stage, exact replay, current unchanged, retained site asset digests |
| link_hardlink_special_path_rejection | Valid M6C baseline, then real symlink, hardlink and FIFO negatives, followed by restored valid baseline |
| fence_and_owned_lock | Parent owns real flock, separate fork/descriptor cannot acquire, stale/conflicting identities reject |
| activation_restore_compensation | Actual symlink replacement, committed known-good identity, new restore operation and fresh recovery compensation |
| first_deployment_absence | Fresh recovery removes only exact matching attempted current; no fabricated previous release |
| authority_change_before_rename | Independent authority changes after durable intent; active pointer remains unchanged |
| kill_after_intent | SIGKILL after durable intent and before current rename; journal/link inspected separately, lock reacquired |
| kill_after_rename | SIGKILL after rename but before completion journal; actual link and intent remain independently observable |
| stage_storage_fault | Valid source baseline then explicit storage API exception; no active pointer change |
| journal_fsync_fault | Valid source baseline then explicit journal API exception; no active pointer change; not kernel-fsync/power-loss proof |

Every negative begins with valid source/authority/phase evidence so it reaches the
intended fault. The local Windows helper suite separately runs real private file
copies and the same production protocol/manifest/fence/dispatch code with explicitly
modeled Linux mutations; it is not execution of the cases above.

The PR #131 correction extends the existing `stage_storage_fault` case with nested
immutable-object observations for both asset and manifest trees: complete publication
and exact replay; injected prefix-write, file-sync, chmod, publication and asset-group
failures; matching/conflicting no-replace winners; successful rename followed by a
directory-sync failure; and SIGKILL during a private prefix write. Each observation
records its step/result, with the active step in first-failure context. A fault must
be reached after a valid baseline; current/candidate stay absent. Killed-child private
leftovers must remain private and must not block a fresh exact insertion. No finally
cleanup or power-loss durability is inferred. Asset and journal roots are checked
independently; a shared filesystem between those roots is not required.

These nested cases are **AUTHORED / NOT EXECUTED**, and do not expand the milestone
acceptance matrix or complete any of the three drivers below. Windows tests execute
the actual PHP session/Python parser and actual publication primitive, but explicitly
model dirfd, Unix metadata/timestamps, renameat2, directory sync and read-only fsync.
Additional native syscall/cleanup/ownership/race stress remains required. The fixture
staging directory is 0700, matching the primitive's private-directory requirement;
there is no change to worker-private M6C source permissions or installed admission.

## Concrete incomplete drivers and mandatory acceptance inventory

Three inventory entries now call actual driver loops. Their required installed
backends are incomplete, so the native readiness barrier prevents execution and
overall PASS. The following native acceptance obligations still apply:

1. `apache_private_health_headers`: provisioned private fixture active/candidate TLS
   vhosts using the exact reviewed template, explicit certificate/client-CA ownership
   and pinned origins. Exercise the actual PHP curl checker with valid baseline,
   malformed/wrong marker/site/release, CSS/media MIME/byte/digest errors, redirects,
   bad trust/Host/SNI, stale cache, timeout and absence 503 without marker. Independently
   fetch HTML/marker/assets to prove private access, GET/HEAD restriction, no inherited
   PHP/CGI/proxy/SSI handler, no app fallback, dot/private/executable denial, no indexes
   or MultiViews, no-store/noindex/nosniff/referrer/frame/CSP policies and old asset
   retention across restore. B14/B16 browser execution remains M6G.
2. `config_failure_preservation`: in the isolated namespace only, capture installed
   config digest, current link and active/candidate identity; submit a deliberately
   invalid candidate config to the fixed provisioning driver’s configtest. Confirm
   prior config/link unchanged. Simulate uncertain reload and require blocked outcome,
   never provisioning success. A passing routine stage/publish must have zero reload,
   configtest, app-deploy, migration, DNS or certificate actions. The provisioning
   driver is not implemented in this PR and routine publishing cannot call one.
3. `worker_helper_authority_integration`: installed systemd socket/service boundary,
   separate worker/publisher/authority/Apache identities, no worker current-write
   permission, SELECT-only authority credential and protected exact source. Exercise
   real wire replies/identity/privacy/bounds, live lease renewal/expiry, target slot
   changes, stale resumed worker, greater recovery fence, equal-fence conflicts,
   process death, session timeout, result commit acknowledgement loss and unavailable
   authority. Preserve committed original success even after a newer winner; never
   compensate blindly or stage a candidate with recovery. No SQL transaction may
   span external work. Any native SQL fixture needs its own explicit authorization
   and source-bound evidence; prior M6D evidence does not prove this new observer.
   The artifact bridge is implemented locally, preserving worker 0700/0600 modes;
   the production helper no longer reads the worker source. The new transfer-only
   identity observations below do not complete this driver or prove live SQL,
   installed systemd restrictions, Apache/TLS or result-settlement behavior.

Additional Linux acceptance obligations: replacement-race stress against every opened
directory/file and named inode, full source/served ownership/mode/device inspection,
real filesystem storage exhaustion and syscall-fsync failures, full shutdown/timeout
cleanup, recovery after interrupted recovery, retained original execution identity,
foreign target and corrupt/missing journal refusal. The current race-free descriptor
code and injected API faults are not execution evidence for those adversarial cases.

The final acceptance report must contain the exact case inventory, distinct positive
baseline and fault reached for every negative, source/config/certificate digests,
actual assertion counts, child PID/exit/signal/barrier chronology, first failure before
cleanup, fence/journal plus actual-link observations, safe HTTP evidence, DB-result
resolution where authorized, ownership ledger and cleanup outcomes. Expected negative
stderr is matched explicitly; unexplained output fails review. Reports must identify
missing observations and terminate INCOMPLETE/FAILED rather than inventing totals.

## Installed host contract prerequisites

All eight section-18 gates need separately executed evidence: Apache, filesystem,
ownership, assets, runtime, health, recovery and provisioning. A root-owned receipt
binds the verified config, exact included PHP source, installed helper/authority code,
Apache config and supervisor units. It is valid for at most 24 hours and is checked
before any operation. No `enabled=true` value can substitute. No receipt is generated
by this implementation or these examples.

Operator-owned config paths are fixed beneath `/etc/ubo-site-publisher`; supervisor
sockets are fixed beneath `/run/ubo-site-publisher`. Served roots use the approved
`/var/www/ubo-sites/staging/sites/<site_key>` layout; journals use
`/var/lib/ubo-site-publisher/staging/<site_key>`. Actual site keys, numeric identities,
source roots, private hosts, CA/certificate files and access groups remain unknown.
Separate root-owned ancestors prevent replacement by worker/tenant identities.
The operational helper additionally checks kernel mount facts and admits only local
ext4/xfs/btrfs read/write served/journal filesystems; unknown, network and overlay
mounts deny. The isolated component harness does not use this operational admission.
Publisher owns served/journal descendants; Apache has only explicit read/traverse.
Preprovision releases/assets at 0750, private staging at 0700, sealed public files
0440/directories 0550 with the verified Apache group, private journal/lock at 0600.
Missing initial lock/journal is unknown, not an invitation to initialize a target.

Authority credentials are separate from worker-readable host receipts/config. Pin the
actual service files and all transitive PHP includes; install no arbitrary commands,
sudo policy or application wrapper. The authority service is only a committed-state
reader. Routine site operations do not invoke `ubo-stage-deploy`, `ubo-stage-migrate`,
`deploy-staging.sh`, DomainManager, config reload or any provider API.

Keep production denied throughout M6E. Installed capability, isolated acceptance,
working-staging operation and production approval are distinct later gates. Stop
this PR at local implementation and exact-head review handoff.

## Authored transfer-only identity observations

**AUTHORED / NOT EXECUTED.** The existing
Earlier transfer-only component evidence remains available through
[WebsitePlatformM6EIdentityDriver.py](../tests/support/WebsitePlatformM6EIdentityDriver.py).
It records nested transfer observations, separate from the new full driver loop.
It cannot satisfy remaining installed integration requirements. Missing separate-identity admission is an
explicit INCOMPLETE result. Neither this module, its agents nor the isolated harness
(including inventory mode) was executed during the desktop task.

The driver requires a separately approved disposable Linux fixture with five distinct
non-root UIDs: coordinator, worker, helper, authority and Apache reader. It does not
create accounts, switch UID, grant permissions, install/start services or connect to
SQL. A separately approved launcher must start each role's pinned agent with its own
prebound listening Unix socket FD as stdin and `--serve`; the listener must have been
created by that role so SO_PEERCRED identifies it. Socket paths are fixed at
`/run/ubo-m6e-identity/<role>/probe.sock`, with role-owned nonwritable ancestors and
no other-user socket permissions. Any necessary dedicated socket access group is
limited to fixture connection access, never artifact access. No wildcard/path RPC.

A root-owned `/run/ubo-m6e-identity/admission.json` (at most 65,536 bytes) has exact
keys `profile`, `source_sha`, `expires_at`, `coordinator_uid`, `identities`,
`fixture_root`, `bindings`, `probes`, `installed_files`. Profile is
`m6e-transfer-identities-v1`; expiry is at most 600 seconds away. `identities` maps
worker/helper/authority/Apache to distinct actual UIDs. Root is a unique
`/srv/ubo-m6e-identity/<token>`; the `valid` and `interrupted` cases have separate
precreated served/journal roots, empty release/asset/staging directories, target locks
and pristine initial state. Every binding has the ordinary site/target/site-key/
binding-key/version plus fixed served/journal roots. Source SHA must equal the
coordinator's verified checkout; installed_files pins the driver, PHP identity child
and actual helper module. All role code/ancestors must satisfy protected-file checks.

Worker and authority have separate root-owned, role-readable 0640 snapshots at
`/run/ubo-m6e-identity/worker.json` and `authority.json`. Each uses
`profile=m6e-identity-rows-v1`, its actual uid, expiry and `cases` keyed by valid/
interrupted. Each case contains independent `build_tables`, `deployment_tables`,
`sites`, `now`, `binding`; worker additionally has the exact execution `lease`, and
its top-level snapshot has `artifact_work_root`/`artifact_root`. These are real M6C
fixture rows and already built original 0700/0600 files, prepared separately before
running the driver. No record supplied here is a passed gate or operator boolean.
The worker ID remains the controlled PDO fixture's `m6d-fixture`. The authority
snapshot must be independently protected and consistent with the original producer,
committed release hashes, lease-token hash and fresh stage permission; the helper
obtains it only from the distinct authority process, never the transfer sender.

[WebsitePlatformM6EIdentityChild.php](../tests/support/WebsitePlatformM6EIdentityChild.php)
runs actual scoped exporter reads and session serialization under the worker UID.
The helper agent runs actual peer admission, descriptor-relative Tree, flock, fence,
receiver, package verifier and atomic staging. The authority agent runs actual PHP
`SitePublisherAuthority::observe` in a completed controlled PDO transaction, under
its separate UID. This is deliberately a PDO fixture, not native SQL or installed
SELECT-only credential acceptance. The private worker-to-helper channel transports
actual original artifact bytes. A bounded relay cuts the interrupted case after
three bytes of the first data frame; it cannot fabricate an end record or receipt.

`probes` fixes source/served/journal/public probe paths under the fixture root. Source
must be the valid release's exact original metadata/manifest.json, enforced by the
worker child. Actual open/fstat results must show source 0600 under 0700, EACCES to
helper/authority/Apache, EACCES for worker writes to helper served/journal probes,
helper write access to those dedicated probes, and Apache read access only to the
approved public probe (journal denied). Write probes open without truncate and do
not modify bytes. SO_PEERCRED verifies all role connections. A coordinator UID
attempting stage is rejected with no inventory change. Valid transfer must produce
a candidate but no current pointer; interrupted transfer must produce neither and
leave no published files. Normal EOF cleanup is tested; no SIGKILL/finally or
power-loss claim is made. Receipts, observed file inventories and permission errno/
mode/UID evidence are recorded; private rows, tokens and bytes are not reports.

Further installed authority revocation/concurrency, systemd hardening, real HTTP/TLS,
provisioning/config failure, crash durability and outcome-settlement observations
remain required. The fixed overall 13-case inventory is unchanged.
