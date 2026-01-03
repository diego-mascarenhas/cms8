# Eventos de Stripe Configurados

## 📋 Eventos que Debe Escuchar el Webhook

Estos son los eventos que tu aplicación está preparada para manejar:

### ✅ Eventos Implementados en `StripeWebhookController`

| Evento | Método | Descripción |
|--------|--------|-------------|
| `invoice.payment_succeeded` | `handleInvoicePaymentSucceeded()` | Cuando se paga una factura exitosamente |
| `customer.subscription.deleted` | `handleCustomerSubscriptionDeleted()` | Cuando se cancela/elimina una suscripción |
| `customer.subscription.updated` | `handleCustomerSubscriptionUpdated()` | Cuando cambia el estado de una suscripción |

### 📌 Eventos Adicionales Recomendados

Considera agregar estos eventos en el Dashboard de Stripe:

| Evento | Para qué sirve |
|--------|----------------|
| `customer.subscription.created` | Detectar nuevas suscripciones |
| `invoice.payment_failed` | Alertar cuando falla un pago |
| `customer.updated` | Sincronizar datos del cliente |
| `payment_method.attached` | Actualizar método de pago |

---

## 🔧 Configuración en Stripe Dashboard

### Opción 1: Eventos Específicos (Recomendado)

```
✅ customer.subscription.created
✅ customer.subscription.updated  
✅ customer.subscription.deleted
✅ invoice.payment_succeeded
✅ invoice.payment_failed
✅ customer.updated
```

### Opción 2: Todos los Eventos de Subscripción

```
✅ Select all customer.subscription events
✅ Select all invoice events
```

---

## 🧪 Probar Eventos

### Desde Stripe Dashboard

1. Ir a: https://dashboard.stripe.com/test/webhooks
2. Click en tu webhook
3. Click "Send test webhook"
4. Seleccionar evento a probar
5. Verificar que retorne **200 OK**

### Desde Stripe CLI (Local)

```bash
# Instalar Stripe CLI
brew install stripe/stripe-cli/stripe

# Login
stripe login

# Escuchar webhooks localmente
stripe listen --forward-to https://humano.test/stripe/webhook

# En otra terminal, enviar evento de prueba
stripe trigger customer.subscription.updated
```

---

## 📊 Verificar Logs

### En Producción

```bash
tail -f storage/logs/laravel.log | grep -i "stripe\|webhook"
```

### Logs Esperados

**✅ Evento recibido correctamente:**
```
[2025-01-03 18:00:00] local.INFO: Invoice payment succeeded for team 2
[2025-01-03 18:00:00] local.INFO: Updated team 2 to basic plan
```

**❌ Error - Team no encontrado:**
```
[2025-01-03 18:00:00] local.WARNING: Stripe webhook: Team not found for customer cus_XXX
```

---

## 🐛 Troubleshooting

### Webhook retorna 500

**Posibles causas:**
1. Team no tiene `stripe_id` configurado
2. Error en `getEmailPlanFromProductId()`
3. Error en `assignEmailPlan()`

**Solución:**
```bash
# Ver último error en logs
tail -100 storage/logs/laravel.log | grep ERROR

# Sincronizar manualmente
php artisan stripe:sync-subscription
```

### Webhook retorna 404

**Causa:** Ruta no registrada

**Solución:**
```bash
php artisan route:clear
php artisan route:cache
php artisan route:list --path=stripe
```

### Webhook retorna 419 (CSRF)

**Causa:** Ruta no está en `$except` de `VerifyCsrfToken`

**Solución:** Verificar que existe:
```php
protected $except = [
    'stripe/webhook',
];
```

