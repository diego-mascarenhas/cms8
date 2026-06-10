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

  /* ── Lead capture modal (after email, optional name + phone) ── */

  (function initLeadModal() {
    var modal = document.querySelector('[data-slash-lead-modal]');

    if (!modal) {
      return;
    }

    var configEl = document.getElementById('slash-lead-config');
    var config = { titles: [], emailConfirmed: '', validation: {} };

    if (configEl) {
      try {
        config = JSON.parse(configEl.textContent);
      } catch (error) {
        config = { titles: [], emailConfirmed: '', validation: {} };
      }
    }

    var validationMessages = config.validation || {};
    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i;
    var phonePattern = /^[+\-\d\s()]+$/;

    function validateEmail(value) {
      var email = (value || '').trim();

      if (email === '') {
        return validationMessages.emailRequired || 'Indica tu email para continuar.';
      }

      if (!emailPattern.test(email)) {
        return validationMessages.emailInvalid || 'El email no es válido.';
      }

      return '';
    }

    function validatePhone(value) {
      var phone = (value || '').trim();

      if (phone === '') {
        return '';
      }

      if (!phonePattern.test(phone)) {
        return validationMessages.phoneInvalid || 'El teléfono no es válido.';
      }

      return '';
    }

    function showFormFeedback(feedbackEl, message, formEl, fieldEl) {
      if (!feedbackEl) {
        return;
      }

      feedbackEl.textContent = message;
      feedbackEl.hidden = false;
      feedbackEl.setAttribute('data-slash-form-feedback-visible', 'true');
      feedbackEl.classList.remove('is-shaking');
      void feedbackEl.offsetWidth;
      feedbackEl.classList.add('is-shaking');

      if (formEl) {
        formEl.classList.add('is-invalid');
      }

      if (fieldEl) {
        fieldEl.classList.add('is-invalid');
      }
    }

    function clearFormFeedback(feedbackEl, formEl, fieldEl) {
      if (feedbackEl) {
        feedbackEl.textContent = '';
        feedbackEl.hidden = true;
        feedbackEl.removeAttribute('data-slash-form-feedback-visible');
        feedbackEl.classList.remove('is-shaking');
      }

      if (formEl) {
        formEl.classList.remove('is-invalid');
      }

      if (fieldEl) {
        fieldEl.classList.remove('is-invalid');
      }
    }

    var titleEl = modal.querySelector('[data-slash-lead-modal-title]');
    var emailEl = modal.querySelector('[data-slash-lead-modal-email]');
    var modalForm = modal.querySelector('[data-slash-lead-modal-form]');
    var modalNameInput = modalForm ? modalForm.querySelector('input[name="name"]') : null;
    var modalPhoneInput = modalForm ? modalForm.querySelector('[data-slash-phone-input]') : null;
    var modalPhoneField = modalPhoneInput ? modalPhoneInput.closest('[data-slash-form-field]') : null;
    var modalFeedback = modalForm ? modalForm.querySelector('[data-slash-form-feedback]') : null;
    var modalSubmitButton = modalForm ? modalForm.querySelector('[data-slash-lead-modal-submit]') : null;
    var pending = null;
    var lastActiveElement = null;

    function pickTitle() {
      var titles = config.titles || [];

      if (!titles.length) {
        return '';
      }

      return titles[Math.floor(Math.random() * titles.length)];
    }

    function hasModalExtraFields() {
      var name = (modalNameInput && modalNameInput.value ? modalNameInput.value : '').trim();
      var phone = (modalPhoneInput && modalPhoneInput.value ? modalPhoneInput.value : '').trim();

      return name.length > 0 || phone.length > 0;
    }

    function updateSubmitLabel() {
      if (!modalSubmitButton) {
        return;
      }

      modalSubmitButton.textContent = hasModalExtraFields()
        ? (config.submitWithDetails || 'Enviar')
        : (config.submitEmailOnly || 'Enviar solo mi email');
    }

    function openModal(payload) {
      pending = payload;
      lastActiveElement = document.activeElement;

      if (titleEl) {
        titleEl.textContent = pickTitle();
      }

      if (emailEl) {
        emailEl.textContent = (config.emailConfirmed || ':email').replace(':email', payload.email);
        emailEl.hidden = false;
      }

      if (modalForm) {
        modalForm.reset();
      }

      clearFormFeedback(modalFeedback, null, modalPhoneField);
      updateSubmitLabel();

      modal.hidden = false;
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('slash-lead-modal-open');

      if (modalNameInput) {
        modalNameInput.focus();
      }
    }

    function submitLead(extra) {
      if (!pending) {
        return;
      }

      var form = document.createElement('form');
      form.method = 'POST';
      form.action = pending.action;
      form.style.display = 'none';

      function addField(name, value) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
      }

      addField('_token', pending.token);
      addField('email', pending.email);
      addField('source', pending.source);

      if (extra.name) {
        addField('name', extra.name);
      }

      if (extra.phone) {
        addField('phone', extra.phone);
      }

      document.body.appendChild(form);
      form.submit();
    }

    document.querySelectorAll('[data-slash-lead-form]').forEach(function (leadForm) {
      var emailInput = leadForm.querySelector('[data-slash-email-input]');
      var feedbackEl = leadForm.querySelector('[data-slash-form-feedback]');

      if (emailInput) {
        emailInput.addEventListener('input', function () {
          clearFormFeedback(feedbackEl, leadForm, null);
        });
      }

      if (feedbackEl && feedbackEl.getAttribute('data-slash-form-feedback-visible') === 'true') {
        leadForm.classList.add('is-invalid');
      }

      leadForm.addEventListener('submit', function (event) {
        event.preventDefault();

        var email = (emailInput && emailInput.value ? emailInput.value : '').trim();
        var emailError = validateEmail(email);

        if (emailError) {
          showFormFeedback(feedbackEl, emailError, leadForm, null);

          if (emailInput) {
            emailInput.focus();
          }

          return;
        }

        clearFormFeedback(feedbackEl, leadForm, null);

        var sourceInput = leadForm.querySelector('input[name="source"]');
        var tokenInput = leadForm.querySelector('input[name="_token"]');

        openModal({
          email: email,
          source: sourceInput ? sourceInput.value : 'cta',
          token: tokenInput ? tokenInput.value : '',
          action: leadForm.getAttribute('action') || '',
        });
      });
    });

    if (modalNameInput) {
      modalNameInput.addEventListener('input', function () {
        updateSubmitLabel();
      });
    }

    if (modalPhoneInput) {
      modalPhoneInput.addEventListener('input', function () {
        clearFormFeedback(modalFeedback, null, modalPhoneField);
        updateSubmitLabel();
      });
    }

    if (modalForm) {
      modalForm.addEventListener('submit', function (event) {
        event.preventDefault();

        if (!pending) {
          return;
        }

        var nameInput = modalForm.querySelector('input[name="name"]');
        var phone = (modalPhoneInput && modalPhoneInput.value ? modalPhoneInput.value : '').trim();
        var phoneError = validatePhone(phone);

        if (phoneError) {
          showFormFeedback(modalFeedback, phoneError, null, modalPhoneField);

          if (modalPhoneInput) {
            modalPhoneInput.focus();
          }

          return;
        }

        clearFormFeedback(modalFeedback, null, modalPhoneField);

        submitLead({
          name: (nameInput && nameInput.value ? nameInput.value : '').trim(),
          phone: phone,
        });
      });
    }

    modal.querySelectorAll('[data-slash-lead-modal-close]').forEach(function (button) {
      button.addEventListener('click', function () {
        submitLead({ name: '', phone: '' });
      });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !modal.hidden) {
        submitLead({ name: '', phone: '' });
      }
    });
  })();

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
