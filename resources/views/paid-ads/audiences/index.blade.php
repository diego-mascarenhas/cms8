@extends('layouts/layoutMaster')

@section('title', __('Audiences'))

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Paid Ads') }}/</span> {{ __('Audiences') }}</h4>
        <p class="text-muted">{{ __('Reusable targeting audiences for your campaigns') }}</p>
    </div>
    <div class="d-flex gap-2 mt-3 mt-md-0">
        <a href="{{ route('paid-ads.index') }}" class="btn btn-label-secondary">{{ __('Back to campaigns') }}</a>
        @can('create', App\Models\PaidAdAudience::class)
        <a href="{{ route('paid-ads.audiences.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>{{ __('Add audience') }}</a>
        @endcan
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th class="text-end">{{ __('Est. size') }}</th>
                    <th class="text-end">{{ __('Campaigns') }}</th>
                    <th class="text-center">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($audiences as $audience)
                    <tr>
                        <td>{{ $audience->name }}</td>
                        <td><span class="badge bg-label-secondary">{{ ucfirst($audience->type) }}</span></td>
                        <td class="text-end">{{ $audience->estimated_size ? number_format($audience->estimated_size) : '—' }}</td>
                        <td class="text-end">{{ $audience->campaigns_count }}</td>
                        <td class="text-center">
                            @can('update', $audience)
                                <a href="{{ route('paid-ads.audiences.edit', $audience->id) }}" class="text-body" title="{{ __('Edit') }}"><i class="ti ti-edit ti-sm me-2"></i></a>
                            @endcan
                            @can('delete', $audience)
                                <a href="#" class="text-danger" onclick="deleteAudience({{ $audience->id }}, this)" title="{{ __('Delete') }}"><i class="ti ti-trash ti-sm"></i></a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted text-center">{{ __('No audiences yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body">{{ $audiences->links() }}</div>
</div>

<script>
    function deleteAudience(id, element) {
        if (!confirm('{{ __("Are you sure you want to delete this record?") }}')) return;
        fetch("{{ route('paid-ads.audiences.destroy', ['id' => ':ID']) }}".replace(':ID', id), {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).then(r => r.json()).then(() => {
            const row = element.closest('tr');
            if (row) row.remove();
        });
    }
</script>
@endsection
