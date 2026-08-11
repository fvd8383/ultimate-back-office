# Sprint 8.7 Milestone 7 Closeout

## Status

**Sprint 8.7 is COMPLETE EFFECTIVE WHEN THE MILESTONE 7 CLOSEOUT PR IS MERGED TO
MAIN.** While this document is on its review branch, Milestone 7 remains pending that
merge and Sprint 8.7 must not be represented as already closed on `main`.

Milestone 7 is documentation only. It implements no pricing runtime, CMS, publisher,
communications service, provider adapter, migration, configuration, staging change,
or production change.

## Baseline

- Repository: `fvd8383/ultimate-back-office`
- Milestone 7 baseline and Milestone 6 merge commit:
  `fa9228eefbbba94523781599e74ca04e0dbadb22`
- Milestone 6 primary artifact:
  `docs/sprint-8.7-milestone-6-website-platform-audit.md`
- Historical migrations remain immutable.

## Completed Sprint 8.7 Milestones

| Milestone | Result | Evidence / durable outcome |
| --- | --- | --- |
| M1 — Existing Schema Review | COMPLETE | `docs/sprint-8.7-existing-schema-review.md` inventories current foundations and gaps. |
| M2 — Shared Business Profile Schema | COMPLETE; staging validated | Migration `021_shared_business_profile.sql` and its validation record. |
| M3 — Product / Architecture / Pricing / Roadmap Alignment | COMPLETE | Approved product, source-of-truth, pricing, website/EMD, communications, and roadmap boundaries. |
| M4 — Shared Business Profile Service Layer | COMPLETE / PASS | Authorized service, transactions, tenant isolation, lifecycle/readiness/audit behavior; staging PASS. |
| M5 — Shared Business Profile Interface | COMPLETE / PASS | Customer profile/dashboard and admin visibility/lifecycle controls; staging PASS. |
| M6 — Website Platform Architecture and Migration Audit | COMPLETE | Documentation architecture merged at `fa9228eefbbba94523781599e74ca04e0dbadb22`. |
| M7 — Sprint Closeout and Future-Sprint Planning | COMPLETE when this PR merges | Pricing execution gate, Sprint 8.8/8.9 plans, migration sequence, handoff/readiness reconciliation. |

The final validated/deployed Milestone 5 `main` state is
`ea81194e7d853782f927fdf58ed65eecd6473a7f`. This is the final deployed main state
after Milestone 5 implementation, follow-up fixes, and successful validation; it is
not a commit attributable to Milestone 5 alone. The final successful validation
artifact SHA-256 is
`687a1444664f9d7167dfb316510f09094e922c2b83166874849db44fb10382a6`.

## Approved Milestone 6 Architecture

Milestone 6 is approved and authoritative for the implementation direction below:

- generic `sites` identity supports 247SP, EMD, and internal/demo purposes while
  customer/business association remains separate;
- legacy 247SP generation records coexist during additive, idempotent backfill and
  staged consumer cutover;
- site, revision, approval, build, deployment, domain, routing, subscription,
  analytics, and conversion lifecycles remain separate;
- regeneration creates an immutable new revision; approval history is revision-specific;
- repository code owns executable components; database configuration uses approved
  keys/variants/schema versions and contains no executable PHP/JavaScript;
- Website Manager is preview/feedback/change-request/approval, not a customer builder
  or publisher; Internal Admin/Super Admin own controlled composition/publication;
- provider-neutral build/deployment state proves publication; DomainManager state alone
  is not deployment proof;
- Host/domain resolves server-side through active site/deployment/routing to LeadHub;
  browser `business_id` is not authoritative;
- EMD sites use the same generic model without fabricated businesses; both conversion
  directions require rights, data-separation, routing, analytics, validation, approval,
  and audit controls;
- Shared Business Profile and existing business/service/service-area sources remain
  authoritative reusable facts; revisions are presentation snapshots/references;
- service-owned transactions, tenant authorization, CSRF, durable external jobs, safe
  reconciliation, and success audit only after commit are mandatory.

No generic CMS, public publisher, conversion service, registered-site contract,
DataForSEO integration, or cohort-aware billing was implemented by Milestone 6 or 7.

## Approved Pricing And Completed-Signup Rule

There is one 247SP product and four pricing cohorts, not feature tiers:

| Cohort | Positions | Setup | Introductory period | Recurring |
| --- | ---: | ---: | --- | ---: |
| Alpha | 1-5 | $0 | First 6 months free | $79/month afterward |
| Beta | 6-10 | $0 | None | $97/month |
| Founding | 11-25 | $100 one-time | None | $147/month |
| Standard | 26+ | $250 one-time | None | $197/month |

One completed 247SP business signup consumes one permanent customer sequence
position. Cohort assignment occurs atomically as part of successful completion of
that business signup. The transaction creates/confirms the local 247SP business
subscription, allocates the next never-reused sequence, selects the cohort, and locks
commercial terms. Rollback consumes no position; idempotent retry returns the existing
assignment; cancellation does not reopen a position; each independently completed
multi-business signup consumes one.

Anonymous account creation, website launch, first invoice payment, Stripe webhook
receipt, later billing state, and active-customer counts do not determine cohort. This
decision is closed. Later work may define route/service and Stripe mechanics plus
refund, fraud, ownership, reactivation, taxes, overage, setup-fee, and unusual admin
policies without reopening the assignment event.

Alpha stores actual introductory start, expiration, and recurring-start dates once.
Its approved flow is completed business signup -> position 1-5 -> Alpha -> `$0` setup
-> six months free -> automatic `$79/month` recurring billing after stored expiration.

## First-Customer Pricing Gate And Migration Sequence

Immediately after this closeout merges and before Sprint 8.8 M1:

1. Pricing P1 — planned migration `022_247sp_pricing_cohorts.sql`, exact additive
   cohort/counter/allocation/snapshot model, `PricingCohortManager`, tests.
2. Pricing P2 — `BillingFoundation`, `StripeBilling`, completed-signup orchestration,
   cohort-aware Stripe, customer/admin views, webhook/reconciliation behavior.
3. Dedicated staging validation using Stripe test mode, including migration/schema,
   boundary/concurrency/idempotency/rollback, Alpha dates/payment method/transition,
   setup fees, webhooks, UI, logs, cleanup, and reconciliation.

The executable plan is `docs/247sp-pricing-cohort-implementation-plan.md`. This gate is
first-customer critical and must PASS before Sprint 8.8 begins or the first production
247SP business signup is accepted.

Forward migration numbering is locked:

```text
021_shared_business_profile.sql        existing; immutable
022_247sp_pricing_cohorts.sql          planned next pricing migration
023_website_platform_foundation.sql    planned Sprint 8.8 M1 migration
```

Additional Sprint 8.8 migrations use the next available numbers in implementation
order. No migration is created by Milestone 7.

## Analytics And SEO Rule

Google Analytics is an optional, customer-connected, customer-owned and authorized
traffic/visitor analytics integration. It is disconnectable and is not required for
UBO internal SEO reporting. Customer GA history does not transfer to EMD by default.

DataForSEO is the planned platform-owned, server-side provider for internal SEO/ranking
intelligence. FDV credentials remain secret and never enter generated sites or browser
JavaScript. It is not implemented and is not a Sprint 8.8 foundation blocker unless
separately scheduled. GA and DataForSEO are distinct systems.

## Sprint 8.8 Plan

`docs/sprint-8.8.md` defines the executable **Website Platform And Component CMS**
sequence:

1. M1 — migration 023 dependency-safe core, generic schema, legacy compatibility and
   idempotent backfill;
2. M2 — SiteManager, revision/lifecycle/approval services;
3. M3 — repository-owned component registry and validated composition;
4. M4 — admin composition/revision/review workflow;
5. M5 — customer preview/feedback/change request/approval;
6. M6 — durable build, provider-neutral deployment, Apache/DigitalOcean adapter,
   retry/reconciliation/restore;
7. M7 — registered-site LeadHub, domain/routing, EMD compatibility and controlled
   conversion;
8. M8 — complete staging validation, cleanup/reconciliation, closeout evidence.

Migration 023 contains the dependency-safe core, not every operational table. M6 and
M7 may use later additive migrations with the next numbers available.

## Sprint 8.9 Plan And Sprint 8.10 Boundary

`docs/sprint-8.9.md` defines the executable **Communications Core Foundation** sequence:

1. M1 — provider-neutral professional-email foundation and Vendasta adapter;
2. M2 — communications schema and `CommunicationsManager`;
3. M3 — Twilio account/webhook/provider foundation only;
4. M4 — conversation/participant/event services;
5. M5 — LeadHub matching and timeline adapter;
6. M6 — owner takeover and AI pause/resume state;
7. M7 — normalized usage events without overage charging;
8. M8 — staging validation, cleanup/reconciliation, closeout evidence.

Vendasta professional-email provisioning is first-customer critical. Sprint 8.10
remains **Telephony + AI Receptionist**: Twilio subaccounts/numbers/porting/routing,
outbound identity, Retell voice agents, transfers, recordings, transcripts, summaries,
dispositions, LeadHub call history, usage metering, and a pilot. Later approved work
owns SMS/MMS, website chat, complete unified inbox, and overage charging.

## Provider Timing

- Immediately after Sprint 8.7: Stripe test configuration for the pricing gate.
- Sprint 8.8: Namecheap/domain credentials only where approved end-to-end staging
  domain/publishing validation needs them. Optional customer GA is not a CMS blocker;
  DataForSEO is not required for the foundation unless separately scheduled.
- Sprint 8.9: Vendasta test credentials for professional email and Twilio test
  credentials for provider/webhook validation.
- Sprint 8.10: Retell credentials and expanded Twilio telephony configuration.
- Google Calendar: only when real scheduling/calendar synchronization is explicitly
  implemented.

Milestone 7 uses no provider credentials and makes no provider call.

## Unresolved First-Customer Blockers

Architecture completion is not runtime completion. The unresolved implementation and
validation gates include:

- **pricing:** migration 022, atomic sequence, locked terms, Alpha free period,
  cohort-aware Stripe, and dedicated staging validation;
- **website:** Sprint 8.8 generic CMS, revisions/approvals, build/deploy/restore,
  public domain lifecycle, and registered-site LeadHub ingestion;
- **email:** Vendasta professional-email provisioning and support path;
- **communications:** Sprint 8.9 core, Sprint 8.10 telephony/AI receptionist, and later
  SMS/chat/unified-inbox work required for the sold product;
- **providers:** approved test/live credentials, least privilege, production
  configuration, and end-to-end QA at the assigned milestones;
- **operations:** legal/policies, support readiness, first-customer admin/customer QA,
  monitoring/backups/rollback, and explicit production deployment/acceptance gate.

## Explicitly Deferred

Milestone 7 does not create migration 022 or 023; implement pricing, Stripe changes,
CMS, publisher, domains, DataForSEO, GA OAuth, Vendasta, communications, Twilio, Retell,
email provisioning, telephony, AI, SMS/chat, or production configuration; run staging;
or deploy. Detailed refund/reactivation/admin exceptions and later channel/overage
policies remain future decisions without changing the fixed cohort-assignment event.

## Documentation Validation

Milestone 7 branch validation passed before commit:

- `git diff --check`: PASS;
- `git diff --cached --check`: PASS;
- all 16 changed files are `README.md` or Markdown under `docs/`;
- PHP, migration, JavaScript/CSS, runtime, and configuration changes: zero;
- repository-wide status, migration, pricing, analytics, sprint, provider, publishing,
  email, communications, and first-customer searches found no unresolved material
  contradiction;
- unimplemented capabilities remain labeled planned/future.

No staging or production validation is appropriate for this documentation-only
milestone. The PR/final report records the exact files and final Git state.

## Closeout Acceptance Criteria

Milestone 7 is accepted when Milestone 6 is recorded COMPLETE at the merge baseline;
Sprint 8.7 status is conditional only on this PR merge; pricing is the immediate
pre-Sprint-8.8 gate; migration numbers 022/023 are consistent; the completed-business-
signup assignment rule and four prices remain exact; the pricing, Sprint 8.8, and
Sprint 8.9 plans are executable; Vendasta/Twilio/Retell and GA/DataForSEO timing is
correct; first-customer blockers remain open; documentation validation passes; one
draft documentation-only PR is opened; and no runtime/provider/environment state is
changed.

After merge, `main` should treat Sprint 8.7 as closed and the 247SP first-customer
pricing implementation as the next executable work. Sprint 8.8 M1 follows only after
that pricing gate passes.
