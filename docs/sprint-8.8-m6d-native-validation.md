# Sprint 8.8 M6D — DB-only native validation

**AUTHORED / NOT EXECUTED.** No MySQL, migration, container, image, host service,
publisher activation or browser was run while authoring this harness. No runtime
image ID has been selected or invented. Local synthetic controls do not satisfy this gate.

The distinct profile is `m6d-db-authority-v3`. Its exact **29 cases / 146 named subcases** are owned by
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
| `/run/ubo-m6d/EXECUTE` | root, 0644; exact `RUN_TOKEN:m6d-db-authority-v3`, created only after execution authorization |
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

### Finite expansion map at the reviewed queue-correction baseline

The expansion starts at `926027c772004a4cd2e223a4b2242c4d21855283` and changes
test infrastructure only. Its obligations are **AUTHORED / NOT EXECUTED**; the
historical 490 behavior/control + 58 scope assertions belong to that earlier head.

| Package / requirements | Required executable observations |
| --- | --- |
| A: D04–D06, D13, U01/U02 | Requester, grant approver and grant revocation, denial-first/work-first at claim, activation authorization and new-success acceptance; each order has its own independent connection wait and committed-boundary observation. Distinct sites, environments and occupied-target progress retain scoped slot/fence/receipt authority. |
| B: D19–D26 | Both APIs: failed, cancelled and reconciliation history; discarded request response; controlled duplicate-insert rollback followed by independent winner creation and pending/advanced reread; current access loss. Existing field and A/B/C cases remain mandatory. |
| C: D02–D04, D09–D12, D17, R01–R06, C01/C04–C06/C08–C13 | Exact grant bindings and phase denial; real-service known-good restore plus current prohibitions; modeled health and compensation with independent DB consequences; third execution, automatic recovery exhaustion and stale-owner protection. |
| D: S02/S03, U01/U02 | Exact named deployment constraints, scoped uniqueness, valid recovery after execution three, retained operator history/SET NULL and current authority, bounded queue/history index access. Credited M6B clean/upgrade schema evidence is not rerun. |

Subcases are part of the versioned report inventory, not optional narrative. The
original committed-ack-history remains an exception injected after successful PDO
commit; discarded request responses are a separate boundary. Neither establishes
wire-level loss. The v3 transport addition below authors that distinct mechanism;
M6C transport evidence is not credited as execution of an M6D result transaction.

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

The first 18 parent case meanings remain mandatory. Payload-field cases now report
each original-field mutation individually. The following exact subcase inventory is
part of v3 acceptance; `service-observations` denotes a retained original case.

| Parent case | Exact required subcases |
| --- | --- |
| `publish-request-history` | `service-observations` |
| `restore-request-history` | `service-observations` |
| `publish-payload-fields` | `release`, `operation`, `approval`, `binding`, `pointer`, `version`, `reason` |
| `restore-payload-fields` | `release`, `operation`, `restore-source`, `approval`, `binding`, `pointer`, `version`, `reason` |
| `scoped-current-access` | `service-observations` |
| `concurrent-request-identity` | `service-observations` |
| `concurrent-target-claims` | `service-observations` |
| `result-audit-rollback` | `service-observations` |
| `production-grant-boundaries` | `service-observations` |
| `production-role-lock` | `service-observations` |
| `staging-production-lifecycle` | `service-observations` |
| `known-good-restore` | `service-observations` |
| `stale-lease-fence` | `service-observations` |
| `third-execution-recovery` | `service-observations` |
| `recovery-budget-operator` | `service-observations` |
| `committed-ack-history` | `service-observations` |
| `unavailable-result-no-compensation` | `service-observations` |
| `deleted-recovery-actor` | `service-observations` |
| `authority-boundary-orders` | `requester-claim-denial-first`, `requester-claim-work-first`, `requester-activation-denial-first`, `requester-activation-work-first`, `requester-success-denial-first`, `requester-success-work-first`, `approver-claim-denial-first`, `approver-claim-work-first`, `approver-activation-denial-first`, `approver-activation-work-first`, `approver-success-denial-first`, `approver-success-work-first`, `grant-claim-denial-first`, `grant-claim-work-first`, `grant-activation-denial-first`, `grant-activation-work-first`, `grant-success-denial-first`, `grant-success-work-first` |
| `independent-targets` | `different-sites`, `same-site-environments`, `occupied-target-progress` |
| `publish-request-outcomes` | `failed-history`, `cancelled-history`, `reconciliation-history`, `lost-response`, `duplicate-winner-pending`, `duplicate-winner-advanced`, `access-loss` |
| `restore-request-outcomes` | `failed-history`, `cancelled-history`, `reconciliation-history`, `lost-response`, `duplicate-winner-pending`, `duplicate-winner-advanced`, `access-loss` |
| `grant-field-bindings` | `site`, `release`, `source`, `artifact`, `target`, `environment`, `operation`, `binding`, `expected-pointer`, `expected-version`, `missing`, `consumed-history` |
| `grant-phase-denials` | `expiry-claim`, `expiry-activation`, `expiry-success`, `revocation-claim`, `revocation-activation`, `revocation-success`, `approver-claim`, `approver-activation`, `approver-success` |
| `restore-eligibility` | `never-successful`, `foreign-site`, `wrong-target`, `artifact-identity`, `tenant-prohibited`, `business-prohibited`, `asset-rights`, `target-disabled`, `superseded-approval`, `production-fresh-grant` |
| `health-compensation` | `candidate-failure`, `active-health-failure`, `third-null-absence`, `compensation-failed`, `compensation-unknown`, `newer-winner`, `activation-audit-grant`, `result-audit-grant`, `third-grant-expired`, `third-grant-revoked`, `health-status`, `health-site`, `health-release`, `health-artifact`, `health-marker`, `health-page`, `health-css`, `health-media`, `health-origin`, `health-tls`, `health-redirect`, `health-cache`, `health-no-store`, `health-timeout` |
| `deployment-schema` | `target-owner`, `release-owner`, `expected-target`, `restore-target`, `current-target`, `active-target`, `request-unique`, `attempt-parent`, `attempt-original`, `current-attempt`, `execution-four`, `execution-shape`, `recovery-shape`, `counter-shape`, `actor-fk`, `history-restrict`, `health-attempt`, `grant-consumption` |
| `deployment-history-access` | `operator-deletion`, `queue-index`, `history-index` |
| `result-commit-transport` | `unarmed-success`, `commit-not-delivered`, `ack-withheld`, `authoritative-unavailable` |

### Deployment-result transport addition — authored, not executed

Starting head: `b0cda17699ed862301738d776308c244f0dbe636`; its exact-head review
completed with no findings. The original **28 / 142 / 22** inventory remains intact.
V3 adds four transport subcases, requiring **five relay/worker legs and five fresh
direct authoritative observations** in total. These are additional structured
requirements, separate from the retained 22 ordering/progress observations. A v2
report or a narrative assertion about a disconnect cannot pass v3.

The definitions-only [M6CSupplementWire](../tests/support/WebsitePlatformM6CSupplementWire.php)
is reused byte-for-byte. Its two exact COM_QUERY forms (`COMMIT` and `COMMIT `),
original packet forwarding, framing/sequence bounds, one-arm/one-fault rules,
unsupported TLS/compression/query-attribute rejection and strict server OK validation
are unchanged. The M6C executable proxy, context, profiles and accepted cases are
untouched. M6D does not manufacture `/run/m6c/context.json` or use a second SQL parser.

The new [relay/control adapter](../tests/support/WebsitePlatformM6DNativeWire.php),
[executable relay](../tests/support/WebsitePlatformM6DNativeRelay.php) and
[worker](../tests/support/WebsitePlatformM6DNativeWireWorker.php) independently require
the protected M6D Linux/UID/source/image/run admission and the v3 EXECUTE receipt
before credentials, sockets or SQL. The relay binds `127.0.0.1:0` only inside that
future disposable namespace, accepts one connection after coordinator release, closes
the listener, and connects only to the admitted `m6d-db-FIRST16:3306`. There is no
host-published port, arbitrary destination, DSN option, persistent PDO or extra grant.

LISTEN travels over the exact tracked child's owned control channel and must match
its PID plus the current run, subcase, leg, site, target, deployment, attempt and fresh
channel nonce. The coordinator writes an exclusive UID-2000/0600 endpoint record;
the worker compares that record with its pipe-delivered endpoint and checks relay
liveness. Both children verify their coordinator parent. A port alone is insufficient.
The isolated namespace and reviewed source remain prerequisites; this is not a proxy
for arbitrary or mutually untrusted same-UID processes. A stolen first connection
prevents the intended worker from completing its required verified READY sequence.

The worker independently queries database, CURRENT_USER account, server UUID,
hostname and MySQL 8.4 version through the relay. All must match admission. The
normal direct connection and cleanup connection implementations are unchanged.
Additional preparation prerequisite: the disposable server/account must permit the
reviewed inspectable, uncompressed mysqlnd connection on its private test network.
Unsupported capabilities fail closed; this task does not change server security
configuration or weaken operational TLS. No image/server identity is invented.

The [test-only PDO decorator](../tests/support/WebsitePlatformM6DNativeWireConnection.php)
recognizes the actual successful `site_deployment_succeeded` audit insert and exact
site/target/deployment/execution-attempt metadata. Only the final inner PDO commit
for that transaction reaches COMMIT. Before emitting the barrier, an acknowledged
text-protocol `DO 0` drains any prior prepared-statement close packets without leaving
another statement handle. That fixed no-op changes no fixture state and occurs while
unarmed. The worker pauses there, the coordinator arms
the owned relay and receives ARMED, then releases that same worker. The next operation
is the real inner `PDO::commit()`; no SQL probe or `exec('COMMIT')` substitution occurs.
The synthetic post-success-PDO exception is absent from this decorator. Earlier
request/claim/activation/grant/heartbeat commits pass unarmed.

| Subcase | Required future native observation |
| --- | --- |
| `unarmed-success` | Same route and selected result boundary with no ARM/FAULT; service returns succeeded, PDO commit returns true, fresh direct connection confirms result/pointer/lifecycle/health/audit/slot. |
| `commit-not-delivered` | Execution three reaches result COMMIT; ARMED/FAULT prove command not forwarded and no OK received. Actual PDO loss and service database_failure are retained. Fresh direct state matches the pre-commit snapshot, including already committed probe renewals, activation and consumed grant; no success/pointer/active health is fabricated. Existing recovery finalizes the prior model effects at counts 4/3/1, retains the producer and automatic budget, rejects stale authority, and stages/activates no new candidate. |
| `ack-withheld` | Execution-three COMMIT bytes forward unchanged; actual server OK is validated and suppressed. PDO does not return success. Fresh direct state establishes the atomic successful result before replay. Exact replay has no DB/model changes; a later service-created winner plus grant revocation/requester deactivation preserves historical success, denies the inactive history reader and permits the current authorized reader. |
| `authoritative-unavailable` | First uses the acknowledgement-withheld result leg and a fresh direct observation. After both children exit, a new relay/worker arms drop-query immediately before executing the service's already-prepared, exact site-lock read on the authoritative reread connection. The intercepted read and PDO failure are observed; service returns no success. A new direct connection confirms unchanged committed history/model and budgets before normal service replay resolves that same outcome. Coordinator knowledge is not substituted for the unavailable worker's result. |

All four fixtures use three real service execution claims with two controlled
transient failures; no execution counter is set to three. The publisher/artifact and
health effects remain the existing explicit models. No physical site switch, rollback,
HTTP health or helper behavior is claimed. `committed-ack-history`, discarded request
responses and unavailable-PDO injection retain their separate evidence meanings.

[WireEvidence](../tests/support/WebsitePlatformM6DNativeWireEvidence.php) requires exact
per-leg bindings, ordered barriers, endpoint/process/connection identities, forwarding
and acknowledgement fields, actual service classification, exits/signals, final barriers,
model effect counts and fresh authoritative outcome before replay. Missing, duplicate,
wrong-case/run or foreign observations, early EOF, malformed/error acknowledgements,
unobserved COMMIT, absent authoritative evidence and unexpected service failures block
PASS. An escaped rollback PDO error is recorded but fails the required service
classification; the harness does not relabel it a successful/unknown result or repair
production. Parser recognition alone is never treated as commit proof.

Children register immediately at spawn, before the first read. Safe phase/failure
context survives cleanup; unknown exits become null while signals remain separate.
At most worker+relay are active. Listener/client/backend resources close on faults and
early exits; exact tracked children must be reaped before schema cleanup. Primary
failure, child cleanup, schema cleanup, publication and later host capture remain
separate. Existing 600/45/10/3-second, memory/PID/tmpfs/output/report limits remain.
The expanded total duration is **UNVERIFIED**, not a demonstrated timeout.

Locally executed [wire regressions](../tests/WebsitePlatformM6DNativeWireTest.php) use
the actual parser/queue/boundary/contract helpers with synthetic packets, memory
streams, fake PDO and bounded synthetic children. They do not open a listener or TCP
connection. Final results are recorded in the [implementation record](sprint-8.8-m6d-local-implementation.md#deployment-result-transport-authoring).
Native mysqlnd/MySQL transport behavior, rollback/error classification and execution
timing remain unexecuted. No further transport mechanism is deliberately deferred;
that is source coverage, not native acceptance. M6D remains pending validation and acceptance.

The fixture uses real services/native PDO and the actual schema. Artifact verification,
publisher observations, health, fences and compensation are explicit test models;
shared private JSON records represent an independent external pointer. No symlink,
Apache, HTTP or production artifact is created. The simulated production profile is
defined only in test dependencies. Direct synthetic seed/profile/expiry changes are
fixtures, not coverage of customer approval UI, elapsed clocks or operational readiness.

Local [service suites](sprint-8.8-m6d-local-implementation.md#executed-local-evidence) additionally
parameterize grant expiry/revocation/role loss at claim/activation/success, failed and
unknown replay, malformed health, NULL compensation, authority/tenant/rights denials,
and model failures. The finite A–D expansion above now supplies executable native
obligations for those documented DB-only gaps. **Authoring does not close native
acceptance.** Real MySQL behavior and total run duration remain unverified; this is
not an all-D/R/C/S/U or M6E–M6G acceptance claim.

Exact grant-field cases call the actual grant owner under a site transaction with
authoritative existing alternative records; they isolate individual tuple fields.
The source/release alternatives use a second same-site approved synthetic revision
and service-created release. Artifact-identity corruption is an explicit model fault.
Separate phase cases exercise full request/claim/activation/result paths. The restore
rights case seeds an owned asset before building and establishing known-good history,
then prohibits its rights. It never changes the stored successful deployment outcome.

Both authorization orders use actual row locks: denial-first waits before the exact
boundary transaction; work-first holds its final audit/commit, observes the mutation
waiting, then independently observes the committed boundary before releasing further
work. Claim, activation authorization and success have distinct barriers. Eighteen
new authority orders, the three retained waits and one distinct-site progress case
give **22 separately required observations**. Same-site environments may serialize
on the site lock; the harness does not demand lock-free same-site work.

Duplicate-key testing explicitly injects error 1062 at the losing insert. It observes
the real PDO rollback before a second service connection creates (and optionally
advances) the winner, then releases the loser to its authoritative reread. Natural
service serialization is retained separately. Discarding a service-request return is
request-response loss only. Neither mechanism is wire-level COMMIT loss.

Schema negatives require the exact SQLSTATE, driver number and named constraint;
the raw server message and SQL are never published. MySQL documents CHECK violations
as 3819/HY000 in its [error reference](https://dev.mysql.com/doc/mysql-errors/8.0/en/server-error-reference.html).
Query cases seed 32 service requests, check index columns and `possible_keys`, and
execute bounded queue/history reads. They record a plan digest and selected access,
without requiring costs or a tiny-table scan choice; `possible_keys` is the documented
[usable-index field](https://dev.mysql.com/doc/refman/8.4/en/explain-output.html).
The accepted [M6B schema evidence](sprint-8.8-m6b-closeout.md) remains credited for
clean/upgrade schema and previously covered structural checks; no fresh DDL campaign
is added. Fixture retirement occurs only after observations and is never counted as
proof of service cancellation.

### Local harness proof and remaining execution requirements

Historical results at `b0cda176`, before the v3 addition: on Windows/PHP 8.4.24,
report controls and harness regressions exercised the v2 contract, protocol/barrier
helpers, PDO instrumentation and failure controls.
Ten affected suites pass: **1,564 behavior/control + 65 scope = 1,629 assertions**.
NativeControl contributes 1,045 synthetic report/admission checks; NativeHarness
contributes 92 checks using real bounded local children/private files and modeled PDO.
Queue/Execution/Request/Authority/Recovery/Artifact/CLI retain 90/63/70/96/60/4/44.
Expected negative diagnostics are classified per suite, not suppressed or required
to be empty. Actual commands, exits, durations and separate rechecks are outside Git.
All 14 changed PHP files pass lint; documentation and source integrity are checked.

No production code changed from `926027c`; the reviewed queue correction remains
byte-identical. All 25 migrations/pins, M6C artifact/native behavior, all 77 requirement
definitions and approved browser allocation remain unchanged. No shared M6B/M6C
dependency changed, so no unrelated historical campaign was repeated. The historical
M6B scope-perimeter failure remains disclosed and is not counted as PASS.

Every existing resource bound is unchanged, including the 600-second coordinator,
45-second children, ten-second waits, two-child cap, memory/PID/tmpfs and output sizes.
The larger inventory has bounded loops and fresh isolated fixtures, but **whether the
complete run fits 600 seconds is unverified**. A deadline failure remains failure;
limits must not be raised or cases skipped to obtain PASS. Preparation, exact-source
review, protected admission and execution still require separate authorization.

At b0cda, D11/C09 wire-level result acknowledgement loss remained a mechanism gap.
The v3 addition above now authors it independently of the retained post-commit API
exception. M6C transport evidence is not proof of an M6D transaction. Actual
HTTP/Apache/helper/filesystem activation and fencing remain outside the DB-only lane.
No production defect was demonstrated by local controls; native implementation,
driver and constraint behavior remain unverified until execution.

### PR #130 bounded queue-progress obligation — NOT EXECUTED

The existing publish/restore request-history cases now create A/B against one pointer
through the service, complete A, request C against the advanced pointer, then claim C.
The independent observer connection must see B cancelled for `expected_pointer_changed`,
zero B attempts, immutable B intent/counters, unchanged A success/pointer/version and
only C's new fence. Repeated scanning plus authorized B replay must leave exactly one
cancellation audit; the independent publisher-model file must remain unchanged during
claim/replay. C then completes through the normal service path.

No terminal outcome is planted with SQL. These additions extend existing declared
case meanings; v2 retains all 18 original cases and all three original
observed lock waits while adding the exact subcases above. `production-role-lock` retains its real wait requirement and now
expects cancellation rather than an exception from the entire scan. The local control
test still rejects incomplete reports. No new native launcher, image, connection or
execution was introduced. See the [local correction record](sprint-8.8-m6d-local-implementation.md#pr-130-queue-starvation-correction)
for modeled evidence; these authored native assertions do not constitute native PASS.

## Failure, cleanup and report rules

The report starts FAIL with every exact case and subcase UNREACHED. Each subcase needs
positive assertions, each parent total must equal its subcase sum, and each of the 22
ordering/progress observations must identify distinct connections and completed order.
No global wait flag can credit another race. Missing, extra, duplicate, wrong-version,
partial, FAIL or unobserved results cannot satisfy acceptance. Published JSON has one
canonical pretty encoding; `M6DNativeContract::acceptPublished` verifies that encoding
(rejecting duplicate keys), the exact source/image/run identity, and complete v3 results,
including the four structured transport subcases and successful child/schema cleanup.
Missing/unavailable capabilities, worker errors, unresolved waits and incomplete
inventory cannot become SKIP/PASS. Diagnostics retain exception class/fixed code and
PDO SQLSTATE/driver number, never SQL text, connection credentials, tokens or bodies.

Primary failure with case/subcase context, worker exits, capture, child/schema cleanup
and publication remain separate. The
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
