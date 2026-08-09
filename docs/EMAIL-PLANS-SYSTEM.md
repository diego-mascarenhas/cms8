# 📧 Sistema de Planes de Email - Control de Límites por Team

## 🎯 **Resumen del Sistema**

Sistema completo de gestión de planes de email que permite asignar límites específicos a cada team basado en tres niveles de servicio: **BASIC**, **FOUNDATION** y **SCALE**. Solo usuarios con rol **admin** pueden asignar y modificar planes.

### **📊 Planes Disponibles:**

| Plan | Descripción | Emails/Mes | Emails/Día | Contactos | Ideal Para |
|------|-------------|------------|------------|-----------|------------|
| **BASIC** | Ideal para comenzar | 10,000 | 500 | 3,000 | Pequeñas empresas |
| **FOUNDATION** | Para empresas en crecimiento | 50,000 | 2,000 | 20,000 | Empresas medianas |
| **SCALE** | Para grandes empresas | 100,000 | Sin límite | 50,000 | Grandes empresas |

---

## 🏗️ **Arquitectura del Sistema**

### **1. Base de Datos (Migración)**
```sql
-- Campos agregados a tabla teams
ALTER TABLE teams ADD COLUMN email_plan VARCHAR(255) DEFAULT 'basic';
ALTER TABLE teams ADD COLUMN email_monthly_limit INT DEFAULT 10000;
ALTER TABLE teams ADD COLUMN email_monthly_used INT DEFAULT 0;
ALTER TABLE teams ADD COLUMN email_monthly_reset_at TIMESTAMP NULL;
ALTER TABLE teams ADD COLUMN email_daily_limit INT DEFAULT 500;
ALTER TABLE teams ADD COLUMN email_daily_used INT DEFAULT 0;
ALTER TABLE teams ADD COLUMN email_daily_reset_date DATE NULL;
ALTER TABLE teams ADD COLUMN contact_limit INT DEFAULT 3000;
ALTER TABLE teams ADD COLUMN email_plan_assigned_at TIMESTAMP NULL;
ALTER TABLE teams ADD COLUMN email_plan_assigned_by BIGINT UNSIGNED NULL;
```

### **2. Archivos del Sistema**

#### **Enum de Planes:**
- `app/Enums/EmailPlan.php` - Define los 3 planes y sus límites

#### **Trait de Funcionalidad:**
- `app/Traits/HasEmailLimits.php` - Lógica de límites para Teams

#### **Comandos de Consola:**
- `app/Console/Commands/AssignEmailPlan.php` - Asignar planes
- `app/Console/Commands/CheckEmailLimits.php` - Verificar estado

#### **Modelo Actualizado:**
- `app/Models/Team.php` - Incluye trait HasEmailLimits

#### **Seeder:**
- `database/seeders/EmailPlansSeeder.php` - Inicializa teams existentes

#### **Migración:**
- `database/migrations/2025_08_25_220000_add_email_limits_to_teams_table.php`

---

## 🚀 **Comandos Principales**

### **1. Asignar Plan a Team**
```bash
# Sintaxis
php artisan email-plans:assign {team_id} {plan} [--admin-id=X]

# Ejemplos
php artisan email-plans:assign 1 basic
php artisan email-plans:assign 2 foundation --admin-id=1
php artisan email-plans:assign 3 scale

# Ver todos los teams
php artisan email-plans:assign 0 basic --list-teams
```

### **2. Verificar Estado de Límites**
```bash
# Ver estado de todos los teams
php artisan email-plans:check

# Ver team específico
php artisan email-plans:check --team-id=1

# Ver solo teams con problemas
php artisan email-plans:check --over-limits

# Resetear límites automáticamente
php artisan email-plans:check --reset-limits

# Salida en JSON
php artisan email-plans:check --format=json
```

### **3. Inicializar Sistema**
```bash
# Ejecutar migración
php artisan migrate

# Inicializar teams existentes con plan BASIC
php artisan db:seed --class=EmailPlansSeeder
```

---

## 🔒 **Control de Acceso y Validaciones**

### **Solo Admin Puede Asignar Planes:**
```php
// En HasEmailLimits trait
public function assignEmailPlan(EmailPlan $plan, int $assignedByUserId): bool
{
    $assignedBy = User::find($assignedByUserId);
    if (!$assignedBy || !$assignedBy->hasRole('admin')) {
        throw new Exception('Only admin users can assign email plans');
    }
    // ... resto de la lógica
}
```

### **Validaciones Automáticas:**
- ✅ Verificar límites antes de enviar
- ✅ Incrementar contadores después del envío
- ✅ Reset automático diario/mensual
- ✅ Validación de contactos por team

---

## 📈 **Funcionamiento del Sistema**

### **1. Verificación de Límites (Antes del Envío)**
```php
// En SendAllPendingNow.php
if (!$delivery->team->canSendEmails(1)) {
    $remaining = $delivery->team->getRemainingEmails();
    $this->warn("Team '{$delivery->team->name}' has reached email limits:");
    $this->warn("Monthly: {$remaining['monthly_used']}/{$remaining['monthly_limit']}");
    // ... skip email
}
```

### **2. Incremento de Contadores (Después del Envío)**
```php
// Después del dispatch exitoso
$delivery->team->incrementEmailUsage(1);
```

### **3. Reset Automático de Límites**
```php
// Reset mensual: primer día del mes
// Reset diario: cada día a las 00:00
$team->resetLimitsIfNeeded(); // Se ejecuta automáticamente
```

---

## 💡 **Casos de Uso**

### **Escenario 1: Asignar Plan FOUNDATION a Team**
```bash
# 1. Ver teams disponibles
php artisan email-plans:assign 0 basic --list-teams

# 2. Asignar plan
php artisan email-plans:assign 2 foundation

# 3. Verificar asignación
php artisan email-plans:check --team-id=2
```

### **Escenario 2: Verificar Teams con Problemas**
```bash
# Ver solo teams que superaron límites
php artisan email-plans:check --over-limits

# Resultado esperado:
# ⚠️  Teams over limits need attention:
#   • Mi Empresa: Over monthly emails, contacts
```

### **Escenario 3: Envío Masivo con Límites**
```bash
# El comando respeta automáticamente los límites
php artisan emails:send-all-now --message-id=3

# Resultado:
# ⚠️  Team 'Mi Empresa' has reached email limits:
#     Monthly: 9950/10000
#     Daily: 450/500
```

---

## 📊 **Métricas y Monitoreo**

### **Estados de los Teams:**
- 🟢 **Verde**: < 80% del límite
- 🟡 **Amarillo**: 80-99% del límite
- 🔴 **Rojo**: 100%+ del límite

### **Información Disponible:**
```php
$team = Team::find(1);

// Estado actual
$remaining = $team->getRemainingEmails();
// [
//     'monthly_remaining' => 5000,
//     'daily_remaining' => 300,
//     'monthly_used' => 5000,
//     'daily_used' => 200,
//     'monthly_limit' => 10000,
//     'daily_limit' => 500,
// ]

// Configuración del plan
$config = $team->getEmailPlanConfig();
// [
//     'name' => 'Foundation',
//     'monthly_limit' => 50000,
//     'daily_limit' => 2000,
//     'contact_limit' => 20000,
//     'assigned_by' => 'Admin User',
//     // ... más info
// ]
```

---

## 🔧 **Integración con Sistema Existente**

### **Modificaciones en Comandos Existentes:**

#### **SendAllPendingNow.php:**
```php
// ✅ Agregado: Verificación de límites antes del envío
if (!$delivery->team->canSendEmails(1)) {
    // Skip este delivery
}

// ✅ Agregado: Incremento de contador después del envío exitoso
$delivery->team->incrementEmailUsage(1);
```

#### **Team.php Model:**
```php
// ✅ Agregado: Trait para funcionalidad de límites
use HasEmailLimits;

// ✅ Agregado: Campos fillable para planes
protected $fillable = [
    'email_plan', 'email_monthly_limit', // ...
];

// ✅ Agregado: Relación con contactos
public function contacts() {
    return $this->hasMany(Contact::class);
}
```

---

## ⚠️ **Consideraciones y Limitaciones**

### **Limitaciones Actuales:**
1. **Solo Admin puede asignar**: Requiere rol 'admin'
2. **Reset automático**: No manual (solo por tiempo)
3. **Un plan por team**: No planes múltiples o personalizados

### **Extensiones Futuras:**
1. **Planes personalizados**: Límites custom por team
2. **Notificaciones**: Alertas automáticas al 80% del límite
3. **Billing integration**: Cobros automáticos por upgrade
4. **API REST**: Endpoints para gestión externa

---

## 🚨 **Solución de Problemas**

### **Error: "Only admin users can assign email plans"**
**Solución:**
```bash
# Verificar roles del usuario
php artisan tinker
>>> User::find(1)->getRoleNames();

# Asignar rol admin si es necesario
>>> User::find(1)->assignRole('admin');
```

### **Error: Team no puede enviar emails**
**Solución:**
```bash
# Verificar límites
php artisan email-plans:check --team-id=1

# Asignar plan superior
php artisan email-plans:assign 1 foundation

# O resetear límites manualmente (solo para testing)
php artisan email-plans:check --team-id=1 --reset-limits
```

### **Deliveries no respetan límites**
**Verificar:**
1. ✅ Migración ejecutada
2. ✅ Team model usa HasEmailLimits trait
3. ✅ Comando modificado correctamente

---

## 📝 **Instalación Completa**

### **1. Ejecutar Migraciones:**
```bash
php artisan migrate
```

### **2. Inicializar Teams Existentes:**
```bash
php artisan db:seed --class=EmailPlansSeeder
```

### **3. Verificar Instalación:**
```bash
php artisan email-plans:check
```

### **4. Asignar Planes Específicos:**
```bash
# Ejemplo: Team 1 = Foundation, Team 2 = Scale
php artisan email-plans:assign 1 foundation
php artisan email-plans:assign 2 scale
```

---

## 🎉 **Resultado Final**

**Sistema de Planes de Email completamente funcional:**

✅ **3 Planes predefinidos** con límites claros
✅ **Control de acceso** solo para admins
✅ **Validación automática** antes de envío
✅ **Contadores automáticos** después de envío
✅ **Reset automático** diario y mensual
✅ **Comandos completos** para gestión
✅ **Integración perfecta** con sistema existente

**¡Tu sistema está listo para manejar planes de email profesionales por team!** 🚀📧
