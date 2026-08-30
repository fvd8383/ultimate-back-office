# Sprint 8.8 M1 Current Schema Audit

## Status

Local implementation audit for Sprint 8.8 M1. The repository baseline is
`82c0317a95f6de10b9496a66e0c01274ad7edfcc`. This document records the dependencies
observed before migration `023_website_platform_foundation.sql` was designed. It does
not claim that migration 023 has been applied or that the generic runtime is
authoritative.

## Existing identity and authority

- `businesses.id` is the existing legal/core business identity. `business_users` and
  `business_modules` own membership and product access. Generic site identity must not
  replace any of them.
- `business_profiles` and its migration-021 child tables own reusable Shared Business
  Profile facts. `SharedBusinessProfile` reads service-area intent from
  `247sp_website_configurations` and selected/custom services from
  `business_sub_services`, `sub_services`, and `business_custom_services`.
- The generic import may retain business/profile/service identifiers and presentation
  snapshots. It must not create a second mutable business-facts or service catalog.

## Existing website aggregate

- `247sp_generated_websites` has one row per business and requires an existing
  `businesses`, `247sp_onboarding`, and `247sp_templates` row.
- The only seeded repository template is `starter_local_service`.
- `247sp_generated_pages` belongs to both a legacy website and business, is unique by
  website/slug, and stores `page_type`, title, slug, `content_json`, status, and order.
- `SiteGenerator` currently emits exactly `home`, `service`, `about`, and `contact`
  page types. The authenticated preview has explicit repository code for those four
  types.
- Regeneration updates the website row, deletes every generated page, and inserts new
  page rows. Legacy page IDs are therefore evidence for the imported baseline, not a
  durable page identity. Generic `site_pages` must use a deterministic logical key
  derived from page type and slug and keep legacy page IDs in a separate mapping.

## Presentation inputs

- `247sp_website_branding` is one row per business and stores logo, colors, hero, and
  about image paths.
- `247sp_website_service_images` is unique by business/service number.
- `247sp_website_content_overrides` is unique by business/page key/field key.
- `website_integrations` is one row per business. The current preview renders only a
  validated Google Analytics measurement ID; the other columns are stored references.
  M1 records integration source references but does not create an analytics authority.
- `247sp_service_pages` is presentation-oriented service-page input. Existing selected
  and custom service records remain the authoritative service catalog.
- Imported assets remain references to validated existing paths with checksum and
  rights/provenance state. M1 does not move files or call storage providers.

## Legacy lifecycle and approval evidence

- `247sp_generated_websites.status`, `published_at`, and `website_domains.publish_status`
  are legacy state only.
- `TwentyFourSevenSalesPartner` derives launch approval from the latest
  `247sp_website_launch_approved` or `247sp_website_changes_requested` activity.
  Migration 023 provides revision-specific approval storage, but the importer does not
  elevate legacy activity into an authoritative generic approval.
- `DomainManager::markLive()` updates domain and `website_domains` state without a
  versioned build, deployment, or health check. M1 therefore imports every site and
  baseline revision conservatively as `draft` and never sets generic `active` state or
  a published revision pointer.

## M1 dependency decisions

- One deterministic generic site key is derived from each eligible legacy website ID.
- One active customer association links that site to the legacy website business.
  Generic sites remain independently creatable without any business association.
- Stable logical page keys are derived from normalized legacy page type and slug.
- One baseline imported revision stores bounded source references, the legacy
  presentation snapshot, deterministic page/section/theme hashes, and explicit legacy
  website/page mappings.
- Repository component metadata is limited to `legacy_247sp_page` and its existing
  preview variants: `home`, `service`, `about`, and `contact`. These rows select no
  executable path and contain no PHP, JavaScript, template, or include content.
- M1 creates no build, deployment, domain-association, routing, conversion, publisher,
  or public-ingestion structures.

## Import eligibility and quarantine

An import unit is eligible only when the legacy website, business, onboarding, and
`starter_local_service` template agree; it has at least one page; every page belongs
to the same website/business; slugs are non-empty and unique after normalization;
page JSON is valid; and each page type has a seeded legacy variant. Referenced local
assets must resolve beneath the application public root and be readable.

Missing dependencies, malformed JSON, ownership mismatches, unsupported page types,
asset failures, deterministic-key collisions, and mapping collisions are quarantined
with a bounded error code/summary. Each website is locked and imported in its own
transaction. Completed mappings are reconciled on rerun; no legacy row is updated or
deleted.
