<?php

namespace App\Livewire;

use Livewire\Component;

class MailInbox extends Component
{
    /** @var array<int, array{subject: string, from: string, date: string, body: string, attachments: array}> */
    public array $emails = [];

    public ?int $selectedIndex = null;

    public function selectEmail(int $index): void
    {
        // #region agent log
        $logPath = base_path('.cursor/debug-e41f19.log');
        $payload = json_encode([
            'sessionId' => 'e41f19',
            'hypothesisId' => 'A',
            'location' => 'MailInbox::selectEmail',
            'message' => 'selectEmail called',
            'data' => ['index' => $index, 'emailsCount' => count($this->emails)],
            'timestamp' => (int) (microtime(true) * 1000),
        ])."\n";
        @file_put_contents($logPath, $payload, FILE_APPEND | LOCK_EX);
        // #endregion
        if ($index >= 0 && $index < count($this->emails))
        {
            $this->selectedIndex = $index;
        }
    }

    public function getSelectedEmailProperty(): ?array
    {
        if ($this->selectedIndex === null || ! isset($this->emails[$this->selectedIndex]))
        {
            return null;
        }

        return $this->emails[$this->selectedIndex];
    }

    public function render()
    {
        // #region agent log
        $logPath = base_path('.cursor/debug-e41f19.log');
        $payload = json_encode([
            'sessionId' => 'e41f19',
            'hypothesisId' => 'A',
            'location' => 'MailInbox::render',
            'message' => 'render',
            'data' => ['selectedIndex' => $this->selectedIndex],
            'timestamp' => (int) (microtime(true) * 1000),
        ])."\n";
        @file_put_contents($logPath, $payload, FILE_APPEND | LOCK_EX);
        // #endregion
        return view('livewire.mail-inbox');
    }
}
