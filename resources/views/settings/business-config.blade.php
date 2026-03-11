@extends('layouts/layoutMaster')

@section('title', 'Configuración del negocio')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/bs-stepper/bs-stepper.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/bootstrap-select/bootstrap-select.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/bs-stepper/bs-stepper.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/bootstrap-select/bootstrap-select.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('page-script')
<script src="{{ asset('assets/js/form-wizard-icons.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var logoInput = document.getElementById('business-logo-modern');
  var preview = document.getElementById('business-logo-preview-modern');
  var placeholder = document.getElementById('business-logo-placeholder-modern');
  if (logoInput && preview && placeholder) {
    logoInput.addEventListener('change', function () {
      var file = this.files && this.files[0];
      if (file && file.type.indexOf('image') !== -1) {
        var reader = new FileReader();
        reader.onload = function (e) {
          preview.src = e.target.result;
          preview.classList.remove('d-none');
          placeholder.classList.add('d-none');
        };
        reader.readAsDataURL(file);
      } else {
        preview.src = '';
        preview.classList.add('d-none');
        placeholder.classList.remove('d-none');
      }
    });
  }
});
</script>
@endsection

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3">
    <div class="d-flex flex-column justify-content-center">
        <h4 class="mb-1 mt-3"><span class="text-muted fw-light">Ajustes/</span> Configuración del negocio</h4>
        <p class="text-muted">Configura los datos de tu negocio paso a paso.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="{{ route('team-settings.index', $team) }}" class="btn btn-label-secondary">
            <i class="ti ti-arrow-left me-1"></i> Volver a Ajustes
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="bs-stepper wizard-icons wizard-modern wizard-modern-icons-example mt-2">
            <div class="bs-stepper-header">
                <div class="step" data-target="#account-details-modern">
                    <button type="button" class="step-trigger">
                        <span class="bs-stepper-icon">
                            <svg viewBox="0 0 54 54">
                                <use xlink:href="{{ asset('assets/svg/icons/form-wizard-account.svg#wizardAccount') }}"></use>
                            </svg>
                        </span>
                        <span class="bs-stepper-label">Datos básicos del negocio</span>
                    </button>
                </div>
                <div class="line">
                    <i class="ti ti-chevron-right"></i>
                </div>
                <div class="step" data-target="#personal-info-modern">
                    <button type="button" class="step-trigger">
                        <span class="bs-stepper-icon">
                            <svg viewBox="0 0 58 54">
                                <use xlink:href="{{ asset('assets/svg/icons/form-wizard-personal.svg#wizardPersonal') }}"></use>
                            </svg>
                        </span>
                        <span class="bs-stepper-label">Información personal</span>
                    </button>
                </div>
                <div class="line">
                    <i class="ti ti-chevron-right"></i>
                </div>
                <div class="step" data-target="#address-modern">
                    <button type="button" class="step-trigger">
                        <span class="bs-stepper-icon">
                            <svg viewBox="0 0 54 54">
                                <use xlink:href="{{ asset('assets/svg/icons/form-wizard-address.svg#wizardAddress') }}"></use>
                            </svg>
                        </span>
                        <span class="bs-stepper-label">Dirección</span>
                    </button>
                </div>
                <div class="line">
                    <i class="ti ti-chevron-right"></i>
                </div>
                <div class="step" data-target="#social-links-modern">
                    <button type="button" class="step-trigger">
                        <span class="bs-stepper-icon">
                            <svg viewBox="0 0 54 54">
                                <use xlink:href="{{ asset('assets/svg/icons/form-wizard-social-link.svg#wizardSocialLink') }}"></use>
                            </svg>
                        </span>
                        <span class="bs-stepper-label">Redes sociales</span>
                    </button>
                </div>
                <div class="line">
                    <i class="ti ti-chevron-right"></i>
                </div>
                <div class="step" data-target="#review-submit-modern">
                    <button type="button" class="step-trigger">
                        <span class="bs-stepper-icon">
                            <svg viewBox="0 0 54 54">
                                <use xlink:href="{{ asset('assets/svg/icons/form-wizard-submit.svg#wizardSubmit') }}"></use>
                            </svg>
                        </span>
                        <span class="bs-stepper-label">Revisar y enviar</span>
                    </button>
                </div>
            </div>
            <div class="bs-stepper-content">
                <form onSubmit="return false">
                    <!-- Datos básicos del negocio -->
                    <div id="account-details-modern" class="content">
                        <div class="content-header mb-3">
                            <h6 class="mb-0">Datos básicos del negocio</h6>
                            <small>Nombre, rubro, ubicación, logo y descripción de tu negocio.</small>
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label d-block" for="business-logo-modern">
                                    <i class="ti ti-photo ti-sm me-1 text-body"></i> Logo
                                </label>
                                <div class="d-flex align-items-start gap-3">
                                    <div class="rounded border bg-lighter p-2" style="width: 80px; height: 80px;">
                                        <img id="business-logo-preview-modern" src="" alt="" class="w-100 h-100 object-fit-contain d-none" />
                                        <div id="business-logo-placeholder-modern" class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                            <i class="ti ti-building-store ti-xl"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <input type="file" id="business-logo-modern" class="form-control" name="business_logo" accept="image/*" />
                                        <small class="text-muted">Recomendado: imagen cuadrada, PNG o JPG, máx. 2 MB</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="business-name-modern"><i class="ti ti-building-store ti-sm me-1 text-body"></i> Nombre del negocio (*)</label>
                                <input type="text" id="business-name-modern" class="form-control" name="business_name" placeholder="Nombre de tu empresa o marca" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="business-industry-modern"><i class="ti ti-category ti-sm me-1 text-body"></i> Rubro / Sector</label>
                                <input type="text" id="business-industry-modern" class="form-control" name="business_industry" placeholder="ej. Tecnología, Retail, Servicios" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="business-location-modern"><i class="ti ti-map-pin ti-sm me-1 text-body"></i> Ubicación / Dirección</label>
                                <input type="text" id="business-location-modern" class="form-control" name="business_location" placeholder="Calle, ciudad, región" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="business-postal-code-modern"><i class="ti ti-mailbox ti-sm me-1 text-body"></i> Código postal</label>
                                <input type="text" id="business-postal-code-modern" class="form-control" name="business_postal_code" placeholder="28001" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="business-phone-modern"><i class="ti ti-phone ti-sm me-1 text-body"></i> Teléfono</label>
                                <input type="tel" id="business-phone-modern" class="form-control" name="business_phone" placeholder="+34 600 000 000" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="business-whatsapp-modern"><i class="ti ti-brand-whatsapp ti-sm me-1 text-body"></i> WhatsApp</label>
                                <input type="tel" id="business-whatsapp-modern" class="form-control" name="business_whatsapp" placeholder="+34 600 000 000" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="business-website-modern"><i class="ti ti-world ti-sm me-1 text-body"></i> Página web</label>
                                <input type="url" id="business-website-modern" class="form-control" name="business_website" placeholder="https://www.ejemplo.com" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="business-email-modern"><i class="ti ti-mail ti-sm me-1 text-body"></i> Email</label>
                                <input type="email" id="business-email-modern" class="form-control" name="business_email" placeholder="contacto@ejemplo.com" />
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="business-tagline-modern"><i class="ti ti-quote ti-sm me-1 text-body"></i> Eslogan</label>
                                <input type="text" id="business-tagline-modern" class="form-control" name="business_tagline" placeholder="Frase corta que defina tu negocio" />
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="business-description-modern"><i class="ti ti-file-description ti-sm me-1 text-body"></i> Descripción</label>
                                <textarea id="business-description-modern" class="form-control" name="business_description" rows="4" placeholder="¿Qué hace tu negocio? ¿A quién va dirigido?"></textarea>
                            </div>
                            <div class="col-12 d-flex justify-content-between">
                                <button class="btn btn-label-secondary btn-prev" disabled> <i class="ti ti-arrow-left me-sm-1"></i>
                                    <span class="align-middle d-sm-inline-block d-none">Anterior</span>
                                </button>
                                <button class="btn btn-primary btn-next"> <span class="align-middle d-sm-inline-block d-none me-sm-1">Siguiente</span> <i class="ti ti-arrow-right"></i></button>
                            </div>
                        </div>
                    </div>
                    <!-- Información personal -->
                    <div id="personal-info-modern" class="content">
                        <div class="content-header mb-3">
                            <h6 class="mb-0">Información personal</h6>
                            <small>Introduce tu información personal. Fecha y hora de nacimiento se usan para obtener tu arquetipo humano.</small>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="first-name-modern">Nombre</label>
                                <input type="text" id="first-name-modern" class="form-control" name="first_name" placeholder="Nombre" />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="last-name-modern">Apellidos</label>
                                <input type="text" id="last-name-modern" class="form-control" name="last_name" placeholder="Apellidos" />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="birth-date-modern"><i class="ti ti-calendar-event ti-sm me-1 text-body"></i> Fecha de nacimiento</label>
                                <input type="date" id="birth-date-modern" class="form-control" name="birth_date" />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="birth-time-modern"><i class="ti ti-clock ti-sm me-1 text-body"></i> Hora de nacimiento</label>
                                <input type="time" id="birth-time-modern" class="form-control" name="birth_time" placeholder="HH:MM" />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="country-modern">País</label>
                                <select class="select2" id="country-modern" name="country">
                                    <option label=" "></option>
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
                                <label class="form-label" for="language-modern">Idioma</label>
                                <select class="selectpicker w-auto" id="language-modern" name="language[]" data-style="btn-transparent" data-icon-base="ti" data-tick-icon="ti-check text-white" multiple>
                                    <option>Español</option>
                                    <option>Inglés</option>
                                    <option>Francés</option>
                                </select>
                            </div>
                            <div class="col-12 d-flex justify-content-between">
                                <button class="btn btn-label-secondary btn-prev"> <i class="ti ti-arrow-left me-sm-1"></i>
                                    <span class="align-middle d-sm-inline-block d-none">Anterior</span>
                                </button>
                                <button class="btn btn-primary btn-next"> <span class="align-middle d-sm-inline-block d-none me-sm-1">Siguiente</span> <i class="ti ti-arrow-right"></i></button>
                            </div>
                        </div>
                    </div>
                    <!-- Dirección -->
                    <div id="address-modern" class="content">
                        <div class="content-header mb-3">
                            <h6 class="mb-0">Dirección</h6>
                            <small>Introduce tu dirección.</small>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="address-modern-input">Dirección</label>
                                <input type="text" class="form-control" id="address-modern-input" placeholder="Calle, número, piso">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="landmark-modern">Punto de referencia</label>
                                <input type="text" class="form-control" id="landmark-modern" placeholder="Cerca de...">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="pincode-modern">Código postal</label>
                                <input type="text" class="form-control" id="pincode-modern" placeholder="28001">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="city-modern">Ciudad</label>
                                <input type="text" class="form-control" id="city-modern" placeholder="Madrid">
                            </div>
                            <div class="col-12 d-flex justify-content-between">
                                <button class="btn btn-label-secondary btn-prev"> <i class="ti ti-arrow-left me-sm-1"></i>
                                    <span class="align-middle d-sm-inline-block d-none">Anterior</span>
                                </button>
                                <button class="btn btn-primary btn-next"> <span class="align-middle d-sm-inline-block d-none me-sm-1">Siguiente</span> <i class="ti ti-arrow-right"></i></button>
                            </div>
                        </div>
                    </div>
                    <!-- Redes sociales -->
                    <div id="social-links-modern" class="content">
                        <div class="content-header mb-3">
                            <h6 class="mb-0">Redes sociales</h6>
                            <small>Introduce los enlaces a tus redes sociales.</small>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="twitter-modern"><i class="ti ti-brand-x ti-sm me-1 text-body"></i> X (Twitter)</label>
                                <input type="text" id="twitter-modern" class="form-control" placeholder="https://x.com/..." />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="facebook-modern"><i class="ti ti-brand-facebook ti-sm me-1 text-primary"></i> Facebook</label>
                                <input type="text" id="facebook-modern" class="form-control" placeholder="https://facebook.com/..." />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="instagram-modern"><i class="ti ti-brand-instagram ti-sm me-1 text-danger"></i> Instagram</label>
                                <input type="text" id="instagram-modern" class="form-control" placeholder="https://instagram.com/..." />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="linkedin-modern"><i class="ti ti-brand-linkedin ti-sm me-1 text-primary"></i> LinkedIn</label>
                                <input type="text" id="linkedin-modern" class="form-control" placeholder="https://linkedin.com/in/..." />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="youtube-modern"><i class="ti ti-brand-youtube ti-sm me-1 text-danger"></i> YouTube</label>
                                <input type="text" id="youtube-modern" class="form-control" placeholder="https://youtube.com/..." />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="tiktok-modern"><i class="ti ti-brand-tiktok ti-sm me-1 text-body"></i> TikTok</label>
                                <input type="text" id="tiktok-modern" class="form-control" placeholder="https://tiktok.com/@" />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="whatsapp-modern"><i class="ti ti-brand-whatsapp ti-sm me-1 text-success"></i> WhatsApp</label>
                                <input type="text" id="whatsapp-modern" class="form-control" placeholder="https://wa.me/..." />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="telegram-modern"><i class="ti ti-brand-telegram ti-sm me-1 text-info"></i> Telegram</label>
                                <input type="text" id="telegram-modern" class="form-control" placeholder="https://t.me/..." />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="pinterest-modern"><i class="ti ti-brand-pinterest ti-sm me-1 text-danger"></i> Pinterest</label>
                                <input type="text" id="pinterest-modern" class="form-control" placeholder="https://pinterest.com/..." />
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="threads-modern"><i class="ti ti-brand-threads ti-sm me-1 text-body"></i> Threads</label>
                                <input type="text" id="threads-modern" class="form-control" placeholder="https://threads.net/@" />
                            </div>
                            <div class="col-12 d-flex justify-content-between">
                                <button class="btn btn-label-secondary btn-prev"> <i class="ti ti-arrow-left me-sm-1"></i>
                                    <span class="align-middle d-sm-inline-block d-none">Anterior</span>
                                </button>
                                <button class="btn btn-primary btn-next"> <span class="align-middle d-sm-inline-block d-none me-sm-1">Siguiente</span> <i class="ti ti-arrow-right"></i></button>
                            </div>
                        </div>
                    </div>
                    <!-- Revisar y enviar -->
                    <div id="review-submit-modern" class="content">
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
                            <li>Dirección</li>
                            <li>Punto de referencia</li>
                            <li>Código postal</li>
                            <li>Ciudad</li>
                        </ul>
                        <hr>
                        <p class="fw-medium mb-2">Redes sociales</p>
                        <ul class="list-unstyled mb-0">
                            <li><i class="ti ti-brand-x ti-sm me-1 text-body"></i> X (Twitter)</li>
                            <li><i class="ti ti-brand-facebook ti-sm me-1 text-primary"></i> Facebook</li>
                            <li><i class="ti ti-brand-instagram ti-sm me-1 text-danger"></i> Instagram</li>
                            <li><i class="ti ti-brand-linkedin ti-sm me-1 text-primary"></i> LinkedIn</li>
                            <li><i class="ti ti-brand-youtube ti-sm me-1 text-danger"></i> YouTube</li>
                            <li><i class="ti ti-brand-tiktok ti-sm me-1 text-body"></i> TikTok</li>
                            <li><i class="ti ti-brand-whatsapp ti-sm me-1 text-success"></i> WhatsApp</li>
                            <li><i class="ti ti-brand-telegram ti-sm me-1 text-info"></i> Telegram</li>
                            <li><i class="ti ti-brand-pinterest ti-sm me-1 text-danger"></i> Pinterest</li>
                            <li><i class="ti ti-brand-threads ti-sm me-1 text-body"></i> Threads</li>
                        </ul>
                        <div class="col-12 d-flex justify-content-between mt-3">
                            <button class="btn btn-label-secondary btn-prev"> <i class="ti ti-arrow-left me-sm-1"></i>
                                <span class="align-middle d-sm-inline-block d-none">Anterior</span>
                            </button>
                            <button class="btn btn-success btn-submit">Enviar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
