<?php

namespace App\Http\Controllers;

use App\Enums\EmailPlan;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailPlansManagementController extends Controller
{
    /**
     * Show email plans management interface
     */
    public function index()
    {
        // Only admin users can access email plans management
        if (! Auth::user()->hasRole('admin'))
        {
            abort(403, 'Only admin users can manage email plans');
        }

        $teams = Team::with(['contacts'])
            ->orderBy('name')
            ->get()
            ->map(function ($team)
            {
                $remaining = $team->getRemainingEmails();
                $limits = $team->isOverLimits();
                $actualUsage = $team->getActualEmailUsage();
                $planDetails = $team->getPlanDetails();

                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'plan' => $team->getEmailPlan(),
                    'plan_config' => $team->getEmailPlanConfig(),
                    'remaining' => $remaining,
                    'limits' => $limits,
                    'actual_usage' => $actualUsage,
                    'contacts_count' => $team->contacts()->count(),
                    'assigned_by' => $planDetails['assigned_by'],
                    'assigned_at' => $planDetails['assigned_at'],
                ];
            });

        $availablePlans = collect(EmailPlan::cases())->map(function ($plan)
        {
            return [
                'value' => $plan->value,
                'name' => $plan->getDisplayName(),
                'description' => $plan->getDescription(),
                'config' => $plan->getConfig(),
            ];
        });

        return view('email-plans-management.index', compact('teams', 'availablePlans'));
    }

    /**
     * Assign email plan to team (AJAX)
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
     * Get team details (AJAX)
     */
    public function show(Team $team)
    {
        if (! Auth::user()->hasRole('admin'))
        {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        $remaining = $team->getRemainingEmails();
        $limits = $team->isOverLimits();
        $actualUsage = $team->getActualEmailUsage();
        $plan = $team->getEmailPlan();
        $planDetails = $team->getPlanDetails();

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
                'actual_usage' => $actualUsage,
                'status' => [
                    'over_monthly' => $limits['over_monthly'],
                    'over_daily' => $limits['over_daily'],
                    'over_contacts' => $limits['over_contacts'],
                    'can_send' => $limits['can_send'],
                ],
                'contacts_count' => $team->contacts()->count(),
                'contact_limit' => $team->getContactLimit(),
                'assigned_by' => $planDetails['assigned_by']?->name ?? 'System',
                'assigned_at' => $planDetails['assigned_at']?->format('d/m/Y H:i'),
            ],
        ]);
    }

    /**
     * Sync email usage with actual database values (AJAX)
     */
    public function syncUsage(Team $team)
    {
        if (! Auth::user()->hasRole('admin'))
        {
            return response()->json([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        try
        {
            $team->syncEmailUsage();
            $remaining = $team->fresh()->getRemainingEmails();
            $actualUsage = $team->getActualEmailUsage();

            return response()->json([
                'success' => true,
                'message' => "Usage synced successfully for {$team->name}",
                'usage' => [
                    'monthly_used' => $remaining['monthly_used'],
                    'daily_used' => $remaining['daily_used'],
                    'actual_usage' => $actualUsage,
                ],
            ]);
        } catch (\Exception $e)
        {
            return response()->json([
                'success' => false,
                'message' => 'Error syncing usage: '.$e->getMessage(),
            ], 500);
        }
    }
}
