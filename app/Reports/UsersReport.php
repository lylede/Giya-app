<?php

namespace App\Reports;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UsersReport extends RecordReport
{
    public function title(): string
    {
        return 'User Report';
    }

    public function columns(): array
    {
        return ['Name', 'Email', 'Role', 'Status', 'Joined', 'Saved Destinations', 'Itineraries'];
    }

    protected function query(): Builder
    {
        return $this->inRange(User::query(), 'created_at')
            ->withCount(['favorites', 'itineraries'])
            ->orderByDesc('created_at');
    }

    public function rows(): Collection
    {
        return $this->query()->get()->map(fn (User $user) => [
            $user->name,
            $user->email,
            ucfirst($user->role),
            $user->status,
            self::screenTime($user->created_at),
            $user->favorites_count,
            $user->itineraries_count,
        ]);
    }

    protected function csvHeader(): array
    {
        return ['No.', 'Name', 'Email', 'Role', 'Status', 'Joined', 'Saved Destinations', 'Itineraries Created'];
    }

    protected function csvRow(mixed $user, int $i): array
    {
        return [
            $i + 1,
            $user->name,
            $user->email,
            ucfirst($user->role),
            $user->status,
            self::csvTime($user->created_at),
            $user->favorites_count,
            $user->itineraries_count,
        ];
    }
}
