# M6C native build-only validation

HARNESS IMPLEMENTED LOCALLY / REVIEW REQUIRED.
NATIVE CONTAINER/MYSQL/PROTECTED-WORKER EXECUTION — NOT PERFORMED.

This is the operating procedure for the executable harness on PR #128, branch
`codex/sprint-8.8-m6c-artifact-builder`. The authoring baseline is
`96715eb35ac1ae2eb69c3a39a14ec009048128ad`. The protected application, worker,
renderer, artifact store, all 25 migrations and their integrity pins are unchanged.
The [implementation record](sprint-8.8-m6c-local-implementation.md) retains earlier
results separately. This guide does not authorize image preparation or a native run.

## Entries and evidence lanes

`bash tests/RunM6CNative.sh --help` prints usage without runtime bootstrap.
`--check-only` performs read-only admission and inspection; it creates no client
configuration, credentials, lock, evidence directory, network, container or SQL.
`--run` creates the isolated resources only after admission. Both modes require
`--expected-sha` (40 lowercase hex), `--mysql-digest`, `--runtime-image` and
`--php-base-digest` (each `sha256:` plus 64 lowercase hex), and `--php` (the real,
absolute, executable host PHP path, not a symlink). No arbitrary hostname or DSN
argument exists. Unknown arguments and incomplete identities fail closed.

`tests/WebsitePlatformM6CNative.php` is the internal coordinator. Its only execution
contract is `--container-run SHA TOKEN`, Linux, namespace UID 0, `/opt/ubo`, exact
runtime hostname, and root-owned launcher attestation. Its `--help` does not load
application configuration. `tests/support/WebsitePlatformM6CNativeWorker.php` is
an internal child; neither is named `*Test.php`. The only new discovery test is
`WebsitePlatformM6CNativeLauncherTest.php`, which uses pure contracts, fake engine
commands, disposable local Git fixtures and bounded synthetic PHP children.

Lane A, `protected`: a root coordinator starts `setpriv`, changes to UID/GID 2000,
clears groups, drops every capability including the bounding set, sets
`no-new-privs`, then the child uses `pcntl_exec` to replace itself with the actual
`scripts/site-build-worker.php`. PHP establishes the actual entry identity.
The production CLI/factory, SQL connection, builder, asset resolver, validator and
store execute unchanged. No reflection, server-variable substitution, source
rewriting or fake receipts occurs in that worker process. Four startup denials
exercise wrong UID, unsafe config mode, wrong checkout ownership and wrong SHA.
Setup restores only its own changed modes/owner/config between denials.

Lane B, `instrumented`: a test dependency adapter wires the private service slot
and forwards to the real builder, validator and store. Fresh claim and ordinary
construction remain in the same process. Explicit stdin/stdout barriers expose
committed claim, completed seal and verification points. Audit/result fault cases
add a PDO forwarding adapter: one final INSERT receives an invalid FK or CHECK
value; real MySQL rejects it, and the real service rolls back its transaction.
SQLSTATE and driver number are retained without raw SQL or driver messages.
This lane is not proof of unmodified worker startup.

Request setup uses the actual composition editor/manager and `requestBuild`.
The adapter obtains the exact clean SHA and `SiteBuildRuntime::identity`, so the
persisted input/toolchain identity is the same identity the real factory computes.
Users, internal roles, business/module association, site, immutable revision facts,
approved asset metadata/PNG bytes and approval rows are explicitly seeded synthetic
fixtures in the new database. This is not UI or approval-workflow coverage. No job,
attempt, successful release or success receipt is manually fabricated.

## Admission, ownership and containment

The fixed target is Linux `ubo-stage-app`, user `codex-validation`, UID 1000;
the local rootless socket is exactly `/run/user/1000/docker.sock`, owned by 1000.
An already active user Docker service must expose cgroup v2/systemd delegation
for CPU, memory and PIDs. The harness never starts/stops/reconfigures the daemon.
It rejects inherited Git/Docker/database/application configuration overrides.
Docker calls use an empty environment, explicit local socket and a private client
directory containing only a mode-0600 `config.json` with exactly `{}\n`.

The volume mount is `/mnt/ubo_stage_testdata`, UUID
`7d255792-0283-4773-b7a1-20596ed0d8fa`, a different device from `/`.
`codex-validation/{checkouts,tmp,evidence}` and their parent must be owned by 1000,
mode 0700, with no path substitution. Docker data must be under
`/mnt/ubo_stage_testdata/codex-validation/docker`, mode 0700 or 0710, group
`codex-validation`. The engine must use the verified classic `overlay2` or
`fuse-overlayfs` store. A separate containerd image store is an unverified storage
prerequisite and is rejected; this harness does not relocate it.

The independent non-web-root checkout must be under `checkouts`, at exactly the
expected SHA, clean, with no untracked **or ignored** files, no shared Git directory,
alternates, tracked symlinks or submodules. All canonical migration pins are checked
before export. The export includes only the exact commit and its current trees and
blobs, then creates an intentionally shallow checkout of that real commit. Deleted
historical files, ignored operational `env.php`, Git credentials/configuration,
backups, uploads and evidence are not exported. Raw file bytes are independently
hashed as Git blobs; executable modes, protected root ownership and the exact
25-migration inventory are checked before SQL. The production factory repeats its
own clean-source/path checks. The local synthetic Git test proves current-tree
identity and omission of ignored/deleted-history canaries, without using a server.

One random run token binds actual network/container IDs, exact image IDs, names,
labels, hostnames, inspected mounts/environment/commands and effective limits.
The bridge is internal, IPv4 private, with exactly the two run members, no published
ports, host namespaces, devices, extra hosts, broad bind mounts or socket mounts.
The runtime receives one read-only bind of the newly created private input folder.
MySQL receives no host mounts. Image-declared volumes must not become anonymous
volume attachments; unexpected mounts fail admission before SQL.

MySQL starts as namespace UID/GID 999, read-only root filesystem, all capabilities
dropped. Runtime setup starts as namespace UID 0 with only CHOWN, DAC_OVERRIDE,
KILL, SETUID, SETGID and SETPCAP. KILL is needed for the coordinator to terminate its
owned UID-2000 children. DAC_OVERRIDE lets the coordinator independently inspect
worker-owned mode-0700 artifact roots and apply explicit synthetic file faults.
These setup capabilities are all removed before each worker starts. Actual PID-1
UIDs and child capabilities are inspected. No host sudo, host user creation or
host `/etc` changes are involved. The fixed trusted config and ignored application
config exist only inside this disposable runtime.

Fresh, distinct 256-bit bootstrap and application passwords are written only into
private scratch input. The DB IP comes from the inspected exact container/network.
The server UUID is read from that container's `auto.cnf`; the initial SQL read must
match UUID, run hostname and MySQL 8.4 before any schema mutation. The schema must
not already exist and is exactly `ubo_m6c_<32-hex-token>`. Only then are canonical
migrations 001–025 applied to this new disposable schema. `m6c_worker` receives
SELECT/INSERT/UPDATE/DELETE on that schema only, without DDL/admin privileges.
Application connections use that account. `APP_ENV=staging` is a factory contract,
not the isolation proof. There is no connection or probe of `ubo_staging`.

## Resource and failure contract

| Resource | Admission and bound |
| --- | --- |
| CPUs | At least 4 host CPUs; MySQL 1 CPU, whole PHP/runtime cgroup 1 CPU. |
| Memory | Initial MemAvailable at least 4.25 GiB: DB 1.5 GiB + runtime 1 GiB + host-controller allowance 0.25 GiB + staging reserve 1.5 GiB. Stop if reserve falls below 1.5 GiB. |
| PIDs and swap | DB 128 PIDs, runtime 48; memory-swap equals memory, effective cgroup swap limit zero. |
| PHP | Coordinator and at most two direct children, each 128 MiB; all descendants share runtime CPU/memory/PID limits. |
| DB tmpfs | Data 1 GiB, socket directory 16 MiB, `/tmp` 16 MiB, charged against DB memory. |
| Runtime tmpfs | Checkout 256 MiB, combined work/sealed/uploads 256 MiB on one filesystem, report 16 MiB, `/tmp` 16 MiB; all charged against runtime memory. |
| Host disk | Root free floor 1 GiB; shared volume admission 10 GiB, running floor 4 GiB. Count shared Docker/evidence free space once. |
| Disk budget | Cached images combined at most 5 GiB; runtime writable layer at most 256 MiB; run scratch/evidence at most 640 MiB; bounded logs 2 × 1 MiB per container. Aggregate below 6 GiB, monitored rather than a disk quota. |
| Diagnostics | Source pack 64 MiB; native JSON 1 MiB; each child stream normally 64 KiB; source-index read 1 MiB; container local logs capped. Host worker stdout/stderr use a 1 MiB file limit. |
| Time | One 1200-second suite deadline, plus one 90-second cleanup/evidence deadline. Cleanup calls reserve eight seconds for final publication/failure diagnostics. Evidence preparation: at most 15 seconds plus 1-second kill grace; terminal commit: at most 4 + 1 seconds; one failure diagnostic write: at most 2 + 1 seconds, all clipped to that same deadline. Inspection/Git calls otherwise use 15 + 2 seconds. Worker children 90 seconds, barriers 15 seconds; SIGTERM escalates to SIGKILL within 4 seconds. |

The memory allowance for host controller utilities is admission accounting, not a
new host cgroup. Effective container CPU/memory/swap/PID limits and OOM/PID events
are checked; a mismatch, OOM, mount loss, output bound or floor failure stops work.
No limit is silently raised. Root-disk free space from earlier tasks is historical
and must be measured again by admission.

Host writes/cleanup use an open FD to the verified volume, so mount loss cannot
redirect them into the underlying boot-disk directory. A new lock directory prevents
overlapping harness runs. Cleanup requires the exact token, ID, name, role, image
and network binding; a lost create response is resolved by the previously absent
name and the same identity checks. Foreign/mismatched resources are retained with
cleanup FAIL. Nothing is pruned and old evidence is never swept.

The report is captured before teardown. Containers are stopped, actual final State
(exit code/OOM/finish state) recorded, then removed by exact ID. Explicit native
SIGKILL cases record the actual PHP child signal separately from container teardown.
The network must have no remaining members before removal. Scratch removal requires
the exact newly created path, device/inode/UID/mode; credential files are not copied
to evidence. If cleanup fails, retained resources may still contain test-only
credentials and need separately reviewed recovery, not broad pruning.

`status.txt` records `primary_exit`, `interruption_exit`, `cleanup`, `publication`
and `final_exit` independently, along with the source SHA. Final precedence is:
nonzero original operation result, otherwise the first accepted INT (130) or TERM
(143), otherwise cleanup/publication failure (2), otherwise zero. Signal handlers
only record the first signal. They never invoke cleanup or reset its deadline.

Signals remain accepted through container stop/inspection/removal, network/scratch
cleanup, provisional status preparation and substantive evidence hashing. Once
these have settled, `m6n_close_signals` executes the single `trap '' INT TERM`
terminal boundary. All signals handled before that builtin affect the final
decision; INT/TERM after it are ignored through the bounded commit and exit.
This includes signals received while a bounded evidence child is running: Bash
dispatches the parent's pending trap when the foreground command returns.

Before that boundary, payload `.txt`/`.json` files are hashed with status excluded.
After the boundary, the launcher refreshes the decision and regenerates the small
status file once, adds its finalized hash to `SHA256SUMS`, and atomically renames
`PUBLICATION` last. The receipt contains the manifest hash and the same outcome
fields as status and console. There is no repeated-signal rewrite loop or second
substantive payload-hashing pass. Provisional/pending files are not completion
evidence. A failed write/hash/rename/timeout fails publication; one bounded attempt
removes any receipt and writes diagnostic failure status. If even that write fails,
console/exit remain authoritative and the evidence is incomplete.

`publication=PASS` means the evidence was committed successfully. A valid failure
report can have publication PASS and a nonzero final exit. `native.json` remains
the coordinator's observed result, including native-case PASS when the launcher
was later interrupted. Native PASS requires all 20 named cases, the correct lane
for each, positive assertions, exact SHA/token and native=true; launcher success
also requires zero primary/interruption/final exits and cleanup/publication PASS.
Missing/malformed reports, failed capture, cleanup or publication cannot yield a
successful launcher exit. Completed, failed and unreached cases remain distinct.

### Review corrections and synthetic proof

The [fake-command return finding](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4320474823)
reported Bash 5.2 retaining a nonzero EXIT-trap status after a successful fake
inspection. Fake leaf commands now explicitly return their actual output/file/test
status; intentional failures remain nonzero. Lost-create recovery still requires
exact ownership. The absent-resource fallback explicitly returns zero only when
listing succeeds and is empty; failed inspection with a present or uncertain
resource fails cleanup. Fake files are synthetic markers, not Docker resources.

The [teardown interruption finding](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4320474831)
was reproduced locally against unchanged `648fd0d`: INT and TERM at runtime stop,
DB cleanup, network cleanup, status preparation, manifest hashing and receipt
publication all reached their handlers yet exited zero (12 probes). Original
status, manifests, receipts and handler observations are retained outside Git.
Git Bash 5.3.15's unchanged suite passed 292 assertions/25 scenarios; its nonzero
EXIT probe returned inspection status zero and verified-absence zero. That is not
a reproduction of the reviewer's Linux/Bash 5.2 failure. Bash 5.2 was unavailable;
no shell, WSL, Docker or other software was installed to obtain it.

The expanded suite uses the actual launcher functions with fake side-effect leaves,
private files and foreground Bash children. Bounded PID/signal/phase handshakes
deliver real INT/TERM only to the recorded synthetic child's PID. Handler logs,
first-signal preservation, unchanged cleanup deadline, single removals and both
sides of the terminal boundary are asserted, along with finalized manifest hashes,
receipt/status/console/exit agreement and failure injection. Fresh counts and
retained attempts appear in the implementation record. These tests establish no
actual Docker teardown, native Linux namespace, MySQL or protected-worker result.

## Authored native acceptance cases

All rows below are executable assertions, **not executed native results**.

| Cases | Lane and scope | M6 plan mapping |
| --- | --- | --- |
| protected-startup-denials; protected-multipage-build | A: actual factory rejection, then eligible three-page build, asset bytes, all hash layers, marker, navigation, inert forms, private canaries, release/two validations/audit. | B01, B13–B18 subsets. |
| protected-replay | A CLI no-work replay plus instrumented real-service exact request replay; immutable bytes/mtime, one release/attempt/event. | B11 matching replay; no B25 revocation/commit-loss claim. |
| concurrent-claims | B: two independent PHP/PDO processes released at a barrier, exactly one committed owner/render/release; completion replay. | B09 ownership subset; **not** B06 simultaneous request coverage. |
| requester-denied-after-claim; source-denied-after-claim | B: committed claim then requester deactivation or source lifecycle change, retained attempt/count, atomic failure audit, zero builder calls/output. | B04/B22/B23 pre-output subsets. |
| asset-loss-after-claim; asset-corruption-after-claim | B: real asset failure after committed claim, exact producer journal, quarantine and atomic integrity failure. | B08/B12/B15 subsets. |
| execution-verification-renewal; recovery-verification-renewal | B: forced real heartbeat callback with controlled short expiry; current lease extends, absolute deadline fixed, producer never revived. | B09 progress subset; not elapsed-cadence proof. |
| stale-completion | B: controlled expired metadata rejects completion, no release/validation/event or sealed-file overwrite. | B09 stale owner subset. |
| killed-before-journal; killed-after-seal | B: actual SIGKILL at committed claim or completed real seal; missing evidence blocks, sealed evidence remains available. | B08 crash points. |
| fresh-process-adoption | A recovery of the interrupted B producer: wait for **real** 120-second lease expiry and real 30-second automatic backoff using bounded DB-clock observations, then unchanged CLI adopts exact bytes. | B08 and C07 adoption mechanism only; first execution, **not** exhausted execution-three coverage. |
| missing-evidence; tampered-evidence; ambiguous-evidence | B: owned journal removal, sealed-byte mutation or pending journal; controlled expiry, blocked recovery with zero render/repair and no business success. | B08/B12 uncertain evidence subsets. |
| audit-rollback; validation-rollback | B: real FK/CHECK errors, SQLSTATE/driver, no partial release/validation/event, job transaction rollback, sealed files unchanged. | Build-result transaction mechanism; not deployment D12 or lost commit acknowledgment. |
| no-publication | B observer: no published revision pointer, deployment, target, health or production/conversion approval rows. Network/storage boundary excludes external provider effects. | Build-only boundary, not deployment acceptance. |

Controlled expiry cases explicitly rewrite only owned attempt lease metadata and
retain the original deadline; they do not claim elapsed time. Neither fsync nor
SIGKILL proves power-loss durability. B02–B07 full eligibility/request/retry matrices,
B10 golden identities, B12/B15 full malformed-input matrices, B19–B26 full requester
variants, C07 execution-limit and C09 lost-acknowledgment cases retain existing
local-only behavioral coverage or remain native-unexecuted. D/R/publication/provider
cases are outside this harness. No claim covers all 77 planned cases.

## Later preparation and operating commands — NOT RUN

The operator must separately authorize preparing images and the empty client
directory, and later authorize the native run. Required existing tools include
Bash, real host PHP with JSON/hash/filter, Git, Docker CLI, systemd user tooling,
findmnt and standard GNU coreutils. No software installation is done by the launcher.
The PHP runtime recipe requires a verified official Debian CLI PHP 8.4 or 8.5 base,
Git, util-linux `setpriv`, PDO MySQL/native prepares, DOM/libxml, mbstring, posix,
pcntl/pcntl_exec, filter, fileinfo, fsync, getimagesizefromstring and proc_open.

Commands below intentionally use required variables; unknown digests are not values
to guess. Prepare a clean independent checkout of the reviewed PR head in the fixed
volume checkout directory first. Verify official image provenance/digests and record
the built image ID, package versions and recipe/source SHA during separately
authorized image preparation. The recipe conditionally builds missing extensions
and does not copy the application or host configuration.

```bash
# NOT RUN. Separate authorized preparation, as codex-validation (UID 1000).
set -Eeuo pipefail
: "${WORK_SHA:?exact reviewed PR head required}"
: "${MYSQL_DIGEST:?verified official MySQL 8.4 sha256 digest required}"
: "${PHP_BASE_DIGEST:?verified official Debian PHP CLI sha256 digest required}"
CLIENT=/run/user/1000/ubo-m6c-client
test ! -e "$CLIENT"
mkdir -m 700 "$CLIENT"
(umask 077; printf '{}\n' > "$CLIENT/config.json")
DOCKER=(env -i PATH=/usr/bin:/bin HOME=/nonexistent docker
  --config "$CLIENT" --host unix:///run/user/1000/docker.sock)
"${DOCKER[@]}" pull --platform linux/amd64 "mysql@$MYSQL_DIGEST"
"${DOCKER[@]}" pull --platform linux/amd64 "php@$PHP_BASE_DIGEST"
"${DOCKER[@]}" build --pull=false --platform linux/amd64 \
  --build-arg "PHP_BASE=php@$PHP_BASE_DIGEST" \
  --build-arg "SOURCE_SHA=$WORK_SHA" \
  --tag "ubo-m6c-runtime:$WORK_SHA" tests/native/m6c
RUNTIME_IMAGE=$("${DOCKER[@]}" image inspect --format '{{.Id}}' "ubo-m6c-runtime:$WORK_SHA")
# Record/review RUNTIME_IMAGE and provenance; no native run occurs in preparation.
```

```bash
# NOT RUN. From the clean independent volume checkout, with daemon already active.
: "${HOST_PHP:?absolute real host PHP executable required}"
: "${RUNTIME_IMAGE:?reviewed locally cached sha256 image ID required}"
ARGS=(--expected-sha "$WORK_SHA" --mysql-digest "$MYSQL_DIGEST"
  --runtime-image "$RUNTIME_IMAGE" --php-base-digest "$PHP_BASE_DIGEST"
  --php "$HOST_PHP")
env -i PATH=/usr/bin:/bin HOME="$HOME" XDG_RUNTIME_DIR=/run/user/1000 \
  DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/1000/bus \
  bash tests/RunM6CNative.sh --check-only "${ARGS[@]}"
# Only after separate native execution authorization:
env -i PATH=/usr/bin:/bin HOME="$HOME" XDG_RUNTIME_DIR=/run/user/1000 \
  DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/1000/bus \
  bash tests/RunM6CNative.sh --run "${ARGS[@]}"
```

Missing cached images report `prepare_cached_mysql_image` or
`prepare_cached_runtime_image`; `--run` uses `--pull=never`. Read the complete
primary/cleanup/publication status and verify `SHA256SUMS` plus `PUBLICATION` before
attributing a future result to its SHA. Preserve failures and unreached cases.
Do not point this harness at the working checkout/database or edit migration 025.

## Completed Linux prerequisite evidence and desktop validation

The operator reports the focused Linux correction passed at
`96715eb35ac1ae2eb69c3a39a14ec009048128ad`: CLI exit 0, 206 assertions, 2.854755 s,
profile `parent-ini;bounded;parent-directives`, four normal cases and 32 isolated
runtime denials; CGI unavailable and zero actual CGI calls. Process exit 0,
389 assertions, 5.150861 s, all five phases with original deadlines. Six changed PHP
files linted; cleanup completed with no surviving processes or temporary fixtures.
Report: `/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-cli-process-20261020T000240Z-1gba0qjh/M6C-LINUX-CLI-PROCESS-VERIFICATION.txt`.
Operator-supplied SHA-256:
`585cb438af58046d99717a00111d87e2474a82a805b446de78507e463a715885`;
the operator verified 26/26 manifest entries. This desktop task did not retrieve
that report or independently verify its checksum, and did not rerun those suites.
Earlier `d4fb3d6` eight-suite/682-assertion Linux results and its separate CLI failure
remain historical evidence; later blocked review-gate tasks ran no tests.

Fresh desktop results and retained failures are recorded in the implementation
record's [teardown correction section](sprint-8.8-m6c-local-implementation.md#native-teardown-review-corrections). The recipe, MySQL integration, namespace ownership,
effective Linux cgroups and protected worker acceptance remain **UNEXECUTED** here.
First authorized native execution may expose a prerequisite or production-contract
blocker; stop and record it, without weakening the factory or applying staging SQL.

Last operator-reported deployed application remains
`f466fc1cdbfb53325a1db11445736bdc5ea7c6f4`. Migration 025 is already applied to working
staging; canonical SHA-256 remains
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
Backup/download remains operator-reported COMPLETE, both copies and confirmation
receipt retained. Restore is NOT EXECUTED; no backup or disk maintenance is repeated.

Implementation references: [Git explicit object packs](https://git-scm.com/docs/git-pack-objects),
[Docker memory/swap limits](https://docs.docker.com/engine/containers/resource_constraints/),
[tmpfs memory accounting](https://docs.docker.com/engine/storage/tmpfs/),
[official PHP recipe](https://github.com/docker-library/php/blob/master/8.4/bookworm/cli/Dockerfile),
[MySQL constraint diagnostics](https://dev.mysql.com/doc/mysql-errors/8.0/en/server-error-reference.html).
