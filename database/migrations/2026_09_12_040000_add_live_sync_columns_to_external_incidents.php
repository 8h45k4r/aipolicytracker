<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns filled by the live AI Incident Database sync (external:sync-aiid-api):
 * editor notes, entity identifiers, implicated systems, similar incidents, the
 * record's last modification on AIID and when this row was last synced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_incidents', function (Blueprint $table) {
            $table->text('editor_notes')->nullable();
            $table->json('entities')->nullable();
            $table->json('implicated_systems')->nullable();
            $table->json('similar_incidents')->nullable();
            $table->dateTime('modified_at')->nullable()->index();
            $table->dateTime('synced_at')->nullable()->index();
        });
        Schema::table('external_incident_reports', function (Blueprint $table) {
            $table->dateTime('synced_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('external_incidents', function (Blueprint $table) {
            $table->dropColumn(['editor_notes', 'entities', 'implicated_systems', 'similar_incidents', 'modified_at', 'synced_at']);
        });
        Schema::table('external_incident_reports', function (Blueprint $table) {
            $table->dropColumn('synced_at');
        });
    }
};
