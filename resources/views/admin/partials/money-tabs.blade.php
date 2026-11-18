{{-- Transactions page: the payments, and the plans they pay for. --}}
<nav class="sm-tabs" aria-label="Transactions views">
    <a href="{{ route('admin.transactions') }}" @class(['sm-tab', 'is-on' => ($tab ?? 'payments') === 'payments'])>
        <i class="bi bi-list"></i> Payments
    </a>
    <a href="{{ route('admin.transactions', ['view' => 'plans']) }}" @class(['sm-tab', 'is-on' => ($tab ?? '') === 'plans'])>
        <i class="bi bi-cash-stack"></i> Plans &amp; prices
    </a>
</nav>
