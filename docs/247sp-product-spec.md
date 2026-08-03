# 24/7 Sales Partner (247SP) Product Specification

## Approved Product Definition

24/7 Sales Partner is a done-for-you lead-generation and digital-front-office platform powered by one structured Business Profile. It generates a custom website, captures forms, calls, texts, and chats, provides immediate AI-assisted responses, and keeps every opportunity organized in LeadHub.

Product workflow:

```text
Present the business professionally
  -> Capture every opportunity
  -> Respond when the owner cannot
  -> Organize the opportunity in LeadHub
  -> Help the owner follow through
```

The website is a major product component and presentation layer. It is not the entire product or the primary source of business information.

---

# Product Status

## Existing And Completed Foundations

The repository currently includes:

* Business onboarding and selected services
* 247SP onboarding and content records
* Single-template website generation and private preview
* Customer Website Manager and internal Admin Website Editor
* Website branding, content overrides, service hierarchy, CTA configuration, pricing-list upload, and Google Analytics rendering
* Public website form capture into LeadHub through the existing lead-submit path
* LeadHub contacts, statuses, notes, tasks, and activity records
* Stripe billing foundation and Checkout/webhook integration
* Domain request, registrar abstraction, DNS planning, SSL status, and domain-event foundations
* Professional email request and assignment foundations
* Shared Business Profile schema from migration 021
* Authorized Shared Business Profile service layer with validation, transactions, lifecycle rules, readiness calculation, and audit summaries

Migration 021 is complete and staging validated. It created the initial provider-neutral Shared Business Profile root and child records for hours, FAQs, pricing guidance, appointment rules, transfer rules, escalation rules, and notification preferences.

Sprint 8.7 Milestone 4 is implemented in `private/classes/SharedBusinessProfile.php`
and staging validated as PASS on deployed commit
`d11bd0e7d14b9d9dd432f3ce244a9b2bbebfafb7`. The service is the reusable
business-rule boundary for profile consumers. The customer-facing profile interface
is implemented on branch `codex/sprint-8-7-milestone-5` with live readiness,
section draft saving, existing-fact reuse, and admin visibility. It is not merged,
deployed, or staging validated. See `docs/shared-business-profile-service-layer.md`.

## Planned Capabilities

The following approved product capabilities are not yet complete unless a later implementation document says otherwise:

* Multi-component website CMS and standardized revision/deployment workflow
* Public site lifecycle beyond the current generated-site foundation
* Local business phone provisioning
* AI receptionist
* Business texting and SMS Assistant
* AI website chat
* Unified conversation inbox
* Provider-neutral communications services and provider adapters
* Usage metering and overage billing
* EMD demo and bidirectional site-conversion workflows

---

# Included Product

Every 247SP customer cohort receives the same core product:

* Done-for-you website
* Domain
* Professional email
* Local business phone number
* AI receptionist
* Business texting
* AI website chat
* LeadHub CRM
* Unified conversation inbox
* Basic SEO setup
* Google Analytics tracking

Pricing cohorts are not feature tiers.

---

# Four Connected Responsibilities

## 1. Business Knowledge System

247SP collects and maintains approved business information once:

* Business identity and contact information
* Services and service areas
* Hours and FAQs
* Pricing guidance and appointment rules
* Transfer, emergency, and escalation rules
* Notification preferences
* Branding and trust information
* Marketing and SEO information
* Images and media
* Approved customer claims

The Shared Business Profile is the central knowledge layer. It is consumed by website generation and, when implemented, the AI receptionist, SMS Assistant, website chat, LeadHub routing, SEO helpers, schema markup, notifications, future email automation, future marketing tools, and future UBO modules.

Migration 021 is the initial schema, not the final home for every business-data category. New concepts must be evaluated against existing business, service, branding, website, media, and integration records before adding fields or child tables.

## 2. Website-Generation System

247SP creates custom-looking, conversion-focused websites from structured business data, approved reusable components and variants, shared platform modules, AI-assisted composition, and standardized revision/deployment workflows.

The current implementation has one starter template and private preview workflow. The component library, structured page CMS, site-revision system, and portable site lifecycle are planned.

See `docs/247sp-website-generation-architecture.md`.

## 3. Digital Front Office

The approved long-term product handles website forms, incoming calls, business texting, website chat, AI-assisted responses, owner transfers, owner takeover, notifications, and follow-up triggers.

Only website form capture and the existing LeadHub foundation are implemented today. Communications and unified-inbox capabilities remain planned.

## 4. Lead-Management System

LeadHub is the central system of record for contacts, leads and opportunities, website submissions, calls, text messages, website chat, notes, tasks, lead status, follow-up, conversation history, unresolved opportunities, owner activity, and AI activity.

One contact may have many conversations across many channels, presented in one unified timeline. Provider systems may retain operational records, but a communication must not remain isolated in a provider-only call log, text thread, chat transcript, or form store.

---

# Business Facts And Presentation

Authoritative business facts and channel-specific presentation remain separate.

| Concept | Authoritative layer |
| --- | --- |
| Business hours | Shared Business Profile |
| Services offered | Existing business service records |
| Travel radius | Existing 247SP configuration |
| FAQs | Shared Business Profile |
| Licenses and certifications | Proposed structured trust records |
| Homepage headline | Website presentation record |
| Hero and navigation variants | Proposed website page/presentation records |
| Lead routing | LeadHub/routing configuration |
| Transfer behavior | Shared Business Profile |
| Provider IDs | Proposed provider integration records |

Website, voice, SMS, and schema-markup wording may differ by channel, but every version must remain grounded in approved facts.

---

# Target Customer

247SP serves small local service businesses, including plumbers, HVAC contractors, electricians, roofers, landscapers, cleaning companies, handymen, mobile detailers, pest-control companies, and similar businesses.

The primary customer typically has 1-10 employees, limited CRM or digital-marketing expertise, and needs a professional presence, rapid response, and one place to manage opportunities.

---

# Approved Pricing

| Customer cohort | Customer numbers | Setup fee | Monthly price |
| --- | ---: | ---: | ---: |
| Beta Users | 1-5 | $0 | $79/month |
| Founding Users | 6-25 | $100 | $97/month |
| Standard Users | 26+ | $250 | $147/month |

Labels are `Beta User`, `Founding User`, and `Standard User`. These cohorts price the same core product; they do not grant different feature sets.

## Included Monthly Usage

Each active subscription includes:

* 200 AI minutes
* 500 outbound owner minutes
* 500 SMS segments
* 500 AI chat responses

Usage is measured per billing month and does not roll over.

## Overages

Overage categories are:

* AI receptionist minutes above 200
* Outbound owner minutes above 500
* SMS segments above 500
* AI chat responses above 500

The applicable unit rates must be defined in the approved pricing plan, order form, or billing policy before charging customers. Whether rates differ by cohort remains an open commercial-policy question. Do not advertise unlimited usage without an explicit written plan.

Additional mailboxes, aliases, or advanced email features may be billed separately only when an approved pricing policy defines them.

## Open Commercial-Policy Questions

The following are unresolved:

* Whether cohort pricing is permanently grandfathered or may increase
* Whether canceled Beta or Founding positions reopen
* Whether position is based on signup, payment, activation, or launch
* Whether failed, refunded, or fraudulent accounts count
* Whether returning customers retain their prior cohort
* Whether ownership changes preserve cohort
* Whether multiple businesses under one owner count separately
* Whether taxes are included
* Whether usage allowances or overage rates differ by cohort
* Whether setup fees are refundable or apply again after reactivation

Recommended default, not approved policy:

* Each independently activated business subscription counts as one customer position.
* Cohort is assigned when the subscription becomes active.
* The assigned cohort is stored and does not change merely because later customers cancel.

---

# Billing Direction

Billing must eventually distinguish product, pricing cohort, customer sequence or qualifying event, setup fee, monthly recurring price, included usage, overage rates, discounts, promotional periods, subscription status, original activation date, reactivation status, and Stripe price ID.

Proposed internal cohort identifiers are `beta`, `founding`, and `standard`. They are not implemented.

Cohort must not be permanently inferred from the current count of active customers. Once assigned, the subscription or billing record should preserve its cohort and applicable pricing reference. This document does not authorize a billing migration or change current Stripe configuration.

---

# Domain Policy

## FDV-Owned Domain

When 247SP purchases a domain, FDV owns it until an approved transfer.

Documented transfer fees remain:

* Months 0-12: $150
* Months 13-24: $250
* Months 25+: $350

The existing `DomainManager`, `RegistrarInterface`, and `NamecheapRegistrar` architecture owns registrar-neutral workflow, DNS planning, SSL status tracking, and domain events.

## Customer-Owned Domain

A customer who brings an existing domain retains ownership. The domain may be connected to the site but must not be retained, redirected, transferred, or reassigned without contractual authority and customer authorization.

Domain ownership is separate from site structure, site purpose, customer relationship, business association, and lead routing.

---

# Website And EMD Lifecycle

24/7SP and EMD Network are planned to share one website-generation infrastructure. A site may have a business, EMD lead-property, EMD demo, internal demo, or internal marketing purpose, subject to schema review.

An EMD/internal demo may be converted into a purchased 247SP site after facts, assets, customer association, domain, analytics, consent language, and routing are verified and approved. Lead routing then moves to the customer business.

An eligible canceled 247SP site may enter review for conversion to an EMD property. Conversion is not automatic and not every canceled site qualifies. Domain rights, content/media rights, customer data, claims, analytics, routing, and integrations require separate review.

Customer CRM records, leads, conversations, private communications, and other customer-specific data must remain isolated from the EMD property. Customer routing is removed before EMD routing is configured.

See `docs/247sp-website-generation-architecture.md` for the complete planned lifecycle and approval controls.

---

# Existing Website Behavior

The current generated website includes Home, a Services dropdown, About, and Contact. Existing active service pages and configured sub-service pages appear under Services.

Current CTA behaviors are `call_now`, `contact_form`, and `view_pricing`. Labels that imply scheduling, quoting, applications, or reservations still route to an implemented behavior and do not create a scheduling, quote, application, reservation, checkout, or ecommerce engine.

The current Admin Website Editor organizes controls around Branding, Pages, Services, Calls to Action, SEO, Integrations, and Advanced. Google Analytics is the only stored website integration currently rendered into generated output; other stored integration references do not imply implemented scripts or APIs.

The MVP CMS direction is structured and done-for-you. Customers manage shared business facts; FDV manages page composition, component variants, section order, typography, imagery, visual hierarchy, revisions, and publishing. It is not a customer drag-and-drop builder.

---

# Customer Dashboard Direction

The customer dashboard should report website, domain, professional email, business phone, AI receptionist, business texting, AI website chat, LeadHub, unified inbox, launch-readiness, billing-cohort, and usage status without presenting planned capabilities as active.

---

# Package Rules

* 247SP automatically includes LeadHub CRM access.
* Customers do not purchase LeadHub separately.
* One business has one Shared Business Profile and, for the current product model, one primary customer site.
* Every supported 247SP lead and communication must be stored in or connected to LeadHub.
* Professional email and a local business phone number are included in the approved product.
* Customers may use an FDV-purchased domain or connect a customer-owned domain.
* Basic SEO setup and Google Analytics tracking are included.
* Usage and overages follow the approved allowances and the active pricing/order/billing policy.
* Proposed communications, CMS, MCP, EMD conversion, and billing-cohort identifiers do not become implemented merely because they are documented.
