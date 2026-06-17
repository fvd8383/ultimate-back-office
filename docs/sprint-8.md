# Sprint 8 – Email Provisioning Foundation

## Objective

Create the email provisioning and management foundation required for 24/7 Sales Partner customers.

This sprint does not create real mailboxes.

This sprint establishes:

* Mailbox requests
* Mailbox assignments
* Mailbox status tracking
* Customer mailbox visibility
* Admin mailbox management

Future mailbox providers and automation will connect to this foundation.

---

# Business Goal

A customer should be able to request business email addresses during onboarding and track their status through the platform.

The system must be able to manage:

* Requested mailboxes
* Approved mailboxes
* Active mailboxes
* Suspended mailboxes
* Cancelled mailboxes

Before integrating actual mailbox creation, these workflows must exist.

---

# Product Rules

24/7 Sales Partner includes:

```text
1 mailbox included
```

Additional mailboxes:

```text
$3/month each
```

Billing integration will occur later.

Store mailbox counts now.

---

# Customer Workflow

Customer:

1. Creates business
2. Activates 247SP
3. Completes onboarding
4. Chooses domain
5. Requests mailbox(es)
6. Views mailbox status
7. Receives provisioning updates

---

# Database

Create migration:

010_email_provisioning.sql

---

## Mailbox Requests

Store:

* id
* business_id
* requested_email
* display_name
* status
* created_at

Statuses:

```text
requested
pending_setup
active
suspended
cancelled
```

---

## Mailbox Assignments

Store:

* id
* business_id
* email_address
* display_name
* status
* mailbox_type
* created_at

Mailbox types:

```text
included
additional
admin
```

---

## Mailbox Activity Log

Store:

* id
* mailbox_assignment_id
* activity_type
* notes
* created_at

Examples:

```text
created
activated
suspended
cancelled
password_reset
```

---

# Customer Portal

Add:

```text
Accounts
├─ Dashboard
├─ Businesses
├─ Billing
├─ Domains
├─ Email
└─ Logout
```

Create:

```text
public/accounts/email.php
```

Display:

* Requested Mailboxes
* Active Mailboxes
* Status
* Mailbox Type

Allow mailbox requests.

No mailbox creation occurs.

---

# Admin Portal

Add:

```text
Admin
├─ Dashboard
├─ Users
├─ Businesses
├─ Websites
├─ Billing
├─ Domains
├─ Email
```

Create:

```text
public/app/admin/email.php
```

Display:

* Business
* Email Address
* Status
* Mailbox Type
* Request Date

---

# Admin Controls

Allow:

* Approve Request
* Mark Pending Setup
* Activate Mailbox
* Suspend Mailbox
* Cancel Mailbox
* Log Activity

All actions are manual.

---

# Onboarding Integration

If customer selected email during onboarding:

Create mailbox request automatically.

Display request status in Email page.

---

# Billing Preparation

Store:

* Included mailbox count
* Additional mailbox count

No charges generated during this sprint.

Future billing integration will use this data.

---

# Security

Customers may only view mailbox records associated with their businesses.

Admins may manage all mailbox records.

---

# Reporting

Admin Email page should show:

* Total Requested
* Total Pending Setup
* Total Active
* Total Suspended
* Total Cancelled

---

# Explicitly Out Of Scope

Do NOT build:

* Microsoft 365 integration
* Google Workspace integration
* Roundcube integration
* SMTP provisioning
* IMAP provisioning
* Password management
* DNS mail records
* Email sending
* Payment collection
* Automatic mailbox creation

---

# Definition Of Done

Customer can:

* Request mailboxes
* View mailbox status

Admin can:

* Manage mailbox requests
* Manage mailbox status
* Track mailbox lifecycle

System can:

* Store mailbox requests
* Store mailbox assignments
* Store mailbox activity

All functionality must operate in staging without third-party email providers.
