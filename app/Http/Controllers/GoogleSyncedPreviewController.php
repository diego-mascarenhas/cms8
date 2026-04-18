<?php

namespace App\Http\Controllers;

use App\Enums\ExternalProvider;
use App\Models\Contact;
use App\Models\ExternalAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

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

        $googleAccounts = $this->googleAccountsForTeam($team->id, ExternalProvider::Google);

        $stats = DB::table('contact_sync_mappings')
            ->join('external_accounts', 'external_accounts.id', '=', 'contact_sync_mappings.external_account_id')
            ->leftJoin('contacts', 'contacts.id', '=', 'contact_sync_mappings.contact_id')
            ->where('external_accounts.team_id', $team->id)
            ->where('external_accounts.provider', ExternalProvider::Google->value)
            ->selectRaw('COUNT(*) as mapped_total')
            ->selectRaw('COALESCE(SUM(CASE WHEN contacts.id IS NOT NULL AND contacts.deleted_at IS NULL THEN 1 ELSE 0 END), 0) as local_active')
            ->selectRaw('COALESCE(SUM(CASE WHEN contacts.deleted_at IS NOT NULL THEN 1 ELSE 0 END), 0) as local_soft_deleted')
            ->selectRaw('COALESCE(SUM(CASE WHEN contacts.id IS NULL THEN 1 ELSE 0 END), 0) as missing_local_row')
            ->first() ?? new stdClass;

        $rows = DB::table('contact_sync_mappings')
            ->join('external_accounts', 'external_accounts.id', '=', 'contact_sync_mappings.external_account_id')
            ->leftJoin('contacts', 'contacts.id', '=', 'contact_sync_mappings.contact_id')
            ->leftJoin('users', 'users.id', '=', 'external_accounts.user_id')
            ->where('external_accounts.team_id', $team->id)
            ->where('external_accounts.provider', ExternalProvider::Google->value)
            ->orderByDesc('contact_sync_mappings.last_synced_at')
            ->limit(500)
            ->select([
                'contact_sync_mappings.external_id',
                'contact_sync_mappings.last_synced_at',
                'contact_sync_mappings.contact_id',
                'contacts.name',
                'contacts.surname',
                'contacts.email',
                'contacts.phone',
                'contacts.deleted_at as contact_deleted_at',
                'external_accounts.last_synced_at as account_last_synced_at',
                'users.name as connected_by_name',
                'users.email as connected_by_email',
            ])
            ->get();

        return view('integrations.google-synced-contacts', [
            'rows' => $rows,
            'team' => $team,
            'googleAccounts' => $googleAccounts,
            'stats' => $stats,
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

        $googleAccounts = $this->googleAccountsForTeam($team->id, ExternalProvider::Google);

        $stats = DB::table('calendar_event_sync_mappings')
            ->join('external_accounts', 'external_accounts.id', '=', 'calendar_event_sync_mappings.external_account_id')
            ->leftJoin('calendar_events', 'calendar_events.id', '=', 'calendar_event_sync_mappings.calendar_event_id')
            ->where('external_accounts.team_id', $team->id)
            ->where('external_accounts.provider', ExternalProvider::Google->value)
            ->selectRaw('COUNT(*) as mapped_total')
            ->selectRaw('COALESCE(SUM(CASE WHEN calendar_events.id IS NOT NULL AND calendar_events.deleted_at IS NULL THEN 1 ELSE 0 END), 0) as local_visible')
            ->selectRaw('COALESCE(SUM(CASE WHEN calendar_events.deleted_at IS NOT NULL THEN 1 ELSE 0 END), 0) as local_soft_deleted')
            ->selectRaw('COALESCE(SUM(CASE WHEN calendar_events.id IS NULL THEN 1 ELSE 0 END), 0) as missing_local_row')
            ->first() ?? new stdClass;

        $rows = DB::table('calendar_event_sync_mappings')
            ->join('external_accounts', 'external_accounts.id', '=', 'calendar_event_sync_mappings.external_account_id')
            ->leftJoin('calendar_events', 'calendar_events.id', '=', 'calendar_event_sync_mappings.calendar_event_id')
            ->leftJoin('users', 'users.id', '=', 'external_accounts.user_id')
            ->where('external_accounts.team_id', $team->id)
            ->where('external_accounts.provider', ExternalProvider::Google->value)
            ->orderByDesc('calendar_events.start')
            ->orderByDesc('calendar_event_sync_mappings.last_synced_at')
            ->limit(500)
            ->select([
                'calendar_event_sync_mappings.calendar_event_id',
                'calendar_event_sync_mappings.external_id',
                'calendar_event_sync_mappings.last_synced_at',
                'calendar_events.title',
                'calendar_events.start',
                'calendar_events.end',
                'calendar_events.all_day',
                'calendar_events.deleted_at as local_deleted_at',
                'calendar_events.google_event_id as local_google_event_id',
                'external_accounts.last_synced_at as account_last_synced_at',
                'users.name as connected_by_name',
                'users.email as connected_by_email',
            ])
            ->get();

        return view('integrations.google-synced-calendar', [
            'rows' => $rows,
            'team' => $team,
            'googleAccounts' => $googleAccounts,
            'stats' => $stats,
        ]);
    }

    /**
     * @return Collection<int, ExternalAccount>
     */
    private function googleAccountsForTeam(int $teamId, ExternalProvider $provider): Collection
    {
        return ExternalAccount::query()
            ->where('team_id', $teamId)
            ->where('provider', $provider)
            ->with(['user' => static function ($query): void
            {
                $query->select('id', 'name', 'email');
            }])
            ->orderByDesc('last_synced_at')
            ->get();
    }
}
