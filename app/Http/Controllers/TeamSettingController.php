<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Http\Requests\UpdateTeamSettingsRequest;

class TeamSettingController extends Controller
{
    public function edit(Team $team)
    {
        $this->authorize('update', $team);

        $settings = [
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
        ];

        return view('team-settings.edit', compact('team', 'settings'));
    }

    public function update(UpdateTeamSettingsRequest $request, Team $team)
    {
        $this->authorize('update', $team);

        foreach ($request->validated() as $group => $settings)
        {
            foreach ($settings as $key => $value)
            {
                if (!empty($value))
                {
                    $team->setSetting($key, $value, [
                        'group' => $group,
                        'is_encrypted' => in_array($key, ['stripe_secret', 'stripe_webhook']),
                    ]);
                }
            }
        }

        return redirect()
            ->back()
            ->with('success', 'La configuración de Stripe se actualizó correctamente.');
    }
}