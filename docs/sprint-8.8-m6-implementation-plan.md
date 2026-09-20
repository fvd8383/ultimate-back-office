# Sprint 8.8 M6 — Build, Deployment, and Restore Implementation Plan

Status: **M6 IN PROGRESS**. **M6A — ARCHITECTURE REVIEWED / MERGED** (PR #125).
**M6B — IMPLEMENTED LOCALLY / REAL-MYSQL VALIDATION PENDING**. **M6C–M6G — NOT STARTED**.
Linux tooling: **CORRECTIONS IMPLEMENTED / REVIEW REQUIRED**; [host modes and prerequisites](sprint-8.8-m6b-linux-validation.md).
Shared-staging volume/setup: **OPERATOR-REPORTED COMPLETE**, runtime verification pending.
This is the merged architecture contract. The separately authorized
[M6B implementation record](sprint-8.8-m6b-local-implementation.md) records the code,
standalone evidence and **NOT EXECUTED** local real-MySQL/concurrency gate.
Migration 025 exists locally/in the PR and is **NOT APPLIED TO STAGING OR PRODUCTION**.

## 1. Authority and boundaries

| Item | Authoritative value |
| --- | --- |
| Repository | `fvd8383/ultimate-back-office` |
| Repository audit baseline | `5c9fee700b83bd04589d612b8bc4e8eea975499a` |
| M6B implementation baseline / merged PR #125 | `5baae28c9af68cca7694a912d7f35c87c50c9dc5` |
| Deployed application baseline | `70a3051f73874e7268b9c1bba45bf19d41f9432a` |
| Why different | PR #124 merged M5 documentation closeout; its diff from the deployed application contains documentation only and requires no staging deployment. |
| M5A / M5B | COMPLETE / STAGING PASS / FORMALLY CLOSED |
| M5C | ACCEPTED / NARRATOR FOLLOW-UP DEFERRED |
| M5 | COMPLETE FOR SPRINT PROGRESSION |
| Sprint 8.8 | IN PROGRESS |
| Production | UNAUTHORIZED / NOT DEPLOYED |
| Migrations | 023 and 024 immutable; 025 created by M6B, not applied to staging or production |

The [merged M6 scope](sprint-8.8.md#m6--build--deployment--restore) is authoritative.
The [M5 closeout](sprint-8.8-m5-closeout.md) and its evidence are unchanged. The historical
M6A task was documentation only; M6B implements the separately authorized persistence
slice without staging/production access, migration or deployment. The deployed SHA is
existing user-supplied/repository evidence, not a new remote observation.

Names and contracts below originated as reviewed M6A proposals. The M6B record
identifies the implemented persistence slice; later artifact, deployment and control
facilities remain unimplemented. Actual host capabilities require the future gate in section
18. No customer DNS, Host-to-site resolution, LeadHub ingestion, domain conversion,
EMD launch, or legacy runtime cutover is added to M6. Those remain M7/M8.

## 2. Audited evidence and findings

Paths in this inventory were inspected at the repository baseline. Existing tests
were inspected as contract evidence; application/runtime tests were not executed by
this documentation-only task.

| Existing source | Finding and M6 consequence |
| --- | --- |
| [SiteManager](../private/classes/SiteManager.php) | Owns site identity, lock/version, lifecycle and association reads. `active`, cancellation and conversion states are future-gated. `approved` requires an internally approved revision and effective customer approval. |
| [SiteRevisionManager](../private/classes/SiteRevisionManager.php) | Owns immutable revision boundary, materiality, ancestry, review transitions and snapshot hashes. Only draft/validation-failed composition is mutable. Publication transitions explicitly require M6. `createRestoreCandidate` creates a new material review candidate; it does not restore a deployment. |
| [SiteApprovalManager](../private/classes/SiteApprovalManager.php), [SiteServiceSupport](../private/classes/SiteServiceSupport.php) | Customer/internal approval implemented; production/conversion future-gated. Effective customer approval and newer-material checks are authoritative. Transactions reject nesting. `site_events` is the canonical site audit. |
| [SiteAuthorizationPolicy](../private/classes/SiteAuthorizationPolicy.php) | Active internal-scope Admin/Super Admin differ from business-scope Owner/Admin. actorContext(userId, connection) locks current users/grants/role definitions; M6 uses it in request, ordinary claim and new-success transactions, separately from trusted worker/recovery authority. |
| [SiteRevisionSnapshotBuilder](../private/classes/SiteRevisionSnapshotBuilder.php), [SiteRevisionSnapshotHasher](../private/classes/SiteRevisionSnapshotHasher.php), [CanonicalJson](../private/classes/CanonicalJson.php) | Authoring snapshots business facts once; hashes include facts, references, brief, ordered pages/sections, component versions, theme and asset descriptors. Hash representation excludes environment-local component row IDs, but includes storage/source references and must not be exported wholesale. |
| [SiteCompositionManager](../private/classes/SiteCompositionManager.php), [SiteCompositionValidator](../private/classes/SiteCompositionValidator.php) | Validated stored composition checks canonical page/section/theme/revision hashes, registry versions, asset readiness/rights/ownership. `MODE_RENDER_READ` supports historical rendering; legacy historical unknown-rights exception is insufficient for public export. |
| [ComponentRegistry](../private/classes/ComponentRegistry.php), [ThemeRegistry](../private/classes/ThemeRegistry.php), [ComponentSchemaValidator](../private/classes/ComponentSchemaValidator.php) | Repository owns executable implementations; DB selects allowlisted versioned configurations. Preserve exact-version rendering and drift rejection. |
| [SiteCompositionRenderer](../private/classes/SiteCompositionRenderer.php), [SiteComponentRenderers](../private/classes/SiteComponentRenderers.php) | Renderer emits a combined fragment with every page, not independent static documents. Asset/action URLs come from context. Lead form is inert when no approved action is supplied. A page-aware static render operation and public context projector are required. |
| [SiteAdminPreview](../private/classes/SiteAdminPreview.php), [SiteCustomerPreview](../private/classes/SiteCustomerPreview.php), [admin preview route](../public/app/admin/site-preview.php), [customer preview route](../public/app/247sp/website-review-preview.php) | Existing previews are authenticated/private. Customer preview explicitly disables forms/links and warns that unresolved media may not appear. Never scrape these routes as build input. |
| [SiteReviewAdminWorkflow](../private/classes/SiteReviewAdminWorkflow.php), [SiteCustomerReviewWorkflow](../private/classes/SiteCustomerReviewWorkflow.php), [SiteCustomerReviewGuard](../private/classes/SiteCustomerReviewGuard.php) | Review capability DTOs are advisory. Mutation services own authorization and state. Admin workflow currently reports `publicly_deployed=false`; M6 must use environment deployment evidence rather than infer success from approval. |
| [Admin Site Review](../public/app/admin/site-review.php), [Website Manager](../public/app/247sp/website-manager.php), [site workspace](../private/classes/SiteAdminWorkspace.php) | Existing internal review and customer feedback surfaces are separate. Preserve CSRF, POST/303/GET, bounded input and customer prohibitions. |
| [023](../database/migrations/023_website_platform_foundation.sql), [024](../database/migrations/024_component_registry_versioning.sql), [database plan](database-plan.md) | 023 has 16 foundation tables and ownership FKs; 024 adds definition-version uniqueness and seeds registry entries. Neither stores build/deployment jobs or releases. |
| [SiteGenerator](../private/classes/SiteGenerator.php), [WebsiteManager](../private/classes/WebsiteManager.php), [legacy preview](../public/app/247sp/site-preview.php), migrations 003/004/007/012/013/015 | Legacy generation writes website/page DB records, not static release directories. Preview remains PHP/authenticated. WebsiteManager uploads into `public/app/uploads/<category>/` and returns `/uploads/...` paths. Do not mutate this runtime during M6. |
| [DomainManager](../private/classes/domains/DomainManager.php), [DomainAutomation](../private/classes/DomainAutomation.php), migrations 009/017/019/020 | Legacy domain-ready/live operations can set legacy website publish status. They do not prove artifact activation or health and must never satisfy M6 success. |
| [deploy-staging.sh](../scripts/deploy-staging.sh), [deployment plan](deployment-plan.md), [environment plan](environment-plan.md) | Historical script updates `/var/www/ubo-repo` in place, lints PHP and reloads Apache. Current documented routine is restricted `ubo-deploy` wrappers, with migration approval separate. |
| [marketing staging evidence](247sp-marketing-staging-preview.md), [infrastructure](../infrastructure/) | Documented app DocumentRoot is `/var/www/ubo-repo/public/app`; marketing has separate aliases. `infrastructure/apache` and `infrastructure/deployment` contain only placeholders. No checked-in actual vhost/helper/systemd/ownership configuration or generic release root exists. |
| [.env.example](../.env.example), [env example](../private/config/env.example.php), [repository rules](codex-rules.md) | Spaces/CDN is intended and settings are placeholders. No Spaces storage client or artifact implementation was found. Upload policy prefers Spaces; existing legacy local uploads are a compatibility fact, not a new storage policy. |
| [M2 invariants](../tests/WebsitePlatformM2ApprovalInvariantTest.php), [M2 DB tests](../tests/WebsitePlatformM2DatabaseTest.php), [M3 DB tests](../tests/WebsitePlatformM3DatabaseTest.php), [M3 renderer](../tests/WebsitePlatformM3RendererTest.php), [M5B behavior](../tests/WebsitePlatformM5BBehaviorTest.php), [M5C scope](../tests/WebsitePlatformM5CScopeTest.php) | Existing standalone PHP/fake-DB and separately authorized real-MySQL/browser gates establish the test style. M6 adds focused fault/concurrency tests rather than claiming fake DB tests prove locking. |

No repository-backed current customer-site symlink convention, release rollback
script, automatic release health probe, shared artifact store, or scheduler was found.
Existing lint, HTTP/browser checks and bounded Apache log review validate the UBO app.
They are not a customer-site health protocol. Wrapper implementation, OS ownership,
Apache modules, mount semantics, actual Spaces configuration and backups are unknown
without the later authorized environment audit.

## 3. Lifecycle authority and exact build eligibility

Build input is **one persisted, immutable generic `site_revisions` aggregate** plus
its exact stored M3 composition and approved public asset bytes. It is not a live
Shared Business Profile read, generation brief alone, preview HTML, legacy website,
customer approval submission, or latest-revision guess. The service receives explicit
`site_id`, `revision_id` and expected snapshot hash.

The first implemented build profile serves customer-associated `247sp` revisions.
EMD/internal-demo schemas remain supported structurally but new build requests for
those purposes return `future_gate_required` until their approval policy is explicitly
implemented. Do not create fake customer approvals/businesses to bypass this boundary.

Add one locked eligibility method to **SiteRevisionManager**, calling existing
SiteManager, SiteApprovalManager and SiteServiceSupport rules. SiteBuildService calls
it; it must not copy their lifecycle SQL. M6B implements this boundary as
`SiteRevisionManager::lockBuildEligibility` with `SiteApprovalManager::lockedBuildApprovals`.

| Gate | Required rule |
| --- | --- |
| Actor | Active internal Admin/Super Admin at request. Every ordinary build execution claim requires BOTH a currently trusted environment-bound build worker AND current locked internal authority of the job's persisted original requester. New build-success acceptance repeats that requester check. Customer/business-scope membership never grants internal build authority. |
| Site | Exists; purpose `247sp`; lifecycle `approved`, or `active` only after the future guarded production transition is enabled. Reject draft/demo/pending/suspended/archived/cancellation/conversion. Existing `assertSiteOperational` only rejects archived, so it is insufficient alone. |
| Business | Current active customer association equals snapshot business identity; business active and not suspended; active business 247SP module and globally active module. Recheck under locks; internal role does not bypass tenant readiness. |
| Revision | Belongs to site; `internally_approved`; materiality is `material` or `non_material`; review-ready evidence exists; stored canonical composition validates. A currently production-published revision may be rebuilt only if it is still the canonical current revision and passes the same approvals/freshness rules. |
| Material approval | Current unrevoked approved customer record on this exact material revision, plus current unrevoked internal approval on this exact revision. |
| Non-material approval | Existing `effectiveCustomerApproval` must return exactly one unrevoked approved earlier customer baseline; internal approval still binds this revision. Do not silently strengthen this to require an already deployed baseline: current code checks earlier approval, despite its message saying “public baseline.” Initial launch still requires material customer approval. |
| Freshness | Reject any newer material revision via existing helper. Also reject a newer revision already internally approved/published. A newer unclassified draft conservatively blocks a new build until classified; a classified non-material draft alone does not invalidate the approved candidate. This extra delivery freshness rule belongs in the single lifecycle eligibility method. |
| Rejected/stale states | `changes_requested`, `validation_failed`, `ready_for_review`, `customer_approved`, `superseded`, and `restored` are not directly buildable. A restore candidate must complete review/internal approval first. |
| Asset/repository integrity | Exact renderable versions; all public assets have current permitted rights, checksum/size matches and site/business ownership. Historical unknown-rights exception is refused. |
| Existing successful build | Return existing job/release for the same identity; never rebuild/overwrite it. Stale content eligibility can block ordinary deployment; later requester authorization loss alone does not invalidate release history or become a deployment gate. |
| Domains | No DomainManager, DNS, SSL, registrar or legacy `publish_status` check grants build eligibility. |

### Original build requester and authority source

SiteBuildService owns this action-authorization check separately from reusable
SiteRevisionManager revision/content eligibility. For a new request, persist the
authenticated actingUserId as requested_by_user_id only after locked authorization.
For every ordinary claim (including retries) and every **new** build-success acceptance,
load that ID from the persisted job; the worker cannot supply or substitute it.

Use `SiteAuthorizationPolicy::actorContext($requesterId, $connection)` inside the
owning transaction, then require its `is_internal_admin` result. The existing policy
requires users.status active and an assigned role named Admin or Super Admin with
roles.scope internal, through user_roles. Its locked path takes the users parent row
FOR UPDATE, user_roles ordered by role_id FOR UPDATE, assigned role definitions ordered
by ID FOR SHARE, then a current locking actor read. Preserve that parent/grant/definition
order; a nonlocking requireInternalAdmin preflight alone cannot satisfy the claim.
Section 8 places those locks within the site-first service transaction. The existing
internal policy is global internal authority applied to the exact site-owned job;
do not invent a customer membership or second build-role model.

Missing/deleted user, NULL requested_by_user_id, inactive user, or absence of any
currently permitted internal role denies ordinary execution/new success. Historical
actor_type, cached/session permissions and request-time authorization are provenance,
not continuing permission. Business-scope Owner/Admin (including is_owner) is
insufficient. A change between Admin and Super Admin remains allowed while the user
is active and the current role is internal; do not compare role names to the historical
actor_type. Current site/business/module/revision/input eligibility is independently
required and cannot be bypassed by internal authority.

Original requester authorization does not govern trusted recovery inspection or
recognition of a committed result. Uncommitted output adopted as a **new** release
must pass the same requester check as ordinary success. Staff turnover does not
invalidate an already committed release or successful history, or become a new
deployment/restore eligibility predicate. Those operations retain their own access,
execution and production-grant rules.

Customer approval alone is explicitly insufficient. M5 material-successor handling
supersedes prior customer/requested internal approvals but can retain an approved
internal record; checking only `internally_approved` or only site `approved` is unsafe.

Lifecycle/input eligibility is rechecked for new requests, execution claims, accepting
successful output, immediately authorizing activation, and committing deployment
success. Build request/claim/new-success transactions also check requester authority
as the separate gate above; section 8 defines the revocation race and safe disposition.
Matching deployment/restore request replay uses current scoped history authorization
before the separate new-operation gates (section 13). Recovery claims require current
trusted recovery authority, not the original build requester's continued access or
renewed publication eligibility. Build adoption checks both current requester authority
and content eligibility; deployment finalization retains its own eligibility/grant
checks. Inspection/compensation follows section 8's narrow rules.
A content successor/approval revocation discovered after build leaves an immutable historical
artifact, not permission to publish it. Normal deployment rejects stale releases;
the separately authorized known-good restore path is the deliberate exception in
section 21. A grant authorizes an exact activation attempt at a defined transaction
boundary; revocation races after that point are handled by final recheck/compensation,
not a false claim that SQL and a filesystem switch are one transaction.

## 4. Existing schema coverage and migration decision

**Migration 025 is definitely required.** No existing operational table safely models
this scope. Do not overload `site_events`, revision lifecycle, legacy import attempts,
Stripe webhook jobs, or DomainManager status as a build/deployment queue.

| Requirement | Existing representation | Proposed addition |
| --- | --- | --- |
| Site/revision/source identity | sites, site_revisions, pages/sections/themes, registry versions, assets and revision assets | Reference these; no duplicate lifecycle aggregate. |
| Source hash | revision snapshot_hash, page/section/theme/brief hashes, asset checksum_sha256 | Separately define build_input_hash and artifact_hash. |
| Customer/internal approval | site_approvals | Reuse. Production type exists but is service-gated and only revision-bound. Add release/target/operation binding. |
| Build job/attempt/lease/retry | Absent | site_build_jobs and site_build_attempts. |
| Immutable artifact/release, validation | Absent | site_releases and site_release_validations. |
| Deployment job/target/attempt | Absent | site_deployment_targets, site_deployments, site_deployment_attempts. |
| Health result, external correlation | Absent as deployment evidence | site_deployment_health_checks, typed external references and attempt receipts. |
| Current/previous deployment | Only global published revision pointer; no environment/deployment identity | One target row per site/environment, current successful deployment FK, previous deployment on each operation. |
| Restore | Revision restored_from_revision_id means content candidate ancestry | New deployment operation with restored_from_deployment_id; retain all history. |
| Safe failure/timing/audit | Generic site_events; import-specific errors only | Typed bounded job/attempt errors, timestamps; reuse site_events. |

## 5. Proposed migration 025 relational contract

Proposed future filename: `025_site_build_deployment.sql`. **Do not create it in this
planning task.** Reconfirm 025 is still next before implementation. Do not alter the
contents of migrations 023/024; new ALTERs belong in 025. No new domain/routing tables.

### Conventions, ownership, isolation, and deletion

Use InnoDB and the existing utf8mb4 convention. Every new table has `id BIGINT
UNSIGNED AUTO_INCREMENT PRIMARY KEY`, `site_id BIGINT UNSIGNED NOT NULL`, and
`created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)`. All times are UTC;
lease comparisons use DB time. `ID` below means BIGINT UNSIGNED; `?` means NULL allowed;
unmarked columns are NOT NULL. `hash` means CHAR(64) ASCII binary collation, lowercase
SHA-256; `uuid` means CHAR(36) ASCII binary; `key` means VARCHAR(100) ASCII binary.
Strings have explicit bounded sizes. All named status/operation fields are VARCHAR,
with CHECK constraints for the listed values, rather than new ENUM conventions.

Every table has FK `site_id -> sites(id) ON DELETE RESTRICT`. Every operational child
has composite ownership FK including site_id, even if the ID alone is globally unique.
Every referenced composite tuple gets an explicit UNIQUE KEY (MySQL 8.4 compatible,
no reliance on nonunique referenced keys). All history/content FKs use RESTRICT,
including optional previous/restore pointers. Optional user actor FKs use SET NULL,
with actor_type retained. No cascade deletion. Normal APIs never delete jobs, releases,
approvals, deployments or checks. Cleanup/retention is a separately approved operation;
current, prior known-good, referenced and held artifacts cannot be garbage-collected.

Generic operational records are site-owned, not required to invent a business_id.
Build jobs snapshot nullable `business_id` and `association_id`; 247SP requires both.
Add `UNIQUE (id, site_id, business_id)` to site_business_associations in 025 and a
composite FK from each job's `(association_id,site_id,business_id)`. This binds tenant
identity at request; current eligibility still rechecks the active association.
All reads join through site ownership; possession of a UUID/ID is not authorization.

`I` fields below never change after insertion; `W` fields are set once with a guarded
transition; `M` fields are mutable only by the owning service under lock/token checks.
Completed attempts/checks are append-only; mutable workflow rows do not erase history.

Index purposes are explicit across the schema: opaque-key UNIQUEs enforce request/
receipt identity; numbered-attempt UNIQUEs prevent duplicate claims; `(id,...,site_id)`
UNIQUEs are ownership FK targets, not additional business identities. Status/due/expiry
indexes bound queue and reconciliation scans; site/target plus time/ID indexes support
keyset history; digest indexes support integrity investigations; correlation indexes
join safe operational evidence. Use `uq_`, `idx_`, `fk_` and `chk_` names following 023,
with table/column abbreviations bounded to MySQL's identifier length. Each FK's purpose
is ownership/history preservation, while the locked owner enforces state predicates
that cannot be expressed as cross-table CHECK constraints.

### 5.1 site_build_jobs — owner SiteBuildService

| Columns | Mutability and purpose |
| --- | --- |
| job_key uuid; release_key uuid; revision_id ID; business_id ID?; association_id ID? | I; opaque correlation, reserved release identity and exact source ownership. |
| snapshot_hash hash; build_input_hash hash; builder_version VARCHAR(64); builder_code_sha CHAR(40) ASCII; build_profile VARCHAR(40); build_options_json JSON; input_manifest_json JSON | I; canonical bounded public input specification and toolchain identity. Input manifest is private DB evidence, never a snapshot dump. |
| idempotency_key hash; requested_by_user_id ID?; actor_type VARCHAR(24); correlation_id key | I; identity and audit. Original requester is non-NULL on new requests, service-immutable and loaded for current claim/new-success authorization. Only user deletion may SET NULL; retained actor_type never grants authority. |
| status VARCHAR(32) default requested; next_attempt_at DATETIME(6)? | M; ordinary execution scheduling only. Shared execution/recovery accounting and policy columns below also apply. |
| current_attempt_id ID?; lock_version BIGINT UNSIGNED default 0 | M; CAS/lease generation. |
| failure_category VARCHAR(40)?; failure_code VARCHAR(64)?; safe_summary VARCHAR(500)? | M; last bounded result, no exception text. |
| started_at DATETIME(6)?; completed_at DATETIME(6)?; updated_at DATETIME(6) | W first start, M terminal/updated timestamps; attempts retain each execution. |

UNIQUE `job_key`, `release_key`, `idempotency_key`, `(id,site_id)`,
`(id,revision_id,site_id)`; INDEX `(status,next_attempt_at,id)`, `(site_id,created_at,id)`,
`(revision_id,site_id)`, `correlation_id`. FK `(revision_id,site_id)` to
site_revisions; actor to users; business to businesses. Add current-attempt FK after
attempt table exists: `(current_attempt_id,id,site_id)` to attempt `(id,build_job_id,site_id)`.
CHECK business/association both NULL or both set. Apply the shared counter CHECKs
below. Job statuses: `requested`, `running`, `retry_wait`, `succeeded`,
`failed`, `reconciliation_required`, `cancelled`. A failed retry may clear completed_at
on the job; its terminal attempt remains immutable. Successful job outcome is terminal;
recovery bookkeeping may still record inspection without rerendering or rewriting it.
Nullable requester FK preserves history after deletion; it does not authorize an
execution/adoption with no requester. No service may replace it on duplicate request,
retry or recovery. Authorization cancellation uses existing status/reason/timestamp
columns and site_events (section 8); this clarification adds no columns or tables.

### Shared execution and recovery columns on jobs and deployments

The selected model reuses both attempt tables with `attempt_kind=execution|recovery`.
The following columns are required on **both** site_build_jobs and site_deployments;
they replace the ambiguous attempt_count/max_attempts execution-budget pair. No new
table, execution-budget reset or later schema amendment is needed for recovery.

| Columns | Mutability and purpose |
| --- | --- |
| attempt_count BIGINT UNSIGNED default 0; execution_count INT UNSIGNED default 0; recovery_count BIGINT UNSIGNED default 0 | M, increment-only; total lease sequence and separate execution/recovery totals. |
| max_execution_attempts INT UNSIGNED default 3; max_automatic_recoveries INT UNSIGNED default 2 | I; persisted per-operation limits selected by server policy. |
| automatic_recovery_count INT UNSIGNED default 0 | M, increment-only; automatic recovery claims over the operation's lifetime, not a resettable retry window. |
| recovery_status VARCHAR(16) default none; next_recovery_at DATETIME(6)? | M; none/required/running/blocked/resolved, separately visible from operation outcome. |
| worker_policy_version VARCHAR(64); worker_policy_json JSON | I; version and exact bounded timing/backoff policy snapshot; no client override. |

CHECK `attempt_count = execution_count + recovery_count`,
`max_execution_attempts BETWEEN 1 AND 3`, `execution_count <= max_execution_attempts`,
`max_automatic_recoveries BETWEEN 0 AND 2`,
`automatic_recovery_count <= max_automatic_recoveries`,
`automatic_recovery_count <= recovery_count`, and the recovery_status allowlist.
There is **no** CHECK capping attempt_count or recovery_count at three. Execution claim
CAS requires execution_count < max_execution_attempts; recovery claim does not.
Each parent adds INDEX `(recovery_status,next_recovery_at,id)` for the separate queue.
Monotonic counter updates and equality to child-row totals are enforced by the locked
service transaction and real-MySQL tests; CHECKs alone cannot count child rows.

The immutable policy snapshot stores `lease_seconds=120`, `heartbeat_seconds=30`,
`execution_timeout_seconds=900` for builds or 300 for deployments,
`recovery_timeout_seconds=300`, `execution_retry_delays_seconds=[30,120]`, and
`automatic_recovery_delays_seconds=[30,120]`. Schema stores limits in typed columns;
service validates JSON against worker_policy_version and these bounded values.
Attempt deadline_at and initial expiry are computed from this snapshot and DB UTC
leased_at, then persisted. Renewal cannot extend deadline_at. A policy version change
applies to new operations only; it never silently changes existing budgets or clocks.

### 5.2 site_build_attempts — owner SiteBuildService worker

| Columns | Mutability and purpose |
| --- | --- |
| build_job_id ID; attempt_number BIGINT UNSIGNED; attempt_kind VARCHAR(16); execution_number INT UNSIGNED?; recovery_number BIGINT UNSIGNED?; worker_id key; lease_token_hash hash; correlation_id key | I; monotonically numbered claim across both kinds and hashed 256-bit secret token. |
| recovery_of_attempt_id ID?; recovery_trigger VARCHAR(16)?; operator_request_key uuid?; recovery_authorized_by_user_id ID?; recovery_actor_type VARCHAR(24)?; recovery_reason_code VARCHAR(64)? | I except actor FK may become NULL on user deletion; exact original execution, trigger and operator request evidence. Nullable actor FK to users(id) uses ON DELETE SET NULL, with actor type retained; excluded from every CHECK, insertion rules enforced by service below. |
| status VARCHAR(24); leased_at DATETIME(6); deadline_at DATETIME(6); lease_expires_at DATETIME(6); heartbeat_at DATETIME(6); started_at DATETIME(6)?; completed_at DATETIME(6)? | I lease start/deadline; M expiry/heartbeat; W start/completion/terminal status. |
| candidate_storage_key VARCHAR(500)?; candidate_artifact_hash hash?; external_reference VARCHAR(191)? | W; bounded private artifact receipt; no raw OS paths/URLs. |
| failure_category VARCHAR(40)?; failure_code VARCHAR(64)?; safe_summary VARCHAR(500)? | W terminal result. |

UNIQUE `(build_job_id,attempt_number)`, `(build_job_id,execution_number)`,
`(build_job_id,recovery_number)`, `(build_job_id,operator_request_key)`,
`(id,site_id)`, `(id,build_job_id,site_id)`;
INDEX `(status,lease_expires_at,id)`, `correlation_id`; FK `(build_job_id,site_id)` to
jobs; FK `(recovery_of_attempt_id,build_job_id,site_id)` to this table's
`(id,build_job_id,site_id)`. CHECK positive attempt and
`leased_at < lease_expires_at AND lease_expires_at <= deadline_at`. Statuses `leased`,
`running`, `succeeded`, `failed`, `expired`, `abandoned`. Lease token never appears in
DTO history, artifact, event metadata or logs.

Shared attempt-shape CHECK references **only** attempt_kind, execution_number,
recovery_number, recovery_of_attempt_id, recovery_trigger, operator_request_key,
recovery_actor_type and recovery_reason_code. Its predicates are:

- attempt_kind IN (execution,recovery).
- Execution branch: execution_number IS NOT NULL and BETWEEN 1 AND 3;
  recovery_number, recovery_of_attempt_id, recovery_trigger, operator_request_key,
  recovery_actor_type and recovery_reason_code each IS NULL.
- Recovery branch: execution_number IS NULL; recovery_number IS NOT NULL and positive;
  recovery_of_attempt_id, recovery_trigger, recovery_actor_type and recovery_reason_code
  each IS NOT NULL; recovery_trigger IN (automatic,operator). Automatic requires
  operator_request_key IS NULL and recovery_actor_type=system. Operator requires
  operator_request_key IS NOT NULL and recovery_actor_type IN (internal_admin,super_admin).

The branches are alternatives selected by attempt_kind; their requirements are
conjoined, with explicit IS NOT NULL guards so SQL CHECK's UNKNOWN result cannot admit
missing required data. Existing numbered-attempt uniqueness, lease timing, statuses,
same-parent/site ownership and execution/recovery budget constraints remain unchanged.

**CHECK/FK boundary:** recovery_authorized_by_user_id is nullable and retains its FK
to users(id) ON DELETE SET NULL. It appears in **no CHECK expression**, including an
IS NULL test for execution/automatic claims or a blanket test over recovery fields.
MySQL 8.4 prohibits combining CHECK references and foreign-key referential actions on
the same column ([CHECK restrictions](https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html)).
Allowing NULL in an operator branch alone does not address that DDL restriction.
The FK still enforces that any non-NULL reference names an existing user; current
authorization and insertion shape for this actor column belong to the service.
Do not add NOT NULL, remove the FK or replace SET NULL with RESTRICT.

**Service insertion rules, both attempt tables:** before inserting an attempt,
incrementing any counter, assigning ownership or issuing a lease/fence, validate the
trusted worker context and actor fields within the existing locked claim transaction.
Operator recovery requires a non-NULL authenticated operator with current permitted
authority, a valid operator_request_key and allowlisted reason under section 8. The
service derives recovery_authorized_by_user_id from that authenticated operator.
Automatic recovery and ordinary execution must insert NULL in this column. Reject
forged overrides or inconsistent actor fields, including a supplied operator reference
on an automatic/execution claim; do not merely rely on the FK/CHECK or silently coerce
the input. These are creation-time service requirements, not historical-row CHECKs.

The existing historical-NULL allowance remains: after a valid operator claim, deleting
its user may SET NULL without invalidating the attempt. Preserve its operator trigger,
actor type, request key, reason, original execution reference and audit history, along
with ownership/counters. The service cannot transfer or rewrite that identity. A NULL
historical FK neither changes the attempt to automatic nor grants new authority;
history/replay still requires current caller authorization, and any new operator claim
requires its own current authenticated operator. Recovery does not depend on restoring
the deleted historical user. This does not alter the original build-requester policy.

Service also verifies recovery_of_attempt_id identifies an earlier **execution** row
of the same parent. A recovery of a crashed recovery keeps that original execution
reference; it gets a new recovery_number/attempt_number, not a reused token.

### 5.3 site_releases — owner SiteBuildService completion

Insert only on verified successful completion; **all columns immutable**. Columns:
`release_key uuid`, `build_job_id ID`, `source_revision_id ID`,
`source_snapshot_hash hash`, `build_input_hash hash`, `artifact_hash hash`,
`manifest_hash hash`, `builder_version VARCHAR(64)`, `builder_code_sha CHAR(40) ASCII`,
`build_profile VARCHAR(40)`, `storage_backend VARCHAR(32)`, `storage_key VARCHAR(500)`,
`manifest_json JSON`, `validation_summary_json JSON`, `file_count INT UNSIGNED`,
`byte_size BIGINT UNSIGNED`, `built_at DATETIME(6)`, `correlation_id key`.

UNIQUE `release_key`, `build_job_id`, `(id,site_id)`,
`(id,source_revision_id,site_id)`; INDEX `(site_id,created_at,id)`, `artifact_hash`,
`correlation_id`. FK `(build_job_id,source_revision_id,site_id)` to
jobs `(id,revision_id,site_id)`; FK `(source_revision_id,site_id)` to revisions.
Service verifies every duplicated digest/version equals the job input. No uniqueness
on artifact_hash: two explicit source identities may intentionally render equal bytes.
No mutable `is_current`/`published` flag. New validation failures append evidence and
block deployability; they do not alter successful artifact history.

### 5.4 site_release_validations — owner artifact validator via SiteBuildService

Columns: `release_id ID?`, `build_attempt_id ID?`, `validation_key uuid`,
`validation_phase VARCHAR(32)`, `validator_version VARCHAR(64)`,
`artifact_hash hash?`, `result VARCHAR(16)`, `summary_json JSON`,
`checked_at DATETIME(6)`, `correlation_id key`. Immutable insert, capped summary 16KiB.
At least one of release/attempt must be present. Build precommit checks reference the
attempt; final accepted release has a second verification record in its completion
transaction. Later stage/restore/integrity checks reference release. Both, if provided,
must belong to the same job, verified by service under job lock.

UNIQUE `validation_key`, `(id,site_id)`; INDEX `(release_id,checked_at,id)`,
`(build_attempt_id,checked_at,id)`, `correlation_id`; ownership FKs to release/attempt.
CHECK phase in `build`, `seal`, `stage`, `restore`, `reconcile`; result `pass`/`fail`.
Only an explicitly permitted, current validation result can satisfy a deployment gate.

### 5.5 site_deployment_targets — owner SiteManager target/pointer methods

Columns: `environment VARCHAR(16)`, `publisher_key VARCHAR(64)`, `binding_key key`,
`binding_version INT UNSIGNED`, `enabled TINYINT(1) default 0`,
`current_deployment_id ID?`, `active_deployment_id ID?`,
`pointer_version BIGINT UNSIGNED default 0`, `fence_epoch BIGINT UNSIGNED default 0`,
`reconciliation_status VARCHAR(24) default clean`, `last_observed_at DATETIME(6)?`,
`updated_at DATETIME(6)`. Site/environment I; binding can change only while disabled,
with no active operation, approved config version change and reconciliation.
Pointer/fence/observation M through guarded SiteManager methods only.

UNIQUE `(site_id,environment)`, `(environment,binding_key)`, `(id,site_id)`;
INDEX `(enabled,reconciliation_status,id)`. CHECK environment `staging`/`production`,
reconciliation `clean`/`required`/`blocked`. Add FKs after deployments exist:
`(current_deployment_id,id,site_id)` and `(active_deployment_id,id,site_id)` to
deployments `(id,target_id,site_id)`. Exactly one row is the pointer authority per
site/environment. NULL current means never successfully deployed; after first success
there is exactly one current successful deployment. SQL FKs prove ownership, not
success status: the guarded success transaction enforces that predicate.

### 5.6 site_deployments — owner SiteDeploymentService

| Columns | Mutability and purpose |
| --- | --- |
| deployment_key uuid; target_id ID; release_id ID; source_revision_id ID; operation VARCHAR(16); request_key uuid; request_payload_hash hash; request_payload_json JSON; reason_code VARCHAR(64) | I; scoped request identity and separate canonical original intent fingerprint/body (section 7). The payload hash is not a unique request key. |
| expected_current_deployment_id ID?; expected_pointer_version BIGINT UNSIGNED; restored_from_deployment_id ID?; deployment_approval_id ID? | I; optimistic request precondition, restore source and production grant. |
| requested_by_user_id ID?; actor_type VARCHAR(24); correlation_id key; binding_version INT UNSIGNED | I; authorization/config snapshot. |
| status VARCHAR(40) default requested; next_attempt_at DATETIME(6)?; current_attempt_id ID? | M; durable operation, ordinary execution scheduling and sole current lease owner. Shared execution/recovery accounting and policy columns above also apply. |
| previous_deployment_id ID?; activation_fence BIGINT UNSIGNED?; external_reference VARCHAR(191)? | W per first activation reservation; target slot forbids intervening deployment; attempts carry recovery fences. |
| failure_category VARCHAR(40)?; failure_code VARCHAR(64)?; safe_summary VARCHAR(500)? | M bounded terminal/current failure. |
| started_at DATETIME(6)?; staged_at DATETIME(6)?; activation_attempted_at DATETIME(6)?; health_verified_at DATETIME(6)?; completed_at DATETIME(6)?; updated_at DATETIME(6) | W milestones/M workflow; attempt rows preserve retries. |

UNIQUE `deployment_key`, `(site_id,target_id,request_key)`, `(id,site_id)`,
`(id,target_id,site_id)`; INDEX `(status,next_attempt_at,id)`,
`(target_id,created_at,id)`, `(release_id,site_id)`, `correlation_id`.
FK `(target_id,site_id)` to targets; FK `(release_id,source_revision_id,site_id)` to
releases `(id,source_revision_id,site_id)`; previous/expected/restore composite FKs
`(pointer,target_id,site_id)` to deployments; actor to users. Current attempt FK
`(current_attempt_id,id,site_id)` added after attempts exist. Approval FK to section
5.9's `(id,release_id,target_id,site_id)` with matching columns on deployment.
CHECK operation `publish`/`restore`; restore requires restored_from_deployment_id,
publish requires it NULL; shared counter/shape CHECKs apply. No uniqueness constraint
on request_payload_hash. Production-approval requirement is a
cross-table locked service check, not an ineffective same-row CHECK.

### 5.7 site_deployment_attempts — owner SiteDeploymentService worker

Columns: `deployment_id ID`, `attempt_number BIGINT UNSIGNED`, `worker_id key`,
`lease_token_hash hash`, `fence_epoch BIGINT UNSIGNED`, `status VARCHAR(24)`,
`leased_at DATETIME(6)`, `deadline_at DATETIME(6)`, `lease_expires_at DATETIME(6)`, `heartbeat_at DATETIME(6)`,
`started_at DATETIME(6)?`, `completed_at DATETIME(6)?`,
`external_reference VARCHAR(191)?`, `receipt_json JSON?`,
`failure_category VARCHAR(40)?`, `failure_code VARCHAR(64)?`,
`safe_summary VARCHAR(500)?`, `correlation_id key`.

Also include the exact attempt_kind, execution_number, recovery_number and all six
recovery authority/reference fields from section 5.2, with the same types/NULL rules.
Same immutability/lease/status/shape CHECKs as build attempts, including the complete
exclusion of recovery_authorized_by_user_id from CHECK expressions and the separate
service-enforced insertion rules. Its nullable users(id) ON DELETE SET NULL FK and
historical-NULL behavior are retained. `receipt_json` is bounded
16KiB structured phase evidence, never provider output; finalized once per attempt.
Phase observations before finalization go to append-only site_events/health checks
and external journal. UNIQUE `(deployment_id,attempt_number)`,
`(deployment_id,execution_number)`, `(deployment_id,recovery_number)`,
`(deployment_id,operator_request_key)`, `(id,site_id)`,
`(id,deployment_id,site_id)`; INDEX `(status,lease_expires_at,id)`, `correlation_id`;
FK `(deployment_id,site_id)` to deployments; FK
`(recovery_of_attempt_id,deployment_id,site_id)` to this table's
`(id,deployment_id,site_id)`; optional recovery actor FK to users SET NULL. Both attempt
kinds allocate a fresh target fence. Expired execution/recovery fences are never reused.

### 5.8 site_deployment_health_checks — owner health checker via deployment service

Immutable columns: `deployment_id ID`, `deployment_attempt_id ID`, `check_key uuid`,
`phase VARCHAR(24)`, `probe_profile VARCHAR(64)`, `expected_release_id ID?`,
`expected_absent TINYINT(1) default 0`,
`observed_release_key uuid?`, `observed_artifact_hash hash?`,
`http_status SMALLINT UNSIGNED?`, `duration_ms INT UNSIGNED`, `result VARCHAR(16)`,
`failure_code VARCHAR(64)?`, `summary_json JSON`, `checked_at DATETIME(6)`,
`correlation_id key`. Summary capped 4KiB; no response body/cookies/credentials.

UNIQUE `check_key`, `(id,site_id)`; INDEX `(deployment_id,checked_at,id)`,
`(deployment_attempt_id,checked_at,id)`, `correlation_id`. FK
`(deployment_attempt_id,deployment_id,site_id)` to attempts; ownership FKs to
deployment and expected release. Service checks expected release equals candidate,
or previous release for phase `rollback`. CHECK phase `candidate`/`active`/`rollback`/
`reconcile`; result `pass`/`fail`. CHECK expected_absent is 0 with a non-NULL expected
release, or 1 with NULL release and phase rollback/reconcile. Absence is only the
first-deployment compensation case; it can never prove deployment success. Two active
PASS checks with non-NULL expected release form the success proof.

### 5.9 site_deployment_approvals — owner SiteApprovalManager

This **extends**, rather than replaces, the existing production decision in
site_approvals. Columns: `approval_id ID`, `release_id ID`, `source_revision_id ID`,
`target_id ID`, `operation VARCHAR(16)`, `artifact_hash hash`,
`expected_current_deployment_id ID?`, `expected_pointer_version BIGINT UNSIGNED`,
`binding_version INT UNSIGNED`, `expires_at DATETIME(6)`,
`consumed_by_deployment_id ID?`, `consumed_at DATETIME(6)?`, `correlation_id key`.
All I except consumed fields set together once at activation authorization.
Decision/revocation stays on site_approvals; no second approval-state column.

UNIQUE `approval_id`, `(id,site_id)`, `(id,release_id,target_id,site_id)`;
INDEX `(target_id,expires_at,id)`, `(release_id,site_id)`, `correlation_id`.
Add UNIQUE `(id,revision_id,site_id)` to site_approvals. FKs
`(approval_id,source_revision_id,site_id)` to approvals,
`(release_id,source_revision_id,site_id)` to releases, `(target_id,site_id)` to targets;
expected/consumed deployment composite ownership FKs include target/site. Consume
link added last to resolve DDL cycles. CHECK operation publish/restore and paired
consumption NULLability. Locked service enforces production target, production type,
approved/unrevoked/unexpired decision, exact hash/binding/pointer and Super Admin.

Existing unique current-approved `(revision,type)` permits only one current production
approval per revision. A replacement grant explicitly supersedes the old approval
through SiteApprovalManager before creating the new one; no index weakening. Revoking
an in-flight grant cancels pending activation or requires compensation/reconciliation.

### DDL order and scope

Create jobs and attempts; add job attempt FK; create releases and validations; create
targets without deployment pointers; create deployments without attempt/approval FKs;
create attempts/checks; create approval extension; add circular nullable FKs last.
Add only the two existing-table ownership UNIQUE keys described above. Do not add
non-null columns requiring a foundation backfill. Fresh database and 023/024 upgrade
paths must both pass real MySQL with native prepares. DDL application is a separately
approved one-time operation, not wrapped in a fictitious transactional migration.

## 6. SiteBuildService API and DTOs

Follow existing static PHP service/associative-array conventions, with explicit
allowlists and PHPDoc array shapes, rather than a framework or generic job platform.
The M6B persistence service is `private/classes/SiteBuildService.php`; external
execution remains fail-closed until the separately implemented M6C dependencies exist.

| Proposed operation | Inputs and output | Boundary |
| --- | --- | --- |
| `requestBuild(int $actingUserId, array $input): array` | Input site_id, revision_id, expected_snapshot_hash, build_profile, correlation_id?; output BuildJob DTO plus existing boolean. Requester is authenticated actingUserId; input cannot choose another requester. Builder version is server-selected. | Locked current internal authorization plus separate lifecycle/input eligibility; new insert persists original requester. Duplicate returns the existing job without transferring/requeueing it; no FS/network. |
| `claimBuild(array $workerContext): ?array` | Trusted environment-bound build-worker context, no requester override; successful result is execution Lease DTO plus immutable BuildInput DTO. Denied candidate yields no lease/input. | Section 8's site-first transaction locks and reauthorizes the persisted original requester, then rechecks eligibility, budget and ownership/no unresolved recovery before any attempt/counter/start-event write. Safely denied queued job is cancelled once. |
| `renewBuildLease(array $lease): array` | job_id, attempt_id, token; returns expiry. | Token, current attempt, status, unexpired lease and max runtime CAS. Renewal alone is not evidence of current requester or other success authorization. |
| `executeBuild(array $lease): array` | Valid execution lease; rejects recovery kind; returns verified candidate receipt. | Outside DB transaction: bounded asset reads, static render, validation, immutable candidate storage. |
| `completeBuildSuccess(array $lease, array $receipt): array` | Execution completion with verified sealed candidate; returns Release DTO only on accepted or already committed success. Recovery adoption uses the same internal success transaction through completeBuildRecovery. | Recognize committed result first under current worker/read authority. For new success, lock and reauthorize original requester plus source/eligibility/lease checks; insert release/validation, job/current-attempt success and event atomically. Missing requester authority rejects new success and uses section 8's safe failure/recovery disposition. |
| `completeBuildFailure(array $lease, array $failure): array` | Fixed category/code/safe summary; returns job. | Trusted current lease may record failure without original requester permission. Classify safe failure versus unresolved effects; authorization loss never auto-retries or changes publication pointers. |
| `buildJobForActor(int $actingUserId, int $jobId): array` | Internal reader; safe job/attempt summary. | Read-only tenant-scoped projection; no lease tokens/input snapshot. |
| `releasesForSite(int $actingUserId, int $siteId, array $cursor): array` | Keyset cursor (created_at,id), limit 1..100. | Read-only history, immutable Release DTOs. |
| `releaseManifestForActor(int $actingUserId, int $releaseId): array` | Returns bounded sanitized metadata/file manifest and integrity status. | Internal only; no unrestricted file-path/download API. |
| `retryBuild(int $actingUserId, int $jobId, string $correlationId): array` | Eligible transient failure, remaining execution budget, resolved external state. Current caller and persisted original requester must each have current internal authorization. | Site-first locked checks; requeue same job/event, preserve original requester and counters. Every later claim rechecks requester again. No ownership transfer; succeeded/cancelled or authorization-failed jobs are not requeued. |
| `claimBuildRecovery(int $jobId, array $workerContext, ?array $operatorRequest = null): array` | Current trusted recovery worker; optional currently authorized operator actor/request_key/reason_code. Returns existing resolved/request result or fresh recovery Lease DTO. | Site-first short transaction; separate recovery budget even at execution_count=3 or with missing/revoked original requester. Prior execution identity fixed server-side; claim grants inspection/recovery, not new-success acceptance. |
| `reconcileBuild(array $recoveryLease): array` | Current recovery-only lease with recovery_of_attempt_id; produces bounded evidence for completeBuildRecovery. | Inspect exact prior output outside transaction; no rendering/new output. |
| `completeBuildRecovery(array $lease, array $result): array` | Recovery disposition recorded_success/adopted/safely_failed/retryable_recovery_failure/blocked, plus bounded evidence. | One guarded result transaction. Adopted invokes shared new-success logic, including current original-requester authorization; otherwise settle safely/block. Recorded committed success, inspection and safe failure do not require that requester. No nested/separate success commit, execution claim or expired-attempt rewrite. |

BuildJob DTO: IDs, status, source hash, profile/version, execution_count/limit,
recovery_count/automatic count/limit, total attempt_count, recovery_status, separate
next execution/recovery times, safe error, timestamps, correlation and release ID if
succeeded. Lease DTO identifies attempt_kind, attempt_number, kind-specific number,
recovery_of_attempt_id when applicable, deadline_at and opaque token only in worker
memory. Recovery is not a generic BuildInput/executeBuild capability. BuildInput contains projected facts, ordered
composition, exact versions, asset digests/authorized locator handles and output
options. Release DTO contains IDs/digests/profile/validation summary/file counts and
timestamp, never secrets or arbitrary storage credentials.

Canonicalize/validate proposed input outside the intent transaction, then reread the
source identity/hash and eligibility under site/revision locks before insert. No live
business refresh inside build. Hashing file bytes is external work; changes between
snapshot and read fail integrity validation. Result transactions consume verified
receipts, never inspect files while holding lifecycle locks. A worker must assert
`inTransaction() === false` before every storage/publisher operation.

## 7. Deterministic idempotency

`build_input_hash = CanonicalJson::hash({contract_version, source_snapshot_hash,
public_composition, public_facts, ordered_asset_digests, output_profile,
output_options, registry_manifest_digest, toolchain_contract})`.
Keep full source hash as integrity evidence but public projection in the private
input manifest; public artifact metadata does not contain source facts/references.

`idempotency_key = SHA256(CanonicalJson::encode({site_key, revision_id,
build_input_hash, builder_version, builder_code_sha}))`.

No timestamps, actor, correlation, worker, lease, environment, hostname or database
component IDs enter the key. DB-local revision ID intentionally distinguishes two
revision identities within this deployment control plane; exported manifest also
includes site_key/revision_number. Builder identity includes exact reviewed Git SHA,
static asset bundle, canonicalization/renderer version and runtime compatibility
profile. Dirty/unidentified builder checkout cannot process jobs. Golden hash tests
lock JSON ordering, list order, integer/NULL handling, UTF-8 and asset ordering.

| Case | Required behavior |
| --- | --- |
| Duplicate/simultaneous request | UNIQUE identity plus locked read/insert returns one job/release_key; rollback duplicate-key transaction before rereading winner; no duplicate requested event. |
| Same input and builder | Reuse successful immutable release; active job reports in progress. |
| Retry after transient failure | Same job/key/reserved release_key and original requester; new execution only below limit and after current requester authorization at every claim. An authorized retry caller cannot take over requester identity. |
| Crash/expired lease | Separate recovery claim first if external outcome is uncertain; execution budget exhaustion never prevents bounded recovery. No blind render retry. |
| Builder code changes | New key/job/release even with same semantic version; server must identify the new SHA. |
| Successor revision | Distinct key; normal freshness rules determine eligibility. |
| Different options/profile | New input hash and key; only server-allowlisted profiles/options. |
| Existing success with stale content eligibility | History is retained; ordinary deployability false. Never silently regenerate or reactivate it. |
| Original requester authorization lost | Section 8 denies ordinary claims/new success and settles the queue safely; duplicate requests never change requester or revive a cancelled job. Committed release history survives; deployment eligibility is assessed independently. |

### Deployment request identity and original payload

For both requestDeployment and requestRestore, request **identity** is the tuple
`(site_id,target_id,request_key)`, enforced by its UNIQUE key. Normalize request_key
once to a lowercase UUID. It is not an authorization token and is not a payload hash.
Different authorized target namespaces can use the same UUID without sharing history.
Never look up a request key globally or move it to another target on retry.

The immutable original payload is CanonicalJson::encode of exactly:
`{request_contract_version:1, site_id, target_id, environment, release_id, operation,
expected_current_deployment_id, expected_pointer_version, restored_from_deployment_id,
deployment_approval_id, binding_version, reason_code}`. Environment comes from the
authorized target's immutable environment, not a client-selected credential. The
caller supplies the expected binding_version; do not substitute today's version on
replay. All other listed values come from the normalized original request, not from
current pointer/grant state. Store the canonical JSON and its SHA-256 separately as
request_payload_json and request_payload_hash; compare hash **and** re-encoded canonical
stored payload on replay. Neither has a uniqueness constraint.

Reject unknown fields, floating/coerced IDs and invalid types. IDs are positive
PHP-representable integers; pointer version is a nonnegative integer; nullable fields
have explicit NULL normalization. Publish requires NULL restore source; restore
requires its explicit source. reason_code is a required allowlisted intent code, not
free text. Release identity determines its immutable revision/artifact/profile;
binding_version fixes configuration intent. New-operation checks resolve and verify
those records only after the existing-request path has been considered. Caller cannot
override worker policy, target environment, source revision or builder configuration.

request_key is outside the fingerprint because it names the request; authenticated
actor and correlation_id are recorded separately for provenance/tracing. A currently
authorized different internal operator may retrieve the same operation without
impersonating its original requester. Original actor, policy snapshot, timestamps and
correlation are never replaced. Trace IDs, time or today's server policy cannot make
an otherwise exact replay a different operation.

Matching identity/payload returns the recorded operation before new-operation pointer,
eligibility, binding-readiness or grant checks (section 13). Changed payload conflicts.
A new request key is a new intent and must pass every current precondition; matching
history confers no execution/retry/production authority.

## 8. Worker leases, retries, and completion

Execution means new rendering, staging or candidate activation through an ordinary
claim/retry. Recovery means resolving effects of an already-recorded execution:
inspection, verified adoption/finalization or narrowly bounded compensation. Both use
new attempt rows, but only execution consumes the three-execution budget.

Section 5 persists the limits and immutable timing policy. Lease is 120 seconds with
30-second heartbeat; execution deadline is 15 minutes for build and 5 for deployment;
each recovery has a 5-minute absolute deadline. Runtime/renewal never extends that
persisted deadline. Execution retry delays are 30/120 seconds. Deadlock retries are
bounded separately and consume no claim until the transaction commits. DB UTC time
governs lease, deadline and due comparisons.

Select candidate IDs without locks; then lock site, relevant revision/approval/tenant
rows, current authorization rows where required, target if applicable, job and attempt
in documented order, and reread queue eligibility. The ordinary build protocol below
adds the original-requester gate; it is not a prerequisite for recovery inspection.
Site-first locking matches lifecycle owners. Do not lock a job first and
then wait on site in another path. Queue-only `SKIP LOCKED` is optional optimization,
not an eligibility/read-consistency mechanism; MySQL explicitly limits its useful
semantics to queue-like workloads ([locking-read documentation](https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html)).

### Ordinary build claim, denial, and requester revocation

1. Authenticate the currently trusted environment-bound worker and its build-claim/
   execution capability. Select a requested/due retry_wait candidate using the queue
   approach above; a preflight/candidate read supplies no execution authority.
2. Start a fresh short SiteServiceSupport transaction. Lock the site first using
   SiteManager::lockSite, then the relevant revision/approval/tenant/input rows through
   their existing owners. Acquire locks before evaluating current eligibility; never
   take a job/attempt lock and then wait for its site. No external I/O in this transaction.
3. Read the persisted job's identity and original requested_by_user_id scoped to that
   site, then use section 3's locked actorContext path on this connection. Require an
   active currently permitted internal requester separately from worker trust. This
   identity-only read cannot substitute for current locking authorization/eligibility
   reads; no cached actor or worker-supplied requester is accepted.
4. Lock the job/current attempt and reread the persisted identity, status and ownership.
   If identity changed (including an FK becoming NULL), do not substitute another user
   or allocate a claim; restart/reassess from current rows. Check the original requester
   result, lifecycle/input eligibility, execution_count < max_execution_attempts,
   no unexpired current owner, and no unresolved recovery. Nullable history is not an
   authorization exception. Mutable eligibility inputs use current locked values,
   never an earlier queue/identity snapshot.
5. Only after **all** checks pass, insert the execution attempt, increment total and
   execution counters, assign current_attempt_id, persist token hash/lease/deadline,
   and append site_build_started atomically. A failed transaction allocates nothing.
6. Commit before returning the lease and BuildInput or performing any render, asset
   read/copy, filesystem, storage or publisher action. Scan candidates in bounded
   batches; a denied candidate returns no lease/BuildInput, even if scanning continues
   to another authorized job.

If requester revocation committed before the locked authorization check, this path
creates **zero** new execution attempts, counter increments, execution leases,
BuildInput DTOs, execution-start events or external actions for that job. Under the
same job lock, if status is requested/retry_wait, no owner is still executing, and
durable evidence establishes no unresolved effects, transition exactly to cancelled,
set failure_category=authorization, failure_code=requester_not_authorized,
safe_summary="Original build requester is not currently authorized.", completed_at
and updated_at to DB UTC, clear next_attempt_at, and increment lock_version only.
Keep original identity, counters and all prior attempts/receipts. Append exactly one
site_build_cancelled event with system actor and the bounded reason in the same
transaction. Cancelled is terminal for queue/retry purposes. Guard the status transition
so repeated polls or simultaneous claimers produce no additional cancellation event.

Handle only a definite requester authorization denial (including NULL/missing user)
as that disposition. The policy's unauthorized result is handled inside the callback
so the cancellation can commit; do not throw it through SiteServiceSupport::transaction
and accidentally roll back the queue disposition. Database errors/deadlocks roll back
and follow bounded retry policy, not a false authorization cancellation. An untrusted
worker cannot cancel jobs. If prior effects/commit outcome are uncertain, instead
preserve/set reconciliation_required and the existing required/blocked recovery state
and ownership rules; remove it from ordinary scheduling without claiming safe
cancellation. No recovery lease/counter is allocated by the denied execution path.

retryBuild reauthorizes its current caller AND the persisted original requester under
these locks, and preserves requested_by_user_id. When two users require authorization,
lock their policy rows in ascending user-ID order. Retry can requeue only a resolved
transient failure with remaining execution budget; authorization-failed/cancelled jobs
are not eligible. If requester access is revoked after requeue, the later ordinary
claim repeats all checks and takes the denial disposition above. A different caller,
duplicate request, changed correlation or restored permission never silently replaces
the original requester, transfers the job or resets any budget.

The user/grant/role locks define the race boundary. A committed revocation observed
by the locked check denies the claim. If the authorized claim commits first, revocation
can follow and external work may already have started; do not claim zero execution
or promise instant interruption. Real-MySQL tests must establish both serial outcomes
for user deactivation/deletion, grant removal and role-definition changes. Deadlock
retries reenter a fresh transaction and reauthorize; preflight/session caches cannot
decide the winner. No transaction remains open for the duration of rendering.

Before accepting **new** build success, use the same site-first/current requester locks
and separate source, eligibility, current-lease/token/kind and receipt checks. Hold
authorization locks through that short result commit, so a revocation serialized
before acceptance prevents a new release, success validation or success event. This
also applies to recovery adoption through completeBuildRecovery. Lease renewal alone
does not establish any of these success permissions.

On post-claim requester loss, preserve candidate receipt/evidence and make the output
unavailable for release acceptance; any inspection/quarantine storage action occurs
outside SQL transactions under valid execution/recovery ownership. Record bounded
authorization failure. Under a still-current execution lease, set the job and that
execution attempt failed only after verified closed/quarantined output and absence of
unresolved effects; otherwise retain reconciliation_required and lease/recovery rules
until safely settled. Recovery instead uses its existing safely_failed disposition:
job failed, recovery attempt succeeded, original expired attempt unchanged. No release
is inserted, no counters are reset and no authorization failure auto-retries. Work already performed and its attempt
remain historical facts. An expired worker cannot settle state using its old token.

Read committed result identity before applying new-success authorization gates. If
success committed before revocation, preserve the release/job success and return its
recorded result under current worker/reader authorization without a new success event.
The original requester's later deletion/deactivation cannot turn committed success
into failure. This distinction also applies when the commit acknowledgement was lost.

### Shared execution and recovery accounting

Every successful authorized claim locks the parent/current attempt, establishes that
no unexpired owner remains, and increments attempt_count. The new attempt_number
equals that total.
An execution claim also increments execution_count and assigns execution_number;
a recovery claim instead increments recovery_count and assigns recovery_number.
Thus after three executions, first recovery is attempt_number=4, recovery_number=1,
execution_number=NULL; execution_count stays 3. Only automatic recovery increments
automatic_recovery_count. current_attempt_id's same-parent/site FK, locked CAS and
UNIQUE attempt/kind numbers enforce one current owner; prior expired rows stay intact.

Each claim creates a fresh random 32-byte token (only SHA-256 stored), persisted
deadline/expiry and start event. Deployment execution **and recovery** claims also
increment target fence_epoch. Renew/result/phase updates match kind, token, current
attempt, unexpired lease and deadline. Recovery cannot call executeBuild,
executeDeployment, stageRelease or candidate activateRelease. Expired tokens/fences
cannot be renewed or reused. The original activation_fence remains provenance;
the current attempt's new fence is the only fence authorized for recovery effects.

Errors use fixed codes/categories: `authorization`, `eligibility`, `input_invalid`,
`integrity`, `configuration`, `transient_storage`, `transient_network`, `lease_lost`,
`external_unknown`, `health_failed`, `database_unavailable`. Only transient storage/
network errors before activation auto-retry. Invalid source, rights, tampering, config,
missing approval and health failures need correction/new explicit action. Ambiguous
external outcomes require reconciliation. Execution exhaustion prohibits further
ordinary execution, not recovery. Unknown effects at exhaustion set operation status
reconciliation_required and recovery_status required, not terminal-safe failed.
Verified absence of effects can settle failed without retry. Never reset execution
counters, raise an existing operation's immutable limit, or create a replacement
operation merely to evade the limit or abandon an unresolved target slot.

### Recovery claim policy and permitted work

Recovery claim/inspection authority is the trusted recovery worker and, when policy
requires it, a currently authorized operator. It does **not** require the original
build requester to exist, be active or regain internal authority, even after execution
three. Recovery may inspect artifacts/journals/pointers, recognize committed success,
quarantine unacceptable output, record verified safe failure or perform the existing
bounded compensation where applicable. New build-output adoption is different: it
must pass section 3's current original-requester authorization and content eligibility
inside the guarded new-success transaction. A revoked/missing requester blocks that
adoption, not inspection or safe settlement; a recovery lease cannot bypass the gate.

Before claiming or compensating, reread the authoritative DB result for the original
operation/execution ID. The claim transaction repeats this check under its site-first
parent/current-attempt locks before incrementing counters or replacing ownership; it
cannot rely on a preflight snapshot. A late old result cannot commit after ownership
is replaced. An already committed success is returned/retained; do not
roll it back because its acknowledgement was lost or its grant has since expired.
If DB commit state cannot be determined, remain blocked and perform no compensation.
External inspection that is still necessary uses a new recovery lease; operation
status succeeded stays succeeded, with independent recovery_status for any actual
drift. History retrieval alone requires no lease or fence and has no side effects.
For a committed success whose active slot was cleared, further external recovery
requires the target still to identify that deployment as current and no newer active
operation; acquire its slot under the target lock before granting recovery ownership.
A historical operation cannot acquire a slot or mutate the target over a newer winner.

Automatic recovery allows at most **two committed recovery claims per operation**,
due after 30 then 120 seconds from the corresponding detected failure. Detection
persists next_recovery_at once; repeated polling cannot slide the deadline or reset
the count. Crash, expiry and unknown recovery results consume their claim. Conflicting
identity, tampering or indeterminate recovery worker/operator authority blocks
immediately; transient recovery failure may schedule the second allowed automatic claim.
If both automatic claims fail/expire, set recovery_status blocked, clear
next_recovery_at, and retain reconciliation_required/blocked target and active slot.
If persisted max_automatic_recoveries is zero, require operator recovery immediately;
a lower persisted limit blocks when that limit is reached. Only required/due recovery
is schedulable; blocked recovery is never selected automatically.

A later explicit operator request can claim **one** additional recovery at any time
after current ownership expires/is safely released, without schema or budget changes.
It requires current scoped internal Admin/Super Admin for build/staging recovery or
Super Admin for production recovery, trusted environment-bound worker context, a new
operator_request_key and allowlisted reason. Record these on that recovery attempt
and in site_events. UNIQUE `(parent_id,operator_request_key)` makes a lost-response
operator request return its existing attempt summary, never another lease. A failed
operator recovery stays blocked; another distinct authorized request is necessary.
Compare the stored original execution and reason for a repeated operator key; changed
intent conflicts. Current operator authorization is required for this history response.
No operator request resets automatic/execution counters or re-enables the scheduler.
All worker_context and operator identity values come from authenticated CLI/service
context; a submitted user ID or a customer browser cannot forge this authority.
Both claim services enforce section 5.2's actor insertion rules before any attempt,
counter or lease/fence allocation: current non-NULL authenticated operator for an
operator claim; NULL recovery_authorized_by_user_id for automatic recovery/ordinary
execution. The actor FK has no CHECK predicate. Its later SET NULL action preserves
historical evidence, not reusable permission: it cannot authorize another caller's
history/replay, a new recovery/execution or production action, or transfer the original
build requester. Existing current caller/worker, budget, ownership and grant gates apply.

Recovery binds server-side to the original execution row and immutable parent source,
reserved release key/release, site, target, environment, binding version and recorded
previous pointer. It may inspect DB/manifest/bytes/journal/pointer, repeat bounded
health probes, adopt already sealed exact build output only with current original
requester authorization and content eligibility at new-success acceptance,
finalize an already activated exact deployment only with current eligibility and
valid grant, or compensate that activation to its recorded prior release/absence.
It may not render, materialize new files, stage a release, initiate/repeat candidate
activation, choose another source/target/environment, consume another approval or
implicitly call an ordinary retry. Missing/partial output cannot be regenerated by
recovery. A staged-but-never-activated release may only be inspected/settled; any later
ordinary execution needs a remaining execution slot and normal eligibility. At three
executions, it settles safely failed after verified absence of activation, or blocks
if that absence cannot be proved.

Expired/revoked production approval prevents adoption as a new successful publication;
it does not revoke the original operation's narrow compensating authority. Fresh
trusted recovery ownership plus original activation evidence permits only CAS restore
to the recorded prior pointer (or verified unavailability for first deployment), under
a fresh fence. This is not a new publish/restore request or grant consumption. If a
newer operation owns/changed the target, recovery cannot overwrite it. Unknown or
failed compensation remains visibly blocked. No safe terminal failure is inferred.

Reuse existing SiteServiceException classifications for request errors; add a separate
bounded worker result vocabulary rather than passing unrecognized strings to its
current constructor (which normalizes them to database_failure). Log exception class
and fixed error code only, not message/SQL/command output. No nested transactions.

Completion replay with matching already-committed attempt and receipt returns the
recorded result and emits no duplicate event, after current trusted authorization;
it cannot mutate state. A conflicting replay or stale-worker mutation is rejected.
Recovery completion links the original producer receipt and the new recovery lease.
Adoption/finalization atomically settles parent/recovery_status/attempt/result events;
original expired execution stays expired. Verified adoption/finalization, confirmed
recorded success or verified safe failure makes the recovery attempt succeeded and
recovery_status resolved; safe failure still makes the original operation failed.
An unsuccessful recovery attempt becomes failed (or expired on timeout). Only a
retryable automatic recovery failure with remaining automatic budget sets required
and next_recovery_at; exhausted, nonretryable or operator recovery failures set blocked.
Neither outcome clears an unresolved target slot. Stale attempts
write only their own private candidates, never the sealed namespace. Unknown or
mismatched orphan remains quarantined with an event, never automatically published.

## 9. Immutable artifact contract

`release_key` is a server UUID reserved once per build job; numeric release_id is
assigned only when success is recorded. Package contract `ubo-static-site-v1`:

```text
<site_key>/<release_key>/
  public/
    index.html
    <validated-slug>/index.html
    assets/<sha256>.<allowlisted-extension>
    _release.json
  metadata/
    manifest.json
    validation.json
```

Only `public/` is web-served. Metadata is never under an Apache alias. `_release.json`
contains only contract version, site_key, release_key and a deterministic public
payload digest; no numeric business/user IDs, source data, approval IDs or paths.
HTML includes the same site/release marker and correct page title/language/viewport.
Slugs are validated segments; home is index.html; reject reserved `assets`,
`_release.json`, `metadata`, duplicate normalized routes and file/directory collisions.
No arbitrary encoded paths, query-derived filenames or generated server-side code.

Manifest fields: contract/schema version, release_key, site_key, source_revision_id,
revision_number, source_snapshot_hash, composition hash mode/version, exact component/
variant/schema versions, theme version, build_input_hash, builder_version/code SHA,
registry/toolchain digest, build profile/options, sorted file entries
`{relative_path,sha256,byte_size,mime_type}`, total counts/bytes, created_at UTC,
public_payload_hash, artifact_hash, validation summary and unresolved integration
capabilities. Source revision ID is internal metadata, never an authorization token.

Hash definitions avoid cycles: public_payload_hash hashes sorted file entries for
all public content except `_release.json`; the marker then stores it. artifact_hash
hashes the sorted entries for **all public files including the marker**. manifest_hash
hashes exact canonical manifest bytes (which include artifact_hash but omit their
own hash). validation.json has its own `validation_hash` recorded in manifest; neither metadata
file is included in artifact_hash. Verification checks exact file inventory and all
three hash layers, not just the self-reported marker. No archive-container digest is
confused with the content digest. Builder output is deterministic; metadata creation
time/release marker intentionally differs for distinct job identities.

Public facts are explicitly projected from the stored snapshot: display name, approved
public phone/email/address, approved website descriptions, selected services/areas,
public FAQ and configured section copy. Exclude generation brief, source reference
objects, transfer/escalation/notification rules, prohibited-claims instructions,
customer submissions, comments/reasons, pricing guidance not selected for public copy,
internal IDs and all session/OTP/provider material. Approved public contact details
are allowed; private-data scanning must distinguish them from forbidden fields.

Extend renderer with an explicit page render entrypoint preserving full navigation,
theme and exact component implementations; emit one complete document per page.
Do not call the combined-preview renderer once and label it a production website.
Add repository-owned static CSS; copy selected approved asset bytes into content-hash
paths. No link to authenticated previews, legacy uploads, mutable CDN objects or live
application CSS. Unknown/raw SVG is rejected until a verified sanitization contract
exists; repository-owned SVG can be allowlisted by digest. Allowed image/PDF assets
must satisfy MIME/size/rights checks and a reviewed public-use selection.

## 10. Artifact storage decision and tradeoffs

Use a small `SiteArtifactStore` boundary: write unique candidate, seal-if-absent,
inspect manifest, open verified file, verify release. It returns opaque keys, never
customer-controlled filesystem paths. The first adapter is a local immutable store;
keep a future Spaces adapter behind the same contract. Do not require an unimplemented
Spaces client for the first staging build.

| Choice | Benefit | Cost / decision |
| --- | --- | --- |
| Local sealed artifact store | Fits the documented single Apache host; no new provider credential; simple fault testing. | Host loss risk and backup need. Selected for staging-first M6, with explicit retention/backup gate before production. |
| Spaces-only | Durable remote artifact archive; transferable across hosts. | Apache still needs verified local materialization; no multi-file atomic live activation; client/credentials/permissions unimplemented. Not first adapter. |
| Local plus Spaces archive | Local atomic activation plus off-host durability. | Replication status and verification needed; do not call archive complete on upload acknowledgement alone. Future store adapter or later separately scoped durability PR. |

Proposed private store `/var/lib/ubo-site-builds/<environment>/artifacts/<site_key>/<release_key>`;
private candidates `/var/lib/ubo-site-builds/<environment>/work/<job_key>/<attempt_number>`.
These are new proposed roots, not observed directories. Environment segregates storage
and permissions; the artifact bytes contain no environment URL/credential. Future
promotion imports identical verified bytes into the production control plane/store
through an approved operation, never grants staging credentials production writes.
No build reads a production database. Legacy local assets are read-only compatibility
inputs with checksum verification; new uploaded media remains subject to Spaces policy.

## 11. Release validation and contact-form boundary

A release is deployable only after all required profile gates PASS; successful build
does not imply production launch readiness. Validation summary has profile/version,
pass/fail codes, counts, expected hash evidence and explicit deferred capabilities.

| Gate | Required assertion |
| --- | --- |
| Source | Locked current eligibility, expected snapshot hash, validated M3 composition, exact version availability, strict asset rights and tenant binding. |
| Inventory | Home entrypoint and every expected page exist; no unlisted files, duplicate normalized paths, traversal, links, hardlinks, devices, dotfiles, `.htaccess`, PHP/CGI/server-side executable content. |
| HTML/assets | Parse with network/entity loading disabled; exactly one page/main, correct language/title and marker; referenced local images/styles/links resolve; asset bytes/MIME match manifests. |
| Browser policy | Static profile CSP denies scripts/connect/object/frame/base/form submissions; repository CSS and approved images only; no inline handlers, javascript URLs, active embeds, remote tracking or service workers. |
| Privacy | Public projector is allowlist-based; forbidden-field canaries, secret-pattern scan and manual fixture review. Scanning is defense in depth, not permission to serialize entire snapshots. |
| Bounds | Maximum 100 pages, 2,000 files, 20MiB per asset, 1MiB per HTML file, 100MiB public package, 1MiB manifest, 16KiB validation summary. Fail before unbounded memory/write; reviewed profile version change required to raise. |
| Integrity/sealing | Rehash bytes, canonical manifest and complete inventory before seal, stage and activation; immutable store ownership verified; no overwrite on identity collision. |
| Integration | Preserve contact form field/required-field/submit-label configuration. `static-review-v1` keeps form inert, marks LeadHub integration unavailable in metadata/control plane and cannot be production-published. No fake success, legacy handler fallback or user-supplied endpoint. |

M7 may introduce an explicitly versioned same-origin registered-site form contract
and new production-capable build profile. That changes build-input identity and
requires new validation; it does not mutate M6 artifacts. M6 staging proves static
build/deploy/restore, not public lead ingestion or first-customer launch readiness.
No placeholder endpoint is registered. M6 production-gate code is tested with fixtures
while actual production execution remains disabled, even if approval rows exist.

## 12. Provider-neutral publisher and deployment service

`SiteDeploymentService` owns durable orchestration, authorization, retries, audit and
success requests. `SitePublisher` is the provider boundary, injected through a small
allowlisted factory. Apache/DigitalOcean paths/config never enter SiteManager SQL or
build eligibility. Array DTOs have documented allowlisted shapes.

| SitePublisher operation | DTO contract |
| --- | --- |
| `stageRelease(array $release, array $target, array $context): array` | Verified release identity/digests, opaque target binding, execution-only operation key/fence; returns immutable staged receipt, verification summary and external reference. |
| `activateRelease(array $staged, array $expectedCurrent, array $context): array` | Expected external release and fence, authorized execution attempt; recovery kind rejected. Returns activation receipt with old/new identity and observed fence, or conflict/unknown. |
| `healthCheck(array $expected, array $target, array $context): array` | Exact release/marker/probe profile, bounded phase and timeout; returns structured check evidence. |
| `inspectCurrentRelease(array $target): array` | Read-only observed release, pointer/journal fence, integrity and unknown/missing classification; no DB pointer mutation. |
| `restoreRelease(array $stagedKnownGood, array $expectedCurrent, array $context): array` | Ordinary restore execution only, same fenced activation mechanics; semantic restore marker retained in receipt. Recovery compensation uses reconcileExternalState. Authority comes from deployment service, not adapter. |
| `reconcileExternalState(array $intent, array $target, array $context): array` | Recovery-only lease for exact original execution; inspect/probe and optionally compensate its recorded activation. No staging or candidate activation. Never automatically bless an arbitrary directory or latest symlink. |

Keep staging, activation, health and inspection distinct on one interface: each phase
has a durable boundary and fault point, and this avoids one opaque “deploy succeeded”
call. A reusable HTTP checker may be composed internally; a second public orchestration
interface is unnecessary for the first adapter. DTO context contains trusted identity,
correlation, deployment/attempt IDs and fence; provider credentials are resolved only
inside environment-scoped infrastructure config.

Proposed SiteDeploymentService methods: `requestDeployment(actor,input)`,
`requestRestore(actor,input)`, `claimDeployment(worker)`, `renewDeploymentLease(lease)`,
`executeDeployment(lease)`, `completeDeploymentSuccess(lease,receipt)`,
`completeDeploymentFailure(lease,failure)`, `deploymentForActor(actor,id)`,
`deploymentsForSite(actor,site,environment,cursor)`,
`claimDeploymentRecovery(id,worker,operatorRequest?)`,
`reconcileDeployment(recoveryLease)`, `completeDeploymentRecovery(lease,result)`.
Recovery claim/renew/completion uses the same durable policy and operator-request
contract as build recovery, with a new target fence and same-operation active slot.
executeDeployment accepts execution kind only; reconcileDeployment accepts recovery
kind only. Recovery completion invokes the shared verified-success implementation
within its single result transaction for an already activated result, or settles
verified compensation/blocking. Parent, recovery_status, current recovery attempt and
result events commit together; no nested/separate success commit. It never consumes a new
grant or stages/activates the candidate. Known committed success is read before any
compensation and is not replayed as a fresh success transition.

Both request methods use the exact identity/payload and replay protocol in sections
7/13. They return `{deployment_id, deployment_key, request_key, existing_operation,
replayed, operation, operation_status, recovery_status, operation_release_id,
target:{target_id,environment,current_deployment_id,current_release_id,pointer_version,
active_deployment_id,reconciliation_status}, is_current}`. Matching replay sets both
indicators true; new insertion sets both false. is_current compares operation ID to
target.current_deployment_id, not release ID or historical success. All current target
values are DB observations from this read, not new external health claims. History
does not imply the release is still live. Worker cannot choose another release/target.
Restore uses a new audited deployment, never a build-job retry.

## 13. Deployment states and transaction protocol

Statuses: `requested`, `running`, `artifact_staged`, `activation_attempted`,
`health_check_pending`, `succeeded`, `retry_wait`, `failed`,
`reconciliation_required`, `cancelled`. Phase transitions and health observations are
audited; an expired attempt cannot advance them. “Current”, “previous”, “superseded”
and “restored” are history/read-model relationships, not destructive rewrites of a
successful deployment status. A restore succeeds as a new row with operation restore.

Recovery bookkeeping is independent: none/resolved -> required when unresolved effects
are detected, required -> running -> resolved, or required/running -> blocked;
blocked -> running only through a new authorized operator recovery
claim. Transient failure may return required with a due time while automatic budget
remains. Unresolved operation status stays reconciliation_required and target stays
required/blocked with active_deployment_id retained. Recovering an acknowledged DB
success preserves succeeded/history; recovery_status can report an actual external
drift without rewriting that outcome. Neither recovery_status nor a replay response
allows a new execution claim.

### Request transaction: replay before new-operation gates

1. Validate request shape/contract and canonical values. Begin a **fresh** transaction;
   acquire SiteManager's site lock first, then locked current actor/tenant authorization.
   Resolve target only with `(target_id,site_id)` and enforce current target access:
   internal Admin/Super Admin for staging, Super Admin for production. Inactive/lost
   role, missing site/target or forbidden scope is denied without revealing history.
   Runtime enabledness, target readiness and historical grant validity are not access
   permissions; those are new-operation gates later in this protocol.
2. Under that root lock, read the existing identity `(site_id,target_id,request_key)`
   and original payload. This is a scoped nonlocking read before revision/approval
   mutation locks. All target/operation writers hold the same site lock; the fresh
   transaction establishes its first consistent read **after** that lock, never from
   an earlier preflight snapshot. This preserves site-first ordering without taking
   a job/target write lock before revision/approval locks. History-only return needs
   no lifecycle mutation locks. Read target state in the same protected snapshot.
3. If found, compare the section 7 canonical original payload/hash. A mismatch returns
   safe conflict. An exact match returns the existing-operation DTO and its actual
   pending/succeeded/failed/reconciliation-required outcome. Commit a read-only result
   with no intent, lease, grant consumption, event, retry, file action or pointer write.
   Do **not** run today's expected-pointer, release freshness, binding-readiness or
   approval expiry/consumption checks as though this were a new operation.
4. Only if identity is absent, lock relevant revision/approval/tenant rows, then target,
   then operation/attempt rows when needed, in the existing mutation order. Apply all
   current source/release/restore eligibility, exact configuration binding, enabled
   environment, expected current deployment/pointer version and production grant
   preconditions. Insert original canonical payload, policy snapshot and one requested
   event atomically. Unknown/foreign referenced release/restore/approval is denied.
5. Site locking normally serializes duplicate inserts; the UNIQUE request identity is
   the final database defense. On duplicate insert race, roll back the entire losing
   transaction and any event work, start a fresh site-first authorized lookup, and
   compare to the winner. Return matching history or payload conflict. Do not rerun
   stale new-operation pointer/grant checks **before** checking the winner, even if
   it already succeeded. Deadlock retries likewise restart at authorization/lookup.

No request-key possession bypasses authorization. A foreign target/key combination
is never searched globally; a foreign key cannot retrieve another site's row. If the
same UUID is absent in an authorized target namespace it is merely a potential new
identity, subject to all new-operation ownership gates, with no foreign existence
disclosure. Lost current access denies even an otherwise identical replay. Matching
history may be returned after pointer advancement, grant consumption/expiry/revocation
or target disablement; none of these read responses reauthorize publication.

### Execution and recovery phases

1. Request transaction follows the replay/new-intent protocol above. Only a newly
   created or explicitly eligible queued operation may proceed to execution claim.
2. Execution claim transaction: require execution_count below its immutable limit and
   no unresolved recovery; acquire target active_deployment_id slot if NULL or already
   this safely reconciled operation, check request pointer precondition, and issue new
   execution attempt/lease/fence. Queued/replayed requests do not themselves own a slot.
3. Outside transaction: stage/verify immutable release and candidate health. Persist
   each phase/result in a short guarded transaction.
4. Activation-authorizing transaction: recheck live eligibility/grant/config/lease,
   reserve previous pointer, consume exact production grant when required, record
   activation_attempted with fence; commit. Outside transaction, helper compares actual
   old pointer and fence and performs the atomic switch.
5. Outside transaction: active health and integrity checks. Short result transaction
   calls SiteManager's guarded pointer method; it checks exact lease/fence/target slot,
   expected pointer/version, current eligibility/grant and fresh health receipt, then
   records success, pointer, event and releases slot atomically.
6. Failed active health/final authority: compensate outside transaction to verified
   previous release using the still-valid current execution lease/fence and expected candidate pointer, then
   verify rollback. Only after confirmed compensation mark failed and free the slot.
   Unknown/failed compensation remains reconciliation_required and blocks new work.
   For a first deployment with no previous release, compensation removes only this
   operation's current link and verifies the preconfigured unavailable response;
   the DB pointer stays NULL. Never invent a successful previous deployment.
7. If execution lease/deadline expires or the process dies, stop its writes. An
   independent recovery claim follows section 8 even when execution_count=3. It gets
   a new token/deadline/fence and references the original execution, holding only the
   same operation's target slot. Observe committed DB outcome first; finalize verified
   eligible prior activation or compensate under bounded recovery authority. Recovery
   failure retains blocking; it cannot fall through into step 2 as execution four.

No external operation runs inside any lifecycle DB transaction. Holding a bounded
**external** per-target helper lock around activation/health/DB result coordination
does not mean holding a SQL transaction through a network call. If the DB is down,
the helper journals outcome, closes/relinquishes only after bounded compensation or
records unresolved state; it never guesses DB commit success after a lost connection.
When commit acknowledgement is missing, no rollback is attempted until a fresh
authoritative DB read resolves whether success committed. A dead worker's execution
token/fence is never used by its replacement, including for compensation.

## 14. Current pointer and existing publication lifecycle

`site_deployment_targets.current_deployment_id` is the only current-deployment
authority for that site/environment. Lock target and site; success CAS includes
pointer_version and active_deployment_id. Failed/pending operations cannot replace it.
Pointer update, succeeded row, health linkage and site_event commit together. Rollback
of that transaction restores the old pointer. Previous successful deployment remains
referenced on the new operation and keeps its original success evidence.

Staging success changes **only the staging target pointer**. It does not set site
active, revision published, or sites.current_published_revision_id. Those existing
global fields represent canonical production publication, so a staging candidate
cannot overwrite what production serves. This resolves the schema's lack of an
environment on the global revision pointer without weakening it.

Proposed `SiteManager::recordVerifiedDeployment(connection, evidence)` owns target
pointer mutation; only SiteDeploymentService calls it inside the result transaction.
For an eventually authorized production success it calls a narrowly gated
`SiteRevisionManager::applyVerifiedPublication` to supersede previous published state,
mark the selected revision published, and atomically update the global site pointer
and active state. Ordinary lifecycle/approval APIs remain unable to publish. Preserve
one published revision uniqueness by superseding old before marking new in the same
transaction. Preserve first published_at on republish; deployment history supplies
each subsequent time. Same revision/new builder only changes deployment pointer.

Production restore may republish a historically superseded revision only through the
verified restore capability. It does not revive superseded customer approvals. The
new restore authorization and known-good history explain that exception. An active
site keeps its serving state while successors go through review; M6 lifecycle-owner
extensions must preserve that behavior instead of demoting a live site when new
customer/internal requests are made. Test these extensions even while production is
disabled. No direct publication SQL in routes, build service or publisher adapter.

## 15. Staging and production authority

Staging target provisioning/enabling requires a separately approved M6 staging workflow
and recorded binding. This planning PR authorizes neither it nor an app deployment.
Once that workflow is authorized, active internal Admin/Super Admin may request builds
and staging publish/restore for the exact target. Workers have only that environment's
OS/DB/storage capability. A query parameter cannot select production credentials.

Production needs **all** of: separately enabled production runtime policy (default
deny), permitted production-capable artifact profile/integration readiness, active
Super Admin actor, site/revision or restore eligibility, enabled production target,
and exact durable grant. Customer website approval, internal content approval, a
staging success, a CLI flag and DomainManager state are never production approval.

Proposed `SiteApprovalManager::approveProductionRelease` creates the existing
production approval decision and section 5.9 binding in one short transaction.
Grant binds site, revision, release ID, artifact hash, target/environment, config
binding version, operation publish/restore and expected current pointer/version.
TTL is at most 24 hours; no blanket/unlimited release-family grants. Only current
active internal Super Admin can approve/revoke; an internal Admin may request review
but cannot approve production. No customer production UI. No grant is created now.

Worker rechecks decision type/state/revoked_at/expiry, approver's current Super Admin
authority, exact binding and runtime environment at execution claim, activation authorization
and success. Consumption binds one deployment operation, permitting only its fenced
eligible finalization/retries while the grant is still valid. Changed release/hash/pointer/
binding, expiry, revocation or role loss rejects unexecuted work. Expired/revoked grant
after activation cannot be used to declare success: restore previous verified pointer
under the operation's bounded compensating authority, otherwise block/reconcile.

There are three distinct checks: current caller access governs request-history replay;
valid production grant governs new execution and finalizing an uncommitted activation;
fresh trusted recovery lease plus original activation evidence governs bounded
compensation. Consumed/expired grants are not consumed again on matching request
replay. An already committed success is historical fact, not invalidated by a grant
expiring later. Recovery after expiry/revocation cannot initiate a candidate activation.

In-memory/raw grant IDs are not capabilities. The deployment service loads authority
from DB, and the restricted helper validates the committed operation/fence against
trusted control-plane state. Durable grant does not override the separate production
environment enablement or M7 production-readiness requirements.

## 16. ApacheDigitalOceanSitePublisher and activation layout

Initial adapter targets the documented Linux/Apache DigitalOcean-style environment;
it does not require a DigitalOcean API call to switch static files. Proposed layout:

```text
/var/www/ubo-sites/staging/sites/<site_key>/
  releases/<release_key>/public/...
  staging/<deployment_key>-<attempt_number>/...
  assets/<sha256>.<allowlisted-extension>
  current -> releases/<release_key>/public
/var/lib/ubo-site-publisher/staging/<site_key>/
  target.lock
  fence.json
  operations/<deployment_key>/<attempt_number>.json
```

These roots are separate from `/var/www/ubo-repo`, `public/app/uploads` and marketing.
Stage into a private sibling directory on the release filesystem, verify, seal and
rename into a new release directory only if absent. Keep metadata in private storage;
web release contains public files only. Never rsync/copy over live current files.

Create temporary sibling symlink with validated relative target, then rename it over
`current` on the **same filesystem**. Do not implement unlink-then-link or rely on
ambiguous `ln -sfn` behavior. fsync new files/journal and parent directories in the
helper's durability protocol; power-loss recovery must inspect both journal and link.
Atomic namespace replacement is not a multi-request consistency guarantee: an old
HTML request can overlap a new asset request. Use content-addressed assets and retain
referenced previous assets in a read-only per-site asset pool/alias, verified by digest,
until no retained release references them. Cross-site pools/aliases are not permitted.

Runtime root is an approved, fixed staging-only vhost serving `current`, with a
separate private candidate probe binding. Choose the actual host/certificate in the
authorized infrastructure gate; do not claim one already exists or bind customer
domains. Root-based vhost avoids the current renderer's root-relative URL problem
with arbitrary path-prefix aliases. Generic domain registration/resolution remains M7.

Vhost/config provisioning is an operator-owned reviewed template under proposed
`infrastructure/apache/site-static-staging.conf.example`; ordinary deployment does
not generate vhosts, change DNS, issue certificates or reload Apache. Initial/new
binding config uses fixed arguments, syntax validation, controlled installation and
graceful reload via a separate restricted helper. On configtest failure, leave old
config/current untouched. Uncertain reload is reconciled against served identity.

Static directory policy: approved symlink following confined to operator-owned
directories, `AllowOverride None`, no indexes/MultiViews/CGI/includes/PHP handlers,
no fallback into app routing, GET/HEAD only, deny private/dot/server-side files, exact
public asset alias, noindex for staging, private access control for the whole staging
site and its assets. Apache documents that symlink options alone are not a security
boundary; containment and exclusive write ownership are required
([Apache core options](https://httpd.apache.org/docs/2.4/mod/core.html#options)).

Proposed identities: builder owns only private candidate area; restricted publisher
owns release/pointer/journal area; Apache account has read/traverse only. Operator
creates config and service accounts. Sealed files 0440 and directories 0550 with
explicit Apache-readable group/ACL; publisher-controlled parent prevents tenant or
PHP writes. Actual user/group names and ACLs must be validated, not assumed to be
www-data. PHP web user gets no arbitrary shell/sudo, config write or production root.
Logical immutability still requires digest verification because OS owner/root could
change permissions; do not claim chmod alone is tamper-proof.

## 17. External fencing and separation from UBO app deployment

Use a **new restricted site-publish helper**, proposed
`infrastructure/deployment/ubo-site-publish`, installed only by an approved operator.
It accepts an allowlisted operation and machine-generated IDs via a structured request,
never shell fragments, customer text, paths, hosts or arbitrary executables. Target
binding maps IDs to operator-owned paths/vhost/probe configuration. Each environment
has separate credentials and allowed roots. Do not grant it Git/migration privileges.

Per-target OS lock plus durable monotonic fence journal serializes external changes.
Before any mutation, helper verifies committed current operation, unexpired lease,
fence, expected old release and target binding. It rejects fences below its durable
high-water mark and replays an equal fence only for the same operation/phase.
Persist accepted fence before mutation; after a crash the intent/link pair is inspected.
New worker/reconciler must acquire helper lock and install newer fence before recovery.
A resumed old worker cannot overwrite a new winner even if its local lease memory says
otherwise. It cannot bypass helper to write current. Database checks alone do not
protect external state. Helper context includes attempt_kind, original execution ID
and permitted recovery disposition loaded from committed control-plane state. A
recovery fence cannot authorize stage/new activation commands; it permits observation
and only CAS compensation to the original previous pointer or absence. The helper
must establish that the original success did not commit before compensation, never
infer that from a lost acknowledgement; unknown DB state blocks compensation.
All replacement recovery claims use fresh
fences, including after execution three or a prior recovery crash.

The helper holds its external lock across the bounded activation/health/result protocol;
it does not hold an open SQL transaction through external work. Lease-expiry takeover
waits for that lock or terminates the isolated old helper through an approved supervisor
protocol; it never concurrently mutates current. Revalidate immediately before switch,
and after external work before DB success. A lease/grant can expire between a check
and the rename; that attempt cannot commit success and must compensate/reconcile.
Document the activation-authorizing transaction as the linearization point for the
specific external attempt, rather than claiming impossible continuous SQL locks.

`/usr/local/sbin/ubo-stage-deploy` remains solely the UBO PHP application deployment
wrapper. It owns repository update/lint/Apache application deployment. Site publishing
consumes an already built immutable release and cannot invoke it. Likewise
`ubo-stage-migrate`, historical deploy-staging.sh, marketing aliases and legacy
DomainManager are not site publishing adapters. Deploying later M6 application code
to staging and exercising customer-site staging releases require distinct evidence.

## 18. Host-contract verification required before adapter activation

This planning task inspected **repository evidence only**. Symlink activation is the
recommended Linux/Apache design, conditional on these explicit tests; it is not a
verified fact about the current host. There is no authorization to resolve these
unknowns by SSH, HTTP probes or configuration changes now.

| Future approved check | Required result / stop condition |
| --- | --- |
| Apache vhost/modules/handlers | Approved staging-only binding, TLS and access control; no inherited PHP/proxy handler or application rewrite; unknown config blocks enablement. |
| Filesystem/mount semantics | Sibling pointer replacement on local same filesystem; persistent fsync support; no unsupported network filesystem/cache behavior; fault tests prove atomic observed replacement. |
| Symlink policy and path access | Current and candidate resolve inside site root; operator/publisher ownership, Apache read access and no web/tenant writes; malicious link race rejected. |
| Asset retention binding | Per-site content-addressed pool serves previous retained assets; hash conflict fails; no cross-site traversal or unlisted-file exposure. |
| Worker runtime | Exact PHP/builder SHA, native MySQL prepares, UTC clock, helper supervisor, external locks/fences and bounded timeouts verified. |
| Health path/cache | Origin/candidate and active probes identify expected site/release; redirects/auth pages/CDN stale responses cannot pass. |
| Recovery and backup | Old release retained, activation/DB disconnect/kill/health failure exercise restores or visibly blocks; local backup/retention documented. Production requires separately approved durability solution. |
| Config actions | New wrapper policy/configtest/reload approval distinct from app deploy wrapper; no broad sudo added. |

If these fail, disable target and revise adapter design in a reviewed PR. Do not quietly
fall back to in-place mutation, application wrapper, broad permissions or a different
provider. No claim of staging/runtime PASS belongs in the planning PR.

## 19. Health-check contract

For staging, use operator-configured candidate and active origin HTTPS probes, plus
an external-vhost check from the authorized validator. Actual host/IP comes from
binding, never from customer input or artifact data. Pin permitted network destinations
and TLS SNI/Host; verify certificate; no arbitrary URL fetch/SSRF, credential logging
or production endpoint fallback. Candidate private binding uses the same static policy.

Each success phase checks: HTTP 200; exact expected Host binding/site_key/release_key;
`_release.json` payload digest; marker on home and expected page title/content sanity;
one CSS and one referenced media asset when present, verified digest/MIME/size; no
application login/error page. Origin inspection verifies full artifact hash independently.
An echoed arbitrary Host header or generic 200 is insufficient.

Timeout policy: 2-second connect, 5-second request, 30-second phase deadline, up to
three probes separated by one second within the deadline; require two consecutive
active PASS observations no more than 30 seconds before the result transaction.
Canonical slash URL is supplied directly; redirects are disabled for success probes
(a redirect is failure), so a login or wrong-site redirect cannot be accepted. Send
cache-bypass headers and require staging no-store on marker/HTML. No CDN is enabled
for this first adapter. Store only safe check DTOs, never full response bodies.

The absence probe for first-deployment compensation is a separate rollback profile:
require no current link and the operator-configured unavailable response (503), with
no site release marker. Record expected_absent=1; it proves recovery to the previous
unpublished condition and can never satisfy an active deployment success check.

`observed_artifact_hash` in health evidence comes from trusted adapter verification,
not the public marker (which contains the distinct non-circular payload hash). Health
does not prove LeadHub/contact routing. Form unavailability remains explicit.

## 20. Reconciliation and authority during disagreement

DB current pointer is the last **verified successful commitment**; external inspection
is authority for what is physically serving now. Neither automatically overwrites the
other. Disagreement marks target required/blocked and exposes both observations to
internal operators. Keep failed/pending external state distinct from last-known-good.

Reconciler first resolves committed DB outcome, then, when work remains, claims a
separate recovery attempt under section 8, independent of execution budget. It retains
the existing operation identity/target slot, allocates new lease/deadline/fence, inspects
outside transaction and records a guarded result. It never calls ordinary execution
as recovery. A new audited repair/restore deployment when no recoverable operation
exists is a **separate authorized new request**, with all new-operation preconditions;
reconciliation cannot silently create it to bypass an exhausted or blocked operation.
Repeated same receipt/result key is idempotent; a distinct recovery claim has its own
history without duplicating the original outcome. No arbitrary path, newest timestamp
or HTTP 200 can create success.

| Failure window | Resolution |
| --- | --- |
| Built artifact, DB completion lost | Read committed result first without reauthorizing the original requester for historical success. Even after execution three, trusted recovery may inspect exact sealed bytes/producer receipt; new adoption requires current original-requester authorization plus content eligibility. Missing/unacceptable output is not rerendered; quarantine/settle safely or block. |
| Build requester loses authority before claim or success | Safely queued requested/retry_wait job cancels once with no new execution (section 8). After a claim, preserve prior work/evidence and deny new success, then safely fail/recover. Independent recovery remains available; already committed success remains intact. |
| Deployment intent, worker never ran | Eligible requested row remains claimable by ordinary execution. An expired claimed execution has its outcome resolved first; retry only within remaining execution budget. |
| Staged release, no activation | Recovery inspects receipt/current state and records proven nonactivation. It cannot stage/activate. An ordinary eligible retry needs execution_count below limit; at three settle safely failed, or block if outcome uncertain. |
| Config/reload success, DB record lost | Ordinary publish does not reload; for approved binding provisioning inspect installed config digest, actual served target and receipt. No assumption from process exit; disable target until reconciliation. |
| Symlink activated, active health failed | CAS compensation to previous release, then rollback health. Mark failed only after prior active identity is verified; otherwise reconciliation_required and target remains occupied/blocked. |
| Health passed, worker died before DB result | Recovery reads DB commitment first. If uncommitted, new recovery lease/fence repeats health and rechecks current approval/eligibility to finalize the prior activation once; otherwise bounded compensation. Execution exhaustion does not prevent this claim. |
| DB pointer success, external different | Freeze new activation; preserve successful history and record drift. If an identifiable authorized in-flight operation exists reconcile it; otherwise create audited repair/restore to last verified release, never bless unknown external state. |
| DB commit outcome unknown | Read authoritative transaction result by operation/original execution ID before compensation. If committed, retain its success (which may no longer be current after later work); never rollback it on lost acknowledgement. If absent, recover prior effects; if DB unavailable/ambiguous, block without compensation. |
| External equals DB after compensation but prior DB finalization failed | Reverify rollback receipt and pointer; mark failed/free slot exactly once. No extra success deployment. |
| Newer deployment won; old worker returns | Reject token/fence/expected-pointer mismatch. Old worker may not rollback newer live release. Its private artifact is quarantined for retention review. |
| Orphan staged directory | Match exact job/deployment receipt and lease; retain for bounded recovery. Unknown or expired orphan never becomes current automatically; cleanup only under separate retention policy. |
| Recovery crash/expiry or automatic budget exhausted | New recovery row replaces only expired ownership, with new token/fence; original execution_count never changes. After two unsuccessful automatic claims block visibly; a fresh authorized operator_request_key may claim one more bounded recovery, without schema change/reset. |
| Grant expired/revoked after activation | Never initiate candidate publication or finalize an uncommitted success using that grant. If original success already committed, retain it; otherwise fresh recovery may only compensate recorded effects under section 8's narrow authority. |
| First deployment, previous pointer NULL | Fresh recovery verifies no committed success, removes only the matching attempted current link, and proves no link plus configured 503/expected_absent. Never fabricate a previous successful release. |

## 21. Restore contract and honest failure semantics

Restore is a **new deployment** of an existing immutable release that previously
succeeded on the same site and same environment/target. It is not a content edit,
history rewrite, build rerun, deletion, or a call to createRestoreCandidate.

Require current internal operator authority, enabled clean target, exact expected
current pointer/version, a successful historical source deployment with health
evidence, unchanged verified artifact, same site and current business association,
current public asset rights, permitted runtime/profile and explicit restore request.
Production additionally requires a fresh exact release-bound restore approval. A
staging-only success cannot be a production known-good source. Never restore to
cross-site/unknown/tampered or now-prohibited content. Older customer approval may
have been superseded by normal progression: restore authority is the explicit new
operation plus known-good evidence, not retroactively un-superseding that approval.
Ordinary stale-build freshness is bypassed **only** for this narrowly scoped restore;
site suspension, tenant mismatch, explicit rights/security prohibition and environment
gate are never bypassed.

New row records operation restore, restored_from_deployment_id, target release and
previous/current expected deployment, actor/reason code/correlation. Stage and validate
candidate without changing current; activate atomically, verify active health, then
commit pointer/success/audit. Failure before activation leaves old active identity
untouched. Failure afterward requires verified compensation to previous current.

An atomic symlink switch cannot guarantee that no request ever sees the candidate
before the active health result. Nor can any design guarantee rollback when the disk
or host is unavailable. Required semantics are precise: old immutable bytes and DB
pointer are preserved until success; terminal failed means prior active release was
verified restored; unresolved post-activation failure is reconciliation_required,
never a false “failed safely/current unchanged” claim. Tests must observe both DB and
external pointer. This also applies to ordinary deployment failure.

## 22. Security findings and concrete controls

| Threat / finding | Required control |
| --- | --- |
| Traversal/arbitrary write | Canonical server-generated UUID paths; bounded slug grammar; deny absolute/encoded/dot/alternate-separator paths and reserved names; root-relative handle operations, no text interpolation. |
| Symlink/hardlink escape | Reject package links/special files; inspect with no-follow semantics; non-tenant-writable parents; only helper-created current link; verify target containment under external lock. |
| Malicious component data | Existing version/schema validation and escaping; public projection; no stored executable markup; per-page HTML/CSP validation; untrusted SVG fail closed. |
| Command/config injection | Fixed executable/argv and structured helper requests; IDs validated; root-owned binding/config templates; no shell command built from customer text. |
| Cross-site/tenant release | Composite FKs plus locked site/association/business checks at request/execute/restore; no customer-supplied storage locator. |
| Stale approval | Exact revision/release/hash/target/operation/pointer/binding grant, expiry/revocation/current approver checks; new builder artifact needs new grant. |
| Worker replay/lease stealing | Random hashed token, trusted worker principal, current-attempt CAS, expiry and max runtime, durable external monotonic fences and no direct pointer write rights. |
| Artifact tampering | Exact inventory/file/manifest digests at seal/stage/activation/reconcile; immutable successful DB metadata; corruption blocks even previous known-good restore. |
| Unauthorized restore | Same target successful source, rights/tenant recheck, new audited operation, fresh production restore grant; no general force flag. |
| Secret/private-data inclusion | Explicit field and file allowlists; no raw snapshots/brief/comments/session/config copying; leakage canaries and bounded scanning; no credentials in public marker. |
| Unsafe logs | Fixed error codes, safe summaries, IDs/hashes/counts only; discard stdout/stderr bodies; no SQL, paths with customer text, tokens, credentials, URLs with queries or response bodies. |
| Environment confusion/escalation | Separate DB/OS/storage identities, binding roots and helpers; production deny default; environment assertion at every boundary; no fallback from staging to production. |
| SSRF/cache/wrong-host false health | Operator allowlisted origin and TLS Host/SNI, redirects disabled, release marker plus byte hashes, fresh no-cache probes and private binding. |
| Resource exhaustion | Bounded input/HTML/assets/files/manifest/time/memory, three executions, at most two automatic recoveries, separately authorized bounded operator recovery, one active target operation, disk-space preflight and isolated worker limits. |
| Live-state race | Site/target locks + pointer CAS + external expected-pointer/fence; unknown state freezes target; never compensate over a newer winner. |
| Legacy publication shortcut | DomainManager/legacy publish status excluded from all success and authorization checks; no use of UBO deployment wrapper for sites. |

## 23. Observability and audit

Reuse `site_events` in the same transaction as each durable state transition; optional
business activity summaries derive from committed site events and never carry private
build metadata. No new generic logging/outbox platform is required for M6. Job state
is durable work intent. Audit insertion failure rolls back DB mutation; external
effects then require reconciliation.

Required event types: `site_build_requested`, `site_build_started`,
`site_build_succeeded`, `site_build_failed`, `site_build_cancelled`, `site_build_retry_requested`,
`site_deployment_requested`, `site_deployment_started`, `site_release_staged`,
`site_activation_attempted`, `site_deployment_health_checked`,
`site_deployment_succeeded`, `site_deployment_failed`,
`site_reconciliation_required`, `site_reconciliation_completed`,
`site_restore_requested`, `site_restore_succeeded`, `site_restore_failed`,
`site_production_approval_granted`, `site_production_approval_revoked` and
`site_production_approval_consumed`. Add `site_recovery_requested` (operator claim),
`site_recovery_started`, `site_recovery_completed` and `site_recovery_blocked` with
attempt kind, original execution ID, total/kind counters, trigger, policy version,
deadline and disposition. Operator request and lease claim share one transaction;
each new claim has its own audit, including after a prior recovery crashes. Restore
events accompany deployment events with the same operation ID. Preserve expired/
abandoned execution and recovery attempts even after successful recovery.

Exact deployment/restore request replay is an authorized read: no requested, success,
approval-consumed or retry event. Lost-response duplicate completion/recovery records
the result once by immutable operation/attempt/result identity; it does not suppress
audit of genuinely distinct recovery attempts. Payload conflicts never mutate the
original operation, actor, correlation or request. Automatic and operator recovery
failures remain visible independently from a prior committed successful outcome.

Build requester denial is not an execution start. site_build_cancelled records only
the guarded safe queued-cancellation transition, with reason requester_not_authorized,
job ID, previous/next status and unchanged counters; duplicate polls emit nothing.
Do not attribute the cancellation to the revoked user: use the trusted system actor.
For post-claim denial, report the real prior execution and candidate/quarantine or
recovery disposition with bounded authorization reason; never label that as no work
performed. Committed success/history reads remain unchanged and create no new effects.

Each event has site/revision, actor or system identity, UTC time, result, stable
correlation_id and bounded allowlisted metadata: job/deployment/release/target/attempt
IDs, environment, source/result status, fence and pointer version, digests, counts,
duration, safe failure code, previous/restore source IDs and health check keys.
No raw token, OTP/session/cookie, approval comment, feedback, business/customer text,
snapshot/brief, provider credential, environment file, full response or command/SQL
output is logged. History DTOs omit private storage keys and lease token hashes.

Operational views expose queue age, lease expiry, execution and recovery counts/limits,
recovery_status, automatic budget exhaustion, required operator action, duration, failures by
category, target mismatch and last health time. The last successful pointer and current
observed condition are displayed separately; a historical success must not hide drift.
Build views distinguish authorization-cancelled queue entries, post-claim authorization
failure and required recovery; none implies that old successful releases were revoked.

## 24. Implementation test matrix

These are future acceptance tests, **not results of this planning task**. Proposed
suites use existing standalone PHP style, deterministic clock/token/storage/publisher
fakes, disposable local filesystem, real MySQL with native PDO prepares, and a later
explicitly authorized isolated Apache staging fixture. Every failure test asserts
unchanged publication pointer/history and safe events, not merely exception text.

| ID | Scenario | Exact expected result | Required layer |
| --- | --- | --- | --- |
| B01 | Material eligible revision; exact customer + internal approvals | One job/release, valid hashes/files, approval/published pointers unchanged. | Service, DB, artifact |
| B02 | Non-material with one effective earlier customer approval | Allowed with exact internal approval; missing/ambiguous baseline denied. | Service, DB |
| B03 | Missing/wrong-site/rejected/unclassified/customer-only/restored revision | Denied with safe code, zero job/intent writes. | Service, DB |
| B04 | Suspended/archive/pending site; inactive association/business/module | Denied at request and execution after mid-flight change. | Service, DB |
| B05 | New material or newer approved successor; unclassified successor | Old candidate rejected; classified non-material draft alone follows section 3 rule. | Service, DB |
| B06 | Sequential duplicate and simultaneous separate PDO requests | One identity/job/release_key/request event, both callers get same job. | Real MySQL concurrency |
| B07 | Failed transient storage; retry; exhausted execution budget | Same job, execution numbers 1..3 retained, scheduled delays, no fourth execution; separate recovery claims may raise total attempt_count above three. | Service, DB |
| B08 | Crash before output / after seal / before DB commit | Expired attempt recorded; reconcile/adopt exact valid candidate once, or quarantine; no success inferred. | Fault injection, DB, filesystem |
| B09 | Expiry/heartbeat race/late worker completion | Old token cannot renew/finish; newer attempt unaffected. | Clock, real MySQL concurrency |
| B10 | Canonical input ordering, NULL/integers/Unicode, asset order | Golden hashes stable; changed content/options/version/SHA changes identity; actor/environment/time does not. | Pure unit |
| B11 | Repeated success and conflicting output for release_key | Matching completion no duplicate event; differing bytes refused; sealed release unchanged. | Service, filesystem |
| B12 | Tampered/missing/additional file, false MIME, digest mismatch | Seal/stage/restore fails; no deployable artifact. | Artifact, filesystem |
| B13 | Private snapshot fields/brief/comment/OTP/token/secret canaries | No canary in public/metadata artifacts or logs; approved public contact fields preserved. | Artifact, leakage fixtures |
| B14 | Multi-page layout/nav/assets, broken link, HTML parse/CSP | Correct distinct documents and full nav; broken refs/scripts/PHP/.htaccess fail. | Renderer, artifact/browser |
| B15 | Unknown/expired rights, wrong business asset, malicious SVG/link/path, bounds | Denied without arbitrary file access/write; no outside-root mutation. | Artifact, security |
| B16 | Inert lead form and server-owned profile | Fields retained, no POST action/no fake success; production cannot use static-review profile. | Renderer, service/browser |
| B17 | Builder SHA mismatch/dirty runtime; original requester loses internal authority before locked claim | Builder mismatch/dirty runtime: configuration denial, no execution. Requester revocation commits first: no attempt/counter increment, lease/BuildInput, start event or external work. Safe queued job cancels once with requester_not_authorized; repeated polls do not repeat event. Uncertain prior effects remain reconciliation_required. | Worker/fake DB behavior; real MySQL claim gate |
| B18 | Active internal original requester plus trusted worker; Admin changes to Super Admin and vice versa | One ordinary claim and one start event; current permitted authority suffices despite historical actor_type. Untrusted/environment-mismatched worker cannot claim or cancel; internal requester still cannot bypass lifecycle/input eligibility. | Service/worker + fake DB; real MySQL |
| B19 | Original requester inactive/deleted/NULL FK, with retained actor_type | Each variant denies ordinary claim with B17's zero-execution and safe cancellation/recovery disposition; no permission inferred from retained history. | Parameterized service/fake DB + real MySQL FK |
| B20 | Original requester has only business-scope Owner/Admin or is_owner | Internal policy denies claim regardless of customer membership; zero execution and exact B17 disposition. Internal-scope Admin/Super Admin is the separate permitted case. | Authorization/fake DB + real MySQL |
| B21 | Requester revoked before retryBuild, or after requeue before a later claim | Current retry caller is independently authorized; revoked original requester cannot be replaced. Retry request denied or queued retry cancels safely at claim; prior attempts preserved and no additional execution/budget consumption. | Service/fake DB + real MySQL |
| B22 | Claim versus user deactivation/deletion, role removal or scope/name change | Independent connections/barriers establish both serial orders. Revocation first denies; claim first may start work and later success rechecks authority. Rollback/deadlock retry reauthorizes, with no cached/request-time-only bypass. | Real MySQL concurrency; fake DB is behavioral only |
| B23 | Authorized claim then requester revoked before ordinary success or recovery adoption | No newly successful release/validation/event; retain actual execution count and output evidence. Quarantine/verify safely then fail, or retain recovery-required state. Lease renewal cannot bypass acceptance checks; no claim that external work never occurred. | Service/fake DB + storage faults; real MySQL acceptance race |
| B24 | Requester revoked/deleted after execution three; recovery or recovery restart needed | Where inspection remains necessary, fresh recovery is claimable at (total=4,execution=3,recovery=1) under independent recovery policy; safely inspect/quarantine/fail. A known committed result needs no new claim. Uncommitted adoption denied without requester authority; unknown DB outcome blocks compensation, no fourth execution/reset. Preserve auto-limit/operator and stale-token rules. | Service/fake DB + real MySQL/filesystem fault |
| B25 | Build success committed before requester revocation/deletion, including lost acknowledgement | Immutable job/release success retained. Current authorized worker/reader returns recorded result before new-success requester gate, with no new success event/effects. Deployment/restore access and grant semantics remain independent. | Service/fake DB + real MySQL commit race |
| B26 | Worker/caller substitutes requester on request/claim/retry/duplicate, or uses recovery lease for execution | Reject override/recovery-kind execution; original requester/identity/counters unchanged. Different authorized retry caller cannot inherit or transfer execution authority. | Parameterized authorization/worker/fake DB; real MySQL identity constraints |
| D01 | Approved staging fixture publish | Candidate pass, atomic switch, two active health passes, one success/pointer/event transaction. | Service, DB, Apache |
| D02 | Production disabled or no grant/customer approval only | Zero provider calls, pointer unchanged. | Service, DB |
| D03 | Wrong release/hash/site/env/operation/binding/pointer grant | All denied before activation; no grant consumption. | Service, DB |
| D04 | Expired/revoked grant or approver role lost at each boundary | Before activation deny; after activation compensate or require reconciliation, no success. | DB concurrency, adapter fault |
| D05 | Two distinct request keys queued on same expected pointer | One active slot; first verified success wins, second conflicts and cannot move pointer. An identical-key replay instead follows D19–D26. | Real MySQL + helper concurrency |
| D06 | Different sites/environments concurrent | Independent target slots; no cross-site pointer/storage visibility. | DB + helper |
| D07 | Stage copy fails/disk full/permission denied | Old current untouched; bounded retry only if safe; no partial directory served. | Filesystem fault |
| D08 | Configtest failure/reload uncertain during target provisioning | Prior config/current preserved; disabled or reconciliation-required binding; no false deploy success. | Isolated Apache fixture |
| D09 | 200 wrong site/release/login body, stale cache, redirect, bad TLS/asset/timeout | Health fails every case; generic 200 cannot commit pointer. | HTTP stub + Apache |
| D10 | Candidate failure vs active health failure | Candidate leaves current untouched; active failure compensates then verifies prior; unresolved compensation blocks. | Adapter + DB fault |
| D11 | Kill after switch/health, DB success commit lost acknowledgement | Reconcile actual committed state before repair; exactly one success or verified compensation. | Real MySQL + process fault |
| D12 | Result transaction/audit fails | DB pointer rollback; external state visibly pending reconciliation, never unrecorded success. | Real MySQL fault |
| D13 | Stale worker resumes after new fence | External helper refuses mutation; DB rejects completion; winner stays active. | Multi-process helper + DB |
| D14 | External call attempted while connection in transaction | Assert failure before external action across build/publish/reconcile. | Service instrumentation |
| D15 | Staging success then simulated production success | Staging leaves global published fields alone; production gated owner updates global revision/site and env pointer atomically. | Service, real MySQL fixtures only |
| D16 | New revision review while production pointer exists | Live state/pointer retained; fresh approvals required for successor; ordinary transition cannot publish. | Lifecycle regression |
| D17 | First activation fails with no prior successful release | Remove only matching candidate pointer under fence; verify no current link and configured 503; DB current stays NULL. Failed recovery blocks target. | Adapter + DB fault |
| D18 | Old HTML request overlaps activation | Its content-addressed asset remains available from same-site retained pool; tampered/cross-site pool lookup denied. | Concurrent HTTP + filesystem |
| D19 | Matching request key/payload while pending, for publish and restore | Current scoped authorization then lookup returns same operation with existing_operation/replayed=true; exactly one intent/requested event, no lease or effects from replay. | Service + real MySQL |
| D20 | Matching replay after original success advanced pointer | Return original succeeded operation; no stale new-operation pointer/grant check, activation, pointer increment or duplicate success event. | Service + DB effect counters |
| D21 | Matching replay after a later deployment/restore advanced pointer again | Return historical operation plus actual target current ID/version and is_current=false; do not reactivate historical release. | Service + real MySQL |
| D22 | Matching replay after original production grant consumed/expired/revoked | Currently authorized Super Admin gets history only; no grant consumption, lease, provider call or publication authority. | Service + DB |
| D23 | Same identity with changed release/operation/restore source/approval/binding/expected pointer or reason | Canonical payload conflict for each individual field mutation, including NULL/value differences; original row/events unchanged. Different site/target is a separate namespace and must satisfy ownership checks. | Parameterized service + DB |
| D24 | Matching replay with lost actor access or foreign target/tenant key | Safe authorization denial with no historical DTO; no global request-key lookup or foreign existence disclosure; customer/business Admin cannot use internal replay. | Authorization + HTTP/DB |
| D25 | Concurrent identical requests, duplicate-insert race and lost response | One UNIQUE scoped identity, one requested event and one set of execution effects; losing transaction rolls back then reauthorizes/compares winner before new-operation gates. | Independent PDO barriers + HTTP |
| D26 | Matching failed/reconciliation-required request replay | Return actual outcome/recovery_status and current target DTO; no implicit retry, recovery claim, scheduling/event/pointer change. | Service + real MySQL |
| R01 | Restore previous known-good same target | New restore/deployment row, source/prior correlation, health and pointer success; all old history retained. | Service, DB, Apache |
| R02 | Never-successful/missing/tampered/cross-site/wrong-environment restore | Denied; no provider calls or pointer change. | Service, DB, artifact |
| R03 | Older superseded approval with explicit valid restore authority | Known-good restore allowed; old approval remains superseded; rights/tenant prohibition still denies. | Service, DB |
| R04 | Restore candidate health fails | Current remains served; no switch. | Adapter/Apache |
| R05 | Restore active health fails | Verified compensation preserves original current; if impossible, reconciliation_required, never terminal safe failure. | Adapter/process fault |
| R06 | Production restore lacks fresh exact restore grant | Denied even if original publish grant existed. | Service, DB |
| C01 | External active / DB pending | Fresh fence/authority/full health establishes same deployment once or compensates. | DB + filesystem |
| C02 | DB success / external mismatch | Freeze target, retain historical success, audit drift and repair via new authorized operation. | DB + filesystem |
| C03 | Staged orphan / unknown artifact | Only matching receipt eligible for adoption; unknown quarantined, not published/deleted automatically. | Filesystem |
| C04 | Expired lease before/after activation | Resolve uncertain effects first; ordinary retry only below execution limit; recovery remains claimable at limit; no concurrent activation. | DB + process |
| C05 | Repeat reconciliation completion with same observation | No duplicate success/pointer increment/repair/result event. A separately authorized new recovery claim has its own attempt/audit, not a duplicate outcome. | Service, DB |
| C06 | Reconciler vs old worker vs new request | One target authority; old fence cannot rollback newer winner; deadlocks handled boundedly. | Real MySQL + helper concurrency |
| C07 | Execution three seals valid build then dies before DB completion | At counts (total=3,execution=3,recovery=0), fresh recovery claim yields (4,3,1), validates original execution receipt/current content eligibility AND original requester authorization, then adopts exact bytes. Zero rerenders; producer stays expired; no budget reset. B24 covers denied adoption with independent recovery intact. | Build service + real MySQL/filesystem kill point |
| C08 | Execution three activates deployment then dies before health/result | Recovery attempt 4 with fresh token/fence holds same target slot; inspect actual pointer, then finalize verified eligible prior activation or compensate. No stage/new candidate activation or fourth execution. | Real MySQL + helper/process fault |
| C09 | Execution three loses DB commit acknowledgement | Resolve committed result before compensation/claim decisions; already committed success returned intact, including when a later operation is current. DB uncertainty blocks; no blind rollback. | Real MySQL commit/connection fault |
| C10 | Production grant expires/revoked after third activation | If uncommitted, recovery cannot finalize/new-publish with invalid grant but can CAS compensate exact recorded effects using fresh restricted recovery authority. Committed historical success is not retroactively rolled back. | Grant/clock + helper fault |
| C11 | Recovery attempt 4 crashes; later recovery resumes | Original execution reference unchanged; next claim has greater total/recovery number, fresh token/deadline/fence; old recovery renew/result/helper mutation rejected; prior rows preserved. | Real MySQL + multi-process fault |
| C12 | Recovery cannot determine/restore external state; both automatic claims fail | recovery_status and target blocked, operation reconciliation_required, slot retained, execution_count=3, no fourth execution. Later current authorized operator key claims one bounded recovery without schema/counter reset; duplicate operator key creates no second claim; another failure stays blocked. | Service/authorization + real MySQL/helper fault |
| C13 | Third first-deployment activation fails with no prior release | Fresh recovery verifies uncommitted result and exact candidate, then removes only its link and verifies configured 503/absence; current DB pointer NULL, no fabricated history. Unknown compensation remains blocked. | Real MySQL + Apache/process fault |
| S01 | Clean install and upgrade 023/024 to proposed 025 | Exact types/indexes/CHECK/FKs, ownership and uniqueness; no historical row/content change. MySQL 8.4 must accept DDL for both attempt tables with nullable recovery actor ON DELETE SET NULL FK and zero CHECK references to that column. | Future real MySQL 8.4 DDL |
| S02 | Cross-site FK insert, competing execution/recovery claims, invalid counters/kinds, pointer to other target | Ownership/UNIQUE/compatible shape/budget CHECKs reject invalid rows; execution 4 prohibited while total attempt 4/recovery 1 is valid. FK rejects nonexistent non-NULL actor IDs; claim-time actor shape/authorization is service-enforced (U01), not a CHECK. Original-execution/current-owner checks and rollback preserve counts. | Real MySQL + locked service |
| S03 | History deletion, NULL actors, index limits and query plans | For each build/deployment attempt table, create valid operator recovery then delete its user in an isolated fixture where other FKs permit: actor FK becomes NULL; attempt, operator trigger/type/key/reason, original execution reference, audit history, ownership and counters stay intact. Retain history RESTRICT, indexed access and MySQL 8.4 referenced-key checks. | Future real MySQL deletion/FK fixtures |
| U01 | Customer/business-admin access/CLI impersonation; invalid recovery actors | Deny existing unauthorized routes. For operator recovery in both services, missing/NULL authenticated operator, inactive/insufficient operator or forged actor fields deny before attempt/counter/lease/fence creation. Automatic/execution claims reject injected operator actor references even if the user exists; their service-generated actor FK is NULL. | Authorization/HTTP + fake DB behavior; real MySQL service gate |
| U02 | CSRF/replayed request/stale pointer/admin demotion; historical recovery actor deletion | Denied or idempotent as specified; POST303GET receipts correct and safe. After S03 deletion, NULL history grants no replay/recovery/execution/production authority to another caller: current access/claim/grant checks still apply; no trigger conversion or original-requester transfer. | HTTP/browser + service/DB |
| U03 | Internal history/status on mobile/keyboard, errors and drift | Clear environment/current vs observed state; escaped bounded text, accessible focus/receipts. | Browser |

Retain all existing M2–M5 suites and PHP lint as regression gates in implementation
PRs. Real DB concurrency uses independent connections/processes and barriers, not
sequential calls against the fake DB. Kill-point tests cover every committed intent /
external action / result gap. Cleanup uses synthetic IDs, separate fixture roots,
row-count/orphan reconciliation, and explicit authorization for staging cleanup.

D19–D26 run for **both** requestDeployment and requestRestore, with each changed
payload field tested independently. C07–C13 explicitly exercise execution-budget
exhaustion, not just an early-attempt crash. S01/S02 verify the proposed new counters,
NULL/shape CHECKs, same-parent recovery FKs and request-identity uniqueness on real
MySQL at M6B/M6D gates; concurrency/effect tests remain M6D/M6E gates. This contract
walkthrough is not schema or runtime proof.

B17 retains builder-identity coverage and specifies revocation-before-claim denial.
B18–B26 add nine cases for valid/changed permitted roles, inactive/deleted/NULL requester,
business-only roles, retries, races, post-claim loss, independent exhausted-budget
recovery, committed success and substitution. Fake DB tests cover decisions/effect
counts; real MySQL must prove authorization/FK locks and competing transactions at
M6B, with output/adoption faults integrated at M6C/M6D. These are planned gates only.

S01–S03 and U01/U02 are parameterized for **both** site_build_attempts and
site_deployment_attempts. They separate future MySQL 8.4 DDL/deletion proof from strict
service authorization at insertion and current access after deletion. A valid historical
operator row with NULL actor must survive the fixture; a new unauthenticated operator
claim must be rejected before allocation. No additional matrix IDs are added: the
count remains **77 planned cases**, not executed schema/service PASS results.

## 25. Recommended submilestones and PR sequence

Every row is a separate reviewable PR/gate. M6A is reviewed/merged. M6B is implemented
locally/review required, with real-MySQL validation NOT EXECUTED. M6C–M6G are not started.
The table retains the reviewed scope; see the M6B record for its actual file inventory.

| Milestone | Scope and expected files/classes | Migration | Local exit gate | Staging exit gate | Depends on |
| --- | --- | --- | --- | --- | --- |
| M6A — Architecture contract | This document; current sprint/handoff/roadmap links; review unresolved host prerequisites and schema contract. | Historical M6A proposal only; M6B subsequently creates 025. | Markdown/link/fence/status/diff checks, docs-only diff. | None; no access/deploy. | M5 progression complete. |
| M6B — Persistence and jobs | `025_site_build_deployment.sql`; SiteBuildService, locked SiteRevisionManager eligibility and private contract/store helpers; six `WebsitePlatformM6B*Test.php` suites, isolated MySQL harness and fixtures. | Create 025 once; additive nine tables and two ownership indexes. | Fake DB behavior + fresh/upgrade real MySQL gate where available; idempotency/lease/failure tests; PHP lint and existing suites. No external publisher. | Separate approved app deploy/migration; exact schema/ownership/uniqueness/concurrency/cleanup PASS before M6 closeout. | Reviewed M6A. |
| M6C — Artifact builder | `SiteArtifactBuilder.php`, `SiteArtifactValidator.php`, `SiteArtifactStore.php`, `LocalSiteArtifactStore.php`, `SitePublicRenderContext.php`; page render extension, repository static CSS; `scripts/site-build-worker.php`; artifact/hash/privacy tests. | Uses 025. | B01–B26 as applicable, deterministic multi-page artifacts, all external I/O outside transactions, immutable/fault tests. | Approved isolated staging build-only fixture validates assets/permissions/bytes without activation. | M6B. |
| M6D — Deployment state and authority | `SitePublisher.php`, `SiteDeploymentService.php`, publisher factory/fake adapter; SiteManager pointer owner, SiteApprovalManager production grants and revision publication gate; `scripts/site-deployment-worker.php`, `scripts/reconcile-site-jobs.php`; deployment/restore/concurrency tests. | Uses 025; schema corrections before applying it or later additive migration if already applied, never rewrite history. | D/R/C unit + real MySQL native-prepare pointer/fence/grant tests, production deny default. | Approved DB-only fixtures prove pointer and approval transactions; no real activation until M6E. | M6B; M6C for real artifact integration. |
| M6E — Apache adapter and host contract | `ApacheDigitalOceanSitePublisher.php`, HTTP checker, restricted `infrastructure/deployment/ubo-site-publish`, Apache example and service supervision examples; bounded staging runbook. | No new migration expected. | Disposable Linux/Apache same-filesystem fixture, fencing/malicious path/config/kill/health/restore tests; no app-wrapper reuse. | Explicit infrastructure approval; section 18 preflight PASS, exact staging binding enabled, real publish/restore/fault/reconciliation PASS with external/DB evidence. | M6C/M6D. |
| M6F — Internal controls | `SiteDeploymentAdminWorkflow.php`, proposed `public/app/admin/site-deployments.php`, private history view; links from site/review; `scripts/manage-site-releases.php` for controlled operator actions. | None. | Role/CSRF/replay/expected-pointer/escaping tests, accessible status and read-only customer boundaries. | Separately approved authenticated internal browser build/staging publish/restore/history gates; customer denials; no production controls enabled. | M6D/M6E. |
| M6G — Integrated validation/closeout | Proposed `docs/sprint-8.8-m6-staging-validation.md`, `docs/sprint-8.8-m6-closeout.md`; required fixes in focused PRs. | Reconcile applied 025, no rerun; future correction number if needed. | All required regression/lint, fault/concurrency tests and final scoped diff. | Final exact-SHA real-MySQL + external runtime + browser matrix, safe logs, synthetic cleanup and baseline reconciliation, retained known-good restore; production remains unauthorized. | M6B–M6F. |

PR #125 merged the reviewed M6A architecture. A subsequent explicit instruction
authorized local M6B implementation; review and the real-MySQL gate remain outstanding.
Staging-only contract/profile can close
M6 with production denied; live production release and registered-site form routing
remain separately gated later. Do not claim all of Sprint 8.8 or first-customer
readiness from M6 completion.

## 26. Admin and control-plane recommendation

Build trigger and safe build/release/deployment history belong in the internal admin
surface, with staging publish and restore as distinct explicit actions. Initial
worker/reconcile operations remain CLI/service-only; web requests enqueue intent and
return promptly. Never execute render/Apache/provider work during an admin POST.

Internal Admin/Super Admin can read histories/request build and authorized staging
publish/restore. Only Super Admin can later approve production release/restore;
production approval entry remains audited CLI/service-only during M6, disabled by
default. A production UI is not needed to validate the durable gate and should wait
for separate launch authorization. No broad new role or customer destructive controls.

Admin DTO shows source revision/hash, exact release and environment, validation,
current successful deployment, latest observed external state, safe error, pending
operation and available known-good restore choices. Forms carry CSRF, exact IDs,
expected current ID/pointer version, binding version, restore source, approval and
reason code with request_key. Preserve that original request snapshot/key across a
lost response; a genuinely new action gets a new key and current preconditions.
Server reauthorizes, checks for matching history first and uses 303 PRG/safe receipts
without redispatching work. Replayed success must display whether it is still current;
replayed failure/reconciliation state must not offer an automatic retry. Customer
Website Manager retains existing M5 review/feedback/
approval actions; customer approval never automatically builds, deploys or requests
production approval. Existing `publicly_deployed=false` becomes an environment-aware
internal read model only when real evidence exists.

## 27. Planning deliverable and validation record

The following is the **historical pre-merge M6A record**. PR #125 subsequently
merged at the M6B baseline above. Its old approval instructions and absent-025
statements do not override the authorized M6B task or current status.

This document is the architecture/schema/API/security/test/submilestone deliverable.
Current planning pointers in the sprint, handoff, roadmap and architecture documents
are updated only where needed; historical M5 evidence/status snapshots remain intact.
All M5 statuses and the deployed application SHA remain unchanged.

Planning validation PASS: 90 relative Markdown links and one referenced anchor across
seven changed documents; balanced fences; current-status consistency and unchanged
M5/production/deployed-SHA invariants; `git diff --check`; documentation-only changed
paths; absent migration 025; byte-identical 023/024 against the audit baseline.
The earlier correction separates execution/recovery authority and scoped request identity/
payload replay. Completed automated review of `00c3cfd11acdf46b86d14004987bc896d6b595f9`
identified the B17 requester/claim contradiction. The B17 correction preserves its denial
and aligns sections 3/5/6/8/20/23/24: current original-requester authorization at every
ordinary claim and new-success acceptance, deterministic queue cancellation, independent
safe recovery and preserved committed success. Source inspection confirmed the existing
SiteAuthorizationPolicy actorContext locking path; no application tests were run.
The subsequent review of `4655ccc1f8976737e450ae748a69af2ebd893b87` raised nullable
historical recovery actors. The plan already allowed a non-NULL authorized operator at
claim and NULL after deletion; that distinction was not missing. This correction removes
all recovery_authorized_by_user_id references from the shared CHECK to respect MySQL
8.4's CHECK/referential-action restriction, preserves its nullable SET NULL FK, and
spells out service insertion rules and both-table deletion/authority fixtures. No
database failure was reproduced and no migration/schema execution PASS is claimed.
Only this plan changed from that latest reviewed head. Supporting PR documents remain
accurate; B17 and the previous execution/recovery/replay contracts are preserved.
The implementation matrix defines 77 future cases. The temporary local documentation
validator is outside the repository and is not application code or an M6 implementation.
No application suite, real MySQL, staging HTTP/SSH, provider, Apache or production
validation is claimed. Existing documentation PR #125 is non-draft, open and unmerged;
architecture review of the corrected head is required before implementation. Do not
merge or enable auto-merge. Commit SHA, review state and PR URL are reported with the
task result, not self-referentially embedded into the commit that generates them.

Final planning status: **M6 PLANNING COMPLETE / IMPLEMENTATION NOT STARTED**.
