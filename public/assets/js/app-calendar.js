/**
 * App Calendar
 */

/**
 * ! If both start and end dates are same Full calendar will nullify the end date value.
 * ! Full calendar will end the event on a day before at 12:00:00AM thus, event won't extend to the end date.
 * ! We are getting events from a separate file named app-calendar-events.js. You can add or remove events from there.
 *
 **/

'use strict';

const calendarStrings = window.calendarStrings || {};
const calendarLocale = window.calendarLocale || 'es';

let direction = 'ltr';

if (isRtl) {
  direction = 'rtl';
}

if (typeof moment !== 'undefined' && moment.locale) {
  moment.locale(calendarLocale);
}

document.addEventListener('DOMContentLoaded', function () {
  (function () {
    const calendarEl = document.getElementById('calendar'),
      appCalendarSidebar = document.querySelector('.app-calendar-sidebar'),
      addEventSidebar = document.getElementById('addEventSidebar'),
      appOverlay = document.querySelector('.app-overlay'),
      calendarsColor = {
        Business: 'primary',
        Holiday: 'success',
        Personal: 'danger',
        Family: 'warning',
        ETC: 'info'
      },
      offcanvasTitle = document.querySelector('.offcanvas-title'),
      btnToggleSidebar = document.querySelector('.btn-toggle-sidebar'),
      btnSubmit = document.querySelector('button[type="submit"]'),
      btnDeleteEvent = document.querySelector('.btn-delete-event'),
      btnCancel = document.querySelector('.btn-cancel'),
      eventTitle = document.querySelector('#eventTitle'),
      eventStartDate = document.querySelector('#eventStartDate'),
      eventEndDate = document.querySelector('#eventEndDate'),
      eventUrl = document.querySelector('#eventURL'),
      eventLabel = $('#eventLabel'), // ! Using jquery vars due to select2 jQuery dependency
      eventGuests = $('#eventGuests'), // ! Using jquery vars due to select2 jQuery dependency
      eventLocation = document.querySelector('#eventLocation'),
      eventDescription = document.querySelector('#eventDescription'),
      allDaySwitch = document.querySelector('.allDay-switch'),
      selectAll = document.querySelector('.select-all'),
      filterInput = [].slice.call(document.querySelectorAll('.input-filter')),
      inlineCalendar = document.querySelector('.inline-calendar');

    let eventToUpdate,
      // Use API events when calendarEventsApiUrl is set (e.g. Google Calendar), otherwise use hardcoded events
      currentEvents = typeof window.calendarEventsApiUrl !== 'undefined' ? [] : events,
      isFormValid = false,
      submitRequested = false,
      inlineCalInstance;

    // Init event Offcanvas
    const bsAddEventSidebar = new bootstrap.Offcanvas(addEventSidebar);

    //! TODO: Update Event label and guest code to JS once select removes jQuery dependency
    // Event Label (select2)
    if (eventLabel.length) {
      function renderBadges(option) {
        if (!option.id) {
          return option.text;
        }
        var $badge =
          "<span class='badge badge-dot bg-" + $(option.element).data('label') + " me-2'> " + '</span>' + option.text;

        return $badge;
      }
      eventLabel.wrap('<div class="position-relative"></div>').select2({
        placeholder: calendarStrings.selectValue || 'Select value',
        dropdownParent: eventLabel.parent(),
        templateResult: renderBadges,
        templateSelection: renderBadges,
        minimumResultsForSearch: -1,
        escapeMarkup: function (es) {
          return es;
        }
      });
    }

    // Event Guests (select2)
    if (eventGuests.length) {
      function renderGuestAvatar(option) {
        if (!option.id) {
          return option.text;
        }
        var avatarUrl = $(option.element).data('avatar-url');
        var avatarHtml;
        if (avatarUrl) {
          avatarHtml = "<div class='avatar avatar-xs me-2'><img src=\"" + avatarUrl.replace(/"/g, '&quot;') + "\" alt='avatar' class='rounded-circle' /></div>";
        } else {
          var fallback = $(option.element).data('avatar') || '1.png';
          avatarHtml = "<div class='avatar avatar-xs me-2'><img src='" + assetsPath + "img/avatars/" + fallback + "' alt='avatar' class='rounded-circle' /></div>";
        }
        return "<div class='d-flex flex-wrap align-items-center'>" + avatarHtml + option.text + '</div>';
      }
      eventGuests.wrap('<div class="position-relative"></div>').select2({
        placeholder: calendarStrings.selectValue || 'Select value',
        dropdownParent: eventGuests.parent(),
        closeOnSelect: false,
        templateResult: renderGuestAvatar,
        templateSelection: renderGuestAvatar,
        escapeMarkup: function (es) {
          return es;
        }
      });
    }

    var flatpickrLocale = calendarLocale === 'es' && typeof flatpickr !== 'undefined' && flatpickr.l10ns && flatpickr.l10ns.es ? 'es' : undefined;

    // Event start (flatpicker)
    if (eventStartDate) {
      var start = eventStartDate.flatpickr({
        enableTime: true,
        dateFormat: 'Y-m-d H:i',
        locale: flatpickrLocale,
        onReady: function (selectedDates, dateStr, instance) {
          if (instance.isMobile) {
            instance.mobileInput.setAttribute('step', null);
          }
        }
      });
    }

    // Event end (flatpicker)
    if (eventEndDate) {
      var end = eventEndDate.flatpickr({
        enableTime: true,
        dateFormat: 'Y-m-d H:i',
        locale: flatpickrLocale,
        onReady: function (selectedDates, dateStr, instance) {
          if (instance.isMobile) {
            instance.mobileInput.setAttribute('step', null);
          }
        }
      });
    }

    function serializeCalendarDate(flatpickrInstance, isAllDay) {
      if (!flatpickrInstance) {
        return '';
      }
      var selected = flatpickrInstance.selectedDates;
      if (selected && selected.length > 0) {
        if (isAllDay) {
          return moment(selected[0]).format('YYYY-MM-DD');
        }
        return selected[0].toISOString();
      }
      var raw = flatpickrInstance.input ? flatpickrInstance.input.value : '';
      if (!raw) {
        return '';
      }
      if (isAllDay) {
        return moment(raw, ['YYYY-MM-DD', 'Y-m-d'], true).format('YYYY-MM-DD');
      }
      return moment(raw).toISOString();
    }

    function applyFlatpickrAllDayMode(isAllDay) {
      [start, end].forEach(function (flatpickrInstance) {
        if (!flatpickrInstance) {
          return;
        }
        flatpickrInstance.set('enableTime', !isAllDay);
        flatpickrInstance.set('dateFormat', isAllDay ? 'Y-m-d' : 'Y-m-d H:i');
      });
    }

    function readEventDateRangeFromForm() {
      var isAllDay = allDaySwitch && allDaySwitch.checked;
      var startValue = serializeCalendarDate(start, isAllDay);
      var endValue = serializeCalendarDate(end, isAllDay);

      if (isAllDay && startValue && endValue) {
        var startDay = moment(startValue, 'YYYY-MM-DD', true);
        var endDay = moment(endValue, 'YYYY-MM-DD', true);
        if (endDay.isValid() && startDay.isValid() && endDay.isBefore(startDay)) {
          endValue = startValue;
        }
      }

      return {
        start: startValue,
        end: endValue
      };
    }

    function flatpickrDateFromCalendarEvent(calendarEventDate, isAllDay, isExclusiveEnd) {
      if (!calendarEventDate) {
        return null;
      }
      var date = calendarEventDate instanceof Date ? moment(calendarEventDate) : moment(calendarEventDate);
      if (isAllDay && isExclusiveEnd) {
        date = date.clone().subtract(1, 'day');
      }
      return date.toDate();
    }

    function applyAllDayToggleToFlatpickr(isAllDay) {
      applyFlatpickrAllDayMode(isAllDay);

      var startMoment = start && start.selectedDates[0] ? moment(start.selectedDates[0]) : moment();
      var endMoment = end && end.selectedDates[0] ? moment(end.selectedDates[0]) : startMoment.clone();

      if (isAllDay) {
        if (start) {
          start.setDate(startMoment.clone().startOf('day').toDate(), true);
        }
        if (end) {
          end.setDate(endMoment.clone().startOf('day').toDate(), true);
        }
        return;
      }

      var timedStart = startMoment.clone().hour(9).minute(0).second(0).millisecond(0);
      var timedEnd = endMoment.clone().hour(10).minute(0).second(0).millisecond(0);
      if (!timedEnd.isAfter(timedStart)) {
        timedEnd = timedStart.clone().add(1, 'hour');
      }
      if (start) {
        start.setDate(timedStart.toDate(), true);
      }
      if (end) {
        end.setDate(timedEnd.toDate(), true);
      }
    }

    if (allDaySwitch) {
      allDaySwitch.addEventListener('change', function () {
        applyAllDayToggleToFlatpickr(allDaySwitch.checked);
      });
    }

    // Inline sidebar calendar (flatpickr) - week starts Monday in Spanish
    if (inlineCalendar) {
      inlineCalInstance = inlineCalendar.flatpickr({
        monthSelectorType: 'static',
        inline: true,
        locale: flatpickrLocale,
        firstDayOfWeek: 1
      });
    }

    // Event click function
    function eventClick(info) {
      info.jsEvent.preventDefault();
      eventToUpdate = info.event;
      bsAddEventSidebar.show();
      // For update event set offcanvas title text
      if (offcanvasTitle) {
        offcanvasTitle.innerHTML = calendarStrings.updateEvent || 'Update Event';
      }
      btnSubmit.innerHTML = calendarStrings.update || 'Update';
      btnSubmit.classList.add('btn-update-event');
      btnSubmit.classList.remove('btn-add-event');
      btnDeleteEvent.classList.remove('d-none');

      eventTitle.value = eventToUpdate.title || '';
      eventUrl.value = eventToUpdate.url || '';
      var isAllDayEvent = eventToUpdate.allDay === true;
      allDaySwitch.checked = isAllDayEvent;
      applyFlatpickrAllDayMode(isAllDayEvent);
      start.setDate(flatpickrDateFromCalendarEvent(eventToUpdate.start, isAllDayEvent, false), true);
      if (eventToUpdate.end !== null) {
        end.setDate(flatpickrDateFromCalendarEvent(eventToUpdate.end, isAllDayEvent, isAllDayEvent), true);
      } else {
        end.setDate(flatpickrDateFromCalendarEvent(eventToUpdate.start, isAllDayEvent, false), true);
      }
      eventLabel.val(eventToUpdate.extendedProps && eventToUpdate.extendedProps.calendar ? eventToUpdate.extendedProps.calendar : 'Business').trigger('change');
      eventLocation.value = (eventToUpdate.extendedProps && eventToUpdate.extendedProps.location !== undefined) ? eventToUpdate.extendedProps.location : '';
      eventToUpdate.extendedProps && eventToUpdate.extendedProps.guests !== undefined
        ? eventGuests.val(eventToUpdate.extendedProps.guests).trigger('change')
        : eventGuests.val([]).trigger('change');
      eventDescription.value = (eventToUpdate.extendedProps && eventToUpdate.extendedProps.description !== undefined) ? eventToUpdate.extendedProps.description : '';

      // // Call removeEvent function
      // btnDeleteEvent.addEventListener('click', e => {
      //   removeEvent(parseInt(eventToUpdate.id));
      //   // eventToUpdate.remove();
      //   bsAddEventSidebar.hide();
      // });
    }

    // Modify sidebar toggler
    function modifyToggler() {
      const fcSidebarToggleButton = document.querySelector('.fc-sidebarToggle-button');
      fcSidebarToggleButton.classList.remove('fc-button-primary');
      fcSidebarToggleButton.classList.add('d-lg-none', 'd-inline-block', 'ps-0');
      while (fcSidebarToggleButton.firstChild) {
        fcSidebarToggleButton.firstChild.remove();
      }
      fcSidebarToggleButton.setAttribute('data-bs-toggle', 'sidebar');
      fcSidebarToggleButton.setAttribute('data-overlay', '');
      fcSidebarToggleButton.setAttribute('data-target', '#app-calendar-sidebar');
      fcSidebarToggleButton.insertAdjacentHTML('beforeend', '<i class="ti ti-menu-2 ti-sm text-heading"></i>');
    }

    // Filter events by calender
    function selectedCalendars() {
      let selected = [],
        filterInputChecked = [].slice.call(document.querySelectorAll('.input-filter:checked'));

      filterInputChecked.forEach(item => {
        selected.push(item.getAttribute('data-value'));
      });

      return selected;
    }

    // --------------------------------------------------------------------------------------------------
    // fetchEvents
    // * When calendarEventsApiUrl is set (Google Calendar), fetch from API. Otherwise use local events.
    // --------------------------------------------------------------------------------------------------
    function fetchEvents(info, successCallback, failureCallback) {
      if (typeof window.calendarEventsApiUrl !== 'undefined' && window.calendarEventsApiUrl) {
        const url = `${window.calendarEventsApiUrl}?start=${encodeURIComponent(info.startStr)}&end=${encodeURIComponent(info.endStr)}`;
        fetch(url, {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin'
        })
          .then((response) => {
            if (!response.ok) {
              console.warn('Calendar API error:', response.status, response.statusText);
              successCallback([...currentEvents]);
              return null;
            }
            return response.json();
          })
          .then((apiEvents) => {
            if (apiEvents === null) return;
            if (!Array.isArray(apiEvents)) {
              if (apiEvents && apiEvents.error) {
                console.warn('Calendar API error:', apiEvents.error);
              }
              successCallback([...currentEvents]);
              return;
            }
            const merged = [...currentEvents];
            apiEvents.forEach((ev) => {
              const fcEv = {
                id: ev.id,
                title: ev.title,
                start: ev.start,
                end: ev.end,
                allDay: ev.allDay || false,
                url: ev.url || '',
                extendedProps: {
                  calendar: ev.extendedProps?.calendar || 'Business',
                  location: ev.extendedProps?.location || '',
                  description: ev.extendedProps?.description || '',
                  guests: Array.isArray(ev.extendedProps?.guests) ? ev.extendedProps.guests : []
                }
              };
              const idx = merged.findIndex((e) => String(e.id) === String(ev.id));
              if (idx >= 0) {
                merged[idx] = fcEv;
              } else {
                merged.push(fcEv);
              }
            });
            successCallback(merged);
          })
          .catch((err) => {
            console.error('Calendar API error:', err);
            if (typeof failureCallback === 'function') {
              failureCallback(err);
            }
            successCallback([...currentEvents]);
          });
        return;
      }

      // Local events: filter by selected calendars
      let calendars = selectedCalendars();
      let selectedEvents = currentEvents.filter(function (event) {
        const cal = event.extendedProps && event.extendedProps.calendar;
        return cal && calendars.includes(cal.toLowerCase());
      });
      successCallback(selectedEvents);
    }

    var titleUpdateTimeoutId = null;

    function scheduleTitleAndLabelsUpdate() {
      if (titleUpdateTimeoutId) clearTimeout(titleUpdateTimeoutId);
      titleUpdateTimeoutId = setTimeout(function () {
        titleUpdateTimeoutId = null;
        applyToolbarTitleCapitalization();
      }, 50);
    }

    function capitalizeFirstLetter(text) {
      if (!text) {
        return text;
      }
      var first = text.charAt(0);
      if (first === first.toUpperCase()) {
        return text;
      }
      return first.toUpperCase() + text.slice(1);
    }

    function formatDayHeaderContent(arg) {
      var text;
      if (arg.view.type === 'timeGridWeek') {
        text = moment(arg.date).format('ddd D/M');
      } else if (arg.view.type === 'timeGridDay') {
        text = moment(arg.date).format('dddd D/M');
      } else {
        text = arg.text;
      }
      return capitalizeFirstLetter(text);
    }

    function applyToolbarTitleCapitalization() {
      var titleEl = calendarEl.querySelector('.fc-toolbar-title');
      if (titleEl && typeof calendar !== 'undefined' && calendar.view && calendar.view.title) {
        var apiTitle = calendar.view.title;
        if (apiTitle) {
          titleEl.textContent = capitalizeFirstLetter(apiTitle);
        }
      }
    }

    // Init FullCalendar
    // ------------------------------------------------
    const initialView = typeof window.calendarInitialView !== 'undefined' ? window.calendarInitialView : 'dayGridMonth';
    let calendar = new Calendar(calendarEl, {
      initialView: initialView,
      events: fetchEvents,
      plugins: [dayGridPlugin, interactionPlugin, listPlugin, timegridPlugin],
      editable: true,
      dragScroll: true,
      dayMaxEvents: 2,
      eventResizableFromStart: true,
      customButtons: {
        sidebarToggle: {
          text: calendarStrings.sidebar || 'Sidebar'
        }
      },
      headerToolbar: {
        start: 'sidebarToggle, prev,next, title',
        end: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
      },
      buttonText: {
        today: calendarStrings.fcToday || 'Today',
        month: calendarStrings.fcMonth || 'Month',
        week: calendarStrings.fcWeek || 'Week',
        day: calendarStrings.fcDay || 'Day',
        list: calendarStrings.fcList || 'List'
      },
      allDayContent: function (arg) {
        return calendarStrings.fcAllDay || 'all-day';
      },
      dayHeaderContent: formatDayHeaderContent,
      firstDay: 1,
      direction: direction,
      locale: calendarLocale,
      timeZone: 'local',
      initialDate: new Date(),
      navLinks: true,
      eventClassNames: function ({ event: calendarEvent }) {
        const colorName = calendarsColor[calendarEvent._def.extendedProps.calendar];
        // Background Color
        return ['fc-event-' + colorName];
      },
      dateClick: function (info) {
        let date = moment(info.date).format('YYYY-MM-DD');
        resetValues();
        bsAddEventSidebar.show();

        // For new event set offcanvas title text
        if (offcanvasTitle) {
          offcanvasTitle.innerHTML = calendarStrings.addEvent || 'Add Event';
        }
        btnSubmit.innerHTML = calendarStrings.add || 'Add';
        btnSubmit.classList.remove('btn-update-event');
        btnSubmit.classList.add('btn-add-event');
        btnDeleteEvent.classList.add('d-none');
        eventStartDate.value = date;
        eventEndDate.value = date;
      },
      eventClick: function (info) {
        eventClick(info);
      },
      datesSet: function () {
        modifyToggler();
        translateFcButtonTitles();
        scheduleTitleAndLabelsUpdate();
      },
      viewDidMount: function () {
        modifyToggler();
        translateFcButtonTitles();
        scheduleTitleAndLabelsUpdate();
      }
    });

    function translateFcButtonTitles() {
      var btnTitles = {
        'fc-dayGridMonth-button': calendarStrings.fcMonthTitle || 'month view',
        'fc-timeGridWeek-button': calendarStrings.fcWeekTitle || 'week view',
        'fc-timeGridDay-button': calendarStrings.fcDayTitle || 'day view',
        'fc-listMonth-button': calendarStrings.fcListTitle || 'list view'
      };
      Object.keys(btnTitles).forEach(function (cls) {
        var btn = calendarEl.querySelector('.fc-toolbar-chunk:last-child .' + cls);
        if (btn) {
          btn.setAttribute('title', btnTitles[cls]);
        }
      });
    }

    // Render calendar
    calendar.render();
    // Expose calendar instance globally for Google sync and other integrations
    window.calendar = calendar;
    // Modify sidebar toggler
    modifyToggler();

    const eventForm = document.getElementById('eventForm');
    const fv = FormValidation.formValidation(eventForm, {
      fields: {
        eventTitle: {
          validators: {
            notEmpty: {
              message: calendarStrings.pleaseEnterEventTitle || 'Please enter event title '
            }
          }
        },
        eventStartDate: {
          validators: {
            notEmpty: {
              message: calendarStrings.pleaseEnterStartDate || 'Please enter start date '
            }
          }
        },
        eventEndDate: {
          validators: {
            notEmpty: {
              message: calendarStrings.pleaseEnterEndDate || 'Please enter end date '
            }
          }
        }
      },
      plugins: {
        trigger: new FormValidation.plugins.Trigger(),
        bootstrap5: new FormValidation.plugins.Bootstrap5({
          // Use this for enabling/changing valid/invalid class
          eleValidClass: '',
          rowSelector: function (field, ele) {
            // field is the field name & ele is the field element
            return '.mb-3';
          }
        }),
        submitButton: new FormValidation.plugins.SubmitButton(),
        // Submit the form when all fields are valid
        // defaultSubmit: new FormValidation.plugins.DefaultSubmit(),
        autoFocus: new FormValidation.plugins.AutoFocus()
      }
    })
      .on('core.form.valid', function () {
        isFormValid = true;
        if (!submitRequested) return;
        submitRequested = false;
        if (btnSubmit.classList.contains('btn-add-event')) {
          const eventDates = readEventDateRangeFromForm();
          const newEvent = {
            id: calendar.getEvents().length + 1,
            title: eventTitle.value,
            start: eventDates.start,
            end: eventDates.end,
            startStr: eventDates.start,
            endStr: eventDates.end,
            display: 'block',
            extendedProps: {
              location: eventLocation.value,
              guests: eventGuests.val(),
              calendar: eventLabel.val(),
              description: eventDescription.value
            }
          };
          if (eventUrl.value) newEvent.url = eventUrl.value;
          newEvent.allDay = allDaySwitch.checked;
          addEvent(newEvent);
        } else {
          const eventDates = readEventDateRangeFromForm();
          const eventData = {
            id: eventToUpdate.id,
            title: eventTitle.value,
            start: eventDates.start,
            end: eventDates.end,
            url: eventUrl.value,
            extendedProps: {
              location: eventLocation.value,
              guests: eventGuests.val(),
              calendar: eventLabel.val(),
              description: eventDescription.value
            },
            display: 'block',
            allDay: allDaySwitch.checked
          };
          updateEvent(eventData);
        }
        bsAddEventSidebar.hide();
      })
      .on('core.form.invalid', function () {
        isFormValid = false;
      });

    // Sidebar Toggle Btn
    if (btnToggleSidebar) {
      btnToggleSidebar.addEventListener('click', e => {
        btnCancel.classList.remove('d-none');
      });
    }

    function calendarApiBaseUrl() {
      if (typeof window.calendarEventsApiUrl === 'undefined' || !window.calendarEventsApiUrl) return null;
      return String(window.calendarEventsApiUrl).split('?')[0];
    }

    function calendarApiFetch(method, url, body) {
      const opts = {
        method,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        credentials: 'same-origin'
      };
      const csrf = document.querySelector('meta[name="csrf-token"]');
      if (csrf && csrf.getAttribute('content')) {
        opts.headers['X-CSRF-TOKEN'] = csrf.getAttribute('content');
      }
      if (body && method !== 'GET') opts.body = JSON.stringify(body);
      return fetch(url, opts);
    }

    // Add Event
    // ------------------------------------------------
    function addEvent(eventData) {
      const base = calendarApiBaseUrl();
      if (base) {
        calendarApiFetch('POST', base, {
          title: eventData.title,
          start: eventData.start,
          end: eventData.end,
          all_day: Boolean(eventData.allDay),
          url: eventData.url || '',
          label: (eventData.extendedProps && eventData.extendedProps.calendar) || 'Business',
          location: (eventData.extendedProps && eventData.extendedProps.location) || '',
          description: (eventData.extendedProps && eventData.extendedProps.description) || '',
          guests: (eventData.extendedProps && eventData.extendedProps.guests) || []
        }).then(function (res) {
          if (res.ok) calendar.refetchEvents();
        }).catch(function () { calendar.refetchEvents(); });
        return;
      }
      currentEvents.push(eventData);
      calendar.refetchEvents();
    }

    // Update Event
    // ------------------------------------------------
    function updateEvent(eventData) {
      eventData.id = parseInt(eventData.id, 10);
      const base = calendarApiBaseUrl();
      if (base && eventData.id > 0 && !isNaN(eventData.id)) {
        calendarApiFetch('PUT', base + '/' + eventData.id, {
          title: eventData.title,
          start: eventData.start,
          end: eventData.end,
          all_day: Boolean(eventData.allDay),
          url: eventData.url || '',
          label: (eventData.extendedProps && eventData.extendedProps.calendar) || 'Business',
          location: (eventData.extendedProps && eventData.extendedProps.location) || '',
          description: (eventData.extendedProps && eventData.extendedProps.description) || '',
          guests: (eventData.extendedProps && eventData.extendedProps.guests) || []
        }).then(function (res) {
          if (res.ok) calendar.refetchEvents();
        }).catch(function () { calendar.refetchEvents(); });
        return;
      }
      currentEvents[currentEvents.findIndex(el => el.id === eventData.id)] = eventData;
      calendar.refetchEvents();
    }

    // Remove Event
    // ------------------------------------------------
    function removeEvent(eventId) {
      const id = parseInt(eventId, 10);
      const base = calendarApiBaseUrl();
      if (base && id > 0 && !isNaN(id)) {
        calendarApiFetch('DELETE', base + '/' + id).then(function (res) {
          if (res.ok) calendar.refetchEvents();
        }).catch(function () { calendar.refetchEvents(); });
        return;
      }
      currentEvents = currentEvents.filter(function (event) {
        return event.id != eventId;
      });
      calendar.refetchEvents();
    }

    // (Update Event In Calendar (UI Only)
    // ------------------------------------------------
    const updateEventInCalendar = (updatedEventData, propsToUpdate, extendedPropsToUpdate) => {
      const existingEvent = calendar.getEventById(updatedEventData.id);

      // --- Set event properties except date related ----- //
      // ? Docs: https://fullcalendar.io/docs/Event-setProp
      // dateRelatedProps => ['start', 'end', 'allDay']
      // eslint-disable-next-line no-plusplus
      for (var index = 0; index < propsToUpdate.length; index++) {
        var propName = propsToUpdate[index];
        existingEvent.setProp(propName, updatedEventData[propName]);
      }

      // --- Set date related props ----- //
      // ? Docs: https://fullcalendar.io/docs/Event-setDates
      existingEvent.setDates(updatedEventData.start, updatedEventData.end, {
        allDay: updatedEventData.allDay
      });

      // --- Set event's extendedProps ----- //
      // ? Docs: https://fullcalendar.io/docs/Event-setExtendedProp
      // eslint-disable-next-line no-plusplus
      for (var index = 0; index < extendedPropsToUpdate.length; index++) {
        var propName = extendedPropsToUpdate[index];
        existingEvent.setExtendedProp(propName, updatedEventData.extendedProps[propName]);
      }
    };

    // Remove Event In Calendar (UI Only)
    // ------------------------------------------------
    function removeEventInCalendar(eventId) {
      calendar.getEventById(eventId).remove();
    }

    // Add / Update event: request validation on submit button click; add/update runs in core.form.valid
    // ------------------------------------------------
    btnSubmit.addEventListener('click', function (e) {
      e.preventDefault();
      submitRequested = true;
      fv.validate();
    });

    function confirmDeleteEvent() {
      if (!eventToUpdate || !eventToUpdate.id) {
        return;
      }
      removeEvent(parseInt(eventToUpdate.id, 10));
      bsAddEventSidebar.hide();
    }

    // Call removeEvent function
    if (btnDeleteEvent) {
      btnDeleteEvent.addEventListener('click', function (e) {
        e.preventDefault();
        if (!eventToUpdate || !eventToUpdate.id) {
          return;
        }

        if (typeof Swal === 'undefined') {
          if (window.confirm(calendarStrings.deleteConfirmText || 'Are you sure you want to delete this record?')) {
            confirmDeleteEvent();
          }
          return;
        }

        Swal.fire({
          title: calendarStrings.deleteConfirmTitle || 'Are you sure?',
          text: calendarStrings.deleteConfirmText || 'Are you sure you want to delete this record?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: calendarStrings.deleteConfirmYes || 'Yes, delete',
          cancelButtonText: calendarStrings.cancel || 'Cancel',
          customClass: {
            confirmButton: 'btn btn-primary me-3',
            cancelButton: 'btn btn-label-secondary'
          },
          buttonsStyling: false
        }).then(function (result) {
          if (result.isConfirmed) {
            confirmDeleteEvent();
          }
        });
      });
    }

    // Reset event form inputs values
    // ------------------------------------------------
    function resetValues() {
      eventEndDate.value = '';
      eventUrl.value = '';
      eventStartDate.value = '';
      eventTitle.value = '';
      eventLocation.value = '';
      allDaySwitch.checked = false;
      applyFlatpickrAllDayMode(false);
      eventGuests.val('').trigger('change');
      eventDescription.value = '';
    }

    // When modal hides reset input values
    addEventSidebar.addEventListener('hidden.bs.offcanvas', function () {
      resetValues();
    });

    // Hide left sidebar if the right sidebar is open
    btnToggleSidebar.addEventListener('click', e => {
      if (offcanvasTitle) {
        offcanvasTitle.innerHTML = calendarStrings.addEvent || 'Add Event';
      }
      btnSubmit.innerHTML = calendarStrings.add || 'Add';
      btnSubmit.classList.remove('btn-update-event');
      btnSubmit.classList.add('btn-add-event');
      btnDeleteEvent.classList.add('d-none');
      appCalendarSidebar.classList.remove('show');
      appOverlay.classList.remove('show');
    });

    // Calender filter functionality
    // ------------------------------------------------
    if (selectAll) {
      selectAll.addEventListener('click', e => {
        if (e.currentTarget.checked) {
          document.querySelectorAll('.input-filter').forEach(c => (c.checked = 1));
        } else {
          document.querySelectorAll('.input-filter').forEach(c => (c.checked = 0));
        }
        calendar.refetchEvents();
      });
    }

    if (filterInput) {
      filterInput.forEach(item => {
        item.addEventListener('click', () => {
          document.querySelectorAll('.input-filter:checked').length < document.querySelectorAll('.input-filter').length
            ? (selectAll.checked = false)
            : (selectAll.checked = true);
          calendar.refetchEvents();
        });
      });
    }

    // Jump to date on sidebar(inline) calendar change
    inlineCalInstance.config.onChange.push(function (date) {
      calendar.changeView(calendar.view.type, moment(date[0]).format('YYYY-MM-DD'));
      modifyToggler();
      appCalendarSidebar.classList.remove('show');
      appOverlay.classList.remove('show');
    });
  })();
});
