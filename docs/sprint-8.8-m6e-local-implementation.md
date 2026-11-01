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

## Focused PR #131 review correction

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
admission are unchanged. The worker-private 0700/0600 source-access blocker is not
solved by this change. The artifact-transfer bridge and all three incomplete
integration drivers remain unimplemented; M6E remains PARTIALLY IMPLEMENTED /
REVIEW REQUIRED. M6C/M6D stay closed and M6F–M6G remain not started.

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
| `SitePublisherSession` | Bounded version-1 JSON over a protected local supervisor socket. Lease token stays inside the private channel. Exact sequence/shape/size checking; no commands, paths, credentials or hosts in the protocol. |
| Python `ubo-site-publish` / `ubo_site_publish.py` | Linux descriptor-relative no-follow source reads and mutations, regular-file/owner/device/nlink checks, exact manifest/inventory/size/digest checks, exclusive staging, content-addressed site assets, sealed release replay, renameat2 no-replace sealing and sibling symlink replacement. |
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
300 seconds, with 30-second socket waits and an independent supervisor hard timeout.
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
The installed integration also needs an explicit sealed-artifact access bridge:
M6C creates worker-owned 0700 directories and 0600 files, while this helper runs as a
distinct unprivileged UID. A read-only bind mount does not grant access, and an
inherited ACL can be masked by M6C's explicit chmod. This PR does not relax M6C modes,
add root/DAC privileges, or implement a private artifact-streaming channel. Thus the
direct source-tree reader cannot consume newly built M6C artifacts under the default
mode policy; the ownership/runtime host gates must remain unverified until a reviewed
bridge exists and passes installed integration tests.
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
