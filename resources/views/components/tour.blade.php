{{--
    The guided tour for one page: a dimmed screen with a spotlight on one
    button at a time and a card that explains it (giya-tour.js draws it).

    Each step is [selector, key, tap, media]. The selector may list several
    (a phone and a computer show different buttons); the first one on screen
    is used, and a step with nothing on screen is skipped. Text comes from
    giya.tour.<page>.<key>_t and _b. With tap true, tapping the lit button
    does what it does and the tour moves on; false keeps it to Next, for
    buttons that open a panel or change something. media (optional) is a
    CSS media query: the step only shows on screens that match, such as the
    slide bar that only a phone has.

    Only rendered while this account has not finished this page's tour.
--}}
@props(['name', 'steps', 'waitFor' => null])

@if (\App\Http\Controllers\TourController::pending(auth()->user(), $name))
    @php
        $tourSteps = [];
        foreach ($steps as $step) {
            [$target, $key, $tap, $media] = $step + [null, null, false, null];
            $tourSteps[] = [
                'target' => $target,
                'title'  => __("giya.tour.{$name}.{$key}_t", ['name' => auth()->user()->firstName()]),
                'body'   => __("giya.tour.{$name}.{$key}_b"),
                'tap'    => (bool) $tap,
                'media'  => $media,
            ];
        }
        $tourConfig = [
            'name'    => $name,
            'url'     => route('tour.done'),
            'token'   => csrf_token(),
            'waitFor' => $waitFor,
            'steps'   => $tourSteps,
            'words'   => [
                'skip' => __('giya.tour.skip'),
                'back' => __('giya.tour.back'),
                'next' => __('giya.tour.next'),
                'done' => __('giya.tour.done'),
                'step' => __('giya.tour.step', ['n' => ':n', 'total' => ':total']),
                'tap'  => __('giya.tour.tap_hint'),
            ],
        ];
    @endphp
    <script type="application/json" id="giyaTour">@json($tourConfig)</script>
@endif
