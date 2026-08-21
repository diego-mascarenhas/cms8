@php
    $offKey = \App\Services\TeamSiteAssistantPromptService::OFF_KEY;
    $selectedKey = $siteAssistantSelectedKey ?? '';
    $catalogItems = collect($siteAssistantCatalog ?? [])->flatMap(fn (array $group) => $group['items'] ?? []);
    $teamOptions = $siteAssistantPromptOptions ?? [];
    $sidebarReadOnly = !($canManageChatTeamSidebarSettings ?? false);
@endphp
<div class="mt-3 pe-3">
    <label class="form-label small mb-1" for="sidebar-team-prompt-key">{{ __('team_settings.site_assistant.select_label') }}</label>
    <select
        id="sidebar-team-prompt-key"
        class="form-select form-select-sm"
        @disabled($sidebarReadOnly)
        data-save-url="{{ route('chat.team-site-assistant-prompt') }}">
        <optgroup label="{{ __('team_settings.site_assistant.select_group_start') }}">
            <option value="{{ $offKey }}" @selected($selectedKey === $offKey)>{{ __('team_settings.site_assistant.select_off') }}</option>
            <option value="" @selected($selectedKey === '')>{{ __('team_settings.site_assistant.select_empty') }}</option>
        </optgroup>
        @if($catalogItems->isNotEmpty())
            <optgroup label="{{ __('team_settings.site_assistant.select_group_catalog') }}">
                @foreach($catalogItems as $item)
                    <option value="catalog:{{ $item['key'] }}">{{ $item['label'] }}</option>
                @endforeach
            </optgroup>
        @endif
        @if($teamOptions !== [])
            <optgroup label="{{ __('team_settings.site_assistant.select_group_team') }}">
                @foreach($teamOptions as $option)
                    @php
                        $sectionKey = str_contains($option['key'], ':') ? explode(':', $option['key'], 2)[1] : $option['key'];
                        $catalogItem = $catalogItems->first(fn (array $item) => $item['key'] === $option['key'] || ($item['section_key'] ?? '') === $sectionKey);
                        $modified = is_array($catalogItem) && ($catalogItem['drifted'] ?? false);
                    @endphp
                    <option value="{{ $option['key'] }}" @selected($selectedKey === $option['key'])>
                        {{ $modified ? $option['section_label'].' (Modificado)' : $option['section_label'] }}
                    </option>
                @endforeach
            </optgroup>
        @endif
    </select>
    <div class="form-text">{{ __('team_settings.site_assistant.select_help') }}</div>
</div>
