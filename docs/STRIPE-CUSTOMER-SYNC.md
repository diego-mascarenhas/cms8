# Sincronización de Clientes con Stripe

Esta documentación explica el sistema de sincronización entre Stripe Customers y la aplicación Laravel, incluyendo la gestión de usuarios, teams y nombres de contacto.

## 📋 Tabla de Contenidos

- [Comandos Disponibles](#comandos-disponibles)
- [Estructura de Datos](#estructura-de-datos)
- [Flujo de Sincronización](#flujo-de-sincronización)
- [Casos de Uso](#casos-de-uso)
- [Troubleshooting](#troubleshooting)

---

## 🚀 Comandos Disponibles

### 1. `stripe:customer-report`

Genera un reporte completo de los clientes en Stripe vs los teams locales.

**Uso:**
```bash
php artisan stripe:customer-report
```

**Output:**
- Lista de clientes en Stripe con:
  - Customer ID
  - Business Name (razón social)
  - Contact Name (nombre del contacto particular)
  - Email
  - Fecha de creación
- Lista de teams locales con su estado de sincronización
- Resumen de sincronización

**Ejemplo de salida:**
```
┌──────────────────┬────────────────┬─────────────────┬──────────────────────┬────────────┐
│ Customer ID      │ Business Name  │ Contact Name    │ Email                │ Created    │
├──────────────────┼────────────────┼─────────────────┼──────────────────────┼────────────┤
│ cus_TgOXIFdhx... │ Acme Corp      │ John Doe        │ john@acme.com        │ 2025-12-20 │
│ cus_123456789... │ Tech Solutions │ N/A             │ contact@tech.com     │ 2025-12-15 │
└──────────────────┴────────────────┴─────────────────┴──────────────────────┴────────────┘

✅ Synced teams: 5
❌ Not synced teams: 2
📊 Total Stripe customers: 7
```

---

### 2. `stripe:sync-customers`

Sincroniza los clientes de Stripe con los teams y usuarios locales.

**Uso:**
```bash
# Ver qué se sincronizaría sin hacer cambios
php artisan stripe:sync-customers --dry-run

# Ver qué se crearía sin hacer cambios
php artisan stripe:sync-customers --create --dry-run

# Sincronizar solo teams existentes
php artisan stripe:sync-customers

# Sincronizar y crear teams/usuarios para nuevos customers
php artisan stripe:sync-customers --create
```

**Opciones:**
- `--create`: Crea teams y usuarios para customers que no existen localmente
- `--dry-run`: Muestra qué cambios se harían sin aplicarlos

**Lógica de Sincronización:**

1. **Crea/recupera el usuario** usando `User::firstOrCreate` por email
2. **Busca team existente** por `stripe_id`
3. **Si existe el team:**
   - Actualiza el `user_id` al usuario correcto
   - Adjunta el usuario al team si no está conectado
   - Establece como `current_team_id` si es necesario
4. **Si NO existe el team:**
   - Verifica si el usuario ya tiene un team
   - Si tiene team, le asigna el `stripe_id`
   - Si no tiene team, crea uno nuevo

**Ejemplo de salida:**
```
Processing customer: cus_TgOXIFdhxq1gat (tester@revisionalpha.com)
  ✅ Updated team owner to user: tester@revisionalpha.com
  ✅ Attached user to existing team
  ℹ️  Team already exists with stripe_id: cus_TgOXIFdhxq1gat

📊 Synchronization Summary:
┌──────────────────┬───────┐
│ Metric           │ Count │
├──────────────────┼───────┤
│ Total Customers  │ 3     │
│ Updated          │ 2     │
│ Created          │ 1     │
│ Skipped          │ 0     │
│ Errors           │ 0     │
└──────────────────┴───────┘
```

---

### 3. `stripe:sync-customer-names`

Sincroniza los nombres de contacto desde Stripe a la tabla `users`.

**Uso:**
```bash
# Ver qué nombres se actualizarían sin hacer cambios
php artisan stripe:sync-customer-names --dry-run

# Aplicar la sincronización de nombres
php artisan stripe:sync-customer-names
```

**Opciones:**
- `--dry-run`: Muestra qué cambios se harían sin aplicarlos

**Lógica de Nombres:**

Prioriza `individual_name` sobre `name` (business name):

```php
// Estrategia de fallback
$newName = $customer->individual_name ?? $customer->name;
```

1. **Si existe `individual_name`** → Lo usa (nombre del contacto particular)
2. **Si NO existe `individual_name`** → Usa `name` (razón social)
3. **Si ninguno existe** → Salta ese customer

**Ejemplo de salida:**
```
🔄 Syncing Stripe customer names to users...

Found 3 teams with Stripe ID

✅ tester@revisionalpha.com:
   Old: Tester INC.
   New: Diego Testing
   Source: Contact Name

✅ contact@business.com:
   Old: John Smith
   New: Acme Corporation
   Source: Business Name

📊 Summary:
✅ Updated: 2
⏭️  Skipped: 1
❌ Errors: 0
```

---

## 📊 Estructura de Datos

### Tabla `subscription_items`

Esta tabla es parte de **Laravel Cashier** y gestiona suscripciones con múltiples productos o precios.

**Propósito:**
- Almacena los **elementos individuales** que componen una suscripción
- Permite suscripciones con uno o múltiples items (productos/servicios)
- Maneja cantidades variables por item

**Campos principales:**
- `subscription_id`: Relaciona con la suscripción padre
- `stripe_id`: ID del item en Stripe (ej: `si_xxx`)
- `stripe_product`: ID del producto en Stripe (ej: `prod_xxx`)
- `stripe_price`: ID del precio en Stripe (ej: `price_xxx`)
- `quantity`: Cantidad de unidades del item

**Ejemplos de uso:**

**Suscripción Simple (1 item):**
```
Usuario paga $10/mes por "Plan Básico"
→ 1 registro en subscriptions
→ 1 registro en subscription_items
```

**Suscripción Múltiple (varios items):**
```
Usuario paga:
  - $10/mes por "Plan Básico"
  - $5/mes por "Feature Extra"
  - $3/mes por "Soporte Prioritario"
→ 1 registro en subscriptions
→ 3 registros en subscription_items
```

**Suscripciones con cantidad variable:**
```
Usuario paga $5/mes por cada "seat" o usuario
Si tiene 10 usuarios: quantity = 10
```

### Relación entre Modelos

```
Stripe Customer
├── name → Team.name (razón social / nombre empresa)
├── individual_name → User.name (nombre contacto particular)
├── email → User.email
└── id → Team.stripe_id

Team (teams)
├── id
├── user_id → User.id (owner)
├── name (business name)
├── stripe_id → Stripe Customer ID
└── Relación: belongsTo(User), belongsToMany(User) via team_user

User (users)
├── id
├── name (contact name from Stripe)
├── email
├── current_team_id → Team.id
└── Relación: hasMany(Team), belongsToMany(Team) via team_user
```

---

## 🔄 Flujo de Sincronización

### Escenario 1: Cliente Nuevo en Stripe

```bash
php artisan stripe:sync-customers --create
```

**Flujo:**
1. ✅ Crea usuario con email del customer
2. ✅ Crea team con `stripe_id`
3. ✅ Adjunta usuario al team como admin
4. ✅ Establece como `current_team_id`

**Resultado:**
```sql
INSERT INTO users (email, name, ...) VALUES ('john@acme.com', 'Acme Corp', ...);
INSERT INTO teams (user_id, name, stripe_id, ...) VALUES (1, 'Acme Corp', 'cus_123...', ...);
INSERT INTO team_user (team_id, user_id, role) VALUES (1, 1, 'admin');
UPDATE users SET current_team_id = 1 WHERE id = 1;
```

---

### Escenario 2: Usuario Eliminado, Team Existe

**Situación inicial:**
- Usuario (id: 6) → **ELIMINADO** ❌
- Team (id: 4, stripe_id: 'cus_TgOX...') → **EXISTE** ✅

**Comando:**
```bash
php artisan stripe:sync-customers --create
```

**Flujo:**
1. ✅ Crea nuevo usuario (id: 7) con mismo email
2. ✅ Encuentra team existente por `stripe_id`
3. ✅ Actualiza `team.user_id` → 7 (nuevo usuario)
4. ✅ Adjunta usuario al team
5. ✅ Establece como `current_team_id`

**Resultado:**
```sql
-- Crea nuevo usuario
INSERT INTO users (email, name, ...) VALUES ('tester@revisionalpha.com', 'Tester INC.', ...);

-- NO crea team duplicado, actualiza el existente
UPDATE teams SET user_id = 7 WHERE id = 4;

-- Adjunta usuario al team
INSERT INTO team_user (team_id, user_id, role) VALUES (4, 7, 'admin');

-- Establece como current team
UPDATE users SET current_team_id = 4 WHERE id = 7;
```

**✅ Ventaja:** No se crean teams duplicados

---

### Escenario 3: Sincronizar Nombres de Contacto

**Situación inicial:**
- Customer en Stripe:
  - `name`: "Tester INC." (business name)
  - `individual_name`: "Diego Testing" (contact name)
- Usuario local:
  - `name`: "Tester INC."

**Comando:**
```bash
php artisan stripe:sync-customer-names
```

**Flujo:**
1. ✅ Lee `individual_name` de Stripe
2. ✅ Si existe, lo usa para `users.name`
3. ✅ Si NO existe, usa `name` (business name)

**Resultado:**
```sql
UPDATE users SET name = 'Diego Testing' WHERE email = 'tester@revisionalpha.com';
```

---

### Escenario 4: Customer sin `individual_name`

**Situación inicial:**
- Customer en Stripe:
  - `name`: "Acme Corporation"
  - `individual_name`: **null** ❌
- Usuario local:
  - `name`: "Admin User"

**Comando:**
```bash
php artisan stripe:sync-customer-names
```

**Flujo:**
1. ✅ Lee `individual_name` → **null**
2. ✅ **Fallback:** Usa `name` (business name)
3. ✅ Actualiza `users.name` con "Acme Corporation"

**Resultado:**
```sql
UPDATE users SET name = 'Acme Corporation' WHERE email = 'admin@acme.com';
```

**Output del comando:**
```
✅ admin@acme.com:
   Old: Admin User
   New: Acme Corporation
   Source: Business Name  ← Indica que usó el business name
```

---

## 💡 Casos de Uso

### Caso 1: Migración Inicial desde Stripe

Tienes clientes en Stripe y quieres importarlos a tu aplicación:

```bash
# 1. Ver qué se importaría
php artisan stripe:customer-report

# 2. Hacer un dry-run de la sincronización
php artisan stripe:sync-customers --create --dry-run

# 3. Aplicar la importación
php artisan stripe:sync-customers --create

# 4. Sincronizar nombres de contacto
php artisan stripe:sync-customer-names

# 5. Verificar resultado
php artisan stripe:customer-report
```

---

### Caso 2: Actualización Regular de Datos

Sincronización periódica para mantener datos actualizados:

```bash
# Sincronizar teams existentes (sin crear nuevos)
php artisan stripe:sync-customers

# Actualizar nombres desde Stripe
php artisan stripe:sync-customer-names
```

**Recomendación:** Agregar a scheduler en `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Sincronizar diariamente a las 2 AM
    $schedule->command('stripe:sync-customers')->dailyAt('02:00');
    
    // Actualizar nombres semanalmente
    $schedule->command('stripe:sync-customer-names')->weekly();
}
```

---

### Caso 3: Recuperación de Usuario Eliminado

Un usuario fue eliminado accidentalmente pero su team y datos en Stripe siguen existiendo:

```bash
# 1. Verificar estado actual
php artisan stripe:customer-report

# 2. Recrear usuario y reconectar con team
php artisan stripe:sync-customers --create

# 3. Restaurar nombre correcto
php artisan stripe:sync-customer-names
```

El sistema:
- ✅ Recrea el usuario con mismo email
- ✅ Reutiliza el team existente (no crea duplicado)
- ✅ Restaura todas las relaciones
- ✅ Mantiene el `stripe_id` y datos de suscripción

---

### Caso 4: Limpiar Datos Inconsistentes

Tienes teams sin `stripe_id` pero los customers existen en Stripe:

```bash
# 1. Identificar problemas
php artisan stripe:customer-report

# 2. Sincronizar para asignar stripe_id
php artisan stripe:sync-customers

# 3. Verificar que todo esté sincronizado
php artisan stripe:customer-report
```

---

## 🐛 Troubleshooting

### Error: "No API key provided"

**Causa:** La API key de Stripe no está configurada.

**Solución:**
```bash
# Verificar .env
grep STRIPE .env

# Debe tener:
STRIPE_KEY=pk_test_xxxxx
STRIPE_SECRET=sk_test_xxxxx
```

---

### Error: "Team already has different stripe_id"

**Causa:** El team ya tiene un `stripe_id` diferente al del customer en Stripe.

**Solución:**

1. **Verificar en la base de datos:**
```sql
SELECT id, name, stripe_id FROM teams WHERE stripe_id IS NOT NULL;
```

2. **Verificar en Stripe:**
```bash
php artisan stripe:customer-report
```

3. **Corregir manualmente si es necesario:**
```sql
-- Si el stripe_id es incorrecto
UPDATE teams SET stripe_id = 'cus_correcto' WHERE id = X;

-- O eliminar para que se reasigne
UPDATE teams SET stripe_id = NULL WHERE id = X;
```

4. **Volver a sincronizar:**
```bash
php artisan stripe:sync-customers
```

---

### Teams Duplicados

**Causa:** Se crearon múltiples teams para el mismo customer antes de la corrección.

**Solución:**

1. **Identificar duplicados:**
```sql
SELECT stripe_id, COUNT(*) as count 
FROM teams 
WHERE stripe_id IS NOT NULL 
GROUP BY stripe_id 
HAVING count > 1;
```

2. **Eliminar duplicados (mantener el más reciente):**
```sql
-- Ejemplo: Eliminar team duplicado con id 5, mantener el 4
DELETE FROM team_user WHERE team_id = 5;
DELETE FROM teams WHERE id = 5;
```

3. **Sincronizar de nuevo:**
```bash
php artisan stripe:sync-customers --create
```

---

### Usuario sin `current_team_id`

**Causa:** El usuario no tiene asignado un team como actual.

**Solución:**

```bash
# El comando de sincronización lo corrige automáticamente
php artisan stripe:sync-customers
```

O manualmente:
```sql
UPDATE users u 
SET current_team_id = (
    SELECT t.id FROM teams t 
    WHERE t.user_id = u.id 
    LIMIT 1
) 
WHERE current_team_id IS NULL;
```

---

### Nombre Incorrecto en Usuario

**Causa:** El campo `individual_name` no está configurado en Stripe o está desactualizado.

**Solución:**

1. **Actualizar en Stripe Dashboard:**
   - Ir al customer en Stripe
   - Agregar/actualizar el campo `individual_name`

2. **Sincronizar:**
```bash
php artisan stripe:sync-customer-names
```

**Nota:** Si el customer no tiene `individual_name`, el comando usará automáticamente el `name` (business name) como fallback.

---

## 🔧 Configuración

### Variables de Entorno

```env
# Stripe Keys
STRIPE_KEY=pk_test_xxxxxxxxxxxxx
STRIPE_SECRET=sk_test_xxxxxxxxxxxxx

# Webhook (opcional, para sincronización automática)
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxx
```

### Webhooks de Stripe (Opcional)

Para sincronización automática cuando se actualiza un customer en Stripe:

1. **Configurar webhook en Stripe Dashboard:**
   - URL: `https://tu-app.test/stripe/webhook`
   - Eventos:
     - `customer.created`
     - `customer.updated`
     - `customer.deleted`

2. **El webhook de Cashier ya maneja estos eventos automáticamente**

---

## 📝 Notas Importantes

### Diferencia entre `name` e `individual_name` en Stripe

- **`name`**: Nombre de la empresa / Razón social
  - Usado para: Facturas, documentos legales, team name
  - Ejemplo: "Acme Corporation", "Tech Solutions SA"

- **`individual_name`**: Nombre del contacto / Persona particular
  - Usado para: Comunicación personal, notificaciones, user name
  - Ejemplo: "John Doe", "María García"

### Estrategia de Nombres

```php
// Para Team
$team->name = $customer->name; // Business name

// Para User (con fallback)
$user->name = $customer->individual_name ?? $customer->name;
```

### Prevención de Duplicados

El sistema previene duplicados verificando en este orden:

1. **Por `stripe_id`** en la tabla `teams`
2. **Por `email`** en la tabla `users`
3. **Por relación usuario-team** existente

---

## 🚀 Mejores Prácticas

1. **Siempre usar `--dry-run` primero** para ver qué cambios se harán
2. **Hacer backup de la BD** antes de sincronizaciones masivas
3. **Ejecutar reporte después** de cada sincronización para verificar
4. **Configurar scheduler** para mantener datos actualizados automáticamente
5. **Configurar webhooks** de Stripe para sincronización en tiempo real
6. **Mantener `individual_name` actualizado** en Stripe para mejores resultados

---

## 📚 Referencias

- [Laravel Cashier Documentation](https://laravel.com/docs/10.x/billing)
- [Stripe API - Customers](https://stripe.com/docs/api/customers)
- [Stripe API - Subscriptions](https://stripe.com/docs/api/subscriptions)
- [Stripe Webhooks](https://stripe.com/docs/webhooks)

---

**Última actualización:** 2025-12-27  
**Versión de Laravel:** 10.x  
**Versión de Cashier:** 15.x

