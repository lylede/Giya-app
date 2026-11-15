<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** What pilgrims say about GIYA itself: the average, by topic, and each message. */
class AppFeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'topic'  => ['nullable', 'in:'.implode(',', AppFeedback::TOPICS)],
            'rating' => ['nullable', 'integer', 'between:1,5'],
        ]);

        if (! Schema::hasTable('app_feedback')) {
            return view('admin.app-feedback', ['ready' => false, 'items' => collect(), 'summary' => null, 'byTopic' => collect(), 'filters' => $filters]);
        }

        $items = $this->query($filters)->with('user')->orderByDesc('id')->paginate(20)->withQueryString();

        $byTopic = AppFeedback::query()->selectRaw('topic, count(*) as total, avg(rating) as average')
            ->groupBy('topic')->get()->keyBy('topic');

        return view('admin.app-feedback', [
            'ready'   => true,
            'items'   => $items,
            'filters' => $filters,
            'byTopic' => $byTopic,
            'summary' => [
                'total'   => AppFeedback::count(),
                'average' => round((float) AppFeedback::avg('rating'), 2),
                'month'   => AppFeedback::where('created_at', '>=', now()->startOfMonth())->count(),
                'low'     => AppFeedback::where('rating', '<=', 2)->count(),
            ],
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'topic'  => ['nullable', 'in:'.implode(',', AppFeedback::TOPICS)],
            'rating' => ['nullable', 'integer', 'between:1,5'],
        ]);

        $rows = $this->query($filters)->with('user')->orderByDesc('id')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Name', 'Email', 'Rating', 'Topic', 'Comment']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->created_at?->format('Y-m-d H:i'), $r->user->name ?? '', $r->user->email ?? '', $r->rating, $r->topicLabel(), $r->comment]);
            }
            fclose($out);
        }, 'giya-app-feedback-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function query(array $filters)
    {
        return AppFeedback::query()
            ->when($filters['topic'] ?? null, fn ($q, $t) => $q->where('topic', $t))
            ->when($filters['rating'] ?? null, fn ($q, $r) => $q->where('rating', $r));
    }
}
