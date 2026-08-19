@php
    $user = auth()->user();
    $links = [
        ['url' => route(auth()->check() ? 'home' : 'root'), 'label' => 'Home',    'icon' => 'house-fill',     'match' => auth()->check() ? 'home' : 'root'],
        ['url' => route('map'),                            'label' => 'Map',     'icon' => 'map-fill',       'match' => 'map'],
        ['url' => route('plan.hub'),                       'label' => 'Plan',    'icon' => 'journal-text',   'match' => 'plan.*'],
        ['url' => route('chatbot'),                        'label' => 'Chatbot', 'icon' => 'chat-dots-fill', 'match' => 'chatbot'],
        ['url' => route('profile'),                        'label' => 'Profile', 'icon' => 'person-fill',    'match' => 'profile'],
    ];
@endphp

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
                <span class="nav-bell" title="Notifications">
                    <img src="{{ asset('images/icons/bell.svg') }}" alt="Notifications" width="16" height="16">
                </span>

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

            <button type="button" class="hamburger-btn" id="navHamburger" aria-label="Toggle navigation">
                <i class="bi bi-list" style="font-size: 1.25rem;color:var(--gold)"></i>
            </button>
        </div>
    </div>

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
