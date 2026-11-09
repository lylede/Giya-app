<?php

namespace App\Http\Controllers;

use App\Models\Itinerary;
use App\Models\ItineraryStop;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Group pilgrimages: share an itinerary with family or a parish group.
 *
 * The owner makes a private link. Anyone with it sees the route (the name,
 * date and churches in order, and the owner's first name; nothing else). A
 * signed-in pilgrim can save a copy and walk it with their own progress.
 * The owner can stop sharing at any time; the old link then stops working.
 */
class SharedItineraryController extends Controller
{
    /** Make (or return) the link. */
    public function share(Itinerary $itinerary): JsonResponse
    {
        Gate::authorize('update', $itinerary);

        if (! $itinerary->share_token) {
            $itinerary->update(['share_token' => Str::random(32)]);
        }

        return response()->json(['ok' => true, 'url' => route('shared.show', $itinerary->share_token)]);
    }

    public function unshare(Itinerary $itinerary): JsonResponse
    {
        Gate::authorize('update', $itinerary);

        $itinerary->update(['share_token' => null]);

        return response()->json(['ok' => true]);
    }

    public function show(string $token): View
    {
        $itinerary = $this->find($token);

        // A guest who signs in from here comes back to this route to save it.
        if (! Auth::check()) {
            session()->put('url.intended', url()->current());
        }

        return view('plan.shared', [
            'itinerary' => $itinerary,
            'stops'     => $itinerary->stops()->with('church')->get()->filter(fn ($s) => $s->church),
            'owner'     => $itinerary->user?->firstName() ?? 'A pilgrim',
            'mine'      => Auth::id() === $itinerary->user_id,
            'atLimit'   => Auth::check() && Itinerary::atFreeLimit(Auth::user()),
        ]);
    }

    /** Save a copy to my itineraries, with my own progress. */
    public function copy(string $token): RedirectResponse
    {
        $source = $this->find($token);

        $copy = DB::transaction(function () use ($source) {
            $user = User::whereKey(Auth::id())->lockForUpdate()->first();
            if (Itinerary::atFreeLimit($user)) {
                return null;
            }

            $copy = Itinerary::create([
                'user_id'           => $user->id,
                'itinerary_type_id' => $source->itinerary_type_id,
                'name'              => Str::limit($source->name, 190, ''),
                'status'            => 'Upcoming',
                'schedule_date'     => $source->schedule_date?->isPast() ? null : $source->schedule_date,
                'notes'             => $source->notes,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            $order = 1;
            foreach ($source->stops()->whereHas('church', fn ($q) => $q->where('is_active', true))->orderBy('stop_order')->get() as $stop) {
                ItineraryStop::create([
                    'itinerary_id' => $copy->id, 'church_id' => $stop->church_id,
                    'stop_order' => $order++, 'is_visited' => false,
                ]);
            }

            return $copy;
        });

        if (! $copy) {
            return back()->with('error', __('giya.share.limit'));
        }

        return redirect()->route('plan.show', $copy)->with('success', __('giya.share.copied'));
    }

    protected function find(string $token): Itinerary
    {
        abort_unless(strlen($token) === 32 && ctype_alnum($token), 404);

        return Itinerary::where('share_token', $token)->firstOrFail();
    }
}
