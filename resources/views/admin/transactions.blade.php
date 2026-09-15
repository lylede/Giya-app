@extends('layouts.admin')
@section('title', 'Transactions')
@section('page-title', 'Transaction Management')
@section('page-subtitle', 'Premium upgrades and itinerary access payments')

@section('content')

<div class="stat-row">
    @foreach ([
        ['giya-payment',      'Total Revenue', '₱' . number_format($summary['revenue'], 2), 'gold'],
        ['check-circle-fill', 'Paid',          number_format($summary['paid']),             'green'],
        ['hourglass-split',   'Pending',       number_format($summary['pending']),          'blue'],
    ] as [$icon, $label, $value, $tone])
        <x-stat-tile :icon="$icon" :label="$label" :value="$value" :tone="$tone" />
    @endforeach
</div>

<div class="d-flex gap-2 mb-3 flex-wrap">
    @foreach (['All', 'Paid', 'Pending', 'Failed', 'Refunded'] as $s)
        <a href="{{ route('admin.transactions', ['status' => $s]) }}"
           @class(['btn', 'btn-sm', 'btn-primary' => $status === $s, 'btn-outline' => $status !== $s])>{{ $s }}</a>
    @endforeach
</div>

<div class="card adm-scroller">
    <div>
        <table class="giya-table">
            <thead>
                <tr><th>Reference</th><th>User</th><th>Plan</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
                @forelse ($transactions as $t)
                    <tr>
                        <td class="cell-ref">{{ $t->transaction_id }}</td>
                        <td class="cell-strong">{{ $t->user->name ?? 'Deleted user' }}</td>
                        <td class="cell-muted">{{ $t->plan }}</td>
                        <td class="cell-amount">₱{{ number_format($t->amount, 2) }}</td>
                        <td class="cell-small">{{ $t->method ?? '-' }}</td>
                        <td>
                            <span @class([
                                'badge',
                                'badge-green'  => $t->status === 'Paid',
                                'badge-amber'  => $t->status === 'Pending',
                                'badge-brown'  => in_array($t->status, ['Failed', 'Refunded']),
                            ])>{{ $t->status }}</span>
                        </td>
                        <td class="cell-muted">{{ $t->created_at?->format('M j, Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state icon="credit-card" title="No transactions yet" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3"><x-pagination :paginator="$transactions" /></div>
@endsection
