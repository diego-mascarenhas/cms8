<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiAuthProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploading_a_profile_photo_requires_authentication(): void
    {
        $this->post('/api/auth/profile-photo', [
            'photo' => UploadedFile::fake()->image('avatar.jpg', 80, 80),
        ], ['Accept' => 'application/json'])->assertStatus(401);
    }

    public function test_the_user_can_upload_a_profile_photo(): void
    {
        Storage::fake('public');
        [$user, $token] = $this->signedIn();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/auth/profile-photo', [
                'photo' => UploadedFile::fake()->image('avatar.jpg', 80, 80),
            ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $user->refresh();
        $this->assertNotEmpty($user->profile_photo_path);
        Storage::disk('public')->assertExists($user->profile_photo_path);
        $photoUrl = (string) $response->json('user.profile_photo_url');
        $this->assertStringContainsString('/storage/'.$user->profile_photo_path, $photoUrl);
        $this->assertStringStartsWith(url('/storage/'), $photoUrl);
    }

    public function test_a_large_photo_is_accepted(): void
    {
        Storage::fake('public');
        [$user, $token] = $this->signedIn();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/auth/profile-photo', [
                'photo' => UploadedFile::fake()->image('avatar.jpg', 2400, 1800),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($user->fresh()->profile_photo_path);
    }

    public function test_a_non_image_is_refused(): void
    {
        Storage::fake('public');
        [, $token] = $this->signedIn();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/auth/profile-photo', [
                'photo' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_the_user_can_download_the_profile_photo(): void
    {
        Storage::fake('public');
        [$user, $token] = $this->signedIn();
        $path = UploadedFile::fake()->image('avatar.jpg', 80, 80)->store('profile-photos', 'public');
        $user->forceFill(['profile_photo_path' => $path])->save();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'image/*',
        ])->get('/api/auth/profile-photo')->assertOk();
    }

    public function test_downloading_without_a_photo_is_empty(): void
    {
        [, $token] = $this->signedIn();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'image/*',
        ])->get('/api/auth/profile-photo')->assertNoContent();
    }

    public function test_the_user_can_delete_the_profile_photo(): void
    {
        Storage::fake('public');
        [$user, $token] = $this->signedIn();
        $path = UploadedFile::fake()->image('avatar.jpg', 80, 80)->store('profile-photos', 'public');
        $user->forceFill(['profile_photo_path' => $path])->save();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/auth/profile-photo')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.profile_photo_url', null);

        $this->assertNull($user->fresh()->profile_photo_path);
        Storage::disk('public')->assertMissing($path);
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function signedIn(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('profile-photo')->plainTextToken;

        return [$user, $token];
    }
}
