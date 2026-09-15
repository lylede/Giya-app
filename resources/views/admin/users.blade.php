@extends('layouts.admin')
@section('title', 'Users')
@section('page-title', 'User Management')
@section('page-subtitle', 'View and manage all registered users of Giya.')

@section('content')

{{-- ══════════════ Stat cards ══════════════ --}}
<div class="stat-row">
    <x-stat-tile icon="giya-pilgrim" label="Total Users" :value="$summary['total']" tone="primary">
        <x-slot:sub>All registered users</x-slot:sub>
    </x-stat-tile>

    <x-stat-tile icon="giya-route" label="Active Users" :value="$summary['active']" tone="green">
        <x-slot:sub>
            <span class="stat-delta is-up">{{ $summary['active_pct'] }}%</span> of total users
        </x-slot:sub>
    </x-stat-tile>

    <x-stat-tile icon="giya-star" label="New This Week" :value="$summary['new_week']" tone="blue">
        <x-slot:sub>
            <span @class(['stat-delta', 'is-up' => $summary['new_delta'] >= 0, 'is-down' => $summary['new_delta'] < 0])>
                {{ $summary['new_delta'] >= 0 ? '+' : '' }}{{ $summary['new_delta'] }}
            </span> vs last week
        </x-slot:sub>
    </x-stat-tile>

    <x-stat-tile icon="gem" label="Premium Users" :value="$summary['premium']" tone="gold">
        <x-slot:sub>
            <span class="stat-delta is-gold">{{ $summary['premium_pct'] }}%</span> of total users
        </x-slot:sub>
    </x-stat-tile>

    <x-stat-tile icon="x-circle" label="Suspended Users" :value="$summary['suspended']" tone="primary">
        <x-slot:sub>
            <span class="stat-delta is-down">{{ $summary['suspended_pct'] }}%</span> of total users
        </x-slot:sub>
    </x-stat-tile>
</div>

{{-- ══════════════ Filters ══════════════ --}}
<form method="GET" id="userFilters" class="um-filters">
    <div class="field is-wide">
        <label class="dm-label" for="uq">Search Users</label>
        <div class="sm-search">
            <i class="bi bi-search"></i>
            <input id="uq" type="search" name="search" value="{{ request('search') }}"
                   placeholder="Search users by name or email...">
        </div>
    </div>

    <div class="field">
        <label class="dm-label" for="ustatus">Status</label>
        <select id="ustatus" name="status" class="giya-input" onchange="this.form.submit()">
            <option value="">All Status</option>
            @foreach (\App\Models\User::STATUSES as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>

    <div class="field is-wide">
        <label class="dm-label">Date Range</label>
        <div class="field-dates">
            <input type="date" name="from" value="{{ request('from') }}" class="giya-input"
                   aria-label="Joined from" onchange="this.form.submit()">
            <input type="date" name="to" value="{{ request('to') }}" class="giya-input"
                   aria-label="Joined to" onchange="this.form.submit()">
        </div>
    </div>

    <div class="adm-actions">
        <a href="{{ route('admin.users') }}" class="btn btn-outline">
            <i class="bi bi-arrow-clockwise"></i> Reset Filter
        </a>
        <a href="{{ route('admin.users.export', request()->query()) }}" class="btn btn-primary">
            <i class="bi bi-download"></i> Export User
        </a>
    </div>
</form>

{{-- ══════════════ Table ══════════════ --}}
<div class="card adm-scroller mt-3">
    <div>
        <table class="giya-table sm-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>User</th>
                    <th>Email</th>
                    <th>Joined</th>
                    <th>Saved Destinations</th>
                    <th>Itineraries Created</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $i => $u)
                    <tr>
                        <td>{{ $users->firstItem() + $i }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="nav-avatar cell-avatar">
                                    @if ($u->avatarPath())
                                        <img src="{{ $u->avatarPath() }}" alt="{{ $u->name }}">
                                    @else
                                        {{ $u->initials() }}
                                    @endif
                                </span>
                                <span class="cell-strong">{{ $u->name }}</span>
                                @if ($u->isAdmin())
                                    <span class="badge badge-primary">Admin</span>
                                @endif
                            </div>
                        </td>
                        <td class="cell-muted">{{ $u->email }}</td>
                        <td>{{ $u->created_at?->format('F j, Y') ?? '-' }}</td>
                        <td>{{ $u->favorites_count }}</td>
                        <td>{{ $u->itineraries_count }}</td>
                        <td>
                            {{-- $premium is a user id => expiry map built in one
                                 query; $u->is_premium would be a query per row. --}}
                            @isset ($premium[$u->id])
                                <span class="badge badge-amber"
                                      title="Premium until {{ $premium[$u->id]->format('F j, Y') }}">
                                    <i class="bi bi-gem"></i> Premium
                                </span>
                                <div class="cell-note">
                                    until {{ $premium[$u->id]->format('M j, Y') }}
                                </div>
                            @else
                                <span class="badge badge-brown">Free</span>
                            @endisset
                        </td>
                        <td>
                            <span @class(['badge',
                                'badge-published'  => $u->status === 'Active',
                                'badge-brown'      => $u->status === 'Inactive',
                                'badge-suspended'  => $u->status === 'Suspended'])>
                                {{ $u->status }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button type="button" class="icon-btn" title="Edit"
                                        data-user="{{ json_encode([
                                        'id' => $u->id,
                                        'name' => $u->name,
                                        'email' => $u->email,
                                        'role' => $u->role,
                                        'status' => $u->status,
                                        'action' => route('admin.users.update', $u),
                                    ]) }}">
                                    <i class="bi bi-pencil-square"></i>
                                </button>

                                @if ($u->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.users.destroy', $u) }}"
                                          data-confirm-title="Delete {{ $u->name }}?"
                                          data-confirm="Their itineraries, visit history, reviews and saved favourites are deleted with the account. Suspend instead if you may want it back."
                                          data-confirm-ok="Delete account">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="icon-btn is-danger" title="Delete">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="is-flush">
                        <x-empty-state icon="giya-pilgrim" title="No users match"
                                       desc="Adjust the search or filters above." />
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="sm-foot is-inset">
        <span>
            Showing {{ $users->firstItem() ?? 0 }} to {{ $users->lastItem() ?? 0 }}
            of {{ $users->total() }} user{{ $users->total() === 1 ? '' : 's' }}
        </span>

        <div class="d-flex align-items-center gap-3">
            <x-pagination :paginator="$users" />

            <form method="GET" class="d-flex align-items-center gap-2">
                @foreach (request()->except(['per_page', 'page']) as $k => $v)
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endforeach
                <label for="uPer" class="per-page-label">Rows per page</label>
                <select id="uPer" name="per_page" class="giya-input per-page-select"
                        onchange="this.form.submit()">
                    @foreach ([5, 10, 25, 50] as $n)
                        <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════ Edit modal ══════════════ --}}
<div class="modal" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content dm-form-modal">
            <div class="modal-title">
                <i class="bi bi-giya-pilgrim"></i> Edit User
            </div>

            <form method="POST" id="userForm">
                @csrf @method('PATCH')

                <div class="field">
                    <label class="dm-label" for="u-name">Full Name</label>
                    <input id="u-name" type="text" name="name" class="giya-input" required maxlength="100">
                    @error('name')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="field">
                    <label class="dm-label" for="u-email">Email Address</label>
                    <input id="u-email" type="email" name="email" class="giya-input" required maxlength="150">
                    @error('email')<span class="field-error">{{ $message }}</span>@enderror
                </div>

                <div class="dm-row">
                    <div class="field dm-col">
                        <label class="dm-label" for="u-role">Role</label>
                        <select id="u-role" name="role" class="giya-input">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="field dm-col">
                        <label class="dm-label" for="u-status">Status</label>
                        <select id="u-status" name="status" class="giya-input">
                            @foreach (\App\Models\User::STATUSES as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <p class="dm-hint">
                    A suspended account keeps all its data but cannot sign in.
                    Inactive is a label only - it does not block access.
                </p>

                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary is-grow">Save Changes</button>
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
    document.querySelectorAll('[data-user]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const u = JSON.parse(this.dataset.user);

            document.getElementById('userForm').action = u.action;
            document.getElementById('u-name').value    = u.name;
            document.getElementById('u-email').value   = u.email;
            document.getElementById('u-role').value    = u.role;
            document.getElementById('u-status').value  = u.status;

            GiyaUI.Modal.open('userModal');
        });
    });

    document.getElementById('uq').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); document.getElementById('userFilters').submit(); }
    });

    @if ($errors->any())
        GiyaUI.Modal.open('userModal');
    @endif
})();
</script>
@endpush
