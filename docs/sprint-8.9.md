# Sprint 8.9 — Communications Core Foundation

## Status And Objective

**Planned.** Sprint 8.9 establishes provider-neutral communications records and
services, LeadHub integration, professional-email provisioning, and the shared Twilio
foundation required by later channel work. It does not implement the Sprint 8.10
telephony/AI receptionist runtime or claim that the unified inbox and all sold channels
are launch-ready.

Professional email through the planned Vendasta integration is first-customer
critical. Sprint 8.9 therefore begins with that provisioning path before the broader
communications schema.

## Boundaries

- LeadHub remains the system of record for opportunity/customer context.
- `CommunicationsManager` is the provider-neutral service boundary; routes and LeadHub
  screens do not call providers directly.
- Provider secrets live only in approved environment/secret configuration, never in
  database records, browser JavaScript, customer output, or logs.
- Provider operations use durable idempotent intents/jobs, webhook verification,
  replay protection, safe errors, retry, and reconciliation outside long transactions.
- Every record and service operation is tenant/business authorized.
- Twilio foundation belongs in Sprint 8.9, but phone number/porting/routing and voice
  runtime belong to Sprint 8.10.
- Retell voice-agent runtime and credentials are not required until Sprint 8.10.
- SMS/MMS, website chat, complete unified inbox, and overage charging remain later work
  unless a separately approved plan moves a bounded dependency.

## Entry Gates

- Sprint 8.8 closes with its staging validation PASS;
- professional-email product/support/domain requirements and Vendasta test access are
  approved;
- communications source-of-truth, retention, consent, webhook, and provider-account
  security decisions are reviewed against current LeadHub and Shared Business Profile;
- Twilio test access and webhook endpoint strategy are approved for foundation tests;
- no production provider credentials are used for implementation validation.

## Migration Strategy

Use additive migrations with the next numbers available after Sprint 8.8. Do not
pre-create empty files. Separate professional-email provisioning state from generic
communications records when that improves rollback and review. Schema rollout must be
dependency-safe, tenant-scoped, forward-repairable, and reconciled on staging.

## M1 — Provider Account + Professional Email Foundation

### Deliverables

- provider-neutral `ProfessionalEmailProvisioner` (or equivalent) boundary;
- Vendasta adapter for planned Google Workspace/professional Gmail provisioning;
- provider account references that contain no credentials;
- mailbox and domain association, requested identity, lifecycle, and customer/business
  ownership;
- durable provisioning intent/job, idempotency key, status history, provider reference,
  retry/reconciliation, safe error, actor, correlation, and timestamps;
- admin status/action visibility and customer-safe progress/instruction visibility;
- cancellation/suspension/deprovision-review behavior that preserves audit and avoids
  destructive automatic account action without approved policy.

### Security and transaction rules

Staging/test and production Vendasta credentials are distinct, least-privilege secrets.
No credential or raw provider payload enters a table, browser, generated site, or
activity log. Local transactions create/update the intent; provider work occurs after
commit and reconciles through a bounded worker. Retry uses the same idempotency key and
must not create a duplicate mailbox/license.

### Tests and exit gate

Test tenant/domain/mailbox ownership, invalid/duplicate requests, idempotent create,
provider timeout/failure/retry, lost-response reconciliation, webhook/callback
verification if used, cancellation/suspension policy states, safe errors, admin/customer
views, no credential leakage, and cleanup. M1 is first-customer critical and remains
open until Vendasta test provisioning is proven in M8.

## M2 — Communications Schema + `CommunicationsManager`

### Deliverables

Plan and implement provider-neutral structures for:

- provider accounts/references and environment-safe provider identity;
- business communication channels and channel lifecycle;
- conversations and business/contact ownership;
- participants and normalized participant roles/addresses;
- messages/events with direction, type, state, provider/event times, and safe content
  references;
- external provider references mapped uniquely by provider account/channel/type;
- correlation IDs, webhook receipts/idempotency, processing state, and retry metadata.

`CommunicationsManager` owns authorization, channel/conversation identity, normalized
event ingestion, state transitions, participant/message persistence, correlation, and
activity adapters. It owns short atomic transactions and emits success activity only
after commit.

### Tests and exit gate

Validate FKs/indexes/uniqueness/delete/retention, tenant isolation, cross-business ID
rejection, channel state, idempotent event ingest, out-of-order events, rollback, safe
failures, concurrent conversation creation, provider-reference collisions, and schema
reconciliation. No provider adapter bypasses the manager.

## M3 — Provider Adapters / Twilio Foundation

### Deliverables

- shared `CommunicationsProviderInterface` capabilities or narrower reviewed
  interfaces implemented by a Twilio foundation adapter;
- provider-account abstraction and test/live configuration separation;
- authenticated Twilio webhook boundary, signature verification, timestamp/replay
  policy, payload limits, idempotency, normalized provider event envelope, durable
  receipt state, correlation, safe retry/reconciliation, and secret-safe logging;
- test fixtures/contract tests that later voice/SMS adapters can reuse.

This milestone does not provision production phone numbers, port numbers, implement
call routing, send customer SMS, or create Retell agents. It proves only the shared
Twilio account/configuration and webhook normalization boundary required for later
work.

### Tests and exit gate

Test valid/invalid signatures, replay/duplicate events, malformed/oversized payloads,
unknown account/channel, environment mismatch, event ordering, transient handler
failure, reconciliation, tenant isolation, normalized fields, and no customer-visible
secrets. Only Twilio test credentials are used.

## M4 — Conversation / Participant / Event Services

### Deliverables

- stable conversation identity independent of provider thread/call IDs;
- channel identity and lifecycle;
- customer/business/contact and participant associations;
- normalized inbound/outbound event creation and state transitions;
- explicit ownership, timestamps, provider occurrence/receipt time, correlation, and
  retention controls;
- services for lookup, append, participant resolution, unread/needs-response signals,
  and bounded summaries.

### Tests and exit gate

Cover channel-specific normalization without provider leakage, participant merging and
ambiguity, contact/business scope, concurrent first events, out-of-order status events,
duplicate provider IDs, retention visibility, authorization, rollback, and safe output.
Provider-only records cannot become isolated from local business/conversation identity.

## M5 — LeadHub Contact Matching + Timeline Adapter

### Deliverables

- deterministic contact candidate matching using approved normalized identifiers;
- explicit handling of no match, one match, and ambiguous match;
- conversation/contact relationship with attribution to website, email, phone, SMS, or
  other future channel;
- bounded LeadHub timeline summaries linked to the underlying conversation/event;
- activity and task/follow-up triggers through existing authorized LeadHub services;
- repair/reconciliation for provider events awaiting contact resolution.

### Tests and exit gate

Test tenant-scoped phone/email matching, ambiguous matches, contact creation authority,
channel attribution, duplicate activity prevention, activity after commit only,
task-trigger idempotency, private-content minimization, cross-business rejection, and
reconciliation. LeadHub remains the context system of record; provider data is not a
separate shadow CRM.

## M6 — Owner Takeover / AI Pause-Resume State

### Deliverables

- provider-neutral conversation control state for owner takeover, AI pause, and AI
  resume;
- actor/actor type, reason, requested/effective time, previous/new state, correlation,
  and audit timeline;
- authorization rules for customer owner, approved internal roles, and future system
  actors;
- adapter-facing state query/transition contract preserved for Sprint 8.10 and later
  website chat/SMS work.

No Retell agent or autonomous response runtime is implemented here. State transitions
must be idempotent, auditable, reversible where policy allows, and safe during provider
failure.

### Tests and exit gate

Test allowed/denied actors, cross-tenant IDs, repeated/out-of-order transitions,
concurrency, reason requirements, rollback, timeline visibility, safe activity, and the
future adapter contract.

## M7 — Usage Event Foundation

### Deliverables

- normalized immutable usage events for future voice/AI minutes, owner outbound
  minutes, SMS segments, and AI chat responses;
- product/business/subscription, channel/conversation, provider reference, quantity,
  unit, occurred/received time, idempotency, source, correlation, and adjustment/audit;
- aggregation/query boundary that does not mutate raw usage events;
- retention and reconciliation rules suitable for later billing review.

This milestone does not charge overages or decide rates. Any billing export remains
disabled until pricing, dispute, adjustment, rounding, allowance, and invoice policies
are separately approved and validated.

### Tests and exit gate

Test units/precision, duplicate provider events, adjustments without destructive edit,
tenant/subscription association, late events, period boundaries, aggregation,
reconciliation, authorization, and no automatic charge.

## M8 — Staging Validation + Closeout

Create an implementation runbook at the appropriate point. Required phases are:

1. repository/database/environment/provider/log baselines;
2. migration syntax/state, schema reconciliation, PHP lint, standalone/static tests;
3. tenant, authorization, CSRF, provider-secret, transaction/rollback, and activity
   controls;
4. Vendasta test professional-email request, idempotent provisioning, status,
   failure/retry/reconciliation, domain/mailbox association, admin/customer visibility,
   suspension/cancellation behavior, and cleanup;
5. CommunicationsManager conversation/channel/participant/message/event flows,
   concurrency, duplicate/out-of-order events, and correlation;
6. Twilio test account and authenticated webhook contract, replay/idempotency,
   normalization, failure/retry, and environment separation;
7. LeadHub matching, attribution, timeline summaries, task/activity triggers, ambiguous
   contact handling, and reconciliation;
8. takeover/pause/resume states and audit, without Retell runtime;
9. usage-event capture/aggregation/adjustment with no overage charge;
10. admin/customer browser visibility, responsive/accessibility smoke, and clean
    browser console;
11. bounded Apache/PHP/worker/webhook logs with no warnings/fatals/PDO errors/secrets or
    unnecessary communications payloads;
12. synthetic provider/mailbox/channel/conversation/participant/event/contact/activity/
    task/usage cleanup, orphan/cross-tenant checks, and repository/database/provider
    reconciliation;
13. evidence-backed PASS/FAIL report, checksum, handoff, readiness, and blocker update.

Vendasta provisioning or Twilio foundation failures block closeout. No production
provider calls occur in this validation.

## Provider Timing

- Sprint 8.9 needs approved Vendasta staging/test credentials for professional-email
  provisioning and Twilio test credentials for the provider/webhook foundation.
- Retell credentials are first needed in Sprint 8.10 for approved voice-agent work.
- Twilio telephony configuration expands in Sprint 8.10.
- Google Calendar is not configured until real scheduling/calendar synchronization is
  explicitly implemented.

## Sprint 8.10 Boundary — Telephony + AI Receptionist

Sprint 8.10 is planned to own Twilio subaccounts/phone numbers, local number
provisioning, number porting, inbound routing, outbound calling identity, Retell voice
agents, transfers, recordings, transcripts, summaries, dispositions, LeadHub call
history, usage metering, and a staged pilot. Sprint 8.9 preserves interfaces and data
contracts for that work without implementing it.

## Later Boundary

SMS/MMS, A2P, website chat, complete unified inbox, AI chat runtime, and usage/overage
charging are scheduled later according to the approved roadmap and must be complete as
required for the sold product before commercial launch. Documenting foundations here
does not mark those channels ready.

## Sprint Exit Criteria

Sprint 8.9 closes only after M1-M8 focused PRs are merged, all migrations are applied
and reconciled under approval, Vendasta professional-email and Twilio foundation tests
pass on staging, provider-neutral/LeadHub/usage behavior passes the full runbook,
cleanup/reconciliation passes, no future telephony/AI/SMS/chat capability is falsely
marked implemented, and first-customer blockers are updated from evidence.
