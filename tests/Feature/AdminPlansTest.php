<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Admin Transactions > Plans & prices. */
class AdminPlansTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password_hash' => bcrypt('secret'),
            'role' => 'admin', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
        // Migrations may already add the standard plans; these tests use their own.
        $this->plan = SubscriptionPlan::create(['name' => 'Test Monthly', 'description' => 'One month.', 'price' => 99,
            'currency' => 'PHP', 'duration_days' => 30, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function pay(string $status): Transaction
    {
        $user = User::create(['name' => 'Dev', 'email' => uniqid().'@example.com', 'password_hash' => bcrypt('x'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);

        return Transaction::create(['user_id' => $user->id, 'plan_type_id' => $this->plan->id, 'amount' => $this->plan->price,
            'currency' => 'PHP', 'method' => 'Maya', 'status' => $status, 'reference_no' => uniqid('GIYA-'),
            'created_at' => now(), 'updated_at' => now()]);
    }

    private function form(array $over = []): array
    {
        return $over + ['name' => 'Test Monthly', 'description' => 'One month.', 'price' => '149.00', 'duration_days' => 30, 'is_active' => '1'];
    }

    public function test_the_plans_tab_lists_plans_with_their_payments(): void
    {
        $this->pay('Pending');

        $this->actingAs($this->admin)->get(route('admin.transactions', ['view' => 'plans']))->assertOk()
            ->assertSee('Plans &amp; prices', false)
            ->assertSee('₱99.00')
            ->assertSee('1 in progress')
            ->assertSee('id="planModal"', false);
        $this->actingAs($this->admin)->get(route('admin.transactions'))->assertOk()->assertSee('Plans &amp; prices', false);
    }

    public function test_a_new_price_applies_to_new_payments_only(): void
    {
        $pending = $this->pay('Pending');
        $paid = $this->pay('Paid');

        $this->actingAs($this->admin)->put(route('admin.plans.update', $this->plan), $this->form())
            ->assertRedirect(route('admin.transactions', ['view' => 'plans']))
            ->assertSessionHas('success', 'Saved Test Monthly: price ₱99.00 to ₱149.00 (new payments only).');

        $this->assertSame('149.00', $this->plan->fresh()->price);
        $this->assertSame('99.00', $pending->fresh()->amount);
        $this->assertSame('99.00', $paid->fresh()->amount);
    }

    public function test_a_price_change_is_in_the_activity_log(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            $this->markTestSkipped('No activity log table.');
        }

        $this->actingAs($this->admin)->put(route('admin.plans.update', $this->plan), $this->form());

        $this->assertTrue(ActivityLog::where('action', 'Changed a plan')->where('subject', 'Test Monthly')->exists());
    }

    public function test_prices_outside_the_limits_are_refused(): void
    {
        foreach (['5', '99999', '9.999', 'abc'] as $bad) {
            $this->actingAs($this->admin)->put(route('admin.plans.update', $this->plan), $this->form(['price' => $bad]))
                ->assertSessionHasErrors('price');
        }
        $this->assertSame('99.00', $this->plan->fresh()->price);
    }

    public function test_admins_can_add_hide_and_delete_an_unused_plan(): void
    {
        $this->actingAs($this->admin)->post(route('admin.plans.store'), $this->form(['name' => 'Test Yearly', 'price' => '899', 'duration_days' => 365]))
            ->assertSessionHas('success', 'Added Test Yearly at ₱899.00.');
        $yearly = SubscriptionPlan::where('name', 'Test Yearly')->firstOrFail();
        $this->assertTrue($yearly->is_active);

        $this->actingAs($this->admin)->patch(route('admin.plans.toggle', $yearly));
        $this->assertFalse($yearly->fresh()->is_active);
        $this->get(route('upgrade'))->assertViewHas('plans', fn ($plans) => ! $plans->contains('name', 'Test Yearly'));

        $this->actingAs($this->admin)->delete(route('admin.plans.destroy', $yearly));
        $this->assertNull($yearly->fresh());
    }

    public function test_a_plan_with_payments_cannot_be_deleted(): void
    {
        $this->pay('Paid');

        $this->actingAs($this->admin)->delete(route('admin.plans.destroy', $this->plan))
            ->assertSessionHas('error');
        $this->assertNotNull($this->plan->fresh());
    }

    public function test_two_plans_cannot_share_a_name(): void
    {
        $this->actingAs($this->admin)->post(route('admin.plans.store'), $this->form())
            ->assertSessionHasErrors('name');
    }

    public function test_devotees_cannot_change_prices(): void
    {
        $user = User::create(['name' => 'Plain', 'email' => 'p@example.com', 'password_hash' => bcrypt('x'),
            'role' => 'devotee', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->put(route('admin.plans.update', $this->plan), $this->form(['price' => '20']));
        $this->assertSame('99.00', $this->plan->fresh()->price);
    }
}
