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
        Schema::table('ai_policy_trackers', function (Blueprint $table) {
            $table->string('gov_ai_index')->default('policy');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_policy_trackers', function (Blueprint $table) {
            //
        });
    }
};
