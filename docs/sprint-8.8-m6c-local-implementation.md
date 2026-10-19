# Sprint 8.8 M6C — Local artifact implementation

October 17, 2026. M6C — IMPLEMENTED LOCALLY / REVIEW REQUIRED.
M6B — COMPLETE / STAGING PASS / FORMALLY CLOSED by
[PR #127 acceptance](https://github.com/fvd8383/ultimate-back-office/pull/127#issuecomment-6241852763).
M6D–M6G — NOT STARTED. Migration 025 — APPLIED TO WORKING STAGING; do not reapply.
Production — UNAUTHORIZED / NOT DEPLOYED. This task performs local implementation
and synthetic/filesystem validation only. The 77-case M6 plan is not a passed matrix.

Baseline: `7ed48fbaa4858b1b5299dc7cbea98f96e98f4e45`; current origin/main was verified
at that exact merged PR #127 commit. No intervening main changes were found.
Branch: `codex/sprint-8.8-m6c-artifact-builder`. The operator-reported deployed app
remains `f466fc1cdbfb53325a1db11445736bdc5ea7c6f4`. Earlier M6B native results are
not attributed to these changed executable inputs. Original dated evidence is retained.

PR #128 correction work follows the completed review of original head
`d06f9e5ffbd24b8488e61c9ffb307aa9b0125984`. See the
[review corrections](#pr-128-review-corrections) for current behavior and fresh validation.
The original local PASS and reviewer observations below remain separate evidence.
The subsequent review of `7787bec` is addressed in
[complete inventory admission](#complete-inventory-admission); earlier validation totals
remain historical results for their respective corrections.
The review of `3e6871321820f02ddccab7872ae5f69976b91821` is addressed in
[pre-output execution denials](#pre-output-execution-denials). Its current handoff
records the operator-reported completed backup/download checkpoint below.
The subsequent operator-observed CLI failure at `d4fb3d6` is addressed in
[CLI execution-child profile and diagnostics](#cli-execution-child-profile-and-diagnostics).
That test/documentation-only correction preserves the earlier Windows results and
records the partial Linux result separately; the Linux gate has not passed.
The completed review of `8f04073325b3e01a1851aa0faaa4493fec3d059f` is addressed in
[execution-fixture prerequisite and initialization review corrections](#execution-fixture-prerequisite-and-initialization-review-corrections).
The earlier CLI-profile correction's validation remains historical evidence.
The review of `e067f05bd16ca41a46a1dc174d6471f4c826f5b8` is addressed in
[execution-fixture cleanup prerequisites](#execution-fixture-cleanup-prerequisites).
Its basename finding and new validation form a separate evidence layer.

## Implementation and wiring

| File | Responsibility |
| --- | --- |
| `private/classes/SiteArtifactBuilder.php` | Reproject exact stored source, compare persisted input, build separate documents, copy verified bytes, create marker/metadata and seal. |
| `private/classes/SiteArtifactValidator.php` | Exact expected inventory, bounded bytes, MIME, DOM/CSP/links/navigation, canonical metadata and all hash layers. |
| `private/classes/SiteArtifactStore.php` | Small candidate/write/seal/inspect/verified-read/full-verification boundary. |
| `private/classes/LocalSiteArtifactStore.php` | Attempt-private candidates, per-job writer lock, immutable release namespace, producer journals and bounded inspection. |
| `private/classes/SitePublicRenderContext.php` | Explicit public facts/configuration projection and approved contact/content-hash URL context. |
| `private/classes/SiteArtifactFiles.php` | Relative-path checks, bounded regular-file reads/writes, inventory, link checks and fixed worker exceptions. |
| `private/classes/SiteArtifactAssetResolver.php` | Exact site/revision/usage/asset binding; approved legacy bytes read-only. |
| `private/classes/SiteBuildRuntime.php` | Protected Linux CLI factory, pre-resolved worker evidence, exact checkout/toolchain identity, real dependency adapter. |
| `private/site-static/static-review-v1.css` | Repository-owned standalone CSS plus validated theme-variable rules. |
| `scripts/site-build-worker.php` | Explicit one-shot execution or automatic recovery for one explicit job; no daemon or scheduler. |
| `private/classes/SiteCompositionRenderer.php` | `renderPage` selects one main while retaining complete navigation; combined preview behavior remains. |
| `private/classes/SiteBuildService.php` | Real execute/reconcile gates, service-owned lease/source checks, private runtime factory. Existing result/replay/recovery transactions remain the lifecycle owner. |
| `private/classes/SiteBuildDependencies.php` | Adds internal `execute(job, producer, source, pulse)`; no public dependency setter. |
| `private/classes/SiteRevisionManager.php` | Returns already locked asset metadata privately from `lockBuildEligibility`; no copied lifecycle SQL or new migration. |

The path is explicit site/revision/expected hash → existing locked eligibility and
current actor checks → public projection and byte verification outside SQL → persisted
M6B input identity → execution lease → fresh locked source and requester check → render,
validate and seal outside SQL → independently verified producer evidence → existing
atomic release/validation/job/attempt/event commit. Lease heartbeats run between bounded
external operations, including immediately before seal. No SQL transaction encloses
rendering, Git inspection, asset reads or storage operations. Worker authentication
occurs before a CLI invocation can open its configured database.

Successful-history replay still precedes new-build eligibility. It does not require
current source approval, public projection, asset availability or file verification.
Current authorized readers/workers and stored immutable identity remain required.
New success, including recovery adoption, rechecks the original requester, source,
lease and ownership. EMD/internal-demo, active/published transitions, production
profiles and operator identity controls remain future-gated. No customer/admin route
or normal GET/POST/M5 approval starts a worker.

## DTO and public-field contracts

`prepareInput` returns exactly `public_composition`, `public_facts`, and
`ordered_asset_digests`, matching SiteBuildContract. Ordered asset entries are
`{usage_key:string, sha256:hex64, byte_size:int, mime_type:string}` sorted by usage.
No storage locator or metadata row is serialized into those DTOs.

Public facts permit business display name, phone, email and public address fields;
profile language, short/long descriptions and value proposition; selected/custom
service names/category names; public service-area mode/radius/location; and website/all
FAQ question/answer pairs. Private base street address requires the stored public-visit
flag. The snapshot builder already projects the approved public business address.
Briefs, references, internal IDs, guidance, pricing instructions, escalation/notification
rules, feedback, approval comments and credentials are excluded by construction.
Canary tests cover nested objects as well as top-level fields. The HTML scan supplements
this projection; it is not a serializer/redactor security boundary.

Public composition permits page key/title/slug/type/navigation label/order, selected
SEO and presentation fields, exact component/variant/schema/version and validated
configuration, section key/order, and theme key/version/colors/typography/layouts.
Repository component schemas reject unknown configuration fields recursively. Asset IDs,
source references, content hashes and database component IDs are omitted. Existing
legacy snapshot compositions are refused; legacy local asset bytes are the supported
compatibility input. No legacy generated pages or live business-profile refresh is used.

Context permits only content-hash asset URLs, validated tel/mailto links and an existing
contact page. Each complete document has language, charset, viewport, page title,
site/release marker, one main and repository layout/component semantics. Selected theme
colors, fonts, scale, spacing, corners, buttons and page widths are applied. Forms retain
fields, required flags and label text as disabled controls with no form element/action.
No JavaScript, remote stylesheet, application CSS, authenticated preview or legacy upload
URL is present. CSP is embedded in the document; no HTTP header deployment is claimed.

Private locked source additionally contains `asset_metadata` (the existing locked
site_assets rows), used only with the exact composition usage/asset ID. Resolver checks
site/business ownership, ready state, current permitted rights, size, digest and MIME.
Only `source=legacy_247sp` plus a contained `/uploads/...` locator under the configured
legacy root is supported. All reads are read-only; arbitrary URLs, Spaces, unsupported
sources and missing roots fail with bounded errors. PNG/JPEG/WebP and conservative
passive PDFs are permitted. Active/encrypted/object-stream/ambiguous PDFs are rejected;
this is not a general PDF sanitizer. All SVG is currently refused: no repository-owned
SVG asset/digest exception is needed or enabled by this bundle.

## Package and deterministic hashes

```text
<site_key>/<reserved-release_key>/
  public/index.html
  public/<validated-slug>/index.html
  public/assets/<sha256>.<allowlisted-extension>
  public/_release.json
  metadata/manifest.json
  metadata/validation.json
```

Only `public/` is a future serving boundary. There is no alias, vhost, activation link
or download route. A database release ID is assigned only by accepted M6B success.

File entries are `{relative_path, sha256, byte_size, mime_type}`, sorted by path.
`public_payload_hash` hashes the entries excluding the marker. `_release.json` contains
exactly contract_version, site_key, release_key and public_payload_hash. `artifact_hash`
hashes all public entries including the marker. `validation_hash` hashes exact canonical
validation-file bytes. `manifest_hash` hashes exact canonical manifest bytes; the manifest
does not contain its own hash. Neither metadata file participates in artifact_hash.
The verifier reconstructs expected metadata and compares actual bytes/inventory, rather
than trusting claimed file hashes. HTML contains site/release identity, never artifact_hash.

Private manifest fields are explicitly constructed: package/schema, public identity,
source revision ID/number/hash, generic composition mode/version, exact selected
component/theme versions, input identity, builder SHA/version, registry/toolchain
identity, profile/options, file entries/totals, job-created timestamp, hash layers,
validation summary and unavailable capabilities. It contains no raw source or actor,
worker, correlation, host URL, credentials or temporary paths. Producer journal data
is separate from deterministic package metadata.

Builder identity includes exact clean Git HEAD, component/theme manifest digest,
static bundle digest, canonicalization/render contract, PHP/libxml versions and relevant
extension presence. Identity excludes wall clock, environment, worker and actor.
Metadata uses the persisted job created_at value, never a new retry timestamp. Same fixed
job/input generated into two disposable stores produces identical artifact and
manifest hashes, including across different producer attempts. A different reserved
release intentionally changes HTML/marker hashes.
The canonical UTF-8/NULL/integer/list/map golden digest was independently checked using
.NET SHA256. Existing M6B input/idempotency contract tests remain enabled.

## Store, sealing and recovery

Configured work/artifact roots must already exist, be distinct, outside the repository,
and on the same filesystem. Missing configuration has no default root. The desktop
creates only random private temporary test directories, never proposed `/var/lib` roots.
Runtime Linux configuration additionally requires root-owned protected ancestors and private worker-owned
work/artifact roots. The store API rejects traversal, encoded/absolute paths, alternate
separators, dotfiles, Windows device names, duplicate/colliding inventory, symlinks,
hardlinks, special files and unlisted content. Private roots exclude hostile concurrent
path replacement; PHP lstat/fstat checks do not substitute for OS ownership isolation.

An exclusive nonblocking per-job lock spans construction. The database uniquely reserves
release_key; cooperating writers for that job share its lock. A producer journal is
written/fsynced before candidate content. A separate seal proof binds the exact job,
original execution ID/number, source/input/builder and verified manifest digest before
rename. Files use exclusive creation, bounded writes and fsync. Complete inventory and
bytes are rechecked before and after same-filesystem rename. Existing sealed output is
never copied over or overwritten. The API does not permit write access to sealed paths.

`verifyRelease` uses the persisted job and original producer to find the journal; a
receipt UUID is only a hint. It independently checks binding, proof and all bytes.
Recovery takes the existing writer lock without creating missing candidate/journal
content. An active writer, missing journal, mismatched evidence, tampered sealed output
or prepared seal without final bytes returns unknown. A matching journal with no seal
proof/final directory and no active writer proves safe absence or private quarantine
of a bounded pre-seal candidate. That candidate cannot resume or become public. Existing
M6B retry policy may retry a transient failure below its execution limit; recovery itself
never renders, renames, fills missing files or creates replacement content.

After three executions, recovery still uses the existing separate automatic/operator
budgets. Valid sealed output can be adopted only through current requester/source checks.
Missing/tampered final output blocks; original producer remains expired. Lost SQL commit
acknowledgement is resolved through existing committed-history replay, with no compensation.
Known safe failure is distinct from unknown outcome. Current CLI exposes automatic recovery
only; it does not invent an operator identity or implement M6F controls.

Cleanup accepts an in-memory random ownership handle, checks the entire candidate
inventory against files that handle wrote, and deletes only that candidate. Unknown
orphans, another attempt and sealed releases are retained. Test cleanup checks the exact
random run root and never follows symlinks. Package limits are 100 pages, 2,000 total
files including metadata, 20 MiB/asset, 1 MiB/HTML, 100 MiB/public package, 1 MiB/manifest
and 16 KiB/validation. The existing service evidence summary remains capped at 16,000 bytes.
Candidate creation preflights enough available filesystem space for the maximum package
and metadata; this is advisory and does not replace bounded write-failure handling.

Atomicity means cooperating-process flock plus same-filesystem rename under protected
roots. The adapter fsyncs file contents but does not claim directory-entry fsync or
Linux power-loss durability. A crash may leave journals/candidates needing inspection;
ambiguity fails closed. Windows tests are not proof of Linux mount, permission,
cross-process scheduling, directory durability or MySQL/filesystem integration.

## Trusted CLI configuration contract (not provisioned)

Only the exact repository `scripts/site-build-worker.php` entry under Linux CLI and a
matching non-root POSIX UID can initialize the factory. Fixed root-owned, non-writable
`/etc/ubo-site-build-worker.json` must contain exactly: version=1, environment=staging,
worker_id, worker_uid, builder_code_sha, work_root, artifact_root and legacy_upload_root
(nullable when no local assets are needed). No arbitrary configuration path is accepted.
The checkout and tracked code must be root-owned/protected and clean at the configured
SHA. Ignored `private/config/env.php` must be protected and APP_ENV must match staging;
database details come only from that existing trusted configuration. No environment
variable, CLI DSN, actor, dependency class, path or trusted flag overrides these checks.
The only external executable is fixed `/usr/bin/git`, with bounded output/time and argv.
External process/configuration evidence is resolved before lifecycle transactions. Builder identity rechecks clean HEAD and bundle/registry/runtime identity outside locks; workerIdentity itself is an in-memory check. The fixed Git invocation permits only its verified root-owned checkout via safe.directory.

The worker requires existing PHP DOM/libxml, filter, image metadata functions, fsync
and POSIX identity support. Linux `php -n` may lack DOM/POSIX even though Windows builds
include DOM: use the approved installed invocation profile later; missing capabilities
fail closed. No PHP configuration or package was installed/changed here. `--help`, invalid
arguments and web SAPI return before runtime bootstrap; `--once` claims at most one job,
and `--recover JOB_ID` performs at most one automatic recovery claim. No scheduling,
service provisioning, operator controls, deployment or publication is implemented.

## Original local validation and later native gate

The local counts in this section describe original head `d06f9e5`, not the corrected head.

M6C suites cover artifacts, security, service integration, bounds, CLI and scope. Real
file bytes are used with fake lifecycle SQL. Scope guards retain existing negative
protection and add exact M6C paths only; historical migration integrity pins remain.

The six M6C suites pass 274 assertions: artifact 45, security 91, service integration 54,
bounds 19, CLI 12 and scope 53. Security ran a real Windows hardlink rejection; symlink
creation was unavailable (one of two native local link cases executed). CLI checks
include one actual CGI denial. Bounds use test-only counter doubles for 100 MiB and
2,000-file edges, with real bounded files for other cases. Artifact checks also pass
with configured PHP and `php -n -d disable_functions=ctype_digit,mb_strlen` (45 each).
Windows directory-renaming tests required the normal approved temporary-file execution
context after the sandbox denied rename; this is not Linux evidence. No browser or
Narrator matrix was run.

All 66 available standalone `*Test.php` suites pass after focused corrections. The
initial 65-suite enumeration predated the new bounds suite; that suite passed separately.
The initial M2 scope failure was corrected with an exact file-primitive allowance while
retaining network/provider/lifecycle guards; the M6C scope check was corrected to inspect
only the added metadata return in the existing revision owner. Neither change removes a
negative guard. The existing launcher suite passes 2,131 assertions across 161 isolated
Bash scenarios, 47 mount cases and 27 no-ctype port/type cases per mount form, using three
PHP profiles and fake commands only. The existing migration-runner suite passes 848
simulated assertions without SQL; the M6B behavior suite passes 401 assertions.

All 236 tracked/new PHP files pass lint. The 25 changed PHP files and final runtime/store
edits were re-linted. Three script syntax checks pass: Bash `RunM6BMySql.sh`, PowerShell
`RunM6BMySql.ps1` and JavaScript `WebsitePlatformM5CBrowser.cjs`; these were syntax checks,
not native harness or browser execution. Eleven modified Markdown documents have balanced
fences and eight added local links resolve. A bounded current-versus-historical status
review preserved dated evidence and corrected live M6B/M6C status. `git diff --check`
passes. Raw initial results, corrected final results and hashes are retained outside the
repository with the task summary.

| Planned cases | This task's layer | Remaining boundary |
| --- | --- | --- |
| B01–B05 | Executed local: M6B material/non-material/source gates; M6C real output and successor rejection with fake SQL. | Native MySQL plus real approved asset integration, not reattribution of M6B evidence. |
| B06, B09, B22 | Executed local decision/clock doubles and existing replay tests; concurrency simulated only. | Independent native connections/process barriers and lease races. |
| B07–B08, B11 | Executed local real candidate/seal/conflict/quarantine with fault doubles; stable reserved identity and bounded retry. | Disk-full/permission/process-kill and native durability cases authored below, unexecuted. |
| B10, B12–B16 | Executed local hash golden/reproducibility, privacy, DOM/CSP/navigation/forms, MIME/path/rights/bounds and tampering. | Linux symlink/device/permissions and browser/actual-byte review; no production claim. |
| B17–B21, B26 | Executed local preserved M6B authorization tests and CLI/CGI denial; current roles/budgets retained. | Real trusted Linux startup and isolated MySQL gates. |
| B23–B25 | Executed local real artifacts plus fake-SQL revocation/adoption/lost-ack/replay faults. | Native lock/commit-ack races across storage and SQL. |
| C03, C04, C05, C07, C09, C11–C12 (build portions), U01/U02 | Executed local ownership/quarantine, expired producer adoption, no rerender, counters and bounded recovery doubles. | Native process faults/locks; operator CLI identity is future control scope. |
| D01–D26, R01–R06, deployment portions of C01–C13, U03 | Later scope: M6D–M6G. | No deployment, activation, restore, production grant, or new UI tested/implemented. |
| S01–S03 | M6B schema remains unchanged; local canonical-source guards execute. | Existing dated M6B native evidence retained; no migrations/SQL executed in this task. |

Later separately approved isolated staging BUILD-ONLY validation must use an isolated
fixture database and private run roots, never the working staging database or the
destructive M6B fixture runner aimed at it. Required authored native cases:

1. Verify exact reviewed SHA, existing extensions/invocation, UID/owner/modes, protected
   ancestors, same device and private serving boundaries; dirty/wrong-SHA/config/UID
   startup must create no DB work. Do not provision or deploy without separate authority.
2. Use synthetic approved material/non-material revisions and approved local image/PDF
   bytes; prove exact source/tenant/rights/expiry and byte verification without live providers.
3. Run execution through actual MySQL 8.4, checking no external I/O under SQL locks and
   all release/validation/event writes atomically; test requester/source races and lost ack.
4. Kill separate worker processes before journal, during candidate write, after seal proof,
   after rename, and before/after SQL commit. Prove active-writer exclusion, unknown vs
   quarantine, immutable winner, original producer binding and exhausted-budget adoption.
5. Test Linux symlink/hardlink/device/permissions, collision, quota/disk-full and same-mount
   rename assumptions, including directory durability and retained evidence after restart.
6. Reconcile exact run-owned files/rows and preserve unrelated data/orphans. Verify zero
   deployment targets/pointers/approvals/provider actions. No activation or restore.

No migrations were changed or added. All 25 canonical Git blobs remain byte-identical
to baseline; reviewed 017/019 pins and existing Windows checkout line endings are retained.
Migration 025 raw-file and canonical SHA-256 remains
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
No SSH, live DB, SQL/migration execution, provider call, Spaces upload, host provisioning,
runtime installation, deployment, publication, merge or auto-merge occurred.

## Original implementation changed-file inventory

37 files: 19 added and 18 modified. No deletions or renames.

| Change | File |
| --- | --- |
| Modified | `docs/247sp-website-generation-architecture.md` |
| Modified | `docs/codex-handoff.md` |
| Modified | `docs/database-plan.md` |
| Modified | `docs/first-customer-checklist.md` |
| Modified | `docs/production-readiness-review.md` |
| Modified | `docs/sprint-8.8-m6-implementation-plan.md` |
| Modified | `docs/sprint-8.8-m6b-closeout.md` |
| Modified | `docs/sprint-8.8-m6b-linux-validation.md` |
| Modified | `docs/sprint-8.8-m6b-local-implementation.md` |
| Added | `docs/sprint-8.8-m6c-local-implementation.md` |
| Modified | `docs/sprint-8.8.md` |
| Added | `private/classes/LocalSiteArtifactStore.php` |
| Added | `private/classes/SiteArtifactAssetResolver.php` |
| Added | `private/classes/SiteArtifactBuilder.php` |
| Added | `private/classes/SiteArtifactFiles.php` |
| Added | `private/classes/SiteArtifactStore.php` |
| Added | `private/classes/SiteArtifactValidator.php` |
| Modified | `private/classes/SiteBuildDependencies.php` |
| Added | `private/classes/SiteBuildRuntime.php` |
| Modified | `private/classes/SiteBuildService.php` |
| Modified | `private/classes/SiteCompositionRenderer.php` |
| Added | `private/classes/SitePublicRenderContext.php` |
| Modified | `private/classes/SiteRevisionManager.php` |
| Added | `private/site-static/static-review-v1.css` |
| Added | `scripts/site-build-worker.php` |
| Modified | `tests/WebsitePlatformM2ScopeTest.php` |
| Modified | `tests/WebsitePlatformM6BScopeTest.php` |
| Added | `tests/WebsitePlatformM6CArtifactTest.php` |
| Added | `tests/WebsitePlatformM6CBoundsTest.php` |
| Added | `tests/WebsitePlatformM6CCliTest.php` |
| Added | `tests/WebsitePlatformM6CIntegrationTest.php` |
| Added | `tests/WebsitePlatformM6CScopeTest.php` |
| Added | `tests/WebsitePlatformM6CSecurityTest.php` |
| Modified | `tests/support/WebsitePlatformM6BDependencies.php` |
| Modified | `tests/support/WebsitePlatformM6BScope.php` |
| Added | `tests/support/WebsitePlatformM6CFixture.php` |
| Added | `tests/support/WebsitePlatformM6CScope.php` |

## PR #128 review corrections

M6C remains **IMPLEMENTED LOCALLY / REVIEW REQUIRED**. Native build-only validation is
pending. This correction does not reopen M6B or authorize any operational worker run.
The existing branch and PR remain the only implementation review; no new PR is created.

### Reproductions and corrections

The four review comments at original head `d06f9e5` had no replies when inspected.
Original reported local PASS (66 standalone suites, 274 M6C assertions) is retained above.
The completed review is a separate observation, including the bounds failure and unsafe
Linux CLI behavior. Fresh before/after stdout, stderr, exits, profiles and summary are
saved outside the repository in the correction evidence directory.

| Finding | Before observation | Correction |
| --- | --- | --- |
| [4304835568](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4304835568) producer evidence | Reproduced on Windows PHP 8.4.24 with fake SQL/real private files: remove or change asset bytes after request/claim; execution throws `artifact_invalid`, verifier returns `unknown`, completion becomes `reconciliation_required`. | Compare cheap stored input/identity first, then create the existing candidate/journal before execution asset-byte validation. Request-time preparation still writes no journal or attempt. |
| [4304835573](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4304835573) verification heartbeat | Reproduced with a controlled database clock advanced during actual file verification: the final completion rejects an expired lease and inserts no release. No multi-minute sleep or native Linux claim. | Service-owned pulse flows through dependency, real runtime adapter, store, inventory, chunked reads and validator for every active verification path. |
| [4304835576](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4304835576) bounds alias | Exact original `php -n tests/WebsitePlatformM6CBoundsTest.php` exits 0 with 19 assertions locally, empty stderr; the reviewer failure did not reproduce. A deliberately retained PHP reference deterministically makes the shallow saved copy inherit 2,000 files. | Reconstruct only independent scalar/file counters, restore those fields in `finally`, and retain the original live lock/ownership state. A forced-reference regression proves isolation, restoration, later valid write, duplicate denial and orphan preservation. |
| [4304835581](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4304835581) CLI safety | Review identified that Linux passes the OS gate and can reach protected configuration or operational work. No real `--once`/`--recover` was run to reproduce this on a host. | Valid arguments run only in isolated child fixtures using actual runtime/entry source with deny-only namespace doubles. Production runtime selection/configuration stays fixed. Missing capability checks now precede configuration access. |

Instrumentation of the original bounds test's unchanged counter sequence under the
current isolated fixture reports an absent saved candidate reference, restored bytes=0
and file_count=0, and the original 19 assertions pass. The forced-reference case is
separate and deterministic; it does not establish a native Linux reproduction.

### Producer evidence and failure settlement

Execution retains current requester/source/lease checks before external writes, compares
the stored public input and exact asset descriptors, and calls `createCandidate` before
fallible asset reads. The existing fsynced journal binds job/site/release/input/builder
and original execution attempt; candidate content follows that evidence. The builder's
`finally` releases the writer handle on asset, callback, validation and seal failures.
Successful package/hash definitions and deterministic metadata are unchanged.
The private journal is written to `.json.pending` and published by same-directory rename
only after write/flush/fsync/mode checks succeed. Even complete-looking pending JSON is
unknown evidence; a failed journal write cannot masquerade as verified safe absence.
This does not add a directory-entry power-loss guarantee.

An intact journal plus an exclusively observed empty/private candidate permits quarantine
settlement. Missing or corrupt asset bytes remain integrity failures, never automatic
transient retries. A simulated unreadable source or interrupted copy returns the existing
storage failure and can retry under unchanged policy with a new authorized attempt,
same requester/reserved release and incremented execution count. No missing journal is
interpreted as safe absence. Before-journal interruption, a partial journal-write fault
and seal-proof-without-final-output remain unknown/reconciliation states. These storage
faults are explicit local doubles; real disk/fsync/permission failures remain native gates.
Repeated failure/recovery completion returns recorded history without extra effects.
Recovery never regenerates output, removes unknown data or changes execution budgets.

### Active verification leases

`SiteBuildService::leasePulse` shares the existing 25-second monotonic cadence and forced
checkpoint behavior. It captures only the active execution or recovery lease. The optional
clock argument is private and used through Reflection only in isolated cadence tests.
The 120-second lease and persisted 900-second execution / 300-second recovery deadlines
are unchanged. Renewal is a short existing transaction between external operations.

Ordinary success, failure verification, `reconcileBuild`, and recovery completion's
independent reinspection all pass that pulse to `verifyOutcome`, then `verifyRelease`.
Inventory checkpoints occur for directories/entries; reads check progress around each
at-most-1-MiB chunk; file validation/hashing and pre-render asset verification also pulse.
The store distinguishes a callback exception from corrupt evidence and propagates it,
releasing its lock. Final locked requester/source/lease/ownership checks remain in place.
A single blocking OS read cannot be interrupted by a PHP checkpoint; native filesystem
latency/crash behavior remains a separate validation requirement.

Controlled-clock tests span 125 seconds for ordinary completion and failure inspection,
and 125 seconds each for recovery reconciliation and independent adoption within its
original 300-second deadline. The original producer stays expired and unchanged.
Natural absolute-deadline exhaustion, mid-verification ownership/lease loss, callback
exceptions and replacement worker identity stop processing without release/audit success.
Already committed completion returns before verification and heartbeat writes. Read-only
manifest/history inspection has no lease allocation or renewal.

### Safe cross-platform CLI tests

Actual entry processes cover help, invalid arguments and available CGI 404 denial only.
Every valid `--once`/`--recover` test invokes `WebsitePlatformM6CCliChild.php`, never the
real host worker. The child evaluates copies of the actual entry/runtime source in a
test namespace; a checked count of bootstrap imports is replaced with sentinels.
OS/SAPI/POSIX/capability/path/stat/config/process/service doubles fail closed. Filesystem
configuration reads are synthetic; external Git/process calls are blocked; no DB/queue
or artifact write is available. A configured-host double is included, so safety never
depends on absence of `/etc/ubo-site-build-worker.json`. No host file is renamed or hidden.

Assertions check actual child exit/stdout/stderr plus no-bootstrap (help/invalid/web),
no-host-config, no-DB, no-claim and no-artifact-output traces. Parent-owned temporary
stdout/stderr captures are distinct from worker output and cleaned exactly. The parent
enforces ten-second / 64-KiB bounds without blocking on Windows anonymous pipes. A stalled
child is terminated in a one-second timeout regression. Linux owner/UID/config/capability
branches are deterministic simulations, not a native Linux startup PASS.

During fixture development, Windows `dirname` semantics caused a simulated Linux path
walk to stall; only those isolated test processes were stopped. The fixture now models
POSIX path ancestors explicitly. A simulated `proc_open` signature was corrected to
retain its by-reference pipes argument. These intermediate fixture failures are retained
separately from final results, along with corrected DTO/producer assertions in the new
recovery suite. No operational worker or new environment was used.

### Correction validation and inventory

Fresh full regression: **67 standalone suites, all exit 0; 593 M6C assertions**.
Every affected suite was first run individually with `php -n tests/<Suite>Test.php`,
then every available `tests/*Test.php` was run individually with `php -n`, including
all M2-M6B suites and the new recovery suite. No suite was disabled or skipped.

| M6C suite | Assertions | Exit |
| --- | ---: | ---: |
| Artifact/determinism | 45 | 0 |
| Bounds | 29 | 0 |
| Safe CLI | 186 | 0 |
| Integration | 54 | 0 |
| Recovery/progress | 135 | 0 |
| Scope | 53 | 0 |
| Security | 91 | 0 |

The existing M6B launcher test passed 2,131 assertions, 161 isolated Bash scenarios,
47 mount cases and 27 no-ctype port/type cases per mount form across three PHP profiles.
Its commands are fake: no native Linux/container/MySQL execution. The migration-runner
suite passed 848 simulated-lifecycle assertions without a database. Actual Windows
private-file and child-process tests include one CGI denial and one hardlink case;
SQL, clocks, disk faults and Linux startup/identity branches use explicit doubles.

`php -n -l` passed for all 238 tracked/new PHP files. Syntax-only checks passed:
`node --check tests/WebsitePlatformM5CBrowser.cjs`,
`bash -n tests/RunM6BMySql.sh`, and PowerShell `Parser.ParseFile` for
`tests/RunM6BMySql.ps1` (zero parser errors). The changed document's local link/anchor,
fences and milestone-status checks passed. Working and staged `git diff --check`
passed; the committed check is recorded in the outside-repository final report.
Actual per-suite exits/stdout/stderr are retained. Expected injected-error diagnostics
and Git checkout CRLF warnings were not silently relabeled as empty stderr.

Local runtime: PHP 8.4.24 ZTS, Visual C++ 2022 x64; libxml 2.11.9, Windows CLI.
Both default and `-n` invocations report no loaded php.ini. DOM, image metadata, fsync,
filter and ctype are available; POSIX and mb_strlen are absent. Additional artifact checks
use `-n -d disable_functions=ctype_digit,mb_strlen`; no installed PHP configuration is changed.
Script tools: Node v24.22.0, PowerShell 7.6.5, Git Bash 5.3.15. Initial Bash syntax-only
startup was blocked by Windows sandbox named-object permissions; the approved normal
execution context passes the same `bash -n` command. Initial diagnostics remain recorded.

Correction-only inventory relative to `d06f9e5`: 16 files, 14 modified and two added.

| Change | File |
| --- | --- |
| Modified | `private/classes/LocalSiteArtifactStore.php` |
| Modified | `private/classes/SiteArtifactAssetResolver.php` |
| Modified | `private/classes/SiteArtifactBuilder.php` |
| Modified | `private/classes/SiteArtifactFiles.php` |
| Modified | `private/classes/SiteArtifactStore.php` |
| Modified | `private/classes/SiteArtifactValidator.php` |
| Modified | `private/classes/SiteBuildDependencies.php` |
| Modified | `private/classes/SiteBuildRuntime.php` |
| Modified | `private/classes/SiteBuildService.php` |
| Modified | `tests/WebsitePlatformM6CBoundsTest.php` |
| Modified | `tests/WebsitePlatformM6CCliTest.php` |
| Modified | `tests/support/WebsitePlatformM6BDependencies.php` |
| Modified | `tests/support/WebsitePlatformM6CFixture.php` |
| Modified | `docs/sprint-8.8-m6c-local-implementation.md` |
| Added | `tests/WebsitePlatformM6CRecoveryTest.php` |
| Added | `tests/support/WebsitePlatformM6CCliChild.php` |

All 25 canonical migrations and reviewed 017/019 pins remain unchanged from baseline;
025 remains applied to working staging, with the canonical/raw hash recorded above.
No schema, canonical migration runner, M6B launcher, wrapper, TLS or host-permission
changes occur. M6B identity, authorization, lock order, result/audit atomicity and budgets
remain the lifecycle authority. Native Linux startup/permissions, process/crash races,
power-loss behavior and actual MySQL/filesystem integration remain pending. No server,
Docker/WSL/package setup, live database, migration, deployment, provider, M6D–M6G or
Narrator action is part of this correction.

## Complete inventory admission

This focused correction follows [finding 4304989392](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4304989392)
on `7787bec22b8eb5a8cfed91c4ed183b4f2fe2e511`. The branch and PR are unchanged.
The previous producer-evidence, active-lease, independent-counter and isolated-CLI
corrections remain intact. M6C is **IMPLEMENTED LOCALLY / REVIEW REQUIRED**;
native build-only validation is **PENDING**.

### Admission and accounting

`SiteArtifactBuilder::prepareInput` now constructs the public projection and bounded
asset descriptors, calls the existing `SiteArtifactValidator::expected` inventory
validator, and only then verifies asset bytes. No second asset-count formula is added.
The existing service ordering remains current authorization/source eligibility,
preparation outside SQL locks, then a locked recheck and insertion. Successful-history
replay still precedes new-request preparation; the service itself is unchanged.

Total package files are distinct content-addressed media output paths + each HTML page
plus generated CSS + public release marker + two private metadata files. Thus total files
are distinct media + pages + 4, bounded by the unchanged `MAX_FILES = 2000`.
Multiple usages of the same digest/extension occupy one physical path. The existing
1,997-descriptor cap, 1-MiB input/manifest limits and byte/security limits remain separate
and unchanged. Deduplication does not waive them. Public manifest/DTO `file_count` still
counts media + pages + CSS + release marker; it excludes the two private metadata files.
No serialized field, profile or hash definition changes. Per-write, final inventory,
observed-byte validation and immutable sealing checks remain in place.

### Before/after evidence and boundaries

The original preparation path reached the first asset-validation call for metadata
projections of 1 page/1,996 assets, 2 pages/1,995 assets and 100 pages/1,897 assets,
while `expected` rejected those same 2,001-file projections as `artifact_invalid`.
Their actual canonical BuildInput sizes were 274,738, 275,078 and 308,670 bytes,
respectively, below the unchanged 1-MiB limit. MIME and digest formats, per-asset and
aggregate declared sizes, routes, theme and page-count inventory rules were valid.

These metadata tests deliberately stop at the resolver's existing guard before byte
validation. Synthetic digests do not establish real byte integrity, and this is not a
full large-package or native build reproduction. The one-/two-page metadata fixtures
isolate projection/inventory admission; they do not claim authoring eligibility for
thousands of usages within component/section cardinality limits.

Separate actual new-request reproductions use valid 80-page/1,917-asset and
100-page/1,897-asset compositions, authored through the existing composition owner:
one hero section per page, at most 24 usages per section, real stored snapshot hashes,
current fixture approvals/rights and unchanged source-eligibility validation. Both
previously reached the deliberate first-byte-boundary stop. After correction, both
return `artifact_invalid` with zero asset-validation calls. No invented asset file is
read or accepted. Both before/after captures retain actual exit/stdout/stderr outside
the repository; the prior reports remain untouched.

| Pages | Distinct media | Total package files | File-count gate |
| ---: | ---: | ---: | --- |
| 1 | 1,995 | 2,000 | Accept |
| 1 | 1,996 | 2,001 | Reject before asset reads |
| 2 | 1,994 | 2,000 | Accept |
| 2 | 1,995 | 2,001 | Reject before asset reads |
| 100 | 1,896 | 2,000 | Accept |
| 100 | 1,897 | 2,001 | Reject before asset reads |
| 1 | 0 | 5 | Accept; preparation completes without media reads |
| 1 | 1 from 1,997 usages | 6 | Accept inventory; byte integrity not claimed |

An additional 1,998-usage duplicate-path case still fails the descriptor bound, and
oversized serialized input still fails its independent limit. Acceptance in this table
means the file-count gate only. No thousands of physical files or oversized payloads
were generated to exercise arithmetic.

The new-request regressions compare complete fake-database snapshots and mutation
queries: no job, attempt, requested/start event, execution budget or recovery allocation,
and no work-directory entry, journal, candidate or sealed output. Exact successful
historical replay returns the same release without new eligibility/preparation I/O,
events or attempts. A small real private-filesystem build verifies one deduplicated
asset, three pages, CSS and marker (public count 6), plus two metadata files (total 8),
and seals successfully with the existing artifact hash. The full existing determinism,
producer-failure/heartbeat and safe-CLI suites remain enabled.

Fixture development initially exceeded hero cardinality in the two-page authoring
sample; the valid service sample now uses 80/100 pages without relaxing eligibility.
The deliberate stop uses the existing service exception boundary so its signal is not
wrapped as an unrelated dependency error. An initial sandbox run reached the unchanged
private-journal rename and failed there; the same admission suite passed in the normal
Windows execution context. Those intermediate results remain separate from final PASS.

### Admission correction validation and inventory

Fresh execution: **68 standalone suites, all exit 0; eight M6C suites with 651 assertions**.
The new admission suite passes 58 assertions. The existing M6C suites pass artifact 45,
bounds 29, CLI 186, integration 54, recovery/progress 135, scope 53 and security 91.
Each M6C suite ran individually first, then every available `tests/*Test.php` ran with
`php -n`. The unchanged fake launcher passed 2,131 assertions across 161 Bash scenarios,
47 mount cases and 27 no-ctype port/type cases per mount form across three profiles;
the simulated migration runner passed 848 assertions. Neither suite executed Linux,
containers or a live database. Actual Windows checks include the small sealed package,
one hardlink case and one CGI denial. Native/platform limits remain explicit.

`php -n -l` passed all 239 tracked/new PHP files. Syntax-only checks passed for
`node --check tests/WebsitePlatformM5CBrowser.cjs`,
`C:\Git\bin\bash.exe -n tests/RunM6BMySql.sh` and PowerShell `Parser.ParseFile` on
`tests/RunM6BMySql.ps1` (zero errors). This document's two local links/anchors, fences
and milestone/backup status checks pass. Working/staged diff checks pass; committed
checks are retained in the outside-repository report. Per-suite exits/stdout/stderr
are captured; expected injected-error diagnostics and Git CRLF warnings remain visible.

Runtime: Windows PHP 8.4.24 CLI ZTS Visual C++ 2022 x64, libxml 2.11.9; no php.ini in
default or `-n` profiles. Node v24.22.0, PowerShell 7.6.5 and Git Bash 5.3.15.
These are newly observed results; the preceding 67-suite/593-assertion record belongs
to the prior correction. Neither historical evidence nor intermediate failures was erased.

Correction-only inventory relative to `7787bec`: three files.

| Change | File |
| --- | --- |
| Modified | `private/classes/SiteArtifactBuilder.php` |
| Added | `tests/WebsitePlatformM6CAdmissionTest.php` |
| Modified | `docs/sprint-8.8-m6c-local-implementation.md` |

All 25 migration files retain their raw bytes and baseline Git contents, including
017/019 and the existing pins. Migration 025 remains applied to working staging and
unchanged at SHA-256 `dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
No migration, native-test infrastructure, M6B lifecycle, configuration or permission
change is part of this correction. Native Linux startup/permissions, actual MySQL/
filesystem integration and native crash/race/durability validation remain pending.

### Historical admission-correction handoff: backup checkpoint not yet reported

The following was the handoff at the end of the admission correction. The current
operator report in the next section supersedes its instruction to prepare a backup.

**NOT EXECUTED. This local correction does not authorize server access or a backup.**
Before the next M6C droplet runtime validation or environment change, prepare a fresh
backup of the **deployed staging application**, local uploads, relevant recovery
configuration and an **actual `ubo_staging` SQL export**, including hashes and recovery
notes. Use the data volume without exhausting the boot disk. Preserve private
credentials and distinguish any required manual administrator step from restricted
Codex execution. Do not automatically overwrite or delete existing backups.

After preparing that future backup, pause for **Frank's WinSCP download and verification
of the Windows copies** before continuing the runtime validation/environment change.
This backup/download checkpoint is separate from the pending native build-only gates.
M6B remains formally closed; M6D-M6G remain NOT STARTED; production remains
UNAUTHORIZED / NOT DEPLOYED. No server, backup, live database, migration, deployment,
provider, M6D-M6G or Narrator action occurred in this local admission correction.

## Pre-output execution denials

Correction for PR #128 comment `4305089747`, relative to
`3e6871321820f02ddccab7872ae5f69976b91821`. This is local code/test work only.

### Reproduced failure and settlement boundary

Before the change, the actual service with fake SQL and private temporary storage
reproduced requester revocation, deletion, role loss, source approval loss, builder
unavailability and builder mismatch after a committed claim. Each left the service
attempt running with one execution, one attempt, one start event, no outcome event,
and no artifact I/O, journal, candidate or seal. Controlled expiry followed by actual
recovery service calls returned `blocked` / `reconciliation_required`.

The isolated actual CLI source reproduced exit 1 with fixed `unauthorized` or
`conflict` diagnostics for revocation/mismatch, leaving the attempt running. Its
builder-unavailable catch instead recorded an abandoned attempt and required
reconciliation, because journal verification could not establish absence. The
no-work control returned exit 0 and `no_work_claimed`. These are observed local
results, not native MySQL/Linux claims; before/after outputs are retained outside Git.

Construction now has an explicit nontransferable, single-use service contract.
Only `claimBuild` after an acknowledged fresh allocation places authority in the
service's private process memory. Its key binds the exact job/attempt/kind/token;
its value binds the trusted worker and environment, process ID, runtime object and
database connection. The lease DTO exposes none of this authority. A new process,
copied lease, fork with a different PID, runtime/connection substitution, or a
previous invocation cannot reconstruct it from a missing journal. No schema field
is reused as an entry flag. No migration or filesystem marker is added.

`executeBuild` first authenticates and resolves committed database history under
owner locks. It then consumes the authority before builder identification or any
execution callback. Consumption has no callback/yield point. Reentrant calls cannot
reach either the denial branch or construction. The sole consuming invocation can
either settle a gate denial in a short locked transaction or enter the artifact
dependency after that transaction commits. Settlement rechecks current trusted
worker, exact token, current attempt ownership, lease/deadline, requester, source,
input, builder and policy. Ownership transitions use the existing lifecycle locks;
the first execution pulse rechecks ownership before artifact work after lock release.
All filesystem/render work stays outside SQL transactions.

This is stronger than a local “not written yet” flag: **all service construction
requires the unexported authority**, and consumption prevents every later invocation
of that lease from entering construction or claiming non-entry. The protected
one-shot CLI already claims and executes in the same process. Persisting a lease
for construction in another process is deliberately unsupported; such a process
can only resolve history or use the existing evidence-based recovery path. Direct
artifact dependencies remain trusted internal wiring, not public entry points.

The pre-output result uses existing post-claim `failed` semantics. It preserves the
attempt, start event, requester provenance, timestamps and all execution/attempt
counters. One system-authored `site_build_failed` event commits atomically with the
job and attempt. Existing categories distinguish requester authorization, source
eligibility, input mismatch and builder/policy configuration. These gates are
nontransient and schedule no retry. Existing transient storage/network retry rules
after independently verified output remain unchanged.

The result is returned as `completed` only after commit. CLI prints the fixed
`Build-only outcome: failed.` and exits 1, distinct from `no_work_claimed` / exit 0.
There is no broad CLI exception-to-safe-failure conversion. The unchanged result
fingerprint supports repeat execution/completion history without new events,
heartbeats, artifact inspection or eligibility checks. Committed success returns
the recorded release before new gates and cannot be rewritten by a later denial.

Audit failure rolls the whole settlement back. Consumed authority is never restored,
including after rollback or a lost commit acknowledgement; unresolved work remains
conservative. An uncertain commit throws a safe database failure, and only a later
authoritative read can return an actually committed result. An unacknowledged claim
never issues construction authority. Neither case triggers blind compensation.

Once the artifact dependency is entered, failures use existing independent producer
verification, even if the journal is missing. Pending/corrupt journal, interrupted
write/flush and uncertain seal still remain unknown. Private partial output stays
retained; recovery never fills it, rerenders, deletes unknown files, or revives an
expired producer. Producer evidence still precedes fallible execution asset reads.
The 25-second renewal cadence and 120/900/300-second lease/execution/recovery bounds,
budgets, 2,000-file admission, byte limits, layout, hashes and public allowlists remain.

### Execution correction validation and inventory

Fresh run: **69 standalone suites, all exit 0; nine M6C suites, 888 assertions**.
The new execution suite passes 216 assertions; CLI passes 207, recovery/progress 135,
integration 54, admission 58, artifact 45, bounds 29, scope 53 and security 91.
All nine M6C suites and four affected M6B suites ran individually first, then every
available `tests/*Test.php` ran with `php -n`. The unchanged launcher passed 2,131
assertions across 161 isolated Bash scenarios, 47 mount cases and 27 no-ctype port/type
cases per mount form across three PHP profiles. The simulated migration runner passed
848 assertions. Neither executed Linux, containers or a live database.

`php -n -l` passed all 241 tracked/new PHP files. JavaScript and Bash syntax checks
passed (exit 0) for `tests/WebsitePlatformM5CBrowser.cjs` and `tests/RunM6BMySql.sh`;
PowerShell `Parser.ParseFile` found zero errors in `tests/RunM6BMySql.ps1`.
All three local anchor links, both fences and current/historical milestone/backup
status checks pass. Working and staged diff checks pass; committed checks are retained
outside Git. Per-suite commands, exits, stdout/stderr and durations are retained there.
Expected injected PDO diagnostics and Git CRLF warnings remain visible. The first new
test run exposed an incorrect audit-column assertion with PHP warnings; the assertion
was corrected to require `actor_user_id` before the successful focused/full runs.

Observed runtime: Windows PHP 8.4.24 CLI ZTS Visual C++ 2022 x64, libxml 2.11.9,
no php.ini in default or `-n` profiles; Node v24.22.0, PowerShell 7.6.5, Git Bash
5.3.15. One Windows hardlink case and one actual CGI denial executed; the symlink case
was unavailable. Earlier 68-suite/651-assertion results remain historical, not reused.

Focused cases include
revoked/missing/deleted/role-changed requesters, lost source eligibility, unavailable/
mismatched/malformed builders, input/policy failure, wrong token/owner/worker/
environment, lease/deadline loss, identity-time ownership changes, database errors,
audit rollback, lost claim/settlement acknowledgements and committed-success replay.
Controlled callbacks reenter during identity, artifact entry and journaled progress;
substituted private authority tests model process/runtime/connection loss. These are
simulated interleavings and process-identity faults, not native multi-process proof.
The isolated CLI child uses the actual entry and service with fake SQL and private
temporary files; no protected host configuration, operational queue or live database.

Correction-only inventory: seven files, two production changes.

| Change | File | Purpose |
| --- | --- | --- |
| Modified | `private/classes/SiteBuildService.php` | Issue/consume construction authority; atomically settle known non-entry; resolve history first. |
| Modified | `scripts/site-build-worker.php` | Report service-completed claims and return nonzero for failed outcomes. |
| Added | `tests/WebsitePlatformM6CExecutionTest.php` | Focused lifecycle, ownership, interleaving, rollback and replay regressions. |
| Modified | `tests/WebsitePlatformM6CCliTest.php` | Assert actual isolated CLI failed-claim/no-work reporting. |
| Added | `tests/support/WebsitePlatformM6CExecutionChild.php` | Actual CLI/service fixture with fake SQL and private temporary storage. |
| Modified | `tests/support/WebsitePlatformM6CFixture.php` | Test-only builder/worker hooks; production wiring has no new selector. |
| Modified | `docs/sprint-8.8-m6c-local-implementation.md` | This evidence, boundary and current handoff. |

All 25 migrations and integrity pins remain unchanged. Migration 025 remains applied
to working staging, canonical SHA-256
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
No applied SQL, launcher, wrapper, TLS configuration, route, scheduler, provider or
publication behavior changes. Native Linux startup/permissions, actual MySQL locks/
FKs/commit behavior, multi-process races and filesystem crash/durability validation
remain pending under separately authorized build-only validation. The 77-case M6
acceptance matrix is not claimed passed.

### Current next-run handoff: completed backup and download checkpoint

**BACKUP/DOWNLOAD CHECKPOINT — COMPLETE. SERVER BACKUP — VERIFIED.
WINDOWS DOWNLOAD — VERIFIED from operator-supplied checksum results.
RESTORE TEST — NOT EXECUTED.**

The operator reports that all seven hashes, including `SHA256SUMS`, matched. A separate
private confirmation receipt was retained on the server. Original backup files and
manifest and the Windows copy were not deleted. This local correction did not retrieve
or independently verify them; no path, hash value, timestamp or receipt content is
inferred. The historical “not executed in this local task” passage remains above.

Later authorized droplet validation must reference this completed checkpoint and retain
the private backups. A local branch correction alone is not a reason to repeat the
backup. No recreation, download, inspection, restore or deletion is authorized here.

M6B remains formally closed. M6C remains IMPLEMENTED LOCALLY / REVIEW REQUIRED with
native build-only validation pending. M6D–M6G remain NOT STARTED; production remains
UNAUTHORIZED / NOT DEPLOYED. The last operator-reported deployed application remains
`f466fc1cdbfb53325a1db11445736bdc5ea7c6f4`. This task performs zero server, backup,
live SQL, migration, deployment, Docker, provider, M6D–M6G or Narrator operations.

## CLI execution-child profile and diagnostics

This correction starts at `d4fb3d696fafabc93923915c4b294321d630d21b` on the existing
PR #128 branch. It changes only the tests/helpers and this implementation record.
Application, service, runtime, worker, renderer, store, shared M6C fixture and fake
database implementations are unchanged. This is an operator-observed test failure,
not a new reviewer finding or a resolved review conversation.

### Operator-reported Linux result at d4fb3d6

| Suite | Result | Assertions |
| --- | --- | --- |
| Admission | PASS | 58 |
| Artifact | PASS | 45 |
| Bounds | PASS | 29 |
| CLI | FAILED, exit 255 | Final total not emitted |
| Execution | PASS | 216 |
| Integration | PASS | 54 |
| Recovery | PASS | 135 |
| Scope | PASS | 53 |
| Security | PASS | 92 |

The eight passing suites reported **682 assertions**; all **241 tracked PHP files**
passed lint. Real Linux filesystem operations and both hardlink/symlink rejection
cases ran. SQL, clocks and interleavings remained simulated. The CLI stopped at
“Execution child reports trace.” Inspection found the committed `-n` execution-child
profile lacked PDO/PDOStatement needed by the fixture. Its raw child capture had
already been deleted: the precise original fatal diagnostic is **not retained** and
is not inferred here. No Windows CLI assertion count is substituted for that run.

The operator supplied the evidence directory
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-linux-20261018T-readiness`.
No full report filename or checksum was supplied. This desktop task does not access
that directory or independently verify the reported Linux evidence. The earlier
Windows 69-suite/888-assertion results remain historical results at their tested SHA.

### Dependency path, reproduction and selected profile

The execution child loads `WebsitePlatformM6CFixture`, whose fake SQL path is
M6BDatabase → M5ADatabase → M3ServiceDatabase. Their database and statement classes
extend PDO/PDOStatement, use PDO constants and PDOException, and override connection
operations with in-memory behavior. Empty PDO constructors and reflection-injected
fake connections need **PDO types, not a PDO MySQL driver or a real connection**.
Fixture construction also needs reflection, date, SPL filesystem iterators, JSON,
DOM/libxml, hashing/randomness, ctype/filter, image metadata and filesystem/fsync
functions. The new preflight checks these before requiring any fixture class.

On the available Windows PHP 8.4.24 build, PDO/PDOStatement are built in under `-n`
and PDO reports no available drivers. The old `-n` revoked execution child succeeds
in reporting its expected failed-claim trace (exit 1); the operator's missing-PDO
Linux behavior cannot be reproduced naturally here. A separately captured old-child
run with process-only `disable_classes=PDO,PDOStatement` exits 255 without a trace.
This is a controlled capability failure, not the missing original Linux diagnostic.
With the correction, controlled disabled PDO, DOM and fsync cases fail explicitly
with prerequisite exit 78 before fixture construction and without a fabricated trace.

The four normal execution scenarios use the same `PHP_BINARY`, without unconditional
`-n`. A loaded parent INI is passed with `-c`; otherwise the selected executable may
load its installed default configuration. Relevant effective parent `-d` directives
(`extension_dir`, disabled functions/classes and temporary directory) are carried
explicitly. Arbitrary command-line settings are not assumed to propagate. Memory is
capped at 256 MiB, preserving a smaller positive parent limit; execution time is 10
seconds, with a separate parent wall-clock deadline. Auto-prepend/append are disabled,
exception arguments are hidden, and errors go to captured stderr rather than logs.

A capability-only child runs first. If a required PDO/DOM/ctype/filter module is
missing but already loaded by the parent (for example with `-d extension`), the helper
can add its existing standard-named module from the parent's `extension_dir` to this
child only and recheck. It does not install modules, require a database driver, change
INI files, search PATH for another binary, or silently skip normal execution tests.
Nonstandard module filenames are not guessed; a still-incapable profile reports a
retained prerequisite failure and needs an existing capable invocation profile.
The standard shared-module fallback is not exercised on this built-in Windows build.

The no-INI argument, help, web and runtime-denial tests retain their purpose. Valid
`--once`/`--recover` remain confined to isolated children. Operational host calls are
limited to help/invalid arguments and CGI denial. The execution fixture still uses
actual service/entry code with fake SQL and private files, without protected host
configuration, a live database, operational claims or non-fixture artifact output.

### Retained diagnostics and cleanup

The small process helper keeps file captures to avoid blocking Windows pipes. The
existing 64 KiB aggregate output threshold and 10-second default wall timeout remain;
termination has bounded soft/forced waits and records the observed exit or signal
and whether the child is still running. Polling can observe output beyond the
threshold before termination. Evidence reads only a 32 KiB prefix per stream and
retains at most 4 KiB of sanitized diagnostics per stream, plus byte counts/limits.

Trace parsing and execution assertions now run while captures still exist. Missing
or malformed traces, startup errors, timeouts, overflow and failed validation retain
case, profile label, actual exit/termination state and sanitized stdout/stderr in an
owned temporary `ubo-m6c-cli-failure-<random>/failure.json` outside Git, before raw
captures are removed. The thrown summary identifies that evidence. Known engine
capability identities and fixed safe diagnostics are allowlisted; arbitrary messages,
paths, payloads, arguments, environment and unrecognized identifiers are redacted.
No assumed success trace or zero-effect counters replace a missing child trace.

Directories/files request 0700/0600. Windows uses the user's inherited temporary
directory ACLs; POSIX mode assertions run only on POSIX. Successful captures are
removed. Intentional negative regressions verify and consume only their own retained
evidence, leaving unrelated failures intact. A failed evidence write leaves the
original failure and sanitized summary intact and removes partial evidence. Tests
use an isolated process-only temporary root so concurrent suites cannot affect
cleanup observations. No installed PHP configuration is edited.

### Fresh desktop validation and exact inventory

The affected CLI suite passes **207 assertions**, including all four real execution
traces (revoked, builder-unavailable, builder-mismatch, no-work), 32 isolated valid
argument denials and **one actual CGI invocation**. The new process suite passes
**85 assertions**: real startup fatal, missing/malformed trace, nonzero exit despite a
parseable trace, timeout, overflow, payload/identifier redaction, evidence-write
failure, ownership-scoped cleanup, controlled missing PDO/DOM/fsync/JSON encoding/
stderr writing, and parent `-c` and relevant `-d` forwarding. Disabled diagnostic
functions still produce prerequisite exit 78 using a language-level output fallback.
All ten M6C suites pass **973 assertions** in the fresh standalone validation with
final affected-suite rechecks: 58/45/29/85/207/216/54/135/53/91 for admission, artifact,
bounds, CLI process, CLI, execution, integration, recovery, scope and security.

All 245 PHP files pass `php -n -l`; final edited helpers pass lint again. Node syntax,
Bash syntax and the PowerShell parser pass for the existing browser/launcher scripts.
The Bash sandbox launch was denied by Windows shared-memory permissions; the same
syntax-only command passes in normal Windows context. Four local document anchors,
balanced fences, seven-file inventory and working/staged diff checks pass.
The full run completed **70 standalone suites, all exit 0**. The unchanged synthetic
launcher passed 2,131 assertions across 161 Bash scenarios, 47 mount cases and 27
no-ctype cases per mount form across three PHP profiles; the simulated migration
runner passed 848 assertions. No native harness, container or live database ran.
Expected injected PDO/service failure diagnostics remain visible in retained stderr.

Observed runtime: Windows PHP 8.4.24 CLI ZTS Visual C++ 2022 x64, libxml 2.11.9,
no loaded INI in default or `-n` profiles; Node v24.22.0, PowerShell 7.6.5 and Git Bash
5.3.15. One hardlink rejection case ran; symlink creation was unavailable. These are
local filesystem results, not native Linux permissions or real MySQL integration.
Commands, versions, per-suite exits/stdout/stderr/durations, reproduction captures,
lint, migration and documentation checks are retained outside Git in this desktop
task's `m6c-cli-profile` evidence directory. The full run uses `php -n tests/*Test.php`
one standalone file at a time; only execution children select the capable profile.
The focused development runs also caught a shared-temp cleanup assertion race and
an overly short 200 ms startup/timeout expectation; the tests now isolate their temp
root and use the existing one-second controlled timeout budget. Failed development
records are retained separately from successful focused/full results.

Correction-only inventory: **seven files; zero production changes**.

| Change | File | Purpose |
| --- | --- | --- |
| Modified | `tests/WebsitePlatformM6CCliTest.php` | Select capable execution profile; validate real traces before capture cleanup. |
| Modified | `tests/support/WebsitePlatformM6CExecutionChild.php` | Capability probe and prerequisite failure before fixture loading. |
| Added | `tests/support/WebsitePlatformM6CExecutionProfile.php` | Selected-binary, bounded process-only fixture profile and dependency checks. |
| Added | `tests/support/WebsitePlatformM6CCliProcess.php` | Bounded file captures, sanitized failure retention and owned cleanup. |
| Added | `tests/support/WebsitePlatformM6CCliProcessChild.php` | Isolated deterministic process faults and profile inspection. |
| Added | `tests/WebsitePlatformM6CCliProcessTest.php` | Capability, diagnostic, bounds, cleanup and profile regressions. |
| Modified | `docs/sprint-8.8-m6c-local-implementation.md` | Operator evidence, correction, actual local results and next deliverable. |

All 25 migrations and their integrity pins remain byte-identical to the prior head.
Migration 025 remains applied to working staging; its canonical SHA-256 remains
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
The backup/download checkpoint remains **operator-reported COMPLETE** with both
copies and the separate receipt retained. Restore remains **NOT EXECUTED**. This
task neither accesses backups nor independently verifies their contents.

### Next Linux check and separate native harness

A later authorized Linux rerun must run the corrected **CLI suite first**, followed
by its new CLI process regressions. Preserve actual PHP/profile, exits, output,
optional CGI/link coverage and assertion totals; Windows and Linux totals need not
match. The eight passing Linux suites remain recorded at `d4fb3d6`. Further suites
should follow changes to shared fixtures, rather than automatically rerunning them
because a documentation commit changed HEAD. This correction changes no shared
M6C/fake-database fixture. No server rerun is performed or authorized here.

The operator inspected the reviewed checkout and found **no executable M6C native
integration harness**, an existing M6B harness with synthetic build dependencies,
and absent host protected-worker configuration. These are missing deliverables,
not a failed native application run. `RunM6BMySql.sh` is not a substitute M6C test;
no test is improvised against `ubo_staging` and no worker configuration is installed.

The next substantive implementation is a separately reviewed M6C native harness:
disposable test database/private artifacts, isolated protected-worker configuration
and exact checkout, non-root worker under actual ownership rules, **same-process
ordinary claim/construction**, bounded cross-process recovery/fault cases, and
ownership-scoped cleanup with retained diagnostics. This correction implements no
native harness or infrastructure expansion and opens no planning-only PR. Earlier
Docker/WSL proposals are not installation authorization.

M6B remains formally closed; M6C remains IMPLEMENTED LOCALLY / REVIEW REQUIRED with
native validation pending. M6D–M6G and Narrator remain untouched. PR #128 stays open,
non-draft and unmerged with auto-merge disabled. No review conversations are resolved
by this correction. Zero server, live database, SQL/migration, backup/restore,
deployment, installation, container, provider or production operations occur here.

## Execution-fixture prerequisite and initialization review corrections

This test/helper/documentation-only correction starts at
`8f04073325b3e01a1851aa0faaa4493fec3d059f` on the existing PR #128 branch and addresses
the two completed-review findings:

- [4305441338 — Normalize a missing disable_classes directive](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4305441338).
- [4305441342 — Check base64_decode before constructing the fixture](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4305441342).

The reviewer's PHP 8.5.7-dev reproduction is reviewer-supplied evidence. It is not a
PHP version observed by this desktop task or a claim of full application support.
The earlier Windows 70-suite/973-M6C-assertion results above describe the prior
correction; they are not reused as fresh validation for these findings.

### Operator-reported review-gate stop

The latest droplet task **STOPPED at the completed-review gate**. It performed no
fetch or checkout, and neither requested suite ran. It produced no new tested SHA,
assertion total, child-profile result or lint result. No runtime resources were
created. Deployed invariants were not independently rechecked after that stop.
This is a blocked validation attempt, not another executed CLI test failure.

Operator-reported evidence file:
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6c-cli-profile-20261018T235522Z-blocked/M6C-LINUX-CLI-PROFILE-VERIFICATION.txt`.
Reported SHA-256:
`02c376cea15cab66b7958ffe1e4112f0b15af7eead2952332ba9d4991cbb7e77`.
The desktop task does not retrieve this file or independently verify its checksum.

The earlier Linux run at `d4fb3d696fafabc93923915c4b294321d630d21b` remains separate:
eight passing suites / **682 assertions**, the CLI failure at exit 255 without a
final assertion total, and 241 passing PHP lint results. Its original child fatal
was not retained and is not reconstructed. The blocked run supplies no replacement
execution evidence for any of those results.

### Optional directive and class-capability coverage

The prior-head source snapshot reproduces the unavailable-directive failure under
strict types on local PHP 8.4.24: a source-local namespace control returns `false`
from `ini_get('disable_classes')`, and the original `explode` path exits 255 with a
TypeError. This is a controlled false-return reproduction, not native PHP 8.5
execution. An initial external reproduction harness accidentally omitted the eval
source's strict declaration, so that child exited 0; the resulting failed
assertion/evidence remains retained. The corrected strict-types harness reproduced
the failure and exited 0 after checking that actual child result.

The small pure `m6ExecutionDisabledClasses(string|false)` boundary maps `false` to an
empty list, trims and lowercases names, and removes empty entries. The actual
preflight continues to check `class_exists` for every required class. An unavailable
directive therefore does not imply that the class exists. The bounded nearby audit
also guards unavailable `memory_limit` before matching it and unavailable/empty
`extension_dir` before trimming it for the existing-module fallback. No production
configuration or general PHP compatibility framework changes.

Class-negative regressions first establish a capable baseline using the selected
child profile, then inspect whether `disable_classes` is available and whether it
actually removed known internal PDO/DOM methods. Only a supported,
demonstrably effective directive is credited with real process-level disabled-class
coverage. Separately, an isolated strict source copy exercises false optional
directives and controlled missing PDO/DOM class decisions while retaining actual
`class_exists` behavior for other classes. This coverage remains available when PHP
ignores or removes the directive. These controls exist only in the deterministic
test fault child; no production PDO replacements or worker/configuration bypasses
are introduced.

On this Windows build both actual PDO/DOM directive groups were effective and ran,
alongside both controlled false-directive/missing-class groups. A controlled
false-directive capability probe with no denied classes also reported the real
capable class state without constructing the fixture.

The local executable/package/location checks found PHP 8.4.24 and no PHP 8.5
installation in the checked PATH, WinGet PHP package records or common PHP locations.
No PHP version was installed or downloaded. The reviewer's PHP 8.5.7-dev result stays
reviewer evidence; the controlled local case does not become a native version test.

### Fixture prerequisites and partial initialization cleanup

With the prior-head source snapshots and actual process-only
`-d disable_functions=base64_decode`, the capability probe exits 0 and incorrectly
reports an empty missing list. The normal constructor child exits 255 on the missing
function, emits no execution trace and leaves one actual tracked fixture root.
The reproduction confines that root to its own disposable temporary directory; the
existing fixture cleanup removes that exact owned root and leaves that directory
empty. Actual exits and sanitized failure evidence are retained before raw-capture
cleanup. The original sanitizer redacts the unrecognized function identity; the
corrected bounded allowlist separately preserves the exact `base64_decode` identity
from the captured pre-fix diagnostic. No original Linux fatal is inferred from this
local reproduction.

`base64_decode` now belongs to the actual preflight and diagnostic allowlist. A bounded
comparison of the real fixture constructor/request path also found nine concrete
function dependencies: `preg_match_all`, `preg_replace`, `bin2hex`, `hash_equals`,
`clearstatcache`, `lstat`, `is_link`, `fstat` and `feof`. They are checked before loading
fixture classes and are accepted as exact safe diagnostic identities. The capable
profile still carries the parent's disabled-function restriction; it does not
re-enable a function, replace the fixture image or install a module.

The execution child now registers cleanup before construction or request preparation.
A nullable fixture reference and a separate readiness flag prevent execution-trace
generation until setup has returned and completed. The shutdown callback always
calls the existing static tracked-root cleanup in `finally`, including when the
constructor never returns an instance or trace generation throws. It neither searches
for roots nor follows unknown paths. The shared fixture and its deletion mechanism
remain unchanged. A controlled constructor fault is injected immediately after the
fixture tracks its owned root, and a separate trace fault tests the `finally` path;
both controls are confined to the source-copy test child.

After correction, the actual selected-parent `base64_decode` restriction reaches the
nested capability probe, which exits 78 and lists the missing function. A normal
execution case also exits 78, retains the named prerequisite diagnostic and emits
no execution trace. Separate test-only observations confirm all five fixture,
fake-database, database, build-service and runtime classes are unloaded, with no
connection or tracked roots; the owned temporary directory is unchanged. No fixture,
request, claim, candidate or artifact is created. These observations are not
substituted execution-trace counters. All 13 actual disabled-function cases pass,
covering the ten added functions and existing fsync/JSON-encoding/stderr-writing
requirements.

The controlled constructor fault exits 255 before returning an instance or setting
trace readiness. The separate trace-generation fault exits 255 after both are set.
In both cases the observer sees the real fake-database connection and one existing
child-owned tracked root before cleanup; the parent verifies that exact root is
absent after exit. Both preserve the controlled undefined-function diagnostic and
actual exit without an execution trace. Retained sanitized failure evidence is
checked before each intentional negative case consumes only its own evidence.

The callback cannot clean after a forcibly killed process that executes no shutdown
handlers; that is not claimed by these controlled PHP-failure cases. Private file
modes, bounded output/time limits and sanitized evidence retention before raw-capture
cleanup remain in force. No zero-effect execution trace substitutes for incomplete
initialization or a missing trace.

### Fresh local validation and correction-only inventory

The corrected CLI and process suites ran individually with exit 0: **207 CLI
assertions** and **212 process assertions**. The CLI retains revoked,
builder-unavailable, builder-mismatch and no-work execution cases, deliberate no-INI
help/argument/runtime denials and confinement of valid work arguments to isolated
children. The process suite checks the normalization inputs, actual and controlled
class mechanisms, all 13 disabled-function cases, selected-parent propagation,
partial construction and trace-failure cleanup, and the existing diagnostic bounds.
The final focused process run includes the selected-child baseline correction.

The fresh standalone run passes all **ten M6C suites / 1,100 assertions**: admission
58, artifact 45, bounds 29, CLI process 212, CLI 207, execution 216, integration 54,
recovery 135, scope 53 and security 91. One actual CGI denial and one Windows hardlink
case execute; symlink creation is unavailable, so one of two local link cases runs.
These are actual local processes and filesystem operations with fake SQL. Controlled
false-directive/class decisions and injected constructor/trace faults are separately
identified; no native PHP 8.5 or Linux rerun is claimed.

All **245 PHP files** pass `php -n -l`. Three script checks pass: Node `--check` for
`tests/WebsitePlatformM5CBrowser.cjs`, Bash `-n` for `tests/RunM6BMySql.sh`, and
PowerShell `Parser.ParseFile` for `tests/RunM6BMySql.ps1`. These are syntax checks,
not a native harness or browser run. Observed versions are Windows PHP 8.4.24 CLI
ZTS Visual C++ 2022 x64, Node v24.22.0, PowerShell 7.6.5, Git Bash 5.3.15 and Git
2.56.0.windows.2. The available standalone set runs one `php -n tests/*Test.php`
file per process; only its execution children select the capable profile.
Commands, versions, exits, per-suite stdout/stderr/durations, before/after evidence
and integrity checks are retained outside Git in `m6c-bootstrap-review`.

The full available set completes **70 standalone suites, all exit 0; zero failures**,
with 1,169.402 seconds of summed suite duration. Eleven stderr captures retain
expected simulated-fault diagnostics.
The unchanged synthetic launcher passes 2,131 assertions across 161 isolated Bash
scenarios, 47 mount-inspection cases and 27 no-ctype port/type cases per mount form,
using three PHP profiles and fake commands only. Its final run takes 1,036.1 seconds;
it executes no Linux/container/MySQL harness. Documentation checks enumerate 13
Markdown links, verify all five internal anchors and two balanced fence markers,
and confirm the six-file inventory and current/historical status requirements.
At document preparation, working-diff and staged five-file PHP diff checks pass.
Final six-file staged and committed diff results will be recorded in the external
`SUMMARY.txt` with the commit evidence.

Correction-only inventory: **six modified files; zero production changes**.

| File | Purpose |
| --- | --- |
| `tests/support/WebsitePlatformM6CExecutionProfile.php` | Normalize optional directives; check concrete fixture dependencies before loading. |
| `tests/support/WebsitePlatformM6CExecutionChild.php` | Register owned-root cleanup before construction; gate traces on completed setup. |
| `tests/support/WebsitePlatformM6CCliProcess.php` | Allowlist the exact added prerequisite identities in bounded diagnostics. |
| `tests/support/WebsitePlatformM6CCliProcessChild.php` | Isolated directive/capability observations and controlled initialization/trace faults. |
| `tests/WebsitePlatformM6CCliProcessTest.php` | Version-aware class checks, function propagation and partial-initialization cleanup regressions. |
| `docs/sprint-8.8-m6c-local-implementation.md` | Both findings, preserved operator evidence, correction and fresh validation. |

The main CLI suite, shared M6C fixture and fake-database implementations are unchanged.

### Preserved state and later validation

The exact six-file diff contains no production, worker/runtime, build-service,
renderer, store, resource-limit, shared-fixture or main-CLI-test change. All 26 tracked
SQL files retain their raw hashes from this task's starting snapshot. The canonical
contents of all 25 migrations match the M6C baseline
`7ed48fbaa4858b1b5299dc7cbea98f96e98f4e45` using the existing line-ending normalization;
no migration or integrity pin was edited. Migration 025's canonical hash matches the
value below. No applied SQL or deployed invariant is changed or remotely rechecked.

Migration 025 remains operator-reported applied to working staging, with canonical
SHA-256 `dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
The backup/download checkpoint remains **operator-reported COMPLETE**: both backup
copies and the separate confirmation receipt remain retained. Restore remains
**NOT EXECUTED**. This task does not retrieve, recreate, inspect, restore or delete
backups, and does not independently recheck deployed invariants.

A later authorized Linux check must run the corrected **CLI suite first**, then the
CLI process regressions, retaining its own actual profile, exit, diagnostic and
assertion evidence. That rerun is not performed here. The separately reviewed M6C
native harness remains a missing deliverable; it requires a disposable database and
private artifacts, protected configuration and exact checkout, a non-root worker,
same-process ordinary claim/construction, bounded cross-process recovery/fault cases,
and owned cleanup with retained diagnostics. This correction implements no native
harness, runs no M6B harness as a substitute, and provisions no worker environment.

At commit preparation, both original findings are verified locally. Post-push
responses/resolutions and the observed exact-new-head review state are recorded in
PR #128 and the external `SUMMARY.txt`; this pre-commit record does not claim an
unobserved clean review. Only the two named conversations are eligible for resolution.

M6B remains formally closed; M6C remains IMPLEMENTED LOCALLY / REVIEW REQUIRED with
native validation pending. M6D–M6G and Narrator remain untouched. PR #128 must remain
open, non-draft and unmerged with auto-merge disabled. This correction authorizes zero
server, live SQL/database, migration, backup/restore, deployment, installation,
container, provider or Narrator actions.

## Execution-fixture cleanup prerequisites

This test/helper/documentation-only correction starts at
`e067f05bd16ca41a46a1dc174d6471f4c826f5b8` on the existing PR #128 branch and addresses
[4309456808 — Preflight basename before constructing the fixture](https://github.com/fvd8383/ultimate-back-office/pull/128#discussion_r4309456808).
The reviewer reports that a selected child profile disabling `basename` passes
preflight, constructs the fixture and emits an execution trace, then fails during
owned-root cleanup with exit 255 and leaves its root behind. Presence of a trace,
process exit and cleanup outcome are separate observations.

The earlier local results remain attached to their own corrections and SHAs. In
particular, the prior 70-suite/1,100-M6C-assertion record is historical, not fresh
validation for this finding. The Linux run at `d4fb3d6` still records eight passing
suites / 682 assertions and the separate CLI exit-255 failure without its final
assertion count or retained original fatal diagnostic. The subsequent
[review-gate stop](#operator-reported-review-gate-stop) performed no fetch, checkout
or tests and produced no new tested SHA, assertion, child-profile or lint result;
it created no runtime resources and did not independently recheck deployed
invariants. Its operator-supplied report path/checksum remain recorded above and
are not retrieved or independently verified here. This basename finding is not
attributed to a droplet execution.

### Basename reproduction and corrected admission

The actual prior-head isolated child reproduces the finding on local Windows PHP
8.4.24 under process-only `-d disable_functions=basename`. Its capability-only probe
exits 0 and incorrectly reports an empty missing list. The normal `no-work` child
emits a real `M6C_EXECUTION_TRACE`, then exits 255 with an undefined-`basename` fatal.
One owned fixture root remains, containing `work`, `sealed`, `uploads` and
`uploads/approved.png`: five observed residual paths including the root. Trace
presence does not make that process or cleanup successful.

The reproduction confines all child state to a private disposable parent directory.
Actual exit, bounded stdout/stderr, trace, observed initialization state and residual
paths are captured in external `m6c-cleanup-prerequisites/basename-before.json`
before cleanup. The capable parent uses the established fixture cleanup for that
exact observed owned root, verifies the parent is empty and removes the empty
parent. No other test root or retained failure evidence is swept. This is local
Windows process/filesystem evidence, not a native Linux reproduction.

With the correction, the actual basename-disabled capability probe exits 78 and
reports `missing: [basename]`. The normal execution child also exits 78 with the
named prerequisite diagnostic and no execution trace. Its separate test observation
records all five fixture/database/service/runtime classes unloaded, no connection
or tracked roots, and false fixture-returned/trace-ready states. No fixture,
request, claim, candidate or artifact is created. There are zero residual paths;
the private parent is verified empty and removed. These actual observations and
cleanup confirmation are retained with `basename-after.json`; no zero-effect
execution trace is substituted for missing initialization.

The selected-parent/profile forwarding regressions pass for both `basename` and
`base64_decode`: their actual nested capability and normal-execution children retain
the restriction and exit 78 before fixture effects. Normal capable-profile CLI
execution still passes for revoked, builder-unavailable, builder-mismatch and
no-work. Deliberate no-INI help, invalid-argument and isolated runtime-denial cases
remain; valid work arguments never reach operational host configuration or queues.

### Bounded initialization, trace and cleanup dependency inventory

The audit follows initialization, shutdown registration, trace and tracked-root
cleanup in the existing helpers/fixture, including concrete immediate initialization
callees. It adds **33 function checks**: 12 direct lifecycle/cleanup functions, 11
bootstrap/reporting functions and ten immediate initialization dependencies. Each
added name is also an exact allowed diagnostic identity. This bounded audit does
not enumerate all recursive validators, fake-SQL behavior or transitive production
dependencies; early cleanup still handles other constructor failures.

| Path | Concrete dependencies | Admission and coverage boundary |
| --- | --- | --- |
| Optional-directive normalization and class inspection | `ini_get`, `explode`, `trim`, `strtolower`, `array_map`, `array_filter`, `array_values`, `class_exists`, `in_array`, `defined` | Newly checked before normalization or class inspection can call them. The existing false/empty/mixed-case directive normalization and real class-existence checks remain. |
| Capability metadata | `php_ini_loaded_file`, `ini_get` | Checked before fixture loading; metadata fields use `null` when the corresponding accessor is unavailable. |
| Direct fixture initialization/composition | `dirname`, `sys_get_temp_dir`, `array_replace`, `strlen`, `str_repeat`, `ucfirst`; existing `bin2hex`, `random_bytes`, `mkdir`, `base64_decode`, `file_put_contents`, `hash`, `realpath` | Concrete omissions are added; existing checks remain. Reflection construction/injection uses the already checked `ReflectionClass` and `ReflectionProperty`. |
| Immediate initialization callees | `rtrim`, `str_starts_with`, `extension_loaded`, `is_array`, `ksort`, `array_key_exists`, `array_diff`, `array_keys`, `is_string`, `min` | Added for concrete calls reached through store construction, canonical JSON, draft composition replacement and runtime identity/contract/CSS reads. This does not claim an exhaustive audit of those production classes. |
| Early shutdown registration | `register_shutdown_function` | Newly checked before fixture classes load; registration still precedes construction and request preparation. |
| Trace projection and serialization | `array_intersect_key`, `array_flip`, `substr`, `strlen`, `count`; `array_map`, `array_values` and event-filter `array_filter`; existing `json_encode`, `fwrite` | All are checked before side effects. `RecursiveDirectoryIterator`, `RecursiveIteratorIterator` and `FilesystemIterator` remain checked classes; trace traversal uses their constructors, iterator protocol and `getPathname`. |
| Root ownership/path admission | `basename`, `dirname`, `sys_get_temp_dir`, `realpath`, `preg_match` | The first three are added; existing realpath/regular-expression checks remain. Cleanup still requires the tracked random root under the exact current temporary parent. |
| Owned traversal and mutation | `DirectoryIterator`, its constructor, iterator protocol, `isDot`, `getPathname`, `isLink`, `isDir`; `chmod`, `unlink`, `rmdir` | Existing class/function checks remain. Methods are covered through the SPL class and actual cleanup paths; they are not independent `disable_functions` names. Link handling, ownership checks and deletion scope are unchanged. |
| Actual entry source loading | `dirname`, `file_get_contents`, `preg_replace` | `dirname` is added; the existing bounded source-boundary checks and other prerequisites remain. |

The existing PDO/PDOStatement/PDOException, date, DOM/libxml and JSON class/constant
checks remain for the loaded fixture path. Fake SQL still needs PDO types and
constants, not a PDO database driver or live connection.

The required-function scan now runs first and uses only `function_exists` and PHP
language constructs. Missing normalization/class-inspection primitives return the
function report before those primitives are called. An unrelated missing fixture
function still permits the safe class/constant scan, preserving a complete combined
module report for the selected profile's existing parent-module forwarding. Rejection
text uses language-level iteration/concatenation, removing its `implode` dependency.
When JSON encoding or stderr writing is disabled, the existing language-level output
fallback still reports the prerequisite failure. No disabled function is restored
or replaced.

`function_exists` itself and the PHP engine language constructs are the explicit
bootstrap requirement. Disabling that probe can fail before fixture loading, but
is outside the prerequisite-status-78 guarantee. The capable parent controller is
also required for profile selection, bounded capture and safe cleanup; child-only
restriction tests do not disable the controller's dependencies. This boundary adds
no PHP configuration framework, production selector or PDO driver requirement.

The table-driven regression individually restricts all eight cleanup functions
(`basename`, `dirname`, `sys_get_temp_dir`, `realpath`, `preg_match`, `chmod`, `unlink`,
`rmdir`) and separately restricts `register_shutdown_function`. Existing base64,
fsync, JSON-encoding and stderr-writing restrictions remain individual cases.
Additional grouped restrictions require the exact complete missing set in both
capability and normal-execution checks:

- Initialization, 22 names: `array_replace`, `str_repeat`, `ucfirst`, `rtrim`,
  `str_starts_with`, `extension_loaded`, `is_array`, `ksort`, `array_key_exists`,
  `array_diff`, `array_keys`, `is_string`, `min`, `preg_match_all`, `preg_replace`,
  `bin2hex`, `hash_equals`, `clearstatcache`, `lstat`, `is_link`, `fstat`, `feof`.
- Trace projection, five names: `array_intersect_key`, `array_flip`, `substr`,
  `strlen`, `count`.
- Bootstrap/reporting, the 11 names listed in the first two inventory rows.

The parent controller remains capable. When disabled bootstrap or shutdown
primitives also prevent the test observer from operating, direct-child cases
record actual exit, diagnostic, trace and filesystem observations only; they do
not claim unobserved class state. Existing version-aware PDO/DOM negative tests
retain their real-versus-controlled distinction. Actual constructor, trace and
cleanup paths exercise SPL methods through the admitted classes.
A combined case applies a real child-only `ctype_digit` restriction and controlled
PDO/PDOStatement absence together, checking that the report retains both function
and class identities without initializing the fixture.

All listed individual and grouped restriction cases pass in the corrected process
suite. Capability probes identify the exact required sets; normal rejection retains
exit 78 and the named diagnostics without an execution trace or new fixture roots.
The combined real-disabled-ctype/controlled-PDO case reports `ctype_digit`, `PDO`
and `PDOStatement` together, with observed unloaded fixture state. The actual
child-only `function_exists` restriction instead exits 255 with its named fatal,
no execution trace and no new root, confirming the documented bootstrap boundary.

The constructor-failure and trace-generation-failure regressions retain their actual
255 exits and missing traces while removing only the root observed as owned by that
child. A further controlled fault emits a real parseable execution trace, then
exits 255; validation rejects it despite the trace, retains the fatal and verifies
owned cleanup. No partial initialization is replaced with invented success counters.

Missing/malformed traces, nonzero exit, privacy redaction, timeout, output overflow
and secondary evidence-write failure checks pass. Each phase also retains a separate
real failed-child evidence record and an unrelated owned sentinel root throughout
the child cases, then verifies both hashes are unchanged. Only those controller-owned
sentinels are removed after verification. Successful and expected-negative cases
leave no accumulating captures or fixture data; unexpected retained failure evidence
is not swept. Private modes, bounded capture and failure-before-capture-cleanup
ordering remain intact.

The shared fixture is unchanged. Early shutdown registration, `finally` cleanup,
ownership/path/symlink checks, false/empty directive normalization, version-aware
class tests and base64 admission all remain. The controlled PHP-failure cases do
not claim cleanup after a forced process termination that runs no shutdown handler.

### Fresh validation and exact correction inventory

The individually run CLI suite passes **207 assertions**, exit 0, in 32.711 seconds;
the corrected process suite passes **295 assertions**, exit 0, in 24.641 seconds.
All three existing
script syntax checks pass: Node `--check` for `tests/WebsitePlatformM5CBrowser.cjs`,
Bash `-n` for `tests/RunM6BMySql.sh`, and PowerShell `Parser.ParseFile` for
`tests/RunM6BMySql.ps1`. These are syntax checks, not browser or native-harness runs.
Fresh observed versions are Windows PHP 8.4.24 CLI ZTS Visual C++ 2022 x64, Node
v24.22.0, PowerShell 7.6.5, Git 2.56.0.windows.2 and Git Bash 5.3.15.

Expanded sequential regression coverage exposed the actual Windows controller's
10-second PHP execution limit; the fatal diagnostic is retained with the failed
development runs. The process suite now schedules five explicit phases: process,
classes, cleanup, capabilities and fixtures. Each uses the same selected PHP
profile and its 10-second execution bound; a single aggregate 30-second parent
wall deadline covers all phases and final cleanup. This preserves the original
bounds while scheduling the added cases. Successful outer cleanup must leave the
private parent empty and remove it, with a final deadline check after cleanup.

All **245 tracked PHP files** pass lint, including refreshed checks of the final
edited code. The actual local process/filesystem runs are distinct from the labeled
source-copy class/fault controls; no native Linux or other PHP-version run is
claimed. Commands, versions, per-suite exits/stdout/stderr, before/after captures,
failed development runs and successful final runs are retained outside Git in
`m6c-cleanup-prerequisites`.

Fresh standalone validation with final affected-suite rechecks passes **ten M6C
suites / 1,183 assertions**: admission 58, artifact 45, bounds 29, CLI process 295,
CLI 207, execution 216, integration 54, recovery 135, scope 53 and security 91.
The final standalone CLI/process rechecks exit 0 in 25.084/26.739 seconds respectively.
One actual CGI invocation and 32 isolated valid-argument denials run. Security executes
one Windows hardlink case; symlink creation is unavailable, so one of two local link
cases runs. The earlier 291-assertion development passes precede the combined
function/class report regression and are retained separately from the final 295.

The suites run as individual `php -n tests/<suite>Test.php` commands; the focused
commands are `php -n tests/WebsitePlatformM6CCliTest.php` and
`php -n tests/WebsitePlatformM6CCliProcessTest.php`. Execution children select the
capable bounded profile. Lint uses `php -n -l` for each tracked PHP file. No installed
INI or PHP binary is changed.

The full available set completes **70 standalone suites, all exit 0; zero failures**,
with final affected-suite rechecks retained in the combined results. The unchanged
synthetic launcher exits 0 in 1,431.728 seconds and passes 2,131 assertions across
161 isolated Bash scenarios, 47 mount cases and 27 no-ctype port/type cases per mount
form using three PHP profiles. It uses fake commands only; no Linux/container/MySQL
harness is executed. Documentation checks enumerate 16 Markdown links, verify all
seven internal anchors and two balanced fence markers, and confirm preserved
history/status and the exact six-file inventory. There are no local file links to
resolve. The working diff check passes. Final staged and committed diff results
will be recorded with the commit evidence in the external `SUMMARY.txt`.

Correction-only inventory: **six modified files; zero production changes**.

| File | Purpose |
| --- | --- |
| `tests/support/WebsitePlatformM6CExecutionProfile.php` | Check bounded cleanup/initialization/bootstrap dependencies while retaining complete safe class/module reporting. |
| `tests/support/WebsitePlatformM6CExecutionChild.php` | Reject missing prerequisites before effects; make rejection/metadata reporting safe when helper primitives are unavailable. |
| `tests/support/WebsitePlatformM6CCliProcess.php` | Allowlist the exact added diagnostic identities without changing capture/resource limits. |
| `tests/support/WebsitePlatformM6CCliProcessChild.php` | Isolated observations and trace-then-fatal/combined-capability controls. |
| `tests/WebsitePlatformM6CCliProcessTest.php` | Bounded phased cleanup/bootstrap coverage, profile propagation and unrelated-state preservation. |
| `docs/sprint-8.8-m6c-local-implementation.md` | Finding, bounded audit, before/after evidence, actual validation and preserved history. |

Production code, operational worker/runtime, shared fixtures, the main CLI suite and
resource limits remain unchanged. All 26 tracked SQL files retain their raw hashes
from this task's starting snapshot. The canonical contents of all 25 migrations
match baseline `7ed48fbaa4858b1b5299dc7cbea98f96e98f4e45`; migration 025's required
canonical hash and the unchanged integrity pins are checked. No SQL is edited or
reapplied.

### Preserved state, review and later Linux check

Migration 025 remains operator-reported applied to working staging, with canonical
SHA-256 `dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.
The backup/download checkpoint remains **operator-reported COMPLETE**; the server
and Windows copies and separate confirmation receipt remain retained. Restore
remains **NOT EXECUTED**. This task does not retrieve, recreate, restore or delete
backups, or independently recheck deployed invariants.

The M6C native integration harness remains a separate missing deliverable. This
correction neither implements it nor runs the M6B harness as its substitute, and
does not provision a protected worker environment. A later authorized Linux run
must execute the corrected **CLI suite first**, then the process regressions and
retain its own actual profile, exits, diagnostics and assertions. No Linux/server
rerun occurs in this desktop correction.

The post-push reply/resolution for finding 4309456808 and observed exact-new-head
review state will be recorded in PR #128 and the external `SUMMARY.txt`. Only this
conversation is eligible for resolution after verifying the fix and checking for a
substantive rebuttal; no unobserved clean review is claimed in this pre-commit record.

M6B remains formally closed; M6C remains IMPLEMENTED LOCALLY / REVIEW REQUIRED with
native validation pending. M6D–M6G and Narrator remain untouched. PR #128 remains
open, non-draft and unmerged with auto-merge disabled. Scope remains zero server,
live SQL/database, migration, backup/restore, deployment, installation, container,
provider or Narrator operations.
