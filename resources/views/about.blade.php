@extends('layouts.app')
@section('title', __('giya.about.title'))
@section('meta_description', __('giya.about.lead'))

@php
    // Photos and roles from the team's curriculum vitae in the capstone manuscript.
    $team = [
        ['Josh Efraim C. Padernal',  __('giya.about.role_pm'),      'josh',  __('giya.about.bio_pm')],
        ['Clint Eroll A. Capondag',  __('giya.about.role_design'),  'clint', __('giya.about.bio_design')],
        ['Lyle Adrien P. Dedumo',    __('giya.about.role_backend'), 'lyle',  __('giya.about.bio_backend')],
        ['Ian Francis M. Loyola',    __('giya.about.role_qa'),      'ian',   __('giya.about.bio_qa')],
    ];
    // Each feature: icon, short title, one line on what it does.
    $does = [
        ['map-fill',          __('giya.about.f_map_t'),     __('giya.about.f_map')],
        ['giya-spires',       __('giya.about.f_church_t'),  __('giya.about.f_church')],
        ['giya-candle',       __('giya.about.f_mass_t'),    __('giya.about.f_mass')],
        ['giya-assistant',    __('giya.about.f_ai_t'),      __('giya.about.f_ai')],
        ['giya-route',        __('giya.about.f_plan_t'),    __('giya.about.f_plan')],
        ['check-circle-fill', __('giya.about.f_visit_t'),   __('giya.about.f_visit')],
        ['bell-fill',         __('giya.about.f_notify_t'),  __('giya.about.f_notify')],
        ['globe2',             __('giya.about.f_lang_t'),    __('giya.about.f_lang')],
    ];
    $steps = [
        [__('giya.about.step_find_t'), __('giya.about.step_find')],
        [__('giya.about.step_plan_t'), __('giya.about.step_plan')],
        [__('giya.about.step_go_t'),   __('giya.about.step_go')],
        [__('giya.about.step_keep_t'), __('giya.about.step_keep')],
    ];
    $for = [
        [__('giya.about.for_pilgrims_t'), __('giya.about.for_pilgrims')],
        [__('giya.about.for_tourists_t'), __('giya.about.for_tourists')],
        [__('giya.about.for_parish_t'),   __('giya.about.for_parish')],
        [__('giya.about.for_tourism_t'),  __('giya.about.for_tourism')],
    ];
    // Live figures, counted the same way as on Home.
    $churchCount = \App\Models\Church::active()->count();
    $townCount = \App\Models\Church::active()->get(['municipality', 'location'])
        ->map(fn ($c) => mb_strtolower(\App\Services\RoutePlanner::townName((string) ($c->municipality ?: $c->location))))
        ->filter()->unique()->count();
@endphp

@section('content')
<div class="about-page">
    <header class="about-head">
        <img class="about-logo" src="{{ asset('images/logo/giya-logo.svg') }}?v=2" alt="GIYA" width="104" height="104">
        <div>
            <h1 class="about-title">{{ __('giya.about.title') }}</h1>
            <p class="about-lead">{{ __('giya.about.lead') }}</p>
        </div>
    </header>

    <ul class="about-figures" aria-label="{{ __('giya.about.figures_label') }}">
        <li><strong>{{ $churchCount }}</strong><span>{{ __('giya.about.fig_churches') }}</span></li>
        <li><strong>{{ $townCount }}</strong><span>{{ __('giya.home.stat_cities') }}</span></li>
        <li><strong>6</strong><span>{{ __('giya.about.fig_regions') }}</span></li>
        <li><strong>3</strong><span>{{ __('giya.about.fig_languages') }}</span></li>
    </ul>

    <section class="about-section" aria-labelledby="aboutCebu">
        <h2 id="aboutCebu">{{ __('giya.about.cebu_title') }}</h2>
        <p>{{ __('giya.about.cebu_1') }}</p>
        <p>{{ __('giya.about.cebu_2') }}</p>
    </section>

    <section class="about-section" aria-labelledby="aboutWhy">
        <h2 id="aboutWhy">{{ __('giya.about.why_title') }}</h2>
        <p>{{ __('giya.about.why_1') }}</p>
        <p>{{ __('giya.about.why_3') }}</p>
        <p>{{ __('giya.about.why_2') }}</p>
    </section>

    <section class="about-section" aria-labelledby="aboutDoes">
        <h2 id="aboutDoes">{{ __('giya.about.does_title') }}</h2>
        <ul class="about-features">
            @foreach ($does as [$icon, $title, $text])
                <li>
                    <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
                    <div><strong>{{ $title }}</strong><span>{{ $text }}</span></div>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="about-section" aria-labelledby="aboutHow">
        <h2 id="aboutHow">{{ __('giya.about.how_title') }}</h2>
        <ol class="about-steps">
            @foreach ($steps as [$title, $text])
                <li><strong>{{ $title }}</strong><span>{{ $text }}</span></li>
            @endforeach
        </ol>
    </section>

    <section class="about-section" aria-labelledby="aboutFor">
        <h2 id="aboutFor">{{ __('giya.about.for_title') }}</h2>
        <ul class="about-for">
            @foreach ($for as [$title, $text])
                <li><strong>{{ $title }}</strong><span>{{ $text }}</span></li>
            @endforeach
        </ul>
    </section>

    <section class="about-section" aria-labelledby="aboutTrust">
        <h2 id="aboutTrust">{{ __('giya.about.trust_title') }}</h2>
        <p>{{ __('giya.about.trust_1') }}</p>
        <p>{{ __('giya.about.trust_2') }}</p>
        <p>{{ __('giya.about.privacy_text') }} <a href="{{ route('privacy') }}">{{ __('giya.legal.privacy') }}</a>.</p>
    </section>

    <section class="about-section" aria-labelledby="aboutTeam">
        <p class="about-eyebrow">{{ __('giya.about.team_eyebrow') }}</p>
        <h2 id="aboutTeam">{{ __('giya.about.team_title') }}</h2>
        <p>{{ __('giya.about.team_lead') }}</p>
        <ul class="about-team">
            @foreach ($team as [$name, $role, $photo, $bio])
                <li class="about-member">
                    <img src="{{ asset('images/team/'.$photo.'.jpg') }}" alt="{{ $name }}" width="300" height="400" loading="lazy">
                    <span class="about-member-name">{{ $name }}</span>
                    <span class="about-member-role">{{ $role }}</span>
                    <p class="about-member-bio">{{ $bio }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="about-section about-school" aria-labelledby="aboutSchool">
        <h2 id="aboutSchool">{{ __('giya.about.school_title') }}</h2>
        <p>{{ __('giya.about.school_text') }}</p>
    </section>

    <section class="about-section" aria-labelledby="aboutFeedback">
        <h2 id="aboutFeedback">{{ __('giya.app_feedback.title') }}</h2>
        <p>{{ __('giya.app_feedback.lead') }}</p>
        <a href="{{ route('app-feedback') }}" class="btn btn-primary">{{ __('giya.app_feedback.send') }}</a>
    </section>
</div>
@endsection
