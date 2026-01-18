<?php

namespace App\Support\Subscriptions;

use App\Models\StripeSubscription;

class SubscriptionMetadataManager
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function schema(): array
    {
        return [
            \Filament\Forms\Components\Select::make('category')
                ->label('Categoría de servicio')
                ->options([
                    'hosting' => 'Hosting',
                    'web_cloud' => 'Web Cloud',
                    'vps' => 'VPS',
                    'domain' => 'Domain',
                    'backups' => 'Backups',
                    'mailer' => 'Mailer',
                    'whatsapp' => 'WhatsApp',
                ])
                ->required(),
            \Filament\Forms\Components\TextInput::make('plan')
                ->label('Plan')
                ->disabled()
                ->dehydrated()
                ->helperText('Este plan se obtiene automáticamente')
                ->placeholder('Se cargará automáticamente'),
            \Filament\Forms\Components\TextInput::make('server')
                ->label('Servidor')
                ->placeholder('server.example.com')
                ->maxLength(255),
            \Filament\Forms\Components\TextInput::make('domain')
                ->label('Dominio')
                ->placeholder('example.com')
                ->maxLength(255),
            \Filament\Forms\Components\TextInput::make('user')
                ->label('Usuario')
                ->placeholder('username')
                ->maxLength(255),
            \Filament\Forms\Components\TextInput::make('email')
                ->label('Email')
                ->email()
                ->placeholder('user@example.com')
                ->maxLength(255),
            \Filament\Forms\Components\Checkbox::make('auto_suspend')
                ->label('Auto-suspensión si el cliente no paga')
                ->default(false)
                ->helperText('El servicio se suspenderá automáticamente si no se recibe el pago'),
        ];
    }

    /**
     * Fill form with subscription metadata
     */
    public static function fillForm(StripeSubscription $subscription): array
    {
        $data = $subscription->data ?? [];

        return [
            'category' => $data['category'] ?? $data['type'] ?? null, // Support both for reading old data
            'plan' => $data['plan'] ?? null,
            'server' => $data['server'] ?? null,
            'domain' => $data['domain'] ?? null,
            'user' => $data['user'] ?? null,
            'email' => $data['email'] ?? null,
            'auto_suspend' => $data['auto_suspend'] ?? false,
        ];
    }
}
