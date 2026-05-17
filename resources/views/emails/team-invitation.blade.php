@extends('emails.layouts.humano')

@section('title', __('Team Invitation'))

@section('content')
    <h1>{{ __('Team Invitation') }}</h1>

    <p class="center">{{ __('You have been invited to join the :team team!', ['team' => $invitation->team->name]) }}</p>

    <p class="center">{{ __('Click the button below to create your account or sign in and join the team.') }}</p>

    <div class="btn-wrap">
        <a href="{{ $acceptUrl }}" class="btn">{{ __('Accept Invitation') }}</a>
    </div>

    <p class="muted" style="margin-top: 20px;">{{ __('If you did not expect to receive an invitation to this team, you may discard this email.') }}</p>
@endsection
