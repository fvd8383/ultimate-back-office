# Application Shell

## Purpose

This document defines the persistent application layout and navigation standards for Ultimate Back Office (UBO). Account pages, workspace pages, admin pages, customer modules, and future products should use this shell unless a later sprint explicitly changes the platform layout.

The shell must help customers and internal users understand:

* where they are
* which account area or workspace module they are using
* how to return to Accounts from App pages
* how to move between modules without losing the UBO context

## Persistent Header

The header is shared across account, app, module, and admin areas.

It should contain:

* UBO branding/logo
* the signed-in user when available
* logout access

The header should remain clean. Module-specific links belong in the left navigation or in module secondary navigation, not in the header.

## Persistent Left Navigation

The left navigation is the primary application navigation. It is not a floating utility card and it should not be replaced when entering a module.

The shell structure is:

```text
Ultimate Back Office

ACCOUNT
- Home
- Businesses
- Billing
- Domains
- Email
- Profile

WORKSPACE
- Lead Hub
- 24/7 Sales Partner

ADMIN
- Admin Portal

Log out
```

Icons should appear with labels. Sprint 8.5 may use simple temporary text or symbol icons because the repo does not currently include a shared icon library. A future design-system pass should replace temporary icons with the chosen shared icon set.

## Account Section

ACCOUNT links are account-level destinations. They are not module-specific.

Current routing:

* Home: `public/accounts/dashboard.php`
* Businesses: `public/accounts/dashboard.php#businesses`
* Billing: `public/accounts/billing.php`
* Domains: `public/accounts/domains.php`
* Email: `public/accounts/email.php`
* Profile: `public/accounts/profile.php`

No standalone `public/accounts/businesses.php` route exists in Sprint 8.5, so Businesses uses the dashboard businesses section rather than inventing a new route.

## Workspace Section

WORKSPACE links launch product/module areas.

Current routing:

* Lead Hub: `public/app/dashboard.php`
* Lead Hub Leads: `public/app/lead-hub/leads.php`
* Lead Hub Contacts: `public/app/lead-hub/contacts.php`
* Lead Hub Tasks: `public/app/lead-hub/tasks.php`
* Lead Hub Notes: `public/app/lead-hub/notes.php`
* 24/7 Sales Partner: `public/app/247sp/dashboard.php`

When a business is selected or discoverable, workspace links include the `business_id` query parameter. If no business is available, links fall back to the safest existing route and the destination page handles the empty state.

Lead Hub must be shown as a workspace module/action. It must not be used as the global sidebar title.

Future customer modules should plug into WORKSPACE as additional items. They should not create a separate global shell.

## Admin Section

ADMIN is visible only for users with existing internal admin access.

Current routing:

* Admin Portal: `public/app/admin/dashboard.php`

Admin visibility must use the existing internal role authorization logic. Regular customers must not see Admin Portal in the global navigation.

## Module Secondary Navigation

Entering a module does not replace the primary left navigation.

24/7 Sales Partner secondary navigation appears nested under the active 24/7 Sales Partner WORKSPACE item in the left sidebar. It includes:

* Dashboard
* Onboarding
* Review
* Preview
* Website Manager when the selected business has access

Website Manager remains inside the 24/7 Sales Partner workflow. It should not appear as a top-level account business action.

Lead Hub remains a separate WORKSPACE item and must not be moved into 24/7 Sales Partner secondary navigation.

Lead Hub secondary navigation appears nested under the active Lead Hub WORKSPACE item in the left sidebar. It includes:

* Dashboard
* Leads
* Contacts
* Tasks
* Notes

Admin pages keep the global shell visible, while admin routes such as Users, Businesses, Websites, Billing, Domains, and Email appear as secondary admin navigation in the content area.

## Active State Behavior

Only one primary navigation item should be active at a time.

Active state rules:

* account pages highlight the matching ACCOUNT item
* the app dashboard highlights Lead Hub
* 24/7 Sales Partner pages highlight 24/7 Sales Partner
* admin pages highlight Admin Portal for authorized internal users
* secondary module navigation highlights the current module/admin page

## Role-Based Visibility

Admin Portal is role-gated and only appears when the signed-in user passes the existing internal admin check.

Customer-facing account and workspace links are visible to signed-in users. Module pages still enforce their own access checks and should show access-denied or empty-state messaging when a business does not have the relevant module active.

## Mobile Behavior

Sprint 8.5 uses a responsive left navigation shell:

* desktop uses a persistent scrollable left rail beside content
* smaller screens stack the navigation above content
* navigation sections wrap without horizontal scrolling
* active state remains visible

A future design-system sprint may replace the stacked mobile layout with a hamburger drawer, but the current implementation must remain usable without horizontal scrolling.

## Scroll Behavior

Desktop account and app shell pages use a viewport-height frame:

* the header and footer stay outside the scrollable application panes
* the left sidebar has viewport-aware height and scrolls independently when navigation content exceeds the available height
* the main content area scrolls independently from the sidebar
* horizontal page scrolling should not be introduced

On smaller screens, the shell returns to normal stacked page scrolling so navigation and content remain reachable without narrow independent scroll panes.

ACCOUNT, WORKSPACE, and ADMIN sections may become collapsible in a future design-system sprint if the number of modules grows. Independent sidebar scrolling is the preferred Sprint 8.5 behavior, so collapsible sections are optional future polish rather than required behavior.

## Business Context

Module pages should display the selected business in the content area, typically in the hero/header panel. Customers should be able to tell which business they are editing before changing module data.

Business-specific actions belong in business cards or business pages, not in the global shell. Examples:

* Edit Business
* Open 24/7 Sales Partner
* Billing
* Domains
* Email

## Base URL Rules

Cross-area links should use configured base URLs when available:

* `ACCOUNTS_BASE_URL`
* `APP_BASE_URL`

Do not hardcode staging URLs. When config is unavailable, pages may use safe relative fallbacks for the active document root.

## Design Principle

Ultimate Back Office should feel like one operating system. Modules are applications inside that operating system, not separate products with separate global navigation shells.
