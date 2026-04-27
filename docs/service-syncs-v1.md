# Service Syncs V1

## Objetivo

Implementar flujo gemelo para servicios:

1. `stripe:sync-service-syncs` -> staging en `service_syncs`
2. `service-syncs:import` -> proyeccion create-only en `services`

## Comandos

```bash
php artisan stripe:sync-service-syncs --team_id=1
php artisan service-syncs:import --provider=stripe --team_id=1 --fallback-email --link-code-on-email-match
```

Opciones utiles:

- `--dry-run`: previsualiza resultados sin escribir.
- `--limit`: limita filas a importar por corrida.

## SQL de verificacion

```sql
-- 1) Conteo staging por estado/proveedor
SELECT provider, status, COUNT(*) AS total
FROM service_syncs
GROUP BY provider, status
ORDER BY provider, status;

-- 2) Filas importadas create-only
SELECT COUNT(*) AS linked_services
FROM services
WHERE subscription_id IS NOT NULL
  AND deleted_at IS NULL;

-- 3) service_syncs pendientes de importar
SELECT COUNT(*) AS pending_syncs
FROM service_syncs ss
WHERE NOT EXISTS (
    SELECT 1
    FROM services s
    WHERE s.subscription_id = ss.id
      AND s.deleted_at IS NULL
);
```

## Checklist de despliegue

1. Ejecutar migraciones.
2. Correr `stripe:sync-service-syncs` y validar conteos en `service_syncs`.
3. Correr `service-syncs:import` en `--dry-run` y revisar `skipped`.
4. Ejecutar import real y validar relaciones `services.subscription_id`.
5. Revisar pantallas `/subscription` y `/service/list`.
