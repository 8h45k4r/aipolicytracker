<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Schema::table('user_infos', function (Blueprint $table) {
        //     $table->string('password')->nullable()->default(null);
        //     $table->boolean('status')->nullable()->default(true);
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::table('user_infos', function (Blueprint $table) {
        //     $table->dropColumn("password");
        //     $table->dropColumn('status');
        // });
    }
};
