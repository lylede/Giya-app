<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchCategory;
use App\Models\Favorite;
use App\Models\Feedback;
use App\Models\Notification;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Whether anything actually creates a notification.
 *
 * Nothing did. Notification::notify() was called from exactly one place in the
 * codebase - the seeder - so every notification a devotee had ever seen was
 * demo data from db:seed, and an admin could publish a feast day to silence.
 * The panel worked perfectly; it was a reader for a table with no writer.
 *
 * What is asserted here is mostly the restraint rather than the sending. The
 * easy version of this feature notifies on every save, which trains devotees
 * to ignore the bell within a week.
 */
class NotificationProducerTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $admin;

    /** Memoised: two saves in one test would otherwise be two admins with
     *  the same email, and devotees.email is unique. */
    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name' => 'Admin', 'email' => 'admin@example.com',
            'password_hash' => bcrypt('secret'), 'role' => 'admin',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function devotee(string $email): User
    {
        return User::create([
            'name' => 'Devotee', 'email' => $email,
            'password_hash' => bcrypt('secret'), 'role' => 'devotee',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function church(): Church
    {
        return $this->church ??= Church::create([
            'category_id'  => ChurchCategory::create(['name' => 'Church'])->id,
            'name'         => 'Basilica del Santo Nino',
            'municipality' => 'Cebu City',
            'latitude' => 10.29, 'longitude' => 123.90, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array<int, User> devotees who have favourited the church */
    private function followers(int $n): array
    {
        $out = [];

        for ($i = 1; $i <= $n; $i++) {
            $u = $this->devotee("devotee{$i}@example.com");
            Favorite::create([
                'user_id' => $u->id, 'church_id' => $this->church()->id,
                'is_active' => true, 'created_at' => now(),
            ]);
            $out[] = $u;
        }

        return $out;
    }

    /** Named savePost, not post: TestCase::post() is the HTTP helper and
     *  overriding it with a narrower signature is a fatal error. */
    private function savePost(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())->post(route('admin.schedules.store'), array_merge([
            'church_id'  => $this->church()->id,
            'event_name' => 'Sinulog Fiesta',
            'event_type' => 'Feast Day',
            'schedule_date' => now()->addDays(20)->toDateString(),
            'status'     => 'Published',
        ], $overrides));
    }

    public function test_publishing_a_feast_day_notifies_everyone_who_favourited_the_church(): void
    {
        [$a, $b] = $this->followers(2);
        $stranger = $this->devotee('nobody@example.com');

        $this->savePost()->assertRedirect();

        $this->assertSame(1, Notification::where('user_id', $a->id)->count());
        $this->assertSame(1, Notification::where('user_id', $b->id)->count());

        // Not broadcast to the whole devotee list.
        $this->assertSame(0, Notification::where('user_id', $stranger->id)->count());

        $row = Notification::where('user_id', $a->id)->first();
        $this->assertSame('schedule', $row->type);
        $this->assertSame('Sinulog Fiesta', $row->title);
        $this->assertStringContainsString('Basilica del Santo Nino', $row->message);
        $this->assertFalse($row->is_read);

        // Tapping it has to go somewhere.
        $this->assertStringContainsString('/churches/', $row->url);
    }

    public function test_a_draft_tells_nobody(): void
    {
        $this->followers(2);

        $this->savePost(['status' => 'Draft'])->assertRedirect();

        $this->assertSame(0, Notification::count());
    }

    public function test_a_date_in_the_past_tells_nobody(): void
    {
        $this->followers(2);

        // Correcting last year's entry is not an announcement.
        $this->savePost(['schedule_date' => now()->subMonth()->toDateString()])->assertRedirect();

        $this->assertSame(0, Notification::count());
    }

    public function test_an_un_favourited_church_tells_nobody(): void
    {
        $u = $this->devotee('lapsed@example.com');

        Favorite::create([
            'user_id' => $u->id, 'church_id' => $this->church()->id,
            'is_active' => false, 'created_at' => now(),
        ]);

        $this->savePost()->assertRedirect();

        $this->assertSame(0, Notification::count());
    }

    public function test_saving_without_changing_anything_tells_nobody_again(): void
    {
        $this->followers(2);
        $this->savePost()->assertRedirect();

        $this->assertSame(2, Notification::count());

        $schedule = Schedule::first();

        // The admin opens the form and presses Save. Nothing differs.
        $this->savePost(['schedule_id' => $schedule->id])->assertRedirect();

        $this->assertSame(2, Notification::count(), 'a no-op save notified people again');
    }

    public function test_a_note_changing_tells_nobody_but_a_date_moving_does(): void
    {
        $this->followers(2);
        $this->savePost()->assertRedirect();

        $schedule = Schedule::first();

        $this->savePost(['schedule_id' => $schedule->id, 'notes' => 'Ask the parish office.'])->assertRedirect();
        $this->assertSame(2, Notification::count(), 'an edited note notified people');

        $this->savePost([
            'schedule_id'   => $schedule->id,
            'notes'         => 'Ask the parish office.',
            'schedule_date' => now()->addDays(25)->toDateString(),
        ])->assertRedirect();

        $this->assertSame(4, Notification::count(), 'a moved date did not notify anyone');

        $this->assertStringStartsWith(
            'Updated:',
            Notification::orderByDesc('id')->first()->message,
            'an edit reads the same as a new announcement'
        );
    }

    public function test_the_admin_is_told_how_many_were_reached(): void
    {
        $this->followers(3);

        $this->savePost()->assertSessionHas('success', fn ($m) => str_contains($m, '3 devotees were notified'));
    }

    /* ------------------------------ feedback ----------------------------- */

    private function review(string $status = 'Pending'): Feedback
    {
        $author = $this->devotee('author@example.com');

        return Feedback::create([
            'user_id' => $author->id, 'church_id' => $this->church()->id,
            'rating' => 5, 'comment' => 'Beautiful.', 'status' => $status,
            'created_at' => now(),
        ]);
    }

    public function test_approving_a_review_tells_its_author(): void
    {
        $review = $this->review();

        $this->actingAs($this->admin())
            ->patch(route('admin.feedback.update', $review), ['status' => 'Approved'])
            ->assertRedirect();

        $row = Notification::where('user_id', $review->user_id)->first();

        $this->assertNotNull($row, 'approving a review told nobody');
        $this->assertSame('feedback', $row->type);
    }

    public function test_re_approving_does_not_tell_the_author_twice(): void
    {
        $review = $this->review('Approved');

        $this->actingAs($this->admin())
            ->patch(route('admin.feedback.update', $review), ['status' => 'Approved'])
            ->assertRedirect();

        $this->assertSame(0, Notification::count());
    }

    /* ------------------------------ premium ------------------------------ */

    public function test_paying_for_premium_tells_the_devotee_when_it_runs_out(): void
    {
        $devotee = $this->devotee('buyer@example.com');

        $plan = \App\Models\SubscriptionPlan::create([
            'name' => 'Pilgrim Plus', 'price' => 199, 'currency' => 'PHP',
            'duration_days' => 30, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $t = \App\Models\Transaction::create([
            'user_id' => $devotee->id, 'plan_type_id' => $plan->id,
            'amount' => 199, 'currency' => 'PHP', 'method' => 'Maya',
            'provider' => 'Maya', 'status' => 'Pending',
            'reference_no' => 'PB-TEST01',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertTrue($t->settle('Paid'));

        $row = Notification::where('user_id', $devotee->id)->first();

        $this->assertNotNull($row, 'paying for premium told nobody');
        $this->assertSame('system', $row->type);
        $this->assertStringContainsString('Pilgrim Plus', $row->message);

        // The date it runs out, because that is the part worth keeping.
        $this->assertStringContainsString(now()->addDays(30)->format('M j, Y'), $row->message);
    }

    public function test_a_failed_payment_congratulates_nobody(): void
    {
        $devotee = $this->devotee('failed@example.com');

        $plan = \App\Models\SubscriptionPlan::create([
            'name' => 'Pilgrim Plus', 'price' => 199, 'currency' => 'PHP',
            'duration_days' => 30, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $t = \App\Models\Transaction::create([
            'user_id' => $devotee->id, 'plan_type_id' => $plan->id,
            'amount' => 199, 'currency' => 'PHP', 'method' => 'Maya',
            'provider' => 'Maya', 'status' => 'Pending',
            'reference_no' => 'PB-TEST02',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $t->settle('Failed');

        $this->assertSame(0, Notification::count());
    }

    public function test_settling_an_already_paid_transaction_tells_nobody_twice(): void
    {
        $devotee = $this->devotee('twice@example.com');

        $plan = \App\Models\SubscriptionPlan::create([
            'name' => 'Pilgrim Plus', 'price' => 199, 'currency' => 'PHP',
            'duration_days' => 30, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $t = \App\Models\Transaction::create([
            'user_id' => $devotee->id, 'plan_type_id' => $plan->id,
            'amount' => 199, 'currency' => 'PHP', 'method' => 'Maya',
            'provider' => 'Maya', 'status' => 'Pending',
            'reference_no' => 'PB-TEST03',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $t->settle('Paid');

        /* The webhook and the return from checkout both call settle(), and
           they race. The second one must be a no-op. */
        $t->settle('Paid');

        $this->assertSame(1, Notification::count());
    }

    public function test_flagging_a_review_tells_nobody(): void
    {
        $review = $this->review();

        $this->actingAs($this->admin())
            ->patch(route('admin.feedback.update', $review), ['status' => 'Flagged'])
            ->assertRedirect();

        $this->assertSame(0, Notification::count());
    }
}
