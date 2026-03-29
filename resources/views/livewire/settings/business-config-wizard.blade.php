<div>
    <style>
        .wizard-modern .bs-stepper-icon .wizard-step-icon { font-size: 1.75rem; }

        .business-wizard-time-input::-webkit-calendar-picker-indicator { display: none; }
        .business-wizard-time-input::-webkit-datetime-edit-ampm-field { display: none; }

        /* AI futuristic loader — full takeover when loading */
        .ai-loader-overlay {
            animation: ai-loader-fade-in 0.4s ease-out;
            position: relative;
            overflow: hidden;
            border-radius: 1rem;
            background: linear-gradient(135deg, var(--bs-body-bg, #fff) 0%, var(--bs-secondary-bg, #f8f9fa) 100%);
            border: 1px solid var(--bs-border-color, #e9ecef);
            box-shadow: 0 0 0 1px rgba(105, 108, 255, 0.08), 0 8px 24px rgba(105, 108, 255, 0.12);
        }
        .ai-loader-overlay::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            padding: 2px;
            background: linear-gradient(110deg, transparent 0%, rgba(105, 108, 255, 0.4) 25%, rgba(105, 108, 255, 0.8) 50%, rgba(105, 108, 255, 0.4) 75%, transparent 100%);
            background-size: 200% 200%;
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            animation: ai-loader-border-flow 2.5s linear infinite;
            pointer-events: none;
        }
        @keyframes ai-loader-border-flow {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        @keyframes ai-loader-fade-in {
            from { opacity: 0; transform: translateY(8px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .ai-loader-grid {
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(105, 108, 255, 0.03) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(105, 108, 255, 0.03) 1px, transparent 1px);
            background-size: 24px 24px;
            pointer-events: none;
        }
        .ai-loader-core {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            margin: 0 auto 1.25rem;
        }
        .ai-loader-core .ai-loader-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 2px solid transparent;
            border-top-color: rgba(105, 108, 255, 0.9);
            border-right-color: rgba(105, 108, 255, 0.4);
            animation: ai-loader-spin 1s linear infinite;
        }
        .ai-loader-core .ai-loader-ring:nth-child(2) {
            inset: 8px;
            border-top-color: rgba(105, 108, 255, 0.5);
            border-right-color: rgba(105, 108, 255, 0.2);
            animation-duration: 1.4s;
            animation-direction: reverse;
        }
        .ai-loader-core .ai-loader-ring:nth-child(3) {
            inset: 16px;
            border-top-color: rgba(105, 108, 255, 0.3);
            animation-duration: 1.8s;
        }
        .ai-loader-core .ai-loader-icon {
            position: relative;
            font-size: 1.75rem;
            color: rgba(105, 108, 255, 0.95);
            filter: drop-shadow(0 0 12px rgba(105, 108, 255, 0.4));
            animation: ai-loader-pulse 2s ease-in-out infinite;
        }
        @keyframes ai-loader-spin {
            to { transform: rotate(360deg); }
        }
        @keyframes ai-loader-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.85; transform: scale(1.05); }
        }
        .ai-loader-scan-line {
            position: absolute;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(105, 108, 255, 0.6), transparent);
            animation: ai-loader-scan 2s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes ai-loader-scan {
            0%, 100% { top: 20%; opacity: 0.5; }
            50% { top: 80%; opacity: 1; }
        }
        .ai-loader-dots {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 0.5rem;
        }
        .ai-loader-dots span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: rgba(105, 108, 255, 0.8);
            animation: ai-loader-dot 1.2s ease-in-out infinite both;
        }
        .ai-loader-dots span:nth-child(1) { animation-delay: 0s; }
        .ai-loader-dots span:nth-child(2) { animation-delay: 0.2s; }
        .ai-loader-dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes ai-loader-dot {
            0%, 80%, 100% { transform: scale(0.6); opacity: 0.5; }
            40% { transform: scale(1); opacity: 1; }
        }

        /* Informe de mercado — markdown content styling (reference: Resumen para mejorar tu empresa) */
        .potential-clients-content {
            font-size: 0.9375rem;
            line-height: 1.6;
            color: var(--bs-body-color);
        }
        .potential-clients-content h1,
        .potential-clients-content h2,
        .potential-clients-content h3 {
            font-weight: 600;
            margin-top: 1.25rem;
            margin-bottom: 0.5rem;
            color: var(--bs-heading-color, inherit);
        }
        .potential-clients-content h1 { font-size: 1.25rem; margin-top: 0; }
        .potential-clients-content > *:first-child { margin-top: 1rem; }
        .potential-clients-content h2 { font-size: 1.1rem; }
        .potential-clients-content h3 { font-size: 1rem; }
        .potential-clients-content p {
            margin-bottom: 0.75rem;
        }
        .potential-clients-content p:last-child { margin-bottom: 0; }
        .potential-clients-content ul,
        .potential-clients-content ol {
            margin-bottom: 0.75rem;
            padding-left: 1.25rem;
        }
        .potential-clients-content li {
            margin-bottom: 0.35rem;
        }
        .potential-clients-content strong { font-weight: 600; }
        .potential-clients-content em { font-style: italic; }
        .potential-clients-content a {
            color: var(--bs-primary);
            text-decoration: underline;
        }
        .potential-clients-content a:hover {
            color: var(--bs-primary);
            text-decoration: none;
        }
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
                        <small>Nombre, sector, ubicación, logo y descripción de tu negocio.</small>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><i class="ti ti-building-store ti-sm me-1 text-body"></i> Nombre del negocio (*)</label>
                            <input type="text" class="form-control" wire:model.blur="config.business_name" placeholder="Nombre de tu empresa o marca" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="ti ti-category ti-sm me-1 text-body"></i> Sector (*)</label>
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
                                    <label class="form-label"><i class="ti ti-file-description ti-sm me-1 text-body"></i> Descripción (*)</label>
                                    <textarea class="form-control" wire:model.blur="config.business_description" placeholder="¿Qué hace tu negocio? ¿A quién va dirigido?" style="height: 120px; resize: vertical;"></textarea>
                                    @error('logo')
                                        <small class="text-danger d-block mt-1">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><i class="ti ti-quote ti-sm me-1 text-body"></i> Propuesta de valor (*)</label>
                            <input type="text" class="form-control" wire:model.blur="config.business_tagline" placeholder="Frase corta que defina tu negocio" />
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
                            <label class="form-label"><i class="ti ti-mail ti-sm me-1 text-body"></i> Email del negocio</label>
                            <input type="email" class="form-control" wire:model.blur="config.business_email" placeholder="contacto@empresa.com" />
                        </div>
                        <div class="col-12 d-flex justify-content-between">
                            @if ($isLandingWizard)
                                <a href="{{ url('/') }}" class="btn btn-label-secondary"><i class="ti ti-arrow-left me-sm-1"></i><span class="align-middle d-sm-inline-block d-none">Volver al home</span></a>
                            @else
                                <button type="button" class="btn btn-label-secondary" disabled><i class="ti ti-arrow-left me-sm-1"></i><span class="align-middle d-sm-inline-block d-none">Anterior</span></button>
                            @endif
                            <button type="button" class="btn btn-primary" wire:click="nextStep"><span class="align-middle d-sm-inline-block d-none me-sm-1">Siguiente</span><i class="ti ti-arrow-right"></i></button>
                        </div>
                    </div>
                </div>
            @endif

            @if ($step === 2)
                <div class="content active" wire:key="step-2">
                    <div class="content-header mb-3">
                        <h6 class="mb-0">Información personal</h6>
                        <small>Introduce tu información personal. Cuanto más te conozcamos, más te podremos ayudar con la gestión de tus clientes.</small>
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
                            <input type="text" id="business-wizard-birth-date" class="form-control flatpickr-birth-date" wire:model.live="config.birth_date" value="{{ $config['birth_date'] ?? '' }}" placeholder="DD-MM-AAAA" />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Hora de nacimiento</label>
                            <input type="time" class="form-control business-wizard-time-input" wire:model.blur="config.birth_time" placeholder="HH:MM" />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-mail ti-sm me-1 text-body"></i> Email de contacto</label>
                            <input type="email" class="form-control" wire:model.blur="config.contact_email" placeholder="tu@email.com" />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-brand-whatsapp ti-sm me-1 text-body"></i> WhatsApp</label>
                            <input type="tel" class="form-control" wire:model.blur="config.business_whatsapp" placeholder="+34 600 000 000" />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-world ti-sm me-1 text-body"></i> País</label>
                            <select id="business-wizard-country" class="form-select select2-select" wire:model.live="config.country" data-placeholder="Seleccionar país">
                                <option value="">Seleccionar país</option>
                                <option value="Argentina">Argentina</option>
                                <option value="Chile">Chile</option>
                                <option value="Colombia">Colombia</option>
                                <option value="España">España</option>
                                <option value="Estados Unidos">Estados Unidos</option>
                                <option value="Francia">Francia</option>
                                <option value="Italia">Italia</option>
                                <option value="México">México</option>
                                <option value="Perú">Perú</option>
                                <option value="Reino Unido">Reino Unido</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-language ti-sm me-1 text-body"></i> Idioma</label>
                            <select id="business-wizard-language" class="form-select select2-select" wire:model.live="config.language" data-placeholder="Seleccionar idioma">
                                <option value="">Seleccionar idioma</option>
                                <option value="Catalán">Catalán</option>
                                <option value="Español">Español</option>
                                <option value="Francés">Francés</option>
                                <option value="Inglés">Inglés</option>
                                <option value="Italiano">Italiano</option>
                                <option value="Portugués">Portugués</option>
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
                        <small>Introduce tu dirección (no aplica para negocios digitales).</small>
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
<label class="form-label"><i class="ti ti-building ti-sm me-1 text-body"></i> Ciudad, país</label>
                                            <input type="text" class="form-control" wire:model.blur="config.city" placeholder="Madrid, España" />
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
                <div class="content active" wire:key="step-5" @if(method_exists($this, 'checkSummaryReady')) wire:poll.3s="checkSummaryReady" @endif>
                    <div class="content-header mb-3">
                        <h6 class="mb-0">Desafío de tu negocio</h6>
                        <small>Describe el reto o la situación actual. El Asistente AI generará un resumen conciso de lo que necesitas para mejorar.</small>
                    </div>
                    <div class="mb-4">
                        <label class="form-label"><i class="ti ti-puzzle ti-sm me-1 text-body"></i> Desafío</label>
                        <textarea class="form-control" wire:model.blur="config.business_challenge" rows="4" placeholder="Describe brevemente el reto o la situación actual de tu empresa. Luego pulsa «Generar resumen» para que el Asistente AI genere un resumen conciso."></textarea>
                        @php
                            $canLoadSummary = filled($config['business_challenge'] ?? null);
                        @endphp
                    </div>
                    @if ($summaryLoading)
                        <div class="d-flex justify-content-center">
                            <div class="ai-loader-overlay p-4 p-md-5 text-center">
                                <div class="ai-loader-grid" aria-hidden="true"></div>
                                <div class="ai-loader-scan-line" aria-hidden="true"></div>
                                <div class="ai-loader-core">
                                    <span class="ai-loader-ring" aria-hidden="true"></span>
                                    <span class="ai-loader-ring" aria-hidden="true"></span>
                                    <span class="ai-loader-ring" aria-hidden="true"></span>
                                    <i class="ti ti-cpu ai-loader-icon" aria-hidden="true"></i>
                                </div>
                                <h6 class="mb-1 fw-semibold text-body">El asistente Humano.App está generando tu informe</h6>
                                <p class="mb-0 small text-muted">Generando resumen con el asistente Humano.App…</p>
                                <div class="ai-loader-dots" aria-hidden="true"><span></span><span></span><span></span></div>
                            </div>
                        </div>
                    @elseif ($summary)
                        @if (app()->environment('local') && method_exists($this, 'clearSummaryFromSession'))
                            <div class="mb-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="clearSummaryFromSession" title="Quitar el resumen guardado para poder generarlo de nuevo (solo local)">
                                    <i class="ti ti-trash ti-sm me-1"></i> Eliminar resumen
                                </button>
                            </div>
                        @endif
                        <div class="card border-primary mb-4">
                            <div class="card-header bg-label-primary d-flex align-items-center gap-2">
                                <i class="ti ti-report-analytics ti-md"></i>
                                <span class="fw-medium">Resumen para mejorar tu empresa</span>
                            </div>
                            <div class="card-body">
                                @php
                                    $summaryDisplay = preg_replace('/\s*¿Te gustaría profundizar en alguno de estos puntos\?\s*/u', "\n", (string) $summary);
                                @endphp
                                <div class="potential-clients-content">{!! \Illuminate\Support\Str::markdown($summaryDisplay) !!}</div>
                                <p class="mb-1 mt-3 fw-medium">¿Te gustaría profundizar en alguno de estos puntos?</p>
                                <p class="small text-muted mb-2">De ser así, un consultor de nuestro equipo podría contactarte para profundizar sobre estos puntos.</p>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button type="button" class="btn {{ ($config['wants_to_deepen'] ?? '') === 'si' ? 'btn-primary' : 'btn-outline-primary' }}" wire:click="setWantsToDeepen('si')">Sí</button>
                                    <button type="button" class="btn {{ ($config['wants_to_deepen'] ?? '') === 'no' ? 'btn-secondary' : 'btn-outline-secondary' }}" wire:click="setWantsToDeepen('no')">No</button>
                                </div>
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
                <div class="content active" wire:key="step-6" @if(method_exists($this, 'checkProcessingReady')) wire:poll.3s="checkProcessingReady" @elseif(method_exists($this, 'checkInsightsReady')) wire:poll.3s="checkInsightsReady" @endif>
                    <div class="content-header mb-3">
                        <h6 class="mb-0">Revisar y enviar</h6>
                        <small>Revisa los datos de mercado y envía tu configuración.</small>
                    </div>
                    <div class="mb-4">
                        <h6 class="mb-2">Datos de mercado</h6>
                        <p class="text-muted small mb-2">Indicadores de mercado, análisis de tu web y enlaces, posicionamiento frente a competidores y recomendaciones según tu sector y ubicación.</p>
                        @php
                            $insightsLoaderSubtitle = match($insightsPhase ?? '') {
                                'market_data' => 'Consultando datos de mercado y sector...',
                                'web' => 'Analizando tu web...',
                                'recommendations' => 'Generando recomendaciones con el asistente Humano.App...',
                                default => (($summaryLoading ?? false) || (($finalFlowPhase ?? null) === 'summary'))
                                    ? 'Procesando el desafío de tu negocio...'
                                    : 'Procesando datos de mercado, web y recomendaciones · Se actualizará al terminar',
                            };
                        @endphp
                        @if ($summaryLoading || (($finalFlowPhase ?? null) === 'summary'))
                            <div class="d-flex justify-content-center">
                                <div class="ai-loader-overlay p-4 p-md-5 text-center">
                                    <div class="ai-loader-grid" aria-hidden="true"></div>
                                    <div class="ai-loader-scan-line" aria-hidden="true"></div>
                                    <div class="ai-loader-core">
                                        <span class="ai-loader-ring" aria-hidden="true"></span>
                                        <span class="ai-loader-ring" aria-hidden="true"></span>
                                        <span class="ai-loader-ring" aria-hidden="true"></span>
                                        <i class="ti ti-cpu ai-loader-icon" aria-hidden="true"></i>
                                    </div>
                                    <h6 class="mb-1 fw-semibold text-body">El asistente Humano.App está generando tu informe</h6>
                                    <p class="mb-0 small text-muted">{{ $insightsLoaderSubtitle }}</p>
                                    <div class="ai-loader-dots" aria-hidden="true"><span></span><span></span><span></span></div>
                                </div>
                            </div>
                        @elseif (!$insightsLoading && empty($insights))
                            @php
                                $canLoadInsights = filled($config['business_industry'] ?? null) && filled($config['business_description'] ?? null) && filled($config['business_tagline'] ?? null);
                            @endphp
                            @if (!$canLoadInsights)
                                <div class="alert alert-warning mb-0">
                                    <div class="d-flex align-items-center flex-wrap gap-2">
                                        <i class="ti ti-alert-triangle ti-lg me-2 flex-shrink-0"></i>
                                        <div class="flex-grow-1">
                                            <span class="fw-medium">Completa sector, descripción y propuesta de valor para cargar los datos de mercado.</span>
                                        </div>
                                        <button type="button" class="btn btn-warning btn-sm" wire:click="goToStep(1)">
                                            <i class="ti ti-building-store ti-sm me-1"></i> Ir a datos del negocio
                                        </button>
                                    </div>
                                </div>
                            @else
                                <div class="alert alert-info mb-0">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="ti ti-info-circle ti-lg"></i>
                                        <span class="fw-medium">
                                            @if ($isLandingWizard)
                                                Al pulsar el botón final se generará primero el informe de mercado y luego podrás enviarlo por email.
                                            @else
                                                Al pulsar el botón se generará el informe de mercado y se guardará junto a la configuración de tu equipo.
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            @endif
                            @if ($canLoadInsights ?? true)
                            <div class="d-flex justify-content-center">
                                <div wire:loading wire:target="loadInsights" class="ai-loader-overlay p-4 p-md-5 text-center">
                                    <div class="ai-loader-grid" aria-hidden="true"></div>
                                    <div class="ai-loader-scan-line" aria-hidden="true"></div>
                                    <div class="ai-loader-core">
                                        <span class="ai-loader-ring" aria-hidden="true"></span>
                                        <span class="ai-loader-ring" aria-hidden="true"></span>
                                        <span class="ai-loader-ring" aria-hidden="true"></span>
                                        <i class="ti ti-cpu ai-loader-icon" aria-hidden="true"></i>
                                    </div>
                                    <h6 class="mb-1 fw-semibold text-body">El asistente Humano.App está generando tu informe</h6>
                                    <p class="mb-0 small text-muted">{{ $insightsLoaderSubtitle }}</p>
                                    <div class="ai-loader-dots" aria-hidden="true"><span></span><span></span><span></span></div>
                                </div>
                            </div>
                            @endif
                        @elseif ($insightsLoading)
                            <div class="d-flex justify-content-center" @if(method_exists($this, 'checkInsightsReady')) wire:poll.3s="checkInsightsReady" @endif>
                            <div class="ai-loader-overlay p-4 p-md-5 text-center">
                                <div class="ai-loader-grid" aria-hidden="true"></div>
                                <div class="ai-loader-scan-line" aria-hidden="true"></div>
                                <div class="ai-loader-core">
                                    <span class="ai-loader-ring" aria-hidden="true"></span>
                                    <span class="ai-loader-ring" aria-hidden="true"></span>
                                    <span class="ai-loader-ring" aria-hidden="true"></span>
                                    <i class="ti ti-cpu ai-loader-icon" aria-hidden="true"></i>
                                </div>
                                <h6 class="mb-1 fw-semibold text-body">El asistente Humano.App está generando tu informe</h6>
                                <p class="mb-0 small text-muted">{{ $insightsLoaderSubtitle }}</p>
                                <div class="ai-loader-dots" aria-hidden="true"><span></span><span></span><span></span></div>
                            </div>
                            </div>
                        @else
                            @if (app()->environment('local') && method_exists($this, 'clearReportFromSession'))
                                <div class="mb-3">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="clearReportFromSession" title="Quitar el informe guardado para poder generarlo de nuevo (solo local)">
                                        <i class="ti ti-trash ti-sm me-1"></i> Eliminar informe
                                    </button>
                                </div>
                            @endif
                            <div class="row g-3 mb-3">
                                @if (isset($insights['businesses_nearby']))
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="card border-primary h-100">
                                            <div class="card-body d-flex flex-column justify-content-center">
                                                <span class="d-block text-muted small mb-1"><i class="ti ti-building-store ti-sm me-1"></i> Negocios en tu zona</span>
                                                <span class="fs-3 fw-bold text-primary">{{ number_format($insights['businesses_nearby']) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                @if (isset($insights['prospects']))
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="card border-success h-100">
                                            <div class="card-body d-flex flex-column justify-content-center">
                                                <span class="d-block text-muted small mb-1"><i class="ti ti-users ti-sm me-1"></i> Prospectos</span>
                                                <span class="fs-3 fw-bold text-success">{{ number_format($insights['prospects']) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                @if (isset($insights['seniority_c_suite']))
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="card border-secondary h-100">
                                            <div class="card-body d-flex flex-column justify-content-center">
                                                <span class="d-block text-muted small mb-1"><i class="ti ti-crown ti-sm me-1"></i> C-Suite</span>
                                                <span class="fs-3 fw-bold text-secondary">{{ number_format($insights['seniority_c_suite']) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                @if (isset($insights['seniority_director']))
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="card border-secondary h-100">
                                            <div class="card-body d-flex flex-column justify-content-center">
                                                <span class="d-block text-muted small mb-1"><i class="ti ti-briefcase ti-sm me-1"></i> Directores</span>
                                                <span class="fs-3 fw-bold text-secondary">{{ number_format($insights['seniority_director']) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                @if (isset($insights['seniority_manager']))
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="card border-secondary h-100">
                                            <div class="card-body d-flex flex-column justify-content-center">
                                                <span class="d-block text-muted small mb-1"><i class="ti ti-user-check ti-sm me-1"></i> Managers</span>
                                                <span class="fs-3 fw-bold text-secondary">{{ number_format($insights['seniority_manager']) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                @php $byIndustrySum = isset($insights['by_industry']) && is_array($insights['by_industry']) ? array_sum($insights['by_industry']) : 0; @endphp
                                @if ($byIndustrySum > 0)
                                    <div class="col-sm-6 col-lg-3">
                                        <div class="card border-info h-100">
                                            <div class="card-body d-flex flex-column justify-content-center">
                                                <span class="d-block text-muted small mb-1"><i class="ti ti-chart-bar ti-sm me-1"></i> Por sector</span>
                                                <span class="fs-3 fw-bold text-info">{{ number_format($byIndustrySum) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            @if (!empty($insights['potential_clients_summary']))
                                <div class="card border-primary">
                                    <div class="card-header bg-label-primary d-flex align-items-center gap-2">
                                        <i class="ti ti-report-analytics ti-md"></i>
                                        <span class="fw-medium">Informe de mercado</span>
                                    </div>
                                    <div class="card-body pt-3">
                                        <div class="potential-clients-content">
                                            {!! \Illuminate\Support\Str::markdown($insights['potential_clients_summary']) !!}
                                        </div>
                                        <p class="small text-muted mb-0 mt-2">{{ __('Los indicadores de mercado se han obtenido a partir de bases de datos de empresas y profesionales (sector y ubicación).') }}</p>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                    @php
                        $hasReport = !empty($insights['potential_clients_summary']);
                        $hasContactEmail = filled($config['contact_email'] ?? null);
                        $hasBusinessEmail = filled($config['business_email'] ?? null);
                        $hasEmail = $hasContactEmail || $hasBusinessEmail;
                        $canLoadInsights = filled($config['business_industry'] ?? null) && filled($config['business_description'] ?? null) && filled($config['business_tagline'] ?? null);
                        if ($isLandingWizard) {
                            $canSubmit = !$insightsLoading && $hasEmail && (($hasReport) || (!$hasReport && $canLoadInsights));
                        } else {
                            $canSubmit = !$insightsLoading && (($hasReport) || (!$hasReport && $canLoadInsights));
                        }
                    @endphp
                    @if (!$hasEmail && $isLandingWizard)
                        <div class="col-12 mb-3">
                            <div class="alert alert-warning mb-0">
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <i class="ti ti-mail ti-lg me-2"></i>
                                    <div class="flex-grow-1">
                                        <span class="fw-medium">Completa tu email para recibir el informe.</span>
                                    </div>
                                    <button type="button" class="btn btn-warning btn-sm" wire:click="goToStep(2)">
                                        <i class="ti ti-user ti-sm me-1"></i> Ir a Información personal
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                    @if ($showEmailRequired && method_exists($this, 'provideEmail'))
                        <div class="col-12 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <label class="form-label">Email</label>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <input type="email" class="form-control flex-grow-1" style="min-width: 200px;" wire:model="config.contact_email" placeholder="tu@email.com" />
                                        <button type="button" class="btn btn-primary" wire:click="provideEmail" wire:loading.attr="disabled">
                                            <span wire:loading.remove wire:target="provideEmail">Enviar informe</span>
                                            <span wire:loading wire:target="provideEmail">Enviando…</span>
                                        </button>
                                    </div>
                                    @error('config.contact_email')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    @endif
                    @if ($isLandingWizard && ($reportSent ?? false))
                        <div class="col-12 mb-3">
                            <div class="alert alert-success mb-0">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="ti ti-circle-check ti-lg"></i>
                                    <span class="fw-medium">Informe enviado por email correctamente.</span>
                                </div>
                            </div>
                        </div>
                    @endif
                    @if (!$isLandingWizard && $hasReport && !($insightsLoading ?? false) && method_exists($this, 'regenerateMarketInsightsReport'))
                        <div class="col-12 mt-2">
                            <p class="small text-muted mb-0">
                                {{ __('Si actualizaste rubro, descripción o propuesta de valor en pasos anteriores, regenerá el informe para que refleje los cambios.') }}
                            </p>
                        </div>
                    @endif
                    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                        <button type="button" class="btn btn-label-secondary" wire:click="previousStep"><i class="ti ti-arrow-left me-sm-1"></i><span class="align-middle d-sm-inline-block d-none">Anterior</span></button>
                        @if (!($finalFlowRequested ?? false) && !($reportSent ?? false))
                            <div class="d-flex gap-2 flex-wrap justify-content-end ms-auto">
                                @if (!$isLandingWizard && $hasReport && method_exists($this, 'regenerateMarketInsightsReport'))
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary"
                                        wire:click="regenerateMarketInsightsReport"
                                        wire:loading.attr="disabled"
                                        @disabled(!$canLoadInsights || ($insightsLoading ?? false))
                                        title="{{ $canLoadInsights ? '' : __('Completa rubro, descripción y propuesta de valor (paso 1) para regenerar.') }}"
                                    >
                                        <span wire:loading.remove wire:target="regenerateMarketInsightsReport">
                                            <i class="ti ti-refresh ti-sm me-1"></i>{{ __('Regenerar informe') }}
                                        </span>
                                        <span wire:loading wire:target="regenerateMarketInsightsReport">
                                            {{ __('Encolando…') }}
                                        </span>
                                    </button>
                                @endif
                                <button
                                    type="button"
                                    class="btn btn-success"
                                    wire:click="submit"
                                    @disabled(!$canSubmit)
                                >
                                    <i class="ti ti-send ti-sm me-1"></i>
                                    <span wire:loading.remove wire:target="submit,loadInsights,regenerateMarketInsightsReport">
                                    @if ($isLandingWizard)
                                        @if ($hasReport && $hasEmail)
                                            Enviar informe completo por email
                                        @elseif (!$hasReport)
                                            Generar reporte y enviarlo por email
                                        @else
                                            Completa tu email para enviar
                                        @endif
                                    @else
                                        @if ($hasReport)
                                            Guardar configuración
                                        @else
                                            Generar informe de mercado
                                        @endif
                                    @endif
                                    </span>
                                    <span wire:loading wire:target="submit,loadInsights,regenerateMarketInsightsReport">
                                        Procesando...
                                    </span>
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
