<!DOCTYPE html>
@php($giyaPrefs = auth()->check() ? auth()->user()->preferencesOrDefault() : null)
<html lang="en"
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
                aria-label="Ask Giya AI" aria-expanded="false" aria-controls="giyaPanel"
                data-send-url="{{ route('chatbot.send') }}">
            <span class="giya-fab-icon"><i class="bi bi-giya-star"></i></span>
            <span class="giya-fab-label">Ask Giya</span>
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
