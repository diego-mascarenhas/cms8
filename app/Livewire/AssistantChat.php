<?php

namespace App\Livewire;

use App\Services\AssistantChatService;
use Livewire\Component;

class AssistantChat extends Component
{
    /** @var array<int, array{role: string, content: string, routed_to: string|null}> */
    public array $messages = [];

    public string $input = '';

    public bool $loading = false;

    public function sendMessage(AssistantChatService $assistant): void
    {
        $text = trim($this->input);
        if ($text === '' || $this->loading)
        {
            return;
        }

        $this->messages[] = [
            'role' => 'user',
            'content' => $text,
            'routed_to' => null,
        ];
        $this->input = '';
        $this->loading = true;

        $teamId = auth()->check() && auth()->user()->currentTeam
            ? auth()->user()->currentTeam->id
            : null;

        $result = $assistant->run($text, $teamId);

        $this->messages[] = [
            'role' => 'assistant',
            'content' => $result['response'],
            'routed_to' => $result['routed_to'],
        ];
        $this->loading = false;
        $this->dispatch('scroll-to-bottom');
    }

    public function clearChat(): void
    {
        $this->messages = [];
    }

    public function render()
    {
        return view('livewire.assistant-chat');
    }
}
