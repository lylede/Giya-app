@extends('layouts.app')
@section('title', 'Giya AI')

@push('head')
<style>
/*
    Mobile: the page is one screen and only the conversation moves.

    The chat log had no height cap below 600px, so the whole document
    scrolled instead - and the guide, which is the assistant's status
    indicator, scrolled off the top exactly when it had something to say.
    It looks down while you type and up while it thinks; none of that is
    any use above the fold you are no longer looking at.

    The page becomes a fixed-height column: guide pinned, composer pinned,
    log taking what is left. 100dvh because a phone's URL bar changes the
    viewport height and 100vh does not follow it.
*/
@media (max-width: 767px) {
    body { overflow: hidden; }

    .chat-page {
        height: calc(100vh - 64px);      /* fallback */
        height: calc(100dvh - 64px);     /* follows the URL bar */
        display: flex; flex-direction: column;
        padding: 8px 12px 0;
        overflow: hidden;
        max-width: none;
    }

    .chat-page .guide { flex: 0 0 auto; margin-bottom: 4px; }

    .chat-page .chat-shell {
        flex: 1 1 auto; min-height: 0;
        display: flex; flex-direction: column;
        margin-bottom: 0;
    }

    /* min-height:0 is what actually lets this scroll - without it a flex
       item refuses to shrink below its content and the log grows the page
       instead of scrolling inside it. */
    .chat-page .chat-log {
        flex: 1 1 auto;
        min-height: 0;
        max-height: none;
        overscroll-behavior: contain;    /* no page bounce past the ends */
    }

    .chat-page .chat-bar,
    .chat-page .chat-starters,
    .chat-page .chat-composer { flex: 0 0 auto; }

    /* Both would push the composer off the screen, and neither is worth a
       thumb-reach. The disclaimer still shows on desktop. */
    .chat-page .chat-note { display: none; }
    .giya-footer { display: none; }
}

/*
    While the keyboard is up there is barely a third of the screen left, and
    the guide was taking a chunk of it - clipped in half, which looks broken
    rather than charming. It goes away for as long as someone is typing and
    the conversation takes the space. It comes straight back on blur.

    --vvh is the visual viewport height, set from JS below: the keyboard
    covers part of the window WITHOUT changing 100dvh, so dvh alone leaves
    the composer under the keys.
*/
@media (max-width: 767px) {
    .chat-page.is-typing { height: calc(var(--vvh, 100dvh) - 64px); }
    .chat-page.is-typing .guide { display: none; }
    .chat-page.is-typing .chat-bar { padding-top: 10px; padding-bottom: 10px; }
    .chat-page.is-typing .chat-starters { display: none; }
}

/*
    A short screen - a phone turned sideways, mostly - cannot hold the guide,
    the starters and the composer at once. The composer is the one thing that
    must never be off screen, so everything optional goes before it does.
*/
@media (max-width: 767px) and (max-height: 620px) {
    .chat-page .guide-bubble,
    .chat-page .guide-hands,
    .chat-page .chat-starters { display: none; }

    .chat-page .guide-face { width: 62px; height: 56px; }
    .chat-page .guide-screen { width: 46px; height: 38px; }
    .chat-page .guide-eye { width: 9px; height: 9px; top: 12px; }
    .chat-page .guide-eye-l { left: 9px; }
    .chat-page .guide-eye-r { right: 9px; }
    .chat-page .guide-mouth { bottom: 9px; width: 12px; margin-left: -6px; }
    .chat-page .guide-lamp { display: none; }

    .chat-page .chat-bar { padding-top: 8px; padding-bottom: 8px; }
    .chat-page .chat-log { padding: 10px 12px; }
    .chat-page .chat-composer { padding: 8px 10px; }
}
</style>
@endpush

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
                <strong>{{ __('giya.chat.title') }}</strong>
                <span>
                    <span @class(['chat-dot', 'is-off' => ! $online])></span>
                    {{ $online ? __('giya.chat.subtitle') : __('giya.chat.offline') }}
                </span>
            </div>

            @if ($messages->isNotEmpty())
                <form method="POST" action="{{ route('chatbot.reset') }}"
                      data-confirm-title="Start a new conversation?"
                      data-confirm="This conversation is closed and cleared from view."
                      data-confirm-ok="Start new"
                      data-confirm-tone="primary">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm">{{ __('giya.chat.new_chat') }}</button>
                </form>
            @endif
        </div>
        <div class="chat-log" id="chatLog">
            @if ($messages->isEmpty())
                <div class="chat-row">
                    <span class="chat-bot-icon" aria-hidden="true">
                        <span class="guide-face">
                            <span class="guide-ear guide-ear-l"></span>
                            <span class="guide-ear guide-ear-r"></span>
                            <span class="guide-screen">
                                <span class="guide-eye guide-eye-l"></span>
                                <span class="guide-eye guide-eye-r"></span>
                                <span class="guide-mouth"></span>
                            </span>
                            <span class="guide-lamp"></span>
                        </span>
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
                            <span class="chat-bot-icon" aria-hidden="true">
                                <span class="guide-face">
                                    <span class="guide-ear guide-ear-l"></span>
                                    <span class="guide-ear guide-ear-r"></span>
                                    <span class="guide-screen">
                                        <span class="guide-eye guide-eye-l"></span>
                                        <span class="guide-eye guide-eye-r"></span>
                                        <span class="guide-mouth"></span>
                                    </span>
                                    <span class="guide-lamp"></span>
                                </span>
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
                   placeholder="{{ __('giya.chat.placeholder') }}"
                   aria-label="{{ __('giya.chat.placeholder') }}" required>
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
/*
    Keyboard handling.

    A phone keyboard covers part of the window without changing innerHeight or
    100dvh, so the page keeps its full height and the bottom of it - the
    composer - ends up under the keys. visualViewport is the only thing that
    reports the area actually left to draw in.

    Focus drives the class rather than the measurement alone: it fires the
    instant the field is tapped, before the keyboard has finished animating in,
    so the guide is gone by the time the keys arrive instead of jumping a
    moment later. The measurement then keeps it honest - dismissing the
    keyboard by swiping down leaves the field focused, and this notices.
*/
(function () {
    const page  = document.querySelector('.chat-page');
    const input = document.getElementById('chatInput');
    const log   = document.getElementById('chatLog');
    if (!page || !input) return;

    const vv = window.visualViewport;

    /*
        The keyboard animates in AFTER focus fires, so for a moment the
        measurement still says there is no keyboard. Without this window the
        class was added on focus and taken straight back off by the first
        sync, and the guide never actually went away.
    */
    let graceUntil = 0;
    let recheck = null;

    function sync() {
        if (!vv) return;                 // no measurement here; focus decides

        page.style.setProperty('--vvh', vv.height + 'px');

        // Under this it is a browser toolbar, not a keyboard.
        if (window.innerHeight - vv.height > 120) {
            const wasOpen = page.classList.contains('is-typing');
            page.classList.add('is-typing');

            /*
                The scroll on focus runs before the keyboard has finished
                resizing anything, so the newest message ends up half cut off
                once the space settles. Re-pin it on the transition - but only
                then, or every toolbar twitch would drag someone back down
                while they are reading further up.
            */
            if (!wasOpen) pinToBottom();
            return;
        }

        /*
            Still inside the grace window, so this may just be the keyboard
            not having arrived yet. Come back when the window closes - if the
            keyboard really was dismissed in that moment, no further resize is
            coming and nothing else would ever re-check.
        */
        if (Date.now() < graceUntil) {
            clearTimeout(recheck);
            recheck = setTimeout(sync, graceUntil - Date.now() + 50);
            return;
        }

        // No keyboard and the grace window has passed - including the case
        // where it was swiped away while the field kept focus.
        page.classList.remove('is-typing');
    }

    function pinToBottom() {
        if (log) log.scrollTop = log.scrollHeight;
    }

    function open() {
        graceUntil = Date.now() + 700;
        page.classList.add('is-typing');
        if (vv) page.style.setProperty('--vvh', vv.height + 'px');

        /*
            Nothing else is guaranteed to call sync from here. If this was a
            tap that never became a focus - a scroll that started on the field,
            a mis-tap - no keyboard arrives, no resize fires, and the guide
            would stay hidden for nothing. Check once the grace window is up.
        */
        clearTimeout(recheck);
        recheck = setTimeout(sync, 750);

        // Keep the last message in view as the space changes under it.
        setTimeout(pinToBottom, 220);
    }

    function close() {
        graceUntil = 0;
        clearTimeout(recheck);
        page.classList.remove('is-typing');
        page.style.removeProperty('--vvh');
    }

    /*
        pointerdown, not focus alone.

        On a phone, focus lands well after the finger does - the browser
        settles the tap, sometimes scrolls the field into view, and only then
        focuses. Hanging the switch off focus meant the face sat there for a
        visible beat before going. pointerdown is the first thing that fires,
        so the guide is gone before the keyboard even starts moving.

        focus stays as the fallback for anything that gets here another way:
        a hardware keyboard, a tap on a label, autofocus.
    */
    input.addEventListener('pointerdown', open, { passive: true });
    input.addEventListener('touchstart', open, { passive: true });
    input.addEventListener('focus', open);
    input.addEventListener('blur', close);

    if (vv) {
        vv.addEventListener('resize', sync);
        vv.addEventListener('scroll', sync);
    }
})();
</script>
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
