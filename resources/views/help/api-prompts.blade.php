@extends('layouts/layoutHelpSimple')

@section('title', __('Prompts API'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/prism/prism.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/prism/prism.js') }}"></script>
@endsection

@section('content')
<div class="row">
    <!-- Main Content - Full Width since sidebar is in layout -->
    <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">{{ __('Prompts API Reference') }}</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12">
                            <p class="lead">{{ __('Complete API reference for using AI prompts programmatically.') }}</p>

                            <h5 class="mt-4">{{ __('Base URL') }}</h5>
                            <code class="d-block p-3 bg-light">{{ url('/') }}/api/team/prompts</code>

                            <h5 class="mt-4">{{ __('Authentication') }}</h5>
                            <p>{{ __('All requests require team token authentication. Include the token in the Authorization header:') }}</p>
                            <pre><code class="language-http">Authorization: Bearer {{ $apiToken }}</code></pre>

                            <div class="alert alert-info mt-3">
                                <i class="ti ti-info-circle me-2"></i>
                                <strong>{{ __('Note:') }}</strong> {{ __('Prompts API uses Team Token authentication, not personal Bearer tokens. Ensure you are using a valid team token.') }}
                            </div>

                            <h5 class="mt-4">{{ __('Available Endpoints') }}</h5>

                            <!-- Quick Navigation -->
                            <div class="card bg-light mb-4">
                                <div class="card-body">
                                    <h6 class="mb-3">{{ __('Quick Navigation') }}</h6>
                                    <ul class="list-unstyled mb-0">
                                        <li><a href="#list-prompts" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('List Available Prompts') }}</a></li>
                                        <li><a href="#invoke-prompt" class="text-decoration-none"><i class="ti ti-chevron-right ti-xs"></i> {{ __('Invoke Prompt') }}</a></li>
                                    </ul>
                                </div>
                            </div>

                            <!-- List Prompts -->
                            <div class="card mt-4" id="list-prompts">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-primary me-2">GET</span>
                                        {{ __('List Available Prompts') }}
                                        <a href="#list-prompts" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/team/prompts</code>
                                    <p>{{ __('Retrieve a list of all AI prompts available for your team based on enabled modules.') }}</p>

                                    <h6>{{ __('Response Example') }}</h6>
                                    <pre><code class="language-json">{
  "prompts": [
    {
      "id": 1,
      "section_key": "contact_suggestions",
      "section_label": "Sugerencias de Contacto",
      "module_name": "Contactos"
    },
    {
      "id": 2,
      "section_key": "task_description",
      "section_label": "Descripción de Tarea",
      "module_name": "Tareas"
    },
    {
      "id": 3,
      "section_key": "email_composer",
      "section_label": "Redacción de Email",
      "module_name": "Mensajería"
    }
  ]
}</code></pre>

                                    <h6 class="mt-3">{{ __('Response Fields') }}</h6>
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Field') }}</th>
                                                <th>{{ __('Type') }}</th>
                                                <th>{{ __('Description') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><code>id</code></td>
                                                <td>integer</td>
                                                <td>{{ __('Prompt ID (use for invoking)') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>section_key</code></td>
                                                <td>string</td>
                                                <td>{{ __('Unique identifier for the prompt section') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>section_label</code></td>
                                                <td>string</td>
                                                <td>{{ __('Human-readable name of the prompt') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>module_name</code></td>
                                                <td>string</td>
                                                <td>{{ __('Module this prompt belongs to') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Invoke Prompt -->
                            <div class="card mt-4" id="invoke-prompt">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <span class="badge bg-success me-2">POST</span>
                                        {{ __('Invoke Prompt') }}
                                        <a href="#invoke-prompt" class="text-muted ms-2" title="{{ __('Anchor link') }}"><i class="ti ti-link ti-xs"></i></a>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <code class="d-block mb-3">{{ url('/') }}/api/team/prompt</code>
                                    <p>{{ __('Execute an AI prompt with a user message and receive a generated response.') }}</p>

                                    <h6>{{ __('Request Body') }}</h6>
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
                                                <td><code>prompt_id</code></td>
                                                <td>integer</td>
                                                <td><span class="badge bg-warning">Conditional</span></td>
                                                <td>{{ __('ID from /prompts list. Required if prompt_name not provided') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>prompt_name</code></td>
                                                <td>string</td>
                                                <td><span class="badge bg-warning">Conditional</span></td>
                                                <td>{{ __('File-based prompt name. Required if prompt_id not provided') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>test_message</code></td>
                                                <td>string</td>
                                                <td><span class="badge bg-danger">Yes</span></td>
                                                <td>{{ __('User message/input to process with the prompt') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <div class="alert alert-warning">
                                        <i class="ti ti-alert-triangle me-2"></i>
                                        <strong>{{ __('Important:') }}</strong> {{ __('You must provide either prompt_id OR prompt_name, not both.') }}
                                    </div>

                                    <h6>{{ __('Request Example (Using Prompt ID)') }}</h6>
                                    <pre><code class="language-json">{
  "prompt_id": 1,
  "test_message": "Necesito ideas para seguimiento de cliente potencial interesado en desarrollo web"
}</code></pre>

                                    <h6>{{ __('Request Example (Using Prompt Name)') }}</h6>
                                    <pre><code class="language-json">{
  "prompt_name": "landing_suggestions",
  "test_message": "¿Cómo puedo mejorar la conversión de mi landing page?"
}</code></pre>

                                    <h6>{{ __('Response Example (Success)') }}</h6>
                                    <pre><code class="language-json">{
  "success": true,
  "suggestion": "Aquí tienes algunas ideas para el seguimiento:\n\n1. Envía un email personalizado dentro de las 24 horas...\n2. Programa una llamada de seguimiento...\n3. Comparte casos de éxito relevantes...",
  "prompt_id": 1,
  "module": "Contactos"
}</code></pre>

                                    <h6 class="mt-3">{{ __('Response Fields') }}</h6>
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Field') }}</th>
                                                <th>{{ __('Type') }}</th>
                                                <th>{{ __('Description') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><code>success</code></td>
                                                <td>boolean</td>
                                                <td>{{ __('Whether the request was successful') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>suggestion</code></td>
                                                <td>string</td>
                                                <td>{{ __('AI-generated response text') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>prompt_id</code></td>
                                                <td>integer</td>
                                                <td>{{ __('ID of the prompt that was executed') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>module</code></td>
                                                <td>string</td>
                                                <td>{{ __('Module name the prompt belongs to') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <h6 class="mt-3">{{ __('Error Response Example') }}</h6>
                                    <pre><code class="language-json">{
  "success": false,
  "message": "Prompt no encontrado o no disponible para tu equipo"
}</code></pre>
                                </div>
                            </div>

                            <!-- Use Cases -->
                            <div class="card mt-4 bg-light">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">{{ __('Common Use Cases') }}</h6>
                                </div>
                                <div class="card-body">
                                    <h6>{{ __('1. Contact Follow-up Suggestions') }}</h6>
                                    <p>{{ __('Generate personalized follow-up ideas for contacts based on their context.') }}</p>

                                    <h6 class="mt-3">{{ __('2. Email Composition') }}</h6>
                                    <p>{{ __('Draft professional emails for various scenarios (proposals, follow-ups, introductions).') }}</p>

                                    <h6 class="mt-3">{{ __('3. Task Descriptions') }}</h6>
                                    <p>{{ __('Expand brief task titles into detailed, actionable descriptions.') }}</p>

                                    <h6 class="mt-3">{{ __('4. Content Ideas') }}</h6>
                                    <p>{{ __('Generate ideas for blog posts, social media, or marketing content.') }}</p>

                                    <h6 class="mt-3">{{ __('5. Project Planning') }}</h6>
                                    <p>{{ __('Get suggestions for project milestones, tasks breakdown, or resource allocation.') }}</p>
                                </div>
                            </div>

                            <!-- Rate Limits & Best Practices -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">{{ __('Rate Limits & Best Practices') }}</h6>
                                </div>
                                <div class="card-body">
                                    <h6>{{ __('Rate Limits') }}</h6>
                                    <p>{{ __('AI prompt invocations may be subject to rate limits depending on your team plan:') }}</p>
                                    <ul>
                                        <li>{{ __('Basic Plan: Up to 100 prompt calls per day') }}</li>
                                        <li>{{ __('Professional Plan: Up to 500 prompt calls per day') }}</li>
                                        <li>{{ __('Enterprise Plan: Unlimited prompt calls') }}</li>
                                    </ul>

                                    <h6 class="mt-3">{{ __('Best Practices') }}</h6>
                                    <ul>
                                        <li>{{ __('Provide clear and specific context in your test_message') }}</li>
                                        <li>{{ __('Use appropriate prompts for each module/context') }}</li>
                                        <li>{{ __('Cache responses when possible to avoid duplicate calls') }}</li>
                                        <li>{{ __('Handle errors gracefully and provide fallback options to users') }}</li>
                                        <li>{{ __('Monitor your API usage to stay within rate limits') }}</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Error Codes -->
                            <div class="card mt-4 bg-light">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">{{ __('Error Codes') }}</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Code') }}</th>
                                                <th>{{ __('Description') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><code>400</code></td>
                                                <td>{{ __('Bad Request - Missing required parameters (test_message, prompt_id/prompt_name)') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>401</code></td>
                                                <td>{{ __('Unauthorized - Invalid or missing team token') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>403</code></td>
                                                <td>{{ __('Forbidden - Prompt not available for your team or module not enabled') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>404</code></td>
                                                <td>{{ __('Not Found - Prompt ID or name does not exist') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>429</code></td>
                                                <td>{{ __('Too Many Requests - Rate limit exceeded') }}</td>
                                            </tr>
                                            <tr>
                                                <td><code>500</code></td>
                                                <td>{{ __('Server Error - AI service unavailable or processing error') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
    </div>
</div>
@endsection
