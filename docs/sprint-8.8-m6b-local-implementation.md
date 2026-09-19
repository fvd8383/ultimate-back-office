# Sprint 8.8 M6B — Local persistence and jobs implementation

Date: 2026-09-19. **M6B — IMPLEMENTED LOCALLY / REVIEW REQUIRED**.
This is local implementation evidence, not staging validation or formal M6 closeout.

## Authority and status

The authorized baseline is `5baae28c9af68cca7694a912d7f35c87c50c9dc5`, the verified
merge of [architecture PR #125](https://github.com/fvd8383/ultimate-back-office/pull/125).
The [reviewed M6 contract](sprint-8.8-m6-implementation-plan.md) remains authoritative.
The clean checkout, repository path and Git remote were verified before editing;
`origin/main` matched this exact baseline after fetch. The implementation branch is
`codex/sprint-8.8-m6b-persistence-jobs`, created from that SHA, not the old planning branch.

Repository: `fvd8383/ultimate-back-office` at
`C:/Users/fvd83/My Drive/Development/ultimate-back-office`.
Local Windows identity: `laptop-imhqf010\fvd83`; Git identity: Frank Dalba,
`frank@frankdalba.com`. Neither `ubo-deploy` nor `codex-validation` was used.
The execution sandbox has its own restricted account; approved local PHP/Git commands
use the current Windows development account. No remote development environment was used.

| Milestone | Current status |
| --- | --- |
| M6A | ARCHITECTURE REVIEWED / MERGED |
| M6B | IMPLEMENTED LOCALLY / REVIEW REQUIRED |
| M6 | IN PROGRESS |
| M6C–M6G | NOT STARTED |
| M5 | COMPLETE FOR SPRINT PROGRESSION |
| M5C | ACCEPTED / NARRATOR FOLLOW-UP DEFERRED |
| Sprint 8.8 | IN PROGRESS |
| Production | UNAUTHORIZED / NOT DEPLOYED |

Last reported staging application SHA remains existing evidence only:
`70a3051f73874e7268b9c1bba45bf19d41f9432a`. It was not remotely reverified.
No staging/production access, SSH, deployment, remote migration, publisher/provider
action, background worker/service, web route, production activation, or resumed
Narrator work occurred. No merge or auto-merge is authorized.

## Complete changed-file inventory

The implementation has 35 changed files: 21 additions and 14 modifications.

| Change | Path | Purpose |
| --- | --- | --- |
| Add | `database/migrations/025_site_build_deployment.sql` | Nine tables, two existing ownership indexes, deferred cyclic FKs. |
| Add | `private/classes/SiteBuildService.php` | Database build lifecycle, leases, recovery, completion and safe history. |
| Add | `private/classes/SiteBuildContract.php` | Bounded values, canonical identity, persisted policy, actor shapes and DTOs. |
| Add | `private/classes/SiteBuildStore.php` | Build-owned SQL and bounded retries after known rollback. |
| Add | `private/classes/SiteBuildDependencies.php` | Unwired trusted internal M6C dependency interface. |
| Modify | `private/classes/SiteRevisionManager.php` | One additive `lockBuildEligibility` method; existing lifecycle behavior unchanged. |
| Modify | `private/classes/SiteApprovalManager.php` | One additive `lockedBuildApprovals` method reusing effective customer approval authority. |
| Add | `tests/WebsitePlatformM6BBehaviorTest.php` | Eligibility, identity, lease, completion, rollback and DTO behavior. |
| Add | `tests/WebsitePlatformM6BAuthorizationTest.php` | B17–B26 authorization distinctions and simulated interleavings. |
| Add | `tests/WebsitePlatformM6BRecoveryTest.php` | Independent counters, recovery budgets, operator requests and replay. |
| Add | `tests/WebsitePlatformM6BContractTest.php` | Canonical values, actor shapes, forged evidence and fail-closed seams. |
| Add | `tests/WebsitePlatformM6BSchemaTest.php` | Static schema contract and canonical SQL splitter checks; not MySQL evidence. |
| Add | `tests/WebsitePlatformM6BScopeTest.php` | Exact application allowlist and immutable historical migrations. |
| Add | `tests/WebsitePlatformM6BMySql.php` | Opt-in fresh/upgrade, real metadata/rejection and independent-process tests. |
| Add | `tests/RunM6BMySql.ps1` | Disposable local Docker launcher and ownership-limited cleanup. |
| Add | `tests/support/WebsitePlatformM6BDatabase.php` | Behavioral fake PDO fixture using existing M2/M3/M5 inputs. |
| Add | `tests/support/WebsitePlatformM6BDependencies.php` | Synthetic, explicitly bound evidence and private reflection test wiring. |
| Add | `tests/support/WebsitePlatformM6BMySqlSupport.php` | Verified local connection, canonical migrations and synthetic native-PDO fixtures. |
| Add | `tests/support/WebsitePlatformM6BMySqlWorker.php` | Independent test processes and explicit barriers; not an application worker. |
| Add | `tests/support/WebsitePlatformM6BMySqlSchemaCases.php` | Real CHECK/FK/uniqueness/deletion rejection fixtures. |
| Add | `tests/support/WebsitePlatformM6BSql.php` | Canonical SQL lexer; no schema approximation or constraint bypass. |
| Add | `tests/support/WebsitePlatformM6BScope.php` | Exact shared later-M6B allowances for historical scope suites. |
| Modify | `tests/WebsitePlatformM2ScopeTest.php` | Permit only the now-authorized build service name. |
| Modify | `tests/WebsitePlatformM3MigrationTest.php` | Replace obsolete absent-025 assertion with exact only-025 allowance. |
| Modify | `tests/WebsitePlatformM3ScopeTest.php` | Exact only-025 allowance. |
| Modify | `tests/WebsitePlatformM4AScopeTest.php` | Exact only-025 allowance. |
| Modify | `tests/WebsitePlatformM4BScopeTest.php` | Exact seven application paths and only-025 allowance. |
| Modify | `tests/WebsitePlatformM4CScopeTest.php` | Exact only-025 allowance. |
| Modify | `tests/WebsitePlatformM5AScopeTest.php` | Exact only-025 allowance. |
| Modify | `tests/WebsitePlatformM5CScopeTest.php` | Exact seven application paths and only-025 allowance; title regression preserved. |
| Add | `docs/sprint-8.8-m6b-local-implementation.md` | This implementation/evidence record. |
| Modify | `docs/sprint-8.8-m6-implementation-plan.md` | Current status and actual M6B links; reviewed contract and historical record retained. |
| Modify | `docs/codex-handoff.md` | Current milestone and migration status. |
| Modify | `docs/sprint-8.8.md` | Current milestone, migration and next-gate status. |
| Modify | `docs/first-customer-checklist.md` | Current critical-path status. |

No historical scope baseline was changed. M6B independently checks the seven-path
application allowlist, exactly one new method in each existing owner, addition-only
owner diffs, all historical migration blobs, and untouched public/infrastructure/
scripts/shared/apps/configuration trees. No generic authorization or job framework
was introduced. `SiteAuthorizationPolicy` and `SiteServiceSupport` are reused unchanged.

## Migration 025

[025](../database/migrations/025_site_build_deployment.sql) defines:
`site_build_jobs`, `site_build_attempts`, `site_releases`,
`site_release_validations`, `site_deployment_targets`, `site_deployments`,
`site_deployment_attempts`, `site_deployment_health_checks`, and
`site_deployment_approvals`.

It retains exact composite site/parent/revision/target ownership, explicit unique
referenced tuples, nullable current-attempt/current-deployment relationships,
request identity separate from deployment payload fingerprint, counter constraints,
policy/deadline/scheduling fields, RESTRICT history ownership, and nullable user FKs
with ON DELETE SET NULL. Targets default disabled. It adds the two reviewed ownership
indexes to `site_business_associations` and `site_approvals`. Cyclic nullable FKs are
added after their referenced tables exist. There are no operational seeds/backfills.

Both attempt tables exclude `recovery_authorized_by_user_id` from **every CHECK**.
The service validates insertion-time actor shape and current authorization, including
non-NULL authenticated operator identity. Permitted later actor deletion preserves
historical trigger/type/key/reason and original execution ownership. Historical NULL
does not authenticate a new caller. Deferred deployment code must use the same rule;
no deployment service is supplied here.

Concrete SQL translation: explicit non-NULL predicates prevent SQL UNKNOWN from
accepting incomplete execution/recovery shape; bounded summary/receipt JSON uses
OCTET_LENGTH checks. UUID/hash identities use binary ASCII collation; durable times
use DATETIME(6). These implement the reviewed contract, not a relaxed replacement.
No server incompatibility has been observed or corrected because MySQL did not run.

SHA-256 of 025's LF bytes (the committed canonical representation):
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.

023/024 were compared byte-for-byte with the authorized baseline checkout via Git's
checkout filters, preserving the repository's Windows CRLF policy. Their canonical
Git blobs also match the baseline, as do all migrations 001–024.

| Historical migration | Unchanged local-byte SHA-256 |
| --- | --- |
| 023 | `7f487cd11852ee4c05f2bc8766f757134a909716982a47dcfbe5614314189e41` |
| 024 | `eb81ee47ce8bfdf27d0dc9c1b15fc920bc566c2609cc5eaf6fc8dab7a9ffc5b9` |

Only migration 025 was added. Local migration executions: **0**.
**Migration 025 NOT APPLIED TO STAGING OR PRODUCTION.** No DDL rollback is claimed.

## Implemented service behavior

`requestBuild`, `claimBuild`, `renewBuildLease`, `completeBuildSuccess`,
`completeBuildFailure`, `retryBuild`, `claimBuildRecovery`, `completeBuildRecovery`,
`buildJobForActor`, `releasesForSite` and `releaseManifestForActor` implement database
transitions and bounded internal DTOs. `executeBuild` and `reconcileBuild` explicitly
return the future gate. There are no routes, buttons, executable application workers,
schedulers or automatic jobs from M5 approval.

Eligibility belongs to the revision/approval owners: explicit site/revision/hash,
approved customer-associated 247sp site, immutable internally approved composition,
current business/association/module, effective material/non-material customer approval,
exact internal approval, successor freshness, registry and current asset metadata/rights.
The existing active/publication path remains gated; EMD/internal-demo is future-gated.
Approved sites do not need to be active. Domain/DNS/SSL/legacy state grants no authority.
The legacy snapshot business ID fallback is read only from the immutable hashed facts,
never a regenerated live profile. Metadata/rights checks do not prove asset bytes.

The lock order is site, required input, ascending user authorization, job, then attempts.
The unchanged policy locks the user parent, ascending grants and role definitions.
After acquiring those locks, the service rechecks stored job/requester identity,
including a requester FK becoming NULL. Retry authorizes both users in ascending order.
Known deadlock/timeout and identity-change retries start fresh transactions and resolve
authority again; uncertain commits are never blindly retried.

Ordinary claims and new success/adoption need both the current trusted worker and the
persisted requester's current active internal Admin/Super Admin role. Supplied worker
arrays must be empty; user IDs, `actor_type` and `trusted=true` cannot authenticate.
Worker capability is rechecked after locks and after external verification. Current
operator identity comes from private invocation wiring, not the operator request array.
Business Owner/Admin/is_owner and historical actor_type are insufficient.

A definitely unauthorized, safely queued job cancels once in the committed transaction,
with one bounded system cancellation event and no attempt/counter/lease/BuildInput/start
event. Database errors are not denial; an untrusted worker cannot cancel. Uncertain
effects become reconciliation-required. Authority loss after a valid claim denies new
success, retaining real execution/candidate/recovery evidence. Existing committed success
and authorized history/replay survive revocation/deletion unchanged.

The fixed server profile is `static-review-v1` with inert contact mode. The canonical
manifest binds exact source hash, public projection, ordered asset digests, registry
digest and toolchain contract. The idempotency hash binds site key, revision, input hash,
builder version and clean code SHA. Unique identity reserves one job/release key;
duplicate requests preserve the original requester and do not retry or transfer ownership.
External preparation is followed by locked source revalidation before intent commit.

Persisted policy: at most three executions; at most two automatic recoveries; 120-second
leases; 30-second heartbeats; 900-second execution and 300-second recovery deadlines;
30/120-second retry and automatic recovery delays. Scheduling uses DB UTC time. Each
claim gets a new random token; only its SHA-256 is stored. Kind, current owner, token,
lease and deadline are checked. Recovery can raise total attempts above three while
execution stays three. Recovery always references the original execution and has no
BuildInput. Detection persists its due time once; polling does not postpone it.

Recovery remains available with an inactive/deleted original requester. Safe absence
or quarantine can settle failure independently; uncommitted adoption still requires
current source and requester authority. Unknown or inconsistent evidence stays blocked.
After automatic exhaustion, a current internal operator's new request key can authorize
one bounded recovery. Matching operator replay returns history without a reusable lease;
conflicting intent is refused. Attempts/counters are never reset, and old producer rows
remain expired/failed instead of being rewritten by recovery success.

Completion first resolves committed DB outcome. Opaque receipt keys are lookup hints,
not proof. Synthetic trusted evidence permits testing one atomic release, validation,
job, attempt and audit transaction. Matching completion replays without duplicate effects;
conflicts fail. Validation/audit errors roll back the whole result. Safe transient failure
alone permits ordinary retry; recovery settlement does not implicitly execute again.
No site/revision/global publication or deployment pointer is mutated.

History uses allowlists and fixed failure messages, bounded pages/attempts and current
reader authorization. It excludes lease tokens/hashes, private storage locators, raw
input snapshots, arbitrary errors and SQL/provider payloads. Manifest inspection is
bounded, sanitized and rechecks the reader after external work. Logs identify exception
classes only; expected injected PDO errors in tests are not suite failures.

## M6C dependency seams and limitations

`SiteBuildDependencies` is a minimal internal interface for current worker capability,
authenticated invocation operator, clean builder identity, immutable public input
preparation, independent exact-producer outcome verification, and sanitized manifest
inspection. Its private service slot defaults NULL. There is no runtime implementation,
public setter, environment bypass or accept-anything verifier. Isolated tests inject a
deterministic implementation via reflection; its receipt map binds job, original attempt,
reserved release and input identity. Normal runtime remains fail-closed when these
dependencies are needed. History of persisted rows uses normal current authorization.

All input/artifact inspection runs outside SQL transactions. Only the trusted in-memory
worker capability check is allowed under locks; it must perform no external I/O. Result
transactions recheck the identity, eligibility, requester and lease. The future M6C
implementation must independently verify real bytes/storage/journals and reject dirty
toolchains; contract validation alone cannot establish those facts.

No artifact rendering, file copying, sealing, public safety proof, real storage adapter,
customer artifact, filesystem recovery, publisher, Apache action, production approval,
deployment orchestration or restore implementation exists in this change. Deployment
tables are dormant contracts for later milestones. The real-MySQL harness's inert
deployment rows are rollback-only schema fixtures, not grants or activation.

## Executed local checks and 77-case mapping

Environment: Windows desktop, installed PHP 8.4.24. All **56/56 standalone suites**
passed (50 existing plus six new). After the PR #126 corrections below, the six M6B
suites pass **483 assertions** (395 at the initial reviewed head, plus 88 correction
assertions). All 56 suites and all tracked PHP lint were rerun for the correction:

| Suite | Assertions passed |
| --- | ---: |
| M6B behavior | 174 |
| M6B authorization | 83 |
| M6B recovery | 49 |
| M6B contract | 25 |
| M6B schema contract, static only | 110 |
| M6B scope | 42 |

Repository PHP lint passed **211/211 files** (all tracked PHP plus the 18 additions,
which become tracked in this PR). PowerShell harness syntax passed. Markdown validation
passed across five changed documents: **84 relative links, one referenced anchor and
balanced fences**. Current-status, exact 35-file inventory and checksum checks passed;
working-tree `git diff --check` passed. Staged and committed diff checks are also run
before delivery, with their results reported alongside the commit/PR. The temporary
documentation validator is outside the repository. The ordinary suite command is
`php tests/<Name>Test.php` for every
root-level standalone `*Test.php`; the real-MySQL entry point is deliberately excluded.
The fake PDO fixture validates decisions, SQL intent, rollback and ordering, not InnoDB
locking, FK actions, server CHECK semantics, filesystem integrity or concurrency.

The reviewed matrix still has **77 planned cases**, not 77 passes. The mapping is:

| Plan cases | Executed local evidence | Remaining gate |
| --- | --- | --- |
| B01–B05 | Material/non-material eligibility, ownership/association/module/approval/successor denials and synthetic success in behavior tests. | Real DB; B01 actual artifact in M6C. |
| B06 | Sequential duplicate identity/requester/event preservation. | Independent concurrent native-PDO harness NOT EXECUTED. |
| B07 | Three execution limit, retry timing, separate counters. | Real MySQL fixture NOT EXECUTED. |
| B08–B09 | Simulated expired leases, late tokens, recovery/adoption and atomic rollback. | Real heartbeat/completion races and M6C filesystem kill points NOT EXECUTED. |
| B10 | Golden canonical UTF-8, maps/lists/NULL/integers, revision and builder SHA identity. | M6C actual byte/digest inputs remain synthetic here. |
| B11 | Matching/conflicting completion/recovery replay, immutable synthetic release. | Real sealed-byte conflict proof in M6C. |
| B12–B16 | Stored rights/metadata rejection, fixed profile/options and bounded interface data only. | M6C byte integrity, public leakage, rendering, SVG/path security, forms/browser tests. |
| B17–B21 | Current roles, dirty/mismatched builder, trust denial, one-time no-execution cancellation, deleted/NULL requester, business-role denial, retry ownership. | Real FK/authorization gates NOT EXECUTED. |
| B22 | Deterministic fake interleavings, fresh authorization after deadlock/identity retry; no locking proof claimed. | Separate processes, barriers and observed InnoDB waits in harness NOT EXECUTED. |
| B23–B26 | Post-claim/new-adoption denial, independent recovery at (4,3,1), committed/lost-ack replay after deletion, requester/actor/token substitution denial. | Native MySQL races NOT EXECUTED; real output faults in M6C. |
| D01–D26 | No deployment behavior implemented; dormant schema shape/identity statically checked. | M6D–M6G service, grant, pointer, HTTP, Apache and replay gates. |
| R01–R06 | No restore behavior implemented. | M6D–M6G restore/runtime gates. |
| C01–C06, C08, C10, C13 | No deployment/helper behavior implemented. | M6D–M6G process, target and filesystem gates. |
| C07, C09, C11–C12 | Build-only synthetic exhausted-budget adoption/denial, lost-ack, stale recovery, automatic/operator limits and replay. | Real MySQL NOT EXECUTED; M6C filesystem kill points and later deployment equivalents. |
| S01–S03 | Static exact column/FK/CHECK contract, SQL parsing, actor-CHECK exclusion, immutable migration checks. | Fresh/upgrade metadata, every FK rejection, negative CHECKs, uniqueness and both-table actor deletion harness NOT EXECUTED; query-plan review remains a real-DB gate. |
| U01–U02 | Build current authorization, strict shared actor insertion shapes, replay after historical actor NULL and safe history. | Native FK fixtures NOT EXECUTED; deployment service and HTTP/CSRF/control-plane gates deferred. |
| U03 | No new control UI. | M6F/M6G browser/accessibility gate. |

## Executable isolated real-MySQL harness

**NOT EXECUTED.** Prerequisite check reports: `local Docker CLI/engine and an existing
mysql:8.4 image are required.` Docker/mysql executables and a local MySQL service were
not available. PHP's normal configuration has PDO but no loaded PDO MySQL driver.
No software, service, PHP INI or host configuration was installed or modified.

With operator-provided local prerequisites, run from the repository:

```powershell
powershell -NoProfile -File tests/RunM6BMySql.ps1 -CheckOnly
powershell -NoProfile -File tests/RunM6BMySql.ps1
```

Use PowerShell 7 (`pwsh`) where installed. The launcher accepts `-Php <existing-php-path>`.
It may load an already installed Windows `php_pdo_mysql.dll` for that process with `-d`;
it does not edit php.ini. It refuses missing Docker/image prerequisites without pulling
or installing anything. Docker's selected endpoint and any DOCKER_HOST override must
be local npipe/unix. TCP/SSH Docker endpoints are rejected.

Each run creates a uniquely named/labelled MySQL 8.4 container, binds only loopback,
stores the data directory in tmpfs and mounts no host/application data. Both layers
verify exact run token/container ID/name/image/port/ownership before SQL. PDO is native,
the server hostname must match that new container, and version must be 8.4.x. The
actual server version and transaction isolation are printed only on an executed run;
neither is claimed here. No application DSN, credentials or tunnel is consulted.

The harness refuses preexisting database names, creates only
`ubo_m6b_<run-token-prefix>_fresh` and `_upgrade`, and executes canonical repository SQL:
fresh 001–025 and upgrade 001–024 plus synthetic preexisting rows, then 025. It hashes
every preexisting table before/after upgrade, inspects actual information_schema, uses
real rejection statements for ownership/uniqueness/CHECKs, and exercises both nullable
historical actor FKs. FK/CHECK enforcement is never disabled. DDL is not transactional;
partial migration failure is contained in the newly created disposable database.

Concurrent requests/claims and requester deactivation/deletion/grant removal/role
scope/name changes use independent PHP processes and explicit barriers. Revocation
races cover both serial orders and require observed InnoDB lock waits from the exact
connection IDs. Native CHECK/audit fault injection checks rollback of result writes;
exhausted-budget recovery after requester deletion checks (4,3,1). Synthetic artifact
evidence remains explicitly synthetic even when used with real PDO.

Cleanup closes only test children, drops only databases this run recorded as newly
created, and removes only the matching run-labelled container. Launcher environment
values are restored. **This task created zero databases, containers, services or
artifacts**, so none required database/container cleanup. Temporary local test-output
and documentation-check files contain development evidence only; no application data.

## Final review gates

The code is implemented locally and requires review. Local real-MySQL schema and
concurrency checks remain mandatory evidence before formal acceptance; sequential
fakes and static SQL checks cannot replace them. M6C must implement/review trusted
wiring, clean toolchain detection, real projection/asset bytes and artifact inspection
before enabling execution. M6D–M6G must implement their separately scoped behavior.
Any later staging/production migration, deployment or validation requires separate
authorization. Nothing in this record authorizes those actions.

The final commit/PR URL and actual automated-review state are reported with the task
result rather than embedded self-referentially in the commit that creates this record.

## PR #126 completed-review corrections

The correction starts from reviewed head
`78e2838d01ae2a51fa8f8cc02ed335d1ee67282e` on the existing branch and PR.
The clean local checkout and GitHub head matched. PR #126 remained open, non-draft,
unmerged, with auto-merge disabled. The completed review reported
[P1 queue progression](https://github.com/fvd8383/ultimate-back-office/pull/126#discussion_r4054753149)
and [P2 successful request replay](https://github.com/fvd8383/ultimate-back-office/pull/126#discussion_r4054753155).
Neither correction changes migration 025 or reopens the reviewed architecture.

Exactly ten existing files change relative to that head; the correction adds/removes
no files. The complete PR inventory above remains 35 files.

| Corrected file | Change |
| --- | --- |
| `private/classes/SiteBuildService.php` | Safe candidate retirement, exact historical request lookup and race resolution. |
| `private/classes/SiteBuildContract.php` | Recompute recorded canonical input/key and compare the complete builder contract. |
| `private/classes/SiteBuildStore.php` | Optional winner lookup after known rollback and before retrying request gates. |
| `tests/WebsitePlatformM6BBehaviorTest.php` | P1 A–F / P2 G–M regressions, snapshots, effect counts and simulated races. |
| `tests/WebsitePlatformM6BMySql.php` | Native queue contention, historical replay, identity rejection and request races. |
| `tests/support/WebsitePlatformM6BDatabase.php` | New bounded query matching and explicit ID ordering. |
| `tests/support/WebsitePlatformM6BDependencies.php` | Isolated synthetic builder/projection variants. |
| `tests/support/WebsitePlatformM6BMySqlSupport.php` | Queue-selection barrier around actual PDO results. |
| `tests/support/WebsitePlatformM6BMySqlWorker.php` | Test-process barriers, effect observations and withheld response. |
| `docs/sprint-8.8-m6b-local-implementation.md` | Correction record and updated evidence totals. |

### P1: disposition and bounded progression

Trusted worker and valid clean current builder identification remain prerequisites.
Under existing locks, B17 cancellation and unsafe-prior-effect handling retain precedence.
A pure comparison validates the candidate's recorded canonical input/key and then its
builder version/SHA/registry/toolchain against the valid current builder. Inconsistent
stored input yields `input_mismatch`; a definite builder incompatibility yields
`builder_unavailable`. No SQL/dependency exceptions enter that pure comparison.

For a safely queued candidate, one transaction sets failed, clears ordinary scheduling,
and records one bounded system failure event. Identity, requester, input, reserved release,
counters and previous attempts are retained; no new attempt, token, lease or BuildInput
is allocated. Commit then advances the loop. Selection remains **LIMIT 20**: 25 old jobs
take one poll for 20 retirements and a second for the remaining five and compatible work.
Repeated/stale observations recheck locked state; audit failure rolls retirement back.

Dirty/unknown/untrusted current infrastructure fails closed without mass retirement.
Database errors, deadlocks and uncertain commits retain their established handling.
An executing owner or unresolved effects remains reconciliation-required. No builder
substitution, identity rewrite or budget reset occurs.

### P2: exact matching using existing schema

1. Validate the request/caller and obtain the trusted current builder identity. In a
   fresh transaction, lock the explicit site and revision through existing owners,
   then resolve current caller authorization. Require exact ownership and expected hash.
2. Use a current locking job read scoped by site, revision, snapshot hash, fixed profile,
   builder version and reviewed SHA, using the existing revision/site index. Read at
   most 101 rows and reject overflow above 100. Include non-success rows in ambiguity
   detection; never select an arbitrary/latest successful job.
3. Decode bounded stored evidence and reconstruct the complete canonical manifest from
   its recorded projection/ordered digests, immutable source hash, fixed profile/options
   and registry/toolchain. Recompute the build-input hash and the reviewed idempotency
   hash including site key/revision. Require equality with persisted evidence and compare
   the complete builder contract with the current trusted builder.
4. Require exactly one matching input and a succeeded job. Multiple deterministic inputs
   conflict, even if one is not successful. When a racing request has already prepared
   input, its exact hash/key must also match the winner.
5. Recompute the immutable source aggregate hash under its owning locks, supporting the
   established generic and legacy representations. Verify the committed release's source
   revision/hash, release key, build-input hash, builder version/SHA and profile against
   the job. Return its safe DTO with `existing=true`, `replayed=true`, and safe release.

The API accepts no caller-projected input/digest. Immutable source and complete
builder/profile/options define deterministic projection; stored canonical evidence
identifies that operation without preparing input or inspecting artifacts again.
A future projector change must change builder/toolchain identity. Changed source or
builder/registry/toolchain is a new gated request; a different profile is rejected by
the current fixed-profile allowlist. Coherently changed job input that disagrees with
its committed release, or multiple inputs for one contract, conflicts safely.

Exact success history requires current caller authorization, not current content
approval/freshness/lifecycle or the original requester's continued authority. Foreign
ownership, wrong hash and corrupt/ambiguous evidence are rejected. History creates no
job/attempt/lease/event/preparation/verification/publication effects and confers no
current deployment, artifact-health or deployability guarantee.

Without an exact success, unchanged `lockBuildEligibility` and all new-build checks
apply. If a winner commits between preflight and a later gate, a fresh locked lookup
resolves that exact winner before propagating a new-build denial. After known duplicate/
lock-conflict rollback, the optional store callback reauthorizes and compares the winner
before retrying gates. Uncertain commit errors still propagate as database failures;
a subsequent explicit retry resolves the recorded operation. Retries remain bounded.

### Correction evidence and outstanding real-MySQL gate

Behavior coverage adds P1 A–F and P2 G–M using snapshots and effect counters: batches
beyond 20, repeat polling, global failures, unresolved effects, audit rollback, retained
retry attempts, B17 precedence, revoked approvals/newer material revisions, deleted
requester/current reader, changed builder/source/profile, corrupt input/release,
ambiguous matches, mid-preparation winner, duplicate-key rollback with fresh caller
authorization and lost commit acknowledgement. Fake interleavings prove behavior only.

The native harness additionally freezes two independent claimers after selecting the
same stale candidate batch and requires an observed InnoDB lock wait before release.
It checks one retirement event per old job and one compatible lease. Other new cases
concurrently replay after revocation/supersession/requester deletion, reject current
caller or changed/corrupt/ambiguous identity, pause input preparation while another
connection completes a winner, and withhold a committed request response before retry.

**Real MySQL/concurrency remains NOT EXECUTED.** Missing local prerequisites remain;
the known-blocked harness was not repeatedly rerun. No installation, image pull, service,
php.ini change, SQL execution, database or container creation occurred. Existing local
Docker, an operator-provided `mysql:8.4` image and PDO MySQL remain prerequisites.

Migration 025 retains its recorded checksum; 001–024 and all milestone/production
statuses remain unchanged. Updated validation totals appear in the executed-checks
section. The correction SHA, both review replies and the single new-head review
request/state are reported with the task result. No new PR, deployment, remote
migration, staging/production access, M6C generation or Narrator action is authorized.
