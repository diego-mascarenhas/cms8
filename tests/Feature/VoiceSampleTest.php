<?php

namespace Tests\Feature;

use App\Livewire\Profile\UpdateProfileInformationForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class VoiceSampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_profile_form_offers_a_voice_sample_next_to_the_photo(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(UpdateProfileInformationForm::class)
            ->assertSee(__('Voice sample'), false)
            ->assertSee(__('Select a voice sample'), false)
            ->assertSee(__('Record voice'), false)
            ->assertSee(__('Recommended text'), false)
            ->assertSee(__('Hello, this is my voice. I am recording this sample so meetings can recognize me. The sky is clear, the coffee is hot, and the meeting starts at ten. I prefer to speak clearly, without rushing, and to say the numbers slowly: one, two, three, four, five, fifteen, forty-two. If you can hear me well, you can already tell my voice apart from everyone else in the room.'), false)
            ->assertSee('ti-microphone', false)
            ->assertSee('ti-user', false);
    }

    public function test_saving_a_voice_sample_stores_it_and_enrolls_the_speaker(): void
    {
        Storage::fake('local');
        config(['ai.providers.eleven.key' => 'test-key']);
        Http::fake(function ($request)
        {
            if ($request->method() === 'DELETE')
            {
                return Http::response('', 404);
            }

            return Http::response(['speaker_id' => 'spk_ready'], 200);
        });
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        $this->actingAs($user);

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('voiceSample', UploadedFile::fake()->createWithContent('voz.mp3', 'voice-sample-bytes'))
            ->call('saveVoiceSample')
            ->assertRedirect(route('profile.show'));

        $user->refresh();
        $this->assertNotEmpty($user->voice_sample_path);
        $this->assertSame('spk_ready', $user->voice_speaker_id);
        Storage::disk('local')->assertExists($user->voice_sample_path);
        Http::assertSent(function ($request) use ($user)
        {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.elevenlabs.io/v1/speech-to-text/speakers'
                && str_contains($request->body(), 'user-'.$user->id)
                && str_contains($request->body(), 'Ada Lovelace');
        });

        $this->actingAs($user)
            ->get(route('profile.voice-sample'))
            ->assertOk();
    }

    public function test_removing_the_voice_sample_forgets_the_enrolled_speaker(): void
    {
        Storage::fake('local');
        config(['ai.providers.eleven.key' => 'test-key']);
        Http::fake([
            'api.elevenlabs.io/*' => Http::response('', 204),
        ]);
        $user = User::factory()->create();
        $path = UploadedFile::fake()->create('voz.mp3', 40, 'audio/mpeg')->store('voice-samples/'.$user->id, 'local');
        $user->forceFill([
            'voice_sample_path' => $path,
            'voice_speaker_id' => 'spk_ready',
        ])->save();
        $this->actingAs($user);

        Livewire::test(UpdateProfileInformationForm::class)
            ->call('deleteVoiceSample');

        $user->refresh();
        $this->assertNull($user->voice_sample_path);
        $this->assertNull($user->voice_speaker_id);
        Storage::disk('local')->assertMissing($path);
        Http::assertSent(function ($request)
        {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), '/v1/speech-to-text/speakers/spk_ready');
        });
    }

    public function test_a_non_audio_file_is_refused(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('voiceSample', UploadedFile::fake()->create('notas.txt', 10, 'text/plain'))
            ->call('updateProfileInformation')
            ->assertHasErrors(['voice_sample']);
    }

    public function test_the_api_uploads_and_removes_a_voice_sample(): void
    {
        Storage::fake('local');
        config(['ai.providers.eleven.key' => 'test-key']);
        Http::fake([
            'api.elevenlabs.io/*' => Http::response(['speaker_id' => 'spk_api'], 200),
        ]);
        $user = User::factory()->create();
        $token = $user->createToken('voice')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/auth/voice-sample', [
                'voice_sample' => UploadedFile::fake()->createWithContent('voz.mp3', 'voice-sample-bytes'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.has_voice_sample', true)
            ->assertJsonPath('user.voice_sample_ready', true);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/auth/voice-sample')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/auth/voice-sample')
            ->assertOk()
            ->assertJsonPath('user.has_voice_sample', false);

        $this->assertNull($user->fresh()->voice_sample_path);
    }

    public function test_voice_sample_routes_require_authentication(): void
    {
        $this->post('/api/auth/voice-sample', [
            'voice_sample' => UploadedFile::fake()->create('voz.mp3', 10, 'audio/mpeg'),
        ], ['Accept' => 'application/json'])->assertStatus(401);

        $this->get(route('profile.voice-sample'))->assertRedirect();
    }
}
