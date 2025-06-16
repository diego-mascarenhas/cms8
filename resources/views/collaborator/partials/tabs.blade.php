<!-- Tabs -->
<div class="d-flex mb-3">
    <a href="{{ route('collaborator.show', ['id' => $collaborator->id]) }}" class="btn {{ Request::routeIs('collaborator.show') ? 'btn-primary' : 'btn-outline-secondary' }} me-3">
        <i class="ti ti-refresh me-1"></i>Resumen
    </a>
    <a href="{{ route('collaborator.rates', ['id' => $collaborator->id]) }}" class="btn {{ Request::routeIs('collaborator.rates') ? 'btn-primary' : 'btn-outline-secondary' }} me-3">
        <i class="ti ti-tag me-1"></i>Tarifas
    </a>
    <a href="{{ route('collaborator.absences', ['id' => $collaborator->id]) }}" class="btn {{ Request::routeIs('collaborator.absences') ? 'btn-primary' : 'btn-outline-secondary' }} me-3">
        <i class="ti ti-users me-1"></i>Ausencias
    </a>
    <a href="{{ route('collaborator.notifications', ['id' => $collaborator->id]) }}" class="btn {{ Request::routeIs('collaborator.notifications') ? 'btn-primary' : 'btn-outline-secondary' }}">
        <i class="ti ti-bell me-1"></i>Notificaciones
    </a>
</div> 