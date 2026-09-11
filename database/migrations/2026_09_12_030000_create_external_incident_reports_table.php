<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: incident profiles only linked out to the AI Incident Database. The weekly AIID backup
// lists the news reports behind each incident; storing their metadata (title, source, URL,
// date, authors; never the article text) lets each profile show its coverage directly.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_incident_reports', function (Blueprint $table) {
            $table->unsignedInteger('report_number')->primary();
            $table->unsignedInteger('incident_id')->index();
            $table->string('title', 300);
            $table->string('url', 2048);
            $table->string('source_domain', 190)->nullable()->index();
            $table->date('date_published')->nullable()->index();
            $table->json('authors')->nullable();
            $table->string('language', 8)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_incident_reports');
    }
};
