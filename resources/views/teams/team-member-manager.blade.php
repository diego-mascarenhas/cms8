<div id="team-member-manager">
  @if (Gate::check('addTeamMember', $team))

    <!-- Add Team Member -->
    <x-form-section id="add-team-member" submit="addTeamMember">
      <x-slot name="title">
        {{ __('Add Team Member') }}
      </x-slot>

      <x-slot name="description">
        {{ __('Add a new team member to your team, allowing them to collaborate with you.') }}
      </x-slot>

      <x-slot name="form">
        <x-action-message on="saved">
          {{ __('Added.') }}
        </x-action-message>

        <div class="mb-3">
          {{ __('Please provide the email address of the person you would like to add to this team. The email address must be associated with an existing account.') }}
        </div>

        <!-- Member Email -->
        <div class="mb-3">
          <x-label class="form-label" for="email" value="{{ __('Email') }}" />
          <x-input id="name" type="email" class="{{ $errors->has('email') ? 'is-invalid' : '' }}"
            wire:model="addTeamMemberForm.email" />
          <x-input-error for="email" />
        </div>

        <!-- Role -->
        @if (count($this->roles) > 0)
          <div class="my-3">
            <div class="mb-3">
              <x-label class="fw-medium" for="role" value="{{ __('Role') }}" />

              <input type="hidden" class="{{ $errors->has('role') ? 'is-invalid' : '' }}">
              <x-input-error for="role" />
            </div>

            {{-- Show all system roles (Spatie) as reference --}}
            @php
              $allSpatieRoles = \Spatie\Permission\Models\Role::pluck('name');
            @endphp
            @if($allSpatieRoles->count())
              <div class="mb-2 small text-muted">
                {{ __('Available system roles:') }}
                @foreach($allSpatieRoles as $r)
                  <span class="badge bg-label-secondary me-1">{{ ucfirst($r) }}</span>
                @endforeach
              </div>
            @endif

            <div class="list-group">
              @foreach ($this->roles as $index => $role)
                <a href="#" class="list-group-item list-group-item-action"
                  wire:click.prevent="$set('addTeamMemberForm.role', '{{ $role->key }}')">
                  <div>
                    <span class="{{ $addTeamMemberForm['role'] == $role->key ? 'fw-medium' : '' }}">
                      {{ $role->name }}
                    </span>
                    @if ($addTeamMemberForm['role'] == $role->key)
                      <svg class="ms-25 text-success fw-light" width="20" fill="none" stroke-linecap="round"
                        stroke-linejoin="round" stroke-width="2" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                      </svg>
                    @endif
                  </div>

                  <!-- Role Description -->
                  <div class="mt-2">
                    {{ $role->description }}
                  </div>
                </a>
              @endforeach
            </div>
          </div>
        @endif
      </x-slot>

      <x-slot name="actions">
        <x-button>
          {{ __('Add') }}
        </x-button>
      </x-slot>
    </x-form-section>
  @endif

  @if ($team->teamInvitations->isNotEmpty() && Gate::check('addTeamMember', $team))

    <!-- Team Member Invitations -->
    <div class="mt-4">
      <x-action-section>
      <x-slot name="title">
        {{ __('Pending Team Invitations') }}
      </x-slot>

      <x-slot name="description">
        {{ __('These people have been invited to your team and have been sent an invitation email. They may join the team by accepting the email invitation.') }}
      </x-slot>

      <x-slot name="content">
        @foreach ($team->teamInvitations as $invitation)
          <div class="d-flex align-items-center justify-content-between mt-2 mb-2">
            <div class="align-self-center">{{ $invitation->email }}</div>

            <div class="d-flex align-items-center gap-2">
              @if (Gate::check('addTeamMember', $team))
                <form action="{{ route('teams.invitations.confirm', $invitation) }}" method="POST" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-primary">
                    {{ __('Confirm invitation') }}
                  </button>
                </form>
              @endif
              @if (Gate::check('removeTeamMember', $team))
                <button class="btn btn-link text-danger text-decoration-none btn-sm"
                  wire:click="cancelTeamInvitation({{ $invitation->id }})">
                  {{ __('Cancel') }}
                </button>
              @endif
            </div>
          </div>
        @endforeach
      </x-slot>
    </x-action-section>
    </div>
  @endif

  @php
    $teamMembers = $this->filteredMembers;
  @endphp

  @if ($this->totalMembersCount > 0)

    <div class="mt-4">
      <!-- Manage Team Members -->
    <x-action-section id="team-members">
      <x-slot name="title">
        {{ __('Team Members') }}
      </x-slot>

      <x-slot name="description">
        {{ __('All of the people that are part of this team.') }}
      </x-slot>

      <!-- Team Member List -->
      <x-slot name="content">
        <!-- Search & Role Filter -->
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
          <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 320px;">
            <div class="position-relative w-100">
              <input type="text" id="member-search" class="form-control form-control-sm" style="padding-right: 2rem;"
                placeholder="{{ __('Search') }}..." wire:model.live.debounce.300ms="search">
              <i class="ti ti-search position-absolute" style="right: .6rem; top: 50%; transform: translateY(-50%);"></i>
            </div>
            <div wire:loading wire:target="search" class="spinner-border spinner-border-sm text-primary" role="status">
              <span class="visually-hidden">{{ __('Loading...') }}</span>
            </div>
          </div>

          <div class="d-flex align-items-center gap-2">
            <label for="member-role-filter" class="form-label mb-0 text-muted small">{{ __('Filter by role') }}</label>
            <select id="member-role-filter" class="form-select form-select-sm" style="width: auto;"
              wire:model.live="roleFilter">
              <option value="all">{{ __('All roles') }}</option>
              @foreach ($this->roleFilterOptions as $roleOption)
                <option value="{{ $roleOption->key }}">{{ $roleOption->name }}</option>
              @endforeach
            </select>
            <div wire:loading wire:target="roleFilter" class="spinner-border spinner-border-sm text-primary" role="status">
              <span class="visually-hidden">{{ __('Loading...') }}</span>
            </div>
          </div>
        </div>

        <div wire:loading.remove wire:target="roleFilter, search">
          @if ($teamMembers->isEmpty())
            <div class="text-muted text-center py-3">
              {{ __('No members found.') }}
            </div>
          @endif

          @foreach ($teamMembers as $user)
          <div class="d-flex justify-content-between mt-2 mb-2" wire:key="member-{{ $user->id }}-{{ optional($user->membership)->role }}">
            <div class="d-flex align-items-center">
              <div class="pe-2">
                <img class="rounded-circle" width="32" height="32" style="object-fit: cover;" src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}">
              </div>
              <span class="fw-medium">{{ $user->email ? $user->name.' ('.$user->email.')' : $user->name }}</span>
            </div>

            <div class="d-flex align-items-center">
              @php
                $roleKey = optional($user->membership)->role;
                $roleObj = $roleKey ? Laravel\Jetstream\Jetstream::findRole($roleKey) : null;
                $roleName = $roleObj->name ?? __('Member');
              @endphp
              @if (Gate::check('updateTeamMember', $team))
                <a href="javascript:;" class="text-body me-2" title="{{ __('Change Password') }}"
                  onclick="changeTeamMemberPassword({{ $user->id }})">
                  <i class="ti ti-key ti-sm"></i>
                </a>
              @endif
              @if (Gate::check('addTeamMember', $team) && Laravel\Jetstream\Jetstream::hasRoles())
                <button class="btn btn-link text-secondary" wire:click="manageRole({{ $user->id }})">
                  {{ $roleName }}
                </button>
              @elseif (Laravel\Jetstream\Jetstream::hasRoles())
                <button class="btn btn-link text-secondary disabled text-decoration-none ms-2">
                  {{ $roleName }}
                </button>
              @endif

              @if ($this->user->id === $user->id)
                <button class="btn btn-link text-danger text-decoration-none"
                  wire:click="$toggle('confirmingLeavingTeam')">
                  {{ __('Leave') }}
                </button>
              @elseif (Gate::check('removeTeamMember', $team))
                <button class="btn btn-link text-danger text-decoration-none"
                  wire:click="confirmTeamMemberRemoval('{{ $user->id }}')">
                  {{ __('Remove') }}
                </button>
              @endif

              {{-- Badges for all Spatie roles the user has (hidden) --}}
              @php($userSpatieRoles = method_exists($user, 'getRoleNames') ? $user->getRoleNames() : collect())
              @if ($userSpatieRoles->count())
                <div class="ms-3 align-self-center d-none">
                  @foreach ($userSpatieRoles as $sr)
                    <span class="badge bg-label-primary me-1">{{ ucfirst($sr) }}</span>
                  @endforeach
                </div>
              @endif
            </div>
          </div>
          @endforeach
        </div>
      </x-slot>
    </x-action-section>
    </div>
  @endif

  <!-- Role Management Modal -->
  <x-dialog-modal wire:model="currentlyManagingRole">
    <x-slot name="title">
      {{ __('Manage Role') }}
    </x-slot>

    <x-slot name="content">
      @error('role')
        <div class="alert alert-danger py-2 mb-3">{{ $message }}</div>
      @enderror

      <div class="list-group">
        @foreach ($this->roles as $index => $role)
          <a href="#" class="list-group-item list-group-item-action"
            wire:click.prevent="$set('currentRole', '{{ $role->key }}')">
            <div>
              <span class="{{ $currentRole == $role->key ? 'fw-medium' : '' }}">
                {{ $role->name }}
              </span>
              @if ($currentRole == $role->key)
                <svg class="ms-25 text-success fw-light" width="20" fill="none" stroke-linecap="round"
                  stroke-linejoin="round" stroke-width="2" stroke="currentColor" viewBox="0 0 24 24">
                  <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
              @endif
            </div>

            <!-- Role Description -->
            <div class="mt-2">
              {{ $role->description }}
            </div>
          </a>
        @endforeach
      </div>
    </x-slot>

    <x-slot name="footer">
      <x-secondary-button wire:click="stopManagingRole" wire:loading.attr="disabled">
        {{ __('Cancel') }}
      </x-secondary-button>

      <x-button type="button" class="ms-2" wire:click.prevent="updateRole" wire:loading.attr="disabled" wire:target="updateRole">
        {{ __('Save') }}
      </x-button>
    </x-slot>
  </x-dialog-modal>

  <!-- Leave Team Confirmation Modal -->
  <x-confirmation-modal wire:model.live="confirmingLeavingTeam">
    <x-slot name="title">
      {{ __('Leave Team') }}
    </x-slot>

    <x-slot name="content">
      {{ __('Are you sure you would like to leave this team?') }}
    </x-slot>

    <x-slot name="footer">
      <x-secondary-button wire:click="$toggle('confirmingLeavingTeam')" wire:loading.attr="disabled">
        {{ __('Cancel') }}
      </x-secondary-button>

      <x-danger-button class="ms-2" wire:click="leaveTeam" wire:loading.attr="disabled">
        {{ __('Leave') }}
      </x-danger-button>
    </x-slot>
  </x-confirmation-modal>

  <!-- Remove Team Member Confirmation Modal -->
  <x-confirmation-modal wire:model.live="confirmingTeamMemberRemoval">
    <x-slot name="title">
      {{ __('Remove Team Member') }}
    </x-slot>

    <x-slot name="content">
      {{ __('Are you sure you would like to remove this person from the team?') }}
    </x-slot>

    <x-slot name="footer">
      <x-secondary-button wire:click="$toggle('confirmingTeamMemberRemoval')" wire:loading.attr="disabled">
        {{ __('Cancel') }}
      </x-secondary-button>

      <x-danger-button class="ms-2" wire:click="removeTeamMember" wire:loading.attr="disabled">
        {{ __('Remove') }}
      </x-danger-button>
    </x-slot>
  </x-confirmation-modal>
</div>
