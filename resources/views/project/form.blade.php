@extends('layouts/layoutMaster')

@section('title', __('Projects'))

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/typography.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/katex.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/nouislider/nouislider.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/katex.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/quill.js')}}"></script>
<script src="{{asset('assets/vendor/libs/nouislider/nouislider.js')}}"></script>
@endsection

@section('page-style')
<style>
	#ai-usage-balance-slider.noUi-sm {
		height: 8px;
		margin: 6px 0 4px;
	}
	#ai-usage-balance-slider.noUi-sm .noUi-handle {
		width: 16px;
		height: 16px;
		right: -8px;
		top: -5px;
	}
	#ai-usage-balance-slider.noUi-sm .noUi-tooltip {
		font-size: 0.7rem;
		padding: 1px 4px;
	}
	textarea.js-auto-resize {
		overflow-y: hidden;
		resize: vertical;
		min-height: 2.5rem;
	}
</style>
@endsection

@section('page-script')
<script src="{{asset('assets/js/form-layouts.js')}}"></script>

<script>
    @php
        $tokenPricingTeam = ($data->team ?? null) ?: auth()->user()?->currentTeam;
        $tokenPricingService = app(\App\Services\ProjectBudgetSpecService::class);
        if (isset($data->id)) {
            $tokenPricingService->applyProjectTokenPresentation($data);
        } else {
            $tokenPricingService->applyTeamTokenPricing($tokenPricingTeam);
        }
        $tokenIncludeDefault = $tokenPricingService->includesTokenCharges();
        $tokenDiscriminateDefault = $tokenPricingService->discriminatesTokenLines();
        $tokenModelDefault = $tokenPricingService->resolvedTokenModel();
        if (old('data.token_include') !== null) {
            $tokenIncludeDefault = filter_var(old('data.token_include'), FILTER_VALIDATE_BOOLEAN);
        }
        if (old('data.token_discriminate') !== null) {
            $tokenDiscriminateDefault = filter_var(old('data.token_discriminate'), FILTER_VALIDATE_BOOLEAN);
        }
        $oldTokenModel = $tokenPricingService->normalizeTokenModel(old('data.token_model'));
        if ($oldTokenModel) {
            $tokenModelDefault = $oldTokenModel;
            $tokenPricingService->applyNormalizedTokenModel($oldTokenModel);
        }
        if (! $tokenIncludeDefault) {
            $tokenDiscriminateDefault = false;
        }
    @endphp
    var tokenInputRate = {{ $tokenPricingService->tokenInputRate() }};
    var tokenOutputRate = {{ $tokenPricingService->tokenOutputRate() }};
    var tokenBlendPerMillion = {{ $tokenPricingService->tokenBlendEurPerMillion() }};
    var defaultAiUsagePercent = {{ (int) \App\Services\ProjectBudgetSpecService::DEFAULT_AI_USAGE_PERCENT }};
    var modelAiReferenceBlend = {{ \App\Services\ProjectBudgetSpecService::MODEL_AI_REFERENCE_BLEND }};
    var modelAiLogScale = {{ \App\Services\ProjectBudgetSpecService::MODEL_AI_LOG_SCALE }};
    var quoteValueLocked = @json(isset($data->id) && $data->quoteValueIsLocked());
    var tokenInclude = @json($tokenIncludeDefault);
    var tokenDiscriminate = @json($tokenDiscriminateDefault);
    var resourceLevels = ['Junior', 'Mid', 'Senior', 'Consultor'];
    var levelRateWeights = { junior: 0.6, mid: 0.8, senior: 1, consultor: 1.2 };

    function autoResizeTextarea(el) {
        if (!el) return;
        el.style.height = 'auto';
        el.style.height = Math.max(el.scrollHeight, 40) + 'px';
    }

    function autoResizeBudgetTextareas() {
        document.querySelectorAll('textarea.js-auto-resize').forEach(autoResizeTextarea);
    }

    $(function() {
        // ClientSelect owns #enterprise_id (contact/responsible templates).
        if ($.fn.select2) {
            $('#participant_ids').select2({
                width: '100%',
                placeholder: 'Elegí quienes participan',
                closeOnSelect: false,
            });
            $('#category_id, #status_id, #token_model_select').select2({
                placeholder: "{{ __('Choose an option') }}",
                allowClear: true
            });
            // Note: #responsible_id is initialized by the team-users-select component
        }

        autoResizeBudgetTextareas();
        $(document).on('input', 'textarea.js-auto-resize', function() {
            autoResizeTextarea(this);
        });
    });

    @if(isset($data->id))
    // Function to delete project
    function deleteProject(projectId, projectName) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: `¿Deseas eliminar el proyecto "${projectName}"? Esta acción no se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            customClass: {
                confirmButton: 'btn btn-danger me-3',
                cancelButton: 'btn btn-label-secondary'
            },
            buttonsStyling: false
        }).then(function (result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/project/${projectId}`,
                    type: 'DELETE',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                    },
                    success: function (response) {
                        Swal.fire({
                            title: 'Eliminado!',
                            text: 'El proyecto ha sido eliminado exitosamente.',
                            icon: 'success',
                            customClass: {
                                confirmButton: 'btn btn-success'
                            },
                            buttonsStyling: false
                        }).then(function() {
                            // Redirect to projects list
                            window.location.href = '{{ route("project-list") }}';
                        });
                    },
                    error: function (response) {
                        Swal.fire({
                            title: 'Error',
                            text: response.responseJSON?.message || 'Ha ocurrido un error al eliminar el proyecto',
                            icon: 'error',
                            customClass: {
                                confirmButton: 'btn btn-primary'
                            },
                            buttonsStyling: false
                        });
                    }
                });
            }
        });
    }
    @endif

    // Generate budget data from "Project notes" + "Budget received" (AI)
    $('#generate-budget-spec').on('click', function() {
        var notes = $('#description').val().trim();
        var budgetReceived = $('#data_budget_given').val().trim();
        var parts = [];
        if (notes) {
            parts.push('{{ __("Project Notes") }}:\n' + notes);
        }
        if (budgetReceived) {
            parts.push('{{ __("Budget received") }}:\n' + budgetReceived);
        }
        var budgetGiven = parts.join('\n\n');
        if (!budgetGiven) {
            Swal.fire({
                title: '{{ __("Description required") }}',
                text: '{{ __("Write or paste the budget text first, then click Generate.") }}',
                icon: 'info',
                customClass: { confirmButton: 'btn btn-primary' },
                buttonsStyling: false
            });
            return;
        }
        var $btn = $(this);
        var budgetSpecTimeoutMs = {{ max(60, (int) config('ai.budget_spec_timeout', 180)) * 1000 }};
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>{{ __("Generating...") }}');
        $.ajax({
            url: '{{ route("project.generate-budget-spec") }}',
            type: 'POST',
            timeout: budgetSpecTimeoutMs,
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                budget_given: budgetGiven
            },
            success: function(res) {
                if (res.success) {
                    $('#data_ai_interpretation').val(res.ai_interpretation || '');
                    $('#data_dimension').val(res.dimension || '');
                    $('#data_estimated_times').val(res.estimated_times || '');
                    $('#data_resources').val(res.resources || '');
                    autoResizeBudgetTextareas();
                    if (res.suggested_tasks && res.suggested_tasks.length) {
                        res.suggested_tasks.forEach(function(t) {
                            if (t.resource_level === undefined) t.resource_level = '';
                            if (t.unit_price === undefined) t.unit_price = '';
                            if (t.estimated_tokens === undefined || t.estimated_tokens === null || t.estimated_tokens === '') {
                                var hours = parseFloat(t.estimated_hours);
                                t.estimated_tokens = (!isNaN(hours) && hours > 0) ? Math.round(hours * 20000) : 0;
                            }
                        });
                        var html = buildSuggestedTasksTable(res.suggested_tasks);
                        $('#suggested-tasks-container').html(html).removeClass('d-none');
                        $('#data_suggested_tasks').val(JSON.stringify(res.suggested_tasks));
                        applyTokenConsumption(res.token_consumption, res.suggested_tasks);
                        refreshBudgetPreview();
                    } else {
                        $('#suggested-tasks-container').addClass('d-none').empty();
                        $('#data_suggested_tasks').val('');
                        applyTokenConsumption(res.token_consumption, []);
                        refreshBudgetPreview();
                    }
                } else {
                    Swal.fire({
                        title: '{{ __("Error") }}',
                        text: res.message || '{{ __("Could not generate budget spec.") }}',
                        icon: 'error',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                    });
                }
            },
            error: function(xhr, textStatus) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : '{{ __("Request failed. Try again.") }}';
                if (textStatus === 'timeout') {
                    msg = '{{ __("The request took too long. Please try again.") }}';
                }
                Swal.fire({
                    title: '{{ __("Error") }}',
                    text: msg,
                    icon: 'error',
                    customClass: { confirmButton: 'btn btn-primary' },
                    buttonsStyling: false
                });
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="ti ti-sparkles me-1"></i>{{ __("Generate from budget text") }}');
            }
        });
    });

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function formatTokenCount(tokens) {
        tokens = parseInt(tokens, 10) || 0;
        if (tokens <= 0) return '—';
        if (tokens >= 1000000) {
            var m = tokens / 1000000;
            return String(m.toFixed(1)).replace('.', ',').replace(/,?0+$/, '').replace(/,$/, '') + ' M';
        }
        if (tokens >= 1000) {
            return String((tokens / 1000).toFixed(1)).replace('.', ',') + ' K';
        }
        return String(tokens);
    }
    function formatHoursHuman(hours) {
        var rounded = ceilHoursToHalfHour(hours);
        if (rounded === null || rounded <= 0) return '—';
        var totalMinutes = Math.round(rounded * 60);
        var wholeHours = Math.floor(totalMinutes / 60);
        var minutes = totalMinutes % 60;
        if (wholeHours > 0 && minutes === 30) {
            return wholeHours === 1 ? '1 hora y media' : wholeHours + ' horas y media';
        }
        if (wholeHours > 0 && minutes > 0) {
            return wholeHours + ' h ' + minutes + ' min';
        }
        if (wholeHours > 0) {
            return wholeHours + ' h';
        }
        return minutes + ' min';
    }
    function ceilHoursToHalfHour(hours) {
        var h = parseFloat(hours);
        if (isNaN(h) || h < 0) return null;
        if (h <= 0) return 0;
        return (Math.ceil((h * 60) / 30) * 30) / 60;
    }
    function roundLaborToHalfHourSteps(labor, hours) {
        var roundedHours = ceilHoursToHalfHour(hours);
        if (roundedHours === null) {
            return { hours: 0, labor: labor };
        }
        var laborValue = parseFloat(labor);
        if (isNaN(laborValue)) {
            return { hours: roundedHours, labor: NaN };
        }
        var h = parseFloat(hours);
        if (isNaN(h) || h <= 0) {
            return { hours: roundedHours, labor: Math.round(laborValue * 100) / 100 };
        }
        if (Math.abs(roundedHours - h) < 0.00001) {
            return { hours: roundedHours, labor: Math.round(laborValue * 100) / 100 };
        }
        return {
            hours: roundedHours,
            labor: Math.round(laborValue * (roundedHours / h) * 100) / 100
        };
    }
    function formatEuros(amount) {
        var n = parseFloat(amount);
        if (isNaN(n)) return '—';
        var rounded = Math.round(n * 100) / 100;
        var parts = rounded.toFixed(2).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return parts[0] + ',' + parts[1] + ' €';
    }
    function resolveTaskTokens(t) {
        var tokens = parseInt(t.estimated_tokens, 10);
        if (isNaN(tokens) || tokens <= 0) {
            var hours = parseFloat(t.estimated_hours);
            tokens = (!isNaN(hours) && hours > 0) ? Math.round(hours * 20000) : 0;
        }
        return tokens > 0 ? tokens : 0;
    }
    function taskTokenPricing(t, savingsPercent) {
        var tokens = resolveTaskTokens(t);
        var input = Math.round(tokens * 0.7);
        var output = Math.max(0, tokens - input);
        var cost = (input / 1000000) * tokenInputRate + (output / 1000000) * tokenOutputRate;
        var savings = (savingsPercent != null && savingsPercent !== '') ? parseFloat(savingsPercent) : 57;
        if (isNaN(savings)) savings = 57;
        var remaining = Math.max(0.01, 1 - (savings / 100));
        var billable = cost / remaining;
        // Client-facing volume: show tokens as if MCP optimization were not applied.
        var displayTokens = Math.round(tokens / remaining);
        return {
            tokens: tokens,
            displayTokens: displayTokens,
            cost: cost,
            billable: billable,
            moneySaved: Math.max(0, billable - cost),
            hoursSaved: Math.max(0, (displayTokens - tokens) / 20000),
            savings: savings
        };
    }
    function buildTokenConsumptionText(tasks) {
        var lines = [];
        var savings = parseFloat($('#data_token_consumption_savings').val()) || 57;
        (tasks || []).forEach(function(t) {
            if (t.included === false) return;
            var title = (t.title || '').trim();
            if (!title) return;
            var pricing = taskTokenPricing(t, savings);
            if (pricing.tokens <= 0) return;
            lines.push(title + ': ' + formatTokenCount(pricing.displayTokens) + ' · ' + formatEuros(pricing.billable));
        });
        return lines.join('\n');
    }
    function tokenConsumptionNotes(value) {
        if (!value) return '';
        if (typeof value === 'string') return value;
        if (typeof value === 'object' && value.notes != null) {
            if (Array.isArray(value.notes)) return value.notes.join('\n');
            return String(value.notes);
        }
        return '';
    }
    function applyTokenConsumption(tokenConsumption, tasks) {
        var notes = tokenConsumptionNotes(tokenConsumption);
        if (!notes) notes = buildTokenConsumptionText(tasks || []);
        $('#data_token_consumption_notes').val(notes);
        autoResizeTextarea(document.getElementById('data_token_consumption_notes'));

        var total = 0;
        (tasks || []).forEach(function(t) {
            if (t.included === false) return;
            var tokens = parseInt(t.estimated_tokens, 10);
            if (isNaN(tokens) || tokens <= 0) {
                var hours = parseFloat(t.estimated_hours);
                tokens = (!isNaN(hours) && hours > 0) ? Math.round(hours * 20000) : 0;
            }
            total += tokens;
        });
        if (tokenConsumption && typeof tokenConsumption === 'object' && parseInt(tokenConsumption.total_tokens, 10) > 0) {
            total = parseInt(tokenConsumption.total_tokens, 10) || total;
        }
        var input = Math.round(total * 0.7);
        var output = Math.max(0, total - input);
        if (tokenConsumption && typeof tokenConsumption === 'object') {
            if (parseInt(tokenConsumption.input_tokens, 10) > 0) input = parseInt(tokenConsumption.input_tokens, 10);
            if (parseInt(tokenConsumption.output_tokens, 10) > 0) output = parseInt(tokenConsumption.output_tokens, 10);
        }
        var cost = (input / 1000000) * tokenInputRate + (output / 1000000) * tokenOutputRate;
        var savings = 57;
        if (tokenConsumption && typeof tokenConsumption === 'object' && tokenConsumption.savings_percent != null && tokenConsumption.savings_percent !== '') {
            savings = parseFloat(tokenConsumption.savings_percent) || 57;
        }
        var billable = cost / Math.max(0.01, 1 - (savings / 100));
        if (tokenConsumption && typeof tokenConsumption === 'object') {
            if (tokenConsumption.cost_euros != null && tokenConsumption.cost_euros !== '') cost = parseFloat(tokenConsumption.cost_euros) || cost;
            if (tokenConsumption.billable_euros != null && tokenConsumption.billable_euros !== '') billable = parseFloat(tokenConsumption.billable_euros) || billable;
        }
        $('#data_token_consumption_input').val(input);
        $('#data_token_consumption_output').val(output);
        $('#data_token_consumption_total').val(total);
        $('#data_token_consumption_cost').val(cost.toFixed(2));
        $('#data_token_consumption_savings').val(savings);
        $('#data_token_consumption_billable').val(billable.toFixed(2));
    }
    function syncTokenConsumptionFromTasks() {
        var raw = $('#data_suggested_tasks').val();
        var tasks = [];
        try {
            if (raw) tasks = JSON.parse(raw);
        } catch (e) { return; }
        applyTokenConsumption({ notes: buildTokenConsumptionText(tasks) }, tasks);
    }
    function roundBudgetMoney(amount) {
        var n = parseFloat(amount);
        if (isNaN(n)) return 0;
        return Math.round(n * 100) / 100;
    }
    function parseBudgetNumber(value) {
        if (value === null || value === undefined || value === '') return 0;
        var n = parseFloat(value);
        return isNaN(n) ? 0 : n;
    }
    function resolveHourlyRate(task, siblings) {
        var stored = parseFloat(task.hourly_rate);
        if (!isNaN(stored) && stored >= 0) return stored;
        var hours = parseFloat(task.estimated_hours);
        var price = parseFloat(task.unit_price);
        if (!isNaN(hours) && hours > 0 && !isNaN(price) && price >= 0) return price / hours;
        if ((isNaN(hours) || hours <= 0) && !isNaN(price) && price > 0) return price;
        var rates = [];
        (siblings || []).forEach(function(line) {
            if (line === task) return;
            var rate = resolveHourlyRate(line, []);
            if (rate !== null && rate > 0) rates.push(rate);
        });
        if (!rates.length) return null;
        rates.sort(function(a, b) { return a - b; });
        return rates[Math.floor(rates.length / 2)];
    }
    function levelWeight(level) {
        var key = String(level || '').trim().toLowerCase();
        return Object.prototype.hasOwnProperty.call(levelRateWeights, key) ? levelRateWeights[key] : 1;
    }
    function aiUsagePercentFromRates(prompt, completion) {
        if (!tokenInclude) return 0;
        if (prompt === null && completion === null) return defaultAiUsagePercent;
        var blend = Math.round((Math.max(0, prompt || 0) * 0.7 + Math.max(0, completion || 0) * 0.3) * 10000) / 10000;
        if (blend <= 0) return 0;
        var raw = defaultAiUsagePercent + modelAiLogScale * Math.log10(blend / modelAiReferenceBlend);
        return Math.max(0, Math.min(100, Math.round(raw)));
    }
    function setAiUsagePercent(percent) {
        var pct = Math.max(0, Math.min(100, Math.round(percent)));
        $('#data_ai_usage_percent').val(pct);
        $('#data_ai_usage_percent_label').text(pct + '%');
        var slider = document.getElementById('ai-usage-balance-slider');
        if (slider && slider.noUiSlider) slider.noUiSlider.set(pct);
        var helper = document.getElementById('token-model-helper');
        if (helper) {
            helper.textContent = 'Aplica ' + pct + '% IA: un modelo más caro baja horas y pasa peso a tokens.';
        }
    }
    function applyRatesFromModel(prompt, completion) {
        if (prompt !== null && completion !== null) {
            tokenInputRate = Math.max(0, prompt);
            tokenOutputRate = Math.max(0, completion);
            tokenBlendPerMillion = Math.round((tokenInputRate * 0.7 + tokenOutputRate * 0.3) * 10000) / 10000;
        }
        setAiUsagePercent(aiUsagePercentFromRates(prompt, completion));
    }
    function writeTokenModelFields(model) {
        $('#data_token_model_id').val(model.id || '');
        $('#data_token_model_name').val(model.name || '');
        $('#data_token_model_prompt').val(model.prompt_per_million === null || model.prompt_per_million === undefined ? '' : model.prompt_per_million);
        $('#data_token_model_completion').val(model.completion_per_million === null || model.completion_per_million === undefined ? '' : model.completion_per_million);
    }
    function syncTokenFlags() {
        tokenInclude = $('#token_include_toggle').prop('checked');
        tokenDiscriminate = tokenInclude && $('#token_discriminate_toggle').prop('checked');
        $('#data_token_include').val(tokenInclude ? '1' : '0');
        $('#data_token_discriminate').val(tokenDiscriminate ? '1' : '0');
        $('#token_discriminate_toggle').prop('disabled', !tokenInclude || quoteValueLocked);
        if (!tokenInclude) $('#token_discriminate_toggle').prop('checked', false);
        syncTokenColumnVisibility();
    }
    function syncTokenColumnVisibility() {
        $('.suggested-token-col').toggleClass('d-none', !tokenInclude || !tokenDiscriminate);
    }
    function hoursInputHtml(index, hours) {
        var value = (hours != null && hours !== '') ? escapeHtml(String(hours)) : '';
        return '<input type="number" step="0.5" min="0" class="form-control form-control-sm text-end suggested-estimated-hours" data-index="' + index + '" value="' + value + '"' + (quoteValueLocked ? ' readonly' : '') + '>';
    }
    function levelSelectHtml(index, current) {
        var currentText = String(current || '').trim();
        var options = resourceLevels.slice();
        if (currentText && !options.some(function(level) { return level.toLowerCase() === currentText.toLowerCase(); })) {
            options.push(currentText);
        }
        var h = '<select class="form-select form-select-sm suggested-resource-level" data-index="' + index + '"' + (quoteValueLocked ? ' disabled' : '') + '>';
        if (!currentText) h += '<option value=""></option>';
        options.forEach(function(level) {
            var selected = currentText.toLowerCase() === level.toLowerCase() ? ' selected' : '';
            h += '<option value="' + escapeHtml(level) + '"' + selected + '>' + escapeHtml(level) + '</option>';
        });
        h += '</select>';
        return h;
    }
    function setRowPrice(index, price) {
        $('.suggested-unit-price[data-index="' + index + '"]').val(price === '' || price === null ? '' : price);
    }
    function readSuggestedTasks() {
        var raw = $('#data_suggested_tasks').val();
        try {
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return null;
        }
    }
    function buildSuggestedTasksTable(tasks) {
        var tokenCol = (!tokenInclude || !tokenDiscriminate) ? ' d-none' : '';
        var h = '<p class="text-muted small mb-2">' + (tasks.length === 1 ? '{{ __("1 task suggested") }}' : '{{ __(":count tasks suggested") }}'.replace(':count', tasks.length)) + '</p><div class="table-responsive"><table class="table table-sm table-bordered" id="suggested-tasks-table"><thead><tr><th>{{ __("En el presupuesto") }}</th><th>{{ __("Task") }}</th><th class="text-center">{{ __("Category") }}</th><th class="text-end">{{ __("Hours") }}</th><th class="text-end suggested-token-col' + tokenCol + '">{{ __("Tokens") }}</th><th class="text-end">{{ __("Level") }}</th><th class="text-end">{{ __("Value") }}</th></tr></thead><tbody>';
        tasks.forEach(function(t, i) {
            var included = t.included !== false;
            if (typeof t.included === 'undefined') t.included = true;
            var title = escapeHtml(t.title || '—');
            var cat = escapeHtml(t.category_name || '—');
            var tokens = (t.estimated_tokens != null && t.estimated_tokens !== '') ? Number(t.estimated_tokens) : '';
            var unitPrice = (t.unit_price != null && t.unit_price !== '') ? escapeHtml(String(t.unit_price)) : '';
            h += '<tr data-index="' + i + '"><td class="align-middle"><label class="form-check mb-0"><input type="checkbox" class="form-check-input suggested-task-included" data-index="' + i + '" ' + (included ? 'checked' : '') + '><span class="form-check-label small">En el presupuesto</span></label></td><td>' + title + '</td><td class="text-center">' + cat + '</td><td class="text-end">' + hoursInputHtml(i, t.estimated_hours) + '</td>';
            h += '<td class="text-end suggested-token-col' + tokenCol + '"><input type="number" step="1" min="0" class="form-control form-control-sm text-end suggested-estimated-tokens" data-index="' + i + '" value="' + tokens + '" placeholder="0"></td>';
            h += '<td class="text-end">' + levelSelectHtml(i, t.resource_level) + '</td>';
            h += '<td class="text-end"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end suggested-unit-price" data-index="' + i + '" value="' + unitPrice + '" placeholder="0"' + (quoteValueLocked ? ' readonly' : '') + '></td></tr>';
        });
        h += '</tbody></table></div>';
        return h;
    }

    function textToHtmlBlocks(text) {
        var trimmed = String(text || '').trim();
        if (!trimmed) return '';
        return trimmed.split(/\n+/).map(function(line) {
            return '<p>' + escapeHtml(line.trim()) + '</p>';
        }).join('');
    }
    function setBudgetPreviewHtml(html) {
        var safe = html || '';
        $('#data_budget_preview_html').val(safe);
        if (window.budgetPreviewQuill) {
            var delta = window.budgetPreviewQuill.clipboard.convert(safe);
            window.budgetPreviewQuill.setContents(delta, 'silent');
        }
    }
    function resolveAiUsagePercent() {
        var raw = parseFloat($('#data_ai_usage_percent').val());
        if (isNaN(raw) || raw < 0) return defaultAiUsagePercent;
        if (raw > 100) return 100;
        return raw;
    }
    function laborValueAfterAi(unitPrice, aiUsagePercent) {
        var balanced = applyHoursTokensBalance(unitPrice, 1, 0, 57, aiUsagePercent);
        return balanced.labor;
    }
    function applyHoursTokensBalance(unitPrice, hours, baseTokens, savingsPercent, balancePercent) {
        var balance = (balancePercent != null && balancePercent !== '') ? parseFloat(balancePercent) : 0;
        if (isNaN(balance) || balance < 0) balance = 0;
        if (balance > 100) balance = 100;
        var savings = (savingsPercent != null && savingsPercent !== '') ? parseFloat(savingsPercent) : 57;
        if (isNaN(savings) || savings < 0) savings = 57;
        if (savings > 99) savings = 99;
        var remainingFactor = Math.max(0.01, 1 - (savings / 100));
        var blendPerMillion = tokenBlendPerMillion;
        var maxDiscount = 30;

        var hoursValue = parseFloat(hours);
        if (isNaN(hoursValue) || hoursValue < 0) hoursValue = 0;
        var tokensBase = parseInt(baseTokens, 10);
        if (isNaN(tokensBase) || tokensBase < 0) tokensBase = 0;

        var baseInput = Math.round(tokensBase * 0.7);
        var baseOutput = Math.max(0, tokensBase - baseInput);
        var baseCost = (baseInput / 1000000) * tokenInputRate + (baseOutput / 1000000) * tokenOutputRate;
        var baseBillable = Math.round((baseCost / remainingFactor) * 100) / 100;

        var originalLabor = parseFloat(unitPrice);
        if (isNaN(originalLabor) || originalLabor < 0) originalLabor = 0;
        var originalTotal = Math.round((originalLabor + baseBillable) * 100) / 100;

        var transferredHours = Math.round(hoursValue * (balance / 100) * 10000) / 10000;
        var hoursCharged = Math.round(Math.max(0, hoursValue - transferredHours) * 10000) / 10000;
        var labor = isNaN(parseFloat(unitPrice))
            ? NaN
            : Math.round(originalLabor * (1 - (balance / 100)) * 100) / 100;
        var laborCharged = isNaN(labor) ? 0 : labor;

        var extraTokens = Math.round(transferredHours * 20000);
        var tokens = tokensBase + extraTokens;

        // 0% → full original; 100% → original × 70% (30% discount).
        var discountFactor = 1 - ((maxDiscount / 100) * (balance / 100));
        var targetTotal = Math.round(originalTotal * discountFactor * 100) / 100;
        var tokenBillableTarget = Math.max(0, Math.round((targetTotal - laborCharged) * 100) / 100);
        var costNeeded = tokenBillableTarget * remainingFactor;
        var tokensForTarget = Math.ceil((costNeeded * 1000000) / blendPerMillion);
        if (tokensForTarget > tokens) tokens = tokensForTarget;

        var input = Math.round(tokens * 0.7);
        var output = Math.max(0, tokens - input);
        var cost = (input / 1000000) * tokenInputRate + (output / 1000000) * tokenOutputRate;
        var tokenBillable = Math.round((cost / remainingFactor) * 100) / 100;
        var displayTokens = Math.round(tokens / remainingFactor);

        return {
            hours: hoursCharged,
            labor: labor,
            tokens: tokens,
            displayTokens: displayTokens,
            cost: cost,
            tokenBillable: tokenBillable,
            transferredHours: transferredHours,
            originalTotal: originalTotal,
            targetTotal: targetTotal
        };
    }
    function refreshBudgetPreview() {
        var raw = $('#data_suggested_tasks').val();
        var tasks = [];
        try {
            if (raw) tasks = JSON.parse(raw);
        } catch (e) { }
        var dimension = ($('#data_dimension').val() || '').trim();
        var estimatedTimes = ($('#data_estimated_times').val() || '').trim();
        var resources = ($('#data_resources').val() || '').trim();
        var savings = parseFloat($('#data_token_consumption_savings').val()) || 57;
        var aiUsage = tokenInclude ? resolveAiUsagePercent() : 0;

        var hasContent = tasks.length > 0 || dimension || estimatedTimes || resources;
        if (!hasContent) {
            $('#budget-preview-container').addClass('d-none');
            setBudgetPreviewHtml('');
            return;
        }
        $('#budget-preview-container').removeClass('d-none');

        var html = '';
        if (dimension) {
            html += '<h3>{{ __("Dimension") }}</h3>' + textToHtmlBlocks(dimension);
        }
        if (estimatedTimes) {
            html += '<h3>{{ __("Estimated times") }}</h3>' + textToHtmlBlocks(estimatedTimes);
        }
        if (resources) {
            html += '<h3>{{ __("Resources") }}</h3>' + textToHtmlBlocks(resources);
        }

        var totalLabor = 0;
        var totalTokenBillable = 0;
        var totalHours = 0;
        var taskItems = '';
        tasks.forEach(function(t) {
            var title = (t.title || '—');
            var included = t.included !== false;
            var hours = (t.estimated_hours != null && t.estimated_hours !== '') ? parseFloat(t.estimated_hours) : 0;
            var level = (t.resource_level != null && t.resource_level !== '') ? String(t.resource_level) : '—';
            var price = (t.unit_price != null && t.unit_price !== '') ? parseFloat(t.unit_price) : NaN;
            var baseTokens = resolveTaskTokens(t);
            var balanced = applyHoursTokensBalance(price, hours, baseTokens, savings, aiUsage);
            var rounded = roundLaborToHalfHourSteps(balanced.labor, balanced.hours);
            var laborCharged = rounded.labor;
            var hoursCharged = rounded.hours;
            var tokenBillable = tokenInclude ? balanced.tokenBillable : 0;
            var displayTokens = tokenInclude ? balanced.displayTokens : 0;
            var shownLabor = laborCharged;
            if (tokenInclude && !tokenDiscriminate && !isNaN(laborCharged)) {
                shownLabor = roundBudgetMoney(laborCharged + tokenBillable);
            }
            var details = [
                formatHoursHuman(hoursCharged),
                level,
                !isNaN(shownLabor) ? formatEuros(shownLabor) : '—'
            ];
            if (tokenInclude && tokenDiscriminate && (balanced.tokens > 0 || tokenBillable > 0)) {
                details.push(
                    '{{ __("Tokens") }} ' + formatTokenCount(displayTokens)
                    + ' · ' + formatEuros(tokenBillable)
                );
            }
            var detailText = details.join(' · ');
            // Quill splits nested <p> inside <li> into separate bullets — keep title + details in one <p>.
            var itemHtml = '<p style="margin:0 0 1.15em 0;">'
                + '<strong>' + escapeHtml(title) + '</strong><br>'
                + '<span style="font-size:0.85em;line-height:1.35;opacity:0.85;">' + escapeHtml(detailText) + '</span>'
                + '</p>';
            if (included) {
                taskItems += itemHtml;
                if (!isNaN(laborCharged)) totalLabor += laborCharged;
                totalTokenBillable += tokenBillable;
                totalHours += hoursCharged;
            } else {
                taskItems += '<p style="margin:0 0 1.15em 0;"><s>'
                    + '<strong>' + escapeHtml(title) + '</strong><br>'
                    + '<span style="font-size:0.85em;line-height:1.35;opacity:0.85;">' + escapeHtml(detailText) + '</span>'
                    + '</s></p>';
            }
        });
        if (taskItems) {
            html += '<h3>{{ __("Summary of requested quote and values") }}</h3>' + taskItems;
            var grandTotal = Math.round(totalLabor + totalTokenBillable);
            var discount = parseFloat($('#discount').val());
            if (isNaN(discount) || discount < 0) discount = 0;
            if (discount > 100) discount = 100;
            // Commercial discount applies to labor only; tokens stay full price.
            var laborDiscountAmount = Math.round(totalLabor * (discount / 100) * 100) / 100;
            var discountedLabor = Math.round((totalLabor - laborDiscountAmount) * 100) / 100;
            var discountedTotal = Math.round(discountedLabor + totalTokenBillable);
            var payableTotal = discount > 0 ? discountedTotal : grandTotal;
            var discountLabel = String(discount).replace('.', ',');

            html += '<p><br></p><p><strong>{{ __("Budget") }}</strong></p>';
            if (tokenInclude && tokenDiscriminate) {
                html += '<p>{{ __("Labor") }}: ' + formatEuros(totalLabor) + '</p>';
                html += '<p>{{ __("Tokens") }}: ' + formatEuros(totalTokenBillable) + '</p>';
            }
            html += '<p>{{ __("Subtotal") }}: ' + formatEuros(grandTotal) + '</p>';
            if (discount > 0) {
                html += '<p>{{ __("Discount on labor") }} (−' + discountLabel + '%): −'
                    + formatEuros(laborDiscountAmount) + '</p>';
            }
            html += '<p><strong>{{ __("Total") }}: '
                + formatEuros(payableTotal)
                + ' + {{ __("I.V.A.") }}</strong></p>';
            if (!quoteValueLocked) {
                $('#project_price').val(payableTotal);
            }

            var weeks = totalHours > 0 ? Math.ceil(totalHours / 40) : 0;
            html += '<p>' + escapeHtml('{{ __("Estimated development time, :weeks weeks after the budget has been confirmed.") }}'.replace(':weeks', weeks)) + '</p>';
        }

        setBudgetPreviewHtml(html);
    }

    $(document).on('change input', '#discount', function() {
        refreshBudgetPreview();
    });

    $(document).on('change', '.suggested-task-included', function() {
        var idx = parseInt($(this).data('index'), 10);
        var raw = $('#data_suggested_tasks').val();
        var tasks = [];
        try {
            if (raw) tasks = JSON.parse(raw);
        } catch (e) { return; }
        if (tasks[idx] === undefined) return;
        tasks[idx].included = $(this).prop('checked');
        $('#data_suggested_tasks').val(JSON.stringify(tasks));
        syncTokenConsumptionFromTasks();
        refreshBudgetPreview();
    });

    $(document).on('change input', '#data_ai_usage_percent', function() {
        refreshBudgetPreview();
    });

    $(document).on('change input', '.suggested-resource-level, .suggested-unit-price, .suggested-estimated-tokens, .suggested-estimated-hours', function() {
        if (quoteValueLocked && !$(this).hasClass('suggested-estimated-tokens')) return;
        var idx = parseInt($(this).data('index'), 10);
        var tasks = readSuggestedTasks();
        if (!tasks || tasks[idx] === undefined) return;
        var task = tasks[idx];
        if ($(this).hasClass('suggested-estimated-hours')) {
            var hourVal = $(this).val();
            var hours = parseBudgetNumber(hourVal);
            var rate = resolveHourlyRate(task, tasks);
            task.estimated_hours = hourVal === '' ? '' : hours;
            if (rate !== null) {
                task.hourly_rate = rate;
                task.unit_price = roundBudgetMoney(Math.max(0, hours) * rate);
                setRowPrice(idx, task.unit_price);
            }
        } else if ($(this).hasClass('suggested-unit-price')) {
            var val = $(this).val();
            var num = parseFloat(val);
            task.unit_price = (isNaN(num) || val === '') ? '' : num;
            var pricedHours = parseFloat(task.estimated_hours);
            if (!isNaN(num) && !isNaN(pricedHours) && pricedHours > 0) {
                task.hourly_rate = num / pricedHours;
            } else if (!isNaN(num) && num > 0) {
                task.hourly_rate = num;
            }
        } else if ($(this).hasClass('suggested-estimated-tokens')) {
            var tokenVal = $(this).val();
            var tokenNum = parseInt(tokenVal, 10);
            task.estimated_tokens = (isNaN(tokenNum) || tokenVal === '') ? 0 : tokenNum;
        } else {
            var nextLevel = $(this).val();
            var previousLevel = task.resource_level || '';
            var previousWeight = levelWeight(previousLevel);
            var nextWeight = levelWeight(nextLevel);
            var currentRate = resolveHourlyRate(task, tasks);
            task.resource_level = nextLevel;
            if (currentRate !== null && previousWeight > 0) {
                var nextRate = currentRate * (nextWeight / previousWeight);
                var levelHours = parseFloat(task.estimated_hours);
                task.hourly_rate = nextRate;
                if (!isNaN(levelHours) && levelHours > 0) {
                    task.unit_price = roundBudgetMoney(Math.max(0, levelHours) * nextRate);
                } else {
                    task.unit_price = roundBudgetMoney(Math.max(0, nextRate));
                }
                setRowPrice(idx, task.unit_price);
            }
        }
        $('#data_suggested_tasks').val(JSON.stringify(tasks));
        syncTokenConsumptionFromTasks();
        refreshBudgetPreview();
    });

    $(document).on('change', '#token_include_toggle, #token_discriminate_toggle', function() {
        syncTokenFlags();
        var prompt = parseFloat($('#data_token_model_prompt').val());
        var completion = parseFloat($('#data_token_model_completion').val());
        applyRatesFromModel(isNaN(prompt) ? null : prompt, isNaN(completion) ? null : completion);
        syncTokenConsumptionFromTasks();
        refreshBudgetPreview();
    });

    $(document).on('change', '#token_model_select', function() {
        var option = $(this).find('option:selected');
        var prompt = option.data('prompt');
        var completion = option.data('completion');
        prompt = prompt === undefined || prompt === '' ? null : parseFloat(prompt);
        completion = completion === undefined || completion === '' ? null : parseFloat(completion);
        writeTokenModelFields({
            id: option.val(),
            name: option.data('name') || option.text(),
            prompt_per_million: prompt,
            completion_per_million: completion
        });
        applyRatesFromModel(prompt, completion);
        syncTokenConsumptionFromTasks();
        refreshBudgetPreview();
    });

    $(function() {
        var existingHtml = ($('#data_budget_preview_html').val() || '').trim();
        if (typeof Quill !== 'undefined' && document.getElementById('budget-preview-editor')) {
            window.budgetPreviewQuill = new Quill('#budget-preview-editor', {
                theme: 'snow',
                modules: {
                    toolbar: '#budget-preview-toolbar'
                },
                placeholder: '{{ __("Generate from budget text to see the summary here.") }}'
            });
            if (existingHtml !== '' && existingHtml !== '<p><br></p>' && existingHtml !== '<p></p>') {
                var delta = window.budgetPreviewQuill.clipboard.convert(existingHtml);
                window.budgetPreviewQuill.setContents(delta, 'silent');
                $('#budget-preview-container').removeClass('d-none');
            }
            window.budgetPreviewQuill.on('text-change', function() {
                $('#data_budget_preview_html').val(window.budgetPreviewQuill.root.innerHTML);
            });
        }

        $('form.card-body').on('submit', function() {
            if (window.budgetPreviewQuill) {
                $('#data_budget_preview_html').val(window.budgetPreviewQuill.root.innerHTML);
            }
        });

        if (!$('#data_token_consumption_notes').val()) {
            syncTokenConsumptionFromTasks();
        }

        var balanceSlider = document.getElementById('ai-usage-balance-slider');
        var balanceInput = document.getElementById('data_ai_usage_percent');
        var balanceLabel = document.getElementById('data_ai_usage_percent_label');
            if (balanceSlider && typeof noUiSlider !== 'undefined') {
            if (quoteValueLocked) balanceSlider.setAttribute('disabled', true);
            var startBalance = parseFloat(balanceInput ? balanceInput.value : defaultAiUsagePercent);
            if (isNaN(startBalance) || startBalance < 0) startBalance = defaultAiUsagePercent;
            if (startBalance > 100) startBalance = 100;
            noUiSlider.create(balanceSlider, {
                start: [startBalance],
                step: 1,
                connect: [true, false],
                tooltips: {
                    to: function(value) { return Math.round(value) + '%'; }
                },
                range: { min: 0, max: 100 },
                direction: (typeof isRtl !== 'undefined' && isRtl) ? 'rtl' : 'ltr'
            });
            balanceSlider.noUiSlider.on('update', function(values) {
                var pct = Math.round(parseFloat(values[0]));
                if (balanceInput) balanceInput.value = pct;
                if (balanceLabel) balanceLabel.textContent = pct + '%';
            });
            balanceSlider.noUiSlider.on('change', function() {
                refreshBudgetPreview();
            });
        }

        syncTokenFlags();
        loadTokenModelCatalog();
        // Rebuild preview so metrics stay under each title (Quill-safe) and AI % applies.
        refreshBudgetPreview();
    });

    function catalogPriceLabel(value) {
        if (value === null || value === undefined || value === '') return '—';
        var n = parseFloat(value);
        if (isNaN(n)) return '—';
        if (n === 0) return 'Gratis';
        var decimals = n < 0.01 ? 4 : 2;
        return '$' + n.toFixed(decimals).replace('.', ',');
    }
    function appendTokenModelOption(model, selectedId) {
        var select = document.getElementById('token_model_select');
        if (!select || !model || !model.id) return;
        if (select.querySelector('option[value="' + CSS.escape(model.id) + '"]')) return;
        var option = document.createElement('option');
        option.value = model.id;
        option.dataset.name = model.name || model.id;
        option.dataset.prompt = model.prompt_per_million == null ? '' : model.prompt_per_million;
        option.dataset.completion = model.completion_per_million == null ? '' : model.completion_per_million;
        option.textContent = (model.name || model.id) + ' · ' + catalogPriceLabel(model.prompt_per_million) + ' / ' + catalogPriceLabel(model.completion_per_million);
        if (model.id === selectedId) option.selected = true;
        select.appendChild(option);
    }
    function loadTokenModelCatalog() {
        var select = document.getElementById('token_model_select');
        if (!select) return;
        var selectedId = select.value;
        fetch('https://mcp.idoneo.dev/models.json')
            .then(function(response) { return response.ok ? response.json() : null; })
            .then(function(payload) {
                var models = payload && payload.models ? payload.models : payload;
                if (!Array.isArray(models)) return;
                models.forEach(function(item) {
                    if (!item || typeof item.id !== 'string' || !item.id.trim()) return;
                    if (item.modality && item.modality !== 'text') return;
                    appendTokenModelOption({
                        id: item.id.trim(),
                        name: item.name || item.id,
                        prompt_per_million: item.prompt_per_million,
                        completion_per_million: item.completion_per_million
                    }, selectedId);
                });
                if ($.fn.select2 && $('#token_model_select').data('select2')) {
                    $('#token_model_select').select2('destroy').select2({
                        placeholder: "{{ __('Choose an option') }}",
                        allowClear: false,
                        width: '100%'
                    });
                }
            })
            .catch(function() {});
    }
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
		<h4 class="mb-1 mt-3"><span class="text-muted fw-light">{{ __('Projects') }}/</span> {{ isset($data->id) ? __('Edit') : __('Create') }}</h4>
        <p class="text-muted">{{ __('Track your projects') }}</p>
    </div>
    <div class="d-flex align-content-center flex-wrap gap-3"></div>
</div>

<div class="card mb-4">
	<h5 class="card-header">{{ isset($data->id) ? __('Edit Project') : __('Add New Project') }}</h5>
	<form class="card-body" action="{{ route('project.store') }}" method="POST">
		@csrf
		<input type="hidden" name="id" value="{{ $data->id ?? '' }}">

		@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

		<div class="row g-4">
			<!-- Internal name for collaborators -->
			<div class="col-12">
				<label for="name" class="form-label">{{ __('Internal Name for Collaborators') }} <i class="ti ti-eye ms-1"></i></label>
				<input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $data->name ?? '') }}">
				@error('name')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
			</div>

			<!-- Real name -->
			<div class="col-12">
				<label for="real_name" class="form-label">{{ __('Real Name') }} <i class="ti ti-link ms-1"></i></label>
				<input type="text" name="real_name" class="form-control @error('real_name') is-invalid @enderror" value="{{ old('real_name', $data->real_name ?? '') }}">
				@error('real_name')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
			</div>

			<!-- Project status -->
			@php
				$budgetContentLocked = isset($data->id) && $data->isBudgetContentLocked();
			@endphp
			<div class="col-md-6">
				<div class="form-group">
					<div class="d-flex align-items-center justify-content-between flex-nowrap gap-2 mb-1" style="min-height: 2.25rem;">
						<label for="status_id" class="form-label mb-0">{{ __('Project Status') }}</label>
					</div>
					@if ($budgetContentLocked)
						<input type="hidden" name="status_id" value="{{ $data->status_id }}">
					@endif
					<select id="status_id" @if (! $budgetContentLocked) name="status_id" @endif class="select2 form-select @error('status_id') is-invalid @enderror" data-placeholder="{{ __('Choose an option') }}" @disabled($budgetContentLocked)>
						@foreach($statuses as $status)
							<option value="{{ $status['id'] }}" {{ old('status_id', $data->status_id ?? '') == $status['id'] ? 'selected' : '' }}>{{ $status['name'] }}</option>
						@endforeach
					</select>
					@if ($budgetContentLocked)
						<small class="text-muted d-block mt-1">{{ __('Saving keeps the current status. Correct the amount in the breakdown.') }}</small>
					@endif
					@error('status_id')
						<div class="invalid-feedback">{{ $message }}</div>
					@enderror
				</div>
			</div>

			<!-- Category -->
			<div class="col-md-6">
				<x-module-categories-select
					id="category_id"
					label="{{ __('Categoría') }}"
					moduleKey="projects"
					:selected="is_array(old('category_id', $data->category_id ?? '')) ? (old('category_id', $data->category_id ?? '')[0] ?? '') : old('category_id', $data->category_id ?? '')"
				/>
				@error('category_id')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
			</div>

			<!-- Dates -->
			<div class="col-md-6">
				<x-input-date id="date_start" name="date_start" label="{{ __('Start Date') }}"
					value="{{ old('date_start', $data->date_start ?? '') }}" />
				@error('date_start')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>

			<div class="col-md-6">
				<x-input-date id="date_end" name="date_end" label="{{ __('Due date') }}"
					value="{{ old('date_end', $data->date_end ?? '') }}" />
				@error('date_end')
					<div class="invalid-feedback">{{ $message }}</div>
				@enderror
			</div>

			<!-- Client + advisor -->
			<div class="col-md-8 col-12">
				<x-client-select
					id="enterprise_id"
					label="{{ __('Client') }} (*)"
					:selected="old('enterprise_id', $data->enterprise_id ?? $enterprise_id ?? '')"
				/>
				@error('enterprise_id')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
			</div>
			<div class="col-md-4 col-12">
				<x-team-users-select
					id="responsible_id"
					label="{{ __('Asesor') }} (*)"
					:selected="old('responsible_id', $data->responsible_id ?? auth()->id())"
				/>
				@error('responsible_id')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
			</div>

			<div class="col-12">
				@php
					$participantOptions = \App\Support\AssignableTeamUsers::optionsForTeam(
						($data->team ?? null) ?: auth()->user()->currentTeam
					);
					$selectedParticipants = old('participant_ids', isset($data->id) ? $data->participants->pluck('id')->all() : []);
					$selectedParticipants = array_map('intval', is_array($selectedParticipants) ? $selectedParticipants : []);
				@endphp
				<label for="participant_ids" class="form-label">Participantes</label>
				<input type="hidden" name="sync_participants" value="1">
				<select id="participant_ids" name="participant_ids[]" class="form-select" multiple data-placeholder="Elegí quienes participan">
					@foreach($participantOptions as $userId => $userName)
						<option value="{{ $userId }}" @selected(in_array((int) $userId, $selectedParticipants, true))>{{ $userName }}</option>
					@endforeach
				</select>
				<p class="text-muted small mb-0 mt-1">Quienes participan en este proyecto. El filtro de tareas muestra solo a estas personas.</p>
			</div>

			<!-- Notas del proyecto -->
			<div class="col-12">
				<label for="description" class="form-label">{{ __('Project Notes') }}</label>
				<textarea id="description" name="description" class="form-control js-auto-resize @error('description') is-invalid @enderror" rows="4">{{ old('description', $data->description ?? '') }}</textarea>
				@error('description')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
			</div>

			<!-- Presupuesto: texto recibido + datos interpretados (data JSON) -->
			@if ($data->exists ? auth()->user()->can('manageBudget', $data) : auth()->user()->can('createBudget', \App\Models\Project::class))
			<div class="col-12">
				<label for="data_budget_given" class="form-label">{{ __('Budget received') }}</label>
				<textarea id="data_budget_given" name="data[budget_given]" class="form-control js-auto-resize" rows="3" placeholder="{{ __('Paste or type the budget text you received from the client') }}">{{ old('data.budget_given', data_get($data, 'data.budget_given', '')) }}</textarea>
			</div>
			<!-- Vista previa: resumen HTML editable (cotización) -->
			<div class="col-12 d-none mt-2" id="budget-preview-container">
				<label class="form-label">{{ __('Budget preview') }}</label>
				<p class="text-muted small mb-1">{{ __('Summary of requested quote and values, ready to copy into an email.') }}</p>
				@if(isset($data->id) && data_get($data, 'data.budget_preview_token'))
					<p class="small mb-1">
						<a href="{{ route('project.budget-preview', ['token' => data_get($data, 'data.budget_preview_token'), 'report' => 1]) }}" target="_blank" rel="noopener noreferrer">{{ __('Preview') }}</a>
					</p>
				@endif
				<div id="budget-preview-toolbar">
					<span class="ql-formats">
						<button class="ql-bold" type="button"></button>
						<button class="ql-italic" type="button"></button>
						<button class="ql-underline" type="button"></button>
					</span>
					<span class="ql-formats">
						<select class="ql-header">
							<option value="3"></option>
							<option value="4"></option>
							<option selected></option>
						</select>
					</span>
					<span class="ql-formats">
						<button class="ql-list" value="ordered" type="button"></button>
						<button class="ql-list" value="bullet" type="button"></button>
					</span>
					<span class="ql-formats">
						<button class="ql-link" type="button"></button>
						<button class="ql-clean" type="button"></button>
					</span>
				</div>
				<div id="budget-preview-editor" style="min-height: 280px; background: white;"></div>
				<input type="hidden" id="data_budget_preview_html" name="data[budget_preview_html]" value="{{ old('data.budget_preview_html', data_get($data, 'data.budget_preview_html', '')) }}">
			</div>
			<div class="col-12">
				<div class="d-flex justify-content-between align-items-center mb-2">
					<label class="form-label mb-0">{{ __('Budget data (AI)') }}</label>
					<button type="button" id="generate-budget-spec" class="btn btn-outline-primary btn-sm">
						<i class="ti ti-sparkles me-1"></i>{{ __('Generate from budget text') }}
					</button>
				</div>
				<p class="text-muted small mb-3">{{ __('Use "Budget received" above, then click to generate AI interpretation, dimension, timeline, resources and token consumption.') }}</p>
				@php
					$aiUsagePercentDefault = (float) old(
						'data.ai_usage_percent',
						data_get($data, 'data.ai_usage_percent', \App\Services\ProjectBudgetSpecService::DEFAULT_AI_USAGE_PERCENT)
					);
				@endphp
				<div class="row g-3 mb-3">
					<div class="col-md-6">
						<div class="form-check form-switch">
							<input class="form-check-input" type="checkbox" id="token_include_toggle" @checked($tokenIncludeDefault) @disabled(isset($data->id) && $data->quoteValueIsLocked())>
							<label class="form-check-label" for="token_include_toggle">Sumar tokens a las labores</label>
						</div>
						<p class="text-muted small mb-0">Parte del default de Configuración. Si lo apagás, este presupuesto cobra solo las horas.</p>
					</div>
					<div class="col-md-6">
						<div class="form-check form-switch">
							<input class="form-check-input" type="checkbox" id="token_discriminate_toggle" @checked($tokenDiscriminateDefault) @disabled((isset($data->id) && $data->quoteValueIsLocked()) || ! $tokenIncludeDefault)>
							<label class="form-check-label" for="token_discriminate_toggle">Discriminar tokens en el presupuesto</label>
						</div>
						<p class="text-muted small mb-0">Muestra columnas de tokens y su importe. Si lo desactivás, horas e IA van en un solo precio.</p>
					</div>
					<div class="col-12">
						<label class="form-label mb-1" for="token_model_select">Modelo de IA</label>
						<select id="token_model_select" class="form-select" @disabled(isset($data->id) && $data->quoteValueIsLocked())>
							<option
								value="{{ $tokenModelDefault['id'] }}"
								data-name="{{ $tokenModelDefault['name'] }}"
								data-prompt="{{ $tokenModelDefault['prompt_per_million'] }}"
								data-completion="{{ $tokenModelDefault['completion_per_million'] }}"
								selected
							>{{ $tokenModelDefault['name'] }} · ${{ number_format((float) $tokenModelDefault['prompt_per_million'], 2, ',', '') }} / ${{ number_format((float) $tokenModelDefault['completion_per_million'], 2, ',', '') }}</option>
						</select>
						<input type="hidden" name="data[token_include]" id="data_token_include" value="{{ $tokenIncludeDefault ? '1' : '0' }}">
						<input type="hidden" name="data[token_discriminate]" id="data_token_discriminate" value="{{ $tokenDiscriminateDefault ? '1' : '0' }}">
						<input type="hidden" name="data[token_model][id]" id="data_token_model_id" value="{{ $tokenModelDefault['id'] }}">
						<input type="hidden" name="data[token_model][name]" id="data_token_model_name" value="{{ $tokenModelDefault['name'] }}">
						<input type="hidden" name="data[token_model][prompt_per_million]" id="data_token_model_prompt" value="{{ $tokenModelDefault['prompt_per_million'] }}">
						<input type="hidden" name="data[token_model][completion_per_million]" id="data_token_model_completion" value="{{ $tokenModelDefault['completion_per_million'] }}">
						<p class="text-muted small mb-0 mt-1">
							<span id="token-model-helper">Aplica {{ (int) $aiUsagePercentDefault }}% IA: un modelo más caro baja horas y pasa peso a tokens.</span>
							<a href="https://mcp.idoneo.dev/models" target="_blank" rel="noopener">Ver catálogo</a>
						</p>
					</div>
				</div>
				<input type="hidden" name="price" id="project_price" value="{{ old('price', $data->price ?? '') }}">
				<div class="row g-3 align-items-start">
					<div class="col-md-8 col-12">
						<label class="form-label d-flex justify-content-between align-items-center mb-1" for="data_ai_usage_percent">
							<span class="small">{{ __('Hours↔tokens balance (%)') }}</span>
							<strong class="small" id="data_ai_usage_percent_label">{{ (int) $aiUsagePercentDefault }}%</strong>
						</label>
						<div id="ai-usage-balance-slider" class="noUi-primary noUi-sm mb-1"></div>
						<input type="hidden" id="data_ai_usage_percent" name="data[ai_usage_percent]" value="{{ $aiUsagePercentDefault }}">
						<p class="text-muted small mb-0">{{ __('Higher values reduce billable hours and move weight to tokens.') }}</p>
					</div>
					<div class="col-md-4 col-12">
						<label for="discount" class="form-label mb-1">{{ __('Discount') }} (%)</label>
						<input type="number" class="form-control form-control-sm" id="discount" name="discount"
							step="1" min="0" max="100"
							value="{{ old('discount', $data->discount ?? '') }}"
							placeholder="0"
							@if(isset($data->id) && $data->quoteValueIsLocked()) readonly @endif>
					</div>
				</div>
			</div>
			<div class="col-12">
				<label for="data_ai_interpretation" class="form-label">{{ __('AI interpretation') }}</label>
				<textarea id="data_ai_interpretation" name="data[ai_interpretation]" class="form-control js-auto-resize" rows="2">{{ old('data.ai_interpretation', data_get($data, 'data.ai_interpretation', '')) }}</textarea>
			</div>
			@php
				$savedSuggested = old('data.suggested_tasks', data_get($data, 'data.suggested_tasks', []));
				if (is_string($savedSuggested)) {
					$savedSuggested = json_decode($savedSuggested, true) ?? [];
				}
				if (! is_array($savedSuggested)) {
					$savedSuggested = [];
				}
				$tokenConsumption = old('data.token_consumption', data_get($data, 'data.token_consumption', []));
				$tokenConsumption = app(\App\Services\ProjectBudgetSpecService::class)->normalizeTokenConsumption(
					$tokenConsumption,
					$savedSuggested
				);
				$tokenConsumptionNotes = (string) ($tokenConsumption['notes'] ?? '');
			@endphp
			<div class="col-12">
				<label for="data_dimension" class="form-label">{{ __('Dimension') }}</label>
				<textarea id="data_dimension" name="data[dimension]" class="form-control js-auto-resize" rows="3">{{ old('data.dimension', data_get($data, 'data.dimension', '')) }}</textarea>
			</div>
			<div class="col-12">
				<label for="data_estimated_times" class="form-label">{{ __('Estimated times') }}</label>
				<textarea id="data_estimated_times" name="data[estimated_times]" class="form-control js-auto-resize" rows="3">{{ old('data.estimated_times', data_get($data, 'data.estimated_times', '')) }}</textarea>
			</div>
			<div class="col-12">
				<label for="data_resources" class="form-label">{{ __('Resources') }}</label>
				<textarea id="data_resources" name="data[resources]" class="form-control js-auto-resize" rows="3">{{ old('data.resources', data_get($data, 'data.resources', '')) }}</textarea>
			</div>
			<div class="col-12">
				<label for="data_token_consumption_notes" class="form-label">{{ __('Approximate token consumption') }}</label>
				<textarea id="data_token_consumption_notes" name="data[token_consumption][notes]" class="form-control js-auto-resize" rows="3" style="white-space: pre-line;">{{ $tokenConsumptionNotes }}</textarea>
				<input type="hidden" id="data_token_consumption_input" name="data[token_consumption][input_tokens]" value="{{ (int) ($tokenConsumption['input_tokens'] ?? 0) }}">
				<input type="hidden" id="data_token_consumption_output" name="data[token_consumption][output_tokens]" value="{{ (int) ($tokenConsumption['output_tokens'] ?? 0) }}">
				<input type="hidden" id="data_token_consumption_total" name="data[token_consumption][total_tokens]" value="{{ (int) ($tokenConsumption['total_tokens'] ?? 0) }}">
				<input type="hidden" id="data_token_consumption_cost" name="data[token_consumption][cost_euros]" value="{{ (float) ($tokenConsumption['cost_euros'] ?? 0) }}">
				<input type="hidden" id="data_token_consumption_savings" name="data[token_consumption][savings_percent]" value="{{ (float) ($tokenConsumption['savings_percent'] ?? 57) }}">
				<input type="hidden" id="data_token_consumption_billable" name="data[token_consumption][billable_euros]" value="{{ (float) ($tokenConsumption['billable_euros'] ?? 0) }}">
				<input type="hidden" name="data[token_consumption][currency]" value="{{ $tokenConsumption['currency'] ?? 'EUR' }}">
			</div>

			<!-- Suggested tasks (filled by AI, persisted in project data). -->
			<input type="hidden" name="data[suggested_tasks]" id="data_suggested_tasks" value="{{ json_encode(old('data.suggested_tasks', data_get($data, 'data.suggested_tasks', []))) }}">
			<div class="col-12 {{ empty($savedSuggested) || !is_array($savedSuggested) ? 'd-none' : '' }}" id="suggested-tasks-container">
				@if(!empty($savedSuggested) && is_array($savedSuggested))
					<p class="text-muted small mb-2">{{ count($savedSuggested) === 1 ? __('1 task suggested') : __(':count tasks suggested', ['count' => count($savedSuggested)]) }}</p>
					<div class="table-responsive">
						<table class="table table-sm table-bordered" id="suggested-tasks-table">
							<thead><tr><th>En el presupuesto</th><th>{{ __('Task') }}</th><th class="text-center">{{ __('Category') }}</th><th class="text-end">{{ __('Hours') }}</th><th class="text-end suggested-token-col {{ ($tokenIncludeDefault && $tokenDiscriminateDefault) ? '' : 'd-none' }}">{{ __('Tokens') }}</th><th class="text-end">{{ __('Level') }}</th><th class="text-end">{{ __('Value') }}</th></tr></thead>
							<tbody>
								@foreach($savedSuggested as $i => $t)
								@php
									$included = ($t['included'] ?? true);
									$estimatedTokens = $t['estimated_tokens'] ?? null;
									$hoursValue = isset($t['estimated_hours']) && is_numeric($t['estimated_hours']) ? (float) $t['estimated_hours'] : null;
									if ($estimatedTokens === null || $estimatedTokens === '') {
										$estimatedTokens = $hoursValue && $hoursValue > 0 ? (int) round($hoursValue * 20000) : '';
									}
									$levelOptions = ['Junior', 'Mid', 'Senior', 'Consultor'];
									$currentLevel = trim((string) ($t['resource_level'] ?? ''));
									if ($currentLevel !== '' && ! in_array($currentLevel, $levelOptions, true)) {
										$levelOptions[] = $currentLevel;
									}
									$quoteLocked = isset($data->id) && $data->quoteValueIsLocked();
								@endphp
								<tr data-index="{{ $i }}">
									<td class="align-middle">
										<label class="form-check mb-0">
											<input type="checkbox" class="form-check-input suggested-task-included" data-index="{{ $i }}" {{ $included ? 'checked' : '' }}>
											<span class="form-check-label small">En el presupuesto</span>
										</label>
									</td>
									<td>{{ $t['title'] ?? '—' }}</td>
									<td class="text-center">{{ $t['category_name'] ?? '—' }}</td>
									<td class="text-end"><input type="number" step="0.5" min="0" class="form-control form-control-sm text-end suggested-estimated-hours" data-index="{{ $i }}" value="{{ $hoursValue ?? '' }}" @if($quoteLocked) readonly @endif></td>
									<td class="text-end suggested-token-col {{ ($tokenIncludeDefault && $tokenDiscriminateDefault) ? '' : 'd-none' }}"><input type="number" step="1" min="0" class="form-control form-control-sm text-end suggested-estimated-tokens" data-index="{{ $i }}" value="{{ $estimatedTokens }}" placeholder="0"></td>
									<td class="text-end">
										<select class="form-select form-select-sm suggested-resource-level" data-index="{{ $i }}" @disabled($quoteLocked)>
											@if($currentLevel === '')
												<option value=""></option>
											@endif
											@foreach($levelOptions as $level)
												<option value="{{ $level }}" @selected(strcasecmp($currentLevel, $level) === 0)>{{ $level }}</option>
											@endforeach
										</select>
									</td>
									<td class="text-end"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end suggested-unit-price" data-index="{{ $i }}" value="{{ isset($t['unit_price']) && $t['unit_price'] !== '' ? (float) $t['unit_price'] : '' }}" placeholder="0" @if($quoteLocked) readonly @endif></td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				@endif
			</div>
			@endcan

		</div>

		<div class="pt-4">
			<div class="d-flex gap-3">
				<button type="submit" class="btn btn-primary px-5">{{ __('Save') }}</button>
				@if(isset($data->id))
					<button type="button" class="btn btn-label-secondary" onclick="location.href='{{ route('project.show', $data->id) }}'">{{ __('Cancel') }}</button>
				@else
					<button type="button" class="btn btn-label-secondary" onclick="location.href='{{ route('project-list') }}'">{{ __('Cancel') }}</button>
				@endif
			</div>
		</div>
	</form>
</div>

@endsection
