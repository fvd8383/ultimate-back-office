# Ultimate Back Office Database Plan

## Purpose

This document defines the database blueprint for Ultimate Back Office before development begins.

The goal is to give Codex clear direction so the database is built around the correct long-term product model.

This is a planning document, not final SQL.

---

# 1. Product Model

Ultimate Back Office has three commercial levels:

1. Modular products
2. Full OS
3. Enterprise

Lead Hub is the central dashboard and CRM layer for all products.

Every business that purchases any module receives Lead Hub access.

---

## 1.1 Modular Products

Modular users:

* Have one business.
* Have one user.
* Cannot add employees/users.
* Must upgrade to Full OS to add additional users.

Standalone modular products:

```text
24/7 Sales Partner
EMD Network
Super Simple Payments
Tell Us How We Did
Know Your Numbers
```

Rules:

* Lead Hub is included with every purchased module.
* Know Your Numbers requires Super Simple Payments.
* 24/7 Sales Partner includes Lead Hub.
* EMD Network includes Lead Hub.
* Super Simple Payments includes Lead Hub.
* Tell Us How We Did includes Lead Hub.

---

## 1.2 Full OS

Full OS pricing:

```text
$375/month per business
$37/month per additional user
```

Full OS includes:

```text
Lead Hub
24/7 Sales Partner
EMD access
Super Simple Payments
Tell Us How We Did
Know Your Numbers
```

Additional fees:

```text
Stripe processing fees
EMD pay-per-lead charges
```

Full OS does not charge the modular SSP invoice fee.

---

## 1.3 Enterprise

Enterprise pricing:

```text
$1,200/year enterprise fee
$375/month per business
$37/month per additional user
```

Enterprise is the only tier that allows one login to manage multiple businesses.

Each Enterprise location/business is still its own business record.

Enterprise also needs support for parent-level expenses that are not tied to one specific business.

Example:

```text
Parent: Dalba Service Group

Businesses:
- Dalba Plumbing Albany
- Dalba Plumbing Syracuse
- Dalba Plumbing Rochester

Parent-level expense:
- Payroll
- Insurance
- Shared admin
```

---

# 2. Naming Rules

Use:

```text
businesses
```

for paying UBO users.

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

Avoid using:

```text
customers
```

as a database term for UBO-paying accounts.

---

# 3. Core Database Principles

## 3.1 Multi-Tenant Rule

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

## 3.2 Enterprise Rule

A user cannot access multiple businesses unless Enterprise access exists.

## 3.3 Customer Portal Rule

V1 customer interaction uses secure token links.

Long-term, customer portal users may log in and see all UBO businesses they have interacted with.

Example:

```text
My Service Professionals

- ABC Plumbing
- XYZ Electric
- Green Lawn Care
```

## 3.4 API-Ready Rule

UBO v1 is a responsive web app, but the structure should preserve future API support for:

```text
iOS app
Android app
Field tech app
Customer portal app
```

Business logic should be separated from presentation where practical.

---

# 4. Core Platform Tables

## users

Represents platform login users.

Suggested fields:

```text
id
first_name
last_name
email
phone
password_hash nullable
otp_enabled
status
last_login_at
created_at
updated_at
```

Notes:

* V1 authentication should use OTP/magic links.
* Password support may be added later.

---

## businesses

Represents each business using UBO.

Suggested fields:

```text
id
brand_id nullable
business_name
legal_name nullable
owner_user_id
phone
email
address_line_1
address_line_2
city
state
postal_code
country
is_public_physical_location
legal_structure_id
primary_category_id
ein nullable
tax_structure nullable
status
created_at
updated_at
```

Required at signup:

```text
business name
owner name
owner email
phone
full address
whether the address is a public physical location
business type/legal structure
primary business category
```

EIN and tax structure should only be required for businesses using SSP or KYN.

---

## business_users

Connects users to businesses.

Suggested fields:

```text
id
business_id
user_id
employee_id nullable
role_id
status
is_owner
created_at
updated_at
```

Rules:

* Modular accounts may only have one user.
* Full OS and Enterprise accounts may add users.
* Additional users are billed at $37/month/user.

---

## employees

Represents workers/employees of a business.

Employees are separate from users.

Suggested fields:

```text
id
business_id
user_id nullable
first_name
last_name
email nullable
phone nullable
employee_type
status
created_at
updated_at
```

Rules:

* Not every employee needs a login.
* Employees may later be linked to user accounts.
* Employees will support future scheduling, dispatch, payroll, field tech, Twilio numbers, and time tracking.

---

## enterprise_accounts

Represents the parent account for Enterprise users.

Suggested fields:

```text
id
name
primary_owner_user_id
stripe_customer_id
annual_fee_subscription_id
status
created_at
updated_at
```

---

## enterprise_businesses

Connects enterprise accounts to businesses.

Suggested fields:

```text
id
enterprise_account_id
business_id
status
created_at
updated_at
```

---

## enterprise_expenses

Parent-level Enterprise expenses.

Suggested fields:

```text
id
enterprise_account_id
vendor_name
expense_date
amount_cents
expense_category_id nullable
description nullable
created_by_user_id
status
created_at
updated_at
```

Rules:

* Used for parent-level expenses not tied to one business.
* Example: shared payroll, insurance, admin, management costs.

---

# 5. Business Setup Tables

## legal_structures

Examples:

```text
Sole Proprietorship
LLC
Corporation
S Corporation
Partnership
Nonprofit
Other
```

Suggested fields:

```text
id
name
description
is_active
sort_order
created_at
updated_at
```

---

## business_categories

Main business category.

Examples:

```text
Plumbing
Electrical
HVAC
Landscaping
Cleaning
Roofing
Other
```

Suggested fields:

```text
id
name
slug
description
is_active
sort_order
created_at
updated_at
```

Rules:

* Each business selects one primary category.

---

## business_sub_services

Optional services under a main category.

Examples:

```text
Drain Cleaning
Leak Repair
Water Heater Installation
Lawn Mowing
Snow Removal
```

Suggested fields:

```text
id
business_category_id
name
slug
description
is_active
sort_order
created_at
updated_at
```

---

## business_selected_services

Services selected by each business.

Suggested fields:

```text
id
business_id
business_sub_service_id
created_at
```

---

# 6. Product Access and Billing Tables

## modules

Initial modules:

```text
lead_hub
247sp
emd
ssp
tuhwd
kyn
full_os
enterprise
```

Suggested fields:

```text
id
module_key
name
description
is_standalone
is_active
created_at
updated_at
```

---

## business_modules

Controls which modules a business can access.

Suggested fields:

```text
id
business_id
module_id
status
activated_at
deactivated_at nullable
created_at
updated_at
```

Rules:

* Lead Hub is included with every purchased module.
* KYN requires SSP.
* Full OS includes all modules.
* EMD access may be included, but individual leads are still purchased.

---

## subscriptions

Local reference to Stripe subscriptions.

Current Sprint 8.6 implementation uses the `subscriptions` table for 24/7 Sales Partner customers paying UBO through Stripe Checkout. It stores Stripe customer, subscription, checkout session, latest invoice, payment method status, current billing period, and cancellation-at-period-end fields. This is separate from future Stripe Connect records for businesses accepting payments from their own customers.

Suggested fields:

```text
id
business_id nullable
enterprise_account_id nullable
stripe_customer_id
stripe_subscription_id
plan_key
status
current_period_start
current_period_end
cancel_at_period_end
created_at
updated_at
```

---

## subscription_items

Tracks subscription line items.

Suggested fields:

```text
id
subscription_id
module_id nullable
stripe_subscription_item_id
item_type
quantity
unit_price_cents
status
created_at
updated_at
```

Examples:

```text
247SP monthly subscription
TUHWD monthly subscription
KYN monthly subscription
Full OS subscription
Additional users
Enterprise annual fee
```

---

## usage_charges

Tracks usage-based charges.

Suggested fields:

```text
id
business_id
module_id
charge_type
quantity
unit_price_cents
total_cents
stripe_usage_record_id nullable
stripe_invoice_item_id nullable
status
created_at
updated_at
```

Examples:

```text
SSP invoice sent fee
EMD lead purchase
7% Club revenue share
```

Rules:

* SSP estimates are free to send.
* Modular SSP invoices cost $3 per invoice sent.
* Full OS users do not pay the $3 SSP invoice fee.
* Stripe processing fees are passed through.
* EMD leads are purchased separately.
* 7% Club fees may be calculated as a percentage of revenue.

---

## coupons

Local reference to Stripe coupons/promotion codes.

Suggested fields:

```text
id
stripe_coupon_id nullable
stripe_promotion_code_id nullable
code
name
description
discount_type
discount_value
duration
status
created_by_user_id
created_at
updated_at
```

Rules:

* Stripe handles coupon logic.
* UBO stores local references for admin visibility and reporting.

---

## business_discounts

Tracks discounts applied to a business.

Suggested fields:

```text
id
business_id
coupon_id
subscription_id nullable
status
applied_at
expires_at nullable
created_at
updated_at
```

---

# 7. Financial Infrastructure Tables

This section future-proofs UBO for Stripe Connect and later financial products.

Future support may include:

```text
Stripe Treasury
Stripe Capital
Stripe Issuing
Stripe Financial Connections
Plaid
ACH providers
additional payment processors
```

Stripe is the first provider, but the database should not scatter Stripe IDs across unrelated tables.

---

## payment_providers

Initial provider:

```text
stripe
```

Future providers:

```text
plaid
manual
ach_provider
```

Suggested fields:

```text
id
provider_key
name
description
is_active
created_at
updated_at
```

---

## business_payment_accounts

Represents a business connection to a financial provider.

Suggested fields:

```text
id
business_id
payment_provider_id
provider_account_id
provider_customer_id nullable
account_type
status
charges_enabled
payouts_enabled
requirements_due_json nullable
metadata_json nullable
created_at
updated_at
```

For Stripe Connect:

```text
provider_account_id = stripe_connect_account_id
provider_customer_id = stripe_customer_id
```

Rules:

* Each SSP business should eventually have its own Stripe Connect account.
* SSP payments should reference `business_payment_accounts.id`.
* Do not scatter Stripe Connect account IDs throughout unrelated tables.

---

## provider_transactions

Normalized financial transactions from providers.

Suggested fields:

```text
id
business_id
business_payment_account_id
provider_transaction_id
transaction_type
amount_cents
currency
description
transaction_date
status
raw_provider_payload_json nullable
created_at
updated_at
```

Future uses:

```text
Stripe payment transactions
Stripe payout transactions
Stripe Treasury transactions
Stripe Issuing card transactions
Financial Connections imported transactions
Plaid imported transactions
```

---

## linked_bank_accounts

Future support for bank feed integrations.

Suggested fields:

```text
id
business_id
business_payment_account_id nullable
provider_key
provider_account_id
bank_name
account_name
account_last_four
account_type
status
connected_at
disconnected_at nullable
created_at
updated_at
```

Rules:

* V1 does not require bank feeds.
* Full OS should eventually support bank feeds through Stripe, Plaid, or another provider.
* KYN should be able to use linked bank account data later.

---

## bank_transactions

Future imported bank transaction table.

Suggested fields:

```text
id
business_id
linked_bank_account_id
provider_transaction_id
transaction_date
posted_date nullable
description
amount_cents
transaction_type
category_suggestion nullable
status
raw_provider_payload_json nullable
created_at
updated_at
```

Future uses:

```text
auto-categorization
expense matching
revenue matching
bank reconciliation
cash flow dashboard
```

---

## transaction_matches

Future transaction matching table.

Suggested fields:

```text
id
business_id
bank_transaction_id nullable
provider_transaction_id nullable
matched_record_type
matched_record_id
match_type
confidence_score nullable
matched_by_user_id nullable
created_at
updated_at
```

Example matched records:

```text
expense
payment
invoice
payout
refund
```

---

## stripe_financial_products

Optional future table for tracking enabled Stripe products.

Suggested fields:

```text
id
business_id
business_payment_account_id
product_key
status
enabled_at nullable
disabled_at nullable
metadata_json nullable
created_at
updated_at
```

Future product keys:

```text
connect
treasury
capital
issuing
financial_connections
```

---

# 8. Roles and Permissions

## internal_staff_roles

Initial predefined FDV staff roles.

Examples:

```text
Super Admin
Support
Bookkeeping Staff
Marketing Staff
Sales Staff
Domain/Email Admin
Account Manager
```

Internal staff should eventually be assignable to:

```text
all businesses
specific businesses
specific enterprise accounts
```

---

## roles

Suggested default business roles:

```text
Owner
Admin
Sales
Office
Bookkeeper
Technician
```

Suggested internal roles:

```text
Super Admin
Support
Bookkeeping Staff
Marketing Staff
Sales Staff
Domain/Email Admin
Account Manager
```

Suggested fields:

```text
id
name
scope
description
is_system_role
is_custom
business_id nullable
created_at
updated_at
```

Rules:

* V1 uses predefined role-based permissions.
* Custom roles can be added later.

---

## permissions

Suggested fields:

```text
id
permission_key
name
description
module_id nullable
created_at
updated_at
```

Example permissions:

```text
view_dashboard
view_contacts
edit_contacts
view_estimates
create_estimates
send_estimates
view_invoices
create_invoices
send_invoices
view_expenses
create_expenses
view_reviews
manage_reviews
purchase_emd_leads
manage_users
manage_billing
manage_domains
manage_email_setup
manage_scheduling
manage_jobs
manage_phone_system
```

---

## role_permissions

Suggested fields:

```text
id
role_id
permission_id
created_at
```

---

## staff_business_assignments

Assigns internal FDV staff to businesses.

Suggested fields:

```text
id
staff_user_id
business_id nullable
enterprise_account_id nullable
assignment_scope
role_id
status
created_at
updated_at
```

Assignment scopes:

```text
all_businesses
specific_business
specific_enterprise
```

---

# 9. Lead Hub Tables

Lead Hub is the base dashboard and CRM for all modules.

## contacts

Represents a business-specific contact record.

Suggested fields:

```text
id
business_id
portal_user_id nullable
first_name
last_name
company_name nullable
email
phone
contact_type
status_id
source_module_id nullable
source_detail nullable
created_by_user_id nullable
created_at
updated_at
```

Rules:

* Use contacts with statuses.
* Do not create separate leads/customers tables for v1.
* A contact must be tied to a business.
* A contact may later connect to a shared portal user.

---

## contact_statuses

Suggested statuses:

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

Suggested fields:

```text
id
business_id nullable
name
status_key
sort_order
is_default
is_active
created_at
updated_at
```

---

## notes

Suggested fields:

```text
id
business_id
contact_id nullable
created_by_user_id
note_body
created_at
updated_at
```

---

## tasks

Suggested fields:

```text
id
business_id
contact_id nullable
assigned_to_user_id nullable
created_by_user_id
title
description
due_date
status
priority
created_at
updated_at
```

---

## activity_logs

Suggested fields:

```text
id
business_id nullable
enterprise_account_id nullable
user_id nullable
contact_id nullable
module_id nullable
activity_type
subject
description
metadata_json nullable
created_at
```

Examples:

```text
lead_created
estimate_sent
estimate_accepted
invoice_sent
invoice_paid
review_requested
expense_created
domain_purchased
email_setup_requested
appointment_requested
job_completed
call_received
sms_sent
business_impersonated
```

---

# 10. Shared Customer Portal Foundation

V1 customers use secure token links.

Long-term, customers can log in and see all UBO businesses they have interacted with.

## portal_users

Represents customer/client identity across UBO businesses.

Suggested fields:

```text
id
first_name
last_name
email
phone
status
email_verified_at nullable
phone_verified_at nullable
created_at
updated_at
```

Rules:

* Email is the primary matching field.
* Phone is secondary.
* A portal user can interact with multiple UBO businesses.

---

## client_business_relationships

Connects portal users to businesses.

Suggested fields:

```text
id
portal_user_id
business_id
contact_id
relationship_status
first_interaction_at
last_interaction_at
created_at
updated_at
```

---

## customer_portal_tokens

Secure public access tokens for customer-facing actions.

Suggested fields:

```text
id
business_id
portal_user_id nullable
contact_id nullable
related_table
related_id
token_hash
purpose
expires_at
used_at nullable
ip_address nullable
user_agent nullable
created_at
updated_at
```

Example purposes:

```text
view_estimate
respond_to_estimate
view_invoice
pay_invoice
leave_review
private_feedback
appointment_request
```

Rules:

* Store token hashes, not raw tokens.
* V1 uses secure links, not customer passwords.

---

# 11. File Storage Tables

All uploaded files should be stored in DigitalOcean Spaces.

## files

Universal file record.

Suggested fields:

```text
id
business_id nullable
enterprise_account_id nullable
uploaded_by_user_id nullable
file_name
file_url
storage_provider
storage_bucket
storage_key
file_type
mime_type
file_size
status
created_at
updated_at
```

File examples:

```text
receipts
business logos
website images
documents
contracts
estimate attachments
invoice attachments
review attachments
job photos
call recordings
```

---

## file_relationships

Connects files to records.

Suggested fields:

```text
id
file_id
related_table
related_id
relationship_type
created_at
```

Examples:

```text
receipt_attachment
website_image
job_photo
invoice_attachment
estimate_attachment
business_logo
```

---

# 12. Notifications Tables

Notifications should support:

```text
email
sms
in_app
```

Businesses should be able to choose which notifications they receive.

## notification_preferences

Suggested fields:

```text
id
business_id
user_id nullable
event_key
email_enabled
sms_enabled
in_app_enabled
created_at
updated_at
```

Example event keys:

```text
new_lead
estimate_viewed
estimate_accepted
estimate_change_requested
invoice_paid
review_submitted
appointment_requested
job_assigned
call_missed
```

---

## notifications

In-app notification records.

Suggested fields:

```text
id
business_id
user_id nullable
event_key
title
message
related_table nullable
related_id nullable
read_at nullable
created_at
```

---

## notification_templates

Suggested fields:

```text
id
event_key
channel
subject nullable
body_template
status
created_at
updated_at
```

---

## notification_deliveries

Tracks delivery attempts.

Suggested fields:

```text
id
notification_id nullable
business_id
user_id nullable
channel
recipient
status
provider_message_id nullable
error_message nullable
sent_at nullable
created_at
updated_at
```

---

# 13. SSP Tables

## estimates

Suggested fields:

```text
id
business_id
contact_id
estimate_number
title
description
status
subtotal_cents
tax_cents
discount_cents
total_cents
expires_at nullable
sent_at nullable
viewed_at nullable
accepted_at nullable
rejected_at nullable
change_requested_at nullable
converted_invoice_id nullable
created_by_user_id
created_at
updated_at
```

Statuses:

```text
draft
sent
viewed
accepted
change_requested
rejected
converted_to_invoice
expired
```

Rules:

* Estimates are free to send.
* Every estimate must tie to a contact.
* Accepted estimates create a draft invoice for business review.
* Deposits are future support, not v1.

---

## estimate_items

Suggested fields:

```text
id
estimate_id
description
quantity
unit_price_cents
line_total_cents
sort_order
created_at
updated_at
```

---

## estimate_responses

Tracks customer actions on estimates.

Suggested fields:

```text
id
estimate_id
business_id
contact_id
portal_user_id nullable
response_type
message nullable
ip_address nullable
user_agent nullable
created_at
```

Response types:

```text
accepted
change_requested
rejected
```

---

## invoices

Suggested fields:

```text
id
business_id
business_payment_account_id nullable
contact_id
estimate_id nullable
invoice_number
title
description
status
subtotal_cents
tax_cents
discount_cents
total_cents
amount_paid_cents
balance_due_cents
due_date nullable
sent_at nullable
viewed_at nullable
paid_at nullable
created_by_user_id
created_at
updated_at
```

Statuses:

```text
draft
sent
viewed
paid
partially_paid
overdue
void
refunded
```

Rules:

* Every invoice must tie to a contact.
* Invoices can be created without estimates.
* Modular SSP invoices cost $3 per sent invoice.
* Full OS users do not pay the $3 invoice fee.
* Stripe processing fees are passed through.

---

## invoice_items

Suggested fields:

```text
id
invoice_id
description
quantity
unit_price_cents
line_total_cents
sort_order
created_at
updated_at
```

---

## payments

Suggested fields:

```text
id
business_id
business_payment_account_id
invoice_id
contact_id
provider_payment_id nullable
stripe_payment_intent_id nullable
stripe_charge_id nullable
amount_cents
payment_method
status
paid_at nullable
created_at
updated_at
```

Current Sprint 8.6 implementation also uses local `payments` records for UBO billing invoice history. Stripe invoice, payment intent, checkout session, event, and hosted invoice URL references are stored so webhook delivery can update payment status without creating duplicate invoice records.

---

## recurring_invoice_templates

Future support only.

Suggested fields:

```text
id
business_id
contact_id
title
frequency
next_run_date
status
created_at
updated_at
```

Supported future frequencies:

```text
weekly
biweekly
monthly
quarterly
annually
custom
```

---

## deposit_requirements

Future support only.

Suggested fields:

```text
id
business_id
estimate_id nullable
invoice_id nullable
deposit_type
deposit_amount_cents nullable
deposit_percentage nullable
status
created_at
updated_at
```

---

# 14. KYN Tables

KYN is:

```text
$175/month modular add-on
included in Full OS
requires SSP
```

Revenue comes only from SSP invoices/payments.

## expense_categories

Suggested fields:

```text
id
business_id nullable
name
description
is_default
is_active
created_at
updated_at
```

---

## expenses

Suggested fields:

```text
id
business_id
vendor_name
expense_date
amount_cents
expense_category_id
description nullable
created_by_user_id
status
created_at
updated_at
```

Rules:

* Vendor is required.
* Date is required.
* Amount is required.
* Category is required.
* Receipt attachment is required.
* Linking expenses to jobs, invoices, or contacts is future support.

---

## receipts

Suggested fields:

```text
id
business_id
expense_id
file_id
uploaded_by_user_id
created_at
```

---

## profit_loss_snapshots

Suggested fields:

```text
id
business_id
enterprise_account_id nullable
period_start
period_end
revenue_cents
expenses_cents
net_income_cents
generated_at
created_at
```

Rules:

* Business revenue comes from SSP payments.
* Business expenses come from KYN expenses.
* Enterprise-level expenses may be included in Enterprise reporting.

---

# 15. 24/7 Sales Partner Tables

## domains

Suggested fields:

```text
id
business_id
domain_name
registrar
registrar_account_reference nullable
purchase_date
expiration_date nullable
auto_renew
ownership_type
transfer_fee_schedule_key
status
created_at
updated_at
```

Current Sprint 8.6 domain services implementation uses `domain_requests`, `domain_assignments`, `website_domains`, `domain_dns_records`, and `domain_events`.

`domain_requests` tracks the customer/admin workflow and now includes request type, registrar IDs, registrar response JSON, DNS status, DNS verification timestamp, SSL status, next action, last error, and last checked timestamp.

`domain_assignments` tracks the selected domain for the business and now includes registrar, registrar domain ID, ownership type, auto-renew flag, expiration date, and SSL status.

`domain_dns_records` stores the managed DNS plan for A, AAAA, CNAME, TXT, and future MX records. `domain_events` stores availability checks, registrar purchases, DNS syncs, DNS verification, SSL updates, and live-status changes for admin history.

Registrar-specific logic must live behind `RegistrarInterface`; table names should remain registrar-neutral.

Rules:

If 247SP purchases the domain:

```text
FDV owns the domain.

Transfer fee:
0-12 months: $150
13-24 months: $250
25+ months: $350
```

---

## websites

Suggested fields:

```text
id
business_id
domain_id
website_name
template_key
status
published_at nullable
created_at
updated_at
```

Rules:

* One business gets one site.
* One site gets one domain.
* Multiple pages are allowed.
* Multiple websites are not supported for a single business.
* Additional lead flow comes from EMD or Enterprise.

---

## website_pages

Suggested fields:

```text
id
website_id
business_id
page_type
title
slug
content_json
meta_title nullable
meta_description nullable
status
sort_order
created_at
updated_at
```

---

## website_leads

Suggested fields:

```text
id
business_id
website_id
contact_id nullable
name
email
phone
message
source_url
status
created_at
updated_at
```

Rules:

* Website leads should create or connect to Lead Hub contacts.

---

## dns_records

Suggested fields:

```text
id
business_id
domain_id
record_type
host
value
priority nullable
ttl
provider
status
created_at
updated_at
```

---

## email_setup_requests

V1 manual tracking for $25 one-time professional email setup.

Suggested fields:

```text
id
business_id
domain_id
requested_email_address
provider_preference
status
setup_fee_paid
stripe_payment_id nullable
notes nullable
created_at
updated_at
```

Statuses:

```text
requested
in_progress
dns_pending
customer_action_required
completed
cancelled
```

Future support:

```text
Vendasta integration
Google Workspace provisioning
Automated mailbox creation
Automated DNS records
```

---

# 16. EMD Network Tables

## emd_leads

Suggested fields:

```text
id
business_category_id
business_sub_service_id nullable
name
email
phone
service_address_line_1 nullable
service_address_line_2 nullable
city
state
postal_code
message
source_domain
status
created_at
updated_at
```

Rules:

* Leads are exclusive.
* V1 leads are purchased manually one at a time.
* Auto-purchasing may be added later if EMD evolves into a marketplace.

---

## emd_lead_purchases

Suggested fields:

```text
id
emd_lead_id
business_id
purchased_by_user_id
price_cents
stripe_payment_id nullable
status
purchased_at
created_at
updated_at
```

Rules:

* Once purchased, the lead cannot be sold to another business.
* Purchased EMD leads should create or connect to Lead Hub contacts.

---

## emd_service_areas

Suggested fields:

```text
id
business_id
business_category_id
radius_miles
city
state
postal_code
status
created_at
updated_at
```

---

# 17. Tell Us How We Did Tables

## review_settings

Suggested fields:

```text
id
business_id
public_review_threshold
external_review_url
private_feedback_enabled
status
created_at
updated_at
```

Default rule:

```text
5 stars → external review link
1-4 stars → private feedback form
```

Businesses can customize the threshold.

---

## review_requests

Suggested fields:

```text
id
business_id
contact_id
portal_user_id nullable
rating_requested_by_user_id nullable
status
sent_at nullable
opened_at nullable
completed_at nullable
created_at
updated_at
```

---

## review_responses

Suggested fields:

```text
id
review_request_id
business_id
contact_id
portal_user_id nullable
rating
feedback_message nullable
redirected_to_public_review
ip_address nullable
user_agent nullable
created_at
```

---

# 18. Scheduling Tables

Future Full OS support.

Scheduling should support:

```text
instant booking
appointment requests requiring business approval
```

## availability_rules

Suggested fields:

```text
id
business_id
employee_id nullable
day_of_week
start_time
end_time
slot_length_minutes
buffer_minutes
status
created_at
updated_at
```

---

## appointment_requests

Suggested fields:

```text
id
business_id
contact_id
portal_user_id nullable
requested_date
requested_time_window
service_description
status
business_response_message nullable
created_at
updated_at
```

Statuses:

```text
requested
approved
declined
needs_more_info
converted_to_appointment
```

---

## appointments

Suggested fields:

```text
id
business_id
contact_id
portal_user_id nullable
employee_id nullable
title
description
start_time
end_time
status
location_address
created_by_user_id nullable
created_at
updated_at
```

---

# 19. Field Operations Tables

Future Full OS and field tech app support.

## jobs

Suggested fields:

```text
id
business_id
contact_id
appointment_id nullable
estimate_id nullable
invoice_id nullable
title
description
service_address_line_1
service_address_line_2 nullable
city
state
postal_code
status
created_at
updated_at
```

---

## job_assignments

Suggested fields:

```text
id
job_id
business_id
employee_id
assigned_by_user_id nullable
status
created_at
updated_at
```

---

## job_notes

Suggested fields:

```text
id
job_id
business_id
employee_id nullable
user_id nullable
note_body
created_at
updated_at
```

---

## job_photos

Suggested fields:

```text
id
job_id
business_id
file_id
uploaded_by_user_id nullable
uploaded_by_employee_id nullable
created_at
```

---

## job_status_history

Suggested fields:

```text
id
job_id
business_id
status
changed_by_user_id nullable
changed_by_employee_id nullable
created_at
```

Future field tech use:

```text
view jobs for the day
get Google Maps directions
notify customer when on the way
contact customer
take photos
make job notes
complete jobs
```

---

# 20. Communications and VoIP Tables

Future Full OS support using Twilio.

UBO should eventually support a VoIP system similar to RingCentral.

## phone_numbers

Suggested fields:

```text
id
business_id nullable
user_id nullable
employee_id nullable
twilio_phone_number_sid nullable
phone_number
number_type
status
created_at
updated_at
```

Number types:

```text
business_main
user_direct
employee_direct
tracking_number
emd_number
```

---

## call_logs

Suggested fields:

```text
id
business_id
phone_number_id nullable
contact_id nullable
direction
from_number
to_number
duration_seconds nullable
status
recording_file_id nullable
twilio_call_sid nullable
created_at
updated_at
```

---

## sms_messages

Suggested fields:

```text
id
business_id
phone_number_id nullable
contact_id nullable
direction
from_number
to_number
message_body
status
twilio_message_sid nullable
created_at
updated_at
```

---

## voicemail_messages

Suggested fields:

```text
id
business_id
phone_number_id nullable
contact_id nullable
caller_number
transcription_text nullable
recording_file_id nullable
status
created_at
updated_at
```

---

## call_routing_rules

Suggested fields:

```text
id
business_id
phone_number_id
rule_name
routing_type
destination_type
destination_id nullable
status
created_at
updated_at
```

---

# 21. 7% Club Tables

7% Club is a service opt-in where FDV can manage daily operations for a business.

The core architecture already supports this through internal staff assignment and permissions.

## service_engagements

Suggested fields:

```text
id
business_id
engagement_type
fee_type
fee_percentage nullable
flat_fee_cents nullable
status
started_at
ended_at nullable
created_at
updated_at
```

Example:

```text
engagement_type = 7_percent_club
fee_type = revenue_percentage
fee_percentage = 7
```

---

## business_service_assignments

Suggested fields:

```text
id
business_id
staff_user_id
service_area
status
created_at
updated_at
```

Service areas:

```text
customer_service
planning
dispatching
bookkeeping
payroll
account_management
```

---

# 22. Internal Admin Tables

Internal admin tools are required from day one.

## admin_actions

Suggested fields:

```text
id
admin_user_id
business_id nullable
enterprise_account_id nullable
action_type
description
metadata_json nullable
created_at
```

Examples:

```text
subscription_updated
coupon_created
domain_status_updated
email_setup_completed
business_impersonated
staff_assigned
```

---

## impersonation_logs

Suggested fields:

```text
id
admin_user_id
business_id
impersonated_user_id nullable
started_at
ended_at nullable
reason nullable
ip_address nullable
created_at
```

---

## support_notes

Suggested fields:

```text
id
business_id
created_by_user_id
note_body
visibility
created_at
updated_at
```

Visibility examples:

```text
internal_only
visible_to_business
```

---

# 23. Brand and Future Industry Versions

## brands

Supports FDV-owned industry-specific versions.

Examples:

```text
Ultimate Back Office
Landscape Back Office
HVAC Back Office
Cleaning Back Office
```

Suggested fields:

```text
id
name
brand_key
primary_domain
logo_url nullable
theme_json nullable
status
created_at
updated_at
```

Rules:

* This is not outside-party white labeling.
* This is for FDV-owned industry-specific versions.
* Businesses may be associated with a brand.

---

# 24. Mobile and API Future Support

UBO is primarily a responsive web app in v1.

Future apps may include:

```text
iOS app
Android app
Field tech app
Customer portal app
```

Database design should not assume web-only usage.

Future API-related tables may include:

```text
api_tokens
api_clients
mobile_devices
push_notification_tokens
```

These are not required in v1.

---

# 25. Global Audit Fields

Most tables should include:

```text
created_at
updated_at
```

Many business-facing tables should include:

```text
business_id
created_by_user_id
status
```

Security-sensitive records should include:

```text
ip_address
user_agent
```

Soft deletes may be added with:

```text
deleted_at
```

where appropriate.

---

# 26. Development Priorities

## First Tables Codex Should Build

Codex should start with:

```text
users
businesses
business_users
employees
roles
permissions
role_permissions
modules
business_modules
subscriptions
activity_logs
payment_providers
business_payment_accounts
```

Then:

```text
contacts
contact_statuses
notes
tasks
files
file_relationships
notification_preferences
```

Then:

```text
portal_users
client_business_relationships
customer_portal_tokens
```

Feature-specific tables should come after the platform foundation is working.

---

# 27. Codex Database Rules

Codex must follow these rules:

1. Do not create a table called `customers` for UBO-paying accounts.
2. Use `businesses` for UBO-paying accounts.
3. Use `contacts` for the people or companies a business serves.
4. Use `portal_users` for future customer-facing login identities.
5. Build all business-facing records around `business_id`.
6. Do not allow modular accounts to add additional users.
7. Do not allow multi-business access unless Enterprise is enabled.
8. Each Enterprise location/business is its own business record.
9. Enterprise may also have parent-level expenses.
10. Keep Lead Hub as the center of the application.
11. Design customer interactions around secure token links in v1.
12. Preserve future support for full customer portal login.
13. Preserve future support for customers interacting with multiple businesses.
14. Preserve future support for Vendasta/Google Workspace integration.
15. Preserve future support for recurring invoices, deposits, scheduling, and service subscriptions.
16. Use Stripe as the initial billing and payment source of truth.
17. Use `business_payment_accounts` as the connection point for Stripe Connect and future financial providers.
18. Do not scatter Stripe Connect account IDs throughout unrelated tables.
19. Preserve future support for Stripe Treasury, Capital, Issuing, Financial Connections, and Plaid-style bank feeds.
20. KYN v1 uses manual expenses and SSP revenue only.
21. Store all uploaded files in DigitalOcean Spaces.
22. Use a universal file table instead of one-off file columns where practical.
23. Build notification support as a shared platform layer.
24. Separate employees from users.
25. Not every employee needs a login.
26. Preserve future support for Twilio phone numbers, SMS, call routing, voicemail, and call logs.
27. Preserve future support for field tech workflows.
28. Preserve future support for mobile apps and APIs.
29. Keep the schema understandable and maintainable for a solo founder.

Enterprise is an account-level plan, not a business module.

Future architecture:

accounts
  └─ account_plans

account_plans
  ├─ modular
  ├─ full_os
  └─ enterprise

businesses
  └─ linked to account
