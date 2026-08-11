# Sprint 8.7 Milestone 6 Website Platform Architecture And Migration Audit

## Document status

**Milestone:** Sprint 8.7 Milestone 6 — Website Platform Architecture and Migration Audit

**Result represented by this document:** documentation-only architecture definition

**Runtime implementation:** not included

**Baseline reviewed:** `ea81194e7d853782f927fdf58ed65eecd6473a7f`

The words **current** and **implemented** below mean behavior verified in the repository at the baseline commit. The words **proposed**, **planned**, and **future** mean approved architecture that does not exist yet. Nothing in this document claims that the generic CMS, public publisher, DataForSEO provider, conversion workflow, or cohort-aware billing is implemented.

## 1. Purpose and scope

This audit defines an implementation-ready shared website platform for 247SP customer sites, EMD properties, and internal/demo sites. It covers site identity, revisions, approved components, customer and admin controls, domains, LeadHub routing, assets and rights, analytics, build/deployment history, conversion, legacy compatibility, and the related first-customer billing dependency.

Milestone 6 changes documentation only. It does not change PHP, JavaScript, CSS, public assets, configuration, SQL migrations, database state, provider state, staging, or production. Sprint 8.8 and the separately scheduled cohort-pricing stream own future implementation.

## 2. Current implementation baseline

At the reviewed baseline the repository implements:

- one business-bound 247SP onboarding and website configuration;
- the hard-coded `starter_local_service` generator;
- one `247sp_generated_websites` row per business and generated JSON page rows;
- authenticated private preview, a broad customer Website Manager, and an internal website editor;
- business-scoped branding, uploaded images/pricing lists, overrides, and integration references;
- customer change requests and launch approval recorded in `activity_logs`;
- business-bound domain requests, assignments, DNS records, events, and `website_domains` state;
- legacy website-form capture into LeadHub;
- one legacy 247SP billing plan plus Stripe Checkout/webhook foundations; and
- the Shared Business Profile schema, service, customer workspace, and admin visibility.

The repository does **not** implement the generic site schema, revision history, component registry, build jobs, deployment adapter/history, restoration, generic EMD identity, conversion service, registered-host LeadHub contract, DataForSEO, or cohort-aware billing.

## 3. Milestone 5 completion evidence

Sprint 8.7 Milestone 5 is **COMPLETE / PASS**. The validated and deployed commit is `ea81194e7d853782f927fdf58ed65eecd6473a7f`, which is the final deployed `main` state after the Milestone 5 implementation, follow-up fixes, and successful validation; it must not be described as a commit produced by Milestone 5 alone.

The final successful validation artifact has SHA-256:

```text
687a1444664f9d7167dfb316510f09094e922c2b83166874849db44fb10382a6
```

Historical Milestone 4 evidence remains separate and unchanged.

## 4. Current table inventory

| Area | Current tables | Current responsibility and constraints |
| --- | --- | --- |
| Core identity | `businesses`, `business_users`, `business_modules` | Legal/core business identity, membership, and product access. `businesses` remains authoritative for legal/core identity. |
| Shared facts | `business_profiles`; `business_profile_hours`; `business_profile_hour_exceptions`; `business_profile_faqs`; `business_profile_pricing_guidance`; `business_appointment_rules`; `business_transfer_rules`; `business_escalation_rules`; `business_notification_preferences` | Migration 021 provider-neutral facts, lifecycle/readiness, and rules. Child FKs include business/profile ownership. |
| Services | `business_sub_services`, `business_custom_services`, `sub_services`, `categories` | Selected standard services and customer-defined services. These remain authoritative service records. |
| Intake/configuration | `247sp_onboarding`, `247sp_website_configurations`, `247sp_business_content`, `247sp_service_pages` | Product-specific onboarding, service area/configuration, legacy business copy, and presentation-oriented service pages. Most are unique per business or business/service number. |
| Branding/presentation | `247sp_website_branding`, `247sp_website_service_images`, `247sp_website_content_overrides`, `website_integrations` | Business-bound presentation, file paths, overrides, and analytics/reference fields. Only the GA measurement ID is currently rendered. |
| Generation | `247sp_templates`, `247sp_template_assignments`, `247sp_generated_websites`, `247sp_generated_pages` | One seeded starter template, one assignment and generated website per business, and mutable JSON pages unique by website/slug. |
| Domain | `247sp_domain_selections`, `domain_requests`, `domain_assignments`, `website_domains`, `domain_dns_records`, `domain_events` | Legacy intake plus registrar/DNS/SSL workflow. Current assignment and website-domain uniqueness encode effectively one domain per business/site. |
| LeadHub | `contacts`, `contact_statuses`, `notes`, `tasks`, `activity_logs` | Business-scoped leads/contacts, notes, follow-up tasks, and cross-module activity summaries. |
| Billing | `plans`, `subscriptions`, `payments`, `stripe_webhook_events` | One plan per product key; one subscription per business/plan; payment and Stripe webhook state. No cohort snapshot exists. |

Migration 021 profile children are authoritative facts where their fields apply. Generated `content_json`, legacy onboarding copy, and overrides must not displace those facts.

## 5. Current class inventory

| Class/service | Current implemented role | Material limitation |
| --- | --- | --- |
| `SharedBusinessProfile` | Authorized reads/writes, validation, readiness, lifecycle, transactions, and audit summaries for migration 021 records | It is a business-facts service, not a CMS or publisher. |
| `SharedBusinessProfileUi` | Allowlisted profile form dispatch and presentation helpers | Limited to Shared Business Profile workflows. |
| `TwentyFourSevenSalesPartner` | Business access, onboarding/config/content/service saves, readiness, change requests, and launch approval | Product-specific; launch approval is inferred from latest activity. Several legacy multi-step route flows are outside one service transaction. |
| `SiteGenerator` | Builds pages from legacy 247SP data and `starter_local_service`; generates/regenerates the legacy website | Deletes and recreates pages; no revision/build/deployment history or provider boundary. |
| `WebsiteManager` | Reads/saves branding, overrides, images, integration references, and optional regeneration | Broad customer editing; files move before DB transaction; save and regeneration are separate transactions. |
| `DomainAutomation` / `DomainManager` | Registrar-neutral request, assignment, DNS, SSL, event, and website-domain state workflows | Business-bound model; `markLive()` sets published state without SitePublisher deployment proof. |
| `LeadHub` | Business-scoped contacts/leads/notes/tasks/activity and legacy website submission capture | No generic registered-site/routing contract, rate-limit store, or replay key. |
| `BillingFoundation` | Plan/subscription/payment reads and writes, admin metrics/status, Stripe state, webhook event persistence | Reads mutable plan fees; no sequence, cohort, locked terms, or Alpha dates. |
| `StripeBilling` | Checkout creation, webhook verification/processing, and Stripe-state mapping | Uses one recurring `STRIPE_247SP_PRICE_ID` and optional setup price ID. |
| `AdminPortal` | Admin listing/detail wrappers, website generation access, assets, and recent website leads | Route/service responsibilities are mixed and remain legacy-specific. |

## 6. Current customer route inventory

| Route | Current behavior | Current security/architecture finding |
| --- | --- | --- |
| `/247sp/business-profile.php` | Shared Business Profile facts, drafts, review submission | Auth, membership/module checks, service authorization, and CSRF are implemented. |
| `/247sp/dashboard.php` | Product status, profile readiness, website approval/change request | Auth/business access and CSRF are implemented; approval is activity-log-derived rather than revision-specific. |
| `/247sp/onboarding.php` | Multi-step identity, area, service, copy, domain, and email intake | Auth/business/module checks exist; state-changing POSTs have no CSRF token. |
| `/247sp/review.php` | Reviews and completes onboarding | Auth/business/module checks exist; completion POST has no CSRF token. |
| `/247sp/site-preview.php` | Authenticated rendering of generated pages and optional GA tag | Private preview only; not evidence of a public deployment pipeline. |
| `/247sp/website-manager.php` | Customer edits colors, copy, CTAs, images, and pricing-list assets | Auth/business/module checks exist; POST has no CSRF and the controls are broader than the approved future customer boundary. |

Future customer Website Manager behavior is preview, feedback/change request, approved asset/presentation input, and revision approval. Customers will not compose arbitrary layouts, publish, restore, archive, convert, or reassign domains/routing.

## 7. Current admin route inventory

| Route | Current behavior | Current security/architecture finding |
| --- | --- | --- |
| `/admin/websites.php` | Lists generated websites | Read-only legacy list. |
| `/admin/website.php` | Generates/regenerates and shows website detail | Admin protected; mutation POST has no CSRF; legacy generator only. |
| `/admin/website-editor.php` | Edits legacy services/presentation/integrations and regenerates | Admin protected; POST has no CSRF; multi-service save can partially succeed. |
| `/admin/business.php` | Business/module flags, notes, profile readiness/lifecycle | Admin protected; CSRF is implemented. |
| `/admin/domains.php` | Domain provider/status/DNS/SSL/live actions | Admin protected; mutation POSTs have no CSRF; live is not deployment proof. |
| `/admin/billing.php` | Subscription metrics and manual status changes | Admin protected; mutation POST has no CSRF; shows legacy plan fees. |

Internal Admin and Super Admin may eventually compose approved components, create revisions, validate, request customer review, internally approve, build, publish, restore, suspend, and archive. Super Admin approval is required for changes to ownership, routing, domain-rights context, and controlled conversions. No customer impersonation dependency is required.

## 8. Current domain and lifecycle inventory

`DomainManager` currently distinguishes request status (`requested`, `awaiting_customer`, `pending_purchase`, `pending_dns`, `pending_verification`, `ssl_pending`, `ready`, `live`, `error`, `active`, `transferred`, `expired`, `cancelled`), DNS status, SSL status, and `website_domains.publish_status` (`draft`, `ready`, `published`). Ownership is `customer_owned` or `fdv_owned` on assignments.

The legacy model is business-bound and effectively one-to-one: `domain_assignments` is unique by business and `website_domains` is unique by website. `DomainManager::markLive()` changes the request and assignment to live, upserts `website_domains` as published, and updates website-domain publish state. It does not prove that versioned site files were built, deployed, health-checked, or made restorable. Domain readiness and deployment success must therefore become separate state machines.

## 9. Current LeadHub integration inventory

`LeadHub::capture247spWebsiteSubmission()` accepts browser-posted `business_id`, `website_id`, and optional `page_id`, then verifies that the website belongs to the business and the page belongs to the website. It applies basic text/email checks, a honeypot, and simple automation heuristics; matches a contact by exact email/phone; and atomically creates or updates a contact plus optional note, task, and activity record.

This is useful compatibility behavior, but it is not the future public contract. It does not resolve the tenant from the request Host, verify an active generic site/domain association and routing assignment, persist an idempotency/replay key, or provide durable rate limiting. The endpoint redirects back to the authenticated preview and is not a generic public-site ingestion service.

## 10. Current billing and pricing inventory

The current repository has one `plans.product_key = '247sp'` row. Historical migrations seed/update old `plans.setup_fee` and `plans.monthly_fee` values. `subscriptions` links a business to a plan and later adds Stripe customer/subscription/session/invoice/payment-method/current-period fields. `payments` and `stripe_webhook_events` store billing results and webhook idempotency/status.

`StripeBilling` creates Checkout with one `STRIPE_247SP_PRICE_ID` and, when a setup fee is present, one optional `STRIPE_247SP_SETUP_FEE_PRICE_ID`. Customer and admin pages read fees from the current plan row. There is no durable pricing-cohort assignment, customer sequence number, locked fee snapshot, free-month configuration, introductory expiration, dedicated recurring-billing start, or versioned cohort Stripe references.

Current customer billing/account routes are `/accounts/subscriptions.php`,
`/accounts/billing.php`, and `/accounts/checkout.php`; they expose current plan fees,
subscription/payment state, and legacy Stripe Checkout readiness. The current internal
route `/admin/billing.php` lists subscriptions, legacy fees, Stripe references, MRR, and
manual status controls. None can display or enforce a cohort/sequence/locked-term model
that does not yet exist.

Historical migrations containing `$100`/`$47` or other superseded assumptions are historical evidence and must not be edited. A separate future additive billing migration is required.

## 11. Current analytics and integration inventory

`website_integrations` is unique by business and stores GA measurement ID, Search Console property, GTM ID, Clarity ID, Meta Pixel ID, and Google Business Profile URL. The admin editor can store those values. Only a valid Google Analytics measurement ID is currently rendered, and it is rendered into the authenticated preview. The other fields are references, not implemented integrations.

No OAuth/customer-authorization model, ownership lifecycle, disconnect history, DataForSEO client, rank-observation store, or SEO provider job exists. Google Analytics traffic data and future DataForSEO SEO/ranking observations are separate systems.

## 12. Current implementation weaknesses

Verified weaknesses at the baseline are:

- generator dependency on hard-coded `starter_local_service`;
- generator coupling to legacy `247sp_*` business, configuration, page, branding, and override records;
- regeneration updates one website, deletes all generated pages, and inserts replacements, losing page row identity;
- no site revision, approval, build, deployment, release, or restore history;
- generated `content_json` copies facts and can drift from authoritative records;
- one generated website per business and no generic EMD/internal identity;
- launch approval derived from the latest approval/change-request activity rather than a revision-specific approval record;
- domain live/published state without durable public build/deployment proof;
- no public SitePublisher adapter for the current DigitalOcean/Apache environment;
- no durable build/deployment jobs, retry/reconciliation, or conversion service;
- missing CSRF on legacy customer onboarding/review/Website Manager and admin website/editor/domain/billing mutations;
- `WebsiteManager::saveAndRegenerate()` and admin service-page-plus-site saves span multiple transactions and can partially succeed;
- uploaded files move into public storage before the database transaction and can become authoritative-looking orphans after rollback;
- public ingestion lacks Host/domain registration, active routing resolution, durable rate limiting, replay handling, and generic 247SP/EMD identity; and
- current billing cannot implement the approved cohorts or Alpha free period safely.

## 13. Source-of-truth matrix

| Concept | Authoritative source | Snapshot/reference consumers | Rule |
| --- | --- | --- | --- |
| Legal/core identity | `businesses` | Revisions and generated output | Site records do not replace legal identity. |
| Approved descriptions, hours, FAQs, operational rules | Shared Business Profile | Revision snapshot/reference | Generated wording is not the authoritative fact. |
| Services | Existing selected/custom business service records | Revision pages/sections | Do not build a second service catalog. |
| Service area | Existing business/configuration sources until deliberately evolved | Revision snapshot/reference | Avoid a duplicate monolithic CMS facts table. |
| Site identity/purpose/lifecycle | Future `sites` | UI, routing, publisher | Purpose and lifecycle are separate. |
| Customer/business association | Future `site_business_associations` | Authorization/routing | Optional; EMD sites need no fabricated business. |
| Site composition | Future revision/page/section records | Build artifacts | Immutable per revision after review starts. |
| Branding/presentation | Future themes/assets/presentation records | Revision snapshot | Rights and provenance travel with assets. |
| Domain registrar/DNS/SSL | DomainManager/domain-service tables | Site-domain association | Domain state is not deployment state. |
| Site deployment | Future SitePublisher build/deployment records | Domain/public routing | A successful deployment record is required. |
| Lead records | LeadHub | Site submission correlation | Routing resolves server-side. |
| Customer Google Analytics | Optional customer-owned authorized connection | Site tag configuration | Disconnectable; never required for SEO reporting. |
| SEO/ranking intelligence | Future platform-owned DataForSEO integration | Normalized/cache reporting records | Provider secrets remain server-side. |
| Generated revision | Reproducible presentation snapshot | Build/deployment | Never the source of reusable business facts. |
| Pricing | Future cohort configuration plus subscription snapshots | Checkout/admin/customer billing | Existing subscriptions use locked terms, not current counts. |

## 14. Generic site model

The future generic model is additive and coexists temporarily with legacy generation records. A `site` is a durable platform identity, not a generated file tree, business, domain, subscription, or routing target. It owns stable logical pages and a sequence of immutable revision snapshots. Builds and deployments reference revisions; associations connect sites to businesses, domains, routing, analytics, and assets.

The model supports 247SP, EMD, and internal/demo sites; multiple revisions; repository-owned reusable components; durable build/deployment history; restore; multiple domains; routing; and controlled conversion.

## 15. Site-purpose model

Initial `sites.purpose` values are exactly:

- `247sp`
- `emd`
- `internal_demo`

Purpose answers why the platform operates the site. It does not answer whether the site is active, approved, deployed, owned by a customer, or billable. A site with `purpose = 'emd'` may have no business association.

## 16. Site lifecycle

Initial `sites.lifecycle_status` values are:

`draft`, `demo`, `pending_customer`, `pending_internal_review`, `approved`, `active`, `suspended`, `cancellation_pending`, `conversion_pending`, and `archived`.

The future `SiteManager` owns allowlisted transitions and authorization. `transfer_review` is not a site status; domain transfer stays in DomainManager. Revision, build, deployment, domain, subscription, approval, routing, and conversion state remain separate. `active` requires an approved/published revision, successful current production deployment, valid primary domain/routing gates where applicable, and compatible subscription/product access for a 247SP site.

## 17. Revision lifecycle

Initial revision states are:

`draft`, `validation_failed`, `ready_for_review`, `changes_requested`, `customer_approved`, `internally_approved`, `published`, `superseded`, and `restored`.

Regeneration creates a new revision. It never deletes/recreates the published revision. Page and section rows for a revision become immutable once review begins; changes create the next revision. A restore copies a prior snapshot into a new revision with `restored_from_revision_id`, records `restored`, then follows required approval/build/deploy transitions. When a new revision becomes `published`, the former published revision becomes `superseded` in the same service transaction; its build and deployment history remains retained.

## 18. Approval architecture

Future `site_approvals` is the authoritative revision-specific approval history. Each row includes site, revision, approval type (`customer`, `internal`, `production`, `conversion`, or narrowly approved future types), state (`requested`, `approved`, `rejected`, `revoked`, `superseded`), actor user and actor type, timestamps, optional comments/reason, superseded/revoked linkage, correlation ID, and safe metadata.

Initial public launch and every material customer-visible revision require explicit customer approval. Public copy, branding, images, composition, page structure, and public functionality are material. A service must classify materiality explicitly and audit the classification. Non-material technical changes may use internal approval if the customer-approved public result is unchanged. Creation of a later material revision supersedes prior customer approval for launch eligibility; it does not erase history.

## 19. Component architecture

Use a hybrid registry:

- repository-owned PHP/templates/rendering, CSS, JavaScript, validation, escaping, and executable component implementations;
- database-owned approved component keys, variant keys, schema versions, labels/metadata, section configuration, selection, and ordering; and
- strict allowlist lookup by `(component_key, implementation_version)` and `(component_id, variant_key)`.

The database must never store executable PHP or JavaScript. Initial registry coverage should evaluate navigation, header, footer, hero, statistics, service grid/detail, trust cards, about/contact content, CTA blocks, LeadHub form, pricing-list link/download, SEO metadata, mobile CTA, and shared escaping/validation helpers. Unknown, inactive, or schema-incompatible component/variant/config combinations fail validation before build.

## 20. Customer UI and control boundary

The future customer Website Manager provides site preview, revision comparison/review, permitted asset or presentation input, feedback, change request, and revision approval. Business Profile stays the editor for reusable facts.

Customers cannot choose arbitrary components, freely rearrange layouts, edit executable markup, publish, restore, archive, convert, change site purpose, reassign domains, or change LeadHub routing. Every site/revision/page/asset lookup must join through an active customer membership and active module access; browser-submitted business/site IDs are never authorization by themselves.

## 21. Admin UI and control boundary

Internal Admin and Super Admin can eventually create briefs, compose pages from approved components, choose variants, create revisions, validate, submit for customer review, internally approve, queue builds, publish, restore, suspend, and archive through service methods. Routes do not own lifecycle SQL.

Only Super Admin can approve conversion or another operation that changes ownership, routing, or domain-rights context. Production publication requires explicit production approval and cannot be implied by a domain status change. All state-changing routes require CSRF and actor/correlation audit data.

## 22. Domain association model

Future `site_domain_associations` permits many domains per site. At most one association may be both active and primary. Secondary active domains may redirect to the primary. Primary selection belongs to the site/domain association service, not `domain_requests.domain_status` or `website_domains.publish_status`.

The association records normalized host, site, DomainManager identity/legacy assignment where applicable, association state, primary/redirect flags, redirect target, effective timestamps, verification state/reference, and audit fields. Customer-owned/BYOD and FDV/platform-owned rights remain distinguishable in DomainManager. A 247SP-to-EMD conversion cannot retain a customer-owned domain without explicit authority.

The current domain schema requires a business. Future EMD support therefore requires an additive provider-neutral DomainManager identity or equivalent safe extension; it must not fabricate a business or weaken current ownership records.

## 23. LeadHub routing model

Future `site_routing_assignments` independently maps a site to one active routing target. A 247SP target resolves to an authorized business. An EMD target resolves to an approved EMD routing key/pool owned by the later routing implementation. Assignment history has effective/revoked timestamps, actor, reason, and correlation ID.

Conversion is ordered: suspend public ingestion if necessary; revoke customer routing; validate data separation; establish the new target; validate; then reactivate. There is never a window with both customer and EMD routing active. Routing changes require service authorization and immutable audit evidence.

## 24. Registered-site public-ingestion contract

The future provider-neutral flow is:

```text
request Host/domain
  -> normalized permitted active site-domain association
  -> active site and current production deployment
  -> optional opaque site/page identifier cross-check
  -> active routing assignment
  -> business or EMD target
  -> LeadHub capture
```

The browser does not authoritatively choose `business_id` or routing. An opaque site/page identifier helps correlation but is not a secret. The service enforces allowed method/content type/size, Host normalization, permitted-origin policy where relevant, rate limiting, honeypot/spam controls, validation, replay/duplicate handling using a bounded idempotency key/fingerprint, server-side tenant/routing resolution, safe errors, and correlation IDs. 247SP and EMD use the same ingestion DTO and orchestration; only the resolved routing target differs.

## 25. Asset and rights model

Future assets record owner/context, optional business and site associations, type, storage key, checksum, MIME/size/dimensions, source, rights classification, source attribution/license/expiry, lifecycle, retention hold, uploader, timestamps, and revision references. Rights classifications must distinguish platform-owned/generic, customer-owned, customer-licensed-for-site, third-party licensed, and prohibited/unknown.

Platform code, component implementations, layouts, and system-owned generic assets are generally reusable. Customer logos, photographs, testimonials, uploads, supplied copy, CRM data, leads, conversations, and private communications are not automatically reusable. Conversion requires a recorded rights decision for every referenced customer-context asset or replacement/removal.

Upload flow stages and validates bytes, commits a `pending` asset intent, and finalizes storage through an idempotent post-commit operation before marking the asset `ready`. Failed finalization is retryable; stale pending objects/rows have reconciliation and cleanup. A DB rollback must not leave an untracked authoritative file, and a storage failure must not make a revision publishable.

## 26. Analytics and SEO architecture

Analytics connections, site deployment, and SEO observations are independent associations. Site/revision records may reference approved SEO presentation overrides (title, description, canonical policy, structured-data configuration) while reusable facts remain authoritative elsewhere. Provider jobs fetch server-side, normalize results into provider-neutral observation/reporting records, preserve provider/source timestamps, and never expose credentials.

The initial generic platform should define association boundaries now, but DataForSEO runtime/tables may be delivered in a later focused integration PR when reporting requirements and retention are approved. No DataForSEO code or migration belongs to Milestone 6.

## 27. Google Analytics and customer ownership model

Google Analytics is **optional customer-connected Google Analytics**. The connection/property is customer-owned, authorized by the customer, traffic/visitor oriented, disconnectable, and not required for UBO SEO/ranking reporting. A future connection record should store site/business context, provider/property reference, authorization owner, scopes/connection state, connected/disconnected timestamps, and no browser-visible secret.

On conversion, customer GA mapping is removed unless explicitly reassigned with authority. Historical customer analytics remain customer-owned; an EMD property does not inherit them by default. Existing per-business GA ID rendering is a legacy foundation, not a complete ownership/authorization model.

## 28. DataForSEO provider architecture

DataForSEO is the planned platform-owned provider for keyword rankings, SERP results, position/history, supported local-search data, competitive metrics, and domain/search visibility. Credentials are FDV/platform secrets stored only in approved server configuration, never customer-owned, embedded in generated sites, or sent to browser JavaScript.

A future provider-neutral `SeoIntelligenceProvider` boundary should accept bounded jobs, return normalized observations, use durable idempotent job state, record provider/correlation IDs and safe errors, and apply retention policy. Provider payloads may be retained only where necessary and sanitized. DataForSEO observations must never be labeled Google Analytics. During conversion, platform-derived observations remain with a site/domain only when approved retention and rights policy allows it.

## 29. EMD representation

EMD properties are normal `sites` rows with `purpose = 'emd'`. They do not require `business_id` and never receive a fabricated customer business. Site identity, purpose, business/customer association, domain association, routing assignment, analytics association, lifecycle, and conversion state are separate records/state machines. This permits an EMD property to exist before purchase and later acquire a customer association through controlled conversion.

## 30. Bidirectional conversion architecture

Future conversion supports `emd`/`internal_demo` to purchased `247sp`, and eligible canceled `247sp` to `emd`. Neither direction is automatic. `site_conversion_events` is an explicit workflow record with source/target purpose, state, eligibility decision, Super Admin approval, domain-rights decision, content/media-rights decision, customer-data-separation result, routing before/after, analytics action, validation result, actor, correlation ID, and timestamps.

Conversion must preserve the source site/revisions for audit, create a new conversion revision where public content changes, remove customer routing before EMD routing, remove/reassign customer analytics, separate CRM/private data, validate domain rights, validate claims/assets, and record completion only after all gates pass. Not every canceled site qualifies. Failure leaves the previous safe purpose/routing/deployment intact or the site suspended; repair resumes idempotently from durable steps.

## 31. Retention and archive policy direction

Initial architecture supports `suspended` and `archived`; it does not automatically delete sites or assets and does not automatically convert canceled sites. Retention is policy-driven and may later add holds, expiry, anonymization, or deletion after contractual/legal review. Published revisions, deployment evidence, approvals, conversion decisions, and audit records require retention sufficient for operational and rights history. Customer data remains governed by its own retention rules and cannot be retained merely because site structure is retained.

## 32. Legacy compatibility and backfill plan

Use a staged hybrid transition:

1. Add generic site-platform tables; do not edit historical migrations.
2. Identify eligible legacy generated websites with deterministic eligibility/error reporting.
3. Create exactly one generic `sites` row and compatibility mapping per eligible legacy website.
4. Link the existing business through `site_business_associations`.
5. Create stable logical pages and a baseline imported revision from legacy generated pages, branding, overrides, integration references, and authoritative-fact references/snapshots.
6. Preserve legacy IDs in a dedicated mapping table/columns and store import source/hash/time.
7. Validate counts, slugs, content hashes, business ownership, domains, and preview equivalence.
8. Keep legacy records for compatibility/read support while runtime consumers move one at a time.
9. If a temporary dual write is unavoidable, one service owns it, records reconciliation state, and treats generic records as authoritative only after a declared cutover.
10. Eliminate dual writes after validation; retire legacy write responsibilities only after all consumers and repair checks pass.

Backfill is rerunnable and idempotent by unique legacy mapping. It never deletes or mutates legacy pages. Unsupported or inconsistent rows are quarantined in a report/job error state for manual repair.

## 33. Sprint 8.8 proposed schema

All entities in this section are **proposed/future**. Names are implementation-ready recommendations, not existing tables. The provisional website migration name is `022_website_platform_foundation.sql`; Milestone 7 must confirm the name and exact split. Billing uses a separate additive migration.

Unless a table overrides the convention, `id` and foreign-key IDs below are `BIGINT
UNSIGNED`; state/key/type fields are bounded `VARCHAR`; booleans are `TINYINT(1)`;
monetary fields are `DECIMAL(10,2)` plus `CHAR(3)` currency; timestamps are UTC
`DATETIME`; JSON is used only for validated configuration/snapshots, not executable
code. Required columns are `NOT NULL`; columns explicitly called nullable accept
`NULL`. Every table has `created_at` and, when mutable, `updated_at`.

### 33.1 `sites`

- **Responsibility/source of truth:** durable site identity, purpose, lifecycle, and current pointers; owned by `SiteManager`.
- **Columns:** `id BIGINT UNSIGNED PK`; `site_key CHAR(36) NOT NULL`; `purpose VARCHAR(32) NOT NULL`; `lifecycle_status VARCHAR(40) NOT NULL`; nullable `current_published_revision_id BIGINT UNSIGNED`; nullable `current_production_deployment_id BIGINT UNSIGNED`; `created_by_user_id BIGINT UNSIGNED NULL`; `created_at`, `updated_at`; nullable `suspended_at`, `archived_at`; `lock_version INT UNSIGNED NOT NULL DEFAULT 0`.
- **Keys/indexes:** unique `site_key`; indexes `(purpose,lifecycle_status)`, `lifecycle_status`, published/deployment pointers. Pointer FKs are added after dependent tables and use `ON DELETE SET NULL`.
- **Delete/retention:** no routine hard delete; archive. User FK `SET NULL`.
- **Legacy/backfill/repair:** one row per eligible `247sp_generated_websites`; purpose `247sp`; imported lifecycle derived conservatively (`draft`/`approved`, never `active` from domain status alone); mapping ensures idempotency.

### 33.2 `site_business_associations`

- **Responsibility:** separate customer/business relationship from site identity.
- **Columns:** `id`, `site_id NOT NULL`, `business_id NOT NULL`, `association_role VARCHAR(32)` initially `customer`, `status VARCHAR(24)`, `effective_at`, nullable `ended_at`, actor/reason/correlation, timestamps.
- **Keys/indexes:** FKs to site/business `RESTRICT`; indexes by business/status and site/status; a generated nullable active-site key or service-enforced lock permits at most one active customer association per site while allowing history.
- **Delete/retention:** revoke/end, never cascade business deletion into site history.
- **Legacy/backfill/repair:** link legacy website business; EMD has no row. Cross-purpose validator flags an active customer association on `emd` unless conversion is in progress.

### 33.3 `site_pages`

- **Responsibility:** stable logical page identity across revisions.
- **Columns:** `id`, `site_id`, `page_key VARCHAR(100)`, `created_at`, nullable `retired_at`.
- **Keys/indexes:** unique `(site_id,page_key)`; FK site `RESTRICT`; index `(site_id,retired_at)`.
- **Lifecycle/retention:** SiteManager creates/retires; never delete merely because a later revision omits the page.
- **Legacy/backfill/repair:** create stable keys from legacy type/slug with deterministic collision handling.

### 33.4 `site_generation_briefs`

- **Responsibility:** immutable/versioned input intent for generation or composition.
- **Columns:** `id`, `site_id`, `brief_version INT`, `state`, `brief_json JSON`, `source_type`, nullable `source_reference`, `created_by_user_id`, `created_at`, nullable `superseded_at`, `content_hash CHAR(64)`.
- **Keys/indexes:** unique `(site_id,brief_version)` and `(site_id,content_hash)` where appropriate; FKs `RESTRICT`/user `SET NULL`.
- **Retention/backfill:** retain with revision history; optional baseline imported brief summarizes legacy sources without becoming facts.

### 33.5 `site_revisions`

- **Responsibility:** immutable reproducible site-presentation snapshot and revision lifecycle.
- **Columns:** `id`, `site_id`, `revision_number INT UNSIGNED`, `lifecycle_status VARCHAR(40)`, nullable `based_on_revision_id`, `restored_from_revision_id`, `generation_brief_id`; `materiality VARCHAR(24)` (`material`, `non_material`, `undetermined`); `snapshot_schema_version`; `facts_snapshot_json JSON`; `snapshot_hash CHAR(64)`; actor/timestamps including `review_ready_at`, `published_at`, `superseded_at`; `correlation_id VARCHAR(100)`.
- **Keys/indexes:** unique `(site_id,revision_number)` and `(site_id,snapshot_hash)` if duplicate snapshots are prohibited; indexes by site/status and correlation; self FKs `SET NULL`, site `RESTRICT`, brief `SET NULL`. A generated nullable key enforces at most one `published` revision per site.
- **Lifecycle/delete:** `SiteManager`; immutable after review; retain. Failed regeneration creates/fails a new revision and leaves published pointer intact.
- **Legacy/backfill/repair:** one baseline imported revision, deterministic hash, source references, conservative status. Repair recomputes hash and checks pointer/status consistency.

### 33.6 `site_revision_pages`

- **Responsibility:** version-specific title, slug, SEO, navigation, and ordering for a stable page.
- **Columns:** `id`, `revision_id`, `site_page_id`, `title`, `slug`, `page_type`, `navigation_label NULL`, `sort_order INT`, `seo_json JSON NULL`, `presentation_json JSON NULL`, `content_hash CHAR(64)`, timestamps.
- **Keys/indexes:** unique `(revision_id,site_page_id)` and `(revision_id,slug)`; FKs revision/page `RESTRICT`; indexes for revision ordering.
- **Delete/retention:** cascade only if an unreviewed draft revision is explicitly discarded by service; reviewed/published revisions retained.
- **Legacy/backfill/repair:** copy legacy title/slug/type/ordering; split validated content into sections; retain raw import evidence only in bounded import metadata.

### 33.7 `site_page_sections`

- **Responsibility:** ordered approved component configuration within one revision page.
- **Columns:** `id`, `revision_page_id`, `section_key VARCHAR(100)`, `component_variant_id`, `sort_order INT`, `configuration_schema_version INT`, `configuration_json JSON`, `content_hash CHAR(64)`, timestamps.
- **Keys/indexes:** unique `(revision_page_id,section_key)` and `(revision_page_id,sort_order)`; FKs revision page/component variant `RESTRICT`; indexes by variant.
- **Lifecycle/delete:** revision-owned and immutable with it.
- **Legacy/backfill/repair:** map legacy JSON to approved section types; quarantine unsupported content instead of storing executable markup.

### 33.8 `site_themes`

- **Responsibility:** one revision-specific validated presentation/theme snapshot.
- **Columns:** `id`, `revision_id`, `theme_key`, `theme_version`, colors/typography/config JSON, optional approved asset references, `content_hash`, timestamps.
- **Keys/indexes:** unique `revision_id`; index `(theme_key,theme_version)`; FKs revision/assets `RESTRICT`.
- **Lifecycle/backfill:** revision-owned; import legacy colors/assets. Theme configuration never contains executable code.

### 33.9 `component_definitions` and `component_variants`

- **Responsibility:** database metadata/allowlist references for repository code.
- **Columns:** definitions: `id`, unique `component_key`, label/category, `implementation_version`, `configuration_schema_version`, status, metadata JSON, timestamps. Variants: `id`, `component_definition_id`, `variant_key`, label, schema/version/status/metadata, timestamps.
- **Keys/indexes:** unique component key; unique `(component_definition_id,variant_key)`; indexes by status/category; FK variants `RESTRICT`.
- **Lifecycle/delete:** ComponentRegistry owns activation/versioning; deactivate/supersede, do not delete referenced rows.
- **Backfill/repair:** seed only keys backed by repository implementations; validation detects DB/code drift.

### 33.10 `site_assets` and `site_revision_assets`

- **Responsibility:** asset identity, provenance/rights/lifecycle, plus immutable revision references.
- **Columns:** asset: `id`, nullable `site_id`, nullable `business_id`, `asset_key`, `asset_type`, `storage_key`, `checksum_sha256`, MIME/bytes/dimensions, `source`, `rights_classification`, nullable rights metadata/expiry, `lifecycle_status`, retention hold, actor/timestamps. Join: `revision_id`, `asset_id`, `usage_key`, optional page/section reference.
- **Keys/indexes:** unique `asset_key`; unique `(revision_id,asset_id,usage_key)`; checksum/context indexes; FKs mostly `RESTRICT`, business `SET NULL` only with retained rights metadata.
- **Lifecycle/delete:** AssetManager pending/ready/quarantined/retired; no deletion while referenced or on hold.
- **Legacy/backfill/repair:** register legacy paths after file existence/hash/rights review; unknown rights are not conversion-eligible.

### 33.11 `site_approvals`

- **Responsibility:** revision-specific approval history.
- **Columns:** `id`, `site_id`, `revision_id`, `approval_type`, `state`, nullable `actor_user_id`, `actor_type`, comments/reason, nullable `supersedes_approval_id`, `requested_at`, `decided_at`, `revoked_at`, `correlation_id`, safe metadata JSON.
- **Keys/indexes:** FKs site/revision `RESTRICT`, actor/supersedes `SET NULL`; indexes `(revision_id,approval_type,state)`, site/date, correlation. Service prevents more than one current approved decision per type/revision.
- **Retention/backfill:** immutable decisions with supersession; legacy latest launch activity may be imported as historical/unverified evidence, never silently elevated to a new revision approval.

### 33.12 `site_build_jobs`

- **Responsibility:** durable idempotent build queue and result.
- **Columns:** `id`, `site_id`, `revision_id`, `environment`, `status`, `idempotency_key`, attempts/max attempts, requested/started/completed timestamps, repository commit/build version, artifact URI/hash, safe error code/summary, worker/correlation IDs.
- **Keys/indexes:** unique `idempotency_key`; indexes `(status,requested_at)`, revision/environment, correlation; FKs site/revision `RESTRICT`.
- **Lifecycle/delete:** SiteBuildService/worker; retain operational history. Retry the same intent idempotently; never mutate published revision on failure.

### 33.13 `site_deployments`

- **Responsibility:** versioned staging/production deployment and restoration evidence.
- **Columns:** `id`, `site_id`, `revision_id`, `build_job_id`, `environment`, `status`, `release_key`, `idempotency_key`, `is_current`, nullable `restores_deployment_id`, approval reference, requested/started/completed timestamps, target/provider key, health-check summary, safe error, correlation.
- **Keys/indexes:** unique idempotency/release keys; indexes site/environment/date and status; FKs `RESTRICT`/restore `SET NULL`. A generated nullable `(site,environment)` current key enforces at most one current successful deployment.
- **Lifecycle/delete:** SitePublisher orchestrator and provider adapter; retain. Activate new current deployment and retire prior current pointer atomically only after external success is reconciled.

### 33.14 `site_domain_associations`

- **Responsibility:** many domains per site, primary/secondary/redirect selection.
- **Columns:** `id`, `site_id`, normalized `host`, nullable generic DomainManager/domain identity and legacy assignment ID, `status`, `is_primary`, `redirect_to_association_id`, verification/effective timestamps, actor/reason/correlation, timestamps.
- **Keys/indexes:** unique normalized host while active according to policy; indexes by site/status; generated nullable primary-active-site key unique; FKs `RESTRICT`/redirect `SET NULL`.
- **Lifecycle/delete:** SiteDomainService with DomainManager; revoke/retain history. Backfill `website_domains`; duplicate hosts become repair exceptions.

### 33.15 `site_routing_assignments`

- **Responsibility:** current and historical LeadHub/EMD routing independent from purpose.
- **Columns:** `id`, `site_id`, `routing_type` (`business`, `emd`), nullable `business_id`, nullable `routing_target_key`, `status`, effective/revoked timestamps, actor/reason/correlation.
- **Keys/indexes:** site/status and business/status indexes; FK business `RESTRICT`; generated nullable active-site key unique; CHECK/service validation requires exactly the target appropriate to type.
- **Lifecycle/delete:** SiteRoutingService; revoke, never overwrite. Backfill active 247SP business routing only after domain/site validation.

### 33.16 `site_conversion_events`

- **Responsibility:** durable controlled conversion workflow and rights/data-separation evidence.
- **Columns:** `id`, `site_id`, source/target purpose, status, eligibility/result fields, domain/content/media/customer-data/analytics decisions, old/new routing references, requested/approved/completed actor/timestamps, correlation, safe checklist JSON.
- **Keys/indexes:** unique idempotency/correlation key; site/date and status indexes; FKs site and actors `RESTRICT`/`SET NULL`.
- **Lifecycle/delete:** SiteConversionService; immutable completed events retained. Repair resumes incomplete events and validates actual associations before completion.

### 33.17 `legacy_site_mappings` and `site_events`

- **Responsibility:** deterministic compatibility mapping and generic immutable audit not dependent on a business.
- **Columns:** mapping includes site, legacy website ID, import revision, source hash/time/status/error; event includes site/revision/actor, event type, result, reason, correlation, safe metadata, timestamp.
- **Keys/indexes:** unique legacy website ID and site mapping; unique/bounded event correlation where required; FKs `RESTRICT`.
- **Retention:** mapping lasts through retirement; events are append-only. `activity_logs` may receive customer-facing summaries after successful business-associated mutations but is not the only site audit source.

### 33.18 `domains` provider-neutral identity

- **Responsibility/source of truth:** future DomainManager-owned domain identity that is not forced to belong to a business; technically required for EMD domains.
- **Columns:** `id`; `normalized_domain_name VARCHAR(253) NOT NULL`; `ownership_type VARCHAR(32) NOT NULL`; `lifecycle_status VARCHAR(40) NOT NULL`; nullable registrar/provider reference fields; rights/policy reference; actor/timestamps.
- **Keys/indexes:** unique normalized domain name; indexes by ownership/lifecycle and provider reference. Site-domain associations reference this row with `ON DELETE RESTRICT`.
- **Lifecycle/delete/retention:** DomainManager owns it; retire/transfer rather than hard-delete while associations/events exist.
- **Legacy/backfill/repair:** create/link identities from current assignments after normalization and duplicate/rights review. Current `domain_requests`, `domain_assignments`, DNS records, and events remain authoritative compatibility records for existing business workflows until a focused additive DomainManager evolution supports non-business provider operations. EMD support never inserts a fake business.

### 33.19 `site_analytics_connections` and future SEO observations

- **Responsibility/source of truth:** optional customer-owned analytics connection association; later provider-neutral cached SEO/ranking observations remain a separate reporting aggregate.
- **Columns:** connection: `id`, `site_id`, nullable `business_id`, `provider_key`, provider property reference, `ownership_type`, `authorization_state`, nullable authorization-owner user, connected/disconnected timestamps, scopes metadata, timestamps. Observation design: site/domain/keyword scope, provider key, observation type/date, normalized value/position JSON, provider-source timestamp, retention timestamp, job/correlation reference.
- **Keys/indexes:** connection uniqueness by active site/provider/property; indexes by business/state and site/state; FKs site/business/user `RESTRICT` or actor `SET NULL`. Observation uniqueness uses a bounded scope hash plus provider/type/observed-at and references the provider job.
- **Lifecycle/delete/retention:** AnalyticsConnectionManager owns connect/disconnect; disconnection retains only policy-approved metadata. SEO provider jobs own observations with explicit retention.
- **Legacy/backfill/repair:** a legacy GA ID is not silently treated as an authorized customer connection; flag it for ownership/authorization review. DataForSEO has no backfill because it is not implemented. These tables may be delivered in a later focused integration migration rather than initial M1, but the association boundary is reserved now.

## 34. Foreign keys, indexes, and uniqueness design

- Every child references its aggregate root; revision pages must prove stable page and revision belong to the same site through service validation and, where practical, composite keys.
- Use `RESTRICT` for revision/build/deployment/approval/assets history; use `SET NULL` only for optional actors or superseded references; do not cascade-delete operational history.
- Use bounded normalized hosts/keys and generated hashes for long values to stay within `utf8mb4` index limits.
- Enforce one revision number per site, one slug per revision, one section key/order per revision page, one active routing assignment per site, at most one active primary domain per site, and one current successful deployment per site/environment.
- MySQL partial uniqueness uses a documented generated nullable key plus unique index, with application locks/validation as defense in depth.
- Allocate revision numbers, sequence numbers, primary domain switches, routing switches, and current deployment pointers under row locks.
- Every backfill mapping and external job has a unique idempotency key.

## 35. Service-layer blueprint

| Future service | Responsibility |
| --- | --- |
| `SiteManager` | Authorization, site identity/purpose/lifecycle, business associations, revision numbering, materiality, and current pointers. |
| `SiteRevisionManager` | Draft copy/create, page/section/theme validation, immutable snapshots, review transitions, restore candidate creation. |
| `ComponentRegistry` | Repository implementation allowlist, variant/schema validation, metadata synchronization. |
| `SiteApprovalManager` | Revision-specific customer/internal/production decisions, revocation/supersession, launch gates. |
| `SiteAssetManager` | Upload intents, validation/finalization, rights/lifecycle/retention, reference checks. |
| `SiteDomainService` | Generic site-domain association and primary/redirect rules while delegating registrar/DNS/SSL to DomainManager. |
| `SiteRoutingService` | One active server-side routing target, atomic swaps, public resolution. |
| `SiteBuildService` | Durable build intent, idempotency, validation, artifact manifest/hash, worker reconciliation. |
| `SitePublisher` | Provider-neutral staging/production deploy/restore orchestration and approval gates. |
| `ApacheDigitalOceanSitePublisher` | Planned initial adapter for the existing FDV DigitalOcean/Apache environment. |
| `SiteConversionService` | Super Admin approval, rights/data/domain/analytics/routing checklist and conversion state. |
| `RegisteredSiteSubmissionService` | Host/site/page resolution, abuse/replay controls, routing, LeadHub DTO/correlation. |
| `AnalyticsConnectionManager` | Optional customer GA connection ownership and disconnect/reassignment. |
| `SeoIntelligenceProvider` | Planned DataForSEO abstraction and normalized server-side observations. |
| `PricingCohortManager` | Separate first-customer-critical sequence/cohort/locked-term assignment. |

Controllers authenticate, parse allowlisted input, enforce CSRF, call one service operation, and render/redirect. They do not perform direct lifecycle SQL.

## 36. Transaction boundaries

- Services start/own transactions and re-check tenant/role authorization inside the transaction.
- Create revision plus all pages/sections/theme/snapshot and audit event atomically.
- Transition lifecycle/approval and update invalidated approvals/current pointers atomically under site/revision locks.
- Allocate a 247SP customer sequence, determine cohort, snapshot terms, and link subscription atomically in the billing stream.
- Switch primary domain or active routing with row locks and one transaction; never permit two active primaries/targets.
- Write success audit/activity only in the successful mutation transaction. Rollback produces no success activity.
- File/provider/network/deployment operations never run inside long DB transactions. Persist intent, commit, execute externally, then reconcile result in a short transaction.
- A failed generation/build/deployment never changes the currently published revision/deployment.

## 37. External job and deployment boundaries

Build and deployment jobs are durable, idempotent, retryable, and correlation-addressable. A job claims work with bounded leases/attempts, validates the exact immutable revision and repository implementation version, writes a content-addressed artifact/manifest, and records safe failure summaries. Deployment consumes a successful artifact, requires explicit production approval, creates a versioned release, performs health checks, and only then marks it current. Previous releases remain restorable.

The initial planned provider is the existing FDV DigitalOcean/Apache environment behind `SitePublisher`; no filesystem/Apache assumptions leak into SiteManager. Restoration is a new deployment referencing a prior known-good build/revision, not destructive file copying without history. Provider operations and DB transactions are reconciled with idempotency and repair commands/jobs.

## 38. CSRF and security remediation plan

Sprint 8.8 requires authenticated management sessions, active membership/module access, tenant isolation, service-level authorization, protected Admin/Super Admin controls, CSRF on every state-changing browser route, prepared statements, output escaping, allowlisted repository components, safe errors, cross-business ID rejection, and ownership validation for site/revision/page/asset/domain/routing IDs. No provider secret enters browser/site output.

Legacy gaps requiring focused remediation before their mutations remain reachable are `/247sp/onboarding.php`, `/247sp/review.php`, `/247sp/website-manager.php`, `/admin/website.php`, `/admin/website-editor.php`, `/admin/domains.php`, and `/admin/billing.php`. `business-profile.php`, `dashboard.php`, and `admin/business.php` demonstrate the current CSRF pattern. Milestone 6 documents but does not fix these routes.

Public submission additionally requires permitted-domain validation, rate limiting, spam controls, payload limits, replay/duplicate protection, correlation IDs, and server-side routing resolution. Security tests must cover cross-tenant IDs and inactive/suspended associations.

## 39. Activity and audit rules

- Canonical `site_events`, approvals, conversion events, jobs, and deployments record actor/system identity, result, timestamp, correlation ID, and safe bounded metadata.
- `activity_logs` remains a business/customer timeline summary when a business association exists; it cannot be the only approval or EMD audit store.
- No secret, raw provider credential, full provider payload, private communication, or unnecessary customer data enters generic audit metadata.
- Attempt/failure events may be written safely; success is written only after the corresponding state mutation commits.
- Revocation/supersession appends history; it never rewrites the original decision.

## 40. Migration sequencing

Planned Sprint 8.8 sequencing:

1. Confirm schema names and MySQL compatibility in Milestone 7.
2. Add component metadata, sites, associations, pages/revisions/composition, approvals/assets, jobs/deployments, domains/routing, mappings/events in dependency-safe additive order.
3. Seed only repository-backed component/variant keys.
4. Run idempotent legacy eligibility and mapping backfill.
5. Import baseline revisions without changing legacy data.
6. Validate and expose read-only generic comparisons.
7. Move service consumers in staged PRs; use bounded compatibility adapters.
8. Declare generic authority only after staging reconciliation; later retire legacy writes.

`022_website_platform_foundation.sql` is provisional. Cohort pricing is unrelated and should use a separate future additive billing migration/PR. Historical migrations remain immutable.

## 41. Repair and rollback strategy

Schema rollout is additive; application rollback can return to legacy readers while generic rows remain dormant. Backfill rollback disables mappings/authority flags rather than deleting legacy data. Repairs are forward-only migrations or idempotent commands/jobs—never edits to applied migrations.

Repair checks cover orphan/mismatched associations, multiple published revisions/current deployments/primary domains/routes, invalid component keys/schema versions, missing assets/artifacts, stale job leases, deployment/provider drift, content hashes, and legacy mapping counts. Failed external operations retain safe state and retry metadata. Production restore selects a previous successful deployment; published data remains intact during failed regeneration/build.

## 42. Validation blueprint

### Milestone 6 documentation validation

- `git diff --check`
- `git diff --cached --check`
- exact changed-file and documentation-only scope review
- no PHP, JS, CSS, public/runtime asset, configuration, migration, script, staging, production, database, or provider change
- consistency search for Milestone 5/6 status, current-versus-future capability, analytics ownership, pricing, billing status, conversion, publishing, and customer permissions

Milestone 6 has no staging runtime runbook because it has no runtime change.

### Future Sprint 8.8 standalone tests

- `SiteManagerAuthorizationTest.php`
- `SiteLifecycleTest.php`
- `SiteRevisionTest.php`
- `SiteCompositionValidationTest.php`
- `SitePublishingRollbackTest.php`
- `SiteConversionIsolationTest.php`
- `SiteRouteCsrfTest.php`
- `LeadHubSiteSubmissionContractTest.php`
- compatibility/backfill and rerun coverage
- static no-direct-SQL checks for customer/admin site routes

Tests cover tenant/role/module gates, state transition matrices, immutable revision/page identity, material approval invalidation, component allowlists/schema versions, atomicity, idempotency, failed build/deploy preservation, primary domain/routing uniqueness, cross-business IDs, rights/analytics separation, CSRF, Host resolution, rate limits, spam, replay, and safe errors.

### Future Sprint 8.8 staging validation

Validate backfill/reconciliation; site/revision/component creation; preview; change requests; customer/internal/production approval; build success/failure/retry; deployment; restoration; domain/SSL gates; Shared Business Profile consumption; LeadHub submission; suspend/archive; both approved conversion directions; and customer-data isolation.

Browser coverage includes customer dashboard/profile/revision preview/feedback/approval; admin list/detail/composition/publish/restore/archive/domain; LeadHub form; responsive/accessibility smoke; and clean console. Logs use bounded Apache/PHP and worker deltas, correlation IDs, no secrets/raw customer data, and no warnings/notices/fatals/PDO exceptions.

Cleanup removes synthetic records/assets/leads, reconciles site/revision/build/deployment state, checks cross-tenant/orphans, and reconciles repository/database baseline. The planned runbook is `docs/sprint-8.8-website-platform-staging-validation.md`, created with the implementation—not in Milestone 6.

## 43. Sprint 8.8 PR sequence

| Milestone | Planned focused result |
| --- | --- |
| M1 | Generic schema, component metadata seeds required for import, legacy mapping, idempotent compatibility/backfill, reconciliation tests. |
| M2 | `SiteManager` plus revision/lifecycle/approval services and authorization/transaction tests. |
| M3 | Component registry, page/section/theme composition, validation, asset references. |
| M4 | Admin brief/composition/revision/review workflow; no direct route SQL; CSRF. |
| M5 | Customer preview, feedback/change request, approved inputs, revision approval; done-for-you boundary. |
| M6 | Build, provider-neutral deployment, DigitalOcean/Apache adapter, production gate, retry/history/restore. |
| M7 | Registered-site LeadHub ingestion, domain/routing associations, EMD identity/conversion compatibility and isolation. |
| M8 | Full staging validation, cleanup/reconciliation, documentation/readiness closeout. |

Each PR preserves compatibility and can be validated independently. Sprint 8.8 must not be one giant CMS PR.

## 44. Updated pricing cohort architecture

There is one 247SP product and four pricing cohorts, not feature tiers:

| Cohort | Customer positions | Setup fee | Introductory period | Recurring price |
| --- | ---: | ---: | --- | ---: |
| Alpha | 1–5 | $0 | First 6 months free | $79/month afterward |
| Beta | 6–10 | $0 | None | $97/month |
| Founding | 11–25 | $100 one-time | None | $147/month |
| Standard | 26+ | $250 one-time | None | $197/month |

Assigned terms remain with the subscription and do not change when later cohorts start, public pricing changes, other customers cancel, or active counts change. Setup fees are one-time charges and are excluded from MRR.

Future durable configuration needs product, cohort identifier/display name, start/end position, setup fee, recurring price, free introductory months, effective/active state, recurring Stripe price reference, and optional setup Stripe price reference. Do not hard-code these values into signup/onboarding/checkout/customer/admin routes.

The separate future billing migration should add a `pricing_cohorts` aggregate (or
equivalent) with `id`, `product_key/product_id`, unique `cohort_key`, display name,
inclusive `position_start`, nullable inclusive `position_end`, setup/recurring amounts
and currency, free introductory months, effective start/end, active state, recurring
Stripe price reference/version, nullable setup Stripe price reference/version, and
timestamps. Overlapping active ranges for the same product are rejected under a
product lock and validation. Configuration is retained/versioned rather than edited in
a way that changes already assigned subscriptions.

## 45. Pricing assignment model

One completed/qualified 247SP **business subscription** consumes one permanent customer position; a multi-business owner may consume multiple positions. Cancellations never reopen positions, and separate environment databases keep staging/test allocations out of production.

The exact qualifying event for the future billing implementation is the approved billable subscription activation/signup event—not anonymous account creation. Milestone 7 must finalize the event name and its relationship to payment-method/Checkout state before implementation.

Conceptual transaction:

```text
lock product sequence counter
  -> verify qualifying business subscription and idempotency
  -> allocate next never-reused customer_sequence_number
  -> select effective cohort containing that number
  -> snapshot setup/monthly/free-month terms and Stripe price versions
  -> store assignment and actual billing dates
  -> commit
```

Use a product-scoped sequence counter/allocation record and a unique `(product_id, customer_sequence_number)`. The subscription stores assigned cohort, sequence, locked setup/monthly amounts and currency, assignment/signup dates, introductory start/expiration, recurring billing start, and applicable Stripe references/version. Repeated activation events return the existing assignment.

Recommended future storage is `product_customer_sequence_counters` (`product_key` PK,
`next_sequence_number`, `lock_version`, timestamps), an immutable
`product_customer_sequence_allocations` row unique by product/sequence and qualifying
subscription/event idempotency key, and additive subscription columns or a one-to-one
`subscription_commercial_terms` row. The snapshot row FKs the subscription and assigned
cohort with `ON DELETE RESTRICT`, uses `DECIMAL(10,2)` amounts and `CHAR(3)` currency,
stores all actual dates/Stripe references above, and is never recalculated from the
current cohort table. Admin/customer views read the snapshot; setup charges remain
separate payment items and never enter MRR.

## 46. Alpha six-month-free billing contract

For positions 1–5: assign Alpha atomically, charge `$0` setup, retain a payment method, start a six-month free introductory period, and automatically begin `$79/month` recurring billing at expiration. Store `introductory_period_start`, `introductory_period_expires_at`, and `recurring_billing_starts_at` as actual dates when assigned. Reads do not repeatedly infer “six months from signup.”

Exact Stripe trial/schedule/Checkout mechanics belong to the future billing implementation. It must validate month-boundary/time-zone behavior, payment-method retention, webhook idempotency, expiration transition, failed payment, cancellation, and reconciliation before the first production customer.

Beta starts `$97/month` at activation with no setup fee. Founding charges `$100` one-time plus `$147/month`. Standard charges `$250` one-time plus `$197/month`.

## 47. Current billing implementation gap

Current implementation has one 247SP plan with historical old fees, `plans.setup_fee`, `plans.monthly_fee`, subscriptions linked to that plan, one recurring `STRIPE_247SP_PRICE_ID`, and one optional `STRIPE_247SP_SETUP_FEE_PRICE_ID`. It has none of the durable cohort/sequence/locked-term/free-period fields required above.

Therefore the approved prices are **product policy**, not current runtime behavior. A focused additive cohort-pricing migration, services, Stripe mapping, customer/admin presentation, tests, and staging validation are **FIRST-CUSTOMER CRITICAL**. They should not be silently forced into the website schema PR.

## 48. First-customer-critical blocker list

Before accepting the first production 247SP customer, unresolved implementation/validation includes:

- pricing-cohort configuration and atomic customer-sequence assignment;
- Alpha six-month free billing and automatic `$79/month` transition;
- cohort-aware Stripe price/setup handling and payment-method retention;
- locked subscription commercial terms and customer/admin visibility;
- generic website platform/CMS and approval workflow;
- public build/deployment/restore lifecycle;
- registered-site LeadHub ingestion with routing/abuse/replay controls;
- required production provider setup and end-to-end domain/DNS/SSL validation;
- professional email provisioning path;
- later-roadmap communications-core, phone, texting/chat, and AI prerequisites required by the sold product;
- legal/support/operations, cleanup/reconciliation, and first-customer QA.

Existing foundations must not be marked complete for these future capabilities.

## 49. Milestone 7 handoff requirements

Milestone 7 owns Sprint 8.7 closeout. It must reconcile Sprint status; record Milestone 6 architecture approval; produce executable Sprint 8.8 and Sprint 8.9 communications-core plans; confirm the initial Sprint 8.8 migration/split and staged PR sequence; define Sprint 8.8 staging validation; schedule the focused first-customer-critical cohort-pricing implementation; reconcile first-customer blockers; update handoff/readiness/closeout documents; and close Sprint 8.7 only after consistency checks pass.

Milestone 7 does not implement the CMS, cohort billing, DataForSEO, or communications runtime.

## 50. Milestone 6 acceptance criteria

Milestone 6 is acceptable when this audit and supporting documentation establish all of the following without runtime changes:

- Milestone 5 COMPLETE/PASS evidence and final deployed-main wording are accurate.
- Current website, route, class, domain, LeadHub, billing, and analytics foundations are inventoried honestly.
- Current behavior and future architecture are visibly distinct.
- Shared Business Profile and existing service/business records remain authoritative facts.
- Generic site identity, purpose, lifecycle, revisions, approvals, components, associations, assets, build/deployment/restore, EMD, conversion, retention, and legacy backfill are implementation-ready.
- Customer/admin authority, CSRF remediation, service authorization, transaction, audit, and public-ingestion security boundaries are explicit.
- Optional customer-owned Google Analytics and planned platform-owned DataForSEO are separate.
- Alpha/Beta/Founding/Standard cohort policy, sequence assignment, locked terms, and Alpha dates are explicit, while current billing remains correctly identified as incomplete.
- Cohort pricing is scheduled as first-customer critical and separate from the website schema when appropriate.
- Sprint 8.8 staged PRs and validation are executable; Milestone 7 responsibilities are explicit.
- No CMS, billing, DataForSEO, migration, provider, staging, production, configuration, or runtime change is included.
- Documentation consistency and Git whitespace/scope checks pass, and every changed file is documentation.
