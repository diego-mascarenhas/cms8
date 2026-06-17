<?php

namespace Database\Seeders;

use App\Models\AffiliateInvitation;
use App\Models\BillingAffiliateCommission;
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

        $this->seedReferredTeamsAndCommissions($team);

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

    private function seedReferredTeamsAndCommissions(Team $referrerTeam): void
    {
        $referrerStripeId = trim((string) $referrerTeam->stripe_id);

        if ($referrerStripeId === '')
        {
            return;
        }

        $percent = AffiliateCommission::percent();

        $referredTeams = [
            [
                'name' => 'Demo · Referido Estudio Norte',
                'email' => 'owner.estudionorte@demo.humano.app',
                'stripe_id' => 'cus_demo_ref_paying_norte',
                'invoices' => [
                    ['id' => 'in_demo_aff_norte_1', 'amount_paid' => 9900, 'days_ago' => 45],
                    ['id' => 'in_demo_aff_norte_2', 'amount_paid' => 9900, 'days_ago' => 15],
                ],
            ],
            [
                'name' => 'Demo · Referido Agencia Beta',
                'email' => 'owner.agenciabeta@demo.humano.app',
                'stripe_id' => 'cus_demo_ref_paying_beta',
                'invoices' => [
                    ['id' => 'in_demo_aff_beta_1', 'amount_paid' => 29900, 'days_ago' => 30],
                ],
            ],
        ];

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

            foreach ($referred['invoices'] as $invoice)
            {
                $amountPaid = (int) $invoice['amount_paid'];
                $commissionCents = (int) round($amountPaid * ($percent / 100));

                BillingAffiliateCommission::query()->updateOrCreate(
                    ['stripe_invoice_id' => $invoice['id']],
                    [
                        'paying_team_id' => $payingTeam->id,
                        'referrer_team_id' => $referrerTeam->id,
                        'paying_enterprise_id' => null,
                        'referrer_enterprise_id' => null,
                        'amount_paid_cents' => $amountPaid,
                        'currency' => 'EUR',
                        'commission_percent' => $percent,
                        'commission_amount_cents' => $commissionCents,
                        'created_at' => now()->subDays((int) $invoice['days_ago']),
                        'updated_at' => now()->subDays((int) $invoice['days_ago']),
                    ],
                );

                $commissionCount++;
            }
        }

        $this->command?->info("   · {$commissionCount} affiliate commissions ({$percent}% on referred teams)");
    }
}
