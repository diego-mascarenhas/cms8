# 🐛 Newsletter System - Debugging Guide

## 📧 **Proceso Completo de Envío**

### **1. Flujo desde Click "Send":**

```mermaid
graph TD
    A[Click Send] --> B[MessageController::startCampaign]
    B --> C[populateMessageDeliveries]
    C --> D[SendMessageCampaignJob::dispatch]
    D --> E[Job ejecuta: SendMessageCampaignJob::handle]
    E --> F[MessageDeliveryMail::build]
    F --> G[getHtmlForContact - variables reemplazadas]
    G --> H[Mail::to($email)->send()]
    H --> I[Email enviado + logs]
```

### **2. Selección de Template:**

```php
// En MessageDelivery::getHtmlForContact()
$templateHtml = $this->message && $this->message->template && isset($this->message->template->gjs_data['html'])
    ? $this->message->template->gjs_data['html']  // ← Template del Message
    : '';
```
**Template se obtiene de:** `Message → Template → gjs_data['html']`

### **3. Reemplazo de Variables:**

```php
// En MessageDelivery::getHtmlForContact()
$contactName = $this->contact ? $this->contact->name : '';
$html = str_replace('{{name}}', $contactName, $templateHtml);  // ← Reemplazo simple

// También añade tracking pixel:
$trackingImg = '<img src="' . $this->getTrackingUrl() . '" width="1" height="1" style="display:none;" alt="" />';
```

### **4. Construcción del Email:**

```php
// En MessageDeliveryMail::build()
$subject = $this->delivery->message ? $this->delivery->message->name : 'Newsletter';
$html = $this->delivery->getHtmlForContact();  // ← HTML con variables reemplazadas
$inliner = new CssToInlineStyles();
$htmlInlined = $inliner->convert($html, $css);  // ← CSS inline
```

---

## 🚨 **PROBLEMA IDENTIFICADO**

### **Tu configuración .env actual:**
```env
#MAIL_MAILER=smtp
MAIL_MAILER=smtp  
MAIL_MAILER=log    ← ⚠️ ESTA LÍNEA SOBRESCRIBE TODO
```

**Resultado:** Los emails van a `storage/logs/laravel.log` en lugar de SMTP real.

---

## ✅ **SOLUCIÓN**

### **1. Limpiar .env:**
```env
# Remover líneas duplicadas, dejar solo:
MAIL_MAILER=smtp
```

### **2. Verificar configuración SMTP:**
```env
MAIL_HOST=pro3.mail.ovh.net
MAIL_USERNAME=webmaster@revisionalpha.cloud
MAIL_PASSWORD=@PabloHDP
MAIL_FROM_ADDRESS=webmaster@revisionalpha.cloud
MAIL_FROM_NAME="Humano.App"
```

### **3. Limpiar cache:**
```bash
php artisan config:clear
```

---

## 🔍 **Proceso de Debugging**

### **1. Verificar Environment:**
```bash
php artisan tinker
>>> config('mail.default')  // Debe ser "smtp"
>>> config('mail.from.address')  // Debe ser tu email
```

### **2. Verificar Template y Variables:**
```bash
php artisan tinker
>>> $delivery = App\Models\MessageDelivery::first()
>>> $delivery->getHtmlForContact()  // Ver HTML final
```

### **3. Test SMTP Manual:**
```bash
php artisan tinker
>>> Mail::raw('Test email', function($m) { $m->to('tu-email@test.com')->subject('Test'); });
```

### **4. Ver Logs Detallados:**
```bash
tail -f storage/logs/laravel.log | grep -E "Message delivery|Failed|Error"
```

---

## 📊 **Estado Actual de tu Sistema**

### **✅ Lo que SÍ funciona:**
- ✅ **Proceso completo**: Jobs ejecutan correctamente
- ✅ **Template**: Se obtiene de `Message.template.gjs_data.html`
- ✅ **Variables**: `{{name}}` se reemplaza por `contact.name`
- ✅ **Tracking**: Pixel insertado correctamente
- ✅ **Logs**: "Message delivery sent successfully"

### **❌ Lo que NO funciona:**
- ❌ **SMTP**: `MAIL_MAILER=log` envía a logs, no emails reales
- ❌ **Configuración**: Múltiples líneas MAIL_MAILER

---

## 🎯 **Template y Variables - Funcionamiento**

### **Template Selection:**
```
Message ID: 2 
→ Template: "Demo" 
→ HTML: '<body><div class="gjs-row">Bienvenido <b>{{name}}</b>, esta es un envío de prueba.</div></body>'
```

### **Variable Replacement:**
```
Contact: "Revision Alpha"
HTML Before: "Bienvenido <b>{{name}}</b>"
HTML After:  "Bienvenido <b>Revision Alpha</b>"
```

### **Final Output:**
```html
<body>
<div class="gjs-row">
  <div class="gjs-cell">
    <div id="ix12">Bienvenido <b>Revision Alpha</b>, esta es un envío de prueba.</div>
  </div>
</div>
<img src="https://humano.test/message/track/TOKEN" width="1" height="1" style="display:none;" alt="">
</body>
```

---

## 🚀 **Fix Rápido**

### **Ejecutar:**
```bash
# 1. Limpiar .env (remover MAIL_MAILER=log)
# 2. Dejar solo: MAIL_MAILER=smtp
# 3. Limpiar cache
php artisan config:clear

# 4. Verificar
php artisan tinker
>>> config('mail.default')  // Debe mostrar "smtp"

# 5. Reenviar campaña
# Ir a la UI y hacer click "Send" nuevamente
```

## 🎉 **Resultado: Emails llegarán a bandejas reales!**
