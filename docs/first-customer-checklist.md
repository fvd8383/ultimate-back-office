# First Customer Checklist

## Purpose

This checklist tracks what must be complete before accepting the first paying 24/7 Sales Partner customer.

New module development is paused until 247SP is launch-ready. EMD, SSP, TUHWD, and other future modules should not move forward until 247SP can take payment, publish a customer website, capture and respond to leads, support domain/email/phone/text/chat setup, store every communication in LeadHub, and operate safely in production.

---

# Roadmap Priority

1. Sprint 8.7: Shared Business Profile and Website Platform Alignment — COMPLETE
2. 247SP First-Customer Pricing Implementation and staging gate — COMPLETE / PASS
3. Sprint 8.8: Website Platform and Component CMS
4. Sprint 8.9: Communications Core Foundation
5. Sprint 8.10: Telephony and AI Receptionist
6. Later Sprint: Messaging and Website Chat
7. Production Readiness / First Customer Pilot
8. First Paying Customer
9. Resume separately approved future modules

---

# Platform Foundation

* [x] User Authentication
* [x] Business Creation
* [x] Module Activation
* [x] Persistent Application Shell
* [x] Account Navigation
* [x] Workspace Navigation
* [x] Admin Navigation
* [x] Module Navigation
* [x] Accounts/App Return Path
* [x] LeadHub Foundation
* [x] 247SP Onboarding
* [x] Website Generation
* [x] Website Preview
* [x] Website Branding
* [x] Website Customization
* [x] 247SP Website Manager
* [x] Admin Website Editor
* [x] CTA Configuration
* [x] Homepage Stat Configuration
* [x] Service Hierarchy
* [x] Parent/Child Service Pages
* [x] Services Dropdown Navigation
* [x] Pricing List Upload
* [x] Admin Portal
* [x] Billing Foundation
* [x] Sprint 8.5 UX/Application Shell/Admin QA
* [x] Sprint 8.7 Milestone 1 Existing Schema and Architecture Review
* [x] Sprint 8.7 Milestone 2 Shared Business Profile Schema and staging validation
* [x] Sprint 8.7 Milestone 3 Product, Architecture, Pricing, and Roadmap Alignment
* [x] Sprint 8.7 Milestone 4 Shared Business Profile Service Layer and staging validation
* [x] Sprint 8.7 Milestone 5 Shared Business Profile Interface and staging validation (PASS)
* [x] Sprint 8.7 Milestone 6 Website Platform Architecture and Migration Audit (merged at `fa9228eefbbba94523781599e74ca04e0dbadb22`)
* [x] Sprint 8.7 Milestone 7 closeout
* [ ] First-customer Admin QA
* [ ] First-customer Customer QA

---

# Billing & Payments

* [x] Pricing P2 Stripe payment integration: dedicated TEST staging gate PASS at `f4f767d7cf907a085d77f705e734288a3af04f16`
* [x] Pricing P1 foundation: migration `022_247sp_pricing_cohorts.sql` staging validated PASS at `e71f7bed62e54cc5851e2bb365c136e6b5f6321d`
* [x] Pricing P1 foundation: atomic never-reused customer sequence/cohort service with idempotency and rollback
* [x] Pricing P1 foundation: Alpha/Beta/Founding/Standard durable cohort configuration
* [x] Pricing P1 foundation: locked subscription setup/monthly/nullable Stripe-reference snapshot
* [x] STAGING VALIDATED: Pricing P1 integrated atomically at completed 247SP business signup
* [x] STAGING VALIDATED: Alpha exact stored six-calendar-month period and automatic $79 recurring transition
* [x] STAGING VALIDATED: Cohort-aware locked Stripe recurring/setup Prices and six-entry TEST catalog
* [x] STAGING VALIDATED: Setup fee collection exactly once for Founding and Standard
* [x] STAGING VALIDATED: Monthly subscription collection and MRR excluding setup fees
* [x] STAGING VALIDATED: Trial-to-paid workflow
* [x] STAGING VALIDATED: Billing failure/reconciliation workflow
* [x] STAGING VALIDATED: Subscription cancellation does not reopen a sequence position

---

# 247SP Lead Capture And Conversations

* [ ] Public Website Contact Form
* [ ] FIRST-CUSTOMER CRITICAL: Registered Host/domain to site and active routing resolution
* [ ] Permitted-domain validation, rate limiting, spam controls, replay/duplicate handling, and correlation IDs
* [ ] Lead Capture Creates LeadHub Contact
* [ ] Lead Capture Creates LeadHub Activity
* [ ] AI Website Chat Creates LeadHub Contact Or Activity
* [ ] Inbound Calls And AI Receptionist Summaries Create LeadHub Activity
* [ ] Business Texts Create LeadHub Activity
* [ ] Unified Conversation Inbox
* [ ] Lead Capture Notification or Admin Visibility
* [ ] Spam/Validation Controls
* [ ] Lead Capture QA From Published Site

---

# Domain & Publishing

* [ ] Domain Automation
* [ ] DNS Configuration
* [ ] FIRST-CUSTOMER CRITICAL: Generic site/component/revision/approval implementation
* [ ] FIRST-CUSTOMER CRITICAL: Versioned website build/deployment/restore pipeline
* [ ] Website Publishing through approved production deployment
* [ ] Domain Ownership Workflow
* [ ] Publish Workflow Management
* [ ] Publish Confirmation
* [ ] Domain/SSL state reconciled separately from successful deployment state

---

# Email, Phone, Texting, And AI Channel Automation

* [ ] FIRST-CUSTOMER CRITICAL: Vendasta professional-email provisioning and reconciliation
* [ ] Email Login Instructions
* [ ] Email Setup Confirmation
* [ ] Email Support Process
* [ ] Local Business Phone Number Provisioning
* [ ] Business Texting Setup
* [ ] AI Receptionist Setup
* [ ] AI Website Chat Setup
* [ ] Usage Allowance Tracking

---

# Customer Experience

* [x] Persistent left application navigation
* [x] Product-neutral business onboarding
* [x] Account dashboard business-card cleanup
* [x] Customer Website Manager for customer-safe 247SP edits
* [x] Pricing-list upload for private preview
* [ ] Customer Welcome Email
* [ ] Customer Setup Status Dashboard
* [ ] Customer Billing Page Finalized
* [ ] Website Publish Confirmation
* [ ] Email Setup Confirmation
* [ ] Phone/Text/Chat Setup Confirmation
* [ ] Unified Inbox Access
* [ ] First Customer SOP

---

# Admin Operations

* [x] User Management
* [x] Business Management
* [x] Website Management
* [x] Admin Website Editor
* [x] Admin Service Hierarchy Management
* [x] Admin 247SP CTA And Homepage Stat Editing
* [x] Admin Pricing List Upload
* [x] Billing Management
* [ ] Domain Management QA
* [ ] Email Management QA
* [ ] Customer Status Tracking QA
* [ ] Publish Workflow Management QA

---

# Security & Production

* [x] Authentication
* [x] Session Management
* [x] Business Permissions
* [x] Admin Permissions
* [ ] Production Environment
* [ ] Production Database
* [ ] Production Backups
* [ ] Production Monitoring
* [ ] Production Deployment Checklist

---

# Legal & Policies

* [ ] Terms of Service
* [ ] Privacy Policy
* [ ] Billing Policy
* [ ] Refund Policy

---

# Remaining Critical Path

- [ ] Stripe payment collection
- [ ] 247SP lead capture creating LeadHub records
- [ ] Unified conversation inbox for website forms, AI chat, calls, texts, AI receptionist activity, and supported email lead activity
- [ ] Domain publishing and automation
- [ ] Email, phone, texting, and AI channel provisioning and automation
- [ ] Production environment readiness
- [ ] Legal and policy documents
- [ ] First-customer admin QA and customer QA

---

# Operational Readiness

* [ ] First Customer SOP
* [ ] Customer Support Process
* [ ] Domain Transfer Process
* [ ] Email Support Process
* [ ] Website Update Process
* [ ] Billing Support Process

---

# First Paying Customer Definition

A first paying 247SP customer can:

1. Register an account and create a business
2. Successfully complete that business's 247SP signup
3. Receive one permanent sequence/cohort assignment and locked commercial terms
4. Provide a Stripe payment method through the approved cohort-aware flow
5. Receive the assigned cohort's setup and recurring treatment, including `$0` setup
   and six months free before automatic `$79/month` billing for Alpha
6. Complete 247SP onboarding
7. Publish a website on a customer domain
8. Submit a public website lead that creates LeadHub records
9. Receive business email provisioning and login instructions
10. Receive local business phone number, business texting, AI receptionist, and AI website chat setup
11. Access LeadHub
12. Manage conversations through the unified inbox
13. Manage billing

Without manual database changes.

---

# Deferred Until After First Paying Customer

Future module work remains paused until the first paying 247SP customer path is launch-ready and validated:

* EMD
* SSP
* TUHWD
* Other future modules

---

# Current Status

Progress should be measured against the complete digital-front-office workflow, not website-preview completion alone. A current percentage is not asserted while the Shared Business Profile, component CMS, communications core, and launch policies are being sequenced.

Sprint 8.7 is complete. Milestone 6 was merged at
`fa9228eefbbba94523781599e74ca04e0dbadb22`, and the Milestone 7 documentation-only
closeout is merged. Milestone 5 closed as COMPLETE / PASS
on final validated/deployed `main` state
`ea81194e7d853782f927fdf58ed65eecd6473a7f` after its fixes; the final successful
validation SHA-256 is
`687a1444664f9d7167dfb316510f09094e922c2b83166874849db44fb10382a6`.
The platform has persistent navigation, legacy website generation/editing, LeadHub
website capture, billing/domain/email foundations, and the Shared Business Profile.
Pricing P1 and Pricing P2 are COMPLETE / STAGING VALIDATED PASS. They provide durable
cohorts, never-reused sequence allocation, locked terms and Alpha dates,
completed-signup atomicity, locked billing reads, POST/CSRF Checkout, all four cohort
payloads, provider idempotency/recovery, webhook replay/order guards, and customer/admin
presentation. The dedicated pricing first-customer technical gate is CLEARED.
The generic CMS, publisher/restore lifecycle, registered-site ingestion, broader Sprint
8.8 website platform, DataForSEO, unified inbox, communications services, AI
receptionist, texting, chat, usage metering, and conversion workflows remain planned.

Major Remaining Milestones:

1. Sprint 8.8 staged website platform/component/revision/publishing/routing implementation beginning with planned migration 023 and full validation
2. Sprint 8.9 communications core, Vendasta professional email, Twilio foundation, and LeadHub timeline
3. Sprint 8.10 telephony and AI receptionist
4. Later messaging, website chat, unified inbox, usage, and overages required by the sold product
5. Production provider setup/end-to-end QA, legal/policy, support, first-customer admin/customer QA, and production deployment gate
