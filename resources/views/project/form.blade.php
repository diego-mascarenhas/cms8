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
    function autoResizeTextarea(el) {
        if (!el) return;
        el.style.height = 'auto';
        el.style.height = Math.max(el.scrollHeight, 40) + 'px';
    }

    function autoResizeBudgetTextareas() {
        document.querySelectorAll('textarea.js-auto-resize').forEach(autoResizeTextarea);
    }

    $(function() {
        // Inicializar Select2 si está disponible
        if ($.fn.select2) {
            $('#enterprise_id, #category_id, #status_id').select2({
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
                        $('#suggested-tasks-toggle').removeClass('d-none');
                        $('#data_suggested_tasks').val(JSON.stringify(res.suggested_tasks));
                        applyTokenConsumption(res.token_consumption, res.suggested_tasks);
                        refreshBudgetPreview();
                        $('#suggested-tasks-container').addClass('d-none');
                    } else {
                        $('#suggested-tasks-container').addClass('d-none').empty();
                        $('#suggested-tasks-toggle').addClass('d-none');
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
        var h = parseFloat(hours);
        if (isNaN(h) || h <= 0) return '—';
        var totalMinutes = Math.round(h * 60);
        var wholeHours = Math.floor(totalMinutes / 60);
        var minutes = totalMinutes % 60;
        if (wholeHours > 0 && minutes > 0) {
            return wholeHours + ' h ' + minutes + ' min';
        }
        if (wholeHours > 0) {
            return wholeHours + ' h';
        }
        return minutes + ' min';
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
        var cost = (input / 1000000) * 11 + (output / 1000000) * 55;
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
        var cost = (input / 1000000) * 11 + (output / 1000000) * 55;
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
    function buildSuggestedTasksTable(tasks) {
        var h = '<p class="text-muted small mb-2">' + (tasks.length === 1 ? '{{ __("1 task suggested") }}' : '{{ __(":count tasks suggested") }}'.replace(':count', tasks.length)) + '</p><div class="table-responsive"><table class="table table-sm table-bordered" id="suggested-tasks-table"><thead><tr><th class="text-center" style="width: 2.5rem;"></th><th>{{ __("Task") }}</th><th class="text-center">{{ __("Category") }}</th><th class="text-end">{{ __("Hours") }}</th><th class="text-end">{{ __("Tokens") }}</th><th class="text-end">{{ __("Level") }}</th><th class="text-end">{{ __("Value") }}</th></tr></thead><tbody>';
        tasks.forEach(function(t, i) {
            var included = t.included !== false;
            if (typeof t.included === 'undefined') t.included = true;
            var title = escapeHtml(t.title || '—');
            var cat = escapeHtml(t.category_name || '—');
            var hoursLabel = (t.estimated_hours != null && t.estimated_hours !== '') ? formatHoursHuman(t.estimated_hours) : '—';
            var tokens = (t.estimated_tokens != null && t.estimated_tokens !== '') ? Number(t.estimated_tokens) : '';
            var resLevel = (t.resource_level != null && t.resource_level !== '') ? escapeHtml(String(t.resource_level)) : '';
            var unitPrice = (t.unit_price != null && t.unit_price !== '') ? escapeHtml(String(t.unit_price)) : '';
            h += '<tr data-index="' + i + '"><td class="text-center align-middle"><input type="checkbox" class="form-check-input suggested-task-included" data-index="' + i + '" ' + (included ? 'checked' : '') + '></td><td>' + title + '</td><td class="text-center">' + cat + '</td><td class="text-end">' + escapeHtml(hoursLabel) + '</td>';
            h += '<td class="text-end"><input type="number" step="1000" min="0" class="form-control form-control-sm text-end suggested-estimated-tokens" data-index="' + i + '" value="' + tokens + '" placeholder="0"></td>';
            h += '<td class="text-end"><input type="text" class="form-control form-control-sm text-end suggested-resource-level" data-index="' + i + '" value="' + resLevel + '" placeholder="{{ __("e.g. Senior") }}"></td>';
            h += '<td class="text-end"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end suggested-unit-price" data-index="' + i + '" value="' + unitPrice + '" placeholder="0"></td></tr>';
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
        if (isNaN(raw) || raw < 0) return 0;
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
        var blendPerMillion = 24.2;
        var maxDiscount = 30;

        var hoursValue = parseFloat(hours);
        if (isNaN(hoursValue) || hoursValue < 0) hoursValue = 0;
        var tokensBase = parseInt(baseTokens, 10);
        if (isNaN(tokensBase) || tokensBase < 0) tokensBase = 0;

        var baseInput = Math.round(tokensBase * 0.7);
        var baseOutput = Math.max(0, tokensBase - baseInput);
        var baseCost = (baseInput / 1000000) * 11 + (baseOutput / 1000000) * 55;
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
        var cost = (input / 1000000) * 11 + (output / 1000000) * 55;
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
        var aiUsage = resolveAiUsagePercent();

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
        var totalTokenCost = 0;
        var totalBaseTokenBillable = 0;
        var totalHours = 0;
        var totalHoursSaved = 0;
        var taskItems = '';
        tasks.forEach(function(t) {
            var title = (t.title || '—');
            var included = t.included !== false;
            var hours = (t.estimated_hours != null && t.estimated_hours !== '') ? parseFloat(t.estimated_hours) : 0;
            var level = (t.resource_level != null && t.resource_level !== '') ? String(t.resource_level) : '—';
            var price = (t.unit_price != null && t.unit_price !== '') ? parseFloat(t.unit_price) : NaN;
            var baseTokens = resolveTaskTokens(t);
            var basePricing = taskTokenPricing(t, savings);
            var balanced = applyHoursTokensBalance(price, hours, baseTokens, savings, aiUsage);
            var laborCharged = balanced.labor;
            var hoursCharged = balanced.hours;
            var tokenBillable = balanced.tokenBillable;
            var displayTokens = balanced.displayTokens;
            var details = [
                formatHoursHuman(hoursCharged),
                level,
                !isNaN(laborCharged) ? formatEuros(laborCharged) : '—'
            ];
            if (balanced.tokens > 0 || tokenBillable > 0) {
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
                totalBaseTokenBillable += basePricing.billable;
                totalTokenCost += balanced.cost;
                totalHours += hoursCharged;
                totalHoursSaved += basePricing.hoursSaved;
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
            var discountedTotal = Math.round(grandTotal * (1 - (discount / 100)));
            var totalFormatted = grandTotal.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            var discountedFormatted = discountedTotal.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            if (discount > 0) {
                html += '<p><strong>{{ __("Total") }}:</strong> <s>' + totalFormatted + '€</s> '
                    + discountedFormatted + '€ + {{ __("I.V.A.") }}'
                    + ' <em>(−' + String(discount).replace('.', ',') + '% · {{ __("labor") }} ' + formatEuros(totalLabor)
                    + ' + {{ __("Tokens") }} ' + formatEuros(totalTokenBillable) + ')</em></p>';
            } else {
                html += '<p><strong>{{ __("Total") }}:</strong> ' + totalFormatted + '€ + {{ __("I.V.A.") }}'
                    + ' <em>({{ __("labor") }} ' + formatEuros(totalLabor) + ' + {{ __("Tokens") }} ' + formatEuros(totalTokenBillable) + ')</em></p>';
            }
            var weeks = totalHours > 0 ? Math.ceil(totalHours / 40) : 0;
            html += '<p>' + escapeHtml('{{ __("Estimated development time, :weeks weeks after the budget has been confirmed.") }}'.replace(':weeks', weeks)) + '</p>';
            var moneySaved = Math.max(0, totalBaseTokenBillable - totalTokenCost);
            if (moneySaved > 0 || totalHoursSaved > 0) {
                html += '<p style="font-size:0.9em;opacity:0.9;"><em>'
                    + escapeHtml('{{ __("This quote already includes an estimated saving of :money and about :time.") }}'
                        .replace(':money', formatEuros(moneySaved))
                        .replace(':time', formatHoursHuman(totalHoursSaved)))
                    + '</em></p>';
            }
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

    $(document).on('change input', '.suggested-resource-level, .suggested-unit-price, .suggested-estimated-tokens', function() {
        var idx = parseInt($(this).data('index'), 10);
        var raw = $('#data_suggested_tasks').val();
        var tasks = [];
        try {
            if (raw) tasks = JSON.parse(raw);
        } catch (e) { return; }
        if (tasks[idx] === undefined) return;
        if ($(this).hasClass('suggested-unit-price')) {
            var val = $(this).val();
            var num = parseFloat(val);
            tasks[idx].unit_price = (isNaN(num) || val === '') ? '' : num;
        } else if ($(this).hasClass('suggested-estimated-tokens')) {
            var tokenVal = $(this).val();
            var tokenNum = parseInt(tokenVal, 10);
            tasks[idx].estimated_tokens = (isNaN(tokenNum) || tokenVal === '') ? 0 : tokenNum;
        } else {
            tasks[idx].resource_level = $(this).val();
        }
        $('#data_suggested_tasks').val(JSON.stringify(tasks));
        syncTokenConsumptionFromTasks();
        refreshBudgetPreview();
    });

    $('#suggested-tasks-toggle-btn').on('click', function() {
        var container = $('#suggested-tasks-container');
        var btn = $(this);
        var isHidden = container.hasClass('d-none');
        container.toggleClass('d-none');
        btn.attr('aria-expanded', isHidden);
        btn.find('.ti-chevron-down').toggleClass('ti-chevron-down', !isHidden).toggleClass('ti-chevron-up', isHidden);
        btn.find('.toggle-label').text(isHidden ? '{{ __("Hide breakdown") }}' : '{{ __("Edit breakdown") }}');
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
            var startBalance = parseFloat(balanceInput ? balanceInput.value : 0);
            if (isNaN(startBalance) || startBalance < 0) startBalance = 0;
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

        // Rebuild preview so metrics stay under each title (Quill-safe) and AI % applies.
        refreshBudgetPreview();
    });
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
			<div class="col-md-6">
				<label for="status_id" class="form-label">{{ __('Project Status') }}</label>
				<select id="status_id" name="status_id" class="select2 form-select @error('status_id') is-invalid @enderror" data-placeholder="{{ __('Choose an option') }}">
					@foreach($statuses as $status)
						<option value="{{ $status['id'] }}" {{ old('status_id', $data->status_id ?? '') == $status['id'] ? 'selected' : '' }}>{{ $status['name'] }}</option>
					@endforeach
				</select>
				@error('status_id')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
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
				<x-input-date id="date_material" name="date_material" label="{{ __('Material Delivery Date') }}"
					value="{{ old('date_material', $data->date_material ?? '') }}" />
			</div>

			<div class="col-md-6">
				<x-input-date id="date_end" label="{{ __('Final Delivery Date') }}"
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

			<!-- Additional fields for admins -->
			@if(auth()->user()->hasRole('admin'))
			{{-- Price and cost remain hidden; discount is edited in Budget data (AI). --}}

			{{-- Hidden: Start date field --}}
			{{--
			<div class="col-md-6">
				<x-input-date id="date_start" label="{{ __('Start Date') }}"
					value="{{ old('date_start', $data->date_start ?? '') }}" />
			</div>
			--}}
			@else
			<!-- Simplified view for non-admins -->
			{{-- Hidden: Start date field --}}
			{{--
			<div class="col-md-6">
				<x-input-date id="date_start" label="{{ __('Start Date') }}"
					value="{{ old('date_start', $data->date_start ?? '') }}" />
			</div>
			--}}
			@endif

			<!-- Notas del proyecto -->
			<div class="col-12">
				<label for="description" class="form-label">{{ __('Project Notes') }}</label>
				<textarea id="description" name="description" class="form-control js-auto-resize @error('description') is-invalid @enderror" rows="4">{{ old('description', $data->description ?? '') }}</textarea>
				@error('description')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror
			</div>

			<!-- Presupuesto: texto recibido + datos interpretados (data JSON) -->
			@can('access-billing-modules')
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
						<a href="{{ route('project.budget-preview', data_get($data, 'data.budget_preview_token')) }}" target="_blank" rel="noopener noreferrer">{{ __('Preview') }}</a>
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
							placeholder="0">
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

			<!-- Suggested tasks (filled by AI, persisted in project data). Hidden by default; show via "Edit breakdown" link. -->
			<input type="hidden" name="data[suggested_tasks]" id="data_suggested_tasks" value="{{ json_encode(old('data.suggested_tasks', data_get($data, 'data.suggested_tasks', []))) }}">
			<div class="col-12 mb-2 {{ empty($savedSuggested) || !is_array($savedSuggested) ? 'd-none' : '' }}" id="suggested-tasks-toggle">
				<button type="button" class="btn btn-sm btn-label-secondary" id="suggested-tasks-toggle-btn" aria-expanded="false">
					<i class="ti ti-chevron-down me-1"></i><span class="toggle-label">{{ __('Edit breakdown') }}</span>
				</button>
			</div>
			<div class="col-12 d-none" id="suggested-tasks-container">
				@if(!empty($savedSuggested) && is_array($savedSuggested))
					<p class="text-muted small mb-2">{{ count($savedSuggested) === 1 ? __('1 task suggested') : __(':count tasks suggested', ['count' => count($savedSuggested)]) }}</p>
					<div class="table-responsive">
						<table class="table table-sm table-bordered" id="suggested-tasks-table">
							<thead><tr><th class="text-center" style="width: 2.5rem;"></th><th>{{ __('Task') }}</th><th class="text-center">{{ __('Category') }}</th><th class="text-end">{{ __('Hours') }}</th><th class="text-end">{{ __('Tokens') }}</th><th class="text-end">{{ __('Level') }}</th><th class="text-end">{{ __('Value') }}</th></tr></thead>
							<tbody>
								@foreach($savedSuggested as $i => $t)
								@php
									$included = ($t['included'] ?? true);
									$estimatedTokens = $t['estimated_tokens'] ?? null;
									$hoursValue = isset($t['estimated_hours']) && is_numeric($t['estimated_hours']) ? (float) $t['estimated_hours'] : null;
									if ($estimatedTokens === null || $estimatedTokens === '') {
										$estimatedTokens = $hoursValue && $hoursValue > 0 ? (int) round($hoursValue * 20000) : '';
									}
									$hoursLabel = '—';
									if ($hoursValue !== null && $hoursValue > 0) {
										$totalMinutes = (int) round($hoursValue * 60);
										$wholeHours = intdiv($totalMinutes, 60);
										$minutes = $totalMinutes % 60;
										if ($wholeHours > 0 && $minutes > 0) {
											$hoursLabel = $wholeHours.' h '.$minutes.' min';
										} elseif ($wholeHours > 0) {
											$hoursLabel = $wholeHours.' h';
										} else {
											$hoursLabel = $minutes.' min';
										}
									}
								@endphp
								<tr data-index="{{ $i }}">
									<td class="text-center align-middle"><input type="checkbox" class="form-check-input suggested-task-included" data-index="{{ $i }}" {{ $included ? 'checked' : '' }}></td>
									<td>{{ $t['title'] ?? '—' }}</td>
									<td class="text-center">{{ $t['category_name'] ?? '—' }}</td>
									<td class="text-end">{{ $hoursLabel }}</td>
									<td class="text-end"><input type="number" step="1000" min="0" class="form-control form-control-sm text-end suggested-estimated-tokens" data-index="{{ $i }}" value="{{ $estimatedTokens }}" placeholder="0"></td>
									<td class="text-end"><input type="text" class="form-control form-control-sm text-end suggested-resource-level" data-index="{{ $i }}" value="{{ $t['resource_level'] ?? '' }}" placeholder="{{ __('e.g. Senior') }}"></td>
									<td class="text-end"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end suggested-unit-price" data-index="{{ $i }}" value="{{ isset($t['unit_price']) && $t['unit_price'] !== '' ? (float) $t['unit_price'] : '' }}" placeholder="0"></td>
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
