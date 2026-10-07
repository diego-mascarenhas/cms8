@if ($canEdit ?? false)
    <form method="POST" action="{{ route('strategy.evaluate') }}" id="strategy-evaluate-form">
        @csrf
        <button type="submit" class="btn btn-primary waves-effect waves-light" id="strategy-evaluate-button">
            <i class="ti ti-list-check me-1"></i>{{ __('app.weekly_plan_strategy_evaluate') }}
        </button>
    </form>
    <script>
        document.getElementById('strategy-evaluate-form')?.addEventListener('submit', function (event) {
            const button = document.getElementById('strategy-evaluate-button');

            if (!button) {
                return;
            }

            if (button.dataset.loading === '1') {
                event.preventDefault();
                return;
            }

            button.dataset.loading = '1';
            button.setAttribute('aria-busy', 'true');
            window.setTimeout(function () {
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + @json(__('app.weekly_plan_strategy_evaluating'));
            }, 0);
        });
    </script>
@endif
