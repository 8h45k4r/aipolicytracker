<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The template request form has always offered "email me when this template gets a new
 * version" and stored the answer, but nothing sent those emails. These two tables make it
 * a working promise: one delivery row per new version per address (unique, so a repeated
 * or concurrent build cannot mail anyone twice), and the addresses that unsubscribed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_update_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_version_id')->constrained()->cascadeOnDelete();
            $table->string('template_slug', 120);
            $table->string('email', 190);
            // pending → sending → sent | failed | suppressed (unsubscribed after it was queued)
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['template_version_id', 'email']);
        });

        Schema::create('template_update_optouts', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->unique();
            $table->timestamp('unsubscribed_at');
            $table->string('source', 32);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_update_optouts');
        Schema::dropIfExists('template_update_notices');
    }
};
