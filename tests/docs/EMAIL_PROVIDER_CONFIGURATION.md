# 📧 Configuración de Proveedores de Email

## 🎯 **Sistema Flexible de Proveedores**

Ahora puedes **elegir el gateway de salida** mediante una variable de entorno, con **fallback automático** a SMTP si el proveedor seleccionado no está configurado.

## ⚙️ **Variables de Configuración**

### **Variable Principal:**
```bash
EMAIL_PROVIDER=smtp  # mailbaby | mailgun | smtp
```

### **Fallback Automático:**
```bash
EMAIL_FALLBACK_TO_SMTP=true  # true | false
```

## 🚀 **Configuraciones por Proveedor:**

### **1. MailBaby API:**
```bash
EMAIL_PROVIDER=mailbaby

# MailBaby Configuration
MAILBABY_ENABLED=true
MAILBABY_API_KEY=ieLBmOZ5zJEoGLg5tRXdxKtYJoIygXT372NcUIJBUrkgbOBFSOx4jtv85d3bqzm4SlTK8UW7tfwbYjA6mLpUJ1j4yR58Me9LIF54xSijRw1amgqM3ItD9gwq7HE8Ek12
MAILBABY_API_URL=https://api.mailbaby.net
MAILBABY_WEBHOOK_SECRET=mySecret
```

### **2. Mailgun API:**
```bash
EMAIL_PROVIDER=mailgun

# Mailgun Configuration
MAILGUN_DOMAIN=mg.revisionalpha.com
MAILGUN_SECRET=key-xxxxxxxxxxxxxxxxx
MAILGUN_ENDPOINT=api.mailgun.net
```

### **3. SMTP Tradicional:**
```bash
EMAIL_PROVIDER=smtp

# SMTP Configuration (también usado como fallback)
MAIL_HOST=relay.mailbaby.net
MAIL_PORT=587
MAIL_USERNAME=mb80474
MAIL_PASSWORD=xxxxxx
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@revisionalpha.com
MAIL_FROM_NAME="Revision Alpha"
```

## 🔄 **Lógica de Fallback:**

```bash
┌─ EMAIL_PROVIDER=mailgun ────┐    ┌─ ¿Mailgun configurado? ──┐
│ 1. Intenta Mailgun          │ → │ SÍ: Usa Mailgun          │
│ 2. Si falla y fallback=true │    │ NO: Usa SMTP             │
│ 3. Usa SMTP                 │    └──────────────────────────┘
└─────────────────────────────┘
```

## 📊 **Logs Detallados:**

### **Logs de Configuración:**
```bash
🔧 SendMessageCampaignJob: Email provider configuration
{"email_provider":"mailgun","fallback_to_smtp":true}

📧 SendMessageCampaignJob: Using Mailgun API
{"mailgun_domain":"mg.revisionalpha.com"}

✅ SendMessageCampaignJob: Email sent via Mailgun
```

### **Logs de Fallback:**
```bash
⚠️  Mailgun not configured, falling back to SMTP
{"delivery_id":1}

📧 SendMessageCampaignJob: Using SMTP
{"team_has_custom_smtp":false}
```

## 🎯 **Casos de Uso:**

### **Cambio Rápido de Proveedor:**
```bash
# Cambiar de MailBaby a Mailgun
sed -i 's/EMAIL_PROVIDER=mailbaby/EMAIL_PROVIDER=mailgun/' .env
php artisan config:clear
php artisan queue:restart
```

### **Testing Different Providers:**
```bash
# Test con SMTP
EMAIL_PROVIDER=smtp php artisan smtp:diagnose --team=1 --send-test

# Test con Mailgun
EMAIL_PROVIDER=mailgun php artisan smtp:diagnose --team=1 --send-test
```

### **Desactivar Fallback (Modo Estricto):**
```bash
EMAIL_PROVIDER=mailgun
EMAIL_FALLBACK_TO_SMTP=false
# Si Mailgun falla, el job lanza excepción
```

## 🚨 **Troubleshooting:**

### **Error: Provider not configured**
```bash
# Solución: Verificar configuración
php artisan tinker
>>> config('services.mailgun.secret')
>>> config('services.email.provider')
```

### **Error: SMTP fallback failed**
```bash
# Solución: Verificar SMTP básico
php artisan smtp:diagnose --team=1
```

## ✅ **Ventajas del Nuevo Sistema:**

1. **🔄 Flexibilidad:** Cambio fácil entre proveedores
2. **🛡️ Fallback:** Sin downtime si un proveedor falla
3. **📊 Logging:** Visibilidad completa del proceso
4. **⚡ Performance:** Cada proveedor optimizado
5. **🎯 Testing:** Test independiente por proveedor

## 🔗 **Comandos Útiles:**

```bash
# Ver configuración actual
php artisan tinker --execute="echo config('services.email.provider')"

# Cambiar proveedor temporalmente
EMAIL_PROVIDER=mailgun php artisan queue:work --once

# Diagnosticar configuración
php artisan smtp:diagnose --team=1 --verbose

# Test completo
php artisan smtp:diagnose --team=1 --send-test
```
