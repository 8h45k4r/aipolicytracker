<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: researchers need to filter, chart and export the full AI Incident Database
// (metadata only, CC BY-SA 4.0) and the MIT AI Risk Repository database (CC BY 4.0),
// not just weekly aggregates. Rows are imported from reviewed JSON in data/external/
// by `external:import`; the JSON is the source of truth and is refreshed weekly by PR.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_incidents', function (Blueprint $table) {
            $table->unsignedInteger('incident_id')->primary();
            $table->date('occurred_on')->index();
            $table->unsignedSmallInteger('year')->index();
            $table->string('title', 200);
            $table->string('description', 500)->nullable();
            $table->json('deployers')->nullable();
            $table->json('developers')->nullable();
            $table->json('harmed')->nullable();
            $table->unsignedSmallInteger('report_count')->default(0);
            $table->string('mit_domain', 80)->nullable()->index();
            $table->string('mit_subdomain', 120)->nullable()->index();
            $table->string('entity', 40)->nullable();
            $table->string('intent', 40)->nullable();
            $table->string('timing', 40)->nullable();
            $table->json('sectors')->nullable();
            $table->json('countries')->nullable();
            $table->string('harm_level', 60)->nullable()->index();
            $table->date('snapshot_date')->nullable();
            $table->timestamps();
        });

        Schema::create('external_risks', function (Blueprint $table) {
            $table->string('ev_id', 32)->primary();
            $table->string('quick_ref', 80)->index();
            $table->string('paper_title', 200);
            $table->string('level', 32)->index();             // Risk Category | Risk Sub-Category | Additional evidence
            $table->string('risk_category', 200)->nullable();
            $table->string('risk_subcategory', 200)->nullable();
            $table->string('description', 600)->nullable();
            $table->string('entity', 24)->nullable()->index();
            $table->string('intent', 24)->nullable()->index();
            $table->string('timing', 24)->nullable()->index();
            $table->unsignedTinyInteger('domain')->nullable()->index();
            $table->string('subdomain', 8)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_risks');
        Schema::dropIfExists('external_incidents');
    }
};
