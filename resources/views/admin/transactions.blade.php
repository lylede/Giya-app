@extends('layouts.admin')
@section('title', 'Transactions')
@section('page-title', 'Transaction Management')
@section('page-subtitle', 'Premium upgrades and itinerary access payments')

@section('content')

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px">
    @foreach ([
        ['cash-stack',        'Total Revenue', '₱' . number_format($summary['revenue'], 2)],
        ['check-circle-fill', 'Paid',          number_format($summary['paid'])],
        ['hourglass-split',   'Pending',       number_format($summary['pending'])],
    ] as [$icon, $label, $value])
        <div class="card card-body">
            <i class="bi bi-{{ $icon }}" style="font-size: 1.25rem;color:var(--gold)"></i>
            <div style="font-family:var(--font-display);font-size: 1.375rem;font-weight:700;margin-top:8px">{{ $value }}</div>
            <div style="font-size: 0.75rem;color:var(--text-muted)">{{ $label }}</div>
        </div>
    @endforeach
</div>

<div class="d-flex gap-2 mb-3 flex-wrap">
    @foreach (['All', 'Paid', 'Pending', 'Failed', 'Refunded'] as $s)
        <a href="{{ route('admin.transactions', ['status' => $s]) }}"
           @class(['btn', 'btn-sm', 'btn-primary' => $status === $s, 'btn-outline' => $status !== $s])>{{ $s }}</a>
    @endforeach
</div>

<div class="card" style="overflow:hidden">
    <div style="overflow-x:auto">
        <table class="giya-table">
            <thead>
                <tr><th>Reference</th><th>User</th><th>Plan</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
                @forelse ($transactions as $t)
                    <tr>
                        <td style="font-family:monospace;font-size: 0.75rem">{{ $t->transaction_id }}</td>
                        <td style="font-weight:600">{{ $t->user->name ?? 'Deleted user' }}</td>
                        <td style="font-size: 0.8125rem;color:var(--text-muted)">{{ $t->plan }}</td>
                        <td style="font-weight:700">₱{{ number_format($t->amount, 2) }}</td>
                        <td style="font-size: 0.8125rem">{{ $t->method ?? '-' }}</td>
                        <td>
                            <span @class([
                                'badge',
                                'badge-green'  => $t->status === 'Paid',
                                'badge-amber'  => $t->status === 'Pending',
                                'badge-brown'  => in_array($t->status, ['Failed', 'Refunded']),
                            ])>{{ $t->status }}</span>
                        </td>
                        <td style="font-size: 0.8125rem;color:var(--text-muted)">{{ $t->created_at?->format('M j, Y') }}</td>
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
