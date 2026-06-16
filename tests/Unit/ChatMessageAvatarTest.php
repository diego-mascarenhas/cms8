<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\ChatMessageAvatar;
use PHPUnit\Framework\TestCase;

class ChatMessageAvatarTest extends TestCase
{
    public function test_for_user_falls_back_to_initials_when_no_photo(): void
    {
        $user = new User([
            'name' => 'Diego Mascarenhas',
            'email' => 'diego@example.com',
        ]);

        $avatar = ChatMessageAvatar::forUser($user);

        $this->assertSame('DM', $avatar['initials']);
    }

    public function test_for_assistant_uses_robot_icon(): void
    {
        $avatar = ChatMessageAvatar::forAssistant();

        $this->assertSame('robot', $avatar['icon']);
        $this->assertSame('bg-label-info', $avatar['label_class']);
    }
}
