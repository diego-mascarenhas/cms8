# Newsletter — Client Protection Logic

## Overview

This document describes the logic implemented to protect contacts who are already **clients** (status_id 5) from automatic status changes when they interact with newsletters or unsubscribe.

## Problem solved

Previously, when a client clicked a newsletter link or unsubscribed, their status changed automatically:
- **Link click** → Changed to "Conversión" (ID 3)
- **Unsubscribe** → Changed to "Perdido" (ID 4)

This degraded the status of established clients and lost valuable information about the commercial relationship.

## Protection logic implemented

### Status and action table

| Action | Current status | Result | Status changes? | Affected file |
|--------|----------------|--------|-----------------|---------------|
| **Click** | Cliente (5) | Keeps status 5 | NO | `MessageTrackingController.php` |
| **Click** | Prospect (1) | Changes to Conversión (3) | YES | `MessageTrackingController.php` |
| **Click** | Conversión (3) | Keeps status 3 | NO | `MessageTrackingController.php` |
| **Unsubscribe** | Cliente (5) | Keeps status 5 | NO | `MessageController.php` |
| **Unsubscribe** | Prospect (1) | Changes to Perdido (4) | YES | `MessageController.php` |
| **Unsubscribe** | Conversión (3) | Changes to Perdido (4) | YES | `MessageController.php` |

### Protection conditions

#### For clicks (Conversión)
```php
// Only changes if NOT conversion (3) AND NOT client (5)
if ($contact->status_id != 3 && $contact->status_id != 5) {
    $contact->update(['status_id' => 3]);
}
```

#### For unsubscribe (Perdido)
```php
// Only changes if NOT client (5)
if ($contact->status_id != 5) {
    $contact->update(['status_id' => 4]);
}
```

## Modified files

### 1. `app/Console/Commands/ProcessActiveCampaigns.php`
**Method**: `getContactsForMessage()`
**Change**: Corrected the column name from `contact_status_id` to `status_id` in queries against the `contacts` table.

```php
// Filter by contact status if specified in message
if ($message->contact_status_id) {
    $query->where('status_id', $message->contact_status_id); // Fixed: was 'contact_status_id'
}
```

**Problem resolved**: Production error `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'contact_status_id' in 'where clause'`

### 2. `app/Http/Controllers/MessageTrackingController.php`
**Method**: `trackClick()`
**Change**: Added a condition so client status is not changed on click.

```php
// Update contact status to "Conversión" (ID 3) when they click any link
// But don't change status if they are already a client (status_id 5)
if ($delivery->contact && $delivery->contact->status_id != 3 && $delivery->contact->status_id != 5) {
    $delivery->contact->update(['status_id' => 3]);
    \Log::info('Contact status updated to Conversión', [
        'contact_id' => $delivery->contact->id,
        'contact_email' => $delivery->contact->email,
        'delivery_id' => $delivery->id,
        'clicked_url' => $originalUrl,
        'previous_status' => $delivery->contact->getOriginal('status_id'),
    ]);
} elseif ($delivery->contact && $delivery->contact->status_id == 5) {
    \Log::info('Contact is already a client - status not changed', [
        'contact_id' => $delivery->contact->id,
        'contact_email' => $delivery->contact->email,
        'delivery_id' => $delivery->id,
        'clicked_url' => $originalUrl,
        'current_status' => 5,
    ]);
}
```

### 3. `app/Http/Controllers/MessageController.php`
**Method**: `unsubscribe()`
**Change**: Added a condition so client status is not changed on unsubscribe.

```php
public function unsubscribe($email)
{
    // Update contact status to "Perdido" (ID 4) when they unsubscribe
    // But don't change status if they are already a client (status_id 5)
    $contact = Contact::where('email', $email)->first();

    if ($contact)
    {
        if ($contact->status_id != 5) {
            $contact->update(['status_id' => 4]);

            Log::info('Contact unsubscribed - status updated to Perdido', [
                'contact_id' => $contact->id,
                'contact_email' => $contact->email,
                'previous_status' => $contact->getOriginal('status_id'),
                'new_status' => 4,
            ]);
        } else {
            Log::info('Contact is a client - unsubscribed but status not changed', [
                'contact_id' => $contact->id,
                'contact_email' => $contact->email,
                'current_status' => 5,
                'action' => 'unsubscribe_attempt',
            ]);
        }
    }

    return view('message.unsubscribe', ['email' => $email]);
}
```

## Logging and auditing

### Logged events

1. **Client clicks**: Action is logged and status is reported as unchanged
2. **Client unsubscribes**: Attempt is logged and status is preserved
3. **Other contacts**: Status change is logged with previous and new values

### Information in logs

- **contact_id**: Contact ID
- **contact_email**: Contact email
- **delivery_id**: Delivery ID (clicks only)
- **clicked_url**: Clicked URL (clicks only)
- **previous_status**: Previous status (when changed)
- **new_status**: New status (when changed)
- **current_status**: Current status (when unchanged)
- **action**: Type of action performed

## Benefits

1. **Preserves commercial relationships**: Clients remain clients
2. **Avoids confusion**: Clients are not "downgraded" by marketing actions
3. **Maintains integrity**: Client status is permanent and valuable
4. **Full audit trail**: All actions are logged for follow-up
5. **Flexibility**: Other statuses continue to work normally

## Testing

To verify the logic works correctly:

```php
// Verify protection logic
$contact_status_id = 5; // Cliente
$should_update_to_conversion = $contact_status_id != 3 && $contact_status_id != 5;
$should_update_to_lost = $contact_status_id != 5;

echo 'Client clicks → status does NOT change: ' . (!$should_update_to_conversion ? '✅' : '❌');
echo 'Client unsubscribes → status does NOT change: ' . (!$should_update_to_lost ? '✅' : '❌');
```

## Contact statuses

| ID | Name | Description |
|----|------|-------------|
| 1 | Activo/Prospect | Active contact, potential client |
| 2 | Inactivo | Inactive contact |
| 3 | Conversión | Showed interest (clicked) |
| 4 | Perdido | Unsubscribed or lost interest |
| 5 | **Cliente** | **Protected status** — Established client |

---

**Implementation date**: August 2024
**Version**: 1.0
**Author**: Humano Newsletter System
