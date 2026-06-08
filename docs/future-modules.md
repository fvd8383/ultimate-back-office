# Future Modules and Long-Term Architecture

## Purpose

This document describes future Ultimate Back Office modules and platform capabilities that should be preserved in the architecture, even if they are not built in v1.

Codex should not build these features until specifically instructed, but it must avoid database, routing, permission, or code decisions that would block them later.

---

# 1. Core Rule

UBO v1 should be built as a responsive web application, but the long-term platform should support:

```text
Web app
Customer portal
Field technician app
iOS app
Android app
API integrations
```

Business logic should be separated from presentation where practical.

---

# 2. Customer Portal

Future customer portal functionality may include:

```text
View estimates
Accept estimates
Request estimate changes
Reject estimates
View invoices
Pay invoices
View appointment history
Request service
Book appointments
Manage recurring services
View service professionals
Message businesses
Leave reviews
```

Important rule:

One customer/portal user may interact with multiple UBO businesses.

Example:

```text
My Service Professionals

- ABC Plumbing
- XYZ Electric
- Green Lawn Care
```

V1 will use secure token links instead of customer passwords.

---

# 3. Scheduling

Future scheduling should support two paths.

## Instant Booking

Customer selects an available time slot and books directly.

## Appointment Request

Customer requests service, but the business must approve or respond before the appointment is confirmed.

This is important for service businesses where the job scope, travel distance, or crew availability must be reviewed first.

Future scheduling may include:

```text
Availability rules
Employee calendars
Service durations
Buffer times
Appointment requests
Confirmed appointments
Rescheduling
Cancellations
Customer reminders
```

---

# 4. Recurring Services

Future recurring services should support:

```text
Weekly
Biweekly
Monthly
Quarterly
Annually
Custom frequency
```

Target use cases:

```text
Lawn care
Cleaning
Pest control
Pool service
Snow removal
Maintenance plans
```

Recurring services may eventually connect to:

```text
Recurring invoices
Scheduled jobs
Customer portal
Field technician app
Payment methods
```

---

# 5. Field Operations

Future Full OS functionality should support field service workflows.

Future features may include:

```text
Jobs
Job assignments
Dispatching
Daily job list
Field technician notes
Job photos
Job status tracking
Customer notifications
Google Maps directions
On-my-way messages
Job completion reports
```

Field technicians may be employees without platform login at first, then later linked to user accounts or mobile app access.

---

# 6. Field Technician App

Future Android/iOS field tech apps may allow employees to:

```text
View jobs for the day
Open directions in Google Maps
Notify customers they are on the way
Call or text customers
Take job photos
Enter job notes
Update job status
Mark jobs complete
Collect payment
```

The database should separate `employees` from `users` so not every employee requires a login.

---

# 7. Communications and VoIP

Future Full OS functionality should support business communications through Twilio.

Long-term goal:

```text
A VoIP/phone system similar to RingCentral for service businesses
```

Future features may include:

```text
Business phone numbers
User direct numbers
Employee direct numbers
Call routing
Call forwarding
SMS
Voicemail
Voicemail transcription
Call logs
Call recordings
Missed call notifications
Lead source tracking numbers
```

Phone numbers may be assigned to:

```text
Business
User
Employee
Marketing campaign
EMD lead source
```

---

# 8. Google Workspace and Email

V1 247SP supports a manual professional email setup add-on.

V1 pricing:

```text
$25 one-time professional email setup
```

Future support may include:

```text
Vendasta integration
Google Workspace provisioning
Mailbox creation
DNS automation
MX records
SPF records
DKIM records
DMARC records
```

Long-term:

* 247SP pricing may increase when Google Workspace is bundled.
* Full OS pricing should absorb Workspace costs without needing a price increase.

---

# 9. Financial Services Expansion

UBO will use Stripe as the initial financial backbone.

Future Stripe products may include:

```text
Stripe Connect
Stripe Financial Connections
Stripe Treasury
Stripe Capital
Stripe Issuing
```

Future financial features may include:

```text
Bank feeds
Cash flow dashboard
Operating accounts
Reserve accounts
Capital offers
Employee spending cards
Virtual cards
Physical cards
Transaction matching
Reconciliation
```

The database should use `business_payment_accounts` as the abstraction layer for Stripe Connect and future providers.

---

# 10. Know Your Numbers Expansion

KYN v1 uses:

```text
Manual expenses
Required receipt uploads
Revenue from SSP only
Basic P&L
```

Future KYN may support:

```text
Bank feeds
Transaction matching
Receipt matching
Cash flow forecasting
Tax planning
Payroll integration
Job-level profitability
Enterprise-level financial reporting
```

KYN should eventually support both business-level and enterprise parent-level reporting.

---

# 11. 7% Club

7% Club is a service opt-in where a business pays FDV an additional percentage of revenue for hands-on operational support.

Potential services:

```text
Customer service
Planning
Dispatching
Bookkeeping
Payroll
Account management
Daily operations
```

7% Club should not require a separate product architecture.

It should be supported through:

```text
Service engagements
Staff assignments
Permissions
Revenue-based usage charges
Admin tools
```

---

# 12. EMD Marketplace Expansion

V1 EMD is a pay-per-lead network.

Rules:

```text
Leads are exclusive
Leads are manually purchased in v1
Purchased leads flow into Lead Hub
```

Future EMD may evolve into a consumer-facing service marketplace similar to Thumbtack or Angi.

Future marketplace features may include:

```text
Customer service requests
Business matching
Auto-purchase rules
Service professional profiles
Customer reviews
Quote requests
Customer portal integration
```

The architecture should not assume EMD will always be business-only.

---

# 13. Industry-Specific Brands

UBO may eventually spin off FDV-owned industry-specific versions.

Examples:

```text
Landscape Back Office
HVAC Back Office
Cleaning Back Office
Plumbing Back Office
```

These are not outside-party white-label accounts.

They are FDV-owned brands using the same core platform.

Future brand differences may include:

```text
Logo
Theme
Marketing site
Templates
Onboarding questions
Default services
Default workflows
```

---

# 14. Enterprise Expansion

Enterprise supports multiple businesses under one parent account.

Rules:

```text
Each location is its own business
Each business has its own Full OS subscription
Enterprise pays an annual parent fee
Parent-level expenses must be supported
```

Future Enterprise features may include:

```text
Parent dashboard
Consolidated reporting
Parent-level expenses
Cross-business staff assignments
Multi-location analytics
Shared employee support
Shared payroll expense allocation
```

---

# 15. Mobile Apps and API

Future app support may include:

```text
iOS app
Android app
Field tech app
Customer portal app
Admin app
```

Future API-related features may include:

```text
API tokens
API clients
Mobile devices
Push notification tokens
Webhook subscriptions
Third-party integrations
```

Do not build these in v1 unless specifically instructed, but do not block them.

---

# 16. Features Not Required in V1

Do not build these during the initial foundation unless specifically instructed:

```text
Customer password login
Mobile apps
Full scheduling
Recurring service plans
VoIP
Twilio phone system
Field tech app
Stripe Treasury
Stripe Capital
Stripe Issuing
Bank feeds
Vendasta automation
Full EMD marketplace
Custom roles
White-label reseller accounts
```

The v1 foundation should make these possible later.
