<form method="GET" action="{{ url()->current() }}" class="d-flex flex-wrap align-items-center gap-2" id="vat-period-form">
    <select name="vat_year" id="vat_year" class="form-select w-auto" aria-label="{{ __('Year') }}" onchange="this.form.submit()">
        @foreach($vatYears as $yearOption)
            <option value="{{ $yearOption }}" @selected((int) $vatYear === (int) $yearOption)>{{ $yearOption }}</option>
        @endforeach
    </select>
    <select name="vat_period" id="vat_period" class="form-select w-auto" aria-label="{{ __('Period') }}" onchange="this.form.submit()">
        <optgroup label="{{ __('Month') }}">
            @for($month = 1; $month <= 12; $month++)
                <option value="m:{{ $month }}" @selected($vatPeriod === 'm:'.$month)>
                    {{ \Carbon\Carbon::create(null, $month, 1)->translatedFormat('F') }}
                </option>
            @endfor
        </optgroup>
        <optgroup label="{{ __('Quarter') }}">
            @for($q = 1; $q <= 4; $q++)
                <option value="q:{{ $q }}" @selected($vatPeriod === 'q:'.$q)>Q{{ $q }}</option>
            @endfor
        </optgroup>
    </select>
</form>
