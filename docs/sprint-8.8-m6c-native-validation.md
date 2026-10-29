# M6C native build-only validation

M6C — IMPLEMENTED / SCOPED ISOLATED NATIVE GATE PASS / ACCEPTANCE REVIEW PENDING.
M6C formal closure — PENDING.

The [current acceptance/evidence record](sprint-8.8-m6c-native-acceptance.md) binds
the successful native run to `2ab14c7d8212d1170aafe6d8dcdf3b178dd7d5bf`: 20 PASS /
0 FAIL / 0 UNREACHED, 1,315 completed assertions (805 case + 510 setup/identity),
launcher exit 0. Original evidence was retrieved and locally hash-verified. The new
documentation head was not natively executed. The supplemental authoring below runs
local synthetic regressions only; it does not execute the native gate.
The historical core runtime image identity and requirement limits are in that record.

## Journal helper preparation and receipt correction, October 28, 2026

At source **`4d49e5f35b943a5352dac6d7eb5d6af79328c3e1`**, the operator reports
**C COMPILATION/LINKING AND RECIPE STARTUP-LOAD — PASS; RECEIPT ADMISSION — FAILED**.
These are operator-supplied facts: this desktop correction did not retrieve the
evidence, verify its hashes, load the binary or inspect the eight retained nonfatal
compiler warnings. This preserves completed work without upgrading blocked stages.

| Operator-reported stage at `4d49e5f` | Result |
| --- | --- |
| Host Linux supplemental protocol | PASS once, 236 assertions |
| Host Linux journal selection | PASS once, 39 assertions |
| Host Linux supplemental selection | PASS once, 41 assertions |
| Host Linux launcher | PASS once, 7,388 assertions / 104 scenarios |
| Image build | PASS once, 47.391 seconds |
| C compilation/linking and recipe startup-load check | PASS; eight nonfatal warnings retained, not assessed here |
| Ordinary configured runtime PHP | Did not automatically load the extension |
| UID/GID-2000 access/capability/limit checks | PASS |
| `M6CJournalAdmission::verify()` | FAILED, exit 255: actual boolean `PHP_ZTS=false` passed to `int $zts`; original receipt contains JSON `"zts":false` |
| Separate runtime startup-load check, six runtime suites, both profile admissions | UNEXECUTED |
| Arming, status call, interception, MySQL and native acceptance/recovery | UNEXECUTED |
| Cleanup | Completed; Docker returned inactive/disabled |

Operator evidence directory:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-journal-helper-preparation-20261029T010440Z-ab309fe5eb2c`

| Historical artifact / distinct hash role | Operator-supplied value, not locally verified |
| --- | --- |
| Prepared image | `sha256:27e64ee203d197dc74bdda23bbe72cd75aa019e601bad4877aa3f74e5cf870be` |
| Compiled binary SHA-256 | `53185e5de467b44fde43f0c1cef3c413b60aa0c772aef694ab8f51c5d7671fec` |
| `M6C-JOURNAL-HELPER-PREPARATION.txt` SHA-256 | `47b56aa664905a687b9d667b6e490b5994b6b1b3d3141d78b5e6c447454d0329` |
| `SHA256SUMS` file SHA-256 | `9ef20ace2a1a6eb79f903603b7d8b8c4afee9cba3e0bfc8303fe59d445fa5414` |

The [local correction record](sprint-8.8-m6c-local-implementation.md#journal-receipt-boolean-correction-october-28-2026)
details both reproduced mismatches, the original integer fixture's false confidence,
bounded audit and fresh validation. PHP 8.4's
[constant type change](https://www.php.net/manual/en/migration84.incompatible.php#migration84.incompatible.core.constants)
requires a boolean reader contract: `bool $zts`, NTS strictly false and expected
JSON boolean false. The writer and `verify()` continue using actual constants without
conversion. Integer-zero receipts now fail strict validation. Version/source/PHP,
source/binary hashes, exact fields/order and fixed path/ancestor/ownership/mode/
link-count/size checks are preserved; pure receipt tests never invoke `verify()`.

Windows PHP **8.4.24 ZTS** has actual boolean `PHP_ZTS=true`. Literal synthetic NTS
fixtures pass, and actual writer-shaped JSON retains true and reaches intentional
`RuntimeException('journal_helper_receipt')`, not TypeError. Fresh receipt **84** and
journal policy/state **130** assertions, three PHP lints and document/diff/integrity
checks establish a local test-only correction, not Linux NTS admission or load proof.
The host controller may stay PHP 8.3; the helper still requires PHP 8.4 NTS.

The old image/binary/failed receipt remain valid historical artifacts for `4d49e5f`,
not admitted artifacts or provenance for a new commit. Later authorized preparation
requires exact-reviewed-source image/receipt provenance, the corrected receipt test,
and completion of the separate startup-load check, all six runtime suites and both
profile admissions that never ran. No installed receipt edit, image relabeling,
runtime workaround or new image/binary hash is claimed. Unaffected historical host
test evidence can be reused with explicit SHA attribution; do not automatically
repeat all four host suites or transport smoke. This task authorizes no later runtime
operation. Core native PASS remains historical at `2ab14c7`; formal M6C acceptance
remains pending. Browser allocation remains an owner decision, backups/receipts are
untouched and restore is NOT EXECUTED.

## Producer-journal fault profile, October 28, 2026

Historical authoring at `4d49e5f`, before the operator preparation reported above.
The build/load and Linux-test limitations in this section describe that earlier
desktop task; current reported build results and unexecuted stages are separate above.

**CORE NATIVE GATE — HISTORICAL PASS at `2ab14c7`.
STORAGE FAULT COVERAGE — AUTHORED / PARTIAL.
NATIVE HELPER BUILD/LOAD — NOT VERIFIED.
SUPPLEMENTAL NATIVE EXECUTION — NOT PERFORMED.
FORMAL M6C ACCEPTANCE — PENDING.**

This authoring starts at `5ff2b601a3ae07a47094bd4815d6f4cfc90db1ea`, whose completed
automated review reported no findings. It addresses only the three producer-journal
boundaries left out of the 17-case supplement. It neither completes the four broader
acceptance packages nor establishes a new native PASS. Linux execution of the
previous killed-child normalization correction also remains outstanding.

The unchanged [file primitive](../private/classes/SiteArtifactFiles.php) and
[store](../private/classes/LocalSiteArtifactStore.php) perform:
producer `.json.pending` exclusive creation/write → checked `fflush` → checked
`fsync` → `fclose` in `finally` → mode 0600 → pending-to-final `.json` rename →
candidate-directory creation. On failure the writer lock is released. A pending
journal, even complete-looking, or a missing final journal remains `unknown` during
`verifyRelease()`; neither proves safe absence. Service settlement therefore requires
reconciliation, and a fresh recovery inspection remains blocked without rendering.

| Fixed case / targeted operation | Prerequisites already accepted | Exact target role | Immediate caller error | Retained state / permitted recovery |
| --- | --- | --- | --- | --- |
| `producer-journal-flush-failure` / PHP `fflush` | Exclusive open and complete bounded write | This run/job/producer's pending journal stream | `storage_unavailable` | Pending retained; fsync not reached; finally close observed; no chmod/publication/candidate. Inspection unknown; recovery blocked. |
| `producer-journal-fsync-failure` / PHP `fsync` | Open, complete write and successful checked flush | Same bound pending stream | `storage_unavailable` | Pending retained; fsync not accepted; close observed; no chmod/publication/candidate. Inspection unknown; recovery blocked. |
| `producer-journal-publication-failure` / PHP `rename` | Checked write/flush/fsync, close and mode 0600 | Exact pending journal → final producer journal | `outcome_unknown` | Rename handler not executed, pending retained unchanged, no final journal/candidate. Inspection unknown; recovery blocked. |

These are **injected PHP API false returns** at the actual production call, not
spontaneous disk errors, kernel EIO, failed syscalls or power-loss durability proof.
A later close may flush buffered bytes; its accepted result is recorded, without
turning an unaccepted pending journal into published evidence. Producer-journal
publication here is distinct from candidate sealing, native-report publication,
host evidence capture and public website publication.

The [test-only extension source](../tests/native/m6c/journal/m6c_journal.c) wraps the
registered PHP internal handlers in one explicitly selected worker. This choice
follows PHP's actual [stream-based file functions](https://github.com/php/php-src/blob/PHP-8.4/ext/standard/file.c),
not an assumption that PHP `fflush` calls libc `fflush`. The bridge calls the
[shared bounded policy](../tests/support/WebsitePlatformM6CJournalFault.php) before
and after matched operations. Only a requested failure returns false without
calling the original handler. Unrelated operations use their original handlers.
No production source rewrite, filesystem-wrapper replacement, public switch,
global preload or php.ini edit is used.

Arming requires Linux PHP 8.4 NTS CLI, fixed attested worker/policy source paths,
UID/GID 2000, run hostname, verified run/job/producer authorization, protected
ancestors and private owned roots. The extension derives its only pending/final
paths internally. It binds the opened PHP resource and stream stat identity to the
pending file's device/inode, regular type, link count, owner and private mode, and
rechecks ancestors and identity at subsequent calls. Symlinks, replacement targets,
descriptor/resource reuse and wrong callers cannot redirect a fault. Exactly one
injection is permitted. The fixed callback carries enums, booleans and bounded
counts; reports use a target digest and opaque producer identity, never raw paths,
contents, environment or arbitrary exception text. PHP ABI/caller/path safeguards
are source-authored here; their actual Linux execution is **not yet verified**.

The additional **`--profile journal-v1` / `m6c-native-journal-v1`** inventory contains
exactly the three ordered instrumented cases above. Core `m6c-native-v1` retains its
exact unrelated 20 cases; `m6c-native-supplement-v1` retains its exact 17 cases and
meanings. This is **20 core + 17 supplemental + 3 separate journal cases**, not a
silently enlarged old supplement. CLI selection, launcher attestation, coordinator,
report identity, transport and strict acceptance all bind the new profile. Old
reports cannot satisfy it, and journal reports cannot satisfy either old profile.
Help/check-only/discovery does not load the extension or execute application work.

Each case first requires a distinct service-created successful control job using
the same extension/load path. Every armed worker also performs an off-target
write/flush/fsync/close/chmod/rename, checks its contents and removes only its owned
control files. The control checks rejected bad and duplicate arming. Actual call
counts, prerequisites, injected counts, caller errors, pending digests, service DB
effects and distinct recovery ownership are mandatory. The fresh recovery worker
loads no extension and must block without rendering; counters must be (2,1,1),
with zero releases, validations and success events for the fault job. Only a due
timestamp is controlled, never terminal job state or counters.

Missing helper/load, unsupported ABI/frameless handler, missed or duplicate calls,
wrong target/phase, lost evidence, incomplete controls, failed cleanup, extra or
missing cases and wrong lanes are non-PASS. A secondary close failure is retained
without replacing the caller's pending error, but cannot count as accepted close.
Other diagnostic/cleanup failures retain the original typed caller error and
prevent successful child completion. The coordinator, database, Git verifier,
report writer, core workers and protected CLI receive no extension load argument.

Later, separately authorized preparation must use the exact reviewed source as the
Docker build context and the existing operator-pinned PHP base. The
[test Dockerfile](../tests/native/m6c/Dockerfile) now copies and runs the minimal
[build recipe](../tests/native/m6c/journal/build.sh), requiring already available
`phpize`, matching PHP headers/`php-config`, `cc`, `make` and configure prerequisites.
The recipe installs only `/usr/local/lib/ubo-m6c/m6c_journal.so` plus a root-owned
0444 receipt binding source SHA, PHP version, C-source hash and computed binary hash.
There is no global extension configuration. Worker load uses that fixed path with
CLI opcache/JIT disabled. Runtime admission verifies the receipt against attested
source and actual binary bytes before any selected worker load.

No compiler or `phpize` was found on this Windows session's PATH or checked common
compiler locations. No C compilation, helper startup/load, image build or native
call interception was performed. **No binary hash or new image identity is claimed.**
Future build/startup is only a prerequisite: the positive/off-target controls and
all three actual production failure paths must then succeed within the existing
runtime contract. Resource limits, private networking, at-most-two-child supervision,
no-new-privileges/capability removal, deadline, owned cleanup and report hash/ack
checks remain unchanged. No expanded privilege or filesystem facility is proposed.

Fresh Windows results and original failures are in the
[implementation record](sprint-8.8-m6c-local-implementation.md#producer-journal-local-authoring-october-28-2026).
Native API failure handling and real-MySQL recovery outcomes remain unverified.
Browser allocation, backup receipts, prior failed/blocked histories and migration
025's applied status/pin remain untouched; no operational verification was performed.

## Supplemental protocol expectation correction, October 28, 2026

The [P2 exit-normalization correction and focused local results](sprint-8.8-m6c-local-implementation.md#supplemental-protocol-exit-normalization-correction-october-28-2026)
separate raw process exit from the normalized diagnostic report. A synthetic
`-1 -> null` / signal `9` proof precedes the fix; six literal recording regressions
constrain the contract independently. Fresh Windows `php -n` protocol, diagnostics
and observation suites pass with 236, 95 and 273 assertions respectively. The actual
Windows terminated child retained exit 255 / signal 0; the Linux reproduction is
reviewer-reported and actual Linux verification remains outstanding. Shared helpers,
production, exact 20/17 inventories and runtime requirements are unchanged.
No supplemental native execution occurred. At that normalization-correction head,
flush/fsync and producer-journal publication failure injection remained unimplemented;
the later journal-profile authoring above does not establish native execution.
Formal acceptance stays pending.

## Supplemental authoring, October 28, 2026

**PARTIALLY IMPLEMENTED LOCALLY / REVIEW REQUIRED. SUPPLEMENTAL NATIVE EXECUTION —
NOT PERFORMED. FORMAL M6C ACCEPTANCE — STILL PENDING.** This bounded supplement
implements the four packages from the [completed assessment](https://github.com/fvd8383/ultimate-back-office/pull/128#issuecomment-6458480460).
Authoring starts at `fa617fd7eeda92d8febdce66c46f973a5ae23a7e`. It is a different
source from the historical native PASS, and cannot reuse that run's source/image claim.
Package A addresses B23/B24 and the build portions of C07/C11/C12; B addresses
B25/C09; C addresses the remaining B08/B12/B15 store boundaries; D supplies the
promised positive approved-PDF/non-material path. The 77-case plan is not rewritten.

`RunM6CNative.sh --profile supplement-v1` selects `m6c-native-supplement-v1`.
Omitting the option, or selecting `--profile core`, retains `m6c-native-v1` and its
exact 20 cases, lanes and gate. Environment variables cannot select the supplement.
The selection is bound into the coordinator invocation, root-owned attestation,
report, transport reader and final acceptance validator. Help and check-only retain
their existing side-effect boundaries. The supplemental helper entry points reject
ordinary invocation before application bootstrap, a database connection or a socket.

The fixed inventory below has **17 instrumented cases**. All are authored native
cases, **NOT EXECUTED**. Every required name must occur exactly once in the writer's
specified JSON serialization, in inventory order, with PASS, instrumented lane,
positive completed assertions and an observed boundary. Missing/extra/duplicate,
FAIL/UNREACHED, wrong source/run/profile/lane and partial PASS cannot satisfy this
gate. Core and supplemental reports cannot satisfy one another. A transported report
is still subject to acceptance validation; successful transport alone is insufficient.

| Package | Exact cases | Native mechanism and required observation |
| --- | --- | --- |
| A: post-seal acceptance | `acceptance-revocation-first`; `acceptance-success-first` | Independent completion/revocation connections, barriers and an observed MySQL lock wait establish both serial orders. Revocation first denies new release/validation/success; success first remains immutable history after revocation and replay. |
| A: execution three | `third-execution-adoption`; `third-revoked-recovery-restart`; `third-deleted-inspection` | Two actual service executions create partial files and settle injected transient failures. Execution three seals real bytes and is killed at the observed SEALED barrier. Fresh recovery changes counts from (3,3,0) to (4,3,1), preserves the original producer and exact bytes, and renders nothing. Revoked/deleted requesters retain inspection but cannot adopt. A killed recovery is reclaimed with fresh ownership; stale renewal/result calls are rejected. |
| A: budget/operator | `automatic-budget-operator` | Two automatic recovery claims are interrupted during observed inspection of deliberately ambiguous proof. The existing budget blocks automatic work; unauthorized operator work is refused, and one authorized, scoped operator request key plus its duplicate establishes idempotence without resetting counters. |
| B: completion transport | `completion-not-committed`; `completion-ack-dropped`; `authoritative-outcome-unavailable` | A one-connection loopback relay inside the verified runtime suppresses COMMIT before forwarding, or forwards COMMIT but suppresses its successful server OK before PDO receives it. A fresh independent connection records committed state **before** replay/recovery. A separate armed connection loss prevents authoritative lookup; the observer remains unknown without effects, then recognizes history after connectivity returns. |
| C: observed process stops | `candidate-process-kill`; `proof-process-kill` | Real candidate bytes before manifest completion, or actual seal proof before rename, are observed through existing progress callbacks before SIGKILL. Fresh service recovery inspects without rendering, preserves original evidence and creates no partial success. Candidate output is quarantined/safely failed; ambiguous pre-rename seal state stays blocked. |
| C: real filesystem failures | `artifact-write-permission`; `artifact-space-exhaustion`; `artifact-rename-permission`; `foreign-target-collision` | Only new disposable artifact directories receive temporary permission faults. Actual store operations must emit EACCES/ENOSPC observations. Pressure fills only the existing 256-MiB run tmpfs, also testing low-space admission; its owned file is removed and free space checked before the next case. A foreign empty target is created before the store collision check and must remain empty. Write failures safely retry; rename/collision uncertainty remains reconciliation-required. |
| C: admission | `filesystem-admission` | Existing distinct `/tmp` and artifact tmpfs devices exercise cross-device refusal. A run-owned FIFO exercises nonregular inventory rejection. No mount, device creation or added capability. |
| D: supported document path | `nonmaterial-approved-pdf` | A valid inert one-page fixture PDF uses `pricing_list` / `link`, approved rights/site/business metadata, an effective earlier customer approval and exact current internal approval on a non-material revision. Actual selection, rendering, validation, store and completion verify exact PDF bytes, content-addressed path, MIME/size/hash inventory and public anchor reference. |

The two initial transient failures, lease expiry and backoff adjustments are
instrumented controls. Attempts/counters/results are created through the service;
no execution count is set to three, budget reset or successful attempt inserted.
Controlled lease/backoff timestamps are reported as such, not elapsed-time evidence.
Direct fixture identity/approval seeding is not approval-workflow/UI coverage.

The COMMIT relay parses the [MySQL COM_QUERY command](https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_com_query.html)
and [capability flags](https://dev.mysql.com/doc/dev/mysql-server/latest/group__group__cs__capabilities__flags.html).
It accepts bounded, unencrypted/uncompressed packets and targets only the identified
worker connection at the completion transaction. TLS, compression and query-attribute
capabilities fail closed; it never silently downgrades or substitutes a simulation.
It has one listener on runtime loopback, one attested private backend, bounded queues
and a 90-second lifetime. It neither exposes a host port nor changes server state
globally. Reports retain target job/attempt/connection, fault facts, typed worker error,
fresh observer identity and a digest of authoritative state before replay. Raw packets,
SQL, credentials and lease tokens are not logged. Private lease handoffs remain in
the run-owned tmpfs and are removed with that runtime.

The space case also creates a new nested private store, acquires its writer lock
using the actual service-created producer, then removes search permission from only
that new parent at the existing pre-probe guard. With adequate free space established
first, actual EACCES and `storage_unavailable` establish an unavailable
`disk_free_space` probe. The parent mode is restored and the new fixture reconciled
before the case completes; a typed SPACE observation records both low/unknown checks.

At the 17-case authoring head, residual C mechanisms were **UNIMPLEMENTED**: a real failed `fflush`/`fsync` and failure
between the checked producer-journal write and its publishing rename.
The current seams do not expose those boundaries
faithfully; a synthetic throw or an early collision would not prove them. They require
separately reviewed, bounded test-only interception or an appropriate isolated
filesystem facility. The later [separate journal profile](#producer-journal-fault-profile-october-28-2026)
authors API-return interception, with native build/load/execution still unverified.
No production seam, broad capability or host mount is introduced.
FIFO coverage does not claim character/block-device creation; ENOSPC covers the
write/space failure class without a separate quota matrix. SIGKILL is not power-loss
durability proof. Previously credited hardlink/symlink evidence remains credited.

Later work requires exact-new-head review, separately authorized source-matching
runtime preparation, capability/preflight verification and an explicitly authorized
supplemental native run. The cached earlier image is not relabeled or accepted for
new source. Existing resources, 1,200-second deadline, at-most-two-child supervision,
no-new-privileges/capability restrictions, private networking, evidence publication,
ownership-scoped cleanup and primary-failure/interruption precedence remain in force.
No native gap is closed by this authoring. Browser allocation to M6E served-policy
and M6G integrated validation remains a recommendation requiring owner decision.

Local regression results and their modeled boundaries are recorded in the
[implementation record](sprint-8.8-m6c-local-implementation.md#supplemental-local-validation-october-28-2026).

Earlier correction/run sections below retain their source-specific historical facts.
Their then-current failed/not-executed/image-preparation handoffs are superseded
only as current status; historical abac019 cause remains UNDETERMINED.

This is the operating procedure for the executable harness on PR #128, branch
`codex/sprint-8.8-m6c-artifact-builder`. The authoring baseline is
`96715eb35ac1ae2eb69c3a39a14ec009048128ad`. The correction from `8118138` to `9a35b59`
changed production artifact input-hash verification. Its native assertion was
corrected at `79e6d63`. The previous follow-up improved test-worker diagnostics,
observed protocol reads and child context retention. This correction isolates
instrumented Git environments and retains a safe source-verification observation
before generic wrapping; production, canonical verification, transport publication/
acknowledgement, all 25 migrations and pins remain unchanged.
The [implementation record](sprint-8.8-m6c-local-implementation.md) retains earlier
results separately. This guide does not authorize image preparation or a native run.

## Instrumented Git environment correction after abac019

The operator reports **one native run** at
`abac019a23b2801ec4d62187806f93330b9e2ff2`: launcher exit **1**, duration
**28.662524117 seconds**, **3 PASS / 1 FAIL / 16 UNREACHED**, and **673 attempted /
673 completed assertions**, including setup. Protected startup denials, multipage
build and replay passed. `concurrent-claims` failed with `native_child_before_barrier`.
Both instrumented build children reached READY and retained `worker_error` with
`phase=claim`, `class=SiteServiceException`, `code=conflict`. Expected exit was 0;
both actually exited 1 without observed CLAIM or RESULT. Failure-time and cleanup-time
states were stopped, signal 0, with no termination requested.

No typed PDO SQLSTATE/driver was retained. The attempted claim phase proves no
committed claim, and absent SQL diagnostics neither prove that all database
operations succeeded nor exclude a database-related failure. The native cause
remains **UNDETERMINED**. Reported PHP **8.4.27** / MySQL **8.4.11** and all 25
migrations belong to the disposable schema run. Transport, acknowledgement, cleanup
and evidence publication passed; the native result remains **FAILED**.

Operator evidence directory:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-observation-native-20261023T015403Z-6f317a8c9142e0bd`.
Launcher evidence directory:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-native-20261023T020049Z-699e906cf93f46f76697d239070838a9`.

| Operator-supplied record | Reported SHA-256 |
| --- | --- |
| Native report | `4e5e9eb2010ded1494c5b2e28aaf713f2fff150aa61b2a42b40eb1211e6b200b` |
| M6C-OBSERVATION-NATIVE-ACCEPTANCE.txt | `684c8ff1491235d0726f1cd5dc83d6f91bb54d6aab7968835903a08c9eda6685` |
| Operator SUMMARY.txt | `53e943e94802a54ccc9278046168efea4e700d355affe8456197119caef5d0a1` |
| Operator MANIFEST | `5de8949a5c921d6b550e0dfe70b58bde5af64654189f1b85f0203465cdb13f67` |
| Launcher MANIFEST | `0f37655b5439f8d4f39e84e61bf3e439328b66d5b8039416cae9441182e00` |

These are operator-supplied records, not files retrieved or independently verified
by this desktop task. Earlier native results and local validations below retain
their own source SHAs; they are not evidence that this failed native case passed.

Source inspection confirms that the starting instrumented Git helper inherits the
parent environment while protected production Git uses an explicit four-key
environment. The correction uses that same replacement: `PATH=/usr/bin:/bin`,
`HOME=/nonexistent`, `GIT_CONFIG_NOSYSTEM=1`, `LANG=C`. It does not merge HOME, XDG
or inherited Git configuration/repository/index overrides. Ordinary non-Git process
callers continue to inherit through the optional environment argument's null default.

The native lane retains absolute `/usr/bin/git`, `--no-optional-locks`,
`core.fsmonitor=false`, exact-repository `safe.directory`, explicit `-C`, exact HEAD,
clean `--porcelain --untracked-files=all`, zero exit and empty stderr. Permission
warnings are not discarded. The production `external()` wrapper can replace a
source dependency exception with generic conflict before candidate reads; it is
unchanged. Separate bounded `source_head`/`source_status` observations retain only
fixed mode/check and typed exit/stderr-nonempty/output-match facts. The first
`source_error` is retained alongside the outer `worker_error`; coordinator request
preparation is not labeled as an instrumented build child. Raw Git stderr, paths,
environment/configuration, command lines and arbitrary exception text are excluded.

The actual Windows before-driver confirms the shared inherited path accepts a clean
exact repository, but fixture-owned HOME/XDG/global configuration can make it fail.
Inherited `core.excludesFile` can hide a genuine untracked file; inherited repository/
worktree/index overrides can redirect verification to a clean foreign repository
with the same HEAD and falsely accept a dirty intended target. The actual public
claim path with fake PDO converts a reproduced source dependency error to conflict,
with no retained previous exception and zero query statements observed. The driver
passes 12 assertions across nine scenarios confirming those defects; it runs no
real database or protected native worker and changes no user Git configuration.

The corrected local source suite passes **113 assertions / 49 scenarios**, exit 0.
Actual Windows Git with an explicit four-entry Windows profile ignores those
inherited contaminants and accepts only the clean exact target. Wrong SHA, tracked
modification, genuine untracked content and nonzero exit remain rejected. Synthetic
exit-0/stderr cases at HEAD and status also remain rejected; they model warning
behavior and are not native permission evidence. The exact Linux default profile
and argument vector are separately forwarded through two real synthetic children.
Ordinary non-Git inheritance, timeout/output limits and owned cleanup remain intact.

The actual public claim call with fake PDO still throws generic conflict without a
previous cause and before any query statement; its separate source record survives
the wrapper. The existing worker-error producer/parser also retains the caught
outer conflict in the synthetic protocol check. No protected native worker is run
on Windows. Mode/schema/privacy/sink-failure controls pass, and the unchanged
concurrent synthetic observation protocols pass without serializing claimers.

All **11 sequential local suites pass 8,931 assertions**, each exit 0 on its first
run. This includes **7,382 assertions / 104 fake-command launcher scenarios** in
**676.7201238 seconds**; no native/container/MySQL acceptance is executed.
**268 PHP files** pass lint and **five Bash syntax checks** pass. Integrity preserves
all 237 production-path files, all 25 migrations/pins, all 20 native requirements
and the three operating-command blocks. The implementation record retains exact
commands, timings, changed-file inventory and platform limits.

Neither actual HOME nor Git stderr from the failed native children was independently
recovered. The separately supplied Linux/Git 2.47.3 probe demonstrated an inaccessible
synthetic HOME producing attributes/ignore stderr warnings with status exit 0 and
empty stdout under UID/GID 2000; an explicit clean environment removed the warnings.
It is mechanism evidence supplied to this task, not this desktop's execution, a
service test or proof of the droplet's historical error. Windows contamination
tests and modeled permission behavior remain distinct. See
[the complete source comparison and local record](sprint-8.8-m6c-local-implementation.md#instrumented-git-environment-isolation).

Historical handoff at this correction: all 20 native requirements remained. The
then-current operator-reported runtime image belonged to **abac019**. Later work required new reviewed-source provenance,
then focused source-identity verification under the actual UID/runtime environment,
followed by separately authorized native acceptance. No image is built, pulled or
relabeled here; no concurrency fix or native acceptance is claimed. Do not
automatically repeat unchanged transport smoke or backups. Backup/download remains
operator-reported COMPLETE with both copies and receipt retained; restore remains
NOT EXECUTED. Leave all `moby-detect-rro` directories alone.

## Instrumented worker failure at 79e6d63

The operator reports one invocation at `79e6d63e63a018f0f2f8dcf4aa75cee341b7bf8e`:
launcher exit **1**, **26.527271014 s** measured at a two-second monitoring cadence,
**671 attempted / 671 completed assertions** including setup. Explicit PASS:
`protected-startup-denials`, `protected-multipage-build`, `protected-replay`.
The multipage worker exited 0, outcome succeeded, and its case completed independent
artifact verification and the corrected canonical input_hash/release binding.
These passes do not establish the exact exception origin of the older failed run.

The instrumented `concurrent-claims` case failed in `native_cases` with
`RuntimeException / native_child_before_barrier`; 16 cases stayed UNREACHED.
Two cleanup-recorded children exited 1 with signal 0. Expected exits, last barrier,
recognized worker error and SQLSTATE/driver were not retained. The cause is
**UNKNOWN**; neither claim ownership, deadlock, lease failure nor a race is proven.
Six protected children finished. SQL/connection totals were not recorded.
Reported PHP **8.4.27**, MySQL **8.4.11**; 25 migrations ran only in the disposable schema.

Transport/capture passed: acknowledgement MATCH, normal identity PASS,
producer/consumer/verification exits **0/0/0**, no fallback. Native report:
**11411 bytes**, SHA-256 `5b4ff55506d4a2c761214d0d8aa124b7067341777072c881431c4fd479fe3dbf`.
Launcher evidence directory:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-native-20261023T001836Z-ec7247295bac388b7df5b3613f9a7cb3`.
Launcher MANIFEST SHA-256:
`32b4bf6797b979c6be32fca32ea7cbab46f13c71c863da8b960cc258113c9042`.
The operator reports 17/17 payloads and receipt/status agreement verified.

Operator evidence directory:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-canonical-native-operator-20261023T001750Z-57b69c51473987c3`.
Distinct operator file hashes:

| File | SHA-256 |
| --- | --- |
| M6C-CANONICAL-NATIVE-ACCEPTANCE.txt | `81aef562f74ca23e9fe5bb960ef812476e9fe9ea1c4424e735dd4c89de3bcb0f` |
| SUMMARY.txt | `90e6f469731e008b7f6676d3bb79dd314e6893ad0cb0942ea61266fb2206d89c` |
| MANIFEST | `91d7f871ed0a4b0b5021df51c637269370971c63e90660a874e62d65b31a8a70` |

The operator reports 72/72 payloads verified, cleanup/publication PASS and Docker
restored inactive/disabled. Exact scratch removal is launcher-attested, not
independently verified. `moby-detect-rro81790848` remains untouched. These are
operator-supplied facts; this desktop task retrieved or verified none of those files.

Local real synthetic children reproduced the missing pre-READY error information,
unobserved post-READY CLAIM/RESULT failure, waiting-peer context loss and both-child
cleanup loss, with a normal winner/loser control. The shared reader now validates
tags/payloads and retains expected messages, last observed barrier, identity/mode,
actual state and safe worker errors before rethrowing. Cleanup separately records
failure-time and pre-cleanup observations. A still-running peer or later cleanup
signal is not an explanation for the original failure. Unknown exits remain null.

The strict test-worker record uses fixed mode/attempted-phase/class/code enums,
plus typed PDO SQLSTATE/driver only when available in at most four existing causes.
It never records message, stack, SQL, DSN, credential, parameter, path or token.
Unknown JSON is digest/size metadata only; existing Build-only recognition and
2 KiB individual / 16 KiB aggregate diagnostic output bounds remain. Expected
audit/validation/stale RESULT semantics and protected exec stay intact. A phase is
an attempted operation, not proof of commit. Existing service wrappers can discard
causes; the helper cannot recover them or historical output.

A 300-child local terminal-read probe missed no final lines, so no read/exit change
was made. Actual instrumented adapter construction/wiring succeeds with canonical
private roots and existing fake PDO. Neither establishes the native root cause.
See [the implementation record](sprint-8.8-m6c-local-implementation.md#instrumented-worker-observation)
for exact source analysis, before/after evidence and fresh validation.

At that historical handoff all 20 native requirements remained; its cached image
was source-labeled **79e6d63**.
Later authorized preparation had to satisfy the **new reviewed SHA's** provenance.
No image was built, pulled or relabeled here. No automatic repeat of unchanged
transport smoke, backup or broad historical suites is prescribed. Migration 025
remains applied with SHA-256 `dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
Backup/download remains operator-reported COMPLETE, both copies and receipt retained;
restore NOT EXECUTED. Leave all `moby-detect-rro` directories alone.

## Native input assertion P1 follow-up

Review **5622132063** on `9a35b593f5214773c04de664ea283fb256b0350a` identified
[P1 4354571137: Canonicalize the native input-hash assertion](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4354571137).
The coordinator now calls a pure shared predicate that bounded-decodes stored
input, checks its canonical digest, and independently retains strict release/job
digest equality. `input_hash` remains the assertion label; every other native
verification is unchanged. The production validator is untouched.

The actual extracted predicate rejected two equivalent noncanonical encodings
before correction; it now accepts all three tested representations and rejects
seven tamper/binding/invalid-JSON cases. This is local fake-SQL/real-file evidence,
not a new MySQL/native run. See the [focused implementation record](sprint-8.8-m6c-local-implementation.md#native-input-assertion-review-correction)
for exact files, audit dispositions, timings and fresh validation counts. The
earlier 19-suite/2346-assertion result belongs to the production-fix task.

No native run of `9a35b59` has been reported. Historical 8118138 remains FAILED,
its exception origin unconfirmed; transport successes retain their own SHAs.
Later authorized preparation must establish the new reviewed SHA's image
provenance before protected native acceptance. No image ID is invented or image
built here. No automatic repeat of unchanged transport smoke; all migration,
backup/restore and operational boundaries below remain in force.

## October 22 failed run and canonical-input correction

At `8118138c890105f689fa145de7f5566f3263eee5`, the operator reports one native
invocation, **27.711082493 s / launcher exit 1**, ending with `actual_child_exit`
in `native_cases`, `protected-multipage-build`: expected 0, actual 1, signal 0,
last barrier IDENTITY, `Build-only invocation stopped: artifact_invalid.`
It remains **FAILED: 1 PASS / 1 FAIL / 18 UNREACHED; 563 attempted / 562 completed
assertions** including setup. Startup denials passed; four expected-denial children
and one failed protected build were recorded; no instrumented case ran. Reported
PHP **8.4.27** / MySQL **8.4.11**, 25 migrations only in the disposable schema.

Transport succeeded: acknowledgement MATCH, capture identity PASS, reader/Docker,
consumer and verification exits **0/0/0**, native.json **8502 bytes**, SHA-256
`4d62ae8f98070343f18a608f308903cdbb1939877a614c8e6ae13f8c25e760fd`.
No fallback; FAIL remained FAIL. Launcher evidence:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-native-20261022T224833Z-255afaa6947b3c56b8be5e22b16fc35b`,
MANIFEST SHA-256 `e6f8fc92ecae3810e51e55b3e6dbb029705680c3a7e38500159d15c4c0438dcf`
(17/17 entries plus receipt operator-verified). Operator evidence:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-native-retry-operator-20261022T224733Z-c4f684fb35eda2cd`,
MANIFEST SHA-256 `ab133c380ecf109179d8a6faf68a8040338e10f804a2f3728484d8f4a96d14a6`
(73/73 entries plus receipt operator-verified). These are attributed operator
reports, not desktop downloads/checksum verification, and not SUMMARY.txt hashes.
Cleanup/publication passed, Docker inactive/disabled; scratch removal was
launcher-attested only. Leave the new reported `moby-detect-rro` directory alone.

The [implementation record's call-path map and validation](sprint-8.8-m6c-local-implementation.md#stored-json-input-verification-correction)
identify a reproduced local defect: the JSON-column text was hashed raw during
artifact verification even though the input contract hashes canonical decoded
values. A native-content fixture with modeled JSON separators/key order failed
at the old validator line 139 after eight files were written, before sealing;
failure completion did not mask that exception. The one-predicate production
correction uses the existing canonical hash. The same regression passes 95
assertions including tampered neighbors, immutable bytes and replay. It uses real
files/renderer/builder/validator/store and fake SQL, not the protected Linux runtime.
Exact changed files are `private/classes/SiteArtifactValidator.php`, the new
`tests/WebsitePlatformM6CStoredInputTest.php`, this guide and the implementation record.
No diagnostic-only CLI addition was needed; error semantics and safety gates remain.

The reported native origin is still **unconfirmed**: IDENTITY is emitted before
the protected CLI exec and establishes no claim/render/seal milestone. This local
correction is not native acceptance. Prior transport smoke and native failures
remain at their original SHAs. The cached runtime image belongs to **8118138**;
after review, a separately authorized task must establish provenance for the new
exact SHA, run the focused stored-input/artifact checks in that source-matching
runtime and perform protected native acceptance. Do not invent an image ID or
relax admission. Transport, acknowledgement/hash validation, launcher signals,
owned cleanup and finalization are byte-identical, so do not automatically repeat
the transport smoke. The historical smoke proposal below remains reference only.
No server, SQL, image/container, install or native command is executed here.

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
an internal child; neither is named `*Test.php`. Discovery tests are synthetic:
`WebsitePlatformM6CNativeLauncherTest.php` uses pure contracts, fake engine commands,
disposable local Git fixtures and bounded synthetic PHP children;
`WebsitePlatformM6CNativeSetupTest.php` exercises the actual setup/report helpers
with a labeled permission model and real private local filesystem operations;
`WebsitePlatformM6CNativeDiagnosticsTest.php` uses real local child processes and
private files with explicitly modeled Linux metadata.

## Current child-diagnostic and report-transport correction

The operator reports a retry at `e2998fdadaeb19048556d303cc9d310fde2c2276`:
exit **1**, **24.156694505 seconds**. OBSERVED: migrations 001–025 were explicitly
recorded as applied to the disposable schema; the last explicit PASS was
`PASS protected-startup-denials (protected)` at child.stdout.txt:26. There were
**560 attempted / 559 completed assertions**, including setup checks. SOURCE-ORDER
INFERENCE: the next failure belongs to protected-multipage-build before success
text/artifact verification; five protected launches are inferred from progression,
not a surviving child inventory. Thus one explicit PASS, one source-order-attributed
failure and **18 UNREACHED**; instrumented workers were not reached.

The supplied excerpt omits the exact first primary error text. It is not evidence
that the error was `actual_child_exit`. No SQLSTATE/driver or exact container
PHP/MySQL version survived. Coordinator primary diagnostic and terminal summary
were emitted; no `Native report publication FAIL` appeared. Successful container
publication is nevertheless **UNPROVEN**. Host capture explicitly reported that
the daemon could not find `/run/m6c/report.json`; native.json is absent and the
fallback result was suppressed. Original cleanup/publication succeeded, owned
containers/network were removed, and working staging was not used. Overall FAILED
stands. The protected multipage build's underlying cause remains **UNKNOWN**.

Operator-only evidence directory:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-retry-failure-diagnosis-20261022-aub94vi6`.
`M6C-RETRY-FAILURE-DIAGNOSIS.txt` reported SHA-256:
`5eee6e59ecb00b559e51428c55c9f86271490a957493901018b5295ce84020e3`.
That directory's `SUMMARY.txt` reported SHA-256:
`53e5357061417b6e93a9ff0eb631a68a44eac0ca94e77660dc6ba17b8374691d`.
The operator reports eight payloads verified. This desktop task did not retrieve or
verify server evidence. The supplied directory date is retained verbatim.

Unexpected exits and barrier/timeout/output failures now emit `M6C CHILD FAILURE`
to the existing coordinator stderr capture before the generic assertion propagates.
Entries include case/phase, last successful barrier, expected exit when defined,
observed exit/signal/termination/running state, stream byte counts, bounded capture
lengths, truncation and digests. Unknown exits remain null. Expected nonzero startup
denials retain expected/actual status without unexpected-failure messages. Process
capture sizes are observed before temporary-file deletion; retained bytes remain
available afterward. Cleanup stays ownership-scoped with its original deadlines.

Only exact allowlisted worker outcome/error lines can appear as excerpts (at most
two per stream); arbitrary output becomes metadata and a SHA-256 of the bounded
capture, labeled as complete or prefix. No command, config, environment, raw SQL,
lease token or unfiltered child stderr is emitted. JSON escapes controls; individual
diagnostic lines are at most 2 KiB and aggregate emitted child diagnostics at most
16 KiB. Structured report/cleanup child entries also use sanitized summaries.
Diagnostic-write failure is secondary and cannot replace the primary exception.

After the existing checked final report rename, the coordinator emits one
`M6C REPORT PUBLISHED source=... run=... bytes=... sha256=...` acknowledgement.
This acknowledges exact published bytes for either PASS or FAIL, not acceptance
success or power-loss durability. Publication and acknowledgement-write failures
remain separate; acknowledgement failure does not overwrite the native outcome.

[Docker's documented copy corner cases](https://docs.docker.com/reference/cli/docker/container/cp/#corner-cases)
include tmpfs-mounted files. Both old docker-cp paths are replaced by the same
`m6n_capture` routine. This fixes an unsuitable transport mechanism; it does not
prove the original container report existed or explain the multipage build failure.
Before exec, inspection must match exact runtime ID, token/name/role/image and
network mode and the sole current network attachment, and Running must be true.
Unknown, foreign, disconnected, additionally networked, stopped or unavailable
containers are never executed or restarted for capture.

The fixed PHP reader opens only `/run/m6c/report.json`, after protected-parent,
canonical-path, regular-file, root UID/GID, 0644, single-link, device and <=1 MiB
checks. Opened metadata must match the checked inode; bounded reading detects
growth/partial bytes. No pending file, special file, archive extraction, shell,
TTY, additional mount/capability or application/SQL/worker bootstrap is used.
The host consumer reads at most 1 MiB + one rejection byte before writing a new
private `native-normal.pending` or `native-fallback.pending`. Reader/Docker and
consumer pipeline exits are retained separately; nonzero producer status cannot
be hidden by valid stdout. Verification requires complete JSON, exact SHA/token,
native flag, PASS/FAIL state, assertion count and the exact case/status inventory.
Only then may the pending capture become native.json. A valid FAIL is preserved;
the unchanged full acceptance validator still requires every case/lane to PASS.
Linux host owner checks read the effective UID from bounded `/proc/self/status`
using core PHP; they do not require a POSIX extension under `php -n`.

When present, the single publication acknowledgement must match source/run,
captured byte count and SHA-256. ABSENT is an explicit observation, not proof of
publication failure; contradictory acknowledgements fail verification. Normal and
at most one fallback attempt have separate exit/status/diagnostic files included
in the evidence manifest before finalization. Reader/Docker exit is the observed
combined exec result; it does not invent a distinct unobserved internal exit.
Each capture attempt shares a 15-second deadline across its stages, clipped to the
existing 1200-second run or 90-second cleanup deadline with finalization reserve.
Fallback cannot erase a failed normal attempt's primary exit. Capture failure after
native exit zero still prevents launcher success. Signal acceptance, primary/first
signal/secondary precedence, terminal boundary and manifest/receipt rules remain.

Local results and exact file inventory are recorded in the implementation record's
[child diagnostic and transport section](sprint-8.8-m6c-local-implementation.md#native-child-diagnostics-and-tmpfs-transport).
The existing prepared runtime image now matches **e2998fd**, not this correction's
future commit. Later authorized preparation must satisfy the new exact reviewed
SHA, followed by the focused transport smoke check below and separately authorized
native execution. No image pull/build/relabel or real container work occurs here.

## Historical failed operator run and storage correction

The operator reports one full invocation at
`740431fd1f3cae6f747983d75f6bb5309ecaddc3`: exit **1**, **18.60 seconds**, first
failure `chmod(): Operation not permitted` during coordinator storage setup,
before SQL or worker execution. Stdout reported **465 assertions**, which are
not 465 passing native cases. Overall **FAILED** and all **20 UNREACHED** remain
the historical result. Host-side `native.json` was missing. Cleanup and evidence
publication were PASS; the original launcher manifest verified 11/11. Both
containers and the network were removed; Docker returned inactive/disabled with
one start and one stop. Deployed source was unchanged and Apache active. No
retry, patch, merge or deployment occurred in that operator run.

Operator evidence directory:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-isolated-native-operator-20261021-z12w5b24`.
Report: `M6C-ISOLATED-NATIVE-VALIDATION.txt`; summary: `SUMMARY.txt`.
The operator-supplied SHA-256 of **that SUMMARY.txt only** is
`dc452d0fa040670f26a17b85760ee3fffd14a257d8d0263998b51df2f65a5957`.
The operator reports 47/47 manifest entries verified. This desktop task did not
retrieve these files or independently verify either manifest or checksum.

The old sequence was mkdir, chown(2000), chgrp(2000), chmod(0700).
Linux chmod requires the caller to own the inode or hold CAP_FOWNER;
CAP_DAC_OVERRIDE permits read/write/search bypass, not chmod ownership bypass.
After chown, the coordinator no longer owns the root. This is a source-level
diagnosis consistent with the reported failure, not a local Linux reproduction.
See [chmod(2)](https://man7.org/linux/man-pages/man2/chmod.2.html) and
[capabilities(7)](https://man7.org/linux/man-pages/man7/capabilities.7.html).

The coordinator now calls the tested storage gate before configuration or SQL:
verify protected root-owned ancestors and absent names; create restrictive roots;
check newly created metadata; chmod and verify while root-owned; chgrp(2000) and
verify; chown(2000) last; clear stat cache and verify directory type, UID 2000,
GID 2000, mode **0700** and the parent's device. Both worker roots must share that
filesystem. Uploads remain root:root 0755. Config remains root:2000 0440 for
`env.php`, and root:root 0444 for worker config/context. Each mutation is checked
separately; failures have bounded step codes and cannot reach configuration,
connections or worker launch. Existing names, links, unsafe ancestors or metadata
mismatches stop setup without repair. No capabilities or worker privileges change.

The bounded related-path audit found root-owned config/fixture assets and newly
created pending journals; their chmod calls do not follow a worker-owner transfer.
Startup-denial restoration uses CHOWN to restore checkout ownership and chmod on
the separate, still-root-owned config. Those mutation results are now checked.
The existing sealed-artifact byte fault still uses DAC_OVERRIDE and never changes
worker ownership or mode. Fixture/case/process-helper files are byte-identical.

At the historical storage correction, `safeReport` had become true after namespace/attestation checks, before
source verification and storage setup. No coordinator warning-to-exception
handler was installed by the included Linux guard (its handler is CLI-only).
A false chmod therefore reaches `m6nCheck`, while a thrown warning is also caught
by the outer Throwable catch. Finally attempted direct serialization/write to
`/run/m6c/report.json`; chmod's return was unchecked. The source does not establish
why the original host report was absent. The unchanged launcher logs the main
capture PASS/FAIL in `report.txt`, leaves main docker-cp stderr on its caller's
stderr, preserves a nonzero native exit, and attempts a silent fallback copy
before teardown. Missing host evidence alone cannot distinguish in-container
absence, report finalization failure, capture failure or another cause.

The storage correction's reporter verifies the fixed protected report directory after attestation
and rechecks its identity before publication. It retains FAIL, stage, attempted
assertions, separately completed successful assertions, primary failure and all
UNREACHED cases. One exclusive pending file receives at most 1 MiB; write, flush,
mode, file metadata, close and final rename are checked. Only the rename publishes
`report.json`; incomplete pending bytes are never accepted as completed evidence.
Primary and publication failures have separate bounded sanitized stderr messages;
no raw exception/config/environment dump is emitted. Unattested direct invocation
has no report target and cannot choose one. This remains within the existing suite
deadline, with no retry. The original missing-report cause remains **UNPROVEN**.
At that correction, launcher capture, signal handling, precedence, ownership, hashes
and receipts were unchanged; its regression suite was not rerun then. Publication
PASS still does not establish native PASS, and absent native evidence cannot pass.

Focused local validation: **734 assertions / 62 scenarios**, exit 0, **1.0195261 s**;
M6B scope **42**, **13.7497797 s**; M6C scope **53**, **8.4790726 s**, both exit 0.
The setup order/capabilities are explicitly simulated. Directory creation, report
write/flush/rename, rejected existing paths and private fixture cleanup use actual
local files; no successful Windows chmod/chown is Linux permission proof. Initial
sandbox rename failures and all rechecks are retained in the
[implementation record](sprint-8.8-m6c-local-implementation.md#native-storage-setup-and-report-correction).

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

All rows below describe the executable contract. Their source-specific execution
results now appear in the [acceptance record](sprint-8.8-m6c-native-acceptance.md#completed-native-result):
all 20 passed at `2ab14c7…`. The mapping below remains scoped; it is not full
completion of every listed M6 plan identifier.

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

## Later preparation and operating commands — not run by this correction

The operator separately reports Linux launcher PASS (**3981 assertions / 86
scenarios**) and image preparation/check-only PASS at **740431f**, followed by the
failed native invocation above. These are historical reports, not rerun here.
That historical runtime image was source-labeled for 740431f; the operator's latest
reported runtime now belongs to **abac019**. After this correction is
committed, separately authorized preparation must satisfy runtime-image provenance
for the **new exact reviewed SHA**, then verify source identity under the actual
UID/runtime environment before separately authorized native acceptance. Do not
relax the source check, rewrite old labels
as evidence, invent a digest or claim a new image already exists. Verified pinned
base/MySQL images may be reusable. No pulls or builds occur in this desktop task.
The recipe below describes initial provisioning; an existing verified client or
cached images must not be deleted or recreated merely to replay it.

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

### Historical separate transport smoke proposal

This proposal was not executed by the desktop authoring task. The operator's
subsequent transport smoke and acknowledged native capture at 8118138 remain
historical evidence at their own SHAs. Do not automatically repeat the smoke for
the canonical-input correction; the transport path is unchanged.

If separately authorized for a demonstrated transport change, this is a proposal, not permission to
provision or run it now. An operator must first prepare exactly one fresh isolated
runtime and one internal run-owned network, with no MySQL container and no
application worker. Use the reviewed native runtime image/source SHA, existing
approved capabilities and tmpfs layout (including `/run/m6c`), the existing single
read-only input bind/source-pack checkout procedure, and native owner/name/role/
network labels. No new mount, capability, host configuration or image-label edit.
The namespace checkout must contain the exact reviewed helper files. Prepare the
fixed rootless client and volume admission prerequisites separately; the script
below assumes those have passed native admission and refuses foreign/stopped
resources. Its inputs identify only those freshly approved smoke resources.

Save the following as a private proposal outside the clean checkout. It invokes
the actual shared capture routine and existing guarded teardown, not a substitute
copy demonstration. Run only after explicit approval of that preparation and this
procedure. All generated report cases are UNREACHED with status FAIL; transport
success is not a native acceptance result. The final manifest should cover the
acknowledgement, captured bytes, capture metadata and teardown status.

```bash
# PROPOSED ONLY. Run from the exact clean, independent volume checkout.
set -Eeuo pipefail
: "${WORK_SHA:?exact reviewed source SHA}"
: "${SMOKE_TOKEN:?fresh 32-hex run token}"
: "${SMOKE_RUNTIME_ID:?verified full runtime ID}"
: "${SMOKE_NETWORK_ID:?verified full internal network ID}"
: "${SMOKE_RUNTIME_IMAGE:?verified source-matching image ID}"
: "${PHP_BASE_DIGEST:?verified base image digest}"
: "${HOST_PHP:?absolute real executable host PHP}"
source tests/RunM6CNative.sh
repo=$(pwd -P); sha=$WORK_SHA; token=$SMOKE_TOKEN; php=$HOST_PHP
runtime_id=$SMOKE_RUNTIME_ID; runtime_image=$SMOKE_RUNTIME_IMAGE
network_id=$SMOKE_NETWORK_ID; php_base=$PHP_BASE_DIGEST
base=/mnt/ubo_stage_testdata/codex-validation
client=/run/user/1000/ubo-m6c-client
started=$SECONDS; interrupted=''; volume_id=''
[[ $(uname -s) == Linux && $(hostname -s) == ubo-stage-app && $(id -un) == codex-validation && $(id -u) == 1000 ]]
[[ $repo == "$base/checkouts/"* && $(m6n_git rev-parse HEAD) == "$sha" && -z $(m6n_git status --porcelain=v1 --untracked-files=all) ]]
m6n_volume
m6n_docker image inspect "$runtime_image" | m6n_guard runtime-image "$runtime_image" "$sha" "$php_base" >/dev/null
m6n_docker inspect "$runtime_id" | m6n_guard capture-owner "$token" "$runtime_id" "$runtime_image" "$network_id" >/dev/null
m6n_docker network inspect "$network_id" | m6n_guard network "$token" "$network_id" "$runtime_id" >/dev/null
# Pin all evidence writes to the verified volume, as in the native launcher.
exec {base_fd}<"$base"; storage="/proc/$$/fd/$base_fd/."
[[ $(stat -c '%d:%i' "$storage") == "$(stat -c '%d:%i' "$base")" ]]
evidence=$(mktemp -d "$storage/evidence/m6c-transport-smoke.XXXXXXXX")
evidence_path="$base/evidence/${evidence##*/}"
printf 'PROPOSED TRANSPORT SMOKE; no SQL or worker; source=%s\n' "$sha" > "$evidence/report.txt"
runtime_attempted=1; network_attempted=1; db_attempted=0
db_id=''; disk=''; supervisor=''; lock_identity=''
trap 'm6n_signal 130' INT
trap 'm6n_signal 143' TERM
trap m6n_finish EXIT
# Constant PHP program, synthetic FAIL only; publisher writes the known tmpfs path.
m6n_docker exec "$runtime_id" /usr/local/bin/php -n -d memory_limit=128M -r '
require "/opt/ubo/tests/support/WebsitePlatformM6CNativeReport.php";
$r=M6CNativeReport::initial($argv[1],$argv[2]); $r["transport_smoke"]=true;
M6CNativeReport::finish($r,M6CNativeReport::location(),fn()=>[],fn($s)=>fwrite(STDERR,$s));
exit(isset($r["report_failure"])||isset($r["acknowledgement_write"])?2:0);
' "$sha" "$token" > "$evidence/child.stdout.txt" 2> "$evidence/child.stderr.txt"
m6n_capture normal
"$php" -n -r '
$r=json_decode(file_get_contents($argv[1]."/native.json"),true,32,JSON_THROW_ON_ERROR);
$m=json_decode(file_get_contents($argv[1]."/capture-normal.json"),true,32,JSON_THROW_ON_ERROR);
if($r["status"]!=="FAIL"||($r["transport_smoke"]??false)!==true||count($r["cases"])!==20
 ||array_unique(array_column($r["cases"],"status"))!==["UNREACHED"]||$m["acknowledgement"]!=="MATCH")exit(2);
echo "Transport smoke captured exact acknowledged FAIL bytes; native cases NOT EXECUTED.\n";
' "$evidence"
# EXIT uses exact-owner cleanup and existing bounded manifest/receipt publication.
exit 0
```

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

Historical desktop results and retained failures are recorded in the implementation
record's [teardown correction section](sprint-8.8-m6c-local-implementation.md#native-teardown-review-corrections)
and [storage correction section](sprint-8.8-m6c-local-implementation.md#native-storage-setup-and-report-correction),
and [child diagnostics and transport](sprint-8.8-m6c-local-implementation.md#native-child-diagnostics-and-tmpfs-transport).
The current correction is recorded in
[instrumented Git environment isolation](sprint-8.8-m6c-local-implementation.md#instrumented-git-environment-isolation).
This desktop correction executes no recipe, native namespace, container, MySQL or
protected worker. Operator-reported native failures remain separate from these
local results. A later authorized rerun may expose further blockers; stop and
record them without weakening the factory or applying working-staging SQL.

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
