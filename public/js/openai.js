(function webpackUniversalModuleDefinition(root, factory) {
	if(typeof exports === 'object' && typeof module === 'object')
		module.exports = factory();
	else if(typeof define === 'function' && define.amd)
		define([], factory);
	else {
		var a = factory();
		for(var i in a) (typeof exports === 'object' ? exports : root)[i] = a[i];
	}
})(self, function() {
return /******/ (function() { // webpackBootstrap
var __webpack_exports__ = {};
/*!********************************!*\
  !*** ./resources/js/openai.js ***!
  \********************************/
$(document).ready(function () {
  localStorage.clear();
  var chatMessages = $('#chat-messages');
  var chatInput = $('#chat-input');
  var chatForm = $('#chat-form');
  var chatHistory = [];
  chatForm.submit(function (event) {
    event.preventDefault();
    var message = chatInput.val().trim();
    if (message !== '') {
      sendMessage(message);
      chatInput.val('');
    }
  });
  function sendMessage(message) {
    var userMessageElement = createMessageElement('Usuario', message);
    chatMessages.append(userMessageElement);
    chatHistory.push({
      role: 'user',
      content: message
    });
    var botMessageElement = createBotMessageElement('');
    chatMessages.append(botMessageElement);
    chatMessages.scrollTop(chatMessages[0].scrollHeight);
    $.ajax({
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      url: '/open-ai',
      method: 'POST',
      data: {
        msg: message,
        chatHistory: chatHistory
      },
      success: function success(response) {
        chatHistory.push({
          role: 'assistant',
          content: response.response
        });
        botMessageElement.find('.chat-message-text p').html(response.response);
        if (response.audioFile) {
          fetch(response.audioFile).then(function (response) {
            return response.blob();
          }).then(function (blob) {
            var audioUrl = URL.createObjectURL(blob);
            var audioElement = document.createElement("audio");
            audioElement.src = audioUrl;
            audioElement.autoplay = true;
            botMessageElement.find('.chat-message-text').append(audioElement);
          })["catch"](function (error) {
            console.error("Error al obtener el archivo de audio:", error);
          });
        }
        chatMessages.scrollTop(chatMessages[0].scrollHeight);
      },
      error: function error(xhr, status, _error) {
        botMessageElement.find('.chat-message-text p').html("ERROR: O no has puesto tu clave de API o GPT no funciona en este momento.");
        chatMessages.scrollTop(chatMessages[0].scrollHeight);
      }
    });
  }
  function createMessageElement(sender, message) {
    var isUser = sender.toLowerCase() === 'usuario';
    var senderClass = isUser ? 'chat-message chat-message-right' : 'chat-message';
    var avatarUrl = isUser ? '/assets/img/avatars/guru-meditating.jpg' : '/assets/img/avatars/guru-meditating.jpg';
    var messageElement = $('<li class="' + senderClass + '">');
    var messageContent = "\n            <div class=\"d-flex overflow-hidden\">\n                ".concat(isUser ? '' : "\n                <div class=\"user-avatar flex-shrink-0 me-3\">\n                    <div class=\"avatar avatar-sm\">\n                        <img src=\"".concat(avatarUrl, "\" alt=\"Avatar\" class=\"rounded-circle\">\n                    </div>\n                </div>"), "\n                <div class=\"chat-message-wrapper flex-grow-1\">\n                    <div class=\"chat-message-text\">\n                        <p class=\"mb-0\">").concat(message, "</p>\n                    </div>\n                    <div class=\"").concat(isUser ? 'text-end ' : '', "text-muted mt-1\">\n                        ").concat(isUser ? "<i class='ti ti-checks ti-xs me-1 text-success'></i>" : '', "\n                        <small>").concat(new Date().toLocaleTimeString(), "</small>\n                    </div>\n                </div>\n                ").concat(isUser ? "\n                <div class=\"user-avatar flex-shrink-0 ms-3\">\n                    <div class=\"avatar avatar-sm\">\n                        <img src=\"".concat(avatarUrl, "\" alt=\"Avatar\" class=\"rounded-circle\">\n                    </div>\n                </div>") : '', "\n            </div>\n        ");
    messageElement.html(messageContent);
    return messageElement;
  }
  function createBotMessageElement(message) {
    var botMessageElement = $('<li class="chat-message">');
    var avatarUrl = '/assets/img/avatars/guru-meditating.jpg';
    var preloaderImage = '<div class="sk-wave sk-primary"><div class="sk-wave-rect"></div><div class="sk-wave-rect"></div><div class="sk-wave-rect"></div><div class="sk-wave-rect"></div><div class="sk-wave-rect"></div></div>';
    var messageContent = "\n            <div class=\"d-flex overflow-hidden\">\n                <div class=\"user-avatar flex-shrink-0 me-3\">\n                    <div class=\"avatar avatar-sm\">\n                        <img src=\"".concat(avatarUrl, "\" alt=\"Avatar\" class=\"rounded-circle\">\n                    </div>\n                </div>\n                <div class=\"chat-message-wrapper flex-grow-1\">\n                    <div class=\"chat-message-text\">\n                        ").concat(preloaderImage, "\n                        <p class=\"mb-0\">").concat(message, "</p>\n                    </div>\n                    <div class=\"text-muted mt-1\">\n                        <small>").concat(new Date().toLocaleTimeString(), "</small>\n                    </div>\n                </div>\n            </div>\n        ");
    botMessageElement.html(messageContent);
    return botMessageElement;
  }
});
/******/ 	return __webpack_exports__;
/******/ })()
;
});