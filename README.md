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
```

Keep `private/config/env.php` out of Git. It is ignored because it contains environment-specific credentials.

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

## Design System

Shared platform styling lives in `shared/ui/design-system.css`. It loads Poppins from Google Fonts, sets the global `#F5F5F5` page background, uses `#222222` as the charcoal standard, and defines the Ultimate Back Office brand colors from `docs/brand-guidelines.md`.

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
