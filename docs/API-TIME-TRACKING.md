# Time Tracking API Documentation

## Overview

The Time Tracking API allows users to track time spent on specific tasks. This is separate from general attendance (clock in/out) and provides detailed task-level time management with automatic timer switching.

## Authentication

All endpoints require Sanctum token authentication via the `Authorization: Bearer {token}` header.

### Login

```bash
POST /api/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "your-password"
}
```

**Response:**

```json
{
	"email": "user@example.com",
	"token": "1|TZk48cEle5Xrgse5cm0AIPaA4c2bZJOFvEFjWzpV3033f049"
}
```

---

## Task Endpoints

### 1. List My Tasks

Get all tasks assigned to the authenticated user.

```bash
GET /api/tasks
Authorization: Bearer {token}
```

**Optional Query Parameters:**

-   `status_id` - Filter by status ID
-   `pending_only=1` - Only show pending tasks (excludes DONE and CANCELLED)

**Success Response (200):**

```json
{
	"success": true,
	"data": [
		{
			"id": 1,
			"title": "Implement API endpoints",
			"description": "Create REST API for mobile app",
			"start_date": "2025-12-17",
			"due_date": "2025-12-20",
			"estimated_hours": 8.5,
			"status": {
				"id": 2,
				"name": "IN_PROGRESS",
				"translated_name": "In Progress"
			},
			"category": {
				"id": 3,
				"name": "Development"
			},
			"project": {
				"id": 1,
				"name": "Mobile App"
			},
			"responsible": {
				"id": 2,
				"name": "John Doe",
				"email": "john@example.com"
			}
		}
	],
	"total": 1
}
```

---

### 2. Get Task Details

Get detailed information about a specific task.

```bash
GET /api/tasks/{id}
Authorization: Bearer {token}
```

**Success Response (200):**

```json
{
	"success": true,
	"data": {
		"id": 1,
		"title": "Implement API endpoints",
		"description": "Create REST API for mobile app",
		"start_date": "2025-12-17",
		"due_date": "2025-12-20",
		"estimated_hours": 8.5,
		"status": {
			"id": 2,
			"name": "IN_PROGRESS",
			"translated_name": "In Progress"
		},
		"category": {
			"id": 3,
			"name": "Development"
		},
		"project": {
			"id": 1,
			"name": "Mobile App"
		},
		"responsible": {
			"id": 2,
			"name": "John Doe",
			"email": "john@example.com"
		},
		"board": {
			"id": 2,
			"name": "Project: Mobile App"
		}
	}
}
```

**Error Response - Forbidden (403):**

```json
{
	"success": false,
	"message": "No tienes permiso para ver esta tarea."
}
```

---

## Time Tracking Endpoints

### 3. Get Time Tracking History

Retrieve the user's time tracking history.

```bash
GET /api/time
Authorization: Bearer {token}
```

**Optional Query Parameters:**

-   `date_from` - Filter from date (format: YYYY-MM-DD)
-   `date_to` - Filter to date (format: YYYY-MM-DD)
-   `task_id` - Filter by specific task
-   `include_running=1` - Include currently running timer
-   `limit` - Result limit (default: 50)

**Success Response (200):**

```json
{
	"success": true,
	"data": [
		{
			"id": 1,
			"task_id": 1,
			"task": {
				"id": 1,
				"title": "Implement API endpoints",
				"status": "In Progress",
				"project": {
					"id": 1,
					"name": "Mobile App"
				}
			},
			"description": "Working on authentication endpoints",
			"start_time": "2025-12-17T09:35:32.000000Z",
			"end_time": "2025-12-17T11:20:15.000000Z",
			"duration_seconds": 6283,
			"duration_formatted": "1h 44m",
			"duration_hours": 1.75,
			"is_running": false,
			"is_billable": true,
			"hourly_rate": 50.0,
			"earnings": 87.5
		}
	],
	"total": 1
}
```

---

### 4. Check Running Timer

Check if the user has a timer currently running.

```bash
GET /api/time/running
Authorization: Bearer {token}
```

**Success Response - No Timer (200):**

```json
{
	"success": true,
	"running": false,
	"data": null
}
```

**Success Response - Timer Running (200):**

```json
{
	"success": true,
	"running": true,
	"data": {
		"id": 1,
		"task_id": 1,
		"task": {
			"id": 1,
			"title": "Implement API endpoints",
			"status": "In Progress",
			"project": {
				"id": 1,
				"name": "Mobile App"
			}
		},
		"description": "Working on authentication endpoints",
		"start_time": "2025-12-17T09:35:32.000000Z",
		"elapsed_seconds": 3600,
		"is_billable": true
	}
}
```

---

### 5. Start Timer

Start tracking time on a specific task.

```bash
POST /api/time/start
Authorization: Bearer {token}
Content-Type: application/json

{
  "task_id": 1,
  "description": "Optional description of work"
}
```

**Success Response (201):**

```json
{
	"success": true,
	"message": "Timer iniciado correctamente.",
	"data": {
		"id": 1,
		"task_id": 1,
		"task": {
			"id": 1,
			"title": "Implement API endpoints",
			"project": {
				"id": 1,
				"name": "Mobile App"
			}
		},
		"description": "Working on authentication endpoints",
		"start_time": "2025-12-17T09:35:32.000000Z",
		"is_billable": true
	},
	"previous_stopped": false
}
```

**Success Response - Same Task Already Running (200):**

```json
{
	"success": true,
	"message": "El timer ya está corriendo para esta tarea.",
	"data": {
		"id": 1,
		"task_id": 1,
		"task": {
			"id": 1,
			"title": "Implement API endpoints"
		},
		"description": "Working on authentication endpoints",
		"start_time": "2025-12-17T09:35:32.000000Z",
		"elapsed_seconds": 1200
	},
	"previous_stopped": false
}
```

**Success Response - Different Task (Auto-stop) (201):**

```json
{
	"success": true,
	"message": "Timer iniciado correctamente.",
	"data": {
		"id": 2,
		"task_id": 2,
		"task": {
			"id": 2,
			"title": "Write documentation"
		},
		"description": "API documentation",
		"start_time": "2025-12-17T11:30:00.000000Z",
		"is_billable": true
	},
	"previous_stopped": true
}
```

**Error Response - Forbidden (403):**

```json
{
	"success": false,
	"message": "No tienes permiso para fichar en esta tarea."
}
```

**Validation Error (422):**

```json
{
	"message": "The task id field is required.",
	"errors": {
		"task_id": ["The task id field is required."]
	}
}
```

---

### 6. Stop Timer

Stop a running timer.

```bash
POST /api/time/{id}/stop
Authorization: Bearer {token}
```

**Success Response (200):**

```json
{
	"success": true,
	"message": "Timer detenido correctamente.",
	"data": {
		"id": 1,
		"task_id": 1,
		"task": {
			"id": 1,
			"title": "Implement API endpoints",
			"project": {
				"id": 1,
				"name": "Mobile App"
			}
		},
		"description": "Working on authentication endpoints",
		"start_time": "2025-12-17T09:35:32.000000Z",
		"end_time": "2025-12-17T11:20:15.000000Z",
		"duration_seconds": 6283,
		"duration_formatted": "1h 44m",
		"duration_hours": 1.75,
		"is_billable": true,
		"earnings": 87.5
	}
}
```

**Error Response - Not Running (400):**

```json
{
	"success": false,
	"message": "El timer no está corriendo."
}
```

**Error Response - Forbidden (403):**

```json
{
	"success": false,
	"message": "No tienes permiso para detener este timer."
}
```

---

## Complete Workflow Example

```bash
# 1. Login
TOKEN=$(curl -X POST https://your-domain.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}' \
  -s | jq -r '.token')

# 2. List my tasks
curl -X GET "https://your-domain.com/api/tasks?pending_only=1" \
  -H "Authorization: Bearer $TOKEN"

# 3. Get task details
curl -X GET https://your-domain.com/api/tasks/1 \
  -H "Authorization: Bearer $TOKEN"

# 4. Check if timer is running
curl -X GET https://your-domain.com/api/time/running \
  -H "Authorization: Bearer $TOKEN"

# 5. Start timer on task
curl -X POST https://your-domain.com/api/time/start \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"task_id": 1, "description": "Working on feature X"}'

# 6. Stop timer
curl -X POST https://your-domain.com/api/time/1/stop \
  -H "Authorization: Bearer $TOKEN"

# 7. View time tracking history
curl -X GET https://your-domain.com/api/time \
  -H "Authorization: Bearer $TOKEN"

# 8. Filter history by date range
curl -X GET "https://your-domain.com/api/time?date_from=2025-12-01&date_to=2025-12-31" \
  -H "Authorization: Bearer $TOKEN"
```

---

## Business Rules

1. **One Active Timer Per User**: Users can only have one running timer at a time.

2. **Automatic Timer Switching**:

    - Starting a timer on a different task automatically stops the current timer
    - Starting a timer on the same task returns the existing timer (idempotent)

3. **Task Assignment**: Users can only start timers on tasks assigned to them (unless admin).

4. **Duration Calculation**: Automatically calculated when timer is stopped: `duration = end_time - start_time`

5. **Billable by Default**: All task timers are marked as billable by default.

6. **Earnings Calculation**: `earnings = (duration_seconds / 3600) * hourly_rate`

7. **Team Scope**: All time entries are scoped to the user's current team via global scope.

---

## Field Descriptions

### Time Entry Fields

-   `id` - Unique identifier for the time entry
-   `task_id` - ID of the associated task
-   `description` - Optional description of the work performed
-   `start_time` - When the timer started (ISO 8601 UTC)
-   `end_time` - When the timer stopped (ISO 8601 UTC, null if running)
-   `duration_seconds` - Total duration in seconds
-   `duration_formatted` - Human-readable duration (e.g., "1h 44m")
-   `duration_hours` - Duration in decimal hours (e.g., 1.75)
-   `is_running` - Boolean indicating if timer is currently active
-   `is_billable` - Whether this time is billable
-   `hourly_rate` - Rate per hour (if set)
-   `earnings` - Calculated earnings (duration_hours \* hourly_rate)

---

## HTTP Status Codes

-   `200` - Success
-   `201` - Resource created successfully
-   `400` - Bad request (validation error or business rule violation)
-   `401` - Unauthorized (invalid or missing token)
-   `403` - Forbidden (insufficient permissions)
-   `404` - Resource not found
-   `422` - Validation error

---

## Testing Results

### ✅ All Endpoints Tested Successfully

1. **POST /api/auth/login** - Authentication successful
2. **GET /api/tasks** - Listed 2 tasks
3. **GET /api/tasks/1** - Retrieved task details
4. **GET /api/time/running** - Checked timer status (not running)
5. **POST /api/time/start** - Started timer on task 1 (ID: 1)
6. **GET /api/time/running** - Verified timer running
7. **POST /api/time/1/stop** - Stopped timer successfully
8. **GET /api/time** - Retrieved time tracking history (1 entry)

**Test Date:** December 17, 2025

---

## Differences from Attendance API

| Feature                  | Time Tracking                | Attendance                |
| ------------------------ | ---------------------------- | ------------------------- |
| **Purpose**              | Track time on specific tasks | Track general work shifts |
| **Granularity**          | Task-level                   | Day-level                 |
| **Multiple entries**     | Multiple per day             | Typically one per day     |
| **Pause/Resume**         | No                           | Yes                       |
| **Auto-switching**       | Yes (between tasks)          | No                        |
| **Billable tracking**    | Yes                          | No                        |
| **Earnings calculation** | Yes                          | No                        |
| **Required field**       | task_id                      | None                      |

---

## Notes

-   All timestamps are in ISO 8601 format (UTC)
-   Timers automatically stop when starting a new timer on a different task
-   The system prevents multiple running timers per user
-   Time entries are automatically associated with the user's current team
-   Admins can view all time entries; regular users can only see their own
