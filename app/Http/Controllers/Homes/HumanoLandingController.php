<?php

namespace App\Http\Controllers\Homes;

use App\Http\Controllers\Controller;
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
                'description' => __('Presentación interactiva: configuración del negocio, onboarding y módulos principales.'),
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
        ]);
    }
}
