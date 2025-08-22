# Team Configuration Monitoring

Este sistema permite monitorear automáticamente las configuraciones de cada team y reportar problemas proactivamente.

## 🎯 **Características Principales**

### 📊 **Comando de Monitoreo**
```bash
php artisan team:test-configurations
```

### 🔧 **Opciones Disponibles**

| Opción | Descripción |
|--------|-------------|
| `--team=ID` | Probar solo un team específico |
| `--report-email=email` | Enviar reporte a email específico (sobrescribe emails de owners) |
| `--no-email` | No enviar reporte por email |
| `--failures-only` | Solo reportar fallos |
| `--admin-summary` | Enviar resumen administrativo a notification_email |

### 🧪 **Servicios Monitoreados**

1. **📤 SMTP** - Envío de emails
2. **📥 IMAP** - Recepción de emails
3. **💳 Stripe** - Procesamiento de pagos
4. **📱 Twilio** - SMS y WhatsApp

## 📋 **Validaciones Específicas**

### SMTP
- ✅ Configuración completa (host, username)
- ✅ Conectividad de socket
- ❌ Credenciales inválidas
- ❌ Host no accesible

### IMAP
- ✅ Configuración completa (host, username)
- ✅ Autenticación exitosa
- ❌ Credenciales inválidas
- ❌ Servidor no accesible

### Stripe
- ✅ Formato de claves (pk_, sk_)
- ✅ Autenticación con API
- ✅ Permisos de cuenta
- ❌ Claves inválidas/expiradas

### Twilio
- ✅ Formato de SID (AC...)
- ✅ Autenticación con API
- ✅ Estado de cuenta activo
- ❌ Credenciales inválidas
- ❌ Cuenta suspendida

## 🚀 **Scheduling Automático**

### 📅 **Ejecución Diaria**
```bash
# Diario a las 8:00 AM - Solo fallos a team owners individuales
php artisan team:test-configurations --failures-only
```

### 📅 **Reporte Semanal**
```bash
# Lunes a las 9:00 AM - Reporte completo + resumen administrativo
php artisan team:test-configurations --admin-summary
```

## 📖 **Configuración en Kernel**

```php
// Team configuration monitoring - daily at 8:00 AM
// Sends individual reports to team owners only for failures
$schedule->command('team:test-configurations --failures-only')
    ->dailyAt('08:00')
    ->name('team-config-monitoring')
    ->description('Monitor team configurations and send individual failure reports to owners')
    ->onFailure(function () {
        Log::error('Team configuration monitoring command failed');
    })
    ->runInBackground();

// Weekly comprehensive report - Mondays at 9:00 AM
// Sends individual reports to team owners + admin summary
$schedule->command('team:test-configurations --admin-summary')
    ->weeklyOn(1, '09:00') // Monday at 9:00 AM
    ->name('team-config-weekly-report')
    ->description('Weekly team configuration report with admin summary')
    ->runInBackground();
```

## 📊 **Ejemplo de Salida**

```
🔍 Starting Team Configuration Test...

📊 Testing 1 team(s)...

🏢 Testing Team: Demo's Team (ID: 1)
  ✅ SMTP: PASSED - Connection successful
  ✅ IMAP: PASSED - Connection successful
  ✅ Stripe: PASSED - Connection successful
  ✅ Twilio: PASSED - Connection successful
  📊 Summary: 4 passed, 0 failed, 0 skipped

📈 FINAL SUMMARY
===============
Teams tested: 1
Total tests: 4
✅ Passed: 4
❌ Failed: 0
⏭️ Skipped: 0

🎉 All configured services are working correctly!
📧 Sending individual reports to team owners...
📧 Individual report for 'Demo Team' would be sent to: owner@company.com
📧 Admin summary would be sent to: no-reply@idoneo.dev
```

## 📝 **Logging y Reportes**

### 📋 **Logs Automáticos**
- ✅ Resultados completos en canal 'daily'
- ❌ Fallos específicos con detalles
- 📧 Intentos de envío de reportes

### 📧 **Reportes por Email**

#### 🏢 **Reportes Individuales a Team Owners**
- 📧 **Por defecto**: Cada team owner recibe SOLO su reporte
- 🎯 **Con `--failures-only`**: Solo reciben si hay fallos en su team
- 📤 **Destinatario**: `team.owner.email` automáticamente
- 🔧 **Override**: Usar `--report-email=email` para cambiar destinatario

#### 👑 **Resumen Administrativo (Opcional)**
- 📤 **Solo con `--admin-summary`**: Enviado a `config('app.notification_email')`
- 📊 **Contenido**: Estadísticas globales + lista de teams con problemas
- 🎯 **Propósito**: Visión general para administradores

## 🧪 **Tests Unitarios**

```bash
# Ejecutar todos los tests de configuración
php artisan test tests/Unit/TeamConfigurationTest.php

# Tests específicos
php artisan test --filter="it_can_set_and_get_settings"
php artisan test --filter="it_encrypts_sensitive_settings"
php artisan test --filter="it_generates_consistent_team_hash"
```

### 🧪 **Cobertura de Tests**

- ✅ **20 tests** cubriendo toda la funcionalidad
- ✅ Configuración y obtención de settings
- ✅ Encriptación de datos sensibles
- ✅ Generación de hashes determinísticos
- ✅ URLs de webhooks de Twilio
- ✅ Configuraciones de todos los servicios
- ✅ Validación de tipos de datos
- ✅ Compatibilidad con métodos deprecated
- ✅ Manejo de valores nulos/vacíos
- ✅ Eliminación en cascada
- ✅ Restricciones únicas

## ⚙️ **Configuración de Variables**

### 📧 **Email de Notificaciones**
```bash
# .env
NOTIFICATION_EMAIL=no-reply@idoneo.dev  # (valor por defecto)
# o personalizar:
NOTIFICATION_EMAIL=admin@yourcompany.com
```

### 🔐 **Configuraciones Team-Específicas**
```php
// Ejemplo de configuración programática
$team->setSetting('mail_host', 'smtp.gmail.com');
$team->setSetting('mail_username', 'team@company.com');
$team->setSetting('mail_password', 'password', ['is_encrypted' => true]);
```

## 🚨 **Alertas y Notificaciones**

### ❌ **Fallos Detectados**
- 🔥 Log de error inmediato
- 📧 Email de alerta (si configurado)
- 📊 Detalles específicos del problema
- 🔍 Sugerencias de resolución

### ✅ **Estado Saludable**
- ℹ️ Log informativo
- 📊 Estadísticas de rendimiento
- 📈 Tendencias históricas

## 🔧 **Troubleshooting**

### 🚫 **"No teams found to test"**
- Verificar que existan teams en la base de datos
- Revisar filtros de team específico

### 📧 **"No report email configured"**
- Configurar `NOTIFICATION_EMAIL` en `.env` (por defecto: no-reply@idoneo.dev)
- O usar `--report-email=email@domain.com`

### 🔌 **Fallos de Conexión**
- Verificar configuraciones de red
- Revisar credenciales de servicios
- Comprobar estado de servicios externos

## 📈 **Beneficios**

- 🔍 **Detección Proactiva** de problemas
- 📊 **Monitoreo Automático** 24/7
- 📧 **Alertas Inmediatas** por fallos
- 📈 **Visibilidad Completa** del estado del sistema
- 🛡️ **Prevención** de interrupciones del servicio
- 📋 **Auditoría** completa de configuraciones

---

## 📝 **Guía de Uso por Casos**

### 🔄 **Monitoreo Diario (Recomendado)**
```bash
# Solo envía emails a team owners que tengan fallos
php artisan team:test-configurations --failures-only
```
**✅ Ideal para**: Alertas automáticas diarias sin spam

### 📊 **Reporte Semanal Completo**
```bash
# Envía reporte individual a cada owner + resumen a admin
php artisan team:test-configurations --admin-summary
```
**✅ Ideal para**: Revisión semanal completa + visión administrativa

### 🎯 **Team Específico**
```bash
# Solo un team específico (útil para debugging)
php artisan team:test-configurations --team=1
```
**✅ Ideal para**: Debugging de problemas específicos

### 📧 **Override de Email**
```bash
# Envía TODOS los reportes a un email específico (ignora owners)
php artisan team:test-configurations --report-email=admin@empresa.com
```
**✅ Ideal para**: Testing o cuando quieres centralizar reportes temporalmente

### 🚫 **Sin Emails (Testing)**
```bash
# Solo testing/debugging local (no envía emails)
php artisan team:test-configurations --no-email
```
**✅ Ideal para**: Desarrollo y testing local

### 👑 **Solo Resumen Administrativo**
```bash
# Solo resumen global, sin reportes individuales
php artisan team:test-configurations --admin-summary --failures-only --report-email=admin@empresa.com
```
**✅ Ideal para**: Administradores que solo quieren vista global

---

## 🏢 **Sistema de Privacidad y Escalabilidad**

### ✅ **Ventajas del Nuevo Sistema**
- 🔒 **Privacidad**: Cada team owner solo ve SU información
- 📈 **Escalabilidad**: Sin saturación del email administrativo
- 🎯 **Relevancia**: Solo reciben lo que les corresponde
- ⚡ **Acción directa**: Los owners pueden actuar inmediatamente
- 📊 **Visión administrativa**: Resumen opcional para admins

### 📧 **Flujo de Emails**
1. **Fallos detectados** → Email automático al team owner
2. **Todo OK** → Sin email (reduce spam)
3. **Resumen semanal** → Reporte individual + resumen admin (opcional)
4. **Override disponible** → Admin puede recibir todo si es necesario
