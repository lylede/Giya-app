<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Itinerary;
use App\Models\ItineraryStop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Changing the stops of a saved trip, also in the middle of it.
 *
 * A pilgrim halfway through a Visita Iglesia who decides on another church
 * used to be stuck: a saved trip could not be changed, only ended. Now the
 * stops not yet visited can be added to, removed, swapped and put in a new
 * order. Visited stops are never touched, so the history stays true.
 *
 * Premium only (free accounts see the Upgrade offer). Editing a trip never
 * uses a free itinerary: it is the same trip.
 *
 * Order: visited stops first, in the order they were, then the stops still
 * to come. The current stop is always the first one not yet visited, which
 * is how the trip page and the map already read a trip.
 */
class ItineraryStopController extends Controller
{
    /** The same ceiling as saving a new trip. */
    public const MAX_STOPS = 20;

    /** Add a church as the next stop or at the end. */
    public function add(Request $request, Itinerary $itinerary): JsonResponse
    {
        if ($refused = $this->refuse($itinerary)) {
            return $refused;
        }

        $data = $request->validate([
            'church_id' => ['required', 'integer'],
            'position'  => ['required', 'in:next,end'],
        ]);

        $church = Church::active()->find($data['church_id']);
        if (! $church) {
            return $this->no(__('giya.plan.edit_not_found'));
        }

        return $this->change($itinerary, function ($stops) use ($itinerary, $church, $data) {
            if ($stops->contains('church_id', $church->id)) {
                return __('giya.plan.edit_already');
            }
            if ($stops->count() >= self::MAX_STOPS) {
                return __('giya.plan.edit_full', ['max' => self::MAX_STOPS]);
            }

            $stop = ItineraryStop::create([
                'itinerary_id' => $itinerary->id,
                'church_id'    => $church->id,
                'stop_order'   => 0,
                'is_visited'   => false,
            ]);

            $todo = $stops->where('is_visited', false)->pluck('id')->values();

            return $data['position'] === 'next' ? $todo->prepend($stop->id) : $todo->push($stop->id);
        });
    }

    /** Remove a stop not yet visited. It leaves the trip, as if never planned. */
    public function remove(Itinerary $itinerary, ItineraryStop $stop): JsonResponse
    {
        if ($refused = $this->refuse($itinerary, $stop)) {
            return $refused;
        }

        return $this->change($itinerary, function ($stops) use ($stop) {
            if ($stops->count() <= 1) {
                return __('giya.plan.edit_last');
            }

            $stop->delete();

            return $stops->where('is_visited', false)->where('id', '!=', $stop->id)->pluck('id')->values();
        });
    }

    /** Put another church in place of a stop not yet visited. */
    public function swap(Request $request, Itinerary $itinerary, ItineraryStop $stop): JsonResponse
    {
        if ($refused = $this->refuse($itinerary, $stop)) {
            return $refused;
        }

        $data = $request->validate(['church_id' => ['required', 'integer']]);
        $church = Church::active()->find($data['church_id']);
        if (! $church) {
            return $this->no(__('giya.plan.edit_not_found'));
        }

        return $this->change($itinerary, function ($stops) use ($stop, $church) {
            if ($stops->contains('church_id', $church->id)) {
                return __('giya.plan.edit_already');
            }

            $stop->update(['church_id' => $church->id]);

            return $stops->where('is_visited', false)->pluck('id')->values();
        });
    }

    /** A new order for the stops not yet visited. */
    public function reorder(Request $request, Itinerary $itinerary): JsonResponse
    {
        if ($refused = $this->refuse($itinerary)) {
            return $refused;
        }

        $data = $request->validate([
            'stops'   => ['required', 'array', 'max:'.self::MAX_STOPS],
            'stops.*' => ['required', 'integer'],
        ]);

        return $this->change($itinerary, function ($stops) use ($data) {
            $todo = $stops->where('is_visited', false)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $given = collect($data['stops'])->map(fn ($id) => (int) $id)->values();

            // Exactly the stops still to come, each once: nothing added or lost on the way.
            if ($given->sort()->values()->all() !== $todo) {
                return __('giya.plan.edit_stale');
            }

            return $given;
        });
    }

    /**
     * Owner only, Premium only, and a finished trip stays as it was. A
     * visited stop cannot be removed or swapped.
     */
    private function refuse(Itinerary $itinerary, ?ItineraryStop $stop = null): ?JsonResponse
    {
        Gate::authorize('update', $itinerary);

        if ($stop && (int) $stop->itinerary_id !== (int) $itinerary->id) {
            abort(404);
        }

        $user = Auth::user();
        if (! $user->is_premium && ! $user->isAdmin()) {
            return response()->json([
                'ok'          => false,
                'reason'      => 'premium',
                'message'     => __('giya.plan.edit_premium_body'),
                'upgrade_url' => route('upgrade'),
            ], 403);
        }

        if ($itinerary->status === 'Completed') {
            return $this->no(__('giya.plan.edit_finished'));
        }

        if ($stop && $stop->is_visited) {
            return $this->no(__('giya.plan.edit_visited'));
        }

        return null;
    }

    /**
     * Runs one change with the trip's stops locked, then numbers them again:
     * visited first, then the stops still to come in the order the change
     * returned. The change returns a message instead to refuse.
     */
    private function change(Itinerary $itinerary, \Closure $edit): JsonResponse
    {
        $result = DB::transaction(function () use ($itinerary, $edit) {
            $stops = ItineraryStop::where('itinerary_id', $itinerary->id)
                ->orderBy('stop_order')->orderBy('id')->lockForUpdate()->get();

            $todo = $edit($stops);
            if (is_string($todo)) {
                return $todo;
            }

            $visited = $stops->where('is_visited', true)->pluck('id');
            $order = 1;
            foreach ($visited->concat($todo) as $id) {
                ItineraryStop::whereKey($id)->update(['stop_order' => $order++]);
            }

            $itinerary->update(['updated_at' => now()]);

            return null;
        });

        if (is_string($result)) {
            return $this->no($result);
        }

        // Removing the last stop still to come finishes the trip.
        $stops = $itinerary->stops()->with('church')->orderBy('stop_order')->get();
        $finished = $stops->isNotEmpty() && $stops->every->is_visited;
        if ($finished) {
            $itinerary->update(['status' => 'Completed', 'updated_at' => now()]);
            ItineraryController::remindToRate($itinerary);
        }

        return response()->json([
            'ok'       => true,
            'finished' => $finished,
            'stops'    => $stops->map(fn (ItineraryStop $s) => [
                'id'       => $s->id,
                'church'   => $s->church_id,
                'name'     => $s->church_name,
                'location' => $s->church?->location ?? 'Cebu',
                'visited'  => (bool) $s->is_visited,
            ])->values(),
        ]);
    }

    private function no(string $message): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => $message], 422);
    }
}
