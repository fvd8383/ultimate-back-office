Pending Credentials

STAGING
--------
STRIPE_SECRET_KEY
STRIPE_PUBLISHABLE_KEY
STRIPE_WEBHOOK_SECRET
STRIPE_247SP_PRICE_ID
STRIPE_247SP_SETUP_FEE_PRICE_ID
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

PRODUCTION
----------
STRIPE_SECRET_KEY
STRIPE_PUBLISHABLE_KEY
STRIPE_WEBHOOK_SECRET
STRIPE_247SP_PRICE_ID
STRIPE_247SP_SETUP_FEE_PRICE_ID
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

Notes:
- Stripe credentials here are for 24/7 Sales Partner customers paying UBO through Stripe Checkout.
- Stripe Connect credentials are not part of the 247SP billing milestone; Connect belongs to future SSP/customer payment-processing work.
- Namecheap credentials are used by Domain Services for 24/7 Sales Partner domain availability checks, registration, DNS reads/writes, and status refreshes.
- Use Namecheap sandbox credentials for staging and production credentials only after staging validation passes.
- Domain target values define the DNS records prepared by Domain Manager; leave optional values blank when they are not ready.
