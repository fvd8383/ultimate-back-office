# Sprint 8.8 M6D — Local implementation

Status: **PARTIALLY IMPLEMENTED / REVIEW REQUIRED**. Baseline: `1134c65c5f6cdfea804d634fad73e2d8a354d065`.
Branch: `codex/sprint-8.8-m6d-deployment-authority`.

M6C is COMPLETE / ISOLATED NATIVE GATES PASS / FORMALLY CLOSED under the
[final owner receipt](https://github.com/fvd8383/ultimate-back-office/pull/129#issuecomment-6549478529).
M6B remains formally closed, M6 remains IN PROGRESS, and M6E–M6G remain NOT STARTED.
This task implements service boundaries locally. Native SQL, activation and deployment
are not authorized and are not executed.

## PR #130 queue-starvation correction

Starting head: `36afc20d8fec0599cc108b07593f8bede19d83cd`. The
[P2 finding](https://github.com/fvd8383/ultimate-back-office/pull/130#discussion_r4433555129)
was reproduced twice before editing: A and B shared the old pointer, A succeeded,
and C requested the new pointer. Each subsequent claim threw `stale_write` on B;
B stayed requested with zero executions and C stayed unclaimed. This was a bounded
local PDO/publisher model, not MySQL locking evidence.

The claim scan still selects at most 25 candidates. A fresh site-first transaction
checks current queue state before new eligibility, then retains actor/lifecycle/grant,
target, operation and attempt lock ordering. It rechecks worker trust and in-memory
runtime readiness before disposition. A stale selection cannot cancel a currently
succeeded, running, not-due or recovery-required operation.

| Current evidence | Claim outcome |
| --- | --- |
| Exact pointer/binding obsolete; requester absent/inactive/demoted; current lifecycle/approval or exact grant authority denied | Cancel only after proving no unresolved effects; atomically record `site_deployment_cancelled`, then continue. |
| Live owner, another target-slot owner, disabled binding, not-due work or target reconciliation | Allocate nothing and preserve state; later candidates within the bounded scan may progress. |
| Recorded uncertain effects with the matching own slot/fence | Establish reconciliation through the existing recovery owner, retain slot/fence/counters/attempts, and continue without external inspection. Existing recovery state is preserved. |
| Missing recovery ownership, corrupted source/grant identity, unsupported policy, missing records, runtime/worker failure, SQL or programming failure | Propagate the specific failure and roll back; never relabel it ordinary cancellation. Known rolled-back deadlocks retain the existing bounded retry. |

Only narrow owner-specific denials are classified: requester `unauthorized`, lifecycle
`invalid_transition` after checking immutable source identity, and production-grant
`unauthorized` after checking its exact immutable intent. A grant already consumed by
another operation is also a definite denial, established from the locked row rather
than a generic exception. Arbitrary `conflict`,
`not_found`, `future_gate_required`, SQL and unexpected exceptions are not swallowed.
Safely settled transient retry receipts are checked before cancelling a retried intent;
an unknown receipt or activation evidence cannot establish safe cancellation.
Exhausted safe execution work receives a terminal failed disposition instead of
remaining an ordinary selectable request.

Cancellation preserves original request/payload/requester, counters and prior attempts.
It allocates no lease/attempt/fence, consumes no grant, changes no current pointer and
performs no publisher/artifact action. The transaction returns normally to commit its
status and bounded system audit together. Repeated scans do not duplicate that event;
authorized exact request replay returns the cancelled history without refreshing intent.
The A/B/C regression now proves B cancelled once, C claimed, and A's success/current
pointer untouched. Publish and restore are both covered using service-created history.

Executed sequentially on Windows/PHP 8.4.24: Queue **90**, Execution **63**, Request **70**,
Authority **96**, Recovery **60**, Artifact **4**, CLI **44**, NativeControl **63**, Scope
**58** assertions; all nine suites exited 0. This is **490 behavioral/control assertions
plus 58 source-scope assertions**. Actual commands, exits/signals, durations, stdout/stderr
and the before-fix reproduction are retained outside Git. Queue's expected negative
diagnostics are two transactional PDO faults (audit and deadlock), one unavailable-read
PDO fault and one deliberately injected LogicException. Other suites retain their
existing diagnostic contracts. No unrelated historical campaign or native run occurred.

All six changed PHP files passed syntax lint, including the authored native cases.
Documentation links/anchors/fences and working/staged/committed diff/integrity checks
are recorded outside Git. The correction baseline manifest contains 520 tracked files;
513 remain byte-identical, seven are intentionally modified, and one queue test is new.
The unchanged historical M6B scope-test perimeter remains disclosed below and was not
rerun or counted as PASS in this correction.

The native publish/restore request-history cases now author the same A/B/C progression
with independent PDO observations of committed B disposition, one audit, unchanged A,
only C's new attempt/fence and no publisher-model mutation. The production-role-lock
case now expects durable unauthorized-requester cancellation and no attempt. The exact
18-case inventory/report rules are unchanged. These additions are **AUTHORED / NOT
EXECUTED**, and the broader native gaps below remain unchanged.

This correction changes only eight files: this record, the native-validation record,
`SiteDeploymentService.php`, `WebsitePlatformM6DQueueTest.php` (new), the existing M6D
Execution/Authority tests, `WebsitePlatformM6DFixture.php` and
`WebsitePlatformM6DNativeCases.php`. Fixture selection now models the actual owned join,
ascending IDs, projection and LIMIT 25; a one-shot selection hook models stale snapshots.
Shared owners/runtime, all 25 migration sources/pins, M6C artifact/native sources and
all 77 plan definitions remain unchanged from the starting head. M6D remains partial;
M6C stays formally closed and M6E–M6G stay NOT STARTED.

## Implementation checklist

| Contract | Owner / executable local proof | Later proof |
| --- | --- | --- |
| D19–D26, both requests, each original payload field | Deployment service / request regressions | Independent MySQL requests, duplicate winner reread |
| D02–D04, D15, R06 | Approval owner / grant and production-denial fixtures | Real grant, revocation and result transactions |
| D05–D06, D11–D14, S02 | Site pointer owner / slot, lease, fence and rollback regressions | MySQL barriers, commit and audit faults |
| D16 | Lifecycle owners / live successor review regression | Real lifecycle transaction |
| R01–R05 | Historical-release eligibility / restore regressions | MySQL plus M6E artifact/activation faults |
| C01–C06, C08–C13, D17 | Independent recovery / separate modeled external pointer | MySQL recovery/commit barriers; M6E helper/absence proof |
| U01–U02, S03 | Current actor and trusted worker checks / scoped history fixtures | Actor deletion, role locks; M6F HTTP controls |
| D14, CLI admission | Transaction guard / synthetic child processes | Protected Linux invocation; no operational publisher in M6D |
| DB-only native gate | Distinct M6D inventory, admission/report/cleanup tests | Separately authorized disposable MySQL run, NOT EXECUTED |

All 25 migration sources and existing M6C artifact/native source files have a local
baseline integrity manifest outside Git. Migration 025 is the intended schema;
no additive migration has been identified as necessary. Existing pins are immutable.

## Implemented boundaries

`SiteDeploymentService` implements requestDeployment, requestRestore, claimDeployment,
renewDeploymentLease, executeDeployment, completeDeploymentSuccess,
completeDeploymentFailure, claimDeploymentRecovery, reconcileDeployment,
completeDeploymentRecovery, deploymentForActor and deploymentsForSite. The existing
owners retain lifecycle, approval and environment-pointer SQL. No route or URL is added.

Requests validate bounded typed fields and current scoped internal authority before
the `(site_id, target_id, request_key)` lookup. Both canonical stored payload and hash
must match. Replay precedes readiness, artifact, new-pointer and grant checks and
returns the original operation alongside actual target current ID/version/is_current.
The original requester is never transferred. Duplicate-insert retry uses the unchanged
M6B rollback/retry mechanism and repeats current authorization and identity comparison.

New intent rechecks content/tenant/module/asset eligibility, immutable release identity,
target binding, expected pointer/version and exact production grant. Artifact verification
runs outside SQL, between two authoritative request transactions. Restore requires
same-target successful history and active-health evidence; it reuses immutable bytes,
allows superseded historical approvals, and retains current tenant/content prohibitions.
A fresh exact restore grant is required in the simulated production branch.

Claims serialize site-first, reserve the target slot and increment its fence. Leases
are bound to worker/environment/operation/attempt/kind, with only a SHA-256 token hash
persisted. Renewal cannot revive expired ownership or extend the fixed deadline.
Three execution attempts and two automatic recovery attempts have separate counters;
recovery references the original execution and allocates fresh authority. Explicit
operator recovery uses current authenticated authority and an idempotent bounded key.

The provider-neutral interface exposes stageRelease, activateRelease, healthCheck,
inspectCurrentRelease, restoreRelease and reconcileExternalState. Typed DTOs contain
identity/binding/fence evidence, never caller filesystem paths. Actual external state
must match the prior deployment before activation. Candidate checks and two distinct
active observations separated by at least one second precede success. Health requires
site/release/hash markers, page and asset integrity, origin/TLS/no-redirect/cache rules;
absence compensation requires no-current-link plus configured unavailable/503 evidence.
All external/artifact calls assert that no application SQL transaction is open.

Success atomically validates ownership, pointer, current eligibility/grant and fresh
health, then commits pointer/version, result, audit and slot settlement. A private,
transaction-bound service capability invokes the lifecycle owners; ordinary review
cannot publish. Staging changes only its environment pointer. Simulated production
also publishes/supersedes revisions and activates the site through existing owners.
Success replay requires the same attempt's committed success receipt before new gates.

Production grants lock authoritative decision/grant/approver rows, preserve the unique
current-approval rule through explicit supersession, and bind release/artifact/target/
operation/binding/expected pointer/expiry. Consumption is atomic with activation
authorization. An activation-audit failure rolls consumption and phase back together;
a later result-audit failure rolls pointer/lifecycle/result/audit back but deliberately
retains already committed consumption so the activated operation retains its identity.
Expiry/revocation/role loss denies new success; committed history remains history.

Recovery inspects authoritative DB outcome before any compensation. It cannot stage,
activate a new candidate, rerender or reset budgets. Exact uncommitted effects can be
finalized with full current authority or compensated under fresh restricted authority.
Unknown DB/external outcome retains the slot for reconciliation; safe terminal failure
requires independently observed prior identity and rollback health. Committed success
with external drift stays historical success while the target is blocked. First-publish
compensation preserves NULL and never fabricates previous history.

This slice deliberately routes post-activation compensation through an independent
recovery attempt. The plan also permits compensation by a still-live execution owner;
that optional immediate path is not used. Failed active health retains the target and
schedules bounded recovery instead. No automatic cleanup daemon is introduced.

## Runtime and compatibility

The normal deployment runtime and publisher factory fail closed before configuration,
database bootstrap or publisher selection. No injectable setter, environment override,
configured class name or enabled target selects the test publisher. Tests wire private
dependencies by reflection; `fixture-production-v1` exists only in those fixtures.
Production and staging operational deployment both remain disabled pending M6E.

CLI help parses before bootstrap. Execution is one bounded claim; the reconciliation
entry calls the established build/deployment recovery services. The only M6C runtime
integration change admits the exact reconciliation CLI's `--build ID` form with
recovery-only capability. Its existing Linux UID, root-owned configuration, private
paths and clean-source requirements remain. Build execution cannot be obtained through
that entry. No timer, service, cron or installation is added.

Shared eligibility now allows an active site only when it has a published revision
pointer, preserving live-successor workflows and rejecting inconsistent active state.
Ordinary review transitions retain that serving state. The builder, validator, canonical
hashes, immutable store, build persistence and all existing M6C native contracts remain
unchanged. Migration 025 supplies the existing nine-table schema; there is no new or
modified migration. The canonical 025 SHA-256 remains
`dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0`.

## Executed local evidence

The initial implementation at `36afc20` used Windows PHP 8.4.24 and standalone sequential suites. Its seven M6D
behavior/control suites passed **397 assertions**: request 70, authority 93, execution
63, recovery 60, artifact 4, CLI 44 and native-control 63. The separate M6D scope suite
checks protected source trees, changed-file boundaries and all 77 unchanged requirement
rows; it is source evidence, not behavioral/native proof.

Relevant regression selection (29 suites) passed: M2 Service/Database/ApprovalInvariant;
M3 ServiceBehavior/ReviewGate/Composition/Database/LegacyHashCompatibility;
M4A ServiceBehavior, M4B/M4C Behavior; M5A/M5B Behavior;
M6B Behavior/Authorization/Contract/Recovery/ReplayComparison/Schema;
M6C Artifact/Bounds/Integration/Recovery/StoredInput/Security/Admission/Cli/CliProcess/Execution.
No previous native profile was rerun.

All 31 changed PHP files passed lint (including both CLI scripts and the native
coordinator/support syntax). Local Markdown files/anchors/fences and diff checks passed.
The 109-file raw baseline manifest has 108 unchanged files and exactly the documented
SiteBuildRuntime integration delta; all 25 migration sources and M6C native/artifact
sources in that manifest remain unchanged. Working, staged and committed checks are
retained with the outside-Git summary and actual per-suite stdout/stderr/exits.

Evidence layers are deliberately distinct:

- Actual local behavior: small private artifact files through the unchanged M6C store,
  bounded child processes/help/denial/exits, sanitized output and source integrity.
- Modeled behavior: sequential PDO rollback/identity decisions, clock, independent
  publisher pointer/fence/health, simulated production and fault injection. These do
  not establish MySQL constraints, locking, concurrent effects, HTTP or Apache behavior.
- Authored only: the 18-case DB-only profile and its independent MySQL/process barriers.
  See [native validation](sprint-8.8-m6d-native-validation.md). It was **NOT EXECUTED**.
- Platform limitations: protected Linux UID/mount/cgroup/pcntl runtime, native MySQL,
  actual helper/filesystem activation, Apache/network and browser gates were not run.

Before-fix evidence is retained separately outside Git. The first broad run found an
active-site/no-published-pointer eligibility regression; tightening that shared guard
made M6B Behavior pass. A later new rollback assertion incorrectly expected a revision
state named `approved`; comparing the complete original revision corrected that fixture
expectation. The separate recheck passed. An interrupted intermediate capture has no
final result receipt and contributes no PASS claim.

Expected negative diagnostics are assessed per suite: audited rollback/DB/authorization
and unavailable-commit fixtures emit sanitized PDOException messages; M6B's deliberate
unexpected-failure fixture emits LogicException; older lifecycle fault fixtures emit
RuntimeException. Passing negative subprocess cases preserve their nonzero child exit.
No empty-stderr blanket rule is used. The unchanged historical M6B scope test rejects
the existence of deployment classes by design and therefore fails its old milestone
perimeter; it is disclosed, not weakened or counted as a passing regression. M6D scope
checks the newly authorized perimeter and immutable M6C/migration sources.

## Remaining work and handoff

The service slice is locally implemented, but native validation infrastructure is
incomplete. Before formal M6D acceptance, expand executable DB-only cases for both
authorization serialization orders at claim/activation/result; distinct site/environment
concurrency; the full independent grant-field/expiry/revocation and restore/health/
compensation matrix; explicit duplicate-insert/lost-request-response and failed-request
replay; and complete deployment negative CHECK/FK/retained-history/query-plan fixtures.
The first 18-case profile must not be represented as all D/R/C/S/U acceptance.

Review this exact local source and the harness, complete those gaps, then separately
authorize isolated preparation/admission and exact-source native execution. Missing
native capability is failure/blockage, never a mock fallback or skip-to-PASS. Actual
helper/Apache/network evidence remains M6E; controls and browser evidence remain M6F/M6G.
The accepted B14/B16 allocation is unchanged and unexecuted. M6 stays IN PROGRESS.

No server, real SQL/migration, container/image/service, installation, operational
deployment/provider, backup/restore/prune or merge operation occurred. GitHub source
publication/review is the only external write. Last deployed source remains the
operator-reported `f466fc1cdbfb53325a1db11445736bdc5ea7c6f4`; backups/receipts remain
retained and restore NOT EXECUTED. Local test-only restore decisions are not a backup
or site restore execution.

## Exact changed-file inventory

Modified (7):

```text
docs/codex-handoff.md
docs/sprint-8.8-m6-implementation-plan.md
docs/sprint-8.8.md
private/classes/SiteApprovalManager.php
private/classes/SiteBuildRuntime.php
private/classes/SiteManager.php
private/classes/SiteRevisionManager.php
```

Added (30, including the subsequent queue regression):

```text
docs/sprint-8.8-m6d-local-implementation.md
docs/sprint-8.8-m6d-native-validation.md
private/classes/SiteDeploymentArtifacts.php
private/classes/SiteDeploymentContract.php
private/classes/SiteDeploymentDependencies.php
private/classes/SiteDeploymentRuntime.php
private/classes/SiteDeploymentService.php
private/classes/SiteDeploymentStore.php
private/classes/SitePublisher.php
private/classes/SitePublisherFactory.php
scripts/reconcile-site-jobs.php
scripts/site-deployment-worker.php
tests/WebsitePlatformM6DArtifactTest.php
tests/WebsitePlatformM6DAuthorityTest.php
tests/WebsitePlatformM6DCliTest.php
tests/WebsitePlatformM6DExecutionTest.php
tests/WebsitePlatformM6DNative.php
tests/WebsitePlatformM6DNativeControlTest.php
tests/WebsitePlatformM6DQueueTest.php
tests/WebsitePlatformM6DRecoveryTest.php
tests/WebsitePlatformM6DRequestTest.php
tests/WebsitePlatformM6DScopeTest.php
tests/support/WebsitePlatformM6DCliChild.php
tests/support/WebsitePlatformM6DFixture.php
tests/support/WebsitePlatformM6DNativeCases.php
tests/support/WebsitePlatformM6DNativeContract.php
tests/support/WebsitePlatformM6DNativeControl.php
tests/support/WebsitePlatformM6DNativeFixture.php
tests/support/WebsitePlatformM6DNativeRuntime.php
tests/support/WebsitePlatformM6DNativeWorker.php
```
