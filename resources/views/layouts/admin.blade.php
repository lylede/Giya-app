<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>GIYA Admin — @yield('title')</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo/giya-logo.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/giya-icons.css') }}?v={{ filemtime(public_path('assets/css/giya-icons.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/giya.css') }}?v={{ filemtime(public_path('assets/css/giya.css')) }}">
    @stack('head')
</head>
<body>

<div class="admin-layout">

    <aside class="admin-sidebar">
        <div class="admin-sidebar-head">
            <a href="{{ route('admin.dashboard') }}" class="d-flex align-items-center gap-2">
                <img src="{{ asset('images/logo/giya-logo.svg') }}" alt="GIYA" width="32" height="32">
                <div>
                    <div style="font-family:var(--font-display);color:#fff;font-size: 1.25rem;line-height:1">Giya</div>
                    <div style="color:var(--gold);font-size: 0.625rem;letter-spacing:.08em;text-transform:uppercase">Admin Panel</div>
                </div>
            </a>
        </div>

        <nav class="admin-sidebar-nav">
            <div class="admin-nav-section">Overview</div>
            <a href="{{ route('admin.dashboard') }}" @class(['admin-nav-item', 'active' => request()->routeIs('admin.dashboard')])>
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>

            <div class="admin-nav-section">Management</div>
            <a href="{{ route('admin.users') }}" @class(['admin-nav-item', 'active' => request()->routeIs('admin.users')])>
                <i class="bi bi-people-fill"></i> Users
            </a>
            <a href="{{ route('admin.destinations') }}" @class(['admin-nav-item', 'active' => request()->routeIs('admin.destinations')])>
                <i class="bi bi-building"></i> Destinations
            </a>
            <a href="{{ route('admin.schedules') }}" @class(['admin-nav-item', 'active' => request()->routeIs('admin.schedules')])>
                <i class="bi bi-calendar-event-fill"></i> Schedules
            </a>
            <a href="{{ route('admin.feedback') }}" @class(['admin-nav-item', 'active' => request()->routeIs('admin.feedback')])>
                <i class="bi bi-chat-dots-fill"></i> Feedback
            </a>
            <a href="{{ route('admin.transactions') }}" @class(['admin-nav-item', 'active' => request()->routeIs('admin.transactions')])>
                <i class="bi bi-credit-card-fill"></i> Transactions
            </a>

            <div class="admin-nav-section">Site</div>
            <a href="{{ route('home') }}" class="admin-nav-item">
                <i class="bi bi-box-arrow-up-right"></i> View Public Site
            </a>
        </nav>

        <div style="padding:12px 8px;border-top:1px solid rgba(255,255,255,0.08)">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="admin-nav-item" style="color:#E58C8C">
                    <i class="bi bi-box-arrow-right"></i> Sign Out
                </button>
            </form>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <div>
                <div style="font-family:var(--font-display);font-size: 1.125rem;color:var(--text)">@yield('page-title')</div>
                <div style="font-size: 0.75rem;color:var(--text-muted)">@yield('page-subtitle')</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="nav-avatar" style="border-color:var(--primary)">
                    @if (auth()->user()->avatarPath())
                        <img src="{{ auth()->user()->avatarPath() }}" alt="{{ auth()->user()->name }}">
                    @else
                        {{ auth()->user()->initials() }}
                    @endif
                </div>
                <span style="font-size: 0.8125rem;color:var(--text)">{{ auth()->user()->name }}</span>
            </div>
        </header>

        <div class="admin-content">
            @include('components.flash')
            @yield('content')
        </div>
    </div>
</div>
<script src="{{ asset('assets/js/giya.js') }}?v={{ filemtime(public_path('assets/js/giya.js')) }}"></script>
<script src="{{ asset('assets/js/giya-confirm.js') }}?v={{ filemtime(public_path('assets/js/giya-confirm.js')) }}"></script>
@stack('scripts')
</body>
</html>
