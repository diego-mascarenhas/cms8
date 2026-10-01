@once
    @push('modals')
        <script>
            jQuery(function () {
                jQuery('select.js-filter-select').each(function () {
                    var $select = jQuery(this);

                    if ($select.data('select2')) {
                        return;
                    }

                    $select.select2({
                        minimumResultsForSearch: Infinity,
                        width: '100%',
                        dropdownParent: $select.parent(),
                        dropdownAutoWidth: true,
                    });
                });
            });
        </script>
    @endpush
@endonce
