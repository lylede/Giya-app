{{-- The Feedback module has two parts: reviews of churches, and feedback
     about GIYA itself. One sidebar item, these tabs to move between them. --}}
@php
    $reviewsWaiting = \App\Models\Feedback::where('status', 'Pending')->count();
    $appCount = \Illuminate\Support\Facades\Schema::hasTable('app_feedback') ? \App\Models\AppFeedback::count() : 0;
@endphp
<nav class="adm-tabs" aria-label="Feedback">
    <a href="{{ route('admin.feedback') }}" @class(['adm-tab', 'active' => request()->routeIs('admin.feedback')])
       @if (request()->routeIs('admin.feedback')) aria-current="page" @endif>
        <i class="bi bi-giya-star"></i> Church reviews
        @if ($reviewsWaiting) <span class="adm-tab-count" title="Waiting for review">{{ $reviewsWaiting }}</span> @endif
    </a>
    <a href="{{ route('admin.app-feedback') }}" @class(['adm-tab', 'active' => request()->routeIs('admin.app-feedback*')])
       @if (request()->routeIs('admin.app-feedback*')) aria-current="page" @endif>
        <i class="bi bi-chat-dots"></i> App feedback
        @if ($appCount) <span class="adm-tab-count">{{ $appCount }}</span> @endif
    </a>
</nav>
