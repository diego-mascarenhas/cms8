<div>
  <x-form-section icon="ti-user" submit="updateProfileInformation">
    <x-slot name="title">
      {{ __('Profile Information') }}
    </x-slot>

    <x-slot name="description">
      {{ __('app.profile_channels_description') }}
    </x-slot>

    <x-slot name="form">

      <x-action-message on="saved">
        {{ __('Saved.') }}
      </x-action-message>

      <!-- Profile Photo -->
      @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
        <div class="mb-3" x-data="{photoName: null, photoPreview: null}">
          <input type="file" hidden wire:model.live="photo" x-ref="photo"
            x-on:change=" photoName = $refs.photo.files[0].name; const reader = new FileReader(); reader.onload = (e) => { photoPreview = e.target.result;}; reader.readAsDataURL($refs.photo.files[0]);" />

          <div
            class="mt-2 rounded-circle flex-shrink-0"
            style="width: 80px; height: 80px; background-color: var(--bs-secondary-bg, #e8e2d4); background-image: url('{{ $this->user->profile_photo_url }}'); background-repeat: no-repeat; background-position: center; background-size: cover;"
            role="img"
            aria-label="{{ $this->user->name }}"
            x-show="! photoPreview"
          ></div>

          <div class="mt-2 rounded-circle overflow-hidden flex-shrink-0" style="width: 80px; height: 80px;" x-show="photoPreview">
            <img x-bind:src="photoPreview" alt="" width="80" height="80" style="width: 80px; height: 80px; max-width: none; object-fit: cover;">
          </div>

          <x-secondary-button class="mt-2 me-2" type="button" x-on:click.prevent="$refs.photo.click()">
            {{ __('Select A New Photo') }}
          </x-secondary-button>

          @if ($this->user->profile_photo_path)
            <button type="button" class="btn btn-danger text-uppercase mt-2" wire:click="deleteProfilePhoto">
              {{ __('Remove Photo') }}
            </button>
          @endif

          <x-input-error for="photo" class="mt-2" />
        </div>
      @endif

      <div class="mb-3">
        <x-label class="form-label" for="name" value="{{ __('Name') }}" />
        <x-input id="name" type="text" class="{{ $errors->has('name') ? 'is-invalid' : '' }}"
          wire:model="state.name" autocomplete="name" />
        <x-input-error for="name" />
      </div>

      <div class="mb-3">
        <x-label class="form-label" for="email" value="{{ __('Email') }}" />
        <x-input id="email" type="email" class="{{ $errors->has('email') ? 'is-invalid' : '' }}"
          wire:model="state.email" />
        <x-input-error for="email" />
        <div class="form-text">{{ __('app.profile_login_email_help') }}</div>
      </div>

      <div class="mb-3">
        <x-label class="form-label" for="phone" value="{{ __('app.profile_whatsapp') }}" />
        <x-input id="phone" type="tel" class="{{ $errors->has('phone') ? 'is-invalid' : '' }}"
          wire:model="state.phone" autocomplete="tel" placeholder="34600111222" />
        <x-input-error for="phone" />
        <div class="form-text">{{ __('app.profile_whatsapp_help') }}</div>
      </div>
    </x-slot>

    <x-slot name="actions">
      <div class="d-flex align-items-baseline">
        <x-button>
          {{ __('Save') }}
        </x-button>
      </div>
    </x-slot>
  </x-form-section>

  <x-form-section class="mt-4" icon="ti-microphone" submit="saveVoiceSample">
    <x-slot name="title">
      {{ __('Voice sample') }}
    </x-slot>

    <x-slot name="description">
      {{ __('Upload a short recording of your voice, between 10 and 60 seconds, speaking alone. Meetings use it to recognize you.') }}
    </x-slot>

    <x-slot name="form">
      <div
        x-data="voiceSampleRecorder"
        data-too-short="{{ __('The recording is too short. Read the text for at least 10 seconds.') }}"
        data-mic-denied="{{ __('Allow the microphone to record your voice.') }}"
        data-mic-unavailable="{{ __('This browser cannot record audio. Upload a file instead.') }}"
      >
        <div class="border rounded p-3 mb-3">
          <div class="fw-medium mb-2">{{ __('Recommended text') }}</div>
          <p class="mb-2">{{ __('Hello, this is my voice. I am recording this sample so meetings can recognize me. The sky is clear, the coffee is hot, and the meeting starts at ten. I prefer to speak clearly, without rushing, and to say the numbers slowly: one, two, three, four, five, fifteen, forty-two. If you can hear me well, you can already tell my voice apart from everyone else in the room.') }}</p>
          <div class="form-text mb-0">{{ __('Read it aloud in a quiet place, at your normal pace. Keep going until the timer passes 10 seconds. Recording stops at 60.') }}</div>
        </div>

        <input type="file" hidden wire:model="voiceSample" x-ref="voice"
          accept="audio/*,.mp3,.wav,.m4a,.mp4,.webm,.ogg"
          x-on:change="const file = $refs.voice.files[0]; if (! file) { voiceName = null; return } voiceName = file.name; clearPreview(); previewUrl = URL.createObjectURL(file)" />

        <div x-bind:class="{ 'd-none': previewUrl }">
          @if (isset($voiceSample) && $voiceSample)
            <div class="small text-muted mt-2">{{ $voiceSample->getClientOriginalName() }}</div>
          @elseif ($this->user->voice_sample_path)
            <audio controls preload="none" src="{{ route('profile.voice-sample') }}" class="d-block mt-2"></audio>
            @if ($this->user->voice_speaker_id)
              <div class="form-text">{{ __('Your voice is ready for meetings.') }}</div>
            @else
              <div class="form-text">{{ __('The sample is saved. Recognition will turn on when the voice service accepts it.') }}</div>
            @endif
          @endif
        </div>

        <div class="d-none" x-bind:class="{ 'd-none': ! previewUrl }">
          <audio x-bind:src="previewUrl" controls class="d-block mt-2"></audio>
        </div>
        <div class="small text-muted mt-2 d-none" x-bind:class="{ 'd-none': ! voiceName }" x-text="voiceName"></div>
        <div class="text-danger small mt-2 d-none" x-bind:class="{ 'd-none': ! message }" x-text="message"></div>
        <div class="form-text" wire:loading wire:target="voiceSample">{{ __('Preparing the sample…') }}</div>

        <div class="d-none" x-bind:class="{ 'd-none': ! recording }">
          <button type="button" class="btn btn-danger text-uppercase mt-2 me-2" x-on:click="stop()">
            <i class="ti ti-player-stop me-1"></i>{{ __('Stop recording') }}
            <span class="ms-1" x-text="clock()"></span>
          </button>
        </div>

        <div x-bind:class="{ 'd-none': recording }">
          <button type="button" class="btn btn-primary text-uppercase mt-2 me-2" x-on:click="start()">
            <i class="ti ti-microphone me-1"></i>{{ __('Record voice') }}
          </button>

          <x-secondary-button class="mt-2 me-2" type="button" x-on:click.prevent="$refs.voice.click()">
            {{ __('Select a voice sample') }}
          </x-secondary-button>
        </div>

        @if ($this->user->voice_sample_path)
          <button type="button" class="btn btn-danger text-uppercase mt-2" wire:click="deleteVoiceSample">
            {{ __('Remove voice sample') }}
          </button>
        @endif

        <x-input-error for="voiceSample" class="mt-2" />
        <x-input-error for="voice_sample" class="mt-2" />
      </div>
    </x-slot>

    <x-slot name="actions">
      <x-button wire:loading.attr="disabled" wire:target="saveVoiceSample,voiceSample">
        {{ __('Save voice sample') }}
      </x-button>
    </x-slot>
  </x-form-section>

  @if (auth()->user()->currentTeam)
    <x-action-section class="mt-4" icon="ti-mail">
      <x-slot name="title">
        {{ __('Mis casillas personales') }}
      </x-slot>

      <x-slot name="description">
        {{ __('app.profile_personal_mailboxes_help') }}
      </x-slot>

      <x-slot name="content">
        <a href="{{ route('team.mailboxes.index', auth()->user()->currentTeam) }}" class="btn btn-primary text-uppercase">
          <i class="ti ti-mail me-1"></i>{{ __('Configurar casillas IMAP') }}
        </a>
      </x-slot>
    </x-action-section>
  @endif
</div>

@script
<script>
  Alpine.data('voiceSampleRecorder', () => ({
    recording: false,
    seconds: 0,
    timer: null,
    recorder: null,
    chunks: [],
    stream: null,
    previewUrl: null,
    voiceName: null,
    message: '',
    async start() {
      this.message = ''
      this.clearPreview()
      if (!navigator.mediaDevices || typeof MediaRecorder === 'undefined') {
        this.message = this.$el.dataset.micUnavailable
        return
      }
      try {
        this.stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      } catch (error) {
        this.message = this.$el.dataset.micDenied
        return
      }
      const mime = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4'].find((type) => MediaRecorder.isTypeSupported(type)) || ''
      this.chunks = []
      this.recorder = mime !== '' ? new MediaRecorder(this.stream, { mimeType: mime }) : new MediaRecorder(this.stream)
      this.recorder.ondataavailable = (event) => {
        if (event.data && event.data.size > 0) {
          this.chunks.push(event.data)
        }
      }
      this.recorder.onstop = () => this.finish()
      this.recorder.start()
      this.recording = true
      this.seconds = 0
      this.timer = setInterval(() => {
        this.seconds += 1
        if (this.seconds >= 60) {
          this.stop()
        }
      }, 1000)
    },
    stop() {
      if (!this.recording) {
        return
      }
      this.recording = false
      if (this.timer) {
        clearInterval(this.timer)
        this.timer = null
      }
      if (this.recorder && this.recorder.state !== 'inactive') {
        this.recorder.stop()
      }
      if (this.stream) {
        this.stream.getTracks().forEach((track) => track.stop())
        this.stream = null
      }
    },
    finish() {
      const type = this.recorder && this.recorder.mimeType ? this.recorder.mimeType : 'audio/webm'
      const blob = new Blob(this.chunks, { type })
      if (this.seconds < 10 || blob.size === 0) {
        this.message = this.$el.dataset.tooShort
        return
      }
      const extension = type.includes('mp4') ? 'm4a' : 'webm'
      const file = new File([blob], `voice-sample.${extension}`, { type })
      this.previewUrl = URL.createObjectURL(blob)
      this.voiceName = file.name
      this.$wire.upload('voiceSample', file)
    },
    clearPreview() {
      if (this.previewUrl) {
        URL.revokeObjectURL(this.previewUrl)
        this.previewUrl = null
      }
    },
    clock() {
      const minutes = String(Math.floor(this.seconds / 60)).padStart(2, '0')
      const remain = String(this.seconds % 60).padStart(2, '0')
      return `${minutes}:${remain}`
    },
  }))
</script>
@endscript
