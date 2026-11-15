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
