<?php

namespace App\Http\Controllers\Homes;

use App\Http\Controllers\Controller;
use App\Services\HumanoPricingPlanResolver;
use App\Support\HumanoHomeAsset;
use Illuminate\View\View;

class SlashLandingController extends Controller
{
    /**
     * @return list<array{url: string, title: string, subtitle: string, description: string, icon: string}>
     */
    public static function guidePresentations(): array
    {
        return [
            [
                'url' => HumanoHomeAsset::url('presentations/primeros-pasos.html'),
                'title' => __('Primeros pasos'),
                'subtitle' => __('Cómo funciona Humano'),
                'description' => __('Configuración del negocio en seis pasos: marca, contacto, desafío e informe.'),
                'icon' => 'settings',
            ],
            [
                'url' => HumanoHomeAsset::url('presentations/chat-contactos-modulos.html'),
                'title' => __('Chat, contactos y módulos'),
                'subtitle' => __('El día a día en el panel'),
                'description' => __('Conversaciones, agenda de contactos y herramientas según tu plan.'),
                'icon' => 'messages',
            ],
            [
                'url' => HumanoHomeAsset::url('presentations/calendario.html'),
                'title' => __('Calendario'),
                'subtitle' => __('Agenda y eventos'),
                'description' => __('Vista mensual y semanal, citas con clientes y recordatorios del equipo.'),
                'icon' => 'calendar',
            ],
            [
                'url' => HumanoHomeAsset::url('presentations/tareas.html'),
                'title' => __('Tareas'),
                'subtitle' => __('Pendientes del equipo'),
                'description' => __('Lista y tablero por estado, responsables, fechas y vínculo con contactos.'),
                'icon' => 'layout-kanban',
            ],
            [
                'url' => HumanoHomeAsset::url('presentations/prospeccion.html'),
                'title' => __('Prospección'),
                'subtitle' => __('Buscar contactos'),
                'description' => __('Buscá perfiles por cargo y ubicación e importalos a tu agenda con créditos de prospectos.'),
                'icon' => 'target',
            ],
        ];
    }

    public static function isConfiguredAsPublicHome(): bool
    {
        if (config('app.public_home_route') === 'slash')
        {
            return true;
        }

        if (filled(config('app.public_home_route')))
        {
            return false;
        }

        $path = config('app.public_home_path');

        if (! is_string($path) || $path === '')
        {
            return false;
        }

        return '/'.ltrim($path, '/') === '/slash';
    }

    public function index(): View
    {
        if (! self::isConfiguredAsPublicHome())
        {
            abort(404);
        }

        return view('homes.slash.landing', [
            'guidePresentations' => self::guidePresentations(),
            'landingPlans' => app(HumanoPricingPlanResolver::class)->plansForDisplay(),
        ]);
    }
}
