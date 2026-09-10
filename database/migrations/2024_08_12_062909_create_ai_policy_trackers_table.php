<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_policy_trackers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('country_id');
            $table->uuid('status_id');

            $table->string('ai_policy_name')->nullable()->default(null);
            $table->string('governing_body')->nullable()->default(null);
            $table->string('announcement_year')->nullable()->default(null);
            $table->string('whitepaper_document_link')->nullable()->default(null);
            $table->string('technology_partners')->nullable()->default(null);
            $table->string('governance_structure')->nullable()->default(null);
            $table->string('main_motivation')->nullable()->default(null);
            $table->longText('description')->nullable()->default(null);

            $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');
            $table->foreign('status_id')->references('id')->on('statuses')->onDelete('cascade');

            $table->softDeletes();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_policy_trackers');
    }
};
