<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Official consultations and comment deadlines on AI. Rows are collected from official
 * sources (today the US Federal Register API) and stay `collected` until a reviewer opens
 * the official page and confirms them; the public calendar keeps the two apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->string('source', 40);                 // federal_register
            $table->string('external_id', 120);           // the source's own id (FR document number)
            $table->string('jurisdiction_slug', 80)->nullable();
            $table->string('title', 500);
            $table->string('agency', 300)->nullable();
            $table->string('document_type', 60)->nullable();
            $table->text('abstract')->nullable();
            $table->string('url', 500);                   // the official page
            $table->string('comment_url', 500)->nullable();
            $table->date('published_on')->nullable();
            $table->date('comments_close_on')->nullable();
            $table->string('status', 20)->default('collected'); // collected, confirmed, hidden
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
            $table->unique(['source', 'external_id']);
            $table->index(['status', 'comments_close_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
