<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->per_page, [5, 10, 25, 50], true)
            ? (int) $request->per_page
            : 10;

        $total     = User::count();
        $active    = User::where('status', 'Active')->count();
        $suspended = User::where('status', 'Suspended')->count();
        $newWeek   = User::where('created_at', '>=', now()->subWeek())->count();
        $prevWeek  = User::whereBetween('created_at', [now()->subWeeks(2), now()->subWeek()])->count();

        return view('admin.users', [
            'users'   => $this->filtered($request)
                              ->withCount(['favorites', 'itineraries'])
                              ->paginate($perPage)
                              ->withQueryString(),
            'perPage' => $perPage,
            'summary' => [
                'total'         => $total,
                'active'        => $active,
                'active_pct'    => $total ? round($active / $total * 100, 2) : 0,
                'new_week'      => $newWeek,
                'new_delta'     => $newWeek - $prevWeek,
                'suspended'     => $suspended,
                'suspended_pct' => $total ? round($suspended / $total * 100, 2) : 0,
            ],
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'   => ['required', 'string', 'max:100'],
            'email'  => ['required', 'email', 'max:150', 'unique:devotees,email,'.$user->id],
            'role'   => ['required', 'in:user,admin'],
            'status' => ['required', 'in:Active,Inactive,Suspended'],
        ]);

        // Never let an administrator lock themselves out.
        if ($user->id === auth()->id() && ($data['role'] !== 'admin' || $data['status'] !== 'Active')) {
            return back()->with('error', 'You cannot change your own role or status.');
        }

        $user->update($data + ['updated_at' => now()]);

        return back()->with('success', $user->name.' updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $name = $user->name;
        $user->delete();

        return back()->with('success', $name.' deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($request)->withCount(['favorites', 'itineraries'])->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['No.', 'Name', 'Email', 'Role', 'Joined',
                           'Saved Destinations', 'Itineraries Created', 'Status']);

            foreach ($rows as $i => $u) {
                fputcsv($out, [
                    $i + 1,
                    $u->name,
                    $u->email,
                    ucfirst($u->role),
                    $u->created_at?->format('F j, Y'),
                    $u->favorites_count,
                    $u->itineraries_count,
                    $u->status,
                ]);
            }

            fclose($out);
        }, 'giya-users-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /* ------------------------------------------------------------------ */

    protected function filtered(Request $request)
    {
        return User::query()
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) =>
                $w->where('name', 'ilike', "%{$s}%")->orWhere('email', 'ilike', "%{$s}%")))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->when($request->from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderByDesc('created_at');
    }
}
