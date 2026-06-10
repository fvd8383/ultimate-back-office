# Ultimate Back Office

Ultimate Back Office is a raw PHP/LAMP business operating platform for service businesses. Sprint 1 builds the Accounts OTP login foundation and the first Lead Hub shell.

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
