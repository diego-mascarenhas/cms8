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

    public function classicEditor(Request $request): View
    {
        $selectedType = $request->string('type')->toString();
        $selectedTitle = $request->string('title')->toString();
        $selectedTemplateId = $request->integer('template_id');

        return view('campaigns.classic-editor', [
            'selectedTypeLabel' => $selectedType === 'sequences' ? 'Secuencia de correo' : 'Difusión por correo',
            'selectedTitle' => $selectedTitle,
            'selectedTemplateId' => $selectedTemplateId,
            'sendTimes' => [
                '0' => '12:00 AM',
                '60' => '01:00 AM',
                '120' => '02:00 AM',
                '180' => '03:00 AM',
                '240' => '04:00 AM',
                '300' => '05:00 AM',
                '360' => '06:00 AM',
                '420' => '07:00 AM',
                '480' => '08:00 AM',
                '540' => '09:00 AM',
                '600' => '10:00 AM',
                '660' => '11:00 AM',
                '720' => '12:00 PM',
                '780' => '01:00 PM',
                '840' => '02:00 PM',
                '900' => '03:00 PM',
                '960' => '04:00 PM',
                '1020' => '05:00 PM',
                '1080' => '06:00 PM',
                '1140' => '07:00 PM',
                '1200' => '08:00 PM',
                '1260' => '09:00 PM',
                '1320' => '10:00 PM',
                '1380' => '11:00 PM',
            ],
        ]);
    }
}
