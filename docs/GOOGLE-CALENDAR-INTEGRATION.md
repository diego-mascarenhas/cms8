# Google Calendar Integration

## Overview

This integration allows your application to sync with Google Calendar using the same Service Account credentials configured for Google Analytics. Both services share credentials to simplify configuration.

## ✅ Installation Complete

The following packages have been installed:
- `spatie/laravel-google-calendar` (3.8.4)
- `google/apiclient` (2.19.0)
- `google/apiclient-services` (0.430.0)

## 🔧 Configuration

### 1. Enable Google Calendar API

In [Google Cloud Console](https://console.cloud.google.com):

1. Select your project
2. Go to **APIs & Services** > **Library**
3. Search for "Google Calendar API"
4. Click **Enable**

### 2. Configure Team Settings

The Service Account credentials are already configured in:

**Team Settings > Google Services**
- `https://humano.test/team/{team_id}/settings/analytics`

#### Settings Available:

1. **Service Account Credentials (JSON)** - Shared by Analytics & Calendar
2. **GA4 Property ID** - For Analytics
3. **Google Calendar ID** - Optional, defaults to "primary"

### 3. Share Calendar with Service Account

To use a specific calendar:

1. Go to [Google Calendar](https://calendar.google.com)
2. Open calendar settings
3. Share calendar with the service account email (found in the JSON credentials: `client_email`)
4. Grant "Make changes to events" permission
5. Copy the Calendar ID and paste it in Team Settings

## 📋 Usage

### Service Classes

#### GoogleCredentialsService

Centralized service to manage Google credentials:

```php
use App\Services\GoogleCredentialsService;

// Check if team has credentials
if (GoogleCredentialsService::hasCredentials($team)) {
    // Get Calendar client
    $client = GoogleCredentialsService::getCalendarClient($team);
    
    // Get Analytics client
    $client = GoogleCredentialsService::getAnalyticsClient($team);
    
    // Get calendar ID
    $calendarId = GoogleCredentialsService::getCalendarId($team);
}
```

#### GoogleCalendarService

Service to interact with Google Calendar:

```php
use App\Services\GoogleCalendarService;
use Carbon\Carbon;

$calendarService = new GoogleCalendarService($team);

// List events
$events = $calendarService->listEvents(
    Carbon::now()->startOfMonth(),
    Carbon::now()->endOfMonth()
);

// Create event
$event = $calendarService->createEvent(
    summary: 'Team Meeting',
    start: Carbon::parse('2026-02-01 10:00:00'),
    end: Carbon::parse('2026-02-01 11:00:00'),
    options: [
        'description' => 'Monthly team sync',
        'location' => 'Conference Room A',
        'attendees' => ['user@example.com'],
    ]
);

// Update event
$calendarService->updateEvent($eventId, [
    'summary' => 'Updated Meeting Title',
    'start' => Carbon::parse('2026-02-01 14:00:00'),
    'end' => Carbon::parse('2026-02-01 15:00:00'),
]);

// Delete event
$calendarService->deleteEvent($eventId);

// Quick add from text
$event = $calendarService->quickAdd('Meeting with John tomorrow at 3pm');

// Check free/busy
$busy = $calendarService->getFreeBusy(
    Carbon::now(),
    Carbon::now()->addDays(7)
);
```

### Controller Endpoints

The `CalendarController` provides RESTful API endpoints:

```php
// Routes (add to routes/web.php or routes/api.php)
Route::middleware(['auth'])->group(function () {
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/calendar/events', [CalendarController::class, 'getEvents'])->name('calendar.events');
    Route::post('/calendar/events', [CalendarController::class, 'store'])->name('calendar.store');
    Route::put('/calendar/events/{eventId}', [CalendarController::class, 'update'])->name('calendar.update');
    Route::delete('/calendar/events/{eventId}', [CalendarController::class, 'destroy'])->name('calendar.destroy');
    Route::post('/calendar/quick-add', [CalendarController::class, 'quickAdd'])->name('calendar.quick-add');
});
```

### API Examples

#### List Events

```javascript
fetch('/calendar/events?start=2026-02-01&end=2026-02-28')
    .then(response => response.json())
    .then(events => {
        console.log(events);
    });
```

#### Create Event

```javascript
fetch('/calendar/events', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({
        title: 'New Meeting',
        start: '2026-02-01T10:00:00',
        end: '2026-02-01T11:00:00',
        description: 'Meeting description',
        location: 'Office',
        attendees: ['user@example.com']
    })
})
.then(response => response.json())
.then(data => {
    console.log('Event created:', data);
});
```

#### Update Event

```javascript
fetch(`/calendar/events/${eventId}`, {
    method: 'PUT',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify({
        title: 'Updated Title',
        start: '2026-02-01T14:00:00',
        end: '2026-02-01T15:00:00'
    })
})
.then(response => response.json())
.then(data => {
    console.log('Event updated:', data);
});
```

#### Delete Event

```javascript
fetch(`/calendar/events/${eventId}`, {
    method: 'DELETE',
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    }
})
.then(response => response.json())
.then(data => {
    console.log('Event deleted:', data);
});
```

## 🔐 Security

- ✅ Credentials are **encrypted** in the database using Laravel's `Crypt` facade
- ✅ Per-team credentials ensure data isolation
- ✅ Service Account access is scoped to specific APIs
- ✅ Authorization checks using Laravel policies

## 🧪 Testing

Create a test for calendar integration:

```php
use App\Services\GoogleCalendarService;
use Tests\TestCase;

class GoogleCalendarTest extends TestCase
{
    public function test_can_list_calendar_events()
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->currentTeam;
        
        // Configure credentials in team settings
        $team->setSetting('analytics_credentials_json', file_get_contents('path/to/credentials.json'));
        
        $this->actingAs($user);
        
        $response = $this->get(route('calendar.events'));
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => ['id', 'title', 'start', 'end']
        ]);
    }
}
```

## 📊 Integration with Existing Calendar

To integrate with `https://humano.test/app/calendar`:

1. **Add Routes** to `routes/web.php`
2. **Update existing calendar view** to use the new endpoints
3. **Add event sync** functionality to sync local events with Google Calendar

### Example Integration:

```php
// In your existing calendar controller
public function syncToGoogle($eventId)
{
    $localEvent = Event::findOrFail($eventId);
    $team = auth()->user()->currentTeam;
    
    $calendarService = new GoogleCalendarService($team);
    
    $googleEvent = $calendarService->createEvent(
        $localEvent->title,
        Carbon::parse($localEvent->start),
        Carbon::parse($localEvent->end),
        [
            'description' => $localEvent->description,
            'location' => $localEvent->location,
        ]
    );
    
    // Save Google event ID for future updates
    $localEvent->update(['google_event_id' => $googleEvent->getId()]);
    
    return redirect()->back()->with('success', 'Event synced to Google Calendar');
}
```

## 🚀 Next Steps

1. Add routes to `routes/web.php`
2. Update `https://humano.test/app/calendar` view to call new endpoints
3. Test calendar synchronization
4. Add webhook support for real-time updates (optional)
5. Implement recurring events support (optional)

## 📚 Resources

- [Spatie Laravel Google Calendar Docs](https://github.com/spatie/laravel-google-calendar)
- [Google Calendar API Documentation](https://developers.google.com/calendar/api)
- [Service Account Authentication](https://developers.google.com/identity/protocols/oauth2/service-account)
