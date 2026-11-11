<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A saved applicability profile describes one AI system the account declares
 * (no discovery: the customer says what the system is). The workbook built from
 * it is versioned: a new version is stored only when the screened records change,
 * so the account can see what moved and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicability_profiles', function (Blueprint $table) {
            $table->string('system_description', 1000)->nullable()->after('name');
            $table->string('system_owner', 120)->nullable()->after('system_description');
            $table->string('lifecycle', 20)->nullable()->after('system_owner');
        });

        Schema::create('profile_workbook_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicability_profile_id')->constrained('applicability_profiles')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->char('content_hash', 64);
            $table->json('snapshot');            // the screened rows the workbook was built from
            $table->json('record_changes')->nullable(); // added, removed and changed records against the previous version
            $table->string('dataset_version', 40)->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->unique(['applicability_profile_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_workbook_versions');
        Schema::table('applicability_profiles', function (Blueprint $table) {
            $table->dropColumn(['system_description', 'system_owner', 'lifecycle']);
        });
    }
};
