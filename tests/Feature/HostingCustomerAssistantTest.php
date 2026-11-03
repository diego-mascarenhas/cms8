<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppGateway;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\Enterprise;
use App\Models\Server;
use App\Models\ServiceSync;
use App\Models\Team;
use App\Models\User;
use App\Services\AssistantToolsService;
use App\Services\Hosting\HostingCustomerAssistantService;
use App\Services\Hosting\StripeHostingPlanGateway;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\EnterpriseStatusSeeder;
use Database\Seeders\EnterpriseTypeSeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostingCustomerAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            EnterpriseTypeSeeder::class,
            EnterpriseStatusSeeder::class,
            ContactStatusSeeder::class,
        ]);

        config(['whatsapp.driver' => 'twilio']);
        config(['whatsapp.customer_service_window.enabled' => false]);
    }

    public function test_lists_only_the_contact_cpanel_hosting(): void
    {
        [$user, , $contact] = $this->createHosting();

        $text = app(HostingCustomerAssistantService::class)->listForContact(
            (int) $user->current_team_id,
            (int) $contact->id,
        );

        $this->assertStringContainsString('example.test', $text);
        $this->assertStringContainsString('cpanel', $text);
    }

    public function test_a_consulting_subscription_is_not_treated_as_hosting(): void
    {
        [$user, , $contact] = $this->createHosting();

        ServiceSync::query()->create([
            'team_id' => $user->current_team_id,
            'stripe_id' => 'sub_consulting',
            'customer_id' => 'cus_hosting_demo',
            'status' => 'active',
            'plan_name' => 'Asesoramiento telefónico',
            'data' => ['category' => 'asesoramiento'],
        ]);

        $text = app(HostingCustomerAssistantService::class)->listForContact(
            (int) $user->current_team_id,
            (int) $contact->id,
        );

        $this->assertStringContainsString('example.test', $text);
        $this->assertStringNotContainsString('Asesoramiento', $text);
    }

    public function test_deactivated_subscriptions_are_listed_and_cannot_be_changed(): void
    {
        [$user, $domain, $contact] = $this->createHosting();

        ServiceSync::query()->create([
            'team_id' => $user->current_team_id,
            'stripe_id' => 'sub_canceled_hosting',
            'customer_id' => 'cus_hosting_demo',
            'status' => 'canceled',
            'plan_name' => 'Hosting legacy.test',
            'data' => [
                'domain' => 'legacy.test',
                'category' => 'hosting',
            ],
        ]);
        ServiceSync::query()->create([
            'team_id' => $user->current_team_id,
            'stripe_id' => 'sub_canceled_consulting',
            'customer_id' => 'cus_hosting_demo',
            'status' => 'canceled',
            'plan_name' => 'Asesoramiento viejo',
            'data' => ['category' => 'asesoramiento'],
        ]);

        $text = app(HostingCustomerAssistantService::class)->listForContact(
            (int) $user->current_team_id,
            (int) $contact->id,
        );

        $this->assertStringContainsString('example.test', $text);
        $this->assertStringContainsString('legacy.test · cancelada · hosting', $text);
        $this->assertStringContainsString('Asesoramiento viejo · cancelada · no es hosting', $text);
    }

    public function test_a_deactivated_hosting_subscription_is_listed_and_not_changed(): void
    {
        [$user, $domain, $contact] = $this->createHosting();
        ServiceSync::query()->where('stripe_id', 'sub_example_test')->update(['status' => 'canceled']);

        $text = app(HostingCustomerAssistantService::class)->listForContact(
            (int) $user->current_team_id,
            (int) $contact->id,
        );
        $upgrade = app(HostingCustomerAssistantService::class)->requestUpgrade(
            (int) $user->current_team_id,
            (int) $contact->id,
            $user,
            'example.test',
            'explorer',
        );

        $this->assertStringContainsString('example.test · cancelada · hosting', $text);
        $this->assertStringContainsString('están desactivadas', $upgrade);
        $this->assertSame('essential', $domain->fresh()->plan);
    }

    public function test_email_address_selects_one_domain_when_the_contact_has_several(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/cpanel*' => Http::response([
                'result' => ['status' => 1, 'data' => null],
            ]),
        ]);
        $sent = [];
        $this->bindGateway($sent);

        [$user, $domain, $contact] = $this->createHosting();
        $this->addDomain($domain, 'otro.test', 'otrousuario');
        $user->currentTeam->setSetting('whatsapp_driver', 'twilio', ['group' => 'chat']);

        $text = app(HostingCustomerAssistantService::class)->manageMailbox(
            (int) $user->current_team_id,
            (int) $contact->id,
            $user,
            'create',
            null,
            'hola@otro.test',
        );

        $this->assertStringContainsString('hola@otro.test', $text);
        $this->assertStringNotContainsString('más de un hosting', $text);
        Http::assertSent(fn ($request) => ($request['cpanel_jsonapi_func'] ?? null) === 'add_pop'
            && ($request['domain'] ?? null) === 'otro.test'
            && ($request['cpanel_jsonapi_user'] ?? null) === 'otrousuario');
    }

    public function test_listing_mailboxes_includes_every_domain(): void
    {
        Http::fake(function ($request)
        {
            $user = (string) ($request['cpanel_jsonapi_user'] ?? '');
            $email = $user === 'otrousuario' ? 'hola@otro.test' : 'info@example.test';

            return Http::response([
                'result' => [
                    'status' => 1,
                    'data' => [
                        ['email' => $email],
                    ],
                ],
            ]);
        });

        [$user, $domain, $contact] = $this->createHosting();
        $this->addDomain($domain, 'otro.test', 'otrousuario');

        $text = app(HostingCustomerAssistantService::class)->manageMailbox(
            (int) $user->current_team_id,
            (int) $contact->id,
            $user,
            'list',
            null,
            null,
        );

        $this->assertStringContainsString('example.test', $text);
        $this->assertStringContainsString('info@example.test', $text);
        $this->assertStringContainsString('otro.test', $text);
        $this->assertStringContainsString('hola@otro.test', $text);
    }

    public function test_a_unique_fragment_selects_that_domain(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/passwd*' => Http::response([
                'metadata' => ['result' => 1],
            ]),
        ]);
        $sent = [];
        $this->bindGateway($sent);

        [$user, $domain, $contact] = $this->createHosting();
        $this->addDomain($domain, 'otro.test', 'otrousuario');
        $user->currentTeam->setSetting('whatsapp_driver', 'twilio', ['group' => 'chat']);

        $text = app(HostingCustomerAssistantService::class)->resetCpanelAccess(
            (int) $user->current_team_id,
            (int) $contact->id,
            $user,
            'otro',
        );

        $this->assertStringContainsString('ya se enviaron por WhatsApp', $text);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/json-api/passwd')
            && ($request['user'] ?? null) === 'otrousuario');
    }

    public function test_reset_cpanel_password_notifies_the_same_whatsapp_thread(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/passwd*' => Http::response([
                'metadata' => ['result' => 1],
            ]),
        ]);

        $sent = [];
        $this->bindGateway($sent);

        [$user, , $contact] = $this->createHosting();
        $user->currentTeam->setSetting('whatsapp_driver', 'twilio', ['group' => 'chat']);

        $tools = app(AssistantToolsService::class);
        $tools->setRequestContext($user->id, (int) $user->current_team_id, '34611223344', (int) $contact->id);

        $text = $tools->execute('reset_cpanel_password', []);

        $this->assertStringContainsString('ya se enviaron por WhatsApp', $text);
        $this->assertStringNotContainsString('Contraseña:', $text);
        $this->assertCount(1, $sent);
        $this->assertStringContainsString('Usuario: siteuser', $sent[0]['message']);
        $this->assertStringContainsString('Contraseña:', $sent[0]['message']);
    }

    public function test_create_mailbox_sends_imap_and_smtp_settings(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/cpanel*' => Http::response([
                'result' => ['status' => 1, 'data' => null],
            ]),
        ]);
        $sent = [];
        $this->bindGateway($sent);

        [$user, , $contact] = $this->createHosting();
        $user->currentTeam->setSetting('whatsapp_driver', 'twilio', ['group' => 'chat']);

        $text = app(HostingCustomerAssistantService::class)->manageMailbox(
            (int) $user->current_team_id,
            (int) $contact->id,
            $user,
            'create',
            null,
            'info',
        );

        $this->assertStringContainsString('ya se enviaron por WhatsApp', $text);
        $this->assertStringContainsString('Puerto IMAP: 993', $sent[0]['message']);
        $this->assertStringContainsString('info@example.test', $sent[0]['message']);
        Http::assertSent(fn ($request) => ($request['cpanel_jsonapi_func'] ?? null) === 'add_pop'
            && ($request['email'] ?? null) === 'info');
    }

    public function test_create_ftp_sends_host_and_port(): void
    {
        Http::fake([
            'https://cpanel.test:2087/json-api/cpanel*' => Http::response([
                'result' => ['status' => 1, 'data' => null],
            ]),
        ]);
        $sent = [];
        $this->bindGateway($sent);

        [$user, , $contact] = $this->createHosting();
        $user->currentTeam->setSetting('whatsapp_driver', 'twilio', ['group' => 'chat']);

        $text = app(HostingCustomerAssistantService::class)->manageFtp(
            (int) $user->current_team_id,
            (int) $contact->id,
            $user,
            'create',
            'example.test',
            'web',
        );

        $this->assertStringContainsString('ya se enviaron por WhatsApp', $text);
        $this->assertStringContainsString('Puerto: 21', $sent[0]['message']);
        $this->assertStringContainsString('web@example.test', $sent[0]['message']);
        Http::assertSent(fn ($request) => ($request['cpanel_jsonapi_func'] ?? null) === 'add_ftp'
            && ($request['user'] ?? null) === 'web');
    }

    public function test_does_not_touch_another_contacts_hosting(): void
    {
        [$user, , $contact] = $this->createHosting();
        $other = Contact::factory()->create([
            'team_id' => $user->current_team_id,
            'name' => 'Otro',
            'email' => 'otro@example.test',
            'phone' => '600000001',
            'country' => 724,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $text = app(HostingCustomerAssistantService::class)->resetCpanelAccess(
            (int) $user->current_team_id,
            (int) $other->id,
            $user,
            'example.test',
        );

        $this->assertStringContainsString('no está vinculado', $text);
        $this->assertNotSame((int) $contact->id, (int) $other->id);
    }

    public function test_dns_info_lists_every_domain_and_a_named_one_alone(): void
    {
        [$user, $domain, $contact] = $this->createHosting();
        $domain->forceFill([
            'data' => [
                'nameservers' => ['ns1.example.test', 'ns2.example.test'],
                'web_ip' => '203.0.113.10',
                'mx_records' => [
                    ['priority' => 0, 'target' => 'example.test'],
                ],
            ],
        ])->save();
        $other = $this->addDomain($domain, 'otro.test', 'otrousuario');
        $other->forceFill([
            'data' => [
                'nameservers' => ['ns1.otro.test'],
                'mx_records' => [
                    ['priority' => 1, 'target' => 'smtp.google.com'],
                ],
            ],
        ])->save();

        $all = app(HostingCustomerAssistantService::class)->dnsInfo(
            (int) $user->current_team_id,
            (int) $contact->id,
            null,
        );
        $this->assertStringContainsString('DNS de example.test', $all);
        $this->assertStringContainsString('ns1.example.test', $all);
        $this->assertStringContainsString('203.0.113.10', $all);
        $this->assertStringContainsString('DNS de otro.test', $all);
        $this->assertStringContainsString('smtp.google.com', $all);

        $one = app(HostingCustomerAssistantService::class)->dnsInfo(
            (int) $user->current_team_id,
            (int) $contact->id,
            'otro',
        );
        $this->assertStringContainsString('DNS de otro.test', $one);
        $this->assertStringNotContainsString('DNS de example.test', $one);
        $this->assertStringContainsString('v=spf1 include:spf.revisionalpha.com -all', $one);
    }

    public function test_google_mail_setup_explains_mx_without_changing_dns(): void
    {
        [$user, $domain, $contact] = $this->createHosting();
        $domain->forceFill([
            'data' => [
                'mx_records' => [
                    ['priority' => 0, 'target' => 'example.test'],
                ],
            ],
        ])->save();

        $text = app(HostingCustomerAssistantService::class)->googleMailSetup(
            (int) $user->current_team_id,
            (int) $contact->id,
            'example.test',
        );

        $this->assertStringContainsString('smtp.google.com', $text);
        $this->assertStringContainsString('v=spf1 include:spf.revisionalpha.com -all', $text);
        $this->assertStringContainsString('include:_spf.google.com', $text);
        $this->assertStringContainsString('No cambié los DNS.', $text);
        $this->assertStringContainsString('0 example.test', $text);
        $this->assertSame(
            [['priority' => 0, 'target' => 'example.test']],
            $domain->fresh()->data['mx_records'],
        );
    }

    public function test_product_info_sends_the_public_page_and_the_stack(): void
    {
        $hosting = app(HostingCustomerAssistantService::class)->productLinks('información del hosting');

        $this->assertStringContainsString('https://revisionalpha.com/hosting', $hosting);
        $this->assertStringContainsString('https://revisionalpha.com/hosting/stack', $hosting);
        $this->assertStringNotContainsString('https://revisionalpha.com/cloud/stack', $hosting);

        $agency = app(HostingCustomerAssistantService::class)->productLinks('hosting para agencias');

        $this->assertStringContainsString('https://revisionalpha.com/hosting-agency', $agency);
        $this->assertStringNotContainsString('https://revisionalpha.com/hosting/stack', $agency);

        $unknown = app(HostingCustomerAssistantService::class)->productLinks('dominio barato');

        $this->assertStringContainsString('No hay una página pública', $unknown);
        $this->assertStringContainsString('https://revisionalpha.com/hosting', $unknown);
        $this->assertStringNotContainsString('dominio-barato', $unknown);
    }

    public function test_asks_which_domain_before_changing_several_accounts(): void
    {
        [$user, $domain, $contact] = $this->createHosting();
        $this->addDomain($domain, 'otro.test', 'otrousuario');

        $text = app(HostingCustomerAssistantService::class)->resetCpanelAccess(
            (int) $user->current_team_id,
            (int) $contact->id,
            $user,
            null,
        );

        $this->assertStringContainsString('más de un hosting', $text);
        $this->assertStringContainsString('example.test', $text);
        $this->assertStringContainsString('otro.test', $text);
    }

    public function test_upgrade_without_a_plan_lists_stripe_prices_and_changes_nothing(): void
    {
        [$user, $domain, $contact] = $this->createHosting();
        $this->preparePlanChange($domain);
        $gateway = $this->bindPlanGateway();

        $text = app(HostingCustomerAssistantService::class)->requestUpgrade(
            (int) $user->current_team_id,
            (int) $contact->id,
            $user,
            'example.test',
            'Necesito más espacio',
        );

        $this->assertStringContainsString('No cambié el plan', $text);
        $this->assertStringContainsString('Hosting Explorer', $text);
        $this->assertStringContainsString('7,99 EUR al mes', $text);
        $this->assertSame('revision_beginner', $domain->fresh()->plan);
        $this->assertSame([], $gateway->swaps);
        Http::assertNothingSent();
    }

    public function test_named_plan_changes_the_stripe_price_and_the_server_package(): void
    {
        Http::fake([
            '*changepackage*' => Http::response(['metadata' => ['result' => 1]]),
        ]);

        [$user, $domain, $contact] = $this->createHosting();
        $this->preparePlanChange($domain);
        $gateway = $this->bindPlanGateway();

        $text = app(HostingCustomerAssistantService::class)->requestUpgrade(
            (int) $user->current_team_id,
            (int) $contact->id,
            $user,
            'example.test',
            'explorer',
        );

        $this->assertStringContainsString('Listo', $text);
        $this->assertStringContainsString('revision_explorer', $text);
        $this->assertStringContainsString('7,99 EUR al mes', $text);
        $this->assertSame('revision_explorer', $domain->fresh()->plan);
        $this->assertSame([['sub_example_test', 'si_current', 'price_explorer']], $gateway->swaps);
        $this->assertSame('Hosting Explorer', ServiceSync::query()->where('stripe_id', 'sub_example_test')->value('plan_name'));
    }

    public function test_server_rejection_reverts_the_stripe_price(): void
    {
        Http::fake([
            '*changepackage*' => Http::response(['metadata' => ['result' => 0, 'reason' => 'Permission Denied']]),
            '*modifyacct*' => Http::response(['metadata' => ['result' => 0, 'reason' => 'Permission Denied']]),
        ]);

        [$user, $domain, $contact] = $this->createHosting();
        $this->preparePlanChange($domain);
        $gateway = $this->bindPlanGateway();

        $text = app(HostingCustomerAssistantService::class)->requestUpgrade(
            (int) $user->current_team_id,
            (int) $contact->id,
            $user,
            'example.test',
            'explorer',
        );

        $this->assertStringContainsString('volvió al precio anterior', $text);
        $this->assertSame('revision_beginner', $domain->fresh()->plan);
        $this->assertSame([
            ['sub_example_test', 'si_current', 'price_explorer'],
            ['sub_example_test', 'si_current', 'price_beginner'],
        ], $gateway->swaps);
    }

    public function test_rehearse_command_lists_hosting_and_previews_a_plan_without_changing_it(): void
    {
        [$user, $domain, $contact] = $this->createHosting();
        $this->preparePlanChange($domain);
        $gateway = $this->bindPlanGateway();
        app(\App\Services\InboxProductOnboardingService::class)->start($contact);

        $this->withoutMockingConsoleOutput();

        $this->assertSame(0, $this->artisan('hosting:rehearse', [
            'message' => 'Que hosting tengo?',
            '--team' => $user->current_team_id,
            '--phone' => '34611223344',
        ]));
        $listed = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringContainsString('no lo contesta el embudo', $listed);
        $this->assertStringContainsString('example.test', $listed);

        $this->assertSame(0, $this->artisan('hosting:rehearse', [
            'message' => 'Pasame a explorer',
            '--contact' => $contact->id,
            '--plan' => 'explorer',
        ]));
        $preview = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringContainsString('Ensayo. No cambié Stripe ni el servidor.', $preview);
        $this->assertStringContainsString('revision_explorer', $preview);

        $this->assertSame('revision_beginner', $domain->fresh()->plan);
        $this->assertSame([], $gateway->swaps);
        $this->assertTrue((bool) data_get($contact->fresh()->data, 'idoneo_product_onboarding.active'));
    }

    public function test_start_command_rehearses_hosting_without_changing_the_plan(): void
    {
        [$user, $domain] = $this->createHosting();
        $this->preparePlanChange($domain);
        $gateway = $this->bindPlanGateway();
        $this->withoutMockingConsoleOutput();

        $this->assertSame(0, $this->artisan('start', [
            '--hosting-rehearse' => true,
            '--message' => 'Que hosting tengo?',
            '--team' => $user->current_team_id,
            '--phone' => '34611223344',
            '--plan' => 'explorer',
        ]));

        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringContainsString('example.test', $output);
        $this->assertStringContainsString('Ensayo. No cambié Stripe ni el servidor.', $output);
        $this->assertStringContainsString('revision_explorer', $output);
        $this->assertSame('revision_beginner', $domain->fresh()->plan);
        $this->assertSame([], $gateway->swaps);
    }

    public function test_rehearse_chat_lists_hosting_and_chau_leaves_the_flow(): void
    {
        [$user, $domain, $contact] = $this->createHosting();
        $plan = $domain->plan;
        app(\App\Services\InboxProductOnboardingService::class)->start($contact);
        $contact->pinInboundChatAssistantPrompt('hosting:assistant_hosting');

        $this->artisan('hosting:rehearse', [
            '--chat' => true,
            '--contact' => $contact->id,
            '--team' => $user->current_team_id,
        ])->expectsQuestion('Cliente', 'Que hosting tengo?')
            ->expectsQuestion('Cliente', 'chau')
            ->expectsOutputToContain('example.test')
            ->expectsOutputToContain('lo dejamos acá')
            ->assertSuccessful();

        $fresh = $contact->fresh();
        $this->assertFalse((bool) data_get($fresh->data, 'idoneo_product_onboarding.active'));
        $this->assertNull($fresh->inboundChatAssistantPromptKey());
        $this->assertSame($plan, $domain->fresh()->plan);
    }

    public function test_rehearse_chat_greets_without_listing_subscriptions(): void
    {
        [$user, $domain, $contact] = $this->createHosting();

        $this->artisan('hosting:rehearse', [
            '--chat' => true,
            '--contact' => $contact->id,
            '--team' => $user->current_team_id,
        ])->expectsQuestion('Cliente', 'Hola')
            ->expectsQuestion('Cliente', 'chau')
            ->expectsOutputToContain('Qué necesitás')
            ->assertSuccessful();

        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringNotContainsString('example.test', $output);
        $this->assertStringNotContainsString('Suscripciones desactivadas', $output);
        $this->assertSame('essential', $domain->fresh()->plan);
    }

    public function test_disk_space_of_a_cancelled_subscription_does_not_list_the_rest(): void
    {
        [$user, $domain, $contact] = $this->createHosting();
        ServiceSync::query()->create([
            'team_id' => $user->current_team_id,
            'stripe_id' => 'sub_masbcr',
            'customer_id' => 'cus_hosting_demo',
            'status' => 'canceled',
            'plan_name' => 'masbcr.com.ar',
            'data' => ['category' => 'asesoramiento'],
        ]);

        $text = app(HostingCustomerAssistantService::class)->diskSpace(
            (int) $user->current_team_id,
            (int) $contact->id,
            'masbcr.com.ar',
        );

        $this->assertStringContainsString('masbcr.com.ar', $text);
        $this->assertStringContainsString('cancelada', $text);
        $this->assertStringContainsString('no es hosting', $text);
        $this->assertStringNotContainsString('example.test', $text);
        $this->assertStringNotContainsString('Suscripciones desactivadas', $text);
        $this->assertSame('essential', $domain->fresh()->plan);
    }

    public function test_disk_space_reads_the_quota_of_the_named_domain(): void
    {
        [$user, $domain, $contact] = $this->createHosting();
        $domain->forceFill([
            'data' => ['disk_used' => 1500, 'disk_limit' => 10000],
        ])->save();

        $text = app(HostingCustomerAssistantService::class)->diskSpace(
            (int) $user->current_team_id,
            (int) $contact->id,
            'example.test',
        );

        $this->assertStringContainsString('example.test', $text);
        $this->assertStringContainsString('1.500 MB', $text);
        $this->assertStringContainsString('10.000 MB', $text);
        $this->assertStringNotContainsString('Suscripciones desactivadas', $text);
    }

    public function test_rehearse_command_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('hosting:rehearse', [
            'message' => 'Que hosting tengo?',
            '--contact' => 1,
            '--apply' => true,
        ])->assertFailed()
            ->expectsOutputToContain('no corre en producción');
    }

    private function preparePlanChange(Domain $domain): void
    {
        $domain->forceFill([
            'plan' => 'revision_beginner',
            'data' => ['available_plans' => ['revision_beginner', 'revision_explorer']],
        ])->save();
    }

    private function bindPlanGateway(): object
    {
        $gateway = new class extends StripeHostingPlanGateway
        {
            /** @var list<array{0: string, 1: string, 2: string}> */
            public array $swaps = [];

            public function catalog(Team $team): array
            {
                return [
                    [
                        'price_id' => 'price_beginner',
                        'product_name' => 'Hosting Beginner',
                        'plan' => 'hosting-beginner',
                        'currency' => 'eur',
                        'interval' => 'month',
                        'interval_count' => 1,
                        'unit_amount' => 499,
                        'whm_package' => null,
                    ],
                    [
                        'price_id' => 'price_explorer',
                        'product_name' => 'Hosting Explorer',
                        'plan' => 'hosting-explorer',
                        'currency' => 'eur',
                        'interval' => 'month',
                        'interval_count' => 1,
                        'unit_amount' => 799,
                        'whm_package' => null,
                    ],
                ];
            }

            public function currentItem(Team $team, string $subscriptionId): ?array
            {
                return [
                    'item_id' => 'si_current',
                    'price_id' => 'price_beginner',
                    'currency' => 'eur',
                    'interval' => 'month',
                    'interval_count' => 1,
                ];
            }

            public function swap(Team $team, string $subscriptionId, string $itemId, string $priceId): void
            {
                $this->swaps[] = [$subscriptionId, $itemId, $priceId];
            }
        };

        $this->app->instance(StripeHostingPlanGateway::class, $gateway);

        return $gateway;
    }

    /**
     * @param  list<array<string, mixed>>  $sent
     */
    private function bindGateway(array &$sent): void
    {
        $this->app->instance(WhatsAppGateway::class, new class($sent) implements WhatsAppGateway
        {
            public function __construct(private array &$sent) {}

            public function sendMessage(string $to, string $message, ?array $metadata = null, ?int $userId = null): mixed
            {
                $this->sent[] = compact('to', 'message');

                return 'ok';
            }

            public function sendMedia(string $to, string $mediaPath, ?string $caption = null): bool
            {
                return true;
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function getQrUrl(): ?string
            {
                return null;
            }

            public function getConnectionStatus(): ?array
            {
                return ['status' => 'connected'];
            }
        });
    }

    private function addDomain(Domain $domain, string $name, string $username): Domain
    {
        $created = Domain::factory()->create([
            'domain' => $name,
            'server_id' => $domain->server_id,
            'service_id' => null,
            'username' => $username,
            'plan' => 'essential',
            'suspended' => false,
        ]);

        $this->addHostingSubscription($created, $domain);

        return $created;
    }

    private function addHostingSubscription(Domain $domain, ?Domain $sibling = null): void
    {
        $server = $domain->server ?? Server::withoutGlobalScopes()->find($domain->server_id);
        $customerId = 'cus_hosting_demo';
        if ($sibling)
        {
            $match = ServiceSync::query()
                ->where('team_id', $server->team_id)
                ->get()
                ->first(fn (ServiceSync $sync) => $sync->hostingDomainCandidate() === strtolower((string) $sibling->domain));
            if ($match)
            {
                $customerId = (string) $match->customer_id;
            }
        }

        ServiceSync::query()->create([
            'team_id' => $server->team_id,
            'stripe_id' => 'sub_'.str_replace('.', '_', (string) $domain->domain),
            'customer_id' => $customerId,
            'status' => 'active',
            'plan_name' => 'Hosting '.$domain->domain,
            'data' => [
                'domain' => strtolower((string) $domain->domain),
                'category' => 'hosting',
            ],
        ]);
    }

    /**
     * @return array{0: User, 1: Domain, 2: Contact}
     */
    private function createHosting(): array
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->setRelation('currentTeam', $team);

        $server = Server::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'WHM',
            'server_url' => 'cpanel.test',
            'username' => 'democpanel',
            'control_panel' => 'cpanel',
            'encrypted_token' => 'secret-password',
            'success' => true,
            'status_id' => 1,
            'data' => ['auth_mode' => 'cpanel_user'],
        ]);

        $enterprise = Enterprise::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'name' => 'Cliente Demo',
            'code' => 'cus_hosting_demo',
            'type_id' => 1,
            'status_id' => 1,
        ]);

        $domain = Domain::factory()->create([
            'domain' => 'example.test',
            'server_id' => $server->id,
            'service_id' => null,
            'username' => 'siteuser',
            'plan' => 'essential',
            'suspended' => false,
        ]);
        $this->addHostingSubscription($domain);

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Cliente',
            'surname' => 'WhatsApp',
            'email' => 'cliente@example.test',
            'phone' => '611223344',
            'country' => 724,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);
        $enterprise->contacts()->attach($contact->id);

        return [$user->fresh(), $domain->fresh(), $contact];
    }
}
