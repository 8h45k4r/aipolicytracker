<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: refunds and chargebacks arrive as refund.* and dispute.* webhooks that name
// a payment, never a subscription, so a refunded or charged-back customer kept Pro
// until the subscription itself changed state. billing_payments records each
// payment.succeeded with the subscription it paid for, which is the only way to
// trace a refund or dispute back to access. revoked_at on the subscription takes
// access away without overwriting the provider's own status, which stays a
// faithful mirror. Amounts are in the smallest currency unit; no card data.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_payments', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 24)->default('dodo');
            $table->string('provider_payment_id')->unique();
            $table->string('provider_subscription_id')->nullable()->index();
            $table->integer('total_amount')->nullable();
            $table->string('currency', 8)->nullable();
            // succeeded, partially_refunded, refunded, disputed, dispute_lost, dispute_won
            $table->string('status', 24);
            $table->integer('refunded_amount')->default(0);
            $table->string('dispute_status', 32)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['revoked_at', 'revoked_reason']);
        });
        Schema::dropIfExists('billing_payments');
    }
};
