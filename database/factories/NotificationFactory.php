<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\NotificationType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isSent = $this->faker->boolean(70); // 70% chance of being sent
        $isRead = $isSent ? $this->faker->boolean(50) : false; // Only sent notifications can be read

        // Get notification subjects and messages based on type
        $notificationContent = $this->getRandomNotificationContent();

        // Get a random existing contact from team_id 1
        $contact = Contact::where('team_id', 1)->inRandomOrder()->first();
        $contactId = $contact ? $contact->id : null;

        return [
            'team_id' => 1,
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'contact_id' => $contactId,
            'type_id' => NotificationType::inRandomOrder()->first()?->id ?? NotificationType::factory(),
            'reference' => $this->faker->optional(0.3)->randomElement([
                'project_' . $this->faker->numberBetween(1, 100),
                'task_' . $this->faker->numberBetween(1, 200),
                'payment_' . $this->faker->numberBetween(1, 50),
            ]),
            'subject' => $notificationContent['subject'],
            'message' => $notificationContent['message'],
            'is_sent' => $isSent,
            'sent_at' => $isSent ? $this->faker->dateTimeBetween('-30 days', 'now') : null,
            'sent_data' => $isSent ? [
                'email_provider' => 'sendgrid',
                'message_id' => $this->faker->uuid(),
                'attempts' => $this->faker->numberBetween(1, 3),
            ] : null,
            'is_read' => $isRead,
            'read_at' => $isRead ? $this->faker->dateTimeBetween('-15 days', 'now') : null,
            'metadata' => $this->faker->optional(0.4)->randomElement([
                ['priority' => 'high', 'category' => 'urgent'],
                ['source' => 'automated', 'trigger' => 'project_update'],
                ['reminder_count' => $this->faker->numberBetween(1, 3)],
                ['custom_field' => $this->faker->word()],
            ]),
        ];
    }

    /**
     * Get random notification content based on type
     */
    private function getRandomNotificationContent(): array
    {
        $contents = [
            [
                'subject' => 'Nuevo proyecto asignado: ' . $this->faker->catchPhrase(),
                'message' => 'Hola, te hemos asignado un nuevo proyecto de traducción. El proyecto "' . $this->faker->catchPhrase() . '" requiere traducción de ' . $this->faker->randomElement(['inglés', 'francés', 'alemán', 'italiano']) . ' a español. Por favor, revisa los detalles y confirma tu disponibilidad.',
            ],
            [
                'subject' => 'Actualización importante del proyecto',
                'message' => 'El proyecto en el que estás trabajando ha sido actualizado. Se han añadido nuevos archivos y se ha modificado la fecha de entrega. Por favor, revisa los cambios en tu panel de control y ajusta tu planificación según sea necesario.',
            ],
            [
                'subject' => 'Recordatorio de pago pendiente',
                'message' => 'Te recordamos que tienes un pago pendiente por el proyecto completado el mes pasado. El monto es de €' . $this->faker->numberBetween(500, 5000) . '. Por favor, revisa tu factura y procede con el pago a la brevedad posible.',
            ],
            [
                'subject' => '¡Bienvenido al equipo!',
                'message' => '¡Es un placer tenerte en nuestro equipo de traductores profesionales! Hemos revisado tu perfil y estamos emocionados de trabajar contigo. En los próximos días recibirás información sobre nuestros procesos y tu primer proyecto.',
            ],
            [
                'subject' => 'Nueva tarea asignada - Urgente',
                'message' => 'Se te ha asignado una nueva tarea con prioridad alta. La tarea "' . $this->faker->sentence(3) . '" debe completarse antes del ' . $this->faker->dateTimeBetween('now', '+7 days')->format('d/m/Y') . '. Por favor, confirma que puedes hacerte cargo de esta tarea.',
            ],
            [
                'subject' => 'Feedback del cliente disponible',
                'message' => 'El cliente ha dejado comentarios sobre tu último trabajo. En general, está muy satisfecho con la calidad de la traducción. Puedes ver los comentarios detallados en la sección de proyectos. ¡Excelente trabajo!',
            ],
            [
                'subject' => 'Actualización de perfil requerida',
                'message' => 'Necesitamos que actualices tu perfil profesional. Por favor, revisa y actualiza tu información de contacto, especialidades y tarifas. Esto nos ayudará a asignarte proyectos más adecuados a tu experiencia.',
            ],
            [
                'subject' => 'Certificación disponible',
                'message' => 'Hay una nueva certificación disponible en tu área de especialización. Esta certificación puede mejorar tu perfil y darte acceso a proyectos mejor remunerados. ¿Te interesa participar?',
            ],
            [
                'subject' => 'Evaluación de calidad completada',
                'message' => 'Hemos completado la evaluación de calidad de tu último proyecto. Tu puntuación fue de ' . $this->faker->numberBetween(85, 100) . '/100. ¡Felicitaciones por mantener altos estándares de calidad!',
            ],
            [
                'subject' => 'Oportunidad de colaboración especial',
                'message' => 'Tenemos un proyecto especial que creemos que se ajusta perfectamente a tu perfil. Es un proyecto de larga duración con un cliente premium. Si estás interesado, por favor confirma tu disponibilidad.',
            ],
        ];

        return $this->faker->randomElement($contents);
    }

    /**
     * Indicate that the notification is unsent.
     */
    public function unsent(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sent' => false,
            'sent_at' => null,
            'sent_data' => null,
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    /**
     * Indicate that the notification is sent but unread.
     */
    public function sentUnread(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sent' => true,
            'sent_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'sent_data' => [
                'email_provider' => 'sendgrid',
                'message_id' => $this->faker->uuid(),
                'attempts' => 1,
            ],
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    /**
     * Indicate that the notification is sent and read.
     */
    public function sentRead(): static
    {
        $sentAt = $this->faker->dateTimeBetween('-30 days', 'now');
        
        return $this->state(fn (array $attributes) => [
            'is_sent' => true,
            'sent_at' => $sentAt,
            'sent_data' => [
                'email_provider' => 'sendgrid',
                'message_id' => $this->faker->uuid(),
                'attempts' => 1,
            ],
            'is_read' => true,
            'read_at' => $this->faker->dateTimeBetween($sentAt, 'now'),
        ]);
    }

    /**
     * Indicate that the notification is urgent.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'subject' => '🚨 URGENTE: ' . $attributes['subject'],
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'priority' => 'urgent',
                'requires_immediate_attention' => true,
            ]),
        ]);
    }

    /**
     * Create a project-related notification.
     */
    public function projectRelated(): static
    {
        return $this->state(fn (array $attributes) => [
            'reference' => 'project_' . $this->faker->numberBetween(1, 100),
            'subject' => $this->faker->randomElement([
                'Nuevo proyecto asignado: ' . $this->faker->catchPhrase(),
                'Actualización del proyecto: ' . $this->faker->catchPhrase(),
                'Proyecto completado: ' . $this->faker->catchPhrase(),
                'Comentarios del proyecto: ' . $this->faker->catchPhrase(),
            ]),
            'metadata' => [
                'project_type' => $this->faker->randomElement(['translation', 'interpretation', 'review']),
                'language_pair' => $this->faker->randomElement(['en-es', 'fr-es', 'de-es', 'it-es']),
                'word_count' => $this->faker->numberBetween(500, 10000),
            ],
        ]);
    }

    /**
     * Create a payment-related notification.
     */
    public function paymentRelated(): static
    {
        return $this->state(fn (array $attributes) => [
            'reference' => 'payment_' . $this->faker->numberBetween(1, 50),
            'subject' => $this->faker->randomElement([
                'Pago procesado correctamente',
                'Recordatorio de pago pendiente',
                'Factura disponible para descarga',
                'Actualización de información de pago',
            ]),
            'metadata' => [
                'amount' => $this->faker->numberBetween(100, 5000),
                'currency' => 'EUR',
                'payment_method' => $this->faker->randomElement(['bank_transfer', 'paypal', 'stripe']),
                'invoice_number' => 'INV-' . $this->faker->numberBetween(1000, 9999),
            ],
        ]);
    }
}
