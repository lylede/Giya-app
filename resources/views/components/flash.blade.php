@if (session('success') || session('error') || session('warning'))
    <div class="flash-container" aria-live="polite" id="giyaFlash">
        @if (session('success'))
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>{{ session('warning') }}</span>
            </div>
        @endif
    </div>

    {{--
        Self-contained on purpose.

        This used to rely on a handler in giya.js registered at DOMContentLoaded.
        That is one more thing that has to load, in the right order, and survive
        any Livewire re-render - and when it did not, the message simply stayed
        on screen forever.

        Inline, beside the markup it controls, it runs the moment the element
        exists. Nothing to load, no ordering to get right.
    --}}
    <script>
    (function () {
        var box = document.getElementById('giyaFlash');
        if (!box) return;

        var gone = false;

        function hide() {
            if (gone) return;
            gone = true;

            box.classList.add('is-going');
            window.setTimeout(function () {
                if (box.parentNode) box.parentNode.removeChild(box);
            }, 300);

            ['pointerdown', 'touchstart', 'keydown', 'scroll', 'wheel'].forEach(function (ev) {
                window.removeEventListener(ev, hide, true);
            });
        }

        // Five seconds if the devotee does nothing.
        window.setTimeout(hide, 5000);

        // Or the moment they do anything at all. Capture phase, so it fires
        // even when another handler stops the event from bubbling.
        ['pointerdown', 'touchstart', 'keydown', 'scroll', 'wheel'].forEach(function (ev) {
            window.addEventListener(ev, hide, { capture: true, passive: true });
        });
    })();
    </script>
@endif
