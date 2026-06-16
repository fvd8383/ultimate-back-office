# Sprint 5.5 – Website Management & Branding

## Objective

Bridge the gap between website generation and public publishing by allowing customers to customize their generated 24/7 Sales Partner website.

At the completion of Sprint 5.5, a customer should be able to:

* Upload a logo
* Select brand colors
* Upload website images
* Edit website content
* Regenerate previews
* View a personalized website preview

This sprint focuses on website customization only.

No public publishing occurs during this sprint.

---

# Business Goal

The current Sprint 4 website generator creates a functional website preview.

However, all generated websites look substantially the same and cannot be personalized.

Before domain automation and public publishing are introduced, customers must be able to make the website their own.

Sprint 5.5 creates that customization layer.

---

# New Navigation

## 247SP Dashboard

Add a new navigation item:

```text
247SP Dashboard
├─ Onboarding
├─ Review
├─ Preview
├─ Website Manager
└─ Lead Hub
```

Website Manager should only be visible when:

* 247SP is active
* Business exists
* User has access to the business

---

# Website Manager

Create:

```text
public/app/247sp/website-manager.php
```

Purpose:

Manage branding, images, and content for generated websites.

---

# Branding Section

## Logo Upload

Allow upload of:

* PNG
* JPG
* JPEG
* SVG

Store file path in database.

Display current logo preview.

---

## Primary Brand Color

Default:

```text
#3144D3
```

Allow customer override.

Examples:

```text
Blue
Green
Orange
Purple
Custom Hex
```

Validation:

* Must be valid hex color
* Store normalized value

---

## Secondary Brand Color

Optional.

Used for accents and buttons.

Store in database.

---

# Images

## Hero Image

Upload one image.

Used on:

* Homepage
* Service pages

---

## About Image

Upload one image.

Used on:

* About page

---

## Service Images

Optional.

Allow one image per service.

If not provided:

Use existing generated layout.

---

# Content Management

Allow customer editing of:

## Homepage

Fields:

* Headline
* Subheadline
* Call To Action

---

## About Page

Fields:

* About heading
* About description

---

## Contact Page

Fields:

* Contact heading
* Contact description

---

## Service Pages

For each service:

* Service title
* Service description

---

# Preview Integration

Existing preview pages must use:

* Uploaded logo
* Brand colors
* Uploaded images
* Edited content

Preview should immediately reflect changes after save.

---

# Website Regeneration

Add button:

```text
Save & Regenerate Website
```

Behavior:

* Save configuration
* Regenerate generated website records
* Return user to Preview

No publishing occurs.

---

# Database

Create migration:

```text
database/migrations/007_247sp_branding.sql
```

Add support for:

* Logo path
* Primary color
* Secondary color
* Hero image
* About image

Create additional tables only if necessary.

Do not modify completed Sprint 4 generated content tables unless required.

---

# File Storage

For Sprint 5.5:

Store uploads locally.

Example:

```text
public/uploads/logos/
public/uploads/hero-images/
public/uploads/about-images/
public/uploads/service-images/
```

CDN integration will occur later.

---

# Admin Portal Integration

## Website Detail Page

Display:

* Logo
* Primary color
* Secondary color
* Hero image
* About image
* Last generated date

Admins should be able to review website branding.

Editing is optional.

---

# Security

Validate:

* File type
* File size
* Ownership

Users must only manage websites belonging to businesses they can access.

---

# Explicitly Out Of Scope

Do NOT build:

* Domain automation
* DNS management
* Email provisioning
* Mailbox creation
* Stripe billing
* AI content generation
* Public publishing
* Blog system
* CMS
* Media library
* Analytics
* SEO tooling

---

# Definition Of Done

A customer can:

1. Create a business
2. Activate 247SP
3. Complete onboarding
4. Generate a website
5. Upload logo
6. Upload images
7. Select brand colors
8. Edit website content
9. Regenerate website
10. View personalized website preview

Admins can:

* View website branding assets
* View generated website details

All functionality must operate within the existing staging environment.
