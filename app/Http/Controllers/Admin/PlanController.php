<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Plans & prices: the Premium plans devotees can buy.
 *
 * Changing a price is safe for money already in motion: each payment copies
 * the plan's price when it starts (PaymentController::checkout), so paid and
 * pending payments keep their own amount and a new price only applies to
 * payments started after it. A plan that has payments is never deleted, only
 * hidden, so those payments still show what was bought.
 */
class PlanController extends Controller
{
    /** Pesos. Low enough to run a promo, high enough to catch a stray zero. */
    public const MIN_PRICE = 20;

    public const MAX_PRICE = 20000;

    /** Days. Up to ten years. */
    public const MAX_DAYS = 3650;

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $plan = SubscriptionPlan::create($data + [
            'currency' => SubscriptionPlan::query()->value('currency') ?? 'PHP',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->back("Added {$plan->name} at ".self::peso($plan->price).'.');
    }

    public function update(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $data = $this->validated($request, $plan);
        $before = ['price' => self::peso($plan->price), 'days' => $plan->duration_days];

        $plan->update($data + ['updated_at' => now()]);

        $changes = [];
        if ($before['price'] !== self::peso($plan->price)) {
            $changes[] = 'price '.$before['price'].' to '.self::peso($plan->price).' (new payments only)';
        }
        if ($before['days'] !== $plan->duration_days) {
            $changes[] = "length {$before['days']} to {$plan->duration_days} days";
        }

        return $this->back("Saved {$plan->name}".($changes ? ': '.implode(', ', $changes) : '').'.');
    }

    /** Offer it, or stop offering it. Devotees who already paid keep their days. */
    public function toggle(SubscriptionPlan $plan): RedirectResponse
    {
        $plan->update(['is_active' => ! $plan->is_active, 'updated_at' => now()]);

        return $this->back($plan->is_active
            ? "{$plan->name} is offered again."
            : "{$plan->name} is hidden from the upgrade page. Devotees who paid for it keep their days.");
    }

    public function destroy(SubscriptionPlan $plan): RedirectResponse
    {
        if ($plan->transactions()->exists()) {
            return $this->back(null, "{$plan->name} has payments, so it cannot be deleted. Hide it instead.");
        }

        $name = $plan->name;
        $plan->delete();

        return $this->back("Deleted {$name}.");
    }

    private function validated(Request $request, ?SubscriptionPlan $plan = null): array
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:60', Rule::unique('subscription_plans', 'name')->ignore($plan?->id)],
            'description'   => ['nullable', 'string', 'max:300'],
            'price'         => ['required', 'numeric', 'min:'.self::MIN_PRICE, 'max:'.self::MAX_PRICE, 'decimal:0,2'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:'.self::MAX_DAYS],
            'is_active'     => ['sometimes', 'boolean'],
        ], [
            'name.unique'  => 'Another plan already has this name.',
            'price.min'    => 'The price must be at least ₱'.self::MIN_PRICE.'.',
            'price.max'    => 'The price can be at most ₱'.number_format(self::MAX_PRICE).'.',
            'price.decimal' => 'Use at most two decimals, like 99.50.',
            'duration_days.max' => 'A plan can last at most '.self::MAX_DAYS.' days (10 years).',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private static function peso($amount): string
    {
        return '₱'.number_format((float) $amount, 2);
    }

    private function back(?string $success, ?string $error = null): RedirectResponse
    {
        $to = redirect()->route('admin.transactions', ['view' => 'plans']);

        return $error ? $to->with('error', $error) : $to->with('success', $success);
    }
}
