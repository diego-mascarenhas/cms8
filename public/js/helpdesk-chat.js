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
/******/ 	"use strict";
var __webpack_exports__ = {};
/*!***************************************!*\
  !*** ./resources/js/helpdesk-chat.js ***!
  \***************************************/
/**
 * App Chat
 */



document.addEventListener('DOMContentLoaded', function () {
  (function () {
    var chatHistoryBody = document.querySelector('.chat-history-body');

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
/******/ 	return __webpack_exports__;
/******/ })()
;
});