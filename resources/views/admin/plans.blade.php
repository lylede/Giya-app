@extends('layouts.admin')
@section('title', 'Plans & prices')
@section('page-title', 'Transaction Management')
@section('page-subtitle', 'The Premium plans devotees can buy, and what they cost')

@section('content')

@include('admin.partials.money-tabs', ['tab' => 'plans'])

<div class="card adm-panel">
    <div class="pl-head">
        <div>
            <h2 class="dm-panel-title">Plans &amp; prices</h2>
            <p class="pl-sub">A new price applies to payments started after you save it. Payments already paid or in progress keep their own amount.</p>
        </div>
        <button type="button" class="btn btn-primary" id="plAdd"><i class="bi bi-plus-lg"></i> Add plan</button>
    </div>

    @if ($plans->isEmpty())
        <x-empty-state icon="cash-stack" title="No plans yet" desc="Add a plan to offer Premium on the upgrade page." />
    @else
        <div class="adm-scroller">
            <div>
                <table class="giya-table">
                    <thead>
                        <tr><th>Plan</th><th>Price</th><th>Lasts</th><th>On the upgrade page</th><th>Payments</th><th class="pl-actions-head">Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($plans as $plan)
                            <tr @class(['is-muted' => ! $plan->is_active])>
                                <td>
                                    <div class="cell-strong">{{ $plan->name }}</div>
                                    @if ($plan->description)<div class="cell-small cell-muted">{{ $plan->description }}</div>@endif
                                </td>
                                <td class="cell-amount">₱{{ number_format((float) $plan->price, 2) }}</td>
                                <td class="cell-muted">{{ $plan->duration_days }} {{ $plan->duration_days === 1 ? 'day' : 'days' }}</td>
                                <td>
                                    <span @class(['badge', 'badge-green' => $plan->is_active, 'badge-brown' => ! $plan->is_active])>
                                        {{ $plan->is_active ? 'Offered' : 'Hidden' }}
                                    </span>
                                </td>
                                <td class="cell-small">
                                    {{ $plan->paid_count }} paid
                                    @if ($plan->pending_count)<span class="cell-muted"> · {{ $plan->pending_count }} in progress</span>@endif
                                </td>
                                <td>
                                    <div class="d-flex gap-1 justify-content-end">
                                        @php
                                            $payload = [
                                                'id' => $plan->id, 'name' => $plan->name, 'description' => $plan->description,
                                                'price' => (float) $plan->price, 'duration_days' => $plan->duration_days,
                                                'is_active' => $plan->is_active, 'pending' => $plan->pending_count,
                                                'url' => route('admin.plans.update', $plan),
                                            ];
                                        @endphp
                                        <button type="button" class="icon-btn" title="Edit" aria-label="Edit {{ $plan->name }}"
                                                data-plan="{{ json_encode($payload) }}">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <form method="POST" action="{{ route('admin.plans.toggle', $plan) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="icon-btn" title="{{ $plan->is_active ? 'Hide from the upgrade page' : 'Offer on the upgrade page' }}">
                                                <i class="bi bi-{{ $plan->is_active ? 'eye-slash' : 'eye' }}"></i>
                                            </button>
                                        </form>
                                        @if ($plan->all_count === 0)
                                            <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}"
                                                  data-confirm-title="Delete {{ $plan->name }}?"
                                                  data-confirm="Nobody has paid for it yet, so nothing else changes."
                                                  data-confirm-ok="Delete plan">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="icon-btn is-danger" title="Delete"><i class="bi bi-trash3"></i></button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <p class="pl-foot">A plan with payments cannot be deleted, so those payments always show what was bought. Hide it instead: devotees who paid keep their days.</p>
    @endif
</div>

{{-- Add or edit a plan: the pop-up in the middle. --}}
<div class="modal" id="planModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content dm-form-modal">
            <div class="modal-title"><i class="bi bi-cash-stack"></i> <span id="plTitle">Add plan</span></div>

            <form method="POST" id="plForm" action="{{ route('admin.plans.store') }}" data-store="{{ route('admin.plans.store') }}">
                @csrf
                <input type="hidden" name="_method" id="plMethod" value="POST">
                <input type="hidden" name="plan_id" id="plId" value="{{ old('plan_id') }}">

                <div class="dm-row">
                    <div class="field is-full">
                        <label class="dm-label" for="plName">Name</label>
                        <input id="plName" name="name" class="giya-input" maxlength="60" required value="{{ old('name') }}" placeholder="e.g. Pilgrim Monthly">
                        @error('name')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="dm-row">
                    <div class="field">
                        <label class="dm-label" for="plPrice">Price (₱)</label>
                        <input id="plPrice" name="price" type="number" class="giya-input" required
                               min="{{ $min }}" max="{{ $max }}" step="0.01" inputmode="decimal" value="{{ old('price') }}">
                        @error('price')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label class="dm-label" for="plDays">Lasts (days)</label>
                        <input id="plDays" name="duration_days" type="number" class="giya-input" required
                               min="1" max="{{ $maxDays }}" step="1" value="{{ old('duration_days') }}">
                        @error('duration_days')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="dm-row">
                    <div class="field is-full">
                        <label class="dm-label" for="plDesc">What it includes <span class="cell-muted">(shown on the upgrade page)</span></label>
                        <textarea id="plDesc" name="description" class="giya-input" rows="2" maxlength="300">{{ old('description') }}</textarea>
                        @error('description')<span class="field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <label class="pl-check">
                    <input type="checkbox" name="is_active" id="plActive" value="1" @checked(old('is_active', '1'))>
                    Offer it on the upgrade page
                </label>

                {{-- What saving will do, in words, before it is done. --}}
                <p class="pl-change" id="plChange" hidden></p>

                <div class="modal-actions">
                    <button type="button" class="btn btn-outline is-grow" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-primary is-grow" id="plSave">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('plForm');
    const el = (id) => document.getElementById(id);
    const peso = (n) => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let editing = null;

    function open(plan) {
        editing = plan;
        el('plTitle').textContent = plan ? 'Edit ' + plan.name : 'Add plan';
        form.action = plan ? plan.url : form.dataset.store;
        el('plMethod').value = plan ? 'PUT' : 'POST';
        el('plId').value = plan ? plan.id : '';
        el('plName').value = plan ? plan.name : '';
        el('plPrice').value = plan ? plan.price.toFixed(2) : '';
        el('plDays').value = plan ? plan.duration_days : 30;
        el('plDesc').value = plan ? (plan.description || '') : '';
        el('plActive').checked = plan ? plan.is_active : true;
        el('plSave').textContent = plan ? 'Save' : 'Add plan';
        describe();
        GiyaUI.Modal.open('planModal');
        setTimeout(() => el(plan ? 'plPrice' : 'plName').focus(), 60);
    }

    // Spell out what a new price means before anyone saves it.
    function describe() {
        const box = el('plChange');
        const price = parseFloat(el('plPrice').value);
        if (!editing || !isFinite(price) || price.toFixed(2) === editing.price.toFixed(2)) { box.hidden = true; return; }
        let text = peso(editing.price) + ' → ' + peso(price) + '. Applies to new payments only.';
        if (editing.pending) {
            text += ' ' + editing.pending + (editing.pending === 1 ? ' payment in progress keeps ' : ' payments in progress keep ') + peso(editing.price) + '.';
        }
        box.textContent = text;
        box.hidden = false;
    }

    el('plPrice').addEventListener('input', describe);
    el('plAdd').addEventListener('click', () => open(null));
    document.addEventListener('click', (e) => {
        const b = e.target.closest('[data-plan]');
        if (b) open(JSON.parse(b.dataset.plan));
    });

    @if ($errors->any())
        // Came back with a mistake: reopen the same pop-up with what was typed.
        @php $failed = old('plan_id') ? $plans->firstWhere('id', (int) old('plan_id')) : null; @endphp
        @php $failedPayload = $failed ? ['id' => $failed->id, 'name' => $failed->name, 'price' => (float) $failed->price, 'pending' => $failed->pending_count, 'url' => route('admin.plans.update', $failed)] : null; @endphp
        editing = {!! json_encode($failedPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
        el('plTitle').textContent = editing ? 'Edit ' + editing.name : 'Add plan';
        if (editing) { form.action = editing.url; el('plMethod').value = 'PUT'; }
        describe();
        GiyaUI.Modal.open('planModal');
    @endif
})();
</script>
@endpush
