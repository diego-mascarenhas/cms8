<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Calendar wall-clock timezone
    |--------------------------------------------------------------------------
    |
    | Naive datetimes (e.g. "2026-06-10 14:00:00" from the assistant) are interpreted
    | in this timezone before storing UTC in the database — matching how the calendar
    | UI treats local picker times (FullCalendar timeZone: local).
    |
    */
    'wall_clock_timezone' => env('CALENDAR_WALL_CLOCK_TIMEZONE', 'Europe/Madrid'),

];
