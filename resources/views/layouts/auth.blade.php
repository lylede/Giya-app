<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>GIYA — @yield('title')</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo/giya-logo.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/giya-icons.css') }}?v={{ filemtime(public_path('assets/css/giya-icons.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/giya.css') }}?v={{ filemtime(public_path('assets/css/giya.css')) }}">
    @livewireStyles
</head>
<body class="auth-page">

<x-navbar />

<main class="auth-content">
<div class="auth-card">
    <header class="auth-header">
        <div class="auth-header-brand">
            <img src="{{ asset('images/logo/giya-logo.svg') }}" alt="GIYA" width="32" height="32">
            <span class="auth-header-brand-name">Giya</span>
        </div>
        <h1>@yield('heading')</h1>
        <p>@yield('subheading')</p>
    </header>

    <div class="auth-body">
        @if (session('success'))
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>{{ session('warning') }}</span>
            </div>
        @endif

        @yield('content')
    </div>
</div>
</main>
<script src="{{ asset('assets/js/giya.js') }}?v={{ filemtime(public_path('assets/js/giya.js')) }}"></script>
<script src="{{ asset('assets/js/giya-confirm.js') }}?v={{ filemtime(public_path('assets/js/giya-confirm.js')) }}"></script>
@livewireScripts
</body>
</html>
