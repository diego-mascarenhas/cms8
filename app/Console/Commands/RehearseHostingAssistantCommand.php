<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\Team;
use App\Models\User;
use App\Services\Hosting\HostingCustomerAssistantService;
use App\Services\InboxProductOnboardingService;
use App\Services\UserResolverService;
use Illuminate\Console\Command;

class RehearseHostingAssistantCommand extends Command
{
    protected $signature = 'hosting:rehearse
        {message : WhatsApp message to rehearse}
        {--team= : Team id}
        {--phone= : Customer phone}
        {--contact= : Contact id}
        {--domain= : Domain when the contact has several}
        {--plan= : Plan name to rehearse a change}
        {--apply : Change Stripe and cPanel. Refused when APP_ENV is production}
        {--yes : Skip the confirmation for --apply}
        {--allow-production : Allow a dry run when APP_ENV is production}';

    protected $description = 'Rehearse a customer hosting WhatsApp message without sending it';

    public function handle(
        UserResolverService $users,
        InboxProductOnboardingService $onboarding,
        HostingCustomerAssistantService $hosting,
    ): int {
        if ($this->blockedByEnvironment())
        {
            return self::FAILURE;
        }

        $message = trim((string) $this->argument('message'));
        if ($message === '')
        {
            $this->error('El mensaje está vacío.');

            return self::FAILURE;
        }

        $contact = $this->contact($users);
        if (! $contact instanceof Contact)
        {
            return self::FAILURE;
        }

        $team = Team::withoutGlobalScopes()->find((int) $contact->team_id);
        if (! $team instanceof Team)
        {
            $this->error('No encontré el equipo del contacto.');

            return self::FAILURE;
        }

        $flow = data_get($contact->data, InboxProductOnboardingService::DATA_KEY);
        $active = (bool) data_get($flow, 'active');
        $step = (string) (data_get($flow, 'step') ?: '—');
        $asksAccount = $onboarding->asksAboutOwnAccount($message);

        $this->line('Entorno: '.app()->environment());
        $this->line('URL: '.config('app.url'));
        $this->line('Equipo: '.$team->id.' '.$team->name);
        $this->line('Contacto: '.trim($contact->name.' '.$contact->surname).' (#'.$contact->id.')');
        $this->newLine();
        $this->line('Mensaje: '.$message);
        $this->line('Embudo de recomendación: '.($active ? 'activo ('.$step.')' : 'inactivo').'.');
        $this->line($asksAccount
            ? 'Este mensaje no lo contesta el embudo. El asistente usa Hosting cPanel.'
            : 'Este mensaje puede seguir en el embudo si está activo.');
        $this->newLine();
        $this->line($hosting->listForContact((int) $team->id, (int) $contact->id));

        $plan = trim((string) $this->option('plan'));
        if ($plan === '')
        {
            $this->newLine();
            $this->comment('No se envió WhatsApp ni se cambió Stripe ni el servidor.');

            return self::SUCCESS;
        }

        $this->newLine();
        if (! $this->option('apply'))
        {
            $this->line($hosting->rehearseUpgrade((int) $team->id, (int) $contact->id, $this->option('domain') ? (string) $this->option('domain') : null, $plan));
            $this->comment('Para aplicarlo en este entorno, repetí el comando con --apply.');

            return self::SUCCESS;
        }

        if (app()->environment('production'))
        {
            $this->error('--apply no corre en producción.');

            return self::FAILURE;
        }

        if (! $this->option('yes') && ! $this->confirm('Esto cambia la suscripción en Stripe y el paquete en cPanel. ¿Seguimos?'))
        {
            $this->comment('No cambié nada.');

            return self::SUCCESS;
        }

        $actor = User::query()->find($team->user_id);
        if (! $actor instanceof User)
        {
            $this->error('El equipo no tiene un usuario para registrar el cambio.');

            return self::FAILURE;
        }

        $this->line($hosting->requestUpgrade(
            (int) $team->id,
            (int) $contact->id,
            $actor,
            $this->option('domain') ? (string) $this->option('domain') : null,
            $plan,
        ));

        return self::SUCCESS;
    }

    private function blockedByEnvironment(): bool
    {
        if (! app()->environment('production'))
        {
            return false;
        }

        if ($this->option('apply'))
        {
            $this->error('--apply no corre en producción.');

            return true;
        }

        if (! $this->option('allow-production'))
        {
            $this->error('Este ensayo no corre en producción. Usalo en staging, o pasá --allow-production para un ensayo sin cambios.');

            return true;
        }

        return false;
    }

    private function contact(UserResolverService $users): ?Contact
    {
        $contactId = (int) $this->option('contact');
        if ($contactId > 0)
        {
            $contact = Contact::withoutGlobalScopes()->find($contactId);
            if (! $contact instanceof Contact)
            {
                $this->error('No encontré el contacto '.$contactId.'.');

                return null;
            }

            $teamId = (int) $this->option('team');
            if ($teamId > 0 && (int) $contact->team_id !== $teamId)
            {
                $this->error('El contacto '.$contactId.' no está en el equipo '.$teamId.'.');

                return null;
            }

            return $contact;
        }

        $phone = trim((string) $this->option('phone'));
        $teamId = (int) $this->option('team');
        if ($phone === '' || $teamId < 1)
        {
            $this->error('Indicá --contact=ID, o --team=ID y --phone=.');

            return null;
        }

        $matches = $users->findContactsInTeamByPhone($teamId, $phone);
        if ($matches->isEmpty())
        {
            $this->error('No hay un contacto con ese teléfono en el equipo '.$teamId.'.');

            return null;
        }

        if ($matches->count() > 1)
        {
            $this->error('Hay varios contactos con ese teléfono. Elegí uno con --contact=.');
            foreach ($matches as $match)
            {
                $this->line('#'.$match->id.' '.trim($match->name.' '.$match->surname));
            }

            return null;
        }

        return $matches->first();
    }
}
