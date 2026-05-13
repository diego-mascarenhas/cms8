<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\App;
use Tests\TestCase;

class NavbarNotificationsCopyTest extends TestCase
{
    public function test_navbar_notification_translation_keys_resolve_in_english(): void
    {
        App::setLocale('en');

        $this->assertSame('Notifications', __('Notifications'));
        $this->assertStringContainsString('Alerts', __('app.navbar_notifications_lead'));
        $this->assertStringContainsString('notifications', strtolower(__('app.navbar_notifications_empty')));
        $this->assertStringContainsString('tasks', strtolower(__('app.navbar_notifications_view_tasks')));
        $this->assertSame('Open assistant panel', __('app.assistant_fab_title'));
    }

    public function test_navbar_notification_translation_keys_resolve_in_spanish(): void
    {
        App::setLocale('es');

        $this->assertSame('Notificaciones', __('Notifications'));
        $this->assertStringContainsString('Avisos', __('app.navbar_notifications_lead'));
        $this->assertStringContainsString('notificaciones', strtolower(__('app.navbar_notifications_empty')));
        $this->assertStringContainsString('tareas', strtolower(__('app.navbar_notifications_view_tasks')));
        $this->assertSame('Abrir panel del asistente', __('app.assistant_fab_title'));
    }
}
