# Stripe Customer Synchronization

This documentation explains the synchronization system between Stripe Customers and the Laravel application, including user, team, and contact name management.

## Table of contents

- [Available commands](#available-commands)
- [Data structure](#data-structure)
- [Synchronization flow](#synchronization-flow)
- [Use cases](#use-cases)
- [Troubleshooting](#troubleshooting)

---

## Available commands

### 1. `stripe:customer-report`

Generates a full report of Stripe customers vs local teams.

**Usage:**
```bash
php artisan stripe:customer-report
```

**Output:**
- List of Stripe customers with:
  - Customer ID
  - Business Name (legal/business name)
  - Contact Name (individual contact name)
  - Email
  - Creation date
- List of local teams with their sync status
- Synchronization summary

**Sample output:**
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

Synchronizes Stripe customers with local teams and users.

**Usage:**
```bash
# Preview what would be synced without making changes
php artisan stripe:sync-customers --dry-run

# Preview what would be created without making changes
php artisan stripe:sync-customers --create --dry-run

# Sync existing teams only
php artisan stripe:sync-customers

# Sync and create teams/users for new customers
php artisan stripe:sync-customers --create
```

**Options:**
- `--create`: Create teams and users for customers that do not exist locally
- `--dry-run`: Show what changes would be made without applying them

**Synchronization logic:**

1. **Create/retrieve the user** using `User::firstOrCreate` by email
2. **Find an existing team** by `stripe_id`
3. **If the team exists:**
   - Update `user_id` to the correct user
   - Attach the user to the team if not already connected
   - Set as `current_team_id` if needed
4. **If the team does NOT exist:**
   - Check whether the user already has a team
   - If they have a team, assign the `stripe_id`
   - If they do not have a team, create a new one

**Sample output:**
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

Synchronizes contact names from Stripe to the `users` table.

**Usage:**
```bash
# Preview which names would be updated without making changes
php artisan stripe:sync-customer-names --dry-run

# Apply name synchronization
php artisan stripe:sync-customer-names
```

**Options:**
- `--dry-run`: Show what changes would be made without applying them

**Name logic:**

Prioritizes `individual_name` over `name` (business name):

```php
// Fallback strategy
$newName = $customer->individual_name ?? $customer->name;
```

1. **If `individual_name` exists** → Use it (individual contact name)
2. **If `individual_name` does NOT exist** → Use `name` (business name)
3. **If neither exists** → Skip that customer

**Sample output:**
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

## Data structure

### `subscription_items` table

This table is part of **Laravel Cashier** and manages subscriptions with multiple products or prices.

**Purpose:**
- Stores the **individual items** that make up a subscription
- Supports subscriptions with one or multiple items (products/services)
- Handles variable quantities per item

**Main fields:**
- `subscription_id`: Relates to the parent subscription
- `stripe_id`: Item ID in Stripe (e.g. `si_xxx`)
- `stripe_product`: Product ID in Stripe (e.g. `prod_xxx`)
- `stripe_price`: Price ID in Stripe (e.g. `price_xxx`)
- `quantity`: Number of units for the item

**Usage examples:**

**Simple subscription (1 item):**
```
User pays $10/month for "Basic Plan"
→ 1 record in subscriptions
→ 1 record in subscription_items
```

**Multi-item subscription:**
```
User pays:
  - $10/month for "Basic Plan"
  - $5/month for "Extra Feature"
  - $3/month for "Priority Support"
→ 1 record in subscriptions
→ 3 records in subscription_items
```

**Subscriptions with variable quantity:**
```
User pays $5/month per "seat" or user
If they have 10 users: quantity = 10
```

### Model relationships

```
Stripe Customer
├── name → Team.name (legal / business name)
├── individual_name → User.name (individual contact name)
├── email → User.email
└── id → Team.stripe_id

Team (teams)
├── id
├── user_id → User.id (owner)
├── name (business name)
├── stripe_id → Stripe Customer ID
└── Relation: belongsTo(User), belongsToMany(User) via team_user

User (users)
├── id
├── name (contact name from Stripe)
├── email
├── current_team_id → Team.id
└── Relation: hasMany(Team), belongsToMany(Team) via team_user
```

---

## Synchronization flow

### Scenario 1: New customer in Stripe

```bash
php artisan stripe:sync-customers --create
```

**Flow:**
1. Creates a user with the customer email
2. Creates a team with `stripe_id`
3. Attaches the user to the team as admin
4. Sets it as `current_team_id`

**Result:**
```sql
INSERT INTO users (email, name, ...) VALUES ('john@acme.com', 'Acme Corp', ...);
INSERT INTO teams (user_id, name, stripe_id, ...) VALUES (1, 'Acme Corp', 'cus_123...', ...);
INSERT INTO team_user (team_id, user_id, role) VALUES (1, 1, 'admin');
UPDATE users SET current_team_id = 1 WHERE id = 1;
```

---

### Scenario 2: User deleted, team exists

**Initial situation:**
- User (id: 6) → **DELETED**
- Team (id: 4, stripe_id: 'cus_TgOX...') → **EXISTS**

**Command:**
```bash
php artisan stripe:sync-customers --create
```

**Flow:**
1. Creates a new user (id: 7) with the same email
2. Finds the existing team by `stripe_id`
3. Updates `team.user_id` → 7 (new user)
4. Attaches the user to the team
5. Sets it as `current_team_id`

**Result:**
```sql
-- Creates new user
INSERT INTO users (email, name, ...) VALUES ('tester@revisionalpha.com', 'Tester INC.', ...);

-- Does NOT create a duplicate team; updates the existing one
UPDATE teams SET user_id = 7 WHERE id = 4;

-- Attaches user to the team
INSERT INTO team_user (team_id, user_id, role) VALUES (4, 7, 'admin');

-- Sets as current team
UPDATE users SET current_team_id = 4 WHERE id = 7;
```

**Advantage:** Duplicate teams are not created

---

### Scenario 3: Sync contact names

**Initial situation:**
- Customer in Stripe:
  - `name`: "Tester INC." (business name)
  - `individual_name`: "Diego Testing" (contact name)
- Local user:
  - `name`: "Tester INC."

**Command:**
```bash
php artisan stripe:sync-customer-names
```

**Flow:**
1. Reads `individual_name` from Stripe
2. If it exists, uses it for `users.name`
3. If it does NOT exist, uses `name` (business name)

**Result:**
```sql
UPDATE users SET name = 'Diego Testing' WHERE email = 'tester@revisionalpha.com';
```

---

### Scenario 4: Customer without `individual_name`

**Initial situation:**
- Customer in Stripe:
  - `name`: "Acme Corporation"
  - `individual_name`: **null**
- Local user:
  - `name`: "Admin User"

**Command:**
```bash
php artisan stripe:sync-customer-names
```

**Flow:**
1. Reads `individual_name` → **null**
2. **Fallback:** Uses `name` (business name)
3. Updates `users.name` with "Acme Corporation"

**Result:**
```sql
UPDATE users SET name = 'Acme Corporation' WHERE email = 'admin@acme.com';
```

**Command output:**
```
✅ admin@acme.com:
   Old: Admin User
   New: Acme Corporation
   Source: Business Name  ← Indicates that business name was used
```

---

## Use cases

### Case 1: Initial migration from Stripe

You have customers in Stripe and want to import them into your application:

```bash
# 1. See what would be imported
php artisan stripe:customer-report

# 2. Dry-run the synchronization
php artisan stripe:sync-customers --create --dry-run

# 3. Apply the import
php artisan stripe:sync-customers --create

# 4. Sync contact names
php artisan stripe:sync-customer-names

# 5. Verify the result
php artisan stripe:customer-report
```

---

### Case 2: Regular data updates

Periodic sync to keep data up to date:

```bash
# Sync existing teams (without creating new ones)
php artisan stripe:sync-customers

# Update names from Stripe
php artisan stripe:sync-customer-names
```

**Recommendation:** Add to the scheduler in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Sync daily at 2 AM
    $schedule->command('stripe:sync-customers')->dailyAt('02:00');

    // Update names weekly
    $schedule->command('stripe:sync-customer-names')->weekly();
}
```

---

### Case 3: Recover a deleted user

A user was deleted accidentally, but their team and Stripe data still exist:

```bash
# 1. Check current state
php artisan stripe:customer-report

# 2. Recreate user and reconnect with team
php artisan stripe:sync-customers --create

# 3. Restore the correct name
php artisan stripe:sync-customer-names
```

The system:
- Recreates the user with the same email
- Reuses the existing team (does not create a duplicate)
- Restores all relationships
- Keeps the `stripe_id` and subscription data

---

### Case 4: Clean up inconsistent data

You have teams without `stripe_id`, but the customers exist in Stripe:

```bash
# 1. Identify problems
php artisan stripe:customer-report

# 2. Sync to assign stripe_id
php artisan stripe:sync-customers

# 3. Verify everything is synced
php artisan stripe:customer-report
```

---

## Troubleshooting

### Error: "No API key provided"

**Cause:** The Stripe API key is not configured.

**Solution:**
```bash
# Check .env
grep STRIPE .env

# Must have:
STRIPE_KEY=pk_test_xxxxx
STRIPE_SECRET=sk_test_xxxxx
```

---

### Error: "Team already has different stripe_id"

**Cause:** The team already has a `stripe_id` different from the Stripe customer.

**Solution:**

1. **Check the database:**
```sql
SELECT id, name, stripe_id FROM teams WHERE stripe_id IS NOT NULL;
```

2. **Check Stripe:**
```bash
php artisan stripe:customer-report
```

3. **Fix manually if needed:**
```sql
-- If the stripe_id is incorrect
UPDATE teams SET stripe_id = 'cus_correcto' WHERE id = X;

-- Or clear it so it can be reassigned
UPDATE teams SET stripe_id = NULL WHERE id = X;
```

4. **Sync again:**
```bash
php artisan stripe:sync-customers
```

---

### Duplicate teams

**Cause:** Multiple teams were created for the same customer before the fix.

**Solution:**

1. **Identify duplicates:**
```sql
SELECT stripe_id, COUNT(*) as count
FROM teams
WHERE stripe_id IS NOT NULL
GROUP BY stripe_id
HAVING count > 1;
```

2. **Delete duplicates (keep the most recent):**
```sql
-- Example: Delete duplicate team with id 5, keep 4
DELETE FROM team_user WHERE team_id = 5;
DELETE FROM teams WHERE id = 5;
```

3. **Sync again:**
```bash
php artisan stripe:sync-customers --create
```

---

### User without `current_team_id`

**Cause:** The user does not have a current team assigned.

**Solution:**

```bash
# The sync command fixes this automatically
php artisan stripe:sync-customers
```

Or manually:
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

### Incorrect user name

**Cause:** The `individual_name` field is not set in Stripe or is outdated.

**Solution:**

1. **Update in Stripe Dashboard:**
   - Open the customer in Stripe
   - Add/update the `individual_name` field

2. **Sync:**
```bash
php artisan stripe:sync-customer-names
```

**Note:** If the customer has no `individual_name`, the command automatically falls back to `name` (business name).

---

## Configuration

### Environment variables

```env
# Stripe Keys
STRIPE_KEY=pk_test_xxxxxxxxxxxxx
STRIPE_SECRET=sk_test_xxxxxxxxxxxxx

# Webhook (optional, for automatic sync)
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxx
```

### Stripe webhooks (optional)

For automatic sync when a customer is updated in Stripe:

1. **Configure the webhook in Stripe Dashboard:**
   - URL: `https://tu-app.test/stripe/webhook`
   - Events:
     - `customer.created`
     - `customer.updated`
     - `customer.deleted`

2. **The Cashier webhook already handles these events automatically**

---

## Important notes

### Difference between `name` and `individual_name` in Stripe

- **`name`**: Company name / legal name
  - Used for: Invoices, legal documents, team name
  - Example: "Acme Corporation", "Tech Solutions SA"

- **`individual_name`**: Contact / individual person name
  - Used for: Personal communication, notifications, user name
  - Example: "John Doe", "María García"

### Naming strategy

```php
// For Team
$team->name = $customer->name; // Business name

// For User (with fallback)
$user->name = $customer->individual_name ?? $customer->name;
```

### Duplicate prevention

The system prevents duplicates by checking in this order:

1. **By `stripe_id`** in the `teams` table
2. **By `email`** in the `users` table
3. **By existing user-team relationship**

---

## Best practices

1. **Always use `--dry-run` first** to see what changes will be made
2. **Back up the database** before large synchronizations
3. **Run the report afterward** to verify each sync
4. **Configure the scheduler** to keep data updated automatically
5. **Configure Stripe webhooks** for real-time sync
6. **Keep `individual_name` updated** in Stripe for best results

---

## References

- [Laravel Cashier Documentation](https://laravel.com/docs/10.x/billing)
- [Stripe API - Customers](https://stripe.com/docs/api/customers)
- [Stripe API - Subscriptions](https://stripe.com/docs/api/subscriptions)
- [Stripe Webhooks](https://stripe.com/docs/webhooks)

---

**Last updated:** 2025-12-27
**Laravel version:** 10.x
**Cashier version:** 15.x
