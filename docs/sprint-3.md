# Sprint 3 — 247SP Onboarding

## Goal

Create the complete 247SP onboarding system.

This sprint collects and stores all information required to build a customer website.

This sprint does not generate websites.

This sprint does not provision domains.

This sprint does not provision email.

This sprint does not create DNS records.

Those functions belong to future sprints.

---

# Required Reads

Before coding, read:

* docs/database-plan.md
* docs/future-modules.md
* docs/codex-rules.md
* docs/codex-handoff.md
* docs/brand-guidelines.md
* docs/247sp-product-spec.md

---

# Branch

Create:

sprint-3-247sp-onboarding

Open a pull request into main.

---

# Do Not Build

Do not build:

* Website generation
* Domain registration
* DNS automation
* Email provisioning
* Stripe billing
* Analytics integration
* AI content generation

---

# Database

Create migration:

database/migrations/003_247sp_onboarding.sql

Create tables necessary to store:

* 247SP onboarding records
* Domain selections
* Website configuration
* Business content
* Service page content

Use foreign keys tied to businesses.

---

# 247SP Dashboard

Create:

public/app/247sp/

Pages:

* dashboard.php
* onboarding.php
* review.php

---

# Access Rules

Only businesses with 247SP active may access 247SP pages.

If 247SP is not active:

Show access denied message.

---

# Onboarding Workflow

Step 1

Business Information

Collect:

* Business Name
* Contact Name
* Email
* Phone

Prepopulate from business profile when available.

---

Step 2

Service Area

Collect:

* Address
* City
* State
* ZIP

Add option:

Service Area Business

If enabled:

Physical address is hidden from website visitors.

---

Step 3

Services

Collect:

Primary Service Category

Service Pages:

Service 1
Service 2
Service 3

Require:

* Service Name
* Short Description

---

Step 4

Website Content

Collect:

* Business Description
* About Company
* Years In Business
* Financing Available
* Special Offer

---

Step 5

Domain Selection

Choose one:

Option A

Bring Existing Domain

Collect:

* Domain Name

Option B

Purchase Through 247SP

Collect:

* Desired Domain Name

Store status:

Pending

No registration yet.

---

Step 6

Email Selection

Collect:

Primary Mailbox Name

Examples:

info
support
office

Store mailbox request.

Do not provision.

---

Step 7

Review

Display all selections.

Allow editing.

---

Step 8

Submit

Mark onboarding complete.

Store:

setup_status = complete

Store completion timestamp.

---

# Dashboard

247SP dashboard should display:

Website Status

* Not Started
* In Progress
* Ready For Build
* Published (future)

Domain Status

* Not Selected
* Pending
* Registered (future)

Email Status

* Not Selected
* Pending
* Active (future)

---

# Lead Hub Integration

Display:

Lead Hub Included

No additional functionality required.

---

# Design System

Use Sprint 2.5 design system.

Use:

* Shared layouts
* Shared components
* Shared typography
* Shared colors

Follow:

docs/brand-guidelines.md

Use 247SP blue branding where appropriate.

---

# README

Update README.md

Document:

* 247SP onboarding workflow
* New database tables
* Status flow

---

# Success Criteria

User can:

* Activate 247SP
* Complete onboarding
* Save onboarding progress
* Review onboarding data
* Mark onboarding complete

System stores:

* Business details
* Services
* Website content
* Domain request
* Email request

No websites are generated.

No domains are registered.

No emails are provisioned.

All information required for Sprint 4 website generation exists in the database.
