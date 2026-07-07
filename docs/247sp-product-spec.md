# 24/7 Sales Partner (247SP) Product Specification

## Product Overview

24/7 Sales Partner (247SP) is a website and lead tracking platform for local service businesses.

The goal is to provide a professional online presence that works as a 24/7 salesperson for the business owner.

247SP is both:

* A standalone product
* A module included within Ultimate Back Office

---

# Target Customer

Primary audience:

* Plumbers
* HVAC Contractors
* Electricians
* Roofers
* Landscapers
* Cleaning Companies
* Handymen
* Mobile Detailers
* Pest Control Companies
* Other local service businesses

Characteristics:

* 1–10 employees
* Little or no website experience
* Needs an affordable online presence
* Wants more leads

---

# Pricing

## Setup Fee

$100 one-time

Includes:

* Website setup
* Domain connection
* Initial configuration

---

## Monthly Fee

$47/month

Includes:

* 247SP website
* Lead Hub access
* 1 business mailbox
* Basic SEO setup
* Google Analytics tracking

---

## Additional Emails

$3/month per mailbox

---

# Domain Policy

## Domain Purchased Through 247SP

247SP retains ownership until transferred.

Domain workflow:

1. Customer requests a domain.
2. Admin checks availability through the Domain Services layer.
3. Admin purchases the domain through the configured registrar.
4. Domain Manager prepares DNS records.
5. Admin syncs and verifies DNS.
6. SSL status is tracked.
7. Domain becomes launch-ready when purchase/connection, DNS verification, and SSL readiness are complete.

Transfer fee:

Months 0–12: $150

Months 13–24: $250

Months 25+: $350

---

## Bring Your Own Domain

Customer retains ownership.

No transfer fees apply.

Customer-owned domain workflow:

1. Customer enters the existing domain.
2. Domain Manager stores the request as customer-owned.
3. Customer sees the DNS records needed to connect the domain.
4. Admin verifies DNS and tracks SSL status.
5. Domain becomes launch-ready when DNS and SSL readiness are complete.

Customer-friendly domain statuses are:

* Requested
* Awaiting Customer
* Pending Purchase
* Pending DNS
* Pending Verification
* SSL Pending
* Ready
* Live
* Error

Domain Services architecture:

* `DomainManager` owns the customer/admin domain lifecycle, statuses, launch readiness, DNS planning, SSL status tracking, registrar selection, and domain event history.
* `RegistrarInterface` defines the registrar contract for availability checks, registration, transfers, domain status, DNS reads/writes, ownership verification, auto-renew, renewal, and status retrieval.
* `NamecheapRegistrar` is the first registrar adapter and contains Namecheap-specific API calls only.

Future registrar adapters such as Cloudflare, Porkbun, GoDaddy, Hover, and Dynadot should implement the same interface without changing 247SP business workflow code.

DNS automation prepares A records, optional AAAA records, `www` CNAME records, optional TXT verification records, and optional MX placeholders for later email provisioning. SSL automation is not claimed unless supported by staging/production infrastructure; current Sprint 8.6 behavior tracks SSL as Pending, Issued, Renewed, or Failed.

---

# Customer Inputs

During onboarding, customer provides:

## Business Information

* Business Name
* Legal Name (optional)
* Owner Name
* Email
* Phone Number

---

## Location

* Address
* City
* State
* ZIP Code

Service model:

* Customers come to my business
* We travel to our customers

When the business travels to customers, 247SP stores the business as a service-area business and collects a travel radius. Default radius is 25 miles, with supported choices of 10, 15, 20, 25, 35, 50, 75, or a custom mileage value.

Customer-facing website output for service-area businesses should emphasize city/state and service-area language instead of the street address.

---

## Services

Select:

* Primary Service Category
* Service Offerings

Examples:

Plumber

* Drain Cleaning
* Water Heater Repair
* Leak Detection

HVAC

* AC Repair
* Furnace Repair
* Maintenance Plans

---

## Website Content

* Business Description
* About Company
* Years in Business
* Special Offers
* Financing Available (yes/no)

---

## Branding

Optional:

* Logo Upload
* Brand Colors
* Photos

If not provided:

Use 247SP defaults.

---

# Website Structure

Every site includes:

1. Home
2. Services dropdown
3. About
4. Contact

The Services dropdown contains active service pages. Where configured by an internal admin, sub-service pages appear nested under parent services.

---

# Home Page

Contains:

* Hero section
* Call button
* Contact form
* Service highlights
* Trust indicators
* Service area
* CTA

CTA labels may be customer-facing service prompts such as Call Now, Request Service, Book Appointment, Instant Quote, Get Estimate, Request Inspection, Apply Now, Reserve Spot, Free Estimate, Contact Us, View Pricing, or Learn More.

Active CTA behaviors are limited to call, contact form, and view pricing. Scheduling, instant quote, application, reservation, and calculator-style labels route to contact form unless an admin explicitly selects call or view pricing. View Pricing links to the uploaded pricing list when available and otherwise routes to the contact page.

Pricing list uploads support PDF, PNG, JPG/JPEG, and WEBP files through the existing 247SP asset upload flow. No payment processing, checkout, scheduling engine, quote calculator, application workflow, reservation system, or ecommerce behavior is included.

---

# Service Pages

Each service page includes:

* Service description
* Benefits
* CTA
* Contact form

Internal admins can add, edit, reorder, deactivate, and nest service pages for done-for-you website management. For example, a plumbing site may include Clogged Drain as a parent service with Clogged Toilet and Clogged Sink Drain as sub-service pages.

Customer Website Manager may edit existing active service content, but add/remove/reorder/sub-service controls are admin-only for now. Future paid service or SEO page bundles may expose additional page capacity and self-serve management without adding billing logic in this sprint.

---

# Admin Website Editor Sections

Admin Website Editor settings should be organized around:

* Branding
* Pages
* Services
* Calls to Action
* SEO
* Integrations
* Advanced

The current editor may remain a single form. These sections define where future website settings belong.

SEO includes:

* Titles
* Meta descriptions
* Sitemap
* Robots
* Canonicals

Canonical controls are reserved for future SEO settings and should not be mixed into page copy fields.

Integrations include:

* Google Analytics
* Google Search Console
* Google Tag Manager
* Microsoft Clarity
* Meta Pixel
* Google Business Profile

Only Google Analytics is rendered into generated sites today. The other integration values are stored for admin reference and should not inject scripts or verification behavior.

Integration values are stored in `website_integrations`, a shared table for current and future website-enabled products.

---

# About Page

Includes:

* Company story
* Owner information
* Experience
* Service area

---

# Contact Page

Includes:

* Phone
* Email
* Contact form
* Address
* Map (future enhancement)

---

# Lead Tracking

All contact forms generate leads.

Store:

* Name
* Email
* Phone
* Message
* Source Page
* Date Submitted

---

# Lead Hub Integration

Every lead automatically appears in Lead Hub.

Lead status:

* New
* Contacted
* Won
* Lost
* Spam

---

# Email

Includes:

1 mailbox

Examples:

[info@business.com](mailto:info@business.com)

[support@business.com](mailto:support@business.com)

Additional mailboxes available.

---

# Analytics

Google Analytics tracking is configured per business website through the `website_integrations` model.

Admin users can store a Google Analytics Measurement ID, such as G-XXXXXXXXXX, in the Admin Website Editor. Customers do not need to edit code.

When a Measurement ID exists, the 247SP preview and generated/published site rendering include the GA tracking script in the page head. When no Measurement ID exists, the script is omitted cleanly.

The included foundation supports Google Analytics pageview tracking for:

* Visits
* Top pages
* Page engagement in Google Analytics

Tracking must not use one shared Google Analytics Measurement ID for all businesses unless traffic is also distinguishable by business.

Admin users may also store Google Search Console Property, Google Tag Manager ID, Microsoft Clarity ID, Meta Pixel ID, and Google Business Profile URL values. These are not rendered into generated sites in the current implementation.

---

# Basic SEO Setup

Basic SEO setup includes:

* Customer-friendly site structure
* Launch-ready service pages
* Page titles and metadata foundations
* Local service-area copy support

Basic SEO setup does not include Search Console API integration, SEO reporting dashboards, ranking trackers, or ongoing SEO service workflows.

---

# Customer Dashboard

Customer can view:

* Website status
* Domain status
* Lead count
* Recent leads
* Email count

---

# Internal Admin Controls

Admin can:

* View customer
* View website status
* View domain status
* Enable/disable account
* View onboarding progress

---

# Future Enhancements

Not part of Sprint 3.

Future items:

* AI content generation
* Multiple templates
* Blog support
* Online scheduling
* Review integration
* Call tracking
* SMS tracking
* Google Business Profile integration

---

# Sprint 3 Scope

Sprint 3 should focus only on:

* Customer onboarding
* Business information collection
* Service selection
* Domain selection
* Basic website configuration

No site generation yet.

Site generation will be Sprint 4.

247SP Package Rules

- 247SP automatically includes Lead Hub access.
- Customers do not see Lead Hub as a separate product.
- Every 247SP lead is automatically stored in Lead Hub.
- One business per account unless Enterprise is active.
- One included mailbox per 247SP subscription.
- Basic SEO setup and Google Analytics tracking are included in the 247SP monthly package.
- Additional mailboxes are billed separately.
- Customers may purchase a domain through 247SP or connect an existing domain.
