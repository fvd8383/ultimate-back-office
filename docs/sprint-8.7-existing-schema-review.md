# Sprint 8.7 Existing Schema Review

# 1. Executive Summary

The repository already has strong Sprint 8.7 building blocks: authenticated users, business ownership through `business_users`, core business identity on `businesses`, selected service categories through `categories`, `sub_services`, `business_sub_services`, custom service names through `business_custom_services`, 247SP onboarding/content tables, website generation/override tables, domain workflow tables, email provisioning tables, LeadHub contacts/notes/tasks/activity logs, local billing/subscription tables, and Stripe webhook idempotency through `stripe_webhook_events`.

Before migration `021_shared_business_profile.sql`, the implementation did not have an implemented Shared Business Profile table, `CommunicationsManager`, provider interfaces for Retell/Twilio, provider account/channel tables, conversations, conversation participants, conversation messages, calls, SMS messages, chat messages, phone numbers, AI agents/sessions, transfer rules, escalation rules, profile hours, FAQs, pricing guidance, appointment rules, or notification preferences.

What must be extended is mostly schema and documentation, not current production behavior. Sprint 8.7 Milestone 2 should add a provider-neutral Shared Business Profile layer that reuses current `businesses`, service, service-area, website, domain, and email data instead of duplicating it. It should not create a second service catalog. It should also avoid communications provider columns in core business/profile tables.

The largest migration and compatibility risks are staging history around migrations `017`, `019`, and `020`, the repaired `domain_dns_records.record_hash` unique key, existing 247SP service-area radius values, the split between core selected services and website service pages, existing LeadHub status names, and website content stored as both onboarding data and presentation overrides.

There are conflicting sources of truth in planning terms, but not yet conflicting production communications models. The real conflicts to resolve are:

- business/service facts exist in both account onboarding (`businesses`, `business_sub_services`, `business_custom_services`) and 247SP onboarding/website pages (`247sp_website_configurations`, `247sp_service_pages`);
- public/business descriptions exist in `247sp_business_content` and website overrides, but not in a shared profile;
- website presentation is stored separately from shared business facts, which is correct, but some fields currently mix shared content and website copy.

# 2. Existing Business Data Model

| Item | Current repository facts | Related PHP/UI | Sprint 8.7 action |
| --- | --- | --- | --- |
| Accounts | No separate `accounts` table exists. The account identity is the `users` login record. `users` columns: `id`, `first_name`, `last_name`, `email` unique, `phone`, `status`, `created_at`, `updated_at`. | `Auth`, `Session`; UI under `public/accounts/` including `login.php`, `verify.php`, `dashboard.php`, `profile.php`. | Leave unchanged for Milestone 2. Do not introduce an account table for Shared Business Profile. |
| Users | `users.id` is the primary key. OTP data is in `user_otps` with FK `user_id`; login history in `user_logins`. Internal roles use `user_roles`. | `Auth`, `Otp`, `AdminPortal::currentUserIsAdmin()`. | Leave unchanged. |
| Businesses | `businesses` columns currently include `id`, `business_name`, `slug`, `legal_name`, `owner_user_id`, `phone`, `email`, address fields, `is_public_physical_location`, `legal_structure_id`, `legal_structure_other`, `business_started_on`, `primary_category_id`, `status`, `is_suspended`, `is_test_account`, `internal_status`, `setup_status`, `setup_step`, timestamps. FKs: `owner_user_id -> users.id`, `legal_structure_id -> legal_structures.id`, `primary_category_id -> categories.id`. | `BusinessFoundation::saveBusinessInfo()`, `BusinessFoundation::businessForUser()`, `TwentyFourSevenSalesPartner::saveBusinessInformation()`, `AdminPortal::business()`. UI: `public/accounts/business-create.php`, `public/accounts/business.php`, `public/app/admin/business.php`, 247SP onboarding. | Reuse as the authoritative legal/core identity and address root. Extend only if a missing core scalar belongs on every business. |
| Business membership or authorization | `business_users` columns: `business_id`, `user_id`, `role_id`, `status`, `is_owner`. Unique key `business_id, user_id`; FKs to `businesses`, `users`, `roles`. | `BusinessFoundation::businessForUser()` is the main customer authorization gate. LeadHub `_common.php`, 247SP pages, accounts checkout/business flows use it before loading business-scoped records. | Reuse. Every Shared Business Profile customer route must gate through this pattern. |
| Business onboarding | Core onboarding state is on `businesses.setup_status` and `businesses.setup_step`. 247SP onboarding state is in `247sp_onboarding`: `business_id`, `contact_name`, `setup_status`, `current_step`, `completed_at`, timestamps, unique `business_id`. | `BusinessFoundation::saveBusinessInfo()`, `BusinessFoundation::saveServices()`, `BusinessFoundation::completeOnboarding()`, `TwentyFourSevenSalesPartner::*`. UI: `public/accounts/business-create.php`, `public/app/247sp/onboarding.php`, `review.php`. | Reuse for existing onboarding. Shared Business Profile should have its own lifecycle/completeness fields because 247SP setup status is product-specific. |
| Business identity | Core identity currently lives on `businesses.business_name`, `legal_name`, `phone`, `email`, address fields, `business_started_on`, and `primary_category_id`. 247SP additionally stores `247sp_onboarding.contact_name`. | Accounts Business pages and 247SP onboarding update `businesses`; admin reads joined data in `AdminPortal::business()`. | Reuse `businesses` for core identity. Add profile display/greeting/tone fields to proposed `business_profiles`, not to website overrides. |
| Business contact information | `businesses.phone`, `businesses.email`, address fields. Email provisioning stores requested/assigned mailboxes in `mailbox_requests` and `mailbox_assignments`. Domain workflow stores domains separately. | `BusinessFoundation`, `EmailProvisioningFoundation`, `DomainManager`, `WebsiteManager`, `SiteGenerator`. UI: account business profile, account Email, account Domains, 247SP preview. | Reuse and link. Proposed profile can have `primary_email_address`, `primary_domain_assignment_id`, and future `primary_phone_number_id`, but provider-owned phone data should wait for communications schema. |
| Time zone | No implemented table or column was found for business time zone. Docs mention it as planned only. | No current UI. | Extend in Milestone 2 with nullable `business_profiles.timezone`, default/backfill policy required. |
| Business address | `businesses.address_line_1`, `address_line_2`, `city`, `state`, `postal_code`, `country`, `is_public_physical_location`. 247SP service-area copy duplicates address/city/state/postal fields in `247sp_website_configurations`. | `BusinessFoundation::saveBusinessInfo()`, `TwentyFourSevenSalesPartner::saveServiceArea()`, `AdminPortal::business()`. | Reuse `businesses` as core address. Treat `247sp_website_configurations` service-area fields as website/onboarding compatibility inputs to backfill Shared Business Profile service area. |

# 3. Existing Services and Service Areas

Existing service structures:

- `categories`: `id`, `name`, `is_active`, timestamps. One primary category is stored on `businesses.primary_category_id`.
- `sub_services`: `id`, `category_id`, `name`, `is_active`, timestamps; unique `category_id, name`.
- `business_sub_services`: `business_id`, `sub_service_id`, unique `business_id, sub_service_id`.
- `business_custom_services`: `business_id`, `category_id`, `service_name`, unique `business_id, category_id, service_name`.
- `247sp_service_pages`: website service pages with `business_id`, `onboarding_id`, `service_number`, `parent_service_page_id`, `sort_order`, `status`, `slug`, `service_name`, `short_description`. Unique keys include `business_id, service_number` and `business_id, slug`.
- `247sp_website_service_images`: website images by `business_id` and `service_number`.

Service descriptions exist in `247sp_service_pages.short_description` and website overrides (`247sp_website_content_overrides` with `page_key` like `service_{number}` and fields such as title/description). Core selected services (`sub_services`, `business_sub_services`, `business_custom_services`) do not store per-business descriptions, active/inactive flags, emergency availability, appointment eligibility, or pricing guidance.

Active/inactive state exists at catalog level (`categories.is_active`, `sub_services.is_active`) and website page level (`247sp_service_pages.status`). There is no implemented business-level active flag for selected core services besides the presence/absence of a row.

Service-area structures:

- `businesses.is_public_physical_location`: true means customers can see/visit a public physical location.
- `247sp_website_configurations.service_area_business`: true means the business travels to customers.
- `247sp_website_configurations.service_area_address`, `service_area_city`, `service_area_state`, `service_area_postal_code`: 247SP website/onboarding service-area fields.
- `247sp_website_configurations.service_area_radius_miles`, `service_area_radius_is_custom`: added by migration `018`.

The website, onboarding, and LeadHub do not currently use the same service records:

- Account/business onboarding uses `categories`, `sub_services`, `business_sub_services`, and `business_custom_services`.
- 247SP onboarding and website generation use `247sp_service_pages` for customer-facing service pages and copy.
- LeadHub website lead capture stores a submitted service name in activity metadata and contact `source_detail`; it does not reference a service table.

Recommended future source of truth:

- Use the selected core service model as the base service source of truth: `businesses.primary_category_id`, `business_sub_services`, and `business_custom_services`.
- Add a proposed business-owned service detail layer only if needed for descriptions/rules, for example `business_profile_services`, linked where possible to `sub_services` or storing a custom service label. This should not replace `247sp_service_pages`; it should feed website pages and AI/communications rules.
- Keep `247sp_service_pages` as website presentation/navigation content because it supports slugs, hierarchy, sort order, page-specific copy, and active website page status.

# 4. Existing Website and Business Content

Current storage:

| Concept | Current storage |
| --- | --- |
| Business name | `businesses.business_name`; website rendering also uses generated page JSON and overrides where present. |
| Public description | `247sp_business_content.business_description`; generated website page `content_json`; possible presentation override in `247sp_website_content_overrides`. |
| Website headline | `247sp_website_content_overrides` page `home`, field `headline`, or generated page JSON from `SiteGenerator`. |
| Website copy | `247sp_business_content`, `247sp_service_pages.short_description`, generated page `content_json`, and `247sp_website_content_overrides`. |
| Greeting | No shared greeting field found. Some website CTA/copy fields may function as greeting-like presentation text. |
| Calls to action | `247sp_website_content_overrides` fields including `call_to_action`, `primary_cta_label`, `primary_cta_type`, `secondary_cta_label`, `secondary_cta_type`; active CTA types are `call_now`, `contact_form`, `view_pricing`. |
| Hours | No implemented hours table/column found. |
| FAQs | No implemented FAQ table/column found. |
| Pricing language | `247sp_website_content_overrides` can store website pricing CTA/list data; uploads are under `public/app/uploads/pricing-lists/`. No shared pricing guidance table exists. |
| Contact details | `businesses.phone`, `businesses.email`, address fields; domain/email tables for provisioned workflow. |
| Service area copy | `247sp_website_configurations` service-area fields and generated website content. |
| SEO fields | Website integration fields exist. Basic SEO/page titles/meta are primarily generated page JSON or override-driven; no separate canonical/robots/sitemap schema found. |
| Website integrations | `website_integrations` with `ga_measurement_id`, Search Console property, GTM, Clarity, Meta Pixel, Google Business Profile URL. |

Field ownership recommendation:

- Core business record: legal/core name, owner linkage, main phone/email, physical address, public physical location flag, legal structure, business started date, primary category, status flags.
- Shared Business Profile: public display name if different, primary customer-facing greeting, short/long approved business descriptions, tone/personality, time zone, hours, service-area policy, FAQ, pricing guidance, appointment rules, transfer rules, escalation rules, notification preferences, readiness/completeness state.
- Website-specific presentation data: hero headline, page headings, CTA labels/behaviors, service page slugs/order/hierarchy, page-specific SEO titles/meta, branding colors/assets, pricing-list asset path, and web analytics/integration display settings.

# 5. Existing LeadHub Model

Current structures:

- `contacts`: `business_id`, optional `portal_user_id`, `first_name`, `last_name`, `company_name`, `email`, `phone`, `contact_type`, `status_id`, `source_module_key`, `source_detail`, `created_by_user_id`, timestamps. FKs to `businesses`, `contact_statuses`, `users`.
- `contact_statuses`: global or business-specific statuses with `business_id` nullable, `name`, `status_key`, `sort_order`, `is_default`, `is_active`.
- `notes`: `business_id`, optional `contact_id`, optional `created_by_user_id`, `note_body`, timestamps.
- `tasks`: `business_id`, optional `contact_id`, optional `assigned_to_user_id`, optional `created_by_user_id`, `title`, `description`, `due_date`, `status`, `priority`, timestamps.
- `activity_logs`: optional `business_id`, optional `enterprise_account_id`, optional `user_id`, optional `contact_id`, `module_key`, `activity_type`, `subject`, `description`, `metadata_json`, `created_at`.

Explicit answers:

- Does the current system distinguish contacts from leads? Yes, but only with `contacts.contact_type` values normalized by `LeadHub::normalizeContactType()` to `lead` or `contact`. There is no separate leads/opportunities table.
- Can one contact have multiple leads? No separate lead record exists, so one contact cannot have multiple lead/opportunity records today. Multiple submissions update the same contact and add activity/notes/tasks.
- Can one lead contain multiple activity events? A contact/lead can have many `activity_logs`, notes, and tasks via `business_id` + `contact_id`. There is no lead-specific event table.
- How are website forms represented? `LeadHub::capture247spWebsiteSubmission()` validates `business_id`, `website_id`, `page_id`, matches the generated website/page to the business, creates or updates a `contacts` row, optionally inserts a `notes` row, inserts a follow-up `tasks` row, and inserts an `activity_logs` row with `activity_type = '247sp_website_lead_submitted'` and metadata containing website/page/source/service/IP/user-agent.
- How are duplicate phone numbers or email addresses handled? `LeadHub::matchingContact()` searches exact stored `email` or `phone` within the same `business_id`, orders by latest updated/id, and returns one match. There are no unique constraints on contact email/phone and no normalized phone/email columns.
- Are internal notes separated from customer-visible data? LeadHub `notes` are internal application records and are not sent externally by current code. `admin_notes` are separate internal admin notes. There is no customer-visible note flag; future communications must not expose `notes.note_body` or `admin_notes.note` to customers/providers as approved customer-facing content.
- What statuses currently exist? Seeded global statuses are `New Lead` (`new_lead`), `Contacted`, `Qualified`, `Estimate Sent`, `Customer`, `Inactive`, `Lost`, and `Spam`. Code also looks for `status_key IN ('new', 'new_lead')` for new lead defaulting.
- What must be preserved during Sprint 8.7? Existing contact IDs, `contact_type`, status IDs/status keys, notes, tasks, activity types, `metadata_json`, website lead activity rows, and the fact that LeadHub pages load by `business_id` from `lead_hub_bootstrap()`.

# 6. Existing Event and Timeline Architecture

Current generic event/history/audit tables:

| Table | Purpose | Can support Sprint 8.7 communications? |
| --- | --- | --- |
| `activity_logs` | Generic business/user/contact/module activity. Used by BusinessFoundation, 247SP, WebsiteManager, SiteGenerator, BillingFoundation, DomainManager, EmailProvisioningFoundation, and LeadHub. | Good for summary timeline and audit display, but insufficient as the primary conversation/message model. It lacks conversation/thread IDs, channel IDs, participants, direction, provider event IDs, delivery status, idempotency constraints, and source-specific records. |
| `domain_events` | Domain workflow history with business/request/assignment/user/registrar, event type/status/message, request/response JSON. | Good provider-workflow precedent. Not reusable for communications because it is domain-specific. |
| `mailbox_activity_log` | Email provisioning request/assignment history. | Useful pattern for provisioning history, not a unified customer conversation model. |
| `stripe_webhook_events` | Webhook idempotency/status/payload/error storage. | Strong idempotent webhook pattern to reuse conceptually. |
| `admin_notes` | Internal admin notes. | Must remain separate from customer-visible activity and provider prompts. |

Support assessment:

- Calls: `activity_logs` can show a summary, but cannot store call lifecycle, recordings, transcripts, or provider call IDs safely.
- Texts/chat/messages: `activity_logs` lacks direction, participant, delivery, and threading.
- Forms: current website form activity works as summary timeline; future form submissions should also create a conversation/event.
- Owner actions/AI actions/status changes: `activity_logs` can continue to record summary/audit activity.
- Notes/tasks: existing tables should remain separate and can be linked into a future timeline adapter.

Recommendation:

- Add a new provider-neutral conversation/event model for communications foundation.
- Keep `activity_logs` as a compatibility/audit/summary adapter so existing LeadHub timelines and dashboards continue to work.
- Use an adapter between new `conversation_messages`/events and existing LeadHub contact activity. The tradeoff is some duplicate summary rows in `activity_logs`, but it preserves current UI while creating a proper normalized communications model.

# 7. Existing Integration and Webhook Patterns

Stripe:

- Endpoints: `public/accounts/stripe-webhook.php` and `public/webhooks/stripe.php` both require `private/stripe-webhook-endpoint.php`.
- `private/stripe-webhook-endpoint.php` accepts POST only, reads `php://input`, reads `HTTP_STRIPE_SIGNATURE`, calls `StripeBilling::handleWebhook()`, returns JSON, and avoids exposing exception detail unless `APP_DEBUG` is true.
- `StripeBilling::verifyWebhookEvent()` validates `STRIPE_WEBHOOK_SECRET`, parses the Stripe signature header, enforces a 300-second timestamp tolerance, uses `hash_hmac('sha256')`, `hash_equals()`, and decodes JSON.
- `BillingFoundation::stripeWebhookEventExists()`, `recordStripeWebhookEvent()`, and `markStripeWebhookEvent()` use `stripe_webhook_events.event_id` unique key for idempotency/status/error tracking.

Domain provider abstractions:

- `private/classes/domains/RegistrarInterface.php` defines provider-neutral methods for availability, registration, transfer, get domain/status, DNS read/write, ownership verification, auto-renew, renewal.
- `private/classes/registrars/NamecheapRegistrar.php` contains Namecheap XML API-specific calls and configuration checks.
- `private/classes/domains/DomainManager.php` owns workflow orchestration, registrar selection, DNS plan generation, status refresh, errors, domain events, and website-domain synchronization.

Website integrations:

- `website_integrations` stores per-business website analytics/search/social references.
- `WebsiteManager::integrationsForBusiness()`, `WebsiteManager::upsertIntegrations()`, and `WebsiteManager::normalizeGaMeasurementId()` own validation/storage.
- Only Google Analytics is rendered today; other fields are stored only.

External identifiers and duplicate prevention:

- Stripe subscriptions/payments store Stripe IDs directly because this is billing integration-specific.
- Stripe webhook events prevent duplicate webhook processing through unique `event_id`.
- Domain requests are unique on `business_id, requested_domain`; DNS records use generated `record_hash` after migration 020 to avoid oversized `utf8mb4` unique indexes.

Communications conventions to reuse:

- `CommunicationsManager` should own orchestration like `DomainManager`, not page routes.
- Interfaces should mirror `RegistrarInterface`: `VoiceProviderInterface`, `MessagingProviderInterface`, `ChatProviderInterface`, `TelephonyProviderInterface`.
- Provider account identifiers should live in proposed provider/account/channel tables, not `businesses` or `business_profiles`.
- Provider event IDs should use a unique provider-neutral key such as `provider_key`, `provider_account_id`, `provider_event_id` with a generated hash or bounded composite index to avoid `utf8mb4` length issues.
- Webhook verification should follow Stripe's pattern: signature secret from config, timestamp/replay tolerance when provider supports it, no debug detail unless enabled.
- Error/retry state should follow Stripe/domain patterns: `status`, `last_error` or `error_message`, `processed_at`, `last_checked_at`, `retry_count` if retries are implemented.

# 8. Tenant Isolation Review

Current customer enforcement:

- `BusinessFoundation::businessForUser($businessId, $userId)` joins `businesses` to `business_users`, requires active link, and is the main customer authorization gate.
- `BusinessFoundation::firstBusinessForUser()` restricts active business/user link and active business status.
- `public/app/lead-hub/_common.php` resolves `business_id` through `BusinessFoundation::businessForUser()` or first active business before LeadHub pages call `LeadHub` methods.
- 247SP customer pages (`public/app/247sp/onboarding.php`, `review.php`, `dashboard.php`, `site-preview.php`, `website-manager.php`) resolve business through `TwentyFourSevenSalesPartner::businessForUser()` and check `TwentyFourSevenSalesPartner::businessHasAccess()`.
- Account pages use business/user joins through `BusinessFoundation`, `BillingFoundation`, `DomainManager`, and `EmailProvisioningFoundation`.

Admin access:

- `public/app/admin/_common.php` requires login and sets `is_admin` from `AdminPortal::currentUserIsAdmin()`.
- Admin pages then use admin-level methods that can load by business ID or record ID across tenants. That is appropriate only after checking `is_admin`.

Concrete files/methods requiring careful Sprint 8.7 enforcement:

- Any new customer Business Profile route under `public/app/247sp/` or `public/accounts/` must use `BusinessFoundation::businessForUser()` or the 247SP wrapper before calling profile methods.
- Any new LeadHub conversation route under `public/app/lead-hub/` must follow `public/app/lead-hub/_common.php` and query by `business_id`.
- Future public provider webhooks must not trust a submitted `business_id`; they must verify provider signature and resolve business through owned provider account/channel records.
- `DomainManager::domainEvents($requestId)` and `DomainManager::dnsRecordsForRequest($requestId)` load by request ID for admin display; do not copy this pattern into customer routes without joining/verifying business ownership.
- `AdminPortal::website($websiteId)` loads by website ID for admin. Future customer website/conversation loads by ID must include `business_id`.
- `LeadHub::contactDetail()`, `taskForBusiness()`, notes/tasks/activity methods already include `business_id`; future conversation equivalents should do the same.
- `LeadHub::capture247spWebsiteSubmission()` validates `website_id` and `business_id` against `247sp_generated_websites`; future public form/chat/call endpoints should use equivalent ownership validation.

# 9. Proposed Source-of-Truth Map

| Concept | Current source | Proposed authoritative source | Reuse or migration approach | Consumer systems |
| --- | --- | --- | --- | --- |
| Business identity | `businesses` | `businesses` plus `business_profiles` display fields | Reuse, add profile display/greeting where needed | Website, Voice agent, SMS assistant, Chat assistant, LeadHub, Admin, Billing |
| Public business description | `247sp_business_content.business_description`, generated page JSON, overrides | `business_profiles` for approved shared description; website overrides for channel copy | Backfill profile from 247SP content when empty | Website, Voice agent, SMS assistant, Chat assistant, Admin |
| Services | `business_sub_services`, `business_custom_services`, `247sp_service_pages` | Core selected services plus proposed profile service details | Reuse selected services; keep website pages presentation-specific | Website, Voice, SMS, Chat, LeadHub, Future EMD |
| Service areas | `businesses.is_public_physical_location`, `247sp_website_configurations` | Proposed profile/service-area records, seeded from current fields | Backfill from 247SP configuration and business address | Website, Voice, SMS, Chat, LeadHub |
| Hours | None | Proposed `business_profile_hours` and exceptions | New table | Website, Voice, SMS, Chat |
| FAQs | None | Proposed `business_profile_faqs` | New table | Website, Voice, SMS, Chat |
| Pricing guidance | Website overrides/pricing-list upload only | Proposed `business_profile_pricing_guidance` | New table; link pricing-list as website asset only | Website, Voice, SMS, Chat |
| Appointment rules | None | Proposed `business_profile_appointment_rules` or profile JSON initially | New provider-neutral fields | Voice, SMS, Chat, LeadHub, Future scheduling |
| Transfer rules | None | Proposed `business_transfer_rules` | New table | Voice, SMS, Chat, LeadHub |
| Escalation rules | None | Proposed `business_escalation_rules` | New table | Voice, SMS, Chat, LeadHub, Admin |
| Tone and personality | None | `business_profiles` fields | New nullable fields | Voice, SMS, Chat |
| Notification preferences | None | Proposed `business_notification_preferences` | New table | LeadHub, Admin, future notification service |
| Website content | 247SP content/pages/overrides | Website-specific tables remain authoritative for presentation | Leave website tables; consume shared facts where appropriate later | Website, Admin |
| Lead identity | `contacts` with `contact_type = lead` | `contacts` initially; future opportunities only if product requires | Preserve `contacts`; do not add leads table in Milestone 2 | LeadHub, Website, Voice, SMS, Chat, Future EMD |
| Contact identity | `contacts` | `contacts` | Reuse; add normalized contact matching later | LeadHub, all channels |
| Conversation history | `activity_logs`, notes/tasks for website forms | Proposed conversations/messages/events plus `activity_logs` adapter | Add new model later; do not overload activity log | LeadHub, Voice, SMS, Chat, Admin |
| Internal notes | `notes`, `admin_notes` | Same | Preserve separation; never send to providers/customers | LeadHub, Admin |
| Tasks | `tasks` | Same | Reuse and link from future communication events | LeadHub, Voice/SMS/Chat escalations |
| Usage events | None for communications; billing has payments/subscriptions | Proposed `usage_events` later | Communications foundation or deferred to provider integration | Billing, Admin, Voice, SMS, Chat |

# 10. Proposed Schema Changes

Do not write SQL for this task. Minimum proposed schema changes:

## A. Required for Shared Business Profile

Proposed `business_profiles`

- Purpose: one provider-neutral profile per business for shared front-office facts and readiness.
- Why existing structures cannot fully support it: `businesses` stores legal/core facts, while 247SP tables are product/website-specific and do not have time zone, hours, greeting, tone, lifecycle, transfer/escalation readiness, or AI-safe guidance.
- Key columns: `id`, `business_id`, `display_name`, `short_description`, `long_description`, `primary_greeting`, `preferred_tone`, `preferred_personality`, `avoid_claims_text`, `timezone`, `default_language`, `profile_status`, `completeness_status`, `completed_sections_json`, `source_247sp_business_content_id`, `created_at`, `updated_at`.
- Business ownership: `business_id NOT NULL`, unique.
- Relationships: FK to `businesses.id`; optional source FK to `247sp_business_content.id` if used.
- Indexes: unique `business_id`; index `profile_status`; index `completeness_status`.
- Unique constraints: one profile per business.
- Idempotency requirements: backfill should be idempotent by `business_id`.
- Migration/backfill: create one row for every existing business; seed display name from `businesses.business_name`; descriptions from `247sp_business_content` where present; timezone nullable or default explicitly documented.
- Milestone: 2.

Proposed `business_profile_service_areas`

- Purpose: normalize shared service-area rules without deleting current 247SP configuration.
- Why existing structures cannot fully support it: 247SP service-area fields are website/onboarding-specific and only support address/city/state/postal/radius.
- Key columns: `id`, `business_id`, `business_profile_id`, `customers_visit_business`, `business_travels_to_customers`, `travel_radius_miles`, `travel_radius_is_custom`, `primary_city`, `primary_state`, `primary_postal_code`, `regions_json`, `excluded_regions_json`, `remote_service_available`, timestamps.
- Business ownership: `business_id` and `business_profile_id`.
- Relationships: FKs to `businesses`, `business_profiles`.
- Indexes: unique `business_profile_id`; index `business_id`; index `primary_city, primary_state` if bounded.
- Backfill: from `businesses.is_public_physical_location` and `247sp_website_configurations.service_area_*`.
- Milestone: 2.

Proposed `business_profile_services`

- Purpose: business-owned service facts used by AI/communications and later website generation.
- Why existing structures cannot fully support it: selected services have no per-business descriptions/rules; website service pages have presentation slugs/hierarchy and are not ideal as AI-safe source of truth.
- Key columns: `id`, `business_id`, `business_profile_id`, nullable `sub_service_id`, nullable `custom_service_id`, `service_name`, `description`, `status`, `emergency_available`, `appointment_eligible`, `pricing_guidance_id` nullable, `sort_order`, timestamps.
- Business ownership: `business_id`.
- Relationships: FKs to `business_profiles`, `sub_services`, possibly `business_custom_services`.
- Indexes: `business_id, status, sort_order`; `business_profile_id`; `sub_service_id`; generated/bounded unique for profile service identity if needed.
- Backfill: from `business_sub_services`, `business_custom_services`, and optionally descriptions from matching `247sp_service_pages`.
- Milestone: 2 if service descriptions/rules are required immediately; otherwise defer detailed child table and store only profile-level completeness.

Proposed `business_profile_hours`

- Purpose: standard weekly hours.
- Why existing structures cannot fully support it: no hours exist.
- Key columns: `business_id`, `business_profile_id`, `day_of_week`, `opens_at`, `closes_at`, `is_closed`, `is_24_hours`, timestamps.
- Indexes: unique `business_profile_id, day_of_week`; index `business_id`.
- Backfill: none; default incomplete.
- Milestone: 2.

Proposed `business_profile_hour_exceptions`

- Purpose: holiday/date exceptions.
- Why existing structures cannot fully support it: no exceptions exist.
- Key columns: `business_id`, `business_profile_id`, `exception_date`, `label`, `opens_at`, `closes_at`, `is_closed`, timestamps.
- Indexes: `business_profile_id, exception_date`.
- Milestone: later in profile UI if not needed for first schema.

Proposed `business_profile_faqs`

- Purpose: approved FAQ answers.
- Why existing structures cannot fully support it: no FAQ storage exists.
- Key columns: `business_id`, `business_profile_id`, `question`, `answer`, `status`, `sort_order`, `channel_availability_json`, timestamps.
- Indexes: `business_id, status, sort_order`; `business_profile_id`.
- Milestone: 2 if AI readiness requires FAQs; otherwise Milestone 4.

Proposed `business_profile_pricing_guidance`

- Purpose: AI-safe pricing and estimate guidance.
- Why existing structures cannot fully support it: pricing-list upload and website CTA do not define approved spoken/text answers.
- Key columns: `business_id`, `business_profile_id`, nullable `business_profile_service_id`, `guidance_type`, `guidance_text`, `may_discuss_prices`, `status`, timestamps.
- Indexes: `business_id, status`; `business_profile_service_id`.
- Milestone: 2 or 4 depending readiness threshold.

Proposed `business_transfer_rules`, `business_escalation_rules`, `business_notification_preferences`

- Purpose: provider-neutral rules and notifications.
- Why existing structures cannot fully support them: no transfer/escalation/preference model exists.
- Key columns: business/profile IDs, rule/preference type, condition JSON or explicit fields, destination user/phone/email, status, priority, timestamps.
- Indexes: `business_id, status`, `business_profile_id`, rule/preference type.
- Idempotency: not provider webhook-driven; use stable rows by business/profile/type/priority as appropriate.
- Milestone: transfer/escalation may belong in Milestone 2 if launch readiness depends on them; notification preferences can be deferred until notification service work.

## B. Required for communications foundation

These should not be part of Milestone 2 unless the scope is expanded beyond Shared Business Profile schema:

- Proposed `communication_provider_accounts`: business/provider account identity, non-secret provider account IDs, status, provisioning/error state.
- Proposed `communication_channels`: business-owned channels such as website form, website chat, phone number, SMS-enabled number, email inbox. Should reference provider account when applicable.
- Proposed `conversations`: business-scoped thread with optional `contact_id`, status, channel/source, assignment, last activity.
- Proposed `conversation_participants`: contact/user/AI/system/external participants without requiring every external person to be a UBO user.
- Proposed `conversation_messages` or `communication_events`: normalized timeline rows with channel, direction, sender type, message/event type, body/summary, provider identifiers, provider timestamp, metadata.
- Important indexes: always include `business_id`; `business_id, contact_id, last_activity_at`; `conversation_id, created_at`; provider event generated hash or bounded unique.
- Idempotency: unique provider event identity such as generated SHA-256 hash over provider key/account/event ID.

## C. Deferred until live Twilio/Retell integration

- `phone_numbers`
- `calls`
- `call_recordings`
- `call_transcripts`
- `call_summaries`
- `sms_messages`
- `chat_messages`
- `ai_agents`
- `ai_sessions`
- `ai_usage`
- provider-specific Retell/Twilio adapters
- live webhook endpoints that call provider SDKs/APIs

# 11. Migration Risk Review

- Existing staging data: staging may already have partial domain-service migration state. Use additive, existence-aware migration patterns where appropriate.
- Existing customer/test businesses: backfill must create at most one profile row per `businesses.id` and must not require complete 247SP onboarding.
- Existing service-area values: `247sp_website_configurations.service_area_radius_miles` can be null for non-service-area businesses; migration 018 backfilled 25 miles only where `service_area_business = 1`.
- Existing LeadHub statuses: preserve seeded statuses and business-specific statuses. Do not rename `new_lead` or assume `new` exists.
- Existing website content: do not delete or move `247sp_business_content`, generated page JSON, or overrides in Milestone 2. Backfill shared descriptions by copying, not moving.
- Nullability: profile fields should allow drafts and incomplete profiles. Avoid NOT NULL requirements for unknown time zone, hours, FAQs, and guidance unless safe defaults are explicit.
- Foreign keys: all new profile children should include `business_id` and FK to `business_profiles`/`businesses`.
- `utf8mb4` index length: avoid long unique keys across multiple `VARCHAR(255/500)` columns. Use generated hashes like migration 020 when unique identity includes long provider IDs or payload-derived fields.
- Generated hashes: if generated columns are used, document MySQL compatibility and include validation queries for generated column/index existence.
- Duplicate historical records: `contacts` may already contain duplicate email/phone values within a business. Do not add unique contact email/phone constraints in Sprint 8.7.
- Migration ordering: next migration must come after `020_repair_domain_dns_records.sql`.
- Historical repair migrations 019 and 020: historical migrations must not be rewritten or rerun merely to accommodate Sprint 8.7.

# 12. Recommended Milestone 2 Plan

Sprint 8.7 Milestone 2 should be one focused migration:

- Next migration number: `021`.
- Migration filename: `021_shared_business_profile.sql`.
- Reused tables: `businesses`, `categories`, `sub_services`, `business_sub_services`, `business_custom_services`, `247sp_website_configurations`, `247sp_business_content`, `247sp_service_pages`, `activity_logs`.
- Extended existing tables: none unless a small nullable `businesses` field is proven core. Prefer new profile tables.
- New tables needed: `business_profiles`, `business_profile_service_areas`, `business_profile_hours`, `business_profile_hour_exceptions`, `business_profile_faqs`, `business_profile_pricing_guidance`, `business_appointment_rules`, `business_transfer_rules`, `business_escalation_rules`, and `business_notification_preferences`.
- Fields to backfill: profile display name from `businesses.business_name`; short/long description from `247sp_business_content.business_description`/`about_company`; service-area flags/radius/city/state/postal from `businesses` and `247sp_website_configurations`. Do not backfill a second service catalog from website service pages.
- Expected FKs: every new table should reference `businesses.id`; child tables should reference `business_profiles.id`; optional links to `sub_services.id`, `business_custom_services.id`, and `247sp_business_content.id` should be nullable.
- Expected indexes: unique `business_profiles.business_id`; child indexes on `business_id`; child indexes on `business_profile_id`; status/sort indexes where list views need them.
- Expected rollback or repair approach: additive migration with no destructive moves. If backfill misses fields, add a later repair migration; do not edit 021 after staging applies it.
- Validation queries: confirm new tables exist; confirm one `business_profiles` row per existing `businesses` row; confirm no duplicate `business_profiles.business_id`; sample profile rows joined to 247SP content/config; confirm FKs and indexes in `information_schema.statistics` and `information_schema.table_constraints`.
- Regression checks: account business create/edit still loads; 247SP onboarding/review/preview/website-manager still loads; LeadHub leads/contacts/notes/tasks still load; website lead submit still creates contact/note/task/activity; admin business/website editor still loads; billing/domain/email pages still load.

# 13. Open Architecture Questions

| Issue | Why it matters | Recommended default | Alternatives | Consequence of postponing |
| --- | --- | --- | --- | --- |
| Should Milestone 2 include `business_profile_services` or defer it? | Services are the highest-risk source-of-truth conflict because account services and website service pages differ. | Defer and rely on existing selected services plus website pages temporarily. | Add a profile service detail table that links to existing selected services and copies website descriptions only as seed data. | AI/voice/chat work may need another migration before services can be safely consumed. |
| Should time zone default to a value or remain incomplete? | Hours and after-hours behavior need time zone. | Store nullable `timezone`; mark profile incomplete until selected. | Default to `America/New_York` or infer later from address. | Provider behavior could be wrong if defaulted silently. |
| Are FAQs and pricing guidance required in the first schema migration? | They are profile facts but may not be needed before profile UI. | Include nullable/draft child tables now for AI readiness. | Use JSON columns on `business_profiles` temporarily. | Later migration required; fewer backfill risks now. |
| Should transfer/escalation rules be Milestone 2 tables or communications-core tables? | They are profile rules but depend on future phone/users/employees/channels. | Add provider-neutral business/profile rule tables now without provider/channel references. | Defer until communications schema exists. | Launch readiness cannot mark transfer/escalation configured from structured records. |
| Should contact matching add normalized email/phone columns now? | Future webhooks need reliable matching, but current contacts have loose duplicates. | Defer uniqueness; add normalized helper columns only when a dedupe plan exists. | Add `normalized_email`/`normalized_phone` without unique constraints now. | Matching remains exact-string and may create/choose imperfect contacts. |

# 14. Recommended Next Codex Task

Sprint 8.7 Milestone 2 - Shared Business Profile Schema

Task outline:

1. Continue on the focused Sprint 8.7 branch if it remains scoped to schema review and Milestone 2.
2. Do not edit historical migrations `001` through `020`.
3. Create `database/migrations/021_shared_business_profile.sql`.
4. Add `business_profiles` with one row per business, nullable draft fields, unique `business_id`, FK to `businesses.id`, status/completeness fields, timestamps, and idempotent backfill from `businesses` and `247sp_business_content`.
5. Add `business_profile_service_areas` with one row per profile/business, FK to `business_profiles` and `businesses`, backfilled from `businesses.is_public_physical_location` and `247sp_website_configurations.service_area_*`.
6. Add `business_profile_hours` with business/profile/day-of-week structure and no forced complete defaults.
7. Include profile FAQs, pricing guidance, appointment rules, transfer rules, escalation rules, and notification preferences as provider-neutral child tables.
8. Update `docs/database-plan.md` and `README.md` migration list with factual migration 021 documentation and validation queries.
9. Run `git diff --check` and `git diff --cached --check`.
10. Do not run migrations locally, do not call Twilio/Retell, do not change production/staging config, and do not modify PHP behavior unless a documentation reference requires a factual correction.
