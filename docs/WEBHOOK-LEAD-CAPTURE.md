# Webhook Lead Capture - Creator ID Solution

## ❌ Problema Original

```php
'creator_id' => auth()->id() ?? 1
```

**¿Por qué estaba mal?**
- `auth()->id()` siempre es `null` en endpoints API sin autenticación de usuario
- Usar `1` como fallback es arbitrario y peligroso:
  - El usuario ID 1 puede no existir
  - Puede no pertenecer al equipo
  - No tiene sentido semántico

## ✅ Solución Implementada

```php
'creator_id' => $team->user_id  // Owner of the team
```

**¿Por qué es correcto?**
- ✅ El `user_id` del equipo es el **owner** (propietario del team)
- ✅ Siempre existe (foreign key requerida)
- ✅ Semánticamente correcto: el owner es responsable de leads capturados en su equipo
- ✅ Funciona tanto para API con token como para webhook público

---

## 📝 Archivos Modificados

### 1. **TeamContactController** (API con Token)
**Archivo**: `app/Http/Controllers/Api/TeamContactController.php`

```php
// El middleware TeamTokenAuth proporciona $team
$contact = Contact::create([
    'team_id' => $team->id,
    'creator_id' => $team->user_id, // Owner del equipo
    // ... otros campos
]);
```

### 2. **LeadController** (Webhook Público)
**Archivo**: `app/Http/Controllers/LeadController.php`

```php
use App\Models\Team;

// Obtener el team desde el team_id validado
$team = Team::findOrFail($validated['team_id']);

$contact = Contact::create([
    'team_id' => $validated['team_id'],
    'creator_id' => $team->user_id, // Owner del equipo
    // ... otros campos
]);
```

---

## 🔍 Contexto Técnico

### Estructura de Teams (Laravel Jetstream)

En Laravel Jetstream, cada `Team` tiene:
- **`user_id`**: ID del propietario del equipo (NOT NULL)
- **`owner` relationship**: Relación al usuario propietario

```php
// En app/Models/Team.php
class Team extends JetstreamTeam
{
    // Jetstream define automáticamente:
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

### Migration de Teams

```php
// database/migrations/2020_05_21_100000_create_teams_table.php
Schema::create('teams', function (Blueprint $table) {
    $table->foreignId('user_id')->index(); // Owner del equipo
    $table->string('name');
    $table->boolean('personal_team');
    // ...
});
```

### Migration de Contacts

```php
// El creator_id es NOT NULL y debe ser un usuario válido
$table->foreignId('creator_id')->constrained('users');
```

---

## 🎯 Endpoints Disponibles

### 1. Webhook Público (Sin Token)
```bash
POST https://humano.test/lead

{
  "name": "Jane Smith",
  "email": "jane@example.com",
  "phone": "+1987654321",
  "team_id": 3  # El owner de este equipo será el creator
}
```

### 2. API con Token
```bash
POST https://humano.test/api/team/contacts
Authorization: Bearer {token}

{
  "name": "Jane Smith",
  "email": "jane@example.com",
  "phone": "+1987654321"
  # team_id se obtiene del token, creator_id del owner del equipo
}
```

---

## ✅ Beneficios de esta Solución

1. **Consistencia**: Todos los leads del equipo tienen el mismo creador lógico (el owner)
2. **Seguridad**: No hay valores arbitrarios ni IDs inventados
3. **Auditoría**: Se puede rastrear qué equipo generó cada lead
4. **Escalabilidad**: Funciona con cualquier número de equipos sin configuración adicional
5. **Mantenibilidad**: Solución simple y fácil de entender

---

## 🚀 Testing

```bash
# Test con curl
curl -X POST https://humano.test/lead \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Jane Smith",
    "email": "jane@example.com",
    "phone": "+1987654321",
    "team_id": 3
  }'
```

**Resultado esperado:**
```json
{
  "success": true,
  "message": "Lead created successfully"
}
```

El contacto creado tendrá:
- `team_id`: 3
- `creator_id`: El `user_id` del Team 3 (su propietario)
