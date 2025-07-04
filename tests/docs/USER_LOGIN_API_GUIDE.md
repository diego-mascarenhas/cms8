# User Login API Guide

> **Guía completa para autenticación de usuarios via CURL usando Laravel Sanctum**

Esta guía explica cómo hacer login de usuarios individuales via CURL, que es diferente al sistema de tokens de equipo.

## 🔐 Sistemas de Autenticación Disponibles

La aplicación maneja **DOS sistemas de autenticación diferentes**:

### 1. **🏢 Team Tokens** (Tokens de Equipo)
- **Propósito**: Para aplicaciones frontend que representan un equipo completo
- **Acceso**: Solo a datos del equipo (contactos, proyectos, etc.)
- **Rutas**: `/api/team/*`
- **Header**: `Authorization: Bearer {TEAM_TOKEN}`

### 2. **👤 User Tokens** (Tokens de Usuario - Sanctum)
- **Propósito**: Para usuarios individuales autenticados
- **Acceso**: A recursos específicos del usuario y su equipo actual
- **Rutas**: `/api/*` (rutas generales)
- **Header**: `Authorization: Bearer {USER_TOKEN}`

## 🚀 Login de Usuario (Sanctum)

### Paso 1: Login para obtener token

```bash
curl -X POST "https://humano.test/api/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" -k \
  -d '{
    "email": "admin@example.com",
    "password": "Simplicity!",
    "remember_me": true
  }'
```

**Respuesta esperada:**
```json
{
  "email": "admin@example.com",
  "token": "1|VtU5AdJ9ogFAlphio7lKmVPKgACenC6yuwqhgyH11fb10080"
}
```

### Paso 2: Usar el token para acceder a recursos

```bash
USER_TOKEN="1|VtU5AdJ9ogFAlphio7lKmVPKgACenC6yuwqhgyH11fb10080"

# Obtener información del usuario autenticado
curl -X GET "https://humano.test/api/user" \
  -H "Authorization: Bearer $USER_TOKEN" \
  -H "Accept: application/json" -k
```

## 📋 Endpoints Disponibles para Usuarios Autenticados

### 🔍 Información del Usuario
```bash
curl -X GET "https://humano.test/api/user" \
  -H "Authorization: Bearer $USER_TOKEN" \
  -H "Accept: application/json" -k
```

**Respuesta:**
```json
{
  "id": 3,
  "name": "Admin",
  "email": "admin@example.com",
  "current_team_id": 1,
  "profile_photo_url": "https://ui-avatars.com/api/?name=A&color=7F9CF5&background=EBF4FF"
}
```

### 📂 Categorías
```bash
curl -X GET "https://humano.test/api/category" \
  -H "Authorization: Bearer $USER_TOKEN" \
  -H "Accept: application/json" -k
```

### 💬 Mensajes
```bash
# Listar mensajes
curl -X GET "https://humano.test/api/message" \
  -H "Authorization: Bearer $USER_TOKEN" \
  -H "Accept: application/json" -k

# Obtener mensaje específico
curl -X GET "https://humano.test/api/message/1" \
  -H "Authorization: Bearer $USER_TOKEN" \
  -H "Accept: application/json" -k
```

### 🚪 Logout (Cerrar Sesión)
```bash
curl -X POST "https://humano.test/api/auth/logout" \
  -H "Authorization: Bearer $USER_TOKEN" \
  -H "Accept: application/json" -k
```

**Respuesta:**
```json
{
  "message": "Su sesión se ha cerrado correctamente"
}
```

## 👤 Registro de Nuevos Usuarios

```bash
curl -X POST "https://humano.test/api/auth/register" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" -k \
  -d '{
    "name": "Nuevo Usuario",
    "email": "nuevo@example.com",
    "password": "password123"
  }'
```

## 🔄 Comparación: Team Token vs User Token

| Aspecto | Team Token | User Token (Sanctum) |
|---------|------------|----------------------|
| **Propósito** | Aplicaciones frontend | Usuarios individuales |
| **Generación** | Via interfaz web | Via login API |
| **Almacenamiento** | Encriptado en DB | Laravel Sanctum |
| **Acceso** | Solo datos del equipo | Datos del usuario + equipo |
| **Rutas** | `/api/team/*` | `/api/*` |
| **Caducidad** | No caduca | Configurable |
| **Revocación** | Manual via interfaz | Auto al logout |

## 🛠️ Ejemplos Prácticos Completos

### Flujo completo de autenticación de usuario:

```bash
#!/bin/bash
BASE_URL="https://humano.test/api"

echo "🔐 1. Login del usuario..."
RESPONSE=$(curl -s -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" -k \
  -d '{
    "email": "admin@example.com",
    "password": "Simplicity!"
  }')

echo "Respuesta del login:"
echo $RESPONSE | jq '.'

# Extraer el token
USER_TOKEN=$(echo $RESPONSE | jq -r '.token')

if [ "$USER_TOKEN" != "null" ]; then
  echo ""
  echo "✅ Login exitoso! Token: $USER_TOKEN"
  
  echo ""
  echo "👤 2. Obteniendo información del usuario..."
  curl -s "$BASE_URL/user" \
    -H "Authorization: Bearer $USER_TOKEN" \
    -H "Accept: application/json" -k | jq '.'
  
  echo ""
  echo "📂 3. Obteniendo categorías..."
  curl -s "$BASE_URL/category" \
    -H "Authorization: Bearer $USER_TOKEN" \
    -H "Accept: application/json" -k | jq '.'
  
  echo ""
  echo "🚪 4. Cerrando sesión..."
  curl -s -X POST "$BASE_URL/auth/logout" \
    -H "Authorization: Bearer $USER_TOKEN" \
    -H "Accept: application/json" -k | jq '.'
else
  echo "❌ Login fallido"
fi
```

## 🔒 Seguridad y Buenas Prácticas

### Para Tokens de Usuario (Sanctum):
- ✅ Los tokens caducan automáticamente
- ✅ Se revocan al hacer logout
- ✅ Están asociados a un usuario específico
- ✅ Permiten múltiples sesiones simultáneas

### Para Tokens de Equipo:
- ✅ Almacenados encriptados en la base de datos
- ✅ Acceso limitado solo a datos del equipo
- ✅ No hay acceso cruzado entre equipos
- ✅ Control granular de permisos

## 📝 Diferencias Clave en el Uso

### ✨ Cuándo usar User Tokens:
- Aplicaciones móviles
- SPAs que requieren autenticación de usuario
- Dashboards personalizados
- APIs que necesitan identificar al usuario específico

### ✨ Cuándo usar Team Tokens:
- Aplicaciones frontend que representan un equipo
- Integraciones de terceros
- Sistemas que acceden a datos agregados del equipo
- APIs públicas limitadas por equipo

## 🆘 Solución de Problemas

### Error 401: "Los datos de acceso son incorrectos"
```bash
# Verificar credenciales
curl -X POST "https://humano.test/api/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" -k \
  -d '{"email": "tu-email", "password": "tu-password"}' -v
```

### Error 401: "Unauthenticated"
```bash
# Verificar que el token sea válido
curl -X GET "https://humano.test/api/user" \
  -H "Authorization: Bearer TU_TOKEN" \
  -H "Accept: application/json" -k -v
```

### Regenerar token si expiró:
```bash
# Hacer login nuevamente
curl -X POST "https://humano.test/api/auth/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" -k \
  -d '{"email": "admin@example.com", "password": "Simplicity!"}'
```

## 🎯 Testing con Diferentes Usuarios

### Credenciales de prueba disponibles:
- **Admin**: `admin@example.com` / `Simplicity!`
- **Usuario Demo**: `diego.mascarenhas@icloud.com` / `password`

### Script de prueba automatizada:
```bash
./tests/test_user_api.sh
``` 