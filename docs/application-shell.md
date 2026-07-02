# Application Shell

## Purpose

This document defines the persistent application layout and navigation standards for Ultimate Back Office (UBO). Account pages, workspace pages, admin pages, customer modules, and future products should use this shell unless the platform layout standard changes.

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
- future modules

ADMIN
- Admin Portal

Log out
```

ACCOUNT, WORKSPACE, and ADMIN are section labels only. They are not collapsible controls and should not show expand/collapse arrows.

Workspace module rows may expand to show secondary navigation. Only the active module should be expanded at a time. Inactive modules remain visible as top-level WORKSPACE entries without their secondary links.

Workspace module rows should use the generic module navigation pattern:

* module logo or favicon on the left
* module name beside the logo
* optional child links nested under the active module

Adding a future workspace module should require only a module logo path, module name, route, and child links. It should not require custom shell HTML.

## Account Section

ACCOUNT links are account-level destinations. They are not module-specific.

Current routing:

* Home: `public/accounts/dashboard.php`
* Businesses: `public/accounts/businesses.php`
* Billing: `public/accounts/billing.php`
* Domains: `public/accounts/domains.php`
* Email: `public/accounts/email.php`
* Profile: `public/accounts/profile.php`

Account Home is the account overview surface for welcome content, alerts, and reporting placeholders. Business lists belong on the standalone Businesses page.

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

Module secondary navigation should not add redundant labels such as "Lead Hub Menu" or "24/7 Sales Partner Menu"; the expanded child links already sit under the active module row.

Admin pages keep the global shell visible, while admin routes such as Users, Businesses, Websites, Billing, Domains, and Email appear as secondary admin navigation in the content area.

## 24/7 Sales Partner Website Manager

The customer Website Manager lives inside the 24/7 Sales Partner module at:

`public/app/247sp/website-manager.php`

It is customer-facing and should expose only customer-safe controls:

* branding assets
* brand colors
* existing active service content
* homepage content
* homepage CTA labels and behavior
* homepage stat cards
* about and contact content
* pricing-list upload
* save and regenerate private preview

It must not expose admin-only service structure controls such as add, deactivate, reorder, or parent/child service assignment.

## Admin Website Editor

The admin DFY Website Editor lives inside the Admin Portal at:

`public/app/admin/website-editor.php`

It requires existing internal admin authorization. Regular customers must not see or access this route.

Internal admins can use it to prepare and polish a customer's 24/7 Sales Partner website without impersonating the customer.

Admin-only responsibilities include:

* service page add/edit/reorder/deactivation
* parent/child service page assignment
* service supporting copy and trust text
* service page images and hero images
* page-specific hero image management
* CTA and homepage stat review
* pricing-list upload/replacement
* private preview regeneration

## CTA And Pricing Behavior

CTA configuration belongs inside the 24/7 Sales Partner website workflow.

Supported active CTA behaviors are:

* `call_now`
* `contact_form`
* `view_pricing`

Customer-facing CTA labels may imply appointment booking, estimates, inspections, applications, or reservations, but those labels route to contact form unless the behavior is explicitly set to call or view pricing. The shell and module navigation must not imply that calculators, scheduling, quote engines, application workflows, reservations, payment processing, or ecommerce checkout exist.

Pricing-list uploads use the existing 24/7 Sales Partner asset upload pattern under:

`public/app/uploads/pricing-lists/`

View Pricing links to the uploaded pricing list when available. If no pricing list exists, View Pricing routes to the contact page with customer-facing fallback copy.

## Service Hierarchy And Website Navigation

Admin-managed service hierarchy supports:

* parent service pages
* child/sub-service pages
* sort order
* active/inactive service page status
* stable service slugs

Customer website preview navigation uses:

* Home
* Services
* About
* Contact

Service pages and sub-service pages appear under the Services dropdown. They should not appear as separate top-level website navigation items. Sub-services should be nested or indented under their parent service.

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

Sprint 8.5 delivered a responsive left navigation shell:

* desktop uses a persistent scrollable left rail beside content
* smaller screens stack the navigation above content
* navigation sections wrap without horizontal scrolling
* active state remains visible

## Scroll Behavior

Desktop account and app shell pages use a viewport-height frame:

* the header and footer stay outside the scrollable application panes
* the left sidebar has viewport-aware height and scrolls independently when navigation content exceeds the available height
* the main content area scrolls independently from the sidebar
* horizontal page scrolling should not be introduced

On smaller screens, the shell returns to normal stacked page scrolling so navigation and content remain reachable without narrow independent scroll panes.

## Business Context

Module pages should display the selected business in the content area, typically in the hero/header panel. Customers should be able to tell which business they are editing before changing module data.

Business-specific actions belong in business cards or business pages, not in duplicated navigation groups. The account dashboard business cards currently keep only the business overview and Edit Business action; module and account destinations belong in the global shell.

## Module Dashboard Readiness

Customer-facing module dashboards should include a Launch Readiness or Setup Readiness section whenever the module requires customer setup before first use.

The readiness section should:

* show the module-specific checklist items required before launch or first use
* clearly distinguish completed and incomplete items
* provide customer-friendly next actions for incomplete items
* use existing module, billing, domain, email, or workflow records where available
* avoid requesting payment before the customer has reached the module-specific approval or launch step
* avoid internal planning language in customer-facing copy

For 24/7 Sales Partner, payment belongs after website preview review and launch approval, not during signup or business onboarding. Customer payment acceptance belongs to separate payment-processing products, not the 24/7 Sales Partner setup workflow.

## Base URL Rules

Cross-area links should use configured base URLs when available:

* `ACCOUNTS_BASE_URL`
* `APP_BASE_URL`

Do not hardcode staging URLs. When config is unavailable, pages may use safe relative fallbacks for the active document root.

## Design Principle

Ultimate Back Office should feel like one operating system. Modules are applications inside that operating system, not separate products with separate global navigation shells.
