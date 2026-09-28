<?php

namespace App\Http\Controllers;

use App\DataTables\AffiliateDirectoryDataTable;
use App\Http\Requests\AssignAffiliateReferralRequest;
use App\Models\Team;
use App\Services\AffiliateProgramService;
use App\Support\AffiliateDirectoryAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AffiliateDirectoryController extends Controller
{
    public function __construct(
        private readonly AffiliateProgramService $affiliates,
    ) {}

    public function index(AffiliateDirectoryDataTable $dataTable): View|RedirectResponse|JsonResponse
    {
        if ($redirect = $this->ensureDirectory())
        {
            return $redirect;
        }

        return $dataTable->render('affiliate.index');
    }

    public function create(): View|RedirectResponse
    {
        if ($redirect = $this->ensureDirectory())
        {
            return $redirect;
        }

        $affiliates = $this->affiliateOptions();

        return view('affiliate.form', [
            'affiliates' => $affiliates,
            'team' => null,
        ]);
    }

    public function store(AssignAffiliateReferralRequest $request): RedirectResponse
    {
        $affiliate = Team::query()->findOrFail($request->integer('team_id'));

        try
        {
            $code = (string) $request->validated('subscription_code');

            $this->affiliates->claimReferral(
                $affiliate,
                $code,
                attributeCustomer: ! str_starts_with(strtolower($code), 'sub_'),
            );
        } catch (ValidationException $exception)
        {
            return redirect()->back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('affiliate.show', $affiliate)
            ->with('success', 'Código asignado al afiliado.');
    }

    public function show(Team $team): View|RedirectResponse
    {
        if ($redirect = $this->ensureDirectory())
        {
            return $redirect;
        }

        $team->loadMissing('owner');

        $referrals = $this->affiliates->referralAssignments($team);

        $commissions = $team->billingAffiliateCommissionsAsReferrer()
            ->with(['payingTeam.owner'])
            ->latest()
            ->limit(200)
            ->get();

        return view('affiliate.show', [
            'team' => $team,
            'referrals' => $referrals,
            'commissions' => $commissions,
            'canAssign' => $team->canUseAffiliateProgram() && trim((string) ($team->stripe_id ?? '')) !== '',
        ]);
    }

    public function destroyReferral(Request $request, Team $team): RedirectResponse
    {
        if ($redirect = $this->ensureDirectory())
        {
            return $redirect;
        }

        $validated = $request->validate([
            'subscription_code' => ['required', 'string', 'max:64'],
        ]);

        try
        {
            $this->affiliates->releaseReferral($team, $validated['subscription_code']);
        } catch (ValidationException $exception)
        {
            return redirect()->back()->withErrors($exception->errors());
        }

        return redirect()
            ->route('affiliate.show', $team)
            ->with('success', 'Código desvinculado.');
    }

    /**
     * @return array<int, string>
     */
    private function affiliateOptions(): array
    {
        return Team::query()
            ->whereNotNull('stripe_id')
            ->where('stripe_id', '!=', '')
            ->where(function ($query): void
            {
                $query->whereNull('referred_by')->orWhere('referred_by', '');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'stripe_id'])
            ->mapWithKeys(fn (Team $team): array => [
                $team->id => $team->name.' ('.$team->stripe_id.')',
            ])
            ->all();
    }

    private function ensureDirectory(): ?RedirectResponse
    {
        $user = auth()->user();

        abort_unless($user !== null && $user->can('access-billing-modules'), 403);

        if (! AffiliateDirectoryAccess::allows($user))
        {
            return redirect()->route('billing.index');
        }

        return null;
    }
}
