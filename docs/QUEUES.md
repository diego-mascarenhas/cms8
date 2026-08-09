# Humano Queues

Guide to queues, `.env` settings, workers in development/production, and failure recovery.

## 1) Queues by type

### Email (dedicated worker)

| Queue | Job | Usage |
|------|-----|-----|
| `task-communications` | `SendTaskCommunication` | Emails from the Kanban |
| `notifications` | `SendNotificationJob` | Notifications to contacts |
| `mailer` | `SendMessageCampaignJob` | Messages, resends, tests |
| `campaign` | `SendMessageCampaignJob` | Bulk campaign sends |

Campaign queue configuration: `config/message_delivery_dispatch.php`.

### General (separate worker)

| Queue | Job / usage |
|------|-----------|
| `default` | Jobs without an explicit queue |
| `domain-info` | `UpdateDomainInfo`, `UpdateServerDomainInfo` |
| `domain-updates` | `UpdateDomainSiteType` |
| `domain-version` | `UpdateDomainPhpVersion` |
| `whm-sync` | `WhmDomainSync` |
| `whm-tests` | `WhmServerTest` |
| `ovh-sync` | `OvhServiceSync` |

## 2) Prerequisites

`jobs` and `failed_jobs` migrations must have been run.

## 3) `.env` configuration

```env
QUEUE_CONNECTION=redis
MESSAGE_DELIVERY_QUEUE_CONNECTION=redis

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="Humano"
```

## 4) Workers

### Development (two terminals)

```bash
# Email
php artisan queue:work redis --queue=task-communications,notifications,mailer,campaign --sleep=3 --tries=3 --timeout=120

# Everything else
php artisan queue:work redis --queue=default,domain-info,domain-updates,domain-version,whm-sync,whm-tests,ovh-sync --sleep=3 --tries=3 --timeout=120
```

### Production / Forge (two daemons)

Ready-to-copy examples for Forge:

- `deploy/supervisor/forge-queue-email.conf.example`
- `deploy/supervisor/forge-queue-general.conf.example`
- `deploy/supervisor/README.md`

**Daemon 1 — general** (the one you already have, updated):

```ini
command=php8.4 /home/forge/staging.humano.app/artisan queue:work redis --queue=default,domain-info,domain-updates,domain-version,whm-sync,whm-tests,ovh-sync --sleep=3 --tries=3 --timeout=120 --max-time=3600 --memory=256
directory=/home/forge/staging.humano.app
numprocs=2
```

**Daemon 2 — email** (new in Forge → Queue → New Worker):

```ini
command=php8.4 /home/forge/staging.humano.app/artisan queue:work redis --queue=task-communications,notifications,mailer,campaign --sleep=3 --tries=3 --timeout=120 --max-time=3600 --memory=256
directory=/home/forge/staging.humano.app
numprocs=1
```

Remove `--daemon` and `--quiet` if you had them; Supervisor already keeps the process alive.

After every deploy: `php artisan queue:restart`.

## 5) Deployment checklist

1. `.env` with `QUEUE_CONNECTION=redis` and mail configured.
2. Two active daemons (general + email).
3. `php artisan queue:restart` in the deploy script.

## 6) Monitoring and recovery

```bash
php artisan queue:failed
php artisan queue:retry all
php artisan queue:flush

tail -f storage/logs/queue-email.log
tail -f storage/logs/laravel.log
```

## 7) Quick test

1. Send a communication from a task (`task-communications` queue).
2. Or queue a campaign: `php artisan messages:send-pending`.
3. Check `queue-email.log` or Laravel logs.

For bulk sends with rate limiting, keep `numprocs=1` on the email worker; enqueue jitter is handled in `MessageDeliveryDispatcher`.
