# API de Fichaje y Tareas - Documentación

## Autenticación

Todos los endpoints requieren autenticación mediante token Sanctum en el header `Authorization: Bearer {token}`.

### Login

```bash
POST /api/auth/login
Content-Type: application/json

{
  "email": "usuario@ejemplo.com",
  "password": "tu-contraseña"
}
```

**Respuesta:**
```json
{
  "email": "usuario@ejemplo.com",
  "token": "1|TZk48cEle5Xrgse5cm0AIPaA4c2bZJOFvEFjWzpV3033f049"
}
```

---

## Endpoints de Tareas

### 1. Listar Mis Tareas

```bash
GET /api/tasks
Authorization: Bearer {token}
```

**Parámetros opcionales (query string):**
- `status_id` - Filtrar por ID de estado
- `pending_only=1` - Solo tareas pendientes (excluye DONE y CANCELLED)

**Respuesta exitosa:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Por hacer",
      "description": "Por hacer",
      "start_date": "2025-12-17",
      "due_date": "2025-12-17",
      "estimated_hours": null,
      "status": {
        "id": 1,
        "name": "TO_DO",
        "translated_name": "Por Hacer"
      },
      "category": {
        "id": null,
        "name": null
      },
      "project": {
        "id": 1,
        "name": "Prueba interna"
      },
      "responsible": {
        "id": 2,
        "name": "Diego Mascarenhas",
        "email": "diego.mascarenhas@icloud.com"
      }
    }
  ],
  "total": 1
}
```

### 2. Detalle de Tarea

```bash
GET /api/tasks/{id}
Authorization: Bearer {token}
```

**Respuesta exitosa:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Por hacer",
    "description": "Por hacer",
    "start_date": "2025-12-17",
    "due_date": "2025-12-17",
    "estimated_hours": null,
    "status": {
      "id": 1,
      "name": "TO_DO",
      "translated_name": "Por Hacer"
    },
    "category": {
      "id": null,
      "name": null
    },
    "project": {
      "id": 1,
      "name": "Prueba interna"
    },
    "responsible": {
      "id": 2,
      "name": "Diego Mascarenhas",
      "email": "diego.mascarenhas@icloud.com"
    },
    "board": {
      "id": 2,
      "name": "Project: Prueba interna"
    }
  }
}
```

**Error 403 (sin permisos):**
```json
{
  "success": false,
  "message": "No tienes permiso para ver esta tarea."
}
```

---

## Endpoints de Fichaje (Time Tracking)

### 3. Verificar Timer Activo

```bash
GET /api/time/running
Authorization: Bearer {token}
```

**Respuesta sin timer activo:**
```json
{
  "success": true,
  "running": false,
  "data": null
}
```

**Respuesta con timer activo:**
```json
{
  "success": true,
  "running": true,
  "data": {
    "id": 1,
    "task_id": 1,
    "task": {
      "id": 1,
      "title": "Por hacer",
      "status": "Por Hacer",
      "project": {
        "id": 1,
        "name": "Prueba interna"
      }
    },
    "description": "Prueba desde API móvil",
    "start_time": "2025-12-17T09:35:32.000000Z",
    "elapsed_seconds": 145,
    "is_billable": true
  }
}
```

### 4. Iniciar Timer

```bash
POST /api/time/start
Authorization: Bearer {token}
Content-Type: application/json

{
  "task_id": 1,
  "description": "Descripción opcional del trabajo"
}
```

**Respuesta exitosa:**
```json
{
  "success": true,
  "message": "Timer iniciado correctamente.",
  "data": {
    "id": 1,
    "task_id": 1,
    "task": {
      "id": 1,
      "title": "Por hacer",
      "project": {
        "id": 1,
        "name": "Prueba interna"
      }
    },
    "description": "Prueba desde API móvil",
    "start_time": "2025-12-17T09:35:32.000000Z",
    "is_billable": true
  },
  "previous_stopped": false
}
```

**Notas:**
- Si ya existe un timer corriendo para la misma tarea, retorna el timer existente
- Si hay un timer corriendo para otra tarea, lo detiene automáticamente e inicia el nuevo (`previous_stopped: true`)

**Error 403 (sin permisos):**
```json
{
  "success": false,
  "message": "No tienes permiso para fichar en esta tarea."
}
```

### 5. Detener Timer

```bash
POST /api/time/{id}/stop
Authorization: Bearer {token}
```

**Respuesta exitosa:**
```json
{
  "success": true,
  "message": "Timer detenido correctamente.",
  "data": {
    "id": 1,
    "task_id": 1,
    "task": {
      "id": 1,
      "title": "Por hacer",
      "project": {
        "id": 1,
        "name": "Prueba interna"
      }
    },
    "description": "Prueba desde API móvil",
    "start_time": "2025-12-17T09:35:32.000000Z",
    "end_time": "2025-12-17T09:36:05.000000Z",
    "duration_seconds": 33,
    "duration_formatted": "1m",
    "duration_hours": 0.01,
    "is_billable": true,
    "earnings": 0
  }
}
```

**Error 400 (timer no está corriendo):**
```json
{
  "success": false,
  "message": "El timer no está corriendo."
}
```

**Error 403 (sin permisos):**
```json
{
  "success": false,
  "message": "No tienes permiso para detener este timer."
}
```

### 6. Historial de Fichajes

```bash
GET /api/time
Authorization: Bearer {token}
```

**Parámetros opcionales (query string):**
- `date_from` - Fecha desde (formato: YYYY-MM-DD)
- `date_to` - Fecha hasta (formato: YYYY-MM-DD)
- `task_id` - Filtrar por tarea específica
- `include_running=1` - Incluir timer activo en los resultados
- `limit` - Límite de resultados (default: 50)

**Respuesta exitosa:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "task_id": 1,
      "task": {
        "id": 1,
        "title": "Por hacer",
        "status": "Por Hacer",
        "project": {
          "id": 1,
          "name": "Prueba interna"
        }
      },
      "description": "Prueba desde API móvil",
      "start_time": "2025-12-17T09:35:32.000000Z",
      "end_time": "2025-12-17T09:36:05.000000Z",
      "duration_seconds": 33,
      "duration_formatted": "1m",
      "duration_hours": 0.01,
      "is_running": false,
      "is_billable": true,
      "hourly_rate": null,
      "earnings": 0
    }
  ],
  "total": 1
}
```

---

## Ejemplos de Uso con cURL

### Flujo Completo de Fichaje

```bash
# 1. Login
TOKEN=$(curl -X POST https://humano.test/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"tu@email.com","password":"tu-password"}' \
  -s | jq -r '.token')

# 2. Listar mis tareas
curl -X GET https://humano.test/api/tasks \
  -H "Authorization: Bearer $TOKEN"

# 3. Verificar si hay timer activo
curl -X GET https://humano.test/api/time/running \
  -H "Authorization: Bearer $TOKEN"

# 4. Iniciar timer en tarea 1
curl -X POST https://humano.test/api/time/start \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"task_id": 1, "description": "Trabajando en la tarea"}'

# 5. Detener timer (reemplazar {id} con el ID del timer)
curl -X POST https://humano.test/api/time/{id}/stop \
  -H "Authorization: Bearer $TOKEN"

# 6. Ver historial
curl -X GET https://humano.test/api/time \
  -H "Authorization: Bearer $TOKEN"
```

---

## Códigos de Estado HTTP

- `200` - Operación exitosa
- `201` - Recurso creado exitosamente
- `400` - Error de validación o solicitud incorrecta
- `401` - No autenticado (token inválido o ausente)
- `403` - Sin permisos para realizar la acción
- `404` - Recurso no encontrado

---

## Pruebas Realizadas

### ✅ Endpoints Probados y Funcionando

1. **POST /api/auth/login** - Autenticación correcta
2. **GET /api/tasks** - Listado de tareas (2 tareas encontradas)
3. **GET /api/tasks/1** - Detalle de tarea con información completa
4. **GET /api/time/running** - Verificación de timer (sin timer activo)
5. **POST /api/time/start** - Inicio de timer exitoso (ID: 1)
6. **GET /api/time/running** - Verificación de timer corriendo
7. **POST /api/time/1/stop** - Detención de timer exitosa
8. **GET /api/time** - Historial de fichajes (1 registro)

### Fecha de Prueba
17 de diciembre de 2025

