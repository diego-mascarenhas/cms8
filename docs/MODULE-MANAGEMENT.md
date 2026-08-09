# Module Management Commands

Module management system for Humano with automatic migration installation.

## Available commands

### 1. List modules

Shows all available modules, their installation status, and statistics.

```bash
# List all modules
php artisan module:list

# List modules for a specific team
php artisan module:list --team=1

# Show only available modules (not installed)
php artisan module:list --available

# Show only installed modules
php artisan module:list --installed
```

**Output:**

- Installed: Module installed and active
- Available: Module available but not installed
- ✓/✗: Indicates whether the module has tables in the database
- Teams: Number of teams that have the module active

---

### 2. Install module

Installs a module and automatically runs:

- Database migrations
- Asset publishing (config, views, migrations)
- Activation for teams
- Seeders (initial data)

```bash
# Install for all teams
php artisan module:install billing

# Install for a specific team
php artisan module:install billing --team=1

# Install without running migrations
php artisan module:install billing --skip-migrations

# Install without running seeders
php artisan module:install billing --skip-seeders

# Force reinstallation
php artisan module:install billing --force
```

**Installation process:**

1. **Verification**: Confirms the module exists
2. **Migrations**: Runs `php artisan migrate` to create tables
3. **Assets**: Publishes package configs, views, and migrations
4. **Activation**: Enables the module for the specified teams
5. **Seeders**: Runs seeders for initial data (payment types, etc.)
6. **Summary**: Shows installation statistics

**Example output:**

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

### 3. Uninstall module

Deactivates a module for teams without deleting database data.

```bash
# Uninstall for all teams
php artisan module:uninstall billing

# Uninstall for a specific team
php artisan module:uninstall billing --team=1

# Uninstall without confirmation
php artisan module:uninstall billing --force
```

**Important:**

- Does NOT delete tables or database data
- Only deactivates the module in the system
- Data remains intact and safe
- You can reinstall at any time with `module:install`

---

## Common use cases

### Initial project installation

```bash
# Install core modules for all teams
php artisan module:install billing
php artisan module:install ecommerce
php artisan module:install tickets
```

### Enable a module for a new team

```bash
# When you create a new team, enable the required modules
php artisan module:install billing --team=3
php artisan module:install projects --team=3
```

### Check module status

```bash
# See which modules a team has installed
php artisan module:list --team=1

# See which modules are available but not installed
php artisan module:list --available
```

### Reinstall a module (with --force)

```bash
# If there was a problem during installation
php artisan module:install billing --force
```

---

## Available modules

### Core modules (always installed)

- `dashboard` — Main panel and analytics
- `users` — User management
- `settings` — System configuration
- `contacts` — Contact management
- `tasks` — Task management
- `campaigns` — Marketing campaigns
- `templates` — Email templates
- `messages` — Messaging and email

### Add-on modules (optional installation)

- `billing` — Invoices and payments
- `ecommerce` — Online store
- `tickets` — Ticket system
- `academy` — Courses and educational content
- `mailbox` — Team mailbox
- `chat` — Live chat
- `infrastructure` — Hosting and server management
- `projects` — Project management
- `services` — Service management
- `enterprises` — Company management

---

## Troubleshooting

### Error: "Module not found"

```bash
# Verify that the module exists
php artisan module:list

# If it does not exist, run the module seeder
php artisan db:seed --class=ModuleSeeder
```

### Error: "Team not found"

```bash
# Verify available team IDs
php artisan tinker
>>> App\Models\Team::all(['id', 'name'])
```

### Migrations do not run

```bash
# Run migrations manually
php artisan migrate

# Or reinstall the module without skip
php artisan module:install billing --force
```

### Module installed but does not appear

```bash
# Clear Laravel cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

---

## For developers

### Create a new module

1. Register the module in `ModuleSeeder.php`
2. Create the package following the `humano-billing` structure
3. Register the package in `ModuleInstall::$modulePackages`
4. If it has seeders, add them to `ModuleInstall::runModuleSeeders()`

### Package structure

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

### Module service provider

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

## Important notes

1. **Automatic migrations**: The `module:install` command automatically runs pending migrations
2. **Optional seeders**: You can skip seeders with `--skip-seeders` if you do not want initial data
3. **Per-team installation**: Modules can be installed globally or for specific teams
4. **Persistent data**: Uninstalling a module does NOT delete database data
5. **Safe reinstallation**: You can run `module:install` multiple times without issues

---

## Practical examples

### Example 1: Initial setup for Revision Alpha

```bash
# Create the team and its modules
php artisan db:seed --class=TeamRevisionAlphaSeeder

# Install additional modules
php artisan module:install billing --team=2
php artisan module:install infrastructure --team=2

# Verify installation
php artisan module:list --team=2
```

### Example 2: Enable e-commerce for a client

```bash
# New client needs an online store
php artisan module:install ecommerce --team=5
php artisan module:install billing --team=5

# Verify everything is OK
php artisan module:list --team=5 --installed
```

### Example 3: Module maintenance

```bash
# Find modules with problems (no tables but installed)
php artisan module:list | grep "✅.*✗"

# Reinstall modules with problems
php artisan module:install billing --force
```

---

**Created:** 2025-10-05
**Version:** 1.0.0
**Author:** Humano Development Team
