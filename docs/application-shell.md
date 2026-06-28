# Application Shell

## Purpose

This document defines the permanent application layout and navigation standards for Ultimate Back Office (UBO).

All customer-facing modules, account pages, and future products must conform to this structure unless explicitly approved otherwise.

The goal is that every customer always knows:

* Where they are
* What business they are working in
* What module they are using
* How to return to another area

Navigation should never change unexpectedly.

---

# Overall Layout

```
┌───────────────────────────────────────────────────────────────┐
│ Ultimate Back Office                          User | Log Out  │
├───────────────┬───────────────────────────────────────────────┤
│               │                                               │
│ Left Sidebar  │               Page Content                    │
│               │                                               │
│               │                                               │
└───────────────┴───────────────────────────────────────────────┘
```

The application consists of:

* Persistent Header
* Persistent Left Navigation
* Dynamic Content Area

---

# Header

The header is always visible.

Contents:

* Ultimate Back Office logo
* Product title
* Logged-in user
* Log Out button

The header never changes based on the current module.

---

# Left Navigation

The left navigation is persistent across the application.

It does not change when entering a module.

It contains three sections.

---

## ACCOUNT

🏠 Home

🏢 Businesses

💳 Billing

🌐 Domains

✉️ Email

👤 Profile

Purpose:

Manage the customer account.

These pages are not tied to a specific module.

---

## WORKSPACE

Contains business modules.

Launch modules:

• Lead Hub

• 24/7 Sales Partner

Future modules:

• EMD

• Super Simple Payments

• Tell Us How We Did

• Additional modules

Modules appear only when:

* available to the customer
* active for the business

---

## ADMIN

Visible only for internal staff.

Examples:

Admin Portal

Future internal tools

Never shown to customers.

---

# Active Navigation

Only one primary navigation item may be active.

The active item:

* uses accent color
* includes hover state
* remains visible during navigation

---

# Module Navigation

Selecting a module does NOT replace the left navigation.

Instead, the page content changes.

Each module manages its own secondary navigation.

Example:

24/7 Sales Partner

* Dashboard

* Website Manager

* Preview

* Review

* Onboarding

Lead Hub

* Dashboard

* Leads

* Contacts

* Tasks

* Notes

* Pipeline

Future modules follow the same pattern.

---

# Business Context

The selected business is always visible within module pages.

Example:

Business Name

Current Module

Current User

Customers should never wonder which business they are editing.

---

# Business Actions

Business actions belong on Business pages, not in global navigation.

Examples:

* Edit Business

* Open Module

* Billing

* Domains

* Email

Business actions affect only the selected business.

---

# Navigation Rules

Navigation links move between application areas.

Buttons perform actions.

Do not use buttons as navigation unless there is a compelling reason.

---

# Icons

Every navigation item should include an icon.

Icons should remain consistent across all modules.

Avoid emojis in production.

Use a shared icon library.

---

# Empty States

Every page with no data should explain:

* What this page is

* Why it is empty

* What action the user should take next

---

# Mobile

On small screens:

* Left navigation collapses into a hamburger menu.

* Header remains visible.

* Current page remains highlighted.

No horizontal scrolling.

---

# Accessibility

Navigation should support:

* keyboard navigation

* visible focus states

* screen readers

* adequate color contrast

---

# Future Expansion

New modules should integrate by adding a single item under the WORKSPACE section.

No redesign of the application shell should be required.

Examples:

Lead Hub

24/7 Sales Partner

EMD

Super Simple Payments

Tell Us How We Did

Future products

---

# Design Principle

The application shell should feel like one operating system.

Modules are applications inside that operating system.

Customers should never feel like they have left Ultimate Back Office when moving between modules.
