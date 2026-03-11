<div>
    <div class="bs-stepper wizard-icons wizard-modern wizard-modern-icons-example mt-2">
        <div class="bs-stepper-header">
            @foreach ([1 => 'Datos básicos del negocio', 2 => 'Información personal', 3 => 'Dirección', 4 => 'Redes sociales', 5 => 'Revisar y enviar'] as $num => $label)
                <div class="step {{ $step === $num ? 'active' : '' }}">
                    <button type="button" class="step-trigger" wire:click="goToStep({{ $num }})">
                        <span class="bs-stepper-icon">
                            @if ($num === 1)
                                <svg viewBox="0 0 54 54"><use xlink:href="{{ asset('assets/svg/icons/form-wizard-account.svg#wizardAccount') }}"></use></svg>
                            @elseif ($num === 2)
                                <svg viewBox="0 0 58 54"><use xlink:href="{{ asset('assets/svg/icons/form-wizard-personal.svg#wizardPersonal') }}"></use></svg>
                            @elseif ($num === 3)
                                <svg viewBox="0 0 54 54"><use xlink:href="{{ asset('assets/svg/icons/form-wizard-address.svg#wizardAddress') }}"></use></svg>
                            @elseif ($num === 4)
                                <svg viewBox="0 0 54 54"><use xlink:href="{{ asset('assets/svg/icons/form-wizard-social-link.svg#wizardSocialLink') }}"></use></svg>
                            @else
                                <svg viewBox="0 0 54 54"><use xlink:href="{{ asset('assets/svg/icons/form-wizard-submit.svg#wizardSubmit') }}"></use></svg>
                            @endif
                        </span>
                        <span class="bs-stepper-label">{{ $label }}</span>
                    </button>
                </div>
                @if ($num < 5)
                    <div class="line"><i class="ti ti-chevron-right"></i></div>
                @endif
            @endforeach
        </div>
        <div class="bs-stepper-content">
            @if ($step === 1)
                <div class="content active" wire:key="step-1">
                    <div class="content-header mb-3">
                        <h6 class="mb-0">Datos básicos del negocio</h6>
                        <small>Nombre, rubro, ubicación, logo y descripción de tu negocio.</small>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label d-block"><i class="ti ti-photo ti-sm me-1 text-body"></i> Logo</label>
                            <div class="d-flex align-items-start gap-3">
                                <div class="rounded border bg-lighter p-2" style="width: 80px; height: 80px;">
                                    <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                        <i class="ti ti-building-store ti-xl"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control" accept="image/*" />
                                    <small class="text-muted">Recomendado: imagen cuadrada, PNG o JPG, máx. 2 MB</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="ti ti-building-store ti-sm me-1 text-body"></i> Nombre del negocio (*)</label>
                            <input type="text" class="form-control" wire:model.blur="config.business_name" placeholder="Nombre de tu empresa o marca" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="ti ti-category ti-sm me-1 text-body"></i> Rubro / Sector</label>
                            <input type="text" class="form-control" wire:model.blur="config.business_industry" placeholder="ej. Tecnología, Retail, Servicios" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="ti ti-map-pin ti-sm me-1 text-body"></i> Ubicación / Dirección</label>
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
                        <div class="col-12">
                            <label class="form-label"><i class="ti ti-quote ti-sm me-1 text-body"></i> Eslogan</label>
                            <input type="text" class="form-control" wire:model.blur="config.business_tagline" placeholder="Frase corta que defina tu negocio" />
                        </div>
                        <div class="col-12">
                            <label class="form-label"><i class="ti ti-file-description ti-sm me-1 text-body"></i> Descripción</label>
                            <textarea class="form-control" wire:model.blur="config.business_description" rows="4" placeholder="¿Qué hace tu negocio? ¿A quién va dirigido?"></textarea>
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
                            <input type="date" class="form-control" wire:model.blur="config.birth_date" />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label"><i class="ti ti-clock ti-sm me-1 text-body"></i> Hora de nacimiento</label>
                            <input type="time" class="form-control" wire:model.blur="config.birth_time" placeholder="HH:MM" />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">País</label>
                            <select class="form-select" wire:model.blur="config.country">
                                <option value=""> </option>
                                <option>España</option>
                                <option>México</option>
                                <option>Argentina</option>
                                <option>Colombia</option>
                                <option>Chile</option>
                                <option>Perú</option>
                                <option>Reino Unido</option>
                                <option>Estados Unidos</option>
                                <option>Francia</option>
                                <option>Italia</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Idioma</label>
                            <select class="form-select" wire:model.blur="config.language" multiple>
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
                            <label class="form-label">Dirección</label>
                            <input type="text" class="form-control" wire:model.blur="config.address" placeholder="Calle, número, piso" />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Punto de referencia</label>
                            <input type="text" class="form-control" wire:model.blur="config.landmark" placeholder="Cerca de..." />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Código postal</label>
                            <input type="text" class="form-control" wire:model.blur="config.pincode" placeholder="28001" />
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Ciudad</label>
                            <input type="text" class="form-control" wire:model.blur="config.city" placeholder="Madrid" />
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
                            <label class="form-label"><i class="ti ti-brand-whatsapp ti-sm me-1 text-body"></i> WhatsApp</label>
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
                    <div class="mb-4">
                        <label class="form-label"><i class="ti ti-message-question ti-sm me-1 text-body"></i> Problemática actual de tu negocio</label>
                        <textarea class="form-control" wire:model.blur="config.business_problematica" rows="4" placeholder="Describe brevemente el reto o la situación actual de tu empresa. El Asistente AI de Humano generará un resumen conciso de lo que necesitas para mejorar."></textarea>
                        <div class="d-flex justify-content-end mt-2">
                            <button type="button" class="btn btn-primary" wire:click="generateSummary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="generateSummary"><i class="ti ti-sparkles ti-sm me-1"></i> Generar resumen con IA</span>
                                <span wire:loading wire:target="generateSummary">Generando…</span>
                            </button>
                        </div>
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
                    <hr>
                    <p class="fw-medium mb-2">Datos básicos del negocio</p>
                    <ul class="list-unstyled">
                        <li>Nombre del negocio, Rubro, Ubicación, Código postal</li>
                        <li>Logo, Eslogan, Descripción</li>
                        <li>Teléfono, WhatsApp, Página web, Email</li>
                    </ul>
                    <hr>
                    <p class="fw-medium mb-2">Información personal</p>
                    <ul class="list-unstyled">
                        <li>Nombre, Apellidos</li>
                        <li>Fecha de nacimiento, Hora de nacimiento</li>
                        <li>País, Idioma</li>
                    </ul>
                    <hr>
                    <p class="fw-medium mb-2">Dirección</p>
                    <ul class="list-unstyled">
                        <li>Dirección, Punto de referencia</li>
                        <li>Código postal, Ciudad</li>
                    </ul>
                    <hr>
                    <p class="fw-medium mb-2">Redes sociales</p>
                    <ul class="list-unstyled mb-0">
                        <li>X, Facebook, Instagram, LinkedIn, YouTube, TikTok, WhatsApp, Telegram, Pinterest, Threads</li>
                    </ul>
                    <div class="col-12 d-flex justify-content-between mt-3">
                        <button type="button" class="btn btn-label-secondary" wire:click="previousStep"><i class="ti ti-arrow-left me-sm-1"></i><span class="align-middle d-sm-inline-block d-none">Anterior</span></button>
                        <button type="button" class="btn btn-success" wire:click="submit">Enviar</button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
