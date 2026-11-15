<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Notification;
use App\Models\VisitHistory;
use App\Services\RoutePlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Check-ins at any church, not only the stops of a trip.
 *
 * While the map is open, the page watches the devotee's location. Having
 * stood within ARRIVE_M of a church for about a minute, it sends the church
 * and the position here, and the visit is recorded: in the visit history,
 * the profile's progress, and a note in the bell. The page offers Undo.
 */
class VisitController extends Controller
{
    /** Matches the page. */
    public const ARRIVE_M = 75;

    /** Slack for GPS: the server accepts a little more than the page uses. */
    public const ACCEPT_M = 120;

    public function arrive(Request $request): JsonResponse
    {
        $data = $request->validate([
            'church_id' => ['required', 'integer'],
            'lat'       => ['required', 'numeric', 'between:-90,90'],
            'lng'       => ['required', 'numeric', 'between:-180,180'],
        ]);

        $church = Church::active()->whereNotNull('latitude')->whereNotNull('longitude')->find($data['church_id']);

        if (! $church) {
            return response()->json(['ok' => false, 'reason' => 'unknown'], 404);
        }

        // The page decided the devotee is at the door; check it agrees.
        $metres = RoutePlanner::km([(float) $data['lat'], (float) $data['lng']], [(float) $church->latitude, (float) $church->longitude]) * 1000;

        if ($metres > self::ACCEPT_M) {
            return response()->json(['ok' => false, 'reason' => 'too_far'], 422);
        }

        // One visit per church per day: walking around the plaza is one visit.
        $today = VisitHistory::where('user_id', Auth::id())
            ->where('church_id', $church->id)
            ->where('visited_at', '>=', now()->startOfDay())
            ->first();

        if ($today) {
            return response()->json(['ok' => true, 'already' => true, 'visit_id' => $today->id]);
        }

        $visit = DB::transaction(function () use ($church) {
            $visit = VisitHistory::create([
                'user_id'           => Auth::id(),
                'church_id'         => $church->id,
                'itinerary_id'      => null,
                'visited_at'        => now(),
                'completion_status' => 'Completed',
                'notes'             => 'Checked in automatically from the map.',
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            // So someone praying with the phone in a pocket can see later that it was recorded.
            Notification::create([
                'user_id'    => Auth::id(),
                'type'       => 'itinerary',
                'title'      => __('giya.plan.arrive_note_title', ['church' => $church->name]),
                'message'    => __('giya.visit.note_message'),
                'url'        => route('churches.show', $church),
                'dedupe_key' => 'visit:'.$visit->id,
                'is_read'    => false,
                'created_at' => now(),
            ]);

            return $visit;
        });

        return response()->json([
            'ok'       => true,
            'visit_id' => $visit->id,
            'church'   => $church->name,
            'undo_url' => route('visits.undo', $visit),
        ]);
    }

    /** GPS drifts; a church driven past can be checked in by mistake. */
    public function undo(VisitHistory $visit): JsonResponse
    {
        abort_unless($visit->user_id === Auth::id(), 403);

        // Only the map's own check-ins; a trip stop is undone on the trip page.
        abort_unless($visit->itinerary_id === null, 422);

        DB::transaction(function () use ($visit) {
            Notification::where('user_id', Auth::id())->where('dedupe_key', 'visit:'.$visit->id)->delete();
            $visit->delete();
        });

        return response()->json(['ok' => true, 'undone' => true]);
    }
}
