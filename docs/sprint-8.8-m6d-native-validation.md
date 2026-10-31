# Sprint 8.8 M6D — DB-only native validation

**AUTHORED / NOT EXECUTED.** No MySQL, migration, container, image, host service,
publisher activation or browser was run while authoring this harness. No runtime
image ID has been selected or invented. Local synthetic controls do not satisfy this gate.

The distinct profile is `m6d-db-authority-v1`. Its exact 18-case inventory is owned by
[M6DNativeContract](../tests/support/WebsitePlatformM6DNativeContract.php).
An M6B, core20, journal3 or supplement17 report cannot satisfy it. The existing M6C
native profiles and their separate accepted source attribution remain unchanged.

## Boundaries and prerequisites

The [coordinator](../tests/WebsitePlatformM6DNative.php) is deliberately not named
`*Test.php`. Help returns before any dependency/configuration bootstrap. Admission
checks Linux, UID 2000, PHP/PDO/pcntl capability, source/image/profile/token binding,
protected configuration, exact hostname, no privileges, actual cgroup limits and a
bounded private tmpfs. Missing capability fails; there is no fake-PDO fallback.
Admission connects no database. Git verification uses bounded child capture files;
it does not write the repository or application configuration.

A later, separately authorized preparer must supply a disposable MySQL 8.4 instance
and a read-only exact reviewed checkout. Apply the unchanged canonical schema only
inside that disposable fixture during that separate preparation; the M6D coordinator
does not run migrations. It refuses nonempty sites/users/businesses/jobs/deployments.
Do not use the working-staging or production database, credentials, network or mounts.

The isolated runtime requires a verified image containing the installed PHP extensions,
Git and the schema's dependencies. It requires a dedicated internal network shared
only with its disposable MySQL instance. No published ports, operational credentials,
Docker socket, host application mounts or site publishing helpers belong in it.
The root-owned admission receipt is the trusted preparer's attestation of the inspected
image and internal network. The worker cannot independently inspect an image daemon;
it verifies receipt ownership, actual namespace/resource observations and source Git state.
That trust boundary must be reviewed before execution.

Required resource policy: 600-second coordinator deadline; 45-second native children;
10-second MySQL lock-wait and observation deadlines; 3-second PDO connection timeout;
PHP memory 256 MiB, cgroup memory 512 MiB, PID cap 32, state tmpfs 128 MiB. Child output
is bounded to 64 KiB per stream, publisher model files to 64 KiB, fixture records to
8 KiB and the report to 1 MiB. At most two instrumented children run concurrently.
The external preparer must enforce a final namespace deadline and remove only its
owned namespace after capturing evidence, including if the coordinator is killed.

The preparer installs these fixed paths; no CLI/environment path override exists:

| Path | Ownership / purpose |
| --- | --- |
| `/run/ubo-m6d/admission.json` | root, 0644, one regular link; exact fields below |
| `/run/ubo-m6d/database.json` | UID 2000, 0600; only `password`, 32–128 characters |
| `/run/ubo-m6d/EXECUTE` | root, 0644; exact `RUN_TOKEN:m6d-db-authority-v1`, created only after execution authorization |
| `/run/ubo-m6d/state` | UID 2000, 0700, 128 MiB tmpfs; synthetic external pointer records |
| `/run/ubo-m6d/reports` | UID 2000, 0700; new exclusive report output |

Admission fields are exactly `profile`, `source_sha`, `image_id`, `run_token`,
`database_name`, `database_user`, `database_hostname`, `runtime_hostname`,
`mysql_server_uuid`, `worker_uid`, `deadline_seconds`, `memory_bytes`, `pids`,
`storage_bytes`, `network_isolated`. The profile is the constant above; source is a
40-character SHA; image is an inspected `sha256:` identity; token is 32 lowercase hex.
Database is `ubo_m6d_TOKEN`, user is `m6d_FIRST16`, DB hostname is `m6d-db-FIRST16`,
runtime hostname is `m6d-run-FIRST16`. The server UUID comes from that disposable
instance. The remaining values are 2000, 600, 536870912, 32, 134217728 and true.
Parent directories are root-owned and not group/world writable. The repository must
be an actual read-only mount. Never fill these fields with guessed image/server values.

The test account must have fixture DML plus DROP on its single exact disposable
schema, and read-only `performance_schema.data_lock_waits` / `threads` access for
observable independent-connection waits. It must not have operational-schema access.
The coordinator independently checks database, account, hostname, UUID and MySQL 8.4
on every connection, including cleanup. Existing migration pins are not changed.

## Commands for later review — NOT RUN

Substitute only values verified by the authorized preparer. These are templates, not
executed evidence or authorization to prepare a droplet/container now.

```text
php -d memory_limit=256M tests/WebsitePlatformM6DNative.php --admit REVIEWED_SHA REVIEWED_IMAGE_ID RUN_TOKEN
php -d memory_limit=256M tests/WebsitePlatformM6DNative.php --run REVIEWED_SHA REVIEWED_IMAGE_ID RUN_TOKEN
```

First inspect help/admission and preserve their actual exits. Admission PASS means
prerequisites only. Review the exact source, image, resource/network receipt, schema
and cleanup scope; authorize execution separately and only then create `EXECUTE`.
Run once under the external deadline. Preserve stdout/stderr, actual exits/signals,
the exact receipt and `reports/report.json` plus its emitted SHA-256. Do not discard a
failed run or combine results from different source/image/token identities.

## Exact case inventory and evidence layers

| Case | Plan requirements / authored proof |
| --- | --- |
| publish-request-history | D19–D22, D24 and D05 queue progress; current access, pending/history, A/B/C stale-intent cancellation and later pointer |
| restore-request-history | Same request-history and A/B/C queue-progress contract for restore |
| publish-payload-fields | D23; independent original field conflicts, operation difference |
| restore-payload-fields | D23; restore source and other original fields independently |
| scoped-current-access | D24, U01/U02; customer/foreign target and revoked actor denial |
| concurrent-request-identity | D25; independent PDO processes, observed lock wait, one intent |
| concurrent-target-claims | D05, S02; observed competing claim wait and one owner |
| result-audit-rollback | D12; real MySQL rollback with injected audit failure and recovery |
| production-grant-boundaries | D02–D04, D22; simulated production grant/consumption/revoked history |
| production-role-lock | D04, U01; observed approver/requester revocation-before-claim wait, durable cancelled intent and zero attempts |
| staging-production-lifecycle | D15; staging/global isolation and simulated production owner commit |
| known-good-restore | R01/R03; new operation over immutable historical release/old approvals |
| stale-lease-fence | D13, C04/C06; replacement authority rejects old renewal |
| third-execution-recovery | C08; actual three executions then recovery four; no counter reset |
| recovery-budget-operator | C11/C12; crashed recovery, two-auto limit, explicit operator replay |
| committed-ack-history | D11, C09; real committed transaction followed by injected API acknowledgement failure; later pointer |
| unavailable-result-no-compensation | C09; injected connection unavailability prevents model effects |
| deleted-recovery-actor | S03, U01/U02; real SET NULL preserves history and denies deleted operator |

The fixture uses real services/native PDO and the actual schema. Artifact verification,
publisher observations, health, fences and compensation are explicit test models;
shared private JSON records represent an independent external pointer. No symlink,
Apache, HTTP or production artifact is created. The simulated production profile is
defined only in test dependencies. Direct synthetic seed/profile/expiry changes are
fixtures, not coverage of customer approval UI, elapsed clocks or operational readiness.

Local [service suites](sprint-8.8-m6d-local-implementation.md#executed-local-evidence) additionally
parameterize grant expiry/revocation/role loss at claim/activation/success, failed and
unknown replay, malformed health, NULL compensation, authority/tenant/rights denials,
and model failures. Broader real-MySQL permutations remain mandatory review/native
obligations; this first 18-case harness is not an all-D/R/C/S/U acceptance claim.
In particular, reverse authorization serialization, all health/compensation fault
permutations and complete deployment negative CHECK/FK fixtures need expanded native
coverage before formal M6D acceptance. These are explicit validation infrastructure
gaps, not results inferred from the sequential local fixture.

### PR #130 bounded queue-progress obligation — NOT EXECUTED

The existing publish/restore request-history cases now create A/B against one pointer
through the service, complete A, request C against the advanced pointer, then claim C.
The independent observer connection must see B cancelled for `expected_pointer_changed`,
zero B attempts, immutable B intent/counters, unchanged A success/pointer/version and
only C's new fence. Repeated scanning plus authorized B replay must leave exactly one
cancellation audit; the independent publisher-model file must remain unchanged during
claim/replay. C then completes through the normal service path.

No terminal outcome is planted with SQL. These additions extend existing declared
case meanings; the profile still requires exactly all 18 cases and all three original
observed lock waits. `production-role-lock` retains its real wait requirement and now
expects cancellation rather than an exception from the entire scan. The local control
test still rejects incomplete reports. No new native launcher, image, connection or
execution was introduced. See the [local correction record](sprint-8.8-m6d-local-implementation.md#pr-130-queue-starvation-correction)
for modeled evidence; these authored native assertions do not constitute native PASS.

## Failure, cleanup and report rules

The report starts FAIL with every exact case UNREACHED. All cases need positive
assertion counts; the three concurrency cases also need observed MySQL lock waits.
Missing/unavailable capabilities, worker errors, unresolved waits and incomplete
inventory cannot become SKIP/PASS. Diagnostics retain exception class/fixed code and
PDO SQLSTATE/driver number, never SQL text, connection credentials, tokens or bodies.

Primary failure, capture, child/schema cleanup and publication remain separate. The
coordinator terminates/reaps its tracked children before schema cleanup; a surviving
child blocks cleanup. Once the empty baseline and exact ownership are admitted,
cleanup reconnects/rechecks identity and drops **only** `ubo_m6d_TOKEN`, verifying its
absence. A mismatch/non-owned baseline permits no DROP. This destructive action is
source code for the later explicitly authorized disposable run, not performed here.
State-file/tmpfs/namespace teardown is the external preparer's separately recorded
responsibility; the coordinator does not prune host files, images or volumes.

Report publication uses an exclusive pending file, bounded complete write, flush/fsync
and rename. A publication failure cannot erase the primary failure or become PASS.
The preparer must capture failed pending output and actual child exits before teardown.
No retry/cleanup daemon, service installation or host preparation is included.
