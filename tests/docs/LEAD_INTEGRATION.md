# Lead Integration - Documentation

## 📋 Overview

Este documento describe la integración completa entre formularios externos y el CMS Humano para la creación de leads/contactos. La implementación incluye validación condicional, limpieza automática de datos y manejo flexible de campos opcionales para cualquier cliente.

## 🚀 Funcionalidades Implementadas

### ✅ 1. Validación Condicional Email/Teléfono
- **Email obligatorio** si NO se proporciona teléfono
- **Teléfono obligatorio** si NO se proporciona email  
- **Al menos uno de los dos** debe estar presente
- Permite crear contactos con información parcial

### ✅ 2. Campo Surname en Tabla Principal
- **Surname opcional** se guarda directamente en la tabla `contacts`
- **Separado del campo name** para mejor organización
- **Incluido en logs** para trazabilidad completa

### ✅ 3. Limpieza Automática de Teléfonos
- **Remueve automáticamente**: espacios, guiones, paréntesis, símbolos `+`
- **Convierte**: `+34 688-999-000` → `34688999000`
- **Compatible**: con columna `bigInteger` en base de datos
- **Solo procesa** si el teléfono está presente

### ✅ 4. Manejo de Datos Adicionales
- **Campo `data`**: Almacena información adicional de formularios externos como JSON
- **Preserva**: todos los datos del formulario original
- **Estructura**: campos principales + datos adicionales en JSON

## 🔧 Endpoint Configuration

### Ruta
```
POST /lead
```

### Middleware
- **CSRF deshabilitado** para permitir envíos externos
- **Validación DNS de email removida** para mayor flexibilidad

### Headers Recomendados
```bash
Content-Type: application/x-www-form-urlencoded
Accept: application/json
X-Requested-With: XMLHttpRequest  # Opcional para respuestas JSON
```

## 📊 Estructura de Datos

### Campos Principales (Tabla `contacts`)
```php
[
    'name' => 'string|required|max:255',
    'surname' => 'string|nullable|max:255',    // ✅ NUEVO
    'email' => 'email|nullable|required_without:phone',  // ✅ CONDICIONAL  
    'phone' => 'string|nullable|required_without:email', // ✅ CONDICIONAL
    'team_id' => 'integer|required|exists:teams,id'
]
```

### Datos Adicionales (Campo `data` JSON)
```json
{
    "first_name": "María",
    "last_name": "García",
    "form_type": "client_profile",
    "certification": "Cambridge CAE",
    "software": "MemoQ",
    "project_title": "House of the Dragon",
    "rates": { "translation": 15.50 },
    "submission_date": "2025-07-05 13:07:13"
}
```

## 🧪 Tests Realizados

### ✅ Test 1: Solo Email (Sin Teléfono)
```bash
curl -X POST \
  -d "name=Ana Solo Email" \
  -d "email=ana.soloemail@example.com" \
  -d "team_id=1" \
  https://humano.test/lead
```
**Resultado**: ✅ Contacto creado exitosamente

### ✅ Test 2: Solo Teléfono (Sin Email)  
```bash
curl -X POST \
  -d "name=Carlos Solo Teléfono" \
  -d "phone=+34 655 777 888" \
  -d "team_id=1" \
  https://humano.test/lead
```
**Resultado**: ✅ Contacto creado exitosamente
**Teléfono guardado**: `34655777888` (limpiado)

### ✅ Test 3: Completo con Surname
```bash
curl -X POST \
  -d "name=María" \
  -d "surname=García Rodríguez" \
  -d "email=maria.garcia@example.com" \
  -d "phone=+34 611 222 333" \
  -d "team_id=1" \
  https://humano.test/lead
```
**Resultado**: ✅ Contacto creado exitosamente
**Surname guardado**: `García Rodríguez`
**Teléfono guardado**: `34611222333` (limpiado)

### ✅ Test 4: Error - Sin Email ni Teléfono
```bash
curl -X POST \
  -d "name=Error Sin Contacto" \
  -d "team_id=1" \
  https://humano.test/lead
```
**Resultado**: ❌ **HTTP 422** (error esperado)
```json
{
  "message": "Debe proporcionar al menos un email o teléfono",
  "errors": {
    "email": ["Debe proporcionar al menos un email o teléfono"],
    "phone": ["Debe proporcionar al menos un teléfono o email"]
  }
}
```

## 📝 Logs de Evidencia

### Formato del Log
```
[FECHA HORA] Nuevo lead - Nombre: [name] [surname], Email: [email|no proporcionado], Teléfono: [cleaned_phone|no proporcionado] (original: [original_phone])
```

### Ejemplos Reales
```bash
# Solo email:
[2025-07-05 13:06:36] Nuevo lead - Nombre: Ana Solo Email, Email: ana.soloemail@example.com, Teléfono: no proporcionado

# Solo teléfono:  
[2025-07-05 13:07:00] Nuevo lead - Nombre: Carlos Solo Teléfono, Email: no proporcionado, Teléfono: 34655777888 (original: 34 655 777 888)

# Completo con surname:
[2025-07-05 13:07:13] Nuevo lead - Nombre: María García Rodríguez, Email: maria.garcia@example.com, Teléfono: 34611222333 (original: 34 611 222 333)
```

## 🔍 Verificación en Base de Datos

### Consulta de Verificación
```php
php artisan tinker --execute="
\$contact = \App\Models\Contact::latest()->first();
echo 'Nombre: ' . \$contact->name . PHP_EOL;
echo 'Surname: ' . (\$contact->surname ?: 'null') . PHP_EOL;
echo 'Email: ' . (\$contact->email ?: 'null') . PHP_EOL;
echo 'Teléfono: ' . (\$contact->phone ?: 'null') . PHP_EOL;
echo 'Data: ' . json_encode(\$contact->data) . PHP_EOL;
"
```

### Ejemplo de Resultados
```
Nombre: María
Surname: García Rodríguez
Email: maria.garcia@example.com
Teléfono: 34611222333
Data: {"test":"con_surname","apellido_completo":"García Rodríguez"}
```

## 🚨 Casos de Error

### 1. Sin Email ni Teléfono
**HTTP Status**: `422 Unprocessable Entity`
**Mensaje**: "Debe proporcionar al menos un email o teléfono"

### 2. Email Inválido
**HTTP Status**: `422 Unprocessable Entity`  
**Mensaje**: "El email debe ser válido"

### 3. Teléfono con Caracteres Inválidos
**HTTP Status**: `422 Unprocessable Entity`
**Mensaje**: "El teléfono solo puede contener números, espacios y los símbolos + -"

### 4. Team ID Inexistente
**HTTP Status**: `422 Unprocessable Entity`
**Mensaje**: "El equipo seleccionado no es válido"

## 🔧 Configuración Técnica

### Middleware CSRF
```php
// humano/app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    'twilio/webhook',
    'lead',  // ✅ Agregado para formularios externos
];
```

### Validación Condicional
```php
// humano/app/Http/Controllers/LeadController.php
'email' => ['required_without:phone', 'nullable', 'email:rfc', 'max:255'],
'phone' => ['required_without:email', 'nullable', 'string', 'max:20', 'regex:/^[+\-\d\s()]+$/'],
```

### Limpieza de Teléfono
```php
// Remueve todo excepto dígitos
$cleanPhone = null;
if (!empty($validated['phone'])) {
    $cleanPhone = preg_replace('/[^\d]/', '', $validated['phone']);
}
```

## 📈 Casos de Uso Comunes

### Formulario Completo de Cliente
```bash
curl -X POST \
  -d "name=Ana" \
  -d "surname=Martínez" \
  -d "email=ana.martinez@example.com" \
  -d "phone=+34 600 111 222" \
  -d "team_id=1" \
  -d 'data={"first_name":"Ana","last_name":"Martínez","certification":"Cambridge CAE","software":"MemoQ","form_type":"client_profile"}' \
  https://humano.test/lead
```

### Lead Solo con Email
```bash
curl -X POST \
  -d "name=Contact Email" \
  -d "email=contact@company.com" \
  -d "team_id=1" \
  -d 'data={"source":"landing_page","campaign":"email_marketing"}' \
  https://humano.test/lead
```

### Lead Solo con Teléfono
```bash
curl -X POST \
  -d "name=Contact Phone" \
  -d "phone=+34-666-777-888" \
  -d "team_id=1" \
  -d 'data={"source":"phone_call","notes":"Interested in services"}' \
  https://humano.test/lead
```

## ✅ Estado de Implementación

- ✅ **Validación condicional email/teléfono** - COMPLETADO
- ✅ **Campo surname en tabla principal** - COMPLETADO  
- ✅ **Limpieza automática de teléfonos** - COMPLETADO
- ✅ **Manejo de datos JSON externos** - COMPLETADO
- ✅ **CSRF deshabilitado para ruta externa** - COMPLETADO
- ✅ **Logs de trazabilidad** - COMPLETADO
- ✅ **Tests de validación** - COMPLETADO
- ✅ **Documentación** - COMPLETADO

## 🚀 Listo para Producción

La integración de formularios externos → Humano CMS está **completamente funcional** y lista para recibir datos de cualquier cliente con:

- **Flexibilidad**: Email O teléfono (no ambos obligatorios)
- **Robustez**: Limpieza automática de datos
- **Trazabilidad**: Logs completos de todas las operaciones
- **Escalabilidad**: Estructura de datos extensible con JSON

---

**Fecha**: 2024-12-19  
**Versión**: 1.0  
**Estado**: Producción Ready ✅ 