# Invoice Sync Mapping Contract (Phase 2)

This document defines the mapping contract from `invoice_syncs` (staging) to `invoices` (core table).

## Scope

- Source table: `invoice_syncs` (`provider = stripe` in phase 1).
- Target table: `invoices`.
- Current phase only stores synced provider data in staging.
- Mapping/import to `invoices` is intentionally deferred.

## Required fields to import into `invoices`

At import time, each staging row should be validated for these fields:

- `team_id` (required)
- `provider` (required)
- `external_id` (required)
- `status` (required for status mapping)
- `currency` (required)
- `invoice_created_at` (recommended required for ordering/reporting)
- At least one amount source: `total` or `amount_due` or `amount_paid`

If one of the mandatory fields is missing, the row should be marked as not importable and retried later.

## Recommended core traceability columns in `invoices`

To keep referential traceability from core to source, add (in phase 2):

- `source_provider` (recommended enum/string)
- `source_reference_id` (provider external identifier, equals `invoice_syncs.external_id`)
- `source_synced_at` (timestamp from staging import process)

Recommended unique key:

- `unique(source_provider, source_reference_id)`

This provides idempotent imports and prevents duplicated external invoices in core.

## Initial status mapping proposal (Stripe -> Humano)

Core `invoices.status` is numeric and Humano-specific. Stripe statuses are string-based.
Use a dedicated mapper service with explicit rules. Initial proposal:

- `draft` -> `9` (Borrador)
- `open` -> `1` (Imprimir / pendiente)
- `paid` -> `2` (Impresa / cobrada)
- `void` -> `3` (Anulada)
- `uncollectible` -> `7` (Error / incobrable)
- fallback unknown -> `7`

Final mapping should be validated with finance operations before going live.

## Enterprise resolution contract

`invoices.enterprise_id` must be resolved per team. Resolution chain proposal:

1. `invoice_syncs.customer_id` -> `enterprises.code`
2. fallback by `customer_email` exact match (team-scoped)
3. unresolved rows are kept in staging with reconciliation flag

No guessed enterprise assignment should be done silently.

## Amount mapping contract

For initial import (phase 2):

- `gross_amount` <= `subtotal` (or `total` when subtotal is missing)
- `total_amount` <= `total` (or `amount_due` fallback)
- `discount` <= `total_discount_amount`
- `balance` <= `amount_remaining` when available, else `total_amount`

All values must be normalized to positive decimal format before writing core rows.

## Idempotency contract

Importer must use upsert semantics keyed by source identity:

- find core invoice by (`source_provider`, `source_reference_id`)
- create if not found, update mutable fields if found

Never duplicate a core row for the same source invoice.

## Reprocessing and reconciliation

- Keep `invoice_syncs.raw_payload` untouched as source of truth.
- Reprocessing should be possible without re-downloading from Stripe.
- Rows that fail mapping must include reason (status/flag in phase 2 migration).
