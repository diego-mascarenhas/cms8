{{--
    Select2 helper: show "Add …" when search has no match; POST to categories.quick-store.
    Expects: $selectId (string), $moduleKey (string|null), $multiple (bool)
--}}
<script>
    (function () {
        const selectId = @json($selectId);
        const moduleKey = @json($moduleKey ?? '');
        const isMultiple = @json($multiple);
        const select = $('#' + selectId);
        if (!select.length || !$.fn.select2) {
            return;
        }

        const quickStoreUrl = @json(route('categories.quick-store'));
        const csrfToken = @json(csrf_token());
        const btnId = 'quick-add-module-cat-' + selectId;
        const labelAdd = @json(__('Add'));
        const labelCreating = @json(__('Creating...'));
        const labelMinChars = @json(__('Please enter at least 2 characters to create a category.'));
        const labelError = @json(__('Could not create the category.'));
        let multiDropdownSearchBound = false;
        let multiKeyboardRedirectBound = false;

        function getCurrentSearchTerm(select2Data) {
            const dropdownSearch = select2Data?.dropdown?.$search ? String(select2Data.dropdown.$search.val() || '').trim() : '';
            const dropdownField = select2Data?.$dropdown?.find('.select2-search__field').length
                ? String(select2Data.$dropdown.find('.select2-search__field').first().val() || '').trim()
                : '';
            const containerSearch = select2Data?.$container?.find('.select2-search__field').length
                ? String(select2Data.$container.find('.select2-search__field').first().val() || '').trim()
                : '';
            const openContainerSearch = $('.select2-container--open .select2-search__field').length
                ? String($('.select2-container--open .select2-search__field').first().val() || '').trim()
                : '';
            return {
                value: dropdownSearch || dropdownField || containerSearch || openContainerSearch,
                fromDropdown: dropdownSearch.length > 0,
                fromDropdownField: dropdownField.length > 0,
                fromContainer: containerSearch.length > 0,
                fromOpenContainer: openContainerSearch.length > 0,
            };
        }

        function escapeHtml(text) {
            return $('<div>').text(text).html();
        }

        function buildQuickAddRow(term) {
            const $btn = $('<button type="button" class="btn btn-sm btn-primary w-100"></button>')
                .attr('id', btnId)
                .attr('data-quick-term', term)
                .html('<i class="ti ti-plus me-1"></i>' + labelAdd + ' "' + escapeHtml(term) + '"');
            return $('<li class="select2-results__option" role="option" style="padding: 0;"></li>')
                .append($('<div class="p-2"></div>').append($btn));
        }

        function ensureMultipleDropdownSearch(select2Data) {
            if (!isMultiple || !select2Data || !select2Data.$dropdown) {
                return;
            }

            const $results = select2Data.$dropdown.find('.select2-results__options');
            if (!$results.length) {
                return;
            }

            let $searchRow = select2Data.$dropdown.find('.module-cat-dropdown-search-row');
            if (!$searchRow.length) {
                $searchRow = $('<li class="module-cat-dropdown-search-row" style="list-style:none;padding:12px 20px 8px;"></li>')
                    .append('<input type="text" class="form-control module-cat-dropdown-search" placeholder="" autocomplete="off">');
                $results.before($searchRow);
            }

            const $customInput = $searchRow.find('.module-cat-dropdown-search');
            const $inlineInput = select2Data.$container.find('.select2-search__field').first();
            const inlineValue = String($inlineInput.val() || '');
            $customInput.val(inlineValue);
            $customInput.css({
                borderColor: '#d9dee3',
                boxShadow: 'none',
                outline: 'none'
            });

            // Force the real typing surface to be the custom dropdown input,
            // and keep Select2 inline search hidden/offscreen to avoid layout drift.
            if ($inlineInput.length) {
                $inlineInput.css({
                    position: 'absolute',
                    left: '-9999px',
                    width: '1px',
                    height: '1px',
                    opacity: '0',
                    pointerEvents: 'none'
                });
                $inlineInput.prop('readonly', true);
                $inlineInput.attr('tabindex', '-1');
            }
            const customInputNode = $customInput.get(0);
            const containerNode = select2Data.$container.get(0);
            const customRect = customInputNode ? customInputNode.getBoundingClientRect() : null;
            const containerRect = containerNode ? containerNode.getBoundingClientRect() : null;
            const customStyles = customInputNode ? window.getComputedStyle(customInputNode) : null;

            if (!multiDropdownSearchBound) {
                $customInput.off('input.moduleCatDropdownSearch keyup.moduleCatDropdownSearch');
                $customInput.on('input.moduleCatDropdownSearch keyup.moduleCatDropdownSearch', function () {
                    const value = String($(this).val() || '');
                    const s2 = select.data('select2');
                    if (s2) {
                        s2.trigger('query', { term: value });
                    }
                    $inlineInput.val('');
                });
                multiDropdownSearchBound = true;
            }

            if (!multiKeyboardRedirectBound) {
                const $selectionContainer = select2Data.$container.find('.select2-selection--multiple');
                $selectionContainer.off('keydown.moduleCatRedirect');
                $selectionContainer.on('keydown.moduleCatRedirect', function (event) {
                    if (!isMultiple) {
                        return;
                    }
                    const isChar = event.key && event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey;
                    const isBackspace = event.key === 'Backspace';
                    if (!isChar && !isBackspace) {
                        return;
                    }

                    event.preventDefault();
                    const current = String($customInput.val() || '');
                    const next = isBackspace ? current.slice(0, -1) : current + event.key;
                    $customInput.val(next).trigger('input');
                    $customInput.trigger('focus');
                });
                multiKeyboardRedirectBound = true;
            }

            $customInput.trigger('focus');
        }

        function checkAndAddButton() {
            try {
                const select2Data = select.data('select2');
                if (!select2Data || !select2Data.$dropdown || !select2Data.$dropdown.is(':visible')) {
                    return;
                }

                const dropdown = select2Data.$dropdown;
                dropdown.find('.select2-results__message').css('display', 'none');
                const results = dropdown.find('.select2-results__options');
                if (!results.length) {
                    return;
                }

                const searchInfo = getCurrentSearchTerm(select2Data);
                const searchTerm = searchInfo.value;
                const minLen = 2;

                if (searchTerm.length < minLen) {
                    results.find('#' + btnId).closest('li').remove();
                    return;
                }

                let hasMatchingOption = false;
                const searchLower = searchTerm.toLowerCase();
                select.find('option').each(function () {
                    if ($(this).text().toLowerCase().includes(searchLower)) {
                        hasMatchingOption = true;
                        return false;
                    }
                });

                if (hasMatchingOption && !isMultiple) {
                    results.find('#' + btnId).closest('li').remove();
                    return;
                }

                const $existingBtn = results.find('#' + btnId);
                if ($existingBtn.length && $existingBtn.attr('data-quick-term') === searchTerm) {
                    return;
                }
                if ($existingBtn.length) {
                    $existingBtn.closest('li').remove();
                }

                let noResultsMsg = results.find('.select2-results__message');
                if (!noResultsMsg.length) {
                    results.find('li.select2-results__option').each(function () {
                        const text = $(this).text().trim().toLowerCase();
                        if (text.includes('no results') ||
                            text.includes('no se encontraron') ||
                            text.includes('sin resultados')) {
                            noResultsMsg = $(this);
                            return false;
                        }
                    });
                }
                if (noResultsMsg.length) {
                    noResultsMsg.css('display', 'none');
                }

                const visibleRealOptions = results.find('li.select2-results__option:visible')
                    .not('.select2-results__message')
                    .not('.select2-results__group')
                    .filter(function () {
                        return $(this).find('#' + btnId).length === 0;
                    });

                if (isMultiple && searchTerm.length === 0) {
                    visibleRealOptions.hide();
                } else {
                    results.find('li.select2-results__option').show();
                }

                if (noResultsMsg.length) {
                    noResultsMsg.replaceWith(buildQuickAddRow(searchTerm));
                } else if (!visibleRealOptions.length && !noResultsMsg.length) {
                    results.append(buildQuickAddRow(searchTerm));
                } else if (isMultiple && !results.find('#' + btnId).length) {
                    results.append(buildQuickAddRow(searchTerm));
                }
            } catch (e) {
                /* ignore */
            }
        }

        select.on('select2:open', function () {
            setTimeout(function () {
                const select2Data = select.data('select2');
                if (!select2Data) {
                    return;
                }

                ensureMultipleDropdownSearch(select2Data);
                checkAndAddButton();

                const searchInput = select2Data.dropdown.$search;
                if (searchInput) {
                    searchInput.off('input.moduleCatQuick keyup.moduleCatQuick');
                    searchInput.on('input.moduleCatQuick keyup.moduleCatQuick', function () {
                        [10, 50, 100, 200, 400].forEach(function (ms) {
                            setTimeout(checkAndAddButton, ms);
                        });
                    });
                }
                const openSearchInput = $('.select2-container--open .select2-search__field');
                if (openSearchInput.length) {
                    openSearchInput.off('input.moduleCatQuickOpen keyup.moduleCatQuickOpen');
                    openSearchInput.on('input.moduleCatQuickOpen keyup.moduleCatQuickOpen', function () {
                        [10, 50, 100, 200, 400].forEach(function (ms) {
                            setTimeout(checkAndAddButton, ms);
                        });
                    });
                }
            }, 100);
        });

        select.on('select2:close', function () {
            const searchInput = select.data('select2')?.dropdown?.$search;
            if (searchInput) {
                searchInput.off('input.moduleCatQuick keyup.moduleCatQuick');
            }
            multiDropdownSearchBound = false;
            multiKeyboardRedirectBound = false;
            const openSearchInput = $('.select2-container--open .select2-search__field');
            if (openSearchInput.length) {
                openSearchInput.off('input.moduleCatQuickOpen keyup.moduleCatQuickOpen');
            }
            const select2Data = select.data('select2');
            if (select2Data?.$container) {
                select2Data.$container.find('.select2-selection--multiple').off('keydown.moduleCatRedirect');
            }
        });

        $(document).on('click', '#' + btnId, function (e) {
            e.preventDefault();
            e.stopPropagation();

            const select2Data = select.data('select2');
            if (!select2Data) {
                return;
            }

            const searchInfo = getCurrentSearchTerm(select2Data);
            const searchTerm = searchInfo.value;
            if (searchTerm.length < 2) {
                alert(labelMinChars);
                return;
            }

            const btn = $(this);
            const originalHtml = btn.html();
            btn.prop('disabled', true).html('<i class="ti ti-loader me-1"></i>' + labelCreating);

            $.ajax({
                url: quickStoreUrl,
                method: 'POST',
                data: {
                    name: searchTerm,
                    module_key: moduleKey || null,
                    _token: csrfToken
                },
                success: function (response) {
                    if (!response.success || !response.category) {
                        btn.prop('disabled', false).html(originalHtml);
                        return;
                    }
                    if (isMultiple) {
                        const newOption = new Option(response.category.name, response.category.id, false, true);
                        select.append(newOption).trigger('change');
                    } else {
                        const newOption = new Option(response.category.name, response.category.id, true, true);
                        select.append(newOption);
                        select.val(String(response.category.id)).trigger('change');
                    }
                    select.select2('close');
                },
                error: function (xhr) {
                    let message = labelError;
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    } else if (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.name) {
                        message = xhr.responseJSON.errors.name[0];
                    }
                    alert(message);
                    btn.prop('disabled', false).html(originalHtml);
                }
            });
        });
    })();
</script>
