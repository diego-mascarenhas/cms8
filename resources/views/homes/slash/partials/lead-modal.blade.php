<div
  class="slash-lead-modal"
  data-slash-lead-modal
  hidden
  aria-hidden="true"
>
  <div class="slash-lead-modal-backdrop" data-slash-lead-modal-close tabindex="-1"></div>
  <div
    class="slash-lead-modal-dialog"
    role="dialog"
    aria-modal="true"
    aria-labelledby="slash-lead-modal-title"
  >
    <button type="button" class="slash-lead-modal-close" data-slash-lead-modal-close aria-label="{{ __('slash_landing.lead.modal_close') }}">×</button>
    <p class="slash-lead-modal-kicker">{{ __('slash_landing.lead.modal_kicker') }}</p>
    <h2 id="slash-lead-modal-title" class="slash-lead-modal-title" data-slash-lead-modal-title></h2>
    <p class="slash-lead-modal-subtitle" data-slash-lead-modal-subtitle>{{ __('slash_landing.lead.modal_subtitle') }}</p>
    <p class="slash-lead-modal-email" data-slash-lead-modal-email hidden></p>
    <form
      class="slash-lead-modal-form"
      data-slash-lead-modal-form
      action="{{ route('slash.lead.store') }}"
      method="POST"
      novalidate
    >
      @csrf
      <input type="hidden" name="email" value="" data-slash-lead-email>
      <input type="hidden" name="source" value="cta" data-slash-lead-source>
      <label class="slash-lead-modal-field">
        <span>{{ __('slash_landing.lead.modal_name_label') }}</span>
        <input type="text" name="name" maxlength="255" autocomplete="name" placeholder="{{ __('slash_landing.lead.modal_name_placeholder') }}">
      </label>
      <label class="slash-lead-modal-field" data-slash-form-field>
        <span>{{ __('slash_landing.lead.modal_phone_label') }}</span>
        <input type="tel" name="phone" maxlength="20" autocomplete="tel" placeholder="{{ __('slash_landing.lead.modal_phone_placeholder') }}" data-slash-phone-input aria-describedby="slash-lead-feedback-modal">
      </label>
      <p class="slash-form-feedback slash-form-feedback-modal" id="slash-lead-feedback-modal" data-slash-form-feedback hidden role="alert"></p>
      <div class="slash-lead-modal-actions">
        <button type="submit" class="slash-btn slash-btn-accent" data-slash-lead-modal-submit>{{ __('slash_landing.lead.modal_submit_email_only') }}</button>
      </div>
    </form>
  </div>
</div>

<script type="application/json" id="slash-lead-config">
{!! json_encode([
    'titles' => trans('slash_landing.lead.modal_titles'),
    'submitEmailOnly' => __('slash_landing.lead.modal_submit_email_only'),
    'submitWithDetails' => __('slash_landing.lead.modal_submit_with_details'),
    'emailConfirmed' => __('slash_landing.lead.modal_email_confirmed'),
    'validation' => [
        'emailRequired' => __('slash_landing.lead.validation_client_email_required'),
        'emailInvalid' => __('slash_landing.lead.validation_client_email_invalid'),
        'phoneInvalid' => __('slash_landing.lead.validation_phone_invalid'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
</script>
