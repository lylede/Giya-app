/**
 * Giya AI - slide-out panel.
 *
 * Talks to the same endpoint as the full chat page, so a conversation started
 * here continues there and the other way round: both write to the devotee's
 * active ChatSession.
 */
(function (window, document) {
    'use strict';

    var fab     = document.getElementById('giyaFab');
    var panel   = document.getElementById('giyaPanel');
    var scrim   = document.getElementById('giyaPanelScrim');
    var log     = document.getElementById('giyaPanelLog');
    var form    = document.getElementById('giyaPanelForm');
    var input   = document.getElementById('giyaPanelInput');
    var send    = document.getElementById('giyaPanelSend');
    var closeEl = document.getElementById('giyaPanelClose');

    // Every one of these is needed. Bail as a group, so a page that renders
    // the button without the panel cannot throw partway through and take the
    // chip-strip behaviour at the bottom of this file down with it.
    if (!fab || !panel || !scrim || !log || !form || !input || !send || !closeEl) return;

    function token() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function esc(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function open() {
        panel.hidden = false;
        scrim.hidden = false;
        // Next frame, so the transition has a starting state to animate from.
        requestAnimationFrame(function () {
            panel.classList.add('is-open');
            scrim.classList.add('is-open');
        });
        fab.setAttribute('aria-expanded', 'true');
        document.body.classList.add('ai-panel-locked');
        setTimeout(function () { input.focus(); }, 260);
    }

    function close() {
        panel.classList.remove('is-open');
        scrim.classList.remove('is-open');
        fab.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('ai-panel-locked');
        setTimeout(function () { panel.hidden = true; scrim.hidden = true; }, 240);
        fab.focus();
    }

    fab.addEventListener('click', function () {
        panel.classList.contains('is-open') ? close() : open();
    });

    closeEl.addEventListener('click', close);
    scrim.addEventListener('click', close);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panel.classList.contains('is-open')) close();
    });

    /* ---------------------------------------------------------------- chat */

    function scroll() { log.scrollTop = log.scrollHeight; }

    function addUser(text) {
        var row = document.createElement('div');
        row.className = 'chat-row is-user';
        row.innerHTML = '<div class="chat-bubble chat-bubble-user">' + esc(text) + '</div>';
        log.appendChild(row);
        scroll();
    }

    function addTyping() {
        var row = document.createElement('div');
        row.className = 'chat-row';
        row.id = 'giyaPanelTyping';
        row.innerHTML =
            '<span class="chat-bot-icon" aria-hidden="true">' +
                '<span class="guide-face">' +
                    '<span class="guide-ear guide-ear-l"></span>' +
                    '<span class="guide-ear guide-ear-r"></span>' +
                    '<span class="guide-screen">' +
                        '<span class="guide-eye guide-eye-l"></span>' +
                        '<span class="guide-eye guide-eye-r"></span>' +
                        '<span class="guide-mouth"></span>' +
                    '</span>' +
                    '<span class="guide-lamp"></span>' +
                '</span>' +
            '</span>' +
            '<div class="chat-bubble chat-bubble-bot chat-typing">' +
                '<span></span><span></span><span></span></div>';
        log.appendChild(row);
        scroll();
    }

    function addBot(text, ok) {
        var typing = document.getElementById('giyaPanelTyping');
        if (typing) typing.remove();

        var row = document.createElement('div');
        row.className = 'chat-row';
        row.innerHTML =
            '<span class="chat-bot-icon" aria-hidden="true">' +
                '<span class="guide-face">' +
                    '<span class="guide-ear guide-ear-l"></span>' +
                    '<span class="guide-ear guide-ear-r"></span>' +
                    '<span class="guide-screen">' +
                        '<span class="guide-eye guide-eye-l"></span>' +
                        '<span class="guide-eye guide-eye-r"></span>' +
                        '<span class="guide-mouth"></span>' +
                    '</span>' +
                    '<span class="guide-lamp"></span>' +
                '</span>' +
            '</span>' +
            '<div class="chat-bubble chat-bubble-bot' + (ok ? '' : ' is-offline') + '">' +
                esc(text).replace(/\n/g, '<br>').replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>') +
            '</div>';
        log.appendChild(row);
        scroll();
    }

    function ask(text) {
        addUser(text);
        input.value = '';
        input.disabled = send.disabled = true;
        addTyping();

        fetch(fab.dataset.sendUrl || '/chatbot/send', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token(),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ message: text })
        })
            .then(function (r) { return r.json(); })
            .then(function (d) { addBot(d.reply || 'Something went wrong. Try again.', d.ok); })
            .catch(function () { addBot('I could not reach the server. Try again in a moment.', false); })
            .finally(function () {
                input.disabled = send.disabled = false;
                input.focus();
            });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var text = input.value.trim();
        if (text.length > 1) ask(text);
    });

    panel.addEventListener('click', function (e) {
        var chip = e.target.closest('[data-ai-ask]');
        if (chip) ask(chip.dataset.aiAsk);
    });
})(window, document);

/**
 * Horizontal chip strips - the starter questions on /chatbot and in the panel.
 *
 * The scrollbar is hidden, so on a desktop there is nothing to grab and no
 * sign the row continues. A plain vertical wheel is turned sideways, and the
 * edges fade while there are chips off-screen. A trackpad swipe and
 * shift+wheel already worked; this is for the mouse.
 */
(function (window, document) {
    'use strict';

    var strips = document.querySelectorAll('.chat-starters, .ai-panel-starters');
    if (!strips.length) return;

    function mark(el) {
        var max = el.scrollWidth - el.clientWidth;
        el.classList.toggle('can-left', el.scrollLeft > 1);
        el.classList.toggle('can-right', max > 1 && el.scrollLeft < max - 1);
    }

    Array.prototype.forEach.call(strips, function (el) {
        mark(el);

        el.addEventListener('scroll', function () { mark(el); }, { passive: true });
        window.addEventListener('resize', function () { mark(el); });

        el.addEventListener('wheel', function (e) {
            // Leave a real sideways gesture alone - the browser does it better.
            if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;

            var max = el.scrollWidth - el.clientWidth;
            if (max <= 0) return;

            var next = el.scrollLeft + e.deltaY;
            // At either end, give the wheel back so the page still scrolls.
            if (next < 0 || next > max) return;

            e.preventDefault();
            el.scrollLeft = next;
        }, { passive: false });
    });
})(window, document);
