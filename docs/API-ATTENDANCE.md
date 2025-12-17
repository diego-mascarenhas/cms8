# Attendance API Documentation

## Overview

The Attendance API allows users to track their work shifts (clock in/out) with support for pausing and resuming. This is separate from task-based time tracking and is designed for general work attendance management.

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

## Endpoints

### 1. Get Attendance History

Retrieve the user's attendance history.

```bash
GET /api/attendance
Authorization: Bearer {token}
```

**Optional Query Parameters:**

-   `date_from` - Filter from date (format: YYYY-MM-DD)
-   `date_to` - Filter to date (format: YYYY-MM-DD)
-   `include_running=1` - Include currently running attendance
-   `limit` - Result limit (default: 50)

**Success Response (200):**

```json
{
	"success": true,
	"data": [
		{
			"id": 1,
			"start_at": "2025-12-17T09:43:30.000000Z",
			"end_at": "2025-12-17T17:30:00.000000Z",
			"duration_seconds": 28020,
			"duration_formatted": "7h 47m",
			"duration_hours": 7.78,
			"is_running": false,
			"is_paused": false,
			"paused_seconds": 1800
		}
	],
	"total": 1
}
```

---

### 2. Check Running Attendance

Check if the user is currently clocked in.

```bash
GET /api/attendance/running
Authorization: Bearer {token}
```

**Success Response - Not Clocked In (200):**

```json
{
	"success": true,
	"running": false,
	"data": null
}
```

**Success Response - Clocked In (200):**

```json
{
	"success": true,
	"running": true,
	"data": {
		"id": 1,
		"start_at": "2025-12-17T09:43:30.000000Z",
		"elapsed_seconds": 3600,
		"working_seconds": 3300,
		"paused_seconds": 300,
		"is_paused": false,
		"paused_at": null
	}
}
```

**Fields Explanation:**

-   `elapsed_seconds` - Total time since clock-in
-   `working_seconds` - Actual working time (elapsed - paused)
-   `paused_seconds` - Total time spent paused
-   `is_paused` - Whether currently paused
-   `paused_at` - When the current pause started (if paused)

---

### 3. Clock In

Start a new work shift.

```bash
POST /api/attendance/clock-in
Authorization: Bearer {token}
```

**Success Response (201):**

```json
{
	"success": true,
	"message": "Jornada iniciada correctamente.",
	"data": {
		"id": 1,
		"start_at": "2025-12-17T09:43:30.000000Z"
	}
}
```

**Error Response - Already Clocked In (400):**

```json
{
	"success": false,
	"message": "Ya tienes una jornada activa.",
	"data": {
		"id": 1,
		"start_at": "2025-12-17T09:43:30.000000Z",
		"elapsed_seconds": 1200
	}
}
```

---

### 4. Clock Out

End the current work shift.

```bash
POST /api/attendance/{id}/clock-out
Authorization: Bearer {token}
```

**Success Response (200):**

```json
{
	"success": true,
	"message": "Jornada finalizada correctamente.",
	"data": {
		"id": 1,
		"start_at": "2025-12-17T09:43:30.000000Z",
		"end_at": "2025-12-17T17:30:00.000000Z",
		"duration_seconds": 28020,
		"duration_formatted": "7h 47m",
		"duration_hours": 7.78,
		"paused_seconds": 1800
	}
}
```

**Error Response - Not Running (400):**

```json
{
	"success": false,
	"message": "Esta jornada ya está finalizada."
}
```

**Error Response - Unauthorized (403):**

```json
{
	"success": false,
	"message": "No tienes permiso para finalizar esta jornada."
}
```

---

### 5. Pause Attendance

Pause the current work shift (e.g., for lunch break).

```bash
POST /api/attendance/{id}/pause
Authorization: Bearer {token}
```

**Success Response (200):**

```json
{
	"success": true,
	"message": "Jornada pausada correctamente.",
	"data": {
		"id": 1,
		"paused_at": "2025-12-17T13:00:00.000000Z"
	}
}
```

**Error Response - Not Running (400):**

```json
{
	"success": false,
	"message": "Esta jornada no está activa."
}
```

**Error Response - Already Paused (400):**

```json
{
	"success": false,
	"message": "La jornada ya está pausada."
}
```

---

### 6. Resume Attendance

Resume a paused work shift.

```bash
POST /api/attendance/{id}/resume
Authorization: Bearer {token}
```

**Success Response (200):**

```json
{
	"success": true,
	"message": "Jornada reanudada correctamente.",
	"data": {
		"id": 1,
		"paused_seconds": 1800
	}
}
```

**Error Response - Not Paused (400):**

```json
{
	"success": false,
	"message": "La jornada no está pausada."
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

# 2. Check if already clocked in
curl -X GET https://your-domain.com/api/attendance/running \
  -H "Authorization: Bearer $TOKEN"

# 3. Clock in to start work shift
curl -X POST https://your-domain.com/api/attendance/clock-in \
  -H "Authorization: Bearer $TOKEN"

# 4. Pause for lunch break
curl -X POST https://your-domain.com/api/attendance/1/pause \
  -H "Authorization: Bearer $TOKEN"

# 5. Resume after lunch
curl -X POST https://your-domain.com/api/attendance/1/resume \
  -H "Authorization: Bearer $TOKEN"

# 6. Clock out at end of day
curl -X POST https://your-domain.com/api/attendance/1/clock-out \
  -H "Authorization: Bearer $TOKEN"

# 7. View attendance history
curl -X GET https://your-domain.com/api/attendance \
  -H "Authorization: Bearer $TOKEN"
```

---

## Business Rules

1. **One Active Attendance Per User**: Users can only have one active (running) attendance at a time.

2. **Automatic Resume on Clock-Out**: If a user clocks out while paused, the system automatically resumes before calculating the final duration.

3. **Duration Calculation**:

    - `duration_seconds = (end_at - start_at) - paused_seconds`
    - Paused time is excluded from the working duration

4. **Team Scope**: All attendances are scoped to the user's current team via global scope.

5. **Permissions**: Users can only view and modify their own attendance records (admins can view all).

---

## HTTP Status Codes

-   `200` - Success
-   `201` - Resource created successfully
-   `400` - Bad request (validation error or business rule violation)
-   `401` - Unauthorized (invalid or missing token)
-   `403` - Forbidden (insufficient permissions)
-   `404` - Resource not found

---

## Testing Results

### ✅ All Endpoints Tested Successfully

1. **GET /api/attendance/running** - Check status (not clocked in)
2. **POST /api/attendance/clock-in** - Clock in successfully (ID: 1)
3. **GET /api/attendance/running** - Verify running attendance
4. **POST /api/attendance/1/pause** - Pause attendance
5. **POST /api/attendance/1/resume** - Resume attendance
6. **POST /api/attendance/1/clock-out** - Clock out successfully
7. **GET /api/attendance** - View attendance history (1 record)

**Test Date:** December 17, 2025

---

## Notes

-   All timestamps are in ISO 8601 format (UTC)
-   Duration is calculated excluding paused time
-   The system tracks multiple pause/resume cycles within a single attendance
-   Paused seconds accumulate across multiple pause periods
