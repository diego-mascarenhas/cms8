<?php

namespace App\Enums;

enum AdConnectionStatus: string
{
    case Active = 'active';
    case PendingAccount = 'pending_account';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case PendingApproval = 'pending_approval';

    public function label(): string
    {
        return match ($this)
        {
            self::Active => 'Conectada',
            self::PendingAccount => 'Selecciona cuenta',
            self::Expired => 'Expirada',
            self::Revoked => 'Revocada',
            self::PendingApproval => 'Pendiente de aprobación',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this)
        {
            self::Active => 'bg-label-success text-success',
            self::PendingAccount => 'bg-label-warning text-warning',
            self::Expired => 'bg-label-warning text-warning',
            self::Revoked => 'bg-label-danger text-danger',
            self::PendingApproval => 'bg-label-info text-info',
        };
    }
}
