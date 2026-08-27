<?php

namespace Tests\Unit;

use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use App\Services\InboxProductOnboardingService;
use Database\Seeders\ContactStatusSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LanguageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxProductOnboardingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_yes_to_pains_explains_assistant_then_only_register_link(): void
    {
        $contact = $this->makeContact();
        $team = Team::withoutGlobalScopes()->find($contact->team_id);
        $service = app(InboxProductOnboardingService::class);

        $started = $service->start($contact);

        $this->assertTrue($started['ok']);
        $this->assertCount(1, $started['messages']);
        $this->assertStringContainsString('Soy IDONEO, un artesano del software', $started['messages'][0]);
        $this->assertStringContainsString('¿Les resuena centralizar', $started['messages'][0]);
        $this->assertLessThan(180, mb_strlen($started['messages'][0]));
        $this->assertStringNotContainsString('idoneo.dev/register', $started['messages'][0]);

        $more = $service->tryHandleInbound($team, '34600111001', 'Sí, nos pasa lo del embudo y la intención');

        $this->assertNotNull($more);
        $this->assertStringContainsString('Assistant', $more['message']);
        $this->assertStringContainsString('acceso de prueba', $more['message']);
        $this->assertStringNotContainsString('inbox', mb_strtolower($more['message']));
        $this->assertStringNotContainsString('48', $more['message']);
        $this->assertStringNotContainsString('demo', mb_strtolower($more['message']));
        $this->assertStringNotContainsString('idoneo.dev/register', $more['message']);
        $this->assertStringNotContainsString('stripe', mb_strtolower($more['message']));
        $this->assertLessThan(180, mb_strlen($more['message']));

        $signup = $service->tryHandleInbound($team, '34600111001', 'Sí, pasame el acceso');

        $this->assertNotNull($signup);
        $this->assertStringContainsString('acceso de prueba', $signup['message']);
        $this->assertStringContainsString('https://assistant.idoneo.dev/register?ref=cus_recomendar_flow', $signup['message']);
        $this->assertStringNotContainsString('shop.idoneo.dev/register', $signup['message']);
        $this->assertStringNotContainsString('buy.stripe', $signup['message']);
        $this->assertStringNotContainsString('48', $signup['message']);
        $this->assertStringNotContainsString('QR', $signup['message']);
    }

    public function test_por_favor_after_assistant_more_sends_register_link(): void
    {
        $contact = $this->makeContact();
        $team = Team::withoutGlobalScopes()->find($contact->team_id);
        $service = app(InboxProductOnboardingService::class);

        $service->start($contact);
        $service->tryHandleInbound($team, '34600111001', 'Sí');
        $signup = $service->tryHandleInbound($team, '34600111001', 'Por favor');

        $this->assertNotNull($signup);
        $this->assertStringContainsString('https://assistant.idoneo.dev/register?ref=cus_recomendar_flow', $signup['message']);
        $this->assertStringNotContainsString('shop.idoneo.dev/register', $signup['message']);
    }

    public function test_shop_register_link_includes_the_customer_ref(): void
    {
        $contact = $this->makeContact();
        $team = Team::withoutGlobalScopes()->find($contact->team_id);
        $service = app(InboxProductOnboardingService::class);

        $service->start($contact);
        $shop = $service->tryHandleInbound($team, '34600111001', 'Shop, el mostrador');
        $this->assertNotNull($shop);
        $this->assertStringContainsString('acceso de prueba', $shop['message']);
        $this->assertStringNotContainsString('48', $shop['message']);
        $this->assertStringNotContainsString('demo', mb_strtolower($shop['message']));
        $signup = $service->tryHandleInbound($team, '34600111001', 'Sí, pasame el acceso');

        $this->assertNotNull($signup);
        $this->assertStringContainsString('acceso de prueba', $signup['message']);
        $this->assertStringContainsString('https://shop.idoneo.dev/register?ref=cus_recomendar_flow', $signup['message']);
        $this->assertStringNotContainsString('assistant.idoneo.dev/register', $signup['message']);
        $this->assertStringNotContainsString('48', $signup['message']);
        $this->assertStringNotContainsString('demo', mb_strtolower($signup['message']));
    }

    public function test_no_to_pains_offers_consulting_network_not_shop(): void
    {
        $contact = $this->makeContact();
        $team = Team::withoutGlobalScopes()->find($contact->team_id);
        $service = app(InboxProductOnboardingService::class);

        $service->start($contact);
        $reply = $service->tryHandleInbound($team, '34600111001', 'No, eso no nos pasa');

        $this->assertNotNull($reply);
        $this->assertStringContainsString('el negocio', $reply['message']);
        $this->assertStringContainsString('la marca', $reply['message']);
        $this->assertStringNotContainsString('Consultoría de negocio', $reply['message']);
        $this->assertStringNotContainsString('Señalética', $reply['message']);
        $this->assertStringNotContainsString('humano labs', mb_strtolower($reply['message']));
        $this->assertStringNotContainsString('mostrador', $reply['message']);
        $this->assertStringNotContainsString('idoneo.dev/register', $reply['message']);
        $this->assertStringNotContainsString('stripe', mb_strtolower($reply['message']));
        $this->assertLessThan(180, mb_strlen($reply['message']));
    }

    public function test_after_assistant_signup_offers_consulting_then_hands_off(): void
    {
        $contact = $this->makeContact();
        $team = Team::withoutGlobalScopes()->find($contact->team_id);
        config(['humano_pricing.plan_access_team_ids' => [(int) $team->id]]);
        $service = app(InboxProductOnboardingService::class);

        $service->start($contact);
        $service->tryHandleInbound($team, '34600111001', 'Sí');
        $service->tryHandleInbound($team, '34600111001', 'Por favor');
        $mentor = $service->tryHandleInbound($team, '34600111001', 'Listo');

        $this->assertNotNull($mentor);
        $this->assertStringContainsString('el negocio', $mentor['message']);
        $this->assertStringContainsString('la marca', $mentor['message']);
        $this->assertStringNotContainsString('humano labs', mb_strtolower($mentor['message']));
        $this->assertStringNotContainsString('mostrador', $mentor['message']);
        $this->assertStringNotContainsString('stripe', mb_strtolower($mentor['message']));
        $this->assertStringNotContainsString('buy.stripe', $mentor['message']);
        $this->assertLessThan(180, mb_strlen($mentor['message']));

        $handoff = $service->tryHandleInbound($team, '34600111001', 'Sí, nos cuesta crecer');

        $this->assertNotNull($handoff);
        $this->assertStringContainsString('desafío', mb_strtolower($handoff['message']));
        $this->assertStringNotContainsString('stripe', mb_strtolower($handoff['message']));

        $contact->refresh();
        $this->assertSame(InboxProductOnboardingService::STRATEGY_PROMPT_KEY, $contact->inboundChatAssistantPromptKey());
        $this->assertTrue($contact->allowsInboundChatAssistant());
        $flow = $contact->data->idoneo_product_onboarding ?? null;
        $this->assertFalse((bool) (is_object($flow) ? ($flow->active ?? true) : true));
    }

    public function test_business_problem_at_choose_hands_off_to_landing_prompt(): void
    {
        $contact = $this->makeContact();
        $team = Team::withoutGlobalScopes()->find($contact->team_id);
        config(['humano_pricing.plan_access_team_ids' => [(int) $team->id]]);
        $service = app(InboxProductOnboardingService::class);

        $service->start($contact);
        $reply = $service->tryHandleInbound(
            $team,
            '34600111001',
            'El problema es de estrategia: hay desorden, todo depende de una persona y no logramos crecer de forma ordenada.',
        );

        $this->assertNotNull($reply);
        $this->assertStringContainsString('desafío', mb_strtolower($reply['message']));
        $this->assertStringNotContainsString('assistant.idoneo.dev/register', $reply['message']);
        $this->assertStringNotContainsString('stripe', mb_strtolower($reply['message']));

        $contact->refresh();
        $this->assertSame(InboxProductOnboardingService::STRATEGY_PROMPT_KEY, $contact->inboundChatAssistantPromptKey());
        $flow = $contact->data->idoneo_product_onboarding ?? null;
        $this->assertFalse((bool) (is_object($flow) ? ($flow->active ?? true) : true));
    }

    public function test_cambio_de_imagen_after_mentor_offer_hands_off_to_mix_vasallo(): void
    {
        $contact = $this->makeContact();
        $team = Team::withoutGlobalScopes()->find($contact->team_id);
        config(['humano_pricing.plan_access_team_ids' => [(int) $team->id]]);
        $service = app(InboxProductOnboardingService::class);

        $service->start($contact);
        $service->tryHandleInbound($team, '34600111001', 'Sí');
        $service->tryHandleInbound($team, '34600111001', 'Por favor');
        $service->tryHandleInbound($team, '34600111001', 'Listo');
        $reply = $service->tryHandleInbound($team, '34600111001', 'Queremos un cambio de imagen');

        $this->assertNotNull($reply);
        $this->assertStringContainsString('Mix Vasallo', $reply['message']);
        $this->assertStringContainsString('https://mixvasallo.com', $reply['message']);
        $this->assertStringNotContainsString('mostrador', $reply['message']);
        $this->assertStringNotContainsString('stripe', mb_strtolower($reply['message']));

        $contact->refresh();
        $this->assertSame(InboxProductOnboardingService::STRATEGY_PROMPT_KEY, $contact->inboundChatAssistantPromptKey());
        $flow = $contact->data->idoneo_product_onboarding ?? null;
        $this->assertSame('design', is_object($flow) ? ($flow->studio ?? null) : null);
    }

    public function test_design_problem_hands_off_to_mix_vasallo_without_payment_links(): void
    {
        $contact = $this->makeContact();
        $team = Team::withoutGlobalScopes()->find($contact->team_id);
        config(['humano_pricing.plan_access_team_ids' => [(int) $team->id]]);
        $service = app(InboxProductOnboardingService::class);

        $service->start($contact);
        $reply = $service->tryHandleInbound($team, '34600111001', 'Necesitamos diseño de marca y un logo nuevo');

        $this->assertNotNull($reply);
        $this->assertStringContainsString('Mix Vasallo', $reply['message']);
        $this->assertStringContainsString('https://mixvasallo.com', $reply['message']);
        $this->assertStringNotContainsString('assistant.idoneo.dev/register', $reply['message']);
        $this->assertStringNotContainsString('stripe', mb_strtolower($reply['message']));

        $contact->refresh();
        $this->assertSame(InboxProductOnboardingService::STRATEGY_PROMPT_KEY, $contact->inboundChatAssistantPromptKey());
        $flow = $contact->data->idoneo_product_onboarding ?? null;
        $this->assertSame('design', is_object($flow) ? ($flow->studio ?? null) : null);
    }

    public function test_hosting_problem_hands_off_to_revision_alpha(): void
    {
        $contact = $this->makeContact();
        $team = Team::withoutGlobalScopes()->find($contact->team_id);
        config(['humano_pricing.plan_access_team_ids' => [(int) $team->id]]);
        $service = app(InboxProductOnboardingService::class);

        $service->start($contact);
        $reply = $service->tryHandleInbound($team, '34600111001', 'Se nos cae el hosting y el cpanel');

        $this->assertNotNull($reply);
        $this->assertStringContainsString('REVISION ALPHA', $reply['message']);
        $this->assertStringContainsString('https://revisionalpha.com', $reply['message']);
        $this->assertStringNotContainsString('assistant.idoneo.dev/register', $reply['message']);
    }

    private function makeContact(): Contact
    {
        $this->seed([
            CountrySeeder::class,
            LanguageSeeder::class,
            ContactStatusSeeder::class,
            \Database\Seeders\ModuleSeeder::class,
        ]);
        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'stripe_id' => 'cus_recomendar_flow',
        ]);

        return Contact::factory()->create([
            'team_id' => $team->id,
            'user_id' => null,
            'name' => 'Diego Pérez',
            'phone' => '34600111001',
            'email' => 'diego.funnel@example.com',
            'creator_id' => $owner->id,
            'responsible_id' => $owner->id,
            'status_id' => 1,
        ]);
    }
}
