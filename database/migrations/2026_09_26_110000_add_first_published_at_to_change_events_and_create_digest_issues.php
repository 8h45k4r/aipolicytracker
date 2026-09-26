<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Why: published_at is rewritten to "now" on every import, so it says when the
// data was last loaded, not when an entry first appeared. A news sitemap needs
// the second thing. first_published_at is set once, when the row is created,
// and never by the importer. Existing rows take their created_at, which on a
// database that has been running is exactly that moment.
//
// digest_issues records each weekly digest as it is sent, so the newsletter has
// an archive at /newsletter/<date> rather than existing only in inboxes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('change_events', function (Blueprint $table) {
            $table->timestamp('first_published_at')->nullable()->index();
        });
        DB::table('change_events')->whereNull('first_published_at')->update(['first_published_at' => DB::raw('created_at')]);

        Schema::create('digest_issues', function (Blueprint $table) {
            $table->id();
            $table->date('sent_on')->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->json('change_ids');
            $table->json('deadline_ids');
            $table->unsignedInteger('incident_count')->default(0);
            $table->unsignedInteger('recipients')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digest_issues');
        Schema::table('change_events', function (Blueprint $table) {
            $table->dropIndex(['first_published_at']);
            $table->dropColumn('first_published_at');
        });
    }
};
