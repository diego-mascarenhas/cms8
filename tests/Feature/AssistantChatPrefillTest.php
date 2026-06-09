<?php

namespace Tests\Feature;

use App\Livewire\AssistantChat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssistantChatPrefillTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_assistant_prefill_event_sets_input_from_message_param(): void
    {
        Livewire::test(AssistantChat::class)
            ->dispatch('finance-assistant-prefill', message: '¿Qué necesito para duplicar el beneficio en 2025?')
            ->assertSet('input', '¿Qué necesito para duplicar el beneficio en 2025?');
    }
}
