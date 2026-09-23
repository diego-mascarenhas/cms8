<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketResponse;
use App\Models\User;
use App\Services\Tickets\RevisionTicketImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportRevisionTicketsTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_matches_email_and_skips_a_second_run(): void
    {
        $team = Team::factory()->create();
        $existing = User::factory()->create(['email' => 'cliente@example.com']);

        $this->source();

        DB::connection('revision_legacy')->table('users')->insert([
            ['id' => 10, 'name' => 'Cliente', 'email' => 'Cliente@example.com', 'phone' => '34600111222', 'role' => 'client'],
            ['id' => 11, 'name' => 'Nuevo', 'email' => 'nuevo@example.com', 'phone' => null, 'role' => 'admin'],
            ['id' => 12, 'name' => 'Resto', 'email' => 'resto@example.com', 'phone' => null, 'role' => 'client'],
        ]);
        DB::connection('revision_legacy')->table('tickets')->insert([
            'id' => 7,
            'user_id' => 10,
            'assigned_to' => 11,
            'subject' => 'Factura',
            'description' => 'No llega el PDF',
            'status' => 'closed',
            'priority' => 'high',
            'closed_at' => '2026-01-02 10:00:00',
            'last_response_at' => '2026-01-02 09:00:00',
            'created_at' => '2026-01-01 08:00:00',
            'updated_at' => '2026-01-02 10:00:00',
        ]);
        DB::connection('revision_legacy')->table('ticket_responses')->insert([
            'id' => 3,
            'ticket_id' => 7,
            'user_id' => 11,
            'message' => 'Ya está',
            'is_internal_note' => 0,
            'created_at' => '2026-01-02 09:00:00',
            'updated_at' => '2026-01-02 09:00:00',
        ]);

        $files = sys_get_temp_dir().'/revision-import-'.uniqid();
        mkdir($files.'/4', 0777, true);
        file_put_contents($files.'/4/nota.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        DB::connection('revision_legacy')->table('media')->insert([
            'id' => 4,
            'model_type' => 'App\\Models\\Ticket',
            'model_id' => 7,
            'collection_name' => 'attachments',
            'file_name' => 'nota.png',
        ]);

        $stats = app(RevisionTicketImporter::class)->import('revision_legacy', $team->id, $files);

        $this->assertSame(1, $stats['users_existing']);
        $this->assertSame(2, $stats['users_created']);
        $this->assertSame(1, $stats['tickets_imported']);
        $this->assertSame(1, $stats['responses_imported']);
        $this->assertSame(1, $stats['attachments_imported']);

        $ticket = Ticket::withoutGlobalScope('team')->where('subject', 'Factura')->first();
        $this->assertNotNull($ticket);
        $this->assertSame($team->id, $ticket->team_id);
        $this->assertSame($existing->id, $ticket->user_id);
        $this->assertSame('2026-01-01 08:00:00', $ticket->created_at->format('Y-m-d H:i:s'));
        $this->assertTrue($existing->fresh()->teams()->where('teams.id', $team->id)->exists());

        $created = User::query()->where('email', 'nuevo@example.com')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('admin'));
        $this->assertSame($created->id, $ticket->assigned_to);
        $this->assertSame(1, $ticket->getMedia('attachments')->count());

        $resto = User::query()->where('email', 'resto@example.com')->first();
        $this->assertNotNull($resto);
        $this->assertSame($team->id, $resto->current_team_id);
        $this->assertTrue($resto->teams()->where('teams.id', $team->id)->exists());

        $response = TicketResponse::query()->where('message', 'Ya está')->first();
        $this->assertSame('Ya está', $response->message);
        $this->assertSame('2026-01-02 09:00:00', $response->created_at->format('Y-m-d H:i:s'));

        $again = app(RevisionTicketImporter::class)->import('revision_legacy', $team->id, $files);
        $this->assertSame(0, $again['tickets_imported']);
        $this->assertSame(1, $again['tickets_skipped']);
        $this->assertSame(1, Ticket::withoutGlobalScope('team')->where('subject', 'Factura')->count());
    }

    private function source(): void
    {
        config([
            'database.connections.revision_legacy' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        DB::purge('revision_legacy');

        Schema::connection('revision_legacy')->create('users', function ($table)
        {
            $table->increments('id');
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('role');
        });
        Schema::connection('revision_legacy')->create('tickets', function ($table)
        {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('assigned_to')->nullable();
            $table->string('subject');
            $table->text('description');
            $table->string('status');
            $table->string('priority');
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_response_at')->nullable();
            $table->timestamps();
        });
        Schema::connection('revision_legacy')->create('ticket_responses', function ($table)
        {
            $table->increments('id');
            $table->unsignedInteger('ticket_id');
            $table->unsignedInteger('user_id');
            $table->text('message');
            $table->boolean('is_internal_note')->default(false);
            $table->timestamps();
        });
        Schema::connection('revision_legacy')->create('media', function ($table)
        {
            $table->increments('id');
            $table->string('model_type');
            $table->unsignedInteger('model_id');
            $table->string('collection_name');
            $table->string('file_name');
        });
    }
}
