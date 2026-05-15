/**
 * Main
 */

'use strict';

let isRtl = window.Helpers.isRtl(),
  isDarkStyle = window.Helpers.isDarkStyle(),
  menu,
  animate,
  isHorizontalLayout = false;

if (document.getElementById('layout-menu')) {
  isHorizontalLayout = document.getElementById('layout-menu').classList.contains('menu-horizontal');
}

(function () {
  setTimeout(function () {
    window.Helpers.initCustomOptionCheck();
  }, 1000);

  if (typeof Waves !== 'undefined') {
    Waves.init();
    Waves.attach(".btn[class*='btn-']:not([class*='btn-outline-']):not([class*='btn-label-'])", ['waves-light']);
    Waves.attach("[class*='btn-outline-']");
    Waves.attach("[class*='btn-label-']");
    Waves.attach('.pagination .page-item .page-link');
  }

  // Initialize menu
  //-----------------

  let layoutMenuEl = document.querySelectorAll('#layout-menu');
  layoutMenuEl.forEach(function (element) {
    menu = new Menu(element, {
      orientation: isHorizontalLayout ? 'horizontal' : 'vertical',
      closeChildren: isHorizontalLayout ? true : false,
      // ? This option only works with Horizontal menu
      showDropdownOnHover: localStorage.getItem('templateCustomizer-' + templateName + '--ShowDropdownOnHover') // If value(showDropdownOnHover) is set in local storage
        ? localStorage.getItem('templateCustomizer-' + templateName + '--ShowDropdownOnHover') === 'true' // Use the local storage value
        : window.templateCustomizer !== undefined // If value is set in config.js
        ? window.templateCustomizer.settings.defaultShowDropdownOnHover // Use the config.js value
        : true // Use this if you are not using the config.js and want to set value directly from here
    });
    // Change parameter to true if you want scroll animation
    window.Helpers.scrollToActive((animate = false));
    window.Helpers.mainMenu = menu;
  });

  // Initialize menu togglers and bind click on each
  let menuToggler = document.querySelectorAll('.layout-menu-toggle');
  menuToggler.forEach(item => {
    item.addEventListener('click', event => {
      event.preventDefault();
      window.Helpers.toggleCollapsed();
      // Enable menu state with local storage support if enableMenuLocalStorage = true from config.js
      if (config.enableMenuLocalStorage && !window.Helpers.isSmallScreen()) {
        try {
          localStorage.setItem(
            'templateCustomizer-' + templateName + '--LayoutCollapsed',
            String(window.Helpers.isCollapsed())
          );
          // Update customizer checkbox state on click of menu toggler
          let layoutCollapsedCustomizerOptions = document.querySelector('.template-customizer-layouts-options');
          if (layoutCollapsedCustomizerOptions) {
            let layoutCollapsedVal = window.Helpers.isCollapsed() ? 'collapsed' : 'expanded';
            layoutCollapsedCustomizerOptions.querySelector(`input[value="${layoutCollapsedVal}"]`).click();
          }
        } catch (e) {}
      }
    });
  });

  // Menu swipe gesture

  // Detect swipe gesture on the target element and call swipe In
  window.Helpers.swipeIn('.drag-target', function (e) {
    window.Helpers.setCollapsed(false);
  });

  // Detect swipe gesture on the target element and call swipe Out
  window.Helpers.swipeOut('#layout-menu', function (e) {
    if (window.Helpers.isSmallScreen()) window.Helpers.setCollapsed(true);
  });

  // Display in main menu when menu scrolls
  let menuInnerContainer = document.getElementsByClassName('menu-inner'),
    menuInnerShadow = document.getElementsByClassName('menu-inner-shadow')[0];
  if (menuInnerContainer.length > 0 && menuInnerShadow) {
    menuInnerContainer[0].addEventListener('ps-scroll-y', function () {
      if (this.querySelector('.ps__thumb-y').offsetTop) {
        menuInnerShadow.style.display = 'block';
      } else {
        menuInnerShadow.style.display = 'none';
      }
    });
  }

  // Update light/dark image based on current style
  function switchImage(style) {
    if (style === 'system') {
      if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
        style = 'dark';
      } else {
        style = 'light';
      }
    }
    const switchImagesList = [].slice.call(document.querySelectorAll('[data-app-' + style + '-img]'));
    switchImagesList.map(function (imageEl) {
      const setImage = imageEl.getAttribute('data-app-' + style + '-img');
      imageEl.src = assetsPath + 'img/' + setImage; // Using window.assetsPath to get the exact relative path
    });
  }

  //Style Switcher (Light/Dark/System Mode)
  let styleSwitcher = document.querySelector('.dropdown-style-switcher');

  // Get style from local storage or use 'system' as default
  let storedStyle =
    localStorage.getItem('templateCustomizer-' + templateName + '--Style') || //if no template style then use Customizer style
    (window.templateCustomizer?.settings?.defaultStyle ?? 'light'); //!if there is no Customizer then use default style as light

  // Set style on click of style switcher item if template customizer is enabled
  if (window.templateCustomizer && styleSwitcher) {
    let styleSwitcherItems = [].slice.call(styleSwitcher.children[1].querySelectorAll('.dropdown-item'));
    styleSwitcherItems.forEach(function (item) {
      item.addEventListener('click', function () {
        let currentStyle = this.getAttribute('data-theme');
        if (currentStyle === 'light') {
          window.templateCustomizer.setStyle('light');
        } else if (currentStyle === 'dark') {
          window.templateCustomizer.setStyle('dark');
        } else {
          window.templateCustomizer.setStyle('system');
        }
      });
    });

    // Update style switcher icon based on the stored style

    const styleSwitcherIcon = styleSwitcher.querySelector('i');

    if (storedStyle === 'light') {
      styleSwitcherIcon.classList.add('ti-sun');
      new bootstrap.Tooltip(styleSwitcherIcon, {
        title: 'Light Mode',
        fallbackPlacements: ['bottom']
      });
    } else if (storedStyle === 'dark') {
      styleSwitcherIcon.classList.add('ti-moon');
      new bootstrap.Tooltip(styleSwitcherIcon, {
        title: 'Dark Mode',
        fallbackPlacements: ['bottom']
      });
    } else {
      styleSwitcherIcon.classList.add('ti-device-desktop');
      new bootstrap.Tooltip(styleSwitcherIcon, {
        title: 'System Mode',
        fallbackPlacements: ['bottom']
      });
    }
  }

  // Run switchImage function based on the stored style
  switchImage(storedStyle);

  let languageDropdown = document.getElementsByClassName('dropdown-language');

  if (languageDropdown.length) {
    let dropdownItems = languageDropdown[0].querySelectorAll('.dropdown-item');
    const dropdownActiveItem = languageDropdown[0].querySelector('.dropdown-item.active');

    directionChange(dropdownActiveItem.dataset.textDirection);

    for (let i = 0; i < dropdownItems.length; i++) {
      dropdownItems[i].addEventListener('click', function () {
        let textDirection = this.getAttribute('data-text-direction');
        window.templateCustomizer.setLang(this.getAttribute('data-language'));
        directionChange(textDirection);
      });
    }
    function directionChange(textDirection) {
      if (textDirection === 'rtl') {
        if (localStorage.getItem('templateCustomizer-' + templateName + '--Rtl') !== 'true')
          window.templateCustomizer ? window.templateCustomizer.setRtl(true) : '';
      } else {
        if (localStorage.getItem('templateCustomizer-' + templateName + '--Rtl') === 'true')
          window.templateCustomizer ? window.templateCustomizer.setRtl(false) : '';
      }
    }
  }

  // add on click javascript for template customizer reset button id template-customizer-reset-btn

  setTimeout(function () {
    let templateCustomizerResetBtn = document.querySelector('.template-customizer-reset-btn');
    if (templateCustomizerResetBtn) {
      templateCustomizerResetBtn.onclick = function () {
        window.location.href = baseUrl + 'lang/en';
      };
    }
  }, 1500);

  // Notification
  // ------------
  const notificationMarkAsReadAll = document.querySelector('.dropdown-notifications-all');
  const notificationMarkAsReadList = document.querySelectorAll('.dropdown-notifications-read');

  const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  const getNavbarReadAtLabel = formattedDate => {
    const dropdown = document.querySelector('.dropdown-notifications[data-read-at-template]');
    const template = dropdown?.getAttribute('data-read-at-template') || 'Read __DATE__';
    return template.replace('__DATE__', formattedDate);
  };

  const updateNavbarNotificationBadge = () => {
    const unreadItems = document.querySelectorAll(
      '.dropdown-notifications .dropdown-notifications-item:not(.marked-as-read)[data-notification-id]'
    );
    const badge = document.querySelector('.dropdown-notifications .badge-notifications');
    if (!badge) {
      return;
    }
    const count = unreadItems.length;
    if (count <= 0) {
      badge.remove();
    } else {
      badge.textContent = String(count);
    }
  };

  const applyNavbarNotificationReadState = listItem => {
    if (!listItem) {
      return;
    }
    listItem.classList.add('marked-as-read');
    const actions = listItem.querySelector('.dropdown-notifications-actions');
    if (actions) {
      actions.remove();
    }
  };

  const markNavbarNotificationRead = (url, listItem) => {
    if (!url || !listItem || listItem.classList.contains('marked-as-read')) {
      return Promise.resolve();
    }
    return fetch(url, {
      method: 'PATCH',
      headers: {
        'X-CSRF-TOKEN': getCsrfToken(),
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin'
    })
      .then(response => response.json())
      .then(data => {
        if (!data.success) {
          return;
        }
        applyNavbarNotificationReadState(listItem);
        const dateEl = listItem.querySelector('[data-notification-date]');
        if (dateEl && data.read_at_formatted) {
          dateEl.textContent = getNavbarReadAtLabel(data.read_at_formatted);
        }
        updateNavbarNotificationBadge();
      })
      .catch(() => {});
  };

  // Notification: Mark as all as read
  if (notificationMarkAsReadAll) {
    notificationMarkAsReadAll.addEventListener('click', event => {
      event.preventDefault();
      event.stopPropagation();
      const markAllUrl = notificationMarkAsReadAll.getAttribute('data-mark-all-read-url');
      if (markAllUrl) {
        fetch(markAllUrl, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': getCsrfToken(),
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'same-origin'
        })
          .then(response => response.json())
          .then(data => {
            if (!data.success) {
              return;
            }
            document
              .querySelectorAll(
                '.dropdown-notifications .dropdown-notifications-item:not(.marked-as-read)[data-notification-id]'
              )
              .forEach(listItem => {
                applyNavbarNotificationReadState(listItem);
                const dateEl = listItem.querySelector('[data-notification-date]');
                if (dateEl && data.read_at_formatted) {
                  dateEl.textContent = getNavbarReadAtLabel(data.read_at_formatted);
                }
              });
            const markAllLink = document.querySelector('.dropdown-notifications-all');
            if (markAllLink) {
              markAllLink.remove();
            }
            updateNavbarNotificationBadge();
          })
          .catch(() => {});
        return;
      }
      notificationMarkAsReadList.forEach(item => {
        item.closest('.dropdown-notifications-item')?.classList.add('marked-as-read');
      });
    });
  }
  // Notification: Mark as read/unread onclick of dot
  if (notificationMarkAsReadList) {
    notificationMarkAsReadList.forEach(item => {
      item.addEventListener('click', event => {
        event.preventDefault();
        event.stopPropagation();
        const url = item.getAttribute('data-mark-read-url');
        const listItem = item.closest('.dropdown-notifications-item');
        if (url && listItem) {
          markNavbarNotificationRead(url, listItem);
          return;
        }
        listItem?.classList.toggle('marked-as-read');
      });
    });
  }

  // Notification: Mark as read/unread onclick of dot
  const notificationArchiveMessageList = document.querySelectorAll('.dropdown-notifications-archive');
  notificationArchiveMessageList.forEach(item => {
    item.addEventListener('click', event => {
      item.closest('.dropdown-notifications-item').remove();
    });
  });

  // Init helpers & misc
  // --------------------

  // Init BS Tooltip
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  // Accordion active class
  const accordionActiveFunction = function (e) {
    if (e.type == 'show.bs.collapse' || e.type == 'show.bs.collapse') {
      e.target.closest('.accordion-item').classList.add('active');
    } else {
      e.target.closest('.accordion-item').classList.remove('active');
    }
  };

  const accordionTriggerList = [].slice.call(document.querySelectorAll('.accordion'));
  const accordionList = accordionTriggerList.map(function (accordionTriggerEl) {
    accordionTriggerEl.addEventListener('show.bs.collapse', accordionActiveFunction);
    accordionTriggerEl.addEventListener('hide.bs.collapse', accordionActiveFunction);
  });

  // If layout is RTL add .dropdown-menu-end class to .dropdown-menu
  // if (isRtl) {
  //   Helpers._addClass('dropdown-menu-end', document.querySelectorAll('#layout-navbar .dropdown-menu'));
  // }

  // Auto update layout based on screen size
  window.Helpers.setAutoUpdate(true);

  // Toggle Password Visibility
  window.Helpers.initPasswordToggle();

  // Speech To Text
  window.Helpers.initSpeechToText();

  // Init PerfectScrollbar in Navbar Dropdown (i.e notification)
  window.Helpers.initNavbarDropdownScrollbar();

  let horizontalMenuTemplate = document.querySelector("[data-template^='horizontal-menu']");
  if (horizontalMenuTemplate) {
    // if screen size is small then set navbar fixed
    if (window.innerWidth < window.Helpers.LAYOUT_BREAKPOINT) {
      window.Helpers.setNavbarFixed('fixed');
    } else {
      window.Helpers.setNavbarFixed('');
    }
  }

  // On window resize listener
  // -------------------------
  window.addEventListener(
    'resize',
    function (event) {
      // Hide open search input and set value blank
      if (window.innerWidth >= window.Helpers.LAYOUT_BREAKPOINT) {
        if (document.querySelector('.search-input-wrapper')) {
          document.querySelector('.search-input-wrapper').classList.add('d-none');
          document.querySelector('.search-input').value = '';
        }
      }
      // Horizontal Layout : Update menu based on window size
      if (horizontalMenuTemplate) {
        // if screen size is small then set navbar fixed
        if (window.innerWidth < window.Helpers.LAYOUT_BREAKPOINT) {
          window.Helpers.setNavbarFixed('fixed');
        } else {
          window.Helpers.setNavbarFixed('');
        }
        setTimeout(function () {
          if (window.innerWidth < window.Helpers.LAYOUT_BREAKPOINT) {
            if (document.getElementById('layout-menu')) {
              if (document.getElementById('layout-menu').classList.contains('menu-horizontal')) {
                menu.switchMenu('vertical');
              }
            }
          } else {
            if (document.getElementById('layout-menu')) {
              if (document.getElementById('layout-menu').classList.contains('menu-vertical')) {
                menu.switchMenu('horizontal');
              }
            }
          }
        }, 100);
      }
    },
    true
  );

  // Manage menu expanded/collapsed with templateCustomizer & local storage
  //------------------------------------------------------------------

  // If current layout is horizontal OR current window screen is small (overlay menu) than return from here
  if (isHorizontalLayout || window.Helpers.isSmallScreen()) {
    return;
  }

  // If current layout is vertical and current window screen is > small

  // Auto update menu collapsed/expanded based on the themeConfig
  if (typeof TemplateCustomizer !== 'undefined') {
    if (window.templateCustomizer.settings.defaultMenuCollapsed) {
      window.Helpers.setCollapsed(true, false);
    } else {
      window.Helpers.setCollapsed(false, false);
    }
  }

  // Manage menu expanded/collapsed state with local storage support If enableMenuLocalStorage = true in config.js
  if (typeof config !== 'undefined') {
    if (config.enableMenuLocalStorage) {
      try {
        if (localStorage.getItem('templateCustomizer-' + templateName + '--LayoutCollapsed') !== null)
          window.Helpers.setCollapsed(
            localStorage.getItem('templateCustomizer-' + templateName + '--LayoutCollapsed') === 'true',
            false
          );
      } catch (e) {}
    }
  }
})();

// ! Removed following code if you do't wish to use jQuery. Remember that navbar search functionality will stop working on removal.
if (typeof $ !== 'undefined') {
  $(function () {
    // ! TODO: Required to load after DOM is ready, did this now with jQuery ready.
    window.Helpers.initSidebarToggle();
    // Toggle Universal Sidebar

    // Navbar Search with autosuggest (typeahead)
    // ? You can remove the following JS if you don't want to use search functionality.
    //----------------------------------------------------------------------------------

    var searchToggler = $('.search-toggler'),
      searchInputWrapper = $('.search-input-wrapper'),
      searchInput = $('.search-input'),
      contentBackdrop = $('.content-backdrop');

    console.log('[INIT] Search elements found:', {
      toggler: searchToggler.length,
      wrapper: searchInputWrapper.length,
      input: searchInput.length,
      backdrop: contentBackdrop.length
    });

    // Open search input on click of search icon
    if (searchToggler.length) {
      searchToggler.on('click', function () {
        if (searchInputWrapper.length) {
          searchInputWrapper.toggleClass('d-none');
          searchInput.focus();
        }
      });
    }
    // Open search on 'CTRL+/' (or CMD+/ on Mac)
    $(document).on('keydown', function (event) {
      let ctrlKey = event.ctrlKey || event.metaKey,
        slashKey = event.which === 191 || event.keyCode === 191;

      if (ctrlKey && slashKey) {
        event.preventDefault();
        if (searchInputWrapper.length) {
          searchInputWrapper.removeClass('d-none');
          setTimeout(function() {
            searchInput.focus();
          }, 10);
        }
      }
    });
    // Note: Following code is required to update container class of typeahead dropdown width on focus of search input. setTimeout is required to allow time to initiate Typeahead UI.
    setTimeout(function () {
      var twitterTypeahead = $('.twitter-typeahead');
      searchInput.on('focus', function () {
        if (searchInputWrapper.hasClass('container-xxl')) {
          searchInputWrapper.find(twitterTypeahead).addClass('container-xxl');
          twitterTypeahead.removeClass('container-fluid');
        } else if (searchInputWrapper.hasClass('container-fluid')) {
          searchInputWrapper.find(twitterTypeahead).addClass('container-fluid');
          twitterTypeahead.removeClass('container-xxl');
        }
      });
    }, 10);

    if (searchInput.length) {
      console.log('[INIT] Initializing Typeahead on search input');
      console.log('[VERSION] Search JS Version: 2026-01-12-20:15-only-contacts-debug');

      // Filter config
      var filterConfig = function (data) {
        return function findMatches(q, cb) {
          let matches;
          matches = [];
          data.filter(function (i) {
            if (i.name.toLowerCase().startsWith(q.toLowerCase())) {
              matches.push(i);
            } else if (
              !i.name.toLowerCase().startsWith(q.toLowerCase()) &&
              i.name.toLowerCase().includes(q.toLowerCase())
            ) {
              matches.push(i);
              matches.sort(function (a, b) {
                return b.name < a.name ? 1 : -1;
              });
            } else {
              return [];
            }
          });
          cb(matches);
        };
      };

      // Modern async search with Promise and debouncing
      // Shared AJAX cache to avoid multiple requests for the same query
      var searchAjaxCache = {
        lastQuery: null,
        lastResponse: null,
        inflight: null,
        listeners: []
      };

      // Fetch search response (shared among all datasets)
      function fetchSearchResponse(query, onDone) {
        // If we already have a response for this exact query, reuse it immediately
        if (searchAjaxCache.lastQuery === query && searchAjaxCache.lastResponse) {
          return onDone(searchAjaxCache.lastResponse);
        }
        // If there is a request in-flight for this query, attach as listener
        if (searchAjaxCache.inflight && searchAjaxCache.lastQuery === query) {
          searchAjaxCache.listeners.push(onDone);
          return;
        }
        // Start a new request for this query
        searchAjaxCache.lastQuery = query;
        searchAjaxCache.listeners = [onDone];
        searchAjaxCache.inflight = $.ajax({
          url: baseUrl + 'contact/search',
          dataType: 'json',
          data: { q: query }
        })
          .done(function (response) {
            searchAjaxCache.lastResponse = response;
            var cbs = searchAjaxCache.listeners.slice(0);
            searchAjaxCache.listeners = [];
            cbs.forEach(function (fn) { try { fn(response); } catch (e) { console.error(e); } });
          })
          .fail(function (xhr, status, error) {
            console.error('[Search] AJAX Error!', status, error);
            var cbs = searchAjaxCache.listeners.slice(0);
            searchAjaxCache.listeners = [];
            cbs.forEach(function (fn) { try { fn({}); } catch (e) { console.error(e); } });
          })
          .always(function () {
            searchAjaxCache.inflight = null;
          });
      }

      // Dynamic search function - queries server on each keystroke
      var dynamicSearch = function (field) {
        return function findMatches(q, cb) {
          if (!q || q.length < 1) {
            return cb([]);
          }

          fetchSearchResponse(q, function (response) {
            var results = response[field] || [];
            if (typeof results === 'object' && !Array.isArray(results)) {
              results = Object.values(results);
            }
            // Force array and ensure each item has required properties
            if (!Array.isArray(results)) {
              results = [];
            }
            cb(results);
          });
        };
      };

      // Special handler for contacts to use members data
      var contactsSearch = function (q, cb) {
        if (!q || q.length < 1) {
          return cb([]);
        }
        fetchSearchResponse(q, function (response) {
          // Use members data for contacts
          var results = response['members'] || [];
          if (typeof results === 'object' && !Array.isArray(results)) {
            results = Object.values(results);
          }
          if (!Array.isArray(results)) {
            results = [];
          }
          cb(results);
        });
      };


      // Init typeahead on searchInput
      searchInput.each(function () {
        var $this = $(this);
        $this
          .typeahead(
            {
              hint: false,
              minLength: 1,
              classNames: {
                menu: 'tt-menu navbar-search-suggestion',
                cursor: 'active',
                suggestion: 'suggestion d-flex justify-content-between px-3 py-2 w-100'
              }
            },

            // Contacts
            {
              name: 'contacts',
              display: 'name',
              limit: 10,
              source: dynamicSearch('members'),
              templates: {
                header: '<h6 class="suggestions-header text-primary mb-0 mx-3 mt-3 pb-2">Contactos</h6>',
                suggestion: function (data) {
                  if (!data || !data.name) return '';
                  var name = data.name || '';
                  var subtitle = data.subtitle || '';
                  var url = data.url || '#';
                  return (
                    '<a href="' +
                    url + '">' +
                    '<div class="d-flex align-items-center">' +
                    '<i class="ti ti-user me-2"></i>' +
                    '<div class="user-info">' +
                    '<h6 class="mb-0">' +
                    name +
                    '</h6>' +
                    '<small class="text-muted">' +
                    subtitle +
                    '</small>' +
                    '</div>' +
                    '</div>' +
                    '</a>'
                  );
                },
                notFound:
                  '<div class="not-found px-3 py-2">' +
                  '<h6 class="suggestions-header text-primary mb-2">Contactos</h6>' +
                  '<p class="py-2 mb-0"><i class="ti ti-alert-circle ti-xs me-2"></i> Contacto no encontrado</p>' +
                  '</div>'
              }
            }
            // COMMENTED OUT FOR DEBUGGING - Only contacts dataset active
            // // Enterprises
            // {
            //   name: 'enterprises',
            //   display: 'name',
            //   limit: 4,
            //   source: dynamicSearch('enterprises'),
            //   templates: {
            //     header: '<h6 class="suggestions-header text-primary mb-0 mx-3 mt-3 pb-2">Empresas</h6>',
            //     suggestion: function (data) {
            //       if (!data || !data.name) return '';
            //       var name = data.name || '';
            //       var subtitle = data.subtitle || '';
            //       var url = data.url || '#';
            //       return (
            //         '<a href="' +
            //         url + '">' +
            //         '<div class="d-flex align-items-center">' +
            //         '<i class="ti ti-building me-2"></i>' +
            //         '<div class="user-info">' +
            //         '<h6 class="mb-0">' +
            //         name +
            //         '</h6>' +
            //         '<small class="text-muted">' +
            //         subtitle +
            //         '</small>' +
            //         '</div>' +
            //         '</div>' +
            //         '</a>'
            //       );
            //     },
            //     notFound:
            //       '<div class="not-found px-3 py-2">' +
            //       '<h6 class="suggestions-header text-primary mb-2">Empresas</h6>' +
            //       '<p class="py-2 mb-0"><i class="ti ti-alert-circle ti-xs me-2"></i> Empresa no encontrada</p>' +
            //       '</div>'
            //   }
            // },
            // // Services
            // {
            //   name: 'services',
            //   display: 'name',
            //   limit: 4,
            //   source: dynamicSearch('services'),
            //   templates: {
            //     header: '<h6 class="suggestions-header text-primary mb-0 mx-3 mt-3 pb-2">Servicios</h6>',
            //     suggestion: function ({ name, subtitle, url }) {
            //       return (
            //         '<a href="' +
            //         url + '">' +
            //         '<div class="d-flex align-items-center">' +
            //         '<i class="ti ti-world me-2"></i>' +
            //         '<div class="user-info">' +
            //         '<h6 class="mb-0">' +
            //         name +
            //         '</h6>' +
            //         '<small class="text-muted">' +
            //         subtitle +
            //         '</small>' +
            //         '</div>' +
            //         '</div>' +
            //         '</a>'
            //       );
            //     },
            //     notFound:
            //       '<div class="not-found px-3 py-2">' +
            //       '<h6 class="suggestions-header text-primary mb-2">Servicios</h6>' +
            //       '<p class="py-2 mb-0"><i class="ti ti-alert-circle ti-xs me-2"></i> Servicio no encontrado</p>' +
            //       '</div>'
            //   }
            // },
            // // Projects
            // {
            //   name: 'projects',
            //   display: 'name',
            //   limit: 4,
            //   source: dynamicSearch('projects'),
            //   templates: {
            //     header: '<h6 class="suggestions-header text-primary mb-0 mx-3 mt-3 pb-2">Proyectos</h6>',
            //     suggestion: function ({ name, subtitle, url }) {
            //       return (
            //         '<a href="' +
            //         url + '">' +
            //         '<div class="d-flex align-items-center">' +
            //         '<i class="ti ti-folder me-2"></i>' +
            //         '<div class="user-info">' +
            //         '<h6 class="mb-0">' +
            //         name +
            //         '</h6>' +
            //         '<small class="text-muted">' +
            //         subtitle +
            //         '</small>' +
            //         '</div>' +
            //         '</div>' +
            //         '</a>'
            //       );
            //     },
            //     notFound:
            //       '<div class="not-found px-3 py-2">' +
            //       '<h6 class="suggestions-header text-primary mb-2">Proyectos</h6>' +
            //       '<p class="py-2 mb-0"><i class="ti ti-alert-circle ti-xs me-2"></i> Proyecto no encontrado</p>' +
            //       '</div>'
            //   }
            // },
            // // Invoices
            // {
            //   name: 'invoices',
            //   display: 'name',
            //   limit: 4,
            //   source: dynamicSearch('invoices'),
            //   templates: {
            //     header: '<h6 class="suggestions-header text-primary mb-0 mx-3 mt-3 pb-2">Facturas</h6>',
            //     suggestion: function ({ name, subtitle, url }) {
            //       return (
            //         '<a href="' +
            //         url + '">' +
            //         '<div class="d-flex align-items-center">' +
            //         '<i class="ti ti-file-invoice me-2"></i>' +
            //         '<div class="user-info">' +
            //         '<h6 class="mb-0">' +
            //         name +
            //         '</h6>' +
            //         '<small class="text-muted">' +
            //         subtitle +
            //         '</small>' +
            //         '</div>' +
            //         '</div>' +
            //         '</a>'
            //       );
            //     },
            //     notFound:
            //       '<div class="not-found px-3 py-2">' +
            //       '<h6 class="suggestions-header text-primary mb-2">Facturas</h6>' +
            //       '<p class="py-2 mb-0"><i class="ti ti-alert-circle ti-xs me-2"></i> Factura no encontrada</p>' +
            //       '</div>'
            //   }
            // }
          )
          //On typeahead result render.
          .bind('typeahead:render', function (ev, suggestions, async, dataset) {
            console.log('[Typeahead] Render event:', {dataset, suggestions, async});
            // Show content backdrop,
            contentBackdrop.addClass('show').removeClass('fade');
          })
          // On typeahead select
          .bind('typeahead:select', function (ev, suggestion) {
            // Open selected page (URL is already complete from backend)
            if (suggestion.url && suggestion.url !== 'javascript:;') {
              window.location = suggestion.url;
            }
          })
          // On typeahead close
          .bind('typeahead:close', function () {
            // Clear search
            searchInput.val('');
            $this.typeahead('val', '');
            // Hide search input wrapper
            searchInputWrapper.addClass('d-none');
            // Fade content backdrop
            contentBackdrop.addClass('fade').removeClass('show');
          });

        // On searchInput keyup, Fade content backdrop if search input is blank
        searchInput.on('keyup', function () {
          if (searchInput.val() == '') {
            contentBackdrop.addClass('fade').removeClass('show');
          }
        });
      });

      // Init PerfectScrollbar in search result
      var psSearch;
      $('.navbar-search-suggestion').each(function () {
        psSearch = new PerfectScrollbar($(this)[0], {
          wheelPropagation: false,
          suppressScrollX: true
        });
      });

      searchInput.on('keyup', function () {
        psSearch.update();
      });
    }
  });
}

// Second Contact Search implementation removed - using the first implementation above
