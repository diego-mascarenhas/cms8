<?php

namespace Tests\Feature;

use App\Enums\MeetingTranscriptStatus;
use App\Jobs\TranscribeMeetingTranscript;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\MeetingTranscript;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TokenUsageLog;
use App\Models\User;
use App\Services\MeetingTranscriptService;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MeetingTranscriptTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_queues_an_elevenlabs_transcription(): void
    {
        Storage::fake('local');
        Queue::fake();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $response = $this->actingAs($user)->post(route('meeting.store'), [
            'title' => 'Weekly',
            'language' => 'es',
            'audio' => UploadedFile::fake()->create('reunion.mp3', 100, 'audio/mpeg'),
        ]);

        $meeting = MeetingTranscript::query()->first();
        $response->assertRedirect(route('meeting.show', $meeting));
        $this->assertSame(MeetingTranscriptStatus::Pending, $meeting->status);
        Storage::disk('local')->assertExists($meeting->audio_path);
        Queue::assertPushed(TranscribeMeetingTranscript::class, function (TranscribeMeetingTranscript $job) use ($meeting)
        {
            return $job->meetingTranscriptId === $meeting->id;
        });
        $this->assertNotNull($meeting->fresh()->calendar_event_id);
    }

    public function test_missing_transcription_permission_is_explained(): void
    {
        $response = new Response(new PsrResponse(401, ['Content-Type' => 'application/json'], json_encode([
            'detail' => [
                'message' => 'The API key you used is missing the permission speech_to_text to execute this operation.',
            ],
        ])));

        $message = app(MeetingTranscriptService::class)->failureMessage(new RequestException($response));

        $this->assertSame(
            'La clave no tiene el permiso de transcripción. Activalo en la clave y volvé a subir el audio.',
            $message,
        );
    }

    public function test_show_renders_a_speaker_avatar_and_link_button(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $meeting = MeetingTranscript::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'title' => 'Weekly',
            'original_filename' => 'reunion.webm',
            'audio_path' => 'meetings/'.$team->id.'/reunion.webm',
            'language' => 'es',
            'status' => MeetingTranscriptStatus::Ready,
            'turns' => [[
                'speaker' => 'speaker_0',
                'label' => 'Persona 1',
                'text' => 'Hola',
                'start_seconds' => 0,
                'end_seconds' => 1,
            ]],
        ]);

        $this->actingAs($user)
            ->get(route('meeting.show', $meeting))
            ->assertOk()
            ->assertSee('Persona 1', false)
            ->assertSee('rounded-circle', false)
            ->assertSee('ti-link', false)
            ->assertSee('aria-label="Vincular"', false)
            ->assertSee('ti-arrow-left', false)
            ->assertSee('ti-trash', false)
            ->assertSee('ti-notes', false)
            ->assertSee('no es la misma persona', false)
            ->assertSee('caracteres o más', false);
    }

    public function test_linking_a_speaker_names_that_voice_in_this_meeting_only(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        \Illuminate\Support\Facades\DB::table('languages')->insertOrIgnore([
            'code' => 'es',
            'name' => 'Español',
        ]);
        \Illuminate\Support\Facades\DB::table('contact_statuses')->insertOrIgnore([
            'id' => 1,
            'name' => 'Lead',
            'label_class' => 'bg-label-secondary',
        ]);

        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Diego',
            'surname' => 'Mascarenhas',
            'language' => 'es',
            'email' => 'diego-meeting@example.test',
            'phone' => '600111222',
            'country' => null,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);

        $meeting = MeetingTranscript::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'title' => 'Weekly',
            'original_filename' => 'reunion.webm',
            'audio_path' => 'meetings/'.$team->id.'/reunion.webm',
            'language' => 'es',
            'status' => MeetingTranscriptStatus::Ready,
            'turns' => [
                [
                    'speaker' => 'speaker_0',
                    'label' => 'Persona 1',
                    'text' => 'Hola',
                    'start_seconds' => 0,
                    'end_seconds' => 1,
                ],
                [
                    'speaker' => 'speaker_0',
                    'label' => 'Persona 1',
                    'text' => 'Equipo',
                    'start_seconds' => 2,
                    'end_seconds' => 3,
                ],
                [
                    'speaker' => 'speaker_1',
                    'label' => 'Persona 2',
                    'text' => 'Listo',
                    'start_seconds' => 4,
                    'end_seconds' => 5,
                ],
            ],
        ]);

        $this->actingAs($user)
            ->post(route('meeting.speakers.update', $meeting), [
                'speaker' => 'speaker_0',
                'contact_id' => $contact->id,
            ])
            ->assertRedirect(route('meeting.show', $meeting));

        $turns = $meeting->fresh()->turns;
        $this->assertSame($contact->id, $turns[0]['contact_id']);
        $this->assertSame($contact->id, $turns[1]['contact_id']);
        $this->assertArrayNotHasKey('contact_id', $turns[2]);
        $this->assertSame('Persona 1', $turns[0]['label']);
        $this->assertSame(0, TokenUsageLog::withoutGlobalScopes()->count());

        $eventId = $meeting->fresh()->calendar_event_id;
        $this->assertNotNull($eventId);
        $this->assertTrue(
            CalendarEvent::query()->find($eventId)?->guests()->where('contacts.id', $contact->id)->exists(),
        );

        $this->actingAs($user)
            ->get(route('app-calendar-events', [
                'start' => now()->subDay()->toIso8601String(),
                'end' => now()->addDay()->toIso8601String(),
            ]))
            ->assertOk()
            ->assertJsonFragment(['guests' => [$contact->id]]);
    }

    public function test_index_renders_the_datatable(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($user)
            ->get(route('meeting.index'))
            ->assertOk()
            ->assertSee('id="meeting-table"', false)
            ->assertSee('no hay reuniones.', false);

        $this->actingAs($user)
            ->get(route('meeting.create'))
            ->assertOk()
            ->assertSee('id="record-audio"', false)
            ->assertSee('select2', false)
            ->assertSee('Indicá un título para la reunión.', false)
            ->assertDontSee('ElevenLabs', false);
    }

    public function test_job_groups_words_by_speaker(): void
    {
        Storage::fake('local');
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        Storage::disk('local')->put('meetings/'.$team->id.'/reunion.mp3', 'audio');

        $meeting = MeetingTranscript::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'title' => 'Weekly',
            'original_filename' => 'reunion.mp3',
            'audio_path' => 'meetings/'.$team->id.'/reunion.mp3',
            'language' => 'es',
            'status' => MeetingTranscriptStatus::Pending,
        ]);

        Http::fake([
            'api.elevenlabs.io/*' => Http::response([
                'text' => 'Hola equipo Listo',
                'words' => [
                    ['text' => 'Hola', 'type' => 'word', 'speaker_id' => 'speaker_0', 'start' => 0, 'end' => 1],
                    ['text' => 'equipo', 'type' => 'word', 'speaker_id' => 'speaker_0', 'start' => 1, 'end' => 2],
                    ['text' => 'Listo', 'type' => 'word', 'speaker_id' => 'speaker_1', 'start' => 2, 'end' => 3],
                ],
            ]),
        ]);

        (new TranscribeMeetingTranscript($meeting->id))->handle(app(MeetingTranscriptService::class));

        $meeting->refresh();
        $this->assertSame(MeetingTranscriptStatus::Ready, $meeting->status);
        $this->assertSame('Persona 1', $meeting->turns[0]['label']);
        $this->assertSame('Hola equipo', $meeting->turns[0]['text']);
        $this->assertSame('Persona 2', $meeting->turns[1]['label']);
        $this->assertSame('Listo', $meeting->turns[1]['text']);
        $this->assertArrayNotHasKey('contact_id', $meeting->turns[0]);
        Http::assertSent(function ($request)
        {
            return $request->url() === 'https://api.elevenlabs.io/v1/speech-to-text'
                && str_contains($request->body(), 'use_speaker_library');
        });
    }

    public function test_a_known_voice_is_named_on_the_next_meeting(): void
    {
        Storage::fake('local');
        $user = User::factory()->withPersonalTeam()->create();
        $team = $user->ownedTeams()->first();
        Storage::disk('local')->put('meetings/'.$team->id.'/otra.mp3', 'audio');
        \Illuminate\Support\Facades\DB::table('languages')->insertOrIgnore([
            'code' => 'es',
            'name' => 'Español',
        ]);
        \Illuminate\Support\Facades\DB::table('contact_statuses')->insertOrIgnore([
            'id' => 1,
            'name' => 'Lead',
            'label_class' => 'bg-label-secondary',
        ]);
        $contact = Contact::factory()->create([
            'team_id' => $team->id,
            'name' => 'Diego',
            'surname' => 'Mascarenhas',
            'language' => 'es',
            'email' => 'diego-voice@example.test',
            'phone' => '600111223',
            'country' => null,
            'creator_id' => $user->id,
            'responsible_id' => $user->id,
        ]);
        \App\Models\MeetingVoiceProfile::query()->create([
            'team_id' => $team->id,
            'contact_id' => $contact->id,
            'provider_speaker_id' => 'spk_diego',
        ]);
        $meeting = MeetingTranscript::withoutGlobalScope('team')->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'title' => 'Otra',
            'original_filename' => 'otra.mp3',
            'audio_path' => 'meetings/'.$team->id.'/otra.mp3',
            'language' => 'es',
            'status' => MeetingTranscriptStatus::Pending,
        ]);
        Http::fake([
            'api.elevenlabs.io/*' => Http::response([
                'text' => 'Hola',
                'words' => [
                    ['text' => 'Hola', 'type' => 'word', 'speaker_id' => 'spk_diego', 'start' => 0, 'end' => 1],
                ],
            ]),
        ]);

        (new TranscribeMeetingTranscript($meeting->id))->handle(app(MeetingTranscriptService::class));

        $this->assertSame($contact->id, $meeting->fresh()->turns[0]['contact_id']);
    }

    public function test_summary_is_stored_and_counted_for_the_team(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $meeting = MeetingTranscript::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'title' => 'Weekly',
            'original_filename' => 'reunion.webm',
            'audio_path' => 'meetings/'.$team->id.'/reunion.webm',
            'language' => 'es',
            'status' => MeetingTranscriptStatus::Ready,
            'turns' => [[
                'speaker' => 'speaker_0',
                'label' => 'Persona 1',
                'text' => 'Hay que publicar el precio mañana.',
                'start_seconds' => 0,
                'end_seconds' => 2,
            ]],
        ]);

        TaskStatus::query()->create([
            'name' => 'TO_DO',
            'label_class' => 'bg-label-secondary',
            'order' => 1,
        ]);

        $payload = json_encode([
            'minutes' => 'Quedó pendiente publicar el precio.',
            'points' => [[
                'topic' => 'Publicar el precio',
                'agree' => ['Persona 1'],
                'disagree' => [],
            ]],
            'conclusions' => [[
                'title' => 'Publicar el precio',
                'owner' => 'Persona 1',
                'detail' => 'Quedó para mañana.',
            ]],
            'opinions' => [[
                'speaker' => 'Persona 1',
                'text' => 'Conviene publicarlo.',
            ]],
            'opinion' => 'Publicar el precio antes de seguir.',
            'follow_up' => [
                'suggest' => false,
                'reason' => 'Alcanza con publicar el precio.',
                'after' => ['Publicar el precio'],
            ],
        ], JSON_UNESCAPED_UNICODE);
        Ai::fakeAgent(AnonymousAgent::class, [$payload, $payload]);

        $this->actingAs($user)
            ->followingRedirects()
            ->post(route('meeting.summary', $meeting))
            ->assertOk()
            ->assertSee('ti-check', false)
            ->assertSee('El resumen quedó guardado.', false)
            ->assertSee('Quedó pendiente publicar el precio.', false)
            ->assertSee('De acuerdo: Persona 1', false)
            ->assertSee('Persona 1.', false)
            ->assertSee('Conviene publicarlo.', false)
            ->assertSee('Publicar el precio antes de seguir.', false)
            ->assertSee('No hace falta otra reunión por ahora.', false)
            ->assertSee('Enlace público', false);

        $summary = $meeting->fresh()->summary;
        $task = Task::withoutGlobalScopes()->where('team_id', $team->id)->first();
        $this->assertNotNull($task);
        $this->assertSame('Publicar el precio', $task->title);
        $this->assertSame($user->id, $task->responsible_id);
        $this->assertSame($task->id, $summary['conclusions'][0]['task_id']);
        $this->assertSame('Quedó pendiente publicar el precio.', $summary['minutes']);
        $this->assertSame('Quedó pendiente publicar el precio.', $summary['synthesis']);
        $this->assertSame('Publicar el precio', $summary['tasks'][0]['title']);

        auth()->logout();
        $this->get(route('meeting.public', $meeting->fresh()->public_token))
            ->assertOk()
            ->assertSee('Hay que publicar el precio mañana.', false)
            ->assertSee('Quedó pendiente publicar el precio.', false)
            ->assertSee('De acuerdo: Persona 1', false);

        $this->actingAs($user)
            ->followingRedirects()
            ->post(route('meeting.summary', $meeting))
            ->assertOk();
        $this->assertSame(1, Task::withoutGlobalScopes()->where('team_id', $team->id)->count());
        $this->assertSame(2, TokenUsageLog::withoutGlobalScopes()->where('team_id', $team->id)->where('service', 'MeetingSummaryService')->count());

        $this->actingAs($user)
            ->get(route('app-calendar-events', [
                'start' => now()->subDay()->toIso8601String(),
                'end' => now()->addDay()->toIso8601String(),
            ]))
            ->assertOk()
            ->assertJsonFragment(['title' => 'Weekly']);
        $this->assertNotNull(CalendarEvent::query()->find($meeting->fresh()->calendar_event_id));
    }

    public function test_list_actions_open_the_detail_and_the_rename_form(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->withPersonalTeam()->create();
        $user->assignRole('admin');
        $team = $user->ownedTeams()->first();
        $user->forceFill(['current_team_id' => $team->id])->save();

        $meeting = MeetingTranscript::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'title' => 'Weekly',
            'original_filename' => 'reunion.webm',
            'audio_path' => 'meetings/'.$team->id.'/reunion.webm',
            'language' => 'es',
            'status' => MeetingTranscriptStatus::Ready,
        ]);

        $actions = view('meeting.action', ['meeting' => $meeting])->render();
        $this->assertStringContainsString('ti-eye', $actions);
        $this->assertStringContainsString(route('meeting.show', $meeting), $actions);
        $this->assertStringContainsString(route('meeting.edit', $meeting), $actions);
        $this->assertStringNotContainsString('ti-trash', $actions);

        $this->actingAs($user)
            ->get(route('meeting.edit', $meeting))
            ->assertOk()
            ->assertSee('Indicá un título para la reunión.', false);

        $this->actingAs($user)
            ->put(route('meeting.update', $meeting), ['title' => 'Reunión semanal'])
            ->assertRedirect(route('meeting.show', $meeting));

        $this->assertSame('Reunión semanal', $meeting->fresh()->title);
        $this->assertSame('Reunión semanal', CalendarEvent::query()->find($meeting->fresh()->calendar_event_id)?->title);
    }
}
