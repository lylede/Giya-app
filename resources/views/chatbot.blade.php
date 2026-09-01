@extends('layouts.app')
@section('title', 'Giya AI')

@section('content')
<div class="page-wrap chat-page" style="max-width:900px">

    {{--
        The guide sits above the panel and reacts to what is happening: it looks
        down while you type, glances up while it thinks, and smiles when an
        answer arrives.

        A face is not decoration here. A chat window with no visible state is
        silent while it works, and silence reads as broken. The face is the
        status indicator, and it is legible at a glance.
    --}}
    <div class="guide" id="guide" data-online="{{ $online ? '1' : '0' }}">
        <div class="guide-bubble" id="guideBubble">
            <span id="guideSay">
                {{ $online
                    ? 'Maayong buntag. Ask me about any church in Metro Cebu.'
                    : 'I am offline, so I answer from the destination records only.' }}
            </span>
        </div>

        <div class="guide-face" aria-hidden="true">
            <span class="guide-ear guide-ear-l"></span>
            <span class="guide-ear guide-ear-r"></span>

            <div class="guide-screen">
                <span class="guide-eye guide-eye-l"><i></i></span>
                <span class="guide-eye guide-eye-r"><i></i></span>
                <span class="guide-mouth"></span>
                <span class="guide-blush guide-blush-l"></span>
                <span class="guide-blush guide-blush-r"></span>
            </div>

            {{-- A lantern rather than an antenna: giya means guide, and a
                 lantern is what a guide carries. --}}
            <span class="guide-lamp"></span>
        </div>

        <div class="guide-hands">
            <span class="guide-hand guide-hand-l"></span>
            <span class="guide-hand guide-hand-r"></span>
        </div>
    </div>

    <div class="card chat-shell">
                {{-- Inside the panel, so the assistant reads as one box rather than a
             heading floating above a separate card. --}}
        <div class="chat-bar">
            <div>
                <strong>Giya AI Assistant</strong>
                <span>
                    <span @class(['chat-dot', 'is-off' => ! $online])></span>
                    {{ $online ? 'Pilgrimage guide for Metro Cebu' : 'Offline - answering from records' }}
                </span>
            </div>

            @if ($messages->isNotEmpty())
                <form method="POST" action="{{ route('chatbot.reset') }}"
                      data-confirm-title="Start a new conversation?"
                      data-confirm="This conversation is closed and cleared from view."
                      data-confirm-ok="Start new"
                      data-confirm-tone="primary">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm">New chat</button>
                </form>
            @endif
        </div>
        <div class="chat-log" id="chatLog">
            @if ($messages->isEmpty())
                <div class="chat-row">
                    <span class="chat-bot-icon">
                        <img src="{{ asset('images/icons/chatbot_icon.svg') }}"
                            alt=""
                            class="chat-bot-svg">
                    </span>
                    <div class="chat-bubble chat-bubble-bot">
                        Maayong buntag! I am Giya AI. Ask me about churches in Metro Cebu,
                        mass schedules, or let me plan a Visita Iglesia route for you.
                    </div>
                </div>
            @else
                @foreach ($messages as $m)
                    @if ($m->sender_type === 'user')
                        <div class="chat-row is-user">
                            <span class="nav-avatar chat-user-icon">
                                @if (auth()->user()->avatarPath())
                                    <img src="{{ auth()->user()->avatarPath() }}" alt="">
                                @else
                                    {{ auth()->user()->initials() }}
                                @endif
                            </span>
                            <div class="chat-bubble chat-bubble-user">{{ $m->message }}</div>
                        </div>
                    @else
                        <div class="chat-row">
                            <span class="chat-bot-icon">
                                <img src="{{ asset('images/icons/chatbot_icon.svg') }}"
                                    alt=""
                                    class="chat-bot-svg">
                            </span>
                            <div class="chat-bubble chat-bubble-bot">{!! nl2br(e($m->message)) !!}</div>
                        </div>
                    @endif
                @endforeach
            @endif
        </div>

        <div class="chat-starters" id="chatStarters">
            @foreach ($starters as $starter)
                <button type="button" class="chat-starter" data-ask="{{ $starter }}">{{ $starter }}</button>
            @endforeach
        </div>

        <form class="chat-composer" id="chatForm" autocomplete="off">
            @csrf
            <input type="text" id="chatInput" name="message" maxlength="1000"
                   placeholder="Ask about churches, mass times, or a route…"
                   aria-label="Ask Giya AI" required>
            <button type="submit" class="chat-send" id="chatSend" aria-label="Send">
                <i class="bi bi-arrow-right"></i>
            </button>
        </form>
    </div>

    <p class="chat-note">
        Giya AI answers from GIYA's destination records. Mass schedules change -
        please confirm with the parish before travelling.
    </p>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form  = document.getElementById('chatForm');
    const input = document.getElementById('chatInput');
    const log   = document.getElementById('chatLog');
    const send  = document.getElementById('chatSend');
    if (!form) return;

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const avatar = @json(auth()->user()->avatarPath());
    const initials = @json(auth()->user()->initials());

    function scroll() { log.scrollTop = log.scrollHeight; }

    function esc(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function addUser(text) {
        const row = document.createElement('div');
        row.className = 'chat-row is-user';
        row.innerHTML =
            '<span class="nav-avatar chat-user-icon">' +
                (avatar ? '<img src="' + esc(avatar) + '" alt="">' : esc(initials)) +
            '</span>' +
            '<div class="chat-bubble chat-bubble-user">' + esc(text) + '</div>';
        log.appendChild(row);
        scroll();
    }

    /* A typing indicator, not a spinner: it says the assistant is composing. */
    function addTyping() {
        const row = document.createElement('div');
        row.className = 'chat-row';
        row.id = 'chatTyping';
        row.innerHTML =
            '<span class="chat-bot-icon">' +
                '<img src="{{ asset('images/icons/chatbot_icon.svg') }}" alt="" class="chat-bot-svg">' +
            '</span>' +
            '<div class="chat-bubble chat-bubble-bot chat-typing">' +
                '<span></span><span></span><span></span>' +
            '</div>';
        log.appendChild(row);
        scroll();
    }

    function addBot(text, ok) {
        const typing = document.getElementById('chatTyping');
        if (typing) typing.remove();

        const row = document.createElement('div');
        row.className = 'chat-row';
        row.innerHTML =
            '<span class="chat-bot-icon">' +
                '<img src="{{ asset('images/icons/chatbot_icon.svg') }}" alt="" class="chat-bot-svg">' +
            '</span>' +
            '<div class="chat-bubble chat-bubble-bot' + (ok ? '' : ' is-offline') + '">' +
                esc(text)
                    .replace(/\n/g, '<br>')
                    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            '</div>';
        log.appendChild(row);
        scroll();
    }

    function ask(text) {
        addUser(text);
        input.value = '';
        input.disabled = send.disabled = true;
        addTyping();

        fetch('{{ route('chatbot.send') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ message: text }),
        })
            .then(r => r.json())
            .then(d => addBot(d.reply || 'Something went wrong. Try again.', d.ok))
            .catch(() => addBot('I could not reach the server. Check that it is running and try again.', false))
            .finally(() => {
                input.disabled = send.disabled = false;
                input.focus();
            });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const text = input.value.trim();
        if (text.length > 1) ask(text);
    });

    document.addEventListener('click', function (e) {
        const chip = e.target.closest('[data-ask]');
        if (chip) ask(chip.dataset.ask);
    });

    scroll();
    input.focus();
})();
</script>
<script>
/**
 * The guide's expressions.
 *
 * Each state is a class on the wrapper; the CSS does the drawing. Keeping the
 * behaviour here and the appearance there means an expression can be redrawn
 * without touching the logic that decides when to use it.
 */
(function () {
    const guide  = document.getElementById('guide');
    if (!guide) return;

    const say    = document.getElementById('guideSay');
    const bubble = document.getElementById('guideBubble');
    const input  = document.getElementById('chatInput');
    const log    = document.getElementById('chatLog');

    const STATES = ['is-idle', 'is-typing', 'is-secret', 'is-thinking', 'is-happy', 'is-sorry'];

    let restTimer = null;

    function setState(state, line) {
        STATES.forEach(function (c) { guide.classList.toggle(c, c === state); });

        if (line) {
            // Re-trigger the bubble's entrance so a repeat line still registers.
            bubble.classList.remove('is-in');
            void bubble.offsetWidth;
            say.textContent = line;
            bubble.classList.add('is-in');
        }
    }

    function rest(delay) {
        clearTimeout(restTimer);
        restTimer = setTimeout(function () {
            setState('is-idle', guide.dataset.online === '1'
                ? 'Ask me anything about Metro Cebu.'
                : 'Offline, but I still know the destinations.');
        }, delay || 4000);
    }

    /* ---- typing ---- */
    if (input) {
        input.addEventListener('input', function () {
            if (!input.value.trim()) { rest(1200); return; }

            clearTimeout(restTimer);
            setState('is-typing', 'Go on, I am listening.');
        });

        input.addEventListener('focus', function () {
            if (!input.value.trim()) setState('is-typing', 'What would you like to know?');
        });
    }

    /* ---- the conversation ---- */
    if (log) {
        /* Watching the log rather than hooking the fetch: the panel and the
           page both write here, so one observer covers both without either
           script knowing about the guide. */
        new MutationObserver(function (records) {
            records.forEach(function (r) {
                Array.from(r.addedNodes).forEach(function (node) {
                    if (!node.classList) return;

                    if (node.classList.contains('is-user')) {
                        setState('is-thinking', 'Let me look through the churches...');
                        return;
                    }

                    if (node.querySelector && node.querySelector('.chat-typing')) return;

                    const bot = node.querySelector && node.querySelector('.chat-bubble-bot');
                    if (!bot) return;

                    if (bot.classList.contains('is-offline')) {
                        setState('is-sorry', 'I could not reach the AI, so that came from the records.');
                    } else {
                        setState('is-happy', 'Here you go.');
                    }
                    rest(5000);
                });
            });
        }).observe(log, { childList: true });
    }

    /* A blink now and then, so an idle face is not a staring one. */
    setInterval(function () {
        if (guide.classList.contains('is-thinking')) return;
        guide.classList.add('is-blinking');
        setTimeout(function () { guide.classList.remove('is-blinking'); }, 160);
    }, 4200);

    setState('is-idle');
    bubble.classList.add('is-in');
})();
</script>
@endpush
