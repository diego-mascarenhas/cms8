<?php

/**
 * Google Calendar Routes
 * 
 * Add these routes to your routes/web.php file to enable Google Calendar integration
 */

use App\Http\Controllers\CalendarController;
use Illuminate\Support\Facades\Route;

// Google Calendar Integration Routes
Route::middleware(['auth'])->prefix('app')->group(function () {
    // Calendar view
    Route::get('/calendar/google', [CalendarController::class, 'index'])
        ->name('calendar.google.index');

    // Get events (API endpoint for calendar)
    Route::get('/calendar/google/events', [CalendarController::class, 'getEvents'])
        ->name('calendar.google.events');

    // Create event
    Route::post('/calendar/google/events', [CalendarController::class, 'store'])
        ->name('calendar.google.store');

    // Update event
    Route::put('/calendar/google/events/{eventId}', [CalendarController::class, 'update'])
        ->name('calendar.google.update');

    // Delete event
    Route::delete('/calendar/google/events/{eventId}', [CalendarController::class, 'destroy'])
        ->name('calendar.google.destroy');

    // Quick add event from text
    Route::post('/calendar/google/quick-add', [CalendarController::class, 'quickAdd'])
        ->name('calendar.google.quick-add');
});

/**
 * Usage Examples:
 * 
 * 1. View calendar:
 *    https://humano.test/app/calendar/google
 * 
 * 2. Get events (AJAX):
 *    GET /app/calendar/google/events?start=2026-02-01&end=2026-02-28
 * 
 * 3. Create event (AJAX):
 *    POST /app/calendar/google/events
 *    Body: { title, start, end, description, location, attendees }
 * 
 * 4. Update event (AJAX):
 *    PUT /app/calendar/google/events/{eventId}
 *    Body: { title, start, end, description, location }
 * 
 * 5. Delete event (AJAX):
 *    DELETE /app/calendar/google/events/{eventId}
 * 
 * 6. Quick add (AJAX):
 *    POST /app/calendar/google/quick-add
 *    Body: { text: "Meeting with John tomorrow at 3pm" }
 */
