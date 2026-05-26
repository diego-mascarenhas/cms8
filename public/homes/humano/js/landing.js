/**
 * Humano public landing (/inicio) — guides carousel, hero parallax, theme images.
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

  const nav = document.querySelector('.layout-navbar');
  const heroAnimation = document.getElementById('hero-animation');
  const animationImg = document.querySelectorAll('.hero-dashboard-img');
  const animationElements = document.querySelectorAll('.hero-elements-img');
  const swiperReviews = document.getElementById('swiper-reviews');
  const reviewsPreviousBtn = document.getElementById('reviews-previous-btn');
  const reviewsNextBtn = document.getElementById('reviews-next-btn');
  const reviewsSliderPrev = document.querySelector('#swiper-reviews ~ .swiper-button-prev, .swiper-reviews-carousel .swiper-button-prev');
  const reviewsSliderNext = document.querySelector('#swiper-reviews ~ .swiper-button-next, .swiper-reviews-carousel .swiper-button-next');

  const mediaQueryXL = '1200';
  if (screen.width >= mediaQueryXL && heroAnimation && nav) {
    heroAnimation.addEventListener('mousemove', function parallax(e) {
      animationElements.forEach(layer => {
        layer.style.transform = 'translateZ(1rem)';
      });
      animationImg.forEach(layer => {
        const x = (window.innerWidth - e.pageX * 2) / 100;
        const y = (window.innerHeight - e.pageY * 2) / 100;
        layer.style.transform = `perspective(1200px) rotateX(${y}deg) rotateY(${x}deg) scale3d(1, 1, 1)`;
      });
    });

    nav.addEventListener('mousemove', function parallax(e) {
      animationElements.forEach(layer => {
        layer.style.transform = 'translateZ(1rem)';
      });
      animationImg.forEach(layer => {
        const x = (window.innerWidth - e.pageX * 2) / 100;
        const y = (window.innerHeight - e.pageY * 2) / 100;
        layer.style.transform = `perspective(1200px) rotateX(${y}deg) rotateY(${x}deg) scale3d(1, 1, 1)`;
      });
    });

    heroAnimation.addEventListener('mouseout', function () {
      animationElements.forEach(layer => {
        layer.style.transform = 'translateZ(0)';
      });
      animationImg.forEach(layer => {
        layer.style.transform = 'perspective(1200px) scale(1) rotateX(0) rotateY(0)';
      });
    });
  }

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
})();
