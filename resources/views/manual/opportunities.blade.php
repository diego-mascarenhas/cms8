@extends('layouts/layoutManual')

@section('title', __('Oportunidades'))

@section('content')
<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0">{{ __('Oportunidades') }}</h4>
    </div>
    <div class="card-body">
        <p>{{ __('Las oportunidades (deals) representan negocios en curso en tu pipeline comercial. Sirven para seguir desde el primer interés hasta el cierre.') }}</p>

        <h5 class="mt-4">{{ __('Qué puedes hacer') }}</h5>
        <ul>
            <li>{{ __('Crear una oportunidad vinculada a un contacto o cliente.') }}</li>
            <li>{{ __('Asignar responsable, etapa del embudo, importe estimado y fechas.') }}</li>
            <li>{{ __('Actualizar el estado según avanza la negociación.') }}</li>
            <li>{{ __('Convertir o relacionar la oportunidad con un proyecto cuando se gana.') }}</li>
        </ul>

        <x-manual.flowchart title="Flujo comercial" :nodes="[
            ['shape' => 'terminal', 'label' => 'Lead / contacto'],
            ['shape' => 'process', 'label' => 'Crear oportunidad', 'role' => 'collaborator'],
            ['shape' => 'process', 'label' => 'Avanzar etapas del pipeline'],
            ['shape' => 'decision', 'label' => '¿Cerrada ganada?', 'branches' => [
                ['when' => 'Sí', 'label' => 'Proyecto / factura', 'role' => 'admin'],
                ['when' => 'No', 'label' => 'Seguimiento o pérdida', 'role' => 'collaborator'],
            ]],
        ]" />

        <x-manual.role-compare section="opportunities" />
    </div>
</div>
@endsection
