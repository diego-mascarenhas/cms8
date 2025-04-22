<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Http\Requests\UpdateTeamSettingsRequest;

class TeamSettingController extends Controller
{
    public function index(Team $team)
    {
        $this->authorize('update', $team);

        // Get all team settings grouped by group
        $groupedSettings = $team->settings()
            ->orderBy('group')
            ->get()
            ->groupBy('group');

        return view('team-settings.index', compact('team', 'groupedSettings'));
    }

    public function edit(Team $team, $group = 'stripe')
    {
        $this->authorize('update', $team);

        $settings = $this->getSettingsConfig($team, $group);

        return view('team-settings.edit', compact('team', 'settings', 'group'));
    }

    public function update(UpdateTeamSettingsRequest $request, Team $team)
    {
        $this->authorize('update', $team);

        foreach ($request->validated() as $group => $settings)
        {
            foreach ($settings as $key => $value)
            {
                if (!empty($value) || $value === "0")
                {
                    $team->setSetting($key, $value, [
                        'group' => $group,
                        'is_encrypted' => in_array($key, ['stripe_secret', 'stripe_webhook']),
                    ]);
                }
            }
        }

        $group = array_key_first($request->validated());
        $message = ucfirst($group) . ' settings updated successfully';

        return redirect()
            ->back()
            ->with('success', $message);
    }

    /**
     * Get the settings configuration for a specific group
     */
    protected function getSettingsConfig(Team $team, $group)
    {
        $config = [
            'stripe' => [
                'title' => 'Stripe Integration',
                'icon' => 'ti ti-brand-stripe',
                'settings' => [
                    'stripe_public' => [
                        'label' => 'Public Key',
                        'type' => 'text',
                        'value' => $team->getSetting('stripe_public'),
                        'is_encrypted' => false,
                    ],
                    'stripe_secret' => [
                        'label' => 'Secret Key',
                        'type' => 'password',
                        'value' => $team->getSetting('stripe_secret'),
                        'is_encrypted' => true,
                    ],
                    'stripe_webhook' => [
                        'label' => 'Webhook Secret',
                        'type' => 'password',
                        'value' => $team->getSetting('stripe_webhook'),
                        'is_encrypted' => true,
                    ]
                ]
            ],
            'categories' => [
                'title' => 'Categories Configuration',
                'icon' => 'ti ti-category',
                'settings' => [
                    'categories_default_status' => [
                        'label' => 'Default Status',
                        'type' => 'select',
                        'options' => ['active' => 'Active', 'inactive' => 'Inactive'],
                        'value' => $team->getSetting('categories_default_status', 'active'),
                        'is_encrypted' => false,
                    ],
                    'categories_require_approval' => [
                        'label' => 'Require Approval',
                        'type' => 'checkbox',
                        'value' => $team->getSetting('categories_require_approval', '0'),
                        'is_encrypted' => false,
                    ]
                ]
            ],
            'notifications' => [
                'title' => 'Notification Settings',
                'icon' => 'ti ti-bell',
                'settings' => [
                    'notifications_email' => [
                        'label' => 'Email Notifications',
                        'type' => 'checkbox',
                        'value' => $team->getSetting('notifications_email', '1'),
                        'is_encrypted' => false,
                    ],
                    'notifications_sms' => [
                        'label' => 'SMS Notifications',
                        'type' => 'checkbox',
                        'value' => $team->getSetting('notifications_sms', '0'),
                        'is_encrypted' => false,
                    ]
                ]
            ]
        ];

        return isset($config[$group]) ? [$group => $config[$group]] : [];
    }
}