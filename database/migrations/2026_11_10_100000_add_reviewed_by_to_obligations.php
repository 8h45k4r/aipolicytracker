<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('obligations', function (Blueprint $table) {
            $table->string('reviewed_by')->nullable()->after('last_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('obligations', function (Blueprint $table) {
            $table->dropColumn('reviewed_by');
        });
    }
};
