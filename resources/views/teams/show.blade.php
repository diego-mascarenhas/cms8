@extends('layouts.layoutMaster')

@php
$breadcrumbs = [['link' => 'home', 'name' => 'Home'], ['name' => 'Team Settings']];
@endphp

@section('title', 'Team Settings')

@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

@section('vendor-script')
  <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-script')
<script>
  function changeTeamMemberPassword(userId) {
    Swal.fire({
      title: @json(__('Change Password')),
      html: '<div class="text-start">' +
        '<label class="form-label" for="team-member-new-password">' + @json(__('New Password')) + '</label>' +
        '<input id="team-member-new-password" type="password" class="form-control mb-3" autocomplete="new-password">' +
        '<label class="form-label" for="team-member-new-password-confirmation">' + @json(__('Confirm Password')) + '</label>' +
        '<input id="team-member-new-password-confirmation" type="password" class="form-control" autocomplete="new-password">' +
        '</div>',
      showCancelButton: true,
      confirmButtonText: @json(__('Save')),
      cancelButtonText: @json(__('Cancel')),
      buttonsStyling: false,
      customClass: {
        confirmButton: 'btn btn-primary me-2 waves-effect waves-light',
        cancelButton: 'btn btn-label-secondary waves-effect waves-light'
      },
      preConfirm: () => {
        const password = document.getElementById('team-member-new-password').value;
        const confirmation = document.getElementById('team-member-new-password-confirmation').value;

        if (!password || password.length < 8) {
          Swal.showValidationMessage(@json(__('The password must be at least 8 characters.')));
          return false;
        }

        if (password !== confirmation) {
          Swal.showValidationMessage(@json(__('The passwords do not match.')));
          return false;
        }

        return {
          password: password,
          password_confirmation: confirmation
        };
      }
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }

      const root = document.getElementById('team-member-manager');
      const componentId = root ? root.getAttribute('wire:id') : null;
      if (!componentId || typeof Livewire === 'undefined') {
        return;
      }

      Livewire.find(componentId).call(
        'updateMemberPassword',
        userId,
        result.value.password,
        result.value.password_confirmation
      ).then(() => {
        Swal.fire({
          icon: 'success',
          title: @json(__('Password updated')),
          text: @json(__('The team member password was updated.')),
          timer: 2000,
          showConfirmButton: false
        });
      }).catch((error) => {
        Swal.fire({
          icon: 'error',
          title: @json(__('Error')),
          text: (error && error.message) ? error.message : @json(__('An error occurred while changing the password.')),
        });
      });
    });
  }
</script>
@endsection

@section('content')
  @if (session('success'))
    <div class="alert alert-success alert-dismissible mb-4" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif
  @if (session('error'))
    <div class="alert alert-danger alert-dismissible mb-4" role="alert">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger alert-dismissible mb-4" role="alert">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif
  <div class="mb-4">
    @livewire('teams.update-team-name-form', ['team' => $team])
  </div>

  @livewire('teams.team-member-manager', ['team' => $team])


  @if (Gate::check('delete', $team) && !$team->personal_team)

  <div class="mt-4">
    @livewire('teams.delete-team-form', ['team' => $team])
  </div>
  @endif
@endsection
