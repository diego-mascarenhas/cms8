# Team API Testing Guide

> **Guía completa para probar la API de equipos usando tokens de autenticación**

Esta guía te ayudará a probar todos los endpoints de la API de equipos usando tokens de autenticación. Los tokens permiten que aplicaciones frontend accedan a los datos del equipo sin necesidad de autenticación de usuario.

## 🔧 Configuración

### Variables de entorno
```bash
BASE_URL="https://humano.test/api"
TOKEN="d8da230c496e26b1dcab3a05b385db2417e32168a2cb1a217dbf0a8e677af382"
```

### Headers requeridos
- `Authorization: Bearer {TOKEN}`
- `Accept: application/json`
- `Content-Type: application/json` (para POST/PUT)

## 🏢 Información del Equipo

### Obtener información básica del equipo
```bash
curl -X GET "$BASE_URL/team" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" -k
```

**Respuesta esperada:**
```json
{
  "success": true,
  "team": {
    "id": 1,
    "name": "Demo's Team",
    "personal_team": false
  },
  "statistics": {
    "contacts": 151,
    "projects": 30,
    "tasks": 0
  }
}
```

### Obtener configuración del equipo
```bash
curl -X GET "$BASE_URL/team/settings" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" -k
```

## 👥 Gestión de Contactos

### Listar todos los contactos
```bash
curl -X GET "$BASE_URL/team/contacts" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" -k
```

### Listar contactos con paginación
```bash
curl -X GET "$BASE_URL/team/contacts?page=1&per_page=10" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" -k
```

### Obtener un contacto específico
```bash
curl -X GET "$BASE_URL/team/contacts/1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" -k
```

### Crear un nuevo contacto
```bash
curl -X POST "$BASE_URL/team/contacts" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" -k \
  -d '{
    "name": "Juan Pérez",
    "email": "juan@example.com",
    "phone": "612345678",
    "language": "es",
    "status_id": 1,
    "country": 724,
    "engagment": "temperate"
  }'
```

### Actualizar un contacto
```bash
curl -X PUT "$BASE_URL/team/contacts/1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" -k \
  -d '{
    "name": "Juan Pérez Actualizado",
    "email": "juan.actualizado@example.com",
    "phone": "612345679"
  }'
```

### Eliminar un contacto
```bash
curl -X DELETE "$BASE_URL/team/contacts/1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" -k
```

## 📋 Gestión de Proyectos

### Listar todos los proyectos
```bash
curl -X GET "$BASE_URL/team/projects" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" -k
```

### Obtener un proyecto específico
```bash
curl -X GET "$BASE_URL/team/projects/1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" -k
```

### Crear un nuevo proyecto
```bash
curl -X POST "$BASE_URL/team/projects" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" -k \
  -d '{
    "name": "Nuevo Proyecto",
    "real_name": "Proyecto Real",
    "description": "Descripción del proyecto",
    "enterprise_id": 1,
    "responsible_id": 1,
    "date_start": "2025-07-01",
    "date_end": "2025-08-01",
    "cost": "1000.00",
    "price": "1500.00",
    "status_id": 1
  }'
```

### Actualizar un proyecto
```bash
curl -X PUT "$BASE_URL/team/projects/1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" -k \
  -d '{
    "name": "Proyecto Actualizado",
    "description": "Nueva descripción",
    "status_id": 2
  }'
```

### Eliminar un proyecto
```bash
curl -X DELETE "$BASE_URL/team/projects/1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" -k
```

## 🔍 Ejemplos de Filtrado y Búsqueda

### Filtrar contactos por estado
```bash
curl -s "$BASE_URL/team/contacts" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" -k | \
  jq -r '.data.data[] | select(.status_id == 5) | .name'
```

### Buscar contactos por idioma
```bash
curl -s "$BASE_URL/team/contacts" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" -k | \
  jq -r '.data.data[] | select(.language == "es") | (.name + " - " + .email)'
```

### Proyectos ordenados por fecha
```bash
curl -s "$BASE_URL/team/projects" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" -k | \
  jq -r '.data.data | sort_by(.date_start) | .[] | (.name + " - " + .date_start)'
```

## 📊 Ejemplos de Formateo para Humanos

### Mostrar contactos en formato legible
```bash
curl -s "$BASE_URL/team/contacts" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" -k | \
  jq -r '.data.data[:10] | to_entries[] | 
    (.key + 1 | tostring) + ". " + .value.name + 
    "\n   📧 " + (.value.email // "Sin email") + 
    "\n   📱 " + (.value.phone | tostring) + 
    " | 🌍 " + (.value.language // "N/A") + 
    " | 📊 Estado: " + (.value.status_id | tostring) + "\n"'
```

### Resumen de estadísticas del equipo
```bash
curl -s "$BASE_URL/team" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" -k | \
  jq -r '"🏢 Equipo: " + .team.name + 
    "\n📊 Contactos: " + (.statistics.contacts | tostring) + 
    "\n📋 Proyectos: " + (.statistics.projects | tostring) + 
    "\n✅ Tareas: " + (.statistics.tasks | tostring)'
```

## 🚀 Script de Prueba Automatizada

Usa el script incluido para probar todos los endpoints:

```bash
./tests/test_team_api.sh
```

## 🔐 Seguridad

- Los tokens se almacenan encriptados en la base de datos
- Cada token está asociado a un equipo específico
- Los tokens solo dan acceso a los datos del equipo correspondiente
- No hay acceso cruzado entre equipos

## 📝 Notas Importantes

1. **Paginación**: La API devuelve 20 registros por página por defecto
2. **Filtros**: Puedes usar `jq` para filtrar y formatear las respuestas
3. **Errores**: La API devuelve códigos de error HTTP estándar
4. **Límites**: No hay límites de rate limiting implementados actualmente

## 🆘 Solución de Problemas

### Error 401: "Invalid token"
- Verifica que el token sea correcto
- Asegúrate de incluir el header `Authorization: Bearer {TOKEN}`

### Error 404: "Not Found"
- Verifica que el endpoint sea correcto
- Asegúrate de que el ID del recurso exista

### Error 500: "Internal Server Error"
- Revisa los logs de Laravel para más detalles
- Verifica que la base de datos esté funcionando correctamente 