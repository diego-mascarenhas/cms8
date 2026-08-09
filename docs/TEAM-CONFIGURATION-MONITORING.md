# Team Configuration Monitoring

This system automatically monitors each team's configurations and reports problems proactively.

## Main features

### Monitoring command
```bash
php artisan team:test-configurations
```

### Available options

| Option | Description |
|--------|-------------|
| `--team=ID` | Test only a specific team |
| `--report-email=email` | Send the report to a specific email (overrides owner emails) |
| `--no-email` | Do not send an email report |
| `--failures-only` | Report failures only |
| `--admin-summary` | Send an administrative summary to notification_email |

### Monitored services

1. **SMTP** - Outbound email
2. **IMAP** - Inbound email
3. **Stripe** - Payment processing
4. **Twilio** - SMS and WhatsApp

## Specific validations

### SMTP
- Complete configuration (host, username)
- Socket connectivity
- Invalid credentials
- Host unreachable

### IMAP
- Complete configuration (host, username)
- Successful authentication
- Invalid credentials
- Server unreachable

### Stripe
- Key format (pk_, sk_)
- API authentication
- Account permissions
- Invalid/expired keys

### Twilio
- SID format (AC...)
- API authentication
- Active account status
- Invalid credentials
- Suspended account

## Automatic scheduling

### Daily run
```bash
# Daily at 8:00 AM - Failures only to individual team owners
php artisan team:test-configurations --failures-only
```

### Weekly report
```bash
# Mondays at 9:00 AM - Full report + administrative summary
php artisan team:test-configurations --admin-summary
```

## Kernel configuration

```php
// Team configuration monitoring - daily at 8:00 AM
// Sends individual reports to team owners only for failures
$schedule->command('team:test-configurations --failures-only')
    ->dailyAt('08:00')
    ->name('team-config-monitoring')
    ->description('Monitor team configurations and send individual failure reports to owners')
    ->onFailure(function () {
        Log::error('Team configuration monitoring command failed');
    })
    ->runInBackground();

// Weekly comprehensive report - Mondays at 9:00 AM
// Sends individual reports to team owners + admin summary
$schedule->command('team:test-configurations --admin-summary')
    ->weeklyOn(1, '09:00') // Monday at 9:00 AM
    ->name('team-config-weekly-report')
    ->description('Weekly team configuration report with admin summary')
    ->runInBackground();
```

## Sample output

```
🔍 Starting Team Configuration Test...

📊 Testing 1 team(s)...

🏢 Testing Team: Demo's Team (ID: 1)
  ✅ SMTP: PASSED - Connection successful
  ✅ IMAP: PASSED - Connection successful
  ✅ Stripe: PASSED - Connection successful
  ✅ Twilio: PASSED - Connection successful
  📊 Summary: 4 passed, 0 failed, 0 skipped

📈 FINAL SUMMARY
===============
Teams tested: 1
Total tests: 4
✅ Passed: 4
❌ Failed: 0
⏭️ Skipped: 0

🎉 All configured services are working correctly!
📧 Sending individual reports to team owners...
📧 Individual report for 'Demo Team' would be sent to: owner@company.com
📧 Admin summary would be sent to: no-reply@idoneo.dev
```

## Logging and reports

### Automatic logs
- Full results in the 'daily' channel
- Specific failures with details
- Report send attempts

### Email reports

#### Individual reports to team owners
- **By default**: Each team owner receives ONLY their report
- **With `--failures-only`**: They receive it only if their team has failures
- **Recipient**: `team.owner.email` automatically
- **Override**: Use `--report-email=email` to change the recipient

#### Administrative summary (optional)
- **Only with `--admin-summary`**: Sent to `config('app.notification_email')`
- **Content**: Global statistics + list of teams with problems
- **Purpose**: Overview for administrators

## Unit tests

```bash
# Run all configuration tests
php artisan test tests/Unit/TeamConfigurationTest.php

# Specific tests
php artisan test --filter="it_can_set_and_get_settings"
php artisan test --filter="it_encrypts_sensitive_settings"
php artisan test --filter="it_generates_consistent_team_hash"
```

### Test coverage

- **20 tests** covering the full functionality
- Setting and getting settings
- Encryption of sensitive data
- Deterministic hash generation
- Twilio webhook URLs
- Configurations for all services
- Data type validation
- Compatibility with deprecated methods
- Null/empty value handling
- Cascade deletion
- Unique constraints

## Variable configuration

### Notification email
```bash
# .env
NOTIFICATION_EMAIL=no-reply@idoneo.dev  # (default value)
# or customize:
NOTIFICATION_EMAIL=admin@yourcompany.com
```

### Team-specific settings
```php
// Example of programmatic configuration
$team->setSetting('mail_host', 'smtp.gmail.com');
$team->setSetting('mail_username', 'team@company.com');
$team->setSetting('mail_password', 'password', ['is_encrypted' => true]);
```

## Alerts and notifications

### Failures detected
- Immediate error log
- Alert email (if configured)
- Specific problem details
- Resolution suggestions

### Healthy status
- Informational log
- Performance statistics
- Historical trends

## Troubleshooting

### "No teams found to test"
- Verify that teams exist in the database
- Review filters for a specific team

### "No report email configured"
- Configure `NOTIFICATION_EMAIL` in `.env` (default: no-reply@idoneo.dev)
- Or use `--report-email=email@domain.com`

### Connection failures
- Verify network configuration
- Review service credentials
- Check the status of external services

## Benefits

- **Proactive detection** of problems
- **Automatic monitoring** 24/7
- **Immediate alerts** for failures
- **Full visibility** of system status
- **Prevention** of service interruptions
- **Complete audit** of configurations

---

## Usage guide by scenario

### Daily monitoring (recommended)
```bash
# Only sends emails to team owners that have failures
php artisan team:test-configurations --failures-only
```
**Ideal for**: Automatic daily alerts without spam

### Full weekly report
```bash
# Sends an individual report to each owner + summary to admin
php artisan team:test-configurations --admin-summary
```
**Ideal for**: Full weekly review + administrative overview

### Specific team
```bash
# Only one specific team (useful for debugging)
php artisan team:test-configurations --team=1
```
**Ideal for**: Debugging specific problems

### Email override
```bash
# Sends ALL reports to a specific email (ignores owners)
php artisan team:test-configurations --report-email=admin@empresa.com
```
**Ideal for**: Testing or temporarily centralizing reports

### No emails (testing)
```bash
# Local testing/debugging only (does not send emails)
php artisan team:test-configurations --no-email
```
**Ideal for**: Local development and testing

### Admin summary only
```bash
# Global summary only, without individual reports
php artisan team:test-configurations --admin-summary --failures-only --report-email=admin@empresa.com
```
**Ideal for**: Administrators who only want a global view

---

## Privacy and scalability system

### Advantages of the new system
- **Privacy**: Each team owner only sees THEIR information
- **Scalability**: No saturation of the administrative inbox
- **Relevance**: Owners only receive what concerns them
- **Direct action**: Owners can act immediately
- **Administrative overview**: Optional summary for admins

### Email flow
1. **Failures detected** → Automatic email to the team owner
2. **All OK** → No email (reduces spam)
3. **Weekly summary** → Individual report + admin summary (optional)
4. **Override available** → Admin can receive everything if needed
