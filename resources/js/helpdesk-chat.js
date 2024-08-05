/**
 * App Chat
 */

'use strict';

document.addEventListener('DOMContentLoaded', function () {
  (function () {
    const chatHistoryBody = document.querySelector('.chat-history-body');

    // Initialize PerfectScrollbar
    // ------------------------------

    // Chat history scrollbar
    if (chatHistoryBody) {
      new PerfectScrollbar(chatHistoryBody, {
        wheelPropagation: false,
        suppressScrollX: true
      });
    }

    // Scroll to bottom function
    function scrollToBottom() {
      chatHistoryBody.scrollTo(0, chatHistoryBody.scrollHeight);
    }
    scrollToBottom();
  })();
});