@extends('layouts.admin')

@section('title', 'Destinations')
@section('page-title', 'Destination Management')
@section('page-subtitle', $churches->total() . ' total destinations')

@push('head')
    <link rel="stylesheet" href="{{ asset('assets/css/leaflet.css') }}?v={{ filemtime(public_path('assets/css/leaflet.css')) }}">
@endpush

@section('content')

{{-- ═══════════════ ADD / EDIT PANEL (inline, per the design) ═══════════════ --}}
<section class="card dm-panel" id="formPanel">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <h2 class="dm-panel-title" id="formTitle">Add New Destinations</h2>
        <button type="button" class="btn btn-ghost btn-sm" id="btnResetForm" style="display:none">
            <i class="bi bi-x-lg"></i> Cancel edit
        </button>
    </div>

    <form method="POST" action="{{ route('admin.destinations.store') }}" enctype="multipart/form-data" id="destForm">
        @csrf
        <input type="hidden" name="church_id" id="churchId">

        {{-- Row 1 --}}
        <div class="dm-row">
            <div class="field" style="flex:1 1 416px">
                <label class="dm-label" for="f-name">Destination Name</label>
                <input id="f-name" type="text" name="name" class="giya-input" required maxlength="200"
                       value="{{ old('name') }}"
                       placeholder="Archdiocesan Shrine of the Most Sacred Heart of Jesus">
                @error('name')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="field" style="flex:0 1 250px">
                <label class="dm-label" for="f-category">Category</label>
                <select id="f-category" name="category" class="giya-input" required>
                    @foreach ($categories as $cat)
                        @continue ($cat === 'All')
                        <option value="{{ $cat }}" @selected(old('category') === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
                @error('category')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="field" style="flex:0 1 250px">
                <label class="dm-label" for="f-status">Status</label>
                <select id="f-status" name="status" class="giya-input">
                    <option value="Published">Published</option>
                    <option value="Draft" @selected(old('status') === 'Draft')>Draft</option>
                </select>
            </div>
        </div>

        {{-- Row 2 --}}
        <div class="dm-row">
            <div class="field" style="flex:0 1 416px">
                <label class="dm-label" for="f-location">Location (City / Municipality)</label>
                <input id="f-location" type="text" name="location" class="giya-input" required
                       value="{{ old('location') }}" placeholder="Cebu City">
                @error('location')<span class="field-error">{{ $message }}</span>@enderror
            </div>
        </div>

        {{-- Row 3 --}}
        <div class="field">
            <label class="dm-label" for="f-address">Exact Address (Optional)</label>
            <input id="f-address" type="text" name="address" class="giya-input" maxlength="255"
                   value="{{ old('address') }}"
                   placeholder="242 Dionisio Jakosalem St, Cebu City, 6000 Cebu, Philippines">
        </div>

        {{-- Map picker --}}
        <div class="field">
            <label class="dm-label">Map Location</label>

            <div class="dm-map-box">
                <div class="dm-map-side">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <img src="{{ asset('assets/img/giya-logo.png') }}" alt="" width="40" height="40"
                             onerror="this.style.display='none'">
                        <span class="dm-map-heading">Choose on Map</span>
                    </div>
                    <p class="dm-map-hint">Pin the exact location</p>

                    <button type="button" class="btn btn-primary dm-choose-btn" id="btnChooseMap">
                        <i class="bi bi-geo-alt-fill"></i> Choose on Map
                    </button>

                    <p class="dm-map-note">
                        <i class="bi bi-info-circle"></i>
                        Latitude and Longitude will be saved automatically after selecting on the map.
                    </p>
                </div>

                <div class="dm-map-canvas" id="adminPickMap"></div>
            </div>
        </div>

        {{-- Coordinates --}}
        <div class="dm-row">
            <div class="field" style="flex:0 1 250px">
                <label class="dm-label" for="f-lat">Latitude</label>
                <input id="f-lat" type="number" step="0.00000001" name="latitude" class="giya-input"
                       value="{{ old('latitude') }}" placeholder="10.3089">
                @error('latitude')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field" style="flex:0 1 250px">
                <label class="dm-label" for="f-lng">Longitude</label>
                <input id="f-lng" type="number" step="0.00000001" name="longitude" class="giya-input"
                       value="{{ old('longitude') }}" placeholder="123.8990">
                @error('longitude')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field" style="flex:0 1 180px">
                <label class="dm-label" for="f-open">Opens</label>
                <input id="f-open" type="time" name="opening_time" class="giya-input" value="{{ old('opening_time') }}">
            </div>
            <div class="field" style="flex:0 1 180px">
                <label class="dm-label" for="f-close">Closes</label>
                <input id="f-close" type="time" name="closing_time" class="giya-input" value="{{ old('closing_time') }}">
            </div>
        </div>

        {{-- Background + image --}}
        <div class="dm-row">
            <div class="field" style="flex:1 1 479px">
                <label class="dm-label" for="f-desc">Historical Background</label>
                <textarea id="f-desc" name="description" class="giya-input" rows="7"
                          placeholder="Enter historical and religious significance...">{{ old('description') }}</textarea>
            </div>

            <div class="field" style="flex:1 1 478px">
                <label class="dm-label">Destination Image</label>

                <label class="dm-drop" id="dropZone">
                    <input type="file" name="photo" id="f-photo" accept="image/jpeg,image/png,image/webp" hidden>
                    <img id="dropPreview" alt="" style="display:none">
                    <span class="dm-drop-inner" id="dropPrompt">
                        <span class="dm-drop-circle"><i class="bi bi-camera-fill"></i></span>
                        <span class="dm-drop-text">Click to upload image or drag and drop</span>
                        <span class="dm-drop-sub">JPG, PNG</span>
                    </span>
                </label>
                <input type="text" name="caption" class="giya-input" style="margin-top:8px"
                       placeholder="Photo caption (optional)" maxlength="255">
                @error('photo')<span class="field-error">{{ $message }}</span>@enderror
                <p class="dm-map-note" style="margin-top:6px">
                    <i class="bi bi-info-circle"></i> This photo becomes the pin shown on the map.
                </p>
            </div>
        </div>

        <div class="dm-actions">
            <button type="submit" class="btn btn-primary dm-btn-wide" id="btnSave">Save Destination</button>
            <button type="button" class="btn btn-outline dm-btn-wide" id="btnCancel">Cancel</button>
        </div>
    </form>
</section>

{{-- ═══════════════════════════ FILTERS ═══════════════════════════ --}}
<form method="GET" class="dm-filters">
    <div class="field" style="flex:1 1 380px">
        <label class="dm-label" for="q">Search Destination</label>
        <input id="q" type="search" name="search" value="{{ $search }}" class="giya-input"
               placeholder="Search destinations by name or location...">
    </div>

    <div class="field" style="flex:0 1 194px">
        <label class="dm-label" for="fcat">Category</label>
        <select id="fcat" name="category" class="giya-input" onchange="this.form.submit()">
            @foreach ($categories as $cat)
                <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat === 'All' ? 'All Categories' : $cat }}</option>
            @endforeach
        </select>
    </div>

    <div class="field" style="flex:0 1 194px">
        <label class="dm-label" for="fstatus">Status</label>
        <select id="fstatus" name="status" class="giya-input" onchange="this.form.submit()">
            <option value="">All Status</option>
            <option value="active" @selected(request('status') === 'active')>Published</option>
            <option value="hidden" @selected(request('status') === 'hidden')>Draft</option>
        </select>
    </div>

    <div class="d-flex gap-2 align-items-end" style="flex:0 0 auto">
        <a href="{{ route('admin.destinations') }}" class="btn btn-outline">
            <i class="bi bi-arrow-clockwise"></i> Reset Filter
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-search"></i> Apply
        </button>
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
                            <span style="color:var(--text-muted)">&mdash;</span>
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
                                  data-confirm-delete="{{ $church->name }}">
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

<div style="margin-top:14px">{{ $churches->withQueryString()->links() }}</div>

@endsection

@push('scripts')
<script src="{{ asset('assets/js/leaflet.js') }}?v={{ filemtime(public_path('assets/js/leaflet.js')) }}"></script>
<script src="{{ asset('assets/js/giya-leaflet.js') }}?v={{ filemtime(public_path('assets/js/giya-leaflet.js')) }}"></script>
<script>
(function () {
    const markers = @json($markers);
    const rows    = @json($rows);

    /* The map is inline and visible on load, so it can be built immediately. */
    const picker = GiyaLeaflet.picker({
        element: 'adminPickMap',
        churches: markers,
        latInput: '#f-lat',
        lngInput: '#f-lng',
        onChurchClick: function (c) { loadIntoForm(c.id); }
    });

    /* ---- edit an existing destination ---- */
    function loadIntoForm(id) {
        const c = rows.filter(function (r) { return r.id === id; })[0];
        if (!c) return;

        document.getElementById('churchId').value    = c.id;
        document.getElementById('f-name').value      = c.name;
        document.getElementById('f-category').value  = c.category;
        document.getElementById('f-location').value  = c.location || '';
        document.getElementById('f-address').value   = c.address || '';
        document.getElementById('f-lat').value       = c.lat || '';
        document.getElementById('f-lng').value       = c.lng || '';
        document.getElementById('f-open').value      = c.open || '';
        document.getElementById('f-close').value     = c.close || '';
        document.getElementById('f-desc').value      = c.description || '';
        document.getElementById('f-status').value    = c.active ? 'Published' : 'Draft';

        if (c.image) {
            const img = document.getElementById('dropPreview');
            img.src = c.image;
            img.style.display = 'block';
            document.getElementById('dropPrompt').style.display = 'none';
        }

        document.getElementById('formTitle').textContent = 'Edit Destination';
        document.getElementById('btnSave').textContent   = 'Save Changes';
        document.getElementById('btnResetForm').style.display = '';

        if (c.lat && c.lng) { picker.place(c.lat, c.lng, true); picker.focus(c.lat, c.lng); }

        document.getElementById('formPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function resetForm() {
        document.getElementById('destForm').reset();
        document.getElementById('churchId').value = '';
        document.getElementById('formTitle').textContent = 'Add New Destinations';
        document.getElementById('btnSave').textContent   = 'Save Destination';
        document.getElementById('btnResetForm').style.display = 'none';
        document.getElementById('dropPreview').style.display = 'none';
        document.getElementById('dropPrompt').style.display = '';
        picker.clear();
    }

    document.getElementById('btnCancel').addEventListener('click', resetForm);
    document.getElementById('btnResetForm').addEventListener('click', resetForm);

    document.querySelectorAll('[data-edit]').forEach(function (b) {
        b.addEventListener('click', function () { loadIntoForm(Number(this.dataset.edit)); });
    });

    /* ---- "Choose on Map" scrolls the picker into view ---- */
    document.getElementById('btnChooseMap').addEventListener('click', function () {
        document.getElementById('adminPickMap').scrollIntoView({ behavior: 'smooth', block: 'center' });
        picker.map.invalidateSize();
    });

    /* ---- delete confirmation ---- */
    document.querySelectorAll('[data-confirm-delete]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const name = form.dataset.confirmDelete;
            const ok = window.confirm(
                'Delete "' + name + '" permanently?\n\n' +
                'This also removes its photos, and any visit records, reviews, ' +
                'saved favourites and itinerary stops that reference it.\n\n' +
                'This cannot be undone. Use the eye icon to hide it instead.'
            );
            if (!ok) e.preventDefault();
        });
    });

    /* ---- image drop zone ---- */
    const zone = document.getElementById('dropZone');
    const input = document.getElementById('f-photo');

    function preview(file) {
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function (e) {
            const img = document.getElementById('dropPreview');
            img.src = e.target.result;
            img.style.display = 'block';
            document.getElementById('dropPrompt').style.display = 'none';
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
})();
</script>
@endpush
