# Ultimate Back Office

Ultimate Back Office is a raw PHP/LAMP business operating platform for service businesses. It includes the Accounts area, persistent workspace shell, Lead Hub, 24/7 Sales Partner, admin tools, and the supporting business, billing, domain, email, and website-management foundations.

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

The Sprint 6 migration adds local billing foundation tables for `plans`, `subscriptions`, and `payments`. It seeds the 24/7 Sales Partner plan with a $100.00 setup fee and $47.00 monthly fee, then creates trial subscription records for existing businesses with active 247SP access.

Then run the Sprint 7 domain workflow migration:

```bash
mysql -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME < database/migrations/009_domain_automation.sql
```

The Sprint 7 migration adds manual domain workflow tables for `domain_requests`, `domain_assignments`, and `website_domains`. It also creates request records from existing 247SP onboarding domain selections when they exist.

Then run the 247SP pricing and analytics foundation migration:

```bash
mysql -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME < database/migrations/014_247sp_pricing_analytics.sql
```

The pricing and analytics migration updates the 24/7 Sales Partner monthly fee to $47.00 and adds `website_integrations` per-business storage for Google Analytics, Google Search Console, Google Tag Manager, Microsoft Clarity, Meta Pixel, and Google Business Profile references. `website_integrations` is shared by current and future website-enabled products, not scoped only to 247SP.

If staging previously applied an earlier version of the pricing and analytics migration, run the legacy website integrations rename migration:

```bash
mysql -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME < database/migrations/015_rename_legacy_website_integrations.sql
```

This staging repair migration only renames the legacy table when it exists and `website_integrations` does not already exist.

## Testing OTP Login In Staging

1. Insert an active test user into the `users` table.
2. Open the accounts site at `public/accounts/login.php` or the configured accounts web root.
3. Enter the test user's email address.
4. In `APP_ENV=staging` or `APP_ENV=development`, the OTP code is displayed on screen for testing.
5. Submit the email and code at `verify.php`.
6. After verification, the app redirects to the accounts dashboard.

OTP email sending is not configured yet. In production mode, the app does not display the code and shows a placeholder message instead.

## Staging Test Personas

After all database migrations have been applied on staging, seed stable QA users with:

```bash
mysql -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME < database/staging/create_test_personas.sql
```

These accounts are for staging only. Login still uses the staging OTP flow described above.

- `admin@test.com` (`Internal Admin`): internal `Super Admin`/`Admin` access for `/app/admin/*`; intentionally has no linked customer business.
- `customer@test.com` (`Standard Customer`): standard customer linked to `Customer Test Services`; no Admin Portal access; 247SP and Lead Hub active; 247SP subscription active; 247SP onboarding complete.
- `trial@test.com` (`Trial Customer`): customer linked to `Trial Test Services`; no Admin Portal access; 247SP and Lead Hub active; 247SP subscription trial; 247SP onboarding left in progress for incomplete onboarding QA.
- `suspended@test.com` (`Suspended Customer`): customer linked to `Suspended Test Services`; no Admin Portal access; 247SP and Lead Hub active; 247SP subscription past due; flagged as a test/suspended business while public status stays active so testers can verify warning behavior.

## Web Roots

The deployed web roots are:

- `public/accounts`
- `public/app`

`public/accounts` handles OTP login, verification, logout, and account-level dashboard access. `public/app` contains the authenticated workspace, module, and admin application shell.

## Business Onboarding

After login, open `public/accounts/business-create.php` or use the Create Business link on the accounts dashboard.

The onboarding wizard saves progress across four steps:

1. Business information, address, physical location flag, and legal structure.
2. One primary category and multiple selected sub-services.
3. Module or tier selection.
4. Confirmation and onboarding completion.

Business slugs are generated automatically from the business name and stored in `businesses.slug`.

## Business Profile Management

Use `public/accounts/business.php` to edit a linked business profile after creation. Editable fields include business name, legal name, date business started, phone, email, address, physical location, legal structure, category, and services.

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
- Creates generated pages for Home, active service pages, About, and Contact.
- Stores generated page content as structured JSON.
- Sets website status to `generated` and records `generated_at`.

Regeneration replaces the existing generated pages with new pages built from the current onboarding data and admin-managed active service pages.

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
- Homepage headline, subheadline, stat cards, and call-to-action label/type edits.
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
- `public/app/uploads/page-hero-images/`

Uploads are validated by extension, MIME type, size, and business ownership before paths are stored. Public publishing is still not active.

Sprint 5.5 does not add domain automation, DNS management, email provisioning, Stripe billing, AI content generation, public publishing, blog/CMS/media library functionality, analytics, SEO tooling, Apache changes, DNS changes, SSL changes, server config changes, or credential changes.

Admin-managed service page structure is stored on `247sp_service_pages`. Migration `013_247sp_service_page_management.sql` adds parent service support, sort order, active/inactive status, and stable service slugs so generated website navigation can group service pages under a Services dropdown. Customer Website Manager can edit existing active service content, while add/remove/reorder/sub-service controls remain admin-only.

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
- `/admin/website-editor.php`
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
- Edit 24/7 Sales Partner website branding, page content, service content, and upload assets through the DFY admin site editor.
- Store per-business website integration values for Google Analytics, Google Search Console, Google Tag Manager, Microsoft Clarity, Meta Pixel, and Google Business Profile.
- Edit service page supporting copy, trust cards, and page-specific hero images through the shared 247SP website override model.
- Add, reorder, deactivate, and assign parent services for service/sub-service pages, such as Clogged Drain with Clogged Toilet or Clogged Sink Drain underneath it.
- Edit homepage stat cards and primary/secondary CTA labels and types through the shared 247SP website override model.
- Generate websites from completed 247SP onboarding.
- Regenerate websites from the latest 247SP onboarding data.
- Open the existing private preview.

The DFY admin site editor at `public/app/admin/website-editor.php` reuses the existing 247SP Website Manager storage and generation logic. Internal Admin and Super Admin users can save edits, preview sites, and regenerate private previews without customer impersonation. Regular customer users cannot access admin editor routes.

Business display phone, email, and service area remain sourced from the existing business profile and 247SP onboarding records; the DFY editor shows that context and does not create separate display-field overrides.

The Admin Website Editor is organized around the permanent website settings sections: Branding, Pages, Services, Calls to Action, SEO, Integrations, and Advanced. The current form layout remains in place, but new website settings should be added under the matching section.

SEO settings cover titles, meta descriptions, sitemap, robots, and canonical URL foundations. Current basic SEO setup covers launch-ready content and metadata foundations; sitemap, robots, canonical management, ranking reports, and analytics dashboards are outside this implementation.

Integrations settings cover Google Analytics, Google Search Console, Google Tag Manager, Microsoft Clarity, Meta Pixel, and Google Business Profile. Google Analytics is the only integration rendered into 247SP website output today. The other values are stored for admin reference and are not injected into generated sites.

Website integration values are stored in `website_integrations` so the same foundation can support future website-enabled products without product-specific table names.

24/7 Sales Partner is priced at a $100 setup fee and $47/month. The monthly package includes the 247SP website, Lead Hub access, one business mailbox, basic SEO setup, and Google Analytics tracking. Basic SEO setup means customer-friendly site structure, service-page copy support, page titles, and launch-ready metadata foundations; it does not include Search Console API integration, SEO reporting dashboards, ranking trackers, or ongoing SEO service workflows.

Google Analytics tracking is stored per business website through the admin website editor. When a valid Measurement ID such as `G-XXXXXXXXXX` exists, the 247SP preview and generated/published site rendering include the GA script in the page head. When no Measurement ID exists, the script is omitted. This foundation covers pageview tracking; Search Console API access, Tag Manager rendering, Meta Pixel rendering, Clarity rendering, ranking reports, analytics dashboards, and custom conversion events are outside this scope.

247SP CTA controls support customer-facing primary labels such as Call Now, Request Service, Book Appointment, Instant Quote, Get Estimate, Request Inspection, Apply Now, and Reserve Spot. Secondary labels support Free Estimate, Contact Us, View Pricing, and Learn More.

Only three CTA behaviors are active in Sprint 8.5 closeout:

- `call_now` links to the business phone number.
- `contact_form` routes to the contact page.
- `view_pricing` links to the uploaded pricing list when one exists, otherwise routes to the contact page.

Pricing lists are uploaded through the shared 247SP Website Manager and DFY admin editor upload flow under `public/app/uploads/pricing-lists/`. Supported pricing-list file types are PDF, PNG, JPG/JPEG, and WEBP. Future service-specific forms may collect bedrooms and bathrooms for cleaning, property size for lawn care, and onsite inspection requests for roofing, electrical, and plumbing, but this sprint does not add calculators, scheduling engines, quote engines, application workflows, reservations, checkout, or ecommerce.

Future paid service/SEO page bundles may expose more service page capacity and self-serve page management to customers. This sprint only adds admin-side structure management and does not add bundle billing logic.

Billing controls support:

- View 24/7 Sales Partner subscriptions.
- View active, trial, and past-due counts.
- View manual MRR calculated from active subscriptions.
- Manually set subscription status to trial, pending payment, active, past due, or cancelled.

Customer subscription visibility lives at `public/accounts/subscriptions.php` and shows current subscriptions, product status, monthly price, setup fee, launch readiness status, available products, and support-assisted upgrade or cancellation guidance.

Customer billing visibility lives at `public/accounts/billing.php` and focuses on financial status: current monthly charges, upcoming renewal, payment method status, invoice history, and the launch-readiness payment state. Billing does not collect payment yet; incomplete 24/7 Sales Partner payment readiness routes customers back to Billing from the launch checklist.

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

Account, workspace, admin, and module pages should use these shared styles, components, and layouts before adding product-specific presentation. Product modules should keep business logic separate from these UI helpers.
