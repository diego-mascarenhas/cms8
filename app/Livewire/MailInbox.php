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
        if ($index >= 0 && $index < count($this->emails))
        {
            $this->selectedIndex = $index;
        }
    }

    public function getSelectedEmailProperty(): ?array
    {
        $hasEntry = $this->selectedIndex !== null && isset($this->emails[$this->selectedIndex]);

        if (! $hasEntry)
        {
            return null;
        }

        return $this->emails[$this->selectedIndex];
    }

    public function render()
    {
        return view('livewire.mail-inbox');
    }
}
