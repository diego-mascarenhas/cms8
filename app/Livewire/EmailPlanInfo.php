<?php

namespace App\Livewire;

use Livewire\Component;

class EmailPlanInfo extends Component
{
    public $team;

    public function mount()
    {
        $this->team = auth()->user()->load('currentTeam.settings')->currentTeam;
    }

    public function render()
    {
        // Refresh team data on each render
        $this->team = auth()->user()->load('currentTeam.settings')->currentTeam;

        $currentPlan = $this->team->getEmailPlan();
        $remaining = $this->team->getRemainingEmails();

        // Calculate percentages
        $monthlyPercent = $remaining['monthly_limit'] > 0
            ? ($remaining['monthly_used'] / $remaining['monthly_limit']) * 100
            : 0;
        $monthlyColor = $monthlyPercent >= 100 ? 'danger' : ($monthlyPercent >= 80 ? 'warning' : 'success');

        $dailyPercent = $remaining['daily_limit'] > 0
            ? ($remaining['daily_used'] / $remaining['daily_limit']) * 100
            : 0;
        $dailyColor = $dailyPercent >= 100 ? 'danger' : ($dailyPercent >= 80 ? 'warning' : 'success');

        $contactsCount = $this->team->contacts()->count();
        $contactLimit = $this->team->getContactLimit();
        $contactsPercent = $contactLimit > 0 ? ($contactsCount / $contactLimit) * 100 : 0;
        $contactsColor = $contactsPercent >= 100 ? 'danger' : ($contactsPercent >= 80 ? 'warning' : 'success');

        return view('livewire.email-plan-info', [
            'currentPlan' => $currentPlan,
            'remaining' => $remaining,
            'monthlyPercent' => $monthlyPercent,
            'monthlyColor' => $monthlyColor,
            'dailyPercent' => $dailyPercent,
            'dailyColor' => $dailyColor,
            'contactsCount' => $contactsCount,
            'contactLimit' => $contactLimit,
            'contactsPercent' => $contactsPercent,
            'contactsColor' => $contactsColor,
        ]);
    }
}
