# 🔄 Cross-Database Token Authentication

Este documento explica cómo resolver el problema de tokens de autenticación cuando el área de clientes usa una base de datos diferente.

## 🚨 El Problema

**Situación inicial:**
- Este proyecto (`humano`) usa su propia base de datos MySQL
- El área de clientes (`https://revisionalpha.com`) usa una base de datos diferente
- Los tokens de Sanctum se almacenan en `personal_access_tokens` table
- ❌ **Resultado**: Tokens generados aquí no funcionan en el área de clientes

## ✅ La Solución: Tokens Firmados

Implementamos tokens firmados que no requieren acceso a base de datos para validar:

### 🔧 Funcionamiento

```php
// 1. Generación del token (en este sistema)
$payload = [
    'user_id' => $user->id,
    'email' => $user->email,
    'exp' => now()->addHours(24)->timestamp,
    'iat' => now()->timestamp,
    'purpose' => 'whatsapp_autologin'
];

$signature = hash_hmac('sha256', json_encode($payload), config('app.key'));
$token = base64_encode(json_encode($payload) . '|' . $signature);
```

```php
// 2. Validación del token (en el área de clientes)
$decoded = base64_decode($token);
$parts = explode('|', $decoded, 2);
$payload = json_decode($parts[0], true);
$signature = $parts[1];

// Verificar firma usando la misma APP_KEY
$expectedSignature = hash_hmac('sha256', $parts[0], config('app.key'));
if (hash_equals($expectedSignature, $signature)) {
    // Token válido - buscar usuario por email
    $user = User::where('email', $payload['email'])->first();
}
```

## 📁 Archivos Implementados

### 1. `app/Helpers/TokenHelper.php`
Helper centralizado para:
- `generateSignedToken()` - Crear tokens firmados
- `validateSignedToken()` - Validar tokens firmados
- `getTokenPayload()` - Debug de tokens

### 2. `app/Services/TwilioService.php`
Genera tokens firmados para WhatsApp:
```php
$accessToken = TokenHelper::generateSignedToken($user, 'whatsapp_autologin', 24);
```

### 3. `app/Services/ClaudeService.php`
Genera tokens firmados para Claude AI:
```php
$accessToken = TokenHelper::generateSignedToken($user, 'claude_autologin', 24);
```

### 4. `app/Http/Controllers/AuthController.php`
Valida ambos tipos de tokens:
- **Nuevo**: Tokens firmados (preferido)
- **Legacy**: Tokens Sanctum (compatibilidad)

## 🔐 Características de Seguridad

### ✅ Ventajas de los Tokens Firmados:
1. **No requieren base de datos** para validar
2. **Funcionan entre sistemas diferentes**
3. **Expiran automáticamente** (24 horas)
4. **Imposibles de falsificar** sin la APP_KEY
5. **Incluyen información del propósito**

### ⚠️ Consideraciones:
1. **APP_KEY debe ser la misma** en ambos sistemas
2. **No se pueden revocar** antes de la expiración
3. **El reloj debe estar sincronizado** entre sistemas

## 🎯 Casos de Uso

### Desde WhatsApp:
```
Usuario: "¿Qué servicios tengo?"
Sistema: Genera token firmado con propósito 'whatsapp_autologin'
Link: https://revisionalpha.com/login/token/{SIGNED_TOKEN}
```

### Desde Claude AI:
```
Usuario: "Necesito acceder a mi área de clientes"
Claude: Genera token firmado con propósito 'claude_autologin'
Link: https://revisionalpha.com/login/token/{SIGNED_TOKEN}
```

## 🔄 Compatibilidad Legacy

El sistema mantiene compatibilidad con tokens Sanctum:

```php
public function loginWithToken($token)
{
    // 1. Intenta token firmado (nuevo)
    $user = TokenHelper::validateSignedToken($token);
    
    // 2. Si falla, intenta Sanctum (legacy)
    if (!$user) {
        $user = $this->validateSanctumToken($token);
    }
    
    // 3. Login si cualquiera es válido
    if ($user) {
        auth()->login($user, true);
        return redirect()->route('dashboard');
    }
}
```

## 🔧 Configuración Requerida

### En el área de clientes:

1. **Misma APP_KEY**: Asegurar que `config('app.key')` sea idéntica
2. **Ruta de login**: Implementar `/login/token/{token}`
3. **Validación**: Usar el mismo algoritmo de validación
4. **Sincronización de tiempo**: Servidores con hora sincronizada

### Ejemplo de implementación en área de clientes:

```php
// routes/web.php
Route::get('/login/token/{token}', [AuthController::class, 'loginWithToken']);

// AuthController.php
public function loginWithToken($token)
{
    $user = TokenHelper::validateSignedToken($token);
    if ($user) {
        auth()->login($user, true);
        return redirect('/dashboard');
    }
    return redirect('/login')->withErrors(['error' => 'Token inválido']);
}
```

## 🧪 Testing

```bash
# Generar token de prueba
php artisan tinker
>>> $user = User::first()
>>> $token = \App\Helpers\TokenHelper::generateSignedToken($user, 'test', 1)
>>> echo $token

# Validar token
>>> $validUser = \App\Helpers\TokenHelper::validateSignedToken($token)
>>> $validUser->email
```

## 📊 Logs de Monitoreo

Los tokens firmados generan logs automáticos:

```
[INFO] Signed token validated successfully
{
    "user_id": 123,
    "email": "user@example.com", 
    "purpose": "whatsapp_autologin"
}
```

---

> **💡 Tip**: Este sistema resuelve completamente el problema de bases de datos diferentes manteniendo la seguridad y facilitando la experiencia del usuario.
