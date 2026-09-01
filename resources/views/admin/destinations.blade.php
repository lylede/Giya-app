@extends('layouts.admin')

@section('title', 'Destinations')
@section('page-title', 'Destination Management')
@section('page-subtitle', $churches->total() . ' total destinations')

@push('head')
    <link rel="stylesheet" href="{{ asset('assets/css/leaflet.css') }}?v={{ filemtime(public_path('assets/css/leaflet.css')) }}">
@endpush

@section('content')

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
                Click anywhere to add a destination, or click a pin to edit one.
            </p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline" id="dmImportHere">
                <i class="bi bi-upload"></i> Import
            </button>
            <button type="button" class="btn btn-primary" id="dmAddHere">
                <i class="bi bi-plus-lg"></i> Add Destination
            </button>
        </div>
    </div>

    <div class="dm-map-shell" id="adminMapShell">
        <div class="dm-map-canvas" id="adminPickMap"></div>

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
<form method="GET" class="dm-filters">
    <div class="dm-filter-field" style="flex:1 1 320px">
        <label class="dm-label" for="q">Search Destination</label>
        <input id="q" type="search" name="search" value="{{ $search }}" class="giya-input"
               placeholder="Search by name or location...">
    </div>

    <div class="dm-filter-field" style="flex:0 1 190px">
        <label class="dm-label" for="fcat">Category</label>
        <select id="fcat" name="category" class="giya-input" onchange="this.form.submit()">
            @foreach ($categories as $cat)
                <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat === 'All' ? 'All Categories' : $cat }}</option>
            @endforeach
        </select>
    </div>

    <div class="dm-filter-field" style="flex:0 1 190px">
        <label class="dm-label" for="fstatus">Status</label>
        <select id="fstatus" name="status" class="giya-input" onchange="this.form.submit()">
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
            <a href="{{ route('admin.destinations') }}" class="btn btn-outline">
                <i class="bi bi-arrow-clockwise"></i> Reset
            </a>
        @endif
    </div>
</form>

{{-- ═══════════════════════════ TABLE ═══════════════════════════ --}}
<div class="card" style="overflow:hidden">
    <table class="giya-table">
        <thead>
            <tr>
                <th style="width:56px">No.</th>
                <th>Name</th>
                <th>Location</th>
                <th>Category</th>
                <th>Rating</th>
                <th>Status</th>
                <th style="width:120px">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($churches as $i => $church)
                <tr>
                    <td>{{ $churches->firstItem() + $i }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <img src="{{ $church->imagePath() }}" alt=""
                                 style="width:34px;height:34px;border-radius:8px;object-fit:cover;flex-shrink:0">
                            <span style="font-weight:600">{{ $church->name }}</span>
                        </div>
                    </td>
                    <td style="color:var(--text-muted)">{{ $church->location }}</td>
                    <td><span class="badge badge-gold">{{ $church->category }}</span></td>
                    <td>
                        @if ($church->rating > 0)
                            <i class="bi bi-star-fill" style="color:var(--gold);font-size: 0.6875rem"></i>
                            {{ number_format($church->rating, 1) }}
                        @else
                            <span style="color:var(--text-muted)">-</span>
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
                <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">
                    No destinations yet. Add the first one above.
                </td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:14px"><x-pagination :paginator="$churches->withQueryString()" /></div>

{{--
    Quick add, used while the map is expanded.

    In fullscreen the form is off screen, so dropping a pin has nowhere to go.
    Rather than collapsing the map and making the admin find their place again,
    the essential fields come to them. Everything else - hours, description,
    photo - is filled in afterwards on the full form.
--}}
<div class="modal" id="quickAddModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border:none;border-radius:var(--radius-2xl);padding:26px;max-width:460px">
            <div class="modal-title">
                <i class="bi bi-geo-alt-fill" style="color:var(--primary)"></i>
                New destination here
            </div>

            <p class="quick-coords" id="quickCoords"></p>

            <div class="field">
                <label class="dm-label" for="q-name">Destination Name</label>
                <input id="q-name" type="text" class="giya-input" maxlength="200"
                       placeholder="Archdiocesan Shrine of ...">
            </div>

            <div class="dm-row">
                <div class="field" style="flex:1 1 180px">
                    <label class="dm-label" for="q-category">Category</label>
                    <select id="q-category" class="giya-input">
                        @foreach ($categories as $cat)
                            @continue ($cat === 'All')
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field" style="flex:1 1 180px">
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
                <button type="button" class="btn btn-primary" style="flex:1" id="quickApply">
                    Add to form
                </button>
                <button type="button" class="btn btn-outline" style="flex:1" data-modal-close>Cancel</button>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border:none;border-radius:var(--radius-2xl);padding:26px;max-width:520px">
            <div class="dm-modal-head" style="margin-bottom:14px">
                <div class="modal-title" style="margin:0">
                    <i class="bi bi-upload" style="color:var(--primary)"></i>
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

                <div class="dm-map-note" style="margin-top:10px; margin-bottom:14px;">
                    <i class="bi bi-info-circle"></i>
                    Supported columns: name, category, location, address, latitude, longitude, opening_time, closing_time, description, status.
                </div>

                <pre style="background:rgba(15,23,42,0.04);padding:12px;border-radius:12px;font-size:11px;line-height:1.5;overflow:auto;white-space:pre-wrap;margin:0 0 18px;">name,category,location,address,latitude,longitude,opening_time,closing_time,description,status
Basilica del Sto. Niño,Church,Cebu City,Osmeña Blvd,10.29410000,123.90340000,08:00,17:00,"Historic church","Published"</pre>

                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary" style="flex:1">Upload bulk import</button>
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
    <div class="modal-dialog">
        <div class="modal-content dm-modal">

            <div class="dm-modal-head">
                <div class="modal-title" style="margin:0">
                    <i class="bi bi-geo-alt-fill" style="color:var(--primary)"></i>
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
                    <div class="field" style="flex:1 1 100%">
                        <label class="dm-label" for="f-name">Destination Name</label>
                        <input id="f-name" type="text" name="name" class="giya-input" required maxlength="200"
                               value="{{ old('name') }}"
                               placeholder="Archdiocesan Shrine of the Most Sacred Heart of Jesus">
                        @error('name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="dm-row">
                    <div class="field" style="flex:1 1 180px">
                        <label class="dm-label" for="f-category">Category</label>
                        <select id="f-category" name="category" class="giya-input" required>
                            @foreach ($categories as $cat)
                                @continue ($cat === 'All')
                                <option value="{{ $cat }}" @selected(old('category') === $cat)>{{ $cat }}</option>
                            @endforeach
                        </select>
                        @error('category')<span class="field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="field" style="flex:1 1 180px">
                        <label class="dm-label" for="f-status">Status</label>
                        <select id="f-status" name="status" class="giya-input">
                            <option value="Published">Published</option>
                            <option value="Draft" @selected(old('status') === 'Draft')>Draft</option>
                        </select>
                    </div>
                </div>

                <div class="dm-row">
                    <div class="field" style="flex:1 1 100%">
                        <label class="dm-label" for="f-location">Location (City / Municipality)</label>
                        <input id="f-location" type="text" name="location" class="giya-input" required
                               value="{{ old('location') }}" placeholder="Cebu City">
                        @error('location')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="field">
                    <label class="dm-label" for="f-address">Exact Address (Optional)</label>
                    <input id="f-address" type="text" name="address" class="giya-input" maxlength="255"
                           value="{{ old('address') }}"
                           placeholder="242 Dionisio Jakosalem St, Cebu City">
                </div>

                {{-- Coordinates come from the map click; shown so they can be corrected. --}}
                <div class="dm-row dm-coords">
                    <div class="field" style="flex:1 1 160px">
                        <label class="dm-label" for="f-lat">Latitude</label>
                        <input id="f-lat" type="number" step="0.00000001" name="latitude"
                               class="giya-input" value="{{ old('latitude') }}">
                        @error('latitude')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field" style="flex:1 1 160px">
                        <label class="dm-label" for="f-lng">Longitude</label>
                        <input id="f-lng" type="number" step="0.00000001" name="longitude"
                               class="giya-input" value="{{ old('longitude') }}">
                        @error('longitude')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field" style="flex:0 0 auto;align-self:flex-end">
                        <button type="button" class="btn btn-outline btn-sm" id="dmRepin">
                            <i class="bi bi-geo-alt-fill"></i> Move pin
                        </button>
                    </div>
                </div>

                <div class="dm-row">
                    <div class="field" style="flex:1 1 160px">
                        <label class="dm-label" for="f-open">Opens</label>
                        <input id="f-open" type="time" name="opening_time" class="giya-input"
                               value="{{ old('opening_time') }}">
                    </div>
                    <div class="field" style="flex:1 1 160px">
                        <label class="dm-label" for="f-close">Closes</label>
                        <input id="f-close" type="time" name="closing_time" class="giya-input"
                               value="{{ old('closing_time') }}">
                    </div>
                </div>

                <div class="field">
                    <label class="dm-label" for="f-desc">Historical Background</label>
                    <textarea id="f-desc" name="description" class="giya-input" rows="4"
                              placeholder="Historical and religious significance">{{ old('description') }}</textarea>
                </div>

                <div class="field">
                    <label class="dm-label">Destination Photograph</label>

                    <label class="dm-drop" id="dropZone">
                        <input type="file" name="photo" id="f-photo"
                               accept="image/jpeg,image/png,image/webp" hidden>
                        <img id="dropPreview" alt="" style="display:none">
                        <span class="dm-drop-inner" id="dropPrompt">
                            <span class="dm-drop-circle"><i class="bi bi-camera-fill"></i></span>
                            <span class="dm-drop-text">Click to upload, or drag an image here</span>
                            <span class="dm-drop-sub">JPG or PNG</span>
                        </span>
                    </label>

                    <input type="text" name="caption" class="giya-input" style="margin-top:8px"
                           placeholder="Photo caption (optional)" maxlength="255">
                    @error('photo')<span class="field-error">{{ $message }}</span>@enderror
                    <p class="dm-map-note" style="margin-top:6px">
                        <i class="bi bi-info-circle"></i>
                        Saved to public/images/churches, named after the destination.
                    </p>
                </div>

                <div class="dm-modal-foot">
                    <button type="submit" class="btn btn-primary" style="flex:1" id="btnSave">
                        Save Destination
                    </button>
                    <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>

                    {{-- Only meaningful once a destination exists, so it is
                         hidden while adding one. --}}
                    <button type="button" class="btn btn-danger-solid dm-delete"
                            id="btnDelete" style="display:none">
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
<script src="{{ asset('assets/js/giya-leaflet.js') }}?v={{ filemtime(public_path('assets/js/giya-leaflet.js')) }}"></script>
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

        /* A click on empty map means "put a destination here". */
        onPin: function (lat, lng) {
            picking = false;
            hint.textContent = 'Click the map to place a destination';
            openModal(null, lat, lng);
        },

        /* A click on a pin means "edit this one". */
        onChurchClick: function (c) { openModal(c.id); }
    });

    /* ---------------------------------------------------------- modal ---- */

    function field(id) { return document.getElementById(id); }

    function clearForm() {
        field('destForm').reset();
        field('churchId').value = '';
        field('dropPreview').style.display = 'none';
        field('dropPrompt').style.display = '';
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
            field('f-address').value   = c.address || '';
            field('f-lat').value       = c.lat || '';
            field('f-lng').value       = c.lng || '';
            field('f-open').value      = c.open || '';
            field('f-close').value     = c.close || '';
            field('f-desc').value      = c.description || '';
            field('f-status').value    = c.active ? 'Published' : 'Draft';

            if (c.image) {
                const img = field('dropPreview');
                img.src = c.image;
                img.style.display = 'block';
                field('dropPrompt').style.display = 'none';
            }

            field('destModalTitle').textContent = 'Edit Destination';
            field('btnSave').textContent = 'Save Changes';

            if (c.lat && c.lng) picker.place(c.lat, c.lng, true);
        } else if (lat !== null && lat !== undefined) {
            field('f-lat').value = lat.toFixed(8);
            field('f-lng').value = lng.toFixed(8);
        }

        // Delete only applies to something that already exists.
        const del = field('btnDelete');
        del.style.display = churchId ? '' : 'none';
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

    /* Move pin: close the modal, take the next map click, reopen. */
    document.getElementById('dmRepin').addEventListener('click', function () {
        GiyaUI.Modal.close('destModal');
        picking = true;
        hint.textContent = 'Click the new position for this destination';
        hint.classList.add('is-active');
        if (!expanded) expand();
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
    document.querySelectorAll('[data-edit]').forEach(function (b) {
        b.addEventListener('click', function () { openModal(Number(this.dataset.edit)); });
    });

    /* ---- photograph ---- */
    const zone  = document.getElementById('dropZone');
    const input = document.getElementById('f-photo');

    function preview(file) {
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function (e) {
            const img = field('dropPreview');
            img.src = e.target.result;
            img.style.display = 'block';
            field('dropPrompt').style.display = 'none';
        };
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
})();
</script>
@endpush
