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

### Next-run handoff: staging backup and Windows download checkpoint

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
