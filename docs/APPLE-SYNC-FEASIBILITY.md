# Apple Sync Feasibility Spike

## Goal

Define how to add Apple calendar/contact sync without changing the core sync architecture.

## Current architecture readiness

The codebase now includes provider-agnostic sync contracts and storage:

- `ContactSyncProviderInterface`
- `CalendarSyncProviderInterface`
- provider enum (`google`, `apple`)
- per-provider external account + cursor + mapping tables

This allows an `AppleProvider` implementation to be added without schema redesign.

## Apple integration options

## Option A: Calendar first (recommended)

- Scope: read-only calendar sync from Apple users
- Protocol/API: CalDAV-compatible client strategy
- Complexity: medium
- Value: immediate parity with Google calendar sync use cases

## Option B: Contacts + Calendar

- Scope: read-only sync for contacts and events
- Protocol/API: CalDAV + CardDAV
- Complexity: high
- Risks: auth flow and account provisioning UX are more complex than Google OAuth

## Proposed next sprint spike tasks

1. Validate authentication UX with real Apple accounts in staging.
2. Confirm token/session lifecycle requirements for long-running background sync.
3. Implement a thin `AppleCalendarSyncProvider` proof-of-concept.
4. Measure incremental synchronization capabilities and failure modes.
5. Define production constraints (rate limits, reliability, support burden).

## Exit criteria

The spike is complete when we can answer:

- Is Apple calendar sync production-viable with our background jobs?
- What is the supported auth UX for end users?
- What is the realistic SLA for incremental sync?
- Is contact sync feasible in the same phase or should it be phase 2?
