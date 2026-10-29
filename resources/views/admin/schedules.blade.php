@extends('layouts.admin')
@section('title', 'Schedules')
@section('page-title', 'Schedule Management')
@section('page-subtitle', 'Manage Schedules for better and accurate information.')

@section('content')

<div class="card adm-panel">

    {{-- ── Search + filters ── --}}
    <form method="GET" id="filterForm">
        {{-- The button sits on the search row rather than alone above it.
             Four stacked rows of controls - a button, a search, three
             selects, two more buttons - pushed the table itself below the
             fold on a laptop, and the button had a whole row to itself to
             say two words. --}}
        <div class="sm-bar">
            <div class="sm-search">
                <i class="bi bi-search"></i>
                <input type="search" name="search" value="{{ request('search') }}"
                       placeholder="Search Schedule..." aria-label="Search schedules">
            </div>

            <button type="button" class="btn btn-primary sm-bar-action" id="btnAddSchedule">
                <i class="bi bi-plus-lg"></i> Add Schedule
            </button>
        </div>

        <div class="sm-filters">
            <div class="field">
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

            <div class="field">
                <label class="dm-label" for="ftype">Event Type</label>
                <select id="ftype" name="event_type" class="giya-input" onchange="this.form.submit()">
                    <option value="">All types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(request('event_type') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label class="dm-label" for="fday">Day / Date</label>
                <select id="fday" name="day" class="giya-input" onchange="this.form.submit()">
                    <option value="">Any day</option>
                    @foreach ($days as $day)
                        <option value="{{ $day }}" @selected(request('day') === $day)>{{ $day }}</option>
                    @endforeach
                </select>
            </div>

            <div class="adm-actions">
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
    <div class="adm-table-scroll">
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
                        <td class="cell-strong">{{ $s->church->name ?? '-' }}</td>
                        <td>{{ $s->event_name }}</td>
                        <td><span class="badge badge-gold">{{ $s->event_type }}</span></td>
                        <td>{{ $s->day_label }}</td>
                        <td>{{ $s->time_frame_label ?: '-' }}</td>
                        <td>{{ $s->timeLabel($s->start_time) }}</td>
                        <td>{{ $s->timeLabel($s->end_time) }}</td>
                        <td>{{ $s->is_whole_day ? 'Yes' : 'No' }}</td>
                        <td>{{ $s->location ?: '-' }}</td>
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
                                      data-confirm-title="Remove this schedule?"
                                      data-confirm="{{ $s->event_name }} at {{ $s->church->name ?? 'this destination' }} will no longer show for devotees."
                                      data-confirm-ok="Remove">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="icon-btn is-danger" title="Delete">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="is-flush">
                        <x-empty-state icon="giya-candle" title="No schedules match"
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
                <label for="perPage" class="per-page-label">Rows per page</label>
                <select id="perPage" name="per_page" class="giya-input per-page-select"
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
    <div class="modal-dialog is-wide">
        <div class="modal-content dm-form-modal">
            <div class="modal-title">
                <i class="bi bi-giya-candle"></i>
                <span id="scheduleModalTitle">Add Schedule</span>
            </div>

            <form method="POST" action="{{ route('admin.schedules.store') }}">
                @csrf
                <input type="hidden" name="schedule_id" id="s-id">

                <div class="dm-row">
                    <div class="field is-full">
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
                    <div class="field is-wide">
                        <label class="dm-label" for="s-name">Event Name</label>
                        <input id="s-name" type="text" name="event_name" class="giya-input" required
                               placeholder="Weekday Mass" maxlength="200">
                        @error('event_name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label class="dm-label" for="s-type">Type</label>
                        <select id="s-type" name="event_type" class="giya-input" required>
                            @foreach ($types as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="dm-row">
                    <div class="field">
                        <label class="dm-label" for="s-label">Time Frame Label</label>
                        <input id="s-label" type="text" name="time_frame_label" class="giya-input"
                               placeholder="1st Mass" maxlength="100">
                    </div>

                    <div class="field">
                        <label class="dm-label" for="s-location">Location</label>
                        <input id="s-location" type="text" name="location" class="giya-input"
                               placeholder="Main Church" maxlength="150">
                    </div>
                </div>

                {{--
                    How it repeats. One-off events take a date; repeating ones
                    take a rule, and GIYA works out the dates itself - e.g.
                    Yearly, 3rd Sunday of January = Sinulog every year, with a
                    reminder a week before each one.
                --}}
                @php
                    $wd = \App\Support\Recurrence::WEEKDAYS;
                    $weekOrder = [1, 2, 3, 4, 5, 6, 0];
                @endphp
                <div class="sched-repeat">
                    <div class="dm-row">
                        <div class="field">
                            <label class="dm-label" for="s-repeat">Repeats</label>
                            <select id="s-repeat" name="repeat_type" class="giya-input">
                                <option value="none">Does not repeat - one date</option>
                                <option value="daily">Every day</option>
                                <option value="weekly">Weekly - on certain days</option>
                                <option value="monthly">Monthly - e.g. 1st Friday</option>
                                <option value="yearly">Yearly - e.g. 3rd Sunday of January</option>
                            </select>
                        </div>

                        <div class="field" data-repeat="none">
                            <label class="dm-label" for="s-date">Date</label>
                            <input id="s-date" type="date" name="schedule_date" class="giya-input">
                        </div>
                    </div>

                    <div class="field" data-repeat="weekly" hidden>
                        <span class="dm-label">On</span>
                        <div class="sched-days" role="group" aria-label="Days of the week">
                            @foreach ($weekOrder as $d)
                                <label class="sched-day">
                                    <input type="checkbox" name="repeat_days[]" value="{{ $d }}">
                                    <span>{{ substr($wd[$d], 0, 3) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="dm-row" data-repeat="monthly yearly" hidden>
                        <div class="field">
                            <label class="dm-label" for="s-nth">On the</label>
                            <select id="s-nth" name="repeat_nth" class="giya-input">
                                @foreach (\App\Support\Recurrence::NTH as $n => $label)
                                    <option value="{{ $n }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="dm-label" for="s-weekday">Day</label>
                            <select id="s-weekday" name="repeat_weekday" class="giya-input">
                                @foreach ($weekOrder as $d)
                                    <option value="{{ $d }}" @selected($d === 0)>{{ $wd[$d] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" data-repeat="yearly" hidden>
                            <label class="dm-label" for="s-month">Of</label>
                            <select id="s-month" name="repeat_month" class="giya-input">
                                @foreach (\App\Support\Recurrence::MONTHS as $m => $name)
                                    <option value="{{ $m }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <p class="sched-next" id="s-next" aria-live="polite" hidden></p>
                </div>

                <div class="dm-row">
                    <div class="field is-narrow">
                        <label class="dm-label" for="s-start">Start Time</label>
                        <input id="s-start" type="time" name="start_time" class="giya-input">
                        @error('start_time')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="field is-narrow">
                        <label class="dm-label" for="s-end">End Time</label>
                        <input id="s-end" type="time" name="end_time" class="giya-input">
                        @error('end_time')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="field is-narrow">
                        <label class="dm-label" for="s-status">Status</label>
                        <select id="s-status" name="status" class="giya-input">
                            <option value="Published">Published</option>
                            <option value="Draft">Draft</option>
                        </select>
                    </div>
                </div>

                <label class="pref-toggle-row is-ruled">
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
                    <button type="submit" class="btn btn-primary is-grow" id="s-submit">Save Schedule</button>
                    <button type="button" class="btn btn-outline is-grow" data-modal-close>Cancel</button>
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
        setRepeat(null, null);
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
            setRepeat(s.recurrence_rule, s.recurrence);
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

    /* ---- Repeats: show the parts that apply, and the next date ---- */
    const repeat = document.getElementById('s-repeat');
    const WD = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    function nthWeekday(year, month, nth, weekday) {   // month 1-12
        if (nth === -1) {
            const d = new Date(year, month, 0);
            while (d.getDay() !== weekday) d.setDate(d.getDate() - 1);
            return d;
        }
        const d = new Date(year, month - 1, 1);
        while (d.getDay() !== weekday) d.setDate(d.getDate() + 1);
        d.setDate(d.getDate() + 7 * (nth - 1));
        return d;
    }

    // Same rule as App\Support\Recurrence::next, so the preview matches what is saved.
    function nextDates(type, nth, weekday, month, count) {
        const out = [], today = new Date(); today.setHours(0, 0, 0, 0);
        let y = today.getFullYear(), m = today.getMonth() + 1;
        for (let i = 0; out.length < count && i < 40; i++) {
            const d = type === 'yearly' ? nthWeekday(y + i, month, nth, weekday)
                                        : nthWeekday(y, m, nth, weekday);
            if (type === 'monthly') { m++; if (m > 12) { m = 1; y++; } }
            if (d >= today) out.push(d);
        }
        return out;
    }

    function refreshRepeat() {
        const type = repeat.value;
        document.querySelectorAll('[data-repeat]').forEach(function (el) {
            el.hidden = el.dataset.repeat.split(' ').indexOf(type) === -1;
        });
        document.getElementById('s-date').required = type === 'none';

        const note = document.getElementById('s-next');
        if (type === 'monthly' || type === 'yearly') {
            const fmt = function (d) { return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }); };
            const dates = nextDates(type, +document.getElementById('s-nth').value,
                +document.getElementById('s-weekday').value, +document.getElementById('s-month').value, 3);
            note.innerHTML = 'Next date: <strong>' + fmt(dates[0]) + '</strong>' +
                (dates.length > 1 ? ' <span>(then ' + dates.slice(1).map(fmt).join(', ') + ')</span>' : '') +
                (type === 'yearly' ? '<br>Followers are reminded a week before, every year.' : '');
            note.hidden = false;
        } else {
            note.hidden = true;
        }
    }

    /* Fill the builder from a saved rule, or from an old label like
       "Weekly (Sunday)" / "Daily" on rows saved before rules existed. */
    function setRepeat(rule, label) {
        document.querySelectorAll('input[name="repeat_days[]"]').forEach(function (c) { c.checked = false; });
        document.getElementById('s-nth').value = '1';
        document.getElementById('s-weekday').value = '0';
        document.getElementById('s-month').value = '1';

        let type = 'none';
        if (rule && rule.type) {
            type = rule.type;
            (rule.days || []).forEach(function (d) {
                const c = document.querySelector('input[name="repeat_days[]"][value="' + d + '"]');
                if (c) c.checked = true;
            });
            if (rule.nth !== undefined) document.getElementById('s-nth').value = String(rule.nth);
            if (rule.weekday !== undefined) document.getElementById('s-weekday').value = String(rule.weekday);
            if (rule.month !== undefined) document.getElementById('s-month').value = String(rule.month);
        } else if (label) {
            if (/daily/i.test(label)) {
                type = 'daily';
            } else {
                WD.forEach(function (name, i) {
                    if (label.indexOf(name) !== -1) {
                        type = 'weekly';
                        const c = document.querySelector('input[name="repeat_days[]"][value="' + i + '"]');
                        if (c) c.checked = true;
                    }
                });
            }
        }
        repeat.value = type;
        refreshRepeat();
    }

    repeat.addEventListener('change', refreshRepeat);
    ['s-nth', 's-weekday', 's-month'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', refreshRepeat);
    });
    refreshRepeat();

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
