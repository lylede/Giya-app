@extends('layouts.app')
@section('title', __('giya.nav.profile'))

@section('content')
@php $activeTab = request('tab', 'overview'); @endphp
{{-- ───────────────────────── Profile header ─────────────────────────── --}}
<header class="profile-header-card">
    <span style="position:absolute;top:-40px;right:-40px;width:200px;height:200px;border-radius:50%;background:rgba(215,169,74,0.1)"></span>
    <span style="position:absolute;bottom:-60px;left:-50px;width:180px;height:180px;border-radius:50%;background:rgba(215,169,74,0.07)"></span>

    <div style="position:relative;max-width:900px;margin:0 auto;padding:36px 20px 28px">

        <div class="d-flex align-items-center gap-4 mb-4 flex-wrap">
            @if ($user->avatarPath())
                <img src="{{ $user->avatarPath() }}" alt="{{ $user->name }}"
                     style="width:80px;height:80px;border-radius:18px;border:3px solid rgba(215,169,74,0.55);object-fit:cover;flex-shrink:0">
            @else
                <span style="width:80px;height:80px;border-radius:18px;border:3px solid rgba(215,169,74,0.55);background:var(--gold);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size: 2rem;font-weight:700;color:var(--primary-dark);flex-shrink:0">
                    {{ $user->initials() }}
                </span>
            @endif
            <div style="flex:1;min-width:220px">
                <h1 style="font-family:var(--font-display);color:#fff;font-size: 1.625rem;line-height:1.2;margin:0">{{ $user->name }}</h1>
                <p style="color:rgba(255,255,255,0.7);font-size: 0.8125rem;margin:3px 0 0">{{ $user->email }}</p>
                <p class="d-flex align-items-center gap-1" style="color:rgba(255,255,255,0.55);font-size: 0.75rem;margin:5px 0 0">
                    <i class="bi bi-geo-alt-fill" style="color:var(--gold);font-size: 0.6875rem"></i>
                    Cebu City, Philippines · Member since
                    {{ ($user->member_since ?? $user->created_at)?->format('F Y') }}
                </p>
            </div>
            <button type="button" class="btn btn-ghost btn-ghost-inverse" data-modal-open="editProfileModal">
                <i class="bi bi-pencil-square"></i> {{ __('giya.profile.edit') }}
            </button>
        </div>

        <div style="display:grid;grid-template-columns:repeat(3,1fr);text-align:center">
            @foreach ([
                [$user->total_pilgrimages,      __('giya.profile.pilgrimages'), __('giya.profile.completed')],
                [$user->total_churches_visited, __('giya.church.churches'),     __('giya.profile.visited')],
                [$reviewCount,                  __('giya.profile.reviews'),     __('giya.profile.written')],
            ] as $i => [$value, $l1, $l2])
                <div style="padding:8px 4px;{{ $i < 2 ? 'border-right:1px solid rgba(255,255,255,0.1)' : '' }}">
                    <div class="profile-stat-val">{{ $value }}</div>
                    <div class="profile-stat-lbl">{{ $l1 }}<br>{{ $l2 }}</div>
                </div>
            @endforeach
        </div>
    </div>
</header>

{{-- ───────────────────────────── Tabs ───────────────────────────────── --}}
<div style="background:#fff;border-bottom:1px solid var(--border);position:sticky;top:64px;z-index:100">
    <div style="max-width:900px;margin:0 auto;display:flex;padding:0 20px;overflow-x:auto">
        @foreach ([['overview',__('giya.profile.overview')],['visits',__('giya.profile.visit_history')],['itineraries',__('giya.profile.itineraries')],['favorites',__('giya.profile.favorites')],['preferences',__('giya.profile.preferences')]] as $i => [$id, $label])
            <button type="button" @class(['profile-tab', 'is-active' => $id === $activeTab])
                    data-tab="{{ $id }}" onclick="GiyaProfile.show('{{ $id }}', this)">{{ $label }}</button>
        @endforeach
    </div>
</div>

<div style="max-width:900px;margin:0 auto;padding:24px 20px 48px">

    {{-- ── Overview ── --}}
    <section class="profile-panel @if($activeTab !== 'overview') d-none @endif" id="panel-overview">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px" class="profile-grid">

            <div class="card card-body">
                <div class="form-label-sm" style="margin-bottom:14px">{{ __('giya.profile.account_info') }}</div>
                @foreach ([
                    [__('giya.profile.full_name'),    $user->name],
                    [__('giya.profile.email'),        $user->email],
                    [__('giya.profile.role'),         ucfirst($user->role)],
                    [__('giya.profile.member_since'), ($user->member_since ?? $user->created_at)?->format('M Y')],
                    [__('giya.profile.plan_label'),   $user->is_premium ? __('giya.upgrade.premium') : __('giya.profile.free_plan')],
                ] as $i => [$label, $value])
                    <div class="d-flex justify-content-between align-items-center"
                         style="padding:9px 0;{{ $i < 4 ? 'border-bottom:1px solid var(--border-light)' : '' }}">
                        <span style="font-size: 0.8125rem;color:var(--text-muted)">{{ $label }}</span>
                        <span style="font-size: 0.8125rem;font-weight:600;color:var(--text);text-align:right">{{ $value }}</span>
                    </div>
                @endforeach

                {{--
                    Where the free allowance stands.

                    Every itinerary counted here is one the devotee has saved,
                    of either kind - a custom route and a Visita Iglesia both
                    take a slot. That needs saying, because the Pilgrimages
                    figure at the top of this page counts only COMPLETED trips,
                    so a devotee who has just planned three still sees 0 there
                    and reasonably concludes nothing was recorded.
                --}}
                @php
                    $freeLimit = \App\Http\Controllers\ItineraryController::FREE_LIMIT;
                    $used      = $itinerariesUsed;   // deleted ones included
                    $left      = max(0, $freeLimit - $used);
                    $atLimit   = $used >= $freeLimit;
                @endphp

                <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border-light)">

                    @if ($user->is_premium)
                        <div style="display:flex;align-items:center;gap:10px">
                            <i class="bi bi-gem" style="color:var(--gold);font-size:1.125rem"></i>
                            <div>
                                <div style="font-size:.8125rem;font-weight:700;color:var(--text)">
                                    {{ __('giya.profile.unlimited') }}
                                </div>
                                <div style="font-size:.75rem;color:var(--text-muted)">
                                    {{ __('giya.profile.planned', ['count' => $used]) }}
                                    @if ($premiumUntil) · {{ __('giya.profile.premium_until', ['date' => $premiumUntil->format('M j, Y')]) }} @endif
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="d-flex justify-content-between align-items-center" style="margin-bottom:8px">
                            <span style="font-size:.8125rem;color:var(--text-muted)">{{ __('giya.profile.free_itin') }}</span>
                            <span style="font-size:.8125rem;font-weight:700;color:{{ $atLimit ? 'var(--primary)' : 'var(--text)' }}">
                                {{ __('giya.profile.used_of', ['used' => $used, 'limit' => $freeLimit]) }}
                            </span>
                        </div>

                        <div style="height:6px;border-radius:999px;background:var(--border-light);overflow:hidden">
                            <div style="height:100%;border-radius:999px;width:{{ min(100, $freeLimit ? $used / $freeLimit * 100 : 0) }}%;
                                        background:{{ $atLimit
                                            ? 'linear-gradient(to right,#8E3B2F,#C04030)'
                                            : 'linear-gradient(to right,#D7A94A,#F0C76C)' }}"></div>
                        </div>

                        <p style="font-size:.75rem;color:var(--text-muted);margin:8px 0 0;line-height:1.55">
                            @if ($atLimit)
                                {{ __('giya.profile.at_limit_note', ['limit' => $freeLimit]) }}
                            @else
                                {{ trans_choice('giya.profile.slots_note', $left, ['count' => $left]) }}
                                {{ __('giya.profile.slots_hint') }}
                            @endif
                        </p>

                        {{-- Available before the limit is reached, so a devotee who
                             already knows they want Premium can buy it now. --}}
                        <a href="{{ route('upgrade') }}"
                           class="btn {{ $atLimit ? 'btn-primary' : 'btn-outline-gold' }}"
                           style="width:100%;justify-content:center;margin-top:14px">
                            <i class="bi bi-gem"></i><span>{{ __('giya.profile.go_premium') }}</span>
                        </a>
                    @endif
                </div>
            </div>

            {{--
                The two cards sit in a stretching grid, so this one is always as
                tall as Account Information beside it. What changed is where the
                spare height goes: the badge grid now takes it, rather than the
                badges staying their own size and leaving a band of nothing
                underneath. The rows share whatever is left over, so the card
                ends level with its neighbour whether that neighbour is showing
                a Premium line or a free account's usage bar and button.
            --}}
            <div class="card card-body" style="display:flex;flex-direction:column">
                <div class="form-label-sm" style="margin-bottom:14px">{{ __('giya.profile.achievements') }}</div>
                {{-- Rows grow into the spare height but stop at 132px: let them
                     take all of it and a free account's taller card stretched
                     each badge to 183px, leaving the icon adrift in the middle
                     of a mostly empty tile. Whatever is left over after the cap
                     becomes even spacing above, between and below the rows, so
                     the card still ends level with its neighbour. --}}
                <div style="display:grid;grid-template-columns:repeat(3,1fr);
                            grid-template-rows:repeat(2,minmax(74px,132px));
                            gap:10px;flex:1;min-height:0;align-content:space-evenly">
                    @foreach ([
                        /*
                           Drawn for these badges rather than picked from a
                           general-purpose set, so each one says what it is for
                           instead of standing in for it: one footprint for the
                           first church, three spires for five, a votive candle
                           for devotion, the seven churches as seven lights,
                           the road ahead for distance walked, and Magellan's
                           Cross - which is in Cebu, a few minutes from the
                           Basilica - for having seen fifteen.
                        */
                        ['giya-footprint', __('giya.profile.ach_first'),    $user->total_churches_visited >= 1],
                        ['giya-spires',    __('giya.profile.ach_hopper'),   $user->total_churches_visited >= 5],
                        ['giya-candle',    __('giya.profile.ach_devoted'),  $user->total_pilgrimages >= 3],
                        ['giya-seven',     __('giya.plan.visita'),          $user->total_churches_visited >= 7],
                        ['giya-road',      __('giya.profile.ach_traveler'), $user->total_km_walked >= 50],
                        ['giya-magellan',  __('giya.profile.ach_explorer'), $user->total_churches_visited >= 15],
                    ] as [$icon, $name, $earned])
                        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;padding:12px 6px;border-radius:12px;text-align:center;border:1.5px solid var(--border);{{ $earned ? 'background:rgba(215,169,74,0.09);border-color:rgba(215,169,74,0.35)' : 'background:var(--bg);opacity:.45' }}">
                            <i class="bi bi-{{ $icon }}" style="font-size: 1.25rem;color:{{ $earned ? 'var(--gold)' : 'var(--text-muted)' }}"></i>
                            <span style="font-size: 0.625rem;font-weight:700;color:var(--text);line-height:1.25">{{ $name }}</span>
                            @unless ($earned)<i class="bi bi-lock-fill" style="font-size: 0.5625rem;color:var(--text-muted)"></i>@endunless
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="form-label-sm" style="margin:0">{{ __('giya.profile.recent_visits') }}</div>
                <button type="button" class="section-link" onclick="GiyaProfile.show('visits', document.querySelector('[data-tab=visits]'))">
                    {{ __('giya.common.view_all') }} →
                </button>
            </div>

            @forelse ($visits->take(4) as $visit)
                <div class="history-item" style="margin-bottom:8px">
                    {{-- The church's own photograph. It is already loaded for this
                     row, and it tells a devotee which church this was far
                     faster than the same building glyph on every line. --}}
                @if ($visit->church)
                    <img class="history-thumb" src="{{ $visit->church->imagePath() }}" alt="" loading="lazy">
                @else
                    <span class="history-icon"><i class="bi bi-building" style="font-size: 1.125rem;color:var(--primary)"></i></span>
                @endif
                    <div style="flex:1;min-width:0">
                        <div style="font-size: 0.875rem;font-weight:700;color:var(--text)">{{ $visit->church_name }}</div>
                        <div style="font-size: 0.75rem;color:var(--text-muted)">{{ $visit->visited_at?->format('M j, Y') }}</div>
                    </div>
                    @if ($visit->rating)
                        <x-stars :rating="$visit->rating" />
                    @endif
                </div>
            @empty
                <x-empty-state icon="building" :title="__('giya.profile.no_visits')"
                               :desc="__('giya.profile.no_visits_d')" />
            @endforelse
        </div>
    </section>

    {{-- ── Visit history ── --}}
    <section class="profile-panel @if($activeTab !== 'visits') d-none @endif" id="panel-visits">
        <h2 class="section-title" style="font-size: 1.25rem">{{ __('giya.profile.visit_history') }}</h2>

        @forelse ($visits as $visit)
            <div class="history-item">
                {{-- The church's own photograph. It is already loaded for this
                     row, and it tells a devotee which church this was far
                     faster than the same building glyph on every line. --}}
                @if ($visit->church)
                    <img class="history-thumb" src="{{ $visit->church->imagePath() }}" alt="" loading="lazy">
                @else
                    <span class="history-icon"><i class="bi bi-building" style="font-size: 1.125rem;color:var(--primary)"></i></span>
                @endif
                <div class="history-body">
                    <div class="history-name">{{ $visit->church_name }}</div>
                    <div class="d-flex align-items-center gap-1" style="font-size: 0.75rem;color:var(--text-muted);margin-top:2px">
                        <img src="{{ asset('images/icons/location.svg') }}" alt="" width="10" height="10">
                        Cebu · {{ $visit->visited_at?->format('M j, Y') }}
                    </div>
                </div>
                @if ($visit->rating)
                    <span class="history-actions">
                        <x-stars :rating="$visit->rating" />
                        <span style="font-size: 0.75rem;color:var(--text-muted)">{{ __('giya.profile.reviewed') }}</span>
                    </span>
                @else
                    <span class="history-actions">
                        <button type="button" class="btn btn-ghost btn-sm"
                                style="color:var(--primary);font-weight:700"
                                onclick="GiyaProfile.review({{ $visit->id }}, @js($visit->church_name))">
                            {{ __('giya.profile.review') }} <i class="bi bi-chevron-right" style="font-size: 0.625rem"></i>
                        </button>
                    </span>
                @endif
            </div>
        @empty
            <x-empty-state icon="clock-history" :title="__('giya.profile.no_visits')"
                           :desc="__('giya.profile.no_visits_d2')" />
        @endforelse
    </section>

    {{-- ── Itineraries ── --}}
    <section class="profile-panel @if($activeTab !== 'itineraries') d-none @endif" id="panel-itineraries">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <h2 class="section-title" style="font-size: 1.25rem;margin:0">{{ __('giya.plan.my_title') }}</h2>
            <a href="{{ route('plan.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> {{ __('giya.profile.new_itinerary') }}
            </a>
        </div>

        @forelse ($itineraries as $itinerary)
            <div class="history-item">
                <span class="history-icon">
                    <i class="bi bi-{{ $itinerary->type === 'Visita Iglesia' ? 'giya-seven' : 'giya-route' }}"
                       style="font-size: 1.125rem;color:var(--primary)"></i>
                </span>
                <div style="flex:1;min-width:0">
                    <div style="font-size: 0.875rem;font-weight:700;color:var(--text)">{{ $itinerary->name }}</div>
                    <div style="font-size: 0.75rem;color:var(--text-muted);margin-top:2px">
                        {{ $itinerary->total_stops }} stops
                        @if ($itinerary->scheduled_date) · {{ $itinerary->scheduled_date->format('M j, Y') }} @endif
                    </div>
                </div>
                <span class="badge status-{{ $itinerary->status }}">{{ $itinerary->status }}</span>
                <a href="{{ route('plan.show', $itinerary) }}" style="color:var(--primary)" aria-label="{{ __('giya.profile.open_itin') }}">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        @empty
            <x-empty-state icon="giya-route" :title="__('giya.plan.no_itineraries')"
                           :desc="__('giya.profile.no_itin_d')">
                <a href="{{ route('plan.hub') }}" class="btn btn-primary btn-sm mt-3">{{ __('giya.profile.open_plan_hub') }}</a>
            </x-empty-state>
        @endforelse
    </section>

    {{-- ── Settings ── --}}
    {{-- ── Favorites ── --}}
    <section class="profile-panel @if($activeTab !== 'favorites') d-none @endif" id="panel-favorites">
        <h2 class="section-title" style="font-size: 1.25rem">{{ __('giya.profile.favorites') }}</h2>

        @forelse ($favorites as $favorite)
            @continue (! $favorite->church)
            <div class="history-item" data-favorite-row="{{ $favorite->church_id }}">
                {{-- Photograph, with the heart tucked into its corner: the row
                     is already in the Favorites tab, so a whole tile spent
                     saying "favourite" told the devotee nothing new. --}}
                <span class="history-thumb-wrap">
                    <img class="history-thumb" src="{{ $favorite->church->imagePath() }}" alt="" loading="lazy">
                    <i class="bi bi-heart-fill history-thumb-badge"></i>
                </span>
                <div class="history-body">
                    <div class="history-name">{{ $favorite->church->name }}</div>
                    <div style="font-size: 0.75rem;color:var(--text-muted);margin-top:2px">
                        <i class="bi bi-geo-alt-fill" style="font-size: 0.625rem;color:var(--gold)"></i>
                        {{ $favorite->church->location }}
                        @if ($favorite->church->rating > 0)
                            · <i class="bi bi-star-fill" style="font-size: 0.625rem;color:var(--gold)"></i>
                            {{ number_format($favorite->church->rating, 1) }}
                        @endif
                    </div>
                </div>
                <span class="history-actions">
                    <a href="{{ route('churches.show', $favorite->church_id) }}" class="btn btn-ghost btn-sm">{{ __('giya.common.view') }}</a>
                    <button type="button" class="btn btn-ghost btn-sm" style="color:#D4183D"
                            onclick="GiyaProfile.unfavorite({{ $favorite->church_id }})"
                            aria-label="{{ __('giya.profile.remove_fav') }}">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </span>
            </div>
        @empty
            <x-empty-state icon="heart" :title="__('giya.profile.no_favorites')"
                           :desc="__('giya.profile.no_fav_d')">
                <a href="{{ route('map') }}" class="btn btn-primary btn-sm mt-3">{{ __('giya.profile.find_churches') }}</a>
            </x-empty-state>
        @endforelse
    </section>

    {{-- ── Preferences ── --}}
    <section class="profile-panel @if($activeTab !== 'preferences') d-none @endif" id="panel-preferences">
        <h2 class="section-title" style="font-size: 1.25rem">{{ __('giya.profile.preferences') }}</h2>

        <form method="POST" action="{{ route('profile.preferences') }}" id="prefForm"
              data-save-url="{{ route('profile.preference') }}">
            @csrf @method('PATCH')

            {{-- Appearance --}}
            <div class="card card-body mb-3">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-brightness-high-fill" style="color:var(--gold)"></i>
                    <span style="font-size: 0.9375rem;font-weight:700;color:var(--text)">{{ __('giya.profile.appearance') }}</span>
                </div>

                <label class="form-label-sm" style="display:block;margin-bottom:6px">{{ __('giya.profile.font_size') }}</label>
                <div class="d-flex gap-2 mb-3" role="radiogroup" aria-label="{{ __('giya.profile.font_aria') }}">
                    @foreach (['Small' => __('giya.profile.small'), 'Medium' => __('giya.profile.medium'), 'Large' => __('giya.profile.large')] as $size => $sizeLabel)
                        <label @class(['pref-choice', 'is-active' => $prefs->font_size === $size])>
                            <input type="radio" name="font_size" value="{{ $size }}"
                                   @checked($prefs->font_size === $size) class="visually-hidden">
                            {{ $sizeLabel }}
                        </label>
                    @endforeach
                </div>

                <label class="form-label-sm" style="display:block;margin-bottom:6px">{{ __('giya.profile.theme_style') }}</label>
                <div class="d-flex gap-2" role="radiogroup" aria-label="{{ __('giya.profile.theme_aria') }}">
                    @foreach ([['Light', 'sun-fill', __('giya.profile.light')], ['Dark', 'moon-fill', __('giya.profile.dark')]] as [$theme, $icon, $themeLabel])
                        <label @class(['pref-choice', 'is-active' => $prefs->theme_style === $theme])>
                            <input type="radio" name="theme_style" value="{{ $theme }}"
                                   @checked($prefs->theme_style === $theme) class="visually-hidden">
                            <i class="bi bi-{{ $icon }}"></i> {{ $themeLabel }}
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Language --}}
            <div class="card card-body mb-3">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-globe2" style="color:var(--gold)"></i>
                    <span style="font-size: 0.9375rem;font-weight:700;color:var(--text)">{{ __('giya.profile.language') }}</span>
                </div>

                <label class="form-label-sm" for="pref-language">{{ __('giya.profile.display_lang') }}</label>
                <select id="pref-language" name="language" class="giya-input">
                    @foreach (['English', 'Cebuano', 'Filipino'] as $language)
                        <option value="{{ $language }}" @selected($prefs->language === $language)>{{ $language }}</option>
                    @endforeach
                </select>
                <p style="font-size: 0.75rem;color:var(--text-muted);margin:6px 0 0">
                    {{ __('giya.profile.lang_note') }}
                </p>
            </div>

            {{-- Notifications --}}
            <div class="card card-body mb-3">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-bell-fill" style="color:var(--gold)"></i>
                    <span style="font-size: 0.9375rem;font-weight:700;color:var(--text)">{{ __('giya.profile.notifications') }}</span>
                </div>

                @foreach ([
                    ['notify_mass_schedule',     __('giya.profile.notify_mass')],
                    ['notify_itinerary',         __('giya.profile.notify_itin')],
                    ['notify_feast_day',         __('giya.profile.notify_feast')],
                    ['notify_saved_destination', __('giya.profile.notify_saved')],
                ] as [$field, $label])
                    <label class="pref-toggle-row">
                        <span>{{ $label }}</span>
                        <span class="pref-switch">
                            <input type="checkbox" name="{{ $field }}" value="1" @checked($prefs->$field)>
                            <span class="pref-switch-track"></span>
                        </span>
                    </label>
                @endforeach
            </div>

            {{-- No Save button: every control writes as soon as it changes.
                 The submit is kept for a browser with JavaScript disabled. --}}
            <noscript>
                <button type="submit" class="btn btn-primary btn-w-full">{{ __('giya.profile.save_prefs') }}</button>
            </noscript>

            <p class="pref-status" id="prefStatus" role="status" aria-live="polite"></p>
        </form>

        {{-- Account actions kept from the old Settings tab --}}
        <h2 class="section-title" style="font-size: 1.25rem;margin-top:28px">{{ __('giya.profile.account') }}</h2>
        <div class="card card-body">
            @foreach ([
                ['shield-lock-fill', __('giya.profile.change_pw'), __('giya.profile.pw_hint_card'), 'changePasswordModal'],
                ['pencil-square',    __('giya.profile.edit'),      __('giya.profile.edit_hint'),   'editProfileModal'],
            ] as [$icon, $label, $desc, $target])
                <button type="button" class="d-flex align-items-center gap-3 w-100 text-start bg-transparent border-0"
                        style="padding:12px;border-radius:12px" data-modal-open="{{ $target }}">
                    <span style="width:38px;height:38px;border-radius:10px;background:var(--gold-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="bi bi-{{ $icon }}" style="color:var(--primary)"></i>
                    </span>
                    <span style="flex:1">
                        <span style="display:block;font-size: 0.875rem;font-weight:600;color:var(--text)">{{ $label }}</span>
                        <span style="display:block;font-size: 0.75rem;color:var(--text-muted)">{{ $desc }}</span>
                    </span>
                    <i class="bi bi-chevron-right" style="color:var(--text-muted)"></i>
                </button>
            @endforeach

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="d-flex align-items-center gap-3 w-100 text-start bg-transparent border-0"
                        style="padding:12px;border-radius:12px">
                    <span style="width:38px;height:38px;border-radius:10px;background:rgba(212,24,61,0.08);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="bi bi-box-arrow-right" style="color:#D4183D"></i>
                    </span>
                    <span style="flex:1">
                        <span style="display:block;font-size: 0.875rem;font-weight:600;color:#D4183D">{{ __('giya.nav.sign_out') }}</span>
                        <span style="display:block;font-size: 0.75rem;color:var(--text-muted)">{{ __('giya.profile.sign_out_note') }}</span>
                    </span>
                    <i class="bi bi-chevron-right" style="color:var(--text-muted)"></i>
                </button>
            </form>
        </div>
    </section>

</div>{{-- /panel wrapper --}}

{{-- ───────────────────────────── Modals ─────────────────────────────── --}}
<div class="modal" id="editProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border:none;border-radius:var(--radius-2xl);padding:28px">
            <div class="modal-title"><i class="bi bi-pencil-fill" style="color:var(--primary)"></i> {{ __('giya.profile.edit') }}</div>
            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                @csrf @method('PATCH')

                {{--
                    The photograph is the control.

                    It was a thumbnail sitting beside a file input and a Choose
                    photo button - three things to explain one action. Clicking
                    the picture is what people already try, so the picture is
                    now the button: a label wrapping a hidden input, with the
                    camera appearing over it on hover or focus. It takes a drop
                    too, since dragging a photo onto it is the other thing
                    people try.
                --}}
                <div class="field">
                    <label class="form-label-sm" for="pf-avatar">{{ __('giya.profile.photo') }}</label>

                    <div class="avatar-edit">
                        <label class="avatar-drop" id="avatarDrop" for="pf-avatar"
                               tabindex="0" role="button"
                               aria-label="{{ __('giya.profile.photo_aria') }}">
                            <span id="avatarPreviewWrap" class="avatar-drop-face">
                                @if ($user->avatarPath())
                                    <img id="avatarPreview" src="{{ $user->avatarPath() }}" alt="">
                                @else
                                    <span id="avatarPreview" class="avatar-drop-initials">{{ $user->initials() }}</span>
                                @endif
                            </span>

                            <span class="avatar-drop-veil" aria-hidden="true">
                                <i class="bi bi-camera-fill"></i>
                                <span>{{ __('giya.common.change') }}</span>
                            </span>
                        </label>

                        <div class="avatar-edit-text">
                            <p class="avatar-edit-lead" id="avatarName">{{ __('giya.profile.photo_hint') }}</p>
                            <p class="avatar-edit-hint">{{ __('giya.profile.avatar_hint') }}</p>
                        </div>
                    </div>

                    <input id="pf-avatar" type="file" name="avatar" class="visually-hidden"
                           accept="image/jpeg,image/png,image/webp">

                    @error('avatar')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="field">
                    <label class="form-label-sm" for="pf-name">{{ __('giya.profile.full_name') }}</label>
                    <input id="pf-name" type="text" name="name" class="giya-input"
                           value="{{ old('name', $user->name) }}" maxlength="100" required>
                    @error('name')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="field">
                    <label class="form-label-sm" for="pf-email">{{ __('giya.profile.email') }}</label>
                    <input id="pf-email" type="email" name="email" class="giya-input"
                           value="{{ old('email', $user->email) }}" maxlength="150" required>
                    @error('email')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary" style="flex:1">{{ __('giya.profile.save_changes') }}</button>
                    <button type="button" class="btn btn-outline" style="flex:1" data-modal-close>{{ __('giya.common.cancel') }}</button>
                </div>
            </form>
            @if ($user->avatarPath())
                <form method="POST" action="{{ route('profile.avatar.remove') }}" style="margin-top:10px">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-ghost btn-sm" style="color:#D4183D">
                        <i class="bi bi-trash3"></i> {{ __('giya.profile.remove_photo') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>

<div class="modal" id="changePasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border:none;border-radius:var(--radius-2xl);padding:28px">
            <div class="modal-title"><i class="bi bi-shield-lock-fill" style="color:var(--primary)"></i> {{ __('giya.profile.change_pw') }}</div>
            <form method="POST" action="{{ route('profile.password') }}">
                @csrf @method('PATCH')
                <div class="field">
                    <label class="form-label-sm" for="cp-current">{{ __('giya.profile.current_pw') }}</label>
                    <div class="input-wrap">
                        <input id="cp-current" type="password" name="current_password" class="giya-input" required>
                        <button type="button" class="input-suffix" onclick="giyaTogglePassword('cp-current', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    @error('current_password')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label class="form-label-sm" for="cp-new">{{ __('giya.profile.new_pw') }}</label>
                    <div class="input-wrap">
                        <input id="cp-new" type="password" name="password" class="giya-input"
                               placeholder="{{ __('giya.profile.pw_hint') }}" required>
                        <button type="button" class="input-suffix" onclick="giyaTogglePassword('cp-new', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    @error('password')<span class="field-error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label class="form-label-sm" for="cp-confirm">{{ __('giya.profile.confirm_pw') }}</label>
                    <div class="input-wrap">
                        <input id="cp-confirm" type="password" name="password_confirmation" class="giya-input" required>
                        <button type="button" class="input-suffix" onclick="giyaTogglePassword('cp-confirm', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary" style="flex:1">{{ __('giya.profile.update_pw') }}</button>
                    <button type="button" class="btn btn-outline" style="flex:1" data-modal-close>{{ __('giya.common.cancel') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border:none;border-radius:var(--radius-2xl);padding:28px">
            <div class="modal-title">
                <i class="bi bi-star-fill" style="color:var(--gold)"></i>
                {{ __('giya.profile.review') }} <span id="reviewChurchName"></span>
            </div>
            <form method="POST" action="{{ route('profile.review') }}">
                @csrf
                <input type="hidden" name="visit_id" id="reviewVisitId">

                <div class="field">
                    <label class="form-label-sm">{{ __('giya.profile.your_rating') }}</label>
                    <div class="star-picker" id="starPicker">
                        @for ($i = 5; $i >= 1; $i--)
                            <input type="radio" name="rating" id="star-{{ $i }}" value="{{ $i }}"
                                   class="visually-hidden" required>
                            <label for="star-{{ $i }}" title="{{ $i }} star{{ $i > 1 ? 's' : '' }}">
                                <i class="bi bi-star-fill"></i>
                            </label>
                        @endfor
                    </div>
                    @error('rating')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="field">
                    <label class="form-label-sm" for="review-comment">Comment (optional)</label>
                    <textarea id="review-comment" name="comment" class="giya-input" rows="3"
                              placeholder="{{ __('giya.profile.comment_ph') }}"></textarea>
                </div>

                <p style="font-size: 0.75rem;color:var(--text-muted);margin:0 0 12px">
                    {{ __('giya.profile.review_note') }}
                </p>

                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary" style="flex:1">{{ __('giya.profile.submit_review') }}</button>
                    <button type="button" class="btn btn-outline" style="flex:1" data-modal-close>{{ __('giya.common.cancel') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('head')
<style>
    .profile-tab { padding:16px 18px; font-size: 0.875rem; font-weight:500; color:var(--text-muted);
                   background:none; border:none; border-bottom:2px solid transparent;
                   cursor:pointer; white-space:nowrap; transition:all .18s; font-family:var(--font-body); }
    .profile-tab.is-active { color:var(--primary); font-weight:700; border-bottom-color:var(--primary); }
    @media (max-width: 700px) { .profile-grid { grid-template-columns: 1fr !important; } }
</style>
@endpush

@push('scripts')
<script>
const GiyaProfile = {
    show(id, btn) {
        document.querySelectorAll('.profile-panel').forEach(p => p.classList.add('d-none'));
        document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('is-active'));
        document.getElementById('panel-' + id).classList.remove('d-none');
        btn.classList.add('is-active');
    },

    review(visitId, churchName) {
        document.getElementById('reviewVisitId').value = visitId;
        document.getElementById('reviewChurchName').textContent = churchName;
        document.querySelectorAll('#starPicker input').forEach(i => { i.checked = false; });
        GiyaUI.Modal.open('reviewModal');
    },

    unfavorite(churchId) {
        fetch('{{ route('favorites.toggle') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ church_id: churchId }),
        })
            .then(r => r.json())
            .then(data => {
                if (!data.ok || data.saved) return;
                const row = document.querySelector(`[data-favorite-row="${churchId}"]`);
                if (row) row.remove();
            })
            .catch(() => window.location.reload());
    },

    previewAvatar(input) {
        const file = input.files && input.files[0];
        const wrap = document.getElementById('avatarPreviewWrap');
        if (!file || !wrap) return;

        const reader = new FileReader();
        reader.onload = e => {
            wrap.innerHTML = '<img id="avatarPreview" alt="" src="' + e.target.result + '">';
        };
        reader.readAsDataURL(file);

        // Confirm in words what the picture now shows, so it is clear the file
        // was taken and not merely opened.
        const name = document.getElementById('avatarName');
        if (name) name.textContent = file.name;
    },
};

/* ── Preferences save themselves ─────────────────────────────────
   Each control writes its own field the moment it changes, and theme
   and font size apply to the page immediately - no reload, no Save.
   ---------------------------------------------------------------- */
/* Dropping a photo onto the avatar does the same as choosing one. The label
   already opens the picker on click and on Enter, so this only adds the drop:
   the file is handed to the hidden input through a DataTransfer, which means
   it submits with the form exactly as a chosen file does. */
(function () {
    const drop  = document.getElementById('avatarDrop');
    const input = document.getElementById('pf-avatar');
    if (!drop || !input) return;

    const stop = e => { e.preventDefault(); e.stopPropagation(); };

    ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => {
        stop(e);
        drop.classList.add('is-dragging');
    }));

    ['dragleave', 'dragend', 'drop'].forEach(ev => drop.addEventListener(ev, e => {
        stop(e);
        drop.classList.remove('is-dragging');
    }));

    drop.addEventListener('drop', e => {
        const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        if (!file || !file.type.startsWith('image/')) return;

        const carrier = new DataTransfer();
        carrier.items.add(file);
        input.files = carrier.files;

        GiyaProfile.previewAvatar(input);
    });
})();

const PrefSaver = (function () {
    const form = document.getElementById('prefForm');
    if (!form) return { save() {} };

    const status = document.getElementById('prefStatus');
    let timer = null;

    function say(text, kind) {
        if (!status) return;
        status.textContent = text;
        status.className = 'pref-status' + (kind ? ' is-' + kind : '');
        clearTimeout(timer);
        if (kind === 'ok') timer = setTimeout(() => { status.textContent = ''; }, 2000);
    }

    function apply(field, value) {
        const root = document.documentElement;
        if (field === 'theme_style') root.setAttribute('data-theme', String(value).toLowerCase());
        if (field === 'font_size')   root.setAttribute('data-font',  String(value).toLowerCase());
    }

    /*
        `reload` is for settings this page cannot repaint itself.

        Theme and font are attributes on <html>, so apply() flips them and the
        page is done. Language is not: every string was rendered server-side
        on the way in, so the page has to come back to change. Without this
        the choice saved, said "Saved", and nothing moved until you happened
        to navigate somewhere.
    */
    function save(field, value, reload) {
        apply(field, value);          // instant, before the round trip
        say(@json(__('giya.common.saving')));

        fetch(form.dataset.saveUrl, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ field, value }),
        })
            .then(r => r.json())
            .then(d => {
                say(d.ok ? @json(__('giya.profile.saved_ok')) : @json(__('giya.profile.save_fail')), d.ok ? 'ok' : 'error');

                // Come back on the same tab rather than dumping the devotee
                // on Overview - ?tab= is already how this page picks one.
                if (d.ok && reload) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', 'preferences');
                    window.location.replace(url.toString());
                }
            })
            .catch(() => say(@json(__('giya.profile.save_fail_net')), 'error'));
    }

    return { save };
})();

document.addEventListener('change', event => {
    const el = event.target;

    // Font size and theme: radios inside the pill controls.
    if (el.matches('.pref-choice input[type=radio]')) {
        document.querySelectorAll(`.pref-choice input[name="${el.name}"]`)
            .forEach(i => i.closest('.pref-choice').classList.toggle('is-active', i.checked));
        PrefSaver.save(el.name, el.value);
        return;
    }

    // Language.
    if (el.id === 'pref-language') {
        PrefSaver.save('language', el.value, true);   // reload; see save()
        return;
    }

    // Notification toggles.
    if (el.matches('.pref-switch input[type=checkbox]')) {
        PrefSaver.save(el.name, el.checked ? 1 : 0);
        return;
    }

    if (el.id === 'pf-avatar') GiyaProfile.previewAvatar(el);
});

@if ($errors->hasAny(['name', 'email', 'avatar']))
    GiyaUI.Modal.open('editProfileModal');
@endif
@if ($errors->hasAny(['current_password', 'password']))
    GiyaUI.Modal.open('changePasswordModal');
@endif
@if ($errors->hasAny(['rating', 'comment', 'visit_id']))
    GiyaUI.Modal.open('reviewModal');
@endif
</script>
@endpush
