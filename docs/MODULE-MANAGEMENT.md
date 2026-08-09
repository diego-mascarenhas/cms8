# Module Management Commands

Sistema de gestión de módulos para Humano con instalación automática de migraciones.

## 📦 Comandos Disponibles

### 1. Listar Módulos

Muestra todos los módulos disponibles, su estado de instalación y estadísticas.

```bash
# Listar todos los módulos
php artisan module:list

# Listar módulos de un equipo específico
php artisan module:list --team=1

# Mostrar solo módulos disponibles (no instalados)
php artisan module:list --available

# Mostrar solo módulos instalados
php artisan module:list --installed
```

**Salida:**

-   ✅ Installed: Módulo instalado y activo
-   ⬜ Available: Módulo disponible pero no instalado
-   ✓/✗: Indica si el módulo tiene tablas en la base de datos
-   Teams: Número de equipos que tienen el módulo activo

---

### 2. Instalar Módulo

Instala un módulo ejecutando automáticamente:

-   ✅ Migraciones de base de datos
-   ✅ Publicación de assets (config, views, migrations)
-   ✅ Activación para equipos
-   ✅ Seeders (datos iniciales)

```bash
# Instalar para todos los equipos
php artisan module:install billing

# Instalar para un equipo específico
php artisan module:install billing --team=1

# Instalar sin ejecutar migraciones
php artisan module:install billing --skip-migrations

# Instalar sin ejecutar seeders
php artisan module:install billing --skip-seeders

# Forzar reinstalación
php artisan module:install billing --force
```

**Proceso de instalación:**

1. **Verificación**: Comprueba que el módulo existe
2. **Migraciones**: Ejecuta `php artisan migrate` para crear tablas
3. **Assets**: Publica configuraciones, vistas y migraciones del paquete
4. **Activación**: Habilita el módulo para los equipos especificados
5. **Seeders**: Ejecuta seeders para datos iniciales (tipos de pago, etc.)
6. **Resumen**: Muestra estadísticas de la instalación

**Ejemplo de salida:**

```
🚀 Installing module: billing

✅ Module found: Billing
📦 Package: humano-billing

📊 Running migrations...
   ✓ No pending migrations

📂 Publishing package assets...
   ✓ Published humano-billing migrations
   ✓ Published humano-billing config
   ✓ Published humano-billing views

🔌 Enabling module for teams...
   ✓ Enabled for team: Demo's Team (ID: 1)
   ✓ Enabled for team: revision alpha's Team (ID: 2)

🌱 Running seeders...
   ✓ Seeder executed: PaymentTypeSeeder
   ✓ Seeder executed: InvoiceTypeSeeder

═══════════════════════════════════════════════
📋 INSTALLATION SUMMARY
═══════════════════════════════════════════════
Module Name:    Billing
Module Key:     billing
Package:        humano-billing
Type:           Add-on Module
Status:         Active
Installed for:  All teams (2 teams)

📊 Database Tables:
   • invoices (0 records)
   • invoice_items (0 records)
   • invoice_types (4 records)
   • payments (0 records)
   • payment_types (11 records)
   • payment_accounts (0 records)

✅ Module 'Billing' installed successfully!
```

---

### 3. Desinstalar Módulo

Desactiva un módulo para equipos sin eliminar datos de la base de datos.

```bash
# Desinstalar para todos los equipos
php artisan module:uninstall billing

# Desinstalar para un equipo específico
php artisan module:uninstall billing --team=1

# Desinstalar sin confirmación
php artisan module:uninstall billing --force
```

**⚠️ Importante:**

-   NO elimina tablas ni datos de la base de datos
-   Solo desactiva el módulo en el sistema
-   Los datos permanecen intactos y seguros
-   Puedes reinstalar en cualquier momento con `module:install`

---

## 🎯 Casos de Uso Comunes

### Instalación Inicial de Proyecto

```bash
# Instalar módulos core para todos los equipos
php artisan module:install billing
php artisan module:install ecommerce
php artisan module:install tickets
```

### Activar Módulo para Nuevo Equipo

```bash
# Cuando creas un equipo nuevo, activa los módulos necesarios
php artisan module:install billing --team=3
php artisan module:install projects --team=3
```

### Verificar Estado de Módulos

```bash
# Ver qué módulos tiene instalados un equipo
php artisan module:list --team=1

# Ver qué módulos están disponibles pero no instalados
php artisan module:list --available
```

### Reinstalar Módulo (con --force)

```bash
# Si hubo un problema en la instalación
php artisan module:install billing --force
```

---

## 📋 Módulos Disponibles

### Core Modules (Siempre instalados)

-   `dashboard` - Panel principal y analytics
-   `users` - Gestión de usuarios
-   `settings` - Configuración del sistema
-   `contacts` - Gestión de contactos
-   `tasks` - Gestión de tareas
-   `campaigns` - Campañas de marketing
-   `templates` - Plantillas de email
-   `messages` - Mensajería y email

### Add-on Modules (Instalación opcional)

-   `billing` - Facturas y pagos
-   `ecommerce` - Tienda online
-   `tickets` - Sistema de tickets
-   `academy` - Cursos y contenido educativo
-   `mailbox` - Buzón de correo del equipo
-   `chat` - Chat en vivo
-   `infrastructure` - Gestión de hosting y servidores
-   `projects` - Gestión de proyectos
-   `services` - Gestión de servicios
-   `enterprises` - Gestión de empresas

---

## 🔧 Troubleshooting

### Error: "Module not found"

```bash
# Verifica que el módulo existe
php artisan module:list

# Si no existe, ejecuta el seeder de módulos
php artisan db:seed --class=ModuleSeeder
```

### Error: "Team not found"

```bash
# Verifica los IDs de equipos disponibles
php artisan tinker
>>> App\Models\Team::all(['id', 'name'])
```

### Migraciones no se ejecutan

```bash
# Ejecuta manualmente las migraciones
php artisan migrate

# O reinstala el módulo sin skip
php artisan module:install billing --force
```

### Módulo instalado pero no aparece

```bash
# Limpia la caché de Laravel
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

---

## 🛠️ Para Desarrolladores

### Crear un Nuevo Módulo

1. Registra el módulo en `ModuleSeeder.php`
2. Crea el paquete siguiendo la estructura de `humano-billing`
3. Registra el paquete en `ModuleInstall::$modulePackages`
4. Si tiene seeders, añádelos a `ModuleInstall::runModuleSeeders()`

### Estructura de Package

```
packages/humano-{module}/
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/
│   └── views/
├── routes/
│   └── web.php
└── src/
    ├── {Module}ServiceProvider.php
    ├── Models/
    └── Http/
```

### Service Provider del Módulo

```php
public function configurePackage(Package $package): void
{
    $package
        ->name('humano-billing')
        ->hasConfigFile()
        ->hasViews()
        ->hasRoute('web')
        ->hasMigrations([
            '2024_03_01_000000_create_payment_types_table',
            '2024_03_01_000001_create_invoice_types_table',
        ]);
}
```

---

## 📝 Notas Importantes

1. **Migraciones automáticas**: El comando `module:install` ejecuta automáticamente las migraciones pendientes
2. **Seeders opcionales**: Puedes omitir seeders con `--skip-seeders` si no quieres datos iniciales
3. **Instalación por equipo**: Los módulos se pueden instalar globalmente o para equipos específicos
4. **Datos persistentes**: Desinstalar un módulo NO elimina datos de la base de datos
5. **Reinstalación segura**: Puedes ejecutar `module:install` múltiples veces sin problemas

---

## 🎓 Ejemplos Prácticos

### Ejemplo 1: Setup Inicial de Revision Alpha

```bash
# Crear el equipo y sus módulos
php artisan db:seed --class=TeamRevisionAlphaSeeder

# Instalar módulos adicionales
php artisan module:install billing --team=2
php artisan module:install infrastructure --team=2

# Verificar instalación
php artisan module:list --team=2
```

### Ejemplo 2: Activar E-commerce para Cliente

```bash
# Cliente nuevo necesita tienda online
php artisan module:install ecommerce --team=5
php artisan module:install billing --team=5

# Verificar que todo está OK
php artisan module:list --team=5 --installed
```

### Ejemplo 3: Mantenimiento de Módulos

```bash
# Ver módulos con problemas (sin tablas pero instalados)
php artisan module:list | grep "✅.*✗"

# Reinstalar módulos con problemas
php artisan module:install billing --force
```

---

**Creado:** 2025-10-05
**Versión:** 1.0.0
**Autor:** Humano Development Team
