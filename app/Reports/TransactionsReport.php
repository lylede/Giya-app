<?php

namespace App\Reports;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TransactionsReport extends RecordReport
{
    public function title(): string
    {
        return 'Transaction Report';
    }

    public function columns(): array
    {
        return ['Transaction ID', 'User', 'Plan', 'Amount', 'Method', 'Status', 'Reference', 'Created At'];
    }

    protected function query(): Builder
    {
        return $this->inRange(Transaction::query(), 'created_at')
            ->with(['user', 'subscriptionPlan'])
            ->orderByDesc('created_at');
    }

    public function rows(): Collection
    {
        return $this->query()->get()->map(fn (Transaction $t) => [
            $t->transaction_id,
            $t->user?->name ?? 'Unknown user',
            $t->subscriptionPlan?->name ?? 'Unknown plan',
            self::peso($t->amount),
            $t->method,
            $t->status,
            $t->reference_no,
            self::screenTime($t->created_at),
        ]);
    }

    protected function csvHeader(): array
    {
        return [
            'Transaction ID', 'User', 'Email', 'Plan', 'Amount', 'Currency',
            'Method', 'Status', 'Reference No.', 'Processed At', 'Created At',
        ];
    }

    protected function csvRow(mixed $t, int $i): array
    {
        return [
            $t->transaction_id,
            $t->user?->name ?? 'Unknown user',
            $t->user?->email ?? '',
            $t->subscriptionPlan?->name ?? 'Unknown plan',
            self::plainAmount($t->amount),
            $t->currency,
            $t->method,
            $t->status,
            $t->reference_no,
            self::csvTime($t->processed_at),
            self::csvTime($t->created_at),
        ];
    }
}
