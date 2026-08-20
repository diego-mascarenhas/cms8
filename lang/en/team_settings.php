<?php

return [
    'group_saved' => 'Ajustes de :group guardados correctamente.',
    'groups' => [
        'stripe' => [
            'title' => 'Integración con Stripe',
            'subtitle' => 'Configura las claves API y el webhook de Stripe para este equipo.',
        ],
        'hosting' => [
            'title' => 'Hosting y cPanel',
            'subtitle' => 'Conecta servidores WHM/cPanel (reseller) o cuentas cPanel y sincroniza alojamientos.',
            'manage_servers' => 'Servidores',
            'manage_hosting' => 'Hosting',
            'modules_disabled' => 'Activa los módulos Servidores y Hosting en la cuenta del equipo.',
            'enable_modules' => 'Activar módulos',
            'ask_admin_modules' => 'Pide a un administrador que active los módulos Servidores y Hosting.',
            'servers_connected' => '{1} :count servidor conectado|[2,*] :count servidores conectados',
        ],
        'fiscal' => [
            'title' => 'Exportación fiscal',
            'subtitle' => 'Elige la plataforma fiscal y el país para el enrutado automático.',
        ],
        'affiliates' => [
            'title' => 'Afiliados',
            'subtitle' => 'Comisión global del programa de referidos de la plataforma.',
        ],
        'cuentica' => [
            'title' => 'Cuéntica',
            'subtitle' => 'Credenciales de Cuéntica para exportar facturas a España.',
        ],
        'categories' => [
            'title' => 'Categorías',
            'subtitle' => 'Valores por defecto y reglas para las categorías del equipo.',
        ],
        'notifications' => [
            'title' => 'Notificaciones',
            'subtitle' => 'Preferencias de notificación y remitente para avisos del equipo.',
        ],
        'api' => [
            'title' => 'Token de acceso API',
            'subtitle' => 'Nombre y permisos del token API del equipo.',
        ],
        'twilio' => [
            'title' => 'Configuración de Twilio',
            'subtitle' => 'Cuenta, números y URLs de webhook para SMS y WhatsApp.',
        ],
        'chat' => [
            'title' => 'Chat / Asistente',
            'subtitle' => 'Respuestas automáticas, pruebas y reglas del asistente.',
        ],
        'documents' => [
            'title' => 'OCR de documentos',
            'subtitle' => 'Motor de lectura de documentos en chat, WhatsApp y subidas.',
        ],
        'finance' => [
            'title' => 'Informes financieros',
            'subtitle' => 'Moneda única para totales de ingresos, gastos y panel contable.',
        ],
        'public_shop' => [
            'title' => 'Tienda pública del asistente',
            'subtitle' => 'Activa el catálogo público y revisa la URL generada.',
        ],
        'wordpress' => [
            'title' => 'Conexión con WordPress',
            'subtitle' => 'URL del sitio y contraseña de aplicación para publicar contenido.',
        ],
        'woocommerce' => [
            'title' => 'Integración con WooCommerce',
            'subtitle' => 'Credenciales REST API de tu tienda WooCommerce.',
        ],
        'email' => [
            'title' => 'Remitente del Mailer',
            'subtitle' => 'Remitente del equipo y remitente opcional para campañas del Mailer.',
        ],
        'email-plans' => [
            'title' => 'Planes y límites de email',
            'subtitle' => 'Plan actual, cupos mensuales, diarios y contactos.',
        ],
        'analytics' => [
            'title' => 'Servicios de Google',
            'subtitle' => 'Credenciales de Analytics y ID de propiedad GA4.',
        ],
        'google' => [
            'title' => 'Sincronización Google',
            'subtitle' => 'Importación y exportación de contactos y calendario con Google.',
        ],
        'webdav' => [
            'title' => 'Sincronización WebDAV',
            'subtitle' => 'Importación y exportación de contactos, calendario y tareas.',
        ],
        'paid_ads' => [
            'title' => 'Plataformas de Paid Ads',
            'subtitle' => 'Configura las credenciales API de Google, Meta, LinkedIn, TikTok y X para publicidad de pago.',
        ],
        'calendar' => [
            'title' => 'Calendario',
            'subtitle' => 'ID de calendario de Google para sincronización.',
        ],
    ],
    'sections' => [
        'notifications_general' => 'Canales de notificación',
        'notifications_sender' => 'Remitente de notificaciones',
        'notifications_performance_insights' => 'Insights de rendimiento',
        'wordpress_connection' => 'Conexión',
        'woocommerce_connection' => 'Conexión',
        'woocommerce_credentials' => 'Credenciales',
        'woocommerce_security' => 'Seguridad',
        'email_outgoing' => 'Correo saliente (SMTP)',
        'email_incoming' => 'Correo entrante (IMAP)',
        'google_inbound' => 'Importar hacia Humano',
        'google_outbound' => 'Exportar desde Humano',
        'webdav_inbound' => 'Importar hacia Humano',
        'webdav_outbound' => 'Exportar desde Humano',
        'email_plans_plan' => 'Plan actual',
        'email_plans_limits' => 'Límites de envío',
        'email_plans_contacts' => 'Contactos',
        'email_plans_reset' => 'Reinicios',
    ],
    'fields' => [
        'stripe_public' => [
            'label' => 'Clave pública',
            'help' => null,
        ],
        'stripe_secret' => [
            'label' => 'Clave secreta',
            'help' => null,
        ],
        'stripe_webhook' => [
            'label' => 'Secreto del webhook',
            'help' => null,
        ],
        'affiliate_commission_percent' => [
            'label' => 'Comisión de afiliados (%)',
            'help' => 'Porcentaje sobre cada cobro de equipos referidos. Solo el usuario root puede modificarlo.',
        ],
        'categories_default_status' => [
            'label' => 'Estado por defecto',
        ],
        'categories_require_approval' => [
            'label' => 'Requiere aprobación',
        ],
        'categories_max_depth' => [
            'label' => 'Profundidad máxima de subcategorías',
        ],
        'categories_allow_multiple_parents' => [
            'label' => 'Permitir varias categorías padre',
        ],
        'categories_default_ordering' => [
            'label' => 'Orden por defecto',
        ],
        'notifications_email_enabled' => [
            'label' => 'Notificaciones por email',
        ],
        'notifications_sms_enabled' => [
            'label' => 'Notificaciones por SMS',
        ],
        'notifications_from_name' => [
            'label' => 'Nombre del remitente',
            'placeholder' => 'Nombre de tu empresa',
        ],
        'notifications_from_email' => [
            'label' => 'Correo del remitente',
            'placeholder' => 'notificaciones@tudominio.com',
        ],
        'api_token_name' => [
            'label' => 'Nombre del token',
        ],
        'api_token_abilities' => [
            'label' => 'Permisos del token',
        ],
        'twilio_sid' => [
            'label' => 'Account SID',
        ],
        'twilio_token' => [
            'label' => 'Auth Token',
        ],
        'twilio_sms_from' => [
            'label' => 'Número SMS de origen',
        ],
        'twilio_whatsapp_from' => [
            'label' => 'Número WhatsApp de origen',
        ],
        'twilio_webhook_url' => [
            'label' => 'URL del webhook',
            'help' => 'URL generada automáticamente para tu equipo. Configúrala en la consola de Twilio.',
        ],
        'twilio_status_callback_url' => [
            'label' => 'URL de estado (callback)',
            'help' => 'URL generada automáticamente para tu equipo. Configúrala en la consola de Twilio.',
        ],
        'wordpress_url' => [
            'label' => 'URL del sitio',
            'placeholder' => 'https://tu-sitio.com',
            'help' => 'URL completa del sitio WordPress (sin /wp-json ni barra final).',
        ],
        'wordpress_username' => [
            'label' => 'Usuario',
            'placeholder' => 'admin',
            'help' => 'Usuario de WordPress con permisos para editar entradas y páginas.',
        ],
        'wordpress_application_password' => [
            'label' => 'Contraseña de aplicación',
            'placeholder' => 'xxxx xxxx xxxx xxxx xxxx xxxx',
            'help' => 'Generada en WordPress: Usuarios → tu usuario → Contraseñas de aplicación. Se almacena cifrada.',
        ],
        'woocommerce_url' => [
            'label' => 'URL de la tienda',
            'placeholder' => 'https://tu-tienda.com',
            'help' => 'La URL completa de tu tienda WooCommerce.',
        ],
        'woocommerce_api_version' => [
            'label' => 'Versión de la API',
        ],
        'woocommerce_consumer_key' => [
            'label' => 'Consumer Key',
            'placeholder' => 'ck_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'help' => 'Generada en WooCommerce > Ajustes > Avanzado > REST API.',
        ],
        'woocommerce_consumer_secret' => [
            'label' => 'Consumer Secret',
            'placeholder' => 'cs_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'help' => 'Generada en WooCommerce > Ajustes > Avanzado > REST API.',
        ],
        'woocommerce_verify_ssl' => [
            'label' => 'Verificar certificado SSL',
            'help' => 'Recomendado activar en producción con SSL válido.',
        ],
        'email_plan_display' => [
            'label' => 'Plan actual',
            'help' => 'Solo los administradores pueden cambiar el plan de email.',
        ],
        'email_plan_description' => [
            'label' => 'Descripción del plan',
        ],
        'email_monthly_limit' => [
            'label' => 'Límite mensual de emails',
            'help' => 'Máximo de emails por mes.',
        ],
        'email_monthly_used' => [
            'label' => 'Usados este mes',
        ],
        'email_daily_limit' => [
            'label' => 'Límite diario de emails',
            'help' => 'Máximo de emails por día (0 = ilimitado).',
        ],
        'email_daily_used' => [
            'label' => 'Usados hoy',
        ],
        'contact_limit' => [
            'label' => 'Límite de contactos',
            'help' => 'Número máximo de contactos permitidos.',
        ],
        'contact_count' => [
            'label' => 'Contactos actuales',
        ],
        'email_monthly_reset_at' => [
            'label' => 'Fecha de reinicio mensual',
        ],
        'email_daily_reset_date' => [
            'label' => 'Fecha de reinicio diario',
        ],
        'analytics_credentials_json' => [
            'label' => 'Credenciales de cuenta de servicio (JSON)',
            'placeholder' => 'Pega el JSON completo de Google Cloud Console...',
            'help' => 'Crea una cuenta de servicio en Google Cloud, activa Google Analytics Data API y descarga la clave JSON.',
        ],
        'analytics_property_id' => [
            'label' => 'ID de propiedad GA4',
            'placeholder' => '123456789',
            'help' => 'En Google Analytics: Administración > Detalles de la propiedad. Usa el ID numérico.',
        ],
        'google_calendar_id' => [
            'label' => 'ID de calendario de Google (opcional)',
            'placeholder' => 'primary o tu-calendario@group.calendar.google.com',
            'help' => 'Déjalo vacío para usar "primary". Para un calendario concreto, pega su ID desde los ajustes de Google Calendar.',
        ],
        'mailbox_spam_ai_enabled' => [
            'label' => 'Clasificación de spam con IA',
            'help' => 'Si está activo, los mensajes entrantes se clasifican con IA y se mueven a Spam cuando corresponde.',
        ],
        'mailbox_spam_ai_prompt' => [
            'label' => 'Prompt de clasificación de spam',
            'help' => 'Instrucciones opcionales para detectar spam. Déjalo vacío para usar el prompt por defecto.',
        ],
        'finance_reporting_currency' => [
            'label' => 'Moneda de informes',
            'help' => 'Los paneles de ingresos, gastos y contabilidad convierten los pagos aprobados a esta moneda.',
        ],
    ],
    'options' => [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'level_1' => '1 nivel',
        'level_2' => '2 niveles',
        'level_3' => '3 niveles',
        'name_asc' => 'Nombre (A-Z)',
        'name_desc' => 'Nombre (Z-A)',
        'created_desc' => 'Más recientes primero',
        'created_asc' => 'Más antiguos primero',
        'custom' => 'Orden personalizado',
        'abilities_all' => 'Todos los permisos',
        'abilities_read' => 'Solo lectura',
        'abilities_write' => 'Solo escritura',
        'abilities_read_write' => 'Lectura y escritura',
        'wc_v3' => 'v3 (recomendado)',
        'wc_v2' => 'v2',
        'wc_v1' => 'v1',
        'tls' => 'TLS',
        'ssl' => 'SSL',
        'none' => 'Ninguno',
        'not_set' => 'Sin definir',
        'basic_plan' => 'Plan básico de email',
        'fiscal_auto' => 'Automática (según país fiscal)',
        'fiscal_cuentica' => 'Cuéntica (España)',
        'fiscal_arca' => 'ARCA (Argentina)',
        'fiscal_none' => 'Ninguna (no exportar)',
        'country_es' => 'España',
        'country_ar' => 'Argentina',
    ],
    'site_assistant' => [
        'title' => 'Prompt del sitio (citas y ventas)',
        'intro' => 'Elegí el prompt que usa el asistente cuando nadie más reclamó el turno (sin embudo ni comando de carrito). Está pensado para reservar citas, mostrar el catálogo y vender.',
        'help_link' => 'Ver ayuda',
        'select_label' => 'Prompt del equipo',
        'select_off' => 'Sin asistente (no responde por defecto)',
        'select_empty' => 'Router automático (sin prompt fijo)',
        'select_help' => 'Sin asistente = WhatsApp no responde salvo que el chat tenga un prompt asignado. Router = clasifica solo. Un prompt fijo habla de citas, catálogo y compra.',
        'save' => 'Usar este prompt',
        'create_toggle' => 'Crear un prompt nuevo',
        'create_label' => 'Nombre',
        'create_instruction' => 'Instrucción para la IA',
        'create_help' => 'La plantilla ya cubre citas, catálogo y venta. Podés editarla. El embudo y los comandos de WhatsApp (comprar, finalizar) siguen funcionando aparte.',
        'create_submit' => 'Crear y usar',
        'recommended_label' => 'Citas, catálogo y ventas',
        'helper_text' => 'Prompt del sitio: reservas, catálogo y venta de productos.',
        'saved' => 'Prompt del sitio actualizado. El código de embed ya apunta a este flujo.',
        'created' => 'Prompt creado y seleccionado. Copiá el código de embed en la web del cliente.',
        'updated' => 'Prompt actualizado.',
        'update_submit' => 'Guardar cambios',
        'invalid_prompt' => 'Ese prompt no existe o no está activo en este equipo.',
        'missing_module' => 'No hay un módulo disponible para guardar el prompt.',
        'label_required' => 'El nombre es obligatorio.',
        'instruction_required' => 'La instrucción para la IA es obligatoria.',
        'embed_title' => 'Código para la web del cliente',
        'embed_help' => 'Pegá este bloque antes de cerrar el body. El widget habla con el prompt elegido.',
        'embed_docs' => 'Instrucciones y endpoint en Ayuda',
        'embed_empty' => 'Seleccioná o creá un prompt para generar el código de embed.',
        'embed_name' => 'Asistente web',
        'embed_description' => 'Asistente embebible: citas, catálogo y ventas.',
        'welcome_message' => 'Hola, ¿reservamos una cita o te muestro el catálogo?',
    ],
];
