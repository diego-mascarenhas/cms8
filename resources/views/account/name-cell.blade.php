@php
    $teamName = (string) $account->name;
    $primaryName = $account->responsiblePersonName();
    $subtitle = $primaryName !== '' && strcasecmp($primaryName, $teamName) !== 0 ? $teamName : '';
@endphp
<div class="d-flex flex-column overflow-hidden" style="max-width: 18rem;">
    <a href="{{ route('account.edit', $account->id) }}" class="fw-medium text-body text-truncate" title="{{ $primaryName }}">
        {{ $primaryName }}
    </a>
    <small class="text-muted text-truncate" @if($subtitle !== '') title="{{ $subtitle }}" @endif>{!! $subtitle !== '' ? e($subtitle) : '&nbsp;' !!}</small>
</div>
