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

    // Focus the box for a mouse and keyboard only. On a phone an automatic
    // focus throws the keyboard over half the sheet before anyone asked.
    var canAutoFocus = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

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
        if (canAutoFocus) setTimeout(function () { input.focus(); }, 260);
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

    /* ------------------------------------------------- swipe down to close */
    /*
     * On a phone the panel is a bottom sheet with a grab bar. Dragging the
     * header down moves the sheet with the finger; letting go past a quarter
     * of its height, or with a quick flick, minimises it. Anything less
     * springs back. Tapping the bar minimises it too. The conversation is
     * saved on the server, so it is all there when the sheet opens again.
     */
    var head = document.getElementById('giyaPanelHead');
    var grab = document.getElementById('giyaPanelGrab');
    var sheet = window.matchMedia('(max-width: 640px)');

    // Keyboard and screen readers press the bar like any button (a finger
    // tap is handled in dragEnd, because the drag captures the pointer).
    if (grab) grab.addEventListener('click', function (e) { if (e.detail === 0) close(); });

    var startY = 0, lastY = 0, lastT = 0, speed = 0, dy = 0;
    var dragging = false, dragMoved = false, onGrab = false;

    function dragStart(e) {
        if (!sheet.matches || !panel.classList.contains('is-open')) return;
        // The expand and close buttons keep working as buttons.
        if (e.target.closest('a, .ai-panel-close')) return;
        dragging = true; dragMoved = false;
        onGrab = !!(grab && e.target.closest('#giyaPanelGrab'));
        startY = lastY = e.clientY; lastT = e.timeStamp; speed = 0; dy = 0;
        panel.style.transition = 'none';
        if (head.setPointerCapture) head.setPointerCapture(e.pointerId);
    }

    function dragMove(e) {
        if (!dragging) return;
        dy = Math.max(0, e.clientY - startY);          // down only
        if (dy > 4) dragMoved = true;
        var dt = e.timeStamp - lastT;
        if (dt > 0) speed = (e.clientY - lastY) / dt;  // px per ms
        lastY = e.clientY; lastT = e.timeStamp;
        panel.style.transform = 'translateY(' + dy + 'px)';
        scrim.style.opacity = String(Math.max(0, 1 - dy / panel.offsetHeight));
        e.preventDefault();
    }

    function dragEnd() {
        if (!dragging) return;
        dragging = false;
        panel.style.transition = '';
        scrim.style.opacity = '';
        var minimise = dy > panel.offsetHeight * 0.25 || (dy > 30 && speed > 0.5)
            || (onGrab && !dragMoved);                  // a tap on the bar
        panel.style.transform = '';                     // back to the class rules
        if (minimise) close();
    }

    if (head) {
        head.addEventListener('pointerdown', dragStart);
        head.addEventListener('pointermove', dragMove);
        head.addEventListener('pointerup', dragEnd);
        head.addEventListener('pointercancel', dragEnd);
    }

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
                esc(text)
                    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                    .replace(/(^|[\s(])_([^_\n]+?)_(?=[\s.,!?)]|$)/g, '$1<em>$2</em>')
                    .replace(/\n/g, '<br>') +
            '</div>';
        log.appendChild(row);
        scroll();
    }

    /* "Giya is catching its breath": the message, and a button that counts
       down the wait Groq asked for and then asks the same question again. */
    function addBusy(text, seconds, question) {
        addBot(text, false);
        var bubble = log.lastElementChild.querySelector('.chat-bubble');
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'chat-again';
        btn.disabled = true;
        bubble.appendChild(btn);

        var left = Math.max(1, Number(seconds) || 20);
        (function tick() {
            if (left <= 0) {
                btn.disabled = false;
                btn.textContent = fab.dataset.askAgain || 'Ask again';
                return;
            }
            btn.textContent = (fab.dataset.askAgainIn || 'Ask again in :s s').replace(':s', left);
            left--;
            setTimeout(tick, 1000);
        })();

        btn.addEventListener('click', function () {
            var row = btn.closest('.chat-row');
            if (row) row.remove();
            ask(question, true);
        });
    }

    function ask(text, again) {
        if (!chatHistory) { loadHistory().then(function () { ask(text, again); }); return; }
        if (!again) addUser(text);
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
            .then(function (d) {
                if (d.reason === 'busy') addBusy(d.reply, d.retry_after, text);
                else addBot(d.reply || 'Something went wrong. Try again.', d.ok);
            })
            .catch(function () { addBot('I could not reach the server. Try again in a moment.', false); })
            .finally(function () {
                input.disabled = send.disabled = false;
                if (canAutoFocus) input.focus();
            });
    }

    /* ---- the conversation so far ----
       The panel is drawn fresh on every page, so it used to start empty after
       moving to Plan or refreshing, although the server kept the whole
       conversation. It is fetched once per page, the first time the panel
       opens, and shown under the greeting. */
    var chatHistory = null;

    function loadHistory() {
        if (chatHistory) return chatHistory;
        var url = fab.dataset.historyUrl;
        if (!url) return (chatHistory = Promise.resolve());

        chatHistory = fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : { messages: [] }; })
            .then(function (d) {
                var msgs = (d && d.messages) || [];
                msgs.forEach(function (m) {
                    if (m.from === 'user') addUser(m.text); else addBot(m.text, true);
                });
                // A conversation under way does not need the starter questions.
                var starters = document.getElementById('giyaPanelStarters');
                if (starters && msgs.length) starters.hidden = true;
                scroll();
            })
            .catch(function () { /* the panel still works without it */ });

        return chatHistory;
    }

    // Opening the panel brings the conversation back.
    fab.addEventListener('click', function () { loadHistory(); });

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
