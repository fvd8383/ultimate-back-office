# First Customer Checklist

## Purpose

This checklist tracks what must be complete before accepting the first paying 24/7 Sales Partner customer.

New module development is paused until 247SP is launch-ready. EMD, SSP, TUHWD, and other future modules should not move forward until 247SP can take payment, publish a customer website, capture and respond to leads, support domain/email/phone/text/chat setup, store every communication in LeadHub, and operate safely in production.

---

# Roadmap Priority

1. Sprint 8.7: Shared Business Profile and Website Platform Alignment
2. Sprint 8.8: Website Generation and Component CMS Foundation
3. Sprint 8.9: Communications Core Foundation
4. Sprint 8.10: Telephony and AI Receptionist
5. Later Sprint: Messaging and Website Chat
6. Production Readiness / First Customer Pilot
7. First Paying Customer
8. Resume separately approved future modules

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
* [ ] Sprint 8.7 Milestone 5 Shared Business Profile Interface
* [ ] Sprint 8.7 Milestone 6 Website Generation, Site Lifecycle, and Component Audit
* [ ] First-customer Admin QA
* [ ] First-customer Customer QA

---

# Billing & Payments

* [ ] Stripe Payment Integration
* [ ] Setup Fee Collection
* [ ] Monthly Subscription Collection
* [ ] Trial-to-Paid Workflow
* [ ] Billing Failure Workflow
* [ ] Subscription Cancellation Workflow

---

# 247SP Lead Capture And Conversations

* [ ] Public Website Contact Form
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
* [ ] Website Publishing
* [ ] Domain Ownership Workflow
* [ ] Publish Workflow Management
* [ ] Publish Confirmation

---

# Email, Phone, Texting, And AI Channel Automation

* [ ] Business Email Provisioning
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

1. Sign up
2. Create a business
3. Activate 247SP
4. Pay the setup fee through Stripe
5. Start a monthly Stripe subscription
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

Sprint 8.7 Milestones 1 through 4 are complete; Milestones 2 and 4 are staging validated. Milestone 4 closed as PASS on deployed commit `d11bd0e7d14b9d9dd432f3ce244a9b2bbebfafb7`; cleanup and repository/database reconciliation passed. Milestone 5 is implemented on its review branch but is not merged, deployed, or staging validated. The platform has persistent navigation, website generation/editing foundations, LeadHub website capture, billing/domain/email foundations, the Shared Business Profile schema and service, and the pending-review profile interface. The component CMS, unified inbox, communications services, AI receptionist, business texting, website chat, usage metering, and site-conversion workflows remain planned.

Major Remaining Milestones:

1. Review, merge, deploy, and staging validate Sprint 8.7 Milestone 5; then complete the Milestone 6 audit and sprint closeout
2. Sprint 8.8 website generation/component CMS and public lifecycle
3. Sprint 8.9 communications core and LeadHub timeline
4. Sprint 8.10 telephony and AI receptionist
5. Later messaging, website chat, unified inbox, usage, and overages
6. Production deployment, legal/policy, support, and first-customer QA
