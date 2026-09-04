<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ERD entity: Transactions
 *
 * $transaction->plan           -> the SubscriptionPlans NAME (relation is subscriptionPlan)
 * $transaction->transaction_id -> formatted from the primary key
 */
class Transaction extends Model
{
    protected $table = 'transactions';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'plan_type_id', 'amount', 'currency', 'method',
        'provider', 'provider_checkout_id', 'provider_payment_id',
        'status', 'reference_no', 'notes', 'created_at', 'processed_at', 'updated_at',
    ];

    protected $appends = ['transaction_id', 'plan'];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:2',
            'created_at'   => 'datetime',
            'processed_at' => 'datetime',
            'updated_at'   => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_type_id');
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'Paid');
    }

    /** Human-facing reference, e.g. TXN-000042. */
    public function getTransactionIdAttribute(): string
    {
        return 'TXN-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    /** Old views printed $transaction->plan as a string. */
    public function getPlanAttribute(): string
    {
        return $this->subscriptionPlan?->name ?? 'Unknown plan';
    }

    public function amountLabel(): string
    {
        return $this->currency.' '.number_format((float) $this->amount, 2);
    }

    /**
     * Record the outcome Maya reported, once.
     *
     * A webhook can arrive twice, and the devotee usually lands on the return
     * URL at roughly the same moment, so this is written to be safe to call
     * repeatedly: a row that has already settled is left exactly as it is.
     * Without that, a second call would move processed_at forward and quietly
     * extend the devotee's subscription by however long the gap was.
     *
     * @param  string|null  $status  one of Paid | Failed | Refunded, or null
     *                               when Maya said something we do not act on
     */
    public function settle(?string $status, ?string $paymentId = null, ?string $note = null): bool
    {
        if ($paymentId && ! $this->provider_payment_id) {
            $this->provider_payment_id = $paymentId;
        }

        if ($status === null || $this->status !== 'Pending') {
            $this->updated_at = now();
            $this->save();

            return false;
        }

        $this->status       = $status;
        $this->processed_at = now();
        $this->updated_at   = now();

        if ($note) {
            $this->notes = $note;
        }

        $this->save();

        return true;
    }

    /**
     * When each devotee's Premium access runs out, keyed by user id.
     *
     * User::getIsPremiumAttribute() answers this for one devotee, and it costs
     * a query every time it is read. On the admin user list that is a query
     * per row, plus a lazy plan load inside each isStillValid() - a page of
     * fifty users would be well over a hundred queries for one column.
     *
     * This answers it for many devotees in a single query, and still decides
     * "is it still valid" with isStillValid(), so there is only ever one
     * definition of what Premium means.
     *
     * @param  array<int>|null  $userIds  null for everyone
     * @return array<int, \Illuminate\Support\Carbon>  user id => expiry, latest wins
     */
    public static function premiumExpiryByUser(?array $userIds = null): array
    {
        $rows = static::query()
            ->when($userIds !== null, fn ($q) => $q->whereIn('user_id', $userIds))
            ->where('status', 'Paid')
            ->whereNotNull('processed_at')
            ->with('subscriptionPlan')
            ->get();

        $expiry = [];

        foreach ($rows as $transaction) {
            if (! $transaction->subscriptionPlan?->is_active || ! $transaction->isStillValid()) {
                continue;
            }

            $until = $transaction->processed_at
                ->copy()
                ->addDays((int) $transaction->subscriptionPlan->duration_days);

            // Someone who bought twice keeps the later of the two.
            if (! isset($expiry[$transaction->user_id]) || $until->gt($expiry[$transaction->user_id])) {
                $expiry[$transaction->user_id] = $until;
            }
        }

        return $expiry;
    }

    /** Has this paid transaction's coverage window expired yet? */
    public function isStillValid(): bool
    {
        if ($this->status !== 'Paid' || ! $this->processed_at) {
            return false;
        }

        $days = $this->subscriptionPlan?->duration_days ?? 0;

        return $this->processed_at->copy()->addDays($days)->isFuture();
    }
}
