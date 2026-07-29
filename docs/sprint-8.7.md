# Sprint 8.7 — Shared Business Profile and Communications Foundation

## Status

Planned

## Product

24/7 Sales Partner

## Depends On

Sprint 8.6 foundations and Sprint 8.6 Checkpoint 1.

## Primary Outcome

Establish the shared business configuration, provider-neutral communications architecture, and unified conversation data model required for future voice, SMS, website chat, owner takeover, and communication-channel workflows.

This sprint is documentation and planning for the foundation. It must not require live Twilio or Retell provisioning.

---

# Sprint Objective

Sprint 8.7 begins the transition of 24/7 Sales Partner from a website-centered workflow into a complete digital front-office platform for small service businesses.

The sprint objective is to define and prepare the shared foundation that future Twilio, Retell AI, SMS, chat, calling, and appointment workflows will use.

At the end of Sprint 8.7:

* Each business should have one planned Shared Business Profile.
* Website, voice, text, and chat channels should be designed to use the same business information.
* Communication providers should be isolated behind internal interfaces.
* LeadHub should have a provider-neutral conversation model.
* Future phone calls, text messages, website chats, forms, and supported email activity should be able to appear in one unified customer timeline.
* No live Twilio or Retell production workflow should be required.

This sprint prioritizes architecture, data integrity, tenant isolation, and extensibility over live communications functionality.

---

# Product Context

24/7 Sales Partner is being repositioned as:

> A done-for-you lead generation and digital front-office platform for small service businesses.

The complete product will eventually combine:

* Done-for-you lead-generation website
* Custom domain
* Professional email
* Local business phone number
* 24/7 AI receptionist
* Two-way business texting
* AI-powered website chat
* Owner-initiated outbound calling
* LeadHub CRM
* Unified lead and conversation inbox

The website generates opportunities, but the product value is making sure those opportunities receive a response and remain organized until the business owner closes or dismisses them.

Sprint 8.7 creates the shared foundation for that workflow.

---

# Existing Repository Context

The following existing foundations were confirmed during repository inspection:

* Business records exist in `businesses`, `business_users`, `categories`, `sub_services`, and `business_sub_services`.
* 247SP onboarding and website records exist in `247sp_onboarding`, `247sp_website_configurations`, `247sp_business_content`, `247sp_service_pages`, `247sp_domain_selections`, `247sp_email_requests`, `247sp_generated_websites`, and related website override tables.
* Service-area settings already exist on `247sp_website_configurations`, including service-area-business flags and travel radius fields.
* Domain workflow records exist in `domain_requests`, `domain_assignments`, `website_domains`, `domain_dns_records`, and `domain_events`.
* Email provisioning foundation records exist in `mailbox_requests`, `mailbox_assignments`, `mailbox_activity_log`, and `business_mailbox_counts`.
* LeadHub currently uses `contacts`, `contact_statuses`, `notes`, `tasks`, and `activity_logs`.
* `LeadHub::capture247spWebsiteSubmission()` currently handles website lead capture.
* Domain provider abstraction exists through `DomainManager`, `RegistrarInterface`, and `NamecheapRegistrar`.
* No current `CommunicationsManager`, `VoiceProviderInterface`, `MessagingProviderInterface`, `ChatProviderInterface`, `TelephonyProviderInterface`, Retell provider class, Twilio provider class, conversations table, or communications-provider schema was confirmed.

Sprint 8.7 must not duplicate existing business, service, service-area, website, domain, email, LeadHub, note, task, or activity-log concepts without a documented compatibility reason.

---

# Architectural Principles

## Business-Scoped Configuration

All Business Profile and communications records must belong to a specific `business_id`.

Configuration must not be stored only at the account or user level because one account may eventually manage multiple businesses.

Every query and mutation must enforce business-level tenant isolation.

## One Source Of Truth

Business information must not be independently maintained inside the website editor, voice agent, text agent, and chat agent.

The Shared Business Profile should become the authoritative source for:

* Business identity
* Services
* Service areas
* Hours
* Frequently asked questions
* Pricing guidance
* Appointment rules
* Transfer instructions
* Emergency rules
* Escalation instructions
* Tone and personality
* Notification preferences

Existing website and onboarding data should be reused or migrated where practical instead of duplicated.

## Provider-Neutral Application Design

LeadHub and 24/7 Sales Partner must not depend directly on Twilio- or Retell-specific data structures.

External providers should be accessed through internal services and provider interfaces.

Intended design:

```text
24/7 Sales Partner / LeadHub
  ↓
CommunicationsManager
  ↓
Provider interfaces
  ↓
Twilio, Retell, or future providers
```

## LeadHub As System Of Record

Provider platforms may retain recordings, messages, transcripts, and delivery data, but LeadHub must remain the primary business-facing record of:

* Who contacted the business
* Which channel they used
* What they requested
* What response they received
* Whether the owner took over
* What follow-up is still required
* Whether the opportunity is won, lost, scheduled, pending, spam, or dismissed

## Append-Only Communication History

Provider events and customer communications should be preserved as historical events.

Corrections, status changes, summaries, and follow-up records should not silently overwrite original activity.

## Idempotent Provider Event Processing

Future webhooks may be delivered more than once.

The communications data model must support provider event identifiers and idempotency controls so duplicate webhook delivery does not create duplicate calls, messages, conversations, leads, or timeline entries.

## Usage Must Be Measurable

The architecture should make future usage billing possible from the beginning.

Future usage categories include:

* AI receptionist minutes
* Owner-handled call minutes
* SMS and MMS segments
* AI chat responses
* Phone numbers
* Recording storage
* Transcription usage

Sprint 8.7 does not calculate overage invoices, but the schema must not prevent reliable usage tracking later.

---

# Sprint Scope

Sprint 8.7 includes five major workstreams:

* Shared Business Profile
* Communications architecture
* Unified conversation schema
* LeadHub integration foundation
* Readiness, documentation, and validation

This sprint may define schema, service boundaries, internal contracts, UI planning, migration requirements, testing requirements, and acceptance criteria.

This sprint must not implement live Twilio or Retell workflows.

---

# Workstream 1 — Shared Business Profile

## Purpose

Each business needs one central profile that can eventually configure:

* Website content
* AI voice receptionist
* AI text assistant
* Website chat assistant
* LeadHub routing
* Owner notifications
* Transfer behavior
* Escalation behavior

The Business Profile is business-scoped and provider-neutral.

## Profile Categories

### Business Identity

The Shared Business Profile should support:

* Business name
* Public display name
* Primary phone number
* Primary email address
* Website URL
* Business address
* Time zone
* Default language

### Customer-Facing Descriptions

The profile should support:

* Customer-facing description
* Short business description
* Long business description
* Primary greeting
* Value proposition
* Preferred tone
* Preferred personality
* Words or claims the AI should avoid

### Services

The profile should support:

* Services offered
* Service descriptions
* Active or inactive status
* Emergency availability
* Appointment eligibility
* Optional pricing guidance

Existing `categories`, `sub_services`, `business_sub_services`, and `247sp_service_pages` must be reviewed before adding any new service table.

A second conflicting service catalog must not be created without a documented reason.

### Service Areas

The profile should support:

* Whether customers visit the business
* Whether the business travels to customers
* Travel radius
* Cities, ZIP codes, counties, or regions served
* Excluded service areas
* Remote or virtual service availability

Existing `247sp_website_configurations` service-area fields and `businesses.is_public_physical_location` must be reused or migrated where practical.

### Business Hours

The profile should support:

* Standard hours by day
* Closed days
* 24-hour availability
* After-hours behavior
* Holiday exceptions
* Emergency availability

Hours must be interpreted in the business's configured time zone.

### Frequently Asked Questions

Each FAQ should support:

* Question
* Approved answer
* Sort order
* Active status
* Channel availability when needed

### Pricing Guidance

The profile may store customer-approved guidance such as:

* Whether prices may be discussed
* Starting prices
* Service-call fees
* Estimate policies
* Deposit policies
* Financing statements
* Prohibited pricing claims

AI agents must not invent prices that are not contained in approved business information.

### Appointment Rules

The profile should support:

* Whether appointments may be requested
* Whether appointments may be automatically booked
* Services eligible for appointments
* Required customer information
* Minimum notice
* Appointment duration guidance
* Confirmation instructions
* Cancellation instructions

Sprint 8.7 does not require a full scheduling engine.

### Transfer Rules

The profile should support:

* Primary owner transfer number
* Backup transfer number
* Business-hours behavior
* After-hours behavior
* Maximum transfer attempts
* Voicemail or fallback behavior
* Services or conditions that require transfer

### Emergency And Escalation Rules

The profile should support:

* Emergency keywords or conditions
* Services treated as urgent
* Situations the AI must not handle
* Immediate transfer conditions
* Owner alert conditions
* Emergency disclaimer language

The platform must not represent itself as an emergency service unless the business has explicitly configured that behavior.

### Notification Preferences

The profile should support:

* Email notifications
* SMS notifications
* In-app notifications
* New-lead notifications
* Missed-call notifications
* Transfer-failure notifications
* Urgent-lead notifications
* Daily unresolved-lead summary preference

## Business Profile Data Design

The schema plan should use:

* One primary business profile record
* Existing business, service, and service-area records where appropriate
* Child records for repeating structured information
* JSON only where the information is provider-neutral but not yet stable enough to normalize

Potential logical entities include:

* `business_profiles`
* `business_profile_hours`
* `business_profile_hour_exceptions`
* `business_profile_faqs`
* `business_profile_pricing_guidance`
* `business_transfer_rules`
* `business_escalation_rules`
* `business_notification_preferences`

These names are planning guidance, not permission to duplicate existing equivalent tables.

Before creating migrations, existing tables and columns must be reviewed.

## Profile State

The profile should support lifecycle states such as:

* Draft
* In review
* Ready
* Active
* Incomplete

A business must be able to save an incomplete profile without activating communication agents.

## Profile Completeness

The application should be able to calculate whether required profile sections are complete.

Minimum future agent readiness should include:

* Business identity
* Services
* Service areas
* Hours
* Greeting
* Transfer behavior
* Emergency rules
* Notification destination

Profile completeness should be available to the 247SP launch-readiness system.

---

# Workstream 2 — Communications Architecture

## CommunicationsManager

`CommunicationsManager` is a planned application-level service responsible for coordinating:

* Communication channels
* Provider accounts
* Conversations
* Inbound events
* Outbound actions
* Contact matching
* Lead creation
* Owner takeover
* Usage events
* Provider status synchronization

LeadHub pages should call internal application services rather than communicating directly with Twilio or Retell.

`CommunicationsManager` does not currently exist in the repository and should be treated as planned.

## Provider Interfaces

The architecture should define provider-neutral contracts for:

* `VoiceProviderInterface`
* `MessagingProviderInterface`
* `ChatProviderInterface`
* `TelephonyProviderInterface`

Possible future implementations include:

* `RetellVoiceProvider`
* `RetellChatProvider`
* `TwilioTelephonyProvider`
* `TwilioMessagingProvider`

These implementations were not confirmed in the repository and should be treated as planned.

Sprint 8.7 may establish interfaces, namespaces, documentation, and non-networking scaffolding. It should not require live provider provisioning.

## Provider Responsibilities

### Voice Provider

Responsible for:

* AI agent configuration
* Voice-agent sessions
* Transcripts
* Summaries
* Call disposition
* Extracted lead information

### Telephony Provider

Responsible for:

* Phone numbers
* Call routing
* Call transfers
* Caller ID
* Call status
* Recordings
* Browser or callback calling when future scopes require it

### Messaging Provider

Responsible for:

* SMS and MMS delivery
* Message status
* Incoming message webhooks
* Segment counts
* Media attachments

### Chat Provider

Responsible for:

* Website chat sessions
* Chat responses
* Visitor identity
* Agent handoff
* Conversation events

A single provider may implement more than one interface.

## Provider Accounts And Channels

The data model should distinguish between provider accounts and communication channels.

Provider account means the business's relationship with an external provider, such as:

* Twilio subaccount
* Retell workspace or agent ownership
* Future provider account

Provider-account properties may include:

* Business
* Provider
* External account identifier
* Status
* Provisioning state
* Last synchronization date
* Error state
* Non-secret metadata

Provider API secrets must not be stored in plain text.

Communication channel means a customer-facing method of communication, such as:

* Business phone number
* SMS-enabled number
* Website chat widget
* Email inbox
* Website form
* Future social messaging channel

A channel may support multiple capabilities. For example, one phone number may support inbound voice, outbound voice, SMS, and MMS.

---

# Workstream 3 — Unified Conversation Model

## Conversation Design

A conversation represents an interaction thread between a business and one or more external participants.

A conversation should be able to represent:

* Phone calls
* Text-message threads
* Website chat sessions
* Website form follow-up
* Email inquiries
* Future EMD leads

A conversation may be associated with:

* One business
* Zero or one LeadHub contact initially
* Zero or one LeadHub lead or opportunity initially
* One primary communication channel
* One or more participants
* Many messages or events

Central relationship:

```text
One contact
  ↓
Many conversations
  ↓
Many channels
  ↓
One unified timeline
```

## Contact And Lead Behavior

A new communication event must not automatically create a duplicate contact.

Contact matching may use:

* Normalized phone number
* Normalized email address
* Existing conversation participant
* Provider-supplied customer identifier
* Manual owner selection

Ambiguous matches should be reviewable rather than silently merged.

A conversation may initially exist without a matched LeadHub contact.

## Conversation Status

Potential conversation states include:

* Open
* Waiting for business
* Waiting for customer
* AI active
* Owner active
* Resolved
* Closed
* Spam

Conversation status is separate from lead status.

## Lead Status

LeadHub should support or prepare for these opportunity statuses:

* New
* Contacted
* Appointment requested
* Appointment scheduled
* Estimate sent
* Won
* Lost
* Pending
* Spam

Existing statuses must be reviewed before changing production values.

A migration plan must preserve existing LeadHub records.

## Conversation Participants

Participants may include:

* Customer
* Business owner
* Team member
* AI agent
* External provider
* System automation

Participants should be represented without requiring every external person to have a UBO user account.

## Conversation Events

The unified timeline should support events such as:

* Form submitted
* Call started
* Call answered
* Call missed
* Call transferred
* Transfer failed
* Recording available
* Transcript available
* AI summary created
* SMS received
* SMS sent
* MMS received
* Website chat started
* Website chat message received
* Owner took over
* AI paused
* AI resumed
* Appointment requested
* Lead status changed
* Internal note added
* Task created

The original provider payload should not be required for normal application display.

A sanitized provider payload or metadata record may be retained for troubleshooting.

## Direction And Authorship

Messages and communication records should identify:

* Inbound or outbound direction
* Channel
* Sender type
* Recipient type
* AI-generated status
* Owner takeover status
* Delivery status
* Provider identifier
* Provider timestamp
* Application timestamp

## Idempotency

Provider-originated records should support a unique combination such as:

```text
provider
provider_account_id
external_event_id
```

Repeated webhook delivery must update or ignore the existing record rather than create duplicates.

---

# Workstream 4 — LeadHub Integration Foundation

## LeadHub Role

LeadHub must remain the business-facing center of the lead-to-sale workflow.

Future LeadHub screens should answer:

```text
What leads came in, what happened to them, and who still needs a response?
```

Sprint 8.7 should prepare LeadHub for:

* Unified conversation counts
* Unread activity
* Unresolved leads
* Response-needed state
* Channel identification
* AI versus owner activity
* Conversation timeline display

## Unified Timeline

A LeadHub contact timeline should eventually display:

* Website forms
* Phone calls
* Call transfers
* Recordings
* Transcripts
* AI summaries
* SMS and MMS
* Website chat
* Owner calls
* Owner messages
* Internal notes
* Tasks
* Appointment activity
* Status changes

Sprint 8.7 should define the shared event format and integration approach. It does not need to implement every event type.

## Human Takeover

The architecture must treat owner takeover as a first-class capability.

Future conversations should support:

* AI active
* Owner takeover requested
* Owner active
* AI paused
* AI resumed
* Conversation closed

The owner must eventually be able to pause AI responses for a specific contact or conversation without disabling AI for the entire business.

## Internal Notes

Internal notes must never be sent to:

* Customers
* AI providers as customer-visible content
* Public website chat
* SMS recipients

Existing LeadHub note privacy behavior must remain intact.

---

# Workstream 5 — Internal API Planning

## Planned Internal Endpoints

The following are provider-neutral planning contracts. Sprint 8.7 does not need to expose all endpoints publicly.

Potential endpoints:

```text
/api/voice/agent-config
/api/voice/create-lead
/api/voice/check-availability
/api/voice/book-appointment
/api/voice/transfer-rules
/api/voice/post-call

/api/messages/inbound
/api/messages/send

/api/chat/create-lead
/api/chat/message
/api/chat/session-complete

/api/conversations/takeover
/api/conversations/pause-ai
/api/conversations/resume-ai
```

Each contract should document:

* Authentication expectations
* Business identification
* Idempotency key
* Request fields
* Response fields
* Error behavior
* Audit behavior
* LeadHub effects

Provider webhooks must not be trusted solely because they contain a business identifier.

---

# Security Requirements

Sprint 8.7 design must account for:

* Business-level authorization
* Webhook signature verification
* Provider credential protection
* PII handling
* Recording and transcript access control
* Internal-note privacy
* Audit history
* Request replay protection
* Rate limiting
* Retention controls

API tokens, auth tokens, and provider secrets must not be committed to the repository or stored in plain-text application tables.

Environment configuration should continue to use the project's established environment-secret approach.

---

# Audit Requirements

The system should preserve an audit trail for actions such as:

* Business Profile changes
* Agent activation or deactivation
* Transfer-rule changes
* Emergency-rule changes
* Notification changes
* Owner takeover
* AI pause or resume
* Manual contact merge
* Conversation reassignment
* Lead-status changes

Audit records should identify:

* Business
* Acting user or system
* Action
* Target record
* Timestamp
* Relevant before-and-after metadata where appropriate

---

# UI Planning

## 24/7 Sales Partner Business Profile

Recommended sections:

* Business Information
* Services
* Service Area
* Hours
* Frequently Asked Questions
* Pricing Guidance
* Appointment Rules
* Call Transfers
* Emergency and Escalation Rules
* AI Tone and Personality
* Notifications
* Profile Readiness

The interface should use progressive disclosure so a small business owner is not presented with one overwhelming form.

## LeadHub

Prepare navigation and interface planning for:

* Leads
* Contacts
* Conversations
* Inbox
* Tasks
* Unresolved opportunities

Final navigation should avoid showing empty or nonfunctional production features until they are ready.

---

# Launch-Readiness Changes

The 247SP launch-readiness model should be expanded to prepare for:

* Business Profile complete
* Website content complete
* Website approved
* Domain ready
* Professional email ready
* Business phone number ready
* AI receptionist configured
* Transfer rules tested
* Text messaging registered
* Website chat active
* LeadHub connected
* Billing active
* End-to-end communications test passed

During Sprint 8.7, future items may appear as planned, unavailable, or not yet required.

They must not falsely display as complete.

---

# Out Of Scope

The following are explicitly outside Sprint 8.7:

* Live Twilio provisioning
* Live Retell provisioning
* Purchasing Twilio phone numbers
* Creating live Twilio subaccounts
* Creating live Retell agents
* Receiving production phone calls
* Sending production SMS or MMS
* A2P 10DLC registration
* Browser-based calling
* Mobile callback calling
* Native mobile applications
* Live website chat deployment
* Full scheduling integration
* Google Calendar booking
* Overage invoice generation
* Stripe usage-based billing
* Automated phone-number portability
* Automated domain or email provisioning changes
* Full production unified inbox
* Advanced AI quality scoring

These belong in later implementation sprints.

---

# Existing Data Compatibility

Before adding tables or fields, implementation work must inspect:

* Existing business records
* Existing business service records
* Existing service-area fields
* Existing website content
* Existing LeadHub contacts
* Existing LeadHub leads
* Existing notes
* Existing tasks
* Existing timeline events or activity logs
* Existing billing and subscription records

The sprint must avoid creating parallel sources of truth.

Where existing data already represents a Business Profile field, the preferred order is:

1. Reuse the existing record.
2. Extend the existing record.
3. Add a documented compatibility layer.
4. Migrate to a new structure only when necessary.

Existing customer and staging data must remain readable.

Known existing data sources to review:

* `businesses`
* `categories`
* `sub_services`
* `business_sub_services`
* `business_custom_services`
* `247sp_onboarding`
* `247sp_website_configurations`
* `247sp_business_content`
* `247sp_service_pages`
* `247sp_website_content_overrides`
* `contacts`
* `notes`
* `tasks`
* `activity_logs`
* `domain_requests`
* `mailbox_requests`
* `subscriptions`

---

# Migration Requirements

Any schema implementation resulting from this sprint must:

* Use the next available migration number.
* Work on a clean database.
* Work on the current staging schema.
* Preserve existing LeadHub and 247SP data.
* Avoid oversized `utf8mb4` indexes.
* Use indexed foreign keys where needed.
* Use explicit business ownership.
* Include idempotency constraints where provider events are stored.
* Avoid provider-specific columns in core conversation tables.
* Document manual repair steps when a migration cannot be fully reversible.

Do not rerun or rewrite historical repair migrations.

Before implementation, confirm the latest migration number because migration `020_repair_domain_dns_records.sql` already exists.

---

# Recommended Implementation Sequence

## Milestone 1 — Existing-Schema Review

* Inspect current business, service, service-area, website, and LeadHub tables.
* Document which records can be reused.
* Identify conflicts and duplicate concepts.
* Confirm migration numbering.

## Milestone 2 — Shared Business Profile Schema

* Add only the missing profile structures.
* Backfill from existing business and onboarding data when safe.
* Add business-level ownership and indexes.
* Add profile lifecycle and completeness support.

## Milestone 3 — Business Profile Service Layer

* Add a central profile-management service.
* Enforce business authorization.
* Normalize profile data for website and future agent consumers.
* Add audit hooks.

## Milestone 4 — Business Profile Interface

* Add customer-facing profile sections.
* Add administrative visibility.
* Support draft saving.
* Display readiness and missing required fields.

## Milestone 5 — Communications Core Schema

* Add provider-neutral channels.
* Add conversations.
* Add participants.
* Add messages or timeline events.
* Add idempotency support.
* Add optional LeadHub contact and lead associations.

## Milestone 6 — Communications Interfaces

* Add provider interface contracts.
* Add `CommunicationsManager` scaffolding.
* Do not make live provider API calls.
* Add internal DTOs or normalized payload expectations where useful.

## Milestone 7 — LeadHub Timeline Adapter

* Define how normalized communication events appear in LeadHub.
* Preserve existing notes and tasks.
* Prepare for unresolved-conversation reporting.
* Do not expose placeholder actions that do not work.

## Milestone 8 — Documentation And Staging Validation

* Update relevant architecture and database documentation.
* Run migration validation.
* Run PHP linting on staging.
* Confirm existing website and LeadHub workflows still operate.
* Confirm tenant isolation.

---

# Testing Requirements

## Database Testing

* Migration succeeds on the current staging schema.
* Migration succeeds on a clean schema using the documented migration sequence.
* Foreign keys reference valid tables and compatible column types.
* Duplicate provider events are rejected or safely reconciled.
* Business-scoped indexes support normal queries.
* Existing LeadHub records remain intact.

## Business Profile Testing

* A profile can be created for a business.
* A draft profile can be saved while incomplete.
* Required sections are identified correctly.
* One business cannot access another business's profile.
* Existing service and service-area data remain available.
* Time-zone-aware hours are retained correctly.

## Conversation Foundation Testing

* A conversation can exist without a matched contact.
* A conversation can be linked to a LeadHub contact.
* One contact can have multiple conversations.
* One conversation can contain multiple events.
* Inbound and outbound direction are distinguishable.
* AI and human authorship are distinguishable.
* Duplicate external event identifiers do not create duplicate activity.
* Internal notes remain private.

## Regression Testing

* Existing website editing still works.
* Existing website forms still create LeadHub activity.
* Existing contact and lead pages still load.
* Notes still work.
* Tasks still work.
* Billing pages still load.
* Domain pages still load.
* Current onboarding data is not lost.

---

# Acceptance Criteria

Sprint 8.7 is complete when:

* The Shared Business Profile architecture is implemented and documented.
* Existing business, service, and service-area records have been reused where practical.
* Businesses can save and review their profile information.
* Profile completeness can be calculated.
* Provider-neutral communications interfaces exist.
* Core conversation records are business-scoped.
* Conversations may be associated with LeadHub contacts and leads.
* One contact may have multiple conversations.
* Communication events support channel, direction, sender type, provider identity, and idempotency.
* Existing LeadHub notes, tasks, forms, and timelines continue to work.
* No live Twilio or Retell dependency is required.
* Tenant isolation has been validated.
* Documentation reflects the revised digital-front-office direction.
* PHP files pass syntax validation on an environment with PHP available.
* SQL migrations are validated against staging when implementation migrations exist.
* `git diff --check` passes.
* The work is reviewed and merged through the normal pull-request process.

---

# Definition Of Done

Sprint 8.7 is not complete merely because new tables exist.

It is complete when the application has a stable, documented source of truth for business information and a provider-neutral communications foundation that can support this future flow:

```text
Prospect contacts business
  ↓
Provider sends normalized event
  ↓
24/7SP identifies the business
  ↓
LeadHub matches or creates the contact
  ↓
Conversation is created or updated
  ↓
Activity appears in the unified timeline
  ↓
AI or owner responds
  ↓
Follow-up remains visible until resolved
```

The implementation should make Sprint 8.8 telephony work easier, not force the application to be redesigned when Twilio and Retell are connected.

---

# Expected Sprint 8.8 Scope

Sprint 8.8 is expected to be:

```text
Sprint 8.8 — Telephony Foundation
```

Expected scope:

* Twilio master-account configuration
* One Twilio subaccount per customer
* Local phone-number provisioning
* Phone-number status and lifecycle
* Retell voice-agent provisioning
* Inbound-call routing
* Owner transfer rules
* Call recordings
* Call transcripts
* AI summaries
* Call disposition
* LeadHub call timeline
* Initial AI receptionist pilot
