<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\ChurchCategory;
use Illuminate\View\View;

class MapController extends Controller
{
    public function index(): View
    {
        $churches = Church::with('churchCategory', 'primaryImage')
            ->active()
            ->orderBy('name')
            ->get();

        return view('map', [
            'churches'   => $churches,
            'categories' => ChurchCategory::orderBy('name')->pluck('name')->prepend('All')->all(),

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
                    'masses'   => $c->schedules()->where('event_type', 'Mass')->exists(),
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
