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
    <meta name="theme-color" content="#FFF8ED">
    <title>GIYA - {{ __('giya.onboarding.title') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo/giya-logo.svg') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/giya.css') }}?v={{ filemtime(public_path('assets/css/giya.css')) }}">
</head>
<body class="onb-page">

<main class="onb" id="onb" aria-roledescription="carousel" aria-label="{{ __('giya.onboarding.title') }}">
    <a class="onb-skip" href="{{ route('onboarding.finish', ['next' => 'root']) }}">{{ __('giya.onboarding.skip') }}</a>

    <div class="onb-track" id="onbTrack" tabindex="0">
        @foreach ($slides as $i => $s)
            <section class="onb-slide" aria-roledescription="slide"
                     aria-label="{{ __('giya.onboarding.slide', ['n' => $i + 1, 'total' => count($slides)]) }}">
                <span class="onb-icon">
                    <img src="{{ asset('assets/img/onboarding/'.$s['img'].'.png') }}" alt="" width="77" height="78">
                </span>
                <h1 class="onb-title">{{ $s['title'] }}</h1>
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
