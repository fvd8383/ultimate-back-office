# Sprint 8.7 Milestone 5 Staging Validation

## Status

COMPLETE / PASS. The validated and deployed commit is
`ea81194e7d853782f927fdf58ed65eecd6473a7f`. This is the final deployed `main` state
after the Milestone 5 implementation, required fixes, and successful validation; it is
not a claim that the commit came from Milestone 5 alone.

Final successful validation artifact SHA-256:

```text
687a1444664f9d7167dfb316510f09094e922c2b83166874849db44fb10382a6
```

The preconditions and procedures below are retained as historical validation evidence
and do not authorize a new deployment, database access, migration, destructive test, or
production access.

## Deployment Preconditions

1. Milestone 5 PR approved and merged to `main`.
2. Explicit staging deployment approval received.
3. Deployed repository is clean and matches the approved merge commit.
4. Repository-wide PHP lint passes before Apache reload.
5. No migration is run; migration 021 remains unchanged.
6. Any lifecycle transition, deliberate rollback failure, or cleanup receives the
   separate approval required by `docs/codex-rules.md`.

## Customer Validation

Use an active 247SP customer with an active business and membership.

1. Open `/247sp/business-profile.php?business_id={BUSINESS_ID}`.
2. Confirm identity, services, and service area are read-only and link to their
   existing authoritative editors.
3. Save shared wording, partial weekly hours, exceptions, FAQs, pricing guidance,
   appointment settings/rules, transfer rules, escalation rules, and notifications.
4. Confirm validation errors preserve submitted values and open the affected section.
5. Confirm incomplete required sections open by default, completed sections collapse,
   optional sections collapse, and core forms work with JavaScript disabled.
6. Confirm missing/malformed collection payloads do not remove existing rows.
7. Confirm explicit row removal works and all-inactive FAQs remain saveable while
   readiness requires an active FAQ.
8. Confirm duplicate FAQ sort orders save successfully.
9. Confirm pricing guidance remains optional and produces its readiness warning.
10. Confirm appointment rules become required only when appointment requests are enabled.
11. Submit a draft/incomplete profile for review and confirm customers cannot submit
    ready or active lifecycle targets.

## Security And Tenant Isolation

1. Submit each mutation with a missing, incorrect, expired, and rotated CSRF token.
2. Confirm the generic rejection contains no token, SQL, driver, or stack detail and
   creates no mutation or success activity record.
3. Attempt GET and POST with another business ID.
4. Repeat with inactive user, inactive membership, and suspended business.
5. Confirm regular customers and business-scoped Admin roles cannot access internal
   admin visibility or lifecycle controls.
6. Confirm stored wording is escaped when rendered.

## Admin Validation

1. Open `/admin/business.php?business_id={BUSINESS_ID}` as internal Admin/Super Admin.
2. Confirm lifecycle, readiness, missing requirements, warnings, facts, and every
   section count are visible without impersonation.
3. Confirm only transitions allowed from the current lifecycle are offered.
4. Confirm `ready` and `active` fail while readiness is incomplete.
5. With separate approval, transition a complete `in_review` profile to `ready` and
   activate it as an internal admin.
6. Confirm a non-admin cannot activate.

## Milestone 4 Regression Contracts

Reconfirm partial weekly-hour writes, duplicate FAQ sort orders, inactive-only FAQs,
optional pricing warnings, conditional appointment rules, automatic demotion after a
required fact is broken, first-ready and first-active timestamp preservation, rollback
atomicity, and no false success activity record after failure.

## Dashboard And Application Smoke

1. Confirm the 247SP dashboard Business Profile checklist item matches
   `SharedBusinessProfile::calculateReadiness()` before and after each relevant save.
2. Load onboarding, review, preview, Website Manager, Admin Website Editor, LeadHub,
   domains, email, billing, and subscriptions.
3. Submit an existing website lead and confirm LeadHub behavior remains intact.
4. Review the bounded Apache/PHP error-log delta and browser console.

## Evidence And Closeout

Record deployed commit, personas/business IDs, before/after lifecycle and readiness,
activity-log IDs, every command/test result, log delta, cleanup/reconciliation result,
and final PASS, FAIL, or BLOCKED status. Milestone 5 may be called complete only after
the approved validation closes as PASS.
