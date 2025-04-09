<?php

namespace App\Livewire;

use Livewire\Component;

class HelpCenterIcon extends Component
{
    public function render()
    {
        return <<<'HTML'
        <li class="nav-item me-3 me-xl-1">
            <a class="nav-link" href="{{ route('chat.index') }}" 
               data-bs-toggle="tooltip" data-bs-placement="bottom" title="Chat Support">
                <i class="ti ti-lifebuoy ti-md"></i>
            </a>
        </li>
        HTML;
    }
}
