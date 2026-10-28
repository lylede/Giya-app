<?php

namespace App\Reports;

use App\Models\Itinerary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ItinerariesReport extends RecordReport
{
    public function title(): string
    {
        return 'Itinerary Report';
    }

    public function columns(): array
    {
        return ['User', 'Itinerary Name', 'Type', 'Scheduled Date', 'Status', 'Total Stops', 'Visited Stops', 'Created At'];
    }

    protected function query(): Builder
    {
        return $this->inRange(Itinerary::query(), 'created_at')
            ->with(['user', 'itineraryType'])
            ->withCount([
                'stops',
                'stops as visited_stops_count' => fn ($q) => $q->where('is_visited', true),
            ])
            ->orderByDesc('created_at');
    }

    public function rows(): Collection
    {
        return $this->query()->get()->map(fn (Itinerary $it) => [
            $it->user?->name ?? 'Unknown user',
            $it->name,
            $it->itineraryType?->name ?? 'Custom',
            $it->schedule_date?->format('M d, Y'),
            $it->status,
            $it->stops_count,
            $it->visited_stops_count,
            self::screenTime($it->created_at),
        ]);
    }

    protected function csvHeader(): array
    {
        return ['No.', 'User', 'Itinerary Name', 'Type', 'Scheduled Date', 'Status', 'Total Stops', 'Visited Stops', 'Created At'];
    }

    protected function csvRow(mixed $it, int $i): array
    {
        return [
            $i + 1,
            $it->user?->name ?? 'Unknown user',
            $it->name,
            $it->itineraryType?->name ?? 'Custom',
            $it->schedule_date?->format('Y-m-d'),
            $it->status,
            $it->stops_count,
            $it->visited_stops_count,
            self::csvTime($it->created_at),
        ];
    }
}
