# Sprint 2.5 — Design System

## Goal

Create a shared design system for the Ultimate Back Office ecosystem.

This sprint should not add new business functionality.

This sprint exists to establish reusable UI components, layouts, typography, colors, and styling before Sprint 3 (247SP).

---

## Required Reads

Before coding, read:

* docs/database-plan.md
* docs/future-modules.md
* docs/codex-rules.md
* docs/codex-handoff.md
* docs/brand-guidelines.md

---

## Branch

Create:

sprint-2-5-design-system

Open a pull request into main.

---

## Do Not Build

Do not build:

* New business functionality
* 247SP onboarding
* EMD functionality
* SSP functionality
* TUHWD functionality
* KYN functionality
* Payments
* CRM features
* Scheduling
* APIs
* Database migrations

---

## Do Not Modify

Do not modify:

* Apache configuration
* DNS
* SSL
* Server configuration
* Production credentials
* Authentication logic
* Business onboarding logic
* Module activation logic

---

# Design System Foundation

Create:

shared/ui/design-system.css

This file becomes the global design system for the entire platform.

---

## Typography

Primary font:

Poppins

Load from Google Fonts.

Fallback:

Segoe UI, sans-serif

Standards:

Headings:

* 700 weight

Subheadings:

* 600 weight

Body:

* 400 weight

Buttons:

* 600 weight

---

# Global Colors

## Background

Use:

#F5F5F5

for all page backgrounds.

Do not use pure white as the page background.

---

## Charcoal Standard

Use:

#222222

for:

* Headings
* Navigation
* Icons
* Logos
* Primary text
* Dark buttons

Never use:

#000000

---

# Brand Colors

## Ultimate Back Office

Primary:
#3B6C7A

Accent:
#D1892A

Charcoal:
#222222

---

## 24/7 Sales Partner

Primary:
#3144D3

Charcoal:
#222222

---

## EMD Network

Primary:
#D1892A

Charcoal:
#222222

---

## Super Simple Payments

Primary:
#007700

Charcoal:
#222222

---

## Tell Us How We Did

Primary:
#6D46AD

Charcoal:
#222222

---

# Shared Components

Create:

shared/ui/components/

Files:

* buttons.php
* cards.php
* badges.php
* alerts.php

---

## Buttons

Primary Button

* Brand-colored background
* White text
* Border radius 10px
* Font weight 600

Secondary Button

* White background
* Border
* Charcoal text

---

## Cards

Standard card:

* White background
* Border radius 16px
* Light border
* Subtle shadow

Used throughout:

* Accounts
* Lead Hub
* Future modules

---

## Badges

Support:

* Module badges
* Status badges
* Role badges

Rounded pill style.

---

## Alerts

Support:

* Success
* Warning
* Error
* Info

Use colors defined in brand-guidelines.md.

---

# Shared Layout

Create:

shared/ui/layout/

Files:

* header.php
* sidebar.php
* footer.php

---

## Header

Must support:

* Logo area
* User name
* Logout link

---

## Sidebar

Must support:

* Dashboard
* Businesses
* Modules

Navigation items may be placeholders for now.

---

## Footer

Simple shared footer.

No product-specific content yet.

---

# Accounts Refactor

Update Accounts screens to use:

* design-system.css
* shared components
* shared layout

Pages:

* login.php
* signup.php
* dashboard.php
* business.php
* business-create.php

No business logic changes.

---

# Lead Hub Refactor

Update:

public/app/dashboard.php

to use:

* design-system.css
* shared layout
* shared components

No functionality changes.

---

# Responsive Design

All pages should work on:

* Desktop
* Tablet
* Mobile

Use mobile-first layouts.

---

# Accessibility

Maintain:

* Proper labels
* Keyboard navigation
* Color contrast
* Focus states

---

# README

Update README.md:

Add section:

Design System

Document:

* Shared styling
* Shared components
* Brand guidelines integration

---

# Success Criteria

Accounts Portal:

* Uses design system
* Uses shared components
* Uses shared layouts

Lead Hub:

* Uses design system
* Uses shared components
* Uses shared layouts

Brand Guidelines:

* Implemented consistently

No business functionality changed.

No database changes.

No new product functionality added.
