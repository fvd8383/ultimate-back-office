Pending Credentials And Planned Provider Configuration

247SP cohort Price catalogs are environment-specific. After review/merge, staging uses
only the TEST keys and an authorized operator runs
`php scripts/configure-247sp-stripe-prices.php`; production uses only LIVE keys after
separate authorization. The utility does not call Stripe, does not print Price values,
does not change locked subscriptions, and refuses a different populated reference.
Legacy global 247SP Price keys are not authoritative and are not Checkout fallbacks.

STAGING
--------
STRIPE_SECRET_KEY
STRIPE_PUBLISHABLE_KEY
STRIPE_WEBHOOK_SECRET
STRIPE_MODE=test
STRIPE_TEST_247SP_ALPHA_RECURRING_PRICE_ID
STRIPE_TEST_247SP_BETA_RECURRING_PRICE_ID
STRIPE_TEST_247SP_FOUNDING_RECURRING_PRICE_ID
STRIPE_TEST_247SP_FOUNDING_SETUP_PRICE_ID
STRIPE_TEST_247SP_STANDARD_RECURRING_PRICE_ID
STRIPE_TEST_247SP_STANDARD_SETUP_PRICE_ID
STRIPE_SUCCESS_URL
STRIPE_CANCEL_URL
NAMECHEAP_API_USER
NAMECHEAP_API_KEY
NAMECHEAP_USERNAME
NAMECHEAP_CLIENT_IP
NAMECHEAP_SANDBOX
DOMAIN_DEFAULT_REGISTRAR
DOMAIN_TARGET_IPV4
DOMAIN_TARGET_IPV6
DOMAIN_WWW_CNAME
DOMAIN_TXT_VERIFICATION_NAME
DOMAIN_TXT_VERIFICATION_VALUE
DOMAIN_MAIL_MX_HOST
RETELL_API_KEY
RETELL_WEBHOOK_SECRET
RETELL_VOICE_AGENT_ID
RETELL_CHAT_AGENT_ID
TWILIO_ACCOUNT_SID
TWILIO_AUTH_TOKEN
TWILIO_VOICE_APPLICATION_SID
TWILIO_MESSAGING_SERVICE_SID
TWILIO_DEFAULT_FROM_NUMBER
COMMUNICATIONS_DEFAULT_VOICE_PROVIDER
COMMUNICATIONS_DEFAULT_MESSAGING_PROVIDER
COMMUNICATIONS_DEFAULT_CHAT_PROVIDER
COMMUNICATIONS_DEFAULT_TELEPHONY_PROVIDER

PRODUCTION
----------
STRIPE_SECRET_KEY
STRIPE_PUBLISHABLE_KEY
STRIPE_WEBHOOK_SECRET
STRIPE_MODE=live
STRIPE_LIVE_247SP_ALPHA_RECURRING_PRICE_ID
STRIPE_LIVE_247SP_BETA_RECURRING_PRICE_ID
STRIPE_LIVE_247SP_FOUNDING_RECURRING_PRICE_ID
STRIPE_LIVE_247SP_FOUNDING_SETUP_PRICE_ID
STRIPE_LIVE_247SP_STANDARD_RECURRING_PRICE_ID
STRIPE_LIVE_247SP_STANDARD_SETUP_PRICE_ID
STRIPE_SUCCESS_URL
STRIPE_CANCEL_URL
NAMECHEAP_API_USER
NAMECHEAP_API_KEY
NAMECHEAP_USERNAME
NAMECHEAP_CLIENT_IP
NAMECHEAP_SANDBOX
DOMAIN_DEFAULT_REGISTRAR
DOMAIN_TARGET_IPV4
DOMAIN_TARGET_IPV6
DOMAIN_WWW_CNAME
DOMAIN_TXT_VERIFICATION_NAME
DOMAIN_TXT_VERIFICATION_VALUE
DOMAIN_MAIL_MX_HOST
RETELL_API_KEY
RETELL_WEBHOOK_SECRET
RETELL_VOICE_AGENT_ID
RETELL_CHAT_AGENT_ID
TWILIO_ACCOUNT_SID
TWILIO_AUTH_TOKEN
TWILIO_VOICE_APPLICATION_SID
TWILIO_MESSAGING_SERVICE_SID
TWILIO_DEFAULT_FROM_NUMBER
COMMUNICATIONS_DEFAULT_VOICE_PROVIDER
COMMUNICATIONS_DEFAULT_MESSAGING_PROVIDER
COMMUNICATIONS_DEFAULT_CHAT_PROVIDER
COMMUNICATIONS_DEFAULT_TELEPHONY_PROVIDER

Notes:
- Stripe credentials here are for 24/7 Sales Partner customers paying UBO through Stripe Checkout.
- Stripe Connect credentials are not part of the 247SP billing milestone; Connect belongs to future SSP/customer payment-processing work.
- Namecheap credentials are used by Domain Services for 24/7 Sales Partner domain availability checks, registration, DNS reads/writes, and status refreshes.
- Use Namecheap sandbox credentials for staging and production credentials only after staging validation passes.
- Domain target values define the DNS records prepared by Domain Manager; leave optional values blank when they are not ready.
- Retell and Twilio values are planned placeholders only. The communications services and provider adapters are not implemented, and these credentials should not be configured for live use until their implementation and staging-validation milestones.
- Retell credentials will be used by internal UBO communications services for AI Receptionist and Website Chat provider adapters.
- Twilio credentials will be used by internal UBO communications services for local phone number, voice, and SMS provider adapters.
- Provider defaults should name internal adapter keys, such as `retell_voice`, `retell_chat`, `twilio_voice`, and `twilio_messaging`.
- Do not call Retell or Twilio directly from account pages, app pages, admin pages, public website routes, or LeadHub screens. Use internal UBO services and the future CommunicationsManager.
- The future MCP gateway is internal and administrative only. It does not introduce customer-facing MCP credentials or generic database, shell, PHP, or arbitrary-API access.
