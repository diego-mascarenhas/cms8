# Configuración de Mailgun para Laravel

## 1. Instalar el paquete de Mailgun
```bash
composer require mailgun/mailgun-php
composer require symfony/mailgun-mailer
```

## 2. Configurar variables de entorno (.env)
```bash
# Deshabilitar MailBaby
MAILBABY_ENABLED=false

# Configurar Mailgun
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=mg.revisionalpha.com
MAILGUN_SECRET=key-xxxxxxxxxxxxxxxxx
MAILGUN_ENDPOINT=api.mailgun.net

# Mantener configuración SMTP como fallback
MAIL_HOST=relay.mailbaby.net
MAIL_PORT=587
MAIL_USERNAME=mb80474
MAIL_PASSWORD=xxxxxx
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@revisionalpha.com
MAIL_FROM_NAME="Revision Alpha"
```

## 3. Configurar dominio en Mailgun
1. Ir a https://app.mailgun.com/
2. Añadir dominio: mg.revisionalpha.com
3. Configurar registros DNS:
   - TXT: v=spf1 include:mailgun.org ~all
   - CNAME: mg.revisionalpha.com → mailgun.org
   - MX: mxa.mailgun.org, mxb.mailgun.org

## 4. Verificar configuración
```bash
php artisan tinker
>>> config('services.mailgun')
>>> Mail::mailer('mailgun')->to('test@example.com')->send(new TestMail())
```

## 5. Logs detallados en Mailgun
- Dashboard: https://app.mailgun.com/app/logs
- Tiempo real: Logs aparecen inmediatamente
- Tracking: Opens, clicks, bounces automático
