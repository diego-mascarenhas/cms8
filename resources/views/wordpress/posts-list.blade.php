@extends('layouts/layoutMaster')

@section('title', __('Posts'))

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('Posts') }}</h4>
            <p class="text-muted">{{ __('Content from your WordPress site') }}</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="{{ $storeUrl }}/wp-admin/edit.php" target="_blank" rel="noopener noreferrer" class="btn btn-label-secondary">{{ __('Open in WordPress') }}</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible mb-3" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible mb-3" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    <div class="card">
        <div class="card-body">
            @if (count($posts) === 0)
                <p class="text-muted mb-0">{{ __('No posts found.') }} {{ __('Manage posts in') }} <a href="{{ $storeUrl }}/wp-admin/edit.php" target="_blank" rel="noopener noreferrer">{{ __('WordPress') }}</a>.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>{{ __('Title') }}</th>
                                <th class="text-center">{{ __('Date') }}</th>
                                <th class="text-center">{{ __('Status') }}</th>
                                <th class="text-center">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($posts as $post)
                                @php
                                    $rawTitle = is_array($post['title'] ?? null) ? ($post['title']['rendered'] ?? '') : ($post['title'] ?? '');
                                    $title = $rawTitle !== '' ? html_entity_decode(strip_tags($rawTitle), ENT_QUOTES, 'UTF-8') : '—';
                                @endphp
                                <tr>
                                    <td>
                                        @if (!empty($post['link']))
                                            <a href="{{ $post['link'] }}" target="_blank" rel="noopener noreferrer">{{ $title }}</a>
                                        @else
                                            {{ $title }}
                                        @endif
                                    </td>
                                    <td class="text-center">{{ isset($post['date']) ? \Carbon\Carbon::parse($post['date'])->format('d/m/Y H:i') : '—' }}</td>
                                    <td class="text-center">
                                        @php $status = $post['status'] ?? ''; @endphp
                                        @if ($status === 'publish')
                                            <span class="badge bg-success">{{ __('Published') }}</span>
                                        @elseif ($status === 'draft')
                                            <span class="badge bg-secondary">{{ __('Draft') }}</span>
                                        @elseif ($status === 'pending')
                                            <span class="badge bg-warning">{{ __('Pending') }}</span>
                                        @elseif ($status === 'future')
                                            <span class="badge bg-info">{{ __('Scheduled') }}</span>
                                        @else
                                            <span class="badge bg-label-secondary">{{ $status ?: '—' }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center align-items-center">
                                            @if (!empty($post['link']))
                                                <a href="{{ $post['link'] }}" target="_blank" rel="noopener noreferrer" class="text-body" title="{{ __('View') }}">
                                                    <i class="ti ti-eye ti-sm me-2"></i>
                                                </a>
                                            @endif
                                            <a href="{{ route('wordpress.posts.edit', $post['id']) }}" class="text-body" title="{{ __('Edit') }}">
                                                <i class="ti ti-edit ti-sm me-2"></i>
                                            </a>
                                            <a href="{{ $storeUrl }}/wp-admin/post.php?post={{ $post['id'] }}&action=edit" target="_blank" rel="noopener noreferrer" class="text-body" title="{{ __('Edit in WordPress') }}">
                                                <i class="ti ti-external-link ti-sm"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
