<?php

use App\Services\ExternalData\RecordSlugs;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: incident and risk pages were published at their source keys
// ("/ai-risk/incidents/1382", "/ai-risk/risks/05.17.00"), and those keys leaked
// into titles and into search queries. Each record gets a readable address,
// assigned once; the old ones redirect. The importers never write this column,
// so a re-import cannot move an address that has been published.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_incidents', function (Blueprint $table) {
            $table->string('slug', 96)->nullable()->unique();
        });
        Schema::table('external_risks', function (Blueprint $table) {
            $table->string('slug', 96)->nullable()->unique();
        });

        // Rows already present get their addresses now, so a deploy never
        // serves a page without one. On an empty database this does nothing,
        // and the first import assigns them.
        RecordSlugs::assignIncidents();
        RecordSlugs::assignRisks();
    }

    public function down(): void
    {
        Schema::table('external_incidents', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
        Schema::table('external_risks', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
