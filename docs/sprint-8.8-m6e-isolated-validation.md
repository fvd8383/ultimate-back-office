# Sprint 8.8 M6E — Isolated Linux/Apache validation

**AUTHORED / NOT EXECUTED.** The Windows implementation task does not authorize
installation, server preparation, Linux/Apache execution, SQL, service operations,
working-staging activation or deployment. This document is the bounded later path,
not a claim that a host contract exists. M6D’s closed native gate is not reopened.

## Admission and ownership

The component harness is [WebsitePlatformM6EIsolated.py](../tests/WebsitePlatformM6EIsolated.py).
It accepts `--inventory` without execution and `--run` only on Linux with a protected
root-owned `/run/ubo-m6e-fixture/admission.json`. It never installs software or starts,
reloads or provisions Apache/services. Operator preparation is a separate approval.

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

The later authorized command is:

```text
/usr/bin/python3 -I -B tests/WebsitePlatformM6EIsolated.py --run
```

Do not execute it in this desktop task. It has a 600-second coordinator budget,
bounded child capture, exact SHA binding, a fixed case inventory, first-failure
context before teardown, owned-child reaping and exact private-root inventory
cleanup. Cleanup failure cannot turn the first failure into success. Reports use
exclusive creation, flush/fsync and a SHA-256 receipt outside the source checkout.
No assertion total is asserted before execution. Unexecuted cases are absent or
INCOMPLETE, never skipped PASS.

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

Three inventory entries currently return **INCOMPLETE**. The harness therefore cannot
emit overall PASS. Implement their controlled drivers before requesting M6E acceptance:

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
   Resolve the concrete source access gap first: existing M6C directories/files are
   0700/0600 under the worker UID; the distinct unprivileged publisher cannot read
   them. Read-only mounts and default ACLs do not override those explicit modes.
   A reviewed bounded artifact-transfer bridge is still needed without weakening
   M6C modes or granting the helper generic DAC/root access. The current harness uses
   one fixture UID and therefore cannot prove this installed ownership boundary.

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
