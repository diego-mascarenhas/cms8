/**
 * Humano — slash.com inspired landing (/slash).
 * Self-contained: nav, FAQ, Lenis smooth scroll, GSAP + ScrollTrigger motion.
 * Vendor deps live in ../vendor/ (portable with the /homes/slash folder).
 */
'use strict';

(function () {
  var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ── Nav + FAQ (always active) ── */

  var navToggle = document.querySelector('[data-slash-nav-toggle]');
  var navLinks = document.getElementById('slashNavLinks');

  if (navToggle && navLinks) {
    navToggle.addEventListener('click', function () {
      var open = navLinks.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    navLinks.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        navLinks.classList.remove('is-open');
        navToggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  /* ── Plan stories (expand cards + shared footer panel) ── */

  var storiesStage = document.querySelector('[data-slash-stories]');

  if (storiesStage) {
    var storyCards = storiesStage.querySelectorAll('[data-slash-story-card]');

    function activateStory(planId) {
      storyCards.forEach(function (card) {
        var isActive = card.getAttribute('data-slash-story-card') === planId;
        card.classList.toggle('is-active', isActive);
      });
    }

    storyCards.forEach(function (card) {
      var planId = card.getAttribute('data-slash-story-card');

      card.addEventListener('mouseenter', function () {
        activateStory(planId);
      });

      card.addEventListener('focusin', function () {
        activateStory(planId);
      });
    });

    storiesStage.addEventListener('mouseleave', function () {
      var defaultCard = storiesStage.querySelector('.slash-story-card.is-default');

      if (defaultCard) {
        activateStory(defaultCard.getAttribute('data-slash-story-card'));
      }
    });
  }

  document.querySelectorAll('[data-youtube-id]').forEach(function (wrap) {
    var videoId = (wrap.getAttribute('data-youtube-id') || '').trim();

    if (!videoId) {
      return;
    }

    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting || wrap.querySelector('iframe')) {
            return;
          }

          var iframe = document.createElement('iframe');
          var title = wrap.getAttribute('data-video-title') || 'Video del plan';
          iframe.src =
            'https://www.youtube.com/embed/' +
            videoId +
            '?autoplay=1&mute=1&loop=1&playlist=' +
            videoId +
            '&controls=0&playsinline=1&rel=0&modestbranding=1';
          iframe.title = title;
          iframe.allow = 'autoplay; encrypted-media; picture-in-picture';
          iframe.loading = 'lazy';
          wrap.appendChild(iframe);
        });
      },
      { threshold: 0.35 }
    );

    observer.observe(wrap);
  });

  document.querySelectorAll('[data-slash-faq]').forEach(function (item) {
    var question = item.querySelector('.slash-faq-q');
    var answer = item.querySelector('.slash-faq-a');

    if (!question || !answer) {
      return;
    }

    question.addEventListener('click', function () {
      var isOpen = item.classList.toggle('is-open');
      question.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      answer.style.maxHeight = isOpen ? answer.scrollHeight + 'px' : '';
    });
  });

  if (prefersReducedMotion || typeof gsap === 'undefined') {
    document.documentElement.classList.add('slash-motion-off');
    return;
  }

  gsap.registerPlugin(ScrollTrigger);

  /* ── Lenis smooth scroll ── */

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

    document.querySelectorAll('.slash-nav-links a[href^="#"]').forEach(function (anchor) {
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
        lenis.scrollTo(target, { offset: -80, duration: 1.4 });
      });
    });
  }

  /* ── Hero entrance ── */

  var heroSection = document.querySelector('.slash-hero');

  if (heroSection) {
    var heroEyebrow = heroSection.querySelector('.slash-eyebrow');
    var heroTitle = heroSection.querySelector('h1');
    var heroLead = heroSection.querySelector('.slash-lead');
    var heroForm = heroSection.querySelector('.slash-hero-form');
    var heroNote = heroSection.querySelector('.slash-hero-note');
    var heroShot = heroSection.querySelector('.slash-hero-shot');

    gsap.set([heroEyebrow, heroTitle, heroLead, heroForm, heroNote, heroShot].filter(Boolean), {
      opacity: 0,
      y: 36,
    });

    if (heroShot) {
      gsap.set(heroShot, { scale: 0.94, transformOrigin: '50% 100%' });
    }

    gsap.timeline({ defaults: { ease: 'power3.out' } })
      .to(heroEyebrow, { opacity: 1, y: 0, duration: 0.55 })
      .to(heroTitle, { opacity: 1, y: 0, duration: 0.75 }, '-=0.35')
      .to(heroLead, { opacity: 1, y: 0, duration: 0.6 }, '-=0.45')
      .to(heroForm, { opacity: 1, y: 0, duration: 0.55 }, '-=0.4')
      .to(heroNote, { opacity: 1, y: 0, duration: 0.45 }, '-=0.35')
      .to(heroShot, { opacity: 1, y: 0, scale: 1, duration: 1.1, ease: 'power2.out' }, '-=0.25');

    if (heroShot) {
      gsap.to(heroShot, {
        y: -48,
        scale: 1.03,
        ease: 'none',
        scrollTrigger: {
          trigger: heroSection,
          start: 'top top',
          end: 'bottom top',
          scrub: 1.2,
        },
      });
    }
  }

  /* ── Scroll reveals ── */

  function revealElements(selector, options) {
    var elements = gsap.utils.toArray(selector);

    if (!elements.length) {
      return;
    }

    var stagger = (options && options.stagger) || 0.1;
    var y = (options && options.y) || 48;
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

  revealElements('.slash-section-head', { y: 40, stagger: 0.12 });
  revealElements('.slash-trust-card', { y: 56, stagger: 0.14 });
  revealElements('.slash-story-card', { y: 48, stagger: 0.12 });
  revealElements('.slash-cap-card', { y: 40, stagger: 0.12 });
  revealElements('.slash-card', { y: 44, stagger: 0.08 });
  revealElements('.slash-quote', { y: 48, stagger: 0.14 });
  revealElements('.slash-tool-card', { y: 36, stagger: 0.08 });
  revealElements('.slash-security-card', { y: 40, stagger: 0.1 });
  revealElements('.slash-plan-row .slash-plan-copy', { y: 48, stagger: 0.1 });
  revealElements('.slash-plan-row .slash-plan-visual', { y: 64, stagger: 0.1 });
  revealElements('.slash-pricing-card', { y: 52, stagger: 0.16 });
  revealElements('.slash-faq-item', { y: 32, stagger: 0.08 });
  revealElements('.slash-cta-card', { y: 56 });
  revealElements('.slash-contact-card', { y: 40, stagger: 0.14 });
  revealElements('.slash-statband .slash-container', { y: 28, start: 'top 92%' });

  /* ── Metric counters ── */

  document.querySelectorAll('[data-slash-counter]').forEach(function (el) {
    var endValue = parseFloat(el.getAttribute('data-slash-counter'));
    var suffix = el.getAttribute('data-slash-counter-suffix') || '';
    var decimals = parseInt(el.getAttribute('data-slash-counter-decimals') || '0', 10);

    if (isNaN(endValue)) {
      return;
    }

    var counter = { value: 0 };

    gsap.to(counter, {
      value: endValue,
      duration: 2,
      ease: 'power2.out',
      scrollTrigger: {
        trigger: el.closest('.slash-metrics') || el,
        start: 'top 85%',
        once: true,
      },
      onUpdate: function () {
        el.textContent = counter.value.toFixed(decimals) + suffix;
      },
    });
  });

  /* Non-numeric metrics: pulse on reveal */
  gsap.utils.toArray('[data-slash-metric-pulse]').forEach(function (metric) {
    gsap.fromTo(
      metric,
      { opacity: 0, scale: 0.92 },
      {
        opacity: 1,
        scale: 1,
        duration: 0.7,
        ease: 'back.out(1.4)',
        scrollTrigger: {
          trigger: metric,
          start: 'top 88%',
          toggleActions: 'play none none reverse',
        },
      }
    );
  });

  gsap.utils.toArray('.slash-metric strong[data-slash-counter]').forEach(function (el) {
    var metric = el.closest('.slash-metric');

    if (!metric) {
      return;
    }

    gsap.fromTo(
      metric,
      { opacity: 0, y: 24 },
      {
        opacity: 1,
        y: 0,
        duration: 0.65,
        ease: 'power3.out',
        scrollTrigger: {
          trigger: metric,
          start: 'top 88%',
          toggleActions: 'play none none reverse',
        },
      }
    );
  });

  /* ── Nav background on scroll ── */

  var nav = document.querySelector('.slash-nav');

  if (nav) {
    ScrollTrigger.create({
      start: 'top -80',
      onUpdate: function (self) {
        nav.classList.toggle('is-scrolled', self.scroll() > 40);
      },
    });
  }

  /* ── Subtle button hover lift (accent CTAs) ── */

  document.querySelectorAll('.slash-btn-accent').forEach(function (btn) {
    btn.addEventListener('mouseenter', function () {
      gsap.to(btn, { scale: 1.04, duration: 0.25, ease: 'power2.out' });
    });
    btn.addEventListener('mouseleave', function () {
      gsap.to(btn, { scale: 1, duration: 0.3, ease: 'power2.out' });
    });
  });

  /* ── Hero spotlight + floating glows ── */

  var heroSpotlight = document.querySelector('[data-slash-spotlight]');

  if (heroSpotlight) {
    heroSpotlight.addEventListener('mousemove', function (event) {
      var rect = heroSpotlight.getBoundingClientRect();
      var x = ((event.clientX - rect.left) / rect.width) * 100;
      var y = ((event.clientY - rect.top) / rect.height) * 100;
      heroSpotlight.style.setProperty('--slash-spot-x', x + '%');
      heroSpotlight.style.setProperty('--slash-spot-y', y + '%');
    });
  }

  gsap.utils.toArray('.slash-glow').forEach(function (glow, index) {
    gsap.to(glow, {
      x: index % 2 === 0 ? 28 : -24,
      y: index === 1 ? 36 : -20,
      duration: 7 + index * 1.5,
      ease: 'sine.inOut',
      repeat: -1,
      yoyo: true,
    });
  });

  gsap.utils.toArray('.slash-card, .slash-trust-card, .slash-quote').forEach(function (card) {
    card.addEventListener('mouseenter', function () {
      gsap.to(card, {
        boxShadow: '0 20px 56px rgba(0,0,0,0.38), 0 0 0 1px rgba(61,214,140,0.18), 0 0 40px rgba(61,214,140,0.12)',
        duration: 0.35,
        ease: 'power2.out',
      });
    });
    card.addEventListener('mouseleave', function () {
      gsap.to(card, {
        boxShadow: '0 0 0 rgba(0,0,0,0)',
        duration: 0.45,
        ease: 'power2.out',
        clearProps: 'boxShadow',
      });
    });
  });

  document.documentElement.classList.add('slash-motion-on');
})();
