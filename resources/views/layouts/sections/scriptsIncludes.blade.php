@php
$menuCollapsed = ($configData['menuCollapsed'] === 'layout-menu-collapsed') ? json_encode(true) : false;
@endphp
<!-- laravel style -->
<script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>

@if ($configData['hasCustomizer'])
  <!--! Template customizer: required for theme switching -->
  <script src="{{ asset('assets/vendor/js/template-customizer.js') }}"></script>
@endif

<!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
<script src="{{ asset('assets/js/config.js') }}"></script>

@if ($configData['hasCustomizer'])
<script>
  window.templateCustomizer = new TemplateCustomizer({
    cssPath: '',
    themesPath: '',
    defaultStyle: "{{$configData['styleOpt']}}",
    defaultShowDropdownOnHover: "{{$configData['showDropdownOnHover']}}", // true/false (for horizontal layout only)
    displayCustomizer: "{{$configData['displayCustomizer']}}",
    lang: '{{ app()->getLocale() }}',
    pathResolver: function(path) {
      var resolvedPaths = {
        // Core stylesheets
        @foreach (['core'] as $name)
          '{{ $name }}.css': '{{ asset("assets/vendor/css{$configData['rtlSupport']}/{$name}.css") }}',
          '{{ $name }}-dark.css': '{{ asset("assets/vendor/css{$configData['rtlSupport']}/{$name}-dark.css") }}',
        @endforeach

        // Themes
        @foreach (['default', 'bordered', 'semi-dark'] as $name)
          'theme-{{ $name }}.css': '{{ asset("assets/vendor/css{$configData['rtlSupport']}/theme-{$name}.css") }}',
          'theme-{{ $name }}-dark.css':
          '{{ asset("assets/vendor/css{$configData['rtlSupport']}/theme-{$name}-dark.css") }}',
        @endforeach
      }
      return resolvedPaths[path] || path;
    },
    'controls': <?php echo json_encode($configData['customizerControls']); ?>,
  });
</script>
@endif
