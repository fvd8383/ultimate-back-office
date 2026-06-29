# Sprint 8.5 - Production Readiness UX and Application Shell

## Status

Complete.

Sprint 8.5 closed the production-readiness UX gap for the current 24/7 Sales Partner launch path. It did not add payment processing, public production deployment, domain API automation, email API automation, AI generation, or new modules.

## Completed Outcomes

Sprint 8.5 delivered:

* Persistent Application Shell across account, workspace, module, and admin areas
* Account navigation cleanup
* Workspace navigation cleanup
* Admin navigation cleanup
* 24/7 Sales Partner module navigation under the workspace shell
* Return path between Accounts and App areas
* Dashboard business-card cleanup
* Product-neutral business onboarding copy
* Business onboarding field-order cleanup
* Legal Structure = Other text field support
* Expanded service selections
* Active-module-only customer module selection
* Module-selection card alignment fixes
* 24/7 Sales Partner Website Manager polish
* Admin-side DFY Website Editor
* CTA configuration controls
* Homepage stat configuration controls
* Service page hierarchy and parent/child service pages
* Services dropdown navigation in the private website preview
* Pricing-list upload
* Production readiness documentation updates

## Application Shell

The persistent shell is now the primary navigation model.

It includes:

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

Account pages live under `public/accounts`.

Workspace pages live under `public/app`.

Admin pages live under `public/app/admin`.

24/7 Sales Partner pages live under `public/app/247sp`.

The shell keeps the primary left navigation visible when entering modules. Module-specific navigation appears as secondary navigation instead of replacing the global shell.

## Account Navigation

Account navigation is separated from business-specific actions.

Account navigation includes:

* Home
* Businesses
* Billing
* Domains
* Email
* Profile

The account dashboard is now an overview surface. Business cards show business summary information and Edit Business only. Billing, Domains, Email, Lead Hub, and 24/7 Sales Partner live in the persistent navigation rather than duplicated business-card action groups.

## Workspace Navigation

Workspace navigation includes:

* Lead Hub
* 24/7 Sales Partner

Lead Hub is treated as a workspace module/action, not as the global application shell title.

24/7 Sales Partner opens the selected or available business workspace when a business context is available.

## Admin Navigation

The Admin Portal link is role-gated by existing internal admin authorization.

Regular customer users do not see Admin Portal in the global navigation.

Admin pages keep the application shell visible. Admin-specific sections such as Users, Businesses, Websites, Billing, Domains, and Email remain inside the admin portal experience.

## Return Path Between Accounts And App

Users can move between:

* Accounts dashboard and account-level pages
* App workspace pages
* Lead Hub pages
* 24/7 Sales Partner pages
* Admin Portal pages when authorized

Cross-area links use configured `ACCOUNTS_BASE_URL` and `APP_BASE_URL` where available, with safe relative fallbacks.

## Module Navigation

24/7 Sales Partner secondary navigation appears under the active 24/7 Sales Partner workspace item.

It includes:

* Dashboard
* Onboarding
* Review
* Preview
* Website Manager

Lead Hub secondary navigation appears under the active Lead Hub workspace item.

It includes:

* Dashboard
* Leads
* Contacts
* Tasks
* Notes

The 24/7 Sales Partner navigation remains separate from Lead Hub navigation.

## 24/7 Sales Partner Website Manager

Customer Website Manager supports editing existing customer-safe website settings:

* Logo
* Brand colors
* Home hero image
* About image
* Service images
* Homepage heading and description
* Homepage CTA labels and behaviors
* Homepage stat cards
* About page heading and description
* Contact page heading and description
* Existing active service page title and description
* Pricing-list upload
* Save and regenerate private preview

Customer Website Manager does not expose add/remove/reorder service page controls. Those are admin-side responsibilities.

## Admin Website Editor

The admin DFY editor lives at:

`public/app/admin/website-editor.php`

It requires existing internal admin authorization.

Internal admins can edit and prepare a customer's 24/7 Sales Partner website without customer impersonation.

Admin Website Editor supports:

* Branding
* Logo
* Home/about/contact hero images
* Homepage content
* Homepage CTA configuration
* Homepage stat cards
* About content
* Contact content
* Existing service page content
* Service supporting copy and trust text
* Service images and service hero images
* Service page add/edit/reorder/deactivation
* Parent/child service page assignment
* Pricing-list upload
* Preview and regeneration

## CTA Configuration

CTA labels are customer-facing labels. Active CTA behavior is intentionally limited.

Primary CTA label options:

* Call Now
* Request Service
* Book Appointment
* Instant Quote
* Get Estimate
* Request Inspection
* Apply Now
* Reserve Spot

Secondary CTA label options:

* Free Estimate
* Contact Us
* View Pricing
* Learn More

Active CTA behaviors:

* `call_now`
* `contact_form`
* `view_pricing`

Labels that imply scheduling, instant quoting, applications, or reservations route to contact form unless the behavior is explicitly set to call or view pricing.

No calculators, scheduling engine, quote engine, application workflow, reservation workflow, payment processing, or ecommerce behavior was added in Sprint 8.5.

## Homepage Stat Configuration

Homepage stat cards are editable through customer Website Manager and Admin Website Editor.

Date Business Started is used to calculate years in business when available.

If no business start date is available, the site avoids displaying "0 years in business" and falls back to customer-safe local service language.

## Service Hierarchy

Service pages are stored in the existing 24/7 Sales Partner service page model with added hierarchy metadata.

Admins can manage:

* Parent service pages
* Child/sub-service pages
* Sort order
* Active/inactive status
* Stable slugs

Example:

```text
Clogged Drain
- Clogged Toilet
- Clogged Sink Drain
```

Customer Website Manager can edit existing active service content but does not manage service structure.

## Services Dropdown Navigation

Private website preview navigation now uses:

* Home
* Services
* About
* Contact

Service pages appear under the Services dropdown instead of as top-level navigation items.

Sub-services appear nested under their parent service.

Inactive service pages do not appear in preview navigation after regeneration.

## Pricing List Upload

Pricing-list upload is supported through the customer Website Manager and Admin Website Editor.

Supported file types:

* PDF
* PNG
* JPG/JPEG
* WEBP

Uploads use the existing 24/7 Sales Partner upload approach under:

`public/app/uploads/pricing-lists/`

When a pricing list exists, View Pricing links to the uploaded file.

When View Pricing is selected without an uploaded pricing list, the preview routes to the contact page and uses customer-facing fallback copy.

## Customer Responsibilities

Customers can:

* Complete business onboarding
* Activate the customer-ready module
* Complete 24/7 Sales Partner onboarding
* Generate a private website preview
* Edit customer-safe Website Manager fields
* Upload customer-safe website assets
* Upload a pricing list
* Request domain and email setup
* View billing, domain, email, and module status

Customers cannot:

* Access admin-only editor controls
* Add, reorder, deactivate, or nest service pages
* Access internal admin portal routes
* Trigger payment processing, domain automation, email automation, or public production deployment from Sprint 8.5

## Admin Responsibilities

Internal admins can:

* Manage users and businesses
* View and manage admin website records
* Edit customer 24/7 Sales Partner websites through the DFY editor
* Manage service page hierarchy
* Manage service page status and ordering
* Regenerate private previews
* See billing/module access mismatches
* Manage domain and email workflow status through existing admin tools

Admins remain responsible for manual operational review until later payment, domain, email, publishing, and lead-capture automation is implemented.

## Out Of Scope

Sprint 8.5 did not implement:

* Stripe
* Payment processing
* Domain API automation
* Email API automation
* Public production deployment
* Public website publishing
* Lead capture into Lead Hub
* AI generation
* EMD
* SSP
* TUHWD
* New modules

## Definition Of Done

Sprint 8.5 is complete when:

* The persistent application shell is the primary navigation model.
* Account, workspace, module, and admin navigation are clearly separated.
* 24/7 Sales Partner Website Manager supports the current customer-safe customization scope.
* Admin Website Editor supports DFY website preparation and service hierarchy management.
* CTA, homepage stat, service hierarchy, Services dropdown, and pricing-list behaviors are documented.
* Production readiness and first-customer documentation reflect the completed Sprint 8.5 state.
