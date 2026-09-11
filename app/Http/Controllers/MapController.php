<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Itinerary;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MapController extends Controller
{
    /**
     * Categories that exist on churches but do not get a chip of their own.
     *
     * Chapels are not destinations a pilgrim travels to, and Heritage
     * overlapped every other category. Removing the chip is not the same as
     * removing the category: a church filed under either keeps it, still
     * carries it on its own page, and still appears on the map and under All.
     * It simply stops taking a slot in a filter bar that has to fit on a
     * phone.
     */
    private const CHIPS_HIDDEN = ['Chapel', 'Heritage'];

    /**
     * ?plan= values, and the itinerary type each one plans.
     *
     * The keys are what a link may carry; the values must match
     * itinerary_types.name, because that is what plan.store validates.
     */
    private const PLAN_TYPES = [
        '1'      => 'Custom',
        'custom' => 'Custom',
        'visita' => 'Visita Iglesia',
    ];

    public function index(Request $request): View
    {
        /* The map doubles as the custom itinerary planner. ?plan=1 puts the
           trip's details above it and turns the selection tray into a save,
           so choosing destinations happens where the distances are visible
           instead of on a separate screen with a list. */
        /* Only for someone who could actually save. A guest gets the ordinary
           map rather than a details form that would bounce them to a login
           and lose everything they had picked. */
        /* Signed in is what decides whether the trip's details can be on the
           page at all. They are rendered for anyone who could save - hidden
           until wanted - so Plan Route on the ordinary map opens them where
           the devotee is standing instead of navigating to a second screen
           and making them pick their churches again. ?plan=1 only decides
           whether they start open. */
        $canPlan  = $request->user() !== null;

        /* Which kind of trip the map is planning.

           A Visita Iglesia route that came here to be looked at used to lose
           its name: the map only ever planned a Custom trip, so a devotee who
           adjusted their seven churches on the map saved a Custom itinerary
           and had no way back to the planner they came from. The type travels
           in the URL, and the trip keeps it wherever it is saved. */
        $planType = self::PLAN_TYPES[strtolower((string) $request->query('plan'))] ?? null;

        /* Arriving with a route is arriving mid-plan, whoever sent it - the
           churches are already chosen, so the bar has no reason to be shut. */
        $planning = $canPlan && ($planType !== null || $request->filled('stops'));
        $planType ??= 'Custom';
        $churches = Church::with('churchCategory', 'primaryImage')
            /* One subquery in the main statement rather than an exists()
               per church while building the marker list - that was a query
               for every destination on a page that shows all of them. */
            ->withExists(['schedules as has_mass' => fn ($q) => $q->where('event_type', 'Mass')])
            ->active()
            ->orderBy('name')
            ->get();

        return view('map', [
            'planning'   => $planning,
            'canPlan'    => $canPlan,
            'planType'   => $planType,
            'isVisita'   => $planType === 'Visita Iglesia',
            'atLimit'    => $canPlan && Itinerary::atFreeLimit($request->user()),
            'churches'   => $churches,
            'categories' => ChurchCategory::orderBy('name')
                ->whereNotIn('name', self::CHIPS_HIDDEN)
                ->pluck('name')
                ->map(function (string $name) {
                    if (in_array($name, ['Parish', 'Parishes'], true)) {
                        return 'Church';
                    }

                    if (stripos($name, 'Shrine') !== false) {
                        return 'Shrine';
                    }

                    return $name;
                })
                ->unique()
                ->prepend('All')
                ->all(),

            /* Whether the Major chip has anything to show. A filter that is
               always empty is worse than a filter that is not there: the
               devotee presses it, sees nothing, and learns the app is broken
               rather than that no church has been marked yet. */
            'hasMajors'  => $churches->contains(fn (Church $c) => $c->is_major),

            // Plain array for Leaflet - no Eloquent objects cross into JS.
            'markers' => $churches
                ->filter(fn (Church $c) => $c->latitude && $c->longitude)
                ->map(fn (Church $c) => [
                    'id'       => $c->id,
                    'details'  => route('churches.show', $c),
                    'name'     => $c->name,
                    'location' => $c->location,

                    /* The town, and whether this is its principal church.
                       Both are read by the Major chip - the flag to filter
                       by, the town to say which one each result stands for. */
                    'town'     => $c->municipality,
                    'major'    => (bool) $c->is_major,
                    'category' => (function () use ($c) {
                        $name = $c->category;

                        if (in_array($name, ['Parish', 'Parishes'], true)) {
                            return 'Church';
                        }

                        if (stripos($name, 'Shrine') !== false) {
                            return 'Shrine';
                        }

                        return $name;
                    })(),
                    'lat'      => (float) $c->latitude,
                    'lng'      => (float) $c->longitude,
                    'image'    => $c->imagePath(),
                    'color'    => $c->color(),
                    'rating'   => (float) $c->rating,
                    'hours'    => $c->hours_label,
                    'open'     => $c->isOpenNow(),

                    // The search box reads this: typing "mass" or "misa"
                    // filters to churches that hold one, which is what the
                    // Mass Schedule chip used to do.
                    'masses'   => (bool) $c->has_mass,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function show(Church $church): View
    {
        abort_unless($church->is_active, 404);

        $church->load([
            'churchCategory',
            'images',
            'feedback' => fn ($query) => $query
                ->approved()
                ->with('user')
                ->latest('created_at')
                ->latest('id'),
            'schedules' => fn ($query) => $query
                ->orderBy('start_time'),
        ]);

        return view('churches.show', compact('church'));
    }
}
