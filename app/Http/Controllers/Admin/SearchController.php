<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The admin top bar's quick search: churches and people by name, for the
 * box that jumps straight to them. Admin pages are matched in the browser.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['churches' => [], 'users' => []]);
        }
        $like = '%'.mb_strtolower($q).'%';

        $churches = Church::query()
            ->where(fn ($w) => $w->whereRaw('LOWER(name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(municipality, \'\')) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(location, \'\')) LIKE ?', [$like]))
            ->orderByRaw('CASE WHEN LOWER(name) LIKE ? THEN 0 ELSE 1 END', [mb_strtolower($q).'%'])
            ->orderBy('name')->limit(6)
            ->get(['id', 'name', 'municipality', 'location', 'is_active'])
            ->map(fn (Church $c) => [
                'name'  => $c->name,
                'place' => $c->municipality ?: $c->location,
                'draft' => ! $c->is_active,
                'url'   => route('admin.destinations', ['focus' => $c->id]),
            ]);

        $users = User::query()
            ->where(fn ($w) => $w->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(email) LIKE ?', [$like]))
            ->orderBy('name')->limit(5)
            ->get(['id', 'name', 'email', 'role'])
            ->map(fn (User $u) => [
                'name'  => $u->name,
                'email' => $u->email,
                'role'  => $u->role,
                'url'   => route('admin.users', ['search' => $u->email]),
            ]);

        return response()->json(['churches' => $churches, 'users' => $users]);
    }
}
