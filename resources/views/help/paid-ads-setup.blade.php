@extends('layouts/layoutMaster')

@section('title', __('Paid Ads setup'))

@section('content')
<div class="d-flex flex-column justify-content-center mb-4">
    <h4 class="mb-1 mt-3">{{ __('Paid Ads setup') }}</h4>
    <p class="text-muted">{{ __('Register an app in each platform developer portal and add the credentials in each team\'s settings (Team → Settings → Paid Ads platforms).') }}</p>
</div>

<div class="alert alert-info">
    {{ __('Credentials are stored per team (encrypted) in team settings, not in the server .env. Each platform requires: an app in its developer portal, an OAuth client, an ad account, and (often) production approval. Configure the callback (redirect) URI listed below for each platform.') }}
    {{ __('The X (Twitter) Ads API additionally must be enabled globally via the X_ADS_ENABLED flag once access is approved.') }}
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ __('Redirect URIs') }}</h5>
    <div class="card-body">
        <p class="text-muted">{{ __('Use these callback URLs in each platform developer console.') }}</p>
        <ul>
            <li><code>{{ url('/integrations/ad-platforms/google_ads/callback') }}</code></li>
            <li><code>{{ url('/integrations/ad-platforms/meta/callback') }}</code></li>
            <li><code>{{ url('/integrations/ad-platforms/linkedin/callback') }}</code></li>
            <li><code>{{ url('/integrations/ad-platforms/tiktok/callback') }}</code></li>
            <li><code>{{ url('/integrations/ad-platforms/x/callback') }}</code></li>
        </ul>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ __('Optional global fallback (.env)') }}</h5>
    <div class="card-body">
        <p class="text-muted">{{ __('Team settings take priority. These optional .env values act as a shared fallback for all teams (e.g. a single agency app). The X feature flag is global.') }}</p>
        <pre class="bg-light p-3 rounded"><code># Google Ads (OAuth 2.0 + Developer Token)
GOOGLE_ADS_CLIENT_ID=
GOOGLE_ADS_CLIENT_SECRET=
GOOGLE_ADS_DEVELOPER_TOKEN=
GOOGLE_ADS_LOGIN_CUSTOMER_ID=   # MCC / manager account, digits only

# Meta (Facebook + Instagram)
META_APP_ID=
META_APP_SECRET=
META_ADS_API_VERSION=v21.0

# LinkedIn Marketing API
LINKEDIN_CLIENT_ID=
LINKEDIN_CLIENT_SECRET=

# TikTok Business API
TIKTOK_APP_ID=
TIKTOK_APP_SECRET=

# X (Twitter) Ads API — restricted access, disabled by default
X_ADS_ENABLED=false
X_ADS_CLIENT_ID=
X_ADS_CLIENT_SECRET=</code></pre>
        <p class="small text-muted mt-2">{{ __('After editing .env run') }} <code>php artisan config:clear</code>.</p>
    </div>
</div>

<div class="card mb-4">
    <h5 class="card-header">{{ __('Platform notes') }}</h5>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Google Ads</dt>
            <dd class="col-sm-9">{{ __('Search, Display and YouTube (partial). Requires a Developer Token approved for Basic/Standard access and a manager (MCC) account.') }}</dd>
            <dt class="col-sm-3">Meta</dt>
            <dd class="col-sm-9">{{ __('Instagram and Facebook share the same Meta campaign; permissions ads_management and business_management need App Review for production.') }}</dd>
            <dt class="col-sm-3">LinkedIn</dt>
            <dd class="col-sm-9">{{ __('Strong for B2B. The Marketing Developer Platform partner program approval is more demanding.') }}</dd>
            <dt class="col-sm-3">TikTok</dt>
            <dd class="col-sm-9">{{ __('Separate Business API stack with its own OAuth (auth_code exchange) and sandbox vs production environments.') }}</dd>
            <dt class="col-sm-3">X (Twitter)</dt>
            <dd class="col-sm-9">{{ __('Ads API access is heavily restricted and requires an approved developer account. Kept disabled behind the X_ADS_ENABLED feature flag until approved.') }}</dd>
        </dl>
    </div>
</div>
@endsection
