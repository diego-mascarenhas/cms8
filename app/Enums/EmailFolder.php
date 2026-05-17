<?php

namespace App\Enums;

enum EmailFolder: string
{
    case Inbox = 'inbox';
    case Sent = 'sent';
    case Draft = 'draft';
    case Spam = 'spam';
    case Trash = 'trash';
    case Archive = 'archive';

    public function label(): string
    {
        return match ($this)
        {
            self::Inbox => __('Inbox'),
            self::Sent => __('Sent'),
            self::Draft => __('Draft'),
            self::Spam => __('Spam'),
            self::Trash => __('Trash'),
            self::Archive => __('Archive'),
        };
    }

    /**
     * @return list<self>
     */
    public static function sidebarFolders(): array
    {
        return [
            self::Inbox,
            self::Sent,
            self::Draft,
            self::Spam,
            self::Trash,
        ];
    }
}
