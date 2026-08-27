<div class="card mb-4">
    <h5 class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span class="d-flex align-items-center">
            <i class="ti ti-message-chatbot me-2"></i>
            {{ __('team_settings.site_assistant.title') }}
        </span>
        <a href="{{ route('help.chat-assistant') }}#site-assistant-prompt" class="small">
            {{ __('team_settings.site_assistant.help_link') }}
        </a>
    </h5>
    <div class="card-body">
        <p class="text-muted small mb-4">
            {{ __('team_settings.site_assistant.intro') }}
        </p>

        <form method="post" action="{{ route('team-settings.chat.site-assistant-prompt', $team) }}" class="mb-4">
            @csrf
            <div class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label" for="site_assistant_prompt_key">{{ __('team_settings.site_assistant.select_label') }}</label>
                    <select name="prompt_key" id="site_assistant_prompt_key" class="form-select">
                        <option value="{{ \App\Services\TeamSiteAssistantPromptService::OFF_KEY }}" @selected(($siteAssistantSelectedKey ?? '') === \App\Services\TeamSiteAssistantPromptService::OFF_KEY)>
                            {{ __('team_settings.site_assistant.select_off') }}
                        </option>
                        <option value="{{ \App\Services\TeamSiteAssistantPromptService::FORCE_OFF_KEY }}" @selected(($siteAssistantSelectedKey ?? '') === \App\Services\TeamSiteAssistantPromptService::FORCE_OFF_KEY)>
                            {{ __('team_settings.site_assistant.select_off_all') }}
                        </option>
                        <option value="" @selected(($siteAssistantSelectedKey ?? '') === '')>{{ __('team_settings.site_assistant.select_empty') }}</option>
                        @foreach($siteAssistantPromptOptions as $option)
                            <option value="{{ $option['key'] }}" @selected(($siteAssistantSelectedKey ?? '') === $option['key'])>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">{{ __('team_settings.site_assistant.select_help') }}</div>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-check me-1"></i>{{ __('team_settings.site_assistant.save') }}
                    </button>
                </div>
            </div>
        </form>

        <details class="mb-4">
            <summary class="fw-medium mb-3" style="cursor: pointer;">{{ __('team_settings.site_assistant.create_toggle') }}</summary>
            <form method="post" action="{{ route('team-settings.chat.site-assistant-prompt.store', $team) }}">
                @csrf
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="site_assistant_section_label">{{ __('team_settings.site_assistant.create_label') }}</label>
                        <input type="text" class="form-control @error('section_label') is-invalid @enderror" id="site_assistant_section_label"
                            name="section_label" value="{{ old('section_label', __('team_settings.site_assistant.recommended_label')) }}" maxlength="255" required>
                        @error('section_label')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="site_assistant_prompt_instruction">{{ __('team_settings.site_assistant.create_instruction') }}</label>
                        <textarea class="form-control @error('prompt_instruction') is-invalid @enderror" id="site_assistant_prompt_instruction"
                            name="prompt_instruction" rows="12" required>{{ old('prompt_instruction', $siteAssistantDefaultInstruction) }}</textarea>
                        <div class="form-text">{{ __('team_settings.site_assistant.create_help') }}</div>
                        @error('prompt_instruction')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-label-primary">
                            <i class="ti ti-plus me-1"></i>{{ __('team_settings.site_assistant.create_submit') }}
                        </button>
                    </div>
                </div>
            </form>
        </details>

        <div>
            <h6 class="mb-2">{{ __('team_settings.site_assistant.embed_title') }}</h6>
            <p class="text-muted small mb-2">{{ __('team_settings.site_assistant.embed_help') }}</p>
            <textarea class="form-control font-monospace small mb-2" id="site-assistant-embed-snippet" rows="8" readonly>{{ $siteAssistantEmbedSnippet }}</textarea>
            <p class="small mb-0">
                <a href="{{ route('help.chat-assistant') }}#assistant-embed">{{ __('team_settings.site_assistant.embed_docs') }}</a>
            </p>
        </div>
    </div>
</div>
