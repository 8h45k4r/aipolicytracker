<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The topic of a duty as people search for it ("Human oversight"), used in the
// page title next to the instrument and article. The full title stays the heading.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('obligations', function (Blueprint $table) {
            $table->string('short_title', 80)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('obligations', function (Blueprint $table) {
            $table->dropColumn('short_title');
        });
    }
};
