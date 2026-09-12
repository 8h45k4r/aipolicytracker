<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: paid plans need a local, auditable record of who is subscribed, at which
// plan, with which status, decided only by verified webhooks from the payment
// provider (Dodo Payments, the merchant of record). billing_events stores every
// received webhook exactly once (unique event_id) so retries are idempotent and
// every entitlement change can be traced to a signed event. billing_checkouts
// records checkout attempts so the return page can show the right state before
// the webhook lands. No card or bank data is ever stored here.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('provider', 24)->default('dodo');
            $table->string('provider_customer_id', 64)->unique();
            $table->string('email', 190)->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('billing_customer_id')->nullable()->constrained('billing_customers')->nullOnDelete();
            $table->string('provider', 24)->default('dodo');
            $table->string('provider_subscription_id', 64)->unique();
            $table->string('product_id', 64)->nullable();
            $table->string('plan_key', 40)->nullable()->index();   // resolved from product_id via config/billing.php
            $table->string('status', 24)->index();                 // pending|active|on_hold|paused|past_due|cancelled|failed|expired
            $table->timestamp('current_period_end')->nullable();   // Dodo next_billing_date
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('on_hold_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_event_at')->nullable();        // provider timestamp of the last applied event; older events are ignored
            $table->string('last_event_type', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('billing_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 24)->default('dodo');
            $table->string('event_id', 128)->unique();              // webhook-id header; guarantees idempotency
            $table->string('event_type', 64)->index();
            $table->string('provider_subscription_id', 64)->nullable()->index();
            $table->timestamp('event_at')->nullable();              // provider timestamp
            $table->json('payload');
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('outcome', 24)->nullable();              // applied|ignored|stale|error
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('plan_key', 40);
            $table->string('product_id', 64);
            $table->string('provider_session_id', 64)->nullable()->unique();
            $table->string('status', 24)->default('created');        // created|returned|completed|abandoned
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_checkouts');
        Schema::dropIfExists('billing_events');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('billing_customers');
    }
};
