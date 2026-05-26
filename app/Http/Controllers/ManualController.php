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

    /**
     * Display the user manual index
     */
    public function index()
    {
        return view('manual.index');
    }

    /**
     * Display the getting started / overview section
     */
    public function gettingStarted()
    {
        return view('manual.getting-started');
    }

    /**
     * Display the dashboard and today section
     */
    public function dashboard()
    {
        return view('manual.dashboard');
    }

    /**
     * Display the contacts and prospecting section
     */
    public function contacts()
    {
        return view('manual.contacts');
    }

    /**
     * Display the clients section
     */
    public function clients()
    {
        return view('manual.clients');
    }

    /**
     * Display the collaborators section
     */
    public function collaborators()
    {
        return view('manual.collaborators');
    }

    /**
     * Display the services section
     */
    public function services()
    {
        return view('manual.services');
    }

    /**
     * Display the projects section
     */
    public function projects()
    {
        return view('manual.projects');
    }

    /**
     * Display the tasks and time tracking section
     */
    public function tasks()
    {
        return view('manual.tasks');
    }

    /**
     * Display the chat and WhatsApp section
     */
    public function chat()
    {
        return view('manual.chat');
    }

    /**
     * Display the products and orders (e-commerce) section
     */
    public function productsAndOrders()
    {
        return view('manual.products-and-orders');
    }

    /**
     * Display the billing section (invoices, payments, income, expenses)
     */
    public function billing()
    {
        return view('manual.billing');
    }

    /**
     * Display the messages and templates (campaigns) section
     */
    public function campaigns()
    {
        return view('manual.campaigns');
    }

    /**
     * Display the team section (users, departments)
     */
    public function team()
    {
        return view('manual.team');
    }

    /**
     * Display the rest of features (enterprises, contents, prompts, etc.)
     */
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
                'title' => __('Dashboard y Hoy'),
                'description' => __('Vista general y vista del día.'),
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
                'route' => 'manual.products-and-orders',
                'title' => __('Productos y pedidos'),
                'description' => __('Catálogo de productos y gestión de pedidos.'),
                'icon' => 'ti-shopping-cart',
            ],
            [
                'route' => 'manual.billing',
                'title' => __('Facturas y pagos'),
                'description' => __('Facturación, pagos, ingresos, gastos y panel financiero.'),
                'icon' => 'ti-receipt',
            ],
            [
                'route' => 'manual.campaigns',
                'title' => __('Mensajes y plantillas'),
                'description' => __('Campañas de email/SMS y plantillas de mensajes.'),
                'icon' => 'ti-mail',
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
                'description' => __('Empresas, contenidos, prompts, notificaciones y otras herramientas.'),
                'icon' => 'ti-dots',
            ],
        ];
    }
}
