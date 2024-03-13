<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

use App\Models\User;
use App\Services\ApiAuthService;

class Start extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '¡Welcome to CMS8!';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->getStartActions();
    }

    public function getStartActions()
    {
        $action = $this->choice('¿Qué acción deseas realizar?', [
            1 => 'Crear Usuario',
            2 => 'Crear Servicio',
            3 => 'Crear Factura',
            4 => 'Enviar Factura',
            5 => 'Bruler',
            6 => 'Salir'
        ]);

        switch ($action)
        {
            case 'Crear Usuario':
                $this->createUser();
                break;
            case 'Crear Servicio':
                $this->createService();
                break;
            case 'Crear Factura':
                $this->createInvoice();
                break;
            case 'Emitir Factura':
                $this->sendInvoice();
                break;
            case 'Bruler':
                $this->call('api:bruler');
                $this->call('fetch:bruler-data');
                break;
            case 'Salir':
                $this->warn('Se ha cancelado la acción');
                exit;
        }
    }

    private function createUser()
    {
        $name = $this->ask('Ingrese el nombre del usuario');
        $email = $this->ask('Ingrese el correo electrónico del usuario');
        $password = str::random(10);

        $user = new User();
        $user->name = $name;
        $user->email = $email;
        $user->password = bcrypt($password);
        $user->save();

        $this->info('Usuario ' . $name . ' creado con éxito.');
    }

    private function createService()
    {
        $email = $this->ask('Ingrese el email del usuario');

        $user = User::where('email', $email)->first();

        if ($user)
        {
            $this->info("Servicio creado al usuario: {$user->name}");
        }
        else
        {
            $this->error("No se encontró un usuario con el email: $email");
        }
    }

    private function createInvoice()
    {
        $users = User::all(['id', 'name']);

        if ($users->isEmpty())
        {
            $this->info('No hay usuarios disponibles.');
            return;
        }

        $options = $users->mapWithKeys(function ($user)
        {
            return ["{$user->id} - {$user->name}" => $user->name];
        })->toArray();

        $userId = $this->choice('Seleccione un usuario para emitir una factura', $options);

        $selectedUser = User::find($userId);
        if ($selectedUser)
        {
            $this->info("Factura emitida al usuario: {$selectedUser->name} " . $selectedUser->id);
        }
        else
        {
            $this->error("Usuario no encontrado.");
        }
    }
}