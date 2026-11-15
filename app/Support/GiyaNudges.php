<?php

namespace App\Support;

/**
 * The short lines the bubble above the Giya robot shows, now and then, on
 * every page the robot is on. The first fits the page someone is on (the
 * map, the planner, their profile, a church); then it rotates through the
 * general ones.
 */
final class GiyaNudges
{
    /** @return array<int, string> */
    public static function lines(): array
    {
        $lead = match (true) {
            request()->routeIs('map')                     => __('giya.chat.nudge_map'),
            request()->routeIs('plan.*', 'itineraries.*') => __('giya.chat.nudge_plan'),
            request()->routeIs('profile', 'profile.*')    => __('giya.chat.nudge_profile'),
            request()->routeIs('churches.show')           => __('giya.chat.nudge_church'),
            default                                       => null,
        };

        return array_values(array_unique(array_filter(array_merge([$lead], (array) __('giya.chat.nudges')))));
    }
}
