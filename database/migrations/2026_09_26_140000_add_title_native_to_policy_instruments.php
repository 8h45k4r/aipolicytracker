<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The instrument's name in its own language, where a reviewer recorded it (P6 country hubs). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('policy_instruments', function (Blueprint $table) {
            $table->string('title_native')->nullable()->after('short_title');
        });
    }

    public function down(): void
    {
        Schema::table('policy_instruments', function (Blueprint $table) {
            $table->dropColumn('title_native');
        });
    }
};
