@extends('layouts.app')
@section('title', $itinerary->name)
@section('meta_description', __('giya.share.meta', ['owner' => $owner, 'n' => $stops->count()]))

@section('content')
<div class="shared-page">
    <div class="shared-head">
        <span class="shared-badge"><i class="bi bi-people-fill"></i> {{ __('giya.share.badge', ['owner' => $owner]) }}</span>
        <h1 class="shared-title">{{ $itinerary->name }}</h1>
        <p class="shared-meta">
            {{ trans_choice('giya.share.stops', $stops->count(), ['n' => $stops->count()]) }}
            @if ($itinerary->schedule_date) · {{ $itinerary->schedule_date->format('M j, Y') }} @endif
        </p>
        @if ($itinerary->notes)
            <p class="shared-notes">{{ $itinerary->notes }}</p>
        @endif
    </div>

    <ol class="shared-stops">
        @foreach ($stops as $stop)
            <li>
                <span class="shared-num">{{ $loop->iteration }}</span>
                <span class="shared-stop">
                    <strong>{{ $stop->church->name }}</strong>
                    <span>{{ \App\Services\RoutePlanner::townName((string) ($stop->church->municipality ?: $stop->church->location)) }}</span>
                </span>
            </li>
        @endforeach
    </ol>

    <div class="shared-actions">
        @guest
            <p class="shared-help">{{ __('giya.share.guest_help') }}</p>
            <a href="{{ route('login') }}" class="btn btn-primary btn-w-full">{{ __('giya.share.sign_in') }}</a>
            <a href="{{ route('register') }}" class="btn btn-outline btn-w-full">{{ __('giya.footer.create_account') }}</a>
        @else
            @if ($mine)
                <p class="shared-help">{{ __('giya.share.yours') }}</p>
                <a href="{{ route('plan.show', $itinerary) }}" class="btn btn-primary btn-w-full">{{ __('giya.share.open_mine') }}</a>
            @elseif ($atLimit)
                <p class="shared-help">{{ __('giya.share.limit') }}</p>
                <a href="{{ route('upgrade') }}" class="btn btn-primary btn-w-full">{{ __('giya.plan.unlock_more') }}</a>
            @else
                <p class="shared-help">{{ __('giya.share.copy_help') }}</p>
                <form method="POST" action="{{ route('shared.copy', $itinerary->share_token) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-w-full">{{ __('giya.share.copy') }}</button>
                </form>
            @endif
        @endguest
    </div>
</div>
@endsection
