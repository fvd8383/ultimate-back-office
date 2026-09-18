# Sprint 8.8 M6 — Build, Deployment, and Restore Implementation Plan

Status: **M6 PLANNING COMPLETE / IMPLEMENTATION NOT STARTED**.
Planning began as **M6 PLANNING / ARCHITECTURE AUDIT IN PROGRESS** and this document
records the completed repository audit and proposed implementation contracts.
Architecture review and the separately authorized implementation PRs remain ahead.

## 1. Authority and boundaries

| Item | Authoritative value |
| --- | --- |
| Repository | `fvd8383/ultimate-back-office` |
| Repository audit baseline | `5c9fee700b83bd04589d612b8bc4e8eea975499a` |
| Deployed application baseline | `70a3051f73874e7268b9c1bba45bf19d41f9432a` |
| Why different | PR #124 merged M5 documentation closeout; its diff from the deployed application contains documentation only and requires no staging deployment. |
| M5A / M5B | COMPLETE / STAGING PASS / FORMALLY CLOSED |
| M5C | ACCEPTED / NARRATOR FOLLOW-UP DEFERRED |
| M5 | COMPLETE FOR SPRINT PROGRESSION |
| Sprint 8.8 | IN PROGRESS |
| Production | UNAUTHORIZED / NOT DEPLOYED |
| Migrations | 023 and 024 immutable; 025 absent, proposed for M6 |

The [merged M6 scope](sprint-8.8.md#m6--build--deployment--restore) is authoritative.
The [M5 closeout](sprint-8.8-m5-closeout.md) and its evidence are unchanged. This task
does not implement, migrate, deploy, or access staging/production. The deployed SHA is
existing user-supplied/repository evidence, not a new remote observation.

All new names, paths, configuration, schema, and methods below are **proposals**, not
claims that these facilities exist. Repository evidence is sufficient to design a
bounded adapter; actual host capabilities require the explicit future gate in section
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
| [SiteAuthorizationPolicy](../private/classes/SiteAuthorizationPolicy.php) | Active internal-scope Admin/Super Admin differ from business-scope Owner/Admin. Locked actor resolution exists; M6 must reauthorize inside its own intent/result transactions. |
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
it; it must not copy their lifecycle SQL. This is a proposed M6 implementation change,
not a method present today.

| Gate | Required rule |
| --- | --- |
| Actor | Active internal Admin or Super Admin at request; locked current authorization; trusted environment-bound worker at execution. Customer membership never grants build/publish. |
| Site | Exists; purpose `247sp`; lifecycle `approved`, or `active` only after the future guarded production transition is enabled. Reject draft/demo/pending/suspended/archived/cancellation/conversion. Existing `assertSiteOperational` only rejects archived, so it is insufficient alone. |
| Business | Current active customer association equals snapshot business identity; business active and not suspended; active business 247SP module and globally active module. Recheck under locks; internal role does not bypass tenant readiness. |
| Revision | Belongs to site; `internally_approved`; materiality is `material` or `non_material`; review-ready evidence exists; stored canonical composition validates. A currently production-published revision may be rebuilt only if it is still the canonical current revision and passes the same approvals/freshness rules. |
| Material approval | Current unrevoked approved customer record on this exact material revision, plus current unrevoked internal approval on this exact revision. |
| Non-material approval | Existing `effectiveCustomerApproval` must return exactly one unrevoked approved earlier customer baseline; internal approval still binds this revision. Do not silently strengthen this to require an already deployed baseline: current code checks earlier approval, despite its message saying “public baseline.” Initial launch still requires material customer approval. |
| Freshness | Reject any newer material revision via existing helper. Also reject a newer revision already internally approved/published. A newer unclassified draft conservatively blocks a new build until classified; a classified non-material draft alone does not invalidate the approved candidate. This extra delivery freshness rule belongs in the single lifecycle eligibility method. |
| Rejected/stale states | `changes_requested`, `validation_failed`, `ready_for_review`, `customer_approved`, `superseded`, and `restored` are not directly buildable. A restore candidate must complete review/internal approval first. |
| Asset/repository integrity | Exact renderable versions; all public assets have current permitted rights, checksum/size matches and site/business ownership. Historical unknown-rights exception is refused. |
| Existing successful build | Return existing job/release for the same identity; never rebuild/overwrite it. History can remain readable after eligibility expires, but cannot be newly deployed through the ordinary publish path. |
| Domains | No DomainManager, DNS, SSL, registrar or legacy `publish_status` check grants build eligibility. |

Customer approval alone is explicitly insufficient. M5 material-successor handling
supersedes prior customer/requested internal approvals but can retain an approved
internal record; checking only `internally_approved` or only site `approved` is unsafe.

Eligibility is rechecked when requesting, claiming, accepting successful output,
requesting deployment, immediately authorizing activation, and committing deployment
success. A successor/revocation discovered after build leaves an immutable historical
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
| idempotency_key hash; requested_by_user_id ID?; actor_type VARCHAR(24); correlation_id key | I; request identity and audit. |
| status VARCHAR(32) default requested; attempt_count INT UNSIGNED default 0; max_attempts INT UNSIGNED default 3; next_attempt_at DATETIME(6)? | M; scheduling. |
| current_attempt_id ID?; lock_version BIGINT UNSIGNED default 0 | M; CAS/lease generation. |
| failure_category VARCHAR(40)?; failure_code VARCHAR(64)?; safe_summary VARCHAR(500)? | M; last bounded result, no exception text. |
| started_at DATETIME(6)?; completed_at DATETIME(6)?; updated_at DATETIME(6) | W first start, M terminal/updated timestamps; attempts retain each execution. |

UNIQUE `job_key`, `release_key`, `idempotency_key`, `(id,site_id)`,
`(id,revision_id,site_id)`; INDEX `(status,next_attempt_at,id)`, `(site_id,created_at,id)`,
`(revision_id,site_id)`, `correlation_id`. FK `(revision_id,site_id)` to
site_revisions; actor to users; business to businesses. Add current-attempt FK after
attempt table exists: `(current_attempt_id,id,site_id)` to attempt `(id,build_job_id,site_id)`.
CHECK max_attempts between 1 and 3, attempt_count <= max_attempts; business/association
both NULL or both set. Job statuses: `requested`, `running`, `retry_wait`, `succeeded`,
`failed`, `reconciliation_required`, `cancelled`. A failed retry may clear completed_at
on the job; its terminal attempt remains immutable. Successful job is terminal.

### 5.2 site_build_attempts — owner SiteBuildService worker

| Columns | Mutability and purpose |
| --- | --- |
| build_job_id ID; attempt_number INT UNSIGNED; worker_id key; lease_token_hash hash; correlation_id key | I; unique claim and hashed 256-bit secret token. |
| status VARCHAR(24); leased_at DATETIME(6); lease_expires_at DATETIME(6); heartbeat_at DATETIME(6); started_at DATETIME(6)?; completed_at DATETIME(6)? | I lease start; M expiry/heartbeat; W start/completion/terminal status. |
| candidate_storage_key VARCHAR(500)?; candidate_artifact_hash hash?; external_reference VARCHAR(191)? | W; bounded private artifact receipt; no raw OS paths/URLs. |
| failure_category VARCHAR(40)?; failure_code VARCHAR(64)?; safe_summary VARCHAR(500)? | W terminal result. |

UNIQUE `(build_job_id,attempt_number)`, `(id,site_id)`, `(id,build_job_id,site_id)`;
INDEX `(status,lease_expires_at,id)`, `correlation_id`; FK `(build_job_id,site_id)` to
jobs. CHECK positive attempt and lease expiry > leased_at. Statuses `leased`,
`running`, `succeeded`, `failed`, `expired`, `abandoned`. Lease token never appears in
DTO history, artifact, event metadata or logs.

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
| deployment_key uuid; target_id ID; release_id ID; source_revision_id ID; operation VARCHAR(16); request_key uuid; idempotency_key hash | I; exact target/release and publish/restore request. |
| expected_current_deployment_id ID?; expected_pointer_version BIGINT UNSIGNED; restored_from_deployment_id ID?; deployment_approval_id ID? | I; optimistic request precondition, restore source and production grant. |
| requested_by_user_id ID?; actor_type VARCHAR(24); correlation_id key; binding_version INT UNSIGNED | I; authorization/config snapshot. |
| status VARCHAR(40) default requested; attempt_count INT UNSIGNED default 0; max_attempts INT UNSIGNED default 3; next_attempt_at DATETIME(6)?; current_attempt_id ID? | M; durable job and attempts. |
| previous_deployment_id ID?; activation_fence BIGINT UNSIGNED?; external_reference VARCHAR(191)? | W per first activation reservation; target slot forbids intervening deployment; attempts carry recovery fences. |
| failure_category VARCHAR(40)?; failure_code VARCHAR(64)?; safe_summary VARCHAR(500)? | M bounded terminal/current failure. |
| started_at DATETIME(6)?; staged_at DATETIME(6)?; activation_attempted_at DATETIME(6)?; health_verified_at DATETIME(6)?; completed_at DATETIME(6)?; updated_at DATETIME(6) | W milestones/M workflow; attempt rows preserve retries. |

UNIQUE `deployment_key`, `(target_id,request_key)`, `idempotency_key`, `(id,site_id)`,
`(id,target_id,site_id)`; INDEX `(status,next_attempt_at,id)`,
`(target_id,created_at,id)`, `(release_id,site_id)`, `correlation_id`.
FK `(target_id,site_id)` to targets; FK `(release_id,source_revision_id,site_id)` to
releases `(id,source_revision_id,site_id)`; previous/expected/restore composite FKs
`(pointer,target_id,site_id)` to deployments; actor to users. Current attempt FK
`(current_attempt_id,id,site_id)` added after attempts exist. Approval FK to section
5.9's `(id,release_id,target_id,site_id)` with matching columns on deployment.
CHECK operation `publish`/`restore`; restore requires restored_from_deployment_id,
publish requires it NULL; attempt limits 1..3. Production-approval requirement is a
cross-table locked service check, not an ineffective same-row CHECK.

### 5.7 site_deployment_attempts — owner SiteDeploymentService worker

Columns: `deployment_id ID`, `attempt_number INT UNSIGNED`, `worker_id key`,
`lease_token_hash hash`, `fence_epoch BIGINT UNSIGNED`, `status VARCHAR(24)`,
`leased_at DATETIME(6)`, `lease_expires_at DATETIME(6)`, `heartbeat_at DATETIME(6)`,
`started_at DATETIME(6)?`, `completed_at DATETIME(6)?`,
`external_reference VARCHAR(191)?`, `receipt_json JSON?`,
`failure_category VARCHAR(40)?`, `failure_code VARCHAR(64)?`,
`safe_summary VARCHAR(500)?`, `correlation_id key`.

Same immutability/lease/status rules as build attempts. `receipt_json` is bounded
16KiB structured phase evidence, never provider output; finalized once per attempt.
Phase observations before finalization go to append-only site_events/health checks
and external journal. UNIQUE `(deployment_id,attempt_number)`, `(id,site_id)`,
`(id,deployment_id,site_id)`; INDEX `(status,lease_expires_at,id)`, `correlation_id`;
FK `(deployment_id,site_id)` to deployments. Expired attempt's fence is never reused.

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
The proposed service is `private/classes/SiteBuildService.php`.

| Proposed operation | Inputs and output | Boundary |
| --- | --- | --- |
| `requestBuild(int $actingUserId, array $input): array` | Input site_id, revision_id, expected_snapshot_hash, build_profile, correlation_id?; output BuildJob DTO plus existing boolean. Builder version is server-selected, never arbitrary customer code. | Locked authorization/eligibility and deterministic insert transaction; no FS/network. |
| `claimBuild(array $workerContext): ?array` | Environment-bound worker_id/capabilities; output Lease DTO plus immutable BuildInput DTO. | Short transaction, one eligible job/attempt; worker principal is CLI-only, not a user-supplied system actor. |
| `renewBuildLease(array $lease): array` | job_id, attempt_id, token; returns expiry. | Token, current attempt, status, unexpired lease and max runtime CAS. |
| `executeBuild(array $lease): array` | Valid claimed lease; returns verified candidate receipt. | Outside DB transaction: bounded asset reads, static render, validation, immutable candidate storage. |
| `completeBuildSuccess(array $lease, array $receipt): array` | Receipt references already verified sealed candidate; returns Release DTO. | Short transaction rechecks eligibility, lease, identity; inserts release/validation, succeeds job/attempt and event atomically. |
| `completeBuildFailure(array $lease, array $failure): array` | Fixed category/code/safe summary; returns job. | Short guarded transaction; classify retry; never change published/current pointers. |
| `buildJobForActor(int $actingUserId, int $jobId): array` | Internal reader; safe job/attempt summary. | Read-only tenant-scoped projection; no lease tokens/input snapshot. |
| `releasesForSite(int $actingUserId, int $siteId, array $cursor): array` | Keyset cursor (created_at,id), limit 1..100. | Read-only history, immutable Release DTOs. |
| `releaseManifestForActor(int $actingUserId, int $releaseId): array` | Returns bounded sanitized metadata/file manifest and integrity status. | Internal only; no unrestricted file-path/download API. |
| `retryBuild(int $actingUserId, int $jobId, string $correlationId): array` | Eligible transient failure under attempt budget. | Reauthorize/recheck; requeue same job, append event. Succeeded never requeued. |
| `reconcileBuild(int $jobId, array $workerContext): array` | Job + trusted reconciler identity. | Snapshot/claim transaction, external inspection, result transaction; never nest external work in transaction callback. |

BuildJob DTO: IDs, status, source hash, profile/version, attempt count, next retry,
safe error, timestamps, correlation and release ID if succeeded. Lease DTO contains
opaque token only in worker memory; BuildInput contains projected facts, ordered
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
| Retry after transient failure | Same job/key/reserved release_key, new numbered attempt. |
| Crash/expired lease | Reconciliation first, then new fenced attempt; no blind retry if sealed output may exist. |
| Builder code changes | New key/job/release even with same semantic version; server must identify the new SHA. |
| Successor revision | Distinct key; normal freshness rules determine eligibility. |
| Different options/profile | New input hash and key; only server-allowlisted profiles/options. |
| Existing success no longer eligible | History is retained; deployability false. Never silently regenerate or reactivate it. |

Deployment request idempotency is separate: SHA256 of target ID, release ID,
operation, expected pointer version, restore source, approval ID and client-generated
request_key. Repeating the key with any changed payload is conflict. A genuinely new
publish/restore uses a new key and must satisfy the current-pointer precondition.

## 8. Worker leases, retries, and completion

Defaults: 120-second lease, heartbeat every 30 seconds, 15-minute build and 5-minute
deployment attempt deadline, maximum three execution attempts. All are server policy,
recorded on jobs; no customer overrides. Initial transient retry delays are 30 and
120 seconds. Deadlock retries are bounded separately and do not consume an execution
attempt until claim commits. Time and claim conditions use MySQL UTC time.

Select candidate IDs without locks; then lock site, relevant revision/approval/tenant
rows, target if applicable, job and attempt in documented order, and reread queue
eligibility. Site-first locking matches lifecycle owners. Do not lock a job first and
then wait on site in another path. Queue-only `SKIP LOCKED` is optional optimization,
not an eligibility/read-consistency mechanism; MySQL explicitly limits its useful
semantics to queue-like workloads ([locking-read documentation](https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html)).

Claim increments attempt_count, creates attempt and random 32-byte token (store only
SHA-256), records current_attempt_id, lease expiry, start event and returns the lease.
Every renewal, failure, phase advance and completion matches attempt/token, current
attempt and unexpired lease. Expired token cannot renew itself. Claim increments a
monotonic target fence for deployment; a token alone cannot fence filesystem writes.

Errors use fixed codes/categories: `authorization`, `eligibility`, `input_invalid`,
`integrity`, `configuration`, `transient_storage`, `transient_network`, `lease_lost`,
`external_unknown`, `health_failed`, `database_unavailable`. Only transient storage/
network errors before activation auto-retry. Invalid source, rights, tampering, config,
missing approval and health failures need correction/new explicit action. Ambiguous
external outcomes require reconciliation. Exhaustion is terminal until a reviewed
policy change/new valid input; retry never silently resets the three-attempt budget.

Reuse existing SiteServiceException classifications for request errors; add a separate
bounded worker result vocabulary rather than passing unrecognized strings to its
current constructor (which normalizes them to database_failure). Log exception class
and fixed error code only, not message/SQL/command output. No nested transactions.

Completion replay with matching already-committed attempt and receipt returns the
recorded result and emits no duplicate event. A conflicting replay or any stale worker
is rejected. Crash after sealed artifact but before DB success: reconciliation verifies
reserved release identity, full hashes, producer receipt and current eligibility,
claims a new attempt, and adopts only validated bytes. Stale attempts write only their
own private candidate directory; they cannot overwrite the sealed namespace. Unknown
or mismatched orphan remains quarantined with an event, never automatically published.

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
| `stageRelease(array $release, array $target, array $context): array` | Verified release identity/digests, opaque target binding, operation key/fence; returns immutable staged receipt, verification summary and external reference. |
| `activateRelease(array $staged, array $expectedCurrent, array $context): array` | Expected external release and fence, authorized attempt; returns activation receipt with old/new identity and observed fence, or conflict/unknown. |
| `healthCheck(array $expected, array $target, array $context): array` | Exact release/marker/probe profile, bounded phase and timeout; returns structured check evidence. |
| `inspectCurrentRelease(array $target): array` | Read-only observed release, pointer/journal fence, integrity and unknown/missing classification; no DB pointer mutation. |
| `restoreRelease(array $stagedKnownGood, array $expectedCurrent, array $context): array` | Same fenced activation mechanics; semantic restore marker retained in receipt. Authority comes from deployment service, not adapter. |
| `reconcileExternalState(array $intent, array $target, array $context): array` | Returns observed state and bounded repair outcome for an authorized exact operation. Never automatically bless an arbitrary directory or latest symlink. |

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
`deploymentsForSite(actor,site,environment,cursor)`, `reconcileDeployment(id,worker)`.
Request includes site_id, target_id, release_id, expected current ID/pointer version,
request_key, correlation and production approval ID when applicable. Worker cannot
choose a different target/release. Triggering retry after activation uncertainty first
requires reconciliation; restore is never implemented as a build-job retry.

## 13. Deployment states and transaction protocol

Statuses: `requested`, `running`, `artifact_staged`, `activation_attempted`,
`health_check_pending`, `succeeded`, `retry_wait`, `failed`,
`reconciliation_required`, `cancelled`. Phase transitions and health observations are
audited; an expired attempt cannot advance them. “Current”, “previous”, “superseded”
and “restored” are history/read-model relationships, not destructive rewrites of a
successful deployment status. A restore succeeds as a new row with operation restore.

1. Request transaction: locked actor/site/revision/approvals/target checks; immutable
   release verified by DB evidence; expected pointer/version match; insert intent/event.
2. Claim transaction: acquire target active_deployment_id slot if NULL (or recover the
   same reconciled operation), check request pointer precondition, issue attempt/lease
   and new target fence. Concurrent queued requests do not own the slot yet.
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
   previous release using same operation/fence and expected candidate pointer, then
   verify rollback. Only after confirmed compensation mark failed and free the slot.
   Unknown/failed compensation remains reconciliation_required and blocks new work.
   For a first deployment with no previous release, compensation removes only this
   operation's current link and verifies the preconfigured unavailable response;
   the DB pointer stays NULL. Never invent a successful previous deployment.

No external operation runs inside any lifecycle DB transaction. Holding a bounded
**external** per-target helper lock around activation/health/DB result coordination
does not mean holding a SQL transaction through a network call. If the DB is down,
the helper journals outcome, closes/relinquishes only after bounded compensation or
records unresolved state; it never guesses DB commit success after a lost connection.

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
authority, exact binding and runtime environment at claim, activation authorization
and success. Consumption binds one deployment operation, permitting only its fenced
reconciliation/retries while the grant is still valid. Changed release/hash/pointer/
binding, expiry, revocation or role loss rejects unexecuted work. Expired/revoked grant
after activation cannot be used to declare success: restore previous verified pointer
under the operation's bounded compensating authority, otherwise block/reconcile.

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
protect external state.

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

Reconciler claims an exclusive new lease/fence for the existing operation (or a new
audited repair deployment when none exists), checks target slot and approval policy,
inspects bytes/link/journal/health outside transaction, then applies a short guarded
result transaction. Repeated same observation/repair key is idempotent. No arbitrary
filesystem path, newest timestamp or HTTP 200 can create success.

| Failure window | Resolution |
| --- | --- |
| Built artifact, DB completion lost | Inspect reserved job/release key, full manifest/content, producer receipt and eligibility. Fresh fenced attempt may adopt exact valid output; otherwise quarantine and fail/requeue by classification. |
| Deployment intent, worker never ran | Eligible requested row remains claimable. Expired lease without external effects becomes expired attempt and bounded retry. |
| Staged release, no activation | Verify staged bytes/receipt; resume same authorized operation under new fence or cancel safely. Keep current pointer. |
| Config/reload success, DB record lost | Ordinary publish does not reload; for approved binding provisioning inspect installed config digest, actual served target and receipt. No assumption from process exit; disable target until reconciliation. |
| Symlink activated, active health failed | CAS compensation to previous release, then rollback health. Mark failed only after prior active identity is verified; otherwise reconciliation_required and target remains occupied/blocked. |
| Health passed, worker died before DB result | New reconciler rechecks approval/eligibility/fence and repeats fresh health; commit original deployment once if valid, otherwise compensate. Stale old worker completion rejected. |
| DB pointer success, external different | Freeze new activation; preserve successful history and record drift. If an identifiable authorized in-flight operation exists reconcile it; otherwise create audited repair/restore to last verified release, never bless unknown external state. |
| DB commit outcome unknown | Read transaction result by operation/attempt key after reconnect before compensation. If success committed, keep and reverify that current; if absent, reconcile intended operation. |
| External equals DB after compensation but prior DB finalization failed | Reverify rollback receipt and pointer; mark failed/free slot exactly once. No extra success deployment. |
| Newer deployment won; old worker returns | Reject token/fence/expected-pointer mismatch. Old worker may not rollback newer live release. Its private artifact is quarantined for retention review. |
| Orphan staged directory | Match exact job/deployment receipt and lease; retain for bounded recovery. Unknown or expired orphan never becomes current automatically; cleanup only under separate retention policy. |

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
| Resource exhaustion | Bounded input/HTML/assets/files/manifest/time/memory, three attempts, one active target operation, disk-space preflight and isolated worker limits. |
| Live-state race | Site/target locks + pointer CAS + external expected-pointer/fence; unknown state freezes target; never compensate over a newer winner. |
| Legacy publication shortcut | DomainManager/legacy publish status excluded from all success and authorization checks; no use of UBO deployment wrapper for sites. |

## 23. Observability and audit

Reuse `site_events` in the same transaction as each durable state transition; optional
business activity summaries derive from committed site events and never carry private
build metadata. No new generic logging/outbox platform is required for M6. Job state
is durable work intent. Audit insertion failure rolls back DB mutation; external
effects then require reconciliation.

Required event types: `site_build_requested`, `site_build_started`,
`site_build_succeeded`, `site_build_failed`, `site_build_retry_requested`,
`site_deployment_requested`, `site_deployment_started`, `site_release_staged`,
`site_activation_attempted`, `site_deployment_health_checked`,
`site_deployment_succeeded`, `site_deployment_failed`,
`site_reconciliation_required`, `site_reconciliation_completed`,
`site_restore_requested`, `site_restore_succeeded`, `site_restore_failed`,
`site_production_approval_granted`, `site_production_approval_revoked` and
`site_production_approval_consumed`. Restore events accompany deployment events with
the same operation ID. Preserve expired/abandoned attempts even after successful retry.

Each event has site/revision, actor or system identity, UTC time, result, stable
correlation_id and bounded allowlisted metadata: job/deployment/release/target/attempt
IDs, environment, source/result status, fence and pointer version, digests, counts,
duration, safe failure code, previous/restore source IDs and health check keys.
No raw token, OTP/session/cookie, approval comment, feedback, business/customer text,
snapshot/brief, provider credential, environment file, full response or command/SQL
output is logged. History DTOs omit private storage keys and lease token hashes.

Operational views expose queue age, lease expiry, retry count, duration, failures by
category, target mismatch and last health time. The last successful pointer and current
observed condition are displayed separately; a historical success must not hide drift.

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
| B07 | Failed transient storage; retry; exhausted budget | Same job, attempts 1..3 retained, scheduled delays, no fourth execution. | Service, DB |
| B08 | Crash before output / after seal / before DB commit | Expired attempt recorded; reconcile/adopt exact valid candidate once, or quarantine; no success inferred. | Fault injection, DB, filesystem |
| B09 | Expiry/heartbeat race/late worker completion | Old token cannot renew/finish; newer attempt unaffected. | Clock, real MySQL concurrency |
| B10 | Canonical input ordering, NULL/integers/Unicode, asset order | Golden hashes stable; changed content/options/version/SHA changes identity; actor/environment/time does not. | Pure unit |
| B11 | Repeated success and conflicting output for release_key | Matching completion no duplicate event; differing bytes refused; sealed release unchanged. | Service, filesystem |
| B12 | Tampered/missing/additional file, false MIME, digest mismatch | Seal/stage/restore fails; no deployable artifact. | Artifact, filesystem |
| B13 | Private snapshot fields/brief/comment/OTP/token/secret canaries | No canary in public/metadata artifacts or logs; approved public contact fields preserved. | Artifact, leakage fixtures |
| B14 | Multi-page layout/nav/assets, broken link, HTML parse/CSP | Correct distinct documents and full nav; broken refs/scripts/PHP/.htaccess fail. | Renderer, artifact/browser |
| B15 | Unknown/expired rights, wrong business asset, malicious SVG/link/path, bounds | Denied without arbitrary file access/write; no outside-root mutation. | Artifact, security |
| B16 | Inert lead form and server-owned profile | Fields retained, no POST action/no fake success; production cannot use static-review profile. | Renderer, service/browser |
| B17 | Builder SHA mismatch/dirty runtime; revoked actor before execution | No execution; safe configuration/authorization failure. | Worker, DB |
| D01 | Approved staging fixture publish | Candidate pass, atomic switch, two active health passes, one success/pointer/event transaction. | Service, DB, Apache |
| D02 | Production disabled or no grant/customer approval only | Zero provider calls, pointer unchanged. | Service, DB |
| D03 | Wrong release/hash/site/env/operation/binding/pointer grant | All denied before activation; no grant consumption. | Service, DB |
| D04 | Expired/revoked grant or approver role lost at each boundary | Before activation deny; after activation compensate or require reconciliation, no success. | DB concurrency, adapter fault |
| D05 | Two deployments queued on same expected pointer | One active slot; first verified success wins, second conflicts and cannot move pointer. | Real MySQL + helper concurrency |
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
| R01 | Restore previous known-good same target | New restore/deployment row, source/prior correlation, health and pointer success; all old history retained. | Service, DB, Apache |
| R02 | Never-successful/missing/tampered/cross-site/wrong-environment restore | Denied; no provider calls or pointer change. | Service, DB, artifact |
| R03 | Older superseded approval with explicit valid restore authority | Known-good restore allowed; old approval remains superseded; rights/tenant prohibition still denies. | Service, DB |
| R04 | Restore candidate health fails | Current remains served; no switch. | Adapter/Apache |
| R05 | Restore active health fails | Verified compensation preserves original current; if impossible, reconciliation_required, never terminal safe failure. | Adapter/process fault |
| R06 | Production restore lacks fresh exact restore grant | Denied even if original publish grant existed. | Service, DB |
| C01 | External active / DB pending | Fresh fence/authority/full health establishes same deployment once or compensates. | DB + filesystem |
| C02 | DB success / external mismatch | Freeze target, retain historical success, audit drift and repair via new authorized operation. | DB + filesystem |
| C03 | Staged orphan / unknown artifact | Only matching receipt eligible for adoption; unknown quarantined, not published/deleted automatically. | Filesystem |
| C04 | Expired lease before/after activation | Pre-activation bounded retry; post-activation inspect/repair first; no concurrent activation. | DB + process |
| C05 | Repeat reconciliation with same observation | No duplicate success/pointer increment/repair operation/event. | Service, DB |
| C06 | Reconciler vs old worker vs new request | One target authority; old fence cannot rollback newer winner; deadlocks handled boundedly. | Real MySQL + helper concurrency |
| S01 | Clean install and upgrade 023/024 to proposed 025 | Exact types/indexes/CHECK/FKs, ownership and uniqueness; no historical row/content change. | Real MySQL |
| S02 | Cross-site FK insert, multiple current attempts, pointer to other target | Ownership rejected by FK; status/lease illegal transitions rejected by service; rollback leaves no partial records. | Real MySQL |
| S03 | History deletion, NULL actors, index limits and query plans | RESTRICT history, SET NULL optional actor; bounded indexed claims/history; MySQL 8.4 referenced-key rules pass. | Real MySQL |
| U01 | Customer/business-admin request to internal routes/CLI impersonation | Denied; no command/DB side effect. | Authorization + HTTP |
| U02 | CSRF/replayed request/stale pointer/admin demotion | Denied or idempotent as specified; POST303GET receipts correct and safe. | HTTP/browser + DB |
| U03 | Internal history/status on mobile/keyboard, errors and drift | Clear environment/current vs observed state; escaped bounded text, accessible focus/receipts. | Browser |

Retain all existing M2–M5 suites and PHP lint as regression gates in implementation
PRs. Real DB concurrency uses independent connections/processes and barriers, not
sequential calls against the fake DB. Kill-point tests cover every committed intent /
external action / result gap. Cleanup uses synthetic IDs, separate fixture roots,
row-count/orphan reconciliation, and explicit authorization for staging cleanup.

## 25. Recommended submilestones and PR sequence

Every row is a separate reviewable PR/gate. File names are proposed; no files/classes
in this table are created by this planning task unless marked documentation.

| Milestone | Scope and expected files/classes | Migration | Local exit gate | Staging exit gate | Depends on |
| --- | --- | --- | --- | --- | --- |
| M6A — Architecture contract | This document; current sprint/handoff/roadmap links; review unresolved host prerequisites and schema contract. | Proposal only; 025 absent. | Markdown/link/fence/status/diff checks, docs-only diff. | None; no access/deploy. | M5 progression complete. |
| M6B — Persistence and jobs | `025_site_build_deployment.sql`; SiteBuildService, locked SiteRevisionManager eligibility and shared authorization helpers; proposed `WebsitePlatformM6MigrationTest.php`, `WebsitePlatformM6BuildServiceTest.php`, DB fixtures. | Create 025 once; additive nine tables and two ownership indexes. | Fake DB behavior + fresh/upgrade real MySQL gate where available; idempotency/lease/failure tests; PHP lint and existing suites. No external publisher. | Separate approved app deploy/migration; exact schema/ownership/uniqueness/concurrency/cleanup PASS before M6 closeout. | Reviewed M6A. |
| M6C — Artifact builder | `SiteArtifactBuilder.php`, `SiteArtifactValidator.php`, `SiteArtifactStore.php`, `LocalSiteArtifactStore.php`, `SitePublicRenderContext.php`; page render extension, repository static CSS; `scripts/site-build-worker.php`; artifact/hash/privacy tests. | Uses 025. | B01–B17 as applicable, deterministic multi-page artifacts, all external I/O outside transactions, immutable/fault tests. | Approved isolated staging build-only fixture validates assets/permissions/bytes without activation. | M6B. |
| M6D — Deployment state and authority | `SitePublisher.php`, `SiteDeploymentService.php`, publisher factory/fake adapter; SiteManager pointer owner, SiteApprovalManager production grants and revision publication gate; `scripts/site-deployment-worker.php`, `scripts/reconcile-site-jobs.php`; deployment/restore/concurrency tests. | Uses 025; schema corrections before applying it or later additive migration if already applied, never rewrite history. | D/R/C unit + real MySQL native-prepare pointer/fence/grant tests, production deny default. | Approved DB-only fixtures prove pointer and approval transactions; no real activation until M6E. | M6B; M6C for real artifact integration. |
| M6E — Apache adapter and host contract | `ApacheDigitalOceanSitePublisher.php`, HTTP checker, restricted `infrastructure/deployment/ubo-site-publish`, Apache example and service supervision examples; bounded staging runbook. | No new migration expected. | Disposable Linux/Apache same-filesystem fixture, fencing/malicious path/config/kill/health/restore tests; no app-wrapper reuse. | Explicit infrastructure approval; section 18 preflight PASS, exact staging binding enabled, real publish/restore/fault/reconciliation PASS with external/DB evidence. | M6C/M6D. |
| M6F — Internal controls | `SiteDeploymentAdminWorkflow.php`, proposed `public/app/admin/site-deployments.php`, private history view; links from site/review; `scripts/manage-site-releases.php` for controlled operator actions. | None. | Role/CSRF/replay/expected-pointer/escaping tests, accessible status and read-only customer boundaries. | Separately approved authenticated internal browser build/staging publish/restore/history gates; customer denials; no production controls enabled. | M6D/M6E. |
| M6G — Integrated validation/closeout | Proposed `docs/sprint-8.8-m6-staging-validation.md`, `docs/sprint-8.8-m6-closeout.md`; required fixes in focused PRs. | Reconcile applied 025, no rerun; future correction number if needed. | All required regression/lint, fault/concurrency tests and final scoped diff. | Final exact-SHA real-MySQL + external runtime + browser matrix, safe logs, synthetic cleanup and baseline reconciliation, retained known-good restore; production remains unauthorized. | M6B–M6F. |

M6A is planning, not implementation start. After its review, the next implementation
task is M6B under a new explicit instruction. Staging-only contract/profile can close
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
expected pointer version and request_key; server revalidates, uses 303 PRG and safe
one-time receipts. Customer Website Manager retains existing M5 review/feedback/
approval actions; customer approval never automatically builds, deploys or requests
production approval. Existing `publicly_deployed=false` becomes an environment-aware
internal read model only when real evidence exists.

## 27. Planning deliverable and validation record

This document is the architecture/schema/API/security/test/submilestone deliverable.
Current planning pointers in the sprint, handoff, roadmap and architecture documents
are updated only where needed; historical M5 evidence/status snapshots remain intact.
All M5 statuses and the deployed application SHA remain unchanged.

Planning validation PASS: 90 relative Markdown links and one referenced anchor across
seven changed documents; balanced fences; current-status consistency and unchanged
M5/production/deployed-SHA invariants; `git diff --check`; documentation-only changed
paths; absent migration 025; byte-identical 023/024 against the audit baseline.
The implementation matrix defines 53 future cases. The temporary local documentation
validator is outside the repository and is not application code or an M6 implementation.
No application suite, real MySQL, staging HTTP/SSH, provider, Apache or production
validation is claimed. A draft documentation PR may be created; it must remain
unmerged. Commit SHA and PR URL are reported with the task result, not self-referentially
embedded into the commit that generates them.

Final planning status: **M6 PLANNING COMPLETE / IMPLEMENTATION NOT STARTED**.
