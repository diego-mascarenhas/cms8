# Google People + Calendar Sync

## Overview

This project now supports per-user Google OAuth accounts and incremental synchronization for:

- Contacts (`People API`)
- Calendar events (`Google Calendar API`)

## Data Model

New tables:

- `external_accounts`: OAuth credentials per team/user/provider
- `sync_cursors`: incremental cursor (`syncToken`) by resource
- `contact_sync_mappings`: links Google contacts to local `contacts`
- `calendar_event_sync_mappings`: links Google events to local `calendar_events`
- `sync_runs`: operational telemetry per sync execution

## OAuth Flow

Routes:

- `GET /integrations/google/connect`
- `GET /integrations/google/callback`
- `DELETE /integrations/google/disconnect`

Required env vars:

- `APP_URL` (OAuth redirect is always `{APP_URL}/integrations/google/callback`)
- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `GOOGLE_OAUTH_SCOPES`

## Sync Execution

Command:

- `php artisan google:sync-data`
- `php artisan google:sync-data --account_id=123`

Scheduler:

- Runs every 10 minutes from `app/Console/Kernel.php`

Jobs:

- `SyncGoogleDataJob`
- `SyncGoogleContactsJob`
- `SyncGoogleCalendarEventsJob`

## Incremental behavior

Contacts:

- Uses `people.connections.list` with `requestSyncToken=true` for first full sync.
- Uses `syncToken` for incremental sync.
- On `410` (expired token), it resets the cursor and performs a full sync.

Calendar:

- First run fetches recent events and stores resulting token.
- Incremental runs use `syncToken`.
- On `410` (expired token), it resets and performs a fresh sync.

## Metrics and troubleshooting

Each resource sync writes to `sync_runs` with:

- `status`: `running`, `success`, `failed`
- `pulled_count`, `upserted_count`, `deleted_count`
- `error_message` when failed
- start/finish timestamps

Use `sync_runs` to monitor health and detect regressions.
