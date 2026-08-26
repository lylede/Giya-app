<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('home', $this->homeData());
    }

    public function landing(): View|\Illuminate\Http\RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        return view('home', $this->homeData());
    }

    public function search(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < 2) {
            return response()->json(['ok' => true, 'items' => []]);
        }

        $items = Church::with('churchCategory')
            ->active()
            ->where(function ($q) use ($term) {
                $like = '%'.$term.'%';
                $q->where('name', 'ilike', $like)
                  ->orWhere('location', 'ilike', $like)
                  ->orWhereHas('churchCategory', fn ($c) => $c->where('name', 'ilike', $like));
            })
            ->orderBy('name')
            ->take(8)
            ->get()
            ->map(fn (Church $c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'location' => $c->location,
                'category' => $c->category,
                'image'    => $c->imagePath(),
                'open'     => $c->isOpenNow(),
                'url'      => route('churches.show', $c),
            ]);

        return response()->json(['ok' => true, 'items' => $items, 'term' => $term]);
    }

    private function homeData(): array
    {
        return [
            // rating is derived now, so sort on the averaged feedback column.
            'featured'  => Church::active()->featured()->orderByRating()->take(4)->get(),
            'upcoming'  => Schedule::with('church')->orderBy('schedule_date')->take(5)->get(),
            'stats'     => [
                'churches' => Church::active()->count(),
                'cities'   => Church::active()->distinct('location')->count('location'),
            ],
        ];
    }
}
