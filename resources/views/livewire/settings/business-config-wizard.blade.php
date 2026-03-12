<div>
    <style>
        .wizard-modern .bs-stepper-icon .wizard-step-icon { font-size: 1.75rem; }
    </style>
    <div class="bs-stepper wizard-icons wizard-modern wizard-modern-icons-example mt-2">
        <div class="bs-stepper-header">
            @php
                $steps = [
                    1 => ['label' => 'Datos del negocio', 'icon' => 'ti-building-store'],
                    2 => ['label' => 'Información personal', 'icon' => 'ti-user'],
                    3 => ['label' => 'Dirección', 'icon' => 'ti-map-pin'],
                    4 => ['label' => 'Redes sociales', 'icon' => 'ti-share'],
                    5 => ['label' => 'Desafío', 'icon' => 'ti-puzzle'],
                    6 => ['label' => 'Revisar y enviar', 'icon' => 'ti-circle-check'],
                ];
            @endphp
            @foreach ($steps as $num => $stepConfig)
                <div class="step {{ $step === $num ? 'active' : '' }}">
                    <button type="button" class="step-trigger" wire:click="goToStep({{ $num }})">
                        <span class="bs-stepper-icon">
                            <i class="ti {{ $stepConfig['icon'] }} wizard-step-icon"></i>
                        </span>
                        <span class="bs-stepper-label">{{ $stepConfig['label'] }}</span>
                    </button>
                </div>
                @if ($num < 6)
                    <div class="line"><i class="ti ti-chevron-right"></i></div>
                @endif
            @endforeach
        </div>
        <div class="bs-stepper-content">
            @if ($step === 1)
                <div class="content active" wire:key="step-1">
                    <div class="content-header mb-3">
                        <h6 class="mb-0">Datos del negocio</h6>
                        <small>Nombre, rubro, ubicación, logo y descripción de tu negocio.</small>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><i class="ti ti-building-store ti-sm me-1 text-body"></i> Nombre del negocio (*)</label>
                            <input type="text" class="form-control" wire:model.blur="config.business_name" placeholder="Nombre de tu empresa o marca" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="ti ti-category ti-sm me-1 text-body"></i> Rubro / Sector</label>
                            <input type="text" class="form-control" wire:model.blur="config.business_industry" placeholder="ej. Tecnología, Retail, Servicios" />
                        </div>
                        <div class="col-12">
                            <div
                                class="d-flex align-items-start gap-3"
                                x-data="{ dragging: false }"
                            >
                                <div class="d-flex flex-column flex-shrink-0">
                                    <label class="form-label"><i class="ti ti-photo ti-sm me-1 text-body"></i> Logo</label>
                                    <div
                                        class="rounded border bg-lighter p-2 d-flex align-items-center justify-content-center logo-drop-zone position-relative"
                                        style="width: 120px; height: 120px; min-width: 120px; min-height: 120px; cursor: pointer; transition: border-color 0.15s, background-color 0.15s;"
                                        :class="{ 'border-primary border-2': dragging }"
                                        @dragover.prevent="dragging = true"
                                        @dragleave.prevent="dragging = false"
                                        @drop.prevent="dragging = false"
                                    >
                                        <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/jpg" class="position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer" style="cursor: pointer;" />
                                        @if ($logo)
                                            <img src="{{ $logo->temporaryUrl() }}" alt="Logo" class="rounded object-fit-contain position-relative" style="max-width: 100%; max-height: 100%; pointer-events: none;" />
                                        @else
                                            <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-muted small text-center position-relative" style="pointer-events: none;">
                                                <i class="ti ti-photo-plus ti-xl mb-1"></i>
                                                <span>Arrastrá o clic</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <label class="form-label"><i class="ti ti-file-description ti-sm me-1 text-body"></i> Descripción</label>
                                    <textarea class="form-control" wire:model.blur="config.business_description" placeholder="¿Qué hace tu negocio? ¿A quién va dirigido?" style="height: 120px; resize: vertical;"></textarea>
                                    @error('logo')
                                        <small class="text-danger d-block mt-1">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><i class="ti ti-quote ti-sm me-1 text-body"></i> Eslogan</label>
                            <input type="text" class="form-control" wire:model.blur="config.business_tagline" placeholder="Frase corta que defina tu negocio" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="ti ti-map-pin ti-sm me-1 text-body"></i> Ubicación</label>
                            <input type="text" class="form-control" wire:model.blur="config.business_location" placeholder="Calle, ciudad, región" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="ti ti-mailbox ti-sm me-1 text-body"></i> Código postal</label>
                            <input type="text" class="form-control" wire:model.blur="config.business_postal_code" placeholder="28001" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="ti ti-phone ti-sm me-1 text-body"></i> Teléfono</label>
                            <input type="tel" class="form-control" wire:model.blur="config.business_phone" placeholder="+34 600 000 000" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="ti ti-brand-whatsapp ti-sm me-1 text-body"></i> WhatsApp</label>
                            <input type="tel" class="form-control" wire:model.blur="config.business_whatsapp" placeholder="+34 600 000 000" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="ti ti-world ti-sm me-1 text-body"></i> Página web</label>
                            <input type="url" class="form-control" wire:model.blur="config.business_website" placeholder="https://www.ejemplo.com" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="ti ti-mail ti-sm me-1 text-body"></i> Email</label>
                            <input type="email" class="form-control" wire:model.blur="config.business_email" placeholder="contacto@ejemplo.com" />
                        </div>
                        <div class="col-12 d-flex justify-content-between">
                            <button type="button" class="btn btn-label-secondary" disabled><i class="ti ti-arrow-left me-sm-1"></i><span class="align-middle d-sm-inline-block d-none">Anterior</span></button>
                            <button type="button" class="btn btn-primary" wire:click="nextStep"><span class="align-middle d-sm-inline-block d-none me-sm-1">Siguiente</span><i class="ti ti-arrow-right"></i></button>
                        </div>
                    </div>
                </div>
            @endif

            @if ($step === 2)
                <div class="content active" wire:key="step-2">
                    <div class="content-header mb-3">
                        <h6 class="mb-0">Información personal</h6>
                        <small>Introduce tu información personal. Fecha y hora de nacimiento se usan para obtener tu arquetipo humano.</small>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" wire:model.blur="config.first_name" placeholder="Nombre" />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Apellidos</label>
                            <input type="text" class="form-control" wire:model.blur="config.last_name" placeholder="Apellidos" />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-calendar-event ti-sm me-1 text-body"></i> Fecha de nacimiento</label>
                            <input type="text" id="business-wizard-birth-date" class="form-control flatpickr-birth-date" wire:model.live="config.birth_date" placeholder="YYYY-MM-DD" />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-clock ti-sm me-1 text-body"></i> Hora de nacimiento</label>
                            <input type="time" class="form-control" wire:model.blur="config.birth_time" placeholder="HH:MM" />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-mail ti-sm me-1 text-body"></i> Email</label>
                            <input type="email" class="form-control" wire:model.blur="config.business_email" placeholder="contacto@ejemplo.com" />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-brand-whatsapp ti-sm me-1 text-body"></i> WhatsApp</label>
                            <input type="tel" class="form-control" wire:model.blur="config.business_whatsapp" placeholder="+34 600 000 000" />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-world ti-sm me-1 text-body"></i> País</label>
                            <select id="business-wizard-country" class="form-select select2-select" wire:model.live="config.country" data-placeholder="Seleccionar país">
                                <option value="">Seleccionar país</option>
                                <option value="España">España</option>
                                <option value="México">México</option>
                                <option value="Argentina">Argentina</option>
                                <option value="Colombia">Colombia</option>
                                <option value="Chile">Chile</option>
                                <option value="Perú">Perú</option>
                                <option value="Reino Unido">Reino Unido</option>
                                <option value="Estados Unidos">Estados Unidos</option>
                                <option value="Francia">Francia</option>
                                <option value="Italia">Italia</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-language ti-sm me-1 text-body"></i> Idioma</label>
                            <select id="business-wizard-language" class="form-select select2-select" wire:model.live="config.language" data-placeholder="Seleccionar idioma">
                                <option value="">Seleccionar idioma</option>
                                <option value="Español">Español</option>
                                <option value="Inglés">Inglés</option>
                                <option value="Francés">Francés</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex justify-content-between">
                            <button type="button" class="btn btn-label-secondary" wire:click="previousStep"><i class="ti ti-arrow-left me-sm-1"></i><span class="align-middle d-sm-inline-block d-none">Anterior</span></button>
                            <button type="button" class="btn btn-primary" wire:click="nextStep"><span class="align-middle d-sm-inline-block d-none me-sm-1">Siguiente</span><i class="ti ti-arrow-right"></i></button>
                        </div>
                    </div>
                </div>
            @endif

            @if ($step === 3)
                <div class="content active" wire:key="step-3">
                    <div class="content-header mb-3">
                        <h6 class="mb-0">Dirección</h6>
                        <small>Introduce tu dirección.</small>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-map-pin ti-sm me-1 text-body"></i> Dirección</label>
                            <input type="text" class="form-control" wire:model.blur="config.address" placeholder="Calle, número, piso" />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-flag ti-sm me-1 text-body"></i> Punto de referencia</label>
                            <input type="text" class="form-control" wire:model.blur="config.landmark" placeholder="Cerca de..." />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-building ti-sm me-1 text-body"></i> Ciudad</label>
                            <input type="text" class="form-control" wire:model.blur="config.city" placeholder="Madrid" />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-mailbox ti-sm me-1 text-body"></i> Código postal</label>
                            <input type="text" class="form-control" wire:model.blur="config.pincode" placeholder="28001" />
                        </div>
                        <div class="col-12 d-flex justify-content-between">
                            <button type="button" class="btn btn-label-secondary" wire:click="previousStep"><i class="ti ti-arrow-left me-sm-1"></i><span class="align-middle d-sm-inline-block d-none">Anterior</span></button>
                            <button type="button" class="btn btn-primary" wire:click="nextStep"><span class="align-middle d-sm-inline-block d-none me-sm-1">Siguiente</span><i class="ti ti-arrow-right"></i></button>
                        </div>
                    </div>
                </div>
            @endif

            @if ($step === 4)
                <div class="content active" wire:key="step-4">
                    <div class="content-header mb-3">
                        <h6 class="mb-0">Redes sociales</h6>
                        <small>Introduce los enlaces a tus redes sociales.</small>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-brand-x ti-sm me-1 text-body"></i> X (Twitter)</label>
                            <input type="text" class="form-control" wire:model.blur="config.twitter" placeholder="https://x.com/..." />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-brand-facebook ti-sm me-1 text-primary"></i> Facebook</label>
                            <input type="text" class="form-control" wire:model.blur="config.facebook" placeholder="https://facebook.com/..." />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-brand-instagram ti-sm me-1 text-danger"></i> Instagram</label>
                            <input type="text" class="form-control" wire:model.blur="config.instagram" placeholder="https://instagram.com/..." />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-brand-linkedin ti-sm me-1 text-primary"></i> LinkedIn</label>
                            <input type="text" class="form-control" wire:model.blur="config.linkedin" placeholder="https://linkedin.com/in/..." />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-brand-youtube ti-sm me-1 text-danger"></i> YouTube</label>
                            <input type="text" class="form-control" wire:model.blur="config.youtube" placeholder="https://youtube.com/..." />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-brand-tiktok ti-sm me-1 text-body"></i> TikTok</label>
                            <input type="text" class="form-control" wire:model.blur="config.tiktok" placeholder="https://tiktok.com/@" />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-brand-whatsapp ti-sm me-1 text-success"></i> WhatsApp</label>
                            <input type="text" class="form-control" wire:model.blur="config.whatsapp_url" placeholder="https://wa.me/..." />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-brand-telegram ti-sm me-1 text-info"></i> Telegram</label>
                            <input type="text" class="form-control" wire:model.blur="config.telegram" placeholder="https://t.me/..." />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-brand-pinterest ti-sm me-1 text-danger"></i> Pinterest</label>
                            <input type="text" class="form-control" wire:model.blur="config.pinterest" placeholder="https://pinterest.com/..." />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-brand-threads ti-sm me-1 text-body"></i> Threads</label>
                            <input type="text" class="form-control" wire:model.blur="config.threads" placeholder="https://threads.net/@" />
                        </div>
                        <div class="col-12 d-flex justify-content-between">
                            <button type="button" class="btn btn-label-secondary" wire:click="previousStep"><i class="ti ti-arrow-left me-sm-1"></i><span class="align-middle d-sm-inline-block d-none">Anterior</span></button>
                            <button type="button" class="btn btn-primary" wire:click="nextStep"><span class="align-middle d-sm-inline-block d-none me-sm-1">Siguiente</span><i class="ti ti-arrow-right"></i></button>
                        </div>
                    </div>
                </div>
            @endif

            @if ($step === 5)
                <div class="content active" wire:key="step-5">
                    <div class="content-header mb-3">
                        <h6 class="mb-0">Desafío de tu negocio</h6>
                        <small>Describe el reto o la situación actual. El Asistente AI generará un resumen conciso de lo que necesitas para mejorar.</small>
                    </div>
                    <div class="mb-4">
                        <label class="form-label"><i class="ti ti-puzzle ti-sm me-1 text-body"></i> Desafío</label>
                        <textarea class="form-control" wire:model.blur="config.business_problematica" wire:blur="triggerSummaryIfChanged" rows="4" placeholder="Describe brevemente el reto o la situación actual de tu empresa. El Asistente AI generará un resumen conciso al salir del campo (si el texto cambió)."></textarea>
                    </div>
                    @if ($summaryLoading || $summary)
                        <div class="card border-primary mb-4">
                            <div class="card-header bg-label-primary d-flex align-items-center gap-2">
                                <i class="ti ti-report-analytics ti-md"></i>
                                <span class="fw-medium">Resumen para mejorar tu empresa</span>
                            </div>
                            <div class="card-body">
                                @if ($summaryLoading)
                                    <div class="text-muted text-center py-3">
                                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                        Generando resumen con el Asistente AI de Humano…
                                    </div>
                                @elseif ($summary)
                                    <div class="small">{!! nl2br(e($summary)) !!}</div>
                                @endif
                            </div>
                        </div>
                    @endif
                    <div class="col-12 d-flex justify-content-between">
                        <button type="button" class="btn btn-label-secondary" wire:click="previousStep"><i class="ti ti-arrow-left me-sm-1"></i><span class="align-middle d-sm-inline-block d-none">Anterior</span></button>
                        <button type="button" class="btn btn-primary" wire:click="nextStep"><span class="align-middle d-sm-inline-block d-none me-sm-1">Siguiente</span><i class="ti ti-arrow-right"></i></button>
                    </div>
                </div>
            @endif

            @if ($step === 6)
                <div class="content active" wire:key="step-6">
                    <div class="content-header mb-3">
                        <h6 class="mb-0">Revisar y enviar</h6>
                        <small>Revisa los datos de mercado y envía tu configuración.</small>
                    </div>
                    <div class="mb-4">
                        <h6 class="mb-2">Datos de mercado</h6>
                        <p class="text-muted small mb-2">Indicadores de mercado, análisis de tu web y enlaces, posicionamiento frente a competidores y recomendaciones según tu sector y ubicación.</p>
                        @if (!$insightsLoading && empty($insights))
                            <button type="button" class="btn btn-outline-primary" wire:click="loadInsights" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="loadInsights"><i class="ti ti-chart-bar ti-sm me-1"></i> Cargar datos de mercado</span>
                                <span wire:loading wire:target="loadInsights">Cargando…</span>
                            </button>
                        @elseif ($insightsLoading)
                            <div class="d-flex align-items-center gap-2 text-muted">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                                <span>Analizando web, mercado y generando informe…</span>
                            </div>
                        @else
                            <div class="row g-3 mb-3">
                                @if (isset($insights['businesses_nearby']))
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="card border-primary h-100">
                                            <div class="card-body d-flex flex-column justify-content-center">
                                                <span class="d-block text-muted small mb-1">Negocios en tu zona</span>
                                                <span class="fs-3 fw-bold text-primary">{{ number_format($insights['businesses_nearby']) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                @if (isset($insights['prospects']))
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="card border-success h-100">
                                            <div class="card-body d-flex flex-column justify-content-center">
                                                <span class="d-block text-muted small mb-1">Prospectos</span>
                                                <span class="fs-3 fw-bold text-success">{{ number_format($insights['prospects']) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                @if (isset($insights['by_industry']) && is_array($insights['by_industry']))
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="card border-info h-100">
                                            <div class="card-body d-flex flex-column justify-content-center">
                                                <span class="d-block text-muted small mb-1">Por sector (muestra)</span>
                                                <span class="fs-3 fw-bold text-info">{{ number_format(array_sum($insights['by_industry'])) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            @if (!empty($insights['chart_series']))
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <span class="fw-medium">Distribución por sector</span>
                                    </div>
                                    <div class="card-body">
                                        <div id="business-insights-chart" class="min-h-200"></div>
                                        <script>
                                            (function() {
                                                var el = document.getElementById('business-insights-chart');
                                                if (!el || !window.ApexCharts) return;
                                                var data = @json($insights['chart_series'] ?? []);
                                                if (!data.categories || !data.series) return;
                                                try {
                                                    if (window.__businessInsightsChart) window.__businessInsightsChart.destroy();
                                                    window.__businessInsightsChart = new ApexCharts(el, {
                                                        chart: { type: 'bar', toolbar: { show: false }, height: 280 },
                                                        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%' } },
                                                        dataLabels: { enabled: true },
                                                        xaxis: { categories: data.categories },
                                                        series: [{ name: 'Empresas', data: data.series }],
                                                        colors: ['#696cff']
                                                    });
                                                    window.__businessInsightsChart.render();
                                                } catch (e) { console.warn('Chart init', e); }
                                            })();
                                        </script>
                                    </div>
                                </div>
                            @endif
                            @if (!empty($insights['potential_clients_summary']))
                                <div class="card border-secondary">
                                    <div class="card-header bg-label-secondary">
                                        <i class="ti ti-report-analytics ti-sm me-1"></i>
                                        <span class="fw-medium">Informe de mercado</span>
                                    </div>
                                    <div class="card-body pt-3">
                                        <div class="potential-clients-content small">
                                            {!! \Illuminate\Support\Str::markdown($insights['potential_clients_summary']) !!}
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                    <div class="col-12 d-flex justify-content-between mt-3">
                        <button type="button" class="btn btn-label-secondary" wire:click="previousStep"><i class="ti ti-arrow-left me-sm-1"></i><span class="align-middle d-sm-inline-block d-none">Anterior</span></button>
                        <button type="button" class="btn btn-success" wire:click="submit">Enviar informe completo por email</button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
