<div class="d-flex justify-content-center align-items-center">
    @can('view', $teamPassword)
        <a href="javascript:;" class="text-body" onclick="revealPassword({{ $teamPassword->id }})" title="Ver y copiar">
            <i class="ti ti-eye ti-sm me-2"></i>
        </a>
        <a href="javascript:;" class="text-body" onclick="createShare({{ $teamPassword->id }})" title="Crear URL pública compartida">
            <i class="ti ti-link ti-sm me-2"></i>
        </a>
    @endcan

    @can('update', $teamPassword)
        <a href="{{ route('passwords.edit', $teamPassword) }}" class="text-body" title="Editar">
            <i class="ti ti-edit ti-sm"></i>
        </a>
    @endcan
</div>
