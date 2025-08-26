<?php

namespace App\Http\Controllers;

use App\Enums\EmailPlan;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailPlanController extends Controller
{
    /**
     * Show email plans management page (admin only)
     */
    public function index()
    {
        $this->authorize('viewAny', Team::class);

        if (! Auth::user()->hasRole('admin'))
        {
            abort(403, 'Only admin users can manage email plans');
        }

        $teams = Team::with(['emailPlanAssignedBy', 'contacts'])
            ->orderBy('name')
            ->get()
            ->map(function ($team)
            {
                $remaining = $team->getRemainingEmails();
                $limits = $team->isOverLimits();

                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'plan' => $team->getEmailPlan(),
                    'plan_config' => $team->getEmailPlanConfig(),
                    'remaining' => $remaining,
                    'limits' => $limits,
                    'contacts_count' => $team->contacts()->count(),
                    'assigned_by' => $team->emailPlanAssignedBy?->name ?? 'System',
                ];
            });

        $availablePlans = collect(EmailPlan::getAll())->map(function ($plan)
        {
            return [
                'value' => $plan->value,
                'config' => $plan->getConfig(),
            ];
        });

        return view('email-plans.index', compact('teams', 'availablePlans'));
    }

    /**
     * Assign email plan to team (admin only)
     */
    public function assign(Request $request, Team $team)
    {
        if (! Auth::user()->hasRole('admin'))
        {
            return response()->json([
                'success' => false,
                'message' => 'Only admin users can assign email plans',
            ], 403);
        }

        $request->validate([
            'plan' => 'required|string|in:basic,foundation,scale',
        ]);

        try
        {
            $plan = EmailPlan::from($request->plan);
            $team->assignEmailPlan($plan, Auth::id());

            return response()->json([
                'success' => true,
                'message' => "Successfully assigned {$plan->getDisplayName()} plan to {$team->name}",
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'plan' => $plan->getDisplayName(),
                    'config' => $team->fresh()->getEmailPlanConfig(),
                ],
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error assigning plan: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get team email plan details (AJAX)
     */
    public function show(Team $team)
    {
        $remaining = $team->getRemainingEmails();
        $limits = $team->isOverLimits();
        $plan = $team->getEmailPlan();

        return response()->json([
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'plan' => [
                    'name' => $plan->getDisplayName(),
                    'description' => $plan->getDescription(),
                    'value' => $plan->value,
                ],
                'limits' => [
                    'monthly_limit' => $remaining['monthly_limit'],
                    'monthly_used' => $remaining['monthly_used'],
                    'monthly_remaining' => $remaining['monthly_remaining'],
                    'daily_limit' => $remaining['daily_limit'],
                    'daily_used' => $remaining['daily_used'],
                    'daily_remaining' => $remaining['daily_remaining'],
                ],
                'status' => [
                    'over_monthly' => $limits['over_monthly'],
                    'over_daily' => $limits['over_daily'],
                    'over_contacts' => $limits['over_contacts'],
                    'can_send' => $limits['can_send'],
                ],
                'contacts_count' => $team->contacts()->count(),
                'contact_limit' => $team->contact_limit,
                'assigned_by' => $team->emailPlanAssignedBy?->name ?? 'System',
                'assigned_at' => $team->email_plan_assigned_at?->format('d/m/Y H:i'),
            ],
        ]);
    }

    /**
     * Show current team's email plan (for team members)
     */
    public function current()
    {
        $team = Auth::user()->currentTeam;

        if (! $team)
        {
            abort(404, 'No current team found');
        }

        $remaining = $team->getRemainingEmails();
        $limits = $team->isOverLimits();

        return view('email-plans.current', compact('team', 'remaining', 'limits'));
    }
}
