<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class NotificationController extends Controller
{
    /** Feed for the bell panel. */
    public function index(): JsonResponse
    {
        $rows = Notification::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->take(20)
            ->get();

        return response()->json([
            'unread' => $rows->where('is_read', false)->count(),
            'items'  => $rows->map(fn (Notification $n) => [
                'id'      => $n->id,
                'type'    => $n->type,
                'icon'    => $n->icon,
                'title'   => $n->title,
                'message' => $n->message,
                'url'     => $n->url,
                'read'    => $n->is_read,
                'when'    => $n->created_at?->diffForHumans(),
            ])->values(),
        ]);
    }

    public function markRead(Notification $notification): JsonResponse
    {
        Gate::authorize('update', $notification);

        $notification->update(['is_read' => true]);

        return response()->json(['ok' => true]);
    }

    /** Dismiss one notification from the panel. */
    public function destroy(Notification $notification): JsonResponse
    {
        Gate::authorize('delete', $notification);

        $notification->delete();

        return response()->json([
            'ok'     => true,
            'unread' => Notification::where('user_id', Auth::id())->unread()->count(),
        ]);
    }

    public function markAllRead(): JsonResponse
    {
        Notification::where('user_id', Auth::id())->unread()->update(['is_read' => true]);

        return response()->json(['ok' => true]);
    }

    public function clear(): RedirectResponse
    {
        Notification::where('user_id', Auth::id())->delete();

        return back()->with('success', 'Notifications cleared.');
    }
}
