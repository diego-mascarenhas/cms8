<?php

namespace Database\Seeders;

use App\Models\NotificationType;
use Illuminate\Database\Seeder;

class NotificationTypesSeeder extends Seeder
{
    public function run()
    {
        $types = [
            [
                'name' => 'Project Assignment',
                'template_subject' => 'Nuevo proyecto asignado: {project_name}',
                'template_body' => 'Hola {contact_name},

Te contactamos desde {team_name} porque tenemos un nuevo proyecto disponible.

**Proyecto:** {project_name}
**Servicio:** {service_type}
**Idiomas:** {language_pair}
**Fecha de entrega:** {due_date}

{custom_message}

¿Podrías confirmarnos tu tarifa y disponibilidad?

Saludos,
{sender_name}',
                'is_customizable' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Project Update',
                'template_subject' => 'Actualización del proyecto: {project_name}',
                'template_body' => 'Hola {contact_name},

Hay una actualización en el proyecto "{project_name}".

{custom_message}

Si tienes alguna pregunta, no dudes en contactarnos.

Saludos,
{sender_name}',
                'is_customizable' => true,
                'is_active' => true,
            ],
            [
                'name' => 'General Message',
                'template_subject' => 'Mensaje de {team_name}',
                'template_body' => 'Hola {contact_name},

{custom_message}

Saludos,
{sender_name}',
                'is_customizable' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Payment Reminder',
                'template_subject' => 'Recordatorio de pago - {reference}',
                'template_body' => 'Hola {contact_name},

Te recordamos que tienes un pago pendiente.

**Referencia:** {reference}
**Monto:** {amount}
**Fecha de vencimiento:** {due_date}

{custom_message}

Por favor, procede con el pago lo antes posible.

Saludos,
{sender_name}',
                'is_customizable' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Task Assignment',
                'template_subject' => 'Nueva tarea asignada: {task_title}',
                'template_body' => 'Hola {contact_name},

Se te ha asignado una nueva tarea.

**Tarea:** {task_title}
**Descripción:** {task_description}
**Fecha límite:** {due_date}

{custom_message}

Saludos,
{sender_name}',
                'is_customizable' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Welcome Message',
                'template_subject' => 'Bienvenido/a a {team_name}',
                'template_body' => 'Hola {contact_name},

¡Bienvenido/a a {team_name}!

{custom_message}

Estamos emocionados de trabajar contigo.

Saludos,
{sender_name}',
                'is_customizable' => false,
                'is_active' => true,
            ],
        ];

        foreach ($types as $type)
        {
            NotificationType::updateOrCreate(
                ['name' => $type['name']],
                $type,
            );
        }
    }
}
