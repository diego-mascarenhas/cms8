<?php

namespace Tests\Feature;

use App\Enums\MeetingTranscriptStatus;
use App\Jobs\TranscribeMeetingTranscript;
use App\Models\Contact;
use App\Models\MeetingTranscript;
use App\Models\User;
use App\Services\MeetingTranscriptService;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TranscriptionSegment;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\TranscriptionResponse;
use Laravel\Ai\Transcription;
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
    }

    public function test_missing_transcription_permission_is_explained(): void
    {
        $response = new Response(new PsrResponse(401, ['Content-Type' => 'application/json'], json_encode([
            'detail' => [
                'message' => 'The API key you used is missing the permission speech_to_text to execute this operation.',
            ],
        ])));

        $message = (new MeetingTranscriptService)->failureMessage(new RequestException($response));

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
            ->assertSee('Vincular', false)
            ->assertSee('no la reconoce sola', false);
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

        Transcription::fake([
            new TranscriptionResponse(
                'Hola equipo Listo',
                collect([
                    new TranscriptionSegment('Hola', 'speaker_0', 0, 1),
                    new TranscriptionSegment('equipo', 'speaker_0', 1, 2),
                    new TranscriptionSegment('Listo', 'speaker_1', 2, 3),
                ]),
                new Usage,
                new Meta('eleven', 'scribe_v2'),
            ),
        ]);

        (new TranscribeMeetingTranscript($meeting->id))->handle(app(MeetingTranscriptService::class));

        $meeting->refresh();
        $this->assertSame(MeetingTranscriptStatus::Ready, $meeting->status);
        $this->assertSame('Persona 1', $meeting->turns[0]['label']);
        $this->assertSame('Hola equipo', $meeting->turns[0]['text']);
        $this->assertSame('Persona 2', $meeting->turns[1]['label']);
        $this->assertSame('Listo', $meeting->turns[1]['text']);
        Transcription::assertGenerated(function ($prompt)
        {
            return $prompt->diarize === true && $prompt->language === 'es';
        });
    }
}
