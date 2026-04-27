<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ChatAssistantReplyService;
use Illuminate\Console\Command;

/**
 * Terminal-only guided tour of Humano via the same AI stack as chat, without persisting to agent_conversations.
 */
class HumanoInteractiveGuideCommand extends Command
{
    protected $signature = 'humano:interactive-guide
                            {--user= : User email for team context (default: first admin)}
                            {--team= : Team ID for settings context (optional)}
                            {--with-tools : Enable assistant tools (demo with real actions; use with care)}';

    protected $description = 'Interactive terminal tour: chat with a guide bot about Humano (CRM, chat, assistant, billing, registration paths)';

    public function handle(ChatAssistantReplyService $replyService): int
    {
        $user = $this->resolveUser();
        if (! $user)
        {
            $this->error('No user found. Use --user=email or ensure an admin user exists.');

            return self::FAILURE;
        }

        $teamId = $this->option('team')
            ? (int) $this->option('team')
            : ($user->currentTeam?->id ?? $user->teams()->first()?->id);

        $withTools = (bool) $this->option('with-tools');
        $appendix = (string) config('humano_interactive_guide.instructions', '');
        if (trim($appendix) === '')
        {
            $this->error('Missing config humano_interactive_guide.instructions.');

            return self::FAILURE;
        }

        $this->info('Guía interactiva de Humano (terminal). No se guarda esta conversación en el historial del asistente web.');
        if ($withTools)
        {
            $this->warn('Herramientas activadas: el modelo puede ejecutar acciones reales en tu equipo.');
        }
        $this->line('Escribí tu mensaje y pulsá Enter. Comandos de salida: salir, exit, quit, o vacío.');
        $this->newLine();

        /** @var array<int, array{direction: string, body: string}> $history */
        $history = [];

        $seedMessage = 'Hola, quiero que me expliques qué es Humano y qué puedo hacer con la aplicación. Empezá como guía y preguntame en qué necesito ayuda.';
        $first = $replyService->getReply(
            $seedMessage,
            $history,
            $teamId,
            $withTools,
            $user->id,
            null,
            null,
            null,
            false,
            false,
            false,
            $appendix,
        );

        if (! ($first['success'] ?? false))
        {
            $this->error('No se pudo iniciar la guía: '.($first['message'] ?? 'unknown'));

            return self::FAILURE;
        }

        $firstText = (string) ($first['text'] ?? '');
        $this->line('<fg=cyan>Guía Humano:</> '.$firstText);
        $this->newLine();
        $this->pushHistory($history, $seedMessage, $firstText);

        while (true)
        {
            $message = $this->ask('Vos');
            $message = $message === null ? '' : trim($message);

            if ($message === '' || in_array(mb_strtolower($message), ['salir', 'exit', 'quit'], true))
            {
                $this->info('Hasta luego.');
                break;
            }

            $reply = $replyService->getReply(
                $message,
                $history,
                $teamId,
                $withTools,
                $user->id,
                null,
                null,
                null,
                false,
                false,
                false,
                $appendix,
            );

            if (! ($reply['success'] ?? false))
            {
                $this->error('Error: '.($reply['message'] ?? 'Unknown'));

                continue;
            }

            $text = (string) ($reply['text'] ?? '');
            $this->line('<fg=cyan>Guía Humano:</> '.$text);
            $this->newLine();
            $this->pushHistory($history, $message, $text);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{direction: string, body: string}>  $history
     */
    private function pushHistory(array &$history, string $inboundBody, string $outboundBody): void
    {
        $history[] = ['direction' => 'inbound', 'body' => $inboundBody];
        $history[] = ['direction' => 'outbound', 'body' => $outboundBody];
        $maxPairs = 20;
        $maxLen = $maxPairs * 2;
        if (count($history) > $maxLen)
        {
            $history = array_slice($history, -$maxLen);
        }
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
