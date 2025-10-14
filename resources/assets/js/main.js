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

  // Notification: Mark as all as read
  if (notificationMarkAsReadAll) {
    notificationMarkAsReadAll.addEventListener('click', event => {
      notificationMarkAsReadList.forEach(item => {
        item.closest('.dropdown-notifications-item').classList.add('marked-as-read');
      });
    });
  }
  // Notification: Mark as read/unread onclick of dot
  if (notificationMarkAsReadList) {
    notificationMarkAsReadList.forEach(item => {
      item.addEventListener('click', event => {
        item.closest('.dropdown-notifications-item').classList.toggle('marked-as-read');
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

    // Open search input on click of search icon
    if (searchToggler.length) {
      searchToggler.on('click', function () {
        if (searchInputWrapper.length) {
          searchInputWrapper.toggleClass('d-none');
          searchInput.focus();
        }
      });
    }
    // Open search on 'CTRL+/'
    $(document).on('keydown', function (event) {
      let ctrlKey = event.ctrlKey,
        slashKey = event.which === 191;

      if (ctrlKey && slashKey) {
        if (searchInputWrapper.length) {
          searchInputWrapper.toggleClass('d-none');
          searchInput.focus();
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

      // Dynamic search function - queries server on each keystroke
      var dynamicSearch = function (field) {
        return function findMatches(q, cb) {
          console.log('[Search] Query:', q, 'Field:', field);
          if (!q || q.length < 1) {
            console.log('[Search] Empty query, returning empty array');
            return cb([]);
          }

          console.log('[Search] Making AJAX call to /contact/search');
          $.ajax({
            url: '/contact/search',
            dataType: 'json',
            data: { q: q },
            success: function(response) {
              console.log('[Search] Success! Full Response:', response);
              console.log('[Search] Field requested:', field);
              console.log('[Search] Field exists?', field in response);
              console.log('[Search] Field data type:', typeof response[field]);
              console.log('[Search] Field raw data:', response[field]);
              console.log('[Search] Is array?', Array.isArray(response[field]));

              // Convert to array if needed
              var results = response[field] || [];
              if (typeof results === 'object' && !Array.isArray(results)) {
                console.log('[Search] Converting object to array using Object.values()');
                results = Object.values(results);
              }

              console.log('[Search] ✅ Final results array:', results);
              console.log('[Search] ✅ Array length:', results.length);
              console.log('[Search] ✅ First result:', results[0]);
              console.log('[Search] ✅ Sample result.name:', results[0] ? results[0].name : 'N/A');
              console.log('[Search] ⏩ Calling cb() NOW with', results.length, 'results');

              cb(results);

              console.log('[Search] ✓ cb() called for field:', field);
            },
            error: function(xhr, status, error) {
              console.error('[Search] AJAX Error!', status, error);
              cb([]);
            }
          });
        };
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
            // ? Add/Update blocks as per need
            // // Pages
            // {
            //   name: 'pages',
            //   display: 'name',
            //   limit: 5,
            //   source: filterConfig(searchData.pages),
            //   templates: {
            //     header: '<h6 class="suggestions-header text-primary mb-0 mx-3 mt-3 pb-2">Pages</h6>',
            //     suggestion: function ({ url, icon, name }) {
            //       return (
            //         '<a href="' +
            //         baseUrl +
            //         url +
            //         '">' +
            //         '<div>' +
            //         '<i class="ti ' +
            //         icon +
            //         ' me-2"></i>' +
            //         '<span class="align-middle">' +
            //         name +
            //         '</span>' +
            //         '</div>' +
            //         '</a>'
            //       );
            //     },
            //     notFound:
            //       '<div class="not-found px-3 py-2">' +
            //       '<h6 class="suggestions-header text-primary mb-2">Pages</h6>' +
            //       '<p class="py-2 mb-0"><i class="ti ti-alert-circle ti-xs me-2"></i> No Results Found</p>' +
            //       '</div>'
            //   }
            // },
            // // Files
            // {
            //   name: 'files',
            //   display: 'name',
            //   limit: 4,
            //   source: filterConfig(searchData.files),
            //   templates: {
            //     header: '<h6 class="suggestions-header text-primary mb-0 mx-3 mt-3 pb-2">Files</h6>',
            //     suggestion: function ({ src, name, subtitle, meta }) {
            //       return (
            //         '<a href="javascript:;">' +
            //         '<div class="d-flex w-50">' +
            //         '<img class="me-3" src="' +
            //         assetsPath +
            //         src +
            //         '" alt="' +
            //         name +
            //         '" height="32">' +
            //         '<div class="w-75">' +
            //         '<h6 class="mb-0">' +
            //         name +
            //         '</h6>' +
            //         '<small class="text-muted">' +
            //         subtitle +
            //         '</small>' +
            //         '</div>' +
            //         '</div>' +
            //         '<small class="text-muted">' +
            //         meta +
            //         '</small>' +
            //         '</a>'
            //       );
            //     },
            //     notFound:
            //       '<div class="not-found px-3 py-2">' +
            //       '<h6 class="suggestions-header text-primary mb-2">Files</h6>' +
            //       '<p class="py-2 mb-0"><i class="ti ti-alert-circle ti-xs me-2"></i> No Results Found</p>' +
            //       '</div>'
            //   }
            // },
            // Members
            {
              name: 'members',
              display: 'name',
              limit: 4,
              source: dynamicSearch('members'),
              templates: {
                header: '<h6 class="suggestions-header text-primary mb-0 mx-3 mt-3 pb-2">Contactos</h6>',
                suggestion: function (data) {
                  console.log('[Search] Rendering suggestion for:', data);
                  if (!data || !data.name) {
                    console.error('[Search] Invalid data for suggestion:', data);
                    return '';
                  }
                  var name = data.name || '';
                  var subtitle = data.subtitle || '';
                  var url = data.url || '#';
                  var html = '<a href="' + url + '">' +
                    '<div class="d-flex align-items-center">' +
                    '<i class="ti ti-user me-2"></i>' +
                    '<div class="user-info">' +
                    '<h6 class="mb-0">' + name + '</h6>' +
                    '<small class="text-muted">' + subtitle + '</small>' +
                    '</div>' +
                    '</div>' +
                    '</a>';
                  console.log('[Search] Generated HTML:', html);
                  return html;
                },
                notFound:
                  '<div class="not-found px-3 py-2">' +
                  '<h6 class="suggestions-header text-primary mb-2">Contactos</h6>' +
                  '<p class="py-2 mb-0"><i class="ti ti-alert-circle ti-xs me-2"></i> Contacto no encontrado</p>' +
                  '</div>'
              }
            },
            {
              name: 'enterprises',
              display: 'name',
              limit: 4,
              source: dynamicSearch('enterprises'),
              templates: {
                header: '<h6 class="suggestions-header text-primary mb-0 mx-3 mt-3 pb-2">Empresas</h6>',
                suggestion: function (data) {
                  if (!data || !data.name) return '';
                  var name = data.name || '';
                  var subtitle = data.subtitle || '';
                  var url = data.url || '#';
                  return (
                    '<a href="' +
                    url + '">' +
                    '<div class="d-flex align-items-center">' +
                    '<i class="ti ti-building me-2"></i>' +
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
                  '<h6 class="suggestions-header text-primary mb-2">Empresas</h6>' +
                  '<p class="py-2 mb-0"><i class="ti ti-alert-circle ti-xs me-2"></i> Empresa no encontrada</p>' +
                  '</div>'
              }
            },
            {
              name: 'services',
              display: 'name',
              limit: 4,
              source: dynamicSearch('services'),
              templates: {
                header: '<h6 class="suggestions-header text-primary mb-0 mx-3 mt-3 pb-2">Servicios</h6>',
                suggestion: function (data) {
                  if (!data || !data.name) return '';
                  var name = data.name || '';
                  var subtitle = data.subtitle || '';
                  var url = data.url || '#';
                  return (
                    '<a href="' +
                    url + '">' +
                    '<div class="d-flex align-items-center">' +
                    '<i class="ti ti-world me-2"></i>' +
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
                  '<h6 class="suggestions-header text-primary mb-2">Servicios</h6>' +
                  '<p class="py-2 mb-0"><i class="ti ti-alert-circle ti-xs me-2"></i> Servicio no encontrado</p>' +
                  '</div>'
              }
            },
            // Projects
            {
              name: 'projects',
              display: 'name',
              limit: 4,
              source: dynamicSearch('projects'),
              templates: {
                header: '<h6 class="suggestions-header text-primary mb-0 mx-3 mt-3 pb-2">Proyectos</h6>',
                suggestion: function (data) {
                  if (!data || !data.name) return '';
                  var name = data.name || '';
                  var subtitle = data.subtitle || '';
                  var url = data.url || '#';
                  return (
                    '<a href="' +
                    url + '">' +
                    '<div class="d-flex align-items-center">' +
                    '<i class="ti ti-folder me-2"></i>' +
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
                  '<h6 class="suggestions-header text-primary mb-2">Proyectos</h6>' +
                  '<p class="py-2 mb-0"><i class="ti ti-alert-circle ti-xs me-2"></i> Proyecto no encontrado</p>' +
                  '</div>'
              }
            },
            // Invoices
            {
              name: 'invoices',
              display: 'name',
              limit: 4,
              source: dynamicSearch('invoices'),
              templates: {
                header: '<h6 class="suggestions-header text-primary mb-0 mx-3 mt-3 pb-2">Facturas</h6>',
                suggestion: function (data) {
                  if (!data || !data.name) return '';
                  var name = data.name || '';
                  var subtitle = data.subtitle || '';
                  var url = data.url || '#';
                  return (
                    '<a href="' +
                    url + '">' +
                    '<div class="d-flex align-items-center">' +
                    '<i class="ti ti-file-invoice me-2"></i>' +
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
                  '<h6 class="suggestions-header text-primary mb-2">Facturas</h6>' +
                  '<p class="py-2 mb-0"><i class="ti ti-alert-circle ti-xs me-2"></i> Factura no encontrada</p>' +
                  '</div>'
              }
            },
            // DEBUG: Mirror server 'members' dataset with minimal markup
            {
              name: 'members-mirror',
              display: 'name',
              limit: 4,
              source: dynamicSearch('members'),
              templates: {
                header: '<h6 class="suggestions-header text-success mb-0 mx-3 mt-3 pb-2">Contactos (server espejo)</h6>',
                suggestion: function (data) {
                  if (!data || !data.name) return '';
                  var name = data.name || '';
                  var subtitle = data.subtitle || '';
                  var url = data.url || '#';
                  return (
                    '<a href="' + url + '">' +
                    '<div class="d-flex align-items-center">' +
                    '<i class="ti ti-user text-success me-2"></i>' +
                    '<div class="user-info">' +
                    '<h6 class="mb-0 text-success">' + name + '</h6>' +
                    '<small class="text-muted">' + subtitle + '</small>' +
                    '</div>' +
                    '</div>' +
                    '</a>'
                  );
                },
                notFound:
                  '<div class="not-found px-3 py-2">' +
                  '<h6 class="suggestions-header text-success mb-2">Contactos (server espejo)</h6>' +
                  '<p class="py-2 mb-0"><i class="ti ti-alert-circle ti-xs me-2"></i> Contacto no encontrado</p>' +
                  '</div>'
              }
            },
            // TEST HARDCODED - ELIMINAR DESPUÉS
            {
              name: 'test',
              display: 'name',
              limit: 2,
              source: function(q, cb) {
                console.log('[TEST] Hardcoded data being returned');
                cb([
                  {
                    name: '🔥 PRUEBA HARDCODED 1',
                    subtitle: 'Este es un resultado de prueba',
                    url: '#'
                  },
                  {
                    name: '✅ PRUEBA HARDCODED 2',
                    subtitle: 'Si ves esto, el template funciona correctamente',
                    url: '#'
                  }
                ]);
              },
              templates: {
                header: '<h6 class="suggestions-header text-danger mb-0 mx-3 mt-3 pb-2">⚠️ DATOS DE PRUEBA</h6>',
                suggestion: function (data) {
                  console.log('[TEST] Rendering hardcoded suggestion:', data);
                  if (!data || !data.name) return '';
                  var name = data.name || '';
                  var subtitle = data.subtitle || '';
                  var url = data.url || '#';
                  return (
                    '<a href="' +
                    url + '">' +
                    '<div class="d-flex align-items-center">' +
                    '<i class="ti ti-alert-triangle me-2 text-danger"></i>' +
                    '<div class="user-info">' +
                    '<h6 class="mb-0 text-danger">' +
                    name +
                    '</h6>' +
                    '<small class="text-muted">' +
                    subtitle +
                    '</small>' +
                    '</div>' +
                    '</div>' +
                    '</a>'
                  );
                }
              }
            }
          )
          //On typeahead result render.
          .bind('typeahead:render', function () {
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
