<?php

namespace App\Http\Controllers;

use App\Enums\CampaignType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CampaignsController extends Controller
{
    public function index(): View
    {
        return view('campaigns.index', [
            'campaignTypes' => CampaignType::cases(),
        ]);
    }

    public function edit(string $campaign): View
    {
        return view('campaigns.edit', ['campaign' => $campaign]);
    }

    public function selectTemplate(Request $request): View
    {
        $selectedType = $request->string('type')->toString();
        $selectedTitle = $request->string('title')->toString();

        return view('campaigns.templates-select', [
            'selectedType' => $selectedType,
            'selectedTypeLabel' => $selectedType === 'sequences' ? 'Secuencia de correo' : 'Difusión por correo',
            'selectedTitle' => $selectedTitle,
            'customTemplates' => [
                [
                    'id' => 1,
                    'name' => 'PRUEBA',
                    'description' => 'BOH BOH',
                    'preview' => 'https://placehold.co/700x900/f8f9fa/adb5bd?text=Plantilla+Personalizada',
                ],
                [
                    'id' => 2,
                    'name' => 'PRUEBA SANDRA',
                    'description' => 'Plantilla base para secuencias.',
                    'preview' => 'https://placehold.co/700x900/f8f9fa/adb5bd?text=Plantilla+Sandra',
                ],
            ],
            'kajabiTemplates' => [
                [
                    'id' => 101,
                    'name' => 'Squiggle',
                    'description' => 'Plantilla simple para destacar el contenido.',
                    'preview' => 'https://placehold.co/700x900/f6f7fb/a0a5b1?text=Squiggle',
                ],
                [
                    'id' => 102,
                    'name' => 'Slice',
                    'description' => 'Ideal para dar una bienvenida divertida.',
                    'preview' => 'https://placehold.co/700x900/f6f7fb/a0a5b1?text=Slice',
                ],
                [
                    'id' => 103,
                    'name' => 'Timber',
                    'description' => 'Minimalista y perfecta para newsletters.',
                    'preview' => 'https://placehold.co/700x900/f6f7fb/a0a5b1?text=Timber',
                ],
                [
                    'id' => 104,
                    'name' => 'Brush',
                    'description' => 'Con cabecera prominente para promocionar.',
                    'preview' => 'https://placehold.co/700x900/f6f7fb/a0a5b1?text=Brush',
                ],
            ],
        ]);
    }
}
