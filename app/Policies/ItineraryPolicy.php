<?php

namespace App\Policies;

use App\Models\Itinerary;
use App\Models\User;

/**
 * An itinerary belongs to the devotee who made it, and to no one else.
 *
 * Found automatically by Laravel (App\Policies\{Model}Policy). Controllers
 * call Gate::authorize('view', $itinerary) instead of comparing user ids by
 * hand, so the rule lives in one place.
 */
class ItineraryPolicy
{
    public function view(User $user, Itinerary $itinerary): bool
    {
        return (int) $itinerary->user_id === (int) $user->id;
    }

    /** Ticking a stop off, or undoing it. */
    public function update(User $user, Itinerary $itinerary): bool
    {
        return $this->view($user, $itinerary);
    }

    public function delete(User $user, Itinerary $itinerary): bool
    {
        return $this->view($user, $itinerary);
    }
}
