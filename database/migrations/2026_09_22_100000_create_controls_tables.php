<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: an obligation said what the law requires and listed example evidence, but nothing
// stood between the two. A control is the thing an organisation actually operates — a
// policy, a process, a technical measure, a contract term, a training programme — and it is
// what produces the evidence. One control usually serves several duties in several
// jurisdictions, which is the reuse a compliance lead is trying to find. These tables hold
// the control, the evidence it produces, the standards clauses it corresponds to, and the
// many-to-many link to the obligations it satisfies or supports.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('controls', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('kind', 16)->index();           // policy|process|technical|contractual|training
            $table->text('purpose');
            $table->text('description')->nullable();
            $table->string('owner_role', 80);
            $table->string('frequency', 24);                // once|per_system|on_material_change|continuous|quarterly|annual
            $table->json('risk_subdomains')->nullable();    // MIT AI Risk Repository subdomain ids
            $table->json('related_controls')->nullable();   // slugs
            $table->string('review_status', 32)->default('pending_review');
            $table->string('confidence_level', 16)->default('medium');
            $table->timestamp('last_verified_at')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('control_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('control_id')->constrained('controls')->cascadeOnDelete();
            $table->string('evidence_type', 48)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('control_framework_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('control_id')->constrained('controls')->cascadeOnDelete();
            $table->string('framework', 32)->index();
            $table->string('reference');
            $table->text('note')->nullable();
            $table->string('confidence_level', 16)->default('medium');
            $table->timestamps();
        });

        Schema::create('control_obligation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('control_id')->constrained('controls')->cascadeOnDelete();
            $table->foreignId('obligation_id')->constrained('obligations')->cascadeOnDelete();
            $table->string('relationship', 16)->default('supports'); // satisfies|supports
            $table->text('note')->nullable();
            $table->string('confidence_level', 16)->default('medium');
            $table->timestamps();
            $table->unique(['control_id', 'obligation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_obligation');
        Schema::dropIfExists('control_framework_references');
        Schema::dropIfExists('control_evidence');
        Schema::dropIfExists('controls');
    }
};
