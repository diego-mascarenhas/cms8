<?php

namespace Tests\Unit;

use App\Services\AssistantPromptCatalog;
use PHPUnit\Framework\TestCase;

class AssistantPromptAudienceTest extends TestCase
{
    public function test_customer_flows_are_for_whatsapp_and_the_site(): void
    {
        $catalog = new AssistantPromptCatalog;

        $this->assertSame('customer', $catalog->audienceFor('calendar:assistant_citas', 'assistant_citas'));
        $this->assertSame('customer', $catalog->audienceFor('products:assistant_catalogo', 'assistant_catalogo'));
        $this->assertSame('customer', $catalog->audienceFor('products:assistant_embudo', 'assistant_embudo'));
        $this->assertSame('customer', $catalog->audienceFor('invoices:collections', 'collections'));
        $this->assertSame('customer', $catalog->audienceFor('chat:bienvenida', 'bienvenida'));
        $this->assertSame('customer', $catalog->audienceFor('chat:citas_y_ventas', 'citas_y_ventas'));
        $this->assertSame('customer', $catalog->audienceFor('chat:assistant_presupuesto', 'assistant_presupuesto'));
        $this->assertSame('customer', $catalog->audienceFor('contacts:general', 'general'));
        $this->assertSame('Para el cliente', $catalog->audienceLabel('customer'));
    }

    public function test_team_flows_are_for_admins_and_collaborators(): void
    {
        $catalog = new AssistantPromptCatalog;

        $this->assertSame('team', $catalog->audienceFor('tasks:assistant_tareas', 'assistant_tareas'));
        $this->assertSame('team', $catalog->audienceFor('contacts:assistant_contactos', 'assistant_contactos'));
        $this->assertSame('team', $catalog->audienceFor('communications:assistant_campanas', 'assistant_campanas'));
        $this->assertSame('team', $catalog->audienceFor('list60:primer_contacto', 'primer_contacto'));
        $this->assertSame('team', $catalog->audienceFor('contacts:notes', 'notes'));
        $this->assertSame('team', $catalog->audienceFor('communications:message', 'message'));
        $this->assertSame('Para el equipo', $catalog->audienceLabel('team'));
    }

    public function test_audience_rank_follows_customer_then_team_hierarchy(): void
    {
        $catalog = new AssistantPromptCatalog;

        $this->assertLessThan(
            $catalog->audienceRank('calendar:assistant_citas', 'assistant_citas'),
            $catalog->audienceRank('chat:bienvenida', 'bienvenida'),
        );
        $this->assertLessThan(
            $catalog->audienceRank('products:assistant_catalogo', 'assistant_catalogo'),
            $catalog->audienceRank('calendar:assistant_citas', 'assistant_citas'),
        );
        $this->assertLessThan(
            $catalog->audienceRank('chat:assistant_presupuesto', 'assistant_presupuesto'),
            $catalog->audienceRank('products:assistant_catalogo', 'assistant_catalogo'),
        );
        $this->assertLessThan(
            $catalog->audienceRank('products:assistant_embudo', 'assistant_embudo'),
            $catalog->audienceRank('chat:assistant_presupuesto', 'assistant_presupuesto'),
        );
        $this->assertLessThan(
            $catalog->audienceRank('invoices:collections', 'collections'),
            $catalog->audienceRank('products:assistant_embudo', 'assistant_embudo'),
        );
        $this->assertLessThan(
            $catalog->audienceRank('contacts:general', 'general'),
            $catalog->audienceRank('invoices:collections', 'collections'),
        );
        $this->assertLessThan(
            $catalog->audienceRank('tasks:assistant_tareas', 'assistant_tareas'),
            $catalog->audienceRank('contacts:assistant_contactos', 'assistant_contactos'),
        );
        $this->assertLessThan(
            $catalog->audienceRank('communications:assistant_campanas', 'assistant_campanas'),
            $catalog->audienceRank('tasks:assistant_tareas', 'assistant_tareas'),
        );
        $this->assertLessThan(
            $catalog->audienceRank('list60:primer_contacto', 'primer_contacto'),
            $catalog->audienceRank('list60:alta', 'alta'),
        );
    }
}
