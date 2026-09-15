@props(['kind' => 'cards', 'count' => 4])

{{--
    A stand-in shaped like whatever it is standing in for.

    Shown only while the page's images are still arriving, and only when they
    really are: the script checks first, so a cached page never flashes one.
    aria-hidden throughout - a screen reader is not waiting for a photograph
    and should be reading the real thing, which is in the DOM the whole time.
--}}
<div class="section-skeleton" aria-hidden="true">
    @if ($kind === 'cards')
        <div class="home-grid home-grid-sm">
            @for ($i = 0; $i < $count; $i++)
                <div class="card gs-card">
                    <span class="gs gs-thumb"></span>
                    <span class="gs gs-line is-title" style="width:{{ [64, 52, 70, 58][$i % 4] }}%"></span>
                    <span class="gs gs-line" style="width:92%"></span>
                    <span class="gs gs-line" style="width:{{ [70, 84, 62, 78][$i % 4] }}%"></span>
                    <span class="gs gs-line gs-card-go"></span>
                </div>
            @endfor
        </div>
    @else
        @for ($i = 0; $i < $count; $i++)
            <div class="gs-row">
                <span class="gs gs-thumb gs-row-date"></span>
                <span class="gs-row-body">
                    <span class="gs gs-line is-title" style="width:{{ [58, 72, 48][$i % 3] }}%"></span>
                    <span class="gs gs-line" style="width:{{ [34, 42, 30][$i % 3] }}%"></span>
                </span>
                <span class="gs gs-line gs-row-when"></span>
            </div>
        @endfor
    @endif
</div>
