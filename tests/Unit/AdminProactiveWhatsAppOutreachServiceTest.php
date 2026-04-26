<?php

namespace Tests\Unit;

use App\Models\Module;
use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use App\Services\AdminProactiveWhatsAppOutreachService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProactiveWhatsAppOutreachServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_keyword_and_phone_variants(): void
    {
        $s = app(AdminProactiveWhatsAppOutreachService::class);

        $a = $s->parseKeywordAndPhone('demo +34 722 372 858');
        $this->assertSame('demo', $a['keyword']);
        $this->assertSame('34722372858', $a['phone_digits']);

        $b = $s->parseKeywordAndPhone('reunion: +34722372858');
        $this->assertSame('reunion', $b['keyword']);
        $this->assertSame('34722372858', $b['phone_digits']);

        $c = $s->parseKeywordAndPhone('mi flujo demo +34600111222');
        $this->assertSame('mi flujo demo', $c['keyword']);
        $this->assertSame('34600111222', $c['phone_digits']);

        $d = $s->parseKeywordAndPhone('demo +34 (722) 372-858');
        $this->assertSame('demo', $d['keyword']);
        $this->assertSame('34722372858', $d['phone_digits']);

        $e = $s->parseKeywordAndPhone('cobrar (34) 722 37 28 58');
        $this->assertSame('cobrar', $e['keyword']);
        $this->assertSame('34722372858', $e['phone_digits']);

        $f = $s->parseKeywordAndPhone('reunion: +34-722-372-858');
        $this->assertSame('reunion', $f['keyword']);
        $this->assertSame('34722372858', $f['phone_digits']);

        $this->assertNull($s->parseKeywordAndPhone('solo texto sin telefono'));
        $this->assertNull($s->parseKeywordAndPhone("demo +34\n722"));
    }

    public function test_resolve_routing_key_matches_section_key_and_suffix(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        $mod = Module::query()->create([
            'name' => 'Chat',
            'key' => 'chat',
            'is_core' => false,
            'status' => 1,
        ]);

        Prompt::withoutGlobalScopes()->create([
            'team_id' => $team->id,
            'module_id' => $mod->id,
            'section_key' => 'onboarding-demo',
            'section_label' => 'Onboarding',
            'prompt_instruction' => 'x',
            'is_active' => true,
            'order' => 1,
        ]);

        $s = app(AdminProactiveWhatsAppOutreachService::class);
        $this->assertSame('chat:onboarding-demo', $s->resolveRoutingKeyForKeyword((int) $team->id, 'onboarding-demo'));
        $this->assertSame('chat:onboarding-demo', $s->resolveRoutingKeyForKeyword((int) $team->id, 'chat:onboarding-demo'));
        $this->assertSame('chat:onboarding-demo', $s->resolveRoutingKeyForKeyword((int) $team->id, 'onboarding demo'));
    }
}
