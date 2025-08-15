# 📧 Configuración SMTP Real - Opciones

## 🚀 **Opciones de Proveedores SMTP:**

### ✅ **1. Gmail (Más Fácil)**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tu-email@gmail.com
MAIL_PASSWORD=tu-app-password  # Generar en Gmail Security
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tu-email@gmail.com
MAIL_FROM_NAME="Tu Nombre"
```
**Ventajas**: Gratis, fácil setup, familiar
**Requisito**: Activar 2FA y generar App Password

---

### ✅ **2. SendGrid (Producción)**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=tu_sendgrid_api_key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@tudominio.com
MAIL_FROM_NAME="Tu Empresa"
```
**Ventajas**: Robusto, analytics, 100 emails/día gratis
**Requisito**: Cuenta SendGrid

---

### ✅ **3. Mailgun**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@sandbox-xxx.mailgun.org
MAIL_PASSWORD=tu_mailgun_password
MAIL_ENCRYPTION=tls
```
**Ventajas**: Potente API, buena deliverability

---

### ✅ **4. Amazon SES**
```env
MAIL_MAILER=smtp
MAIL_HOST=email-smtp.us-east-1.amazonaws.com
MAIL_PORT=587
MAIL_USERNAME=tu_access_key
MAIL_PASSWORD=tu_secret_key
MAIL_ENCRYPTION=tls
```
**Ventajas**: Muy barato, escalable

---

### ✅ **5. Servidor Propio/Hosting**
```env
MAIL_MAILER=smtp
MAIL_HOST=mail.tudominio.com
MAIL_PORT=587  # o 465
MAIL_USERNAME=tu-email@tudominio.com
MAIL_PASSWORD=tu_password
MAIL_ENCRYPTION=tls  # o ssl
```
