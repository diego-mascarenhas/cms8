@props(['id', 'label', 'selected' => null, 'allowNull' => true])

<div class="form-group">
    <div class="d-flex align-items-center mb-2" style="height: 1.375rem;">
        <label for="{{ $id }}" class="form-label mb-0">{{ $label }}</label>
    </div>
    <select
        id="{{ $id }}"
        name="{{ $id }}"
        class="select2 form-select select2-client-enterprise @error($id) is-invalid @enderror"
        data-placeholder="{{ __('Select') }} {{ $label }}"
        data-allow-clear="{{ $allowNull ? 'true' : 'false' }}"
        @if(! $allowNull) required @endif
    >
        @if($allowNull)
            <option value="">{{ __('Select') }} {{ $label }}</option>
        @endif

        @foreach($options as $option)
            <option
                value="{{ $option['id'] }}"
                data-keywords="{{ $option['keywords'] }}"
                data-type="{{ $option['type'] ?? '' }}"
                data-responsible="{{ $option['responsible'] ?? '' }}"
                data-contacts='@json($option['contacts'])'
                {{ (string) $selected === (string) $option['id'] ? 'selected' : '' }}
            >
                {{ $option['name'] }}
            </option>
        @endforeach
    </select>

    @error($id)
        <span class="invalid-feedback" role="alert">
            <strong>{{ $message }}</strong>
        </span>
    @enderror
</div>

@push('scripts')
<script>
    $(function () {
        const $select = $('#{{ $id }}');
        if (! $select.length || ! $.fn.select2) {
            return;
        }

        function foldAccent(value) {
            return String(value || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase();
        }

        function parseContacts($el) {
            var raw = $el.attr('data-contacts');
            if (! raw) {
                return [];
            }

            try {
                var parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                return [];
            }
        }

        function resolveSecondaryLabel($el, searchTerm) {
            var contacts = parseContacts($el);
            var term = foldAccent(searchTerm || '');

            if (term !== '' && contacts.length) {
                for (var i = 0; i < contacts.length; i++) {
                    var contactLabel = contacts[i] && contacts[i].label ? String(contacts[i].label) : '';
                    if (contactLabel && foldAccent(contactLabel).indexOf(term) > -1) {
                        return contactLabel;
                    }
                }
            }

            if (contacts.length && contacts[0].label) {
                return String(contacts[0].label);
            }

            var responsible = $el.data('responsible');
            return responsible ? String(responsible) : '';
        }

        function currentSearchTerm() {
            return $.trim(String($('.select2-container--open .select2-search__field').val() || ''));
        }

        function formatEnterpriseOption(data, isSelection) {
            if (! data.id) {
                return data.text;
            }

            var $el = $(data.element);
            var type = $el.data('type');
            var secondary = isSelection ? '' : resolveSecondaryLabel($el, currentSearchTerm());
            var $root = $('<span class="d-block"></span>');
            var $title = $('<span></span>').append(document.createTextNode(data.text));

            if (type) {
                $title.append($('<span class="text-muted ms-1"></span>').text('· ' + type));
            }

            $root.append($title);

            if (secondary) {
                $root.append(
                    $('<span class="d-block text-muted small"></span>').text(secondary)
                );
            }

            return $root;
        }

        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }

        $select.select2({
            placeholder: $select.data('placeholder') || '',
            allowClear: String($select.data('allow-clear')) === 'true',
            width: '100%',
            dropdownParent: $(document.body),
            templateResult: function (data) {
                return formatEnterpriseOption(data, false);
            },
            templateSelection: function (data) {
                return formatEnterpriseOption(data, true);
            },
            matcher: function (params, data) {
                if ($.trim(params.term) === '') {
                    return data;
                }

                if (typeof data.text === 'undefined') {
                    return null;
                }

                var term = foldAccent(params.term);
                var text = foldAccent(data.text);
                var keywords = foldAccent($(data.element).data('keywords') || '');

                if (text.indexOf(term) > -1 || keywords.indexOf(term) > -1) {
                    return data;
                }

                return null;
            }
        });
    });
</script>
@endpush
