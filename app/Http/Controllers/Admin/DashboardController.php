<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Feedback;
use App\Models\Itinerary;
use App\Models\User;
use App\Models\VisitHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                ['label' => 'Total Users',  'value' => User::count(),                       'icon' => 'people-fill'],
                ['label' => 'Destinations', 'value' => Church::active()->count(),           'icon' => 'building'],
                ['label' => 'Total Visits', 'value' => VisitHistory::count(),               'icon' => 'geo-alt-fill'],
                ['label' => 'Itineraries',  'value' => Itinerary::count(),                  'icon' => 'journal-text'],
                ['label' => 'Feedback',     'value' => Feedback::count(),                   'icon' => 'chat-dots-fill'],
            ],
            'monthlyVisits'   => $this->monthlyVisits(),
            'popularChurches' => $this->popularChurches(),
            'recentActivity'  => $this->recentActivity(),
        ]);
    }

    /** Visits grouped by month for the last 6 months (PostgreSQL to_char). */
    private function monthlyVisits(): array
    {
        $rows = VisitHistory::query()
            ->select(DB::raw("to_char(visited_at, 'YYYY-MM') as ym"), DB::raw('count(*) as total'))
            ->where('visited_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('ym')
            ->orderBy('ym')
            ->pluck('total', 'ym')
            ->toArray();

        $labels = $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $month    = now()->subMonths($i);
            $key      = $month->format('Y-m');
            $labels[] = $month->format('M');
            $data[]   = (int) ($rows[$key] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    private function popularChurches(): array
    {
        // Only count visits that point to an existing church. This prevents
        // orphaned/null church_id records from appearing as "Unknown church".
        $rows = VisitHistory::query()
            ->join('churches', 'churches.id', '=', 'visit_history.church_id')
            ->whereNotNull('visit_history.church_id')
            ->select(
                'churches.id',
                'churches.name as church_name',
                DB::raw('count(*) as total')
            )
            ->groupBy('churches.id', 'churches.name')
            ->orderByDesc('total')
            ->orderBy('churches.name')
            ->take(5)
            ->get();

        return [
            'labels' => $rows->pluck('church_name')->filter(fn ($name) => filled($name))->values()->toArray(),
            'data'   => $rows->pluck('total')->map(fn ($v) => (int) $v)->toArray(),
        ];
    }

    private function recentActivity(): array
    {
        $items = [];

        foreach (User::orderByDesc('created_at')->take(3)->get() as $u) {
            $items[] = ['icon' => 'person-plus-fill', 'text' => "New user registered: {$u->name}", 'at' => $u->created_at];
        }

        foreach (VisitHistory::with('church')->orderByDesc('visited_at')->take(3)->get() as $v) {
            if ($v->church) {
                $items[] = [
                    'icon' => 'geo-alt-fill',
                    'text' => "Visit logged at {$v->church->name}",
                    'at' => $v->visited_at,
                ];
            }
        }

        foreach (Feedback::with('church')->orderByDesc('created_at')->take(2)->get() as $f) {
            $items[] = ['icon' => 'star-fill', 'text' => "Feedback received for " . ($f->church->name ?? 'a destination') . " ({$f->rating}★)", 'at' => $f->created_at];
        }

        usort($items, fn ($a, $b) => ($b['at'] ?? Carbon::minValue()) <=> ($a['at'] ?? Carbon::minValue()));

        return array_slice($items, 0, 6);
    }
}
