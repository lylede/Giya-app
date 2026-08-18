@extends('layouts.admin')
@section('title', 'Schedules')
@section('page-title', 'Schedule Management')
@section('page-subtitle', 'Manage Schedules for better and accurate information.')

@section('content')

<div class="card" style="padding:22px">

    <div class="d-flex justify-content-end mb-3">
        <button type="button" class="btn btn-primary" id="btnAddSchedule">
            + Add Schedule
        </button>
    </div>

    {{-- ── Search + filters ── --}}
    <form method="GET" id="filterForm">
        <div class="sm-search">
            <i class="bi bi-search"></i>
            <input type="search" name="search" value="{{ request('search') }}"
                   placeholder="Search Schedule..." aria-label="Search schedules">
        </div>

        <div class="sm-filters">
            <div class="field" style="flex:1 1 230px">
                <label class="dm-label" for="fchurch">Destination Name</label>
                <select id="fchurch" name="church_id" class="giya-input" onchange="this.form.submit()">
                    <option value="">All destinations</option>
                    @foreach ($churches as $church)
                        <option value="{{ $church->id }}" @selected(request('church_id') == $church->id)>
                            {{ $church->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field" style="flex:1 1 230px">
                <label class="dm-label" for="ftype">Event Type</label>
                <select id="ftype" name="event_type" class="giya-input" onchange="this.form.submit()">
                    <option value="">All types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(request('event_type') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field" style="flex:1 1 200px">
                <label class="dm-label" for="fday">Day / Date</label>
                <select id="fday" name="day" class="giya-input" onchange="this.form.submit()">
                    <option value="">Any day</option>
                    @foreach ($days as $day)
                        <option value="{{ $day }}" @selected(request('day') === $day)>{{ $day }}</option>
                    @endforeach
                </select>
            </div>

            <div class="d-flex gap-2 align-items-end" style="flex:0 0 auto">
                <a href="{{ route('admin.schedules') }}" class="btn btn-outline">
                    <i class="bi bi-arrow-clockwise"></i> Reset Filter
                </a>
                <a href="{{ route('admin.schedules.export', request()->query()) }}" class="btn btn-primary">
                    <i class="bi bi-download"></i> Export Schedule
                </a>
            </div>
        </div>
    </form>

    {{-- ── Table ── --}}
    <div style="overflow-x:auto;margin-top:18px">
        <table class="giya-table sm-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Destination</th>
                    <th>Event Name</th>
                    <th>Type</th>
                    <th>Day / Date</th>
                    <th>Time Frame<br>Label</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Whole<br>Day</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($schedules as $i => $s)
                    <tr>
                        <td>{{ $schedules->firstItem() + $i }}</td>
                        <td style="font-weight:700">{{ $s->church->name ?? '—' }}</td>
                        <td>{{ $s->event_name }}</td>
                        <td><span class="badge badge-gold">{{ $s->event_type }}</span></td>
                        <td>{{ $s->day_label }}</td>
                        <td>{{ $s->time_frame_label ?: '—' }}</td>
                        <td>{{ $s->timeLabel($s->start_time) }}</td>
                        <td>{{ $s->timeLabel($s->end_time) }}</td>
                        <td>{{ $s->is_whole_day ? 'Yes' : 'No' }}</td>
                        <td>{{ $s->location ?: '—' }}</td>
                        <td>
                            <span @class(['badge', 'badge-published' => $s->status === 'Published',
                                          'badge-brown' => $s->status !== 'Published'])>
                                {{ $s->status }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button type="button" class="icon-btn" title="Edit"
                                        data-schedule="{{ $s->toJson() }}">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <form method="POST" action="{{ route('admin.schedules.destroy', $s) }}"
                                      onsubmit="return confirm('Remove this schedule?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="icon-btn is-danger" title="Delete">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12" style="padding:0">
                        <x-empty-state icon="calendar-event" title="No schedules match"
                                       desc="Adjust the filters, or add the first schedule." />
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── Footer ── --}}
    <div class="sm-foot">
        <span>
            Showing {{ $schedules->firstItem() ?? 0 }} to {{ $schedules->lastItem() ?? 0 }}
            of {{ $schedules->total() }} schedule{{ $schedules->total() === 1 ? '' : 's' }}
        </span>

        <div class="d-flex align-items-center gap-3">
            <x-pagination :paginator="$schedules" />

            <form method="GET" class="d-flex align-items-center gap-2">
                @foreach (request()->except(['per_page', 'page']) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach
                <label for="perPage" style="font-size: 0.8125rem;color:var(--text-muted)">Rows per page</label>
                <select id="perPage" name="per_page" class="giya-input" style="width:74px;padding:6px 8px"
                        onchange="this.form.submit()">
                    @foreach ([5, 10, 25, 50] as $n)
                        <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════ Add / edit modal ══════════════ --}}
<div class="modal" id="scheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border:none;border-radius:var(--radius-2xl);padding:28px;max-width:640px">
            <div class="modal-title">
                <i class="bi bi-calendar-event-fill" style="color:var(--primary)"></i>
                <span id="scheduleModalTitle">Add Schedule</span>
            </div>

            <form method="POST" action="{{ route('admin.schedules.store') }}">
                @csrf
                <input type="hidden" name="schedule_id" id="s-id">

                <div class="dm-row">
                    <div class="field" style="flex:1 1 100%">
                        <label class="dm-label" for="s-church">Destination</label>
                        <select id="s-church" name="church_id" class="giya-input" required>
                            <option value="">Choose a destination…</option>
                            @foreach ($churches as $church)
                                <option value="{{ $church->id }}">{{ $church->name }}</option>
                            @endforeach
                        </select>
                        @error('church_id')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="dm-row">
                    <div class="field" style="flex:1 1 300px">
                        <label class="dm-label" for="s-name">Event Name</label>
                        <input id="s-name" type="text" name="event_name" class="giya-input" required
                               placeholder="Weekday Mass" maxlength="200">
                        @error('event_name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="field" style="flex:1 1 180px">
                        <label class="dm-label" for="s-type">Type</label>
                        <select id="s-type" name="event_type" class="giya-input" required>
                            @foreach ($types as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="dm-row">
                    <div class="field" style="flex:1 1 220px">
                        <label class="dm-label" for="s-label">Time Frame Label</label>
                        <input id="s-label" type="text" name="time_frame_label" class="giya-input"
                               placeholder="1st Mass" maxlength="100">
                    </div>

                    <div class="field" style="flex:1 1 220px">
                        <label class="dm-label" for="s-location">Location</label>
                        <input id="s-location" type="text" name="location" class="giya-input"
                               placeholder="Main Church" maxlength="150">
                    </div>
                </div>

                <div class="dm-row">
                    <div class="field" style="flex:1 1 220px">
                        <label class="dm-label" for="s-recurrence">Repeats on</label>
                        <select id="s-recurrence" name="recurrence" class="giya-input">
                            <option value="">Does not repeat — one-off date</option>
                            @foreach ($days as $day)
                                <option value="{{ $day }}">{{ $day }}</option>
                            @endforeach
                        </select>
                        <p style="font-size: 0.6875rem;color:var(--text-muted);margin:4px 0 0">
                            Leave blank for a single-date event, then set the date below.
                        </p>
                    </div>

                    <div class="field" style="flex:1 1 220px">
                        <label class="dm-label" for="s-date">Date (one-off only)</label>
                        <input id="s-date" type="date" name="schedule_date" class="giya-input">
                    </div>
                </div>

                <div class="dm-row">
                    <div class="field" style="flex:1 1 160px">
                        <label class="dm-label" for="s-start">Start Time</label>
                        <input id="s-start" type="time" name="start_time" class="giya-input">
                        @error('start_time')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="field" style="flex:1 1 160px">
                        <label class="dm-label" for="s-end">End Time</label>
                        <input id="s-end" type="time" name="end_time" class="giya-input">
                        @error('end_time')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="field" style="flex:1 1 150px">
                        <label class="dm-label" for="s-status">Status</label>
                        <select id="s-status" name="status" class="giya-input">
                            <option value="Published">Published</option>
                            <option value="Draft">Draft</option>
                        </select>
                    </div>
                </div>

                <label class="pref-toggle-row" style="border-top:1px solid var(--border);margin-top:6px">
                    <span>Whole day event</span>
                    <span class="pref-switch">
                        <input type="checkbox" name="is_whole_day" value="1" id="s-whole">
                        <span class="pref-switch-track"></span>
                    </span>
                </label>

                <div class="field">
                    <label class="dm-label" for="s-notes">Notes</label>
                    <textarea id="s-notes" name="notes" class="giya-input" rows="2"
                              placeholder="Anything devotees should know"></textarea>
                </div>

                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary" style="flex:1" id="s-submit">Save Schedule</button>
                    <button type="button" class="btn btn-outline" style="flex:1" data-modal-close>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const modal = 'scheduleModal';

    function reset() {
        document.getElementById('s-id').value        = '';
        document.getElementById('s-church').value    = '';
        document.getElementById('s-name').value      = '';
        document.getElementById('s-type').value      = 'Mass';
        document.getElementById('s-label').value     = '';
        document.getElementById('s-location').value  = '';
        document.getElementById('s-recurrence').value = '';
        document.getElementById('s-date').value      = '';
        document.getElementById('s-start').value     = '';
        document.getElementById('s-end').value       = '';
        document.getElementById('s-status').value    = 'Published';
        document.getElementById('s-whole').checked   = false;
        document.getElementById('s-notes').value     = '';
        document.getElementById('scheduleModalTitle').textContent = 'Add Schedule';
        document.getElementById('s-submit').textContent = 'Save Schedule';
    }

    document.getElementById('btnAddSchedule').addEventListener('click', function () {
        reset();
        GiyaUI.Modal.open(modal);
    });

    document.querySelectorAll('[data-schedule]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const s = JSON.parse(this.dataset.schedule);
            reset();

            document.getElementById('s-id').value         = s.id;
            document.getElementById('s-church').value     = s.church_id;
            document.getElementById('s-name').value       = s.event_name || '';
            document.getElementById('s-type').value       = s.event_type || 'Mass';
            document.getElementById('s-label').value      = s.time_frame_label || '';
            document.getElementById('s-location').value   = s.location || '';
            document.getElementById('s-recurrence').value = s.recurrence || '';
            document.getElementById('s-date').value       = s.schedule_date ? s.schedule_date.substring(0, 10) : '';
            document.getElementById('s-start').value      = s.start_time ? s.start_time.substring(0, 5) : '';
            document.getElementById('s-end').value        = s.end_time ? s.end_time.substring(0, 5) : '';
            document.getElementById('s-status').value     = s.status || 'Published';
            document.getElementById('s-whole').checked    = !!s.is_whole_day;
            document.getElementById('s-notes').value      = s.notes || '';

            document.getElementById('scheduleModalTitle').textContent = 'Edit Schedule';
            document.getElementById('s-submit').textContent = 'Save Changes';

            GiyaUI.Modal.open(modal);
        });
    });

    // A whole-day event has no start or end time.
    document.getElementById('s-whole').addEventListener('change', function () {
        const off = this.checked;
        ['s-start', 's-end'].forEach(function (id) {
            const el = document.getElementById(id);
            el.disabled = off;
            if (off) el.value = '';
        });
    });

    // Search submits on Enter without needing a visible button.
    document.querySelector('.sm-search input').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); document.getElementById('filterForm').submit(); }
    });

    @if ($errors->any())
        GiyaUI.Modal.open(modal);
    @endif
})();
</script>
@endpush
