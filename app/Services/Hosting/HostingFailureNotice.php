<?php

namespace App\Services\Hosting;

use App\Mail\HostingFailureMail;
use Illuminate\Support\Facades\Mail;
use Throwable;

class HostingFailureNotice
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function report(string $technical, array $context = []): void
    {
        $recipient = trim((string) config('humano_hosting.error_notify'));

        if ($recipient === '')
        {
            return;
        }

        try
        {
            Mail::to($recipient)->send(new HostingFailureMail(trim($technical), $context));
        } catch (Throwable $exception)
        {
            report($exception);
        }
    }

    public function userMessage(string $technical, ?string $fallback = null): string
    {
        $text = trim($technical);
        $lower = strtolower($text);
        $fallback ??= 'No pudimos crear el hosting. Avisamos al equipo para resolverlo.';

        if ($text === '')
        {
            return $fallback;
        }

        if ($this->mentions($lower, ['unable to use package', 'reseller restriction', 'paquete no existe', 'package does not exist']))
        {
            return 'No pudimos crear el hosting. El plan todavía no está disponible en el servidor. Avisamos al equipo para resolverlo.';
        }

        if ($this->mentions($lower, ['already exists', 'ya existe', 'domain already', 'username already']))
        {
            return 'Ese dominio o el usuario ya están creados en el servidor. Probá con otros datos.';
        }

        if ($this->mentions($lower, ['connection error', 'invalid response', 'missing token', 'permission denied', 'sorry', 'unable to']))
        {
            return $fallback;
        }

        return $text;
    }

    /**
     * @param  list<string>  $needles
     */
    private function mentions(string $text, array $needles): bool
    {
        foreach ($needles as $needle)
        {
            if (str_contains($text, $needle))
            {
                return true;
            }
        }

        return false;
    }
}
