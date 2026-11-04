<?php

namespace App\Services\Hosting;

/**
 * Holds WhatsApp bodies during a hosting rehearsal so they can be printed in that chat.
 */
class RehearsalChatOutbox
{
    private bool $capturing = false;

    /** @var list<string> */
    private array $messages = [];

    public function capture(bool $capturing): void
    {
        $this->capturing = $capturing;
        if (! $capturing)
        {
            $this->messages = [];
        }
    }

    public function isCapturing(): bool
    {
        return $this->capturing;
    }

    public function push(string $message): void
    {
        $message = trim($message);
        if ($message === '')
        {
            return;
        }

        $this->messages[] = $message;
    }

    /**
     * @return list<string>
     */
    public function pull(): array
    {
        $messages = $this->messages;
        $this->messages = [];

        return $messages;
    }
}
