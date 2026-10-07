@extends('layouts/layoutMaster')

@section('title', __('app.weekly_plan_strategy_edit_title', ['level' => $currentStep['number'] ?? 1, 'title' => $currentStep['title'] ?? '']))

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
        <div class="d-flex flex-column justify-content-center">
            <h4 class="mb-1 mt-3">{{ __('app.weekly_plan_strategy_edit_title', ['level' => $currentStep['number'] ?? 1, 'title' => $currentStep['title'] ?? '']) }}</h4>
            @if (!empty($currentStep['tip']))
                <p class="text-muted mb-0">{{ $currentStep['tip'] }}</p>
            @endif
        </div>
        <div class="d-flex align-content-center flex-wrap gap-2 mt-3 mt-md-0">
            <a href="{{ route('strategy.index') }}" class="btn btn-label-secondary">
                <i class="ti ti-target me-1"></i>{{ __('app.weekly_plan_strategy_link') }}
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (is_array($currentStep) && !empty($currentStep['fields']))
        <style>
            .strategy-editor {
                min-height: 7rem;
                white-space: pre-wrap;
            }
            .strategy-editor:empty:before {
                content: attr(data-placeholder);
                color: var(--bs-secondary-color);
            }
            .strategy-editor mark.strategy-suggestion-mark {
                background-color: rgba(var(--bs-primary-rgb), 0.18);
                color: var(--bs-primary);
                padding: 0 0.1rem;
                border-radius: 0.15rem;
            }
        </style>
        <div class="card mb-4 border border-primary">
            <div class="card-body">
                <form method="POST" action="{{ route('strategy.update') }}">
                    @csrf
                    <input type="hidden" name="level" value="{{ $currentStep['number'] ?? 1 }}">
                    <div class="row g-3">
                        @foreach ($currentStep['fields'] as $field)
                            <div class="col-12" data-strategy-item="{{ $field['key'] }}">
                                <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                                    <label class="form-label mb-0" id="strategy-label-{{ $field['key'] }}" for="strategy-editor-{{ $field['key'] }}">{{ $field['label'] }}</label>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-label-primary strategy-suggest"
                                        data-field="{{ $field['key'] }}"
                                    >
                                        <i class="ti ti-sparkles me-1"></i>{{ __('app.strategy_field_suggest') }}
                                    </button>
                                </div>
                                <textarea
                                    id="strategy-{{ $field['key'] }}"
                                    name="strategy[{{ $field['key'] }}]"
                                    class="d-none @error('strategy.'.$field['key']) is-invalid @enderror"
                                    maxlength="5000"
                                >{{ old('strategy.'.$field['key'], $field['value'] ?? '') }}</textarea>
                                <div
                                    id="strategy-editor-{{ $field['key'] }}"
                                    class="form-control strategy-editor @error('strategy.'.$field['key']) is-invalid @enderror"
                                    contenteditable="true"
                                    role="textbox"
                                    aria-multiline="true"
                                    aria-labelledby="strategy-label-{{ $field['key'] }}"
                                    data-placeholder="{{ __('app.weekly_plan_strategy_field_placeholder', ['field' => $field['label']]) }}"
                                ></div>
                                @error('strategy.'.$field['key'])
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="text-danger small mt-1 d-none" data-suggestion-error></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy me-1"></i>{{ __('app.weekly_plan_strategy_save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <script>
            function strategyPlainText(editor) {
                return (editor?.innerText || '').replace(/\u00a0/g, ' ').replace(/\n$/, '');
            }

            function strategySegments(before, after) {
                const left = before.split(/(\s+)/);
                const right = after.split(/(\s+)/);
                const table = Array.from({ length: left.length + 1 }, function () {
                    return new Array(right.length + 1).fill(0);
                });

                for (let row = left.length - 1; row >= 0; row--) {
                    for (let column = right.length - 1; column >= 0; column--) {
                        table[row][column] = left[row] === right[column]
                            ? table[row + 1][column + 1] + 1
                            : Math.max(table[row + 1][column], table[row][column + 1]);
                    }
                }

                const segments = [];
                const push = function (text, changed) {
                    if (text === '') {
                        return;
                    }

                    const last = segments[segments.length - 1];

                    if (last && last.changed === changed) {
                        last.text += text;
                    } else {
                        segments.push({ text: text, changed: changed });
                    }
                };

                let row = 0;
                let column = 0;

                while (row < left.length && column < right.length) {
                    if (left[row] === right[column]) {
                        push(right[column], false);
                        row++;
                        column++;
                    } else if (table[row + 1][column] >= table[row][column + 1]) {
                        row++;
                    } else {
                        push(right[column], true);
                        column++;
                    }
                }

                while (column < right.length) {
                    push(right[column], true);
                    column++;
                }

                return segments;
            }

            function strategyPaint(editor, before, after) {
                editor.replaceChildren();
                strategySegments(before, after).forEach(function (segment) {
                    if (!segment.changed || segment.text.trim() === '') {
                        editor.appendChild(document.createTextNode(segment.text));
                        return;
                    }

                    const mark = document.createElement('mark');
                    mark.className = 'strategy-suggestion-mark';
                    mark.textContent = segment.text;
                    editor.appendChild(mark);
                });
            }

            function strategySync(item) {
                const editor = item.querySelector('.strategy-editor');
                const textarea = item.querySelector('textarea');

                if (!editor || !textarea) {
                    return '';
                }

                textarea.value = strategyPlainText(editor).slice(0, 5000);

                return textarea.value;
            }

            document.querySelectorAll('[data-strategy-item]').forEach(function (item) {
                const editor = item.querySelector('.strategy-editor');
                const textarea = item.querySelector('textarea');

                if (!editor || !textarea) {
                    return;
                }

                editor.textContent = textarea.value;
                editor.addEventListener('input', function () {
                    strategySync(item);
                });
            });

            document.querySelector('form')?.addEventListener('submit', function () {
                document.querySelectorAll('[data-strategy-item]').forEach(strategySync);
            });

            document.querySelectorAll('.strategy-suggest').forEach(function (button) {
                button.addEventListener('click', function () {
                    const field = button.dataset.field;
                    const item = button.closest('[data-strategy-item]');
                    const editor = item?.querySelector('.strategy-editor');
                    const error = item?.querySelector('[data-suggestion-error]');
                    const form = button.closest('form');
                    const token = form?.querySelector('input[name="_token"]')?.value;

                    if (!field || !editor || !token || button.dataset.loading === '1') {
                        return;
                    }

                    const draft = strategySync(item);
                    const siblings = {};

                    document.querySelectorAll('[data-strategy-item]').forEach(function (row) {
                        if (row.dataset.strategyItem === field) {
                            return;
                        }

                        siblings[row.dataset.strategyItem] = strategySync(row);
                    });

                    const label = button.innerHTML;
                    button.dataset.loading = '1';
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + @json(__('app.strategy_field_suggesting'));
                    error?.classList.add('d-none');

                    fetch(@json(route('strategy.suggest')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify({
                            field: field,
                            draft: draft,
                            siblings: siblings,
                        }),
                    }).then(function (response) {
                        return response.json().then(function (payload) {
                            return { ok: response.ok, payload: payload };
                        });
                    }).then(function (result) {
                        if (!result.ok || !result.payload.suggestion) {
                            if (error) {
                                error.textContent = result.payload.message || @json(__('app.strategy_field_suggestion_failed'));
                                error.classList.remove('d-none');
                            }
                            return;
                        }

                        const suggestion = String(result.payload.suggestion).slice(0, 5000);
                        strategyPaint(editor, draft, suggestion);
                        strategySync(item);
                    }).catch(function () {
                        if (error) {
                            error.textContent = @json(__('app.strategy_field_suggestion_failed'));
                            error.classList.remove('d-none');
                        }
                    }).finally(function () {
                        button.dataset.loading = '0';
                        button.disabled = false;
                        button.innerHTML = label;
                    });
                });
            });
        </script>
    @endif
@endsection
