@extends('layouts/layoutMaster')

@section('title', 'Team Settings')

@section('page-style')
<meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">Team Settings</h4>
        <p class="text-muted">Configure your team settings and preferences</p>
    </div>
</div>

    <div class="row">
        <div class="col-md-12">
            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-brand-stripe mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">Stripe Integration</h5>
                            <p class="card-text">Configure Stripe API keys and webhook settings</p>
                            <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'stripe']) }}" class="btn btn-primary">Configure</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-category mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">Categories</h5>
                            <p class="card-text">Configure default category settings and preferences</p>
                            <div class="btn-group">
                                <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'categories']) }}" class="btn btn-primary">Configure</a>
                                <a href="{{ route('categories.index') }}" class="btn btn-outline-primary">Manage</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-bell mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">Notifications</h5>
                            <p class="card-text">Manage notification preferences for your team</p>
                            <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'notifications']) }}" class="btn btn-primary">Configure</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-star mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">Valorations</h5>
                            <p class="card-text">Manage contact valorations for your team</p>
                            <a href="{{ route('team-settings.valorations', $team) }}" class="btn btn-primary">Manage</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-key mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">API Access Token</h5>
                            <p class="card-text">Generate and manage team API tokens for external access</p>
                            <a href="{{ route('team-settings.api-tokens', $team) }}" class="btn btn-primary">Manage</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-language mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">Custom Translations</h5>
                            <p class="card-text">Manage custom translations for your team</p>
                            <a href="{{ route('team-settings.custom-translations', $team) }}" class="btn btn-primary">Manage</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-phone mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">Twilio Integration</h5>
                            <p class="card-text">Configure Twilio API settings for SMS and WhatsApp</p>
                            <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'twilio']) }}" class="btn btn-primary">Configure</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-mail mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">Email Configuration</h5>
                            <p class="card-text">Configure SMTP and IMAP settings for incoming and outgoing emails</p>
                            <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'email']) }}" class="btn btn-primary">Configure</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="ti ti-mail-bolt mb-3" style="font-size: 2rem;"></i>
                            <h5 class="card-title">Email Plans & Limits</h5>
                            <p class="card-text">Configure email limits, plans and usage tracking for your team</p>
                            <div class="btn-group">
                                <a href="{{ route('team-settings.edit', ['team' => $team, 'group' => 'email-plans']) }}" class="btn btn-primary">Configure</a>
                                @if(auth()->user()->hasRole('admin'))
                                    <a href="{{ route('email-plans-management.index') }}" class="btn btn-outline-primary">Manage</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if($groupedSettings->count() > 0)
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Service Connections</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                // Check if SMTP is configured
                                $hasSmtp = $team->hasOutgoingEmailConfig();
                                $smtpHost = $team->getSetting('mail_host');

                                // Check if IMAP is configured
                                $hasImap = $team->hasIncomingEmailConfig();
                                $imapHost = $team->getSetting('imap_host');

                                // Check if Stripe is configured
                                $hasStripe = !empty($team->getSetting('stripe_public')) && !empty($team->getSetting('stripe_secret'));

                                // Check if Twilio is configured
                                $hasTwilio = $team->hasTwilioConfig();
                            @endphp

                            @if($hasSmtp || !empty($smtpHost))
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="ti ti-send me-2 text-primary"></i>
                                        <div>
                                            <div class="fw-semibold">SMTP (Outgoing Email)</div>
                                            <small class="text-muted">{{ $smtpHost ?? 'Not configured' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    @if($hasSmtp)
                                        <span class="badge bg-success">Configured</span>
                                    @else
                                        <span class="badge bg-warning">Partial</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if(!empty($smtpHost))
                                        <button type="button" class="btn btn-sm btn-info" onclick="testSmtpConnection({{ $team->id }})">
                                            <i class="ti ti-send me-1"></i>Test SMTP
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @endif

                            @if($hasImap || !empty($imapHost))
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="ti ti-mail-opened me-2 text-info"></i>
                                        <div>
                                            <div class="fw-semibold">IMAP (Incoming Email)</div>
                                            <small class="text-muted">{{ $imapHost ?? 'Not configured' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    @if($hasImap)
                                        <span class="badge bg-success">Configured</span>
                                    @else
                                        <span class="badge bg-warning">Partial</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($hasImap)
                                        <button type="button" class="btn btn-sm btn-info" onclick="testImapConnection({{ $team->id }})">
                                            <i class="ti ti-mail-opened me-1"></i>Test IMAP
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @endif

                            @if($hasStripe || !empty($team->getSetting('stripe_public')))
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="ti ti-credit-card me-2 text-success"></i>
                                        <div>
                                            <div class="fw-semibold">Stripe Payment</div>
                                            <small class="text-muted">Payment processing</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    @if($hasStripe)
                                        <span class="badge bg-success">Configured</span>
                                    @else
                                        <span class="badge bg-warning">Partial</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($hasStripe)
                                        <button type="button" class="btn btn-sm btn-info" onclick="testStripeConnection({{ $team->id }})">
                                            <i class="ti ti-credit-card me-1"></i>Test Stripe
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @endif

                            @if($hasTwilio || !empty($team->getSetting('twilio_sid')))
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="ti ti-phone me-2 text-warning"></i>
                                        <div>
                                            <div class="fw-semibold">Twilio Messaging</div>
                                            <small class="text-muted">SMS & WhatsApp</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    @if($hasTwilio)
                                        <span class="badge bg-success">Configured</span>
                                    @else
                                        <span class="badge bg-warning">Partial</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($hasTwilio)
                                        <button type="button" class="btn btn-sm btn-info" onclick="testTwilioConnection({{ $team->id }})">
                                            <i class="ti ti-phone me-1"></i>Test Twilio
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
    </div>
@endsection

@section('page-script')
<script>
    function testSmtpConnection(teamId) {
        const button = event.target;
        const originalText = button.innerHTML;

        // Show loading state
        button.disabled = true;
        button.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i>Testing...';

        // Make AJAX request
        fetch(`/team/${teamId}/test-smtp`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            // Show result
            if (data.success) {
                button.classList.remove('btn-info');
                button.classList.add('btn-success');
                button.innerHTML = '<i class="ti ti-check me-1"></i>Success!';
            } else {
                button.classList.remove('btn-info');
                button.classList.add('btn-danger');
                button.innerHTML = '<i class="ti ti-x me-1"></i>Failed';
            }

            // Reset button after 3 seconds
            setTimeout(() => {
                button.disabled = false;
                button.className = 'btn btn-sm btn-info';
                button.innerHTML = originalText;
            }, 3000);
        })
        .catch(error => {
            console.error('Test connection error:', error);
            button.classList.remove('btn-info');
            button.classList.add('btn-danger');
            button.innerHTML = '<i class="ti ti-x me-1"></i>Error';

            setTimeout(() => {
                button.disabled = false;
                button.className = 'btn btn-sm btn-info';
                button.innerHTML = originalText;
            }, 3000);
        });
    }

    function testImapConnection(teamId) {
        const button = event.target;
        const originalText = button.innerHTML;

        // Show loading state
        button.disabled = true;
        button.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i>Testing...';

        // Make AJAX request
        fetch(`/team/${teamId}/test-imap`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            // Show result
            if (data.success) {
                button.classList.remove('btn-info');
                button.classList.add('btn-success');
                button.innerHTML = '<i class="ti ti-check me-1"></i>Success!';
            } else {
                button.classList.remove('btn-info');
                button.classList.add('btn-danger');
                button.innerHTML = '<i class="ti ti-x me-1"></i>Failed';
            }

            // Reset button after 3 seconds
            setTimeout(() => {
                button.disabled = false;
                button.className = 'btn btn-sm btn-info';
                button.innerHTML = originalText;
            }, 3000);
        })
        .catch(error => {
            console.error('Test connection error:', error);
            button.classList.remove('btn-info');
            button.classList.add('btn-danger');
            button.innerHTML = '<i class="ti ti-x me-1"></i>Error';

            setTimeout(() => {
                button.disabled = false;
                button.className = 'btn btn-sm btn-info';
                button.innerHTML = originalText;
            }, 3000);
        });
    }

    function testStripeConnection(teamId) {
        const button = event.target;
        const originalText = button.innerHTML;

        // Show loading state
        button.disabled = true;
        button.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i>Testing...';

        // Make AJAX request
        fetch(`/team/${teamId}/test-stripe`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            // Show result
            if (data.success) {
                button.classList.remove('btn-info');
                button.classList.add('btn-success');
                button.innerHTML = '<i class="ti ti-check me-1"></i>Success!';
            } else {
                button.classList.remove('btn-info');
                button.classList.add('btn-danger');
                button.innerHTML = '<i class="ti ti-x me-1"></i>Failed';
            }

            // Reset button after 3 seconds
            setTimeout(() => {
                button.disabled = false;
                button.className = 'btn btn-sm btn-info';
                button.innerHTML = originalText;
            }, 3000);
        })
        .catch(error => {
            console.error('Test connection error:', error);
            button.classList.remove('btn-info');
            button.classList.add('btn-danger');
            button.innerHTML = '<i class="ti ti-x me-1"></i>Error';

            setTimeout(() => {
                button.disabled = false;
                button.className = 'btn btn-sm btn-info';
                button.innerHTML = originalText;
            }, 3000);
        });
    }

    function testTwilioConnection(teamId) {
        const button = event.target;
        const originalText = button.innerHTML;

        // Show loading state
        button.disabled = true;
        button.innerHTML = '<i class="ti ti-loader ti-spin me-1"></i>Testing...';

        // Make AJAX request
        fetch(`/team/${teamId}/test-twilio`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            // Show result
            if (data.success) {
                button.classList.remove('btn-info');
                button.classList.add('btn-success');
                button.innerHTML = '<i class="ti ti-check me-1"></i>Success!';
            } else {
                button.classList.remove('btn-info');
                button.classList.add('btn-danger');
                button.innerHTML = '<i class="ti ti-x me-1"></i>Failed';
            }

            // Reset button after 3 seconds
            setTimeout(() => {
                button.disabled = false;
                button.className = 'btn btn-sm btn-info';
                button.innerHTML = originalText;
            }, 3000);
        })
        .catch(error => {
            console.error('Test connection error:', error);
            button.classList.remove('btn-info');
            button.classList.add('btn-danger');
            button.innerHTML = '<i class="ti ti-x me-1"></i>Error';

            setTimeout(() => {
                button.disabled = false;
                button.className = 'btn btn-sm btn-info';
                button.innerHTML = originalText;
            }, 3000);
        });
    }
</script>
@endsection
