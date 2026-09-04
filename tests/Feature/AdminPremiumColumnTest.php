<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The admin user list has to say who is on Premium, and has to do it without
 * asking the database once per row.
 */
class AdminPremiumColumnTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionPlan $monthly;

    protected function setUp(): void
    {
        parent::setUp();

        $this->monthly = SubscriptionPlan::create([
            'name' => 'Pilgrim Monthly', 'description' => 'One month.',
            'price' => 99.00, 'currency' => 'PHP', 'duration_days' => 30,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function devotee(string $name, string $role = 'user'): User
    {
        return User::create([
            'name' => $name, 'email' => str($name)->slug().'@example.com',
            'password_hash' => bcrypt('secret'), 'role' => $role,
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function paid(User $user, ?SubscriptionPlan $plan = null, ?string $when = null): Transaction
    {
        return Transaction::create([
            'user_id' => $user->id, 'plan_type_id' => ($plan ?? $this->monthly)->id,
            'amount' => 99.00, 'currency' => 'PHP', 'method' => 'Maya',
            'provider' => 'maya', 'status' => 'Paid',
            'reference_no' => 'GIYA-'.uniqid(),
            'created_at' => $when ?? now(), 'processed_at' => $when ?? now(),
            'updated_at' => $when ?? now(),
        ]);
    }

    public function test_the_list_marks_premium_devotees_and_leaves_the_rest_free(): void
    {
        $admin   = $this->devotee('Admin One', 'admin');
        $premium = $this->devotee('Paid Pilgrim');
        $free    = $this->devotee('Free Pilgrim');

        $this->paid($premium);

        $response = $this->actingAs($admin)->get(route('admin.users'));

        $response->assertOk()->assertSee('Premium')->assertSee('Free');

        // 1 of 3 accounts, and the admin and the free devotee are not counted.
        $this->assertSame(
            [$premium->id],
            array_keys(Transaction::premiumExpiryByUser())
        );
    }

    public function test_an_expired_pass_is_not_premium_any_more(): void
    {
        $expired = $this->devotee('Lapsed Pilgrim');

        $this->paid($expired, when: now()->subDays(31)->toDateTimeString());

        $this->assertSame([], Transaction::premiumExpiryByUser());
        $this->assertFalse($expired->fresh()->is_premium);
    }

    public function test_a_pending_or_failed_transaction_is_not_premium(): void
    {
        foreach (['Pending', 'Failed', 'Refunded'] as $status) {
            $user = $this->devotee('Devotee '.$status);

            $this->paid($user)->update(['status' => $status]);
        }

        $this->assertSame([], Transaction::premiumExpiryByUser());
    }

    /** A devotee who renews keeps the later expiry, not the earlier one. */
    public function test_two_passes_report_the_later_expiry(): void
    {
        $user = $this->devotee('Renewer');

        $this->paid($user, when: now()->subDays(10)->toDateTimeString());
        $this->paid($user);

        $expiry = Transaction::premiumExpiryByUser();

        $this->assertCount(1, $expiry);
        $this->assertSame(
            now()->addDays(30)->toDateString(),
            $expiry[$user->id]->toDateString()
        );
    }

    /** A pass bought on a plan that has since been retired does not count. */
    public function test_a_retired_plan_stops_conferring_premium(): void
    {
        $retired = SubscriptionPlan::create([
            'name' => 'Pilgrim Annual', 'description' => 'Retired.',
            'price' => 899.00, 'currency' => 'PHP', 'duration_days' => 365,
            'is_active' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $user = $this->devotee('Old Subscriber');
        $this->paid($user, $retired);

        $this->assertSame([], Transaction::premiumExpiryByUser());
    }

    /**
     * The reason premiumExpiryByUser exists. Reading $u->is_premium in the
     * Blade loop costs a query per row plus a lazy plan load inside each
     * isStillValid(), so a page of users would run well over a hundred
     * queries for one column. The count here must not grow with the number
     * of devotees on the page.
     */
    public function test_the_column_does_not_add_a_query_per_row(): void
    {
        $admin = $this->devotee('Admin Two', 'admin');

        // Distinct created_at values, because the list is ordered by it. Rows
        // sharing a timestamp order arbitrarily, which would make which
        // devotees land on page one - and therefore the query count - vary
        // between runs.
        foreach (range(1, 30) as $n) {
            $user = $this->devotee('Pilgrim '.$n);
            $user->update(['created_at' => now()->subMinutes($n)]);

            if ($n % 2 === 0) {
                $this->paid($user);
            }
        }

        // One throwaway request first. The locale middleware reads the admin's
        // preferences row on the first hit and has it cached afterwards, which
        // would otherwise show up as an extra query in whichever measurement
        // happened to run first.
        $this->actingAs($admin)->get(route('admin.users'))->assertOk();

        $count = function (int $perPage) use ($admin): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($admin)->get(route('admin.users', ['per_page' => $perPage]))->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $fiveRows      = $count(5);
        $twentyFiveRows = $count(25);

        // Five times the rows on the page. A per-row lookup would show up here
        // as twenty extra queries; a single grouped lookup costs the same for
        // either page size.
        $this->assertSame(
            $fiveRows,
            $twentyFiveRows,
            "A 5-row page ran $fiveRows queries and a 25-row page ran $twentyFiveRows - "
                .'the premium column is querying per row.'
        );
    }
}
