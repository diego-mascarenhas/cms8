# 📧 Opciones para Recibir Emails Reales

## ✅ **OPCIÓN 1: MailTrap (Recomendado para Testing)**
```bash
# En .env:
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=tu_username_mailtrap
MAIL_PASSWORD=tu_password_mailtrap
MAIL_ENCRYPTION=tls

# Luego:
php artisan config:clear
```
**Resultado**: Emails aparecen en tu bandeja MailTrap (inbox virtual)

---

## ✅ **OPCIÓN 2: Gmail SMTP (Emails Reales)**
```bash
# En .env:
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu-email@gmail.com
MAIL_PASSWORD=tu-app-password  # No tu password normal!
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tu-email@gmail.com
MAIL_FROM_NAME="Tu Nombre"

# Luego:
php artisan config:clear
```
**Nota**: Necesitas generar un "App Password" en Gmail (no tu password normal)

---

## ✅ **OPCIÓN 3: SendGrid (Producción)**
```bash
# En .env:
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=tu_sendgrid_api_key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@tudominio.com
MAIL_FROM_NAME="Tu Empresa"

# Luego:
php artisan config:clear
```
**Resultado**: Emails reales de producción

---

## 🧪 **Testing Rápido con MailTrap:**

1. **Registrarse**: https://mailtrap.io (gratis)
2. **Copiar credenciales** de tu inbox
3. **Actualizar .env** con las credenciales
4. **Enviar campaña** desde humano.test
5. **Ver emails** en MailTrap inbox

---

## 🔄 **Para volver al modo LOG:**
```bash
echo "MAIL_MAILER=log" >> .env
php artisan config:clear
```
