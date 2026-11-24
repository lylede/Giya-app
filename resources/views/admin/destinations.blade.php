@extends('layouts.admin')

@section('title', 'Destinations')
@section('page-title', 'Destination Management')
@section('page-subtitle', $churches->total() . ' total destinations')

@push('head')
    <link rel="stylesheet" href="{{ asset('assets/css/leaflet.css') }}?v={{ filemtime(public_path('assets/css/leaflet.css')) }}">
@endpush

@section('content')

{{-- ═══════════════ HEADLINE FIGURES ═══════════════ --}}
<div class="stat-row">
    <x-stat-tile icon="giya-spires" label="Destinations" :value="$summary['total']" tone="primary" />
    <x-stat-tile icon="giya-star" label="Published" :value="$summary['published']" tone="green" />
    <x-stat-tile icon="eye-slash" label="Draft" :value="$summary['draft']" tone="gold" />
    <x-stat-tile icon="giya-nearby" label="On the map" :value="$summary['mapped']" tone="blue">
        <x-slot:sub>Pins a devotee can navigate to</x-slot:sub>
    </x-stat-tile>
</div>

{{-- ═══════════════ ADD / EDIT PANEL (inline, per the design) ═══════════════ --}}
{{-- ═══════════════ MAP: the way destinations are added ═══════════════
     The map is the interface, not an aid to a form beside it. A destination
     exists somewhere, so placing it comes first and describing it follows.
--}}
<section class="card dm-mapcard">
    <div class="dm-mapcard-head">
        <div>
            <h2 class="dm-panel-title">Destinations map</h2>
            <p class="dm-mapcard-sub">
                Click a pin to see everything about a church. Drag a pin to move it.
                Click an empty spot to add a new one.
            </p>
        </div>
        <div class="adm-actions">
            <button type="button" class="btn btn-outline" id="dmImportHere">
                <i class="bi bi-folder2-open"></i> Import
            </button>
            <button type="button" class="btn btn-primary" id="dmAddHere">
                <i class="bi bi-plus-lg"></i> Add Destination
            </button>
        </div>
    </div>

    {{-- Show only the churches that still need something. --}}
    @php
        $issueCount = fn ($code) => collect($markers)->filter(fn ($m) => in_array($code, $m['issues'], true))->count();
        $needAny = collect($markers)->filter(fn ($m) => count($m['issues']) > 0)->count();
    @endphp
    <div class="dm-chips" role="group" aria-label="Show on the map">
        <button type="button" class="dm-chip is-on" data-issue="">All <b>{{ count($markers) }}</b></button>
        <button type="button" class="dm-chip" data-issue="any"><i class="bi bi-info-circle"></i> Needs attention <b>{{ $needAny }}</b></button>
        <button type="button" class="dm-chip" data-issue="photo">No photo <b>{{ $issueCount('photo') }}</b></button>
        <button type="button" class="dm-chip" data-issue="schedule">No Mass schedule <b>{{ $issueCount('schedule') }}</b></button>
        <button type="button" class="dm-chip" data-issue="hidden">Hidden <b>{{ $issueCount('hidden') }}</b></button>
        <button type="button" class="dm-chip" data-issue="rating">Low rating <b>{{ $issueCount('rating') }}</b></button>
    </div>

    <div class="dm-map-shell" id="adminMapShell">
        <div class="dm-map-canvas" id="adminPickMap"></div>

        {{-- One church at a glance: click its pin. Editing still opens the
             usual pop-up; the panel is for seeing and quick actions. --}}
        <aside class="dm-side" id="dmSide" hidden aria-labelledby="dmSideName">
            <div class="dm-side-photo" id="dmSidePhoto"></div>
            <button type="button" class="dm-side-x" id="dmSideClose" aria-label="Close"><i class="bi bi-x-lg"></i></button>
            <div class="dm-side-head">
                <h3 class="dm-side-name" id="dmSideName"></h3>
                <p class="dm-side-place" id="dmSidePlace"></p>
                <div class="dm-side-issues" id="dmSideIssues"></div>
            </div>
            <div class="dm-side-tabs" role="tablist">
                <button type="button" role="tab" class="dm-side-tab is-on" data-tab="details">Details</button>
                <button type="button" role="tab" class="dm-side-tab" data-tab="photos">Photos</button>
                <button type="button" role="tab" class="dm-side-tab" data-tab="schedule">Schedule</button>
                <button type="button" role="tab" class="dm-side-tab" data-tab="feedback">Feedback</button>
            </div>
            <div class="dm-side-body" id="dmSideBody" role="tabpanel"></div>
        </aside>

        <div class="map-tools">
            <button type="button" class="map-tool" id="dmFullscreen"
                    title="Fullscreen" aria-label="Toggle fullscreen">
                <i class="bi bi-arrows-fullscreen"></i>
            </button>
            <div class="map-tool-pair">
                <button type="button" class="map-tool" id="dmZoomIn" title="Zoom in" aria-label="Zoom in">
                    <i class="bi bi-plus-lg"></i>
                </button>
                <button type="button" class="map-tool" id="dmZoomOut" title="Zoom out" aria-label="Zoom out">
                    <i class="bi bi-dash-lg"></i>
                </button>
            </div>
        </div>

        <p class="dm-map-hint-bar" id="dmHint">
            Click the map to place a destination
        </p>
    </div>
</section>

{{-- ═══════════════════════════ FILTERS ═══════════════════════════ --}}
{{--
    Filtering always returns to page one. Narrowing the results while on page 3
    of the old set lands on a page that no longer exists, and the table comes
    back empty - which reads as "the filter is broken".
--}}
<form method="GET" class="dm-filters" data-soft-list-form="dmList">
    <div class="dm-filter-field is-wide">
        <label class="dm-label" for="q">Search Destination</label>
        <input id="q" type="search" name="search" value="{{ $search }}" class="giya-input"
               placeholder="Search by name or location...">
    </div>

    <div class="dm-filter-field">
        <label class="dm-label" for="fcat">Category</label>
        <select id="fcat" name="category" class="giya-input" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
            @foreach ($categories as $cat)
                <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat === 'All' ? 'All Categories' : $cat }}</option>
            @endforeach
        </select>
    </div>

    <div class="dm-filter-field">
        <label class="dm-label" for="fstatus">Status</label>
        <select id="fstatus" name="status" class="giya-input" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
            <option value="">All Status</option>
            <option value="active" @selected(request('status') === 'active')>Published</option>
            <option value="hidden" @selected(request('status') === 'hidden')>Draft</option>
        </select>
    </div>

    <div class="dm-filter-actions">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-search"></i> Apply
        </button>
        @if ($search || ($category && $category !== 'All') || request('status'))
            <a href="{{ route('admin.destinations') }}" class="btn btn-outline" data-soft-list-link="dmList">
                <i class="bi bi-arrow-clockwise"></i> Reset
            </a>
        @endif
    </div>
</form>

{{-- ═══════════════════════════ TABLE ═══════════════════════════ --}}
{{-- Next page, rows per page and the filters swap only this list (giya-soft-list.js): the map stays as it is. --}}
<div id="dmList" class="soft-list" data-soft-list tabindex="-1" aria-live="polite">
<div class="card adm-scroller">
    <div>
    <table class="giya-table">
        <thead>
            <tr>
                <th class="col-no">No.</th>
                <th>Name</th>
                <th>Location</th>
                <th>Category</th>
                <th>Rating</th>
                <th>Status</th>
                <th class="col-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($churches as $i => $church)
                <tr>
                    <td>{{ $churches->firstItem() + $i }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <img class="gs-img cell-thumb" src="{{ $church->imagePath() }}" alt="" loading="lazy" decoding="async">
                            <span class="cell-strong">{{ $church->name }}</span>
                        </div>
                    </td>
                    <td class="cell-muted">{{ $church->location }}</td>
                    <td><span class="badge badge-gold">{{ $church->category }}</span></td>
                    <td>
                        @if ($church->rating > 0)
                            <i class="bi bi-star-fill cell-star"></i>
                            {{ number_format($church->rating, 1) }}
                        @else
                            <span class="cell-muted">-</span>
                        @endif
                    </td>
                    <td>
                        <span @class(['badge', 'badge-primary' => $church->is_active, 'badge-brown' => ! $church->is_active])>
                            {{ $church->is_active ? 'Published' : 'Draft' }}
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <button type="button" class="icon-btn" title="Edit"
                                    data-edit="{{ $church->id }}">
                                <i class="bi bi-pencil-square"></i>
                            </button>

                            {{-- Straight to this church's weekly timetable. --}}
                            <a href="{{ route('admin.schedules', ['view' => 'church', 'church_id' => $church->id]) }}"
                               class="icon-btn" title="Edit schedule" aria-label="Edit schedule of {{ $church->name }}">
                                <i class="bi bi-calendar3"></i>
                            </a>

                            <form method="POST" action="{{ route('admin.destinations.toggle', $church) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="icon-btn"
                                        title="{{ $church->is_active ? 'Move to draft' : 'Publish' }}">
                                    <i class="bi bi-{{ $church->is_active ? 'eye-slash' : 'eye' }}"></i>
                                </button>
                            </form>

                            <form method="POST" action="{{ route('admin.destinations.destroy', $church) }}"
                                  data-confirm-title="Delete {{ $church->name }}?"
                                  data-confirm="Its photos, and any visit records, reviews, saved favourites and itinerary stops that reference it are deleted too. Use the eye icon to hide it instead."
                                  data-confirm-ok="Delete destination">
                                @csrf @method('DELETE')
                                <button type="submit" class="icon-btn is-danger" title="Delete permanently">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="cell-empty">
                    No destinations yet. Add the first one above.
                </td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>

@include('admin.partials.table-foot', ['paginator' => $churches, 'perPage' => $perPage, 'noun' => 'destination', 'options' => \App\Http\Controllers\Controller::PER_PAGE, 'id' => 'destPer'])
</div>

{{--
    Quick add, used while the map is expanded.

    In fullscreen the form is off screen, so dropping a pin has nowhere to go.
    Rather than collapsing the map and making the admin find their place again,
    the essential fields come to them. Everything else - hours, description,
    photo - is filled in afterwards on the full form.
--}}
<div class="modal" id="quickAddModal" tabindex="-1" aria-hidden="true">
    {{-- Four fields. Narrow on purpose. --}}
    <div class="modal-dialog">
        <div class="modal-content dm-form-modal">
            <div class="modal-title">
                <i class="bi bi-giya-spires"></i>
                New destination here
            </div>

            <p class="quick-coords" id="quickCoords"></p>

            <div class="field">
                <label class="dm-label" for="q-name">Destination Name</label>
                <input id="q-name" type="text" class="giya-input" maxlength="200"
                       placeholder="Archdiocesan Shrine of ...">
            </div>

            <div class="dm-row">
                <div class="field">
                    <label class="dm-label" for="q-category">Category</label>
                    <select id="q-category" class="giya-input">
                        @foreach ($categories as $cat)
                            @continue ($cat === 'All')
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label class="dm-label" for="q-location">Location</label>
                    <input id="q-location" type="text" class="giya-input" maxlength="200"
                           placeholder="Cebu City">
                </div>
            </div>

            <p class="quick-note">
                Hours, history and a photograph are added on the form below once
                this destination is created.
            </p>

            <div class="modal-actions">
                <button type="button" class="btn btn-primary is-grow" id="quickApply">
                    Add to form
                </button>
                <button type="button" class="btn btn-outline is-grow" data-modal-close>Cancel</button>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog is-wide">
        <div class="modal-content dm-form-modal">
            <div class="dm-modal-head">
                <div class="modal-title is-flush">
                    <i class="bi bi-folder2-open"></i>
                    Import destinations
                </div>
                <button type="button" class="ai-panel-close" data-modal-close aria-label="Close">
                    <i class="bi bi-x"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.destinations.import') }}" enctype="multipart/form-data">
                @csrf

                <div class="field">
                    <label class="dm-label" for="importFile">CSV or JSON file</label>
                    <input id="importFile" type="file" name="import_file" class="giya-input" accept=".csv,.json,text/csv,application/json" required>
                    @error('import_file')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="dm-map-note is-spaced">
                    <i class="bi bi-info-circle"></i>
                    Supported columns: name, category, location, address, latitude, longitude, opening_time, closing_time, description, history, status.
                </div>

                <pre class="dm-csv-sample">name,category,location,address,latitude,longitude,opening_time,closing_time,description,status
Basilica del Sto. Niño,Church,Cebu City,Osmeña Blvd,10.29410000,123.90340000,08:00,17:00,"Historic church","Published"</pre>

                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary is-grow">Upload bulk import</button>
                    <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═══════════════ ONE MODAL: add and edit ═══════════════
     Every field lives here, photograph included. The inline panel is gone -
     two copies of the same form is two sets of rules to keep in step.

     It posts to the same route as before, so validation and storage are
     unchanged. On a validation failure the modal reopens with the values the
     admin typed, rather than losing them.
--}}
{{-- Outside the modal on purpose: a form nested inside another form is invalid
     HTML and the browser drops the inner one. The action is set in JavaScript,
     since the id is only known once a destination has been opened. --}}
<form method="POST" id="deleteForm" data-base="{{ url('admin/destinations') }}">
    @csrf
    @method('DELETE')
</form>

<div class="modal" id="destModal" tabindex="-1" aria-hidden="true">
    {{-- The widest of the three, because it is the longest form in the app.
         At 460px every one of its fields was its own row. --}}
    <div class="modal-dialog is-wider">
        <div class="modal-content dm-modal">

            <div class="dm-modal-head">
                <div class="modal-title is-flush">
                    <i class="bi bi-giya-spires"></i>
                    <span id="destModalTitle">Add Destination</span>
                </div>
                <button type="button" class="ai-panel-close" data-modal-close aria-label="Close">
                    <i class="bi bi-x"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.destinations.store') }}"
                  enctype="multipart/form-data" id="destForm" class="dm-modal-body">
                @csrf
                <input type="hidden" name="church_id" id="churchId" value="{{ old('church_id') }}">

                <div class="dm-row">
                    <div class="field is-full">
                        <label class="dm-label" for="f-name">Destination Name</label>
                        <input id="f-name" type="text" name="name" class="giya-input" required maxlength="200"
                               value="{{ old('name') }}"
                               placeholder="Archdiocesan Shrine of the Most Sacred Heart of Jesus">
                        @error('name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="dm-row">
                    <div class="field">
                        <label class="dm-label" for="f-category">Category</label>
                        <select id="f-category" name="category" class="giya-input" required>
                            @foreach ($categories as $cat)
                                @continue ($cat === 'All')
                                <option value="{{ $cat }}" @selected(old('category') === $cat)>{{ $cat }}</option>
                            @endforeach
                        </select>
                        @error('category')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label class="dm-label" for="f-status">Status</label>
                        <select id="f-status" name="status" class="giya-input">
                            <option value="Published">Published</option>
                            <option value="Draft" @selected(old('status') === 'Draft')>Draft</option>
                        </select>
                    </div>
                </div>

                <div class="dm-row">
                    <div class="field is-full">
                        <label class="dm-label" for="f-location">Location (City / Municipality)</label>
                        <input id="f-location" type="text" name="location" class="giya-input" required
                               value="{{ old('location') }}" placeholder="Cebu City">
                        @error('location')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="dm-row">
                    <div class="field">
                        <label class="dm-label" for="f-municipality">Municipality</label>
                        {{-- A list rather than free text, because this one is
                             grouped and filtered on: two spellings of the same
                             town are two towns as far as the map is concerned.
                             Left blank it is read out of the address. --}}
                        <input id="f-municipality" type="text" name="municipality" class="giya-input"
                               list="dm-towns" maxlength="120" value="{{ old('municipality') }}"
                               placeholder="Read from the address if left blank">
                        <datalist id="dm-towns">
                            @foreach (\App\Http\Controllers\Admin\ChurchController::TOWNS as $town)
                                <option value="{{ $town }}"></option>
                            @endforeach
                        </datalist>
                        @error('municipality')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="field is-bottom">
                        <label class="dm-check" for="f-major">
                            <input id="f-major" type="checkbox" name="is_major" value="1"
                                   @checked(old('is_major'))>
                            <span>
                                <strong>Major church of this municipality</strong>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="field">
                    <label class="dm-label" for="f-address">Exact Address (Optional)</label>
                    <input id="f-address" type="text" name="address" class="giya-input" maxlength="255"
                           value="{{ old('address') }}"
                           placeholder="242 Dionisio Jakosalem St, Cebu City">
                </div>

                {{-- The parish office, shown on the church page under Contact. --}}
                <div class="dm-row">
                    <div class="field">
                        <label class="dm-label" for="f-phone">Phone (Optional)</label>
                        <input id="f-phone" type="text" name="contact_phone" class="giya-input" maxlength="150"
                               value="{{ old('contact_phone') }}" placeholder="(032) 255-8823, 0956 992 4224">
                    </div>
                    <div class="field">
                        <label class="dm-label" for="f-email">Email (Optional)</label>
                        <input id="f-email" type="email" name="contact_email" class="giya-input" maxlength="150"
                               value="{{ old('contact_email') }}" placeholder="parish@example.com">
                        @error('contact_email')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="field">
                    <label class="dm-label" for="f-facebook">Facebook page (Optional)</label>
                    <input id="f-facebook" type="url" name="facebook_url" class="giya-input" maxlength="300"
                           value="{{ old('facebook_url') }}" placeholder="https://www.facebook.com/yourparish">
                    @error('facebook_url')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                {{-- One map only: the big map above. "Move pin" zooms it onto this
                     church so the admin drags or clicks the exact spot there.
                     Coordinates can also be pasted from Google Maps. --}}
                <div class="dm-row dm-coords">
                    <div class="field is-narrow">
                        <label class="dm-label" for="f-lat">Latitude</label>
                        <input id="f-lat" type="number" step="0.00000001" name="latitude"
                               class="giya-input" value="{{ old('latitude') }}">
                        @error('latitude')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field is-narrow">
                        <label class="dm-label" for="f-lng">Longitude</label>
                        <input id="f-lng" type="number" step="0.00000001" name="longitude"
                               class="giya-input" value="{{ old('longitude') }}">
                        @error('longitude')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field is-bottom is-auto">
                        <button type="button" class="btn btn-outline btn-sm" id="dmRepin">
                            <i class="bi bi-geo-alt-fill"></i> Move pin
                        </button>
                    </div>
                </div>
                <div class="field is-full">
                    <label class="dm-label" for="f-pinpaste">Or paste from Google Maps</label>
                    <input id="f-pinpaste" type="text" class="giya-input" autocomplete="off"
                           placeholder="A Google Maps link, or coordinates like 10.2945, 123.9022">
                    <span class="field-error" id="dmPinPasteError" hidden>
                        No coordinates found. In Google Maps, right-click the church and click the numbers to copy them.
                    </span>
                </div>

                <div class="dm-row">
                    <div class="field is-narrow">
                        <label class="dm-label" for="f-open">Opens</label>
                        <input id="f-open" type="time" name="opening_time" class="giya-input"
                               value="{{ old('opening_time') }}">
                    </div>
                    <div class="field is-narrow">
                        <label class="dm-label" for="f-close">Closes</label>
                        <input id="f-close" type="time" name="closing_time" class="giya-input"
                               value="{{ old('closing_time') }}">
                    </div>
                </div>

                <div class="field">
                    <label class="dm-label" for="f-desc">Short Description</label>
                    <textarea id="f-desc" name="description" class="giya-input" rows="2"
                              placeholder="One or two lines, shown on cards and the home page">{{ old('description') }}</textarea>
                </div>

                <div class="field">
                    <label class="dm-label" for="f-history">History</label>
                    <textarea id="f-history" name="history" class="giya-input" rows="6"
                              placeholder="When it was founded, by whom, and what happened since. Shown on the church page and used by Giya AI.">{{ old('history') }}</textarea>
                </div>

                <div class="field">
                    <label class="dm-label">Destination Photograph</label>

                    <label class="dm-drop" id="dropZone">
                        <input type="file" name="photo" id="f-photo"
                               accept="image/jpeg,image/png,image/webp" hidden>
                        <img id="dropPreview" alt="" hidden>
                        <span class="dm-drop-inner" id="dropPrompt">
                            <span class="dm-drop-circle"><i class="bi bi-camera-fill"></i></span>
                            <span class="dm-drop-text">Click to upload, or drag an image here</span>
                            <span class="dm-drop-sub">JPG or PNG</span>
                        </span>
                    </label>

                    <input type="text" name="caption" class="giya-input mt-2"
                           placeholder="Photo caption (optional)" maxlength="255">
                    @error('photo')<span class="field-error">{{ $message }}</span>@enderror
                    <p class="dm-map-note is-tight">
                        <i class="bi bi-info-circle"></i>
                        Saved to public/images/churches, named after the destination.
                    </p>
                </div>

                <div class="dm-modal-foot">
                    <button type="submit" class="btn btn-primary is-grow" id="btnSave">
                        Save Destination
                    </button>
                    <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>

                    {{-- Only meaningful once a destination exists, so it is
                         hidden while adding one. --}}
                    <button type="button" class="btn btn-danger-solid dm-delete"
                            id="btnDelete" hidden>
                        <i class="bi bi-trash3"></i> Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('assets/js/leaflet.js') }}?v={{ filemtime(public_path('assets/js/leaflet.js')) }}"></script>
<script src="{{ asset('assets/js/leaflet.markercluster.js') }}?v={{ filemtime(public_path('assets/js/leaflet.markercluster.js')) }}"></script>
<script src="{{ asset('assets/js/giya-leaflet.js') }}?v={{ filemtime(public_path('assets/js/giya-leaflet.js')) }}"></script>
<script src="{{ asset('assets/js/giya-soft-list.js') }}?v={{ filemtime(public_path('assets/js/giya-soft-list.js')) }}"></script>
<script>
(function () {
    const markers = @json($markers);
    const rows    = @json($rows);

    let expanded = false;
    let picking  = false;      // waiting for a map click to place a pin

    const shell = document.getElementById('adminMapShell');
    const fsBtn = document.getElementById('dmFullscreen');
    const hint  = document.getElementById('dmHint');

    const picker = GiyaLeaflet.picker({
        element: 'adminPickMap',
        churches: markers,
        latInput: '#f-lat',
        lngInput: '#f-lng',

        /*
         * The pin moved. Two different meanings:
         *  - Dragging the pin, or the click after "Move pin", moves the
         *    destination already in the form. Only the coordinates change;
         *    the form is reopened exactly as it was (the picker has already
         *    written the new latitude and longitude into it).
         *  - Any other click on empty map means "a new destination here".
         * Before, both cleared the form and opened an empty Add Destination,
         * so moving a pin lost everything that had been typed.
         */
        onPin: function (lat, lng, how) {
            const moving = picking || how === 'drag';
            picking = false;
            hint.textContent = 'Click the map to place a destination';
            hint.classList.remove('is-active');

            if (moving) {
                GiyaUI.Modal.open('destModal');
                        flashCoords();
            } else {
                openModal(null, lat, lng);
                guessTown(lat, lng);
            }
        },

        /* A click on a pin opens its side panel. */
        onChurchClick: function (c) { openSide(c.id); },

        /* A pin dragged somewhere else: ask, then save, with Undo. */
        onChurchMove: function (c, lat, lng, undo, keep) {
            GiyaConfirm.ask({
                title: 'Move ' + c.name + ' here?',
                message: 'The pin will stay where you dropped it, and directions will lead here.',
                ok: 'Move pin',
            }).then(function (yes) {
                if (!yes) { undo(); return; }
                saveMove(c.id, lat, lng).then(function (ok) {
                    if (!ok) { undo(); GiyaToast.show('Could not move ' + c.name + '. Try again.', { error: true }); return; }
                    const oldLat = c.lat, oldLng = c.lng;
                    keep();
                    const r = rows.filter(function (x) { return x.id === c.id; })[0];
                    if (r) { r.lat = lat; r.lng = lng; }
                    GiyaToast.show('Moved ' + c.name + '.', {
                        action: 'Undo', ms: 6000,
                        onAction: function () {
                            saveMove(c.id, oldLat, oldLng).then(function () {
                                c.lat = oldLat; c.lng = oldLng;
                                if (r) { r.lat = oldLat; r.lng = oldLng; }
                                picker.moveTo(c.id, oldLat, oldLng);
                            });
                        }
                    });
                });
            });
        },
        showIssues: true,
        cluster: true,     // nearby pins group into one bubble until zoomed in
        loaderMinMs: 0     // no waiting loading screen for admins
    });

    /* A new pin: fill in the town from the nearest church already on the
       map (within 8 km). The admin can still change it. */
    function guessTown(lat, lng) {
        const box = field('f-municipality');
        if (!box || box.value) return;
        let best = null, bestKm = 8;
        rows.forEach(function (r) {
            if (!r.lat || !r.lng || !r.municipality) return;
            const dLat = (r.lat - lat) * 111, dLng = (r.lng - lng) * 111 * Math.cos(lat * Math.PI / 180);
            const km = Math.sqrt(dLat * dLat + dLng * dLng);
            if (km < bestKm) { bestKm = km; best = r; }
        });
        if (best) {
            box.value = best.municipality;
            box.classList.add('is-guessed');
            setTimeout(function () { box.classList.remove('is-guessed'); }, 1600);
        }
    }

    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    function saveMove(id, lat, lng) {
        return fetch(@json(url('admin/destinations')) + '/' + id + '/move', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ latitude: lat, longitude: lng })
        }).then(function (r) { return r.ok; }).catch(function () { return false; });
    }

    /* ---------------------------------------------- needs-attention chips */
    let issueFilter = '';
    function drawFiltered() {
        picker.filter(markers, function (m) {
            if (!issueFilter) return true;
            if (issueFilter === 'any') return m.issues.length > 0;
            return m.issues.indexOf(issueFilter) !== -1;
        });
        if (sideId) picker.select(sideId);
    }
    document.querySelectorAll('.dm-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            document.querySelectorAll('.dm-chip').forEach(function (c) { c.classList.toggle('is-on', c === chip); });
            issueFilter = chip.dataset.issue;
            drawFiltered();
        });
    });

    /* --------------------------------------------------------- side panel */
    const side = document.getElementById('dmSide');
    const sideBody = document.getElementById('dmSideBody');
    const ISSUE = {
        photo:    ['camera-fill', 'No photo', 'photos'],
        schedule: ['calendar3', 'No Mass schedule', 'schedule'],
        hidden:   ['eye-slash', 'Hidden from devotees', 'details'],
        rating:   ['star-fill', 'Low rating', 'feedback'],
    };
    let sideId = null, sideData = null, sideTab = 'details';
    const esc = GiyaUI.esc;

    function openSide(id) {
        const r = rows.filter(function (x) { return x.id === id; })[0];
        const m = markers.filter(function (x) { return x.id === id; })[0];
        if (!r) return;
        sideId = id; sideData = null;
        picker.select(id);
        document.getElementById('dmSideName').textContent = r.name;
        document.getElementById('dmSidePlace').textContent = [r.location, r.municipality].filter(Boolean).filter(function (v, i, a) { return a.indexOf(v) === i; }).join(' · ');
        document.getElementById('dmSidePhoto').style.backgroundImage = "url('" + r.image + "')";
        document.getElementById('dmSideIssues').innerHTML = ((m && m.issues) || []).map(function (k) {
            return '<button type="button" class="dm-issue" data-goto="' + ISSUE[k][2] + '"><i class="bi bi-' + ISSUE[k][0] + '"></i> ' + ISSUE[k][1] + '</button>';
        }).join('');
        side.hidden = false;
        shell.classList.add('has-side');
        // The map narrows to make room for the panel: tell it its new size,
        // then bring the church back into view if it ended up off the edge.
        setTimeout(function () {
            picker.map.invalidateSize();
            if (r.lat && r.lng && !picker.map.getBounds().pad(-0.08).contains([r.lat, r.lng])) {
                picker.map.panTo([r.lat, r.lng]);
            }
        }, 220);
        showTab('details');
        fetch(@json(url('admin/destinations')) + '/' + id + '/panel', { headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (d) { if (sideId === id) { sideData = d; showTab(sideTab); } })
            .catch(function () { if (sideId === id) sideBody.innerHTML = '<p class="dm-side-empty">Could not load this church. Try again.</p>'; });
    }

    function closeSide() {
        side.hidden = true; sideId = null;
        shell.classList.remove('has-side');
        picker.select(null);
        setTimeout(function () { picker.map.invalidateSize(); }, 220);
    }
    document.getElementById('dmSideClose').addEventListener('click', closeSide);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !side.hidden && !document.querySelector('.modal.is-open')) closeSide();
    });

    document.querySelectorAll('.dm-side-tab').forEach(function (t) {
        t.addEventListener('click', function () { showTab(t.dataset.tab); });
    });
    document.getElementById('dmSideIssues').addEventListener('click', function (e) {
        const b = e.target.closest('[data-goto]');
        if (b) showTab(b.dataset.goto);
    });

    function stars(n) {
        let s = '';
        for (let i = 1; i <= 5; i++) s += '<i class="bi bi-star-fill' + (i <= Math.round(n) ? '' : ' is-off') + '"></i>';
        return '<span class="dm-stars" aria-label="' + n + ' of 5">' + s + '</span>';
    }

    function showTab(tab) {
        sideTab = tab;
        document.querySelectorAll('.dm-side-tab').forEach(function (t) {
            const on = t.dataset.tab === tab;
            t.classList.toggle('is-on', on); t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        const r = rows.filter(function (x) { return x.id === sideId; })[0];
        if (!r) return;
        const d = sideData;
        const wait = '<p class="dm-side-empty">Loading…</p>';
        let html = '';

        if (tab === 'details') {
            const line = function (icon, label, value) {
                return value ? '<div class="dm-kv"><i class="bi bi-' + icon + '"></i><div><span>' + label + '</span><b>' + esc(value) + '</b></div></div>' : '';
            };
            html = '<div class="dm-side-actions">'
                 + '<button type="button" class="btn btn-primary btn-sm" data-act="edit"><i class="bi bi-pencil-square"></i> Edit details</button>'
                 + '<form method="POST" action="' + @json(url('admin/destinations')) + '/' + r.id + '/toggle" class="d-inline">'
                 + '<input type="hidden" name="_token" value="' + CSRF + '"><input type="hidden" name="_method" value="PATCH">'
                 + '<button class="btn btn-outline btn-sm"><i class="bi bi-' + (r.active ? 'eye-slash' : 'eye') + '"></i> ' + (r.active ? 'Set to Draft' : 'Publish') + '</button></form>'
                 + '<a class="btn btn-outline btn-sm" target="_blank" href="' + @json(url('churches')) + '/' + r.id + '"><i class="bi bi-box-arrow-up-right"></i> View page</a>'
                 + '</div>'
                 + '<div class="dm-kvs">'
                 + line('list', 'Category', r.category)
                 + line('geo-alt', 'Address', r.address || r.location)
                 + line('clock', 'Open', (r.open && r.close) ? r.open + ' - ' + r.close : '')
                 + line('telephone', 'Phone', r.phone)
                 + line('envelope', 'Email', r.email)
                 + '</div>'
                 + (r.description ? '<p class="dm-side-desc">' + esc(r.description.length > 260 ? r.description.slice(0, 260) + '…' : r.description) + '</p>' : '');
        }

        if (tab === 'photos') {
            if (!d) { sideBody.innerHTML = wait; return; }
            html = (d.photos.length
                    ? '<div class="dm-photos">' + d.photos.map(function (p) {
                        return '<figure class="dm-photo' + (p.primary ? ' is-primary' : '') + '"><img src="' + esc(p.url) + '" alt="" loading="lazy">'
                             + (p.primary ? '<figcaption>Main photo</figcaption>' : '') + '</figure>'; }).join('') + '</div>'
                    : '<p class="dm-side-empty"><i class="bi bi-camera-fill"></i> No photo yet. Devotees see a plain picture for now.</p>')
                 + '<form method="POST" enctype="multipart/form-data" action="' + @json(url('admin/destinations')) + '/' + r.id + '/photo" class="dm-photo-up">'
                 + '<input type="hidden" name="_token" value="' + CSRF + '">'
                 + '<label class="btn btn-primary btn-sm"><i class="bi bi-arrow-up"></i> ' + (d.photos.length ? 'Replace main photo' : 'Upload a photo')
                 + '<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" hidden onchange="this.form.submit()"></label>'
                 + '<span class="dm-hint">JPG, PNG or WebP, up to 4 MB.</span></form>';
        }

        if (tab === 'schedule') {
            if (!d) { sideBody.innerHTML = wait; return; }
            const sc = d.schedule;
            html = '<div class="dm-side-actions"><a class="btn btn-primary btn-sm" href="' + esc(sc.url) + '"><i class="bi bi-calendar3"></i> Edit timetable</a>'
                 + '<span class="dm-hint">' + sc.published + ' published' + (sc.drafts ? ' · ' + sc.drafts + ' draft' : '') + '</span></div>'
                 + (sc.items.length
                    ? '<ul class="dm-sched">' + sc.items.map(function (s) {
                        return '<li><b>' + esc(s.time || '-') + '</b><span>' + esc(s.name) + '<small>' + esc(s.when) + (s.status !== 'Published' ? ' · ' + esc(s.status) : '') + '</small></span></li>'; }).join('') + '</ul>'
                    : '<p class="dm-side-empty"><i class="bi bi-calendar3"></i> No Masses or events yet. Add them in the timetable.</p>');
        }

        if (tab === 'feedback') {
            if (!d) { sideBody.innerHTML = wait; return; }
            const f = d.feedback;
            html = '<div class="dm-fb-sum">' + (f.average !== null ? '<b>' + f.average + '</b>' + stars(f.average) : '<b>-</b>')
                 + '<span>' + f.count + ' approved review' + (f.count === 1 ? '' : 's') + (f.pending ? ' · <em>' + f.pending + ' waiting</em>' : '') + '</span></div>'
                 + (f.items.length
                    ? '<ul class="dm-reviews">' + f.items.map(function (x) {
                        return '<li class="dm-review is-' + x.status.toLowerCase() + '" data-review="' + x.id + '">'
                             + '<div class="dm-review-top">' + stars(x.rating) + '<span>' + esc(x.by) + ' · ' + esc(x.when || '') + '</span></div>'
                             + (x.comment ? '<p>' + esc(x.comment) + '</p>' : '')
                             + '<div class="dm-review-act">'
                             + (x.status !== 'Approved' ? '<button type="button" class="btn btn-sm btn-primary" data-review-set="Approved" data-url="' + esc(x.update) + '">Approve</button>' : '<span class="dm-tag is-ok">Approved</span>')
                             + (x.status !== 'Flagged' ? '<button type="button" class="btn btn-sm btn-outline" data-review-set="Flagged" data-url="' + esc(x.update) + '">Hide</button>' : '<span class="dm-tag">Hidden</span>')
                             + '</div></li>'; }).join('') + '</ul>'
                    : '<p class="dm-side-empty"><i class="bi bi-chat-square-text"></i> No reviews yet.</p>');
        }

        sideBody.innerHTML = html;
    }

    sideBody.addEventListener('click', function (e) {
        if (e.target.closest('[data-act="edit"]')) { openModal(sideId); return; }
        const b = e.target.closest('[data-review-set]');
        if (!b) return;
        b.disabled = true;
        fetch(b.dataset.url, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ status: b.dataset.reviewSet })
        }).then(function (res) {
            if (!res.ok) throw new Error();
            const id = Number(b.closest('[data-review]').dataset.review);
            sideData.feedback.items.forEach(function (x) { if (x.id === id) x.status = b.dataset.reviewSet; });
            GiyaToast.show(b.dataset.reviewSet === 'Approved' ? 'Review approved.' : 'Review hidden.');
            showTab('feedback');
        }).catch(function () { b.disabled = false; GiyaToast.show('Could not update the review.', { error: true }); });
    });

    /* ---------------------------------------------------------- modal ---- */

    function field(id) { return document.getElementById(id); }

    /* Briefly highlight the coordinates so the admin sees what changed. */
    function flashCoords() {
        ['f-lat', 'f-lng'].forEach(function (id) {
            const el = field(id);
            el.classList.remove('is-updated');
            void el.offsetWidth;                   // restart the animation
            el.classList.add('is-updated');
        });
    }

    /* Show a photo in the upload box, or the "click to upload" prompt when
       src is null. Toggles the hidden attribute: giya.css has
       [hidden] { display: none !important; }, which beat the old
       style.display = 'block' - the photo loaded but never appeared, and with
       the prompt hidden the admin saw an empty white box. */
    function showPhoto(src) {
        const img = field('dropPreview');

        img.hidden = !src;
        field('dropPrompt').hidden = !!src;

        if (src) {
            img.src = src;
        } else {
            img.removeAttribute('src');
        }
    }

    function clearForm() {
        field('destForm').reset();
        field('churchId').value = '';
        showPhoto(null);
        field('destModalTitle').textContent = 'Add Destination';
        field('btnSave').textContent = 'Save Destination';
    }

    function openModal(churchId, lat, lng) {
        clearForm();

        if (churchId) {
            const c = rows.filter(function (r) { return r.id === churchId; })[0];
            if (!c) return;

            field('churchId').value    = c.id;
            field('f-name').value      = c.name;
            field('f-category').value  = c.category;
            field('f-location').value  = c.location || '';
            field('f-municipality').value = c.municipality || '';
            field('f-major').checked   = !!c.major;
            field('f-address').value   = c.address || '';
            field('f-phone').value     = c.phone || '';
            field('f-email').value     = c.email || '';
            field('f-facebook').value  = c.facebook || '';
            field('f-lat').value       = c.lat || '';
            field('f-lng').value       = c.lng || '';
            field('f-open').value      = c.open || '';
            field('f-close').value     = c.close || '';
            field('f-desc').value      = c.description || '';
            field('f-history').value   = c.history || '';
            field('f-status').value    = c.active ? 'Published' : 'Draft';

            if (c.image) showPhoto(c.image);

            field('destModalTitle').textContent = 'Edit Destination';
            field('btnSave').textContent = 'Save Changes';

            if (c.lat && c.lng) picker.place(c.lat, c.lng, true);
        } else if (lat !== null && lat !== undefined) {
            field('f-lat').value = lat.toFixed(8);
            field('f-lng').value = lng.toFixed(8);
        }

        // Delete only applies to something that already exists. The button
        // starts with the hidden attribute, and [hidden] is display:none
        // !important in giya.css - so it must be the attribute that changes,
        // not style.display (which left the button hidden even when editing).
        const del = field('btnDelete');
        del.hidden = !churchId;
        del.dataset.church = churchId || '';
        del.dataset.name = churchId ? (field('f-name').value || 'this destination') : '';

        GiyaUI.Modal.open('destModal');
        setTimeout(function () { field('f-name').focus(); }, 120);
    }

    /* Add Destination asks for a location first: a destination without
       coordinates cannot appear on the map, so it is not optional. */
    /* Add Destination opens the form. The pin is placed from inside it with
       Move pin, which is the same map click either way - and this way the
       admin is not thrown into fullscreen before they have typed anything. */
    document.getElementById('dmAddHere').addEventListener('click', function () {
        openModal(null, null, null);
    });

    document.getElementById('dmImportHere').addEventListener('click', function () {
        GiyaUI.Modal.open('importModal');
    });

    /* Move pin: close the form and zoom the big map onto this church, its
       pin ready to drag. A click on the exact spot (or letting go of the
       dragged pin) puts the coordinates in the form and reopens it.
       Before, the map stayed zoomed out on Cebu City, so finding the church
       again was most of the work. */
    document.getElementById('dmRepin').addEventListener('click', function () {
        const lat = parseFloat(field('f-lat').value), lng = parseFloat(field('f-lng').value);
        const has = isFinite(lat) && isFinite(lng);

        GiyaUI.Modal.close('destModal');
        picking = true;
        hint.textContent = has
            ? 'Drag the pin, or click the exact spot of the church'
            : 'Click the exact spot of the church';
        hint.classList.add('is-active');
        if (!expanded) expand();

        setTimeout(function () {
            picker.map.invalidateSize();
            if (has) {
                picker.place(lat, lng, true);
                picker.map.setView([lat, lng], 18);
            } else {
                picker.map.setZoom(Math.max(picker.map.getZoom(), 15));
            }
        }, 160);
    });

    /* "10.2945, 123.9022" or a Google Maps link (…/@10.29,123.90,17z,
       …!3d10.29!4d123.90, ?q=10.29,123.90): the coordinates are taken out
       of it, so the admin can copy the spot straight from Google Maps. */
    function coordsIn(text) {
        const t = String(text || '');
        const m = t.match(/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/)
               || t.match(/@(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)/)
               || t.match(/(-?\d{1,3}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)/);
        if (!m) return null;
        const lat = parseFloat(m[1]), lng = parseFloat(m[2]);
        return (Math.abs(lat) <= 90 && Math.abs(lng) <= 180) ? [lat, lng] : null;
    }

    function usePasted() {
        const box = field('f-pinpaste'), err = field('dmPinPasteError');
        const c = coordsIn(box.value);
        err.hidden = !!c || box.value.trim() === '';
        if (!c) return;
        field('f-lat').value = c[0].toFixed(8);
        field('f-lng').value = c[1].toFixed(8);
        picker.place(c[0], c[1], true);
        picker.map.setView(c, 17);
        flashCoords();
        box.value = '';
    }

    field('f-pinpaste').addEventListener('paste', function () { setTimeout(usePasted, 0); });
    field('f-pinpaste').addEventListener('change', usePasted);
    field('f-pinpaste').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); usePasted(); }
    });

    /* ------------------------------------------------------ fullscreen ---- */

    function expand() {
        expanded = true;
        shell.classList.add('is-fullscreen');
        document.body.style.overflow = 'hidden';
        fsBtn.innerHTML = '<i class="bi bi-fullscreen-exit"></i>';
        fsBtn.title = 'Exit fullscreen';
        setTimeout(function () { picker.map.invalidateSize(); }, 120);
    }

    function collapse() {
        expanded = false;
        shell.classList.remove('is-fullscreen');
        document.body.style.overflow = '';
        fsBtn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';
        fsBtn.title = 'Fullscreen';
        hint.classList.remove('is-active');
        setTimeout(function () { picker.map.invalidateSize(); }, 120);
    }

    fsBtn.addEventListener('click', function () { expanded ? collapse() : expand(); });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (document.querySelector('.modal.is-open')) return;   // let the modal close first
        if (expanded) collapse();
    });

    document.getElementById('dmZoomIn').addEventListener('click', function () { picker.map.zoomIn(); });
    document.getElementById('dmZoomOut').addEventListener('click', function () { picker.map.zoomOut(); });

    /* The modal sits above an expanded map, so the page must stay locked when
       it closes while the map is still expanded. */
    document.addEventListener('click', function (e) {
        if (!e.target.closest('[data-modal-close]')) return;
        if (expanded) setTimeout(function () { document.body.style.overflow = 'hidden'; }, 0);
    });

    /* Delete asks first, and says what goes with it. The confirmation dialog
       is the shared one, so this matches every other destructive action. */
    document.getElementById('btnDelete').addEventListener('click', function () {
        const id = this.dataset.church;
        if (!id) return;

        GiyaConfirm.ask({
            title: 'Delete ' + (this.dataset.name || 'this destination') + '?',
            message: 'Its photograph, and any visit records, reviews, saved '
                   + 'favourites and itinerary stops that reference it are '
                   + 'deleted too. Set it to Draft instead to hide it.',
            ok: 'Delete destination',
            tone: 'danger',
        }).then(function (yes) {
            if (!yes) return;
            const form = document.getElementById('deleteForm');
            form.action = form.dataset.base + '/' + id;
            form.submit();
        });
    });

    /* ---- edit from the table ---- */
    // One listener for the whole page: the list's rows are swapped when
    // changing page, and buttons bound one by one would stop working.
    document.addEventListener('click', function (e) {
        const b = e.target.closest('[data-edit]');
        if (b) openModal(Number(b.dataset.edit));
    });

    /* ---- photograph ---- */
    const zone  = document.getElementById('dropZone');
    const input = document.getElementById('f-photo');

    function preview(file) {
        if (!file) return;
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
            alert('Choose a JPG, PNG or WEBP image.');
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) { showPhoto(e.target.result); };
        reader.readAsDataURL(file);
    }

    input.addEventListener('change', function () { preview(this.files[0]); });

    ['dragenter', 'dragover'].forEach(function (ev) {
        zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.add('is-over'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        zone.addEventListener(ev, function (e) { e.preventDefault(); zone.classList.remove('is-over'); });
    });
    zone.addEventListener('drop', function (e) {
        if (!e.dataTransfer.files.length) return;
        input.files = e.dataTransfer.files;
        preview(e.dataTransfer.files[0]);
    });

    /* A rejected save reopens the modal with what was typed, rather than
       leaving the admin looking at a map wondering what happened. */
    @if ($errors->any())
        GiyaUI.Modal.open('destModal');
    @endif

    /* After a save the page reloads. Open the map on the church just saved,
       instead of zooming all the way out to Cebu City; otherwise on the view
       the admin last had. */
    // Opened from the dashboard's to-do list or the quick search.
    (function fromLink() {
        const want = @json(request('issue'));
        const chip = want && document.querySelector('.dm-chip[data-issue="' + want + '"]');
        if (chip) { chip.click(); document.querySelector('.dm-mapcard').scrollIntoView({ block: 'start' }); }
    })();

    (function keepView() {
        const KEY = 'giya.admin.mapView';
        const focusId = @json(session('focus_church') ?? (request('focus') ? (int) request('focus') : null));
        const saved = focusId ? rows.filter(function (r) { return r.id === focusId; })[0] : null;

        if (saved && saved.lat && saved.lng) {
            picker.map.setView([saved.lat, saved.lng], 16);
            openSide(saved.id);
        } else {
            try {
                const v = JSON.parse(sessionStorage.getItem(KEY) || 'null');
                if (v && isFinite(v.lat) && isFinite(v.lng) && isFinite(v.zoom)) picker.map.setView([v.lat, v.lng], v.zoom);
            } catch (e) { /* no storage: start on Cebu City */ }
        }

        picker.map.on('moveend', function () {
            const c = picker.map.getCenter();
            try { sessionStorage.setItem(KEY, JSON.stringify({lat: c.lat, lng: c.lng, zoom: picker.map.getZoom()})); } catch (e) {}
        });
    })();
})();
</script>
@endpush
