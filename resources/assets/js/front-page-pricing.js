/**
 * Pricing
 */

'use strict';

document.addEventListener('DOMContentLoaded', function (event) {
  (function () {
    const priceDurationToggler = document.querySelector('.price-duration-toggler'),
      priceMonthlyList = [].slice.call(document.querySelectorAll('.price-monthly')),
      priceYearlyList = [].slice.call(document.querySelectorAll('.price-yearly')),
      checkoutLinks = [].slice.call(document.querySelectorAll('[data-checkout-monthly]'));

    function togglePrice() {
      if (priceDurationToggler.checked) {
        // If checked
        priceYearlyList.map(function (yearEl) {
          yearEl.classList.remove('d-none');
        });
        priceMonthlyList.map(function (monthEl) {
          monthEl.classList.add('d-none');
        });
      } else {
        // If not checked
        priceYearlyList.map(function (yearEl) {
          yearEl.classList.add('d-none');
        });
        priceMonthlyList.map(function (monthEl) {
          monthEl.classList.remove('d-none');
        });
      }

      checkoutLinks.forEach(function (link) {
        var monthlyHref = link.getAttribute('data-checkout-monthly') || '';
        var yearlyHref = link.getAttribute('data-checkout-yearly') || monthlyHref;
        link.setAttribute('href', priceDurationToggler.checked ? yearlyHref : monthlyHref);
      });
    }
    // togglePrice Event Listener
    togglePrice();

    priceDurationToggler.onchange = function () {
      togglePrice();
    };
  })();
});
