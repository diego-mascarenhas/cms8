# 🔧 Configuración de Stripe: Sandbox vs Producción

Este documento explica cómo configurar Stripe en dos entornos: **Sandbox (Test)** para desarrollo local y **Live (Producción)** para el servidor real.

---

## 📋 Tabla de Contenidos
1. [Configuración Local (Sandbox)](#configuración-local-sandbox)
2. [Configuración Producción (Live)](#configuración-producción-live)
3. [Crear Productos en Stripe](#crear-productos-en-stripe)
4. [Configurar Webhooks](#configurar-webhooks)
5. [Testing con Tarjetas de Prueba](#testing-con-tarjetas-de-prueba)
6. [Verificación](#verificación)

---

## 🧪 Configuración Local (Sandbox)

### `.env` para Desarrollo Local

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=https://humano.test

# Stripe Test Mode (Sandbox)
STRIPE_KEY=pk_test_XXXXXXXXXXXXXXXXXXXXXXXX
STRIPE_SECRET=sk_test_XXXXXXXXXXXXXXXXXXXXXXXX
STRIPE_WEBHOOK_SECRET=whsec_test_XXXXXXXXXXXXXXXXXXXXXXXX

# Price IDs de TEST (Sandbox) - Obtener de Stripe Dashboard
STRIPE_MAILER_BASIC=price_test_XXXXXXXXXX
STRIPE_MAILER_FOUNDATION=price_test_XXXXXXXXXX
STRIPE_MAILER_SCALE=price_test_XXXXXXXXXX

# Configuración de moneda
CASHIER_CURRENCY=eur
CASHIER_CURRENCY_LOCALE=es_ES
```

### Pasos para obtener las claves de TEST:

1. Ve a [Stripe Dashboard](https://dashboard.stripe.com)
2. **Activa el modo TEST** (toggle arriba a la derecha debe estar en "Test mode")
3. Ve a **Developers > API keys**
4. Copia:
   - **Publishable key**: `pk_test_...` → `STRIPE_KEY`
   - **Secret key**: `sk_test_...` → `STRIPE_SECRET`

---

## 🚀 Configuración Producción (Live)

### `.env` para Servidor de Producción

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://humano.revisionalpha.com

# Stripe Live Mode (Production)
STRIPE_KEY=pk_live_XXXXXXXXXXXXXXXXXXXXXXXX
STRIPE_SECRET=sk_live_XXXXXXXXXXXXXXXXXXXXXXXX
STRIPE_WEBHOOK_SECRET=whsec_XXXXXXXXXXXXXXXXXXXXXXXX

# Price IDs de PRODUCCIÓN (ya configurados)
STRIPE_MAILER_BASIC=price_1SUolyRwN51ygFdec574kfHt
STRIPE_MAILER_FOUNDATION=price_1SUomeRwN51ygFdehZBo2SXd
STRIPE_MAILER_SCALE=price_1SUon4RwN51ygFdeu3gm5bkR

# Configuración de moneda
CASHIER_CURRENCY=eur
CASHIER_CURRENCY_LOCALE=es_ES
```

### Pasos para obtener las claves de LIVE:

1. Ve a [Stripe Dashboard](https://dashboard.stripe.com)
2. **Desactiva el modo TEST** (toggle debe estar en "Live mode")
3. Ve a **Developers > API keys**
4. Copia:
   - **Publishable key**: `pk_live_...` → `STRIPE_KEY`
   - **Secret key**: `sk_live_...` → `STRIPE_SECRET`

---

## 💰 Crear Productos en Stripe

### Para Entorno TEST (Local):

1. Activa **Test mode** en Stripe Dashboard
2. Ve a **Products** → **Add product**
3. Crea 3 productos con precios recurrentes:

#### Product 1: Basic
- **Name**: Email Marketing Basic
- **Description**: Ideal para comenzar
- **Pricing**:
  - Type: Recurring
  - Price: **15.99 EUR**
  - Billing period: Monthly
- Copia el **Price ID**: `price_test_XXXXXXXXXX`

#### Product 2: Foundation
- **Name**: Email Marketing Foundation
- **Description**: Para empresas en crecimiento
- **Pricing**:
  - Type: Recurring
  - Price: **35.99 EUR**
  - Billing period: Monthly
- Copia el **Price ID**: `price_test_XXXXXXXXXX`

#### Product 3: Scale
- **Name**: Email Marketing Scale
- **Description**: Para grandes empresas
- **Pricing**:
  - Type: Recurring
  - Price: **119.99 EUR**
  - Billing period: Monthly
- Copia el **Price ID**: `price_test_XXXXXXXXXX`

4. Actualiza los Price IDs en tu `.env` local:
```env
STRIPE_MAILER_BASIC=price_test_XXXXXXXXXX
STRIPE_MAILER_FOUNDATION=price_test_XXXXXXXXXX
STRIPE_MAILER_SCALE=price_test_XXXXXXXXXX
```

### Para Entorno LIVE (Producción):

Los productos ya están creados con estos Price IDs:
- Basic: `price_1SUolyRwN51ygFdec574kfHt`
- Foundation: `price_1SUomeRwN51ygFdehZBo2SXd`
- Scale: `price_1SUon4RwN51ygFdeu3gm5bkR`

---

## 🔔 Configurar Webhooks

### ⚠️ Importante: Webhooks solo en Staging y Producción

El entorno local **NO tendrá webhooks** debido a restricciones de Firewall. Los webhooks solo se configurarán en:
- **Staging** (Test mode)
- **Producción** (Live mode)

### Para Entorno TEST (Staging):

1. Ve a [Stripe Dashboard (Test mode)](https://dashboard.stripe.com/test/webhooks)
2. Click **Add endpoint**
3. **Endpoint URL**: `https://staging.admin.revisionalpha.com/stripe/webhook`
4. **Nombre del destino**: `staging-humano`
5. **Events to send**:
   - `invoice.payment_succeeded`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
6. Click **Add endpoint**
7. Copia el **Signing secret**: `whsec_test_...`
8. Actualiza `.env` de staging: `STRIPE_WEBHOOK_SECRET=whsec_test_...`

### Para Entorno LIVE (Producción):

1. Ve a [Stripe Dashboard (Live mode)](https://dashboard.stripe.com/webhooks)
2. Click **Add endpoint**
3. **Endpoint URL**: `https://admin.revisionalpha.com/stripe/webhook`
4. **Nombre del destino**: `production-humano`
5. **Events to send**:
   - `invoice.payment_succeeded`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
6. Click **Add endpoint**
7. Copia el **Signing secret**: `whsec_...`
8. Actualiza `.env` de producción: `STRIPE_WEBHOOK_SECRET=whsec_...`

### 🧪 Testing en Local (SIN webhook)

Como el entorno local no tiene webhooks, después de hacer un checkout de prueba en local, debes actualizar el plan manualmente:

```bash
php artisan tinker
```

```php
// Actualizar plan manualmente
$team = auth()->user()->currentTeam;
$team->email_plan = 'basic'; // o 'foundation', 'scale'
$team->save();

// Verificar
echo $team->email_plan;
```

**Recomendación**: Realiza todas las pruebas de flujo completo de suscripción en **Staging** donde el webhook funciona correctamente.

---

## 💳 Testing con Tarjetas de Prueba

**Solo en modo TEST**, usa estas tarjetas:

### Tarjeta Exitosa
```
Número: 4242 4242 4242 4242
Fecha: Cualquier fecha futura (ej: 12/25)
CVC: Cualquier 3 dígitos (ej: 123)
ZIP: Cualquier código postal
```

### Tarjeta que Requiere Autenticación (3D Secure)
```
Número: 4000 0025 0000 3155
Fecha: Cualquier fecha futura
CVC: Cualquier 3 dígitos
```

### Tarjeta Rechazada
```
Número: 4000 0000 0000 0002
Fecha: Cualquier fecha futura
CVC: Cualquier 3 dígitos
```

Más tarjetas de prueba: https://stripe.com/docs/testing#cards

---

## ✅ Verificación

### 1. Verificar Configuración

```bash
# En tu terminal
php artisan tinker
```

```php
// Verificar que la clave esté correcta
echo config('cashier.key');  // Debe empezar con pk_test_ (local) o pk_live_ (prod)
echo config('cashier.secret');  // Debe empezar con sk_test_ (local) o sk_live_ (prod)

// Probar conexión con Stripe
\Stripe\Stripe::setApiKey(config('cashier.secret'));
$price = \Stripe\Price::retrieve(env('STRIPE_MAILER_BASIC'));
echo $price->unit_amount / 100; // Debe mostrar 15.99
```

### 2. Verificar Webhooks

Ve a la página de webhooks en Stripe Dashboard:
- **Test mode**: https://dashboard.stripe.com/test/webhooks
- **Live mode**: https://dashboard.stripe.com/webhooks

Click en tu endpoint y verifica que esté "Enabled"

### 3. Test de Suscripción

1. Ve a https://humano.test/subscription (local) o tu URL de producción
2. Click en "Suscribirse Ahora" en cualquier plan
3. Usa una tarjeta de prueba (solo en test mode)
4. Completa el pago
5. Verifica en Stripe Dashboard que se creó:
   - Customer
   - Subscription
   - Invoice
6. Verifica en tu base de datos:
   - `teams.stripe_id` debe tener valor
   - Debe haber un registro en `subscriptions`

---

## 🔄 Cambiar entre Entornos

### De Test a Live (Producción)

1. Actualiza `.env` con las claves `pk_live_` y `sk_live_`
2. Actualiza `STRIPE_WEBHOOK_SECRET` con el de producción
3. Verifica que los `STRIPE_MAILER_*` sean los Price IDs de producción
4. Limpia cache: `php artisan config:clear`
5. Reinicia servicios: `php artisan optimize`

### De Live a Test (Desarrollo)

1. Actualiza `.env` con las claves `pk_test_` y `sk_test_`
2. Actualiza `STRIPE_WEBHOOK_SECRET` con el de test
3. Actualiza `STRIPE_MAILER_*` con los Price IDs de test
4. Limpia cache: `php artisan config:clear`
5. Reinicia servicios: `php artisan optimize`

---

## 📝 Notas Importantes

- **NUNCA** uses claves de producción en local
- **NUNCA** uses claves de test en producción
- **NUNCA** commitees las claves de Stripe al repositorio
- Los datos de TEST y LIVE están **completamente separados** en Stripe
- Los customers, subscriptions, e invoices de TEST no afectan producción
- Puedes crear/eliminar datos en TEST sin preocupación

---

## 🆘 Troubleshooting

### Error: "No signature found"
- Verifica que `STRIPE_WEBHOOK_SECRET` esté correcto
- Verifica que la URL del webhook en Stripe sea correcta
- Verifica que el endpoint esté excluido de CSRF (ya está en `VerifyCsrfToken.php`)

### Error: "Invalid API Key"
- Verifica que uses `pk_test_` con `sk_test_` (no mezcles test/live)
- Limpia config cache: `php artisan config:clear`

### Webhook no recibe eventos
- Verifica que la URL sea accesible públicamente
- Verifica que no haya restricciones de Firewall
- Revisa logs en Stripe Dashboard → Webhooks → Click en endpoint → "Recent events"
- En local: No hay webhooks, actualiza el plan manualmente con tinker

### Precios no coinciden
- Verifica que los Price IDs en `.env` sean correctos
- Los precios se obtienen desde Stripe API, no desde código
- Revisa logs: `tail -f storage/logs/laravel.log`

---

## 📚 Recursos

- [Stripe Testing](https://stripe.com/docs/testing)
- [Laravel Cashier Docs](https://laravel.com/docs/10.x/billing)
- [Stripe Webhooks](https://stripe.com/docs/webhooks)

---

## 🏗️ Resumen de Arquitectura

```
┌─────────────────────────────────────────────────────────────┐
│                     STRIPE CONFIGURATION                     │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  LOCAL (Development)                                         │
│  ├─ URL: https://humano.test                                │
│  ├─ Stripe Mode: TEST                                       │
│  ├─ Webhook: ❌ NO (Firewall)                               │
│  └─ Testing: Manual con tinker                              │
│                                                              │
│  STAGING (Pre-production)                                    │
│  ├─ URL: https://staging.admin.revisionalpha.com            │
│  ├─ Stripe Mode: TEST                                       │
│  ├─ Webhook: ✅ /stripe/webhook                             │
│  └─ Testing: Completo con tarjetas de prueba                │
│                                                              │
│  PRODUCTION (Live)                                           │
│  ├─ URL: https://admin.revisionalpha.com                    │
│  ├─ Stripe Mode: LIVE                                       │
│  ├─ Webhook: ✅ /stripe/webhook                             │
│  └─ Pagos: Reales con tarjetas reales                       │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```


