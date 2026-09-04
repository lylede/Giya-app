{{--
    Giya AI as a slide-out panel.

    The assistant is something you consult while doing something else - checking
    a mass time while looking at the map, asking about a church mid-route. Sending
    the devotee to a separate page loses their place. A panel keeps it.

    The full page at /chatbot still exists for a longer conversation.
--}}
<div class="ai-panel" id="giyaPanel" role="dialog" aria-modal="false"
     aria-label="{{ __('giya.chat.panel_aria') }}" hidden>

    {{-- The guide's face is the conversation's profile picture: it sits in the
         head beside the name, rather than floating off the panel's corner. --}}
    <div class="ai-panel-head">
        <span class="ai-panel-mark" aria-hidden="true">
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
        <div class="ai-panel-title">
            <strong>Giya AI</strong>
            <span>{{ __('giya.chat.subtitle') }}</span>
        </div>
        <a href="{{ route('chatbot') }}" class="ai-panel-expand" title="{{ __('giya.chat.open_full_t') }}"
           aria-label="{{ __('giya.chat.open_full') }}">
            <i class="bi bi-arrows-fullscreen"></i>
        </a>
        <button type="button" class="ai-panel-close" id="giyaPanelClose" aria-label="{{ __('giya.common.close') }}">
            <i class="bi bi-x"></i>
        </button>
    </div>

    <div class="ai-panel-log" id="giyaPanelLog">
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
                {{ __('giya.chat.greeting_panel') }}
            </div>
        </div>
    </div>

    <div class="ai-panel-starters" id="giyaPanelStarters">
        @foreach ([__('giya.chat.suggest_1'), __('giya.chat.suggest_2'), __('giya.chat.suggest_3')] as $starter)
            <button type="button" class="chat-starter" data-ai-ask="{{ $starter }}">{{ $starter }}</button>
        @endforeach
    </div>

    <form class="ai-panel-composer" id="giyaPanelForm" autocomplete="off">
        <input type="text" id="giyaPanelInput" maxlength="1000"
               placeholder="{{ __('giya.chat.ask_ph') }}" aria-label="{{ __('giya.home.card_ask') }}" required>
        <button type="submit" class="chat-send" id="giyaPanelSend" aria-label="{{ __('giya.chat.send') }}">
            <i class="bi bi-arrow-right"></i>
        </button>
    </form>
</div>

<div class="ai-panel-scrim" id="giyaPanelScrim" hidden></div>
