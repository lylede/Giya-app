@extends('layouts.app')
@section('title', 'Giya AI')

@section('content')
<div class="page-wrap chat-page" style="max-width:900px">

    <div class="chat-header">
        <span class="chat-avatar">
            <i class="bi bi-chat-dots-fill"></i>
        </span>
        <div style="flex:1;min-width:0">
            <h1>Giya AI Assistant</h1>
            <p>
                <span @class(['chat-dot', 'is-off' => ! $online])></span>
                {{ $online ? 'Pilgrimage guide for Metro Cebu' : 'Offline - answering from destination records' }}
            </p>
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

    <div class="card chat-shell">
        <div class="chat-log" id="chatLog">
            @if ($messages->isEmpty())
                <div class="chat-row">
                    <span class="chat-bot-icon"><i class="bi bi-stars"></i></span>
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
                            <span class="chat-bot-icon"><i class="bi bi-stars"></i></span>
                            <div class="chat-bubble chat-bubble-bot">{!! nl2br(e($m->message)) !!}</div>
                        </div>
                    @endif
                @endforeach
            @endif
        </div>

        @if ($messages->isEmpty())
            <div class="chat-starters" id="chatStarters">
                @foreach ($starters as $starter)
                    <button type="button" class="chat-starter" data-ask="{{ $starter }}">{{ $starter }}</button>
                @endforeach
            </div>
        @endif

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
            '<span class="chat-bot-icon"><i class="bi bi-stars"></i></span>' +
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
            '<span class="chat-bot-icon"><i class="bi bi-stars"></i></span>' +
            '<div class="chat-bubble chat-bubble-bot' + (ok ? '' : ' is-offline') + '">' +
                esc(text).replace(/\n/g, '<br>').replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>') +
            '</div>';
        log.appendChild(row);
        scroll();
    }

    function ask(text) {
        const starters = document.getElementById('chatStarters');
        if (starters) starters.remove();

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
@endpush
