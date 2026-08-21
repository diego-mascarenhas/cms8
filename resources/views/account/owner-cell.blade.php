@if($owner)
    @php
        $email = (string) ($owner->email ?: '');
        $phone = $owner->phone !== null && $owner->phone !== '' ? (string) $owner->phone : '';
    @endphp
    <div class="d-flex flex-column overflow-hidden" style="max-width: 16rem;">
        <span class="text-truncate" title="{{ $email !== '' ? $email : '' }}">{{ $email !== '' ? $email : '—' }}</span>
        <small class="text-muted text-truncate" @if($phone !== '') title="{{ $phone }}" @endif>{{ $phone !== '' ? $phone : '—' }}</small>
    </div>
@else
    —
@endif
