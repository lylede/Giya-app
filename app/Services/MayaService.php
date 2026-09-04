<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Every call GIYA makes to Maya lives here.
 *
 * Two things about Maya shape this class:
 *
 * 1. Authentication is HTTP Basic with the key as the USERNAME and an empty
 *    password - base64("pk-xxx:"). The trailing colon matters.
 *
 * 2. Creating a checkout uses the PUBLIC key; reading a payment uses the
 *    SECRET one. Sending the wrong key gives a 401 that looks like a bad key
 *    rather than a wrong key, which is a miserable half hour to debug, so the
 *    two are separated into named methods instead of a shared client.
 */
class MayaService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $publicKey,
        private readonly string $secretKey,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            rtrim((string) config('services.maya.base_url'), '/'),
            (string) config('services.maya.public'),
            (string) config('services.maya.secret'),
        );
    }

    public function isConfigured(): bool
    {
        return $this->publicKey !== '' && $this->secretKey !== '';
    }

    /** True when pointed at the sandbox - the upgrade page says so out loud. */
    public function isSandbox(): bool
    {
        return str_contains($this->baseUrl, 'sandbox');
    }

    /**
     * Hand Maya a transaction and get back the URL to send the devotee to.
     *
     * The amount comes from the transaction row, which was created from the
     * plan's price - never from the request. A price that arrives in a form
     * field is a price the devotee can edit.
     *
     * @return array{id: string, url: string}
     */
    public function createCheckout(Transaction $transaction, array $urls): array
    {
        $user = $transaction->user;
        $plan = $transaction->subscriptionPlan;

        $response = Http::withBasicAuth($this->publicKey, '')
            ->acceptJson()
            ->timeout(20)
            ->post($this->baseUrl.'/checkout/v1/checkouts', [
                'totalAmount' => [
                    'value'    => (float) $transaction->amount,
                    'currency' => $transaction->currency,
                    'details'  => [
                        'subtotal' => (float) $transaction->amount,
                    ],
                ],
                'buyer' => [
                    'firstName' => $this->firstName($user?->name),
                    'lastName'  => $this->lastName($user?->name),
                    'contact'   => ['email' => $user?->email],
                ],
                'items' => [[
                    'name'        => 'GIYA '.($plan?->name ?? 'Subscription'),
                    'quantity'    => 1,
                    'description' => $plan?->description,
                    'totalAmount' => ['value' => (float) $transaction->amount],
                ]],
                // Maya echoes this back on every webhook and every payment we
                // fetch, which is how a payment finds its way home to a row.
                'requestReferenceNumber' => $transaction->reference_no,
                'redirectUrl'            => $urls,
                'metadata'               => [
                    'transactionId' => (string) $transaction->id,
                    'planId'        => (string) $transaction->plan_type_id,
                ],
            ]);

        if ($response->failed()) {
            Log::error('Maya checkout could not be created.', [
                'transaction' => $transaction->id,
                'status'      => $response->status(),
                'body'        => $response->body(),
            ]);

            throw new RuntimeException('Maya refused the checkout ('.$response->status().').');
        }

        $body = $response->json();

        if (empty($body['checkoutId']) || empty($body['redirectUrl'])) {
            throw new RuntimeException('Maya returned a checkout with no id or URL.');
        }

        return ['id' => $body['checkoutId'], 'url' => $body['redirectUrl']];
    }

    /**
     * Ask Maya what actually happened to a checkout.
     *
     * This is the only thing in the whole flow that is allowed to decide a
     * transaction is Paid. Not the return URL the devotee lands on - a devotee
     * can type that URL. Not the webhook body - it is unsigned. Only this.
     *
     * GET /checkout/v1/checkouts/{id} with the SECRET key. The reply carries
     * paymentStatus (PAYMENT_SUCCESS, PAYMENT_FAILED, ...) and, once a payment
     * exists, its id.
     */
    public function retrieveCheckout(string $checkoutId): ?array
    {
        return $this->getJson('/checkout/v1/checkouts/'.$checkoutId, 'checkout '.$checkoutId);
    }

    /** GET /payments/v1/payments/{id} - a payment on its own, SECRET key. */
    public function retrievePayment(string $paymentId): ?array
    {
        return $this->getJson('/payments/v1/payments/'.$paymentId, 'payment '.$paymentId);
    }

    /**
     * Pull the payment id out of a checkout body.
     *
     * Maya has moved this field around between API versions, so rather than
     * betting on one spelling we look in the places it has lived. Failing to
     * find it is not fatal - it is stored for the audit trail, and the status
     * itself comes from paymentStatus.
     */
    public static function paymentIdFrom(array $checkout): ?string
    {
        foreach ([
            $checkout['paymentDetails']['paymentId'] ?? null,
            $checkout['paymentDetails']['id'] ?? null,
            $checkout['paymentId'] ?? null,
            $checkout['payments'][0]['id'] ?? null,
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /** paymentStatus is the checkout wording; status is the payment wording. */
    public static function statusFrom(array $body): ?string
    {
        return $body['paymentStatus'] ?? $body['status'] ?? null;
    }

    private function getJson(string $path, string $what): ?array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->timeout(20)
            ->get($this->baseUrl.$path);

        if ($response->failed()) {
            Log::warning('Maya lookup failed.', [
                'what'   => $what,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response->json();
    }

    /** Register a webhook. Used by the giya:maya-webhooks command. */
    public function registerWebhook(string $name, string $callbackUrl): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->post($this->baseUrl.'/payments/v1/webhooks', [
                'name'        => $name,
                'callbackUrl' => $callbackUrl,
            ]);

        return ['ok' => $response->successful(), 'body' => $response->json() ?? []];
    }

    public function listWebhooks(): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->get($this->baseUrl.'/payments/v1/webhooks');

        return $response->successful() ? (array) $response->json() : [];
    }

    public function deleteWebhook(string $id): bool
    {
        return Http::withBasicAuth($this->secretKey, '')
            ->delete($this->baseUrl.'/payments/v1/webhooks/'.$id)
            ->successful();
    }

    /**
     * Maya's payment status -> the words the ERD's Transactions.status uses.
     *
     * Anything unrecognised returns null, which the caller reads as "leave the
     * row alone". A payment sitting in PENDING_TOKEN is not a failure; writing
     * Failed over it would lock a devotee out of a payment still in progress.
     */
    public static function mapStatus(?string $mayaStatus): ?string
    {
        return match (strtoupper((string) $mayaStatus)) {
            'PAYMENT_SUCCESS', 'AUTHORIZED', 'CAPTURED'  => 'Paid',
            'PAYMENT_FAILED', 'PAYMENT_EXPIRED',
            'PAYMENT_CANCELLED', 'VOIDED'                => 'Failed',
            'REFUNDED'                                   => 'Refunded',
            default                                      => null,
        };
    }

    private function firstName(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];

        return $parts[0] ?? 'Devotee';
    }

    private function lastName(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];

        return count($parts) > 1 ? (string) end($parts) : 'Pilgrim';
    }
}
