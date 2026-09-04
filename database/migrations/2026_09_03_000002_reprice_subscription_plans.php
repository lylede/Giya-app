<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ERD entity: SubscriptionPlans (data)
 *
 * Brings the seeded plans in line with the business model canvas:
 *
 *   Free            3 itineraries per account, no payment
 *   Pilgrim Weekly  PHP 49  /  7 days   - every feature
 *   Pilgrim Monthly PHP 99  / 30 days   - every feature
 *
 * There is no annual tier.
 *
 * Why a migration rather than only editing the seeder: the seeder uses
 * firstOrCreate, so it will not touch a plan that already exists. Every
 * database that has already been seeded - Lyle's, Clint's, Ian's, Josh's -
 * would keep the old 899 annual row and the old prices. This runs once on
 * each of them and fixes it.
 *
 * Retired plans are DEACTIVATED, never deleted. transactions.plan_type_id is
 * restrictOnDelete, so a plan with any transaction against it cannot be
 * removed - and should not be, because deleting it would erase what a past
 * transaction was actually for. is_active = false keeps the history intact
 * while hiding the plan from the upgrade page.
 */
return new class extends Migration
{
    /** The plans the canvas describes, by name. */
    private const PLANS = [
        [
            'name'          => 'Pilgrim Weekly',
            'description'   => 'Every GIYA feature, unlimited itineraries, for one week.',
            'price'         => 49.00,
            'duration_days' => 7,
        ],
        [
            'name'          => 'Pilgrim Monthly',
            'description'   => 'Every GIYA feature, unlimited itineraries, for one month.',
            'price'         => 99.00,
            'duration_days' => 30,
        ],
    ];

    public function up(): void
    {
        $now = now();

        // 'Additional Itinerary Access' was already 99 for 30 days - the same
        // terms as Pilgrim Monthly. Renaming rather than retiring it keeps any
        // transaction already pointing at it attached to a plan that still
        // describes what was bought.
        DB::table('subscription_plans')
            ->where('name', 'Additional Itinerary Access')
            ->update(['name' => 'Pilgrim Monthly', 'updated_at' => $now]);

        foreach (self::PLANS as $plan) {
            $exists = DB::table('subscription_plans')->where('name', $plan['name'])->exists();

            if ($exists) {
                DB::table('subscription_plans')
                    ->where('name', $plan['name'])
                    ->update($plan + ['currency' => 'PHP', 'is_active' => true, 'updated_at' => $now]);
            } else {
                DB::table('subscription_plans')->insert(
                    $plan + ['currency' => 'PHP', 'is_active' => true,
                             'created_at' => $now, 'updated_at' => $now]
                );
            }
        }

        // Anything else stops being offered. That covers 'Pilgrim Annual',
        // which the canvas has no tier for, and the 'Free' row - free is the
        // absence of a subscription, not something anyone buys, and while it
        // sat there as an active plan the upgrade page listed it as a PHP 0.00
        // card with a Pay button.
        DB::table('subscription_plans')
            ->whereNotIn('name', array_column(self::PLANS, 'name'))
            ->update(['is_active' => false, 'updated_at' => $now]);
    }

    public function down(): void
    {
        $now = now();

        DB::table('subscription_plans')
            ->where('name', 'Pilgrim Monthly')
            ->update(['name' => 'Additional Itinerary Access',
                      'description' => 'Unlimited active itineraries for 30 days.',
                      'updated_at' => $now]);

        // Only removable while nothing was ever bought on it - the foreign key
        // is restrictOnDelete, and a transaction must keep pointing at the plan
        // it paid for. Otherwise it is just switched off.
        $weekly = DB::table('subscription_plans')->where('name', 'Pilgrim Weekly')->first();

        if ($weekly) {
            $sold = DB::table('transactions')->where('plan_type_id', $weekly->id)->exists();

            $sold
                ? DB::table('subscription_plans')->where('id', $weekly->id)
                    ->update(['is_active' => false, 'updated_at' => $now])
                : DB::table('subscription_plans')->where('id', $weekly->id)->delete();
        }

        DB::table('subscription_plans')
            ->whereIn('name', ['Free', 'Additional Itinerary Access', 'Pilgrim Annual'])
            ->update(['is_active' => true, 'updated_at' => $now]);
    }
};
