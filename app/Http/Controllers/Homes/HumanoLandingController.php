<?php

namespace App\Http\Controllers\Homes;

use App\Http\Controllers\Controller;
use App\Services\HumanoPricingPlanResolver;
use App\Support\HumanoGuidePresentations;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HumanoLandingController extends Controller
{
    /**
     * @return list<array{url: string, title: string, subtitle: string, description: string, icon: string}>
     */
    public static function guidePresentations(): array
    {
        return HumanoGuidePresentations::all();
    }

    public function index(): View|RedirectResponse
    {
        if (auth()->check())
        {
            return redirect()->route('dashboard');
        }

        return view('homes.humano.landing', [
            'pageConfigs' => ['myLayout' => 'front'],
            'guidePresentations' => self::guidePresentations(),
            'landingPlans' => app(HumanoPricingPlanResolver::class)->plansForPublicDisplay(),
        ]);
    }

    public function chatWhatsappEmbed(): View
    {
        return view('homes.humano.presentations.embed.chat-whatsapp');
    }
}
