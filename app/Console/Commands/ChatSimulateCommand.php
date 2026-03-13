<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AgentConversationContextService;
use App\Services\ChatAssistantReplyService;
use App\Services\UserResolverService;
use Illuminate\Console\Command;

class ChatSimulateCommand extends Command
{
    protected $signature = 'chat:simulate
                            {--user= : User email for conversation context (default: first admin)}
                            {--phone= : Simulate this client by phone (appears as client in web chat)}
                            {--contact= : Simulate this client by contact ID (appears as client in web chat)}
                            {--team= : Team ID for stub/settings (optional)}';

    protected $description = 'Interactive chat with the assistant in the terminal (simulate client or chat with bot)';

    public function handle(
        UserResolverService $userResolver,
        ChatAssistantReplyService $replyService,
        AgentConversationContextService $contextService,
    ): int {
        $phone = $this->option('phone');
        $contactId = $this->option('contact') ? (int) $this->option('contact') : null;

        if ($phone !== null || $contactId !== null)
        {
            $user = $userResolver->resolveUserForConversation($phone ?: null, $contactId);
            if (! $user)
            {
                $this->error('No se pudo resolver el cliente con --phone o --contact.');

                return self::FAILURE;
            }
            $this->info('Simulando cliente: '.$user->name.' ('.($user->phone ?? $user->email).'). Lo que escribas aquí se verá como mensajes del cliente en la web.');
        } else
        {
            $user = $this->resolveUser();
            if (! $user)
            {
                $this->error('No user found. Use --user=email or ensure an admin user exists.');

                return self::FAILURE;
            }
            $this->info('Chat con el asistente (contexto: '.$user->email.')');
        }

        $teamId = $this->option('team')
            ? (int) $this->option('team')
            : (auth()->check() ? auth()->user()->currentTeam?->id : ($user->currentTeam?->id ?? $user->teams()->first()?->id));

        $this->line('Escribe un mensaje y pulsa Enter. Escribe "salir" o deja vacío para terminar.');
        $this->newLine();

        while (true)
        {
            $message = $this->ask('Tú');
            $message = $message === null ? '' : trim($message);

            if ($message === '' || in_array(mb_strtolower($message), ['salir', 'exit', 'quit'], true))
            {
                $this->info('Hasta luego.');
                break;
            }

            $history = $contextService->getHistoryForPrompt($user->id, AgentConversationContextService::DEFAULT_HISTORY_LIMIT);
            $reply = $replyService->getReply($message, $history, $teamId);

            if (! $reply['success'])
            {
                $this->error('Error: '.($reply['message'] ?? 'Unknown'));

                continue;
            }

            $text = $reply['text'] ?? '';
            $this->line('<fg=green>Asistente:</> '.$text);
            $this->newLine();

            $contextService->persistMessages($user->id, $message, $text, $reply['routed_to'] ?? null);
        }

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $email = $this->option('user');
        if ($email)
        {
            return User::withoutGlobalScopes()->where('email', $email)->first();
        }

        return User::withoutGlobalScopes()->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first()
            ?? User::withoutGlobalScopes()->first();
    }
}
