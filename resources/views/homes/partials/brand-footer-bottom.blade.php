<div class="slash-footer-bottom">
  <a
    href="https://www.idoneo.dev"
    class="slash-footer-idoneo"
    target="_blank"
    rel="noopener noreferrer"
    aria-label="{{ __('slash_landing.footer.idoneo_link_title') }}"
  >
    <span class="slash-footer-idoneo-word" aria-hidden="true">
      @foreach (mb_str_split(__('slash_landing.footer.idoneo_tooltip')) as $index => $letter)
        <span class="slash-footer-idoneo-letter" style="--letter-index: {{ $index }}">{{ $letter }}</span>
      @endforeach
    </span>
    <span class="slash-footer-idoneo-bolt" aria-hidden="true">
      <svg viewBox="0 0 24 32" xmlns="http://www.w3.org/2000/svg" focusable="false">
        <path d="M13.2 0 4.5 17.2h6.8L7.8 32 21 13.4h-7.5L13.2 0Z" fill="currentColor"/>
      </svg>
    </span>
    <img src="{{ asset('assets/logo-idoneo-iso.svg') }}" alt="">
  </a>
  <span class="slash-footer-copy">© {{ date('Y') }} {{ __('slash_landing.footer.brand_name') }} <span class="slash-footer-copy-sep" aria-hidden="true">|</span> {{ __('slash_landing.footer.copyright') }}</span>
  <a
    href="https://revisionalpha.com"
    class="slash-footer-powered"
    target="_blank"
    rel="noopener noreferrer"
    aria-label="{{ __('slash_landing.footer.revision_alpha_link_title') }}"
  >
    <span class="slash-footer-powered-by">{{ __('slash_landing.footer.powered_by') }}</span>
    <img
      src="{{ asset('assets/logo-revision-alpha.svg') }}"
      alt="{{ __('slash_landing.footer.revision_alpha_logo_alt') }}"
      width="120"
      height="20"
      loading="lazy"
      decoding="async"
    >
  </a>
</div>
