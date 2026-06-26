@if (session('hosting_provisioned'))
<div class="alert alert-success mb-4">
    <h5 class="alert-heading mb-2">Cuenta creada en cPanel</h5>

    @if (session('generated_password'))
        <p class="mb-2">
            <strong>Contraseña cPanel (guárdala ahora, no se volverá a mostrar):</strong>
            <code class="user-select-all">{{ session('generated_password') }}</code>
        </p>
    @endif

    @if ($nameservers = session('dns_nameservers', []))
        <p class="mb-1"><strong>Configura estos DNS en el registrador del dominio:</strong></p>
        <ul class="mb-2">
            @foreach ($nameservers as $nameserver)
                <li><code>{{ $nameserver }}</code></li>
            @endforeach
        </ul>
        <p class="mb-0 text-muted small">Los nameservers deben apuntarse en el registrador. Una vez propagados, la zona DNS se gestionará en este servidor.</p>
    @endif

    @if (session()->has('spf_configured'))
        @if (session('spf_configured'))
            <p class="mb-0 mt-2"><i class="ti ti-check me-1"></i>Registro SPF configurado en la zona con <code>include:spf.revisionalpha.com</code>.</p>
        @else
            <p class="mb-0 mt-2 text-warning">
                <i class="ti ti-alert-triangle me-1"></i>No se pudo configurar el SPF automáticamente.
                @if (session('spf_error'))
                    {{ session('spf_error') }}
                @else
                    Añade manualmente un TXT: <code>{{ config('humano_hosting.default_spf_record') }}</code>
                @endif
            </p>
        @endif
    @endif
</div>
@endif
