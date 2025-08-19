# 📧 Mailgun Official Setup - Basado en Documentación MCP Context7

## 🎯 **Instalación Correcta del SDK**

### **1. Composer Packages (CRÍTICOS):**
```bash
# Paquetes OBLIGATORIOS para Mailgun + Laravel
composer require mailgun/mailgun-php          # SDK oficial
composer require symfony/mailgun-mailer       # Bridge Laravel (CRÍTICO)
composer require symfony/http-client          # Cliente HTTP
composer require nyholm/psr7                  # PSR-7 support

# Verificar instalación
composer show | grep -E "(mailgun|symfony)"
```

### **🚨 ERROR COMÚN (500): Bridge Faltante**
**Si obtienes este error:**
```
Class "Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory" not found
```

**Solución:** Instalar el bridge de Symfony:
```bash
composer require symfony/mailgun-mailer
php artisan config:clear
```

### **2. Configuración Oficial (Documentación Mailgun):**

#### **Instanciación Correcta:**
```php
# Include the Autoloader
require 'vendor/autoload.php';
use Mailgun\Mailgun;

# Instantiate the client - CORRECTO según documentación
$mgClient = Mailgun::create('PRIVATE_API_KEY', 'https://API_HOSTNAME');
$domain = "YOUR_DOMAIN_NAME";
```

#### **Envío Básico:**
```php
$params = array(
    'from'    => 'Excited User <YOU@YOUR_DOMAIN_NAME>',
    'to'      => 'bob@example.com',
    'subject' => 'Hello',
    'text'    => 'Testing some Mailgun awesomeness!'
);

# Make the call to the client
$result = $mgClient->messages()->send($domain, $params);
```

## ⚙️ **Integración con Laravel (Configuración Correcta):**

### **1. Variables de Entorno (.env):**
```bash
# Mailgun Configuration
MAILGUN_DOMAIN=mg.revisionalpha.com
MAILGUN_SECRET=key-xxxxxxxxxxxxxxxxx
MAILGUN_ENDPOINT=api.mailgun.net

# Email Provider Selection
EMAIL_PROVIDER=mailgun
```

### **2. Uso en Laravel (SendMessageCampaignJob):**
```php
protected function sendViaMailgun()
{
    Log::info('📧 SendMessageCampaignJob: Using Mailgun API', [
        'delivery_id' => $this->messageDelivery->id,
        'mailgun_domain' => config('services.mailgun.domain'),
        'contact_email' => $this->messageDelivery->contact->email,
    ]);

    // Configurar SMTP del equipo para el from_address
    $this->configureMailForTeam($this->messageDelivery->team);

    // IMPORTANTE: Usar el mailer de Laravel, NO el SDK directo
    Mail::mailer('mailgun')
        ->to($this->messageDelivery->contact->email)
        ->send(new MessageDeliveryMail($this->messageDelivery));

    Log::info('✅ SendMessageCampaignJob: Email sent via Mailgun', [
        'delivery_id' => $this->messageDelivery->id,
        'contact_email' => $this->messageDelivery->contact->email,
    ]);

    // Mark as sent
    $this->messageDelivery->update([
        'email_provider' => 'mailgun',
        'sent_at' => now(),
        'status_id' => 2, // 2 = sent
    ]);
}
```

## 🚨 **Posibles Causas del Error 500:**

### **1. Dependencias Faltantes:**
```bash
# Error común: faltan dependencias HTTP
composer require symfony/http-client nyholm/psr7

# Verificar instalación
composer show mailgun/mailgun-php
```

### **2. Configuración Incorrecta:**
```php
// ❌ INCORRECTO - Puede causar error 500
$mg = new Mailgun('YOUR_API_KEY', 'YOUR_DOMAIN', 'YOUR_REGION');

// ✅ CORRECTO según documentación oficial
$mgClient = Mailgun::create('PRIVATE_API_KEY', 'https://API_HOSTNAME');
```

### **3. API Key/Domain Incorrectos:**
```php
// Verificar configuración
$mgClient = Mailgun::create(
    config('services.mailgun.secret'),    // key-xxxxxxxxx
    'https://api.mailgun.net'             // Endpoint oficial
);
```

### **4. Laravel Mail Config:**
```php
// config/mail.php debe tener:
'mailers' => [
    'mailgun' => [
        'transport' => 'mailgun',
    ],
],

// config/services.php ya está configurado ✅
'mailgun' => [
    'domain' => env('MAILGUN_DOMAIN'),
    'secret' => env('MAILGUN_SECRET'),
    'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
],
```

## 🔧 **Fix para Error 500:**

### **1. Verificar Instalación:**
```bash
# Reinstalar con dependencias correctas
composer remove mailgun/mailgun-php
composer require mailgun/mailgun-php symfony/http-client nyholm/psr7
composer dump-autoload
```

### **2. Test Básico (Fuera de Laravel):**
```php
<?php
require 'vendor/autoload.php';
use Mailgun\Mailgun;

try {
    $mgClient = Mailgun::create('key-xxxxxxxxx', 'https://api.mailgun.net');
    $domain = "mg.revisionalpha.com";
    
    $result = $mgClient->messages()->send($domain, [
        'from'    => 'Test <test@mg.revisionalpha.com>',
        'to'      => 'info@revisionalpha.com',
        'subject' => 'Test Mailgun',
        'text'    => 'Testing Mailgun awesomeness!'
    ]);
    
    echo "✅ Success: " . $result->getMessage();
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
```

### **3. Debug en Laravel:**
```php
// En el job, agregar try-catch detallado
try {
    Mail::mailer('mailgun')
        ->to($this->messageDelivery->contact->email)
        ->send(new MessageDeliveryMail($this->messageDelivery));
} catch (\Exception $e) {
    Log::error('❌ Mailgun Error Details', [
        'error_message' => $e->getMessage(),
        'error_code' => $e->getCode(),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine(),
        'mailgun_domain' => config('services.mailgun.domain'),
        'mailgun_secret_set' => !empty(config('services.mailgun.secret')),
    ]);
    throw $e;
}
```

## ✅ **Checklist para Resolver Error 500:**

1. ✅ **Dependencias**: symfony/http-client + nyholm/psr7
2. ✅ **API Key**: Formato correcto key-xxxxx
3. ✅ **Domain**: Configurado en Mailgun dashboard
4. ✅ **Endpoint**: https://api.mailgun.net (sin /v3)
5. ✅ **Laravel Config**: services.mailgun correcto
6. ✅ **From Domain**: Debe coincidir con dominio configurado

## 🎯 **Diferencias Clave vs MailBaby:**

| Aspecto | MailBaby | Mailgun |
|---------|----------|---------|
| **SDK** | API REST manual | SDK oficial PHP |
| **Laravel** | No nativo | Mailer nativo |
| **Config** | Variables custom | services.mailgun |
| **From Domain** | Cualquiera | Debe estar verificado |

## 🔧 **Troubleshooting Completo**

### **🚨 Error 500: MailgunTransportFactory not found**
```bash
# Síntoma:
Class "Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory" not found

# Causa: Falta el bridge de Symfony
# Solución:
composer require symfony/mailgun-mailer
php artisan config:clear
```

### **🚨 Error: API Key inválida**
```bash
# Síntoma: 401 Unauthorized
# Causa: API Key mal configurada
# Verificar:
php artisan tinker
>>> config('services.mailgun.secret')  // Debe empezar con "key-"
```

### **🚨 Error: Domain no configurado**
```bash
# Síntoma: 400 Bad Request
# Causa: Dominio no verificado en Mailgun
# Solución: Verificar dominio en dashboard Mailgun
```

### **🚨 Error: From address rechazado**
```bash
# Síntoma: Email no llega
# Causa: From address no coincide con dominio verificado
# Solución: Usar email del dominio verificado
```

## 🧪 **Testing y Verificación**

### **1. Test de Configuración:**
```bash
# Verificar paquetes instalados
composer show symfony/mailgun-mailer
composer show mailgun/mailgun-php

# Verificar configuración Laravel
php artisan tinker
>>> config('services.mailgun')
>>> config('services.email.provider')
```

### **2. Test Básico (Fuera de Laravel):**
```php
<?php
// test-mailgun.php
require 'vendor/autoload.php';
use Mailgun\Mailgun;

try {
    $mgClient = Mailgun::create(
        'key-xxxxxxxxxxxxxxxxx',  // Tu API key
        'https://api.mailgun.net'
    );
    
    $result = $mgClient->messages()->send('mg.revisionalpha.com', [
        'from'    => 'Test <test@mg.revisionalpha.com>',
        'to'      => 'info@revisionalpha.com',
        'subject' => 'Test Mailgun',
        'text'    => 'Testing Mailgun!'
    ]);
    
    echo "✅ Success: " . $result->getMessage();
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
```

### **3. Test en Laravel (con Logging):**
```php
// En SendMessageCampaignJob, agregar debug detallado
try {
    Log::info('🔧 Testing Mailgun configuration', [
        'mailgun_domain' => config('services.mailgun.domain'),
        'mailgun_secret_configured' => !empty(config('services.mailgun.secret')),
        'email_provider' => config('services.email.provider'),
    ]);

    Mail::mailer('mailgun')
        ->to($this->messageDelivery->contact->email)
        ->send(new MessageDeliveryMail($this->messageDelivery));
        
    Log::info('✅ Mailgun email sent successfully');
} catch (\Exception $e) {
    Log::error('❌ Mailgun Error Details', [
        'error_message' => $e->getMessage(),
        'error_class' => get_class($e),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine(),
    ]);
    throw $e;
}
```

## 📋 **Checklist de Verificación**

### **✅ Antes de Usar Mailgun:**
- [ ] `composer show symfony/mailgun-mailer` existe
- [ ] `composer show mailgun/mailgun-php` existe  
- [ ] API Key empieza con `key-`
- [ ] Dominio verificado en Mailgun dashboard
- [ ] DNS configurado (SPF, DKIM)
- [ ] `EMAIL_PROVIDER=mailgun` en .env
- [ ] From address usa dominio verificado

### **✅ Para Debug de Errores:**
- [ ] Logs de Laravel (`tail -f storage/logs/laravel.log`)
- [ ] Test básico fuera de Laravel funciona
- [ ] Configuración `services.mailgun` correcta
- [ ] Cache limpio (`php artisan config:clear`)

## 🚀 **Setup Completo Paso a Paso**

### **1. Servidor de Producción:**
```bash
ssh forge@servidor
cd proyecto

# Instalar todos los paquetes
composer require mailgun/mailgun-php symfony/mailgun-mailer symfony/http-client nyholm/psr7

# Configurar variables
echo "EMAIL_PROVIDER=mailgun" >> .env
echo "MAILGUN_DOMAIN=mg.tudominio.com" >> .env  
echo "MAILGUN_SECRET=key-tu-api-key" >> .env

# Limpiar cache
php artisan config:clear
php artisan cache:clear

# Test
php artisan tinker
>>> Mail::mailer('mailgun')->to('test@example.com')->send(new TestMail())
```

### **2. Fallback a SMTP si Falla:**
```bash
# Si Mailgun falla, automáticamente usa SMTP
EMAIL_FALLBACK_TO_SMTP=true

# O cambiar manualmente
EMAIL_PROVIDER=smtp
```
