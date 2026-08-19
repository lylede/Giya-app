@if (session('success') || session('error') || session('warning'))
    <div class="flash-container" aria-live="polite">
        @if (session('success'))
            <div class="alert alert-success" data-auto-dismiss="3000">
                <i class="bi bi-check-circle-fill"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger" data-auto-dismiss="3000">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning" data-auto-dismiss="3000">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>{{ session('warning') }}</span>
            </div>
        @endif
    </div>
@endif
