<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: an alert that only says "a record changed" is replaceable; one that says
// "this may affect the EU hiring system you described" is the paid product. A
// profile stores the answers a user already gave the free applicability check,
// so the daily alert can screen every new change against them with exactly the
// same rules the tool page used. Answers only: no customer systems, no evidence.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicability_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('answers');                       // normalised by ApplicabilityScreener
            $table->timestamp('last_matched_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicability_profiles');
    }
};
