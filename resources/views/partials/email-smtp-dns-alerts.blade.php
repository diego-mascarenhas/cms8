{{-- Expects $dnsStatus from controller (nullable). Same rules as message detail. --}}
@if (isset($dnsStatus))
    @php
        $spfOk = $dnsStatus['spf']['has_mailbaby'] ?? false;
        $usingSystemSmtp = auth()->user()?->currentTeam?->isUsingSystemSmtp() ?? false;
        $hasConfigIssues = ! app()->isLocal()
            && $usingSystemSmtp
            && ! $spfOk;
    @endphp

    @if ($hasConfigIssues)
        <div class="row mb-3">
            <div class="col-12">
                <div class="alert alert-warning" role="alert">
                    <i class="ti ti-alert-triangle me-2"></i>
                    <strong>{{ __('app.email_smtp_dns_alert_title') }}</strong>
                    <span class="ms-1">{!! __('app.email_smtp_dns_alert_body', [
                        'domain' => '<strong>'.e($dnsStatus['domain']).'</strong>',
                        'include' => '<code>'.e(\App\Helpers\DnsHelper::REVISION_ALPHA_SPF_INCLUDE).'</code>',
                        'example' => '<code>'.e(\App\Helpers\DnsHelper::REQUIRED_REVISION_ALPHA_SPF_TXT).'</code>',
                    ]) !!}</span>
                    <span class="d-block text-body-secondary small mt-1">{{ __('app.email_smtp_dns_spf_exact_hint') }}</span>
                    <p class="mb-0 mt-2 small">
                        <a href="{{ route('help.email-spf-dns') }}" class="alert-link fw-semibold">{{ __('app.email_smtp_dns_help_link_label') }}</a>
                        <span class="text-body-secondary"> {{ __('app.email_smtp_dns_help_link_description') }}</span>
                    </p>
                </div>
            </div>
        </div>
    @endif
@endif
