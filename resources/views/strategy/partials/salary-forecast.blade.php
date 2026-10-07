@if (! empty($projection['salaries']))
    @php
        $salaryPeople = collect($projection['salaries'])->map(function (array $person): string {
            $assigned = number_format((float) $person['assigned_hours'], 1, ',', '.');

            if ($person['capacity_hours'] === null) {
                return $person['name'].' '.$assigned.' h';
            }

            return $person['name'].' '.$assigned.' h de '.number_format((float) $person['capacity_hours'], 1, ',', '.').' h';
        })->implode('; ');
    @endphp
    <p class="mb-3">
        @if (($projection['salary_monthly'] ?? 0) > 0)
            {{ __('app.cfo_analysis_salaries', [
                'people' => $salaryPeople,
                'amount' => number_format((float) $projection['salary_monthly'], 2, ',', '.'),
                'currency' => $projection['currency'] ?? '',
            ]) }}
        @else
            {{ __('app.cfo_analysis_salaries_unset', ['people' => $salaryPeople]) }}
        @endif
    </p>
@endif
