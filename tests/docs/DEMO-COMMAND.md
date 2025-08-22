# Comando DEMO para WhatsApp

## Descripción
El comando DEMO permite a usuarios nuevos registrarse automáticamente en el sistema y asociarse con la empresa **Idoneo Technologies** (ID: 2) mediante WhatsApp.

## Flujo de Funcionamiento

### 1. Activación
- El usuario envía exactamente **"DEMO"** (sensible a mayúsculas)
- El sistema verifica si el número de teléfono ya tiene una cuenta registrada

### 2. Validación Inicial
- **Si ya existe**: Informa que ya tiene cuenta registrada
- **Si no existe**: Inicia el proceso de registro

### 3. Recopilación de Datos

#### Paso 1: Solicitar Nombre
```
🎉 ¡Bienvenido al DEMO de Idoneo Technologies!

Para crear tu cuenta demo, necesito algunos datos:

👤 Por favor, envíame tu *nombre completo*:
```

**Validaciones:**
- Mínimo 2 caracteres
- Se almacena temporalmente por 10 minutos

#### Paso 2: Solicitar Email
```
✅ Perfecto, *[NOMBRE]*!

📧 Ahora envíame tu *email*:
```

**Validaciones:**
- Formato de email válido
- Email no debe existir en el sistema
- Se verifica contra la base de datos

### 4. Creación Automática

Si todos los datos son válidos, el sistema:

1. **Crea Usuario:**
   - Email y nombre proporcionados
   - Teléfono limpiado con `PhoneHelper`
   - Contraseña temporal: `Simplicity!`
   - Rol: `client`
   - Email verificado automáticamente

2. **Asocia con Team 1:**
   - El usuario se asocia al equipo principal (ID: 1)
   - Rol en el equipo: `client`

3. **Crea Contacto:**
   - Asociado al usuario creado
   - Team ID: 1
   - Estado: Activo
   - Creador: Usuario del sistema (ID: 1)

4. **Asocia con Idoneo Technologies:**
   - El contacto se vincula a la empresa ID: 2
   - Acceso a los servicios demo de la empresa

5. **Genera Token de Auto-login:**
   - Token firmado válido por 24 horas
   - Propósito: `demo_autologin`

### 5. Mensaje de Confirmación

```
🎉 ¡Felicidades! Tu cuenta demo ha sido creada exitosamente.

📧 Email: [EMAIL]
🔐 Contraseña temporal: Simplicity!

🚀 Ahora tienes acceso a nuestros servicios de Idoneo Technologies:
• Desarrollo de Software con IA
• Gestión de Infraestructura en la Nube
• Desarrollo de Apps Móviles
• Consultoría en Ciberseguridad

🌐 Visita nuestro sitio web: https://idoneo.dev
🌐 Accede directamente a tu área de cliente:
https://revisionalpha.com/login/token/[TOKEN]

¡Gracias por probar nuestro demo! 🚀
```

## Características Técnicas

### Estado Temporal (Cache)
- **Demo State**: Almacena el paso actual del registro
- **Demo Data**: Almacena el nombre temporalmente
- **TTL**: 10 minutos para ambos
- **Limpieza**: Automática al completar o fallar

### Validaciones de Seguridad
- Verificación de usuario existente por teléfono
- Validación de email único en la base de datos
- Formato de email válido
- Longitud mínima del nombre

### Manejo de Errores
- Reintentos automáticos para datos inválidos
- Limpieza de estado en caso de error
- Logging detallado de todos los pasos
- Mensajes informativos al usuario

### Empresa Demo
- **ID**: 2 (Idoneo Technologies)
- **Website**: https://idoneo.dev
- **Email**: no-reply@idoneo.dev
- **Team**: 1 (Principal)
- **Servicios Incluidos**:
  - AI Software Development (Activo)
  - Cloud Infrastructure Management (Activo)
  - Mobile App Development (Activo)
  - Cybersecurity Consulting (Activo)
  - Data Analytics Platform (Inactivo)

## Casos de Uso

### ✅ Flujo Exitoso
1. Usuario: `DEMO`
2. Sistema: Solicita nombre
3. Usuario: `Juan Pérez`
4. Sistema: Solicita email
5. Usuario: `juan@email.com`
6. Sistema: Crea cuenta y envía credenciales

### ❌ Usuario Existente
1. Usuario: `DEMO`
2. Sistema: "Ya tienes una cuenta registrada..."

### ❌ Email Duplicado
1. Usuario completa nombre
2. Envía email ya registrado
3. Sistema: "Este email ya está registrado..."
4. Solicita otro email

### ⚠️ Timeout
- Si el usuario no responde en 10 minutos
- El estado se limpia automáticamente
- Debe reiniciar con `DEMO`

## Logs y Monitoreo

### Logs de Éxito
```php
\Log::info("Demo user created successfully", [
    'user_id' => $user->id,
    'contact_id' => $contact->id,
    'email' => $email,
    'phone' => $phoneNumber
]);
```

### Logs de Error
- Errores de validación
- Problemas de creación de usuario
- Fallos de asociación con empresa
- Errores de envío de mensajes

## Configuración Requerida

### Dependencias
- `PhoneHelper::clean()` para limpiar teléfonos
- `TokenHelper::generateSignedToken()` para auto-login
- Cache de Laravel para estado temporal
- Spatie Laravel Permission para roles

### Variables de Entorno
- URL base del cliente: `https://revisionalpha.com`
- Configuración de Twilio para WhatsApp
- Cache driver configurado

## Testing

Para probar el comando:

1. Envía `DEMO` a WhatsApp
2. Sigue el flujo de registro
3. Verifica la creación en la base de datos:
   ```sql
   SELECT * FROM users WHERE phone LIKE '%[NUMERO]%';
   SELECT * FROM contacts WHERE phone LIKE '%[NUMERO]%';
   ```
4. Verifica asociación con empresa:
   ```sql
   SELECT * FROM contact_enterprise WHERE enterprise_id = 2;
   ```
