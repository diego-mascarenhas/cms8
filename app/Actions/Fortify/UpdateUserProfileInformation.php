<?php

namespace App\Actions\Fortify;

use App\Jobs\EnrollUserVoiceSample;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[+\-\d\s()]*$/'],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
            'voice_sample' => ['nullable', 'file', 'mimes:mp3,wav,m4a,mp4,webm,ogg,mpeg', 'max:10240'],
        ])->validateWithBag('updateProfileInformation');

        if (isset($input['photo']))
        {
            $user->updateProfilePhoto($input['photo']);
        }

        if (isset($input['voice_sample']))
        {
            $user->updateVoiceSample($input['voice_sample']);
            EnrollUserVoiceSample::dispatch($user->id);
        }

        $phoneDigits = $this->normalizePhoneDigits($input['phone'] ?? null);

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail)
        {
            $this->updateVerifiedUser($user, $input, $phoneDigits);
        } else
        {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
                'phone' => $phoneDigits,
            ])->save();
        }
    }

    /**
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input, ?int $phoneDigits): void
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $phoneDigits,
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }

    private function normalizePhoneDigits(mixed $phone): ?int
    {
        $digits = preg_replace('/\D+/', '', (string) ($phone ?? ''));

        return is_string($digits) && $digits !== '' ? (int) $digits : null;
    }
}
