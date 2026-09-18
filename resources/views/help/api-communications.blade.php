@extends('layouts/layoutHelpSimple')

@section('title', __('Communications API'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/prism/prism.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/prism/prism.js') }}"></script>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title mb-0">{{ __('Communications API Reference') }}</h4>
            </div>
            <div class="card-body">
                <p class="lead">{{ __('Queue a transactional email, WhatsApp or SMS with your team token, then fetch the same record to read its delivery status.') }}</p>

                <h5 class="mt-4">{{ __('Base URL') }}</h5>
                <code class="d-block p-3 bg-light">{{ url('/') }}/api/team/communications</code>
                <p class="text-muted mt-2">{{ __('This endpoint uses team token authentication. The team is determined by the Bearer token, not by a team_id parameter.') }}</p>

                <h5 class="mt-4">{{ __('Authentication') }}</h5>
                <p>{{ __('All requests require team token authentication. Include the token in the Authorization header:') }}</p>
                <pre><code class="language-http">Authorization: Bearer {{ $apiToken }}</code></pre>

                <div class="alert alert-info mt-3">
                    <i class="ti ti-info-circle me-2"></i>
                    <strong>{{ __('Note:') }}</strong> {{ __('The send is asynchronous. The create response returns status pending. Poll GET until status is sent or failed.') }}
                </div>

                <h5 class="mt-4">{{ __('Available Endpoints') }}</h5>

                <div class="card bg-light mb-4">
                    <div class="card-body">
                        <h6 class="mb-3">{{ __('Quick Navigation') }}</h6>
                        <ul class="list-unstyled mb-0">
                            <li><a href="#send-communication" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('Send a communication') }}</a></li>
                            <li><a href="#get-communication" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('Get a communication') }}</a></li>
                        </ul>
                    </div>
                </div>

                <div class="card mt-4" id="send-communication">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <span class="badge bg-success me-2">POST</span>
                            {{ __('Send a communication') }}
                            <a href="#send-communication" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                        </h6>
                    </div>
                    <div class="card-body">
                        <code class="d-block mb-3">{{ url('/') }}/api/team/communications</code>
                        <p>{{ __('Queue one message to one recipient. Email requires recipient_email and subject. WhatsApp and SMS require recipient_phone (10–15 digits). Attachments are email-only (max 5 files, 10 MB each).') }}</p>

                        <h6>{{ __('Request body') }}</h6>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('Field') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Required') }}</th>
                                    <th>{{ __('Description') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>channel</code></td>
                                    <td>string</td>
                                    <td>{{ __('Yes') }}</td>
                                    <td>{{ __('email, whatsapp or sms') }}</td>
                                </tr>
                                <tr>
                                    <td><code>message</code></td>
                                    <td>string</td>
                                    <td>{{ __('Yes') }}</td>
                                    <td>{{ __('Message body') }}</td>
                                </tr>
                                <tr>
                                    <td><code>recipient_email</code></td>
                                    <td>string</td>
                                    <td>{{ __('If email') }}</td>
                                    <td>{{ __('Recipient email address') }}</td>
                                </tr>
                                <tr>
                                    <td><code>subject</code></td>
                                    <td>string</td>
                                    <td>{{ __('If email') }}</td>
                                    <td>{{ __('Email subject (max 255)') }}</td>
                                </tr>
                                <tr>
                                    <td><code>recipient_phone</code></td>
                                    <td>string</td>
                                    <td>{{ __('If WhatsApp/SMS') }}</td>
                                    <td>{{ __('Phone number, 10–15 digits. Non-digits are stripped.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>recipient_name</code></td>
                                    <td>string</td>
                                    <td>{{ __('No') }}</td>
                                    <td>{{ __('Display name') }}</td>
                                </tr>
                                <tr>
                                    <td><code>contact_id</code></td>
                                    <td>integer</td>
                                    <td>{{ __('No') }}</td>
                                    <td>{{ __('Existing contact in the same team. Otherwise matched by email or phone.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>metadata</code></td>
                                    <td>object</td>
                                    <td>{{ __('No') }}</td>
                                    <td>{{ __('Arbitrary JSON. In multipart, send a JSON string.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>attachments</code></td>
                                    <td>file[]</td>
                                    <td>{{ __('No') }}</td>
                                    <td>{{ __('Email only. Field name attachments[]. WhatsApp and SMS return 422 if files are sent.') }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <h6 class="mt-3">{{ __('Example request (email)') }}</h6>
                        <pre class="docs-code"><code class="language-bash">curl -X POST "{{ url('/') }}/api/team/communications" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "channel": "email",
    "recipient_email": "ada@example.com",
    "recipient_name": "Ada",
    "subject": "Tu factura",
    "message": "Adjuntamos la factura del período.",
    "metadata": { "source": "erp", "external_id": "INV-1042" }
  }'</code></pre>

                        <h6 class="mt-3">{{ __('Example request (WhatsApp)') }}</h6>
                        <pre class="docs-code"><code class="language-bash">curl -X POST "{{ url('/') }}/api/team/communications" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "channel": "whatsapp",
    "recipient_phone": "+34 600 111 222",
    "message": "Hola, tu pedido ya salió."
  }'</code></pre>

                        <h6 class="mt-3">{{ __('Example request (SMS)') }}</h6>
                        <pre class="docs-code"><code class="language-bash">curl -X POST "{{ url('/') }}/api/team/communications" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "channel": "sms",
    "recipient_phone": "34600111222",
    "message": "Código 4821. Caduca en 10 minutos."
  }'</code></pre>

                        <h6 class="mt-3">{{ __('Example request (email with attachment)') }}</h6>
                        <pre class="docs-code"><code class="language-bash">curl -X POST "{{ url('/') }}/api/team/communications" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Accept: application/json" \
  -F "channel=email" \
  -F "recipient_email=ada@example.com" \
  -F "subject=Tu factura" \
  -F "message=Adjuntamos la factura del período." \
  -F "metadata={\"source\":\"erp\",\"external_id\":\"INV-1042\"}" \
  -F "attachments[]=@./factura.pdf"</code></pre>

                        <h6 class="mt-3">{{ __('Success response (201)') }}</h6>
                        <pre><code class="language-json">{
  "success": true,
  "message": "Communication queued successfully",
  "data": {
    "id": 1842,
    "channel": "email",
    "channel_label": "Email",
    "status": "pending",
    "status_label": "Pendiente",
    "recipient_email": "ada@example.com",
    "recipient_phone": null,
    "recipient_name": "Ada",
    "subject": "Tu factura",
    "message": "Adjuntamos la factura del período.",
    "error_message": null,
    "metadata": { "source": "erp", "external_id": "INV-1042" },
    "sent_at": null,
    "created_at": "2026-09-18T13:54:00+00:00",
    "contact": null,
    "attachments": []
  }
}</code></pre>

                        <h6 class="mt-3">{{ __('Error responses') }}</h6>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('HTTP') }}</th>
                                    <th>{{ __('When') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>401</code></td>
                                    <td>{{ __('Missing or invalid team API token') }}</td>
                                </tr>
                                <tr>
                                    <td><code>422</code></td>
                                    <td>{{ __('Validation error (channel, recipient, subject, phone or attachments)') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card mt-4" id="get-communication">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <span class="badge bg-primary me-2">GET</span>
                            {{ __('Get a communication') }}
                            <a href="#get-communication" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                        </h6>
                    </div>
                    <div class="card-body">
                        <code class="d-block mb-3">{{ url('/') }}/api/team/communications/{id}</code>
                        <p>{{ __('Returns the queued message, attachments and delivery status. Use data.status: pending, sent or failed. When failed, read data.error_message. When sent, data.sent_at is ISO-8601.') }}</p>

                        <h6>{{ __('Example request') }}</h6>
                        <pre class="docs-code"><code class="language-bash">curl "{{ url('/') }}/api/team/communications/1842" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Accept: application/json"</code></pre>

                        <h6 class="mt-3">{{ __('Success response (200)') }}</h6>
                        <pre><code class="language-json">{
  "success": true,
  "data": {
    "id": 1842,
    "channel": "email",
    "status": "sent",
    "status_label": "Enviado",
    "recipient_email": "ada@example.com",
    "subject": "Tu factura",
    "message": "Adjuntamos la factura del período.",
    "error_message": null,
    "sent_at": "2026-09-18T13:54:08+00:00",
    "created_at": "2026-09-18T13:54:00+00:00",
    "contact": {
      "id": 88,
      "name": "Ada",
      "email": "ada@example.com",
      "phone": null
    },
    "attachments": [
      {
        "id": 12,
        "file_name": "factura.pdf",
        "mime_type": "application/pdf",
        "size": 122880,
        "url": "{{ url('/') }}/storage/12/factura.pdf"
      }
    ]
  }
}</code></pre>

                        <h6 class="mt-3">{{ __('Status values') }}</h6>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Description') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>pending</code></td>
                                    <td>{{ __('Queued or retrying. sent_at is null.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>sent</code></td>
                                    <td>{{ __('Delivered to the provider. sent_at is set.') }}</td>
                                </tr>
                                <tr>
                                    <td><code>failed</code></td>
                                    <td>{{ __('Did not send. Reason in error_message. WhatsApp outside the 24h window fails without automatic retries.') }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <h6 class="mt-3">{{ __('Error responses') }}</h6>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('HTTP') }}</th>
                                    <th>{{ __('When') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>401</code></td>
                                    <td>{{ __('Missing or invalid team API token') }}</td>
                                </tr>
                                <tr>
                                    <td><code>404</code></td>
                                    <td>{{ __('Communication not found for this team') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a href="{{ route('help.api.authentication') }}" class="btn btn-secondary">
                        <i class="ti ti-key me-2"></i>
                        {{ __('Authentication') }}
                    </a>
                    <a href="{{ route('help.api') }}" class="btn btn-secondary">
                        <i class="ti ti-api me-2"></i>
                        {{ __('API Overview') }}
                    </a>
                    <a href="{{ route('help.api.whatsapp') }}" class="btn btn-success">
                        <i class="ti ti-brand-whatsapp me-2"></i>
                        {{ __('WhatsApp API') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
