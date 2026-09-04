<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Services\MayaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Upgrading a devotee to Premium, through Maya's hosted checkout.
 *
 * The shape of the flow, and why:
 *
 *   GET  /upgrade            the plans, and what Premium changes
 *   POST /upgrade/{plan}     write a Pending transaction, ask Maya for a
 *                            checkout, send the devotee there
 *   GET  /upgrade/success    they came back - VERIFY, then say what happened
 *   GET  /upgrade/failure    "                "
 *   GET  /upgrade/cancel     "                "
 *   POST /maya/webhook       Maya says something changed - VERIFY, then act
 *
 * The word doing the work is VERIFY. Both the return URLs and the webhook are
 * inputs GIYA does not control: a devotee can type the success URL into the
 * address bar, and Maya's webhooks carry no signature at all, so anyone who
 * learns the URL can post whatever JSON they like to it. Neither is ever
 * allowed to say a transaction is Paid. Both are only permitted to say "look
 * again at transaction X", after which GIYA asks Maya directly, with the
 * secret key, and believes only that answer.
 *
 * GIYA never sees a card number. The devotee types it on Maya's page.
 */
class PaymentController extends Controller
{
    /** The plans, and where the devotee stands today. */
    public function upgrade()
    {
        $maya = MayaService::fromConfig();

        return view('upgrade', [
            // Active AND priced. A PHP 0.00 row is not something anyone buys,
            // and listing one would put a Pay with Maya button under it.
            'plans'   => SubscriptionPlan::active()
                ->where('price', '>', 0)
                ->orderBy('price')
                ->get(),
            'user'    => Auth::user(),
            // Whether Maya is reachable at all. The page says only that
            // payment is unavailable - which key is missing is our problem,
            // not something to print on a devotee's screen.
            'ready'   => $maya->isConfigured(),
            'history' => Transaction::where('user_id', Auth::id())
                ->with('subscriptionPlan')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),
        ]);
    }

    /**
     * Start a payment.
     *
     * The plan arrives as a route-model-bound id, and the amount is read off
     * that plan. Nothing about the price comes from the request body - a form
     * field holding an amount is a form field a devotee can edit before it is
     * posted, and 899.00 would become 1.00.
     */
    public function checkout(Request $request, SubscriptionPlan $plan)
    {
        // Not offered, or not something with a price. Maya rejects a zero
        // total anyway, but this fails on our side with a clear 404 rather
        // than a confusing error from the gateway.
        abort_unless($plan->is_active && $plan->price > 0, 404);

        $maya = MayaService::fromConfig();

        if (! $maya->isConfigured()) {
            return back()->with('error', 'Payments are not configured yet. Add your Maya keys to .env.');
        }

        $user = $request->user();

        if ($user->is_premium) {
            return redirect()->route('upgrade')->with('success', 'You are already a Premium pilgrim.');
        }

        // Maya caps requestReferenceNumber at 36 characters and rejects a
        // repeat, so this is short and carries randomness rather than a
        // counter that a retry could land on twice.
        $reference = 'GIYA-'.now()->format('ymd').'-'.strtoupper(Str::random(10));

        $transaction = Transaction::create([
            'user_id'      => $user->id,
            'plan_type_id' => $plan->id,
            'amount'       => $plan->price,
            'currency'     => $plan->currency,
            'method'       => 'Maya',
            'provider'     => 'maya',
            'status'       => 'Pending',
            'reference_no' => $reference,
            'notes'        => 'Maya Checkout started.',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        try {
            $checkout = $maya->createCheckout($transaction, [
                'success' => route('upgrade.success'),
                'failure' => route('upgrade.failure'),
                'cancel'  => route('upgrade.cancel'),
            ]);
        } catch (Throwable $e) {
            Log::error('Maya checkout failed to start.', [
                'transaction' => $transaction->id,
                'message'     => $e->getMessage(),
            ]);

            $transaction->settle('Failed', null, 'Could not reach Maya: '.$e->getMessage());

            return back()->with('error', 'We could not reach Maya just now. Nothing was charged - please try again.');
        }

        $transaction->provider_checkout_id = $checkout['id'];
        $transaction->save();

        // Remembered so the return URLs know which transaction to verify.
        // Maya's redirect carries no identifier of its own that we can trust.
        $request->session()->put('maya.transaction', $transaction->id);

        return redirect()->away($checkout['url']);
    }

    /* ── Coming back from Maya ─────────────────────────────────────────── */

    public function success(Request $request)
    {
        $transaction = $this->verifyFromSession($request);

        if ($transaction?->status === 'Paid') {
            return redirect()->route('upgrade')
                ->with('success', 'Payment received - you are now a Premium pilgrim. Reference '.$transaction->reference_no.'.');
        }

        // Maya sent them to the success URL but our own check has not caught
        // up, or disagreed. Say so plainly rather than promising Premium.
        return redirect()->route('upgrade')
            ->with('info', 'Maya has your payment and we are still confirming it. This page will show Premium once it clears.');
    }

    public function failure(Request $request)
    {
        $this->verifyFromSession($request);

        return redirect()->route('upgrade')
            ->with('error', 'The payment did not go through. Nothing was charged - you can try again.');
    }

    public function cancel(Request $request)
    {
        $this->verifyFromSession($request);

        return redirect()->route('upgrade')
            ->with('info', 'Payment cancelled. Nothing was charged.');
    }

    /**
     * Maya's webhook.
     *
     * Unauthenticated by necessity - Maya will not send a token or a
     * signature - so it is written to be harmless when abused. The body is
     * read for exactly one thing: which reference number to look up. Every
     * other field in it is ignored. The verdict comes from asking Maya.
     *
     * Always 200. A webhook that gets an error back is a webhook Maya will
     * retry, and there is nothing a retry can fix here.
     */
    public function webhook(Request $request)
    {
        $allowed = (array) config('services.maya.webhook_ips', []);

        if ($allowed && ! in_array($request->ip(), $allowed, true)) {
            Log::warning('Maya webhook from an unexpected address.', ['ip' => $request->ip()]);

            return response()->json(['received' => true]);
        }

        $reference = $request->input('requestReferenceNumber');

        if (! is_string($reference) || $reference === '') {
            return response()->json(['received' => true]);
        }

        $transaction = Transaction::where('reference_no', $reference)
            ->where('provider', 'maya')
            ->first();

        if (! $transaction) {
            Log::info('Maya webhook for an unknown reference.', ['reference' => $reference]);

            return response()->json(['received' => true]);
        }

        $this->verify($transaction);

        return response()->json(['received' => true]);
    }

    /* ── The one place a transaction's status is decided ───────────────── */

    private function verifyFromSession(Request $request): ?Transaction
    {
        $id = $request->session()->pull('maya.transaction');

        if (! $id) {
            return null;
        }

        $transaction = Transaction::where('id', $id)
            ->where('user_id', Auth::id())   // a session id is not a licence
            ->first();

        return $transaction ? $this->verify($transaction) : null;
    }

    /**
     * Ask Maya about this transaction and write down the answer.
     *
     * Wrapped in a transaction with a row lock so the webhook and the devotee's
     * return - which routinely arrive within the same second - cannot both read
     * "Pending", both decide "Paid", and both stamp processed_at.
     */
    private function verify(Transaction $transaction): Transaction
    {
        if (! $transaction->provider_checkout_id) {
            return $transaction;
        }

        $maya = MayaService::fromConfig();
        $body = $maya->retrieveCheckout($transaction->provider_checkout_id);

        if ($body === null) {
            return $transaction;   // Maya unreachable; leave it Pending
        }

        $mapped    = MayaService::mapStatus(MayaService::statusFrom($body));
        $paymentId = MayaService::paymentIdFrom($body);

        DB::transaction(function () use ($transaction, $mapped, $paymentId, $body) {
            $fresh = Transaction::lockForUpdate()->find($transaction->id);

            if (! $fresh) {
                return;
            }

            $changed = $fresh->settle(
                $mapped,
                $paymentId,
                'Maya reported '.(MayaService::statusFrom($body) ?? 'no status').'.'
            );

            if ($changed) {
                Log::info('Maya settled a transaction.', [
                    'transaction' => $fresh->id,
                    'status'      => $fresh->status,
                ]);
            }

            $transaction->setRawAttributes($fresh->getAttributes(), true);
        });

        return $transaction;
    }
}
