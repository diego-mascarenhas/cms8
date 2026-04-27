@php
    $supportEmail = trim((string) config('mail.from.address'));
@endphp
@if ($supportEmail !== '')
  <a href="mailto:{{ e($supportEmail) }}">{{ $label ?? 'Support' }}</a>
@else
  <span class="text-muted">{{ $label ?? 'Support' }}</span>
@endif
