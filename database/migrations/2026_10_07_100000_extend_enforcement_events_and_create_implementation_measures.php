<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enforcement tracker and implementation tracker.
 *
 * enforcement_events existed since the policy-intelligence model but could only say
 * what happened and who acted. A fines and enforcement tracker has to answer who was
 * acted against, what kind of action it was, under which provision, for how much and
 * whether it was appealed, so those columns are added. Every one is nullable: an event
 * recorded before this change, or one whose amount was never published, stays valid.
 *
 * implementation_measures is a new record type (data/implementation/*.yaml): the
 * delegated and implementing acts, guidelines, codes of practice, templates, AI Board
 * outputs, standardisation requests and standards an instrument depends on. The stored
 * status is what the record declares; "overdue" is derived at read time from due_on and
 * today, so it never goes stale between imports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enforcement_events', function (Blueprint $table) {
            $table->string('slug', 160)->nullable()->index();
            $table->string('kind', 24)->nullable()->index();
            $table->string('regulator')->nullable();
            $table->string('respondent')->nullable();
            $table->decimal('amount', 20, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('legal_basis')->nullable();
            $table->string('appeal_status', 24)->nullable();
        });

        Schema::create('implementation_measures', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('policy_instrument_id')->nullable()->constrained('policy_instruments')->nullOnDelete();
            $table->foreignId('related_policy_instrument_id')->nullable()->constrained('policy_instruments')->nullOnDelete();
            $table->string('kind', 32)->index();
            $table->string('title');
            $table->string('status', 24)->index();
            $table->string('legal_basis')->nullable();
            $table->text('summary')->nullable();
            $table->date('due_on')->nullable();
            $table->date('adopted_on')->nullable();
            $table->date('published_on')->nullable();
            $table->string('published_on_precision', 8)->default('exact');
            $table->string('body', 32)->nullable()->index();
            $table->string('reference')->nullable();
            $table->string('stage')->nullable();
            $table->date('oj_citation_expected_on')->nullable();
            $table->date('oj_citation_on')->nullable();
            $table->text('notes')->nullable();
            $table->string('official_source_url', 2048)->nullable();
            $table->string('source_title')->nullable();
            $table->string('source_publisher')->nullable();
            $table->date('source_document_date')->nullable();
            $table->string('source_reference')->nullable();
            $table->unsignedTinyInteger('source_tier')->default(4);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->string('review_status', 32)->default('draft');
            $table->string('confidence_level', 16)->default('low');
            $table->unsignedInteger('content_version')->default(1);
            $table->text('change_summary')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('implementation_measures');
        Schema::table('enforcement_events', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropIndex(['kind']);
            $table->dropColumn(['slug', 'kind', 'regulator', 'respondent', 'amount', 'currency', 'legal_basis', 'appeal_status']);
        });
    }
};
