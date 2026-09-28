<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per request for a template: who asked, for which template, and whether
 * the emailed links were used. The links carry the row's id, so a download is
 * attributed to the request that produced it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_download_requests', function (Blueprint $table) {
            $table->id();
            $table->string('template_slug', 120)->index();
            $table->string('name', 120);
            $table->string('email', 190)->index();
            $table->string('company', 160);
            $table->string('job_title', 120)->nullable();
            $table->string('country', 80)->nullable();
            $table->timestamp('terms_accepted_at');
            $table->timestamp('marketing_consent_at')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('referrer', 512)->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('first_downloaded_at')->nullable();
            $table->unsignedInteger('downloads')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_download_requests');
    }
};
