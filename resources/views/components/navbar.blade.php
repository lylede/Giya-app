@php
    $user = auth()->user();
    $links = [
        ['url' => route(auth()->check() ? 'home' : 'root'), 'label' => 'Home',    'icon' => 'house-fill',     'match' => auth()->check() ? 'home' : 'root'],
        ['url' => route('map'),                            'label' => 'Map',     'icon' => 'map-fill',       'match' => 'map'],
        ['url' => route('plan.hub'),                       'label' => 'Plan',    'icon' => 'journal-text',   'match' => 'plan.*'],
        ['url' => route('profile'),                        'label' => 'Profile', 'icon' => 'person-fill',    'match' => 'profile'],
    ];
@endphp

{{--
    These rules live here, not in giya.css, for the same reason the flash
    message carries its own script: the menu must not depend on another file
    having been updated. Whenever this component renders, the CSS that opens it
    renders with it.
--}}
<style>
    /* The checkbox holds the state and is never seen. */
    .giya-nav .nav-toggle-input {
        position: absolute !important;
        opacity: 0 !important;
        width: 1px !important; height: 1px !important;
        margin: -1px !important; padding: 0 !important;
        overflow: hidden !important; clip: rect(0 0 0 0) !important;
        white-space: nowrap !important; border: 0 !important;
    }

    /* The label is the visible button. */
    .giya-nav label.hamburger-btn {
        cursor: pointer;
        -webkit-user-select: none; user-select: none;
        -webkit-tap-highlight-color: transparent;
        position: relative; z-index: 5;
    }

    /* Open the menu. Both selectors, so either structure works. */
    .giya-nav .nav-toggle-input:checked ~ .mobile-menu,
    .giya-nav .nav-toggle-input:checked ~ * .mobile-menu {
        display: block !important;
    }

    .giya-nav:has(.nav-toggle-input:checked) label.hamburger-btn {
        background: rgba(215,169,74,.34);
    }

    /* The bar is only relevant below the desktop breakpoint. */
    /* Full-screen close target, only present while the menu is open. */
    .giya-nav .menu-scrim { display: none; }
    .giya-nav .nav-toggle-input:checked ~ .menu-scrim {
        display: block;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        z-index: 1;
        background: rgba(36,28,24,.35);
        cursor: default;
    }

    /* The menu must sit above the scrim. */
    .giya-nav .mobile-menu { position: relative; z-index: 2; }

    /* Swap the glyph with CSS, so the script is not needed for that either. */
    .giya-nav label.hamburger-btn .icon-close { display: none; }
    .giya-nav .nav-toggle-input:checked ~ .nav-inner label.hamburger-btn .icon-open,
    .giya-nav:has(.nav-toggle-input:checked) label.hamburger-btn .icon-open { display: none; }
    .giya-nav:has(.nav-toggle-input:checked) label.hamburger-btn .icon-close { display: inline-block; }

    @media (min-width: 768px) {
        .giya-nav .nav-toggle-input:checked ~ .mobile-menu,
        .giya-nav .nav-toggle-input:checked ~ .menu-scrim { display: none !important; }
    }
</style>



<nav class="giya-nav">
    <div class="nav-inner">

        <a href="{{ route(auth()->check() ? 'home' : 'root') }}" class="nav-logo">
            <span class="nav-logo-icon">
                <img src="{{ asset('images/logo/giya-icon.svg') }}" alt="" width="32" height="32">
            </span>
            <span class="nav-logo-name">Giya</span>
            <span class="nav-logo-badge d-none d-sm-inline">Metro Cebu</span>
        </a>

        <div class="nav-links">
            @foreach ($links as $link)
                <a href="{{ $link['url'] }}"
                   @class(['nav-link', 'active' => request()->routeIs($link['match'])])>
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>

        <div class="nav-right">
            @auth
                @php($unreadCount = \App\Models\Notification::where('user_id', $user->id)->where('is_read', false)->count())

                <div class="nav-bell-wrap">
                    <button type="button" class="nav-bell" id="navBell"
                            aria-haspopup="true" aria-expanded="false" aria-label="Notifications">
                        <img src="{{ asset('images/icons/bell.svg') }}" alt="" width="16" height="16">
                        <span class="nav-bell-dot" id="navBellDot" @class(['is-hidden' => $unreadCount === 0])>
                            {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                        </span>
                    </button>

                    <div class="notif-panel" id="notifPanel" role="dialog" aria-label="Notifications">
                        <div class="notif-head">
                            <span>Notifications</span>
                            <button type="button" class="notif-readall" id="notifReadAll">Mark all read</button>
                        </div>
                        <div class="notif-body" id="notifBody">
                            <p class="notif-empty">Loading…</p>
                        </div>
                    </div>
                </div>

                <a href="{{ route('profile') }}" class="nav-avatar-wrap">
                    <span class="nav-avatar">
                    @if ($user->avatarPath())
                        <img src="{{ $user->avatarPath() }}" alt="{{ $user->name }}">
                    @else
                        {{ $user->initials() }}
                    @endif
                </span>
                    <span class="nav-username">{{ $user->firstName() }}</span>
                </a>

                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn-signout">Sign Out</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn-signout">Sign In</a>
                <a href="{{ route('register') }}" class="btn btn-gold btn-sm">Create Account</a>
            @endauth

            {{--
                The menu is driven by this checkbox, not by JavaScript.

                A script that fails to load, errors earlier, or is dropped by a
                Livewire re-render leaves a button that looks fine and does
                nothing. A checkbox cannot fail that way: the browser toggles it,
                and CSS shows the menu.

                giya.js still syncs the icon when it is available; the menu works
                either way.
            --}}
            <label for="navHamburger" class="hamburger-btn" role="button" tabindex="0"
                   aria-label="Toggle navigation" aria-controls="navMobileMenu">
                <i class="bi bi-list icon-open"  style="font-size: 1.25rem;color:var(--gold)"></i>
                <i class="bi bi-x    icon-close" style="font-size: 1.25rem;color:var(--gold)"></i>
            </label>
        </div>
    </div>

    <input type="checkbox" id="navHamburger" class="nav-toggle-input">

    {{-- Tapping anywhere outside closes the menu. A label pointed at the same
         checkbox does that with no JavaScript involved. --}}
    <label for="navHamburger" class="menu-scrim" aria-hidden="true" tabindex="-1"></label>

    <div class="mobile-menu" id="navMobileMenu">
        <div class="mobile-nav-links">
            @foreach ($links as $link)
                <a href="{{ $link['url'] }}"
                   @class(['mobile-nav-link', 'active' => request()->routeIs($link['match'])])>
                    <i class="bi bi-{{ $link['icon'] }} me-2"></i>{{ $link['label'] }}
                </a>
            @endforeach

            @auth
                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                    @csrf
                    <button type="submit" class="mobile-nav-link w-100 text-start bg-transparent border-0">
                        <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="mobile-nav-link">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </a>
                <a href="{{ route('register') }}" class="mobile-nav-link">
                    <i class="bi bi-person-plus me-2"></i>Create Account
                </a>
            @endauth
        </div>
    </div>
</nav>
