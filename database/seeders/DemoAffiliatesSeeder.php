<?php

namespace Database\Seeders;

use App\Models\AffiliateInvitation;
use App\Models\BillingAffiliateCommission;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Support\AffiliateCommission;
use Illuminate\Database\Seeder;

/**
 * Demo affiliate program data for team Demo: referral code, invitations, referred teams and commissions.
 *
 * Run from {@see TeamDemoSeeder} after demo users exist.
 * Standalone: php artisan db:seed --class=DemoAffiliatesSeeder
 */
class DemoAffiliatesSeeder extends Seeder
{
    public const DEMO_REFERRER_STRIPE_ID = 'cus_demo_referrer_humano';

    public function run(): void
    {
        $team = Team::query()->where('name', 'Demo')->orderBy('id')->first();

        if ($team === null)
        {
            $this->command?->warn('DemoAffiliatesSeeder: team "Demo" not found — skip.');

            return;
        }

        $this->command?->info('🤝 Seeding demo affiliate data for team Demo...');

        if (trim((string) $team->stripe_id) === '')
        {
            $team->forceFill(['stripe_id' => self::DEMO_REFERRER_STRIPE_ID])->save();
        }

        $team->refresh();

        $inviter = User::query()
            ->where('email', 'admin@humano.app')
            ->first() ?? $team->owner;

        if ($inviter !== null)
        {
            $this->seedInvitations($team, $inviter);
        } else
        {
            $this->command?->warn('DemoAffiliatesSeeder: no admin user for invitations — skip invitations.');
        }

        $agencyPercent = AffiliateCommission::agencyPercent();
        $subscriptionPercent = AffiliateCommission::percent();

        $this->seedReferredTeamsAndCommissions($team, [
            [
                'name' => 'Demo · Referido Estudio Norte',
                'email' => 'owner.estudionorte@demo.humano.app',
                'stripe_id' => 'cus_demo_ref_paying_norte',
                'services' => [
                    ['stripe_id' => 'sub_demo_norte_assistant', 'type' => 'assistant', 'percent' => $agencyPercent],
                    ['stripe_id' => 'sub_demo_norte_hosting', 'type' => 'hosting', 'percent' => $agencyPercent],
                ],
                'invoices' => [
                    ['id' => 'in_demo_aff_norte_1', 'amount_paid' => 9900, 'days_ago' => 45, 'percent' => $subscriptionPercent],
                    ['id' => 'in_demo_aff_norte_2', 'amount_paid' => 9900, 'days_ago' => 15, 'percent' => $subscriptionPercent],
                ],
            ],
            [
                'name' => 'Demo · Referido Agencia Beta',
                'email' => 'owner.agenciabeta@demo.humano.app',
                'stripe_id' => 'cus_demo_ref_paying_beta',
                'services' => [
                    ['stripe_id' => 'sub_demo_beta_shop', 'type' => 'shop', 'percent' => $agencyPercent],
                ],
                'invoices' => [
                    ['id' => 'in_demo_aff_beta_1', 'amount_paid' => 29900, 'days_ago' => 30, 'percent' => $subscriptionPercent],
                ],
            ],
            [
                'name' => 'Demo · Referido Taller Sur',
                'email' => 'owner.tallersur@demo.humano.app',
                'stripe_id' => 'cus_demo_ref_paying_taller',
                'services' => [
                    ['stripe_id' => 'sub_demo_taller_mailer', 'type' => 'mailer', 'percent' => $agencyPercent],
                ],
                'invoices' => [
                    ['id' => 'in_demo_aff_taller_1', 'amount_paid' => 4900, 'days_ago' => 8, 'percent' => $agencyPercent],
                ],
            ],
        ]);
        $this->seedLeticiaAffiliate($agencyPercent, $subscriptionPercent);

        $this->command?->info('✅ Demo affiliate data seeded');
    }

    private function seedInvitations(Team $team, User $inviter): void
    {
        $invitations = [
            [
                'invitee_name' => 'María López',
                'invitee_email' => 'maria.rodriguez@cliente2.com',
                'plan_id' => 'hunter',
                'plan_name' => (string) __('humano_pricing.plans.hunter.name'),
                'sent_at' => now()->subDays(5),
                'opened_at' => now()->subDays(4),
                'clicked_at' => null,
                'clicked_link' => null,
            ],
            [
                'invitee_name' => 'Estudio Norte',
                'invitee_email' => 'contacto@estudionorte.demo',
                'plan_id' => 'business',
                'plan_name' => (string) __('humano_pricing.plans.business.name'),
                'sent_at' => now()->subDays(10),
                'opened_at' => now()->subDays(9),
                'clicked_at' => now()->subDays(8),
                'clicked_link' => 'checkout',
            ],
            [
                'invitee_name' => 'Laura Sánchez',
                'invitee_email' => 'laura.sanchez@cliente6.com',
                'plan_id' => 'assistant',
                'plan_name' => (string) __('humano_pricing.plans.assistant.name'),
                'sent_at' => now()->subDays(2),
                'opened_at' => null,
                'clicked_at' => null,
                'clicked_link' => null,
            ],
        ];

        foreach ($invitations as $data)
        {
            $invitation = AffiliateInvitation::query()->firstOrNew([
                'team_id' => $team->id,
                'invitee_email' => $data['invitee_email'],
            ]);

            if ($invitation->tracking_token === null)
            {
                $invitation->tracking_token = AffiliateInvitation::generateTrackingToken();
            }

            $invitation->fill([
                'invited_by_user_id' => $inviter->id,
                'invitee_name' => $data['invitee_name'],
                'plan_id' => $data['plan_id'],
                'plan_name' => $data['plan_name'],
                'sent_at' => $data['sent_at'],
                'opened_at' => $data['opened_at'],
                'clicked_at' => $data['clicked_at'],
                'clicked_link' => $data['clicked_link'],
            ]);

            $invitation->save();
        }

        $this->command?->info('   · '.count($invitations).' affiliate invitations');
    }

    /**
     * @param  list<array{
     *     name: string,
     *     email: string,
     *     stripe_id: string,
     *     services: list<array{stripe_id: string, type: string, percent: float, referred_by?: string}>,
     *     invoices: list<array{id: string, amount_paid: int, days_ago: int, percent: float}>
     * }>  $referredTeams
     */
    private function seedReferredTeamsAndCommissions(Team $referrerTeam, array $referredTeams): void
    {
        $referrerStripeId = trim((string) $referrerTeam->stripe_id);

        if ($referrerStripeId === '')
        {
            return;
        }

        $commissionCount = 0;

        foreach ($referredTeams as $referred)
        {
            $owner = User::firstOrCreate(
                ['email' => $referred['email']],
                [
                    'name' => explode(' ', str_replace('Demo · Referido ', '', $referred['name']))[0] ?? 'Referido',
                    'password' => bcrypt('Simplicity!'),
                    'email_verified_at' => now(),
                ],
            );

            $payingTeam = Team::query()->firstOrCreate(
                ['name' => $referred['name']],
                [
                    'user_id' => $owner->id,
                    'personal_team' => false,
                ],
            );

            $payingTeam->forceFill([
                'stripe_id' => $referred['stripe_id'],
                'referred_by' => $referrerStripeId,
            ])->save();

            if (! $owner->teams()->where('team_id', $payingTeam->id)->exists())
            {
                $owner->teams()->attach($payingTeam->id, ['role' => 'admin']);
            }

            foreach ($referred['services'] as $service)
            {
                Subscription::query()->updateOrCreate(
                    ['stripe_id' => $service['stripe_id']],
                    [
                        'user_id' => $owner->id,
                        'team_id' => $payingTeam->id,
                        'type' => $service['type'],
                        'stripe_status' => 'active',
                        'stripe_price' => 'price_demo_'.$service['type'],
                        'quantity' => 1,
                        'referred_by' => $service['referred_by'] ?? $referrerStripeId,
                        'affiliate_commission_percent' => $service['percent'],
                    ],
                );
            }

            foreach ($referred['invoices'] as $invoice)
            {
                $amountPaid = (int) $invoice['amount_paid'];
                $invoicePercent = (float) $invoice['percent'];
                $commissionCents = (int) round($amountPaid * ($invoicePercent / 100));

                BillingAffiliateCommission::query()->updateOrCreate(
                    ['stripe_invoice_id' => $invoice['id']],
                    [
                        'paying_team_id' => $payingTeam->id,
                        'referrer_team_id' => $referrerTeam->id,
                        'paying_enterprise_id' => null,
                        'referrer_enterprise_id' => null,
                        'amount_paid_cents' => $amountPaid,
                        'currency' => 'EUR',
                        'commission_percent' => $invoicePercent,
                        'commission_amount_cents' => $commissionCents,
                        'created_at' => now()->subDays((int) $invoice['days_ago']),
                        'updated_at' => now()->subDays((int) $invoice['days_ago']),
                    ],
                );

                $commissionCount++;
            }
        }

        $this->command?->info("   · {$commissionCount} affiliate commissions for {$referrerTeam->name}");
    }

    private function seedLeticiaAffiliate(float $agencyPercent, float $subscriptionPercent): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'leticia.afiliado@demo.humano.app'],
            [
                'name' => 'Leticia',
                'password' => bcrypt('Simplicity!'),
                'email_verified_at' => now(),
            ],
        );

        $affiliate = Team::query()->firstOrCreate(
            ['name' => 'Demo · Afiliado Leticia'],
            [
                'user_id' => $owner->id,
                'personal_team' => false,
            ],
        );

        $affiliate->forceFill(['stripe_id' => 'cus_demo_referrer_leticia'])->save();

        if (! $owner->teams()->where('team_id', $affiliate->id)->exists())
        {
            $owner->teams()->attach($affiliate->id, ['role' => 'admin']);
        }

        $carlos = User::firstOrCreate(
            ['email' => 'carlos.afiliado@demo.humano.app'],
            [
                'name' => 'Carlos',
                'password' => bcrypt('Simplicity!'),
                'email_verified_at' => now(),
            ],
        );

        $carlosTeam = Team::query()->firstOrCreate(
            ['name' => 'Demo · Afiliado Carlos'],
            [
                'user_id' => $carlos->id,
                'personal_team' => false,
            ],
        );

        $carlosTeam->forceFill(['stripe_id' => 'cus_demo_referrer_carlos'])->save();

        if (! $carlos->teams()->where('team_id', $carlosTeam->id)->exists())
        {
            $carlos->teams()->attach($carlosTeam->id, ['role' => 'admin']);
        }

        $this->seedReferredTeamsAndCommissions($affiliate->fresh(), [
            [
                'name' => 'Demo · Referido Clínica Sur',
                'email' => 'owner.clinicasur@demo.humano.app',
                'stripe_id' => 'cus_demo_ref_paying_clinica',
                'services' => [],
                'invoices' => [
                    ['id' => 'in_demo_aff_clinica_1', 'amount_paid' => 14900, 'days_ago' => 12, 'percent' => $agencyPercent],
                ],
            ],
        ]);

        Subscription::query()
            ->whereIn('stripe_id', ['sub_demo_clinica_assistant', 'sub_demo_clinica_hosting'])
            ->delete();

        $this->seedSubscriptionsGrantedBy('cus_demo_ref_paying_clinica', $subscriptionPercent, [
            [
                'team' => 'Demo · Cliente de Clínica Sur',
                'email' => 'owner.cliente.clinicasur@demo.humano.app',
                'stripe_id' => 'cus_demo_clinica_client',
                'subscription' => 'sub_demo_clinica_cliente_assistant',
                'type' => 'assistant',
            ],
        ]);
    }

    /**
     * @param  list<array{team: string, email: string, stripe_id: string, subscription: string, type: string}>  $clients
     */
    private function seedSubscriptionsGrantedBy(string $agencyStripeId, float $percent, array $clients): void
    {
        foreach ($clients as $client)
        {
            $owner = User::firstOrCreate(
                ['email' => $client['email']],
                [
                    'name' => 'Cliente',
                    'password' => bcrypt('Simplicity!'),
                    'email_verified_at' => now(),
                ],
            );

            $team = Team::query()->firstOrCreate(
                ['name' => $client['team']],
                [
                    'user_id' => $owner->id,
                    'personal_team' => false,
                ],
            );

            $team->forceFill([
                'stripe_id' => $client['stripe_id'],
                'user_id' => $owner->id,
            ])->save();

            if (! $owner->teams()->where('team_id', $team->id)->exists())
            {
                $owner->teams()->attach($team->id, ['role' => 'admin']);
            }

            Subscription::query()->updateOrCreate(
                ['stripe_id' => $client['subscription']],
                [
                    'user_id' => $owner->id,
                    'team_id' => $team->id,
                    'type' => $client['type'],
                    'stripe_status' => 'active',
                    'stripe_price' => 'price_demo_'.$client['type'],
                    'quantity' => 1,
                    'referred_by' => $agencyStripeId,
                    'affiliate_commission_percent' => $percent,
                ],
            );
        }
    }
}
