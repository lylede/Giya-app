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

    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo/giya-logo.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/giya-icons.css') }}?v={{ filemtime(public_path('assets/css/giya-icons.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/giya.css') }}?v={{ filemtime(public_path('assets/css/giya.css')) }}">
    @stack('head')
</head>
<body @class(['app-body']) @yield('body-attr')>

<x-navbar />

@include('components.flash')

<main>
    @yield('content')
</main>

@hasSection('no-footer')
@else
    <x-footer />
@endif
@auth
    @unless (request()->routeIs('chatbot'))
        <button type="button" class="giya-fab" id="giyaFab"
                aria-label="{{ __('giya.home.card_ask') }}" aria-expanded="false" aria-controls="giyaPanel"
                data-send-url="{{ route('chatbot.send') }}">
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
@stack('scripts')
</body>
</html>
