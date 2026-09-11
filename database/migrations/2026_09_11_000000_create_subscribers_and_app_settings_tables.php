<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: subscribers receive the weekly change-log digest (double opt-in, per-topic,
// unsubscribe by token); app_settings holds operator-managed configuration such as
// the mail API key, stored encrypted so no secret ever sits in plaintext.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('token', 64)->unique();          // confirm / unsubscribe token
            $table->json('topics')->nullable();             // jurisdiction slugs, use-case slugs or ["all"]
            $table->string('frequency', 16)->default('weekly');
            $table->string('source', 64)->nullable();       // page the form was submitted from
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
            $table->index(['confirmed_at', 'unsubscribed_at']);
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();               // encrypted with APP_KEY
            $table->boolean('secret')->default(false);       // masked in the admin UI
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('subscribers');
    }
};
