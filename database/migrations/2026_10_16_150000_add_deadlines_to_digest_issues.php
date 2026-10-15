<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deadline rows are rebuilt on every import, so an id stored in a past issue soon names
 * another deadline or none. The issue now keeps the dates it carried by value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digest_issues', function (Blueprint $table) {
            $table->json('deadlines')->nullable()->after('deadline_ids');
        });
    }

    public function down(): void
    {
        Schema::table('digest_issues', function (Blueprint $table) {
            $table->dropColumn('deadlines');
        });
    }
};
