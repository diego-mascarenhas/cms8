<?php

namespace App\Http\Controllers;

class ManualController extends Controller
{
    /**
     * Short guide: login, WhatsApp QR, products, orders, assistant.
     */
    public function ayuda()
    {
        return view('manual.ayuda');
    }

    public function index()
    {
        return view('manual.index');
    }

    public function gettingStarted()
    {
        return view('manual.getting-started');
    }

    public function dashboard()
    {
        return view('manual.dashboard');
    }

    public function contacts()
    {
        return view('manual.contacts');
    }

    public function clients()
    {
        return view('manual.clients');
    }

    public function collaborators()
    {
        return view('manual.collaborators');
    }

    public function services()
    {
        return view('manual.services');
    }

    public function projects()
    {
        return view('manual.projects');
    }

    public function opportunities()
    {
        return view('manual.opportunities');
    }

    public function tasks()
    {
        return view('manual.tasks');
    }

    public function chat()
    {
        return view('manual.chat');
    }

    public function tickets()
    {
        return view('manual.tickets');
    }

    public function productsAndOrders()
    {
        return view('manual.products-and-orders');
    }

    public function billing()
    {
        return view('manual.billing');
    }

    public function campaigns()
    {
        return view('manual.campaigns');
    }

    public function automation()
    {
        return view('manual.automation');
    }

    public function website()
    {
        return view('manual.website');
    }

    public function team()
    {
        return view('manual.team');
    }

    public function moreFeatures()
    {
        return view('manual.more-features');
    }

    /**
     * @return list<array{route: string, title: string, description: string, icon: string}>
     */
    public static function guideSections(): array
    {
        return [
            [
                'route' => 'manual.getting-started',
                'title' => __('Primeros pasos'),
                'description' => __('Roles, equipos y navegación básica.'),
                'icon' => 'ti-rocket',
            ],
            [
                'route' => 'manual.dashboard',
                'title' => __('Dashboard, Hoy y Calendario'),
                'description' => __('Resumen, día a día y agenda.'),
                'icon' => 'ti-layout-dashboard',
            ],
            [
                'route' => 'manual.contacts',
                'title' => __('Contactos'),
                'description' => __('Gestión de contactos, prospección y Lista de 60.'),
                'icon' => 'ti-address-book',
            ],
            [
                'route' => 'manual.clients',
                'title' => __('Clientes'),
                'description' => __('Fichas de clientes y datos relacionados.'),
                'icon' => 'ti-briefcase',
            ],
            [
                'route' => 'manual.collaborators',
                'title' => __('Colaboradores'),
                'description' => __('Perfiles, tarifas, disponibilidad y portafolios.'),
                'icon' => 'ti-user-star',
            ],
            [
                'route' => 'manual.services',
                'title' => __('Servicios'),
                'description' => __('Servicios que ofreces y su uso en proyectos.'),
                'icon' => 'ti-tool',
            ],
            [
                'route' => 'manual.projects',
                'title' => __('Proyectos'),
                'description' => __('Crear y gestionar proyectos, presupuestos y colaboradores.'),
                'icon' => 'ti-folders',
            ],
            [
                'route' => 'manual.opportunities',
                'title' => __('Oportunidades'),
                'description' => __('Pipeline comercial y seguimiento de deals.'),
                'icon' => 'ti-chart-donut',
            ],
            [
                'route' => 'manual.tasks',
                'title' => __('Tareas y tiempo'),
                'description' => __('Tareas, kanban, registro de tiempo y asistencia.'),
                'icon' => 'ti-list-check',
            ],
            [
                'route' => 'manual.chat',
                'title' => __('Chat y WhatsApp'),
                'description' => __('Conversaciones e integración con WhatsApp.'),
                'icon' => 'ti-brand-whatsapp',
            ],
            [
                'route' => 'manual.tickets',
                'title' => __('Tickets'),
                'description' => __('Soporte interno y portal del Client.'),
                'icon' => 'ti-ticket',
            ],
            [
                'route' => 'manual.products-and-orders',
                'title' => __('E-commerce'),
                'description' => __('Productos, tiendas y pedidos.'),
                'icon' => 'ti-shopping-cart',
            ],
            [
                'route' => 'manual.billing',
                'title' => __('Facturas y pagos'),
                'description' => __('Facturación, suscripciones, afiliados y finanzas.'),
                'icon' => 'ti-receipt',
            ],
            [
                'route' => 'manual.campaigns',
                'title' => __('Marketing'),
                'description' => __('Campañas, mensajes, plantillas y paid ads.'),
                'icon' => 'ti-mail',
            ],
            [
                'route' => 'manual.automation',
                'title' => __('Automatización'),
                'description' => __('Prompts, embudos, automatizaciones e integraciones.'),
                'icon' => 'ti-robot',
            ],
            [
                'route' => 'manual.website',
                'title' => __('Sitio web y contenidos'),
                'description' => __('Landing, CMS, multimedia y academia.'),
                'icon' => 'ti-world',
            ],
            [
                'route' => 'manual.team',
                'title' => __('Equipo'),
                'description' => __('Usuarios, departamentos y organización.'),
                'icon' => 'ti-users-group',
            ],
            [
                'route' => 'manual.more-features',
                'title' => __('Más funciones'),
                'description' => __('Infraestructura, notificaciones y plan Humano.'),
                'icon' => 'ti-dots',
            ],
        ];
    }
}
