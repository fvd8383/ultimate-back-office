# Sprint 7 – Domain Automation

## Objective

Automate domain acquisition, assignment, and management for 24/7 Sales Partner customers.

---

# Business Goal

A customer should be able to request a domain during onboarding and have that request managed through the platform.

Sprint 7 focuses on domain workflow and management.

Actual DNS publishing may remain partially manual during initial implementation if necessary.

---

# Customer Workflow

Customer:

1. Creates business
2. Completes onboarding
3. Chooses domain
4. Generates website
5. Domain request is created
6. Admin reviews request
7. Domain is assigned
8. Website becomes publish-ready

---

# Database

Create migration:

```text
009_domain_automation.sql
```

---

## Domain Requests

Store:

* business_id
* requested_domain
* domain_status
* registrar
* annual_cost
* purchase_date
* expiration_date
* created_at

Statuses:

```text
requested
pending_purchase
active
transferred
expired
cancelled
```

---

## Domain Assignments

Store:

* business_id
* domain_name
* status
* assigned_at

---

# Customer Portal

Create:

```text
Accounts
→ Domains
```

Page:

```text
public/accounts/domains.php
```

Display:

* Requested Domain
* Status
* Purchase Date
* Expiration Date

---

# Admin Portal

Add:

```text
Admin
→ Domains
```

Create:

```text
public/app/admin/domains.php
```

Display:

* Business
* Domain
* Status
* Registrar
* Expiration

---

# Admin Controls

Allow:

* Approve domain request
* Mark purchased
* Mark active
* Mark transferred
* Mark expired
* Cancel request

No automated registrar integration yet.

---

# Website Publishing Preparation

Add website-domain relationship tracking.

Store:

* Website
* Domain
* Publish Status

Statuses:

```text
draft
ready
published
```

---

# Domain Ownership Rules

Domains purchased through 247SP are owned by Frank Dalba Ventures until transferred.

Transfer policy:

First 12 Months: $150

Months 13–24: $250

24+ Months: $350

Store transfer fee schedule in documentation only.

---

# Security

Customers may only view domains associated with their businesses.

Admins may manage all domains.

---

# Explicitly Out Of Scope

Do NOT build:

* Namecheap API integration
* Cloudflare integration
* DNS automation
* SSL automation
* Email provisioning
* Payment processing
* Website publishing

These will be addressed in future sprints.

---

# Definition Of Done

Customer can:

* View domain requests
* View domain status

Admin can:

* Manage domain requests
* Track domain ownership
* Track domain lifecycle

System can:

* Store domain records
* Associate domains with businesses
* Associate domains with websites

All functionality must operate in staging.
