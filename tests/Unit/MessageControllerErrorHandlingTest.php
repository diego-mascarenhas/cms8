<?php

namespace Tests\Unit;

use App\Http\Controllers\MessageController;
use Exception;
use Tests\TestCase;

class MessageControllerErrorHandlingTest extends TestCase
{
    private MessageController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new MessageController;
    }

    public function test_spf_error_returns_user_friendly_message()
    {
        $exception = new Exception('550 domain is not configured with ORIGIN IP IN SPF see mail.baby/spf log');

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getUserFriendlyErrorMessage');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $exception);

        $this->assertEquals(
            "No se pudo enviar el email de prueba.\nPor favor, contacte con soporte técnico para autorizar la salida de emails desde su dominio.",
            $result,
        );
    }

    public function test_authentication_error_returns_user_friendly_message()
    {
        $exception = new Exception('535 Authentication failed');

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getUserFriendlyErrorMessage');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $exception);

        $this->assertEquals(
            'Error de autenticación en el servidor de correo. Verifique las credenciales de configuración.',
            $result,
        );
    }

    public function test_connection_error_returns_user_friendly_message()
    {
        $exception = new Exception('Connection timeout');

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getUserFriendlyErrorMessage');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $exception);

        $this->assertEquals(
            'No se pudo conectar al servidor de correo. Verifique la configuración de conexión.',
            $result,
        );
    }

    public function test_quota_error_returns_user_friendly_message()
    {
        $exception = new Exception('Quota exceeded');

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getUserFriendlyErrorMessage');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $exception);

        $this->assertEquals(
            'Se ha alcanzado el límite de envío de emails. Contacte con soporte técnico.',
            $result,
        );
    }

    public function test_unknown_error_returns_generic_message()
    {
        $exception = new Exception('Some unknown error occurred');

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getUserFriendlyErrorMessage');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $exception);

        $this->assertEquals(
            'No se pudo enviar el email de prueba. Por favor, contacte con soporte técnico si el problema persiste.',
            $result,
        );
    }
}
