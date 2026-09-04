<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERD entity: Transactions (extended)
 *
 * The ERD's Transactions table records WHAT was paid - amount, method, status,
 * and our own reference_no. It has nowhere to keep the identifiers the payment
 * provider gives back, and without those we cannot ask Maya later whether a
 * payment actually succeeded.
 *
 * That question has to be answerable, because Maya's webhooks carry no
 * signature. Anyone who learns the URL can post to it. So the webhook is
 * treated as a nudge, not as evidence: it tells us a payment id changed, and
 * we then fetch that payment from Maya with our secret key and take the status
 * from the answer. Storing provider_payment_id is what makes that possible.
 *
 *   provider              which processor handled it ('maya'); nullable so the
 *                         Cash and manual rows the seeder writes stay valid
 *   provider_checkout_id  the id Maya returns when the checkout is created
 *   provider_payment_id   the id of the payment itself, learned at return or
 *                         from the webhook; unique, so a webhook replayed ten
 *                         times cannot create ten paid transactions
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('provider', 30)->nullable()->after('method');
            $table->string('provider_checkout_id', 64)->nullable()->after('provider');
            $table->string('provider_payment_id', 64)->nullable()->after('provider_checkout_id');

            $table->unique('provider_checkout_id');
            $table->unique('provider_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['provider_payment_id']);
            $table->dropUnique(['provider_checkout_id']);
            $table->dropColumn(['provider', 'provider_checkout_id', 'provider_payment_id']);
        });
    }
};
