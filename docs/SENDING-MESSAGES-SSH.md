# Sending Email Messages via SSH

This guide explains how to send email campaigns from the command line using SSH access to the server.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Quick Start](#quick-start)
- [Detailed Steps](#detailed-steps)
- [Verification](#verification)
- [Troubleshooting](#troubleshooting)
- [Monitoring](#monitoring)

---

## Prerequisites

Before sending messages, ensure:

1. **SSH Access**: You have SSH access to the production server
2. **Message Created**: The message/campaign exists and is configured in the web interface
3. **Queue Worker**: A queue worker is running to process email jobs
4. **Team Configuration**: The team has email sender configuration (FROM address and name)

---

## Quick Start

### Send a Specific Message by ID

```bash
# Connect to server
ssh user@server
cd /path/to/project

# Send message ID 3
php artisan messages:populate-deliveries && \
php artisan messages:send-pending && \
php artisan queue:work --queue=mailer --stop-when-empty --verbose
```

---

## Detailed Steps

### Step 1: Verify the Message

Before sending, check the message configuration:

```bash
php artisan tinker
```

```php
// Get message by ID
$message = \App\Models\Message::find(3);

// Display basic info
echo "Message: " . $message->name . "\n";
echo "Status: " . ($message->status_id == 1 ? 'Active' : 'Inactive') . "\n";
echo "Team ID: " . $message->team_id . "\n";
echo "Category: " . ($message->category ? $message->category->name : 'None') . "\n";

// Count contacts in category
if ($message->category) {
    $contactsCount = $message->category->contacts()->count();
    echo "Contacts in category: $contactsCount\n";
}

// Check existing deliveries
$deliveriesCount = \App\Models\MessageDelivery::where('message_id', 3)->count();
echo "Deliveries created: $deliveriesCount\n";

$pendingCount = \App\Models\MessageDelivery::where('message_id', 3)
    ->whereNull('sent_at')
    ->count();
echo "Pending deliveries: $pendingCount\n";

exit;
```

### Step 2: Activate the Message (if needed)

```php
$message = \App\Models\Message::find(3);

if ($message->status_id != 1) {
    $message->update(['status_id' => 1]);
    echo "✅ Message activated\n";
}
```

### Step 3: Generate Deliveries

This creates `message_deliveries` records for each contact in the message's category:

```bash
php artisan messages:populate-deliveries
```

**What it does:**
- Finds all active messages (`status_id = 1`)
- For each message, gets contacts from its category
- Creates delivery records in batches of 5
- Skips contacts that already have deliveries

**Expected output:**
```
Inserted 5 deliveries for message 3 (block of 5)
Inserted 5 deliveries for message 3 (block of 5)
Done. Total new deliveries created: 15
```

### Step 4: Queue the Emails

This enqueues jobs to send all pending deliveries:

```bash
php artisan messages:send-pending
```

**What it does:**
- Finds all `message_deliveries` with `sent_at = NULL`
- Validates contact has email
- Validates message is active
- Creates a job for each delivery with random delays
- Adds jobs to the `mailer` queue

**Expected output:**
```
Queued job for: user@example.com (delay: 60s, team: Team Name)
Queued job for: user2@example.com (delay: 180s, team: Team Name)
Total jobs queued: 15
Total errors: 0
```

### Step 5: Process the Queue

This executes the queued jobs and sends the emails:

```bash
# Process until queue is empty (recommended for manual sends)
php artisan queue:work --queue=mailer --stop-when-empty --verbose

# OR: Process one job at a time (for testing)
php artisan queue:work --queue=mailer --once --verbose

# OR: Run continuously (for production)
php artisan queue:work --queue=mailer --daemon
```

**Expected output:**
```
[2024-12-23 10:30:15][abc123] Processing: App\Jobs\SendMessageCampaignJob
[2024-12-23 10:30:16][abc123] Processed:  App\Jobs\SendMessageCampaignJob
```

---

## Verification

### Check Delivery Status

```bash
php artisan tinker
```

```php
// Get statistics for message ID 3
$messageId = 3;

$total = \App\Models\MessageDelivery::where('message_id', $messageId)->count();
$sent = \App\Models\MessageDelivery::where('message_id', $messageId)
    ->whereNotNull('sent_at')
    ->count();
$delivered = \App\Models\MessageDelivery::where('message_id', $messageId)
    ->whereNotNull('delivered_at')
    ->count();
$pending = \App\Models\MessageDelivery::where('message_id', $messageId)
    ->whereNull('sent_at')
    ->count();

echo "📊 Message #$messageId Statistics:\n";
echo "Total deliveries: $total\n";
echo "✅ Sent: $sent\n";
echo "✅ Delivered: $delivered\n";
echo "⏳ Pending: $pending\n";

exit;
```

### View Recent Deliveries

```php
$deliveries = \App\Models\MessageDelivery::where('message_id', 3)
    ->with('contact')
    ->latest('sent_at')
    ->limit(10)
    ->get();

foreach ($deliveries as $d) {
    $status = $d->sent_at ? '✅ Sent' : '⏳ Pending';
    echo "$status - " . $d->contact->email . " - " . ($d->sent_at ?? 'Not sent') . "\n";
}
```

### Check Logs

View the application logs to see detailed information:

```bash
# View last 50 lines
tail -50 storage/logs/laravel.log

# Filter for email-related logs
tail -100 storage/logs/laravel.log | grep -E "SendMessageCampaignJob|from_address"

# Real-time monitoring
tail -f storage/logs/laravel.log | grep -E "ConfiguresTeamMail|Email sent"
```

**Look for:**
```
🔧 ConfiguresTeamMail: Applied team email config
    final_from_address: team@domain.com
    final_from_name: Team Name
✅ Email sent via SMTP
    delivery_id: 123
    sent_to: user@example.com
    from_address: team@domain.com
```

---

## Troubleshooting

### No Deliveries Created

**Problem:** `messages:populate-deliveries` creates 0 deliveries

**Solutions:**
1. Check message is active: `status_id = 1`
2. Check message has a category assigned
3. Check category has contacts
4. Check contacts have valid email addresses

```php
$message = \App\Models\Message::find(3);
echo "Status: " . $message->status_id . "\n";
echo "Category: " . ($message->category_id ?? 'NULL') . "\n";

if ($message->category) {
    echo "Contacts in category: " . $message->category->contacts()->count() . "\n";
}
```

### Emails Not Sending

**Problem:** Jobs are queued but emails don't send

**Solutions:**

1. **Check queue worker is running:**
```bash
ps aux | grep "queue:work"
```

2. **Check failed jobs:**
```bash
php artisan queue:failed
```

3. **View specific failed job:**
```bash
php artisan queue:failed --id=<job-id>
```

4. **Retry failed jobs:**
```bash
php artisan queue:retry all
```

### Wrong FROM Address

**Problem:** Emails send with incorrect FROM address

**Solutions:**

1. **Verify team configuration:**
```php
$team = \App\Models\Team::find(4);
echo "FROM: " . $team->getSetting('mail_from_address') . "\n";
echo "NAME: " . $team->getSetting('mail_from_name') . "\n";
```

2. **Check configuration is applied:**
```bash
tail -f storage/logs/laravel.log | grep "from_address"
```

3. **Clear configuration cache:**
```bash
php artisan config:clear
php artisan cache:clear
```

### Memory Errors

**Problem:** Composer or PHP runs out of memory

**Solutions:**

```bash
# Run composer with unlimited memory
COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev

# Run PHP with more memory
php -d memory_limit=512M artisan queue:work
```

---

## Monitoring

### Real-time Queue Monitoring

```bash
# Watch queue status
watch -n 2 'php artisan queue:work --queue=mailer --once --verbose'

# Monitor jobs table
watch -n 5 'echo "SELECT COUNT(*) FROM jobs WHERE queue = \"mailer\";" | mysql -u user -p database'
```

### Dashboard Statistics

After sending, refresh the message detail page in the web interface:

`https://admin.revisionalpha.com/message/3`

You should see updated statistics:
- **Subscribers**: Total contacts in category
- **Sent**: Number of emails sent
- **Delivered**: Number of emails delivered
- **Opened**: Number of emails opened (if tracking enabled)
- **Clicked**: Number of clicks (if tracking enabled)

---

## Automated Sending (Cron Jobs)

To automate message sending, add these cron jobs:

```bash
# Edit crontab
crontab -e
```

Add:
```bash
# Generate deliveries every hour at minute 0
0 * * * * cd /path/to/project && php artisan messages:populate-deliveries >> /dev/null 2>&1

# Send pending messages every 15 minutes
*/15 * * * * cd /path/to/project && php artisan messages:send-pending >> /dev/null 2>&1
```

**Important:** Ensure a queue worker is always running via Supervisor.

---

## Queue Worker Setup (Supervisor)

For production, use Supervisor to keep queue workers running:

Create `/etc/supervisor/conf.d/laravel-worker.conf`:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/project/artisan queue:work --queue=mailer --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=forge
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/project/storage/logs/worker.log
stopwaitsecs=3600
```

Reload Supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

---

## Command Reference

| Command | Description |
|---------|-------------|
| `php artisan messages:populate-deliveries` | Create delivery records for active messages |
| `php artisan messages:send-pending` | Queue pending deliveries for sending |
| `php artisan queue:work --queue=mailer` | Process the mailer queue |
| `php artisan queue:work --once` | Process one job only |
| `php artisan queue:failed` | List failed jobs |
| `php artisan queue:retry all` | Retry all failed jobs |
| `php artisan queue:flush` | Delete all failed jobs |
| `php artisan queue:restart` | Restart queue workers |

---

## Best Practices

1. **Test First**: Send a test email before sending to all contacts
2. **Small Batches**: Start with small batches to verify configuration
3. **Monitor Logs**: Always monitor logs during first sends
4. **Queue Worker**: Ensure queue worker is running before sending
5. **Backups**: Backup database before large sends
6. **Rate Limiting**: Respect email provider rate limits
7. **Unsubscribe**: Ensure unsubscribe links work
8. **Bounce Handling**: Monitor bounced emails and update contact lists

---

## Related Documentation

- [Email Team Settings](EMAIL-TEAM-SETTINGS.md)
- [Queue Configuration](QUEUES.md)
- [Message System](NEWSLETTER-OPTIMIZED-SYSTEM.md)

---

## Support

For issues or questions:
1. Check application logs: `storage/logs/laravel.log`
2. Check queue failed jobs: `php artisan queue:failed`
3. Review team email configuration
4. Verify SMTP/email provider credentials

