<?php

namespace App\Support;

use App\Models\List60Status;

class List60StatusAdvancer
{
    public const CONTACT_COUNT_STATUS_NAMES = ['1 Contacto', '2 Contactos', '3 Contactos'];

    /**
     * @var array<int, array{name: string, label_class: string}>
     */
    private const DEFAULT_STATUSES = [
        ['name' => '1 Contacto', 'label_class' => 'bg-label-success'],
        ['name' => '2 Contactos', 'label_class' => 'bg-label-warning'],
        ['name' => '3 Contactos', 'label_class' => 'bg-label-danger'],
        ['name' => 'Parado', 'label_class' => 'bg-label-secondary'],
        ['name' => 'Sin respuesta', 'label_class' => 'bg-label-info'],
        ['name' => 'Sin contactar', 'label_class' => 'bg-label-secondary'],
    ];

    public static function ensureDefaultStatuses(): void
    {
        foreach (self::DEFAULT_STATUSES as $status)
        {
            List60Status::query()->firstOrCreate(
                ['name' => $status['name']],
                ['label_class' => $status['label_class']],
            );
        }
    }

    public static function initialStatusId(): int
    {
        self::ensureDefaultStatuses();

        return (int) List60Status::query()
            ->where('name', 'Sin contactar')
            ->value('id');
    }

    public static function statusIdAfterOutreach(int $currentStatusId): int
    {
        self::ensureDefaultStatuses();

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
        $initialStatusId = self::initialStatusId();

        if ($currentStatusId === $initialStatusId || ! in_array($currentStatusId, $contactCountIds, true))
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
