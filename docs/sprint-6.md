# Sprint 6 – Billing & Subscription Foundation

## Objective

Create the billing and subscription foundation required to support paying customers.

This sprint does not process real payments.

This sprint establishes:

* Plans
* Subscriptions
* Billing status
* Customer billing visibility
* Admin billing management

Future payment processors (Stripe, ACH, etc.) will connect to this foundation.

---

# Business Goal

Ultimate Back Office must be able to track:

* What products a customer has
* What they should be paying
* Whether they are active
* Whether they are past due
* Whether they are cancelled

Before accepting real payments, these billing relationships must exist.

---

# Current Launch Product

Only one customer-facing product should be billable:

```text
24/7 Sales Partner
```

Launch pricing:

Setup Fee: $100

Monthly Subscription: $27

---

# New Navigation

## Customer Accounts

Add:

```text
Accounts
├─ Dashboard
├─ Businesses
├─ Billing
└─ Logout
```

Create:

```text
public/accounts/billing.php
```

---

# Billing Statuses

Supported statuses:

```text
trial
pending_payment
active
past_due
cancelled
```

Status should be visible to:

* Customer
* Admin

---

# Database

Create migration:

```text
database/migrations/008_billing_foundation.sql
```

---

## Plans Table

Store available plans.

Fields:

* id
* product_key
* name
* setup_fee
* monthly_fee
* active
* created_at

Initial seed:

247SP

Setup Fee: 100.00

Monthly Fee: 27.00

---

## Subscriptions Table

Store customer subscriptions.

Fields:

* id
* business_id
* plan_id
* status
* started_at
* cancelled_at
* created_at

---

## Payments Table

Store payment records.

Fields:

* id
* subscription_id
* payment_type
* amount
* status
* transaction_reference
* created_at

Status examples:

```text
pending
paid
failed
refunded
```

---

# Customer Billing Page

Display:

Business

Current Plan

Monthly Fee

Setup Fee

Subscription Status

Start Date

No payment collection occurs during this sprint.

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
```

Create:

```text
public/app/admin/billing.php
```

Display:

* Business
* Plan
* Status
* Setup Fee
* Monthly Fee
* Start Date

---

# Admin Controls

Admin should be able to:

* Activate subscription
* Set trial status
* Set past due
* Cancel subscription

This is manual administration only.

No automated billing.

---

# Module Activation Rules

247SP may remain active when:

* trial
* active

247SP should display billing warnings when:

* pending_payment
* past_due

247SP should not be automatically disabled in this sprint.

---

# Reporting

Admin billing page should show:

Total Active Subscriptions

Total Trial Accounts

Total Past Due Accounts

Monthly Recurring Revenue (MRR)

MRR Calculation:

Active subscriptions × monthly fee

---

# Security

Customers may only view their own billing information.

Admins may view all billing information.

---

# Explicitly Out Of Scope

Do NOT build:

* Stripe integration
* ACH integration
* Credit card processing
* Automatic renewals
* Automated invoicing
* Tax calculations
* Refund workflows
* Collections workflows
* Domain automation
* Email provisioning

---

# Definition Of Done

Customer can:

* View billing information
* View subscription status

Admin can:

* View subscriptions
* Change subscription status
* View MRR metrics

System can:

* Store plans
* Store subscriptions
* Store payment records

All functionality must operate in staging without third-party payment processors.
