<?php

namespace App\Reports;

use App\Models\Feedback;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FeedbackReport extends RecordReport
{
    public function title(): string
    {
        return 'Feedback Report';
    }

    public function columns(): array
    {
        return ['User', 'Destination', 'Rating', 'Status', 'Comment', 'Submitted At'];
    }

    protected function query(): Builder
    {
        return $this->inRange(Feedback::query(), 'created_at')
            ->with(['user', 'church'])
            ->orderByDesc('created_at');
    }

    public function rows(): Collection
    {
        return $this->query()->get()->map(fn (Feedback $f) => [
            $f->user?->name ?? 'Unknown user',
            $f->church?->name ?? 'Unknown destination',
            $f->rating.'★',
            $f->status,
            $f->comment,
            self::screenTime($f->created_at),
        ]);
    }

    protected function csvHeader(): array
    {
        return ['No.', 'User', 'Destination', 'Rating', 'Status', 'Comment', 'Submitted At'];
    }

    protected function csvRow(mixed $f, int $i): array
    {
        return [
            $i + 1,
            $f->user?->name ?? 'Unknown user',
            $f->church?->name ?? 'Unknown destination',
            $f->rating,
            $f->status,
            $f->comment,
            self::csvTime($f->created_at),
        ];
    }
}
