# Sprint 4 — 247SP Site Generator

## Goal

Generate a complete 247SP website from onboarding data collected during Sprint 3.

A customer who completes onboarding should be able to generate a working website preview without requiring manual page creation.

This sprint creates websites.

This sprint does not provision domains.

This sprint does not provision email.

This sprint does not modify DNS.

This sprint does not include AI-generated content.

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

---

# Branch

Create:

sprint-4-site-generator

Open a pull request into main.

---

# Do Not Build

Do not build:

* Domain registration
* DNS automation
* Email provisioning
* Stripe billing
* AI content generation
* Analytics integration
* Blog functionality
* Online scheduling
* Review widgets
* Media uploads

---

# Database

Create migration:

database/migrations/004_247sp_site_generator.sql

Add tables required for:

* Generated websites
* Generated pages
* Site publishing status
* Template assignments

Use foreign keys tied to businesses.

---

# Website Generator Service

Create:

private/classes/SiteGenerator.php

Responsibilities:

* Read completed onboarding data
* Build site structure
* Create page records
* Store generated content
* Track generation status

---

# Website Templates

Create a single template only.

Template Name:

Starter Local Service

Do not build multiple templates.

Do not build template switching.

Store template assignment for future use.

---

# Generated Pages

Every generated website contains:

1. Home
2. Service 1
3. Service 2
4. Service 3
5. About
6. Contact

Total:

6 pages

---

# Home Page

Generate using onboarding data:

* Business Name
* Business Description
* Service Area
* Service Highlights
* Call To Action

---

# Service Pages

Generate:

* Service Name
* Service Description
* Call To Action

One page per service.

---

# About Page

Generate:

* Company Description
* Years In Business
* Service Area

---

# Contact Page

Generate:

* Phone
* Email
* Contact Form Placeholder

No email sending required.

No lead processing required.

---

# Site Generation Workflow

From 247SP dashboard:

Add action:

Generate Website

Requirements:

* Onboarding must be complete.
* Website not already generated.

Generation should:

* Create website record.
* Create page records.
* Store generated content.
* Set status = generated.

---

# Website Preview

Create:

public/app/247sp/site-preview.php

Display generated pages.

Preview must be accessible only to authorized users.

Preview is not public.

---

# Website Status

Statuses:

* Not Started
* Ready For Build
* Generated
* Published (future)

---

# Regeneration

Allow:

Regenerate Website

Requirements:

* Existing generated pages replaced.
* Current onboarding data used.

---

# Dashboard

247SP dashboard should display:

Website Status

Template

Generation Date

Generate Website button

Regenerate Website button

Preview Website button

---

# Design System

Use Sprint 2.5 design system.

Follow:

docs/brand-guidelines.md

Use 247SP branding where appropriate.

---

# README

Update README.md

Document:

* Site generation process
* Template system
* Website status flow

---

# Success Criteria

User can:

* Complete onboarding
* Generate website
* Preview website
* Regenerate website

System stores:

* Website record
* Generated pages
* Template assignment
* Generation timestamp

No domains registered.

No DNS changes.

No email provisioning.

A complete six-page website can be generated from onboarding data and previewed inside the platform.
