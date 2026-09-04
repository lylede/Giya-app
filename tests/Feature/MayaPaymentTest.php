<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The payment flow, tested at the two points where getting it wrong costs
 * somebody money:
 *
 *   - the amount charged must come from the plan, not from the request
 *   - only Maya, asked directly with the secret key, may mark a row Paid
 *
 * Maya's webhooks are unsigned. That means the webhook endpoint is, in
 * practice, a public "please mark this Paid" button unless the payload is
 * treated as untrusted. Several tests below post exactly the sort of thing an
 * attacker would and assert that nothing moves.
 */
class MayaPaymentTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.maya.base_url'    => 'https://pg-sandbox.paymaya.com',
            'services.maya.public'      => 'pk-test',
            'services.maya.secret'      => 'sk-test',
            'services.maya.webhook_ips' => [],   // no IP filter in tests
        ]);

        // The business model canvas has two paid tiers and no annual one.
        // Monthly is the one the tests buy; weekly exists so the listing and
        // the best-value badge are exercised against a real second plan.
        $this->plan = SubscriptionPlan::create([
            'name' => 'Pilgrim Monthly', 'description' => 'Every feature, one month.',
            'price' => 99.00, 'currency' => 'PHP', 'duration_days' => 30,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        SubscriptionPlan::create([
            'name' => 'Pilgrim Weekly', 'description' => 'Every feature, one week.',
            'price' => 49.00, 'currency' => 'PHP', 'duration_days' => 7,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function devotee(string $name = 'Lyle'): User
    {
        return User::create([
            'name' => $name, 'email' => strtolower($name).'@example.com',
            'password_hash' => bcrypt('secret'), 'role' => 'devotee',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * A checkout creation that succeeds, plus a checkout lookup reply.
     *
     * The ids are generated per call rather than fixed, because Maya issues a
     * fresh checkoutId for every checkout and provider_checkout_id is unique.
     * An earlier version of this helper returned one hard-coded id; on SQLite
     * that made every checkout after the first 500 on the unique index while
     * the tests still passed, because none of them asserted the response.
     * PostgreSQL refused more loudly, which is how it was found.
     */
    private function fakeMaya(string $paymentStatus = 'PAYMENT_SUCCESS'): void
    {
        $issued = 0;

        Http::fake([
            '*/checkout/v1/checkouts' => function () use (&$issued) {
                $issued++;

                return Http::response([
                    'checkoutId'  => 'chk-'.$issued,
                    'redirectUrl' => 'https://pg-sandbox.paymaya.com/checkout/chk-'.$issued,
                ], 200);
            },

            // Answers for whichever checkout id was asked about, so a test with
            // several transactions in flight gets each one's own payment back.
            '*/checkout/v1/checkouts/*' => function ($request) use ($paymentStatus) {
                $id = basename((string) parse_url($request->url(), PHP_URL_PATH));

                return Http::response([
                    'id'                     => $id,
                    'paymentStatus'          => $paymentStatus,
                    'paymentDetails'         => ['paymentId' => 'pay-for-'.$id],
                    'requestReferenceNumber' => 'irrelevant',
                ], 200);
            },
        ]);
    }

    /* ── Starting a payment ────────────────────────────────────────────── */

    public function test_starting_a_checkout_writes_a_pending_transaction_and_redirects_to_maya(): void
    {
        $this->fakeMaya();
        $user = $this->devotee();

        $response = $this->actingAs($user)->post(route('upgrade.checkout', $this->plan));

        $response->assertRedirect('https://pg-sandbox.paymaya.com/checkout/chk-1');

        $transaction = Transaction::firstOrFail();

        $this->assertSame('Pending', $transaction->status);
        $this->assertSame('Maya', $transaction->method);
        $this->assertSame('maya', $transaction->provider);
        $this->assertSame('chk-1', $transaction->provider_checkout_id);
        $this->assertSame('99.00', (string) $transaction->amount);
        $this->assertNull($transaction->processed_at);
        $this->assertFalse($user->fresh()->is_premium, 'Pending must not confer Premium.');
    }

    /**
     * The amount is read off the plan, never off the request. If it were taken
     * from a form field, a devotee could post amount=1 and be Premium for a
     * peso. Posting exactly that must change nothing.
     */
    public function test_the_amount_comes_from_the_plan_not_the_request(): void
    {
        $this->fakeMaya();

        $this->actingAs($this->devotee())
            ->post(route('upgrade.checkout', $this->plan), [
                'amount' => 1, 'price' => 1, 'totalAmount' => 1,
            ])
            ->assertRedirect();   // a 500 here would otherwise pass silently

        $this->assertSame('99.00', (string) Transaction::firstOrFail()->amount);

        Http::assertSent(function ($request) {
            return $request['totalAmount']['value'] === 99.0;
        });
    }

    /** Maya's reference number field is capped at 36 characters. */
    public function test_the_reference_number_fits_mayas_limit_and_is_unique(): void
    {
        $this->fakeMaya();

        $references = [];

        foreach ([$this->devotee('One'), $this->devotee('Two'), $this->devotee('Three')] as $user) {
            $this->actingAs($user)
                ->post(route('upgrade.checkout', $this->plan))
                ->assertRedirect();
        }

        // Not just three reference numbers - three checkouts that survived the
        // round trip. Without this the unique index could reject the second and
        // third and the reference assertions below would still pass.
        $this->assertSame(3, Transaction::whereNotNull('provider_checkout_id')->count());

        foreach (Transaction::all() as $transaction) {
            $this->assertLessThanOrEqual(36, strlen($transaction->reference_no));
            $references[] = $transaction->reference_no;
        }

        $this->assertCount(3, array_unique($references));
    }

    public function test_maya_being_unreachable_fails_the_row_and_charges_nothing(): void
    {
        Http::fake(['*' => Http::response(['error' => 'nope'], 500)]);

        $user = $this->devotee();
        $this->actingAs($user)->post(route('upgrade.checkout', $this->plan))->assertRedirect();

        $this->assertSame('Failed', Transaction::firstOrFail()->status);
        $this->assertFalse($user->fresh()->is_premium);
    }

    /* ── Coming back from Maya ─────────────────────────────────────────── */

    public function test_a_successful_return_is_verified_against_maya_and_grants_premium(): void
    {
        $this->fakeMaya('PAYMENT_SUCCESS');
        $user = $this->devotee();

        $this->actingAs($user)->post(route('upgrade.checkout', $this->plan));
        $this->actingAs($user)->get(route('upgrade.success'))->assertRedirect(route('upgrade'));

        $transaction = Transaction::firstOrFail();

        $this->assertSame('Paid', $transaction->status);
        $this->assertSame('pay-for-chk-1', $transaction->provider_payment_id);
        $this->assertNotNull($transaction->processed_at);
        $this->assertTrue($user->fresh()->is_premium);
    }

    /**
     * The heart of it. A devotee who never pays can still type the success URL
     * into the address bar - the browser is theirs. What stops that is that
     * the landing page is not evidence: GIYA asks Maya, Maya says the payment
     * failed, and the row stays unpaid no matter which URL was visited.
     */
    public function test_landing_on_the_success_url_without_paying_grants_nothing(): void
    {
        $this->fakeMaya('PAYMENT_FAILED');
        $user = $this->devotee();

        $this->actingAs($user)->post(route('upgrade.checkout', $this->plan));
        $this->actingAs($user)->get(route('upgrade.success'));

        $this->assertSame('Failed', Transaction::firstOrFail()->status);
        $this->assertFalse($user->fresh()->is_premium);
    }

    public function test_visiting_the_success_url_with_no_payment_in_flight_does_nothing(): void
    {
        Http::fake();

        $user = $this->devotee();
        $this->actingAs($user)->get(route('upgrade.success'))->assertRedirect(route('upgrade'));

        $this->assertSame(0, Transaction::count());
        $this->assertFalse($user->fresh()->is_premium);
        Http::assertNothingSent();
    }

    /* ── The webhook ───────────────────────────────────────────────────── */

    public function test_the_webhook_verifies_with_maya_before_marking_anything_paid(): void
    {
        $this->fakeMaya('PAYMENT_SUCCESS');
        $user = $this->devotee();

        $this->actingAs($user)->post(route('upgrade.checkout', $this->plan));
        $reference = Transaction::firstOrFail()->reference_no;

        // Maya's own callback, arriving with no session and no CSRF token.
        $this->postJson(route('maya.webhook'), [
            'id'                     => 'pay-xyz-789',
            'requestReferenceNumber' => $reference,
            'paymentStatus'          => 'PAYMENT_SUCCESS',
        ])->assertOk();

        $this->assertSame('Paid', Transaction::firstOrFail()->status);
        $this->assertTrue($user->fresh()->is_premium);

        // It did not take the body's word for it.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/checkout/v1/checkouts/chk-1'));
    }

    /**
     * The attack the missing signature makes possible: anyone who learns the
     * webhook URL posts a PAYMENT_SUCCESS body for a reference they guessed or
     * observed. Here Maya says the payment failed, and the forged body claims
     * success. Maya wins.
     */
    public function test_a_forged_webhook_body_cannot_mark_a_transaction_paid(): void
    {
        $this->fakeMaya('PAYMENT_FAILED');
        $user = $this->devotee();

        $this->actingAs($user)->post(route('upgrade.checkout', $this->plan));
        $reference = Transaction::firstOrFail()->reference_no;

        $this->postJson(route('maya.webhook'), [
            'requestReferenceNumber' => $reference,
            'paymentStatus'          => 'PAYMENT_SUCCESS',
            'status'                 => 'COMPLETED',
            'isPaid'                 => true,
        ])->assertOk();

        $this->assertSame('Failed', Transaction::firstOrFail()->status);
        $this->assertFalse($user->fresh()->is_premium);
    }

    /**
     * Laravel skips CSRF verification inside tests, so posting to the webhook
     * above proves nothing about it. The exemption is asserted directly
     * instead: without it Maya - which has no session and therefore no token -
     * would get a 419 on every callback, and payments finished after the tab
     * was closed would never be recorded.
     */
    public function test_the_webhook_path_is_exempt_from_csrf(): void
    {
        $middleware = app(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $property = (new \ReflectionClass($middleware))->getProperty('neverVerify');
        $property->setAccessible(true);

        $this->assertContains(
            'maya/webhook',
            $property->getValue(),
            'Maya cannot send a CSRF token, so its callback would 419.'
        );
    }

    /**
     * Maya posts from a fixed set of addresses. It is a cheap first filter,
     * not the security boundary - the re-fetch is that - but a body from
     * somewhere else should not even cost us an API call.
     */
    public function test_a_webhook_from_an_unexpected_address_is_ignored(): void
    {
        $this->fakeMaya('PAYMENT_SUCCESS');
        config(['services.maya.webhook_ips' => ['13.229.160.234']]);

        $user = $this->devotee();
        $this->actingAs($user)->post(route('upgrade.checkout', $this->plan));
        $reference = Transaction::firstOrFail()->reference_no;

        $this->postJson(route('maya.webhook'), ['requestReferenceNumber' => $reference], [
            'REMOTE_ADDR' => '203.0.113.9',
        ])->assertOk();

        $this->assertSame('Pending', Transaction::firstOrFail()->status);
        $this->assertFalse($user->fresh()->is_premium);
    }

    public function test_a_webhook_for_an_unknown_reference_is_shrugged_off(): void
    {
        Http::fake();

        $this->postJson(route('maya.webhook'), [
            'requestReferenceNumber' => 'GIYA-000000-MADEUP0000',
            'paymentStatus'          => 'PAYMENT_SUCCESS',
        ])->assertOk();

        Http::assertNothingSent();
    }

    /**
     * Maya retries a webhook it thinks was not acknowledged, and the devotee's
     * return usually lands at about the same moment. Neither may re-stamp a
     * settled row: processed_at is what the subscription's expiry is measured
     * from, so moving it silently extends Premium.
     */
    public function test_a_replayed_webhook_does_not_move_the_paid_date(): void
    {
        $this->fakeMaya('PAYMENT_SUCCESS');
        $user = $this->devotee();

        $this->actingAs($user)->post(route('upgrade.checkout', $this->plan));
        $reference = Transaction::firstOrFail()->reference_no;

        $this->postJson(route('maya.webhook'), ['requestReferenceNumber' => $reference])->assertOk();
        $first = Transaction::firstOrFail()->processed_at;

        $this->travel(2)->hours();

        $this->postJson(route('maya.webhook'), ['requestReferenceNumber' => $reference])->assertOk();
        $this->postJson(route('maya.webhook'), ['requestReferenceNumber' => $reference])->assertOk();

        $this->assertSame(1, Transaction::count());
        $this->assertEquals(
            $first->timestamp,
            Transaction::firstOrFail()->processed_at->timestamp,
            'A replayed webhook moved processed_at, quietly extending the subscription.'
        );
    }

    /**
     * A payment still working its way through 3-D Secure is not a failure.
     * Writing Failed over it would strand a devotee mid-payment.
     */
    public function test_an_in_progress_payment_is_left_pending(): void
    {
        $this->fakeMaya('PENDING_TOKEN');
        $user = $this->devotee();

        $this->actingAs($user)->post(route('upgrade.checkout', $this->plan));
        $this->postJson(route('maya.webhook'), [
            'requestReferenceNumber' => Transaction::firstOrFail()->reference_no,
        ])->assertOk();

        $this->assertSame('Pending', Transaction::firstOrFail()->status);
    }

    /** Maya unreachable during verification must not decide anything. */
    public function test_maya_being_down_leaves_the_row_pending(): void
    {
        Http::fake([
            '*/checkout/v1/checkouts' => Http::response([
                'checkoutId' => 'chk-abc-123', 'redirectUrl' => 'https://example.test/pay',
            ], 200),
            '*/checkout/v1/checkouts/*' => Http::response('', 503),
        ]);

        $user = $this->devotee();
        $this->actingAs($user)->post(route('upgrade.checkout', $this->plan));
        $this->actingAs($user)->get(route('upgrade.success'));

        $this->assertSame('Pending', Transaction::firstOrFail()->status);
        $this->assertFalse($user->fresh()->is_premium);
    }

    /* ── Access ────────────────────────────────────────────────────────── */

    public function test_a_guest_cannot_start_a_payment(): void
    {
        Http::fake();

        $this->post(route('upgrade.checkout', $this->plan))->assertRedirect(route('login'));

        $this->assertSame(0, Transaction::count());
        Http::assertNothingSent();
    }

    /**
     * The session remembers which transaction to verify. It must still be
     * checked against the signed-in devotee, or a copied session value would
     * let one account settle another's payment.
     */
    public function test_a_devotee_cannot_verify_another_devotees_transaction(): void
    {
        $this->fakeMaya('PAYMENT_SUCCESS');

        $payer     = $this->devotee('Payer');
        $bystander = $this->devotee('Bystander');

        $this->actingAs($payer)->post(route('upgrade.checkout', $this->plan));
        $transaction = Transaction::firstOrFail();

        $this->actingAs($bystander)
            ->withSession(['maya.transaction' => $transaction->id])
            ->get(route('upgrade.success'));

        $this->assertSame('Pending', $transaction->fresh()->status);
        $this->assertFalse($bystander->fresh()->is_premium);
        $this->assertFalse($payer->fresh()->is_premium);
    }

    public function test_an_inactive_plan_cannot_be_bought(): void
    {
        Http::fake();

        $this->plan->update(['is_active' => false]);

        $this->actingAs($this->devotee())
            ->post(route('upgrade.checkout', $this->plan))
            ->assertNotFound();

        $this->assertSame(0, Transaction::count());
    }

    public function test_premium_expires_when_the_plans_window_closes(): void
    {
        $this->fakeMaya('PAYMENT_SUCCESS');
        $user = $this->devotee();

        $this->actingAs($user)->post(route('upgrade.checkout', $this->plan));
        $this->actingAs($user)->get(route('upgrade.success'));

        $this->assertTrue($user->fresh()->is_premium);

        $this->travel(31)->days();

        $this->assertFalse($user->fresh()->is_premium, 'A month-old monthly pass still grants Premium.');
    }

    /* ── The upgrade page ──────────────────────────────────────────────── */

    public function test_the_upgrade_page_lists_the_plans_on_offer(): void
    {
        $this->actingAs($this->devotee())
            ->get(route('upgrade'))
            ->assertOk()
            ->assertSee('Pilgrim Monthly')
            ->assertSee('Pilgrim Weekly')
            ->assertDontSee('Pilgrim Annual');
    }

    /**
     * The page is for devotees, so it carries nothing addressed to whoever
     * built it: no test card numbers, no sandbox notice, no instruction to put
     * keys in .env, and no description of how transactions are recorded.
     */
    public function test_the_upgrade_page_shows_nothing_meant_for_developers(): void
    {
        $page = $this->actingAs($this->devotee())->get(route('upgrade'))->assertOk();

        foreach ([
            '5123', '4917', 'mctest1',       // sandbox test cards
            'Sandbox', 'sandbox',            // which environment we point at
            'MAYA_PUBLIC_KEY', '.env',       // setup instructions
            'Pending transaction',           // our own transaction handling
        ] as $developerOnly) {
            $page->assertDontSee($developerOnly, false);
        }
    }

    /** With Maya unreachable a devotee is told that, not which key is missing. */
    public function test_an_unconfigured_gateway_does_not_leak_setup_details(): void
    {
        config(['services.maya.public' => '', 'services.maya.secret' => '']);

        $this->actingAs($this->devotee())
            ->get(route('upgrade'))
            ->assertOk()
            ->assertSee('temporarily unavailable')
            ->assertDontSee('MAYA_PUBLIC_KEY')
            ->assertDontSee('.env', false);
    }

    /**
     * The seeder used to write a 'Free' plan at PHP 0.00 with is_active true.
     * The upgrade page listed every active plan, so free appeared as a card
     * with a Pay with Maya button under it - and Maya rejects a zero total,
     * so the devotee would have got an error for pressing a button we drew.
     */
    public function test_a_free_plan_is_neither_listed_nor_purchasable(): void
    {
        Http::fake();

        $free = SubscriptionPlan::create([
            'name' => 'Free', 'description' => 'No payment.',
            'price' => 0.00, 'currency' => 'PHP', 'duration_days' => 0,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $user = $this->devotee();

        $this->actingAs($user)->get(route('upgrade'))->assertOk()->assertDontSee('₱0');

        $this->actingAs($user)
            ->post(route('upgrade.checkout', $free))
            ->assertNotFound();

        $this->assertSame(0, Transaction::count());
        Http::assertNothingSent();
    }

    /** A retired tier stays in the database for history but is not sold. */
    public function test_a_deactivated_plan_is_not_listed(): void
    {
        SubscriptionPlan::create([
            'name' => 'Pilgrim Annual', 'description' => 'Retired.',
            'price' => 899.00, 'currency' => 'PHP', 'duration_days' => 365,
            'is_active' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->devotee())
            ->get(route('upgrade'))
            ->assertOk()
            ->assertDontSee('Pilgrim Annual')
            ->assertDontSee('899');
    }

    public function test_the_page_refuses_to_take_money_with_no_keys_configured(): void
    {
        config(['services.maya.public' => '', 'services.maya.secret' => '']);
        Http::fake();

        $this->actingAs($this->devotee())
            ->post(route('upgrade.checkout', $this->plan))
            ->assertRedirect();

        $this->assertSame(0, Transaction::count());
        Http::assertNothingSent();
    }
}
