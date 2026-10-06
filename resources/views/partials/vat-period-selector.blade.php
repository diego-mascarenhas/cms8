<form method="GET" action="{{ url()->current() }}" class="d-flex flex-nowrap align-items-center gap-2 flex-shrink-0" id="vat-period-form">
    <div class="position-relative w-px-100">
        <select name="vat_year" id="vat_year" class="select2 form-select js-filter-select" aria-label="{{ __('Year') }}" onchange="this.form.submit()">
            @foreach($vatYears as $yearOption)
                <option value="{{ $yearOption }}" @selected((int) $vatYear === (int) $yearOption)>{{ $yearOption }}</option>
            @endforeach
        </select>
    </div>
    <div class="position-relative w-px-200">
        <select name="vat_period" id="vat_period" class="select2 form-select js-filter-select" aria-label="{{ __('Period') }}" onchange="this.form.submit()">
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
    </div>
</form>
@include('partials.filter-select2-script')
