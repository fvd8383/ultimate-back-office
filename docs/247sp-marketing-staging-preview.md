# 247SP Marketing Staging Preview

Publication result on 2026-09-03: **PASS / ACTIVE**.

Exact preview URL:
[https://staging-app.ultimatebackoffice.com/marketing/](https://staging-app.ultimatebackoffice.com/marketing/)

This is **staging only**, not a production launch. It serves the existing standalone
sales/marketing property at `public/marketing`, separate from Site Platform, M4B
generic customer websites, `SiteCompositionManager`, `SiteGenerator`, and customer
Website Manager. No marketing site, revision, business association, or composition
record was created.

## Evidence source and resolved access history

This documentation correction records the authoritative publication evidence supplied
by the user on 2026-09-03. No staging or production access, deployment, or fresh remote
validation was performed during this local documentation update.

The earlier SSH/public-key access block is **historical and resolved**. It no longer
describes the current publication state. The deployed staging application remained at
`8805eeeae704f130ddda357e82c4dd936fde5b4c`; PR #110 and M4B were not deployed.

## Staging Apache routing

The administrator added routing in the staging-only HTTPS vhost
`staging-app.ultimatebackoffice.com`:

- Existing application document root remains `/var/www/ubo-repo/public/app`.
- `/marketing/` maps to `/var/www/ubo-repo/public/marketing/`.
- Exact marketing root-relative asset aliases serve the marketing CSS, JavaScript,
  logo, favicon, and social image.

No production vhost, DNS, or SSL was changed. `247salespartner.com` was **not
configured**.

## Verified HTTP evidence

All paths below use `https://staging-app.ultimatebackoffice.com`.

| Route or asset | Actual HTTP result |
| --- | --- |
| `/marketing/` | 200 |
| `/marketing/privacy.php` | 200 |
| `/marketing/terms.php` | 200 |
| `/marketing/contact.php` | 200 |
| `/assets/css/marketing.css` | 200 |
| `/assets/js/marketing.js` | 200 |
| `/assets/brand/247sp-logo.svg` | 200 |
| `/assets/brand/favicon.svg` | 200 |
| `/assets/img/og-247sp.png` | 200 |
| `/marketing` | 302 to `https://staging-app.ultimatebackoffice.com/marketing/` |

No 5xx responses were observed.

## Verified search-engine protection

Actual HTTP response headers verified `X-Robots-Tag: noindex, nofollow` on:

- `/marketing/`;
- `/marketing/privacy.php`;
- `/marketing/terms.php`;
- `/marketing/contact.php`;
- `/assets/css/marketing.css`; and
- the `/marketing` redirect response.

This protection was verified in responses, not merely recorded as configured. It is
preview-only and does not change eventual production marketing SEO behavior.

## Asset integrity

The served CSS exactly matched
`/var/www/ubo-repo/public/marketing/assets/css/marketing.css`:

```text
Repository file SHA-256:
261ae78c77fadbde899a81e357afcdc0ec31955ea6c64e27f828e29987c7a41a
Remote staging CSS SHA-256:
261ae78c77fadbde899a81e357afcdc0ec31955ea6c64e27f828e29987c7a41a
Result: MATCH
```

The served JavaScript exactly matched
`/var/www/ubo-repo/public/marketing/assets/js/marketing.js`:

```text
Repository file SHA-256:
cfe0e464c9c20459ebca0aa5b3ae8d800748522bdbd49f7bce16d02f456c07f6
Remote staging JavaScript SHA-256:
cfe0e464c9c20459ebca0aa5b3ae8d800748522bdbd49f7bce16d02f456c07f6
Result: MATCH
```

The staging application's `/assets` tree is not substituting the wrong CSS or
JavaScript for the marketing property.

## Remaining browser review QA

| Browser check | Result |
| --- | --- |
| Desktop 1440px | NOT YET RECORDED |
| Tablet 768px | NOT YET RECORDED |
| Mobile 390px | NOT YET RECORDED |
| Narrow mobile 320px | NOT YET RECORDED |
| Browser console | NOT YET RECORDED |
| Interactive navigation, mobile navigation, FAQ, and CTA/utility links | NOT YET RECORDED |
| Horizontal overflow | NOT YET RECORDED |

These remain review QA items and are not labeled PASS. Publication remains **PASS**
because the property is active and its HTTP, assets, integrity, and noindex contracts
are verified. Publication does not establish browser QA completion or launch readiness.

## Server and implementation boundaries

Publication required no M4B deployment, database mutation, migration, provider call,
production access, repository modification, permission or ownership change, sudoers
change, or Git configuration change. The only routing changes recorded above are
staging-only administrator Apache configuration. Production Apache, DNS, and SSL
remain unchanged, and `247salespartner.com` is not configured.

M4B remains **IMPLEMENTED LOCALLY / REVIEW REQUIRED**. M4C remains **NOT STARTED**.
PR #110 has not been deployed. Generic customer sites remain separate and dormant.

## Launch placeholders retained

The existing VSL/demo media and poster, approved screenshots/footage, final privacy
and terms copy, refund/cancellation policies, public support contact details, and
social-card approval remain pending. Preview publication does not mean launch
readiness. Marketing behavior, pricing/cohorts, signup context, onboarding, billing,
analytics, and provider integrations were not changed.
