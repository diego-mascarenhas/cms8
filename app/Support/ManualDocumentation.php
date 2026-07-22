<?php

namespace App\Support;

class ManualDocumentation
{
    /**
     * Role comparison matrix used across manual pages.
     *
     * @return array<string, array{
     *     admin: list<string>,
     *     collaborator: list<string>,
     *     client?: list<string>,
     *     collaborator_blocked?: list<string>,
     *     client_blocked?: list<string>
     * }>
     */
    public static function roleMatrix(): array
    {
        return [
            'getting-started' => [
                'admin' => [
                    'Configura el equipo, marca, módulos activos e invita usuarios.',
                    'Asigna roles (admin / collaborator / client) y gestiona permisos.',
                    'Vincula un contacto a un usuario Client para darle acceso de portal.',
                    'Ve todos los datos del equipo sin filtros por responsable.',
                ],
                'collaborator' => [
                    'Entra al equipo al que fue invitado y trabaja en módulos operativos.',
                    'Ve el menú según módulos (sin facturación ni infraestructura).',
                    'Alcance en proyectos/servicios: propio o asignado.',
                ],
                'client' => [
                    'Usuario final vinculado a un contacto del equipo (portal en la misma app).',
                    'Ve solo sus proyectos/servicios/empresas asociados a su contacto.',
                    'No cambia de equipo ni ve menús de billing o infraestructura.',
                ],
            ],
            'dashboard' => [
                'admin' => [
                    'Resumen del equipo completo: contactos, proyectos, métricas y alertas.',
                    'Puede ver Insight diario / performance del equipo si está activo.',
                ],
                'collaborator' => [
                    'Dashboard y Hoy centrados en su trabajo y tareas del día.',
                    'No ve paneles financieros del equipo.',
                ],
                'client' => [
                    'Entra a un dashboard de cliente (vista operativa restringida).',
                    'No ve métricas internas ni finanzas del proveedor.',
                ],
            ],
            'contacts' => [
                'admin' => [
                    'CRUD completo de contactos (crear, editar, eliminar).',
                    'Asigna responsables y gestiona Lista de 60 de todo el equipo.',
                    'Puede vincular contacto → usuario Client (acceso portal).',
                ],
                'collaborator' => [
                    'Lista, crea y edita contactos del equipo.',
                    'No elimina contactos ni asigna asesores.',
                ],
                'client' => [
                    'Ve su propia ficha de contacto (datos personales asociados).',
                    'No gestiona la agenda CRM del equipo.',
                ],
                'client_blocked' => [
                    'Lista de contactos del equipo, importación, Lista de 60, prospección.',
                ],
            ],
            'clients' => [
                'admin' => [
                    'Gestión completa de clientes/empresas y vínculo con facturación.',
                    'Importación y búsqueda de negocios.',
                ],
                'collaborator' => [
                    'Puede ver, crear y actualizar clientes del equipo.',
                    'No accede a facturas ni cobros.',
                ],
                'client' => [
                    'Consulta la/s empresa/s vinculadas a su contacto (solo lectura).',
                    'No crea ni edita fichas de otros clientes.',
                ],
            ],
            'collaborators' => [
                'admin' => [
                    'Alta de colaboradores, tarifas, disponibilidad y asignación a proyectos.',
                ],
                'collaborator' => [
                    'Consulta perfiles relevantes para el trabajo.',
                    'No gestiona tarifas de otros.',
                ],
                'client' => [
                    'No usa el módulo de catálogo de colaboradores.',
                ],
                'client_blocked' => [
                    'Listado y tarifas de colaboradores internos.',
                ],
            ],
            'services' => [
                'admin' => [
                    'Catálogo completo de servicios y precios.',
                ],
                'collaborator' => [
                    'Crea servicios; edita sobre todo los de su responsabilidad.',
                ],
                'client' => [
                    'Puede ver servicios vinculados a sus empresas/proyectos.',
                    'No edita el catálogo ni precios.',
                ],
            ],
            'projects' => [
                'admin' => [
                    'CRUD completo, presupuesto, precios y asignación de equipo.',
                ],
                'collaborator' => [
                    'Crea proyectos; ve/edita si es responsable o está asignado.',
                    'Sin eliminar; precios de billing ocultos.',
                ],
                'client' => [
                    'Consulta proyectos de sus empresas (avance, alcance).',
                    'Puede abrir presupuestos públicos por enlace token (/p/budget/…).',
                    'No edita ni elimina proyectos ni ve costes internos.',
                ],
            ],
            'tasks' => [
                'admin' => [
                    'Asigna responsables, fechas y ve el tablero del equipo.',
                ],
                'collaborator' => [
                    'Ejecuta tareas, Kanban y registro de tiempo propio.',
                ],
                'client' => [
                    'No opera el Kanban interno del equipo.',
                    'El seguimiento lo ve vía proyecto / ticket / comunicación.',
                ],
                'client_blocked' => [
                    'Crear tareas internas, cronómetro, asistencia.',
                ],
            ],
            'chat' => [
                'admin' => [
                    'Configura WhatsApp del equipo, QR y asistente.',
                ],
                'collaborator' => [
                    'Atiende conversaciones según acceso del equipo.',
                ],
                'client' => [
                    'Se comunica por los canales que el equipo expone (chat/WhatsApp del contacto).',
                    'No configura el número ni el asistente del equipo.',
                ],
            ],
            'products-and-orders' => [
                'admin' => [
                    'CRUD completo de productos, tiendas y pedidos.',
                ],
                'collaborator' => [
                    'Ve/crea/actualiza; eliminar es admin.',
                ],
                'client' => [
                    'No administra el catálogo e-commerce del proveedor.',
                ],
                'client_blocked' => [
                    'Productos, tiendas y pedidos internos.',
                ],
            ],
            'billing' => [
                'admin' => [
                    'Facturas, pagos, ingresos, gastos, tarifas y finanzas.',
                    'Sync de pagos (MercadoPago, Stripe, etc.).',
                ],
                'collaborator' => [
                    'Sin acceso al menú de facturación.',
                ],
                'client' => [
                    'No entra al módulo de facturación del equipo proveedor.',
                    'Recibe facturas/cobros por los canales que el admin configure (email, enlace).',
                ],
                'collaborator_blocked' => [
                    'Facturas, pagos, ingresos, gastos, tarifas, finanzas, afiliados.',
                ],
                'client_blocked' => [
                    'Todo el menú Billing e infraestructura.',
                ],
            ],
            'campaigns' => [
                'admin' => [
                    'Campañas email/SMS y plantillas del equipo.',
                ],
                'collaborator' => [
                    'Uso operativo de mensajes/plantillas si el módulo está activo.',
                ],
                'client' => [
                    'Es destinatario de campañas, no emisor.',
                ],
                'client_blocked' => [
                    'Crear o lanzar campañas.',
                ],
            ],
            'team' => [
                'admin' => [
                    'Usuarios, roles, departamentos y ajustes del equipo.',
                    'Invita o vincula usuarios Client desde contactos.',
                ],
                'collaborator' => [
                    'Sin user-management; departamentos según módulo.',
                ],
                'client' => [
                    'No administra usuarios ni departamentos.',
                    'Su rol Client queda bloqueado si está vinculado a un contacto.',
                ],
                'client_blocked' => [
                    'Gestión de usuarios, invitaciones, departamentos.',
                ],
            ],
            'more-features' => [
                'admin' => [
                    'Prompts, embudos, automatizaciones, infraestructura, CMS, suscripción.',
                ],
                'collaborator' => [
                    'CMS/multimedia según módulos; sin automatizaciones ni servidores.',
                ],
                'client' => [
                    'Puede crear tickets de soporte (asunto, prioridad, descripción, adjuntos).',
                    'Sin prompts, embudos, automatizaciones ni servidores.',
                ],
            ],
        ];
    }

    /**
     * Mockup catalog: forms and flow diagrams.
     *
     * @return list<array{slug: string, route: string, title: string, description: string, icon: string, roles: list<string>, type: string}>
     */
    public static function mockups(): array
    {
        return [
            [
                'slug' => 'overview',
                'route' => 'mockups.overview',
                'title' => 'Mapa general',
                'description' => 'Diagrama de flujo de roles: Admin, Collaborator y Client.',
                'icon' => 'ti-map',
                'roles' => ['admin', 'collaborator', 'client'],
                'type' => 'flow',
            ],
            [
                'slug' => 'roles-flow',
                'route' => 'mockups.roles-flow',
                'title' => 'Flujo por roles',
                'description' => 'Carriles paralelos: qué hace cada rol de punta a punta.',
                'icon' => 'ti-arrows-split-2',
                'roles' => ['admin', 'collaborator', 'client'],
                'type' => 'flow',
            ],
            [
                'slug' => 'client-journey',
                'route' => 'mockups.client-journey',
                'title' => 'Viaje del Client',
                'description' => 'Alta → login → proyectos → ticket → presupuesto.',
                'icon' => 'ti-user-heart',
                'roles' => ['client', 'admin'],
                'type' => 'flow',
            ],
            [
                'slug' => 'client-ticket',
                'route' => 'mockups.client-ticket',
                'title' => 'Ticket del Client',
                'description' => 'Formulario de soporte del usuario final.',
                'icon' => 'ti-ticket',
                'roles' => ['client'],
                'type' => 'form',
            ],
            [
                'slug' => 'client-home',
                'route' => 'mockups.client-home',
                'title' => 'Home del Client',
                'description' => 'Qué ve el cliente al entrar (vista restringida).',
                'icon' => 'ti-layout-dashboard',
                'roles' => ['client'],
                'type' => 'form',
            ],
            [
                'slug' => 'contact-form',
                'route' => 'mockups.contact-form',
                'title' => 'Formulario de contacto',
                'description' => 'Campos del alta/edición de contactos (staff).',
                'icon' => 'ti-address-book',
                'roles' => ['admin', 'collaborator'],
                'type' => 'form',
            ],
            [
                'slug' => 'client-form',
                'route' => 'mockups.client-form',
                'title' => 'Formulario de cliente (CRM)',
                'description' => 'Ficha empresa que gestiona el equipo (no el portal Client).',
                'icon' => 'ti-briefcase',
                'roles' => ['admin', 'collaborator'],
                'type' => 'form',
            ],
            [
                'slug' => 'project-form',
                'route' => 'mockups.project-form',
                'title' => 'Formulario de proyecto',
                'description' => 'Alta de proyecto; precios solo admin.',
                'icon' => 'ti-folders',
                'roles' => ['admin', 'collaborator'],
                'type' => 'form',
            ],
            [
                'slug' => 'task-form',
                'route' => 'mockups.task-form',
                'title' => 'Formulario de tarea',
                'description' => 'Título, estado, fechas y responsable.',
                'icon' => 'ti-list-check',
                'roles' => ['admin', 'collaborator'],
                'type' => 'form',
            ],
            [
                'slug' => 'service-form',
                'route' => 'mockups.service-form',
                'title' => 'Formulario de servicio',
                'description' => 'Catálogo de servicios del equipo.',
                'icon' => 'ti-tool',
                'roles' => ['admin', 'collaborator'],
                'type' => 'form',
            ],
            [
                'slug' => 'invoice-flow',
                'route' => 'mockups.invoice-flow',
                'title' => 'Flujo de facturación',
                'description' => 'Diagrama: proyecto → factura → pago (admin).',
                'icon' => 'ti-receipt',
                'roles' => ['admin'],
                'type' => 'flow',
            ],
            [
                'slug' => 'collaborator-day',
                'route' => 'mockups.collaborator-day',
                'title' => 'Día típico del colaborador',
                'description' => 'Diagrama operativo diario del Collaborator.',
                'icon' => 'ti-user-star',
                'roles' => ['collaborator', 'admin'],
                'type' => 'flow',
            ],
            [
                'slug' => 'admin-setup',
                'route' => 'mockups.admin-setup',
                'title' => 'Arranque del admin',
                'description' => 'Diagrama de puesta en marcha del equipo.',
                'icon' => 'ti-settings',
                'roles' => ['admin'],
                'type' => 'flow',
            ],
        ];
    }

    /**
     * @return array{
     *     admin: list<string>,
     *     collaborator: list<string>,
     *     client?: list<string>,
     *     collaborator_blocked?: list<string>,
     *     client_blocked?: list<string>
     * }|null
     */
    public static function rolesFor(string $section): ?array
    {
        return self::roleMatrix()[$section] ?? null;
    }

    /**
     * @return array{slug: string, route: string, title: string, description: string, icon: string, roles: list<string>, type: string}|null
     */
    public static function mockup(string $slug): ?array
    {
        foreach (self::mockups() as $mockup)
        {
            if ($mockup['slug'] === $slug)
            {
                return $mockup;
            }
        }

        return null;
    }
}
