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

## Local validation and later native gate

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

## Exact changed-file inventory

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
