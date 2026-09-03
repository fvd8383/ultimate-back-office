# 247SP Marketing Staging Preview

Publication result on 2026-09-03: **BLOCKED**.

This concerns only the existing standalone sales/marketing property at
`public/marketing`. It is separate from Site Platform customer composition and the
M4B internal admin preview. No site, revision, business association, or composition
record was created for marketing.

## Access evidence

The local SSH configuration contains deployment alias `ubo-deploy`, targeting
`ubo-deploy@45.55.252.62` with the configured deployment identity. The authorized
Phase B environment is Codex CLI directly on `ubo-stage-app` as `ubo-deploy`.

The connection attempt used batch public-key authentication and a bounded connection
timeout. The command failed (reported exit code 1) with:

```text
ubo-deploy@45.55.252.62: Permission denied (publickey).
```

Authentication failed before `whoami`, `hostname`, CLI discovery, or deployed Git
state checks could run. The expected staging runtime
`8805eeeae704f130ddda357e82c4dd936fde5b4c` was therefore **not verified**. No root,
alternate deployment user, permission changes, or broad sudo workaround was used.

## Publication and QA state

| Check | Result |
| --- | --- |
| Actual staging hostname / vhosts / document root | Not inspected; access blocked |
| Exact browser preview URL | Not available; no URL invented |
| Index, privacy, terms, contact HTTP statuses | Not tested |
| CSS, JS, logo, favicon, social asset HTTP statuses | Not tested |
| Desktop 1440px / tablet 768px / mobile 390px / narrow 320px | Not tested |
| Navigation, mobile navigation, FAQ, CTA anchors, utility links | Not tested on staging |
| Overflow, console, PHP errors, 5xx | Not verified on staging |
| Preview-only noindex/nofollow | Not verified; no protection claimed |
| M4B branch deployed | No |
| Repository or Apache changes on staging | None |
| Production access, DNS changes, SSL requests | None |
| Production `247salespartner.com` configured by this task | No |

## Required next action

An administrator must restore access for the existing configured deployment public
key to the `ubo-deploy` account, or open a working Codex CLI session directly as
`ubo-deploy` on `ubo-stage-app`. Do not share a private key or broaden sudo access.
This is the minimal known access correction; no Apache/DNS change can responsibly be
specified until actual staging routing and approved fixed wrappers are inspected.

After access is restored, verify identity and the expected deployed SHA, then inspect
existing staging vhosts/document roots read-only. Prefer existing staging routing.
The marketing source uses root-relative `/assets/...` URLs, so a proposed subpath
must be tested for the actual marketing CSS/JS/brand files, not merely an HTTP 200
index response. Use a fixed approved staging mechanism only if routing or
preview-only `X-Robots-Tag: noindex, nofollow` requires a change. If unavailable,
report the exact minimal administrator configuration after discovery; do not edit
Apache as root or alter production DNS.

Do not deploy M4B to publish this property. Preserve existing staging authentication.
Read CTA hrefs only; they currently target production-facing Accounts and must not be
used to create data. Complete the requested four-width and route/asset QA before
calling publication PASS.

## Launch placeholders retained

The existing VSL/demo media and poster, approved screenshots/footage, final privacy
and terms copy, refund/cancellation policies, public support contact details, and
social-card approval remain pending. Preview publication does not mean launch
readiness. Marketing behavior, pricing/cohorts, signup context, onboarding, billing,
analytics, and provider integrations were not changed.
