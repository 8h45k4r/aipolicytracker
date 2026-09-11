<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: policy:import rebuilds every record from data/ on each deploy, so a reviewer's
// "verified" decision made in the admin would be overwritten minutes later. This table keeps
// each human verification (who, when, confidence, note) keyed by record slug; the importer
// re-applies it after every import and `policy:export-verifications` writes it back into the
// YAML files so the repository stays the source of truth.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('record_type', 24);           // policy | jurisdiction
            $table->string('record_slug', 160);
            $table->string('review_status', 32);          // verified | pending_review | needs_update
            $table->string('confidence_level', 16);
            $table->date('last_verified_at')->nullable();
            $table->string('reviewed_by', 120);
            $table->string('source_checked_url', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('exported')->default(false);  // written back to data/ by the export command
            $table->timestamps();
            $table->unique(['record_type', 'record_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_verifications');
    }
};
