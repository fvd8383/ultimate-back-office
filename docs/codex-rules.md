# Codex Rules for Ultimate Back Office

## Purpose

This document defines hard rules for Codex when building Ultimate Back Office.

Codex should follow these rules unless explicitly instructed otherwise.

---

# 1. Project Goal

Build Ultimate Back Office as a modular LAMP-stack business operating platform for service businesses.

Lead Hub is the core dashboard and CRM.

All products operate through Lead Hub.

---

# 2. Technology Stack

Use:

```text
PHP
MySQL
HTML
CSS
Vanilla JavaScript
Bootstrap where helpful
Apache
DigitalOcean Spaces
Stripe
Namecheap API
Twilio later
```

Do not use:

```text
Laravel
Symfony
React
Vue
Angular
Node.js backend
Python backend
Heavy frontend frameworks
```

unless explicitly approved.

The code should remain understandable for a solo founder who may need to maintain it manually.

---

# 3. Application Style

Use raw PHP with simple structure.

Preferred patterns:

```text
includes
shared components
clear folders
small reusable functions
environment config files
simple routing
```

Avoid unnecessary abstraction.

Do not create complicated framework-like systems unless needed.

---

# 4. Repository Structure

Recommended structure:

```text
ultimate-back-office/

docs/

apps/
    leadhub/
    accounts/
    247sp/
    emd/
    ssp/
    tuhwd/
    kyn/

shared/
    auth/
    billing/
    permissions/
    database/
    ui/
    notifications/
    files/

database/
    migrations/
    seeds/
    schema/

infrastructure/
    apache/
    deployment/

scripts/
```

---

# 5. Environment Rules

There are two environments:

```text
staging
production
```

Production URLs:

```text
app.ultimatebackoffice.com
accounts.ultimatebackoffice.com
cdn.ultimatebackoffice.com
```

Staging URLs:

```text
staging-app.ultimatebackoffice.com
staging-accounts.ultimatebackoffice.com
staging-cdn.ultimatebackoffice.com
```

Rules:

* All active development happens in staging.
* Production receives code only after review and approval.
* Staging and production use separate droplets.
* Staging and production use separate MySQL clusters.
* Staging and production use separate Spaces buckets/CDN endpoints.
* Staging may use smaller resources, but the software setup should match production.

---

# 6. Product Structure Rules

The platform has three commercial levels:

```text
Modular
Full OS
Enterprise
```

Lead Hub is included with every module.

Modular products:

```text
24/7 Sales Partner
EMD Network
Super Simple Payments
Tell Us How We Did
Know Your Numbers
```

Full OS includes:

```text
Lead Hub
247SP
EMD access
SSP
TUHWD
KYN
```

Enterprise adds:

```text
Multi-business parent account
Business switching
Parent-level expenses
Future consolidated reporting
```

---

# 7. Pricing Rules

Current pricing assumptions:

```text
247SP:
$100 setup
$27/month

247SP professional email setup:
$25 one-time

EMD:
pay per lead

SSP:
estimates are free
$3 per invoice sent for modular users
Stripe fees passed through

TUHWD:
$7/month

KYN:
$175/month
requires SSP

Full OS:
$375/month per business
$37/month per additional user

Enterprise:
$1,200/year parent fee
$375/month per business
$37/month per additional user
```

Full OS users do not pay the $3 SSP invoice fee.

Stripe processing fees are always passed through.

---

# 8. Naming Rules

Use:

```text
businesses
```

for paying UBO accounts.

Use:

```text
contacts
```

for the people or companies a business serves.

Use:

```text
portal_users
```

for future customer-facing users.

Do not create a table named:

```text
customers
```

for UBO-paying accounts.

Avoid ambiguity between UBO businesses and their customers.

---

# 9. Multi-Tenant Rules

Most business-facing records must include:

```text
business_id
```

Examples:

```text
contacts
estimates
invoices
expenses
review_requests
website_leads
appointments
jobs
```

No business should be able to access another business’s records unless Enterprise or internal admin permissions explicitly allow it.

---

# 10. Modular Account Rules

Modular users:

```text
one business
one user
no additional users
```

If a modular business wants extra users, they must upgrade to Full OS.

Do not build user-add functionality for modular accounts.

---

# 11. Full OS Rules

Full OS users:

```text
one business
all modules included
additional users allowed
```

Additional users are billed at:

```text
$37/month/user
```

---

# 12. Enterprise Rules

Enterprise is the only tier where one login can manage multiple businesses.

Each Enterprise location is its own business record.

Enterprise must support parent-level expenses.

Do not model multiple locations as one business unless specifically instructed.

---

# 13. Employee Rules

Employees are separate from users.

Use:

```text
employees
```

for workers.

Use:

```text
users
```

for login accounts.

An employee may optionally be linked to a user.

Not every employee needs a login.

This supports future:

```text
field tech app
dispatching
scheduling
payroll
Twilio user numbers
job assignments
```

---

# 14. Lead Hub Rules

Lead Hub is the center of the application.

Lead Hub should include:

```text
Dashboard
Contacts
Contact statuses
Notes
Tasks
Activity timeline
Module navigation
```

Use contacts with statuses instead of separate leads/customers tables.

Default statuses may include:

```text
New Lead
Contacted
Qualified
Estimate Sent
Customer
Inactive
Lost
Spam
```

---

# 15. Customer Interaction Rules

V1 customer interaction uses secure token links.

Customers should be able to:

```text
View estimates
Accept estimates
Request changes
Reject estimates
View invoices
Pay invoices
Leave reviews
Submit private feedback
```

V1 does not require customer passwords.

Secure customer tokens must be hashed in the database.

Preserve future support for customer portal login.

---

# 16. Shared Customer Identity Rules

Customers/clients are not owned exclusively by one business.

A future portal user may interact with multiple UBO businesses.

Email is the primary matching field.

Phone is secondary.

Future portal users should be able to see:

```text
My Service Professionals
```

with all businesses they have used.

---

# 17. SSP Rules

SSP must support:

```text
Estimates
Estimate items
Estimate responses
Invoices
Invoice items
Payments
```

Rules:

* Estimates are free to send.
* Every estimate must tie to a contact.
* Every invoice must tie to a contact.
* Invoices may be created without estimates.
* Accepted estimates create draft invoices for business review.
* Modular SSP users pay $3 per invoice sent.
* Full OS users do not pay the $3 invoice fee.
* Stripe processing fees are passed through.

Future support:

```text
Deposits
Recurring invoices
Recurring service subscriptions
```

---

# 18. KYN Rules

KYN requires SSP.

KYN v1 includes:

```text
Manual expenses
Required receipt uploads
Basic P&L
Revenue from SSP only
```

Expense required fields:

```text
Vendor
Date
Amount
Category
Receipt attachment
```

Do not require bank feeds in v1.

Preserve future support for:

```text
Stripe Financial Connections
Plaid
transaction matching
job-level profitability
enterprise-level reporting
```

---

# 19. Financial Infrastructure Rules

Stripe is the first financial provider.

Use:

```text
business_payment_accounts
```

as the connection point for Stripe Connect and future financial providers.

Do not scatter Stripe Connect account IDs throughout unrelated tables.

Preserve future support for:

```text
Stripe Treasury
Stripe Capital
Stripe Issuing
Stripe Financial Connections
Plaid-style bank feeds
ACH providers
```

Payments should reference `business_payment_accounts` where relevant.

---

# 20. 247SP Rules

247SP includes:

```text
Website
Hosting
Domain purchase through Namecheap API
Lead capture
Lead Hub access
```

Rules:

* One business gets one website.
* One website gets one domain.
* Multiple pages are allowed.
* Multiple websites are not supported for one business.
* Additional lead flow should come from EMD or Enterprise.

Domain ownership rule:

```text
If 247SP purchases the domain, FDV owns the domain.
```

Transfer fee schedule:

```text
0-12 months: $150
13-24 months: $250
25+ months: $350
```

V1 professional email setup:

```text
$25 one-time
manual setup tracking
```

Future:

```text
Vendasta
Google Workspace provisioning
automated DNS setup
```

---

# 21. EMD Rules

EMD leads are exclusive.

V1 EMD leads are manually purchased one at a time.

Purchased EMD leads should create or connect to Lead Hub contacts.

EMD lead purchasing may later evolve into auto-purchasing or a customer-facing marketplace.

---

# 22. TUHWD Rules

Default review flow:

```text
5 stars → external review link
1-4 stars → private feedback form
```

Businesses can customize the threshold.

Examples:

```text
Only 5 stars go public
4-5 stars go public
```

Review activity should connect to Lead Hub.

---

# 23. Notification Rules

Notifications should be a shared platform service.

Support channels:

```text
email
sms
in_app
```

Businesses and users should be able to configure notification preferences.

Examples:

```text
new lead
estimate viewed
estimate accepted
invoice paid
review submitted
appointment requested
job assigned
missed call
```

Do not build separate notification logic inside every module.

---

# 24. File Storage Rules

All uploaded files should be stored in DigitalOcean Spaces.

Use a universal file system where practical.

Examples:

```text
receipts
logos
website images
documents
contracts
estimate attachments
invoice attachments
review attachments
job photos
call recordings
```

Do not store uploaded files directly in the database.

---

# 25. Internal Admin Rules

Internal admin tools are required from day one.

Initial internal roles:

```text
Super Admin
Support
Bookkeeping Staff
Marketing Staff
Sales Staff
Domain/Email Admin
Account Manager
```

Staff should eventually be assignable to:

```text
all businesses
specific businesses
specific enterprise accounts
```

Internal tools should support:

```text
View all businesses
Impersonate business
Manage subscriptions
Manage coupons/discounts
Manage domains
Manage email setup requests
Manage staff assignments
View admin actions
```

Impersonation must be logged.

---

# 26. 7% Club Rules

7% Club is a service opt-in, not a separate application.

It should be supported through:

```text
service engagements
staff assignments
permissions
usage charges
admin tools
```

Example service areas:

```text
customer service
planning
dispatching
bookkeeping
payroll
account management
```

Fee structure:

```text
7% of revenue
plus normal platform fees
```

---

# 27. Scheduling and Field Operations Rules

Do not build scheduling or field operations in v1 unless specifically instructed.

Preserve future support for:

```text
appointment requests
instant booking
availability rules
jobs
dispatching
field tech workflows
Google Maps directions
on-my-way notifications
job photos
job notes
job completion
```

---

# 28. Twilio and VoIP Rules

Do not build VoIP in v1 unless specifically instructed.

Preserve future support for:

```text
business phone numbers
user phone numbers
employee numbers
call routing
SMS
voicemail
call logs
call recording
missed call notifications
```

Twilio is the expected provider.

---

# 29. Brands Rule

UBO may support FDV-owned industry-specific brands.

Examples:

```text
Ultimate Back Office
Landscape Back Office
HVAC Back Office
Cleaning Back Office
```

This is not outside-party white labeling.

The same platform should support different:

```text
logos
themes
domains
onboarding defaults
templates
```

---

# 30. Security Rules

Follow these security principles:

```text
Use prepared statements
Validate input
Escape output
Hash secure tokens
Do not log secrets
Use environment variables for credentials
Restrict business data by business_id
Log admin impersonation
Use HTTPS
```

Do not commit credentials, API keys, or `.env` files to GitHub.

---

# 31. Deployment Rules

Development happens in staging first.

Production deployment happens only after review.

Do not make production-only changes directly on the production server.

Keep staging and production configuration as similar as practical.

Use environment-specific config for:

```text
database credentials
Stripe keys
Namecheap API keys
Twilio keys
Spaces credentials
base URLs
```

---

# 32. Build Order Rules

Codex should build in this order:

## First

```text
Project structure
Environment config
Database connection
Authentication
Users
Businesses
Business users
Roles
Permissions
Modules
Business modules
Activity logs
```

## Second

```text
Lead Hub shell
Contacts
Contact statuses
Notes
Tasks
Files
Notifications
Internal admin basics
```

## Third

```text
Stripe customer/subscription foundation
Business payment accounts
SSP foundation
Customer token links
```

## Later

```text
247SP
EMD
TUHWD
KYN
Scheduling
Field operations
VoIP
Mobile/API
```

Do not build feature modules before the shared foundation is stable.

---

# 33. General Coding Rules

Codex should:

```text
Keep files organized
Write readable code
Add comments where helpful
Avoid overengineering
Use clear function names
Use consistent naming
Keep database access secure
Prefer simple solutions
Preserve future architecture
```

Codex should not:

```text
Introduce new frameworks without approval
Rename core concepts
Create customer/business confusion
Bypass permissions
Hardcode production credentials
Build future modules early
Make assumptions that contradict docs
```
# Codex Environment Constraints

The Codex environment does not have access to the staging server.

The Codex environment does not have:

* PHP CLI
* MySQL CLI
* Apache
* Browser automation
* DigitalOcean access

Do not spend effort attempting:

* php -l validation
* mysql migration execution
* browser verification
* deployment verification

Instead:

* Perform static code review.
* Run git diff --check before commits.
* Verify file paths logically.
* Note when runtime validation must occur on staging.

---

# UBO Web Root Structure

Accounts application document root:

public/accounts

App application document root:

public/app

Rules:

* Customer account pages belong under public/accounts.
* Lead Hub pages belong under public/app.
* 247SP pages belong under public/app/247sp.
* Admin pages belong under public/app/admin.

Never create admin pages under:

public/admin

unless specifically instructed.

Always verify new routes align with the existing document root structure before creating pages.

---

# Staging Validation Responsibility

Runtime validation occurs on staging after deployment.

Codex should:

* Build features.
* Verify file references.
* Verify includes.
* Verify route structure.

Human validation on staging will verify:

* Authentication
* Sessions
* Database migrations
* Rendering
* Navigation
* Permissions

---

# Pull Request Requirements

Every PR summary should include:

* Files added
* Files modified
* Database changes
* Routing changes
* New URLs introduced

This allows staging validation to be performed quickly.

Do not claim runtime verification was performed unless it actually occurred.

Before implementing routes or pages, inspect the existing application structure and ensure new files are created within the correct document root hierarchy.

# Repository Synchronization

Before creating a branch or making any code changes:

1. Check current branch and repository status.

2. If on `main` and the worktree is clean:

```bash
git fetch origin
git checkout main
git pull --ff-only origin main
```

3. If the worktree is not clean:

* Stop immediately.
* Report the status.
* Do not stash changes.
* Do not create commits.
* Do not overwrite files.

4. Verify all required sprint documents exist locally after synchronization.

Examples:

```text
docs/sprint-5.md
docs/sprint-5.5.md
docs/deployment-plan.md
docs/product-structure.md
```

5. Only after synchronization is complete should a sprint branch be created.

---

# Branch Creation Order

Always follow:

```text
Synchronize Main
↓
Read Required Documentation
↓
Create Sprint Branch
↓
Implement Changes
↓
Open Pull Request
```

Never create a sprint branch from an outdated local copy of main.
