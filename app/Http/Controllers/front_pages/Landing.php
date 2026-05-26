<?php

namespace App\Http\Controllers\front_pages;

use App\Http\Controllers\Controller;

class Landing extends Controller
{
    public function index()
    {
        $pageConfigs = ['myLayout' => 'front'];

        return view('content.front-pages.humano-landing-page', [
            'pageConfigs' => $pageConfigs,
            'guidePresentations' => [
                [
                    'url' => url('/humano-presentacion.html'),
                    'title' => __('Primeros pasos'),
                    'subtitle' => __('Cómo funciona Humano'),
                    'description' => __('Presentación interactiva: configuración del negocio, onboarding y módulos principales.'),
                ],
            ],
        ]);
    }
}
