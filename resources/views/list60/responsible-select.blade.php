<select
    class="form-select form-select-sm list60-responsible-select"
    data-list60-id="{{ $list60Id }}"
    onfocus="this.dataset.previousValue = this.value"
    onchange="updateList60Responsible(this)"
>
    @foreach ($teamUsers as $user)
        <option value="{{ $user->id }}" @selected((int) $responsibleId === (int) $user->id)>{{ $user->name }}</option>
    @endforeach
</select>
