@extends('layouts.app')
@section('title', __('giya.about.title'))
@section('meta_description', __('giya.about.lead'))

@php
    // Photos and roles from the team's curriculum vitae in the capstone manuscript.
    $team = [
        ['Josh Efraim C. Padernal',  __('giya.about.role_pm'),      'josh'],
        ['Clint Eroll A. Capondag',  __('giya.about.role_design'),  'clint'],
        ['Lyle Adrien P. Dedumo',    __('giya.about.role_backend'), 'lyle'],
        ['Ian Francis M. Loyola',    __('giya.about.role_qa'),      'ian'],
    ];
    $does = [
        ['map-fill',        __('giya.about.does_map')],
        ['calendar-event',  __('giya.about.does_mass')],
        ['chat-dots-fill',  __('giya.about.does_ai')],
        ['giya-route',      __('giya.about.does_plan')],
        ['check-circle-fill', __('giya.about.does_visit')],
    ];
@endphp

@section('content')
<div class="about-page">
    <header class="about-head">
        <h1 class="about-title">{{ __('giya.about.title') }}</h1>
        <p class="about-lead">{{ __('giya.about.lead') }}</p>
    </header>

    <section class="about-section" aria-labelledby="aboutWhy">
        <h2 id="aboutWhy">{{ __('giya.about.why_title') }}</h2>
        <p>{{ __('giya.about.why_1') }}</p>
        <p>{{ __('giya.about.why_2') }}</p>
    </section>

    <section class="about-section" aria-labelledby="aboutDoes">
        <h2 id="aboutDoes">{{ __('giya.about.does_title') }}</h2>
        <ul class="about-does">
            @foreach ($does as [$icon, $text])
                <li><i class="bi bi-{{ $icon }}" aria-hidden="true"></i><span>{{ $text }}</span></li>
            @endforeach
        </ul>
    </section>

    <section class="about-section" aria-labelledby="aboutTeam">
        <h2 id="aboutTeam">{{ __('giya.about.team_title') }}</h2>
        <p>{{ __('giya.about.team_lead') }}</p>
        <ul class="about-team">
            @foreach ($team as [$name, $role, $photo])
                <li class="about-member">
                    <img src="{{ asset('images/team/'.$photo.'.jpg') }}" alt="{{ $name }}" width="160" height="160" loading="lazy">
                    <span class="about-member-name">{{ $name }}</span>
                    <span class="about-member-role">{{ $role }}</span>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="about-section about-school" aria-labelledby="aboutSchool">
        <h2 id="aboutSchool">{{ __('giya.about.school_title') }}</h2>
        <p>{{ __('giya.about.school_text') }}</p>
    </section>
</div>
@endsection
