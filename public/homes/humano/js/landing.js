/**
 * Humano public landing (/inicio) — guides carousel, hero entrance, theme images.
 */
'use strict';

(function () {
  function applyHumanoHeroImages(style) {
    let resolved = style;
    if (resolved === 'system') {
      resolved = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    const attr = resolved === 'dark' ? 'data-humano-dark-img' : 'data-humano-light-img';
    document.querySelectorAll('.humano-landing-hero-img[' + attr + ']').forEach(function (imageEl) {
      const src = imageEl.getAttribute(attr);
      if (src) {
        imageEl.src = src;
      }
    });
  }

  applyHumanoHeroImages(document.documentElement.getAttribute('data-style') || 'light');

  if (window.Helpers && typeof window.Helpers.on === 'function') {
    window.Helpers.on('templateCustomizer:changed', function (data) {
      if (data && data.style) {
        applyHumanoHeroImages(data.style);
      }
    });
  }

  const swiperReviews = document.getElementById('swiper-reviews');
  const reviewsPreviousBtn = document.getElementById('reviews-previous-btn');
  const reviewsNextBtn = document.getElementById('reviews-next-btn');
  const reviewsSliderPrev = document.querySelector('#swiper-reviews ~ .swiper-button-prev, .swiper-reviews-carousel .swiper-button-prev');
  const reviewsSliderNext = document.querySelector('#swiper-reviews ~ .swiper-button-next, .swiper-reviews-carousel .swiper-button-next');

  if (swiperReviews && typeof Swiper !== 'undefined') {
    new Swiper(swiperReviews, {
      slidesPerView: 1,
      spaceBetween: 5,
      grabCursor: true,
      autoplay: {
        delay: 3000,
        disableOnInteraction: false,
      },
      loop: false,
      rewind: true,
      navigation: {
        nextEl: '.swiper-reviews-carousel .swiper-button-next',
        prevEl: '.swiper-reviews-carousel .swiper-button-prev',
      },
      breakpoints: {
        1200: {
          slidesPerView: 3,
          spaceBetween: 26,
        },
        992: {
          slidesPerView: 2,
          spaceBetween: 20,
        },
      },
    });
  }

  if (reviewsNextBtn && reviewsSliderNext) {
    reviewsNextBtn.addEventListener('click', function () {
      reviewsSliderNext.click();
    });
  }

  if (reviewsPreviousBtn && reviewsSliderPrev) {
    reviewsPreviousBtn.addEventListener('click', function () {
      reviewsSliderPrev.click();
    });
  }

  var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (prefersReducedMotion || typeof gsap === 'undefined') {
    document.documentElement.classList.add('humano-motion-off');
    return;
  }

  gsap.registerPlugin(ScrollTrigger);

  var lenis = null;

  if (typeof Lenis !== 'undefined') {
    lenis = new Lenis({
      duration: 1.15,
      easing: function (t) {
        return Math.min(1, 1.001 - Math.pow(2, -10 * t));
      },
      smoothWheel: true,
      touchMultiplier: 1.8,
    });

    lenis.on('scroll', ScrollTrigger.update);

    gsap.ticker.add(function (time) {
      lenis.raf(time * 1000);
    });
    gsap.ticker.lagSmoothing(0);

    document.querySelectorAll('#humanoFrontNavCollapse a[href^="#"], .humano-front-topnav a[href^="#"]').forEach(function (anchor) {
      anchor.addEventListener('click', function (event) {
        var targetId = anchor.getAttribute('href');

        if (!targetId || targetId === '#') {
          return;
        }

        var target = document.querySelector(targetId);

        if (!target) {
          return;
        }

        event.preventDefault();
        lenis.scrollTo(target, { offset: -88, duration: 1.4 });
      });
    });
  }

  function revealElements(selector, options) {
    var elements = gsap.utils.toArray(selector);

    if (!elements.length) {
      return;
    }

    var stagger = (options && options.stagger) || 0.1;
    var y = (options && options.y) || 40;
    var start = (options && options.start) || 'top 88%';

    gsap.set(elements, { opacity: 0, y: y });

    ScrollTrigger.batch(elements, {
      start: start,
      onEnter: function (batch) {
        gsap.to(batch, {
          opacity: 1,
          y: 0,
          duration: 0.85,
          stagger: stagger,
          ease: 'power3.out',
          overwrite: true,
        });
      },
      onLeaveBack: function (batch) {
        gsap.to(batch, {
          opacity: 0,
          y: y,
          duration: 0.4,
          stagger: 0.05,
          overwrite: true,
        });
      },
    });
  }

  revealElements('.landing-features .features-icon-box', { y: 44, stagger: 0.08 });
  revealElements('.landing-plans-stack .landing-plan-row', { y: 48, stagger: 0.12 });
  revealElements('#landingManuals .card', { y: 36, stagger: 0.1 });
  revealElements('#landingFAQ .accordion-item', { y: 32, stagger: 0.08 });
  revealElements('#landingCTA .col-lg-6', { y: 40, stagger: 0.12 });
  revealElements('#landingContact .card', { y: 36 });

  var heroSection = document.getElementById('hero-animation');
  var heroTextBox = heroSection ? heroSection.querySelector('.hero-text-box') : null;
  var heroDashboard = heroSection ? heroSection.querySelector('.hero-dashboard-img') : null;

  if (heroSection && heroDashboard) {
    gsap.set(heroTextBox, { opacity: 0, y: 36 });
    gsap.set(heroDashboard, { opacity: 0, y: 36 });

    gsap.timeline({ defaults: { ease: 'power3.out' } })
      .to(heroTextBox, { opacity: 1, y: 0, duration: 0.65 })
      .to(heroDashboard, { opacity: 1, y: 0, duration: 0.85, ease: 'power2.out' }, '-=0.35');
  }

  var frontNav = document.querySelector('.humano-front-navbar');

  if (frontNav) {
    ScrollTrigger.create({
      start: 'top -80',
      onUpdate: function (self) {
        frontNav.classList.toggle('is-scrolled', self.scroll() > 40);
      },
    });
  }
})();
