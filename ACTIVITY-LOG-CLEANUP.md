# Activity Log Cleanup for Seeders

This project includes several methods to clean up activity log entries generated during database seeding operations.

## 🎯 Problem

When running seeders, the Activity Log package logs all model creation and updates, which creates noise in the activity log that isn't useful for production monitoring.

## ✅ Solution

We've implemented automatic and manual cleanup methods:

### 1. Automatic Cleanup (Recommended)

The `DatabaseSeeder` automatically clears all activity log entries after running all seeders:

```php
// database/seeders/DatabaseSeeder.php
public function run(): void
{
    $this->call([
        UserSeeder::class,
        ContactSeeder::class,
        // ... other seeders
    ]);

    // Automatically clears all activities generated during seeding
    $this->clearAllActivities();
}
```

### 2. Manual Cleanup Commands

#### Clear all activities:
```bash
php artisan activity:clear-seeding --all --force
```

#### Clear recent activities (last 5 minutes):
```bash
php artisan activity:clear-seeding --minutes=5 --force
```

#### Clear activities with confirmation:
```bash
php artisan activity:clear-seeding --all
# Will prompt for confirmation
```

### 3. Individual Seeder Cleanup

Use the `ClearsActivityLog` trait in specific seeders:

```php
<?php

namespace Database\Seeders;

use App\Traits\ClearsActivityLog;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    use ClearsActivityLog;

    public function run(): void
    {
        // Create contacts...
        Contact::factory(100)->create();

        // Clear only Contact model activities
        $this->clearActivitiesForModels([Contact::class]);
    }
}
```

### 4. Advanced Cleanup Options

#### Clear activities for specific models:
```php
$this->clearActivitiesForModels([
    Contact::class,
    User::class,
    Project::class
]);
```

#### Clear activities from specific users:
```php
$this->clearActivitiesForUsers([1, 2, 3]); // User IDs
```

#### Clear recent activities:
```php
$this->clearRecentActivities(10); // Last 10 minutes
```

## 🔧 Implementation Files

- **Command**: `app/Console/Commands/ClearSeedingActivities.php`
- **Trait**: `app/Traits/ClearsActivityLog.php`
- **Main Seeder**: `database/seeders/DatabaseSeeder.php`

## 🚀 Usage Examples

### During Development

```bash
# Run seeders and auto-clean activities
php artisan migrate:fresh --seed

# Or run specific seeder and clean manually
php artisan db:seed --class=ContactSeeder
php artisan activity:clear-seeding --minutes=2 --force
```

### In Production

```bash
# Only clear recent seeding activities (safer)
php artisan activity:clear-seeding --minutes=10 --force
```

## ⚠️ Notes

- The automatic cleanup in `DatabaseSeeder` clears **ALL** activities, not just seeding ones
- Use `--minutes` option if you want to preserve older activities
- Activities are permanently deleted (not soft deleted)
- Always test in development before using in production

## 🎨 Benefits

- ✅ Clean activity log after seeding
- ✅ Multiple cleanup strategies
- ✅ Confirmation prompts for safety
- ✅ Flexible time-based cleanup
- ✅ Model-specific cleanup
- ✅ User-specific cleanup
- ✅ Automatic integration with main seeder 