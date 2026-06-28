<form method="GET" action="{{ $filterAction }}" class="d-flex flex-wrap align-items-center gap-2">
    @foreach($hiddenFields ?? [] as $name => $value)
        @if(is_array($value))
            @foreach($value as $nestedValue)
                <input type="hidden" name="{{ $name }}" value="{{ $nestedValue }}">
            @endforeach
        @else
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach
    <label for="invoiced-lines-month" class="form-label mb-0 me-2">{{ __('Month') }}</label>
    <select id="invoiced-lines-month" name="month" class="form-select w-auto" onchange="this.form.submit()">
        <option value="0" @selected($selectedMonth === 0)>{{ __('All months') }}</option>
        @for($month = 1; $month <= 12; $month++)
            <option value="{{ $month }}" @selected($selectedMonth === $month)>
                {{ \Carbon\Carbon::create(null, $month, 1)->translatedFormat('F') }}
            </option>
        @endfor
    </select>
    <label for="invoiced-lines-year" class="form-label mb-0 me-2">{{ __('Year') }}</label>
    <select id="invoiced-lines-year" name="year" class="form-select w-auto" onchange="this.form.submit()">
        @foreach($availableYears as $year)
            <option value="{{ $year }}" @selected($year === $selectedYear)>{{ $year }}</option>
        @endforeach
    </select>
</form>
