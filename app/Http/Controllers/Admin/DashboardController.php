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
                /* GIYA's own icons, and a tone each so a row of five reads as
                   five things rather than one thing repeated. An itinerary is
                   a route, not a notebook; feedback is a rating, not a speech
                   bubble; a devotee is a pilgrim, not a generic person. */
                ['label' => 'Total Users',  'value' => User::count(),             'icon' => 'giya-pilgrim', 'tone' => 'primary'],
                ['label' => 'Destinations', 'value' => Church::active()->count(), 'icon' => 'giya-spires',  'tone' => 'gold'],
                ['label' => 'Total Visits', 'value' => VisitHistory::count(),     'icon' => 'giya-nearby',  'tone' => 'green'],
                ['label' => 'Itineraries',  'value' => Itinerary::count(),        'icon' => 'giya-route',   'tone' => 'blue'],
                ['label' => 'Feedback',     'value' => Feedback::count(),         'icon' => 'giya-star',    'tone' => 'gold'],
            ],
            'monthlyVisits'   => $this->monthlyVisits(),
            'popularChurches' => $this->popularChurches(),
            'recentActivity'  => $this->recentActivity(),
            'todo'            => $this->todo(),
        ]);
    }

    /** Visits grouped by month for the last 6 months. */
    private function monthlyVisits(): array
    {
        // PostgreSQL in production; SQLite in the tests.
        $month = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', visited_at)"
            : "to_char(visited_at, 'YYYY-MM')";

        $rows = VisitHistory::query()
            ->select(DB::raw("$month as ym"), DB::raw('count(*) as total'))
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
    $rows = DB::table('visit_history')
        ->join('churches', 'churches.id', '=', 'visit_history.church_id')
        ->select(
            'churches.id',
            'churches.name as destination_name',
            DB::raw('COUNT(visit_history.id) as total')
        )
        ->whereNotNull('visit_history.church_id')
        ->whereNotNull('churches.name')
        ->where('churches.name', '<>', '')
        ->groupBy('churches.id', 'churches.name')
        ->orderByDesc('total')
        ->orderBy('churches.name')
        ->limit(5)
        ->get();

    return [
        'labels' => $rows
            ->pluck('destination_name')
            ->values()
            ->toArray(),

        'data' => $rows
            ->pluck('total')
            ->map(fn ($value) => (int) $value)
            ->values()
            ->toArray(),
    ];
}

    private function recentActivity(): array
    {
        $items = [];

        foreach (User::orderByDesc('created_at')->take(3)->get() as $u) {
            $items[] = ['icon' => 'giya-pilgrim', 'text' => "New user registered: {$u->name}", 'at' => $u->created_at];
        }
        foreach (VisitHistory::orderByDesc('visited_at')->take(3)->get() as $v) {
            $items[] = ['icon' => 'giya-nearby', 'text' => "Visit logged at {$v->church_name}", 'at' => $v->visited_at];
        }

        foreach (Feedback::with('church')->orderByDesc('created_at')->take(2)->get() as $f) {
            $items[] = ['icon' => 'giya-star', 'text' => "Feedback received for " . ($f->church->name ?? 'a destination') . " ({$f->rating}★)", 'at' => $f->created_at];
        }

        usort($items, fn ($a, $b) => ($b['at'] ?? Carbon::minValue()) <=> ($a['at'] ?? Carbon::minValue()));

        return array_slice($items, 0, 6);
    }

    /**
     * What needs an admin today, in the order it matters to devotees: each
     * line counts something waiting and links straight to where it is done.
     * Lines with nothing waiting are left out.
     */
    private function todo(): array
    {
        $churches = Church::query()
            ->withCount(['schedules as live_schedules' => fn ($q) => $q->where('status', 'Published')])
            ->with('primaryImage')
            ->get();

        $noMass  = $churches->where('is_active', true)->where('live_schedules', 0)->count();
        $noPhoto = $churches->where('is_active', true)
            ->filter(fn (Church $c) => str_contains($c->imagePath(), 'placeholder'))->count();

        $items = [
            ['n' => Feedback::where('status', 'Pending')->count(), 'icon' => 'giya-star',
             'one' => 'review waiting for approval', 'many' => 'reviews waiting for approval',
             'url' => route('admin.feedback', ['status' => 'Pending']), 'tone' => 'gold'],
            ['n' => \App\Models\Transaction::where('status', 'Pending')->count(), 'icon' => 'credit-card',
             'one' => 'payment to check', 'many' => 'payments to check',
             'url' => route('admin.transactions', ['status' => 'Pending']), 'tone' => 'blue'],
            ['n' => $noMass, 'icon' => 'calendar3',
             'one' => 'published church with no Mass schedule', 'many' => 'published churches with no Mass schedule',
             'url' => route('admin.destinations', ['issue' => 'schedule']), 'tone' => 'primary'],
            ['n' => \App\Models\Schedule::where('status', '!=', 'Published')->count(), 'icon' => 'eye-slash',
             'one' => 'schedule saved as Draft', 'many' => 'schedules saved as Draft',
             'url' => route('admin.schedules', ['view' => 'all', 'status' => 'Draft']), 'tone' => 'primary'],
            ['n' => $noPhoto, 'icon' => 'camera-fill',
             'one' => 'published church with no photo', 'many' => 'published churches with no photo',
             'url' => route('admin.destinations', ['issue' => 'photo']), 'tone' => 'green'],
        ];

        return array_values(array_filter($items, fn ($i) => $i['n'] > 0));
    }
}
