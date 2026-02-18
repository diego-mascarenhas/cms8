@extends('layouts.layoutMaster')

@php
$breadcrumbs = [['link' => 'home', 'name' => 'Home'], ['name' => 'Team Settings']];
@endphp

@section('title', 'Team Settings')

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
