@extends('layouts/layoutMaster')

@section('title', $pageTitle)

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3">{{ $pageTitle }}</h4>
        <p class="text-muted mb-0">{{ $pageSubtitle }}</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ $backUrl }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>{{ __('Back') }}
        </a>
    </div>
</div>

<div class="mb-4">
    @include('partials.invoiced-lines-period-filter', [
        'filterAction' => route('finance-dashboard.invoiced-lines'),
        'availableYears' => $availableYears,
        'selectedYear' => $selectedYear,
        'selectedMonth' => $selectedMonth,
        'hiddenFields' => array_filter([
            'operation' => request()->query('operation'),
            'return' => request()->query('return'),
            'uncategorized' => ! empty($uncategorizedOnly) ? '1' : null,
        ]),
    ])
</div>

<div class="card">
    <div class="card-body p-0">
        @include('partials.invoiced-line-items-list', [
            'lines' => $lines,
            'totalAmount' => $totalAmount,
            'reportingCurrency' => $reportingCurrency,
            'conversionComplete' => $conversionComplete,
            'amountTone' => $amountTone ?? 'auto',
            'emptyMessage' => $emptyMessage,
            'canEditCategory' => $canEditCategory ?? false,
        ])
    </div>
</div>

@if (! empty($canEditCategory))
    @include('partials.line-category-modal', [
        'categoryOptions' => $categoryOptions ?? [],
        'showSuggestion' => false,
        'livewireKey' => 'invoiced-lines-cat-mgr-services',
    ])
@endif
@endsection

@section('page-script')
@if (! empty($canEditCategory))
<script>
$(function () {
    var updateUrlTemplate = @json(url('/invoice-items/__ID__/category'));
    var uncategorizedOnly = @json(! empty($uncategorizedOnly));
    var uncategorizedLabel = @json(__('Uncategorized'));
    var csrfToken = $('meta[name="csrf-token"]').attr('content');
    var $modal = $('#lineCategoryModal');
    var $select = $('#line_category_modal_select');
    var $activeRow = null;
    var $selectTemplate = null;

    function typeFilterNote() {
        var $note = $('#line-category-type-filter');
        if (! $note.length && $modal.length) {
            $note = $('<div id="line-category-type-filter" class="alert alert-secondary d-none mb-3" role="status"></div>');
            $modal.find('.modal-body').prepend($note);
        }

        return $note;
    }

    function rememberSelectOptions() {
        if (! $selectTemplate && $select.length) {
            $selectTemplate = $select.clone();
        }
    }

    function applyTypeFilter(group, type) {
        rememberSelectOptions();
        var $note = typeFilterNote();
        if (! $selectTemplate) {
            return;
        }

        var current = $select.val();
        $select.empty();
        $selectTemplate.children().each(function () {
            var $node = $(this).clone();
            if (! group || $node.is('option') || $node.attr('label') === group) {
                $select.append($node);
            }
        });
        $select.val(current);

        if (group && type) {
            $note.removeClass('d-none').html(
                'Tipo de la suscripción: <strong>' + $('<span>').text(type).html() + '</strong>. ' +
                'Se muestran las categorías de ' + $('<span>').text(group).html() + '. ' +
                '<button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="line-category-show-all">Ver todas</button>'
            );
        } else {
            $note.addClass('d-none').empty();
        }
    }

    function updateUrl(itemId) {
        return updateUrlTemplate.replace('__ID__', String(itemId));
    }

    function initSelect() {
        if (! $select.length) {
            return;
        }

        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }

        $select.select2({
            dropdownParent: $modal,
            width: '100%',
            allowClear: true,
            placeholder: uncategorizedLabel,
        });
    }

    function setBadge($row, categoryId, categoryName) {
        var id = categoryId ? String(categoryId) : '';
        var hasCategory = id !== '';
        var label = hasCategory ? (categoryName || ('#' + id)) : uncategorizedLabel;

        $row.attr('data-category-id', id);
        $row.find('.line-category-id').val(id);
        $row.find('.line-category-badge')
            .text(label)
            .toggleClass('bg-label-primary', hasCategory)
            .toggleClass('bg-label-secondary', !hasCategory);
    }

    function persistCategory(categoryId) {
        if (! $activeRow || ! $activeRow.length) {
            return;
        }

        var itemId = $activeRow.data('item-id');
        if (! itemId) {
            return;
        }

        var $saveBtn = $('#save-line-category');
        var $clearBtn = $('#clear-line-category');
        $saveBtn.prop('disabled', true);
        $clearBtn.prop('disabled', true);

        $.ajax({
            url: updateUrl(itemId),
            type: 'POST',
            data: {
                _token: csrfToken,
                _method: 'PATCH',
                category_id: categoryId === '' || categoryId === null ? null : categoryId,
            },
            success: function (response) {
                var id = response.category_id ? String(response.category_id) : '';
                var name = response.category_name || uncategorizedLabel;
                setBadge($activeRow, id, name);

                if (uncategorizedOnly && id !== '') {
                    $activeRow.remove();
                }

                bootstrap.Modal.getOrCreateInstance($modal.get(0)).hide();
            },
            error: function (xhr) {
                var message = (xhr.responseJSON && xhr.responseJSON.message)
                    ? xhr.responseJSON.message
                    : @json(__('Could not update category.'));
                alert(message);
            },
            complete: function () {
                $saveBtn.prop('disabled', false);
                $clearBtn.prop('disabled', false);
            },
        });
    }

    $(document).on('click', '.line-category-badge', function () {
        $activeRow = $(this).closest('.invoiced-line-item');
        var currentId = $.trim(String($activeRow.find('.line-category-id').val() || ''));
        var group = $.trim(String($activeRow.data('category-group') || ''));
        var type = $.trim(String($activeRow.data('subscription-type') || ''));
        applyTypeFilter(group, type);
        initSelect();
        $select.val(currentId).trigger('change');
        bootstrap.Modal.getOrCreateInstance($modal.get(0)).show();
    });

    $(document).on('click', '#line-category-show-all', function () {
        var currentId = $.trim(String($select.val() || ''));
        applyTypeFilter('', '');
        initSelect();
        $select.val(currentId).trigger('change');
    });

    if ($modal.length) {
        rememberSelectOptions();
        initSelect();
        $modal.on('shown.bs.modal', function () {
            initSelect();
        });

        $('#save-line-category').on('click', function () {
            persistCategory($.trim(String($select.val() || '')));
        });

        $('#clear-line-category').on('click', function () {
            persistCategory(null);
        });

        if (typeof Livewire !== 'undefined' && typeof Livewire.on === 'function') {
            Livewire.on('module-categories-refreshed', function () {
                initSelect();
            });
        }
    }
});
</script>
@endif
@endsection
