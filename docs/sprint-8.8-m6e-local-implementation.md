# Sprint 8.8 M6E — Local Apache publisher implementation

Status: **PARTIALLY IMPLEMENTED / REVIEW REQUIRED**. Operational publishing remains
disabled without the verified installed host contract. M6 remains IN PROGRESS;
M6F–M6G remain NOT STARTED. No M6E native, Apache, TLS or browser acceptance is claimed.

Baseline: `303b073658d7eb08e6086237a70dd4d285dc121c`; branch
`codex/sprint-8.8-m6e-apache-publisher`. Remote main matched the baseline before branch
creation, with no existing M6E branch/PR. The retained M6D branch and detached merge
verification worktree were preserved. The baseline has reviewed M6D `2dc88e439…` as its
second parent; native-tested `7cdbbeef639…` is an ancestor of that reviewed head.

**M6D — COMPLETE / DB-ONLY NATIVE V3 PASS / FORMALLY CLOSED** under the
[final closeout receipt](https://github.com/fvd8383/ultimate-back-office/pull/130#issuecomment-6563165702).
Its native PASS belongs only to `7cdbbeef`, never this implementation or the merge.
Historical failures and pre-merge reports are not rewritten or rerun.

## Apache commissioning integration following 90d891c

**APACHE SERVING/ACCESS — IMPLEMENTED LOCALLY / NATIVE UNEXECUTED.** Starting
SHA: `90d891c8bb99a51d24c26ebf99d8e6a67cbc0a0f`, verified with the exact existing
branch and a clean worktree. PR #131 was OPEN DRAFT at that SHA. This section
supersedes earlier Apache implementation-gap descriptions only; historical test
results remain attributed to their original sources.

The established order is apache, filesystem, ownership, assets, runtime, health,
recovery and provisioning. The existing assets producer creates and removes its
release-A component inside its own later probe. Apache therefore cannot use that
component, its removed files, or an invented earlier assets result. The bounded
solution is a **distinct Apache-owned A observation** in the original commission
service. No gate moves and no artifact ownership is transferred between producers.

[CommissionApache.py](../tests/support/WebsitePlatformM6ECommissionApache.py) uses
the existing source, transfer, Publisher.stage, observer, private serving and cleanup
primitives. Its fixed component-apache-a control/data namespaces, command identities,
plan and immutable evidence names are separate from component-a. Its separate
component-apache-https resource tree reuses the existing fixed private endpoint
`127.0.0.4:8443`; it introduces no public listener, installation or public entrypoint.
The later assets component starts only after Apache cleanup has completed. The
internal PHP/Python child modes select only these two fixed namespaces, not arbitrary
roots, releases, commands or deployment targets.

The Apache probe retains its module, unavailable-endpoint and unauthorized-client
baseline, then independently reads the original two-release source bundle. Before
starting the helper, the A worker observation must equal that bundle's exact
original A manifest, artifact and producer. The actual exporter/receiver and publisher
stage A into its own immutable candidate and asset pool. Authority remains synthetic,
stage-only and exact-request-bound. Fence 1, phase 1 and the unsettled component
journal remain until removal of the complete owned namespace; no activation,
settlement, deployment row, target pointer or original journal mutation is added.

The shared component lifecycle now exposes observation, immutable checkpoint and
finish boundaries. Standalone A and assets still use the existing immediate runner.
Apache defers finish until the unchanged collector's cleanup callback, so both its
own checkpoint and the gate's before-restore checkpoint exist while the reader is
alive. The existing configtest must succeed before reader startup. GET/HEAD cover
every original public file, with MIME, length, SHA-256, security headers and CSP;
existing real PHP/CGI/SSI/private canaries must be denied, alongside method,
Host/SNI/trust/client-authentication, listing, MultiViews, fallback and route checks.
The original independent artifact inventory hash and separate before/after serving
resource identities remain mandatory.

The immutable `m6e-commission-apache-serving-v1` record binds the original commissioning
identity, source-bundle digest, exact component plan, source/producer measurements,
configtest, reader, HTTP summaries, resource inventory and original operational state
to the full before-cleanup capture. The existing Apache gate gains one digest field,
`apache_sha256`. Offline validation reopens those immutable evidence bytes and checks
the original capture plus the linked successful termination/cleanup record. It never
reads a removed candidate or consumes the assets producer's observation. Failed or
partial diagnostics cannot satisfy these checks. No receipt schema changes.

The original 90-second component deadline also bounds the collector's independent
inspection while the reader remains alive. Cleanup restores only the original
absolute supervisor deadline; it does not refresh admission, the 420-second service,
the 1,200-second watchdog or the 120-second cleanup reserve. Capture failures,
uncertain reader exits, changed/missing resources, inode substitutions and incomplete
cleanup preserve the first failure and retain LIMITED/INCOMPLETE outcomes. The final
original environment observation still checks database state, source artifacts,
operational pools, pointers and journal.

Exact changed-file inventory for this increment (15 files):

- New: `tests/support/WebsitePlatformM6ECommissionApache.py` and
  `tests/WebsitePlatformM6ECommissionApacheTest.py`.
- Commissioning: `tests/support/WebsitePlatformM6ECommissionProbes.py`,
  `tests/support/WebsitePlatformM6ECommissionEvidence.py`,
  `tests/support/WebsitePlatformM6ECommissionChild.php`,
  `tests/WebsitePlatformM6ECommissionControlTest.py`.
- Shared component boundaries and local fixtures:
  `tests/support/WebsitePlatformM6EComponent.py`,
  `tests/support/WebsitePlatformM6EComponentHost.py`,
  `tests/support/WebsitePlatformM6EComponentChild.py`,
  `tests/support/WebsitePlatformM6EComponent.php`,
  `tests/support/WebsitePlatformM6EComponentLocal.php`,
  `tests/WebsitePlatformM6EComponentTest.py`.
- Scope: `tests/WebsitePlatformM6EScopeTest.php` (two exact new paths).
- This document and `docs/sprint-8.8-m6e-isolated-validation.md`.

Production executables/templates, closed M6C/M6D contracts, migrations, all eight
gate names, native-readiness predicates, host admission and publication policy are
unchanged. The Apache `retained-release-static-responses` MISSING classification
is deliberately retained pending native evidence. The other five incomplete
producers remain unchanged. **No operational receipt may be issued.**
The [executed local validation and remaining native obligations](sprint-8.8-m6e-isolated-validation.md#apache-commissioning-local-controls-following-90d891c)
separate Windows models from native proof. Final commit identity, exact tested/index/
committed hashes and the one review handoff are recorded outside Git in
`m6e-apache-commission/SUMMARY.txt`; the commit cannot contain its own resulting SHA.

M6C/M6D remain FORMALLY CLOSED; M6E PARTIALLY IMPLEMENTED / REVIEW REQUIRED;
M6F/M6G NOT STARTED; all six commissioning gates OPEN PENDING NATIVE EVIDENCE;
production UNAUTHORIZED. No server, SSH, sudo, native bootstrap/Apache/systemd,
real SQL, deployment, operational receipt, public activation or merge was performed.

## Supervised standalone A and required configtest following 71b1839

Starting SHA: `71b1839ee3390ea7e4c4c2bfdba258c6e38ea6d0`, verified on the
existing clean `codex/sprint-8.8-m6e-apache-publisher` branch. PR #131 was OPEN
DRAFT; its exact-head automated review completed without findings. The ending
SHA is the single commit introducing this section. Its full SHA and exact
tested/working/index/committed hashes are recorded after commit in the external
`m6e-entry-configtest/SUMMARY.txt` and the exact-head PR review request: a commit
cannot embed its own resulting SHA.

The fixed public request is `WebsitePlatformM6EPreparation.py --component-a`.
[ComponentEntry.py](../tests/support/WebsitePlatformM6EComponentEntry.py) dispatches
only `ubo-m6e-component-a.service`; its protected `--component-a-owned` mode is
not exposed by Preparation. Bootstrap owns the fixed source-pinned unit, and the
existing watchdog stops it before acquiring the lifecycle lock. The service uses
the existing 420-second envelope and control-group termination. The component
still has 90 seconds, the original watchdog 1,200 seconds and cleanup reserve
120 seconds. Neither dispatch nor execution restarts the timer or admission.

Both entry functions acquire the original lifecycle lock and reload the original
ledger. Before dispatch, READY/pristine inspection, original immutable bootstrap
publication/handoff, source inventories, configuration, tools/modules, VM/boot,
expiry and original database/target/journal checks must pass. Dispatch records
intent before systemctl, then binds the observed MainPID, invocation and cgroup
while the service waits on that lock. Protected execution checks the exact
fragment bytes/path, absence of drop-ins, cgroup, MainPID, systemd InvocationID
and process INVOCATION_ID against the admitted request and completed dispatch
command. The internal component runner also checks supervisor admission before
creating its component record or resources. Repeated, conflicting and direct
unsupervised execution rejects. The original commissioning route retains its
separate supervisor admission and eight-gate policy.

This route calls the existing Component.run lifecycle directly, without collecting
eight producers. It uses original release A, its PHP/Python transfer and
Publisher.stage, synthetic stage-only authority, fence 1 and unsettled journal.
Independent artifact and candidate HTTPS observations, original observer inventory
binding, immutable capture before stopping the reader, quiescence and exact owned
cleanup remain required. The original operational target is checked after success
and failure. Unknown or changed resources remain LIMITED. Historical READY
publications and commissioning observations are never rewritten.

After creating and verifying the owned private config, ComponentHost executes
the fixed pinned `/usr/sbin/apache2 -t -f <owned-component-apache.conf>` with an
argv array. Its command intent is durable before execution. The ten-second
configtest is subordinate to the absolute component/service/watchdog deadlines;
stdout is bounded to 2 MiB and stderr to 16 KiB by the existing process owner.
Evidence retains sanitized classifications, hashes and process metadata, never
raw diagnostics. Exit zero, a reaped matching command/process and unchanged
device/inode/type/UID/GID/mode/link-count/content identity are required immediately
before reader spawn. Missing/changed command ownership, timeout, output overflow,
unknown exit or config mutation prevents Apache startup. Failed configtest can
remove only unchanged explicitly owned resources after proven process quiescence;
uncertain startup or changed identity remains LIMITED.

The immutable `m6e-supervised-component-a-v1` execution result binds the original
admission and hashes of the component before-cleanup, owned-before-remove and
result records. Success means **ORIGINAL A VERIFIED AND REMOVED** only. It does
not award an Apache gate PASS, issue a host receipt, accept an installed workflow,
activate anything publicly or establish full native acceptance. Windows native
service/UID/Apache/TLS behavior remains explicitly modeled.

Exact changed files for this increment:

- `tests/support/WebsitePlatformM6EComponentEntry.py` (new fixed entrypoint).
- `tests/WebsitePlatformM6EComponentEntryTest.py` (new focused controls).
- `tests/support/WebsitePlatformM6EPreparation.py`,
  `tests/support/WebsitePlatformM6EBootstrapPlan.py`,
  `tests/support/WebsitePlatformM6EBootstrapHost.py`,
  `tests/support/WebsitePlatformM6EBootstrap.py` (routing, owned unit, supervisor
  observation and watchdog cgroup quiescence).
- `tests/support/WebsitePlatformM6EComponent.py`,
  `tests/support/WebsitePlatformM6EComponentHost.py`,
  `tests/WebsitePlatformM6EComponentTest.py` (admission, configtest and local adapters).
- `tests/WebsitePlatformM6EScopeTest.php` (two exact new M6E paths only).
- This document and `docs/sprint-8.8-m6e-isolated-validation.md`.

Production publisher/admission, live Apache templates, deployment services,
requirements, receipt schema, all migrations and closed M6C/M6D contracts are
unchanged. The [validation record](sprint-8.8-m6e-isolated-validation.md#standalone-a-and-configtest-local-controls)
contains executed counts and native prerequisites. All six commissioning blockers
remain open: apache, assets, runtime, health, recovery and provisioning. Native
execution requires separately authorized disposable-host preparation and explicit
fixture-local MySQL/SQL authorization; no synthetic database can satisfy that
future requirement. No native execution or host preparation was authorized here.

## A-only private candidate serving following 18b4900

Source HEAD: `18b4900ebad62c511587c4bb4498000460cf5129`; PR #131 was OPEN DRAFT,
with its completed exact-head review reporting no major findings. The introducing
commit is the implementation identity; its full new SHA, exact source/index/commit
inventories and one review handoff are recorded after commit in the external
`m6e-candidate-https/SUMMARY.txt`. A commit cannot contain its own resulting SHA.

This bounded increment extends A's existing original PHP-to-Python transfer and
immutable stage. Fence 1 remains at phase 1, unsettled. There is no release B,
activation, current pointer, deployment success or settlement. The original
operational database/target/journal observations must remain unchanged.

[ComponentHost.py](../tests/support/WebsitePlatformM6EComponentHost.py) owns a
separate `/var/lib/ubo-m6e-fixture/component-https-<run-id>` namespace. Its config,
canary directories/files, runtime directory and generated PID file have ledger
intent before creation; process intent precedes spawn. Resources stay outside the
original component inventory. The original observer hash is never replaced.
The new fixed listener is `127.0.0.4:8443`, using `candidate.m6e.invalid` and the
existing admitted certificate paths without copying keys. It points directly to
A's staged candidate and retained asset pool. The normal fixture bindings remain
unchanged. There are no operational Apache, service, account or routing changes.

The complete candidate policy is rendered from the unchanged reviewed Apache
template. Only that candidate vhost is included. The dedicated config pins the
already admitted module inventory, adds fixture-only AuthMerging And so Location
authorization also preserves preceding directory/file denials, and requires client
TLS authentication, exact Host and SNI, and supplies the same static policy to a separate alias containing real
harmless PHP/CGI/SSI, dotfile, private, metadata, journal, source, proxy and listing
canaries (the harmless CGI script is 0755). Their independent identities, modes
and contents are checked. Existing
canaries require 403; missing-file 404 alone is not handler-disable evidence.

[CommissionServing.py](../tests/support/WebsitePlatformM6ECommissionServing.py)
adds a single-manifest CandidateChecks adapter using the existing response, file
and denial predicates. Every original public file is checked by GET and HEAD;
index, marker, CSS, PNG and PDF must match original length, MIME and SHA-256,
with no-store/noindex, CSP, frame, referrer, content-type and permissions policies.
Negative requests cover methods, unauthorized clients, Host/SNI/trust/client-cert
failures, traversal, private paths, executable handlers, listings, MultiViews and
fallback routes. Bounded evidence contains counters and hashes, not response bodies,
private headers, credentials or certificate contents.

[Apache.py](../tests/support/WebsitePlatformM6EApache.py) shares its TLS client
implementation with a fixed component endpoint; existing operational endpoint
validation remains exact. [ComponentChild.py](../tests/support/WebsitePlatformM6EComponentChild.py)
authors a worker-UID HTTP client child supervised within the original absolute
90-second component budget, plus independent before/after serving resource walks.
The native path checks the Apache reader's real/effective/saved/filesystem UID and
GID, supplementary groups and zero effective/permitted capabilities before and
after HTTP. A slow socket/TLS read cannot outlive the parent's deadline unobserved.
The existing
420-second commission bound, higher watchdog and cleanup reserve are unchanged.

[Component.py](../tests/support/WebsitePlatformM6EComponent.py) orders stage, original
observer, serving, independent after-serving verification and immutable capture
before reader shutdown. Cleanup requires the original artifact inventory, complete
matching serving inventories and tracked process quiescence. The existing ledger
and per-object cleanup checks are reused, including inode/type/owner/mode/link-count
checks and static content hashes. Only Apache's own PID file may disappear after
verified normal shutdown. Unknown exits, missing/malformed observations, extra or
replaced resources and failed capture retain LIMITED/INCOMPLETE. The first serving
failure remains primary; after-serving, capture and cleanup failures are separate.

[ComponentModel.py](../tests/support/WebsitePlatformM6EComponentModel.py) and
[component controls](../tests/WebsitePlatformM6EComponentTest.py) use real private
original files, real processes, the original transfer/staging/observer code and a
separate reader holding a retained PNG descriptor until shutdown. Windows HTTP,
TLS, Apache policy, UID/mode, Unix socket and Unix filesystem semantics remain
explicit models. They do not establish any native acceptance result.

The full component suite passes 22 tests (58 subtests, 464 assertion calls); the
final five candidate controls pass 24 subtests / 181 assertion calls, and the
existing serving suite passes 12 tests. The positive case observes 55 requests,
seven original files and 20 serving resources. [Executed results and limitations](sprint-8.8-m6e-isolated-validation.md#a-only-candidate-serving-local-evidence-and-native-boundary)
include durations, fault outcomes, scope, syntax and documentation checks.

Native Linux/Apache is **AUTHORED / UNEXECUTED**. M6C/M6D remain **FORMALLY CLOSED**;
M6E is **PARTIALLY IMPLEMENTED / REVIEW REQUIRED**; M6F/M6G are **NOT STARTED**;
production is **UNAUTHORIZED**. All six commissioning MISSING entries remain.
The next increment is separately authorized native execution of this A-only
boundary on the admitted disposable fixture, including actual config/TLS/identity,
reader quiescence and cleanup evidence. It does not authorize a second release
or the next commissioning producer.

## Component review corrections following 8916b25

The exact-head review of 8916b25 completed **with two P2 findings**:
[ancestor access](https://github.com/fvd8383/ultimate-back-office/pull/131#discussion_r4447829150)
and [generated inode adoption](https://github.com/fvd8383/ultimate-back-office/pull/131#discussion_r4447829161).
Both corrections are confined to component test support; production changes are zero.

The root-owned component-data ancestor now uses 0755 in both creation and the exact
inventory policy. The control ancestor remains 0711, served remains helper:apache
0750 and journal remains helper:helper 0700. Source, served-file, journal and IPC
restrictions are unchanged. Search permission alone on 0711 did not permit the
unchanged Tree constructor's O_RDONLY | O_DIRECTORY opens. The local adapter now
runs that actual constructor with explicit UID/mode checks rather than bypassing
it with __new__. The checks distinguish parent traversal, directory reading and
access to protected child files. Windows models are not native UID enforcement.

The independent observer retains its original canonical inventory SHA-256. Once
processes are quiescent, owned() captures the full current namespace and requires
that exact hash before adopting generated output or publishing removal ownership.
Paths, device/inode, type, UID/GID/mode, link counts and validated link targets are
bound. Missing/malformed observer measurements and different inventories reject.
A failed observer cannot authorize cleanup of staged output. Early partial
construction/transfer retains the existing strict ownership behavior; unknown
scratch remains LIMITED and no observer hash is fabricated.

The restricted control ancestor need not be made listable: the observer stats its
fixed root-owned child names and enumerates helper-owned IPC. The operator's full
walk must match the earlier hash. Unknown control-root entries therefore reject,
and directory-walk errors no longer silently omit entries. The data subtree is
fully enumerated by both observers.

Cleanup seeds each tree's ledger children from the already bound inventory before
capture_tree(), preserving the original expected identities. Capture/release then
reject replacement, omission or extra children instead of establishing a later
baseline. A narrow filesystem wrapper also compares full file identity at the
ledger's unlink boundary, including link count; directories retain all immutable
identity fields while their link counts may fall as owned children are removed.
The existing ledger deletion algorithm and per-object checks remain unchanged.
The original observer evidence and first failure survive LIMITED/INCOMPLETE results.

Before correction, the actual local lifecycle accepted an identical-byte,
different-inode index.html substitution after observation, deleted it and reported
STAGED_AND_REMOVED / REMOVED. The corrected case rejects before any deletion and
retains the replacement and journal. Deterministic races during tree capture and
after deletion inventory are rejected, and a hardlink introduced at the final
removal boundary is retained. The old inode is held outside the component tree in
controls so the negative does not depend on allocator reuse or an extra-path error.

Successful cleanup still discards fence 1 only with the complete isolated namespace
after child quiescence; it never rewinds a live or operational journal. Original
bootstrap state remains untouched. All six MISSING entries and production default
denial remain. M6C/M6D stay FORMALLY CLOSED; native Linux validation, release B,
Apache/HTTPS, retention and remaining commissioning producers are still outstanding.

## Isolated release-A component lifecycle following cd4af965

The component staging lifecycle is implemented in test support. Its native Linux
path is **AUTHORED / UNEXECUTED**. This is not an operational deployment or a
commissioning gate. The exact starting head was
`cd4af96520f443ccbd6438b15f78364960ceb3c6`; its completed review had no findings.
M6C/M6D remain FORMALLY CLOSED. All six MISSING entries remain unchanged.

[Component.py](../tests/support/WebsitePlatformM6EComponent.py) owns the one-attempt
lifecycle, invoked from the existing assets probe after its unchanged baseline and
source checks. [ComponentHost.py](../tests/support/WebsitePlatformM6EComponentHost.py)
uses the existing ledger and original commissioning/watchdog budgets: 90 seconds
inside the 420-second service, with the original cleanup reserve. There is no
stage retry. Native process intent precedes spawn; each child waits on its private
stdin until its real PID is durable. Linux pidfds address the owned children;
remaining process-group/session members prevent namespace removal. No unit,
watchdog, account, operational socket template or publisher entry changes.

Only the original bootstrap release A is staged. The worker UID 2000 rechecks
original release/job/producer rows, the sealed store and the reviewed exporter,
then repeats source inspection before sending actual UBT1 bytes. The helper UID
2102 accepts one private Unix connection, checks SO_PEERCRED UID and the exact
operator-supplied worker PID before dispatch, and uses the original receiver,
Publisher, Tree, fence and no-overwrite primitives. The component authority is
explicitly synthetic, expiry/run/namespace/release/request bound and stage-only.
It is absent from operational entry points and creates no deployment lease,
success row or database target pointer. No SitePublisherSession is opened.

The roots are fixed to the admitted run:

- /run/ubo-m6e-fixture/component-a-<run>: protected plan and helper-owned IPC.
- /var/lib/ubo-m6e-fixture/component-a-<run>: separate helper-owned served pool,
  staging scratch, lock and journal.

All root, scratch-container, socket and candidate intent is ledgered before its
producer can act. The component may create its own candidate; it never creates
current or changes operational pointers. Its journal reaches fence 1/phase 1 and
is discarded only with the entire independently owned namespace after quiescence.
The original target is checked before staging, after staging and after cleanup:
empty pools, pristine journal, absent current/candidate/receipt and unchanged
read-only worker/database inspection. No fence is rewound.

A third, independently launched observer acquires the lock and reopens the actual
files. It checks the original retained manifest, release identity, every public
file's MIME/size/digest/bytes, approved PDF, retained pool objects, exact native
UID/GID/modes/link counts and closed namespace inventory. Helper success alone is
insufficient. Worker write denial and helper direct-source denial are separate
observations. Source, receiver, helper, acknowledgement and observer evidence are
retained separately under the component profile, never converted into a gate PASS.

The first immutable capture precedes child termination and includes resource
intent/identities, journal digest/fence/phase, process status and the first failure.
A second immutable capture records quiescent ownership before removal. Cleanup
rechecks identities, removes only the exact owned candidate and complete owned
trees, and preserves unknown, foreign, replaced or interrupted scratch as
LIMITED/INCOMPLETE. The existing watchdog stops the commissioning cgroup; an
unreleased component producer tree is intentionally not adopted by generic
bootstrap cleanup. Its unknown producer leaves that cleanup LIMITED.

The actual cold PHP include graph exposed a commissioning inventory dependency:
its earlier single-verifier allowlist rejected CommissionServing/NativeSetup.
The test-only inventory now accepts exactly those two worker files plus
Component.php, hashes their protected bytes, and still rejects arbitrary support
paths, wrong owners/modes and changed pins. No production host admission changed.

Local controls use this same runner and actual distinct PHP/Python processes with
original M6C release-A bytes. Windows UID/permissions, Unix sockets, dirfd,
renameat2, symlink metadata, flock and directory durability are explicit adapters;
private loopback TCP transports the actual reviewed protocol. They are not Linux
identity or durability proof. The local fixture's optional A-only mode preserves
the existing two-release default. Remaining work includes release B, private
HTTPS/Apache responses, retention/conflict validation, supervised runtime authority,
health, recovery and provisioning. No receipt or full native acceptance is issued.

## Serving-source runtime dependency correction following 50c4356

The exact-head review of 50c4356 completed **with finding
[4447201891](https://github.com/fvd8383/ultimate-back-office/pull/131#discussion_r4447201891)**.
A fresh PHP process with no autoloader or M6C fixture reproduced
`TypeError: Failed to create closure from callable: class "SiteBuildRuntime" not found`
after directly including CommissionServing.php. The local fixture's earlier runtime
include had masked this dependency. NativeSetup.php alone had the same omission.

NativeSetup now explicitly requires SiteBuildRuntime.php. Its small m6eArtifactStore
function retains the real `Closure::fromCallable([SiteBuildRuntime::class,'guard'])`
and is shared by m6eObserveRelease and commissioning source inspection. This removes
reliance on the native build/inspect fixture's incidental include as well. Loading
the class does not call fromTrustedCli or grant publishing authority. Production
runtime/store/guard semantics and commissioning admission are unchanged.

The new cold-process regression exercises both actual entry graphs and that shared
store constructor, with no fixture preload, autoloader, SQL or operational writes.
All six commissioning MISSING entries remain. Native staged-fixture/authority/
ownership/serving work, installed workflow and full Linux acceptance remain pending.

## Shared Apache/assets observations following ce2d154 — incomplete producers

This slice adds executable source inspection and shared serving predicates. It does
**not complete either native producer**. Both MISSING entries remain. No fixture
aliases, transfer sockets, services or operational receipts are created by the new
native source-inspection path. The exact ce2d154 review completed without findings;
that disposition does not apply to this subsequent source change.

[CommissionServing.php](../tests/support/WebsitePlatformM6ECommissionServing.php)
uses the original worker identity, the bootstrap's existing two-release handoff,
original build/producer rows, LocalSiteArtifactStore and SitePublisherArtifactExport.
It verifies the original 0700/0600 source, reads original manifest bytes through the
exporter's checked descriptor, and repeats exporter validation. Native entry remains
inside the fixed admitted VM/expiry/UID guard; it checks the target is unpublished
before and after read-only observation. It neither starts a deployment session nor
renews a deployment lease. The loaded-file inventory includes its dependencies.

Both existing probes now consume this same source observation. Apache retains its
module and initial-503/unauthorized baseline. Assets retains its empty served-pool
check. Each independently re-observes the worker sources; their bundle digest must
match across gates. The existing profile/FIELDS add source release/file/asset/byte
counts and hashes binding the original manifests, source bundle and shared/changed/
PDF/foreign identity sets. Measurements are retained before binding predicates;
failed observations remain diagnostics, never passed evidence. No receipt schema,
normal host admission, watchdog, ledger or production helper behavior changes.

[CommissionServing.py](../tests/support/WebsitePlatformM6ECommissionServing.py)
provides shared exact-file/marker, GET/HEAD, MIME/length/digest, security/CSP/cache,
method, private/dot/executable/traversal, authentication/Host/SNI/trust and binding
checks. Its default request function is the existing private HTTPS client. Responses
are summarized before predicates, including header digest and fixed policy matches;
no response body or arbitrary header text enters diagnostics. Independent retained
checks invoke the actual helper's verify_served and descriptor-based asset reads.
These serving methods are **not yet called by native probes against a staged fixture**.
They do not establish executable-handler denial merely from absent canary paths.

Local controls share one M6C fixture with two actual same-site builds, distinct
release/job/producer identities, unchanged approved PNG/PDF and changed CSS. The
existing bootstrap fixture test now uses that factory. No replacement manifest is
generated. The actual PHP exporter/UBT1 sender feeds the actual Python receiver and
Publisher.stage, including retained replay with no file transfer or inode changes.
Authority is an explicit local component DTO, not live database authority. The new
sender path creates no deployment rows, target success or approval-consumption event.
Controlled HTTP reads the real immutable files; transport, Unix metadata and pointers
remain modeled. Foreign identity shares legitimate same-site bytes: a foreign-qualified
route is denied, while the legitimate bare same-site content hash remains accessible.

**Remaining code gap:** connect these checks to one ledger-owned native serving
fixture with separately controlled worker/helper/authority processes and private
sockets, actual stage A/replay/B, durable intent before transient helper paths and
alias effects, and strict reconciliation of uncertain completion. It must capture
before restoration, prove original current/candidate absence and unchanged original
journal/fence, and retain unknown objects as LIMITED. The local model restores alias
absence, but its component scratch journal changes during real stage and is not
credited as native pristine-journal evidence. Existing collector primary-error and
cleanup-failure controls remain in force. No blind cleanup or direct privileged
source-copy shortcut was added to fill this gap.

The six unchanged MISSING entries are retained-release-static-responses (apache),
two-release-transfer-retention-conflict (assets), supervised-helper-lock-fence
(runtime), candidate-active-fault-restoration (health),
retained-state-interruption-authority-recovery (recovery), and
owned-uncertain-reload-reconciliation (provisioning). Native receipt issuance stays
blocked. Installed workflow/full Linux acceptance remain unexecuted. Multi-page and
B14/B16 browser behavior remains M6G; header checks do not prove browser behavior.

## Commissioning measurement capture correction following bcf64ec

The exact-head review of bcf64ec completed **with two findings**:
[4446480421](https://github.com/fvd8383/ultimate-back-office/pull/131#discussion_r4446480421)
(measurements lost before validation failure) and
[4446480432](https://github.com/fvd8383/ultimate-back-office/pull/131#discussion_r4446480432)
(host-dependent admission test). This correction does not implement additional
commissioning gates or change production admission.

Before correction, an actual controlled child returned filesystem
replacement_rejected=0 to NativeProbes.probe. The strict filesystem predicate rejected
it, but the original pre-restoration checkpoint contained observations={} and cleanup
ran. No final gate evidence existed. A separate actual child returned runtime
measurements and a real PID before the native runtime producer's PHP-pin check failed;
those measurements and participant context were also absent from its checkpoint.
Original and corrected reproductions are retained outside Git, without rewriting
failed evidence or describing the rejected measurements as PASS.

NativeProbes now retains bounded allowlisted measurements before evaluating the
unchanged predicates. It records attempted/completed/failed stages, obtained child
PID/role/exit context (including execution or parse failure), and incremental data
from existing access, HTTP, inventory, journal and configtest collection points.
The original returned values still feed strict validation. An observed zero remains
zero; absent fields stay absent. Malformed access sections cannot become valid errnos.
Unexpected text/objects receive fixed type/error classifications; arbitrary field
names, paths, response bodies, SQL, credentials and exception messages are not copied.
Diagnostic copies are separate from valid evidence and cannot authorize a receipt.

The existing collector still publishes its immutable checkpoint before cleanup,
preserves earlier gate publications and stops before later unexecuted gates. Tests
exercise cleanup mutation/removal, combined probe/capture/cleanup failure, retained
ownership and rejection of incomplete diagnostics by the actual offline validators.
No report schema, evidence predicate, receipt issuer, ledger/watchdog/supervision or
resource/time ceiling is changed.

The admission controls now use bounded module-local system/OS replacements and
controlled protected readers. Non-Linux Denied must precede every read; Linux wrong
UID must read exactly the synthetic configuration and then deny before the receipt;
missing configuration remains FileNotFoundError before UID evaluation. The existing
positive actual-parser/private-receipt control remains. No live operational path is
read, created or changed by these tests.

Executed checks and platform limits are recorded in the isolated-validation section
and external m6e-measurement-correction/SUMMARY.txt. **M6E remains PARTIALLY
IMPLEMENTED / REVIEW REQUIRED**. Apache/assets/runtime/health/recovery/provisioning
still lack the previously listed producers; native receipt issuance stays blocked.
Filesystem and ownership remain authored/native-unexecuted. Installed workflows,
COMMIT relay, audit/predicates and full Linux acceptance remain pending. M6C/M6D stay
formally closed. No server/database/native infrastructure/deployment action occurred.

## Commissioning collector and protected receipt publication — partial

**COMMISSIONING / RECEIPT ISSUER — PARTIALLY IMPLEMENTED LOCALLY. NATIVE
COMMISSIONING — NOT EXECUTED. INSTALLED WORKFLOW / FULL ACCEPTANCE — PENDING.**
This change does not complete the requested eight-gate commissioning slice. Two
native producers are authored end to end; six still need executable steps listed
below. The native evidence policy rejects those six even if every available
prerequisite measurement succeeds. No usable operational receipt was created.

The fixed entry is [WebsitePlatformM6ECommission.py](../tests/support/WebsitePlatformM6ECommission.py),
also routed by the existing preparation CLI. It consumes the immutable bootstrap
record/publication and protected bootstrap-handoff.json, using their current
fixture, two-release, foreign-site, tool/module, configuration and identity schemas.
It does not create run.json, fabricate deployment rows, or issue an initial receipt
to enable components. The normal protected publishing worker remains inactive.

A fixed ubo-m6e-commission.service owns the operation with TimeoutStartSec=420 and
the existing process-group/resource restrictions. The existing watchdog now stops
that service before taking the lifecycle lock. Its 1,200-second absolute lifetime,
120-second cleanup reserve and bootstrap limits are unchanged. Every stage checks
the original timer-start monotonic timestamp and admission expiry. No timer restart,
deadline extension, general configuration-root option or production override exists.

Collection validates source, bootstrap/handoff, configuration, tools/modules,
identities, ownership, listeners and inactive worker before effects. The historical
READY record is never rewritten. A dedicated post-commissioning inspection accepts
released commissioning resources, checks original bindings and independently
rechecks the unpublished fixture/database state. It does not call pristine
inspect_ready after commissioning. Repeated commissioning rejects; inspection is
read-only and cannot refresh a receipt or rerun measurements.

The eight names remain the existing section-18 host-contract names. The following
is an implementation inventory, **not native PASS evidence**:

| Gate | Executable producer and current limitation |
| --- | --- |
| apache | NativeProbes.probe_apache observes the fixed private active/candidate 503 responses, denied unauthenticated TLS request and actual module output. **Incomplete:** retained-release static response, access/handler and final-binding checks are not implemented. |
| filesystem | CommissionChild.py --filesystem uses actual helper Tree admission at the configured root, narrows its descriptor to owned scratch on that mount, checks local mounts, immutable write/replay/conflict, checked sync calls, no-follow rejection and a real named-inode replacement. A bounded child observes sibling pointer replacement with forced old/new baselines and unknown observations retained. **Authored, unexecuted.** This does not claim kernel-fsync failure or power-loss durability. |
| ownership | CommissionChild.py --access runs as the actual worker/helper/Apache roles. It observes original worker-private manifest read and 0700/0600 modes, helper/Apache denial, worker served/journal denial, and Apache read/digest plus write denial on a commissioning-owned 0440 public canary inside the configured releases root. No existing permissions are repaired. **Authored, unexecuted.** |
| assets | Reads and independently rechecks the current two-release/foreign-site fixture and empty served pool. **Incomplete:** actual two-release transfer, retained serving, conflict and foreign/unlisted/traversal negatives. The existing foreign asset shares a same-site digest; its digest alone is not a foreign-path denial test. |
| runtime | CommissionChild.php --runtime uses the isolated authority configuration, actual PHP/extensions, TLS MySQL identity, native prepare positive/duplicate-parameter negative, UTC observation, SELECT baseline and denied UPDATE/foreign-schema attempts with unchanged state hashes. **Incomplete:** pre-receipt supervised helper properties and lock/fence observations. No old M6D result is credited. |
| health | Records actual unavailable private HTTPS endpoints and absent pointers. **Incomplete:** actual SiteHttpHealthChecker candidate/active release positives, wrong marker/content, redirect/auth/error/stale cases and owned restoration. Unavailable HTTP is not release-health PASS; no retry budget is increased. |
| recovery | Records the actual pristine journal/fence and absent pointers. **Incomplete:** retained-state component interruption/health-failure recovery, unavailable/unknown authority blocking and independently verified known-good byte preservation. No backup, successful deployment or compensation is fabricated. |
| provisioning | Runs the existing fixed Provisioner with actual positive configtest and deliberately invalid pending configuration; preserves live config/journal and records zero reload commands. Pending bytes are captured before removal. **Incomplete:** owned uncertain-reload/reconciliation producer with restored and independently observed final configuration. |

These missing steps need a component transfer/retained-state controller with a
durable ownership transition protocol for the configured served/journal roots,
pre-receipt helper supervision, checker fault/restoration orchestration and reload
reconciliation. The existing NativeBackend expects a different later run.json and
its commissioning restore method raises an unimplemented error; directly invoking
it would be circular or unowned. The existing NativeSql child also expects the
older nested fixture shape. This implementation supplies a narrow current-schema
runtime producer but does not silently reinterpret those remaining drivers as
commissioned. Native issuer authorization stays blocked until their producers and
independent predicates are completed. Full COMMIT-loss relay/audit/installed
workflows remain separate additional work.

Evidence records retain bounded original observations, actual process/role IDs,
source/run/machine/boot, protected bootstrap/handoff, final configuration/tool/module,
root and loaded dependency bindings. The collector uses existing immutable evidence
publication, captures each result or partial failure before cleanup, preserves the
first failure and separates later capture/cleanup limitations. Measurements are
copied once and never completed from expected counts. Controlled-local records
remain visibly labeled and are rejected by native issuance. Records contain no
raw SQL values, credentials, keys, bodies or arbitrary exception messages.

The issuer code independently validates finalized gate bytes and their original
pre-restoration publications before constructing the unchanged seven-field v2
m6e-host-v2 receipt. Its installed inventory uses actual PHP include observations
for worker and authority, required entrypoints/configuration and safe file checks,
with the existing 256-file cap. Nested PHP include paths are preserved by bootstrap
installation, rather than flattened. Service-template names containing @ cannot be
entries in the unchanged PHP receipt path grammar; their exact bytes stay pinned
through the bootstrap record and every final unit recheck. Runtime-loaded PHP files
are still individually pinned and checked by each consumer.

Expiry is no later than fixture authorization, the original watchdog deadline or
24 hours. Complete private writes, checked file synchronization, atomic no-replace
publication and directory synchronization target only the fixed protected receipt
path. Intents and the complete candidate's bytes/inode are recorded before rename.
Existing receipts reject. Post-rename/sync/ack failure retains the final object and
reports uncertainty; read-only reconciliation requires the exact original binding,
inode, bytes and unexpired issuance. It does not claim to reestablish durability.
Actual PHP HostContract.load and helper load_config acknowledgements are authored
without starting a publishing operation; the native path is not reached while
producers are missing. Authority service execution remains unverified.

Local verification and platform limits are recorded in the corresponding isolated
validation section and external m6e-commissioning/SUMMARY.txt. Production publisher,
transfer, helper, authority, health, host-contract schema/admission and canonical
socket/service examples are unchanged. The reviewed fixture metadata implementation,
ledger save/recovery, M6C/M6D behavior, migrations and requirement definitions remain
unchanged. M6E/PR #131 remain partial/draft; M6C/M6D remain formally closed and
M6F-M6G remain not started. Earlier sections below are historical evidence, not a
claim that the newly added producers have executed.

## Directory lifetime correction following a5ff864

Review of `a5ff864d56486447e50357833cd3112365f24044` completed with
[finding 4438243969](https://github.com/fvd8383/ultimate-back-office/pull/131#discussion_r4438243969).
The reviewer reported Linux full-suite and isolated index-1 partial-clone failures:
`ModelHost.put()` created parents directly, bypassing metadata initialization, so
a recycled directory could inherit the pending file's cached regular kind. Inventory
then applied the regular-file link check. The earlier Windows PASS remains recorded.
Deterministic local injection reproduced the wrong type and LIMITED cleanup; Windows
reported one directory link, so its failure occurred later than Linux's reported
`base_tree_hardlink`. No natural inode reuse or local Linux execution is inferred.

Ordinary file/directory type now comes from real stat; only modeled sockets and
intentional unsafe-type controls retain explicit overrides. Exclusive file creation
still initializes fresh modeled ownership/mode from the actual descriptor before
identification. A small `PrivateFiles.makedirs()` helper initializes each directory
only after its mkdir succeeds, checks existing parents' actual and modeled directory
type without changing metadata, and rejects unexpected entries. The constructor and
`ModelHost.put()` use it, covering clone, TLS, database/artifact and PID fixture output.
Existing parent modes, rename identity, remaining hardlink names and collisions
remain intact. No cache-wide clearing, lookup repair or unlink eviction was added.

The finite creation audit preserves unmanaged foreign/replacement/fault fixtures;
standalone template-copy scratch files have no modeled inode cache. Deterministic
controls cover regular/socket metadata reused by directories, directory/socket
metadata reused by files, recursive parents, constructor setup, unsafe existing
types, collisions and partial clone teardown. Both corrected partial-clone cases
retain their intended command failure and complete owned cleanup. The actual
ledger, bootstrap/host/watchdog, admission/cleanup policy, socket templates and
production code stay byte/mode unchanged. Commissioning, receipts, installed
workflows, remaining fault scenarios and native acceptance remain pending.

## Controlled filesystem identity correction following 1834f93

Review of `1834f93a101caa4d7e056960472e77f970c4f67e` completed with two fixture
findings: [stale modeled metadata](https://github.com/fvd8383/ultimate-back-office/pull/131#discussion_r4438173135)
and [foreign-object identity](https://github.com/fvd8383/ultimate-back-office/pull/131#discussion_r4438173144).
The reviewer reported both transient teardown subcases failing when a new pending
file inherited cached socket metadata, and the foreign-inode subcase failing in
the full suite and three isolated repetitions. The recorded Windows PASS below
remains historical evidence; it did not establish allocator-independent fixtures.
These findings concern the test model, not a demonstrated recurrence of the
previous pending-file implementation defect. The reviewed ledger stays unchanged.

`PrivateFiles.create()` now initializes only the successfully opened exclusive
creation's actual device/inode entry to modeled root/root, regular, 0600 before
calling identification, including its injected failure path. It does not clear
metadata on lookup, collision, rename or unlink. Existing unsafe metadata and
remaining hardlink names retain their observations. Directory creation already
initializes after successful mkdir; socket creation assigns its role afterward.

The foreign-file control preallocates its file while the original ledger exists,
retains the newly installed object under another name, and verifies original,
created and foreign identities differ before injecting substitution. Foreign bytes
equal the expected complete ledger; real rename operations preserve a single link.
The original sync error remains primary and subsequent save must reject the
unchanged foreign object. This tests distinct-object substitution, not general
inode-reuse security, crash recovery or power-loss durability.

Deterministic stale-cache injection uses the real exclusive-open descriptor without
altering stat results or ledger checks. Before correction it reproduced a regular
file observed as a socket and false `ledger_object_replaced`; after correction it
reaches the intended empty/prefix/sync boundaries. Normal creation, callback failure,
collisions, existing unsafe metadata, rename/hardlink behavior and teardown controls
remain covered. Only the two existing control files and two M6E records change;
ledger, orchestration/watchdog, socket policy and production code are byte/mode
preserved. Commissioning and native integration remain pending.

## Pending-ledger recovery following 82aef44

The outstanding [P2 finding 4438049274](https://github.com/fvd8383/ultimate-back-office/pull/131#discussion_r4438049274)
was reproduced against `82aef4403cf17faa5ff71ec317f568684ef0554a`: both an empty
new pending file and a real 13-byte prefix survived a failed save, blocked later
saves and prevented modeled teardown from reaching any service-stop executor.
A separate directory-sync fault installed complete new bytes but left the cached
ledger identity stale. Before-fix bytes, hashes and modeled commands are retained
outside Git with the corrected results in `m6e-ledger-recovery`.

The creation callback now captures descriptor/name identity before writing, without
recursively journaling the pending ledger. Replacement checks the protected parent,
exact object, regular type, single link, owner and mode. Pre-replace failure keeps
the old ledger and removes only this invocation's verified pending object. Original
write/sync errors survive close/cleanup errors; bounded secondary diagnostics use
existing sanitized classifications. A post-replace error still fails and records
its phase and unconfirmed durability. Cached identity changes only after proving
this invocation's exact created inode and complete expected bytes are installed.
The version-1 schema remains; `observations.ledger_save_failure` adds only fixed
stage/installed/durability fields. Failure recording stays in memory until a later
deliberate save; there is no automatic retry or crash-durability claim.

Thirteen new recovery controls and twelve affected bootstrap lifecycle/capture/
teardown controls pass. Cleared transient faults allow save, bound reload and the
real modeled journaled teardown; persistent faults leave cleanup LIMITED and the
watchdog ARMED. Foreign resources/replacements survive and repeated teardown does
not repeat destructive effects. Native UID enforcement, directory durability,
service cleanup, SIGKILL and power-loss recovery remain unexecuted. Production
executables, corrected socket templates/policy, bootstrap orchestration and
watchdog limits are unchanged. Commissioning/receipts, installed workflows, fault
scenarios/COMMIT relay, full audit/predicates and native acceptance remain pending.

## Canonical socket policy following 166765f

The source compatibility issue recorded at `166765f308d5d0d09d4b406939f4711c88a50515`
is now corrected in both operator-facing socket examples. `Accept=yes` remains;
explicit `Service=` is removed. The existing matching `ubo-site-authority@.service`
and `ubo-site-publisher@.service` names select per-connection services, whose socket
stdin/stdout routing, identities and hardening are unchanged. This follows the
[systemd 257 contract](https://www.freedesktop.org/software/systemd/man/257/systemd.socket.html);
compatibility with the actual pinned installed version remains a later native check.

The authority example now explicitly declares `SocketGroup=@PUBLISHER_GROUP@` once
inside `[Socket]`. Omitting a group would derive the `SocketUser` account's primary
group, not necessarily root's group. Explicit canonical binding keeps the existing
fixture helper group without a fixture-only appended directive. Authority 0600,
publisher root/worker-group 0660, fixed paths, directory policy and connection
limits are preserved; neither example adds an installation/enablement section.

`fixture_units()` now returns the canonical socket bytes with placeholder
substitution only. It no longer expects/deletes an incompatible `Service=` line or
appends `SocketGroup`. A small fixed-template policy checks the exact unit inventory,
matching service names, expected sections/listener/activation/access/limits and
rejects duplicate, extra or conflicting directives on canonical and rendered input.
It rejects continuation syntax and is not a general systemd parser or unit-manager
validation. Private-copy mutation controls and the existing limited bootstrap
workflow exercise the real renderer; no socket activation or native configtest ran.

This changes **two installation configuration examples**, two test/support Python
files and these two M6E records. Production PHP/Python executables, both service
examples, bootstrap host/inspection, watchdog, ownership ledger, teardown and
capture-before-cleanup behavior are unchanged. Current scope needs no additions.
The review of `166765f` published an unrelated P2 pending-ledger-write finding
[4438049274](https://github.com/fvd8383/ultimate-back-office/pull/131#discussion_r4438049274).
It was unresolved at that socket correction; the ledger section above supersedes
that finding's implementation status. The prior head is not claimed clean.
Bootstrap remains **IMPLEMENTED LOCALLY / NATIVE EXECUTION PENDING** and
overall M6E remains **PARTIALLY IMPLEMENTED / REVIEW REQUIRED**. Commissioning,
receipt issuance, protected successful installed workflow, remaining scenarios/
COMMIT relay, full restore/retention/audit and Linux filesystem/race/identity
acceptance remain outstanding. M6C/M6D stay formally closed.

## Fixture bootstrap and supervision following 935618a

**FIXTURE BOOTSTRAP/SUPERVISION — IMPLEMENTED LOCALLY, NATIVE EXECUTION PENDING.**
This bounded slice starts from clean `935618abc7fa6e5b3552f2efb1a6b5a14292f37d`.
Its exact-head review was read once: completed without findings; all three earlier
threads remained resolved. Overall M6E is still **PARTIALLY IMPLEMENTED / REVIEW
REQUIRED**. The older sections below describe the state at their named heads;
this section supersedes their bootstrap/supervision gaps only.

`WebsitePlatformM6EPreparation.py --prepare-base` now executes the complete fixed
bootstrap through `WebsitePlatformM6EBootstrap.py`. The existing VM contract,
component templates and canonical setup are reused. `BootstrapPlan.py` contains
definitions/rendering, `BootstrapHost.py` the native boundaries and safe command
journal, and `BootstrapLedger.py` the durable ownership/evidence handling. No
provider abstraction, software installation or production admission bypass was
added. Legacy `--prepare` and full native `--run` retain the existing incomplete
native-readiness barrier; no callable placeholder satisfies it.

Preflight validates the version-2 admission, dedicated VM/run/machine/boot, exact
read-only source and tracked modes, loopback-only network, fixed accounts/UIDs/GIDs,
path/unit/drop-in collisions, pinned installed tools/modules, PHP extensions,
local storage and capacity/cgroup policy. It checks bootstrap source completeness
and syntax and resolves every fixed template before creating resources. Source
copying preserves tracked executable bits. Generated application secrets stay in
a separate root-owned configuration; the ignored installed `env.php` only loads it.

The authored native path creates seven separate non-login accounts, private TLS,
one fresh MySQL 8.4 instance and `ubo_m6e_<run-id>`, applies canonical migrations
001–025 once and checks the `247sp` seed. Writer, worker and SELECT-only authority
credentials have separate role access; the authority receives no foreign-schema
grant. Safe output binds the server UUID, schema, accounts and setup records.
Actual M6C service/builder/validator/store calls build two releases of the same site
with separate jobs, execution producers and manifests, shared PNG/PDF and changed
CSS, plus a foreign-site asset identity. Artifact roots/files remain worker-owned
0700/0600. Synthetic approvals are fixture data preparation, not installed protected
worker acceptance. The target is unpublished, deployment count is zero and the
helper journal is pristine; no current/candidate link or activation is fabricated.

The fixed systemd bootstrap service owns preparation after client disconnect. Its
600-second preparation budget is separate from the unchanged acceptance deadline.
An independent 1,200-second timer invokes the fixed watchdog cleanup service with
a 120-second cleanup budget. Public teardown also dispatches that service. Only
MySQL, Apache and the two sockets start; the protected worker is installed inactive.
The standalone Apache main config loads admitted modules and supplies runtime,
user/group, MIME and exact private listeners around the reviewed static/mTLS policy.
Inspection checks source/tool/config/unit bytes, service PID/invocation/cgroup,
socket type/ownership/mode/unit, role access, listener ownership, actual DB/artifact
identities, empty served output, pristine journal and absent host receipt. Startup
and socket presence are never credited as application authorization or TLS gates.

Intent is persisted before partial creation; observed identities and safe command
IDs/caller/PID/exit/time follow actual invocation. Commands, credentials, raw rows
and content are not retained in report bodies. Immutable failure and teardown
snapshots precede restoration; capture errors remain separate and do not suppress
cleanup or replace the primary failure. Cleanup revalidates namespace and exact
unit/invocation/object/account ownership, verifies processes are gone and preserves
unknown/replaced resources as LIMITED. Sealed trees reject new/replaced children;
partial account removal remains retryable. Successfully released resources are
never adopted again. The watchdog remains armed through incomplete cleanup; its
control files are removed last. `/opt/ubo` and the external evidence export remain.

The checked immutable `m6e-fixture-bootstrap-v1` record says only **FIXTURE BOOTSTRAP
READY / COMMISSIONING NOT EXECUTED / HOST RECEIPT ABSENT / NATIVE ACCEPTANCE NOT
EXECUTED**. A separate publication receipt binds its bytes. The protected handoff
contains measured configuration/source/tool/resource/DB/fixture identities for
future commissioning. Read-only inspection rechecks that handoff and current
state against the record. Archived READY bytes alone do not authorize later use
after teardown, expiry or any state change. The full native validator rejects this
distinct profile.

Local evidence consists of the actual Python orchestration/adapters with real
private files and modeled Linux/service/UID/SQL boundaries, and PHP controls that
exercise schema command formation with fake PDO plus actual M6C artifact builds,
validation and byte/digest cross-checks. The PHP controls use two isolated builder
fixtures; they do not establish a native same-site SQL run. Final results and exact
commands are recorded in the validation runbook. Original failures and rechecks
are retained outside Git in `m6e-bootstrap`; no failure file was overwritten.

Production executable changes in this slice: **zero**. The reviewed observation
capture/cleanup implementation is unchanged. Current scope adds only six exact
bootstrap support/test paths; all 25 migrations/pins, 77 requirement definitions,
M6C/M6D code, historical scope policies and closed reports remain preserved.
Commissioning/host-receipt issuance, installed protected successful workflow,
remaining scenarios/COMMIT relay, full Apache restore/retention/command audit/native
predicates and Linux syscall/race/identity acceptance remain unfinished. No server,
SQL, migration, native service/configtest, VM, deployment or merge was executed.

## Failed-observation evidence correction following 3475bf4

The review of `3475bf4f7829bc2b1b9342697e6e13f2e22a91e4` completed **with**
[finding 4437786320](https://github.com/fvd8383/ultimate-back-office/pull/131#discussion_r4437786320).
All three actual driver functions were reproduced through `dispatch`, `Recorder`,
`coordinate` and the controlled backend before editing: baseline and fault returned,
observation threw, cleanup saw the private marker still faulted and no capture file,
then restoration destroyed that state. The later saved report contained only
UNREACHED for the reached observation. That earlier behavior was incorrect.

The recorder now starts each observation explicitly, retains immutable copies of
safe measurements immediately, tracks attempted/last-completed stages and retains
the first failure. The adapter progress seam records an outcome before a later
independent read can throw. Started failures produce FAILED partial rows; genuinely
unstarted observations stay UNREACHED. Earlier complete observations survive a
later failure. Only evaluated assertions are retained, including a failed check;
unexecuted planned assertions are not counted.

All three drivers share a required checked disk checkpoint before their per-case
cleanup. It binds source/run/config/case/observation and contains available events,
safe failure classification, bounded notes and owned resource/child metadata.
Snapshots are at most 64 KiB, use distinct no-replace names and keep original bytes.
Provisioner additionally checkpoints safe pending-file size/hash and actual result
state before pending-file cleanup. This does not change provisioning decisions or
add rollback. The coordinator's later capture covers remaining global cleanup; it
is no longer described as the first capture for an observation already restored.

Capture failure is separately recorded and blocks acceptance. Cleanup still runs.
A prior case exception remains primary through capture and cleanup failures;
secondary outcomes remain distinct. Ownership is registered before begin/setup can
partially succeed. Released resources are not destructively cleaned again; failed
or explicitly incomplete cleanup stays tracked for bounded final handling, while
retaining the first cleanup measurement if a final retry releases it. Child diagnostics distinguish
observed exit before cleanup from requested termination and actual final exit/reap.
Exception messages/arguments, private handles/paths, bodies and credentials are not
serialized. A post-publication diagnostic cannot rewrite finalized report bytes.

The v2 report adds explicitly versioned `lifecycle.version=1` diagnostic fields.
Current integration PASS rows require successful per-observation capture and cleanup
alongside every existing measurement/assertion requirement. Historical v2 integration
rows without the lifecycle extension reject explicitly as
`legacy_observation_lifecycle_missing`; they are not retroactively credited with
capture. FAILED partial records can be validated diagnostically but never accepted
as a passed observation. The native predicates, 13 parents, mandatory inventory,
lane separation and final publication-receipt requirement remain intact.

Focused local validation: **37 integration-control tests PASS**, including the
parameterized baseline/fault/outcome/independent/assertion/cleanup failures, combined
capture/cleanup failures, partial construction, multiple observations, real private
file ordering, pending cleanup, immutable copies, privacy/tampering and owned child
context and incomplete-cleanup retry. The final run took 28.529 seconds. Original before/after files and hashes
are retained outside Git in this task's `m6e-evidence-ordering` directory. Unchanged
helper/transfer/publication suites, historical campaigns and native gates were not
rerun. Production executable changes: **zero**.

This correction does not complete native integration. Commissioning/receipt issuance,
fixed supervised entrypoints/watchdog, complete native orchestration, COMMIT relay,
Apache restore/retention, command audit and native schema/syscall/race obligations
remain unfinished. `native_readiness()` still blocks them before effects. M6E stays
**PARTIALLY IMPLEMENTED / REVIEW REQUIRED**; M6C/M6D remain formally closed.

## Integration controls following 065983d5 — partial implementation

Starting source was the clean, exact branch head
`065983d5a69269bcf8865d902a9a66f050860958`. Its exact-head review was observed once:
completed, no findings, with both earlier threads resolved. This change does **not**
achieve the requested complete installed integration handoff. Status remains
**PARTIALLY IMPLEMENTED / LOCAL CONTROLS PASS / LINUX-APACHE-MYSQL ACCEPTANCE NOT EXECUTED**.

| Existing driver | Implemented code | Mandatory executable work still missing |
| --- | --- | --- |
| `apache_private_health_headers` | Named driver loop, positive baselines, actual PHP HTTPS-checker child, independent bounded Python TLS requests, fixed fault mutations, response/hash/header observations and cleanup | VM Apache installation/commissioning, phase-bound two-release inputs, verified restore/retention cleanup and full native inventory predicates |
| `config_failure_preservation` | Actual provisioning policy with fixed command vectors and protected-file storage; configtest-before-install, failed/unknown reload stays UNKNOWN, separate reconciliation; deterministic executor tests | Completed supervised native configuration/reload fault setup and independent command/syscall audit for routine operations |
| `worker_helper_authority_integration` | Named driver loop and controlled adapter tests; authored separate-UID permission/protocol child, actual PDO SELECT-only/foreign-schema probes and instrumented worker/barriers | Native scenario backend, fixed installed worker/recovery service orchestration, real COMMIT wire faults, independent barriers/transaction observations, renewal/expiry and 300-second session scheduling |

The three names now dispatch through `WebsitePlatformM6EDrivers.py`; dispatch alone
is not completion. `WebsitePlatformM6ECoordinator.py` retains all 13 parents and
initializes every named observation UNREACHED. `native_readiness()` lists absent
executable methods. Both the integration `--run` path and preparation `--prepare`
fail before fixture mutation while these dependencies are missing. There is no
force flag, caller-provided completion boolean, fabricated command-counter file,
all-PASS gate dictionary, generated host receipt or operational bypass.

`WebsitePlatformM6EPreparation.py` contains guarded disposable-VM preflight,
ownership intentions, account/private TLS/schema preparation and namespace teardown
code. It requires a separately supplied VM with preinstalled pinned tools and a
read-only exact checkout. That code is **not a complete runnable preparation path**:
commissioning, receipt issuance, installed supervision and watchdog are still
missing. The readiness barrier deliberately prevents partial construction followed
by an unwritten operator step. Neither preparation nor any native child was run.

The definitions-only v2 acceptance contract fixes source/run/config/tool/role/
endpoint/database identities, exact names and evidence lanes. Five measured stages
are required for every observation: baseline, reached boundary, outcome, independent
effect and cleanup. Report-supplied assertions cannot override source-defined native
predicates. Native policies for component/identity observations, manifest/retained
inventory and command auditing are still missing and explicitly reject acceptance.
Controlled adapter results cannot be promoted to native acceptance.

The coordinator captures evidence before owned-resource cleanup, preserves the first
error, retains child/reaping context and publishes original final bytes without
replacement. Original bytes say publication PENDING; a separate checked publication
receipt binds their exact hash/length. Missing receipt, bad cleanup, unreached cases
or any first failure prevents exit zero. Namespace teardown remains separate.
The 600-second coordinator, 30-second stage and 300-second helper session limits
were not increased. Native duration has not been measured.

### Narrow reproduced helper correction

A local reproduction showed that successful database COMMIT followed by session
EOF before `settle` left a completed activation journal permanently blocking the
next ordinary deployment. The unchanged M6D recovery service intentionally does not
allocate ordinary recovery for an already completed success. The helper now admits
a greater execution fence only when fresh independent authority identifies the
exact successful predecessor, journal phase is completed activation with no intent,
original deployment/producer match, and the locked current-pointer/retained-byte
read verifies that exact identity. It does not compensate or infer success from EOF.
Unknown outcome, wrong producer/owner, pending intent, wrong phase, changed bytes,
pointer drift and dead authority still reject without journal mutation.

The first reproduction failed at the old fence guard; its output is retained outside
Git. The final helper suite has 19 passing tests, including the reproduction and
eight negative subcases. Only `fence` and `Publisher.dispatch` changed executable
production logic; transfer framing, budgets and publication primitives are preserved.

### Local verification and limits

The focused local campaign uses installed Python/PHP, private files, bounded child
pipes and the existing synthetic protocol/HTTP/PDO adapters. It does not use MySQL,
Apache, installed helpers or supervisor services. The new control suite exercises
the actual three driver functions, actual Provisioner, actual coordinator and
definitions-only validator, including partial construction, primary/cleanup failure,
fabricated inventory, self-serving assertions, wrong identity binding and absent
publication receipt. Linux filesystem details remain explicitly modeled.

Final directly affected results: 26 integration-control tests, 19 helper tests,
8 publication tests, 20 transfer tests and 5 PHP-to-Python wire tests pass (78 Python
tests). PHP host/authority/health/protocol suites pass 33/18/43/19 assertions (113).
All 13 changed Python files parse and all 5 changed PHP files lint. The working
scope guard passes 172 assertions; staged/committed counts are retained in the
external handoff. Four relative document links resolve. AST comparison against
065983d5 confirms that only the two declared helper functions changed executable
production logic; all three reviewed PHP bridge files are byte-preserved.

Raw per-program results, initial failures, rechecks, interpreter versions, syntax/
scope/preservation results and complete handoff are retained outside Git in
`m6e-integration/SUMMARY.txt` under this task's Codex visualization directory. The
original Windows sandbox hard-link denial and successful authorized local rerun are
both retained. The 68-program historical campaign and closed native gates were not
rerun. No server, SSH, infrastructure, SQL, migration, installation, deployment,
merge or formal closure operation occurred.

## Bounded artifact transfer — current local implementation

Starting source: `659d59d7478df5bc0e42886a796a4e78c910ba23`, clean on the existing
branch. Its exact-head review completed with no findings; the two earlier review
threads remain resolved. This task implements the missing bridge in one subsequent
commit. **ARTIFACT TRANSFER — IMPLEMENTED LOCALLY / NOT YET INSTALLED-VERIFIED**.
**INSTALLED IDENTITY/HTTP/AUTHORITY ACCEPTANCE — NOT EXECUTED**.

`SitePublisherArtifactExport` is worker-only. Scoped committed reads resolve the
exact release, site, successful build job and original execution producer (including
an adopted recovery). The unchanged M6C `verifyRelease` runs before and after export,
not before every file. The existing service also performs its own verification.
Only raw `metadata/manifest.json`, raw `metadata/validation.json` and the verified
public inventory leave the worker. There is no rerender, latest-release lookup,
archive, full-package base64/JSON, journal/lock/snapshot export or caller path.
Source paths derive from the verified storage key and fixed protected worker roots.
Pinned per-file/ancestor dev/inode/owner/mode/link/size/time checks and incremental
length/digest checks reject replacement, links, foreign ownership, changed bytes,
missing or extra inventory. Worker directories/files stay 0700/0600; shared M6C
classes, hashes and transaction behavior are unchanged. Filesystem work and the
transfer occur outside all SQL transactions.

`SitePublisherSession` retains its seven ordinary version-1 wire operations and
all six public SitePublisher operations/DTOs. Stage adds a mandatory internal
`transfer_ready` handshake with `transfer_version=1`, request sequence and a fresh
128-bit helper-generated transfer ID. The helper authenticates the Unix peer,
validates the request/binding/lease, independently reads committed authority,
acquires the existing target lock, persists the fence and requires permitted live
execution before ready. Recovery never admits transfer. An exact retained release
returns disposition `retained`; the sender sends no file frames and awaits the
normal stage receipt. A conflict rejects before ready, without repair. This explicit
branch also prevents a lost-receipt retry from deadlocking over expected bytes.

Disposition `receive` uses UBT1 frames: four-byte magic, one byte C/D type, four-byte
unsigned network-order length, then exactly that many bytes. Control JSON carries
version/transfer ID/request sequence and an exact file index, fixed verified path,
length and SHA-256. Binary D data never enters JSON command parsing. Manifest is
first, validation second, then sorted manifest public entries. Each file is checked
and acknowledged before the next; no pipelining/unbounded queue or resumption.
The raw manifest hash must equal independent authority before its inventory is
trusted. An explicit end control binds file count, public bytes, metadata bytes,
data-frame count and manifest hash. EOF cannot complete a transfer; already queued
extra frames are rejected. Fragmented/coalesced and partial transport is handled by
exact reads/writes under an absolute deadline.

| Resource | Fixed bound and accounting |
| --- | --- |
| Ordinary request/response | 16,384 / 1,048,576 bytes; not enlarged for packages |
| Control / binary frame | 4,096 / 65,536 bytes; header length checked before allocation/write |
| Files | 2,000 total including both metadata files; public count at most 1,998 |
| Public bytes | 104,857,600 total; HTML 1,048,576/file, marker 1,024, CSS/media/PDF 20,971,520 |
| Metadata | Manifest 1,048,576 plus validation 16,384; separate from public allowance |
| Transfer control / framing | At most 16,392,192 control bytes (both directions), 9,664 frames, nine header bytes/frame |
| Stream allocation | 65,536-byte chunks; manifest up to 1 MiB. Existing M6C/Python verifiers and publication still allocate a bounded individual file up to 20 MiB (read assembly can temporarily double that); never a full package |
| Helper disk | Receive plus public stage plus asset insertion: at most three public payloads, two manifests and one validation file, with bounded file/directory counts and a 357,711,872-byte free-space admission reserve (allocation units at most 4 KiB). Filesystem metadata/quota/exhaustion remain installed gates; failed writes still deny. Existing retained history is not deleted or credited as free space |
| Deadline | Stage including export verification, transfer, verification, publication and receipt: at most 30 seconds, further capped by helper lease expiry and the original 300-second session. Ordinary operations remain bounded. No progress reset or timeout extension |

The PHP session renews execution through the existing guarded service mechanism
before transfer; the renewal commits before external work. The 30-second phase
needs no periodic extension of the 120-second lease. The helper rechecks authority
at least at one-second progress intervals, after reception and before publication,
candidate update and acknowledgement; observer calls have an absolute five-second
cap and respect the remaining stage budget. Possession never authorizes activation.

Ingress uses a unique exclusive helper-owned 0700 subtree under private served
`staging`, with 0600 files and pinned no-follow directory descriptors. Bytes remain
outside asset, candidate and active aliases. Complete received packages pass the
actual existing `verify_package`, including exact validation bytes, complete
inventory, public/full hashes and the non-circular marker. Only then can the existing
corrected stage path publish retained assets/pinned manifests through complete
private temporaries and atomic no-replace operations. Candidate/current behavior,
lock lifetime, fence, settlement and unknown-DB-outcome semantics are preserved.

Failure never fabricates a receipt, reconnects, activates or compensates. Cleanup
verifies the invocation's recorded inode identities and removes only its private
receive files/directories; secondary failure gets a separate fixed diagnostic and
cannot replace the first phase/index error. A post-publication error or lost final
acknowledgement leaves complete installed objects and an unresolved journal; no
nothing-changed claim or destructive repair. Other private incomplete staging and
crash leftovers keep their existing semantics. No broad cleanup/prune was added.

The production helper has no source-root configuration, no source Tree/member and
no direct-source fallback, including startup, inspection, probe, recovery and
settlement. Existing component source adapters now live only in test subclasses.
Worker/helper configs and the receipt use version 2 (`m6e-host-v2`); authority config
also uses version 2. Old incompatible configs fail, while ordinary request version 1
remains unambiguous through the mandatory stage handshake. Each identity verifies
its own loaded code/config pins. The helper does not read worker config/application
credentials or the authority credential file; the worker/authority likewise verify
their own included files rather than opening another identity's private files.
Supervision templates deny helper access to original work/sealed roots, worker config
and credentials, and deny worker access to served/journal roots. No template, receipt,
account, permissions or service was installed, and no passed receipt was manufactured.

New implementation: `private/classes/SitePublisherArtifactExport.php` and
`private/classes/SitePublisherTransfer.php`; integration changes are session/runtime,
host config, helper and identity-specific pin verification. New executable desktop
regression: `tests/WebsitePlatformM6ETransferTest.py` with the actual PHP transfer
child. Existing wire/host/helper/filesystem fixtures are updated consistently.
Exact changed-file inventory and committed hashes are retained in the external SUMMARY.

Local real operations include original M6C builder/validator/store, production
exporter/session/receiver/publication code, private files, bounded child pipes and
private loopback protocol tests. Distinct UIDs, Unix permissions/dirfd/renameat2,
directory/read-only fsync, free-space admission, peer credentials and pointers remain
explicit Windows models. The original production helper main is exercised with
modeled host/peer/OS admission to prove inspect/probe/recovery/settlement open only
served/journal trees. This is not installed admission. Authored separate-identity
Linux observations are described in the isolated runbook and were syntax-checked
only. All three broader integration drivers remain INCOMPLETE.

### Focused transfer validation and exact inventory

The final focused run passes 280 PHP behavioral assertions plus the current M6E
scope guard, and 50 Python tests. Commands, wall times, stdout/stderr, failed runs
and normalized source hashes are retained outside Git in the task's `SUMMARY.txt`
and JSON evidence. PHP 8.4.24, Python 3.14.7 and Git 2.56.0.windows.2 were used.

| Program | Observed result | Command wall seconds |
| --- | --- | ---: |
| M6E protocol | 19 assertions PASS | 6.004 |
| M6E host | 33 assertions PASS; default-deny/admission fixtures | 0.409 |
| M6E authority | 18 assertions PASS; sequential PDO fixtures | 0.304 |
| M6E health | 43 assertions PASS; synthetic HTTP/TLS | 0.547 |
| M6D CLI / execution / recovery | 44 / 63 / 60 assertions PASS | 3.489 / 1.026 / 0.626 |
| Current M6E scope, working files | 146 assertions PASS | 2.830 |
| Actual PHP/Python transfer | 20 tests PASS | 23.062 |
| Actual PHP/Python ordinary wire | 5 tests PASS | 7.385 |
| Immutable publication | 8 tests PASS | 3.584 |
| Helper | 17 tests PASS | 5.090 |

The initial composite transfer run exceeded its outer runner timeout and reported
a retained-case fixture-startup deadline error before staging. That failure is kept;
its cause is not attributed to the host without evidence. The isolated retained case
then passed, followed by the full transfer suite and a final full run after adding
receive-directory-open cleanup coverage. Production's 30-second stage bound was
never extended. Development also exposed obsolete direct-source fixture assumptions,
Windows stream/dirfd/diagnostic model defects and an owner-test path normalization
error; those failures and corrections are recorded separately, not credited as PASS.
No full 68-program campaign, long M6C synthetic launcher, historical closed scope
check or native/infrastructure gate was run.

This transfer change contains exactly these 22 paths (six new):

```text
docs/sprint-8.8-m6e-isolated-validation.md
docs/sprint-8.8-m6e-local-implementation.md
infrastructure/deployment/site-publisher-host-contract.json.example
infrastructure/deployment/ubo-site-authority.php
infrastructure/deployment/ubo-site-publisher@.service.example
infrastructure/deployment/ubo-site-worker.service.example
infrastructure/deployment/ubo_site_publish.py
private/classes/SiteDeploymentRuntime.php
private/classes/SitePublisherArtifactExport.php [new]
private/classes/SitePublisherHostContract.php
private/classes/SitePublisherSession.php
private/classes/SitePublisherTransfer.php [new]
tests/WebsitePlatformM6EHelperTest.py
tests/WebsitePlatformM6EHostTest.php
tests/WebsitePlatformM6EIsolated.py
tests/WebsitePlatformM6EScopeTest.php
tests/WebsitePlatformM6ETransferTest.py [new]
tests/support/WebsitePlatformM6EFilesystem.py
tests/support/WebsitePlatformM6EIdentityChild.php [new]
tests/support/WebsitePlatformM6EIdentityDriver.py [new]
tests/support/WebsitePlatformM6ETransferChild.php [new]
tests/support/WebsitePlatformM6EWireChild.php
```

Changed PHP files are linted and changed Python files AST-parsed without bytecode;
the authored native entry points are not imported or executed. Documentation links,
Git inventory and whitespace are checked. Every `Tree` method, including corrected
atomic publication, is AST-identical to 659d59d; the 13-case isolated inventory is
unchanged. Shared protected classes, 25 migrations/pins, 77 definitions and closed
native contracts/reports/policies remain unchanged. Final staged/committed scope and
exact-head review observations belong to the external evidence receipt.

## Focused PR #131 review correction — historical 659d59d evidence

Starting source: `528b47fefbe2e7bacd4a3b7e8a365de7b4db8aa2`, verified clean on
the existing branch with the PR still draft. Its completed review had two unresolved
findings; completion was not a clean review:
[empty wire payload](https://github.com/fvd8383/ultimate-back-office/pull/131#discussion_r4437330462)
and [partial permanent objects](https://github.com/fvd8383/ultimate-back-office/pull/131#discussion_r4437330464).

Before editing production code, the actual PHP session wrote `payload: []` for
inspect/settle into a test-only memory stream (seven-byte writes, five-byte reads).
The actual Python parser rejected both. Saved original producer bytes also reproduce
the rejection in a bounded Python child loading the exact prior Git blob. No
operational inspection/settlement occurred. The separately source-confirmed
correlation mismatch also reproduces: the actual service-generated `site:<32 hex>`
ID rejects, while a UUID-shaped correlation positive control parses. This is not a
third reviewer thread or a claimed native failure.

The PHP session now converts only the intentionally empty payload to an object.
Nonempty payloads and nested lists/numbers/booleans/nulls/strings retain their types.
CanonicalJson and shared hashing are unchanged. The Python correlation field now
accepts precisely the normalized service grammar `[A-Za-z0-9._:-]{1,100}`, without
trimming or rewriting. Deployment/release UUID validation remains strict. The
bounded compatibility audit passes actual PHP-produced examples of all seven wire
operations, including service-produced nullable expectations and recovery context;
no further producer/parser mismatch was observed. Arrays/null/scalars/extra fields
for empty payloads, invalid correlation IDs, UUID/identity/shape faults and oversized
requests still reject. This is parsing evidence, not authority admission.

The original `Tree.write_new` reproduction created the final asset/manifest name,
wrote a three-byte prefix, then raised a controlled error. Real private files retained
`bod` instead of the complete synthetic CSS, and `{"f` instead of the complete
synthetic manifest; the next exact insertion raised FileExistsError. Original and
partial bytes/digests are retained outside Git. Unix metadata/dirfd behavior was
explicitly modeled on Windows; the executed writer/reader were production methods.

Only two production files change in this correction: `SitePublisherSession.php` and
`ubo_site_publish.py`. The new `Tree.publish_immutable` applies only to retained
assets and pinned manifests. It validates fixed object identity and owned directory
descriptors, creates a unique exclusive private temporary, handles partial writes,
verifies full length/digest/type/owner/single-link/device, applies final 0440/group
or private 0600 permissions, fsyncs and checks close before publishing. It reuses the
existing Linux `renameat2(RENAME_NOREPLACE)` primitive and syncs both affected
directories. Asset temporaries stay in private served-filesystem staging; manifest
temporaries stay in the private journal root. Those filesystems need not be equal.

Exact existing objects and matching no-replace race winners are safely read,
identity/mode checked and synchronized before reuse. Conflicting/partial objects
are never overwritten, deleted or repaired. Normal exception cleanup removes only
the invocation's proven-owned temporary; a secondary cleanup error cannot replace
the first error. A successful rename followed by sync failure remains an error and
retains the complete published object. Later exact object replay can verify it;
activation is not automatically replayed. SIGKILL/power loss does not run finally,
and private leftovers do not create permanent-name conflicts or authorize pruning.

The actual stage call sites use this primitive. Private incomplete stage writes,
replaceable state journals and current/candidate replacement retain their separate
semantics. Target locking, fencing, fresh authority, settlement, limits/timeouts and
admission were unchanged by that correction. At 659d59d the source-access bridge
was still missing; the current transfer section above supersedes that gap. The
three broader integration drivers remain incomplete; M6E remains PARTIALLY
IMPLEMENTED / REVIEW REQUIRED, with M6C/M6D closed and M6F–M6G not started.

The new publication suite executes the real primitive with real private regular-file
I/O, fault injection and exact replay checks. Dirfd, Unix ownership/modes/timestamps,
renameat2, directory sync and read-only-descriptor sync are Windows models. Initial
test-adapter failures exposed Windows text-mode newline conversion, deferred file
timestamps and read-only fsync differences; binary I/O and explicit model boundaries
correct those test adapters without relaxing production Linux checks. The existing
helper suite now invokes the actual primitive at stage's permanent-object call sites.

Corresponding Linux publication/race/sync/kill observations are authored within the
existing `stage_storage_fault` case; the top-level inventory and three INCOMPLETE
driver results are unchanged. That harness was not executed. Native ownership,
replacement races, filesystem durability, Apache and installed authority acceptance
remain separate unexecuted obligations. Current focused command/count/exit/duration
results are recorded below; the initial 68-program campaign remains historical.

## Implemented components and boundaries

| Component | Executable behavior |
| --- | --- |
| `ApacheDigitalOceanSitePublisher` | Implements the six unchanged SitePublisher operations and validates existing receipts/observations. No lifecycle, grant, deployment, job, attempt or DB-pointer writes. |
| `SitePublisherHostContract` | Protected Linux CLI startup, fixed config/receipt locations, root ownership and nonwritable ancestors, versioned staging bindings, distinct UIDs, immutable in-memory resolution, eight evidence-backed host gates, short-lived receipt and installed-file hashes. Unknown platform/config/provider/binding denies before helper/network/artifact work. |
| `SiteDeploymentRuntime` / factory | Hard-coded `apache-digitalocean-v1` only; startup creates dependencies, `assertReady` performs memory-only checks under locks. Existing M6C `SiteDeploymentArtifacts` and `LocalSiteArtifactStore` perform verification. No fallback, environment override, force switch, public dependency setter or production adapter. |
| `SitePublisherSession` | Bounded version-1 JSON and mandatory stage-only UBT1 transfer over the same protected local supervisor socket. Lease token stays inside the private channel. Exact sequence/shape/size checking; no commands, paths, credentials or hosts in the protocol. |
| Python `ubo-site-publish` / `ubo_site_publish.py` | Linux descriptor-relative private receive/retained reads and mutations; no worker source access, regular-file/owner/device/nlink checks, exact manifest/inventory/size/digest checks, exclusive staging, content-addressed site assets, sealed release replay, renameat2 no-replace sealing and sibling symlink replacement. |
| `SitePublisherAuthority` / `ubo-site-authority.php` | Separate read-only observer of committed operation, original execution, current attempt/lease/token/fence, binding, expected DB pointer and permitted phase. Dedicated protected SELECT-only credential contract; fresh read-only transaction completes before any reply. No real connection executed locally. |
| `SiteHttpHealthChecker` | Actual curl implementation pins destination and Host/SNI, verifies certificates, uses client TLS credentials, disables proxies/redirect following, and bounds body/header/connect/request/phase work. Tests use synthetic responses because desktop curl/TLS/Apache are not installed/verified. |
| Apache and systemd examples | Separate active/candidate private TLS vhosts, mTLS plus network access control, static-only handlers, GET/HEAD, exact per-site assets, no-store/security headers, absence 503 and bounded supervisor sessions. Templates only; all unknown host/account/group/certificate values remain placeholders. |

The only existing integration changes are the factory, runtime and two protected CLI
entry points. **SiteDeploymentService and its six-operation interface are unchanged.**
CLI startup happens before claiming SQL work; after a claim it binds the trusted lease
to the session. The contextless `inspectCurrentRelease` consequently uses that already
bound lease rather than treating target IDs as authorization. Unbound sessions deny.
No public service dependency injection or state-machine redesign was needed.
Startup also verifies the fixed application `private/config/env.php` ownership/hash
and staging APP_ENV before any database connection; application config and client
private keys must have no other-user permissions. Credentials are never returned
or added to common installed-source inventories. Artifact work/sealed roots are
owned by the configured worker, with root-owned ancestors and no group/other writes.

## Helper authority, fence and lock lifetime

The supervised helper holds the real per-target flock from its first operation through
candidate health, activation, both active checks and DB-result coordination. A cached
PHP instance is only the transport handle; it does not own the OS lock. A separately
constructed publisher uses the same runtime session. Another process must acquire the
actual lock and pass durable fence admission. An equal fence from a different helper
session fails closed, requiring fresh recovery after a disconnect.

The root-owned supervisor socket restricts clients to the dedicated worker group; the
helper independently checks its peer UID. The authority socket is publisher-owned 0600
inside root-owned nonwritable ancestors and is served by a separate fixed systemd
service. PHP does not assume an undocumented `SO_PEERCRED` struct API. Authority DB
credentials are in a separate protected file, readable only by the authority account;
the non-secret config pins their digest. Worker/helper config and every included PHP
source are pinned by the root-owned receipt. Root/operator integrity is part of the
trust boundary; evidence digests are attestations, not self-proving host tests.

Lower fences reject; equal fences require the same operation, producer, attempt,
session and permitted nonregressing phase. An unsettled target accepts a greater fence
only for recovery of that same original execution. New recovery never stages or
activates a candidate. Accepted higher fences and switch intent are flushed/fsynced
before pointer mutation. The journal records before/after identity and settlement.
Inspection checks both actual link and full retained bytes. Contradictory or ambiguous
records block, including an indistinguishable interrupted switch between two operation
identities serving the same release.

The helper independently re-reads authority immediately before switching. The committed
M6D activation-authorizing transaction remains the authorization point; no claim of
continuous SQL locking across filesystem work is made. A revoked lease cannot finalize
success; a new owner still has to acquire the external lock. Sessions last at most
300 seconds, with absolute 30-second operation/transfer bounds and an independent supervisor hard timeout.
No SQL transaction spans helper, HTTP or artifact I/O.

After result handling, the CLI requests settlement; the helper resolves the committed
DB outcome itself. A missing acknowledgement, unavailable authority, nonterminal result
or unexpected pointer never authorizes rollback. EOF, kill, timeout or cleanup failure
releases descriptors while the durable unsettled record continues to block ordinary
work. Recovery uses a fresh lease/token/fence. Primary CLI errors are reported before
any secondary settlement error; logs contain fixed safe classifications.

## Immutable activation, restore and health

M6C remains the package authority: its unchanged validator/store first verifies the
sealed source. The helper consumes the existing `ubo-static-site-v1` manifest; it does
not render, scrape previews or copy operational PHP/configuration. It hashes raw
canonical manifest bytes, complete public inventory, the distinct marker payload hash
and all public files. Private metadata is retained outside the served tree. Source
symlinks/hardlinks/special files, extra inventory, foreign identities and conflicting
targets deny. Directory descriptors and no-follow opens enforce containment; sealing
uses Linux `renameat2(RENAME_NOREPLACE)`, not an existence check followed by overwriting
rename. Files and relevant directories receive checked fsync operations.

Candidate and current are separate relative symlinks to a verified release. Current
replacement is one same-filesystem rename. No unlink-then-link or in-place served-file
copy occurs. Same-site content-addressed assets are retained without a prune path.
Restore uses an already verified known-good release through service authority.
Compensation requires fresh recovery, a proven uncommitted original result and exact
candidate CAS; it restores the recorded previous identity. First deployment removes
only that matching current link, with the service then requiring the separate 503/no
marker/no-current profile. A helper receipt alone cannot settle safe failure.

One checker call is one observation, without an internal retry loop. M6D owns its two
separated active checks. Both share the checker’s 30-second active phase budget; each
request uses at most two seconds to connect and five seconds total. Marker/home/page,
CSS and referenced home media are checked against independently verified origin
inventory/digests/MIME/size. Evidence booleans reflect the observations, including TLS,
destination, redirect, Age/cache and no-store. Response bodies, URLs and credentials
are not retained. No browser navigation/CSP execution or inert-control execution is
claimed; those remain M6G.

## Requirement mapping within the existing M6 matrix

This is a component mapping, not a replacement requirement matrix. All 77 original
definitions remain byte-identical under the M6E scope guard.

| Existing requirements | Components / evidence | Remaining obligation |
| --- | --- | --- |
| D01, D07, D18, R01–R05 | Publisher/helper, M6C artifact child, helper tests | Linux descriptor/rename/mode/fsync and real Apache retention/restore |
| D02/D03/D06/D14, operational admission and isolation | Host/runtime/factory/CLI, host/protocol/authority tests, existing M6D regressions | Installed identities, protected source/runtime, real supervisor/socket permissions |
| C01–C06, C08–C13, D05/D11–D13/D17 | Durable helper session/fence/intent, read-only observer, modeled authority/COMMIT/kill/storage tests | Installed worker–helper–authority integration, native lease/result races, kernel fault and process-death proof |
| D09/D10/D17, R04/R05 | Actual checker with real M6C bytes and controlled response/clock tests | Real TLS/Apache candidate/active/503 and full restore under faults |
| D08 | Operator templates; routine publisher has no configtest/reload path | Incomplete isolated provisioning driver; old config/current preservation and uncertain reload proof |
| B14/B16 served-policy/header allocation | Two private static Apache templates and isolated gate inventory | Real served headers/access/handler policy; integrated browser allocation remains M6G |

## Local validation and current limits

Executed command/version/count/exit/duration inventory is recorded below. Full stdout/stderr and SUMMARY.txt are retained outside Git. The existing
M6C/M6D scope guards remain historical source-bound checks, not current M6E allowlists.
Their files, baselines and accepted native evidence are unchanged. The new M6E guard
compares exact allowed paths against `303b073` in working, index and HEAD views and
preserves all other baseline blobs/modes, all 25 migrations and all 77 definitions.

The [isolated runbook](sprint-8.8-m6e-isolated-validation.md) distinguishes authored
Linux component cases from still-incomplete Apache/provisioning/installed-integration
drivers. The harness returns INCOMPLETE rather than treating those missing cases as
PASS. This concrete integration gap is why the current milestone is **PARTIALLY
IMPLEMENTED**, despite executable local publisher/helper/health/authority components.
The local bounded bridge now replaces the unusable direct-source reader while
preserving worker-private M6C 0700/0600 modes and separate unprivileged identities.
Actual installed ownership/runtime/authority gates remain unverified. Local transfer
success does not prove those gates or complete Apache/provisioning acceptance.
No production enablement, installation, operational host preparation, live database,
SSH, public URL, deployment, migration, provider, container, service, backup, system
restore, prune, merge or formal closure occurred. Fixture website restore tests are
distinct from operational backup/system restore.

### Executed Windows validation — 2026-11-01, initial 528b47f source

Installed tools: PHP 8.4.24 ZTS, Python 3.14.7 (existing GIMP-bundled
interpreter), Git 2.56.0.windows.2 and existing Git Bash. No packages installed.
For every PHP row below the exact command is
`php -d memory_limit=256M tests/<File>`; Python is the installed interpreter
with `-X utf8 -B tests/WebsitePlatformM6EHelperTest.py`. Durations are process
wall seconds. Counts come from executed output; assertion and scenario counts
are distinct. These are private-file/process and controlled PDO/HTTP/clock
checks, not real SQL, TLS, Apache or native milestone acceptance.

The initial 68-test batch had 67 exit-zero results and one outer timeout:
unchanged `WebsitePlatformM6CNativeLauncherTest.php`, 181.840 seconds, no
assertion failure before termination. That incomplete record is preserved.
It was rerun with a 1,200-second outer allowance for its many bounded fake-command
scenarios; the rerun result appears below. No native harness was invoked.
The latest focused reruns replace earlier counts here; all original logs remain
outside Git. The initial broad batch, including version probes, took 421.468 seconds.

| File in tests/ | Executed count | Exit | Seconds |
| --- | --- | --- | --- |
| `WebsitePlatformM2ApprovalInvariantTest.php` | 95 assertions | 0 | 0.258 |
| `WebsitePlatformM2DatabaseTest.php` | 69 assertions | 0 | 0.205 |
| `WebsitePlatformM2ServiceTest.php` | 103 assertions | 0 | 0.260 |
| `WebsitePlatformM3CompositionTest.php` | 29 assertions | 0 | 0.226 |
| `WebsitePlatformM3ConfigurationTest.php` | 42 assertions | 0 | 0.226 |
| `WebsitePlatformM3DatabaseTest.php` | 59 assertions | 0 | 0.244 |
| `WebsitePlatformM3LegacyHashCompatibilityTest.php` | 13 assertions | 0 | 0.244 |
| `WebsitePlatformM3MigrationTest.php` | 46 assertions | 0 | 0.544 |
| `WebsitePlatformM3RegistryTest.php` | 26 assertions | 0 | 0.232 |
| `WebsitePlatformM3RendererTest.php` | 34 assertions | 0 | 0.220 |
| `WebsitePlatformM3ReviewGateTest.php` | 84 assertions | 0 | 0.361 |
| `WebsitePlatformM3ServiceBehaviorTest.php` | 27 assertions | 0 | 0.314 |
| `WebsitePlatformM4ADatabaseContractTest.php` | 35 assertions | 0 | 0.236 |
| `WebsitePlatformM4AServiceBehaviorTest.php` | 87 assertions | 0 | 0.320 |
| `WebsitePlatformM4AServiceTest.php` | 31 assertions | 0 | 0.292 |
| `WebsitePlatformM4BBehaviorTest.php` | 170 assertions | 0 | 0.604 |
| `WebsitePlatformM4BViewTest.php` | 37 assertions | 0 | 0.429 |
| `WebsitePlatformM4CBehaviorTest.php` | 37 assertions | 0 | 0.367 |
| `WebsitePlatformM4CViewTest.php` | 21 assertions | 0 | 0.331 |
| `WebsitePlatformM5ABehaviorTest.php` | 103 assertions | 0 | 0.517 |
| `WebsitePlatformM5AViewTest.php` | 40 assertions | 0 | 0.362 |
| `WebsitePlatformM5BBehaviorTest.php` | 258 assertions | 0 | 1.353 |
| `WebsitePlatformM5BInputSessionTest.php` | 72 assertions | 0 | 0.329 |
| `WebsitePlatformM5BViewRouteTest.php` | 118 assertions | 0 | 0.728 |
| `WebsitePlatformM5CViewTest.php` | 314 assertions | 0 | 0.534 |
| `WebsitePlatformM6CAdmissionTest.php` | 58 assertions | 0 | 2.458 |
| `WebsitePlatformM6CArtifactTest.php` | 45 assertions | 0 | 1.411 |
| `WebsitePlatformM6CBoundsTest.php` | 29 assertions | 0 | 0.515 |
| `WebsitePlatformM6CCliProcessTest.php` | 383 assertions | 0 | 17.828 |
| `WebsitePlatformM6CCliTest.php` | 207 assertions | 0 | 14.643 |
| `WebsitePlatformM6CExecutionTest.php` | 216 assertions | 0 | 3.306 |
| `WebsitePlatformM6CIntegrationTest.php` | 54 assertions | 0 | 3.787 |
| `WebsitePlatformM6CJournalProcessTest.php` | 33 assertions | 0 | 3.066 |
| `WebsitePlatformM6CJournalReceiptTest.php` | 84 assertions | 0 | 0.212 |
| `WebsitePlatformM6CJournalSelectionTest.php` | 39 assertions | 0 | 8.824 |
| `WebsitePlatformM6CJournalTest.php` | 130 assertions | 0 | 0.697 |
| `WebsitePlatformM6CNativeDiagnosticsTest.php` | 95 assertions; 41 scenarios | 0 | 3.046 |
| `WebsitePlatformM6CNativeGitSourceTest.php` | 113 assertions; 49 scenarios | 0 | 18.970 |
| `WebsitePlatformM6CNativeInputTest.php` | 28 assertions | 0 | 0.579 |
| `WebsitePlatformM6CNativeLauncherTest.php` | 7410 assertions; 104 scenarios | 0 | 745.413 |
| `WebsitePlatformM6CNativeObservationTest.php` | 273 assertions; 103 scenarios | 0 | 10.076 |
| `WebsitePlatformM6CNativeSetupTest.php` | 751 assertions; 62 scenarios | 0 | 0.828 |
| `WebsitePlatformM6CRecoveryTest.php` | 135 assertions | 0 | 6.114 |
| `WebsitePlatformM6CSecurityTest.php` | 91 assertions | 0 | 1.130 |
| `WebsitePlatformM6CStoredInputTest.php` | 95 assertions | 0 | 1.474 |
| `WebsitePlatformM6CSupplementProtocolTest.php` | 236 assertions | 0 | 8.070 |
| `WebsitePlatformM6CSupplementSelectionTest.php` | 41 assertions | 0 | 9.443 |
| `WebsitePlatformM6CSupplementServiceTest.php` | 103 assertions | 0 | 3.809 |
| `WebsitePlatformM6CSupplementWireTest.php` | 4591 assertions; 562 scenarios | 0 | 0.234 |
| `WebsitePlatformM6DArtifactTest.php` | 4 assertions | 0 | 0.676 |
| `WebsitePlatformM6DAuthorityTest.php` | 96 assertions | 0 | 1.095 |
| `WebsitePlatformM6DCliTest.php` | 44 assertions | 0 | 4.508 |
| `WebsitePlatformM6DExecutionTest.php` | 63 assertions | 0 | 1.592 |
| `WebsitePlatformM6DNativeControlTest.php` | 1081 assertions | 0 | 0.480 |
| `WebsitePlatformM6DNativeHarnessTest.php` | 179 assertions | 0 | 5.913 |
| `WebsitePlatformM6DNativeSchemaTest.php` | 230 assertions | 0 | 0.530 |
| `WebsitePlatformM6DNativeWireTest.php` | 471 assertions | 0 | 4.420 |
| `WebsitePlatformM6DQueueTest.php` | 90 assertions | 0 | 1.986 |
| `WebsitePlatformM6DRecoveryTest.php` | 60 assertions | 0 | 0.916 |
| `WebsitePlatformM6DRequestTest.php` | 70 assertions | 0 | 1.049 |
| `WebsitePlatformM6DScopePolicyTest.php` | 27 assertions | 0 | 69.629 |
| `WebsitePlatformM6DTransactionTest.php` | 97 assertions | 0 | 0.717 |
| `WebsitePlatformM6EAuthorityTest.php` | 18 assertions | 0 | 0.393 |
| `WebsitePlatformM6EHealthTest.php` | 43 assertions | 0 | 0.766 |
| `WebsitePlatformM6EHelperTest.py` | 17 tests | 0 | 5.480 |
| `WebsitePlatformM6EHostTest.php` | 31 assertions | 0 | 0.364 |
| `WebsitePlatformM6EProtocolTest.php` | 19 assertions | 0 | 4.038 |
| `WebsitePlatformTransactionTest.php` | 773 assertions | 0 | 0.515 |

`WebsitePlatformM6CSecurityTest.php` reports one of two local link cases executed;
that is not a skipped Linux acceptance PASS. Older test output that says native
verification is pending remains historical text; closed M6D native PASS is still
attributed only to 7cdbbeef and its final receipt.

Expected stderr was inspected: injected database-operation/read failures contain
only RuntimeException, PDOException or LogicException classifications; the Git
source negative emits one fixed build-dependency RuntimeException classification.
Python unittest writes its normal per-case/OK output to stderr. These are retained
as expected fault diagnostics, not silently discarded. New focused PHP tests
have empty stderr. Initial development fixture cleanup failed at Windows path
length limits; the helper fixture now uses extended paths and all 17 cases finish
with cleanup. The timed-out launcher's private temporary root is retained as
incomplete-run diagnostics, not reported as cleaned.

The unchanged historical scope checks were also executed on this new M6E tree:
`WebsitePlatformM6CScopeTest.php` exits 255 at its frozen executable inventory;
`WebsitePlatformM6DScopeTest.php` exits 255 at the first M6E path outside its
historical allowlist. Neither check was edited, hidden or credited as PASS.
The separate historical M6D scope-policy fixture controls pass (27 assertions,
21 controls, exact fixture cleanup), as recorded above.

The current `WebsitePlatformM6EScopeTest.php` passed before staging with 58
assertions against working/index/HEAD and the exact 303b073 baseline. Post-staging
and committed views are recorded in the external SUMMARY after commit.
All 17 new/modified PHP files pass `php -l`; all four new Python sources
(including the extensionless launcher and authored isolated harness) parse with
the installed Python AST compiler without execution. The combined config example
parses as JSON. Documentation checks cover seven documents, 119 relative links,
paired fences and all 77 unchanged requirement rows; link anchors are not claimed
verified by that checker. `git diff --check` passes. Apache/systemd templates were
not configtested, installed or started.

### Executed focused review-correction validation

Installed PHP 8.4.24 ZTS, Python 3.14.7 and Git 2.56.0.windows.2. Eleven
focused programs exit zero: 278 PHP behavior assertions plus the current scope
guard, and 30 Python unittest cases (with additional subtests). These are local
fixture results, not native acceptance. All PHP commands are
`php -d memory_limit=256M tests/<File>`; Python commands use the existing interpreter
with `-I -B tests/<File>`. The table records measured process wall time.

| File | Actual count | Exit | Seconds |
| --- | --- | --- | --- |
| `WebsitePlatformM6EProtocolTest.php` | 19 assertions | 0 | 4.063 |
| `WebsitePlatformM6EHostTest.php` | 31 assertions | 0 | 0.430 |
| `WebsitePlatformM6EAuthorityTest.php` | 18 assertions | 0 | 0.482 |
| `WebsitePlatformM6EHealthTest.php` | 43 assertions | 0 | 0.801 |
| `WebsitePlatformM6DCliTest.php` | 44 assertions | 0 | 3.810 |
| `WebsitePlatformM6DExecutionTest.php` | 63 assertions | 0 | 1.140 |
| `WebsitePlatformM6DRecoveryTest.php` | 60 assertions | 0 | 0.618 |
| `WebsitePlatformM6EScopeTest.php` | 132 assertions | 0 | 2.381 |
| `WebsitePlatformM6EWireTest.py` | 5 unittest cases | 0 | 7.183 |
| `WebsitePlatformM6EPublicationTest.py` | 8 unittest cases | 0 | 3.443 |
| `WebsitePlatformM6EHelperTest.py` | 17 unittest cases | 0 | 4.447 |

The wire audit uses 11 actual PHP-produced valid examples across seven operations,
plus a serializer-only mixed-type example that intentionally fails the protocol
shape. Invalid empty payloads, correlations, UUIDs/identities/shapes and size limits
are tested separately. Actual memory-stream writes/reads are deliberately partial.
The publication negatives verify the injected fault was reached after a valid
publication baseline; complete prior objects and pointer sentinels are unchanged.

Expected stderr consists of Python unittest progress/OK and six fixed PDOException
classification lines from existing injected M6D execution/recovery failures. New PHP
suites have empty stderr. No unexplained output is ignored. The prior 68-program
campaign, 7,410-assertion synthetic launcher and closed M6C/M6D scope guards were
not rerun. Their historical failures/timeouts remain recorded above.

Changed PHP files pass `php -l`; changed Python files parse with the installed
interpreter's AST compiler without executing the Linux harness or creating bytecode.
Documentation links/fences, exact working/staged/committed scope and preservation
checks are retained with commands/timings in the external SUMMARY. Original failure
observations and corrected test runs are separate evidence files.
