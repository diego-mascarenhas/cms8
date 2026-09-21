<div class="card mb-4">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
        <div>
            <h5 class="card-title mb-1">Afiliados</h5>
            <p class="text-muted mb-0">
                Productos SaaS Idoneo: la <strong>agencia</strong> que vende se lleva el {{ $catalog['agency_percent'] }}% del cobro.
                Quien <strong>asesora</strong> a esa agencia o empresa se lleva el {{ $catalog['advisor_percent'] }}%.
            </p>
        </div>
        <a href="{{ url('/billing') }}" class="btn btn-outline-primary">
            <i class="ti ti-affiliate me-1"></i> Ver módulo de afiliados
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Quién</th>
                    <th>Tipo</th>
                    <th>Qué percibe</th>
                    <th>Empresas / proyectos que cubre</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($catalog['affiliate_rows'] as $row)
                    <tr>
                        <td>{{ $row['person']['name'] ?? $row['assignment']['person_key'] }}</td>
                        <td>{{ $row['assignment']['role'] }}</td>
                        <td>{{ $row['assignment']['percent'] }}%</td>
                        <td>{{ $row['assignment']['scope'] }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td>Agencia afiliada</td>
                    <td>Agencia</td>
                    <td>{{ $catalog['agency_percent'] }}%</td>
                    <td>Cada suscripción Idoneo que traiga (Assistant, Shop, Mailer, Ads, Landings, Affiliates).</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
