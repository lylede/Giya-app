@extends('layouts.admin')
@section('title', 'Announcements')
@section('page-title', 'Announcements')
@section('page-subtitle', 'A message from the team to every pilgrim')

@section('content')

@unless ($ready)
    <div class="alert alert-warning mb-3">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span>The database is missing the announcements table. Run <strong>php artisan migrate</strong> once, then come back.</span>
    </div>
@endunless

<div class="sys-grid">
    <div class="card card-body">
        <div class="card-title">New announcement</div>
        <p class="dm-hint">It shows as a banner across the top of GIYA. Only the newest announcement is on the banner at a time.</p>

        <form method="POST" action="{{ route('admin.announcements.store') }}">
            @csrf

            <div class="field">
                <label class="dm-label" for="a-title">Title</label>
                <input id="a-title" type="text" name="title" class="giya-input" maxlength="120" required
                       value="{{ old('title') }}" placeholder="Sinulog Mass schedules are now posted">
                @error('title')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label class="dm-label" for="a-message">Announcement</label>
                <textarea id="a-message" name="message" class="giya-input" rows="3" maxlength="300" required
                          placeholder="One or two sentences. What changed, and what the pilgrim should do.">{{ old('message') }}</textarea>
                @error('message')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label class="dm-label" for="a-show">Keep it on the banner for</label>
                <select id="a-show" name="show_for" class="giya-input sys-date">
                    @foreach ($showFor as $days => $label)
                        <option value="{{ $days }}" @selected((int) old('show_for', 7) === $days)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <label class="ann-check">
                <input type="hidden" name="notify" value="0">
                <input type="checkbox" name="notify" value="1" @checked(old('notify', '1') === '1')>
                <span>Also send it to every pilgrim's notification bell</span>
            </label>

            <div class="sys-actions">
                <button type="submit" class="btn btn-primary" @disabled(! $ready)>
                    <i class="bi bi-bell-fill"></i> Post announcement
                </button>
            </div>
        </form>
    </div>

    <div class="card card-body">
        <div class="card-title">On the banner now</div>
        @if ($current)
            <div class="ann-preview">
                <strong>{{ $current->title }}</strong>
                <span>{{ $current->message }}</span>
            </div>
            <p class="dm-hint">
                {{ $current->show_until ? 'Until '.$current->show_until->format('M j, g:i A').'.' : 'Until you stop it.' }}
                A pilgrim who closes the banner does not see it again.
            </p>
        @else
            <p class="dm-hint">Nothing. Pilgrims see no banner.</p>
        @endif
    </div>
</div>

<div class="card-title ann-list-title">Past announcements</div>

@forelse ($announcements as $item)
    <div class="card card-body mb-2">
        <div class="adm-item">
            <div class="adm-item-main">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="adm-item-name">{{ $item->title }}</span>
                    @if ($item->isShowing())
                        <span class="badge badge-published">{{ $current && $current->id === $item->id ? 'On the banner' : 'Showing (behind a newer one)' }}</span>
                    @else
                        <span class="badge badge-brown">{{ $item->is_active ? 'Ended' : 'Stopped' }}</span>
                    @endif
                </div>
                <div class="adm-item-meta">
                    {{ $item->created_at?->diffForHumans() }}
                    · by {{ $item->author->name ?? 'a deleted admin' }}
                    · {{ $item->sent_to > 0 ? 'sent to '.$item->sent_to.' '.($item->sent_to === 1 ? 'person' : 'people') : 'banner only' }}
                </div>
                <p class="adm-item-body">{{ $item->message }}</p>
            </div>
            <div class="d-flex gap-2">
                @if ($item->isShowing())
                    <form method="POST" action="{{ route('admin.announcements.stop', $item) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-outline btn-sm">Stop showing</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.announcements.destroy', $item) }}"
                      data-confirm-title="Delete this announcement?"
                      data-confirm="It comes off the banner and this list. Notifications already sent stay in pilgrims' bells."
                      data-confirm-ok="Delete">
                    @csrf @method('DELETE')
                    <button type="submit" class="icon-btn is-danger" title="Delete"><i class="bi bi-trash3"></i></button>
                </form>
            </div>
        </div>
    </div>
@empty
    <div class="card"><x-empty-state icon="bell-fill" title="No announcements yet" desc="Post one with the form above." /></div>
@endforelse

@if ($ready)
    <div class="mt-3"><x-pagination :paginator="$announcements" /></div>
@endif
@endsection
