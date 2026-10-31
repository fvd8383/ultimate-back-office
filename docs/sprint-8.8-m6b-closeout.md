# Sprint 8.8 M6B — Staging closeout and M6C handoff

Current follow-up (October 17, 2026): [PR #127 acceptance](https://github.com/fvd8383/ultimate-back-office/pull/127#issuecomment-6241852763) establishes **M6B — COMPLETE / STAGING PASS / FORMALLY CLOSED**. **M6C — IMPLEMENTED LOCALLY / REVIEW REQUIRED** in the authorized local implementation task; M6D–M6G NOT STARTED. Migration 025 remains APPLIED TO WORKING STAGING; Production UNAUTHORIZED / NOT DEPLOYED. The original dated record below is retained as historical evidence, including pre-acceptance wording and original failures. No M6B native rerun or operational-evidence retrieval is implied.

Proposed October 17, 2026: **M6B — COMPLETE / STAGING PASS / CLOSEOUT REVIEW PENDING**.
This documentation PR requests formal acceptance of the proposed closeout. It does
not claim that acceptance, review or merge has already occurred. The proposal uses
hash-verified native records, supplied operator deployment/migration results and
the completed browser smoke, with the unavailable raw operational reports disclosed.

| Milestone or environment | Current status |
| --- | --- |
| M6A | ARCHITECTURE REVIEWED / MERGED |
| M6B | COMPLETE / STAGING PASS / CLOSEOUT REVIEW PENDING |
| M6C | NEXT / NOT STARTED; implementation requires a separate task |
| M6D–M6G | NOT STARTED |
| M6 | IN PROGRESS |
| Migration 025 | APPLIED TO WORKING STAGING — operator-executed October 17, 2026 |
| Production | UNAUTHORIZED / NOT DEPLOYED; production migration unperformed |
| M5 | COMPLETE FOR SPRINT PROGRESSION |
| M5C | ACCEPTED / NARRATOR FOLLOW-UP DEFERRED |

## Baseline and authority

Repository: `fvd8383/ultimate-back-office`. GitHub main was verified at
`f466fc1cdbfb53325a1db11445736bdc5ea7c6f4`, exactly the operator-supplied deployed
baseline. [PR #126](https://github.com/fvd8383/ultimate-back-office/pull/126) is
**MERGED**, at that commit on October 8, 2026, 22:39:25 UTC. It remains closed.
The clean local worktree was preserved; the new documentation branch
`codex/sprint-8.8-m6b-closeout` starts at that verified main commit.

Native execution belongs to `bef67310adafa041a45ee204ed0fc99ca13b754f`. The delta
from that source to merged main contains documentation only. No earlier native
result is attributed to changed executable code. This closeout does not rerun
deployment, migration, Docker/native/PHP tests, or direct SQL; change configuration,
certificates, wrappers, permissions or identities; create business fixtures; or
implement M6C. Normal permitted browser authentication/logout is a separate scope.

## Native persistence and concurrency evidence

The operator executed one isolated native gate on October 8, 2026. The
[dated native record](sprint-8.8-m6b-native-validation.md) remains the detailed
coverage and historical review reconciliation. It records **1824 native assertions**,
**53/53 canonical and fixture migration executions**, all twelve historical-replay
conditions true, and completion of the later rollback, exhausted-queue, revocation
and recovery cases. Launcher/test exits were 0/0; cleanup, publication and manifests
passed. These are earlier operator-executed results, not tests run by this task.

This task retrieved six existing summary/report/manifest files, **49,767 bytes**,
through established `ubo-validation` access as `codex-validation` on `ubo-stage-app`.
Regular-file, no-symlink, exact resolved-path and bounded-size checks preceded
byte-preserving SCP. All 252 operator-manifest and three launcher-manifest paths
were validated as unique plain basenames before checking the selected local files.
Only retrieved entries were rehashed; the remaining manifest entries and the prior
archive were not retrieved or independently reverified in this task.

| Retrieved original file | SHA-256 verified in this task |
| --- | --- |
| Native `M6B-HISTORICAL-REPLAY-AND-MYSQL-VALIDATION.md` | `8cf8794fbb2fec7ee9c0d21fa1e308d085f883f9808ed39fb825e591bc94e08b` |
| Native `summary.sha256` | `8ca20e9e3983a307c844e34faca63c8c22e3f39368a806261a84ba16a296fc04` |
| Native `SHA256SUMS` | `e7c2ff02330e2586536b71df0709444299814164295a9e2e372c00cf2e062417` |
| Native `manifest.sha256` | `e39f7063503033517ea15bcbff10693dea7633d4215059712db03a4dc81dcbf4` |
| Launcher `report.txt` | `a8315f55a35b21b8e734f32767b8262425c4fe5e85a892bd186ec87bd3094b0f` |
| Launcher `SHA256SUMS` | `c00eb3c28dcabce7680c42cf2ddfbff5a7b23df34e5549c7872400ac453857d2` |

Remote directories are respectively
`/mnt/ubo_stage_testdata/codex-validation/evidence/m6b-replay-validation-20261008T001902Z`
and `/mnt/ubo_stage_testdata/codex-validation/evidence/run-20261008T002107Z-P9dqtQHb`.
Original bytes and a path/size/hash inventory remain outside Git under
`C:/Users/fvd83/.codex/visualizations/2026/10/17/01a14bbe-6def-7f42-bbd6-f77e8c533531/m6b-closeout`.
No credentials, environment files, authentication storage or private customer data
are part of the documentation or PR.

## Working-staging code deployment

The operator supplied completed deployment results: exact baseline
`f466fc1cdbfb53325a1db11445736bdc5ea7c6f4` deployed through the approved wrapper
once; exit 0, clean worktree, PHP lint and Apache reload passed. Configuration,
CA and operational wrappers were preserved. This task did not execute those checks.

Evidence directory:
`/home/ubo-deploy/m6b-staging-code-deployment-20261017T202541Z-Vn0qS6`.
One established-identity access check found it inaccessible. Frank has been asked
for the bounded report/SUMMARY.txt and relevant manifest through his existing
access. No deployment report has yet been independently retrieved/hash-verified
here; these results remain supplied operator evidence.

## Working-staging migration 025 and schema

The operator reports migration 025 **applied once**, October 17, 2026,
**20:47:58–20:48:01 UTC**, through the approved wrapper with exit 0. Verification
matched nine tables, 228 columns, 73 explicit indexes including the two existing-table
additions, 42 foreign keys, 30 enforced CHECKs and all six deferred relationships.
All nine new tables contained zero rows; `site_business_associations` and
`site_approvals` remained at zero rows. Limited HTTPS checks returned expected
302/302/200. The operator reports all 116 manifest entries verified.

Named evidence:
`/home/ubo-deploy/m6b-working-staging-migration-025-20261017T204227Z/M6B-WORKING-STAGING-MIGRATION-025.txt`.
Reported SHA-256:
`d8d7b6d73cd4dd653076990f411b71766b63e9bd82c0f9fd2a87fa2566e5dc75`.
This directory was inaccessible through the same single established-identity check.
The report/SUMMARY.txt and relevant manifest are requested from Frank; this digest
is supplied, not independently verified here yet. That access limitation does not
make migration 025 unapplied. Production migration remains unperformed.

## TLS and operational wrappers

The supplied migration results report verified-TLS reads using **MySQL 8.4.8 /
TLS 1.3**, distinct from isolated native MySQL 8.4.11. The deployment results report
preserved application configuration, CA and approved wrappers. Details of earlier
TLS configuration and wrapper changes await the bounded operational reports; no
unseen settings, files or change history are inferred. This task changes none of
them and does not weaken TLS verification or request broader privileges.

## Limited authenticated browser smoke

**PASS for all applicable checks**, October 17, 2026, approximately **21:32–21:34 UTC
(17:32–17:34 EDT)**. Execution provenance: Frank completed normal sign-in/OTP himself;
Codex used the approved Chrome browser tooling for the observations, navigation,
refreshes, logout and protected-page checks. The existing operator-controlled staging
account was used; account identifiers and business details are omitted here.

Before sign-in, an old cached authenticated page redirected to the normal login
page on Home navigation. Frank reported an Internet interruption during an initial
OTP attempt, then confirmed reconnection and sign-in. The cause of the interruption
was not independently diagnosed; the subsequent authenticated session was observed
and refreshed successfully. No application regression was established.

| Page/action | Correct staging origin and page identity | Observed result |
| --- | --- | --- |
| Confirm session / dashboard refresh | `https://staging-accounts.ultimatebackoffice.com/dashboard.php`; Accounts Dashboard | PASS: authenticated identity and normal overview rendered; refresh preserved the session and page. No visible database/schema error or unexpected server-error page. |
| Normal 247SP navigation | `https://staging-app.ultimatebackoffice.com/247sp/dashboard.php`; 24/7 Sales Partner | PASS: followed the existing workspace link; dashboard and Website Manager entry rendered normally. No visible database/schema error or unexpected server-error page. |
| Website Manager / refresh | `https://staging-app.ultimatebackoffice.com/247sp/website-manager.php`; 247SP Website Manager | PASS: followed the existing menu; refreshed without losing page/session. Expected review empty state: “No website revision is currently waiting for your review.” Existing legacy settings remained visible and were not edited. No visible database/schema error or unexpected server-error page. |
| Internal site/review list | Current account's permitted navigation | NOT APPLICABLE: this business account exposed no internal/admin site or review entry. No internal role was demonstrated; no alternate account, role, guessed admin route or impersonation was used. This is a scope limitation, not an application failure or an internal-role authorization test. |
| Lead Hub / refresh / return Home | `https://staging-app.ultimatebackoffice.com/dashboard.php`; Lead Hub Dashboard, then accounts Home | PASS: normal menu navigation, refresh and return Home worked. No visible database/schema error or unexpected server-error page. |
| Logout | `https://staging-accounts.ultimatebackoffice.com/logout.php` to `/login.php`; Login | PASS: normal Log out link returned to “Sign in with a one-time code.” |
| Protected pages after logout | Previously visited Website Manager and accounts dashboard URLs | PASS: each direct navigation returned to the accounts sign-in page. Protected content was not shown; no visible database/schema error or unexpected server-error page. |

Browser results describe visible page behavior; exact HTTP status codes were not
collected and no network/HAR export was made. Dynamic business query values and
private account content are omitted from this record. Minimal sanitized observations
and one blank post-logout sign-in screenshot are retained outside Git in the local
evidence directory. The staging tab is left logged out.

The expected empty review state is consistent with the operator-reported zero
associations/approvals; no data was created to obtain a populated screen. No feedback,
approval, settings save, build, payment, domain or provider action was submitted.
M6F build controls and the full M5 accessibility matrix were outside scope; Narrator
remains deferred. Normal authentication/session/audit writes occurred or may occur
through sign-in, refresh and logout; they were not inspected with SQL and are separate
from intentional business-data changes. **Zero database writes is not claimed**. No
OTP, cookie, authentication storage or HAR was read, retained or exported by Codex.

## Cleanup, migration integrity and operational limitations

Native cleanup evidence records both disposable databases dropped, owned container
and live processes/cgroups absent, private client/scratch files removed, and Docker
returned inactive/dead/disabled. Three preexisting failed-unit metadata records were
retained without live processes/cgroups. These are saved run observations, not new
runtime checks. Earlier FAILED/BLOCKED/INCOMPLETE reports keep their original verdicts
and hashes; no immutable report was replaced.

The exact reviewed **017/019 bootstrap-source exception** remains unchanged:

| Migration | Reviewed Git blob | Canonical SHA-256 |
| --- | --- | --- |
| `017_domain_services_automation.sql` | `a8345bed9669e2e43b29a3c40de593cbce1f44de` | `eab87000d3796c8b6d3fb6c8c4a85c1d6a56253620f6d465d7343b78aaa6392f` |
| `019_repair_domain_services_schema.sql` | `de2d1ce8c3481ae034383be4b138a446650d5d51` | `b420624b28c4f5c6eba129f1ac47bba3e871286156d54fd52793d98aacc9108b` |
| `025_site_build_deployment.sql` | `e609ed5634f7f14cc9acc5ad5f652118978e2044` | `dab585dc29aac11153f92703c65d3883aeea73a1b2283157cfa9d2f2ece85cb0` |

These canonical Git bytes were hash-verified locally in this task. The 017/019
exception substitutes unchanged 020's stored record-hash DNS identity in exactly
those two bootstrap CREATE definitions. All other 22 historical migrations and
020/023/024 remain unchanged. **Neither 017 nor 019 was replayed on working staging**,
per the operator's completed-operation scope; this task performs no replay or
applied-history rewrite.

Frank Dalba confirmed the correct `ubo-stage-mysql` cluster and recovery availability
on October 17, 2026, **16:44 EDT / 20:44 UTC**. This is operator-confirmed availability,
**not a tested restore or a permanently retained backup**. Apache logs were inaccessible
during the migration checks and remain an explicit limitation, not a log PASS. No
root/sudo access, identity switch or permission change is requested to overcome it.

## M6C handoff and remaining M6 work

The [reviewed M6 sequence](sprint-8.8-m6-implementation-plan.md#25-recommended-submilestones-and-pr-sequence)
remains the architecture. M6C is the next separate task, not started here:

- Build trusted input from the exact approved immutable revision and existing M6B eligibility boundary.
- Generate deterministic multi-page static artifacts using the reviewed renderer/public-context contracts.
- Project only permitted public content; validate paths, assets, rights, hashes and private-data exclusion.
- Store immutable releases through the approved artifact-store abstraction and integrate the build-worker path with M6B persistence.
- Keep forms inert; do not activate public sites or deployment targets. Use existing 025; closing M6B does not require another migration.

M6D retains deployment state/authority, M6E the Apache adapter and host contract,
M6F internal build/deploy/restore controls, and M6G integrated validation/closeout.
The native gate plus this limited smoke do not establish all **77 planned M6
acceptance cases**, artifact determinism/privacy/publication, deployment/restore
fault and concurrency behavior, full accessibility PASS, production readiness or
first-customer readiness. M6 and Sprint 8.8 remain IN PROGRESS.

## Documentation validation and review

The completed limited smoke and earlier native PASS support this proposed closeout
alongside the supplied operator deployment/migration results. Raw deployment/migration
reports and detailed prior TLS/wrapper change history were requested but remain
unavailable; their independent hash verification is not claimed. Formal review must
assess these disclosed provenance limits. Apache-log access and recovery limitations
remain unchanged. Local documentation checks passed:
**150 relative links / 12 anchors**, balanced fences, whitespace/diff checks, current
status consistency and exact eleven-document scope. All **339 tracked paths outside
`docs/`** retain the same Git blobs and modes as verified main and the natively tested
source; local raw-file hashes are captured separately. This covers all migration SQL,
source, tests, launchers, guards, configuration and infrastructure. No PHP/native
test was rerun. The dated native record changes only by adding its current-status link.

| Change | Document |
| --- | --- |
| Add | `docs/sprint-8.8-m6b-closeout.md` |
| Modify | `docs/codex-handoff.md` |
| Modify | `docs/sprint-8.8.md` |
| Modify | `docs/sprint-8.8-m6-implementation-plan.md` |
| Modify | `docs/sprint-8.8-m6b-local-implementation.md` |
| Modify | `docs/sprint-8.8-m6b-linux-validation.md` |
| Modify, historical-status link only | `docs/sprint-8.8-m6b-native-validation.md` |
| Modify | `docs/first-customer-checklist.md` |
| Modify | `docs/database-plan.md` |
| Modify | `docs/production-readiness-review.md` |
| Modify | `docs/247sp-website-generation-architecture.md` |

Final working/staged/committed proof, commit ID, PR URL and exact-head review state
are recorded in the external SUMMARY.txt and PR after the commit exists, avoiding
a self-referential commit ID. The changed-file inventory is limited to the documents
above; neither source/test inputs nor applied migration history are changed.

The new documentation PR requests formal M6B closeout acceptance and one exact-head
review unless already running. It remains unmerged with auto-merge disabled. A review
request is not a completed review or formal acceptance. Production stays unauthorized,
M6C stays next/not started, and M6 stays in progress.
