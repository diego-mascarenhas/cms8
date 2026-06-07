<?php

namespace App\Enums;

enum SyncResource: string
{
    case Contacts = 'contacts';
    case CalendarEvents = 'calendar_events';
    case Tasks = 'tasks';
}
