<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** P9: AI economic transition measures, indicators and the computed displacement policy index snapshots. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transition_measures', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('jurisdiction_id')->constrained('jurisdictions')->cascadeOnDelete();
            $table->string('title');
            $table->string('measure_type', 32)->index();
            $table->string('status', 24)->index();
            $table->text('summary')->nullable();
            $table->text('mechanism')->nullable();
            $table->text('funding')->nullable();
            $table->text('trigger')->nullable();
            $table->text('benefit')->nullable();
            $table->text('cost')->nullable();
            $table->string('bill_number')->nullable();
            $table->json('sponsors')->nullable();
            $table->date('introduced_on')->nullable();
            $table->date('enacted_on')->nullable();
            $table->date('in_force_on')->nullable();
            $table->json('arguments_for')->nullable();
            $table->json('arguments_against')->nullable();
            $table->json('sources')->nullable();
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

        Schema::create('transition_indicators', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('jurisdiction_id')->nullable()->constrained('jurisdictions')->nullOnDelete();
            $table->string('title');
            $table->string('unit', 64);
            $table->text('description')->nullable();
            $table->string('frequency', 16)->default('irregular');
            $table->json('series')->nullable();
            $table->string('official_source_url', 2048)->nullable();
            $table->string('source_publisher')->nullable();
            $table->unsignedTinyInteger('source_tier')->default(4);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->string('review_status', 32)->default('draft');
            $table->string('confidence_level', 16)->default('low');
            $table->string('reviewed_by')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('displacement_index_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jurisdiction_id')->constrained('jurisdictions')->cascadeOnDelete();
            $table->string('quarter', 7); // 2026-Q3
            $table->string('version', 8);
            $table->unsignedTinyInteger('score');
            $table->json('subscores');
            $table->json('inputs');
            $table->timestamp('computed_at');
            $table->timestamps();
            $table->unique(['jurisdiction_id', 'quarter', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('displacement_index_snapshots');
        Schema::dropIfExists('transition_indicators');
        Schema::dropIfExists('transition_measures');
    }
};
