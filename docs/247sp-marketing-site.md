# 24/7 Sales Partner Marketing Site

## Architecture

The launch marketing site lives in `public/marketing`. It is a standalone raw-PHP public root with no database dependency and no shared authenticated application stylesheet. The intended future production document root for `247salespartner.com` is:

```text
public/marketing
```

No Apache, DNS, SSL, staging, or production configuration is part of the implementation branch.

The site uses:

- `index.php` for the long-form landing page;
- `privacy.php`, `terms.php`, and `contact.php` for supporting utility pages;
- `config/marketing.php` for editable marketing presentation and destinations;
- `includes/components.php` for shared document, navigation, CTA, heading, and footer components;
- `assets/css/marketing.css` for public-site-only styling;
- `assets/js/marketing.js` for mobile navigation, first-party analytics events, FAQ engagement, and configurable VSL events.

Authenticated Accounts and App CSS are not imported or modified. Exact copies of the approved 247SP logo and favicon artwork live inside the isolated future document root.

## Configuration

Edit `public/marketing/config/marketing.php` to change:

- `promoted_pricing`: the one marketing cohort displayed on the landing page;
- `signup.url`: the centralized Ultimate Back Office CTA target and `product=247sp` context;
- `vsl.video_url` and `vsl.poster_image`: approved demo media;
- `support`: approved public support details and the account URL;
- `homepage`: the major homepage copy;
- `brand.canonical_url`: the canonical public domain;
- `legal`: utility-page destinations;
- `analytics_events`: semantic event identifiers;
- `features`, `industries`, and `faq`: reusable content collections.

This configuration controls presentation only. It does not query a pricing sequence, assign or reserve a cohort, write a subscription snapshot, call Stripe, or change completed-business-signup rules.

## Signup Context

Every marketing CTA targets:

```text
https://accounts.ultimatebackoffice.com/signup.php?product=247sp
```

The CTA appends a `utm_content` location for future campaign attribution. `SignupContext` allowlists only `247sp` and preserves it through account creation, OTP request, OTP verification, and the initial business-onboarding welcome. After verification, the prospect is sent to `business-create.php?product=247sp`.

The product parameter changes customer-facing context and the post-login destination only. It does not supply module, cohort, price, sequence, billing, Checkout, or Stripe command fields. Existing server-side product validation and completed-business-signup pricing assignment remain authoritative.

## Analytics Adapter

The page dispatches a `247sp:analytics` browser event with semantic payloads for page view, CTA clicks, signup initiation, FAQ engagement, VSL play, and VSL completion. If an approved implementation later defines `window.dataLayer`, the same payload is also pushed there. No production analytics ID, third-party script, or second analytics product is configured.

## Future Campaign Pages

The homepage reads hero, pricing, feature, industry, FAQ, CTA, VSL, legal, and analytics values from one configuration and uses shared rendering helpers. Future `/plumbers`, `/hvac`, `/electricians`, and `/landscaping` routes can reuse the same components and stylesheet with a campaign-specific content array instead of duplicating the whole page. No CMS or database layer is introduced.

## Launch Placeholders

The following require final approved content or assets before launch:

- VSL video and poster image;
- approved product screenshots or footage, if desired beyond the conceptual product-flow compositions;
- privacy policy;
- terms of service;
- refund and final cancellation policy;
- public support email or phone number;
- final review of the generated social preview card.

Placeholders are explicitly marked in configuration and markup. No fake testimonial, customer count, performance metric, conversation, dashboard data, review, rating, or guarantee is included.

## Local Preview

The separate staging-preview attempt on 2026-09-03 is **BLOCKED** by deployment-user
SSH public-key authentication failure. No staging URL or noindex protection was
verified. See `docs/247sp-marketing-staging-preview.md` for evidence and the required
access correction. Production `247salespartner.com` was not configured by this task.

From the repository root:

```powershell
php -S 127.0.0.1:8765 -t public/marketing
```

Then open `http://127.0.0.1:8765/`. Marketing assets use document-root-safe `/assets/...` URLs so they continue to resolve from future clean campaign paths such as `/plumbers` and `/plumbers/`.
