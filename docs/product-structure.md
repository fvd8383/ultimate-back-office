# Product Structure

## Purpose

This document defines the Ultimate Back Office product ecosystem.

It explains:

* Parent platform
* Customer-facing products
* Internal modules
* Product dependencies
* Current launch priority
* Which modules are visible or hidden

This document should be read before building new modules, onboarding flows, dashboards, pricing logic, or admin controls.

---

# Parent Platform

## Ultimate Back Office

Ultimate Back Office is the parent platform and operating system for local service businesses.

Ultimate Back Office includes:

* Accounts
* Business profiles
* Module activation
* Lead Hub
* 247SP
* Future financial, reputation, and lead marketplace tools

Ultimate Back Office is the umbrella brand.

---

# Core Account Structure

The system is organized as:

```text
User
  ↓
Business
  ↓
Modules / Products
  ↓
Product Configuration
  ↓
Product Output
```

A user may own or manage one or more businesses.

A business may have one or more active modules.

---

# Current Launch Priority

The first commercial product is:

```text
24/7 Sales Partner
```

All current development should prioritize making 247SP usable, sellable, manageable, and supportable.

Other modules exist in the platform structure but should remain hidden or unavailable until their sprint begins.

---

# Product Visibility Rules

## Visible Now

The following may be visible during the current 247SP build:

* 247SP
* Lead Hub

Lead Hub is visible because it supports 247SP lead tracking.

---

## Hidden Until Future Sprints

The following should not be available to regular customers yet:

* EMD Network
* Super Simple Payments
* Tell Us How We Did
* Know Your Numbers
* Full OS
* Enterprise

These products are future modules.

They may exist in the database, documentation, or admin controls, but customers should not be able to self-activate or rely on them yet.

---

# Lead Hub

Lead Hub is the shared lead tracking layer.

It is not currently sold as a standalone product.

Lead Hub is automatically included when a business activates:

* 247SP
* EMD Network
* Future lead-generating products

For 247SP customers, Lead Hub should feel like part of the product experience, not a separate product purchase.

---

# 24/7 Sales Partner

## Product Type

Standalone product and UBO module.

## Purpose

247SP creates a simple professional website and lead capture experience for local service businesses.

## Current Scope

247SP currently includes:

* Business onboarding
* Service selection
* Domain request storage
* Email mailbox request storage
* Website generation
* Private website preview

## Future Scope

Future 247SP sprints will include:

* Website management
* Logo upload
* Brand color selection
* Hero/service/about images
* Content editing
* Billing
* Domain provisioning
* Email provisioning
* Public publishing

---

# 247SP Dependencies

247SP automatically includes:

* Lead Hub

247SP does not require:

* SSP
* EMD
* TUHWD
* KYN
* Enterprise
* Full OS

---

# EMD Network

## Product Type

Future module.

## Purpose

Lead marketplace using exact match domains.

## Status

Not active for regular customer use.

Should remain hidden until its sprint begins.

---

# Super Simple Payments

## Product Type

Future module.

## Purpose

Simple estimates, invoices, payments, expenses, and financial tracking.

## Status

Not active for regular customer use.

Should remain hidden until its sprint begins.

---

# Tell Us How We Did

## Product Type

Future module.

## Purpose

Review funnel and customer feedback collection.

## Status

Not active for regular customer use.

Should remain hidden until its sprint begins.

---

# Know Your Numbers

## Product Type

Future module.

## Purpose

Financial reporting, bookkeeping insights, and business metrics.

## Status

Not active for regular customer use.

Should remain hidden until its sprint begins.

---

# Full OS

## Product Type

Future package.

## Purpose

Full Ultimate Back Office operating system bundle.

## Behavior

Full OS should include all active core modules when the OS is ready.

## Status

Not available for regular customer self-selection during the 247SP-only launch phase.

---

# Enterprise

## Product Type

Future account level.

## Purpose

Allows ownership or management of multiple businesses under one account.

Enterprise sits above individual business modules.

It is not the same thing as a business module.

## Status

Not available for regular customer self-selection during the 247SP-only launch phase.

---

# Module Activation Rules

## Current 247SP Launch Phase

Customer-facing module activation should allow only:

* 247SP

When 247SP is activated, the system should automatically activate:

* Lead Hub

Customers should not self-activate:

* EMD
* SSP
* TUHWD
* KYN
* Full OS
* Enterprise

---

# Admin Module Controls

Admins may view all modules.

Admins may enable or disable modules for testing.

Admin controls should clearly distinguish between:

* Available customer products
* Internal/future modules
* Test-only modules

---

# Customer Dashboard Rules

For normal customers, dashboards should only show:

* Active usable modules
* Products they can access
* Products that are actually implemented

Do not show placeholders for unfinished modules unless intentionally marked as coming soon.

---

# Current Milestone

As of Sprint 4, the platform has proven:

```text
Account
→ Business
→ 247SP activation
→ Onboarding
→ Website generation
→ Private preview
```

This is the core 247SP workflow.

It is not yet first paying customer ready.

---

# Before First Paying Customer

Required before taking paying customers:

* Production deployment
* Real OTP delivery
* Module gating
* Payment/billing workflow
* Customer-safe module visibility
* Website management tools
* Admin support visibility
* Basic operational workflow

---

# Development Rule

When building new features, always ask:

```text
Does this support the current 247SP launch path?
```

If not, defer it unless it is required for platform stability.
