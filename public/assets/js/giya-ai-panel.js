/**
 * Giya AI - slide-out panel.
 *
 * Talks to the same endpoint as the full chat page, so a conversation started
 * here continues there and the other way round: both write to the devotee's
 * active ChatSession.
 */
(function (window, document) {
    'use strict';

    var fab = document.getElementById('giyaFab');
    if (!fab) return;

    var panel   = document.getElementById('giyaPanel');
    var scrim   = document.getElementById('giyaPanelScrim');
    var log     = document.getElementById('giyaPanelLog');
    var form    = document.getElementById('giyaPanelForm');
    var input   = document.getElementById('giyaPanelInput');
    var send    = document.getElementById('giyaPanelSend');
    var closeEl = document.getElementById('giyaPanelClose');

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
                '<span class="mini-face">' +
                    '<span class="mini-eye mini-eye-l"></span>' +
                    '<span class="mini-eye mini-eye-r"></span>' +
                    '<span class="mini-mouth"></span>' +
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
            '<span class="chat-bot-icon">' +
                '<span class="mini-guide" aria-hidden="true"><span class="mini-eye mini-eye-l"></span><span class="mini-eye mini-eye-r"></span><span class="mini-mouth"></span></span>' +
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
