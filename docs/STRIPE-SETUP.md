# Stripe Configuration: Sandbox vs Production

This document explains how to configure Stripe in two environments: **Sandbox (Test)** for local development and **Live (Production)** for the real server.

---

## Table of contents
1. [Local configuration (Sandbox)](#local-configuration-sandbox)
2. [Production configuration (Live)](#production-configuration-live)
3. [Create products in Stripe](#create-products-in-stripe)
4. [Configure webhooks](#configure-webhooks)
5. [Testing with test cards](#testing-with-test-cards)
6. [Verification](#verification)

---

## Local configuration (Sandbox)

### `.env` for local development

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=https://humano.test

# Stripe Test Mode (Sandbox)
STRIPE_KEY=pk_test_XXXXXXXXXXXXXXXXXXXXXXXX
STRIPE_SECRET=sk_test_XXXXXXXXXXXXXXXXXXXXXXXX
STRIPE_WEBHOOK_SECRET=whsec_test_XXXXXXXXXXXXXXXXXXXXXXXX

# TEST Price IDs (Sandbox) - Get from Stripe Dashboard
STRIPE_MAILER_BASIC=price_test_XXXXXXXXXX
STRIPE_MAILER_FOUNDATION=price_test_XXXXXXXXXX
STRIPE_MAILER_SCALE=price_test_XXXXXXXXXX

# Currency configuration
CASHIER_CURRENCY=eur
CASHIER_CURRENCY_LOCALE=es_ES
```

### Steps to get TEST keys:

1. Go to [Stripe Dashboard](https://dashboard.stripe.com)
2. **Enable TEST mode** (toggle in the top right must be "Test mode")
3. Go to **Developers > API keys**
4. Copy:
   - **Publishable key**: `pk_test_...` → `STRIPE_KEY`
   - **Secret key**: `sk_test_...` → `STRIPE_SECRET`

---

## Production configuration (Live)

### `.env` for the production server

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://humano.revisionalpha.com

# Stripe Live Mode (Production)
STRIPE_KEY=pk_live_XXXXXXXXXXXXXXXXXXXXXXXX
STRIPE_SECRET=sk_live_XXXXXXXXXXXXXXXXXXXXXXXX
STRIPE_WEBHOOK_SECRET=whsec_XXXXXXXXXXXXXXXXXXXXXXXX

# PRODUCTION Price IDs (already configured)
STRIPE_MAILER_BASIC=price_1SUolyRwN51ygFdec574kfHt
STRIPE_MAILER_FOUNDATION=price_1SUomeRwN51ygFdehZBo2SXd
STRIPE_MAILER_SCALE=price_1SUon4RwN51ygFdeu3gm5bkR

# Currency configuration
CASHIER_CURRENCY=eur
CASHIER_CURRENCY_LOCALE=es_ES
```

### Steps to get LIVE keys:

1. Go to [Stripe Dashboard](https://dashboard.stripe.com)
2. **Disable TEST mode** (toggle must be "Live mode")
3. Go to **Developers > API keys**
4. Copy:
   - **Publishable key**: `pk_live_...` → `STRIPE_KEY`
   - **Secret key**: `sk_live_...` → `STRIPE_SECRET`

---

## Create products in Stripe

### For the TEST environment (local):

1. Enable **Test mode** in Stripe Dashboard
2. Go to **Products** → **Add product**
3. Create 3 products with recurring prices:

#### Product 1: Basic
- **Name**: Email Marketing Basic
- **Description**: Ideal to get started
- **Pricing**:
  - Type: Recurring
  - Price: **15.99 EUR**
  - Billing period: Monthly
- Copy the **Price ID**: `price_test_XXXXXXXXXX`

#### Product 2: Foundation
- **Name**: Email Marketing Foundation
- **Description**: For growing companies
- **Pricing**:
  - Type: Recurring
  - Price: **35.99 EUR**
  - Billing period: Monthly
- Copy the **Price ID**: `price_test_XXXXXXXXXX`

#### Product 3: Scale
- **Name**: Email Marketing Scale
- **Description**: For large enterprises
- **Pricing**:
  - Type: Recurring
  - Price: **119.99 EUR**
  - Billing period: Monthly
- Copy the **Price ID**: `price_test_XXXXXXXXXX`

4. Update the Price IDs in your local `.env`:
```env
STRIPE_MAILER_BASIC=price_test_XXXXXXXXXX
STRIPE_MAILER_FOUNDATION=price_test_XXXXXXXXXX
STRIPE_MAILER_SCALE=price_test_XXXXXXXXXX
```

### For the LIVE environment (production):

Products are already created with these Price IDs:
- Basic: `price_1SUolyRwN51ygFdec574kfHt`
- Foundation: `price_1SUomeRwN51ygFdehZBo2SXd`
- Scale: `price_1SUon4RwN51ygFdeu3gm5bkR`

---

## Configure webhooks

### Important: Webhooks only on Staging and Production

The local environment **will not have webhooks** due to firewall restrictions. Webhooks are only configured on:
- **Staging** (Test mode)
- **Production** (Live mode)

### For the TEST environment (Staging):

1. Go to [Stripe Dashboard (Test mode)](https://dashboard.stripe.com/test/webhooks)
2. Click **Add endpoint**
3. **Endpoint URL**: `https://staging.admin.revisionalpha.com/stripe/webhook`
4. **Destination name**: `staging-humano`
5. **Events to send**:
   - `invoice.payment_succeeded`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
6. Click **Add endpoint**
7. Copy the **Signing secret**: `whsec_test_...`
8. Update staging `.env`: `STRIPE_WEBHOOK_SECRET=whsec_test_...`

### For the LIVE environment (Production):

1. Go to [Stripe Dashboard (Live mode)](https://dashboard.stripe.com/webhooks)
2. Click **Add endpoint**
3. **Endpoint URL**: `https://admin.revisionalpha.com/stripe/webhook`
4. **Destination name**: `production-humano`
5. **Events to send**:
   - `invoice.payment_succeeded`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
6. Click **Add endpoint**
7. Copy the **Signing secret**: `whsec_...`
8. Update production `.env`: `STRIPE_WEBHOOK_SECRET=whsec_...`

### Local testing (without webhook)

Because the local environment has no webhooks, after a local test checkout you must update the plan manually:

```bash
php artisan tinker
```

```php
// Update plan manually
$team = auth()->user()->currentTeam;
$team->email_plan = 'basic'; // or 'foundation', 'scale'
$team->save();

// Verify
echo $team->email_plan;
```

**Recommendation**: Run full subscription flow tests on **Staging**, where the webhook works correctly.

---

## Testing with test cards

**Only in TEST mode**, use these cards:

### Successful card
```
Number: 4242 4242 4242 4242
Expiry: Any future date (e.g. 12/25)
CVC: Any 3 digits (e.g. 123)
ZIP: Any postal code
```

### Card that requires authentication (3D Secure)
```
Number: 4000 0025 0000 3155
Expiry: Any future date
CVC: Any 3 digits
```

### Declined card
```
Number: 4000 0000 0000 0002
Expiry: Any future date
CVC: Any 3 digits
```

More test cards: https://stripe.com/docs/testing#cards

---

## Verification

### 1. Verify configuration

```bash
# In your terminal
php artisan tinker
```

```php
// Verify the key is correct
echo config('cashier.key');  // Must start with pk_test_ (local) or pk_live_ (prod)
echo config('cashier.secret');  // Must start with sk_test_ (local) or sk_live_ (prod)

// Test connection with Stripe
\Stripe\Stripe::setApiKey(config('cashier.secret'));
$price = \Stripe\Price::retrieve(env('STRIPE_MAILER_BASIC'));
echo $price->unit_amount / 100; // Should show 15.99
```

### 2. Verify webhooks

Go to the webhooks page in Stripe Dashboard:
- **Test mode**: https://dashboard.stripe.com/test/webhooks
- **Live mode**: https://dashboard.stripe.com/webhooks

Click your endpoint and verify it is "Enabled"

### 3. Subscription test

1. Go to https://humano.test/subscription (local) or your production URL
2. Click "Subscribe Now" on any plan
3. Use a test card (test mode only)
4. Complete payment
5. Verify in Stripe Dashboard that these were created:
   - Customer
   - Subscription
   - Invoice
6. Verify in your database:
   - `teams.stripe_id` must have a value
   - There should be a record in `subscriptions`

---

## Switching environments

### From Test to Live (Production)

1. Update `.env` with `pk_live_` and `sk_live_` keys
2. Update `STRIPE_WEBHOOK_SECRET` with the production secret
3. Verify that `STRIPE_MAILER_*` are production Price IDs
4. Clear cache: `php artisan config:clear`
5. Restart services: `php artisan optimize`

### From Live to Test (Development)

1. Update `.env` with `pk_test_` and `sk_test_` keys
2. Update `STRIPE_WEBHOOK_SECRET` with the test secret
3. Update `STRIPE_MAILER_*` with test Price IDs
4. Clear cache: `php artisan config:clear`
5. Restart services: `php artisan optimize`

---

## Important notes

- **NEVER** use production keys locally
- **NEVER** use test keys in production
- **NEVER** commit Stripe keys to the repository
- TEST and LIVE data are **completely separate** in Stripe
- TEST customers, subscriptions, and invoices do not affect production
- You can create/delete TEST data without concern

---

## Troubleshooting

### Error: "No signature found"
- Verify that `STRIPE_WEBHOOK_SECRET` is correct
- Verify that the webhook URL in Stripe is correct
- Verify that the endpoint is excluded from CSRF (already in `VerifyCsrfToken.php`)

### Error: "Invalid API Key"
- Verify that you use `pk_test_` with `sk_test_` (do not mix test/live)
- Clear config cache: `php artisan config:clear`

### Webhook does not receive events
- Verify that the URL is publicly accessible
- Verify that there are no firewall restrictions
- Check logs in Stripe Dashboard → Webhooks → Click endpoint → "Recent events"
- Locally: There are no webhooks; update the plan manually with tinker

### Prices do not match
- Verify that the Price IDs in `.env` are correct
- Prices are fetched from the Stripe API, not from code
- Check logs: `tail -f storage/logs/laravel.log`

---

## Resources

- [Stripe Testing](https://stripe.com/docs/testing)
- [Laravel Cashier Docs](https://laravel.com/docs/10.x/billing)
- [Stripe Webhooks](https://stripe.com/docs/webhooks)

---

## Architecture summary

```
┌─────────────────────────────────────────────────────────────┐
│                     STRIPE CONFIGURATION                     │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  LOCAL (Development)                                         │
│  ├─ URL: https://humano.test                                │
│  ├─ Stripe Mode: TEST                                       │
│  ├─ Webhook: ❌ NO (Firewall)                               │
│  └─ Testing: Manual with tinker                             │
│                                                              │
│  STAGING (Pre-production)                                    │
│  ├─ URL: https://staging.admin.revisionalpha.com            │
│  ├─ Stripe Mode: TEST                                       │
│  ├─ Webhook: ✅ /stripe/webhook                             │
│  └─ Testing: Full flow with test cards                      │
│                                                              │
│  PRODUCTION (Live)                                           │
│  ├─ URL: https://admin.revisionalpha.com                    │
│  ├─ Stripe Mode: LIVE                                       │
│  ├─ Webhook: ✅ /stripe/webhook                             │
│  └─ Payments: Real charges with real cards                  │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```
