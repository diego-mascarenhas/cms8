<div
  class="slash-lead-modal slash-reward-modal"
  data-slash-reward-modal
  hidden
  aria-hidden="true"
>
  <div class="slash-lead-modal-backdrop" data-slash-reward-modal-close tabindex="-1"></div>
  <div
    class="slash-lead-modal-dialog slash-reward-modal-dialog"
    role="dialog"
    aria-modal="true"
    aria-labelledby="slash-reward-modal-title"
  >
    <button type="button" class="slash-lead-modal-close" data-slash-reward-modal-close aria-label="{{ __('slash_landing.lead.success_close') }}">×</button>
    <div class="slash-lead-reward slash-lead-reward-modal">
      <span class="slash-lead-reward-badge">{{ __('slash_landing.lead.success_badge') }}</span>
      <p class="slash-lead-reward-kicker">{{ __('slash_landing.lead.success_kicker') }}</p>
      <h2 id="slash-reward-modal-title" class="slash-lead-reward-title">{{ __('slash_landing.lead.success_title') }}</h2>
      <p class="slash-lead-reward-body">{{ __('slash_landing.lead.success_body') }}</p>
      <div class="slash-lead-reward-code">
        <span class="slash-lead-reward-code-label">{{ __('slash_landing.lead.success_code_label') }}</span>
        <code class="slash-lead-reward-code-value" data-slash-coupon-code>{{ config('humano_pricing.slash_lead_coupon_code') }}</code>
        <button
          type="button"
          class="slash-lead-reward-copy"
          data-slash-copy-coupon
          data-copied-label="{{ __('slash_landing.lead.success_copied') }}"
        >
          {{ __('slash_landing.lead.success_copy') }}
        </button>
      </div>
    </div>
    <div class="slash-lead-modal-actions">
      <a href="#precios" class="slash-btn slash-btn-accent" data-slash-reward-modal-go>{{ __('slash_landing.lead.success_cta') }}</a>
    </div>
  </div>
</div>
