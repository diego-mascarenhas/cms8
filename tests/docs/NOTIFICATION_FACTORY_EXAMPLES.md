# NotificationFactory - Ejemplos de Uso

El `NotificationFactory` proporciona una forma fácil de generar notificaciones de prueba con datos realistas para desarrollo y testing.

## Uso Básico

### Crear una notificación simple
```php
use App\Models\Notification;

// Crear una notificación básica
$notification = Notification::factory()->create();

// Crear múltiples notificaciones
$notifications = Notification::factory()->count(10)->create();
```

### Especificar datos específicos
```php
// Crear notificación para un contacto específico
$notification = Notification::factory()->create([
    'contact_id' => 1,
    'team_id' => 1,
    'user_id' => 1
]);
```

## Estados Disponibles

### Estados de Envío
```php
// Notificación no enviada
$notification = Notification::factory()->unsent()->create();

// Notificación enviada pero no leída
$notification = Notification::factory()->sentUnread()->create();

// Notificación enviada y leída
$notification = Notification::factory()->sentRead()->create();
```

### Tipos Especiales
```php
// Notificación urgente
$notification = Notification::factory()->urgent()->create();

// Notificación relacionada con proyecto
$notification = Notification::factory()->projectRelated()->create();

// Notificación relacionada con pagos
$notification = Notification::factory()->paymentRelated()->create();
```

## Combinando Estados

```php
// Notificación urgente y no leída
$notification = Notification::factory()
    ->urgent()
    ->sentUnread()
    ->create();

// Notificación de proyecto pendiente de envío
$notification = Notification::factory()
    ->projectRelated()
    ->unsent()
    ->create();
```

## Uso en Testing

### Ejemplo de Test
```php
/** @test */
public function user_can_view_their_notifications()
{
    // Crear usuario y equipo
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $contact = Contact::factory()->create(['team_id' => $team->id]);
    
    // Crear notificaciones para el contacto
    $notifications = Notification::factory()
        ->count(5)
        ->state([
            'team_id' => $team->id,
            'contact_id' => $contact->id,
            'user_id' => $user->id
        ])
        ->create();
    
    // Simular autenticación
    $this->actingAs($user);
    
    // Visitar página de notificaciones
    $response = $this->get("/collaborator/{$contact->id}/notifications");
    
    $response->assertStatus(200);
    $response->assertSee($notifications->first()->subject);
}
```

### Test de Estados
```php
/** @test */
public function notification_states_are_displayed_correctly()
{
    $contact = Contact::factory()->create();
    
    // Crear notificación no enviada
    $unsentNotification = Notification::factory()
        ->unsent()
        ->create(['contact_id' => $contact->id]);
    
    // Crear notificación enviada no leída
    $unreadNotification = Notification::factory()
        ->sentUnread()
        ->create(['contact_id' => $contact->id]);
    
    // Verificar badges de estado
    $this->assertStringContainsString('Pendiente', $unsentNotification->status_badge);
    $this->assertStringContainsString('Enviado', $unreadNotification->status_badge);
}
```

## Datos Generados

### Contenido Realista
El factory genera contenido realista para el contexto de traducción:
- Asuntos variados (proyectos, pagos, bienvenidas, etc.)
- Mensajes contextuales en español
- Referencias a proyectos, tareas y pagos
- Metadatos específicos según el tipo

### Fechas Inteligentes
- `sent_at`: Entre 30 días atrás y ahora (solo si está enviado)
- `read_at`: Entre fecha de envío y ahora (solo si está leído)
- `created_at`: Automático por Laravel

### Metadatos Contextuales
```php
// Ejemplos de metadata generados
[
    'priority' => 'high',
    'category' => 'urgent'
]

[
    'project_type' => 'translation',
    'language_pair' => 'en-es',
    'word_count' => 2500
]

[
    'amount' => 1500,
    'currency' => 'EUR',
    'payment_method' => 'paypal'
]
```

## Uso en Seeding

```php
// En un seeder
public function run(): void
{
    $contacts = Contact::all();
    $users = User::all();
    $teams = Team::all();
    
    foreach ($contacts as $contact) {
        // Crear 3-8 notificaciones por contacto
        Notification::factory()
            ->count(rand(3, 8))
            ->state([
                'contact_id' => $contact->id,
                'team_id' => $contact->team_id,
                'user_id' => $users->random()->id
            ])
            ->create();
    }
}
```

## Desarrollo Local

### Generar datos para un colaborador específico
```php
// En tinker: php artisan tinker
$contact = Contact::find(1);

// Crear notificaciones variadas
Notification::factory()->count(3)->urgent()->sentUnread()->create(['contact_id' => $contact->id]);
Notification::factory()->count(2)->projectRelated()->sentRead()->create(['contact_id' => $contact->id]);
Notification::factory()->count(1)->paymentRelated()->unsent()->create(['contact_id' => $contact->id]);
```

### Limpiar y regenerar
```php
// Eliminar todas las notificaciones y crear nuevas
Notification::truncate();
Artisan::call('db:seed', ['--class' => 'NotificationSeeder']);
```

## Personalización

### Extender el Factory
Para agregar nuevos tipos de notificaciones:

```php
// En NotificationFactory.php
public function certificateRelated(): static
{
    return $this->state(fn (array $attributes) => [
        'subject' => 'Nueva certificación disponible',
        'message' => 'Se ha publicado una nueva certificación que puede interesarte...',
        'metadata' => [
            'certificate_type' => 'translation',
            'level' => 'advanced',
            'deadline' => now()->addDays(30)->format('Y-m-d')
        ],
    ]);
}
```

### Usar en Tests
```php
$notification = Notification::factory()->certificateRelated()->create();
``` 