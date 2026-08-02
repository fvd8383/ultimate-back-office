# Internal MCP And Integration Access Strategy

## Status

Planned architecture. The repository does not currently contain an MCP gateway.

---

# Approved Direction

MCP is internal and administrative only. It is not a customer-facing Ultimate Back Office or 24/7 Sales Partner feature.

The approved model is:

```text
Approved internal AI client
  -> Private MCP gateway
  -> Authentication, policy, scopes, approvals, and audit
  -> Internal UBO service layer
  -> LeadHub | Website/CMS | Communications | Financial tools
```

The internal UBO service layer owns business rules. MCP translates approved agent actions into authenticated service calls; it does not replace authorization, validation, transactions, tenant isolation, or audit behavior.

AI providers and clients must remain replaceable. Development access and production operations access must be separate. High-risk actions require explicit approval. Every action must be authenticated, scoped, tenant-checked, and audited.

---

# Service-Layer Boundary

MCP tools must call stable internal UBO services. They must not write directly to tables, invoke arbitrary PHP, run shell commands, or call providers without the owning service.

The same internal services should support approved UI routes, background jobs, narrow internal APIs, and future MCP tools. This keeps business rules consistent across clients.

The planned `CommunicationsManager` and provider interfaces remain below this boundary. A future MCP tool may request an approved communications action, but only the internal communications service may select or call Twilio, Retell, or another provider.

---

# Tool Design

Prohibited generic tools include:

* `run_sql`
* `query_database`
* `execute_php`
* `run_server_command`
* `edit_any_record`
* `call_any_api`
* `send_arbitrary_request`

Preferred narrow tools include:

* `get_site_status`
* `create_site_revision`
* `publish_site_revision`
* `inspect_form_delivery`
* `search_leads`
* `inspect_lead_routing`
* `retry_failed_job`

Each tool should have a bounded purpose, explicit input schema, business or tenant scope, authorization policy, approval class, idempotency behavior where relevant, and auditable result.

Read operations should return only the minimum data needed for the approved task. Write operations should validate expected current state and use idempotency or optimistic checks when retries could cause duplicate work.

---

# Risk And Approval

Low-risk reads may run without per-action approval after authentication and scope checks.

High-risk operations require explicit approval, including:

* Publishing or restoring a site revision
* Changing lead routing
* Converting a demo or customer site
* Reassigning or releasing a domain
* Removing customer access
* Sending external communications
* Retrying a financial or provider-side operation that may duplicate an effect
* Exporting sensitive or high-volume customer data

Approval records should capture the requested action, target business or resource, requesting actor or agent, approver, approved parameters, expiration, execution result, and correlation ID.

---

# Security And Operations

Required controls:

* Private network or otherwise access-restricted gateway
* Strong client and user authentication
* Least-privilege scopes
* Business-level tenant checks
* Separate development and production identities
* Short-lived credentials where supported
* Secret storage outside source control
* Input validation and output filtering
* Rate and concurrency limits
* Idempotency for repeatable actions
* Immutable or append-oriented audit events
* Correlation IDs across MCP, service, job, and provider activity
* Explicit disable/revocation controls

Production operations access must not be inherited automatically from development access. Provider credentials remain owned by internal services and are not returned to MCP clients.

---

# Customer Integration Policy

Customer integrations are narrow, source-specific ingestion channels. Examples include website submissions, Twilio calls and messages, Retell summaries, Stripe events, PayPal imports, bank imports, QuickBooks imports, CSV uploads, and receipt or document uploads.

Inbound means data flows into UBO, including cases where UBO calls an external provider API to retrieve data the customer authorized.

UBO does not plan to offer:

* Customer MCP access
* General customer API keys
* Broad public read access
* Direct database access
* Generic GraphQL access
* Continuous full-database replication
* Generic outbound webhooks for every record
* Unrestricted automated bulk extraction

This restriction does not remove normal customer access. Customers retain appropriate access to reports, documents, invoices, estimates, receipts, statements, customer records, communications, uploaded files, and legally or operationally required records.

Website form ingestion is a representative narrow integration: a registered site may submit a bounded lead payload to a write-oriented endpoint, but receives no general LeadHub read capability.

---

# Planned Implementation Sequence

1. Stabilize internal service methods and authorization boundaries.
2. Define narrow internal API contracts and audit events.
3. Establish production-separated client identity, scopes, and approvals.
4. Implement a private gateway with read-only tools first.
5. Add bounded write tools with idempotency and explicit approval.
6. Validate tenant isolation, audit completeness, revocation, and failure handling before production use.

No MCP implementation is part of Sprint 8.7 Milestone 3.

---

# Open Architecture Questions

* Which identity provider and machine-authentication method will protect the gateway?
* Where will approval requests and immutable audit events be stored?
* Which service methods are stable enough to expose first?
* What data-classification rules govern tool output and redaction?
* What production break-glass process is permitted, and who can approve it?
* What retention and export rules apply to agent prompts, tool inputs, and tool results?
