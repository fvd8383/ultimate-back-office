# Shared Business Profile Service Layer

## Status

Implemented in Sprint 8.7 Milestone 4 by `private/classes/SharedBusinessProfile.php`.

This milestone adds an internal application service only. It does not add a customer or admin profile interface, a public endpoint, an MCP gateway, communications tables, provider integrations, scheduling, or website presentation management.

## Purpose

`SharedBusinessProfile` is the business-rule boundary for Shared Business Profile reads and writes. Future customer/admin screens, website generation, LeadHub routing, background jobs, private internal APIs, communications services, and internal MCP tools should call this service rather than reading or writing migration 021 tables directly.

The service manages shared business facts. Website hero variants, page composition, section order, typography, component variants, revisions, and publishing remain outside this service.

## Error Contract

Service failures use `SharedBusinessProfileException`. Consumers may inspect:

* `errorType()` for `unauthorized`, `business_not_found`, `profile_not_found`, `validation_failed`, `invalid_lifecycle_transition`, `child_record_not_found`, `cross_business_reference`, or `database_failure`.
* `fieldErrors()` for field-specific validation messages.

SQL and driver messages are not returned through the application-safe exception message. Diagnostic details are sent to the existing PHP error log.

## Authorization

Every public method requires both `business_id` and `acting_user_id` and performs an explicit business-scoped authorization check.

Customer access requires:

* An active `users` record.
* An active `business_users` membership for the target business.
* An active, non-suspended business.

Internal access uses the existing `AdminPortal::currentUserIsAdmin()` rule: an active user with an internal `Super Admin` or `Admin` role. Admin access does not use a bypass flag. Profile activation is admin-only.

Submitted business IDs, URL parameters, hidden form fields, and session business context are never sufficient authorization. Future routes must still perform authentication and CSRF checks appropriate to the route, but CSRF is not used as tenant authorization.

## Public Methods

```text
getProfileForBusiness
updateProfile

getHours
replaceHours
getHourExceptions
replaceHourExceptions

getFaqs
saveFaqs
getPricingGuidance
savePricingGuidance
getAppointmentRules
saveAppointmentRules
getTransferRules
saveTransferRules
getEscalationRules
saveEscalationRules
getNotificationPreferences
saveNotificationPreferences

transitionLifecycleStatus
calculateReadiness
```

Collection save methods are replace-all operations. Existing child IDs may be submitted to preserve identity, but every ID is checked against both the target business and profile before any delete or insert occurs. Omitted rows are removed; rows explicitly saved with `is_active = false` remain available as inactive configuration.

Mutation methods return the complete normalized profile. Collection read methods return the requested collection.

## Normalized Output

`getProfileForBusiness()` and mutation methods return:

```text
shared_business_facts
services
  selected_sub_services
  custom_services
service_area
hours
exceptions
faqs
pricing_guidance
appointment_rules
transfer_rules
escalation_rules
notification_preferences
readiness
lifecycle
```

Core identity and address data come from `businesses`. Services come from `business_sub_services`, `sub_services`, and `business_custom_services`. Service-area mode and radius come from `businesses.is_public_physical_location` and `247sp_website_configurations`. Migration 021 tables supply the profile and child configuration.

The output excludes website presentation configuration, provider credentials, billing data, LeadHub notes, admin observations, and the stored readiness snapshot.

## Profile Updates

`updateProfile()` rejects unknown fields and accepts only the documented migration 021 shared fields:

* Public display name and website URL.
* Timezone and default language.
* Shared descriptions, greeting, value proposition, tone, personality, and prohibited claims.
* Appointment-request, automatic-booking, notice, and duration settings.
* Emergency-service setting.

IDs, business ownership, lifecycle status, timestamps, readiness snapshots, completion timestamps, and activation timestamps cannot be set through this method. Lifecycle changes use `transitionLifecycleStatus()`.

HTML-like markup is rejected from approved shared wording. This preserves plain, customer-approved language without silently rewriting it.

## Hours And Exceptions

Migration 021 supports split shifts and overnight time ranges, so the service supports both. An overnight range has a closing time earlier than its opening time and is returned with `is_overnight = true`.

Validation enforces:

* Day values from `0` (Sunday) through `6` (Saturday).
* ISO exception dates and 24-hour times.
* Unique range order per day/date.
* Closed, 24-hour, or timed states without contradictory combinations.
* No equal opening/closing time; use the 24-hour state instead.
* No overlapping ranges with the same starting day or exception date.
* Exactly one row for a closed or 24-hour day/date.

Hours are interpreted in the profile timezone. Missing timezone does not prevent draft saving, but it makes readiness incomplete. Readiness requires an explicit hours record for every day of the week.

## FAQs And Pricing Guidance

FAQ questions and answers are required, length limited, plain text, active/inactive, channel scoped, and sortable. At least one active FAQ is required by the current conservative readiness policy because the production-readiness document lists FAQs as required.

Pricing guidance supports:

```text
starting_price
service_call_fee
estimate_policy
deposit_policy
financing
disclaimer
prohibited_statement
general_guidance
```

Amounts are optional for non-monetary guidance. Starting-price and service-call-fee records require at least one approved amount. Amounts are non-negative decimals with two-place precision, and monetary rows require a three-letter currency code. Pricing guidance is not a quoting engine and is currently optional for overall readiness, with a warning when absent.

## Appointment, Transfer, And Escalation Rules

A rule may be profile-wide, reference one selected `sub_service`, or reference one same-business `business_custom_service`. It may not reference both service types. Missing and cross-business references produce distinct errors.

Appointment rules are provider-neutral policy text. They do not create availability or scheduling. Appointment rules are conditionally required when appointment requests are enabled. Automatic booking may be configured for future use, but readiness warns that this service does not provide scheduling or calendar availability.

Transfer numbers are normalized to E.164-compatible storage. Ten-digit US/Canadian numbers receive the `+1` country code; other local-format numbers must include a country code. Primary and backup numbers, priority, maximum attempts, business-hours flags, and fallback behavior are validated. No Twilio fields or live routing are present.

Escalation types are `immediate_transfer`, `owner_alert`, `prohibited_ai_handling`, and `disclaimer_language`. Urgency, conditions, instructions/disclaimers, priority, and action flags are validated. An emergency-urgency rule is rejected unless emergency service is explicitly enabled.

## Notification Preferences

Supported notification types are:

```text
new_lead
missed_call
transfer_failed
urgent_lead
new_message
appointment_request
unresolved_lead_summary
```

Only one row per profile/type is accepted. Email and phone destinations are validated and phone destinations are normalized. Draft saving remains allowed when an enabled email or SMS destination is missing; readiness then reports the exact missing destination. This service does not send notifications or create delivery jobs.

## Lifecycle

Allowed deliberate transitions are:

```text
draft      -> incomplete | in_review
incomplete -> draft | in_review
in_review  -> draft | incomplete | ready
ready      -> in_review | incomplete | active
active     -> in_review | incomplete | ready
```

`ready` and `active` require live readiness to pass. `active` additionally requires the existing internal administrator role. Writes never auto-advance a draft/incomplete profile to ready or active. Any mutation that removes required information automatically demotes `ready` or `active` to `incomplete`. No communications agent, website, or provider is activated.

## Readiness Version 1

Readiness is calculated from current authoritative records, not from the stored snapshot. Required sections are:

* Business identity.
* Timezone.
* Services.
* Service area.
* Seven-day hours coverage.
* Greeting.
* At least one active FAQ.
* Transfer behavior.
* Escalation behavior.
* Notification preferences and enabled destinations.
* Appointment rules when appointment requests are enabled.

Pricing guidance is optional and reported as a warning when absent. Appointment rules are optional while appointment requests are disabled. The result contains `is_complete`, lifecycle status, completed and incomplete sections, section-specific missing fields, warnings, calculation timestamp, and readiness version.

`readiness_snapshot_json` is refreshed after mutations for diagnostics and future operational visibility, but it is never treated as authoritative. Reads and lifecycle gates always recalculate readiness from current data.

## Transactions And Audit

Every mutation:

1. Starts a transaction.
2. Locks the target profile row.
3. Applies the profile or collection change with prepared statements.
4. Recalculates readiness and applies required lifecycle demotion.
5. Refreshes the non-authoritative readiness snapshot.
6. Inserts an `activity_logs` audit summary.
7. Commits only after the normalized response can be built.

Any failure rolls back the entire mutation. Audit metadata stores the action, business-scoped target type/ID, changed field names or row counts, and notification type names where applicable. It does not store submitted wording, phone numbers, email destinations, secrets, or full payloads.

## Staging Validation

Runtime validation remains required on staging because the local Codex environment has no PHP CLI or MySQL runtime.

Authorization:

1. Load and update a profile as an active member of the same business.
2. Attempt both operations with another business ID and confirm `unauthorized`.
3. Repeat with inactive membership, inactive user, and suspended business.
4. Confirm internal Admin/Super Admin access and confirm a business-scoped Admin role does not grant it.
5. Submit a child ID from another profile and confirm `cross_business_reference` with no changes.

Profile and collections:

1. Load an existing migration-021 draft profile and confirm no duplicate profile row is created.
2. Save every allowlisted profile field; submit an unknown field and confirm field-specific rejection.
3. Save closed, 24-hour, normal, split-shift, and overnight hours.
4. Confirm contradictory, duplicate-order, equal-time, and overlapping hours roll back.
5. Save and replace exceptions, including a labeled holiday closure.
6. Add, edit, deactivate, reorder, and replace FAQs; verify required text and markup rejection.
7. Save monetary and non-monetary pricing guidance; reject invalid amounts/types.
8. Save profile-wide, selected sub-service, and same-business custom-service rules; reject both references, unselected services, and cross-business custom services.
9. Save transfer/escalation rules and validate phone, fallback, urgency, and emergency-enable rules.
10. Save notification preferences; confirm enabled channels with missing destinations save but fail readiness and duplicate types fail atomically.

Lifecycle and transactions:

1. Confirm an incomplete draft remains draft after saving.
2. Confirm `ready` and `active` fail while readiness is incomplete.
3. Complete every required section, transition through `in_review` to `ready`, then activate as an internal admin.
4. Remove a required value and confirm ready/active demotes to incomplete.
5. Force a child insert failure during a replacement and confirm the old collection, lifecycle, readiness snapshot, and audit state all roll back.

Regression:

1. Load 247SP onboarding, review, preview, and Website Manager.
2. Load the Admin Website Editor.
3. Submit an existing website form and confirm LeadHub contact/task/activity creation.
4. Load LeadHub contacts, leads, notes, and tasks.
5. Load domain, email, billing, and subscription pages.

## Explicit Exclusions

No database migration, UI route, public API, MCP tool, component CMS, website-generation change, communications table, Twilio/Retell integration, notification delivery, scheduling engine, billing change, domain change, email change, or production deployment is included.
