@extends('layouts.admin')
@section('title', 'System Maintenance')
@section('page-title', 'System Maintenance')
@section('page-subtitle', 'Close GIYA to pilgrims while the team works on it')

@section('content')

@unless ($ready)
    <div class="alert alert-warning mb-3">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span>The database is missing the settings table. Run <strong>php artisan migrate</strong> once, then come back.</span>
    </div>
@endunless

<div class="sys-grid">
    <div class="card card-body">
        <div class="sys-head">
            <div>
                <div class="card-title">Maintenance mode</div>
                <p class="dm-hint">While it is on, pilgrims see a "GIYA is being updated" page. Admins can still sign in and use everything.</p>
            </div>
            <span @class(['badge', 'badge-suspended' => $on, 'badge-published' => ! $on]) id="maintState">
                {{ $on ? 'On: site is closed' : 'Off: site is open' }}
            </span>
        </div>

        @if ($on && $since)
            <p class="sys-since">
                <i class="bi bi-clock-history"></i>
                Turned on {{ $since->diffForHumans() }}@if ($by) by {{ $by }}@endif.
            </p>
        @endif

        <form method="POST" action="{{ route('admin.system.maintenance') }}" id="maintForm"
              @unless ($on)
                  data-confirm-title="Close GIYA to pilgrims?"
                  data-confirm="Everyone except admins will see the maintenance page until you turn this off."
                  data-confirm-ok="Turn on"
              @endunless>
            @csrf @method('PATCH')
            {{-- The confirm box submits the form itself, which drops the button's value. --}}
            @unless ($on)<input type="hidden" name="state" value="on">@endunless

            <div class="field">
                <label class="dm-label" for="m-message">Message shown to pilgrims</label>
                <textarea id="m-message" name="message" class="giya-input" rows="3" maxlength="300"
                          placeholder="{{ \App\Support\Maintenance::DEFAULT_MESSAGE }}">{{ old('message', $text) }}</textarea>
                @error('message')<span class="field-error">{{ $message }}</span>@enderror
                <p class="dm-hint">Leave empty to use the message shown in grey.</p>
            </div>

            <div class="field">
                <label class="dm-label" for="m-back">Back by (optional)</label>
                <input id="m-back" type="datetime-local" name="back_by" class="giya-input sys-date"
                       value="{{ old('back_by', $backBy) }}">
                @error('back_by')<span class="field-error">{{ $message }}</span>@enderror
                <p class="dm-hint">Shown as "We expect to be back by…". It does not reopen the site for you.</p>
            </div>

            <div class="sys-actions">
                @if ($on)
                    <button type="submit" name="state" value="off" class="btn btn-primary">
                        <i class="bi bi-check-circle-fill"></i> Turn off and reopen GIYA
                    </button>
                    <button type="submit" name="state" value="on" class="btn btn-outline">Save message</button>
                @else
                    <button type="submit" class="btn btn-danger" @disabled(! $ready)>
                        <i class="bi bi-cone-striped"></i> Turn on maintenance mode
                    </button>
                @endif
                <a href="{{ route('admin.system.preview') }}" target="_blank" rel="noopener" class="btn btn-ghost">
                    <i class="bi bi-eye"></i> Preview the page
                </a>
            </div>
        </form>
    </div>

    <div class="card card-body">
        <div class="card-title">What stays open</div>
        <ul class="sys-list">
            <li><i class="bi bi-shield-lock-fill"></i><span><strong>Admin sign-in.</strong> Sign in as usual at the sign-in page.</span></li>
            <li><i class="bi bi-credit-card-fill"></i><span><strong>Maya payment confirmations.</strong> A payment already on its way is still recorded.</span></li>
            <li><i class="bi bi-lock-fill"></i><span><strong>Everything else is closed</strong> to pilgrims: church pages, the map, itineraries, Giya AI and sign-up.</span></li>
        </ul>
        <div class="card-title sys-gap">Before you turn it on</div>
        <ul class="sys-list">
            <li><i class="bi bi-info-circle"></i><span>Pick a quiet hour. Sunday mornings and feast days are the busiest.</span></li>
            <li><i class="bi bi-info-circle"></i><span>Remember to turn it off. A reminder shows at the top of every admin page while it is on.</span></li>
        </ul>
    </div>
</div>
@endsection
