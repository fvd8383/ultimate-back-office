# M6B isolated Linux MySQL validation

**M6B — IMPLEMENTED LOCALLY / REAL-MYSQL VALIDATION PENDING**.
Linux launcher: **CORRECTIONS IMPLEMENTED / REVIEW REQUIRED**.
Actual Linux/systemd/container/MySQL execution: **NOT EXECUTED**.
M6 **IN PROGRESS**; M6C–M6G **NOT STARTED**; Production **UNAUTHORIZED / NOT DEPLOYED**.

Work remains in [PR #126](https://github.com/fvd8383/ultimate-back-office/pull/126).
The [implementation record](sprint-8.8-m6b-local-implementation.md) records executed
local tests separately from the unexecuted real-MySQL gate. The earlier clean
application review of `19dc550` did not approve the Linux launcher.

## Completed-review corrections

[P2: deterministic supervisor interruption](https://github.com/fvd8383/ultimate-back-office/pull/126#discussion_r4058088240)
is addressed with minimal SIGINT/SIGTERM traps that only record the first signal.
The main flow checks that state before starting or collecting another phase and
after status command substitutions. Child collection polls to confirmed termination
before calling `wait`, handles a signal-interrupted wait without `set -e` losing its
reason, and uses the existing absolute run deadline. A signal between a flag check
and a monitor sleep therefore cannot cause an unbounded wait.

Cleanup keeps the recording traps installed, stops the owned unit and local children
once, and bounds local reaping after TERM/KILL. It retains all ownership and descendant
cgroup checks below. A recorded interruption takes precedence over normal/test-failure
classification: SIGINT returns **130**, SIGTERM **143** when cleanup succeeds.
Cleanup failure remains separately reported and returns **2**, retaining `interrupted`
and the original signal code in private evidence. Repeated mixed signals keep the first
code and do not restart cleanup. The final publication boundary below supersedes
the earlier republish-on-signal loop.

[P2: final evidence signal race](https://github.com/fvd8383/ultimate-back-office/pull/126#discussion_r4058219312)
is addressed with an explicit outcome-freezing boundary after cleanup attempts and
evidence preparation have settled. Until that boundary, first-signal recording stays
active, including throughout cleanup. A single `trap '' INT TERM` then ends acceptance
of new INT/TERM signals. The frozen interruption, cleanup and exit statuses drive the
final report. Signals during final report/checksum generation, after publication or
immediately before exit are deliberately outside the acceptance window: they cannot
change just the evidence or just the exit code. This is a defined finalization policy,
not a claim that Bash can atomically rewrite filesystem evidence until process exit.

Final report writing, checksumming, manifest publication and the path notice run in
one subprocess under a **5-second deadline plus a 1-second forced-kill allowance**.
Publication failure forces exit 2 and permits exactly one equally bounded failure
publication: at most two attempts / 12 seconds of watchdog allowance, with no signal
retry loop or sleep in production finalization. Cleanup is never restarted. An
already accepted signal retains its reason/code if publication fails; cleanup and
publication results are separate fields. The report includes the process `Exit status`.
The manifest is removed before writing and published by rename only after successful
checksumming. A missing/invalid manifest marks evidence **uncommitted**, even if a
partial report contains PASS text; persistent storage errors cannot establish PASS.
INT/TERM are ignored only in this bounded final section. Earlier execution, supervisor,
collection and cleanup signal behavior is retained.

Windows-local command doubles cover controlled active/before/after-completion barriers,
child waits, status command substitution, the check/sleep gap, cleanup, repeated signals,
cleanup failure and evidence publication for both signals. The original `run-interrupt`
case remains enabled. The exact local baseline reproduction and current validation
counts are recorded in the [implementation record](sprint-8.8-m6b-local-implementation.md#docker-data-root-and-final-evidence-correction--2026-09-20).
These results do not establish real Linux/systemd/MySQL PASS. Resource admission and
workload limits are unchanged by this signal correction.

[P1: unloaded transient units](https://github.com/fvd8383/ultimate-back-office/pull/126#discussion_r4055419657)
is addressed by capturing unit ownership and cgroup identity before stopping, then
reading an explicit LoadState/ActiveState/Description/ControlGroup snapshot.
A successfully stopped unit may disappear. Explicit `LoadState=not-found` can prove
absence even when systemctl exits nonzero; a failed command without that metadata
cannot. Already absent, owned stopped/unloaded, still active, replaced/foreign,
inaccessible-manager and failed/indeterminate-stop outcomes remain distinct.

A recorded cgroup that still exists must be accessible and report
`cgroup.events: populated 0`, which includes descendants. Missing ancestry under
an accessible parent is distinguished from unreadable ancestry. Safe termination
allows ownership-verified container cleanup to continue after unit unloading.
Uncertain termination prevents additional PHP inspection and container deletion;
it produces a non-success cleanup result. No remain-after-exit workaround, broad
process kill or guessed resource deletion is used. See the
[kernel's cgroup v2 interface](https://www.kernel.org/doc/html/latest/admin-guide/cgroup-v2.html).

[P2: Docker client configuration](https://github.com/fvd8383/ultimate-back-office/pull/126#discussion_r4055419662)
is addressed with a new private, run-owned CLI configuration containing only
`config.json` with `{}`. Every daemon command specifies both `--config` and the
verified `--host unix://...` endpoint, including PHP inspection and cleanup.
Inherited DOCKER_CONFIG and proxy variables are removed from launcher Docker
commands; the existing client configuration is never read, copied, printed, edited
or deleted. Explicit DOCKER_CONTEXT overrides are rejected, and DOCKER_HOST must be
absent or exactly match the approved socket. The empty config needs no default context.

Before SQL, the actual container environment must exactly equal the inspected,
approved image's defaults plus the generated MYSQL_ROOT_PASSWORD and
MYSQL_ROOT_HOST=% additions. Unexpected entries, duplicates, missing defaults or
changed values fail without printing values. This preserves legitimate pinned-image
defaults while excluding injected proxy credentials. Docker documents automatic
[client proxy injection](https://docs.docker.com/engine/cli/proxy/).
The client's temporary config is separate from the rootless **daemon** configuration:
`~/.config/docker/daemon.json`, its data-root and installed service remain untouched.

## Operator-reported setup and supported layouts

The operator supplied a completed inspection at **2026-09-20T21:23:19Z** on
**ubo-stage-app**, as **codex-validation**, UID **1000**. These measurements are
**operator-supplied**, not observations made by this desktop task:

| Item | Reported observation | Existing admission requirement |
| --- | --- | --- |
| Available CPUs | 4 | 4 |
| MemAvailable | 7610155008 bytes | 4563402752 bytes |
| Volume free | 24222400512 bytes | 10737418240 bytes |
| Boot free | 2941308928 bytes | 1073741824 bytes |

Capacity **qualified at inspection**. Recheck it with Docker running before separately
authorized execution; no resizing proposal or lowered gate follows from this task.
The volume UUID was reported as `7d255792-0283-4773-b7a1-20596ed0d8fa`. The Docker user
service was **installed, stopped and disabled**, with its socket absent while stopped;
it is not reported missing, and this launcher never starts it. PHP was reported at
`/usr/bin/php8.3`, version **8.3.6**, with PDO MySQL and `proc_open` available.
The deployed application was reported clean at
`70a3051f73874e7268b9c1bba45bf19d41f9432a`, with Apache active. APP_ENV remains
independently unconfirmed. Actual Docker engine version and approved cached-image
digest remain later runtime observations. No host access or setup occurred here.
A dedicated validation server remains a supported alternative.

Select `--host-mode shared-staging --layout volume` for the reported arrangement:

```text
/mnt/ubo_stage_testdata/                            actual mounted filesystem
  codex-validation/                               test-user-owned, 0700
    docker/                                       rootless daemon data-root, 0700 or 0710
    checkouts/                                    independent full-history clone, 0700
    evidence/                                     private retained evidence, 0700
      mysql-image-pin.txt                         operator's image identity record
    tmp/                                          disk-backed test scratch, 0700
/etc/ubo-validation/volume.uuid                    root-owned UUID identity anchor
/usr/local/bin/ubo-test-volume-check               root-owned verification helper
/run/user/<verified-test-uid>/docker.sock           owned rootless Unix socket
```

Before creating any validation files on that volume, the launcher independently
checks the exact resolved mountpoint, filesystem UUID against the marker, distinct
boot/volume devices, mount ID, and the resolved private directories' ownership,
permissions, filesystem and mount identity. The marker and helper must be regular,
root-owned, non-writable by the test user, with protected parents; the helper must
be executable. Helper success cannot override a path/UUID mismatch.
DockerRootDir must equal exactly
`/mnt/ubo_stage_testdata/codex-validation/docker`. Symlink escapes and nested mounts
are rejected; no home-directory symlink is used to disguise the volume.

Only that exact Docker data-root path accepts **0700 or 0710**, with the verified
test-user UID and the intended **codex-validation owning group**. Group read/write,
all other-user access, special bits, wrong owner/group, different paths, symlinks and
unexpected mounts are rejected. The enclosing `codex-validation` base, `checkouts`,
`evidence`, `tmp` and private runtime/client directories retain **0700**. The reported
Docker directory was `codex-validation:codex-validation` / **0710**; the other three
workspace directories were reported **0700**. Initial, recurring and cleanup mount
checks apply this same distinction. No Docker data-directory contents are traversed or changed.

For upstream context, Moby's
[`setupDaemonRoot` at docker-v29.1.3](https://raw.githubusercontent.com/moby/moby/docker-v29.1.3/daemon/daemon_unix.go)
initializes daemon-root ownership/permissions with 0710. This source reference does
**not** identify the installed host engine version. The launcher neither chmods/chowns
Docker storage nor changes daemon configuration, storage location or contents.

The initial UUID and mount ID are retained and rechecked before resource creation,
during execution and during cleanup. A directory descriptor pins the verified
storage tree before writes; evidence and TMPDIR paths use that descriptor so
unmount/overmount cannot redirect writes onto the boot filesystem. Loss/change of
mount identity stops the run and yields an infrastructure failure. No replacement
evidence is created beneath an absent mountpoint. A small, private emergency report
may remain under the verified runtime directory when volume evidence is unavailable.

Small ephemeral CLI configuration, credentials, socket, FIFO and gate files remain
under verified private `/run/user/<uid>`; these are the explicit runtime exception.
Disk-backed PHP temporary data and retained evidence use the selected storage tree.
Only run-owned temporary files/directories are removed; unexpected leftovers make
cleanup fail rather than permitting broad deletion.

Dedicated operation remains supported as `--host-mode dedicated --layout home`
on exactly `ubo-m6b-validate`, with a selected non-root test user, never ubo-deploy.
Its base is the resolved account home plus `/m6b-validation`, with private `checkouts`,
`evidence` and `tmp` directories. DockerRootDir must resolve under that home.
The prior shared-host home layout also remains available explicitly, subject to the
same conservative shared-host gate; it is not substituted for volume mode automatically.

Default mode rejects ubo-stage-app. Shared mode requires its exact hostname and the
exact codex-validation identity. The closed host-role allowlist rejects production
and unknown hosts. Names are role selection, not cryptographic host attestation:
the operator must never rename a production host to satisfy a guard.

All layouts require an exact operator-supplied reviewed SHA, clean tracked and
untracked/ignored inputs, a standalone checkout outside all web roots, and independent
Git metadata (no worktree, alternates or redirected work tree). `/var/www/ubo-repo`,
other `/var/www` paths and `www`/`public_html` descendants remain rejected.
Migrations 001–024 must match baseline `5baae28c9af68cca7694a912d7f35c87c50c9dc5`;
canonical LF migration 025 must hash to
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.

No application environment, staging/production database or provider credentials,
or customer data belongs in this account or checkout. No normal application
configuration is sourced and no arbitrary DSN fallback exists.

## Conservative resource decision

The established shared-conservative gate is unchanged: **four available CPUs** and
**4.25 GiB MemAvailable**, with the existing workload caps and overhead allowance.
No smaller profile, automatic fallback or resizing proposal is added by this correction.

| Shared-conservative allocation | Amount |
| --- | --- |
| MySQL hard memory cap, including database tmpfs | 1536 MiB; tmpfs at most 1024 MiB within that cap |
| PHP unit aggregate hard cap | 1024 MiB; three PHP processes at most 256 MiB each, with remaining unit allowance for helpers |
| Rootless engine / external supervisor growth allowance | 256 MiB planning allowance, not a measured or independently capped daemon allocation |
| Additional staging/Apache operating reserve | 1536 MiB |
| Required measured MemAvailable before run | **4352 MiB = 4.25 GiB = 4563402752 bytes** |

The daemon must already be running; its existing resident memory and existing staging
usage are already excluded from MemAvailable. The allowance covers additional
overhead, with abort monitoring if host pressure consumes the reserve.
MySQL and the PHP unit each have a one-CPU quota; four available CPUs leave two
CPU-equivalents for staging and overhead. This is a bounded consumption policy,
not a promise about performance or a reservation against other host activity.

| Profile | CPU admission | Available memory admission | Data admission / runtime floor | Boot reserve |
| --- | --- | --- | --- | --- |
| dedicated-conservative | 2 available CPUs | 3 GiB | 6 GiB / 1 GiB | 1 GiB |
| shared-staging-conservative | 4 available CPUs | 4.25 GiB | 10 GiB / 4 GiB | 1 GiB |

Runtime MemAvailable floors remain 256 MiB dedicated and 1536 MiB shared.
Volume mode requires only the **1 GiB boot operating reserve**, not 10 GiB boot
free space. Docker/evidence capacity on the same filesystem is counted once, not
summed as two independent pools. The 6 GiB run-storage allowance remains a monitored
budget, not a filesystem quota: full cached-image size, writable layer, tmpfs
reserve, bounded logs/evidence and scratch allowance are accounted for. Disk scratch
is monitored against 4 MiB; unexpected nonempty scratch during cleanup is reported.

Check-only reports the selected profile/layout and actual versus required CPU,
MemAvailable, boot, data and evidence space. Capacity is reported even if the
operator intentionally left Docker stopped. The launcher then fails that prerequisite
clearly and never starts or reconfigures the service. The completed operator-supplied
inspection above qualifies against the unchanged capacity gate. This desktop task
has not independently verified the measurements, UUID, UID, digest or running-daemon
behavior. Capacity must be rechecked with Docker running before execution; this
correction requests no resize, installation or host change.

Rootless cgroup v2/systemd with delegated cpu/memory/pids is mandatory. Actual
container and PHP cgroup limits are read before releasing the SQL gate. Other limits:
MySQL zero additional swap and 128 PIDs; PHP unit zero swap and 32 tasks; 4 MiB per-file
limit; no core dumps; Docker logs two 1 MiB files; test output and diagnostic tail
at most 2 MiB each. Deadline remains 1200 seconds plus bounded cleanup. No limit or
timeout is automatically enlarged, and no SQL/concurrency acceptance case is skipped.
Monitoring normally repeats each second between bounded inspections; concurrent
host activity can change capacity between checks.

## Later check-only invocation

Only after review and separate authorization, verify/supply the following **nonsecret**
shell variables: `reviewed_sha`, `php_bin` (resolved executable path), `validation_uid`
(actual codex-validation UID), `image_digest` (approved sha256 digest), and
`image_platform` (`linux/amd64` or `linux/arm64`). The operator's
`evidence/mysql-image-pin.txt` is a reference to verify against the cached official
image, not proof that this desktop task inspected it. No example UID or digest is invented.

```bash
cd /mnt/ubo_stage_testdata/codex-validation/checkouts/ultimate-back-office
bash tests/RunM6BMySql.sh --check-only \
  --host-mode shared-staging --layout volume \
  --expected-host ubo-stage-app --expected-user codex-validation \
  --expected-sha "$reviewed_sha" --php "$php_bin" \
  --docker-socket "/run/user/$validation_uid/docker.sock" \
  --image-digest "$image_digest" --platform "$image_platform"
```

Check-only creates no container, database credentials, connection, migration or test
worker. It may create and remove only its private empty runtime CLI configuration;
cleanup is registered before creation. Missing prerequisites are not application
assertion failures. Success does not establish SQL PASS or effective per-container
resource enforcement.

## Separate later run invocation

**Not authorized or executed in this desktop task.** After an independently approved
check and explicit execution authorization, with the same verified values:

```bash
cd /mnt/ubo_stage_testdata/codex-validation/checkouts/ultimate-back-office
bash tests/RunM6BMySql.sh --run \
  --host-mode shared-staging --layout volume \
  --expected-host ubo-stage-app --expected-user codex-validation \
  --expected-sha "$reviewed_sha" --php "$php_bin" \
  --docker-socket "/run/user/$validation_uid/docker.sock" \
  --image-digest "$image_digest" --platform "$image_platform"
```

For the dedicated alternative use its home checkout, `--host-mode dedicated
--layout home --expected-host ubo-m6b-validate`, and its verified user/UID; retain
all other explicit parameters. Neither mode installs, pulls, starts services,
uses sudo, changes daemon/client/host configuration, remounts volumes, fetches,
switches branches, repairs checkouts or uses a skip-safety flag.

## Evidence and preserved test validity

Private evidence contains the exact SHA/migration hash, selected layout/profile,
mount identity, runtime versions, approved image identity, requested/observed limits,
bounded redacted diagnostics, separate test result and cleanup result, and checksums.
Cleanup failure prevents overall PASS. Unknown ownership is reported for operator
review; cached images, unrelated resources and other runs are never deleted.
Passwords, tokens, token-derived database-name prefixes and environment values are
not printed or retained in diagnostics.

The [Windows launcher](../tests/RunM6BMySql.ps1) and all application/migration code are
unchanged. The native harness retains canonical fresh/upgrade migrations, MySQL 8.4
verification, native prepares, schema/FK/CHECK/actor-deletion cases, independent
authorization/queue/replay/rollback processes and synthetic artifact labels.
The [launcher suite](../tests/WebsitePlatformM6BLinuxLauncherTest.php) uses only
[isolated command doubles](../tests/support/WebsitePlatformM6BLinuxLauncherFixture.sh).
Actual totals are in the implementation record. Fake commands and Git Bash syntax
are **not actual Linux/systemd/container isolation or MySQL PASS**.

Historical pre-resize staging information remains **user-supplied evidence**:
`ubo-m6b-isolation-preflight-20260919T232220Z/M6B-DROPLET-ISOLATION-PREFLIGHT.md`,
supplied SHA-256 `1d82275194a5c67d784a64314692744fbfdc15192d88ca0ec929366fa0423059`.
It reported one CPU, about 962 MiB RAM, no swap, about 3.15 GiB free disk and no runtime.
It was not downloaded or independently hash-verified here. APP_ENV remains
independently unconfirmed. The completed operator inspection at 2026-09-20T21:23:19Z
supersedes that old capacity/runtime description with operator-supplied measurements.
It does not establish this desktop task's host access, kernel isolation or MySQL PASS.

No staging/production access, host setup, resize, installation, image download,
container/database creation, migration, deployment, M6C or Narrator work occurred.
