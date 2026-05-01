{{-- Expects $dnsStatus from controller (nullable). Same rules as message detail. --}}
@if (isset($dnsStatus))
    @php
        $isAuthorized = $dnsStatus['spf']['has_mailbaby'] && $dnsStatus['mailbaby_auth']['authorized'];
        $usingSystemSmtp = auth()->user()?->currentTeam?->isUsingSystemSmtp() ?? false;
        $hasConfigIssues = $usingSystemSmtp && (! $dnsStatus['spf']['has_mailbaby'] || ! $isAuthorized);
    @endphp

    @if ($hasConfigIssues)
        <div class="row mb-3">
            <div class="col-12">
                @if (! $dnsStatus['spf']['has_mailbaby'])
                    <div class="alert alert-warning" role="alert">
                        <i class="ti ti-alert-triangle me-2"></i>
                        <strong>SPF Configuration Required:</strong>
                        Add TXT record: <code>"v=spf1 include:spf.revisionalpha.com -all"</code> to domain <strong>{{ $dnsStatus['domain'] }}</strong>
                    </div>
                @endif

                @if ($usingSystemSmtp && ! $isAuthorized)
                    <div class="alert alert-danger" role="alert">
                        <i class="ti ti-x-circle me-2"></i>
                        <strong>Domain Not Authorized:</strong>
                        Your domain <strong>{{ $dnsStatus['domain'] }}</strong> is not authorized to use system SMTP. Email sending is disabled.
                    </div>
                @endif
            </div>
        </div>
    @endif
@endif
