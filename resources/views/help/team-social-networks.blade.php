@extends('layouts/layoutHelpSimple')

@section('title', __('Team social networks (Meta, LinkedIn, Bluesky)'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/prism/prism.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/prism/prism.js') }}"></script>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0">{{ __('Team social networks') }}</h4>
                <a href="{{ route('help.index') }}" class="btn btn-sm btn-label-secondary">{{ __('← Help home') }}</a>
            </div>
            <div class="card-body">
                <p class="lead">{{ __('Humano can connect each team’s social accounts for dashboard metrics. There are two different kinds of credentials: the OAuth app keys (one per Humano deployment) and the per-team tokens created when an admin clicks Connect.') }}</p>

                <div class="alert alert-primary mb-4">
                    <h6 class="alert-heading mb-2"><i class="ti ti-key me-2"></i>{{ __('App keys (.env) — global for the whole product') }}</h6>
                    <p class="mb-0">{{ __('You register one Meta app, one LinkedIn app, etc., as the developer of Humano. Their Client ID and Client Secret live in the server .env (or hosting secrets) and map to config/services.php for Laravel Socialite. Every team uses the same app; redirect URIs must match your APP_URL.') }}</p>
                </div>

                <div class="alert alert-info mb-4">
                    <h6 class="alert-heading mb-2"><i class="ti ti-users-group me-2"></i>{{ __('Per-team tokens — stored in the database') }}</h6>
                    <p class="mb-0">{{ __('When a team admin connects Meta or LinkedIn, Humano receives access (and refresh) tokens tied to that user/page. Those values are stored per team (encrypted), not in .env. Disconnecting removes that row for the team.') }}</p>
                </div>

                <h5 class="mt-4">{{ __('Where team admins connect') }}</h5>
                <p>{{ __('Open') }} <strong>{{ __('Team Settings') }}</strong> → <strong>{{ __('Social networks') }}</strong> → <strong>{{ __('Manage') }}</strong>, {{ __('or go directly to') }}:</p>
                <pre class="mb-3"><code>{{ rtrim(config('app.url'), '/') }}/team/&lt;team_id&gt;/settings/social</code></pre>
                <p class="small text-muted">{{ __('Replace') }} <code>&lt;team_id&gt;</code> {{ __('with the numeric ID of the team (same as in') }} <code>/team/&lt;team_id&gt;/settings</code>).</p>

                <h5 class="mt-4">{{ __('Typical server variables (when OAuth is wired)') }}</h5>
                <p>{{ __('Exact names follow your config/services.php entries. Common Socialite examples:') }}</p>
                <pre class="mb-3"><code class="language-env">APP_URL=https://humano.test

# Meta (Facebook Login / Graph) — example keys; align with services.facebook
FACEBOOK_CLIENT_ID=
FACEBOOK_CLIENT_SECRET=
FACEBOOK_REDIRECT_URI="${APP_URL}/integrations/social/facebook/callback"

# LinkedIn OpenID — example; align with services.linkedin-openid
LINKEDIN_CLIENT_ID=
LINKEDIN_CLIENT_SECRET=
LINKEDIN_REDIRECT_URI="${APP_URL}/integrations/social/linkedin-openid/callback"</code></pre>
                <p class="small text-muted">{{ __('Register the same redirect URLs in the Meta and LinkedIn developer consoles. Until OAuth routes are enabled in your build, the Connect buttons in Team Settings may stay disabled.') }}</p>

                <h5 class="mt-4">{{ __('Bluesky') }}</h5>
                <p>{{ __('Public follower counts can use the Bluesky handle alone in some flows; optional app passwords are for authenticated API access. Team-specific handles or secrets are never global .env keys for all teams.') }}</p>

                <h5 class="mt-4">{{ __('Related') }}</h5>
                <ul>
                    <li><a href="{{ route('help.environment-variables.google-people-calendar') }}">{{ __('Google People y Calendar (OAuth)') }}</a> — {{ __('same pattern: global Google OAuth client, per-team tokens.') }}</li>
                    <li><a href="{{ route('help.environment-variables') }}">{{ __('Variables de Entorno') }}</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
