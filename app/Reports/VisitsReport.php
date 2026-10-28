<?php

namespace App\Reports;

use App\Models\VisitHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class VisitsReport extends RecordReport
{
    public function title(): string
    {
        return 'Pilgrimage Visit Report';
    }

    public function columns(): array
    {
        return ['User', 'Destination', 'Itinerary', 'Completion Status', 'Visited At', 'Notes'];
    }

    protected function query(): Builder
    {
        return $this->inRange(VisitHistory::query(), 'visited_at')
            ->with(['user', 'church', 'itinerary'])
            ->orderByDesc('visited_at');
    }

    public function rows(): Collection
    {
        return $this->query()->get()->map(fn (VisitHistory $v) => [
            $v->user?->name ?? 'Unknown user',
            $v->church?->name ?? 'Unknown destination',
            $v->itinerary?->name ?? 'Independent visit',
            $v->completion_status,
            self::screenTime($v->visited_at),
            $v->notes,
        ]);
    }

    protected function csvHeader(): array
    {
        return ['No.', 'User', 'Destination', 'Itinerary', 'Completion Status', 'Visited At', 'Notes'];
    }

    protected function csvRow(mixed $v, int $i): array
    {
        return [
            $i + 1,
            $v->user?->name ?? 'Unknown user',
            $v->church?->name ?? 'Unknown destination',
            $v->itinerary?->name ?? 'Independent visit',
            $v->completion_status,
            self::csvTime($v->visited_at),
            $v->notes,
        ];
    }
}
