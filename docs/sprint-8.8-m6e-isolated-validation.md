# Sprint 8.8 M6E — Isolated Linux/Apache validation

**PARTIALLY IMPLEMENTED / NATIVE NOT EXECUTED.** The Windows implementation task does not authorize
installation, server preparation, Linux/Apache execution, SQL, service operations,
working-staging activation or deployment. This document is the bounded later path,
not a claim that a host contract exists. M6D’s closed native gate is not reopened.

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
