<?php

namespace App\Http\Controllers\Homes;

use App\Http\Controllers\Controller;
use App\Services\HumanoPricingPlanResolver;
use App\Support\HumanoHomeAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HumanoLandingController extends Controller
{
    /**
     * @return list<array{url: string, title: string, subtitle: string, description: string}>
     */
    public static function guidePresentations(): array
    {
        return [
            [
                'url' => HumanoHomeAsset::url('presentations/primeros-pasos.html'),
                'title' => __('Primeros pasos'),
                'subtitle' => __('Cómo funciona Humano'),
                'description' => __('Configuración del negocio en seis pasos: marca, contacto, desafío e informe.'),
            ],
            [
                'url' => HumanoHomeAsset::url('presentations/chat-contactos-modulos.html'),
                'title' => __('Chat, contactos y módulos'),
                'subtitle' => __('El día a día en el panel'),
                'description' => __('Conversaciones, agenda de contactos y herramientas según tu plan.'),
            ],
        ];
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
            'landingPlans' => app(HumanoPricingPlanResolver::class)->plansForDisplay(),
        ]);
    }

    public function chatWhatsappEmbed(): View
    {
        return view('homes.humano.presentations.embed.chat-whatsapp');
    }
}
