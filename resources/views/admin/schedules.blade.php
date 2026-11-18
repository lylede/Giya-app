@extends('layouts.admin')
@section('title', 'Schedules')
@section('page-title', 'Schedule Management')
@section('page-subtitle', 'Manage Schedules for better and accurate information.')

@section('content')

{{-- Two ways in: By church (a timetable for editing one church's week)
     and All schedules (the long list, for searching and bulk changes). --}}
<nav class="sm-tabs" aria-label="Schedule views">
    <a href="{{ route('admin.schedules', array_filter(['view' => 'church', 'church_id' => request('church_id')])) }}"
       @class(['sm-tab', 'is-on' => $view === 'church'])>
        <i class="bi bi-calendar3"></i> By church
    </a>
    <a href="{{ route('admin.schedules', ['view' => 'all']) }}" @class(['sm-tab', 'is-on' => $view === 'all'])>
        <i class="bi bi-list"></i> All schedules
    </a>
</nav>

@if ($view === 'church')
@php
    // Monday first, the way a parish bulletin reads.
    $weekOrder = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'];
@endphp
<div class="card adm-panel tt">
    <form method="GET" class="tt-pick" id="ttPick">
        <input type="hidden" name="view" value="church">
        <input type="hidden" name="church_id" id="ttChurchId" value="{{ $church->id ?? '' }}">
        <label class="dm-label" for="ttChurch">Church</label>
        <div class="sm-search tt-pick-box">
            <i class="bi bi-search"></i>
            <input type="search" id="ttChurch" list="ttChurches" autocomplete="off"
                   value="{{ $church->name ?? '' }}" placeholder="Type a church name, e.g. Basilica">
        </div>
        <datalist id="ttChurches">
            @foreach ($churches as $c)
                <option value="{{ $c->name }}" data-id="{{ $c->id }}">{{ ($counts[$c->id] ?? 0) }} schedules</option>
            @endforeach
        </datalist>
    </form>

    @if (! $church)
        <x-empty-state icon="calendar3" title="Pick a church"
                       desc="Type a church name above to see and edit its weekly Mass times." />
    @else
        <div class="tt-head">
            <div>
                <h2 class="tt-title">{{ $church->name }}</h2>
                <p class="tt-sub">{{ $weekly->count() }} weekly {{ $weekly->count() === 1 ? 'Mass' : 'Masses' }}
                    · {{ $dated->count() }} other {{ $dated->count() === 1 ? 'date' : 'dates' }}.
                    Tap a day to add or remove it.</p>
            </div>
            <div class="tt-actions">
                <button type="button" class="btn btn-primary" id="ttAdd" data-church="{{ $church->id }}">
                    <i class="bi bi-plus-lg"></i> Add Mass
                </button>
                <button type="button" class="btn btn-outline" data-modal-open="copyModal">
                    <i class="bi bi-journal-text"></i> Copy from another church
                </button>
            </div>
        </div>

        <div class="adm-table-scroll">
            <table class="giya-table tt-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Mass</th>
                        @foreach ($weekOrder as $n => $short)<th class="tt-daycol">{{ $short }}</th>@endforeach
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($weekly as $s)
                        @php $on = $s->weekdays() ?? []; @endphp
                        <tr>
                            <td class="cell-strong tt-time">
                                {{ $s->is_whole_day ? 'Whole day' : $s->timeLabel($s->start_time) }}
                                @if ($s->end_time)<span class="tt-end">to {{ $s->timeLabel($s->end_time) }}</span>@endif
                            </td>
                            <td>
                                {{ $s->event_name }}
                                <span class="tt-meta">
                                    @if ($s->event_type !== 'Mass')<span class="badge badge-gold">{{ $s->event_type }}</span>@endif
                                    @if ($s->language)<span class="sched-lang">{{ $s->language }}</span>@endif
                                </span>
                            </td>
                            @foreach ($weekOrder as $n => $short)
                                <td class="tt-daycol">
                                    <form method="POST" action="{{ route('admin.schedules.days', $s) }}">
                                        @csrf
                                        <button type="submit" name="day" value="{{ $n }}"
                                                @class(['tt-day', 'is-on' => in_array($n, $on, true)])
                                                aria-pressed="{{ in_array($n, $on, true) ? 'true' : 'false' }}"
                                                aria-label="{{ $s->event_name }} {{ $s->timeLabel($s->start_time) }} on {{ \App\Support\Recurrence::WEEKDAYS[$n] }}">
                                            @if (in_array($n, $on, true))<i class="bi bi-check-lg"></i>@endif
                                        </button>
                                    </form>
                                </td>
                            @endforeach
                            <td>
                                <span @class(['badge', 'badge-published' => $s->status === 'Published',
                                              'badge-brown' => $s->status !== 'Published'])>{{ $s->status }}</span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button type="button" class="icon-btn is-edit" title="Edit" data-schedule="{{ $s->toJson() }}">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <form method="POST" action="{{ route('admin.schedules.destroy', $s) }}"
                                          data-confirm-title="Remove this Mass?"
                                          data-confirm="{{ $s->event_name }} at {{ $s->timeLabel($s->start_time) }} will no longer show for devotees."
                                          data-confirm-ok="Remove">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="icon-btn is-danger" title="Delete"><i class="bi bi-trash3"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="is-flush">
                            <x-empty-state icon="calendar3" title="No weekly Masses yet"
                                           desc="Add one, or copy the schedule of a church with the same times." />
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h3 class="tt-subhead">Feast days, novenas and other dates</h3>
        @if ($dated->isEmpty())
            <p class="tt-none">None for this church.</p>
        @else
            <ul class="tt-dated">
                @foreach ($dated as $s)
                    <li>
                        <span class="tt-dated-when">{{ $s->day_label }}</span>
                        <span class="tt-dated-what">
                            <strong>{{ $s->event_name }}</strong>
                            <span class="badge badge-gold">{{ $s->event_type }}</span>
                            @if ($s->start_time)<span class="tt-meta">{{ $s->timeLabel($s->start_time) }}</span>@endif
                        </span>
                        <span @class(['badge', 'badge-published' => $s->status === 'Published',
                                      'badge-brown' => $s->status !== 'Published'])>{{ $s->status }}</span>
                        <span class="d-flex gap-1">
                            <button type="button" class="icon-btn is-edit" title="Edit" data-schedule="{{ $s->toJson() }}">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <form method="POST" action="{{ route('admin.schedules.destroy', $s) }}"
                                  data-confirm-title="Remove this event?" data-confirm="{{ $s->event_name }} will no longer show for devotees."
                                  data-confirm-ok="Remove">
                                @csrf @method('DELETE')
                                <button type="submit" class="icon-btn is-danger" title="Delete"><i class="bi bi-trash3"></i></button>
                            </form>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Copy every schedule of another church into this one, as Drafts. --}}
        <div class="modal" id="copyModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-title"><i class="bi bi-journal-text"></i> Copy a schedule</div>
                    <form method="POST" action="{{ route('admin.schedules.copy') }}">
                        @csrf
                        <input type="hidden" name="to_church_id" value="{{ $church->id }}">
                        <label class="dm-label" for="copyFrom">Copy every Mass and event from</label>
                        <select id="copyFrom" name="from_church_id" class="giya-input" required>
                            <option value="">Pick a church</option>
                            @foreach ($churches as $c)
                                @if ($c->id !== $church->id && ($counts[$c->id] ?? 0) > 0)
                                    <option value="{{ $c->id }}">{{ $c->name }} ({{ $counts[$c->id] }})</option>
                                @endif
                            @endforeach
                        </select>
                        <p class="tt-note">They are added to {{ $church->name }} as <strong>Draft</strong>, so devotees
                            do not see them until you check the times and publish them.</p>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-outline is-grow" data-modal-close>Cancel</button>
                            <button type="submit" class="btn btn-primary is-grow">Copy</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
@else

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

    {{-- ── Bulk bar: shows once a row is ticked ── --}}
    <form method="POST" action="{{ route('admin.schedules.bulk') }}" id="bulkForm" class="sm-bulk" hidden
          data-confirm-title="Change the ticked schedules?" data-confirm="This applies to every schedule you ticked."
          data-confirm-ok="Yes, apply">
        @csrf
        <span class="sm-bulk-count" id="bulkCount">0 selected</span>
        <button type="submit" name="action" value="publish" class="btn btn-outline btn-sm"><i class="bi bi-check-circle-fill"></i> Publish</button>
        <button type="submit" name="action" value="draft" class="btn btn-outline btn-sm"><i class="bi bi-eye-slash"></i> Set to Draft</button>
        <button type="submit" name="action" value="delete" class="btn btn-danger-solid btn-sm"><i class="bi bi-trash3"></i> Delete</button>
    </form>

    {{-- ── Table ── --}}
    <div class="adm-table-scroll">
        <table class="giya-table sm-table">
            <thead>
                <tr>
                    <th class="sm-check"><input type="checkbox" id="bulkAll" aria-label="Select all on this page"></th>
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
                        <td class="sm-check"><input type="checkbox" name="ids[]" value="{{ $s->id }}" form="bulkForm"
                                                    class="bulk-pick" aria-label="Select {{ $s->event_name }}"></td>
                        <td>{{ $schedules->firstItem() + $i }}</td>
                        <td class="cell-strong">
                            @if ($s->church)
                                {{ $s->church->name }}
                            @else
                                {{ $s->location ?: '-' }}
                                <span class="sched-town-tag">Town festival</span>
                            @endif
                        </td>
                        <td>{{ $s->event_name }}</td>
                        <td>
                            <span class="badge badge-gold">{{ $s->event_type }}</span>
                            @if ($s->language)<span class="sched-lang">{{ $s->language }}</span>@endif
                        </td>
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
                                <button type="button" class="icon-btn is-edit" title="Edit"
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
                    <tr><td colspan="13" class="is-flush">
                        <x-empty-state icon="calendar3" title="No schedules match"
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

@endif

{{-- ══════════════ Add / edit modal ══════════════ --}}
<div class="modal" id="scheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog is-wide">
        <div class="modal-content dm-form-modal">
            <div class="modal-title">
                <i class="bi bi-calendar-event"></i>
                <span id="scheduleModalTitle">Add Schedule</span>
            </div>

            <form method="POST" action="{{ route('admin.schedules.store') }}">
                @csrf
                <input type="hidden" name="schedule_id" id="s-id">

                <div class="dm-row">
                    <div class="field is-full">
                        <label class="dm-label" for="s-church">Destination</label>
                        <select id="s-church" name="church_id" class="giya-input">
                            <option value="">None - a town festival (type the town in Location)</option>
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

                    <div class="field">
                        <label class="dm-label" for="s-language">Language</label>
                        <select id="s-language" name="language" class="giya-input">
                            <option value="">Not set</option>
                            @foreach (\App\Models\Schedule::LANGUAGES as $language)
                                <option value="{{ $language }}">{{ $language }}</option>
                            @endforeach
                        </select>
                        <p class="dm-hint">Shown as a colour on the church page. Leave "Not set" unless the parish confirms it.</p>
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

                    <div class="field" data-repeat="yearly" hidden>
                        <label class="dm-label" for="s-yearly-on">Every year on</label>
                        <select id="s-yearly-on" name="repeat_yearly_on" class="giya-input">
                            <option value="nth">A weekday of a month - e.g. 3rd Sunday of January</option>
                            <option value="date">A date - e.g. every January 15</option>
                            <option value="easter">Easter Sunday (date changes every year)</option>
                        </select>
                    </div>

                    <div class="dm-row" data-repeat="monthly yearly" data-yearly="nth date" hidden>
                        <div class="field" data-repeat="monthly yearly" data-yearly="nth">
                            <label class="dm-label" for="s-nth">On the</label>
                            <select id="s-nth" name="repeat_nth" class="giya-input">
                                @foreach (\App\Support\Recurrence::NTH as $n => $label)
                                    <option value="{{ $n }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" data-repeat="monthly yearly" data-yearly="nth">
                            <label class="dm-label" for="s-weekday">Day</label>
                            <select id="s-weekday" name="repeat_weekday" class="giya-input">
                                @foreach ($weekOrder as $d)
                                    <option value="{{ $d }}" @selected($d === 0)>{{ $wd[$d] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" data-repeat="yearly" data-yearly="nth date" hidden>
                            <label class="dm-label" for="s-month">Of</label>
                            <select id="s-month" name="repeat_month" class="giya-input">
                                @foreach (\App\Support\Recurrence::MONTHS as $m => $name)
                                    <option value="{{ $m }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" data-repeat="yearly" data-yearly="date" hidden>
                            <label class="dm-label" for="s-day">Day</label>
                            <input id="s-day" type="number" name="repeat_day" min="1" max="31" class="giya-input" placeholder="15">
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
        document.getElementById('s-language').value  = '';
        document.getElementById('scheduleModalTitle').textContent = 'Add Schedule';
        document.getElementById('s-submit').textContent = 'Save Schedule';
    }

    // "Add Schedule" is on the All schedules list; "Add Mass" is on a
    // church's timetable and starts the form on that church, weekly.
    const addBtn = document.getElementById('btnAddSchedule');
    if (addBtn) addBtn.addEventListener('click', function () {
        reset();
        GiyaUI.Modal.open(modal);
    });
    const ttAdd = document.getElementById('ttAdd');
    if (ttAdd) ttAdd.addEventListener('click', function () {
        reset();
        document.getElementById('s-church').value = this.dataset.church;
        document.getElementById('s-name').value = 'Mass';
        setRepeat({ type: 'weekly', days: [0] }, null);
        document.getElementById('scheduleModalTitle').textContent = 'Add Mass';
        GiyaUI.Modal.open(modal);
    });

    /* ---- By church: pick a church by typing its name ---- */
    const ttChurch = document.getElementById('ttChurch');
    if (ttChurch) {
        const pick = function () {
            const name = ttChurch.value.trim().toLowerCase();
            const opt = Array.prototype.find.call(document.querySelectorAll('#ttChurches option'),
                function (o) { return o.value.toLowerCase() === name; });
            if (!opt || opt.dataset.id === document.getElementById('ttChurchId').value) return;
            document.getElementById('ttChurchId').value = opt.dataset.id;
            document.getElementById('ttPick').submit();
        };
        ttChurch.addEventListener('change', pick);
        ttChurch.addEventListener('input', pick);
    }

    /* ---- All schedules: tick rows, then act on them together ---- */
    const bulkForm = document.getElementById('bulkForm');
    if (bulkForm) {
        const picks = Array.prototype.slice.call(document.querySelectorAll('.bulk-pick'));
        const all = document.getElementById('bulkAll');
        const refresh = function () {
            const n = picks.filter(function (c) { return c.checked; }).length;
            bulkForm.hidden = n === 0;
            document.getElementById('bulkCount').textContent = n + ' selected';
            if (all) all.checked = n > 0 && n === picks.length;
        };
        picks.forEach(function (c) { c.addEventListener('change', refresh); });
        if (all) all.addEventListener('change', function () {
            picks.forEach(function (c) { c.checked = all.checked; });
            refresh();
        });
    }

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
            document.getElementById('s-language').value   = s.language || '';

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

    // Same as App\Support\Recurrence::easter.
    function easter(y) {
        const a = y % 19, b = Math.floor(y / 100), c = y % 100, d = Math.floor(b / 4), e = b % 4,
              f = Math.floor((b + 8) / 25), g = Math.floor((b - f + 1) / 3), h = (19 * a + b - d - g + 15) % 30,
              i = Math.floor(c / 4), k = c % 4, l = (32 + 2 * e + 2 * i - h - k) % 7,
              m = Math.floor((a + 11 * h + 22 * l) / 451), n = h + l - 7 * m + 114;
        return new Date(y, Math.floor(n / 31) - 1, (n % 31) + 1);
    }

    function nextYearly(on, month, day, count) {
        const out = [], today = new Date(); today.setHours(0, 0, 0, 0);
        if (on === 'date' && !day) return out;
        for (let y = today.getFullYear(); out.length < count && y < today.getFullYear() + 5; y++) {
            const d = on === 'easter' ? easter(y)
                : new Date(y, month - 1, Math.min(day, new Date(y, month, 0).getDate()));
            if (d >= today) out.push(d);
        }
        return out;
    }

    function refreshRepeat() {
        const type = repeat.value;
        const on = document.getElementById('s-yearly-on').value;
        document.querySelectorAll('[data-repeat]').forEach(function (el) {
            const forType = el.dataset.repeat.split(' ').indexOf(type) !== -1;
            const forKind = type !== 'yearly' || !el.dataset.yearly || el.dataset.yearly.split(' ').indexOf(on) !== -1;
            el.hidden = !(forType && forKind);
        });
        document.getElementById('s-date').required = type === 'none';

        const note = document.getElementById('s-next');
        if (type === 'monthly' || type === 'yearly') {
            const fmt = function (d) { return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }); };
            const dates = type === 'yearly' && on !== 'nth'
                ? nextYearly(on, +document.getElementById('s-month').value, +document.getElementById('s-day').value, 3)
                : nextDates(type, +document.getElementById('s-nth').value,
                    +document.getElementById('s-weekday').value, +document.getElementById('s-month').value, 3);
            if (!dates.length || isNaN(dates[0])) { note.hidden = true; return; }
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
        document.getElementById('s-yearly-on').value = 'nth';
        document.getElementById('s-day').value = '';

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
            if (rule.day !== undefined) { document.getElementById('s-yearly-on').value = 'date'; document.getElementById('s-day').value = String(rule.day); }
            if (rule.easter !== undefined) document.getElementById('s-yearly-on').value = 'easter';
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
    ['s-nth', 's-weekday', 's-month', 's-yearly-on'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', refreshRepeat);
    });
    document.getElementById('s-day').addEventListener('input', refreshRepeat);
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
    // Only on All schedules: By church has its own box, and no filterForm.
    const filterForm = document.getElementById('filterForm');
    const filterSearch = filterForm && filterForm.querySelector('.sm-search input');
    if (filterSearch) filterSearch.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); filterForm.submit(); }
    });

    @if ($errors->any())
        GiyaUI.Modal.open(modal);
    @endif
})();
</script>
@endpush
