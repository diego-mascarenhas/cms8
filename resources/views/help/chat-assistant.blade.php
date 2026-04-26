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

                    <h6 class="mt-4" id="assistant-flow-routing">{{ __('Team flow prompts and routing') }}</h6>
                    <p>{{ __('The Humano Assistant can merge extra instructions from per-team prompts (module prompts). Each prompt has a module (optional), a stable section key, a section label, and the instruction text for the model.') }}</p>
                    <ul>
                        <li>{{ __('Routing key: if the prompt has a module, the key is «module_key:section_key» (e.g. «chat:onboarding»). Without a module, the routing key is just «section_key».') }}</li>
                        <li>{{ __('Manage prompts from the prompts list in the app (authenticated users).') }} <span class="text-muted">{{ __('Path: /prompt/list') }}</span></li>
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

                    <h6 class="mt-4" id="admin-proactive-whatsapp">{{ __('Admin: proactive WhatsApp from the assistant chat') }}</h6>
                    <p>{{ __('In your own assistant thread (no client selected), users with roles admin or root can start an outbound WhatsApp conversation tied to a team flow by typing a keyword and a phone number in one line.') }}</p>
                    <ul>
                        <li>{{ __('Examples: «demo +34722372858», «cobrar +34 722 372 111», «reunion: +34 (722) 372-858», «mi flujo (34) 722 372 858». Spaces, hyphens and parentheses in the number are allowed; the system keeps only digits for sending.') }}</li>
                        <li>{{ __('The keyword must match an active team prompt: section key, full routing key (module:key), suffix after «:», or the section label (normalized).') }}</li>
                        <li>{{ __('The assistant runs with that flow forced and either calls send_whatsapp_message or the system sends the generated opening text if the model did not call the tool.') }}</li>
                        <li>{{ __('Requires WhatsApp configured for the team. Same flow engine as inbound WhatsApp auto-replies, but initiated from the web assistant.') }}</li>
                        <li>{{ __('Local demo data: run «php artisan db:seed --class=ChatAssistantProactiveDemoPromptsSeeder» (after ModuleSeeder) to create sample flows «demo», «cobrar», «reunion», «registar», «mi-flujo-demo» and a long-label flow; it also turns keyword routing on per team.') }}</li>
                    </ul>

                    <h6 class="mt-4">{{ __('Sticky flow and reset') }}</h6>
                    <p>{{ __('When a tool flow is active, the routing key may stick across messages until the user clearly changes topic. Substrings configured under assistant_tool_intent_prompts (e.g. «cambiar de tema») clear the sticky key and re-evaluate routing for that message.') }}</p>

                    <h6 class="mt-4">{{ __('Environment') }}</h6>
                    <p>{{ __('Global intent routing can be disabled with ASSISTANT_TOOL_INTENT_PROMPTS=false. Minimum match score can be tuned with ASSISTANT_TOOL_INTENT_PROMPTS_MIN_SCORE.') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
