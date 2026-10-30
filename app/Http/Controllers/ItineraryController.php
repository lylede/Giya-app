<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Feedback;
use App\Models\Itinerary;
use App\Models\ItineraryStop;
use App\Models\Notification;
use App\Models\ItineraryType;
use App\Models\User;
use App\Models\VisitHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ItineraryController extends Controller
{
    /** Free accounts may keep this many saved itineraries. */
    public const FREE_LIMIT = 3;

    public function hub(): View
    {
        $userId = Auth::id();

        /* Plan is for what is next: the trip being walked (a church on it
           visited - opening it does not start it) and the two ways to plan.
           Every trip is listed on Saved Itineraries. */
        $active = Itinerary::where('user_id', $userId)
            ->where('status', 'Active')
            ->withCount(['stops as visited_count' => fn ($q) => $q->where('is_visited', true)])
            ->latest('updated_at')
            ->first();

        $next = $active?->stops()->with('church')->where('is_visited', false)->orderBy('stop_order')->first();

        return view('plan.hub', [
            'activeItinerary' => $active,
            'activeNext'      => $next,
            'savedCount'      => Itinerary::where('user_id', $userId)->count(),
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
            ->orderByDesc('updated_at')
            ->get();

        /* The stars this devotee gave on each finished trip (the average of
           their ratings for its churches), so the card says "You rated 4.5"
           instead of offering Rate again. */
        $myStars = Feedback::query()
            ->join('visit_history', 'visit_history.id', '=', 'feedback.visit_history_id')
            ->where('feedback.user_id', Auth::id())
            ->whereNotNull('feedback.rating')
            ->groupBy('visit_history.itinerary_id')
            ->selectRaw('visit_history.itinerary_id as trip, avg(feedback.rating) as stars')
            ->pluck('stars', 'trip');

        return view('plan.my-itineraries', [
            // In progress first, then planned, then finished.
            'groups'      => [
                ['progress',  __('giya.hub.in_progress'),  $itineraries->where('status', 'Active')->values()],
                ['upcoming',  __('giya.hub.st_upcoming'),  $itineraries->whereNotIn('status', ['Active', 'Completed'])->values()],
                ['completed', __('giya.hub.st_completed'), $itineraries->where('status', 'Completed')->values()],
            ],
            'myStars'     => $myStars,
            // Arriving from "View Saved Itineraries" after rating: that trip is outlined.
            'highlight'   => (int) request('trip'),
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
            /* The allowance is checked again here, with the devotee's row
               locked. The check at the top of store() is a fast path for the
               message; on its own it was a race: two quick submits (a double
               click, two tabs) both read "2 of 3", both passed, and a free
               account ended up with 4. The lock makes the second request wait
               until the first has committed, so it counts the new row.
               (SQLite ignores the lock; PostgreSQL, which GIYA runs on, honours it.) */
            $user = User::whereKey(Auth::id())->lockForUpdate()->first();

            if (Itinerary::atFreeLimit($user)) {
                return null;
            }

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

        if (! $itinerary) {
            return back()->with('error',
                'You have reached the free limit of ' . self::FREE_LIMIT . ' itineraries. Upgrade to create more.');
        }

        return redirect()->route('plan.show', $itinerary)
            ->with('success', 'Itinerary created. Your pilgrimage has begun.');
    }

    public function show(Itinerary $itinerary): View
    {
        Gate::authorize('view', $itinerary);

        // Opening a trip only shows it. It becomes "In progress" (Active)
        // when its first church is visited - see markVisited().

        $stops = $itinerary->stops()->with('church')->get();

        // Ratings already given on this trip, so the sheet opens filled in.
        $given = Feedback::where('user_id', Auth::id())
            ->whereIn('visit_history_id', VisitHistory::where('user_id', Auth::id())
                ->where('itinerary_id', $itinerary->id)->pluck('id'))
            ->get()
            ->keyBy('church_id');

        return view('plan.active', [
            'itinerary' => $itinerary,
            'stops'     => $stops,
            'given'     => $given,
            // From the bell's "Rate the churches", once every stop is visited.
            'rateNow'   => request()->boolean('rate') && $stops->isNotEmpty() && $stops->every->is_visited,
        ]);
    }

    /**
     * The ratings from "Rate your pilgrimage": stars for each church visited
     * on this trip, with an optional comment. Stars count at once; a comment
     * waits for an admin before it is shown on the church page.
     */
    public function storeRatings(Request $request, Itinerary $itinerary): JsonResponse
    {
        Gate::authorize('update', $itinerary);

        $data = $request->validate([
            'ratings'           => ['required', 'array'],
            'ratings.*.rating'  => ['nullable', 'integer', 'between:1,5'],
            'ratings.*.comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $saved = 0;

        foreach ($itinerary->stops()->where('is_visited', true)->get() as $stop) {
            $row = $data['ratings'][$stop->id] ?? null;
            if (! $row || empty($row['rating'])) {
                continue;
            }

            $visit = VisitHistory::where('user_id', Auth::id())
                ->where('itinerary_id', $itinerary->id)
                ->where('church_id', $stop->church_id)
                ->latest('visited_at')
                ->first();

            if (! $visit) {
                continue;
            }

            $comment = trim((string) ($row['comment'] ?? '')) ?: null;

            Feedback::updateOrCreate(
                ['user_id' => Auth::id(), 'visit_history_id' => $visit->id],
                [
                    'church_id'  => $stop->church_id,
                    'rating'     => (int) $row['rating'],
                    'comment'    => $comment,
                    'status'     => $comment ? 'Pending' : 'Approved',
                    'created_at' => now(),
                ]
            );
            $saved++;
        }

        // The reminder in the bell has done its job.
        if ($saved) {
            Notification::where('user_id', Auth::id())->where('dedupe_key', 'rate:'.$itinerary->id)->delete();
        }

        return response()->json(['ok' => true, 'saved' => $saved]);
    }

    public function markVisited(Request $request): JsonResponse
    {
        $data = $request->validate([
            'stop_id' => ['required', 'integer', 'exists:itinerary_stops,id'],
            'undo'    => ['nullable', 'boolean'],
            'auto'    => ['nullable', 'boolean'],
        ]);

        $stop = ItineraryStop::with('itinerary')->findOrFail($data['stop_id']);
        Gate::authorize('update', $stop->itinerary);

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

                // And the "You've arrived" note it left in the bell.
                Notification::where('user_id', Auth::id())
                    ->where('dedupe_key', 'arrive:'.$stop->id)
                    ->delete();

                // Back to "In progress", or to "Upcoming" if nothing is left visited.
                $anyLeft = ItineraryStop::where('itinerary_id', $stop->itinerary_id)->where('is_visited', true)->exists();
                $stop->itinerary->update(['status' => $anyLeft ? 'Active' : 'Upcoming', 'updated_at' => now()]);
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
                // The first church visited starts the trip.
                if ($stop->itinerary->status !== 'Active') {
                    $stop->itinerary->update(['status' => 'Active', 'updated_at' => now()]);
                }

                return false;
            }

            $stop->itinerary->update(['status' => 'Completed', 'updated_at' => now()]);

            // The profile counters are derived from itineraries and
            // visit_history now, so there is nothing left to increment here.

            return true;
        });

        /* An automatic check-in (the devotee walked up to the church while
           the pilgrimage screen was open) also leaves a note in the bell, so
           someone who was praying with the phone in a pocket can see later
           that it was recorded - and what is next. */
        if ($request->boolean('auto')) {
            $this->notifyArrival($stop, $allDone);
        }

        /* The last stop: a reminder in the bell to rate the churches, for
           whoever presses "Later" on the rating sheet or closes the app. */
        if ($allDone) {
            Notification::updateOrCreate(
                ['user_id' => Auth::id(), 'dedupe_key' => 'rate:'.$stop->itinerary_id],
                [
                    'type'       => 'feedback',
                    'title'      => __('giya.plan.rate_note_title', ['trip' => $stop->itinerary->name]),
                    'message'    => __('giya.plan.rate_note_body'),
                    'url'        => route('plan.show', ['itinerary' => $stop->itinerary_id, 'rate' => 1]),
                    'is_read'    => false,
                    'created_at' => now(),
                ]
            );
        }

        return response()->json(['ok' => true, 'all_done' => $allDone]);
    }

    private function notifyArrival(ItineraryStop $stop, bool $allDone): void
    {
        $stop->loadMissing('itinerary', 'church');
        $church = $stop->church?->name ?? __('giya.map.this_church');

        $next = $allDone ? null : ItineraryStop::with('church')
            ->where('itinerary_id', $stop->itinerary_id)
            ->where('is_visited', false)
            ->orderBy('stop_order')
            ->first();

        Notification::where('user_id', Auth::id())->where('dedupe_key', 'arrive:'.$stop->id)->delete();

        Notification::create([
            'user_id'    => Auth::id(),
            'type'       => 'itinerary',
            'title'      => __('giya.plan.arrive_note_title', ['church' => $church]),
            'message'    => $allDone
                ? __('giya.plan.arrive_note_last', ['trip' => $stop->itinerary->name])
                : __('giya.plan.arrive_note_next', ['next' => $next?->church?->name ?? '']),
            'url'        => route('plan.show', $stop->itinerary_id),
            'dedupe_key' => 'arrive:'.$stop->id,
            'is_read'    => false,
            'created_at' => now(),
        ]);
    }

    public function destroy(Itinerary $itinerary): RedirectResponse
    {
        Gate::authorize('delete', $itinerary);

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
}
