# Eventos de Stripe Configurados

## 📋 Eventos que Debe Escuchar el Webhook

Estos son los eventos que tu aplicación está preparada para manejar:

### ✅ Eventos Implementados en `StripeWebhookController`

| Evento | Método | Descripción |
|--------|--------|-------------|
| `invoice.paid` | `handleInvoicePaid()` | Factura marcada como pagada en Stripe (incluye transferencias externas) |
| `invoice.payment_succeeded` | `handleInvoicePaymentSucceeded()` | Pago registrado; sincroniza factura + plan de email + afiliados |
| `invoice.updated` | `handleInvoiceUpdated()` | Refresca factura cuando pasa a paid/void/uncollectible |
| `customer.subscription.deleted` | `handleCustomerSubscriptionDeleted()` | Cuando se cancela/elimina una suscripción |
| `customer.subscription.updated` | `handleCustomerSubscriptionUpdated()` | Cuando cambia el estado de una suscripción |

Los eventos de factura encolan `ProcessStripeInvoiceWebhookJob`: upsert en `invoice_syncs`, import a `invoices` y reconciliación de pago si falta.

### ⏱ Respaldo programado (si el webhook no llega)

| Comando | Frecuencia | Función |
|---------|------------|---------|
| `stripe:sync-invoices` | Cada 10 min | Refresca `invoice_syncs` desde API (incluye `open` obsoletas) |
| `invoice-syncs:import-stripe --reconcile` | Cada 10 min | Importa estado/balance al core |
| `stripe:sync-payments` + `payment-syncs:import-stripe` | Cada ~15 min | Cargos `ch_` → pagos |
| `invoices:reconcile-stripe-collected-payments` | :20 y :50 | Actualiza core desde sync pagado + crea pagos faltantes |

### 📌 Eventos Adicionales Recomendados

Considera agregar estos eventos en el Dashboard de Stripe:

| Evento | Para qué sirve |
|--------|----------------|
| `customer.subscription.created` | Detectar nuevas suscripciones |
| `invoice.payment_failed` | Alertar cuando falla un pago |
| `customer.updated` | Sincronizar datos del cliente |
| `payment_method.attached` | Actualizar método de pago |

---

## 📍 Añadir eventos en el Dashboard (producción)

Si tu webhook solo tiene suscripciones e `invoice.payment_succeeded`, **faltan eventos críticos**. Pasos en modo **Live**:

1. Ir a [https://dashboard.stripe.com/webhooks](https://dashboard.stripe.com/webhooks)
2. Confirmar selector **Live** (no Test)
3. Abrir el endpoint `POST /stripe/webhook` de producción
4. **Edit destination** / **Update details** → **Add events**
5. Categoría **Invoice** → marcar **`invoice.paid`** e **`invoice.updated`**
6. Guardar

### Lista mínima recomendada

**Customer:** `customer.subscription.created`, `.updated`, `.deleted`

**Invoice:** `invoice.paid`, `invoice.payment_succeeded`, `invoice.updated`, `invoice.payment_failed` (opcional)

### Por qué `invoice.paid`

| Tipo de cobro | `payment_succeeded` | `paid` |
|---------------|---------------------|--------|
| Tarjeta / cargo (`ch_`) | Sí | Sí |
| Transferencia externa en Stripe | A veces no | **Sí** |

`invoice.updated` refresca la factura cuando pasa a paid, void o uncollectible.

### Verificar

- Pestaña **Event deliveries** → respuesta **200** en `invoice.paid`
- **Send test webhook** → `invoice.paid` → **200 OK**
- Worker de colas activo (job `ProcessStripeInvoiceWebhookJob`)

Documentación en la app: ruta **`/help/stripe-webhook`**.

---

## 🔧 Configuración en Stripe Dashboard

### Opción 1: Eventos Específicos (Recomendado)

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

