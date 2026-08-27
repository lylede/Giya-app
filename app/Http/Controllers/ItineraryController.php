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

    public function create(Request $request): View
    {
        $churches = Church::active()->orderBy('name')->get();

        /*
           Stops chosen on the map arrive as ?stops=3,7,1 - in the order the
           map worked out, which is the nearest-neighbour order it drew. That
           order is the useful part, so it is preserved rather than re-sorted.
        */
        $preset = collect(explode(',', (string) $request->query('stops')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->unique()
            ->map(fn ($id) => $churches->firstWhere('id', $id))
            ->filter()
            ->map(fn (Church $c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'location' => $c->location,
            ])
            ->values();

        return view('plan.create', [
            'churches' => $churches,
            'atLimit'  => $this->atLimit(),
            'preset'   => $preset,
        ]);
    }

    public function visita(): View
    {
        return view('plan.visita', [
            'churches' => Church::active()->orderBy('name')->get(),
            'atLimit'  => $this->atLimit(),
        ]);
    }

    public function index(): View
    {
        $itineraries = Itinerary::with('stops')
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        return view('plan.my-itineraries', [
            'itineraries' => $itineraries,
            'used'        => $itineraries->count(),
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
            $churchIds = Church::whereIn('name', $data['stops'])->pluck('id', 'name');

            $order = 1;
            foreach (array_values($data['stops']) as $churchName) {
                if (! isset($churchIds[$churchName])) {
                    continue;   // skip anything that is not a real destination
                }

                ItineraryStop::create([
                    'itinerary_id' => $itinerary->id,
                    'church_id'    => $churchIds[$churchName],
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
        $data = $request->validate(['stop_id' => ['required', 'integer', 'exists:itinerary_stops,id']]);

        $stop = ItineraryStop::with('itinerary')->findOrFail($data['stop_id']);
        $this->authorizeOwner($stop->itinerary);

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

        DB::transaction(function () use ($itinerary) {
            $itinerary->stops()->delete();
            $itinerary->delete();
        });

        return redirect()->route('plan.index')->with('success', 'Itinerary deleted.');
    }

    private function atLimit(): bool
    {
        $user = Auth::user();

        return ! $user->is_premium
            && Itinerary::where('user_id', $user->id)->count() >= self::FREE_LIMIT;
    }

    private function authorizeOwner(Itinerary $itinerary): void
    {
        abort_unless($itinerary->user_id === Auth::id(), 403);
    }
}
