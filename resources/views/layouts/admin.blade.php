<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>GIYA Admin - @yield('title')</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo/giya-logo.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/giya-icons.css') }}?v={{ filemtime(public_path('assets/css/giya-icons.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/giya.css') }}?v={{ filemtime(public_path('assets/css/giya.css')) }}">
    @stack('head')
</head>
<body>

<div class="admin-layout">

    <aside class="admin-sidebar">
        <div class="admin-sidebar-head">
            <a href="{{ route('admin.dashboard') }}" class="admin-brand">
                <img src="{{ asset('images/logo/giya-logo.svg') }}" alt="GIYA" width="32" height="32">
                <span>
                    <span class="admin-brand-name">Giya</span>
                    <span class="admin-brand-sub">Admin Panel</span>
                </span>
            </a>

            {{-- Always on screen, because a toggle you have to find is a
                 toggle nobody uses. The state is remembered, so an admin who
                 wants the rail gets it on every page. --}}
            <button type="button" class="admin-rail-toggle" id="adminRail"
                    aria-label="Collapse the menu" aria-expanded="true">
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
            </button>
        </div>

        <nav class="admin-sidebar-nav">
            <div class="admin-nav-section">Overview</div>
            <a href="{{ route('admin.dashboard') }}" @class(['admin-nav-item', 'active' => request()->routeIs('admin.dashboard')])>
                <i class="bi bi-giya-magellan"></i> <span class="admin-nav-label">Dashboard</span>
            </a>

            <div class="admin-nav-section">Management</div>
            <a href="{{ route('admin.users') }}" @class(['admin-nav-item', 'active' => request()->routeIs('admin.users')])>
                <i class="bi bi-giya-pilgrim"></i> <span class="admin-nav-label">Users</span>
            </a>
            <a href="{{ route('admin.destinations') }}" @class(['admin-nav-item', 'active' => request()->routeIs('admin.destinations')])>
                <i class="bi bi-giya-spires"></i> <span class="admin-nav-label">Destinations</span>
            </a>
            <a href="{{ route('admin.schedules') }}" @class(['admin-nav-item', 'active' => request()->routeIs('admin.schedules')])>
                <i class="bi bi-giya-candle"></i> <span class="admin-nav-label">Schedules</span>
            </a>
            <a href="{{ route('admin.feedback') }}" @class(['admin-nav-item', 'active' => request()->routeIs('admin.feedback')])>
                <i class="bi bi-giya-star"></i> <span class="admin-nav-label">Feedback</span>
            </a>
            <a href="{{ route('admin.transactions') }}" @class(['admin-nav-item', 'active' => request()->routeIs('admin.transactions')])>
                <i class="bi bi-giya-payment"></i> <span class="admin-nav-label">Transactions</span>
            </a>
            <a href="{{ route('admin.reports') }}" @class(['admin-nav-item', 'active' =>request()->routeIs('admin.reports*')])>
                <i class="bi bi-giya-tally"></i> <span class="admin-nav-label">Reports</span>
            </a>
            
            <div class="admin-nav-section">Site</div>
            <a href="{{ route('home') }}" class="admin-nav-item">
                <i class="bi bi-box-arrow-up-right"></i> <span class="admin-nav-label">View Public Site</span>
            </a>
        </nav>

        <div class="admin-sidebar-foot">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="admin-nav-item is-signout">
                    <i class="bi bi-box-arrow-right"></i> <span class="admin-nav-label">Sign Out</span>
                </button>
            </form>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <div class="admin-topbar-titles">
                <div class="admin-page-title">@yield('page-title')</div>
                <div class="admin-page-sub">@yield('page-subtitle')</div>
            </div>
            <div class="admin-topbar-user">
                <div class="nav-avatar is-admin">
                    @if (auth()->user()->avatarPath())
                        <img src="{{ auth()->user()->avatarPath() }}" alt="{{ auth()->user()->name }}">
                    @else
                        {{ auth()->user()->initials() }}
                    @endif
                </div>
                <span class="admin-topbar-name">{{ auth()->user()->name }}</span>
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
<script src="{{ asset('assets/js/giya-datepicker.js') }}?v={{ filemtime(public_path('assets/js/giya-datepicker.js')) }}"></script>
@stack('scripts')
<script src="{{ asset('assets/js/giya-admin.js') }}?v={{ filemtime(public_path('assets/js/giya-admin.js')) }}"></script>
</body>
</html>
