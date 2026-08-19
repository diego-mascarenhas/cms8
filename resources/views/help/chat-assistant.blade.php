@extends('layouts/layoutHelpSimple')

@section('title', __('Chat and Assistant'))

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
                <h4 class="card-title mb-0">{{ __('Chat and Assistant') }}</h4>
            </div>
            <div class="card-body">
                <div class="col-12">
                    <p class="lead">{{ __('In the Chat section you can talk to the assistant and, if configured, manage WhatsApp conversations.') }}</p>

                    <h6 class="mt-4">{{ __('Assistant view') }}</h6>
                    <p>{{ __('When you open the assistant chat (without selecting a specific client), the conversation is unified: the messages you see are the same as those in the terminal session.') }}</p>
                    <p>{{ __('For developers or advanced users: that session corresponds to the same context used by the Artisan command:') }}</p>
                    <pre class="language-bash"><code>php artisan chat:simulate</code></pre>
                    <p>{{ __('So you can interact with the assistant from the web interface and, if you run the command in the terminal, you will see the same conversation. The reverse is also true: messages sent from the terminal appear in the Chat assistant view after refreshing.') }}</p>

                    <h6 class="mt-4">{{ __('Chat with a client') }}</h6>
                    <p>{{ __('If you select a client or contact in the chat list, the conversation is specific to that recipient (e.g. WhatsApp). The assistant can suggest replies; you can enable or disable this with the robot toggle next to the message box.') }}</p>

                    <h6 class="mt-4" id="site-assistant-prompt">{{ __('Site prompt (appointments and sales)') }}</h6>
                    <p>{{ __('From the Assistant app (Configuración) or Team settings → Chat / Assistant you can select one team prompt or create a new one. That prompt is the default voice of the assistant when no funnel, sticky flow, or WhatsApp cart command owns the turn.') }}</p>
                    <p>{{ __('The recommended template is written for three jobs: book appointments, show the catalog, and help the customer buy. Funnels stay a separate path (aliases or an open session). WhatsApp commands such as comprar or finalizar still skip the prompt.') }}</p>
                    <ul>
                        <li>{{ __('Select an existing active prompt of the team, or create one with a name plus instructions.') }}</li>
                        <li>{{ __('Saving the prompt also creates or updates the web embed automation (slug: asistente-web) so the widget uses the same flow.') }}</li>
                        <li>{{ __('Path in the Assistant app:') }} <span class="text-muted">/settings</span></li>
                        <li>{{ __('Path in cms8:') }} <span class="text-muted">/team/{id}/settings/chat</span></li>
                    </ul>

                    <h6 class="mt-4" id="assistant-embed">{{ __('Embed the assistant on a client website') }}</h6>
                    <p>{{ __('Copy the cms8 snippet from Assistant settings or Chat settings and paste it before the closing body tag of the client site. The snippet is always available.') }}</p>
                    <pre class="language-html"><code>&lt;div data-cms8-widget="assistant"&gt;&lt;/div&gt;
&lt;script&gt;
  window.CMS8_WIDGETS_API_BASE = "{{ url('/api/embed/automation/PUBLIC_TOKEN') }}";
&lt;/script&gt;
&lt;script src="{{ url('/js/cms8-widgets.js') }}" async&gt;&lt;/script&gt;</code></pre>
                    <p>{{ __('Replace PUBLIC_TOKEN with the public token shown in settings (or on the Asistente web automation). The widget calls:') }}</p>
                    <ul>
                        <li><code>GET {{ url('/api/embed/automation/{token}') }}</code> — {{ __('name and welcome message') }}</li>
                        <li><code>POST {{ url('/api/embed/automation/{token}/assistant') }}</code> — {{ __('JSON body:') }} <code>{"message":"...","session_key":"..."}</code></li>
                    </ul>
                    <p class="mb-0">{{ __('Keep the API channel enabled on that automation. Regenerating the public token in the automation form invalidates the old snippet.') }}</p>

                    <h6 class="mt-4" id="assistant-flow-routing">{{ __('Team flow prompts and routing') }}</h6>
                    <p>{{ __('The cms8 Assistant can merge extra instructions from per-team prompts (module prompts). Each prompt has a module (optional), a stable section key, a section label, and the instruction text for the model.') }}</p>
                    <ul>
                        <li>{{ __('Routing key: if the prompt has a module, the key is «module_key:section_key» (e.g. «chat:onboarding»). Without a module, the routing key is just «section_key».') }}</li>
                        <li>{{ __('Manage prompts from the prompts list in the app (authenticated users).') }} <span class="text-muted">{{ __('Path: /prompt/list') }}</span></li>
                        <li>{{ __('For the exact «help» paragraphs appended to the assistant system prompt from configuration (web and WhatsApp), see') }} <a href="#assistant-help-hints">{{ __('Assistant help hints (reference)') }}</a>.</li>
                    </ul>

                    <h6 class="mt-4">{{ __('Chat sidebar: Settings (AJUSTES)') }}</h6>
                    <p>{{ __('Admins (admin / root) can change team-wide chat assistant options from the left sidebar in Chat.') }}</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr><th>{{ __('Toggle') }}</th><th>{{ __('Meaning') }}</th></tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ __('Humano Assistant replies / Assistant auto-respond') }}</td>
                                    <td>{{ __('Controls automatic assistant behaviour where that setting applies (e.g. inbound auto-replies).') }}</td>
                                </tr>
                                <tr>
                                    <td>{{ __('Predefined test responses / Stub') }}</td>
                                    <td>{{ __('When on, uses canned test replies instead of the real model (development only).') }}</td>
                                </tr>
                                <tr>
                                    <td>{{ __('Default assistant flow (AI discovery)') }}</td>
                                    <td>{{ __('When on, keyword routing to flows is off: the model picks flows using discovery and the commit-flow tool. Mutually exclusive with the keyword toggle below (same team setting, inverted in the UI).') }}</td>
                                </tr>
                                <tr>
                                    <td>{{ __('Keyword routing') }}</td>
                                    <td>{{ __('When on, attaches a team flow from the message using deterministic scoring (no extra LLM call for routing). See the next section.') }}</td>
                                </tr>
                                <tr>
                                    <td>{{ __('Block assistant AI button') }}</td>
                                    <td>{{ __('Team-level block for the assistant AI button in the chat UI.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h6 class="mt-4">{{ __('Keyword routing vs AI discovery') }}</h6>
                    <p><strong>{{ __('Keyword routing on') }}</strong> {{ __('— The system scores global «intents» from configuration (phrases/words such as catalog, billing, etc.) and scores each active team prompt using:') }}</p>
                    <ul>
                        <li>{{ __('The section key: hyphens/underscores are treated like spaces; a strong match is when the normalized phrase appears in the message, plus per-word matches with word boundaries.') }}</li>
                        <li>{{ __('The section label (additional signal): if the label is at least 12 characters after normalizing punctuation, the same scoring runs on the label text. Use the label for natural phrases users might type, while keeping the section key stable for APIs and routing keys.') }}</li>
                    </ul>
                    <p>{{ __('The best global intent score and the best team-prompt score are compared; whichever side has the higher score wins. If both sides tie, the global intent wins. This is still literal text matching, not semantic «intent» from an embedding or a second model.') }}</p>
                    <p><strong>{{ __('Keyword routing off (default flow / AI discovery)') }}</strong> {{ __('— No automatic attachment from keywords. The model sees available routing keys and can commit a flow when appropriate. Better for paraphrasing and vague user messages, at the cost of the model sometimes skipping a flow.') }}</p>

                    <h6 class="mt-4" id="assistant-help-hints">{{ __('Assistant help hints (reference)') }}</h6>
                    <p>{{ __('These paragraphs are loaded from configuration and appended to the assistant system instructions when tools are enabled (web and WhatsApp). This page is the public reference for that text (same source as production).') }}</p>
                    <p class="text-muted small mb-2">{{ __('Config file:') }} <code>config/humano_interactive_guide.php</code></p>
                    <h6 class="mt-3 small text-uppercase text-muted">{{ __('Web assistant (web_help_hint)') }}</h6>
                    <pre class="language-txt"><code>{{ config('humano_interactive_guide.web_help_hint') }}</code></pre>
                    <h6 class="mt-3 small text-uppercase text-muted">{{ __('Inbound WhatsApp (whatsapp_help_hint)') }}</h6>
                    <pre class="language-txt"><code>{{ config('humano_interactive_guide.whatsapp_help_hint') }}</code></pre>
                    <p class="mt-2 mb-0 small text-muted">{{ __('The terminal tour uses a different block (instructions); run:') }} <code>php artisan humano:interactive-guide</code>. {{ __('Config key:') }} <code>humano_interactive_guide.instructions</code></p>

                    <h6 class="mt-4" id="admin-proactive-whatsapp">{{ __('Admin: proactive WhatsApp (demo / onboarding)') }}</h6>
                    <p>{{ __('Users with roles admin or root can send a forced-flow WhatsApp opening in three ways: slash command in the web assistant (own thread), slash command on the team WhatsApp number, or Artisan on the server.') }}</p>
                    <h6 class="mt-4">{{ __('Slash commands (chat or WhatsApp)') }}</h6>
                    <ul>
                        <li><code>/enviar-demo +34…</code> {{ __('or') }} <code>/send-demo +34…</code> — {{ __('keyword «demo» + destination number.') }}</li>
                        <li><code>/enviar-onboarding +34…</code> {{ __('or') }} <code>/send-onboarding +34…</code> — {{ __('keyword «onboarding» + destination number; active Chat prompt section_key onboarding.') }}</li>
                        <li><code>/system-onboarding +34…</code> {{ __('or') }} <code>/send-system-onboarding +34…</code> — {{ __('fixed Humano reseller onboarding (text + static screenshots), independent of team prompts.') }}</li>
                        <li><code>/enviar-flujo cobrar +34…</code> {{ __('or') }} <code>/send-flow cobrar +34…</code> — {{ __('another active team prompt + number (spaces in the number are allowed).') }}</li>
                        <li>{{ __('In chat: only in your own assistant thread (no client selected). On WhatsApp: the sender must be a user linked to that phone with admin/root; runs even if inbound assistant is off for that contact.') }}</li>
                    </ul>
                    <h6 class="mt-4">{{ __('Artisan (server)') }}</h6>
                    <pre class="language-bash"><code>php artisan humano:send-demo "+34600111222" --team=YOUR_TEAM_ID
php artisan humano:send-demo "+34600111222" --team=YOUR_TEAM_ID --keyword=onboarding
php artisan humano:system-onboarding "+34600111222" --team=YOUR_TEAM_ID</code></pre>
                    <ul>
                        <li>{{ __('Optional: --user=USER_ID (defaults to the team owner). The user must belong to the team and have admin or root.') }}</li>
                        <li>{{ __('Optional: --keyword=cobrar or --keyword=onboarding (must match an active team prompt: section key, routing key, or label).') }}</li>
                        <li>{{ __('Requires WhatsApp configured for the team and the same assistant flow engine as inbound auto-replies.') }}</li>
                        <li>{{ __('Local demo prompts: run «php artisan db:seed --class=ChatAssistantProactiveDemoPromptsSeeder» (after ModuleSeeder) for sample flows.') }}</li>
                    </ul>

                    <h6 class="mt-4">{{ __('Sticky flow and reset') }}</h6>
                    <p>{{ __('When a tool flow is active, the routing key may stick across messages until the user clearly changes topic. Substrings configured under assistant_tool_intent_prompts (e.g. «cambiar de tema», «salir del embudo», «empezar de nuevo») clear the sticky key and also abandon an open funnel session, then re-evaluate routing for that message.') }}</p>

                    <h6 class="mt-4">{{ __('Environment') }}</h6>
                    <p>{{ __('Global intent routing can be disabled with ASSISTANT_TOOL_INTENT_PROMPTS=false. Minimum match score can be tuned with ASSISTANT_TOOL_INTENT_PROMPTS_MIN_SCORE.') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
