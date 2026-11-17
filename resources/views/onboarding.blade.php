{{--
    Welcome slides: shown once, the first time someone opens GIYA on a phone
    or browser (see OnboardingController). The slides sit side by side in a
    scroll-snap track, so a finger swipe moves between them natively; Next,
    the dots and the arrow keys do the same through a few lines of script.
    Without script it is still a swipeable row with working links.
--}}
@php
    $slides = [
        ['img' => 'discover', 'title' => __('giya.onboarding.s1_title'), 'text' => __('giya.onboarding.s1_text')],
        ['img' => 'routes',   'title' => __('giya.onboarding.s2_title'), 'text' => __('giya.onboarding.s2_text')],
        ['img' => 'ai-guide', 'title' => __('giya.onboarding.s3_title'), 'text' => __('giya.onboarding.s3_text')],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#EFE8DF">
    <title>GIYA - {{ __('giya.onboarding.title') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo/giya-logo.svg') }}?v=2">
    <link rel="stylesheet" href="{{ asset('assets/css/giya.css') }}?v={{ filemtime(public_path('assets/css/giya.css')) }}">
</head>
<body class="onb-page">

<main class="onb" id="onb" aria-roledescription="carousel" aria-label="{{ __('giya.onboarding.title') }}">
    {{-- The official logo and name, opposite Skip, on every slide. --}}
    <div class="onb-brand">
        <img src="{{ asset('images/logo/giya-logo.svg') }}?v=2" alt="" width="40" height="40">
        <span>Giya</span>
    </div>
    <a class="onb-skip" href="{{ route('onboarding.finish', ['next' => 'root']) }}">{{ __('giya.onboarding.skip') }}</a>

    <div class="onb-track" id="onbTrack" tabindex="0">
        @foreach ($slides as $i => $s)
            <section class="onb-slide" aria-roledescription="slide"
                     aria-label="{{ __('giya.onboarding.slide', ['n' => $i + 1, 'total' => count($slides)]) }}">
                {{-- Drawn, not a small picture, so they stay sharp on any phone. --}}
                <span class="onb-icon is-{{ $s['img'] }}" aria-hidden="true">
                    @if ($s['img'] === 'discover')
                        <svg viewBox="0 0 96 96">
                            <circle cx="48" cy="48" r="48" fill="#F8E3C6"/>
                            <rect x="46" y="14" width="4" height="15" rx="1" fill="#E3B043"/>
                            <rect x="41" y="18" width="14" height="4" rx="1" fill="#E3B043"/>
                            <path d="M48 28 31 43v31h34V43L48 28Z" fill="#8E3B2F"/>
                            <path d="M22 52 31 45v29h-9V52Zm52 0-9-7v29h9V52Z" fill="#6B2D22"/>
                            <path d="M42 74V61a6 6 0 0 1 12 0v13H42Z" fill="#F8E3C6"/>
                            <circle cx="48" cy="47" r="4" fill="#F8E3C6"/>
                            <rect x="18" y="74" width="60" height="4" rx="2" fill="#E3B043"/>
                        </svg>
                    @elseif ($s['img'] === 'routes')
                        <svg viewBox="0 0 96 96">
                            <circle cx="48" cy="48" r="48" fill="#F8E3C6"/>
                            <path d="M33 74c10 2 22 0 26-8s2-10 7-14" fill="none" stroke="#E3B043" stroke-width="4" stroke-linecap="round" stroke-dasharray="1 8"/>
                            <path d="M30 76c-6-7-12-12-12-19a12 12 0 0 1 24 0c0 7-6 12-12 19Z" fill="#8E3B2F"/>
                            <circle cx="30" cy="57" r="4.5" fill="#F8E3C6"/>
                            <path d="M66 50c-6-7-12-12-12-19a12 12 0 0 1 24 0c0 7-6 12-12 19Z" fill="#8E3B2F"/>
                            <rect x="64.6" y="24" width="2.8" height="12" rx=".6" fill="#E3B043"/>
                            <rect x="61" y="27.6" width="10" height="2.8" rx=".6" fill="#E3B043"/>
                        </svg>
                    @else
                        <span class="onb-robot">
                            <span class="guide-face">
                                <span class="guide-ear guide-ear-l"></span>
                                <span class="guide-ear guide-ear-r"></span>
                                <span class="guide-screen">
                                    <span class="guide-eye guide-eye-l"><i></i></span>
                                    <span class="guide-eye guide-eye-r"><i></i></span>
                                    <span class="guide-mouth"></span>
                                    <span class="guide-blush guide-blush-l"></span>
                                    <span class="guide-blush guide-blush-r"></span>
                                </span>
                                <span class="guide-lamp"></span>
                            </span>
                        </span>
                    @endif
                </span>
                {{-- One h1 per page: the first slide titles the page, the others are sections. --}}
                <{{ $loop->first ? 'h1' : 'h2' }} class="onb-title">{{ $s['title'] }}</{{ $loop->first ? 'h1' : 'h2' }}>
                <p class="onb-text">{{ $s['text'] }}</p>
            </section>
        @endforeach
    </div>

    <div class="onb-foot">
        <div class="onb-dots" id="onbDots">
            @foreach ($slides as $i => $s)
                <button type="button" class="onb-dot {{ $i === 0 ? 'is-on' : '' }}" data-i="{{ $i }}"
                        aria-label="{{ __('giya.onboarding.slide', ['n' => $i + 1, 'total' => count($slides)]) }}"
                        @if ($i === 0) aria-current="true" @endif></button>
            @endforeach
        </div>

        <a class="onb-btn" id="onbNext" href="{{ route('onboarding.finish', ['next' => 'register']) }}"
           data-next="{{ __('giya.onboarding.next') }}" data-start="{{ __('giya.onboarding.start') }}">{{ __('giya.onboarding.start') }}</a>

        <a class="onb-login" id="onbLogin" href="{{ route('onboarding.finish', ['next' => 'login']) }}">{{ __('giya.onboarding.have_acct') }}</a>
    </div>
</main>

<script src="{{ asset('assets/js/giya-onboarding.js') }}?v={{ filemtime(public_path('assets/js/giya-onboarding.js')) }}"></script>
</body>
</html>
