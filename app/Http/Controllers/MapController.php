<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\ChurchCategory;
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

    public function index(): View
    {
        $churches = Church::with('churchCategory', 'primaryImage')
            /* One subquery in the main statement rather than an exists()
               per church while building the marker list - that was a query
               for every destination on a page that shows all of them. */
            ->withExists(['schedules as has_mass' => fn ($q) => $q->where('event_type', 'Mass')])
            ->active()
            ->orderBy('name')
            ->get();

        return view('map', [
            'churches'   => $churches,
            'categories' => ChurchCategory::orderBy('name')
                ->whereNotIn('name', self::CHIPS_HIDDEN)
                ->pluck('name')
                ->prepend('All')
                ->all(),

            // Plain array for Leaflet - no Eloquent objects cross into JS.
            'markers' => $churches
                ->filter(fn (Church $c) => $c->latitude && $c->longitude)
                ->map(fn (Church $c) => [
                    'id'       => $c->id,
                    'details'  => route('churches.show', $c),
                    'name'     => $c->name,
                    'location' => $c->location,
                    'category' => $c->category,
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
