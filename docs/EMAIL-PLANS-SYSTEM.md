# Email Plans System — Per-Team Limit Control

## System overview

Complete email plan management system that assigns specific limits to each team based on three service levels: **BASIC**, **FOUNDATION**, and **SCALE**. Only users with the **admin** role can assign and modify plans.

### Available plans:

| Plan | Description | Emails/Month | Emails/Day | Contacts | Ideal for |
|------|-------------|--------------|------------|----------|-----------|
| **BASIC** | Ideal to get started | 10,000 | 500 | 3,000 | Small businesses |
| **FOUNDATION** | For growing companies | 50,000 | 2,000 | 20,000 | Mid-size companies |
| **SCALE** | For large organizations | 100,000 | Unlimited | 50,000 | Large enterprises |

---

## System architecture

### 1. Database (migration)
```sql
-- Columns added to the teams table
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

### 2. System files

#### Plan enum:
- `app/Enums/EmailPlan.php` — Defines the 3 plans and their limits

#### Functionality trait:
- `app/Traits/HasEmailLimits.php` — Limit logic for Teams

#### Console commands:
- `app/Console/Commands/AssignEmailPlan.php` — Assign plans
- `app/Console/Commands/CheckEmailLimits.php` — Check status

#### Updated model:
- `app/Models/Team.php` — Includes the HasEmailLimits trait

#### Seeder:
- `database/seeders/EmailPlansSeeder.php` — Initializes existing teams

#### Migration:
- `database/migrations/2025_08_25_220000_add_email_limits_to_teams_table.php`

---

## Main commands

### 1. Assign a plan to a team
```bash
# Syntax
php artisan email-plans:assign {team_id} {plan} [--admin-id=X]

# Examples
php artisan email-plans:assign 1 basic
php artisan email-plans:assign 2 foundation --admin-id=1
php artisan email-plans:assign 3 scale

# List all teams
php artisan email-plans:assign 0 basic --list-teams
```

### 2. Check limit status
```bash
# View status for all teams
php artisan email-plans:check

# View a specific team
php artisan email-plans:check --team-id=1

# View only teams with problems
php artisan email-plans:check --over-limits

# Reset limits automatically
php artisan email-plans:check --reset-limits

# JSON output
php artisan email-plans:check --format=json
```

### 3. Initialize the system
```bash
# Run migration
php artisan migrate

# Initialize existing teams with the BASIC plan
php artisan db:seed --class=EmailPlansSeeder
```

---

## Access control and validations

### Only admin can assign plans:
```php
// In HasEmailLimits trait
public function assignEmailPlan(EmailPlan $plan, int $assignedByUserId): bool
{
    $assignedBy = User::find($assignedByUserId);
    if (!$assignedBy || !$assignedBy->hasRole('admin')) {
        throw new Exception('Only admin users can assign email plans');
    }
    // ... rest of the logic
}
```

### Automatic validations:
- Check limits before sending
- Increment counters after sending
- Automatic daily/monthly reset
- Contact limit validation per team

---

## How the system works

### 1. Limit check (before sending)
```php
// In SendAllPendingNow.php
if (!$delivery->team->canSendEmails(1)) {
    $remaining = $delivery->team->getRemainingEmails();
    $this->warn("Team '{$delivery->team->name}' has reached email limits:");
    $this->warn("Monthly: {$remaining['monthly_used']}/{$remaining['monthly_limit']}");
    // ... skip email
}
```

### 2. Counter increment (after sending)
```php
// After successful dispatch
$delivery->team->incrementEmailUsage(1);
```

### 3. Automatic limit reset
```php
// Monthly reset: first day of the month
// Daily reset: every day at 00:00
$team->resetLimitsIfNeeded(); // Runs automatically
```

---

## Use cases

### Scenario 1: Assign FOUNDATION plan to a team
```bash
# 1. List available teams
php artisan email-plans:assign 0 basic --list-teams

# 2. Assign plan
php artisan email-plans:assign 2 foundation

# 3. Verify assignment
php artisan email-plans:check --team-id=2
```

### Scenario 2: Check teams with problems
```bash
# View only teams that exceeded limits
php artisan email-plans:check --over-limits

# Expected result:
# ⚠️  Teams over limits need attention:
#   • My Company: Over monthly emails, contacts
```

### Scenario 3: Bulk send with limits
```bash
# The command automatically respects limits
php artisan emails:send-all-now --message-id=3

# Result:
# ⚠️  Team 'My Company' has reached email limits:
#     Monthly: 9950/10000
#     Daily: 450/500
```

---

## Metrics and monitoring

### Team status indicators:
- Green: < 80% of the limit
- Yellow: 80–99% of the limit
- Red: 100%+ of the limit

### Available information:
```php
$team = Team::find(1);

// Current status
$remaining = $team->getRemainingEmails();
// [
//     'monthly_remaining' => 5000,
//     'daily_remaining' => 300,
//     'monthly_used' => 5000,
//     'daily_used' => 200,
//     'monthly_limit' => 10000,
//     'daily_limit' => 500,
// ]

// Plan configuration
$config = $team->getEmailPlanConfig();
// [
//     'name' => 'Foundation',
//     'monthly_limit' => 50000,
//     'daily_limit' => 2000,
//     'contact_limit' => 20000,
//     'assigned_by' => 'Admin User',
//     // ... more info
// ]
```

---

## Integration with the existing system

### Changes in existing commands:

#### SendAllPendingNow.php:
```php
// ✅ Added: Limit check before sending
if (!$delivery->team->canSendEmails(1)) {
    // Skip this delivery
}

// ✅ Added: Counter increment after successful send
$delivery->team->incrementEmailUsage(1);
```

#### Team.php model:
```php
// ✅ Added: Trait for limit functionality
use HasEmailLimits;

// ✅ Added: Fillable fields for plans
protected $fillable = [
    'email_plan', 'email_monthly_limit', // ...
];

// ✅ Added: Relationship with contacts
public function contacts() {
    return $this->hasMany(Contact::class);
}
```

---

## Considerations and limitations

### Current limitations:
1. **Admin-only assignment**: Requires the `admin` role
2. **Automatic reset only**: No manual reset outside time-based rules
3. **One plan per team**: No multiple or custom plans

### Future extensions:
1. **Custom plans**: Per-team custom limits
2. **Notifications**: Automatic alerts at 80% of the limit
3. **Billing integration**: Automatic charges on upgrade
4. **REST API**: Endpoints for external management

---

## Troubleshooting

### Error: "Only admin users can assign email plans"
**Solution:**
```bash
# Verify user roles
php artisan tinker
>>> User::find(1)->getRoleNames();

# Assign admin role if needed
>>> User::find(1)->assignRole('admin');
```

### Error: Team cannot send emails
**Solution:**
```bash
# Verify limits
php artisan email-plans:check --team-id=1

# Assign a higher plan
php artisan email-plans:assign 1 foundation

# Or reset limits manually (testing only)
php artisan email-plans:check --team-id=1 --reset-limits
```

### Deliveries do not respect limits
**Verify:**
1. Migration has been run
2. Team model uses the HasEmailLimits trait
3. Command was modified correctly

---

## Full installation

### 1. Run migrations:
```bash
php artisan migrate
```

### 2. Initialize existing teams:
```bash
php artisan db:seed --class=EmailPlansSeeder
```

### 3. Verify installation:
```bash
php artisan email-plans:check
```

### 4. Assign specific plans:
```bash
# Example: Team 1 = Foundation, Team 2 = Scale
php artisan email-plans:assign 1 foundation
php artisan email-plans:assign 2 scale
```

---

## Final result

**Fully functional email plans system:**

- **3 predefined plans** with clear limits
- **Access control** for admins only
- **Automatic validation** before sending
- **Automatic counters** after sending
- **Automatic daily and monthly reset**
- **Complete management commands**
- **Clean integration** with the existing system

Your system is ready to manage professional per-team email plans.
