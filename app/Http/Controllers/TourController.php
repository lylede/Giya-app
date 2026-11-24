<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The guided tour a new account sees after logging in: a dimmed screen with
 * a spotlight on one button at a time (home, map, trip screen, profile).
 *
 * Each page's tour shows once per account, on any device. Finishing a page
 * marks that page; Skip marks them all. "Show the tour again" clears the
 * list. Admins are not shown the tour.
 */
class TourController extends Controller
{
    public const TOURS = ['home', 'map', 'trip', 'profile'];

    public static function pending(?User $user, string $tour): bool
    {
        return $user !== null
            && ! $user->isAdmin()
            && in_array($tour, self::TOURS, true)
            && ! in_array($tour, $user->tours_done ?? [], true);
    }

    /** Sent by the page (a beacon) when a tour is finished or skipped. */
    public function done(Request $request): Response
    {
        $tour = (string) $request->input('tour');
        $names = $tour === 'all' ? self::TOURS : (in_array($tour, self::TOURS, true) ? [$tour] : []);

        if ($names) {
            $user = $request->user();
            $user->tours_done = array_values(array_unique(array_merge($user->tours_done ?? [], $names)));
            $user->save();
        }

        return response()->noContent();
    }

    public function replay(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->tours_done = [];
        $user->save();

        return redirect()->route('home')->with('success', __('giya.tour.replay_done'));
    }
}
