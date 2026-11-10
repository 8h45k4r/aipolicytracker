<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Whether an instrument is about AI itself. Pages that say a jurisdiction has, or lacks,
 * binding AI law counted every binding instrument, so a data-protection act made India
 * read as having binding AI law while its own page said it had none. The value comes from
 * data/ on every import; existing rows take the default until the next import.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('policy_instruments', function (Blueprint $table) {
            $table->boolean('ai_specific')->default(true)->after('is_binding');
        });
    }

    public function down(): void
    {
        Schema::table('policy_instruments', function (Blueprint $table) {
            $table->dropColumn('ai_specific');
        });
    }
};
