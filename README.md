# Ultimate Back Office

Ultimate Back Office is a raw PHP/LAMP business operating platform for service businesses. Sprint 1 builds the Accounts OTP login foundation and the first Lead Hub shell. Sprint 2 adds the business onboarding, service selection, and module activation foundation.

## Environment Configuration

Copy the example PHP environment file:

```bash
cp private/config/env.example.php private/config/env.php
```

On Windows PowerShell:

```powershell
Copy-Item private/config/env.example.php private/config/env.php
```

Edit `private/config/env.php` and fill in:

```php
DB_HOST
DB_PORT
DB_NAME
DB_USER
DB_PASSWORD
DB_SSL_MODE
DB_SSL_CA
APP_ENV
APP_DEBUG
APP_BASE_URL
ACCOUNTS_BASE_URL
SESSION_COOKIE_NAME
SESSION_COOKIE_DOMAIN
```

Keep `private/config/env.php` out of Git. It is ignored because it contains environment-specific credentials.

`SESSION_COOKIE_NAME` and `SESSION_COOKIE_DOMAIN` may stay empty for local development. When the domain is empty, the app derives a shared cookie domain from `APP_BASE_URL` and `ACCOUNTS_BASE_URL` when they are sibling subdomains, such as staging accounts and staging app hosts.

## Database Setup

Create the MySQL database named in `DB_NAME`, then run the Sprint 1 migration manually:

```bash
mysql -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME < database/migrations/001_create_platform_foundation.sql
```

Replace the placeholders with the values from `private/config/env.php`.

DigitalOcean Managed MySQL commonly uses port `25060` and requires SSL. For that setup, set `DB_PORT` to `25060` and set `DB_SSL_MODE` to `REQUIRED`. If you download DigitalOcean's CA certificate, set `DB_SSL_CA` to the full server path for that CA file. Local MySQL can leave `DB_SSL_MODE` and `DB_SSL_CA` empty.

The migration creates the platform foundation tables for users, OTPs, businesses, roles, permissions, modules, payment providers, contacts, notes, tasks, and activity logs. It also seeds the required modules, Stripe payment provider, system roles, and default contact statuses.

Then run the Sprint 2 migration:

```bash
mysql -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME < database/migrations/002_business_foundation.sql
```

The Sprint 2 migration adds business slugs, onboarding status fields, legal structures, categories, sub-services, selected business services, and module activation tracking fields.

Then run the Sprint 3 migration:

```bash
mysql -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME < database/migrations/003_247sp_onboarding.sql
```

The Sprint 3 migration adds the 24/7 Sales Partner onboarding storage tables for setup progress, website configuration, business content, service page content, domain selection, and email mailbox requests.

Then run the Sprint 4 migration:

```bash
mysql -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME < database/migrations/004_247sp_site_generator.sql
```

The Sprint 4 migration adds the 24/7 Sales Partner site generator tables for the starter template, template assignments, generated website records, and generated page records.

Then run the Sprint 5 migration:

```bash
mysql -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME < database/migrations/005_admin_portal.sql
```

The Sprint 5 migration adds `admin_notes`, business suspension/test/internal status fields, and the internal Admin role.

Then run the Sprint 5 admin role assignment migration:

```bash
mysql -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME < database/migrations/006_admin_user_roles.sql
```

The Sprint 5 admin role assignment migration adds `user_roles` for internal platform roles that are not tied to one business.

Then run the Sprint 5.5 website branding migration:

```bash
mysql -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME < database/migrations/007_247sp_branding.sql
```

The Sprint 5.5 migration adds 247SP website branding, service image, and editable content override tables.

Then run the Sprint 6 billing foundation migration:

```bash
mysql -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME < database/migrations/008_billing_foundation.sql
```

The Sprint 6 migration adds local billing foundation tables for `plans`, `subscriptions`, and `payments`. It seeds the 24/7 Sales Partner plan with a $100.00 setup fee and $27.00 monthly fee, then creates trial subscription records for existing businesses with active 247SP access.

Then run the Sprint 7 domain workflow migration:

```bash
mysql -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME < database/migrations/009_domain_automation.sql
```

The Sprint 7 migration adds manual domain workflow tables for `domain_requests`, `domain_assignments`, and `website_domains`. It also creates request records from existing 247SP onboarding domain selections when they exist.

## Testing OTP Login In Staging

1. Insert an active test user into the `users` table.
2. Open the accounts site at `public/accounts/login.php` or the configured accounts web root.
3. Enter the test user's email address.
4. In `APP_ENV=staging` or `APP_ENV=development`, the OTP code is displayed on screen for testing.
5. Submit the email and code at `verify.php`.
6. After verification, the app redirects to the accounts dashboard.

OTP email sending is not configured yet. In production mode, the app does not display the code and shows a placeholder message instead.

## Web Roots

The deployed web roots are:

- `public/accounts`
- `public/app`

`public/accounts` handles OTP login, verification, logout, and account-level dashboard access. `public/app` contains the authenticated Lead Hub shell.

## Business Onboarding

After login, open `public/accounts/business-create.php` or use the Create Business link on the accounts dashboard.

The onboarding wizard saves progress across four steps:

1. Business information, address, physical location flag, and legal structure.
2. One primary category and multiple selected sub-services.
3. Module or tier selection.
4. Confirmation and onboarding completion.

Business slugs are generated automatically from the business name and stored in `businesses.slug`.

## Business Profile Management

Use `public/accounts/business.php` to edit a linked business profile after creation. Editable fields include business name, legal name, phone, email, address, physical location, legal structure, category, and services.

Authenticated users can only create, edit, and complete onboarding for businesses linked to their account through `business_users`.

## Category and Service Structure

Sprint 2 uses:

- `categories` for one primary business category.
- `sub_services` for selectable services under each category.
- `business_sub_services` for the services selected by each business.

Seed data includes common service business categories such as Plumbing, Electrical, HVAC, Landscaping, Cleaning, Roofing, Painting, Handyman, Pest Control, Pool Service, Pressure Washing, Auto Detailing, General Contractor, and Other.

## Module Activation Framework

The onboarding wizard activates records in `business_modules` and records `activated_by_user_id` and `activation_source`.

Rules currently enforced:

- KYN requires SSP unless Full OS or Enterprise is selected.
- Full OS activates Lead Hub, 247SP, EMD, SSP, TUHWD, and KYN.
- Enterprise activates Full OS for the business.
- Standalone module selections include Lead Hub access.

## 24/7 Sales Partner Onboarding

Businesses with an active `247sp` module can open `public/app/247sp/dashboard.php`.

The Sprint 3 onboarding workflow is:

1. Business information.
2. Service area and service-area-business visibility.
3. Primary service category and three service pages.
4. Website content.
5. Domain selection.
6. Email mailbox request.
7. Review and submit.

Submitting onboarding sets `setup_status` to `complete` and website status to `ready_for_build`.

New database tables:

- `247sp_onboarding`
- `247sp_website_configurations`
- `247sp_business_content`
- `247sp_service_pages`
- `247sp_domain_selections`
- `247sp_email_requests`

Status flow:

- Website: `not_started`, `in_progress`, `ready_for_build`, `published` future.
- Domain: `not_selected`, `pending`, `registered` future.
- Email: `not_selected`, `pending`, `active` future.

Sprint 3 stores onboarding data only. It does not generate websites, register domains, provision DNS, provision email, add Stripe billing, add analytics, or generate AI content.

## 24/7 Sales Partner Site Generation

After 247SP onboarding is marked complete, use the 247SP dashboard to generate a private website preview.

The site generator:

- Reads completed Sprint 3 onboarding data.
- Assigns the single `starter_local_service` template, displayed as Starter Local Service.
- Creates one generated website record.
- Creates six generated pages: Home, Service 1, Service 2, Service 3, About, and Contact.
- Stores generated page content as structured JSON.
- Sets website status to `generated` and records `generated_at`.

Regeneration replaces the existing generated pages with new pages built from the current onboarding data.

New database tables:

- `247sp_templates`
- `247sp_template_assignments`
- `247sp_generated_websites`
- `247sp_generated_pages`

Website status flow:

- `not_started`
- `in_progress`
- `ready_for_build`
- `generated`
- `published` future.

The private preview is available at `public/app/247sp/site-preview.php` for authenticated users linked to a business with active 247SP access.

Sprint 4 creates website records and private preview pages only. It does not register domains, modify DNS, provision email, add Stripe billing, add analytics, add media uploads, or generate AI content.

## 24/7 Sales Partner Website Management

Sprint 5.5 adds `public/app/247sp/website-manager.php` for customers with active 247SP access.

The Website Manager supports:

- Logo upload.
- Primary and secondary brand color selection.
- Hero image, about image, and one optional image per service.
- Homepage headline, subheadline, and call-to-action edits.
- About page heading and description edits.
- Contact page heading and description edits.
- Service title and description edits.
- Save & Regenerate Website, which rebuilds the private preview and returns to `site-preview.php`.

New database tables:

- `247sp_website_branding`
- `247sp_website_service_images`
- `247sp_website_content_overrides`

Sprint 5.5 stores uploads locally under browser-accessible app document-root paths:

- `public/app/uploads/logos/`
- `public/app/uploads/hero-images/`
- `public/app/uploads/about-images/`
- `public/app/uploads/service-images/`

Uploads are validated by extension, MIME type, size, and business ownership before paths are stored. Public publishing is still not active.

Sprint 5.5 does not add domain automation, DNS management, email provisioning, Stripe billing, AI content generation, public publishing, blog/CMS/media library functionality, analytics, SEO tooling, Apache changes, DNS changes, SSL changes, server config changes, or credential changes.

## Admin Portal

The internal admin portal lives in `public/app/admin` because `staging-app.ultimatebackoffice.com` uses `public/app` as its document root.

Admin pages:

- `/admin/dashboard.php`
- `/admin/users.php`
- `/admin/user.php`
- `/admin/businesses.php`
- `/admin/business.php`
- `/admin/websites.php`
- `/admin/website.php`
- `/admin/billing.php`

Only active users assigned an internal `Super Admin` or internal `Admin` role through `user_roles` may access these pages. Business-scoped Admin roles do not grant admin portal access. Other authenticated users receive Access Denied.

To manually promote a staging user, replace `{USER_ID}` with the user's ID:

```sql
INSERT INTO user_roles (user_id, role_id)
SELECT {USER_ID}, id
FROM roles
WHERE name = 'Super Admin'
  AND scope = 'internal';
```

The admin dashboard shows platform metrics, recent signups, recent businesses, and recent website generations.

Business controls support:

- Enable and disable modules.
- Suspend and unsuspend businesses.
- Mark a business as a test account.
- Add and view internal admin notes.

Website controls support:

- View generated website details.
- View a read-only asset and branding summary.
- Generate websites from completed 247SP onboarding.
- Regenerate websites from the latest 247SP onboarding data.
- Open the existing private preview.

Billing controls support:

- View 24/7 Sales Partner subscriptions.
- View active, trial, and past-due counts.
- View manual MRR calculated from active subscriptions.
- Manually set subscription status to trial, pending payment, active, past due, or cancelled.

Customer billing visibility lives at `public/accounts/billing.php` and shows each linked business, current plan, setup fee, monthly fee, subscription status, and start date.

Domain controls support:

- View 24/7 Sales Partner domain requests.
- Track requested, pending purchase, active, transferred, expired, and cancelled lifecycle states.
- Store registrar, annual cost, purchase date, and expiration date.
- Assign an active or transferred domain to the business.
- Associate assigned domains with generated 247SP websites through `website_domains`.
- Set website-domain publish status to `ready` when a generated website has an active assigned domain.

Customer domain visibility lives at `public/accounts/domains.php` and shows each linked business domain request, status, assigned domain, registrar, annual cost, purchase date, expiration date, and publish-readiness state.

Sprint 7 adds the admin route `/admin/domains.php` under the app document root. It does not add Namecheap API integration, Cloudflare integration, DNS automation, SSL automation, email provisioning, payment processing, website publishing, Apache changes, DNS changes, SSL changes, server config changes, or credential changes.

Sprint 6 does not add Stripe integration, ACH integration, credit card processing, automatic renewals, automated invoicing, tax calculations, refund workflows, collections workflows, domain automation, email provisioning, Apache changes, DNS changes, SSL changes, server config changes, or credential changes.

Sprint 5 does not add website editing, domain automation, email provisioning, Stripe billing, AI content generation, public website publishing, customer CMS, support ticketing, Apache changes, DNS changes, SSL changes, server config changes, or credential changes.

## Design System

Shared platform styling is maintained in `shared/ui/design-system.css` and published for browsers under each public web root: `public/accounts/assets/css/design-system.css` and `public/app/assets/css/design-system.css`. The shared layout loads `/assets/css/design-system.css`, which resolves inside the active subdomain document root.

Reusable PHP UI helpers live in `shared/ui/components`:

- `buttons.php` for primary and secondary actions.
- `cards.php` for standard content containers.
- `badges.php` for module, status, and role pills.
- `alerts.php` for success, warning, error, and info messages.

Shared layout files live in `shared/ui/layout`:

- `header.php` supports the logo area, current user display, and logout link.
- `sidebar.php` renders simple dashboard navigation.
- `footer.php` renders the shared footer.

Accounts and Lead Hub pages should use these shared styles, components, and layouts before adding product-specific presentation. Product modules should keep business logic separate from these UI helpers.
