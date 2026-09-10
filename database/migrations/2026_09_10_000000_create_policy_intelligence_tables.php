<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Policy-intelligence schema: jurisdictions, policy instruments, obligations,
 * deadlines, change events, sources, and the contributor review workflow.
 *
 * Source-quality columns are shared by every table that makes a factual claim
 * (see addSourceQualityColumns). The canonical data lives in the data/ directory;
 * this schema is the read model that the site and API serve from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurisdictions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('iso_code', 8)->nullable();
            $table->string('jurisdiction_type', 32)->default('country'); // supranational|country|state|territory
            $table->string('region', 64)->nullable();
            $table->string('subregion', 64)->nullable();
            $table->foreignId('parent_jurisdiction_id')->nullable()->constrained('jurisdictions')->nullOnDelete();
            $table->text('overview')->nullable();
            $table->text('regulatory_status_summary')->nullable();
            $table->text('binding_vs_guidance')->nullable();
            $table->text('current_priorities')->nullable();
            $table->json('how_to_use')->nullable();
            $table->json('regulators')->nullable();
            $table->json('official_sources')->nullable();
            $table->json('faq')->nullable();
            $table->json('related_jurisdictions')->nullable();
            $table->boolean('featured')->default(false);
            $this->addSourceQualityColumns($table);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('policy_instruments', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('jurisdiction_id')->constrained('jurisdictions')->cascadeOnDelete();
            $table->string('title');
            $table->string('short_title')->nullable();
            $table->string('instrument_type', 32);
            $table->string('status', 32)->index();
            $table->text('status_note')->nullable();
            $table->boolean('is_binding')->default(false);
            $table->string('issuing_body')->nullable();
            $table->text('summary_plain')->nullable();
            $table->text('scope_summary')->nullable();
            $table->text('who_it_applies_to')->nullable();
            $table->text('key_dates_summary')->nullable();
            $table->text('penalties_summary')->nullable();
            $table->text('what_organizations_must_do')->nullable();
            $table->date('adopted_on')->nullable();
            $table->date('published_on')->nullable();
            $table->date('in_force_on')->nullable();
            $table->date('applies_from')->nullable()->index();
            $table->text('date_notes')->nullable();
            $table->json('faq')->nullable();
            $table->json('related_policies')->nullable();
            $table->json('related_frameworks')->nullable();
            $table->boolean('featured')->default(false);
            $this->addSourceQualityColumns($table);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('policy_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_instrument_id')->constrained('policy_instruments')->cascadeOnDelete();
            $table->string('version_label');
            $table->date('version_date')->nullable();
            $table->text('summary')->nullable();
            $table->string('official_source_url', 2048)->nullable();
            $table->string('source_reference')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('policy_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_instrument_id')->constrained('policy_instruments')->cascadeOnDelete();
            $table->string('reference');
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->string('official_source_url', 2048)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('obligations', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('policy_instrument_id')->constrained('policy_instruments')->cascadeOnDelete();
            $table->foreignId('policy_section_id')->nullable()->constrained('policy_sections')->nullOnDelete();
            $table->string('title');
            $table->string('category', 48)->index();
            $table->text('summary')->nullable();
            $table->text('practical_action')->nullable();
            $table->boolean('is_binding')->default(false);
            $table->date('applies_from')->nullable();
            $table->string('source_reference')->nullable();
            $table->string('official_source_url', 2048)->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->string('review_status', 32)->default('pending_review');
            $table->string('confidence_level', 16)->default('medium');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('applicability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_instrument_id')->constrained('policy_instruments')->cascadeOnDelete();
            $table->foreignId('obligation_id')->nullable()->constrained('obligations')->cascadeOnDelete();
            $table->text('description');
            $table->json('actors')->nullable();
            $table->json('ai_system_types')->nullable();
            $table->json('sectors')->nullable();
            $table->json('risk_categories')->nullable();
            $table->json('use_cases')->nullable();
            $table->text('conditions')->nullable();
            $table->string('source_reference')->nullable();
            $table->timestamps();
        });

        Schema::create('taxonomy_terms', function (Blueprint $table) {
            $table->id();
            $table->string('taxonomy', 32); // actor|ai_system_type|sector|risk_category|use_case|obligation_category|framework
            $table->string('slug');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['taxonomy', 'slug']);
        });

        Schema::create('taxonomy_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taxonomy_term_id')->constrained('taxonomy_terms')->cascadeOnDelete();
            $table->string('assignable_type');
            $table->unsignedBigInteger('assignable_id');
            $table->timestamps();
            $table->index(['assignable_type', 'assignable_id']);
            $table->unique(['taxonomy_term_id', 'assignable_type', 'assignable_id'], 'taxonomy_assignment_unique');
        });

        Schema::create('deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_instrument_id')->constrained('policy_instruments')->cascadeOnDelete();
            $table->foreignId('obligation_id')->nullable()->constrained('obligations')->nullOnDelete();
            $table->string('title');
            $table->date('due_on')->nullable()->index();
            $table->string('date_precision', 16)->default('exact'); // exact|month|year|tbd
            $table->string('date_label')->nullable();
            $table->text('description')->nullable();
            $table->string('source_reference')->nullable();
            $table->string('official_source_url', 2048)->nullable();
            $table->string('deadline_status', 16)->default('scheduled'); // scheduled|passed|tbd|superseded
            $table->string('confidence_level', 16)->default('medium');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('enforcement_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jurisdiction_id')->constrained('jurisdictions')->cascadeOnDelete();
            $table->foreignId('policy_instrument_id')->nullable()->constrained('policy_instruments')->nullOnDelete();
            $table->string('title');
            $table->date('occurred_on')->nullable();
            $table->string('authority')->nullable();
            $table->text('summary')->nullable();
            $table->text('outcome')->nullable();
            $this->addSourceQualityColumns($table);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jurisdiction_id')->constrained('jurisdictions')->cascadeOnDelete();
            $table->foreignId('policy_instrument_id')->nullable()->constrained('policy_instruments')->nullOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('applies_to')->nullable();
            $this->addSourceQualityColumns($table);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('framework_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obligation_id')->constrained('obligations')->cascadeOnDelete();
            $table->string('framework', 32); // iso_42001|nist_ai_rmf|iso_27001|...
            $table->string('reference');
            $table->text('note')->nullable();
            $table->string('confidence_level', 16)->default('medium');
            $table->boolean('is_original')->default(true);
            $table->timestamps();
        });

        Schema::create('evidence_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obligation_id')->constrained('obligations')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('artifact_type', 32)->default('document');
            $table->timestamps();
        });

        Schema::create('change_events', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('jurisdiction_id')->constrained('jurisdictions')->cascadeOnDelete();
            $table->foreignId('policy_instrument_id')->nullable()->constrained('policy_instruments')->nullOnDelete();
            $table->date('occurred_on')->index();
            $table->string('title');
            $table->text('what_changed');
            $table->text('practical_impact')->nullable();
            $table->string('impact_level', 16)->default('routine')->index();
            $table->string('status_after', 32)->nullable();
            $this->addSourceQualityColumns($table);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('source_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jurisdiction_id')->nullable()->constrained('jurisdictions')->cascadeOnDelete();
            $table->foreignId('policy_instrument_id')->nullable()->constrained('policy_instruments')->cascadeOnDelete();
            $table->string('title');
            $table->string('publisher')->nullable();
            $table->string('url', 2048);
            $table->date('document_date')->nullable();
            $table->string('document_type', 32)->default('official'); // legislation|guidance|consultation|press_release|standard|secondary
            $table->unsignedTinyInteger('source_tier')->default(1);
            $table->string('language', 8)->default('en');
            $table->string('license_note')->nullable();
            $table->timestamp('retrieved_at')->nullable();
            $table->string('snapshot_path')->nullable();
            $table->string('checksum', 128)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('contributor_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32); // correction|new_source|new_policy|reviewer_application
            $table->string('subject_type', 32)->nullable(); // policy|jurisdiction|obligation|change|other
            $table->string('subject_slug')->nullable();
            $table->string('summary');
            $table->text('details')->nullable();
            $table->string('proposed_source_url', 2048)->nullable();
            $table->string('submitter_name')->nullable();
            $table->string('submitter_email')->nullable();
            $table->string('submitter_affiliation')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 32)->default('pending_review')->index();
            $table->string('source_page', 2048)->nullable();
            $table->timestamps();
        });

        Schema::create('reviewer_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contributor_submission_id')->constrained('contributor_submissions')->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 32);
            $table->text('notes')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'reviewer_decisions', 'contributor_submissions', 'source_documents', 'change_events',
            'evidence_artifacts', 'framework_mappings', 'procurement_rules', 'enforcement_events',
            'deadlines', 'taxonomy_assignments', 'taxonomy_terms', 'applicability_rules', 'obligations',
            'policy_sections', 'policy_versions', 'policy_instruments', 'jurisdictions',
        ] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }

    private function addSourceQualityColumns(Blueprint $table): void
    {
        $table->string('official_source_url', 2048)->nullable();
        $table->string('source_title')->nullable();
        $table->string('source_publisher')->nullable();
        $table->date('source_document_date')->nullable();
        $table->string('source_reference')->nullable();
        $table->unsignedTinyInteger('source_tier')->default(1);
        $table->timestamp('last_checked_at')->nullable();
        $table->timestamp('last_verified_at')->nullable();
        $table->string('review_status', 32)->default('pending_review');
        $table->string('confidence_level', 16)->default('medium');
        $table->unsignedInteger('content_version')->default(1);
        $table->text('change_summary')->nullable();
        $table->string('reviewed_by')->nullable();
    }
};
