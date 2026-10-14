<?php

namespace App\Services\Mail;

use App\Models\Contact;
use App\Models\ContactStatus;

class NewsContactStatus
{
    public const CLICK = 'click';

    public const CONVERSION = 'conversion';

    public const UNSUBSCRIBE = 'unsubscribe';

    /**
     * Move a non-client contact along the news pipeline. Clients stay as they are.
     */
    public function apply(Contact $contact, string $action): bool
    {
        $clientId = $this->statusId('Cliente', 5);
        if ((int) $contact->status_id === $clientId)
        {
            return false;
        }

        $followId = $this->statusId('En seguimiento', 2);
        $conversionId = $this->statusId('Conversión', 3);
        $lostId = $this->statusId('Perdido', 4);
        $finishedId = $this->statusId('Finalizado', 6);
        $current = (int) $contact->status_id;

        $next = match ($action)
        {
            self::CLICK => $this->statusAfterClick($current, $followId, $conversionId, $lostId, $finishedId),
            self::CONVERSION => in_array($current, [$conversionId, $finishedId], true) ? null : $conversionId,
            self::UNSUBSCRIBE => $lostId,
            default => null,
        };

        if ($next === null || $next === $current)
        {
            return false;
        }

        $contact->update(['status_id' => $next]);

        return true;
    }

    private function statusAfterClick(int $current, int $followId, int $conversionId, int $lostId, int $finishedId): ?int
    {
        if (in_array($current, [$followId, $conversionId, $lostId, $finishedId], true))
        {
            return null;
        }

        return $followId;
    }

    private function statusId(string $name, int $fallback): int
    {
        $id = ContactStatus::query()->where('name', $name)->value('id');

        return $id ? (int) $id : $fallback;
    }
}
