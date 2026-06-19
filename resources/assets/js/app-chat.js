/**
 * App Chat
 */

'use strict';

(function bindChatWhatsAppSidebarControls() {
  const avatar = document.getElementById('chat-contacts-wa-avatar');
  const sidebar = document.getElementById('app-chat-sidebar-left');
  const overlay = document.querySelector('.app-overlay');
  const closeButtons = sidebar ? sidebar.querySelectorAll('.close-sidebar') : [];

  if (!sidebar || sidebar.dataset.chatWaSidebarBound === '1') {
    return;
  }

  sidebar.dataset.chatWaSidebarBound = '1';
  if (avatar) {
    avatar.dataset.chatWaSidebarBound = '1';
  }

  function syncOverlay(show) {
    if (!overlay) {
      return;
    }

    if (show) {
      overlay.classList.add('show');
      overlay.onclick = function (e) {
        e.currentTarget.classList.remove('show');
        sidebar.classList.remove('show');
      };
    } else {
      overlay.classList.remove('show');
      overlay.onclick = null;
    }
  }

  function openWhatsAppSidebar() {
    sidebar.classList.add('show');
    syncOverlay(true);
  }

  function closeWhatsAppSidebar() {
    sidebar.classList.remove('show');
    syncOverlay(false);
  }

  function toggleWhatsAppSidebar() {
    if (sidebar.classList.contains('show')) {
      closeWhatsAppSidebar();
    } else {
      openWhatsAppSidebar();
    }
  }

  if (avatar) {
    avatar.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopImmediatePropagation();
      toggleWhatsAppSidebar();
    }, true);

    avatar.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        toggleWhatsAppSidebar();
      }
    });
  }

  closeButtons.forEach(function (closeButton) {
    closeButton.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopImmediatePropagation();
      closeWhatsAppSidebar();
    }, true);
  });
})();

document.addEventListener('DOMContentLoaded', function () {
  (function () {
    const chatContactsBody = document.querySelector('.app-chat-contacts .sidebar-body'),
      chatContactListItems = [].slice.call(
        document.querySelectorAll('.chat-contact-list-item:not(.chat-contact-list-item-title)')
      ),
      chatHistoryBody = document.querySelector('.chat-history-body'),
      chatSidebarLeftBody = document.querySelector('.app-chat-sidebar-left .sidebar-body'),
      chatSidebarRightBody = document.querySelector('.app-chat-sidebar-right .sidebar-body'),
      chatUserStatus = [].slice.call(document.querySelectorAll(".form-check-input[name='chat-user-status']")),
      chatSidebarLeftUserAbout = $('.chat-sidebar-left-user-about'),
      formSendMessage = document.querySelector('.form-send-message'),
      messageInput = document.querySelector('.message-input'),
      searchInput = document.querySelector('.chat-search-input'),
      speechToText = $('.speech-to-text'), // ! jQuery dependency for speech to text
      userStatusObj = {
        active: 'avatar-online',
        offline: 'avatar-offline',
        away: 'avatar-away',
        busy: 'avatar-busy'
      };

    // Initialize PerfectScrollbar
    // ------------------------------

    // Chat contacts scrollbar
    if (chatContactsBody) {
      new PerfectScrollbar(chatContactsBody, {
        wheelPropagation: false,
        suppressScrollX: true
      });
    }

    // Chat history scrollbar
    if (chatHistoryBody) {
      new PerfectScrollbar(chatHistoryBody, {
        wheelPropagation: false,
        suppressScrollX: true
      });
    }

    // Sidebar left scrollbar
    if (chatSidebarLeftBody) {
      new PerfectScrollbar(chatSidebarLeftBody, {
        wheelPropagation: false,
        suppressScrollX: true
      });
    }

    // Sidebar right scrollbar
    if (chatSidebarRightBody) {
      new PerfectScrollbar(chatSidebarRightBody, {
        wheelPropagation: false,
        suppressScrollX: true
      });
    }

    // Scroll to bottom function
    function scrollToBottom() {
      if (!chatHistoryBody) {
        return;
      }

      chatHistoryBody.scrollTo(0, chatHistoryBody.scrollHeight);
    }
    scrollToBottom();

    // User About Maxlength Init
    if (chatSidebarLeftUserAbout.length) {
      chatSidebarLeftUserAbout.maxlength({
        alwaysShow: true,
        warningClass: 'label label-success bg-success text-white',
        limitReachedClass: 'label label-danger',
        separator: '/',
        validate: true,
        threshold: 120
      });
    }

    // Update user status
    chatUserStatus.forEach(el => {
      el.addEventListener('click', e => {
        let chatLeftSidebarUserAvatar = document.querySelector('.chat-sidebar-left-user .avatar'),
          value = e.currentTarget.value;
        //Update status in left sidebar user avatar
        chatLeftSidebarUserAvatar.removeAttribute('class');
        Helpers._addClass('avatar avatar-xl ' + userStatusObj[value] + '', chatLeftSidebarUserAvatar);
        //Update status in contacts sidebar user avatar
        let chatContactsUserAvatar = document.querySelector('.app-chat-contacts .avatar');
        chatContactsUserAvatar.removeAttribute('class');
        Helpers._addClass('flex-shrink-0 avatar ' + userStatusObj[value] + ' me-3', chatContactsUserAvatar);
      });
    });

    // Select chat or contact
    chatContactListItems.forEach(chatContactListItem => {
      // Bind click event to each chat contact list item
      chatContactListItem.addEventListener('click', e => {
        // Remove active class from chat contact list item
        chatContactListItems.forEach(chatContactListItem => {
          chatContactListItem.classList.remove('active');
        });
        // Add active class to current chat contact list item
        e.currentTarget.classList.add('active');
      });
    });

    function setChatListItemVisible(listItem, visible) {
      if (!listItem) {
        return;
      }

      if (visible) {
        listItem.classList.remove('d-none');
      } else {
        listItem.classList.add('d-none');
      }
    }

    function syncWhatsAppSectionSearchVisibility(searchValue) {
      const whatsappSection = document.getElementById('whatsapp-conversations-section');
      if (!whatsappSection) {
        return;
      }

      if (!searchValue) {
        const showWhatsAppToggle = document.getElementById('sidebar-show-whatsapp-conversations-toggle');
        if (showWhatsAppToggle) {
          whatsappSection.classList.toggle('d-none', !showWhatsAppToggle.checked);
        } else {
          whatsappSection.classList.remove('d-none');
        }

        return;
      }

      const visibleItems = whatsappSection.querySelectorAll(
        '#chat-list-whatsapp li.chat-contact-list-item:not(.d-none):not(.chat-list-item-0)'
      );
      whatsappSection.classList.toggle('d-none', visibleItems.length === 0);
    }

    // Search chat and contacts function
    function searchChatContacts(searchListItems, searchValue, listItem0) {
      let matchedCount = 0;

      searchListItems.forEach(searchListItem => {
        const searchListItemText = searchListItem.textContent.toLowerCase();
        const matches = searchValue === '' || searchListItemText.includes(searchValue);

        setChatListItemVisible(searchListItem, matches);
        if (matches) {
          matchedCount++;
        }
      });

      if (!listItem0) {
        return matchedCount;
      }

      setChatListItemVisible(listItem0, searchValue !== '' && matchedCount === 0);

      return matchedCount;
    }

    function applyChatSidebarSearch() {
      const searchInputEl = document.querySelector('.chat-search-input');
      const searchValue = searchInputEl ? searchInputEl.value.trim().toLowerCase() : '';
      const chatListItem0 = document.querySelector('#chat-list .chat-list-item-0');
      const contactListItem0 = document.querySelector('#contact-list .contact-list-item-0');
      const searchChatListItems = [].slice.call(
        document.querySelectorAll(
          '#chat-list li:not(.chat-contact-list-item-title), #chat-list-assistant-clients li:not(.chat-contact-list-item-title)'
        )
      );
      const searchContactListItems = [].slice.call(
        document.querySelectorAll('#contact-list li:not(.chat-contact-list-item-title)')
      );
      const searchWhatsAppListItems = [].slice.call(
        document.querySelectorAll('#chat-list-whatsapp li:not(.chat-contact-list-item-title):not(.chat-list-item-0)')
      );

      searchChatContacts(searchChatListItems, searchValue, chatListItem0);
      searchChatContacts(searchContactListItems, searchValue, contactListItem0);
      searchChatContacts(searchWhatsAppListItems, searchValue, null);
      syncWhatsAppSectionSearchVisibility(searchValue);
    }

    window.applyChatSidebarSearch = applyChatSidebarSearch;

    // Filter Chats
    if (searchInput) {
      searchInput.addEventListener('input', applyChatSidebarSearch);
    }

    // Send Message
    if (formSendMessage) {
    formSendMessage.addEventListener('submit', e => {
      e.preventDefault();
      if (messageInput.value) {
        const message = messageInput.value;
        const to = document.getElementById('recipient').value;
        
        // Parte original: Actualizar la UI
        let renderMsg = document.createElement('div');
        renderMsg.className = 'chat-message-text mt-2';
        renderMsg.innerHTML = '<p class="mb-0 text-break">' + message + '</p>';
        document.querySelector('li:last-child .chat-message-wrapper').appendChild(renderMsg);
        messageInput.value = '';
        scrollToBottom();
        
        // Parte nueva: Enviar al servidor (incluye contact_id para persistir en contexto asistente)
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const cleanTo = to.replace('whatsapp:', ''); // Quitar prefijo si existe
        const contactIdEl = document.getElementById('contact-id');
        const contactId = contactIdEl && contactIdEl.value ? parseInt(contactIdEl.value, 10) : undefined;
        const body = { to: cleanTo, message: message, use_ai: false };
        if (contactId) body.contact_id = contactId;

        fetch('/chat/send', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token
          },
          body: JSON.stringify(body)
        })
        .then(response => {
          if (!response.ok) {
            throw new Error('Error en la respuesta: ' + response.status);
          }
          return response.json();
        })
        .then(data => {
          console.log('Mensaje enviado correctamente:', data);
          if (window.refreshAssistantHistory) window.refreshAssistantHistory();
        })
        .catch(error => {
          console.error('Error enviando mensaje:', error);
          // Puedes mostrar un mensaje de error si quieres
        });
      }
    });
    }

    // on click of chatHistoryHeaderMenu, Remove data-overlay attribute from chatSidebarLeftClose to resolve overlay overlapping issue for two sidebar
    let chatHistoryHeaderMenu = document.querySelector(".chat-history-header [data-target='#app-chat-contacts']"),
      chatSidebarLeftClose = document.querySelector('.app-chat-sidebar-left .close-sidebar');
    if (chatHistoryHeaderMenu && chatSidebarLeftClose) {
      chatHistoryHeaderMenu.addEventListener('click', e => {
        chatSidebarLeftClose.removeAttribute('data-overlay');
      });
    }
    // }

    // Speech To Text
    if (speechToText.length) {
      var SpeechRecognition = SpeechRecognition || webkitSpeechRecognition;
      if (SpeechRecognition !== undefined && SpeechRecognition !== null) {
        var recognition = new SpeechRecognition(),
          listening = false;
        speechToText.on('click', function () {
          const $this = $(this);
          recognition.onspeechstart = function () {
            listening = true;
          };
          if (listening === false) {
            recognition.start();
          }
          recognition.onerror = function (event) {
            listening = false;
          };
          recognition.onresult = function (event) {
            $this.closest('.form-send-message').find('.message-input').val(event.results[0][0].transcript);
          };
          recognition.onspeechend = function (event) {
            listening = false;
            recognition.stop();
          };
        });
      }
    }
  })();
});

/* ===== CÓDIGO DE DIAGNÓSTICO (comentado) =====
document.addEventListener('DOMContentLoaded', function() {
  // Esperar a que todo esté completamente cargado
  setTimeout(function() {
    // Obtener los elementos relevantes
    const formSendMessage = document.querySelector('.form-send-message');
    
    if (formSendMessage) {
      // Agregar un controlador de eventos adicional al formulario
      formSendMessage.addEventListener('submit', function(e) {
        // Obtener todos los datos importantes
        const messageInput = document.querySelector('.message-input');
        const recipientInput = document.getElementById('recipient');

        // Recopilar información para depuración
        const debugInfo = {
          formFound: !!formSendMessage,
          messageInputFound: !!messageInput,
          recipientInputFound: !!recipientInput,
          messageValue: messageInput ? messageInput.value : 'No disponible',
          recipientValue: recipientInput ? recipientInput.value : 'No disponible',
          csrfMeta: document.querySelector('meta[name="csrf-token"]') ? 'Presente' : 'No encontrado',
          csrfMetaContent: document.querySelector('meta[name="csrf-token"]') ? 
                           document.querySelector('meta[name="csrf-token"]').getAttribute('content') : 'No disponible',
          csrfToken: '{{ csrf_token() }}',
          ajaxRoute: '{{ route("chat.send") }}'
        };

        // Mostrar información de depuración
        alert(
          'DIAGNÓSTICO DEL FORMULARIO:\n\n' +
          'Formulario encontrado: ' + debugInfo.formFound + '\n' +
          'Campo de mensaje encontrado: ' + debugInfo.messageInputFound + '\n' +
          'Campo de destinatario encontrado: ' + debugInfo.recipientInputFound + '\n' +
          'Valor del mensaje: ' + debugInfo.messageValue + '\n' +
          'Valor del destinatario: ' + debugInfo.recipientValue + '\n\n' +
          'INFORMACIÓN CSRF:\n' +
          'Meta tag CSRF: ' + debugInfo.csrfMeta + '\n' +
          'Contenido del meta tag: ' + debugInfo.csrfMetaContent + '\n' +
          'Token CSRF de Blade: ' + debugInfo.csrfToken + '\n\n' +
          'URL de envío: ' + debugInfo.ajaxRoute
        );

        // También mostrar en la consola para referencia fácil
        console.log('Diagnóstico del formulario:', debugInfo);
      });
      
      console.log('Diagnóstico instalado en el formulario de chat');
    } else {
      console.error('No se encontró el formulario de envío de mensajes');
      alert('ERROR: No se encontró el formulario de envío de mensajes');
    }
  }, 500); // Esperar medio segundo para asegurarnos que todo está cargado
});
===== FIN CÓDIGO DE DIAGNÓSTICO ===== */
