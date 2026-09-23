<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketResponse;
use App\Models\User;
use App\Services\Tickets\RevcmsTicketImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportRevcmsTicketsTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_tickets_from_every_group_land_on_the_team(): void
    {
        $team = Team::factory()->create();
        $existing = User::factory()->create(['email' => 'cliente@example.com']);
        $this->source();

        $opened = 1700000000;
        DB::connection('revcms')->table('contactos')->insert([
            ['id' => 10, 'nombre' => 'Ana', 'apellido' => 'Paz', 'email' => 'Cliente@example.com', 'celular' => '5491111111111', 'telefono' => null, 'area_privada' => 3],
            ['id' => 11, 'nombre' => 'Leo', 'apellido' => 'Staff', 'email' => 'leo@example.com', 'celular' => null, 'telefono' => null, 'area_privada' => 2],
            ['id' => 12, 'nombre' => 'Otro', 'apellido' => 'Grupo', 'email' => 'otro@example.com', 'celular' => null, 'telefono' => null, 'area_privada' => 3],
        ]);
        DB::connection('revcms')->table('tickets')->insert([
            ['id' => 9389, 'grupo' => 502, 'asunto' => 'Tag Manager', 'prioridad' => 4, 'estado' => 7, 'fecha_alta' => $opened, 'username_alta' => 10, 'fecha_modificacion' => $opened + 3600],
            ['id' => 100, 'grupo' => 513, 'asunto' => 'Otro grupo', 'prioridad' => 1, 'estado' => 2, 'fecha_alta' => $opened + 10, 'username_alta' => 12, 'fecha_modificacion' => null],
        ]);
        DB::connection('revcms')->table('tickets_rel_contactos')->insert([
            ['id_ticket' => 9389, 'id_contacto' => 10],
            ['id_ticket' => 100, 'id_contacto' => 12],
        ]);
        DB::connection('revcms')->table('tickets_items')->insert([
            ['id' => 1, 'id_ticket' => 9389, 'id_contacto' => 10, 'mensaje' => 'No carga el sitio', 'visibilidad' => 0, 'fecha_alta' => $opened],
            ['id' => 2, 'id_ticket' => 9389, 'id_contacto' => 11, 'mensaje' => 'Nota interna', 'visibilidad' => 1, 'fecha_alta' => $opened + 60],
            ['id' => 3, 'id_ticket' => 100, 'id_contacto' => 12, 'mensaje' => 'Consulta', 'visibilidad' => 0, 'fecha_alta' => $opened + 10],
        ]);
        DB::connection('revcms')->table('tickets_items_adjuntos')->insert([
            'id' => 1,
            'id_ticket_item' => 2,
            'nombre' => 'nota.png',
            'archivo' => 'nota.png',
        ]);
        DB::connection('revcms')->table('ticket_ratings')->insert([
            'ticket_id' => 9389,
            'user_id' => 10,
            'tiempo_respuesta' => 5,
            'atencion' => 4,
            'solucion' => 5,
            'comentarios' => 'Bien',
        ]);

        $files = sys_get_temp_dir().'/revcms-import-'.uniqid();
        mkdir($files, 0777, true);
        file_put_contents($files.'/nota.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        $stats = app(RevcmsTicketImporter::class)->import('revcms', $team->id, $files);

        $this->assertSame(1, $stats['users_existing']);
        $this->assertSame(2, $stats['users_created']);
        $this->assertSame(2, $stats['tickets_imported']);
        $this->assertSame(1, $stats['responses_imported']);
        $this->assertSame(1, $stats['attachments_imported']);
        $this->assertSame(1, $stats['ratings_imported']);

        $ticket = Ticket::withoutGlobalScope('team')->where('subject', 'Tag Manager')->first();
        $this->assertNotNull($ticket);
        $this->assertSame($team->id, $ticket->team_id);
        $this->assertSame($existing->id, $ticket->user_id);
        $this->assertSame('closed', $ticket->status);
        $this->assertSame('urgent', $ticket->priority);
        $this->assertSame('No carga el sitio', $ticket->description);

        $other = Ticket::withoutGlobalScope('team')->where('subject', 'Otro grupo')->first();
        $this->assertSame($team->id, $other->team_id);
        $this->assertSame('open', $other->status);

        $response = TicketResponse::query()->where('ticket_id', $ticket->id)->first();
        $this->assertTrue($response->is_internal_note);
        $this->assertSame(1, $response->getMedia('attachments')->count());
        $this->assertTrue(User::query()->where('email', 'otro@example.com')->first()->teams()->where('teams.id', $team->id)->exists());

        $again = app(RevcmsTicketImporter::class)->import('revcms', $team->id, $files);
        $this->assertSame(0, $again['tickets_imported']);
        $this->assertSame(2, $again['tickets_skipped']);
        $this->assertSame(2, Ticket::withoutGlobalScope('team')->count());
    }

    public function test_same_opening_with_different_replies_imports_both(): void
    {
        $team = Team::factory()->create();
        $this->source();
        $opened = 1700000000;

        DB::connection('revcms')->table('contactos')->insert([
            'id' => 10, 'nombre' => 'Ana', 'apellido' => 'Paz', 'email' => 'ana@example.com', 'celular' => null, 'telefono' => null, 'area_privada' => 3,
        ]);
        DB::connection('revcms')->table('tickets')->insert([
            ['id' => 1, 'grupo' => 502, 'asunto' => 'Caido', 'prioridad' => 1, 'estado' => 7, 'fecha_alta' => $opened, 'username_alta' => 10, 'fecha_modificacion' => null],
            ['id' => 2, 'grupo' => 502, 'asunto' => 'Caido', 'prioridad' => 1, 'estado' => 7, 'fecha_alta' => $opened, 'username_alta' => 10, 'fecha_modificacion' => null],
        ]);
        DB::connection('revcms')->table('tickets_rel_contactos')->insert([
            ['id_ticket' => 1, 'id_contacto' => 10],
            ['id_ticket' => 2, 'id_contacto' => 10],
        ]);
        DB::connection('revcms')->table('tickets_items')->insert([
            ['id' => 1, 'id_ticket' => 1, 'id_contacto' => 10, 'mensaje' => 'Hola', 'visibilidad' => 0, 'fecha_alta' => $opened],
            ['id' => 2, 'id_ticket' => 1, 'id_contacto' => 10, 'mensaje' => 'Respuesta A', 'visibilidad' => 0, 'fecha_alta' => $opened + 1],
            ['id' => 3, 'id_ticket' => 2, 'id_contacto' => 10, 'mensaje' => 'Hola', 'visibilidad' => 0, 'fecha_alta' => $opened],
            ['id' => 4, 'id_ticket' => 2, 'id_contacto' => 10, 'mensaje' => 'Respuesta B', 'visibilidad' => 0, 'fecha_alta' => $opened + 1],
        ]);

        $stats = app(RevcmsTicketImporter::class)->import('revcms', $team->id);
        $this->assertSame(2, $stats['tickets_imported']);
        $this->assertSame(2, TicketResponse::query()->count());

        $again = app(RevcmsTicketImporter::class)->import('revcms', $team->id);
        $this->assertSame(0, $again['tickets_imported']);
        $this->assertSame(2, $again['tickets_skipped']);
    }

    private function source(): void
    {
        config([
            'database.connections.revcms' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('revcms');

        Schema::connection('revcms')->create('contactos', function ($table)
        {
            $table->unsignedInteger('id')->primary();
            $table->string('nombre')->nullable();
            $table->string('apellido')->nullable();
            $table->string('email')->nullable();
            $table->string('celular')->nullable();
            $table->string('telefono')->nullable();
            $table->unsignedTinyInteger('area_privada')->nullable();
        });
        Schema::connection('revcms')->create('tickets', function ($table)
        {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('grupo');
            $table->string('asunto');
            $table->unsignedTinyInteger('prioridad');
            $table->unsignedTinyInteger('estado');
            $table->unsignedInteger('fecha_alta');
            $table->unsignedInteger('username_alta')->nullable();
            $table->unsignedInteger('fecha_modificacion')->nullable();
        });
        Schema::connection('revcms')->create('tickets_rel_contactos', function ($table)
        {
            $table->unsignedInteger('id_ticket');
            $table->unsignedInteger('id_contacto');
        });
        Schema::connection('revcms')->create('tickets_items', function ($table)
        {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('id_ticket');
            $table->unsignedInteger('id_contacto');
            $table->text('mensaje');
            $table->unsignedTinyInteger('visibilidad');
            $table->unsignedInteger('fecha_alta');
        });
        Schema::connection('revcms')->create('tickets_items_adjuntos', function ($table)
        {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('id_ticket_item');
            $table->string('nombre');
            $table->string('archivo');
        });
        Schema::connection('revcms')->create('ticket_ratings', function ($table)
        {
            $table->unsignedInteger('ticket_id');
            $table->unsignedInteger('user_id');
            $table->unsignedTinyInteger('tiempo_respuesta');
            $table->unsignedTinyInteger('atencion');
            $table->unsignedTinyInteger('solucion');
            $table->text('comentarios')->nullable();
        });
    }
}
