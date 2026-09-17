<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Why: /corrections publishes what happened to every report a reader sends, which is the
// only way a reader can tell whether reporting an error achieves anything. It must do that
// without republishing text the site never moderated: a submitter's own words and the
// reviewer's internal `notes` were both written for the review queue, not for the public.
// This column holds a note the reviewer writes deliberately for publication, so the public
// log carries a sentence somebody chose to publish rather than one it scraped from an
// internal field. Null means the entry publishes its structured facts only.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviewer_decisions', function (Blueprint $table) {
            $table->string('public_note', 500)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('reviewer_decisions', function (Blueprint $table) {
            $table->dropColumn('public_note');
        });
    }
};
