# Sprint 8.7 - Shared Business Profile And Website Platform Alignment

## Status

COMPLETE EFFECTIVE WHEN THE MILESTONE 7 CLOSEOUT PR IS MERGED TO MAIN. Milestones 1
through 6 are complete; Milestones 2, 4, and 5 are staging validated. Milestone 5
closed as COMPLETE / PASS on final deployed `main` state
`ea81194e7d853782f927fdf58ed65eecd6473a7f` after its follow-up fixes. Milestone 6
is complete and merged at `fa9228eefbbba94523781599e74ca04e0dbadb22`. Milestone 7
is this documentation-only closeout and is complete when its PR merges.

## Product

24/7 Sales Partner

## Objective

Align 247SP product, pricing, website-generation, EMD lifecycle, internal integration, and future communications architecture around one structured Shared Business Profile, then establish its reusable application service and customer-facing management workflow.

24/7 Sales Partner is a done-for-you lead-generation and digital-front-office platform powered by one structured Business Profile. It generates a custom website, captures forms, calls, texts, and chats, provides immediate AI-assisted responses, and keeps every opportunity organized in LeadHub.

---

# Status Vocabulary

* **Existing:** confirmed in the repository.
* **Complete:** implementation milestone merged.
* **Staging validated:** runtime or schema behavior verified on staging and recorded in repository documentation.
* **Planned:** approved future direction with no claim of implementation.
* **Proposed:** candidate name or design requiring repository/schema review.
* **Deferred:** intentionally outside the current milestone or sprint.

---

# Confirmed Current State

Existing foundations include business onboarding, 247SP onboarding, one starter website template, generated pages and private previews, customer and admin website editors, LeadHub contacts/notes/tasks/activity, website lead capture, Stripe billing integration, Domain Services, and email request/assignment records.

Milestone 1 reviewed the existing schema and architecture in `docs/sprint-8.7-existing-schema-review.md`.

Milestone 2 added migration `021_shared_business_profile.sql`. Migration 021 created the initial provider-neutral Shared Business Profile root and child tables, was applied to staging, and passed the validation recorded in `docs/sprint-8.7-migration-021-validation.md`.

Migration 021 must not be rewritten. Historical migrations 019 and 020 must not be rewritten or rerun to support later Sprint 8.7 work.

The Shared Business Profile application service is implemented in `private/classes/SharedBusinessProfile.php`, staging validated as PASS on deployed commit `d11bd0e7d14b9d9dd432f3ce244a9b2bbebfafb7`, and documented in `docs/shared-business-profile-service-layer.md`.

The Milestone 5 customer-facing Shared Business Profile interface and admin visibility
are deployed and staging validated. The component CMS, site revision/deployment system,
portable site conversion system, DataForSEO integration, cohort-aware billing,
`CommunicationsManager`, provider interfaces/adapters, unified conversation inbox, and
private MCP gateway do not currently exist.

---

# Milestone Sequence

## Milestone 1 - Existing Schema And Architecture Review

Status: Complete

Deliverables:

* Repository inventory of business, service, service-area, website, LeadHub, billing, domain, and email foundations
* Source-of-truth and compatibility risks
* Migration 021 recommendation
* Communications schema deferred boundaries

## Milestone 2 - Shared Business Profile Schema

Status: Complete and staging validated

Deliverables:

* `business_profiles`
* Profile hours and hour exceptions
* FAQs and pricing guidance
* Appointment, transfer, and escalation rules
* Notification preferences
* One draft profile backfilled per existing business
* Composite business/profile foreign-key enforcement
* Staging validation record

Migration 021 is the initial business-knowledge schema. It does not contain every future branding, trust, marketing, SEO, image, or media category.

## Milestone 3 - Product Definition, Architecture, Pricing, And Roadmap Alignment

Status: Complete

Deliverables:

* Updated 247SP definition and pricing cohorts
* Shared Business Profile source-of-truth model
* Business-facts versus channel-presentation model
* Website-generation and component CMS architecture
* Shared 247SP/EMD website infrastructure
* EMD demo and demo-to-247SP lifecycle
* Cancellation-to-EMD review model
* Domain/site/customer-data ownership distinctions
* Lead-routing and data-separation requirements
* Revision, approval, and audit controls
* Standardized website-to-LeadHub ingestion direction
* Internal service-layer and MCP direction
* Customer integration policy
* Revised future-sprint sequence and readiness model

This milestone changes documentation only. It does not implement PHP, migrations, database tables, billing changes, providers, communications, MCP, or production configuration.

## Milestone 4 - Shared Business Profile Service Layer

Status: Complete and staging validated - PASS

Scope:

* Authorized profile reads and writes
* Business-level tenant checks
* Input validation and transactions
* Audit hooks
* Readiness calculation
* Normalized DTOs or data structures
* Reusable service methods for UI, jobs, internal APIs, and future MCP tools

No MCP gateway implementation is included.

Implementation notes:

* Every public method performs explicit business-scoped authorization using active membership or the existing internal administrator role.
* Mutations use transactions, profile-row locking, child ownership checks, live readiness calculation, lifecycle demotion, and safe `activity_logs` audit summaries.
* Migration 021 remains unchanged and no new migration is required.
* Runtime, tenant-isolation, readiness, lifecycle, rollback, cleanup, and complete regression-smoke validation passed on staging.
* The final validation record is `/home/codex-validation/ubo-sbp-validation/MILESTONE-4-REGRESSION-RERUN-3.md`, SHA-256 `8ea329ecc1f1515eaafe28cf5284d6e6f6a97bc61ec010b106e4a67620f849b4`.
* PR #85 corrected ANSI-unsafe double-quoted SQL literals in the Domains DNS-record query. PR #86 replaced ascending `FIELD(...)` ordering with explicit `CASE` ranking and deterministic secondary ordering. Neither fix required a migration or schema change.
* Earlier blocked reports remain retained audit evidence for the Domains PDOException, both query corrections, the temporary log-permission blocker, and each required stop point.

## Milestone 5 - Shared Business Profile Interface

Status: COMPLETE / PASS

Scope:

* Customer-facing profile sections
* Draft saving
* Readiness and missing-information indicators
* Admin visibility
* Progressive disclosure
* Existing service and service-area reuse
* No customer page builder

Implementation notes:

* Customer route: `public/app/247sp/business-profile.php`
* All migration-021 sections use section-specific POST actions, post/redirect/get, reusable session-backed CSRF, and `SharedBusinessProfile` mutations.
* Identity, services, and service area are read-only summaries linked to their existing authoritative editors.
* The 247SP dashboard uses live `SharedBusinessProfile` readiness instead of the legacy contact-field completion calculation.
* Customers may submit draft/incomplete profiles for review; they cannot directly mark ready or active.
* `public/app/admin/business.php` provides read-only profile/readiness summaries and service-enforced lifecycle transitions, including admin-only activation.
* Collection replacement requires submitted rows or explicit removal/empty confirmation; absent or malformed collections do not replace stored rows.
* No migration or schema change is required.
* The final validated/deployed state is commit `ea81194e7d853782f927fdf58ed65eecd6473a7f`, the final `main` state after Milestone 5 implementation and fixes.
* Final successful validation SHA-256: `687a1444664f9d7167dfb316510f09094e922c2b83166874849db44fb10382a6`.

## Milestone 6 - Website Generation, Site Lifecycle, And Component Audit

Status: COMPLETE — merged at `fa9228eefbbba94523781599e74ca04e0dbadb22`

Scope:

* Inspect existing website tables, rendering, and editor behavior
* Identify reusable components
* Review site ownership/control, customer/business associations, and EMD associations
* Review domains, lead routing, demo support, site purpose, and lifecycle
* Review revisions, archival, restoration, and conversion history
* Review transfer eligibility and customer-data separation
* Review both EMD-to-247SP and 247SP-to-EMD conversion
* Produce an implementation-ready schema and migration plan
* Define optional customer-owned Google Analytics versus planned platform-owned DataForSEO
* Replace stale pricing with Alpha/Beta/Founding/Standard cohort architecture and document the first-customer-critical billing gap

The primary artifact is `docs/sprint-8.7-milestone-6-website-platform-audit.md`. The
complete CMS, publisher, cohort billing, and DataForSEO integration are not implemented
during Sprint 8.7.

## Milestone 7 - Sprint Closeout And Future-Sprint Planning

Status: COMPLETE effective when the Milestone 7 closeout PR merges

Scope:

* Confirm Business Profile service and interface status
* Confirm website-generation audit status
* Confirm documentation consistency
* Produce executable Sprint 8.8 and Sprint 8.9 plans
* Schedule the first-customer-critical pricing-cohort implementation
* Lock planned pricing migration `022_247sp_pricing_cohorts.sql` and initial Sprint 8.8 migration `023_website_platform_foundation.sql`
* Define Sprint 8.8 staged PRs and staging validation
* Keep full CMS and communications implementation outside Sprint 8.7

---

# Shared Business Profile Direction

The Shared Business Profile is the central business-knowledge layer:

```text
Shared Business Profile
  |-- Website
  |-- Voice agent (planned)
  |-- SMS assistant (planned)
  |-- Website chat (planned)
  |-- LeadHub routing
  |-- SEO and schema
  |-- Notifications
  `-- Future marketing automation
```

Business facts are maintained once. Channel wording may vary while remaining grounded in approved facts. Website presentation records own page composition; provider integration records own provider identifiers; LeadHub/routing configuration owns lead routing.

Future source-of-truth reviews must cover branding, social, licenses, certifications, insurance, awards, guarantees, testimonials, review references, before-and-after media, target customers, conversion goals, differentiators, SEO cities/keywords, and image/media libraries. These must not all be appended to `business_profiles` by default.

---

# Website Platform Direction

The planned website system has three layers:

1. Authoritative structured business data.
2. Repository-owned approved components and variants selected through structured page records.
3. AI-assisted presentation constrained to approved components, facts, and platform services.

The MVP is a done-for-you structured CMS, not a customer drag-and-drop builder. Customers manage business facts; FDV manages presentation, revisions, approval, and publishing.

24/7SP and EMD Network share the planned site-brief, component, theme, analytics, SEO, LeadHub form, tracking, deployment, revision, validation, and image infrastructure. Product/site purpose controls lead routing; it does not create a separate website engine.

See `docs/247sp-website-generation-architecture.md`.

---

# LeadHub Ingestion Direction

Website submissions use a narrow, write-oriented inbound contract. Registered site identity resolves the authoritative business; submitted `business_id` is not trusted by itself.

LeadHub validates the site, matches or creates the contact, creates or updates the opportunity, records submission history, applies spam and routing rules, preserves attribution, prevents supported duplicates, and surfaces follow-up.

The ingestion endpoint must provide rate limiting, replay protection, site/domain credential validation, no browser-exposed API secret, and an auditable correlation ID. It is not broad customer API access.

LeadHub remains the system of record for every supported channel and the future unified timeline.

---

# Site Lifecycle And Conversion Direction

Approved future site purposes are `247sp`, `emd`, and `internal_demo`. Purpose is
separate from lifecycle and business association; an EMD site does not require a
fabricated business.

Planned bidirectional lifecycle:

```text
EMD/internal demo
  -> approved conversion to purchased 247SP site
  -> active customer operation
  -> customer cancellation
  -> eligibility, rights, and conversion review
  -> approved conversion to EMD property, suspension, archive, or retention hold
```

Site build, site purpose, lifecycle, ownership/control, customer relationship, business association, domain ownership, lead routing, CRM data, analytics ownership, and provider accounts are independent concepts.

Every conversion requires eligibility review, explicit approval, validation, and audit history. Customer CRM data and private communications never transfer to an EMD property. Customer routing is removed before EMD routing is configured.

FDV-owned and customer-owned domains are reviewed separately. A customer-owned domain is not retained or reassigned without authority and authorization, even if reusable site structure remains with the platform.

---

# Internal MCP And Customer Integration Direction

MCP is planned for internal administrative use only and sits above stable UBO services. It is not a customer 247SP feature.

Generic database, shell, PHP, record-editing, and arbitrary-API tools are prohibited. Narrow tools use authentication, scopes, tenant checks, approval rules, idempotency, and audit records.

Customer integrations are narrow source-specific ingestion channels. Customers do not receive MCP, general API keys, direct database access, broad public reads, generic GraphQL, continuous database replication, or unrestricted bulk extraction. Customers retain normal access to their records, communications, reports, documents, and files.

See `docs/internal-mcp-and-integration-access-strategy.md`.

---

# Approved 247SP Pricing

| Pricing cohort | Customer positions | Setup fee | Introductory period | Recurring price |
| --- | ---: | ---: | --- | ---: |
| Alpha | 1-5 | $0 | First 6 months free | $79/month afterward |
| Beta | 6-10 | $0 | None | $97/month |
| Founding | 11-25 | $100 one-time | None | $147/month |
| Standard | 26+ | $250 one-time | None | $197/month |

These cohorts price the same core product. They are not feature tiers.

Included monthly usage:

* 200 AI minutes
* 500 outbound owner minutes
* 500 SMS segments
* 500 AI chat responses

Usage above each allowance is overage usage. Unit rates must be defined in an approved pricing plan, order form, or billing policy before charging customers.

One completed 247SP business signup consumes one permanent customer sequence position.
Cohort assignment occurs atomically as part of successful completion of that business
signup. Anonymous account creation, website launch, first invoice payment, Stripe
webhook receipt, later billing-state changes, and active-customer counts do not determine
the cohort. Multiple businesses under one owner consume one position for each
independently completed signup, and cancellations do not reopen positions.

Milestone 7 may define the responsible route/service, Stripe mechanics, idempotent
retry, and detailed refund/reactivation/admin-exception policy. It must not reopen the
approved completed-business-signup assignment event.

The future subscription stores its sequence, assigned cohort, locked fees, assignment
and signup dates, introductory start/expiration, recurring billing start, and applicable
Stripe references/version. The current billing runtime does not implement this model or
Alpha's free period; the focused implementation is first-customer critical.

---

# Future Sprint Sequence

No existing Sprint 8.8, 8.9, or 8.10 plan conflicts were found in the repository. The architectural sequence is:

## Pre-Sprint 8.8 Gate - 247SP First-Customer Pricing Implementation

Immediately after Sprint 8.7 closes, implement pricing P1, pricing P2, and the dedicated
staging validation in `docs/247sp-pricing-cohort-implementation-plan.md`. Planned
migration 022 belongs to pricing. This gate must pass before Sprint 8.8 M1.

## Sprint 8.8 - Website Platform And Component CMS

Use the executable staged M1-M8 sequence in `docs/sprint-8.8.md`: planned migration
023 and schema/backfill; SiteManager and
revision/approval services; component composition; admin workflow; customer review;
build/deploy/restore; registered-site routing and EMD compatibility; then full staging
validation.

This is not a customer drag-and-drop builder.

## Sprint 8.9 - Communications Core Foundation

The executable plan is `docs/sprint-8.9.md`. Vendasta professional-email provisioning
is first-customer critical. Planned scope also includes `CommunicationsManager`,
provider-neutral records/services, the shared Twilio account/webhook foundation,
conversations, participants, events, contact matching, owner takeover, AI pause/resume,
usage events, LeadHub timeline adapter, and webhook idempotency.

## Sprint 8.10 - Telephony And AI Receptionist

Expected scope includes Twilio subaccounts and local phone numbers, Retell voice agents, inbound routing, transfers, recordings, transcripts, summaries, dispositions, LeadHub call history, usage metering, and a pilot.

## Later Sprint - Messaging And Website Chat

Expected scope includes SMS/MMS, A2P registration, website chat, AI chat, owner takeover, unified inbox, usage, and overages.

---

# Production Readiness Layers

Readiness is tracked independently for:

* Business Profile
* 247SP customer site
* EMD demo
* Demo-to-customer conversion
* Customer-to-EMD conversion
* Communications
* Commercial launch

Planned categories must not be marked complete until implementation and required staging validation are recorded. See `docs/production-readiness-review.md`.

---

# Milestone 3 Out Of Scope

* PHP or application-code changes
* SQL migrations or database tables
* Twilio, Retell, MCP, website-component, communications, or billing implementation
* Provider provisioning or live API calls
* Production or staging configuration changes
* Automatic site conversion, domain transfer, routing change, or publication
* Milestone 4 service-layer implementation

---

# Milestone 3 Acceptance Criteria

* Current product documents use the approved 247SP definition and cohort pricing.
* The website is documented as a presentation layer of structured facts.
* Migration 021 is accurately described as complete and staging validated.
* Proposed CMS, conversion, communications, and MCP capabilities are not presented as existing.
* LeadHub is the system of record and website ingestion is narrow and write-oriented.
* Shared 247SP/EMD infrastructure and both approval-controlled conversion directions are documented.
* Domain ownership, customer data, analytics, and routing are separated.
* Sprint 8.7 milestones and future sprint sequence are consistent.
* Documentation-only scope is preserved.
* `git diff --check` and `git diff --cached --check` pass.

---

# Recommended Next Task

Merge the Milestone 7 documentation-only closeout after review. The next executable
work is the first-customer-critical pricing P1/P2 implementation and staging gate in
`docs/247sp-pricing-cohort-implementation-plan.md`. Sprint 8.8 M1 begins only after
that gate passes.
