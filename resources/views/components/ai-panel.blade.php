{{--
    Giya AI as a slide-out panel.

    The assistant is something you consult while doing something else - checking
    a mass time while looking at the map, asking about a church mid-route. Sending
    the devotee to a separate page loses their place. A panel keeps it.

    The full page at /chatbot still exists for a longer conversation.
--}}
<div class="ai-panel" id="giyaPanel" role="dialog" aria-modal="false"
     aria-label="Giya AI assistant" hidden>

    <div class="ai-panel-head">
        <span class="ai-panel-mark"><i class="bi bi-giya-star"></i></span>
        <div class="ai-panel-title">
            <strong>Giya AI</strong>
            <span>Pilgrimage guide for Metro Cebu</span>
        </div>
        <a href="{{ route('chatbot') }}" class="ai-panel-expand" title="Open full page"
           aria-label="Open the full chat page">
            <i class="bi bi-arrows-fullscreen"></i>
        </a>
        <button type="button" class="ai-panel-close" id="giyaPanelClose" aria-label="Close">
            <i class="bi bi-x"></i>
        </button>
    </div>

    <div class="ai-panel-log" id="giyaPanelLog">
        <div class="chat-row">
            <span class="chat-bot-icon"><i class="bi bi-giya-star"></i></span>
            <div class="chat-bubble chat-bubble-bot">
                Maayong buntag! Ask me about churches, mass schedules, or a Visita Iglesia route.
            </div>
        </div>
    </div>

    <div class="ai-panel-starters" id="giyaPanelStarters">
        @foreach (['Churches near Cebu City', 'Plan a Visita Iglesia', 'Mass at the Cathedral'] as $starter)
            <button type="button" class="chat-starter" data-ai-ask="{{ $starter }}">{{ $starter }}</button>
        @endforeach
    </div>

    <form class="ai-panel-composer" id="giyaPanelForm" autocomplete="off">
        <input type="text" id="giyaPanelInput" maxlength="1000"
               placeholder="Ask Giya AI…" aria-label="Ask Giya AI" required>
        <button type="submit" class="chat-send" id="giyaPanelSend" aria-label="Send">
            <i class="bi bi-arrow-right"></i>
        </button>
    </form>
</div>

<div class="ai-panel-scrim" id="giyaPanelScrim" hidden></div>
