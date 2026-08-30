/**
 * Minimal embed loader for static landings: mounts widgets into [data-cms8-widget] nodes.
 * Set window.CMS8_WIDGETS_API_BASE to your app origin + /api/embed/automation/{public_token}
 * (or /api/embed/demo for the echo demo). No trailing slash.
 * Legacy HUMANO_WIDGETS_API_BASE and data-humano-widget remain supported.
 */
(function ()
{
    'use strict';

    function apiBase()
    {
        var cms8 = typeof window.CMS8_WIDGETS_API_BASE === 'string' ? window.CMS8_WIDGETS_API_BASE : '';
        var legacy = typeof window.HUMANO_WIDGETS_API_BASE === 'string' ? window.HUMANO_WIDGETS_API_BASE : '';
        var base = cms8 || legacy;

        return base.replace(/\/$/, '');
    }

    function el(tag, attrs, text)
    {
        var node = document.createElement(tag);

        if (attrs)
        {
            Object.keys(attrs).forEach(function (k)
            {
                if (attrs[k] === null || attrs[k] === undefined)
                {
                    return;
                }

                node.setAttribute(k, attrs[k]);
            });
        }

        if (text !== undefined && text !== null)
        {
            node.textContent = text;
        }

        return node;
    }

    function mountCalendar(container)
    {
        var base = apiBase();

        if (! base)
        {
            container.appendChild(el('p', { class: 'humano-widget-error' }, 'CMS8_WIDGETS_API_BASE is not set.'));

            return;
        }

        container.appendChild(el('p', { class: 'humano-widget-loading' }, 'Loading calendar…'));

        fetch(base + '/calendar', { credentials: 'omit' })
            .then(function (r)
            {
                if (! r.ok)
                {
                    throw new Error('HTTP ' + r.status);
                }

                return r.json();
            })
            .then(function (data)
            {
                container.innerHTML = '';
                var title = el('h3', { class: 'humano-widget-title' }, data.title || 'Calendar');
                container.appendChild(title);

                var list = el('div', { class: 'humano-widget-slots' });
                (data.slots || []).forEach(function (slot)
                {
                    var btn = el('button', {
                        type: 'button',
                        class: 'humano-widget-slot' + (slot.available ? '' : ' humano-widget-slot-disabled'),
                        disabled: slot.available ? null : 'disabled',
                        'data-slot-id': slot.id,
                    }, slot.label || slot.id);
                    list.appendChild(btn);
                });
                container.appendChild(list);

                list.addEventListener('click', function (e)
                {
                    var t = e.target;

                    if (t && t.getAttribute && t.getAttribute('data-slot-id'))
                    {
                        container.querySelectorAll('.humano-widget-slot').forEach(function (b)
                        {
                            b.classList.remove('humano-widget-slot-active');
                        });
                        t.classList.add('humano-widget-slot-active');
                    }
                });
            })
            .catch(function (err)
            {
                container.innerHTML = '';
                container.appendChild(el('p', { class: 'humano-widget-error' }, 'Could not load calendar: ' + err.message));
            });
    }

    function mountAssistant(container)
    {
        var base = apiBase();

        if (! base)
        {
            container.appendChild(el('p', { class: 'humano-widget-error' }, 'CMS8_WIDGETS_API_BASE is not set.'));

            return;
        }

        var title = el('h3', { class: 'humano-widget-title' }, 'Assistant');
        var log = el('div', { class: 'humano-widget-chat-log' });
        var row = el('div', { class: 'humano-widget-chat-row' });
        var input = el('input', { type: 'text', class: 'humano-widget-input', placeholder: 'Type a message…', maxlength: '2000' });
        var send = el('button', { type: 'button', class: 'humano-widget-send' }, 'Send');

        log.appendChild(el('div', { class: 'humano-widget-msg humano-widget-msg-bot' }, 'Loading…'));

        fetch(base + '/', { credentials: 'omit', headers: { Accept: 'application/json' } })
            .then(function (r)
            {
                if (! r.ok)
                {
                    return null;
                }

                return r.json();
            })
            .then(function (meta)
            {
                log.innerHTML = '';
                if (meta && meta.name)
                {
                    title.textContent = meta.name;
                }
                var welcome = (meta && meta.welcome_message) ? meta.welcome_message : 'Hi — how can I help you?';
                log.appendChild(el('div', { class: 'humano-widget-msg humano-widget-msg-bot' }, welcome));
            })
            .catch(function ()
            {
                log.innerHTML = '';
                log.appendChild(el('div', { class: 'humano-widget-msg humano-widget-msg-bot' }, 'Hi — how can I help you?'));
            });

        send.addEventListener('click', function ()
        {
            var text = (input.value || '').trim();

            if (! text)
            {
                return;
            }

            log.appendChild(el('div', { class: 'humano-widget-msg humano-widget-msg-user' }, text));
            input.value = '';
            send.disabled = true;

            fetch(base + '/assistant', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                credentials: 'omit',
                body: JSON.stringify({
                    message: text,
                    session_key: window.CMS8_WIDGET_SESSION_KEY || window.HUMANO_WIDGET_SESSION_KEY || null,
                }),
            })
                .then(function (r)
                {
                    if (! r.ok)
                    {
                        throw new Error('HTTP ' + r.status);
                    }

                    return r.json();
                })
                .then(function (data)
                {
                    if (data.session_key)
                    {
                        window.CMS8_WIDGET_SESSION_KEY = data.session_key;
                    }
                    var reply = data.reply || data.response || '';
                    if (reply)
                    {
                        log.appendChild(el('div', { class: 'humano-widget-msg humano-widget-msg-bot' }, reply));
                        log.scrollTop = log.scrollHeight;
                    }
                })
                .catch(function (err)
                {
                    log.appendChild(el('div', { class: 'humano-widget-msg humano-widget-error' }, err.message));
                })
                .finally(function ()
                {
                    send.disabled = false;
                });
        });

        input.addEventListener('keydown', function (e)
        {
            if (e.key === 'Enter')
            {
                send.click();
            }
        });

        var lastPolledId = 0;
        var pollPrimed = false;
        window.setInterval(function ()
        {
            var sessionKey = window.CMS8_WIDGET_SESSION_KEY || window.HUMANO_WIDGET_SESSION_KEY;
            if (! sessionKey)
            {
                return;
            }

            fetch(base + '/messages?session_key=' + encodeURIComponent(sessionKey) + '&after_id=' + lastPolledId, {
                headers: { Accept: 'application/json' },
                credentials: 'omit',
            })
                .then(function (r)
                {
                    return r.ok ? r.json() : null;
                })
                .then(function (data)
                {
                    if (! data || ! data.messages)
                    {
                        return;
                    }

                    data.messages.forEach(function (message)
                    {
                        lastPolledId = Math.max(lastPolledId, Number(message.id) || 0);
                        if (! pollPrimed || message.role !== 'staff')
                        {
                            return;
                        }

                        log.appendChild(el('div', { class: 'humano-widget-msg humano-widget-msg-bot' }, message.body || ''));
                    });
                    pollPrimed = true;
                    log.scrollTop = log.scrollHeight;
                })
                .catch(function () {});
        }, 4000);

        container.appendChild(title);
        container.appendChild(log);
        row.appendChild(input);
        row.appendChild(send);
        container.appendChild(row);
    }

    function mount(container)
    {
        var type = container.getAttribute('data-cms8-widget') || container.getAttribute('data-humano-widget');

        container.classList.add('humano-widget-root', 'cms8-widget-root');

        if (type === 'calendar')
        {
            mountCalendar(container);
        }
        else if (type === 'assistant')
        {
            mountAssistant(container);
        }
        else
        {
            container.appendChild(el('p', { class: 'humano-widget-error' }, 'Unknown widget type.'));
        }
    }

    function init()
    {
        document.querySelectorAll('[data-cms8-widget], [data-humano-widget]').forEach(mount);
    }

    if (document.readyState === 'loading')
    {
        document.addEventListener('DOMContentLoaded', init);
    }
    else
    {
        init();
    }
})();
