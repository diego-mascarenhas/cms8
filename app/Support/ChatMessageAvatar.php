<?php

namespace App\Support;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Avatar payload for chat message bubbles (web UI + JSON polling).
 *
 * @phpstan-type ChatAvatar array{photo_url?: string|null, initials?: string, icon?: string, label_class?: string}
 */
class ChatMessageAvatar
{
    /**
     * @return array{user: ChatAvatar, assistant: ChatAvatar, contact: ChatAvatar, current_user: ChatAvatar}
     */
    public static function contextForChat(
        bool $viewAssistant,
        ?User $authUser,
        ?User $assistantConversationUser,
        ?User $contactUser,
        ?Contact $selectedContact,
        ?string $selectedPhone,
    ): array {
        $conversationUser = $viewAssistant
            ? ($assistantConversationUser ?? $authUser)
            : $authUser;

        return [
            'user' => self::forUser($conversationUser, 'bg-label-primary'),
            'assistant' => self::forAssistant(),
            'contact' => self::forContact($contactUser, $selectedContact, $selectedPhone),
            'current_user' => self::forUser($authUser, 'bg-label-primary'),
        ];
    }

    /**
     * @return ChatAvatar
     */
    public static function forUser(?User $user, string $labelClass = 'bg-label-primary'): array
    {
        if (! $user)
        {
            return [
                'initials' => '?',
                'label_class' => $labelClass,
            ];
        }

        $photoPath = $user->profile_photo_path ?? null;
        if (is_string($photoPath) && $photoPath !== '')
        {
            $photoUrl = self::reachablePhotoUrl($photoPath);

            return [
                'photo_url' => $photoUrl,
                'label_class' => $labelClass,
            ];
        }

        return [
            'initials' => self::initialsFromName($user->name ?? $user->email ?? '?'),
            'label_class' => $labelClass,
        ];
    }

    /**
     * @return ChatAvatar
     */
    public static function forAssistant(): array
    {
        return [
            'icon' => 'robot',
            'label_class' => 'bg-label-info',
        ];
    }

    /**
     * @return ChatAvatar
     */
    public static function forContact(?User $contactUser, ?Contact $contact, ?string $phone): array
    {
        if ($contactUser)
        {
            $fromUser = self::forUser($contactUser, 'bg-label-success');
            if (isset($fromUser['photo_url']) || ($fromUser['initials'] ?? '') !== '?')
            {
                return $fromUser;
            }
        }

        $name = trim((string) ($contact?->name ?? $contactUser?->name ?? ''));
        if ($name !== '')
        {
            return [
                'initials' => self::initialsFromName($name),
                'label_class' => 'bg-label-success',
            ];
        }

        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return [
            'initials' => $digits !== '' ? substr($digits, -2) : '?',
            'label_class' => 'bg-label-success',
        ];
    }

    /**
     * SPAs call cms8.test; Storage::url() follows APP_URL (idoneo.test here) and the <img> never loads.
     */
    public static function reachablePhotoUrl(string $path): ?string
    {
        $disk = (string) config('jetstream.profile_photo_disk', 'public');
        if ($disk === 'public')
        {
            $request = request();
            if ($request)
            {
                return $request->getSchemeAndHttpHost().'/storage/'.ltrim($path, '/');
            }
        }

        $url = Storage::disk($disk)->url($path);

        return is_string($url) && $url !== '' ? $url : null;
    }

    public static function initialsFromName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $parts = array_values(array_filter($parts, fn (string $part) => $part !== ''));

        if (count($parts) >= 2)
        {
            return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[count($parts) - 1], 0, 1));
        }

        if (count($parts) === 1)
        {
            return mb_strtoupper(mb_substr($parts[0], 0, min(2, mb_strlen($parts[0]))));
        }

        return '?';
    }
}
