<?php

namespace Tests\Feature;

use Tests\TestCase;

class HelpDocumentationCompletenessTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function helpPageRoutes(): array
    {
        return [
            'help.index',
            'help.onboarding',
            'help.usage',
            'help.chat-assistant',
            'help.contacts',
            'help.api',
            'help.api.authentication',
            'help.api.contacts',
            'help.api.posts',
            'help.api.enterprises',
            'help.api.payments',
            'help.api.products',
            'help.api.orders',
            'help.api.tasks',
            'help.api.prompts',
            'help.api.whatsapp',
            'help.environment-variables',
            'help.environment-variables.google-analytics',
            'help.environment-variables.google-people-calendar',
            'help.team-social-networks',
            'help.paid-ads-setup',
            'help.woocommerce-configuration',
            'help.wordpress-mcp-cursor',
            'help.plugins',
            'help.postgresql-search-unaccent',
            'help.email-spf-dns',
            'help.stripe-webhook',
        ];
    }

    public function test_all_help_pages_are_public(): void
    {
        foreach ($this->helpPageRoutes() as $routeName)
        {
            $this->get(route($routeName))->assertOk();
        }
    }

    public function test_help_index_links_orphan_pages(): void
    {
        $this->get(route('help.index'))
            ->assertOk()
            ->assertSee(route('help.plugins', [], false), false)
            ->assertSee(route('help.paid-ads-setup', [], false), false)
            ->assertSee(route('manual.index', [], false), false)
            ->assertSee(route('help.chat-assistant').'#assistant-embed', false);
    }

    public function test_paid_ads_uses_help_layout_sidebar(): void
    {
        $this->get(route('help.paid-ads-setup'))
            ->assertOk()
            ->assertSee(route('help.environment-variables', [], false), false)
            ->assertSee(route('help.paid-ads-setup', [], false), false);
    }

    public function test_environment_variables_hub_links_existing_docs(): void
    {
        $this->get(route('help.environment-variables'))
            ->assertOk()
            ->assertSee(route('help.stripe-webhook', [], false), false)
            ->assertSee(route('help.email-spf-dns', [], false), false)
            ->assertSee(route('help.paid-ads-setup', [], false), false)
            ->assertDontSee('Claves API y webhook para pagos con Stripe.', false);
    }

    public function test_usage_points_to_manual(): void
    {
        $this->get(route('help.usage'))
            ->assertOk()
            ->assertSee(route('manual.index', [], false), false)
            ->assertSee('Client', false);
    }
}
