<?php

use App\Services\ExternalData\IncidentEnrichment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: an incident record says what happened; this site adds the policy angle.
// The harm domain from its coding, the recorded laws that address that harm
// where it happened, one neutral sentence tying the two together, and a
// sensitivity flag for the records about sexual imagery, which are kept out of
// search. The importers never write these columns, so a re-import cannot undo
// a reviewer's override (data/external/incident_overrides.yaml).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_incidents', function (Blueprint $table) {
            $table->string('harm_domain', 64)->nullable()->index();
            $table->string('sensitivity', 16)->nullable()->index();
            $table->text('policy_angle')->nullable();
            $table->json('related_policy_slugs')->nullable();
        });

        // Rows already present are classified now; on an empty database the
        // first import does it.
        IncidentEnrichment::apply();
    }

    public function down(): void
    {
        Schema::table('external_incidents', function (Blueprint $table) {
            $table->dropIndex(['harm_domain']);
            $table->dropIndex(['sensitivity']);
            $table->dropColumn(['harm_domain', 'sensitivity', 'policy_angle', 'related_policy_slugs']);
        });
    }
};
