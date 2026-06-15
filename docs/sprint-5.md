# Sprint 5 — Admin Portal

## Goal

Create an internal administrative portal that allows Ultimate Back Office administrators to manage users, businesses, modules, onboarding progress, generated websites, and support activity.

This portal is for internal staff only.

Customers do not access these pages.

This sprint creates the operational control center for Ultimate Back Office.

---

# Required Reads

Before coding, read:

* docs/database-plan.md
* docs/future-modules.md
* docs/codex-rules.md
* docs/codex-handoff.md
* docs/brand-guidelines.md
* docs/247sp-product-spec.md
* docs/sprint-3.md
* docs/sprint-4.md

---

# Branch

Create:

sprint-5-admin-portal

Open a pull request into main.

---

# Do Not Build

Do not build:

* Website editing
* Domain automation
* Email provisioning
* Stripe billing
* AI content generation
* Public website publishing
* Customer CMS
* Support ticketing

---

# Database

Create migration:

database/migrations/005_admin_portal.sql

---

## Admin Notes

Create table:

admin_notes

Fields:

* id
* business_id
* user_id
* admin_user_id
* note
* created_at

---

## Business Flags

Add fields to businesses:

* is_suspended
* is_test_account
* internal_status

Defaults:

* is_suspended = 0
* is_test_account = 0
* internal_status = active

---

# Admin Roles

Support:

* Super Admin
* Admin

Only these roles may access admin pages.

All other users must receive:

Access Denied

---

# Admin Area

Create:

public/admin/

Pages:

* dashboard.php
* users.php
* user.php
* businesses.php
* business.php
* websites.php
* website.php

---

# Admin Dashboard

Display:

## Platform Metrics

* Total Users
* Total Businesses
* Total Websites
* Businesses Ready For Build
* Generated Websites

---

## Recent Activity

Display:

* Recent Signups
* Recent Businesses
* Recent Website Generations

---

# User Management

Users list should display:

* Name
* Email
* Status
* Created Date

Open user detail page.

---

# User Detail Page

Display:

* User information
* Linked businesses
* Active modules
* Website count

---

# Business Management

Businesses list should display:

* Business Name
* Owner
* Onboarding Status
* Website Status
* Active Modules
* Internal Status

Open business detail page.

---

# Business Detail Page

Display:

## Business Information

* Name
* Contact
* Email
* Phone

---

## Modules

Display:

* 247SP
* Lead Hub
* EMD
* SSP
* TUHWD
* Enterprise
* Full OS

---

## Actions

Allow:

* Enable Module
* Disable Module
* Suspend Business
* Unsuspend Business
* Mark Test Account

---

## Admin Notes

Allow:

* Add Note
* View Notes

---

# Website Management

Create:

websites.php

Display:

* Website Name
* Business
* Template
* Website Status
* Generated Date

---

# Website Detail Page

Display:

## Website Information

* Business
* Template
* Website Status
* Generated Date

---

## Website Assets Summary

Display:

* Logo Assigned (Yes/No)
* Primary Color Assigned (Yes/No)
* Secondary Color Assigned (Yes/No)
* Image Count

This is visibility only.

No editing.

---

## Website Actions

Allow:

* Generate Website
* Regenerate Website
* Open Preview

---

# Navigation

Admin sidebar:

* Dashboard
* Users
* Businesses
* Websites

---

# Design System

Use Sprint 2.5 design system.

Follow:

docs/brand-guidelines.md

Use:

* Charcoal #222222
* Background #F5F5F5
* Poppins

Admin pages should use Ultimate Back Office branding.

Not 247SP branding.

---

# README

Document:

* Admin roles
* Admin pages
* Admin notes
* Business controls
* Website controls

---

# Success Criteria

Administrators can:

* View users
* View businesses
* View websites
* View onboarding status
* View website status
* Generate websites
* Regenerate websites
* Open previews
* Add notes
* Suspend businesses
* Enable/disable modules

Customers cannot access admin pages.

No billing.

No domain automation.

No email provisioning.

No website editing.
