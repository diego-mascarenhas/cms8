# Configured Stripe Events

## Events the webhook should listen for

These are the events your application is prepared to handle:

### Events implemented in `StripeWebhookController`

| Event | Method | Description |
|--------|--------|-------------|
| `invoice.paid` | `handleInvoicePaid()` | Invoice marked as paid in Stripe (includes external transfers) |
| `invoice.payment_succeeded` | `handleInvoicePaymentSucceeded()` | Payment recorded; syncs invoice + email plan + affiliates |
| `invoice.updated` | `handleInvoiceUpdated()` | Refreshes invoice when it becomes paid/void/uncollectible |
| `customer.subscription.deleted` | `handleCustomerSubscriptionDeleted()` | When a subscription is canceled/deleted |
| `customer.subscription.updated` | `handleCustomerSubscriptionUpdated()` | When a subscription status changes |

Invoice events enqueue `ProcessStripeInvoiceWebhookJob`: upsert into `invoice_syncs`, import into `invoices`, and payment reconciliation if missing.

### Scheduled backup (if the webhook does not arrive)

| Command | Frequency | Purpose |
|---------|------------|---------|
| `stripe:sync-invoices` | Every 10 min | Refresh `invoice_syncs` from API (includes stale `open` invoices) |
| `invoice-syncs:import-stripe --reconcile` | Every 10 min | Import status/balance into the core |
| `stripe:sync-payments` + `payment-syncs:import-stripe` | Every ~15 min | `ch_` charges → payments |
| `invoices:reconcile-stripe-collected-payments` | :20 and :50 | Update core from paid sync + create missing payments |

### Additional recommended events

Consider adding these events in the Stripe Dashboard:

| Event | Purpose |
|--------|----------------|
| `customer.subscription.created` | Detect new subscriptions |
| `invoice.payment_failed` | Alert when a payment fails |
| `customer.updated` | Sync customer data |
| `payment_method.attached` | Update payment method |

---

## Add events in the Dashboard (production)

If your webhook only has subscriptions and `invoice.payment_succeeded`, **critical events are missing**. Steps in **Live** mode:

1. Go to [https://dashboard.stripe.com/webhooks](https://dashboard.stripe.com/webhooks)
2. Confirm the **Live** selector (not Test)
3. Open the production `POST /stripe/webhook` endpoint
4. **Edit destination** / **Update details** → **Add events**
5. **Invoice** category → select **`invoice.paid`** and **`invoice.updated`**
6. Save

### Recommended minimum list

**Customer:** `customer.subscription.created`, `.updated`, `.deleted`

**Invoice:** `invoice.paid`, `invoice.payment_succeeded`, `invoice.updated`, `invoice.payment_failed` (optional)

### Why `invoice.paid`

| Charge type | `payment_succeeded` | `paid` |
|---------------|---------------------|--------|
| Card / charge (`ch_`) | Yes | Yes |
| External transfer in Stripe | Sometimes no | **Yes** |

`invoice.updated` refreshes the invoice when it becomes paid, void, or uncollectible.

### Verify

- **Event deliveries** tab → **200** response for `invoice.paid`
- **Send test webhook** → `invoice.paid` → **200 OK**
- Queue worker active (`ProcessStripeInvoiceWebhookJob` job)

In-app documentation: route **`/help/stripe-webhook`**.

---

## Configuration in Stripe Dashboard

### Option 1: Specific events (recommended)

```
✅ customer.subscription.created
✅ customer.subscription.updated  
✅ customer.subscription.deleted
✅ invoice.paid
✅ invoice.payment_succeeded
✅ invoice.updated
✅ invoice.payment_failed
✅ customer.updated
```

### Option 2: All subscription events

```
✅ Select all customer.subscription events
✅ Select all invoice events
```

---

## Test events

### From Stripe Dashboard

1. Go to: https://dashboard.stripe.com/test/webhooks
2. Click your webhook
3. Click "Send test webhook"
4. Select the event to test
5. Verify it returns **200 OK**

### From Stripe CLI (local)

```bash
# Install Stripe CLI
brew install stripe/stripe-cli/stripe

# Login
stripe login

# Listen for webhooks locally
stripe listen --forward-to https://humano.test/stripe/webhook

# In another terminal, send a test event
stripe trigger customer.subscription.updated
```

---

## Check logs

### In production

```bash
tail -f storage/logs/laravel.log | grep -i "stripe\|webhook"
```

### Expected logs

**Event received successfully:**
```
[2025-01-03 18:00:00] local.INFO: Invoice payment succeeded for team 2
[2025-01-03 18:00:00] local.INFO: Updated team 2 to basic plan
```

**Error - Team not found:**
```
[2025-01-03 18:00:00] local.WARNING: Stripe webhook: Team not found for customer cus_XXX
```

---

## Troubleshooting

### Webhook returns 500

**Possible causes:**
1. Team does not have `stripe_id` configured
2. Error in `getEmailPlanFromProductId()`
3. Error in `assignEmailPlan()`

**Solution:**
```bash
# View the latest error in logs
tail -100 storage/logs/laravel.log | grep ERROR

# Sync manually
php artisan stripe:sync-subscription
```

### Webhook returns 404

**Cause:** Route not registered

**Solution:**
```bash
php artisan route:clear
php artisan route:cache
php artisan route:list --path=stripe
```

### Webhook returns 419 (CSRF)

**Cause:** Route is not in `$except` of `VerifyCsrfToken`

**Solution:** Verify that this exists:
```php
protected $except = [
    'stripe/webhook',
];
```
