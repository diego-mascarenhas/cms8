# Service Syncs V1

## Goal

Implement a twin flow for services:

1. `stripe:sync-service-syncs` -> staging in `service_syncs`
2. `service-syncs:import` -> create-only projection into `services`

## Commands

```bash
php artisan stripe:sync-service-syncs --team_id=1
php artisan service-syncs:import --provider=stripe --team_id=1 --fallback-email --link-code-on-email-match
```

Useful options:

- `--dry-run`: preview results without writing.
- `--limit`: limit rows imported per run.

## Verification SQL

```sql
-- 1) Staging counts by status/provider
SELECT provider, status, COUNT(*) AS total
FROM service_syncs
GROUP BY provider, status
ORDER BY provider, status;

-- 2) Create-only imported rows
SELECT COUNT(*) AS linked_services
FROM services
WHERE subscription_id IS NOT NULL
  AND deleted_at IS NULL;

-- 3) service_syncs pending import
SELECT COUNT(*) AS pending_syncs
FROM service_syncs ss
WHERE NOT EXISTS (
    SELECT 1
    FROM services s
    WHERE s.subscription_id = ss.id
      AND s.deleted_at IS NULL
);
```

## Deployment checklist

1. Run migrations.
2. Run `stripe:sync-service-syncs` and validate counts in `service_syncs`.
3. Run `service-syncs:import` with `--dry-run` and review `skipped`.
4. Run the real import and validate `services.subscription_id` relations.
5. Review the `/subscription` and `/service/list` screens.
