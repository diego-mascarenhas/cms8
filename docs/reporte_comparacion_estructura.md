# Reporte de Comparación de Estructura de Base de Datos

## Resumen Ejecutivo

- **Total tablas en LOCAL (humano_ok.sql)**: 116
- **Total tablas en PRODUCCIÓN (humano_prod.sql)**: 119
- **Diferencia**: 3 tablas adicionales en producción

---

## Tablas Adicionales en Producción

Las siguientes tablas existen en producción pero NO en local:

1. **`activity_log`** - Sistema de auditoría de Spatie (Activity Log)
2. **`cache`** - Sistema de caché de Laravel
3. **`cache_locks`** - Sistema de bloqueos de caché de Laravel

**Conclusión**: Estas son tablas del sistema y no afectan la funcionalidad de suscripciones. No requieren acción.

---

## Tablas Relacionadas con Suscripciones

### Estado de Existencia

Todas las tablas relacionadas con suscripciones **YA EXISTEN** en producción:

| Tabla | Estado en PROD | Notas |
|-------|----------------|-------|
| `subscriptions` | ✅ Existe | **Verificar columna `data`** |
| `subscription_products` | ✅ Existe | - |
| `stripe_subscriptions` | ✅ Existe | - |
| `subscription_changes` | ✅ Existe | - |
| `subscription_items` | ✅ Existe | - |
| `subscription_notifications` | ✅ Existe | - |

---

## Verificación Crítica: Columna `data` en `subscriptions`

### Estructura Esperada (LOCAL)

```sql
CREATE TABLE `subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `team_id` bigint unsigned DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_price` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `data` json DEFAULT NULL,  ← ⚠️ COLUMNA CRÍTICA
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscriptions_stripe_id_unique` (`stripe_id`),
  KEY `subscriptions_user_id_stripe_status_index` (`user_id`,`stripe_status`),
  KEY `subscriptions_team_id_stripe_status_index` (`team_id`,`stripe_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Acción Requerida

**Ejecutar en producción para verificar:**

```sql
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✅ La columna `data` existe'
        ELSE '❌ FALTA: La columna `data` NO existe'
    END AS estado
FROM INFORMATION_SCHEMA.COLUMNS
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'subscriptions'
    AND COLUMN_NAME = 'data';
```

**Si la columna NO existe, ejecutar:**

```sql
ALTER TABLE `subscriptions` 
ADD COLUMN `data` json NULL AFTER `ends_at`;
```

---

## Estructura de Tablas de Suscripciones

### 1. `subscription_products`

**Estado**: ✅ Existe en producción

**Propósito**: Almacena los productos de suscripción sincronizados con Stripe.

**Columnas clave**:
- `stripe_id`, `stripe_product`, `stripe_price`
- `category` (mailer, hosting, support, mentoring)
- `plan` (para mapear a EmailPlan enum)
- `unit_amount`, `currency`, `recurring_interval`

### 2. `stripe_subscriptions`

**Estado**: ✅ Existe en producción

**Propósito**: Tracking detallado de suscripciones de Stripe.

**Columnas clave**:
- `stripe_id`, `team_id`, `customer_id`
- `status`, `plan_name`, `plan_interval`
- `amount_total`, `current_period_start`, `current_period_end`
- `data` (json para metadata adicional)

### 3. `subscription_changes`

**Estado**: ✅ Existe en producción

**Propósito**: Auditoría de cambios en suscripciones.

**Columnas clave**:
- `subscription_id` (FK a stripe_subscriptions)
- `changed_fields`, `previous_values`, `current_values`
- `detected_at`

### 4. `subscription_items`

**Estado**: ✅ Existe en producción

**Propósito**: Items individuales dentro de una suscripción.

**Columnas clave**:
- `subscription_id` (FK a stripe_subscriptions)
- `stripe_id`, `stripe_product`, `stripe_price`
- `quantity`

### 5. `subscription_notifications`

**Estado**: ✅ Existe en producción

**Propósito**: Notificaciones automáticas de suscripciones.

**Columnas clave**:
- `subscription_id` (FK a stripe_subscriptions)
- `notification_type` (warning_5_days, warning_2_days, suspended, reactivated)
- `status` (pending, sent, failed)
- `recipient_email`, `recipient_name`

---

## Scripts de Verificación

### Script SQL Completo

Ver archivo: `docs/verificar_estructura_produccion.sql`

Este script verifica:
1. Existencia de columna `data` en `subscriptions`
2. Existencia de todas las tablas de suscripciones
3. Estructura completa de cada tabla
4. Índices y foreign keys
5. Resumen de tablas relacionadas

### Script de Migración

Ver archivo: `docs/migration_subscriptions_production.sql`

Este script aplica cambios de forma segura (solo si no existen).

---

## Recomendaciones

### ✅ Acciones Inmediatas

1. **Ejecutar script de verificación** en producción:
   ```bash
   mysql -u usuario -p nombre_base_datos < docs/verificar_estructura_produccion.sql
   ```

2. **Verificar columna `data`** en `subscriptions`:
   - Si NO existe, ejecutar el ALTER TABLE mencionado arriba
   - Esta columna es crítica para almacenar metadata (ej: dominio para hosting/support)

3. **Verificar índices**:
   - Asegurar que los índices en `subscriptions` estén presentes:
     - `subscriptions_stripe_id_unique`
     - `subscriptions_user_id_stripe_status_index`
     - `subscriptions_team_id_stripe_status_index`

### ⚠️ Consideraciones

- Las tablas `activity_log`, `cache`, `cache_locks` en producción son normales (sistema Laravel/Spatie)
- Todas las tablas de suscripciones ya existen en producción
- La única diferencia potencial es la columna `data` en `subscriptions`

---

## Conclusión

**Estado General**: ✅ **Estructura compatible**

La base de datos de producción está prácticamente sincronizada con la local. La única verificación crítica es la columna `data` en la tabla `subscriptions`, que es necesaria para el funcionamiento correcto del sistema de suscripciones (especialmente para almacenar el dominio en suscripciones de hosting/support).

---

**Fecha de comparación**: 2026-01-18
**Archivos comparados**:
- `docs/humano_ok.sql` (local)
- `docs/humano_prod.sql` (producción)
