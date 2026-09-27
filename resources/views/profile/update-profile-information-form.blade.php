<x-form-section submit="updateProfileInformation">
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

        <div class="mt-2" x-show="! photoPreview">
          <img src="{{ $this->user->profile_photo_url }}" class="rounded-circle" width="80" height="80" style="object-fit: cover;" alt="{{ $this->user->name }}">
        </div>

        <div class="mt-2" x-show="photoPreview">
          <img x-bind:src="photoPreview" class="rounded-circle" width="80" height="80" style="object-fit: cover;" alt="">
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

    @if (auth()->user()->currentTeam)
      <div class="mb-3">
        <label class="form-label">{{ __('Mis casillas personales') }}</label>
        <div>
          <a href="{{ route('team.mailboxes.index', auth()->user()->currentTeam) }}" class="btn btn-sm btn-outline-primary">
            <i class="ti ti-mail me-1"></i>{{ __('Configurar casillas IMAP') }}
          </a>
        </div>
        <div class="form-text">{{ __('app.profile_personal_mailboxes_help') }}</div>
      </div>
    @endif
  </x-slot>

  <x-slot name="actions">
    <div class="d-flex align-items-baseline">
      <x-button>
        {{ __('Save') }}
      </x-button>
    </div>
  </x-slot>
</x-form-section>
