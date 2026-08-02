# Sprint 8.7 - Shared Business Profile And Website Platform Alignment

## Status

In progress. Milestones 1 and 2 are complete; Milestone 2 is staging validated; Milestone 3 is the current documentation task.

## Product

24/7 Sales Partner

## Objective

Align 247SP product, pricing, website-generation, EMD lifecycle, internal integration, and future communications architecture around one structured Shared Business Profile before application service-layer work begins.

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

The component CMS, site revision system, portable site conversion system, `CommunicationsManager`, provider interfaces/adapters, unified conversation inbox, and private MCP gateway do not currently exist.

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

Status: Current documentation task

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

Status: Planned

Scope:

* Authorized profile reads and writes
* Business-level tenant checks
* Input validation and transactions
* Audit hooks
* Readiness calculation
* Normalized DTOs or data structures
* Reusable service methods for UI, jobs, internal APIs, and future MCP tools

No MCP gateway implementation is included.

## Milestone 5 - Shared Business Profile Interface

Status: Planned

Scope:

* Customer-facing profile sections
* Draft saving
* Readiness and missing-information indicators
* Admin visibility
* Progressive disclosure
* Existing service and service-area reuse
* No customer page builder

## Milestone 6 - Website Generation, Site Lifecycle, And Component Audit

Status: Planned

Scope:

* Inspect existing website tables, rendering, and editor behavior
* Identify reusable components
* Review site ownership/control, customer/business associations, and EMD associations
* Review domains, lead routing, demo support, site purpose, and lifecycle
* Review revisions, archival, restoration, and conversion history
* Review transfer eligibility and customer-data separation
* Review both EMD-to-247SP and 247SP-to-EMD conversion
* Produce an implementation-ready schema and migration plan

The complete CMS is not implemented during Sprint 8.7 unless separately approved.

## Milestone 7 - Sprint Closeout And Future-Sprint Planning

Status: Planned

Scope:

* Confirm Business Profile service and interface status
* Confirm website-generation audit status
* Confirm documentation consistency
* Produce executable Sprint 8.8 and Sprint 8.9 plans
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

Proposed site purposes are `business_owned`, `emd_lead_property`, `emd_demo`, `internal_demo`, and `internal_marketing`.

Planned bidirectional lifecycle:

```text
EMD/internal demo
  -> approved conversion to purchased 247SP site
  -> active customer operation
  -> customer cancellation
  -> eligibility and transfer review
  -> approved conversion to EMD property, suspension, archive, retention hold, or policy-based deletion
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

| Customer cohort | Customer numbers | Setup fee | Monthly price |
| --- | ---: | ---: | ---: |
| Beta Users | 1-5 | $0 | $79/month |
| Founding Users | 6-25 | $100 | $97/month |
| Standard Users | 26+ | $250 | $147/month |

These cohorts price the same core product. They are not feature tiers.

Included monthly usage:

* 200 AI minutes
* 500 outbound owner minutes
* 500 SMS segments
* 500 AI chat responses

Usage above each allowance is overage usage. Unit rates must be defined in an approved pricing plan, order form, or billing policy before charging customers.

Open policy questions include grandfathering, price increases, reopened positions, the qualifying position event, failed/refunded/fraudulent accounts, returning customers, ownership changes, multi-business counting, taxes, allowance/rate differences, and setup-fee refunds/reactivation.

Recommended but not approved: each independently activated business subscription counts when it becomes active, stores its assigned cohort, and does not change cohort merely because later customers cancel.

---

# Future Sprint Sequence

No existing Sprint 8.8, 8.9, or 8.10 plan conflicts were found in the repository. The architectural sequence is:

## Sprint 8.8 - Website Generation And Component CMS Foundation

Expected scope includes site identity/purpose/lifecycle; customer, business, EMD, domain, and routing associations; pages and sections; component registry and variants; themes and briefs; demos and both conversion directions; data separation; conversion audit; revisions and approval; build/deployment jobs; archival/restoration; shared LeadHub forms; validation; initial AI-assisted assembly; and internal presentation-editor planning or initial implementation.

This is not a customer drag-and-drop builder.

## Sprint 8.9 - Communications Core Foundation

Expected scope includes `CommunicationsManager`, provider-neutral interfaces, provider accounts, communication channels, conversations, participants, messages/events, contact matching, owner takeover, AI pause/resume, usage events, LeadHub timeline adapter, and webhook idempotency.

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

Sprint 8.7 Milestone 4 - Shared Business Profile Service Layer
