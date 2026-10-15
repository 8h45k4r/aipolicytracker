<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Permanent record identifiers (AIPT-POL-0001 and so on), assigned in data/ and never
// reused, so a citation, a GRC ticket or a calendar entry keeps pointing at the same
// record when its slug or title changes.
return new class extends Migration
{
    private const TABLES = ['policy_instruments', 'obligations', 'change_events', 'controls'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('aipt_id', 24)->nullable()->unique()->after('slug');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropUnique($table.'_aipt_id_unique');
                $t->dropColumn('aipt_id');
            });
        }
    }
};
