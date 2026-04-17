<?php

namespace App\Http\Controllers;

use App\Enums\ExternalProvider;
use App\Models\Contact;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoogleSyncedPreviewController extends Controller
{
    /**
     * Rows synced from Google Contacts (via contact_sync_mappings) for the current team.
     */
    public function contacts(Request $request): View|RedirectResponse
    {
        $this->authorize('viewAny', Contact::class);

        $team = $request->user()->currentTeam;

        if ($team === null)
        {
            return redirect()->route('error-without-team');
        }

        $provider = ExternalProvider::Google->value;

        $rows = DB::table('contact_sync_mappings')
            ->join('external_accounts', 'external_accounts.id', '=', 'contact_sync_mappings.external_account_id')
            ->leftJoin('contacts', function ($join): void
            {
                $join->on('contacts.id', '=', 'contact_sync_mappings.contact_id')
                    ->whereNull('contacts.deleted_at');
            })
            ->where('external_accounts.team_id', $team->id)
            ->where('external_accounts.provider', $provider)
            ->orderByDesc('contact_sync_mappings.last_synced_at')
            ->limit(500)
            ->select([
                'contact_sync_mappings.external_id',
                'contact_sync_mappings.last_synced_at',
                'contacts.name',
                'contacts.surname',
                'contacts.email',
                'contacts.phone',
                'contacts.id as contact_id',
            ])
            ->get();

        return view('integrations.google-synced-contacts', [
            'rows' => $rows,
            'team' => $team,
        ]);
    }

    /**
     * Calendar events synced from Google (via calendar_event_sync_mappings) for the current team.
     */
    public function calendar(Request $request): View|RedirectResponse
    {
        $team = $request->user()->currentTeam;

        if ($team === null)
        {
            return redirect()->route('error-without-team');
        }

        $provider = ExternalProvider::Google->value;

        $rows = DB::table('calendar_event_sync_mappings')
            ->join('external_accounts', 'external_accounts.id', '=', 'calendar_event_sync_mappings.external_account_id')
            ->join('calendar_events', function ($join): void
            {
                $join->on('calendar_events.id', '=', 'calendar_event_sync_mappings.calendar_event_id')
                    ->whereNull('calendar_events.deleted_at');
            })
            ->where('external_accounts.team_id', $team->id)
            ->where('external_accounts.provider', $provider)
            ->orderByDesc('calendar_events.start')
            ->limit(500)
            ->select([
                'calendar_event_sync_mappings.external_id',
                'calendar_event_sync_mappings.last_synced_at',
                'calendar_events.title',
                'calendar_events.start',
                'calendar_events.end',
                'calendar_events.all_day',
                'calendar_events.id as calendar_event_id',
            ])
            ->get();

        return view('integrations.google-synced-calendar', [
            'rows' => $rows,
            'team' => $team,
        ]);
    }
}
