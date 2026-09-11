<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Itinerary;
use App\Models\ItineraryStop;
use App\Models\ItineraryType;
use App\Models\User;
use App\Models\VisitHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ItineraryController extends Controller
{
    /** Free accounts may keep this many saved itineraries. */
    public const FREE_LIMIT = 3;

    public function hub(): View
    {
        $userId = Auth::id();

        return view('plan.hub', [
            'itineraries' => Itinerary::where('user_id', $userId)
                ->orderByDesc('created_at')->take(5)->get(),
            'activeItinerary' => Itinerary::where('user_id', $userId)
                ->where('status', 'Active')->latest('updated_at')->first(),
        ]);
    }

    public function visita(Request $request): View
    {
        $churches = Church::active()->orderBy('name')->get();

        /* A route coming back from the map. The map is the other half of this
           planner now - a Visita trip sent there keeps its type and can be
           saved from either screen - so a church added or dropped over there
           has to arrive here, or the two views would quietly disagree about
           what the trip is. */
        $preset = $this->presetFrom($request, $churches)
            ->map(fn (Church $c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'location' => $c->location,
                'color'    => $c->color(),
            ])
            ->values();

        return view('plan.visita', [
            'churches' => $churches,
            'atLimit'  => $this->atLimit(),
            'preset'   => $preset,
        ]);
    }

    /**
     * The route a planner is being opened with, in the order it was given.
     *
     * Stops chosen on the map arrive as ?stops=3,7,1 - in the order the map
     * worked out, which is the nearest-neighbour order it drew. That order is
     * the useful part, so it is preserved rather than re-sorted.
     *
     * old('stop_ids') comes first, and is why a rejected form no longer costs
     * the devotee their route: the planners post the chosen ids alongside the
     * names, so when validation sends them back the route is rebuilt exactly
     * as they left it instead of the page reloading empty.
     *
     * @param  \Illuminate\Support\Collection<int, Church>  $churches
     * @return \Illuminate\Support\Collection<int, Church>
     */
    private function presetFrom(Request $request, $churches)
    {
        return collect(explode(',', (string) old('stop_ids', $request->query('stops'))))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->unique()
            ->map(fn ($id) => $churches->firstWhere('id', $id))
            ->filter();
    }

    public function index(): View
    {
        $itineraries = Itinerary::with('stops')
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        return view('plan.my-itineraries', [
            'itineraries' => $itineraries,
            // Deleted ones are not in the list above but still spend a slot,
            // so the meter is counted separately rather than from the list.
            'used'        => Itinerary::countingAgainstFreeLimit(Auth::id()),
            'limit'       => self::FREE_LIMIT,
            'atLimit'     => $this->atLimit(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->atLimit()) {
            return back()->with('error',
                'You have reached the free limit of ' . self::FREE_LIMIT . ' itineraries. Upgrade to create more.');
        }

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:200'],
            'type'           => ['required', 'in:Custom,Visita Iglesia'],
            'scheduled_date' => ['nullable', 'date', 'after_or_equal:today'],
            'notes'          => ['nullable', 'string', 'max:2000'],
            'stops'          => ['required', 'array', 'min:1', 'max:20'],
            'stops.*'        => ['required', 'string', 'max:200'],

            /* Ids when the caller has them - the map does, and the planner
               already posts them for repopulating a rejected form. Names alone
               cannot tell two "San Roque Chapel" apart, and a renamed church
               would drop out of a route silently. A comma-joined string, the
               shape the planner has always used, so old('stop_ids') means one
               thing everywhere. */
            'stop_ids'       => ['nullable', 'string', 'max:200'],
        ], [
            'stops.required' => 'Add at least one destination to your route.',
            'scheduled_date.after_or_equal' => 'The pilgrimage date cannot be in the past.',
        ]);

        $itinerary = DB::transaction(function () use ($data) {
            // ERD: itineraries.type is now a foreign key into itinerary_types.
            $type = ItineraryType::firstOrCreate(
                ['name' => $data['type']],
                ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );

            $itinerary = Itinerary::create([
                'user_id'           => Auth::id(),
                'itinerary_type_id' => $type->id,
                'name'              => $data['name'],
                'status'            => 'Upcoming',
                'schedule_date'     => $data['scheduled_date'] ?? null,
                'notes'             => $data['notes'] ?? null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            // ERD: itinerary_stops.church_id is required, and church_name is gone.
            $wanted = collect(explode(',', (string) ($data['stop_ids'] ?? '')))
                ->map(fn ($id) => (int) trim($id))
                ->filter()
                ->values()
                ->all();

            $ids = $wanted
                ? Church::whereIn('id', $wanted)->pluck('id')
                    ->sortBy(fn ($id) => array_search($id, $wanted))
                    ->values()
                : Church::whereIn('name', $data['stops'])
                    ->pluck('id', 'name')
                    ->only($data['stops'])
                    ->sortBy(fn ($id, $name) => array_search($name, $data['stops']))
                    ->values();

            $order = 1;
            foreach ($ids as $churchId) {
                ItineraryStop::create([
                    'itinerary_id' => $itinerary->id,
                    'church_id'    => $churchId,
                    'stop_order'   => $order++,
                    'is_visited'   => false,
                ]);
            }

            return $itinerary;
        });

        return redirect()->route('plan.show', $itinerary)
            ->with('success', 'Itinerary created. Your pilgrimage has begun.');
    }

    public function show(Itinerary $itinerary): View
    {
        $this->authorizeOwner($itinerary);

        if ($itinerary->status === 'Upcoming') {
            $itinerary->update(['status' => 'Active', 'updated_at' => now()]);
        }

        return view('plan.active', [
            'itinerary' => $itinerary,
            'stops'     => $itinerary->stops()->with('church')->get(),
        ]);
    }

    public function markVisited(Request $request): JsonResponse
    {
        $data = $request->validate([
            'stop_id' => ['required', 'integer', 'exists:itinerary_stops,id'],
            'undo'    => ['nullable', 'boolean'],
        ]);

        $stop = ItineraryStop::with('itinerary')->findOrFail($data['stop_id']);
        $this->authorizeOwner($stop->itinerary);

        /*
           Undo an automatic check-in.

           GPS drifts, and a devotee walking past a church that is later on
           their route can be marked arrived before they mean to be. The visit
           record is removed with the flag, so their history stays honest.
        */
        if ($request->boolean('undo')) {
            DB::transaction(function () use ($stop) {
                $stop->update(['is_visited' => false, 'visited_at' => null]);

                VisitHistory::where('user_id', Auth::id())
                    ->where('itinerary_id', $stop->itinerary_id)
                    ->where('church_id', $stop->church_id)
                    ->latest('visited_at')
                    ->limit(1)
                    ->delete();
            });

            return response()->json(['ok' => true, 'undone' => true]);
        }

        if ($stop->is_visited) {
            return response()->json(['ok' => true, 'already' => true]);
        }

        $allDone = DB::transaction(function () use ($stop) {
            $stop->update(['is_visited' => true, 'visited_at' => now()]);

            VisitHistory::create([
                'user_id'           => Auth::id(),
                'church_id'         => $stop->church_id,
                'itinerary_id'      => $stop->itinerary_id,
                'visited_at'        => now(),
                'completion_status' => 'Completed',
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            $remaining = ItineraryStop::where('itinerary_id', $stop->itinerary_id)
                ->where('is_visited', false)->count();

            if ($remaining > 0) {
                return false;
            }

            $stop->itinerary->update(['status' => 'Completed', 'updated_at' => now()]);

            // The profile counters are derived from itineraries and
            // visit_history now, so there is nothing left to increment here.

            return true;
        });

        return response()->json(['ok' => true, 'all_done' => $allDone]);
    }

    public function destroy(Itinerary $itinerary): RedirectResponse
    {
        $this->authorizeOwner($itinerary);

        // A soft delete, so the stops stay with it. Removing them would gut
        // the record we are deliberately keeping, and a deleted itinerary
        // still has to be a real itinerary for the allowance count and for
        // anything an admin looks at later.
        $itinerary->delete();

        return redirect()->route('plan.index')->with(
            'success',
            Auth::user()->is_premium
                ? 'Itinerary deleted.'
                : 'Itinerary deleted. It still counts towards your '.self::FREE_LIMIT.' free itineraries.'
        );
    }

    private function atLimit(): bool
    {
        return Itinerary::atFreeLimit(Auth::user());
    }

    private function authorizeOwner(Itinerary $itinerary): void
    {
        abort_unless($itinerary->user_id === Auth::id(), 403);
    }
}
