<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P8: follows become watches (a record, a jurisdiction, a sector, a use case,
 * a framework, a change type or a saved search), alerts gain channels (a
 * private feed, Slack, a signed webhook) with a delivery log, and consent
 * decisions are written down.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follows', function (Blueprint $table) {
            $table->string('label')->nullable()->after('subject_slug');
            $table->json('params')->nullable()->after('label');
        });

        Schema::create('alert_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 16); // email|rss|slack|webhook
            $table->string('endpoint', 2048)->nullable();
            $table->string('secret', 96)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('last_delivered_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'kind']);
        });

        Schema::create('channel_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_channel_id')->constrained('alert_channels')->cascadeOnDelete();
            $table->foreignId('alert_delivery_id')->nullable()->constrained('alert_deliveries')->nullOnDelete();
            $table->json('payload');
            $table->string('status', 16)->default('pending'); // pending|sent|failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at']);
        });

        Schema::create('consent_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 32);    // alerts.email|alerts.channel|marketing
            $table->boolean('granted');
            $table->string('source', 32);  // account|unsubscribe-link|api
            $table->string('detail')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_events');
        Schema::dropIfExists('channel_deliveries');
        Schema::dropIfExists('alert_channels');
        Schema::table('follows', function (Blueprint $table) {
            $table->dropColumn(['label', 'params']);
        });
    }
};
