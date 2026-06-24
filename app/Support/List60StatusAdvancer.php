<?php

namespace App\Support;

use App\Models\List60Status;

class List60StatusAdvancer
{
    public const CONTACT_COUNT_STATUS_NAMES = ['1 Contacto', '2 Contactos', '3 Contactos'];

    public static function initialStatusId(): int
    {
        return (int) List60Status::query()
            ->where('name', 'Sin contactar')
            ->value('id');
    }

    public static function statusIdAfterOutreach(int $currentStatusId): int
    {
        $contactCountIds = List60Status::query()
            ->whereIn('name', self::CONTACT_COUNT_STATUS_NAMES)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($contactCountIds === [])
        {
            return max(1, $currentStatusId);
        }

        $firstContactId = $contactCountIds[0];
        $maxContactId = $contactCountIds[count($contactCountIds) - 1];

        if ($currentStatusId === self::initialStatusId() || ! in_array($currentStatusId, $contactCountIds, true))
        {
            return $firstContactId;
        }

        if ($currentStatusId >= $maxContactId)
        {
            return $maxContactId;
        }

        return $currentStatusId + 1;
    }
}
