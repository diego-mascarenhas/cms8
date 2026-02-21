(function () {
    'use strict';

    if (typeof window.humanoChat === 'undefined') {
        return;
    }

    const config = window.humanoChat;
    const i18n = config.i18n || {};

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function appendMessage(container, role, text, meta) {
        const msg = document.createElement('div');
        msg.className = 'humano-chat-msg humano-chat-msg-' + role;
        msg.innerHTML = escapeHtml(text);
        if (meta) {
            const metaEl = document.createElement('div');
            metaEl.className = 'humano-chat-msg-meta';
            metaEl.textContent = meta;
            msg.appendChild(metaEl);
        }
        container.appendChild(msg);
        container.scrollTop = container.scrollHeight;
    }

    function setStatus(wrap, text, thinking) {
        const status = wrap.querySelector('.humano-chat-status');
        if (!status) return;
        status.textContent = text || '';
        status.classList.toggle('humano-chat-status-thinking', !!thinking);
    }

    function sendMessage(wrap) {
        const input = wrap.querySelector('.humano-chat-input');
        const sendBtn = wrap.querySelector('.humano-chat-send');
        const messages = wrap.querySelector('.humano-chat-messages');
        const text = (input && input.value) ? input.value.trim() : '';
        if (!text || !messages) return;

        input.value = '';
        input.style.height = 'auto';
        appendMessage(messages, 'user', text);

        sendBtn.disabled = true;
        setStatus(wrap, i18n.thinking || 'Thinking...', true);

        const formData = new FormData();
        formData.append('action', config.action);
        formData.append('nonce', config.nonce);
        formData.append('message', text);
        if (config.promptKey) {
            formData.append('prompt_key', config.promptKey);
        }

        fetch(config.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        })
            .then(function (res) {
                return res.json();
            })
            .then(function (data) {
                setStatus(wrap, '', false);
                sendBtn.disabled = false;
                if (data.success && data.data && data.data.response !== undefined) {
                    appendMessage(
                        messages,
                        'bot',
                        data.data.response,
                        data.data.routed_to ? '→ ' + data.data.routed_to : null
                    );
                } else {
                    appendMessage(
                        messages,
                        'error',
                        (data.data && data.data.message) || i18n.error || 'Something went wrong.'
                    );
                }
            })
            .catch(function () {
                setStatus(wrap, '', false);
                sendBtn.disabled = false;
                appendMessage(messages, 'error', i18n.error || 'Something went wrong.');
            });
    }

    function bindWrap(wrap) {
        if (!wrap || wrap.dataset.humanoChatBound === 'true') return;
        wrap.dataset.humanoChatBound = 'true';

        const input = wrap.querySelector('.humano-chat-input');
        const sendBtn = wrap.querySelector('.humano-chat-send');

        function submit() {
            sendMessage(wrap);
        }

        if (sendBtn) {
            sendBtn.addEventListener('click', submit);
        }
        if (input) {
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    submit();
                }
            });
            input.addEventListener('input', function () {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
        }
    }

    function initInline() {
        document.querySelectorAll('.humano-chat-wrap').forEach(bindWrap);
    }

    function initFloating() {
        const floating = document.getElementById('humano-chat-floating');
        if (!floating || !config.floating) return;

        const btn = floating.querySelector('.humano-chat-float-btn');
        const panel = floating.querySelector('.humano-chat-float-panel');
        if (!btn || !panel) return;

        btn.addEventListener('click', function () {
            const open = panel.hidden;
            panel.hidden = !open;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.setAttribute('aria-label', open ? (i18n.close || 'Close chat') : (i18n.open || 'Open chat'));
            if (open) {
                const inner = panel.querySelector('.humano-chat-wrap');
                if (inner) bindWrap(inner);
            }
        });

        bindWrap(panel.querySelector('.humano-chat-wrap'));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initInline();
            initFloating();
        });
    } else {
        initInline();
        initFloating();
    }
})();
