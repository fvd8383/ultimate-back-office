You are working on the Ultimate Back Office PHP/LAMP project.

Before writing code, read these files fully:

- docs/database-plan.md
- docs/future-modules.md
- docs/codex-rules.md
- docs/codex-handoff.md

Complete Sprint 1: Accounts, OTP Authentication, and Platform Foundation.

IMPORTANT:
- Do not build 247SP, EMD, SSP, TUHWD, KYN, scheduling, field operations, Twilio, customer portal login, or mobile/API features yet.
- Do not touch Apache config, DNS, SSL, server config, DigitalOcean config, or production credentials.
- Use the existing repo structure.
- Use raw PHP, MySQL, HTML, CSS, and vanilla JavaScript.
- Do not introduce Laravel, Symfony, React, Vue, Angular, Node backend, or any framework.
- Do not use Composer unless absolutely necessary.
- Keep code readable and maintainable for a solo founder.

Current deployed web roots are:

- public/accounts
- public/app

Build files under those paths and the existing private/shared/database structure.

Create or update the following structure:

private/
  config/
    env.example.php
  classes/
    Database.php
    Auth.php
    Otp.php
    Session.php
  views/
    header.php
    footer.php

public/
  accounts/
    index.php
    login.php
    verify.php
    logout.php
    dashboard.php
    assets/
      css/
        app.css
  app/
    index.php
    dashboard.php
    assets/
      css/
        app.css

database/
  migrations/
    001_create_platform_foundation.sql

README.md

Core requirements:

1. Environment loading
- Create private/config/env.example.php.
- Do not commit private/config/env.php.
- Update .gitignore if needed so env.php is ignored.
- env.example.php should clearly show required values:
  DB_HOST
  DB_PORT
  DB_NAME
  DB_USER
  DB_PASSWORD
  APP_ENV
  APP_DEBUG
  APP_BASE_URL
  ACCOUNTS_BASE_URL

2. Database connection
- Use PDO.
- Use prepared statements.
- Use utf8mb4.
- Set ERRMODE_EXCEPTION.
- Do not hard-code database credentials.

3. Authentication model
- Build OTP/magic-code authentication, not password authentication.
- Login page asks for email.
- If the email exists for an active user, generate a 6-digit OTP code.
- Store only a hash of the OTP code in the database.
- OTP expires after 10 minutes.
- Create a verify page where the user enters email + code.
- On successful verification:
  - mark OTP as used
  - regenerate session ID with session_regenerate_id(true)
  - store authenticated user id in session
  - log successful login in user_logins
  - redirect to accounts dashboard.
- For now, display the OTP code on screen in staging/development mode only so we can test without email sending.
- In production mode, do not display OTP. Instead show a placeholder message that email sending is not configured yet.

4. Required database tables
Create database/migrations/001_create_platform_foundation.sql with these tables:

users
- id
- first_name
- last_name
- email unique
- phone nullable
- status
- created_at
- updated_at

user_otps
- id
- user_id
- code_hash
- purpose
- expires_at
- used_at nullable
- ip_address nullable
- user_agent nullable
- created_at

user_logins
- id
- user_id
- login_at
- ip_address nullable
- user_agent nullable

businesses
- id
- business_name
- legal_name nullable
- owner_user_id
- phone
- email
- address_line_1
- address_line_2 nullable
- city
- state
- postal_code
- country
- is_public_physical_location
- legal_structure_id nullable
- primary_category_id nullable
- status
- created_at
- updated_at

business_users
- id
- business_id
- user_id
- role_id nullable
- status
- is_owner
- created_at
- updated_at

employees
- id
- business_id
- user_id nullable
- first_name
- last_name
- email nullable
- phone nullable
- employee_type nullable
- status
- created_at
- updated_at

roles
- id
- name
- scope
- description nullable
- is_system_role
- is_custom
- business_id nullable
- created_at
- updated_at

permissions
- id
- permission_key unique
- name
- description nullable
- module_key nullable
- created_at
- updated_at

role_permissions
- id
- role_id
- permission_id
- created_at

modules
- id
- module_key unique
- name
- description nullable
- is_standalone
- is_active
- created_at
- updated_at

business_modules
- id
- business_id
- module_id
- status
- activated_at nullable
- deactivated_at nullable
- created_at
- updated_at

payment_providers
- id
- provider_key unique
- name
- description nullable
- is_active
- created_at
- updated_at

business_payment_accounts
- id
- business_id
- payment_provider_id
- provider_account_id nullable
- provider_customer_id nullable
- account_type nullable
- status
- charges_enabled
- payouts_enabled
- requirements_due_json nullable
- metadata_json nullable
- created_at
- updated_at

contact_statuses
- id
- business_id nullable
- name
- status_key
- sort_order
- is_default
- is_active
- created_at
- updated_at

contacts
- id
- business_id
- portal_user_id nullable
- first_name
- last_name
- company_name nullable
- email nullable
- phone nullable
- contact_type nullable
- status_id nullable
- source_module_key nullable
- source_detail nullable
- created_by_user_id nullable
- created_at
- updated_at

notes
- id
- business_id
- contact_id nullable
- created_by_user_id nullable
- note_body
- created_at
- updated_at

tasks
- id
- business_id
- contact_id nullable
- assigned_to_user_id nullable
- created_by_user_id nullable
- title
- description nullable
- due_date nullable
- status
- priority nullable
- created_at
- updated_at

activity_logs
- id
- business_id nullable
- enterprise_account_id nullable
- user_id nullable
- contact_id nullable
- module_key nullable
- activity_type
- subject nullable
- description nullable
- metadata_json nullable
- created_at

5. Seed data
The migration should insert seed records for:

modules:
- lead_hub
- 247sp
- emd
- ssp
- tuhwd
- kyn
- full_os
- enterprise

payment_providers:
- stripe

roles:
- Owner
- Admin
- Sales
- Office
- Bookkeeper
- Technician
- Super Admin
- Support
- Bookkeeping Staff
- Marketing Staff
- Sales Staff
- Domain/Email Admin
- Account Manager

contact_statuses:
- New Lead
- Contacted
- Qualified
- Estimate Sent
- Customer
- Inactive
- Lost
- Spam

6. Accounts UI
- public/accounts/index.php redirects to login.php if not authenticated.
- public/accounts/login.php lets a user request OTP.
- public/accounts/verify.php lets a user submit OTP.
- public/accounts/dashboard.php requires authentication.
- public/accounts/dashboard.php shows:
  - logged-in user first name
  - email
  - linked businesses if any
  - clear message if no business is linked yet
- public/accounts/logout.php destroys session and redirects to login.php.

7. App UI
- public/app/index.php redirects to dashboard.php.
- public/app/dashboard.php requires authentication.
- public/app/dashboard.php should be a basic Lead Hub shell.
- If user has no linked business, show a message that business setup is required.
- Do not build module functionality yet.

8. Security
- Use htmlspecialchars() for output.
- Use prepared statements.
- Hash OTP codes.
- Do not store plain OTP codes.
- Use secure session practices.
- Do not expose stack traces when APP_DEBUG=false.
- Keep env.php out of Git.
- Do not hard-code credentials.

9. README
Update README.md with:
- how to copy private/config/env.example.php to private/config/env.php
- how to fill database settings
- how to run the migration manually
- how to test login in staging
- note that OTP email sending is not configured yet

10. Branch and PR
Create a branch named:

sprint-1-otp-platform-foundation

Open a pull request into main.