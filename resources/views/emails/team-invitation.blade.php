@extends('emails.layouts.humano')

@section('title', __('Team Invitation'))

@section('content')
    <h1>{{ __('Team Invitation') }}</h1>

    <p class="center">{{ __('You have been invited to join the :team team!', ['team' => $invitation->team->name]) }}</p>

    @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::registration()))
        <p>{{ __('If you do not have an account, you may create one by clicking the button below. After creating an account, you may click the invitation acceptance button in this email to accept the team invitation:') }}</p>

        <div class="btn-wrap">
            <a href="{{ route('register') }}" class="btn">{{ __('Create Account') }}</a>
        </div>

        <hr class="divider">

        <p>{{ __('If you already have an account, you may accept this invitation by clicking the button below:') }}</p>
    @else
        <p>{{ __('You may accept this invitation by clicking the button below:') }}</p>
    @endif

    <div class="btn-wrap">
        <a href="{{ $acceptUrl }}" class="btn btn-secondary">{{ __('Accept Invitation') }}</a>
    </div>

    <p class="muted" style="margin-top: 20px;">{{ __('If you did not expect to receive an invitation to this team, you may discard this email.') }}</p>
@endsection
