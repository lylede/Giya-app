@extends('layouts.admin')
@section('title', 'Users')
@section('page-title', 'User Management')
@section('page-subtitle', 'View and manage all registered users of Giya.')

@section('content')

{{-- ══════════════ Stat cards ══════════════ --}}
<div class="um-stats">
    <div class="card um-stat">
        <span class="um-stat-icon" style="background:#E8E4F5;color:#5B4BA8"><i class="bi bi-people-fill"></i></span>
        <div>
            <span class="um-stat-label">Total User</span>
            <span class="um-stat-value">{{ number_format($summary['total']) }}</span>
            <span class="um-stat-sub">All registered users</span>
        </div>
    </div>

    <div class="card um-stat">
        <span class="um-stat-icon" style="background:#DCF2E3;color:#166534"><i class="bi bi-person-fill"></i></span>
        <div>
            <span class="um-stat-label">Active Users</span>
            <span class="um-stat-value">{{ number_format($summary['active']) }}</span>
            <span class="um-stat-sub">
                <strong style="color:#166534">{{ $summary['active_pct'] }}%</strong> of total users
            </span>
        </div>
    </div>

    <div class="card um-stat">
        <span class="um-stat-icon" style="background:#FBEED2;color:#B8860B"><i class="bi bi-stars"></i></span>
        <div>
            <span class="um-stat-label">New This Week</span>
            <span class="um-stat-value">{{ number_format($summary['new_week']) }}</span>
            <span class="um-stat-sub">
                <strong style="color:{{ $summary['new_delta'] >= 0 ? '#166534' : '#B3182F' }}">
                    {{ $summary['new_delta'] >= 0 ? '+' : '' }}{{ $summary['new_delta'] }}
                </strong> vs last week
            </span>
        </div>
    </div>

    <div class="card um-stat">
        <span class="um-stat-icon" style="background:#F7E2E2;color:#B3182F"><i class="bi bi-x-circle"></i></span>
        <div>
            <span class="um-stat-label">Suspended Users</span>
            <span class="um-stat-value">{{ number_format($summary['suspended']) }}</span>
            <span class="um-stat-sub">
                <strong style="color:#B3182F">{{ $summary['suspended_pct'] }}%</strong> of total users
            </span>
        </div>
    </div>
</div>

{{-- ══════════════ Filters ══════════════ --}}
<form method="GET" id="userFilters" class="um-filters">
    <div class="field" style="flex:1 1 460px">
        <label class="dm-label" for="uq">Search Users</label>
        <div class="sm-search" style="margin:0">
            <i class="bi bi-search"></i>
            <input id="uq" type="search" name="search" value="{{ request('search') }}"
                   placeholder="Search users by name or email...">
        </div>
    </div>

    <div class="field" style="flex:0 1 230px">
        <label class="dm-label" for="ustatus">Status</label>
        <select id="ustatus" name="status" class="giya-input" onchange="this.form.submit()">
            <option value="">All Status</option>
            @foreach (\App\Models\User::STATUSES as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>

    <div class="field" style="flex:0 1 380px">
        <label class="dm-label">Date Range</label>
        <div class="d-flex gap-2">
            <input type="date" name="from" value="{{ request('from') }}" class="giya-input"
                   aria-label="Joined from" onchange="this.form.submit()">
            <input type="date" name="to" value="{{ request('to') }}" class="giya-input"
                   aria-label="Joined to" onchange="this.form.submit()">
        </div>
    </div>

    <div class="d-flex gap-2 align-items-end" style="flex:0 0 auto">
        <a href="{{ route('admin.users') }}" class="btn btn-outline">
            <i class="bi bi-arrow-clockwise"></i> Reset Filter
        </a>
        <a href="{{ route('admin.users.export', request()->query()) }}" class="btn btn-primary">
            <i class="bi bi-download"></i> Export User
        </a>
    </div>
</form>

{{-- ══════════════ Table ══════════════ --}}
<div class="card" style="overflow:hidden;margin-top:18px">
    <div style="overflow-x:auto">
        <table class="giya-table sm-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>User</th>
                    <th>Email</th>
                    <th>Joined</th>
                    <th>Saved Destinations</th>
                    <th>Itineraries Created</th>
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
                                <span class="nav-avatar" style="width:36px;height:36px;font-size: 0.75rem;border-color:var(--primary)">
                                    @if ($u->avatarPath())
                                        <img src="{{ $u->avatarPath() }}" alt="{{ $u->name }}">
                                    @else
                                        {{ $u->initials() }}
                                    @endif
                                </span>
                                <span style="font-weight:700">{{ $u->name }}</span>
                                @if ($u->isAdmin())
                                    <span class="badge badge-primary">Admin</span>
                                @endif
                            </div>
                        </td>
                        <td style="color:var(--text-muted)">{{ $u->email }}</td>
                        <td>{{ $u->created_at?->format('F j, Y') ?? '—' }}</td>
                        <td>{{ $u->favorites_count }}</td>
                        <td>{{ $u->itineraries_count }}</td>
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
                               @php($payload = ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role, 'status' => $u->status, 'action' => route('admin.users.update', $u)])
                                <button type="button" class="icon-btn" title="Edit"
                                        data-user="{{ json_encode($payload) }}">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                @if ($u->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.users.destroy', $u) }}"
                                          onsubmit="return confirm('Delete {{ $u->name }}? Their itineraries, visit history, reviews and favourites go too. This cannot be undone.')">
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
                    <tr><td colspan="8" style="padding:0">
                        <x-empty-state icon="people-fill" title="No users match"
                                       desc="Adjust the search or filters above." />
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="sm-foot" style="padding:14px 18px">
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
                <label for="uPer" style="font-size: 0.8125rem;color:var(--text-muted)">Rows per page</label>
                <select id="uPer" name="per_page" class="giya-input" style="width:74px;padding:6px 8px"
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
        <div class="modal-content" style="border:none;border-radius:var(--radius-2xl);padding:28px">
            <div class="modal-title">
                <i class="bi bi-person-fill" style="color:var(--primary)"></i> Edit User
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
                    <div class="field" style="flex:1 1 180px">
                        <label class="dm-label" for="u-role">Role</label>
                        <select id="u-role" name="role" class="giya-input">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="field" style="flex:1 1 180px">
                        <label class="dm-label" for="u-status">Status</label>
                        <select id="u-status" name="status" class="giya-input">
                            @foreach (\App\Models\User::STATUSES as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <p style="font-size: 0.75rem;color:var(--text-muted);margin:0 0 14px">
                    A suspended account keeps all its data but cannot sign in.
                    Inactive is a label only — it does not block access.
                </p>

                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary" style="flex:1">Save Changes</button>
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
