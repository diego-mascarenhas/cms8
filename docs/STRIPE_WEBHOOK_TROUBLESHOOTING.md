# Solución de Problemas con Webhooks de Stripe

## 🔍 Problema Identificado

Stripe está intentando enviar eventos a: `https://admin.revisionalpha.com/stripe/webhook`

Pero el webhook configurado actualmente es: `https://staging.admin.revisionalpha.com/stripe/webhook`

**Resultado**: 763 solicitudes fallidas desde el 30 de diciembre de 2025.

---

## ✅ Solución

### Opción 1: Actualizar el Webhook Existente (Recomendado)

1. **Ir al Dashboard de Stripe**:
   - https://dashboard.stripe.com/webhooks

2. **Editar el webhook existente**:
   - Click en el webhook actual: `we_1SitaFRwN51ygFdeZ...`
   - Click en "..." (menú) → "Update details"
   - Cambiar la URL de:
     ```
     https://staging.admin.revisionalpha.com/stripe/webhook
     ```
     A:
     ```
     https://admin.revisionalpha.com/stripe/webhook
     ```

3. **Verificar los eventos suscritos**:
   Asegurarse de que estén activos estos eventos:
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.payment_succeeded`
   - `invoice.payment_failed`

4. **Copiar el Signing Secret**:
   - En la página del webhook, copiar el "Signing secret" (comienza con `whsec_...`)
   - Actualizar en el servidor de producción `.env`:
     ```
     CASHIER_WEBHOOK_SECRET=whsec_tu_secret_aqui
     ```

---

### Opción 2: Crear un Nuevo Webhook

Si prefieres mantener el staging y crear uno nuevo para producción:

1. **Crear nuevo webhook**:
   - Dashboard → Webhooks → "+ Add endpoint"
   - URL: `https://admin.revisionalpha.com/stripe/webhook`
   - Description: "Production - REVISION ALPHA"
   - Version: Latest API version

2. **Seleccionar eventos**:
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.payment_succeeded`
   - `invoice.payment_failed`

3. **Copiar el Signing Secret**:
   - Añadir a `.env` en producción:
     ```
     CASHIER_WEBHOOK_SECRET=whsec_nuevo_secret
     ```

---

## 🧪 Pruebas

### 1. Verificar Configuración Local

```bash
php artisan stripe:test-webhook --check
```

### 2. Listar Webhooks Actuales

```bash
php artisan stripe:test-webhook --list
```

### 3. Probar en Stripe Dashboard

1. Ir al webhook en Stripe Dashboard
2. Click en "Send test webhook"
3. Seleccionar evento: `customer.subscription.updated`
4. Click "Send test webhook"
5. Verificar que retorna **200 OK**

### 4. Verificar Logs en el Servidor

```bash
# En producción
tail -f storage/logs/laravel.log | grep stripe
```

---

## 🔧 Configuración Requerida en Producción

### Archivo `.env`

```env
# Stripe - Production
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
CASHIER_WEBHOOK_SECRET=whsec_...

# Stripe Mailer Plans (Price IDs)
STRIPE_MAILER_BASIC=price_1SUolyRwN51ygFdec574kfHt
STRIPE_MAILER_FOUNDATION=price_1SUomeRwN51ygFdehZBo2SXd
STRIPE_MAILER_SCALE=price_1SUon4RwN51ygFdeu3gm5bkR
```

### Verificar Rutas

```bash
php artisan route:list --path=stripe
```

Debe mostrar:
```
POST      stripe/webhook .... StripeWebhookController@handleWebhook
```

### Verificar CSRF Exempt

En `app/Http/Middleware/VerifyCsrfToken.php`:

```php
protected $except = [
    'stripe/webhook',
];
```

---

## 📊 Monitoreo

### Comando para Verificar Estado

```bash
php artisan stripe:test-webhook --check
```

### Ver Eventos Recientes

```bash
php artisan stripe:test-webhook
# Seleccionar opción: "List recent webhook events"
```

---

## ⚠️ Importante

1. **Plazo**: Stripe deshabilitará el webhook el **8 de enero de 2026** si no se soluciona.

2. **Impacto**:
   - Las suscripciones seguirán funcionando
   - Pero las facturas pueden tardar hasta 3 días en procesarse
   - Los cambios de plan no se sincronizarán automáticamente

3. **Verificación Manual**:
   Si el webhook sigue fallando, se puede sincronizar manualmente con:
   ```bash
   php artisan stripe:sync-subscription
   php artisan stripe:update-users --sync-subscriptions
   ```

---

## 📝 Checklist de Verificación

- [ ] Webhook URL actualizada a producción
- [ ] Signing secret copiado y configurado en `.env`
- [ ] Eventos correctos seleccionados
- [ ] Prueba desde Dashboard exitosa (200 OK)
- [ ] Logs verificados sin errores
- [ ] Comando `stripe:test-webhook --check` sin warnings

---

## 🆘 Si el Problema Persiste

Verificar que el servidor de producción:

1. **Tiene acceso HTTP desde internet**:
   ```bash
   curl https://admin.revisionalpha.com/stripe/webhook
   ```
   Debería retornar error 405 (Method Not Allowed), no timeout.

2. **No tiene firewall bloqueando Stripe IPs**:
   - IPs de Stripe: https://stripe.com/docs/ips

3. **Certificado SSL válido**:
   ```bash
   curl -I https://admin.revisionalpha.com
   ```

4. **Laravel está respondiendo**:
   - Verificar que el sitio esté activo
   - Verificar logs de Nginx/Apache

---

## 🔄 Sincronización Manual (Si es necesario)

Si el webhook tarda en configurarse, sincronizar manualmente:

```bash
# Sincronizar todas las suscripciones
php artisan stripe:sync-subscription

# Sincronizar usuarios y suscripciones
php artisan stripe:update-users --sync-subscriptions

# Ver reporte
php artisan stripe:customer-report
```

