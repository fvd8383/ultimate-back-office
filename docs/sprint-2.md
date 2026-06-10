# Sprint 2 — Business Foundation

## Goal

Build the complete business onboarding and management foundation for Ultimate Back Office (UBO).

Sprint 2 establishes the relationship between:

User → Business → Modules → Lead Hub

No actual module functionality is built during this sprint.

---

## Required Reads

Before coding, read:

* docs/database-plan.md
* docs/future-modules.md
* docs/codex-rules.md
* docs/codex-handoff.md

---

## Branch

Create:

sprint-2-business-foundation

Open a pull request into main.

---

## Do Not Build Yet

Do not build:

* 24/7 Sales Partner functionality
* EMD lead marketplace functionality
* SSP estimates, invoices, or payments
* TUHWD review workflows
* KYN bookkeeping functionality
* Scheduling
* Customer portals
* Twilio integrations
* Mobile applications
* APIs
* Field technician functionality
* Enterprise parent-company management

---

## Do Not Modify

Do not modify:

* Apache configuration
* DNS
* SSL
* Server configuration
* Production credentials
* Existing OTP authentication flow

---

# Database Changes

Create migration:

database/migrations/002_business_foundation.sql

---

## Update businesses table

Add:

* slug
* setup_status
* setup_step

Definitions:

setup_status:

* draft
* incomplete
* complete

setup_step:

* business_info
* services
* modules
* completed

---

## Create legal_structures

Columns:

* id
* name
* is_active
* created_at
* updated_at

Seed:

* Sole Proprietorship
* Single Member LLC
* Multi Member LLC
* Corporation
* S Corporation
* Partnership
* Nonprofit
* Other

---

## Create categories

Columns:

* id
* name
* is_active
* created_at
* updated_at

Seed:

* Plumbing
* Electrical
* HVAC
* Landscaping
* Cleaning
* Roofing
* Painting
* Handyman
* Pest Control
* Pool Service
* Pressure Washing
* Auto Detailing
* General Contractor
* Other

---

## Create sub_services

Columns:

* id
* category_id
* name
* is_active
* created_at
* updated_at

Seed representative services for each category.

Examples:

Plumbing:

* Leak Repair
* Drain Cleaning
* Water Heaters

Electrical:

* Panel Upgrades
* Wiring
* Generator Installation

HVAC:

* Repairs
* Maintenance
* Installations

---

## Create business_sub_services

Columns:

* id
* business_id
* sub_service_id
* created_at

---

## Update business_modules

Add:

* activated_by_user_id
* activation_source

activation_source values:

* manual
* full_os
* enterprise
* admin
* subscription

---

# Business Creation Wizard

Create:

public/accounts/business-create.php

---

## Step 1 — Business Information

Required:

* Business Name
* Legal Name
* Business Email
* Business Phone

Address:

* Address Line 1
* Address Line 2
* City
* State
* Postal Code
* Country

Additional:

* Physical Location (Yes/No)
* Legal Structure

Save progress.

Update onboarding status.

---

## Step 2 — Category & Services

Required:

* One Primary Category
* Multiple Sub Services

Rules:

* One category only
* Multiple services allowed

Save progress.

Update onboarding status.

---

## Step 3 — Module Selection

Available:

* 247SP
* EMD
* SSP
* TUHWD
* KYN
* Full OS
* Enterprise

Rules:

KYN requires SSP.

Full OS automatically activates:

* Lead Hub
* 247SP
* EMD
* SSP
* TUHWD
* KYN

Enterprise automatically activates:

* Full OS

Create records in business_modules.

Record activation_source.

---

## Step 4 — Confirmation

Display:

* Business Summary
* Selected Services
* Selected Modules

Mark onboarding complete.

Redirect to dashboard.

---

# Accounts Dashboard

Update:

public/accounts/dashboard.php

Display:

* User Name
* Business Name
* Business Status
* Profile Completion %
* Active Modules

If user has no business:

Display:

Create Business

Link to onboarding wizard.

---

# Business Profile Management

Create:

public/accounts/business.php

Editable:

* Business Name
* Legal Name
* Phone
* Email
* Address
* Physical Location
* Legal Structure
* Category
* Services

---

# Lead Hub Dashboard

Update:

public/app/dashboard.php

Display:

* Business Name
* Module Status
* Contact Count
* Task Count
* Recent Activity

Counts may initially be zero.

No CRM functionality required yet.

---

# Business Slugs

Generate automatically:

Examples:

Dalba Plumbing
→ dalba-plumbing

Frank's Electric
→ franks-electric

Slug must be unique.

Store in businesses.slug.

---

# Permissions

Only authenticated users may:

* Create businesses
* Edit businesses
* Complete onboarding

Users should only see businesses linked to them.

---

# UI Requirements

Continue using:

* Raw PHP
* HTML
* CSS
* Vanilla JavaScript

No frameworks.

Maintain consistent styling with Sprint 1.

---

# README

Update README.md with:

* Business onboarding process
* Business profile management
* Category/service structure
* Module activation framework

---

# Success Criteria

A user can:

1. Login
2. Create a business
3. Enter business information
4. Select category
5. Select services
6. Select modules
7. Complete onboarding
8. View business dashboard
9. Edit business profile
10. Access Lead Hub dashboard

All data must be stored in the database and persist across sessions.
