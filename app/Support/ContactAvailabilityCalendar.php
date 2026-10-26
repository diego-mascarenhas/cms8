<?php

namespace App\Support;

use App\Models\Contact;
use Carbon\Carbon;

class ContactAvailabilityCalendar
{
    /**
     * @return array{
     *     collaborator: Contact,
     *     weeklyAvailability: \App\Models\ContactWeeklyAvailability,
     *     absences: list<string>,
     *     months: list<array{name: string, month: string, year: string, firstDay: int, daysInMonth: int, startPadding: int}>
     * }
     */
    public static function payload(Contact $contact): array
    {
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->addMonths(5)->endOfMonth();

        $absences = $contact->absences()
            ->whereBetween('absence_date', [$startDate, $endDate])
            ->get()
            ->pluck('absence_date')
            ->map(function ($date)
            {
                return $date->format('Y-m-d');
            })
            ->all();

        $weeklyAvailability = $contact->weeklyAvailability;
        if (! $weeklyAvailability)
        {
            $weeklyAvailability = $contact->weeklyAvailability()->create([
                'contact_id' => $contact->id,
                'monday' => true,
                'tuesday' => true,
                'wednesday' => true,
                'thursday' => true,
                'friday' => true,
                'saturday' => false,
                'sunday' => false,
                'team_id' => $contact->team_id,
            ]);
        }

        $months = [];
        for ($i = 0; $i < 6; $i++)
        {
            $currentMonth = Carbon::now()->addMonths($i);
            $months[] = [
                'name' => $currentMonth->format('F Y'),
                'month' => $currentMonth->format('n'),
                'year' => $currentMonth->format('Y'),
                'firstDay' => $currentMonth->copy()->startOfMonth()->dayOfWeek,
                'daysInMonth' => $currentMonth->daysInMonth,
                'startPadding' => $currentMonth->copy()->startOfMonth()->dayOfWeekIso - 1,
            ];
        }

        return [
            'collaborator' => $contact,
            'weeklyAvailability' => $weeklyAvailability,
            'absences' => $absences,
            'months' => $months,
        ];
    }
}
