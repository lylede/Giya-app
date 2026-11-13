<!DOCTYPE html>
@php($giyaPrefs = auth()->check() ? auth()->user()->preferencesOrDefault() : null)
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      data-theme="{{ strtolower($giyaPrefs->theme_style ?? 'light') }}"
      data-font="{{ strtolower($giyaPrefs->font_size ?? 'medium') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>GIYA - @yield('title', 'Pilgrimage Companion')</title>
    <x-meta />

    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo/giya-logo.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/giya-icons.css') }}?v={{ filemtime(public_path('assets/css/giya-icons.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/giya.css') }}?v={{ filemtime(public_path('assets/css/giya.css')) }}">
    @stack('head')
</head>
<body @class(['app-body']) @yield('body-attr')>

<x-navbar />

@include('components.flash')

@if (auth()->user()?->isAdmin() && \App\Support\Maintenance::on())
    {{-- An admin browsing the site sees it as normal; pilgrims do not. --}}
    <div class="verify-banner" role="status">
        <i class="bi bi-cone-striped"></i>
        <span>Maintenance mode is on. Only admins can see this page.</span>
        <a href="{{ route('admin.system') }}">Manage</a>
    </div>
@endif

@if (\App\Actions\Auth\EmailVerification::pending(auth()->user()) && ! request()->routeIs('verification.notice'))
    {{-- Unconfirmed email: a gentle reminder, not a wall. Browsing still works. --}}
    <div class="verify-banner" role="status">
        <i class="bi bi-envelope"></i>
        <span>{{ __('giya.verify.banner') }}</span>
        <a href="{{ route('verification.notice') }}">{{ __('giya.verify.banner_link') }}</a>
    </div>
@endif

<main>
    @yield('content')
</main>

@hasSection('no-footer')
@else
    <x-footer />
@endif
@auth
    @unless (request()->routeIs('chatbot', 'chatbot.show'))
        <button type="button" class="giya-fab" id="giyaFab"
                aria-label="{{ __('giya.home.card_ask') }}" aria-expanded="false" aria-controls="giyaPanel"
                data-send-url="{{ route('chatbot.send') }}"
                data-history-url="{{ route('chatbot.history') }}"
                data-ask-again="{{ __('giya.chat.ask_again') }}"
                data-ask-again-in="{{ __('giya.chat.ask_again_in', ['s' => ':s']) }}"
                data-open-route="{{ __('giya.route.open') }}">
            {{-- The assistant's own head, reused exactly: same classes, same
                 CSS, just scaled down. Restyling the guide restyles this too. --}}
            <span class="fab-head-wrap" aria-hidden="true">
                <span class="guide-face">
                    <span class="guide-ear guide-ear-l"></span>
                    <span class="guide-ear guide-ear-r"></span>

                    <span class="guide-screen">
                        <span class="guide-eye guide-eye-l"><i></i></span>
                        <span class="guide-eye guide-eye-r"><i></i></span>
                        <span class="guide-mouth"></span>
                        <span class="guide-blush guide-blush-l"></span>
                        <span class="guide-blush guide-blush-r"></span>
                    </span>

                    <span class="guide-lamp"></span>
                </span>
            </span>
            {{-- Appears on hover. The head alone is friendly but silent;
                 a line of greeting says what pressing it will do. --}}
            <span class="fab-say" id="fabSay" aria-hidden="true">
                {{ __('giya.chat.starter') }}
            </span>
        </button>

        <x-ai-panel />
    @endunless
@endauth

<script src="{{ asset('assets/js/giya.js') }}?v={{ filemtime(public_path('assets/js/giya.js')) }}"></script>
<script src="{{ asset('assets/js/giya-confirm.js') }}?v={{ filemtime(public_path('assets/js/giya-confirm.js')) }}"></script>
<script src="{{ asset('assets/js/giya-datepicker.js') }}?v={{ filemtime(public_path('assets/js/giya-datepicker.js')) }}"></script>
<script src="{{ asset('assets/js/giya-notifications.js') }}?v={{ filemtime(public_path('assets/js/giya-notifications.js')) }}"></script>
<script src="{{ asset('assets/js/giya-ai-panel.js') }}?v={{ filemtime(public_path('assets/js/giya-ai-panel.js')) }}"></script>
{{-- Before the page scripts, because the plan screens call GiyaCandles as
     part of their own first render. --}}
<script src="{{ asset('assets/js/giya-candles.js') }}?v={{ filemtime(public_path('assets/js/giya-candles.js')) }}"></script>
<script src="{{ asset('assets/js/giya-reveal.js') }}?v={{ filemtime(public_path('assets/js/giya-reveal.js')) }}"></script>
{{-- After the reveal, which it re-arms once the pictures have landed. --}}
<script src="{{ asset('assets/js/giya-page-loading.js') }}?v={{ filemtime(public_path('assets/js/giya-page-loading.js')) }}"></script>
<script src="{{ asset('assets/js/giya-navsearch.js') }}?v={{ filemtime(public_path('assets/js/giya-navsearch.js')) }}"></script>
@stack('scripts')
<script src="{{ asset('assets/js/giya-offline.js') }}?v={{ filemtime(public_path('assets/js/giya-offline.js')) }}" defer></script>
</body>
</html>
