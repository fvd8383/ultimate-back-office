# M6B isolated Linux MySQL validation

**M6B — IMPLEMENTED LOCALLY / REAL-MYSQL VALIDATION PENDING**.
Linux launcher/security changes: **REVIEW REQUIRED**. Real Linux/container/MySQL
execution: **NOT EXECUTED**. M6 **IN PROGRESS**; M6C–M6G **NOT STARTED**;
Production **UNAUTHORIZED / NOT DEPLOYED**.

The earlier [clean review of application head 19dc550](https://github.com/fvd8383/ultimate-back-office/pull/126#issuecomment-5745751056)
does not approve this new launcher. Work remains in [PR #126](https://github.com/fvd8383/ultimate-back-office/pull/126).
The [implementation record](sprint-8.8-m6b-local-implementation.md) distinguishes
standalone test evidence from the unexecuted real-MySQL gate.

## Host choice and setup authority

No host access, provisioning, resizing, purchase, installation, image download,
container creation, database creation or migration execution was authorized or
performed in this tooling task. The dedicated host has **NOT BEEN PROVISIONED**.
An operator may separately authorize either of these future arrangements:

| Host mode | Exact allowed hostname | User | Prerequisites before execution |
| --- | --- | --- | --- |
| `dedicated` (default) | `ubo-m6b-validate` | Explicit non-root test user, proposed `codex-validation`; never `ubo-deploy` | At least two CPUs, 3 GiB available memory, 6 GiB free on both Docker/evidence filesystems. Proposed host: 2 vCPU, 4 GiB RAM, 20+ GiB disk. |
| `shared-staging` (explicit each run) | `ubo-stage-app` | Exactly `codex-validation` | At least four online CPUs, 4 GiB available memory and 10 GiB free on both filesystems, plus all identical isolation/resource guards. A resize/setup requires separate operator authorization. |

The default rejects `ubo-stage-app`. `--host-mode shared-staging` opens only that
exact host/user pairing. The closed hostname allowlist rejects production and
unknown hosts even when supplied as `--expected-host`; it is not a generic bypass.
Hostnames establish the selected role, not cryptographic attestation: the operator
must verify the machine's role and must never rename a production host to pass a guard.
Shared-staging execution does not confer access to the deployed checkout or database.

The operator is responsible for the host choice, capacity and separately approved
setup; a Linux CLI PHP with PDO MySQL and POSIX support; Git, Bash/GNU utilities;
rootless Docker and its already-running user systemd service; cgroup v2/systemd
with `cpu`, `memory`, and `pids` delegated; and an approved, cached official MySQL
8.4 image with recorded digest, Linux platform and architecture. The operator
must verify the actual image provenance/digest independently, arrange a user
systemd session, and prepare a standalone full-history clone at a private path.
Neither launcher mode installs, pulls, enables services, uses sudo, edits daemon
settings/quotas, fetches, switches branches, cleans files or changes Docker context.

`codex-validation` runs its own rootless engine and the future tests. Existing
staging/deployment identities and services remain untouched. This account must
have no staging/production/provider credentials, application environment files or
customer data. Do not add it to a privileged Docker group. Do not copy an existing
deployment tree. Use a fresh standalone clone, not a worktree sharing `.git` metadata.

Required layout, owned by the test user (the three parent directories mode `0700`):

```text
/home/codex-validation/m6b-validation/                  0700
  checkouts/                                         0700
    ultimate-back-office/                            standalone clean clone
  evidence/                                          0700
/run/user/<test-uid>/                                 0700
  docker.sock                                        socket owned by test user
```

These paths must resolve outside every web document root. The launcher resolves
symlinks, restricts checkouts to the private home hierarchy, rejects `/var/www/*`
(including `/var/www/ubo-repo`) and `www`/`public_html` descendants, checks standalone
Git metadata, exact HEAD and clean tracked/untracked/ignored inputs. The operator
must not configure that private hierarchy as a web document root. Required baseline
history is `5baae28c9af68cca7694a912d7f35c87c50c9dc5`. Migrations 001–024 must
match its blobs; 025 must hash to
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
Use canonical LF checkout bytes. No normal application environment is sourced.
Inherited database/application/provider and Git/PHP configuration overrides are rejected.

## Explicit invocation

Run only after the exact new head has been reviewed and execution separately authorized.
The operator supplies the final reviewed SHA and independently approved digest;
the launcher deliberately does not embed its own commit SHA. Values below are
placeholders, not approval of an image or execution on a host.

```bash
cd /home/codex-validation/m6b-validation/checkouts/ultimate-back-office
bash tests/RunM6BMySql.sh --check-only \
  --host-mode dedicated \
  --expected-host ubo-m6b-validate --expected-user codex-validation \
  --expected-sha '<reviewed-40-hex-SHA>' --php /usr/bin/php8.4 \
  --docker-socket /run/user/1001/docker.sock \
  --image-digest 'sha256:<approved-64-hex-digest>' --platform linux/amd64
```

Use the resolved PHP binary path and actual test UID. For the separately authorized
shared host, replace **both** `--host-mode dedicated` and `--expected-host
ubo-m6b-validate` with `--host-mode shared-staging --expected-host ubo-stage-app`;
the expected user must remain `codex-validation`. `linux/arm64` is supported only
with matching engine and approved cached image metadata.

`--check-only` verifies prerequisites and cached inspection metadata, then exits
before generating credentials, creating a container, SQL connections, migrations
or workers. Its success is not a SQL PASS or proof of effective per-container
limits. It does not create a run evidence directory. Missing prerequisites produce
explicit prerequisite diagnostics and nonzero exit, not application assertion failures.

To execute later, repeat the exact reviewed arguments with `--run` in place of
`--check-only`. There is no implicit run mode or `--skip-safety` switch.

## Locality, resources and lifecycle

Before a daemon call the launcher resolves context/`DOCKER_HOST` selection and
rejects remote, conflicting, ambiguous and TLS/API overrides. Every daemon command
pins the verified `/run/user/<uid>/docker.sock` endpoint. It checks socket and daemon
process ownership, rootless engine metadata, cgroup v2/systemd, delegated controllers,
and engine warnings. It does not change persistent context. Linux PHP rechecks
rootless engine, approved official digest/image ID/platform, container identity,
ownership, limits, mounts and loopback port before any database connection.

Creation uses the already-inspected immutable image ID and `--pull=never`. One
randomly named, labelled container receives fresh test-only credentials. It has
no host-data, application, credential, device or socket mounts; no privileged mode,
host network/PID/IPC or added capabilities; and uses `no-new-privileges`.

| Control | Requested and checked |
| --- | --- |
| MySQL | 1 CPU, 1536 MiB memory, no additional swap, 128 PIDs |
| Database | `/var/lib/mysql` tmpfs, maximum 1 GiB; **included within the 1536 MiB container memory limit** |
| Docker logs | Local driver, two 1 MiB files, compression disabled |
| PHP | 256 MiB per process, coordinator plus at most two independent workers; child settings propagated on Windows and Linux |
| PHP process tree | Transient user systemd unit; aggregate 1 CPU, 1 GiB memory, zero swap, 32 tasks, file-size limit 4 MiB, no core dumps |
| Wall clock | 1200 seconds from run setup, remaining time passed to systemd and GNU timeout; stop timeout 10 seconds and bounded external cleanup commands |
| Output | At most 2 MiB test output, at most 2 MiB redacted container tail, private bounded credential file; Docker/PHP inspection output capped |
| Storage | Conservative 6 GiB monitored budget: image size + writable layer + 1 GiB tmpfs reserve + Docker log caps + evidence/scratch allowance; **not a filesystem quota** |

The launcher reads the actual container and PHP cgroup CPU, memory, swap and PID
files before releasing a gate that permits PHP/SQL to start. Ignored flags,
unavailable controls or unknown cgroup layout fail closed. Limits are not increased
automatically. Effective resource enforcement still needs validation on the selected
Linux host; fake command tests cannot establish kernel behavior.

While active, it checks writable-layer size, evidence size, free disk and available
memory. Dedicated reserve floors are 1 GiB free disk and 256 MiB available RAM;
shared-staging floors are 4 GiB and 1536 MiB respectively. Falling below a floor
stops this run, without altering staging. Checks occur between bounded Docker
inspections (normally one-second intervals; an inspection may take up to 15 seconds).
Concurrent host activity can change headroom between checks. This is monitoring,
not a reservation or quota, and shared execution still needs operator capacity planning.

Timeout, signals, output/storage/headroom exhaustion, OOM/PID exhaustion and test
failures return nonzero. Systemd kills only this run's control group; GNU timeout
bounds its client tree. A trap installed before resource creation first stops the
owned unit, then verifies exact container ID/name/labels/image before removal.
Partial startup resolves only the generated name and verifies ownership. Unknown
or foreign identity produces an orphan/cleanup failure for operator inspection;
there is no guessed deletion, prune, killall or cached-image removal. Cleanup
failure overrides overall PASS while preserving the separate test exit status.

Private evidence is outside the checkout under `evidence/run-*`: SHA/migration hash,
host mode/user, runtime versions, approved image digest/ID/platform, requested and
observed controls, bounded redacted test/container output, exit/failure classification,
cleanup result and `SHA256SUMS`. Credentials are removed from the private runtime
directory; passwords, tokens, database-name token prefixes and environment dumps
are excluded from evidence. Retained artifacts are synthetic test evidence only.

The [Windows launcher](../tests/RunM6BMySql.ps1) is unchanged. The native PHP harness
retains canonical fresh 001–025 and 001–024-to-025 upgrade cases, actual MySQL 8.4
and isolation reporting, native prepares, real metadata/FK/CHECK/actor-deletion
checks, independent barriers, queue retirement, policy/replay/races/rollback and
synthetic artifact labels. Application worker behavior and migrations are unchanged.

## Historical staging preflight and local verification

User-supplied report only:
`ubo-m6b-isolation-preflight-20260919T232220Z/M6B-DROPLET-ISOLATION-PREFLIGHT.md`.
User-supplied SHA-256:
`1d82275194a5c67d784a64314692744fbfdc15192d88ca0ec929366fa0423059`.
It reports one vCPU, approximately 962 MiB RAM, no swap, approximately 3.15 GiB
available disk and no container runtime. It was **not downloaded or independently
hash-verified** here. `APP_ENV` remains **independently unconfirmed**. That reported
configuration cannot pass either mode. This tooling task makes no claim that the
host has since changed and authorizes no resize.

Local validation uses [policy/launcher tests](../tests/WebsitePlatformM6BLinuxLauncherTest.php)
and [process-scoped fake commands](../tests/support/WebsitePlatformM6BLinuxLauncherFixture.sh),
existing Windows Git Bash syntax checks, standalone PHP suites, PHP lint, unchanged
PowerShell syntax, documentation links/fences/status/inventory and Git diff checks.
Actual totals are recorded in the implementation record after the checks complete.
Bash syntax and fake lifecycle success are **not a Linux run, container isolation
proof, real MySQL PASS, staging validation or M6 closeout**.

Relevant primary references: [Docker rootless prerequisites](https://docs.docker.com/engine/security/rootless/),
[rootless resource limits and delegation](https://docs.docker.com/engine/security/rootless/tips/),
[systemd-run](https://www.freedesktop.org/software/systemd/man/latest/systemd-run.html).
