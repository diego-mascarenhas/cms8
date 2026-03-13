<?php

namespace App\Services;

/**
 * Returns the assistant reply for chat/WhatsApp. Uses Claude by default; can use a stub for testing.
 */
class ChatAssistantReplyService
{
    public function __construct(
        protected ClaudeService $claudeService,
    ) {}

    /**
     * Get assistant reply for the given message and history.
     * When stub mode is enabled (config or team), returns a canned response for testing.
     *
     * @param  array<int, array{direction: string, body: string}>  $history
     * @return array{success: bool, text?: string, message?: string, routed_to?: string|null}
     */
    public function getReply(string $message, array $history = [], ?int $teamId = null): array
    {
        if ($this->useStub($teamId))
        {
            return $this->getStubReply($message);
        }

        return $this->claudeService->chat($message, $history, null, $teamId);
    }

    /**
     * Whether to use stub response (for testing without calling Claude).
     */
    public function useStub(?int $teamId = null): bool
    {
        if (config('app.assistant_chat_stub', false))
        {
            return true;
        }

        if ($teamId !== null)
        {
            $team = \App\Models\Team::withoutGlobalScopes()->find($teamId);

            return $team && (bool) $team->getSetting('assistant_chat_stub', false);
        }

        if (auth()->check() && auth()->user()->currentTeam)
        {
            return (bool) auth()->user()->currentTeam->getSetting('assistant_chat_stub', false);
        }

        return false;
    }

    /**
     * Stub response for testing the chat flow without Claude.
     */
    protected function getStubReply(string $message): array
    {
        return [
            'success' => true,
            'text' => '[Modo prueba] Recibí: «'.mb_substr($message, 0, 100).(mb_strlen($message) > 100 ? '…' : '').'». En producción aquí respondería el asistente.',
            'routed_to' => null,
        ];
    }
}
