<?php

namespace App\Livewire\Profile;

use App\Jobs\EnrollUserVoiceSample;
use App\Services\MeetingSpeakerLibrary;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm as JetstreamUpdateProfileInformationForm;

class UpdateProfileInformationForm extends JetstreamUpdateProfileInformationForm
{
    /**
     * The new voice sample for the user.
     *
     * @var mixed
     */
    public $voiceSample;

    /**
     * @return \Illuminate\Http\RedirectResponse|null
     */
    public function updateProfileInformation(UpdatesUserProfileInformation $updater)
    {
        $this->resetErrorBag();

        $input = $this->state;
        if ($this->photo)
        {
            $input['photo'] = $this->photo;
        }
        if ($this->voiceSample)
        {
            $input['voice_sample'] = $this->voiceSample;
        }

        $updater->update(Auth::user(), $input);

        if (isset($this->photo) || isset($this->voiceSample))
        {
            return redirect()->route('profile.show');
        }

        $this->dispatch('saved');
        $this->dispatch('refresh-navigation-menu');
    }

    public function saveVoiceSample(): mixed
    {
        $this->resetErrorBag();

        $this->validate([
            'voiceSample' => ['required', 'file', 'mimes:mp3,wav,m4a,mp4,webm,ogg,mpeg', 'max:10240'],
        ], [
            'voiceSample.required' => __('Choose a voice sample.'),
            'voiceSample.file' => __('The voice sample must be an audio file.'),
            'voiceSample.mimes' => __('Use MP3, WAV, M4A, MP4, WebM or OGG.'),
            'voiceSample.max' => __('The voice sample cannot be larger than 10 MB.'),
        ]);

        $user = Auth::user();
        $user->updateVoiceSample($this->voiceSample);
        EnrollUserVoiceSample::dispatch($user->id);
        $this->voiceSample = null;

        return redirect()->route('profile.show');
    }

    public function deleteVoiceSample(MeetingSpeakerLibrary $library): void
    {
        $user = Auth::user();
        $library->forgetUser($user);
        $user->deleteVoiceSample();
    }
}
