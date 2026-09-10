<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MayaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A transaction should say HOW it was paid, not just who processed it.
 *
 * `method` is written as 'Maya' when the checkout is created - before the
 * devotee has seen Maya's page, let alone chosen between a card, QR Ph and a
 * wallet. So every row read "Maya" and a QR payment was indistinguishable
 * from a card one, which is what the admin Method column was showing.
 *
 * Maya carries the answer in fundSource, and the shapes below are taken from
 * its published webhook samples rather than invented here.
 */
class MayaPaymentChannelTest extends TestCase
{
    use RefreshDatabase;

    private const CHECKOUT_ID = 'cb0e3e93-1a7d-4b3c-9f1e-1c9a1f0e0001';
    private const PAYMENT_ID  = '9f2b1c44-77aa-4c1b-9a10-5b6f2a3d0002';

    /* ── the parser ─────────────────────────────────────────────────── */

    public static function fundSourceProvider(): array
    {
        return [
            'QR Ph' => [
                ['type' => 'qrph', 'description' => '***************6137', 'details' => []],
                'QR Ph',
            ],
            'card with scheme and last four' => [
                ['type' => 'card', 'details' => ['scheme' => 'master-card', 'last4' => '4154', 'masked' => '545301******4154']],
                'Card · Master Card ••••4154',
            ],
            'card with nothing but a type' => [
                ['type' => 'card'],
                'Card',
            ],
            'GCash' => [
                ['type' => 'gcash', 'description' => 'GCash Account', 'details' => ['mid' => 'XXX106009567']],
                'GCash',
            ],
            'Maya wallet' => [
                ['type' => 'maya-wallet', 'details' => ['masked' => '********0366']],
                'Maya Wallet',
            ],
            'a channel Maya has not shipped yet' => [
                ['type' => 'shopee_pay'],
                'Shopee Pay',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('fundSourceProvider')]
    public function test_it_reads_the_channel_out_of_a_fund_source(array $fundSource, string $expected): void
    {
        $this->assertSame($expected, MayaService::channelFrom(['fundSource' => $fundSource]));
    }

    public static function nothingUsefulProvider(): array
    {
        return [
            'no fundSource at all' => [[]],
            'fundSource is not an object' => [['fundSource' => 'card']],
            'fundSource has no type' => [['fundSource' => ['details' => ['last4' => '4154']]]],
            'the type is blank' => [['fundSource' => ['type' => '']]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nothingUsefulProvider')]
    public function test_it_returns_null_rather_than_guessing(array $body): void
    {
        $this->assertNull(MayaService::channelFrom($body));
    }

    /* ── end to end ─────────────────────────────────────────────────── */

    private function devotee(): User
    {
        return User::create([
            'name' => 'Lyle', 'email' => 'lyle@example.com',
            'password_hash' => bcrypt('secret'), 'role' => 'devotee',
            'status' => 'Active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function pendingTransaction(User $user): Transaction
    {
        $plan = SubscriptionPlan::active()->where('price', '>', 0)->orderBy('price')->firstOrFail();

        return Transaction::create([
            'user_id'      => $user->id,
            'plan_type_id' => $plan->id,
            'amount'       => $plan->price,
            'currency'     => $plan->currency,
            'method'       => 'Maya',
            'provider'     => 'maya',
            'provider_checkout_id' => self::CHECKOUT_ID,
            'status'       => 'Pending',
            'reference_no' => 'GIYA-260905-TESTREF0001',
            'notes'        => 'Maya Checkout started.',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    /** Maya answers the checkout call with the fund source already attached. */
    public function test_a_qr_payment_is_recorded_as_qr_ph(): void
    {
        $user        = $this->devotee();
        $transaction = $this->pendingTransaction($user);

        Http::fake([
            '*/checkout/v1/checkouts/*' => Http::response([
                'id'            => self::CHECKOUT_ID,
                'paymentStatus' => 'PAYMENT_SUCCESS',
                'paymentId'     => self::PAYMENT_ID,
                'fundSource'    => ['type' => 'qrph', 'description' => '***************6137'],
            ]),
        ]);

        $this->actingAs($user)
            ->withSession(['maya.transaction' => $transaction->id])
            ->get(route('upgrade.success'))
            ->assertRedirect(route('upgrade'));

        $transaction->refresh();

        $this->assertSame('Paid', $transaction->status);
        $this->assertSame('QR Ph', $transaction->method);
        $this->assertSame(self::PAYMENT_ID, $transaction->provider_payment_id);
    }

    /**
     * The checkout body does not always carry fundSource. When it does not,
     * the payment itself is asked - and only then, which is what stops this
     * becoming a second call on every return trip.
     */
    public function test_it_asks_the_payment_when_the_checkout_does_not_say(): void
    {
        $user        = $this->devotee();
        $transaction = $this->pendingTransaction($user);

        Http::fake([
            '*/checkout/v1/checkouts/*' => Http::response([
                'id'            => self::CHECKOUT_ID,
                'paymentStatus' => 'PAYMENT_SUCCESS',
                'paymentId'     => self::PAYMENT_ID,
            ]),
            '*/payments/v1/payments/*' => Http::response([
                'id'         => self::PAYMENT_ID,
                'status'     => 'PAYMENT_SUCCESS',
                'fundSource' => ['type' => 'card', 'details' => ['scheme' => 'master-card', 'last4' => '2346']],
            ]),
        ]);

        $this->actingAs($user)
            ->withSession(['maya.transaction' => $transaction->id])
            ->get(route('upgrade.success'))
            ->assertRedirect(route('upgrade'));

        $this->assertSame('Card · Master Card ••••2346', $transaction->refresh()->method);
    }

    /** No second call while the checkout already answered the question. */
    public function test_it_does_not_ask_the_payment_when_the_checkout_already_said(): void
    {
        $user        = $this->devotee();
        $transaction = $this->pendingTransaction($user);

        Http::fake([
            '*/checkout/v1/checkouts/*' => Http::response([
                'id'            => self::CHECKOUT_ID,
                'paymentStatus' => 'PAYMENT_SUCCESS',
                'paymentId'     => self::PAYMENT_ID,
                'fundSource'    => ['type' => 'qrph'],
            ]),
            '*/payments/v1/payments/*' => Http::response(['should' => 'not be called']),
        ]);

        $this->actingAs($user)
            ->withSession(['maya.transaction' => $transaction->id])
            ->get(route('upgrade.success'))
            ->assertRedirect(route('upgrade'));

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/payments/v1/payments/'));
    }

    /**
     * A channel we could not learn must leave the old value alone. Blanking
     * the column would be worse than the 'Maya' it replaced.
     */
    public function test_an_unknown_channel_leaves_the_method_as_it_was(): void
    {
        $user        = $this->devotee();
        $transaction = $this->pendingTransaction($user);

        Http::fake([
            '*/checkout/v1/checkouts/*' => Http::response([
                'id'            => self::CHECKOUT_ID,
                'paymentStatus' => 'PAYMENT_SUCCESS',
            ]),
        ]);

        $this->actingAs($user)
            ->withSession(['maya.transaction' => $transaction->id])
            ->get(route('upgrade.success'))
            ->assertRedirect(route('upgrade'));

        $transaction->refresh();

        $this->assertSame('Paid', $transaction->status);
        $this->assertSame('Maya', $transaction->method);
    }

    /**
     * The webhook settles through the same verify(), so a payment confirmed
     * while the devotee has already closed the tab still records how it was
     * paid.
     */
    public function test_the_webhook_records_the_channel_too(): void
    {
        $user        = $this->devotee();
        $transaction = $this->pendingTransaction($user);

        Http::fake([
            '*/checkout/v1/checkouts/*' => Http::response([
                'id'            => self::CHECKOUT_ID,
                'paymentStatus' => 'PAYMENT_SUCCESS',
                'fundSource'    => ['type' => 'gcash', 'description' => 'GCash Account'],
            ]),
        ]);

        config(['services.maya.webhook_ips' => ['127.0.0.1']]);

        $this->postJson(route('maya.webhook'), [
            'requestReferenceNumber' => $transaction->reference_no,
        ])->assertOk();

        $this->assertSame('GCash', $transaction->refresh()->method);
    }

    /**
     * The devotee's own list shows the channel - and shows nothing extra
     * while it is still the placeholder, because "Maya" printed beside a
     * Maya payment tells them nothing they did not already know.
     */
    public function test_the_upgrade_page_prints_the_channel(): void
    {
        $user        = $this->devotee();
        $transaction = $this->pendingTransaction($user);
        $transaction->update(['status' => 'Paid', 'method' => 'QR Ph', 'processed_at' => now()]);

        $this->assertStringContainsString('QR Ph', $this->rowFor($user, $transaction->reference_no));

        $transaction->update(['method' => 'Maya']);

        $placeholder = $this->rowFor($user, $transaction->reference_no);

        $this->assertStringNotContainsString('Maya', $placeholder);

        // The same row either way - only the channel appears and disappears.
        $this->assertStringContainsString($transaction->reference_no, $placeholder);
    }

    /** The rendered transaction row, as plain text. */
    private function rowFor(User $user, string $reference): string
    {
        $html  = $this->actingAs($user)->get(route('upgrade'))->assertOk()->getContent();
        $start = strpos($html, $reference);

        $this->assertNotFalse($start, "The transaction row for $reference was never rendered.");

        return preg_replace('/\s+/', ' ', strip_tags(substr($html, $start, 220)));
    }
}
