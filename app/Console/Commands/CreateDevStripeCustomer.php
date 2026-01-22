<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Stripe\Stripe;

class CreateDevStripeCustomer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:create-dev-customer 
                            {--team-id= : ID del team local a asociar}
                            {--email=dev@humano.test : Email del customer}
                            {--name=Dev Customer : Nombre del customer}
                            {--subscriptions= : IDs de precios separados por coma (ej: price_1,price_2)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea un customer de prueba en Stripe con múltiples suscripciones para desarrollo';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Stripe::setApiKey(config('cashier.secret'));

        // Verificar que estamos en TEST MODE
        if (! str_contains(config('cashier.key', ''), 'pk_test_'))
        {
            $this->error('⚠️  Este comando solo funciona en TEST MODE. Verifica tu configuración.');

            return self::FAILURE;
        }

        $email = $this->option('email');
        $name = $this->option('name');
        $teamId = $this->option('team-id');

        $this->info('🚀 Creando customer de desarrollo en Stripe (TEST MODE)...');
        $this->newLine();

        try
        {
            // Crear o obtener customer en Stripe
            $this->info("📧 Buscando customer con email: {$email}");

            $existingCustomers = \Stripe\Customer::all([
                'email' => $email,
                'limit' => 1,
            ]);

            if (count($existingCustomers->data) > 0)
            {
                $customer = $existingCustomers->data[0];
                $this->warn("  ⚠️  Customer ya existe: {$customer->id}");
            } else
            {
                $customer = \Stripe\Customer::create([
                    'email' => $email,
                    'name' => $name,
                    'metadata' => [
                        'dev_customer' => 'true',
                        'created_by' => 'dev-command',
                    ],
                ]);
                $this->info("  ✅ Customer creado: {$customer->id}");
            }

            // Obtener o crear team local
            $team = null;
            if ($teamId)
            {
                $team = Team::find($teamId);
                if (! $team)
                {
                    $this->error("  ❌ Team con ID {$teamId} no encontrado");

                    return self::FAILURE;
                }
            } else
            {
                // Buscar team por email del owner o crear uno nuevo
                $user = User::where('email', $email)->first();
                if ($user)
                {
                    $team = $user->allTeams()->first();
                }

                if (! $team)
                {
                    // Crear usuario si no existe
                    if (! $user)
                    {
                        $user = User::create([
                            'name' => $name,
                            'email' => $email,
                            'password' => bcrypt('password'),
                            'email_verified_at' => now(),
                        ]);
                        $user->assignRole('admin');
                        $this->info("  ✅ Usuario creado: {$user->email}");
                    }

                    // Crear team
                    $team = Team::create([
                        'user_id' => $user->id,
                        'name' => $name,
                        'personal_team' => false,
                    ]);
                    $user->teams()->attach($team, ['role' => 'admin']);
                    $user->current_team_id = $team->id;
                    $user->save();
                    $this->info("  ✅ Team creado: {$team->name} (ID: {$team->id})");
                }
            }

            // Asociar customer de Stripe con team
            if ($team->stripe_id !== $customer->id)
            {
                $team->stripe_id = $customer->id;
                $team->save();
                $this->info("  ✅ Team asociado con Stripe customer: {$customer->id}");
            }

            // Crear suscripciones
            $priceIds = $this->option('subscriptions');
            if ($priceIds)
            {
                $priceIdsArray = explode(',', $priceIds);
            } else
            {
                // Usar precios por defecto para desarrollo
                $priceIdsArray = [
                    'price_1SitE6RwN51ygFdeuoV0tTLf', // Mailer Basic
                    'price_1SitGURwN51ygFdeoS4K9YDn', // Mailer Foundation
                    'price_1SqaWJRwN51ygFdew8AD7i0S', // WordPress Profesional
                    'price_1SqaWPRwN51ygFdeviaeCVOz', // WordPress Support
                ];
            }

            $this->newLine();
            $this->info('📦 Creando suscripciones...');

            $createdSubscriptions = [];
            foreach ($priceIdsArray as $priceId)
            {
                $priceId = trim($priceId);
                if (empty($priceId))
                {
                    continue;
                }

                try
                {
                    // Verificar que el precio existe
                    $price = \Stripe\Price::retrieve($priceId);
                    $product = is_string($price->product) ? \Stripe\Product::retrieve($price->product) : $price->product;

                    // Determinar tipo de suscripción
                    $subscriptionType = $this->determineSubscriptionType($product);

                    // Crear suscripción en Stripe
                    // En modo test, usamos collection_method: 'send_invoice' para no requerir método de pago
                    $subscriptionData = [
                        'customer' => $customer->id,
                        'items' => [
                            [
                                'price' => $priceId,
                            ],
                        ],
                        'metadata' => [
                            'team_id' => $team->id,
                            'type' => $subscriptionType,
                            'dev_subscription' => 'true',
                        ],
                        'collection_method' => 'send_invoice',
                        'days_until_due' => 30,
                    ];

                    $subscription = \Stripe\Subscription::create($subscriptionData);

                    // Para desarrollo, podemos marcar la suscripción como activa manualmente
                    // o simplemente aceptar que estará en estado 'unpaid' hasta que se pague
                    // En test mode, esto es aceptable para desarrollo

                    // Crear registro local
                    $localSubscription = $team->subscriptions()->create([
                        'user_id' => $team->user_id,
                        'type' => $subscriptionType,
                        'stripe_id' => $subscription->id,
                        'stripe_status' => $subscription->status,
                        'stripe_price' => $priceId,
                        'quantity' => $subscription->items->data[0]->quantity ?? 1,
                        'trial_ends_at' => $subscription->trial_end ? \Carbon\Carbon::createFromTimestamp($subscription->trial_end) : null,
                        'ends_at' => null,
                    ]);

                    $createdSubscriptions[] = [
                        'id' => $subscription->id,
                        'product' => $product->name,
                        'type' => $subscriptionType,
                        'status' => $subscription->status,
                    ];

                    $this->line("  ✅ Suscripción creada: {$product->name} ({$subscriptionType}) - {$subscription->status}");
                } catch (\Exception $e)
                {
                    $this->error("  ❌ Error creando suscripción con precio {$priceId}: {$e->getMessage()}");
                }
            }

            // Resumen
            $this->newLine();
            $this->info('📊 Resumen:');
            $this->table(
                ['Campo', 'Valor'],
                [
                    ['Customer ID', $customer->id],
                    ['Email', $customer->email],
                    ['Team ID', $team->id],
                    ['Team Name', $team->name],
                    ['Suscripciones Creadas', count($createdSubscriptions)],
                ],
            );

            if (count($createdSubscriptions) > 0)
            {
                $this->newLine();
                $this->info('📋 Suscripciones:');
                $this->table(
                    ['Producto', 'Tipo', 'Status', 'Stripe ID'],
                    array_map(function ($sub)
                    {
                        return [
                            $sub['product'],
                            $sub['type'],
                            $sub['status'],
                            $sub['id'],
                        ];
                    }, $createdSubscriptions),
                );
            }

            $this->newLine();
            $this->info('✅ Customer de desarrollo creado exitosamente!');
            $this->info("💡 Puedes sincronizar las suscripciones con: php artisan stripe:sync-subscription {$team->id}");

            return self::SUCCESS;
        } catch (\Exception $e)
        {
            $this->error('❌ Error: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());

            return self::FAILURE;
        }
    }

    /**
     * Determine subscription type from product
     */
    private function determineSubscriptionType($product): string
    {
        // Check product metadata
        if (isset($product->metadata['type']) && ! empty($product->metadata['type']))
        {
            return $product->metadata['type'];
        }

        // Check product name for keywords
        $productName = strtolower($product->name ?? '');

        if (str_contains($productName, 'mailer') || str_contains($productName, 'email'))
        {
            return 'mailer';
        } elseif (str_contains($productName, 'hosting') || str_contains($productName, 'wordpress'))
        {
            return 'hosting';
        } elseif (str_contains($productName, 'domain'))
        {
            return 'domain';
        } elseif (str_contains($productName, 'support'))
        {
            return 'support';
        } elseif (str_contains($productName, 'licen'))
        {
            return 'licence';
        } elseif (str_contains($productName, 'strategic') || str_contains($productName, 'framework'))
        {
            return 'mentoring';
        }

        return 'default';
    }
}
