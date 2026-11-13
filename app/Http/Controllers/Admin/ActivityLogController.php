<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/** The activity log: read only. */
class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'admin' => ['nullable', 'string', 'max:100'],
            'q'     => ['nullable', 'string', 'max:100'],
            'from'  => ['nullable', 'date'],
            'to'    => ['nullable', 'date'],
        ]);

        if (! Schema::hasTable('activity_logs')) {
            return view('admin.activity', ['ready' => false, 'entries' => collect(), 'admins' => collect(), 'filters' => $filters]);
        }

        $search = trim((string) ($filters['q'] ?? ''));
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search)).'%';

        $entries = ActivityLog::query()
            ->when($filters['admin'] ?? null, fn ($q, $name) => $q->where('admin_name', $name))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereRaw('LOWER(action) LIKE ?', [$like])
                ->orWhereRaw('LOWER(subject) LIKE ?', [$like])
                ->orWhereRaw('LOWER(details) LIKE ?', [$like])))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', \Illuminate\Support\Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', \Illuminate\Support\Carbon::parse($to)->endOfDay()))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.activity', [
            'ready'   => true,
            'entries' => $entries,
            'admins'  => ActivityLog::query()->distinct()->orderBy('admin_name')->pluck('admin_name'),
            'filters' => $filters,
        ]);
    }
}
