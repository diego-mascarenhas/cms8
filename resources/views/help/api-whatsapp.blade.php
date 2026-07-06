@extends('layouts/layoutHelpSimple')

@section('title', __('WhatsApp API'))

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
                <h4 class="card-title mb-0">{{ __('WhatsApp API Reference') }}</h4>
            </div>
            <div class="card-body">
                <p class="lead">{{ __('Send outbound WhatsApp text messages programmatically using your team token. Messages are sent with the WhatsApp number configured for your team (local Baileys service or Twilio).') }}</p>

                <h5 class="mt-4">{{ __('Base URL') }}</h5>
                <code class="d-block p-3 bg-light">{{ url('/') }}/api/team/whatsapp/send</code>
                <p class="text-muted mt-2">{{ __('This endpoint uses team token authentication. The team is determined by the Bearer token, not by a team_id parameter.') }}</p>

                <h5 class="mt-4">{{ __('Authentication') }}</h5>
                <p>{{ __('Generate a token in Team Settings → API Tokens, then include it in every request:') }}</p>
                <pre><code class="language-http">Authorization: Bearer {{ $apiToken }}</code></pre>

                <div class="alert alert-info mt-3">
                    <i class="ti ti-info-circle me-2"></i>
                    <strong>{{ __('Note:') }}</strong> {{ __('WhatsApp must be connected for your team (QR linked in Chat) when using the local driver. With Twilio, ensure team WhatsApp settings are configured.') }}
                </div>

                <h5 class="mt-4" id="send-message">{{ __('Send a text message') }}</h5>
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <span class="badge bg-success me-2">POST</span>
                            {{ __('Send WhatsApp message') }}
                        </h6>
                    </div>
                    <div class="card-body">
                        <code class="d-block mb-3">{{ url('/') }}/api/team/whatsapp/send</code>

                        <h6>{{ __('Request body (JSON)') }}</h6>
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
                                    <td><code>to</code></td>
                                    <td>string</td>
                                    <td>{{ __('Yes') }}</td>
                                    <td>{{ __('Recipient phone number (E.164 or digits only, 10–15 digits)') }}</td>
                                </tr>
                                <tr>
                                    <td><code>message</code></td>
                                    <td>string</td>
                                    <td>{{ __('Yes') }}</td>
                                    <td>{{ __('Text message body (max 4096 characters)') }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <h6 class="mt-3">{{ __('Example request') }}</h6>
                        <pre class="docs-code"><code class="language-bash">curl -X POST "{{ url('/') }}/api/team/whatsapp/send" \
  -H "Authorization: Bearer {{ $apiToken }}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "to": "+34722372858",
    "message": "Mensaje de prueba desde la API de Humano"
  }'</code></pre>

                        <h6 class="mt-3">{{ __('Success response (200)') }}</h6>
                        <pre><code class="language-json">{
  "success": true,
  "message": "WhatsApp message sent",
  "to": "34722372858"
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
                                    <td>{{ __('Validation error (missing fields or invalid phone number)') }}</td>
                                </tr>
                                <tr>
                                    <td><code>503</code></td>
                                    <td>{{ __('WhatsApp not connected (local driver — scan QR in Chat)') }}</td>
                                </tr>
                                <tr>
                                    <td><code>500</code></td>
                                    <td>{{ __('Send failed (provider misconfiguration, service unreachable, etc.)') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <h5 class="mt-4">{{ __('Related endpoints') }}</h5>
                <p class="text-muted">{{ __('For interactive chat from a mobile app with user login (Sanctum), see also:') }}</p>
                <ul>
                    <li><code>POST /api/chat/whatsapp-send</code> — {{ __('Send message (user token, supports audio and attachments)') }}</li>
                    <li><code>GET /api/chat/whatsapp-list</code> — {{ __('List WhatsApp conversations') }}</li>
                    <li><code>GET /api/chat/whatsapp-messages/{phone}</code> — {{ __('Thread messages for a contact') }}</li>
                </ul>

                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a href="{{ route('help.api.authentication') }}" class="btn btn-secondary">
                        <i class="ti ti-key me-2"></i>
                        {{ __('Authentication') }}
                    </a>
                    <a href="{{ route('help.api') }}" class="btn btn-secondary">
                        <i class="ti ti-api me-2"></i>
                        {{ __('API Overview') }}
                    </a>
                    <a href="{{ route('help.chat-assistant') }}" class="btn btn-info">
                        <i class="ti ti-message-chatbot me-2"></i>
                        {{ __('Chat & Assistant') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
