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
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
