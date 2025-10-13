<?php

namespace Tests\Feature;

use App\Mail\TestMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailTest extends TestCase
{
    public function test_real_smtp_connection()
    {
        try
        {
            // Enviar a múltiples direcciones para probar
            $recipients = [
                'diego.mascarenhas@icloud.com',
                'info@revisionalpha.es',
                // Agrega más emails si quieres probar
            ];

            foreach ($recipients as $email)
            {
                Mail::to($email)->send(new TestMail);
                $this->assertTrue(true);
                echo "\nEmail enviado correctamente a: ".$email;
            }
        } catch (\Exception $e)
        {
            $this->fail('Error al enviar el email: '.$e->getMessage());
        }
    }

    public function test_smtp_configuration()
    {
        // Verificar la configuración SMTP
        echo "\nConfiguracion actual:";
        echo "\nMAIL_HOST: ".config('mail.mailers.smtp.host');
        echo "\nMAIL_PORT: ".config('mail.mailers.smtp.port');
        echo "\nMAIL_USERNAME: ".config('mail.mailers.smtp.username');
        echo "\nMAIL_FROM_ADDRESS: ".config('mail.from.address');
        echo "\nMAIL_ENCRYPTION: ".config('mail.mailers.smtp.encryption');

        $this->assertTrue(true);
    }
}
