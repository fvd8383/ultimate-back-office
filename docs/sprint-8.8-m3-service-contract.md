# Sprint 8.8 M3 — Component Registry And Composition Service Contract

## Status And Boundary

M3 is **IMPLEMENTED LOCALLY / REVIEW REQUIRED / STAGING MIGRATION AND VALIDATION
PENDING** on branch `codex/sprint-8.8-m3-component-composition`. M1 and M2 remain
**COMPLETE / STAGING PASS**. Sprint 8.8 remains **IN PROGRESS**; production is
**UNAUTHORIZED / NOT DEPLOYED**. This milestone adds service code and migration 024
only. It adds no route, browser UI, public runtime cutover, upload pipeline, build,
deployment, domain, routing, publication, conversion, or provider behavior. M4 has not
started.

## Registry Ownership And Versioning

Repository code owns the executable component manifest, schemas, placement rules,
asset requirements, renderer identifiers, and fixed renderer dispatch. Database rows
store durable, reviewable metadata only. A database value cannot select a PHP file,
class, method, include path, template, JavaScript file, or CSS file. An unknown exact
repository identity fails closed.

The selected repository variant is part of that executable contract and is passed to
fixed renderer code. Repository-owned classes give `hero/default` and
`hero/split_media`, `cta/banner` and `cta/inline`, and `site_header/standard` and
`site_header/centered` distinct deterministic output semantics. Database metadata
cannot supply a callback, class, template, or variant implementation.

A component definition row represents one immutable executable identity:
`component_key + implementation_version`. Migration
`024_component_registry_versioning.sql` replaces migration 023's unique component-key
index with a unique key/version index without changing existing rows or IDs. A future
incompatible implementation is inserted as another definition row; historical rows
are never updated to a newer version. Variants remain unique within their immutable
definition version. Migration 023 remains byte-for-byte unchanged.

`ComponentRegistry::verifyDatabase()` provides a read-only reconciliation report. It
detects missing definitions and variants, unknown active definitions and variants,
definition/variant schema drift, and authoring-relevant status drift. Runtime requests
do not silently synchronize or rewrite metadata.

New authoring requires an exact repository and DB identity that is active,
authorable, and in the requested section/layout scope. Historical rendering requires
the exact repository implementation to remain known and renderable but does not
require it to remain authorable. `legacy_247sp_page@legacy-preview-v1` is renderable
snapshot compatibility only and can never be selected by new M3 composition.

## Initial Catalog

The authored implementation version is `1.0.0`, configuration schema version 1.

- Section components: `hero` (`default`, `split_media`), `statistics` (`default`),
  `service_grid` (`cards`), `service_detail` (`default`), `trust_cards` (`default`),
  `about_content` (`default`), `contact_content` (`default`), `cta` (`banner`,
  `inline`), `lead_form` (`default`), `pricing_list` (`link`), `faq` (`accordion`),
  and `text_block` (`default`).
- Layout components: `site_header` (`standard`, `centered`), `site_footer`
  (`default`), and `mobile_cta` (`default`).
- Compatibility component: `legacy_247sp_page@legacy-preview-v1` with the existing
  `home`, `service`, `about`, and `contact` variants.

Layout components are selected only through the theme and cannot become page-section
rows. Section components cannot become layout selections.

## Configuration Schema Dialect

Schemas live only in repository code. The bounded dialect supports object, array,
string, integer, number, boolean, nullability, required properties, unknown-property
rejection, enums, string/numeric/item bounds, nested objects, array item schemas,
safe tokens, relative paths, and asset usage keys. Associative configuration is
normalized deterministically.

Plain text rejects PHP tags, script tags, `javascript:` payloads, and inline event
handler markup. There are no general `html`, `markup`, `script`, `javascript`, `css`,
`style`, or `attributes` fields. CTA configuration stores only the semantic actions
`call`, `contact`, or `email`. Lead-form configuration stores only labels, the
allowlisted fields `name`, `email`, `phone`, `service`, and `message`, and a required
subset. It cannot store business/site identity, routing, endpoints, webhooks, or a
form action.

Authored page types are `home`, `service`, `about`, `contact`, `landing`, `standard`,
and `legal`. Page keys and asset usage keys are bounded safe tokens; slugs are
lowercase URL-safe values without traversal. Page key, slug, and page sort order are
unique per revision. Section key and section sort order are unique per page.

SEO accepts only bounded title/description, the fixed robots policy, and `self` or
`none` canonical policy. Presentation accepts only `narrow|standard|wide` layout width
and a navigation visibility boolean. Neither accepts hostnames, HTML, JSON-LD,
scripts, CSS, file paths, or templates.

## Placement And Cardinality

Each authored page has at least one section. `hero`, `statistics`, `service_grid`,
`service_detail`, `trust_cards`, `about_content`, `contact_content`, `lead_form`,
`pricing_list`, and `faq` are limited to one instance per page; `cta` is limited to
three and `text_block` to six. The manifest also enforces page-type placement,
including service detail only on service pages, lead forms on home/contact/landing,
service grids on home/landing/standard, about content on about/home/standard, and
contact content on contact/landing/standard.

A 247SP composition requires exactly one home page. An EMD composition requires
exactly one home-or-landing entry page. An internal demo requires at least one page.

## Theme And Layout Model

The authored theme is `local_service@1`. It validates two `#RRGGBB` colors, fixed
system typography families and scale, fixed spacing/corner/button tokens, and exact
repository-owned selections for header, footer, and mobile CTA. Layout configuration
is validated by the selected layout component schema. Arbitrary font/stylesheet URLs,
CSS, JavaScript, and template paths are prohibited.

`legacy_247sp_starter@1` remains renderable compatibility for restored legacy
snapshots and is not selectable for new authoring.

## Existing Asset References And Rights

M3 references existing `site_assets`; it does not upload, finalize, modify, duplicate,
or delete them. Migration 023's foreign key makes M3 asset use same-site only. M3 does
not invent global asset sharing.

Every authored reference must resolve to the revision's site, be `ready`, have rights
classified as `platform_owned`, `customer_owned`,
`customer_licensed_for_site`, or `third_party_licensed`, and be unexpired. `unknown`
and `prohibited` are rejected. Customer-owned or customer-licensed assets on 247SP
must match the active customer business association. Each usage key is unique per
revision. Section references must target their page and section; theme references
have null page/section targets. Repository requirements enforce image usage where
required and an existing document with `application/pdf` for pricing lists.

The sole historical exception is validation of an exact restored M1 snapshot. An
already-copied same-site, ready asset classified `unknown` may preserve that snapshot
only when its source is `legacy_247sp`, its metadata has both `legacy_reference` and
`review_required`, its historical revision reference remains present, and its stored
checksum/MIME/size evidence is intact. This does not approve the asset or permit its
reuse in authored M3, non-legacy restores, conversions, or new selection.
`prohibited` remains rejected everywhere.

## Atomic Full-Composition Replacement

`SiteCompositionManager::replaceDraftComposition()` is Internal Admin/Super Admin
only and requires the current 64-hex `expected_snapshot_hash`. It owns one short local
transaction, resolves revision ownership, then uses
`SiteRevisionManager::lockMutableRevisionForComposition()` so site then revision are
locked inside the same transaction. Only `draft` and `validation_failed` may be
written. A hash mismatch returns `stale_write`.

The service validates the complete DTO before deleting revision-owned rows. It locks
and reuses stable `site_pages` by page key, creates missing stable pages, and rejects
retired pages. It removes and replaces only the target revision's asset references,
sections, revision pages, and theme. Omitted stable pages and all `site_assets` remain.
It inserts the complete normalized graph, calculates hashes from stored rows, updates
the revision hash through the narrow caller-transaction-owned M2 helper, and records
`site_revision_composition_replaced` in the same transaction. Any failure rolls back
the complete replacement and success event.

The event contains only counts, previous/new hashes, and component identity counts;
it never contains customer copy, configuration, facts snapshots, credentials, or
rights-private metadata. There is no filesystem, HTTP, provider, email, domain,
LeadHub, build, or deployment work inside the transaction.

## Canonical Content And Revision Hashing

`CanonicalJson` recursively sorts associative keys, preserves list order, encodes with
`JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`, and produces
lowercase SHA-256.

Section hashes cover component key/version, variant, schema version, and normalized
configuration. Page hashes cover stable page metadata and ordered section
identities/hashes. Theme hashes cover theme identity and normalized design/layout
configuration. Auto-increment IDs are excluded.

`SiteRevisionSnapshotHasher` builds the full representation from actual stored rows:
snapshot schema version, facts/references, nullable generation brief, ordered stable
pages and sections with exact component/variant/schema identities and content hashes,
theme identity/configuration/hash, and ordered asset type/storage/checksum/MIME/size,
usage, page/section, and source reference. This becomes the revision's optimistic
concurrency token.

The legacy importer now uses the shared canonical utility and stored-revision hasher
through explicit `legacy_m1` compatibility mode. That mode retains M1's historical
nullable-string rule: trim non-null values and convert a normalized empty string to
null. Generic authored M3 hashing remains raw and exact. M1 representation, ordering,
component identity, asset evidence, and baseline hashes are unchanged; focused empty,
whitespace, null, and populated-value compatibility cases and the mandatory M1
regressions pass.

## M2 Review Gate Integration

`SiteRevisionManager::markReadyForReview()` invokes the M3 stored-composition validator
inside its existing site/revision transaction before lifecycle transition. The gate
re-resolves exact repository/DB component identities, validates authorability for
normal drafts and historical renderability for restored snapshots, schemas,
placement/cardinality, page/theme metadata, asset ownership/lifecycle/rights/targets,
and every stored section/page/theme/revision hash. Drift or mismatch blocks review.

A restored revision may retain an exact known renderable historical version even when
that version is no longer authorable. It is never silently upgraded. Restored M1
legacy snapshots retain their M1-compatible section/page/theme hashing rules.
Each M1 page must contain exactly one `legacy-page-snapshot` section at sort order 10,
with exact legacy component/version identity and a variant matching its page type;
mixed, duplicate, or malformed compatibility structures fail closed.

## Rendering And Read Boundaries

`compositionForActor()` is the authorized editor read. A fresh mutable draft is
returned deterministically as `composition_state=empty`, empty pages/assets, a null
theme, and its current snapshot hash. A composed editor model includes pages,
sections, theme, safe asset usage metadata, and authorized `asset_id` values for future
editing. Database IDs remain excluded from canonical hashes. Credentials, provider
secrets, absolute filesystem paths, and rights-private metadata are never exposed.

`validatedCompositionForActor()` is the separate preview/render read. It authorizes
the actor, loads site purpose, validates the complete stored revision, verifies all
content and canonical snapshot hashes, and returns a normalized safe render model.
An empty editor draft is readable but is not render-ready.

The pure renderer enforces its already authorized and validated read-model marker. Repository
identity resolves to a fixed renderer identifier and a fixed PHP `match`; DB metadata
cannot choose code. Text and attributes use `htmlspecialchars` with
`ENT_QUOTES | ENT_SUBSTITUTE` and UTF-8. No configuration renders as raw markup.
CTA hrefs come only from semantic action plus validated render context. Asset URLs
come only from validated render context. Missing context fails safely.

Legacy `home`, `service`, `about`, and `contact` snapshots render meaningful known
structured fields through fixed repository code. Text is escaped, unknown fields and
stored paths are ignored, and any asset URL must come from safe render context.

Lead form rendering is presentation-only. Without a future registered-site action it
uses a non-form container with disabled inputs and a disabled non-submit button. Only
an allowlisted relative action may produce a POST form. M3 creates no public submit
route and no LeadHub routing.

Focused transaction-aware tests inject a PDO-compatible local database and invoke the
actual full-composition replacement and review-gate services. They cover stable-page
reuse, stale writers, rollback after deletion, event atomicity, stored hashes,
read-model authorization/validation, legacy compatibility, tamper rejection,
immutability, and the future publication gate.

## Explicit Exclusions

M3 includes no browser routes, admin composer, customer Website Manager UI, preview
route cutover, POST/CSRF handler, asset upload/storage finalization, builds,
deployment, publication, domains, routing, public LeadHub ingestion, conversion,
provider integration, or M4+ work. `SiteGenerator` and `AdminPortal` legacy reads
remain authoritative. No staging or production access, migration execution, merge,
or deployment is part of this local implementation.
