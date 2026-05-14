<?php

return [
    'page_title' => 'Ayuda — Guía sobre SPF y DNS para el envío de correo',
    'title' => 'Ayuda — Guía sobre SPF y DNS para el envío de correo',
    'intro' => 'Cuando tu equipo usa el correo saliente de la plataforma (SMTP del sistema), Humano comprueba el dominio de la dirección «De». Debes publicar un registro SPF TXT exacto en el apex de ese dominio.',

    'required_record_heading' => 'Valor TXT (SPF) obligatorio',
    'required_record_body' => 'Crea o actualiza un registro TXT en la raíz del dominio de envío (la parte después de @ en el remitente del equipo). El valor debe coincidir exactamente (solo pueden variar espacios y mayúsculas/minúsculas):',

    'domain_heading' => '¿Qué dominio?',
    'domain_body' => 'La comprobación usa el dominio del remitente «De» configurado para tu equipo (Ajustes del equipo → correo / notificaciones). Si envías como noreply@ejemplo.com, el SPF se valida en ejemplo.com.',

    'why_heading' => '¿Por qué un registro exacto?',
    'why_body' => 'Autoriza la infraestructura de envío de Revision Alpha para tu dominio y mantiene la política estricta (-all) para que los receptores confíen en la alineación que espera la plataforma.',

    'propagation_heading' => 'Propagación DNS',
    'propagation_body' => 'Tras guardar en tu proveedor DNS, los resolvers globales pueden tardar desde minutos hasta 48 horas. Humano lee DNS desde el servidor de la aplicación; herramientas como MXToolbox pueden mostrar el registro antes o después que tu servidor, según la caché del resolver.',

    'verify_heading' => 'Cómo comprobarlo',
    'verify_body' => 'Usa un comprobador SPF/DNS externo para el apex de tu dominio, o consulta TXT desde terminal (ejemplo):',
    'verify_note' => 'Deberías ver una línea TXT que coincide con el valor requerido (tras normalizar espacios).',

    'own_smtp_heading' => 'Si usas SMTP propio',
    'own_smtp_body' => 'Si tu equipo configura SMTP personalizado (host, usuario, etc.) en los ajustes del equipo, estas comprobaciones de SPF para «SMTP del sistema» no aplican al envío por tu propio servidor—siguen aplicando los requisitos de tu proveedor.',

    'back_to_help' => 'Volver al inicio de Ayuda',
];
